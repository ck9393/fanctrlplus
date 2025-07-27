#!/bin/bash
# fanctrlplus_refresh_single.sh
plugin="fanctrlplus"
cfg_path="/boot/config/plugins/$plugin"
custom="$1"
cfg_file="$cfg_path/${plugin}_$custom.cfg"
[[ -f "$cfg_file" ]] || exit 1
source "$cfg_file"
max="${max:-255}"
controller_enable="${controller}_enable"

# 计算 max_temp
max_temp=0
IFS=',' read -ra disks_list <<< "$disks"

for disk in "${disks_list[@]}"; do
  disk_path="/dev/disk/by-id/$disk"
  real_path=$(realpath "$disk_path" 2>/dev/null)
  [[ ! -b "$real_path" ]] && continue

  smartctl -n standby -A "$real_path" | grep -q "Device is in STANDBY" && continue

  if [[ "$real_path" == /dev/nvme* ]]; then
    temp=$(smartctl -A "$real_path" | awk '/Temperature:/ {print $2; exit}')
  else
    temp=$(smartctl -A "$real_path" | awk '
      $1 == 190 || $1 == 194                 { print $10; exit }
      $1 == "Temperature_Celsius"           { print $10; exit }
      $1 == "Airflow_Temperature_Cel"       { print $10; exit }
      $1 == "Current" && $3 == "Temperature:" { print $4; exit }
    ')
  fi

  [[ "$temp" =~ ^[0-9]+$ ]] && (( temp > max_temp )) && max_temp=$temp
done

# PWM 计算
if (( max_temp <= low )); then
  pwm_val=$pwm
elif (( max_temp >= high )); then
  pwm_val=$max
else
  delta=$((max_temp - low))
  range=$((high - low))
  pwm_val=$((pwm + delta * (max - pwm) / range))
fi

# 强制写 PWM
[[ -f "$controller_enable" ]] && echo 1 > "$controller_enable"
echo "$pwm_val" > "$controller"
sleep 4

# 采集 RPM
fan_index=""
if [[ "$controller" =~ pwm([0-9]+)$ ]]; then
  fan_index="${BASH_REMATCH[1]}"
  fan_path="$(dirname "$controller")/fan${fan_index}_input"
fi
if [[ -n "$fan_path" && -f "$fan_path" ]]; then
  rpm=$(cat "$fan_path")
else
  rpm="?"
fi

label="[${custom}]"
logger -t fanctrlplus "Manual Run $label Temp=${max_temp}°C → PWM=$pwm_val → RPM=$rpm"

echo "$max_temp" > "/var/tmp/fanctrlplus/temp_${plugin}_${custom}"