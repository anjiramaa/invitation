<?php
// application/modules/auth/views/login.php
// Version: 2025-10-17_v1
defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('partials/public_header');
?>
<div class="max-w-md mx-auto bg-white p-8 rounded-xl card-shadow">
  <h2 class="text-2xl font-semibold mb-2">Login</h2>

  <?php if(!empty($error)): ?><div class="text-red-600 mb-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form id="login-form" method="post" action="<?= site_url('auth/login') ?>">
    <label class="block text-sm font-medium">Username atau Email</label>
    <input name="identity" id="identity" class="w-full mt-2 p-3 border rounded-lg" required>

    <label class="block text-sm font-medium mt-4">Password</label>
    <input name="password" id="password" type="password" class="w-full mt-2 p-3 border rounded-lg" required>

    <?php $cu = $this->session->userdata('user'); ?>
    <?php if (empty($cu)): ?>
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

    <button id="btn-login" class="mt-6 bg-indigo-600 text-white px-4 py-2 rounded-lg" type="submit">Masuk</button>
  </form>

  <div class="mt-4 text-sm text-slate-500">Belum Punya Akun? <a href="<?= site_url('auth/register') ?>" class="text-indigo-600">Daftar</a></div>
</div>

<script>
/**
 * Client-side captcha rendering using server-provided code.
 * Pattern inspired by generateCaptcha() in your antrian.php.
 */
document.addEventListener('DOMContentLoaded', function () {
  const canvas = document.getElementById('captcha-canvas');
  const ctx = canvas ? canvas.getContext('2d') : null;
  const reloadBtn = document.getElementById('reload-captcha');

  // draw function that accepts the code string
  function drawCaptchaOnCanvas(code) {
    if (!ctx) return;
    // clear
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    // background
    ctx.fillStyle = '#f9fafb';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    // text
    ctx.font = '24px Arial';
    ctx.fillStyle = '#374151';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(code, canvas.width / 2, canvas.height / 2);

    // noise: lines
    for (let i = 0; i < 8; i++) {
      ctx.strokeStyle = '#d1d5db';
      ctx.beginPath();
      ctx.moveTo(Math.random() * canvas.width, Math.random() * canvas.height);
      ctx.lineTo(Math.random() * canvas.width, Math.random() * canvas.height);
      ctx.stroke();
    }
    // dots
    for (let i = 0; i < 40; i++) {
      ctx.fillStyle = '#c7d2fe';
      ctx.fillRect(Math.random() * canvas.width, Math.random() * canvas.height, 1, 1);
    }
  }

  // request captcha code from server and draw it
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
      // fallback: draw random client-side (but server-side will not accept it)
      const fallback = Math.random().toString(36).substring(2,7).toUpperCase();
      drawCaptchaOnCanvas(fallback);
    }
  }

  if (canvas) {
    canvas.addEventListener('click', fetchAndDrawCaptcha);
  }
  if (reloadBtn) reloadBtn.addEventListener('click', fetchAndDrawCaptcha);

  // initialize
  if (canvas) fetchAndDrawCaptcha();

  // minimal client validation
  const form = document.getElementById('login-form');
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
