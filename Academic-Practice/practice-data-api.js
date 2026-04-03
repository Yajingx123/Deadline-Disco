// =========================================
// 视频数据 API：数据库（管理员上传） + practice-data.js 本地目录合并
// =========================================

const API_BASE_URL = './api';

window.PracticeDataAPI = (function () {
  const modeMeta = {
    understand: {
      label: "Listening and Understand",
      answerType: "text"
    },
    respond: {
      label: "Listening and Respond",
      answerType: "text"
    }
  };

  function mapFromApiRow(video) {
    return {
      id: video.video_id,
      mode: video.mode,
      title: video.title,
      type: video.type,
      difficulty: video.difficulty,
      duration: video.duration,
      source: video.source,
      country: video.country,
      author: video.author,
      timeSpecific: video.time_specific,
      videoUrl: video.video_url,
      transcriptUrl: video.transcript_url,
      vttUrl: video.vtt_url,
      labelsUrl: video.labels_url,
      sampleNotesUrl: video.sample_notes_url,
      coverUrl: video.cover_url,
      flagUrl: video.flag_url,
      transcriptText: video.transcript_text,
      question: video.question,
      answerText: video.answer_text,
      dataSource: 'db'
    };
  }

  /** 与旧版 practice-data.js（本地 JSON）对齐的字段，供 practice-app-api 播放与封面解析 */
  function mapFromLocalJson(v) {
    return {
      id: v.id,
      mode: v.mode,
      title: v.title,
      type: v.type,
      difficulty: v.difficulty,
      duration: v.duration,
      source: v.source,
      country: v.country,
      author: v.author,
      timeSpecific: v.timeSpecific,
      videoUrl: v.videoUrl || null,
      videoPath: v.videoPath,
      videoFile: v.videoFile,
      transcriptUrl: v.transcriptPath || v.transcriptUrl,
      vttUrl: v.vttUrl || null,
      labelsUrl: v.labelsUrl || null,
      sampleNotesUrl: v.sampleNotesUrl || null,
      coverUrl: v.coverUrl || null,
      coverFile: v.coverFile,
      flagUrl: v.flagUrl || null,
      transcriptText: v.transcriptText,
      question: v.question,
      answerText: v.answerText,
      dataSource: 'local'
    };
  }

  function localVideosForMode(mode) {
    if (typeof window === 'undefined' || !window.PracticeData || !Array.isArray(window.PracticeData.videos)) {
      return [];
    }
    return window.PracticeData.videos.filter(function (x) {
      return x.mode === mode;
    });
  }

  /**
   * 列表：每个 mode 固定先出 practice-data.js 中的本地条目（默认 12 条，顺序与文件一致），
   * 再追加数据库中「id 不在本地集合里」的条目（管理员新增的第 13 条及以后）。同 id 时以本地为准。
   */
  async function getVideos(filters) {
    const mode = (filters && filters.mode) || 'understand';
    const localRaw = localVideosForMode(mode);
    const localList = localRaw.map(mapFromLocalJson);
    const localIdSet = new Set(localList.map(function (v) {
      return String(v.id);
    }));

    let apiList = [];
    try {
      const params = new URLSearchParams();
      params.append('action', 'list');
      params.append('mode', mode);
      const response = await fetch(API_BASE_URL + '/videos.php?' + params.toString());
      const result = await response.json();
      if (result.ok && Array.isArray(result.data)) {
        apiList = result.data.map(mapFromApiRow);
      } else if (!result.ok) {
        console.error('Failed to fetch videos:', result.message);
      }
    } catch (error) {
      console.error('Error fetching videos:', error);
    }

    const extras = apiList.filter(function (v) {
      return !localIdSet.has(String(v.id));
    });

    return localList.concat(extras);
  }

  /**
   * 详情：先本地（默认 12 条），再数据库（管理员新增 id）。
   */
  async function getVideoById(videoId) {
    if (typeof window !== 'undefined' && window.PracticeData && Array.isArray(window.PracticeData.videos)) {
      const found = window.PracticeData.videos.find(function (x) {
        return String(x.id) === String(videoId);
      });
      if (found) {
        return mapFromLocalJson(found);
      }
    }

    try {
      const response = await fetch(
        API_BASE_URL + '/videos.php?action=detail&id=' + encodeURIComponent(videoId)
      );
      const result = await response.json();
      if (result.ok && result.data) {
        return mapFromApiRow(result.data);
      }
    } catch (error) {
      console.error('Error fetching video detail:', error);
    }

    return null;
  }

  return {
    modeMeta: modeMeta,
    getVideos: getVideos,
    getVideoById: getVideoById
  };
})();
