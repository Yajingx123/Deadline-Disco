<?php
declare(strict_types=1);

$rawConfig = [];
$configPath = __DIR__ . '/../api/video/zego-config.php';
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

$tokenEndpoint = (string) ($rawConfig['token_endpoint'] ?? '../api/video/zego-token.php');
if ($tokenEndpoint === '') {
    $tokenEndpoint = '../api/video/zego-token.php';
} elseif (!preg_match('#^(?:https?:)?/#', $tokenEndpoint)) {
    if (str_starts_with($tokenEndpoint, './api/video/')) {
        $tokenEndpoint = '../api/video/' . substr($tokenEndpoint, strlen('./api/video/'));
    } elseif (str_starts_with($tokenEndpoint, 'api/video/')) {
        $tokenEndpoint = '../' . $tokenEndpoint;
    }
}

$publicConfig = [
    'appID' => (int) ($rawConfig['app_id'] ?? 0),
    'tokenMode' => $tokenMode,
    'tokenEndpoint' => $tokenEndpoint,
    'projectName' => (string) ($rawConfig['project_name'] ?? 'AcadBeat Video Call'),
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
    <title>AcadBeat | Video Call Room</title>
    <link rel="stylesheet" href="../../shared-nav.css">
    <script src="../../shared/acadbeat-local-config.js"></script>
    <link rel="stylesheet" href="../practice-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #e7dfd6;
            --ink: #33455f;
            --muted: rgba(51, 69, 95, 0.68);
            --line: rgba(51, 69, 95, 0.12);
            --panel: rgba(255, 255, 255, 0.74);
            --strong: rgba(255, 255, 255, 0.92);
            --accent: #91abc8;
            --success: #4c7960;
            --warning: #946941;
            --danger: #b26060;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Inter", sans-serif; }
        body {
            min-height: 100vh;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(145,171,200,0.28), transparent 30%),
                radial-gradient(circle at bottom right, rgba(51,69,95,0.10), transparent 34%),
                var(--bg);
        }
        .room-page-shell {
            width: min(calc(100% - 12px), 1760px);
            margin: 96px auto 28px;
        }
        .layout {
            min-height: calc(100vh - 124px);
            display: grid;
            grid-template-columns: minmax(300px, 360px) minmax(0, 1fr);
            gap: 20px;
        }
        .sidebar,
        .shell {
            border: 1px solid var(--line);
            border-radius: 26px;
            background: var(--panel);
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 44px rgba(51, 69, 95, 0.08);
        }
        .sidebar {
            padding: 28px;
            display: grid;
            align-content: start;
            gap: 18px;
        }
        .eyebrow {
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .headline {
            font-family: "Playfair Display", serif;
            font-size: 2rem;
            letter-spacing: -0.03em;
            line-height: 1;
        }
        .copy,
        .status-body,
        .panel p,
        .panel li {
            color: var(--muted);
            line-height: 1.72;
        }
        .status-card,
        .panel {
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 16px;
            background: var(--strong);
        }
        .status-card.success { border-color: rgba(76,121,96,0.24); }
        .status-card.warning { border-color: rgba(148,105,65,0.24); }
        .status-card.danger { border-color: rgba(178,96,96,0.24); }
        .status-title {
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .pill-row,
        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .pill,
        .btn {
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .pill {
            border: 1px solid var(--line);
            padding: 9px 13px;
            background: rgba(255,255,255,0.88);
        }
        .btn {
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.88);
            color: var(--ink);
            text-decoration: none;
            cursor: pointer;
        }
        .btn.primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }
        .btn.danger {
            color: var(--danger);
            border-color: rgba(178,96,96,0.32);
        }
        .panel ul { padding-left: 18px; }
        .panel li + li { margin-top: 8px; }
        .stage { min-width: 0; }
        .shell {
            min-height: calc(100vh - 28px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .shell-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--line);
            background: rgba(255,255,255,0.82);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }
        .header-main {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }
        .back-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.9);
            color: var(--ink);
            font-size: 1.2rem;
            cursor: pointer;
            flex: 0 0 auto;
        }
        .shell-header h1 {
            font-size: 1rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .shell-meta {
            margin-top: 4px;
            color: var(--muted);
            font-size: 0.86rem;
        }
        #zego-root {
            width: 100%;
            flex: 1 1 auto;
            min-height: 0;
            background: #f7f4ef;
        }
        .empty {
            position: absolute;
            inset: 92px 18px 18px;
            border-radius: 24px;
            border: 1px dashed rgba(51,69,95,0.22);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px;
            background: linear-gradient(180deg, rgba(255,255,255,0.94), rgba(241,236,230,0.92));
        }
        .empty[hidden] { display: none !important; }
        .empty-card { max-width: 520px; }
        .empty-title {
            font-family: "Playfair Display", serif;
            font-size: 2rem;
            margin-bottom: 12px;
        }
        .empty-body {
            color: var(--muted);
            line-height: 1.72;
        }
        .empty-actions {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .shell-stage {
            position: relative;
            flex: 1 1 auto;
            min-height: 0;
        }
        @media (max-width: 1024px) {
            .layout { grid-template-columns: 1fr; }
            .shell { min-height: 72vh; }
            .shell-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div id="acadbeatNav"></div>
    <main class="room-page-shell">
    <div class="layout">
        <aside class="sidebar">
            <div class="eyebrow">Academic Practice</div>
            <div class="headline">Video Call Room</div>
            <p class="copy">This page keeps the built-in ZEGO call runtime, but room access now comes from topic rooms and invite URLs instead of random matching.</p>

            <div class="pill-row">
                <div class="pill" id="topicPill">Topic</div>
                <div class="pill" id="visibilityPill">Visibility</div>
                <div class="pill" id="tokenModePill">Token mode</div>
            </div>

            <div class="status-card" id="connectionStatus">
                <div class="status-title">Status</div>
                <div class="status-body">Checking your room access and preparing the call.</div>
            </div>

            <div class="panel">
                <div class="status-title">How It Works</div>
                <ul>
                    <li>Public rooms can be opened from the room list.</li>
                    <li>Private rooms can be opened from the invite URL in direct message.</li>
                    <li>Leaving the room keeps your original lobby page untouched in the previous tab.</li>
                </ul>
            </div>

            <div class="panel">
                <div class="status-title">Setup</div>
                <p>Fill <code>ZEGO_APP_ID</code> and <code>ZEGO_SERVER_SECRET</code> in the server environment. Test mode still works for local verification.</p>
            </div>

            <div class="action-row">
                <a class="btn primary" href="./index.php">Back To Lobby</a>
                <a class="btn" href="../training.html">Back To Training</a>
            </div>
        </aside>

        <main class="stage">
            <div class="shell">
                <div class="shell-header">
                    <div class="header-main">
                        <button class="back-btn" id="backToLobbyBtn" type="button" aria-label="Back">←</button>
                        <div>
                            <h1 id="stageTitle">Video Room</h1>
                            <div class="shell-meta" id="stageMeta">Preparing the room...</div>
                        </div>
                    </div>
                    <button class="btn danger" id="closeCallBtn" type="button">Leave Room</button>
                </div>
                <div class="shell-stage">
                    <div id="zego-root"></div>
                    <div class="empty" id="emptyState">
                        <div class="empty-card">
                            <div class="empty-title" id="emptyTitle">Preparing the video room</div>
                            <div class="empty-body" id="emptyBody">Checking the room URL, your room membership, and ZEGO configuration.</div>
                            <div class="empty-actions" id="emptyActions">
                                <a class="btn primary" href="./index.php">Open Room Lobby</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    </main>

    <script src="../../shared-nav.js"></script>
    <script src="https://unpkg.com/@zegocloud/zego-uikit-prebuilt/zego-uikit-prebuilt.js"></script>
    <script>
        const ZEGO_PUBLIC_CONFIG = <?php echo json_encode($publicConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const ROOM_ACCESS_URL = '../api/video/video-call-access.php';
        const ROOM_LEAVE_URL = '../api/video/video-call-leave.php';
        const AUTH_ME_URL = '../../Auth/backend/api/me.php';

        let activeRoom = null;
        let leaveStarted = false;

        function sanitizeRoomValue(value) {
            return String(value || '').replace(/[^A-Za-z0-9_]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '');
        }

        function requestedRoomId() {
            return sanitizeRoomValue(new URLSearchParams(window.location.search).get('roomID'));
        }

        function updateStatus(title, body, tone) {
            const card = document.getElementById('connectionStatus');
            card.className = `status-card${tone ? ` ${tone}` : ''}`;
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

        async function buildKitToken(roomID, userID, userName) {
            if (!ZEGO_PUBLIC_CONFIG.appID) {
                throw new Error('Missing ZEGO_APP_ID. Configure Academic-Practice/api/video/zego-config.php first.');
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
                return ZegoUIKitPrebuilt.generateKitTokenForProduction(
                    ZEGO_PUBLIC_CONFIG.appID,
                    tokenResponse.token,
                    roomID,
                    String(tokenResponse.userID || userID),
                    userName
                );
            }

            if (!ZEGO_PUBLIC_CONFIG.testSecret) {
                throw new Error('Missing ZEGO test secret.');
            }

            return ZegoUIKitPrebuilt.generateKitTokenForTest(
                ZEGO_PUBLIC_CONFIG.appID,
                ZEGO_PUBLIC_CONFIG.testSecret,
                roomID,
                userID,
                userName
            );
        }

        async function leaveRoom(reason, useBeacon) {
            if (!activeRoom || leaveStarted) return;
            leaveStarted = true;
            const payload = JSON.stringify({ roomId: activeRoom.roomId, reason });
            if (useBeacon && navigator.sendBeacon) {
                navigator.sendBeacon(ROOM_LEAVE_URL, new Blob([payload], { type: 'application/json' }));
                return;
            }
            try {
                await fetchJson(ROOM_LEAVE_URL, {
                  method: 'POST',
                  body: payload
                });
            } catch (_error) {}
        }

        function bindLeaveHooks() {
            const exit = () => { void leaveRoom('page_unload', true); };
            window.addEventListener('pagehide', exit, { once: true });
            window.addEventListener('beforeunload', exit, { once: true });
        }

        async function mountRoom() {
            const L = window.ACADBEAT_LOCAL || {};
            window.initializeAcadBeatNav({
                mountId: 'acadbeatNav',
                basePath: '../../',
                active: 'academic',
                authApiBase: '../../Auth/backend/api',
                homeUrl: '../../home.html',
                ownerUrl: '../../owner.html',
                forumUrl: '../../home.html?module=Dialogue',
                technologyUrl: '../../home.html?module=Method',
                studioUrl: '../../Studio/studio.html',
                messageCenterUrl: L.messageCenterDistUrl,
                adminUrl: L.adminDistUrl || window.location.origin + '/admin_page/dist/index.html',
                messageApiUrl: L.messageSummaryApiUrl,
                loginUrl: '../../home.html?login=1',
                redirectAdmins: true,
                showChallengeButton: false
            });

            document.getElementById('tokenModePill').textContent = `Token ${ZEGO_PUBLIC_CONFIG.tokenMode}`;
            const roomId = requestedRoomId();
            if (!roomId) {
                updateStatus('Missing room URL', 'No roomID was provided in the URL.', 'warning');
                updateEmptyState('Invalid room link', 'This room link is missing the roomID parameter.', '<a class="btn primary" href="./index.php">Open Room Lobby</a>');
                return;
            }

            try {
                const [meData, roomData] = await Promise.all([
                    fetchJson(AUTH_ME_URL),
                    fetchJson(ROOM_ACCESS_URL, {
                        method: 'POST',
                        body: JSON.stringify({ roomId })
                    })
                ]);

                const me = meData.user || {};
                const room = roomData.room || {};
                activeRoom = room;

                document.getElementById('stageTitle').textContent = room.topic || 'Video Call Room';
                document.getElementById('stageMeta').textContent = `${room.visibility || 'public'} room | Room ${room.roomId} | Host @${room.owner?.username || 'unknown'}`;
                document.getElementById('topicPill').textContent = room.topic || 'Topic';
                document.getElementById('visibilityPill').textContent = (room.visibility || 'public').toUpperCase();

                const userID = sanitizeRoomValue(`acadbeat_${me.user_id || me.userId || me.username}`);
                const userName = String(me.username || 'AcadBeat User');
                const kitToken = await buildKitToken(room.roomId, userID, userName);
                const zp = ZegoUIKitPrebuilt.create(kitToken);
                const shareLink = room.shareUrl || `${window.location.origin}${window.location.pathname}?roomID=${encodeURIComponent(room.roomId)}`;

                zp.joinRoom({
                    container: document.getElementById('zego-root'),
                    maxUsers: 2,
                    showPreJoinView: true,
                    turnOnCameraWhenJoining: true,
                    turnOnMicrophoneWhenJoining: true,
                    showScreenSharingButton: false,
                    showTextChat: true,
                    showUserList: true,
                    showRoomTimer: true,
                    sharedLinks: [{ name: 'Invite URL', url: shareLink }],
                    scenario: { mode: ZegoUIKitPrebuilt.OneONoneCall },
                    onLeaveRoom: async () => {
                        await leaveRoom('user_left', false);
                        window.close();
                        window.location.href = './index.php';
                    }
                });

                hideEmptyState();
                bindLeaveHooks();
                updateStatus(
                    'Room ready',
                    ZEGO_PUBLIC_CONFIG.tokenMode === 'test'
                        ? 'Room opened successfully. Switch to production tokens before release.'
                        : 'Room opened successfully.',
                    ZEGO_PUBLIC_CONFIG.tokenMode === 'test' ? 'warning' : 'success'
                );
            } catch (error) {
                if (error.status === 401) {
                    updateStatus('Login required', 'Please log in before joining this room.', 'warning');
                    updateEmptyState('Login required', 'Your session is missing or expired.', '<a class="btn primary" href="../../home.html?login=1">Go To Login</a>');
                    return;
                }

                updateStatus('Unable to open room', error.message || 'The room could not be prepared.', 'danger');
                updateEmptyState('Room access failed', error.message || 'The room could not be prepared.', '<a class="btn primary" href="./index.php">Open Room Lobby</a>');
            }
        }

        document.getElementById('closeCallBtn').addEventListener('click', async () => {
            await leaveRoom('return_to_lobby', false);
            window.close();
            window.location.href = './index.php';
        });
        document.getElementById('backToLobbyBtn').addEventListener('click', () => {
            window.location.href = './index.php';
        });
        mountRoom();
    </script>
</body>
</html>
