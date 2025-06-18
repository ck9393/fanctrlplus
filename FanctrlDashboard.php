<?php
header('Content-Type: application/json');

$plugin = "fanctrlplus";
$cfg_path = "/boot/config/plugins/$plugin";
$tmp_path = "/var/tmp/$plugin";

$fans = [];

foreach (glob("$cfg_path/{$plugin}_*.cfg") as $file) {
  $cfg = parse_ini_file($file);

  $custom = $cfg['custom'] ?? basename($file, '.cfg');
  $label = $custom;

  // 读取临时缓存的温度、RPM、状态
  $temp = trim(@file_get_contents("$tmp_path/temp_{$plugin}_$custom"));
  $rpm = trim(@file_get_contents("$tmp_path/rpm_{$plugin}_$custom"));

  // 容错处理
  $temp = (is_numeric($temp)) ? "{$temp}°C" : "-";
  $rpm = ($rpm !== "" && is_numeric($rpm)) ? $rpm : "-";
  $status = ($cfg['service'] ?? '0') === '1' ? 'Active' : 'Inactive';

  $fans[] = [
    'label' => $label,
    'temp' => $temp,
    'rpm' => $rpm,
    'status' => $status
  ];
}

// 总运行状态（用于顶部 status 圆点）
$daemon_running = count(glob("/var/run/{$plugin}_*.pid")) > 0;
$status_text = $daemon_running ? "Running" : "Stopped";

header("Content-Type: application/json");
echo json_encode([
  'status' => $status_text,
  'fans' => $fans
]);
