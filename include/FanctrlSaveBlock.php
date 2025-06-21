<?php
// FanctrlSaveBlock.php
$plugin = 'fanctrlplus';
$cfg_dir = "/boot/config/plugins/$plugin";
$log = "/tmp/fanctrlplus_debug.log";

// 初始化
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Debug 起点日志
file_put_contents($log, "[" . date('Y-m-d H:i:s') . "] FanctrlSaveBlock called\n", FILE_APPEND);

// 校验参数
$index = $_POST['index'] ?? '';
if (!is_numeric($index)) {
  echo json_encode(['status' => 'error', 'message' => 'Invalid index']);
  exit;
}

$custom = trim($_POST['custom'][$index] ?? '');
if ($custom === '') {
  echo json_encode(['status' => 'error', 'message' => 'Custom Name cannot be empty']);
  exit;
}

// 安全过滤自定义名称（防止注入）
$custom = preg_replace('/[^a-zA-Z0-9_-]/', '_', $custom);

// 构建目标文件名
$cfg_file = "$cfg_dir/{$plugin}_$custom.cfg";

// 生成配置内容
$fields = [
  'custom', 'service', 'controller', 'pwm',
  'low', 'high', 'interval', 'label', 'exclude'
];

$lines = [];
foreach ($fields as $field) {
  if (!isset($_POST[$field][$index])) continue;
  $value = trim($_POST[$field][$index]);
  $value = str_replace('"', '', $value); // 移除引号防止写入错误
  $lines[] = "$field=\"$value\"";
}

// 多选 disks 特别处理
if (isset($_POST['disks'][$index]) && is_array($_POST['disks'][$index])) {
  $disk_val = implode(',', array_map('trim', $_POST['disks'][$index]));
  $lines[] = "disks=\"$disk_val\"";
} else {
  $lines[] = "disks=\"\"";
}

// 写入配置文件
if (!is_dir($cfg_dir)) {
  mkdir($cfg_dir, 0777, true);
}
if (file_put_contents($cfg_file, implode("\n", $lines) . "\n") === false) {
  echo json_encode(['status' => 'error', 'message' => 'Failed to save configuration']);
  exit;
}

// 成功日志
file_put_contents($log, "[" . date('Y-m-d H:i:s') . "] Saved: $cfg_file\n", FILE_APPEND);

// 成功响应
echo json_encode(['status' => 'ok', 'message' => "Saved: $cfg_file"]);
exit;
