(function () {
  const L = typeof window !== 'undefined' && window.ACADBEAT_LOCAL ? window.ACADBEAT_LOCAL : null;
  const MAIN_ORIGIN = (L && L.mainOrigin) || window.location.origin;
  const API_URL = (L && L.challengeApiUrl) || `${MAIN_ORIGIN}/challenge/api/challenge.php`;
  const REALTIME_WS_URL = (L && L.voiceRoomWsUrl) || `${window.location.protocol === 'https:' ? 'wss' : 'ws'}://${window.location.host}/ws`;
  const state = {
    data: null,
    pendingOpenAfterLogin: false,
    realtimeSocket: null,
    realtimeReconnectTimer: null,
    realtimeRefreshTimer: null,
    realtimeClosedManually: false,
    countdownTimer: null,
    fallbackPollTimer: null,
  };

  function getEl(id) {
    return document.getElementById(id);
  }

  function isChallengeOpen() {
    return !!getEl('teamModalOverlay')?.classList.contains('is-open');
  }

  function closePanels() {
    ['searchLayer', 'publicHubLayer', 'inviteHubLayer'].forEach((id) => {
      const el = getEl(id);
      if (el) el.style.display = 'none';
    });
    ['challengeComposerError', 'publicLobbyError', 'inviteInboxError'].forEach((id) => {
      const el = getEl(id);
      if (el) {
        el.hidden = true;
        el.textContent = '';
      }
    });
  }

  function openPanel(id) {
    closePanels();
    const panel = getEl(id);
    if (panel) panel.style.display = 'flex';
  }

  function setPanelError(id, message = '') {
    const el = getEl(id);
    if (!el) return;
    el.hidden = !message;
    el.textContent = message || '';
  }

  async function challengeFetch(options = {}) {
    const response = await fetch(API_URL, {
      method: options.method || 'GET',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        ...(options.headers || {}),
      },
      body: options.body || undefined,
    });
    const data = await response.json().catch(() => ({ ok: false, message: 'Invalid server response.' }));
    if (!response.ok || data.ok === false) {
      throw new Error(data.message || 'Challenge request failed.');
    }
    return data;
  }

  function formatCountdown(seconds) {
    const value = Math.max(0, Number(seconds || 0));
    const hours = String(Math.floor(value / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((value % 3600) / 60)).padStart(2, '0');
    const secs = String(value % 60).padStart(2, '0');
    return `${hours}:${minutes}:${secs}`;
  }

  function getCountdownParts(seconds) {
    const value = Math.max(0, Number(seconds || 0));
    return {
      hours: String(Math.floor(value / 3600)).padStart(2, '0'),
      minutes: String(Math.floor((value % 3600) / 60)).padStart(2, '0'),
      seconds: String(value % 60).padStart(2, '0'),
    };
  }

  function renderCountdown(seconds) {
    const parts = getCountdownParts(seconds);
    const hoursEl = getEl('formingCountdownHours');
    const minutesEl = getEl('formingCountdownMinutes');
    const secondsEl = getEl('formingCountdownSeconds');
    const container = getEl('formingCountdown');
    if (hoursEl) hoursEl.textContent = parts.hours;
    if (minutesEl) minutesEl.textContent = parts.minutes;
    if (secondsEl) secondsEl.textContent = parts.seconds;
    if (container) {
      container.setAttribute('aria-label', `Time remaining ${parts.hours} hours ${parts.minutes} minutes ${parts.seconds} seconds`);
    }
  }

  function formatRemainingLabel(seconds) {
    const value = Math.max(0, Number(seconds || 0));
    const hours = Math.floor(value / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    if (hours > 0) {
      return `${hours}h ${String(minutes).padStart(2, '0')}m left`;
    }
    if (minutes > 0) {
      return `${minutes}m left`;
    }
    return `${Math.max(0, value % 60)}s left`;
  }

  function stopCountdownTicker() {
    if (state.countdownTimer) {
      window.clearTimeout(state.countdownTimer);
      state.countdownTimer = null;
    }
  }

  function startCountdownTicker(secondsRemaining) {
    stopCountdownTicker();
    let remaining = Math.max(0, Number(secondsRemaining || 0));

    const tick = () => {
      if (!getEl('formingCountdown')) return;
      renderCountdown(remaining);
      if (remaining <= 0) {
        stopCountdownTicker();
        loadState().catch(() => {});
        return;
      }
      remaining -= 1;
      const nextDelay = Math.min(1000, Math.max(120, 1000 - (Date.now() % 1000) + 8));
      state.countdownTimer = window.setTimeout(tick, nextDelay);
    };

    tick();
  }

  function renderSlots(gridId, team, canInvite) {
    const grid = getEl(gridId);
    if (!grid) return;
    const members = team?.members || [];
    const cards = members.map((member) => `
      <div class="slot-card slot-card--filled">
        <div class="slot-card__avatar">${member.avatar}</div>
        <div class="slot-card__badge ${member.role === 'captain' ? 'slot-card__badge--captain' : ''}">
          ${member.role === 'captain' ? 'Captain' : 'Member'}
        </div>
        <p class="slot-card__name">${member.username}</p>
        <p class="slot-card__meta">${member.email || 'AcadBeat member'}</p>
      </div>
    `);

    const missing = Math.max(0, Number(team?.maxMembers || 4) - members.length);
    for (let index = 0; index < missing; index += 1) {
      cards.push(`
        <button type="button" class="slot-card slot-empty" ${canInvite ? 'onclick="openInviteComposer()"' : 'disabled'}>
          <span style="font-size:2rem; font-weight:200;">+</span>
          <p class="module-label" style="margin:8px 0 0;">Seat</p>
          <p class="slot-card__emptyLabel">${canInvite ? 'Invite by username' : 'Waiting to fill'}</p>
        </button>
      `);
    }

    grid.innerHTML = cards.join('');
  }

  function renderSentInvites(sentInvites) {
    const section = getEl('pendingInvitesSection');
    const list = getEl('sentInvitesList');
    if (!section || !list) return;
    if (!sentInvites.length) {
      section.hidden = true;
      list.innerHTML = '';
      return;
    }
    section.hidden = false;
    list.innerHTML = sentInvites.map((invite) => `
      <div class="challenge-pending__item">
        <div class="challenge-pending__avatar">${invite.invitee.avatar}</div>
        <div class="challenge-pending__content">
          <strong>${invite.invitee.username}</strong>
          <span>Invite pending.</span>
        </div>
        <div class="challenge-status-chip">Pending</div>
      </div>
    `).join('');
  }

  function inviteStatusLabel(status) {
    return status === 'accepted'
      ? 'Accepted'
      : status === 'declined'
        ? 'Declined'
        : status === 'cancelled'
          ? 'Cancelled'
          : status === 'expired'
            ? 'Expired'
            : 'Pending';
  }

  function renderInviteInbox(invites) {
    const count = getEl('inviteInboxCount');
    const list = getEl('inviteInboxList');
    const empty = getEl('inviteInboxEmpty');
    const pending = (invites || []).filter((item) => item.status === 'pending').length;
    if (count) count.textContent = String(pending);
    if (!list || !empty) return;
    if (!invites.length) {
      empty.hidden = false;
      list.innerHTML = '';
      return;
    }
    empty.hidden = true;
    list.innerHTML = invites.map((invite) => `
      <div class="challenge-invite-card">
        <div class="challenge-invite-card__avatar">${invite.inviter.avatar}</div>
        <div class="challenge-invite-card__content">
          <strong>${invite.teamName}</strong>
          <span>${invite.inviter.username} invited you into this forming team.</span>
          ${invite.status === 'pending' ? `
            <div class="challenge-invite-card__actions">
              <button type="button" class="challenge-inline-btn" onclick="respondToChallengeInvite(${invite.id}, 'accept')">Accept</button>
              <button type="button" class="challenge-inline-btn challenge-inline-btn--ghost" onclick="respondToChallengeInvite(${invite.id}, 'decline')">Decline</button>
            </div>
          ` : `<div class="challenge-status-chip">${inviteStatusLabel(invite.status)}</div>`}
        </div>
      </div>
    `).join('');
  }

  function renderPublicLobby(listings, currentTeam, signedUp) {
    const list = getEl('publicLobbyList');
    const empty = getEl('publicLobbyEmpty');
    if (!list || !empty) return;
    const visible = (listings || []).filter((item) => !item.isOwnTeam);
    if (!visible.length) {
      empty.hidden = false;
      list.innerHTML = '';
      return;
    }
    empty.hidden = true;
    list.innerHTML = visible.map((item) => `
      <div class="challenge-invite-card">
        <div class="challenge-invite-card__avatar">${String(item.captain || 'C').slice(0, 1).toUpperCase()}</div>
        <div class="challenge-invite-card__content">
          <strong>${item.teamName}</strong>
          <span>Captain: ${item.captain} · Members: ${item.memberCount}/4 · ${formatRemainingLabel(item.secondsRemaining)}</span>
          <div class="challenge-invite-card__actions">
            <button
              type="button"
              class="challenge-inline-btn"
              onclick="joinChallengePublicTeam(${item.teamId}, '${String(item.teamName).replace(/'/g, "\\'")}')"
              ${item.isFull || !signedUp || (currentTeam && currentTeam.status === 'locked') ? 'disabled' : ''}
            >${!signedUp ? 'Sign up first' : item.isFull ? 'Full' : 'Join Team'}</button>
          </div>
        </div>
      </div>
    `).join('');
  }

  function renderLeaderboard(leaderboard, team) {
    const list = getEl('leaderboardList');
    const empty = getEl('leaderboardEmpty');
    const status = getEl('challengeStatusCardValue');
    if (!list || !empty || !status) return;

    if (!leaderboard.length) {
      list.innerHTML = '';
      empty.hidden = false;
    } else {
      empty.hidden = true;
      list.innerHTML = leaderboard.map((entry, index) => `
        <div class="rank-item">
          <span class="rank-badge ${index === 0 ? 'gold' : index === 1 ? 'silver' : index === 2 ? 'bronze' : ''}">${String(entry.rank || index + 1).padStart(2, '0')}</span>
          <div class="rank-info">
            <p class="rank-name">${entry.teamName}</p>
            <p class="rank-pts">${entry.score} pts</p>
          </div>
        </div>
      `).join('');
    }

    if (!team) {
      status.textContent = 'No team yet. Sign up and form a 4-person team.';
    } else if (team.status === 'forming') {
      status.textContent = `${team.name} is still forming with ${team.memberCount}/4 members.`;
    } else if (!team.score) {
      status.textContent = `${team.name} is locked with 0 pts.`;
    } else if (team.dailyRank) {
      status.textContent = `${team.name} is ranked #${team.dailyRank} with ${team.score} pts.`;
    } else {
      status.textContent = `${team.name} is live with ${team.score} pts.`;
    }
  }

  function renderState(nextState) {
    state.data = nextState || null;
    const team = nextState?.team || null;
    const phase = String(nextState?.phase || 'signup');
    const signedUp = !!nextState?.signup?.isSignedUp;
    const isCaptain = !!team?.isCaptain;
    const canConfirmName = !!(team && team.status === 'locked' && isCaptain);

    getEl('challengeCycleLabel').textContent = `Weekly Challenge — ${nextState?.cycle?.label || ''}`;
    getEl('challengeWeekChip').textContent = nextState?.cycle?.resetRule || 'Challenge teams reset every Monday at 00:00.';
    getEl('challengeRoleChip').textContent = `Role: ${team ? (isCaptain ? 'Captain' : 'Member') : signedUp ? 'Signed Up' : 'Open'}`;
    getEl('challengeTitle').textContent =
      phase === 'signup' ? 'Sign up for the team challenge'
        : phase === 'chooser' ? 'Choose how you want to form'
          : phase === 'forming' ? (team?.name || 'Your team is forming')
            : (team?.name || 'Your team is locked');
    getEl('challengeCopy').textContent =
      phase === 'signup'
        ? 'The challenge only runs in 4-person teams. Sign up first, then create your own team or join one from the square.'
        : phase === 'chooser'
          ? 'You are signed up. Either create your own 4-person team or join a team from the public square.'
          : phase === 'forming'
            ? 'Your forming team has a 1-hour countdown. If it does not reach 4 members in time, it dissolves and everyone returns to the chooser state.'
            : 'Your team is full and locked. The captain now confirms the official team name and the team starts accumulating points.';

    getEl('challengeSignupPanel').hidden = phase !== 'signup';
    getEl('challengeChooserPanel').hidden = phase !== 'chooser';
    getEl('challengeFormingPanel').hidden = phase !== 'forming';
    getEl('challengeLockedPanel').hidden = phase !== 'locked';
    getEl('openInviteInboxBtn').hidden = !signedUp;

    if (phase === 'forming' && team) {
      getEl('formingTeamHeading').textContent = team.name || 'Your team is forming';
      renderCountdown(team.secondsRemaining);
      getEl('formingMissingCopy').textContent = team.remainingSlots > 0
        ? `Still missing ${team.remainingSlots} member${team.remainingSlots > 1 ? 's' : ''}`
        : 'Full team reached';
      renderSlots('slotsGrid', team, isCaptain);
      renderSentInvites(nextState?.sentInvites || []);
      const publishBtn = getEl('publishPublicBtn');
      if (publishBtn) {
        publishBtn.textContent = nextState?.publicListing ? 'Remove From Public' : 'Post To Public';
        publishBtn.disabled = !isCaptain;
      }
      startCountdownTicker(team.secondsRemaining);
    } else {
      stopCountdownTicker();
      renderSentInvites([]);
      renderSlots('slotsGrid', { members: [], maxMembers: 0 }, false);
    }

    if (phase === 'locked' && team) {
      const input = getEl('teamNameInput');
      if (input) {
        input.value = team.name || '';
        input.disabled = !canConfirmName;
      }
      getEl('saveTeamNameBtn').hidden = !canConfirmName;
      getEl('lockedTeamHeading').textContent = `${team.name || 'Your team'} is locked`;
      renderSlots('lockedSlotsGrid', team, false);
    } else {
      const input = getEl('teamNameInput');
      if (input) {
        input.value = '';
        input.disabled = true;
      }
      getEl('saveTeamNameBtn').hidden = true;
      renderSlots('lockedSlotsGrid', { members: [], maxMembers: 0 }, false);
    }

    renderInviteInbox(nextState?.receivedInvites || []);
    renderPublicLobby(nextState?.publicListings || [], team, signedUp);
    renderLeaderboard(nextState?.leaderboard || [], team);
  }

  async function loadState() {
    const data = await challengeFetch();
    renderState(data.state || null);
    return data.state || null;
  }

  function scheduleRealtimeRefresh() {
    if (state.realtimeRefreshTimer) window.clearTimeout(state.realtimeRefreshTimer);
    state.realtimeRefreshTimer = window.setTimeout(() => {
      state.realtimeRefreshTimer = null;
      if (!isChallengeOpen()) return;
      loadState().catch(() => {});
    }, 120);
  }

  function startFallbackPolling() {
    if (state.fallbackPollTimer) return;
    state.fallbackPollTimer = window.setInterval(() => {
      if (!isChallengeOpen()) return;
      loadState().catch(() => {});
    }, 5000);
  }

  function stopFallbackPolling() {
    if (state.fallbackPollTimer) {
      window.clearInterval(state.fallbackPollTimer);
      state.fallbackPollTimer = null;
    }
  }

  function handleRealtimeEvent(payload) {
    if (!payload || payload.type !== 'challenge.updated') return;
    if (typeof authState === 'undefined' || !authState.user) return;
    if (String(authState.user.role || '').toLowerCase() === 'admin') return;
    scheduleRealtimeRefresh();
  }

  function connectChallengeRealtime() {
    if (state.realtimeSocket || state.realtimeClosedManually) return;
    try {
      const socket = new WebSocket(REALTIME_WS_URL);
      state.realtimeSocket = socket;
      socket.addEventListener('message', (event) => {
        try {
          handleRealtimeEvent(JSON.parse(event.data));
        } catch (_err) {
          // Ignore malformed payloads.
        }
      });
      socket.addEventListener('close', () => {
        state.realtimeSocket = null;
        startFallbackPolling();
        if (state.realtimeClosedManually) return;
        state.realtimeReconnectTimer = window.setTimeout(connectChallengeRealtime, 1500);
      });
      socket.addEventListener('open', () => {
        stopFallbackPolling();
      });
      socket.addEventListener('error', () => {
        try {
          socket.close();
        } catch (_err) {}
      });
    } catch (_err) {
      state.realtimeSocket = null;
      startFallbackPolling();
    }
  }

  async function openChallengeModal() {
    if (typeof authState === 'undefined' || !authState.user) {
      state.pendingOpenAfterLogin = true;
      openAuthModal('login', 'Please log in before using challenge teams.');
      return;
    }
    if (String(authState.user.role || '').toLowerCase() === 'admin') {
      openSiteModal('Challenge Disabled', 'Admin accounts do not join weekly challenge teams.');
      return;
    }
    getEl('teamModalOverlay')?.classList.add('is-open');
    startFallbackPolling();
    await loadState();
  }

  function closeChallengeModal() {
    getEl('teamModalOverlay')?.classList.remove('is-open');
    stopCountdownTicker();
    stopFallbackPolling();
    closePanels();
  }

  async function signUp() {
    const response = await challengeFetch({
      method: 'POST',
      body: JSON.stringify({ action: 'signup' }),
    });
    renderState(response.state || null);
    openSiteModal('Challenge Signup', response.message || 'Signed up.');
  }

  async function createTeam() {
    const response = await challengeFetch({
      method: 'POST',
      body: JSON.stringify({ action: 'create_team' }),
    });
    renderState(response.state || null);
    openSiteModal('Team Created', response.message || 'Team created.');
  }

  async function saveTeamName() {
    const teamName = getEl('teamNameInput')?.value.trim() || '';
    if (!teamName) {
      openSiteModal('Team Name', 'Enter the official team name first.');
      return;
    }
    const response = await challengeFetch({
      method: 'POST',
      body: JSON.stringify({ action: 'confirm_team_name', teamName }),
    });
    renderState(response.state || null);
    openSiteModal('Team Name Confirmed', response.message || 'Team name confirmed.');
  }

  async function sendInvite() {
    setPanelError('challengeComposerError', '');
    const inviteeUsername = getEl('searchInput')?.value.trim() || '';
    if (!inviteeUsername) {
      setPanelError('challengeComposerError', 'Enter the teammate username first.');
      return;
    }
    try {
      const response = await challengeFetch({
        method: 'POST',
        body: JSON.stringify({ action: 'send_invite', inviteeUsername }),
      });
      if (getEl('searchInput')) getEl('searchInput').value = '';
      renderState(response.state || null);
      closePanels();
      openSiteModal('Invite Sent', response.message || 'Invite sent.');
    } catch (error) {
      setPanelError('challengeComposerError', error.message || 'Failed to send invite.');
    }
  }

  async function respondToInvite(inviteId, decision) {
    setPanelError('inviteInboxError', '');
    try {
      const response = await challengeFetch({
        method: 'POST',
        body: JSON.stringify({ action: 'respond_invite', inviteId, decision }),
      });
      renderState(response.state || null);
      openSiteModal('Invitation Updated', response.message || 'Invite updated.');
    } catch (error) {
      setPanelError('inviteInboxError', error.message || 'Failed to update invite.');
    }
  }

  async function togglePublicListing() {
    try {
      const response = await challengeFetch({
        method: 'POST',
        body: JSON.stringify({
          action: 'toggle_public_listing',
          mode: state.data?.publicListing ? 'close' : 'publish',
        }),
      });
      renderState(response.state || null);
      openSiteModal('Team Square', response.message || 'Public status updated.');
    } catch (error) {
      openSiteModal('Team Square', error.message || 'Failed to update public status.');
    }
  }

  async function joinPublicTeam(teamId, teamName) {
    const proceed = window.confirm(`Join "${teamName}" now? You will enter this forming team directly.`);
    if (!proceed) return;
    setPanelError('publicLobbyError', '');
    try {
      const response = await challengeFetch({
        method: 'POST',
        body: JSON.stringify({ action: 'join_public_team', teamId }),
      });
      renderState(response.state || null);
      closePanels();
      openSiteModal('Joined Team', response.message || 'Team joined.');
    } catch (error) {
      setPanelError('publicLobbyError', error.message || 'Failed to join team.');
    }
  }

  function attachListeners() {
    const overlay = getEl('teamModalOverlay');
    if (overlay) {
      overlay.addEventListener('click', (event) => {
        if (event.target === overlay) closeChallengeModal();
      });
    }
  }

  window.toggleTeamModal = function (show) {
    if (show) {
      openChallengeModal().catch((error) => openSiteModal('Challenge Error', error.message || 'Failed to load challenge.'));
    } else {
      closeChallengeModal();
    }
  };
  window.openInviteComposer = function () { openPanel('searchLayer'); };
  window.closeSearch = function () { closePanels(); };
  window.challengeSendInvite = function () { sendInvite(); };
  window.openPublicHub = function () { openPanel('publicHubLayer'); };
  window.closePublicHub = function () { closePanels(); };
  window.openInviteInbox = function () { openPanel('inviteHubLayer'); };
  window.closeInviteInbox = function () { closePanels(); };
  window.respondToChallengeInvite = function (inviteId, decision) { respondToInvite(inviteId, decision); };
  window.toggleChallengePublicListing = function () { togglePublicListing(); };
  window.joinChallengePublicTeam = function (teamId, teamName) { joinPublicTeam(teamId, teamName); };
  window.signUpForChallenge = function () { signUp().catch((error) => openSiteModal('Challenge Error', error.message || 'Signup failed.')); };
  window.createChallengeTeam = function () { createTeam().catch((error) => openSiteModal('Challenge Error', error.message || 'Failed to create team.')); };
  window.saveChallengeTeamName = function () { saveTeamName().catch((error) => openSiteModal('Challenge Error', error.message || 'Failed to confirm team name.')); };

  window.challengeHome = {
    syncAccess(user) {
      const btn = getEl('challengeEntryBtn');
      if (btn) btn.hidden = !!(user && String(user.role || '').toLowerCase() === 'admin');
      if (user && String(user.role || '').toLowerCase() !== 'admin') {
        connectChallengeRealtime();
      }
      if (state.pendingOpenAfterLogin && user && String(user.role || '').toLowerCase() !== 'admin') {
        state.pendingOpenAfterLogin = false;
        openChallengeModal().catch((error) => openSiteModal('Challenge Error', error.message || 'Failed to open challenge.'));
      }
    },
    handleInitialRoute(params) {
      if (params.get('challenge') === '1') {
        openChallengeModal().catch((error) => openSiteModal('Challenge Error', error.message || 'Failed to open challenge.'));
      }
    },
  };

  attachListeners();
  if (typeof authState !== 'undefined' && authState.user && String(authState.user.role || '').toLowerCase() !== 'admin') {
    connectChallengeRealtime();
  }
})();
