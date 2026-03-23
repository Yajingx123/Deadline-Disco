import { ALL_TAGS } from '../data/mockData';

export default function SidebarFilter({ selectedTags, setSelectedTags, minViews, setMinViews, maxViews, setMaxViews }) {
  const handleTagToggle = (tag) => {
    if (selectedTags.includes(tag)) {
      setSelectedTags(selectedTags.filter(t => t !== tag));
    } else {
      setSelectedTags([...selectedTags, tag]);
    }
  };

  // 检查输入是否成立：最小浏览量不能大于最大浏览量
  const isInvalidRange = minViews !== '' && maxViews !== '' && Number(minViews) > Number(maxViews);

  return (
    <aside className="sidebar">
      <h3>Filter by Tags</h3>
      <div className="filter-options">
        {ALL_TAGS.map(tag => (
          <label key={tag} className="tag-label">
            <input 
              type="checkbox" 
              checked={selectedTags.includes(tag)}
              onChange={() => handleTagToggle(tag)}
              style={{ marginRight: '8px' }}
            />
            {tag}
          </label>
        ))}
      </div>
      
      {/* 使用 CSS 类调大间距 */}
      <hr className="filter-section-divider" />
      
      <h3>Views Range</h3>
      <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
        <input 
          type="number" 
          placeholder="Min Views (e.g. 0)" 
          value={minViews} 
          onChange={(e) => setMinViews(e.target.value)} 
        />
        <input 
          type="number" 
          placeholder="Max Views (e.g. 1000)" 
          value={maxViews} 
          onChange={(e) => setMaxViews(e.target.value)}
        />
        {/* 如果输入不成立，显示警告 */}
        {isInvalidRange && (
          <span className="error-text">⚠️ Min views cannot exceed Max views.</span>
        )}
      </div>
    </aside>
  );
}