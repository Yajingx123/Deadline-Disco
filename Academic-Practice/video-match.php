<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AcadBeat | Random Video Match</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #e4dfd8;
            --ink: #33455f;
            --muted: rgba(51, 69, 95, 0.68);
            --line: rgba(51, 69, 95, 0.12);
            --panel: rgba(255, 255, 255, 0.68);
            --strong: rgba(255, 255, 255, 0.86);
            --accent: #91abc8;
            --success: #43755d;
            --warning: #af7b3f;
            --danger: #ad5757;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Inter", sans-serif; }
        body {
            min-height: 100vh;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(145,171,200,0.28), transparent 32%),
                radial-gradient(circle at bottom right, rgba(51,69,95,0.10), transparent 34%),
                var(--bg);
        }

        .shell { width: min(1100px, calc(100% - 40px)); margin: 0 auto; padding: 32px 0 44px; }
        .topbar, .hero, .body-grid, .meta-grid { display: grid; gap: 18px; }
        .topbar { grid-template-columns: 1fr auto; align-items: center; margin-bottom: 28px; }
        .hero { grid-template-columns: 1.3fr 0.9fr; margin-bottom: 22px; }
        .body-grid { grid-template-columns: 1.2fr 0.8fr; }
        .meta-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 18px; }

        .logo { font-size: 1.3rem; font-weight: 800; letter-spacing: -0.03em; text-transform: uppercase; }
        .logo span { color: var(--accent); }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; }

        .card {
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 26px;
            background: var(--panel);
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 48px rgba(51, 69, 95, 0.08);
        }

        .eyebrow {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 14px;
            color: var(--muted);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 34px;
            height: 1px;
            background: rgba(51, 69, 95, 0.22);
        }

        h1 {
            font-family: "Playfair Display", serif;
            font-size: clamp(2.3rem, 4vw, 4.4rem);
            line-height: 0.98;
            letter-spacing: -0.04em;
            margin-bottom: 16px;
        }

        h2 { font-size: 1.5rem; line-height: 1.15; letter-spacing: -0.03em; margin-bottom: 12px; }
        p { line-height: 1.75; }
        .copy { color: var(--muted); }
        .summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 22px; }
        .summary-item, .list-item, .meta-item {
            border: 1px solid rgba(51, 69, 95, 0.08);
            border-radius: 20px;
            background: rgba(255,255,255,0.62);
            padding: 16px;
        }
        .summary-item h3, .list-item h3, .meta-item h3 {
            margin-bottom: 8px;
            font-size: 0.76rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 999px;
            background: rgba(51, 69, 95, 0.08);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--ink);
        }
        .status::before { content: ""; width: 10px; height: 10px; border-radius: 50%; background: currentColor; }
        .status.success { color: var(--success); }
        .status.warning { color: var(--warning); }
        .status.danger { color: var(--danger); }

        .name { font-size: 1.45rem; font-weight: 800; letter-spacing: -0.03em; margin: 12px 0 8px; }
        .button-row { display: flex; flex-wrap: wrap; gap: 10px; }
        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: var(--strong);
            color: var(--ink);
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
        }
        .btn.primary { background: var(--ink); border-color: var(--ink); color: #fff; }
        .btn[disabled] { opacity: 0.42; cursor: not-allowed; }
        .mono { font-family: Consolas, monospace; font-size: 0.86rem; word-break: break-all; }
        .note { margin-top: 14px; font-size: 0.84rem; color: var(--muted); }
        code { padding: 2px 6px; border-radius: 999px; background: rgba(51, 69, 95, 0.08); font-size: 0.82em; }
        .list { display: grid; gap: 14px; }

        @media (max-width: 920px) {
            .topbar, .hero, .body-grid, .summary { grid-template-columns: 1fr; }
            .actions { justify-content: flex-start; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <header class="topbar">
            <div class="logo">Acad<span>Beat</span></div>
            <div class="actions">
                <a class="btn" href="./training.html">Back To Training</a>
                <a class="btn" href="../home.html">Back To Home</a>
            </div>
        </header>

        <section class="hero">
            <article class="card">
                <div class="eyebrow">Video Match Lobby</div>
                <h1>Random one-on-one video match.</h1>
                <p class="copy">Start matching here, wait for a partner, and continue into the call once the room is ready.</p>
                <div class="summary">
                    <div class="summary-item">
                        <h3>Entry</h3>
                        <p><code>training.html</code> is the recommended launch point.</p>
                    </div>
                    <div class="summary-item">
                        <h3>Lobby</h3>
                        <p><code>video-match.php</code> owns queue, polling, cancel, match, and leave actions.</p>
                    </div>
                    <div class="summary-item">
                        <h3>Room</h3>
                        <p><code>zego-call.php</code> remains the final room runtime after backend pairing succeeds.</p>
                    </div>
                </div>
            </article>

            <aside class="card">
                <div class="eyebrow">Identity</div>
                <div class="status" id="authStatus">Checking Session</div>
                <div class="name" id="identityName">Loading account...</div>
                <p class="copy" id="identityCopy">Verifying the current login state before this page becomes the random match lobby.</p>
            </aside>
        </section>

        <section class="body-grid">
            <article class="card">
                <div class="eyebrow">Match Flow</div>
                <h2 id="stageTitle">Loading current matchmaking state.</h2>
                <p class="copy" id="stageCopy">Checking your current queue and session state before enabling matchmaking actions.</p>
                <div class="button-row">
                    <button class="btn primary" id="startMatchBtn" type="button" disabled>Start Matching</button>
                    <button class="btn" id="cancelMatchBtn" type="button" disabled>Cancel Queue</button>
                    <button class="btn" id="enterRoomBtn" type="button" disabled>Prepare Room</button>
                </div>
                <div class="meta-grid">
                    <div class="meta-item">
                        <h3>Queue Mode</h3>
                        <p id="queueModeValue">random_1v1</p>
                    </div>
                    <div class="meta-item">
                        <h3>Queue Status</h3>
                        <p id="queueStatusValue">Idle</p>
                    </div>
                    <div class="meta-item">
                        <h3>Request Token</h3>
                        <p class="mono" id="requestTokenValue">Not in queue</p>
                    </div>
                    <div class="meta-item">
                        <h3>Last Heartbeat</h3>
                        <p id="heartbeatValue">Not started</p>
                    </div>
                </div>
                <p class="note" id="stageNote">Start matching, wait for a partner, then continue into the call from here.</p>
            </article>

            <aside class="card">
                <div class="eyebrow">Live State</div>
                <div class="list">
                    <div class="list-item">
                        <h3>Partner</h3>
                        <p id="partnerValue">Waiting for a match.</p>
                    </div>
                    <div class="list-item">
                        <h3>Session</h3>
                        <p id="sessionValue">No active session.</p>
                    </div>
                    <div class="list-item">
                        <h3>Room Handshake</h3>
                        <p id="roomValue">Room not prepared yet.</p>
                    </div>
                    <div class="list-item">
                        <h3>Next Transfer</h3>
                        <p id="roomLinkValue">Prepare the room and this lobby will continue into the ZEGO page.</p>
                    </div>
                </div>
            </aside>
        </section>
    </main>

    <script>
        const AUTH_ME_URL = '../Auth/backend/api/me.php';
        const VIDEO_MATCH_JOIN_URL = './api/video/video-match-join.php';
        const VIDEO_MATCH_STATUS_URL = './api/video/video-match-status.php';
        const VIDEO_MATCH_CANCEL_URL = './api/video/video-match-cancel.php';
        const VIDEO_MATCH_ROOM_URL = './api/video/video-match-room.php';
        const VIDEO_MATCH_LEAVE_URL = './api/video/video-match-leave.php';

        const authStatusEl = document.getElementById('authStatus');
        const identityNameEl = document.getElementById('identityName');
        const identityCopyEl = document.getElementById('identityCopy');
        const stageTitleEl = document.getElementById('stageTitle');
        const stageCopyEl = document.getElementById('stageCopy');
        const stageNoteEl = document.getElementById('stageNote');
        const startMatchBtn = document.getElementById('startMatchBtn');
        const cancelMatchBtn = document.getElementById('cancelMatchBtn');
        const enterRoomBtn = document.getElementById('enterRoomBtn');
        const queueModeValueEl = document.getElementById('queueModeValue');
        const queueStatusValueEl = document.getElementById('queueStatusValue');
        const requestTokenValueEl = document.getElementById('requestTokenValue');
        const heartbeatValueEl = document.getElementById('heartbeatValue');
        const partnerValueEl = document.getElementById('partnerValue');
        const sessionValueEl = document.getElementById('sessionValue');
        const roomValueEl = document.getElementById('roomValue');
        const roomLinkValueEl = document.getElementById('roomLinkValue');

        const lobbyState = {
            user: null,
            match: { mode: 'idle', queue: null, session: null, room: null, message: '', action: '' },
            pollingTimer: null,
            actionPending: '',
            roomHandshakeReady: false
        };

        function setAuthState(kind, title, copy) {
            authStatusEl.className = `status ${kind}`;
            authStatusEl.textContent = title;
            identityCopyEl.textContent = copy;
        }

        function formatDateTime(value) {
            if (!value) return 'Not available';
            const date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return String(value);
            return date.toLocaleString('en-US', {
                hour12: false,
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        function compactValue(value, fallback = 'Not available') {
            const text = String(value || '').trim();
            if (!text) return fallback;
            if (text.length <= 20) return text;
            return `${text.slice(0, 8)}...${text.slice(-8)}`;
        }

        function normalizeMatchPayload(payload) {
            const state = payload?.state && typeof payload.state === 'object' ? payload.state : {};
            return {
                ok: payload?.ok !== false,
                action: payload?.action || '',
                message: payload?.message || '',
                mode: payload?.mode || state.mode || 'idle',
                queue: payload?.queue || state.queue || null,
                session: payload?.session || state.session || null,
                room: payload?.room || null,
                endedSession: payload?.endedSession || null
            };
        }

        async function fetchJson(url, options = {}) {
            const response = await fetch(url, {
                method: options.method || 'GET',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                    ...(options.headers || {})
                },
                body: options.body || undefined
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.ok === false || data.status === 'error') {
                const error = new Error(data.message || 'Request failed.');
                error.status = response.status;
                error.payload = data;
                throw error;
            }
            return data;
        }

        async function requestMatchState(url, options = {}) {
            return normalizeMatchPayload(await fetchJson(url, options));
        }

        function startPolling() {
            if (lobbyState.pollingTimer !== null) return;
            lobbyState.pollingTimer = window.setInterval(() => syncMatchState(true, true), 4000);
        }

        function stopPolling() {
            if (lobbyState.pollingTimer === null) return;
            window.clearInterval(lobbyState.pollingTimer);
            lobbyState.pollingTimer = null;
        }

        function syncPollingForMode(mode) {
            if (mode === 'waiting' || mode === 'matched') startPolling();
            else stopPolling();
        }

        function renderButtons() {
            const mode = lobbyState.match.mode || 'idle';
            const pending = lobbyState.actionPending !== '';
            startMatchBtn.disabled = !lobbyState.user || pending || mode !== 'idle';
            cancelMatchBtn.disabled = !lobbyState.user || pending || mode === 'idle';
            enterRoomBtn.disabled = !lobbyState.user || pending || mode !== 'matched';
            cancelMatchBtn.textContent = mode === 'matched' ? 'Leave Session' : 'Cancel Queue';
            enterRoomBtn.textContent = lobbyState.roomHandshakeReady ? 'Enter Room' : 'Prepare Room';
        }

        function openPreparedRoom() {
            const roomPageUrl = String(lobbyState.match?.room?.roomPageUrl || '').trim();
            if (!roomPageUrl) return false;
            window.location.assign(roomPageUrl);
            return true;
        }

        function renderMatchState() {
            const { mode, queue, session, room, message, action, endedSession } = lobbyState.match;
            const partner = session?.partner || null;
            const sessionStatus = session?.status || '';

            queueModeValueEl.textContent = queue?.queueMode || session?.queueMode || 'random_1v1';
            queueStatusValueEl.textContent = queue?.status || 'idle';
            requestTokenValueEl.textContent = queue?.requestToken ? compactValue(queue.requestToken) : 'Not in queue';
            heartbeatValueEl.textContent = queue?.lastHeartbeatAt ? formatDateTime(queue.lastHeartbeatAt) : 'Not started';

            if (mode === 'idle') {
                stageTitleEl.textContent = 'Ready to join the random 1-on-1 queue.';
                stageCopyEl.textContent = lobbyState.user
                    ? 'Start matching to enter the queue. This page will keep polling your state and update as soon as another logged-in user is paired with you.'
                    : 'Sign in first to use the random video match flow.';
                partnerValueEl.textContent = endedSession?.partner?.displayName ? `Last session ended with ${endedSession.partner.displayName}.` : 'Waiting for a match.';
                sessionValueEl.textContent = endedSession?.sessionId ? `Previous session ${endedSession.sessionId} ended with reason ${endedSession.endedReason || 'user_left'}.` : 'No active session.';
                roomValueEl.textContent = 'Room not prepared yet.';
                roomLinkValueEl.textContent = 'When your next match is ready, this page will take you into the call.';
                stageNoteEl.innerHTML = endedSession?.roomId ? `Session <code>${endedSession.roomId}</code> has been closed.` : 'Join the queue to start a new 1-on-1 call.';
                lobbyState.roomHandshakeReady = false;
            } else if (mode === 'waiting') {
                stageTitleEl.textContent = 'Looking for another available user.';
                stageCopyEl.textContent = 'You are in the queue now. This page is polling the backend every few seconds and refreshing your queue heartbeat automatically.';
                partnerValueEl.textContent = 'No partner assigned yet.';
                sessionValueEl.textContent = queue?.enqueuedAt ? `Queued since ${formatDateTime(queue.enqueuedAt)}.` : 'Queued and waiting for pairing.';
                roomValueEl.textContent = 'Room will be generated after a successful match.';
                roomLinkValueEl.textContent = 'There is no room URL before the backend creates a session.';
                stageNoteEl.innerHTML = `Queue request token <code>${queue?.requestToken || 'pending'}</code> is active.`;
                lobbyState.roomHandshakeReady = false;
            } else {
                stageTitleEl.textContent = partner?.displayName ? `Matched with ${partner.displayName}.` : 'Match found.';
                stageCopyEl.textContent = lobbyState.roomHandshakeReady
                    ? 'Your room is ready. Use Enter Room to continue into the call.'
                    : 'Your session is ready. Select Prepare Room to continue into the call.';
                partnerValueEl.textContent = partner ? `${partner.displayName} (@${partner.username})` : 'Partner data is unavailable.';
                sessionValueEl.textContent = session?.sessionId ? `Session ${session.sessionId} is ${sessionStatus || 'matched'}${session?.matchedAt ? ` since ${formatDateTime(session.matchedAt)}` : '.'}` : 'Session metadata is loading.';
                roomValueEl.textContent = room?.roomId ? `Room ${room.roomId} is prepared with status ${room.sessionStatus || sessionStatus || 'matched'}.` : 'Prepare Room will finish the room handoff and open the call.';
                roomLinkValueEl.textContent = room?.roomPageUrl || 'The room link will appear after Prepare Room succeeds.';
                stageNoteEl.innerHTML = room?.roomPageUrl ? 'Room ready. Use <code>Enter Room</code> if you need to open it again.' : 'Select <code>Prepare Room</code> to continue into the call.';
                lobbyState.roomHandshakeReady = Boolean(room?.roomPageUrl);
            }

            if (action === 'queue_cancelled') stageNoteEl.innerHTML = 'Queue cancelled successfully.';
            if (action === 'session_left') stageNoteEl.innerHTML = endedSession?.roomId ? `Video session <code>${endedSession.roomId}</code> ended and the queue state returned to idle.` : 'There was no active session to leave.';
            if (message && mode !== 'waiting' && action === 'room_opened') stageNoteEl.innerHTML = `Room handshake acknowledged: <code>${message}</code>. Redirecting to the ZEGO room now.`;

            renderButtons();
            syncPollingForMode(mode);
        }

        function applyActionState(actionName) {
            lobbyState.actionPending = actionName;
            renderButtons();
        }

        function clearActionState() {
            lobbyState.actionPending = '';
            renderButtons();
        }

        async function syncMatchState(touchHeartbeat = false, silent = false) {
            if (!lobbyState.user) return;
            const separator = VIDEO_MATCH_STATUS_URL.includes('?') ? '&' : '?';
            const url = `${VIDEO_MATCH_STATUS_URL}${separator}touch=${touchHeartbeat ? '1' : '0'}`;
            if (!silent) applyActionState('sync');
            try {
                lobbyState.match = await requestMatchState(url);
                renderMatchState();
            } catch (error) {
                stageTitleEl.textContent = 'Unable to sync match state.';
                stageCopyEl.textContent = error.message || 'The lobby could not refresh queue or session status.';
                stageNoteEl.textContent = 'Retry after the matchmaking API becomes reachable.';
                stopPolling();
                renderButtons();
            } finally {
                if (!silent) clearActionState();
            }
        }

        async function joinQueue() {
            applyActionState('join');
            try {
                lobbyState.match = await requestMatchState(VIDEO_MATCH_JOIN_URL, {
                    method: 'POST',
                    body: JSON.stringify({ cameraEnabled: true, microphoneEnabled: true })
                });
                renderMatchState();
            } catch (error) {
                stageTitleEl.textContent = 'Unable to join the queue.';
                stageCopyEl.textContent = error.message || 'The backend rejected the queue join request.';
                stageNoteEl.textContent = 'Check your login state and database migration status before retrying.';
                renderButtons();
            } finally {
                clearActionState();
            }
        }

        async function cancelOrLeave() {
            const mode = lobbyState.match.mode || 'idle';
            if (mode === 'idle') return;
            applyActionState(mode === 'matched' ? 'leave' : 'cancel');
            try {
                lobbyState.match = mode === 'matched'
                    ? await requestMatchState(VIDEO_MATCH_LEAVE_URL, { method: 'POST', body: JSON.stringify({ reason: 'user_left' }) })
                    : await requestMatchState(VIDEO_MATCH_CANCEL_URL, { method: 'POST', body: JSON.stringify({}) });
                renderMatchState();
            } catch (error) {
                stageTitleEl.textContent = mode === 'matched' ? 'Unable to leave the current session.' : 'Unable to cancel the queue.';
                stageCopyEl.textContent = error.message || 'The matchmaking backend did not accept the action.';
                renderButtons();
            } finally {
                clearActionState();
            }
        }

        async function prepareRoom() {
            if ((lobbyState.match.mode || 'idle') !== 'matched') return;
            if (lobbyState.roomHandshakeReady) {
                openPreparedRoom();
                return;
            }

            applyActionState('room');
            try {
                lobbyState.match = await requestMatchState(VIDEO_MATCH_ROOM_URL, { method: 'POST', body: JSON.stringify({}) });
                renderMatchState();
                if (!openPreparedRoom()) {
                    stageTitleEl.textContent = 'Room prepared.';
                    stageCopyEl.textContent = 'The room was prepared, but no room URL was available for redirect.';
                }
            } catch (error) {
                stageTitleEl.textContent = 'Unable to prepare the room.';
                stageCopyEl.textContent = error.message || 'The backend room handshake failed.';
                renderButtons();
            } finally {
                clearActionState();
            }
        }

        async function loadCurrentUser() {
            try {
                const response = await fetch(AUTH_ME_URL, { method: 'GET', credentials: 'include', headers: { Accept: 'application/json' } });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.status !== 'success' || !data.user) {
                    lobbyState.user = null;
                    identityNameEl.textContent = 'Login required';
                    setAuthState('warning', 'Session Missing', 'Open this page after signing in. Matchmaking actions stay disabled until the session is valid.');
                    renderButtons();
                    return;
                }

                lobbyState.user = data.user;
                identityNameEl.textContent = data.user.username;
                setAuthState('success', 'Session Ready', 'Queue, polling, room-open, and leave actions are now handled on this page.');
                await syncMatchState(false, false);
            } catch (error) {
                lobbyState.user = null;
                identityNameEl.textContent = 'Unable to verify account';
                setAuthState('danger', 'Check Failed', 'The auth check failed before matchmaking could start. Retry after the backend is reachable.');
                stageTitleEl.textContent = 'Account verification failed.';
                stageCopyEl.textContent = 'The lobby cannot join or poll matchmaking state until login verification succeeds.';
                stageNoteEl.textContent = 'Resolve the auth API error first, then retry.';
                renderButtons();
            }
        }

        startMatchBtn.addEventListener('click', joinQueue);
        cancelMatchBtn.addEventListener('click', cancelOrLeave);
        enterRoomBtn.addEventListener('click', prepareRoom);
        loadCurrentUser();
    </script>
</body>
</html>
