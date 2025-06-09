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

  // 获取 Array 中 /dev/sdX → DiskX/Parity 映射
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

  // 查找 Pool → 挂载路径
  $pools = [];
  foreach (glob("/mnt/*") as $path) {
    $base = basename($path);
    if (!is_dir($path)) continue;
    if (in_array($base, ['user', 'disks', 'remotes'])) continue;
    if (preg_match('/^disk[0-9]+$/', $base)) continue;
    $pools[$base] = $path;
  }

  // 获取 /dev/xxxp1 → Pool 名称映射
  $dev_to_pool = [];
  $blk = json_decode(shell_exec("lsblk -pJ -o NAME,KNAME,MOUNTPOINT 2>/dev/null"), true);
  foreach (($blk['blockdevices'] ?? []) as $dev) {
    foreach (($dev['children'] ?? []) as $child) {
      $kname = $child['kname'] ?? '';
      $mount = $child['mountpoint'] ?? '';
      foreach ($pools as $pool => $mnt) {
        if ($mount && strpos($mount, $mnt) === 0) {
          $dev_to_pool["/dev/$kname"] = ucfirst($pool);
        }
      }
    }
  }

  // boot device
  $boot_dev = exec("findmnt -n -o SOURCE --target /boot 2>/dev/null");
  $boot_base = preg_replace('#[0-9]+$#', '', $boot_dev);

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

    $group = "Others";

    if (preg_match('#/dev/([a-z0-9]+)[p]?[0-9]*$#', $real, $m)) {
      $base = "/dev/" . $m[1];
      $basename = basename($real);

      if (isset($dev_to_diskx[$base])) {
        $label = $dev_to_diskx[$base] . " - " . $label;
        $group = "Array";
      } else {
        $base_real = preg_replace('#[p]?[0-9]+$#', '', $real);
        if (isset($dev_to_pool[$base_real])) {
          $label .= " (" . basename($base_real) . ")";
          $group = $dev_to_pool[$base_real];
        } else {
          $label .= " (" . basename($real) . ")";
        }
      }
    }

    $groups[$group][] = [
      'id'    => $id,
      'dev'   => $real,
      'label' => $label,
      'title' => $title
    ];
  }

  // 分组排序：Array → Pool（字母序）→ Others
  uksort($groups, function($a, $b) {
    if ($a === "Array") return -1;
    if ($b === "Array") return 1;
    if ($a === "Others") return 1;
    if ($b === "Others") return -1;
    return strnatcasecmp($a, $b);
  });

  // 排序 Array 内部 Disk 顺序
  if (isset($groups["Array"])) {
    usort($groups["Array"], function($a, $b) {
      preg_match('/Disk (\d+)/', $a['label'], $ma);
      preg_match('/Disk (\d+)/', $b['label'], $mb);
      return ($ma[1] ?? 99) <=> ($mb[1] ?? 99);
    });
  }

  // 其他组按 label 排序
  foreach ($groups as $key => &$entries) {
    if ($key !== 'Array') {
      usort($entries, fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
    }
  }

  return $groups;
}
