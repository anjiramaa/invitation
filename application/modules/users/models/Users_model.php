<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Users_model
 * Version: 2025-10-16_v1
 *
 * - Full user management (get, create, update, delete)
 * - Enforces business rules:
 *    * parent_admin_id must refer to a user with role 'super_admin' or 'admin'
 *    * admin cannot create admin or super_admin
 *    * super_admin can create any role and can set parent_admin_id
 *    * public registration (via Auth) may pass parent_admin_id (usually first super_admin)
 * - Uses query binding consistently
 * - Adds change_password with verification & audit log entry
 */

class Users_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * get_users_for_admin
     * - role_key: role of the requesting user
     * - admin_id: id of the requesting user
     * returns array of users visible to the actor
     */
    public function get_users_for_admin($role_key, $admin_id)
    {
        if ($role_key === 'super_admin') {
            $sql = "SELECT u.*, r.role_key FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.created_at DESC";
            $q = $this->db->query($sql);
            return $q->result_array();
        } elseif ($role_key === 'admin') {
            $sql = "SELECT u.*, r.role_key FROM users u JOIN roles r ON u.role_id = r.id
                    WHERE (u.id = ?) OR (u.parent_admin_id = ?)
                    ORDER BY u.created_at DESC";
            $q = $this->db->query($sql, array($admin_id, $admin_id));
            return $q->result_array();
        } else {
            // clients & staff not allowed to list all users
            return [];
        }
    }

    /**
     * get_user_by_id
     */
    public function get_user_by_id($id)
    {
        $sql = "SELECT u.*, r.role_key FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1";
        $q = $this->db->query($sql, array($id));
        return $q->row_array();
    }

    /**
     * validate_parent_admin
     * - ensures parent_admin_id exists and has role_key in allowed list
     * - allowed roles for parent: super_admin, admin
     * returns true if ok, false otherwise
     */
    protected function validate_parent_admin($parent_admin_id)
    {
        if ($parent_admin_id === null) return true; // null is acceptable
        if (!is_numeric($parent_admin_id)) return false;

        $sql = "SELECT r.role_key FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1";
        $q = $this->db->query($sql, array((int)$parent_admin_id));
        $row = $q->row_array();
        if (!$row) return false;
        $rk = $row['role_key'];
        return in_array($rk, array('super_admin','admin'));
    }

    /**
     * create_user
     * - payload: username,email,full_name,password,role_key,parent_admin_id(optional)
     * - actor: current_user array (id,role_key)
     * returns boolean true on success
     */
    public function create_user($payload, $actor = null)
    {
        // check minimal payload
        if (empty($payload['username']) || empty($payload['email']) || empty($payload['password']) || empty($payload['role_key'])) {
            return false;
        }

        // get role id for requested role_key
        $q = $this->db->query("SELECT id FROM roles WHERE role_key = ? LIMIT 1", array($payload['role_key']));
        $role = $q->row_array();
        if (!$role) return false;
        $role_id = (int)$role['id'];

        // duplicate check
        $qd = $this->db->query("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1", array($payload['username'], $payload['email']));
        if ($qd->num_rows() > 0) return false;

        // Determine parent_admin_id according to actor
        $parent_admin_id = null;
        if (!empty($actor) && isset($actor['role_key'])) {
            $actor_role = $actor['role_key'];
            if ($actor_role === 'super_admin') {
                // super_admin can set parent_admin_id optionally
                if (isset($payload['parent_admin_id']) && $payload['parent_admin_id'] !== '') {
                    $parent_admin_id = (int)$payload['parent_admin_id'];
                    // validate parent points to allowed role
                    if (!$this->validate_parent_admin($parent_admin_id)) return false;
                } else {
                    $parent_admin_id = null;
                }
            } elseif ($actor_role === 'admin') {
                // admin cannot create admin or super_admin
                if (in_array($payload['role_key'], array('admin','super_admin'))) {
                    return false;
                }
                // parent_admin_id must be this admin
                $parent_admin_id = (int)$actor['id'];
            } else {
                // other roles cannot create user via this method
                return false;
            }
        } else {
            // public registration: payload may include parent_admin_id (Auth sets this to first super_admin)
            if (isset($payload['parent_admin_id']) && $payload['parent_admin_id'] !== '') {
                $parent_admin_id = (int)$payload['parent_admin_id'];
                if (!$this->validate_parent_admin($parent_admin_id)) return false;
            } else {
                // allow null as fallback (should be rare)
                $parent_admin_id = null;
            }
        }

        // Hash password securely
        $password_hash = password_hash($payload['password'], PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (role_id, parent_admin_id, username, email, password_hash, full_name, phone, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $this->db->query($sql, array($role_id, $parent_admin_id, $payload['username'], $payload['email'], $password_hash, $payload['full_name'], isset($payload['phone']) ? $payload['phone'] : null, isset($actor['id']) ? $actor['id'] : null));
        return $this->db->affected_rows() > 0;
    }

    /**
     * update_user
     * - Allows super_admin to change role and parent_admin_id
     * - Allows admin to update users under him (cannot promote/demote to admin/super_admin)
     */
    public function update_user($id, $payload, $actor = null)
    {
        $target = $this->get_user_by_id($id);
        if (!$target) return false;

        if (empty($actor) || !isset($actor['role_key'])) return false;

        $actor_role = $actor['role_key'];

        // Permission checks
        if ($actor_role === 'admin') {
            // admin can only update self or users under his parent_admin scope
            if (!($target['id'] == $actor['id'] || (isset($target['parent_admin_id']) && $target['parent_admin_id'] == $actor['id']))) {
                return false;
            }
            // cannot change role to admin or super_admin
            if (isset($payload['role_key']) && in_array($payload['role_key'], array('admin','super_admin'))) {
                return false;
            }
        } elseif ($actor_role === 'super_admin') {
            // allowed to update anyone
        } else {
            return false;
        }

        // Build update fields
        $fields = [];
        $params = [];

        if (!empty($payload['username'])) { $fields[] = "username = ?"; $params[] = $payload['username']; }
        if (!empty($payload['email'])) { $fields[] = "email = ?"; $params[] = $payload['email']; }
        if (!empty($payload['full_name'])) { $fields[] = "full_name = ?"; $params[] = $payload['full_name']; }
        if (isset($payload['phone'])) { $fields[] = "phone = ?"; $params[] = $payload['phone']; }
        if (!empty($payload['password'])) { $fields[] = "password_hash = ?"; $params[] = password_hash($payload['password'], PASSWORD_DEFAULT); }

        // role change only allowed for super_admin
        if (!empty($payload['role_key']) && $actor_role === 'super_admin') {
            $q = $this->db->query("SELECT id FROM roles WHERE role_key = ? LIMIT 1", array($payload['role_key']));
            $r = $q->row_array();
            if ($r) { $fields[] = "role_id = ?"; $params[] = (int)$r['id']; }
        }

        // parent_admin_id can be updated only by super_admin
        if (array_key_exists('parent_admin_id', $payload) && $actor_role === 'super_admin') {
            $par = $payload['parent_admin_id'];
            if ($par === null || $par === '') {
                $fields[] = "parent_admin_id = NULL";
            } else {
                // validate parent
                if (!$this->validate_parent_admin($par)) return false;
                $fields[] = "parent_admin_id = ?";
                $params[] = (int)$par;
            }
        }

        if (empty($fields)) {
            // nothing to update
            return true;
        }

        $params[] = $id;
        $sql = "UPDATE users SET " . implode(", ", $fields) . ", updated_at = NOW() WHERE id = ?";
        $this->db->query($sql, $params);
        return $this->db->affected_rows() >= 0;
    }

    /**
     * delete_user
     */
    public function delete_user($id, $actor = null)
    {
        $target = $this->get_user_by_id($id);
        if (!$target) return false;

        if (empty($actor) || !isset($actor['role_key'])) return false;

        if ($actor['id'] == $id) return false; // cannot delete self

        if ($actor['role_key'] === 'admin') {
            if (!($target['id'] == $actor['id'] || (isset($target['parent_admin_id']) && $target['parent_admin_id'] == $actor['id']))) return false;
            if (in_array($target['role_key'], array('admin','super_admin'))) return false;
        } elseif ($actor['role_key'] === 'super_admin') {
            // allowed to delete anyone (except self handled above)
        } else {
            return false;
        }

        $this->db->query("DELETE FROM users WHERE id = ?", array($id));
        return $this->db->affected_rows() > 0;
    }

    /**
     * change_password
     * - Validates old password, enforces minimal length on new password
     * - Returns array ['status'=>'ok'|'error','message'=>string]
     */
    public function change_password($user_id, $old_password, $new_password)
    {
        // Get current password_hash
        $q = $this->db->query("SELECT password_hash FROM users WHERE id = ? LIMIT 1", array($user_id));
        $row = $q->row_array();
        if (!$row) {
            return ['status' => 'error', 'message' => 'User tidak ditemukan.'];
        }

        $current_hash = $row['password_hash'];

        // Verify old password
        if (!password_verify($old_password, $current_hash)) {
            return ['status' => 'error', 'message' => 'Password lama tidak cocok.'];
        }

        // Validate new password
        if (strlen($new_password) < 8) {
            return ['status' => 'error', 'message' => 'Password baru harus minimal 8 karakter.'];
        }

        // Hash new password and update
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $this->db->query("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?", array($new_hash, $user_id));

        if ($this->db->affected_rows() >= 0) {
            // insert audit log
            $this->db->query("INSERT INTO audit_logs (user_id, action, resource_type, resource_id, created_at) VALUES (?, ?, ?, ?, NOW())",
                array($user_id, 'changed_password', 'user', (string)$user_id)
            );
            return ['status' => 'ok', 'message' => 'Password berhasil diubah.'];
        } else {
            return ['status' => 'error', 'message' => 'Gagal memperbarui password (database error).'];
        }
    }
}
