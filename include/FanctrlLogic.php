<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$plugin  = 'fanctrlplus';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$cfg_dir = "/boot/config/plugins/$plugin";
$order_file = "$cfg_dir/order.cfg";
$label_file = "$cfg_dir/pwm_labels.cfg";

require_once "$docroot/plugins/$plugin/include/Common.php";
require_once "/usr/local/emhttp/plugins/fanctrlplus/include/OrderManager.php";

header('Content-Type: application/json');

$op = $_GET['op'] ?? $_POST['op'] ?? '';

if ($op === 'refresh_single' && !empty($_GET['custom'])) {
  $custom = escapeshellarg($_GET['custom']);
  shell_exec("/usr/local/emhttp/plugins/fanctrlplus/scripts/fanctrlplus_refresh_single.sh $custom > /dev/null 2>&1 &");
  exit('OK');
}

function json_response($data) {
  while (ob_get_level()) {
    ob_end_clean(); // 安全清除所有输出缓冲区，避免 notice 错误
  }
  header('Content-Type: application/json');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function scan_dir($dir) {
  $out = [];
  foreach (array_diff(scandir($dir), ['.','..']) as $f) {
    $out[] = realpath($dir) . '/' . $f;
  }
  return $out;
}

$op = $_GET['op'] ?? $_POST['op'] ?? '';

switch ($op) {
    
  case 'identify':
    $pwm  = $_GET['pwm']  ?? '';
    $mode = $_GET['mode'] ?? 'pause';  // 默认 pause
    if (is_file($pwm)) {
      $original_pwm  = trim(@file_get_contents($pwm));
      $pwm_enable    = $pwm . "_enable";
      $original_mode = is_file($pwm_enable) ? trim(@file_get_contents($pwm_enable)) : '2';

      // 强制切到手动
      @file_put_contents($pwm_enable, "1");

      if ($mode === 'pause') {
        // 直接停
        @file_put_contents($pwm, "0");
        $restore_cmd = "sleep 30 && echo " . escapeshellarg($original_mode) . " > " . escapeshellarg($pwm_enable) .
                      " && echo " . escapeshellarg($original_pwm) . " > " . escapeshellarg($pwm);

      } elseif ($mode === 'max') {
        // 拉满
        @file_put_contents($pwm, "255");
        $restore_cmd = "sleep 30 && echo " . escapeshellarg($original_mode) . " > " . escapeshellarg($pwm_enable) .
                      " && echo " . escapeshellarg($original_pwm) . " > " . escapeshellarg($pwm);

      } elseif ($mode === 'pulse') {
        // 10s 停 -> 10s 满速 -> 10s 停 -> 10s 满速
        $restore_cmd = 
          "echo 0   > " . escapeshellarg($pwm) . " && " .
          "sleep 10 && echo 255 > " . escapeshellarg($pwm) . " && " .
          "sleep 10 && echo 0   > " . escapeshellarg($pwm) . " && " .
          "sleep 10 && echo 255 > " . escapeshellarg($pwm) . " && " .
          "sleep 10 && echo " . escapeshellarg($original_mode) . " > " . escapeshellarg($pwm_enable) .
                      " && echo " . escapeshellarg($original_pwm) . " > " . escapeshellarg($pwm);

      } else {
        json_response(['status' => 'error', 'message' => 'Unknown identify mode']);
        break;
      }

      exec("nohup bash -c \"$restore_cmd\" >/dev/null 2>&1 &");
      json_response(['status' => 'ok', 'message' => "Fan identify ($mode) started"]);

    } else {
      json_response(['status' => 'error', 'message' => 'Invalid PWM path']);
    }
    break;

  case 'savelabel':
    $pwm = $_POST['pwm'] ?? '';
    $label = $_POST['label'] ?? '';

    $label_file = "/boot/config/plugins/fanctrlplus/pwm_labels.cfg";
    // 读取现有label
    $lines = is_file($label_file) ? file($label_file, FILE_IGNORE_NEW_LINES) : [];
    $found = false;

    if (!$pwm) {
      json_response(['status' => 'error', 'message' => 'Missing pwm']);
      break;
    }

    // 空label表示删除
    if ($label === '') {
      $new_lines = [];
      foreach ($lines as $line) {
        if (strpos($line, "$pwm=") !== 0) $new_lines[] = $line;
      }
      file_put_contents($label_file, implode("\n", $new_lines) . "\n");
      json_response(['status' => 'ok', 'message' => 'Label removed']);
      break;
    }

    // 正常写入label
    foreach ($lines as &$line) {
      if (strpos($line, "$pwm=") === 0) {
        $line = "$pwm=$label";
        $found = true;
        break;
      }
    }
    if (!$found) $lines[] = "$pwm=$label";
    file_put_contents($label_file, implode("\n", $lines) . "\n");
    json_response(['status' => 'ok', 'message' => 'Label saved']);
    break;
  
  case 'newtemp':
    $cfg_dir = "/boot/config/plugins/$plugin";

    // 找 temp_X.cfg 文件名，不重复
    $index_cfg = 0;
    while (file_exists("$cfg_dir/{$plugin}_temp_$index_cfg.cfg")) {
      $index_cfg++;
    }

    $temp_file = "$cfg_dir/{$plugin}_temp_$index_cfg.cfg";
    file_put_contents($temp_file, <<<INI
    custom=""
    service="1"
    controller=""
    pwm="102"
    max="255"
    idle="0"
    low="40"
    high="60"
    interval="2"
    disks=""
    syslog="1"
    cpu_enable="0"
    cpu_sensor=""
    cpu_min_temp=""
    cpu_max_temp=""
    INI
    );

    require_once "$docroot/plugins/$plugin/include/FanBlockRender.php";
    $cfg = parse_ini_file($temp_file);
    $cfg['file'] = basename($temp_file);

    // ✅ 页面传来的 index 决定 <input name="x[INDEX]"> 的值
    $page_index = intval($_REQUEST['index'] ?? 99);
    $pwms = list_pwm();
    $disks = list_valid_disks_by_id();
    $cpu_sensors = detect_cpu_sensors();

    header('Content-Type: text/html; charset=utf-8');
    echo render_fan_block($cfg, $page_index, $pwms, $disks, $pwm_labels, $cpu_sensors); 
    exit;

  case 'setsyslog':
      $cfg_file = basename($_POST['cfg']);
      $enabled = isset($_POST['enabled']) && $_POST['enabled'] == 1 ? 1 : 0;

      $cfg_dir = "/boot/config/plugins/fanctrlplus";
      $cfg_path = "$cfg_dir/$cfg_file";

      if (file_exists($cfg_path)) {
          $lines = file($cfg_path, FILE_IGNORE_NEW_LINES);
          $found = false;
          foreach ($lines as &$line) {
              if (strpos($line, 'syslog=') === 0) {
                  $line = 'syslog="' . $enabled . '"';
                  $found = true;
              }
          }
          if (!$found) {
              $lines[] = 'syslog="' . $enabled . '"';
          }
          file_put_contents($cfg_path, implode("\n", $lines) . "\n");
          echo json_encode(['status' => 'ok']);
      } else {
          echo json_encode(['status' => 'error', 'msg' => 'Config file not found']);
      }
      exit;

  case 'delete':
    $file = basename($_POST['file'] ?? '');
    $cfgpath = "/boot/config/plugins/$plugin/$file";

    if (is_file($cfgpath)) {
      unlink($cfgpath);
    }

    OrderManager::remove($file);

    json_response(['status' => 'ok', 'message' => "Deleted $file"]);
    break;

  case 'status':
    $pid_files = glob("/var/run/fanctrlplus_*.pid");
    $running = false;
    foreach ($pid_files as $pidfile) {
      $pid = trim(@file_get_contents($pidfile));
      if (is_numeric($pid) && posix_kill((int)$pid, 0)) {
        $running = true;
        break;
      }
    }
  
    json_response(['status' => $running ? 'running' : 'stopped']);
    break;

  case 'status_all':
    $cfg_dir = "/boot/config/plugins/$plugin";
    $result = [];

    foreach (glob("$cfg_dir/{$plugin}_*.cfg") as $file) {
      $cfg = parse_ini_file($file);
      $name = trim($cfg['custom'] ?? '');
      $enabled = trim($cfg['service'] ?? '0') === '1';

      // 保持和 rc.fanctrlplus 的一致性（自定义名 → pid 文件名）
      $name_trimmed = trim($name);
      $custom_safe = preg_replace('/\W+/', '_', $name_trimmed);
      $pid_file = "/var/run/{$plugin}_{$custom_safe}.pid";
      $running = false;

      if ($enabled && file_exists($pid_file)) {
        $pid = trim(@file_get_contents($pid_file));
        if (is_numeric($pid) && posix_kill((int)$pid, 0)) {
          $running = true;
        }
      }

      if ($name !== '') {
        $result[basename($file)] = $running ? 'running' : 'stopped';
      }
    }
  
    json_response($result);
    break;

  case 'saveorder':
    error_log("[fanctrlplus] 🔥 saveorder triggered");

    $order_raw = $_POST['order'] ?? [];

    if (!is_array($order_raw)) {
      error_log("[fanctrlplus] ⚠️ order is not array: " . print_r($order_raw, true));
      json_response(['status' => 'error', 'message' => 'Order not array']);
    }

    $output = "";

    foreach (['left', 'right'] as $side) {
      if (!isset($order_raw[$side]) || !is_array($order_raw[$side])) continue;

      $valid = array_values(array_filter($order_raw[$side], function ($f) use ($cfg_dir) {
        return is_string($f) && trim($f) !== '' && is_file("$cfg_dir/$f");
      }));

      foreach ($valid as $i => $file) {
        $output .= "{$side}{$i}=\"$file\"\n";
      }
    }

    if ($output !== "") {
      file_put_contents("$cfg_dir/order.cfg", $output);
      json_response(['status' => 'ok']);
    } else {
      error_log("[fanctrlplus] ❌ Blocked invalid saveorder: " . print_r($order_raw, true));
      json_response(['status' => 'error', 'message' => 'Invalid order']);
    }
    break;
    
  case 'start':
    shell_exec("/etc/rc.d/rc.fanctrlplus start");
    json_response(['status' => 'started']);
    break;
  
  case 'stop':
    shell_exec("/etc/rc.d/rc.fanctrlplus stop");
    json_response(['status' => 'stopped']);
    break;

  case 'getpwm':
    $pwms = list_pwm();
    $label_file = "/boot/config/plugins/fanctrlplus/pwm_labels.cfg";
    $labels = [];
    if (is_file($label_file)) {
      foreach (file($label_file, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^(.+?)=(.+)$/', $line, $m)) {
          $labels[$m[1]] = $m[2];
        }
      }
    }
    foreach ($pwms as &$pwm) {
      $pwm['label'] = $labels[$pwm['sensor']] ?? '';
    }
    json_response($pwms);
    break;

  case 'read_temp_rpm':
    $custom = $_GET['custom'] ?? '';
    $custom = basename($custom); // 安全过滤

    $plugin = 'fanctrlplus';
    $temp_file = "/var/tmp/{$plugin}/temp_{$plugin}_{$custom}";
    $rpm_file  = "/var/tmp/{$plugin}/rpm_{$plugin}_{$custom}";

    $temp = is_file($temp_file) ? trim(file_get_contents($temp_file)) : '*';
    $rpm  = is_file($rpm_file)  ? trim(file_get_contents($rpm_file))  : '?';

    echo "$temp|$rpm";  // 示例："48 (CPU)|1150"
    exit;

  case 'fcp_airflow_toggle':
    
      $cfg_dir     = "/boot/config/plugins/fanctrlplus";
      $labels_file = $cfg_dir.'/pwm_labels.cfg';

      $enabled = (($_POST['enabled'] ?? '0') === '1');
      $lines   = is_file($labels_file) ? file($labels_file, FILE_IGNORE_NEW_LINES) : [];
      $found   = false;

      foreach ($lines as &$ln) {
          $t = trim($ln);
          if ($t === '' || $t[0] === '#') continue;
          if (preg_match('/^__FCP_AIRFLOW__\s*=/', $t)) {
              $ln = "__FCP_AIRFLOW__=" . ($enabled ? '1' : '0');
              $found = true;
              break;
          }
      }
      unset($ln);

      if (!$found) $lines[] = "__FCP_AIRFLOW__=" . ($enabled ? '1' : '0');

      @mkdir($cfg_dir, 0777, true);
      file_put_contents($labels_file, implode("\n", $lines) . "\n");

      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok'=>1, 'enabled'=>$enabled ? 1 : 0]);
      exit;
}
?>