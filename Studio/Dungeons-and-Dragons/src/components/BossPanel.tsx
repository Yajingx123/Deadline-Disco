import { useEffect, useMemo, useState } from "react";
import type { BossResult, BossScenario } from "../types/game";
import { Portrait } from "./Portrait";
import "./BossPanel.css";

type Props = {
  scenario: BossScenario;
  onResolve: (result: BossResult) => void;
};

export function BossPanel({ scenario, onResolve }: Props) {
  const [index, setIndex] = useState(0);
  const [secondsLeft, setSecondsLeft] = useState(
    scenario.questions[0]?.timeLimitSec ?? 15
  );
  const [selected, setSelected] = useState<number | null>(null);
  const [wrongCount, setWrongCount] = useState(0);
  const current = scenario.questions[index];

  useEffect(() => {
    setSecondsLeft(current?.timeLimitSec ?? 15);
    setSelected(null);
  }, [index, current]);

  useEffect(() => {
    if (!current) return;
    if (secondsLeft <= 0) {
      submit(false);
      return;
    }
    const t = window.setTimeout(() => setSecondsLeft((s) => s - 1), 1000);
    return () => window.clearTimeout(t);
  }, [secondsLeft, current]);

  const total = scenario.questions.length;
  const ratio = useMemo(
    () => Math.max(0, secondsLeft / (current?.timeLimitSec ?? 1)),
    [secondsLeft, current]
  );

  if (!current) return null;

  function submit(bySelect = true) {
    const correct = bySelect ? selected === current.correctIndex : false;
    const nextWrong = wrongCount + (correct ? 0 : 1);
    if (index >= total - 1) {
      onResolve({ success: nextWrong === 0, wrongCount: nextWrong });
      return;
    }
    setWrongCount(nextWrong);
    setIndex((i) => i + 1);
  }

  return (
    <div className="boss-backdrop" role="dialog" aria-modal="true">
      <div className="boss-panel pixel-border">
        <div className="boss-panel-inner">
          <Portrait variant="boss" label="Archivist" />
          <div className="boss-panel-main">
            <div className="boss-header">
              <span>{scenario.title}</span>
              <span>
                Q{index + 1}/{total} — {secondsLeft}s
              </span>
            </div>
            <div className="boss-timer-bar">
              <div
                className="boss-timer-fill"
                style={{ width: `${ratio * 100}%` }}
              />
            </div>
            <p className="boss-passage">{scenario.passage}</p>
            <p className="boss-prompt">{current.prompt}</p>
            <ul className="boss-choices">
              {current.choices.map((choice, i) => (
                <li key={i}>
                  <button
                    type="button"
                    className={selected === i ? "selected" : ""}
                    onClick={() => setSelected(i)}
                  >
                    {String.fromCharCode(65 + i)}. {choice}
                  </button>
                </li>
              ))}
            </ul>
            <button
              type="button"
              className="boss-submit"
              disabled={selected === null}
              onClick={() => submit(true)}
            >
              Confirm
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
