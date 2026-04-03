# Studio / Scrabble 模块说明

## 1. 改动是否只在本文件夹内？

**与本模块（Studio 游戏区 + Scrabble 联机）相关的功能代码，均位于 `Studio/` 目录下**，主要包括：

- `studio.html` — Studio 入口页（卡片进入 Scrabble）
- `Scrabble/scrabble.html` — 游戏页面（人机 + 在线匹配）
- `Scrabble/enable.txt` — 词典（ENABLE 词表）
- `Scrabble/fetch-words.ps1` — 可选辅助脚本
- `Scrabble/match-server/` — Node.js + Socket.IO 匹配与权威对局服务

**不包含**仓库其它部分（如 `home.html`、`forum-project`、`vocba_prac` 等），除非你在别处单独改过导航链接。

> **说明：** 若你在本机还在项目根目录放过「解压 zip 用的辅助脚本」（例如 `.tools/`），那些**不属于** `Studio/`，也不会出现在仅拷贝 `Studio` 文件夹的场景里。  
> 小组下发的 **4 月 zip 若原本没有 `Studio/`**，需要从带 `Studio` 的版本同步；本说明对应的树以 `C:\DdUnpack\Deadline-Disco\Studio` 为准。

---

## 2. 相对「仅人机版」的主要改动

| 内容 | 说明 |
|------|------|
| 开局模式 | 进入页面后可选 **Play vs AI** 或 **Online match** |
| 在线匹配 | 通过 Socket.IO 连接 `match-server`，队列中两人配对进同一房间 |
| 服务端校验 | 落子、 pass、换牌由服务器用与前端一致的规则与词典判定 |
| 状态同步 | 客户端接收 `game:state`：己方完整 rack，对手仅显示数量（`?`） |
| 断线/离开 | 一方离开或断线，房间销毁并通知另一方返回菜单 |

前端默认匹配服地址：`http://127.0.0.1:9000`（可在页面 URL 加参数覆盖，见下文）。

---

## 3. 目录结构（简要）

```
Studio/
  README-SCRABBLE.md          ← 本说明
  studio.html                 ← Studio 入口
  Scrabble/
    scrabble.html
    enable.txt
    fetch-words.ps1
    match-server/
      package.json
      server.js               ← HTTP + Socket.IO
      gameEngine.cjs          ← 与棋盘/计分相关的服务端逻辑
      .gitignore              ← 忽略 node_modules
```

---

## 4. 如何安装并启动匹配服（联机必填）

在终端中执行（路径按你实际解压位置调整）：

```powershell
cd C:\DdUnpack\Deadline-Disco\Studio\Scrabble\match-server
npm install
npm start
```

成功时控制台会类似：

- `Dictionary loaded: … words`
- `Scrabble server http://127.0.0.1:9000 (Socket.IO)`

**可选环境变量：**

| 变量 | 含义 |
|------|------|
| `SCRABBLE_PORT` | 监听端口，默认 `9000` |
| `SCRABBLE_DICT` | 词典文件路径；不设则使用上一级 `Scrabble/enable.txt` |

---

## 5. 如何打开游戏页面并测试

### 5.1 推荐：用本地 HTTP 打开（避免 `file://` 下词典加载失败）

在项目根目录（含 `Studio` 的那一层）启动 PHP 内置服务器，例如：

```powershell
cd C:\DdUnpack\Deadline-Disco
php -S 127.0.0.1:8010 -t .
```

浏览器访问：

**http://127.0.0.1:8010/Studio/Scrabble/scrabble.html**

### 5.2 连接地址

- 本机两人测试：一般**无需改参数**，默认即 `127.0.0.1:9000`。
- 另一台电脑连你这台机器：先把匹配服在 `0.0.0.0` 或可访问 IP 上跑起来（视防火墙而定），页面使用：

  `http://127.0.0.1:8010/Studio/Scrabble/scrabble.html?server=http://你的局域网IP:9000`

---

## 6. 联机功能测试步骤（最小流程）

1. 确保 **match-server** 已启动（见第 4 节）。
2. 用 **两个浏览器窗口**（或普通 + 无痕）打开同一 Scrabble 页面 URL。
3. 均选择 **Online match** → **Find match**。
4. 出现 **Matched** 后应进入棋盘；**仅轮到己方时可摆牌、提交、Pass、换牌**。
5. 一方关闭页面前会断开；另一方应收到提示并返回菜单。

人机模式：选择 **Play vs AI** 即可，不依赖 `match-server`。

---

## 7. 常见问题

| 现象 | 处理 |
|------|------|
| 无法连接匹配服 | 确认已 `npm start`，且端口未被占用；防火墙放行对应端口。 |
| 端口被占用 | 设置 `SCRABBLE_PORT` 为其它端口，并在页面 URL 加上 `?server=http://127.0.0.1:新端口`。 |
| 词典相关报错 | 确认 `Scrabble/enable.txt` 存在且 `match-server` 能读取（路径勿乱移）。 |

---

*文档版本：与「Scrabble + 在线匹配」模块目录布局一致；若你移动了 `Studio` 或 `enable.txt` 位置，请同步修改 `match-server` 内词典路径或 `SCRABBLE_DICT`。*
