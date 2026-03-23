// src/utils/formatText.js

// 完整的渲染函数 (用于详情页)
export const renderFormattedText = (text) => {
  if (!text) return '';
  
  let html = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  // 👇 第一步：先处理音频 (必须在处理图片之前，因为格式很像)
  // 匹配格式：![audio:文件名.mp3](data:audio/...)
  html = html.replace(/!\[audio:(.*?)\]\((.*?)\)/g, (match, fileName, src) => {
    // 简单的类型检测，默认 mp3，也可以根据文件名后缀动态判断
    let type = 'audio/mpeg';
    if (fileName.endsWith('.wav')) type = 'audio/wav';
    if (fileName.endsWith('.ogg')) type = 'audio/ogg';
    if (fileName.endsWith('.m4a')) type = 'audio/mp4';
    if (fileName.endsWith('.webm')) type = 'audio/webm';

    return `
      <div style="margin: 15px 0; padding: 12px; background: #f4f3ec; border-radius: 8px; border: 1px solid #e5e4e7;">
        <div style="font-size: 13px; color: #6b6375; margin-bottom: 8px; font-weight: 500;">
          🎵 ${fileName}
        </div>
        <audio controls style="width: 100%; height: 36px; outline: none;">
          <source src="${src}" type="${type}" />
          Your browser does not support the audio element.
        </audio>
      </div>
    `;
  });

  // 👇 第二步：处理普通图片
  // 注意：因为音频已经被替换掉了，这里的正则不会再匹配到 audio 标记
  html = html.replace(/!\[(.*?)\]\((.*?)\)/g, '<img src="$2" alt="$1" style="max-width: 100%; border-radius: 8px; margin: 10px 0; display:block;" />');
  
  // 处理加粗、斜体、下划线
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
  html = html.replace(/&lt;u&gt;(.*?)&lt;\/u&gt;/g, '<u>$1</u>');
  
  html = html.replace(/\n/g, '<br>');

  return html;
};

// 获取摘要函数 (用于列表页)
export const getSummary = (text, maxLength = 100) => {
  if (!text) return '暂无内容';
  
  let cleanText = text;

  // 👇 1. 处理音频标记：替换为提示文字，避免显示 Base64 乱码
  cleanText = cleanText.replace(/!\[audio:(.*?)\]\((.*?)\)/g, '[🎵 音频]');

  // 2. 处理图片标记：替换为 [图片]
  cleanText = cleanText.replace(/!\[(.*?)\]\((.*?)\)/g, '[图片]');
  
  // 3. 移除 Markdown 符号，只留纯文本
  cleanText = cleanText
    .replace(/\*\*(.+?)\*\*/g, '$1') 
    .replace(/\*(.+?)\*/g, '$1')     
    .replace(/<u>(.+?)<\/u>/g, '$1') 
    .replace(/&lt;u&gt;(.+?)&lt;\/u&gt;/g, '$1') 
    .replace(/#/g, '')               
    .replace(/>/g, '')               
    .replace(/\n/g, ' ');            

  // 4. 解码 HTML 实体 (以防万一)
  const txt = document.createElement('textarea');
  txt.innerHTML = cleanText;
  cleanText = txt.value;

  // 5. 截断
  if (cleanText.length > maxLength) {
    return cleanText.substring(0, maxLength) + '...';
  }
  return cleanText;
};

// 编辑格式化函数 (发帖时用，保持原样)
export const formatTextForEdit = (prefix, suffix = prefix, editorRef) => {
  if (!editorRef.current) return '';
  const editor = editorRef.current;
  const start = editor.selectionStart;
  const end = editor.selectionEnd;
  const content = editor.value;
  const selectedText = content.substring(start, end);

  let newContent;
  if (selectedText) {
    newContent = content.substring(0, start) + prefix + selectedText + suffix + content.substring(end);
    setTimeout(() => {
      editor.selectionStart = editor.selectionEnd = start + prefix.length + selectedText.length + suffix.length;
    }, 0);
  } else {
    newContent = content.substring(0, start) + prefix + suffix + content.substring(end);
    setTimeout(() => {
      editor.selectionStart = editor.selectionEnd = start + prefix.length;
    }, 0);
  }
  editor.focus();
  return newContent;
};
