/*
 * @Author: yzp 488361078@qq.com
 * @Date: 2026-03-31 15:16:46
 * @LastEditors: yzp 488361078@qq.com
 * @LastEditTime: 2026-03-31 15:33:58
 * @FilePath: \dev\forum-project\src\components\Header.jsx
 * @Description: 这是默认设置,请设置`customMade`, 打开koroFileHeader查看配置 进行设置: https://github.com/OBKoro1/koro1FileHeader/wiki/%E9%85%8D%E7%BD%AE
 */
import { useState, useEffect } from 'react';
import AppTopNav from './AppTopNav';
import { fetchAnnouncements } from '../api/forumApi';

export default function Header({ searchQuery, setSearchQuery, onOpenModal, currentUser, onShowFavorites, onShowMyPosts, viewMode, onBackToForum, favoritesTab }) {
  const [announcements, setAnnouncements] = useState([]);
  const [loading, setLoading] = useState(false);
  const [showAnnouncementsModal, setShowAnnouncementsModal] = useState(false);

  useEffect(() => {
    const loadAnnouncements = async () => {
      setLoading(true);
      try {
        const data = await fetchAnnouncements({ limit: 5 });
        setAnnouncements(data.announcements || []);
      } catch (error) {
        console.error('Failed to load announcements:', error);
      } finally {
        setLoading(false);
      }
    };

    loadAnnouncements();
  }, []);

  const latestAnnouncement = announcements.find(ann => ann.is_pinned) || announcements[0];

  return (
    <>
      <AppTopNav currentUser={currentUser} activeMode="forum" />

      {/* Announcement Bar */}
      {!loading && announcements.length > 0 && (
        <div className="announcement-bar">
          <div className="announcement-container">
            <div className="announcement-content">
              {latestAnnouncement && (
                <>
                  <span className="announcement-icon">📢</span>
                  <span className="announcement-text">
                    <strong>{latestAnnouncement.title}</strong>: {latestAnnouncement.content.length > 100 ? latestAnnouncement.content.substring(0, 100) + '...' : latestAnnouncement.content}
                  </span>
                  <button 
                    className="announcement-link" 
                    onClick={() => setShowAnnouncementsModal(true)}
                  >
                    View All
                  </button>
                </>
              )}
            </div>
          </div>
        </div>
      )}

      <header className="forum-header">
        <div className="forum-hero">
          <div className="forum-hero__eyebrow">Section III — Collective Intellect</div>
          <h1 className="forum-hero__title">{viewMode === 'favorites' ? (favoritesTab === 'favorites' ? 'My Favorites' : favoritesTab === 'likes' ? 'My Likes' : 'My Posts') : 'Forum'}</h1>
          <p className="forum-hero__sub">
            {viewMode === 'favorites' 
              ? (favoritesTab === 'favorites' ? 'Your saved posts' : favoritesTab === 'likes' ? 'Posts you liked' : 'Your posted content') 
              : 'Discuss ideas, ask for help, and archive useful threads in one place.'}
          </p>
          <div className="forum-hero__actions">
            {viewMode === 'favorites' ? (
              <button className="post-btn post-btn--hero" onClick={onBackToForum}>Back to Forum</button>
            ) : (
              <>
                <button className="post-btn post-btn--hero" onClick={onOpenModal}>Publish Post</button>
                <button className="post-btn post-btn--secondary" onClick={onShowFavorites}>My...</button>
              </>
            )}
          </div>
        </div>
        {viewMode !== 'favorites' && (
          <div className="forum-header__searchWrap">
            <input 
              type="text" 
              className="search-bar"
              placeholder="Search title, author, content" 
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>
        )}
      </header>

      {/* Announcements Modal */}
      {showAnnouncementsModal && (
        <div className="modal-overlay" onClick={() => setShowAnnouncementsModal(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h2 className="modal-title">Announcements</h2>
              <button 
                className="modal-close" 
                onClick={() => setShowAnnouncementsModal(false)}
              >
                ×
              </button>
            </div>
            <div className="modal-body">
              {loading ? (
                <div className="loading-indicator">Loading...</div>
              ) : announcements.length > 0 ? (
                <div className="announcements-list">
                  {announcements.map(announcement => (
                    <div key={announcement.id} className="announcement-item">
                      <div className="announcement-item-header">
                        <h4 className="announcement-item-title">{announcement.title}</h4>
                        <span className="announcement-item-date">{announcement.publishTime}</span>
                      </div>
                      <p className="announcement-item-content">{announcement.content}</p>
                      <div className="announcement-item-footer">
                        <span className="announcement-item-author">By: {announcement.author}</span>
                        {announcement.is_pinned && (
                          <span className="announcement-item-pinned">Pinned</span>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="no-announcements">No announcements found</div>
              )}
            </div>
          </div>
        </div>
      )}
    </>
  );
}
