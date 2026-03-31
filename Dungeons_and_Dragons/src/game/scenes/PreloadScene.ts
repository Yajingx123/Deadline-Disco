import Phaser from "phaser";
import type { LevelConfig } from "../../types/game";

export class PreloadScene extends Phaser.Scene {
  constructor() {
    super({ key: "PreloadScene" });
  }

  preload(): void {
    const base = `${import.meta.env.BASE_URL}assets/game/`;
    this.load.spritesheet("hero", `${base}dude.png`, {
      frameWidth: 32,
      frameHeight: 48,
    });
    this.load.spritesheet("mummy", `${base}enemy_mummy.png`, {
      frameWidth: 37,
      frameHeight: 45,
    });
    this.load.spritesheet("dungeon", `${base}buch-dungeon-tileset.png`, {
      frameWidth: 16,
      frameHeight: 16,
    });
    this.load.image("skullcandle", `${base}skullcandle.png`);
  }

  create(): void {
    const level = this.game.registry.get("activeLevel") as LevelConfig;
    this.scene.start("GameScene", {
      roomIndex: 1,
      totalRooms: level.totalRooms,
    });
  }
}
