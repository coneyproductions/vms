<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

class WP_Error
{
    private string $code;
    private string $message;

    public function __construct(string $code = '', string $message = '')
    {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }
}

class WP_Post
{
    public int $ID;
    public string $post_type;
    public string $post_status;

    public function __construct(int $id, string $post_type, string $post_status = 'pending')
    {
        $this->ID = $id;
        $this->post_type = $post_type;
        $this->post_status = $post_status;
    }
}

class BvmgrProofStreamIntercept extends RuntimeException
{
}

class BvmgrProofDieIntercept extends RuntimeException
{
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
    return true;
}

function add_shortcode(string $tag, $callback): bool
{
    return true;
}

function apply_filters(string $hook, $value)
{
    if ($hook === 'vms_ticketing_verification_allowed_mimes' && !empty($GLOBALS['bvmgr_proof_allow_gif'])) {
        $value['gif'] = 'image/gif';
    }

    return $value;
}

function __(string $text, string $domain = ''): string
{
    return $text;
}

function _x(string $text, string $context = '', string $domain = ''): string
{
    return $text;
}

function _n_noop(string $single, string $plural, string $domain = ''): array
{
    return array($single, $plural);
}

function esc_html__(string $text, string $domain = ''): string
{
    return $text;
}

function esc_html(string $text): string
{
    return $text;
}

function sanitize_key(string $value): string
{
    return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $value));
}

function sanitize_text_field(string $value): string
{
    return trim((string) preg_replace('/[\r\n\t]+/', ' ', $value));
}

function sanitize_file_name(string $value): string
{
    $value = basename(str_replace('\\', '/', $value));
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);
    return trim((string) $value, '-.');
}

function absint($value): int
{
    return abs((int) $value);
}

function wp_unslash($value)
{
    return $value;
}

function trailingslashit(string $value): string
{
    return rtrim($value, '/\\') . '/';
}

function wp_normalize_path(string $path): string
{
    return str_replace('\\', '/', $path);
}

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

function bvmgr_request_read_absint(array $source, string $key): int
{
    return isset($source[$key]) && !is_array($source[$key]) ? absint($source[$key]) : 0;
}

function bvmgr_nonce_action_for_value(string $nonce, string $action): string
{
    return $action;
}

function current_user_can(string $capability): bool
{
    return !empty($GLOBALS['bvmgr_proof_caps'][$capability]);
}

function wp_verify_nonce(string $nonce, string $action)
{
    return hash_equals('valid:' . $action, $nonce) ? 1 : false;
}

function wp_die(string $message): void
{
    throw new BvmgrProofDieIntercept($message);
}

function get_post(int $post_id)
{
    return $GLOBALS['bvmgr_proof_posts'][$post_id] ?? null;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
    return $GLOBALS['bvmgr_proof_meta'][$post_id][$key] ?? '';
}

function bvmgr_private_file_get(int $file_id): ?array
{
    return $GLOBALS['bvmgr_proof_files'][$file_id] ?? null;
}

function bvmgr_private_file_path(string $stored_filename): string
{
    return trailingslashit($GLOBALS['bvmgr_proof_private_root']) . $stored_filename;
}

function bvmgr_private_files_ensure_dir(string $bucket = ''): bool
{
    return is_dir(bvmgr_private_files_bucket_dir($bucket));
}

function bvmgr_private_files_bucket_dir(string $bucket): string
{
    return trailingslashit($GLOBALS['bvmgr_proof_private_root']) . sanitize_key($bucket);
}

function bvmgr_private_files_safe_download_name(string $filename, string $fallback_base = 'download'): string
{
    $filename = sanitize_file_name($filename);
    if ($filename !== '') {
        return $filename;
    }

    $fallback_base = sanitize_file_name($fallback_base);
    return $fallback_base !== '' ? $fallback_base : 'download';
}

function wp_upload_dir($time = null, bool $create_dir = true): array
{
    return array('basedir' => $GLOBALS['bvmgr_proof_upload_root']);
}

function wp_check_filetype_and_ext(string $path, string $filename, array $allowed_mimes): array
{
    $extension = sanitize_key((string) pathinfo($filename, PATHINFO_EXTENSION));
    if ($extension === '' || !array_key_exists($extension, $allowed_mimes)) {
        return array('ext' => false, 'type' => false, 'proper_filename' => false);
    }

    $allowed = (array) $allowed_mimes[$extension];
    $type = isset($GLOBALS['bvmgr_proof_detected_mime'][$path])
        ? (string) $GLOBALS['bvmgr_proof_detected_mime'][$path]
        : (string) reset($allowed);

    return array('ext' => $extension, 'type' => $type, 'proper_filename' => false);
}

