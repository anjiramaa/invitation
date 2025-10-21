<?php
// application/views/partials/admin_header.php
// Version: 2025-10-15_v1
defined('BASEPATH') OR exit('No direct script access allowed');
$CI =& get_instance();
$user = $CI->session->userdata('user') ?: [];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= isset($title) ? htmlspecialchars($title).' - Dashboard' : 'Dashboard' ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>">
</head>
<body class="bg-slate-50 text-slate-800">
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <?php $this->load->view('partials/admin_sidebar'); ?>

    <!-- Main content -->
    <div class="flex-1">
      <header class="bg-white shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <button id="mobile-sidebar-toggle" class="md:hidden p-2 rounded bg-slate-100">☰</button>
            <h1 class="text-lg font-semibold"><?= isset($title) ? htmlspecialchars($title) : 'Dashboard' ?></h1>
          </div>
          <div class="flex items-center gap-4">
            <div class="text-sm text-slate-600 hidden md:block">Halo, <?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User') ?></div>
            <a href="<?= site_url('profile') ?>" class="px-3 py-1 rounded hover:bg-slate-100">Profile</a>
            <a href="<?= site_url('logout') ?>" class="px-3 py-1 rounded hover:bg-red-50 text-red-600">Logout</a>
          </div>
        </div>
      </header>

      <div class="max-w-6xl mx-auto px-4 py-6">
