<?php
function json_response($data) {
  while (ob_get_level()) ob_end_clean();
  if (!headers_sent()) {
    header_remove();
    header('Content-Type: application/json');
    http_response_code(200);
  }
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/fanctrlplus_error.log');
error_reporting(E_ALL);

$log = "/tmp/fanctrlplus_debug.log";

$plugin  = 'fanctrlplus';
$docroot = $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/plugins/$plugin/include/Common.php";

file_put_contents($log, "[" . date('Y-m-d H:i:s') . "] [FanctrlSaveBlock] entered\n", FILE_APPEND);

$index = intval($_POST['index'] ?? 0);
$file  = basename($_POST['file'][$index] ?? '');
file_put_contents($log, "[" . date('Y-m-d H:i:s') . "] [saveblock] Raw file: $file (index=$index)\n", FILE_APPEND);

if (!preg_match('/^fanctrlplus_[A-Za-z0-9_\-]+\.cfg$/', $file)) {
  file_put_contents($log, "[" . date('Y-m-d H:i:s') . "] [saveblock] Invalid file: $file\n", FILE_APPEND);
  json_response(['status' => 'error', 'message' => 'Invalid config file name']);
}
if (!$file) {
  json_response(['status' => 'error', 'message' => 'Missing file name']);
}

$cfg_dir  = "/boot/config/plugins/$plugin";
$cfg_path = "$cfg_dir/$file";

// 收集字段
$custom     = trim($_POST['custom'][$index] ?? '');
$controller = trim($_POST['controller'][$index] ?? '');
$pwm        = trim($_POST['pwm'][$index] ?? '');
$low        = trim($_POST['low'][$index] ?? '');
$high       = trim($_POST['high'][$index] ?? '');
$interval   = trim($_POST['interval'][$index] ?? '');
$service    = trim($_POST['service'][$index] ?? '');
$disks_arr  = $_POST['disks'][$index] ?? [];
$disks      = implode(',', array_map('trim', (array)$disks_arr));

if ($custom === '') {
  json_response(['status' => 'error', 'message' => 'Custom name is required']);
}

$ini = [
  'custom'     => $custom,
  'service'    => $service,
  'controller' => $controller,
  'pwm'        => $pwm,
  'low'        => $low,
  'high'       => $high,
  'interval'   => $interval,
  'disks'      => $disks
];

// 写入 .cfg 文件
$lines = [];
foreach ($ini as $k => $v) {
  $lines[] = $k . '="' . str_replace('"', '\"', $v) . '"';
}

if (!file_put_contents($cfg_path, implode("\n", $lines))) {
  json_response(['status' => 'error', 'message' => "Failed to write config"]);
}

file_put_contents($log, "[" . date('Y-m-d H:i:s') . "] [saveblock] Saved: $cfg_path\n", FILE_APPEND);

json_response(['status' => 'ok']);
