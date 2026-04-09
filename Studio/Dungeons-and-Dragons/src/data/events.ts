import type { EventScenario } from "../types/game";

export const EVENT_SCENARIOS: EventScenario[] = [
  {
    id: "princess-forest",
    npcName: "Princess Elara",
    title: "A Lost Princess",
    startNodeId: "princess-root",
    nodes: [
      {
        id: "princess-root",
        npcLine:
          "Traveler, I am lost in this cursed forest. Will you guide me to a safe path?",
        options: [
          {
            id: "polite",
            text: "Of course, Your Highness. Stay close and we will find a safe way.",
            nextId: "princess-good-end",
          },
          {
            id: "neutral",
            text: "Maybe. It depends on what I get in return.",
            nextId: "princess-neutral-end",
          },
          {
            id: "rude",
            text: "Not my problem. Figure it out yourself.",
            nextId: "princess-bad-end",
          },
        ],
      },
      {
        id: "princess-good-end",
        npcLine:
          "Your words are kind and brave. Please accept this blessing from the royal court.",
        options: [
          {
            id: "take-blessing",
            text: "Thank you. I will use it wisely.",
            effect: {
              hpDelta: 20,
              buffs: { extraTimeNext: 5 },
              message: "Blessing gained: +20 HP and +5s on next question.",
            },
          },
        ],
      },
      {
        id: "princess-neutral-end",
        npcLine: "You are honest. I cannot give much, but take some coins.",
        options: [
          {
            id: "take-coins",
            text: "Fair enough. Safe travels.",
            effect: {
              goldDelta: 15,
              message: "You received 15 gold.",
            },
          },
        ],
      },
      {
        id: "princess-bad-end",
        npcLine:
          "Such arrogance... The forest itself rejects your attitude.",
        options: [
          {
            id: "leave",
            text: "Whatever.",
            effect: {
              hpDelta: -12,
              message: "Debuff: You lost 12 HP.",
            },
          },
        ],
      },
    ],
  },
  {
    id: "mage-ruins",
    npcName: "Archmage Rowan",
    title: "Arcane Trial",
    startNodeId: "mage-root",
    nodes: [
      {
        id: "mage-root",
        npcLine:
          "Before I share my runes, answer me with wisdom: how do you treat knowledge?",
        options: [
          {
            id: "wise",
            text: "Knowledge should help others, not just oneself.",
            nextId: "mage-good-end",
          },
          {
            id: "selfish",
            text: "Power belongs to the strongest mind.",
            nextId: "mage-neutral-end",
          },
          {
            id: "mock",
            text: "I do not care about books. Just give me rewards.",
            nextId: "mage-bad-end",
          },
        ],
      },
      {
        id: "mage-good-end",
        npcLine:
          "A noble answer. I grant you a tactical rune for your next battle.",
        options: [
          {
            id: "accept",
            text: "I am honored.",
            effect: {
              buffs: { magnifierNext: true },
              message: "Buff gained: Magnifier for next question (50/50).",
            },
          },
        ],
      },
      {
        id: "mage-neutral-end",
        npcLine: "Ambitious, but not hopeless. Take this and move on.",
        options: [
          {
            id: "accept-coins",
            text: "I will make use of it.",
            effect: {
              goldDelta: 12,
              message: "You received 12 gold.",
            },
          },
        ],
      },
      {
        id: "mage-bad-end",
        npcLine: "Then leave my tower with weakened spirit.",
        options: [
          {
            id: "leave",
            text: "Fine.",
            effect: {
              buffs: { halfDamageNext: false },
              hpDelta: -10,
              message: "Arcane backlash: -10 HP.",
            },
          },
        ],
      },
    ],
  },
];
