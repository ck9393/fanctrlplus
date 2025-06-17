<?php
header('Content-Type: application/json');

$pluginname = "fanctrlplus";
$cfg_path = "/boot/config/plugins/$pluginname";
$cfg_files = glob("$cfg_path/{$pluginname}_*.cfg");

$result = [
  "status" => "Loading...",
  "fans" => []
];

// 判断是否运行中
$result["status"] = file_exists("/var/run/{$pluginname}.pid") ? "Running" : "Stopped";

// 收集各个风扇的 RPM
foreach ($cfg_files as $i => $file) {
  $cfg = parse_ini_file($file);
  if (($cfg['service'] ?? '0') !== '1') continue;

  $custom = $cfg['custom'] ?? '';
  if (!$custom) continue;

  $rpm_file = "/var/tmp/{$pluginname}/rpm_{$pluginname}_{$custom}";
  $rpm = (file_exists($rpm_file) && is_readable($rpm_file)) ? trim(file_get_contents($rpm_file)) : "-";

  $result["fans"][] = [
    "label" => $custom,
    "rpm" => $rpm
  ];
}

echo json_encode($result);
