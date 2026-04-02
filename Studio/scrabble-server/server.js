"use strict";

const http = require("http");
const path = require("path");
const fs = require("fs");
const { Server } = require("socket.io");
const engine = require("./gameEngine.cjs");

const PORT = Number(process.env.SCRABBLE_PORT || 9000);
const ENABLE_PATH = process.env.SCRABBLE_DICT || path.join(__dirname, "..", "Scrabble", "enable.txt");

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
const rooms = new Map();
let roomSeq = 1;

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
    log: []
  };
}

function addLog(game, msg) {
  game.log.unshift(`[T${game.turnNumber}] ${msg}`);
  if (game.log.length > 40) game.log = game.log.slice(0, 40);
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

function finalizeGame(room, emptiedSeat) {
  const g = room.game;
  if (g.gameOver) return;
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
  broadcastRoom(room);
}

function checkGameOverAfterPlay(room) {
  const g = room.game;
  if (g.bag.length > 0) return;
  const empty = g.racks.findIndex((r) => r.length === 0);
  if (empty >= 0) finalizeGame(room, empty);
}

function leaveQueue(socketId) {
  const i = queue.indexOf(socketId);
  if (i >= 0) queue.splice(i, 1);
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

  socket.on("game:play", (payload) => {
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
    g.scores[seat] += ev.total;
    engine.commitMove(g.board, rack, g.bag, placements, ev);
    g.passStreak = 0;
    const name = `Player ${seat + 1}`;
    const wordsDesc = ev.words.map((w) => `${w.word}(+${w.score})`).join(", ");
    addLog(g, `${name} played ${wordsDesc}${ev.bingo ? " + Bingo 50" : ""}.`);
    g.turnNumber += 1;
    g.current = 1 - seat;
    checkGameOverAfterPlay(room);
    if (!g.gameOver) broadcastRoom(room);
    else broadcastRoom(room);
  });

  socket.on("game:pass", () => {
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
      finalizeGame(room, -1);
      return;
    }
    g.turnNumber += 1;
    g.current = 1 - seat;
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
  console.log(`Scrabble server http://127.0.0.1:${PORT} (Socket.IO)`);
  console.log("Dictionary file:", ENABLE_PATH);
});
