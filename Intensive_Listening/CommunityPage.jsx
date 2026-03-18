import { useEffect, useState } from "react";
import "./CommunityPage.css";

// ✅ 接收父组件传来的 onNavigate 回调
export default function CommunityPage({ onNavigate }) {
  const [audioList, setAudioList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showNotification, setShowNotification] = useState(false);
  const [notificationMsg, setNotificationMsg] = useState("");
  const [submittingAudioIds, setSubmittingAudioIds] = useState([]);

  const loadAudioData = async () => {
    try {
      setLoading(true);
      const response = await fetch("http://127.0.0.1:8000/api/audio/all-with-collect?user_id=1");
      if (!response.ok) throw new Error("API request failed");

      const data = await response.json();
      if (data.code === 0) {
        setAudioList(data.data);
      } else {
        setError(data.msg || "Failed to fetch audio data");
      }
    } catch (err) {
      setError("Network error: " + err.message);
    } finally {
      setLoading(false);
    }
  };

  const collectAudio = async (audio) => {
    if (audio.is_collected === 1 || submittingAudioIds.includes(audio.audio_id)) return;

    const userId = 1;
    const audioId = audio.audio_id;

    try {
      setSubmittingAudioIds([...submittingAudioIds, audioId]);
      const response = await fetch(
        `http://127.0.0.1:8000/api/audio/collection/add?user_id=${userId}&audio_id=${audioId}`
      );
      if (!response.ok) throw new Error("Collect request failed");

      const result = await response.json();
      if (result.code === 0) {
        setAudioList(
          audioList.map((item) =>
            item.audio_id === audioId ? { ...item, is_collected: 1 } : item
          )
        );
        setNotificationMsg("Article added to your collection!");
      } else {
        setNotificationMsg("Failed to collect: " + result.msg);
      }
    } catch (err) {
      console.error("Collect failed:", err);
      setNotificationMsg("Network error, please try again later");
    } finally {
      setSubmittingAudioIds(submittingAudioIds.filter((id) => id !== audioId));
      setShowNotification(true);
      setTimeout(() => setShowNotification(false), 3000);
    }
  };

  useEffect(() => {
    loadAudioData();
  }, []);

  return (
    <div className="main-container">
      {/* ✅ 修复：移除 sidebar，改为与 CollectionsPage 一致的顶部 Tabs */}
      <div className="top-tabs">
        <button 
          className="tab" 
          // ✅ 修复：点击跳回 Collections，使用回调而非 window.location
          onClick={() => onNavigate && onNavigate("collections")}
        >
          Collections
        </button>
        <button className="tab active">Community</button>
        <button 
          className="tab" 
          onClick={() => onNavigate && onNavigate("player")}
        >
          Player
        </button>
      </div>

      {/* 社区内容区 */}
      <div className="content-container">
        <div className="content-area">
          <div className="page-title">
            <span>Community - Learning Exchange</span>
          </div>
          <div className="community-content">
            <h2>Listening Articles</h2>
            <p>Discover and collect quality listening materials</p>

            {loading && <div style={{ textAlign: "center", padding: "20px" }}>Loading...</div>}
            {error && <div style={{ color: "red", textAlign: "center", padding: "20px" }}>{error}</div>}

            {!loading && !error && (
              <div className="article-cards">
                {audioList.map((audio) => (
                  <div className="article-card" key={audio.audio_id}>
                    <div className="article-header">
                      <h3 className="article-title">{audio.title}</h3>
                      <div className="article-stats">
                        <span className="article-duration">{audio.duration}</span>
                        <span className="article-difficulty">{audio.difficulty}</span>
                      </div>
                    </div>
                    <div className="article-content">
                      <p className="article-description">{audio.description || "No description available"}</p>
                      {audio.tags && (
                        <div className="article-tags">
                          {audio.tags.split(",").map((tag) => (
                            <span className="article-tag" key={tag.trim()}>
                              {tag.trim()}
                            </span>
                          ))}
                        </div>
                      )}
                    </div>
                    <div className="article-footer">
                      <span className="article-author">{audio.author || "Unknown Author"}</span>
                      <div className="article-actions">
                        <button
                          className={`article-button ${audio.is_collected === 1 ? "added" : ""}`}
                          onClick={() => collectAudio(audio)}
                          disabled={audio.is_collected === 1 || submittingAudioIds.includes(audio.audio_id)}
                        >
                          {audio.is_collected === 1 ? "Collected" : "Add to Collection"}
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {showNotification && (
        <div className="notification">{notificationMsg}</div>
      )}
    </div>
  );
}