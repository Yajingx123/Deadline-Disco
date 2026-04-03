# AcadBeat 完整软件运行指南

## 环境要求

请确保电脑上已安装以下软件：

| 软件 | 最低版本 | 下载地址 |
|------|---------|---------|
| PHP | 8.0+ | https://windows.php.net/download/ |
| Node.js | 18+ | https://nodejs.org/ |
| MySQL | 8.0+ | https://dev.mysql.com/downloads/installer/ |

### 验证安装

打开终端（PowerShell / CMD），分别输入以下命令确认已安装：

```
php -v
node -v
npm -v
mysql --version
```

> **注意**：如果 `mysql` 命令找不到，需要将 MySQL 的 `bin` 目录加入系统 PATH。
> 默认路径：`C:\Program Files\MySQL\MySQL Server 8.0\bin`

### PHP 扩展检查

运行 `php -m` 确认输出中包含 `pdo_mysql`。如果没有，需要在 `php.ini` 中启用该扩展。

---

## 第一步：启动 MySQL 服务

```bash
# Windows（需要管理员权限）
net start MySQL80
```

确保 MySQL root 密码为 `123456`。如果你的密码不同，请修改以下两个文件：
- `vocba_prac/config.php` — 修改 `$pass` 变量（或设置环境变量 `VOCAB_DB_PASS`）
- `Listening/backend/php/src/Config/database.php` — 修改 `$password` 变量

---

## 第二步：导入数据库

在项目根目录下，按顺序执行以下命令。

### 词汇练习模块（数据库：acadbeat）

优先使用新的统一目录 `database/bootstrap/`（仍兼容旧根目录 SQL）：

```bash
mysql -u root -p123456 < database/bootstrap/101_acadbeat_all_tables.sql
mysql -u root -p123456 < database/bootstrap/102_acadbeat_all_data.sql
```

### 听力考试模块（数据库：my_test_schema）

```bash
mysql -u root -p123456 < Listening/backend/sql/schema.sql
mysql -u root -p123456 < Listening/backend/sql/seed.sql
mysql -u root -p123456 < Listening/backend/sql/migration_add_timer_columns.sql
```

### 精听练习模块（表放入 my_test_schema）

```bash
mysql -u root -p123456 my_test_schema < Intensive_Listening/sql/createUser.sql
mysql -u root -p123456 my_test_schema < Intensive_Listening/sql/createAudio.sql
mysql -u root -p123456 my_test_schema < Intensive_Listening/sql/createProgress.sql
```

### 验证数据库

```bash
mysql -u root -p123456 -e "SHOW DATABASES;"
```

应该能看到 `acadbeat` 和 `my_test_schema` 两个数据库。

> **Windows PowerShell 用户注意**：如果管道 `<` 报错，请使用以下格式：
> ```powershell
> cmd /c 'mysql -u root -p123456 < database/bootstrap/101_acadbeat_all_tables.sql'
> ```

---

## 第三步：启动所有服务

需要打开 **4 个终端窗口**，每个窗口运行一个服务。所有命令都在项目根目录下执行。

### 终端 1 — 词汇练习（端口 8002）

```bash
php -S 127.0.0.1:8002 -t ./vocba_prac
```

### 终端 2 — 听力后端 API（端口 8000）

```bash
php -S 127.0.0.1:8000 Listening/backend/php/router.php
```

### 终端 3 — 听力前端（端口 5173）

```bash
cd Listening/frontend
npm install
npm run dev
```

> 如果 `npm run dev` 报错，先删除 `node_modules` 文件夹，再重新执行 `npm install`。

### 终端 4 — 词汇考试（端口 8003）

```bash
php -S 127.0.0.1:8003 -t ./vocabulary-exam
```

---

## 第四步：访问应用

启动完成后，打开浏览器访问以下地址：

| 功能 | 地址 |
|------|------|
| 主页（推荐入口） | http://127.0.0.1:5173 |
| 静态主页（可选） | 直接双击打开项目根目录下的 `home.html` |
| 词汇练习 | http://127.0.0.1:8002 |
| 词汇考试 | http://127.0.0.1:8003/vocabulary-exam.html |
| 听力考试前端 | http://127.0.0.1:5173 |
| 听力后端 API 健康检查 | http://127.0.0.1:8000/api/health |

### 页面导航关系

```
听力前端主页（推荐入口，127.0.0.1:5173）
  |-- Vocabulary → "Word Quest"    → 词汇练习（127.0.0.1:8002）
  |-- Vocabulary → "Mastery Check" → 词汇考试（127.0.0.1:8003）
  +-- Listening 模块按钮             → 听力考试流程

词汇考试页面（127.0.0.1:8003/vocabulary-exam.html）
  |-- 右下角 "Back to Main"         → 返回来源页（若无来源则回 127.0.0.1:5173）
  |-- 顶部 AcadBeat Logo            → 同上
  +-- 顶部 Listening/Speaking/...   → 同上

词汇练习页面（127.0.0.1:8002）
  +-- 右下角 "Back to Main"         → 返回来源主页
```

---

## 端口速查表

| 端口 | 服务 | 说明 |
|------|------|------|
| 3306 | MySQL | 数据库服务（系统服务，自动运行） |
| 8000 | PHP 内置服务器 | 听力模块后端 API |
| 8002 | PHP 内置服务器 | 词汇练习模块 |
| 8003 | PHP 内置服务器 | 词汇考试模块 |
| 5173 | Vite 开发服务器 | 听力模块前端（React） |

---

## 常见问题

### Q: `mysql` 命令找不到？
将 MySQL 安装目录下的 `bin` 文件夹添加到系统 PATH 环境变量中。Windows 默认路径为：
`C:\Program Files\MySQL\MySQL Server 8.0\bin`

### Q: PHP 报错 `pdo_mysql` 找不到？
编辑 PHP 安装目录下的 `php.ini`，取消注释 `extension=pdo_mysql` 这一行。

### Q: 端口被占用？
使用以下命令查看哪个进程占用了端口（以 8000 为例）：
```bash
netstat -ano | findstr :8000
```
然后用 `taskkill /PID <进程ID> /F` 结束该进程。

### Q: `npm run dev` 失败？
1. 删除 `Listening/frontend/node_modules` 文件夹
2. 删除 `Listening/frontend/package-lock.json`
3. 重新运行 `npm install`
4. 再运行 `npm run dev`

### Q: SQL 导入时出现编码错误？
在 mysql 命令中添加字符集参数：
```bash
mysql -u root -p123456 --default-character-set=utf8mb4 < 文件路径.sql
```
