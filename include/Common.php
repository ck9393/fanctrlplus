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
  $dev_to_diskx = [];

  // 1. 建立 /dev/sdX → diskX/parity 映射
  $lines = shell_exec("/usr/local/sbin/mdcmd status | grep rdevName");
  foreach (explode("\n", $lines) as $line) {
    if (preg_match('/rdevName\.(\d+)=(\w+)/', $line, $m)) {
      $slot = intval($m[1]);
      $dev  = "/dev/" . trim($m[2]);
      if ($slot == 0) {
        $dev_to_diskx[$dev] = "Parity";
      } elseif ($slot == 29) {
        $dev_to_diskx[$dev] = "Parity 2";
      } else {
        $dev_to_diskx[$dev] = "Disk " . $slot;
      }
    }
  }

  // 2. 获取 /boot 的设备（跳过它）
  $boot_mount = realpath("/boot");
  $boot_dev = exec("findmnt -n -o SOURCE --target $boot_mount 2>/dev/null");
  $boot_dev_base = preg_replace('#[0-9]+$#', '', $boot_dev);

  // 3. 遍历所有 by-id
  foreach (glob("/dev/disk/by-id/*") as $dev) {
    if (!is_link($dev) || strpos($dev, "part") !== false) continue;
    if (strpos(basename($dev), 'usb-') === 0) continue;

    $real = realpath($dev);
    if ($real === false || in_array($real, $seen)) continue;
    if (strpos($real, $boot_dev_base) === 0) continue;

    $seen[] = $real;
    $id = basename($dev);
    $clean_id = preg_replace('/^(ata|nvme)-/', '', $id); // 去前缀

    // 判断是否为 sdX/nvmeXn1，并匹配 dev_to_diskx
    $label = $clean_id;
    $sort_key = "Z-" . $clean_id; // 默认排最后（未识别的 NVMe）

    if (preg_match('#/dev/([a-z0-9]+)[p]?[0-9]*$#', $real, $m)) {
      $base = "/dev/" . $m[1];
      if (isset($dev_to_diskx[$base])) {
        $diskx = $dev_to_diskx[$base];
        $label = "$diskx - $clean_id";
        $sort_key = str_pad($diskx === 'Parity' ? 0 : ($diskx === 'Parity 2' ? 1 : intval(preg_replace('/\D/', '', $diskx)) + 10), 3, '0', STR_PAD_LEFT);
      } else {
        // 未映射（通常是 NVMe）
        $shortname = basename($real);
        $label = "$clean_id ($shortname)";
      }
    }

    error_log("[fanctrlplus] disk id=$id real=$real label=$label sort=$sort_key");
    $result[] = ['id' => $id, 'dev' => $real, 'label' => $label, 'sort' => $sort_key];
  }

  // 4. 排序
  usort($result, fn($a, $b) => strnatcasecmp($a['sort'], $b['sort']));
  foreach ($result as &$r) unset($r['sort']); // 清理临时排序键
  error_log("[fanctrlplus] final sorted disk list count: " . count($result));
  return $result;
}
