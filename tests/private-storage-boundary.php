<?php
/** Filesystem-only outside-webroot boundary regression; never boots an installed WordPress site. */
if (($argv[1] ?? '') === 'child') {
    $case = json_decode(base64_decode($argv[2]), true);
    define('ABSPATH', $case['wordpress'] . '/');
    define('WP_CONTENT_DIR', $case['content']);
    if (isset($case['root'])) define('BVMGR_PRIVATE_STORAGE_ROOT', $case['root']);
    if (isset($case['public'])) define('BVMGR_PRIVATE_STORAGE_WEB_ROOTS', $case['public']);
    $_SERVER['DOCUMENT_ROOT'] = $case['document_root'] ?? '';
    class WP_Error { public function __construct(public string $code, public string $message) {} }
    function __($s, $domain = '') { return $s; }
    function add_action(...$args) {} function add_filter(...$args) {}
    function wp_normalize_path($p) { return str_replace('\\', '/', $p); }
    function wp_unslash($p) { return stripslashes($p); }
    function wp_is_writable($p) { return is_writable($p); }
    function wp_upload_dir(...$args) { global $case; return array('basedir' => $case['uploads']); }
    function get_current_blog_id() { return 7; }
    function is_wp_error($value) { return $value instanceof WP_Error; }
    function sanitize_file_name($s) { return preg_replace('/[^a-zA-Z0-9_.-]/', '', $s); }
    require dirname(__DIR__) . '/includes/core/private-files.php';
    if (DIRECTORY_SEPARATOR !== '\\' && bvmgr_private_storage_within('/PRIVATE/site-7/file', '/private/site-7')) throw new RuntimeException('Case-distinct boundary accepted');
    $config = bvmgr_private_storage_config();
    if (is_wp_error($config)) { echo $config->code; exit; }
    foreach (array('../secret', '/etc/passwd', 'tax/../../secret', "tax/x\0.pdf", 'https://example.com/x', 'tax/%2e%2e/x') as $key) {
        if (bvmgr_private_storage_target($key) !== '') throw new RuntimeException('Traversal accepted');
    }
    if (bvmgr_private_storage_safe_file($case['root'] . '/site-7/hardlink.txt')) throw new RuntimeException('Hardlink accepted');
    if (bvmgr_private_storage_safe_file($case['root'] . '/site-8/other.txt')) throw new RuntimeException('Other site file accepted');
    if (bvmgr_private_storage_safe_file($case['outside_file'])) throw new RuntimeException('Arbitrary file accepted');
    if (bvmgr_private_storage_target('linked/secret.txt') !== '') throw new RuntimeException('Symlink child accepted');
    echo 'accepted'; exit;
}
$base = sys_get_temp_dir() . '/bvm-private-unit-' . bin2hex(random_bytes(8));
$dirs = array('/public/wordpress/wp-content/uploads', '/private/site-7', '/private/site-8', '/relocated-content/uploads', '/other-public', '/outside');
foreach ($dirs as $dir) mkdir($base . $dir, 0700, true);
$base = realpath($base);
file_put_contents($base . '/outside/secret.txt', 'SYNTHETIC');
link($base . '/outside/secret.txt', $base . '/private/site-7/hardlink.txt');
file_put_contents($base . '/private/site-8/other.txt', 'OTHER_SITE');
symlink($base . '/outside', $base . '/private/site-7/linked');
symlink($base . '/private', $base . '/private-link');
$default = array('wordpress' => $base . '/public/wordpress', 'content' => $base . '/public/wordpress/wp-content', 'uploads' => $base . '/public/wordpress/wp-content/uploads', 'root' => $base . '/private', 'public' => array($base . '/public'), 'document_root' => $base . '/public', 'outside_file' => $base . '/outside/secret.txt');
$cases = array(
    'secure root outside actual document root' => array(array(), true),
    'WordPress parent remains public' => array(array('root' => $base . '/public'), false),
    'uploads root' => array(array('root' => $default['uploads']), false),
    'inside document root' => array(array('root' => $base . '/public/wordpress'), false),
    'WP-CLI synthesized WordPress DOCUMENT_ROOT' => array(array('document_root' => $default['wordpress']), true),
    'misleading DOCUMENT_ROOT' => array(array('document_root' => $base . '/other-public'), false),
    'missing host map' => array(array('public' => null), false),
    'missing storage root' => array(array('root' => null), false),
    'nonexistent root' => array(array('root' => $base . '/absent'), false),
    'symlink root' => array(array('root' => $base . '/private-link'), false),
    'traversal root' => array(array('root' => $base . '/public/../private'), false),
    'root above public tree' => array(array('root' => $base), false),
    'declared alias exposes private root' => array(array('public' => array($base . '/public', $base . '/private')), false),
    'relocated content is public' => array(array('content' => $base . '/relocated-content', 'root' => $base . '/relocated-content'), false),
    'relocated uploads is public' => array(array('uploads' => $base . '/private'), false),
    'CLI requires explicit host map even without DOCUMENT_ROOT' => array(array('document_root' => ''), true),
);
try {
    foreach ($cases as $label => [$override, $accepted]) {
        $case = array_replace($default, $override);
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child ' . escapeshellarg(base64_encode(json_encode($case)));
        exec($command, $output, $status);
        $result = implode("\n", $output); $output = array();
        if ($status !== 0 || ($result === 'accepted') !== $accepted) throw new RuntimeException($label . ': ' . $result);
    }
    echo 'PASS ' . count($cases) . " outside-webroot configurations; traversal, aliases, case, hardlinks, multisite, and symlink children denied\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { if ($file->isLink() || $file->isFile()) unlink($file->getPathname()); else rmdir($file->getPathname()); }
    rmdir($base);
}
