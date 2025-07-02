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

  public static function remove(string $filename): bool {
    $order = self::readOrder();
    $left = array_filter($order['left'], fn($f) => $f !== $filename);
    $right = array_filter($order['right'], fn($f) => $f !== $filename);

    return self::writeOrder(array_values($left), array_values($right));
  }

  public static function replaceFileName($old_file, $new_file) {
        $cfg_dir = "/boot/config/plugins/fanctrlplus"; // 路径可按你实际定义
        $order_file = "$cfg_dir/order.cfg";

        if (!file_exists($order_file)) return;

        $lines = file($order_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $out = [];
        foreach ($lines as $line) {
            // 只替换 value
            if (preg_match('/^(left\d+|right\d+)="([^"]+)"/', $line, $m)) {
                $key = $m[1];
                $val = $m[2];
                if ($val === $old_file) {
                    $val = $new_file;
                }
                $out[] = "{$key}=\"{$val}\"";
            } else {
                $out[] = $line;
            }
        }
        file_put_contents($order_file, implode("\n", $out) . "\n");
    }
}
