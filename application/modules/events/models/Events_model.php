<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Events_model
 *
 * Model untuk operasi CRUD pada tabel `events`.
 *
 * Version: 1.0.0
 * Date: 2025-10-06
 * Author: ChatGPT
 *
 * Catatan:
 * - Menyediakan method untuk create/update/get/list event.
 * - Meng-handle kolom `settings` (JSON) sebagai array PHP.
 * - Debug hanya aktif ketika ENVIRONMENT === 'development'.
 */

class Events_model extends CI_Model
{
    /**
     * Version file
     * @var string
     */
    private $version = '1.0.0';

    /**
     * Nama tabel
     * @var string
     */
    private $table = 'events';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        if (ENVIRONMENT === 'development') {
            log_message('debug', "Events_model v{$this->version} loaded.");
        }
    }

    /**
     * get_by_id
     * Ambil event berdasarkan ID
     *
     * @param int $id
     * @return array|null
     */
    public function get_by_id($id)
    {
        $query = $this->db->get_where($this->table, ['id' => (int)$id], 1);
        if ($query->num_rows() === 0) return null;
        $row = $query->row_array();

        // decode JSON settings jika tersedia
        if (!empty($row['settings'])) {
            $decoded = json_decode($row['settings'], true);
            $row['settings'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['settings'] = [];
        }
        return $row;
    }

    /**
     * create
     * Buat event baru
     *
     * @param array $data
     * @return int|false - id event baru atau false
     */
    public function create(array $data)
    {
        $allowed = ['owner_user_id','slug','title','description','venue_name','venue_address','venue_lat','venue_lng','start_datetime','end_datetime','is_public','status','capacity','settings'];
        $insert = array_intersect_key($data, array_flip($allowed));

        // jika settings diberikan sebagai array, encode ke JSON
        if (isset($insert['settings']) && is_array($insert['settings'])) {
            $insert['settings'] = json_encode($insert['settings']);
        }

        $this->db->insert($this->table, $insert);
        if ($this->db->affected_rows() === 1) {
            $id = (int)$this->db->insert_id();
            if (ENVIRONMENT === 'development') {
                log_message('debug', "Events_model.create: id={$id}");
            }
            return $id;
        }

        if (ENVIRONMENT === 'development') {
            log_message('error', "Events_model.create: failed. Last query: " . $this->db->last_query());
        }
        return false;
    }

    /**
     * update
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data)
    {
        $allowed = ['owner_user_id','slug','title','description','venue_name','venue_address','venue_lat','venue_lng','start_datetime','end_datetime','is_public','status','capacity','settings'];
        $update = array_intersect_key($data, array_flip($allowed));

        if (isset($update['settings']) && is_array($update['settings'])) {
            $update['settings'] = json_encode($update['settings']);
        }

        if (empty($update)) return false;

        $this->db->where('id', (int)$id);
        $this->db->update($this->table, $update);

        $ok = ($this->db->affected_rows() >= 0);
        if (ENVIRONMENT === 'development') {
            log_message('debug', "Events_model.update: id={$id}, ok=" . ($ok ? '1' : '0'));
        }
        return $ok;
    }

    /**
     * delete
     * Hapus event (hard delete). Pastikan backup sebelum gunakan di production.
     *
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $this->db->where('id', (int)$id);
        $this->db->delete($this->table);
        $ok = ($this->db->affected_rows() === 1);
        if (ENVIRONMENT === 'development') {
            log_message('debug', "Events_model.delete: id={$id}, deleted=" . ($ok ? '1' : '0'));
        }
        return $ok;
    }

    /**
     * list
     * Ambil daftar events dengan filter sederhana dan paging
     *
     * @param int $limit
     * @param int $offset
     * @param array $filters (owner_user_id, status, q)
     * @return array
     */
    public function list($limit = 50, $offset = 0, array $filters = [])
    {
        if (isset($filters['owner_user_id'])) {
            $this->db->where('owner_user_id', (int)$filters['owner_user_id']);
        }
        if (isset($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $this->db->like('title', $filters['q'])->or_like('slug', $filters['q']);
        }

        $query = $this->db->get($this->table, (int)$limit, (int)$offset);
        $rows = $query->result_array();

        // decode settings tiap row
        foreach ($rows as &$r) {
            if (!empty($r['settings'])) {
                $decoded = json_decode($r['settings'], true);
                $r['settings'] = is_array($decoded) ? $decoded : [];
            } else {
                $r['settings'] = [];
            }
        }
        return $rows;
    }

    /**
     * find_by_slug
     * Ambil event berdasarkan slug (untuk public URL)
     *
     * @param string $slug
     * @return array|null
     */
    public function find_by_slug($slug)
    {
        $query = $this->db->get_where($this->table, ['slug' => $slug], 1);
        if ($query->num_rows() === 0) return null;
        $row = $query->row_array();
        if (!empty($row['settings'])) {
            $decoded = json_decode($row['settings'], true);
            $row['settings'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['settings'] = [];
        }
        return $row;
    }
}
