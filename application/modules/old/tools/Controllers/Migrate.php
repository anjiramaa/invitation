<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Module Controller: tools/Migrate
 * VERSION : 1.0.7-fix
 * DATE    : 2025-10-06
 * AUTHOR  : ChatGPT
 *
 * Debug intensive fixed version:
 * - Past issues: syntax/parens fixed.
 * - Functionality:
 *   - Find and load DB config if CI doesn't auto-load DB.
 *   - Use load->database(..., TRUE) to get DB object and assign to $this->db.
 *   - Dump diagnostics of $this->db before running migrations.
 *   - Run migrations->latest() and print debug lines.
 *
 * SECURITY: Only runs in ENVIRONMENT === 'development'
 */

class Migrate extends MX_Controller
{
    protected $debug_lines = array();

    public function __construct()
    {
        parent::__construct();

        if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'development') {
            show_error('Migrations can only be run in development environment.', 403);
            exit;
        }

        $this->_debug("Start Migrate (v1.0.7-fix) - debug DB object state before migration.");

        // 1) Try to get DB object via return-mode first
        $this->_debug("Attempt: \$this->load->database(NULL, TRUE)");
        try {
            $dbobj = $this->load->database(NULL, TRUE);
            if ($dbobj) {
                $this->db = $dbobj;
                $this->_debug(" -> load->database(NULL, TRUE) returned object and assigned to \$this->db");
            } else {
                $this->_debug(" -> load->database(NULL, TRUE) returned falsy");
            }
        } catch (Exception $e) {
            $this->_debug(" -> Exception during load->database(NULL, TRUE): " . $e->getMessage());
        }

        // 2) If not set, try explicit config-based load (reusing previous defensive routine)
        if (!isset($this->db) || $this->db === NULL) {
            $this->_debug("DB object not present yet. Attempt explicit config-based load and assign (return-mode).");
            $ok = $this->explicit_load_and_assign_db();
            if (!$ok) {
                $this->_debug("explicit load failed - will output debug and abort.");
                $this->output_debug_and_die();
            }
        }

        // 3) Now print full diagnostics about $this->db
        $this->diagnose_db_object();

        // 4) Try initialize() if method exists
        if (isset($this->db) && is_object($this->db)) {
            if (method_exists($this->db, 'initialize')) {
                $this->_debug("DB object has initialize() method. Attempting \$this->db->initialize() ...");
                try {
                    $init = $this->db->initialize();
                    $this->_debug(" -> initialize() returned: " . var_export($init, TRUE));
                } catch (Throwable $t) {
                    $this->_debug(" -> Throwable during initialize(): " . $t->getMessage());
                } catch (Exception $e) {
                    $this->_debug(" -> Exception during initialize(): " . $e->getMessage());
                }
            } else {
                $this->_debug("DB object DOES NOT have initialize() method.");
            }
        } else {
            $this->_debug("DB object not set or not an object; cannot call initialize().");
        }

        // 5) Now load migration library and proceed (we will still print debug)
        $this->_debug("Loading migration library now.");
        $this->load->library('migration');

