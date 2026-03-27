import AppTopNav from './AppTopNav'

export default function Header({ searchQuery, setSearchQuery, onOpenModal, currentUser }) {
  return (
    <>
      <AppTopNav currentUser={currentUser} activeMode="forum" />

      <header className="forum-header">
        <div className="forum-hero">
          <div className="forum-hero__eyebrow">Section III — Collective Intellect</div>
          <h1 className="forum-hero__title">Forum</h1>
          <p className="forum-hero__sub">Discuss ideas, ask for help, and archive useful threads in one place.</p>
          <div className="forum-hero__actions">
            <button className="post-btn post-btn--hero" onClick={onOpenModal}>Publish Post</button>
          </div>
        </div>
        <div className="forum-header__searchWrap">
          <input 
            type="text" 
            className="search-bar"
            placeholder="Search title, author, content" 
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
          />
        </div>
      </header>
    </>
  );
}
