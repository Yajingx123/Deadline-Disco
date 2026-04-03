# Deadline-Disco — AcadBeat

> Agile Software Engineering Course Project

## Important Notice

The repository has evolved beyond the historical module list below.
For the current runnable architecture and startup topology, use:

- `docs/ARCHITECTURE.md`
- `docs/REPO_REORG_PLAN.md`
- `SETUP_GUIDE.md`

If this `README.md` conflicts with `docs/ARCHITECTURE.md`, treat `docs/ARCHITECTURE.md` as the source of truth.

## Current Quick Start (Recommended)

### 1) Start database

- Ensure MySQL is running.
- Import bootstrap SQL from the canonical path:

```bash
mysql -u root -p123456 < database/bootstrap/101_acadbeat_all_tables.sql
mysql -u root -p123456 < database/bootstrap/102_acadbeat_all_data.sql
```

### 2) Start all services

Use one command from repository root:

```bash
php start_all.php
```

Or run full bootstrap flow (import SQL + restart services):

```bash
php run_everything.php
```

### 3) Verify key pages

- Home: `http://127.0.0.1:8001/home.html`
- Forum: `http://127.0.0.1:8001/forum-project/dist/index.html?view=forum`
- Message Center: `http://127.0.0.1:8001/message-center-project/dist/index.html`
- Admin: `http://127.0.0.1:5174/admin_page/dist/`
- Realtime health: `http://127.0.0.1:3001/health`

AcadBeat is an English learning platform built for university freshmen. It currently provides four functional modules:

| Module | Description | Tech Stack |
|--------|-------------|------------|
| **Vocabulary Practice** | Word banks, word books, study sessions and progress tracking | PHP + MySQL |
| **Vocabulary Exam** | Timed vocabulary test with 3 difficulty levels (Beginner / Intermediate / Advanced) | Static HTML/CSS/JS |
| **Listening Exam** | Full listening exam flow with 5 question types, timer, progress saving and auto-grading | React + Vite + PHP + MySQL |
| **Intensive Listening** | Sentence-level audio practice with collection and progress tracking | PHP + MySQL |

---

## Project Structure

```
Deadline-Disco-dev/
|-- home.html                  # Main landing page (entry point)
|-- README.md
|-- 101_acadbeat_all_tables.sql
|-- 102_acadbeat_all_data.sql
|
|-- vocba_prac/                # Vocabulary Practice module (PHP, port 8002)
|   |-- config.php             # DB config
|   |-- index.php, wordbank.php, practice.php, ...
|   +-- includes/              # Shared header/footer
|
|-- vocabulary-exam/           # Vocabulary Exam module (static, port 8003)
|   |-- vocabulary-exam.html   # Exam page
|   |-- styles.css
|   |-- exam.js                # Exam logic (timer, grading)
|   +-- questions.js           # Question bank (180 questions)
|
|-- Listening/                 # Listening Exam module
|   |-- frontend/              # React + Vite app (port 5173)
|   |   |-- src/
|   |   |-- package.json
|   |   +-- index.html
|   +-- backend/               # PHP API backend (port 8000)
|       |-- php/
|       |   |-- router.php     # Entry router
|       |   +-- src/           # Modules, config, HTTP helpers
|       +-- sql/               # Database scripts (my_test_schema)
|
+-- Intensive_Listening/       # Intensive Listening data & components
    +-- sql/                   # Database scripts (tables in my_test_schema)
```

---

## Quick Start Guide

### Prerequisites

Make sure the following are installed on your machine:

- **PHP** >= 8.0 (with `pdo_mysql` extension enabled)
- **Node.js** >= 18 and **npm**
- **MySQL** >= 8.0

### Step 1: Start MySQL Service

Make sure the MySQL service is running:

```bash
# Windows (run as Administrator if needed)
net start MySQL80
```

> Default credentials used by the project: user `root`, password `123456`.
> If your password is different, update these files:
> - `vocba_prac/config.php` (or set env variable `VOCAB_DB_PASS`)
> - `Listening/backend/php/src/Config/database.php`

### Step 2: Import All SQL Files

Run the following commands from the project root directory. On Windows, if `mysql` is not in PATH, use the full path (e.g. `"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql"`).

**Vocabulary module** (database: `acadbeat`):

```bash
mysql -u root -p123456 < 101_acadbeat_all_tables.sql
mysql -u root -p123456 < 102_acadbeat_all_data.sql
```

**Listening module** (database: `my_test_schema`):

```bash
mysql -u root -p123456 < Listening/backend/sql/schema.sql
mysql -u root -p123456 < Listening/backend/sql/seed.sql
mysql -u root -p123456 < Listening/backend/sql/migration_add_timer_columns.sql
```

**Intensive Listening tables** (into `my_test_schema`):

```bash
mysql -u root -p123456 my_test_schema < Intensive_Listening/sql/createUser.sql
mysql -u root -p123456 my_test_schema < Intensive_Listening/sql/createAudio.sql
mysql -u root -p123456 my_test_schema < Intensive_Listening/sql/createProgress.sql
```

### Step 3: Start All Servers

Open **4 separate terminal windows** from the project root and run one command in each:

**Terminal 1** - Vocabulary Practice (port 8002):
```bash
php -S 127.0.0.1:8002 -t ./vocba_prac
```

**Terminal 2** - Listening Backend API (port 8000):
```bash
php -S 127.0.0.1:8000 Listening/backend/php/router.php
```

**Terminal 3** - Listening Frontend (port 5173):
```bash
cd Listening/frontend
npm install
npm run dev
```

> If `npm run dev` fails, delete `node_modules` folder, then run `npm install` again.

**Terminal 4** - Vocabulary Exam (port 8003):
```bash
php -S 127.0.0.1:8003 -t ./vocabulary-exam
```

### Step 4: Open the Application

Open your browser and navigate to:

| Page | URL |
|------|-----|
| **Main Homepage (recommended)** | http://127.0.0.1:5173 |
| **Static Landing Page (optional)** | Open `home.html` directly in browser |
| **Vocabulary Practice** | http://127.0.0.1:8002 |
| **Vocabulary Exam** | http://127.0.0.1:8003/vocabulary-exam.html |
| **Listening Exam (Frontend)** | http://127.0.0.1:5173 |
| **Listening API** | http://127.0.0.1:8000/api/health |

Navigation behavior (latest):
- In **Listening Frontend** (`5173`) Home:
  - **Vocabulary → Word Quest** opens `http://127.0.0.1:8002/`
  - **Vocabulary → Mastery Check** opens `http://127.0.0.1:8003/vocabulary-exam.html`
- In **Vocabulary Exam** (`8003`):
  - **Back to Main** button (bottom-right) returns to the page you came from
  - Top nav (**AcadBeat / Listening / Speaking / Reading / Writing**) also returns to main page
  - If no referrer is available, fallback target is `http://127.0.0.1:5173/`

---

## Databases

| Database | Module | Tables |
|----------|--------|--------|
| `acadbeat` | Core platform + Vocabulary Practice | `users`, `training_*`, `forum_*`, `checkin_*`, `vocab_*` |
| `my_test_schema` | Listening + Intensive Listening | `user`, `exams`, `questions`, `exam_progress`, `exam_results`, `intensive_listening_user`, `intensive_listening_audio`, `user_audio_progress` |

---

## Port Summary

| Port | Service |
|------|---------|
| 8000 | Listening backend PHP API |
| 8002 | Vocabulary Practice PHP |
| 8003 | Vocabulary Exam (static) |
| 5173 | Listening Frontend (Vite dev server) |

---

## Team

Deadline-Disco | Agile Software Engineering