function bvmgr_private_files_stream_path(string $path, string $filename, string $mime, string $disposition = 'attachment'): void
{
    $GLOBALS['bvmgr_proof_stream'] = array(
        'path' => $path,
        'filename' => bvmgr_private_files_safe_download_name($filename),
        'mime' => $mime,
        'disposition' => $disposition === 'inline' ? 'inline' : 'attachment',
    );
    throw new BvmgrProofStreamIntercept('streamed');
}

function bvmgrProofAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function bvmgrProofSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
}

function bvmgrProofRunHandler(array $query, array $caps): array
{
    $_GET = $query;
    $GLOBALS['bvmgr_proof_caps'] = $caps;
    $GLOBALS['bvmgr_proof_stream'] = null;

    try {
        bvmgr_ticketing_verification_stream_proof();
    } catch (BvmgrProofStreamIntercept $exception) {
        return array('result' => 'stream', 'stream' => $GLOBALS['bvmgr_proof_stream']);
    } catch (BvmgrProofDieIntercept $exception) {
        return array('result' => 'denied', 'message' => $exception->getMessage());
    }

    return array('result' => 'returned');
}

$repo_root = dirname(__DIR__);
$ticketing_source = file_get_contents($repo_root . '/includes/integrations/ticketing-verifications.php');
$private_source = file_get_contents($repo_root . '/includes/core/private-files.php');
bvmgrProofAssert(is_string($ticketing_source) && is_string($private_source), 'Required runtime source should be readable.');

require_once $repo_root . '/includes/integrations/ticketing-verifications.php';

$fixture_root = sys_get_temp_dir() . '/bvmgr-eligibility-proof-preview-' . getmypid();
$upload_root = $fixture_root . '/uploads';
$private_root = $upload_root . '/vms-private';
$proof_root = $private_root . '/verifications';
$other_root = $private_root . '/tax-docs';
$legacy_root = $upload_root . '/vms-verification-proofs';
foreach (array($proof_root, $other_root, $legacy_root) as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create isolated proof fixture directory.');
    }
}

$GLOBALS['bvmgr_proof_upload_root'] = $upload_root;
$GLOBALS['bvmgr_proof_private_root'] = $private_root;
$GLOBALS['bvmgr_proof_allow_gif'] = true;
$GLOBALS['bvmgr_proof_detected_mime'] = array();
$GLOBALS['bvmgr_proof_posts'] = array();
$GLOBALS['bvmgr_proof_meta'] = array();
$GLOBALS['bvmgr_proof_files'] = array();

$fixtures = array(
    501 => array('request_id' => 101, 'stored' => 'verifications/proof.pdf', 'original' => '../../unsafe "proof".pdf', 'mime' => 'application/pdf'),
    502 => array('request_id' => 102, 'stored' => 'verifications/proof.jpg', 'original' => 'proof.jpg', 'mime' => 'image/jpeg'),
    503 => array('request_id' => 103, 'stored' => 'verifications/proof.png', 'original' => 'proof.png', 'mime' => 'image/png'),
    504 => array('request_id' => 104, 'stored' => 'verifications/proof.webp', 'original' => 'proof.webp', 'mime' => 'image/webp'),
    505 => array('request_id' => 105, 'stored' => 'verifications/proof.gif', 'original' => 'proof.gif', 'mime' => 'image/gif'),
    506 => array('request_id' => 106, 'stored' => 'verifications/proof.txt', 'original' => 'proof.txt', 'mime' => 'text/plain'),
    507 => array('request_id' => 107, 'stored' => 'verifications/mismatch.pdf', 'original' => 'mismatch.pdf', 'mime' => 'image/jpeg'),
    508 => array('request_id' => 108, 'stored' => '../../outside.pdf', 'original' => 'outside.pdf', 'mime' => 'application/pdf'),
    509 => array('request_id' => 109, 'stored' => 'tax-docs/not-a-proof.pdf', 'original' => 'not-a-proof.pdf', 'mime' => 'application/pdf'),
);

