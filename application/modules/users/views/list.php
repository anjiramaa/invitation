<?php
// application/modules/users/views/list.php
// Version: 2025-10-15_v1
defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('partials/admin_header', isset($data) ? $data : []);
?>
<div class="bg-white p-4 card-shadow">
  <?php if($this->session->flashdata('success')): ?>
    <div class="text-green-600 mb-2"><?= $this->session->flashdata('success') ?></div>
  <?php endif; ?>

  <div class="flex justify-between items-center mb-4">
    <h3 class="font-semibold">Users</h3>
    <a href="<?= site_url('users/create') ?>" class="px-3 py-2 bg-indigo-600 text-white rounded">+ New User</a>
  </div>

  <table class="w-full table-modern">
    <thead><tr>
      <th>#</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Parent Admin</th><th>Actions</th>
    </tr></thead>
    <tbody>
      <?php if(!empty($users)): $i=1; foreach($users as $u): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['full_name']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars($u['role_key']) ?></td>
          <td><?= htmlspecialchars($u['parent_admin_id']) ?></td>
          <td>
            <a href="<?= site_url('users/edit/'.$u['id']) ?>" class="text-indigo-600">Edit</a>
            <?php if($current_user['id'] != $u['id']): ?>
              <a href="<?= site_url('users/delete/'.$u['id']) ?>" data-confirm="Hapus user ini?" class="text-red-600 ml-2">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="7">Tidak ada data.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php $this->load->view('partials/admin_footer'); ?>
