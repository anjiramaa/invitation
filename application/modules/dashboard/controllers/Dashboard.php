<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dashboard Controller
 * - Hanya dapat diakses user terautentikasi
 * - Menyajikan ringkasan sederhana
 * Version: 2025-10-14_v1
 */

class Dashboard extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        // Pastikan login (auth)
        $this->check_role_or_die([]); // hanya cek authenticated, tidak membatasi role
        $this->load->model('dashboard_model');
    }

    public function index()
    {
        // Ambil summary data berdasarkan role
        $role_key = $this->current_user['role_key'];
        $user_id = $this->current_user['id'];

        $summary = $this->dashboard_model->get_summary_for_user($role_key, $user_id);

        $data = [
            'title' => 'Dashboard',
            'user' => $this->current_user,
            'summary' => $summary
        ];

        $this->load->view('index', $data);
    }
}
