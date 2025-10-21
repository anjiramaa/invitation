<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Migration: 002_seed_data_v100
 * VERSION : 1.0.0
 * DATE    : 2025-10-06
 * AUTHOR  : ChatGPT (seed data)
 *
 * DESCRIPTION:
 *   Seed data minimal:
 *     - roles (super_admin, client, staff)
 *     - admin user (role: super_admin)
 *     - contoh event (owned by admin)
 *     - contoh guest (without email, per permintaan)
 *
 * NOTES:
 *   - Password default untuk seed: 'Admin123!' (hanya contoh)
 *   - Password disimpan sebagai hash menggunakan password_hash()
 *   - Setelah deploy, segera ubah password default admin
 */

class Migration_Seed_data_v100 extends CI_Migration {

    public function up()
    {
        // Transaksi untuk konsistensi
        $this->db->trans_start();

        // 1) Insert roles
        $roles = [
            ['id' => 1, 'name' => 'super_admin', 'description' => 'Super administrator (full access)'],
            ['id' => 2, 'name' => 'client', 'description' => 'Client / Event owner'],
            ['id' => 3, 'name' => 'staff', 'description' => 'Staff / operator with limited privileges']
        ];
        foreach ($roles as $r) {
            // gunakan insert_ignore pattern: cek dulu apakah sudah ada
            $exists = $this->db->where('id', $r['id'])->or_where('name', $r['name'])->get('roles')->row();
            if (!$exists) {
                $this->db->insert('roles', $r);
            }
        }

        // 2) Insert admin user
        // jangan hardcode hash yang tidak dihasilkan di runtime; gunakan password_hash()
        $admin_email = 'admin@example.com';
        $admin_username = 'admin';
        $admin_password_plain = 'Admin123!'; // ubah setelah deployment
        $password_hash = password_hash($admin_password_plain, PASSWORD_DEFAULT);

        // cek apakah sudah ada user dengan email atau username yang sama
        $exists = $this->db->where('email', $admin_email)->or_where('username', $admin_username)->get('users')->row();
        if (!$exists) {
            $this->db->insert('users', [
                'role_id' => 1, // super_admin
                'username' => $admin_username,
                'email' => $admin_email,
                'password_hash' => $password_hash,
                'full_name' => 'System Administrator',
                'phone' => NULL,
                'is_active' => 1
            ]);
            $admin_user_id = $this->db->insert_id();
        } else {
            $admin_user_id = $exists->id;
        }

        // 3) Insert contoh event
        $slug = 'contoh-event-sample';
        $exists_event = $this->db->where('slug', $slug)->get('events')->row();
        if (!$exists_event) {
            $this->db->insert('events', [
                'owner_user_id' => $admin_user_id,
                'slug' => $slug,
                'title' => 'Contoh Event: Launching Produk',
                'description' => 'Event contoh yang dibuat oleh seed data. Hapus/ubah jika perlu.',
                'venue_name' => 'Aula Besar',
                'venue_address' => 'Jalan Contoh No. 1, Kota Contoh',
                'venue_lat' => NULL,
                'venue_lng' => NULL,
                'start_datetime' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'end_datetime' => date('Y-m-d H:i:s', strtotime('+7 days +3 hours')),
                'is_public' => 1,
                'status' => 'active',
                'capacity' => 500,
                'settings' => json_encode(['require_phone' => true, 'qrcode_mode' => 'guest_code'])
            ]);
            $event_id = $this->db->insert_id();
        } else {
            $event_id = $exists_event->id;
        }

        // 4) Insert contoh guest (tanpa email)
        $this->db->where('event_id', $event_id);
        $this->db->where('name', 'Tamu Contoh');
        $exists_guest = $this->db->get('guests')->row();
        if (!$exists_guest) {
            $guest_code = 'GUEST-' . strtoupper(uniqid());
            $this->db->insert('guests', [
                'event_id' => $event_id,
                'guest_code' => $guest_code,
                'name' => 'Tamu Contoh',
                'phone' => '081234567890',
                'type' => 'Guest',
                'status' => 'invited',
                'note' => 'Contoh tamu seed data'
            ]);
        }

        $this->db->trans_complete();

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            if ($this->db->trans_status() === FALSE) {
                echo "Migration 002_seed_data_v100: FAILED\n";
            } else {
                echo "Migration 002_seed_data_v100: OK (seed applied)\n";
                echo "Admin login (email): {$admin_email} | username: {$admin_username} | password: {$admin_password_plain}\n";
                echo "NB: Change the admin password immediately after first login.\n";
            }
        }
    }

    public function down()
    {
        $this->db->trans_start();

        // Hapus sample guest
        $this->db->where('name', 'Tamu Contoh')->delete('guests');

        // Hapus sample event
        $this->db->where('slug', 'contoh-event-sample')->delete('events');

        // Hapus admin user (hati-hati: hanya hapus jika email default)
        $this->db->where('email', 'admin@example.com')->delete('users');

        // Hapus roles sesuai seed (jika tidak dipakai)
        $this->db->where_in('name', ['super_admin','client','staff'])->delete('roles');

        $this->db->trans_complete();

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            if ($this->db->trans_status() === FALSE) {
                echo "Migration 002_seed_data_v100: ROLLBACK FAILED\n";
            } else {
                echo "Migration 002_seed_data_v100: ROLLBACK OK (seed removed)\n";
            }
        }
    }
}
