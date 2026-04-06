/**
 * 为 newUI/shell/*/shell.html 设置 iframe 默认地址：与原版同一主站，仅换肤参数。
 * - ?embed=<encodeURIComponent(url)> 强制嵌入任意同源/可嵌页面
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
    var forumBase = L.forumProdIndexUrl || (origin ? origin + '/forum-project/dist/index.html' : '/forum-project/dist/index.html');
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
