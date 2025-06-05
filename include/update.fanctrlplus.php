<?PHP
$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";

if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

// 收集 PWM 配置，使用 controller 名称作为文件名
$index = 0;
while (isset($_POST["controller"][$index])) {
  $controller = $_POST["controller"][$index] ?? '';
  if ($controller == '') { $index++; continue; }

  $cfg = [
    'service'   => $_POST['service'][$index] ?? '0',
    'controller'=> $controller,
    'pwm'       => $_POST['pwm'][$index] ?? '',
    'low'       => $_POST['low'][$index] ?? '',
    'high'      => $_POST['high'][$index] ?? '',
    'interval'  => $_POST['interval'][$index] ?? '',
    'disks'     => isset($_POST['disks'][$index]) ? implode(',', $_POST['disks'][$index]) : ''
  ];

  $filename = "$cfgpath/{$plugin}_" . basename($controller) . ".cfg";

  $content = '';
  foreach ($cfg as $k => $v) {
    $content .= "$k=\"$v\"\n";
  }

  file_put_contents($filename, $content);
  $index++;
}

// 删除未包含在表单中的旧配置文件（清理被删除的 fan）
$used_controllers = array_map('basename', array_filter($_POST['controller'] ?? []));
foreach (glob("$cfgpath/{$plugin}_*.cfg") as $cfgfile) {
  if (!in_array(basename($cfgfile, '.cfg'), array_map(fn($c) => "{$plugin}_$c", $used_controllers))) {
    @unlink($cfgfile);
  }
}

// 重启插件控制逻辑
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan stop");
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan start");
