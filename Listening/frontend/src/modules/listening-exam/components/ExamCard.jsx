export default function ExamCard({ exam, statusInfo, onStart }) {
  const practiceCompleted = Boolean(statusInfo.practiceCompleted);
  const examCompleted = Boolean(statusInfo.examCompleted);

  return (
    <article className="card exam-card">
      <div className="exam-cover">▶</div>
      <h3>{exam.title}</h3>
      <p>Audio length: {Math.round(exam.durationSeconds / 60)} minutes</p>
      <p>Questions: {exam.questions.length}</p>
      <p>Difficulty: {exam.difficulty}</p>
      <p>Best score: {statusInfo.bestScore ?? "-"}</p>
      <div className="exam-card-mode-progress" aria-label="Practice and exam completion">
        <span className={practiceCompleted ? "exam-card-half filled" : "exam-card-half"} />
        <span className={examCompleted ? "exam-card-half filled" : "exam-card-half"} />
      </div>
      <button onClick={() => onStart(exam)}>Start Practice</button>
    </article>
  );
}
