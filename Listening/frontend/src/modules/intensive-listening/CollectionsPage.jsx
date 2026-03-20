import { useEffect, useState } from "react";
import "./CollectionsPage.css";

const API_BASE = import.meta.env?.VITE_API_BASE_URL || "http://127.0.0.1:8000";

export default function CollectionsPage({ onNavigate, currentUserId = 1, selectedAudioId = null }) {
  const [collections, setCollections] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const loadCollections = async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await fetch(`${API_BASE}/api/audio/collection?user_id=${currentUserId}`);
      const data = await res.json();

      if (data.code === 0) {
        setCollections(data.data);
      } else {
        setError(data.msg || "Failed to fetch data");
      }
    } catch (err) {
      setError("Network error: " + err.message);
    } finally {
      setLoading(false);
    }
  };

  const startPractice = (audioId) => {
    if (onNavigate) {
      onNavigate("player", { audioId });
    }
  };

  const removeCollection = async (audioId) => {
    if (!window.confirm("Remove from collection?")) return;

    try {
      const res = await fetch(`${API_BASE}/api/audio/collection/cancel?user_id=${currentUserId}&audio_id=${audioId}`);
      const data = await res.json();

      if (data.code === 0) {
        alert("Removed successfully");
        setCollections(collections.filter(item => item.audio_id !== audioId));
      } else {
        alert(data.msg || "Failed to remove");
      }
    } catch (err) {
      alert("Network error");
    }
  };

  const goToInclusiveListen = (audioId) => {
    if (onNavigate) {
      onNavigate("intensivelistening", { audioId });
    }
  };

  const goToIntensiveListening = (audioId) => {
    if (onNavigate) {
      onNavigate("intensivelistening", { audioId });
    }
  };

  useEffect(() => {
    loadCollections();
  }, [currentUserId]);

  const fallbackAudioId = selectedAudioId || collections[0]?.audio_id || null;

  return (
    <div className="main-container">
      <div className="top-tabs">
        <button className="tab active" onClick={() => loadCollections()}>
          Collections
        </button>
        <button className="tab" onClick={() => onNavigate?.("community", { audioId: fallbackAudioId })}>
          Community
        </button>
        <button
          className="tab"
          disabled={!fallbackAudioId}
          onClick={() => fallbackAudioId && onNavigate?.("player", { audioId: fallbackAudioId })}
        >
          Player
        </button>
      </div>

      <div className="content-container">
        <div className="content-area">
          <div className="page-title">
            <span>Collections - Listening</span>
          </div>

          <div className="test-cards">
            {loading && <div className="loading">Loading...</div>}
            {error && <div className="error">{error}</div>}
            {!loading && !error && collections.length === 0 && (
              <div className="no-data">No audio materials in your collection yet</div>
            )}

            {collections.map((material) => (
              <div className="test-card" key={material.audio_id || material.id}>
                <div className="card-header">
                  <h3 className="card-title">
                    {material.title || material.name || `Audio ${material.audio_id || material.id}`}
                  </h3>
                  <div className="play-icon">▶</div>
                </div>

                <div className="card-details">
                  <div className="detail-item">
                    <span className="detail-label">Audio length:</span>
                    <span>{material.duration || "Unknown duration"}</span>
                  </div>
                  <div className="detail-item">
                    <span className="detail-label">Difficulty:</span>
                    <span>{material.difficulty || "Medium"}</span>
                  </div>
                  <div className="detail-item">
                    <span className="detail-label">Current Index:</span>
                    <span>{material.current_index || "Not tested"}</span>
                  </div>
                  <div className="detail-item">
                    <span className="detail-label">Percent:</span>
                    <span>{(material.progress_percent || 0) + "%"}</span>
                  </div>
                </div>

                <div className="progress-bar">
                  <div
                    className="progress-fill"
                    style={{ width: (material.progress_percent || 0) + "%" }}
                  />
                </div>

                <div className={`status ${(material.status || "not-started").toLowerCase()}`}>
                  Status: {material.status || "Not Started"}
                </div>

                <div className="card-buttons">
                  <div className="button-row">
                    <button className="start-button" onClick={() => startPractice(material.audio_id || material.id)}>
                      Start
                    </button>
                    <button className="remove-button" onClick={() => removeCollection(material.audio_id || material.id)}>
                      Remove
                    </button>
                  </div>
                  <button
                    className="listening-btn"
                    onClick={() => goToInclusiveListen(material.audio_id || material.id)}
                  >
                    Intensive Listening
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
