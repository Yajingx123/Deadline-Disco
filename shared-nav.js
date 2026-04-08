(function () {
  function initialsOf(name) {
    const trimmed = String(name || '').trim();
    if (!trimmed) return 'IN';
    return trimmed.slice(0, 2).toUpperCase();
  }

  function withSlashPrefix(path) {
    return path.endsWith('/') ? path : `${path}`;
  }

  async function authFetch(authApiBase, path, options = {}) {
    const response = await fetch(`${authApiBase}/${path}`, {
      method: options.method || 'GET',
      headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
      credentials: 'include',
      body: options.body || undefined
    });
    return response.json().catch(() => ({ status: 'error', message: 'Invalid response' }));
  }

  function releaseRoleGuard() {
    document.documentElement.classList.remove('acadbeat-role-guard');
  }

  function loginUrlWithReturn(loginUrl, returnTo) {
    try {
      const u = new URL(loginUrl, window.location.href);
      u.searchParams.set('from', String(returnTo || '').trim() || window.location.href.split('#')[0]);
      return u.toString();
    } catch (_err) {
      return loginUrl;
    }
  }

  function applyNavActive(navRoot, activeKey) {
    if (!navRoot) return;
    const key = String(activeKey || '').toLowerCase();
    navRoot.querySelectorAll('.nav-item[data-nav]').forEach((el) => {
      el.classList.remove('active');
    });
    navRoot.querySelectorAll('.nav-item[data-nav]').forEach((el) => {
      const itemKey = el.getAttribute('data-nav');
      const on = key === itemKey;
      if (on) el.classList.add('active');
    });
  }

  window.setAcadBeatNavActive = function setAcadBeatNavActive(activeKey) {
    const navRoot =
      document.querySelector('#acadbeatNav .acadbeat-shared-nav') ||
      document.querySelector('.acadbeat-shared-nav');
    applyNavActive(navRoot, activeKey);
  };

  window.initializeAcadBeatNav = async function initializeAcadBeatNav(options = {}) {
    const mount = document.getElementById(options.mountId || 'acadbeatNav');
    if (!mount) return null;

    if (typeof mount.__acadbeatCleanup === 'function') {
      mount.__acadbeatCleanup();
      mount.__acadbeatCleanup = null;
    }

    const basePath = withSlashPrefix(options.basePath || './');
    const homeUrl = options.homeUrl || `${basePath}home.html`;
    const academicUrl = options.academicUrl || `${basePath}academic-gate.html`;
    const forumUrl = options.forumUrl || `${basePath}forum-gate.html`;
    const studioUrl = options.studioUrl || `${basePath}studio-gate.html`;
    const socialUrl = options.socialUrl || `${basePath}social-gate.html`;
    const competitionUrl = options.competitionUrl || options.rankUrl || `${basePath}competition-gate.html`;
    const competitionHubUrl = options.competitionHubUrl || competitionUrl.split('?')[0];
    const competitionRankingSubUrl = options.competitionRankingSubUrl
      || `${competitionUrl}${competitionUrl.includes('?') ? '&' : '?'}highlight=ranking`;
    const competitionChallengeSubUrl = options.competitionChallengeSubUrl
      || `${competitionUrl}${competitionUrl.includes('?') ? '&' : '?'}highlight=challenge`;
    const competitionRankingDirectUrl = options.competitionRankingDirectUrl || `${basePath}rank/index.html`;
    const challengeHomeUrl = options.challengeHomeUrl || homeUrl;
    const competitionChallengeDirectUrl = options.competitionChallengeDirectUrl
      || `${basePath}rank/index.html?challenge=1`;
    const authApiBase = options.authApiBase || `${basePath}Auth/backend/api`;
    const loginUrl = options.loginUrl || `${homeUrl}?login=1`;
    const ownerUrl = options.ownerUrl || `${basePath}owner.html`;
    const L = typeof window !== 'undefined' && window.ACADBEAT_LOCAL ? window.ACADBEAT_LOCAL : null;
    const technologyUrl = options.technologyUrl || (L && L.technologyUrl) || `${basePath}technology-gate.html`;
    const academicTrainingUrl = options.academicTrainingUrl || `${basePath}Academic-Practice/training.html`;
    const academicListeningUrl = options.academicListeningUrl || `${basePath}Academic-Practice/listening.html`;
    const academicRespondUrl = options.academicRespondUrl || `${basePath}Academic-Practice/respond_training.html`;
    const academicNoteUrl = options.academicNoteUrl || `${basePath}Academic-Practice/note_training.html`;
    const currentOrigin = (typeof window !== 'undefined' && window.location) ? window.location.origin : 'http://127.0.0.1:8001';
    const technologyTeamsUrl = options.technologyTeamsUrl || 'https://support.microsoft.com/en-us/teams';
    const technologyGithubUrl = options.technologyGithubUrl || 'https://docs.github.com/en/get-started';
    const technologyMatlabUrl = options.technologyMatlabUrl || 'https://matlabacademy.mathworks.com/';
    const technologyAutocadUrl = options.technologyAutocadUrl || 'https://help.autodesk.com/view/ACD/2024/ENU/';
    const technologyOriginlabUrl = options.technologyOriginlabUrl || 'https://www.originlab.com/index.aspx?go=Support/VideoTutorials';
    const technologyDundeeUrl = options.technologyDundeeUrl || `${basePath}dundee.html?from=technology`;
    const studioScrabbleUrl = options.studioScrabbleUrl || `${basePath}Studio/Scrabble/scrabble.html`;
    const studio2dUrl = options.studio2dUrl || ((L && L.godotWebEntryUrl) ? L.godotWebEntryUrl : `${basePath}gameUI_src/Release/index.html?ui=godot`);
    const adminUrl = options.adminUrl || (L && L.adminDistUrl) || `${currentOrigin}/admin_page/dist/index.html`;
    const active = String(options.active || '').toLowerCase();
    const showSwitchButton = options.showSwitchButton !== undefined
      ? Boolean(options.showSwitchButton)
      : active === 'academic'; // 默认只在 academic 页面显示切换按钮
    const redirectAdmins = Boolean(options.redirectAdmins);
    const requireLogin = Boolean(options.requireLogin);
    const loginReturnUrl = String(options.loginReturnUrl || '').trim();

    if (requireLogin) {
      document.documentElement.classList.add('acadbeat-login-guard');
    }

    mount.innerHTML = `
      <nav class="acadbeat-shared-nav">
        <a class="logo" href="${homeUrl}">Acad<span>Beat</span><span class="logo-heart" aria-hidden="true">🫀</span></a>
        <div class="nav-menu">
          <div class="nav-dropdown">
            <a class="nav-item" data-nav="academic" href="${academicUrl}">Academic</a>
            <div class="nav-submenu">
              <a class="nav-subitem" href="${academicTrainingUrl}">Vocabulary</a>
              <a class="nav-subitem" href="${academicListeningUrl}">Understand</a>
              <a class="nav-subitem" href="${academicRespondUrl}">Respond</a>
              <a class="nav-subitem" href="${academicNoteUrl}">Room</a>
            </div>
          </div>
          <a class="nav-item" data-nav="forum" href="${forumUrl}">Forum</a>
          <div class="nav-dropdown">
            <a class="nav-item" data-nav="technology" href="${technologyUrl}">Technology</a>
            <div class="nav-submenu">
              <a class="nav-subitem" href="${technologyTeamsUrl}" target="_blank" rel="noopener noreferrer">Teams</a>
              <a class="nav-subitem" href="${technologyGithubUrl}" target="_blank" rel="noopener noreferrer">Github</a>
              <a class="nav-subitem" href="${technologyMatlabUrl}" target="_blank" rel="noopener noreferrer">Matlab</a>
              <a class="nav-subitem" href="${technologyAutocadUrl}" target="_blank" rel="noopener noreferrer">AutoCAD</a>
              <a class="nav-subitem" href="${technologyOriginlabUrl}" target="_blank" rel="noopener noreferrer">Originlab</a>
              <a class="nav-subitem" href="${technologyDundeeUrl}">My Dundee</a>
            </div>
          </div>
          <div class="nav-dropdown">
            <a class="nav-item" data-nav="studio" href="${studioUrl}">Studio</a>
            <div class="nav-submenu">
              <a class="nav-subitem" href="${studioScrabbleUrl}">Scrabble</a>
              <a class="nav-subitem nav-subitem--disabled" href="javascript:void(0)" aria-disabled="true" tabindex="-1">2D</a>
            </div>
          </div>
          <a class="nav-item" data-nav="social" href="${socialUrl}">Social</a>
          <div class="nav-dropdown">
            <a class="nav-item" data-nav="competition" href="${competitionHubUrl}">Competition</a>
            <div class="nav-submenu nav-submenu--competition">
              <a class="nav-subitem nav-subitem--muted" href="${competitionHubUrl}">Hub overview</a>
              <div class="nav-submenu-divider" role="presentation"></div>
              <a class="nav-subitem" href="${competitionRankingDirectUrl}">Team ranking</a>
              <a class="nav-subitem" href="${competitionChallengeDirectUrl}">Weekly challenge</a>
            </div>
          </div>
        </div>
        <div class="user-group">
          ${showSwitchButton ? `<button type="button" class="nav-godot" id="homeGodotSwitchBtn">SWITCH</button>` : ''}
          <div class="user-section" id="userSection">
            <span id="userLabel" class="user-label">LOGIN</span>
            <div class="avatar" id="userAvatar">IN</div>
          </div>
          <a href="#" class="logout-link" id="logoutLink" style="display:none;">Log out</a>
        </div>
      </nav>
    `;

    applyNavActive(mount.querySelector('.acadbeat-shared-nav'), active);

    const userLabel = mount.querySelector('#userLabel');
    const userAvatar = mount.querySelector('#userAvatar');
    const userSection = mount.querySelector('#userSection');
    const logoutLink = mount.querySelector('#logoutLink');
    const switchButton = mount.querySelector('#homeGodotSwitchBtn');

    let authUser = null;

    function getPortalUrl(user) {
      return user && String(user.role || '').toLowerCase() === 'admin' ? adminUrl : ownerUrl;
    }

    function renderAuthUI() {
      if (!userLabel || !userAvatar || !logoutLink) return;
      if (authUser) {
        userLabel.textContent = authUser.username;
        userAvatar.textContent = initialsOf(authUser.username);
        logoutLink.style.display = 'inline-flex';
      } else {
        userLabel.textContent = 'LOGIN';
        userAvatar.textContent = 'IN';
        logoutLink.style.display = 'none';
      }
      if (switchButton) {
        switchButton.hidden = !authUser;
      }
    }

    if (switchButton) {
      switchButton.addEventListener('click', () => {
        if (authUser) {
          const L = window.ACADBEAT_LOCAL || {};
          window.location.href = L.godotWebEntryUrl || 'http://127.0.0.1:5500/index.html?ui=godot';
        } else {
          window.location.href = loginUrl;
        }
      });
    }

    userSection?.addEventListener('click', () => {
      if (authUser) {
        window.location.href = getPortalUrl(authUser);
        return;
      }
      window.location.href = loginUrl;
    });

    logoutLink?.addEventListener('click', async (event) => {
      event.preventDefault();
      if (!authUser) return false;
      await authFetch(authApiBase, 'logout.php', { method: 'POST' });
      authUser = null;
      renderAuthUI();
      window.location.href = homeUrl;
      return false;
    });

    const data = await authFetch(authApiBase, 'me.php');
    authUser = data.status === 'success' ? data.user : null;
    if (redirectAdmins && authUser && String(authUser.role || '').toLowerCase() === 'admin') {
      if (requireLogin) {
        document.documentElement.classList.remove('acadbeat-login-guard');
      }
      window.location.replace(adminUrl);
      return authUser;
    }
    if (requireLogin && !authUser) {
      document.documentElement.classList.remove('acadbeat-login-guard');
      const ret = loginReturnUrl || window.location.href.split('#')[0];
      window.location.replace(loginUrlWithReturn(loginUrl, ret));
      return null;
    }
    window.acadbeatNavState = { user: authUser };
    window.dispatchEvent(new CustomEvent('acadbeat:nav-user', { detail: { user: authUser } }));
    renderAuthUI();

    releaseRoleGuard();

    if (requireLogin) {
      document.documentElement.classList.remove('acadbeat-login-guard');
    }

    window.dispatchEvent(new CustomEvent('acadbeat:nav-mounted', { detail: { active } }));

    mount.__acadbeatCleanup = function cleanupAcadbeatNav() {
      return;
    };

    return authUser;
  };
})();
