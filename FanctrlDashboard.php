<?php
header('Content-Type: application/json');

$plugin = "fanctrlplus";
$cfg_path = "/boot/config/plugins/$plugin";
$tmp_path = "/var/tmp/$plugin";

$fans = [];

foreach (glob("$cfg_path/{$plugin}_*.cfg") as $file) {
  $cfg = parse_ini_file($file);
  if (($cfg['service'] ?? '0') !== '1') continue;

  $custom = $cfg['custom'] ?? basename($file, '.cfg');
  $label = $custom;

  // 读取临时缓存的温度、RPM、状态
  $temp = trim(@file_get_contents("$tmp_path/temp_{$plugin}_$custom"));
  $rpm = trim(@file_get_contents("$tmp_path/rpm_{$plugin}_$custom"));
  $status = trim(@file_get_contents("$tmp_path/status_{$plugin}_$custom"));

  // 容错处理
  $temp = ($temp !== "" && is_numeric($temp)) ? "{$temp}°C" : "-";
  $rpm = ($rpm !== "" && is_numeric($rpm)) ? $rpm : "-";
  $status = in_array($status, ['Running', 'Stopped']) ? $status : "-";

  $fans[] = [
    'label' => $label,
    'temp' => $temp,
    'rpm' => $rpm,
    'status' => $status
  ];
}

// 总运行状态（用于顶部 status 圆点）
$daemon_running = file_exists("/var/run/{$plugin}.pid");
$status_text = $daemon_running ? "Running" : "Stopped";

header("Content-Type: application/json");
echo json_encode([
  'status' => $status_text,
  'fans' => $fans
]);
