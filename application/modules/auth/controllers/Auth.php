<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Auth Controller
 * Version: 2025-10-17_v1
 *
 * Perubahan penting:
 * - Menambahkan endpoint captcha_json() yang meng-generate kode captcha,
 *   menyimpannya ke session, dan mengembalikan JSON {captcha: 'XXXXX'}.
 * - login() dan register() memvalidasi captcha dengan session value.
 *
 * NOTE:
 * - Semua debug messages hanya aktif saat ENVIRONMENT === 'development'
 * - Pastikan session library aktif dan dapat menulis session (CI session)
 */

class Auth extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('auth_model');
        $this->load->helper(['form','url']);
    }

    /**
     * captcha_json
     * - Menghasilkan kode CAPTCHA (string) dan menyimpannya ke session.
     * - Mengembalikan JSON: {captcha: 'ABCDE'}.
     * - Client-side akan menggambar ke canvas menggunakan value ini.
     *
     * Endpoint: /auth/captcha_json
     */
    public function captcha_json()
    {
        // Prevent caching
        $this->output->set_header('Cache-Control: no-cache, no-store, must-revalidate');
        $this->output->set_header('Pragma: no-cache');
        $this->output->set_header('Expires: 0');

        // Generate code
        $length = 5;
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // avoid ambiguous chars
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        // store upper-case in session for server-side validation
        $this->session->set_userdata('captcha_code', strtoupper($code));
        $this->session->set_userdata('captcha_time', time());

        // Return JSON (code will be used by client to draw canvas)
        $this->output
             ->set_content_type('application/json')
             ->set_output(json_encode(['status' => 'ok', 'captcha' => $code]));
    }

    /**
     * login
     * - GET: tampilkan form
     * - POST: proses login
     * - Jika user tidak login (publik), maka server melakukan validasi captcha
     */
    public function login()
    {
        if ($this->input->method() === 'post') {
            $identity = $this->input->post('identity', TRUE);
            $password = $this->input->post('password', TRUE);
            $captcha_input = $this->input->post('captcha', TRUE);

            // Jika publik (tidak logged in), require captcha
            if (empty($this->current_user)) {
                $session_code = $this->session->userdata('captcha_code');
                // normalize and compare
                if (empty($captcha_input) || empty($session_code) || strtoupper(trim($captcha_input)) !== strtoupper(trim($session_code))) {
                    // invalidate used/failed captcha
                    $this->session->unset_userdata('captcha_code');
                    $data['error'] = 'CAPTCHA tidak cocok. Silakan coba lagi.';
                    $this->load->view('login', $data);
                    return;
                }
                // valid -> remove from session so it can't be reused
                $this->session->unset_userdata('captcha_code');
            }

            if (empty($identity) || empty($password)) {
                $data['error'] = 'Isi username/email dan password.';
                $this->load->view('login', $data);
                return;
            }

            $user = $this->auth_model->attempt_login($identity, $password);
            $this->debug_log('auth_login_attempt', ['identity'=>$identity, 'found'=>!empty($user)]);

            if ($user) {
                $sess = [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role_id' => (int)$user['role_id'],
                    'role_key' => $user['role_key'],
                    'parent_admin_id' => isset($user['parent_admin_id']) ? $user['parent_admin_id'] : null,
                    'last_login' => $user['last_login'] ?? null
                ];
                $this->session->set_userdata('user', $sess);
                $this->auth_model->update_last_login($user['id']);
                redirect('dashboard');
                return;
            } else {
                $data['error'] = 'Login gagal: kredensial tidak cocok atau user tidak aktif.';
                $this->load->view('login', $data);
                return;
            }
        } else {
            // GET
            $this->load->view('login');
        }
    }

    /**
     * register
     * - GET: tampilkan form (context aware)
     * - POST: proses registrasi
     * - Untuk PUBLIC register: captcha wajib (divalidasi server-side)
     */
    public function register()
    {
        if ($this->input->method() !== 'post') {
            $data = [];
            // jika super_admin, beri daftar admins untuk parent dropdown
            if (!empty($this->current_user) && $this->current_user['role_key'] === 'super_admin') {
                $admins = [];
                $sql = "SELECT u.id, u.full_name, u.username, r.role_key
                        FROM users u
                        JOIN roles r ON u.role_id = r.id
                        WHERE r.role_key IN ('super_admin','admin')
                        ORDER BY r.role_key DESC, u.full_name ASC";
                $q = $this->db->query($sql);
                foreach ($q->result_array() as $row) {
                    $admins[$row['id']] = strtoupper($row['role_key']) . ' - ' . $row['full_name'] . ' (' . $row['username'] . ')';
                }
                $data['admins'] = $admins;
            }
            $this->load->view('register', $data);
            return;
        }

        // POST processing
        $username = $this->input->post('username', TRUE);
        $email = $this->input->post('email', TRUE);
        $password = $this->input->post('password', TRUE);
        $full_name = $this->input->post('full_name', TRUE);
        $requested_role = $this->input->post('role_key', TRUE);
        $parent_admin_input = $this->input->post('parent_admin_id', TRUE);
        $captcha_input = $this->input->post('captcha', TRUE);

        if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            $data['error'] = 'Lengkapi semua field yang dibutuhkan.';
            $this->load->view('register', $data);
            return;
        }

        $role_key = 'client';
        $parent_admin_id = null;
        $creator_id = null;

        if (!empty($this->current_user)) {
            $creator_id = $this->current_user['id'];
            $actor_role = $this->current_user['role_key'];

            if ($actor_role === 'admin') {
                $role_key = in_array($requested_role, ['client','staff']) ? $requested_role : 'client';
                $parent_admin_id = $this->current_user['id'];
            } elseif ($actor_role === 'super_admin') {
                $role_key = !empty($requested_role) ? $requested_role : 'client';
                $parent_admin_id = (!empty($parent_admin_input) && is_numeric($parent_admin_input)) ? (int)$parent_admin_input : null;
            } else {
                $role_key = 'client';
            }
        } else {
            // PUBLIC: validate captcha against session
            $session_code = $this->session->userdata('captcha_code');
            if (empty($captcha_input) || empty($session_code) || strtoupper(trim($captcha_input)) !== strtoupper(trim($session_code))) {
                $this->session->unset_userdata('captcha_code');
                $data['error'] = 'CAPTCHA tidak cocok. Silakan coba lagi.';
                $this->load->view('register', $data);
                return;
            }
            $this->session->unset_userdata('captcha_code');

            // parent = first super_admin (if exists)
            $q = $this->db->query("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_key = ? ORDER BY u.id ASC LIMIT 1", array('super_admin'));
            $row = $q->row_array();
            if ($row && isset($row['id'])) {
                $parent_admin_id = (int)$row['id'];
            } else {
                $parent_admin_id = null;
            }
            $role_key = 'client';
        }

        $inserted = $this->auth_model->register_user($username, $email, $password, $full_name, $role_key, $parent_admin_id, $creator_id);

        if ($inserted) {
            if (!empty($this->current_user) && in_array($this->current_user['role_key'], ['super_admin','admin'])) {
                $this->session->set_flashdata('success', 'User berhasil dibuat.');
                redirect('users');
            } else {
                $this->session->set_flashdata('success', 'Registrasi berhasil. Silakan login.');
                redirect('auth/login');
            }
        } else {
            $data['error'] = 'Gagal mendaftar. Username atau email mungkin sudah dipakai atau parent invalid.';
            if (!empty($this->current_user) && $this->current_user['role_key'] === 'super_admin') {
                $admins = [];
                $sql = "SELECT u.id, u.full_name, u.username, r.role_key
                        FROM users u
                        JOIN roles r ON u.role_id = r.id
                        WHERE r.role_key IN ('super_admin','admin')
                        ORDER BY r.role_key DESC, u.full_name ASC";
                $q = $this->db->query($sql);
                foreach ($q->result_array() as $row) {
                    $admins[$row['id']] = strtoupper($row['role_key']) . ' - ' . $row['full_name'] . ' (' . $row['username'] . ')';
                }
                $data['admins'] = $admins;
            }
            $this->load->view('register', $data);
        }
    }

    /**
     * logout
     */
    public function logout()
    {
        $this->session->unset_userdata('user');
        $this->session->sess_destroy();
        redirect('auth/login');
    }

    // other API endpoints (api_login, api_register etc.) can remain unchanged.
}
