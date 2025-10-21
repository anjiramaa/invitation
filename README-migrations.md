# README: Menjalankan Migrations (Development)
VERSION: 1.0.0
DATE: 2025-10-06
AUTHOR: ChatGPT

Deskripsi:
  File migration siap: 
    - application/migrations/001_initial_schema_v100.php
    - application/migrations/002_seed_data_v100.php
  Controller opsional untuk menjalankan migration:
    - application/controllers/tools/Migrate.php

Langkah konfigurasi (development):

1) Set ENVIRONMENT ke 'development'
   Buka file `index.php` (root project CodeIgniter)
   Cari baris yang mendefinisikan ENVIRONMENT dan ubah menjadi:
     define('ENVIRONMENT', 'development');
   *Catatan:* Pastikan ini **hanya** pada mesin development.

2) Konfigurasi database
   Buka `application/config/database.php` dan atur parameter koneksi DB
   contoh minimal:
     $db['default'] = array(
       'dsn'   => '',
       'hostname' => '127.0.0.1',
       'username' => 'db_user',
       'password' => 'db_pass',
       'database' => 'invitation_system',
       'dbdriver' => 'mysqli',
       'dbprefix' => '',
       'pconnect' => FALSE,
       'db_debug' => (ENVIRONMENT !== 'production'),
       'cache_on' => FALSE,
       'char_set' => 'utf8mb4',
       'dbcollat' => 'utf8mb4_unicode_ci',
       ...
     );

3) Aktifkan migration library
   Buka `application/config/migration.php` dan set:
     $config['migration_enabled'] = TRUE;
     $config['migration_type'] = 'sequential'; // memakai nomor urut seperti 001,002
     $config['migration_table'] = 'migrations';
     $config['migration_auto_latest'] = FALSE; // kita jalankan manual

4) (Opsional) Pastikan folder migrations dapat ditulis oleh web server jika diperlukan.

Menjalankan migrations (via CLI) [Direkomendasikan]:
  - Dari root project, jalankan:
      php index.php tools/migrate/run
    Output akan menampilkan status (debugging hanya muncul jika ENVIRONMENT === 'development').

Menjalankan migrations (via browser) [HANYA untuk development]:
  - Akses:
      https://your-host/tools/migrate/run
    (Controller `tools/Migrate` harus ada; jika tidak ada, buatlah atau jalankan via CLI)

Rollback / Down:
  - Untuk rollback migration tertentu, Anda dapat panggil:
      php index.php tools/migrate/version/0
    atau
      php index.php tools/migrate/version/1
    (nomor versi sesuai urutan file migration. Hati-hati saat melakukan rollback.)

Seed:
  - Seed dibuat sebagai migration kedua (002_seed_data_v100). Jadi saat menjalankan
    migrations ke latest, seed akan otomatis diterapkan.

Catatan keamanan:
  - Controller tools/migrate hanya boleh tersedia di environment development. Jangan biarkan controller ini aktif di production.
  - Password admin seed: 'Admin123!' — segera ubah setelah login pertama.
  - Backup database sebelum menjalankan migration pada environment non-dev.

Troubleshooting:
  - Jika migration gagal pada FK, pastikan semua tabel menggunakan Engine=InnoDB dan urutan CREATE TABLE benar.
  - Jika database tidak mendukung tipe JSON (MySQL < 5.7), ubah kolom `settings` dan `payload` menjadi TEXT di migration file (ganti "JSON" menjadi "TEXT").

Jika Anda ingin, saya bisa:
  - Mengubah tipe kolom JSON -> TEXT (jika DB Anda MySQL < 5.7)
  - Membuat skrip rollback terkontrol
  - Men-generate migration tambahan (index/performance, fulltext)
