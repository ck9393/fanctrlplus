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

  $enabled = ($cfg['service'] ?? '0') === '1';

  if ($enabled) {
    // ✅ 读取 temp/rpm/status 文件
    $temp = trim(@file_get_contents("$tmp_path/temp_{$plugin}_$custom"));
    $rpm  = trim(@file_get_contents("$tmp_path/rpm_{$plugin}_$custom"));

    $temp = (is_numeric($temp)) ? "{$temp}°C" : "-";
    $rpm  = ($rpm !== "" && is_numeric($rpm)) ? $rpm : "-";
    $status = "Active";
  } else {
    // ❗未启用时，不读取缓存，状态标记为 Inactive
    $temp = "-";
    $rpm  = "-";
    $status = "Inactive";
  }

  $fans[] = [
    'label' => $label,
    'temp' => $temp,
    'rpm'  => $rpm,
    'status' => $status
  ];
}

// ✅ 顶部总运行状态：只看是否有 fanctrlplus_xxx.pid 存在
$daemon_running = count(glob("/var/run/{$plugin}_*.pid")) > 0;
$status_text = $daemon_running ? "Running" : "Stopped";

echo json_encode([
  'status' => $status_text,
  'fans' => $fans
]);
