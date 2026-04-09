import type { LevelConfig } from "../types/game";

export const LEVELS: LevelConfig[] = [
  {
    id: "misty-forest",
    name: "Misty Forest",
    description: "Balanced route with medium pressure and a tricky boss room.",
    totalRooms: 4,
    minEnemies: 2,
    maxEnemies: 3,
    bossEnemies: 4,
  },
  {
    id: "royal-dungeon",
    name: "Royal Dungeon",
    description: "More crowded rooms and a harder final wave.",
    totalRooms: 5,
    minEnemies: 3,
    maxEnemies: 4,
    bossEnemies: 5,
  },
];
