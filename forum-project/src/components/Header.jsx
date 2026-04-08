export default function Header({ searchQuery, setSearchQuery, onOpenModal, onShowFavorites, viewMode, onBackToForum, favoritesTab, onOpenGuide }) {
  return (
    <header className="forum-header">
      <div className="forum-hero">
        <div className="forum-hero__eyebrow">Section III — Collective Intellect</div>
        <h1 className="forum-hero__title">{viewMode === 'favorites' ? (favoritesTab === 'favorites' ? 'My Favorites' : favoritesTab === 'likes' ? 'My Likes' : 'My Posts') : 'Forum'}</h1>
        <p className="forum-hero__sub">
          {viewMode === 'favorites'
            ? (favoritesTab === 'favorites' ? 'Your saved posts' : favoritesTab === 'likes' ? 'Posts you liked' : 'Your posted content')
            : 'Discuss ideas, ask for help, and archive useful threads in one place.'}
        </p>
        <div className="forum-hero__actions">
          {viewMode === 'favorites' ? (
            <>
              <button className="post-btn post-btn--hero" onClick={onBackToForum}>Back to Forum</button>
            </>
          ) : (
            <>
              <button className="post-btn post-btn--hero" onClick={onOpenModal} data-guide-forum="publish-btn">Publish Post</button>
              <button className="post-btn post-btn--secondary post-btn--personal" onClick={onShowFavorites} data-guide-forum="my-btn">Personal</button>
              <button className="post-btn post-btn--guide-dot" onClick={onOpenGuide} data-guide-forum="guide-btn" aria-label="Open forum guide" title="Guide">?</button>
            </>
          )}
        </div>
      </div>
      {viewMode !== 'favorites' && (
        <div className="forum-header__searchWrap">
          <input
            type="text"
            className="search-bar"
            data-guide-forum="search"
            placeholder="Search title, author, content"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
          />
        </div>
      )}
    </header>
  );
}
