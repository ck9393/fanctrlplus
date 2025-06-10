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

  // 映射 /dev/sdX → DiskX / Parity
  $dev_to_diskx = [];
  $lines = shell_exec("/usr/local/sbin/mdcmd status | grep rdevName");
  foreach (explode("\n", $lines) as $line) {
    if (preg_match('/rdevName\.(\d+)=(\w+)/', $line, $m)) {
      $slot = intval($m[1]);
      $dev  = "/dev/" . trim($m[2]);
      $dev_to_diskx[$dev] = match (true) {
        $slot === 0  => 'Parity',
        $slot === 29 => 'Parity 2',
        default      => 'Disk ' . $slot
      };
    }
  }

  // 映射 /dev/nvmeXp1 → pool 名（通过 zpool list -v）
  $dev_to_pool = [];
  $zpool = shell_exec("zpool list -v 2>/dev/null");
  $current_pool = '';
  foreach (explode("\n", $zpool) as $line) {
    if (preg_match('/^(\S+)\s+\d/', $line, $m)) {
      $current_pool = $m[1];
    } elseif (preg_match('/^\s+(nvme\S+)/', $line, $m)) {
      $dev = '/dev/' . preg_replace('/p\d+$/', '', $m[1]);
      $dev_to_pool[$dev] = ucfirst($current_pool);
    }
  }

  // boot device
  $boot_dev = exec("findmnt -n -o SOURCE --target /boot 2>/dev/null");
  $boot_base = preg_replace('#[0-9]+$#', '', $boot_dev);

  // 遍历所有 by-id
  foreach (glob("/dev/disk/by-id/*") as $dev) {
    if (!is_link($dev) || strpos($dev, 'part') !== false) continue;
    if (strpos(basename($dev), 'usb-') === 0) continue;

    $real = realpath($dev);
    if (!$real || in_array($real, $seen)) continue;
    if (strpos($real, $boot_base) === 0) continue;
    $seen[] = $real;

    $id = basename($dev);
    $label = preg_replace('/^(nvme|ata)-/', '', $id);
    $title = "$id\n$real";
    $group = 'Others';

    if (preg_match('#/dev/([a-zA-Z0-9]+)[p]?[0-9]*$#', $real, $m)) {
      $base = "/dev/" . $m[1];
      if (isset($dev_to_diskx[$base])) {
        $label = $dev_to_diskx[$base] . " - " . $label;
        $group = "Array";
      } elseif (isset($dev_to_pool[$base])) {
        $label .= " ($m[1])";
        $group = $dev_to_pool[$base];
      } else {
        $label .= " ($m[1])";
      }
    }

    $groups[$group][] = [
      'id'    => $id,
      'dev'   => $real,
      'label' => $label,
      'title' => $title
    ];
  }

  // 排序组：Array → Pool → Others
  uksort($groups, function($a, $b) {
    if ($a === 'Array') return -1;
    if ($b === 'Array') return 1;
    if ($a === 'Others') return 1;
    if ($b === 'Others') return -1;
    return strnatcasecmp($a, $b);
  });

  // Array 内部排序（Parity → Parity 2 → Disk X）
  if (isset($groups['Array'])) {
    usort($groups['Array'], function($a, $b) {
      $la = $a['label'];
      $lb = $b['label'];
  
      // ✅ 优先 Parity → Parity 2 → Disk X
      if (str_starts_with($la, 'Parity') && !str_starts_with($lb, 'Parity')) return -1;
      if (!str_starts_with($la, 'Parity') && str_starts_with($lb, 'Parity')) return 1;
      if ($la === 'Parity') return -1;
      if ($lb === 'Parity') return 1;
  
      // 排序 Disk N
      preg_match('/Disk (\d+)/', $la, $ma);
      preg_match('/Disk (\d+)/', $lb, $mb);
      return ($ma[1] ?? 99) <=> ($mb[1] ?? 99);
    });
  }
  
  // 其他组按 label 排序
  foreach ($groups as $group => &$entries) {
    if ($group !== 'Array') {
      usort($entries, fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
    }
  }

  return $groups;
}
