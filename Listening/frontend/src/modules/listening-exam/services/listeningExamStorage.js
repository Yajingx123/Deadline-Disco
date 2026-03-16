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

export async function loadExamProgress(examId, mode) {
  const data = await callApi(
    `/api/exams/${examId}/progress?mode=${encodeURIComponent(mode)}`,
    {},
    userIdForMode(mode)
  );
  return data?.progress || null;
}

export async function saveExamProgress(examId, mode, payload) {
  await callApi(
    `/api/exams/${examId}/progress?mode=${encodeURIComponent(mode)}`,
    {
      method: "PUT",
      body: JSON.stringify(payload)
    },
    userIdForMode(mode)
  );
}

export async function clearExamProgress(examId, mode) {
  await callApi(
    `/api/exams/${examId}/progress?mode=${encodeURIComponent(mode)}`,
    { method: "DELETE" },
    userIdForMode(mode)
  );
}

export async function loadExamResult(examId, mode) {
  const data = await callApi(
    `/api/exams/${examId}/result?mode=${encodeURIComponent(mode)}`,
    {},
    userIdForMode(mode)
  );
  return data?.result || null;
}

export async function saveExamResult(examId, payload, mode) {
  await callApi(
    `/api/exams/${examId}/result?mode=${encodeURIComponent(mode)}`,
    {
      method: "PUT",
      body: JSON.stringify(payload)
    },
    userIdForMode(mode)
  );
}

export async function submitExam(examId, mode, answers) {
  const payload = { mode, answers };
  const data = await callApi(
    `/api/exams/${examId}/submit?mode=${encodeURIComponent(mode)}`,
    {
      method: "POST",
      body: JSON.stringify(payload)
    },
    userIdForMode(mode)
  );
  return data?.result || null;
}

export async function loadExamStatuses(exams, mode = "exam") {
  const data = await callApi(
    `/api/exam-status?mode=${encodeURIComponent(mode)}`,
    {},
    userIdForMode(mode)
  );
  return data?.statuses || {};
}
