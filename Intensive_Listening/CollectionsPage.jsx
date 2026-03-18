import { useEffect, useState } from "react";
import "./CollectionsPage.css";

// 接收父组件的导航方法，用于Tab跳转
export default function CollectionsPage({ onNavigate }) {
  const [collections, setCollections] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const userId = 1;

  // 加载收藏列表
  const loadCollections = async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await fetch(`http://localhost:8000/api/audio/collection?user_id=${userId}`);
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

  // 开始练习
  const startPractice = (audioId) => {
    // 使用传入的 onNavigate 函数进行导航
    if (onNavigate) { // ✅ 使用正确的变量名 onNavigate
      // 先切换到 'player' 视图
      onNavigate('player'); 
      // 然后通过 URL search params 传递 audioId
      // 为了让 Player 组件能接收到这个 ID
      const url = new URL(window.location);
      url.searchParams.set('audio_id', audioId);
      window.history.pushState({}, '', url);
      // 注意：Player 组件需要能够从 URL search params 中读取 ID
    } else {
      // 如果没有提供 onNavigate，则回退到原始的页面跳转行为
      window.location.href = `player.html?audio_id=${audioId}`;
    }
  };

  // 取消收藏
  const removeCollection = async (audioId) => {
    if (!window.confirm("Remove from collection?")) return;

    try {
      const res = await fetch(`http://127.0.0.1:8000/api/audio/collection/cancel?user_id=${userId}&audio_id=${audioId}`);
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

  // 前往 Inclusive Listening
  const goToInclusiveListen = (audioId) => {
    window.location.href = `in_listen.html?audio_id=${audioId}`;
  };

    // 前往 Intensive Listening (强制跳转到独立 HTML 文件)
  const goToIntensiveListening = (audioId) => {
    // 直接跳转，不再检查 onNavigate
    window.location.href = `in_listen.html?audio_id=${audioId}`;
  };

  useEffect(() => {
    loadCollections();
  }, []);

  return (
    <div className="main-container">
      {/* 顶部 Tab 栏（核心跳转区域，无侧边栏） */}
      <div className="top-tabs">
        <button 
          className="tab active" 
          // 点击自身Tab：刷新当前组件（可选）
          onClick={() => loadCollections()}
        >
          Collections
        </button>
        <button 
          className="tab" 
          // 点击Community Tab：调用父组件方法跳转到CommunityPage
          onClick={() => onNavigate("community")}
        >
          Community
        </button>
        <button 
          className="tab" 
          // 预留Player Tab跳转
          onClick={() => onNavigate("player")}
        >
          Player
        </button>
      </div>

      {/* 内容区（无侧边栏，直接显示内容） */}
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
                    onClick={() => goToIntensiveListening(material.audio_id || material.id)}
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