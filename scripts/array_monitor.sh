#!/bin/bash

plugin="fanctrlplus"
LOG="/var/log/fanctrlplus_array_watch.log"
CHECK_INTERVAL=10
rc_script="/etc/rc.d/rc.${plugin}"
pidfile="/var/run/fanctrlplus.user_stopped"

last_md_state=""
last_fanctrl_state=0

log() {
  echo "[fanctrlplus] $(date +'%Y-%m-%d %H:%M:%S') $1" >> "$LOG"
}

check_array_started() {
  local state
  state=$(grep -oP 'mdState=\K\w+' /proc/mdstat 2>/dev/null || true)
  [[ "$state" == "STARTED" ]]
}

is_fanctrl_running() {
  pgrep -f fanctrlplus_loop.sh | grep -vq "$$"
}

log "Array monitor started"

while true; do
  current_md_state=$(grep -oP 'mdState=\K\w+' < <(/usr/local/sbin/mdcmd status 2>/dev/null) || echo "")
  fanctrl_running=0
  is_fanctrl_running && fanctrl_running=1

  if [[ "$current_md_state" != "$last_md_state" ]]; then
    log "Array state changed: $last_md_state → $current_md_state"
    last_md_state="$current_md_state"
  fi

  if [[ "$current_md_state" == "STARTED" && "$fanctrl_running" -eq 0 && ! -f "$pidfile" ]]; then
    log "FanCtrlPlus not running after array start → launching"
    "$rc_script" start
  fi

  sleep "$CHECK_INTERVAL"
done
