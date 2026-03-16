import { useEffect, useState } from "react";
import ExamCard from "../components/ExamCard";
import { loadExamStatuses } from "../services/listeningExamStorage";

export default function ExamList({ exams, onStart, currentUser }) {
  const [statusMap, setStatusMap] = useState({});

  useEffect(() => {
    if (!currentUser) return;
    let active = true;
    Promise.all([
      loadExamStatuses(exams, "practice", currentUser),
      loadExamStatuses(exams, "exam", currentUser)
    ]).then(
      ([practiceStatuses, examStatuses]) => {
        if (!active) return;
        const merged = {};
        for (const exam of exams) {
          merged[exam.id] = {
            practiceCompleted: practiceStatuses?.[exam.id]?.status === "Completed",
            examCompleted: examStatuses?.[exam.id]?.status === "Completed",
            bestScore: examStatuses?.[exam.id]?.bestScore || practiceStatuses?.[exam.id]?.bestScore || "-",
          };
        }
        setStatusMap(merged);
      }
    );
    return () => {
      active = false;
    };
  }, [exams, currentUser]);

  return (
    <section>
      <div className="materials-head card">
        <div>
          <h2>Materials - Listening Exam</h2>
          <p>Choose a test to begin practice, with progress save and recovery.</p>
        </div>
        <div className="materials-tags">
          <span className="chip">{currentUser}</span>
          <span className="chip">Listening Drill</span>
          <span className="chip">Exam Mode</span>
        </div>
      </div>
      <div className="exam-grid">
        {exams.map((exam) => (
          <ExamCard
            key={exam.id}
            exam={exam}
            statusInfo={
              statusMap[exam.id] || { practiceCompleted: false, examCompleted: false, bestScore: "-" }
            }
            onStart={onStart}
          />
        ))}
      </div>
    </section>
  );
}
