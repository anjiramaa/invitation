<?php
// application/modules/profile/views/change_password.php
// Version: 2025-10-15_v2
// Change password form (admin layout)
// - Posts to profile/change-password
// - Minimal inline JS (wrapped in DOMContentLoaded)
// - Shows error messages if supplied in $error variable or flashdata
defined('BASEPATH') OR exit('No direct script access allowed');

$this->load->view('partials/admin_header', isset($data) ? $data : []);
$user = isset($user) ? $user : $this->session->userdata('user');
?>
<div class="bg-white p-6 card-shadow max-w-md">
  <h2 class="text-lg font-semibold">Change Password</h2>
  <p class="text-sm text-slate-500">Ubah kata sandi akun Anda. Session Anda tetap aktif setelah perubahan (opsional: bisa dipaksa logout jika diinginkan).</p>

  <?php if (!empty($error)): ?>
    <div class="mt-4 p-3 rounded bg-red-50 text-red-700"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($this->session->flashdata('success')): ?>
    <div class="mt-4 p-3 rounded bg-green-50 text-green-700"><?= htmlspecialchars($this->session->flashdata('success')) ?></div>
  <?php endif; ?>

  <form id="change-pass-form" method="post" action="<?= site_url('profile/change-password') ?>" class="mt-4">
    <label class="block text-sm font-medium">Current Password</label>
    <input id="old_password" name="old_password" type="password" class="w-full mt-2 p-3 border rounded" required>

    <label class="block text-sm font-medium mt-3">New Password</label>
    <input id="new_password" name="new_password" type="password" class="w-full mt-2 p-3 border rounded" required>

    <label class="block text-sm font-medium mt-3">Confirm New Password</label>
    <input id="confirm_password" name="confirm_password" type="password" class="w-full mt-2 p-3 border rounded" required>

    <div id="form-error" class="text-red-600 text-sm mt-3" style="display:none;"></div>

    <div class="mt-4 flex items-center gap-3">
      <button id="btn-save" type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Save changes</button>
      <a href="<?= site_url('profile') ?>" class="text-sm text-slate-600">Cancel</a>
    </div>
  </form>

  <div class="mt-3 text-xs text-slate-500">
    Saran keamanan: gunakan password unik dan aktifkan 2FA jika tersedia.
  </div>

  <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
    <div class="mt-6 bg-slate-50 p-3 rounded text-sm">
      <strong>Debug:</strong>
      <pre>Current user: <?= print_r($user, true) ?></pre>
    </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Client-side pre-checks to improve UX; server still authoritative.
  var form = document.getElementById('change-pass-form');
  var err = document.getElementById('form-error');
  form.addEventListener('submit', function (e) {
    err.style.display = 'none';
    err.textContent = '';

    var oldp = document.getElementById('old_password').value.trim();
    var newp = document.getElementById('new_password').value.trim();
    var conf = document.getElementById('confirm_password').value.trim();

    if (!oldp || !newp || !conf) {
      e.preventDefault();
      err.style.display = 'block';
      err.textContent = 'Semua field wajib diisi.';
      return;
    }
    if (newp.length < 8) {
      e.preventDefault();
      err.style.display = 'block';
      err.textContent = 'Password baru harus minimal 8 karakter.';
      return;
    }
    if (newp !== conf) {
      e.preventDefault();
      err.style.display = 'block';
      err.textContent = 'Password baru dan konfirmasi tidak cocok.';
      return;
    }
    // optionally show a small processing state
    var btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.textContent = 'Saving...';
  });
});
</script>

<?php $this->load->view('partials/admin_footer'); ?>
