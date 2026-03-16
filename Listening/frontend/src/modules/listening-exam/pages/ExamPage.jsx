import { useEffect, useMemo, useRef, useState } from "react";
import AudioPlayer from "../../../shared/ui/AudioPlayer";
import DecisionModal from "../../../shared/ui/DecisionModal";
import QuestionRenderer, { isAnswerCorrect } from "../components/QuestionRenderer";
import {
  clearExamProgress,
  loadExamProgress,
  loadExamResult,
  saveExamProgress,
  saveExamResult,
  submitExam
} from "../services/listeningExamStorage";

function isAnswered(question, answer) {
  if (question.type === "multiple_choice") return Boolean(answer);
  if (question.type === "multiple_select") return Array.isArray(answer) && answer.length > 0;
  if (question.type === "fill_blank") return Boolean(String(answer || "").trim());
  if (question.type === "matching") {
    return (
      answer &&
      question.leftItems.every((item) => Boolean(answer[item.id]))
    );
  }
  if (question.type === "ordering") {
    return (
      Array.isArray(answer) &&
      answer.length === question.orderingItems.length &&
      answer.every(Boolean)
    );
  }
  return false;
}

function formatTime(totalSeconds) {
  const safe = Math.max(0, Math.floor(totalSeconds));
  const mm = String(Math.floor(safe / 60)).padStart(2, "0");
  const ss = String(safe % 60).padStart(2, "0");
  return `${mm}:${ss}`;
}

function hasMeaningfulProgress(progress) {
  if (!progress || progress.exam_status !== "in_progress") return false;
  if (typeof progress.answered_questions === "number" && progress.answered_questions > 0) return true;
  const answers = progress.answers;
  return Boolean(answers && typeof answers === "object" && Object.keys(answers).length > 0);
}

