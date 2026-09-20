<?php
/** Synthetic filesystem tests. Never loads an installed WordPress environment. */
$root = sys_get_temp_dir() . '/bvmgr-round2-' . bin2hex(random_bytes(8));
mkdir($root, 0700, true);
$root = realpath($root);
mkdir($root . '/custom-media');
define('ABSPATH', $root . '/');
class WP_Error { public function __construct(public string $code, public string $message) {} }
function __($text, $domain = '') { return $text; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function add_filter(...$args) {} function add_action(...$args) {}
function wp_upload_dir(...$args) { global $root; return array('basedir' => $root . '/custom-media', 'baseurl' => 'https://example.invalid/relocated-media'); }
function wp_normalize_path($p) { return str_replace('\\', '/', $p); }
function untrailingslashit($p) { return rtrim($p, '/'); }
function wp_is_writable($p) { return is_writable($p); }
function wp_mkdir_p($p) { return is_dir($p) || mkdir($p, 0700, true); }
function get_current_blog_id() { return $GLOBALS['site'] ?? 7; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_-]/', '', $s); }
function sanitize_file_name($s) { return preg_replace('/[^a-zA-Z0-9_.-]/', '', $s); }
function wp_generate_uuid4() { return bin2hex(random_bytes(16)); }
function wp_delete_file($p) { if (file_exists($p)) unlink($p); }
function delete_option($key) { unset($GLOBALS['options'][$key]); return true; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, ...$args) { $GLOBALS['options'][$key] = $value; return true; }
function wp_remote_get($url, $args) {
    global $mode, $root;
    if ($mode === 'error') return new WP_Error('http_error', 'Unreachable');
    $path = $root . '/custom-media' . substr($url, strlen('https://example.invalid/relocated-media'));
    $private = strpos($url, '/private/') !== false;
    if ($mode === 'redirect') return array('code' => 302, 'body' => '');
    return array('code' => $private && $mode !== 'exposed' ? 403 : 200, 'body' => $mode === 'wrong-mapping' ? 'wrong' : ($private && $mode !== 'exposed' ? '' : file_get_contents($path)));
}
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
require dirname(__DIR__) . '/includes/core/private-files.php';
$checks = 0;
function check($ok, $label) { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; }
function tree($root) { $rows = array(); foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) if ($f->isFile()) $rows[$f->getPathname()] = hash_file('sha256', $f->getPathname()); return $rows; }
try {
    $before = tree($root);
    $config = bvmgr_private_storage_config();
    check($config['site'] === $root . '/custom-media/backstage-venue-manager/private/site-7', 'runtime uploads and site scope');
    check($before === tree($root), 'configuration reads write nothing');
    check(!bvmgr_private_storage_harden($root), 'arbitrary hardening root rejected');
    check(!file_exists($root . '/index.php'), 'arbitrary destination untouched');
    foreach (array('../x', '/tmp/x', 'tax/../../x', 'tax/%2e%2e/x', "tax/x\0", 'https://example.invalid/x') as $key) check(bvmgr_private_storage_target($key) === '', 'reject traversal ' . $key);
    foreach (array('error', 'redirect', 'wrong-mapping', 'exposed') as $mode) {
        check(!bvmgr_private_storage_prepare('tax-docs', true), 'unsafe HTTP configuration rejected: ' . $mode);
        check(!is_dir($config['site']), 'no document bucket on failed verification');
        check(count(glob($config['container'] . '/probe-*')) + count(glob($config['root'] . '/probe-*')) === 0, 'probe cleanup');
    }
    $mode = 'protected';
    check(bvmgr_private_storage_prepare('tax-docs', true), 'verified storage preparation');
    check(file_get_contents($config['root'] . '/index.php') === '', 'empty index.php');
    check(bvmgr_private_storage_target('tax-docs/proof.pdf') === $config['site'] . '/tax-docs/proof.pdf', 'approved destination');
    $path = bvmgr_private_storage_target('tax-docs/proof.pdf'); file_put_contents($path, 'SYNTHETIC PRIVATE');
    check(bvmgr_private_storage_safe_file($path), 'protected file readable');
    $before = tree($root); bvmgr_private_storage_safe_file($path); bvmgr_private_storage_resolve('tax-docs/proof.pdf');
    check($before === tree($root), 'reads do not mutate');
    $GLOBALS['site'] = 8; check(!bvmgr_private_storage_safe_file($path), 'cross-site read rejected'); unset($GLOBALS['site']);
    mkdir($root . '/outside'); symlink($root . '/outside', $config['site'] . '/escape');
    check(bvmgr_private_storage_target('escape/file.pdf') === '', 'symlink write rejected');
    check(!bvmgr_private_storage_harden($config['site'] . '/escape'), 'symlink hardening rejected');
    link($path, $config['site'] . '/linked.pdf'); check(!bvmgr_private_storage_safe_file($path), 'hardlink rejected'); unlink($config['site'] . '/linked.pdf');
    file_put_contents($config['root'] . '/.htaccess', ''); check(!bvmgr_private_storage_safe_file($path), 'missing protection rejected');
    check(!bvmgr_private_storage_prepare('tax-docs', true), 'altered protection not overwritten');
    echo "PASS $checks storage boundary assertions\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) { if ($f->isLink() || $f->isFile()) unlink($f->getPathname()); else rmdir($f->getPathname()); }
    rmdir($root);
}
