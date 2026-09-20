<?php
/** Verified private storage within the WordPress uploads directory. */
defined('ABSPATH') || exit;

function bvmgr_private_storage_error(string $code = 'private_storage_unavailable'): WP_Error
{
    return new WP_Error($code, __('Private document storage is unavailable. Ask an administrator to configure verified private storage and complete migration under Tools → BVM Private Documents.', 'backstage-venue-manager'));
}

/** Normalize a local absolute path without accepting traversal or stream wrappers. */
function bvmgr_private_storage_normalize(string $path): string
{
    $path = str_replace('\\', '/', $path);
    if ($path === '' || strpos($path, "\0") !== false || strpos($path, '://') !== false
        || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path) || strpos($path, '//') !== false
        || !preg_match('~^(?:/|[A-Za-z]:/)~', $path)) return '';
    return rtrim($path, '/');
}

function bvmgr_private_storage_within(string $path, string $root): bool
{
    $path = str_replace('\\', '/', $path);
    $root = rtrim(str_replace('\\', '/', $root), '/');
    if (DIRECTORY_SEPARATOR === '\\') { $path = strtolower($path); $root = strtolower($root); }
    return $path === $root || strpos($path, $root . '/') === 0;
}

/** Resolve even an absent final path through its existing parent; never create it. */
function bvmgr_private_storage_canonical(string $path): string
{
    $path = bvmgr_private_storage_normalize($path);
    if ($path === '') return '';
    $suffix = array();
    $parent = $path;
    while (!@file_exists($parent) && !@is_link($parent)) {
        $suffix[] = basename($parent);
        $next = dirname($parent);
        if ($next === $parent) return '';
        $parent = $next;
    }
    $real = @realpath($parent);
    return $real === false ? '' : rtrim(wp_normalize_path($real), '/') . ($suffix ? '/' . implode('/', array_reverse($suffix)) : '');
}

/** Reject symlinks/junction redirects in the storage path, including ancestors. */
function bvmgr_private_storage_no_links(string $path): bool
{
    $normalized = bvmgr_private_storage_normalize($path);
    $canonical = bvmgr_private_storage_canonical($path);
    if ($normalized === '' || $canonical === '' || (DIRECTORY_SEPARATOR === '\\' ? strtolower($normalized) !== strtolower($canonical) : $normalized !== $canonical)) return false;
    for ($part = $normalized; dirname($part) !== $part; $part = dirname($part)) {
        if (@is_link($part)) return false;
    }
    return true;
}

/** Resolve paths without creating files, changing options, or making HTTP requests. */
function bvmgr_private_storage_config()
{
    $uploads = wp_upload_dir(null, false);
    if (!empty($uploads['error']) || empty($uploads['basedir']) || empty($uploads['baseurl'])) return bvmgr_private_storage_error('private_uploads_unavailable');
    $base = bvmgr_private_storage_canonical((string) $uploads['basedir']);
    $url = untrailingslashit((string) $uploads['baseurl']);
    if ($base === '' || !preg_match('~^https?://~i', $url)) return bvmgr_private_storage_error('private_uploads_invalid');
    $container = $base . '/backstage-venue-manager';
    $root = $container . '/private';
    $site = $root . '/site-' . get_current_blog_id();
    if (!bvmgr_private_storage_no_links($root) || !bvmgr_private_storage_no_links($site)) return bvmgr_private_storage_error('private_storage_symlink');
    return array('root' => $root, 'site' => $site, 'container' => $container, 'url' => $url . '/backstage-venue-manager');
}

/** Only these immutable, non-executable protection files may be generated. */
function bvmgr_private_storage_protection_files(): array
{
    return array(
        'index.php' => '',
        '.htaccess' => "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
        'web.config' => '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs="" /><add accessType="Deny" users="*" /></authorization></security></system.webServer></configuration>',
    );
}

