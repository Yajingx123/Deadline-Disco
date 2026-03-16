export default function ResultSummary({ result }) {
  return (
    <section className="result">
      <h3>
        Score: {result.score} / {result.total}
      </h3>
      {result.perQuestion.map((row, index) => (
        <article key={row.questionId} className="result-item">
          <p>
            Question {index + 1} {row.correct ? "✓" : "✗"}
          </p>
          {!row.correct ? <p>Correct answer: {JSON.stringify(row.correctAnswer)}</p> : null}
          {row.explanation ? <p>Explanation: {row.explanation}</p> : null}
        </article>
      ))}
    </section>
  );
}

