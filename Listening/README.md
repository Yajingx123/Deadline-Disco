# Listening Project

This project now uses two main parts:

- `frontend/` : frontend source code
- `backend/` : backend source code and SQL

The home page is shared by the whole team.
The listening exam code stays inside the listening exam module only.

## Simple Structure

### `frontend/`
Only focus on these folders:

- `src/app/`
  - App entry and page flow
- `src/shared/`
  - Shared home page, shared layout, shared UI
- `src/modules/listening-exam/`
  - Listening exam module only
- `src/styles/`
  - Global styles
- `public/audio/`
  - Audio files

### `backend/`
Only focus on these folders:

- `php/public/index.php`
  - Thin PHP entry file
- `php/router.php`
  - Router for the local PHP development server
- `php/src/Config/`
  - Database config
- `php/src/Http/`
  - Request and JSON response helpers
- `php/src/Support/`
  - Shared JSON utility helpers
- `php/src/Modules/ListeningExam/`
  - Listening exam API files split by function
- `sql/`
  - Database schema and seed data

## Where Your Module Is

Your listening exam code is mainly here:

- `frontend/src/modules/listening-exam/`
- `backend/php/src/Modules/ListeningExam/`

## Where Shared Code Is

Shared code is mainly here:

- `frontend/src/shared/home/`
- `frontend/src/shared/layout/`
- `frontend/src/shared/ui/`

## Run Commands

### 1. Initialize database

```bash
cd /Users/panjingyu/Desktop/Deadline-Disco/Deadline-Disco/Listening/backend
mysql -u root -p'JingyuPan@20051026' < sql/schema.sql
mysql -u root -p'JingyuPan@20051026' < sql/seed.sql
```

### 2. Start PHP backend

```bash
cd /Users/panjingyu/Desktop/Deadline-Disco/Deadline-Disco/Listening/backend
php -S 127.0.0.1:8000 php/router.php
```

### 3. Start frontend

```bash
cd /Users/panjingyu/Desktop/Deadline-Disco/Deadline-Disco/Listening/frontend
VITE_API_BASE_URL=http://127.0.0.1:8000 npm run dev -- --host 127.0.0.1 --port 5173
```

## Ports

- Frontend: `5173`
- PHP backend: `8000`
- MySQL: `3306`
