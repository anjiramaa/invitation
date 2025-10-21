<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Profile Controller
 * - Menyediakan halaman profile dan change password
 * - Hanya untuk user yang sudah login
 * Version: 2025-10-15_v1
 *
 * Note:
 * - Menggunakan MY_Controller::check_role_or_die([]) untuk memastikan authenticated.
 * - Menggunakan Users_model->change_password untuk validasi dan update.
 */

class Profile extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Pastikan user sudah login (authenticated)
        $this->check_role_or_die([]); // empty array => hanya cek authenticated

        // Load model users (full path: application/modules/users/models/Users_model.php)
        $this->load->model('users/users_model'); // HMVC: module/model
        $this->load->helper(['url', 'form']);
    }

    /**
     * index
     * - Menampilkan halaman profile sederhana (info user + link ke change password)
     */
    public function index()
    {
        $data = [
            'title' => 'Profile Settings',
            'user' => $this->current_user
        ];

        // Debug: show current user data (only in development)
        $this->debug_log('profile_index_user', $this->current_user);

        $this->load->view('index', $data);
    }

    /**
     * change_password (GET -> form, POST -> process)
     */
    public function change_password()
    {
        // Jika POST -> proses perubahan password
        if ($this->input->method() === 'post') {
            $old_password = $this->input->post('old_password', TRUE);
            $new_password = $this->input->post('new_password', TRUE);
            $confirm_password = $this->input->post('confirm_password', TRUE);

            // Basic validation
            if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
                $data['error'] = 'Lengkapi semua field.';
                $data['user'] = $this->current_user;
                $this->load->view('change_password', $data);
                return;
            }

            if ($new_password !== $confirm_password) {
                $data['error'] = 'Password baru dan konfirmasi tidak cocok.';
                $data['user'] = $this->current_user;
                $this->load->view('change_password', $data);
                return;
            }

            // Panggil model untuk mengganti password
            $user_id = (int)$this->current_user['id'];
            $result = $this->users_model->change_password($user_id, $old_password, $new_password);

            // Debug
            $this->debug_log('profile_change_password_result', $result);

            if ($result['status'] === 'ok') {
                // sukses -> set flash dan redirect ke profile (atau logout tergantung kebijakan)
                $this->session->set_flashdata('success', 'Password berhasil diubah. Silakan gunakan password baru saat login berikutnya.');
                // optional: force logout after change? For now keep session, just notify.
                redirect('profile');
                return;
            } else {
                // gagal -> tampilkan pesan error
                $data['error'] = $result['message'];
                $data['user'] = $this->current_user;
                $this->load->view('change_password', $data);
                return;
            }
        } else {
            // GET: tampilkan form
            $data = [
                'title' => 'Change Password',
                'user' => $this->current_user
            ];
            $this->load->view('change_password', $data);
        }
    }

    /**
     * api_change_password
     * - AJAX endpoint untuk mengganti password (mengembalikan JSON)
     */
    public function api_change_password()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Bad Request', 400);
            return;
        }

        $old_password = $this->input->post('old_password', TRUE);
        $new_password = $this->input->post('new_password', TRUE);
        $confirm_password = $this->input->post('confirm_password', TRUE);

        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $this->output->set_content_type('application/json')->set_output(json_encode(['status'=>'error','message'=>'Missing fields']));
            return;
        }

        if ($new_password !== $confirm_password) {
            $this->output->set_content_type('application/json')->set_output(json_encode(['status'=>'error','message'=>'New password and confirmation do not match']));
            return;
        }

        $user_id = (int)$this->current_user['id'];
        $result = $this->users_model->change_password($user_id, $old_password, $new_password);

        // Debug
        $this->debug_log('api_change_password_result', $result);

        $this->output->set_content_type('application/json')->set_output(json_encode($result));
    }
}
