# Academic-Practice Zego Integration Plan

## Decision

第 3 个小任务的结论不是二选一。

最稳的方案是：

1. 保留 `Deadline-Disco7` 里完整可用的“随机 1v1 视频匹配链路”。
2. 裁剪掉 `peer-resonance` 里和邀请、组队、打卡、双人 streak 相关的业务。
3. 把视频功能整体收口到 `Academic-Practice` 模块下面，不把旧的 `peer-resonance` 目录整包搬进来。

这意味着不能只迁移 `zego-call.php`，也没必要把整套 resonance 业务一起迁入。

## Why This Path

只迁页面不可行，因为当前链路依赖以下服务端能力：

- 登录态校验
- 排队与心跳续期
- 匹配后建房
- 房间状态轮询
- 离开与回收会话
- ZEGO token 生成

整包迁移 `peer-resonance` 也不合适，因为视频随机匹配真正硬依赖的只有“房间容器 + 视频会话”这一小层，`peer_resonance_teams`、`peer_space_invites`、`peer_resonance_daily_logs` 都不是 Academic-Practice 视频功能的必要前置。

## Recommended Target Layout

建议把目标结构固定为：

- `Academic-Practice/video-match.php`
- `Academic-Practice/zego-call.php`
- `Academic-Practice/api/video/bootstrap.php`
- `Academic-Practice/api/video/video-helpers.php`
- `Academic-Practice/api/video/video-match-join.php`
- `Academic-Practice/api/video/video-match-status.php`
- `Academic-Practice/api/video/video-match-room.php`
- `Academic-Practice/api/video/video-match-leave.php`
- `Academic-Practice/api/video/video-match-cancel.php`
- `Academic-Practice/api/video/zego-token.php`
- `Academic-Practice/api/video/zego-config.php`
- `zego_server_assistant/src/ZEGO/ZegoAssistantToken.php`
- `zego_server_assistant/src/ZEGO/ZegoErrorCodes.php`
- `zego_server_assistant/src/ZEGO/ZegoServerAssistant.php`
- `105_academic_practice_video_match_tables.sql`

这里的原则是：

- 前台页面放在 `Academic-Practice`
- 视频 API 放在 `Academic-Practice/api/video`
- 第三方 ZEGO PHP 库继续放仓库根目录，减少 include 路径改动
- SQL 单独出一份，不混进 listening/respond 现有表结构

## Reuse Strategy

当前仓库已有可直接复用的基础设施：

- `Academic-Practice/api/_bootstrap.php`
- `Auth/backend/config/config.php`
- `Auth/backend/api/me.php`
- `$_SESSION['auth_user']`

因此不建议照搬 `Deadline-Disco7/config/runtime.php` 到当前仓库根目录。

更稳的做法是：

1. `Academic-Practice/api/video/bootstrap.php` 基于 `Academic-Practice/api/_bootstrap.php` 扩展。
2. 把 `peer_json()`、`peer_input()`、`peer_require_user()` 这类通用能力改成直接复用现有 bootstrap 风格。
3. 把 ZEGO 相关配置收敛到 `Academic-Practice/api/video/zego-config.php`，不要额外引入新的全局 runtime 配置层。

## Database Boundary

建议保留两类表，裁掉 resonance 专属表。

必须迁移：

- `peer_spaces`
- `peer_space_members`
- `peer_video_match_settings`
- `peer_video_sessions`
- `peer_video_match_queue`
- `peer_video_session_events`

可以裁掉：

- `peer_space_invites`
- `peer_resonance_teams`
- `peer_resonance_daily_logs`

原因很明确：

- `video-helpers.php` 在建房和离房时确实用到了 `peer_spaces` / `peer_space_members`
- 但视频随机匹配流程没有依赖 invite/team/streak 逻辑

所以第 4 步迁移时，不要直接执行 `103_peer_resonance_tables.sql` 全量版本，而是应该从里面抽出通用房间表，和 `104_video_match_tables.sql` 合并成新的最小 SQL 文件。

## Required Code Changes

### 1. `video-match.php`

这个页面基本可以原样迁移，但要改三类路径：

- API 地址从 `./peer-resonance/api/...` 改到 `./api/video/...`
- 返回入口保持 `Academic-Practice/training.html`
- 不再依赖 `owner.html` 作为主要回退入口

