import { useEffect, useMemo, useRef, useState } from 'react'

const CANVAS_WIDTH = 960
const CANVAS_HEIGHT = 540

function secondsRemaining(targetTime) {
  if (!targetTime) return null
  const target = new Date(targetTime).getTime()
  if (!Number.isFinite(target)) return null
  return Math.max(0, Math.ceil((target - Date.now()) / 1000))
}

function formatClock(value) {
  const total = Math.max(0, Number(value || 0))
  const minutes = Math.floor(total / 60)
  const seconds = total % 60
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}

function drawStrokeSegment(ctx, stroke) {
  if (!ctx) return
  const payload = stroke?.payload || {}
  if (stroke?.eventType === 'clear' || payload?.type === 'clear') {
    ctx.clearRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT)
    return
  }
  const from = payload?.from || null
  const to = payload?.to || null
  if (!from || !to) return

  if (payload.tool === 'eraser') {
    ctx.save()
    ctx.globalCompositeOperation = 'destination-out'
    ctx.strokeStyle = 'rgba(0,0,0,1)'
    ctx.lineWidth = Number(payload.size || 16)
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
    ctx.beginPath()
    ctx.moveTo(Number(from.x || 0), Number(from.y || 0))
    ctx.lineTo(Number(to.x || 0), Number(to.y || 0))
    ctx.stroke()
    ctx.restore()
    return
  }

  ctx.save()
  ctx.strokeStyle = String(payload.color || '#1f2937')
  ctx.lineWidth = Number(payload.size || 4)
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.beginPath()
  ctx.moveTo(Number(from.x || 0), Number(from.y || 0))
  ctx.lineTo(Number(to.x || 0), Number(to.y || 0))
  ctx.stroke()
  ctx.restore()
}

function replayCanvas(canvas, strokes) {
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return
  ctx.clearRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT)
  ctx.fillStyle = '#fffdf8'
  ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT)
  strokes.forEach((stroke) => drawStrokeSegment(ctx, stroke))
}

function useCountdown(targetTime) {
  const [remaining, setRemaining] = useState(() => secondsRemaining(targetTime))

  useEffect(() => {
    setRemaining(secondsRemaining(targetTime))
    if (!targetTime) {
      return undefined
    }
    const timer = window.setInterval(() => {
      setRemaining(secondsRemaining(targetTime))
    }, 1000)
    return () => window.clearInterval(timer)
  }, [targetTime])

  return remaining
}

