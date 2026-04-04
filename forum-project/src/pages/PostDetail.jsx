import React, { useEffect, useRef, useState } from 'react';
import { enhanceRenderedAudioPlayers, getReplyPreview, renderFormattedText } from '../utils/formatText';
import PostModal from '../components/PostModal';
import { likePost, favoritePost } from '../api/forumApi';
import './PostDetail.css';

function getCommentId(comment = {}) {
  return Number(comment.id ?? comment.commentId ?? comment.comment_id ?? 0);
}

const PostDetail = ({ post, onBack, onAddComment, onDeletePost, onDeleteComment, labelOptions, currentUser, onLikeChange, onFavoriteChange }) => {
  if (!post) return null;

  const [isReplyModalOpen, setIsReplyModalOpen] = useState(false);
  const [replyTarget, setReplyTarget] = useState(null);
  const [isLiked, setIsLiked] = useState(post.isLiked || false);
  const [isFavorited, setIsFavorited] = useState(post.isFavorited || false);
  const [likeCount, setLikeCount] = useState(post.likeCount || 0);
  const [favoriteCount, setFavoriteCount] = useState(post.favoriteCount || 0);
  const [imageViewer, setImageViewer] = useState(null);
  const comments = post.comments || [];
  const contentRootRef = useRef(null);

  useEffect(() => {
    return enhanceRenderedAudioPlayers(contentRootRef.current);
  }, [post]);

  useEffect(() => {
    const root = contentRootRef.current;
    if (!root) return undefined;

    const handleImageClick = (event) => {
      const trigger = event.target.closest('.forumInlineImage');
      if (!trigger) return;
      event.preventDefault();
      const image = trigger.querySelector('img');
      setImageViewer({
        src: trigger.getAttribute('href') || image?.getAttribute('src') || '',
        alt: image?.getAttribute('alt') || '',
      });
    };

    root.addEventListener('click', handleImageClick);
    return () => root.removeEventListener('click', handleImageClick);
  }, [post]);

  const handleReplySubmit = async (newComment) => {
    await onAddComment({
      postId: post.id,
      content: newComment.content,
      parentCommentId: replyTarget?.id || null,
    });
    setIsReplyModalOpen(false);
    setReplyTarget(null);
  };

  const handleReplyClick = (comment = null) => {
    setReplyTarget(comment);
    setIsReplyModalOpen(true);
  };

  const handleDeleteCommentClick = async (comment) => {
    const commentId = getCommentId(comment);
    if (commentId <= 0) {
      throw new Error('Comment ID is missing. Refresh the post and try again.');
    }
    await onDeleteComment(commentId);
  };

  const canDeletePost = Number(currentUser?.user_id || 0) === Number(post.authorUserId || 0);

  const handleLike = async () => {
    try {
      const data = await likePost(post.id);
      setIsLiked(data.liked);
      setLikeCount(data.likeCount);
      onLikeChange?.(post.id, data.liked, data.likeCount);
    } catch (err) {
      console.error('Failed to like post:', err);
    }
  };

  const handleFavorite = async () => {
    try {
      const data = await favoritePost(post.id);
      setIsFavorited(data.favorited);
      setFavoriteCount(data.favoriteCount);
      onFavoriteChange?.(post.id, data.favorited, data.favoriteCount);
    } catch (err) {
      console.error('Failed to favorite post:', err);
    }
  };

  return (
    <div className="detail-page-wrapper" ref={contentRootRef}>
      {/* Top Navigation Bar */}
      <div className="detail-header">
        <div className="header-left">
          <button onClick={onBack} className="btn-back">
            <span className="icon">←</span> Back to List
          </button>
        </div>
        <div className="header-right">
          {canDeletePost && (
            <button
              onClick={() => onDeletePost(post.id)}
              className="btn-delete-ghost"
            >
              Delete Post
            </button>
          )}
          <button 
            onClick={() => handleReplyClick()} 
            className="btn-reply-main"
          >
            <span className="icon">💬</span> Reply to Post
          </button>
        </div>
      </div>

      {/* Main Post Content Card */}
      <div className="detail-card main-post">
        <div className="detail-post-header">
          <div className="detail-post-author">
            <div className="detail-avatar-circle">{post.author[0]}</div>
            <div className="detail-post-authorText">
              <span className="detail-author-name">{post.author}</span>
              <span className="detail-meta-date">{post.publishTime}</span>
            </div>
          </div>
          <h1 className="detail-post-title">{post.title}</h1>
        </div>

        <div className="post-divider"></div>

        <div 
          className="post-body"
          dangerouslySetInnerHTML={{ __html: renderFormattedText(post.content) }}
        />

        <div className="post-actions-bar">
          <button 
            onClick={handleLike}
            className={`btn-action ${isLiked ? 'btn-action--liked' : ''}`}
          >
            <span className="icon">{isLiked ? '❤️' : '🤍'}</span>
            <span>{likeCount}</span>
          </button>
          <button 
            onClick={handleFavorite}
            className={`btn-action ${isFavorited ? 'btn-action--favorited' : ''}`}
          >
            <span className="icon">{isFavorited ? '⭐' : '☆'}</span>
            <span>{favoriteCount}</span>
          </button>
        </div>
      </div>

      {/* Comments Section */}
      <div className="comments-section">
        <div className="comments-header">
          <h3>All Comments ({comments.length})</h3>
        </div>

        {comments.length === 0 ? (
          <div className="empty-comments">
            <div className="empty-icon">💭</div>
            <p>No comments yet. Be the first to reply!</p>
            <button onClick={() => handleReplyClick()} className="btn-first-comment">
              Write the first reply
            </button>
          </div>
        ) : (
          <div className="comments-list">
            {comments.map((comment) => (
              <div key={comment.id} className="comment-item">
                <div className="comment-avatar">
                  <div className="detail-avatar-circle detail-avatar-circle--small">{comment.avatar || comment.author[0]}</div>
                </div>
                <div className="comment-body">
                  <div className="comment-info">
                    <span className="comment-author">{comment.author}</span>
                    <span className="comment-time">{comment.time}</span>
                  </div>

                  {/* Quote Block */}
                  {comment.replyTo && (
                    <div className="comment-quote-box">
                      <div className="quote-icon">❝</div>
                      <div className="quote-content">
                        Replying to {comment.replyTo.author}: {getReplyPreview(comment.replyTo.content)}
                      </div>
                    </div>
                  )}

                  <div className="comment-text">
                    <div dangerouslySetInnerHTML={{ __html: renderFormattedText(comment.content) }} />
                  </div>

                  <div className="comment-actions">
                    <button 
                      onClick={() => handleReplyClick(comment)}
                      className="btn-reply-small"
                    >
                      Reply
                    </button>
                    {Number(currentUser?.user_id || 0) === Number(comment.authorUserId || 0) && (
                      <button
                        onClick={() => handleDeleteCommentClick(comment)}
                        className="btn-delete-inline"
                        disabled={getCommentId(comment) <= 0}
                      >
                        Delete
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Reuse Publish Modal */}
      <PostModal 
        isOpen={isReplyModalOpen}
        onClose={() => {
          setIsReplyModalOpen(false);
          setReplyTarget(null);
        }}
        onSubmit={handleReplySubmit}
        isReplyMode={true}
        quoteText={replyTarget ? getReplyPreview(replyTarget.content) : ''}
        parentTitle={post.title}
        labelOptions={labelOptions}
        currentUser={currentUser}
      />

      {imageViewer?.src && (
        <div className="post-image-viewer" onClick={() => setImageViewer(null)}>
          <button type="button" className="post-image-viewer__back" onClick={() => setImageViewer(null)}>
            Back
          </button>
          <img
            className="post-image-viewer__img"
            src={imageViewer.src}
            alt={imageViewer.alt || 'Preview image'}
            onClick={(event) => event.stopPropagation()}
          />
        </div>
      )}
    </div>
  );
};

export default PostDetail;
