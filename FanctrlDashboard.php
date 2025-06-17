<?php
$plugin = "fanctrlplus";
$cfg_path = "/boot/config/plugins/$plugin";
$cfg_files = glob("$cfg_path/{$plugin}_*.cfg");

$status = file_exists("/var/run/{$plugin}.pid") ? "Running" : "Stopped";

$data = [
  'status' => $status,
  'fans' => []
];

foreach ($cfg_files as $i => $file) {
  $cfg = parse_ini_file($file);
  if (($cfg['service'] ?? '0') !== '1') continue;

  $label = $cfg['custom'] ?? "Fan $i";
  $rpm_path = "/var/tmp/{$plugin}/rpm_$i";
  $rpm = file_exists($rpm_path) ? trim(file_get_contents($rpm_path)) : "N/A";

  $data['fans'][] = ['label' => $label, 'rpm' => $rpm];
}

header('Content-Type: application/json');
echo json_encode($data);
