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

  async function loadMessageSummary(messageApiUrl, user, badgeEl) {
    if (!badgeEl || !user) {
      if (badgeEl) {
        badgeEl.hidden = true;
        badgeEl.style.display = 'none';
        badgeEl.classList.remove('is-visible');
        badgeEl.textContent = '0';
      }
      return;
    }

    try {
      const response = await fetch(messageApiUrl, { credentials: 'include' });
      const data = await response.json().catch(() => ({ ok: false }));
      const totalUnread = Number(data?.summary?.totalUnread || 0);
      if (totalUnread >= 1) {
        badgeEl.hidden = false;
        badgeEl.style.display = 'inline-flex';
        badgeEl.classList.add('is-visible');
        badgeEl.textContent = totalUnread > 99 ? '99+' : String(totalUnread);
      } else {
        badgeEl.hidden = true;
        badgeEl.style.display = 'none';
        badgeEl.classList.remove('is-visible');
        badgeEl.textContent = '0';
      }
    } catch (_err) {
      badgeEl.hidden = true;
      badgeEl.style.display = 'none';
      badgeEl.classList.remove('is-visible');
      badgeEl.textContent = '0';
    }
  }

  window.initializeAcadBeatNav = async function initializeAcadBeatNav(options = {}) {
    const mount = document.getElementById(options.mountId || 'acadbeatNav');
    if (!mount) return null;

    const basePath = withSlashPrefix(options.basePath || './');
    const homeUrl = options.homeUrl || `${basePath}home.html`;
    const forumUrl = options.forumUrl || 'http://127.0.0.1:5173/?view=forum';
    const technologyUrl = options.technologyUrl || `${basePath}technology.html`;
    const authApiBase = options.authApiBase || `${basePath}Auth/backend/api`;
    const loginUrl = options.loginUrl || `${homeUrl}?login=1`;
    const ownerUrl = options.ownerUrl || `${basePath}owner.html`;
    const adminUrl = options.adminUrl || 'http://127.0.0.1:5174/';
    const messageCenterUrl = options.messageCenterUrl || 'http://127.0.0.1:5173/?view=messages';
    const messageApiUrl = options.messageApiUrl || 'http://127.0.0.1:8001/forum-project/api/message-center.php?summaryOnly=1';
    const active = String(options.active || '').toLowerCase();

    mount.innerHTML = `
      <nav class="acadbeat-shared-nav">
        <a class="logo" href="${homeUrl}">Acad<span>Beat</span></a>
        <div class="nav-menu">
          <a class="nav-item ${active === 'academic' ? 'active' : ''}" href="${homeUrl}?module=Insight">Academic</a>
          <a class="nav-item ${active === 'forum' || active === 'messages' ? 'active' : ''}" href="${homeUrl}?module=Dialogue">Forum</a>
          <a class="nav-item ${active === 'technology' ? 'active' : ''}" href="${homeUrl}?module=Method">Technology</a>
        </div>
        <div class="user-group">
          <a class="message-link" id="messageCenterLink" href="${messageCenterUrl}" aria-label="Open message center" hidden>
            ✉
            <span class="message-badge" id="messageBadge" hidden>0</span>
          </a>
          <div class="user-section" id="userSection">
            <span id="userLabel" class="user-label">LOGIN</span>
            <div class="avatar" id="userAvatar">IN</div>
          </div>
          <a href="#" class="logout-link" id="logoutLink" style="display:none;">Log out</a>
        </div>
      </nav>
    `;

    const userLabel = mount.querySelector('#userLabel');
    const userAvatar = mount.querySelector('#userAvatar');
    const userSection = mount.querySelector('#userSection');
    const logoutLink = mount.querySelector('#logoutLink');
    const messageLink = mount.querySelector('#messageCenterLink');
    const messageBadge = mount.querySelector('#messageBadge');

    let authUser = null;

    function getPortalUrl(user) {
      return user && String(user.role || '').toLowerCase() === 'admin' ? adminUrl : ownerUrl;
    }

    function renderAuthUI() {
      if (!userLabel || !userAvatar || !logoutLink || !messageLink || !messageBadge) return;
      if (authUser) {
        userLabel.textContent = authUser.username;
        userAvatar.textContent = initialsOf(authUser.username);
        logoutLink.style.display = 'inline-flex';
        messageLink.hidden = false;
      } else {
        userLabel.textContent = 'LOGIN';
        userAvatar.textContent = 'IN';
        logoutLink.style.display = 'none';
        messageLink.hidden = true;
        messageBadge.hidden = true;
        messageBadge.style.display = 'none';
        messageBadge.classList.remove('is-visible');
        messageBadge.textContent = '0';
      }
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
    window.acadbeatNavState = { user: authUser };
    window.dispatchEvent(new CustomEvent('acadbeat:nav-user', { detail: { user: authUser } }));
    renderAuthUI();
    await loadMessageSummary(messageApiUrl, authUser, messageBadge);

    window.addEventListener('acadbeat:message-summary', (event) => {
      const totalUnread = Number(event?.detail?.totalUnread || 0);
      if (!messageBadge || !authUser) return;
      if (totalUnread >= 1) {
        messageBadge.hidden = false;
        messageBadge.style.display = 'inline-flex';
        messageBadge.classList.add('is-visible');
        messageBadge.textContent = totalUnread > 99 ? '99+' : String(totalUnread);
      } else {
        messageBadge.hidden = true;
        messageBadge.style.display = 'none';
        messageBadge.classList.remove('is-visible');
        messageBadge.textContent = '0';
      }
    });

    return authUser;
  };
})();
