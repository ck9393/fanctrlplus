<?PHP
$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";

if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

// 获取 PWM 对应 fan_input 路径
function get_fan_path($controller) {
  if (preg_match('/pwm(\d+)$/', $controller, $m)) {
    $fan_path = dirname($controller) . "/fan{$m[1]}_input";
    return file_exists($fan_path) ? $fan_path : '';
  }
  return '';
}

// 收集所有 PWM 设置
$used_controllers = [];
$index = 0;
while (isset($_POST["controller"][$index])) {
  $controller = $_POST["controller"][$index] ?? '';
  if ($controller == '') { $index++; continue; }

  $fan = get_fan_path($controller);
  $used_controllers[] = basename($controller);

  $cfg = [
    'service'    => $_POST['service'][$index] ?? '0',
    'controller' => $controller,
    'fan'        => $fan,
    'pwm'        => $_POST['pwm'][$index] ?? '',
    'low'        => $_POST['low'][$index] ?? '',
    'high'       => $_POST['high'][$index] ?? '',
    'interval'   => $_POST['interval'][$index] ?? '',
    'disks'      => isset($_POST['disks'][$index]) ? implode(',', $_POST['disks'][$index]) : ''
  ];

  $filename = "$cfgpath/{$plugin}_" . basename($controller) . ".cfg";
  $content = '';
  foreach ($cfg as $k => $v) {
    $content .= "$k=\"$v\"\n";
  }

  file_put_contents($filename, $content);
  $index++;
}

// 删除已移除的旧 cfg
foreach (glob("$cfgpath/{$plugin}_*.cfg") as $cfgfile) {
  $basename = basename($cfgfile, '.cfg');
  $suffix = str_replace("{$plugin}_", '', $basename);
  if (!in_array($suffix, $used_controllers)) {
    @unlink($cfgfile);
  }
}

// 重启控制逻辑
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan stop");
exec("/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan start");
?>
