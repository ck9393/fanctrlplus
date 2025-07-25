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
  case 'detect':
    $pwm = $_GET['pwm'] ?? '';
    if (is_file($pwm)) {
      $pwm_enable = $pwm . "_enable";
      $default_method = @file_get_contents($pwm_enable);
      $default_rpm = @file_get_contents($pwm);
      @file_put_contents($pwm_enable, "1");
      @file_put_contents($pwm, "150");
      sleep(3);
      $init_fans = list_fan();
      @file_put_contents($pwm, "255");
      sleep(3);
      $final_fans = list_fan();
      @file_put_contents($pwm, $default_rpm);
      @file_put_contents($pwm_enable, $default_method);
      for ($i = 0; $i < count($final_fans); $i++) {
        if (($final_fans[$i]['rpm'] - $init_fans[$i]['rpm']) > 0) {
          echo $init_fans[$i]['sensor'];
          exit;
        }
      }
    }
    exit;

  case 'pwm':
    $pwm = $_GET['pwm'] ?? '';
    $fan = $_GET['fan'] ?? '';
    if (is_file($pwm) && is_file($fan)) {
      $autofan = "$docroot/plugins/$plugin/scripts/rc.fanctrlplus";
      exec("$autofan stop >/dev/null");

      $fan_min = explode("_", $fan)[0] . "_min";
      $default_method = @file_get_contents($pwm . "_enable");
      $default_pwm = @file_get_contents($pwm);
      $default_fan_min = @file_get_contents($fan_min);

      @file_put_contents($pwm . "_enable", "1");
      @file_put_contents($fan_min, "0");
      @file_put_contents($pwm, "0");
      sleep(5);

      $min_rpm = @file_get_contents($fan);
      for ($i = 0; $i <= 20; $i++) {
        $val = $i * 5;
        @file_put_contents($pwm, "$val");
        sleep(2);
        if ((@file_get_contents($fan) - $min_rpm) > 15) {
          $is_lowest = true;
          for ($j = 0; $j <= 10; $j++) {
            if (@file_get_contents($fan) == 0) {
              $is_lowest = false;
              break;
            }
            sleep(1);
          }
          if ($is_lowest) {
            echo $val;
            break;
          }
        }
      }

      @file_put_contents($pwm, $default_pwm);
      @file_put_contents($fan_min, $default_fan_min);
      @file_put_contents($pwm . "_enable", $default_method);
      exec("$autofan start >/dev/null");
    }
    exit;
    
  case 'pause':
    $pwm = $_GET['pwm'] ?? '';
    if (is_file($pwm)) {
      $original_pwm  = trim(@file_get_contents($pwm));
      $pwm_enable    = $pwm . "_enable";
      $original_mode = is_file($pwm_enable) ? trim(@file_get_contents($pwm_enable)) : '2';
  
      @file_put_contents($pwm_enable, "1");
      @file_put_contents($pwm, "0");
  
      $restore_cmd = "sleep 30 && echo " . escapeshellarg($original_mode) . " > " . escapeshellarg($pwm_enable) .
                     " && echo " . escapeshellarg($original_pwm) . " > " . escapeshellarg($pwm);
      exec("nohup bash -c \"$restore_cmd\" >/dev/null 2>&1 &");
  
      json_response(['status' => 'ok', 'message' => 'Fan paused for 30 seconds']);
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
    file_put_contents($temp_file, "custom=\"\"\nservice=\"1\"\ncontroller=\"\"\npwm=\"102\"\nmax=\"255\"\nlow=\"40\"\nhigh=\"60\"\ninterval=\"2\"\ndisks=\"\"\nsyslog=\"1\"");

    require_once "$docroot/plugins/$plugin/include/FanBlockRender.php";
    $cfg = parse_ini_file($temp_file);
    $cfg['file'] = basename($temp_file);

    // ✅ 页面传来的 index 决定 <input name="x[INDEX]"> 的值
    $page_index = intval($_REQUEST['index'] ?? 99);
    $pwms = list_pwm();
    $disks = list_valid_disks_by_id();

    header('Content-Type: text/html; charset=utf-8');
    echo render_fan_block($cfg, $page_index, $pwms, $disks, $pwm_labels);
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
}
?>