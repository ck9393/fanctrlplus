<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

$plugin  = 'fanctrlplus';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

header('Content-Type: application/json');

function json_response($data) {
  ob_clean();
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

function list_fan() {
  $out = [];
  exec("find /sys/devices -type f -iname 'fan[0-9]_input' -exec dirname \"{}\" + | uniq", $chips);
  foreach ($chips as $chip) {
    $name = is_file("$chip/name") ? @file_get_contents("$chip/name") : false;
    if ($name) {
      foreach (preg_grep("/fan\d+_input/", scan_dir($chip)) as $fan) {
        $out[] = [
          'chip'   => $name,
          'name'   => basename($fan),
          'sensor' => $fan,
          'rpm'    => @file_get_contents($fan)
        ];
      }
    }
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
    $index = intval($_POST['index'] ?? 0);
    $cfgpath = "/boot/config/plugins/$plugin";
    $filename = "$plugin" . "_temp_$index.cfg";
    $fullpath = "$cfgpath/$filename";
    if (!file_exists($fullpath)) {
      file_put_contents($fullpath, "custom=\"\"\nservice=\"1\"\ncontroller=\"\"\npwm=\"100\"\nlow=\"40\"\nhigh=\"60\"\ninterval=\"2\"\ndisks=\"\"");
    }
    echo "created";
    exit;

  case 'delete':
    $file = basename($_POST['file'] ?? '');
    $cfgpath = "/boot/config/plugins/$plugin/$file";
    if (is_file($cfgpath)) {
      unlink($cfgpath);
      echo "deleted";
    } else {
      echo "not found";
    }
    exit;

  case 'status':
    $running = false;
    foreach (glob("/var/run/fanctrlplus_*.pid") as $pidfile) {
      $pid = trim(file_get_contents($pidfile));
      if (posix_kill((int)$pid, 0)) {
        $running = true;
        break;
      }
    }
    echo json_encode(['status' => $running ? 'running' : 'stopped']);
    break;

  case 'status_all':
    ob_clean();
    header('Content-Type: application/json');

    $cfg_dir = "/boot/config/plugins/$plugin";
    $result = [];

    // 获取当前所有正在运行的 fanctrlplus_loop 对应 cfg
    exec("pgrep -af fanctrlplus_loop", $running_processes);

    foreach (glob("$cfg_dir/{$plugin}_*.cfg") as $file) {
      $cfg = parse_ini_file($file);
      $name = trim($cfg['custom'] ?? '');
      $enabled = trim($cfg['service'] ?? '0') === '1';

      $is_running = false;
      foreach ($running_processes as $proc) {
        if (strpos($proc, $file) !== false) {
          $is_running = true;
          break;
        }
      }

      if ($name !== '') {
        $result[$name] = ($enabled && $is_running) ? 'running' : 'stopped';
      }
    }

    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;

  case 'start':
    $rc = "$docroot/plugins/$plugin/scripts/rc.fanctrlplus";
    if (is_file($rc)) {
      exec("$rc start >/dev/null 2>&1 &");
      echo "started";
    } else {
      echo "script not found";
    }
    exit;

  case 'stop':
    $rc = "$docroot/plugins/$plugin/scripts/rc.fanctrlplus";
    exec("$rc stop >/dev/null 2>&1 &");
    echo "stopped";
    exit;

  default:
    json_response(['error' => 'Invalid op']);
}
?>
