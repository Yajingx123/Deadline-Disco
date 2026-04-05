import { useEffect, useState } from 'react'
import { adminFetch } from '../api'

function renderAdminPostContent(text) {
  if (!text) return ''

  let html = String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')

  html = html.replace(/!\[(.*?)\]\((.*?)\)/g, '<img src="$2" alt="$1" style="max-width:100%; height:auto; border-radius:14px; display:block; margin:8px 0;" />')
  html = html.replace(/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>')
  html = html.replace(/&lt;u&gt;(.*?)&lt;\/u&gt;/g, '<u>$1</u>')
  html = html.replace(/\n/g, '<br>')

  return html
}

function ForumPostReview() {
  const [filterStatus, setFilterStatus] = useState('pending')
  const [posts, setPosts] = useState([])
  const [counts, setCounts] = useState({ pending: 0, approved: 0, rejected: 0, all: 0 })
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState('')
  const [actingPostId, setActingPostId] = useState(null)
  const [selectedPost, setSelectedPost] = useState(null)

  useEffect(() => {
    let active = true

    async function loadPosts() {
      setIsLoading(true)
      setErrorMessage('')
      try {
        const data = await adminFetch(`/forum-project/api/admin-posts.php?status=${encodeURIComponent(filterStatus)}`)
        if (!active) {
          return
        }
        setPosts(Array.isArray(data.posts) ? data.posts : [])
        setCounts(data.counts || { pending: 0, approved: 0, rejected: 0, all: 0 })
      } catch (error) {
        if (!active) {
          return
        }
        setErrorMessage(error.message || 'Failed to load forum posts.')
      } finally {
        if (active) {
          setIsLoading(false)
        }
      }
    }

    loadPosts()

    return () => {
      active = false
    }
  }, [filterStatus])

  async function updatePostStatus(postId, action) {
    setActingPostId(postId)
    setErrorMessage('')

    try {
      const data = await adminFetch('/forum-project/api/admin-posts.php', {
        method: 'POST',
        body: JSON.stringify({ postId, action })
      })

      setCounts(data.counts || counts)
      setPosts((currentPosts) => currentPosts.filter((post) => post.id !== postId))
    } catch (error) {
      setErrorMessage(error.message || 'Failed to update post status.')
    } finally {
      setActingPostId(null)
    }
  }

  function handleApprove(postId) {
    return updatePostStatus(postId, 'approve')
  }

  function handleReject(postId) {
    return updatePostStatus(postId, 'reject')
  }

  function handleDelete(postId) {
    const confirmed = window.confirm('Delete this published post? It will be removed from the public forum.')
    if (!confirmed) {
      return
    }
    return updatePostStatus(postId, 'delete')
  }

  function closePostPreview() {
    setSelectedPost(null)
  }

  function getStatusBadge(status) {
    const statusMap = {
      'Under review': { label: 'Under review', style: styles.statusPending },
      active: { label: 'Active', style: styles.statusActive },
      Rejected: { label: 'Rejected', style: styles.statusRejected }
    }
    const info = statusMap[status] || statusMap['Under review']
    return <span style={{ ...styles.statusBadge, ...info.style }}>{info.label}</span>
  }

  return (
    <div style={styles.container}>
      <header style={styles.header}>
        <div style={styles.hero}>
          <div style={styles.eyebrow}>Admin Content Moderation</div>
          <h1 style={styles.title}>Forum Review</h1>
          <p style={styles.subtitle}>Review forum posts stored in the live database and update their moderation status.</p>
        </div>
        <div style={styles.filterWrap}>
          <div style={styles.filterContainer}>
            <button
              style={{
                ...styles.filterButton,
                ...(filterStatus === 'pending' ? styles.activeFilter : {})
              }}
              onClick={() => setFilterStatus('pending')}
            >
              Pending ({counts.pending || 0})
            </button>
            <button
              style={{
                ...styles.filterButton,
                ...(filterStatus === 'approved' ? styles.activeFilter : {})
              }}
              onClick={() => setFilterStatus('approved')}
            >
              Approved ({counts.approved || 0})
            </button>
            <button
              style={{
                ...styles.filterButton,
                ...(filterStatus === 'rejected' ? styles.activeFilter : {})
              }}
              onClick={() => setFilterStatus('rejected')}
            >
              Rejected ({counts.rejected || 0})
            </button>
          </div>
        </div>
      </header>

      <div style={styles.content}>
        <div style={styles.summaryBar}>
          <div style={styles.summaryChip}>Total tracked posts: {counts.all || 0}</div>
          <div style={styles.summaryChip}>Current filter: {filterStatus}</div>
        </div>

        {errorMessage ? <div style={styles.errorBanner}>{errorMessage}</div> : null}

        <div style={styles.mainContent}>
          {isLoading ? (
            <div style={styles.emptyState}>
              <p style={styles.emptyText}>Loading posts...</p>
            </div>
          ) : posts.length === 0 ? (
            <div style={styles.emptyState}>
              <p style={styles.emptyText}>No posts found for this filter.</p>
            </div>
          ) : (
            posts.map((post) => {
              const isActing = actingPostId === post.id
              return (
                <article
                  key={post.id}
                  style={styles.postItem}
                  onClick={() => setSelectedPost(post)}
                  role="button"
                  tabIndex={0}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                      event.preventDefault()
                      setSelectedPost(post)
                    }
                  }}
                >
                  <div style={styles.postAvatarRail}>
                    <div style={styles.postAvatarCircle}>{post.authorInitial}</div>
                  </div>

                  <div style={styles.postMain}>
                    <div style={styles.postAuthorRow}>
                      <span style={styles.postAuthorName}>{post.author}</span>
                      <span style={styles.postAuthorTime}>posted on {post.publishTime}</span>
                      {getStatusBadge(post.status)}
                    </div>
                    <h3 style={styles.postTitle}>{post.title}</h3>
                    <p style={styles.postSummary}>{post.content}</p>
                  </div>

                  <div style={styles.postSide}>
                    <div style={styles.postTags}>
                      {post.labels.map((label) => (
                        <span key={label} style={styles.tagBadge}>{label}</span>
                      ))}
                      <span style={styles.typeBadge}>{post.mediaType || 'text'}</span>
                    </div>
                    <div style={styles.postStats}>
                      <div style={styles.statLine}>
                        <span style={styles.statLabel}>Comments</span>
                        <span>{post.commentCount}</span>
                      </div>
                      <div style={styles.statLine}>
                        <span style={styles.statLabel}>Views</span>
                        <span>{post.views}</span>
                      </div>
                      <div style={styles.statLine}>
                        <span style={styles.statLabel}>Likes</span>
                        <span>{post.likeCount}</span>
                      </div>
                    </div>
                    {post.status === 'Under review' ? (
                      <div style={styles.actionButtons}>
                        <button
                          style={styles.approveButton}
                          onClick={(event) => {
                            event.stopPropagation()
                            handleApprove(post.id)
                          }}
                          disabled={isActing}
                        >
                          {isActing ? 'Saving...' : 'Approve'}
                        </button>
                        <button
                          style={styles.rejectButton}
                          onClick={(event) => {
                            event.stopPropagation()
                            handleReject(post.id)
                          }}
                          disabled={isActing}
                        >
                          {isActing ? 'Saving...' : 'Reject'}
                        </button>
                      </div>
                    ) : null}
                    {post.status === 'active' ? (
                      <div style={styles.actionButtons}>
                        <button
                          style={styles.deleteButton}
                          onClick={(event) => {
                            event.stopPropagation()
                            handleDelete(post.id)
                          }}
                          disabled={isActing}
                        >
                          {isActing ? 'Deleting...' : 'Delete'}
                        </button>
                      </div>
                    ) : null}
                  </div>
                </article>
              )
            })
          )}
        </div>
      </div>

      {selectedPost ? (
        <div style={styles.previewOverlay} onClick={closePostPreview}>
          <div style={styles.previewCard} onClick={(event) => event.stopPropagation()}>
            <div style={styles.previewHeader}>
              <div>
                <div style={styles.previewEyebrow}>Forum Post Preview</div>
                <h2 style={styles.previewTitle}>{selectedPost.title}</h2>
                <div style={styles.previewMeta}>
                  <span>{selectedPost.author}</span>
                  <span>{selectedPost.publishTime}</span>
                  {getStatusBadge(selectedPost.status)}
                </div>
              </div>
              <button type="button" style={styles.previewClose} onClick={closePostPreview}>Back</button>
            </div>
            <div style={styles.previewInfoRow}>
              {(selectedPost.labels || []).map((label) => (
                <span key={label} style={styles.tagBadge}>{label}</span>
              ))}
              <span style={styles.typeBadge}>{selectedPost.mediaType || 'text'}</span>
            </div>
            <div
              style={styles.previewBody}
              dangerouslySetInnerHTML={{ __html: renderAdminPostContent(selectedPost.content || '') }}
            />
            <div style={styles.previewStats}>
              <span>Comments: {selectedPost.commentCount}</span>
              <span>Views: {selectedPost.views}</span>
              <span>Likes: {selectedPost.likeCount}</span>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  )
}

