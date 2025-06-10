#!/bin/bash

# FanCtrlPlus Array Monitor
LOG="/var/log/fanctrlplus_array_watch.log"
CHECK_INTERVAL=5
prev_state="unknown"

while true; do
  # 读取 array 启动状态
  state=$(grep -Po '^arrayStarted="\K[^"]+' /var/local/emhttp/var.ini 2>/dev/null)

  if [[ "$state" == "yes" && "$prev_state" != "yes" ]]; then
    echo "[fanctrlplus] Detected array started at $(date)" >> "$LOG"
    /usr/local/emhttp/plugins/fanctrlplus/scripts/rc.fanctrlplus start
  fi

  prev_state="$state"
  sleep $CHECK_INTERVAL
done
