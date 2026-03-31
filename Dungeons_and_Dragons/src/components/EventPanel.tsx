import { useEffect, useMemo, useState } from "react";
import type { EffectDelta, EventScenario } from "../types/game";
import { Portrait, eventScenarioToPortrait } from "./Portrait";
import "./EventPanel.css";

type Props = {
  scenario: EventScenario;
  onResolve: (effect: EffectDelta) => void;
};

export function EventPanel({ scenario, onResolve }: Props) {
  const nodeMap = useMemo(
    () => new Map(scenario.nodes.map((n) => [n.id, n])),
    [scenario.nodes]
  );
  const [nodeId, setNodeId] = useState(scenario.startNodeId);
  const [accEffect, setAccEffect] = useState<EffectDelta>({});
  useEffect(() => {
    setNodeId(scenario.startNodeId);
    setAccEffect({});
  }, [scenario.id, scenario.startNodeId]);
  const node = nodeMap.get(nodeId);
  if (!node) return null;

  const mergeEffect = (base: EffectDelta, inc?: EffectDelta): EffectDelta => {
    if (!inc) return base;
    return {
      hpDelta: (base.hpDelta ?? 0) + (inc.hpDelta ?? 0),
      goldDelta: (base.goldDelta ?? 0) + (inc.goldDelta ?? 0),
      buffs: { ...(base.buffs ?? {}), ...(inc.buffs ?? {}) },
      message: inc.message ?? base.message,
    };
  };

  const bust = eventScenarioToPortrait(scenario.id);

  return (
    <div className="event-backdrop" role="dialog" aria-modal="true">
      <div className="event-panel pixel-border">
        <div className="event-panel-inner">
          <Portrait variant={bust} label={scenario.npcName} />
          <div className="event-panel-main">
            <div className="event-header">
              <span className="event-tag">{scenario.title}</span>
            </div>
            <p className="event-line">"{node.npcLine}"</p>
            <ul className="event-options">
              {node.options.map((option) => (
                <li key={option.id}>
                  <button
                    type="button"
                    onClick={() => {
                      const nextEffect = mergeEffect(accEffect, option.effect);
                      if (option.nextId) {
                        setAccEffect(nextEffect);
                        setNodeId(option.nextId);
                      } else {
                        onResolve(nextEffect);
                      }
                    }}
                  >
                    {option.text}
                  </button>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