function ToolIcon({ kind }) {
  if (kind === 'brush') {
    return (
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M14.5 4.5 19.5 9.5 9 20H4v-5.1Z" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" />
        <path d="M13.7 5.3 18.7 10.3" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
      </svg>
    )
  }
  if (kind === 'eraser') {
    return (
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M7 15 14.8 7.2a2 2 0 0 1 2.8 0l2.2 2.2a2 2 0 0 1 0 2.8L14 18H9.2L7 15Z" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" />
        <path d="M4 18h16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
      </svg>
    )
  }
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M5 7h14M9 7V5h6v2m-8 0 1 12h8l1-12" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}

function ToolButton({ kind, label, active = false, onClick }) {
  return (
    <button
      type="button"
      className={`draw-guess__iconButton ${active ? 'is-active' : ''}`}
      onClick={onClick}
      title={label}
      aria-label={label}
    >
      <ToolIcon kind={kind} />
    </button>
  )
}

function medalForRank(index) {
  if (index === 0) return '👑'
  if (index === 1) return '🥈'
  if (index === 2) return '🥉'
  return '⭐'
}

export default function DrawGuessPanel({
  game,
  onCreateLobby,
  onToggleReady,
  onStartGame,
  onPickWord,
  onSubmitGuess,
  onStroke,
  onClearCanvas,
  onLeaveGame,
  onExitGameView,
}) {
  const canvasRef = useRef(null)
  const pointerRef = useRef(null)
  const [guessText, setGuessText] = useState('')
  const [tool, setTool] = useState('brush')
  const [color, setColor] = useState('#1f2937')
  const [size, setSize] = useState(4)

  const selectionSeconds = useCountdown(game?.selectionDeadlineAt || null)
  const playSeconds = useCountdown(game?.roundEndsAt || null)
  const playerList = Array.isArray(game?.players) ? game.players : []
  const viewer = game?.viewer || {}
  const drawer = playerList.find((player) => Number(player.userId) === Number(game?.currentDrawerId || 0)) || null
  const leaderboard = useMemo(
    () => [...playerList].sort((a, b) => Number(b.score || 0) - Number(a.score || 0)),
    [playerList],
  )
  const guessFeed = Array.isArray(game?.recentGuesses) ? game.recentGuesses : []
  const hints = Array.isArray(game?.hints) ? game.hints : []

  useEffect(() => {
    replayCanvas(canvasRef.current, Array.isArray(game?.strokes) ? game.strokes : [])
  }, [game?.strokes])

  useEffect(() => {
    if (game?.status === 'PLAYING') {
      setTool('brush')
    }
  }, [game?.status, game?.roundIndex])

  const translatePoint = (event) => {
    const canvas = canvasRef.current
    if (!canvas) return null
    const rect = canvas.getBoundingClientRect()
    if (!rect.width || !rect.height) return null
    return {
      x: ((event.clientX - rect.left) / rect.width) * CANVAS_WIDTH,
      y: ((event.clientY - rect.top) / rect.height) * CANVAS_HEIGHT,
    }
  }

  const canDraw = Boolean(viewer?.isDrawer) && game?.status === 'PLAYING'

  const handlePointerDown = (event) => {
    if (!canDraw) return
    const point = translatePoint(event)
    if (!point) return
    pointerRef.current = point
    if (event.currentTarget?.setPointerCapture) {
      event.currentTarget.setPointerCapture(event.pointerId)
    }
  }

  const handlePointerMove = (event) => {
    if (!canDraw || !pointerRef.current || !(event.buttons & 1)) return
    const nextPoint = translatePoint(event)
    if (!nextPoint) return
    const payload = {
      tool,
      color,
      size,
      from: pointerRef.current,
      to: nextPoint,
    }
    drawStrokeSegment(canvasRef.current?.getContext('2d'), { eventType: 'stroke', payload })
    pointerRef.current = nextPoint
    onStroke?.(payload)
  }

  const handlePointerUp = () => {
    pointerRef.current = null
  }

  const handleGuessSubmit = (event) => {
    event.preventDefault()
    const nextGuess = guessText.trim()
    if (!nextGuess) return
    onSubmitGuess?.(nextGuess)
    setGuessText('')
  }

  const statusLabel = String(game?.status || 'IDLE').replaceAll('_', ' ')

  if (!game) {
    return (
      <section className="draw-guess draw-guess--arcade">
        <div className="draw-guess__heroCard">
          <div className="draw-guess__heroBadge">Draw & Guess</div>
          <div className="draw-guess__heroTitle">Sketch. Guess. Win.</div>
          <p className="draw-guess__heroText">Open a round inside this chat and start playing with your current members.</p>
          <button type="button" className="draw-guess__primaryBtn" onClick={onCreateLobby}>
            Start Game
          </button>
        </div>
      </section>
    )
  }

  if (game?.status === 'GAME_END' && game?.endReason === 'cancelled') {
    return (
      <section className="draw-guess draw-guess--arcade">
        <div className="draw-guess__heroCard">
          <div className="draw-guess__heroBadge">Lobby Closed</div>
          <div className="draw-guess__heroTitle">Game cancelled</div>
          <p className="draw-guess__heroText">The room was closed before the match began.</p>
        </div>
      </section>
    )
  }

  if (game?.status === 'GAME_END') {
    const champion = leaderboard[0] || null
    return (
      <section className="draw-guess draw-guess--arcade draw-guess--arcadeEnd">
        <div className="draw-guess__leaderboardCard">
          <div className="draw-guess__leaderboardBadge">Leaderboard</div>
          <div className="draw-guess__leaderboardTrophy">🏆</div>
          <div className="draw-guess__leaderboardTitle">Top Rank</div>
          {champion && (
            <div className="draw-guess__leaderboardChampion">
              {champion.displayName || champion.username} · {champion.score} pts
            </div>
          )}
          <ol className="draw-guess__rankList">
            {leaderboard.map((player, index) => (
              <li key={player.userId} className="draw-guess__rankItem">
                <span className="draw-guess__rankMeta">
                  <em>{medalForRank(index)}</em>
                  <strong>{player.displayName || player.username}</strong>
                </span>
                <strong>{player.score} pts</strong>
              </li>
            ))}
          </ol>
          <div className="draw-guess__rankActions">
            <button type="button" className="draw-guess__primaryBtn" onClick={onCreateLobby}>
              Go on
            </button>
            <button type="button" className="draw-guess__ghostBtn" onClick={onExitGameView}>
              Exit
            </button>
          </div>
        </div>
      </section>
    )
  }

  const isRoundStartWaiting = game?.status === 'ROUND_START' && !viewer?.isDrawer
  const isLobbyState = game?.status === 'LOBBY'

  return (
    <section className={`draw-guess draw-guess--arcade ${viewer?.isDrawer ? 'draw-guess--drawerView' : 'draw-guess--guesserView'} ${isRoundStartWaiting ? 'draw-guess--waitingState' : ''} ${isLobbyState ? 'draw-guess--lobbyState' : ''}`.trim()}>
      <div className="draw-guess__arcadeHeader">
        <div className="draw-guess__headerMeta">
          <span className="draw-guess__chip">Round {Math.max(1, Number(game?.roundIndex || 0))}</span>
          <span className="draw-guess__headerText">
            Drawer: <strong>{drawer?.displayName || drawer?.username || 'Waiting...'}</strong>
          </span>
          <span className="draw-guess__chip draw-guess__chip--status">{statusLabel}</span>
        </div>

        <div className="draw-guess__timer">
          {game?.status === 'ROUND_START' ? formatClock(selectionSeconds || 0) : formatClock(playSeconds || 0)}
        </div>
      </div>

      {viewer?.isDrawer && game?.drawerWord && (
        <div className="draw-guess__wordBanner">
          <span className="draw-guess__wordLabel">Your word</span>
          <strong>{game.drawerWord}</strong>
        </div>
      )}

      {hints.length > 0 && (
        <div className="draw-guess__hintStack">
          {hints.map((hint) => (
            <div key={`hint-${hint.level}`} className="draw-guess__hintBubble">
              <strong>Hint {hint.level}</strong>
              <span>{hint.text}</span>
            </div>
          ))}
        </div>
      )}

      {game?.status === 'LOBBY' && (
        <div className="draw-guess__lobbyShell">
          <div className="draw-guess__panelTitle">Lobby</div>
          <div className="draw-guess__panelCopy draw-guess__lobbySummary">
            Required players: {game.minPlayers}. Ready now: {game.readyCount || 0}.
          </div>
          <div className="draw-guess__playerGrid">
            {playerList.map((player) => (
              <div key={player.userId} className="draw-guess__playerCard">
                <strong>{player.displayName || player.username}</strong>
                <span>{player.isReady ? 'Ready' : 'Waiting'}</span>
              </div>
            ))}
          </div>
          <div className="draw-guess__rankActions draw-guess__lobbyActions">
            <button type="button" className="draw-guess__ghostBtn" onClick={() => onToggleReady?.(!viewer?.isReady)}>
              {viewer?.isReady ? 'Unready' : 'Ready'}
            </button>
            <button
              type="button"
              className="draw-guess__primaryBtn"
              onClick={onStartGame}
              disabled={!viewer?.isReady || Number(game?.readyCount || 0) < Number(game?.minPlayers || 2)}
            >
              Start Game
            </button>
            <button type="button" className="draw-guess__ghostBtn" onClick={onLeaveGame}>
              Cancel
            </button>
          </div>
        </div>
      )}

      {game?.status === 'ROUND_START' && viewer?.isDrawer && (
        <div className="draw-guess__wordChoiceShell">
          <div className="draw-guess__panelTitle">Choose one word</div>
          <div className="draw-guess__panelCopy">Pick within 10 seconds or the system will choose for you.</div>
          <div className="draw-guess__wordOptions">
            {(game.drawerWordOptions || []).map((option) => (
              <button
                key={option.id}
                type="button"
                className="draw-guess__wordCard"
                onClick={() => onPickWord?.(option.id)}
              >
                {option.word}
              </button>
            ))}
          </div>
        </div>
      )}

      {game?.status === 'ROUND_START' && !viewer?.isDrawer && (
        <div className="draw-guess__waitingCard">Waiting for the drawer to choose a word…</div>
      )}

      {['PLAYING', 'ROUND_END'].includes(game?.status) && (
        <>
          {viewer?.isDrawer && game?.status === 'PLAYING' && (
            <div className="draw-guess__toolbarStrip">
              <div className="draw-guess__toolbarGroup">
                <span className="draw-guess__toolbarLabel">Tools</span>
                <div className="draw-guess__iconRow">
                  <ToolButton kind="brush" label="Brush" active={tool === 'brush'} onClick={() => setTool('brush')} />
                  <ToolButton kind="eraser" label="Eraser" active={tool === 'eraser'} onClick={() => setTool('eraser')} />
                  <ToolButton kind="clear" label="Clear canvas" onClick={onClearCanvas} />
                </div>
              </div>
              <div className="draw-guess__toolbarGroup">
                <span className="draw-guess__toolbarLabel">Colors</span>
                <div className="draw-guess__paletteRow">
                  {['#1f2937', '#2563eb', '#dc2626', '#16a34a', '#f59e0b', '#7c3aed'].map((swatch) => (
                    <button
                      key={swatch}
                      type="button"
                      className={`draw-guess__swatch ${color === swatch ? 'is-active' : ''}`}
                      style={{ '--swatch': swatch }}
                      title={swatch}
                      aria-label={`Color ${swatch}`}
                      onClick={() => setColor(swatch)}
                    >
                      <span className="draw-guess__swatchInner" />
                    </button>
                  ))}
                </div>
              </div>
              <label className="draw-guess__sizeControl draw-guess__sizeControl--toolbar">
                <span>Brush Size</span>
                <input type="range" min="2" max="18" value={size} onChange={(event) => setSize(Number(event.target.value))} />
              </label>
            </div>
          )}

          <div className="draw-guess__playGrid">
            <div className="draw-guess__stageCard">
              <div className="draw-guess__canvasShell">
                <canvas
                  ref={canvasRef}
                  className={`draw-guess__canvas ${canDraw ? 'is-drawer' : 'is-viewer'}`}
                  width={CANVAS_WIDTH}
                  height={CANVAS_HEIGHT}
                  onPointerDown={handlePointerDown}
                  onPointerMove={handlePointerMove}
                  onPointerUp={handlePointerUp}
                  onPointerLeave={handlePointerUp}
                />
              </div>
            </div>

            <aside className="draw-guess__rail">
              <section className="draw-guess__railCard">
                <div className="draw-guess__panelTitle">Scores</div>
                <div className="draw-guess__scoreList">
                  {leaderboard.map((player) => (
                    <div key={player.userId} className="draw-guess__scoreRow">
                      <span>{player.displayName || player.username}</span>
                      <strong>{player.score}</strong>
                    </div>
                  ))}
                </div>
              </section>

              <section className="draw-guess__railCard">
                <div className="draw-guess__panelTitle">Round Updates</div>
                <div className="draw-guess__updates">
                  {guessFeed.length > 0 ? (
                    guessFeed.map((item) => (
                      <div
                        key={item.guessId}
                        className={`draw-guess__updateItem ${item.isCorrect ? 'is-correct' : 'is-wrong'}`}
                      >
                        {item.isCorrect ? (
                          `${item.displayName || item.username} guessed correctly`
                        ) : (
                          <>
                            <span className="draw-guess__guessStatus">Wrong guess:</span>{' '}
                            {item.displayName || item.username} answered "{item.guessText}"
                          </>
                        )}
                      </div>
                    ))
                  ) : (
                    <div className="draw-guess__panelCopy">No guesses yet.</div>
                  )}
                </div>
              </section>

            </aside>
          </div>

          <div className="draw-guess__footerBar">
            <form className="draw-guess__guessForm" onSubmit={handleGuessSubmit}>
              <input
                type="text"
                className="draw-guess__guessInput"
                placeholder={viewer?.isDrawer ? 'Drawer cannot guess' : 'Type your guess and press Enter'}
                value={guessText}
                onChange={(event) => setGuessText(event.target.value)}
                disabled={!viewer?.canGuess}
              />
              <button type="submit" className="draw-guess__primaryBtn" disabled={!viewer?.canGuess}>
                Guess
              </button>
            </form>

            <button type="button" className="draw-guess__ghostBtn" onClick={onLeaveGame}>
              Leave Game
            </button>
          </div>

          {game?.status === 'ROUND_END' && (
            <div className="draw-guess__answerCard">
              Answer: <strong>{game.revealedAnswer || 'Unknown'}</strong>
            </div>
          )}
        </>
      )}
    </section>
  )
}
