// src/components/PostModal.jsx

import { useState, useRef, useEffect } from 'react';
import './PostModal.css';
// 👇 1. 引入所有标签定义
import { ALL_TAGS } from '../data/mockData'; 

const PostModal = ({ 
  isOpen, 
  onClose, 
  onSubmit, 
  isReplyMode = false, 
  quoteText = '',
  parentTitle = ''
}) => {
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [selectedTags, setSelectedTags] = useState([]); // 👇 2. 新增：选中的标签状态
  const [isPreview, setIsPreview] = useState(false);
  
  const editorRef = useRef(null);
  const fileInputRef = useRef(null);
  const audioInputRef = useRef(null);
  
  const imageMapRef = useRef({});
  const audioMapRef = useRef({});

  // 初始化逻辑
  useEffect(() => {
    if (isOpen) {
      if (isReplyMode) {
        setTitle(`Re: ${parentTitle}`);
      } else {
        setTitle('');
        setContent('');
      }
      setSelectedTags([]); // 👇 重置标签
      setIsPreview(false);
      imageMapRef.current = {};
      audioMapRef.current = {};

      const timer = setTimeout(() => {
        const input = document.getElementById('modal-title-input');
        if (input) input.focus();
      }, 100);
      
      document.body.classList.add('pm-modal-open');
      return () => {
        clearTimeout(timer);
        document.body.classList.remove('pm-modal-open');
      };
    }
  }, [isOpen, isReplyMode, parentTitle]);

  // ... (中间的 helper 函数 createImagePlaceholder, decodeContentForSubmit, parseMarkdown 等保持不变) ...
  // 为了节省篇幅，这里省略未变动的 helper 函数，请保留你之前的代码
  const createImagePlaceholder = (fileName) => {
    const id = 'img_' + Date.now() + Math.random().toString(36).substr(2, 5);
    return { placeholder: `%%IMG_${id}%%`, id };
  };
  const createAudioPlaceholder = (fileName) => {
    const id = 'aud_' + Date.now() + Math.random().toString(36).substr(2, 5);
    return { placeholder: `%%AUDIO_${id}%%`, id };
  };
  const decodeContentForSubmit = (editText) => {
    let text = editText;
    Object.keys(imageMapRef.current).forEach(id => {
      const { fileName, base64 } = imageMapRef.current[id];
      const placeholder = `%%IMG_${id}%%`;
      text = text.split(placeholder).join(`![${fileName}](${base64})`);
    });
    Object.keys(audioMapRef.current).forEach(id => {
      const { fileName, base64 } = audioMapRef.current[id];
      const placeholder = `%%AUDIO_${id}%%`;
      text = text.split(placeholder).join(`![audio:${fileName}](${base64})`);
    });
    return text;
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
  const handleImageUpload = (e) => {
    const file = e.target.files[0];
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = (event) => {
      const base64String = event.target.result;
      const { placeholder, id } = createImagePlaceholder(file.name);
      imageMapRef.current[id] = { fileName: file.name, base64: base64String };
      insertPlaceholder(placeholder);
    };
    reader.readAsDataURL(file);
    e.target.value = '';
  };
  const handleAudioUpload = (e) => {
    const file = e.target.files[0];
    if (!file || !file.type.startsWith('audio/')) { alert('Please upload a valid audio file'); return; }
    if (file.size > 5 * 1024 * 1024) { alert('Audio file too large (>5MB)'); return; }
    const reader = new FileReader();
    reader.onload = (event) => {
      const base64String = event.target.result;
      const { placeholder, id } = createAudioPlaceholder(file.name);
      audioMapRef.current[id] = { fileName: file.name, base64: base64String };
      insertPlaceholder(placeholder);
    };
    reader.readAsDataURL(file);
    e.target.value = '';
  };
  const insertPlaceholder = (placeholder) => {
    const textarea = editorRef.current;
    if (!textarea) return;
    const start = textarea.selectionStart;
    const insertText = `\n${placeholder}\n`;
    const newText = content.substring(0, start) + insertText + content.substring(textarea.selectionEnd);
    setContent(newText);
    setTimeout(() => {
      textarea.focus();
      textarea.setSelectionRange(start + insertText.length, start + insertText.length);
    }, 0);
  };
  const handlePaste = (e) => {
     const items = e.clipboardData.items;
     for (let i = 0; i < items.length; i++) {
      if (items[i].type.indexOf('image') !== -1) {
        e.preventDefault();
        const file = items[i].getAsFile();
        const reader = new FileReader();
        reader.onload = (event) => {
          const base64String = event.target.result;
          const { placeholder, id } = createImagePlaceholder('Pasted_Image');
          imageMapRef.current[id] = { fileName: 'Pasted_Image.png', base64: base64String };
          insertPlaceholder(placeholder);
        };
        reader.readAsDataURL(file);
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
        alert("You can select up to 3 tags.");
        return;
      }
      setSelectedTags([...selectedTags, tag]);
    }
  };

  const handleSubmit = () => {
    if (!content.trim()) {
      alert('Content cannot be empty!');
      return;
    }
    if (!isReplyMode && !title.trim()) {
       alert('Title cannot be empty!');
       return;
    }

    const finalContent = decodeContentForSubmit(content);

    // 👇 4. 提交时包含 tags
    const newData = {
      id: Date.now(),
      title: title, 
      content: finalContent,
      tags: selectedTags, // 传递选中的标签
      author: 'Current User',
      time: new Date().toLocaleDateString()
    };

    onSubmit(newData);
    
    setTitle('');
    setContent('');
    setSelectedTags([]);
    setIsPreview(false);
    onClose();
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
              <div className="post-modal-avatar">Y</div>
              <div className="post-modal-input-wrapper">
                <input
                  id="modal-title-input"
                  type="text"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder={isReplyMode ? "Reply title (optional)" : "Enter post title..."}
                  className="post-modal-title-input"
                  disabled={isPreview}
                />
              </div>
            </div>

            {/* 👇 5. 新增：标签选择区域 */}
            {!isPreview && (
              <div className="post-modal-tags-section">
                <label className="tags-label">Select Tags (Max 3):</label>
                <div className="tags-container">
                  {ALL_TAGS.map(tag => (
                    <button
                      key={tag}
                      type="button"
                      onClick={() => toggleTag(tag)}
                      className={`tag-chip ${selectedTags.includes(tag) ? 'active' : ''}`}
                    >
                      {tag}
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