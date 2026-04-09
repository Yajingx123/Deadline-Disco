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

    // 以下是数据库真实字段 ↓
    video_url: '',
    transcript_url: '',
    vtt_url: '',
    labels_url: '',
    sample_notes_url: '',
    cover_url: '',
    flag_url: '',

    transcript_text: '',
    question: '',
    answer_text: '',
    sort_order: '',
    status: 'active'
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
      // 👇 直接用原生 fetch，不经过任何封装！
      const rawResponse = await fetch('/Academic-Practice/api/video_resources.php?action=list')
      console.log("📶 HTTP 状态码：", rawResponse.status)

      const response = await rawResponse.json()
      console.log("✅ 真实后端返回：", response) // 看这里！看这里！

      // 正确读取数据（不管后端是什么结构，我们都能拿到）
      let data = []
      if (response.video_resources) {
        data = Object.values(response.video_resources)
      } else if (Array.isArray(response)) {
        data = response
      } else if (response.data) {
        data = response.data
      }

      setVideos(data)
    } catch (err) {
      console.error("❌ 请求失败：", err)
      setError("无法连接服务器：" + err.message)
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
    const file = e.target.files[0];
    if (!file) return;

    // 先保存文件
    setFiles(prev => ({
      ...prev,
      [type]: file
    }));

    // ==============================================
    // 上传视频 → 自动填标题 + 时长（已正常工作）
    // ==============================================
    if (type === 'video') {
      const rawTitle = file.name.replace(/\.[^/.]+$/, "");
      setFormData(prev => ({
        ...prev,
        title: rawTitle
      }));

      const video = document.createElement('video');
      video.preload = 'metadata';

      video.onloadedmetadata = function () {
        window.URL.revokeObjectURL(video.src);
        const seconds = video.duration;
        const minutes = (seconds / 60).toFixed(1);
        const durationText = `${minutes}min`;

        setFormData(prev => ({
          ...prev,
          duration: durationText
        }));
      };

      video.src = URL.createObjectURL(file);
      video.load();
    }

    // ==============================================
    // 上传 transcript → 自动填文本（已正常工作）
    // ==============================================
    if (type === 'transcript') {
      const reader = new FileReader();
      reader.onload = (event) => {
        setFormData(prev => ({
          ...prev,
          transcript_text: event.target.result
        }));
      };
      reader.readAsText(file);
    }

    // ==============================================
    // ✅ 终极修复：完美匹配你当前的 JSON 结构
    // ==============================================
    if (type === 'labels') {
      const reader = new FileReader();
      reader.onload = (event) => {
        try {
          const json = JSON.parse(event.target.result);
          console.log("✅ JSON 解析成功", json);

          setFormData(prev => ({
            ...prev,
            mode: json.mode === "Listening and Understand" ? "understand" : json.mode || prev.mode,
            title: json.title ?? prev.title,
            type: json.type ?? prev.type,
            difficulty: json.difficulty ?? prev.difficulty,
            duration: json.duration ?? prev.duration,
            source: json.source ?? prev.source,
            country: json.country ?? prev.country,
            author: json.author ?? prev.author,
            time_specific: json.timeSpecific ?? prev.time_specific, // 必识别
            transcript_text: json.transcript ?? json.transcript_text ?? json.transcriptText ?? prev.transcript_text, // ✅ 必识别
            question: json.question ?? prev.question,
            answer_text: json.answer_text ?? json.answerText ?? prev.answer_text
          }));

        } catch (err) {
          console.error("❌ JSON 格式错误", err);
          alert("JSON 格式不正确！");
        }
      };
      reader.readAsText(file);
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

      // ✅ 强制先生成 video_id
      const newId = generateVideoId()
      console.log("✅ 最终提交的 video_id：", newId)

      // 添加所有表单字段
      Object.keys(formData).forEach(key => {
        submitData.append(key, formData[key])
      })

      // ✅ 强制写入 video_id，确保后端一定收到
      submitData.append('video_id', newId)

      // 添加文件
      Object.keys(files).forEach(key => {
        if (files[key]) {
          submitData.append(key + '_file', files[key])
        }
      })

      const url = '/Academic-Practice/api/video_resources.php?action=create'

      const response = await fetch(url, {
        method: 'POST',
        body: submitData
      })

      const result = await response.json()
      console.log("后端返回：", result)

      if (!result.ok) throw new Error(result.error)

      setSuccess('Upload the resource successfully！')
      resetForm()
      setTimeout(fetchVideos, 1000)
      setTimeout(() => setSuccess(null), 3000)

    } catch (err) {
      setError('失败：' + err.message)
      console.error(err)
    } finally {
      setUploading(false)
    }
  }

  function handleEdit(video) {
    setEditingId(video.video_id)
    setFormData({
      video_id: video.video_id,
      mode: video.mode,
      title: video.title,
      type: video.type,
      difficulty: video.difficulty,
      duration: video.duration,
      source: video.source,
      country: video.country,
      author: video.author,
      time_specific: video.time_specific,

      video_url: video.video_url || '',
      transcript_url: video.transcript_url || '',
      vtt_url: video.vtt_url || '',
      labels_url: video.labels_url || '',
      sample_notes_url: video.sample_notes_url || '',
      cover_url: video.cover_url || '',
      flag_url: video.flag_url || '',

      transcript_text: video.transcript_text || '',
      question: video.question || '',
      answer_text: video.answer_text || '',
      sort_order: video.sort_order || '',
      status: video.status || 'active'
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

      video_url: '',
      transcript_url: '',
      vtt_url: '',
      labels_url: '',
      sample_notes_url: '',
      cover_url: '',
      flag_url: '',

      transcript_text: '',
      question: '',
      answer_text: '',
      sort_order: '',
      status: 'active'
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
      await adminFetch(`/Academic-Practice/api/video_resources.php?action=delete&id=${id}`, {
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
    { key: 'labels', label: 'Labels File (JSON Only)', accept: '.json,application/json', required: false }
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
                style={{ ...styles.input, backgroundColor: 'var(--bg-2)' }}
                disabled
              />
            </div>

            <div style={{ ...styles.formGroup, gridColumn: 'span 2' }}>
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

            <div style={{ ...styles.formGroup, gridColumn: 'span 3' }}>
              <label style={styles.label}>Transcript</label>
              <textarea
                name="transcript_text"
                value={formData.transcript_text}
                onChange={handleInputChange}
                style={{ ...styles.textarea, minHeight: '180px' }}
                placeholder="Paste transcript text here..."
              />
            </div>

            {formData.mode === 'respond' && (
              <div style={{ ...styles.formGroup, gridColumn: 'span 2' }}>
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
            <h3 style={styles.fileSectionTitle}>Upload Labels (JSON Only)</h3>
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
              <div key={video.video_id} style={styles.videoCard}>
                <div style={styles.videoHeader}>
                  <span style={styles.videoId}>{video.video_id}</span>
                  <span style={{
                    ...styles.modeBadge,
                    backgroundColor: video.mode === 'understand' ? 'rgba(250, 204, 21, 0.28)' : 'rgba(91, 42, 134, 0.14)',
                    color: '#5b2a86'
                  }}>
                    {video.mode === 'understand' ? 'Understand' : 'Respond'}
                  </span>
                  <span style={styles.difficultyBadge}>{video.difficulty}</span>
                </div>

                <h3 style={styles.videoTitle}>{video.title}</h3>

                <div style={styles.videoMeta}>
                  <span>{video.mode}</span>
                  <span>{video.difficulty}</span>
                  <span>{video.type}</span>
                  <span>{video.duration}</span>
                  <span>{video.country}</span>
                </div>

                <div style={styles.videoFiles}>
                  {video.video_url && <span>🎬 Video</span>}
                  {video.transcript_url && <span>📝 Transcript</span>}
                  {video.vtt_url && <span>📄 VTT</span>}
                  {video.labels_url && <span>🏷️ Labels</span>}
                  {video.cover_url && <span>🖼️ Cover</span>}
                  {video.flag_url && <span>🏳️ Flag</span>}
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
                    onClick={() => handleDelete(video.video_id)}
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
    backgroundColor: 'transparent',
    minHeight: '100vh'
  },
  header: {
    marginBottom: '32px',
    textAlign: 'center'
  },
  title: {
    fontSize: '36px',
    fontWeight: '700',
    color: '#5b2a86',
    margin: '0 0 12px 0',
    fontFamily: 'var(--sans), system-ui, -apple-system, sans-serif'
  },
  subtitle: {
    fontSize: '16px',
    color: '#5b2a86',
    margin: 0,
    opacity: 0.75
  },
  errorAlert: {
    backgroundColor: 'rgba(184, 76, 92, 0.12)',
    color: '#8b2940',
    padding: '16px 20px',
    borderRadius: '8px',
    marginBottom: '24px',
    display: 'flex',
    alignItems: 'center',
    gap: '12px',
    fontSize: '14px',
    border: '1px solid rgba(184, 76, 92, 0.28)'
  },
  successAlert: {
    backgroundColor: 'rgba(250, 204, 21, 0.18)',
    color: '#5b2a86',
    padding: '16px 20px',
    borderRadius: '8px',
    marginBottom: '24px',
    display: 'flex',
    alignItems: 'center',
    gap: '12px',
    fontSize: '14px',
    border: '1px solid rgba(250, 204, 21, 0.45)'
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
    backgroundColor: 'rgba(255, 255, 255, 0.92)',
    border: '1px solid rgba(91, 42, 134, 0.14)',
    borderRadius: '16px',
    padding: '32px',
    marginBottom: '32px',
    boxShadow: '0 12px 40px rgba(91, 42, 134, 0.08)'
  },
  sectionTitle: {
    fontSize: '24px',
    fontWeight: '600',
    color: '#5b2a86',
    margin: '0 0 24px 0',
    paddingBottom: '16px',
    borderBottom: '2px solid rgba(91, 42, 134, 0.14)'
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
    color: '#5b2a86',
    textTransform: 'uppercase',
    letterSpacing: '0.5px'
  },
  input: {
    padding: '12px 16px',
    borderRadius: '10px',
    border: '1px solid rgba(91, 42, 134, 0.18)',
    backgroundColor: '#faf8ff',
    color: '#3d1f5c',
    fontSize: '15px',
    fontFamily: 'system-ui, -apple-system, sans-serif',
    transition: 'all 0.2s',
    outline: 'none'
  },
  select: {
    padding: '12px 16px',
    borderRadius: '10px',
    border: '1px solid rgba(91, 42, 134, 0.18)',
    backgroundColor: '#faf8ff',
    color: '#3d1f5c',
    fontSize: '15px',
    fontFamily: 'system-ui, -apple-system, sans-serif',
    cursor: 'pointer',
    outline: 'none'
  },
  textarea: {
    padding: '12px 16px',
    borderRadius: '10px',
    border: '1px solid rgba(91, 42, 134, 0.18)',
    backgroundColor: '#faf8ff',
    color: '#3d1f5c',
    fontSize: '15px',
    fontFamily: 'system-ui, -apple-system, sans-serif',
    resize: 'vertical',
    minHeight: '120px',
    outline: 'none'
  },
  fileSection: {
    backgroundColor: 'rgba(248, 244, 255, 0.85)',
    borderRadius: '12px',
    padding: '24px',
    border: '1px solid rgba(91, 42, 134, 0.12)'
  },
  fileSectionTitle: {
    fontSize: '18px',
    fontWeight: '600',
    color: '#5b2a86',
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
    color: '#5b2a86'
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
    border: '1px solid rgba(91, 42, 134, 0.14)',
    backgroundColor: '#fff'
  },
  fileInputHidden: {
    display: 'none'
  },
  fileUploadButton: {
    padding: '8px 16px',
    borderRadius: '6px',
    border: '1px solid #5b2a86',
    backgroundColor: '#5b2a86',
    color: '#fff8ea',
    fontSize: '13px',
    fontWeight: '500',
    cursor: 'pointer',
    transition: 'all 0.2s',
    whiteSpace: 'nowrap'
  },
  fileName: {
    fontSize: '13px',
    color: 'rgba(91, 42, 134, 0.65)',
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
    background: 'linear-gradient(135deg, #5b2a86 0%, #6a33a0 100%)',
    color: '#fff8ea',
    fontSize: '16px',
    fontWeight: '600',
    cursor: 'pointer',
    fontFamily: 'system-ui, -apple-system, sans-serif',
    boxShadow: '0 4px 14px rgba(91, 42, 134, 0.28)',
    transition: 'all 0.2s'
  },
  cancelButton: {
    padding: '14px 32px',
    borderRadius: '10px',
    border: '1px solid rgba(91, 42, 134, 0.2)',
    backgroundColor: '#fff',
    color: 'rgba(91, 42, 134, 0.75)',
    fontSize: '16px',
    fontWeight: '600',
    cursor: 'pointer',
    fontFamily: 'system-ui, -apple-system, sans-serif',
    transition: 'all 0.2s'
  },
  listSection: {
    backgroundColor: 'rgba(255, 255, 255, 0.92)',
    border: '1px solid rgba(91, 42, 134, 0.14)',
    borderRadius: '16px',
    padding: '32px',
    boxShadow: '0 12px 40px rgba(91, 42, 134, 0.08)'
  },
  loading: {
    textAlign: 'center',
    padding: '48px',
    color: 'rgba(91, 42, 134, 0.65)',
    fontSize: '16px'
  },
  empty: {
    textAlign: 'center',
    padding: '48px',
    color: 'rgba(91, 42, 134, 0.5)',
    fontSize: '16px'
  },
  videoList: {
    display: 'grid',
    gridTemplateColumns: 'repeat(2, 1fr)',
    gap: '20px'
  },
  videoCard: {
    backgroundColor: 'rgba(248, 244, 255, 0.9)',
    borderRadius: '12px',
    padding: '20px',
    border: '1px solid rgba(91, 42, 134, 0.12)',
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
    color: '#5b2a86',
    fontFamily: 'monospace',
    backgroundColor: 'rgba(91, 42, 134, 0.1)',
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
    backgroundColor: 'rgba(250, 204, 21, 0.2)',
    color: '#5b2a86'
  },
  videoTitle: {
    fontSize: '18px',
    fontWeight: '600',
    color: '#5b2a86',
    margin: '0 0 12px 0',
    lineHeight: '1.4'
  },
  videoMeta: {
    display: 'flex',
    alignItems: 'center',
    gap: '12px',
    fontSize: '14px',
    color: 'rgba(91, 42, 134, 0.7)',
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
    backgroundColor: 'rgba(250, 204, 21, 0.22)',
    color: '#5b2a86',
    fontWeight: '500'
  },
  videoActions: {
    display: 'flex',
    gap: '12px'
  },
  editButton: {
    padding: '8px 16px',
    borderRadius: '8px',
    border: '1px solid #5b2a86',
    backgroundColor: '#fff',
    color: '#5b2a86',
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
