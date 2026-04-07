/**
 * Set default iframe URL for newUI shells.
 * Optional override: ?embed=<encodeURIComponent(url)>
 */
(function () {
  var frame = document.getElementById('abLegacyFrame');
  if (!frame) return;

  var params = new URLSearchParams(window.location.search);
  var embed = params.get('embed');
  if (embed) {
    try {
      frame.src = decodeURIComponent(embed);
    } catch (e) {
      frame.src = embed;
    }
    return;
  }

  var L = window.ACADBEAT_LOCAL || {};
  var origin = L.mainOrigin || '';
  var mod = (document.body && document.body.getAttribute('data-ab-module')) || 'common';

  function withUiGodot(pathOrUrl) {
    if (!pathOrUrl) return pathOrUrl;
    if (/^https?:\/\//i.test(pathOrUrl)) {
      var u;
      try {
        u = new URL(pathOrUrl);
      } catch (e) {
        return pathOrUrl;
      }
      u.searchParams.set('ui', 'godot');
      return u.toString();
    }
    var q = pathOrUrl.indexOf('?') >= 0 ? '&' : '?';
    return pathOrUrl + q + 'ui=godot';
  }

  if (mod === 'forum') {
    var forumBase = L.forumGodotShellIndexUrl || (origin ? origin + '/forum-project-v2/dist/index.html' : '/forum-project-v2/dist/index.html');
    frame.src = withUiGodot(forumBase);
    return;
  }

  var pathByModule = {
    academic: '/Academic-Practice/training.html',
    vocabulary: '/vocba_prac/',
    common: '/home.html',
  };
  var path = pathByModule[mod] || pathByModule.common;
  frame.src = origin ? origin + withUiGodot(path) : withUiGodot(path);
})();
