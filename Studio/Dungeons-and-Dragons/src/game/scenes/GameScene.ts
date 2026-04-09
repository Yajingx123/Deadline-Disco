import Phaser from "phaser";
import type { CombatHandlers } from "../createGame";
import type {
  BossScenario,
  CombatQuestion,
  EventScenario,
  LevelConfig,
  RoomState,
  RoomType,
  ShopItem,
} from "../../types/game";
import questions from "../../data/questions.json";
import { EVENT_SCENARIOS } from "../../data/events";
import { SHOP_POOL } from "../../data/shop";
import { BOSS_SCENARIO } from "../../data/boss";

const TILE = 16;
const ROOM_W = 34;
const ROOM_H = 24;
const ROOM_PX_W = ROOM_W * TILE;
const ROOM_PX_H = ROOM_H * TILE;
const VIEW_H = 200;
const SPEED = 96;

const FLOOR_FRAMES = [27, 28, 29, 30, 46, 47];
const TEX_WALL_BRICK = "tex-wall-brick";
const TEX_WALL_PILLAR = "tex-wall-pillar";
const PORTAL_TEX = "portal-rune";

export class GameScene extends Phaser.Scene {
  private player!: Phaser.Types.Physics.Arcade.SpriteWithDynamicBody;
  private enemies!: Phaser.Physics.Arcade.Group;
  private walls!: Phaser.Physics.Arcade.StaticGroup;
  private portal?: Phaser.Types.Physics.Arcade.SpriteWithDynamicBody;
  private interactNpc?: Phaser.Types.Physics.Arcade.SpriteWithDynamicBody;
  private hintText?: Phaser.GameObjects.Text;
  private cursors!: Phaser.Types.Input.Keyboard.CursorKeys;
  private wasd!: {
    W: Phaser.Input.Keyboard.Key;
    A: Phaser.Input.Keyboard.Key;
    S: Phaser.Input.Keyboard.Key;
    D: Phaser.Input.Keyboard.Key;
  };
  private combatBusy = false;
  private interactionBusy = false;
  private lastFacing: "left" | "right" | "front" = "front";
  private roomIndex = 1;
  private totalRooms = 1;
  private roomType: RoomType = "combat";
  private enemyCount = 0;
  private level!: LevelConfig;
  private roomPlan: RoomType[] = [];
  private blockedTiles = new Set<string>();
  /** 事件/商店对话结束后玩家仍与 NPC 重叠，否则会每帧再次触发 overlap */
  private npcInteractConsumed = false;
  private bossCooldownUntil = 0;
  private pendingEventScenario: EventScenario | null = null;

  constructor() {
    super({ key: "GameScene" });
  }

  init(data?: { roomIndex?: number; totalRooms?: number }): void {
    this.roomIndex = data?.roomIndex ?? 1;
    this.totalRooms = data?.totalRooms ?? 1;
    this.blockedTiles.clear();
    this.generateBlockedTiles();
  }

