<?php
$plugin = 'fanctrlplus';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

function scan_dir($dir) {
  $out = [];
  foreach (array_diff(scandir($dir), ['.','..']) as $f) $out[] = realpath($dir).'/'.$f;
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

switch ($_GET['op'] ?? '') {
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
          break;
        }
      }
    }
    break;

  case 'pwm':
    $pwm = $_GET['pwm'] ?? '';
    $fan = $_GET['fan'] ?? '';
    if (is_file($pwm) && is_file($fan)) {
      $autofan = "$docroot/plugins/$plugin/scripts/rc.autofan";
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
    break;

  case 'pause':
    $pwm = $_GET['pwm'] ?? '';
    if (is_file($pwm)) {
      $original_pwm  = trim(@file_get_contents($pwm));
      $pwm_enable    = $pwm . "_enable";
      $original_mode = is_file($pwm_enable) ? trim(@file_get_contents($pwm_enable)) : '2';

      @file_put_contents($pwm_enable, "1");
      @file_put_contents($pwm, "0");

      // 后台恢复原状态
      $restore_cmd = "sleep 30 && echo " . escapeshellarg($original_mode) . " > " . escapeshellarg($pwm_enable) .
                     " && echo " . escapeshellarg($original_pwm) . " > " . escapeshellarg($pwm);
      exec("nohup bash -c \"$restore_cmd\" >/dev/null 2>&1 &");

      echo "Fan paused for 30 seconds";
    } else {
      echo "Invalid PWM path";
    }
    break;
}
?>
