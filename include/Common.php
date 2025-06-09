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

  // 取得启动盘设备（用于排除）
  $boot_mount = realpath("/boot");
  $boot_dev = exec("findmnt -n -o SOURCE --target $boot_mount 2>/dev/null");
  $boot_dev_base = preg_replace('#[0-9]+$#', '', $boot_dev);

  // 解析 /mnt/diskX 对应的底层设备（用 lsblk 追踪）
  $dev_to_disk = [];
  $lsblk = shell_exec("lsblk -P -o NAME,MOUNTPOINT");
  foreach (explode("\n", trim($lsblk)) as $line) {
    if (preg_match('/NAME="([^"]+)" MOUNTPOINT="\/mnt\/disk([0-9]+)"/', $line, $m)) {
      $disk = "disk" . $m[2];
      $dev = "/dev/" . $m[1];
      $dev_to_disk[$dev] = $disk;
      error_log("[fanctrlplus] map $dev → $disk");
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

    // 若真实设备在 array 中，加上 diskX 标注
    if (isset($dev_to_disk[$real])) {
      $label .= " → " . $dev_to_disk[$real];
      error_log("[fanctrlplus] matched $real → " . $dev_to_disk[$real] . " for id=$id");
    } else {
      error_log("[fanctrlplus] no match for $real (id=$id)");
    }

    $result[] = ['id' => $id, 'dev' => $real, 'label' => $label];
  }

  error_log("[fanctrlplus] final disk list count: " . count($result));
  usort($result, fn($a, $b) => strnatcasecmp($a['id'], $b['id']));
  return $result;
}
