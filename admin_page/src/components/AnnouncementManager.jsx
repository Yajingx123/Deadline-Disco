import { useState, useEffect } from 'react'
import { adminFetch } from '../api'

function AnnouncementManager() {
  const [announcements, setAnnouncements] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [editingId, setEditingId] = useState(null)
  const [formData, setFormData] = useState({
    title: '',
    content: ''
  })

  useEffect(() => {
    fetchAnnouncements()
  }, [])

  async function fetchAnnouncements() {
    setLoading(true)
    setError(null)
    try {
      const response = await adminFetch('/forum-project/api/announcements.php')
      setAnnouncements(response.announcements || [])
    } catch (err) {
      setError('Failed to load announcements')
      console.error('Error fetching announcements:', err)
    } finally {
      setLoading(false)
    }
  }

  function handleInputChange(e) {
    const { name, value } = e.target
    setFormData(prev => ({
      ...prev,
      [name]: value
    }))
  }

  async function handleSubmit(e) {
    e.preventDefault()
    
    if (!formData.title.trim() || !formData.content.trim()) {
      setError('Title and content are required')
      return
    }

    setLoading(true)
    setError(null)
    
    try {
      if (editingId) {
        // Update existing announcement
        await adminFetch(`/forum-project/api/announcements.php?id=${editingId}`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(formData)
        })
        setEditingId(null)
      } else {
        // Create new announcement
        await adminFetch('/forum-project/api/announcements.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(formData)
        })
      }
      
      setFormData({ title: '', content: '' })
      await fetchAnnouncements()
    } catch (err) {
      setError('Failed to save announcement')
      console.error('Error saving announcement:', err)
    } finally {
      setLoading(false)
    }
  }

  function handleEdit(announcement) {
    setEditingId(announcement.id)
    setFormData({
      title: announcement.title,
      content: announcement.content
    })
  }

  async function handleDelete(id) {
    if (!confirm('Are you sure you want to delete this announcement?')) {
      return
    }

    setLoading(true)
    setError(null)
    
    try {
      await adminFetch(`/forum-project/api/announcements.php?id=${id}`, {
        method: 'DELETE'
      })
      await fetchAnnouncements()
    } catch (err) {
      setError('Failed to delete announcement')
      console.error('Error deleting announcement:', err)
    } finally {
      setLoading(false)
    }
  }

  function cancelEdit() {
    setEditingId(null)
    setFormData({ title: '', content: '' })
  }

  return (
    <div style={styles.container}>
      <header style={styles.header}>
        <div style={styles.hero}>
          <div style={styles.eyebrow}>Admin Content Management</div>
          <h1 style={styles.title}>Announcement Manager</h1>
          <p style={styles.subtitle}>Create, edit, and manage announcements for the forum community.</p>
        </div>
      </header>
      
      {error && (
        <div style={styles.error}>{error}</div>
      )}

      {/* Announcement Form */}
      <div style={styles.formContainer}>
        <h2 style={styles.formTitle}>
          {editingId ? 'Edit Announcement' : 'Create New Announcement'}
        </h2>
        <form onSubmit={handleSubmit} style={styles.form}>
          <div style={styles.formGroup}>
            <label style={styles.label}>Title</label>
            <input
              type="text"
              name="title"
              value={formData.title}
              onChange={handleInputChange}
              style={styles.input}
              placeholder="Enter announcement title"
            />
          </div>
          <div style={styles.formGroup}>
            <label style={styles.label}>Content</label>
            <textarea
              name="content"
              value={formData.content}
              onChange={handleInputChange}
              style={styles.textarea}
              placeholder="Enter announcement content"
              rows={5}
            />
          </div>
          <div style={styles.formActions}>
            {editingId && (
              <button
                type="button"
                onClick={cancelEdit}
                style={{ ...styles.button, ...styles.cancelButton }}
                disabled={loading}
              >
                Cancel
              </button>
            )}
            <button
              type="submit"
              style={{ ...styles.button, backgroundColor: 'rgba(58, 78, 107, 0.94)', color: 'var(--white)' }}
              disabled={loading}
            >
              {loading ? 'Saving...' : editingId ? 'Update' : 'Create'}
            </button>
          </div>
        </form>
      </div>

      {/* Announcements List */}
      <div style={styles.listContainer}>
        <h2 style={styles.listTitle}>Existing Announcements</h2>
        {loading ? (
          <div style={styles.loading}>Loading announcements...</div>
        ) : announcements.length === 0 ? (
          <div style={styles.empty}>No announcements found</div>
        ) : (
          <div style={styles.announcementsList}>
            {announcements.map(announcement => (
              <div key={announcement.id} style={styles.announcementItem}>
                <div style={styles.announcementHeader}>
                  <h3 style={styles.announcementTitle}>{announcement.title}</h3>
                  <div style={styles.announcementActions}>
                    <button
                      onClick={() => handleEdit(announcement)}
                      style={{ ...styles.actionButton, ...styles.editButton }}
                    >
                      Edit
                    </button>
                    <button
                      onClick={() => handleDelete(announcement.id)}
                      style={{ ...styles.actionButton, ...styles.deleteButton }}
                    >
                      Delete
                    </button>
                  </div>
                </div>
                <p style={styles.announcementContent}>
                  {announcement.content.length > 100 
                    ? announcement.content.substring(0, 100) + '...' 
                    : announcement.content
                  }
                </p>
                <div style={styles.announcementMeta}>
                  <span style={styles.announcementAuthor}>By: {announcement.author}</span>
                  <span style={styles.announcementDate}>{announcement.publishTime}</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

const styles = {
  container: {
    maxWidth: '1280px',
    margin: '0 auto',
    padding: '42px 20px 24px',
    minHeight: 'calc(100vh - 73px)'
  },
  header: {
    display: 'grid',
    gridTemplateColumns: 'minmax(0, 1fr) 360px',
    gap: '24px',
    alignItems: 'end',
    marginBottom: '32px'
  },
  hero: {
    minWidth: 0
  },
  eyebrow: {
    fontSize: '0.72rem',
    fontWeight: 700,
    letterSpacing: '0.24rem',
    textTransform: 'uppercase',
    color: 'rgba(58, 78, 107, 0.45)',
    marginBottom: '12px'
  },
  title: {
    fontSize: '2.9rem',
    fontWeight: 700,
    color: 'var(--secondary-color)',
    margin: '0 0 10px 0',
    fontFamily: 'Inter, sans-serif',
    lineHeight: 1,
    letterSpacing: '-0.03em'
  },
  subtitle: {
    margin: 0,
    maxWidth: '620px',
    color: 'rgba(58, 78, 107, 0.74)',
    fontSize: '0.95rem',
    lineHeight: 1.6
  },
  error: {
    backgroundColor: 'rgba(239, 68, 68, 0.12)',
    color: '#b42318',
    padding: '14px 16px',
    borderRadius: '18px',
    marginBottom: '24px',
    fontSize: '14px',
    fontWeight: 700
  },
  formContainer: {
    backgroundColor: 'rgba(255, 255, 255, 0.88)',
    border: '1px solid rgba(58, 78, 107, 0.08)',
    borderRadius: '26px',
    padding: '24px',
    marginBottom: '32px',
    boxShadow: '0 18px 44px rgba(58, 78, 107, 0.06)'
  },
  formTitle: {
    fontSize: '20px',
    fontWeight: '600',
    color: 'var(--secondary-color)',
    margin: '0 0 20px 0',
    fontFamily: 'Inter, sans-serif'
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    gap: '16px'
  },
  formGroup: {
    display: 'flex',
    flexDirection: 'column',
    gap: '8px'
  },
  label: {
    fontSize: '14px',
    fontWeight: '600',
    color: 'var(--secondary-color)',
    fontFamily: 'Inter, sans-serif'
  },
  input: {
    padding: '12px 16px',
    border: '1px solid rgba(58, 78, 107, 0.08)',
    borderRadius: '8px',
    fontSize: '16px',
    color: 'var(--secondary-color)',
    backgroundColor: 'rgba(255, 255, 255, 0.95)',
    fontFamily: 'Inter, sans-serif',
    transition: 'all 0.3s ease'
  },
  textarea: {
    padding: '12px 16px',
    border: '1px solid rgba(58, 78, 107, 0.08)',
    borderRadius: '8px',
    fontSize: '16px',
    color: 'var(--secondary-color)',
    backgroundColor: 'rgba(255, 255, 255, 0.95)',
    fontFamily: 'Inter, sans-serif',
    resize: 'vertical',
    transition: 'all 0.3s ease'
  },
  formActions: {
    display: 'flex',
    gap: '12px',
    marginTop: '8px'
  },
  button: {
    padding: '12px 24px',
    border: 'none',
    borderRadius: '999px',
    fontSize: '16px',
    fontWeight: '600',
    cursor: 'pointer',
    fontFamily: 'Inter, sans-serif',
    transition: 'all 0.3s ease',
    boxShadow: '0 14px 28px rgba(58, 78, 107, 0.12)'
  },
  cancelButton: {
    backgroundColor: 'rgba(155, 183, 212, 0.8)',
    color: 'var(--white)'
  },
  listContainer: {
    backgroundColor: 'rgba(255, 255, 255, 0.88)',
    border: '1px solid rgba(58, 78, 107, 0.08)',
    borderRadius: '26px',
    padding: '24px',
    boxShadow: '0 18px 44px rgba(58, 78, 107, 0.06)'
  },
  listTitle: {
    fontSize: '20px',
    fontWeight: '600',
    color: 'var(--secondary-color)',
    margin: '0 0 20px 0',
    fontFamily: 'Inter, sans-serif'
  },
  loading: {
    textAlign: 'center',
    padding: '48px',
    color: 'rgba(58, 78, 107, 0.5)'
  },
  empty: {
    textAlign: 'center',
    padding: '48px',
    color: 'rgba(58, 78, 107, 0.5)'
  },
  announcementsList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '16px'
  },
  announcementItem: {
    backgroundColor: 'transparent',
    border: '1px solid rgba(58, 78, 107, 0.08)',
    borderRadius: '16px',
    padding: '18px',
    transition: 'all 0.3s ease',
    backdropFilter: 'none'
  },
  announcementHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: '8px'
  },
  announcementTitle: {
    fontSize: '16px',
    fontWeight: '600',
    color: 'var(--secondary-color)',
    margin: '0',
    fontFamily: 'Inter, sans-serif'
  },
  announcementActions: {
    display: 'flex',
    gap: '8px'
  },
  actionButton: {
    padding: '8px 16px',
    border: 'none',
    borderRadius: '999px',
    fontSize: '0.78rem',
    fontWeight: '700',
    cursor: 'pointer',
    fontFamily: 'Inter, sans-serif',
    transition: 'all 0.25s ease'
  },
  editButton: {
    backgroundColor: 'rgba(155, 183, 212, 0.8)',
    color: 'var(--white)'
  },
  deleteButton: {
    backgroundColor: 'rgba(239, 68, 68, 0.9)',
    color: 'white'
  },
  announcementContent: {
    fontSize: '14px',
    color: 'rgba(58, 78, 107, 0.74)',
    margin: '0 0 12px 0',
    lineHeight: '1.55'
  },
  announcementMeta: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    fontSize: '12px',
    color: 'rgba(58, 78, 107, 0.6)'
  }
}

export default AnnouncementManager