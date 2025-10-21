<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dashboard_model
 * Version: 2025-10-14_v2
 * - Menyesuaikan counts berdasarkan schema baru
 * - Admin hanya melihat data untuk clients under him
 */

class Dashboard_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * get_summary_for_user
     * - role_key: super_admin/admin/client/staff
     * - user_id: current user
     */
    public function get_summary_for_user($role_key, $user_id)
    {
        $summary = [
            'events_count' => 0,
            'guests_today' => 0,
            'checkins_today' => 0,
            'pending_transactions' => 0
        ];

        if ($role_key === 'super_admin') {
            $q = $this->db->query("SELECT COUNT(*) AS cnt FROM events");
            $summary['events_count'] = (int)$q->row()->cnt;

            $q2 = $this->db->query("SELECT COUNT(*) AS cnt FROM guests WHERE DATE(created_at) = CURDATE()");
            $summary['guests_today'] = (int)$q2->row()->cnt;

            $q3 = $this->db->query("SELECT COUNT(*) AS cnt FROM checkins WHERE DATE(checked_at) = CURDATE()");
            $summary['checkins_today'] = (int)$q3->row()->cnt;

            $q4 = $this->db->query("SELECT COUNT(*) AS cnt FROM transactions WHERE status = 'pending'");
            $summary['pending_transactions'] = (int)$q4->row()->cnt;
        } elseif ($role_key === 'admin') {
            // get client ids under this admin
            $qclients = $this->db->query("SELECT id FROM users WHERE parent_admin_id = ? AND role_id = (SELECT id FROM roles WHERE role_key = 'client' LIMIT 1)", array($user_id));
            $clients = array_map(function($r){ return $r['id']; }, $qclients->result_array());
            if (empty($clients)) {
                return $summary;
            }
            // prepare IN clause safely using query binding for each value
            $placeholders = implode(',', array_fill(0, count($clients), '?'));
            $params = $clients;

            $sqlE = "SELECT COUNT(*) AS cnt FROM events WHERE client_owner_id IN ($placeholders)";
            $q = $this->db->query($sqlE, $params);
            $summary['events_count'] = (int)$q->row()->cnt;

            $sqlG = "SELECT COUNT(g.id) AS cnt FROM guests g JOIN events e ON g.event_id = e.id WHERE e.client_owner_id IN ($placeholders) AND DATE(g.created_at) = CURDATE()";
            $q2 = $this->db->query($sqlG, $params);
            $summary['guests_today'] = (int)$q2->row()->cnt;

            $sqlC = "SELECT COUNT(ch.id) AS cnt FROM checkins ch JOIN events e ON ch.event_id = e.id WHERE e.client_owner_id IN ($placeholders) AND DATE(ch.checked_at) = CURDATE()";
            $q3 = $this->db->query($sqlC, $params);
            $summary['checkins_today'] = (int)$q3->row()->cnt;

            $sqlT = "SELECT COUNT(tx.id) AS cnt FROM transactions tx JOIN events e ON tx.event_id = e.id WHERE e.client_owner_id IN ($placeholders) AND tx.status = 'pending'";
            $q4 = $this->db->query($sqlT, $params);
            $summary['pending_transactions'] = (int)$q4->row()->cnt;

        } elseif ($role_key === 'client') {
            // events owned by this client (client_owner_id)
            $q = $this->db->query("SELECT COUNT(*) AS cnt FROM events WHERE client_owner_id = ?", array($user_id));
            $summary['events_count'] = (int)$q->row()->cnt;

            $q2 = $this->db->query("SELECT COUNT(g.id) AS cnt FROM guests g JOIN events e ON g.event_id = e.id WHERE e.client_owner_id = ? AND DATE(g.created_at) = CURDATE()", array($user_id));
            $summary['guests_today'] = (int)$q2->row()->cnt;

            $q3 = $this->db->query("SELECT COUNT(ch.id) AS cnt FROM checkins ch JOIN events e ON ch.event_id = e.id WHERE e.client_owner_id = ? AND DATE(ch.checked_at) = CURDATE()", array($user_id));
            $summary['checkins_today'] = (int)$q3->row()->cnt;

            $q4 = $this->db->query("SELECT COUNT(*) AS cnt FROM transactions WHERE user_id = ? AND status = 'pending'", array($user_id));
            $summary['pending_transactions'] = (int)$q4->row()->cnt;
        } else {
            // staff: show assigned events count today and checkins performed by staff
            $q = $this->db->query("SELECT COUNT(sea.id) AS cnt FROM staff_event_assignments sea WHERE sea.staff_user_id = ? AND DATE(sea.created_at) <= CURDATE()", array($user_id));
            $summary['events_count'] = (int)$q->row()->cnt;

            $q2 = $this->db->query("SELECT COUNT(ch.id) AS cnt FROM checkins ch WHERE ch.checked_by_user_id = ? AND DATE(ch.checked_at) = CURDATE()", array($user_id));
            $summary['checkins_today'] = (int)$q2->row()->cnt;
        }

        return $summary;
    }
}
