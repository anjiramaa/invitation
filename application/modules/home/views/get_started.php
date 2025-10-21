<?php
// application/modules/home/views/get_started.php
// Version: 2025-10-16_v1
defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('partials/public_header');
?>
<div class="max-w-5xl mx-auto py-12">
  <div class="grid md:grid-cols-2 gap-8 items-center">
    <div>
      <h2 class="text-3xl font-bold">Mulai Mengelola Undanganmu Sekarang</h2>
      <p class="mt-4 text-slate-600">Daftar sekarang dan mulai buat halaman event interaktif, kirim undangan, dan verifikasi kehadiran dengan cepat. Cocok untuk pernikahan, seminar, atau acara perusahaan.</p>

      <ul class="mt-6 list-disc pl-5 text-slate-700 space-y-2">
        <li>Template undangan web & PDF</li>
        <li>Import guest list via Excel</li>
        <li>Multiple check-in methods: scanner / self check-in</li>
        <li>Addon: custom packages & payment integration</li>
      </ul>

      <div class="mt-6 flex gap-3">
        <a href="<?= site_url('register') ?>" class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Daftar Sekarang (Gratis)</a>
        <a href="mailto:sales@example.com" class="border px-4 py-2 rounded-lg">Hubungi Sales</a>
      </div>
    </div>

    <div>
      <div class="bg-white p-6 rounded-xl card-shadow">
        <h4 class="font-semibold">Quick Start</h4>
        <ol class="list-decimal pl-5 mt-3 text-slate-700 space-y-1">
          <li>Buat akun & verifikasi email (opsional)</li>
          <li>Buat event baru dan pilih template</li>
          <li>Import guest list & kirim undangan</li>
          <li>Gunakan scanner untuk check-in di hari H</li>
        </ol>
        <div class="mt-4 text-sm text-slate-500">Butuh bantuan? Hubungi tim kami untuk demo.</div>
      </div>
    </div>
  </div>
</div>
<?php $this->load->view('partials/public_footer'); ?>