$created_paths = array();
try {
    foreach ($fixtures as $file_id => $fixture) {
        $request_id = (int) $fixture['request_id'];
        $stored = (string) $fixture['stored'];
        $path = bvmgr_private_file_path($stored);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create proof fixture parent directory.');
        }
        file_put_contents($path, 'synthetic-proof-fixture');
        $created_paths[] = $path;

        $GLOBALS['bvmgr_proof_posts'][$request_id] = new WP_Post($request_id, 'vms_verify_req');
        $GLOBALS['bvmgr_proof_meta'][$request_id] = array(
            'proof_file_id' => $file_id,
            'proof_storage_kind' => 'private_file',
        );
        $GLOBALS['bvmgr_proof_files'][$file_id] = array(
            'stored_filename' => $stored,
            'original_filename' => (string) $fixture['original'],
            'mime_type' => (string) $fixture['mime'],
        );
    }

    $GLOBALS['bvmgr_proof_detected_mime'][bvmgr_private_file_path('verifications/mismatch.pdf')] = 'application/pdf';

    foreach (
        array(
            101 => array('application/pdf', 'inline'),
            102 => array('image/jpeg', 'inline'),
            103 => array('image/png', 'inline'),
            104 => array('image/webp', 'inline'),
            105 => array('image/gif', 'inline'),
        ) as $request_id => $expected
    ) {
        $result = bvmgrProofRunHandler(
            array(
                'request_id' => $request_id,
                '_wpnonce' => 'valid:bvmgr_verification_proof_' . $request_id,
            ),
            array('vms_manage_verifications' => true)
        );
        bvmgrProofSame('stream', $result['result'], 'Authorized previewable proof should stream.');
        bvmgrProofSame($expected[0], $result['stream']['mime'], 'Previewable proof should use its validated MIME type.');
        bvmgrProofSame($expected[1], $result['stream']['disposition'], 'Previewable proof should use inline disposition.');
    }

    $safe_name_result = bvmgrProofRunHandler(
        array('request_id' => 101, '_wpnonce' => 'valid:bvmgr_verification_proof_101'),
        array('manage_options' => true)
    );
    $safe_filename = (string) $safe_name_result['stream']['filename'];
    bvmgrProofAssert(
        $safe_filename !== ''
        && substr($safe_filename, -4) === '.pdf'
        && strpos($safe_filename, '/') === false
        && strpos($safe_filename, '\\') === false
        && strpos($safe_filename, '"') === false,
        'Original proof filename should be sanitized before use in a response header.'
    );

    $unsupported = bvmgrProofRunHandler(
        array('request_id' => 106, '_wpnonce' => 'valid:bvmgr_verification_proof_106'),
        array('vms_manage_verifications' => true)
    );
    bvmgrProofSame('application/octet-stream', $unsupported['stream']['mime'], 'Unsupported proof should use a neutral MIME type.');
    bvmgrProofSame('attachment', $unsupported['stream']['disposition'], 'Unsupported proof should retain the download fallback.');

    $mismatch = bvmgrProofRunHandler(
        array('request_id' => 107, '_wpnonce' => 'valid:bvmgr_verification_proof_107'),
        array('vms_manage_verifications' => true)
    );
    bvmgrProofSame('application/octet-stream', $mismatch['stream']['mime'], 'MIME metadata mismatch should fail closed.');
    bvmgrProofSame('attachment', $mismatch['stream']['disposition'], 'MIME metadata mismatch should not render inline.');

    $anonymous = bvmgrProofRunHandler(
        array('request_id' => 101, '_wpnonce' => 'valid:bvmgr_verification_proof_101'),
        array()
    );
    bvmgrProofSame('denied', $anonymous['result'], 'Anonymous request should be denied.');

    $without_capability = bvmgrProofRunHandler(
        array('request_id' => 101, '_wpnonce' => 'valid:bvmgr_verification_proof_101'),
        array('read' => true)
    );
    bvmgrProofSame('denied', $without_capability['result'], 'Logged-in user without the management capability should be denied.');

    $expired_nonce = bvmgrProofRunHandler(
        array('request_id' => 101, '_wpnonce' => 'expired'),
        array('vms_manage_verifications' => true)
    );
    bvmgrProofSame('denied', $expired_nonce['result'], 'Expired proof nonce should be denied.');

    $malformed_nonce = bvmgrProofRunHandler(
        array('request_id' => 101, '_wpnonce' => array('invalid')),
        array('vms_manage_verifications' => true)
    );
    bvmgrProofSame('denied', $malformed_nonce['result'], 'Malformed proof nonce should be denied.');

    $substituted_request = bvmgrProofRunHandler(
        array('request_id' => 102, '_wpnonce' => 'valid:bvmgr_verification_proof_101'),
        array('vms_manage_verifications' => true)
    );
    bvmgrProofSame('denied', $substituted_request['result'], 'A proof nonce must not authorize a substituted eligibility request ID.');

    $substituted_file = bvmgrProofRunHandler(
        array('request_id' => 101, 'file_id' => 509, '_wpnonce' => 'valid:bvmgr_verification_proof_101'),
        array('vms_manage_verifications' => true)
    );
    bvmgrProofSame(bvmgr_private_file_path('verifications/proof.pdf'), $substituted_file['stream']['path'], 'A caller-supplied file ID must not replace the proof attached to the authorized request.');

    $traversal = bvmgrProofRunHandler(
        array('request_id' => 108, '_wpnonce' => 'valid:bvmgr_verification_proof_108'),
        array('vms_manage_verifications' => true)
    );
    bvmgrProofSame('denied', $traversal['result'], 'Path traversal proof metadata should be denied.');

    $non_proof = bvmgrProofRunHandler(
        array('request_id' => 109, '_wpnonce' => 'valid:bvmgr_verification_proof_109'),
        array('vms_manage_verifications' => true)
    );
    bvmgrProofSame('denied', $non_proof['result'], 'A private file outside the verification bucket should be denied.');

    $GLOBALS['bvmgr_proof_posts'][110] = new WP_Post(110, 'attachment');
    $wrong_post_type = bvmgrProofRunHandler(
        array('request_id' => 110, '_wpnonce' => 'valid:bvmgr_verification_proof_110'),
        array('vms_manage_verifications' => true)
    );
    bvmgrProofSame('denied', $wrong_post_type['result'], 'A non-eligibility post should not be accepted as a proof record.');

    bvmgrProofAssert(
        strpos($private_source, "string \$disposition = 'attachment'") !== false
        && strpos($private_source, "\$disposition = strtolower(trim(\$disposition)) === 'inline' ? 'inline' : 'attachment';") !== false,
        'Shared private-file responses should default to attachment and accept only an explicit inline disposition.'
    );
    bvmgrProofAssert(
        strpos($private_source, "header('Content-Disposition: ' . \$disposition . '; filename=\"' . \$filename . '\"');") !== false,
        'Shared private-file response should emit the normalized disposition and sanitized filename.'
    );
    foreach (
        array(
            'nocache_headers();',
            "header('X-Content-Type-Options: nosniff');",
            "header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');",
            "header('Pragma: no-cache');",
        ) as $required_header_source
    ) {
        bvmgrProofAssert(strpos($private_source, $required_header_source) !== false, 'Private/no-cache response headers should remain in the shared streamer.');
    }

    bvmgrProofAssert(strpos($ticketing_source, 'target="_blank" rel="noopener"') !== false, 'View Proof should retain a dedicated browser tab.');
    bvmgrProofAssert(strpos($ticketing_source, 'admin_post_nopriv_vms_view_verification_proof') === false, 'Proof endpoint must remain unavailable to anonymous dispatch.');
    bvmgrProofAssert(strpos($ticketing_source, "'bvmgr_verification_proof_' . \$request_id") !== false, 'Proof nonce should remain bound to the eligibility request ID.');
    bvmgrProofAssert(strpos($ticketing_source, "add_action('admin_post_vms_verification_decision', 'bvmgr_ticketing_verification_handle_decision');") !== false, 'Approve/reject handler registration should remain intact.');
    bvmgrProofAssert(strpos($ticketing_source, "!wp_verify_nonce(\$nonce, bvmgr_nonce_action_for_value(\$nonce, 'bvmgr_verification_decision_' . \$request_id))") !== false, 'Approve/reject nonce verification should remain intact.');
    bvmgrProofAssert(strpos($ticketing_source, "in_array(\$decision, array('approved', 'denied'), true)") !== false, 'Approve/reject decision allowlist should remain intact.');
    bvmgrProofAssert(strpos($ticketing_source, "bvmgr_approvals_queue_record_transition(") !== false, 'Eligibility audit transition should remain intact.');

    preg_match_all('/\bid="([^"]+)"/', $ticketing_source, $id_matches);
    $static_ids = $id_matches[1] ?? array();
    bvmgrProofSame(count($static_ids), count(array_unique($static_ids)), 'Eligibility runtime should not introduce duplicate static HTML IDs.');

    fwrite(STDOUT, "eligibility proof inline preview: PASS\n");
} finally {
    foreach (array_reverse(array_unique($created_paths)) as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    foreach (array($proof_root, $other_root, $legacy_root, $private_root, $upload_root, $fixture_root) as $directory) {
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
