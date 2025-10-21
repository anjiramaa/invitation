<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Guests_model
 *
 * Model untuk operasi CRUD pada tabel `guests` dan helper terkait (guest_code, bulk import helper).
 *
 * Version: 1.0.0
 * Date: 2025-10-06
 * Author: ChatGPT
 *
 * Catatan:
 * - Kolom `email` sengaja tidak ada (sesuai permintaan).
 * - Menyediakan method create_bulk untuk import Excel/CSV (Anda dapat panggil dari controller import).
 * - Debug output hanya aktif di environment 'development'.
 */

class Guests_model extends CI_Model
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
    private $table = 'guests';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();

        if (ENVIRONMENT === 'development') {
            log_message('debug', "Guests_model v{$this->version} loaded.");
        }
    }

    /**
     * generate_guest_code
     * Membuat guest_code unik sederhana.
     * Anda bisa menggantinya dengan UUID atau hash lain bila perlu.
     *
     * @param int|null $guest_id (opsional) - jika diberikan, gunakan untuk deterministic code
     * @return string
     */
    public function generate_guest_code($guest_id = null)
    {
        if ($guest_id !== null) {
            // deterministic: prefix + base36(id) + timestamp fragment
            return 'G' . strtoupper(base_convert((int)$guest_id, 10, 36)) . substr(uniqid(), -4);
        }
        // random fallback
        return 'G' . strtoupper(substr(sha1(uniqid((string)mt_rand(), true)), 0, 10));
    }

    /**
     * get_by_id
     *
     * @param int $id
     * @return array|null
     */
    public function get_by_id($id)
    {
        $query = $this->db->get_where($this->table, ['id' => (int)$id], 1);
        if ($query->num_rows() === 0) return null;
        return $query->row_array();
    }

    /**
     * get_by_guest_code
     *
     * @param string $code
     * @return array|null
     */
    public function get_by_guest_code($code)
    {
        $query = $this->db->get_where($this->table, ['guest_code' => $code], 1);
        if ($query->num_rows() === 0) return null;
        return $query->row_array();
    }

    /**
     * create
     * Insert satu tamu
     *
     * @param array $data - allowed: event_id, guest_code, name, phone, type, status, note
     * @return int|false - id guest baru atau false
     */
    public function create(array $data)
    {
        $allowed = ['event_id','guest_code','name','phone','type','status','note'];
        $insert = array_intersect_key($data, array_flip($allowed));

        // jika tidak ada guest_code, generate
        if (empty($insert['guest_code'])) {
            $insert['guest_code'] = $this->generate_guest_code();
        }

        $this->db->insert($this->table, $insert);
        if ($this->db->affected_rows() === 1) {
            $id = (int)$this->db->insert_id();

            // Jika guest_code dihasilkan sebelum ada id (random), oke.
            // Jika ingin deterministic berdasarkan ID, anda bisa update guest_code di sini:
            // contoh: jika code format 'G'+base36(id)
            if (ENVIRONMENT === 'development') {
                log_message('debug', "Guests_model.create: id={$id}, guest_code={$insert['guest_code']}");
            }
            return $id;
        }

        if (ENVIRONMENT === 'development') {
            log_message('error', "Guests_model.create: failed. Query: " . $this->db->last_query());
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
        $allowed = ['guest_code','name','phone','type','status','note'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) return false;

        $this->db->where('id', (int)$id);
        $this->db->update($this->table, $update);

        $ok = ($this->db->affected_rows() >= 0);
        if (ENVIRONMENT === 'development') {
            log_message('debug', "Guests_model.update: id={$id}, ok=" . ($ok ? '1' : '0'));
        }
        return $ok;
    }

    /**
     * delete
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
            log_message('debug', "Guests_model.delete: id={$id}, deleted=" . ($ok ? '1' : '0'));
        }
        return $ok;
    }

    /**
     * list_by_event
     * Ambil daftar tamu untuk event tertentu, dengan paging dan filter status/type
     *
     * @param int $event_id
     * @param int $limit
     * @param int $offset
     * @param array $filters (status, type, q)
     * @return array
     */
    public function list_by_event($event_id, $limit = 100, $offset = 0, array $filters = [])
    {
        $this->db->where('event_id', (int)$event_id);

        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $this->db->where('type', $filters['type']);
        }
        if (!empty($filters['q'])) {
            $this->db->like('name', $filters['q'])->or_like('guest_code', $filters['q']);
        }

        $query = $this->db->get($this->table, (int)$limit, (int)$offset);
        return $query->result_array();
    }

    /**
     * create_bulk
     * Membuat banyak tamu sekaligus. Berguna untuk import Excel/CSV.
     *
     * @param int $event_id
     * @param array $rows - array of associative rows, tiap row minimal ['name'] dan optional ['phone','type','note','guest_code']
     * @return array - ['inserted' => n, 'failed' => m, 'errors' => [...]]
     */
    public function create_bulk($event_id, array $rows)
    {
        $result = ['inserted' => 0, 'failed' => 0, 'errors' => []];

        if (empty($rows)) {
            return $result;
        }

        $this->db->trans_start();

        foreach ($rows as $i => $r) {
            $row = [];
            $row['event_id'] = (int)$event_id;
            $row['name'] = isset($r['name']) ? trim($r['name']) : null;
            $row['phone'] = isset($r['phone']) ? trim($r['phone']) : null;
            $row['type'] = isset($r['type']) ? $r['type'] : null;
            $row['note'] = isset($r['note']) ? $r['note'] : null;
            $row['guest_code'] = isset($r['guest_code']) ? $r['guest_code'] : null;

            if (empty($row['name'])) {
                $result['failed']++;
                $result['errors'][] = "Row {$i}: missing name";
                continue;
            }

            // generate guest_code jika kosong
            if (empty($row['guest_code'])) {
                $row['guest_code'] = $this->generate_guest_code();
            }

            $this->db->insert($this->table, $row);
            if ($this->db->affected_rows() === 1) {
                $result['inserted']++;
            } else {
                $result['failed']++;
                $result['errors'][] = "Row {$i}: DB insert failed";
                if (ENVIRONMENT === 'development') {
                    $result['errors'][] = $this->db->last_query();
                }
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            // transaksi gagal -> rollback otomatis
            if (ENVIRONMENT === 'development') {
                log_message('error', "Guests_model.create_bulk: transaction failed");
            }
            // hitung semua sebagai failed jika rollback
            $result['failed'] = count($rows);
            $result['inserted'] = 0;
        }

        return $result;
    }
}
