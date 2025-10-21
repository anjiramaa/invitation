<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Templates_model
 * Version: 2025-10-18_v2
 *
 * - Added: create_template_metadata_with_meta (saves placeholders/sample_json)
 * - Added: update_placeholders_sample
 * - Uses safe query binding
 */

class Templates_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_templates_for_user($user)
    {
        if (empty($user)) return [];
        if (in_array($user['role_key'], ['super_admin','admin'])) {
            $sql = "SELECT t.* FROM templates t ORDER BY t.created_at DESC";
            $q = $this->db->query($sql);
            return $q->result_array();
        } else {
            $sql = "SELECT t.* FROM templates t WHERE (t.owner_id = ? AND t.owner_role = 'client') OR t.is_active = 1 ORDER BY t.created_at DESC";
            $q = $this->db->query($sql, array($user['id']));
            return $q->result_array();
        }
    }

    public function slug_exists($slug)
    {
        $sql = "SELECT id FROM templates WHERE slug = ? LIMIT 1";
        $q = $this->db->query($sql, array($slug));
        return $q->num_rows() > 0;
    }

    /**
     * create_template_metadata_with_meta
     * - Inserts metadata including placeholders & sample_json
     */
    public function create_template_metadata_with_meta($payload)
    {
        $sql = "INSERT INTO templates (template_name, slug, template_type, created_by, is_active, version, placeholders, sample_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $this->db->query($sql, array(
            $payload['template_name'],
            $payload['slug'],
            isset($payload['template_type']) ? $payload['template_type'] : 'web',
            isset($payload['created_by']) ? $payload['created_by'] : null,
            isset($payload['is_active']) ? $payload['is_active'] : 1,
            isset($payload['version']) ? $payload['version'] : 1,
            isset($payload['placeholders']) ? $payload['placeholders'] : null,
            isset($payload['sample_json']) ? $payload['sample_json'] : null
        ));
        return $this->db->insert_id();
    }

    public function update_template_file($id, $payload, $actor = null)
    {
        $sql = "UPDATE templates SET template_file = ?, thumbnail_image = ?, html_content = ?, updated_at = NOW() WHERE id = ?";
        $this->db->query($sql, array(
            isset($payload['template_file']) ? $payload['template_file'] : null,
            isset($payload['thumbnail_image']) ? $payload['thumbnail_image'] : null,
            isset($payload['html_content']) ? $payload['html_content'] : null,
            $id
        ));
        return $this->db->affected_rows() >= 0;
    }

    public function get_template_by_id($id)
    {
        $sql = "SELECT t.*, u.id as created_user_id, r.role_key as created_by_role
                FROM templates t
                LEFT JOIN users u ON u.id = t.created_by
                LEFT JOIN roles r ON r.id = u.role_id
                WHERE t.id = ? LIMIT 1";
        $q = $this->db->query($sql, array($id));
        $row = $q->row_array();
        if ($row) {
            if (!empty($row['placeholders'])) {
                $row['placeholders'] = json_decode($row['placeholders'], true);
            } else {
                $row['placeholders'] = [];
            }
            if (!empty($row['sample_json'])) {
                $row['sample_json_decoded'] = json_decode($row['sample_json'], true);
            } else {
                $row['sample_json_decoded'] = [];
            }
        }
        return $row;
    }

    public function can_edit($template, $user)
    {
        if (empty($user)) return false;
        if (in_array($user['role_key'], ['super_admin','admin'])) return true;
        if ($user['role_key'] === 'client') {
            if (!empty($template['owner_id']) && $template['owner_id'] == $user['id']) return true;
            if (!empty($template['created_by']) && $template['created_by'] == $user['id']) return true;
        }
        return false;
    }

    public function ensure_asset_record($payload)
    {
        $sql = "SELECT id FROM template_assets WHERE template_id = ? AND path = ? LIMIT 1";
        $q = $this->db->query($sql, array($payload['template_id'], $payload['path']));
        if ($q->num_rows() > 0) return $q->row()->id;
        $ins = "INSERT INTO template_assets (template_id, filename, path, mime, size, uploaded_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $this->db->query($ins, array($payload['template_id'], $payload['filename'], $payload['path'], $payload['mime'], $payload['size'], $payload['uploaded_by']));
        return $this->db->insert_id();
    }

    public function get_asset_by_id($id)
    {
        $sql = "SELECT * FROM template_assets WHERE id = ? LIMIT 1";
        $q = $this->db->query($sql, array($id));
        return $q->row_array();
    }

    public function delete_template($id, $actor)
    {
        $sql = "DELETE FROM templates WHERE id = ?";
        $this->db->query($sql, array($id));
        return $this->db->affected_rows() > 0;
    }

    /**
     * update_placeholders_sample
     */
    public function update_placeholders_sample($id, $placeholders_json, $sample_json, $actor = null)
    {
        $sql = "UPDATE templates SET placeholders = ?, sample_json = ?, updated_at = NOW() WHERE id = ?";
        $this->db->query($sql, array(
            $placeholders_json ? $placeholders_json : null,
            $sample_json ? $sample_json : null,
            $id
        ));
        return $this->db->affected_rows() >= 0;
    }
}
