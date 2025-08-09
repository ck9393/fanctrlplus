<?php
$label_file = "/boot/config/plugins/fanctrlplus/pwm_labels.cfg";
$pwm_labels = [];
if (is_file($label_file)) {
  foreach (file($label_file, FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^(.+?)=(.+)$/', $line, $m)) {
      $pwm_labels[$m[1]] = $m[2];
    }
  }
}

function render_fan_block($cfg, $i, $pwms, $disks, $pwm_labels, $cpu_sensors) {
  // PWM fallback（如果值为空，则默认 fallback 为 40% 和 100%）
  $pwm_raw = isset($cfg['pwm']) && is_numeric($cfg['pwm']) ? $cfg['pwm'] : 102;
  $max_raw = isset($cfg['max']) && is_numeric($cfg['max']) ? $cfg['max'] : 255;

  $pwm_pct = round($pwm_raw * 100 / 255) . '%';
  $max_pct = round($max_raw * 100 / 255) . '%';

  // 温度 fallback（防止空值出现 UI 上显示为 0°C）
  $low = isset($cfg['low']) && is_numeric($cfg['low']) ? intval($cfg['low']) : 40;
  $high = isset($cfg['high']) && is_numeric($cfg['high']) ? intval($cfg['high']) : 60;

  ob_start();
  ?>
  <div class="fan-block" data-index="<?=$i?>" data-file="<?=htmlspecialchars($cfg['file'])?>">
    <input type="hidden" name="#file[<?=$i?>]" value="<?=htmlspecialchars($cfg['file'])?>" class="cfg-file">

    <fieldset class="fan-fieldset">
      <div style="position:absolute; top:10px; right:10px; width:36px; height:36px;">
        <div class="fan-svg-container" style="position:absolute; top:0; left:0; width:100%; height:100%;">
          <div style="position:absolute; top:0; left:0; width:100%; height:100%; cursor:help; z-index:1;"></div>
          <svg id="fan-icon-<?=$i?>" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" style="width:36px; height:36px; display:block; margin:auto;">
            <defs>
              <linearGradient id="flameGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="5%" stop-color="#FFD700"/>
                <stop offset="25%" stop-color="#FFA500"/>
                <stop offset="50%" stop-color="#FF8C00"/>
                <stop offset="75%" stop-color="#FF4500"/>
                <stop offset="100%" stop-color="#B22222"/>
              </linearGradient>
            </defs>
        
            <g class="rotor">
              <!-- fan blades -->
              <path fill="url(#flameGradient)" d="M176.713,229.639c5.603-16.892,16.465-31.389,30.628-41.571c-14.778-34.253-22.268-74.165-20.636-112.788 c0.217-5.095-4.279-13.455-15.648-8.54c-22.522,9.728-42.142,24.48-59.949,40.872c-17.008,15.667-20.853,40.637-7.96,56.168 C124.507,189.491,149.096,213.274,176.713,229.639z"/>
              <path fill="url(#flameGradient)" d="M290.516,179.908c22.286-29.938,53.094-56.375,87.366-74.264c4.534-2.367,9.52-10.436-0.435-17.843 c-19.674-14.634-42.268-24.253-65.352-31.47c-22.086-6.909-45.623,2.249-52.623,21.198c-11.605,31.334-19.892,64.536-20.254,96.632 C256.644,170.561,274.614,172.728,290.516,179.908z"/>
              <path fill="url(#flameGradient)" d="M412.281,169.754c-32.949,5.63-65.842,15.041-93.822,30.772c11.841,13.3,18.949,29.956,20.69,47.319 c37.064,4.324,75.362,17.798,107.983,38.524c4.316,2.738,13.799,3.029,15.232-9.302c2.847-24.354-0.108-48.724-5.403-72.334 C451.884,182.157,432.191,166.345,412.281,169.754z"/>
              <path fill="url(#flameGradient)" d="M335.287,282.361c-5.603,16.881-16.464,31.38-30.627,41.56c14.779,34.254,22.267,74.165,20.635,112.789 c-0.217,5.095,4.28,13.455,15.667,8.54c22.504-9.729,42.142-24.48,59.93-40.872c17.008-15.667,20.853-40.637,7.96-56.168 C387.511,322.508,362.904,298.717,335.287,282.361z"/>
              <path fill="url(#flameGradient)" d="M221.501,332.091c-22.267,29.93-53.075,56.367-87.348,74.264c-4.533,2.358-9.519,10.427,0.435,17.834 c19.675,14.634,42.269,24.253,65.352,31.471c22.086,6.908,45.623-2.249,52.623-21.198c11.605-31.334,19.892-64.527,20.254-96.632 C255.392,341.43,237.404,339.263,221.501,332.091z"/>
              <path fill="url(#flameGradient)" d="M172.85,264.146c-37.064-4.326-75.362-17.798-107.982-38.525c-4.316-2.738-13.8-3.028-15.233,9.303 c-2.846,24.352,0.109,48.724,5.422,72.333c5.059,22.576,24.752,38.388,44.663,34.979c32.948-5.631,65.842-15.042,93.82-30.772 C181.699,298.164,174.591,281.509,172.85,264.146z"/>
            </g>
        
            <!-- fan hub -->
            <path class="hub" fill="var(--hub-color)" d="M255.991,195.503c-33.402,0-60.475,27.091-60.475,60.492c0,33.411,27.073,60.493,60.475,60.493 c33.419,0,60.51-27.082,60.51-60.493C316.502,222.594,289.411,195.503,255.991,195.503z"/>
        
            <!-- frame -->
            <path class="frame" fill="var(--frame-color)" d="M463.017,0H49.001C21.928,0,0.005,21.932,0.005,48.987v414.016C0.005,490.059,21.928,512,49.001,512h414.016 c27.055,0,48.978-21.941,48.978-48.996V48.987C511.995,21.932,490.073,0,463.017,0z M463.017,31.706 c9.539,0,17.281,7.743,17.281,17.282c0,9.547-7.742,17.28-17.281,17.28c-9.556,0-17.299-7.734-17.299-17.28 C445.718,39.448,453.461,31.706,463.017,31.706z M49.001,31.706c9.538,0,17.281,7.743,17.281,17.282 c0,9.556-7.743,17.28-17.281,17.28c-9.556,0-17.299-7.724-17.299-17.28C31.702,39.448,39.445,31.706,49.001,31.706z M48.983,480.284c-9.538,0-17.281-7.734-17.281-17.281s7.743-17.281,17.281-17.281c9.556,0,17.299,7.734,17.299,17.281 S58.539,480.284,48.983,480.284z M463.017,480.284c-9.556,0-17.299-7.734-17.299-17.281c0-9.538,7.743-17.281,17.299-17.281 c9.539,0,17.281,7.743,17.281,17.281C480.298,472.55,472.556,480.284,463.017,480.284z M255.991,489.324 c-128.855,0-233.32-104.466-233.32-233.33c0-128.854,104.466-233.319,233.32-233.319c128.873,0,233.338,104.465,233.338,233.319 C489.329,384.858,384.864,489.324,255.991,489.324z"/>
          </svg>
        </div>
            <span class="drag-handle" ><i class="fa fa-reorder"></i></span>
      </div> 

<!-- 放在每个风扇 fan-block 内部 -->
<div class="fan-tools" style="position:absolute; bottom:10px; right:10px; display:flex; flex-direction:column; width: 86px; height: 96px">
  <button type="button" class="show-chart-btn" 
          onclick="showFanChart(this)" 
          title="Preview Fan Speed Curve">
    <i class="fa fa-line-chart" style= "color: var(--blue-800); font:"></i> Chart
  </button>
  <button type="button" class="delete-btn" 
          onclick="removeFan(this)" 
          title="Delete this fan configuration">
    DELETE
  </button>
</div>

      <table style="width:100%;">
        <!-- Custom Name -->
        <tr>
          <td style="cursor: help;" title="Enter a unique name for this fan configuration. Avoid spaces or special characters.">Custom Name</td>
          <td>
            <input type="text" name="custom[<?=$i?>]" class="custom-name-input" value="<?=htmlspecialchars($cfg['custom'] ?? '')?>" placeholder="Required (e.g. HDDBay)" required>
          </td>
        </tr>

        <!-- Fan Control Dropdown -->
        <tr>
          <td style="cursor: help;" title="Enable or disable this fan controller">Fan Control:</td>
          <td>
            <select name="service[<?=$i?>]">
              <option value="0" <?=($cfg['service'] ?? '') == '0' ? 'selected' : ''?>>Disabled</option>
              <option value="1" <?=($cfg['service'] ?? '') == '1' ? 'selected' : ''?>>Enabled</option>
            </select>
          </td>
        </tr>



        <!-- PWM Controller -->
        <tr>
          <td style="cursor: help;" title="Each fan corresponds to a PWM controller (pwm1, pwm2, etc). Select the one controlling this fan. You can use the Identify section below to locate and label each fan.">PWM Controller:</td>
          <td>
            <select name="controller[<?=$i?>]" class="pwm-controller">
              <option value="">-- Select PWM Controller --</option>
              <?php foreach ($pwms as $pwm): 
                $label = $pwm_labels[$pwm['sensor']] ?? '';
                $display = $pwm['chip'] . ' - ' . $pwm['name'];
                if ($label) $display .= '（' . htmlspecialchars($label) . '）';
              ?>
                <option value="<?=$pwm['sensor']?>" <?=($cfg['controller'] ?? '') == $pwm['sensor'] ? 'selected' : ''?>>
                  <?= $display ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>

        <!-- Fan Speed Range -->
        <tr>
          <td style="cursor: help;" title="Set the minimum and maximum fan speed (0–100%). % will be automatically converted to PWM. Hover to see actual values.">Fan Speed Range:</td>
          <td>
            <div style="display: grid; grid-template-columns: 130px 40px 130px; align-items: center;">

              <!-- 左侧 Min -->
              <input type="text"
                    id="pwm_percent_input_<?=$i?>"
                    name="pwm_percent[<?=$i?>]"
                    inputmode="numeric"
                    style="width: 100%; text-align: left;"
                    value="<?=$pwm_pct?>"
                    title="Minimum speed: <?=$pwm_pct?> = <?=htmlspecialchars($pwm_raw)?> PWM"
                    placeholder="Min %">


              <!-- 中间波浪号 -->
              <span style="text-align: center;">~</span>

              <!-- 右侧 Max -->
              <input type="text"
                    id="max_percent_input_<?=$i?>"
                    name="max_percent[<?=$i?>]"
                    inputmode="numeric"
                    style="width: 100%; text-align: left;"
                    value="<?=$max_pct?>"
                    title="Maximum speed: <?=$max_pct?> = <?=htmlspecialchars($max_raw)?> PWM"
                    placeholder="Max %">
            </div>
          </td>
        </tr>

        <!-- Interval -->
        <tr>
          <td style="cursor: help;" title="Check temperature and adjust fan speed every X minutes.">Interval:</td>
          <td>
            <input type="text"
                  id="interval_input_<?=$i?>"
                  name="interval[<?=$i?>]"
                  class="interval-input"
                  inputmode="numeric"
                  value="<?=htmlspecialchars(($cfg['interval'] ?? '') . ' min')?>"
                  placeholder="Recommended: 1–5 min"
                  style="width: 225px; display: inline-block; margin-right: 1px; text-align: left;">

            <span class="fanctrlplus-interval-refresh"
                  style="cursor: pointer; font-size: 13px; color: var(--blue-800); margin-left: 1px; vertical-align: middle;"
                  title="Manual Run: Read current temperature and set fan speed immediately"
                  data-label="<?=htmlspecialchars($cfg['custom'] ?? '')?>">
              <span class="fa fa-refresh" style="font-size: 13px;"></span> Run Now
            </span>
          </td>
        </tr>

        <tr><td colspan="2" class="subhead">Disk Temperature Settings</td></tr>

        <!-- Include Disk(s) -->
        <tr>
          <td style="cursor: help;" title="Select disks, NVMe drives, or other block devices to monitor for this fan.">Include Disk(s):</td>
          <td>
            <select class="disk-select" name="disks[<?=$i?>][]" multiple style="width:300px;">
              <?php
              $selected = explode(',', $cfg['disks'] ?? '');
              foreach ($disks as $group => $entries):
              ?>
                <optgroup label="<?=htmlspecialchars($group)?>">
                  <?php foreach ($entries as $disk):
                    $sel = in_array($disk['id'], $selected) ? 'selected' : '';
                  ?>
                    <option value="<?=$disk['id']?>" <?=$sel?> title="<?=$disk['id']?>&#10;<?=$disk['dev']?>"><?=htmlspecialchars($disk['label'])?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>

        <!-- Disk Temperature Range -->
        <tr>
          <td style="cursor: help;" title="Fan runs at the configured minimum speed if the highest selected disk temperature is at or below the Low Temp. Fan ramps up linearly and reaches maximum speed at or above the High Temp.">Disk Temperature Range:</td>
          <td>
            <div style="display: grid; grid-template-columns: 130px 40px 130px; align-items: center;">

              <!-- 左侧 Low Temp -->
              <input type="text"
                    id="low_temp_input_<?=$i?>"
                    name="low[<?=$i?>]"
                    class="low-temp-input"
                    inputmode="numeric"
                    style="width: 100%; text-align: left;"
                    value="<?=$low?>°C"
                    title="Low Temp: <?=intval($cfg['low'] ?? 40)?>°C"
                    placeholder="Low °C">

              <!-- 中间波浪号 -->
              <span style="text-align: center;">~</span>

              <!-- 右侧 High Temp -->
              <input type="text"
                    id="high_temp_input_<?=$i?>"
                    name="high[<?=$i?>]"
                    class="high-temp-input"
                    inputmode="numeric"
                    style="width: 100%; text-align: left;"
                    value="<?=$high?>°C"
                    title="High Temp: <?=intval($cfg['high'] ?? 60)?>°C"
                    placeholder="High °C">

            </div>
          </td>
        </tr>

        <tr><td colspan="2" class="subhead">CPU Temperature Settings</td></tr>

        <!-- CPU Temp Monitoring Dropdown -->
        <tr>
          <td style="cursor: help;" title="Enable or disable monitoring CPU temperature for this fan.">CPU Temp Monitor:</td>
          <td>
            <select id="cpu-enable-<?=$i?>" name="cpu_enable[<?=$i?>]" onchange="handleCpuEnableChange(this, <?=$i?>);">
              <option value="0" <?=($cfg['cpu_enable'] ?? '') != '1' ? 'selected' : ''?>>Disabled</option>
              <option value="1" <?=($cfg['cpu_enable'] ?? '') == '1' ? 'selected' : ''?>>Enabled</option>
            </select>
          </td>
        </tr>

        <!-- CPU Sensor -->
        <tr class="cpu-control cpu-control-<?=$i?>">
          <td class="cpu-label" style="cursor: help;" title="Automatically selected the most reliable CPU temperature sensor. Change only if necessary.">CPU Sensor:</td>
          <td>
            <select name="cpu_sensor[<?=$i?>]" class="cpu-input" style="width: 300px;" <?=($cfg['cpu_enable'] ?? '') != '1' ? 'disabled' : ''?>>
              <?php foreach ($cpu_sensors as $path => $label): ?>
                <option value="<?=htmlspecialchars($path)?>" <?=($cfg['cpu_sensor'] ?? '') == $path ? 'selected' : ''?>><?=htmlspecialchars($label)?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>

        <!-- CPU Temp Range -->
        <tr class="cpu-control cpu-control-<?=$i?>">
          <td class="cpu-label" style="cursor: help;" title="Fan runs at the configured minimum speed if the CPU temperature is at or below the Low Temp. Fan ramps up linearly and reaches maximum speed at or above the High Temp.">CPU Temperature Range:</td>
          <td>
            <div style="display: grid; grid-template-columns: 130px 40px 130px; align-items: center;">
              <input type="text"
                    id="cpu_low_temp_input_<?=$i?>"
                    name="cpu_min_temp[<?=$i?>]"
                    class="cpu-input"
                    inputmode="numeric"
                    style="width: 100%; text-align: left;"
                    value="<?=htmlspecialchars(($cfg['cpu_min_temp'] ?? '') . '°C')?>"
                    title="Low Temp: <?=intval($cfg['cpu_min_temp'] ?? 50)?>°C"
                    placeholder="Low °C"
                    <?=($cfg['cpu_enable'] ?? '') != '1' ? 'disabled' : ''?>>

              <span style="text-align: center;">~</span>

              <input type="text"
                    id="cpu_high_temp_input_<?=$i?>"
                    name="cpu_max_temp[<?=$i?>]"
                    class="cpu-input"
                    inputmode="numeric"
                    style="width: 100%; text-align: left;"
                    value="<?=htmlspecialchars(($cfg['cpu_max_temp'] ?? '') . '°C')?>"
                    title="High Temp: <?=intval($cfg['cpu_max_temp'] ?? 75)?>°C"
                    placeholder="High °C"
                    <?=($cfg['cpu_enable'] ?? '') != '1' ? 'disabled' : ''?>>
            </div>
          </td>
        </tr>
      </table>
    </fieldset>
  </div>
  <?php
  $html = ob_get_contents();
  ob_end_clean();

  return $html;
}