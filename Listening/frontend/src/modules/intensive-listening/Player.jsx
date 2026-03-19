import React, { useEffect, useRef, useState } from 'react';
import './Player.css'; // 导入CSS文件

const Player = ({ audioId: externalAudioId = null, onBackToCollections = () => {}, onNavigate = null, currentView = 'player' }) => {
  const [audioId, setAudioId] = useState(null);
  const [subtitlesData, setSubtitlesData] = useState([]);
  const [currentLine, setCurrentLine] = useState(0);
  const [totalLines, setTotalLines] = useState(0);
  const [savedCount, setSavedCount] = useState(0);
  const [lessonTitle, setLessonTitle] = useState('Loading...');
  const [savedStars, setSavedStars] = useState(new Set());
  const [progress, setProgress] = useState(0);
  const [isManualScrolling, setIsManualScrolling] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);

  const audioRef = useRef(null);
  const subtitleContainerRef = useRef(null);

  const BASE_AUDIO_PATH = './listening/';
  const STORAGE_PREFIX = 'acadbeat_player_';

  // 本地存储相关函数
  const savePlayerState = (id, prog, stars) => {
    const state = {
      audioId: id,
      progress: prog,
      savedStars: Array.from(stars),
      lastUpdate: new Date().getTime(),
    };
    localStorage.setItem(`${STORAGE_PREFIX}state`, JSON.stringify(state));
    window.location.hash = id;
  };

  const getSavedPlayerState = () => {
    const stateStr = localStorage.getItem(`${STORAGE_PREFIX}state`);
    if (stateStr) {
      try {
        return JSON.parse(stateStr);
      } catch (e) {
        console.error('解析存储状态失败:', e);
      }
    }
    return null;
  };

  const savePlaybackProgress = () => {
    const player = audioRef.current;
    if (!player || !player.src) return;
    const id = audioId || getSavedPlayerState()?.audioId;
    if (id) {
      savePlayerState(id, player.currentTime, savedStars);
    }
  };

  const restoreSavedStars = (savedIndices = []) => {
    setSavedStars(new Set(savedIndices));
    setSavedCount(savedIndices.length);
  };

  const updateSavedCount = (newStars) => {
    const count = newStars.size;
    setSavedCount(count);
  };

  // 从URL获取audio_id的函数
  const getAudioIdFromUrl = () => {
    const params = new URLSearchParams(window.location.search);
    let id = params.get('audio_id');

    if (!id && window.location.hash) {
      id = window.location.hash.slice(1);
    }

    if (id && id.trim() !== '') {
      setAudioId(id.trim());
      return id.trim();
    }

    const savedState = getSavedPlayerState();
    if (savedState?.audioId) {
      setAudioId(savedState.audioId);
      return savedState.audioId;
    }

    return null;
  };

  // 加载音频和字幕
  const loadAudioAndSubtitles = async (customId = null) => {
    const id = customId || externalAudioId || getAudioIdFromUrl();
    if (!id) {
      // 如果没有找到ID，可以选择显示错误或提示
      alert('Missing audio ID');
      return;
    }

    setAudioId(id);

    const dynamicAudioPath = `${BASE_AUDIO_PATH}${id}.mp3`;
    const dynamicSubtitlePath = `${BASE_AUDIO_PATH}${id}.json`;

    try {
      const audio = audioRef.current;
      audio.src = dynamicAudioPath;
      await audio.load();
      setLessonTitle(id);

      // 恢复播放进度
      const savedState = getSavedPlayerState();
      if (savedState?.progress && savedState.audioId === id) {
        audio.currentTime = savedState.progress;
        setProgress(savedState.progress);
      }

      // 恢复收藏
      if (savedState?.savedStars && savedState.audioId === id) {
        restoreSavedStars(savedState.savedStars);
      }

      // 加载字幕
      try {
        const response = await fetch(dynamicSubtitlePath);
        if (!response.ok) throw new Error('Subtitle file not found');

        const subs = await response.json();
        setSubtitlesData(subs);
        setTotalLines(subs.length);
      } catch (subtitleErr) {
        console.warn('Subtitle loading failed:', subtitleErr);
        setSubtitlesData([]);
        setTotalLines(0);
        setCurrentLine(0);
      }
    } catch (err) {
      console.error('Load resource failed:', err);
      alert(`Failed to load audio: ${dynamicAudioPath}`);
    }
  };

  // 播放时间更新
  const handleTimeUpdate = () => {
    const player = audioRef.current;
    const currentTime = player.currentTime;
    setProgress(currentTime);

    if (isManualScrolling) return;

    let activeIndex = -1;
    subtitlesData.forEach((item, idx) => {
      const start = parseFloat(item.start);
      const end = parseFloat(item.end);
      if (currentTime >= start && currentTime < end) {
        activeIndex = idx;
      }
    });

    if (activeIndex !== -1 && activeIndex !== currentLine - 1) {
      setCurrentLine(activeIndex + 1);
      const activeLineElement = document.querySelector(`.subtitle-line[data-index="${activeIndex}"]`);
      if (activeLineElement && subtitleContainerRef.current) {
        activeLineElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
  };

  // 滚轮事件处理
  const handleWheel = () => {
    setIsManualScrolling(true);
    setTimeout(() => setIsManualScrolling(false), 3000);
  };

  // 点击字幕行跳转
  const handleClickSubtitle = (index, start) => {
    const player = audioRef.current;
    if (player.readyState >= 1) {
      player.pause();
      player.currentTime = parseFloat(start);
      setIsPlaying(false);

      const lineElement = document.querySelector(`.subtitle-line[data-index="${index}"]`);
      if (lineElement && subtitleContainerRef.current) {
        lineElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      setCurrentLine(index + 1);
      savePlaybackProgress();

      player.play().catch(err => console.warn('Auto play failed:', err));
    } else {
      const setTimeAndPlay = () => {
        player.currentTime = parseFloat(start);
        setIsPlaying(true);
        player.removeEventListener('loadedmetadata', setTimeAndPlay);
        savePlaybackProgress();
        player.play().catch(err => console.warn('Auto play failed:', err));
      };
      player.addEventListener('loadedmetadata', setTimeAndPlay);
    }
  };

  // 点击星星收藏
  const toggleStar = (index) => {
    setSavedStars(prev => {
      const newSet = new Set(prev);
      if (newSet.has(index)) {
        newSet.delete(index);
      } else {
        newSet.add(index);
      }
      updateSavedCount(newSet);
      savePlaybackProgress();
      return newSet;
    });
  };

  useEffect(() => {
    loadAudioAndSubtitles();
  }, [externalAudioId]); // 当外部传入的audioId变化时重新加载

  useEffect(() => {
    const audio = audioRef.current;
    if (!audio) return;

    const handlePlay = () => setIsPlaying(true);
    const handlePause = () => setIsPlaying(false);

    audio.addEventListener('timeupdate', handleTimeUpdate);
    audio.addEventListener('play', handlePlay);
    audio.addEventListener('pause', handlePause);

    return () => {
      audio.removeEventListener('timeupdate', handleTimeUpdate);
      audio.removeEventListener('play', handlePlay);
      audio.removeEventListener('pause', handlePause);
      savePlaybackProgress(); // 卸载前保存
    };
  }, [isManualScrolling]);

  // --- 恢复使用类名 ---
  return (
    <div className="main-container">
      {}
      <div className="top-tabs">
        <button 
          className={`tab ${currentView === 'collections' ? 'active' : ''}`} 
          onClick={() => onNavigate && onNavigate("collections")}
        >
          Collections
        </button>
        <button 
          className={`tab ${currentView === 'community' ? 'active' : ''}`} 
          onClick={() => onNavigate && onNavigate("community")}
        >
          Community
        </button>
        <button className={`tab ${currentView === 'player' ? 'active' : ''}`} onClick={() => onNavigate && onNavigate("player")}>Player</button>
        
      </div>

      <div className="content-container">
        <div className="content-area">
          <div className="page-title">
            <span>Player</span>
          </div>

          <div className="player-wrapper">
            <audio ref={audioRef} controls />
            <div
              ref={subtitleContainerRef}
              className="subtitle-container"
              onWheel={handleWheel}
            >
              {subtitlesData.map((item, index) => (
                <div
                  key={index}
                  className={`subtitle-line ${currentLine - 1 === index ? 'active' : ''}`}
                  data-index={index}
                  onClick={() => handleClickSubtitle(index, item.start)}
                >
                  <span className="subtitle-text">{item.text}</span>
                  <span
                    className={`star-icon ${savedStars.has(index) ? 'filled' : ''}`}
                    onClick={(e) => { e.stopPropagation(); toggleStar(index); }}
                  >
                    {savedStars.has(index) ? '★' : '☆'}
                  </span>
                </div>
              ))}
            </div>
          </div>

          <div className="lesson-info-card">
            <div className="info-item">
              <span className="info-label">Lesson:</span>
              <span className="info-value">{lessonTitle}</span>
            </div>
            <div className="info-item">
              <span className="info-label">Current Line:</span>
              <span className="info-value">{currentLine}</span>/
              <span className="info-value">{totalLines}</span>
            </div>
            <div className="info-item">
              <span className="info-label">Saved Sentences:</span>
              <span className="info-value">{savedCount}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Player;