<?php

function list_pwm() {
  $out = [];
  exec("find /sys/devices -type f -iname 'pwm[0-9]' -exec dirname \"{}\" + | uniq", $chips);
  foreach ($chips as $chip) {
    $name = is_file("$chip/name") ? trim(file_get_contents("$chip/name")) : '';
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

// 掃描所有 hwmon 傳感器，找出可能的 CPU 溫度路徑，並附上即時溫度與優先排序
function detect_cpu_sensors(): array {
  $result = [];
  $priority_order = [
    'Package id', 'Tctl', 'Tdie', 'CPU Temp',
    'PECI Agent', 'CPUTIN', 'Core 0'
  ];

  foreach (glob('/sys/class/hwmon/hwmon*') as $hwmonPath) {
    $nameFile = "$hwmonPath/name";
    if (!is_readable($nameFile)) continue;
    $chipName = trim(file_get_contents($nameFile));

    foreach (glob("$hwmonPath/temp*_label") as $labelFile) {
      $label = trim(file_get_contents($labelFile));
      $tempInput = str_replace('_label', '_input', $labelFile);
      if (!is_readable($tempInput)) continue;

      $raw = trim(file_get_contents($tempInput));
      $c = is_numeric($raw) ? intval($raw) / 1000 : null;
      if ($c === null || $c <= 0) continue;

      $tempC = round($c, 1) . '°C';
      $labelFull = "$chipName - $label ($tempC)";

      $priority = array_search(true, array_map(fn($k) => stripos($label, $k) !== false, $priority_order));
      if ($priority === false) continue;

      $result[] = [
        'path' => $tempInput,
        'label' => $labelFull,
        'priority' => $priority
      ];
    }
  }

  // 排序：先按优先级，再按芯片名
  usort($result, fn($a, $b) => $a['priority'] <=> $b['priority']);

  // 生成最终键值对（path => label）
  $final = [];
  foreach ($result as $entry) {
    $final[$entry['path']] = $entry['label'];
  }

  return $final;
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

  // 映射 /dev/sdX → 非 ZFS Pool（btrfs, xfs）名（通过挂载点）
  $dev_to_pool_fs = [];
  $mounts = shell_exec("findmnt -rn -o SOURCE,TARGET,FSTYPE | grep -E 'btrfs|xfs'");
  foreach (explode("\n", $mounts) as $line) {
    $parts = preg_split('/\s+/', $line);
    if (count($parts) === 3) {
      [$dev, $mount, $fstype] = $parts;
      $base = preg_replace('#[0-9]+$#', '', $dev); // /dev/sdj1 → /dev/sdj
      // 过滤掉 array 的 mdX 磁盘
      if (strpos($base, '/dev/md') === 0 || strpos($base, '/dev/loop') === 0) continue;
      $pool_name = basename($mount); // 从挂载路径推断 pool 名
      $dev_to_pool_fs[$base] = ucfirst($pool_name);
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

    $base = $real;

    // 只对 sdX1 / nvme0n1p1 这类分区做 base 处理
    if (preg_match('#^/dev/(sd[a-z]|nvme\d+n\d+)p?\d+$#', $real, $m)) {
      $base = "/dev/" . $m[1];  // 去除分区编号
    }

    $id = basename($dev);
    $label = preg_replace('/^(nvme|ata)-/', '', $id);
    $title = "$id → $real";
    $group = 'Others';

    if (isset($dev_to_diskx[$base])) {
      $label = $dev_to_diskx[$base] . " - " . $label;
      $group = "Array";
    } elseif (isset($dev_to_pool[$base])) {
      $label .= " (" . basename($base) . ")";
      $group = $dev_to_pool[$base];
    } elseif (isset($dev_to_pool_fs[$base])) {
      $label .= " (" . basename($base) . ")";
      $group = $dev_to_pool_fs[$base];
    } else {
      $label .= " (" . basename($base) . ")";
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
      $order = function($label) {
        if (str_starts_with($label, 'Parity 2')) return 1;
        if (str_starts_with($label, 'Parity'))   return 0;
        if (preg_match('/Disk (\d+)/', $label, $m)) {
          return 2 + intval($m[1]);
        }
        return 999;
      };
      return $order($a['label']) <=> $order($b['label']);
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
