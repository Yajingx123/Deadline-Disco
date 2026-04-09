// Synced from forum-project/src/utils/formatText.js for rich post/comment rendering.

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

export const renderFormattedText = (text) => {
  if (!text) return '';

  let html = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  html = html.replace(/!\[audio:(.*?)\]\((.*?)\)/g, (match, fileName, src) => {
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

  html = html.replace(/!\[(.*?)\]\((.*?)\)/g, '<a class="forumInlineImage" href="$2" target="_blank" rel="noopener noreferrer"><img class="forumInlineImage__img" src="$2" alt="$1" /></a>');
  html = html.replace(/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

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

export const getSummary = (text, maxLength = 100) => {
  if (!text) return 'No content';

  let cleanText = text;

  cleanText = cleanText.replace(/!\[audio:(.*?)\]\((.*?)\)/g, '[Audio]');
  cleanText = cleanText.replace(/!\[video:(.*?)\]\((.*?)\)/g, '[Video]');

  cleanText = cleanText.replace(/!\[(.*?)\]\((.*?)\)/g, '[Image]');
  cleanText = cleanText.replace(/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/g, '$1');

  cleanText = cleanText
    .replace(/\*\*(.+?)\*\*/g, '$1')
    .replace(/\*(.+?)\*/g, '$1')
    .replace(/<u>(.+?)<\/u>/g, '$1')
    .replace(/&lt;u&gt;(.+?)&lt;\/u&gt;/g, '$1')
    .replace(/#/g, '')
    .replace(/>/g, '')
    .replace(/\n/g, ' ');

  const txt = document.createElement('textarea');
  txt.innerHTML = cleanText;
  cleanText = txt.value;

  if (cleanText.length > maxLength) {
    return cleanText.substring(0, maxLength) + '...';
  }
  return cleanText;
};
