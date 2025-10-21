<?php
// application/modules/profile/views/index.php
// Version: 2025-10-15_v2
// Profile page (admin layout) - shows user info and actions (Change Password)
// - Uses partials/admin_header and partials/admin_footer
// - Debug info only shown in development
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Expected variables:
 *  - $title (optional)
 *  - $user  (array) - current user session data (fallback to session if not provided)
 */

$this->load->view('partials/admin_header', isset($data) ? $data : []);
$user = isset($user) ? $user : $this->session->userdata('user');
?>
<div class="bg-white p-6 card-shadow">
  <div class="flex items-center justify-between">
    <div>
      <h2 class="text-xl font-semibold">Profile Settings</h2>
      <p class="text-sm text-slate-500 mt-1">Atur informasi akun dan keamanan Anda</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="<?= site_url('profile/change-password') ?>" class="px-3 py-2 bg-indigo-600 text-white rounded">Change Password</a>
      <a href="<?= site_url('auth/logout') ?>" class="px-3 py-2 border rounded text-slate-700">Logout</a>
    </div>
  </div>

  <?php if ($this->session->flashdata('success')): ?>
    <div class="mt-4 p-3 rounded bg-green-50 text-green-700"><?= htmlspecialchars($this->session->flashdata('success')) ?></div>
  <?php endif; ?>
  <?php if ($this->session->flashdata('error')): ?>
    <div class="mt-4 p-3 rounded bg-red-50 text-red-700"><?= htmlspecialchars($this->session->flashdata('error')) ?></div>
  <?php endif; ?>

  <div class="mt-6 grid md:grid-cols-2 gap-6">
    <div class="bg-white border rounded p-4">
      <h3 class="font-semibold mb-3">Account Details</h3>
      <table class="w-full text-sm">
        <tr><td class="text-slate-600 font-medium w-36 py-2">Username</td><td class="py-2"><?= htmlspecialchars($user['username'] ?? '-') ?></td></tr>
        <tr><td class="text-slate-600 font-medium w-36 py-2">Full name</td><td class="py-2"><?= htmlspecialchars($user['full_name'] ?? '-') ?></td></tr>
        <tr><td class="text-slate-600 font-medium w-36 py-2">Email</td><td class="py-2"><?= htmlspecialchars($user['email'] ?? '-') ?></td></tr>
        <tr><td class="text-slate-600 font-medium w-36 py-2">Role</td><td class="py-2"><?= htmlspecialchars($user['role_key'] ?? '-') ?></td></tr>
        <tr><td class="text-slate-600 font-medium w-36 py-2">Last login</td><td class="py-2"><?= !empty($user['last_login']) ? htmlspecialchars($user['last_login']) : '-' ?></td></tr>
      </table>
    </div>

    <div class="bg-white border rounded p-4">
      <h3 class="font-semibold mb-3">Security</h3>
      <p class="text-sm text-slate-600">Untuk mengganti kata sandi Anda, klik tombol <strong>Change Password</strong>. Kami menyarankan menggunakan password minimal 8 karakter dengan kombinasi huruf, angka, dan simbol.</p>

      <div class="mt-4">
        <a href="<?= site_url('profile/change-password') ?>" class="px-3 py-2 bg-indigo-600 text-white rounded">Change Password</a>
      </div>
    </div>
  </div>

  <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
    <div class="mt-6 bg-slate-50 p-3 rounded text-sm">
      <strong>Debug (development):</strong>
      <pre><?= print_r($user, true) ?></pre>
    </div>
  <?php endif; ?>
</div>

<?php $this->load->view('partials/admin_footer'); ?>
