const API_ORIGIN = 'http://127.0.0.1:8001'

export const HOME_URL = `${API_ORIGIN}/home.html`

async function parseResponse(response) {
  const data = await response.json().catch(() => ({
    ok: false,
    status: 'error',
    message: 'Invalid server response.'
  }))

  if (!response.ok) {
    const error = new Error(data.message || data.status || 'Request failed.')
    error.status = response.status
    error.data = data
    throw error
  }

  return data
}

export async function adminFetch(path, options = {}) {
  const response = await fetch(`${API_ORIGIN}${path}`, {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {})
    },
    credentials: 'include',
    body: options.body || undefined
  })

  return parseResponse(response)
}

export function redirectToLogin() {
  window.location.replace(`${HOME_URL}?login=1`)
}

export function redirectToHome() {
  window.location.replace(HOME_URL)
}
