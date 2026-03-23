export default function PostItem({ post, onPostClick }) {
  
  // 新增：浏览量格式化逻辑
  const formatViews = (views) => {
    if (views >= 10000) {
      // 保留一位小数，例如 10500 -> 1.1w, 123450 -> 12.3w
      return (views / 10000).toFixed(1) + 'w';
    }
    return views;
  };

  return (
    <div className="post-item" onClick={() => onPostClick(post.id)} style={{ cursor: 'pointer' }}>
      {/* 绑定点击事件，并设置鼠标样式提示可点击 */}
      <div className="post-main">
        <h3 className="post-title">
          {post.title} <span style={{fontSize:'12px', color:'#999'}}>(ID: {post.id})</span>
        </h3>
        <div className="post-tags">
          {post.tags.map(tag => (
            <span key={tag} className="tag-badge">{tag}</span>
          ))}
        </div>
      </div>

      <div className="post-meta">
        <div className="meta-row"><strong>Type:</strong> <span>{post.mediaType}</span></div>
        <div className="meta-row"><strong>Author:</strong> <span>{post.author}</span></div>
        {/* 调用格式化函数渲染浏览量，并加亮显示以便测试 */}
        <div className="meta-row">
          <strong>Views:</strong> 
          <span style={{ color: '#0056b3', fontWeight: 'bold' }}>{formatViews(post.views)}</span>
        </div>
        <div className="meta-row"><strong>Time:</strong> <span>{post.publishTime}</span></div>
      </div>
      
    </div>
  );
}