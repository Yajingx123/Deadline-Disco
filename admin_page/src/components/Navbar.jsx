/*
 * @Author: yzp 488361078@qq.com
 * @Date: 2026-03-28 23:03:52
 * @LastEditors: yzp 488361078@qq.com
 * @LastEditTime: 2026-03-28 23:03:57
 * @FilePath: \Deadline-Disco-dev\admin_page\src\components\Navbar.jsx
 * @Description: 这是默认设置,请设置`customMade`, 打开koroFileHeader查看配置 进行设置: https://github.com/OBKoro1/koro1FileHeader/wiki/%E9%85%8D%E7%BD%AE
 */
import { useState } from 'react'

function Navbar({ currentPage, setCurrentPage }) {
  return (
    <nav style={styles.navbar}>
      <div style={styles.container}>
        <div style={styles.logo}>Admin Panel</div>
        <div style={styles.navLinks}>
          <button
            style={{
              ...styles.navLink,
              ...(currentPage === 'home' ? styles.activeLink : {})
            }}
            onClick={() => setCurrentPage('home')}
          >
            Home
          </button>
          <button
            style={{
              ...styles.navLink,
              ...(currentPage === 'forum' ? styles.activeLink : {})
            }}
            onClick={() => setCurrentPage('forum')}
          >
            Forum Management
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
    alignItems: 'center'
  },
  logo: {
    fontSize: '24px',
    fontWeight: '600',
    color: 'var(--text-h)',
    fontFamily: 'var(--heading)'
  },
  navLinks: {
    display: 'flex',
    gap: '8px'
  },
  navLink: {
    padding: '8px 16px',
    borderRadius: '6px',
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
  }
}

export default Navbar
