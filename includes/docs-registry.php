<?php
if (!defined('ABSPATH')) { exit; }

/**
 * VMS Docs Registry
 * Stores sources (core + addons) and the discovered docs in those sources.
 */

function vms_docs_sources() {
    static $sources = null;

    if ($sources !== null) {
        return $sources;
    }

    $sources = [];

    // Register core docs by default.
    $sources[] = [
        'module' => 'vms',
        'label'  => 'VMS Core',
        'path'   => trailingslashit(plugin_dir_path(__DIR__)) . 'docs',
        'public_base' => 'vms',
    ];

    /**
     * Addons can register docs sources:
     * do_action('vms_register_docs_sources', $register_cb);
     *
     * Where $register_cb is a callable like: function($source){ ... }
     */
    $register = function($source) use (&$sources) {
        if (!is_array($source)) { return; }
        if (empty($source['module']) || empty($source['path'])) { return; }

        $sources[] = [
            'module' => sanitize_key($source['module']),
            'label'  => isset($source['label']) ? sanitize_text_field($source['label']) : sanitize_text_field($source['module']),
            'path'   => untrailingslashit($source['path']),
            'public_base' => isset($source['public_base']) ? sanitize_key($source['public_base']) : sanitize_key($source['module']),
        ];
    };

    do_action('vms_register_docs_sources', $register);

    return $sources;
}

/**
 * Discover markdown docs in registered sources.
 * Returns array keyed by module, each item is an array of docs.
 */
function vms_docs_index() {
    $index = [];

    foreach (vms_docs_sources() as $src) {
        $path = $src['path'];

        if (!is_dir($path)) {
            continue;
        }

        $files = glob(trailingslashit($path) . '*.md');
        if (!$files) {
            $files = [];
        }

        foreach ($files as $file) {
            $parsed = vms_docs_parse_file($file);

            // Require at least a title and slug; fallback to filename-based slug.
            $slug = $parsed['slug'];
            if (!$slug) {
                $slug = sanitize_title(basename($file, '.md'));
            }

            $doc = [
                'module' => $src['module'],
                'module_label' => $src['label'],
                'public_base' => $src['public_base'],
                'title'  => $parsed['title'] ?: ucwords(str_replace('-', ' ', $slug)),
                'slug'   => $slug,
                'since'  => $parsed['since'],
                'file'   => $file,
            ];

            $index[$src['module']][] = $doc;
        }

        // Sort by title for consistent UI.
        if (!empty($index[$src['module']])) {
            usort($index[$src['module']], function($a, $b) {
                return strcasecmp($a['title'], $b['title']);
            });
        }
    }

    return $index;
}

/**
 * Parse a doc file and extract front matter.
 * Front matter format:
 * ---
 * title: Something
 * slug: something
 * since: 0.1.0
 * ---
 */
function vms_docs_parse_file($file_path) {
    $out = [
        'title' => '',
        'slug'  => '',
        'since' => '',
    ];

    $raw = @file_get_contents($file_path);
    if (!is_string($raw) || $raw === '') {
        return $out;
    }

    // Detect simple YAML-ish front matter.
    if (preg_match('/\A---\s*\R(.*?)\R---\s*\R/s', $raw, $m)) {
        $fm = trim($m[1]);
        $lines = preg_split('/\R/', $fm);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, ':') === false) { continue; }

            list($k, $v) = array_map('trim', explode(':', $line, 2));
            $k = strtolower($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");

            if ($k === 'title') { $out['title'] = $v; }
            if ($k === 'slug')  { $out['slug']  = sanitize_title($v); }
            if ($k === 'since') { $out['since'] = $v; }
        }
    }

    return $out;
}

/**
 * Load raw markdown body (without front matter).
 */
function vms_docs_get_markdown($file_path) {
    $raw = @file_get_contents($file_path);
    if (!is_string($raw)) { return ''; }

    // Strip front matter if present.
    $raw = preg_replace('/\A---\s*\R(.*?)\R---\s*\R/s', '', $raw, 1);

    return ltrim($raw);
}
