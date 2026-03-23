// src/pages/ForumHome.jsx

import { useState, useEffect, useMemo } from 'react';
import Header from '../components/Header';
import SidebarFilter from '../components/SidebarFilter';
import PostList from '../components/PostList';
// 👇 1. 新增：引入详情页组件 (请确保路径正确，如果 PostDetail 在 pages 文件夹则改为 './PostDetail')
import PostDetail from './PostDetail'; 

// 👇 2. 引入新的 PostModal
import PostModal from '../components/PostModal'; 

// 引入数据逻辑
import { fetchPosts, createPost, incrementPostViews } from '../data/mockData'; 
import { getFilteredPosts } from '../utils/filterLogic';

export default function ForumHome() {
  const [posts, setPosts] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedTags, setSelectedTags] = useState([]);
  
  // 模态框控制状态
  const [isModalOpen, setIsModalOpen] = useState(false);
  
  // 回复模式状态
  const [isReplyMode, setIsReplyMode] = useState(false);
  const [quoteText, setQuoteText] = useState('');
  const [parentTitle, setParentTitle] = useState('');

  // 👇 3. 新增：详情页状态 (null 表示显示列表，有值表示显示详情)
  const [selectedPost, setSelectedPost] = useState(null);

  // 筛选状态
  const [minViews, setMinViews] = useState('');
  const [maxViews, setMaxViews] = useState('');

  useEffect(() => {
    setPosts(fetchPosts());
  }, []);

  const filteredPosts = useMemo(() => {
    return getFilteredPosts(posts, searchQuery, selectedTags, minViews, maxViews);
  }, [posts, searchQuery, selectedTags, minViews, maxViews]);

  // src/pages/ForumHome.jsx

  const handleCreatePost = (newData) => {
    // newData 包含: { title, content, tags, author, time }
    
    // 👇 修改这里：把 newData.content 作为第 5 个参数传进去
    const adaptedPost = createPost(
      newData.title, 
      newData.tags || [], 
      newData.author, 
      'text',          // mediaType
      newData.content  // 👈 新增：传入具体内容
    );

    const finalPost = {
      ...adaptedPost,
      // 确保 content 被正确覆盖（虽然 createPost 里已经加了，但这层保险更好）
      content: newData.content, 
      views: 0,
      comments: []
    };

    setPosts([finalPost, ...posts]); 
    setIsModalOpen(false);
  };

  const handleReplyClick = (post) => {
    setIsReplyMode(true);
    setParentTitle(post.title);
    setQuoteText(post.content.substring(0, 150));
    setIsModalOpen(true);
  };

  const handleOpenPostModal = () => {
    setIsReplyMode(false);
    setQuoteText('');
    setParentTitle('');
    setIsModalOpen(true);
  };

  // 👇 4. 修改：处理点击事件，不再只是 +1，而是进入详情页
  const handlePostClick = (postId) => {
    // 1. 找到完整的帖子数据
    const post = posts.find(p => p.id === postId);
    
    if (post) {
      // 2. 更新浏览量 (本地状态 + localStorage)
      const updatedPosts = posts.map(p => 
        p.id === postId ? { ...p, views: p.views + 1 } : p
      );
      setPosts(updatedPosts);
      incrementPostViews(postId);

      // 3. 【关键】设置选中帖子，触发详情页渲染
      // 注意：我们需要把更新后的浏览量也传进去，所以用 updatedPosts 里找到的最新数据
      const updatedPost = updatedPosts.find(p => p.id === postId);
      setSelectedPost(updatedPost);
    }
  };

  // 👇 5. 新增：处理从详情页返回
  const handleBackToList = () => {
    setSelectedPost(null);
    // 可选：返回时重新刷新一下列表，确保浏览量同步（虽然上面已经同步了）
    setPosts(fetchPosts());
  };

  // 👇 6. 新增：处理在详情页提交评论
  const handleAddComment = (newComment) => {
    if (!selectedPost) return;

    const commentData = {
      id: Date.now(),
      author: 'Current User', 
      avatar: 'U',
      time: new Date().toLocaleString(),
      content: newComment.content,
      quote: newComment.quote || ''
    };

    // 更新本地 posts 状态
    const updatedPosts = posts.map(p => {
      if (p.id === selectedPost.id) {
        const updatedPost = {
          ...p,
          comments: [...(p.comments || []), commentData]
        };
        // 同时更新 selectedPost，让详情页立即看到新评论
        setSelectedPost(updatedPost);
        return updatedPost;
      }
      return p;
    });

    setPosts(updatedPosts);
    
    // 保存到 localStorage
    localStorage.setItem('react_forum_posts_v2', JSON.stringify(updatedPosts));
  };

  // 👇 7. 修改：根据 selectedPost 决定渲染列表还是详情
  if (selectedPost) {
    return (
      <div className="forum-container">
        {/* 详情页模式下，通常不需要显示 SidebarFilter 和 Header 的搜索框，或者可以简化 Header */}
        {/* 这里直接渲染详情页组件 */}
        <PostDetail 
          post={selectedPost} 
          onBack={handleBackToList}
          onAddComment={handleAddComment} // 传递评论处理函数
        />
      </div>
    );
  }

  // 默认渲染列表模式
  return (
    <div className="forum-container">
      <Header 
        searchQuery={searchQuery} 
        setSearchQuery={setSearchQuery} 
        onOpenModal={handleOpenPostModal} 
      />
      
      <div className="forum-layout">
        <SidebarFilter 
          selectedTags={selectedTags} 
          setSelectedTags={setSelectedTags}
          minViews={minViews} 
          setMinViews={setMinViews}
          maxViews={maxViews} 
          setMaxViews={setMaxViews}
        />
        
        <PostList 
          posts={filteredPosts} 
          onPostClick={handlePostClick} 
          onReply={handleReplyClick} 
        />
      </div>

      {isModalOpen && (
        <PostModal 
          isOpen={isModalOpen}
          onClose={() => setIsModalOpen(false)}
          onSubmit={handleCreatePost}
          isReplyMode={isReplyMode}
          quoteText={quoteText}
          parentTitle={parentTitle}
        />
      )}
    </div>
  );
}