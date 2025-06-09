<?php

function list_pwm() {
  $out = [];
  exec("find /sys/devices -type f -iname 'pwm[0-9]' -exec dirname \"{}\" + | uniq", $chips);
  foreach ($chips as $chip) {
    $name = is_file("$chip/name") ? file_get_contents("$chip/name") : '';
    foreach (glob("$chip/pwm[0-9]") as $pwm) {
      $out[] = ['chip' => $name, 'name' => basename($pwm), 'sensor' => $pwm];
    }
  }

  usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));
  return $out;
}

function list_valid_disks_by_id() {
  $seen = [];
  $result = [];
  $dev_to_diskx = [];

  // 建立 /dev/sdX → diskX/parity 映射
  $lines = shell_exec("/usr/local/sbin/mdcmd status | grep rdevName");
  foreach (explode("\n", $lines) as $line) {
    if (preg_match('/rdevName\.(\d+)=(\w+)/', $line, $m)) {
      $slot = intval($m[1]);
      $dev  = "/dev/" . trim($m[2]);
      if ($slot == 0) {
        $dev_to_diskx[$dev] = "Parity";
      } elseif ($slot == 29) {
        $dev_to_diskx[$dev] = "Parity 2";
      } else {
        $dev_to_diskx[$dev] = "disk" . $slot;
      }
    }
  }

  // 获取 /boot 的设备（跳过）
  $boot_mount = realpath("/boot");
  $boot_dev = exec("findmnt -n -o SOURCE --target $boot_mount 2>/dev/null");
  $boot_dev_base = preg_replace('#[0-9]+$#', '', $boot_dev);

  // 遍历所有 by-id
  foreach (glob("/dev/disk/by-id/*") as $dev) {
    if (!is_link($dev) || strpos($dev, "part") !== false) continue;
    if (strpos(basename($dev), 'usb-') === 0) continue;

    $real = realpath($dev);
    if ($real === false) continue;
    if (strpos($real, $boot_dev_base) === 0) continue;
    if (in_array($real, $seen)) continue;

    $seen[] = $real;
    $id = basename($dev);
    $label = $id;

    // 判断是否为 sdX/nvme 设备
    if (preg_match('#/dev/([a-z0-9]+)[p]?[0-9]*$#', $real, $m)) {
      $base = "/dev/" . $m[1];
      if (isset($dev_to_diskx[$base])) {
        $label .= " → " . $dev_to_diskx[$base];
        error_log("[fanctrlplus] matched $base → {$dev_to_diskx[$base]} for id=$id");
      } else {
        error_log("[fanctrlplus] no match for $base (id=$id)");
      }
    } else {
      error_log("[fanctrlplus] no match regex for real=$real");
    }

    error_log("[fanctrlplus] disk id=$id real=$real label=$label");
    $result[] = ['id' => $id, 'dev' => $real, 'label' => $label];
  }

  usort($result, fn($a, $b) => strnatcasecmp($a['id'], $b['id']));
  error_log("[fanctrlplus] final disk list count: " . count($result));
  return $result;
}
