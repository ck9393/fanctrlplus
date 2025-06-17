#!/bin/bash
# fanctrlplus_dashboard_update.sh - 用于 Dashboard 实时刷新 RPM 和上次温度
plugin="fanctrlplus"
cfg_path="/boot/config/plugins/$plugin"
tmp_path="/var/tmp/$plugin"

mkdir -p "$tmp_path"

while true; do
  for cfg in "$cfg_path"/${plugin}_*.cfg; do
    [[ -f "$cfg" ]] || continue
    source "$cfg"
    [[ "$service" != "1" ]] && continue
    [[ -z "$controller" || -z "$custom" ]] && continue

    # 提取 fan 路径
    if [[ "$controller" =~ pwm([0-9]+)$ ]]; then
      fan_index="${BASH_REMATCH[1]}"
      fan_path="$(dirname "$controller")/fan${fan_index}_input"
    else
      continue
    fi

    # 读取 RPM
    if [[ -f "$fan_path" ]]; then
      rpm=$(< "$fan_path")
    else
      rpm="?"
    fi

    # 尝试读取 loop.sh 写入的温度文件
    temp_file="$tmp_path/temp_${plugin}_${custom}"
    temp="-"
    [[ -f "$temp_file" ]] && temp=$(< "$temp_file")

    echo "$rpm" > "$tmp_path/rpm_${plugin}_${custom}"
    echo "$temp" > "$tmp_path/temp_${plugin}_${custom}"
  done

  sleep 15
done
