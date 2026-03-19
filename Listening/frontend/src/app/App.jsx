import { useEffect, useState } from "react";
import SiteNav from "../shared/layout/SiteNav";
import HomeLanding from "../shared/home/HomeLanding";
import { HOME_MODULES, VOCABULARY_QUOTES } from "../shared/home/homeData";
import ModeSelectionModal from "../modules/listening-exam/components/ModeSelectionModal";
import ExamListPage from "../modules/listening-exam/pages/ExamListPage";
import ExamPage from "../modules/listening-exam/pages/ExamPage";
import ReviewPage from "../modules/listening-exam/pages/ReviewPage";
import { loadExams } from "../modules/listening-exam/services/listeningExamApi";
import CollectionsPage from "../../../../Intensive_Listening/CollectionsPage";
import CommunityPage from "../../../../Intensive_Listening/CommunityPage";
import Player from "../../../../Intensive_Listening/Player";

import AuthPage from "../../../../Auth/frontend/AuthPage.jsx";

const VOCAB_BASE_URL = "http://127.0.0.1:8002";

export default function App() {
  // ================= 状态管理 =================
  // 【修改】：使用 localStorage 缓存用户名，刷新页面也不会掉线！
  const [currentUser, setCurrentUser] = useState(() => localStorage.getItem("currentUser") || null);
  const [showAuthPage, setShowAuthPage] = useState(false);

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

  // ================= 生命周期 =================
  useEffect(() => {
    let active = true;
    loadExams().then((data) => {
      if (active) {
        setExams(data);
        setLoading(false);
      }
    });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (activeModule === "Vocabulary") {
      setQuoteIndex((index) => (index + 1) % VOCABULARY_QUOTES.length);
    }
  }, [activeModule]);

  // ================= 跳转逻辑 =================
  const handleSelectModule = (moduleName) => {
    setActiveModule(moduleName);
    setSection("home");
    setView("list");
  };

  const handleModuleAction = (moduleName, actionLabel) => {
    if (moduleName === "Vocabulary" && actionLabel === "Word Quest") {
      window.location.href = `${VOCAB_BASE_URL}/backend/practice.php`;
      return;
    }
    if (moduleName === "Vocabulary" && actionLabel === "Mastery Check") {
      if (!currentUser) {
        alert("请先登录或注册，才能参加词汇测验！");
        setShowAuthPage(true);
        return;
      }
      // 【修改】：将用户名拼接到 URL 后面传递给 8003 端口的测试页面
      window.location.href = `http://127.0.0.1:8003/vocabulary-exam.html?username=${encodeURIComponent(currentUser)}`;
      return;
    }

    if (moduleName === "Listening" && actionLabel === "Echo Challenge") {
      setActiveModule("Listening");
      setSection("materials");
      setView("list");
      return;
    }
    if (moduleName === "Listening" && actionLabel === "Audio Stream") {
      setActiveModule("Listening");
      setSection("materials");
      setView("collections"); 
      return;
    }

    if (HOME_MODULES[moduleName]) {
      setActiveModule(moduleName);
    }
    setSection("home");
  };

  const handleSubNav = (target) => {
    setSection("materials"); 
    setView(target);         
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
          currentUser={currentUser} 
          onSelectModule={handleSelectModule}
          onAction={handleModuleAction}
          onAuthClick={() => setShowAuthPage(true)} 
          onLogout={() => {
            setCurrentUser(null);
            localStorage.removeItem("currentUser"); // 【修改】：登出时清除缓存
          }}     
        />
      ) : null}

      {section === "home" ? (
        <HomeLanding activeModule={activeModule} onAction={handleModuleAction} quoteIndex={quoteIndex} />
      ) : (
        <div className={fullscreenExam ? "materials-page-shell fullscreen-materials" : "materials-page-shell"}>
          <section className="materials-page-body">
            {view === "list" ? (
              <>
                <ExamListPage exams={exams} onStart={openModeSelection} currentUser={currentUser || "Guest"} />
                {loading && <p>Loading exams...</p>}
              </>
            ) : view === "collections" ? (
               <CollectionsPage onNavigate={handleSubNav} />
            ) : view === "player" ? (
               <Player onNavigate={handleSubNav} />
            ) : view === "intensivelistening" ? (
               <div>Intensive Listening Module (Component loading)</div> 
            ) : view === "community" ? (
               <CommunityPage onNavigate={handleSubNav} />
            ) : view === "exam" && selectedExam ? (
              <ExamPage
                exam={selectedExam}
                mode={mode}
                currentUser={currentUser || "Guest"} 
                onExit={() => setView("list")}
                onSubmit={(nextResult) => {
                  setResult(nextResult);
                  setView("review");
                }}
              />
            ) : view === "review" && selectedExam && result ? (
              <ReviewPage exam={selectedExam} result={result} onBack={() => setView("list")} />
            ) : null}
          </section>
        </div>
      )}

      {showMode && selectedExam ? (
        <ModeSelectionModal examTitle={selectedExam.title} onClose={() => setShowMode(false)} onStart={startExam} />
      ) : null}

      {showAuthPage && (
        <AuthPage 
          onClose={() => setShowAuthPage(false)} 
          onSuccess={(username) => {
            setCurrentUser(username);
            localStorage.setItem("currentUser", username); // 【修改】：登录成功存入缓存
            setShowAuthPage(false); 
          }} 
        />
      )}
    </div>
  );
}