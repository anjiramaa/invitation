<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Addons_model
 * Version: 2025-10-19_v1
 *
 * - CRUD for addons
 * - asset registration
 * - helper retrieval functions to be used by Templates preview renderer
 */

class Addons_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * get_all_addons_for_user
     * - super_admin/admin: return all
     * - client/staff: return addons created by them OR global (event_id IS NULL) OR those for events they own
     */
    public function get_all_addons_for_user($user)
    {
        if (in_array($user['role_key'], ['super_admin','admin'])) {
            $sql = "SELECT * FROM addons ORDER BY created_at DESC";
            $q = $this->db->query($sql);
            return $q->result_array();
        }

        // For client/staff: show global and own created
        $sql = "SELECT * FROM addons WHERE (event_id IS NULL) OR (created_by = ?) ORDER BY created_at DESC";
        $q = $this->db->query($sql, array($user['id']));
        return $q->result_array();
    }

    /**
     * slug_exists (unique per event)
     */
    public function slug_exists($slug, $event_id = null)
    {
        $sql = "SELECT id FROM addons WHERE slug = ? AND (event_id <=> ?) LIMIT 1";
        $q = $this->db->query($sql, array($slug, $event_id));
        return $q->num_rows() > 0;
    }

    /**
     * create_addon
     */
    public function create_addon($payload)
    {
        $sql = "INSERT INTO addons (slug, name, type, event_id, template_id, settings, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $this->db->query($sql, array(
            $payload['slug'],
            $payload['name'],
            $payload['type'],
            isset($payload['event_id']) ? $payload['event_id'] : null,
            isset($payload['template_id']) ? $payload['template_id'] : null,
            isset($payload['settings']) ? $payload['settings'] : null,
            isset($payload['created_by']) ? $payload['created_by'] : null
        ));
        return $this->db->insert_id();
    }

    /**
     * get_addon
     */
    public function get_addon($id)
    {
        $sql = "SELECT * FROM addons WHERE id = ? LIMIT 1";
        $q = $this->db->query($sql, array($id));
        return $q->row_array();
    }

    /**
     * update_addon
     */
    public function update_addon($id, $payload)
    {
        $sql = "UPDATE addons SET name = ?, slug = ?, type = ?, settings = ?, snippet_path = ?, updated_at = NOW() WHERE id = ?";
        $this->db->query($sql, array(
            isset($payload['name']) ? $payload['name'] : null,
            isset($payload['slug']) ? $payload['slug'] : null,
            isset($payload['type']) ? $payload['type'] : null,
            isset($payload['settings']) ? $payload['settings'] : null,
            isset($payload['snippet_path']) ? $payload['snippet_path'] : null,
            $id
        ));
        return $this->db->affected_rows() >= 0;
    }

    /**
     * delete_addon
     */
    public function delete_addon($id)
    {
        // delete assets rows first
        $this->db->query("DELETE FROM addon_assets WHERE addon_id = ?", array($id));
        $this->db->query("DELETE FROM addons WHERE id = ?", array($id));
        return $this->db->affected_rows() > 0;
    }

    /**
     * ensure_asset_record - insert asset if not exists
     */
    public function ensure_asset_record($payload)
    {
        $sql = "SELECT id FROM addon_assets WHERE addon_id = ? AND path = ? LIMIT 1";
        $q = $this->db->query($sql, array($payload['addon_id'], $payload['path']));
        if ($q->num_rows() > 0) return $q->row()->id;

        $ins = "INSERT INTO addon_assets (addon_id, filename, path, mime, size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $this->db->query($ins, array($payload['addon_id'], $payload['filename'], $payload['path'], $payload['mime'], $payload['size'], $payload['uploaded_by']));
        return $this->db->insert_id();
    }

    /**
     * get_assets_by_addon
     */
    public function get_assets_by_addon($addon_id)
    {
        $sql = "SELECT * FROM addon_assets WHERE addon_id = ? ORDER BY created_at ASC";
        $q = $this->db->query($sql, array($addon_id));
        $rows = $q->result_array();
        foreach ($rows as &$r) {
            // convert to public url if file exists
            if (!empty($r['path']) && file_exists(FCPATH . $r['path'])) {
                $r['url'] = base_url($r['path']);
            } else {
                $r['url'] = null;
            }
        }
        return $rows;
    }

    /**
     * get_addons_for_template
     * - returns addons applicable for a template id: those with template_id == template_id OR global (event_id IS NULL) OR those linked to events using this template (complex, simplified here)
     * - For now: return addons where template_id = given OR event_id IS NULL OR addon.template_id IS NULL (global). This suffices for preview merging, but can be extended.
     */
    public function get_addons_for_template($template_id)
    {
        $sql = "SELECT * FROM addons WHERE (template_id = ? OR template_id IS NULL) AND is_active = 1 ORDER BY created_at DESC";
        $q = $this->db->query($sql, array($template_id));
        return $q->result_array();
    }

    /**
     * can_edit - permission check
     */
    public function can_edit($addon, $user)
    {
        if (empty($user) || empty($addon)) return false;
        if (in_array($user['role_key'], ['super_admin','admin'])) return true;
        if ($user['role_key'] === 'client') {
            if (!empty($addon['created_by']) && $addon['created_by'] == $user['id']) return true;
        }
        return false;
    }
}
