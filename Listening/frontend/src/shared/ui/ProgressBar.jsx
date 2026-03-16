export default function ProgressBar({ answered = 0, total = 0 }) {
  const safeTotal = total || 1;
  const percent = Math.round((answered / safeTotal) * 100);

  return (
    <div className="progress-wrap">
      <div className="progress-text">Progress: {percent}%</div>
      <div className="progress-track">
        <div className="progress-fill" style={{ width: `${percent}%` }} />
      </div>
    </div>
  );
}

