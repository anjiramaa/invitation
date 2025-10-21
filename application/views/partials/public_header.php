<?php
// application/views/partials/public_header.php
// Version: 2025-10-15_v1
// Public header for landing & auth pages
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= isset($title) ? htmlspecialchars($title).' - Invitation' : 'Invitation' ?></title>

  <!-- Tailwind CDN for rapid prototyping & modern UI -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>">
  <style>
    /* small inline fallback for header only if needed */
  </style>
</head>
<body class="bg-slate-50 text-slate-800">
  <header class="bg-white shadow-sm">
    <div class="max-w-6xl mx-auto px-4 py-6 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <a href="<?= site_url('') ?>" class="site-brand text-2xl text-slate-900">Invitation<span class="text-indigo-600">.</span></a>
        <nav class="hidden md:flex gap-4 text-sm text-slate-600">
          <a href="<?= site_url('') ?>" class="hover:text-slate-900">Home</a>
          <a href="<?= site_url('get_started') ?>" class="hover:text-slate-900">Get Started</a>
          <a href="<?= site_url('login') ?>" class="hover:text-slate-900">Login</a>
        </nav>
      </div>
      <div class="text-sm text-slate-500">
        <span class="hidden md:inline">Platform manajemen undangan & verifikasi kehadiran</span>
      </div>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-4 py-10">
