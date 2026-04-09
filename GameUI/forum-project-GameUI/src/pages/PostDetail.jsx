import { useEffect, useMemo, useState } from 'react'
import PostModal from '../components/PostModal'
import { favoritePost, likePost } from '../api/forumApi'
import './PostDetail.css'

export default function PostDetail({
  post,
  onBack,
  onAddComment,
  onLikeChange,
  onFavoriteChange,
  labelOptions = [],
  currentUser = null,
}) {
  const [isReplyModalOpen, setIsReplyModalOpen] = useState(false)
  const [isLiked, setIsLiked] = useState(Boolean(post?.isLiked))
  const [isFavorited, setIsFavorited] = useState(Boolean(post?.isFavorited))
  const [likeCount, setLikeCount] = useState(Number(post?.likeCount || 0))
  const [favoriteCount, setFavoriteCount] = useState(Number(post?.favoriteCount || 0))

  useEffect(() => {
    setIsLiked(Boolean(post?.isLiked))
    setIsFavorited(Boolean(post?.isFavorited))
    setLikeCount(Number(post?.likeCount || 0))
    setFavoriteCount(Number(post?.favoriteCount || 0))
  }, [post?.id, post?.isLiked, post?.isFavorited, post?.likeCount, post?.favoriteCount])

  const comments = useMemo(() => {
    return Array.isArray(post?.comments) ? post.comments : []
  }, [post])

  if (!post) return null

  const handleLike = async () => {
    try {
      const data = await likePost(post.id)
      const nextLiked = Boolean(data?.liked)
      const nextLikeCount = Number(data?.likeCount || 0)
      setIsLiked(nextLiked)
      setLikeCount(nextLikeCount)
      onLikeChange?.(post.id, nextLiked, nextLikeCount)
    } catch (_err) {
      // keep silent in this stylized page
    }
  }

  const handleFavorite = async () => {
    try {
      const data = await favoritePost(post.id)
      const nextFavorited = Boolean(data?.favorited)
      const nextFavoriteCount = Number(data?.favoriteCount || 0)
      setIsFavorited(nextFavorited)
      setFavoriteCount(nextFavoriteCount)
      onFavoriteChange?.(post.id, nextFavorited, nextFavoriteCount)
    } catch (_err) {
      // keep silent in this stylized page
    }
  }

  const handleReplySubmit = async (payload) => {
    const replyText = String(payload?.content || '').trim()
    if (!replyText) return
    if (typeof onAddComment !== 'function') return

    await onAddComment({
      postId: post.id,
      content: replyText,
      parentCommentId: null,
    })
    setIsReplyModalOpen(false)
  }

  return (
    <div className="v2-postdetail-page">
      <button className="v2-postdetail-back" aria-label="Back to list" onClick={onBack} />

      <aside className="v2-postdetail-side v2-postdetail-side--left" />

      <aside className="v2-postdetail-side">
        <button
          className="v2-postdetail-reply"
          aria-label="Reply to post"
          type="button"
          onClick={() => setIsReplyModalOpen(true)}
        >
          YES
        </button>
      </aside>

      <main className="v2-postdetail-main">
        <section className="v2-postdetail-block v2-postdetail-block--post">
          <div className="v2-postdetail-author">
            <strong>{post.author || 'User'}</strong>
            <span>{post.publishTime || post.time || 'now'}</span>
          </div>

          <h1 className="v2-postdetail-title">{post.title}</h1>

          <div className="v2-postdetail-content">{post.content}</div>

          <div className="v2-postdetail-stats">
            <span>Views {post.views || 0}</span>
            <span>Comments {post.commentCount || comments.length || 0}</span>
            <button
              type="button"
              className={`v2-postdetail-action ${isLiked ? 'is-active' : ''}`}
              onClick={handleLike}
            >
              {isLiked ? 'Liked' : 'Like'} {likeCount}
            </button>
            <button
              type="button"
              className={`v2-postdetail-action ${isFavorited ? 'is-active' : ''}`}
              onClick={handleFavorite}
            >
              {isFavorited ? 'Favorited' : 'Favorite'} {favoriteCount}
            </button>
          </div>
        </section>

        <section className="v2-postdetail-block v2-postdetail-block--comments">
          <h2 className="v2-postdetail-comments-title">All Comments ({comments.length})</h2>

          {comments.length === 0 ? (
            <div className="v2-postdetail-empty">No comments yet.</div>
          ) : (
            <div className="v2-postdetail-comments-list">
              {comments.map((comment) => (
                <article key={comment.id} className="v2-postdetail-comment">
                  <div className="v2-postdetail-comment-head">
                    <strong>{comment.author || 'User'}</strong>
                    <span>{comment.time || 'now'}</span>
                  </div>
                  <p>{comment.content || ''}</p>
                </article>
              ))}
            </div>
          )}
        </section>
      </main>

      <PostModal
        isOpen={isReplyModalOpen}
        onClose={() => setIsReplyModalOpen(false)}
        onSubmit={handleReplySubmit}
        isReplyMode={true}
        quoteText=""
        parentTitle={post.title || ''}
        labelOptions={labelOptions}
        currentUser={currentUser}
      />
    </div>
  )
}
