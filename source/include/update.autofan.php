<?PHP
/* Copyright 2012-2023, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * Plugin development contribution by gfjardim
 */
?>
<?PHP
$plugin  = 'dynamix.system.autofan';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

$new = isset($default) ? array_replace_recursive($_POST, $default) : $_POST;
$options = '';

foreach ($new as $key => $value) {
  if (!strlen($value) && $value !== '0') continue;

  switch ($key) {
    case '#prefix':
      parse_str($value, $prefix);
      break;

    case 'disks':
      // 多选处理：数组转为逗号分隔字符串
      if (is_array($value)) {
        $value = implode(',', $value);
        $new['disks'] = $value;  // 确保写入 config 用的是字符串
      }
      $options .= (isset($prefix[$key]) ? "-{$prefix[$key]} " : "") . $value . " ";
      break;

    case 'service':
      // service 不拼接进 $options，由 rc.autofan 读取 config 决定是否启用
      break;

    default:
      if ($key[0] != '#') {
        $options .= (isset($prefix[$key]) ? "-{$prefix[$key]} " : "") . $value . " ";
      }
      break;
  }
}

// 写入 .cfg 配置文件
$cfgfile = "/boot/config/plugins/$plugin/{$plugin}.cfg";
file_put_contents($cfgfile, '');
foreach ($new as $key => $value) {
  if ($key[0] != '#' && strlen($value)) {
    file_put_contents($cfgfile, "$key=\"$value\"\n", FILE_APPEND);
  }
}

// 重启 autofan 控制脚本
$autofan = "$docroot/plugins/$plugin/scripts/rc.autofan";
exec("$autofan stop >/dev/null");
$keys['options'] = trim($options);
$_POST['#command'] = $autofan;
$_POST['#arg'][1] = 'start';
?>