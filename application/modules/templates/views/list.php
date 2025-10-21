<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('partials/admin_header', isset($data) ? $data : []);
?>
<div class="bg-white p-6 card-shadow max-w-6xl mx-auto">
  <div class="flex justify-between items-center mb-4">
    <h3 class="text-xl font-semibold">Templates</h3>
    <a href="<?= site_url('templates/create') ?>" class="px-3 py-2 bg-indigo-600 text-white rounded">+ New Template</a>
  </div>

  <?php if ($this->session->flashdata('success')): ?>
    <div class="text-green-600 mb-3"><?= $this->session->flashdata('success') ?></div>
  <?php endif; ?>
  <?php if ($this->session->flashdata('error')): ?>
    <div class="text-red-600 mb-3"><?= $this->session->flashdata('error') ?></div>
  <?php endif; ?>

  <table class="w-full table-auto border-collapse">
    <thead>
      <tr class="text-left">
        <th class="p-2">#</th>
        <th class="p-2">Thumbnail</th>
        <th class="p-2">Name</th>
        <th class="p-2">Type</th>
        <th class="p-2">Slug</th>
        <th class="p-2">Owner</th>
        <th class="p-2">Version</th>
        <th class="p-2">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($templates)): $i=1; foreach($templates as $t): ?>
        <tr class="border-t">
          <td class="p-2 align-top"><?= $i++ ?></td>
          <td class="p-2 align-top">
            <?php if (!empty($t['thumbnail_image']) && file_exists(FCPATH . $t['thumbnail_image'])): ?>
              <img src="<?= base_url($t['thumbnail_image']) ?>" alt="thumb" style="width:96px;height:64px;object-fit:cover;border-radius:6px">
            <?php else: ?>
              <div style="width:96px;height:64px;border-radius:6px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#9ca3af">No Image</div>
            <?php endif; ?>
          </td>
          <td class="p-2 align-top"><?= htmlspecialchars($t['template_name']) ?></td>
          <td class="p-2 align-top"><?= htmlspecialchars($t['template_type']) ?></td>
          <td class="p-2 align-top"><?= htmlspecialchars($t['slug']) ?></td>
          <td class="p-2 align-top"><?= !empty($t['created_by']) ? 'User#'.$t['created_by'] : 'System' ?></td>
          <td class="p-2 align-top"><?= htmlspecialchars($t['version']) ?></td>
          <td class="p-2 align-top">
            <?php if (!empty($t['template_file'])): ?>
              <a href="<?= site_url('templates/preview/'.$t['id']) ?>" class="inline-block px-3 py-1 border rounded mr-2">Preview</a>
            <?php else: ?>
              <a href="<?= site_url('templates/upload_zip/'.$t['id']) ?>" class="inline-block px-3 py-1 border rounded mr-2">Upload ZIP</a>
            <?php endif; ?>

            <?php if ($this->templates_model->can_edit($t, $current_user)): ?>
              <a href="<?= site_url('templates/edit/'.$t['id']) ?>" class="inline-block px-3 py-1 border rounded mr-2">Edit</a>
              <a href="<?= site_url('templates/delete/'.$t['id']) ?>" onclick="return confirm('Hapus template ini?')" class="inline-block px-3 py-1 border rounded text-red-600">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="8" class="p-4 text-slate-600">No templates found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php $this->load->view('partials/admin_footer'); ?>
