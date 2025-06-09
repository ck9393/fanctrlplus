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

  $dev_to_disk = [];

  // ✅ 用 findmnt + lsblk 正确建立 /dev/sdX → diskX 的映射
  foreach (glob("/mnt/disk*") as $mnt) {
    $src = trim(shell_exec("findmnt -n -o SOURCE --target " . escapeshellarg($mnt)));
    if (!$src) continue;

    $parent = trim(shell_exec("lsblk -no PKNAME $src 2>/dev/null")); // 例如 sdd
    if (!$parent) continue;

    $base = "/dev/" . $parent;
    $dev_to_disk[$base] = basename($mnt);

    error_log("[fanctrlplus] dev_to_disk: $base => " . basename($mnt));
  }

  $boot_mount = realpath("/boot");
  $boot_dev = exec("findmnt -n -o SOURCE --target $boot_mount 2>/dev/null");
  $boot_dev_base = preg_replace('#[0-9]+$#', '', $boot_dev);

  foreach (glob("/dev/disk/by-id/*") as $dev) {
    if (!is_link($dev) || strpos($dev, "part") !== false) continue;
    if (strpos(basename($dev), 'usb-') === 0) continue;

    $real = realpath($dev);
    if (!$real || (!str_starts_with($real, "/dev/sd") && !str_starts_with($real, "/dev/nvme"))) continue;
    if (strpos($real, $boot_dev_base) === 0) continue;
    if (in_array($real, $seen)) continue;

    $seen[] = $real;
    $id = basename($dev);
    $label = $id;

    // ✅ 提取出 /dev/sdX 或 /dev/nvmeXn1
    if (preg_match('#/dev/([a-z0-9]+)#', $real, $m)) {
      $base = "/dev/" . $m[1];
      if (isset($dev_to_disk[$base])) {
        $label .= " → " . $dev_to_disk[$base];
        error_log("[fanctrlplus] matched $base => {$dev_to_disk[$base]}");
      } else {
        error_log("[fanctrlplus] no match for $base");
      }
    }

    $result[] = ['id' => $id, 'dev' => $real, 'label' => $label];
  }

  usort($result, fn($a, $b) => strnatcasecmp($a['id'], $b['id']));
  error_log("[fanctrlplus] final list count: " . count($result));
  return $result;
}
