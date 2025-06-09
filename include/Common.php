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
  $groups = [];

  // 获取 dev → DiskX/Parity 映射
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

  // 获取 pool 名 → 挂载点路径
  $mnt = array_filter(glob("/mnt/*"), 'is_dir');
  $pools = [];
  foreach ($mnt as $path) {
    $base = basename($path);
    if (in_array($base, ['user', 'disks', 'remotes'])) continue;
    if (strpos($base, 'disk') === 0 || strpos($base, 'user') === 0) continue;
    $pools[$base] = $path;
  }

  // 解析 lsblk 中 pool 对应设备
  $dev_to_pool = [];
  $lsblk = shell_exec("lsblk -pJ -o NAME,KNAME,MOUNTPOINT 2>/dev/null");
  $blk = json_decode($lsblk, true);
  foreach (($blk['blockdevices'] ?? []) as $dev) {
    foreach (($dev['children'] ?? []) as $child) {
      $mp = $child['mountpoint'] ?? '';
      $kname = $child['kname'] ?? '';
      foreach ($pools as $poolname => $poolmnt) {
        if ($mp && strpos($mp, $poolmnt) === 0) {
          $dev_to_pool["/dev/$kname"] = ucfirst($poolname);
        }
      }
    }
  }

  // 忽略 /boot 所在设备
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

    // 显示格式优化
    if (preg_match('#/dev/([a-z0-9]+)[p]?[0-9]*$#', $real, $m)) {
      $base = "/dev/" . $m[1];
      if (isset($dev_to_diskx[$base])) {
        $label = $dev_to_diskx[$base] . " - " . $label;
        $group = "Array";
      } elseif (isset($dev_to_pool[$real])) {
        $label .= " ($m[1])";
        $group = $dev_to_pool[$real];
      } else {
        $label .= " ($m[1])";
        $group = "Others";
      }
    } else {
      $group = "Others";
    }

    $groups[$group][] = ['id' => $id, 'dev' => $real, 'label' => $label, 'title' => $title];
  }

  // 排序：Array → 其他 pool → Others
  uksort($groups, function($a, $b) {
    if ($a === 'Array') return -1;
    if ($b === 'Array') return 1;
    if ($a === 'Others') return 1;
    if ($b === 'Others') return -1;
    return strnatcasecmp($a, $b);
  });

  // 内部排序：Array 按 Disk 顺序，其他按 label
  if (isset($groups['Array'])) {
    usort($groups['Array'], function($a, $b) {
      preg_match('/Disk (\d+)/', $a['label'], $ma);
      preg_match('/Disk (\d+)/', $b['label'], $mb);
      return ($ma[1] ?? 99) <=> ($mb[1] ?? 99);
    });
  }
  foreach ($groups as $key => &$entries) {
    if ($key !== 'Array') {
      usort($entries, fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
    }
  }

  return $groups;
}
