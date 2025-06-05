<?php
$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";

// 确保配置目录存在
if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

$used_files = [];

foreach ($_POST['#file'] as $i => $file) {
  $filename = basename($file);  // 安全处理
  $filepath = "$cfgpath/$filename";

  $cfg = [
    'service'   => $_POST['service'][$i] ?? '0',
    'controller'=> $_POST['controller'][$i] ?? '',
    'pwm'       => $_POST['pwm'][$i] ?? '',
    'low'       => $_POST['low'][$i] ?? '',
    'high'      => $_POST['high'][$i] ?? '',
    'interval'  => $_POST['interval'][$i] ?? '',
    'disks'     => isset($_POST['disks'][$i]) ? implode(',', $_POST['disks'][$i]) : ''
  ];

  $used_files[] = $filename;

  $content = '';
  foreach ($cfg as $k => $v) {
    $content .= "$k=\"$v\"\n";
  }

  file_put_contents($filepath, $content);
}

// 删除未使用的旧配置文件
foreach (glob("$cfgpath/{$plugin}_*.cfg") as $cfgfile) {
  $base = basename($cfgfile);
  if (!in_array($base, $used_files)) {
    @unlink($cfgfile);
  }
}

// 重启 fanctrlplus 脚本（后台运行）
$script = "/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan";
exec("bash $script stop > /dev/null 2>&1 &");
sleep(1);
exec("bash $script start > /dev/null 2>&1 &");

// 返回响应
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
exit;
