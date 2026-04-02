/**
 * AcadBeat 本地开发环境 — 绝对 URL 唯一配置入口。
 * 经典模式（原版 UI）与 Godot 模式共用同一套业务与登录态，仅入口与部分换肤不同。
 * 部署到其他主机/端口时：改此文件 + 同步 Auth/backend/api/*.php 与 forum api 的 CORS。
 *
 * @see docs/ARCHITECTURE.md
 */
(function (g) {
  var MAIN = 'http://127.0.0.1:8001';
  g.ACADBEAT_LOCAL = {
    mainOrigin: MAIN,
    challengeApiUrl: MAIN + '/forum-project/api/challenge.php',
    /** 与 start_all_mac 中 voice-room-server 一致 */
    voiceRoomWsUrl: 'ws://127.0.0.1:3001/ws',
    adminDistUrl: MAIN + '/admin_page/dist/index.html',
    messageCenterDistUrl: MAIN + '/message-center-project/dist/index.html',
    messageSummaryApiUrl: MAIN + '/forum-project/api/message-center.php?summaryOnly=1',
    authMeUrl: MAIN + '/Auth/backend/api/me.php',
    /** 与 forum-project/vite.config.js 的 base 一致 */
    forumDevChooserUrl: 'http://127.0.0.1:5173/forum-project/dist/?view=chooser',
    forumProdIndexUrl: MAIN + '/forum-project/dist/index.html',
    godotWebEntryUrl: 'http://127.0.0.1:5500/index.html?ui=godot',
    /** Web 导出读 ?scene=academic 进入学术星球；听力页 Godot 模式返回用 */
    godotAcademicWebUrl: 'http://127.0.0.1:5500/index.html?ui=godot&scene=academic',
  };
})(typeof window !== 'undefined' ? window : globalThis);
