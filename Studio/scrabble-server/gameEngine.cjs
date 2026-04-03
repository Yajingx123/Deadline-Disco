"use strict";

const BOARD_SIZE = 15;
const CENTER = 7;

const LETTER_SPECS = {
  A: { score: 1, count: 9 }, B: { score: 3, count: 2 }, C: { score: 3, count: 2 }, D: { score: 2, count: 4 },
  E: { score: 1, count: 12 }, F: { score: 4, count: 2 }, G: { score: 2, count: 3 }, H: { score: 4, count: 2 },
  I: { score: 1, count: 9 }, J: { score: 8, count: 1 }, K: { score: 5, count: 1 }, L: { score: 1, count: 4 },
  M: { score: 3, count: 2 }, N: { score: 1, count: 6 }, O: { score: 1, count: 8 }, P: { score: 3, count: 2 },
  Q: { score: 10, count: 1 }, R: { score: 1, count: 6 }, S: { score: 1, count: 4 }, T: { score: 1, count: 6 },
  U: { score: 1, count: 4 }, V: { score: 4, count: 2 }, W: { score: 4, count: 2 }, X: { score: 8, count: 1 },
  Y: { score: 4, count: 2 }, Z: { score: 10, count: 1 }, "*": { score: 0, count: 2 }
};

function buildPremiumMap() {
  const map = {};
  const add = (type, coords) => coords.forEach(([r, c]) => { map[`${r}-${c}`] = type; });
  add("TW", [[0, 0], [0, 7], [0, 14], [7, 0], [7, 14], [14, 0], [14, 7], [14, 14]]);
  add("DW", [[1, 1], [2, 2], [3, 3], [4, 4], [7, 7], [10, 10], [11, 11], [12, 12], [13, 13], [1, 13], [2, 12], [3, 11], [4, 10], [10, 4], [11, 3], [12, 2], [13, 1]]);
  add("TL", [[1, 5], [1, 9], [5, 1], [5, 5], [5, 9], [5, 13], [9, 1], [9, 5], [9, 9], [9, 13], [13, 5], [13, 9]]);
  add("DL", [[0, 3], [0, 11], [2, 6], [2, 8], [3, 0], [3, 7], [3, 14], [6, 2], [6, 6], [6, 8], [6, 12], [7, 3], [7, 11], [8, 2], [8, 6], [8, 8], [8, 12], [11, 0], [11, 7], [11, 14], [12, 6], [12, 8], [14, 3], [14, 11]]);
  return map;
}

const PREMIUM = buildPremiumMap();

