<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('partials/admin_header', isset($data) ? $data : []);
$template = isset($template) ? $template : null;
?>
<div class="max-w-6xl mx-auto bg-white p-6 card-shadow">
  <div class="flex justify-between items-center mb-4">
    <h3 class="text-lg font-semibold">Preview: <?= htmlspecialchars($template['template_name']) ?></h3>
    <div>
      <a href="<?= site_url('templates') ?>" class="px-3 py-1 border rounded">Back</a>
    </div>
  </div>

  <div class="mb-4">
    <label class="block text-sm font-medium">Sample JSON (override stored sample)</label>
    <textarea id="preview-sample-json" rows="6" class="w-full p-2 border rounded"><?= !empty($template['sample_json']) ? htmlspecialchars($template['sample_json']) : '' ?></textarea>
    <div class="mt-2 flex gap-2">
      <button id="btn-render" class="px-4 py-2 bg-indigo-600 text-white rounded">Render Preview</button>
      <?php if (!empty($template['template_file'])): ?>
        <?php
          // Provide direct open in new tab to static index if exists
          $folder = FCPATH . $template['template_file'] . '/';
          $static_url = null;
          if (file_exists($folder . 'index.html')) $static_url = base_url($template['template_file'] . '/index.html');
          else {
            $htmls = glob($folder . '*.html');
            if (!empty($htmls)) $static_url = base_url($template['template_file'] . '/' . basename($htmls[0]));
          }
        ?>
        <?php if ($static_url): ?>
          <a href="<?= $static_url ?>" target="_blank" class="px-4 py-2 border rounded">Open Raw (New Tab)</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div id="preview-wrapper" style="border:1px solid #e6e9ef;border-radius:8px;overflow:hidden">
    <iframe id="preview-iframe" style="width:100%;min-height:720px;border:0;"></iframe>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('btn-render');
  var iframe = document.getElementById('preview-iframe');
  var sampleEl = document.getElementById('preview-sample-json');

  btn.addEventListener('click', function () {
    var sample = sampleEl.value;
    fetch('<?= site_url('templates/preview_render/'.$template['id']) ?>', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body: 'sample_json=' + encodeURIComponent(sample || '')
    }).then(r=>r.text()).then(html=>{
      iframe.srcdoc = html;
    }).catch(e=>{
      console.error(e);
      alert('Preview failed: ' + e.message);
    });
  });

  // initial render using stored sample_json (if any)
  btn.click();
});
</script>

<?php $this->load->view('partials/admin_footer'); ?>
