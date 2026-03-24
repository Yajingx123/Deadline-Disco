export default function PostItem({ post, onPostClick }) {
  const formatViews = (views) => {
    if (views >= 10000) {
      return (views / 10000).toFixed(1) + 'w';
    }
    return views;
  };

  return (
    <article className="post-item" onClick={() => onPostClick(post.id)} style={{ cursor: 'pointer' }}>
      <div className="post-avatarRail">
        <div className="post-avatarCircle">{post.authorInitial || post.author?.[0] || 'U'}</div>
      </div>

      <div className="post-main">
        <div className="post-authorRow">
          <span className="post-authorName">{post.author}</span>
          <span className="post-authorTime">posted on {post.publishTime}</span>
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
      </div>
    </article>
  );
}
