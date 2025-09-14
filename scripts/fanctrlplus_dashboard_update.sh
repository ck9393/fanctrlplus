#!/bin/bash
# fanctrlplus_dashboard_update.sh - 实时更新 Dashboard 所需的 RPM 和 PWM
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

    # ✅ 写入RPM文件
    echo "$rpm" > "$tmp_path/rpm_${plugin}_${custom}"

    # 读取 PWM
    pwm_val="-"
    [[ -f "$controller" ]] && pwm_val=$(< "$controller")

    # ✅ 写入PWM文件
    echo "$pwm_val" > "$tmp_path/pwm_${plugin}_${custom}"

    # ✅ 状态判断
    if [[ "$rpm" =~ ^[0-9]+$ ]] && (( rpm > 0 )); then
      echo "Running" > "$tmp_path/status_${plugin}_${custom}"
    else
      echo "Stopped" > "$tmp_path/status_${plugin}_${custom}"
    fi
  done

  sleep 5  # dashboard 刷新频率，不影响风扇控制逻辑
done