这页应继续承担：

- join queue
- poll status
- cancel queue
- open room
- leave session

### 2. `zego-call.php`

这个文件不能原样复制，必须做一次定向裁剪。

当前 `Deadline-Disco7/zego-call.php` 同时支持两种上下文：

- random match
- resonance team

迁入当前仓库时只保留 `random_match` 分支，删除或禁用以下旧耦合：

- `PEER_STATUS_URL = './peer-resonance/api/status.php?...'`
- `resolveResonanceRoomId(...)`
- `activeTeam` 分支
- 回退到 `owner.html` 的 resonance 入口文案

推荐改法不是继续保留 `status.php`，而是把房间页直接建立在以下两个接口之上：

- `../Auth/backend/api/me.php`
- `./api/video/video-match-status.php?touch=0`

这样能把 `zego-call.php` 的数据来源收缩为“当前用户 + 当前视频会话”，不再依赖整个 `peer-resonance` 状态聚合器。

### 3. `video-helpers.php`

这个文件应保留随机匹配核心流程：

- `peer_video_join_queue()`
- `peer_video_fetch_state()`
- `peer_video_get_room()`
- `peer_video_leave_session()`
- `peer_video_cancel_queue()`

但应删除或避免继续引入以下内容：

- `peer_build_state()`
- `peer_find_active_team_for_user()`
- `peer_find_pending_invite_between()`
- streak 计算相关函数

换句话说，当前仓库里需要的是“video-only helpers”，不是“peer-resonance super helpers”。

## ZEGO Config Strategy

建议配置收口为 `Academic-Practice/api/video/zego-config.php`。

它至少需要输出：

- `app_id`
- `server_secret`
- `test_secret`
- `token_mode`
- `token_endpoint`
- `room_prefix`
- `project_name`
- `branding_logo_url`

其中：

- 生产 token 模式依赖 `ZEGO_SERVER_SECRET`
- 测试 token 模式可以回退到 `test_secret`
- 前端仍然通过远程脚本加载 `https://unpkg.com/@zegocloud/zego-uikit-prebuilt/zego-uikit-prebuilt.js`

这部分是运行前置条件，不属于可裁剪范围。

## Entry Integration Inside Academic-Practice

最自然的入口不是新开一级导航，而是接管现有 `Mode 04`。

具体建议：

1. 保留 `Academic-Practice/training.html` 的卡片结构。
2. 将 `Pair Speaking Room` 卡片从 `voice_room.html` 改到 `video-match.php`。
3. 把当前 `voice_room.html` / `voice-room.js` 视为静态原型，不作为真实视频入口。

这样用户路径会变成：

`training.html` -> `video-match.php` -> `zego-call.php`

## Migration Order For Task 4

第 4 个小任务建议严格按下面顺序做：

1. 先补 SQL，保证最小表结构可落库。
2. 再迁移 `Academic-Practice/api/video/*` 和 `zego_server_assistant/*`。
3. 然后迁移 `Academic-Practice/video-match.php`。
4. 最后裁剪并迁移 `Academic-Practice/zego-call.php`。
5. 再把 `training.html` 的 `Mode 04` 入口切过去。

这个顺序的好处是：

- 页面接入前，服务端已具备完整链路
- `zego-call.php` 的裁剪范围清晰
- 调试时可以先测接口，再测页面，再测实际进房

## Known Gaps To Validate Later

第 5 个小任务需要重点校验这些点：

- 当前库里是否已存在 `users` 表且字段名兼容 `user_id` / `username`
- PHP session 在 `Academic-Practice` 页面和 `Auth/backend/api/me.php` 之间是否共享
- `ZEGO_SERVER_SECRET` 是否已经就位，还是只能先走 test token
- 远程加载 ZEGO UI SDK 时，本地网络环境是否允许访问 `unpkg`
- `video-match-leave.php` 的 `keepalive/sendBeacon` 在当前浏览器环境是否稳定

## Final Recommendation

推荐执行路径可以概括成一句话：

把 `Deadline-Disco7` 的“随机视频匹配”作为一个独立 vertical feature 迁到 `Academic-Practice`，保留完整匹配链路，裁掉 resonance 团队业务，不做只迁页面的假迁移，也不做整包搬运。
