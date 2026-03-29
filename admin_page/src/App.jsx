/*
 * @Author: yzp 488361078@qq.com
 * @Date: 2026-03-28 22:06:59
 * @LastEditors: yzp 488361078@qq.com
 * @LastEditTime: 2026-03-28 23:07:49
 * @FilePath: \Deadline-Disco-dev\admin_page\src\App.jsx
 * @Description: 这是默认设置,请设置`customMade`, 打开koroFileHeader查看配置 进行设置: https://github.com/OBKoro1/koro1FileHeader/wiki/%E9%85%8D%E7%BD%AE
 */
import { useState } from 'react'
import './App.css'
import Navbar from './components/Navbar'
import ForumPostReview from './components/ForumPostReview'

function App() {
  const [currentPage, setCurrentPage] = useState('home')

  const renderContent = () => {
    if (currentPage === 'forum') {
      return <ForumPostReview />
    }
    return (
      <div style={styles.homeContainer}>
        <h1 style={styles.welcomeTitle}>Welcome to Admin Panel</h1>
        <p style={styles.welcomeText}>Select a module from the navigation menu to get started.</p>
      </div>
    )
  }

  return (
    <>
      <Navbar currentPage={currentPage} setCurrentPage={setCurrentPage} />
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
