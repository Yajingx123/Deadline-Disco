function Navbar({ currentPage, setCurrentPage, currentUser, isReady, onLogout }) {
  return (
    <nav style={styles.navbar}>
      <div style={styles.container}>
        <div style={styles.logo}>Admin Panel</div>
        <div style={styles.navLinks}>
          <button 
            style={{ ...styles.navLink, ...(currentPage === 'forum' && styles.activeLink) }} 
            onClick={() => setCurrentPage('forum')}
            disabled={!isReady}
          >
            Forum Management
          </button>
          <button 
            style={{ ...styles.navLink, ...(currentPage === 'announcements' && styles.activeLink) }} 
            onClick={() => setCurrentPage('announcements')}
            disabled={!isReady}
          >
            Announcements
          </button>
          <button 
            style={{ ...styles.navLink, ...(currentPage === 'competition' && styles.activeLink) }} 
            onClick={() => setCurrentPage('competition')}
            disabled={!isReady}
          >
            Weekly Competition
          </button>
          <button 
            style={{ ...styles.navLink, ...(currentPage === 'videos' && styles.activeLink) }} 
            onClick={() => setCurrentPage('videos')}
            disabled={!isReady}
          >
            Video Resources
          </button>
        </div>
        <div style={styles.userPanel}>
          <div style={styles.userMeta}>
            <span style={styles.userLabel}>{currentUser?.username || 'Checking session'}</span>
            <span style={styles.userRole}>{currentUser?.role || '...'}</span>
          </div>
          <button style={styles.logoutButton} onClick={onLogout} disabled={!isReady}>
            Logout
          </button>
        </div>
      </div>
    </nav>
  )
}

const styles = {
  navbar: {
    backgroundColor: 'var(--bg)',
    borderBottom: '1px solid var(--border)',
    padding: '16px 0',
    position: 'sticky',
    top: 0,
    zIndex: 100
  },
  container: {
    maxWidth: '1126px',
    margin: '0 auto',
    padding: '0 24px',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: '16px'
  },
  logo: {
    fontSize: '24px',
    fontWeight: '600',
    color: 'var(--text-h)',
    fontFamily: 'var(--heading)'
  },
  navLinks: {
    display: 'flex',
    gap: '8px',
    flex: 1,
    justifyContent: 'center'
  },
  navLink: {
    padding: '8px 16px',
    borderRadius: '999px',
    border: 'none',
    backgroundColor: 'transparent',
    color: 'var(--text)',
    fontSize: '16px',
    cursor: 'pointer',
    transition: 'all 0.3s',
    fontFamily: 'var(--sans)'
  },
  activeLink: {
    backgroundColor: 'var(--accent-bg)',
    color: 'var(--accent)'
  },
  userPanel: {
    display: 'flex',
    alignItems: 'center',
    gap: '12px'
  },
  userMeta: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'flex-end',
    gap: '2px'
  },
  userLabel: {
    fontSize: '0.88rem',
    fontWeight: 700,
    color: 'var(--text-h)'
  },
  userRole: {
    fontSize: '0.72rem',
    textTransform: 'uppercase',
    letterSpacing: '0.12rem',
    color: 'rgba(58, 78, 107, 0.55)'
  },
  logoutButton: {
    padding: '8px 14px',
    borderRadius: '999px',
    border: '1px solid rgba(58, 78, 107, 0.14)',
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    color: 'var(--text)',
    fontSize: '0.85rem',
    fontWeight: 700,
    cursor: 'pointer'
  }
}

export default Navbar
