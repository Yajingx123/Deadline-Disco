/* Shared Technology page guards. GameUI pages should call this instead of duplicating logic. */
(function attachTechnologyPageGuards(globalObj) {
  function getAdminUrl() {
    var L = globalObj.ACADBEAT_LOCAL || {};
    return String(L.adminDistUrl || '').trim() || (globalObj.location.origin + '/admin_page/dist/index.html');
  }

  async function guardAdminPage(options) {
    var opts = options || {};
    var authUrl = String(opts.authMeUrl || '').trim() || '/Auth/backend/api/me.php';
    var guardClass = String(opts.guardClass || 'acadbeat-role-guard');
    var redirectAdmin = opts.redirectAdmin !== false;

    globalObj.document.documentElement.classList.add(guardClass);
    try {
      var response = await fetch(authUrl, { credentials: 'include' });
      var data = await response.json().catch(function () { return { status: 'error' }; });
      var role = String((data && data.user && data.user.role) || '').toLowerCase();
      if (redirectAdmin && data && data.status === 'success' && role === 'admin') {
        globalObj.location.replace(getAdminUrl());
        return false;
      }
      return true;
    } catch (_err) {
      return true;
    } finally {
      globalObj.document.documentElement.classList.remove(guardClass);
    }
  }

  globalObj.acadbeatGuardAdminPage = guardAdminPage;
})(window);