/** Reject arbitrary, traversal, linked, and cross-site hardening destinations. */
function bvmgr_private_storage_harden(string $dir): bool
{
    $config = bvmgr_private_storage_config();
    if (is_wp_error($config) || !bvmgr_private_storage_no_links($dir) || !is_dir($dir)
        || ($dir !== $config['root'] && !bvmgr_private_storage_within($dir, $config['site']))) return false;
    foreach (bvmgr_private_storage_protection_files() as $name => $content) {
        $path = $dir . '/' . $name;
        if (!bvmgr_private_storage_no_links($path)) return false;
        if (file_exists($path)) {
            if (!is_file($path) || (int) (@stat($path)['nlink'] ?? 0) !== 1 || file_get_contents($path) !== $content) return false;
        } else {
            $handle = @fopen($path, 'xb');
            if (!$handle) return false;
            try { if ($content !== '' && fwrite($handle, $content) !== strlen($content)) return false; }
            finally { fclose($handle); }
        }
    }
    return true;
}

/** Read-only check of the verified configuration and protection files. */
function bvmgr_private_storage_is_verified(array $config): bool
{
    $receipt = get_option('bvmgr_private_storage_http_verification', array());
    if (!is_array($receipt) || ($receipt['root'] ?? '') !== $config['root'] || ($receipt['url'] ?? '') !== $config['url']) return false;
    foreach (bvmgr_private_storage_protection_files() as $name => $content) {
        $path = $config['root'] . '/' . $name;
        if (!bvmgr_private_storage_no_links($path) || !is_file($path) || (int) (@stat($path)['nlink'] ?? 0) !== 1 || file_get_contents($path) !== $content) return false;
    }
    return true;
}

/** Explicit preparation only: prove the uploads URL mapping and actual HTTP denial. */
function bvmgr_private_storage_verify_http(array $config): bool
{
    if ($config !== bvmgr_private_storage_config()) return false;
    $verified = false;
    $token = wp_generate_uuid4();
    $public = $config['container'] . '/probe-' . $token . '.txt';
    $private = $config['root'] . '/probe-' . $token . '.txt';
    foreach (array($public, $private) as $path) if (!bvmgr_private_storage_no_links($path) || file_exists($path)) return false;
    try {
        foreach (array($public, $private) as $path) if (file_put_contents($path, $token, LOCK_EX) !== strlen($token)) return false;
        // The URL comes exclusively from WordPress uploads configuration, never request input.
        $args = array('timeout' => 5, 'redirection' => 0, 'limit_response_size' => 1024, 'cookies' => array(), 'headers' => array('Cache-Control' => 'no-cache'));
        $visible = wp_remote_get($config['url'] . '/' . basename($public), $args);
        $hidden = wp_remote_get($config['url'] . '/private/' . basename($private), $args);
        if (is_wp_error($visible) || is_wp_error($hidden) || wp_remote_retrieve_response_code($visible) !== 200
            || wp_remote_retrieve_body($visible) !== $token || wp_remote_retrieve_response_code($hidden) !== 403) return false;
        $receipt = array('root' => $config['root'], 'url' => $config['url']);
        update_option('bvmgr_private_storage_http_verification', $receipt, false);
        $verified = get_option('bvmgr_private_storage_http_verification') === $receipt;
        return $verified;
    } finally {
        if (!$verified) delete_option('bvmgr_private_storage_http_verification');
        foreach (array($public, $private) as $path) if (bvmgr_private_storage_no_links($path)) wp_delete_file($path);
    }
}

/** Resolve a validated logical key for an explicit write; does not create anything. */
function bvmgr_private_storage_target(string $key): string
{
    $key = bvmgr_private_files_validate_storage_key($key);
    $config = bvmgr_private_storage_config();
    if ($key === '' || is_wp_error($config)) return '';
    $path = $config['site'] . '/' . $key;
    return bvmgr_private_storage_no_links($path) ? $path : '';
}

/** Read-only path check. Hard-linked files and all legacy public paths are rejected. */
function bvmgr_private_storage_safe_file(string $path): bool
{
    $config = bvmgr_private_storage_config();
    if (is_wp_error($config) || !bvmgr_private_storage_is_verified($config) || !bvmgr_private_storage_no_links($path) || !@is_file($path) || !@is_readable($path)) return false;
    $real = @realpath($path);
    $stat = @stat($path);
    return $real !== false && bvmgr_private_storage_within(wp_normalize_path($real), $config['site'])
        && is_array($stat) && (int) $stat['nlink'] === 1;
}