const styles = {
  container: {
    paddingBottom: '48px'
  },
  header: {
    maxWidth: '1280px',
    margin: '0 auto',
    padding: '42px 20px 24px',
    display: 'grid',
    gridTemplateColumns: 'minmax(0, 1fr) 360px',
    gap: '24px',
    alignItems: 'end'
  },
  hero: {
    minWidth: 0
  },
  eyebrow: {
    fontSize: '0.72rem',
    fontWeight: 700,
    letterSpacing: '0.24rem',
    textTransform: 'uppercase',
    color: 'rgba(58, 78, 107, 0.45)',
    marginBottom: '12px'
  },
  title: {
    margin: '0 0 10px',
    fontFamily: 'Inter, sans-serif',
    fontSize: '2.9rem',
    lineHeight: 1,
    fontWeight: 700,
    letterSpacing: '-0.03em',
    color: 'var(--secondary-color)'
  },
  subtitle: {
    margin: 0,
    maxWidth: '620px',
    color: 'rgba(58, 78, 107, 0.74)',
    fontSize: '0.95rem',
    lineHeight: 1.6
  },
  filterWrap: {
    display: 'flex',
    justifyContent: 'flex-end'
  },
  filterContainer: {
    display: 'flex',
    gap: '8px',
    flexDirection: 'row',
    width: '100%',
    maxWidth: '480px'
  },
  filterButton: {
    padding: '12px 22px',
    backgroundColor: 'rgba(155, 183, 212, 0.8)',
    color: 'var(--white)',
    border: 'none',
    borderRadius: '8px',
    cursor: 'pointer',
    fontWeight: 600,
    fontSize: '0.85rem',
    transition: 'all 0.3s ease',
    boxShadow: '0 14px 28px rgba(58, 78, 107, 0.12)',
    fontFamily: 'Inter, sans-serif',
    flex: 1
  },
  activeFilter: {
    backgroundColor: 'rgba(58, 78, 107, 0.94)'
  },
  content: {
    maxWidth: '1280px',
    margin: '0 auto',
    padding: '0 20px'
  },
  summaryBar: {
    display: 'flex',
    gap: '12px',
    marginBottom: '16px',
    flexWrap: 'wrap'
  },
  summaryChip: {
    padding: '10px 14px',
    borderRadius: '999px',
    background: 'rgba(255, 255, 255, 0.82)',
    border: '1px solid rgba(58, 78, 107, 0.08)',
    color: 'rgba(58, 78, 107, 0.72)',
    fontSize: '0.82rem',
    fontWeight: 700
  },
  errorBanner: {
    marginBottom: '16px',
    padding: '14px 16px',
    borderRadius: '18px',
    backgroundColor: 'rgba(239, 68, 68, 0.12)',
    color: '#b42318',
    fontWeight: 700
  },
  mainContent: {
    minWidth: 0,
    background: 'rgba(255, 255, 255, 0.88)',
    border: '1px solid rgba(58, 78, 107, 0.08)',
    borderRadius: '26px',
    padding: '12px 18px',
    boxShadow: '0 18px 44px rgba(58, 78, 107, 0.06)'
  },
  emptyState: {
    textAlign: 'center',
    padding: '48px',
    color: 'rgba(58, 78, 107, 0.5)'
  },
  emptyText: {
    fontSize: '1rem'
  },
  postItem: {
    background: 'transparent',
    padding: '18px 12px 18px 8px',
    borderRadius: 0,
    marginBottom: 0,
    boxShadow: 'none',
    border: 0,
    borderBottom: '1px solid rgba(58, 78, 107, 0.08)',
    display: 'flex',
    alignItems: 'flex-start',
    gap: '18px',
    transition: 'background 0.2s ease',
    backdropFilter: 'none',
    cursor: 'pointer'
  },
  postAvatarRail: {
    flexShrink: 0,
    paddingTop: '2px'
  },
  postAvatarCircle: {
    width: '42px',
    height: '42px',
    borderRadius: '50%',
    background: 'linear-gradient(135deg, #9BB7D4, #c7d5e5)',
    color: '#fff',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '0.92rem',
    fontWeight: 700,
    boxShadow: '0 10px 20px rgba(155, 183, 212, 0.22)'
  },
  postMain: {
    flex: 1,
    minWidth: 0
  },
  postAuthorRow: {
    display: 'flex',
    alignItems: 'center',
    gap: '12px',
    marginBottom: '8px',
    flexWrap: 'wrap'
  },
  postAuthorName: {
    fontWeight: 700,
    fontSize: '0.92rem',
    color: 'var(--secondary-color)'
  },
  postAuthorTime: {
    fontSize: '0.78rem',
    color: 'rgba(58, 78, 107, 0.6)'
  },
  statusBadge: {
    padding: '4px 10px',
    borderRadius: '12px',
    fontSize: '0.72rem',
    fontWeight: 600,
    textTransform: 'uppercase',
    letterSpacing: '0.5px'
  },
  statusPending: {
    backgroundColor: 'rgba(251, 191, 36, 0.15)',
    color: '#d97706'
  },
  statusActive: {
    backgroundColor: 'rgba(34, 197, 94, 0.15)',
    color: '#16a34a'
  },
  statusRejected: {
    backgroundColor: 'rgba(239, 68, 68, 0.15)',
    color: '#dc2626'
  },
  postTitle: {
    fontSize: '1.05rem',
    fontWeight: 700,
    color: 'var(--secondary-color)',
    margin: '0 0 8px 0',
    lineHeight: 1.35,
    letterSpacing: '-0.01em'
  },
  postSummary: {
    fontSize: '0.88rem',
    color: 'rgba(58, 78, 107, 0.74)',
    lineHeight: 1.55,
    margin: 0,
    display: '-webkit-box',
    WebkitLineClamp: 3,
    WebkitBoxOrient: 'vertical',
    overflow: 'hidden'
  },
  postSide: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'flex-end',
    gap: '10px',
    minWidth: '180px'
  },
  postTags: {
    display: 'flex',
    gap: '6px',
    flexWrap: 'wrap',
    justifyContent: 'flex-end'
  },
  tagBadge: {
    padding: '4px 10px',
    borderRadius: '10px',
    backgroundColor: 'rgba(155, 183, 212, 0.18)',
    color: 'var(--secondary-color)',
    fontSize: '0.72rem',
    fontWeight: 600
  },
  typeBadge: {
    padding: '4px 10px',
    borderRadius: '10px',
    backgroundColor: 'rgba(58, 78, 107, 0.08)',
    color: 'rgba(58, 78, 107, 0.6)',
    fontSize: '0.72rem',
    fontWeight: 600,
    textTransform: 'lowercase'
  },
  postStats: {
    display: 'grid',
    gap: '6px'
  },
  statLine: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: '10px',
    fontSize: '0.85rem',
    color: 'rgba(58, 78, 107, 0.7)',
    fontWeight: 600,
    minWidth: '120px'
  },
  statLabel: {
    color: 'rgba(58, 78, 107, 0.55)'
  },
  actionButtons: {
    display: 'flex',
    gap: '8px',
    marginTop: '8px'
  },
  approveButton: {
    padding: '8px 16px',
    borderRadius: '999px',
    border: 'none',
    backgroundColor: 'rgba(34, 197, 94, 0.9)',
    color: 'white',
    fontSize: '0.78rem',
    fontWeight: 700,
    cursor: 'pointer',
    transition: 'all 0.25s ease',
    fontFamily: 'Inter, sans-serif'
  },
  rejectButton: {
    padding: '8px 16px',
    borderRadius: '999px',
    border: 'none',
    backgroundColor: 'rgba(239, 68, 68, 0.9)',
    color: 'white',
    fontSize: '0.78rem',
    fontWeight: 700,
    cursor: 'pointer',
    transition: 'all 0.25s ease',
    fontFamily: 'Inter, sans-serif'
  },
  deleteButton: {
    padding: '8px 16px',
    borderRadius: '999px',
    border: 'none',
    backgroundColor: 'rgba(127, 29, 29, 0.92)',
    color: 'white',
    fontSize: '0.78rem',
    fontWeight: 700,
    cursor: 'pointer',
    transition: 'all 0.25s ease',
    fontFamily: 'Inter, sans-serif'
  },
  previewOverlay: {
    position: 'fixed',
    inset: 0,
    zIndex: 1200,
    background: 'rgba(28, 35, 49, 0.42)',
    backdropFilter: 'blur(6px)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '24px'
  },
  previewCard: {
    width: 'min(920px, 100%)',
    maxHeight: '88vh',
    overflow: 'auto',
    borderRadius: '28px',
    background: 'rgba(255,255,255,0.98)',
    boxShadow: '0 28px 64px rgba(58, 78, 107, 0.18)',
    padding: '24px'
  },
  previewHeader: {
    display: 'flex',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: '18px',
    marginBottom: '18px'
  },
  previewEyebrow: {
    fontSize: '0.74rem',
    fontWeight: 800,
    letterSpacing: '0.16em',
    textTransform: 'uppercase',
    color: 'rgba(58, 78, 107, 0.48)',
    marginBottom: '10px'
  },
  previewTitle: {
    margin: '0 0 10px',
    fontSize: '1.6rem',
    lineHeight: 1.2,
    color: 'var(--secondary-color)'
  },
  previewMeta: {
    display: 'flex',
    alignItems: 'center',
    gap: '10px',
    flexWrap: 'wrap',
    color: 'rgba(58, 78, 107, 0.62)',
    fontSize: '0.84rem'
  },
  previewClose: {
    minHeight: '40px',
    padding: '0 16px',
    borderRadius: '999px',
    border: '1px solid rgba(58, 78, 107, 0.14)',
    background: 'rgba(255,255,255,0.88)',
    color: 'var(--secondary-color)',
    fontSize: '0.82rem',
    fontWeight: 700,
    cursor: 'pointer'
  },
  previewInfoRow: {
    display: 'flex',
    alignItems: 'center',
    gap: '8px',
    flexWrap: 'wrap',
    marginBottom: '18px'
  },
  previewBody: {
    border: '1px solid rgba(58, 78, 107, 0.08)',
    borderRadius: '20px',
    background: 'rgba(247, 244, 239, 0.62)',
    padding: '18px',
    color: 'rgba(58, 78, 107, 0.86)',
    fontSize: '0.94rem',
    lineHeight: 1.7
  },
  previewStats: {
    display: 'flex',
    gap: '18px',
    flexWrap: 'wrap',
    marginTop: '16px',
    color: 'rgba(58, 78, 107, 0.62)',
    fontSize: '0.82rem',
    fontWeight: 700
  }
}

export default ForumPostReview
