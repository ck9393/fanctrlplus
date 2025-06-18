#!/bin/bash
# fanctrlplus_loop.sh - 实际运行的风扇控制脚本

cfg_file="$1"
[[ -f "$cfg_file" ]] || exit 1
source "$cfg_file

plugin="fanctrlplus"
custom="${custom:-$(basename "$cfg_file" .cfg)}"
controller_enable="${controller}_enable"

# 推导 RPM 读取路径
if [[ "$controller" =~ pwm([0-9]+)$ ]]; then
  fan_index="${BASH_REMATCH[1]}"
  fan_path="$(dirname "$controller")/fan${fan_index}_input"
else
  fan_path=""
fi

prev_pwm=-1

while true; do
  max_temp=0
  IFS=',' read -ra disks_list <<< "$disks"

  for disk in "${disks_list[@]}"; do
    disk_path="/dev/disk/by-id/$disk"
    real_path=$(realpath "$disk_path" 2>/dev/null)
    [[ ! -b "$real_path" ]] && continue

    # 跳过休眠中的硬盘
    smartctl -n standby -A "$real_path" | grep -q "Device is in STANDBY" && continue

    # 获取温度
    if [[ "$real_path" == /dev/nvme* ]]; then
      temp=$(smartctl -A "$real_path" | awk '/Temperature:/ {print $2; exit}')
    else
      temp=$(smartctl -A "$real_path" | awk '/^194|Temperature_Celsius/ {print $10; exit}')
    fi

    [[ "$temp" =~ ^[0-9]+$ ]] && (( temp > max_temp )) && max_temp=$temp
  done

  # 计算 PWM 值
  if (( max_temp <= low )); then
    pwm_val=$pwm
  elif (( max_temp >= high )); then
    pwm_val=255
  else
    delta=$((max_temp - low))
    range=$((high - low))
    pwm_val=$((pwm + delta * (255 - pwm) / range))
  fi

  # ✅ 写入 Dashboard 读取的温度缓存
  echo "$max_temp" > "/var/tmp/fanctrlplus/temp_${plugin}_${custom}"

  # 只有 PWM 有明显变化时才写入
  if [[ "$prev_pwm" == -1 || $(( pwm_val - prev_pwm >= 5 || prev_pwm - pwm_val >= 5 )) == 1 ]]; then
    [[ -f "$controller_enable" ]] && echo 1 > "$controller_enable"
    echo "$pwm_val" > "$controller"

    sleep 4
    if [[ -n "$fan_path" && -f "$fan_path" ]]; then
      rpm=$(cat "$fan_path")
    else
      rpm="?"
    fi

    label="[${custom}]"
    logger -t fanctrlplus "$label Temp=${max_temp}°C → PWM=$pwm_val → RPM=$rpm"
    prev_pwm=$pwm_val
  fi

  sleep $((interval * 60))
done
