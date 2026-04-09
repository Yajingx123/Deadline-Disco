export type CombatQuestion = {
  id: string;
  /** Used for future enemy-question binding */
  enemyType: "skeleton" | "beast" | "dragon" | "rebel";
  prompt: string;
  choices: string[];
  correctIndex: number;
  timeLimitSec: number;
  goldReward: number;
  damageOnFail: number;
};

export type RoomType = "combat" | "event" | "shop" | "boss";

export type BuffState = {
  extraTimeNext: number;
  halfDamageNext: boolean;
  magnifierNext: boolean;
};

export type EffectDelta = {
  hpDelta?: number;
  goldDelta?: number;
  buffs?: Partial<BuffState>;
  message?: string;
};

export type DialogueOption = {
  id: string;
  text: string;
  nextId?: string;
  effect?: EffectDelta;
};

export type DialogueNode = {
  id: string;
  npcLine: string;
  options: DialogueOption[];
};

export type EventScenario = {
  id: string;
  npcName: string;
  title: string;
  startNodeId: string;
  nodes: DialogueNode[];
};

export type ShopItem = {
  id: string;
  name: string;
  description: string;
  cost: number;
  effect: EffectDelta;
};

export type BossScenario = {
  id: string;
  title: string;
  passage: string;
  questions: CombatQuestion[];
};

export type BossResult = {
  success: boolean;
  wrongCount: number;
};

export type LevelConfig = {
  id: string;
  name: string;
  description: string;
  totalRooms: number;
  minEnemies: number;
  maxEnemies: number;
  bossEnemies: number;
};

export type RoomState = {
  levelName: string;
  roomIndex: number;
  totalRooms: number;
  roomType: RoomType;
  enemiesLeft: number;
  portalOpen: boolean;
};
