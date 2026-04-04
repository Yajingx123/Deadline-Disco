<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AcadBeat | Video Room Lobby</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f1e9df;
            --panel: rgba(255, 255, 255, 0.78);
            --panel-strong: rgba(255, 255, 255, 0.92);
            --ink: #263746;
            --muted: rgba(38, 55, 70, 0.68);
            --line: rgba(38, 55, 70, 0.12);
            --accent: #c86448;
            --accent-soft: rgba(200, 100, 72, 0.12);
            --success: #35705a;
            --warning: #9a6d2f;
            --danger: #a34b4b;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            padding: 24px 18px 40px;
            font-family: "Space Grotesk", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(200,100,72,0.16), transparent 32%),
                radial-gradient(circle at right center, rgba(38,55,70,0.08), transparent 28%),
                var(--bg);
        }

        .shell { width: min(1180px, 100%); margin: 0 auto; display: grid; gap: 20px; }
        .topbar, .hero, .content { display: grid; gap: 20px; }
        .topbar { grid-template-columns: 1fr auto; align-items: center; }
        .hero { grid-template-columns: minmax(0, 1.25fr) minmax(290px, 0.75fr); }
        .content { grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr); }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            color: #fff;
            background: linear-gradient(135deg, var(--accent), #e29b61);
            font-family: "Fraunces", serif;
            font-size: 1.1rem;
        }

        .card {
            padding: 24px;
            border: 1px solid var(--line);
            border-radius: 28px;
            background: var(--panel);
            backdrop-filter: blur(14px);
            box-shadow: 0 20px 56px rgba(38, 55, 70, 0.09);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .eyebrow::before {
            content: "";
            width: 32px;
            height: 1px;
            background: rgba(38, 55, 70, 0.22);
        }

        h1, h2, h3, .identity-name {
            margin: 0;
            letter-spacing: -0.03em;
        }

        h1, .identity-name {
            font-family: "Fraunces", serif;
        }

        h1 { font-size: clamp(2.4rem, 4vw, 4.6rem); line-height: 0.95; margin-bottom: 16px; }
        h2 { font-size: 1.4rem; margin-bottom: 12px; }
        h3 { font-size: 0.78rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); }
        p { margin: 0; line-height: 1.72; color: var(--muted); }

        .summary, .facts, .list, .room-list { display: grid; gap: 14px; }
        .summary { grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 22px; }
        .summary-item, .fact, .list-item, .room-row {
            padding: 16px;
            border: 1px solid rgba(38, 55, 70, 0.08);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.68);
        }

        .summary-item p, .fact p { margin-top: 8px; }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 999px;
            background: rgba(38, 55, 70, 0.08);
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .status::before {
            content: "";
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: currentColor;
        }

        .status.success { color: var(--success); }
        .status.warning { color: var(--warning); }
        .status.danger { color: var(--danger); }

        .identity-name { font-size: 1.9rem; line-height: 1; margin: 12px 0 8px; }
        .watch-copy { margin-top: 12px; font-size: 0.9rem; }

        .btn-row, .nav-actions, .radio-row, .room-row-meta, .toast-actions { display: flex; flex-wrap: wrap; gap: 10px; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: var(--panel-strong);
            color: var(--ink);
            text-decoration: none;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
        }

        .btn.primary { color: #fff; border-color: var(--accent); background: linear-gradient(135deg, var(--accent), #e29062); }
        .btn.danger { color: var(--danger); border-color: rgba(163,75,75,0.26); }
        .btn.ghost { background: transparent; }
        .btn[disabled] { opacity: 0.45; cursor: not-allowed; }

        select {
            width: 100%;
            min-height: 46px;
            padding: 0 14px;
            border: 1px solid rgba(38,55,70,0.14);
            border-radius: 16px;
            background: rgba(255,255,255,0.88);
            color: var(--ink);
            font: inherit;
        }

        .radio {
            flex: 1 1 180px;
            min-width: 0;
            position: relative;
        }

        .radio input {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .radio span {
            display: block;
            min-height: 50px;
            padding: 12px 14px;
            border: 1px solid rgba(38,55,70,0.12);
            border-radius: 18px;
            background: rgba(255,255,255,0.72);
            cursor: pointer;
            line-height: 1.55;
            color: var(--muted);
        }

        .radio input:checked + span {
            background: var(--accent-soft);
            border-color: rgba(200,100,72,0.38);
            color: var(--ink);
        }

        .mono { font-family: Consolas, monospace; font-size: 0.84rem; word-break: break-all; color: var(--ink); }
        .note { margin-top: 10px; font-size: 0.84rem; }

        .room-row { display: grid; gap: 10px; }
        .room-row-head { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .room-chip {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 10px;
            border-radius: 999px;
            border: 1px solid rgba(38,55,70,0.12);
            background: rgba(38,55,70,0.06);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .room-chip.public { color: var(--success); border-color: rgba(53,112,90,0.22); background: rgba(53,112,90,0.08); }
        .room-chip.private { color: var(--warning); border-color: rgba(154,109,47,0.22); background: rgba(154,109,47,0.08); }
        .room-chip.host { color: var(--accent); border-color: rgba(200,100,72,0.22); background: var(--accent-soft); }

        .empty-box {
            padding: 18px;
            border: 1px dashed rgba(38,55,70,0.18);
            border-radius: 18px;
            background: rgba(255,255,255,0.48);
        }

        .toast-stack {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 50;
            display: grid;
            gap: 12px;
            width: min(360px, calc(100vw - 32px));
        }

        .toast {
            padding: 16px 18px;
            border: 1px solid rgba(38,55,70,0.12);
            border-radius: 22px;
            background: rgba(255,255,255,0.94);
            box-shadow: 0 18px 46px rgba(38,55,70,0.14);
        }

        .toast h3 { margin: 0 0 8px; font-size: 0.84rem; letter-spacing: 0.08em; text-transform: uppercase; }

        @media (max-width: 960px) {
            .topbar, .hero, .content, .summary { grid-template-columns: 1fr; }
            .nav-actions { justify-content: flex-start; }
        }
    </style>
</head>
<body>
    <div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="false"></div>
    <main class="shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark">AB</div>
                <div>AcadBeat Video Rooms</div>
            </div>
            <div class="nav-actions">
                <a class="btn ghost" href="../Academic-Practice/training.html">Back To Training</a>
                <a class="btn" href="../home.html">Back To Home</a>
            </div>
        </header>

        <section class="hero">
            <article class="card">
                <div class="eyebrow">Mode 04 - Room Based Video</div>
                <h1>Persistent rooms replace random matching.</h1>
                <p>Create a topic-based room, decide whether it is public or private, then reopen the same room URL until the one-hour lifetime ends.</p>
                <div class="summary">
                    <div class="summary-item">
                        <h3>Entry</h3>
                        <p><code>training.html</code> now routes Mode 04 into the room-based lobby.</p>
                    </div>
                    <div class="summary-item">
                        <h3>Lobby</h3>
                        <p><code>video-match.php</code> now handles room creation, room discovery, invite refresh, and host notifications.</p>
                    </div>
                    <div class="summary-item">
                        <h3>Room</h3>
                        <p><code>zego-call.php</code> stays as the dedicated call page after room access is confirmed.</p>
                    </div>
                </div>
            </article>

            <aside class="card">
                <div class="eyebrow">Identity</div>
                <div class="status" id="authStatus">Checking Session</div>
                <div class="identity-name" id="identityName">Loading account...</div>
                <p id="identityCopy">Verifying the current session before room creation and room discovery become available.</p>
                <p class="watch-copy" id="hostWatchCopy">Hosted-room notifications will start after login verification.</p>
            </aside>
        </section>

        <section class="content">
            <article class="card">
                <div class="eyebrow">Create Room</div>
                <h2 id="stageTitle">Prepare a topic-based room.</h2>
                <p id="stageCopy">Choose a prepared discussion topic and select whether the room should be openly joinable or invite-only.</p>

                <div class="facts" style="margin-top: 18px;">
                    <div class="fact">
                        <h3>Topic</h3>
                        <select id="topicSelect" disabled>
                            <option value="">Loading topics...</option>
                        </select>
                        <p class="note" id="topicPreview">Topic details will appear here after the prepared topic list loads.</p>
                    </div>
                    <div class="fact">
                        <h3>Visibility</h3>
                        <div class="radio-row" style="margin-top: 10px;">
                            <label class="radio">
                                <input type="radio" name="roomVisibility" value="public" checked>
                                <span>Public room. Logged-in users can join directly from the lobby.</span>
                            </label>
                            <label class="radio">
                                <input type="radio" name="roomVisibility" value="private">
                                <span>Private room. Visible in the lobby, but invite access is required for entry.</span>
                            </label>
                        </div>
                        <p class="note">Rooms last one hour. If the host stays alone the whole time, expiry becomes a cancellation instead of a regular expiration.</p>
                    </div>
                    <div class="fact">
                        <h3>Host Controls</h3>
                        <p id="hostedRoomValue">No active hosted room yet.</p>
                        <p class="note" id="stageNote">Hosts can stay on this page, wait for others to enter, and open the actual room only when they want to join the call.</p>
                    </div>
                </div>

                <div class="btn-row" style="margin-top: 18px;">
                    <button class="btn primary" id="createRoomBtn" type="button" disabled>Create Room</button>
                    <button class="btn" id="refreshRoomsBtn" type="button" disabled>Refresh Rooms</button>
                    <button class="btn" id="createInviteBtn" type="button" disabled>Create Invite</button>
                    <button class="btn danger" id="endHostedRoomBtn" type="button" disabled>End Room</button>
                </div>
            </article>

            <aside class="card">
                <div class="eyebrow">Open Rooms</div>
                <div class="list">
                    <div class="list-item">
                        <h3>Hosted Link</h3>
                        <p class="mono" id="hostedLinkValue">No hosted room link yet.</p>
                        <p class="note" id="hostedLinkMeta">Create a room and its current room URL or invite URL will appear here.</p>
                        <div class="btn-row" style="margin-top: 12px;">
                            <a class="btn primary" id="openHostedRoomBtn" href="./video-match.php" hidden>Open Room</a>
                            <button class="btn" id="copyHostedLinkBtn" type="button" disabled>Copy Link</button>
                        </div>
                    </div>

                    <div class="list-item">
                        <h3>Room List</h3>
                        <div class="room-list" id="roomListContainer" style="margin-top: 12px;">
                            <div class="empty-box">Loading room list...</div>
                        </div>
                    </div>

                    <div class="list-item">
                        <h3>Room Re-entry</h3>
                        <p id="recentRoomValue">No saved room URL yet.</p>
                        <p class="note" id="recentRoomMeta">Open a durable room once and the latest re-entry URL will be kept here for quick return.</p>
                        <div class="btn-row" style="margin-top: 12px;">
                            <button class="btn primary" id="reenterRoomBtn" type="button" disabled>Re-enter Room</button>
                            <button class="btn" id="copyRoomUrlBtn" type="button" disabled>Copy URL</button>
                        </div>
                    </div>
                </div>
            </aside>
        </section>
    </main>

    <script src="./video-room-topics.js"></script>
    <script src="./video-room-lobby.js"></script>
</body>
</html>