/** Known BVM-owned historical roots; unrelated companion subdirectories are not swept. */
function bvmgr_private_storage_legacy_roots(): array
{
    $uploads = wp_upload_dir(null, false);
    $base = isset($uploads['basedir']) ? bvmgr_private_storage_canonical((string) $uploads['basedir']) : '';
    if ($base === '') return array();
    $roots = array($base . '/vms-private' => '', $base . '/vms-event-plan-imports' => 'legacy-event-plan-imports/', $base . '/vms-verification-proofs' => 'legacy-verifications/');
    if (defined('BVMGR_PRIVATE_STORAGE_ROOT') && is_string(BVMGR_PRIVATE_STORAGE_ROOT)) {
        $prior = bvmgr_private_storage_canonical(BVMGR_PRIVATE_STORAGE_ROOT . '/site-' . get_current_blog_id());
        $config = bvmgr_private_storage_config();
        if ($prior !== '' && !is_wp_error($config) && !bvmgr_private_storage_within($prior, $config['container'])
            && !bvmgr_private_storage_within($config['container'], $prior) && bvmgr_private_storage_no_links($prior)) $roots[$prior] = '';
    }
    return $roots;
}

/** Read compatibility: stable keys and historical absolute references map to secure objects only. */
function bvmgr_private_storage_resolve(string $reference): string
{
    $key = bvmgr_private_files_validate_storage_key($reference);
    $legacy = '';
    if ($key === '') {
        $normalized = bvmgr_private_storage_normalize($reference);
        if ($normalized === '') return '';
        if (bvmgr_private_storage_safe_file($normalized)) return $normalized;
        foreach (bvmgr_private_storage_legacy_roots() as $root => $prefix) {
            if (bvmgr_private_storage_within($normalized, $root) && strlen($normalized) > strlen($root)) {
                $key = bvmgr_private_files_validate_storage_key($prefix . substr($normalized, strlen($root) + 1));
                $legacy = $normalized;
                break;
            }
        }
    } else {
        foreach (bvmgr_private_storage_legacy_roots() as $root => $prefix) {
            if ($prefix === '' && (@file_exists($root . '/' . $key) || @is_link($root . '/' . $key))) { $legacy = $root . '/' . $key; break; }
        }
    }
    if ($key === '' || ($legacy !== '' && (@file_exists($legacy) || @is_link($legacy)))) return '';
    $path = bvmgr_private_storage_target($key);
    return $path !== '' && bvmgr_private_storage_safe_file($path) ? $path : '';
}

/** Explicit mutation only; unsupported HTTP configurations fail before private writes. */
function bvmgr_private_storage_prepare(string $bucket, bool $migration = false): bool
{
    $config = bvmgr_private_storage_config();
    if (is_wp_error($config) || ($bucket !== '' && sanitize_key($bucket) !== $bucket)) return false;
    if (!bvmgr_private_storage_no_links($config['root']) || (!is_dir($config['root']) && !wp_mkdir_p($config['root']))) return false;
    if (!bvmgr_private_storage_harden($config['root']) || !bvmgr_private_storage_verify_http($config)) return false;
    if (!$migration && bvmgr_private_storage_pending()) return false;
    $path = $config['site'] . ($bucket !== '' ? '/' . $bucket : '');
    if (!bvmgr_private_storage_no_links($path) || (!is_dir($path) && !wp_mkdir_p($path))) return false;
    return bvmgr_private_storage_no_links($path) && wp_is_writable($path);
}

/** Suppress public URLs for explicitly migrated private attachments. */
function bvmgr_private_storage_attachment_url($url, int $attachment_id)
{
    return get_post_meta($attachment_id, '_bvmgr_private_storage_key', true) !== '' ? '' : $url;
}
add_filter('wp_get_attachment_url', 'bvmgr_private_storage_attachment_url', 99, 2);
