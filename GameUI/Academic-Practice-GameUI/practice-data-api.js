// =========================================
// 视频数据 API：统一从数据库读取（video_resources）
// =========================================

const API_BASE_URL = '/Academic-Practice/api';

function normalizeAcademicUrl(rawUrl) {
  const value = String(rawUrl || '').trim();
  if (!value) return '';
  if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:')) {
    return value;
  }
  if (value.startsWith('/')) {
    return value;
  }
  const clean = value.replace(/^\.\//, '');
  return `/Academic-Practice/${clean}`;
}

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
      videoUrl: normalizeAcademicUrl(video.video_url),
      transcriptUrl: normalizeAcademicUrl(video.transcript_url),
      vttUrl: normalizeAcademicUrl(video.vtt_url),
      labelsUrl: normalizeAcademicUrl(video.labels_url),
      sampleNotesUrl: normalizeAcademicUrl(video.sample_notes_url),
      coverUrl: normalizeAcademicUrl(video.cover_url),
      flagUrl: normalizeAcademicUrl(video.flag_url),
      transcriptText: video.transcript_text,
      question: video.question,
      answerText: video.answer_text,
      dataSource: 'db'
    };
  }

  async function getVideos(filters) {
    const params = new URLSearchParams();
    params.append('action', 'list');

    if (filters && filters.mode) params.append('mode', filters.mode);
    if (filters && filters.type && filters.type !== 'All') params.append('type', filters.type);
    if (filters && filters.difficulty && filters.difficulty !== 'All') params.append('difficulty', filters.difficulty);
    if (filters && filters.duration && filters.duration !== 'All') params.append('duration', filters.duration);
    if (filters && filters.source && filters.source !== 'All') params.append('source', filters.source);
    if (filters && filters.country && filters.country !== 'All') params.append('country', filters.country);
    if (filters && filters.search) params.append('search', filters.search);

    const response = await fetch(API_BASE_URL + '/videos.php?' + params.toString());
    const result = await response.json();

    if (!result.ok || !Array.isArray(result.data)) {
      throw new Error(result.message || 'Failed to fetch videos from database.');
    }

    return result.data.map(mapFromApiRow);
  }

  async function getVideoById(videoId) {
    const response = await fetch(
      API_BASE_URL + '/videos.php?action=detail&id=' + encodeURIComponent(videoId)
    );
    const result = await response.json();

    if (!result.ok || !result.data) {
      return null;
    }

    return mapFromApiRow(result.data);
  }

  return {
    modeMeta: modeMeta,
    getVideos: getVideos,
    getVideoById: getVideoById
  };
})();
