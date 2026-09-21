<?php
/** Synthetic strict outside-webroot test. Never loads an installed WordPress environment. */
$root = sys_get_temp_dir() . '/bvmgr-outside-storage-' . bin2hex(random_bytes(8));
mkdir($root . '/public/wordpress/wp-content/uploads', 0700, true);
mkdir($root . '/private', 0700, true);
mkdir($root . '/outside', 0700, true);
$root = realpath($root);
define('ABSPATH', $root . '/public/wordpress/');
define('WP_CONTENT_DIR', $root . '/public/wordpress/wp-content');
define('BVMGR_PRIVATE_STORAGE_ROOT', $root . '/private');
define('BVMGR_PRIVATE_STORAGE_WEB_ROOTS', array($root . '/public'));
$_SERVER['DOCUMENT_ROOT'] = $root . '/public';
class WP_Error { public function __construct(public string $code, public string $message) {} }
function __($text, $domain = '') { return $text; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function add_filter(...$args) {} function add_action(...$args) {}
function wp_upload_dir(...$args) { global $root; return array('basedir' => $root . '/public/wordpress/wp-content/uploads'); }
function wp_normalize_path($p) { return str_replace('\\', '/', $p); }
function wp_unslash($p) { return stripslashes($p); }
function wp_is_writable($p) { return is_writable($p); }
function wp_mkdir_p($p) { return is_dir($p) || mkdir($p, 0700, true); }
function get_current_blog_id() { return $GLOBALS['site'] ?? 7; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_-]/', '', $s); }
function sanitize_file_name($s) { return preg_replace('/[^a-zA-Z0-9_.-]/', '', $s); }
require dirname(__DIR__) . '/includes/core/private-files.php';
$checks = 0;
function check($ok, $label) { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; }
function tree($path) { $rows = array(); foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $f) $rows[$f->getPathname()] = $f->isFile() ? hash_file('sha256', $f->getPathname()) : 'directory'; ksort($rows); return $rows; }
try {
    $before = tree($root);
    $config = bvmgr_private_storage_config();
    check(!is_wp_error($config), 'outside host configuration accepted');
    check($config['site'] === $root . '/private/site-7', 'site-isolated destination');
    check($before === tree($root), 'configuration reads write nothing');
    check(!bvmgr_private_storage_harden($root . '/public'), 'public hardening root rejected');
    foreach (array('../x', '/tmp/x', 'tax/../../x', 'tax/%2e%2e/x', "tax/x\0", 'https://example.invalid/x') as $key) check(bvmgr_private_storage_target($key) === '', 'reject traversal ' . $key);
    check(bvmgr_private_storage_prepare('tax-docs', true), 'outside storage preparation');
    check(!file_exists($config['root'] . '/.htaccess') && !file_exists($config['root'] . '/web.config') && !file_exists($config['root'] . '/index.php'), 'no server protection artifact required');
    $path = bvmgr_private_storage_target('tax-docs/proof.pdf');
    check($path === $config['site'] . '/tax-docs/proof.pdf', 'approved destination');
    file_put_contents($path, 'SYNTHETIC PRIVATE');
    check(bvmgr_private_storage_safe_file($path), 'outside private file readable by broker');
    $read_snapshot = tree($root); bvmgr_private_storage_safe_file($path); bvmgr_private_storage_resolve('tax-docs/proof.pdf');
    check($read_snapshot === tree($root), 'reads do not mutate');
    $GLOBALS['site'] = 8; check(!bvmgr_private_storage_safe_file($path), 'cross-site read rejected'); unset($GLOBALS['site']);
    symlink($root . '/outside', $config['site'] . '/escape');
    check(bvmgr_private_storage_target('escape/file.pdf') === '', 'symlink write rejected');
    check(!bvmgr_private_storage_harden($config['site'] . '/escape'), 'symlink hardening rejected');
    link($path, $config['site'] . '/linked.pdf'); check(!bvmgr_private_storage_safe_file($path), 'hardlink rejected'); unlink($config['site'] . '/linked.pdf');
    check($before !== tree($root), 'only explicit preparation/write mutates');
    echo "PASS $checks strict outside-webroot storage assertions\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) { if ($f->isLink() || $f->isFile()) unlink($f->getPathname()); else rmdir($f->getPathname()); }
    rmdir($root);
}
