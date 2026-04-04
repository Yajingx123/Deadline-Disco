const http = require('http')
const { WebSocketServer } = require('ws')

const HOST = process.env.REALTIME_HOST || '127.0.0.1'
const PORT = Number(process.env.REALTIME_PORT || 3001)
const DEFAULT_ALLOWED_ORIGINS = [
  'http://127.0.0.1:5173',
  'http://127.0.0.1:5174',
  'http://127.0.0.1:5500',
  'http://127.0.0.1:8001',
]
const ALLOWED_ORIGINS = new Set(
  String(process.env.REALTIME_ALLOWED_ORIGINS || DEFAULT_ALLOWED_ORIGINS.join(','))
    .split(',')
    .map((v) => v.trim())
    .filter(Boolean)
)
const CORS_ALLOW_ALL = process.env.REALTIME_CORS_ALLOW_ALL === '1'

function corsOriginForRequest(origin) {
  if (CORS_ALLOW_ALL) return '*'
  if (!origin) return ''
  return ALLOWED_ORIGINS.has(origin) ? origin : ''
}

function sendJson(req, res, statusCode, payload) {
  const origin = corsOriginForRequest(req.headers.origin)
  const headers = {
    'Content-Type': 'application/json; charset=utf-8',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization',
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
  }
  if (origin !== '') {
    headers['Access-Control-Allow-Origin'] = origin
    headers.Vary = 'Origin'
  }
  res.writeHead(statusCode, headers)
  res.end(JSON.stringify(payload))
}

const server = http.createServer((req, res) => {
  if (!req.url) {
    return sendJson(req, res, 404, { ok: false, message: 'Not found.' })
  }

  if (req.method === 'OPTIONS') {
    const origin = corsOriginForRequest(req.headers.origin)
    const headers = {
      'Access-Control-Allow-Headers': 'Content-Type, Authorization',
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    }
    if (origin !== '') {
      headers['Access-Control-Allow-Origin'] = origin
      headers.Vary = 'Origin'
    }
    res.writeHead(204, headers)
    res.end()
    return
  }

  if (req.method === 'GET' && req.url === '/health') {
    return sendJson(req, res, 200, { ok: true, status: 'healthy' })
  }

  if (req.method === 'POST' && req.url === '/publish') {
    const chunks = []
    req.on('data', (chunk) => chunks.push(chunk))
    req.on('end', () => {
      try {
        const body = Buffer.concat(chunks).toString('utf8')
        const payload = body ? JSON.parse(body) : {}
        const event = {
          type: String(payload.type || 'system.update'),
          data: payload.data && typeof payload.data === 'object' ? payload.data : {},
          occurredAt: new Date().toISOString(),
        }

        let delivered = 0
        wss.clients.forEach((client) => {
          if (client.readyState === 1) {
            client.send(JSON.stringify(event))
            delivered += 1
          }
        })

        sendJson(req, res, 200, { ok: true, delivered })
      } catch (_error) {
        sendJson(req, res, 400, { ok: false, message: 'Invalid JSON payload.' })
      }
    })
    return
  }

  sendJson(req, res, 404, { ok: false, message: 'Not found.' })
})

const wss = new WebSocketServer({ noServer: true })

wss.on('connection', (socket) => {
  socket.send(JSON.stringify({
    type: 'system.ready',
    data: { connected: true },
    occurredAt: new Date().toISOString(),
  }))
})

server.on('upgrade', (req, socket, head) => {
  const origin = req.headers.origin
  if (!CORS_ALLOW_ALL && origin && !ALLOWED_ORIGINS.has(origin)) {
    socket.write('HTTP/1.1 403 Forbidden\r\n\r\n')
    socket.destroy()
    return
  }

  if (req.url !== '/ws') {
    socket.write('HTTP/1.1 404 Not Found\r\n\r\n')
    socket.destroy()
    return
  }

  wss.handleUpgrade(req, socket, head, (ws) => {
    wss.emit('connection', ws, req)
  })
})

server.listen(PORT, HOST, () => {
  console.log(`Realtime relay listening on http://${HOST}:${PORT}`)
})
