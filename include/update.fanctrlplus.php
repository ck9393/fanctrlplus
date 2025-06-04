<?PHP
$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";

if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

// 用于记录已经处理过哪些 PWM，避免重复
$seen_controllers = [];

foreach ($_POST['controller'] as $index => $controller) {
  if (empty($controller)) continue;
  $pwm_name = basename($controller); // e.g., pwm6
  if (in_array($pwm_name, $seen_controllers)) continue;
  $seen_controllers[] = $pwm_name;

  $cfg = [
    'service'    => $_POST['service'][$index] ?? '0',
    'controller' => $controller,
    'pwm'        => $_POST['pwm'][$index] ?? '',
    'low'        => $_POST['low'][$index] ?? '',
    'high'       => $_POST['high'][$index] ?? '',
    'interval'   => $_POST['interval'][$index] ?? '',
    'disks'      => isset($_POST['disks'][$index]) ? implode(',', $_POST['disks'][$index]) : ''
  ];

  $filename = "$cfgpath/{$plugin}_$pwm_name.cfg";
  $content = '';
  foreach ($cfg as $k => $v) {
    $content .= "$k=\"$v\"\n";
  }

  file_put_contents($filename, $content);
}

// 停止旧进程并重新加载脚本
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan stop");
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan start");
?>
