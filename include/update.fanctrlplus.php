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
  $custom = $_POST['custom'][$i] ?? '';

  // ⚠️ 若是临时文件名，则重命名为 pwm 或 pwm_custom 格式
  if (strpos($old_file, 'temp') !== false && !empty($controller)) {
    $base = str_replace('/', '_', basename($controller)); // e.g., pwm6
    $safe_custom = preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $custom));
    $new_file = $plugin . '_' . $base;
    if (!empty($safe_custom)) {
      $new_file .= "_$safe_custom";
    }
    $new_file .= '.cfg';

    // 若新文件名已存在，不重复添加
    if (in_array($new_file, $used_files)) {
      $suffix = 1;
      while (in_array("{$plugin}_{$base}_{$safe_custom}_$suffix.cfg", $used_files)) {
        $suffix++;
      }
      $new_file = "{$plugin}_{$base}_{$safe_custom}_$suffix.cfg";
    }
  } else {
    $new_file = $old_file;
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

  // 如果旧文件不同，删除旧文件（避免残留 temp0.cfg）
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
