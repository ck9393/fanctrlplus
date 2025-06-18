#!/bin/bash
# fanctrlplus_dashboard_update.sh - 实时更新 Dashboard 所需的 RPM 和上次温度

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

    # 提取 fan 路径：从 pwmX 推导为 fanX_input
    if [[ "$controller" =~ pwm([0-9]+)$ ]]; then
      fan_index="${BASH_REMATCH[1]}"
      fan_path="$(dirname "$controller")/fan${fan_index}_input"
    else
      continue
    fi

    # 读取 RPM
    rpm="-"
    [[ -f "$fan_path" ]] && rpm=$(< "$fan_path")

    # ✅ 正确读取 loop.sh 写入的缓存温度文件
    temp="-"
    temp_file="/var/tmp/${plugin}/temp_${plugin}_${custom}"
    [[ -f "$temp_file" ]] && temp=$(< "$temp_file")

    # ✅ 写入 Dashboard 所需文件
    echo "$rpm" > "$tmp_path/rpm_${plugin}_${custom}"
    echo "$temp" > "$tmp_path/temp_${plugin}_${custom}"

    # ✅ 状态判断
    if [[ "$rpm" =~ ^[0-9]+$ ]] && (( rpm > 0 )); then
      echo "Running" > "$tmp_path/status_${plugin}_${custom}"
    else
      echo "Stopped" > "$tmp_path/status_${plugin}_${custom}"
    fi
  done

  sleep 15  # dashboard 刷新频率，不影响风扇控制逻辑
done
