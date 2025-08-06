<?php
header('Content-Type: application/json');

$plugin = "fanctrlplus";
$cfg_path = "/boot/config/plugins/$plugin";
$tmp_path = "/var/tmp/$plugin";

$daemon_running = count(glob("/var/run/{$plugin}_*.pid")) > 0;
$status_text = $daemon_running ? "Running" : "Stopped";

$fans = [];

if ($_GET['op'] == 'refresh' && !empty($_GET['custom'])) {
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

  // 如果启用并且守护进程仍在运行，才读取缓存值
  if ($enabled && $daemon_running) {
    $temp_raw = trim(@file_get_contents("$tmp_path/temp_{$plugin}_$custom"));
    $rpm      = trim(@file_get_contents("$tmp_path/rpm_{$plugin}_$custom"));

    // 解析温度：如 "55 (CPU)" 或 "46 (Disk)"
    if (preg_match('/^([0-9]+)\s+\((CPU|Disk)\)$/', $temp_raw, $matches)) {
      $temp_val = $matches[1];
      $source   = $matches[2];
      // 格式化：温度占2位，后加°C + 两个空格 + 来源
      $temp = sprintf("%2d°C  (%s)", $temp_val, $source);
    } else {
      $temp = "-";
    }

    $rpm = ($rpm !== "" && is_numeric($rpm)) ? $rpm : "-";
    $status = '<span class="green-text">Active</span>';
  } else {
    $temp = "-";
    $rpm = "-";
    $status = '<span class="red-text">Inactive</span>';
  }

  $fans[] = [
    'label' => $label,
    'temp' => $temp,
    'temp_raw' => $temp_val,
    'temp_origin' => $source,
    'rpm'  => $rpm,
    'status' => $status
  ];
}

echo json_encode([
  'status' => $status_text,
  'fans' => $fans
]);
