import { useEffect, useMemo, useState } from 'react'
import PersonalHub from './PersonalHub'
import { connectRealtime, fetchMessageCenter, fetchSessionUser, markMessageCenterCategoryRead, markMessageCenterNoticeRead } from '../api/forumApi'
import './MessageCenter.css'

function broadcastSummary(summary = {}) {
  window.dispatchEvent(new CustomEvent('acadbeat:message-summary', {
    detail: {
      summary,
      totalUnread: Number(summary.totalUnread || 0),
    },
  }))
}

function timeAgo(value) {
  if (!value) return ''
  const diffMs = Date.now() - new Date(value).getTime()
  const diffMinutes = Math.max(1, Math.floor(diffMs / 60000))
  if (diffMinutes < 60) return `${diffMinutes}m ago`
  const diffHours = Math.floor(diffMinutes / 60)
  if (diffHours < 24) return `${diffHours}h ago`
  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 30) return `${diffDays}d ago`
  const diffMonths = Math.floor(diffDays / 30)
  if (diffMonths < 12) return `${diffMonths}mo ago`
  return `${Math.floor(diffMonths / 12)}y ago`
}

const TAB_META = [
  { id: 'messages', label: 'My Messages', desc: 'Direct and group chats' },
  { id: 'replies', label: 'Replies to Me', desc: 'Comments on your posts' },
  { id: 'reactions', label: 'Likes & Favorites', desc: 'Post appreciation updates' },
  { id: 'system', label: 'System Notices', desc: 'Product and community updates' },
]

const MAIN_ORIGIN =
  (typeof window !== 'undefined' && window.ACADBEAT_LOCAL && window.ACADBEAT_LOCAL.mainOrigin)
  || (typeof window !== 'undefined' ? window.location.origin : 'http://127.0.0.1:8001')
const FALLBACK_BACK_URL = `${MAIN_ORIGIN}/home.html?module=Dialogue`
const GAMEUI_FORUM_URL = `${MAIN_ORIGIN}/GameUI/forum-project-GameUI/dist/index.html`
const GAMEUI_MESSAGE_CENTER_URL = `${MAIN_ORIGIN}/GameUI/message-center-project-GameUI/dist/index.html`
const GAMEUI_CHALLENGE_URL = `${MAIN_ORIGIN}/GameUI/challenge-GameUI/challenge-panel.html`
const IS_GODOT_CONTEXT = (() => {
  if (typeof window === 'undefined') return false
  const params = new URLSearchParams(window.location.search)
  if (params.get('ui') === 'godot') return true
  if (String(window.location.pathname || '').startsWith('/GameUI/message-center-project-GameUI/')) return true
  return sessionStorage.getItem('acadbeatMessageCenterGodot') === '1'
})()

if (typeof window !== 'undefined' && IS_GODOT_CONTEXT) {
  sessionStorage.setItem('acadbeatMessageCenterGodot', '1')
}

function withGodotUi(url) {
  if (!IS_GODOT_CONTEXT) return url
  try {
    const parsed = new URL(url, window.location.origin)
    parsed.searchParams.set('ui', 'godot')
    return parsed.toString()
  } catch (_err) {
    return url
  }
}

function sanitizeBackUrl(rawUrl) {
  if (!rawUrl) return ''
  try {
    const parsed = new URL(rawUrl, window.location.origin)
    if (parsed.origin !== window.location.origin) {
      return ''
    }
    parsed.searchParams.delete('login')
    return parsed.toString()
  } catch (_err) {
    return ''
  }
}

function resolveBackUrl() {
  const params = new URLSearchParams(window.location.search)
  const from = params.get('from')
  if (from) {
    const sanitized = sanitizeBackUrl(decodeURIComponent(from))
    if (sanitized) {
      sessionStorage.setItem('acadbeatMessageCenterFrom', sanitized)
      return sanitized
    }
  }

  const stored = sessionStorage.getItem('acadbeatMessageCenterFrom')
  if (stored) {
    const sanitized = sanitizeBackUrl(stored)
    if (sanitized) {
      sessionStorage.setItem('acadbeatMessageCenterFrom', sanitized)
      return sanitized
    }
    sessionStorage.removeItem('acadbeatMessageCenterFrom')
  }

  if (document.referrer) {
    const sanitized = sanitizeBackUrl(document.referrer)
    if (sanitized) {
      sessionStorage.setItem('acadbeatMessageCenterFrom', sanitized)
      return sanitized
    }
  }

  return sanitizeBackUrl(FALLBACK_BACK_URL) || FALLBACK_BACK_URL
}

