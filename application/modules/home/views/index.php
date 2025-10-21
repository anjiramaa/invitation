<?php
// application/modules/home/views/index.php
// Version: 2025-10-15_v1
defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('partials/public_header', isset($data) ? $data : []);
?>
  <section class="bg-gradient-to-b from-indigo-600 to-sky-500 text-white rounded-xl p-8 mb-6 card-shadow">
    <div class="md:flex md:items-center md:justify-between">
      <div>
        <h2 class="text-3xl font-bold">Platform Manajemen Undangan & Verifikasi Kehadiran</h2>
        <p class="mt-3 text-indigo-100 max-w-2xl">Buat undangan digital, kirim via WhatsApp/email, dan verifikasi kehadiran dengan scanner atau self check-in. Skala dari event kecil hingga besar.</p>
        <div class="mt-6">
          <a href="<?= site_url('register') ?>" class="inline-block bg-white text-indigo-700 px-4 py-2 rounded-lg font-semibold shadow">Mulai Sekarang</a>
          <a href="<?= site_url('login') ?>" class="ml-3 inline-block border border-white text-white px-4 py-2 rounded-lg">Login</a>
        </div>
      </div>
      <div class="mt-6 md:mt-0">
        <!-- Illustration placeholder -->
        <div class="w-64 h-40 bg-white/20 rounded-lg flex items-center justify-center">
          <svg width="120" height="80" viewBox="0 0 120 80" fill="none" xmlns="http://www.w3.org/2000/svg" class="opacity-80">
            <rect width="120" height="80" rx="12" fill="white" />
          </svg>
        </div>
      </div>
    </div>
  </section>

  <section class="grid md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white p-6 card-shadow">
      <h3 class="font-semibold">Manage Events</h3>
      <p class="mt-2 text-sm text-slate-600">Buat halaman event, import guests, dan atur template undangan.</p>
    </div>
    <div class="bg-white p-6 card-shadow">
      <h3 class="font-semibold">Invitation & RSVP</h3>
      <p class="mt-2 text-sm text-slate-600">Kirim undangan via WhatsApp/email dan pantau RSVP secara real-time.</p>
    </div>
    <div class="bg-white p-6 card-shadow">
      <h3 class="font-semibold">Check-in & Analytics</h3>
      <p class="mt-2 text-sm text-slate-600">Scanner cepat, self-checkin, dan laporan hadir yang mudah diexport.</p>
    </div>
  </section>

  <section class="bg-white p-6 rounded-xl card-shadow">
    <h3 class="font-semibold mb-2">Kenapa memilih kami?</h3>
    <ul class="list-disc pl-6 text-sm text-slate-700">
      <li>Antarmuka modern & mudah digunakan</li>
      <li>Skalabilitas untuk event besar</li>
      <li>Dukungan template undangan PDF & web</li>
    </ul>
  </section>

<?php $this->load->view('partials/public_footer'); ?>
