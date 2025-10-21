<?php
// application/views/partials/admin_sidebar.php
// Version: 2025-10-15_v1
defined('BASEPATH') OR exit('No direct script access allowed');
$CI =& get_instance();
$uri1 = $CI->uri->segment(1);
$uri2 = $CI->uri->segment(2);
$user = $CI->session->userdata('user') ?: [];

/**
 * helper to return active class
 */
function nav_active($key) {
    $CI =& get_instance();
    $seg = $CI->uri->segment(1);
    return ($seg === $key) ? 'active' : '';
}
?>
<aside id="sidebar" class="sidebar bg-white w-64 border-r hidden md:block">
  <div class="p-4">
    <a href="<?= site_url('dashboard') ?>" class="site-brand text-xl block mb-4">Invitation<span class="text-indigo-600">.</span></a>

    <nav class="space-y-1 text-sm">
      <a href="<?= site_url('dashboard') ?>" class="nav-item block px-3 py-2 rounded <?= nav_active('dashboard') ?>">
        Dashboard
      </a>
      <?php if (in_array($user['role_key'] ?? '', ['super_admin','admin'])): ?>
        <a href="<?= site_url('users') ?>" class="nav-item block px-3 py-2 rounded <?= nav_active('users') ?>">Users</a>
      <?php endif; ?>
      <?php if (in_array($user['role_key'] ?? '', ['super_admin'])): ?>
        <a href="<?= site_url('templates') ?>" class="nav-item block px-3 py-2 rounded <?= nav_active('profile') ?>">Template</a>
        <a href="<?= site_url('addons') ?>" class="nav-item block px-3 py-2 rounded <?= nav_active('profile') ?>">Addons</a>
      <?php endif; ?>

      <!-- placeholder links for modules we'll implement -->
      <a href="<?= site_url('events') ?>" class="nav-item block px-3 py-2 rounded <?= nav_active('events') ?>">Events</a>
      <a href="<?= site_url('guests') ?>" class="nav-item block px-3 py-2 rounded <?= nav_active('guests') ?>">Guests</a>
      <a href="<?= site_url('invitations') ?>" class="nav-item block px-3 py-2 rounded <?= nav_active('invitations') ?>">Invitations</a>
      <a href="<?= site_url('transactions') ?>" class="nav-item block px-3 py-2 rounded <?= nav_active('transactions') ?>">Transactions</a>
    </nav>

    <div class="mt-6 text-xs text-slate-400">Version: 2025-10-15_v1</div>
  </div>
</aside>
