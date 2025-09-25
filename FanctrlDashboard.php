<?php
header('Content-Type: application/json');

$plugin = "fanctrlplus";
$cfg_path = "/boot/config/plugins/$plugin";
$tmp_path = "/var/tmp/$plugin";

$daemon_running = count(glob("/var/run/{$plugin}_*.pid")) > 0;
$status_text = $daemon_running ? "Running" : "Stopped";

$fans = [];

if (isset($_GET['op']) && $_GET['op'] === 'refresh' && !empty($_GET['custom'])) {
    $custom = escapeshellarg($_GET['custom']);
    $script = "/usr/local/emhttp/plugins/fanctrlplus/scripts/fanctrlplus_refresh_single.sh $custom";
    shell_exec($script . " > /dev/null 2>&1 &");
    echo json_encode(['ok' => 1]);
    exit;
}

foreach (glob("$cfg_path/{$plugin}_*.cfg") as $file) {
  $cfg = parse_ini_file($file);
  $custom = $cfg['custom'] ?? basename($file, '.cfg');
  $label = $custom;
  $enabled = ($cfg['service'] ?? '0') === '1';

  // 初始化字段
  $temp_val = "*";
  $temp_origin = "";
  $rpm = "-";
  $status = '<span class="red-text">Inactive</span>';

  if ($enabled && $daemon_running) {
      $temp_raw = trim(@file_get_contents("$tmp_path/temp_{$plugin}_$custom"));
      $rpm_raw  = trim(@file_get_contents("$tmp_path/rpm_{$plugin}_$custom"));
      $pwm_raw  = trim(@file_get_contents("$tmp_path/pwm_{$plugin}_$custom"));

      // ✅ 解析温度（数字）
      if (preg_match('/^([0-9]+)\s+\((CPU|Disk)\)$/', $temp_raw, $matches)) {
        $temp_val = $matches[1];
        $temp_origin = $matches[2];
      }
      elseif (preg_match('/^\*\s+\((CPU|Disk)\)$/', $temp_raw, $matches)) {
        $temp_val = "*";
        $temp_origin = $matches[1];
      }

      $rpm = ($rpm_raw !== "" && is_numeric($rpm_raw)) ? $rpm_raw : "-";
      $status = '<span class="green-text">Active</span>';

      // ✅ 计算百分比
      $percent = "-";
      if ($pwm_raw !== "" && is_numeric($pwm_raw)) {
        $percent = round($pwm_raw / 255 * 100);
      }

      $rpm_val = $rpm;
      $pct_val = $percent;
  }

  $fans[] = [
    'label'       => $label,
    'temp'        => ($temp_val === "*" ? "*" : "{$temp_val}°C"),
    'temp_raw'    => $temp_val,
    'temp_origin' => $temp_origin,
    'rpm_val'     => $rpm_val,
    'percent' => ($pct_val === "-" ? "-" : "{$pct_val} %"),
    'status'      => $status
  ];
}

echo json_encode([
  'status' => $status_text,
  'fans' => $fans
]);