function shuffle(arr) {
  for (let i = arr.length - 1; i > 0; i -= 1) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

function createBag() {
  const bag = [];
  Object.entries(LETTER_SPECS).forEach(([ch, spec]) => {
    for (let i = 0; i < spec.count; i += 1) bag.push(ch);
  });
  return shuffle(bag);
}

function createBoard() {
  const board = [];
  for (let r = 0; r < BOARD_SIZE; r += 1) {
    const row = [];
    for (let c = 0; c < BOARD_SIZE; c += 1) {
      const premium = PREMIUM[`${r}-${c}`] || "";
      row.push({ letter: "", isBlank: false, premium, usedPremium: false });
    }
    board.push(row);
  }
  return board;
}

function letterScore(ch) {
  return LETTER_SPECS[ch]?.score ?? 0;
}

function cellKey(r, c) {
  return `${r}-${c}`;
}

function boardHasTiles(board) {
  for (let r = 0; r < BOARD_SIZE; r += 1) {
    for (let c = 0; c < BOARD_SIZE; c += 1) {
      if (board[r][c].letter) return true;
    }
  }
  return false;
}

function getPlacementMap(placements) {
  const map = new Map();
  placements.forEach((p) => map.set(cellKey(p.r, p.c), p));
  return map;
}

function getCellLetter(board, r, c, placementMap) {
  const p = placementMap?.get(cellKey(r, c));
  if (p) return p.letter;
  return board[r][c].letter;
}

function isInside(r, c) {
  return r >= 0 && r < BOARD_SIZE && c >= 0 && c < BOARD_SIZE;
}

function hasNeighborTile(board, r, c, placementMap) {
  const dirs = [[1, 0], [-1, 0], [0, 1], [0, -1]];
  for (const [dr, dc] of dirs) {
    const nr = r + dr;
    const nc = c + dc;
    if (!isInside(nr, nc)) continue;
    const boardLetter = board[nr][nc].letter;
    const newLetter = placementMap?.get(cellKey(nr, nc))?.letter;
    if (boardLetter || newLetter) return true;
  }
  return false;
}

function buildWordFrom(board, r, c, dr, dc, placementMap) {
  let sr = r;
  let sc = c;
  while (isInside(sr - dr, sc - dc) && getCellLetter(board, sr - dr, sc - dc, placementMap)) {
    sr -= dr;
    sc -= dc;
  }
  const coords = [];
  let cr = sr;
  let cc = sc;
  let word = "";
  while (isInside(cr, cc) && getCellLetter(board, cr, cc, placementMap)) {
    word += getCellLetter(board, cr, cc, placementMap);
    coords.push([cr, cc]);
    cr += dr;
    cc += dc;
  }
  return { word, coords };
}

function scoreWord(board, wordData, placementMap) {
  let wordMultiplier = 1;
  let sum = 0;
  for (const [r, c] of wordData.coords) {
    const key = cellKey(r, c);
    const placed = placementMap.get(key);
    const boardCell = board[r][c];
    const letter = placed ? placed.letter : boardCell.letter;
    const base = placed && placed.isBlank ? 0 : letterScore(letter);
    let letterValue = base;
    if (placed && !boardCell.letter) {
      const premium = boardCell.premium;
      if (premium === "DL") letterValue *= 2;
      if (premium === "TL") letterValue *= 3;
      if (premium === "DW" || premium === "CENTER") wordMultiplier *= 2;
      if (premium === "TW") wordMultiplier *= 3;
    }
    sum += letterValue;
  }
  return sum * wordMultiplier;
}

function evaluateMove(board, placements, dictionary) {
  if (!placements.length) return { valid: false, message: "Place at least one tile.", highlightCells: [] };
  const placementMap = getPlacementMap(placements);
  const keys = new Set();
  for (const p of placements) {
    const key = cellKey(p.r, p.c);
    if (keys.has(key)) return { valid: false, message: "Duplicate tile placement.", highlightCells: [] };
    keys.add(key);
    if (board[p.r][p.c].letter) return { valid: false, message: "Cannot place on occupied square.", highlightCells: [] };
  }

  const firstMove = !boardHasTiles(board);
  if (firstMove && placements.length < 2) return { valid: false, message: "First word must contain at least 2 letters.", highlightCells: [] };

  const sameRow = placements.every((p) => p.r === placements[0].r);
  const sameCol = placements.every((p) => p.c === placements[0].c);
  if (!sameRow && !sameCol) return { valid: false, message: "Tiles must be in one row or one column.", highlightCells: [] };

  let dr = 0;
  let dc = 0;
  if (sameRow && !sameCol) { dr = 0; dc = 1; }
  if (sameCol && !sameRow) { dr = 1; dc = 0; }
  if (sameRow && sameCol) {
    const r = placements[0].r;
    const c = placements[0].c;
    const hWord = buildWordFrom(board, r, c, 0, 1, placementMap);
    const vWord = buildWordFrom(board, r, c, 1, 0, placementMap);
    if (hWord.word.length > 1) { dr = 0; dc = 1; }
    else if (vWord.word.length > 1) { dr = 1; dc = 0; }
    else return { valid: false, message: "Single tile must connect to existing letters.", highlightCells: [] };
  }

  if (dr === 0) {
    const row = placements[0].r;
    const cols = placements.map((p) => p.c).sort((a, b) => a - b);
    for (let c = cols[0]; c <= cols[cols.length - 1]; c += 1) {
      if (!getCellLetter(board, row, c, placementMap)) {
        return { valid: false, message: "Placement has gaps.", highlightCells: [] };
      }
    }
  } else {
    const col = placements[0].c;
    const rows = placements.map((p) => p.r).sort((a, b) => a - b);
    for (let r = rows[0]; r <= rows[rows.length - 1]; r += 1) {
      if (!getCellLetter(board, r, col, placementMap)) {
        return { valid: false, message: "Placement has gaps.", highlightCells: [] };
      }
    }
  }

  if (firstMove) {
    const onCenter = placements.some((p) => p.r === CENTER && p.c === CENTER);
    if (!onCenter) return { valid: false, message: "First move must cover center star (H8).", highlightCells: [] };
  } else {
    const touching = placements.some((p) => hasNeighborTile(board, p.r, p.c, placementMap));
    if (!touching) return { valid: false, message: "Move must connect to existing board letters.", highlightCells: [] };
  }

  const uniqueWords = new Map();
  const mainStart = placements[0];
  const mainWord = buildWordFrom(board, mainStart.r, mainStart.c, dr, dc, placementMap);
  if (mainWord.word.length > 1) uniqueWords.set(mainWord.coords.map(([r, c]) => cellKey(r, c)).join("|"), mainWord);

  for (const p of placements) {
    const cross = buildWordFrom(board, p.r, p.c, dc, dr, placementMap);
    if (cross.word.length > 1) {
      uniqueWords.set(cross.coords.map(([r, c]) => cellKey(r, c)).join("|"), cross);
    }
  }

  if (uniqueWords.size === 0) return { valid: false, message: "No valid word formed.", highlightCells: [] };

  let total = 0;
  const words = [];
  const highlightCells = [];
  for (const wordData of uniqueWords.values()) {
    const w = wordData.word.toUpperCase();
    if (!dictionary.has(w)) {
      return { valid: false, message: `Word "${w}" is not in dictionary.`, highlightCells: [] };
    }
    const score = scoreWord(board, wordData, placementMap);
    total += score;
    words.push({ word: w, score });
    for (const [rr, cc] of wordData.coords) {
      highlightCells.push(cellKey(rr, cc));
    }
  }

  const bingo = placements.length === 7 ? 50 : 0;
  total += bingo;
  return {
    valid: true,
    total,
    words,
    bingo,
    highlightCells
  };
}

function verifyPlacementsAgainstRack(rack, placements) {
  const usedIdx = new Set();
  for (const p of placements) {
    const idx = p.rackIndex;
    if (typeof idx !== "number" || idx < 0 || idx >= rack.length) {
      return { ok: false, message: "Invalid rack index." };
    }
    if (usedIdx.has(idx)) return { ok: false, message: "Duplicate rack slot in move." };
    usedIdx.add(idx);
    const tile = rack[idx];
    if (tile === "*") {
      if (!p.isBlank || !/^[A-Z]$/.test(String(p.letter || ""))) {
        return { ok: false, message: "Invalid blank tile assignment." };
      }
    } else if (tile !== p.letter) {
      return { ok: false, message: "Tile does not match rack." };
    }
  }
  return { ok: true };
}

function drawTiles(bag, rack, n) {
  for (let i = 0; i < n && bag.length > 0; i += 1) {
    rack.push(bag.pop());
  }
}

function commitMove(board, rack, bag, placements, evalResult) {
  placements.forEach((p) => {
    const cell = board[p.r][p.c];
    cell.letter = p.letter;
    cell.isBlank = !!p.isBlank;
    cell.usedPremium = true;
  });
  const removeIndices = [...new Set(placements.map((p) => p.rackIndex))].sort((a, b) => b - a);
  removeIndices.forEach((idx) => rack.splice(idx, 1));
  drawTiles(bag, rack, 7 - rack.length);
}

module.exports = {
  BOARD_SIZE,
  CENTER,
  LETTER_SPECS,
  shuffle,
  createBag,
  createBoard,
  evaluateMove,
  verifyPlacementsAgainstRack,
  commitMove,
  letterScore,
  boardHasTiles,
  drawTiles
};
