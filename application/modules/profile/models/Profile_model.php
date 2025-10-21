<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Users_model
 *
 * Model CRUD untuk tabel `users`.
 * Versi ini memuat perbaikan:
 * - Konsistensi return (bool / id / array)
 * - Sanitasi input via allowed fields
 * - Transaksi di operasi sensitif
 * - Debug log hanya di ENVIRONMENT === 'development'
 *
 * VERSION: 1.1.0
 * DATE: 2025-10-08
 * AUTHOR: ChatGPT (for Febrianto Rama Anji)
 */

class Users_model extends CI_Model
{
    /**
     * version file
     */
    private $version = '1.1.0';

    /**
     * table
     */
    private $table = 'users';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();

        if (ENVIRONMENT === 'development') {
            log_message('debug', "Users_model v{$this->version} initialized.");
        }
    }

    /**
     * allowed fields untuk insert/update
     */
    private function allowed_fields()
    {
        return ['role_id','username','email','password_hash','full_name','phone','is_active','last_login_at'];
    }

    /**
     * get_by_id
     * @param int $id
     * @return array|null
     */
    public function get_by_id($id)
    {
        $id = (int)$id;
        if ($id <= 0) return null;
        $q = $this->db->get_where($this->table, ['id' => $id], 1);
        if ($q->num_rows() === 0) return null;
        return $q->row_array();
    }

    /**
     * get_by_email
     * @param string $email
     * @return array|null
     */
    public function get_by_email($email)
    {
        if (empty($email)) return null;
        $q = $this->db->get_where($this->table, ['email' => $email], 1);
        return ($q->num_rows() > 0) ? $q->row_array() : null;
    }

    /**
     * get_by_username
     * @param string $username
     * @return array|null
     */
    public function get_by_username($username)
    {
        if (empty($username)) return null;
        $q = $this->db->get_where($this->table, ['username' => $username], 1);
        return ($q->num_rows() > 0) ? $q->row_array() : null;
    }

    /**
     * create
     * @param array $data
     * @return int|false inserted id or false
     */
    public function create(array $data)
    {
        $insert = array_intersect_key($data, array_flip($this->allowed_fields()));

        // validasi minimal
        if (empty($insert['username']) || empty($insert['email']) || empty($insert['password_hash'])) {
            if (ENVIRONMENT === 'development') {
                log_message('error', 'Users_model.create: missing required fields');
            }
            return false;
        }

        $this->db->trans_start();
        $this->db->insert($this->table, $insert);
        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            if (ENVIRONMENT === 'development') {
                log_message('error', "Users_model.create: transaction failed. Last query: " . $this->db->last_query());
            }
            return false;
        }

        $id = (int)$this->db->insert_id();
        if (ENVIRONMENT === 'development') {
            log_message('debug', "Users_model.create: inserted id={$id}");
        }
        return $id;
    }

    /**
     * update
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data)
    {
        $id = (int)$id;
        if ($id <= 0) return false;

        $update = array_intersect_key($data, array_flip($this->allowed_fields()));
        if (empty($update)) return false;

        $this->db->trans_start();
        $this->db->where('id', $id);
        $this->db->update($this->table, $update);
        $this->db->trans_complete();

        $ok = $this->db->trans_status() !== FALSE;
        if (ENVIRONMENT === 'development') {
            log_message('debug', "Users_model.update: id={$id}, ok=" . ($ok ? '1' : '0'));
        }
        return $ok;
    }

    /**
     * delete (hard)
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $id = (int)$id;
        if ($id <= 0) return false;

        $this->db->trans_start();
        $this->db->where('id', $id);
        $this->db->delete($this->table);
        $this->db->trans_complete();

        $ok = $this->db->trans_status() !== FALSE;
        if (ENVIRONMENT === 'development') {
            log_message('debug', "Users_model.delete: id={$id}, ok=" . ($ok ? '1' : '0'));
        }
        return $ok;
    }

    /**
     * list
     * @param int $limit
     * @param int $offset
     * @param array $filters
     * @return array
     */
    public function list($limit = 50, $offset = 0, array $filters = [])
    {
        $limit = (int)$limit;
        $offset = (int)$offset;

        if (isset($filters['role_id'])) {
            $this->db->where('role_id', (int)$filters['role_id']);
        }
        if (isset($filters['is_active'])) {
            $this->db->where('is_active', (int)$filters['is_active']);
        }
        if (!empty($filters['q'])) {
            $this->db->group_start();
            $this->db->like('username', $filters['q']);
            $this->db->or_like('full_name', $filters['q']);
            $this->db->or_like('email', $filters['q']);
            $this->db->group_end();
        }

        $q = $this->db->get($this->table, $limit, $offset);
        return $q->result_array();
    }
}
