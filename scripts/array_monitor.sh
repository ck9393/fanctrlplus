#!/bin/bash

LOG="/var/log/fanctrlplus_array_watch.log"
CHECK_INTERVAL=5
prev_state="unknown"

get_state() {
  grep -Po '^arrayStarted="\K[^"]+' /var/local/emhttp/var.ini 2>/dev/null
}

is_fanctrl_running() {
  pgrep -f rc.fanctrlplus | grep -vq $$  # 排除自己
}

while true; do
  state=$(get_state)

  if [[ "$state" == "yes" ]]; then
    if [[ "$prev_state" != "yes" ]]; then
      echo "[fanctrlplus] Array started at $(date)" >> "$LOG"
      /usr/local/emhttp/plugins/fanctrlplus/scripts/rc.fanctrlplus start
    elif ! is_fanctrl_running; then
      echo "[fanctrlplus] Array already running but fanctrl not active, starting at $(date)" >> "$LOG"
      /usr/local/emhttp/plugins/fanctrlplus/scripts/rc.fanctrlplus start
    fi
  fi

  prev_state="$state"
  sleep $CHECK_INTERVAL
done
