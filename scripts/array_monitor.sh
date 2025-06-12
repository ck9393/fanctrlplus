#!/bin/bash

plugin="fanctrlplus"
LOG="/var/log/fanctrlplus_array_watch.log"
CHECK_INTERVAL=5
rc_script="/etc/rc.d/rc.${plugin}"
pidfile="/var/run/fanctrlplus.user_stopped"

echo "[fanctrlplus] Array monitor started at $(date)" >> "$LOG"

check_array_started() {
  output=$(/usr/local/sbin/mdcmd status 2>/dev/null)
  echo "$output" >> "$LOG"

  if [[ "$output" == *"mdState=STARTED"* ]]; then
    return 0
  elif mount | grep -q "/mnt/disk"; then
    echo "[fanctrlplus] fallback: /mnt/disk mount found" >> "$LOG"
    return 0
  fi
  return 1
}

is_fanctrl_running() {
  pgrep -f fanctrlplus_loop.sh | grep -vq $$  # 当前脚本除外
}

while true; do
  if check_array_started; then
    if ! is_fanctrl_running && [ ! -f "$pidfile" ]; then
      echo "[fanctrlplus] Array started + not running + not stopped → launching" >> "$LOG"
      "$rc_script" start
    fi
  fi
  sleep "$CHECK_INTERVAL"
done
