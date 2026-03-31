import { useCallback, useEffect, useRef, useState } from "react";
import type { CombatQuestion } from "../types/game";
import { Portrait, type PortraitVariant } from "./Portrait";
import "./QuizPanel.css";

type Props = {
  question: CombatQuestion;
  onResolve: (correct: boolean) => void;
  title?: string;
  eliminatedIndex?: number | null;
  portraitVariant?: PortraitVariant;
};

export function QuizPanel({
  question,
  onResolve,
  title = "Combat — Vocabulary",
  eliminatedIndex = null,
  portraitVariant = "foe",
}: Props) {
  const [selected, setSelected] = useState<number | null>(null);
  const [secondsLeft, setSecondsLeft] = useState(question.timeLimitSec);
  const [locked, setLocked] = useState(false);
  const resolvedRef = useRef(false);

  const finish = useCallback(
    (correct: boolean) => {
      if (resolvedRef.current) return;
      resolvedRef.current = true;
      setLocked(true);
      onResolve(correct);
    },
    [onResolve]
  );

  useEffect(() => {
    resolvedRef.current = false;
    setSecondsLeft(question.timeLimitSec);
    setSelected(null);
    setLocked(false);
  }, [question]);

  useEffect(() => {
    if (locked) return;
    if (secondsLeft <= 0) {
      finish(false);
      return;
    }
    const t = window.setTimeout(() => setSecondsLeft((s) => s - 1), 1000);
    return () => window.clearTimeout(t);
  }, [secondsLeft, locked, finish]);

  const submit = () => {
    if (selected === null || locked) return;
    finish(selected === question.correctIndex);
  };

  const ratio = question.timeLimitSec
    ? Math.max(0, secondsLeft / question.timeLimitSec)
    : 0;

  return (
    <div className="quiz-backdrop" role="dialog" aria-modal="true">
      <div className="quiz-panel pixel-border">
        <div className="quiz-panel-inner">
          <Portrait variant={portraitVariant} label="Encounter" />
          <div className="quiz-panel-main">
            <div className="quiz-header">
              <span className="quiz-tag">{title}</span>
              <span
                className={secondsLeft <= 3 ? "quiz-timer danger" : "quiz-timer"}
              >
                {secondsLeft}s
              </span>
            </div>
            <div className="quiz-timer-bar">
              <div
                className="quiz-timer-fill"
                style={{ width: `${ratio * 100}%` }}
              />
            </div>
            <p className="quiz-prompt">{question.prompt}</p>
            <ul className="quiz-choices">
              {question.choices.map((c, i) => (
                <li key={i}>
                  <button
                    type="button"
                    className={selected === i ? "choice selected" : "choice"}
                    onClick={() => !locked && setSelected(i)}
                    disabled={locked || eliminatedIndex === i}
                  >
                    {String.fromCharCode(65 + i)}.{" "}
                    {eliminatedIndex === i ? "~~ eliminated ~~" : c}
                  </button>
                </li>
              ))}
            </ul>
            <button
              type="button"
              className="submit-btn"
              onClick={submit}
              disabled={selected === null || locked}
            >
              Confirm
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
