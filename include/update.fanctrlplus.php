<?php
ob_start(); // 开启缓冲，防止意外输出破坏 JSON

$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";

if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

$used_files = [];

header('Content-Type: application/json');

// 校验提交数据结构
if (!isset($_POST['#file']) || !is_array($_POST['#file'])) {
  ob_clean();
  echo json_encode(['status' => 'error', 'message' => 'No fan config received']);
  exit;
}

foreach ($_POST['#file'] as $i => $file) {
  $old_file = basename($file);
  $controller = $_POST['controller'][$i] ?? '';
  $custom = trim($_POST['custom'][$i] ?? '');

  // Custom Name 不能为空
  if ($custom === '') {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => "Custom Name is required."]);
    exit;
  }

  // 安全 Custom Name → 用作配置文件名
  $safe_custom = preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $custom));

  // 如为临时文件则重命名
  if (strpos($old_file, 'temp') !== false && !empty($controller)) {
    $new_file = $plugin . "_$safe_custom.cfg";
  } else {
    $new_file = $old_file;
  }

  // 避免命名冲突
  $basefile = pathinfo($new_file, PATHINFO_FILENAME);
  $suffix = 1;
  while (in_array($new_file, $used_files)) {
    $new_file = $basefile . "_$suffix.cfg";
    $suffix++;
  }

  $used_files[] = $new_file;
  $filepath = "$cfgpath/$new_file";

  // 拼接配置内容
  $cfg = [
    'custom'     => $custom,
    'label'      => $custom,
    'service'    => $_POST['service'][$i] ?? '0',
    'controller' => $controller,
    'pwm'        => $_POST['pwm'][$i] ?? '',
    'low'        => $_POST['low'][$i] ?? '',
    'high'       => $_POST['high'][$i] ?? '',
    'interval'   => $_POST['interval'][$i] ?? '',
    'disks'      => isset($_POST['disks'][$i]) ? implode(',', $_POST['disks'][$i]) : ''
  ];

  $content = '';
  foreach ($cfg as $k => $v) {
    $v = str_replace('"', '', $v); // 移除双引号
    $content .= "$k=\"$v\"\n";
  }

  file_put_contents($filepath, $content, LOCK_EX);

  // 删除旧临时文件
  if ($old_file !== $new_file && is_file("$cfgpath/$old_file")) {
    @unlink("$cfgpath/$old_file");
  }
}

// 删除未使用的旧 cfg 文件
foreach (glob("$cfgpath/{$plugin}_*.cfg") as $cfgfile) {
  $base = basename($cfgfile);
  if (!in_array($base, $used_files)) {
    @unlink($cfgfile);
  }
}

// 重启 fanctrlplus 守护进程
$script = "/usr/local/emhttp/plugins/$plugin/scripts/rc.fanctrlplus";
if (is_file($script)) {
  exec("bash $script stop > /dev/null 2>&1");
  sleep(1);
  exec("bash $script start > /dev/null 2>&1");
}

ob_clean();
echo json_encode(['status' => 'ok']);
exit;
