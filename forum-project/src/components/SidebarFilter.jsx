export default function SidebarFilter({ labels, selectedTags, setSelectedTags, sortOrder, setSortOrder }) {
  const handleTagToggle = (labelName) => {
    if (selectedTags.includes(labelName)) {
      setSelectedTags(selectedTags.filter(t => t !== labelName));
    } else {
      setSelectedTags([...selectedTags, labelName]);
    }
  };

  return (
    <aside className="sidebar">
      <h3>Browse labels</h3>
      <div className="filter-options">
        {labels.map(label => (
          <label key={label.id} className="tag-label">
            <input
              type="checkbox" 
              checked={selectedTags.includes(label.name)}
              onChange={() => handleTagToggle(label.name)}
            />
            {label.name}
          </label>
        ))}
      </div>

      <hr className="filter-section-divider" />

      <h3>Sort threads</h3>
      <div className="sort-select-wrap">
        <select
          className="sort-select"
          value={sortOrder}
          onChange={(e) => setSortOrder(e.target.value)}
        >
          <option value="latest_reply">Latest reply</option>
          <option value="latest_post">Latest post</option>
          <option value="most_viewed">Most viewed</option>
        </select>
      </div>
    </aside>
  );
}
