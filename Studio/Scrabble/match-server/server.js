"use strict";

const http = require("http");
const path = require("path");
const fs = require("fs");
const { Server } = require("socket.io");
const engine = require("./gameEngine.cjs");

const PORT = Number(process.env.SCRABBLE_PORT || 9000);
const MAX_FULL_ROUNDS = 20;
const DEV_ADMIN_KEY = process.env.SCRABBLE_BANK_KEY || "123456";
const ENABLE_PATH = process.env.SCRABBLE_DICT || path.join(__dirname, "..", "enable.txt");
const DEFAULT_VOCAB_BANK = [
  "ANALYZE", "APPROACH", "ASSUME", "BENEFIT", "CHALLENGE",
  "COMMUNITY", "CONCEPT", "CONCLUDE", "CONTEXT", "CONTRAST",
  "CRITICAL", "DATA", "DISTRIBUTE", "EVIDENCE", "FACTOR",
  "FRAMEWORK", "FUNCTION", "IDENTIFY", "IMPACT", "INDICATE",
  "INTERPRET", "ISSUE", "METHOD", "OUTCOME", "PERSPECTIVE",
  "POLICY", "PRINCIPLE", "PROCESS", "RELEVANT", "RESEARCH",
  "RESOURCE", "RESPONSE", "SIGNIFICANT", "SIMILAR", "STRATEGY",
  "STRUCTURE", "THEORY", "VARIABLE"
];
const VOCAB_BANK = new Set(DEFAULT_VOCAB_BANK);

function loadDictionary() {
  const set = new Set();
  try {
    const text = fs.readFileSync(ENABLE_PATH, "utf8");
    const lines = text.split(/\r?\n/);
    for (let i = 0; i < lines.length; i += 1) {
      const w = lines[i].trim().toUpperCase().replace(/[^A-Z]/g, "");
      if (w.length >= 2 && w.length <= 15) set.add(w);
    }
  } catch (e) {
    console.warn("Could not read dictionary at", ENABLE_PATH, "- using tiny fallback.");
    "THE AND CAT DOG PLAY GAME WORD HELLO WORLD AA AB AD AE AI AN AR AS AT BE BY DO GO HE IF IN IS IT ME MY OF ON OR SO TO UP US WE".split(" ").forEach((w) => set.add(w));
  }
  console.log("Dictionary loaded:", set.size, "words");
  return set;
}

const DICTIONARY = loadDictionary();

const queue = [];
const weeklyQueue = [];
const rooms = new Map();
let roomSeq = 1;

// 数据库连接和得分记录函数
const mysql = require('mysql2');

