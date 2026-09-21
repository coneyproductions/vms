<?php
/** wp eval-file; requires a fresh guarded WordPress DB and external evidence dir. */
if (!defined('ABSPATH') || getenv('BVM_P1D_DISPOSABLE') !== '1') {
	throw new RuntimeException('Guarded disposable WordPress required.');
}
$root = dirname(__DIR__);
$evidence = getenv('BVM_P1D_EVIDENCE');
if (!$evidence || !is_dir($evidence)) {
	throw new RuntimeException('Explicit external evidence directory required.');
}
$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
	++$checks;
	if (!$condition) { throw new RuntimeException($message); }
};
$requests = 0;
add_filter('pre_http_request', static function () use (&$requests) {
	++$requests;
	return new WP_Error('test_network_blocked', 'No external requests allowed.');
}, -999, 3);
require_once $root . '/includes/runtime-guards.php';
require_once $root . '/includes/core/prefix-b4-compat.php';
foreach (array('db', 'caps', 'normalize', 'audit', 'admission-tokens', 'rest', 'pass-claims') as $file) {
	require_once $root . '/includes/modules/admissions/' . $file . '.php';
}
bvmgr_admission_maybe_upgrade_schema();
bvmgr_admission_ensure_capability_mapping();
register_post_type('vms_event_plan', array('public' => false));
$event = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_title' => 'Disposable QR event', 'post_status' => 'publish'));
update_post_meta($event, '_vms_event_plan_status', 'published');
update_post_meta($event, '_vms_event_date', '2026-12-15');
global $wpdb;
$table = bvmgr_admission_table_entries();
$ids = $tokens = $images = array();
foreach (array(1, 2) as $slot) {
	$assert($wpdb->insert($table, array('event_plan_id' => $event, 'venue_id' => 0, 'admission_kind' => 'comp', 'source' => 'pass_claim', 'guest_name' => 'QR fixture', 'guest_name_norm' => 'qr fixture', 'guest_email' => 'guest@example.invalid', 'party_size' => 1, 'status' => 'active', 'pass_claim_id' => 777, 'created_by' => 1, 'created_at' => current_time('mysql'))) === 1, 'Create isolated admission');
	$id = (int) $wpdb->insert_id;
	$ids[] = $id;
	$token = bvmgr_admission_ensure_entry_token($id);
	$tokens[] = $token;
	$assert((bool) preg_match('/\A[a-f0-9]{40}\z/', $token), 'Existing 160-bit token format');
	$assert(bvmgr_admission_ensure_entry_token($id) === $token, 'Issuance reuses persisted token');
	$assert($wpdb->get_var($wpdb->prepare('SELECT admission_token_hash FROM %i WHERE id = %d', $table, $id)) === bvmgr_admission_token_hash($token), 'Existing HMAC storage');
	$assert(bvmgr_admission_extract_scan_token('vms-admission:' . $token) === $token, 'Unchanged scanner payload');
	$assert(bvmgr_admission_extract_scan_token(bvmgr_admission_scan_url($token)) === $token, 'Unchanged public URL redemption');
}
$assert($tokens[0] !== $tokens[1], 'Independent admission credentials');
$save_image = static function (string $uri, string $name, string $payload) use (&$images, $evidence, $assert): void {
	$assert(str_starts_with($uri, 'data:image/png;base64,'), 'Local PNG data image');
	$png = base64_decode(substr($uri, 22), true);
	$assert(is_string($png) && str_starts_with($png, "\x89PNG\r\n\x1a\n"), 'Valid PNG signature');
	file_put_contents($evidence . '/' . $name . '.png', $png);
	$images[] = array('file' => $name . '.png', 'payload' => $payload);
};
foreach (array(str_repeat('a', 32), $tokens[0], str_repeat('Z9', 20), str_repeat('b', 80)) as $index => $token) {
	$payload = 'vms-admission:' . $token;
	$uri = bvmgr_admission_qr_image_url($payload);
	$assert($uri === bvmgr_pass_claims_qr_image_url($payload), 'Shared encoder parity');
	$save_image($uri, 'qr-length-' . $index, $payload);
}
foreach (array('', 'vms-admission:', 'vms-admission:bad<script>', 'https://example.invalid/token', 'vms-admission:' . str_repeat('a', 81), 'vms-admission:' . str_repeat('a', 31), "vms-admission:" . str_repeat('a', 39) . "\n") as $bad) {
	$assert(\BVMGR\Admissions\Local_QR::data_uri($bad) === '', 'Malformed local image input fails closed');
}
$success = array('scan_url' => bvmgr_admission_scan_url($tokens[0]), 'admission_token' => $tokens[0], 'admission_tokens' => array(array('entry_id' => $ids[0], 'token' => $tokens[0]), array('entry_id' => $ids[1], 'token' => $tokens[1])));
foreach (array('single', 'group') as $kind) {
	$input = $success;
	if ($kind === 'single') { $input['admission_tokens'] = array($input['admission_tokens'][0]); }
	$html = bvmgr_pass_claims_public_success_confirmation_html($input, 'guest@example.invalid');
	preg_match_all('/<img[^>]+src="([^"]+)"/', $html, $matches);
	$assert(count($matches[1]) === ($kind === 'single' ? 1 : 2), 'Expected successful claim QR count');
	foreach ($matches[1] as $index => $uri) { $save_image(html_entity_decode($uri), 'claim-' . $kind . '-' . $index, 'vms-admission:' . $tokens[$index]); }
	$assert(!str_contains($html, 'qrserver') && !str_contains($html, 'goqr.me'), 'No remote QR image in success HTML');
}
// Actual WordPress MIME composition; only the final transport is replaced.
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/class-wp-phpmailer.php';
class BVMGR_P1D_Test_Mailer extends WP_PHPMailer {
	public $messages = array();
	public function postSend() {
		$this->messages[] = array('body' => $this->Body, 'attachments' => $this->getAttachments(), 'mime' => $this->getSentMIMEMessage());
		return true;
	}
}
$GLOBALS['phpmailer'] = new BVMGR_P1D_Test_Mailer(true);
remove_all_filters('pre_wp_mail');
$hooks_before = isset($GLOBALS['wp_filter']['phpmailer_init']) ? count($GLOBALS['wp_filter']['phpmailer_init']->callbacks, COUNT_RECURSIVE) : 0;
$result = bvmgr_admission_email_pass_result($ids[0]);
$assert($result['sent'] === true, 'Legitimate grouped pass email composed');
$message = $GLOBALS['phpmailer']->messages[0];
$assert(count($message['attachments']) === 2 && substr_count($message['body'], 'src="cid:') === 2, 'One embedded PNG per pass');
$assert(!str_contains($message['body'], 'src="http') && !str_contains($message['body'], 'src="data:'), 'Email requires no remote or data-image support');
$assert(str_contains($message['mime'], 'Content-Type: multipart/related;'), 'Portable MIME related images');
foreach ($message['attachments'] as $index => $attachment) {
	$assert($attachment[4] === 'image/png' && $attachment[6] === 'inline', 'PNG MIME disposition');
	$assert(str_contains($message['body'], 'cid:' . $attachment[7]), 'Matching content ID');
	$save_image('data:image/png;base64,' . base64_encode($attachment[0]), 'email-' . $index, 'vms-admission:' . $tokens[$index]);
}
$hooks_after = isset($GLOBALS['wp_filter']['phpmailer_init']) ? count($GLOBALS['wp_filter']['phpmailer_init']->callbacks, COUNT_RECURSIVE) : 0;
$assert($hooks_before === $hooks_after, 'Temporary email embedding hook removed');
$unrelated_error = '';
$unrelated_capture = static function ($error) use (&$unrelated_error): void {
	$unrelated_error = is_wp_error($error) ? $error->get_error_message() : 'unknown mail error';
};
add_action('wp_mail_failed', $unrelated_capture, 10, 1);
$fixture_mail_from = static function (): string { return 'wordpress@example.invalid'; };
add_filter('wp_mail_from', $fixture_mail_from);
$unrelated_sent = wp_mail('guest@example.invalid', 'Unrelated fixture', 'No QR');
remove_filter('wp_mail_from', $fixture_mail_from);
remove_action('wp_mail_failed', $unrelated_capture, 10);
$assert($unrelated_sent && count($GLOBALS['phpmailer']->messages) === 2, 'Unrelated mail composed after QR mail: ' . $unrelated_error);
$assert($GLOBALS['phpmailer']->messages[1]['attachments'] === array(), 'No QR leaks to later email');
$assert(bvmgr_admission_email_pass_result(-1)['sent'] === false, 'Invalid admission email fails safely');
// Native authorization, row status, event scope and sequential redemption.
$scan = static function (string $token, int $event_id, bool $auto = false) {
	$request = new WP_REST_Request('POST', '/vms/v1/admissions/scan');
	$request->set_param('scan', 'vms-admission:' . $token);
	$request->set_param('event_plan_id', $event_id);
	$request->set_param('auto_checkin', $auto);
	return bvmgr_admission_rest_scan($request);
};
wp_set_current_user(0);
$assert($scan($tokens[0], $event)->get_status() === 403, 'Bearer token cannot authorize check-in');
$user = wp_insert_user(array('user_login' => 'qr_door', 'user_pass' => 'disposable', 'role' => 'vms_door_staff'));
wp_set_current_user($user);
$assert($scan('unknown', $event)->get_status() === 404, 'Unknown token rejected');
$assert($scan($tokens[0], $event + 999)->get_status() === 404, 'Wrong event rejected');
$assert($scan($tokens[0], $event)->get_data()['data']['status'] === 'valid', 'Legitimate scanned payload validates');
$assert($scan($tokens[0], $event, true)->get_data()['ok'] === true, 'Legitimate redemption succeeds');
$assert($scan($tokens[0], $event, true)->get_status() === 409, 'Sequential replay blocked after use');
$wpdb->update($table, array('status' => 'canceled'), array('id' => $ids[1]));
$assert($scan($tokens[1], $event)->get_data()['error']['code'] === 'voided', 'Revoked admission rejected');
$wpdb->update($table, array('status' => 'active'), array('id' => $ids[1]));
update_post_meta($event, '_vms_event_plan_status', 'cancelled');
$assert($scan($tokens[1], $event)->get_data()['error']['code'] === 'event_cancelled', 'Canceled event rejected');
update_post_meta($event, '_vms_event_plan_status', 'published');
$nonce_request = new WP_REST_Request('POST', '/vms/v1/admissions/scan');
$nonce_request->set_header('X-WP-Nonce', 'invalid');
$assert(is_wp_error(bvmgr_admission_rest_can_checkin_request($nonce_request)), 'Invalid supplied nonce rejected');
$nonce_request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
$assert(bvmgr_admission_rest_can_checkin_request($nonce_request) === true, 'Authorized valid nonce accepted');
$logs = (string) wp_json_encode($wpdb->get_results('SELECT * FROM ' . bvmgr_admission_table_audit(), ARRAY_A));
foreach ($tokens as $token) { $assert(!str_contains($logs, $token) && !str_contains(wp_json_encode($result), $token), 'No raw token in normal audit/email result'); }
// Run the real terminal public print router; inspect its output on normal exit.
wp_set_current_user(0);
set_query_var('bvmgr_admission_scan_token', $tokens[0]);
$_GET['vms_print_pass'] = '1';
$level = ob_get_level();
remove_action('shutdown', 'wp_ob_end_flush_all', 1);
ob_start();
register_shutdown_function(static function () use ($assert, $save_image, $tokens, &$requests, &$checks, &$images, $evidence, $level): void {
	$html = '';
	while (ob_get_level() > $level) { $html = ob_get_clean() . $html; }
	$assert(str_contains($html, 'vms-pass-public-page--print'), 'Actual print router reached');
	preg_match_all('/<img[^>]+src="([^"]+)"/', $html, $matches);
	$assert(count($matches[1]) === 2, 'Actual group print output');
	foreach ($matches[1] as $index => $uri) { $save_image(html_entity_decode($uri), 'print-' . $index, 'vms-admission:' . $tokens[$index]); }
	$assert($requests === 0, 'Zero WordPress outbound QR requests');
	file_put_contents($evidence . '/qr-decode-manifest.json', wp_json_encode($images, JSON_PRETTY_PRINT));
	file_put_contents($evidence . '/qr-native-result.json', wp_json_encode(array('result' => 'PASS', 'assertions' => $checks, 'outbound_requests' => $requests, 'images_for_independent_decode' => count($images)), JSON_PRETTY_PRINT));
	echo "PASS P1-D: $checks assertions; local QR, embedded MIME, native redemption, zero outbound requests.\n";
});
bvmgr_admission_scan_template_router();
