import { mockExams } from "../data/mockExams";

const API_BASE = import.meta.env?.VITE_API_BASE_URL || "http://127.0.0.1:8000";
async function fetchJson(url) {
  const response = await fetch(url);
  if (!response.ok) throw new Error(`Fetch failed: ${response.status}`);
  return response.json();
}

export async function loadExams() {
  try {
    const summaryData = await fetchJson(`${API_BASE}/api/exams`);
    const summaries = summaryData?.exams || [];
    const detailPromises = summaries.map((summary) =>
      fetchJson(`${API_BASE}/api/exams/${summary.id}`).then((data) => data.exam)
    );
    const exams = await Promise.all(detailPromises);
    const mockById = Object.fromEntries(mockExams.map((item) => [item.id, item]));
    const normalized = exams
      .filter(Boolean)
      .map((exam) => ({
        ...exam,
        transcript:
          typeof exam.transcript === "string" && exam.transcript.trim().length > 120
            ? exam.transcript
            : mockById[exam.id]?.transcript || "",
        questions: Array.isArray(exam.questions) ? exam.questions : []
      }));
    if (!normalized.length) throw new Error("No exams from backend");
    return normalized;
  } catch {
    return mockExams;
  }
}
