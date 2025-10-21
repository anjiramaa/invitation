<?php
// application/modules/auth/views/register.php
// Version: 2025-10-17_v1
defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('partials/public_header');
$current_user = $this->session->userdata('user');
?>
<div class="max-w-md mx-auto bg-white p-8 rounded-xl card-shadow">
  <h2 class="text-2xl font-semibold mb-2"><?= isset($current_user) && !empty($current_user) ? 'Create New User' : 'Register (Client)' ?></h2>

  <?php if (!empty($error)): ?><div class="text-red-600 mb-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form id="register-form" method="post" action="<?= site_url('auth/register') ?>">
    <label class="block text-sm font-medium mt-2">Full Name</label>
    <input id="full_name" type="text" name="full_name" class="w-full mt-2 p-3 border rounded-lg" required value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">

    <label class="block text-sm font-medium mt-2">Username</label>
    <input id="username" type="text" name="username" class="w-full mt-2 p-3 border rounded-lg" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">

    <label class="block text-sm font-medium mt-2">Email</label>
    <input id="email" type="email" name="email" class="w-full mt-2 p-3 border rounded-lg" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">

    <label class="block text-sm font-medium mt-2">Password</label>
    <input id="password" type="password" name="password" class="w-full mt-2 p-3 border rounded-lg" required>

    <!-- Context aware fields for admin/super_admin -->
    <?php if (!empty($current_user) && $current_user['role_key'] === 'super_admin'): ?>
      <label class="block text-sm font-medium mt-2">Role</label>
      <select name="role_key" class="w-full mt-2 p-3 border rounded-lg">
        <option value="super_admin">Super Admin</option>
        <option value="admin">Admin</option>
        <option value="client" selected>Client</option>
        <option value="staff">Staff</option>
      </select>

      <label class="block text-sm font-medium mt-2">Parent Admin (optional)</label>
      <select name="parent_admin_id" class="w-full mt-2 p-3 border rounded-lg">
        <option value="">-- No parent admin --</option>
        <?php if (!empty($admins) && is_array($admins)): foreach($admins as $aid => $aname): ?>
          <option value="<?= (int)$aid ?>"><?= htmlspecialchars($aname) ?></option>
        <?php endforeach; endif; ?>
      </select>

    <?php elseif (!empty($current_user) && $current_user['role_key'] === 'admin'): ?>
      <label class="block text-sm font-medium mt-2">Role</label>
      <select name="role_key" class="w-full mt-2 p-3 border rounded-lg">
        <option value="client">Client</option>
        <option value="staff">Staff</option>
      </select>
      <div class="text-xs text-slate-500 mt-1">Parent admin akan otomatis diset ke akun Anda.</div>

    <?php else: ?>
      <input type="hidden" name="role_key" value="client">
      <!-- PUBLIC captcha -->
      <div class="mt-4">
        <label class="block text-sm font-medium">CAPTCHA</label>
        <div class="flex items-center gap-3 mt-2">
          <canvas id="captcha-canvas" width="150" height="44" title="Klik untuk ganti gambar" style="border:1px solid #E5E7EB;border-radius:6px;cursor:pointer;"></canvas>
          <button id="reload-captcha" type="button" class="px-3 py-2 border rounded text-sm">Refresh</button>
        </div>
        <input id="captcha" name="captcha" type="text" class="w-full mt-2 p-2 border rounded-lg" placeholder="Masukkan kode di kiri" autocomplete="off" required>
        <div class="text-xs text-slate-500 mt-1">Klik gambar untuk memuat ulang.</div>
      </div>
    <?php endif; ?>

    <div class="mt-6">
      <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg" type="submit"><?= isset($current_user) ? 'Create' : 'Register' ?></button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const canvas = document.getElementById('captcha-canvas');
  const ctx = canvas ? canvas.getContext('2d') : null;
  const reloadBtn = document.getElementById('reload-captcha');

  function drawCaptchaOnCanvas(code) {
    if (!ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#f9fafb';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.font = '24px Arial';
    ctx.fillStyle = '#374151';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(code, canvas.width / 2, canvas.height / 2);

    for (let i = 0; i < 8; i++) {
      ctx.strokeStyle = '#d1d5db';
      ctx.beginPath();
      ctx.moveTo(Math.random() * canvas.width, Math.random() * canvas.height);
      ctx.lineTo(Math.random() * canvas.width, Math.random() * canvas.height);
      ctx.stroke();
    }
    for (let i = 0; i < 40; i++) {
      ctx.fillStyle = '#c7d2fe';
      ctx.fillRect(Math.random() * canvas.width, Math.random() * canvas.height, 1, 1);
    }
  }

  async function fetchAndDrawCaptcha() {
    try {
      const res = await fetch('<?= site_url('auth/captcha_json') ?>', {cache: 'no-store'});
      if (!res.ok) throw new Error('Gagal memuat CAPTCHA');
      const data = await res.json();
      if (data && data.captcha) {
        drawCaptchaOnCanvas(data.captcha);
      } else {
        throw new Error('Response captcha invalid');
      }
    } catch (err) {
      console.error(err);
      const fallback = Math.random().toString(36).substring(2,7).toUpperCase();
      drawCaptchaOnCanvas(fallback);
    }
  }

  if (canvas) {
    canvas.addEventListener('click', fetchAndDrawCaptcha);
    fetchAndDrawCaptcha();
  }
  if (reloadBtn) reloadBtn.addEventListener('click', fetchAndDrawCaptcha);

  // client validation
  const form = document.getElementById('register-form');
  form.addEventListener('submit', function (e) {
    const captchaEl = document.getElementById('captcha');
    if (captchaEl) {
      if (captchaEl.value.trim().length === 0) {
        e.preventDefault();
        alert('Silakan isi CAPTCHA.');
        return;
      }
    }
  });
});
</script>

<?php $this->load->view('partials/public_footer'); ?>
