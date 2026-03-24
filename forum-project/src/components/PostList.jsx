import PostItem from './PostItem';

export default function PostList({ posts, onPostClick }) {
  if (posts.length === 0) {
    return <div className="main-content"><p>No posts found.</p></div>;
  }

  return (
    <div className="main-content">
      {posts.map(post => (
        <PostItem key={post.id} post={post} onPostClick={onPostClick} />
      ))}
    </div>
  );
}