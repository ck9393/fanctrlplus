<?php
// =============================
// Migrate hwmonX cfg 与 label 路径
// =============================
function normalize_chip_name(string $chip): string {
    // 去掉结尾的 .数字
    $chip = preg_replace('/\.\d+$/', '', $chip);
    // 去掉 -isa-XXXX 这种片段
    $chip = preg_replace('/-isa-[0-9a-fA-Fx]+$/', '', $chip);
    return $chip;
}

function build_pwm_map(): array {
    $map = [];
    foreach (glob("/sys/class/hwmon/hwmon*") as $dir) {
        $name_file = "$dir/name";
        if (!is_file($name_file)) continue;

        $chip = normalize_chip_name(trim(file_get_contents($name_file)));

        foreach (glob("$dir/pwm[0-9]") as $pwm_path) {
            $pwmN = basename($pwm_path);
            $real = realpath($pwm_path) ?: $pwm_path;
            $map["$chip:$pwmN"] = $real;
        }
    }
    return $map;
}

function extract_chip_and_pwm_from_path(string $old_path): ?array {
    $old_path = trim($old_path, " \t\n\r\0\x0B\"'");

    // 先从路径提取 pwmN
    preg_match_all('/pwm(\d+)/', $old_path, $pm);
    if (empty($pm[1])) return null;
    $pwmN = 'pwm' . end($pm[1]);

    // 先看有没有 platform 节点（nct6775.672 这种）
    if (preg_match('#/platform/([^/]+)/#', $old_path, $m)) {
        $platform = $m[1];
        // 遍历 platform/$platform/hwmon/* 目录，找对应 pwmN
        foreach (glob("/sys/devices/platform/$platform/hwmon/hwmon*") as $dir) {
            if (is_file("$dir/$pwmN") && is_file("$dir/name")) {
                $chip = normalize_chip_name(trim(@file_get_contents("$dir/name")));
                if ($chip !== '') {
                    return [$chip, $pwmN];
                }
            }
        }
    }

    // 如果没 platform，就回退用 hwmonX + /sys/class/hwmon
    preg_match_all('/hwmon(\d+)/', $old_path, $hm);
    if (!empty($hm[1])) {
        $hwmon = 'hwmon' . end($hm[1]);
        $name_file = "/sys/class/hwmon/$hwmon/name";
        if (is_file($name_file)) {
            $chip = normalize_chip_name(trim(@file_get_contents($name_file)));
            if ($chip !== '') return [$chip, $pwmN];
        }
    }

    // 最后兜底：扫描所有 hwmon*
    foreach (glob("/sys/class/hwmon/hwmon*") as $dir) {
        if (is_file("$dir/$pwmN") && is_file("$dir/name")) {
            $chip = normalize_chip_name(trim(@file_get_contents("$dir/name")));
            if ($chip !== '') return [$chip, $pwmN];
        }
    }

    return null;
}

function log_migrate(string $msg): void {
    // 本地独立日志
    @file_put_contents("/var/log/fanctrlplus-migrate.log",
        date("c")." ".$msg."\n", FILE_APPEND);
    // 再打一份到 syslog
    @exec("logger -t fanctrlplus '$msg'");
}

function safe_rewrite(string $file, string $content): bool {
    $content = rtrim($content, "\n") . "\n";
    $old = @file_get_contents($file);
    if ($old !== false && rtrim($old, "\n") . "\n" === $content) return false;
    $tmp = $file . '.tmp';
    @file_put_contents($tmp, $content, LOCK_EX);
    @rename($tmp, $file);
    return true;
}

