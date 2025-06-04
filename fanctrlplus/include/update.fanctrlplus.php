<?PHP
$plugin  = 'fanctrlplus';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

$new = isset($default) ? array_replace_recursive($_POST, $default) : $_POST;

// ✅ 清理旧的配置文件
$cfg_dir = "/boot/config/plugins/$plugin";
array_map('unlink', glob("$cfg_dir/{$plugin}_*.cfg"));

// ✅ 为每组配置生成一个独立 cfg 文件
foreach ($new['controller'] as $i => $ctrl) {
  $cfg = [];

  // 写入基础配置字段
  foreach (['service','controller','fan','pwm','low','high','interval'] as $key) {
    $val = $new[$key][$i] ?? '';
    $val = str_replace('"', '\"', $val); // 防止破坏 INI 格式
    $cfg[] = "$key=\"$val\"";
  }

  // 处理 disks 多选
  $disks = $new['disks'][$i] ?? [];
  if (is_array($disks)) {
    $disk_str = implode(',', $disks);
    $disk_str = str_replace('"', '\"', $disk_str);
    $cfg[] = "disks=\"$disk_str\"";
  }

  // 写入单个 .cfg 文件
  file_put_contents("$cfg_dir/{$plugin}_$i.cfg", implode("\n", $cfg) . "\n");
}

// ✅ 重启脚本
$autofan = "$docroot/plugins/$plugin/scripts/rc.autofan";
exec("$autofan stop > /dev/null 2>&1");
exec("nohup bash -c '$autofan start' > /dev/null 2>&1 &");
?>