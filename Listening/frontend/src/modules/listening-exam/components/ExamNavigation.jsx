export default function ExamNavigation({
  total,
  currentIndex,
  onPrev,
  onNext
}) {
  return (
    <nav className="exam-nav compact simple">
      <span className="chip">Q {currentIndex + 1} / {total}</span>
      <div className="pager compact">
        <button onClick={onPrev} disabled={currentIndex === 0}>
          Previous
        </button>
        <button onClick={onNext} disabled={currentIndex === total - 1}>
          Next
        </button>
      </div>
    </nav>
  );
}
