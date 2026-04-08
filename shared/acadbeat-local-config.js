/**
 * AcadBeat 本地开发环境 — 绝对 URL 唯一配置入口。
 * 经典模式（原版 UI）与 Godot 模式共用同一套业务与登录态，仅入口与部分换肤不同。
 * 部署到其他主机/端口时：改此文件 + 同步 Auth/backend/api/*.php 与 forum api 的 CORS。
 *
 * @see docs/ARCHITECTURE.md
 */
(function (g) {
  var isBrowser = typeof window !== 'undefined' && window.location;
  var host = isBrowser ? window.location.hostname : '127.0.0.1';
  var isLocalHost = host === '127.0.0.1' || host === 'localhost';
  var origin = isBrowser ? window.location.origin : 'http://127.0.0.1:8001';
  var fallbackLocalMain = 'http://127.0.0.1:8001';
  var MAIN = origin;
  if (isLocalHost) {
    var savedMain = '';
    try {
      if (isBrowser && window.localStorage) {
        savedMain = String(window.localStorage.getItem('acadbeat_main_origin') || '').trim();
      }
    } catch (_err) {
      savedMain = '';
    }
    var currentPort = isBrowser ? String(window.location.port || '') : '';
    // If we are on business pages (not Godot shell), use current origin as main and persist it.
    if (isBrowser && currentPort !== '5500') {
      MAIN = origin;
      try {
        if (window.localStorage) {
          window.localStorage.setItem('acadbeat_main_origin', MAIN);
        }
      } catch (_err) {}
    } else if (savedMain && /^https?:\/\//i.test(savedMain)) {
      MAIN = savedMain;
    } else {
      MAIN = fallbackLocalMain;
    }
  }
  var wsProtocol = isBrowser && window.location.protocol === 'https:' ? 'wss:' : 'ws:';
  var wsHost = isBrowser ? window.location.host : '127.0.0.1:3001';
  var sameOriginWs = wsProtocol + '//' + wsHost + '/ws';
  var localAdminDistUrl = MAIN + '/admin_page/index.html';
  g.ACADBEAT_LOCAL = {
    mainOrigin: MAIN,
    technologyUrl: MAIN + '/technology.html',
    technologyGodotUrl: MAIN + '/technology1.html',
    challengeApiUrl: MAIN + '/challenge/api/challenge.php',
    /** Production should use same-origin /ws via Nginx reverse proxy. */
    voiceRoomWsUrl: isLocalHost ? 'ws://127.0.0.1:3001/ws' : sameOriginWs,
    // Default to PHP-served entries so pages open even when Vite build/dist is unavailable.
    adminDistUrl: isLocalHost ? localAdminDistUrl : MAIN + '/admin_page/dist/index.html',
    messageCenterDistUrl: MAIN + '/message-center-project/index.html',
    messageSummaryApiUrl: MAIN + '/forum-project/api/message-center.php?summaryOnly=1',
    authMeUrl: MAIN + '/Auth/backend/api/me.php',
    /** 经典 UI（home 等）固定使用 forum-project，避免与 v2 混用 */
    forumDevChooserUrl: MAIN + '/forum-project/index.html?view=forum',
    forumClassicIndexUrl: MAIN + '/forum-project/index.html',
    /** 兼容旧键名：仍指向经典论坛 */
    forumProdIndexUrl: MAIN + '/forum-project/dist/index.html',
    /** Godot/newUI 网页壳固定使用 forum-project-v2（仅壳内 iframe） */
    forumGodotShellIndexUrl: MAIN + '/forum-project-v2/dist/index.html',
    godotWebEntryUrl: isLocalHost ? 'http://127.0.0.1:5500/index.html?ui=godot' : MAIN + '/gameUI_src/Release/index.html?ui=godot',
    /** Web 导出读 ?scene=academic 进入学术星球；听力页 Godot 模式返回用 */
    godotAcademicWebUrl: isLocalHost ? 'http://127.0.0.1:5500/index.html?ui=godot&scene=academic' : MAIN + '/gameUI_src/Release/index.html?ui=godot&scene=academic',
  };
})(typeof window !== 'undefined' ? window : globalThis);
