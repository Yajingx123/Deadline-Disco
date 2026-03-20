import { useEffect, useMemo, useRef, useState } from "react";
import "./Player.css";

const API_BASE = import.meta.env?.VITE_API_BASE_URL || "http://127.0.0.1:8000";

function buildProgressData(totalLines, savedStars) {
  return Array.from({ length: totalLines }, (_, index) => (savedStars.has(index) ? 1 : 0));
}

export default function Player({ audioId, currentUserId = 1, onNavigate, currentView = "player" }) {
  const [audioMeta, setAudioMeta] = useState(null);
  const [subtitlesData, setSubtitlesData] = useState([]);
  const [currentLine, setCurrentLine] = useState(0);
  const [savedStars, setSavedStars] = useState(new Set());
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [isManualScrolling, setIsManualScrolling] = useState(false);

  const audioRef = useRef(null);
  const subtitleContainerRef = useRef(null);
  const latestStarsRef = useRef(new Set());

  const fileKey = useMemo(() => {
    if (!audioMeta) return null;
    return String(audioMeta.path || audioMeta.audio_id || "");
  }, [audioMeta]);

  const totalLines = subtitlesData.length;
  const savedCount = savedStars.size;

  useEffect(() => {
    latestStarsRef.current = savedStars;
  }, [savedStars]);

  const saveServerProgress = async (lineIndex = currentLine, stars = latestStarsRef.current) => {
    if (!audioId || !totalLines) return;

    const progressData = buildProgressData(totalLines, stars);
    const answeredCount = progressData.filter((value) => value > 0).length;
    const progressPercent = Math.round((answeredCount / totalLines) * 100);
    const status = progressPercent === 100 ? "completed" : answeredCount > 0 ? "in_progress" : "not_started";

    const payload = new URLSearchParams({
      user_id: String(currentUserId),
      audio_id: String(audioId),
      progress_percent: String(progressPercent),
      current_index: String(lineIndex),
      progress_data: JSON.stringify(progressData),
      status,
    });

    try {
      await fetch(`${API_BASE}/api/audio/progress/save`, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: payload.toString(),
      });
    } catch (requestError) {
      console.error("Failed to save audio progress:", requestError);
    }
  };

  const loadAudioData = async () => {
    if (!audioId) {
      setAudioMeta(null);
      setSubtitlesData([]);
      setCurrentLine(0);
      setSavedStars(new Set());
      setError("");
      return;
    }

    try {
      setLoading(true);
      setError("");

      const detailResponse = await fetch(`${API_BASE}/api/audio/${audioId}`);
      const detailData = await detailResponse.json();
      if (detailData.code !== 0 || !detailData.data) {
        throw new Error(detailData.msg || "Failed to load audio detail");
      }

      const meta = detailData.data;
      setAudioMeta(meta);

      const nextFileKey = String(meta.path || meta.audio_id);
      const subtitleResponse = await fetch(`/listening/${nextFileKey}.json`);
      if (!subtitleResponse.ok) {
        throw new Error(`Subtitle file /listening/${nextFileKey}.json not found`);
      }

      const subtitleJson = await subtitleResponse.json();
      setSubtitlesData(subtitleJson);

      const progressResponse = await fetch(
        `${API_BASE}/api/audio/progress?user_id=${currentUserId}&audio_id=${audioId}`,
      );
      const progressResult = await progressResponse.json();
      const remoteProgress = progressResult.code === 0 ? progressResult.data || {} : {};
      const savedArray = Array.isArray(remoteProgress.progress_data) ? remoteProgress.progress_data : [];
      const restoredStars = new Set(
        savedArray
          .map((value, index) => (Number(value) > 0 ? index : null))
          .filter((value) => value !== null),
      );

      setSavedStars(restoredStars);
      setCurrentLine(Number(remoteProgress.current_index || 0));

      if (audioRef.current) {
        audioRef.current.src = `/listening/${nextFileKey}.mp3`;
        audioRef.current.load();
      }
    } catch (requestError) {
      console.error(requestError);
      setError(requestError.message || "Failed to load player resources");
      setAudioMeta(null);
      setSubtitlesData([]);
      setCurrentLine(0);
      setSavedStars(new Set());
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAudioData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [audioId, currentUserId]);

  useEffect(() => {
    const player = audioRef.current;
    if (!player || !subtitlesData.length) return undefined;

    const handleTimeUpdate = () => {
      const currentTime = player.currentTime;
      if (isManualScrolling) return;

      const activeIndex = subtitlesData.findIndex((item) => {
        const start = Number(item.start);
        const end = Number(item.end);
        return currentTime >= start && currentTime < end;
      });

      if (activeIndex !== -1 && activeIndex !== currentLine) {
        setCurrentLine(activeIndex);
        const activeLineElement = document.querySelector(`.subtitle-line[data-index="${activeIndex}"]`);
        activeLineElement?.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    };

    player.addEventListener("timeupdate", handleTimeUpdate);
    return () => {
      player.removeEventListener("timeupdate", handleTimeUpdate);
    };
  }, [currentLine, isManualScrolling, subtitlesData]);

  useEffect(() => {
    return () => {
      saveServerProgress(currentLine, latestStarsRef.current);
    };
  }, [currentLine]);

  const handleWheel = () => {
    setIsManualScrolling(true);
    window.clearTimeout(window.__acadbeatManualScrollTimer);
    window.__acadbeatManualScrollTimer = window.setTimeout(() => setIsManualScrolling(false), 1500);
  };

  const handleClickSubtitle = (index, start) => {
    if (audioRef.current) {
      audioRef.current.currentTime = Number(start);
      audioRef.current.play().catch((requestError) => console.warn("Audio play failed:", requestError));
    }
    setCurrentLine(index);
    saveServerProgress(index, latestStarsRef.current);
  };

  const toggleStar = (index) => {
    setSavedStars((previousStars) => {
      const nextStars = new Set(previousStars);
      if (nextStars.has(index)) {
        nextStars.delete(index);
      } else {
        nextStars.add(index);
      }
      saveServerProgress(currentLine, nextStars);
      return nextStars;
    });
  };

  return (
    <div className="main-container">
      <div className="top-tabs">
        <button className={`tab ${currentView === "collections" ? "active" : ""}`} onClick={() => onNavigate?.("collections", { audioId })}>
          Collections
        </button>
        <button className={`tab ${currentView === "community" ? "active" : ""}`} onClick={() => onNavigate?.("community", { audioId })}>
          Community
        </button>
        <button className={`tab ${currentView === "player" ? "active" : ""}`}>Player</button>
      </div>

      <div className="content-container">
        <div className="content-area">
          <div className="page-title">
            <span>{audioMeta ? audioMeta.title : "Player"}</span>
          </div>

          {!audioId ? (
            <div className="player-empty-state">Choose an audio material from Collections or Community first.</div>
          ) : loading ? (
            <div className="player-empty-state">Loading player resources...</div>
          ) : error ? (
            <div className="player-empty-state player-error-state">{error}</div>
          ) : (
            <>
              <div className="player-wrapper">
                <audio ref={audioRef} controls />
                <div ref={subtitleContainerRef} className="subtitle-container" onWheel={handleWheel}>
                  {subtitlesData.map((item, index) => (
                    <div
                      key={`${audioId}-${index}`}
                      className={`subtitle-line ${currentLine === index ? "active" : ""}`}
                      data-index={index}
                      onClick={() => handleClickSubtitle(index, item.start)}
                    >
                      <span className="subtitle-text">{item.text}</span>
                      <span
                        className={`star-icon ${savedStars.has(index) ? "filled" : ""}`}
                        onClick={(event) => {
                          event.stopPropagation();
                          toggleStar(index);
                        }}
                      >
                        {savedStars.has(index) ? "★" : "☆"}
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="lesson-info-card">
                <div className="info-item">
                  <span className="info-label">Lesson:</span>
                  <span className="info-value">{audioMeta?.title || `Audio ${audioId}`}</span>
                </div>
                <div className="info-item">
                  <span className="info-label">Current Line:</span>
                  <span className="info-value">{Math.min(currentLine + 1, totalLines || 0)}</span>
                  <span className="info-label">/ {totalLines}</span>
                </div>
                <div className="info-item">
                  <span className="info-label">Saved Sentences:</span>
                  <span className="info-value">{savedCount}</span>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
