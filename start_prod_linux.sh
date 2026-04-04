#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
RUN_DIR="$ROOT_DIR/.run"
mkdir -p "$RUN_DIR"

if [[ -f "$ROOT_DIR/.env.production" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "$ROOT_DIR/.env.production"
  set +a
elif [[ -f "$ROOT_DIR/.env.prod" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "$ROOT_DIR/.env.prod"
  set +a
fi

echo "=== Build Frontend Dist (Production) ==="
for app in forum-project admin_page message-center-project; do
  if [[ -d "$ROOT_DIR/$app" ]]; then
    echo "[build] $app"
    (cd "$ROOT_DIR/$app" && npm install && npm run build)
  fi
done

echo
echo "=== Start Realtime + Scrabble Match Services ==="
if [[ -f "$RUN_DIR/realtime_3001.pid" ]]; then
  old_pid="$(cat "$RUN_DIR/realtime_3001.pid" || true)"
  if [[ -n "${old_pid:-}" ]] && kill -0 "$old_pid" 2>/dev/null; then
    kill "$old_pid" || true
    sleep 1
  fi
fi

(
  cd "$ROOT_DIR/voice-room-server"
  nohup npm start >> "$RUN_DIR/realtime_3001.out.log" 2>> "$RUN_DIR/realtime_3001.err.log" < /dev/null &
  echo $! > "$RUN_DIR/realtime_3001.pid"
)

echo "[started] realtime PID $(cat "$RUN_DIR/realtime_3001.pid")"

if [[ -f "$RUN_DIR/scrabble_9000.pid" ]]; then
  old_pid="$(cat "$RUN_DIR/scrabble_9000.pid" || true)"
  if [[ -n "${old_pid:-}" ]] && kill -0 "$old_pid" 2>/dev/null; then
    kill "$old_pid" || true
    sleep 1
  fi
fi

(
  cd "$ROOT_DIR/Studio/Scrabble/match-server"
  nohup npm run start >> "$RUN_DIR/scrabble_9000.out.log" 2>> "$RUN_DIR/scrabble_9000.err.log" < /dev/null &
  echo $! > "$RUN_DIR/scrabble_9000.pid"
)

echo "[started] scrabble-match PID $(cat "$RUN_DIR/scrabble_9000.pid")"
echo
echo "Done."
echo "1) If using Nginx + PHP-FPM, ensure Nginx is started and points to this repo root."
echo "2) If not using Nginx yet, you can temporarily run: php -S 0.0.0.0:8001 -t $ROOT_DIR"
