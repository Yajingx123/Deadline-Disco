export default function PostItem({ post, onPostClick, showStatus, onDelete }) {
  const formatViews = (views) => {
    if (views >= 10000) {
      return (views / 10000).toFixed(1) + 'w';
    }
    return views;
  };

  const getStatusLabel = (status) => {
    const statusMap = {
      'active': { label: 'Active', className: 'post-status--active' },
      'Under review': { label: 'Under review', className: 'post-status--pending' },
      'Rejected': { label: 'Rejected', className: 'post-status--rejected' },
    };
    return statusMap[status] || { label: 'Under review', className: 'post-status--pending' };
  };

  const statusInfo = showStatus ? getStatusLabel(post.status) : null;

  return (
    <article className="post-item" onClick={() => onPostClick(post.id)} style={{ cursor: 'pointer' }}>
      <div className="post-avatarRail">
        <div className="post-avatarCircle">{post.authorInitial || post.author?.[0] || 'U'}</div>
      </div>

      <div className="post-main">
        <div className="post-authorRow">
          <span className="post-authorName">{post.author}</span>
          <span className="post-authorTime">posted on {post.publishTime}</span>
          {showStatus && statusInfo && (
            <span className={`post-status ${statusInfo.className}`}>{statusInfo.label}</span>
          )}
        </div>
        <h3 className="post-title">
          {post.title}
        </h3>
        {post.summary && <p className="post-summary">{post.summary}</p>}
      </div>

      <div className="post-side">
        <div className="post-tags post-tags--side">
          {post.labels.map((tag) => (
            <span key={tag} className="tag-badge">{tag}</span>
          ))}
          <span className="post-typeBadge">{post.mediaType}</span>
        </div>
        <div className="post-stats">
          <div className="post-stats__line">
            <span className="post-stats__icon">💬</span>
            <span>{post.commentCount}</span>
          </div>
          <div className="post-stats__line">
            <span className="post-stats__icon">👁</span>
            <span>{formatViews(post.views)}</span>
          </div>
        </div>
        {showStatus && onDelete && (
          <button 
            className="post-delete-btn"
            onClick={(e) => {
              e.stopPropagation();
              if (confirm('Are you sure you want to delete this post?')) {
                onDelete(post.id);
              }
            }}
            style={{ marginTop: '8px' }}
          >
            Delete
          </button>
        )}
      </div>
    </article>
  );
}
