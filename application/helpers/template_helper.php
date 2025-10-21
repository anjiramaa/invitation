<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * template_helper
 * - render_template_html($html, $data)
 * - process_template_includes($html, $base_folder, $data)
 * - fetch_from_data($data, $key, $options = [])
 *
 * Safety & behavior:
 * - Includes are optional: missing include file becomes an HTML comment, not an error.
 * - Missing placeholders are replaced with empty string.
 * - Triple-brace {{{ key }}} outputs raw value (use cautiously).
 * - Arrays/objects default to empty string unless triple-brace is used; use helper to provide safe JSON if needed.
 * - Script tags are removed to reduce XSS in preview.
 *
 * Version: 2025-10-18_v2
 */

if (!function_exists('render_template_html')) {
    /**
     * Render template HTML safely by replacing placeholders.
     *
     * @param string $html Raw HTML content (may contain placeholders and include directives already resolved)
     * @param array $data Associative array used for placeholder replacement
     * @param bool $escape Whether to escape double-brace placeholders (default true)
     * @return string Rendered HTML
     */
    function render_template_html($html, $data = array(), $escape = true) {
        // Remove <script> tags entirely for preview safety
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);

        // Replace include directives if any remained (defensive)
        if (strpos($html, '{{include:') !== false) {
            // cannot resolve here because include requires base folder context;
            // process_template_includes should be used prior to this call when base folder known.
            // keep as-is (no-op) so next layer can handle or leave comment.
        }

        // Triple braces {{{ key }}} => raw (no escaping)
        $html = preg_replace_callback('/\{\{\{\s*([^\}]+)\s*\}\}\}/', function($m) use ($data) {
            $key = trim($m[1]);
            $val = fetch_from_data($data, $key, ['raw'=>true]);
            // If array/object returned as JSON string by fetch_from_data when raw=true, allow it.
            return is_scalar($val) ? $val : '';
        }, $html);

        // Double braces {{ key }} => escaped (or raw if escape=false)
        $html = preg_replace_callback('/\{\{\s*([^\}]+)\s*\}\}/', function($m) use ($data, $escape) {
            $key = trim($m[1]);
            $val = fetch_from_data($data, $key, ['raw'=>false]);
            if ($val === null || $val === '') return '';
            if ($escape) return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            return $val;
        }, $html);

        return $html;
    }
}

if (!function_exists('fetch_from_data')) {
    /**
     * Fetch nested value from $data by dot-notated key.
     *
     * Options:
     * - ['raw' => bool] : if raw=true and value is array/object, returns json-encoded string; if raw=false and value is array/object returns empty string.
     *
     * Return:
     * - scalar string/number : returned as-is (string)
     * - array/object : see options
     * - missing key : returns null
     *
     * @param array $data
     * @param string $key (dot notation)
     * @param array $options
     * @return mixed|null
     */
    function fetch_from_data($data, $key, $options = array()) {
        $opts = array_merge(['raw' => false], $options);
        if ($key === '' || $key === null) return null;

        // Support indexing like gallery.0 or addons.gallery.images.0
        $parts = preg_split('/\.(?![^\[]*\])/', $key); // basic split on dot, ignore if bracketed (not used now)
        $cur = $data;
        foreach ($parts as $p) {
            // Support numeric index if p is like "0" or "[0]"
            $p = trim($p);
            if ($p === '') return null;
            // array index support: if part contains [n], convert
            if (preg_match('/^([^\[]+)\[(\d+)\]$/', $p, $m)) {
                $pname = $m[1];
                $idx = (int)$m[2];
                if (!is_array($cur) || !array_key_exists($pname, $cur)) return null;
                $cur = $cur[$pname];
                if (!is_array($cur) || !array_key_exists($idx, $cur)) return null;
                $cur = $cur[$idx];
                continue;
            }
            // numeric index pure
            if (is_array($cur) && array_key_exists($p, $cur)) {
                $cur = $cur[$p];
                continue;
            } elseif (is_object($cur) && isset($cur->$p)) {
                $cur = $cur->$p;
                continue;
            } else {
                return null;
            }
        }

        // If value is array/object:
        if (is_array($cur) || is_object($cur)) {
            if ($opts['raw']) {
                // developer explicitly asked for raw JSON output
                $json = json_encode($cur, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                return $json !== false ? $json : '';
            }
            // default: treat array/object as empty string to avoid ugly prints
            return '';
        }

        // scalar value: cast to string
        return (string)$cur;
    }
}

if (!function_exists('process_template_includes')) {
    /**
     * Process include directives of form: {{include:relative/path.html}}
     * - base_folder must be full server path (trailing slash recommended)
     * - will sanitize relative path to avoid traversal
     * - included file content will be processed recursively for includes, then run through render_template_html using $data
     *
     * Behavior: if included file not found -> returns a safe HTML comment node (no error)
     *
     * @param string $html
     * @param string $base_folder Absolute server path to template folder (with trailing slash)
     * @param array $data Data to use for rendering placeholders inside included files
     * @return string
     */
    function process_template_includes($html, $base_folder, $data = array()) {
        // Normalize base folder
        $base_folder = rtrim($base_folder, '/\\') . DIRECTORY_SEPARATOR;

        $result = preg_replace_callback('/\{\{\s*include:([^\}\s]+)\s*\}\}/i', function($m) use ($base_folder, $data) {
            $rel = trim($m[1]);
            // sanitize: remove parent traversal tokens and backslashes
            $rel = str_replace(['..','\\'], ['', '/'], $rel);
            $rel = ltrim($rel, '/');

            // construct path
            $path = $base_folder . $rel;

            // realpath check: ensure the path is inside base_folder
            $realBase = realpath($base_folder);
            $realPath = realpath($path);
            if ($realPath === false || strpos($realPath, $realBase) !== 0) {
                // not found or outside allowed dir: return safe comment
                return '<!-- include not found or outside allowed folder: ' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . ' -->';
            }

            if (!is_file($realPath)) {
                return '<!-- include file not found: ' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . ' -->';
            }

            // Read content
            $content = @file_get_contents($realPath);
            if ($content === false) {
                return '<!-- include read error: ' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . ' -->';
            }

            // Recursively process includes inside included file (relative base is the included file's dir)
            $includedBase = dirname($realPath) . DIRECTORY_SEPARATOR;
            $content = process_template_includes($content, $includedBase, $data);

            // Now render placeholders in included content with given data
            $rendered = render_template_html($content, $data);

            return $rendered;
        }, $html);

        // If preg_replace_callback returned null (error), fallback to original html
        return $result === null ? $html : $result;
    }
}
