import Phaser from "phaser";
import { PreloadScene } from "./scenes/PreloadScene";
import { GameScene } from "./scenes/GameScene";
import type {
  BossResult,
  BossScenario,
  CombatQuestion,
  EffectDelta,
  EventScenario,
  LevelConfig,
  RoomState,
  RoomType,
  ShopItem,
} from "../types/game";

export type CombatHandlers = {
  requestCombat: (q: CombatQuestion) => Promise<boolean>;
  requestEvent: (scenario: EventScenario) => Promise<EffectDelta>;
  requestShop: (items: ShopItem[]) => Promise<EffectDelta>;
  requestBoss: (scenario: BossScenario) => Promise<BossResult>;
  onRoomStateChange: (state: RoomState) => void;
  onRunCompleted: () => void;
};

export function createGame(
  parent: HTMLElement,
  handlers: CombatHandlers,
  level: LevelConfig,
  roomPlan: RoomType[]
): Phaser.Game {
  const config: Phaser.Types.Core.GameConfig = {
    type: Phaser.AUTO,
    parent,
    width: 320,
    height: 200,
    pixelArt: true,
    roundPixels: true,
    backgroundColor: "#0b132b",
    physics: {
      default: "arcade",
      arcade: {
        gravity: { x: 0, y: 0 },
        debug: false,
      },
    },
    scene: [PreloadScene, GameScene],
    scale: {
      mode: Phaser.Scale.FIT,
      autoCenter: Phaser.Scale.CENTER_BOTH,
    },
  };

  const game = new Phaser.Game(config);
  game.registry.set("combatHandlers", handlers);
  game.registry.set("activeLevel", level);
  game.registry.set("roomPlan", roomPlan);
  return game;
}
