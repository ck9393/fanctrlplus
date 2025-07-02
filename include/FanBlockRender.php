<?php
function render_fan_block($cfg, $i, $pwms, $disks) {

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
      
      <button type="button" class="delete-btn" title="Delete this fan configuration" style="position:absolute; bottom:0px; right:0px; transform: translate(2px, 0px);">DELETE</button>

      <table style="width:100%;">
        <tr>
          <td style="cursor: help;" title="Enter a unique name for this fan configuration. Avoid spaces or special characters.">Custom Name</td>
          <td>
            <input type="text" name="custom[<?=$i?>]" class="custom-name-input" value="<?=htmlspecialchars($cfg['custom'] ?? '')?>" placeholder="Required (e.g. HDDBay)" required>
          </td>
        </tr>

        <tr>
          <td style="cursor: help;" title="Enable or disable this fan controller">Fan Control:</td>
          <td>
            <select name="service[<?=$i?>]">
              <option value="0" <?=($cfg['service'] ?? '') == '0' ? 'selected' : ''?>>Disabled</option>
              <option value="1" <?=($cfg['service'] ?? '') == '1' ? 'selected' : ''?>>Enabled</option>
            </select>
          </td>
        </tr>

        <tr>
          <td style="cursor: help;" title="Select the PWM controller for this fan configuration">PWM Controller:</td>
          <td>
            <select name="controller[<?=$i?>]" class="pwm-controller">
              <option value="">-- Select PWM --</option>
              <?php foreach ($pwms as $pwm): ?>
                <option value="<?=$pwm['sensor']?>" <?=($cfg['controller'] ?? '') == $pwm['sensor'] ? 'selected' : ''?>>
                  <?=$pwm['chip']?> - <?=$pwm['name']?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="button" onclick="pauseFan($(this).prev().val(), this)" title="Pause this fan for 30 seconds to identify its location.">Pause 30s</button>
          </td>
        </tr>

        <tr>
          <td style="cursor: help;" title="Set the minimum PWM value (0–255)">Min PWM:</td>
          <td>
            <input type="number" name="pwm[<?=$i?>]" value="<?=htmlspecialchars($cfg['pwm'] ?? '')?>">
          </td>
        </tr>

        <tr>
          <td style="cursor: help;" title="At or below this temperature, fan will run at the configured minimum PWM">Low Temp (°C):</td>
          <td>
            <input type="number" name="low[<?=$i?>]" value="<?=htmlspecialchars($cfg['low'] ?? '')?>">
          </td>
        </tr>

        <tr>
          <td style="cursor: help;" title="At or above this temperature, fan will run at the configured maximum PWM">High Temp (°C):</td>
          <td>
            <input type="number" name="high[<?=$i?>]" value="<?=htmlspecialchars($cfg['high'] ?? '')?>">
          </td>
        </tr>

        <tr>
          <td style="cursor: help;" title="Check temperature and adjust fan speed every X minutes.">Interval (min):</td>
          <td>
            <input type="number" name="interval[<?=$i?>]" class="interval-input" value="<?=htmlspecialchars($cfg['interval'] ?? '')?>" placeholder="Recommended: 1–5 min" min="1" required style="width:225px;display:inline-block;margin-right:4px;">
            <span class="fanctrlplus-interval-refresh"
                  style="cursor:pointer;font-size:13px;color:var(--blue-800);margin-left:1px;vertical-align:middle;"
                  title="Manual Run: Read current temperature and set fan speed immediately"
                  data-label="<?=htmlspecialchars($cfg['custom'] ?? '')?>">
              <span class="fa fa-refresh"></span> Run Now    
            </span>
          </td>
        </tr>

        <tr>
          <td style="cursor: help;" title="Select disk(s) to monitor for temperature control.">Include Disk(s):</td>
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
      </table>
    </fieldset>
  </div>
  <?php
  $html = ob_get_contents();
  ob_end_clean();

  return $html;
}