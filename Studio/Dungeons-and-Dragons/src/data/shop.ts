import type { ShopItem } from "../types/game";

export const SHOP_POOL: ShopItem[] = [
  {
    id: "health-potion",
    name: "Health Potion",
    description: "Recover 25 HP instantly.",
    cost: 20,
    effect: {
      hpDelta: 25,
      message: "You drank a potion: +25 HP.",
    },
  },
  {
    id: "hourglass",
    name: "Hourglass",
    description: "Next combat question gets +5 seconds.",
    cost: 18,
    effect: {
      buffs: { extraTimeNext: 5 },
      message: "Hourglass activated: +5s on next question.",
    },
  },
  {
    id: "magnifier",
    name: "Magnifier",
    description: "Next combat question removes one wrong option.",
    cost: 22,
    effect: {
      buffs: { magnifierNext: true },
      message: "Magnifier ready for next question (50/50).",
    },
  },
  {
    id: "iron-skin",
    name: "Iron Skin Charm",
    description: "Next wrong answer deals half damage.",
    cost: 20,
    effect: {
      buffs: { halfDamageNext: true },
      message: "Iron Skin ready: next wrong answer is halved.",
    },
  },
];
