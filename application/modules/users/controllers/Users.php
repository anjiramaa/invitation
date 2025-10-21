<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Users Controller
 * Version: 2025-10-16_v1
 *
 * Perubahan:
 * - Menyediakan daftar parents (super_admin + admin) untuk super_admin pada create/edit view
 * - Memastikan parent_admin_id ikut dikirim saat submit
 * - Tetap menegakkan rule: admin tidak dapat membuat admin
 */

class Users extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        // Hanya super_admin & admin boleh mengakses modul users
        $this->check_role_or_die(['super_admin', 'admin']);

        // load model HMVC style (module/model)
        $this->load->model('users_model');
        $this->load->helper(['form', 'url']);
    }

    /**
     * index - list users
     */
    public function index()
    {
        $role_key = $this->current_user['role_key'];
        $user_id = $this->current_user['id'];

        $users = $this->users_model->get_users_for_admin($role_key, $user_id);

        $data = [
            'title' => 'Users Management',
            'users' => $users,
            'current_user' => $this->current_user
        ];
        $this->load->view('list', $data);
    }

    /**
     * create - GET show form, POST process create
     */
    public function create()
    {
        // GET: tampilkan form
        if ($this->input->method() !== 'post') {
            $data = [];

            // jika current user adalah super_admin -> siapkan parents (super_admin & admin)
            if ($this->current_user['role_key'] === 'super_admin') {
                $parents = $this->get_possible_parents();
                $data['parents'] = $parents; // array id => "Role - Full Name (username)"
            }

            $this->load->view('form', $data);
            return;
        }

        // POST: proses pembuatan user
        $payload = [
            'username' => $this->input->post('username', TRUE),
            'email' => $this->input->post('email', TRUE),
            'full_name' => $this->input->post('full_name', TRUE),
            'password' => $this->input->post('password', TRUE),
            'role_key' => $this->input->post('role_key', TRUE)
        ];

        // parent_admin_id hanya dipakai jika disertakan (super_admin dapat mengisikan)
        $parent_id = $this->input->post('parent_admin_id', TRUE);
        if (!empty($parent_id)) {
            $payload['parent_admin_id'] = (int)$parent_id;
        }

        $this->debug_log('users_create_payload', $payload);

        $ok = $this->users_model->create_user($payload, $this->current_user);
        if ($ok) {
            $this->session->set_flashdata('success', 'User berhasil dibuat.');
            redirect('users');
        } else {
            // Jika gagal, tampilkan kembali form. Jika current_user super_admin, kirim parents lagi
            $data = ['error' => 'Gagal membuat user. Cek duplicate username/email atau permission.'];
            if ($this->current_user['role_key'] === 'super_admin') {
                $data['parents'] = $this->get_possible_parents();
            }
            $this->load->view('form', $data);
        }
    }

    /**
     * edit - GET show form with existing data; POST update
     */
    public function edit($id = null)
    {
        if (!$id) show_404();

        // GET - menampilkan form
        if ($this->input->method() !== 'post') {
            $data['user'] = $this->users_model->get_user_by_id($id);
            if (!$data['user']) show_404();

            // jika super_admin -> siapkan parents
            if ($this->current_user['role_key'] === 'super_admin') {
                $data['parents'] = $this->get_possible_parents();
            }
            $this->load->view('form', $data);
            return;
        }

        // POST - proses update
        $payload = [
            'username' => $this->input->post('username', TRUE),
            'email' => $this->input->post('email', TRUE),
            'full_name' => $this->input->post('full_name', TRUE),
            'password' => $this->input->post('password', TRUE),
            'role_key' => $this->input->post('role_key', TRUE)
        ];

        // parent_admin_id hanya diperbolehkan diubah oleh super_admin
        if ($this->current_user['role_key'] === 'super_admin') {
            $parent_id = $this->input->post('parent_admin_id', TRUE);
            if ($parent_id !== null) {
                // bisa kosong string => set null
                $payload['parent_admin_id'] = $parent_id === '' ? null : (int)$parent_id;
            }
        }

        $this->debug_log('users_edit_payload', $payload);

        $ok = $this->users_model->update_user($id, $payload, $this->current_user);
        if ($ok) {
            $this->session->set_flashdata('success', 'User berhasil diperbarui.');
            redirect('users');
        } else {
            $data['error'] = 'Gagal memperbarui user.';
            $data['user'] = $this->users_model->get_user_by_id($id);
            if ($this->current_user['role_key'] === 'super_admin') {
                $data['parents'] = $this->get_possible_parents();
            }
            $this->load->view('form', $data);
        }
    }

    /**
     * delete
     */
    public function delete($id = null)
    {
        if (!$id) show_404();
        $ok = $this->users_model->delete_user($id, $this->current_user);
        if ($ok) {
            $this->session->set_flashdata('success', 'User dihapus.');
        } else {
            $this->session->set_flashdata('error', 'Gagal menghapus user.');
        }
        redirect('users');
    }

    /**
     * API endpoints (list/get/create/update/delete) - unchanged; but create/update endpoints
     * should also accept parent_admin_id if provided (AJAX)
     */

    public function api_list()
    {
        if (!$this->input->is_ajax_request()) { show_404(); return; }
        $role_key = $this->current_user['role_key'];
        $user_id = $this->current_user['id'];
        $users = $this->users_model->get_users_for_admin($role_key, $user_id);
        $this->output->set_content_type('application/json')->set_output(json_encode(['status'=>'ok','data'=>$users]));
    }

    // get single
    public function api_get($id)
    {
        if (!$this->input->is_ajax_request()) { show_404(); return; }
        $user = $this->users_model->get_user_by_id($id);
        if (!$user) {
            $this->output->set_content_type('application/json')->set_output(json_encode(['status'=>'error','message'=>'Not found']));
            return;
        }
        $this->output->set_content_type('application/json')->set_output(json_encode(['status'=>'ok','data'=>$user]));
    }

    // create via API
    public function api_create()
    {
        if (!$this->input->is_ajax_request()) { show_404(); return; }
        $payload = [
            'username' => $this->input->post('username', TRUE),
            'email' => $this->input->post('email', TRUE),
            'full_name' => $this->input->post('full_name', TRUE),
            'password' => $this->input->post('password', TRUE),
            'role_key' => $this->input->post('role_key', TRUE)
        ];
        // accept parent_admin_id if provided
        $p = $this->input->post('parent_admin_id', TRUE);
        if ($p !== null) $payload['parent_admin_id'] = $p === '' ? null : (int)$p;

        $ok = $this->users_model->create_user($payload, $this->current_user);
        $this->output->set_content_type('application/json')->set_output(json_encode(['status' => $ok ? 'ok':'error']));
    }

    // update via API
    public function api_update($id)
    {
        if (!$this->input->is_ajax_request()) { show_404(); return; }
        $payload = [
            'username' => $this->input->post('username', TRUE),
            'email' => $this->input->post('email', TRUE),
            'full_name' => $this->input->post('full_name', TRUE),
            'password' => $this->input->post('password', TRUE),
            'role_key' => $this->input->post('role_key', TRUE)
        ];
        if ($this->current_user['role_key'] === 'super_admin') {
            $p = $this->input->post('parent_admin_id', TRUE);
            if ($p !== null) $payload['parent_admin_id'] = $p === '' ? null : (int)$p;
        }
        $ok = $this->users_model->update_user($id, $payload, $this->current_user);
        $this->output->set_content_type('application/json')->set_output(json_encode(['status' => $ok ? 'ok':'error']));
    }

    public function api_delete($id)
    {
        if (!$this->input->is_ajax_request()) { show_404(); return; }
        $ok = $this->users_model->delete_user($id, $this->current_user);
        $this->output->set_content_type('application/json')->set_output(json_encode(['status' => $ok ? 'ok':'error']));
    }

    /**
     * Helper: get_possible_parents
     * -> returns array id => "ROLE - Full Name (username)" for users with role_key IN ('super_admin','admin')
     */
    protected function get_possible_parents()
    {
        $sql = "SELECT u.id, u.full_name, u.username, r.role_key
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE r.role_key IN ('super_admin','admin')
                ORDER BY r.role_key DESC, u.full_name ASC";
        $q = $this->db->query($sql);
        $rows = $q->result_array();
        $parents = [];
        foreach ($rows as $r) {
            $label = strtoupper($r['role_key']) . ' - ' . $r['full_name'] . ' (' . $r['username'] . ')';
            $parents[$r['id']] = $label;
        }
        return $parents;
    }
}
