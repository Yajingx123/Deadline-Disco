import { useEffect, useMemo, useState } from 'react'
import AppTopNav from '../components/AppTopNav'
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

export default function MessageCenter() {
  const [currentUser, setCurrentUser] = useState(null)
  const [activeTab, setActiveTab] = useState('messages')
  const [data, setData] = useState({ summary: {}, replies: [], reactions: [], notices: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const loadCenter = async () => {
    const [sessionData, centerData] = await Promise.all([
      fetchSessionUser(),
      fetchMessageCenter(false),
    ])
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
          window.location.href = 'http://127.0.0.1:8001/home.html?login=1'
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
    <div className="message-feed">
      {data.replies.map((item) => (
        <article key={item.id} className={`message-card ${item.isRead ? '' : 'is-unread'}`}>
          <div className="message-card__avatar">{String(item.actor || 'U').slice(0, 1).toUpperCase()}</div>
          <div className="message-card__body">
            <div className="message-card__top">
              <strong>{item.actor}</strong>
              <span>{timeAgo(item.createdAt)}</span>
            </div>
            <div className="message-card__headline">{item.title}</div>
            <div className="message-card__preview">{item.commentPreview || item.body}</div>
            <div className="message-card__meta">Post: {item.postTitle}</div>
            <div className="message-card__actions">
              <button type="button" className="message-card__cta" onClick={() => { window.location.href = item.ctaUrl }}>
                Reply
              </button>
            </div>
          </div>
        </article>
      ))}
      {!data.replies.length && <div className="message-empty">No reply activity yet.</div>}
    </div>
  )

  const renderReactionList = () => (
    <div className="message-feed">
      {data.reactions.map((item) => (
        <article key={item.id} className={`message-card ${item.isRead ? '' : 'is-unread'}`}>
          <div className="message-card__avatar">{String(item.actor || 'U').slice(0, 1).toUpperCase()}</div>
          <div className="message-card__body">
            <div className="message-card__top">
              <strong>{item.actor}</strong>
              <span>{timeAgo(item.createdAt)}</span>
            </div>
            <div className="message-card__headline">{item.title}</div>
            <div className="message-card__preview">{item.postTitle || item.body}</div>
            <div className="message-card__actions">
              <button type="button" className="message-card__cta" onClick={() => { window.location.href = item.ctaUrl }}>
                View post
              </button>
            </div>
          </div>
        </article>
      ))}
      {!data.reactions.length && <div className="message-empty">No likes or favorites yet.</div>}
    </div>
  )

  const renderNotices = () => (
    <div className="notice-feed">
      {data.notices.map((item) => (
        <article key={`${item.kind || 'system'}-${item.id}`} className="notice-card">
          {!item.isRead && <div className="notice-card__dot" />}
          <div className="notice-card__tag">{item.kind === 'challenge' ? 'Challenge Update' : 'System Notice'}</div>
          <h3>{item.title}</h3>
          <p>{item.body}</p>
          <div className="notice-card__footer">
            <span>{timeAgo(item.createdAt)}</span>
            {item.ctaUrl && (
              <button type="button" className="message-card__cta" onClick={async () => {
                await handleReadNotice(item.id, item.kind || 'system')
                window.location.href = item.ctaUrl
              }}>
                {item.ctaLabel || 'Open'}
              </button>
            )}
            {!item.ctaUrl && !item.isRead && (
              <button type="button" className="message-card__cta" onClick={() => handleReadNotice(item.id, item.kind || 'system')}>
                Mark as read
              </button>
            )}
          </div>
        </article>
      ))}
      {!data.notices.length && <div className="message-empty">No system notices right now.</div>}
    </div>
  )

  let panelContent = null
  if (loading) {
    panelContent = <div className="message-empty">Loading message center…</div>
  } else if (error) {
    panelContent = <div className="message-empty message-empty--error">{error}</div>
  } else if (activeTab === 'messages') {
    panelContent = <PersonalHub embedded />
  } else if (activeTab === 'replies') {
    panelContent = renderReplyList()
  } else if (activeTab === 'reactions') {
    panelContent = renderReactionList()
  } else {
    panelContent = renderNotices()
  }

  return (
    <div className="forum-container forum-container--messages">
      <AppTopNav currentUser={currentUser} activeMode="messages" />

      <section className="message-center">
        <aside className="message-center__sidebar">
          <div className="message-center__sidebarHeader">
            <div className="message-center__eyebrow">Message Center</div>
            <h1>Inbox</h1>
            <p>Track conversations, post replies, appreciation, and platform notices in one place.</p>
          </div>

          <div className="message-center__nav">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                type="button"
                className={`message-center__navItem ${activeTab === tab.id ? 'is-active' : ''}`}
                onClick={() => setActiveTab(tab.id)}
              >
                <span>
                  <strong>{tab.label}</strong>
                  <small>{tab.desc}</small>
                </span>
                {tab.count > 0 && <em>{tab.count > 99 ? '99+' : tab.count}</em>}
              </button>
            ))}
          </div>
        </aside>

        <main className="message-center__content">
          {activeTab !== 'messages' && (
            <div className="message-center__panelHeader">
              <div>
                <div className="message-center__eyebrow">{tabs.find((tab) => tab.id === activeTab)?.label}</div>
                <h2>{tabs.find((tab) => tab.id === activeTab)?.label}</h2>
              </div>
            </div>
          )}
          <div className={`message-center__panelBody ${activeTab === 'messages' ? 'is-chat' : ''}`}>
            {panelContent}
          </div>
        </main>
      </section>
    </div>
  )
}
