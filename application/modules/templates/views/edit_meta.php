<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('partials/admin_header', isset($data) ? $data : []);
$template = isset($template) ? $template : null;
?>
<div class="max-w-3xl mx-auto bg-white p-6 card-shadow">
  <h3 class="font-semibold mb-3">Edit Template Metadata: <?= htmlspecialchars($template['template_name']) ?></h3>
  <?php if ($this->session->flashdata('error')): ?><div class="text-red-600 mb-2"><?= $this->session->flashdata('error') ?></div><?php endif; ?>

  <form method="post" action="<?= site_url('templates/edit_meta/'.$template['id']) ?>">
    <label class="block text-sm font-medium">Placeholders (JSON array)</label>
    <textarea name="placeholders" rows="7" class="w-full p-2 border rounded mt-1"><?php
      if (!empty($template['placeholders'])) echo htmlspecialchars(is_array($template['placeholders']) ? json_encode($template['placeholders'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : $template['placeholders']);
    ?></textarea>
    <div class="text-xs text-slate-500 mt-1">Format: [{"key":"event.title","label":"Event Title","sample":"..."}]</div>

    <label class="block text-sm font-medium mt-3">Sample JSON (used for preview)</label>
    <textarea name="sample_json" id="sample-json" rows="10" class="w-full p-2 border rounded mt-1"><?php
      if (!empty($template['sample_json'])) echo htmlspecialchars($template['sample_json']);
      elseif (!empty($template['sample_json_decoded'])) echo htmlspecialchars(json_encode($template['sample_json_decoded'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    ?></textarea>

    <div class="mt-4 flex gap-2">
      <button class="bg-indigo-600 text-white px-4 py-2 rounded">Save Metadata</button>
      <a href="<?= site_url('templates') ?>" class="px-4 py-2 border rounded">Back</a>
      <button id="btn-preview-sample" type="button" class="ml-auto px-4 py-2 border rounded">Preview with Sample JSON</button>
    </div>
  </form>

  <div id="preview-area" class="mt-6" style="border:1px solid #e6e9ef;padding:8px;border-radius:8px;min-height:360px">
    <iframe id="preview-iframe" style="width:100%;min-height:320px;border:0;"></iframe>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('btn-preview-sample');
  var iframe = document.getElementById('preview-iframe');
  btn.addEventListener('click', function () {
    var sample = document.getElementById('sample-json').value;
    fetch('<?= site_url('templates/preview_render/'.$template['id']) ?>', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body: 'sample_json=' + encodeURIComponent(sample || '')
    }).then(r=>r.text()).then(html=>{
      iframe.srcdoc = html;
    }).catch(e=>{
      console.error(e);
      alert('Preview failed');
    });
  });
});
</script>

<?php $this->load->view('partials/admin_footer'); ?>
