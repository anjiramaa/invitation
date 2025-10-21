<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('partials/admin_header', isset($data) ? $data : []);
?>
<div class="max-w-2xl mx-auto bg-white p-6 card-shadow">
  <h3 class="font-semibold mb-3">Upload ZIP untuk Template: <?= htmlspecialchars($template['template_name']) ?></h3>
  <?php if ($this->session->flashdata('error')): ?><div class="text-red-600 mb-2"><?= $this->session->flashdata('error') ?></div><?php endif; ?>
  <?php if ($this->session->flashdata('success')): ?><div class="text-green-600 mb-2"><?= $this->session->flashdata('success') ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" action="<?= site_url('templates/upload_zip/'.$template['id']) ?>">
    <label class="block text-sm font-medium">Pilih file ZIP (index.html + assets)</label>
    <input type="file" name="template_zip" accept=".zip" class="mt-2">
    <div class="text-xs text-slate-500 mt-2">Setelah upload, sistem akan mengekstrak ZIP ke folder <code>assets/templates_files/<?= htmlspecialchars($template['slug']) ?></code> dan file ZIP tidak disimpan.</div>

    <div class="mt-4">
      <button class="bg-indigo-600 text-white px-4 py-2 rounded">Upload & Extract</button>
      <a href="<?= site_url('templates') ?>" class="ml-2 px-4 py-2 border rounded">Kembali</a>
    </div>
  </form>

  <?php if (!empty($template['template_file'])): ?>
    <div class="mt-6">
      <h4 class="font-semibold">Folder saat ini</h4>
      <div class="mt-2 text-sm">
        <div>Folder: <?= htmlspecialchars($template['template_file']) ?></div>
        <div>Preview: <?php
          $url = null;
          $folder = FCPATH . $template['template_file'] . '/';
          if (file_exists($folder . 'index.html')) $url = base_url($template['template_file'] . '/index.html');
          else {
            $htmls = glob($folder . '*.html');
            if (!empty($htmls)) $url = base_url($template['template_file'] . '/' . basename($htmls[0]));
          }
          if ($url): ?>
            <a class="inline-block mt-2 px-3 py-1 border rounded" href="<?= $url ?>" target="_blank">Open Preview (new tab)</a>
          <?php else: ?>
            <div class="mt-2 text-slate-500">index.html tidak ditemukan di folder.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>
<?php $this->load->view('partials/admin_footer'); ?>
