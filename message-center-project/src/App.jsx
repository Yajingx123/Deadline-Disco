import { useEffect } from 'react'
import MessageCenter from './pages/MessageCenter'

export default function App() {
  useEffect(() => {
    const run = () => {
      if (typeof window.setAcadBeatNavActive === 'function') {
        window.setAcadBeatNavActive('messages')
      }
    }
    run()
    window.addEventListener('acadbeat:nav-mounted', run)
    return () => window.removeEventListener('acadbeat:nav-mounted', run)
  }, [])

  return (
    <div className="app-container app-container--with-shared-nav">
      <MessageCenter />
    </div>
  )
}
