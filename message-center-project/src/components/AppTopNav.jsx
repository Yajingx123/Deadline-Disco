import { useEffect, useState } from 'react'
import { connectRealtime, fetchMessageCenter } from '../api/forumApi'

export default function AppTopNav({ currentUser, activeMode = 'forum' }) {
  const userName = currentUser?.username || 'LOGIN'
  const userInitial = userName.slice(0, 2).toUpperCase()
  const portalUrl = currentUser?.role === 'admin'
    ? 'http://127.0.0.1:8001/admin_page/dist/index.html'
    : 'http://127.0.0.1:8001/owner.html'
  const [hasUnread, setHasUnread] = useState(false)
  const messageCenterUrl = `http://127.0.0.1:8001/message-center-project/dist/index.html?from=${encodeURIComponent(window.location.href)}`

  useEffect(() => {
    let cancelled = false
    if (!currentUser) {
      setHasUnread(false)
      return undefined
    }

    fetchMessageCenter(true)
      .then((data) => {
        if (!cancelled) {
          setHasUnread(Number(data?.summary?.totalUnread || 0) > 0)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setHasUnread(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [currentUser])

  useEffect(() => {
    const handleSummaryUpdate = (event) => {
      const nextTotal = Number(event?.detail?.summary?.totalUnread ?? event?.detail?.totalUnread ?? 0)
      setHasUnread(nextTotal > 0)
    }

    window.addEventListener('acadbeat:message-summary', handleSummaryUpdate)
    return () => window.removeEventListener('acadbeat:message-summary', handleSummaryUpdate)
  }, [])

  useEffect(() => {
    if (!currentUser) {
      return undefined
    }

    let refreshTimer = null
    const pollTimer = window.setInterval(async () => {
      if (document.hidden) {
        return
      }
      try {
        const data = await fetchMessageCenter(true)
        setHasUnread(Number(data?.summary?.totalUnread || 0) > 0)
      } catch (_err) {
        // Ignore polling failures.
      }
    }, 3000)

    const disconnect = connectRealtime((event) => {
      const type = String(event?.type || '')
      if (!type.startsWith('chat.') && !type.startsWith('message-center.') && !type.startsWith('forum.')) {
        return
      }
      if (refreshTimer) {
        window.clearTimeout(refreshTimer)
      }
        refreshTimer = window.setTimeout(async () => {
          refreshTimer = null
          try {
            const data = await fetchMessageCenter(true)
            setHasUnread(Number(data?.summary?.totalUnread || 0) > 0)
        } catch (_err) {
          // Keep current badge state on transient realtime failures.
        }
      }, 120)
    })

    return () => {
      window.clearInterval(pollTimer)
      disconnect?.()
    }
  }, [currentUser])

  return (
    <header className="forum-topnav">
      <a className="forum-topnav__logo" href="http://127.0.0.1:8001/home.html">Acad<span>Beat</span></a>
      <nav className="forum-topnav__menu" aria-label="Main">
        <a className={`forum-topnav__item ${activeMode === 'academic' ? 'is-active' : ''}`} href="http://127.0.0.1:8001/home.html?module=Insight">Academic</a>
        <a className={`forum-topnav__item ${activeMode === 'forum' || activeMode === 'personal' || activeMode === 'chooser' || activeMode === 'messages' ? 'is-active' : ''}`} href="http://127.0.0.1:8001/home.html?module=Dialogue">Forum</a>
        <a className={`forum-topnav__item ${activeMode === 'technology' ? 'is-active' : ''}`} href="http://127.0.0.1:8001/home.html?module=Method">Technology</a>
      </nav>
      <div className="forum-topnav__actions">
        {currentUser && (
          <a
            className={`forum-topnav__messageLink ${activeMode === 'messages' ? 'is-active' : ''}`}
            href={messageCenterUrl}
            aria-label="Open message center"
          >
            <span className="forum-topnav__messageIcon" aria-hidden="true">✉</span>
            {hasUnread && (
              <span className="forum-topnav__messageBadge" aria-label="Unread messages or notifications" />
            )}
          </a>
        )}
        <div className="forum-topnav__userGroup">
          <a className="forum-topnav__user" href={portalUrl}>
            <span className="forum-topnav__userLabel">{userName}</span>
            <span className="forum-topnav__avatar" aria-hidden="true">{userInitial}</span>
          </a>
          {currentUser && (
            <a className="forum-topnav__logout" href="http://127.0.0.1:8001/vocba_prac/logout.php">
              Log out
            </a>
          )}
        </div>
      </div>
    </header>
  )
}
