<?php
$index = intval($_GET['index'] ?? 0);

// 获取所有 pwm 控制器
$pwms = [];
exec("find /sys/devices -type f -iname 'pwm[0-9]' -exec dirname \"{}\" + | uniq", $chips);
foreach ($chips as $chip) {
  $name = is_file("$chip/name") ? file_get_contents("$chip/name") : '';
  foreach (glob("$chip/pwm[0-9]") as $pwm) {
    $pwms[] = ['sensor'=>$pwm, 'label'=>trim($name).' - '.basename($pwm)];
  }
}

// 获取所有有效的 by-id 磁盘
$disks = [];
$seen = [];
foreach (glob("/dev/disk/by-id/*") as $dev) {
  if (!is_link($dev) || strpos($dev, "part") !== false) continue;
  $real = realpath($dev);
  if (strpos($real, "/dev/sd") === false && strpos($real, "/dev/nvme") === false) continue;
  if (in_array($real, $seen)) continue;
  $seen[] = $real;
  $disks[] = ['id'=>basename($dev), 'dev'=>$real];
}

// 自动生成临时配置文件名
$tmp_cfg = "fanctrlplus_temp{$index}.cfg";
?>

<div class="fan-block" style="display:inline-block; width:48%; vertical-align:top;">
  <input type="hidden" name="#file[<?=$index?>]" value="<?=$tmp_cfg?>" class="cfg-file">
  <fieldset style="margin:10px; padding:26px 10px 10px 10px; border:1px solid #ccc; position:relative;">
    <button type="button" onclick="removeFan(this)" style="position:absolute; top:4px; right:4px;">DELETE</button>

    <table style="width:100%;">
      <tr>
        <td style="width:140px;">Fan Control:</td>
        <td>
          <select name="service[<?=$index?>]">
            <option value="0">Disabled</option>
            <option value="1" selected>Enabled</option>
          </select>
        </td>
      </tr>
      <tr>
        <td>PWM Controller:</td>
        <td>
          <select name="controller[<?=$index?>]">
            <option disabled selected hidden>请选择</option>
            <?php foreach ($pwms as $p): ?>
              <option value="<?=htmlspecialchars($p['sensor'])?>"><?=htmlspecialchars($p['label'])?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" onclick="pauseFan($(this).prev().val(), this)">Pause 30s</button>
        </td>
      </tr>
      <tr><td>Min PWM:</td><td><input type="text" name="pwm[<?=$index?>]" value="0"></td></tr>
      <tr><td>Low Temp (°C):</td><td><input type="number" name="low[<?=$index?>]" min="0" max="100" value="35"></td></tr>
      <tr><td>High Temp (°C):</td><td><input type="number" name="high[<?=$index?>]" min="0" max="100" value="50"></td></tr>
      <tr><td>Interval (min):</td><td><input type="number" name="interval[<?=$index?>]" min="1" max="60" value="5"></td></tr>
      <tr>
        <td>Include Disks:</td>
        <td>
          <select class="disk-select" name="disks[<?=$index?>][]" multiple style="width:400px;">
            <?php foreach ($disks as $d): ?>
              <option value="<?=htmlspecialchars($d['id'])?>"><?=htmlspecialchars($d['id'])?> → <?=htmlspecialchars($d['dev'])?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
    </table>
  </fieldset>
</div>
