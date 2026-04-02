# AcadBeat 仓库架构（双 UI 模式）

## 核心结论

- **一个项目、一套账号与数据**：登录后 Session 在 `Auth/backend`，各模块读同一用户。
- **经典模式（原版）**：以 `home.html` 为浏览器入口，必须保证**所有模块可完整跑通**（验收标准）。
- **Godot 模式**：同一业务链路的**换壳入口**（`5500` Web 导出 + `ui_mode=godot` / `ui=godot`）。部分页面可仍用经典样式或尚未接 newUI，**允许未全部接完**。
- **结构整理路线**：见 `docs/REPO_REORG_PLAN.md`（仅做低风险、可回滚的分阶段重组）。

## 目录职责

| 路径 | 职责 |
|------|------|
| `Auth/` | 登录注册、Session、与 PHP 主站同域 Cookie |
| `home.html` / `owner.html` / `technology.html` | 经典主页、个人页、技术页 |
| `Academic-Practice/` | 学术听力、口语、语音房等（`practice-app.js` 驱动多页） |
| `vocba_prac/` | 词表练习 |
| `forum-project/` | 论坛前端（Vite）；开发 `5173/forum-project/dist/` |
| `admin_page/` | 管理端构建产物 + 开发服务 |
| `message-center-project/` | 消息中心前端 |
| `newUI/static/` | Godot/经典共用的**补充样式与静态片段**（如像素风 CSS） |
| `gameUI_src/` | Godot 工程；Web 玩法则导出到 `gameUI_src/Release` 并由 `serve.py` 提供 |
| `shared/` | **跨页面共享**：`acadbeat-local-config.js`（本地绝对 URL 单一真相） |
| `shared-nav.js` / `shared-nav.css`（仓库根） | 顶栏导航组件；在引入 `shared/acadbeat-local-config.js` 后会自动使用其中的管理端/消息中心/摘要 API URL（未引入时仍有内置回退） |
| `docs/` | 架构与约定（本文件） |

## 本地 URL 单一配置

- 文件：**`shared/acadbeat-local-config.js`**
- 暴露全局 **`window.ACADBEAT_LOCAL`**（`adminDistUrl`、`forumDevChooserUrl`、`godotWebEntryUrl` 等）。
- 各 HTML 在 **admin 角色守卫**与 `initializeAcadBeatNav` 之前引入该脚本，避免散落硬编码。
- **PHP / Node** 侧 CORS 与重定向（如 `Auth/backend/api/login.php`、`forum-project/api/bootstrap.php`）需与此处 **同源策略一致**；改端口时两边一起改。

## 双模式如何切换

- 主页 **Switch**：写入 `ui_mode=godot` 并进入 `godotWebEntryUrl`。
- 学术等子页通过 URL 参数 **`ui=godot`** 或 Cookie **`ui_mode=godot`** 决定：是否切 `newUI/static` 样式、未登录时是否回 Godot 入口等。
- Godot 内门/星球上的外链应与 `ACADBEAT_LOCAL` **保持同一套地址**（Godot 无法读 JS 配置，改配置后请同步 `gameUI_src` 内常量或重新导出）。

## 启动

- `php start_all.php`：按 OS 拉起主站 `8001`、论坛 Vite、管理端、Godot 静态等；详见脚本输出。
- **Windows** 与 **macOS** 的 `start_all` 均会尝试启动 **`voice-room-server`（`3001`）**；论坛实时/私信摘要等依赖该 WebSocket。若未在 `voice-room-server` 目录执行过 `npm install`，该进程可能立即退出，请在该目录安装依赖后重试。
- 论坛开发地址必须为 **`/forum-project/dist/`** 子路径（与 Vite `base` 一致）。
- 论坛 / 消息中心 Vite 开发服将 **`/shared/*` 代理到 `http://127.0.0.1:8001`**，以便加载 `shared/acadbeat-local-config.js`；**主站 `8001` 必须先启动**，否则该脚本与 API 都会失败。
- Vite 子项目执行 `npm run build` 后，`dist/index.html` 会从源 `index.html` 重新生成；若曾手改 `dist`，应以源文件为准并重新构建。

## 经典模式完整跑通 — 模块清单（验收）

在**未**开 Godot、已登录普通用户前提下，应能从顶栏或主页进入：

1. Academic → `Academic-Practice/training.html` → 各子功能（听力选视频、练习、语音房等）
2. Forum → 开发环境 `forumDevChooserUrl`；或主站静态 `forumProdIndexUrl`
3. Technology → `technology.html`
4. 词表 → `vocba_prac/`
5. 个人页 → `owner.html`
6. 消息中心 → `messageCenterDistUrl`（依赖登录）

管理员应被重定向到 **`adminDistUrl`**，且不进入普通学术/论坛学生流（由各页 `acadbeat-role-guard` 与 nav 逻辑处理）。

## Godot 模式（非阻塞）

- 入口：`godotWebEntryUrl`。
- 允许：部分门/星球目标页仍为经典 HTML、或 newUI 样式未覆盖；以「不阻断主流程」为准。
- 长期目标：门/星球 URL 与 `ACADBEAT_LOCAL` 对齐，减少重复常量。

## 其它字面量 URL（改环境时请手查）

- `Academic-Practice/practice-app.js` 内 `CHAT_API_BASE`、`FORUM_COMPOSE_URL` 等仍为固定字符串；若更换主站端口，需与此处及 PHP CORS 一并修改（或后续再抽到构建期配置）。
