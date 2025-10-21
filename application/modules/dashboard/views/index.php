<?php
// application/modules/dashboard/views/index.php
// Version: 2025-10-15_v1
defined('BASEPATH') OR exit('No direct script access allowed');
// $data contains: title, user, summary
$this->load->view('partials/admin_header', isset($data) ? $data : []);
?>
  <div class="grid md:grid-cols-3 gap-6">
    <div class="bg-white p-6 card-shadow">
      <div class="text-sm text-slate-500">Events</div>
      <div class="kpi mt-2"><?= $summary['events_count'] ?? 0 ?></div>
    </div>
    <div class="bg-white p-6 card-shadow">
      <div class="text-sm text-slate-500">Guests registered today</div>
      <div class="kpi mt-2"><?= $summary['guests_today'] ?? 0 ?></div>
    </div>
    <div class="bg-white p-6 card-shadow">
      <div class="text-sm text-slate-500">Check-ins today</div>
      <div class="kpi mt-2"><?= $summary['checkins_today'] ?? 0 ?></div>
    </div>
  </div>

  <div class="mt-6 bg-white p-6 card-shadow">
    <h3 class="font-semibold">Quick Actions</h3>
    <div class="mt-3 flex gap-3">
      <a href="<?= site_url('events/create') ?>" class="px-3 py-2 bg-indigo-600 text-white rounded">Create Event</a>
      <a href="<?= site_url('guests/import') ?>" class="px-3 py-2 border rounded">Import Guests</a>
    </div>
  </div>

<?php $this->load->view('partials/admin_footer'); ?>
