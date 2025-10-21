<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Addons Controller
 * - Manage addons (CRUD), upload snippet HTML or assets, preview addon snippet
 * - API: provide addon data for a template slug
 *
 * Version: 2025-10-19_v1
 *
 * Notes:
 * - All DB operations use CI query binding
 * - Debug messages are logged only if ENVIRONMENT === 'development'
 */

class Addons extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        // require authentication for admin pages
        $this->check_role_or_die(['super_admin','admin','client','staff']);

        $this->load->model('addons_model');
        $this->load->helper(['form','url','file']);
        $this->load->library('upload');

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            log_message('debug', 'Addons controller initialized - v2025-10-19_v1');
        }
    }

    /**
     * index - list addons (global & per-event)
     */
    public function index()
    {
        $user = $this->current_user;
        $addons = $this->addons_model->get_all_addons_for_user($user);

        $data = [
            'title' => 'Addons',
            'addons' => $addons,
            'current_user' => $user
        ];

        $this->load->view('list', $data);
    }

    /**
     * create - GET form / POST save
     */
    public function create()
    {
        if ($this->input->method() !== 'post') {
            $data = ['title' => 'Create Addon', 'current_user' => $this->current_user];
            $this->load->view('form', $data);
            return;
        }

        // POST processing
        $name = $this->input->post('name', TRUE);
        $slug = $this->input->post('slug', TRUE);
        $type = $this->input->post('type', TRUE);
        $event_id = $this->input->post('event_id', TRUE); // optional
        $template_id = $this->input->post('template_id', TRUE); // optional
        $settings = $this->input->post('settings', TRUE); // JSON string

        if (empty($name) || empty($type)) {
            $this->session->set_flashdata('error', 'Name and type are required');
            redirect('addons/create');
            return;
        }

        $slug = empty($slug) ? url_title($name, '-', TRUE) : url_title($slug, '-', TRUE);

        // ensure unique slug per event (null event means global)
        if ($this->addons_model->slug_exists($slug, $event_id)) {
            $slug = $slug . '-' . time();
        }

        $payload = [
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'event_id' => $event_id ? $event_id : null,
            'template_id' => $template_id ? $template_id : null,
            'settings' => $settings ? $settings : null,
            'created_by' => $this->current_user['id']
        ];

        $new_id = $this->addons_model->create_addon($payload);
        if ($new_id) {
            $this->session->set_flashdata('success', 'Addon created. You can upload snippet or assets now.');
            redirect('addons');
        } else {
            $this->session->set_flashdata('error', 'Failed to create addon.');
            redirect('addons/create');
        }
    }

    /**
     * edit - edit addon metadata
     */
    public function edit($id = null)
    {
        if (!$id) show_404();
        $addon = $this->addons_model->get_addon($id);
        if (!$addon) show_404();
        if (!$this->addons_model->can_edit($addon, $this->current_user)) show_error('Forbidden', 403);

        if ($this->input->method() !== 'post') {
            $data = ['title' => 'Edit Addon', 'addon' => $addon, 'current_user' => $this->current_user];
            $this->load->view('form', $data);
            return;
        }

        $name = $this->input->post('name', TRUE);
        $slug = $this->input->post('slug', TRUE);
        $type = $this->input->post('type', TRUE);
        $settings = $this->input->post('settings', TRUE);

        if (empty($name) || empty($type)) {
            $this->session->set_flashdata('error', 'Name and type are required');
            redirect('addons/edit/'.$id);
            return;
        }

        $slug = empty($slug) ? url_title($name, '-', TRUE) : url_title($slug, '-', TRUE);

        $payload = [
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'settings' => $settings ? $settings : null
        ];

        $ok = $this->addons_model->update_addon($id, $payload);
        if ($ok) {
            $this->session->set_flashdata('success', 'Addon updated.');
            redirect('addons');
        } else {
            $this->session->set_flashdata('error', 'Failed to update addon.');
            redirect('addons/edit/'.$id);
        }
    }

    /**
     * delete - delete addon (DB) and optionally remove files
     */
    public function delete($id = null)
    {
        if (!$id) show_404();
        $addon = $this->addons_model->get_addon($id);
        if (!$addon) show_404();
        if (!$this->addons_model->can_edit($addon, $this->current_user)) show_error('Forbidden', 403);

        // try remove asset files folder if exists (assets/addons/{slug}/)
        if (!empty($addon['slug'])) {
            $folder = FCPATH . 'assets/addons/' . $addon['slug'] . '/';
            if (is_dir($folder)) {
                $this->rrmdir_all($folder);
            }
        }

        $ok = $this->addons_model->delete_addon($id);
        if ($ok) $this->session->set_flashdata('success','Addon deleted.');
        else $this->session->set_flashdata('error','Failed to delete addon.');
        redirect('addons');
    }

    /**
     * upload_snippet - upload a single snippet HTML file (e.g. gallery.html)
     * - filename will be saved under assets/addons/{addon_slug}/snippet.html or provided name
     */
    public function upload_snippet($addon_id = null)
    {
        if (!$addon_id) show_404();
        $addon = $this->addons_model->get_addon($addon_id);
        if (!$addon) show_404();
        if (!$this->addons_model->can_edit($addon, $this->current_user)) show_error('Forbidden', 403);

        if ($this->input->method() !== 'post') {
            $data = ['title' => 'Upload Snippet', 'addon' => $addon, 'current_user' => $this->current_user];
            $this->load->view('upload_snippet', $data);
            return;
        }

        if (empty($_FILES['snippet']) || $_FILES['snippet']['error'] !== UPLOAD_ERR_OK) {
            $this->session->set_flashdata('error','No file uploaded.');
            redirect('addons/upload-snippet/'.$addon_id);
            return;
        }

        // Accept only .html files (text/html)
        $ext = pathinfo($_FILES['snippet']['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'html' && strtolower($ext) !== 'htm') {
            $this->session->set_flashdata('error','Snippet harus berupa HTML file (.html).');
            redirect('addons/upload-snippet/'.$addon_id);
            return;
        }

        $slug = $addon['slug'];
        $target_dir_rel = 'assets/addons/' . $slug;
        $target_dir = FCPATH . $target_dir_rel . '/';
        if (!is_dir($target_dir)) @mkdir($target_dir, 0755, true);

        // desired filename: snippet.html or original name sanitized
        $filename = 'snippet.html';
        $dest_path = $target_dir . $filename;

        if (!move_uploaded_file($_FILES['snippet']['tmp_name'], $dest_path)) {
            $this->session->set_flashdata('error','Failed to move uploaded snippet.');
            redirect('addons/upload-snippet/'.$addon_id);
            return;
        }
        @chmod($dest_path, 0644);

        // register asset record
        $asset_payload = [
            'addon_id' => $addon_id,
            'filename' => $filename,
            'path' => $target_dir_rel . '/' . $filename,
            'mime' => mime_content_type($dest_path),
            'size' => filesize($dest_path),
            'uploaded_by' => $this->current_user['id']
        ];
        $this->addons_model->ensure_asset_record($asset_payload);

        // update addon.snippet_path
        $this->addons_model->update_addon($addon_id, ['snippet_path' => $asset_payload['path']]);

        $this->session->set_flashdata('success','Snippet uploaded.');
        redirect('addons');
    }

    /**
     * upload_assets - upload multiple assets (images, audio, etc) as zip or multiple files
     * Accepts input name 'assets_zip' (zip) OR multiple file inputs name 'assets[]'
     */
    public function upload_assets($addon_id = null)
    {
        if (!$addon_id) show_404();
        $addon = $this->addons_model->get_addon($addon_id);
        if (!$addon) show_404();
        if (!$this->addons_model->can_edit($addon, $this->current_user)) show_error('Forbidden', 403);

        if ($this->input->method() !== 'post') {
            $data = ['title' => 'Upload Assets', 'addon' => $addon, 'current_user' => $this->current_user];
            $this->load->view('upload_assets', $data);
            return;
        }

        $slug = $addon['slug'];
        $target_dir_rel = 'assets/addons/' . $slug;
        $target_dir = FCPATH . $target_dir_rel . '/';
        if (!is_dir($target_dir)) @mkdir($target_dir, 0755, true);

        // Handle zip first
        if (!empty($_FILES['assets_zip']) && $_FILES['assets_zip']['error'] === UPLOAD_ERR_OK) {
            $tmp = sys_get_temp_dir() . '/addon_assets_' . uniqid() . '.zip';
            if (!move_uploaded_file($_FILES['assets_zip']['tmp_name'], $tmp)) {
                $this->session->set_flashdata('error','Failed to move uploaded zip.');
                redirect('addons/upload-assets/'.$addon_id);
                return;
            }
            $zip = new ZipArchive();
            $res = $zip->open($tmp);
            if ($res !== TRUE) {
                @unlink($tmp);
                $this->session->set_flashdata('error','Invalid ZIP archive.');
                redirect('addons/upload-assets/'.$addon_id);
                return;
            }
            for ($i=0;$i<$zip->numFiles;$i++) {
                $entry = $zip->getNameIndex($i);
                $entry = str_replace('\\','/',$entry);
                if (strpos($entry,'..') !== false) continue;
                $dest = $target_dir . $entry;
                $dpath = dirname($dest);
                if (!is_dir($dpath)) @mkdir($dpath, 0755, true);
                $stream = $zip->getStream($entry);
                if (!$stream) continue;
                $out = fopen($dest,'w');
                while (!feof($stream)) fwrite($out, fread($stream,8192));
                fclose($out);
                fclose($stream);
            }
            $zip->close();
            @unlink($tmp);
        }

        // Handle multiple files input assets[]
        if (!empty($_FILES['assets'])) {
            $files = $_FILES['assets'];
            for ($i=0;$i<count($files['name']);$i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $name = $files['name'][$i];
                $tmpname = $files['tmp_name'][$i];
                $dest = $target_dir . basename($name);
                move_uploaded_file($tmpname, $dest);
            }
        }

        // register all files under target_dir to addon_assets
        $added = 0;
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target_dir));
        foreach ($rii as $file) {
            if ($file->isDir()) continue;
            $rel = $target_dir_rel . '/' . substr($file->getPathname(), strlen($target_dir));
            $asset_payload = [
                'addon_id' => $addon_id,
                'filename' => $file->getBasename(),
                'path' => $rel,
                'mime' => mime_content_type($file->getPathname()),
                'size' => $file->getSize(),
                'uploaded_by' => $this->current_user['id']
            ];
            $this->addons_model->ensure_asset_record($asset_payload);
            $added++;
        }

        $this->session->set_flashdata('success','Assets uploaded/registered. ('.$added.' files)');
        redirect('addons');
    }

    /**
     * preview - show snippet raw or a message if snippet not present
     */
    public function preview($id = null)
    {
        if (!$id) show_404();
        $addon = $this->addons_model->get_addon($id);
        if (!$addon) show_404();

        $snippet_url = null;
        if (!empty($addon['snippet_path']) && file_exists(FCPATH . $addon['snippet_path'])) {
            $snippet_url = base_url($addon['snippet_path']);
            $html = file_get_contents(FCPATH . $addon['snippet_path']);
        } else {
            $html = '<div style="padding:16px;background:#fff;border-radius:8px">Snippet not uploaded for this addon.</div>';
        }

        $data = ['title' => 'Addon Preview', 'html' => $html, 'addon' => $addon, 'snippet_url' => $snippet_url];
        // direct output: show in iframe (no layout) to prevent cross-slot issues.
        $this->load->view('preview', $data);
    }

    /**
     * API endpoint - return addons data for a given template slug
     * Output: JSON { addons: { <slug>: {id,name,settings,...,assets:[]}}, ... }
     *
     * This endpoint is used by Templates preview_render to merge addon data server-side.
     */
    public function api_get_by_template_slug($template_slug = null)
    {
        // allow unauthenticated? We'll allow public read (no sensitive data)
        if (empty($template_slug)) {
            $this->output->set_content_type('application/json')->set_output(json_encode(['error'=>'missing_template_slug']));
            return;
        }

        // Find template by slug to get id
        $tpl = $this->db->query("SELECT id FROM templates WHERE slug = ? LIMIT 1", array($template_slug))->row_array();
        if (!$tpl) {
            $this->output->set_content_type('application/json')->set_output(json_encode(['error'=>'template_not_found']));
            return;
        }
        $template_id = $tpl['id'];

        // get addons for this template (both global (event_id null) and those linked to this template)
        $addons = $this->addons_model->get_addons_for_template($template_id);

        $out = ['addons' => []];
        foreach ($addons as $a) {
            // get assets
            $assets = $this->addons_model->get_assets_by_addon($a['id']);
            $a['assets'] = $assets;
            $out['addons'][$a['slug']] = $a;
        }

        $this->output->set_content_type('application/json')->set_output(json_encode($out));
    }

    /* ---------------- Helpers ---------------- */
    private function rrmdir_all($dir)
    {
        if (!is_dir($dir)) return;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }
        @rmdir($dir);
    }
}
