<?php
function render_fan_block($cfg_file, $i, $pwms, $disks) {
  $cfg = parse_ini_file("/boot/config/plugins/fanctrlplus/$cfg_file");
  $cfg['file'] = $cfg_file;

  ob_start();
  ?>
  <div class="fan-block" style="display:inline-block; width:48%; vertical-align:top;">
    <input type="hidden" name="#file[<?=$i?>]" value="<?=$cfg['file']?>" class="cfg-file">
    <fieldset style="margin:10px 0; padding:34px 16px 12px 16px; border:1px solid #ccc; border-radius:6px; position:relative;">
      <span class="fan-status" style="position:absolute; top:6px; right:8px;">🔄</span>
      <button type="button" onclick="removeFan(this)" title="Delete this fan configuration" style="position:absolute; bottom:6px; right:8px;">DELETE</button>
      <table style="width:100%;">
        <tr>
          <td style="cursor: help;" title="Enter a unique name for this fan. Avoid spaces or special characters.">Custom Name</td>
          <td><input type="text" name="custom[<?=$i?>]" value="<?=htmlspecialchars($cfg['custom']??'')?>" placeholder="Required (e.g. HDDBay)" required></td>
        </tr>
        <tr>
          <td style="cursor: help;" title="Enable or disable this fan controller">Fan Control:</td>
          <td>
            <select name="service[<?=$i?>]">
              <option value="0" <?=($cfg['service']??'')=='0'?'selected':''?>>Disabled</option>
              <option value="1" <?=($cfg['service']??'')=='1'?'selected':''?>>Enabled</option>
            </select>
          </td>
        </tr>
        <tr>
          <td style="cursor: help;" title="Select the PWM controller for this fan">PWM Controller:</td>
          <td>
            <select name="controller[<?=$i?>]">
              <?php foreach ($pwms as $pwm): ?>
              <option value="<?=$pwm['sensor']?>" <?=($cfg['controller']??'')==$pwm['sensor']?'selected':''?>><?=$pwm['chip']?> - <?=$pwm['name']?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" onclick="pauseFan($(this).prev().val(), this)" title="Pause this fan for 30 seconds to identify its location.">Pause 30s</button>
          </td>
        </tr>
        <tr>
          <td style="cursor: help;" title="Set the minimum PWM value (0–255)">Min PWM:</td>
          <td><input type="text" name="pwm[<?=$i?>]" value="<?=htmlspecialchars($cfg['pwm']??'')?>"></td>
        </tr>
        <tr>
          <td style="cursor: help;" title="At or below this temperature, fan will run at the configured minimum PWM">Low Temp (°C):</td>
          <td><input type="number" name="low[<?=$i?>]" value="<?=htmlspecialchars($cfg['low']??'')?>"></td>
        </tr>
        <tr>
          <td style="cursor: help;" title="At or above this temperature, fan will run at the configured maximum PWM">High Temp (°C):</td>
          <td><input type="number" name="high[<?=$i?>]" value="<?=htmlspecialchars($cfg['high']??'')?>"></td>
        </tr>
        <tr>
          <td style="cursor: help;" title="Check temperature and adjust fan speed every X minutes.">Interval (min):</td>
          <td><input type="number" name="interval[<?=$i?>]" value="<?=htmlspecialchars($cfg['interval']??'')?>"></td>
        </tr>
        <tr>
          <td style="cursor: help;" title="Select disk(s) to monitor for temperature control.">Include Disk(s):</td>
          <td>
            <select class="disk-select" name="disks[<?=$i?>][]" multiple style="width:400px;">
              <?php foreach ($disks as $d):
                $id = $d['id'];
                $sel = in_array($id, explode(',', $cfg['disks']??'')) ? 'selected' : '';
              ?>
              <option value="<?=$id?>" <?=$sel?>><?=$id?> → <?=$d['dev']?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
      </table>
    </fieldset>
  </div>
  <?php
  return ob_get_clean();
