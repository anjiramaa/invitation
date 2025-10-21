<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Home Controller
 * - Menampilkan halaman landing / info
 * Version: 2025-10-14_v1
 */

class Home extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
    }

    // Halaman landing utama
    public function index()
    {
        // Debug: log current user jika development
        $this->debug_log('home_current_user', $this->current_user);

        $data = [
            'title' => 'Invitation System - Home',
            'user' => $this->current_user
        ];

        // Load view
        $this->load->view('index', $data);
    }

    // Halaman get Started
    public function get_started()
    {
        // Debug: log current user jika development
        $this->debug_log('home_current_user', $this->current_user);

        $data = [
            'title' => 'Invitation System - Get Started',
            'user' => $this->current_user
        ];

        // Load view
        $this->load->view('get_started', $data);
    }
}
