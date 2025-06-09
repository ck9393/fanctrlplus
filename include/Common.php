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

  // 按 pwm 名称排序（例如 pwm1, pwm2...）
  usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));
  return $out;
}

function list_valid_disks_by_id() {
  $seen = [];
  $result = [];

  $boot_mount = realpath("/boot");
  $boot_dev = exec("findmnt -n -o SOURCE --target $boot_mount 2>/dev/null");
  $boot_dev_base = preg_replace('#[0-9]+$#', '', $boot_dev);
  error_log("[fanctrlplus] boot_dev_base = $boot_dev_base");

  // 使用 lsblk 获取 md 设备底层的 sdX
  $dev_to_disk = [];
  foreach (glob("/mnt/disk*") as $mnt) {
    $mount_dev = exec("findmnt -n -o SOURCE --target " . escapeshellarg($mnt));  // /dev/md1p1
    $dev_name = basename($mount_dev);  // md1p1

    if ($dev_name) {
      $output = [];
      exec("lsblk -no PKNAME /dev/$dev_name 2>/dev/null", $output);  // 获取物理设备名，如 sdd
      if (!empty($output)) {
        $sd = trim($output[0]);
        $dev_base = "/dev/$sd";
        $dev_to_disk[$dev_base] = basename($mnt);  // /dev/sdd → disk1
        error_log("[fanctrlplus] map $dev_base → " . basename($mnt));
      }
    }
  }

  foreach (glob("/dev/disk/by-id/*") as $dev) {
    if (!is_link($dev) || strpos($dev, "part") !== false) continue;
    if (strpos(basename($dev), 'usb-') === 0) continue;

    $real = realpath($dev);
    if ($real === false) continue;
    if (strpos($real, "/dev/sd") === false && strpos($real, "/dev/nvme") === false) continue;
    if (strpos($real, $boot_dev_base) === 0) continue;
    if (in_array($real, $seen)) continue;

    $seen[] = $real;
    $id = basename($dev);
    $label = $id;

    $base = preg_replace('#[0-9]+$#', '', $real);  // 去掉 /dev/sdX1 → /dev/sdX
    if (isset($dev_to_disk[$base])) {
      $label .= " → " . $dev_to_disk[$base];
      error_log("[fanctrlplus] matched $base → " . $dev_to_disk[$base] . " for id=$id");
    } else {
      error_log("[fanctrlplus] no match for $base (id=$id)");
    }

    $result[] = ['id' => $id, 'dev' => $real, 'label' => $label];
  }

  usort($result, function($a, $b) {
    return strnatcasecmp($a['id'], $b['id']);
  });

  error_log("[fanctrlplus] final disk list count: " . count($result));
  return $result;
}
