import { useState } from 'react'

function ForumPostReview() {
  const [filterStatus, setFilterStatus] = useState('pending')

  const mockPosts = [
    {
      id: 1,
      title: 'How should we balance AI tools and original writing in class?',
      content: 'Our seminar keeps debating where AI support becomes too much. I am curious how other students define a fair boundary between drafting support and actual authorship.\n\nWhat counts as acceptable help in your course right now?',
      author: 'student1',
      authorInitial: 'S',
      publishTime: '2025-03-28 21:30',
      status: 'Under review',
      labels: ['Current news', 'Viewpoint topic'],
      viewCount: 15,
      likeCount: 3,
      commentCount: 0
    },
    {
      id: 2,
      title: 'Need help choosing between database systems and web development electives',
      content: 'I can only keep one elective this term. The database course looks practical, but the web development studio might help my portfolio faster.\n\nIf you took either one, what was the workload really like?',
      author: 'student2',
      authorInitial: 'S',
      publishTime: '2025-03-28 18:15',
      status: 'Under review',
      labels: ['Seek help'],
      viewCount: 28,
      likeCount: 5,
      commentCount: 2
    },
    {
      id: 3,
      title: 'Study tips for final exams',
      content: 'Share your best study strategies for the upcoming finals. I am particularly interested in time management techniques and effective note-taking methods.',
      author: 'student3',
      authorInitial: 'S',
      publishTime: '2025-03-27 14:20',
      status: 'Rejected',
      labels: ['Study tips'],
      viewCount: 45,
      likeCount: 8,
      commentCount: 5
    },
    {
      id: 4,
      title: 'Campus events this weekend',
      content: 'Looking for recommendations on what to do on campus this weekend. Any concerts, exhibitions, or other events worth attending?',
      author: 'student4',
      authorInitial: 'S',
      publishTime: '2025-03-26 09:45',
      status: 'active',
      labels: ['Campus life'],
      viewCount: 67,
      likeCount: 12,
      commentCount: 8
    }
  ]

  const filteredPosts = mockPosts.filter(post => {
    if (filterStatus === 'pending') return post.status === 'Under review'
    if (filterStatus === 'approved') return post.status === 'active'
    if (filterStatus === 'rejected') return post.status === 'Rejected'
    return true
  })

  const handleApprove = (postId) => {
    console.log('Approve post:', postId)
  }

  const handleReject = (postId) => {
    console.log('Reject post:', postId)
  }

  const getStatusBadge = (status) => {
    const statusMap = {
      'Under review': { label: 'Under review', style: styles.statusPending },
      'active': { label: 'Active', style: styles.statusActive },
      'Rejected': { label: 'Rejected', style: styles.statusRejected }
    }
    const info = statusMap[status] || statusMap['Under review']
    return <span style={{ ...styles.statusBadge, ...info.style }}>{info.label}</span>
  }

  return (
    <div style={styles.container}>
      <header style={styles.header}>
        <div style={styles.hero}>
          <div style={styles.eyebrow}>Admin — Content Moderation</div>
          <h1 style={styles.title}>Forum Review</h1>
          <p style={styles.subtitle}>Review and moderate forum posts. Approve quality content and reject inappropriate submissions.</p>
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
              Pending ({mockPosts.filter(p => p.status === 'Under review').length})
            </button>
            <button
              style={{
                ...styles.filterButton,
                ...(filterStatus === 'approved' ? styles.activeFilter : {})
              }}
              onClick={() => setFilterStatus('approved')}
            >
              Approved ({mockPosts.filter(p => p.status === 'active').length})
            </button>
            <button
              style={{
                ...styles.filterButton,
                ...(filterStatus === 'rejected' ? styles.activeFilter : {})
              }}
              onClick={() => setFilterStatus('rejected')}
            >
              Rejected ({mockPosts.filter(p => p.status === 'Rejected').length})
            </button>
          </div>
        </div>
      </header>

      <div style={styles.content}>
        <div style={styles.mainContent}>
          {filteredPosts.length === 0 ? (
            <div style={styles.emptyState}>
              <p style={styles.emptyText}>No posts found</p>
            </div>
          ) : (
            filteredPosts.map(post => (
              <article key={post.id} style={styles.postItem}>
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
                    {post.labels.map(label => (
                      <span key={label} style={styles.tagBadge}>{label}</span>
                    ))}
                    <span style={styles.typeBadge}>text</span>
                  </div>
                  <div style={styles.postStats}>
                    <div style={styles.statLine}>
                      <span style={styles.statIcon}>💬</span>
                      <span>{post.commentCount}</span>
                    </div>
                    <div style={styles.statLine}>
                      <span style={styles.statIcon}>👁</span>
                      <span>{post.viewCount}</span>
                    </div>
                  </div>
                  {post.status === 'Under review' && (
                    <div style={styles.actionButtons}>
                      <button
                        style={styles.approveButton}
                        onClick={() => handleApprove(post.id)}
                      >
                        ✓ Approve
                      </button>
                      <button
                        style={styles.rejectButton}
                        onClick={() => handleReject(post.id)}
                      >
                        ✕ Reject
                      </button>
                    </div>
                  )}
                </div>
              </article>
            ))
          )}
        </div>
      </div>
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
    flexDirection: 'column',
    width: '100%',
    maxWidth: '360px'
  },
  filterButton: {
    padding: '12px 22px',
    backgroundColor: 'rgba(155, 183, 212, 0.8)',
    color: 'var(--white)',
    border: 'none',
    borderRadius: '999px',
    cursor: 'pointer',
    fontWeight: 600,
    fontSize: '0.85rem',
    transition: 'all 0.3s ease',
    boxShadow: '0 14px 28px rgba(58, 78, 107, 0.12)',
    fontFamily: 'Inter, sans-serif'
  },
  activeFilter: {
    backgroundColor: 'rgba(58, 78, 107, 0.94)'
  },
  content: {
    maxWidth: '1280px',
    margin: '0 auto',
    padding: '0 20px'
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
    WebkitLineClamp: 2,
    WebkitBoxOrient: 'vertical',
    overflow: 'hidden'
  },
  postSide: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'flex-end',
    gap: '10px',
    minWidth: '120px'
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
    display: 'flex',
    gap: '16px'
  },
  statLine: {
    display: 'flex',
    alignItems: 'center',
    gap: '4px',
    fontSize: '0.85rem',
    color: 'rgba(58, 78, 107, 0.7)',
    fontWeight: 600
  },
  statIcon: {
    fontSize: '0.9rem'
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
  }
}

export default ForumPostReview
