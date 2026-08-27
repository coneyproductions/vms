<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
	throw new RuntimeException($message . ' @ ' . $file . ':' . $line, $severity);
});

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = stripslashes((string) $value);
	$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $value);
	$value = str_replace(array("\r", "\n", "\t"), ' ', (string) $value);
	return trim((string) $value);
}

function sanitize_textarea_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = stripslashes((string) $value);
	$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $value);
	return trim((string) $value);
}

function sanitize_email($value): string
{
	$value = sanitize_text_field($value);
	return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = strtolower((string) $value);
	return (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
}

function absint($value): int
{
	return abs((int) $value);
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	if (is_string($value)) {
		return stripslashes($value);
	}

	return $value;
}

function home_url(string $path = ''): string
{
	return 'https://example.test' . $path;
}

function esc_attr($text): string
{
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_html($text): string
{
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_textarea($text): string
{
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function wp_kses_post($text): string
{
	return (string) $text;
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	return true;
}

function add_shortcode(string $tag, $callback): bool
{
	return true;
}

function apply_filters(string $hook, $value)
{
	return $value;
}

function shortcode_atts(array $pairs, $atts, string $shortcode = ''): array
{
	return array_merge($pairs, is_array($atts) ? $atts : array());
}

function selected($selected, $current, bool $echo = true): string
{
	$result = ((string) $selected === (string) $current) ? ' selected="selected"' : '';
	if ($echo) {
		echo $result;
	}

	return $result;
}

function is_admin(): bool
{
	return false;
}

function get_option(string $name, $default = false)
{
	return $default;
}

function update_option(string $name, $value, bool $autoload = true): bool
{
	return true;
}

function delete_transient(string $name): bool
{
	return true;
}

function wp_nonce_field(string $action, string $name): void
{
	echo '<input type="hidden" name="' . esc_attr($name) . '" value="good:' . esc_attr($action) . '">';
}

function wp_verify_nonce(string $nonce, string $action): bool
{
	return $nonce === 'good:' . $action;
}

function current_time(string $type): string
{
	return $type === 'Y-m-d' ? '2026-07-11' : '2026-07-11 12:00:00';
}

function get_the_title(int $postId): string
{
	return (string) ($GLOBALS['vms_test_titles'][$postId] ?? '');
}

function get_post_meta(int $postId, string $metaKey, bool $single = true)
{
	$meta = (array) ($GLOBALS['vms_test_post_meta'][$postId] ?? array());
	if (!array_key_exists($metaKey, $meta)) {
		return $single ? '' : array();
	}

	$value = $meta[$metaKey];
	if ($single) {
		return $value;
	}

	return is_array($value) ? $value : array($value);
}

function wp_insert_post(array $postarr, bool $wpError = false)
{
	$GLOBALS['vms_test_insert_post_calls'][] = $postarr;
	return 321;
}

function bvmgr_get_event_plan_for_tec_event(int $eventId): int
{
	return (int) ($GLOBALS['vms_test_event_plan_map'][$eventId] ?? 0);
}

function wp_validate_redirect(string $location, string $fallback = ''): string
{
	$location = trim($location);
	if ($location === '') {
		return $fallback;
	}

	if (strpos($location, '//') === 0) {
		return $fallback;
	}

	$parts = parse_url($location);
	if ($parts === false) {
		return $fallback;
	}
	if (strpos($location, '://') !== false && empty($parts['scheme'])) {
		return $fallback;
	}
	if (!empty($parts['scheme']) && !in_array(strtolower((string) $parts['scheme']), array('http', 'https'), true)) {
		return $fallback;
	}
	if (is_array($parts) && !empty($parts['host'])) {
		$homeHost = (string) parse_url(home_url('/'), PHP_URL_HOST);
		$candidateHost = (string) ($parts['host'] ?? '');
		if ($candidateHost !== $homeHost) {
			return $fallback;
		}
	}

	return $location;
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$assertContains = static function (string $needle, string $haystack, string $message) use ($assert): void {
	$assert(strpos($haystack, $needle) !== false, $message . ' Missing substring: ' . $needle);
};

$assertNotContains = static function (string $needle, string $haystack, string $message) use ($assert): void {
	$assert(strpos($haystack, $needle) === false, $message . ' Unexpected substring: ' . $needle);
};

require dirname(__DIR__) . '/includes/runtime-guards.php';
require dirname(__DIR__) . '/includes/admin-ui/context.php';
require dirname(__DIR__) . '/includes/cpt/ratings.php';

$_GET = array(
	'page' => 'vms-dashboard',
);
$_POST = array();
$_REQUEST = array();
$_SERVER = array();

$assert(vms_request_read_scalar(array('name' => 'Band O\\\'Clock'), 'name') === "Band O'Clock", 'Scalar reads should unslash valid strings.');
$assert(vms_request_read_scalar(array(), 'missing') === '', 'Scalar reads should return empty for missing values.');
$assert(vms_request_read_scalar(array('empty' => '   '), 'empty') === '', 'Scalar reads should trim empty strings to empty.');
$assert(vms_request_read_scalar(array('name' => array('bad')), 'name') === '', 'Scalar reads should drop array-shaped input.');
$assert(vms_request_read_scalar(array('name' => array(array('nested'))), 'name') === '', 'Scalar reads should drop nested array input.');
$assert(vms_request_read_scalar(array('enabled' => true), 'enabled') === '1', 'Scalar reads should normalize boolean true when a string consumer expects a scalar.');
$assert(vms_request_read_scalar(array('enabled' => false), 'enabled') === '', 'Scalar reads should normalize boolean false to an empty scalar string.');
$assert(vms_request_read_scalar(array('count' => 42), 'count') === '42', 'Scalar reads should normalize integer scalars when a string consumer expects a scalar.');
$assert(vms_request_read_text_field(array('field' => '  Hello  '), 'field') === 'Hello', 'Text field reads should sanitize and trim scalar input.');
$assert(vms_request_read_text_field(array('field' => false), 'field') === '', 'Text field reads should safely reject false-like scalar input.');
$assert(vms_request_read_textarea_field(array('field' => "Line 1\nLine 2"), 'field') === "Line 1\nLine 2", 'Textarea reads should preserve multiline content after unslashing.');
$assert(vms_request_read_email(array('email' => ' user@example.test '), 'email') === 'user@example.test', 'Email reads should sanitize valid email addresses.');
$assert(vms_request_read_email(array('email' => 'not-an-email'), 'email') === '', 'Email reads should reject invalid email addresses.');
$normalizedKey = vms_request_read_key(array('tab' => 'Vendor_Portal'), 'tab');
$assert($normalizedKey === 'vendor_portal', 'Key reads should normalize to lowercase key format. Got ' . var_export($normalizedKey, true));
$assert(vms_request_read_absint(array('id' => '42'), 'id') === 42, 'absint reads should preserve valid numeric identifiers.');
$assert(vms_request_read_absint(array('id' => array('42')), 'id') === 0, 'absint reads should drop array-shaped identifiers.');

$assert(vms_request_read_bool_flag(array('enabled' => '1'), 'enabled') === true, 'Bool flags should accept truthy scalar strings.');
$assert(vms_request_read_bool_flag(array('enabled' => 'false'), 'enabled') === false, 'Bool flags should reject explicit false-like strings.');
$assert(vms_request_read_bool_flag(array('enabled' => true), 'enabled') === true, 'Bool flags should preserve boolean true inputs.');
$assert(vms_request_read_bool_flag(array('enabled' => ''), 'enabled') === false, 'Bool flags should reject empty scalar values.');
$assert(vms_request_read_bool_flag(array('enabled' => array('1')), 'enabled') === false, 'Bool flags should reject array-shaped input.');

$_SERVER = array();
$assert(vms_request_server_value('MISSING') === '', 'Server reads should return empty when the key is missing.');
$assert(vms_request_method() === 'get', 'Request method should default to get when missing.');
$assert(vms_request_method('HEAD') === 'head', 'Request method should normalize custom fallbacks.');
$assert(vms_request_current_uri('/fallback') === '/fallback', 'Request URI should preserve the provided fallback when missing.');
$assert(vms_request_remote_addr() === '', 'Remote address should return empty when missing.');
$assert(vms_request_user_agent() === '', 'User agent should return empty when missing.');

$_SERVER['REQUEST_METHOD'] = array('POST');
$assert(vms_request_server_value('REQUEST_METHOD') === '', 'Server reads should reject array-shaped values.');
$assert(vms_request_method('HEAD') === 'head', 'Request method should fall back safely when the server value is malformed.');
$_SERVER['REQUEST_URI'] = array('/bad');
$assert(vms_request_current_uri('/fallback') === '/fallback', 'Current URI should fall back safely when the server value is malformed.');
$_SERVER['HTTP_ACCEPT'] = array('text/html');
$assert(vms_request_server_value('HTTP_ACCEPT') === '', 'Server reads should reject malformed accept headers.');

$_SERVER['REQUEST_METHOD'] = 'PoSt';
$_SERVER['REQUEST_URI'] = "vendor-portal/dashboard?tab=dashboard\x00";
$_SERVER['REMOTE_ADDR'] = " 127.0.0.1 \n";
$_SERVER['HTTP_USER_AGENT'] = 'Browser\\' . str_repeat('A', 300);
$_SERVER['HTTP_ACCEPT'] = " application/json\\, text/html ";

$assert(vms_request_server_value('REQUEST_METHOD') === 'PoSt', 'Server reads should preserve scalar values after unslashing.');
$assert(vms_request_server_value('HTTP_ACCEPT') === 'application/json, text/html', 'Server reads should unslash and preserve diagnostic headers.');
$assert(vms_request_method() === 'post', 'Request method should normalize to lowercase keys.');
$assert(vms_request_current_uri('/') === '/vendor-portal/dashboard?tab=dashboard', 'Request URI should normalize missing leading slashes and strip control bytes.');
$assert(vms_request_remote_addr() === '127.0.0.1', 'Remote address should be trimmed and sanitized.');
$assert(strpos(vms_request_user_agent(), 'Browser') === 0, 'User agent normalization should preserve the diagnostic prefix.');
$assert(strlen(vms_request_user_agent()) === 255, 'User agent should be length-bounded.');
$_SERVER['REQUEST_URI'] = str_repeat('a', 2100);
$assert(strlen(vms_request_current_uri('/')) === 2048, 'Request URI normalization should enforce the configured length bound.');

$safeLocalRedirect = vms_request_local_redirect(home_url('/fallback'), '/vendor-portal/?tab=availability');
$assert($safeLocalRedirect === '/vendor-portal/?tab=availability', 'Valid local redirects should be preserved.');
$sameHostRedirect = vms_request_local_redirect(home_url('/fallback'), 'https://example.test/vendor-portal/?tab=dashboard');
$assert($sameHostRedirect === 'https://example.test/vendor-portal/?tab=dashboard', 'Same-host absolute redirects should be preserved.');
$externalRedirect = vms_request_local_redirect(home_url('/fallback'), 'https://evil.test/phish');
$assert($externalRedirect === home_url('/fallback'), 'External redirects should fall back to the safe local destination.');
$schemeRelativeRedirect = vms_request_local_redirect(home_url('/fallback'), '//evil.test/phish');
$assert($schemeRelativeRedirect === home_url('/fallback'), 'Scheme-relative external redirects should be rejected.');
$malformedRedirect = vms_request_local_redirect(home_url('/fallback'), '://broken');
$assert($malformedRedirect === home_url('/fallback'), 'Malformed redirect values should fall back safely.');
$arrayRedirect = vms_request_local_redirect(home_url('/fallback'), array('bad'));
$assert($arrayRedirect === home_url('/fallback'), 'Array-shaped redirect values should resolve to the safe fallback.');
$emptyRedirect = vms_request_local_redirect(home_url('/fallback'), '');
$assert($emptyRedirect === home_url('/fallback'), 'Empty redirect values should preserve the established safe fallback.');

$_GET['page'] = array('bad');
$assert(vms_admin_ui_query_arg('page') === '', 'Admin UI query helper should drop array-shaped request values without warnings.');
$_GET['page'] = 'vms-guided-tours';
$assert(vms_admin_ui_query_arg('page') === 'vms-guided-tours', 'Admin UI query helper should preserve valid scalar page slugs.');

$GLOBALS['vms_test_event_plan_map'] = array(7 => 11);
$GLOBALS['vms_test_titles'] = array(
	7 => 'Event <Title>',
	9 => 'Band <Stage>',
);
$GLOBALS['vms_test_post_meta'] = array(
	11 => array(
		'_vms_band_vendor_id' => 9,
		'_vms_event_date' => '2026-07-10',
		'_vms_start_time' => '19:30',
	),
);
$GLOBALS['vms_test_insert_post_calls'] = array();

$_GET = array(
	'event' => '7',
	'band' => '9',
);
$_POST = array(
	'vms_reviewer_name' => ' Alice <script>alert(1)</script> "A" ',
	'vms_reviewer_email' => ' alice@example.test ',
	'vms_rating_value' => '4',
	'vms_rating_comment' => "Line <b>one</b>\nLine two",
);
$_SERVER['REQUEST_METHOD'] = 'GET';

$html = vms_rate_band_shortcode(array());
$assertContains('Rate Band &lt;Stage&gt;', $html, 'The shortcode should preserve valid form repopulation and escape the band title at output.');
$assertContains('Event: Event &lt;Title&gt;', $html, 'The shortcode should escape the event title at output.');
$assertContains('value="Alice &lt;script&gt;alert(1)&lt;/script&gt; &quot;A&quot;"', $html, 'Name repopulation should remain escaped at output.');
$assertContains('value="alice@example.test"', $html, 'Email repopulation should preserve valid sanitized values.');
$assertContains('&lt;b&gt;one&lt;/b&gt;', $html, 'Comment repopulation should remain escaped in textarea output.');
$assertContains('value="4"', $html, 'Rating repopulation should preserve valid scalar values.');
$assertContains('selected="selected"', $html, 'Rating repopulation should preserve the selected option.');
$assertContains('name="vms_rating_nonce"', $html, 'The shortcode should preserve the nonce field in the rendered form.');
$assertNotContains('<script>alert(1)</script>', $html, 'Rendered repopulation output must not emit raw script tags.');

$GLOBALS['vms_test_insert_post_calls'] = array();
$_POST = array(
	'vms_rating_nonce' => 'good:vms_submit_rating',
	'vms_reviewer_name' => array('bad'),
	'vms_reviewer_email' => 'alice@example.test',
	'vms_rating_value' => '4',
	'vms_rating_comment' => 'Still ignored',
);
$invalidResult = vms_handle_rating_submission(7, 9);
$assert(($invalidResult['success'] ?? null) === false, 'Malformed rating input should fail closed.');
$assertContains('Please fill in your name, email, and rating.', (string) ($invalidResult['message'] ?? ''), 'Malformed rating input should preserve the existing validation message.');
$assert(count((array) $GLOBALS['vms_test_insert_post_calls']) === 0, 'Malformed rating input should not reach mutation or storage paths.');

fwrite(STDOUT, "Request input sanitization OK.\n");
