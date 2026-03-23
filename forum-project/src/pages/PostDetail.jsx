import React, { useState } from 'react';
import { renderFormattedText } from '../utils/formatText';
import PostModal from '../components/PostModal';
import './PostDetail.css';

function getCommentId(comment = {}) {
  return Number(comment.id ?? comment.commentId ?? comment.comment_id ?? 0);
}

const PostDetail = ({ post, onBack, onAddComment, onDeletePost, onDeleteComment, labelOptions, currentUser }) => {
  if (!post) return null;

  const [isReplyModalOpen, setIsReplyModalOpen] = useState(false);
  const [replyTarget, setReplyTarget] = useState(null);
  const comments = post.comments || [];

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

  return (
    <div className="detail-page-wrapper">
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
                        Replying to {comment.replyTo.author}: {comment.replyTo.content}
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
        quoteText={replyTarget ? replyTarget.content : ''}
        parentTitle={post.title}
        labelOptions={labelOptions}
        currentUser={currentUser}
      />
    </div>
  );
};

export default PostDetail;
