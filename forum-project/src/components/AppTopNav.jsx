export default function AppTopNav({ currentUser, activeMode = 'forum' }) {
  const userName = currentUser?.username || 'LOGIN'
  const userInitial = userName.slice(0, 2).toUpperCase()

  return (
    <header className="forum-topnav">
      <a className="forum-topnav__logo" href="http://127.0.0.1:8001/home.html">Acad<span>Beat</span></a>
      <nav className="forum-topnav__menu" aria-label="Main">
        <a className="forum-topnav__item" href="http://127.0.0.1:8001/home.html?module=Insight">Academic</a>
        <a className={`forum-topnav__item ${activeMode === 'forum' || activeMode === 'personal' || activeMode === 'chooser' ? 'is-active' : ''}`} href="http://127.0.0.1:8001/home.html?module=Dialogue">Forum</a>
        <a className="forum-topnav__item" href="http://127.0.0.1:8001/home.html?module=Method">Technology</a>
        <a className="forum-topnav__item" href="http://127.0.0.1:8001/Studio/studio.html">Studio</a>
      </nav>
      <div className="forum-topnav__actions">
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
  )
}
