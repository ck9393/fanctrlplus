<?PHP
$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";

if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

$files = $_POST['#file'] ?? [];
foreach ($files as $index => $file) {
  $path = "/boot/config/plugins/$plugin/" . basename($file);

  $cfg = [
    'service'   => $_POST['service'][$index] ?? '0',
    'controller'=> $_POST['controller'][$index] ?? '',
    'pwm'       => $_POST['pwm'][$index] ?? '',
    'low'       => $_POST['low'][$index] ?? '',
    'high'      => $_POST['high'][$index] ?? '',
    'interval'  => $_POST['interval'][$index] ?? '',
    'disks'     => isset($_POST['disks'][$index]) ? implode(',', $_POST['disks'][$index]) : ''
  ];

  $content = '';
  foreach ($cfg as $k => $v) {
    $content .= "$k=\"$v\"\n";
  }

  file_put_contents($path, $content);
}

// 重新启动脚本
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan stop");
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan start");
?>
