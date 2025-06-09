<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$plugin  = 'fanctrlplus';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

require_once "$docroot/plugins/$plugin/include/Common.php";

header('Content-Type: application/json');

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

      echo "Fan paused for 30 seconds";
    } else {
      echo "Invalid PWM path";
    }
    exit;

  case 'newtemp':
    $index = $_REQUEST['index'] ?? 0;
    $temp_file = "$cfg_dir/{$plugin}_temp_$index.cfg";
  
    if (!file_exists($temp_file)) {
      file_put_contents($temp_file, "custom=\"\"\nservice=\"1\"\ncontroller=\"\"\npwm=\"100\"\nlow=\"40\"\nhigh=\"60\"\ninterval=\"2\"\ndisks=\"\"");
    }
  
    require_once "$docroot/plugins/$plugin/include/FanBlockRender.php";
  
    $pwms = list_pwm();
    $disks = list_valid_disks_by_id();
  
    echo render_fan_block(basename($temp_file), $index, $pwms, $disks);
    error_log("[fanctrlplus] newtemp block output success");
    exit;

  case 'delete':
    $file = basename($_POST['file'] ?? '');
    $cfgpath = "/boot/config/plugins/$plugin/$file";
    if (is_file($cfgpath)) {
      unlink($cfgpath);
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
  
    error_log("[fanctrlplus] status result: " . ($running ? 'running' : 'stopped'));
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
  
    error_log("[fanctrlplus] status_all result: " . json_encode($result));
    json_response($result);
    break;

    case 'start':
      $rc = "$docroot/plugins/$plugin/scripts/rc.fanctrlplus";
      if (is_file($rc)) {
        exec("$rc start >/dev/null 2>&1 &");
        json_response(['status' => 'started']);
      } else {
        json_response(['error' => 'rc script not found']);
      }
      break;  // ✅ 必须加上
  
    case 'stop':
      $rc = "$docroot/plugins/$plugin/scripts/rc.fanctrlplus";
      exec("$rc stop >/dev/null 2>&1 &");
      json_response(['status' => 'stopped']);
      break;  // ✅ 必须加上
  
    default:
      json_response(['error' => 'Invalid op']);
}
?>
