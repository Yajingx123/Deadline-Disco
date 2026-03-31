import { useEffect, useState } from 'react'
import { fetchMessageCenter } from '../api/forumApi'

export default function AppTopNav({ currentUser, activeMode = 'forum' }) {
  const userName = currentUser?.username || 'LOGIN'
  const userInitial = userName.slice(0, 2).toUpperCase()
  const portalUrl = currentUser?.role === 'admin'
    ? 'http://127.0.0.1:5174/'
    : 'http://127.0.0.1:8001/owner.html'
  const [unreadCount, setUnreadCount] = useState(0)

  useEffect(() => {
    let cancelled = false
    if (!currentUser) {
      setUnreadCount(0)
      return undefined
    }

    fetchMessageCenter(true)
      .then((data) => {
        if (!cancelled) {
          setUnreadCount(Number(data?.summary?.totalUnread || 0))
        }
      })
      .catch(() => {
        if (!cancelled) {
          setUnreadCount(0)
        }
      })

    return () => {
      cancelled = true
    }
  }, [currentUser])

  useEffect(() => {
    const handleSummaryUpdate = (event) => {
      const nextTotal = Number(event?.detail?.totalUnread || 0)
      setUnreadCount(nextTotal)
    }

    window.addEventListener('acadbeat:message-summary', handleSummaryUpdate)
    return () => window.removeEventListener('acadbeat:message-summary', handleSummaryUpdate)
  }, [])

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
            href="http://127.0.0.1:8001/forum-project/dist/index.html?view=messages"
            aria-label="Open message center"
          >
            <span className="forum-topnav__messageIcon" aria-hidden="true">✉</span>
            {unreadCount >= 1 && (
              <span className="forum-topnav__messageBadge">
                {unreadCount > 99 ? '99+' : unreadCount}
              </span>
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