        $this->_debug("Constructor done. Ready to run migrations via ->run()");
        $this->load->helper('url');
    }

    /**
     * Attempt to require config files and assign DB using return-mode.
     *
     * @return bool
     */
    protected function explicit_load_and_assign_db()
    {
        $this->_debug("explicit_load_and_assign_db: searching config files...");

        $candidates = array();
        if (defined('ENVIRONMENT') && ENVIRONMENT) {
            $candidates[] = APPPATH . 'config' . DIRECTORY_SEPARATOR . ENVIRONMENT . DIRECTORY_SEPARATOR . 'database.php';
        }
        $candidates[] = APPPATH . 'config' . DIRECTORY_SEPARATOR . 'database.php';

        // recursive finds
        $rec = $this->recursive_find_files(APPPATH . 'config', 'database.php');
        foreach ($rec as $r) {
            $candidates[] = $r;
        }
        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $cfg) {
            $this->_debug("Candidate: {$cfg}");
            if (!file_exists($cfg)) {
                $this->_debug(" -> file_exists: NO");
                continue;
            }

            $this->_debug(" -> file_exists: YES");
            $this->_debug(" -> is_readable: " . (is_readable($cfg) ? 'YES' : 'NO'));

            if (!is_readable($cfg)) {
                $this->_debug(" -> NOT readable, skip");
                continue;
            }

            // unset prior
            if (isset($active_group)) {
                unset($active_group);
            }
            if (isset($db)) {
                unset($db);
            }

            // require_once config
            try {
                require_once($cfg);
                $this->_debug(" -> require_once ok");
            } catch (Throwable $t) {
                $this->_debug(" -> Throwable during require: " . $t->getMessage());
                continue;
            } catch (Exception $e) {
                $this->_debug(" -> Exception during require: " . $e->getMessage());
                continue;
            }

            $ag = isset($active_group) ? $active_group : null;
            $db_keys = (isset($db) && is_array($db)) ? array_keys($db) : null;
            $this->_debug(" -> \$active_group: " . var_export($ag, TRUE));
            $this->_debug(" -> \$db keys: " . var_export($db_keys, TRUE));

            if ($ag !== null && isset($db[$ag]) && is_array($db[$ag])) {
                $params = $db[$ag];
                if (empty($params['dbdriver'])) {
                    $params['dbdriver'] = 'mysqli';
                }
                if (empty($params['char_set'])) {
                    $params['char_set'] = 'utf8';
                }
                if (empty($params['dbcollat'])) {
                    $params['dbcollat'] = 'utf8_general_ci';
                }

                $params_for_log = $params;
                if (isset($params_for_log['password'])) {
                    $params_for_log['password'] = '***';
                }

                $this->_debug(" -> Final params to pass to load->database (masked): " . var_export($params_for_log, TRUE));

                try {
                    $dbobj = $this->load->database($params, TRUE);
                    if ($dbobj) {
                        $this->db = $dbobj;
                        $this->_debug(" -> Assigned returned DB object to \$this->db");
                        return TRUE;
                    } else {
                        $this->_debug(" -> load->database returned falsy");
                    }
                } catch (Exception $e) {
                    $this->_debug(" -> Exception during load->database: " . $e->getMessage());
                }
            } else {
                $this->_debug(" -> No usable active_group/db entry in this file.");
            }
        }

        $this->_debug("explicit_load_and_assign_db: none succeeded.");
        return FALSE;
    }

    /**
     * Diagnose $this->db object state and print key fields.
     */
    protected function diagnose_db_object()
    {
        if (!isset($this->db)) {
            $this->_debug("DIAG: \$this->db is NOT set.");
            return;
        }
        $this->_debug("DIAG: \$this->db IS set.");
        $cls = is_object($this->db) ? get_class($this->db) : gettype($this->db);
        $this->_debug("DIAG: get_class(\$this->db) = " . $cls);

        $props = array('dbdriver', 'hostname', 'username', 'database', 'port', 'char_set', 'dbcollat');
        foreach ($props as $p) {
            $val = null;
            if (is_object($this->db) && (property_exists($this->db, $p) || isset($this->db->$p))) {
                $val = isset($this->db->$p) ? $this->db->$p : (property_exists($this->db, $p) ? $this->db->$p : null);
            }
            if ($p === 'password' && $val !== null) {
                $val = '***';
            }
            $this->_debug("DIAG: property '{$p}' => " . var_export($val, TRUE));
        }

        $this->_debug("DIAG: method_exists(\$this->db, 'initialize') = " . (method_exists($this->db, 'initialize') ? 'YES' : 'NO'));
        $this->_debug("DIAG: method_exists(\$this->db, 'db_connect') = " . (method_exists($this->db, 'db_connect') ? 'YES' : 'NO'));

        if (is_object($this->db)) {
            $public = array();
            foreach (get_object_vars($this->db) as $k => $v) {
                if (in_array($k, array('conn_id','result_id','result_array'))) {
                    continue;
                }
                $public[$k] = is_scalar($v) ? $v : gettype($v);
            }
            $this->_debug("DIAG: public object vars summary: " . var_export($public, TRUE));
        }
    }

    /**
     * Recursively find files with given basename under root.
     *
     * @param string $root
     * @param string $basename
     * @return array
     */
    protected function recursive_find_files($root, $basename)
    {
        $found = array();
        if (!is_dir($root)) {
            return $found;
        }
        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($it as $file) {
                if ($file->isFile() && strcasecmp($file->getBasename(), $basename) === 0) {
                    $found[] = $file->getPathname();
                }
            }
        } catch (Exception $e) {
            $this->_debug(" -> recursive_find_files exception: " . $e->getMessage());
        }
        return $found;
    }

    public function index()
    {
        $this->run();
    }

    public function run()
    {
        $this->_debug("About to run migrations->latest()");
        try {
            if ($this->migration->latest() === FALSE) {
                $err = $this->migration->error_string();
                $this->_debug("Migration failed: " . $err);
                $this->output_debug_and_die();
                return;
            }
            $this->_debug("Migrations run successfully to latest version.");
            $this->output_debug_and_die(FALSE);
        } catch (Throwable $t) {
            $this->_debug("Throwable during migration->latest(): " . $t->getMessage());
            $this->output_debug_and_die();
        } catch (Exception $e) {
            $this->_debug("Exception during migration->latest(): " . $e->getMessage());
            $this->output_debug_and_die();
        }
    }

    protected function _debug($line)
    {
        $this->debug_lines[] = date('[H:i:s] ') . $line;
    }

    protected function output_debug_and_die($dieAfter = TRUE)
    {
        if (is_cli()) {
            foreach ($this->debug_lines as $l) {
                echo $l . PHP_EOL;
            }
        } else {
            echo '<pre style="white-space:pre-wrap;word-wrap:break-word;">';
            foreach ($this->debug_lines as $l) {
                echo htmlentities($l) . PHP_EOL;
            }
            echo '</pre>';
        }
        if ($dieAfter) {
            exit;
        }
    }
}
