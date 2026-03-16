import ResultSummary from "../components/ResultSummary";

export default function ResultPage({ result, onReview, onBack }) {
  return (
    <section>
      <h2>Result</h2>
      <ResultSummary result={result} />
      <div className="mode-grid">
        <button onClick={onReview}>Review Exam</button>
        <button className="secondary" onClick={onBack}>
          Back to Exam List
        </button>
      </div>
    </section>
  );
}

