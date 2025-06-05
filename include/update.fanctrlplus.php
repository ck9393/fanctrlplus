<?PHP
$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";

if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

// 清除旧的 .cfg 文件（不含 default.cfg）
foreach (glob("$cfgpath/{$plugin}_*.cfg") as $file) {
  if (basename($file) != "default.cfg") unlink($file);
}

// 遍历提交的配置项
foreach ($_POST['controller'] ?? [] as $i => $controller) {
  if (empty($controller)) continue;

  $cfg = [
    'service'   => $_POST['service'][$i] ?? '0',
    'controller'=> $controller,
    'pwm'       => $_POST['pwm'][$i] ?? '',
    'low'       => $_POST['low'][$i] ?? '',
    'high'      => $_POST['high'][$i] ?? '',
    'interval'  => $_POST['interval'][$i] ?? '',
    'disks'     => isset($_POST['disks'][$i]) ? implode(',', $_POST['disks'][$i]) : ''
  ];

  $filename = "$cfgpath/{$plugin}_" . basename($controller) . ".cfg";
  $content = '';
  foreach ($cfg as $k => $v) {
    $content .= "$k=\"$v\"\n";
  }
  file_put_contents($filename, $content);
}

// 重启脚本
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan stop");
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan start");
