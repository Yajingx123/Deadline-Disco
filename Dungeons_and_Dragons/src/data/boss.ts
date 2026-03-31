import type { BossScenario } from "../types/game";

export const BOSS_SCENARIO: BossScenario = {
  id: "royal-archive-trial",
  title: "Royal Archive Trial",
  passage:
    "In the Royal Archive, every apprentice must complete a final mission. " +
    "Lena arrived before sunrise, organized ancient records, and guided two new students. " +
    "When a storm damaged part of the roof, she stayed calm, moved rare books to safety, " +
    "and wrote a clear report for the chief librarian. Because of her discipline and kindness, " +
    "the team finished early and no important document was lost.",
  questions: [
    {
      id: "boss-1",
      enemyType: "dragon",
      prompt: "Why did Lena arrive before sunrise?",
      choices: [
        "To avoid meeting the chief librarian",
        "To prepare and organize records early",
        "To hide rare books from students",
        "To repair the roof alone",
      ],
      correctIndex: 1,
      timeLimitSec: 18,
      goldReward: 0,
      damageOnFail: 8,
    },
    {
      id: "boss-2",
      enemyType: "dragon",
      prompt: "What happened during the storm?",
      choices: [
        "The team left the archive immediately",
        "Lena canceled the mission",
        "Part of the roof was damaged",
        "All documents were destroyed",
      ],
      correctIndex: 2,
      timeLimitSec: 18,
      goldReward: 0,
      damageOnFail: 8,
    },
    {
      id: "boss-3",
      enemyType: "dragon",
      prompt: "Which quality is best shown by Lena's actions?",
      choices: [
        "Carelessness",
        "Discipline and kindness",
        "Pride and impatience",
        "Fear of responsibility",
      ],
      correctIndex: 1,
      timeLimitSec: 18,
      goldReward: 0,
      damageOnFail: 8,
    },
    {
      id: "boss-4",
      enemyType: "dragon",
      prompt: "What was the final result of the team's work?",
      choices: [
        "They failed to protect any books",
        "They finished late with major losses",
        "They finished early and protected important documents",
        "They had to wait for outside help",
      ],
      correctIndex: 2,
      timeLimitSec: 20,
      goldReward: 0,
      damageOnFail: 8,
    },
  ],
};
