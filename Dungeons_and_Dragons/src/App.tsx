import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Phaser from "phaser";
import { createGame } from "./game/createGame";
import { Hud } from "./components/Hud";
import { QuizPanel } from "./components/QuizPanel";
import { EventPanel } from "./components/EventPanel";
import { ShopPanel } from "./components/ShopPanel";
import { BossPanel } from "./components/BossPanel";
import { LEVELS } from "./data/levels";
import type {
  BossResult,
  BossScenario,
  BuffState,
  CombatQuestion,
  EffectDelta,
  EventScenario,
  LevelConfig,
  RoomState,
  RoomType,
  ShopItem,
} from "./types/game";
import "./App.css";

const MAX_HP = 100;
const RETURN_SECONDS = 30;

const DEFAULT_BUFFS: BuffState = {
  extraTimeNext: 0,
  halfDamageNext: false,
  magnifierNext: false,
};

function buildRoomPlan(totalRooms: number): RoomType[] {
  const base: RoomType[] = ["combat", "combat", "event", "shop"];
  const plan: RoomType[] = [];
  for (let i = 0; i < totalRooms - 1; i++) {
    plan.push(base[Math.floor(Math.random() * base.length)]!);
  }
  plan.push("boss");
  return plan;
}

function clamp(n: number, lo: number, hi: number): number {
  return Math.max(lo, Math.min(hi, n));
}

