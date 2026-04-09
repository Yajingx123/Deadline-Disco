import { useEffect, useMemo, useRef, useState } from 'react'
import { uploadForumAsset } from '../api/forumApi'

const EMOJI_GROUPS = [
  ['😀', '😄', '😁', '🙂', '😊', '🥹', '😉', '😍'],
  ['🤝', '👏', '🙌', '🔥', '🎯', '💡', '📚', '💻'],
  ['🎧', '🎤', '📝', '📎', '🫶', '🥳', '😎', '🤔'],
]

function formatRecordingDuration(totalSeconds) {
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}

export default function MessageComposer({ disabled = false, onSend }) {
  const [content, setContent] = useState('')
  const [error, setError] = useState('')
  const [isEmojiOpen, setIsEmojiOpen] = useState(false)
  const [isRecording, setIsRecording] = useState(false)
  const [isRecorderBusy, setIsRecorderBusy] = useState(false)
  const [recordingSeconds, setRecordingSeconds] = useState(0)

  const textareaRef = useRef(null)
  const imageInputRef = useRef(null)
  const audioInputRef = useRef(null)
  const mediaRecorderRef = useRef(null)
  const mediaStreamRef = useRef(null)
  const recordedChunksRef = useRef([])

  const emojiList = useMemo(() => EMOJI_GROUPS.flat(), [])

  useEffect(() => {
    if (!isRecording) {
      setRecordingSeconds(0)
      return undefined
    }
    const intervalId = window.setInterval(() => {
      setRecordingSeconds((prev) => prev + 1)
    }, 1000)
    return () => window.clearInterval(intervalId)
  }, [isRecording])

  useEffect(() => () => {
    if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
      mediaRecorderRef.current.stop()
    }
    if (mediaStreamRef.current) {
      mediaStreamRef.current.getTracks().forEach((track) => track.stop())
    }
  }, [])

  const insertText = (text) => {
    const textarea = textareaRef.current
    if (!textarea) {
      setContent((prev) => `${prev}${text}`)
      return
    }
    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const nextValue = `${content.slice(0, start)}${text}${content.slice(end)}`
    setContent(nextValue)
    window.requestAnimationFrame(() => {
      textarea.focus()
      const cursor = start + text.length
      textarea.setSelectionRange(cursor, cursor)
    })
  }

  const insertMarkupBlock = (markup) => {
    const prefix = content.trim() ? '\n' : ''
    insertText(`${prefix}${markup}\n`)
  }

  const handleImageUpload = async (event) => {
    const file = event.target.files?.[0]
    if (!file || !file.type.startsWith('image/')) {
      return
    }
    try {
      setError('')
      const uploaded = await uploadForumAsset(file, 'image')
      insertMarkupBlock(`![${uploaded.fileName}](${uploaded.url})`)
    } catch (err) {
      setError(err?.message || 'Failed to upload image.')
    }
    event.target.value = ''
  }

  const handleAudioUpload = async (event) => {
    const file = event.target.files?.[0]
    if (!file || !file.type.startsWith('audio/')) {
      setError('Please choose a valid audio file.')
      return
    }
    try {
      setError('')
      const uploaded = await uploadForumAsset(file, 'audio')
      insertMarkupBlock(`![audio:${uploaded.fileName}](${uploaded.url})`)
    } catch (err) {
      setError(err?.message || 'Failed to upload audio.')
    }
    event.target.value = ''
  }

  const stopRecordingStream = () => {
    if (mediaStreamRef.current) {
      mediaStreamRef.current.getTracks().forEach((track) => track.stop())
      mediaStreamRef.current = null
    }
  }

  const handleRecordAudio = async () => {
    if (disabled || isRecorderBusy) {
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
      setError('')
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

      recorder.ondataavailable = (event) => {
        if (event.data && event.data.size > 0) {
          recordedChunksRef.current.push(event.data)
        }
      }

      recorder.onstop = async () => {
        const fallbackType = recorder.mimeType || 'audio/webm'
        const extension = fallbackType.includes('ogg') ? 'ogg' : fallbackType.includes('mp4') ? 'm4a' : 'webm'
        const blob = new Blob(recordedChunksRef.current, { type: fallbackType })
        const file = new File([blob], `recording-${Date.now()}.${extension}`, { type: fallbackType })

        try {
          const uploaded = await uploadForumAsset(file, 'audio')
          insertMarkupBlock(`![audio:${uploaded.fileName}](${uploaded.url})`)
        } catch (err) {
          setError(err?.message || 'Failed to upload recorded audio.')
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

  const handleSend = async () => {
    if (!content.trim() || disabled) {
      return
    }
    try {
      setError('')
      await onSend(content.replace(/\s+$/g, ''))
      setContent('')
      setIsEmojiOpen(false)
      window.requestAnimationFrame(() => textareaRef.current?.focus())
    } catch (err) {
      setError(err?.message || 'Failed to send message.')
    }
  }

  const handleKeyDown = async (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault()
      await handleSend()
    }
  }

  return (
    <div className="chat-composer">
      {error && <div className="chat-composer__error">{error}</div>}

      {(isRecording || isRecorderBusy) && (
        <div className={`chat-composer__recording ${isRecording ? 'is-live' : 'is-processing'}`}>
          {isRecording ? `Recording ${formatRecordingDuration(recordingSeconds)}` : 'Uploading audio clip...'}
        </div>
      )}

      <div className="chat-composer__panel">
        <textarea
          ref={textareaRef}
          className="chat-composer__textarea"
          placeholder="Write a message"
          value={content}
          onChange={(event) => setContent(event.target.value)}
          onKeyDown={handleKeyDown}
          disabled={disabled || isRecorderBusy}
          rows={2}
        />

        <div className="chat-composer__toolbar">
          <button type="button" className="chat-composer__tool" onClick={() => imageInputRef.current?.click()} disabled={disabled || isRecorderBusy}>
            Picture
          </button>
          <button type="button" className="chat-composer__tool" onClick={() => audioInputRef.current?.click()} disabled={disabled || isRecorderBusy}>
            Audio
          </button>
          <button type="button" className={`chat-composer__tool ${isRecording ? 'is-recording' : ''}`} onClick={handleRecordAudio} disabled={disabled || isRecorderBusy}>
            {isRecording ? 'Stop' : 'Record'}
          </button>
          <div className="chat-composer__emojiWrap">
            <button type="button" className="chat-composer__tool" onClick={() => setIsEmojiOpen((prev) => !prev)} disabled={disabled || isRecorderBusy}>
              Emoji
            </button>
            {isEmojiOpen && (
              <div className="chat-composer__emojiMenu">
                {emojiList.map((emoji) => (
                  <button
                    key={emoji}
                    type="button"
                    className="chat-composer__emojiItem"
                    onClick={() => insertText(emoji)}
                  >
                    {emoji}
                  </button>
                ))}
              </div>
            )}
          </div>
          <button type="button" className="chat-composer__send" onClick={handleSend} disabled={disabled || !content.trim() || isRecorderBusy}>
            Send
          </button>
        </div>
      </div>

      <input ref={imageInputRef} type="file" accept="image/*" onChange={handleImageUpload} hidden />
      <input ref={audioInputRef} type="file" accept="audio/*" onChange={handleAudioUpload} hidden />
    </div>
  )
}
