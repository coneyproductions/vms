<?php
/** Filesystem-only boundary matrix for the approved uploads-root architecture. */
if (($argv[1] ?? '') === 'child') {
    $case = json_decode(base64_decode($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    define('ABSPATH', $case['base'] . '/wordpress/');
    define('WP_CONTENT_DIR', $case['base'] . '/relocated-content');
    // These former destination settings are migration-source hints only.
    if (isset($case['legacy_root'])) define('BVMGR_PRIVATE_STORAGE_ROOT', $case['legacy_root']);
    if (isset($case['host_map'])) define('BVMGR_PRIVATE_STORAGE_WEB_ROOTS', $case['host_map']);
    $_SERVER['DOCUMENT_ROOT'] = $case['document_root'] ?? '';
    class WP_Error { public function __construct(public string $code, public string $message) {} }
    function __($s, $domain = '') { return $s; }
    function add_action(...$args) {} function add_filter(...$args) {}
    function wp_normalize_path($p) { return str_replace('\\', '/', $p); }
    function untrailingslashit($p) { return rtrim($p, '/'); }
    function wp_upload_dir($time = null, $create = true) {
        global $case;
        if ($time !== null || $create !== false) throw new RuntimeException('Configuration reads requested upload-directory creation');
        return $case['uploads'];
    }
    function get_current_blog_id() { global $case; return $case['site']; }
    function is_wp_error($v) { return $v instanceof WP_Error; }
    function sanitize_file_name($s) { return preg_replace('/[^a-zA-Z0-9_.-]/', '', $s); }
    function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
    function wp_remote_get(...$args) { throw new RuntimeException('Read attempted HTTP'); }
    function update_option(...$args) { throw new RuntimeException('Read attempted option write'); }
    require dirname(__DIR__) . '/includes/core/private-files.php';
    $config = bvmgr_private_storage_config();
    if (is_wp_error($config)) { echo 'rejected'; exit; }
    $expected = $case['expected_uploads'] . '/backstage-venue-manager/private';
    if ($config['root'] !== $expected || $config['site'] !== $expected . '/site-' . $case['site']) throw new RuntimeException('Destination escaped the WordPress uploads configuration');
    if (file_exists($expected)) throw new RuntimeException('Configuration read created storage');
    foreach (array('../secret', '/etc/passwd', 'tax/../../secret', "tax/x\0.pdf", 'https://example.com/x', 'tax/%2e%2e/x') as $key) {
        if (bvmgr_private_storage_target($key) !== '') throw new RuntimeException('Traversal accepted');
    }
    mkdir($config['site'], 0700, true);
    foreach (bvmgr_private_storage_protection_files() as $name => $content) file_put_contents($expected . '/' . $name, $content);
    $GLOBALS['options']['bvmgr_private_storage_http_verification'] = array('root' => $config['root'], 'url' => $config['url']);
    // The real HTTP receipt is separately tested by the isolated integration harness; this fixture tests path decisions.
    file_put_contents($config['site'] . '/valid.pdf', 'SYNTHETIC');
    if (!bvmgr_private_storage_safe_file($config['site'] . '/valid.pdf')) throw new RuntimeException('Verified current-site positive control failed');
    $outside = $case['base'] . '/outside';
    link($outside . '/secret.txt', $config['site'] . '/hardlink.txt');
    symlink($outside, $config['site'] . '/linked');
    $other = $expected . '/site-' . ($case['site'] + 1); mkdir($other);
    file_put_contents($other . '/other.txt', 'OTHER');
    foreach (array($config['site'] . '/hardlink.txt', $other . '/other.txt', $outside . '/secret.txt') as $path) {
        if (bvmgr_private_storage_safe_file($path)) throw new RuntimeException('Unowned/hardlinked path accepted');
    }
    if (bvmgr_private_storage_target('linked/secret.txt') !== '' || bvmgr_private_storage_harden($outside) || bvmgr_private_storage_harden($other)) throw new RuntimeException('Linked or cross-site write accepted');
    if (file_exists($outside . '/index.php') || file_exists($other . '/index.php')) throw new RuntimeException('Rejected destination changed');
    if (DIRECTORY_SEPARATOR !== '\\' && bvmgr_private_storage_within('/PRIVATE/site-7/file', '/private/site-7')) throw new RuntimeException('Case-distinct boundary accepted');
    echo 'accepted'; exit;
}
$base = sys_get_temp_dir() . '/bvm-private-matrix-' . bin2hex(random_bytes(8));
mkdir($base, 0700); $base = realpath($base);
$variants = array(
    'standard uploads' => array(),
    'custom uploads' => array('directory' => 'custom-media'),
    'multisite secondary uploads' => array('directory' => 'uploads/sites/7', 'site' => 7),
    'missing old root and host map' => array(),
    'old external root is never destination' => array('legacy' => 'outside'),
    'old public root is never destination' => array('legacy' => 'wordpress'),
    'old traversal root is never destination' => array('legacy' => 'outside/../wordpress'),
    'misleading document root is irrelevant' => array('document' => 'outside'),
    'host alias cannot authorize another destination' => array('map' => true),
    'relative uploads rejected' => array('bad_path' => 'relative/uploads', 'reject' => true),
    'traversal uploads rejected' => array('bad_path' => '/tmp/../uploads', 'reject' => true),
    'stream uploads rejected' => array('bad_path' => 'php://memory', 'reject' => true),
    'NUL uploads rejected' => array('bad_path' => "/tmp/media\0bad", 'reject' => true),
    'missing uploads URL rejected' => array('url' => '', 'reject' => true),
    'file URL rejected' => array('url' => 'file:///tmp/uploads', 'reject' => true),
    'WordPress upload error rejected' => array('error' => 'unavailable', 'reject' => true),
    'symlinked private root rejected' => array('link' => 'private', 'reject' => true),
    'symlinked site rejected' => array('link' => 'site', 'reject' => true),
);
try {
    foreach ($variants as $label => $variant) {
        $dir = $base . '/case-' . count(glob($base . '/case-*')); mkdir($dir);
        foreach (array('wordpress', 'relocated-content', 'outside') as $name) mkdir($dir . '/' . $name);
        file_put_contents($dir . '/outside/secret.txt', 'OUTSIDE');
        $uploads = $dir . '/' . ($variant['directory'] ?? 'uploads'); mkdir($uploads, 0700, true);
        $case = array('base' => $dir, 'uploads' => array('basedir' => $variant['bad_path'] ?? $uploads, 'baseurl' => $variant['url'] ?? 'https://example.invalid/custom-media', 'error' => $variant['error'] ?? false), 'expected_uploads' => $uploads, 'site' => $variant['site'] ?? 1);
        if (isset($variant['legacy'])) $case['legacy_root'] = $dir . '/' . $variant['legacy'];
        if (isset($variant['document'])) $case['document_root'] = $dir . '/' . $variant['document'];
        if (isset($variant['map'])) $case['host_map'] = array($dir . '/outside');
        if (isset($variant['link'])) {
            $target = $uploads . '/backstage-venue-manager/private' . ($variant['link'] === 'site' ? '/site-1' : '');
            mkdir(dirname($target), 0700, true); symlink($dir . '/outside', $target);
        }
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child ' . escapeshellarg(base64_encode(json_encode($case)));
        exec($command, $output, $status); $result = implode("\n", $output); $output = array();
        if ($status !== 0 || $result !== (empty($variant['reject']) ? 'accepted' : 'rejected')) throw new RuntimeException($label . ': ' . $result);
        if (file_get_contents($dir . '/outside/secret.txt') !== 'OUTSIDE') throw new RuntimeException('Outside canary changed');
    }
    echo 'PASS ' . count($variants) . " upload-root configurations; positive reads, legacy destinations, malformed paths, hardlinks, symlinks, and cross-site boundaries\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        if ($file->isLink() || $file->isFile()) unlink($file->getPathname()); else rmdir($file->getPathname());
    }
    rmdir($base);
}
