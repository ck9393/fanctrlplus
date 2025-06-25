<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$plugin  = 'fanctrlplus';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$cfg_dir = "/boot/config/plugins/$plugin";
$order_file = "$cfg_dir/order.json";

require_once "$docroot/plugins/$plugin/include/Common.php";

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
  
  case 'newtemp':
    $cfg_dir = "/boot/config/plugins/$plugin";

    // 找 temp_X.cfg 文件名，不重复
    $index_cfg = 0;
    while (file_exists("$cfg_dir/{$plugin}_temp_$index_cfg.cfg")) {
      $index_cfg++;
    }

    $temp_file = "$cfg_dir/{$plugin}_temp_$index_cfg.cfg";
    file_put_contents($temp_file, "custom=\"\"\nservice=\"1\"\ncontroller=\"\"\npwm=\"100\"\nlow=\"40\"\nhigh=\"60\"\ninterval=\"2\"\ndisks=\"\"");

    require_once "$docroot/plugins/$plugin/include/FanBlockRender.php";
    $cfg = parse_ini_file($temp_file);
    $cfg['file'] = basename($temp_file);

    // ✅ 页面传来的 index 决定 <input name="x[INDEX]"> 的值
    $page_index = $_REQUEST['index'] ?? 99;  // fallback 防止 crash
    $pwms = list_pwm();
    $disks = list_valid_disks_by_id();

    echo render_fan_block($cfg, $page_index, $pwms, $disks);
    exit;

  case 'delete':
    $file = basename($_POST['file'] ?? '');
    $cfgpath = "/boot/config/plugins/$plugin/$file";

    if (is_file($cfgpath)) {
      unlink($cfgpath);

      // ===== 重建 order.cfg（排除 _temp_）=====
      $cfgdir = "/boot/config/plugins/$plugin";
      $cfgs = [];
      foreach (glob("$cfgdir/{$plugin}_*.cfg") as $f) {
        $b = basename($f);
        if (strpos($b, '_temp_') === false) {
          $cfgs[] = $b;
        }
      }

      $order_cfg = "";
      foreach ($cfgs as $i => $cfg_file) {
        $order_cfg .= 'order' . ($i+1) . '="' . $cfg_file . "\"\n";
      }
      file_put_contents("$cfgdir/order.cfg", $order_cfg);

      json_response(['status' => 'deleted', 'file' => $file]);
    } else {
      json_response(['status' => 'not_found', 'file' => $file]);
    }
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
        $result[$name] = $running ? 'running' : 'stopped';
      }
    }
  
    json_response($result);
    break;

  case 'saveorder':
    error_log("[fanctrlplus] 🔥 saveorder triggered");

    $order_raw = $_POST['order'] ?? [];
    if (!is_array($order_raw)) {
      error_log("[fanctrlplus] ⚠️ order is not array: ".print_r($order_raw, true));
      json_response(['status' => 'error', 'message' => 'Order not array']);
    }

    file_put_contents("/tmp/fanctrlplus_post_order_raw.log", print_r($order_raw, true));

    $valid = array_values(array_filter($order_raw, function ($f) use ($cfg_dir) {
      return is_string($f)
          && trim($f) !== ''
          && strpos($f, '_temp_') === false
          && is_file("$cfg_dir/$f");
    }));

    if (count($valid) > 0) {
      $output = "";
      foreach ($valid as $i => $file) {
        $output .= "order" . ($i + 1) . "=\"$file\"\n";
      }
      file_put_contents("$cfg_dir/order.cfg", $output);
      json_response(['status' => 'ok']);
    } else {
      error_log("[fanctrlplus] ❌ Blocked invalid saveorder: ".print_r($order_raw, true));
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
}
?>
