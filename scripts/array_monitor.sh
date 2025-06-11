#!/bin/bash

LOG="/var/log/fanctrlplus_array_watch.log"
CHECK_INTERVAL=5
prev_state="unknown"

echo "[fanctrlplus] Monitor started at $(date)" >> "$LOG"

get_state() {
  if /usr/local/sbin/mdcmd status 2>/dev/null | grep -q "mdState=STARTED"; then
    echo "yes"
  elif mount | grep -q "/mnt/disk"; then
    echo "yes"
  else
    echo "no"
  fi
}

is_fanctrl_running() {
  pgrep -f fanctrlplus_loop.sh | grep -vq $$  # 只要有任意 fanctrlplus_loop.sh 正在跑就认为在运行
}

while true; do
  state=$(get_state)
  echo "[fanctrlplus] Current state: $state | Previous state: $prev_state" >> "$LOG"

  if [[ "$state" == "yes" ]]; then
    if [[ "$prev_state" != "yes" ]]; then
      if [ ! -f /var/run/fanctrlplus.user_stopped ]; then
        echo "[fanctrlplus] Array just started, triggering fanctrlplus at $(date)" >> "$LOG"
        /etc/rc.d/rc.fanctrlplus start
      else
        echo "[fanctrlplus] Array started, but user stop flag exists → not starting" >> "$LOG"
      fi
    elif ! is_fanctrl_running && [ ! -f /var/run/fanctrlplus.user_stopped ]; then
      echo "[fanctrlplus] Array already running, fanctrlplus not active → starting at $(date)" >> "$LOG"
      /etc/rc.d/rc.fanctrlplus start
    fi
  fi

  prev_state="$state"
  sleep $CHECK_INTERVAL
done
