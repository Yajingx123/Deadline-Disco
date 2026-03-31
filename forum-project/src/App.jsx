import { useEffect, useMemo, useState } from 'react'
import ForumHome from './pages/ForumHome'
import PersonalHub from './pages/PersonalHub'
import PortalChoice from './pages/PortalChoice'

function resolveInitialView() {
  const params = new URLSearchParams(window.location.search)
  if (params.get('compose') === '1') {
    return 'forum'
  }
  const view = params.get('view')
  if (view === 'forum' || view === 'personal' || view === 'chooser' || view === 'announcements') {
    return view
  }
  return 'chooser'
}

function App() {
  const [view, setView] = useState(resolveInitialView)

  const normalizedView = useMemo(() => {
    if (view === 'forum' || view === 'personal' || view === 'chooser') {
      return view
    }
    return 'chooser'
  }, [view])

  const handleNavigate = (nextView) => {
    const params = new URLSearchParams(window.location.search)
    params.delete('compose')
    if (nextView === 'chooser') {
      params.set('view', 'chooser')
    } else {
      params.set('view', nextView)
    }
    const nextUrl = `${window.location.pathname}?${params.toString()}${window.location.hash || ''}`
    window.history.pushState({}, '', nextUrl)
    setView(nextView)
  }

  useEffect(() => {
    const handlePopState = () => {
      setView(resolveInitialView())
    }
    window.addEventListener('popstate', handlePopState)
    return () => window.removeEventListener('popstate', handlePopState)
  }, [])

  let content = null
  if (normalizedView === 'forum') {
    content = <ForumHome />
  } else if (normalizedView === 'personal') {
    content = <PersonalHub onBackToChooser={() => handleNavigate('chooser')} />
  } else {
    content = <PortalChoice onChoose={handleNavigate} />
  }

  return (
    <div className="app-container">
      {content}
    </div>
  )
}

export default App
