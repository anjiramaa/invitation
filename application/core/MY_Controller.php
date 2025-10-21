<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Controller (base controller)
 * - Versi: 2025-10-14_v2
 * - Penambahan helper scope untuk admin (get_clients_under_admin)
 * - Semua debug messages hanya aktif di ENVIRONMENT === 'development'
 */

if (class_exists('MX_Controller')) {
    class MY_Controller extends MX_Controller
    {
        protected $current_user = null;

        public function __construct()
        {
            parent::__construct();

            // Load common libs/helpers
            $this->load->library(['session', 'form_validation']);
            $this->load->helper(['url', 'security', 'file']);

            // timezone default sesuai dokumen
            date_default_timezone_set('Asia/Jakarta');

            // ambil session user (jika ada)
            $user = $this->session->userdata('user');
            if (!empty($user) && is_array($user)) {
                $this->current_user = $user;
            }

            // debug header only in development
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                $this->output->set_header('X-Debug-Mode: development');
            }
        }

        /**
         * check_role_or_die
         * - allowed_roles: array of role_key (e.g. ['super_admin','admin'])
         * - if empty, only checks authentication
         */
        protected function check_role_or_die(array $allowed_roles = [])
        {
            if (empty($this->current_user)) {
                if ($this->input->is_ajax_request()) {
                    $this->output->set_status_header(401)->set_content_type('application/json')->set_output(json_encode(['status'=>'error','message'=>'Unauthorized']));
                    exit;
                }
                redirect('auth/login');
            }

            if (empty($allowed_roles)) return true; // only authenticated

            $role_key = isset($this->current_user['role_key']) ? $this->current_user['role_key'] : null;
            if (!in_array($role_key, $allowed_roles)) {
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    $msg = "Access denied for role '{$role_key}'. Allowed: " . implode(',', $allowed_roles);
                    show_error($msg, 403, 'Access Denied');
                } else {
                    show_error('Access Denied', 403);
                }
                exit;
            }
            return true;
        }

        /**
         * get_clients_under_admin
         * - Kembalikan array user.id dari clients yang berada di bawah admin
         * - Digunakan model/controller untuk membatasi scope admin
         */
        protected function get_clients_under_admin($admin_user_id)
        {
            $this->load->database();
            $sql = "SELECT id FROM users WHERE parent_admin_id = ? AND role_id = (SELECT id FROM roles WHERE role_key = 'client' LIMIT 1)";
            $q = $this->db->query($sql, array($admin_user_id));
            $rows = $q->result_array();
            $ids = array_map(function($r){ return (int)$r['id']; }, $rows);
            return $ids;
        }

        /**
         * debug_log
         */
        protected function debug_log($label, $data)
        {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                log_message('debug', "[DEBUG] {$label}: " . print_r($data, true));
            }
        }
    }
} else {
    class MY_Controller extends CI_Controller {
        public function __construct() {
            parent::__construct();
        }
    }
}
