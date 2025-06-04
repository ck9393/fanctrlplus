<?PHP
$plugin  = 'fanctrlplus';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

$files = $_POST['#file'] ?? [];
$prefix = [];
parse_str($_POST['#prefix'] ?? '', $prefix);

// 遍历每组配置（支持多个）
foreach ($files as $i => $file) {
  $path = "/boot/config/plugins/$file";
  $new = [];

  foreach ($_POST as $key => $val) {
    if ($key[0] == '#') continue;

    // 处理数组参数：如 service[0]、controller[0]...
    if (is_array($val) && isset($val[$i])) {
      $v = $val[$i];
      if (is_array($v)) {
        $v = implode(',', $v);
      }
      $new[$key] = $v;
    }
  }

  // 写入该 cfg 文件
  file_put_contents($path, '');
  foreach ($new as $k => $v) {
    $v = str_replace('"', '\"', $v);
    file_put_contents($path, "$k=\"$v\"\n", FILE_APPEND);
  }
}

// 重启风扇控制脚本
$autofan = "$docroot/plugins/$plugin/scripts/rc.autofan";
exec("$autofan stop >/dev/null 2>&1");
exec("nohup bash -c '$autofan start' >/dev/null 2>&1 &");
?>