  create(): void {
    this.combatBusy = false;
    this.interactionBusy = false;
    this.npcInteractConsumed = false;
    this.bossCooldownUntil = 0;
    this.pendingEventScenario = null;
    this.lastFacing = "front";
    this.level = this.game.registry.get("activeLevel") as LevelConfig;
    this.roomPlan = (this.game.registry.get("roomPlan") as RoomType[]) ?? [];
    this.roomType =
      this.roomPlan[this.roomIndex - 1] ??
      (this.roomIndex === this.totalRooms ? "boss" : "combat");
    this.enemyCount = this.pickEnemyCount();

    this.createHeroAnims();
    this.createMummyAnims();
    this.createPortalTexture();
    this.createStoneWallTextures();
    this.createNpcTextures();
    this.drawDungeonRoom();
    this.buildWalls();
    this.spawnProps();

    this.player = this.physics.add.sprite(TILE * 4, TILE * 4, "hero", 4);
    this.player.setCollideWorldBounds(true);
    this.player.setDepth(10);
    this.player.body.setSize(18, 30);
    this.player.body.setOffset(7, 14);

    this.physics.world.setBounds(0, 0, ROOM_PX_W, ROOM_PX_H);
    this.enemies = this.physics.add.group({
      immovable: true,
      allowGravity: false,
    });

    this.physics.add.collider(this.player, this.walls);
    this.physics.add.collider(this.enemies, this.walls);

    this.add
      .text(8, 8, this.getHeaderText(), {
        fontFamily: "monospace",
        fontSize: "10px",
        color: "#9bf6ff",
      })
      .setDepth(30)
      .setScrollFactor(0)
      .setStroke("#0d1b2a", 3);

    this.hintText = this.add
      .text(8, VIEW_H - 14, this.getHintText(), {
        fontFamily: "monospace",
        fontSize: "7px",
        color: "#8ecae6",
      })
      .setDepth(30)
      .setScrollFactor(0)
      .setStroke("#0d1b2a", 3);

    if (!this.input.keyboard) {
      return;
    }
    this.cursors = this.input.keyboard.createCursorKeys();
    this.wasd = {
      W: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.W),
      A: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.A),
      S: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.S),
      D: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.D),
    };

    this.setupRoomTypeLogic();

    this.cameras.main.setRoundPixels(true);
    this.cameras.main.setBackgroundColor(0x0b132b);
    this.cameras.main.setBounds(0, 0, ROOM_PX_W, ROOM_PX_H);
    this.cameras.main.startFollow(this.player, true, 0.15, 0.15);
    this.emitRoomState();
  }

  private setupRoomTypeLogic(): void {
    if (this.roomType === "combat") {
      this.spawnEnemies(this.enemyCount);
      this.physics.add.overlap(this.player, this.enemies, (_, enemyObj) => {
        if (this.combatBusy || this.interactionBusy) return;
        const enemy = enemyObj as Phaser.Types.Physics.Arcade.SpriteWithDynamicBody;
        if (!enemy.active) return;
        void this.beginCombat(enemy);
      });
      return;
    }

    let npcKey: string;
    if (this.roomType === "event") {
      this.pendingEventScenario = this.pickEventScenario();
      npcKey =
        this.pendingEventScenario.id === "princess-forest"
          ? "tex-npc-princess"
          : "tex-npc-mage";
    } else if (this.roomType === "shop") {
      npcKey = "tex-npc-merchant";
    } else {
      npcKey = "tex-npc-boss";
    }

    this.interactNpc = this.physics.add.sprite(
      ROOM_PX_W - TILE * 5,
      ROOM_PX_H - TILE * 5,
      npcKey
    );
    this.interactNpc.setDepth(11);
    this.interactNpc.body.setAllowGravity(false);
    this.interactNpc.body.setSize(20, 28);
    this.interactNpc.body.setOffset(2, 8);
    this.physics.add.collider(this.interactNpc, this.walls);

    this.physics.add.overlap(this.player, this.interactNpc, () => {
      if (
        this.interactionBusy ||
        this.combatBusy ||
        !this.interactNpc?.active ||
        this.npcInteractConsumed
      ) {
        return;
      }
      if (this.roomType === "boss" && this.time.now < this.bossCooldownUntil) {
        return;
      }
      if (this.roomType === "event") {
        void this.beginEventRoom();
      } else if (this.roomType === "shop") {
        void this.beginShopRoom();
      } else if (this.roomType === "boss") {
        void this.beginBossRoom();
      }
    });
  }

  private getHintText(): string {
    if (this.roomType === "combat") {
      return "Defeat all enemies, then enter the portal.";
    }
    if (this.roomType === "event") {
      return "Find the NPC and interact to resolve the event.";
    }
    if (this.roomType === "shop") {
      return "Find the shopkeeper and purchase one item.";
    }
    return "Touch the boss to start 3-5 reading questions.";
  }

  private createHeroAnims(): void {
    if (this.anims.exists("hero-left")) return;
    this.anims.create({
      key: "hero-left",
      frames: this.anims.generateFrameNumbers("hero", { start: 0, end: 3 }),
      frameRate: 9,
      repeat: -1,
    });
    this.anims.create({
      key: "hero-right",
      frames: this.anims.generateFrameNumbers("hero", { start: 5, end: 8 }),
      frameRate: 9,
      repeat: -1,
    });
    this.anims.create({
      key: "hero-front",
      frames: [{ key: "hero", frame: 4 }],
      frameRate: 1,
    });
  }

  private createMummyAnims(): void {
    if (this.anims.exists("mummy-walk")) return;
    this.anims.create({
      key: "mummy-walk",
      frames: this.anims.generateFrameNumbers("mummy", { start: 0, end: 4 }),
      frameRate: 9,
      repeat: -1,
    });
  }

  private createPortalTexture(): void {
    if (this.textures.exists(PORTAL_TEX)) return;
    const g = this.add.graphics();
    g.fillStyle(0x2d00f7, 1);
    g.fillCircle(16, 16, 15);
    g.fillStyle(0x4cc9f0, 0.95);
    g.fillCircle(16, 16, 10);
    g.lineStyle(2, 0xf72585, 1);
    g.strokeCircle(16, 16, 14);
    g.generateTexture(PORTAL_TEX, 32, 32);
    g.destroy();
  }

  /** Buch 图集帧索引易错位成纯黑，墙体改用程序生成石砖 */
  private createStoneWallTextures(): void {
    if (this.textures.exists(TEX_WALL_BRICK)) return;
    const g = this.add.graphics();
    g.fillStyle(0x3d2e5c, 1);
    g.fillRect(0, 0, 16, 16);
    g.fillStyle(0x52406f, 1);
    g.fillRect(0, 0, 16, 3);
    g.fillRect(0, 8, 16, 2);
    g.fillStyle(0x2a1f3d, 1);
    g.fillRect(0, 12, 16, 2);
    g.lineStyle(1, 0x7bed9f, 0.45);
    g.strokeRect(0.5, 0.5, 15, 15);
    g.lineStyle(1, 0x1b1528, 0.35);
    g.beginPath();
    g.moveTo(8, 0);
    g.lineTo(8, 16);
    g.strokePath();
    g.generateTexture(TEX_WALL_BRICK, 16, 16);
    g.clear();

    g.fillStyle(0x2a1f45, 1);
    g.fillRect(0, 0, 16, 16);
    g.fillStyle(0x4a6670, 0.55);
    g.fillRect(5, 2, 6, 12);
    g.fillStyle(0x7bed9f, 0.25);
    g.fillRect(6, 3, 4, 3);
    g.lineStyle(1, 0xb8e0d4, 0.55);
    g.strokeRect(0.5, 0.5, 15, 15);
    g.generateTexture(TEX_WALL_PILLAR, 16, 16);
    g.destroy();
  }

  /** 简易像素风 NPC（避免与战斗木乃伊共用贴图） */
  private createNpcTextures(): void {
    if (this.textures.exists("tex-npc-princess")) return;
    const W = 24;
    const H = 36;
    const px = (
      g: Phaser.GameObjects.Graphics,
      x: number,
      y: number,
      c: number
    ) => {
      g.fillStyle(c, 1);
      g.fillRect(x, y, 1, 1);
    };
    const fillRect = (
      g: Phaser.GameObjects.Graphics,
      x: number,
      y: number,
      w: number,
      h: number,
      c: number
    ) => {
      g.fillStyle(c, 1);
      g.fillRect(x, y, w, h);
    };

    const mk = (key: string, draw: (g: Phaser.GameObjects.Graphics) => void) => {
      const g = this.add.graphics();
      draw(g);
      g.generateTexture(key, W, H);
      g.destroy();
    };

    mk("tex-npc-princess", (g) => {
      fillRect(g, 8, 6, 8, 8, 0xffdbac);
      fillRect(g, 6, 4, 12, 4, 0xffd60a);
      fillRect(g, 7, 8, 10, 4, 0x6f4e37);
      fillRect(g, 5, 14, 14, 14, 0xe63946);
      fillRect(g, 9, 28, 6, 8, 0xe63946);
      fillRect(g, 6, 16, 3, 12, 0xc1121f);
      fillRect(g, 15, 16, 3, 12, 0xc1121f);
      px(g, 10, 9, 0x222222);
      px(g, 14, 9, 0x222222);
    });

    mk("tex-npc-mage", (g) => {
      fillRect(g, 8, 8, 8, 8, 0xffdbac);
      fillRect(g, 5, 4, 14, 8, 0x3a0ca3);
      fillRect(g, 7, 2, 10, 4, 0x3a0ca3);
      fillRect(g, 4, 14, 16, 16, 0x4361ee);
      fillRect(g, 18, 18, 2, 14, 0x7209b7);
      fillRect(g, 8, 30, 4, 6, 0x240046);
      fillRect(g, 12, 30, 4, 6, 0x240046);
      px(g, 10, 11, 0xffffff);
      px(g, 14, 11, 0xffffff);
    });

    mk("tex-npc-merchant", (g) => {
      fillRect(g, 8, 8, 8, 8, 0xffdbac);
      fillRect(g, 6, 6, 12, 4, 0x8d5524);
      fillRect(g, 5, 14, 14, 12, 0x606c38);
      fillRect(g, 6, 22, 12, 3, 0xb69162);
      fillRect(g, 7, 26, 10, 8, 0x283618);
      fillRect(g, 3, 16, 4, 10, 0xdda15e);
      px(g, 10, 11, 0x222222);
      px(g, 14, 11, 0x222222);
    });

    mk("tex-npc-boss", (g) => {
      fillRect(g, 7, 6, 10, 10, 0x8d99ae);
      fillRect(g, 5, 4, 14, 4, 0x2b2d42);
      fillRect(g, 6, 2, 4, 4, 0x2b2d42);
      fillRect(g, 14, 2, 4, 4, 0x2b2d42);
      fillRect(g, 4, 14, 16, 18, 0x1d1e2c);
      fillRect(g, 6, 18, 12, 8, 0xef233c);
      fillRect(g, 7, 30, 4, 6, 0x111111);
      fillRect(g, 13, 30, 4, 6, 0x111111);
      px(g, 9, 10, 0xffd60a);
      px(g, 14, 10, 0xffd60a);
    });
  }

  private getHandlers(): CombatHandlers {
    return this.game.registry.get("combatHandlers") as CombatHandlers;
  }

  private pickQuestion(): CombatQuestion {
    const list = questions as CombatQuestion[];
    return list[Phaser.Math.Between(0, list.length - 1)]!;
  }

  private pickEventScenario(): EventScenario {
    return Phaser.Math.RND.pick(EVENT_SCENARIOS) as EventScenario;
  }

  private pickShopItems(): ShopItem[] {
    const pool = Phaser.Utils.Array.Shuffle([...SHOP_POOL]);
    return pool.slice(0, 3);
  }

  private pickBossScenario(): BossScenario {
    const total = Phaser.Math.Between(3, 5);
    return {
      ...BOSS_SCENARIO,
      questions: BOSS_SCENARIO.questions.slice(0, total),
    };
  }

  private pickEnemyCount(): number {
    if (this.roomType === "boss") {
      return this.level.bossEnemies;
    }
    return Phaser.Math.Between(this.level.minEnemies, this.level.maxEnemies);
  }

  private getHeaderText(): string {
    const roomLabel =
      this.roomType === "boss"
        ? `Boss Room ${this.roomIndex}/${this.totalRooms}`
        : `${this.roomType.toUpperCase()} Room ${this.roomIndex}/${this.totalRooms}`;
    return `${this.level.name} — ${roomLabel}`;
  }

  private emitRoomState(): void {
    const portalOpen = Boolean(this.portal?.active);
    const count =
      this.roomType === "combat"
        ? this.enemies.countActive(true)
        : this.interactNpc?.active
        ? 1
        : 0;
    const state: RoomState = {
      levelName: this.level.name,
      roomIndex: this.roomIndex,
      totalRooms: this.totalRooms,
      roomType: this.roomType,
      enemiesLeft: portalOpen ? 0 : count,
      portalOpen,
    };
    this.getHandlers().onRoomStateChange(state);
  }

  private async beginCombat(
    enemy: Phaser.Types.Physics.Arcade.SpriteWithDynamicBody
  ): Promise<void> {
    this.combatBusy = true;
    this.physics.pause();
    enemy.anims.pause();
    this.hintText?.setVisible(false);

    const q = this.pickQuestion();
    const handlers = this.getHandlers();
    let correct = false;
    try {
      correct = await handlers.requestCombat(q);
    } finally {
      this.physics.resume();
      this.combatBusy = false;
      this.hintText?.setVisible(true);
    }

    if (correct) {
      this.spawnBurst(enemy.x, enemy.y);
      enemy.destroy();
      if (this.enemies.countActive(true) <= 0) {
        this.openPortal();
      }
      this.emitRoomState();
    } else {
      enemy.anims.resume();
      this.cameras.main.flash(220, 200, 60, 60, false);
    }
  }

  private async beginEventRoom(): Promise<void> {
    if (!this.pendingEventScenario || this.npcInteractConsumed) return;
    this.npcInteractConsumed = true;
    this.interactionBusy = true;
    this.physics.pause();
    const handlers = this.getHandlers();
    try {
      await handlers.requestEvent(this.pendingEventScenario);
      this.dismissInteractNpc();
      this.openPortal();
    } finally {
      this.physics.resume();
      this.interactionBusy = false;
    }
  }

  private async beginShopRoom(): Promise<void> {
    if (this.npcInteractConsumed) return;
    this.npcInteractConsumed = true;
    this.interactionBusy = true;
    this.physics.pause();
    const handlers = this.getHandlers();
    try {
      await handlers.requestShop(this.pickShopItems());
      this.dismissInteractNpc();
      this.openPortal();
    } finally {
      this.physics.resume();
      this.interactionBusy = false;
    }
  }

  private async beginBossRoom(): Promise<void> {
    if (this.interactionBusy) return;
    this.interactionBusy = true;
    this.physics.pause();
    const handlers = this.getHandlers();
    try {
      const result = await handlers.requestBoss(this.pickBossScenario());
      if (result.success) {
        this.dismissInteractNpc();
        this.openPortal();
      } else {
        this.bossCooldownUntil = this.time.now + 900;
        this.cameras.main.flash(220, 220, 80, 80, false);
      }
    } finally {
      this.physics.resume();
      this.interactionBusy = false;
      this.emitRoomState();
    }
  }

  private dismissInteractNpc(): void {
    this.interactNpc?.destroy();
    this.interactNpc = undefined;
  }

  private spawnBurst(x: number, y: number): void {
    const colors = [0xff6b6b, 0xfeca57, 0x48dbfb, 0xff9ff3, 0xb8c5d6];
    for (let i = 0; i < 22; i++) {
      const p = this.add.rectangle(x, y, 4, 4, colors[i % colors.length]!);
      p.setDepth(15);
      this.tweens.add({
        targets: p,
        x: x + Phaser.Math.Between(-48, 48),
        y: y + Phaser.Math.Between(-48, 48),
        alpha: 0,
        duration: 450,
        onComplete: () => p.destroy(),
      });
    }
  }

  private drawDungeonRoom(): void {
    for (let ty = 0; ty < ROOM_H; ty++) {
      for (let tx = 0; tx < ROOM_W; tx++) {
        const wx = tx * TILE;
        const wy = ty * TILE;
        const blocked = this.blockedTiles.has(`${tx},${ty}`);
        if (blocked) {
          const key =
            tx % 3 === 0 && ty % 3 === 0 ? TEX_WALL_PILLAR : TEX_WALL_BRICK;
          const tile = this.add.image(wx, wy, key);
          tile.setOrigin(0, 0);
          tile.setDepth(3);
        } else {
          const frame = Phaser.Math.RND.pick(FLOOR_FRAMES) as number;
          const tile = this.add.image(wx, wy, "dungeon", frame);
          tile.setOrigin(0, 0);
          tile.setDepth(-2);
        }
      }
    }
  }

  private buildWalls(): void {
    this.walls = this.physics.add.staticGroup();
    for (let ty = 0; ty < ROOM_H; ty++) {
      for (let tx = 0; tx < ROOM_W; tx++) {
        if (!this.blockedTiles.has(`${tx},${ty}`)) continue;
        const body = this.add.rectangle(
          tx * TILE + TILE / 2,
          ty * TILE + TILE / 2,
          TILE,
          TILE,
          0x000000,
          0.001
        );
        this.physics.add.existing(body, true);
        this.walls.add(body);
      }
    }
  }

  private spawnProps(): void {
    const s = 0.5;
    const c1 = this.add
      .image(TILE * 3, ROOM_PX_H - TILE * 2, "skullcandle")
      .setOrigin(0.5, 1)
      .setScale(s)
      .setDepth(4);
    const c2 = this.add
      .image(ROOM_PX_W - TILE * 3, ROOM_PX_H - TILE * 2, "skullcandle")
      .setOrigin(0.5, 1)
      .setScale(-s, s)
      .setDepth(4);
    this.tweens.add({
      targets: [c1, c2],
      y: "+=2",
      duration: 900,
      yoyo: true,
      repeat: -1,
      ease: "Sine.easeInOut",
    });
  }

  private generateBlockedTiles(): void {
    const isSafe = (x: number, y: number): boolean =>
      x >= 2 && y >= 2 && x <= 8 && y <= 8;
    const block = (x: number, y: number) => {
      if (isSafe(x, y)) return;
      this.blockedTiles.add(`${x},${y}`);
    };

    for (let x = 0; x < ROOM_W; x++) {
      for (let y = 0; y < ROOM_H; y++) {
        if (x < 2 || y < 2 || x >= ROOM_W - 2 || y >= ROOM_H - 2) {
          block(x, y);
        }
      }
    }

    const obstacles = Phaser.Math.Between(6, 10);
    for (let i = 0; i < obstacles; i++) {
      const w = Phaser.Math.Between(2, 4);
      const h = Phaser.Math.Between(2, 4);
      const sx = Phaser.Math.Between(4, ROOM_W - w - 4);
      const sy = Phaser.Math.Between(4, ROOM_H - h - 4);
      for (let x = sx; x < sx + w; x++) {
        for (let y = sy; y < sy + h; y++) {
          if (Phaser.Math.Between(0, 100) > 15) {
            block(x, y);
          }
        }
      }
    }
  }

  private getFreeTile(): { x: number; y: number } {
    for (let i = 0; i < 300; i++) {
      const x = Phaser.Math.Between(3, ROOM_W - 4);
      const y = Phaser.Math.Between(3, ROOM_H - 4);
      if (this.blockedTiles.has(`${x},${y}`)) continue;
      if (x <= 9 && y <= 9) continue;
      return { x, y };
    }
    return { x: ROOM_W - 5, y: ROOM_H - 5 };
  }

  private spawnEnemies(count: number): void {
    const used = new Set<string>();
    for (let i = 0; i < count; i++) {
      let tile = this.getFreeTile();
      let guard = 0;
      while (used.has(`${tile.x},${tile.y}`) && guard < 40) {
        tile = this.getFreeTile();
        guard += 1;
      }
      used.add(`${tile.x},${tile.y}`);
      const enemy = this.physics.add.sprite(
        tile.x * TILE + TILE / 2,
        tile.y * TILE + TILE / 2,
        "mummy",
        0
      );
      enemy.setDepth(10);
      enemy.body.setSize(22, 36);
      enemy.body.setOffset(7, 7);
      enemy.play("mummy-walk");
      this.enemies.add(enemy);
    }
  }

  private openPortal(): void {
    if (this.portal?.active) return;
    this.hintText?.setText(
      this.roomIndex < this.totalRooms
        ? "Portal opened. Step in to enter next room."
        : "Final portal opened. Step in to finish."
    );
    const p = this.physics.add.sprite(
      ROOM_PX_W - TILE * 4,
      ROOM_PX_H - TILE * 4,
      PORTAL_TEX
    );
    p.setDepth(12);
    p.body.setCircle(12);
    p.body.setOffset(4, 4);
    p.body.setAllowGravity(false);
    p.setScale(1);
    this.portal = p;
    this.tweens.add({
      targets: p,
      scale: 1.2,
      alpha: 0.8,
      duration: 650,
      yoyo: true,
      repeat: -1,
    });
    let portalUsed = false;
    this.physics.add.overlap(this.player, p, () => {
      if (portalUsed) return;
      portalUsed = true;
      if (this.roomIndex < this.totalRooms) {
        this.scene.restart({
          roomIndex: this.roomIndex + 1,
          totalRooms: this.totalRooms,
        });
      } else {
        this.getHandlers().onRunCompleted();
        this.scene.pause();
      }
    });
    this.emitRoomState();
  }

  update(): void {
    if (!this.player?.body || this.combatBusy || this.interactionBusy) return;

    let vx = 0;
    let vy = 0;
    if (this.cursors.left.isDown || this.wasd.A.isDown) vx = -1;
    else if (this.cursors.right.isDown || this.wasd.D.isDown) vx = 1;
    if (this.cursors.up.isDown || this.wasd.W.isDown) vy = -1;
    else if (this.cursors.down.isDown || this.wasd.S.isDown) vy = 1;

    if (vx !== 0 && vy !== 0) {
      vx *= 0.7071;
      vy *= 0.7071;
    }

    this.player.setVelocity(vx * SPEED, vy * SPEED);

    if (vx < 0) {
      this.player.anims.play("hero-left", true);
      this.lastFacing = "left";
    } else if (vx > 0) {
      this.player.anims.play("hero-right", true);
      this.lastFacing = "right";
    } else if (vy !== 0) {
      if (this.lastFacing === "right") {
        this.player.anims.play("hero-right", true);
      } else if (this.lastFacing === "left") {
        this.player.anims.play("hero-left", true);
      } else {
        this.player.anims.play("hero-front", true);
      }
    } else {
      this.player.anims.play("hero-front", true);
    }
  }
}
