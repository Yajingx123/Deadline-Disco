export const getFilteredPosts = (posts, searchQuery, selectedTags, minViews, maxViews) => {
  let result = [...posts];

  // 1. 标题、ID、发贴用户搜索 (大小写不敏感)
  if (searchQuery) {
    const query = searchQuery.toLowerCase().trim();
    result = result.filter(post => 
      post.id.toLowerCase() === query || 
      post.title.toLowerCase().includes(query) ||
      post.author.toLowerCase().includes(query)
    );
  }

  // 2. 检查浏览量输入是否成立
  const isRangeValid = !(minViews !== '' && maxViews !== '' && Number(minViews) > Number(maxViews));

  // 仅在范围输入合法时，才执行浏览量过滤
  if (isRangeValid) {
    if (minViews !== '' && minViews !== null) {
      result = result.filter(post => post.views >= Number(minViews));
    }
    if (maxViews !== '' && maxViews !== null) {
      result = result.filter(post => post.views <= Number(maxViews));
    }
  }

  // 3. 标签与媒体类型过滤
  if (selectedTags.length > 0) {
    result = result.map(post => {
      const matchCount = post.tags.filter(tag => selectedTags.includes(tag)).length;
      return { ...post, matchCount };
    })
    .filter(post => post.matchCount > 0)
    .sort((a, b) => b.matchCount - a.matchCount);
  }

  return result;
};