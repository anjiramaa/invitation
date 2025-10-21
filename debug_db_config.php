<?php
/**
 * debug_db_config.php
 *
 * Cara pakai:
 * 1) Simpan di root project (satu level dengan index.php)
 * 2) Jalankan via CLI:
 *      php debug_db_config.php
 *    atau buka di browser:
 *      http://localhost/your-project/debug_db_config.php
 *
 * Apa yang dilakukan:
 * - Mencari file bernama 'database.php' dalam folder application/config dan application/modules
 * - Menampilkan realpath, readable flag, filesize, dan potongan isi file (escaped)
 * - Mencari keberadaan $active_group dan $db[...] via regex (pemeriksaan statis)
 *
 * NOTE: script ini TIDAK meng-include file apapun (aman).
 */

set_time_limit(30);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = realpath(__DIR__);
echo "Project root: {$root}\n\n";

$application_dir = $root . DIRECTORY_SEPARATOR . 'application';
if (!is_dir($application_dir)) {
    echo "ERROR: Folder 'application' tidak ditemukan di root: {$application_dir}\n";
    exit(1);
}

echo "Inspecting application folder: {$application_dir}\n\n";

// helper: recursive find files by basename (case-insensitive)
function find_files_by_basename($root, $basename) {
    $found = [];
    if (!is_dir($root)) return $found;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $f) {
        if ($f->isFile()) {
            if (strcasecmp($f->getBasename(), $basename) === 0) {
                $found[] = $f->getPathname();
            }
        }
    }
    return $found;
}

// 1) show content listing of application/config (non-recursive)
$config_dir = $application_dir . DIRECTORY_SEPARATOR . 'config';
echo "Top-level config folder: {$config_dir}\n";
if (is_dir($config_dir)) {
    $items = scandir($config_dir);
    echo "Top-level contents of application/config:\n";
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $p = $config_dir . DIRECTORY_SEPARATOR . $it;
        $type = is_dir($p) ? 'DIR ' : 'FILE';
        echo "  - {$type} : {$it}\n";
    }
} else {
    echo "  -> application/config DOES NOT EXIST (check path)\n";
}
echo "\n";

// 2) Search for database.php under application/config and application/modules (recursive)
$candidates = [];

// check environment-specific folder(s) commonly used
$envFolders = [];
// include direct ENV folder if exists
$possibleEnvPaths = glob($config_dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
foreach ($possibleEnvPaths as $p) {
    // add all subdirs of application/config (like 'development', 'production', etc.)
    $envFolders[] = $p;
}

echo "Detected subfolders under application/config (possible env folders):\n";
foreach ($envFolders as $e) {
    echo "  - " . $e . "\n";
}
echo "\n";

// find database.php anywhere under application/config (recursive)
echo "Searching for files named 'database.php' under application/config (recursive)...\n";
$candidates = find_files_by_basename($config_dir, 'database.php');
if (empty($candidates)) {
    echo "  -> No 'database.php' found under application/config\n";
} else {
    echo "  -> Found " . count($candidates) . " candidate(s):\n";
    foreach ($candidates as $c) echo "     - {$c}\n";
}
echo "\n";

// also search under application/modules (HMVC)
$modules_dir = $application_dir . DIRECTORY_SEPARATOR . 'modules';
echo "Searching for 'database.php' under application/modules (recursive)...\n";
$mod_candidates = [];
if (is_dir($modules_dir)) {
    $mod_candidates = find_files_by_basename($modules_dir, 'database.php');
    if (empty($mod_candidates)) {
        echo "  -> No 'database.php' found under application/modules\n";
    } else {
        echo "  -> Found " . count($mod_candidates) . " candidate(s) under modules:\n";
        foreach ($mod_candidates as $c) echo "     - {$c}\n";
    }
} else {
    echo "  -> application/modules directory not present.\n";
}
echo "\n";

// merge all candidates
$all_candidates = array_merge($candidates, $mod_candidates);

// 3) Inspect each candidate: readable, size, snippet, check for $active_group and $db['...']
if (!empty($all_candidates)) {
    foreach ($all_candidates as $path) {
        echo "=== Candidate: {$path} ===\n";
        $real = @realpath($path);
        echo "realpath: " . ($real ? $real : 'N/A') . "\n";
        echo "exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";
        echo "is_readable: " . (is_readable($path) ? 'YES' : 'NO') . "\n";
        if (file_exists($path)) {
            echo "filesize: " . filesize($path) . " bytes\n";
            $content = @file_get_contents($path);
            if ($content === false) {
                echo " -> failed to read content via file_get_contents()\n";
            } else {
                // show first 1000 characters escaped for safety
                $snippet = mb_substr($content, 0, 1000);
                echo "---- top of file (escaped, first 1000 chars) ----\n";
                echo htmlspecialchars($snippet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
                echo "---- end snippet ----\n";

                // simple regex detection for $active_group and $db['something']
                $has_active_group = preg_match("/\\\$active_group\\s*=/", $content) ? 'YES' : 'NO';
                // find db array keys, e.g. $db['default']
                preg_match_all("/\\\$db\\s*\\[\\s*'([^']+)'\\s*\\]/", $content, $m);
                $db_keys = [];
                if (!empty($m) && !empty($m[1])) {
                    $db_keys = array_unique($m[1]);
                }
                echo "Has \$active_group assignment? {$has_active_group}\n";
                echo "Detected \$db keys: " . (empty($db_keys) ? 'NONE' : implode(',', $db_keys)) . "\n";
            }
        }
        echo "\n";
    }
} else {
    echo "No database.php candidates found in standard places.\n";
    echo "Additional check: listing all files named 'database.php' under project root (recursive)...\n";
    $all_project_candidates = find_files_by_basename($root, 'database.php');
    if (!empty($all_project_candidates)) {
        foreach ($all_project_candidates as $p) {
            echo "  - {$p}\n";
        }
    } else {
        echo "  -> NONE found in whole project.\n";
    }
    echo "\n";
}

echo "DIAGNOSTIC COMPLETE.\n";
