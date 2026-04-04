// src/utils/formatText.js

const formatAudioTime = (seconds) => {
  if (!Number.isFinite(seconds) || seconds < 0) return '00:00';
  const totalSeconds = Math.floor(seconds);
  const minutes = Math.floor(totalSeconds / 60);
  const remainingSeconds = totalSeconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
};

const buildWaveBars = (waveEl, duration) => {
  if (!waveEl) return [];
  const seconds = Number.isFinite(duration) && duration > 0 ? duration : 6;
  const barCount = Math.max(18, Math.min(96, Math.round(seconds * 4)));
  waveEl.innerHTML = '';

  const bars = [];
  for (let index = 0; index < barCount; index += 1) {
    const bar = document.createElement('span');
    bar.className = 'forumAudioPlayer__bar';
    const height = 18 + Math.round(
      ((Math.sin((index + 1) * 0.63 + seconds) + 1) * 18) +
      ((Math.cos((index + 1) * 0.29 + seconds * 0.5) + 1) * 8)
    );
    bar.style.height = `${Math.min(height, 72)}px`;
    waveEl.appendChild(bar);
    bars.push(bar);
  }
  return bars;
};

export const enhanceRenderedAudioPlayers = (root) => {
  if (!root) return () => {};

  const playerNodes = root.querySelectorAll('[data-audio-player]');
  const cleanups = [];

  playerNodes.forEach((playerNode) => {
    if (playerNode.dataset.audioReady === 'true') {
      return;
    }
    playerNode.dataset.audioReady = 'true';

    const audio = playerNode.querySelector('[data-role="audio"]');
    const toggle = playerNode.querySelector('[data-role="toggle"]');
    const wave = playerNode.querySelector('[data-role="wave"]');
    const durationEl = playerNode.querySelector('[data-role="duration"]');
    const currentEl = playerNode.querySelector('[data-role="current"]');
    let bars = [];

    if (!audio || !toggle || !wave || !durationEl || !currentEl) {
      return;
    }

    const syncBars = () => {
      const progress = audio.duration > 0 ? audio.currentTime / audio.duration : 0;
      const activeCount = Math.max(0, Math.round(progress * bars.length));
      bars.forEach((bar, index) => {
        bar.classList.toggle('is-played', index < activeCount);
      });
      currentEl.textContent = formatAudioTime(audio.currentTime);
      durationEl.textContent = formatAudioTime(audio.duration);
    };

    const updateButton = () => {
      const isPlaying = !audio.paused && !audio.ended;
      playerNode.classList.toggle('is-playing', isPlaying);
      toggle.setAttribute('aria-label', isPlaying ? 'Pause audio' : 'Play audio');
      toggle.textContent = isPlaying ? 'Pause' : 'Play';
    };

    const handleMetadata = () => {
      bars = buildWaveBars(wave, audio.duration);
      syncBars();
    };

    const handleToggle = () => {
      if (audio.paused || audio.ended) {
        document.querySelectorAll('[data-audio-player] audio').forEach((otherAudio) => {
          if (otherAudio !== audio) {
            otherAudio.pause();
          }
        });
        audio.play().catch(() => {});
      } else {
        audio.pause();
      }
    };

    const handlePlay = () => {
      updateButton();
      syncBars();
    };

    const handlePause = () => {
      updateButton();
      syncBars();
    };

    const handleEnded = () => {
      audio.currentTime = 0;
      updateButton();
      syncBars();
    };

    toggle.addEventListener('click', handleToggle);
    audio.addEventListener('loadedmetadata', handleMetadata);
    audio.addEventListener('timeupdate', syncBars);
    audio.addEventListener('play', handlePlay);
    audio.addEventListener('pause', handlePause);
    audio.addEventListener('ended', handleEnded);

    if (audio.readyState >= 1) {
      handleMetadata();
    } else {
      bars = buildWaveBars(wave, 6);
      syncBars();
    }
    updateButton();

    cleanups.push(() => {
      toggle.removeEventListener('click', handleToggle);
      audio.removeEventListener('loadedmetadata', handleMetadata);
      audio.removeEventListener('timeupdate', syncBars);
      audio.removeEventListener('play', handlePlay);
      audio.removeEventListener('pause', handlePause);
      audio.removeEventListener('ended', handleEnded);
      delete playerNode.dataset.audioReady;
    });
  });

  return () => {
    cleanups.forEach((cleanup) => cleanup());
  };
};

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
      <div class="forumAudioPlayer" data-audio-player>
        <button class="forumAudioPlayer__toggle" type="button" data-role="toggle" aria-label="Play audio">Play</button>
        <div class="forumAudioPlayer__body">
          <div class="forumAudioPlayer__wave" data-role="wave" aria-hidden="true"></div>
          <div class="forumAudioPlayer__timeline">
            <span data-role="current">00:00</span>
            <span data-role="duration">00:00</span>
          </div>
        </div>
        <audio preload="metadata" data-role="audio">
          <source src="${src}" type="${type}" />
          Your browser does not support the audio element.
        </audio>
      </div>
    `;
  });

  html = html.replace(/!\[video:(.*?)\]\((.*?)\)/g, (match, fileName, src) => {
    return `
      <div class="forumVideoPlayer">
        <div class="forumVideoPlayer__label">Video: ${fileName}</div>
        <video controls preload="metadata" style="width: 100%; max-height: 360px; border-radius: 10px; background: #111;">
          <source src="${src}" type="video/mp4" />
          <source src="${src}" type="video/webm" />
          Your browser does not support the video element.
        </video>
      </div>
    `;
  });

  // 👇 第二步：处理普通图片
  // 注意：因为音频已经被替换掉了，这里的正则不会再匹配到 audio 标记
  html = html.replace(/!\[(.*?)\]\((.*?)\)/g, '<img src="$2" alt="$1" style="max-width: 100%; border-radius: 8px; margin: 10px 0; display:block;" />');
  html = html.replace(/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
  
  // 处理加粗、斜体、下划线
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
  html = html.replace(/&lt;u&gt;(.*?)&lt;\/u&gt;/g, '<u>$1</u>');
  
  html = html.replace(/\n/g, '<br>');

  return html;
};

export const getReplyPreview = (text, maxLength = 90) => {
  if (!text) return '';

  let preview = text;
  let imageCount = 0;
  let audioCount = 0;
  let videoCount = 0;

  preview = preview.replace(/!\[audio:(.*?)\]\((.*?)\)/g, () => {
    audioCount += 1;
    return audioCount > 1 ? ' [Voice messages] ' : ' [Voice message] ';
  });
  preview = preview.replace(/!\[video:(.*?)\]\((.*?)\)/g, () => {
    videoCount += 1;
    return videoCount > 1 ? ' [Videos] ' : ' [Video] ';
  });

  preview = preview.replace(/!\[(.*?)\]\((.*?)\)/g, () => {
    imageCount += 1;
    return imageCount > 1 ? ' [Images] ' : ' [Image] ';
  });

  preview = preview
    .replace(/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/g, '$1')
    .replace(/\*\*(.+?)\*\*/g, '$1')
    .replace(/\*(.+?)\*/g, '$1')
    .replace(/<u>(.+?)<\/u>/g, '$1')
    .replace(/&lt;u&gt;(.+?)&lt;\/u&gt;/g, '$1')
    .replace(/\s+/g, ' ')
    .trim();

  const txt = document.createElement('textarea');
  txt.innerHTML = preview;
  preview = txt.value.trim();

  if (preview.length > maxLength) {
    return `${preview.substring(0, maxLength).trim()}...`;
  }
  return preview;
};

// 获取摘要函数 (用于列表页)
export const getSummary = (text, maxLength = 100) => {
  if (!text) return '暂无内容';
  
  let cleanText = text;

  // 👇 1. 处理音频标记：替换为提示文字，避免显示 Base64 乱码
  cleanText = cleanText.replace(/!\[audio:(.*?)\]\((.*?)\)/g, '[🎵 音频]');
  cleanText = cleanText.replace(/!\[video:(.*?)\]\((.*?)\)/g, '[🎬 视频]');

  // 2. 处理图片标记：替换为 [图片]
  cleanText = cleanText.replace(/!\[(.*?)\]\((.*?)\)/g, '[图片]');
  cleanText = cleanText.replace(/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/g, '$1');
  
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
