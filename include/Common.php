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

  $boot_mount = realpath("/boot");
  $boot_dev = exec("findmnt -n -o SOURCE --target $boot_mount 2>/dev/null");
  $boot_dev_base = preg_replace('#[0-9]+$#', '', $boot_dev);
  error_log("[fanctrlplus] Boot device base: $boot_dev_base");

  // 建立 diskX 映射：/mnt/disk1 → /dev/md1p1 → /dev/md1
  $dev_to_disk = [];
  foreach (glob("/mnt/disk*") as $mnt) {
    $real = realpath($mnt);  // 例如 /dev/md1p1、/dev/sdX1
    error_log("[fanctrlplus] MNT=$mnt → REAL=$real");
    if ($real && preg_match('#^/dev/(md[0-9]+|sd[a-z]+)[p0-9]*$#', $real, $m)) {
      $dev_base = "/dev/" . $m[1]; // 提取 /dev/sdX 或 /dev/mdX
      $disk_name = basename($mnt);
      $dev_to_disk[$dev_base] = $disk_name;
      error_log("[fanctrlplus] Map $dev_base → $disk_name");
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

    $matched = false;
    foreach ($dev_to_disk as $dev_base => $disk_name) {
      if (strpos($real, $dev_base) === 0) {
        $label .= " → $disk_name";
        error_log("[fanctrlplus] Matched $real → $disk_name");
        $matched = true;
        break;
      }
    }

    if (!$matched) {
      error_log("[fanctrlplus] No match for $real");
    }

    $result[] = ['id' => $id, 'dev' => $real, 'label' => $label];
  }

  usort($result, function($a, $b) {
    return strnatcasecmp($a['id'], $b['id']);
  });

  return $result;
}
