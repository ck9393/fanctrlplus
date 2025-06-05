<?php
$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";

// 确保配置目录存在
if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

// 收集 PWM 配置，使用 controller 名称作为唯一标识符
$index = 0;
$used_controllers = [];

while (isset($_POST["controller"][$index])) {
  $controller = trim($_POST["controller"][$index] ?? '');
  if ($controller === '') {
    $index++;
    continue;
  }

  $cfg = [
    'service'   => $_POST['service'][$index] ?? '0',
    'controller'=> $controller,
    'pwm'       => $_POST['pwm'][$index] ?? '',
    'low'       => $_POST['low'][$index] ?? '',
    'high'      => $_POST['high'][$index] ?? '',
    'interval'  => $_POST['interval'][$index] ?? '',
    'disks'     => isset($_POST['disks'][$index]) ? implode(',', $_POST['disks'][$index]) : ''
  ];

  // 用 controller 的 basename 替代原始文件名，确保合法文件名
  $basename = str_replace('/', '_', basename($controller));
  $used_controllers[] = $basename;
  $filename = "$cfgpath/{$plugin}_$basename.cfg";

  $content = '';
  foreach ($cfg as $k => $v) {
    $content .= "$k=\"$v\"\n";
  }

  file_put_contents($filename, $content);
  $index++;
}

// 删除未使用的旧配置文件
foreach (glob("$cfgpath/{$plugin}_*.cfg") as $cfgfile) {
  $base = basename($cfgfile, '.cfg');
  $suffix = substr($base, strlen($plugin) + 1);
  if (!in_array($suffix, $used_controllers)) {
    @unlink($cfgfile);
  }
}

// 重启 fanctrlplus 脚本（后台执行，避免阻塞 UI）
$script = "/usr/local/emhttp/plugins/$plugin/scripts/rc.autofan";
exec("bash $script stop > /dev/null 2>&1 &");
sleep(1);
exec("bash $script start > /dev/null 2>&1 &");

// 返回成功状态，供 .page 接收防止 UI 卡死
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
exit;
