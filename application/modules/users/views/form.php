<?php
// application/modules/users/views/form.php
// Version: 2025-10-16_v1
defined('BASEPATH') OR exit('No direct script access allowed');

$this->load->view('partials/admin_header', isset($data) ? $data : []);
$editing = !empty($user);
$current_user = $this->session->userdata('user');

function val($arr, $key, $default = '') {
    if (isset($_POST[$key])) return htmlspecialchars($_POST[$key], ENT_QUOTES, 'UTF-8');
    if (!empty($arr) && isset($arr[$key])) return htmlspecialchars($arr[$key], ENT_QUOTES, 'UTF-8');
    return $default;
}
?>
<div class="bg-white p-6 card-shadow max-w-2xl">
  <h3><?= $editing ? 'Edit User' : 'Create User' ?></h3>
  <?php if(!empty($error)): ?><div class="text-red-600 mb-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="post" action="<?= $editing ? site_url('users/edit/'.$user['id']) : site_url('users/create') ?>">
    <label class="block text-sm font-medium mt-3">Full name</label>
    <input type="text" name="full_name" class="w-full mt-2 p-3 border rounded" value="<?= val($user,'full_name') ?>" required>

    <label class="block text-sm font-medium mt-3">Username</label>
    <input type="text" name="username" class="w-full mt-2 p-3 border rounded" value="<?= val($user,'username') ?>" required>

    <label class="block text-sm font-medium mt-3">Email</label>
    <input type="email" name="email" class="w-full mt-2 p-3 border rounded" value="<?= val($user,'email') ?>" required>

    <label class="block text-sm font-medium mt-3">Password (kosongkan jika tidak ingin mengganti)</label>
    <input type="password" name="password" class="w-full mt-2 p-3 border rounded">

    <label class="block text-sm font-medium mt-3">Role</label>
    <select id="role_key" name="role_key" class="w-full mt-2 p-3 border rounded" required>
      <?php
        $roles = ['super_admin'=>'Super Admin','admin'=>'Admin','client'=>'Client','staff'=>'Staff'];
        $current_role = val($user,'role_key');
        foreach($roles as $k=>$v) {
          $sel = ($editing && $current_role === $k) ? 'selected' : '';
          // If current actor is admin, disallow admin creation; admin cannot set role 'admin' on creation (controller/model enforce)
          echo "<option value=\"{$k}\" {$sel}>{$v}</option>";
        }
      ?>
    </select>

    <?php if (!empty($current_user) && $current_user['role_key'] === 'super_admin'): ?>
      <label class="block text-sm font-medium mt-3">Parent Admin (optional)</label>
      <select name="parent_admin_id" class="w-full mt-2 p-3 border rounded">
        <option value=""><?= $editing ? '(no change / no parent)' : '-- No parent --' ?></option>
        <?php if (!empty($parents) && is_array($parents)): foreach($parents as $pid => $plabel): 
            $selected = '';
            if ($editing && isset($user['parent_admin_id']) && (string)$user['parent_admin_id'] === (string)$pid) $selected = 'selected';
        ?>
          <option value="<?= (int)$pid ?>" <?= $selected ?>><?= htmlspecialchars($plabel) ?></option>
        <?php endforeach; endif; ?>
      </select>
      <p class="text-xs text-slate-500 mt-1">Pilih admin/superadmin yang menjadi parent. Untuk admin, parent sebaiknya menunjuk ke superadmin.</p>
    <?php elseif (!empty($current_user) && $current_user['role_key'] === 'admin'): ?>
      <p class="text-sm text-slate-500 mt-3">Parent admin akan otomatis diset ke Anda (admin).</p>
    <?php endif; ?>

    <div class="mt-4">
      <button class="bg-indigo-600 text-white px-4 py-2 rounded"><?= $editing ? 'Update' : 'Create' ?></button>
      <a href="<?= site_url('users') ?>" class="ml-3 text-sm text-slate-600">Cancel</a>
    </div>
  </form>
</div>

<?php $this->load->view('partials/admin_footer'); ?>
