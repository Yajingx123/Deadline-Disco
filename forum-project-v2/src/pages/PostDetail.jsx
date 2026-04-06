import { useMemo } from 'react'
import './PostDetail.css'

const SAMPLE_COMMENTS = [
  { id: 'sample-c-1', author: 'noura', time: '2026-04-05 21:10', content: 'This is super helpful. Thanks for sharing the full process.' },
  { id: 'sample-c-2', author: 'liam', time: '2026-04-05 21:26', content: 'Could you also share which part took the longest time?' },
  { id: 'sample-c-3', author: 'mia', time: '2026-04-05 21:42', content: 'I tried this structure this week and it really improved my review speed.' },
  { id: 'sample-c-4', author: 'alex', time: '2026-04-05 22:03', content: 'Great thread. The timeline breakdown is exactly what I needed.' },
  { id: 'sample-c-5', author: 'zoe', time: '2026-04-05 22:11', content: 'Can you share one bad strategy you tried before finding this method?' },
  { id: 'sample-c-6', author: 'ben', time: '2026-04-05 22:27', content: 'I used your sequence for two days and already feel less overwhelmed.' },
  { id: 'sample-c-7', author: 'iris', time: '2026-04-05 22:41', content: 'The “review weak points every morning” line is gold. Thanks.' },
  { id: 'sample-c-8', author: 'noah', time: '2026-04-05 22:55', content: 'Would love a template checklist version of this thread.' },
  { id: 'sample-c-9', author: 'emma', time: '2026-04-05 23:08', content: 'This saved me before mock week. Please keep posting these summaries.' },
  { id: 'sample-c-10', author: 'ryan', time: '2026-04-05 23:19', content: 'I can confirm this works for language revision too, not only STEM courses.' },
]

export default function PostDetail({ post, onBack }) {
  const comments = useMemo(() => {
    const realComments = Array.isArray(post?.comments) ? post.comments : []
    if (realComments.length > 0) return realComments
    return SAMPLE_COMMENTS
  }, [post])

  if (!post) return null

  return (
    <div className="v2-postdetail-page">
      <button className="v2-postdetail-back" aria-label="Back to list" onClick={onBack} />

      <aside className="v2-postdetail-side v2-postdetail-side--left" />

      <aside className="v2-postdetail-side">
        <button className="v2-postdetail-reply" aria-label="Reply to post" type="button">YES</button>
      </aside>

      <main className="v2-postdetail-main">
        <section className="v2-postdetail-block v2-postdetail-block--post">
          <div className="v2-postdetail-author">
            <strong>{post.author || 'Sample Author'}</strong>
            <span>{post.publishTime || post.time || 'Sample'}</span>
          </div>

          <h1 className="v2-postdetail-title">{post.title}</h1>

          <div className="v2-postdetail-content">{post.content}</div>

          <div className="v2-postdetail-stats">
            <span>Views {post.views || 0}</span>
            <span>Comments {post.commentCount || comments.length || 0}</span>
            <span>Likes {post.likeCount || 0}</span>
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
    </div>
  )
}
