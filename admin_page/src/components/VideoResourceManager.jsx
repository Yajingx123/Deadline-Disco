import { useState, useEffect, useRef } from 'react'
import { adminFetch } from '../api'

function VideoResourceManager() {
  const [videos, setVideos] = useState([])
  const [loading, setLoading] = useState(true)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)
  const [editingId, setEditingId] = useState(null)
  
  // 表单数据
  const [formData, setFormData] = useState({
    video_id: '',
    mode: 'understand',
    title: '',
    type: 'Campus',
    difficulty: 'Easy',
    duration: '',
    source: 'ELLLO',
    country: '',
    author: '',
    time_specific: '',
    question: '',
    transcript_text: '',
    answer_text: ''
  })
  
  // 文件引用
  const fileRefs = {
    video: useRef(null),
    transcript: useRef(null),
    vtt: useRef(null),
    labels: useRef(null),
    sampleNotes: useRef(null),
    cover: useRef(null),
    flag: useRef(null)
  }
  
  // 文件状态
  const [files, setFiles] = useState({
    video: null,
    transcript: null,
    vtt: null,
    labels: null,
    sampleNotes: null,
    cover: null,
    flag: null
  })

  useEffect(() => {
    fetchVideos()
  }, [])

  async function fetchVideos() {
    setLoading(true)
    setError(null)
    try {
      const response = await adminFetch('/Academic-Practice/api/videos.php?action=list')
      setVideos(response.data || [])
    } catch (err) {
      setError('Failed to load video resources')
      console.error('Error fetching videos:', err)
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

  function handleFileChange(type, e) {
    const file = e.target.files[0]
    if (file) {
      setFiles(prev => ({
        ...prev,
        [type]: file
      }))
    }
  }

  /** 本地 practice-data.js 各占 12 个 id（u1–u12 / s1–s12），新建从 13 起，避免与默认题库冲突 */
  function generateVideoId() {
    const reserved = 12
    const isUnderstand = formData.mode === 'understand'
    const prefix = isUnderstand ? 'u' : 's'
    const nums = videos
      .filter((v) => v.mode === formData.mode)
      .map((v) => {
        const id = String(v.video_id || '')
        if (isUnderstand) {
          const m = id.match(/^u(\d+)$/i)
          return m ? parseInt(m[1], 10) : 0
        }
        const ms = id.match(/^s(\d+)$/i)
        if (ms) return parseInt(ms[1], 10)
        const mr = id.match(/^r(\d+)$/i)
        return mr ? parseInt(mr[1], 10) : 0
      })
    const maxId = Math.max(reserved, 0, ...nums)
    return `${prefix}${maxId + 1}`
  }

  async function handleSubmit(e) {
    e.preventDefault()
    
    if (!formData.title.trim()) {
      setError('Title is required')
      return
    }

    setUploading(true)
    setError(null)
    setSuccess(null)
    
    try {
      const submitData = new FormData()
      
      // 添加表单字段
      Object.keys(formData).forEach(key => {
        submitData.append(key, formData[key])
      })
      
      // 如果是新建，生成视频ID
      if (!editingId) {
        const newId = generateVideoId()
        submitData.append('video_id', newId)
      }
      
      // 添加文件
      Object.keys(files).forEach(key => {
        if (files[key]) {
          submitData.append(key + '_file', files[key])
        }
      })

      const url = editingId 
        ? `/Academic-Practice/api/videos.php?action=update&id=${editingId}`
        : '/Academic-Practice/api/videos.php?action=create'
      
      const response = await adminFetch(url, {
        method: 'POST',
        body: submitData
      })
      
      setSuccess(editingId ? 'Video resource updated successfully!' : 'Video resource created successfully!')
      resetForm()
      await fetchVideos()
      
      // 3秒后清除成功消息
      setTimeout(() => setSuccess(null), 3000)
    } catch (err) {
      setError(err.message || 'Failed to save video resource')
      console.error('Error saving video:', err)
    } finally {
      setUploading(false)
    }
  }

  function handleEdit(video) {
    setEditingId(video.id)
    setFormData({
      video_id: video.id,
      mode: video.mode,
      title: video.title,
      type: video.type,
      difficulty: video.difficulty,
      duration: video.duration || '',
      source: video.source || 'ELLLO',
      country: video.country || '',
      author: video.author || '',
      time_specific: video.timeSpecific || '',
      question: video.question || '',
      transcript_text: video.transcriptText || '',
      answer_text: video.answerText || ''
    })
    setFiles({
      video: null,
      transcript: null,
      vtt: null,
      labels: null,
      sampleNotes: null,
      cover: null,
      flag: null
    })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  function resetForm() {
    setEditingId(null)
    setFormData({
      video_id: '',
      mode: 'understand',
      title: '',
      type: 'Campus',
      difficulty: 'Easy',
      duration: '',
      source: 'ELLLO',
      country: '',
      author: '',
      time_specific: '',
      question: '',
      transcript_text: '',
      answer_text: ''
    })
    setFiles({
      video: null,
      transcript: null,
      vtt: null,
      labels: null,
      sampleNotes: null,
      cover: null,
      flag: null
    })
    // 清空文件输入
    Object.values(fileRefs).forEach(ref => {
      if (ref.current) ref.current.value = ''
    })
  }

  async function handleDelete(id) {
    if (!confirm('Are you sure you want to delete this video resource?')) {
      return
    }

    setLoading(true)
    setError(null)
    
    try {
      await adminFetch(`/Academic-Practice/api/videos.php?action=delete&id=${id}`, {
        method: 'DELETE'
      })
      setSuccess('Video resource deleted successfully!')
      await fetchVideos()
      setTimeout(() => setSuccess(null), 3000)
    } catch (err) {
      setError('Failed to delete video resource')
      console.error('Error deleting video:', err)
    } finally {
      setLoading(false)
    }
  }

  const fileInputs = [
    { key: 'video', label: 'Video File (MP4)', accept: '.mp4,video/mp4', required: !editingId },
    { key: 'transcript', label: 'Transcript (TXT)', accept: '.txt,text/plain' },
    { key: 'vtt', label: 'VTT Subtitles', accept: '.vtt' },
    { key: 'labels', label: 'Labels (JSON)', accept: '.json,application/json' },
    { key: 'sampleNotes', label: 'Sample Notes (TXT)', accept: '.txt,text/plain' },
    { key: 'cover', label: 'Cover Image', accept: 'image/*' },
    { key: 'flag', label: 'Country Flag', accept: 'image/*' }
  ]

  return (
    <div style={styles.container}>
      <div style={styles.header}>
        <h1 style={styles.title}>Video Resource Manager</h1>
        <p style={styles.subtitle}>Manage Listening and Understand / Listening and Respond video resources</p>
      </div>

      {error && (
        <div style={styles.errorAlert}>
          <span style={styles.alertIcon}>⚠️</span>
          {error}
          <button style={styles.closeButton} onClick={() => setError(null)}>×</button>
        </div>
      )}

      {success && (
        <div style={styles.successAlert}>
          <span style={styles.alertIcon}>✓</span>
          {success}
          <button style={styles.closeButton} onClick={() => setSuccess(null)}>×</button>
        </div>
      )}

      {/* 表单区域 */}
      <div style={styles.formSection}>
        <h2 style={styles.sectionTitle}>
          {editingId ? 'Edit Video Resource' : 'Add New Video Resource'}
        </h2>
        
        <form onSubmit={handleSubmit} style={styles.form}>
          <div style={styles.formGrid}>
            {/* 基本信息 */}
            <div style={styles.formGroup}>
              <label style={styles.label}>Mode *</label>
              <select 
                name="mode" 
                value={formData.mode} 
                onChange={handleInputChange}
                style={styles.select}
                disabled={editingId}
              >
                <option value="understand">Listening and Understand</option>
                <option value="respond">Listening and Respond</option>
              </select>
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>Video ID</label>
              <input 
                type="text" 
                value={editingId || (formData.mode ? generateVideoId() : 'Auto-generated')}
                style={{...styles.input, backgroundColor: 'var(--bg-2)'}}
                disabled
              />
            </div>

            <div style={styles.formGroup} style={{...styles.formGroup, gridColumn: 'span 2'}}>
              <label style={styles.label}>Title *</label>
              <input 
                type="text" 
                name="title" 
                value={formData.title} 
                onChange={handleInputChange}
                style={styles.input}
                placeholder="Enter video title"
              />
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>Type</label>
              <select 
                name="type" 
                value={formData.type} 
                onChange={handleInputChange}
                style={styles.select}
              >
                <option value="Campus">Campus</option>
                <option value="Academic">Academic</option>
                <option value="Campus&Life">Campus & Life</option>
              </select>
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>Difficulty</label>
              <select 
                name="difficulty" 
                value={formData.difficulty} 
                onChange={handleInputChange}
                style={styles.select}
              >
                <option value="Easy">Easy</option>
                <option value="Medium">Medium</option>
                <option value="Hard">Hard</option>
              </select>
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>Duration</label>
              <input 
                type="text" 
                name="duration" 
                value={formData.duration} 
                onChange={handleInputChange}
                style={styles.input}
                placeholder="e.g., 2-3min"
              />
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>Source</label>
              <input 
                type="text" 
                name="source" 
                value={formData.source} 
                onChange={handleInputChange}
                style={styles.input}
                placeholder="e.g., ELLLO"
              />
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>Country</label>
              <input 
                type="text" 
                name="country" 
                value={formData.country} 
                onChange={handleInputChange}
                style={styles.input}
                placeholder="e.g., US, UK, Australia"
              />
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>Author</label>
              <input 
                type="text" 
                name="author" 
                value={formData.author} 
                onChange={handleInputChange}
                style={styles.input}
                placeholder="Speaker name"
              />
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>Time Specific</label>
              <input 
                type="text" 
                name="time_specific" 
                value={formData.time_specific} 
                onChange={handleInputChange}
                style={styles.input}
                placeholder="e.g., 2024"
              />
            </div>

            {formData.mode === 'respond' && (
              <div style={styles.formGroup} style={{...styles.formGroup, gridColumn: 'span 2'}}>
                <label style={styles.label}>Question (for Respond mode)</label>
                <input 
                  type="text" 
                  name="question" 
                  value={formData.question} 
                  onChange={handleInputChange}
                  style={styles.input}
                  placeholder="Enter the question for this video"
                />
              </div>
            )}
          </div>

          {/* 文件上传区域 */}
          <div style={styles.fileSection}>
            <h3 style={styles.fileSectionTitle}>Resource Files</h3>
            <div style={styles.fileGrid}>
              {fileInputs.map(({ key, label, accept, required }) => (
                <div key={key} style={styles.fileInputGroup}>
                  <label style={styles.fileLabel}>
                    {label} {required && <span style={styles.required}>*</span>}
                    {files[key] && <span style={styles.fileSelected}> ✓</span>}
                  </label>
                  <div style={styles.fileUploadWrapper}>
                    <input 
                      ref={fileRefs[key]}
                      type="file" 
                      accept={accept}
                      onChange={(e) => handleFileChange(key, e)}
                      style={styles.fileInputHidden}
                      id={`file-${key}`}
                    />
                    <label htmlFor={`file-${key}`} style={styles.fileUploadButton}>
                      Choose File
                    </label>
                    <span style={styles.fileName}>
                      {files[key] ? files[key].name : 'No file selected'}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* 文本内容区域 */}
          <div style={styles.textSection}>
            <h3 style={styles.fileSectionTitle}>Text Content</h3>
            
            <div style={styles.formGroup}>
              <label style={styles.label}>Transcript</label>
              <textarea 
                name="transcript_text" 
                value={formData.transcript_text} 
                onChange={handleInputChange}
                style={{...styles.textarea, minHeight: '150px'}}
                placeholder="Paste transcript text here..."
              />
            </div>

            {formData.mode === 'respond' && (
              <div style={styles.formGroup}>
                <label style={styles.label}>Sample Answer</label>
                <textarea 
                  name="answer_text" 
                  value={formData.answer_text} 
                  onChange={handleInputChange}
                  style={{...styles.textarea, minHeight: '100px'}}
                  placeholder="Paste sample answer here..."
                />
              </div>
            )}
          </div>

          {/* 按钮 */}
          <div style={styles.buttonGroup}>
            <button 
              type="submit" 
              style={styles.submitButton}
              disabled={uploading}
            >
              {uploading ? 'Uploading...' : (editingId ? 'Update Resource' : 'Create Resource')}
            </button>
            {editingId && (
              <button 
                type="button" 
                style={styles.cancelButton}
                onClick={resetForm}
                disabled={uploading}
              >
                Cancel
              </button>
            )}
          </div>
        </form>
      </div>

      {/* 视频列表 */}
      <div style={styles.listSection}>
        <h2 style={styles.sectionTitle}>Existing Video Resources ({videos.length})</h2>
        
        {loading ? (
          <div style={styles.loading}>Loading...</div>
        ) : videos.length === 0 ? (
          <div style={styles.empty}>No video resources found</div>
        ) : (
          <div style={styles.videoList}>
            {videos.map(video => (
              <div key={video.id} style={styles.videoCard}>
                <div style={styles.videoHeader}>
                  <span style={styles.videoId}>{video.id}</span>
                  <span style={{...styles.modeBadge, 
                    backgroundColor: video.mode === 'understand' ? '#dbeafe' : '#dcfce7',
                    color: video.mode === 'understand' ? '#1e40af' : '#166534'
                  }}>
                    {video.mode === 'understand' ? 'Understand' : 'Respond'}
                  </span>
                  <span style={styles.difficultyBadge}>{video.difficulty}</span>
                </div>
                
                <h3 style={styles.videoTitle}>{video.title}</h3>
                
                <div style={styles.videoMeta}>
                  <span>{video.type}</span>
                  <span>•</span>
                  <span>{video.duration || 'N/A'}</span>
                  <span>•</span>
                  <span>{video.country || 'N/A'}</span>
                  <span>•</span>
                  <span>{video.author || 'N/A'}</span>
                </div>

                <div style={styles.videoFiles}>
                  {video.videoUrl && <span style={styles.fileTag}>🎬 Video</span>}
                  {video.transcriptUrl && <span style={styles.fileTag}>📝 Transcript</span>}
                  {video.vttUrl && <span style={styles.fileTag}>📄 VTT</span>}
                  {video.labelsUrl && <span style={styles.fileTag}>🏷️ Labels</span>}
                  {video.sampleNotesUrl && <span style={styles.fileTag}>📋 Notes</span>}
                  {video.coverUrl && <span style={styles.fileTag}>🖼️ Cover</span>}
                  {video.flagUrl && <span style={styles.fileTag}>🏳️ Flag</span>}
                </div>
                
                <div style={styles.videoActions}>
                  <button 
                    style={styles.editButton}
                    onClick={() => handleEdit(video)}
                  >
                    Edit
                  </button>
                  <button 
                    style={styles.deleteButton}
                    onClick={() => handleDelete(video.id)}
                  >
                    Delete
                  </button>
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
    maxWidth: '90%',
    margin: '0 auto',
    padding: '32px 24px',
    backgroundColor: '#f5f5f5',
    minHeight: '100vh'
  },
  header: {
    marginBottom: '32px',
    textAlign: 'center'
  },
  title: {
    fontSize: '36px',
    fontWeight: '700',
    color: '#1e3a5f',
    margin: '0 0 12px 0',
    fontFamily: 'system-ui, -apple-system, sans-serif'
  },
  subtitle: {
    fontSize: '16px',
    color: '#1e3a5f',
    margin: 0,
    opacity: 0.7
  },
  errorAlert: {
    backgroundColor: '#fee2e2',
    color: '#991b1b',
    padding: '16px 20px',
    borderRadius: '8px',
    marginBottom: '24px',
    display: 'flex',
    alignItems: 'center',
    gap: '12px',
    fontSize: '14px',
    border: '1px solid #fecaca'
  },
  successAlert: {
    backgroundColor: '#dcfce7',
    color: '#166534',
    padding: '16px 20px',
    borderRadius: '8px',
    marginBottom: '24px',
    display: 'flex',
    alignItems: 'center',
    gap: '12px',
    fontSize: '14px',
    border: '1px solid #bbf7d0'
  },
  alertIcon: {
    fontSize: '18px'
  },
  closeButton: {
    marginLeft: 'auto',
    background: 'none',
    border: 'none',
    fontSize: '20px',
    cursor: 'pointer',
    color: 'inherit',
    padding: '0 4px'
  },
  formSection: {
    backgroundColor: '#ffffff',
    border: '1px solid #e5e5e5',
    borderRadius: '16px',
    padding: '32px',
    marginBottom: '32px',
    boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
  },
  sectionTitle: {
    fontSize: '24px',
    fontWeight: '600',
    color: '#1e3a5f',
    margin: '0 0 24px 0',
    paddingBottom: '16px',
    borderBottom: '2px solid #e8eef5'
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    gap: '28px'
  },
  formGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(3, 1fr)',
    gap: '20px'
  },
  formGroup: {
    display: 'flex',
    flexDirection: 'column',
    gap: '8px'
  },
  label: {
    fontSize: '14px',
    fontWeight: '600',
    color: '#1e3a5f',
    textTransform: 'uppercase',
    letterSpacing: '0.5px'
  },
  input: {
    padding: '12px 16px',
    borderRadius: '10px',
    border: '1px solid #ddd',
    backgroundColor: '#fafafa',
    color: '#333',
    fontSize: '15px',
    fontFamily: 'system-ui, -apple-system, sans-serif',
    transition: 'all 0.2s',
    outline: 'none'
  },
  select: {
    padding: '12px 16px',
    borderRadius: '10px',
    border: '1px solid #ddd',
    backgroundColor: '#fafafa',
    color: '#333',
    fontSize: '15px',
    fontFamily: 'system-ui, -apple-system, sans-serif',
    cursor: 'pointer',
    outline: 'none'
  },
  textarea: {
    padding: '12px 16px',
    borderRadius: '10px',
    border: '1px solid #ddd',
    backgroundColor: '#fafafa',
    color: '#333',
    fontSize: '15px',
    fontFamily: 'system-ui, -apple-system, sans-serif',
    resize: 'vertical',
    minHeight: '120px',
    outline: 'none'
  },
  fileSection: {
    backgroundColor: '#f8f9fa',
    borderRadius: '12px',
    padding: '24px',
    border: '1px solid #e9ecef'
  },
  fileSectionTitle: {
    fontSize: '18px',
    fontWeight: '600',
    color: '#1e3a5f',
    margin: '0 0 20px 0'
  },
  fileGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(4, 1fr)',
    gap: '16px'
  },
  fileInputGroup: {
    display: 'flex',
    flexDirection: 'column',
    gap: '8px'
  },
  fileLabel: {
    fontSize: '13px',
    fontWeight: '600',
    color: '#1e3a5f'
  },
  required: {
    color: '#dc2626'
  },
  fileSelected: {
    color: '#16a34a',
    fontWeight: '600'
  },
  fileUploadWrapper: {
    display: 'flex',
    alignItems: 'center',
    gap: '12px',
    padding: '8px',
    borderRadius: '8px',
    border: '1px solid #ddd',
    backgroundColor: '#fff'
  },
  fileInputHidden: {
    display: 'none'
  },
  fileUploadButton: {
    padding: '8px 16px',
    borderRadius: '6px',
    border: '1px solid #1e3a5f',
    backgroundColor: '#1e3a5f',
    color: '#fff',
    fontSize: '13px',
    fontWeight: '500',
    cursor: 'pointer',
    transition: 'all 0.2s',
    whiteSpace: 'nowrap'
  },
  fileName: {
    fontSize: '13px',
    color: '#666',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  },
  textSection: {
    display: 'flex',
    flexDirection: 'column',
    gap: '20px'
  },
  buttonGroup: {
    display: 'flex',
    gap: '16px',
    marginTop: '16px',
    justifyContent: 'flex-start'
  },
  submitButton: {
    padding: '14px 32px',
    borderRadius: '10px',
    border: 'none',
    backgroundColor: '#2563eb',
    color: '#fff',
    fontSize: '16px',
    fontWeight: '600',
    cursor: 'pointer',
    fontFamily: 'system-ui, -apple-system, sans-serif',
    boxShadow: '0 2px 4px rgba(37, 99, 235, 0.3)',
    transition: 'all 0.2s'
  },
  cancelButton: {
    padding: '14px 32px',
    borderRadius: '10px',
    border: '1px solid #ddd',
    backgroundColor: '#fff',
    color: '#666',
    fontSize: '16px',
    fontWeight: '600',
    cursor: 'pointer',
    fontFamily: 'system-ui, -apple-system, sans-serif',
    transition: 'all 0.2s'
  },
  listSection: {
    backgroundColor: '#ffffff',
    border: '1px solid #e5e5e5',
    borderRadius: '16px',
    padding: '32px',
    boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
  },
  loading: {
    textAlign: 'center',
    padding: '48px',
    color: '#666',
    fontSize: '16px'
  },
  empty: {
    textAlign: 'center',
    padding: '48px',
    color: '#999',
    fontSize: '16px'
  },
  videoList: {
    display: 'grid',
    gridTemplateColumns: 'repeat(2, 1fr)',
    gap: '20px'
  },
  videoCard: {
    backgroundColor: '#f8f9fa',
    borderRadius: '12px',
    padding: '20px',
    border: '1px solid #e9ecef',
    transition: 'all 0.2s'
  },
  videoHeader: {
    display: 'flex',
    alignItems: 'center',
    gap: '10px',
    marginBottom: '12px'
  },
  videoId: {
    fontSize: '13px',
    fontWeight: '700',
    color: '#666',
    fontFamily: 'monospace',
    backgroundColor: '#e9ecef',
    padding: '4px 8px',
    borderRadius: '4px'
  },
  modeBadge: {
    padding: '4px 12px',
    borderRadius: '999px',
    fontSize: '12px',
    fontWeight: '600'
  },
  difficultyBadge: {
    padding: '4px 12px',
    borderRadius: '999px',
    fontSize: '12px',
    fontWeight: '600',
    backgroundColor: '#e9ecef',
    color: '#495057'
  },
  videoTitle: {
    fontSize: '18px',
    fontWeight: '600',
    color: '#1e3a5f',
    margin: '0 0 12px 0',
    lineHeight: '1.4'
  },
  videoMeta: {
    display: 'flex',
    alignItems: 'center',
    gap: '12px',
    fontSize: '14px',
    color: '#666',
    marginBottom: '16px',
    flexWrap: 'wrap'
  },
  videoFiles: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '8px',
    marginBottom: '16px'
  },
  fileTag: {
    padding: '4px 10px',
    borderRadius: '6px',
    fontSize: '12px',
    backgroundColor: '#e3f2fd',
    color: '#1976d2',
    fontWeight: '500'
  },
  videoActions: {
    display: 'flex',
    gap: '12px'
  },
  editButton: {
    padding: '8px 16px',
    borderRadius: '8px',
    border: '1px solid #2563eb',
    backgroundColor: '#fff',
    color: '#2563eb',
    fontSize: '14px',
    fontWeight: '600',
    cursor: 'pointer',
    transition: 'all 0.2s'
  },
  deleteButton: {
    padding: '8px 16px',
    borderRadius: '8px',
    border: '1px solid #dc2626',
    backgroundColor: '#fff',
    color: '#dc2626',
    fontSize: '14px',
    fontWeight: '600',
    cursor: 'pointer',
    transition: 'all 0.2s'
  }
}

export default VideoResourceManager
