<?php
declare(strict_types=1);

$rawConfig = [];
$configPath = __DIR__ . '/api/zego-config.php';
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
    'tokenEndpoint' => (string) ($rawConfig['token_endpoint'] ?? '/video-chat-project/api/zego-token.php'),
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
        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }
        .panel-head h2 { margin-bottom: 0; }
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
        .mini-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.72);
            color: var(--ink);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
        }
        .mini-btn.danger {
            color: var(--danger);
            border-color: rgba(177, 92, 92, 0.34);
        }
        .mini-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .room-facts {
            display: grid;
            gap: 10px;
        }
        .room-fact {
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(255,255,255,0.48);
        }
        .room-fact-label {
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.58;
            margin-bottom: 4px;
        }
        .room-fact-value {
            font-size: 0.9rem;
            line-height: 1.5;
            word-break: break-word;
        }
        .invite-box {
            min-height: 68px;
            margin-top: 10px;
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px dashed rgba(51,69,95,0.22);
            background: rgba(255,255,255,0.5);
            font-size: 0.84rem;
            line-height: 1.6;
            word-break: break-all;
        }
        .subtle-note {
            margin-top: 8px;
            font-size: 0.82rem;
            line-height: 1.6;
            opacity: 0.72;
        }
        .member-list {
            display: grid;
            gap: 10px;
        }
        .member-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.52);
        }
        .member-main {
            min-width: 0;
            display: grid;
            gap: 4px;
        }
        .member-name {
            font-size: 0.92rem;
            font-weight: 700;
            line-height: 1.3;
        }
        .member-meta {
            font-size: 0.8rem;
            opacity: 0.72;
            line-height: 1.5;
        }
        .member-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .member-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            padding: 0 9px;
            border-radius: 999px;
            background: rgba(145,171,200,0.18);
            border: 1px solid rgba(145,171,200,0.24);
            font-size: 0.68rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .member-badge.host {
            background: rgba(74,120,96,0.16);
            border-color: rgba(74,120,96,0.24);
        }
        .member-badge.you {
            background: rgba(51,69,95,0.12);
            border-color: rgba(51,69,95,0.18);
        }
        .member-badge.offline {
            background: rgba(51,69,95,0.08);
        }
        .member-badge.joining {
            background: rgba(143,97,63,0.14);
            border-color: rgba(143,97,63,0.22);
        }
        .member-badge.in-room {
            background: rgba(74,120,96,0.16);
            border-color: rgba(74,120,96,0.24);
        }
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
            <div class="eyebrow">Section III - Video Room</div>
            <div class="headline">Video Room</div>
            <p class="copy">This page now resolves access from the durable room model. It checks the requested room, optional invite link, and your current membership before opening ZEGO.</p>

            <div class="pill-row">
                <div class="pill">Room Access</div>
                <div class="pill">Invite Aware</div>
                <div class="pill" id="tokenModePill">Token mode</div>
            </div>

            <div class="status-card" id="connectionStatus">
                <div class="status-title">Status</div>
                <div class="status-body">Checking your login state, requested room, invite link, and room access...</div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Room Snapshot</h2>
                    <button class="mini-btn" id="refreshRoomBtn" type="button">Refresh</button>
                </div>
                <div class="room-facts" id="roomFacts">
                    <div class="room-fact">
                        <div class="room-fact-label">Topic</div>
                        <div class="room-fact-value">Loading room information...</div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Share</h2>
                    <div class="pill" id="sharePill">Room link</div>
                </div>
                <div class="pill-row">
                    <button class="mini-btn" id="createInviteBtn" type="button">Create Invite</button>
                    <button class="mini-btn" id="copyInviteBtn" type="button">Copy Link</button>
                </div>
                <div class="invite-box" id="inviteLinkValue">The room link will appear after access is resolved.</div>
                <div class="subtle-note" id="shareNote">Private invite links are generated on demand by the host.</div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Members</h2>
                    <div class="pill" id="memberCountPill">0 members</div>
                </div>
                <div class="member-list" id="memberList">
                    <div class="member-row">
                        <div class="member-main">
                            <div class="member-name">Loading members...</div>
                            <div class="member-meta">Room membership is resolved after access check completes.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <h2>How this version works</h2>
                <ul>
                    <li>This page resolves room access from <code>api/video-room-detail.php</code> and <code>api/video-room-access.php</code>.</li>
                    <li>Private rooms require a valid invite unless you are already a room member.</li>
                    <li>ZEGO token generation is allowed only after room membership has been confirmed.</li>
                    <li>The sidebar refreshes room details, members, and host actions from the durable room APIs.</li>
                </ul>
            </div>

            <div class="panel">
                <h2>Setup</h2>
                <p>Fill <code>ZEGO_APP_ID</code> and <code>ZEGO_SERVER_SECRET</code> in the server environment. If you stay in <code>test</code> token mode, the page will use the configured test secret directly in the browser.</p>
            </div>

            <div class="action-row">
                <a class="btn primary" href="/video-chat-project/video-match.php">Back To Lobby</a>
                <a class="btn" href="../Academic-Practice/training.html">Back To Training</a>
            </div>
        </aside>

        <main class="stage">
            <div class="shell">
                <div class="shell-header">
                    <div>
                        <h1 id="stageTitle">Video Call Room</h1>
                        <div class="shell-meta" id="stageMeta">Waiting for access check...</div>
                    </div>
                    <button class="btn danger" id="closeCallBtn" type="button">Back To Lobby</button>
                </div>
                <div id="zego-root"></div>
                <div class="empty" id="emptyState">
                    <div class="empty-card">
                        <div class="empty-title" id="emptyTitle">Preparing the video room</div>
                        <div class="empty-body" id="emptyBody">Verifying your account, requested room, and invite access before the video call starts.</div>
                        <div class="empty-actions" id="emptyActions">
                            <a class="btn primary" href="/video-chat-project/video-match.php">Open Video Lobby</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/@zegocloud/zego-uikit-prebuilt/zego-uikit-prebuilt.js"></script>
    <script>
        const ZEGO_PUBLIC_CONFIG = <?php echo json_encode($publicConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const AUTH_ME_URL = '/Auth/backend/api/me.php';
        const ROOM_DETAIL_URL = '/video-chat-project/api/video-room-detail.php';
        const ROOM_ACCESS_URL = '/video-chat-project/api/video-room-access.php';
        const ROOM_PRESENCE_URL = '/video-chat-project/api/video-room-presence.php';
        const ROOM_INVITE_URL = '/video-chat-project/api/video-room-invite.php';
        const ROOM_MEMBER_REMOVE_URL = '/video-chat-project/api/video-room-member-remove.php';
        const ROOM_REENTRY_STORAGE_KEY = 'acadbeat.video_room.latest_reentry';

        let activeCallContext = null;
        let leaveHooksBound = false;
        let currentPresenceStatus = '';
        let offlinePresenceSent = false;
        let roomPollingTimer = null;
        let roomActionPending = '';

        function sanitizeZegoRoomId(value) {
            return String(value || '').replace(/[^A-Za-z0-9_]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '').slice(0, 128);
        }

        function sanitizeRoomPublicId(value) {
            return String(value || '').replace(/[^A-Za-z0-9_-]/g, '').slice(0, 40);
        }

        function sanitizeInviteToken(value) {
            return String(value || '').replace(/[^A-Za-z0-9_-]/g, '').slice(0, 80);
        }

        function requestedRoomPublicId() {
            const params = new URLSearchParams(window.location.search);
            return sanitizeRoomPublicId(params.get('room') || params.get('roomPublicId'));
        }

        function requestedLegacyRoomId() {
            const params = new URLSearchParams(window.location.search);
            return sanitizeZegoRoomId(params.get('roomID') || params.get('zegoRoomId'));
        }

        function requestedInviteToken() {
            const params = new URLSearchParams(window.location.search);
            return sanitizeInviteToken(params.get('invite') || params.get('inviteToken'));
        }

        function roomDetailUrl(roomPublicId, inviteToken, legacyRoomId = '') {
            const url = new URL(ROOM_DETAIL_URL, window.location.href);
            if (roomPublicId) {
                url.searchParams.set('room', roomPublicId);
            } else if (legacyRoomId) {
                url.searchParams.set('roomID', legacyRoomId);
            }
            if (inviteToken) {
                url.searchParams.set('invite', inviteToken);
            }
            return url.toString();
        }

        function buildCanonicalRoomUrl(room) {
            if (!room?.roomPageUrl) {
                return '';
            }
            return new URL(String(room.roomPageUrl), window.location.href).toString();
        }

        function buildShareLink(room, inviteToken) {
            const canonical = buildCanonicalRoomUrl(room);
            if (!canonical) {
                return '';
            }

            const url = new URL(canonical, window.location.href);
            if (room?.visibility === 'private' && inviteToken) {
                url.searchParams.set('invite', inviteToken);
            }
            return url.toString();
        }

        function syncCanonicalRoomUrl(room, inviteToken) {
            const canonical = buildShareLink(room, inviteToken);
            if (!canonical) {
                return;
            }

            const current = new URL(window.location.href);
            if (current.toString() !== canonical) {
                window.history.replaceState({}, '', canonical);
            }
        }

        function readStoredRoomReentry() {
            try {
                const raw = window.localStorage.getItem(ROOM_REENTRY_STORAGE_KEY);
                if (!raw) {
                    return null;
                }

                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') {
                    return null;
                }

                return parsed;
            } catch (error) {
                return null;
            }
        }

        function clearStoredRoomReentry(roomPublicId = '') {
            const normalizedRoom = sanitizeRoomPublicId(roomPublicId);
            const stored = readStoredRoomReentry();
            if (!stored) {
                return;
            }

            if (normalizedRoom && sanitizeRoomPublicId(stored.roomPublicId || '') !== normalizedRoom) {
                return;
            }

            window.localStorage.removeItem(ROOM_REENTRY_STORAGE_KEY);
        }

        function persistStoredRoomReentry(room, overrides = {}) {
            const canonicalUrl = buildCanonicalRoomUrl(room);
            if (!room?.roomPublicId || !canonicalUrl) {
                return;
            }

            const payload = {
                roomPublicId: sanitizeRoomPublicId(room.roomPublicId),
                roomPageUrl: canonicalUrl,
                topicLabel: String(room?.topic?.label || 'Video room'),
                visibility: String(room?.visibility || 'public'),
                shareUrl: String(overrides.shareUrl || ''),
                inviteUrl: String(overrides.inviteUrl || ''),
                inviteToken: sanitizeInviteToken(overrides.inviteToken || ''),
                savedAt: new Date().toISOString()
            };

            try {
                window.localStorage.setItem(ROOM_REENTRY_STORAGE_KEY, JSON.stringify(payload));
            } catch (error) {
                // Ignore storage failures so room access continues normally.
            }
        }

        function syncStoredRoomReentry(room, inviteToken = '', inviteUrl = '') {
            if (!room?.access?.canOpenRoomPage) {
                clearStoredRoomReentry(room?.roomPublicId || '');
                return;
            }

            const shareUrl = inviteUrl || buildShareLink(room, inviteToken);
            persistStoredRoomReentry(room, {
                shareUrl,
                inviteUrl,
                inviteToken
            });
        }

        function resolveReturnedInvite(room, invitePayload = null) {
            const invite = invitePayload && typeof invitePayload === 'object'
                ? invitePayload
                : (room?.activeInvite && typeof room.activeInvite === 'object' ? room.activeInvite : null);

            if (!invite) {
                return {
                    inviteToken: '',
                    inviteUrl: '',
                    inviteExpiresAt: ''
                };
            }

            return {
                inviteToken: sanitizeInviteToken(invite.inviteToken || ''),
                inviteUrl: String(invite.inviteUrl || ''),
                inviteExpiresAt: String(invite.expiresAt || '')
            };
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[char] || char));
        }

        function formatDateTime(value) {
            if (!value) {
                return 'Unavailable';
            }

            const normalized = String(value).replace(' ', 'T');
            const parsed = new Date(normalized);
            if (Number.isNaN(parsed.getTime())) {
                return String(value);
            }

            return new Intl.DateTimeFormat('en-US', {
                dateStyle: 'medium',
                timeStyle: 'short'
            }).format(parsed);
        }

        function formatPresenceLabel(value) {
            const normalized = String(value || '').trim().toLowerCase();
            if (normalized === 'in_room') return 'In Room';
            if (normalized === 'joining') return 'Joining';
            if (normalized === 'offline') return 'Offline';
            return 'Unknown';
        }

        function roomMetaText(room) {
            if (!room) {
                return 'Room metadata is unavailable.';
            }

            const visibilityLabel = room.visibility === 'private' ? 'Private room' : 'Public room';
            const memberCount = Number(room.memberCount || 0);
            const hostUsername = String(room?.host?.username || 'unknown');
            return `${visibilityLabel} | Room ${room.roomPublicId} | Host @${hostUsername} | ${memberCount} member${memberCount === 1 ? '' : 's'}`;
        }

        function primaryShareLink(room = activeCallContext?.room) {
            if (!room) {
                return '';
            }

            if (room.visibility === 'private') {
                if (activeCallContext?.generatedInviteUrl) {
                    return activeCallContext.generatedInviteUrl;
                }

                if (activeCallContext?.inviteToken) {
                    return buildShareLink(room, activeCallContext.inviteToken);
                }
            }

            return buildCanonicalRoomUrl(room);
        }

        function syncStageMetaFromRoom(room) {
            if (!room) {
                return;
            }

            const topicLabel = String(room?.topic?.label || 'Video room');
            document.getElementById('stageTitle').textContent = topicLabel;
            document.getElementById('stageMeta').textContent = roomMetaText(room);
        }

        function setRoomActionPending(actionName) {
            roomActionPending = actionName;
            renderRoomSidebar();
        }

        function clearRoomActionPending() {
            roomActionPending = '';
            renderRoomSidebar();
        }

        function renderRoomSidebar() {
            const room = activeCallContext?.room || null;
            const factsEl = document.getElementById('roomFacts');
            const inviteValueEl = document.getElementById('inviteLinkValue');
            const shareNoteEl = document.getElementById('shareNote');
            const sharePillEl = document.getElementById('sharePill');
            const createInviteBtn = document.getElementById('createInviteBtn');
            const copyInviteBtn = document.getElementById('copyInviteBtn');
            const memberListEl = document.getElementById('memberList');
            const memberCountPillEl = document.getElementById('memberCountPill');
            const refreshRoomBtn = document.getElementById('refreshRoomBtn');

            if (!room) {
                factsEl.innerHTML = `
                    <div class="room-fact">
                        <div class="room-fact-label">Topic</div>
                        <div class="room-fact-value">Room data is still loading.</div>
                    </div>
                `;
                inviteValueEl.textContent = 'The room link will appear after access is resolved.';
                shareNoteEl.textContent = 'Private invite links are generated on demand by the host.';
                sharePillEl.textContent = 'Room link';
                createInviteBtn.disabled = true;
                copyInviteBtn.disabled = true;
                refreshRoomBtn.disabled = false;
                memberCountPillEl.textContent = '0 members';
                memberListEl.innerHTML = `
                    <div class="member-row">
                        <div class="member-main">
                            <div class="member-name">Loading members...</div>
                            <div class="member-meta">Room membership is resolved after access check completes.</div>
                        </div>
                    </div>
                `;
                return;
            }

            syncStageMetaFromRoom(room);

            const visibilityLabel = room.visibility === 'private' ? 'Private' : 'Public';
            const roleLabel = room.currentUserRole ? String(room.currentUserRole) : 'Participant';
            factsEl.innerHTML = `
                <div class="room-fact">
                    <div class="room-fact-label">Topic</div>
                    <div class="room-fact-value">${escapeHtml(room?.topic?.label || 'Video room')}</div>
                </div>
                <div class="room-fact">
                    <div class="room-fact-label">Visibility</div>
                    <div class="room-fact-value">${escapeHtml(visibilityLabel)}</div>
                </div>
                <div class="room-fact">
                    <div class="room-fact-label">Your Role</div>
                    <div class="room-fact-value">${escapeHtml(roleLabel)}</div>
                </div>
                <div class="room-fact">
                    <div class="room-fact-label">Expires At</div>
                    <div class="room-fact-value">${escapeHtml(formatDateTime(room.expiresAt))}</div>
                </div>
            `;

            const isPrivate = room.visibility === 'private';
            const isHost = Boolean(room.access?.isHost);
            const shareLink = primaryShareLink(room);
            const hasShareLink = Boolean(shareLink);
            sharePillEl.textContent = isPrivate ? 'Invite link' : 'Room link';
            inviteValueEl.textContent = hasShareLink
                ? shareLink
                : (isPrivate ? 'Only the host can generate an invite link for this private room.' : 'This room link is unavailable.');

            if (!isPrivate) {
                shareNoteEl.textContent = 'Anyone who can see this public room can join from the room link.';
            } else if (isHost && room.canCreateInvite) {
                shareNoteEl.textContent = activeCallContext?.generatedInviteUrl
                    ? `Private invite active until ${formatDateTime(activeCallContext.inviteExpiresAt || room.expiresAt)}.`
                    : 'Generate an invite link when you want to admit another logged-in user.';
            } else if (hasShareLink) {
                shareNoteEl.textContent = 'This private invite link remains usable while the room stays open.';
            } else {
                shareNoteEl.textContent = 'Only the host can create invite links for private rooms.';
            }

            createInviteBtn.hidden = !isPrivate;
            createInviteBtn.disabled = !isHost || !room.canCreateInvite || roomActionPending === 'invite';
            createInviteBtn.textContent = activeCallContext?.generatedInviteUrl ? 'Refresh Invite' : 'Create Invite';
            copyInviteBtn.disabled = !hasShareLink;
            refreshRoomBtn.disabled = roomActionPending === 'refresh';

            const members = Array.isArray(room.members) ? room.members : [];
            memberCountPillEl.textContent = `${members.length} member${members.length === 1 ? '' : 's'}`;

            if (!members.length) {
                memberListEl.innerHTML = `
                    <div class="member-row">
                        <div class="member-main">
                            <div class="member-name">No member details available</div>
                            <div class="member-meta">The room data did not include any visible members.</div>
                        </div>
                    </div>
                `;
                return;
            }

            memberListEl.innerHTML = members.map((member) => {
                const badges = [];
                if (member.role === 'host') badges.push('<span class="member-badge host">Host</span>');
                if (member.isCurrentUser) badges.push('<span class="member-badge you">You</span>');
                badges.push(`<span class="member-badge ${escapeHtml(String(member.presenceStatus || '').replace('_', '-'))}">${escapeHtml(formatPresenceLabel(member.presenceStatus))}</span>`);

                const canKick = Boolean(room.canManageMembers) && !member.isCurrentUser && member.membershipStatus === 'active';
                return `
                    <div class="member-row">
                        <div class="member-main">
                            <div class="member-name">${escapeHtml(member.displayName || member.username || 'Unknown user')}</div>
                            <div class="member-meta">@${escapeHtml(member.username || 'unknown')} | Joined ${escapeHtml(formatDateTime(member.joinedAt))}</div>
                            <div class="member-badges">${badges.join('')}</div>
                        </div>
                        ${canKick ? `<button class="mini-btn danger" type="button" data-remove-user-id="${Number(member.userId || 0)}" ${roomActionPending === 'remove' ? 'disabled' : ''}>Remove</button>` : ''}
                    </div>
                `;
            }).join('');
        }

        function describeJoinMode(access) {
            const joinMode = String(access?.joinMode || '');
            if (joinMode === 'member_reentry') return 'Re-entry granted.';
            if (joinMode === 'public_join') return 'Public room access granted.';
            if (joinMode === 'invite_required') return 'Invite required.';
            if (joinMode === 'blocked_removed') return 'Access blocked after host removal.';
            if (joinMode === 'blocked_left') return 'Access blocked after leaving the room.';
            return 'Access resolved.';
        }

        function roomBlockedContext(room) {
            const access = room?.access || {};
            const effectiveStatus = String(access.effectiveStatus || room?.effectiveStatus || room?.status || '');
            const titleBase = room?.topic?.label || 'Video room';

            if (effectiveStatus === 'ended') {
                return {
                    allowed: false,
                    title: 'Room ended',
                    body: `"${titleBase}" has already been ended by the host.`,
                    actionsHtml: '<a class="btn primary" href="/video-chat-project/video-match.php">Back To Lobby</a>'
                };
            }

            if (effectiveStatus === 'cancelled') {
                return {
                    allowed: false,
                    title: 'Room cancelled',
                    body: `"${titleBase}" expired without any additional participant joining.`,
                    actionsHtml: '<a class="btn primary" href="/video-chat-project/video-match.php">Back To Lobby</a>'
                };
            }

            if (effectiveStatus === 'expired') {
                return {
                    allowed: false,
                    title: 'Room expired',
                    body: `"${titleBase}" has passed its one-hour lifetime and can no longer be reopened.`,
                    actionsHtml: '<a class="btn primary" href="/video-chat-project/video-match.php">Back To Lobby</a>'
                };
            }

            if (access.joinMode === 'blocked_removed') {
                return {
                    allowed: false,
                    title: 'Access removed',
                    body: 'The host removed you from this room, so it can no longer be reopened from this account.',
                    actionsHtml: '<a class="btn primary" href="/video-chat-project/video-match.php">Back To Lobby</a>'
                };
            }

            if (access.joinMode === 'blocked_left') {
                return {
                    allowed: false,
                    title: 'Room already left',
                    body: 'Your membership in this room is marked as left, so this link can no longer reopen the room.',
                    actionsHtml: '<a class="btn primary" href="/video-chat-project/video-match.php">Back To Lobby</a>'
                };
            }

            if (access.requiresInvite) {
                return {
                    allowed: false,
                    title: 'Invite required',
                    body: 'This is a private room. Open it with a valid invite link or re-enter through your existing room membership.',
                    actionsHtml: '<a class="btn primary" href="/video-chat-project/video-match.php">Back To Lobby</a>'
                };
            }

            return {
                allowed: false,
                title: 'Access denied',
                body: 'This account does not currently have access to the requested room.',
                actionsHtml: '<a class="btn primary" href="/video-chat-project/video-match.php">Back To Lobby</a>'
            };
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
                keepalive: Boolean(options.keepalive),
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

        async function copyText(value) {
            const text = String(value || '').trim();
            if (!text) {
                throw new Error('There is no link to copy yet.');
            }

            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
                return;
            }

            const temp = document.createElement('textarea');
            temp.value = text;
            temp.setAttribute('readonly', 'readonly');
            temp.style.position = 'absolute';
            temp.style.left = '-9999px';
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
        }

        function roomRefreshUrl() {
            const url = new URL(ROOM_DETAIL_URL, window.location.href);
            url.searchParams.set('room', sanitizeRoomPublicId(activeCallContext?.resolvedRoomPublicId || activeCallContext?.room?.roomPublicId || ''));
            return url.toString();
        }

        function startRoomPolling() {
            if (roomPollingTimer !== null) {
                return;
            }

            roomPollingTimer = window.setInterval(() => {
                void refreshRoomDetail(true);
            }, 8000);
        }

        function stopRoomPolling() {
            if (roomPollingTimer === null) {
                return;
            }

            window.clearInterval(roomPollingTimer);
            roomPollingTimer = null;
        }

        function applyBlockedRoomState(room) {
            const blocked = roomBlockedContext(room);
            activeCallContext = {
                ...(activeCallContext || {}),
                allowed: false,
                room
            };
            stopRoomPolling();
            sendOfflinePresence();
            clearStoredRoomReentry(room?.roomPublicId || '');
            renderRoomSidebar();
            updateStatus(blocked.title || 'Access denied', blocked.body || 'This room is no longer available.', 'warning');
            updateEmptyState(blocked.title || 'Room unavailable', blocked.body || 'This room is no longer available.', blocked.actionsHtml);
        }

        async function refreshRoomDetail(silent = false) {
            const roomPublicId = sanitizeRoomPublicId(activeCallContext?.resolvedRoomPublicId || activeCallContext?.room?.roomPublicId || '');
            if (!roomPublicId) {
                return null;
            }

            if (!silent) {
                setRoomActionPending('refresh');
            }

            try {
                const detailPayload = await fetchJson(roomRefreshUrl());
                const room = detailPayload.room || null;
                if (!room) {
                    throw new Error('Room detail payload is missing.');
                }

                const returnedInvite = resolveReturnedInvite(room, detailPayload.activeInvite || null);

                activeCallContext.room = room;
                activeCallContext.resolvedRoomPublicId = room.roomPublicId;
                activeCallContext.roomPublicId = room.roomPublicId;
                activeCallContext.title = String(room?.topic?.label || 'Video room');
                activeCallContext.meta = roomMetaText(room);
                activeCallContext.shareName = room.visibility === 'private' ? 'Invite link' : 'Room link';
                if (returnedInvite.inviteUrl) {
                    activeCallContext.generatedInviteUrl = returnedInvite.inviteUrl;
                }
                if (returnedInvite.inviteToken) {
                    activeCallContext.inviteToken = returnedInvite.inviteToken;
                }
                if (returnedInvite.inviteExpiresAt) {
                    activeCallContext.inviteExpiresAt = returnedInvite.inviteExpiresAt;
                }

                if (!room?.access?.canOpenRoomPage || !room?.zegoRoomId) {
                    applyBlockedRoomState(room);
                    return room;
                }

                syncStoredRoomReentry(room, activeCallContext.inviteToken || '', activeCallContext.generatedInviteUrl || '');
                renderRoomSidebar();
                hideEmptyState();
                if (!silent) {
                    updateStatus('Room refreshed', `Room details were refreshed at ${formatDateTime(new Date().toISOString())}.`, 'success');
                }
                return room;
            } catch (error) {
                if (!silent) {
                    updateStatus('Refresh failed', error.message || 'Unable to refresh room details.', 'danger');
                }
                return null;
            } finally {
                if (!silent) {
                    clearRoomActionPending();
                }
            }
        }

        async function updateRoomPresence(roomPublicId, presenceStatus, keepalive = false) {
            const normalizedRoom = sanitizeRoomPublicId(roomPublicId);
            const normalizedPresence = String(presenceStatus || '').trim().toLowerCase();

            if (!normalizedRoom || !['joining', 'in_room', 'offline'].includes(normalizedPresence)) {
                return null;
            }

            if (!keepalive && currentPresenceStatus === normalizedPresence) {
                return null;
            }

            const payload = await fetchJson(ROOM_PRESENCE_URL, {
                method: 'POST',
                keepalive,
                body: JSON.stringify({
                    room: normalizedRoom,
                    presenceStatus: normalizedPresence
                })
            });
            currentPresenceStatus = normalizedPresence;
            if (normalizedPresence !== 'offline') {
                offlinePresenceSent = false;
            }
            return payload;
        }

        function sendOfflinePresence() {
            const roomPublicId = sanitizeRoomPublicId(activeCallContext?.resolvedRoomPublicId || activeCallContext?.room?.roomPublicId || '');
            if (!roomPublicId || offlinePresenceSent) {
                return;
            }

            offlinePresenceSent = true;
            void updateRoomPresence(roomPublicId, 'offline', true).catch(() => {});
        }

        function bindLeaveHooks() {
            if (leaveHooksBound) {
                return;
            }

            leaveHooksBound = true;
            window.addEventListener('pagehide', stopRoomPolling);
            window.addEventListener('beforeunload', stopRoomPolling);
            window.addEventListener('pagehide', sendOfflinePresence);
            window.addEventListener('beforeunload', sendOfflinePresence);
        }

        async function createInviteLink() {
            const room = activeCallContext?.room || null;
            if (!room?.roomPublicId) {
                return;
            }

            setRoomActionPending('invite');
            try {
                const payload = await fetchJson(ROOM_INVITE_URL, {
                    method: 'POST',
                    body: JSON.stringify({ room: room.roomPublicId })
                });
                const invite = payload.invite || {};
                activeCallContext.generatedInviteUrl = String(invite.inviteUrl || '');
                activeCallContext.inviteToken = String(invite.inviteToken || activeCallContext.inviteToken || '');
                activeCallContext.inviteExpiresAt = String(invite.expiresAt || '');
                syncStoredRoomReentry(room, activeCallContext.inviteToken, activeCallContext.generatedInviteUrl);
                renderRoomSidebar();
                updateStatus('Invite ready', 'A private invite link has been generated for this room.', 'success');
            } catch (error) {
                updateStatus('Invite failed', error.message || 'Unable to create invite link.', 'danger');
            } finally {
                clearRoomActionPending();
            }
        }

        async function removeMember(targetUserId) {
            const room = activeCallContext?.room || null;
            const normalizedUserId = Number(targetUserId || 0);
            if (!room?.roomPublicId || normalizedUserId <= 0) {
                return;
            }

            if (!window.confirm('Remove this member from the room? They will not be able to reopen it from this account.')) {
                return;
            }

            setRoomActionPending('remove');
            try {
                const payload = await fetchJson(ROOM_MEMBER_REMOVE_URL, {
                    method: 'POST',
                    body: JSON.stringify({
                        room: room.roomPublicId,
                        targetUserId: normalizedUserId
                    })
                });
                if (payload.room) {
                    activeCallContext.room = payload.room;
                    activeCallContext.meta = roomMetaText(payload.room);
                }
                renderRoomSidebar();
                updateStatus('Member removed', 'The selected member has been removed from the room.', 'success');
            } catch (error) {
                updateStatus('Remove failed', error.message || 'Unable to remove that member.', 'danger');
            } finally {
                clearRoomActionPending();
            }
        }

        async function resolveCallContext() {
            const roomPublicId = requestedRoomPublicId();
            const legacyRoomId = requestedLegacyRoomId();
            const inviteToken = requestedInviteToken();

            if (!roomPublicId && !legacyRoomId) {
                return {
                    allowed: false,
                    title: 'Room required',
                    body: 'Open this page with a room link such as zego-call.php?room=<room_public_id>.',
                    actionsHtml: '<a class="btn primary" href="/video-chat-project/video-match.php">Back To Lobby</a>'
                };
            }

            const meData = await fetchJson(AUTH_ME_URL);
            const me = meData.user || {};
            let detailPayload = await fetchJson(roomDetailUrl(roomPublicId, inviteToken, legacyRoomId));
            let room = detailPayload.room || null;
            let resolvedBy = detailPayload.resolvedBy || null;
            let returnedInvite = resolveReturnedInvite(room, detailPayload.activeInvite || null);

            if (!room) {
                throw new Error('Room detail payload is missing.');
            }

            if (room?.access?.canJoinDirectly && !room?.access?.canOpenRoomPage) {
                const accessPayload = await fetchJson(ROOM_ACCESS_URL, {
                    method: 'POST',
                    body: JSON.stringify({
                        room: room.roomPublicId,
                        invite: inviteToken
                    })
                });
                room = accessPayload.room || room;
                resolvedBy = accessPayload.resolvedBy || resolvedBy;
                if (!returnedInvite.inviteUrl) {
                    returnedInvite = resolveReturnedInvite(room, accessPayload.activeInvite || null);
                }
            }

            if (!room?.access?.canOpenRoomPage || !room?.zegoRoomId) {
                clearStoredRoomReentry(room?.roomPublicId || '');
                return roomBlockedContext(room);
            }

            syncCanonicalRoomUrl(room, inviteToken);

            const topicLabel = String(room?.topic?.label || 'Video room');
            const effectiveInviteToken = returnedInvite.inviteToken || inviteToken;
            const shareLink = returnedInvite.inviteUrl || buildShareLink(room, effectiveInviteToken);
            syncStoredRoomReentry(room, effectiveInviteToken, returnedInvite.inviteUrl);

            return {
                allowed: true,
                me,
                room,
                roomPublicId: room.roomPublicId,
                resolvedRoomPublicId: room.roomPublicId,
                inviteToken: effectiveInviteToken,
                generatedInviteUrl: returnedInvite.inviteUrl || (room.visibility === 'private' && effectiveInviteToken ? shareLink : ''),
                inviteExpiresAt: returnedInvite.inviteExpiresAt,
                roomID: sanitizeZegoRoomId(room.zegoRoomId),
                title: topicLabel,
                meta: roomMetaText(room),
                shareName: room.visibility === 'private' ? 'Invite link' : 'Room link',
                shareLink,
                readyMessage: `${describeJoinMode(room.access)} Topic: ${topicLabel}.`
            };
        }

        function closeCallAndReturn() {
            sendOfflinePresence();
            window.location.href = '/video-chat-project/video-match.php';
        }

        async function buildKitToken(roomID, userID, userName) {
            if (!ZEGO_PUBLIC_CONFIG.appID) {
                throw new Error('Missing ZEGO_APP_ID. Configure api/zego-config.php first.');
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

            try {
                activeCallContext = await resolveCallContext();
                renderRoomSidebar();
            } catch (error) {
                if (error.status === 401) {
                    updateStatus('Login required', 'You need to log in before opening the video room.', 'warning');
                    updateEmptyState('Login required', 'Your session is missing or expired. Go back, log in, and reopen the video room.', '<a class="btn primary" href="../home.html?login=1">Go To Login</a>');
                    return;
                }

                updateStatus('Connection failed', error.message || 'Unable to load room data.', 'danger');
                updateEmptyState('Unable to verify access', error.message || 'The page could not verify your account and room access.', '<a class="btn primary" href="/video-chat-project/video-match.php">Back To Lobby</a>');
                return;
            }

            const callContext = activeCallContext;

            if (!callContext.allowed || !callContext.roomID) {
                renderRoomSidebar();
                updateStatus(callContext.title || 'Access denied', callContext.body || 'No active room is available for this account.', 'warning');
                updateEmptyState(callContext.title || 'No active video room', callContext.body || 'This page could not resolve a valid room for your account.', callContext.actionsHtml);
                return;
            }

            const roomID = callContext.roomID;
            const me = callContext.me || {};
            const userID = sanitizeZegoRoomId(`acadbeat_${me.user_id || me.userId || me.username}`);
            const userName = String(me.username || 'AcadBeat User');

            renderRoomSidebar();

            try {
                await updateRoomPresence(callContext.resolvedRoomPublicId, 'joining');
                const kitToken = await buildKitToken(roomID, userID, userName);
                const zp = ZegoUIKitPrebuilt.create(kitToken);
                const root = document.getElementById('zego-root');
                const shareLink = callContext.shareLink || buildCanonicalRoomUrl(callContext.room);
                const joinConfig = {
                    container: root,
                    maxUsers: Math.max(6, Number(callContext.room?.memberCount || 0) || 0),
                    showPreJoinView: true,
                    turnOnCameraWhenJoining: true,
                    turnOnMicrophoneWhenJoining: true,
                    showScreenSharingButton: false,
                    showTextChat: true,
                    showUserList: true,
                    showRoomTimer: true,
                    sharedLinks: [{ name: callContext.shareName || 'Room link', url: shareLink }],
                    scenario: { mode: ZegoUIKitPrebuilt.GroupCall },
                    onLeaveRoom: () => {
                        sendOfflinePresence();
                        window.location.href = '/video-chat-project/video-match.php';
                    }
                };

                if (ZEGO_PUBLIC_CONFIG.brandingLogoUrl) {
                    joinConfig.branding = { logoURL: ZEGO_PUBLIC_CONFIG.brandingLogoUrl };
                }

                zp.joinRoom(joinConfig);
                window.setTimeout(() => {
                    void updateRoomPresence(callContext.resolvedRoomPublicId, 'in_room').catch(() => {});
                }, 1200);
                bindLeaveHooks();
                startRoomPolling();
                hideEmptyState();
                updateStatus(
                    'Room ready',
                    ZEGO_PUBLIC_CONFIG.tokenMode === 'test'
                        ? `${callContext.readyMessage} Switch to production token generation before going live.`
                        : callContext.readyMessage,
                    ZEGO_PUBLIC_CONFIG.tokenMode === 'test' ? 'warning' : 'success'
                );
            } catch (error) {
                stopRoomPolling();
                void updateRoomPresence(callContext.resolvedRoomPublicId, 'offline').catch(() => {});
                updateStatus('ZEGO setup error', error.message || 'Unable to start the call room.', 'danger');
                updateEmptyState('ZEGO configuration required', error.message || 'The page could not build a valid ZEGO kit token.', '<a class="btn primary" href="/video-chat-project/video-match.php">Back To Lobby</a><a class="btn" href="../Academic-Practice/training.html">Back To Training</a>');
            }
        }

        document.getElementById('closeCallBtn').addEventListener('click', closeCallAndReturn);
        document.getElementById('refreshRoomBtn').addEventListener('click', () => {
            void refreshRoomDetail(false);
        });
        document.getElementById('createInviteBtn').addEventListener('click', () => {
            void createInviteLink();
        });
        document.getElementById('copyInviteBtn').addEventListener('click', async () => {
            try {
                await copyText(primaryShareLink());
                updateStatus('Link copied', 'The current room link was copied to your clipboard.', 'success');
            } catch (error) {
                updateStatus('Copy failed', error.message || 'Unable to copy the current room link.', 'danger');
            }
        });
        document.getElementById('memberList').addEventListener('click', (event) => {
            const target = event.target instanceof HTMLElement ? event.target.closest('[data-remove-user-id]') : null;
            if (!target) {
                return;
            }

            void removeMember(Number(target.getAttribute('data-remove-user-id') || 0));
        });
        mountVideoRoom();
    </script>
</body>
</html>
