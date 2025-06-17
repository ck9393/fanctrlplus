<?php
header('Content-Type: application/json');

$pluginname = "fanctrlplus";
$cfg_path = "/boot/config/plugins/$pluginname";
$cfg_files = glob("$cfg_path/{$pluginname}_*.cfg");
$tmp_path = "/var/tmp/$pluginname";

$result = [
  "status" => "Loading...",
  "fans" => []
];

// 判断主守护进程是否运行
$result["status"] = file_exists("/var/run/{$pluginname}.pid") ? "Running" : "Stopped";

foreach ($cfg_files as $file) {
  $cfg = parse_ini_file($file);
  if (($cfg['service'] ?? '0') !== '1') continue;

  $custom = $cfg['custom'] ?? '';
  if (!$custom) continue;

  // 从 dashboard_update.sh 写入的缓存读取温度与 rpm
  $rpm_file = "$tmp_path/rpm_{$pluginname}_{$custom}";
  $temp_file = "$tmp_path/temp_{$pluginname}_{$custom}";

  $rpm = (file_exists($rpm_file) && is_readable($rpm_file)) ? trim(file_get_contents($rpm_file)) : "-";
  $temp = (file_exists($temp_file) && is_readable($temp_file)) ? trim(file_get_contents($temp_file)) : "-";

  // 输出统一格式
  $result["fans"][] = [
    "label" => $custom,
    "text" => "[$custom] Temp={$temp}°C, RPM={$rpm}"
  ];
}

echo json_encode($result);