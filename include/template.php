<?php
$plugin = 'fanctrlplus';

$index = isset($_GET['index']) ? (int)$_GET['index'] : 0;

// 获取有效磁盘
$disks = glob("/dev/disk/by-id/*");
$valid_disks = [];
foreach ($disks as $dev) {
  if (!is_link($dev) || strpos($dev, "part") !== false) continue;
  $real = realpath($dev);
  if (strpos($real, "/dev/sd") === false && strpos($real, "/dev/nvme") === false) continue;
  $valid_disks[] = ['id' => basename($dev), 'dev' => $real];
}

// 获取 PWM 控制器
exec("find /sys/devices -type f -iname 'pwm[0-9]' -exec dirname \"{}\" + | uniq", $chips);
$pwms = [];
foreach ($chips as $chip) {
  $name = is_file("$chip/name") ? file_get_contents("$chip/name") : '';
  foreach (glob("$chip/pwm[0-9]") as $pwm) {
    $pwms[] = ['chip'=>$name, 'name'=>basename($pwm), 'sensor'=>$pwm];
  }
}
?>

<div class="fan-block" style="display:inline-block; width:48%; vertical-align:top;">
  <input type="hidden" name="#file[<?=$index?>]" value="fanctrlplus_temp<?=$index?>.cfg" class="cfg-file">
  <fieldset style="margin:10px; padding:26px 10px 36px 10px; border:1px solid #ccc; border-radius:6px; position:relative;">
    <span class="fan-status" style="position:absolute; top:6px; right:8px;">🔄</span>
    <button type="button" onclick="removeFan(this)" style="position:absolute; bottom:6px; right:8px;">DELETE</button>
    <table style="width:100%;">
      <tr>
        <td style="width:140px;">Custom Name:</td>
        <td>
          <input type="text" name="custom[<?=$index?>]" class="custom-name" placeholder="e.g. HDDBay" style="width:100%;">
        </td>
      </tr>
      <tr>
        <td>Fan Control:</td>
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
            <? foreach ($pwms as $pwm): ?>
              <option value="<?=$pwm['sensor']?>"><?=$pwm['chip']?> - <?=$pwm['name']?></option>
            <? endforeach; ?>
          </select>
          <button type="button" onclick="pauseFan($(this).prev().val(), this)">Pause 30s</button>
        </td>
      </tr>
      <tr><td>Min PWM:</td><td><input type="text" name="pwm[<?=$index?>]" value="100"></td></tr>
      <tr><td>Low Temp (°C):</td><td><input type="number" name="low[<?=$index?>]" min="0" max="100" value="40"></td></tr>
      <tr><td>High Temp (°C):</td><td><input type="number" name="high[<?=$index?>]" min="0" max="100" value="60"></td></tr>
      <tr><td>Interval (min):</td><td><input type="number" name="interval[<?=$index?>]" min="1" max="60" value="2"></td></tr>
      <tr>
        <td>Include Disks:</td>
        <td>
          <select class="disk-select" name="disks[<?=$index?>][]" multiple style="width:400px;">
            <? foreach ($valid_disks as $d): ?>
              <option value="<?=$d['id']?>"><?=$d['id']?> → <?=$d['dev']?></option>
            <? endforeach; ?>
          </select>
        </td>
      </tr>
    </table>
  </fieldset>
</div>
