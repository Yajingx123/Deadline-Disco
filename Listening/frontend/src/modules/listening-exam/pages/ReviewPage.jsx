import { useState } from "react";
import TranscriptViewer from "../components/TranscriptViewer";

function formatAnswer(value) {
  if (value == null) return "No answer";
  if (typeof value === "string") return value;
  return JSON.stringify(value);
}

function AnswerLine({ label, value, tone = "user" }) {
  return (
    <p className={`answer-line ${tone}`}>
      <span className="answer-label">{label}:</span> <span className="answer-value">{formatAnswer(value)}</span>
    </p>
  );
}

function renderQuestionDetail(question, row) {
  return (
    <div className="review-question-detail">
      <h4 className="review-question-title">{question.questionText}</h4>

      {question.options ? (
        <div className="review-options">
          {question.options.map((opt) => {
            const isUserSelected = Array.isArray(row?.userAnswer)
              ? row.userAnswer.includes(opt.id)
              : row?.userAnswer === opt.id;
            const isCorrect = Array.isArray(question.correctAnswer)
              ? question.correctAnswer.includes(opt.id)
              : question.correctAnswer === opt.id;
            return (
              <div
                key={opt.id}
                className={`review-option ${isUserSelected ? "user" : ""} ${isCorrect ? "correct" : ""}`}
              >
                <span>
                  {opt.id}. {opt.label}
                </span>
                <span>
                  {isUserSelected ? "Your choice" : ""} {isCorrect ? "Correct" : ""}
                </span>
              </div>
            );
          })}
        </div>
      ) : null}

      {question.type === "matching" ? (
        <div className="review-structured">
          <p><strong>Matching Left Items:</strong></p>
          {question.leftItems?.map((item) => (
            <p key={`left-${item.id}`}>
              {item.id}. {item.label}
            </p>
          ))}
          <p><strong>Matching Right Items:</strong></p>
          {question.rightItems?.map((item) => (
            <p key={`right-${item.id}`}>
              {item.id}. {item.label}
            </p>
          ))}
          <p><strong>Your Match vs Correct:</strong></p>
          {question.leftItems.map((item) => {
            const userValue = row?.userAnswer?.[item.id];
            const correctValue = question.correctAnswer?.[item.id];
            const isMatchCorrect = userValue === correctValue;
            return (
              <div key={item.id} className="review-match-answer">
                <p className="review-match-label">{item.label}</p>
                <AnswerLine label="Your answer" value={userValue} tone={isMatchCorrect ? "correct" : "wrong"} />
                <AnswerLine label="Correct answer" value={correctValue} tone="correct" />
              </div>
            );
          })}
        </div>
      ) : null}

      {question.type === "ordering" ? (
        <div className="review-structured">
          <p><strong>Ordering Items:</strong></p>
          {(question.orderingItems || []).map((item) => (
            <p key={item.id}>
              {item.id}. {item.label}
            </p>
          ))}
          <AnswerLine
            label="Your answer"
            value={row?.userAnswer}
            tone={JSON.stringify(row?.userAnswer) === JSON.stringify(question.correctAnswer) ? "correct" : "wrong"}
          />
          <AnswerLine label="Correct answer" value={question.correctAnswer} tone="correct" />
        </div>
      ) : null}

      {!question.options && question.type !== "matching" && question.type !== "ordering" ? (
        <div className="review-structured">
          <AnswerLine label="Your answer" value={row?.userAnswer} tone={row?.correct ? "correct" : "wrong"} />
          <AnswerLine label="Correct answer" value={question.correctAnswer} tone="correct" />
        </div>
      ) : null}

      <p className="review-explain">
        Explanation: {question.explanation || row?.explanation || "No explanation provided."}
      </p>
    </div>
  );
}

export default function ReviewPage({ exam, result, onBack }) {
  const [activeQuestionId, setActiveQuestionId] = useState(exam.questions[0]?.id);
  const activeQuestion = exam.questions.find((q) => q.id === activeQuestionId);
  const resultMap = Object.fromEntries(result.perQuestion.map((row) => [row.questionId, row]));
  const correctCount = result.perQuestion.filter((row) => row.correct).length;

  return (
    <section>
      <div className="top-row">
        <h2>Review: {exam.title}</h2>
        <button className="secondary" onClick={onBack}>
          Back to Exam List
        </button>
      </div>
      <section className="audio-player review-audio-wrap">
        <audio className="review-audio" controls src={exam.audioUrl} preload="metadata" />
      </section>

      <section className="result-overview card">
        <div className="result-overview-head">
          <h3>Result Overview</h3>
          <p>
            Correct {correctCount} / {result.total}
          </p>
        </div>
        <div className="result-q-grid">
          {result.perQuestion.map((row, index) => (
            <div
              key={row.questionId}
              className={`result-q-pill ${row.correct ? "ok" : "bad"}`}
              title={row.correct ? "Correct" : "Incorrect"}
            >
              Q{index + 1}
            </div>
          ))}
        </div>
      </section>

      <div className="review-layout">
        <aside className="review-panel review-transcript card">
          <div className="review-panel-head">
            <h3>Full Transcript</h3>
          </div>
          <div className="review-panel-body">
            <TranscriptViewer transcript={exam.transcript} activeReference={activeQuestion?.transcriptReference} />
          </div>
        </aside>

        <section className="review-panel review-analysis card">
          <div className="review-panel-head">
            <h3>Question Analysis</h3>
          </div>
          <div className="review-panel-body">
            {exam.questions.map((q, index) => {
              const row = resultMap[q.id];
              return (
                <article
                  key={q.id}
                  className={`result-item review-item ${q.id === activeQuestionId ? "active" : ""}`}
                  onClick={() => setActiveQuestionId(q.id)}
                >
                  <div className="review-item-head">
                    <p className="review-item-index">Q{index + 1}</p>
                    <span className={`review-item-badge ${row?.correct ? "ok" : "bad"}`}>
                      {row?.correct ? "Correct" : "Incorrect"}
                    </span>
                  </div>
                  {renderQuestionDetail(q, row)}
                </article>
              );
            })}
          </div>
        </section>
      </div>
    </section>
  );
}