function migrate_cfg_and_labels(string $plugin): void {
    $cfgpath   = "/boot/config/plugins/$plugin";
    $labelFile = "$cfgpath/pwm_labels.cfg";
    $pwm_map   = build_pwm_map();

    // --- labels ---
    if (is_file($labelFile)) {
        $lines = file($labelFile, FILE_IGNORE_NEW_LINES) ?: [];
        $changed = false; $out = [];
        foreach ($lines as $line) {
            if (!preg_match('/^(.+?)=(.*)$/', $line, $m)) { $out[]=$line; continue; }
            $old_path = trim($m[1], " \t\n\r\0\x0B\"'");
            $label    = $m[2];

            if (preg_match('/^__FCP_[A-Z0-9_]+__$/', $old_path)) {
                $out[] = $line;
                continue;
            }

            $pair = extract_chip_and_pwm_from_path($old_path);
            if (!$pair) { log_migrate("migrate label: skip (unparsable) $old_path"); $out[]=$line; continue; }
            [$chip,$pwmN] = $pair;
            $key = "$chip:$pwmN";
            if (!isset($pwm_map[$key])) { log_migrate("migrate label: no match for $chip:$pwmN, keep $old_path"); $out[]=$line; continue; }

            $new_path = $pwm_map[$key];
            if ($new_path !== $old_path) {
                if (preg_match('#/(hwmon\d+)/#', $old_path, $o) && preg_match('#/(hwmon\d+)/#', $new_path, $n)) {
                    log_migrate("migrate label: $old_path → $new_path ({$o[1]} → {$n[1]})");
                } else {
                    log_migrate("migrate label: $old_path → $new_path");
                }
                $changed = true;
                $out[] = $new_path.'='.$label;
            } else {
                $out[] = $line;
            }
        }
        if ($changed) safe_rewrite($labelFile, implode("\n", $out));
    }

    // --- cfgs ---
    foreach (glob("$cfgpath/{$plugin}_*.cfg") ?: [] as $cfgfile) {
        $ini = @parse_ini_file($cfgfile);
        if (!$ini) continue;

        $old_path = trim((string)($ini['controller'] ?? ''), " \t\n\r\0\x0B\"'");

        if ($old_path === '' || !preg_match('#/hwmon\d+/pwm\d+$#', $old_path)) {
            continue;
        }

        $pair = extract_chip_and_pwm_from_path($old_path);
        if (!$pair) { 
            log_migrate("migrate cfg: skip (unparsable) $cfgfile controller=$old_path"); 
            continue; 
        }
        [$chip,$pwmN] = $pair;
        $key = "$chip:$pwmN";
        if (!isset($pwm_map[$key])) { 
            log_migrate("migrate cfg: no match for $cfgfile ($chip:$pwmN), keep $old_path"); 
            continue; 
        }

        $new_path = $pwm_map[$key];
        if ($new_path === $old_path) continue;

        if (preg_match('#/(hwmon\d+)/#', $old_path, $o) && preg_match('#/(hwmon\d+)/#', $new_path, $n)) {
            log_migrate("migrate cfg: $cfgfile controller: $old_path → $new_path ({$o[1]} → {$n[1]})");
        } else {
            log_migrate("migrate cfg: $cfgfile controller: $old_path → $new_path");
        }

        $ini['controller'] = $new_path;
        $buf=''; foreach ($ini as $k=>$v){ $v=str_replace('"','',(string)$v); $buf.=$k.'="'.$v."\"\n"; }
        safe_rewrite($cfgfile, $buf);
    }
}
// ================================
// END: Migrate hwmonX (cfg+labels)
// ================================

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

  // 映射 /dev/sdX ↔ 非 ZFS Pool (btrfs, xfs) 名（通过挂载点）
  $dev_to_pool_fs = [];
  $mounts = @shell_exec("findmnt -rn -o SOURCE,TARGET,FSTYPE | grep -E 'btrfs|xfs' 2>/dev/null");
  $mounts = is_string($mounts) ? $mounts : '';

  // 安全按行切分，忽略空行
  $lines = preg_split("/\r\n|\n|\r/", trim($mounts));
  foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '') continue;

      // SOURCE TARGET FSTYPE 以空白切分，确保有3段
      $parts = preg_split('/\s+/', $line);
      if (count($parts) < 3) continue;

      // 只取前3列，避免多余空白/列影响
      list($dev, $mount, $fstype) = array_slice($parts, 0, 3);

      // /dev/sdX1 -> /dev/sdX
      $base = preg_replace('/\d+$/', '', $dev);

      // 过滤 array 的 mdX 磁盘与 loop 设备，防止从挂载路径推断 pool 名
      if (strpos($base, '/dev/md') === 0 || strpos($base, '/dev/loop') === 0) continue;
      $pool_name = basename($mount);
      $dev_to_pool_fs[$base] = ucfirst($pool_name);
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
