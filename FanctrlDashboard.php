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
    $temp = trim(@file_get_contents("$tmp_path/temp_{$plugin}_$custom"));
    $rpm  = trim(@file_get_contents("$tmp_path/rpm_{$plugin}_$custom"));

    $temp = (is_numeric($temp)) ? "{$temp}°C" : "-";
    $rpm  = ($rpm !== "" && is_numeric($rpm)) ? $rpm : "-";
    $status = '<span class="green-text">Active</span>';
  } else {
    $temp = "-";
    $rpm = "-";
    $status = '<span class="red-text">Inactive</span>';
  }

  $fans[] = [
    'label' => $label,
    'temp' => $temp,
    'rpm'  => $rpm,
    'status' => $status
  ];
}

echo json_encode([
  'status' => $status_text,
  'fans' => $fans
]);