function mapMessageCtaToGameUi(rawUrl, noticeKind = '') {
  if (!rawUrl) {
    return noticeKind === 'challenge' ? GAMEUI_CHALLENGE_URL : ''
  }

  try {
    const parsed = new URL(rawUrl, window.location.origin)
    if (parsed.origin !== window.location.origin) {
      return parsed.toString()
    }

    const path = String(parsed.pathname || '')
    const module = String(parsed.searchParams.get('module') || '').toLowerCase()
    const isChallengeHome = path === '/home.html' && (parsed.searchParams.get('challenge') === '1' || module === 'challenge' || module === 'competition')
    const isForumHome = path === '/home.html' && (module === 'dialogue' || module === 'forum')
    const isMessageHome = path === '/home.html' && (module === 'messages' || module === 'messagecenter')
    const isForumPath = path.includes('/forum-project/dist/index.html') || path.includes('/forum-project/index.html') || path.startsWith('/forum-project/')
    const isMessagePath = path.includes('/message-center-project/dist/index.html')
      || path.includes('/message-center-project/index.html')
      || path.includes('/message-center-project%202/')
      || path.includes('/message-center-project 2/')
      || path.startsWith('/message-center-project/')
    const isChallengePath = path.startsWith('/challenge/')

    if (isChallengeHome || isChallengePath || noticeKind === 'challenge') {
      return withGodotUi(GAMEUI_CHALLENGE_URL)
    }

    if (isForumHome || isForumPath) {
      const target = new URL(GAMEUI_FORUM_URL)
      const postId = parsed.searchParams.get('postId')
      if (postId) {
        target.searchParams.set('postId', postId)
      }
      return withGodotUi(target.toString())
    }

    if (isMessageHome || isMessagePath) {
      return withGodotUi(GAMEUI_MESSAGE_CENTER_URL)
    }

    return parsed.toString()
  } catch (_err) {
    return rawUrl
  }
}

