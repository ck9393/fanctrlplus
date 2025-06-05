<?PHP
$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";

if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

// 收集 PWM 配置，使用 controller 名称作为文件名
$index = 0;
$used_controllers = [];

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

  $basename = basename($controller);
  $used_controllers[] = $basename;
  $filename = "$cfgpath/{$plugin}_$basename.cfg";

  $content = '';
  foreach ($cfg as $k => $v) {
    $content .= "$k=\"$v\"\n";
  }

  file_put_contents($filename, $content);
  $index++;
}

// 删除未在使用中的旧配置文件
foreach (glob("$cfgpath/{$plugin}_*.cfg") as $cfgfile) {
  $base = basename($cfgfile, '.cfg');
  $suffix = substr($base, strlen($plugin) + 1);
  if (!in_array($suffix, $used_controllers)) {
    @unlink($cfgfile);
  }
}

// 重启 fanctrlplus 脚本
$script = "/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan";
exec("bash $script stop");
sleep(1);
exec("bash $script start");
