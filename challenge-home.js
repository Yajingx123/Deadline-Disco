(function () {
  const API_URL = 'http://127.0.0.1:8001/forum-project/api/challenge.php';
  const REALTIME_WS_URL = 'ws://127.0.0.1:3001/ws';
  const state = {
    data: null,
    pendingOpenAfterLogin: false,
    previousMemberCount: 0,
    draftTeamName: '',
    realtimeSocket: null,
    realtimeReconnectTimer: null,
    realtimeClosedManually: false,
    realtimeRefreshTimer: null,
    hubTabs: {
      public: 'browse',
      request: 'invited',
    },
  };

  function getEl(id) {
    return document.getElementById(id);
  }

  function isChallengeOpen() {
    return !!getEl('teamModalOverlay')?.classList.contains('is-open');
  }

  function scheduleRealtimeRefresh() {
    if (state.realtimeRefreshTimer) {
      window.clearTimeout(state.realtimeRefreshTimer);
    }
    state.realtimeRefreshTimer = window.setTimeout(() => {
      state.realtimeRefreshTimer = null;
      if (typeof authState === 'undefined' || !authState.user) return;
      if (String(authState.user.role || '').toLowerCase() === 'admin') return;
      if (!isChallengeOpen() && !state.data) return;
      loadState().catch(() => {});
    }, 120);
  }

  function handleRealtimeEvent(payload) {
    if (!payload || payload.type !== 'challenge.updated') return;
    if (typeof authState === 'undefined' || !authState.user) return;
    const currentUserId = Number(authState.user.user_id || 0);
    const eventUserIds = Array.isArray(payload.data?.userIds)
      ? payload.data.userIds.map((value) => Number(value || 0)).filter((value) => value > 0)
      : [];
    const currentTeamId = Number(state.data?.team?.id || 0);
    const eventTeamId = Number(payload.data?.teamId || 0);
    const isGlobal = String(payload.data?.scope || 'global') === 'global';
    const isRelevant = isGlobal || eventUserIds.includes(currentUserId) || (currentTeamId > 0 && eventTeamId === currentTeamId);
    if (!isRelevant) return;
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
          // Ignore malformed realtime events.
        }
      });

      socket.addEventListener('close', () => {
        state.realtimeSocket = null;
        if (state.realtimeClosedManually) return;
        state.realtimeReconnectTimer = window.setTimeout(() => {
          state.realtimeReconnectTimer = null;
          connectChallengeRealtime();
        }, 1500);
      });

      socket.addEventListener('error', () => {
        try {
          socket.close();
        } catch (_err) {
          // Ignore close errors.
        }
      });
    } catch (_err) {
      state.realtimeSocket = null;
    }
  }

  function setPanelError(id, message = '') {
    const el = getEl(id);
    if (!el) return;
    if (!message) {
      el.hidden = true;
      el.textContent = '';
      return;
    }
    el.hidden = false;
    el.textContent = message;
  }

  function clearAllPanelErrors() {
    ['challengeComposerError', 'inviteInboxError', 'publicLobbyError', 'captainRequestError'].forEach((id) => {
      setPanelError(id, '');
    });
  }

  function setHubTab(kind, tab) {
    state.hubTabs[kind] = tab;
    if (kind === 'public') {
      getEl('publicBrowseTab')?.classList.toggle('is-active', tab === 'browse');
      getEl('publicMineTab')?.classList.toggle('is-active', tab === 'mine');
      getEl('publicBrowsePane')?.classList.toggle('is-active', tab === 'browse');
      getEl('publicMinePane')?.classList.toggle('is-active', tab === 'mine');
      return;
    }
    getEl('requestInvitedTab')?.classList.toggle('is-active', tab === 'invited');
    getEl('requestReviewTab')?.classList.toggle('is-active', tab === 'review');
    getEl('requestInvitedPane')?.classList.toggle('is-active', tab === 'invited');
    getEl('requestReviewPane')?.classList.toggle('is-active', tab === 'review');
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
      const error = new Error(data.message || 'Challenge request failed.');
      error.payload = data;
      throw error;
    }
    return data;
  }

  function syncChallengeEntry(user) {
    const btn = getEl('challengeEntryBtn');
    if (btn) btn.hidden = !!(user && String(user.role || '').toLowerCase() === 'admin');
  }

  function closePanels() {
    ['searchLayer', 'publicHubLayer', 'requestHubLayer'].forEach((id) => {
      const el = getEl(id);
      if (el) el.style.display = 'none';
    });
    clearAllPanelErrors();
  }

  function fakeCaptainTeam() {
    if (typeof authState === 'undefined' || !authState.user) return null;
    const username = String(authState.user.username || 'User');
    return {
      id: 0,
      name: state.draftTeamName || `${username}'s Squad`,
      score: 0,
      dailyRank: null,
      memberCount: 1,
      maxMembers: 4,
      isCaptain: true,
      members: [{
        id: Number(authState.user.user_id || 0),
        username,
        email: String(authState.user.email || ''),
        role: 'captain',
        avatar: username.slice(0, 1).toUpperCase(),
      }],
    };
  }

  function challengeDisplayTeam() {
    return state.data?.team || fakeCaptainTeam();
  }

  function showFullTeamOverlay(team) {
    const overlay = getEl('successOverlay');
    if (!overlay || !team) return;
    const title = getEl('successTeamTitle');
    const copy = getEl('successTeamCopy');
    if (title) title.textContent = team.name || 'Ready to Sprint';
    if (copy) copy.textContent = `Your squad is full for ${state.data?.cycle?.label || 'this week'}.`;
    overlay.style.display = 'flex';
    getEl('teamModal')?.classList.add('is-ready');
  }

  function hideFullTeamOverlay() {
    const overlay = getEl('successOverlay');
    if (overlay) overlay.style.display = 'none';
    getEl('teamModal')?.classList.remove('is-ready');
  }

  function renderSlots(team, rules) {
    const grid = getEl('slotsGrid');
    if (!grid) return;
    const members = team?.members || [];
    const maxMembers = Number(rules?.maxMembers || 4);
    const canInvite = !!team?.isCaptain;
    const cards = [];

    members.forEach((member) => {
      cards.push(`
        <div class="slot-card slot-card--filled">
          <div class="slot-card__avatar">${member.avatar}</div>
          <div class="slot-card__badge ${member.role === 'captain' ? 'slot-card__badge--captain' : ''}">
            ${member.role === 'captain' ? 'Captain' : 'Member'}
          </div>
          <p class="slot-card__name">${member.username}</p>
          <p class="slot-card__meta">${member.email || 'AcadBeat member'}</p>
        </div>
      `);
    });

    for (let index = members.length; index < maxMembers; index += 1) {
      cards.push(`
        <button type="button" class="slot-card slot-empty" ${canInvite ? 'onclick="openInviteComposer()"' : 'disabled'}>
          <span style="font-size:2rem; font-weight:200;">+</span>
          <p class="module-label" style="margin:8px 0 0;">Invite</p>
          <p class="slot-card__emptyLabel">${canInvite ? 'Send an invite by username' : 'Only the captain can invite'}</p>
        </button>
      `);
    }

    grid.innerHTML = cards.join('');
  }

  function renderSentInvites(sentInvites) {
    const list = getEl('sentInvitesList');
    const section = getEl('pendingInvitesSection');
    if (!list || !section) return;
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
          <span>Pending invite sent.</span>
        </div>
        <div class="challenge-status-chip">Pending</div>
      </div>
    `).join('');
  }

  function renderInviteInbox(invites) {
    const count = getEl('inviteInboxCount');
    const list = getEl('inviteInboxList');
    const empty = getEl('inviteInboxEmpty');
    const pendingCount = (invites || []).filter((invite) => invite.status === 'pending').length;
    if (count) count.textContent = String(pendingCount || 0);
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
          <span>${invite.inviter.username} invited you to join this week&apos;s team.</span>
          ${invite.status === 'pending' ? `
            <div class="challenge-invite-card__actions">
              <button type="button" class="challenge-inline-btn" onclick="respondToChallengeInvite(${invite.id}, 'accept')">Accept</button>
              <button type="button" class="challenge-inline-btn challenge-inline-btn--ghost" onclick="respondToChallengeInvite(${invite.id}, 'decline')">Decline</button>
            </div>
          ` : `
            <div class="challenge-status-chip">${invite.status === 'accepted' ? 'Accepted' : invite.status === 'declined' ? 'Declined' : invite.status === 'cancelled' ? 'Cancelled' : 'Expired'}</div>
          `}
        </div>
      </div>
    `).join('');
  }

  function renderPublicLobby(listings, currentTeam, teamLocked) {
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
          <span>Captain: ${item.captain} · Members: ${item.memberCount}/4${item.description ? ` · ${item.description}` : ''}</span>
          <div class="challenge-invite-card__actions">
            <button
              type="button"
              class="challenge-inline-btn"
              onclick="requestJoinPublicTeam(${item.teamId})"
              ${item.isFull || item.hasPendingRequest || teamLocked ? 'disabled' : ''}
            >${item.isFull ? 'Full' : item.hasPendingRequest ? 'Requested' : teamLocked ? 'Locked Team' : 'Request to Join'}</button>
          </div>
        </div>
      </div>
    `).join('');
  }

  function renderCaptainRequests(requests) {
    const list = getEl('captainRequestList');
    const empty = getEl('captainRequestEmpty');
    if (!list || !empty) return;
    if (!requests.length) {
      empty.hidden = false;
      list.innerHTML = '';
      return;
    }
    empty.hidden = true;
    list.innerHTML = requests.map((item) => `
      <div class="challenge-invite-card">
        <div class="challenge-invite-card__avatar">${item.requester.avatar}</div>
        <div class="challenge-invite-card__content">
          <strong>${item.requester.username}</strong>
          <span>${item.requester.email || 'AcadBeat user'}${item.message ? ` · ${item.message}` : ''}</span>
          ${item.status === 'pending' ? `
            <div class="challenge-invite-card__actions">
              <button type="button" class="challenge-inline-btn" onclick="respondToCaptainJoinRequest(${item.id}, 'accept')">Approve</button>
              <button type="button" class="challenge-inline-btn challenge-inline-btn--ghost" onclick="respondToCaptainJoinRequest(${item.id}, 'decline')">Decline</button>
            </div>
          ` : `
            <div class="challenge-status-chip">${item.status === 'accepted' ? 'Accepted' : item.status === 'declined' ? 'Declined' : item.status === 'cancelled' ? 'Cancelled' : 'Expired'}</div>
          `}
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
      status.textContent = 'No team yet.';
    } else if (!team.score) {
      status.textContent = `${team.name} is live with 0 pts.`;
    } else if (team.dailyRank) {
      status.textContent = `${team.name} is ranked #${team.dailyRank} with ${team.score} pts.`;
    } else {
      status.textContent = `${team.name} is active with ${team.score} pts.`;
    }
  }

  function renderState(nextState) {
    state.data = nextState || null;
    const rules = nextState?.rules || { maxMembers: 4 };
    const realTeam = nextState?.team || null;
    const displayTeam = challengeDisplayTeam();
    const isCaptain = !!displayTeam?.isCaptain;
    const teamLocked = !!nextState?.teamLocked;
    const pendingInviteCount = (nextState?.receivedInvites || []).filter((item) => item.status === 'pending').length;
    const pendingCaptainReviewCount = (nextState?.captainJoinRequests || []).filter((item) => item.status === 'pending').length;
    const requestPendingTotal = pendingInviteCount + pendingCaptainReviewCount;

    getEl('challengeCycleLabel').textContent = `Weekly Challenge — ${nextState?.cycle?.label || ''}`;
    getEl('challengeWeekChip').textContent = nextState?.cycle?.resetRule || 'Teams reset every Monday at 00:00.';
    getEl('challengeRoleChip').textContent = `Role: ${isCaptain ? 'Captain' : 'Member'}`;
    getEl('challengeTitle').textContent = displayTeam?.name || 'Create your fellowship';
    getEl('challengeCopy').textContent = 'Teams are capped at four members. Only captains can invite, and every Monday the system clears weekly teams and notifies members.';

    const teamNameInput = getEl('teamNameInput');
    if (teamNameInput) {
      const fallbackName = realTeam?.name || state.draftTeamName || displayTeam?.name || '';
      teamNameInput.value = fallbackName;
      teamNameInput.disabled = !!(realTeam && !realTeam.isCaptain);
      state.draftTeamName = fallbackName;
    }

    getEl('saveTeamNameBtn').hidden = !!(realTeam && !realTeam.isCaptain);
    getEl('publishPublicBtn').disabled = !isCaptain;
    getEl('publishPublicBtn').hidden = !isCaptain;
    getEl('challengeAdminNote').hidden = !(nextState?.access?.isAdmin);
    const requestCountEl = getEl('requestPendingCount');
    if (requestCountEl) {
      requestCountEl.hidden = requestPendingTotal <= 0;
      requestCountEl.textContent = String(requestPendingTotal);
    }

    if (getEl('publishPublicBtn')) {
      const isPublic = !!nextState?.publicListing;
      getEl('publishPublicBtn').textContent = isPublic ? 'Remove Public' : 'Post to Public';
    }
    if (getEl('publicMineStatus')) {
      getEl('publicMineStatus').textContent = nextState?.publicListing
        ? 'Your team is currently visible in the public lobby. Remove it when you no longer want applicants.'
        : 'Your team is currently private. Publish it when you want others to request entry.';
    }

    const publicMineTab = getEl('publicMineTab');
    if (publicMineTab) publicMineTab.hidden = !isCaptain;
    const requestReviewTab = getEl('requestReviewTab');
    if (requestReviewTab) requestReviewTab.hidden = !isCaptain;
    if (!isCaptain) {
      state.hubTabs.public = 'browse';
      state.hubTabs.request = 'invited';
    }

    renderSlots(displayTeam, rules);
    renderSentInvites(nextState?.sentInvites || []);
    renderInviteInbox(nextState?.receivedInvites || []);
    renderPublicLobby(nextState?.publicListings || [], realTeam, teamLocked);
    renderCaptainRequests(nextState?.captainJoinRequests || []);
    renderLeaderboard(nextState?.leaderboard || [], realTeam);
    setHubTab('public', state.hubTabs.public);
    setHubTab('request', state.hubTabs.request);

    const currentMemberCount = Number(realTeam?.memberCount || displayTeam?.memberCount || 0);
    if (currentMemberCount >= Number(realTeam?.maxMembers || displayTeam?.maxMembers || 4) && state.previousMemberCount < Number(realTeam?.maxMembers || displayTeam?.maxMembers || 4)) {
      showFullTeamOverlay(realTeam || displayTeam);
    }
    state.previousMemberCount = currentMemberCount;
  }

  async function loadState() {
    const data = await challengeFetch();
    renderState(data.state || null);
    return data.state || null;
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
    const overlay = getEl('teamModalOverlay');
    if (overlay) overlay.classList.add('is-open');
    await loadState();
  }

  function closeChallengeModal() {
    getEl('teamModalOverlay')?.classList.remove('is-open');
    closePanels();
    hideFullTeamOverlay();
  }

  async function saveTeamName() {
    const input = getEl('teamNameInput');
    if (!input) return;
    state.draftTeamName = input.value.trim();
    if (!state.data?.team) {
      renderState(state.data);
      openSiteModal('Draft Saved', 'The team name draft will be used when you invite someone or post to public.');
      return;
    }
    const response = await challengeFetch({
      method: 'POST',
      body: JSON.stringify({ action: 'rename_team', teamName: state.draftTeamName }),
    });
    renderState(response.state || null);
    openSiteModal('Team Updated', response.message || 'Team name updated.');
  }

  async function sendInvite() {
    setPanelError('challengeComposerError', '');
    const input = getEl('searchInput');
    const teamName = getEl('teamNameInput')?.value.trim() || state.draftTeamName;
    const inviteeUsername = input?.value.trim() || '';
    if (!inviteeUsername) {
      setPanelError('challengeComposerError', 'Enter the teammate username first.');
      return;
    }
    try {
      const response = await challengeFetch({
        method: 'POST',
        body: JSON.stringify({ action: 'send_invite', inviteeUsername, teamName }),
      });
      if (input) input.value = '';
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
      openSiteModal('Challenge Updated', response.message || 'Invite updated.');
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
          teamName: getEl('teamNameInput')?.value.trim() || state.draftTeamName,
        }),
      });
      renderState(response.state || null);
      openSiteModal('Public Lobby', response.message || 'Public listing updated.');
    } catch (error) {
      openSiteModal('Public Lobby', error.message || 'Failed to update public listing.');
    }
  }

  async function requestJoinPublicTeam(teamId) {
    setPanelError('publicLobbyError', '');
    try {
      const response = await challengeFetch({
        method: 'POST',
        body: JSON.stringify({ action: 'request_join_public', teamId }),
      });
      renderState(response.state || null);
      openSiteModal('Request Sent', response.message || 'Join request sent.');
    } catch (error) {
      setPanelError('publicLobbyError', error.message || 'Failed to send join request.');
    }
  }

  async function respondToCaptainJoinRequest(requestId, decision) {
    setPanelError('captainRequestError', '');
    try {
      const response = await challengeFetch({
        method: 'POST',
        body: JSON.stringify({ action: 'respond_join_request', requestId, decision }),
      });
      renderState(response.state || null);
      openSiteModal('Join Request Updated', response.message || 'Join request updated.');
    } catch (error) {
      setPanelError('captainRequestError', error.message || 'Failed to update join request.');
    }
  }

  function openPanel(id) {
    closePanels();
    const panel = getEl(id);
    if (panel) panel.style.display = 'flex';
  }

  function attachListeners() {
    const overlay = getEl('teamModalOverlay');
    if (overlay) {
      overlay.addEventListener('click', (event) => {
        if (event.target === overlay) closeChallengeModal();
      });
    }

    const teamNameInput = getEl('teamNameInput');
    if (teamNameInput) {
      teamNameInput.addEventListener('input', (event) => {
        state.draftTeamName = event.target.value.trim();
        const title = getEl('challengeTitle');
        if (title && !state.data?.team) {
          title.textContent = state.draftTeamName || fakeCaptainTeam()?.name || 'Create your fellowship';
        }
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
  window.respondToChallengeInvite = function (inviteId, decision) { respondToInvite(inviteId, decision); };
  window.openPublicHub = function () {
    openPanel('publicHubLayer');
    setHubTab('public', 'browse');
  };
  window.closePublicHub = function () { closePanels(); };
  window.requestJoinPublicTeam = function (teamId) { requestJoinPublicTeam(teamId); };
  window.openRequestHub = function () {
    openPanel('requestHubLayer');
    setHubTab('request', 'invited');
  };
  window.closeRequestHub = function () { closePanels(); };
  window.respondToCaptainJoinRequest = function (requestId, decision) { respondToCaptainJoinRequest(requestId, decision); };
  window.toggleChallengePublicListing = function () { togglePublicListing(); };
  window.closeChallengeCelebration = hideFullTeamOverlay;
  window.saveChallengeTeamName = function () { saveTeamName(); };
  window.setChallengeHubTab = function (kind, tab) { setHubTab(kind, tab); };

  window.challengeHome = {
    syncAccess(user) {
      syncChallengeEntry(user);
      if (user && String(user.role || '').toLowerCase() !== 'admin') {
        connectChallengeRealtime();
      }
      if (state.pendingOpenAfterLogin && user && String(user.role || '').toLowerCase() !== 'admin') {
        state.pendingOpenAfterLogin = false;
        openChallengeModal().catch((error) => openSiteModal('Challenge Error', error.message || 'Failed to load challenge.'));
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
