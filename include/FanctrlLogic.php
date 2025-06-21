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
register_shutdown_function(function () {
  $error = error_get_last();
  if ($error !== null) {
    file_put_contents('/tmp/fanctrlplus_error.log', print_r($error, true), FILE_APPEND);
    json_response(['status' => 'error', 'message' => 'PHP Fatal error']);
  }
});

$plugin  = 'fanctrlplus';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/plugins/$plugin/include/Common.php";

try {
  $op = $_GET['op'] ?? $_POST['op'] ?? '';

  if ($op === 'saveblock') {
    require "$docroot/plugins/$plugin/include/FanctrlSaveBlock.php";
    exit;
  }

  if ($op === 'newtemp') {
    require "$docroot/plugins/$plugin/include/FanctrlNewTemp.php";
    exit;
  }

  if ($op === 'status') {
    require "$docroot/plugins/$plugin/include/FanctrlStatus.php";
    exit;
  }

  if ($op === 'status_all') {
    require "$docroot/plugins/$plugin/include/FanctrlStatusAll.php";
    exit;
  }

  if ($op === 'start') {
    shell_exec("/etc/rc.d/rc.fanctrlplus start");
    json_response(['status' => 'started']);
  }

  if ($op === 'stop') {
    shell_exec("/etc/rc.d/rc.fanctrlplus stop");
    json_response(['status' => 'stopped']);
  }

  if ($op === 'pause') {
    require "$docroot/plugins/$plugin/include/FanctrlPause.php";
    exit;
  }

  if ($op === 'pwm') {
    require "$docroot/plugins/$plugin/include/FanctrlPwm.php";
    exit;
  }

  if ($op === 'detect') {
    require "$docroot/plugins/$plugin/include/FanctrlDetect.php";
    exit;
  }

  if ($op === 'delete') {
    require "$docroot/plugins/$plugin/include/FanctrlDelete.php";
    exit;
  }

  json_response(['status' => 'error', 'message' => "Unknown op: $op"]);
} catch (Throwable $e) {
  file_put_contents($log, "[EXCEPTION] " . $e->getMessage() . "\n", FILE_APPEND);
  json_response(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()]);
}
