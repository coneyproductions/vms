<?php
/** Host-provisioned private storage outside every public web root. */
defined('ABSPATH') || exit;

function bvmgr_private_storage_error(string $code = 'private_storage_unavailable'): WP_Error
{
    $messages = array(
        'private_storage_not_configured' => __('Private document storage is not configured. Ask the host to provision an outside-webroot directory and declare every public web root.', 'backstage-venue-manager'),
        'private_storage_public_roots_missing' => __('Private document storage is unavailable because the public web-root declaration is missing.', 'backstage-venue-manager'),
        'private_storage_invalid_root' => __('The configured private document root is unavailable or unsafe.', 'backstage-venue-manager'),
        'private_storage_root_unreadable' => __('The configured private document root is not readable by PHP.', 'backstage-venue-manager'),
        'private_storage_root_not_writable' => __('The configured private document root is not writable by PHP.', 'backstage-venue-manager'),
        'private_storage_invalid_web_roots' => __('The declared public web-root configuration is incomplete or invalid.', 'backstage-venue-manager'),
        'private_storage_wordpress_root_unverified' => __('The WordPress root is not covered by the declared public web roots.', 'backstage-venue-manager'),
        'private_storage_document_root_unknown' => __('The active HTTP document root cannot be verified against the declared public web roots.', 'backstage-venue-manager'),
        'private_storage_document_root_mismatch' => __('The active HTTP document root does not match a declared public web root.', 'backstage-venue-manager'),
        'private_storage_public_path_unknown' => __('A WordPress public filesystem path could not be verified.', 'backstage-venue-manager'),
        'private_storage_public_overlap' => __('The configured private document root overlaps a public filesystem path.', 'backstage-venue-manager'),
        'private_storage_symlink' => __('The private document path contains an unsafe filesystem link.', 'backstage-venue-manager'),
    );
    $message = $messages[$code] ?? __('Private document storage is unavailable. Review the configuration and migration status under Tools → BVM Private Documents.', 'backstage-venue-manager');
    return new WP_Error($code, $message);
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
    if (!defined('BVMGR_PRIVATE_STORAGE_ROOT') || !is_string(BVMGR_PRIVATE_STORAGE_ROOT)) {
        return bvmgr_private_storage_error('private_storage_not_configured');
    }
    if (!defined('BVMGR_PRIVATE_STORAGE_WEB_ROOTS') || !is_array(BVMGR_PRIVATE_STORAGE_WEB_ROOTS) || !BVMGR_PRIVATE_STORAGE_WEB_ROOTS) {
        return bvmgr_private_storage_error('private_storage_public_roots_missing');
    }

    $root = bvmgr_private_storage_canonical(BVMGR_PRIVATE_STORAGE_ROOT);
    if ($root === '' || !@is_dir($root) || !bvmgr_private_storage_no_links(BVMGR_PRIVATE_STORAGE_ROOT)) {
        return bvmgr_private_storage_error('private_storage_invalid_root');
    }
    if (!@is_readable($root)) return bvmgr_private_storage_error('private_storage_root_unreadable');
    if (!@wp_is_writable($root)) return bvmgr_private_storage_error('private_storage_root_not_writable');

    $public = array();
    foreach (BVMGR_PRIVATE_STORAGE_WEB_ROOTS as $declared) {
        if (!is_string($declared)) return bvmgr_private_storage_error('private_storage_invalid_web_roots');
        $real = bvmgr_private_storage_canonical($declared);
        if ($real === '' || !@is_dir($real)) return bvmgr_private_storage_error('private_storage_invalid_web_roots');
        $public[] = $real;
    }

    $wordpress = bvmgr_private_storage_canonical(ABSPATH);
    $covered = false;
    foreach ($public as $webroot) {
        if (bvmgr_private_storage_within($wordpress, $webroot)) { $covered = true; break; }
    }
    if (!$covered) return bvmgr_private_storage_error('private_storage_wordpress_root_unverified');

    // Server configuration field, never an HTTP_* request header or user-selected path.
    $document_root = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? wp_unslash($_SERVER['DOCUMENT_ROOT']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Canonicalized below and matched exactly to a trusted wp-config declaration.
        : '';
    if ($document_root !== '') {
        $effective = bvmgr_private_storage_canonical($document_root);
        $cli_wordpress_root = PHP_SAPI === 'cli' && $effective === $wordpress;
        if ($effective === '' || (!$cli_wordpress_root && !in_array($effective, $public, true))) {
            return bvmgr_private_storage_error('private_storage_document_root_mismatch');
        }
    } elseif (PHP_SAPI !== 'cli') {
        return bvmgr_private_storage_error('private_storage_document_root_unknown');
    }

    $uploads = wp_upload_dir(null, false);
    $known = array(ABSPATH, WP_CONTENT_DIR, isset($uploads['basedir']) ? (string) $uploads['basedir'] : '');
    foreach ($known as $path) {
        $real = bvmgr_private_storage_canonical($path);
        if ($real === '') return bvmgr_private_storage_error('private_storage_public_path_unknown');
        $public[] = $real;
    }
    $public = array_values(array_unique($public));
    foreach ($public as $webroot) {
        if (bvmgr_private_storage_within($root, $webroot) || bvmgr_private_storage_within($webroot, $root)) {
            return bvmgr_private_storage_error('private_storage_public_overlap');
        }
    }

    $site = $root . '/site-' . get_current_blog_id();
    if (!bvmgr_private_storage_no_links($site)) return bvmgr_private_storage_error('private_storage_symlink');
    return array('root' => $root, 'site' => $site, 'public_roots' => $public);
}

/** Legacy public-source artifacts recognized during inventory; never canonical confidentiality controls. */
function bvmgr_private_storage_protection_files(): array
{
    return array(
        'index.php' => '',
        '.htaccess' => "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
        'web.config' => '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs="" /><add accessType="Deny" users="*" /></authorization></security></system.webServer></configuration>',
    );
}

/** Compatibility boundary: validate a canonical private directory without generating public-server rules. */
function bvmgr_private_storage_harden(string $dir): bool
{
    $config = bvmgr_private_storage_config();
    return !is_wp_error($config) && bvmgr_private_storage_is_verified($config)
        && bvmgr_private_storage_no_links($dir) && is_dir($dir)
        && ($dir === $config['root'] || bvmgr_private_storage_within($dir, $config['site']));
}

/** Read-only compatibility check for the verified outside-webroot configuration. */
function bvmgr_private_storage_is_verified(array $config): bool
{
    $current = bvmgr_private_storage_config();
    return !is_wp_error($current) && $current === $config
        && @is_dir($config['root']) && @is_readable($config['root']) && @wp_is_writable($config['root'])
        && bvmgr_private_storage_no_links($config['root']) && bvmgr_private_storage_no_links($config['site']);
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
    return array(
        $base . '/vms-private' => '',
        $base . '/vms-event-plan-imports' => 'legacy-event-plan-imports/',
        $base . '/vms-verification-proofs' => 'legacy-verifications/',
        $base . '/backstage-venue-manager/private/site-' . get_current_blog_id() => '',
    );
}

/** Legacy roots wholly owned by BVM and eligible for empty-directory cleanup. */
function bvmgr_private_storage_owned_legacy_roots(): array
{
    $uploads = wp_upload_dir(null, false);
    $base = isset($uploads['basedir']) ? bvmgr_private_storage_canonical((string) $uploads['basedir']) : '';
    if ($base === '') return array();
    return array(
        $base . '/vms-event-plan-imports',
        $base . '/vms-verification-proofs',
        $base . '/backstage-venue-manager/private/site-' . get_current_blog_id(),
    );
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

/** Explicit mutation only; the host-provisioned root itself is never created here. */
function bvmgr_private_storage_prepare(string $bucket, bool $migration = false): bool
{
    $config = bvmgr_private_storage_config();
    if (is_wp_error($config) || ($bucket !== '' && sanitize_key($bucket) !== $bucket)) return false;
    if (!bvmgr_private_storage_is_verified($config)) return false;
    if (!$migration && bvmgr_private_storage_pending()) return false;
    $path = $config['site'] . ($bucket !== '' ? '/' . $bucket : '');
    if (!bvmgr_private_storage_no_links($path)) return false;
    if (!@is_dir($config['site']) && !wp_mkdir_p($config['site'])) return false;
    if (!@is_dir($path) && !wp_mkdir_p($path)) return false;
    return bvmgr_private_storage_no_links($path) && wp_is_writable($path);
}

/** Suppress public URLs for explicitly migrated private attachments. */
function bvmgr_private_storage_attachment_url($url, int $attachment_id)
{
    return get_post_meta($attachment_id, '_bvmgr_private_storage_key', true) !== '' ? '' : $url;
}
add_filter('wp_get_attachment_url', 'bvmgr_private_storage_attachment_url', 99, 2);
