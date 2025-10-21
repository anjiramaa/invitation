<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Migration: 001_initial_schema_v100
 * VERSION : 1.0.0
 * DATE    : 2025-10-06
 * AUTHOR  : ChatGPT (draf untuk Febrianto Rama Anji)
 *
 * DESCRIPTION:
 *   Membuat seluruh tabel inti untuk sistem invitation & check-in.
 *   - Engine: InnoDB
 *   - Charset: utf8mb4
 *
 * NOTES:
 *   - Semua komentar menjelaskan fungsi tiap bagian.
 *   - Debugging / echo hanya aktif jika ENVIRONMENT === 'development'
 *   - Pastikan konfigurasi database di application/config/database.php sudah benar
 *
 * USAGE:
 *   - Letakkan file ini di application/migrations/001_initial_schema_v100.php
 *   - Jalankan migration (lihat README.md yang disediakan).
 */

class Migration_Initial_schema_v100 extends CI_Migration {

    /**
     * up
     * Buat semua tabel yang diperlukan. Gunakan transaksi untuk atomic operation.
     */
    public function up()
    {
        // Mulai transaction
        $this->db->trans_start();

        // 1) roles
        $sql = "
        CREATE TABLE IF NOT EXISTS `roles` (
          `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(50) NOT NULL COMMENT 'contoh: super_admin, client, staff',
          `description` TEXT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_roles_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daftar role pengguna';
        ";
        $this->db->query($sql);

        // 2) users
        $sql = "
        CREATE TABLE IF NOT EXISTS `users` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `role_id` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'FK ke roles (1=super_admin)',
          `username` VARCHAR(100) NOT NULL,
          `email` VARCHAR(255) NOT NULL,
          `password_hash` VARCHAR(255) NOT NULL COMMENT 'hasil password_hash()',
          `full_name` VARCHAR(255) NULL,
          `phone` VARCHAR(32) NULL,
          `is_active` TINYINT(1) NOT NULL DEFAULT 1,
          `last_login_at` TIMESTAMP NULL DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_users_email` (`email`),
          UNIQUE KEY `uq_users_username` (`username`),
          KEY `idx_users_role` (`role_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Akun pengguna sistem';
        ";
        $this->db->query($sql);

        // 3) events
        $sql = "
        CREATE TABLE IF NOT EXISTS `events` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `owner_user_id` INT UNSIGNED NOT NULL COMMENT 'user yang memiliki/men-create event',
          `slug` VARCHAR(150) NOT NULL COMMENT 'unik, dipakai di public URL',
          `title` VARCHAR(255) NOT NULL,
          `description` TEXT NULL,
          `venue_name` VARCHAR(255) NULL,
          `venue_address` TEXT NULL,
          `venue_lat` DECIMAL(10,7) NULL,
          `venue_lng` DECIMAL(10,7) NULL,
          `start_datetime` DATETIME NULL,
          `end_datetime` DATETIME NULL,
          `is_public` TINYINT(1) NOT NULL DEFAULT 1,
          `status` ENUM('draft','active','closed','cancelled') NOT NULL DEFAULT 'draft',
          `capacity` INT UNSIGNED NULL,
          `settings` JSON NULL COMMENT 'json untuk setting fleksibel (e.g., require_phone, qrcode_mode)',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_events_slug` (`slug`),
          KEY `idx_events_owner` (`owner_user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data event / workspace';
        ";
        $this->db->query($sql);

        // 4) event_custom_fields
        $sql = "
        CREATE TABLE IF NOT EXISTS `event_custom_fields` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `event_id` BIGINT UNSIGNED NOT NULL,
          `field_key` VARCHAR(100) NOT NULL COMMENT 'internal key, contoh: company_name',
          `label` VARCHAR(255) NOT NULL COMMENT 'label yang ditampilkan',
          `type` ENUM('text','textarea','select','checkbox','radio','date','number','file') NOT NULL DEFAULT 'text',
          `options` TEXT NULL COMMENT 'jika select/radio/checkbox: opsi dipisah JSON/CSV',
          `is_required` TINYINT(1) NOT NULL DEFAULT 0,
          `order_no` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_ecf_event` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Custom fields per event';
        ";
        $this->db->query($sql);

        // 5) guests (PERMINTAAN: TANPA kolom email)
        $sql = "
        CREATE TABLE IF NOT EXISTS `guests` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `event_id` BIGINT UNSIGNED NOT NULL,
          `guest_code` VARCHAR(100) NULL COMMENT 'unik per tamu (bisa dipakai utk QR id)',
          `name` VARCHAR(255) NOT NULL,
          `phone` VARCHAR(50) NULL,
          `type` VARCHAR(100) NULL COMMENT 'jenis tamu, e.g. VIP, Speaker, Guest',
          `status` ENUM('invited','notified','confirmed','attended','no_show','cancelled') NOT NULL DEFAULT 'invited',
          `note` TEXT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_guests_event` (`event_id`),
          KEY `idx_guests_guest_code` (`guest_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daftar tamu per event (email removed)';
        ";
        $this->db->query($sql);

        // 6) guest_custom_data
        $sql = "
        CREATE TABLE IF NOT EXISTS `guest_custom_data` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `guest_id` BIGINT UNSIGNED NOT NULL,
          `custom_field_id` BIGINT UNSIGNED NOT NULL,
          `value_text` TEXT NULL,
          `value_file` VARCHAR(512) NULL COMMENT 'jika field type=file -> path/nama file',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_gcd_guest` (`guest_id`),
          KEY `idx_gcd_customfield` (`custom_field_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nilai custom field per tamu';
        ";
        $this->db->query($sql);

        // 7) invitations
        $sql = "
        CREATE TABLE IF NOT EXISTS `invitations` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `event_id` BIGINT UNSIGNED NOT NULL,
          `guest_id` BIGINT UNSIGNED NULL,
          `channel` ENUM('link','pdf','whatsapp','email','sms','manual') NOT NULL DEFAULT 'link',
          `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `sent_by_user_id` INT UNSIGNED NULL,
          `status` ENUM('sent','failed','queued') NOT NULL DEFAULT 'sent',
          `payload` JSON NULL COMMENT 'raw payload / response dari API pengiriman jika ada',
          PRIMARY KEY (`id`),
          KEY `idx_invit_event` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log pengiriman undangan';
        ";
        $this->db->query($sql);

        // 8) checkins
        $sql = "
        CREATE TABLE IF NOT EXISTS `checkins` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `event_id` BIGINT UNSIGNED NOT NULL,
          `guest_id` BIGINT UNSIGNED NULL,
          `guest_code` VARCHAR(100) NULL COMMENT 'jika scan QR tanpa guest_id',
          `checked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `checked_by_user_id` INT UNSIGNED NULL COMMENT 'operator scanner',
          `device_info` VARCHAR(255) NULL,
          `note` TEXT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_checkins_event` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log check-in per tamu';
        ";
        $this->db->query($sql);

        // 9) transactions
        $sql = "
        CREATE TABLE IF NOT EXISTS `transactions` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `event_id` BIGINT UNSIGNED NOT NULL,
          `user_id` INT UNSIGNED NOT NULL COMMENT 'pembuat / pemesan',
          `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          `currency` VARCHAR(8) NOT NULL DEFAULT 'IDR',
          `method` VARCHAR(50) NULL COMMENT 'gateway / manual',
          `status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
          `payload` JSON NULL COMMENT 'response gateway / metadata',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_tx_event` (`event_id`),
          KEY `idx_tx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transaksi / pembayaran';
        ";
        $this->db->query($sql);

        // 10) event_files
        $sql = "
        CREATE TABLE IF NOT EXISTS `event_files` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `event_id` BIGINT UNSIGNED NOT NULL,
          `file_key` VARCHAR(150) NULL COMMENT 'contoh: banner, invitation_pdf',
          `original_name` VARCHAR(255) NULL,
          `path` VARCHAR(1024) NOT NULL COMMENT 'path relatif pada storage',
          `mime` VARCHAR(100) NULL,
          `size` BIGINT UNSIGNED NULL,
          `uploaded_by` INT UNSIGNED NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_ef_event` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='File upload per event';
        ";
        $this->db->query($sql);

        // 11) audit_logs
        $sql = "
        CREATE TABLE IF NOT EXISTS `audit_logs` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id` INT UNSIGNED NULL,
          `action` VARCHAR(255) NOT NULL,
          `resource_type` VARCHAR(100) NULL,
          `resource_id` VARCHAR(64) NULL,
          `meta` JSON NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_audit_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log audit aktivitas pengguna';
        ";
        $this->db->query($sql);

        // 12) ci_sessions (opsional, untuk session driver database)
        $sql = "
        CREATE TABLE IF NOT EXISTS `ci_sessions` (
          `id` varchar(128) NOT NULL,
          `ip_address` varchar(45) NOT NULL,
          `timestamp` int(10) unsigned DEFAULT 0 NOT NULL,
          `data` blob NOT NULL,
          PRIMARY KEY (`id`),
          KEY `ci_sessions_timestamp` (`timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Session table for CodeIgniter db driver';
        ";
        $this->db->query($sql);

        // 13) Foreign Key Constraints - ditambahkan setelah tabel dibuat supaya urutan tidak bermasalah
        // Note: MySQL memerlukan engine InnoDB yang sama untuk FK.
        // Tambahkan FK untuk users.role_id -> roles.id
        $this->db->query("ALTER TABLE `users` ADD CONSTRAINT `fk_users_roles` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT;");

        // events.owner_user_id -> users.id
        $this->db->query("ALTER TABLE `events` ADD CONSTRAINT `fk_events_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        // event_custom_fields.event_id -> events.id
        $this->db->query("ALTER TABLE `event_custom_fields` ADD CONSTRAINT `fk_ecf_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        // guests.event_id -> events.id
        $this->db->query("ALTER TABLE `guests` ADD CONSTRAINT `fk_guests_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        // guest_custom_data.guest_id -> guests.id
        $this->db->query("ALTER TABLE `guest_custom_data` ADD CONSTRAINT `fk_gcd_guest` FOREIGN KEY (`guest_id`) REFERENCES `guests`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        // guest_custom_data.custom_field_id -> event_custom_fields.id
        $this->db->query("ALTER TABLE `guest_custom_data` ADD CONSTRAINT `fk_gcd_customfield` FOREIGN KEY (`custom_field_id`) REFERENCES `event_custom_fields`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        // invitations.event_id -> events.id ; invitations.guest_id -> guests.id
        $this->db->query("ALTER TABLE `invitations` ADD CONSTRAINT `fk_invit_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        $this->db->query("ALTER TABLE `invitations` ADD CONSTRAINT `fk_invit_guest` FOREIGN KEY (`guest_id`) REFERENCES `guests`(`id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        // checkins.event_id -> events.id
        $this->db->query("ALTER TABLE `checkins` ADD CONSTRAINT `fk_checkins_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        // transactions.event_id -> events.id ; transactions.user_id -> users.id
        $this->db->query("ALTER TABLE `transactions` ADD CONSTRAINT `fk_tx_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        $this->db->query("ALTER TABLE `transactions` ADD CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT;");

        // event_files.event_id -> events.id
        $this->db->query("ALTER TABLE `event_files` ADD CONSTRAINT `fk_ef_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        // audit_logs.user_id -> users.id
        $this->db->query("ALTER TABLE `audit_logs` ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        // commit transaction
        $this->db->trans_complete();

        // debugging / human readable output hanya di environment development
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            if ($this->db->trans_status() === FALSE) {
                echo "Migration 001_initial_schema_v100: FAILED (transaction rolled back)\n";
            } else {
                echo "Migration 001_initial_schema_v100: OK (all tables created)\n";
            }
        }
    }

    /**
     * down
     * Drop semua tabel (reverse order untuk menghindari FK constraint error)
     */
    public function down()
    {
        $this->db->trans_start();

        // drop in reverse dependency order
        $this->db->query("DROP TABLE IF EXISTS `audit_logs`;");
        $this->db->query("DROP TABLE IF EXISTS `event_files`;");
        $this->db->query("DROP TABLE IF EXISTS `transactions`;");
        $this->db->query("DROP TABLE IF EXISTS `checkins`;");
        $this->db->query("DROP TABLE IF EXISTS `invitations`;");
        $this->db->query("DROP TABLE IF EXISTS `guest_custom_data`;");
        $this->db->query("DROP TABLE IF EXISTS `guests`;");
        $this->db->query("DROP TABLE IF EXISTS `event_custom_fields`;");
        $this->db->query("DROP TABLE IF EXISTS `events`;");
        $this->db->query("DROP TABLE IF EXISTS `users`;");
        $this->db->query("DROP TABLE IF EXISTS `roles`;");
        $this->db->query("DROP TABLE IF EXISTS `ci_sessions`;");

        $this->db->trans_complete();

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            if ($this->db->trans_status() === FALSE) {
                echo "Migration 001_initial_schema_v100: ROLLBACK FAILED\n";
            } else {
                echo "Migration 001_initial_schema_v100: ROLLBACK OK\n";
            }
        }
    }
}
