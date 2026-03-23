import React, { useState } from 'react';
import { renderFormattedText } from '../utils/formatText';
import PostModal from '../components/PostModal';
import './PostDetail.css';

const PostDetail = ({ post, onBack }) => {
  if (!post) return null;

  // Initialize comment list
  const [comments, setComments] = useState(post.comments || []);
  const [isReplyModalOpen, setIsReplyModalOpen] = useState(false);
  const [replyQuote, setReplyQuote] = useState('');

  // Handle submitting a reply
  const handleReplySubmit = (newComment) => {
    const commentData = {
      id: Date.now(),
      author: 'Current User', 
      avatar: 'U', // Avatar letter
      time: new Date().toLocaleString(),
      content: newComment.content,
      quote: replyQuote
    };

    setComments([...comments, commentData]);
    setIsReplyModalOpen(false);
    setReplyQuote('');
  };

  // Open reply modal
  const handleReplyClick = (quoteText = '') => {
    setReplyQuote(quoteText);
    setIsReplyModalOpen(true);
  };

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
        <div className="post-header">
          <h1 className="post-title">{post.title}</h1>
          <div className="post-meta">
            <div className="meta-user">
              <div className="avatar-circle">{post.author[0]}</div>
              <span className="author-name">{post.author}</span>
            </div>
            <span className="meta-date">📅 {post.time}</span>
          </div>
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
                  <div className="avatar-circle small">{comment.avatar || comment.author[0]}</div>
                </div>
                <div className="comment-body">
                  <div className="comment-info">
                    <span className="comment-author">{comment.author}</span>
                    <span className="comment-time">{comment.time}</span>
                  </div>

                  {/* Quote Block */}
                  {comment.quote && (
                    <div className="comment-quote-box">
                      <div className="quote-icon">❝</div>
                      <div className="quote-content">{comment.quote}</div>
                    </div>
                  )}

                  <div className="comment-text">
                    <div dangerouslySetInnerHTML={{ __html: renderFormattedText(comment.content) }} />
                  </div>

                  <div className="comment-actions">
                    <button 
                      onClick={() => handleReplyClick(comment.content)}
                      className="btn-reply-small"
                    >
                      Reply
                    </button>
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
          setReplyQuote('');
        }}
        onSubmit={handleReplySubmit}
        isReplyMode={true}
        quoteText={replyQuote}
        parentTitle={post.title}
      />
    </div>
  );
};

export default PostDetail;