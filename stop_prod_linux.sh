#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
RUN_DIR="$ROOT_DIR/.run"

stop_pid_file() {
  local file="$1"
  if [[ -f "$file" ]]; then
    local pid
    pid="$(cat "$file" || true)"
    if [[ -n "${pid:-}" ]] && kill -0 "$pid" 2>/dev/null; then
      kill "$pid" || true
      sleep 1
      kill -9 "$pid" 2>/dev/null || true
      echo "[stopped] PID $pid"
    fi
    rm -f "$file"
  fi
}

stop_pid_file "$RUN_DIR/realtime_3001.pid"
stop_pid_file "$RUN_DIR/scrabble_9000.pid"

if command -v lsof >/dev/null 2>&1; then
  for port in 3001 9000; do
    pids="$(lsof -ti tcp:$port 2>/dev/null || true)"
    if [[ -n "${pids:-}" ]]; then
      echo "$pids" | xargs -r kill -9
      echo "[stopped-by-port] $port"
    fi
  done
fi

echo "[done] production stop completed."