export default function ExamPage({ exam, mode, onSubmit, onExit }) {
  const [answers, setAnswers] = useState({});
  const audioTimeRef = useRef(0);
  const [initialAudioTime, setInitialAudioTime] = useState(0);
  const [loaded, setLoaded] = useState(false);
  const [timerSeconds, setTimerSeconds] = useState(mode === "exam" ? exam.durationSeconds : 0);
  const [submitting, setSubmitting] = useState(false);
  const [pendingResume, setPendingResume] = useState(null);
  const [showSubmitConfirm, setShowSubmitConfirm] = useState(false);
  const [submitPrompt, setSubmitPrompt] = useState({
    title: "Submit Exam",
    message: "Submit now and finish this exam?",
    confirmLabel: "Submit Now",
    cancelLabel: "Keep Editing"
  });
  const latestProgressRef = useRef(null);
  const allowProgressPersistenceRef = useRef(true);

  useEffect(() => {
    let active = true;
    (async () => {
      const saved = await loadExamProgress(exam.id, mode);
      if (!active) return;

      const existingResult = await loadExamResult(exam.id, mode);
      if (!active) return;

      const savedUpdatedAt = Number(saved?.updated_at || 0);
      const resultSubmittedAt = Number(existingResult?.submittedAt || 0);
      const shouldResume =
        hasMeaningfulProgress(saved) &&
        (!existingResult || savedUpdatedAt > resultSubmittedAt);

      if (shouldResume) {
        setPendingResume(saved);
      } else {
        if (saved && !hasMeaningfulProgress(saved)) {
          await clearExamProgress(exam.id, mode);
          if (!active) return;
        } else if (existingResult && saved?.exam_status === "in_progress") {
          await clearExamProgress(exam.id, mode);
          if (!active) return;
        }
        setPendingResume(null);
        setLoaded(true);
      }
    })();
    return () => {
      active = false;
    };
  }, [exam.id, mode]);

  useEffect(() => {
    if (!loaded || submitting) return;
    const id = setInterval(() => {
      setTimerSeconds((prev) => {
        if (mode === "practice") return prev + 1;
        return prev > 0 ? prev - 1 : 0;
      });
    }, 1000);
    return () => clearInterval(id);
  }, [loaded, mode, submitting]);

  const answeredSet = useMemo(() => {
    const set = new Set();
    exam.questions.forEach((q, i) => {
      if (isAnswered(q, answers[q.id])) set.add(i);
    });
    return set;
  }, [answers, exam.questions]);

  useEffect(() => {
    if (!loaded) return;
    latestProgressRef.current = {
      exam_id: exam.id,
      mode,
      current_question: 0,
      answers,
      // Both modes store answer trace only; audio position is intentionally not persisted.
      audio_time: 0,
      answered_questions: answeredSet.size,
      exam_status: "in_progress",
      exam_duration_seconds: mode === "exam" ? exam.durationSeconds : null,
      timer_seconds: timerSeconds,
      updated_at: Date.now()
    };
  }, [loaded, exam.id, exam.durationSeconds, mode, answers, answeredSet.size, timerSeconds]);

  useEffect(() => {
    if (!loaded) return;
    const saveNow = () => {
      if (!allowProgressPersistenceRef.current) return;
      if (!latestProgressRef.current) return;
      if (!hasMeaningfulProgress(latestProgressRef.current)) {
        clearExamProgress(exam.id, mode);
        return;
      }
      saveExamProgress(exam.id, mode, latestProgressRef.current);
    };

    const intervalId = setInterval(saveNow, 6000);
    window.addEventListener("beforeunload", saveNow);

    return () => {
      clearInterval(intervalId);
      window.removeEventListener("beforeunload", saveNow);
      saveNow();
    };
  }, [loaded, exam.id, mode]);

  useEffect(() => {
    if (mode !== "exam") return;
    if (!loaded || submitting) return;
    if (timerSeconds > 0) return;
    submit(true);
  }, [mode, timerSeconds, loaded, submitting]);

  const answered = answeredSet.size;
  const unanswered = exam.questions.length - answered;

  const requestManualSubmit = () => {
    if (unanswered > 0) {
      setSubmitPrompt({
        title: "Unanswered Questions",
        message: `You still have ${unanswered} unanswered question${unanswered > 1 ? "s" : ""}. Submit anyway?`,
        confirmLabel: "Submit Anyway",
        cancelLabel: "Back to Questions"
      });
    } else {
      setSubmitPrompt({
        title: "Submit Exam",
        message: "Submit now and finish this exam?",
        confirmLabel: "Submit Now",
        cancelLabel: "Keep Editing"
      });
    }
    setShowSubmitConfirm(true);
  };

  const handleBack = async () => {
    if (!submitting && loaded) {
      if (answered > 0 && latestProgressRef.current) {
        await saveExamProgress(exam.id, mode, latestProgressRef.current);
      } else {
        await clearExamProgress(exam.id, mode);
      }
    }
    onExit();
  };

  const submit = async (force = false) => {
    if (submitting) return;
    if (!force) setShowSubmitConfirm(false);
    allowProgressPersistenceRef.current = false;
    setSubmitting(true);
    const serverResult = await submitExam(exam.id, mode, answers);
    const result =
      serverResult ||
      (() => {
        const perQuestion = exam.questions.map((q) => {
          const userAnswer = answers[q.id];
          return {
            questionId: q.id,
            correct: isAnswerCorrect(q, userAnswer),
            userAnswer,
            correctAnswer: q.correctAnswer,
            explanation: q.explanation
          };
        });
        const score = perQuestion.filter((row) => row.correct).length;
        return {
          examId: exam.id,
          mode,
          score,
          total: exam.questions.length,
          perQuestion,
          submittedAt: Date.now()
        };
      })();
    await saveExamResult(exam.id, result, mode);
    await clearExamProgress(exam.id, mode);
    latestProgressRef.current = null;
    onSubmit(result, answers);
  };

  return (
    <section className="exam-shell">
      <div className="exam-workbench">
        <section className="exam-toolbar card">
          <div className="toolbar-line">
            <span className="chip">{mode === "exam" ? "Exam Mode" : "Practice Mode"}</span>
            <span className="chip">{mode === "exam" ? "Time Left" : "Time"} {formatTime(timerSeconds)}</span>
            <span className="chip">Answered {answered}/{exam.questions.length}</span>
            <div className="toolbar-right">
              <button className="submit-btn strong" onClick={requestManualSubmit} disabled={submitting}>
                {submitting ? "Submitting..." : "Submit"}
              </button>
              <button className="secondary submit-btn" onClick={handleBack}>
                Back
              </button>
            </div>
          </div>
          <div className="toolbar-audio-row">
            <AudioPlayer
              src={exam.audioUrl}
              mode={mode}
              initialTime={initialAudioTime}
              onTimeUpdate={(seconds) => {
                audioTimeRef.current = seconds;
              }}
            />
          </div>
        </section>

        <section className="question-stack">
          {exam.questions.map((q, idx) => (
            <article className="question-box card" key={q.id}>
              <div className="session-head">
                <h3>
                  Question {idx + 1} / {exam.questions.length}
                </h3>
              </div>
              <QuestionRenderer
                question={q}
                answer={answers[q.id]}
                onAnswerChange={(value) => setAnswers((prev) => ({ ...prev, [q.id]: value }))}
              />
            </article>
          ))}
        </section>
      </div>

      {pendingResume ? (
        <DecisionModal
          title="Unfinished Exam Found"
          message="You have an unfinished exam. Do you want to resume where you left off?"
          confirmLabel="Resume"
          cancelLabel="Restart"
          onConfirm={() => {
            setAnswers(pendingResume.answers || {});
            // Resume answers only; audio always restarts from 0 for both modes.
            audioTimeRef.current = 0;
            setInitialAudioTime(0);
            if (typeof pendingResume.timer_seconds === "number") {
              setTimerSeconds(pendingResume.timer_seconds);
            }
            setPendingResume(null);
            setLoaded(true);
          }}
          onCancel={async () => {
            await clearExamProgress(exam.id, mode);
            setPendingResume(null);
            setLoaded(true);
          }}
        />
      ) : null}

      {showSubmitConfirm ? (
        <DecisionModal
          title={submitPrompt.title}
          message={submitPrompt.message}
          confirmLabel={submitPrompt.confirmLabel}
          cancelLabel={submitPrompt.cancelLabel}
          onConfirm={() => submit(true)}
          onCancel={() => setShowSubmitConfirm(false)}
        />
      ) : null}
    </section>
  );
}
