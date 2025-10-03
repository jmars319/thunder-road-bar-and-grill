#!/usr/bin/env bash
# dev.sh — helper to start/stop the PHP dev server and MailHog for local development
# Usage: ./dev.sh [start|stop|status|help]

set -euo pipefail
ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
PID_PHP="$ROOT_DIR/.dev_php.pid"
PID_MAILHOG="$ROOT_DIR/.dev_mailhog.pid"
HOST=127.0.0.1
PORT=8000
DOCROOT="$ROOT_DIR"

print_help(){
  cat <<EOF
Usage: $0 [command]

Commands:
  start    Start PHP built-in server (and MailHog if available)
  stop     Stop services started by this script
  status   Show status (running/pids)
  help     Show this message

Notes:
- PHP server will be started on http://$HOST:$PORT (document root: $DOCROOT)
- MailHog will be started if the 'mailhog' binary is available in PATH.
- PID files are stored at: $PID_PHP and $PID_MAILHOG
EOF
}

is_running(){
  local pidfile="$1"
  if [[ -f "$pidfile" ]]; then
    local pid
    pid=$(cat "$pidfile" 2>/dev/null || echo "")
    if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
      return 0
    fi
  fi
  return 1
}

start_php(){
  if command -v php >/dev/null 2>&1; then
    if lsof -i tcp:"$PORT" >/dev/null 2>&1; then
      echo "Port $PORT appears in use. Refusing to start PHP dev server." >&2
      return 1
    fi
    echo "Starting PHP built-in server on http://$HOST:$PORT (docroot: $DOCROOT)"
    nohup php -S "$HOST:$PORT" -t "$DOCROOT" >/dev/null 2>&1 &
    echo $! > "$PID_PHP"
    echo "PHP PID: $(cat "$PID_PHP")"
  else
    echo "PHP not found in PATH. Install PHP (eg. 'brew install php') to run the dev server." >&2
    return 2
  fi
}

start_mailhog(){
  if command -v mailhog >/dev/null 2>&1; then
    if is_running "$PID_MAILHOG"; then
      echo "MailHog already running (PID: $(cat "$PID_MAILHOG"))"
      return 0
    fi
    echo "Starting MailHog (web UI: http://localhost:8025)"
    nohup mailhog >/dev/null 2>&1 &
    echo $! > "$PID_MAILHOG"
    echo "MailHog PID: $(cat "$PID_MAILHOG")"
  else
    echo "MailHog not installed (optional). Install via Homebrew: 'brew install mailhog'" >&2
  fi
}

stop_pidfile(){
  local pidfile="$1"
  if [[ -f "$pidfile" ]]; then
    local pid
    pid=$(cat "$pidfile" 2>/dev/null || echo "")
    if [[ -n "$pid" ]]; then
      echo "Stopping PID $pid"
      kill "$pid" 2>/dev/null || true
      sleep 0.2
    fi
    rm -f "$pidfile" || true
  else
    echo "No PID file $pidfile"
  fi
}

cmd_start(){
  start_mailhog || true
  start_php
}

cmd_stop(){
  stop_pidfile "$PID_PHP"
  stop_pidfile "$PID_MAILHOG"
}

cmd_status(){
  if is_running "$PID_PHP"; then
    echo "PHP dev server running (PID: $(cat "$PID_PHP"))"
  else
    echo "PHP dev server not running"
  fi
  if is_running "$PID_MAILHOG"; then
    echo "MailHog running (PID: $(cat "$PID_MAILHOG"))"
  else
    echo "MailHog not running"
  fi
}

if [[ ${1:-} == "" ]]; then
  print_help
  exit 0
fi

case "$1" in
  start)
    cmd_start
    ;;
  stop)
    cmd_stop
    ;;
  status)
    cmd_status
    ;;
  help|-h|--help)
    print_help
    ;;
  *)
    echo "Unknown command: $1" >&2
    print_help
    exit 2
    ;;
esac
