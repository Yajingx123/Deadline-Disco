import { useEffect, useState } from "react";
import SiteNav from "../shared/layout/SiteNav";
import HomeLanding from "../shared/home/HomeLanding";
import { HOME_MODULES, VOCABULARY_QUOTES } from "../shared/home/homeData";
import ModeSelectionModal from "../modules/listening-exam/components/ModeSelectionModal";
import ExamListPage from "../modules/listening-exam/pages/ExamListPage";
import ExamPage from "../modules/listening-exam/pages/ExamPage";
import ReviewPage from "../modules/listening-exam/pages/ReviewPage";
import { loadExams } from "../modules/listening-exam/services/listeningExamApi";

export default function App() {
  const [section, setSection] = useState("home");
  const [activeModule, setActiveModule] = useState("Vocabulary");
  const [quoteIndex, setQuoteIndex] = useState(0);
  const [view, setView] = useState("list");
  const [selectedExam, setSelectedExam] = useState(null);
  const [showMode, setShowMode] = useState(false);
  const [mode, setMode] = useState("practice");
  const [result, setResult] = useState(null);
  const [exams, setExams] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    loadExams().then((data) => {
      if (active) {
        setExams(data);
        setLoading(false);
      }
    });
    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    if (activeModule === "Vocabulary") {
      setQuoteIndex((index) => (index + 1) % VOCABULARY_QUOTES.length);
    }
  }, [activeModule]);

  const handleSelectModule = (moduleName) => {
    setActiveModule(moduleName);
    setSection("home");
    setView("list");
  };

  const handleModuleAction = (moduleName, actionLabel) => {
    if (moduleName === "Listening" && actionLabel === "Echo Challenge") {
      setActiveModule("Listening");
      setSection("materials");
      setView("list");
      return;
    }

    if (HOME_MODULES[moduleName]) {
      setActiveModule(moduleName);
    }
    setSection("home");
  };

  const openModeSelection = (exam) => {
    setSelectedExam(exam);
    setShowMode(true);
  };

  const startExam = ({ mode: nextMode }) => {
    setMode(nextMode);
    setShowMode(false);
    setSection("materials");
    setView("exam");
  };

  const fullscreenExam = section === "materials" && view === "exam";

  return (
    <div className={fullscreenExam ? "site-shell fullscreen-shell" : "site-shell"}>
      {!fullscreenExam ? (
        <SiteNav
          activeModule={activeModule}
          currentSection={section}
          onSelectModule={handleSelectModule}
          onAction={handleModuleAction}
        />
      ) : null}

      {section === "home" ? (
        <HomeLanding activeModule={activeModule} onAction={handleModuleAction} quoteIndex={quoteIndex} />
      ) : (
        <div className={fullscreenExam ? "materials-page-shell fullscreen-materials" : "materials-page-shell"}>
          <section className="materials-page-body">
            {view === "list" ? <ExamListPage exams={exams} onStart={openModeSelection} /> : null}
            {view === "list" && loading ? <p>Loading exams...</p> : null}
            {view === "exam" && selectedExam ? (
              <ExamPage
                exam={selectedExam}
                mode={mode}
                onExit={() => setView("list")}
                onSubmit={(nextResult) => {
                  setResult(nextResult);
                  setView("review");
                }}
              />
            ) : null}
            {view === "review" && selectedExam && result ? (
              <ReviewPage exam={selectedExam} result={result} onBack={() => setView("list")} />
            ) : null}
          </section>
        </div>
      )}

      {showMode && selectedExam ? (
        <ModeSelectionModal
          examTitle={selectedExam.title}
          onClose={() => setShowMode(false)}
          onStart={startExam}
        />
      ) : null}
    </div>
  );
}
