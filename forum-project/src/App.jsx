import { useEffect, useMemo, useState } from 'react'
import ForumHome from './pages/ForumHome'
import PersonalHub from './pages/PersonalHub'

function syncForumSharedNavActive(view) {
  if (typeof window.setAcadBeatNavActive !== 'function') return
  if (view === 'personal') {
    window.setAcadBeatNavActive('messages')
  } else {
    window.setAcadBeatNavActive('forum')
  }
}

function resolveInitialView() {
  const params = new URLSearchParams(window.location.search)
  if (params.get('compose') === '1') {
    return 'forum'
  }
  const view = params.get('view')
  if (view === 'forum' || view === 'personal' || view === 'chooser' || view === 'announcements') {
    return view
  }

  return 'forum'
}

function App() {
  const [view, setView] = useState(resolveInitialView)

  // 与旧版一致：?view=chooser 仍解析为 URL，但界面直接进论坛列表（不显示 Portal 门户页）
  const normalizedView = useMemo(() => {
    if (view === 'forum' || view === 'personal') {
      return view
    }
    return 'forum'
  }, [view])

  const handleNavigate = (nextView) => {
    const params = new URLSearchParams(window.location.search)
    params.delete('compose')
    params.set('view', nextView)
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

  useEffect(() => {
    const run = () => syncForumSharedNavActive(view)
    run()
    window.addEventListener('acadbeat:nav-mounted', run)
    return () => window.removeEventListener('acadbeat:nav-mounted', run)
  }, [view])

  let content = null
  if (normalizedView === 'forum') {
    content = <ForumHome />
  } else if (normalizedView === 'personal') {
    content = <PersonalHub onBackToChooser={() => handleNavigate('forum')} />
  }

  return (
    <div className="app-container">
      {content}
    </div>
  )
}

export default App
