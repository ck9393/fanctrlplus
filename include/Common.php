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

  // 使用 lsblk JSON 输出建立 dev → diskX 映射
  $json = shell_exec("lsblk -pJ -o NAME,KNAME,MOUNTPOINT 2>/dev/null");
  $blk = json_decode($json, true);

  if (!empty($blk['blockdevices'])) {
    foreach ($blk['blockdevices'] as $dev) {
      if (!empty($dev['mountpoint']) && preg_match('@/mnt/disk[0-9]+@', $dev['mountpoint'])) {
        // 情况: mdXp1 直接挂载
        $disk = basename($dev['mountpoint']);
        $kname = $dev['kname'] ?? $dev['name'];
        error_log("[fanctrlplus] map (direct) $kname → $disk");
        $dev_to_disk[$kname] = $disk;
      } elseif (!empty($dev['children'])) {
        foreach ($dev['children'] as $child) {
          if (!empty($child['mountpoint']) && preg_match('@/mnt/disk[0-9]+@', $child['mountpoint'])) {
            $disk = basename($child['mountpoint']);
            $kname = $dev['kname'] ?? $dev['name'];
            error_log("[fanctrlplus] map (child) $kname → $disk");
            $dev_to_disk[$kname] = $disk;
          }
        }
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

    // 提取基础 dev 名称用于匹配映射（如 /dev/sdX、/dev/nvmeXn1）
    if (preg_match('#^(/dev/(?:sd[a-z]+|nvme\d+n\d+))#', $real, $m)) {
      $base = $m[1];
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
