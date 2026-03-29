import AppTopNav from './AppTopNav'

export default function Header({ searchQuery, setSearchQuery, onOpenModal, currentUser, onShowFavorites, onShowMyPosts, viewMode, onBackToForum }) {
  return (
    <>
      <AppTopNav currentUser={currentUser} activeMode="forum" />

      <header className="forum-header">
        <div className="forum-hero">
          <div className="forum-hero__eyebrow">Section III — Collective Intellect</div>
          <h1 className="forum-hero__title">{viewMode === 'favorites' ? 'My Favorites' : 'Forum'}</h1>
          <p className="forum-hero__sub">
            {viewMode === 'favorites' 
              ? 'Your saved, liked, and posted content' 
              : 'Discuss ideas, ask for help, and archive useful threads in one place.'}
          </p>
          <div className="forum-hero__actions">
            {viewMode === 'favorites' ? (
              <button className="post-btn post-btn--hero" onClick={onBackToForum}>Back to Forum</button>
            ) : (
              <>
                <button className="post-btn post-btn--hero" onClick={onOpenModal}>Publish Post</button>
                <button className="post-btn post-btn--secondary" onClick={onShowFavorites}>My Favorites</button>
                <button className="post-btn post-btn--secondary" onClick={onShowMyPosts}>My Posts</button>
              </>
            )}
          </div>
        </div>
        {viewMode !== 'favorites' && (
          <div className="forum-header__searchWrap">
            <input 
              type="text" 
              className="search-bar"
              placeholder="Search title, author, content" 
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>
        )}
      </header>
    </>
  );
}
