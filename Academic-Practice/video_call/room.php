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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
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
            overflow: hidden;
            background:
                radial-gradient(circle at top left, rgba(145,171,200,0.28), transparent 30%),
                radial-gradient(circle at bottom right, rgba(51,69,95,0.10), transparent 34%),
                var(--bg);
        }
        .room-page-shell {
            width: min(calc(100% - 32px), 960px);
            margin: 92px auto 0;
            height: calc(100vh - 108px);
        }
        .room-stack {
            display: grid;
            gap: 12px;
            height: 100%;
            grid-template-rows: auto minmax(0, 1fr);
        }
        .overview,
        .shell {
            border: 1px solid var(--line);
            border-radius: 20px;
            background: var(--panel);
            backdrop-filter: blur(14px);
            box-shadow: 0 16px 38px rgba(51, 69, 95, 0.07);
        }
        .overview {
            padding: 14px 16px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: start;
        }
        .overview-copy {
            min-width: 0;
        }
        .overview-eyebrow {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .overview-title {
            margin-top: 6px;
            font-size: 1.08rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .overview-sub {
            margin-top: 4px;
            color: var(--muted);
            line-height: 1.6;
            font-size: 0.86rem;
        }
        .pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
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
            padding: 8px 12px;
            background: rgba(255,255,255,0.88);
        }
        .btn {
            min-height: 38px;
            padding: 0 14px;
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
        .shell {
            height: 100%;
            min-height: 620px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .shell-header {
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
            background: rgba(255,255,255,0.82);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn[hidden] { display: none !important; }
        .header-main {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .back-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.9);
            color: var(--ink);
            font-size: 1.05rem;
            cursor: pointer;
            flex: 0 0 auto;
        }
        .shell-header h1 {
            font-size: 0.88rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .shell-meta {
            margin-top: 2px;
            color: var(--muted);
            font-size: 0.78rem;
        }
        #zego-root {
            width: 100%;
            height: 100%;
            flex: 1 1 auto;
            min-height: 0;
            background: #f7f4ef;
            overflow: hidden;
        }
        .empty {
            position: absolute;
            inset: 14px;
            border-radius: 16px;
            border: 1px dashed rgba(51,69,95,0.22);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 16px;
            background: linear-gradient(180deg, rgba(255,255,255,0.94), rgba(241,236,230,0.92));
        }
        .empty[hidden] { display: none !important; }
        .empty-card { max-width: 440px; }
        .empty-title {
            font-size: 1.02rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .empty-body {
            color: var(--muted);
            line-height: 1.6;
            font-size: 0.88rem;
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
            height: 100%;
            overflow: hidden;
        }
        .room-manage-modal {
            position: fixed;
            inset: 0;
            z-index: 1200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .room-manage-modal[hidden] { display: none !important; }
        .room-manage-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(28, 35, 49, 0.34);
            backdrop-filter: blur(5px);
        }
        .room-manage-card {
            position: relative;
            width: min(620px, 100%);
            border-radius: 24px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.96);
            box-shadow: 0 24px 56px rgba(51,69,95,0.18);
            padding: 20px;
            display: grid;
            gap: 16px;
        }
        .room-manage-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .room-manage-title {
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        .room-manage-sub {
            margin-top: 4px;
            color: var(--muted);
            font-size: 0.86rem;
            line-height: 1.5;
        }
        .room-manage-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.95);
            cursor: pointer;
            color: var(--ink);
            font-size: 1rem;
        }
        .room-manage-grid {
            display: grid;
            gap: 14px;
        }
        .room-manage-section {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: rgba(255,255,255,0.88);
            padding: 14px;
            display: grid;
            gap: 12px;
        }
        .room-manage-label {
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .room-visibility-row {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }
        .room-visibility-option {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--ink);
            font-weight: 700;
        }
        .room-visibility-option input {
            accent-color: var(--accent);
        }
        .room-manage-field {
            position: relative;
            display: grid;
            gap: 10px;
        }
        .room-manage-input {
            width: 100%;
            min-height: 44px;
            padding: 0 14px;
            border-radius: 16px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            font-size: 0.95rem;
        }
        .room-manage-suggestions {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            width: min(340px, 100%);
            z-index: 8;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.98);
            box-shadow: 0 18px 42px rgba(51,69,95,0.16);
            padding: 8px;
            display: grid;
            gap: 6px;
            max-height: 220px;
            overflow: auto;
        }
        .room-manage-suggestions[hidden] { display: none !important; }
        .room-manage-option {
            border: 0;
            border-radius: 14px;
            background: rgba(247,244,239,0.92);
            padding: 10px 12px;
            display: grid;
            gap: 3px;
            text-align: left;
            cursor: pointer;
            color: var(--ink);
        }
        .room-manage-option span {
            color: var(--muted);
            font-size: 0.82rem;
        }
        .room-manage-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .room-member-list {
            display: grid;
            gap: 8px;
        }
        .room-member-card {
            border-radius: 14px;
            border: 1px solid var(--line);
            padding: 10px 12px;
            background: rgba(247,244,239,0.92);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .room-member-card strong {
            display: block;
            font-size: 0.9rem;
        }
        .room-member-card span {
            display: block;
            color: var(--muted);
            font-size: 0.8rem;
            margin-top: 2px;
        }
        .room-member-status {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .room-manage-feedback {
            min-height: 18px;
            color: var(--muted);
            font-size: 0.84rem;
        }
        @media (max-width: 1024px) {
            .overview {
                grid-template-columns: 1fr;
            }
            .pill-row {
                justify-content: flex-start;
            }
            body {
                overflow-y: auto;
            }
            .room-page-shell {
                height: auto;
                margin-bottom: 20px;
            }
            .room-stack {
                height: auto;
                grid-template-rows: none;
            }
            .shell {
                min-height: 68vh;
                height: auto;
            }
            .shell-header {
                align-items: flex-start;
                flex-direction: column;
            }
            .header-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <div id="acadbeatNav"></div>
    <main class="room-page-shell">
    <div class="room-stack">
        <section class="overview">
            <div class="overview-copy">
                <div class="overview-eyebrow">Academic Practice</div>
                <div class="overview-title">Video Call Room</div>
                <div class="overview-sub">Topic-based room access with invite URLs. Use the lobby to create, share, or reopen rooms.</div>
            </div>
            <div class="pill-row">
                <div class="pill" id="topicPill">Topic</div>
                <div class="pill" id="visibilityPill">Visibility</div>
                <div class="pill" id="tokenModePill">Token mode</div>
            </div>
        </section>

        <section class="shell-wrap">
            <div class="shell">
                <div class="shell-header">
                    <div class="header-main">
                        <button class="back-btn" id="backToLobbyBtn" type="button" aria-label="Back">←</button>
                        <div>
                            <h1 id="stageTitle">Video Room</h1>
                            <div class="shell-meta" id="stageMeta">Preparing the room...</div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button class="btn" id="manageRoomBtn" type="button" hidden>Manage Room</button>
                        <button class="btn" id="refreshRoomBtn" type="button">Refresh</button>
                        <button class="btn danger" id="closeCallBtn" type="button">Leave Room</button>
                    </div>
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
        </section>
    </div>
    </main>

    <div class="room-manage-modal" id="roomManageModal" hidden>
        <div class="room-manage-modal__backdrop" id="roomManageBackdrop"></div>
        <div class="room-manage-card">
            <div class="room-manage-head">
                <div>
                    <div class="room-manage-title">Manage Room</div>
                    <div class="room-manage-sub">Invite more people after creation and switch the room between public and private.</div>
                </div>
                <button class="room-manage-close" id="roomManageClose" type="button" aria-label="Close">✕</button>
            </div>
            <div class="room-manage-grid">
                <section class="room-manage-section">
                    <div class="room-manage-label">Visibility</div>
                    <div class="room-visibility-row">
                        <label class="room-visibility-option"><input type="radio" name="roomVisibility" value="public"> Public</label>
                        <label class="room-visibility-option"><input type="radio" name="roomVisibility" value="private"> Private</label>
                    </div>
                    <div class="room-manage-actions">
                        <button class="btn primary" id="saveVisibilityBtn" type="button">Save Visibility</button>
                    </div>
                </section>
                <section class="room-manage-section">
                    <div class="room-manage-label">Invite Member</div>
                    <div class="room-manage-field">
                        <input class="room-manage-input" id="roomInviteInput" type="text" placeholder="Search a username">
                        <div class="room-manage-suggestions" id="roomInviteSuggestions" hidden></div>
                    </div>
                    <div class="room-manage-actions">
                        <button class="btn primary" id="sendInviteBtn" type="button">Send Invite</button>
                    </div>
                </section>
                <section class="room-manage-section">
                    <div class="room-manage-label">Members</div>
                    <div class="room-member-list" id="roomMemberList"></div>
                </section>
            </div>
            <div class="room-manage-feedback" id="roomManageFeedback"></div>
        </div>
    </div>

    <script src="../../shared-nav.js"></script>
    <script src="https://unpkg.com/@zegocloud/zego-uikit-prebuilt/zego-uikit-prebuilt.js"></script>
    <script>
        const ZEGO_PUBLIC_CONFIG = <?php echo json_encode($publicConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const ROOM_ACCESS_URL = '../api/video/video-call-access.php';
        const ROOM_LEAVE_URL = '../api/video/video-call-leave.php';
        const ROOM_MANAGE_URL = '../api/video/video-call-manage.php';
        const AUTH_ME_URL = '../../Auth/backend/api/me.php';
        const CHAT_USERS_URL = '../../forum-project/api/chat-users.php';

        let activeRoom = null;
        let leaveStarted = false;
        let inviteAbortController = null;

        function sanitizeRoomValue(value) {
            return String(value || '').replace(/[^A-Za-z0-9_]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '');
        }

        function requestedRoomId() {
            return sanitizeRoomValue(new URLSearchParams(window.location.search).get('roomID'));
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

        function setManageFeedback(message, isError) {
            const el = document.getElementById('roomManageFeedback');
            if (!el) return;
            el.textContent = message || '';
            el.style.color = isError ? '#b26060' : 'rgba(51,69,95,0.72)';
        }

        function toggleManageModal(open) {
            const modal = document.getElementById('roomManageModal');
            if (!modal) return;
            modal.hidden = !open;
            if (open) {
                syncManageModal();
            }
        }

        function renderMemberList() {
            const list = document.getElementById('roomMemberList');
            if (!list || !activeRoom) return;
            const accepted = Array.isArray(activeRoom.members) ? activeRoom.members : [];
            const pending = Array.isArray(activeRoom.pendingMembers) ? activeRoom.pendingMembers : [];
            const rows = accepted.map(member => ({ ...member, label: member.role === 'owner' ? 'Owner' : 'Joined' }))
                .concat(pending.map(member => ({ ...member, label: 'Pending' })));

            list.innerHTML = rows.map(member => `
                <div class="room-member-card">
                    <div>
                        <strong>@${member.username || 'unknown'}</strong>
                        <span>${member.displayName || member.username || 'User'}</span>
                    </div>
                    <div class="room-member-status">${member.label}</div>
                </div>
            `).join('') || '<div class="room-member-card"><div><strong>No members yet</strong><span>Add someone to this room.</span></div></div>';
        }

        function syncManageModal() {
            if (!activeRoom) return;
            const visibility = String(activeRoom.visibility || 'public');
            document.querySelectorAll('input[name="roomVisibility"]').forEach((input) => {
                input.checked = input.value === visibility;
            });
            renderMemberList();
        }

        async function searchInviteUsers(query) {
            const suggestionsEl = document.getElementById('roomInviteSuggestions');
            if (!suggestionsEl) return;
            const keyword = String(query || '').trim();
            if (!keyword) {
                suggestionsEl.innerHTML = '';
                suggestionsEl.hidden = true;
                return;
            }
            if (inviteAbortController) {
                inviteAbortController.abort();
            }
            inviteAbortController = new AbortController();
            try {
                const response = await fetch(`${CHAT_USERS_URL}?q=${encodeURIComponent(keyword)}`, {
                    credentials: 'include',
                    signal: inviteAbortController.signal
                });
                const data = await response.json().catch(() => ({}));
                const users = Array.isArray(data.users) ? data.users : [];
                suggestionsEl.innerHTML = users.map((user) => `
                    <button type="button" class="room-manage-option" data-username="${String(user.username || '').replace(/"/g, '&quot;')}">
                        <strong>@${user.username || 'unknown'}</strong>
                        <span>${user.email || ''}</span>
                    </button>
                `).join('');
                suggestionsEl.hidden = users.length === 0;
            } catch (error) {
                if (error.name === 'AbortError') return;
                suggestionsEl.innerHTML = '';
                suggestionsEl.hidden = true;
            }
        }

        async function manageRoom(action, payload) {
            if (!activeRoom) return;
            const data = await fetchJson(ROOM_MANAGE_URL, {
                method: 'POST',
                body: JSON.stringify({
                    roomId: activeRoom.roomId,
                    action,
                    ...payload
                })
            });
            activeRoom = data.room || activeRoom;
            document.getElementById('stageMeta').textContent = `${activeRoom.visibility || 'public'} room | Room ${activeRoom.roomId} | Host @${activeRoom.owner?.username || 'unknown'}`;
            document.getElementById('visibilityPill').textContent = (activeRoom.visibility || 'public').toUpperCase();
            syncManageModal();
        }

        async function ensureZegoSdkReady() {
            if (window.ZegoUIKitPrebuilt) {
                return window.ZegoUIKitPrebuilt;
            }

            await new Promise((resolve, reject) => {
                const existing = document.querySelector('script[data-zego-sdk="1"]');
                if (existing) {
                    existing.addEventListener('load', () => resolve(), { once: true });
                    existing.addEventListener('error', () => reject(new Error('Failed to load ZEGO SDK.')), { once: true });
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://unpkg.com/@zegocloud/zego-uikit-prebuilt/zego-uikit-prebuilt.js';
                script.async = true;
                script.dataset.zegoSdk = '1';
                script.onload = () => resolve();
                script.onerror = () => reject(new Error('Failed to load ZEGO SDK.'));
                document.head.appendChild(script);
            });

            if (!window.ZegoUIKitPrebuilt) {
                throw new Error('ZEGO SDK is unavailable.');
            }

            return window.ZegoUIKitPrebuilt;
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
            const zegoSdk = await ensureZegoSdkReady();
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
                return zegoSdk.generateKitTokenForProduction(
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

            return zegoSdk.generateKitTokenForTest(
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
                document.getElementById('manageRoomBtn').hidden = !room.currentUser?.canManage;

                const userID = sanitizeRoomValue(`acadbeat_${me.user_id || me.userId || me.username}`);
                const userName = String(me.username || 'AcadBeat User');
                const ZegoUIKitPrebuilt = await ensureZegoSdkReady();
                const kitToken = await buildKitToken(room.roomId, userID, userName);
                const zp = ZegoUIKitPrebuilt.create(kitToken);
                const shareLink = room.shareUrl || `${window.location.origin}${window.location.pathname}?roomID=${encodeURIComponent(room.roomId)}`;
                const groupMode = Number(room.capacity || 2) > 2 || Number(room.memberCount || 0) > 2;

                zp.joinRoom({
                    container: document.getElementById('zego-root'),
                    maxUsers: Number(room.capacity || 2),
                    showPreJoinView: true,
                    turnOnCameraWhenJoining: true,
                    turnOnMicrophoneWhenJoining: true,
                    showScreenSharingButton: false,
                    showTextChat: true,
                    showUserList: true,
                    showRoomTimer: true,
                    sharedLinks: [{ name: 'Invite URL', url: shareLink }],
                    scenario: { mode: groupMode ? ZegoUIKitPrebuilt.GroupCall : ZegoUIKitPrebuilt.OneONoneCall },
                    onLeaveRoom: async () => {
                        await leaveRoom('user_left', false);
                        window.close();
                        window.location.href = './index.php';
                    }
                });

                hideEmptyState();
                bindLeaveHooks();
            } catch (error) {
                if (error.status === 401) {
                    updateEmptyState('Login required', 'Your session is missing or expired.', '<a class="btn primary" href="../../home.html?login=1">Go To Login</a>');
                    return;
                }

                updateEmptyState('Room access failed', error.message || 'The room could not be prepared.', '<a class="btn primary" href="./index.php">Open Room Lobby</a>');
            }
        }

        document.getElementById('closeCallBtn').addEventListener('click', async () => {
            await leaveRoom('return_to_lobby', false);
            window.close();
            window.location.href = './index.php';
        });
        document.getElementById('refreshRoomBtn').addEventListener('click', () => {
            window.location.reload();
        });
        document.getElementById('backToLobbyBtn').addEventListener('click', () => {
            window.location.href = './index.php';
        });
        document.getElementById('manageRoomBtn').addEventListener('click', () => {
            toggleManageModal(true);
        });
        document.getElementById('roomManageClose').addEventListener('click', () => toggleManageModal(false));
        document.getElementById('roomManageBackdrop').addEventListener('click', () => toggleManageModal(false));
        document.getElementById('roomInviteInput').addEventListener('input', (event) => {
            setManageFeedback('', false);
            void searchInviteUsers(event.target.value || '');
        });
        document.getElementById('roomInviteInput').addEventListener('blur', () => {
            window.setTimeout(() => {
                document.getElementById('roomInviteSuggestions').hidden = true;
            }, 120);
        });
        document.getElementById('roomInviteSuggestions').addEventListener('click', (event) => {
            const option = event.target.closest('.room-manage-option');
            if (!option) return;
            document.getElementById('roomInviteInput').value = option.dataset.username || '';
            document.getElementById('roomInviteSuggestions').hidden = true;
        });
        document.getElementById('saveVisibilityBtn').addEventListener('click', async () => {
            try {
                const visibility = document.querySelector('input[name="roomVisibility"]:checked')?.value || 'public';
                await manageRoom('update_visibility', { visibility });
                setManageFeedback('Visibility updated.', false);
            } catch (error) {
                setManageFeedback(error.message || 'Failed to update visibility.', true);
            }
        });
        document.getElementById('sendInviteBtn').addEventListener('click', async () => {
            const inviteUsername = document.getElementById('roomInviteInput').value.trim();
            if (!inviteUsername) {
                setManageFeedback('Choose a username first.', true);
                return;
            }
            try {
                await manageRoom('invite_member', { inviteUsername });
                document.getElementById('roomInviteInput').value = '';
                document.getElementById('roomInviteSuggestions').hidden = true;
                setManageFeedback('Invite sent.', false);
            } catch (error) {
                setManageFeedback(error.message || 'Failed to send invite.', true);
            }
        });
        mountRoom();
    </script>
</body>
</html>
