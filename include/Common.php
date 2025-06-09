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

  // 建立 diskX 映射（从 /mnt/diskX → /dev/sdX）
  $dev_to_disk = [];
  foreach (glob("/mnt/disk*") as $mnt) {
    $real = realpath($mnt);  // 会返回 /dev/mdX 或 /dev/sdX1
    if ($real && preg_match('#/dev/(sd[a-z]+)[0-9]*$#', $real, $m)) {
      $dev_base = "/dev/" . $m[1];  // 提取 /dev/sdX
      $dev_to_disk[$dev_base] = basename($mnt);  // /dev/sdX → disk1
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

    // 追加 diskX 标签（通过 dev_base 匹配）
    if (preg_match('#/dev/(sd[a-z]+)#', $real, $m)) {
      $dev_base = "/dev/" . $m[1];
      if (isset($dev_to_disk[$dev_base])) {
        $label .= " → " . $dev_to_disk[$dev_base];
      }
    }

    $result[] = ['id' => $id, 'dev' => $real, 'label' => $label];
  }

  usort($result, function($a, $b) {
    return strnatcasecmp($a['id'], $b['id']);
  });

  return $result;
}
