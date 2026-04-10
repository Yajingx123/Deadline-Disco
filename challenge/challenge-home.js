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
    tutorialIndex: 0,
  };

  function resolveTeamGuidePath(fileName) {
    const path = (window.location.pathname || '').toLowerCase();
    const isRankPage = path.includes('/rank/') || path.includes('/rank-v2/');
    const isGameUiChallenge = path.includes('/gameui/challenge-gameui/');
    const base = isGameUiChallenge
      ? '/challenge/teamGuide/'
      : (isRankPage ? '../challenge/teamGuide/' : './teamGuide/');
    return `${base}${fileName}`;
  }

  const CHALLENGE_TUTORIAL_STEPS = [
    {
      title: 'Step 1: Sign Up First',
      image: resolveTeamGuidePath('signup.png'),
      copy: 'First, sign up for this week\'s challenge cycle. Only signed-up users can create or join teams.',
    },
    {
      title: 'Step 2: Choose Mode',
      image: resolveTeamGuidePath('chooseMode.png'),
      copy: 'After sign-up, choose your route first: create your own team or go to the square.',
    },
    {
      title: 'Step 3: Team Square',
      image: resolveTeamGuidePath('square.png'),
      copy: 'In Team Square, you can browse open teams and join one that fits your plan.',
    },
    {
      title: 'Step 4: My Team Invite',
      image: resolveTeamGuidePath('myteam.png'),
      copy: 'When you create your own team, use Invite to send requests and fill all required team seats. You can also post your team to the square so that others can join.',
    },
    {
      title: 'Step 5: Invitation Inbox',
      image: resolveTeamGuidePath('invitation.png'),
      copy: 'Use the Invitations button to review invite records and accept/decline invites sent to you.',
    },
  ];

  function getEl(id) {
    return document.getElementById(id);
  }

  function getChallengeMount() {
    return document.getElementById('challengeMount');
  }

  function isStandaloneChallengePanel() {
    return !!getEl('challengePageRoot') && !getChallengeMount();
  }

  function getAuthUser() {
    if (typeof window === 'undefined') return null;
    if (window.authState && window.authState.user) {
      return window.authState.user;
    }
    if (window.acadbeatNavState && window.acadbeatNavState.user) {
      return window.acadbeatNavState.user;
    }
    return null;
  }

  function getGuideUserId() {
    const user = getAuthUser() || {};
    return user.user_id || user.id || user.username || 'guest';
  }

  function challengeTutorialSeenKey(userId) {
    return `acadbeat:challenge:tutorial:v2:u:${String(userId || 'guest')}`;
  }

  function hasSeenChallengeTutorial(userId) {
    try {
      return window.localStorage.getItem(challengeTutorialSeenKey(userId)) === '1';
    } catch (_err) {
      return false;
    }
  }

  function markSeenChallengeTutorial(userId) {
    try {
      window.localStorage.setItem(challengeTutorialSeenKey(userId), '1');
    } catch (_err) {}
  }

  function isChallengePageMode() {
    return Boolean(
      (typeof window !== 'undefined' && window.ACADBEAT_CHALLENGE_PAGE)
      || document.body?.dataset?.challengePage === '1'
    );
  }

  function competitionHubUrl() {
    let base = './gates/competition-gate.html';
    try {
      const path = window.location.pathname || '';
      if (path.includes('/rank/') || isChallengePageMode()) {
        base = '../gates/competition-gate.html';
      }
    } catch (_e) {}
    return `${base}?highlight=challenge`;
  }

  function isChallengeOpen() {
    return !!getChallengeMount()?.classList.contains('challenge-mount--open');
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
    const v2Class = isV2Style ? ' v2-style' : '';
    const cards = members.map((member) => `
      <div class="slot-card slot-card--filled${v2Class}">
        <div class="slot-card__avatar${v2Class}">${member.avatar}</div>
        <div class="slot-card__badge${v2Class} ${member.role === 'captain' ? 'slot-card__badge--captain' : ''}${v2Class}">
          ${member.role === 'captain' ? 'Captain' : 'Member'}
        </div>
        <p class="slot-card__name${v2Class}">${member.username}</p>
        <p class="slot-card__meta${v2Class}">${member.email || 'AcadBeat member'}</p>
      </div>
    `);

    const missing = Math.max(0, Number(team?.maxMembers || 4) - members.length);
    for (let index = 0; index < missing; index += 1) {
      cards.push(`
        <button type="button" class="slot-card slot-empty${v2Class}" ${canInvite ? 'onclick="openInviteComposer()"' : 'disabled'}>
          <span style="font-size:2rem; font-weight:200;">+</span>
          <p class="module-label${v2Class}" style="margin:8px 0 0;">Seat</p>
          <p class="slot-card__emptyLabel${v2Class}">${canInvite ? 'Invite by username' : 'Waiting to fill'}</p>
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
    const v2Class = isV2Style ? ' v2-style' : '';
    section.hidden = false;
    list.innerHTML = sentInvites.map((invite) => `
      <div class="challenge-pending__item${v2Class}">
        <div class="challenge-pending__avatar${v2Class}">${invite.invitee.avatar}</div>
        <div class="challenge-pending__content${v2Class}">
          <strong>${invite.invitee.username}</strong>
          <span>Invite pending.</span>
        </div>
        <div class="challenge-status-chip${v2Class}">Pending</div>
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
    const v2Class = isV2Style ? ' v2-style' : '';

    if (!leaderboard.length) {
      list.innerHTML = '';
      empty.hidden = false;
    } else {
      empty.hidden = true;
      list.innerHTML = leaderboard.map((entry, index) => `
        <div class="rank-item${v2Class}">
          <span class="rank-badge ${index === 0 ? 'gold' : index === 1 ? 'silver' : index === 2 ? 'bronze' : ''}${v2Class}">${String(entry.rank || index + 1).padStart(2, '0')}</span>
          <div class="rank-info${v2Class}">
            <p class="rank-name${v2Class}">${entry.teamName}</p>
            <p class="rank-pts${v2Class}">${entry.score} pts</p>
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
    const u = (typeof authState !== 'undefined' && authState.user) ? authState.user : getAuthUser();
    if (!u) return;
    if (String(u.role || '').toLowerCase() === 'admin') return;
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
    const authUser = getAuthUser();
    if (!authUser) {
      state.pendingOpenAfterLogin = true;
      openAuthModal('login', 'Please log in before using challenge teams.');
      return;
    }
    if (String(authUser.role || '').toLowerCase() === 'admin') {
      openSiteModal('Challenge Disabled', 'Admin accounts do not join weekly challenge teams.');
      return;
    }
    const mount = getChallengeMount();
    mount?.classList.add('challenge-mount--open');
    mount?.setAttribute('aria-hidden', 'false');
    startFallbackPolling();
    await loadState();
  }

  function closeChallengeModal() {
    if (isChallengePageMode()) {
      window.location.href = competitionHubUrl();
      return;
    }
    const mount = getChallengeMount();
    mount?.classList.remove('challenge-mount--open');
    mount?.setAttribute('aria-hidden', 'true');
    stopCountdownTicker();
    stopFallbackPolling();
    closePanels();
    closeChallengeTutorial();
    if (typeof window.acadbeatAfterChallengeClose === 'function') {
      window.acadbeatAfterChallengeClose();
    }
  }

  function renderChallengeTutorial() {
    const titleEl = getEl('challengeTutorialTitle');
    const imageEl = getEl('challengeTutorialImage');
    const copyEl = getEl('challengeTutorialCopy');
    const progressEl = getEl('challengeTutorialProgress');
    const prevBtn = getEl('challengeTutorialPrevBtn');
    const nextBtn = getEl('challengeTutorialNextBtn');
    if (!titleEl || !imageEl || !copyEl || !progressEl || !prevBtn || !nextBtn) return;
    const idx = Math.max(0, Math.min(CHALLENGE_TUTORIAL_STEPS.length - 1, state.tutorialIndex));
    const step = CHALLENGE_TUTORIAL_STEPS[idx];
    titleEl.textContent = step.title;
    imageEl.src = step.image;
    imageEl.alt = step.title;
    copyEl.textContent = step.copy;
    progressEl.textContent = `${idx + 1} / ${CHALLENGE_TUTORIAL_STEPS.length}`;
    prevBtn.disabled = idx <= 0;
    nextBtn.textContent = idx >= CHALLENGE_TUTORIAL_STEPS.length - 1 ? '✓' : '→';
  }

  function openChallengeTutorial(force) {
    const user = getAuthUser();
    if (!user) return;
    const userId = getGuideUserId();
    if (!force && hasSeenChallengeTutorial(userId)) {
      return;
    }
    const layer = getEl('challengeTutorialLayer');
    if (!layer) return;
    state.tutorialIndex = 0;
    renderChallengeTutorial();
    layer.style.display = 'flex';
  }

  function closeChallengeTutorial(markCompleted) {
    const layer = getEl('challengeTutorialLayer');
    if (layer) {
      layer.style.display = 'none';
    }
    if (markCompleted) {
      markSeenChallengeTutorial(getGuideUserId());
    }
  }

  function prevChallengeTutorialStep() {
    state.tutorialIndex = Math.max(0, state.tutorialIndex - 1);
    renderChallengeTutorial();
  }

  function nextChallengeTutorialStep() {
    if (state.tutorialIndex >= CHALLENGE_TUTORIAL_STEPS.length - 1) {
      closeChallengeTutorial(true);
      return;
    }
    state.tutorialIndex += 1;
    renderChallengeTutorial();
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

  window.toggleTeamModal = function (show) {
    if (show) {
      openChallengeModal().catch((error) => openSiteModal('Challenge Error', error.message || 'Failed to load challenge.'));
    } else {
      closeChallengeModal();
    }
  };

  window.closeChallengeCelebration = function () {
    const el = getEl('successOverlay');
    if (el) {
      el.style.display = 'none';
      el.classList.remove('is-open');
      el.setAttribute('aria-hidden', 'true');
    }
    if (isChallengePageMode()) {
      window.location.href = competitionHubUrl();
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

  function attachListeners() {
    const mount = getChallengeMount();
    if (mount) {
      mount.addEventListener('click', (event) => {
        if (event.target === mount) closeChallengeModal();
      });
    }
    const guideBtn = getEl('challengeGuideBtn');
    if (guideBtn) {
      guideBtn.addEventListener('click', () => openChallengeTutorial(true));
    }
    const tutorialLayer = getEl('challengeTutorialLayer');
    if (tutorialLayer) {
      tutorialLayer.addEventListener('click', (event) => {
        if (event.target === tutorialLayer) {
          closeChallengeTutorial(false);
        }
      });
    }
    const tutorialPrevBtn = getEl('challengeTutorialPrevBtn');
    if (tutorialPrevBtn) {
      tutorialPrevBtn.addEventListener('click', prevChallengeTutorialStep);
    }
    const tutorialNextBtn = getEl('challengeTutorialNextBtn');
    if (tutorialNextBtn) {
      tutorialNextBtn.addEventListener('click', nextChallengeTutorialStep);
    }
    const tutorialCloseBtn = getEl('challengeTutorialCloseBtn');
    if (tutorialCloseBtn) {
      tutorialCloseBtn.addEventListener('click', () => closeChallengeTutorial(false));
    }
  }

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
      if (isChallengePageMode()) return;
      if (params.get('challenge') === '1') {
        openChallengeModal().catch((error) => openSiteModal('Challenge Error', error.message || 'Failed to open challenge.'));
      }
    },
  };

  let isV2Style = false;

  function toggleChallengeStyle() {
    isV2Style = !isV2Style;
    const elements = document.querySelectorAll('#teamModalOverlay, #teamModal, .team-main, .challenge-hero, .challenge-copy, .challenge-secondary-btn, .challenge-ghost-btn, .challenge-secondary-btn__count, .challenge-name-row, .challenge-name-field, .challenge-name-input, .challenge-search-input, .challenge-request-input, .challenge-status-row, .challenge-status-chip, .challenge-stage, .challenge-card, .challenge-card--center, .challenge-card--stacked, .challenge-forming-head, .challenge-countdown-card, .challenge-countdown, .challenge-countdown__unit, .challenge-countdown__divider, .slots-grid, .slot-card, .slot-card--filled, .slot-card__avatar, .slot-card__badge, .slot-card__badge--captain, .slot-card__name, .slot-card__meta, .slot-empty, .slot-card__emptyLabel, .challenge-pending, .challenge-pending__list, .challenge-invite-list, .challenge-pending__item, .challenge-invite-card, .challenge-pending__avatar, .challenge-invite-card__avatar, .challenge-pending__content, .challenge-invite-card__content, .team-float-actions, .team-float-actions--wide, .btn-publish, .btn-publish__count, .btn-publish--secondary, .btn-publish--soft, .btn-publish--ghost, .challenge-tabbar, .challenge-tab, .challenge-hub-pane, .challenge-mylisting-card, .challenge-mylisting-card__copy, .leaderboard-aside, .leaderboard-empty, .challenge-system-card, .challenge-system-card__value, .search-layer, .search-layer__card, .search-layer__card--wide, .search-layer__copy, .search-layer__actions, .challenge-inline-btn, .challenge-inline-btn--ghost, .challenge-panel-title, .challenge-panel-error, .challenge-admin-note, .success-overlay, .module-label, .quote-text, .challenge-btn, .challenge-empty');
    
    elements.forEach(el => {
      if (isV2Style) {
        el.classList.add('v2-style');
      } else {
        el.classList.remove('v2-style');
      }
    });
    
    const toggleBtn = document.querySelector('.style-toggle-btn');
    if (toggleBtn) {
      toggleBtn.textContent = isV2Style ? 'Switch to V1' : 'Switch to V2';
    }
  }

  window.toggleChallengeStyle = toggleChallengeStyle;

  attachListeners();
  if (isStandaloneChallengePanel()) {
    if (document.body && document.body.dataset) {
      document.body.dataset.challengePage = '1';
    }
    loadState().catch(() => {
      renderState({
        phase: 'signup',
        signup: { isSignedUp: false },
        cycle: { label: 'Current Week', resetRule: 'Teams reset every Monday at 00:00.' },
        team: null,
        sentInvites: [],
        receivedInvites: [],
        publicListings: [],
        leaderboard: [],
      });
    });
  }
  {
    const u = (typeof authState !== 'undefined' && authState.user) ? authState.user : getAuthUser();
    if (u && String(u.role || '').toLowerCase() !== 'admin') {
      connectChallengeRealtime();
    }
  }
})();