export default function App() {
  const mountRef = useRef<HTMLDivElement>(null);
  const gameRef = useRef<Phaser.Game | null>(null);

  const hpRef = useRef(MAX_HP);
  const goldRef = useRef(0);
  const buffsRef = useRef<BuffState>(DEFAULT_BUFFS);

  const [runSeed, setRunSeed] = useState(0);
  const [selectedLevel, setSelectedLevel] = useState<LevelConfig | null>(null);
  const [roomPlan, setRoomPlan] = useState<RoomType[]>([]);
  const [hp, setHp] = useState(MAX_HP);
  const [gold, setGold] = useState(0);
  const [buffs, setBuffs] = useState<BuffState>(DEFAULT_BUFFS);
  const [toast, setToast] = useState<string | null>(null);
  const [phase, setPhase] = useState<"menu" | "playing" | "defeated" | "victory">(
    "menu"
  );
  const [roomState, setRoomState] = useState<RoomState | null>(null);
  const [returnCountdown, setReturnCountdown] = useState(RETURN_SECONDS);

  const [activeCombat, setActiveCombat] = useState<{
    question: CombatQuestion;
    resolve: (correct: boolean) => void;
    title: string;
    damageMultiplier: number;
    eliminatedIndex: number | null;
  } | null>(null);
  const [activeEvent, setActiveEvent] = useState<{
    scenario: EventScenario;
    resolve: (effect: EffectDelta) => void;
  } | null>(null);
  const [activeShop, setActiveShop] = useState<{
    items: ShopItem[];
    currentGold: number;
    resolve: (effect: EffectDelta) => void;
  } | null>(null);
  const [activeBoss, setActiveBoss] = useState<{
    scenario: BossScenario;
    resolve: (result: BossResult) => void;
  } | null>(null);

  useEffect(() => {
    hpRef.current = hp;
  }, [hp]);

  useEffect(() => {
    goldRef.current = gold;
  }, [gold]);

  useEffect(() => {
    buffsRef.current = buffs;
  }, [buffs]);

  const applyEffect = useCallback((effect: EffectDelta) => {
    const hpDelta = effect.hpDelta ?? 0;
    const goldDelta = effect.goldDelta ?? 0;
    if (hpDelta !== 0) {
      setHp((cur) => {
        const next = clamp(cur + hpDelta, 0, MAX_HP);
        if (next <= 0) setPhase("defeated");
        return next;
      });
    }
    if (goldDelta !== 0) {
      setGold((cur) => Math.max(0, cur + goldDelta));
    }
    if (effect.buffs) {
      setBuffs((cur) => ({ ...cur, ...effect.buffs }));
    }
    if (effect.message) {
      setToast(effect.message);
    }
  }, []);

  useEffect(() => {
    if (!toast) return;
    const t = window.setTimeout(() => setToast(null), 2600);
    return () => window.clearTimeout(t);
  }, [toast]);

  const requestCombat = useCallback((q: CombatQuestion) => {
    return new Promise<boolean>((resolve) => {
      const curBuffs = buffsRef.current;
      const bonusTime = curBuffs.extraTimeNext;
      const useMagnifier = curBuffs.magnifierNext;
      const eliminatedWrongChoices = q.choices
        .map((_, idx) => idx)
        .filter((idx) => idx !== q.correctIndex);
      const eliminatedIndex =
        useMagnifier && eliminatedWrongChoices.length > 0
          ? eliminatedWrongChoices[
              Math.floor(Math.random() * eliminatedWrongChoices.length)
            ]!
          : null;

      const prepared: CombatQuestion = {
        ...q,
        timeLimitSec: q.timeLimitSec + bonusTime,
      };

      setBuffs((cur) => ({
        ...cur,
        extraTimeNext: 0,
        magnifierNext: false,
      }));

      setActiveCombat({
        question: prepared,
        resolve,
        title: "Combat — Question",
        damageMultiplier: curBuffs.halfDamageNext ? 0.5 : 1,
        eliminatedIndex,
      });
    });
  }, []);

  const requestEvent = useCallback((scenario: EventScenario) => {
    return new Promise<EffectDelta>((resolve) => {
      setActiveEvent({ scenario, resolve });
    });
  }, []);

  const requestShop = useCallback((items: ShopItem[]) => {
    return new Promise<EffectDelta>((resolve) => {
      setActiveShop({
        items,
        currentGold: goldRef.current,
        resolve,
      });
    });
  }, []);

  const requestBoss = useCallback((scenario: BossScenario) => {
    return new Promise<BossResult>((resolve) => {
      setActiveBoss({ scenario, resolve });
    });
  }, []);

  const resolveCombat = useCallback(
    (correct: boolean) => {
      setActiveCombat((cur) => {
        if (!cur) return null;
        const q = cur.question;
        if (correct) {
          if (q.goldReward > 0) {
            applyEffect({ goldDelta: q.goldReward });
          }
        } else {
          const baseDamage = q.damageOnFail;
          const halfDamage = buffsRef.current.halfDamageNext;
          const multiplier = halfDamage ? 0.5 : cur.damageMultiplier;
          const damage = Math.ceil(baseDamage * multiplier);
          applyEffect({ hpDelta: -damage });
          if (halfDamage) {
            setBuffs((b) => ({ ...b, halfDamageNext: false }));
          }
        }
        cur.resolve(correct);
        return null;
      });
    },
    [applyEffect]
  );

  const resolveEvent = useCallback(
    (effect: EffectDelta) => {
      setActiveEvent((cur) => {
        if (!cur) return null;
        applyEffect(effect);
        cur.resolve(effect);
        return null;
      });
    },
    [applyEffect]
  );

  const resolveShop = useCallback(
    (effect: EffectDelta) => {
      setActiveShop((cur) => {
        if (!cur) return null;
        applyEffect(effect);
        cur.resolve(effect);
        return null;
      });
    },
    [applyEffect]
  );

  const resolveBoss = useCallback(
    (result: BossResult) => {
      setActiveBoss((cur) => {
        if (!cur) return null;
        if (!result.success && result.wrongCount > 0) {
          applyEffect({
            hpDelta: -(result.wrongCount * 10),
            message: `Boss trial failed: -${result.wrongCount * 10} HP`,
          });
        } else if (result.success) {
          applyEffect({ goldDelta: 40, message: "Boss cleared: +40 gold." });
        }
        cur.resolve(result);
        return null;
      });
    },
    [applyEffect]
  );

  const startLevel = (level: LevelConfig) => {
    const plan = buildRoomPlan(level.totalRooms);
    setRoomPlan(plan);
    setSelectedLevel(level);
    setHp(MAX_HP);
    setGold(0);
    setBuffs(DEFAULT_BUFFS);
    setToast(null);
    setRoomState(null);
    setReturnCountdown(RETURN_SECONDS);
    setActiveCombat(null);
    setActiveEvent(null);
    setActiveShop(null);
    setActiveBoss(null);
    setPhase("playing");
    setRunSeed((x) => x + 1);
  };

  useEffect(() => {
    const el = mountRef.current;
    if (!el || !selectedLevel || roomPlan.length === 0) return;

    const game = createGame(
      el,
      {
        requestCombat,
        requestEvent,
        requestShop,
        requestBoss,
        onRoomStateChange: (state) => setRoomState(state),
        onRunCompleted: () => {
          setPhase("victory");
          setReturnCountdown(RETURN_SECONDS);
        },
      },
      selectedLevel,
      roomPlan
    );
    gameRef.current = game;

    return () => {
      game.destroy(true);
      if (gameRef.current === game) {
        gameRef.current = null;
      }
    };
  }, [
    requestBoss,
    requestCombat,
    requestEvent,
    requestShop,
    selectedLevel,
    roomPlan,
    runSeed,
  ]);

  useEffect(() => {
    const g = gameRef.current;
    if (!g) return;
    try {
      g.scene.getScene("GameScene");
    } catch {
      return;
    }

    if (phase === "playing") {
      g.scene.resume("GameScene");
    } else if (phase === "defeated" || phase === "victory") {
      g.scene.pause("GameScene");
    }
  }, [phase]);

  useEffect(() => {
    if (phase !== "victory") return;
    if (returnCountdown <= 0) {
      setSelectedLevel(null);
      setRoomPlan([]);
      setPhase("menu");
      setRoomState(null);
      setReturnCountdown(RETURN_SECONDS);
      return;
    }
    const t = window.setTimeout(() => setReturnCountdown((s) => s - 1), 1000);
    return () => window.clearTimeout(t);
  }, [phase, returnCountdown]);

  const restartRun = () => {
    if (!selectedLevel) return;
    startLevel(selectedLevel);
  };

  const backToMenu = () => {
    setSelectedLevel(null);
    setRoomPlan([]);
    setPhase("menu");
    setRoomState(null);
    setActiveCombat(null);
    setActiveEvent(null);
    setActiveShop(null);
    setActiveBoss(null);
    setReturnCountdown(RETURN_SECONDS);
  };

  const showGame = Boolean(selectedLevel);
  const planPreview = useMemo(
    () => roomPlan.map((t, i) => `${i + 1}.${t}`).join(" -> "),
    [roomPlan]
  );

  return (
    <div className="app">
      <header className="title-bar">
        <h1>Dungeons and Dragons</h1>
        <p className="subtitle">Web Roguelike English Adventure</p>
      </header>

      {phase === "menu" && (
        <section className="level-select pixel-border">
          <h2>Select a Chapter</h2>
          <p>Choose a level to start your run.</p>
          <div className="level-grid">
            {LEVELS.map((level) => (
              <button
                key={level.id}
                type="button"
                className="level-card"
                onClick={() => startLevel(level)}
              >
                <h3>{level.name}</h3>
                <p>{level.description}</p>
                <span>
                  Rooms: {level.totalRooms} | Enemies/Room: {level.minEnemies}-
                  {level.maxEnemies}
                </span>
              </button>
            ))}
          </div>
        </section>
      )}

      {showGame && (
        <div className="game-shell pixel-border">
          <div className="game-viewport">
            <div ref={mountRef} className="phaser-mount" />
            {roomPlan.length > 0 && (
              <div className="plan-chip">Route: {planPreview}</div>
            )}
            {toast && <div className="toast-chip">{toast}</div>}

          {activeCombat && (
            <QuizPanel
              title={activeCombat.title}
              question={activeCombat.question}
              eliminatedIndex={activeCombat.eliminatedIndex}
              onResolve={resolveCombat}
            />
          )}
          {activeEvent && (
            <EventPanel
              key={activeEvent.scenario.id}
              scenario={activeEvent.scenario}
              onResolve={resolveEvent}
            />
          )}
          {activeShop && (
            <ShopPanel
              items={activeShop.items}
              currentGold={activeShop.currentGold}
              onResolve={resolveShop}
            />
          )}
          {activeBoss && (
            <BossPanel scenario={activeBoss.scenario} onResolve={resolveBoss} />
          )}

          {phase === "defeated" && (
            <div className="game-over-overlay">
              <div className="game-over-box pixel-border">
                <h2>Defeated</h2>
                <p>Your HP reached 0. Progress in this run is lost.</p>
                <div className="overlay-actions">
                  <button type="button" className="restart-btn" onClick={restartRun}>
                    Retry Level
                  </button>
                  <button type="button" className="secondary-btn" onClick={backToMenu}>
                    Level Select
                  </button>
                </div>
              </div>
            </div>
          )}

          {phase === "victory" && (
            <div className="game-over-overlay">
              <div className="game-over-box pixel-border">
                <h2>Dungeon Cleared</h2>
                <p>
                  You cleared all rooms. Returning to level select in{" "}
                  {returnCountdown}s.
                </p>
                <button type="button" className="restart-btn" onClick={backToMenu}>
                  Return Now
                </button>
              </div>
            </div>
          )}
          </div>
          <Hud hp={hp} maxHp={MAX_HP} gold={gold} roomState={roomState} />
        </div>
      )}
    </div>
  );
}
