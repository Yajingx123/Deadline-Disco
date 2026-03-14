# Listening Project

This project now uses three parts together:

- `frontend/` : React + Vite frontend
- `backend/php/` : official backend gateway layer
- `backend/python/` : Python business service layer

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
  - Official PHP API gateway
  - Frontend talks to PHP first
- `python/app/main.py`
  - Python service entry
- `python/app/core/`
  - Shared backend basics, mainly DB connection
- `python/app/modules/listening_exam/`
  - Listening exam backend module
- `sql/`
  - Shared database schema and seed data

## Where Your Module Is

Your listening exam code is mainly here:

- `frontend/src/modules/listening-exam/`
- `backend/python/app/modules/listening_exam/`

## Where Shared Code Is

Shared code is mainly here:

- `frontend/src/shared/home/`
- `frontend/src/shared/layout/`
- `frontend/src/shared/ui/`
- `backend/python/app/core/`
- `backend/php/public/index.php`

## Run Commands

### 1. Initialize database

```bash
cd /Users/panjingyu/Desktop/Listening/backend
mysql -u root -p'JingyuPan@20051026' < sql/schema.sql
mysql -u root -p'JingyuPan@20051026' < sql/seed.sql
```

### 2. Start Python service

```bash
cd /Users/panjingyu/Desktop/Listening/backend/python
source ../.venv/bin/activate
uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

### 3. Start PHP gateway

```bash
cd /Users/panjingyu/Desktop/Listening/backend
PYTHON_API_BASE=http://127.0.0.1:8001 php -S 127.0.0.1:8000 -t php/public
```

### 4. Start frontend

```bash
cd /Users/panjingyu/Desktop/Listening/frontend
VITE_API_BASE_URL=http://127.0.0.1:8000 npm run dev -- --host 127.0.0.1 --port 5173
```

## Ports

- Frontend: `5173`
- PHP gateway: `8000`
- Python service: `8001`
- MySQL: `3306`
