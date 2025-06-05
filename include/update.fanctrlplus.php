<?php
$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";

// 确保配置目录存在
if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

$used_files = [];

if (!isset($_POST['#file']) || !is_array($_POST['#file'])) {
  header('Content-Type: application/json');
  echo json_encode(['status' => 'error', 'message' => 'No fan config received']);
  exit;
}

foreach ($_POST['#file'] as $i => $file) {
  $old_file = basename($file);
  $controller = $_POST['controller'][$i] ?? '';
  $custom = trim($_POST['custom'][$i] ?? '');

  // 如果是临时配置（temp），但没有填写 Custom Name，跳过
  if (strpos($old_file, 'temp') !== false && $custom === '') {
    continue;
  }

  // 安全处理 Custom 名字
  $safe_custom = preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $custom));

  // 临时文件改名为带 custom 的新文件名
  if (strpos($old_file, 'temp') !== false && !empty($controller)) {
    $base = str_replace('/', '_', basename($controller)); // e.g., pwm6
    $new_file = $plugin . "_$safe_custom.cfg";
  } else {
    $new_file = $old_file;
  }

  // 避免冲突
  $basefile = pathinfo($new_file, PATHINFO_FILENAME);
  $suffix = 1;
  while (in_array($new_file, $used_files)) {
    $new_file = $basefile . "_$suffix.cfg";
    $suffix++;
  }

  $used_files[] = $new_file;
  $filepath = "$cfgpath/$new_file";

  $cfg = [
    'custom'    => $custom,
    'service'   => $_POST['service'][$i] ?? '0',
    'controller'=> $controller,
    'pwm'       => $_POST['pwm'][$i] ?? '',
    'low'       => $_POST['low'][$i] ?? '',
    'high'      => $_POST['high'][$i] ?? '',
    'interval'  => $_POST['interval'][$i] ?? '',
    'disks'     => isset($_POST['disks'][$i]) ? implode(',', $_POST['disks'][$i]) : ''
  ];

  $content = '';
  foreach ($cfg as $k => $v) {
    $v = str_replace('"', '', $v);
    $content .= "$k=\"$v\"\n";
  }

  file_put_contents($filepath, $content);

  // 删除旧 temp 文件
  if ($old_file !== $new_file && is_file("$cfgpath/$old_file")) {
    unlink("$cfgpath/$old_file");
  }
}

// 删除未使用的旧配置文件
foreach (glob("$cfgpath/{$plugin}_*.cfg") as $cfgfile) {
  $base = basename($cfgfile);
  if (!in_array($base, $used_files)) {
    @unlink($cfgfile);
  }
}

// 重启 fanctrlplus 脚本（后台执行）
$script = "/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan";
exec("bash $script stop > /dev/null 2>&1 &");
sleep(1);
exec("bash $script start > /dev/null 2>&1 &");

// 返回响应
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
exit;
