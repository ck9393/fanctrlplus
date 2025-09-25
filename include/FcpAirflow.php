<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * 从 pwm_labels.cfg 读取：/abs/.../pwmN=Name
 * 用 realpath(dirname(...)) + 通道号N 作为 key，映射到同目录 fanN_input。
 */
function load_labels(): array {
  $cfg = '/boot/config/plugins/fanctrlplus/pwm_labels.cfg';
  $dirN_to_label = []; // key = realdir.'::'.N => label
  if (!is_file($cfg)) return $dirN_to_label;

  $lines = file($cfg, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;

    $eq = strpos($line, '=');
    if ($eq === false) continue;

    $pwm_path = trim(substr($line, 0, $eq));
    $label    = trim(substr($line, $eq+1));
    if ($label === '') continue;

    if (preg_match('~/pwm(\d+)$~i', $pwm_path, $m)) {
      $n    = (int)$m[1];
      $rdir = realpath(dirname($pwm_path)) ?: dirname($pwm_path);
      $dirN_to_label[$rdir.'::'.$n] = $label;
    }
  }
  return $dirN_to_label;
}

/**
 * 用 sensors -A 列出“真实存在”的风扇通道号 N。
 * sensors 通常只输出已接 tach 的 fanN 行，未接的口不会出现。
 * 我们不在这里过滤 RPM=0，以免停转时被误隐藏。
 */
function detect_present_channels(): array {
  $present = [];

  // A) 从 sensors 抓 fanN：fanN:/FANN.../Array Fan N: 三种格式
  $lines = [];
  @exec('sensors -A 2>/dev/null', $lines);
  foreach ($lines as $ln) {
    $ln = trim($ln);
    if (preg_match('/^fan\s*([0-9]+)\s*:\s*([0-9]+)\s*RPM/i', $ln, $m))     { $present[(int)$m[1]] = true; continue; }
    if (preg_match('/^FAN\s*([0-9]+)\b.*?\b([0-9]+)\s*RPM/i', $ln, $m))      { $present[(int)$m[1]] = true; continue; }
    if (preg_match('/^Array\s+Fan\s*([0-9]+)\s*:\s*([0-9]+)\s*RPM/i', $ln, $m)) { $present[(int)$m[1]] = true; continue; }
  }

  // B) 用 sysfs 兜底：凡是有“正转速(RPM>0)”的一律加入
  foreach (glob('/sys/class/hwmon/hwmon*/fan*_input') as $f) {
    if (!preg_match('/fan(\d+)_input$/', basename($f), $mm)) continue;
    $n   = (int)$mm[1];
    $rpm = @file_get_contents($f);
    if (is_numeric($rpm) && (int)$rpm > 0) $present[$n] = true;
  }

  // C) 如果什么都没抓到（极少数平台），退回把所有 fan*_input 都列出来
  if (!$present) {
    foreach (glob('/sys/class/hwmon/hwmon*/fan*_input') as $f) {
      if (preg_match('/fan(\d+)_input$/', basename($f), $mm)) $present[(int)$mm[1]] = true;
    }
  }

  ksort($present, SORT_NUMERIC);
  return array_keys($present);  // 例如 [1,2,4,5,7]
}

$dirN_to_label = load_labels();
$presentNs     = detect_present_channels();

$fans = [];
foreach ($presentNs as $n) {
  // 在所有 hwmon 下寻找对应的 fanN_input（通常只会命中一个）
  $match = null;
  foreach (glob('/sys/class/hwmon/hwmon*/fan'.$n.'_input') as $path) {
    if (is_file($path)) { $match = $path; break; }
  }
  if (!$match) continue;

  $rpm_raw = @file_get_contents($match);
  $rpm     = is_numeric($rpm_raw) ? (int)$rpm_raw : 0;

  $rdir = realpath(dirname($match)) ?: dirname($match);
  $key  = $rdir.'::'.$n;
  $name = $dirN_to_label[$key] ?? ('FAN '.$n);

  $fans[] = [
    'name'     => $name,
    'rpm'      => $rpm,
    'rpm_text' => $rpm.' RPM',
    'dom_id'   => 'fcp_fan_'.$n,
    'index'    => $n,
    'realdir'  => $rdir,
  ];
}

// 固定排序：按通道号 N 升序
usort($fans, fn($a,$b) => $a['index'] <=> $b['index']);

// Fan count：保持与渲染列表一致（包含 0 RPM）
echo json_encode([
  'count' => count($fans),
  'fans'  => $fans,
], JSON_UNESCAPED_UNICODE);