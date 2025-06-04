<?PHP
$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";

if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

$index = 0;
while (isset($_POST["controller"][$index])) {
  $cfg = [
    'service'   => $_POST['service'][$index] ?? '0',
    'controller'=> $_POST['controller'][$index] ?? '',
    'pwm'       => $_POST['pwm'][$index] ?? '',
    'low'       => $_POST['low'][$index] ?? '',
    'high'      => $_POST['high'][$index] ?? '',
    'interval'  => $_POST['interval'][$index] ?? '',
    'disks'     => isset($_POST['disks'][$index]) ? implode(',', $_POST['disks'][$index]) : ''
  ];

  $filename = "$cfgpath/{$plugin}_{$index}.cfg";
  $content = '';
  foreach ($cfg as $k => $v) {
    $content .= "$k=\"$v\"\n";
  }

  file_put_contents($filename, $content);
  $index++;
}

// 停止旧进程并重新加载脚本
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan stop");
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan start");
?>
