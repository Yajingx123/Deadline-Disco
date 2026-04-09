/* Shared Dundee back-link resolver for main Technology and GameUI Technology pages. */
(function attachDundeeRouting(globalObj) {
  function normalizePath(path) {
    return String(path || '').toLowerCase();
  }

  function resolveTechnologyPath(mode) {
    return mode === 'gameui' ? '/GameUI/Technology-GameUI/technology.html' : '/Technology/technology.html';
  }

  function resolveBackLink(options) {
    var opts = options || {};
    var backLinkId = String(opts.backLinkId || 'backLink');
    var mode = String(opts.mode || 'main').toLowerCase();
    var fallback = String(opts.fallback || resolveTechnologyPath(mode));

    var backLink = globalObj.document.getElementById(backLinkId);
    if (!backLink) return;

    var mainPath = '/Technology/technology.html';
    var gameUiPath = '/GameUI/Technology-GameUI/technology.html';
    var params = new URLSearchParams(globalObj.location.search || '');
    var from = String(params.get('from') || '').toLowerCase();

    if (from === 'technology1' || from === 'gameui' || from === 'technology-gameui') {
      backLink.href = gameUiPath;
      return;
    }
    if (from === 'technology' || from === 'main') {
      backLink.href = mainPath;
      return;
    }

    var referrer = globalObj.document.referrer
      ? new URL(globalObj.document.referrer, globalObj.location.href)
      : null;
    if (referrer && referrer.origin === globalObj.location.origin) {
      var refPath = normalizePath(referrer.pathname);
      if (refPath.endsWith('/technology1.html') || refPath.endsWith('/gameui/technology-gameui/technology.html')) {
        backLink.href = gameUiPath;
        return;
      }
      if (refPath.endsWith('/technology.html') || refPath.endsWith('/technology/technology.html')) {
        backLink.href = mainPath;
        return;
      }
    }

    backLink.href = fallback;
  }

  globalObj.acadbeatResolveDundeeBackLink = resolveBackLink;
})(window);
