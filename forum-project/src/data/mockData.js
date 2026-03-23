// src/data/mockData.js

// 1. 补全测试数据的 content 字段，防止报错
const initialPosts = [
  { 
    id: "P1001", 
    title: "React 组件性能优化指南", 
    tags: ["Frontend", "video"], 
    author: "Evan", 
    views: 9999, 
    publishTime: "2026-03-23 09:00", 
    mediaType: "video",
    content: 'This is the first test post on the forum~ \n**Happy discussing!** \n<u>Feel free to post any questions</u>\n',
    comments: [] // 建议加上空的 comments 数组
  },
  { 
    id: "P1002", 
    title: "C++ 指针详解与内存泄露排查", 
    tags: ["C++", "Bug", "image"], 
    author: "Bjarne", 
    views: 10500, 
    publishTime: "2026-03-22 14:30", 
    mediaType: "image",
    content: 'Here is a detailed explanation about C++ pointers...\n\nRemember to check for **memory leaks**!',
    comments: []
  },
  { 
    id: "P1003", 
    title: "数据库 MySQL 索引底层原理", 
    tags: ["Database", "Study", "link"], 
    author: "Alice", 
    views: 450, 
    publishTime: "2026-03-21 11:15", 
    mediaType: "link",
    content: 'MySQL indexing is crucial for performance.\n\n*B-Tree* structure explained here.',
    comments: []
  },
  { 
    id: "P1004", 
    title: "大一第二学期 机械专业选课指南", 
    tags: ["Study", "General", "audio"], 
    author: "Bob", 
    views: 123450, 
    publishTime: "2026-03-20 08:00", 
    mediaType: "audio",
    content: 'Listen to my advice on course selection.\n\n[audio:advice.mp3](base64...)',
    comments: []
  },
  { 
    id: "P1005", 
    title: "HTML文件如何与JS结合？", 
    tags: ["Frontend", "HTML", "video"], 
    author: "Charlie", 
    views: 0, 
    publishTime: "2026-03-23 16:00", 
    mediaType: "video",
    content: 'You can use <script> tags to link JS files.\n\n**Example:**\n<script src="app.js"></script>',
    comments: []
  }
];

export const ALL_TAGS = ["Current news", "Seek help", "Viewpoint topic","image", "video", "audio", "link"];

const STORAGE_KEY = 'react_forum_posts_v2';

export const fetchPosts = () => {
  const stored = localStorage.getItem(STORAGE_KEY);
  if (stored) {
    const parsed = JSON.parse(stored);
    // 兼容性检查：如果旧数据没有 comments 字段，补上
    return parsed.map(p => ({ ...p, comments: p.comments || [] }));
  }
  localStorage.setItem(STORAGE_KEY, JSON.stringify(initialPosts));
  return initialPosts;
};

// 👇 2. 修改 createPost 函数：接收 content 参数并保存
export const createPost = (title, tags, author, mediaType, content = '') => {
  const posts = fetchPosts();
  const now = new Date();
  const timeString = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

  const newPost = {
    id: `P${1000 + posts.length + 1}`,
    title,
    tags: [...tags], 
    author: author || "Anonymous",
    views: 0, 
    publishTime: timeString,
    mediaType: mediaType || "text", // 默认改为 text，除非你明确传了 video/image
    content: content, // 👈 关键：保存传入的内容
    comments: []      // 👈 关键：初始化评论数组
  };

  posts.unshift(newPost);
  localStorage.setItem(STORAGE_KEY, JSON.stringify(posts));
  return newPost;
};

export const incrementPostViews = (postId) => {
  const posts = fetchPosts();
  const updatedPosts = posts.map(post => 
    post.id === postId ? { ...post, views: post.views + 1 } : post
  );
  localStorage.setItem(STORAGE_KEY, JSON.stringify(updatedPosts));
  return updatedPosts;
};