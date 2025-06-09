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

  // 1. 获取 Array 映射
  $dev_to_diskx = [];
  foreach (explode("\n", shell_exec("/usr/local/sbin/mdcmd status | grep rdevName")) as $line) {
    if (preg_match('/rdevName\.(\d+)=(\w+)/', $line, $m)) {
      $slot = (int)$m[1];
      $dev  = "/dev/" . $m[2];
      $dev_to_diskx[$dev] = match ($slot) {
        0 => 'Parity',
        29 => 'Parity 2',
        default => "Disk $slot"
      };
    }
  }

  // 2. 获取所有 Pool 挂载路径
  $pools = [];
  foreach (glob("/mnt/*") as $path) {
    $base = basename($path);
    if (!is_dir($path)) continue;
    if (in_array($base, ['user', 'disks', 'remotes'])) continue;
    if (preg_match('/^disk[0-9]+$/', $base)) continue;
    $pools[$base] = $path;
  }

  // 3. 获取 dev → Pool 名的映射
  $dev_to_pool = [];
  $blk = json_decode(shell_exec("lsblk -pJ -o NAME,KNAME,MOUNTPOINT 2>/dev/null"), true);
  foreach (($blk['blockdevices'] ?? []) as $dev) {
    $name = $dev['name'] ?? '';
    foreach ($dev['children'] ?? [] as $child) {
      $mp = $child['mountpoint'] ?? '';
      if (!$mp) continue;
      foreach ($pools as $pool => $mnt) {
        if (strpos($mp, $mnt) === 0) {
          $dev_to_pool[$name] = ucfirst($pool);  // ✅ 记录 parent 设备名
        }
      }
    }
  }

  // 4. 忽略 boot 所在设备
  $boot_dev = exec("findmnt -n -o SOURCE --target /boot 2>/dev/null");
  $boot_base = preg_replace('#[0-9]+$#', '', $boot_dev);

  // 5. 遍历 /dev/disk/by-id 并生成列表
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

    $base = preg_replace('#[p]?[0-9]+$#', '', $real);  // ✅ 获取父设备

    if (isset($dev_to_diskx[$base])) {
      $label = $dev_to_diskx[$base] . " - " . $label;
      $group = "Array";
    } elseif (isset($dev_to_pool[$base])) {
      $nvme = basename($base);
      $label .= " ($nvme)";
      $group = $dev_to_pool[$base];
    } else {
      $nvme = basename($real);
      $label .= " ($nvme)";
      $group = "Others";
    }

    $groups[$group][] = [
      'id'    => $id,
      'dev'   => $real,
      'label' => $label,
      'title' => $title
    ];
  }

  // 6. 排序 group：Array → Pools → Others
  uksort($groups, function($a, $b) {
    if ($a === 'Array') return -1;
    if ($b === 'Array') return 1;
    if ($a === 'Others') return 1;
    if ($b === 'Others') return -1;
    return strnatcasecmp($a, $b);
  });

  // 排序 Array 内部 DiskX
  if (isset($groups['Array'])) {
    usort($groups['Array'], function($a, $b) {
      preg_match('/Disk (\d+)/', $a['label'], $ma);
      preg_match('/Disk (\d+)/', $b['label'], $mb);
      return ($ma[1] ?? 99) <=> ($mb[1] ?? 99);
    });
  }

  // 排序其他组按 label
  foreach ($groups as $key => &$entries) {
    if ($key !== 'Array') {
      usort($entries, fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
    }
  }

  return $groups;
}
