// src/pages/ForumHome.jsx
import { useState, useEffect } from 'react';
import Header from '../components/Header';
import SidebarFilter from '../components/SidebarFilter';
import PostList from '../components/PostList';
import PostDetail from './PostDetail'; 
import PostModal from '../components/PostModal'; 
import { getSummary } from '../utils/formatText';
import { connectRealtime, fetchLabels, fetchPosts, createPost, fetchPostDetail, incrementPostViews, createComment, deletePost, deleteComment, fetchUserFavorites, fetchUserLikes, fetchUserPosts } from '../api/forumApi';

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
  const [viewMode, setViewMode] = useState('list');
  const [favorites, setFavorites] = useState([]);
  const [likes, setLikes] = useState([]);
  const [userPosts, setUserPosts] = useState([]);
  const [favoritesTab, setFavoritesTab] = useState('favorites');

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
    return connectRealtime(async (event) => {
      const eventType = String(event?.type || '');
      const eventPostId = Number(event?.data?.postId || 0);

      if (!eventType.startsWith('forum.')) {
        return;
      }

      if (selectedPost?.id) {
        if (eventType === 'forum.post.deleted' && eventPostId === Number(selectedPost.id)) {
          setSelectedPost(null);
          await loadForumData();
          return;
        }

        if (
          eventPostId === Number(selectedPost.id)
          && (eventType === 'forum.comment.created' || eventType === 'forum.comment.deleted' || eventType === 'forum.post.created')
        ) {
          try {
            const detail = await fetchPostDetail(selectedPost.id);
            setSelectedPost(detail.post);
          } catch (_err) {
            // Ignore transient realtime detail refresh failures.
          }
        }
      }

      if (
        eventType === 'forum.post.created'
        || eventType === 'forum.post.deleted'
        || eventType === 'forum.comment.created'
        || eventType === 'forum.comment.deleted'
      ) {
        await loadForumData();
      }
    });
  }, [selectedPost, searchQuery, selectedTags, sortOrder]);

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

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const postId = Number(params.get('postId') || 0)
    if (!postId) {
      return
    }

    fetchPostDetail(postId)
      .then((data) => {
        if (data?.post) {
          setSelectedPost(data.post)
        }
      })
      .catch(() => {})
  }, [])

  const handleCreatePost = async (newData) => {
    const data = await createPost({
      title: newData.title,
      content: newData.content,
      labels: newData.labels || [],
    });
    const newPost = {
      ...data.post,
      summary: getSummary(data.post.content, 120),
      status: data.post.status || 'Under review',
    };
    setPosts(prev => [newPost, ...prev]);
    setUserPosts(prev => [newPost, ...prev]);
    setIsModalOpen(false);
    await loadForumData();
  };

  const handleOpenPostModal = () => {
    setPrefillDraft(null);
    setIsModalOpen(true);
  };

  const handlePostClick = async (postId) => {
    await incrementPostViews(postId);
    const data = await fetchPostDetail(postId);
    setSelectedPost(data.post);
    const params = new URLSearchParams(window.location.search)
    params.set('view', 'forum')
    params.set('postId', String(postId))
    window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`)
    setPosts(prev => prev.map(post => (
      post.id === postId ? { ...post, views: post.views + 1 } : post
    )));
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
    setUserPosts((prev) => prev.filter((post) => post.id !== postId));
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

  const handleShowFavorites = async () => {
    setLoading(true);
    setError('');
    try {
      const [favoritesData, likesData, userPostsData] = await Promise.all([
        fetchUserFavorites(),
        fetchUserLikes(),
        fetchUserPosts(),
      ]);
      setFavorites((favoritesData.posts || []).map((post) => ({
        ...post,
        summary: getSummary(post.content, 120),
      })));
      setLikes((likesData.posts || []).map((post) => ({
        ...post,
        summary: getSummary(post.content, 120),
      })));
      setUserPosts((userPostsData.posts || []).map((post) => ({
        ...post,
        summary: getSummary(post.content, 120),
      })));
      setViewMode('favorites');
    } catch (err) {
      if ((err.message || '').toLowerCase().includes('login required')) {
        window.location.href = 'http://127.0.0.1:8001/home.html?login=1';
        return;
      }
      setError(err.message || 'Failed to load favorites.');
    } finally {
      setLoading(false);
    }
  };

  const handleShowMyPosts = async () => {
    setLoading(true);
    setError('');
    try {
      const [favoritesData, likesData, userPostsData] = await Promise.all([
        fetchUserFavorites(),
        fetchUserLikes(),
        fetchUserPosts(),
      ]);
      setFavorites((favoritesData.posts || []).map((post) => ({
        ...post,
        summary: getSummary(post.content, 120),
      })));
      setLikes((likesData.posts || []).map((post) => ({
        ...post,
        summary: getSummary(post.content, 120),
      })));
      setUserPosts((userPostsData.posts || []).map((post) => ({
        ...post,
        summary: getSummary(post.content, 120),
      })));
      setFavoritesTab('posts');
      setViewMode('favorites');
    } catch (err) {
      if ((err.message || '').toLowerCase().includes('login required')) {
        window.location.href = 'http://127.0.0.1:8001/home.html?login=1';
        return;
      }
      setError(err.message || 'Failed to load posts.');
    } finally {
      setLoading(false);
    }
  };

  const handleBackToList = () => {
    setSelectedPost(null);
    const params = new URLSearchParams(window.location.search)
    params.delete('postId')
    const query = params.toString()
    window.history.replaceState({}, '', `${window.location.pathname}${query ? `?${query}` : ''}`)
  };

  const handleBackToForum = () => {
    setViewMode('list');
    loadForumData();
  };

  const handleLikeChange = (postId, liked, likeCount) => {
    setPosts(prev => prev.map(post => 
      post.id === postId ? { ...post, likeCount, isLiked: liked } : post
    ));
    setFavorites(prev => prev.map(post => 
      post.id === postId ? { ...post, likeCount, isLiked: liked } : post
    ));
    setLikes(prev => prev.map(post => 
      post.id === postId ? { ...post, likeCount, isLiked: liked } : post
    ));
    setUserPosts(prev => prev.map(post => 
      post.id === postId ? { ...post, likeCount, isLiked: liked } : post
    ));
    if (selectedPost?.id === postId) {
      setSelectedPost(prev => prev ? { ...prev, likeCount, isLiked: liked } : null);
    }
  };

  const handleFavoriteChange = (postId, favorited, favoriteCount) => {
    setPosts(prev => prev.map(post => 
      post.id === postId ? { ...post, favoriteCount, isFavorited: favorited } : post
    ));
    setFavorites(prev => prev.map(post => 
      post.id === postId ? { ...post, favoriteCount, isFavorited: favorited } : post
    ));
    setLikes(prev => prev.map(post => 
      post.id === postId ? { ...post, favoriteCount, isFavorited: favorited } : post
    ));
    setUserPosts(prev => prev.map(post => 
      post.id === postId ? { ...post, favoriteCount, isFavorited: favorited } : post
    ));
    if (selectedPost?.id === postId) {
      setSelectedPost(prev => prev ? { ...prev, favoriteCount, isFavorited: favorited } : null);
    }
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
          onLikeChange={handleLikeChange}
          onFavoriteChange={handleFavoriteChange}
        />
      </div>
    );
  }

  if (viewMode === 'favorites') {
    return (
      <div className="forum-container">
        <Header 
          searchQuery={searchQuery} 
          setSearchQuery={setSearchQuery} 
          onOpenModal={handleOpenPostModal}
          currentUser={currentUser}
          onShowFavorites={handleShowFavorites}
          onShowMyPosts={handleShowMyPosts}
          viewMode={viewMode}
          onBackToForum={handleBackToForum}
        />
        
        <div className="forum-layout favorites-layout">
          <div className="favorites-container">
            <div className="favorites-header">
              <button 
                className={`favorites-tab ${favoritesTab === 'favorites' ? 'favorites-tab--active' : ''}`}
                onClick={() => setFavoritesTab('favorites')}
              >
                ⭐ Favorites ({favorites.length})
              </button>
              <button 
                className={`favorites-tab ${favoritesTab === 'likes' ? 'favorites-tab--active' : ''}`}
                onClick={() => setFavoritesTab('likes')}
              >
                ❤️ Liked ({likes.length})
              </button>
              <button 
                className={`favorites-tab ${favoritesTab === 'posts' ? 'favorites-tab--active' : ''}`}
                onClick={() => setFavoritesTab('posts')}
              >
                📝 My Posts ({userPosts.length})
              </button>
            </div>
            
            {loading ? (
              <div className="forum-empty">Loading…</div>
            ) : error ? (
              <div className="forum-empty forum-empty--error">{error}</div>
            ) : favoritesTab === 'favorites' && favorites.length === 0 ? (
              <div className="forum-empty">
                <div className="empty-icon">⭐</div>
                <p>No favorites yet. Start by favoriting posts you like!</p>
                <button onClick={handleBackToForum} className="btn-back">Back to Forum</button>
              </div>
            ) : favoritesTab === 'likes' && likes.length === 0 ? (
              <div className="forum-empty">
                <div className="empty-icon">❤️</div>
                <p>No liked posts yet. Start by liking posts you enjoy!</p>
                <button onClick={handleBackToForum} className="btn-back">Back to Forum</button>
              </div>
            ) : favoritesTab === 'posts' && userPosts.length === 0 ? (
              <div className="forum-empty">
                <div className="empty-icon">📝</div>
                <p>You haven't posted anything yet. Start by creating your first post!</p>
                <button onClick={handleBackToForum} className="btn-back">Back to Forum</button>
              </div>
            ) : (
              <PostList 
                posts={favoritesTab === 'favorites' ? favorites : favoritesTab === 'likes' ? likes : userPosts} 
                onPostClick={handlePostClick} 
                showStatus={favoritesTab === 'posts'}
                onDelete={favoritesTab === 'posts' ? handleDeletePost : undefined}
              />
            )}
          </div>
        </div>
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
        onShowFavorites={handleShowFavorites}
        onShowMyPosts={handleShowMyPosts}
        viewMode={viewMode}
        onBackToForum={handleBackToForum}
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
