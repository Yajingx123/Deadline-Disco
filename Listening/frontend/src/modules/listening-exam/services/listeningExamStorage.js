const progressKey = (examId, mode) => `listening_exam_progress_${examId}_${mode}`;
const resultKey = (examId, mode) => `listening_exam_result_${examId}_${mode}`;
const API_BASE = import.meta.env?.VITE_API_BASE_URL || "http://localhost:8000";
const USER_ID = "demo-user";
const userIdForMode = (mode) => `${USER_ID}_${mode}`;

async function callApi(path, init = {}, userId = USER_ID) {
  const url = `${API_BASE}${path}${path.includes("?") ? "&" : "?"}userId=${encodeURIComponent(userId)}`;
  const response = await fetch(url, {
    headers: { "Content-Type": "application/json" },
    ...init
  });
  if (!response.ok) throw new Error(`API request failed: ${response.status}`);
  return response.status === 204 ? null : response.json();
}

function loadLocal(keyFn, examId, mode) {
  try {
    const raw = localStorage.getItem(keyFn(examId, mode));
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function saveLocal(keyFn, examId, mode, payload) {
  localStorage.setItem(keyFn(examId, mode), JSON.stringify(payload));
}

function parseStamp(value) {
  if (typeof value === "number") return value;
  if (typeof value === "string") {
    const parsed = Date.parse(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }
  return 0;
}

export async function loadExamProgress(examId, mode) {
  const local = loadLocal(progressKey, examId, mode);
  try {
    const data = await callApi(
      `/api/exams/${examId}/progress?mode=${encodeURIComponent(mode)}`,
      {},
      userIdForMode(mode)
    );
    const remote = data?.progress || null;
    if (!local) return remote;
    if (!remote) return local;
    return parseStamp(local.updated_at) >= parseStamp(remote.updated_at) ? local : remote;
  } catch {
    return local;
  }
}

export async function saveExamProgress(examId, mode, payload) {
  saveLocal(progressKey, examId, mode, payload);
  try {
    await callApi(
      `/api/exams/${examId}/progress?mode=${encodeURIComponent(mode)}`,
      {
        method: "PUT",
        body: JSON.stringify(payload)
      },
      userIdForMode(mode)
    );
  } catch {
    // Ignore network failure and keep local fallback.
  }
}

export async function clearExamProgress(examId, mode) {
  localStorage.removeItem(progressKey(examId, mode));
  try {
    await callApi(
      `/api/exams/${examId}/progress?mode=${encodeURIComponent(mode)}`,
      { method: "DELETE" },
      userIdForMode(mode)
    );
  } catch {
    // Ignore network failure and keep local fallback.
  }
}

export async function loadExamResult(examId, mode) {
  try {
    const data = await callApi(
      `/api/exams/${examId}/result?mode=${encodeURIComponent(mode)}`,
      {},
      userIdForMode(mode)
    );
    return data?.result || null;
  } catch {
    return loadLocal(resultKey, examId, mode);
  }
}

export async function saveExamResult(examId, payload, mode) {
  saveLocal(resultKey, examId, mode, payload);
  try {
    await callApi(
      `/api/exams/${examId}/result?mode=${encodeURIComponent(mode)}`,
      {
        method: "PUT",
        body: JSON.stringify(payload)
      },
      userIdForMode(mode)
    );
  } catch {
    // Ignore network failure and keep local fallback.
  }
}

export async function submitExam(examId, mode, answers) {
  const payload = { mode, answers };
  try {
    const data = await callApi(
      `/api/exams/${examId}/submit?mode=${encodeURIComponent(mode)}`,
      {
        method: "POST",
        body: JSON.stringify(payload)
      },
      userIdForMode(mode)
    );
    if (data?.result) {
      saveLocal(resultKey, examId, mode, data.result);
      return data.result;
    }
  } catch {
    // Ignore and fallback below.
  }
  return null;
}

export async function loadExamStatuses(exams, mode = "exam") {
  try {
    const data = await callApi(
      `/api/exam-status?mode=${encodeURIComponent(mode)}`,
      {},
      userIdForMode(mode)
    );
    return data?.statuses || {};
  } catch {
    const statuses = {};
    for (const exam of exams) {
      const result = loadLocal(resultKey, exam.id, mode);
      statuses[exam.id] = {
        status: result ? "Completed" : "Not Started",
        answeredQuestions: result ? exam.questions.length : 0,
        bestScore: result ? `${result.score}/${result.total}` : "-"
      };
    }
    return statuses;
  }
}
