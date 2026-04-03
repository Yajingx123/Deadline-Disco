import PostItem from './PostItem';

export default function PostList({ posts, pinnedPosts = [], onPostClick, showStatus, onDelete }) {
  if (posts.length === 0 && pinnedPosts.length === 0) {
    return <div className="main-content"><p>No posts found.</p></div>;
  }

  return (
    <div className="main-content">
      {pinnedPosts.length > 0 && (
        <div className="pinned-posts">
          <div className="pinned-posts__header">Pinned announcements</div>
          {pinnedPosts.map((post) => (
            <PostItem key={`pinned-${post.id}`} post={post} onPostClick={onPostClick} showStatus={showStatus} onDelete={onDelete} />
          ))}
        </div>
      )}
      {posts.map(post => (
        <PostItem key={post.id} post={post} onPostClick={onPostClick} showStatus={showStatus} onDelete={onDelete} />
      ))}
    </div>
  );
}
