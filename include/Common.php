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
  usort($out, function ($a, $b) {
    return strcmp($a['name'], $b['name']);
  });

  return $out;
}

function list_valid_disks_by_id() {
  $seen = [];
  $result = [];

  $boot_mount = realpath("/boot");
  $boot_dev = exec("findmnt -n -o SOURCE --target $boot_mount 2>/dev/null");
  $boot_dev_base = preg_replace('#[0-9]+$#', '', $boot_dev);

  // 映射 /dev/sdX → diskX（只针对 array 成员）
  $sd_to_disk = [];
  foreach (glob("/mnt/disk*") as $disk_path) {
    $dev = exec("findmnt -n -o SOURCE --target " . escapeshellarg($disk_path));
    $dev_base = preg_replace('#[0-9]+$#', '', $dev);
    if ($dev_base && strpos($dev_base, '/dev/') === 0) {
      $sd_to_disk[$dev_base] = basename($disk_path);  // /dev/sdd → disk1
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

    // 若设备是 diskX，则追加 disk 名标签
    if (isset($sd_to_disk[$real])) {
      $label .= " → " . $sd_to_disk[$real];
    }

    $result[] = ['id' => $id, 'dev' => $real, 'label' => $label];
  }

  usort($result, function($a, $b) {
    return strnatcasecmp($a['id'], $b['id']);
  });

  return $result;
}
