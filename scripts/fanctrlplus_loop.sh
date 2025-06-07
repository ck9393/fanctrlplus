#!/bin/bash
# fanctrlplus_loop.sh - 实际运行的风扇控制脚本
cfg_file="$1"
source "$cfg_file"

controller_enable="${controller}_enable"
fan_path="${controller/fan/pwm}"   # 推导 fan 路径
[[ "$controller" =~ pwm([0-9]+)$ ]] && fan_path="$(dirname "$controller")/fan${BASH_REMATCH[1]}_input"

prev_pwm=-1

while true; do
  max_temp=0
  IFS=',' read -ra disks_list <<< "$disks"
  for disk in "${disks_list[@]}"; do
    path="/dev/disk/by-id/$disk"
    path=$(realpath "$path" 2>/dev/null)
    [[ ! -b "$path" ]] && continue
    smartctl -n standby -A "$path" | grep -q "Device is in STANDBY" && continue
    if [[ "$path" == /dev/nvme* ]]; then
      temp=$(smartctl -A "$path" | awk '/Temperature:/ {print $2; exit}')
    else
      temp=$(smartctl -A "$path" | awk '/^194|Temperature_Celsius/ {print $10; exit}')
    fi
    [[ "$temp" =~ ^[0-9]+$ ]] && (( temp > max_temp )) && max_temp=$temp
  done

  if (( max_temp <= low )); then
    pwm_val=$pwm
  elif (( max_temp >= high )); then
    pwm_val=255
  else
    delta=$((max_temp - low))
    range=$((high - low))
    pwm_val=$((pwm + delta * (255 - pwm) / range))
  fi

  if [[ "$prev_pwm" == -1 || $(( pwm_val - prev_pwm >= 5 || prev_pwm - pwm_val >= 5 )) == 1 ]]; then
    [[ -f "$controller_enable" ]] && echo 1 > "$controller_enable"
    echo "$pwm_val" > "$controller"
    sleep 4
    rpm=$(cat "$fan_path" 2>/dev/null || echo 0)
    logger -t fanctrlplus "[${custom:-FanCtrl_${controller##*/}}] Temp=${max_temp}°C → PWM=$pwm_val → RPM=$rpm"
    prev_pwm=$pwm_val
  fi

  sleep $((interval * 60))
done
