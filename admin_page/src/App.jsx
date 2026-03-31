import { useEffect, useState } from 'react'
import './App.css'
import Navbar from './components/Navbar'
import ForumPostReview from './components/ForumPostReview'
import AnnouncementManager from './components/AnnouncementManager'
import { adminFetch, redirectToHome, redirectToLogin } from './api'

function App() {
  const [currentPage, setCurrentPage] = useState('forum')
  const [currentUser, setCurrentUser] = useState(null)
  const [bootStatus, setBootStatus] = useState('loading')
  const [bootMessage, setBootMessage] = useState('')

  useEffect(() => {
    let active = true

    async function bootstrap() {
      try {
        const data = await adminFetch('/Auth/backend/api/me.php')
        if (!active) {
          return
        }

        const user = data.user || null
        if (!user) {
          setBootStatus('error')
          setBootMessage('Session expired. Returning to login...')
          window.setTimeout(redirectToLogin, 600)
          return
        }

        if (user.role !== 'admin') {
          setBootStatus('error')
          setBootMessage('Admin access required. Returning to home...')
          window.setTimeout(redirectToHome, 800)
          return
        }

        setCurrentUser(user)
        setBootStatus('ready')
      } catch (error) {
        if (!active) {
          return
        }

        if (error.status === 401) {
          setBootStatus('error')
          setBootMessage('Login required. Returning to login...')
          window.setTimeout(redirectToLogin, 600)
          return
        }

        if (error.status === 403) {
          setBootStatus('error')
          setBootMessage('Admin access required. Returning to home...')
          window.setTimeout(redirectToHome, 800)
          return
        }

        setBootStatus('error')
        setBootMessage(error.message || 'Failed to load admin session.')
      }
    }

    bootstrap()

    return () => {
      active = false
    }
  }, [])

  async function handleLogout() {
    try {
      await adminFetch('/Auth/backend/api/logout.php', {
        method: 'POST'
      })
    } finally {
      redirectToHome()
    }
  }

  function renderContent() {
    if (bootStatus === 'loading') {
      return (
        <div style={styles.centerPanel}>
          <h1 style={styles.welcomeTitle}>Loading admin session</h1>
          <p style={styles.welcomeText}>Checking permissions and preparing moderation tools.</p>
        </div>
      )
    }

    if (bootStatus === 'error') {
      return (
        <div style={styles.centerPanel}>
          <h1 style={styles.welcomeTitle}>Access unavailable</h1>
          <p style={styles.welcomeText}>{bootMessage}</p>
        </div>
      )
    }

    if (currentPage === 'forum') {
      return <ForumPostReview />
    }

    if (currentPage === 'announcements') {
      return <AnnouncementManager />
    }

    return <ForumPostReview />
  }

  return (
    <>
      <Navbar
        currentPage={currentPage}
        currentUser={currentUser}
        isReady={bootStatus === 'ready'}
        onLogout={handleLogout}
        setCurrentPage={setCurrentPage}
      />
      {renderContent()}
    </>
  )
}

const styles = {
  homeContainer: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 'calc(100vh - 73px)',
    padding: '48px 24px',
    textAlign: 'center'
  },
  centerPanel: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 'calc(100vh - 73px)',
    padding: '48px 24px',
    textAlign: 'center'
  },
  welcomeTitle: {
    fontSize: '48px',
    fontWeight: '600',
    color: 'var(--text-h)',
    margin: '0 0 16px 0',
    fontFamily: 'var(--heading)'
  },
  welcomeText: {
    fontSize: '18px',
    color: 'var(--text)',
    margin: '0',
    fontFamily: 'var(--sans)'
  }
}

export default App
