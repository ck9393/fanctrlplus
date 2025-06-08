function renderFanBlock($cfg, $i) {
  ob_start();
  // 输出 <div class="fan-block"> ... </div>，用和 .page 中一样的 HTML，只是变量来自 $cfg, $i
  ?>
  <div class="fan-block" style="display:inline-block; width:48%; vertical-align:top;">
    <input type="hidden" name="#file[<?=$i?>]" value="<?=$cfg['file']?>" class="cfg-file">
    <fieldset style="margin:10px 0; padding:34px 16px 12px 16px; border:1px solid #ccc; border-radius:6px; position:relative;">
      <span class="fan-status" style="position:absolute; top:6px; right:8px;">🔄</span>
      <button type="button" onclick="removeFan(this)" title="Delete this fan configuration" style="position:absolute; bottom:6px; right:8px;">DELETE</button>
      <table style="width:100%;">
        <!-- 所有字段照你现有的输出，但来自 $cfg, $i -->
        ...
      </table>
    </fieldset>
  </div>
  <?
  return ob_get_clean();
}
