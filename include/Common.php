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
  $groups = [];

  // 获取 Array 设备映射（/dev/sdX -> Parity/diskX）
  $dev_to_label = [];
  $array_order = [];
  $lines = shell_exec("/usr/local/sbin/mdcmd status | grep rdevName");
  foreach (explode("\n", $lines) as $line) {
    if (preg_match('/rdevName\.(\d+)=(\w+)/', $line, $m)) {
      $slot = intval($m[1]);
      $dev  = "/dev/" . trim($m[2]);
      if ($slot == 0) {
        $label = "Parity";
      } elseif ($slot == 29) {
        $label = "Parity 2";
      } else {
        $label = "Disk " . $slot;
      }
      $dev_to_label[$dev] = $label;
      $array_order[$label] = $slot;  // 用于排序
    }
  }

  // 获取每个设备 -> pool 名的映射
  $pool_map = [];
  foreach (glob("/etc/libvirt/lxc/*/config") as $file) {
    $pool = basename(dirname($file));
    $content = file_get_contents($file);
    if (preg_match_all('/by-id\/(\S+)/', $content, $matches)) {
      foreach ($matches[1] as $id) {
        $pool_map["/dev/disk/by-id/$id"] = $pool;
      }
    }
  }

  // 获取 /boot 的设备（排除）
  $boot_mount = realpath("/boot");
  $boot_dev = exec("findmnt -n -o SOURCE --target $boot_mount 2>/dev/null");
  $boot_dev_base = preg_replace('#[0-9]+$#', '', $boot_dev);

  foreach (glob("/dev/disk/by-id/*") as $dev) {
    if (!is_link($dev) || strpos($dev, "part") !== false) continue;
    if (strpos(basename($dev), 'usb-') === 0) continue;

    $real = realpath($dev);
    if ($real === false) continue;
    if (strpos($real, $boot_dev_base) === 0) continue;
    if (in_array($real, $seen)) continue;
    $seen[] = $real;

    $id = basename($dev);
    $id_clean = preg_replace('/^(ata|nvme)-/', '', $id);
    $short = $id_clean;

    // 获取 nvmeXn1 或 sdX
    if (preg_match('#/dev/(nvme\d+n\d+|sd[a-z])#', $real, $m)) {
      $dev_short = $m[1];
    } else {
      $dev_short = basename($real);
    }

    // 判断属于哪个组
    $group = "Others";
    $label = $short;

    if (isset($dev_to_label[$real])) {
      $group = "Array";
      $label = $dev_to_label[$real] . " - $short";
    } elseif (isset($pool_map[$dev])) {
      $group = $pool_map[$dev];
      $label = "$short ($dev_short)";
    } else {
      $label = "$short ($dev_short)";
    }

    $groups[$group][] = ['id' => $id, 'dev' => $real, 'label' => $label];
  }

  // 排序
  uksort($groups, function($a, $b) {
    if ($a == "Array") return -1;
    if ($b == "Array") return 1;
    return strnatcasecmp($a, $b);
  });

  // 排序 Array 内部顺序
  if (isset($groups["Array"])) {
    usort($groups["Array"], function($a, $b) use ($array_order) {
      $al = explode(" -", $a['label'])[0];
      $bl = explode(" -", $b['label'])[0];
      return ($array_order[$al] ?? 99) <=> ($array_order[$bl] ?? 99);
    });
  }

  return $groups;  // 每组对应 optgroup -> list of [id, dev, label]
}
