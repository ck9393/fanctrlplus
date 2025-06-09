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

  // 解析 lsblk 的 JSON，映射 dev → diskX
  $lsblk_json = shell_exec("lsblk -pJ -o NAME,KNAME,MOUNTPOINT 2>/dev/null");
  $blk = json_decode($lsblk_json, true);

  if (!empty($blk['blockdevices'])) {
    foreach ($blk['blockdevices'] as $dev) {
      $mountpoint = $dev['mountpoint'] ?? '';
      $kname = $dev['kname'] ?? '';

      if ($mountpoint && strpos($mountpoint, '/mnt/disk') === 0 && $kname) {
        $label = basename($mountpoint); // disk1 ~ diskX

        // 尝试反查 mdXp1 的物理父设备
        $parent = shell_exec("lsblk -no PKNAME $kname 2>/dev/null");
        $parent = trim($parent);
        if ($parent) {
          $base = "/dev/$parent";
          $dev_to_disk[$base] = $label;
          error_log("[fanctrlplus] map $base ← $label");
        }
      }
    }
  }

  // 查找启动设备，排除 /boot 所在设备
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
