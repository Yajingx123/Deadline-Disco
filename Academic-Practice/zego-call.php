<?php
declare(strict_types=1);

$rawConfig = [];
$configPath = __DIR__ . '/api/video/zego-config.php';
if (is_file($configPath)) {
    $loaded = require $configPath;
    if (is_array($loaded)) {
        $rawConfig = $loaded;
    }
}

$tokenMode = (string) ($rawConfig['token_mode'] ?? 'production');
if (!in_array($tokenMode, ['test', 'production'], true)) {
    $tokenMode = 'production';
}

$publicConfig = [
    'appID' => (int) ($rawConfig['app_id'] ?? 0),
    'tokenMode' => $tokenMode,
    'tokenEndpoint' => (string) ($rawConfig['token_endpoint'] ?? './api/video/zego-token.php'),
    'projectName' => (string) ($rawConfig['project_name'] ?? 'AcadBeat Video Match'),
    'brandingLogoUrl' => (string) ($rawConfig['branding_logo_url'] ?? ''),
];

if ($tokenMode === 'test') {
    $publicConfig['testSecret'] = (string) ($rawConfig['test_secret'] ?? $rawConfig['server_secret'] ?? '');
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AcadBeat | Video Room</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #e4dfd8;
            --ink: #33455f;
            --muted: rgba(51, 69, 95, 0.68);
            --line: rgba(51, 69, 95, 0.12);
            --panel: rgba(255, 255, 255, 0.72);
            --warn: #8f613f;
            --danger: #b15c5c;
            --success: #4a7860;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Inter", sans-serif; }
        html, body {
            height: auto;
        }
        body {
            min-height: 100dvh;
            background:
                radial-gradient(circle at top left, rgba(145,171,200,0.28), transparent 32%),
                radial-gradient(circle at bottom right, rgba(51,69,95,0.10), transparent 36%),
                var(--bg);
            color: var(--ink);
            overflow-x: hidden;
            overflow-y: auto;
        }
        .layout { min-height: 100dvh; display: grid; grid-template-columns: minmax(220px, 290px) minmax(0, 1fr); align-items: start; }
        .sidebar {
            padding: 26px 22px;
            border-right: 1px solid var(--line);
            background: rgba(255,255,255,0.32);
            backdrop-filter: blur(12px);
            overflow-y: auto;
        }
        .eyebrow { font-size: 0.76rem; letter-spacing: 0.24em; text-transform: uppercase; opacity: 0.54; margin-bottom: 18px; }
        .headline { font-family: "Playfair Display", serif; font-size: 2.1rem; line-height: 1.06; margin-bottom: 14px; }
        .copy { line-height: 1.74; font-size: 0.94rem; opacity: 0.8; margin-bottom: 22px; }
        .pill-row, .action-row { display: flex; flex-wrap: wrap; gap: 10px; }
        .pill {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 0.77rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            background: rgba(255,255,255,0.56);
        }
        .panel, .status-card {
            margin-top: 18px;
            border: 1px solid var(--line);
            border-radius: 22px;
            background: var(--panel);
            padding: 18px;
            box-shadow: 0 18px 48px rgba(51, 69, 95, 0.08);
        }
        .panel h2 { font-size: 1rem; margin-bottom: 10px; }
        .panel p, .panel li { line-height: 1.7; font-size: 0.9rem; opacity: 0.84; }
        .panel ul { padding-left: 18px; }
        .panel li + li { margin-top: 6px; }
        .status-title { font-size: 0.84rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.7; margin-bottom: 6px; }
        .status-body { font-size: 0.94rem; line-height: 1.7; opacity: 0.84; }
        .status-card.warning { background: rgba(255, 244, 235, 0.82); border-color: rgba(143, 97, 63, 0.24); }
        .status-card.danger { background: rgba(255, 236, 236, 0.84); border-color: rgba(177, 92, 92, 0.26); }
        .status-card.success { background: rgba(240, 248, 243, 0.84); border-color: rgba(74, 120, 96, 0.24); }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid var(--ink);
            background: transparent;
            color: var(--ink);
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
        }
        .btn.primary { background: rgba(145,171,200,0.9); border-color: rgba(145,171,200,0.9); }
        .btn.danger { color: var(--danger); border-color: var(--danger); }
        .stage { padding: 10px; min-height: 0; overflow: visible; }
        .shell {
            width: 100%;
            height: calc(100dvh - 20px);
            border-radius: 22px;
            overflow: hidden;
            border: 1px solid rgba(51,69,95,0.1);
            background: rgba(246, 243, 238, 0.94);
            box-shadow: 0 24px 72px rgba(51, 69, 95, 0.12);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .shell-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--line);
            background: rgba(255,255,255,0.76);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        .shell-header h1 { font-size: 1.02rem; letter-spacing: 0.06em; text-transform: uppercase; }
        .shell-meta { font-size: 0.82rem; opacity: 0.64; }
        #zego-root { width: 100%; flex: 1 1 auto; min-height: 0; background: #f7f4ef; }
        .empty {
            position: absolute;
            inset: 82px 18px 18px;
            border-radius: 24px;
            border: 1px dashed rgba(51,69,95,0.24);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(255,255,255,0.76), rgba(236,232,225,0.88));
            padding: 30px;
            text-align: center;
        }
        .empty[hidden] { display: none !important; }
        .empty-card { max-width: 540px; }
        .empty-title { font-family: "Playfair Display", serif; font-size: 2rem; margin-bottom: 12px; }
        .empty-body { line-height: 1.75; opacity: 0.8; }
        .empty-actions { margin-top: 22px; display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; }
        @media (max-width: 1040px) {
            body { overflow: auto; }
            .layout { grid-template-columns: 1fr; }
            .sidebar { border-right: none; border-bottom: 1px solid var(--line); }
            .shell { height: auto; min-height: calc(100dvh - 24px); }
            #zego-root { min-height: min(72dvh, 560px); }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="eyebrow">Section III - Video Match</div>
            <div class="headline">Video Room</div>
            <p class="copy">This room only accepts an active random-match session from the Academic-Practice video lobby. ZEGO renders the final 1-on-1 call UI after your backend has already paired the users.</p>

            <div class="pill-row">
                <div class="pill">1-on-1 Call</div>
                <div class="pill">Random Match</div>
                <div class="pill" id="tokenModePill">Token mode</div>
            </div>

            <div class="status-card" id="connectionStatus">
                <div class="status-title">Status</div>
                <div class="status-body">Checking your login state, requested room, and active match session...</div>
            </div>

            <div class="panel">
                <h2>How this version works</h2>
                <ul>
                    <li>This page only reads the active random-match session from <code>api/video/video-match-status.php</code>.</li>
                    <li>If the requested <code>roomID</code> does not match the current session, access is blocked.</li>
                    <li>Leaving the call will notify the match API and return you to the video lobby.</li>
                </ul>
            </div>

            <div class="panel">
                <h2>Setup</h2>
                <p>Fill <code>ZEGO_APP_ID</code> and <code>ZEGO_SERVER_SECRET</code> in the server environment. If you stay in <code>test</code> token mode, the page will use the configured test secret directly in the browser.</p>
            </div>

            <div class="action-row">
                <a class="btn primary" href="./video-match.php">Back To Lobby</a>
                <a class="btn" href="./training.html">Back To Training</a>
            </div>
        </aside>

        <main class="stage">
            <div class="shell">
                <div class="shell-header">
                    <div>
                        <h1 id="stageTitle">Video Call Room</h1>
                        <div class="shell-meta" id="stageMeta">Waiting for access check...</div>
                    </div>
                    <button class="btn danger" id="closeCallBtn" type="button">End Call & Back</button>
                </div>
                <div id="zego-root"></div>
                <div class="empty" id="emptyState">
                    <div class="empty-card">
                        <div class="empty-title" id="emptyTitle">Preparing the video room</div>
                        <div class="empty-body" id="emptyBody">Verifying your account, requested room, and active match session before the video call starts.</div>
                        <div class="empty-actions" id="emptyActions">
                            <a class="btn primary" href="./video-match.php">Open Video Lobby</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/@zegocloud/zego-uikit-prebuilt/zego-uikit-prebuilt.js"></script>
    <script>
        const ZEGO_PUBLIC_CONFIG = <?php echo json_encode($publicConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const AUTH_ME_URL = '../Auth/backend/api/me.php';
        const VIDEO_MATCH_STATUS_URL = './api/video/video-match-status.php?touch=0';
        const VIDEO_MATCH_LEAVE_URL = './api/video/video-match-leave.php';

        let activeCallContext = null;
        let leaveState = { requestStarted: false, completed: false };

        function sanitizeRoomValue(value) {
            return String(value || '').replace(/[^A-Za-z0-9_]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '').slice(0, 128);
        }

        function requestedRoomId() {
            const params = new URLSearchParams(window.location.search);
            return sanitizeRoomValue(params.get('roomID'));
        }

        function updateStatus(title, body, tone) {
            const card = document.getElementById('connectionStatus');
            card.className = 'status-card' + (tone ? ` ${tone}` : '');
            card.querySelector('.status-title').textContent = title;
            card.querySelector('.status-body').textContent = body;
        }

        function updateEmptyState(title, body, actionsHtml) {
            document.getElementById('emptyTitle').textContent = title;
            document.getElementById('emptyBody').textContent = body;
            if (typeof actionsHtml === 'string') {
                document.getElementById('emptyActions').innerHTML = actionsHtml;
            }
            document.getElementById('emptyState').hidden = false;
        }

        function hideEmptyState() {
            document.getElementById('emptyState').hidden = true;
        }

        async function fetchJson(url, options = {}) {
            const response = await fetch(url, {
                method: options.method || 'GET',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    ...(options.body ? { 'Content-Type': 'application/json' } : {})
                },
                body: options.body || undefined
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.ok === false || data.status === 'error') {
                const error = new Error(data.message || 'Request failed.');
                error.status = response.status;
                throw error;
            }
            return data;
        }

        function resolveCallContext(matchState, me) {
            const session = matchState?.session || null;
            const room = matchState?.room || null;
            const requestedRoom = requestedRoomId();
            const sessionRoomId = sanitizeRoomValue(session?.roomId || room?.roomId);

            if (!session || !sessionRoomId) {
                return {
                    allowed: false,
                    title: 'No active video session',
                    body: requestedRoom
                        ? `Room ${requestedRoom} does not match any active random-match session for your account.`
                        : 'You do not have an active random-match session right now.',
                    actionsHtml: '<a class="btn primary" href="./video-match.php">Open Video Lobby</a><a class="btn" href="./training.html">Back To Training</a>'
                };
            }

            if (requestedRoom && requestedRoom !== sessionRoomId) {
                return {
                    allowed: false,
                    title: 'Room mismatch',
                    body: `Requested room ${requestedRoom} does not match your current match room ${sessionRoomId}.`,
                    actionsHtml: '<a class="btn primary" href="./video-match.php">Back To Lobby</a>'
                };
            }

            const partner = session.partner || {};
            return {
                allowed: true,
                roomID: sessionRoomId,
                title: `${me.username || 'User'} x ${partner.displayName || partner.username || 'Partner'}`,
                meta: `Random match | Room ${sessionRoomId} | Session ${session.sessionId} | Partner @${partner.username || 'unknown'}`,
                shareName: 'Random match room',
                readyMessage: 'Connected through the random match workflow.'
            };
        }

        function shouldHandleLeave() {
            return Boolean(activeCallContext?.roomID);
        }

        function sendLeaveBeacon(reason) {
            const payload = JSON.stringify({ reason });
            if (navigator.sendBeacon) {
                const blob = new Blob([payload], { type: 'application/json' });
                return navigator.sendBeacon(VIDEO_MATCH_LEAVE_URL, blob);
            }
            fetch(VIDEO_MATCH_LEAVE_URL, {
                method: 'POST',
                credentials: 'include',
                keepalive: true,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: payload
            }).catch(() => {});
            return true;
        }

        async function notifyLeave(reason, options = {}) {
            if (!shouldHandleLeave() || leaveState.requestStarted) {
                return false;
            }
            leaveState.requestStarted = true;
            if (options.useBeacon === true) {
                leaveState.completed = sendLeaveBeacon(reason);
                return leaveState.completed;
            }

            try {
                await fetchJson(VIDEO_MATCH_LEAVE_URL, {
                    method: 'POST',
                    body: JSON.stringify({ reason })
                });
                leaveState.completed = true;
                return true;
            } catch (error) {
                leaveState.requestStarted = false;
                throw error;
            }
        }

        function bindLeaveHooks() {
            const handleExit = () => {
                void notifyLeave('page_unload', { useBeacon: true });
            };
            window.addEventListener('pagehide', handleExit, { once: true });
            window.addEventListener('beforeunload', handleExit, { once: true });
        }

        async function closeCallAndReturn() {
            try {
                if (shouldHandleLeave() && !leaveState.completed) {
                    await notifyLeave('return_to_lobby');
                }
            } catch (_error) {
                updateStatus('Leave sync failed', 'Returning to the lobby, but session cleanup did not complete cleanly.', 'warning');
            } finally {
                window.location.href = './video-match.php';
            }
        }

        async function buildKitToken(roomID, userID, userName) {
            if (!ZEGO_PUBLIC_CONFIG.appID) {
                throw new Error('Missing ZEGO_APP_ID. Configure api/video/zego-config.php first.');
            }

            if (ZEGO_PUBLIC_CONFIG.tokenMode === 'production') {
                const tokenUrl = new URL(ZEGO_PUBLIC_CONFIG.tokenEndpoint, window.location.href);
                tokenUrl.searchParams.set('userID', userID);
                tokenUrl.searchParams.set('roomID', roomID);
                tokenUrl.searchParams.set('effectiveTimeInSeconds', '3600');
                const tokenResponse = await fetchJson(tokenUrl.toString());
                if (!tokenResponse.token) {
                    throw new Error(tokenResponse.message || 'Production token endpoint returned no token.');
                }
                const resolvedUserID = String(tokenResponse.userID || userID);
                return ZegoUIKitPrebuilt.generateKitTokenForProduction(
                    ZEGO_PUBLIC_CONFIG.appID,
                    tokenResponse.token,
                    roomID,
                    resolvedUserID,
                    userName
                );
            }

            if (!ZEGO_PUBLIC_CONFIG.testSecret) {
                throw new Error('Missing ZEGO test secret. Fill ZEGO_SERVER_SECRET or ZEGO_APP_SIGN first.');
            }

            return ZegoUIKitPrebuilt.generateKitTokenForTest(
                ZEGO_PUBLIC_CONFIG.appID,
                ZEGO_PUBLIC_CONFIG.testSecret,
                roomID,
                userID,
                userName
            );
        }

        async function mountVideoRoom() {
            document.getElementById('tokenModePill').textContent = `Token mode: ${ZEGO_PUBLIC_CONFIG.tokenMode}`;

            let me;
            let matchPayload;

            try {
                const [meData, statusData] = await Promise.all([
                    fetchJson(AUTH_ME_URL),
                    fetchJson(VIDEO_MATCH_STATUS_URL)
                ]);
                me = meData.user;
                matchPayload = statusData;
            } catch (error) {
                if (error.status === 401) {
                    updateStatus('Login required', 'You need to log in before opening the video room.', 'warning');
                    updateEmptyState('Login required', 'Your session is missing or expired. Go back, log in, and reopen the video room.', '<a class="btn primary" href="../home.html?login=1">Go To Login</a>');
                    return;
                }

                updateStatus('Connection failed', error.message || 'Unable to load room data.', 'danger');
                updateEmptyState('Unable to verify access', error.message || 'The page could not verify your account and room access.', '<a class="btn primary" href="./video-match.php">Back To Lobby</a>');
                return;
            }

            const callContext = resolveCallContext(matchPayload, me);
            activeCallContext = callContext;

            if (!callContext.allowed || !callContext.roomID) {
                updateStatus(callContext.title || 'Access denied', callContext.body || 'No active room is available for this account.', 'warning');
                updateEmptyState(callContext.title || 'No active video session', callContext.body || 'This page could not resolve a valid room for your account.', callContext.actionsHtml);
                return;
            }

            const roomID = callContext.roomID;
            const userID = sanitizeRoomValue(`acadbeat_${me.user_id || me.userId || me.username}`);
            const userName = String(me.username || 'AcadBeat User');

            document.getElementById('stageTitle').textContent = callContext.title || 'Video Call Room';
            document.getElementById('stageMeta').textContent = callContext.meta || `Room ${roomID}`;

            try {
                const kitToken = await buildKitToken(roomID, userID, userName);
                const zp = ZegoUIKitPrebuilt.create(kitToken);
                const root = document.getElementById('zego-root');
                const shareLink = `${window.location.origin}${window.location.pathname}?roomID=${encodeURIComponent(roomID)}`;
                const joinConfig = {
                    container: root,
                    maxUsers: 2,
                    showPreJoinView: true,
                    turnOnCameraWhenJoining: true,
                    turnOnMicrophoneWhenJoining: true,
                    showScreenSharingButton: false,
                    showTextChat: true,
                    showUserList: true,
                    showRoomTimer: true,
                    sharedLinks: [{ name: callContext.shareName || 'Room link', url: shareLink }],
                    scenario: { mode: ZegoUIKitPrebuilt.OneONoneCall },
                    onLeaveRoom: async () => {
                        try {
                            await notifyLeave('user_left');
                        } catch (_error) {
                            updateStatus('Leave sync failed', 'The call closed, but the session cleanup request did not complete cleanly.', 'warning');
                        } finally {
                            window.location.href = './video-match.php';
                        }
                    }
                };

                if (ZEGO_PUBLIC_CONFIG.brandingLogoUrl) {
                    joinConfig.branding = { logoURL: ZEGO_PUBLIC_CONFIG.brandingLogoUrl };
                }

                zp.joinRoom(joinConfig);
                bindLeaveHooks();
                hideEmptyState();
                updateStatus(
                    'Room ready',
                    ZEGO_PUBLIC_CONFIG.tokenMode === 'test'
                        ? `${callContext.readyMessage} Switch to production token generation before going live.`
                        : callContext.readyMessage,
                    ZEGO_PUBLIC_CONFIG.tokenMode === 'test' ? 'warning' : 'success'
                );
            } catch (error) {
                updateStatus('ZEGO setup error', error.message || 'Unable to start the call room.', 'danger');
                updateEmptyState('ZEGO configuration required', error.message || 'The page could not build a valid ZEGO kit token.', '<a class="btn primary" href="./video-match.php">Back To Lobby</a><a class="btn" href="./training.html">Back To Training</a>');
            }
        }

        document.getElementById('closeCallBtn').addEventListener('click', () => { void closeCallAndReturn(); });
        mountVideoRoom();
    </script>
</body>
</html>
