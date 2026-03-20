import { useEffect, useMemo, useRef, useState } from "react";
import "./IntensiveListeningPage.css";

const API_BASE = import.meta.env?.VITE_API_BASE_URL || "http://127.0.0.1:8000";

function buildSegments(total, progressData, currentIndex) {
  return Array.from({ length: total }, (_, index) => {
    const status = Number(progressData[index] || 0);
    return {
      key: index,
      className: [
        "progress-segment",
        status === 1 ? "progress-understood" : "",
        status === 2 ? "progress-not-understood" : "",
        status === 3 ? "progress-skipped" : "",
        index === currentIndex ? "current" : "",
      ]
        .filter(Boolean)
        .join(" "),
    };
  });
}

export default function IntensiveListeningPage({ audioId, currentUserId = 1, onNavigate, currentView = "intensivelistening" }) {
  const audioRef = useRef(null);
  const latestProgressRef = useRef([]);
  const latestIndexRef = useRef(0);

  const [audioMeta, setAudioMeta] = useState(null);
  const [sentences, setSentences] = useState([]);
  const [progress, setProgress] = useState([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [isPlaying, setIsPlaying] = useState(false);

  const totalCount = sentences.length;
  const currentSentence = sentences[currentIndex] || null;
  const doneCount = progress.filter((item) => Number(item) === 1 || Number(item) === 2).length;

  const progressSegments = useMemo(
    () => buildSegments(totalCount, progress, currentIndex),
    [currentIndex, progress, totalCount],
  );

  useEffect(() => {
    latestProgressRef.current = progress;
  }, [progress]);

  useEffect(() => {
    latestIndexRef.current = currentIndex;
  }, [currentIndex]);

  const saveServerProgress = async (nextProgress = latestProgressRef.current, nextIndex = latestIndexRef.current) => {
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

  const seekToSentence = (index, autoPlay = false) => {
    const player = audioRef.current;
    const sentence = sentences[index];
    if (!player || !sentence) return;

    player.currentTime = Number(sentence.start || 0);
    if (autoPlay) {
      player.play().catch((requestError) => console.warn("Audio play failed:", requestError));
    }
  };

  const moveToSentence = (nextIndex, options = {}) => {
    if (!sentences.length) return;
    const clampedIndex = Math.max(0, Math.min(nextIndex, sentences.length - 1));
    setCurrentIndex(clampedIndex);
    seekToSentence(clampedIndex, options.autoPlay ?? isPlaying);
    saveServerProgress(latestProgressRef.current, clampedIndex);
  };

  const loadPageData = async () => {
    if (!audioId) {
      setAudioMeta(null);
      setSentences([]);
      setProgress([]);
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
      const fileKey = String(meta.path || meta.audio_id);
      const subtitleResponse = await fetch(`/listening/${fileKey}.json`);
      if (!subtitleResponse.ok) {
        throw new Error(`Subtitle file /listening/${fileKey}.json not found`);
      }

      const subtitleJson = await subtitleResponse.json();

      const progressResponse = await fetch(
        `${API_BASE}/api/audio/progress?user_id=${currentUserId}&audio_id=${audioId}`,
      );
      const progressResult = await progressResponse.json();
      const serverProgress = progressResult.code === 0 ? progressResult.data || {} : {};

      const nextProgress =
        Array.isArray(serverProgress.progress_data) && serverProgress.progress_data.length === subtitleJson.length
          ? serverProgress.progress_data.map((item) => Number(item))
          : Array(subtitleJson.length).fill(0);

      const nextIndex = Math.max(
        0,
        Math.min(Number(serverProgress.current_index || 0), Math.max(subtitleJson.length - 1, 0)),
      );

      setAudioMeta(meta);
      setSentences(subtitleJson);
      setProgress(nextProgress);
      setCurrentIndex(nextIndex);
      setIsPlaying(false);

      if (audioRef.current) {
        audioRef.current.src = `/listening/${fileKey}.mp3`;
        audioRef.current.load();
        audioRef.current.onloadedmetadata = () => {
          seekToSentence(nextIndex, false);
        };
      }
    } catch (requestError) {
      console.error(requestError);
      setError(requestError.message || "Failed to load intensive listening resources");
      setAudioMeta(null);
      setSentences([]);
      setProgress([]);
      setCurrentIndex(0);
      setIsPlaying(false);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadPageData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [audioId, currentUserId]);

  useEffect(() => {
    const player = audioRef.current;
    if (!player || !currentSentence) return undefined;

    const handleTimeUpdate = () => {
      if (!isPlaying) return;
      const sentenceEnd = Number(currentSentence.end || 0);
      const sentenceStart = Number(currentSentence.start || 0);
      if (player.currentTime >= sentenceEnd) {
        player.currentTime = sentenceStart;
      }
    };

    const handlePlay = () => setIsPlaying(true);
    const handlePause = () => setIsPlaying(false);

    player.addEventListener("timeupdate", handleTimeUpdate);
    player.addEventListener("play", handlePlay);
    player.addEventListener("pause", handlePause);

    return () => {
      player.removeEventListener("timeupdate", handleTimeUpdate);
      player.removeEventListener("play", handlePlay);
      player.removeEventListener("pause", handlePause);
    };
  }, [currentSentence, isPlaying]);

  useEffect(() => {
    return () => {
      saveServerProgress();
    };
  }, []);

  const markCurrent = (value) => {
    if (!sentences.length) return;

    const nextProgress = [...latestProgressRef.current];
    nextProgress[currentIndex] = value;
    setProgress(nextProgress);
    latestProgressRef.current = nextProgress;

    const nextIndex = Math.min(currentIndex + 1, sentences.length - 1);
    saveServerProgress(nextProgress, nextIndex);

    if (currentIndex < sentences.length - 1) {
      setCurrentIndex(nextIndex);
      seekToSentence(nextIndex, true);
    }
  };

  const goNext = () => {
    if (!sentences.length) return;

    const nextProgress = [...latestProgressRef.current];
    if (Number(nextProgress[currentIndex] || 0) === 0) {
      nextProgress[currentIndex] = 3;
      setProgress(nextProgress);
      latestProgressRef.current = nextProgress;
    }

    const nextIndex = Math.min(currentIndex + 1, sentences.length - 1);
    saveServerProgress(nextProgress, nextIndex);

    if (currentIndex < sentences.length - 1) {
      setCurrentIndex(nextIndex);
      seekToSentence(nextIndex, true);
    }
  };

  const goPrevious = () => {
    if (currentIndex <= 0) return;
    moveToSentence(currentIndex - 1, { autoPlay: true });
  };

  const togglePlay = () => {
    const player = audioRef.current;
    if (!player || !currentSentence) return;

    if (player.paused) {
      seekToSentence(currentIndex, true);
    } else {
      player.pause();
    }
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
        <div className="content-area intensive-shell">
          <div className="page-title">
            <span>{audioMeta ? `In_Listening - ${audioMeta.title}` : "In_Listening - Intensive Listening"}</span>
            <div className="title-right">
              <button className="save-btn" onClick={() => saveServerProgress()}>
                Save Progress to Server
              </button>
              <button className="save-btn" onClick={() => onNavigate?.("collections", { audioId })}>
                ← Back to Collections
              </button>
            </div>
          </div>

          {!audioId ? (
            <div className="intensive-empty-state">Choose an audio material first.</div>
          ) : loading ? (
            <div className="intensive-empty-state">Loading intensive listening materials...</div>
          ) : error ? (
            <div className="intensive-empty-state intensive-error-state">{error}</div>
          ) : (
            <div className="listening-content">
              <div className="headphone-container">
                <div className="headphone-icon">
                  <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M3 18v-6a9 9 0 0 1 18 0v6" />
                    <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z" />
                  </svg>
                </div>
                <div className="ripple-ring" />
                <div className="ripple-ring" />
                <div className="ripple-ring" />
              </div>

              <div className="progress-wrapper">
                <div className="progress-label">
                  <span>Article Comprehension</span>
                  <span>{doneCount} / {totalCount}</span>
                </div>
                <div className="overall-progress-bar">
                  {progressSegments.map((segment, index) => (
                    <div key={segment.key} className={segment.className} style={{ width: `${100 / totalCount}%` }} data-index={index} />
                  ))}
                </div>
                <div className="progress-legend">
                  <div className="legend-item">
                    <div className="legend-color legend-understood" />
                    <span>Understood</span>
                  </div>
                  <div className="legend-item">
                    <div className="legend-color legend-not-understood" />
                    <span>Didn't Understand</span>
                  </div>
                  <div className="legend-item">
                    <div className="legend-color legend-skipped" />
                    <span>Skipped</span>
                  </div>
                  <div className="legend-item">
                    <div className="legend-color legend-current" />
                    <span>Current Sentence</span>
                  </div>
                </div>
              </div>

              <div className="status-text">Please select your understanding status</div>
              <div className="status-buttons">
                <div className="status-btn-group">
                  <button className="status-btn not-understood" onClick={() => markCurrent(2)}>
                    <span className="btn-icon">😵</span> Didn't understand
                  </button>
                  <button className="status-btn understood" onClick={() => markCurrent(1)}>
                    <span className="btn-icon">😊</span> Understood
                  </button>
                  <button className="status-btn next-btn" onClick={goNext}>
                    Next
                  </button>
                </div>
              </div>

              <div className="player-container">
                <div className="sentence-player-wrapper">
                  <div className="sentence-progress-bar">
                    <div className="sentence-progress-fill" style={{ width: `${totalCount ? ((currentIndex + 1) / totalCount) * 100 : 0}%` }} />
                  </div>
                  <div className="sentence-controls">
                    <button className="sentence-control-btn" onClick={goPrevious} disabled={currentIndex <= 0}>
                      ◀
                    </button>
                    <button className="sentence-control-btn primary" onClick={togglePlay}>
                      {isPlaying ? "❚❚" : "▶"}
                    </button>
                    <button className="sentence-control-btn" onClick={goNext} disabled={currentIndex >= totalCount - 1}>
                      ▶
                    </button>
                  </div>
                  <div className="sentence-info-card">
                    <div className="sentence-info-title">
                      Sentence {Math.min(currentIndex + 1, totalCount)} of {totalCount}
                    </div>
                    <div className="sentence-info-content">
                      <span className="sentence-content">
                        {currentSentence?.text || ""}
                        <span className="sentence-mask">hidden text (hover me)</span>
                      </span>
                    </div>
                  </div>
                  <audio ref={audioRef} className="native-audio" controls />
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
