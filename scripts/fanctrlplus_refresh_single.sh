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

# === CPU 温度 ===
cpu_pwm_val=0
if [[ "${cpu_enable:-0}" == "1" && -n "$cpu_sensor" && -f "$cpu_sensor" ]]; then
  raw=$(cat "$cpu_sensor")
  [[ "$raw" =~ ^[0-9]+$ ]] && cpu_temp=$((raw / 1000))
  cpu_temp=${cpu_temp:-0}

  if (( cpu_temp <= cpu_min_temp )); then
    cpu_pwm_val=$pwm
  elif (( cpu_temp >= cpu_max_temp )); then
    cpu_pwm_val=$max
  else
    delta=$((cpu_temp - cpu_min_temp))
    range=$((cpu_max_temp - cpu_min_temp))
    cpu_pwm_val=$((pwm + delta * (max - pwm) / range))
  fi
else
  cpu_temp="-"
fi


# === Disk 温控 PWM ===
disk_pwm_val=0
disk_max="*"

# 有勾选 disk 时才处理
if [ -n "$disks" ]; then
  disk_max_valid=0
  found_valid_temp=0

  IFS=',' read -ra disks_list <<< "$disks"
  for disk in "${disks_list[@]}"; do
    disk_path="/dev/disk/by-id/$disk"
    real_path=$(realpath "$disk_path" 2>/dev/null)
    [[ ! -b "$real_path" ]] && continue

    # 跳过休眠磁盘
    smartctl -n standby -A "$real_path" | grep -q "Device is in STANDBY" && continue

    # 获取温度
    if [[ "$real_path" == /dev/nvme* ]]; then
      temp=$(smartctl -A "$real_path" | awk '/Temperature:/ {print $2; exit}')
    else
      temp=$(smartctl -A "$real_path" | awk '
        $1 == 190 || $1 == 194                   { print $10; exit }
        $1 == "Temperature_Celsius"             { print $10; exit }
        $1 == "Airflow_Temperature_Cel"         { print $10; exit }
        $1 == "Current" && $3 == "Temperature:" { print $4; exit }
      ')
    fi

    # 有效温度，更新最大值
    if [[ "$temp" =~ ^[0-9]+$ ]]; then
      (( temp > disk_max_valid )) && disk_max_valid=$temp
      found_valid_temp=1
    fi
  done

  # 若取得有效温度，再执行 PWM 推算
  if (( found_valid_temp == 1 )); then
    disk_max=$disk_max_valid

    if (( disk_max <= low )); then
      disk_pwm_val=$pwm
    elif (( disk_max >= high )); then
      disk_pwm_val=$max
    else
      delta=$((disk_max - low))
      range=$((high - low))
      disk_pwm_val=$((pwm + delta * (max - pwm) / range))
    fi
  fi
fi
  
# === 取较高 PWM 作为最终值，同时设定 max_temp 与来源 ===
if (( cpu_pwm_val > disk_pwm_val )); then
  pwm_val=$cpu_pwm_val
  max_temp=$cpu_temp
  temp_origin="(CPU)"
else
  pwm_val=$disk_pwm_val
  max_temp=$disk_max
  temp_origin=$([ -n "$disks" ] && echo "(Disk)" || echo "(CPU)")
fi

# 避免空写入
if [[ ! "$max_temp" =~ ^[0-9]+$ ]]; then
  max_temp="*"
  temp_origin=""
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
logger -t fanctrlplus "Manual Run $label Temp=${max_temp}°C $temp_origin → PWM=$pwm_val → RPM=$rpm"

echo "${max_temp} ${temp_origin}" > "/var/tmp/fanctrlplus/temp_${plugin}_${custom}"