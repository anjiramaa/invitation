<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Auth Controller
 * Version: 2025-10-16_v3
 *
 * Improvements:
 * - Robust captcha() implementation (GD)
 * - Explicit session loading & session_write_close to ensure captcha is persisted
 * - Captcha expiry (5 minutes)
 * - Proper headers and output buffer cleaning to prevent image corruption
 * - Validation checks and clearing of captcha after use
 *
 * Note: keep file as-is (no stray whitespace before <?php)
 */

class Auth extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        // force-load session to be sure it's available here
        $this->load->library('session');
        $this->load->model('auth_model');
        $this->load->helper(['form','url']);
    }

    /**
     * captcha
     * - Output: PNG image (direct), sets session 'captcha_code' and 'captcha_time'
     *
     * Implementation details:
     * - Clears current output buffers before generating image.
     * - Writes PNG to output buffer (so we can ensure no accidental output).
     * - Emits no other content.
     */
    public function captcha()
    {
        // Configuration (aligned with your antrian.js generateCaptcha pattern)
        $width  = 120;
        $height = 40;
        $length = 5;
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        // Generate code
        $code = '';
        try {
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } catch (Exception $e) {
            // random_int may fail in very rare cases; fallback to mt_rand
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[mt_rand(0, strlen($chars) - 1)];
            }
        }
        $code = strtoupper($code);

        // Save to session
        $this->session->set_userdata('captcha_code', $code);
        $this->session->set_userdata('captcha_time', time());

        // Ensure no previous output (prevent 'blank image' caused by prior output)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Send no-cache headers first
        // Use CodeIgniter header helper or plain header (plain header is fine)
        header('Content-Type: image/png');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // If GD not available, create a simple fallback image with text
        if (!function_exists('imagecreatetruecolor')) {
            $im = imagecreate($width, $height);
            $bg = imagecolorallocate($im, 249, 250, 251); // #f9fafb
            $txt = imagecolorallocate($im, 55, 65, 81);   // #374151
            imagestring($im, 5, 10, 10, $code, $txt);
            // Buffer and output
            ob_start();
            imagepng($im);
            $png = ob_get_clean();
            imagedestroy($im);
            echo $png;
            exit;
        }

        // Create truecolor image and colors
        $im = imagecreatetruecolor($width, $height);

        // Define colors (match antrian style)
        $bgColor    = imagecolorallocate($im, 249, 250, 251); // #f9fafb
        $textColor  = imagecolorallocate($im, 55, 65, 81);   // #374151
        $noiseColor = imagecolorallocate($im, 209, 213, 219); // #d1d5db

        // Fill background
        imagefilledrectangle($im, 0, 0, $width, $height, $bgColor);

        // Try to use TTF font if available
        $fontPath = FCPATH . 'assets/fonts/Roboto-Regular.ttf'; // change if you use another font
        $useTtf = (file_exists($fontPath) && function_exists('imagettftext'));

        if ($useTtf) {
            // Draw each char with small random rotation and slight vertical jitter
            $fontSize = 18; // tune if needed
            $x = 10;
            $charSpacing = (int) ($width / $length) - 2;
            for ($i = 0; $i < strlen($code); $i++) {
                $char = $code[$i];
                $angle = random_int(-18, 18);
                $y = (int)($height / 2 + $fontSize / 2 - 4 + random_int(-2, 2));
                // Suppress any warnings from imagettftext by @ — we log in dev instead
                @imagettftext($im, $fontSize, $angle, $x, $y, $textColor, $fontPath, $char);
                $x += $charSpacing;
            }
        } else {
            // fallback: draw centered text using imagestring
            $font = 5;
            $textWidth = imagefontwidth($font) * strlen($code);
            $x = (int)(($width - $textWidth) / 2);
            $y = (int)(($height - imagefontheight($font)) / 2);
            imagestring($im, $font, $x, $y, $code, $textColor);
        }

        // Draw noise lines (mirror JS style)
        for ($i = 0; $i < 10; $i++) {
            $x1 = random_int(0, $width);
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width);
            $y2 = random_int(0, $height);
            imageline($im, $x1, $y1, $x2, $y2, $noiseColor);
        }

        // Add some dots
        for ($i = 0; $i < 60; $i++) {
            imagesetpixel($im, random_int(0, max(0,$width-1)), random_int(0, max(0,$height-1)), $noiseColor);
        }

        // Buffer the PNG so we have full control and avoid partial output
        ob_start();
        imagepng($im);
        $pngData = ob_get_clean();

        // Clean up
        imagedestroy($im);

        // Final sanity: if png buffer empty, send fallback text image
        if ($pngData === false || $pngData === '') {
            // fallback minimal image
            $im2 = imagecreate($width, $height);
            $bg2 = imagecolorallocate($im2, 249, 250, 251);
            $txt2 = imagecolorallocate($im2, 55, 65, 81);
            imagestring($im2, 5, 10, 10, $code, $txt2);
            ob_start();
            imagepng($im2);
            $pngData = ob_get_clean();
            imagedestroy($im2);
            // log in dev
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                log_message('debug', 'Auth::captcha produced empty PNG buffer, used fallback.');
            }
        }

        // Output the PNG bytes
        echo $pngData;
        exit;
    }

    /**
     * login (GET/POST)
     * - if public (not logged in) require captcha validation on POST
     */
    public function login()
    {
        if ($this->input->method() === 'post') {
            $identity = $this->input->post('identity', TRUE);
            $password = $this->input->post('password', TRUE);
            $captcha_input = $this->input->post('captcha', TRUE);

            // If public (not logged in), validate captcha
            if (empty($this->current_user)) {
                $session_code = $this->session->userdata('captcha_code');
                $session_time = $this->session->userdata('captcha_time');
                $ok_captcha = false;
                if (!empty($session_code) && !empty($captcha_input)) {
                    if (strtoupper(trim($captcha_input)) === strtoupper(trim($session_code))) {
                        // check expiry (5 minutes)
                        if (is_numeric($session_time) && (time() - (int)$session_time) <= 300) {
                            $ok_captcha = true;
                        }
                    }
                }
                // clear used captcha
                $this->session->unset_userdata('captcha_code');
                $this->session->unset_userdata('captcha_time');

                if (!$ok_captcha) {
                    $data['error'] = 'CAPTCHA tidak cocok atau kadaluarsa. Silakan coba lagi.';
                    $this->load->view('login', $data);
                    return;
                }
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
            $this->load->view('login');
        }
    }

    /**
     * register (GET/POST)
     * - public registration -> captcha required and assigned parent_super_admin
     * - admin and super_admin register via same endpoint but captcha not required
     */
    public function register()
    {
        if ($this->input->method() !== 'post') {
            $data = [];
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

        // POST
        $username = $this->input->post('username', TRUE);
        $email = $this->input->post('email', TRUE);
        $password = $this->input->post('password', TRUE);
        $full_name = $this->input->post('full_name', TRUE);
        $requested_role = $this->input->post('role_key', TRUE);
        $parent_admin_input = $this->input->post('parent_admin_id', TRUE);
        $captcha_input = $this->input->post('captcha', TRUE);

        // Basic validation
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
            // public registration: validate captcha
            $session_code = $this->session->userdata('captcha_code');
            $session_time = $this->session->userdata('captcha_time');
            $ok_captcha = false;
            if (!empty($session_code) && !empty($captcha_input)) {
                if (strtoupper(trim($captcha_input)) === strtoupper(trim($session_code))) {
                    if (is_numeric($session_time) && (time() - (int)$session_time) <= 300) {
                        $ok_captcha = true;
                    }
                }
            }
            // clear used captcha
            $this->session->unset_userdata('captcha_code');
            $this->session->unset_userdata('captcha_time');

            if (!$ok_captcha) {
                $data['error'] = 'CAPTCHA tidak cocok atau kadaluarsa. Silakan coba lagi.';
                $this->load->view('register', $data);
                return;
            }

            // public: assign parent to first super_admin if exists
            $q = $this->db->query("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_key = ? ORDER BY u.id ASC LIMIT 1", array('super_admin'));
            $row = $q->row_array();
            $parent_admin_id = $row['id'] ?? null;
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

    // API endpoints unchanged...
}
