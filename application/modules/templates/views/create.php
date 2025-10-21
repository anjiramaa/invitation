<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('partials/admin_header', isset($data) ? $data : []);
?>
<div class="max-w-3xl mx-auto bg-white p-6 card-shadow">
  <h3 class="font-semibold mb-3">Create Template</h3>
  <?php if ($this->session->flashdata('error')): ?><div class="text-red-600 mb-2"><?= $this->session->flashdata('error') ?></div><?php endif; ?>

  <form method="post" action="<?= site_url('templates/create') ?>">
    <label class="block text-sm font-medium">Template Name</label>
    <input type="text" name="template_name" class="w-full p-2 border rounded mt-1" required>

    <label class="block text-sm font-medium mt-3">Slug (optional)</label>
    <input type="text" name="slug" class="w-full p-2 border rounded mt-1" placeholder="bela-agung">

    <label class="block text-sm font-medium mt-3">Template Type</label>
    <select name="template_type" class="w-full p-2 border rounded mt-1">
      <option value="web">Web (landing)</option>
      <option value="pdf">PDF</option>
    </select>

    <label class="block text-sm font-medium mt-3">Placeholders (JSON array)</label>
    <textarea name="placeholders" rows="5" class="w-full p-2 border rounded mt-1" placeholder='[{"key":"event.title","label":"Event Title","sample":"..."}]'></textarea>

    <label class="block text-sm font-medium mt-3">Sample JSON (for preview)</label>
    <textarea name="sample_json" rows="6" class="w-full p-2 border rounded mt-1" placeholder='{"event":{"title":"My Event"},"guest":{"full_name":"John"}}'></textarea>

    <div class="mt-4">
      <button class="bg-indigo-600 text-white px-4 py-2 rounded">Create Metadata</button>
      <a href="<?= site_url('templates') ?>" class="ml-2 px-4 py-2 border rounded">Cancel</a>
    </div>
  </form>
</div>
<?php $this->load->view('partials/admin_footer'); ?>
