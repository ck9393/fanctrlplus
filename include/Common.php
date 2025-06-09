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

  // 建立 /dev/sdX 或 /dev/nvmeXn1 → diskX 的反向映射
  $dev_to_disk = [];

  // 使用 lsblk 分析 /mnt/diskX 实际来源设备
  foreach (glob("/mnt/disk*") as $mnt) {
    $mountpoint = escapeshellarg($mnt);
    $output = shell_exec("lsblk -no NAME,MOUNTPOINT /dev/md* 2>/dev/null");
    // 手动逐行找出 mdXp1 -> diskX
    exec("findmnt -n -o SOURCE --target $mountpoint", $out);
    if (!empty($out[0])) {
      $src = trim($out[0]); // /dev/mdXp1
      $parents = shell_exec("lsblk -no PKNAME $src 2>/dev/null");  // 可能返回 sdd
      $parent = trim($parents);
      if ($parent && strpos($parent, 'loop') === false) {
        $base = "/dev/" . $parent;
        $dev_to_disk[$base] = basename($mnt); // disk1, disk2...
        error_log("[fanctrlplus] map $base ← " . basename($mnt));
      }
    }
  }

  $boot_mount = realpath("/boot");
  $boot_dev = exec("findmnt -n -o SOURCE --target $boot_mount 2>/dev/null");
  $boot_dev_base = preg_replace('#[0-9]+$#', '', $boot_dev);

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

    // 从 /dev/sdX1 或 /dev/nvmeXn1p1 → /dev/sdX
    if (preg_match('#/dev/([a-z0-9]+)[p]?[0-9]*$#', $real, $m)) {
      $base = "/dev/" . $m[1];
      if (isset($dev_to_disk[$base])) {
        $label .= " → " . $dev_to_disk[$base];
        error_log("[fanctrlplus] matched $base → {$dev_to_disk[$base]} for id=$id");
      } else {
        error_log("[fanctrlplus] no match for $base (id=$id)");function list_valid_disks_by_id() {
  $seen = [];
  $result = [];

  // 建立 /dev/sdX 或 /dev/nvmeXn1 → diskX 的反向映射
  $dev_to_disk = [];

  // 使用 lsblk 分析 /mnt/diskX 实际来源设备
  foreach (glob("/mnt/disk*") as $mnt) {
    $mountpoint = escapeshellarg($mnt);
    $output = shell_exec("lsblk -no NAME,MOUNTPOINT /dev/md* 2>/dev/null");
    // 手动逐行找出 mdXp1 -> diskX
    exec("findmnt -n -o SOURCE --target $mountpoint", $out);
    if (!empty($out[0])) {
      $src = trim($out[0]); // /dev/mdXp1
      $parents = shell_exec("lsblk -no PKNAME $src 2>/dev/null");  // 可能返回 sdd
      $parent = trim($parents);
      if ($parent && strpos($parent, 'loop') === false) {
        $base = "/dev/" . $parent;
        $dev_to_disk[$base] = basename($mnt); // disk1, disk2...
        error_log("[fanctrlplus] map $base ← " . basename($mnt));
      }
    }
  }

  $boot_mount = realpath("/boot");
  $boot_dev = exec("findmnt -n -o SOURCE --target $boot_mount 2>/dev/null");
  $boot_dev_base = preg_replace('#[0-9]+$#', '', $boot_dev);

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

    // 从 /dev/sdX1 或 /dev/nvmeXn1p1 → /dev/sdX
    if (preg_match('#/dev/([a-z0-9]+)[p]?[0-9]*$#', $real, $m)) {
      $base = "/dev/" . $m[1];
      if (isset($dev_to_disk[$base])) {
        $label .= " → " . $dev_to_disk[$base];
        error_log("[fanctrlplus] matched $base → {$dev_to_disk[$base]} for id=$id");
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
