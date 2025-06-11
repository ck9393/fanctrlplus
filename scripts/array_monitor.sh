#!/bin/bash

plugin="fanctrlplus"
LOG="/var/log/fanctrlplus_array_watch.log"
CHECK_INTERVAL=5
prev_state="unknown"
rc_script="/etc/rc.d/rc.${plugin}"
pidfile="/var/run/fanctrlplus.user_stopped"

echo "[fanctrlplus] Monitor started at $(date)" >> "$LOG"

get_state() {
  echo "[fanctrlplus] Running mdcmd status at $(date)" >> "$LOG"
  output=$(/usr/local/sbin/mdcmd status 2>/dev/null)
  echo "$output" >> "$LOG"

  if [[ "$output" == *"mdState=STARTED"* ]]; then
    echo "yes"
  elif mount | grep -q "/mnt/disk"; then
    echo "[fanctrlplus] mdcmd fallback: found mounted /mnt/disk" >> "$LOG"
    echo "yes"
  else
    echo "no"
  fi
}

is_fanctrl_running() {
  pgrep -f fanctrlplus_loop.sh | grep -vq $$  # 当前脚本排除自身
}

while true; do
  state=$(get_state)
  echo "[fanctrlplus] Current state: $state | Previous: $prev_state | $(date)" >> "$LOG"

  if [[ "$state" == "yes" ]]; then
    if [[ "$prev_state" != "yes" ]]; then
      if [ ! -f "$pidfile" ]; then
        echo "[fanctrlplus] Array just started → launching fanctrlplus" >> "$LOG"
        "$rc_script" start
      else
        echo "[fanctrlplus] Array started, but user stopped manually → skip" >> "$LOG"
      fi
    elif ! is_fanctrl_running && [ ! -f "$pidfile" ]; then
      echo "[fanctrlplus] Array already running, but fanctrlplus not → launching" >> "$LOG"
      "$rc_script" start
    fi
  fi

  prev_state="$state"
  sleep $CHECK_INTERVAL
done
