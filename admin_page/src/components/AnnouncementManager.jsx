import { useEffect, useMemo, useRef, useState } from 'react'
import { adminFetch } from '../api'

const API_ORIGIN = 'http://127.0.0.1:8001'

function formatRecordingDuration(totalSeconds) {
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}

function renderAnnouncementContent(text) {
  if (!text) return ''

  let html = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')

  html = html.replace(/!\[audio:(.*?)\]\((.*?)\)/g, (match, fileName, src) => {
    let type = 'audio/mpeg'
    if (fileName.endsWith('.wav')) type = 'audio/wav'
    if (fileName.endsWith('.ogg')) type = 'audio/ogg'
    if (fileName.endsWith('.m4a')) type = 'audio/mp4'
    if (fileName.endsWith('.webm')) type = 'audio/webm'

    return `
      <div style="margin: 14px 0; padding: 12px; border-radius: 14px; background: rgba(58, 78, 107, 0.08); border: 1px solid rgba(58, 78, 107, 0.14);">
        <div style="font-size: 12px; font-weight: 700; color: #3a4e6b; margin-bottom: 8px;">Audio · ${fileName}</div>
        <audio controls style="width: 100%;">
          <source src="${src}" type="${type}" />
        </audio>
      </div>
    `
  })

  html = html.replace(/!\[(.*?)\]\((.*?)\)/g, '<img src="$2" alt="$1" style="max-width: 100%; border-radius: 14px; margin: 12px 0; display: block;" />')
  html = html.replace(/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>')
  html = html.replace(/&lt;u&gt;(.*?)&lt;\/u&gt;/g, '<u>$1</u>')
  html = html.replace(/\n/g, '<br />')

  return html
}

function getAnnouncementPreview(text, maxLength = 160) {
  const preview = String(text || '')
    .replace(/!\[audio:(.*?)\]\((.*?)\)/g, ' [Audio] ')
    .replace(/!\[(.*?)\]\((.*?)\)/g, ' [Image] ')
    .replace(/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/g, '$1')
    .replace(/\*\*(.+?)\*\*/g, '$1')
    .replace(/\*(.+?)\*/g, '$1')
    .replace(/<u>(.*?)<\/u>/g, '$1')
    .replace(/\s+/g, ' ')
    .trim()

  return preview.length > maxLength ? `${preview.slice(0, maxLength)}...` : preview
}

function AnnouncementManager() {
  const [announcements, setAnnouncements] = useState([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)
  const [editingId, setEditingId] = useState(null)
  const [isPreview, setIsPreview] = useState(false)
  const [formData, setFormData] = useState({
    title: '',
    content: '',
    is_pinned: false,
  })
  const [isRecording, setIsRecording] = useState(false)
  const [isRecorderBusy, setIsRecorderBusy] = useState(false)
  const [recordingSeconds, setRecordingSeconds] = useState(0)

  const editorRef = useRef(null)
  const imageInputRef = useRef(null)
  const audioInputRef = useRef(null)
  const mediaRecorderRef = useRef(null)
  const mediaStreamRef = useRef(null)
  const recordedChunksRef = useRef([])

  useEffect(() => {
    fetchAnnouncements()
  }, [])

  useEffect(() => {
    if (!isRecording) {
      setRecordingSeconds(0)
      return undefined
    }

    const timer = window.setInterval(() => {
      setRecordingSeconds((prev) => prev + 1)
    }, 1000)

    return () => window.clearInterval(timer)
  }, [isRecording])

  const editorTools = useMemo(() => ([
    { label: 'B', onClick: () => handleFormat('**') },
    { label: 'I', onClick: () => handleFormat('*') },
    { label: 'U', onClick: () => handleFormat('<u>', '</u>') },
  ]), [formData.content])

  async function fetchAnnouncements() {
    setLoading(true)
    setError(null)
    try {
      const response = await adminFetch('/forum-project/api/announcements.php?limit=20')
      setAnnouncements(response.announcements || [])
    } catch (err) {
      setError('Failed to load announcements')
      console.error('Error fetching announcements:', err)
    } finally {
      setLoading(false)
    }
  }

  function updateField(name, value) {
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }))
  }

  function handleFormat(prefix, suffix = prefix) {
    const textarea = editorRef.current
    if (!textarea) return

    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const before = formData.content.slice(0, start)
    const selected = formData.content.slice(start, end)
    const after = formData.content.slice(end)
    const nextContent = `${before}${prefix}${selected}${suffix}${after}`

    updateField('content', nextContent)
    window.setTimeout(() => {
      textarea.focus()
      textarea.setSelectionRange(start + prefix.length, end + prefix.length)
    }, 0)
  }

  function insertMarkup(markup) {
    const textarea = editorRef.current
    if (!textarea) return
    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const insertText = `\n${markup}\n`
    const nextContent = `${formData.content.slice(0, start)}${insertText}${formData.content.slice(end)}`
    updateField('content', nextContent)
    window.setTimeout(() => {
      textarea.focus()
      const position = start + insertText.length
      textarea.setSelectionRange(position, position)
    }, 0)
  }

  async function uploadAsset(file, kind) {
    const formDataPayload = new FormData()
    formDataPayload.append('file', file)
    formDataPayload.append('kind', kind)

    const response = await fetch(`${API_ORIGIN}/forum-project/api/upload.php`, {
      method: 'POST',
      credentials: 'include',
      body: formDataPayload,
    })

    const data = await response.json().catch(() => ({
      ok: false,
      message: 'Invalid upload response.',
    }))

    if (!response.ok || data.ok === false) {
      throw new Error(data.message || 'Upload failed.')
    }

    return data
  }

  async function handleImageUpload(event) {
    const file = event.target.files?.[0]
    if (!file) return

    try {
      setError(null)
      const uploaded = await uploadAsset(file, 'image')
      insertMarkup(`![${uploaded.fileName}](${uploaded.url})`)
    } catch (err) {
      setError(err.message || 'Failed to upload image.')
    } finally {
      event.target.value = ''
    }
  }

  async function handleAudioUpload(event) {
    const file = event.target.files?.[0]
    if (!file) return

    try {
      setError(null)
      const uploaded = await uploadAsset(file, 'audio')
      insertMarkup(`![audio:${uploaded.fileName}](${uploaded.url})`)
    } catch (err) {
      setError(err.message || 'Failed to upload audio.')
    } finally {
      event.target.value = ''
    }
  }

  function stopRecordingStream() {
    if (mediaStreamRef.current) {
      mediaStreamRef.current.getTracks().forEach((track) => track.stop())
      mediaStreamRef.current = null
    }
  }

  async function handleRecordAudio() {
    if (isRecorderBusy) {
      return
    }

    if (isRecording) {
      if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
        setIsRecorderBusy(true)
        mediaRecorderRef.current.stop()
      }
      return
    }

    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function' || typeof MediaRecorder === 'undefined') {
      setError('This browser does not support direct audio recording.')
      return
    }

    try {
      setError(null)
      setIsRecorderBusy(true)
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      mediaStreamRef.current = stream

      let mimeType = ''
      if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
        mimeType = 'audio/webm;codecs=opus'
      } else if (MediaRecorder.isTypeSupported('audio/webm')) {
        mimeType = 'audio/webm'
      }

      const recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream)
      mediaRecorderRef.current = recorder
      recordedChunksRef.current = []

      recorder.ondataavailable = (evt) => {
        if (evt.data && evt.data.size > 0) {
          recordedChunksRef.current.push(evt.data)
        }
      }

      recorder.onstop = async () => {
        const fallbackType = recorder.mimeType || 'audio/webm'
        const extension = fallbackType.includes('ogg') ? 'ogg' : fallbackType.includes('mp4') ? 'm4a' : 'webm'
        const blob = new Blob(recordedChunksRef.current, { type: fallbackType })
        const file = new File([blob], `announcement-recording-${Date.now()}.${extension}`, { type: fallbackType })

        try {
          const uploaded = await uploadAsset(file, 'audio')
          insertMarkup(`![audio:${uploaded.fileName}](${uploaded.url})`)
        } catch (err) {
          setError(err.message || 'Failed to upload recorded audio.')
        } finally {
          recordedChunksRef.current = []
          setIsRecording(false)
          setIsRecorderBusy(false)
          setRecordingSeconds(0)
          stopRecordingStream()
        }
      }

      recorder.onerror = () => {
        setError('Recording failed. Please try again.')
        setIsRecording(false)
        setIsRecorderBusy(false)
        setRecordingSeconds(0)
        stopRecordingStream()
      }

      recorder.start()
      setIsRecording(true)
      setIsRecorderBusy(false)
    } catch (_err) {
      setError('Microphone access is required to record audio.')
      setIsRecording(false)
      setIsRecorderBusy(false)
      setRecordingSeconds(0)
      stopRecordingStream()
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()

    if (!formData.title.trim() || !formData.content.trim()) {
      setError('Title and content are required')
      return
    }

    setSaving(true)
    setError(null)

    try {
      const payload = {
        title: formData.title.trim(),
        content: formData.content.trim(),
        is_pinned: formData.is_pinned,
      }

      if (editingId) {
        await adminFetch(`/forum-project/api/announcements.php/${editingId}`, {
          method: 'PUT',
          body: JSON.stringify(payload),
        })
      } else {
        await adminFetch('/forum-project/api/announcements.php', {
          method: 'POST',
          body: JSON.stringify(payload),
        })
      }

      resetEditor()
      await fetchAnnouncements()
    } catch (err) {
      setError(err.message || 'Failed to save announcement')
      console.error('Error saving announcement:', err)
    } finally {
      setSaving(false)
    }
  }

  function handleEdit(announcement) {
    setEditingId(announcement.id)
    setIsPreview(false)
    setFormData({
      title: announcement.title || '',
      content: announcement.content || '',
      is_pinned: Boolean(announcement.isPinned ?? announcement.is_pinned),
    })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  async function handleDelete(id) {
    if (!window.confirm('Delete this announcement?')) {
      return
    }

    setSaving(true)
    setError(null)

    try {
      await adminFetch(`/forum-project/api/announcements.php/${id}`, {
        method: 'DELETE',
      })
      if (editingId === id) {
        resetEditor()
      }
      await fetchAnnouncements()
    } catch (err) {
      setError(err.message || 'Failed to delete announcement')
      console.error('Error deleting announcement:', err)
    } finally {
      setSaving(false)
    }
  }

  function resetEditor() {
    setEditingId(null)
    setIsPreview(false)
    setFormData({
      title: '',
      content: '',
      is_pinned: false,
    })
  }

  return (
    <div style={styles.container}>
      <header style={styles.header}>
        <div style={styles.hero}>
          <div style={styles.eyebrow}>Admin Content Management</div>
          <h1 style={styles.title}>Announcement Manager</h1>
          <p style={styles.subtitle}>Publish pinned or regular forum announcements with the same writing tools your post editor already supports.</p>
        </div>
      </header>

      {error ? <div style={styles.error}>{error}</div> : null}

      <div style={styles.layout}>
        <section style={styles.formContainer}>
          <div style={styles.formHeader}>
            <div>
              <h2 style={styles.formTitle}>{editingId ? 'Edit Announcement' : 'Create Announcement'}</h2>
              <p style={styles.formHint}>Supports text formatting, images, uploaded audio, and recorded voice.</p>
            </div>
            <label style={styles.pinToggle}>
              <input
                type="checkbox"
                checked={formData.is_pinned}
                onChange={(event) => updateField('is_pinned', event.target.checked)}
              />
              <span>Pin on forum top bar</span>
            </label>
          </div>

          <form onSubmit={handleSubmit} style={styles.form}>
            <div style={styles.formGroup}>
              <label style={styles.label}>Title</label>
              <input
                type="text"
                value={formData.title}
                onChange={(event) => updateField('title', event.target.value)}
                style={styles.input}
                placeholder="Enter announcement title"
              />
            </div>

            <div style={styles.toolbarRow}>
              <div style={styles.toolbarGroup}>
                {editorTools.map((tool) => (
                  <button key={tool.label} type="button" onClick={tool.onClick} style={styles.toolButton}>
                    {tool.label}
                  </button>
                ))}
                <button type="button" onClick={() => imageInputRef.current?.click()} style={styles.toolButton}>Image</button>
                <button type="button" onClick={() => audioInputRef.current?.click()} style={styles.toolButton}>Audio</button>
                <button type="button" onClick={handleRecordAudio} style={{ ...styles.toolButton, ...(isRecording ? styles.toolButtonActive : {}) }}>
                  {isRecording ? 'Stop' : 'Record'}
                </button>
              </div>
              <button type="button" onClick={() => setIsPreview((prev) => !prev)} style={styles.previewToggle}>
                {isPreview ? 'Back To Edit' : 'Preview'}
              </button>
            </div>

            {(isRecording || isRecorderBusy) ? (
              <div style={styles.recorderBanner}>
                <strong>{isRecording ? `Recording ${formatRecordingDuration(recordingSeconds)}` : 'Uploading audio clip...'}</strong>
              </div>
            ) : null}

            <div style={styles.editorSurface}>
              {isPreview ? (
                <div
                  style={styles.previewBox}
                  dangerouslySetInnerHTML={{ __html: renderAnnouncementContent(formData.content) }}
                />
              ) : (
                <textarea
                  ref={editorRef}
                  value={formData.content}
                  onChange={(event) => updateField('content', event.target.value)}
                  style={styles.textarea}
                  placeholder="Write the announcement body here..."
                />
              )}
            </div>

            <div style={styles.formActions}>
              {editingId ? (
                <button type="button" onClick={resetEditor} style={{ ...styles.actionButton, ...styles.secondaryButton }} disabled={saving}>
                  Cancel
                </button>
              ) : null}
              <button type="submit" style={{ ...styles.actionButton, ...styles.primaryButton }} disabled={saving}>
                {saving ? 'Saving...' : editingId ? 'Update Announcement' : 'Publish Announcement'}
              </button>
            </div>
          </form>
        </section>

        <section style={styles.listContainer}>
          <div style={styles.listHeader}>
            <h2 style={styles.listTitle}>Existing Announcements</h2>
            <span style={styles.countBadge}>{announcements.length}</span>
          </div>

          {loading ? (
            <div style={styles.loading}>Loading announcements...</div>
          ) : announcements.length === 0 ? (
            <div style={styles.empty}>No announcements found</div>
          ) : (
            <div style={styles.announcementsList}>
              {announcements.map((announcement) => (
                <article key={announcement.id} style={styles.announcementItem}>
                  <div style={styles.announcementHeader}>
                    <div>
                      <h3 style={styles.announcementTitle}>{announcement.title}</h3>
                      <div style={styles.announcementMeta}>
                        <span>{announcement.publishTime}</span>
                        <span>{announcement.author}</span>
                        {(announcement.isPinned ?? announcement.is_pinned) ? <span style={styles.pinnedBadge}>Pinned</span> : null}
                      </div>
                    </div>
                    <div style={styles.announcementActions}>
                      <button onClick={() => handleEdit(announcement)} style={{ ...styles.smallButton, ...styles.editButton }}>Edit</button>
                      <button onClick={() => handleDelete(announcement.id)} style={{ ...styles.smallButton, ...styles.deleteButton }}>Delete</button>
                    </div>
                  </div>
                  <p style={styles.announcementContent}>{getAnnouncementPreview(announcement.content)}</p>
                </article>
              ))}
            </div>
          )}
        </section>
      </div>

      <input ref={imageInputRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={handleImageUpload} />
      <input ref={audioInputRef} type="file" accept="audio/*" style={{ display: 'none' }} onChange={handleAudioUpload} />
    </div>
  )
}

const styles = {
  container: {
    maxWidth: '1320px',
    margin: '0 auto',
    padding: '42px 20px 28px',
    minHeight: 'calc(100vh - 73px)',
  },
  header: {
    marginBottom: '28px',
  },
  hero: {
    minWidth: 0,
  },
  eyebrow: {
    fontSize: '0.72rem',
    fontWeight: 700,
    letterSpacing: '0.24rem',
    textTransform: 'uppercase',
    color: 'rgba(58, 78, 107, 0.45)',
    marginBottom: '12px',
  },
  title: {
    fontSize: '2.9rem',
    fontWeight: 700,
    color: 'var(--secondary-color)',
    margin: '0 0 10px 0',
    fontFamily: 'Inter, sans-serif',
    lineHeight: 1,
    letterSpacing: '-0.03em',
  },
  subtitle: {
    margin: 0,
    maxWidth: '820px',
    fontSize: '1rem',
    lineHeight: 1.65,
    color: 'rgba(58, 78, 107, 0.78)',
  },
  error: {
    marginBottom: '18px',
    borderRadius: '16px',
    border: '1px solid rgba(179, 83, 58, 0.22)',
    background: 'rgba(179, 83, 58, 0.1)',
    color: '#8d3c25',
    padding: '14px 16px',
  },
  layout: {
    display: 'grid',
    gridTemplateColumns: 'minmax(0, 1.2fr) minmax(320px, 0.8fr)',
    gap: '24px',
    alignItems: 'start',
  },
  formContainer: {
    borderRadius: '26px',
    background: 'rgba(255, 255, 255, 0.9)',
    border: '1px solid rgba(58, 78, 107, 0.12)',
    boxShadow: '0 20px 45px rgba(58, 78, 107, 0.08)',
    padding: '24px',
  },
  formHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    gap: '16px',
    marginBottom: '20px',
    alignItems: 'flex-start',
  },
  formTitle: {
    margin: '0 0 6px',
    fontSize: '1.55rem',
    color: '#2b3f58',
  },
  formHint: {
    margin: 0,
    color: 'rgba(58, 78, 107, 0.7)',
    lineHeight: 1.5,
  },
  pinToggle: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '10px',
    fontWeight: 600,
    color: '#3a4e6b',
    whiteSpace: 'nowrap',
  },
  form: {
    display: 'grid',
    gap: '18px',
  },
  formGroup: {
    display: 'grid',
    gap: '8px',
  },
  label: {
    fontSize: '0.9rem',
    fontWeight: 700,
    color: '#3a4e6b',
  },
  input: {
    borderRadius: '16px',
    border: '1px solid rgba(58, 78, 107, 0.16)',
    padding: '14px 16px',
    fontSize: '1rem',
    outline: 'none',
  },
  toolbarRow: {
    display: 'flex',
    justifyContent: 'space-between',
    gap: '12px',
    flexWrap: 'wrap',
  },
  toolbarGroup: {
    display: 'flex',
    gap: '10px',
    flexWrap: 'wrap',
  },
  toolButton: {
    borderRadius: '999px',
    border: '1px solid rgba(58, 78, 107, 0.16)',
    background: '#f6f4f0',
    color: '#314760',
    padding: '10px 14px',
    fontWeight: 700,
    cursor: 'pointer',
  },
  toolButtonActive: {
    background: '#3a4e6b',
    color: '#fff',
  },
  previewToggle: {
    borderRadius: '999px',
    border: '1px solid rgba(58, 78, 107, 0.16)',
    background: '#fff',
    color: '#314760',
    padding: '10px 16px',
    fontWeight: 700,
    cursor: 'pointer',
  },
  recorderBanner: {
    borderRadius: '16px',
    padding: '14px 16px',
    background: 'rgba(58, 78, 107, 0.08)',
    color: '#314760',
  },
  editorSurface: {
    borderRadius: '22px',
    border: '1px solid rgba(58, 78, 107, 0.14)',
    overflow: 'hidden',
    background: '#fff',
  },
  textarea: {
    width: '100%',
    minHeight: '340px',
    border: 'none',
    outline: 'none',
    resize: 'vertical',
    padding: '20px',
    fontSize: '1rem',
    lineHeight: 1.7,
    fontFamily: 'inherit',
  },
  previewBox: {
    minHeight: '340px',
    padding: '20px',
    lineHeight: 1.7,
    color: '#243547',
  },
  formActions: {
    display: 'flex',
    justifyContent: 'flex-end',
    gap: '12px',
    flexWrap: 'wrap',
  },
  actionButton: {
    border: 'none',
    borderRadius: '999px',
    padding: '12px 18px',
    fontWeight: 700,
    cursor: 'pointer',
  },
  primaryButton: {
    background: 'rgba(58, 78, 107, 0.94)',
    color: '#fff',
  },
  secondaryButton: {
    background: '#ece7de',
    color: '#2b3f58',
  },
  listContainer: {
    borderRadius: '26px',
    background: 'rgba(255, 255, 255, 0.9)',
    border: '1px solid rgba(58, 78, 107, 0.12)',
    boxShadow: '0 20px 45px rgba(58, 78, 107, 0.08)',
    padding: '24px',
    minHeight: '480px',
  },
  listHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: '12px',
    marginBottom: '18px',
  },
  listTitle: {
    margin: 0,
    fontSize: '1.4rem',
    color: '#2b3f58',
  },
  countBadge: {
    minWidth: '32px',
    padding: '6px 10px',
    borderRadius: '999px',
    background: 'rgba(58, 78, 107, 0.08)',
    color: '#3a4e6b',
    textAlign: 'center',
    fontWeight: 700,
  },
  loading: {
    color: 'rgba(58, 78, 107, 0.78)',
  },
  empty: {
    color: 'rgba(58, 78, 107, 0.62)',
  },
  announcementsList: {
    display: 'grid',
    gap: '14px',
  },
  announcementItem: {
    borderRadius: '20px',
    border: '1px solid rgba(58, 78, 107, 0.12)',
    background: '#faf8f4',
    padding: '18px',
  },
  announcementHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    gap: '12px',
    alignItems: 'flex-start',
    marginBottom: '12px',
  },
  announcementTitle: {
    margin: '0 0 8px',
    color: '#2b3f58',
  },
  announcementMeta: {
    display: 'flex',
    gap: '10px',
    flexWrap: 'wrap',
    color: 'rgba(58, 78, 107, 0.7)',
    fontSize: '0.9rem',
  },
  pinnedBadge: {
    borderRadius: '999px',
    background: 'rgba(214, 170, 63, 0.18)',
    color: '#8a6515',
    padding: '2px 8px',
    fontWeight: 700,
  },
  announcementActions: {
    display: 'flex',
    gap: '8px',
  },
  smallButton: {
    border: 'none',
    borderRadius: '999px',
    padding: '8px 12px',
    fontWeight: 700,
    cursor: 'pointer',
  },
  editButton: {
    background: 'rgba(58, 78, 107, 0.12)',
    color: '#2b3f58',
  },
  deleteButton: {
    background: 'rgba(179, 83, 58, 0.14)',
    color: '#8d3c25',
  },
  announcementContent: {
    margin: 0,
    color: '#4a5b71',
    lineHeight: 1.65,
  },
}

export default AnnouncementManager
