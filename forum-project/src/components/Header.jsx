export default function Header({ searchQuery, setSearchQuery, onOpenModal, currentUser }) {
  const userName = currentUser?.username || 'LOGIN';
  const userInitial = userName.slice(0, 2).toUpperCase();

  return (
    <>
      <header className="forum-topnav">
        <a className="forum-topnav__logo" href="http://127.0.0.1:8001/home.html">Acad<span>Beat</span></a>
        <nav className="forum-topnav__menu" aria-label="Main">
          <a className="forum-topnav__item" href="http://127.0.0.1:8001/home.html?module=Lexis">Vocabulary</a>
          <a className="forum-topnav__item" href="http://127.0.0.1:8001/home.html?module=Insight">Academic</a>
          <a className="forum-topnav__item is-active" href="http://127.0.0.1:8001/home.html?module=Dialogue">Forum</a>
          <a className="forum-topnav__item" href="http://127.0.0.1:8001/home.html?module=Method">Technology</a>
        </nav>
        <div className="forum-topnav__actions">
          <button className="post-btn" onClick={onOpenModal}>+ Publish Post</button>
          <div className="forum-topnav__userGroup">
            <a className="forum-topnav__user" href="http://127.0.0.1:8001/owner.html">
              <span className="forum-topnav__userLabel">{userName}</span>
              <span className="forum-topnav__avatar" aria-hidden="true">{userInitial}</span>
            </a>
            {currentUser && (
              <a className="forum-topnav__logout" href="http://127.0.0.1:8001/vocba_prac/logout.php">
                Log out
              </a>
            )}
          </div>
        </div>
      </header>

      <header className="forum-header">
        <div className="forum-hero">
          <div className="forum-hero__eyebrow">Section III — Collective Intellect</div>
          <h1 className="forum-hero__title">Forum</h1>
          <p className="forum-hero__sub">Discuss ideas, ask for help, and archive useful threads in one place.</p>
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