export default function MessageCenter() {
  const [currentUser, setCurrentUser] = useState(null)
  const [activeTab, setActiveTab] = useState('messages')
  const [data, setData] = useState({ summary: {}, replies: [], reactions: [], notices: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [backUrl] = useState(resolveBackUrl)

  const loadCenter = async () => {
    const [sessionData, centerData] = await Promise.all([
      fetchSessionUser(),
      fetchMessageCenter(false),
    ])
    if ((sessionData.user?.role || '').toLowerCase() === 'admin') {
      window.location.replace(`${MAIN_ORIGIN}/admin_page/dist/index.html`)
      return
    }
    setCurrentUser(sessionData.user || null)
    setData({
      summary: centerData.summary || {},
      replies: centerData.replies || [],
      reactions: centerData.reactions || [],
      notices: centerData.notices || [],
    })
    broadcastSummary(centerData.summary || {})
  }

  useEffect(() => {
    let cancelled = false
    ;(async () => {
      setLoading(true)
      setError('')
      try {
        const [sessionData, centerData] = await Promise.all([
          fetchSessionUser(),
          fetchMessageCenter(false),
        ])
        if (cancelled) return
        if ((sessionData.user?.role || '').toLowerCase() === 'admin') {
          window.location.replace(`${MAIN_ORIGIN}/admin_page/dist/index.html`)
          return
        }
        setCurrentUser(sessionData.user || null)
        setData({
          summary: centerData.summary || {},
          replies: centerData.replies || [],
          reactions: centerData.reactions || [],
          notices: centerData.notices || [],
        })
        broadcastSummary(centerData.summary || {})
      } catch (err) {
        if ((err.message || '').toLowerCase().includes('login required')) {
          window.location.href = `${MAIN_ORIGIN}/home.html?login=1`
          return
        }
        if (!cancelled) {
          setError(err?.message || 'Failed to load message center.')
        }
      } finally {
        if (!cancelled) setLoading(false)
      }
    })()
    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    if (activeTab !== 'replies' && activeTab !== 'reactions') {
      return
    }
    markMessageCenterCategoryRead(activeTab).then(() => {
      setData((prev) => {
        const next = {
        ...prev,
        summary: {
          ...prev.summary,
          repliesUnread: activeTab === 'replies' ? 0 : prev.summary.repliesUnread || 0,
          reactionsUnread: activeTab === 'reactions' ? 0 : prev.summary.reactionsUnread || 0,
          totalUnread:
            (prev.summary.chatsUnread || 0)
            + (activeTab === 'replies' ? 0 : prev.summary.repliesUnread || 0)
            + (activeTab === 'reactions' ? 0 : prev.summary.reactionsUnread || 0)
            + (prev.summary.systemCount || 0),
        },
        replies: activeTab === 'replies' ? prev.replies.map((item) => ({ ...item, isRead: true })) : prev.replies,
        reactions: activeTab === 'reactions' ? prev.reactions.map((item) => ({ ...item, isRead: true })) : prev.reactions,
        }
        broadcastSummary(next.summary)
        return next
      })
    }).catch(() => {})
  }, [activeTab])

  useEffect(() => {
    const handleSummaryUpdate = (event) => {
      const nextSummary = event?.detail?.summary
      if (!nextSummary || typeof nextSummary !== 'object') {
        return
      }
      setData((prev) => ({
        ...prev,
        summary: {
          ...prev.summary,
          ...nextSummary,
        },
      }))
    }

    window.addEventListener('acadbeat:message-summary', handleSummaryUpdate)
    return () => window.removeEventListener('acadbeat:message-summary', handleSummaryUpdate)
  }, [])

  const handleReadNotice = async (noticeId, noticeKind = 'system') => {
    await markMessageCenterNoticeRead(noticeId, noticeKind)
    setData((prev) => {
      const targetNotice = prev.notices.find((item) => (
        Number(item.id) === Number(noticeId) && String(item.kind || 'system') === noticeKind
      ))
      const wasUnread = targetNotice && !targetNotice.isRead
      const next = {
        ...prev,
        summary: {
          ...prev.summary,
          systemCount: Math.max((prev.summary.systemCount || 0) - (wasUnread ? 1 : 0), 0),
          totalUnread: Math.max((prev.summary.totalUnread || 0) - (wasUnread ? 1 : 0), 0),
        },
        notices: prev.notices.map((item) => (
          Number(item.id) === Number(noticeId) && String(item.kind || 'system') === noticeKind
            ? { ...item, isRead: true }
            : item
        )),
      }
      broadcastSummary(next.summary)
      return next
    })
  }

  useEffect(() => {
    if (loading) {
      return undefined
    }
    const disconnect = connectRealtime(async (event) => {
      const type = String(event?.type || '')
      if (!type.startsWith('chat.') && !type.startsWith('message-center.') && !type.startsWith('forum.')) {
        return
      }
      try {
        await loadCenter()
      } catch (_err) {
        // keep current state on transient realtime refresh failures
      }
    })

    const pollTimer = window.setInterval(() => {
      if (document.hidden) {
        return
      }
      loadCenter().catch(() => {})
    }, 3000)

    return () => {
      window.clearInterval(pollTimer)
      disconnect?.()
    }
  }, [loading])

  const tabs = useMemo(() => ([
    { ...TAB_META[0], count: Number(data.summary.chatsUnread || 0) },
    { ...TAB_META[1], count: Number(data.summary.repliesUnread || 0) },
    { ...TAB_META[2], count: Number(data.summary.reactionsUnread || 0) },
    { ...TAB_META[3], count: Number(data.summary.systemCount || 0) },
  ]), [data.summary])

  const renderReplyList = () => (
    <div className="mc2-feed">
      {data.replies.map((item) => (
        <article key={item.id} className={`mc2-card ${item.isRead ? '' : 'is-unread'}`}>
          <div className="mc2-card__avatar">{String(item.actor || 'U').slice(0, 1).toUpperCase()}</div>
          <div className="mc2-card__body">
            <div className="mc2-card__top">
              <strong>{item.actor}</strong>
              <span>{timeAgo(item.createdAt)}</span>
            </div>
            <div className="mc2-card__headline">{item.title}</div>
            <div className="mc2-card__preview">{item.commentPreview || item.body}</div>
            <div className="mc2-card__meta">Post: {item.postTitle}</div>
            <div className="mc2-card__actions">
              <button type="button" className="mc2-actionBtn" onClick={() => { window.location.href = mapMessageCtaToGameUi(item.ctaUrl) }}>
                Reply
              </button>
            </div>
          </div>
        </article>
      ))}
      {!data.replies.length && <div className="mc2-empty">No reply activity yet.</div>}
    </div>
  )

  const renderReactionList = () => (
    <div className="mc2-feed">
      {data.reactions.map((item) => (
        <article key={item.id} className={`mc2-card ${item.isRead ? '' : 'is-unread'}`}>
          <div className="mc2-card__avatar">{String(item.actor || 'U').slice(0, 1).toUpperCase()}</div>
          <div className="mc2-card__body">
            <div className="mc2-card__top">
              <strong>{item.actor}</strong>
              <span>{timeAgo(item.createdAt)}</span>
            </div>
            <div className="mc2-card__headline">{item.title}</div>
            <div className="mc2-card__preview">{item.postTitle || item.body}</div>
            <div className="mc2-card__actions">
              <button type="button" className="mc2-actionBtn" onClick={() => { window.location.href = mapMessageCtaToGameUi(item.ctaUrl) }}>
                View post
              </button>
            </div>
          </div>
        </article>
      ))}
      {!data.reactions.length && <div className="mc2-empty">No likes or favorites yet.</div>}
    </div>
  )

  const renderNotices = () => (
    <div className="mc2-noticeFeed">
      {data.notices.map((item) => (
        <article key={`${item.kind || 'system'}-${item.id}`} className="mc2-notice">
          {!item.isRead && <div className="mc2-notice__dot" />}
          <div className="mc2-notice__tag">
            {item.kind === 'challenge' ? 'Challenge Update' : item.kind === 'notification' ? 'Moderation Update' : 'System Notice'}
          </div>
          <h3>{item.title}</h3>
          <p>{item.body}</p>
          <div className="mc2-notice__footer">
            <span>{timeAgo(item.createdAt)}</span>
            {item.ctaUrl && (
              <button type="button" className="mc2-actionBtn" onClick={async () => {
                await handleReadNotice(item.id, item.kind || 'system')
                window.location.href = mapMessageCtaToGameUi(item.ctaUrl, item.kind || 'system')
              }}>
                {item.ctaLabel || 'Open'}
              </button>
            )}
            {!item.ctaUrl && !item.isRead && (
              <button type="button" className="mc2-actionBtn" onClick={() => handleReadNotice(item.id, item.kind || 'system')}>
                Mark as read
              </button>
            )}
          </div>
        </article>
      ))}
      {!data.notices.length && <div className="mc2-empty">No system notices right now.</div>}
    </div>
  )

  const statusPanel = loading
    ? <div className="mc2-empty">Loading message center…</div>
    : (error ? <div className="mc2-empty mc2-empty--error">{error}</div> : null)
  const activeTabMeta = tabs.find((tab) => tab.id === activeTab)
  const infoPanel = activeTab === 'replies'
    ? renderReplyList()
    : (activeTab === 'reactions' ? renderReactionList() : renderNotices())

  return (
    <div className="forum-container forum-container--messages mc2-page">
      <header className="mc2-topbar">
        <div className="mc2-topbar__actions">
          <button type="button" className="mc2-topbar__backBtn" onClick={() => { window.location.href = backUrl }}>
            ←
          </button>
        </div>
      </header>

      <section className="mc2-a">
        <aside className="mc2-b">
          <div className="mc2-b__header">
            <div className="mc2-eyebrow">Message Center</div>
            <h1>Inbox</h1>
          </div>

          <nav className="mc2-b__nav" aria-label="Message center tabs">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                type="button"
                className={`mc2-b__navItem ${activeTab === tab.id ? 'is-active' : ''}`}
                onClick={() => setActiveTab(tab.id)}
              >
                <span>
                  <strong>{tab.label}</strong>
                  <small>{tab.desc}</small>
                </span>
                {tab.count > 0 && <em>{tab.count > 99 ? '99+' : tab.count}</em>}
              </button>
            ))}
          </nav>
        </aside>

        <div className="mc2-right">
          {activeTab === 'messages'
            ? (
              <section className="mc2-d">
                {statusPanel || (
                  <div className="mc2-d__inner">
                    <PersonalHub embedded />
                  </div>
                )}
              </section>
              )
            : (
              <section className="mc2-c">
                <header className="mc2-c__header">
                  <div>
                    <div className="mc2-eyebrow">{activeTabMeta?.label}</div>
                    <h2>{activeTabMeta?.label}</h2>
                  </div>
                </header>
                <div className="mc2-c__body">
                  {statusPanel || infoPanel}
                </div>
              </section>
              )}
        </div>
      </section>
    </div>
  )
}
