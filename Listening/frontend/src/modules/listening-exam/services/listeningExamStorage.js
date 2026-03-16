const API_BASE = import.meta.env?.VITE_API_BASE_URL || "http://localhost:8000";

async function callApi(path, init = {}, userId) {
  const url = `${API_BASE}${path}${path.includes("?") ? "&" : "?"}userId=${encodeURIComponent(userId)}`;
  const response = await fetch(url, {
    headers: { "Content-Type": "application/json" },
    ...init
  });
  if (!response.ok) throw new Error(`API request failed: ${response.status}`);
  return response.status === 204 ? null : response.json();
}

export async function loadExamProgress(examId, mode, userId) {
  const data = await callApi(
    `/api/exams/${examId}/progress?mode=${encodeURIComponent(mode)}`,
    {},
    userId
  );
  return data?.progress || null;
}

export async function saveExamProgress(examId, mode, payload, userId) {
  await callApi(
    `/api/exams/${examId}/progress?mode=${encodeURIComponent(mode)}`,
    {
      method: "PUT",
      body: JSON.stringify(payload)
    },
    userId
  );
}

export async function clearExamProgress(examId, mode, userId) {
  await callApi(
    `/api/exams/${examId}/progress?mode=${encodeURIComponent(mode)}`,
    { method: "DELETE" },
    userId
  );
}

export async function loadExamResult(examId, mode, userId) {
  const data = await callApi(
    `/api/exams/${examId}/result?mode=${encodeURIComponent(mode)}`,
    {},
    userId
  );
  return data?.result || null;
}

export async function saveExamResult(examId, payload, mode, userId) {
  await callApi(
    `/api/exams/${examId}/result?mode=${encodeURIComponent(mode)}`,
    {
      method: "PUT",
      body: JSON.stringify(payload)
    },
    userId
  );
}

export async function submitExam(examId, mode, answers, userId) {
  const payload = { mode, answers };
  const data = await callApi(
    `/api/exams/${examId}/submit?mode=${encodeURIComponent(mode)}`,
    {
      method: "POST",
      body: JSON.stringify(payload)
    },
    userId
  );
  return data?.result || null;
}

export async function loadExamStatuses(exams, mode = "exam", userId) {
  const data = await callApi(
    `/api/exam-status?mode=${encodeURIComponent(mode)}`,
    {},
    userId
  );
  return data?.statuses || {};
}
