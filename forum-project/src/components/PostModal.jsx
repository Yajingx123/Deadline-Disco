// src/components/PostModal.jsx

import { useState, useRef, useEffect } from 'react';
import './PostModal.css';
import { uploadForumAsset } from '../api/forumApi';

function formatRecordingDuration(totalSeconds) {
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

const PostModal = ({ 
  isOpen, 
  onClose, 
  onSubmit, 
  isReplyMode = false, 
  quoteText = '',
  parentTitle = '',
  labelOptions = [],
  currentUser = null,
  prefillDraft = null,
}) => {
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [selectedTags, setSelectedTags] = useState([]); // 👇 2. 新增：选中的标签状态
  const [isPreview, setIsPreview] = useState(false);
  const [error, setError] = useState('');
  const [isRecording, setIsRecording] = useState(false);
  const [isRecorderBusy, setIsRecorderBusy] = useState(false);
  const [recordingSeconds, setRecordingSeconds] = useState(0);
  
  const editorRef = useRef(null);
  const fileInputRef = useRef(null);
  const audioInputRef = useRef(null);
  const mediaRecorderRef = useRef(null);
  const mediaStreamRef = useRef(null);
  const recordedChunksRef = useRef([]);
  
  // 初始化逻辑
  useEffect(() => {
    if (isOpen) {
      if (isReplyMode) {
        setTitle('');
        setContent('');
      } else {
        setTitle(prefillDraft?.title || '');
        setContent(prefillDraft?.content || '');
      }
      setSelectedTags([]); // 👇 重置标签
      setIsPreview(false);
      setError('');
      setIsRecording(false);
      setIsRecorderBusy(false);
      setRecordingSeconds(0);
      recordedChunksRef.current = [];

      const timer = setTimeout(() => {
        if (isReplyMode && editorRef.current) {
          editorRef.current.focus();
          return;
        }
        const input = document.getElementById('modal-title-input');
        if (input) input.focus();
      }, 100);
      
      document.body.classList.add('pm-modal-open');
      return () => {
        clearTimeout(timer);
        if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
          mediaRecorderRef.current.stop();
        }
        if (mediaStreamRef.current) {
          mediaStreamRef.current.getTracks().forEach((track) => track.stop());
          mediaStreamRef.current = null;
        }
        setRecordingSeconds(0);
        document.body.classList.remove('pm-modal-open');
      };
    }
  }, [isOpen, isReplyMode, parentTitle, prefillDraft]);

  useEffect(() => {
    if (!isRecording) {
      setRecordingSeconds(0);
      return undefined;
    }

    const intervalId = window.setInterval(() => {
      setRecordingSeconds((prev) => prev + 1);
    }, 1000);

    return () => window.clearInterval(intervalId);
  }, [isRecording]);

  // ... (中间的 helper 函数 createImagePlaceholder, decodeContentForSubmit, parseMarkdown 等保持不变) ...
  // 为了节省篇幅，这里省略未变动的 helper 函数，请保留你之前的代码
  const decodeContentForSubmit = (editText) => {
    return editText;
  };
  const parseMarkdown = (text) => {
    if (!text) return '';
    const realText = decodeContentForSubmit(text);
    let html = realText
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.*?)\*/g, '<em>$1</em>')
      .replace(/&lt;u&gt;(.*?)&lt;\/u&gt;/g, '<u>$1</u>');
    html = html.replace(/!\[(.*?)\]\((.*?)\)/g, (match, alt, src) => {
      if (alt.startsWith('audio:')) return match; 
      return `<img src="${src}" alt="${alt}" style="max-width: 100%; border-radius: 8px; margin: 8px 0;" />`;
    });
    html = html.replace(/!\[audio:(.*?)\]\((.*?)\)/g, (match, fileName, src) => {
      return `<div style="margin: 12px 0; padding: 10px; background: #f4f3ec; border-radius: 8px; border: 1px solid #e5e4e7;"><div style="font-size: 12px; color: #6b6375; margin-bottom: 6px;">🎵 ${fileName}</div><audio controls style="width: 100%; height: 32px;"><source src="${src}" type="audio/mpeg" /><source src="${src}" type="audio/wav" /><source src="${src}" type="audio/ogg" />Your browser does not support the audio element.</audio></div>`;
    });
    html = html.replace(/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
    html = html.replace(/\n/g, '<br/>');
    return html;
  };
  const handleFormat = (prefix, suffix = prefix) => {
    if (!editorRef.current) return;
    const textarea = editorRef.current;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = content;
    const before = text.substring(0, start);
    const after = text.substring(end, text.length);
    const selectedText = text.substring(start, end);
    const newContent = before + prefix + selectedText + suffix + after;
    setContent(newContent);
    setTimeout(() => {
      textarea.focus();
      textarea.setSelectionRange(start + prefix.length, end + prefix.length);
    }, 0);
  };
  const insertUploadedMarkup = (markup) => {
    const textarea = editorRef.current;
    if (!textarea) return;
    const start = textarea.selectionStart;
    const insertText = `\n${markup}\n`;
    const newText = content.substring(0, start) + insertText + content.substring(textarea.selectionEnd);
    setContent(newText);
    setTimeout(() => {
      textarea.focus();
      textarea.setSelectionRange(start + insertText.length, start + insertText.length);
    }, 0);
  };

  const handleImageUpload = async (e) => {
    const file = e.target.files[0];
    if (!file || !file.type.startsWith('image/')) return;
    try {
      setError('');
      const uploaded = await uploadForumAsset(file, 'image');
      insertUploadedMarkup(`![${uploaded.fileName}](${uploaded.url})`);
    } catch (err) {
      setError(err?.message || 'Failed to upload image.');
    }
    e.target.value = '';
  };
  const handleAudioUpload = async (e) => {
    const file = e.target.files[0];
    if (!file || !file.type.startsWith('audio/')) { setError('Please upload a valid audio file.'); return; }
    try {
      setError('');
      const uploaded = await uploadForumAsset(file, 'audio');
      insertUploadedMarkup(`![audio:${uploaded.fileName}](${uploaded.url})`);
    } catch (err) {
      setError(err?.message || 'Failed to upload audio.');
    }
    e.target.value = '';
  };

  const stopRecordingStream = () => {
    if (mediaStreamRef.current) {
      mediaStreamRef.current.getTracks().forEach((track) => track.stop());
      mediaStreamRef.current = null;
    }
  };

  const handleRecordAudio = async () => {
    if (isRecorderBusy) {
      return;
    }

    if (isRecording) {
      if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
        setIsRecorderBusy(true);
        mediaRecorderRef.current.stop();
      }
      return;
    }

    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function' || typeof MediaRecorder === 'undefined') {
      setError('This browser does not support direct audio recording.');
      return;
    }

    try {
      setError('');
      setIsRecorderBusy(true);
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      mediaStreamRef.current = stream;

      let mimeType = '';
      if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
        mimeType = 'audio/webm;codecs=opus';
      } else if (MediaRecorder.isTypeSupported('audio/webm')) {
        mimeType = 'audio/webm';
      }

      const recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
      mediaRecorderRef.current = recorder;
      recordedChunksRef.current = [];

      recorder.ondataavailable = (event) => {
        if (event.data && event.data.size > 0) {
          recordedChunksRef.current.push(event.data);
        }
      };

      recorder.onstop = async () => {
        const fallbackType = recorder.mimeType || 'audio/webm';
        const blob = new Blob(recordedChunksRef.current, { type: fallbackType });
        const extension = fallbackType.includes('ogg') ? 'ogg' : fallbackType.includes('mp4') ? 'm4a' : 'webm';
        const file = new File([blob], `recording-${Date.now()}.${extension}`, { type: fallbackType });

        try {
          const uploaded = await uploadForumAsset(file, 'audio');
          insertUploadedMarkup(`![audio:${uploaded.fileName}](${uploaded.url})`);
        } catch (err) {
          setError(err?.message || 'Failed to upload recorded audio.');
        } finally {
          recordedChunksRef.current = [];
          setIsRecording(false);
          setIsRecorderBusy(false);
          setRecordingSeconds(0);
          stopRecordingStream();
        }
      };

      recorder.onerror = () => {
        setError('Recording failed. Please try again.');
        setIsRecording(false);
        setIsRecorderBusy(false);
        setRecordingSeconds(0);
        stopRecordingStream();
      };

      recorder.start();
      setIsRecording(true);
      setIsRecorderBusy(false);
    } catch (err) {
      setError('Microphone access is required to record audio.');
      setIsRecording(false);
      setIsRecorderBusy(false);
      setRecordingSeconds(0);
      stopRecordingStream();
    }
  };
  const handlePaste = async (e) => {
     const items = e.clipboardData.items;
     for (let i = 0; i < items.length; i++) {
      if (items[i].type.indexOf('image') !== -1) {
        e.preventDefault();
        const file = items[i].getAsFile();
        if (!file) break;
        const pastedFile = new File([file], 'pasted-image.png', { type: file.type || 'image/png' });
        try {
          setError('');
          const uploaded = await uploadForumAsset(pastedFile, 'image');
          insertUploadedMarkup(`![${uploaded.fileName}](${uploaded.url})`);
        } catch (err) {
          setError(err?.message || 'Failed to upload pasted image.');
        }
        break;
      }
    }
  };

  // 👇 3. 新增：标签切换逻辑
  const toggleTag = (tag) => {
    if (selectedTags.includes(tag)) {
      setSelectedTags(selectedTags.filter(t => t !== tag));
    } else {
      // 限制最多选 3 个标签，避免太多
      if (selectedTags.length >= 3) {
        setError("You can select up to 3 labels.");
        return;
      }
      setSelectedTags([...selectedTags, tag]);
    }
  };

  const handleSubmit = async () => {
    if (!content.trim()) {
      setError('Content cannot be empty.');
      return;
    }
    if (!isReplyMode && !title.trim()) {
       setError('Title cannot be empty.');
       return;
    }
    setError('');

    const finalContent = decodeContentForSubmit(content);

    // 👇 4. 提交时包含 tags
    const newData = {
      id: Date.now(),
      title: title, 
      content: finalContent,
      labels: selectedTags,
      author: currentUser?.username || 'Current User',
      time: new Date().toLocaleDateString()
    };

    try {
      await onSubmit(newData);
      setTitle('');
      setContent('');
      setSelectedTags([]);
      setIsPreview(false);
      onClose();
    } catch (err) {
      setError(err?.message || 'Failed to submit. Please try again.');
    }
  };

  if (!isOpen) return null;

  return (
    <>
      <div className="post-modal-overlay" onClick={onClose} />
      <div className="post-modal-container">
        <div className="post-modal-header">
          <div className="post-modal-title-group">
            <button onClick={onClose} className="post-modal-cancel-btn">✕ Cancel</button>
            <h2 className="post-modal-title">
              {isReplyMode ? 'Reply to Post' : 'New Post'}
            </h2>
          </div>
          <div className="post-modal-draft-label" style={{ fontSize: '11px' }}>
            {isPreview ? 'PREVIEW' : 'EDIT'}
          </div>
        </div>

        <div className="post-modal-content-scroll">
          <div className="post-modal-card">
            
            {isReplyMode && (
              <div className="reply-info-banner">
                <div className="reply-target">Replying to: <strong>{parentTitle}</strong></div>
                {quoteText && (
                  <div className="reply-quote-preview">
                    <span className="quote-label">Quoted text:</span>
                    <div className="quote-text-short">
                      {quoteText.length > 100 ? quoteText.substring(0, 100) + '...' : quoteText}
                    </div>
                  </div>
                )}
              </div>
            )}

            <div className="post-modal-input-group">
              <div className="post-modal-avatar">{(currentUser?.username || 'Y').slice(0, 1).toUpperCase()}</div>
              <div className="post-modal-input-wrapper">
                {isReplyMode ? (
                  <div className="post-modal-reply-heading">{parentTitle}</div>
                ) : (
                  <input
                    id="modal-title-input"
                    type="text"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="Enter post title..."
                    className="post-modal-title-input"
                    disabled={isPreview}
                  />
                )}
              </div>
            </div>

            {/* 👇 5. 新增：标签选择区域 */}
            {!isPreview && !isReplyMode && (
              <div className="post-modal-tags-section">
                <label className="tags-label">Select labels (max 3)</label>
                <div className="tags-container">
                  {labelOptions.map(tag => (
                    <button
                      key={tag.id}
                      type="button"
                      onClick={() => toggleTag(tag.name)}
                      className={`tag-chip ${selectedTags.includes(tag.name) ? 'active' : ''}`}
                    >
                      {tag.name}
                    </button>
                  ))}
                </div>
                {selectedTags.length > 0 && (
                  <div className="selected-tags-summary">
                    Selected: <strong>{selectedTags.join(', ')}</strong>
                  </div>
                )}
              </div>
            )}

            {!!error && <div className="post-modal-error">{error}</div>}

            {(isRecording || isRecorderBusy) && (
              <div className={`post-modal-recorder-panel ${isRecording ? 'is-live' : 'is-processing'}`}>
                <div className="post-modal-recorder-panel__pulse" aria-hidden="true" />
                <div className="post-modal-recorder-panel__body">
                  <div className="post-modal-recorder-panel__eyebrow">
                    {isRecording ? 'Recording in progress' : 'Processing recording'}
                  </div>
                  <div className="post-modal-recorder-panel__title">
                    {isRecording ? `Mic is on · ${formatRecordingDuration(recordingSeconds)}` : 'Uploading audio clip...'}
                  </div>
                  <div className="post-modal-recorder-panel__hint">
                    {isRecording ? 'Click the stop button when you want to insert this audio into the reply.' : 'Please wait. The audio will be inserted into the editor automatically.'}
                  </div>
                </div>
              </div>
            )}

            <div className="post-modal-editor-container">
              {isPreview ? (
                <div 
                  className="post-modal-preview-content"
                  dangerouslySetInnerHTML={{ __html: parseMarkdown(content) }}
                />
              ) : (
                <textarea
                  ref={editorRef}
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                  onPaste={handlePaste}
                  placeholder={isReplyMode ? "Write your reply..." : "Say something..."}
                  className="post-modal-textarea"
                />
              )}
            </div>
          </div>
        </div>

        <div className="post-modal-footer">
          {!isPreview && (
            <div className="post-modal-format-group">
              {['B', 'I', 'U'].map((type, index) => (
                <button 
                  key={type}
                  onClick={() => {
                    if (index === 0) handleFormat('**');
                    if (index === 1) handleFormat('*');
                    if (index === 2) handleFormat('<u>', '</u>');
                  }}
                  className={`post-modal-format-btn ${index === 0 ? 'bold' : index === 1 ? 'italic' : index === 2 ? 'underline' : ''}`}
                >
                  {type}
                </button>
              ))}
              
              <button onClick={() => fileInputRef.current.click()} title="Upload image" className="post-modal-format-btn">
                🖼️
              </button>
              <button onClick={() => audioInputRef.current.click()} title="Upload audio" className="post-modal-format-btn">
                🎵
              </button>
              <button
                type="button"
                onClick={handleRecordAudio}
                title={isRecording ? 'Stop recording' : 'Record audio'}
                className={`post-modal-format-btn ${isRecording ? 'is-recording' : ''}`}
              >
                {isRecording ? '⏹' : '🎙'}
              </button>
            </div>
          )}
          {isPreview && <div style={{ width: '100px' }}></div>}

          <div className="post-modal-actions">
            <button 
              onClick={() => setIsPreview(!isPreview)}
              className="post-modal-submit-btn"
              style={{
                backgroundColor: isPreview ? '#E4DFD8' : undefined,
                color: isPreview ? '#3A4E6B' : undefined,
                border: isPreview ? '1px solid #3A4E6B' : undefined,
                marginRight: '8px'
              }}
            >
              {isPreview ? ' Edit' : 'Preview'}
            </button>
            <button onClick={handleSubmit} className="post-modal-submit-btn">
              {isReplyMode ? 'Send Reply' : 'Publish'}
            </button>
          </div>
        </div>
        
        <input type="file" ref={fileInputRef} style={{ display: 'none' }} accept="image/*" onChange={handleImageUpload} />
        <input type="file" ref={audioInputRef} style={{ display: 'none' }} accept="audio/*" onChange={handleAudioUpload} />
      </div>
    </>
  );
};

export default PostModal;
