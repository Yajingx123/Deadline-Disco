<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Academic Video Call | AcadBeat</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,500;1,500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../shared-nav.css">
  <script src="../../shared/acadbeat-local-config.js"></script>
  <link rel="stylesheet" href="../practice-style.css">
  <style>html.acadbeat-role-guard body{visibility:hidden}</style>
  <script>
    document.documentElement.classList.add('acadbeat-role-guard');
    (async function guardAdminVideoCall() {
      try {
        const response = await fetch('../../Auth/backend/api/me.php', { credentials: 'include' });
        const data = await response.json().catch(() => ({ status: 'error' }));
        if (data.status === 'success' && data.user && String(data.user.role || '').toLowerCase() === 'admin') {
          const admin = (window.ACADBEAT_LOCAL && window.ACADBEAT_LOCAL.adminDistUrl) || window.location.origin + '/admin_page/dist/index.html';
          window.location.replace(admin);
          return;
        }
      } catch (_err) {}
      document.documentElement.classList.remove('acadbeat-role-guard');
    })();
  </script>
</head>
<body data-page="voice-room-home">
  <div id="acadbeatNav"></div>

  <main class="module-content page-shell voice-room-shell">
    <section class="voice-room-hero-wrap">
      <button class="back-btn back-fab voice-room-back" data-back-target="../training.html" aria-label="Go back" title="Back" type="button">&#8592;</button>
      <section class="voice-room-hero">
        <h1>Video Call Room</h1>
        <span class="voice-room-underline" aria-hidden="true"></span>
        <p>Browse public rooms, create your own topic room, and invite someone directly with a URL.</p>
      </section>
    </section>

    <section class="section-block voice-room-board">
      <div class="voice-room-tools">
        <div class="voice-room-tools-copy">
          <span class="voice-room-tools-eyebrow">Live Room Finder</span>
          <h2>Choose A Topic To Practice Together</h2>
        </div>
        <div class="voice-room-search-wrap">
          <input id="voiceRoomSearch" class="voice-room-search" type="search" placeholder="Search topic or host..." aria-label="Search room topic">
        </div>
      </div>

      <div class="voice-room-filter-bar">
        <div class="voice-room-filter-group">
          <span class="voice-room-filter-label">Visibility</span>
          <div class="voice-room-chip-row" data-filter-group="visibility">
            <button type="button" class="voice-room-chip is-active" data-value="all">All</button>
            <button type="button" class="voice-room-chip" data-value="public">Public</button>
            <button type="button" class="voice-room-chip" data-value="private">Private</button>
          </div>
        </div>

        <div class="voice-room-filter-group">
          <span class="voice-room-filter-label">Availability</span>
          <div class="voice-room-chip-row" data-filter-group="availability">
            <button type="button" class="voice-room-chip is-active" data-value="all">All</button>
            <button type="button" class="voice-room-chip" data-value="available">Available</button>
            <button type="button" class="voice-room-chip" data-value="full">Full</button>
          </div>
        </div>

        <div class="voice-room-filter-group">
          <span class="voice-room-filter-label">Action</span>
          <div class="voice-room-chip-row">
            <button id="voiceRoomRefreshBtn" type="button" class="voice-room-chip">Refresh</button>
            <button id="voiceRoomOpenCreateBtn" type="button" class="voice-room-chip">Create Room</button>
          </div>
        </div>
      </div>

      <div class="voice-room-board-meta">
        <p id="voiceRoomResultMeta">Loading rooms...</p>
      </div>

      <div id="voiceRoomGrid" class="voice-room-grid"></div>
      <div id="voiceRoomEmpty" class="voice-room-empty hidden">
        <strong>No rooms match your filters.</strong>
        <span>Try another keyword or create your own room below.</span>
      </div>
    </section>

    <button id="voiceRoomCreateBtn" class="voice-room-create-btn" type="button">Create your room</button>
  </main>

  <div id="voiceRoomCreateModal" class="voice-room-modal hidden" role="dialog" aria-modal="true" aria-labelledby="voiceRoomCreateTitle">
    <div class="voice-room-modal-card voice-room-modal-card--form">
      <h2 id="voiceRoomCreateTitle">Create your room</h2>
      <p>Set the topic, choose public or private access, and optionally send the invite URL to a username through direct message.</p>
      <form id="voiceRoomCreateForm" class="voice-room-create-form">
        <label class="voice-room-form-field">
          <span>Topic</span>
          <input id="voiceRoomTopicInput" type="text" maxlength="120" placeholder="Enter your speaking topic">
        </label>

        <div class="voice-room-form-field">
          <span>Visibility</span>
          <div class="voice-room-visibility-choice" id="voiceRoomVisibilityChoice">
            <label class="voice-room-radio">
              <input type="radio" name="visibility" value="public" checked>
              <span class="voice-room-radio__dot"></span>
              <span class="voice-room-radio__label">Public</span>
            </label>
            <label class="voice-room-radio">
              <input type="radio" name="visibility" value="private">
              <span class="voice-room-radio__dot"></span>
              <span class="voice-room-radio__label">Private</span>
            </label>
          </div>
        </div>

        <label class="voice-room-form-field voice-room-form-field--invite">
          <span>Invite username</span>
          <input id="voiceRoomInviteInput" type="text" maxlength="50" placeholder="Optional for public, required only if you want to DM someone">
          <div id="voiceRoomInviteSuggestions" class="voice-room-invite-suggestions hidden"></div>
        </label>

        <div class="voice-room-form-field">
          <span>What happens next</span>
          <input type="text" value="Open the room in a new browser tab after creation" disabled>
        </div>

        <div class="voice-room-modal-actions">
          <button id="voiceRoomCreateCancelBtn" class="voice-room-modal-btn voice-room-modal-btn--ghost" type="button">Cancel</button>
          <button class="voice-room-modal-btn" type="submit">Create</button>
        </div>
      </form>
    </div>
  </div>

  <div id="voiceRoomToast" class="voice-room-toast hidden" role="status" aria-live="polite"></div>

  <style>
    #voiceRoomOpenCreateBtn {
      background: rgba(58, 78, 107, 0.92);
      border-color: rgba(58, 78, 107, 0.92);
      color: #f6f1ea;
    }

    #voiceRoomRefreshBtn {
      background: rgba(255, 255, 255, 0.74);
    }

    .voice-room-card p.voice-room-card-host {
      margin-top: 6px;
      font-size: 0.88rem;
    }

    .voice-room-card p.voice-room-card-note {
      margin-top: 12px;
      min-height: 3.2em;
    }

    .voice-room-tag--private {
      background: rgba(221, 215, 238, 0.72);
      color: #56487b;
      border-color: rgba(115, 95, 168, 0.18);
    }

    .voice-room-tag--public {
      background: rgba(200, 231, 212, 0.7);
      color: #33583d;
      border-color: rgba(95, 154, 114, 0.22);
    }

    .voice-room-toast.is-error {
      background: rgba(145, 74, 74, 0.94);
    }

    .voice-room-toast.is-success {
      background: rgba(58, 78, 107, 0.92);
    }

    .voice-room-modal-btn {
      min-height: 42px;
      padding: 0 16px;
      border: 0;
      border-radius: 999px;
      background: rgba(58, 78, 107, 0.92);
      color: #f6f1ea;
      font-size: 0.86rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      cursor: pointer;
    }

    .voice-room-form-field input:disabled {
      opacity: 0.82;
      cursor: default;
      background: rgba(244, 241, 236, 0.9);
    }

    body[data-page="voice-room-home"] .page-shell {
      width: min(calc(100% - 40px), 1320px);
      margin-top: 96px;
    }

    .voice-room-shell {
      padding-bottom: 144px;
    }

    .voice-room-hero-wrap {
      display: grid;
      grid-template-columns: 56px minmax(0, 1fr) 56px;
      align-items: center;
      margin-bottom: 10px;
      min-height: 96px;
    }

    .voice-room-back {
      position: static;
      transform: none;
      grid-column: 1;
    }

    .voice-room-hero {
      grid-column: 2;
      align-items: center;
      text-align: center;
      max-width: none;
      margin: 0;
      padding-left: 0;
    }

    .voice-room-hero p {
      max-width: 760px;
      margin-inline: auto;
      text-wrap: balance;
    }

    .voice-room-board {
      padding: 28px;
    }

    .voice-room-tools {
      grid-template-columns: minmax(0, 1.05fr) minmax(300px, 0.7fr);
      gap: 20px;
    }

    .voice-room-filter-bar {
      grid-template-columns: 1.1fr 1.1fr 0.9fr;
      gap: 16px;
    }

    .voice-room-grid {
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 18px;
      align-items: stretch;
    }

    .voice-room-card {
      min-height: 272px;
      padding: 20px;
    }

    .voice-room-card h3 {
      font-size: 1.5rem;
    }

    .voice-room-create-btn {
      right: 36px;
      bottom: 34px;
      min-height: 62px;
      padding: 0 28px;
    }

    .voice-room-modal-card--form {
      width: min(620px, calc(100vw - 32px));
      max-width: none;
      padding: 28px 28px;
    }

    .voice-room-modal-card--form h2 {
      font-family: "Inter", sans-serif;
      font-size: 2.1rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      text-align: center;
    }

    .voice-room-modal-card--form p {
      font-family: "Inter", sans-serif;
      text-align: center;
    }

    .voice-room-create-form {
      gap: 16px;
    }

    .voice-room-form-field {
      align-items: stretch;
    }

    .voice-room-form-field > span {
      width: 100%;
      text-align: left;
      padding-right: 0;
      padding-left: 2px;
      font-family: "Inter", sans-serif;
      font-size: 0.9rem;
      font-weight: 800;
      letter-spacing: 0.14em;
    }

    .voice-room-visibility-choice {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 16px;
      min-height: 48px;
      padding: 0 6px;
    }

    .voice-room-radio {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      color: var(--secondary-color);
      font-family: "Inter", sans-serif;
      font-weight: 700;
    }

    .voice-room-radio input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .voice-room-radio__dot {
      width: 16px;
      height: 16px;
      border-radius: 50%;
      border: 2px solid rgba(58, 78, 107, 0.34);
      background: #fff;
      box-shadow: inset 0 0 0 3px #fff;
      transition: all 0.16s ease;
    }

    .voice-room-radio input:checked + .voice-room-radio__dot {
      border-color: rgba(58, 78, 107, 0.92);
      background: rgba(58, 78, 107, 0.92);
    }

    .voice-room-radio__label {
      font-size: 1rem;
    }

    .voice-room-form-field--invite {
      position: relative;
    }

    .voice-room-invite-suggestions {
      position: absolute;
      left: 0;
      right: 0;
      top: calc(100% + 6px);
      z-index: 20;
      display: grid;
      gap: 8px;
      max-height: 220px;
      overflow: auto;
      padding: 10px;
      border: 1px solid rgba(58, 78, 107, 0.12);
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.92);
      box-shadow: 0 16px 28px rgba(58, 78, 107, 0.08);
    }

    .voice-room-invite-suggestions.hidden {
      display: none !important;
    }

    .voice-room-invite-option {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 10px 12px;
      border: 1px solid rgba(58, 78, 107, 0.08);
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.88);
      color: var(--secondary-color);
      cursor: pointer;
    }

    .voice-room-invite-option strong {
      font-size: 0.95rem;
    }

    .voice-room-invite-option span {
      color: var(--text-soft);
      font-size: 0.82rem;
    }

    .voice-room-invite-option:hover {
      background: rgba(155, 183, 212, 0.14);
      border-color: rgba(155, 183, 212, 0.36);
    }

    @media (max-width: 900px) {
      body[data-page="voice-room-home"] .page-shell {
        width: min(calc(100% - 24px), 1540px);
      }

      .voice-room-hero-wrap {
        grid-template-columns: 48px minmax(0, 1fr);
      }

      .voice-room-back {
        grid-column: 1;
      }

      .voice-room-hero {
        grid-column: 1 / -1;
        margin-top: 10px;
      }

      .voice-room-filter-bar {
        grid-template-columns: 1fr;
      }

      .voice-room-tools {
        grid-template-columns: 1fr;
      }

      .voice-room-modal-card--form {
        width: min(100vw - 24px, 620px);
        padding: 28px 22px;
      }
    }
  </style>

  <script src="./video-call.js"></script>
  <script>
    window.addEventListener('load', () => {
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
    });
  </script>
  <script src="../../shared-nav.js"></script>
</body>
</html>