// 创建数据库连接池
const pool = mysql.createPool({
  host: 'localhost',
  user: 'root',
  password: '123456',
  database: 'acadbeat',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

// 记录得分到数据库
function recordScore(userId, teamId, ruleId, score, description) {
  const numericUserId = parseInt(userId);
  
  const sql = `INSERT INTO score_records (group_id, rule_id, score, user_id, description, record_time) 
               VALUES (?, ?, ?, ?, ?, NOW())`;
  
  pool.execute(sql, [teamId, ruleId, score, numericUserId, description], (err, results) => {
    if (err) {
      console.error('Error recording score:', err);
      return;
    }
    console.log(`Score recorded successfully: userId=${userId}, teamId=${teamId}, ruleId=${ruleId}, score=${score}`);
  });
  
  const updateSql = `UPDATE challenge_teams SET score = score + ? WHERE team_id = ?`;
  pool.execute(updateSql, [score, teamId], (err, results) => {
    if (err) {
      console.error('Error updating team score:', err);
    }
  });
}

// 检查用户是否在队伍中（简化逻辑：只判断 challenge_team_members 表中的 active 记录）
async function isUserInTeam(userId) {
  return new Promise((resolve, reject) => {
    const sql = `
      SELECT team_id
      FROM challenge_team_members
      WHERE user_id = ? 
      AND membership_status = 'active'
      LIMIT 1
    `;
    
    pool.execute(sql, [userId], (err, results) => {
      if (err) {
        console.error('Error checking user team:', err);
        resolve(false);
        return;
      }
      resolve(results.length > 0);
    });
  });
}

// 检查用户是否首次参加周赛
async function isFirstWeeklyMatch(userId) {
  return new Promise((resolve, reject) => {
    const sql = `
      SELECT COUNT(*) as count
      FROM score_records
      WHERE user_id = ? 
      AND rule_id IN ('compete1_win', 'compete1_lose')
      AND YEARWEEK(record_time, 1) = YEARWEEK(CURDATE(), 1)
    `;
    
    pool.execute(sql, [userId], (err, results) => {
      if (err) {
        console.error('Error checking weekly match count:', err);
        resolve(false);
        return;
      }
      resolve(results[0].count === 0);
    });
  });
}

// 获取用户所在的队伍ID（简化逻辑：只判断 challenge_team_members 表中的 active 记录）
async function getUserTeamId(userId) {
  return new Promise((resolve, reject) => {
    const sql = `
      SELECT team_id
      FROM challenge_team_members
      WHERE user_id = ? 
      AND membership_status = 'active'
      LIMIT 1
    `;
    
    pool.execute(sql, [userId], (err, results) => {
      if (err) {
        console.error('Error getting user team ID:', err);
        resolve(null);
        return;
      }
      resolve(results.length > 0 ? results[0].team_id : null);
    });
  });
}

// 检查两个用户是否在同一队伍
async function isSameTeam(userId1, userId2) {
  const teamId1 = await getUserTeamId(userId1);
  const teamId2 = await getUserTeamId(userId2);
  
  return teamId1 !== null && teamId2 !== null && teamId1 === teamId2;
}

function randomRoomId() {
  return `room_${Date.now()}_${roomSeq++}`;
}

function initGame() {
  const board = engine.createBoard();
  const bag = engine.createBag();
  const racks = [[], []];
  engine.drawTiles(bag, racks[0], 7);
  engine.drawTiles(bag, racks[1], 7);
  return {
    board,
    bag,
    racks,
    scores: [0, 0],
    current: Math.floor(Math.random() * 2),
    passStreak: 0,
    turnNumber: 1,
    gameOver: false,
    winnerSeat: null,
    endReason: null,
    log: []
  };
}

function addLog(game, msg) {
  game.log.unshift(`[T${game.turnNumber}] ${msg}`);
  if (game.log.length > 40) game.log = game.log.slice(0, 40);
}

function getVocabularyBankWords() {
  return [...VOCAB_BANK].sort();
}

function sanitizeVocabWord(raw) {
  return String(raw || "").trim().toUpperCase().replace(/[^A-Z]/g, "");
}

function applyVocabularyBonus(ev) {
  if (!ev || !ev.valid || !Array.isArray(ev.words)) return ev;
  let bonus = 0;
  ev.words = ev.words.map((w) => {
    if (!VOCAB_BANK.has(w.word)) {
      return { ...w, vocabBonus: false };
    }
    bonus += w.score;
    return { ...w, score: w.score * 2, vocabBonus: true };
  });
  ev.total += bonus;
  ev.vocabBonusTotal = bonus;
  return ev;
}

function serializeBoard(board) {
  const out = [];
  for (let r = 0; r < engine.BOARD_SIZE; r += 1) {
    const row = [];
    for (let c = 0; c < engine.BOARD_SIZE; c += 1) {
      const cell = board[r][c];
      row.push(cell.letter ? { letter: cell.letter, isBlank: !!cell.isBlank } : null);
    }
    out.push(row);
  }
  return out;
}

function snapshotFor(room, socketId) {
  const seat = room.seatBySocket.get(socketId);
  if (seat === undefined) return null;
  const g = room.game;
  const opp = 1 - seat;
  return {
    board: serializeBoard(g.board),
    scores: [...g.scores],
    bagCount: g.bag.length,
    myRack: [...g.racks[seat]],
    opponentRackCount: g.racks[opp].length,
    currentPlayer: g.current,
    isMyTurn: g.current === seat && !g.gameOver,
    seat,
    turnNumber: g.turnNumber,
    passStreak: g.passStreak,
    gameOver: g.gameOver,
    winnerSeat: g.winnerSeat,
    endReason: g.gameOver ? g.endReason : null,
    vocabBank: getVocabularyBankWords(),
    log: [...g.log]
  };
}

function broadcastRoom(room) {
  room.sockets.forEach((sid) => {
    const s = room.io.sockets.sockets.get(sid);
    if (!s) return;
    const snap = snapshotFor(room, sid);
    if (snap) s.emit("game:state", snap);
  });
}

function checkRoundLimit(room) {
  const g = room.game;
  if (g.gameOver) return;
  if (g.turnNumber < 1 + MAX_FULL_ROUNDS * 2) return;
  finalizeGameRoundLimit(room);
}

async function finalizeGameRoundLimit(room) {
  const g = room.game;
  if (g.gameOver) return;
  g.gameOver = true;
  g.endReason = "round_limit";
  const w0 = g.scores[0];
  const w1 = g.scores[1];
  let winnerSeat = null;
  if (w0 > w1) winnerSeat = 0;
  else if (w1 > w0) winnerSeat = 1;
  g.winnerSeat = winnerSeat;
  addLog(room.game, `Game over — ${MAX_FULL_ROUNDS} rounds. Scores P1 ${w0} — P2 ${w1}.`);
  
  // 记录得分（如果是周赛）
  if (room.isWeekly) {
    console.log(`=== Finalizing weekly game (round limit) - roomId=${room.roomId}, winnerSeat=${winnerSeat}, sockets=${room.sockets.length}`);
    for (let index = 0; index < room.sockets.length; index++) {
      const sid = room.sockets[index];
      const s = room.io.sockets.sockets.get(sid);
      const seat = index;
      const userId = s?.data?.userId;
      const teamId = s?.data?.teamId;
      
      console.log(`Seat ${seat}: sid=${sid}, socket exists=${!!s}, userId=${userId}, teamId=${teamId}`);
      
      if (userId && teamId) {
        const isWinner = seat === winnerSeat;
        const ruleId = isWinner ? 'compete1_win' : 'compete1_lose';
        const score = isWinner ? 10 : 2;
        const description = `周赛Scrabble对战${isWinner ? '获胜' : '参与'}`;
        console.log(`Recording score: userId=${userId}, teamId=${teamId}, isWinner=${isWinner}, score=${score}`);
        recordScore(userId, teamId, ruleId, score, description);
      } else {
        console.warn(`Cannot record score for seat ${seat}: userId=${userId}, teamId=${teamId}`);
      }
    }
  }
  
  broadcastRoom(room);
}

async function finalizeGame(room, emptiedSeat) {
  const g = room.game;
  if (g.gameOver) return;
  g.endReason = emptiedSeat >= 0 ? "empty_rack" : "pass_streak";
  let bonus = 0;
  g.scores.forEach((sc, idx) => {
    const left = g.racks[idx].reduce((sum, ch) => sum + engine.letterScore(ch), 0);
    g.scores[idx] = sc - left;
    if (idx !== emptiedSeat && emptiedSeat >= 0) bonus += left;
  });
  if (emptiedSeat >= 0) {
    g.scores[emptiedSeat] += bonus;
  }
  g.gameOver = true;
  const w0 = g.scores[0];
  const w1 = g.scores[1];
  let winnerSeat = null;
  if (w0 > w1) winnerSeat = 0;
  else if (w1 > w0) winnerSeat = 1;
  g.winnerSeat = winnerSeat;
  addLog(room.game, `Game over. Scores P1 ${w0} — P2 ${w1}.`);
  
  // 记录得分（如果是周赛）
  if (room.isWeekly) {
    console.log(`=== Finalizing weekly game (normal) - roomId=${room.roomId}, winnerSeat=${winnerSeat}, sockets=${room.sockets.length}`);
    for (let index = 0; index < room.sockets.length; index++) {
      const sid = room.sockets[index];
      const s = room.io.sockets.sockets.get(sid);
      const seat = index;
      const userId = s?.data?.userId;
      const teamId = s?.data?.teamId;
      
      console.log(`Seat ${seat}: sid=${sid}, socket exists=${!!s}, userId=${userId}, teamId=${teamId}`);
      
      if (userId && teamId) {
        const isWinner = seat === winnerSeat;
        const ruleId = isWinner ? 'compete1_win' : 'compete1_lose';
        const score = isWinner ? 10 : 2;
        const description = `周赛Scrabble对战${isWinner ? '获胜' : '参与'}`;
        console.log(`Recording score: userId=${userId}, teamId=${teamId}, isWinner=${isWinner}, score=${score}`);
        recordScore(userId, teamId, ruleId, score, description);
      } else {
        console.warn(`Cannot record score for seat ${seat}: userId=${userId}, teamId=${teamId}`);
      }
    }
  }
  
  broadcastRoom(room);
}

async function checkGameOverAfterPlay(room) {
  const g = room.game;
  if (g.bag.length > 0) return;
  const empty = g.racks.findIndex((r) => r.length === 0);
  if (empty >= 0) await finalizeGame(room, empty);
}

function leaveQueue(socketId) {
  const i = queue.indexOf(socketId);
  if (i >= 0) queue.splice(i, 1);
  const j = weeklyQueue.indexOf(socketId);
  if (j >= 0) weeklyQueue.splice(j, 1);
}

function destroyRoom(room, reason) {
  rooms.delete(room.id);
  room.sockets.forEach((sid) => {
    const s = room.io.sockets.sockets.get(sid);
    if (s) {
      s.leave(room.id);
      delete s.data.roomId;
      delete s.data.seat;
      if (reason) s.emit("game:left", { reason });
    }
  });
}

const server = http.createServer((_req, res) => {
  res.writeHead(200, { "Content-Type": "text/plain; charset=utf-8" });
  res.end("AcadBeat Scrabble match server OK\n");
});

const io = new Server(server, {
  cors: { origin: "*" }
});

io.on("connection", (socket) => {
  socket.data.roomId = null;
  socket.data.seat = null;
  
  const query = socket.handshake.query || {};
  if (query.userId) {
    socket.data.userId = parseInt(query.userId);
    socket.data.teamId = query.teamId ? parseInt(query.teamId) : null;
    socket.data.username = query.username || null;
    console.log(`User connected: userId=${socket.data.userId}, teamId=${socket.data.teamId}, username=${socket.data.username}`);
  } else {
    console.log(`Anonymous socket connected: ${socket.id}`);
  }
  
  socket.emit("bank:state", { words: getVocabularyBankWords() });

  socket.on("bank:edit", (payload) => {
    const key = String(payload?.key || "");
    if (key !== DEV_ADMIN_KEY) {
      socket.emit("bank:edit:result", { ok: false, message: "Invalid admin key." });
      return;
    }
    const op = payload?.op;
    const word = sanitizeVocabWord(payload?.word);
    if (!word || word.length < 2 || word.length > 15) {
      socket.emit("bank:edit:result", { ok: false, message: "Word must be 2-15 letters (A-Z)." });
      return;
    }
    if (op !== "add" && op !== "remove") {
      socket.emit("bank:edit:result", { ok: false, message: "Unsupported edit operation." });
      return;
    }
    if (op === "add") VOCAB_BANK.add(word);
    if (op === "remove") VOCAB_BANK.delete(word);
    const words = getVocabularyBankWords();
    socket.emit("bank:edit:result", { ok: true, message: `Vocabulary Bank updated (${op}: ${word}).`, words });
    io.emit("bank:state", { words });
  });

  socket.on("match:join", () => {
    if (socket.data.roomId) return;
    leaveQueue(socket.id);
    if (queue.length > 0) {
      const otherId = queue.shift();
      const other = io.sockets.sockets.get(otherId);
      if (!other || other.data.roomId) {
        queue.push(socket.id);
        socket.emit("match:waiting");
        return;
      }
      const roomId = randomRoomId();
      const game = initGame();
      const room = {
        id: roomId,
        io,
        sockets: [otherId, socket.id],
        seatBySocket: new Map([[otherId, 0], [socket.id, 1]]),
        game
      };
      rooms.set(roomId, room);
      other.join(roomId);
      socket.join(roomId);
      other.data.roomId = roomId;
      socket.data.roomId = roomId;
      other.data.seat = 0;
      socket.data.seat = 1;
      other.emit("match:found", { roomId, seat: 0 });
      socket.emit("match:found", { roomId, seat: 1 });
      addLog(game, "Match started. Good luck!");
      broadcastRoom(room);
    } else {
      queue.push(socket.id);
      socket.emit("match:waiting");
    }
  });

  socket.on("match:cancel", () => {
    leaveQueue(socket.id);
    socket.emit("match:cancelled");
  });

  socket.on("weekly:join", async () => {
    if (socket.data.roomId) return;
    leaveQueue(socket.id);
    
    if (!socket.data.userId || !socket.data.teamId) {
      socket.emit("weekly:error", { message: "You must be logged in and in a team to join weekly match." });
      return;
    }
    
    if (weeklyQueue.length > 0) {
      let otherId = null;
      let other = null;
      let found = false;
      
      for (let i = 0; i < weeklyQueue.length; i++) {
        const candidateId = weeklyQueue[i];
        const candidate = io.sockets.sockets.get(candidateId);
        
        if (!candidate || candidate.data.roomId) {
          weeklyQueue.splice(i, 1);
          i--;
          continue;
        }
        
        if (!candidate.data.userId || !candidate.data.teamId) {
          weeklyQueue.splice(i, 1);
          i--;
          continue;
        }
        
        if (socket.data.teamId === candidate.data.teamId) {
          continue;
        }
        
        otherId = candidateId;
        other = candidate;
        weeklyQueue.splice(i, 1);
        found = true;
        break;
      }
      
      if (!found) {
        weeklyQueue.push(socket.id);
        socket.emit("weekly:waiting");
        return;
      }
      const roomId = randomRoomId();
      const game = initGame();
      const room = {
        id: roomId,
        io,
        sockets: [otherId, socket.id],
        seatBySocket: new Map([[otherId, 0], [socket.id, 1]]),
        game,
        isWeekly: true
      };
      rooms.set(roomId, room);
      other.join(roomId);
      socket.join(roomId);
      other.data.roomId = roomId;
      socket.data.roomId = roomId;
      other.data.seat = 0;
      socket.data.seat = 1;
      other.emit("weekly:found", { roomId, seat: 0 });
      socket.emit("weekly:found", { roomId, seat: 1 });
      addLog(game, "Weekly match started. Good luck!");
      broadcastRoom(room);
    } else {
      weeklyQueue.push(socket.id);
      socket.emit("weekly:waiting");
    }
  });

  socket.on("weekly:cancel", () => {
    leaveQueue(socket.id);
    socket.emit("weekly:cancelled");
  });

  socket.on("game:play", async (payload) => {
    const roomId = socket.data.roomId;
    const seat = socket.data.seat;
    if (roomId === null || seat === null) return;
    const room = rooms.get(roomId);
    if (!room || room.game.gameOver) return;
    const g = room.game;
    if (g.current !== seat) {
      socket.emit("game:error", { message: "Not your turn." });
      return;
    }
    const placements = payload?.placements;
    if (!Array.isArray(placements) || placements.length === 0) {
      socket.emit("game:error", { message: "Invalid play." });
      return;
    }
    const rack = g.racks[seat];
    const v = engine.verifyPlacementsAgainstRack(rack, placements);
    if (!v.ok) {
      socket.emit("game:error", { message: v.message });
      return;
    }
    const ev = engine.evaluateMove(g.board, placements, DICTIONARY);
    if (!ev.valid) {
      socket.emit("game:error", { message: ev.message });
      return;
    }
    applyVocabularyBonus(ev);
    g.scores[seat] += ev.total;
    engine.commitMove(g.board, rack, g.bag, placements, ev);
    g.passStreak = 0;
    const name = `Player ${seat + 1}`;
    const wordsDesc = ev.words.map((w) => `${w.word}(+${w.score}${w.vocabBonus ? ",VBx2" : ""})`).join(", ");
    addLog(g, `${name} played ${wordsDesc}${ev.bingo ? " + Bingo 50" : ""}${ev.vocabBonusTotal ? ` + Vocab Bonus ${ev.vocabBonusTotal}` : ""}.`);
    g.turnNumber += 1;
    g.current = 1 - seat;
    await checkGameOverAfterPlay(room);
    if (!g.gameOver) checkRoundLimit(room);
    broadcastRoom(room);
  });

  socket.on("game:pass", async () => {
    const roomId = socket.data.roomId;
    const seat = socket.data.seat;
    if (roomId === null || seat === null) return;
    const room = rooms.get(roomId);
    if (!room || room.game.gameOver) return;
    const g = room.game;
    if (g.current !== seat) {
      socket.emit("game:error", { message: "Not your turn." });
      return;
    }
    g.passStreak += 1;
    addLog(g, `Player ${seat + 1} passed.`);
    if (g.passStreak >= 4) {
      await finalizeGame(room, -1);
      return;
    }
    g.turnNumber += 1;
    g.current = 1 - seat;
    if (!g.gameOver) checkRoundLimit(room);
    broadcastRoom(room);
  });

  socket.on("game:exchange", (payload) => {
    const roomId = socket.data.roomId;
    const seat = socket.data.seat;
    if (roomId === null || seat === null) return;
    const room = rooms.get(roomId);
    if (!room || room.game.gameOver) return;
    const g = room.game;
    if (g.current !== seat) {
      socket.emit("game:error", { message: "Not your turn." });
      return;
    }
    if (g.bag.length < 7) {
      socket.emit("game:error", { message: "Bag too small to exchange." });
      return;
    }
    const indices = payload?.indices;
    if (!Array.isArray(indices) || indices.length === 0) {
      socket.emit("game:error", { message: "Select tiles to exchange." });
      return;
    }
    const rack = g.racks[seat];
    const removed = [];
    const sorted = [...new Set(indices)].sort((a, b) => b - a);
    for (let i = 0; i < sorted.length; i += 1) {
      const idx = sorted[i];
      if (idx < 0 || idx >= rack.length) {
        socket.emit("game:error", { message: "Invalid exchange index." });
        return;
      }
      removed.push(rack.splice(idx, 1)[0]);
    }
    g.bag.push(...removed);
    engine.shuffle(g.bag);
    engine.drawTiles(g.bag, rack, removed.length);
    g.passStreak = 0;
    addLog(g, `Player ${seat + 1} exchanged ${removed.length} tile(s).`);
    g.turnNumber += 1;
    g.current = 1 - seat;
    if (!g.gameOver) checkRoundLimit(room);
    broadcastRoom(room);
  });

  socket.on("game:leave", () => {
    const roomId = socket.data.roomId;
    leaveQueue(socket.id);
    if (!roomId) return;
    const room = rooms.get(roomId);
    if (room) {
      destroyRoom(room, "opponent_left");
    }
  });

  socket.on("disconnect", () => {
    leaveQueue(socket.id);
    const roomId = socket.data.roomId;
    if (roomId) {
      const room = rooms.get(roomId);
      if (room) destroyRoom(room, "opponent_disconnected");
    }
  });
});

server.listen(PORT, () => {
  console.log(`Scrabble server listening on 0.0.0.0:${PORT} (Socket.IO)`);
  console.log("Dictionary file:", ENABLE_PATH);
});
