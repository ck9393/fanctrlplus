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

  // 建立 diskX 映射：/mnt/disk1 → /dev/mdX 或 /dev/sdX
  $dev_to_disk = [];
  foreach (glob("/mnt/disk*") as $mnt) {
    $src = exec("findmnt -n -o SOURCE --target " . escapeshellarg($mnt));
    if ($src && preg_match('#^/dev/([a-z]+[0-9]*)$#', $src, $m)) {
      $dev_base = "/dev/" . preg_replace('#p?[0-9]+$#', '', $m[1]); // 去除分区号
      $dev_to_disk[$dev_base] = basename($mnt); // 如 /dev/md1 → disk1
      error_log("[fanctrlplus] Map $dev_base → {$dev_to_disk[$dev_base]}");
    }
  }

  foreach (glob("/dev/disk/by-id/*") as $dev) {
    if (!is_link($dev) || strpos($dev, "part") !== false) continue;
    if (strpos(basename($dev), 'usb-') === 0) continue;

    $real = realpath($dev);
    if ($real === false) continue;
    if (!preg_match('#^/dev/(sd|nvme|md)#', $real)) continue;
    if (strpos($real, $boot_dev_base) === 0) continue;
    if (in_array($real, $seen)) continue;

    $seen[] = $real;
    $id = basename($dev);
    $label = $id;

    // 匹配设备路径映射（允许 /dev/mdX /dev/sdX）
    foreach ($dev_to_disk as $base => $diskname) {
      if (strpos($real, $base) === 0) {
        $label .= " → $diskname";
        error_log("[fanctrlplus] Matched $real → $diskname");
        break;
      }
    }

    $result[] = ['id' => $id, 'dev' => $real, 'label' => $label];
  }

  usort($result, function($a, $b) {
    return strnatcasecmp($a['id'], $b['id']);
  });

  return $result;
}
