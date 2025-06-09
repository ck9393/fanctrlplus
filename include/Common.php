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
  $dev_to_disk = [];

  // 构建 dev -> diskX 映射（包括 Parity）
  $lsblk_json = shell_exec("lsblk -pJ -o NAME,KNAME,MOUNTPOINT 2>/dev/null");
  $blk = json_decode($lsblk_json, true);
  if (!empty($blk['blockdevices'])) {
    foreach ($blk['blockdevices'] as $dev) {
      $mp = $dev['mountpoint'] ?? '';
      $kname = $dev['kname'] ?? '';
      if ($mp && preg_match('#^/mnt/disk[0-9]+$#', $mp)) {
        $diskX = basename($mp);
        $parent = trim(shell_exec("lsblk -no PKNAME $kname 2>/dev/null"));
        if ($parent) {
          $dev_to_disk["/dev/$parent"] = $diskX;
        }
      }
    }
  }

  // 加入 Parity/Parity2 映射
  $mdstat = @file_get_contents("/proc/mdstat");
  if ($mdstat !== false) {
    foreach (explode("\n", $mdstat) as $line) {
      if (preg_match('#^md([0-9]+) : .*#', $line, $m)) {
        $md = "md{$m[1]}";
        $out = shell_exec("/usr/local/sbin/mdcmd status | grep -i 'rdevName.*='");
        foreach (explode("\n", $out) as $entry) {
          if (preg_match('#rdevName\\.[0-9]+=([^\\s]+)#', $entry, $m2)) {
            $dev = "/dev/" . trim($m2[1]);
            if (!isset($dev_to_disk[$dev])) {
              $dev_to_disk[$dev] = ($m[1] == "1") ? "Parity" : "Parity 2";
            }
          }
        }
      }
    }
  }

  // 获取启动盘（用于排除）
  $boot_mount = realpath("/boot");
  $boot_dev = exec("findmnt -n -o SOURCE --target $boot_mount 2>/dev/null");
  $boot_dev_base = preg_replace('#[0-9]+$#', '', $boot_dev);

  // 遍历 by-id 设备
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
    $short = preg_replace('#^(ata|nvme)-#', '', $id); // 去除前缀

    $label = $short;
    if (preg_match('#/dev/([a-z0-9]+)[p]?[0-9]*$#', $real, $m)) {
      $base = "/dev/" . $m[1];
      if (isset($dev_to_disk[$base])) {
        $label .= " → " . $dev_to_disk[$base];
      } elseif (strpos($real, '/dev/nvme') === 0) {
        $label .= " (" . basename($real) . ")";
      }
    }

    $result[] = ['id' => $id, 'dev' => $real, 'label' => $label];
  }

  // 排序规则：先 Parity，再 Disk1~N，最后其它
  usort($result, function($a, $b) {
    $map = ['Parity' => 0, 'Parity 2' => 1];
    $a_priority = 999;
    $b_priority = 999;

    foreach ($map as $key => $val) {
      if (strpos($a['label'], $key) !== false) $a_priority = $val;
      if (strpos($b['label'], $key) !== false) $b_priority = $val;
    }

    if (preg_match('/→ disk(\\d+)/i', $a['label'], $ma)) $a_priority = 10 + (int)$ma[1];
    if (preg_match('/→ disk(\\d+)/i', $b['label'], $mb)) $b_priority = 10 + (int)$mb[1];

    return $a_priority <=> $b_priority;
  });

  return $result;
}
