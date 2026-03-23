export default function Header({ searchQuery, setSearchQuery, onOpenModal }) {
  return (
    <header className="forum-header">
      {/* 搜索框和标题组合在左侧 */}
      <div className="header-left">
        <h2>Community</h2>
        <input 
          type="text" 
          className="search-bar"
          placeholder="Search Title, ID, or Author..." 
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
        />
      </div>
      
      <button className="post-btn" onClick={onOpenModal}>+ Publish Post</button>
    </header>
  );
}