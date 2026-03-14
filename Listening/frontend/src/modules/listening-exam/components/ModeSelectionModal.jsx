import { useState } from "react";

export default function ModeSelectionModal({ examTitle, onStart, onClose }) {
  const [mode, setMode] = useState("practice");

  return (
    <div className="modal-backdrop">
      <div className="modal mode-modal centered-modal">
        <h3>Start Listening Session</h3>
        <p>{examTitle}</p>

        <div className="mode-pick-grid">
          <button
            className={`mode-pick ${mode === "practice" ? "selected" : ""}`}
            onClick={() => setMode("practice")}
          >
            <strong>Practice Mode</strong>
            <span>Count up timer, flexible replay, submit when ready.</span>
          </button>
          <button
            className={`mode-pick ${mode === "exam" ? "selected" : ""}`}
            onClick={() => setMode("exam")}
          >
            <strong>Exam Mode</strong>
            <span>Countdown timer, stricter playback, formal test flow.</span>
          </button>
        </div>

        <div className="mode-actions centered-row">
          <button
            onClick={() => onStart({ mode })}
          >
            Begin Session
          </button>
          <button className="secondary" onClick={onClose}>
            Cancel
          </button>
        </div>
      </div>
    </div>
  );
}
