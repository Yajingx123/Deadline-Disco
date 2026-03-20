import { useEffect, useMemo, useRef, useState } from "react";
import "./IntensiveListeningPage.css";

const API_BASE = import.meta.env?.VITE_API_BASE_URL || "http://127.0.0.1:8000";

function buildProgressSegments(total, progressData, currentIndex) {
  return Array.from({ length: total }, (_, index) => {
    const value = Number(progressData[index] || 0);
    return {
      key: index,
      className: [
        "segment",
        value === 1 ? "understood" : "",
        value === 2 ? "not-understood" : "",
        value === 3 ? "skipped" : "",
        currentIndex === index ? "current" : "",
      ]
        .filter(Boolean)
        .join(" "),
    };
  });
}

export default function IntensiveListeningPage({ audioId, currentUserId = 1, onNavigate, currentView = "intensivelistening" }) {
  const audioRef = useRef(null);
  const subtitleContainerRef = useRef(null);

  const [audioMeta, setAudioMeta] = useState(null);
  const [subtitles, setSubtitles] = useState([]);
  const [progressData, setProgressData] = useState([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [isManualScrolling, setIsManualScrolling] = useState(false);

  const totalCount = subtitles.length;
  const completedCount = progressData.filter((item) => Number(item) === 1 || Number(item) === 2).length;
  const progressPercent = totalCount ? Math.round((completedCount / totalCount) * 100) : 0;

  const progressSegments = useMemo(
    () => buildProgressSegments(totalCount, progressData, currentIndex),
    [currentIndex, progressData, totalCount],
  );

  const saveServerProgress = async (nextProgress = progressData, nextIndex = currentIndex) => {
    if (!audioId || !totalCount) return;

    const answeredCount = nextProgress.filter((item) => Number(item) === 1 || Number(item) === 2).length;
    const status = answeredCount === 0 ? "Not Started" : answeredCount === totalCount ? "completed" : "in_progress";

    const payload = new URLSearchParams({
      user_id: String(currentUserId),
      audio_id: String(audioId),
      progress_percent: String(Math.round((answeredCount / totalCount) * 100)),
      current_index: String(nextIndex),
      progress_data: JSON.stringify(nextProgress),
      status,
    });

    try {
      await fetch(`${API_BASE}/api/audio/progress/save`, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: payload.toString(),
      });
    } catch (requestError) {
      console.error("Failed to save intensive listening progress:", requestError);
    }
  };

  const loadIntensiveData = async () => {
    if (!audioId) {
      setAudioMeta(null);
      setSubtitles([]);
      setProgressData([]);
      setCurrentIndex(0);
      setError("");
      return;
    }

    try {
      setLoading(true);
      setError("");

      const detailResponse = await fetch(`${API_BASE}/api/audio/${audioId}`);
      const detailResult = await detailResponse.json();
      if (detailResult.code !== 0 || !detailResult.data) {
        throw new Error(detailResult.msg || "Failed to load intensive listening detail");
      }

      const meta = detailResult.data;
      setAudioMeta(meta);

      const fileKey = String(meta.path || meta.audio_id);
      const subtitleResponse = await fetch(`/listening/${fileKey}.json`);
      if (!subtitleResponse.ok) {
        throw new Error(`Subtitle file /listening/${fileKey}.json not found`);
      }
      const subtitleJson = await subtitleResponse.json();
      setSubtitles(subtitleJson);

      const progressResponse = await fetch(
        `${API_BASE}/api/audio/progress?user_id=${currentUserId}&audio_id=${audioId}`,
      );
      const progressResult = await progressResponse.json();
      const serverProgress = progressResult.code === 0 ? progressResult.data || {} : {};

      const nextProgress = Array.isArray(serverProgress.progress_data) && serverProgress.progress_data.length === subtitleJson.length
        ? serverProgress.progress_data.map((item) => Number(item))
        : Array(subtitleJson.length).fill(0);

      setProgressData(nextProgress);
      setCurrentIndex(Math.min(Number(serverProgress.current_index || 0), Math.max(subtitleJson.length - 1, 0)));

      if (audioRef.current) {
        audioRef.current.src = `/listening/${fileKey}.mp3`;
        audioRef.current.load();
      }
    } catch (requestError) {
      console.error(requestError);
      setError(requestError.message || "Failed to load intensive listening resources");
      setAudioMeta(null);
      setSubtitles([]);
      setProgressData([]);
      setCurrentIndex(0);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadIntensiveData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [audioId, currentUserId]);

  useEffect(() => {
    const player = audioRef.current;
    if (!player || !subtitles.length) return undefined;

    const handleTimeUpdate = () => {
      const activeIndex = subtitles.findIndex((item) => {
        const start = Number(item.start);
        const end = Number(item.end);
        return player.currentTime >= start && player.currentTime < end;
      });

      if (activeIndex !== -1 && activeIndex !== currentIndex) {
        setCurrentIndex(activeIndex);
        if (!isManualScrolling) {
          const activeLineElement = document.querySelector(`.intensive-subtitle-line[data-index="${activeIndex}"]`);
          activeLineElement?.scrollIntoView({ behavior: "smooth", block: "center" });
        }
      }
    };

    player.addEventListener("timeupdate", handleTimeUpdate);
    return () => {
      player.removeEventListener("timeupdate", handleTimeUpdate);
    };
  }, [currentIndex, isManualScrolling, subtitles]);

  useEffect(() => {
    return () => {
      saveServerProgress(progressData, currentIndex);
    };
  }, [currentIndex, progressData]);

  const markCurrent = (value) => {
    if (!subtitles.length) return;
    const nextProgress = [...progressData];
    nextProgress[currentIndex] = value;
    setProgressData(nextProgress);
    saveServerProgress(nextProgress, currentIndex);

    if (currentIndex < subtitles.length - 1) {
      setCurrentIndex(currentIndex + 1);
      const nextSentence = subtitles[currentIndex + 1];
      if (audioRef.current && nextSentence) {
        audioRef.current.currentTime = Number(nextSentence.start);
        audioRef.current.play().catch((requestError) => console.warn("Audio play failed:", requestError));
      }
    }
  };

  const handleSubtitleClick = (index, start) => {
    setCurrentIndex(index);
    if (audioRef.current) {
      audioRef.current.currentTime = Number(start);
      audioRef.current.play().catch((requestError) => console.warn("Audio play failed:", requestError));
    }
    saveServerProgress(progressData, index);
  };

  const handleWheel = () => {
    setIsManualScrolling(true);
    window.clearTimeout(window.__acadbeatIntensiveScrollTimer);
    window.__acadbeatIntensiveScrollTimer = window.setTimeout(() => setIsManualScrolling(false), 1500);
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
        <button className={`tab ${currentView === "player" ? "active" : ""}`} onClick={() => onNavigate?.("player", { audioId })}>
          Player
        </button>
        <button className={`tab ${currentView === "intensivelistening" ? "active" : ""}`}>Intensive Listening</button>
      </div>

      <div className="content-container">
        <div className="content-area">
          <div className="page-title intensive-title">
            <span>{audioMeta ? `Intensive Listening - ${audioMeta.title}` : "Intensive Listening"}</span>
            <button className="save-btn" onClick={() => saveServerProgress()}>
              Save Progress
            </button>
          </div>

          {!audioId ? (
            <div className="intensive-empty-state">Choose an audio material first.</div>
          ) : loading ? (
            <div className="intensive-empty-state">Loading intensive listening materials...</div>
          ) : error ? (
            <div className="intensive-empty-state intensive-error-state">{error}</div>
          ) : (
            <div className="listening-content">
              <div className="progress-wrapper">
                <div className="progress-label">
                  <span>Article Comprehension</span>
                  <span>{completedCount} / {totalCount}</span>
                </div>
                <div className="overall-progress-bar">
                  {progressSegments.map((segment) => (
                    <div key={segment.key} className={segment.className} style={{ width: `${100 / totalCount}%` }} />
                  ))}
                </div>
                <div className="progress-summary">{progressPercent}% completed</div>
              </div>

              <div className="status-text">Mark your understanding for the current sentence</div>
              <div className="status-buttons">
                <div className="status-btn-group">
                  <button className="status-btn not-understood" onClick={() => markCurrent(2)}>
                    Didn't understand
                  </button>
                  <button className="status-btn understood" onClick={() => markCurrent(1)}>
                    Understood
                  </button>
                </div>
              </div>

              <div className="intensive-audio-panel">
                <audio ref={audioRef} controls />
              </div>

              <div className="intensive-subtitle-container" ref={subtitleContainerRef} onWheel={handleWheel}>
                {subtitles.map((item, index) => (
                  <button
                    key={`${audioId}-${index}`}
                    type="button"
                    className={`intensive-subtitle-line ${currentIndex === index ? "active" : ""}`}
                    data-index={index}
                    onClick={() => handleSubtitleClick(index, item.start)}
                  >
                    <span className="sentence-number">{index + 1}</span>
                    <span className="sentence-text">{item.text}</span>
                    <span className={`sentence-badge badge-${Number(progressData[index] || 0)}`}>
                      {Number(progressData[index] || 0) === 1
                        ? "Understood"
                        : Number(progressData[index] || 0) === 2
                          ? "Need Review"
                          : "Pending"}
                    </span>
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
