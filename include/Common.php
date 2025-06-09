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

  // 获取 /mnt/diskX → /dev/mdX 映射
  $md_to_disk = [];
  foreach (glob("/mnt/disk*") as $disk_path) {
    $src = exec("findmnt -n -o SOURCE --target " . escapeshellarg($disk_path));
    $md_base = preg_replace('#p?[0-9]+$#', '', $src);
    if (strpos($md_base, "/dev/md") === 0) {
      $md_to_disk[$md_base] = basename($disk_path);  // /dev/md1 → disk1
    }
  }

  // 获取 /dev/disk/by-id/* → /dev/mdX 的反向映射
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

    // 匹配：该 by-id 所属物理盘是否属于 array 的 /dev/mdX
    $md_base = exec("findmnt -n -o SOURCE --target $real 2>/dev/null");
    $md_base = preg_replace('#p?[0-9]+$#', '', $md_base);
    if (isset($md_to_disk[$md_base])) {
      $label .= " → " . $md_to_disk[$md_base];  // 显示 disk1、disk2 标签
    }

    $result[] = ['id' => $id, 'dev' => $real, 'label' => $label];
  }

  usort($result, fn($a, $b) => strnatcasecmp($a['id'], $b['id']));
  return $result;
}
