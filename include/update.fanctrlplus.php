<?php
ob_start(); // 开启缓冲，防止意外输出破坏 JSON

$plugin = 'fanctrlplus';
$cfgpath = "/boot/config/plugins/$plugin";
$rename_map = [];
$used_files = [];
$docroot = $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

if (!is_dir($cfgpath)) {
  mkdir($cfgpath, 0777, true);
}

header('Content-Type: application/json');

// 校验提交数据结构
if (!isset($_POST['#file']) || !is_array($_POST['#file'])) {
  ob_clean();
  echo json_encode(['status' => 'error', 'message' => 'No fan config received']);
  exit;
}

foreach ($_POST['#file'] as $i => $file) {
  $old_file = basename($file);
  $controller = $_POST['controller'][$i] ?? '';
  $custom = trim($_POST['custom'][$i] ?? '');
  $interval = $_POST['interval'][$i] ?? '';
  $expected_file = $plugin . '_' . $custom . '.cfg';
  $old_path = "$cfgpath/$old_file";
  $new_path = "$cfgpath/$expected_file";

  // Custom Name 不能为空
  if ($custom === '') {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => "Custom Name is required."]);
    exit;
  }

  // 校验 Custom Name 合法性（仅允许 A-Z a-z 0-9 和 _）
  if (!preg_match('/^[A-Za-z0-9_]+$/', $custom)) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => "Custom Name can only contain letters, numbers, and underscores."]);
    exit;
  }

  if (stripos($custom, 'temp_') !== false) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Custom Name cannot contain "temp_".']);
    exit;
  }

  // 检查是否已有相同 custom 名称的 cfg
  foreach (glob("$cfgpath/{$plugin}_*.cfg") as $existing) {
    $info = parse_ini_file($existing);
    if (isset($info['custom']) && trim($info['custom']) === $custom) {
      // 排除自身（重命名 temp → 正式名时允许自己）
      if (basename($existing) !== $old_file) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => "Custom Name \"$custom\" is already used."]);
        exit;
      }
    }
  }
  
  //重命名custom name后 cfg文件名同步重命名
  if ($old_file !== $expected_file) {
      if (file_exists($old_path)) {
          rename($old_path, $new_path);
      }
      require_once "$docroot/plugins/$plugin/include/OrderManager.php";
      OrderManager::replaceFileName($old_file, $expected_file);
      $rename_map[$old_file] = $expected_file;
      $old_file = $expected_file;
      $old_path = $new_path;
  }

  file_put_contents($old_path, "custom=\"$custom\"\n...");

  // 校验 interval 合法性（必须为正整数）
  if (!ctype_digit($interval) || intval($interval) <= 0) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => "Interval cannot be empty or 0 (recommended: 1–5 min)."]);
    exit;
  } 

  // === 临时文件：以 custom 命名为正式文件 ===
  if (strpos($old_file, 'temp_') !== false && !empty($controller)) {
    $new_file = $plugin . "_$custom.cfg";
    $rename_map[$old_file] = $new_file;
  } else {
    $new_file = $old_file;
  }  

  // 避免命名冲突
  $basefile = pathinfo($new_file, PATHINFO_FILENAME);
  $suffix = 1;
  while (in_array($new_file, $used_files)) {
    $new_file = $basefile . "_$suffix.cfg";
    $suffix++;
  }

  $used_files[] = $new_file;
  $filepath = "$cfgpath/$new_file";

  // 拼接配置内容
  $cfg = [
    'custom'     => $custom,
    'label'      => $custom,
    'service'    => $_POST['service'][$i] ?? '0',
    'controller' => $controller,
    'pwm'        => $_POST['pwm'][$i] ?? '',
    'low'        => $_POST['low'][$i] ?? '',
    'high'       => $_POST['high'][$i] ?? '',
    'interval'   => $_POST['interval'][$i] ?? '',
    'disks'      => isset($_POST['disks'][$i]) ? implode(',', $_POST['disks'][$i]) : ''
  ];

  $content = '';
  foreach ($cfg as $k => $v) {
    $v = str_replace('"', '', $v);
    $content .= "$k=\"$v\"\n";
  }

  file_put_contents($filepath, $content, LOCK_EX);

  // 删除旧临时文件
  if ($old_file !== $new_file && is_file("$cfgpath/$old_file")) {
    @unlink("$cfgpath/$old_file");
  }
}

// 删除未使用的旧 cfg 文件
foreach (glob("$cfgpath/{$plugin}_*.cfg") as $cfgfile) {
  $base = basename($cfgfile);
  if (!in_array($base, $used_files)) {
    @unlink($cfgfile);
  }
}

// === 写入 order.cfg 排序顺序（转移至OrderManager。php）===
require_once "$docroot/plugins/fanctrlplus/include/OrderManager.php";

$order_left = array_map(function($f) use ($rename_map) {
  return $rename_map[$f] ?? $f;
}, $_POST['order_left'] ?? []);

$order_right = array_map(function($f) use ($rename_map) {
  return $rename_map[$f] ?? $f;
}, $_POST['order_right'] ?? []);

OrderManager::writeOrder(array_values($order_left), array_values($order_right));

// 重启 fanctrlplus 守护进程
$script = "/usr/local/emhttp/plugins/$plugin/scripts/rc.fanctrlplus";
if (is_file($script)) {
  exec("bash $script stop > /dev/null 2>&1");
  sleep(1);
  exec("bash $script start > /dev/null 2>&1");
}

ob_clean();
echo json_encode(['status' => 'ok']);
exit;
