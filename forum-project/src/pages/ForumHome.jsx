// src/pages/ForumHome.jsx
import { useState, useEffect } from 'react';
import Header from '../components/Header';
import SidebarFilter from '../components/SidebarFilter';
import PostList from '../components/PostList';
import PostDetail from './PostDetail'; 
import PostModal from '../components/PostModal'; 
import { getSummary } from '../utils/formatText';
import { fetchLabels, fetchPosts, createPost, fetchPostDetail, incrementPostViews, createComment, deletePost, deleteComment } from '../api/forumApi';

const FORUM_PREFILL_WINDOW_NAME_KEY = '__acadbeat_forum_prefill__';

function normalizeComment(comment = {}) {
  return {
    ...comment,
    id: Number(comment.id ?? comment.commentId ?? comment.comment_id ?? 0),
    authorUserId: Number(comment.authorUserId ?? comment.author_user_id ?? 0),
    content: comment.content ?? comment.content_text ?? '',
    time: comment.time ?? '',
  };
}

export default function ForumHome() {
  const [posts, setPosts] = useState([]);
  const [labels, setLabels] = useState([]);
  const [currentUser, setCurrentUser] = useState(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedTags, setSelectedTags] = useState([]);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedPost, setSelectedPost] = useState(null);
  const [sortOrder, setSortOrder] = useState('latest_reply');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [prefillDraft, setPrefillDraft] = useState(null);

  const loadForumData = async () => {
    setLoading(true);
    setError('');
    try {
      const [labelData, postData] = await Promise.all([
        fetchLabels(),
        fetchPosts({
          q: searchQuery,
          labels: selectedTags,
          sort: sortOrder,
        }),
      ]);
      setLabels(labelData.labels || []);
      setCurrentUser(labelData.currentUser || null);
      setPosts((postData.posts || []).map((post) => ({
        ...post,
        summary: getSummary(post.content, 120),
      })));
    } catch (err) {
      if ((err.message || '').toLowerCase().includes('login required')) {
        window.location.href = 'http://127.0.0.1:8001/home.html?login=1';
        return;
      }
      setError(err.message || 'Failed to load forum.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadForumData();
  }, [searchQuery, selectedTags, sortOrder]);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const shouldCompose = params.get('compose') === '1';
    let prefillTitle = params.get('prefillTitle') || '';
    let prefillContent = params.get('prefillContent') || '';

    if (!prefillTitle && !prefillContent) {
      try {
        const bridgePayload = JSON.parse(window.name || '{}');
        const storedDraft = bridgePayload?.[FORUM_PREFILL_WINDOW_NAME_KEY] || null;
        prefillTitle = storedDraft?.title || '';
        prefillContent = storedDraft?.content || '';
      } catch (_err) {
        prefillTitle = '';
        prefillContent = '';
      }
    }

    if (!shouldCompose || (!prefillTitle && !prefillContent)) {
      return;
    }

    setPrefillDraft({
      title: prefillTitle,
      content: prefillContent,
    });
    setIsModalOpen(true);
    window.name = '';

    const cleanUrl = `${window.location.pathname}${window.location.hash || ''}`;
    window.history.replaceState({}, document.title, cleanUrl);
  }, []);

  const handleCreatePost = async (newData) => {
    const data = await createPost({
      title: newData.title,
      content: newData.content,
      labels: newData.labels || [],
    });
    setPosts(prev => [{
      ...data.post,
      summary: getSummary(data.post.content, 120),
    }, ...prev]);
    setIsModalOpen(false);
  };

  const handleOpenPostModal = () => {
    setPrefillDraft(null);
    setIsModalOpen(true);
  };

  const handlePostClick = async (postId) => {
    await incrementPostViews(postId);
    const data = await fetchPostDetail(postId);
    setSelectedPost(data.post);
    setPosts(prev => prev.map(post => (
      post.id === postId ? { ...post, views: post.views + 1 } : post
    )));
  };

  const handleBackToList = () => {
    setSelectedPost(null);
  };

  const handleAddComment = async (newComment) => {
    const data = await createComment(newComment);
    const createdComment = normalizeComment(data.comment || {});
    setPosts(prev => prev.map((post) => (
      post.id === newComment.postId
        ? { ...post, commentCount: (post.commentCount || 0) + 1 }
        : post
    )));
    if (selectedPost?.id === newComment.postId) {
      const detail = await fetchPostDetail(newComment.postId);
      setSelectedPost(detail.post);
    }
    return createdComment;
  };

  const handleDeletePost = async (postId) => {
    await deletePost(postId);
    setPosts((prev) => prev.filter((post) => post.id !== postId));
    setSelectedPost(null);
  };

  const handleDeleteComment = async (commentId) => {
    const data = await deleteComment(commentId);
    const deletedIds = Array.isArray(data.deletedCommentIds) ? data.deletedCommentIds : [commentId];
    const removedCount = deletedIds.length;
    setPosts(prev => prev.map((post) => (
      post.id === selectedPost?.id
        ? { ...post, commentCount: Math.max((post.commentCount || 0) - removedCount, 0) }
        : post
    )));
    setSelectedPost((prev) => prev ? {
      ...prev,
      comments: (prev.comments || []).filter((comment) => !deletedIds.includes(comment.id)),
      commentCount: Math.max((prev.commentCount || 0) - removedCount, 0),
    } : prev);
    return data;
  };

  if (selectedPost) {
    return (
      <div className="forum-container">
        <PostDetail 
          post={selectedPost} 
          onBack={handleBackToList}
          onAddComment={handleAddComment}
          onDeletePost={handleDeletePost}
          onDeleteComment={handleDeleteComment}
          labelOptions={labels}
          currentUser={currentUser}
        />
      </div>
    );
  }

  return (
    <div className="forum-container">
      <Header 
        searchQuery={searchQuery} 
        setSearchQuery={setSearchQuery} 
        onOpenModal={handleOpenPostModal}
        currentUser={currentUser}
      />
      
      <div className="forum-layout">
        <SidebarFilter 
          labels={labels}
          selectedTags={selectedTags} 
          setSelectedTags={setSelectedTags}
          sortOrder={sortOrder}
          setSortOrder={setSortOrder}
        />
        
        {loading ? (
          <div className="main-content"><div className="forum-empty">Loading forum…</div></div>
        ) : error ? (
          <div className="main-content"><div className="forum-empty forum-empty--error">{error}</div></div>
        ) : (
          <PostList posts={posts} onPostClick={handlePostClick} />
        )}
      </div>

      {isModalOpen && (
        <PostModal 
          isOpen={isModalOpen}
          onClose={() => {
            setIsModalOpen(false);
            setPrefillDraft(null);
          }}
          onSubmit={handleCreatePost}
          isReplyMode={false}
          quoteText=""
          parentTitle=""
          labelOptions={labels}
          currentUser={currentUser}
          prefillDraft={prefillDraft}
        />
      )}
    </div>
  );
}
