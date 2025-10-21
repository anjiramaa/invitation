<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Auth_model
 * Version: 2025-10-14_v2
 *
 * Penyesuaian:
 * - register_user kini menerima $role_key dan $parent_admin_id (nullable)
 * - menggunakan query binding sesuai guidline
 * - mengembalikan inserted user id pada sukses
 */

class Auth_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * attempt_login
     * @param string $identity (username or email)
     * @param string $password (plain)
     * @return array|false (user data without password_hash)
     */
    public function attempt_login($identity, $password)
    {
        $sql = "SELECT u.id, u.username, u.email, u.password_hash, u.full_name, u.role_id, u.parent_admin_id, r.role_key
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1
                LIMIT 1";
        $q = $this->db->query($sql, array($identity, $identity));
        $user = $q->row_array();

        if (!$user) return false;

        if (!empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            return $user;
        }

        return false;
    }

    /**
     * register_user
     * - Untuk pendaftaran publik / admin-created users
     * @param string $username
     * @param string $email
     * @param string $password
     * @param string $full_name
     * @param string $role_key (default 'client')
     * @param int|null $parent_admin_id (nullable) -> jika admin membuat client, set parent_admin_id = admin.id
     * @param int|null $creator_id (nullable) -> siapa yang membuat (untuk created_by)
     * @return int|false inserted user id or false
     */
    public function register_user($username, $email, $password, $full_name, $role_key = 'client', $parent_admin_id = null, $creator_id = null)
    {
        // Duplicate check
        $q = $this->db->query("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1", array($username, $email));
        if ($q->num_rows() > 0) {
            return false;
        }

        // Get role id by role_key
        $q2 = $this->db->query("SELECT id FROM roles WHERE role_key = ? LIMIT 1", array($role_key));
        $r = $q2->row_array();
        if (!$r) return false;
        $role_id = (int)$r['id'];

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert with optional parent_admin_id and created_by
        $sql = "INSERT INTO users (role_id, parent_admin_id, username, email, password_hash, full_name, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $this->db->query($sql, array($role_id, $parent_admin_id, $username, $email, $password_hash, $full_name, $creator_id));
        if ($this->db->affected_rows() > 0) {
            return (int)$this->db->insert_id();
        }
        return false;
    }

    /**
     * update_last_login
     */
    public function update_last_login($user_id)
    {
        $this->db->query("UPDATE users SET last_login = NOW() WHERE id = ?", array($user_id));
    }
}
