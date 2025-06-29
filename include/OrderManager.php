<?php
class OrderManager {
  private static string $cfg_dir  = "/boot/config/plugins/fanctrlplus";
  private static string $order_file = "/boot/config/plugins/fanctrlplus/order.cfg";

  public static function readOrder(): array {
    $left = [];
    $right = [];

    if (!is_file(self::$order_file)) return ['left' => [], 'right' => []];

    $lines = file(self::$order_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      if (preg_match('/^(left|right)(\d+)\s*=\s*"?(.*?)"?$/', $line, $m)) {
        $side = $m[1];
        $idx  = (int)$m[2];
        $cfg  = $m[3];
        if ($side === 'left')  $left[$idx]  = $cfg;
        if ($side === 'right') $right[$idx] = $cfg;
      }
    }

    ksort($left);
    ksort($right);

    return ['left' => array_values($left), 'right' => array_values($right)];
  }

  public static function writeOrder(array $left, array $right): bool {
    $lines = [];

    foreach ($left as $i => $cfg) {
      $lines[] = 'left' . $i . '="' . $cfg . '"';
    }
    foreach ($right as $i => $cfg) {
      $lines[] = 'right' . $i . '="' . $cfg . '"';
    }

    $content = implode("\n", $lines) . "\n";
    return file_put_contents(self::$order_file, $content) !== false;
  }
}