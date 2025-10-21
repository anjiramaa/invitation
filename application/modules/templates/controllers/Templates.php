<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Templates Controller
 * Version: 2025-10-18_v2
 *
 * - Added: edit_meta() endpoint to edit placeholders & sample JSON
 * - Added: preview_render() server-side preview rendering (reads index.html from template folder,
 *          supports {{include:relative/path}} directive and then runs placeholder replacement)
 * - Updated: create() form supports placeholders & sample_json fields (POST)
 *
 * Important:
 * - This file is self-contained and ready to copy-paste.
 * - Debug messages are only shown/logged when ENVIRONMENT === 'development'
 */

class Templates extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        // Ensure authentication
        $this->check_role_or_die(['super_admin','admin','client','staff']);

        $this->load->model('templates_model');
        $this->load->helper(['url','form','file','template_helper']);
        $this->load->library('upload');

        // Log debug if in development
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            log_message('debug', 'Templates controller initialized - version 2025-10-18_v2');
        }
    }

    /**
     * index - list templates
     */
    public function index()
    {
        $user = $this->current_user;
        $templates = $this->templates_model->get_templates_for_user($user);

        $data = [
            'title' => 'Templates',
            'templates' => $templates,
            'current_user' => $user
        ];

        $this->load->view('list', $data);
    }

    /**
     * create - GET show form / POST save metadata including placeholders & sample_json
     */
    public function create()
    {
        if ($this->input->method() !== 'post') {
            $data = ['title' => 'Create Template', 'current_user' => $this->current_user];
            $this->load->view('create', $data);
            return;
        }

        // POST
        $name = $this->input->post('template_name', TRUE);
        $slug = $this->input->post('slug', TRUE);
        $template_type = $this->input->post('template_type', TRUE) === 'pdf' ? 'pdf' : 'web';
        $placeholders = $this->input->post('placeholders', TRUE); // JSON string
        $sample_json = $this->input->post('sample_json', TRUE); // JSON string

        if (empty($name)) {
            $this->session->set_flashdata('error', 'Nama template wajib diisi.');
            redirect('templates/create');
            return;
        }

        // normalize slug
        if (empty($slug)) $slug = url_title($name, '-', TRUE);
        else $slug = url_title($slug, '-', TRUE);

        if ($this->templates_model->slug_exists($slug)) {
            $slug .= '-' . time();
        }

        $payload = [
            'template_name' => $name,
            'slug' => $slug,
            'template_type' => $template_type,
            'created_by' => $this->current_user['id'],
            'is_active' => 1,
            'version' => 1,
            'placeholders' => $placeholders ? $placeholders : null,
            'sample_json' => $sample_json ? $sample_json : null
        ];

        $new_id = $this->templates_model->create_template_metadata_with_meta($payload);
        if ($new_id) {
            $this->session->set_flashdata('success', 'Template metadata dibuat. Silakan unggah file ZIP template.');
            redirect('templates/upload_zip/' . $new_id);
        } else {
            $this->session->set_flashdata('error', 'Gagal membuat template metadata.');
            redirect('templates/create');
        }
    }

    /**
     * edit_meta - edit placeholders & sample_json for a template
     * GET: show form
     * POST: save changes
     */
    public function edit_meta($id = null)
    {
        if (!$id) show_404();
        $template = $this->templates_model->get_template_by_id($id);
        if (!$template) show_404();
        if (!$this->templates_model->can_edit($template, $this->current_user)) show_error('Forbidden', 403);

        if ($this->input->method() !== 'post') {
            $data = ['title' => 'Edit Template Meta', 'template' => $template, 'current_user' => $this->current_user];
            $this->load->view('edit_meta', $data);
            return;
        }

        $placeholders = $this->input->post('placeholders', TRUE);
        $sample_json = $this->input->post('sample_json', TRUE);

        $ok = $this->templates_model->update_placeholders_sample($id, $placeholders, $sample_json, $this->current_user);
        if ($ok) {
            $this->session->set_flashdata('success', 'Metadata template berhasil diperbarui.');
            redirect('templates');
        } else {
            $this->session->set_flashdata('error', 'Gagal memperbarui metadata template.');
            redirect('templates/edit_meta/' . $id);
        }
    }

    /**
     * preview_render - server-side rendering for preview
     * - POST: sample_json (optional) -> returns rendered HTML
     * - This reads index.html from template_file folder, resolves includes, then runs placeholder replacement.
     * - Response: text/html (rendered)
     */
    public function preview_render($id = null)
    {
        if (!$id) show_404();
        $template = $this->templates_model->get_template_by_id($id);
        if (!$template) show_404();

        // security: ensure template_file exists
        if (empty($template['template_file'])) {
            show_error('Template file belum di-upload.', 400);
        }

        $folder = FCPATH . $template['template_file'] . '/';
        if (!is_dir($folder)) show_error('Template folder tidak ditemukan di server.', 404);

        // determine index.html
        $indexCandidates = ['index.html','index.htm','landing.html'];
        $indexFile = null;
        foreach ($indexCandidates as $c) {
            if (file_exists($folder . $c)) {
                $indexFile = $folder . $c;
                break;
            }
        }
        if (!$indexFile) {
            // fallback to any html
            $htmls = glob($folder . '*.html');
            if (!empty($htmls)) $indexFile = $htmls[0];
        }
        if (!$indexFile) show_error('Tidak ada file HTML (index.html) dalam folder template.', 404);

        // sample data from POST overrides stored sample_json
        $post_sample = $this->input->post('sample_json', TRUE);
        $sampleData = [];
        if (!empty($post_sample)) {
            $decoded = json_decode($post_sample, true);
            if (is_array($decoded)) $sampleData = $decoded;
        } elseif (!empty($template['sample_json'])) {
            $decoded = json_decode($template['sample_json'], true);
            if (is_array($decoded)) $sampleData = $decoded;
        }

        // Read HTML
        $rawHtml = file_get_contents($indexFile);

        // Process includes: resolve {{include:relative/path}} directive and embed included file content
        $processedHtml = process_template_includes($rawHtml, $folder, $sampleData);

        // Now run placeholder renderer (escape-safe)
        $rendered = render_template_html($processedHtml, $sampleData);

        // Output as HTML
        $this->output->set_content_type('text/html')->set_output($rendered);
    }

    /* ---- other existing methods left unchanged (upload_zip, preview, download_asset, delete) ---- */
    // For brevity these methods are identical to previous implementation (upload_zip, preview, download_asset, delete)
    // but since the requirement is to provide full files, we include them here as well.

    /**
     * upload_zip - handle zip upload and extraction for a template (template_id)
     */
    public function upload_zip($template_id = null)
    {
        if (!$template_id) show_404();
        $template = $this->templates_model->get_template_by_id($template_id);
        if (!$template) show_404();
        if (!$this->templates_model->can_edit($template, $this->current_user)) show_error('Forbidden', 403);

        if ($this->input->method() !== 'post') {
            $data = ['title' => 'Upload Template ZIP', 'template' => $template, 'current_user' => $this->current_user];
            $this->load->view('upload_zip', $data);
            return;
        }

        if (empty($_FILES['template_zip']) || $_FILES['template_zip']['error'] !== UPLOAD_ERR_OK) {
            $this->session->set_flashdata('error', 'File ZIP tidak ditemukan atau gagal diupload.');
            redirect('templates/upload_zip/'.$template_id);
            return;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['template_zip']['tmp_name']);
        $ext = pathinfo($_FILES['template_zip']['name'], PATHINFO_EXTENSION);
        if ($mime !== 'application/zip' && strtolower($ext) !== 'zip') {
            $this->session->set_flashdata('error', 'File harus berupa ZIP.');
            redirect('templates/upload_zip/'.$template_id);
            return;
        }

        $slug = $template['slug'];
        if (empty($slug)) $slug = 'template-' . $template_id;
        $target_dir_rel = 'assets/templates_files/' . $slug;
        $target_dir = FCPATH . $target_dir_rel . '/';

        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                $this->session->set_flashdata('error', 'Gagal membuat folder template di server.');
                redirect('templates/upload_zip/'.$template_id);
                return;
            }
        } else {
            // clear dir (keep root)
            $this->rrmdir_keep_dir($target_dir);
        }

        $tmp_zip_path = sys_get_temp_dir() . '/template_upload_' . uniqid() . '.zip';
        if (!move_uploaded_file($_FILES['template_zip']['tmp_name'], $tmp_zip_path)) {
            $this->session->set_flashdata('error', 'Gagal memindahkan file upload.');
            redirect('templates/upload_zip/'.$template_id);
            return;
        }

        $zip = new ZipArchive();
        $res = $zip->open($tmp_zip_path);
        if ($res !== TRUE) {
            @unlink($tmp_zip_path);
            $this->session->set_flashdata('error', 'Gagal membuka ZIP file (err '.$res.'). Pastikan file ZIP valid.');
            redirect('templates/upload_zip/'.$template_id);
            return;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry_name = $zip->getNameIndex($i);
            $entry_name = str_replace('\\', '/', $entry_name);
            if (strpos($entry_name, '..') !== false) continue;
            $dest_path = $target_dir . $entry_name;
            $dest_dir = dirname($dest_path);
            if (!is_dir($dest_dir)) @mkdir($dest_dir, 0755, true);
            $stream = $zip->getStream($entry_name);
            if (!$stream) continue;
            $out_fp = fopen($dest_path, 'w');
            while (!feof($stream)) {
                fwrite($out_fp, fread($stream, 8192));
            }
            fclose($out_fp);
            fclose($stream);
            @chmod($dest_path, 0644);
        }

        $zip->close();
        @unlink($tmp_zip_path);

        // find thumbnail
        $thumb_candidates = ['thumbnail.jpg','thumbnail.jpeg','thumbnail.png','thumbnail.webp','thumb.jpg','thumb.png'];
        $found_thumb = null;
        foreach ($thumb_candidates as $c) {
            if (file_exists($target_dir . $c)) {
                $found_thumb = $target_dir_rel . '/' . $c;
                break;
            }
        }
        if (!$found_thumb) {
            $images = glob($target_dir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);
            if (!empty($images)) $found_thumb = $target_dir_rel . '/' . basename($images[0]);
        }

        // determine index html
        $index_candidates = ['index.html','index.htm','landing.html'];
        $index_found = null;
        foreach ($index_candidates as $ic) {
            if (file_exists($target_dir . $ic)) {
                $index_found = $target_dir_rel . '/' . $ic;
                break;
            }
        }
        if (!$index_found) {
            $htmls = glob($target_dir . '*.html');
            if (!empty($htmls)) $index_found = $target_dir_rel . '/' . basename($htmls[0]);
        }

        // register assets
        $files = $this->scan_files_recursive($target_dir);
        foreach ($files as $f) {
            $rel = $target_dir_rel . '/' . substr($f, strlen($target_dir));
            $rel = preg_replace('#/+/#','/',$rel);
            $asset_payload = [
                'template_id' => $template_id,
                'filename' => basename($f),
                'path' => $rel,
                'mime' => mime_content_type($f),
                'size' => filesize($f),
                'uploaded_by' => $this->current_user['id']
            ];
            $this->templates_model->ensure_asset_record($asset_payload);
        }

        $update_payload = [
            'template_file' => $target_dir_rel,
            'thumbnail_image' => $found_thumb,
            'html_content' => null
        ];
        $ok = $this->templates_model->update_template_file($template_id, $update_payload, $this->current_user);

        if ($ok) {
            $this->session->set_flashdata('success', 'Template berhasil diunggah dan diekstrak.');
            redirect('templates');
        } else {
            $this->session->set_flashdata('error', 'Gagal menyimpan info template.');
            redirect('templates/upload_zip/'.$template_id);
        }
    }

    /**
     * preview (old) - show iframe container that uses preview_render via fetch to fill srcdoc
     */
    public function preview($id = null)
    {
        if (!$id) show_404();
        $template = $this->templates_model->get_template_by_id($id);
        if (!$template) show_404();

        $data = [
            'title' => 'Preview Template',
            'template' => $template,
            'preview_url' => null
        ];
        $this->load->view('preview_iframe', $data);
    }

    /**
     * download_asset - serve asset file through PHP readfile
     */
    public function download_asset($asset_id = null)
    {
        if (!$asset_id) show_404();
        $asset = $this->templates_model->get_asset_by_id($asset_id);
        if (!$asset) show_404();
        $file_path = FCPATH . $asset['path'];
        if (!file_exists($file_path)) show_404();
        force_download(basename($file_path), file_get_contents($file_path));
    }

    /**
     * delete - delete template metadata and folder
     */
    public function delete($id = null)
    {
        if (!$id) show_404();
        $template = $this->templates_model->get_template_by_id($id);
        if (!$template) show_404();
        if (!$this->templates_model->can_edit($template, $this->current_user)) show_error('Forbidden', 403);
        if (!empty($template['template_file'])) {
            $folder = FCPATH . $template['template_file'] . '/';
            $this->rrmdir_all($folder);
        }
        $ok = $this->templates_model->delete_template($id, $this->current_user);
        if ($ok) $this->session->set_flashdata('success','Template dihapus.');
        else $this->session->set_flashdata('error','Gagal menghapus template.');
        redirect('templates');
    }

    /* ---------------- Helpers (private) ---------------- */

    private function scan_files_recursive($dir)
    {
        $result = [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($rii as $file) {
            if ($file->isDir()) continue;
            $result[] = $file->getPathname();
        }
        return $result;
    }

    private function rrmdir_keep_dir($dir)
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
    }

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
