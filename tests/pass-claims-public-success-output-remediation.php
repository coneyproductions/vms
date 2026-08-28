<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}

final class VmsPassClaimsPublicShellRendered extends Error
{
}

if (!class_exists('WP_Error')) {
	final class WP_Error
	{
		/** @var string */
		private $code;

		/** @var string */
		private $message;

		public function __construct(string $code = '', string $message = '')
		{
			$this->code = $code;
			$this->message = $message;
		}

		public function get_error_message(): string
		{
			return $this->message;
		}
	}
}

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_rewrite_tags'] = array();
$GLOBALS['vms_test_rewrite_rules'] = array();
$GLOBALS['vms_test_query_vars'] = array();
$GLOBALS['vms_test_is_admin'] = false;
$GLOBALS['vms_test_shell_calls'] = array();
$GLOBALS['vms_test_find_token_calls'] = 0;
$GLOBALS['vms_test_find_token_tokens'] = array();
$GLOBALS['vms_test_find_token_return'] = null;
$GLOBALS['vms_test_get_batch_calls'] = 0;
$GLOBALS['vms_test_get_batch_ids'] = array();
$GLOBALS['vms_test_get_batch_return'] = null;
$GLOBALS['vms_test_rate_limit_calls'] = 0;
$GLOBALS['vms_test_rate_limit_args'] = array();
$GLOBALS['vms_test_rate_limit_return'] = false;
$GLOBALS['vms_test_eligible_calls'] = 0;
$GLOBALS['vms_test_eligible_batches'] = array();
$GLOBALS['vms_test_eligible_return'] = array(array('id' => 11));
$GLOBALS['vms_test_empty_notice_calls'] = 0;
$GLOBALS['vms_test_empty_notice_batches'] = array();
$GLOBALS['vms_test_empty_notice_return'] = array(
	'title' => 'No Eligible Events',
	'message' => 'There are no eligible published events for this pass right now.',
);
$GLOBALS['vms_test_nonce_calls'] = 0;
$GLOBALS['vms_test_nonce_args'] = array();
$GLOBALS['vms_test_nonce_return'] = true;
$GLOBALS['vms_test_create_claim_calls'] = 0;
$GLOBALS['vms_test_create_claim_args'] = array();
$GLOBALS['vms_test_create_claim_return'] = array();
$GLOBALS['vms_test_public_pass_url_calls'] = 0;
$GLOBALS['vms_test_public_pass_url_args'] = array();
$GLOBALS['vms_test_public_pass_url_return'] = '';

if (!function_exists('add_action')) {
	function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
	{
		unset($accepted_args);
		if (!isset($GLOBALS['vms_test_actions'][$hook_name])) {
			$GLOBALS['vms_test_actions'][$hook_name] = array();
		}
		if (!isset($GLOBALS['vms_test_actions'][$hook_name][$priority])) {
			$GLOBALS['vms_test_actions'][$hook_name][$priority] = array();
		}
		$GLOBALS['vms_test_actions'][$hook_name][$priority][] = $callback;
		return true;
	}
}

if (!function_exists('add_rewrite_tag')) {
	function add_rewrite_tag($tag, $regex): bool
	{
		$GLOBALS['vms_test_rewrite_tags'][] = array((string) $tag, (string) $regex);
		return true;
	}
}

if (!function_exists('add_rewrite_rule')) {
	function add_rewrite_rule($regex, $query, $position = 'bottom'): bool
	{
		$GLOBALS['vms_test_rewrite_rules'][] = array((string) $regex, (string) $query, (string) $position);
		return true;
	}
}

if (!function_exists('add_filter')) {
	function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
	{
		unset($hook_name, $callback, $priority, $accepted_args);
		return true;
	}
}

if (!function_exists('__')) {
	function __($text, $domain = ''): string
	{
		unset($domain);
		return (string) $text;
	}
}

if (!function_exists('_n')) {
	function _n($single, $plural, $number, $domain = ''): string
	{
		unset($domain);
		return ((int) $number === 1) ? (string) $single : (string) $plural;
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
		return is_string($sanitized) ? $sanitized : '';
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[\x00-\x1F\x7F]+/', '', strip_tags((string) $value));
		return is_string($sanitized) ? trim($sanitized) : '';
	}
}

if (!function_exists('sanitize_email')) {
	function sanitize_email($email): string
	{
		if (!is_scalar($email)) {
			return '';
		}

		return filter_var((string) $email, FILTER_SANITIZE_EMAIL) ?: '';
	}
}

if (!function_exists('absint')) {
	function absint($maybeint): int
	{
		return abs((int) $maybeint);
	}
}

if (!function_exists('wp_unslash')) {
	function wp_unslash($value)
	{
		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}

		return is_string($value) ? stripslashes($value) : $value;
	}
}

if (!function_exists('bvmgr_request_server_value')) {
	function bvmgr_request_server_value(string $key): string
	{
		if (!isset($_SERVER[$key]) || !is_scalar($_SERVER[$key])) {
			return '';
		}

		return trim((string) wp_unslash($_SERVER[$key]));
	}
}

if (!function_exists('bvmgr_request_method')) {
	function bvmgr_request_method(string $fallback = 'get'): string
	{
		$method = sanitize_key(bvmgr_request_server_value('REQUEST_METHOD'));
		if ($method !== '') {
			return $method;
		}

		$fallback = sanitize_key($fallback);
		return ($fallback !== '') ? $fallback : 'get';
	}
}

if (!function_exists('bvmgr_request_current_uri')) {
	function bvmgr_request_current_uri(string $fallback = ''): string
	{
		$uri = bvmgr_request_server_value('REQUEST_URI');
		if ($uri === '') {
			return $fallback;
		}

		$uri = preg_replace('/[\x00-\x1F\x7F]+/', '', $uri);
		if (!is_string($uri) || $uri === '') {
			return $fallback;
		}

		if ($uri[0] !== '/') {
			$uri = '/' . $uri;
		}

		return substr($uri, 0, 2048);
	}
}

if (!function_exists('bvmgr_request_remote_addr')) {
	function bvmgr_request_remote_addr(): string
	{
		$ip = bvmgr_request_server_value('REMOTE_ADDR');
		if ($ip === '') {
			return '';
		}

		return substr(sanitize_text_field($ip), 0, 64);
	}
}

if (!function_exists('bvmgr_request_user_agent')) {
	function bvmgr_request_user_agent(): string
	{
		$user_agent = bvmgr_request_server_value('HTTP_USER_AGENT');
		if ($user_agent === '') {
			return '';
		}

		return substr(sanitize_text_field($user_agent), 0, 255);
	}
}

if (!function_exists('esc_html')) {
	function esc_html($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_html__')) {
	function esc_html__($text, $domain = ''): string
	{
		return esc_html(__($text, $domain));
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_attr__')) {
	function esc_attr__($text, $domain = ''): string
	{
		return esc_attr(__($text, $domain));
	}
}

if (!function_exists('esc_url')) {
	function esc_url($url): string
	{
		$url = (string) $url;
		if (preg_match('~^\s*(?:javascript|data|vbscript):~i', html_entity_decode($url, ENT_QUOTES, 'UTF-8'))) {
			return '';
		}

		return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('is_admin')) {
	function is_admin(): bool
	{
		return !empty($GLOBALS['vms_test_is_admin']);
	}
}

if (!function_exists('get_query_var')) {
	function get_query_var($name)
	{
		return $GLOBALS['vms_test_query_vars'][(string) $name] ?? '';
	}
}

if (!function_exists('status_header')) {
	function status_header($code, $description = ''): bool
	{
		unset($description);
		$GLOBALS['vms_test_status_headers'][] = (int) $code;
		return true;
	}
}

if (!function_exists('nocache_headers')) {
	function nocache_headers(): bool
	{
		$GLOBALS['vms_test_nocache_headers'][] = true;
		return true;
	}
}

if (!function_exists('wp_enqueue_style')) {
	function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all'): bool
	{
		unset($src, $deps, $ver, $media);
		$GLOBALS['vms_test_enqueued_styles'][] = (string) $handle;
		return true;
	}
}

if (!function_exists('wp_enqueue_script')) {
	function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false): bool
	{
		unset($src, $deps, $ver, $in_footer);
		$GLOBALS['vms_test_enqueued_scripts'][] = (string) $handle;
		return true;
	}
}

if (!function_exists('wp_timezone')) {
	function wp_timezone(): DateTimeZone
	{
		return new DateTimeZone('UTC');
	}
}

if (!function_exists('wp_date')) {
	function wp_date($format, $timestamp = null, $timezone = null): string
	{
		$ts = $timestamp === null ? time() : (int) $timestamp;
		$dt = new DateTimeImmutable('@' . $ts);
		if ($timezone instanceof DateTimeZone) {
			$dt = $dt->setTimezone($timezone);
		}
		return $dt->format((string) $format);
	}
}

if (!function_exists('current_time')) {
	function current_time($type, $gmt = 0): string
	{
		unset($gmt);
		return gmdate((string) $type);
	}
}

if (!function_exists('wp_nonce_field')) {
	function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $display = true): string
	{
		unset($action, $referer, $display);
		return '<input type="hidden" name="' . esc_attr((string) $name) . '" value="nonce">';
	}
}

if (!function_exists('wp_verify_nonce')) {
	function wp_verify_nonce($nonce, $action = -1): bool
	{
		$GLOBALS['vms_test_nonce_calls']++;
		$GLOBALS['vms_test_nonce_args'][] = array((string) $nonce, (string) $action);
		return !empty($GLOBALS['vms_test_nonce_return']);
	}
}

if (!function_exists('selected')) {
	function selected($selected, $current = true, $display = true): string
	{
		unset($display);
		return ((string) $selected === (string) $current) ? ' selected="selected"' : '';
	}
}

if (!function_exists('checked')) {
	function checked($checked, $current = true, $display = true): string
	{
		unset($display);
		return ((string) $checked === (string) $current) ? ' checked="checked"' : '';
	}
}

if (!function_exists('is_wp_error')) {
	function is_wp_error($thing): bool
	{
		return $thing instanceof WP_Error;
	}
}

if (!function_exists('bvmgr_admission_public_pass_url')) {
	function bvmgr_admission_public_pass_url(string $token, bool $for_print = false): string
	{
		$GLOBALS['vms_test_public_pass_url_calls']++;
		$GLOBALS['vms_test_public_pass_url_args'][] = array($token, $for_print);
		$return = (string) $GLOBALS['vms_test_public_pass_url_return'];
		if ($return !== '') {
			return $return;
		}

		return 'https://passes.example.test/view?token=' . rawurlencode($token);
	}
}

if (!function_exists('bvmgr_pass_claims_render_public_shell')) {
	function bvmgr_pass_claims_render_public_shell(string $headline, callable $render_content): void
	{
		ob_start();
		$render_content();
		$content_html = ob_get_clean();
		$GLOBALS['vms_test_shell_calls'][] = array(
			'headline' => $headline,
			'content_html' => is_string($content_html) ? $content_html : '',
		);
		throw new VmsPassClaimsPublicShellRendered('rendered');
	}
}

if (!function_exists('bvmgr_pass_claims_find_token_by_raw')) {
	function bvmgr_pass_claims_find_token_by_raw(string $raw_token): ?array
	{
		$GLOBALS['vms_test_find_token_calls']++;
		$GLOBALS['vms_test_find_token_tokens'][] = $raw_token;
		$return = $GLOBALS['vms_test_find_token_return'];
		return is_array($return) ? $return : null;
	}
}

if (!function_exists('bvmgr_pass_claims_get_batch_by_id')) {
	function bvmgr_pass_claims_get_batch_by_id(int $batch_id): ?array
	{
		$GLOBALS['vms_test_get_batch_calls']++;
		$GLOBALS['vms_test_get_batch_ids'][] = $batch_id;
		$return = $GLOBALS['vms_test_get_batch_return'];
		return is_array($return) ? $return : null;
	}
}

if (!function_exists('bvmgr_pass_claims_rate_limit_hit')) {
	function bvmgr_pass_claims_rate_limit_hit(string $ip, string $token_public_key): bool
	{
		$GLOBALS['vms_test_rate_limit_calls']++;
		$GLOBALS['vms_test_rate_limit_args'][] = array($ip, $token_public_key);
		return !empty($GLOBALS['vms_test_rate_limit_return']);
	}
}

if (!function_exists('bvmgr_pass_claims_eligible_events_for_batch')) {
	function bvmgr_pass_claims_eligible_events_for_batch(array $batch): array
	{
		$GLOBALS['vms_test_eligible_calls']++;
		$GLOBALS['vms_test_eligible_batches'][] = $batch;
		return (array) $GLOBALS['vms_test_eligible_return'];
	}
}

if (!function_exists('bvmgr_pass_claims_empty_events_notice')) {
	function bvmgr_pass_claims_empty_events_notice(array $batch): array
	{
		$GLOBALS['vms_test_empty_notice_calls']++;
		$GLOBALS['vms_test_empty_notice_batches'][] = $batch;
		return (array) $GLOBALS['vms_test_empty_notice_return'];
	}
}

if (!function_exists('bvmgr_pass_claims_create_claim')) {
	function bvmgr_pass_claims_create_claim(array $token_row, array $batch, array $event_plan, array $input)
	{
		$GLOBALS['vms_test_create_claim_calls']++;
		$GLOBALS['vms_test_create_claim_args'][] = array($token_row, $batch, $event_plan, $input);
		return $GLOBALS['vms_test_create_claim_return'];
	}
}

require_once dirname(__DIR__) . '/includes/core/prefix-b4-compat.php';
require_once dirname(__DIR__) . '/includes/modules/admissions/pass-claims.php';

$pluginRoot = dirname(__DIR__);
$passClaimsSource = file_get_contents($pluginRoot . '/includes/modules/admissions/pass-claims.php');
$adminShellSource = file_get_contents($pluginRoot . '/includes/admin-ui/shell.php');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$resetRuntime = static function (): void {
	$GLOBALS['vms_test_query_vars'] = array();
	$GLOBALS['vms_test_is_admin'] = false;
	$GLOBALS['vms_test_shell_calls'] = array();
	$GLOBALS['vms_test_find_token_calls'] = 0;
	$GLOBALS['vms_test_find_token_tokens'] = array();
	$GLOBALS['vms_test_find_token_return'] = null;
	$GLOBALS['vms_test_get_batch_calls'] = 0;
	$GLOBALS['vms_test_get_batch_ids'] = array();
	$GLOBALS['vms_test_get_batch_return'] = null;
	$GLOBALS['vms_test_rate_limit_calls'] = 0;
	$GLOBALS['vms_test_rate_limit_args'] = array();
	$GLOBALS['vms_test_rate_limit_return'] = false;
	$GLOBALS['vms_test_eligible_calls'] = 0;
	$GLOBALS['vms_test_eligible_batches'] = array();
	$GLOBALS['vms_test_eligible_return'] = array(array('id' => 11));
	$GLOBALS['vms_test_empty_notice_calls'] = 0;
	$GLOBALS['vms_test_empty_notice_batches'] = array();
	$GLOBALS['vms_test_empty_notice_return'] = array(
		'title' => 'No Eligible Events',
		'message' => 'There are no eligible published events for this pass right now.',
	);
	$GLOBALS['vms_test_nonce_calls'] = 0;
	$GLOBALS['vms_test_nonce_args'] = array();
	$GLOBALS['vms_test_nonce_return'] = true;
	$GLOBALS['vms_test_create_claim_calls'] = 0;
	$GLOBALS['vms_test_create_claim_args'] = array();
	$GLOBALS['vms_test_create_claim_return'] = array();
	$GLOBALS['vms_test_public_pass_url_calls'] = 0;
	$GLOBALS['vms_test_public_pass_url_args'] = array();
	$GLOBALS['vms_test_public_pass_url_return'] = '';
	$_GET = array();
	$_POST = array();
	$_SERVER = array(
		'REQUEST_METHOD' => 'GET',
	);
};

$baseToken = static function (array $overrides = array()): array {
	return $overrides + array(
		'id' => 21,
		'batch_id' => 9,
		'status' => 'unclaimed',
		'token_public_key' => 'pubkey',
		'reservation_entry_id' => 0,
	);
};

$baseBatch = static function (array $overrides = array()): array {
	return $overrides + array(
		'id' => 9,
		'status' => 'active',
		'expires_at' => '',
		'validity_type' => 'single_event',
		'single_event_plan_id' => 0,
		'admissions_per_link' => 3,
	);
};

$captureShellRender = static function (callable $callback) use ($assert): array {
	try {
		$callback();
	} catch (VmsPassClaimsPublicShellRendered $e) {
	}
	$assert(count($GLOBALS['vms_test_shell_calls']) === 1, 'The selected Pass Claims public success family should render the shell exactly once per branch.');
	return $GLOBALS['vms_test_shell_calls'][0];
};

$assert(is_string($passClaimsSource) && $passClaimsSource !== '', 'Pass Claims source should be readable.');
$assert(is_string($adminShellSource) && $adminShellSource !== '', 'Administrator shell source should be readable.');

$assert(isset($GLOBALS['vms_test_actions']['init'][30]) && in_array('bvmgr_pass_claims_register_rewrite', $GLOBALS['vms_test_actions']['init'][30], true), 'Pass Claims should register its public rewrite callback on init at priority 30.');
$assert(isset($GLOBALS['vms_test_actions']['template_redirect'][0]) && in_array('bvmgr_pass_claims_template_router', $GLOBALS['vms_test_actions']['template_redirect'][0], true), 'Pass Claims should register its public template router on template_redirect at priority 0.');

$GLOBALS['vms_test_rewrite_tags'] = array();
$GLOBALS['vms_test_rewrite_rules'] = array();
bvmgr_pass_claims_register_rewrite();
$assert($GLOBALS['vms_test_rewrite_tags'] === array(array('%vms_pass_claim_token%', '([^&]+)')), 'Pass Claims rewrite registration should preserve the claim-token tag.');
$assert($GLOBALS['vms_test_rewrite_rules'] === array(array('^pass/claim/([^/]+)/?$', 'index.php?vms_pass_claim_token=$matches[1]', 'top')), 'Pass Claims rewrite registration should preserve the public claim route.');

$resetRuntime();
$GLOBALS['vms_test_query_vars']['vms_pass_claim_token'] = 'query-token';
$assert(bvmgr_pass_claims_get_request_token() === 'query-token', 'Pass Claims request token helper should prefer the registered query var.');

$resetRuntime();
$_GET['vms_pass_claim_token'] = 'get%20token';
$assert(bvmgr_pass_claims_get_request_token() === 'get token', 'Pass Claims request token helper should fall back to the query-string token.');

$resetRuntime();
$_SERVER['REQUEST_URI'] = '/pass/claim/uri%20token?ref=1';
$assert(bvmgr_pass_claims_get_request_token() === 'uri token', 'Pass Claims request token helper should fall back to the routed request URI token.');

$resetRuntime();
$GLOBALS['vms_test_is_admin'] = true;
$GLOBALS['vms_test_query_vars']['vms_pass_claim_token'] = 'ignored';
bvmgr_pass_claims_template_router();
$assert($GLOBALS['vms_test_shell_calls'] === array(), 'Pass Claims template router should stay inactive in wp-admin contexts.');

$resetRuntime();
bvmgr_pass_claims_template_router();
$assert($GLOBALS['vms_test_shell_calls'] === array(), 'Pass Claims template router should stay silent when no claim token is present.');

$assert(strpos($passClaimsSource, 'function bvmgr_pass_claims_render_public_shell(string $headline, callable $render_content): void') !== false, 'Pass Claims public shell should accept a renderer callback.');
$assert(strpos($passClaimsSource, "echo '<main id=\"primary\" class=\"site-main vms-pass-public-page\" role=\"main\">';") !== false, 'Pass Claims public shell should preserve the outer main wrapper.');
$assert(strpos($passClaimsSource, "echo '<div class=\"vms-pass-wrap\"><div class=\"vms-pass-card\">';") !== false, 'Pass Claims public shell should preserve the nested pass wrappers.');
$assert(strpos($passClaimsSource, '$render_content();') !== false, 'Pass Claims public shell should invoke the renderer callback at the content insertion point.');
$assert(strpos($passClaimsSource, 'echo $content_html;') === false, 'Pass Claims public shell should remove the raw content_html sink.');
$assert(strpos($passClaimsSource, "echo '</div></div>';") !== false && strpos($passClaimsSource, "echo '</main>';") !== false, 'Pass Claims public shell should preserve the closing wrappers.');
$assert(strpos($adminShellSource, 'echo $captured_notices_html;') !== false && strpos($adminShellSource, 'echo $content_html;') !== false, 'Administrator shell raw captured and content sinks should remain unchanged.');

$successHelperStart = strpos($passClaimsSource, 'function bvmgr_pass_claims_public_success_confirmation_html(array $success, string $posted_email): string');
$successHelperEnd = strpos($passClaimsSource, "if (!function_exists('bvmgr_pass_claims_render_public_shell'))");
$assert($successHelperStart !== false && $successHelperEnd !== false && $successHelperEnd > $successHelperStart, 'Pass Claims success helper block should be locatable.');
$successHelperSource = substr($passClaimsSource, (int) $successHelperStart, (int) $successHelperEnd - (int) $successHelperStart);

$assert(strpos($successHelperSource, 'function bvmgr_pass_claims_public_success_confirmation_html(array $success, string $posted_email): string') !== false, 'Pass Claims should define a dedicated success-confirmation HTML helper.');
$assert(strpos($successHelperSource, 'function bvmgr_pass_claims_render_public_success_confirmation(array $success, string $posted_email): void') !== false, 'Pass Claims should define a dedicated success-confirmation renderer.');
$assert(strpos($successHelperSource, 'wp_kses(') === false, 'Pass Claims success-confirmation family should rely on direct escaping rather than a local KSES contract.');
$assert(strpos($successHelperSource, 'wp_kses_post(') === false, 'Pass Claims success-confirmation family should not use wp_kses_post().');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $successHelperSource), 'Pass Claims success-confirmation family should not use the broad post allowlist.');
$assert(strpos($successHelperSource, '$wpdb') === false && strpos($successHelperSource, 'get_post(') === false && strpos($successHelperSource, 'get_posts(') === false && strpos($successHelperSource, 'get_transient(') === false && strpos($successHelperSource, 'set_transient(') === false && strpos($successHelperSource, 'delete_transient(') === false, 'Pass Claims success-confirmation helper should not add new provider or storage operations.');
$assert(strpos($successHelperSource, 'wp_verify_nonce(') === false && strpos($successHelperSource, 'bvmgr_pass_claims_create_claim(') === false && strpos($successHelperSource, '$_POST') === false, 'Pass Claims success-confirmation helper should stay outside nonce validation, claim mutation, and direct request parsing.');

$assert(strpos($passClaimsSource, "bvmgr_pass_claims_render_public_success_confirmation(\$success, (string) \$posted['email']);") !== false, 'Pass Claims should route successful claims through the dedicated success-confirmation renderer.');
$assert(strpos($passClaimsSource, "bvmgr_pass_claims_render_public_shell(__('Pass Claimed', 'backstage-venue-manager'), \$html);") === false, 'Pass Claims should remove the old raw success html handoff.');
$assert(strpos($passClaimsSource, 'function bvmgr_pass_claims_public_status_allowed_html(): array') !== false, 'The accepted Pass Claims public status family should remain defined.');
$assert(strpos($passClaimsSource, 'function bvmgr_pass_claims_public_claimed_card_html(int $entry_id): string') !== false, 'The accepted Pass Claims already-claimed family should remain defined.');

$singleSuccess = array(
	'event_title' => 'Summer Fest',
	'event_date' => '2026-08-01',
	'venue_name' => 'Main Hall',
	'reference' => 'GL-17',
	'scan_url' => 'https://scan.example.test/pass?token=primary',
	'admission_token' => 'primary token"><script>',
	'admission_tokens' => array(
		array(
			'entry_id' => 17,
			'token' => 'primary token"><script>',
			'reference' => 'GL-17',
		),
	),
	'party_size' => 1,
	'email_sent' => true,
	'email_result' => array(),
);
$singlePassUrl = 'https://passes.example.test/view?token=primary token"><script>&context="quoted"<tag>';
$GLOBALS['vms_test_public_pass_url_return'] = $singlePassUrl;
$singleQrUrl = bvmgr_pass_claims_qr_image_url('vms-admission:' . (string) $singleSuccess['admission_token']);
$expectedSingleSuccess = '<h1>You Are Confirmed</h1>';
$expectedSingleSuccess .= '<div class="vms-pass-success">Your pass has been claimed and your reservation is confirmed.</div>';
$expectedSingleSuccess .= '<div class="vms-pass-ticket">';
$expectedSingleSuccess .= '<h2>Show this pass at the gate</h2>';
$expectedSingleSuccess .= '<div class="vms-pass-qr-wrap"><img class="vms-pass-qr" src="' . esc_url($singleQrUrl) . '" alt="Gate QR code"></div>';
$expectedSingleSuccess .= '<p class="vms-pass-meta"><strong>Event:</strong> Summer Fest</p>';
$expectedSingleSuccess .= '<p class="vms-pass-meta"><strong>Date:</strong> August 1, 2026</p>';
$expectedSingleSuccess .= '<p class="vms-pass-meta"><strong>Venue:</strong> Main Hall</p>';
$expectedSingleSuccess .= '<p class="vms-pass-meta"><strong>Reference:</strong> GL-17</p>';
$expectedSingleSuccess .= '<p class="vms-pass-actions"><a class="vms-pass-button" href="' . esc_url($singlePassUrl) . '" target="_blank" rel="noopener">View / Print Passes</a></p>';
$expectedSingleSuccess .= '<p class="vms-pass-hint">Screenshot this page or open it at the gate. Door staff can scan each QR code or search your name/phone.</p>';
$expectedSingleSuccess .= '</div>';
$expectedSingleSuccess .= '<p class="vms-pass-note">We also emailed a copy of this pass to the email address you entered.</p>';

$resetRuntime();
$GLOBALS['vms_test_public_pass_url_return'] = $singlePassUrl;
$assert(bvmgr_pass_claims_public_success_confirmation_html($singleSuccess, 'guest@example.test') === $expectedSingleSuccess, 'Pass Claims success-confirmation helper should preserve the single-pass success markup exactly.');
$assert($GLOBALS['vms_test_public_pass_url_calls'] === 1 && $GLOBALS['vms_test_public_pass_url_args'] === array(array('primary token"><script>', true)), 'Pass Claims success-confirmation helper should preserve the public pass URL lookup inputs.');
$assert($GLOBALS['vms_test_find_token_calls'] === 0 && $GLOBALS['vms_test_get_batch_calls'] === 0 && $GLOBALS['vms_test_create_claim_calls'] === 0 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0 && $GLOBALS['vms_test_nonce_calls'] === 0, 'Pass Claims success-confirmation helper should not perform token, batch, nonce, eligibility, rate-limit, or mutation work on its own.');

$resetRuntime();
$GLOBALS['vms_test_public_pass_url_return'] = $singlePassUrl;
$directSuccessRender = $captureShellRender(static function () use ($singleSuccess): void {
	bvmgr_pass_claims_render_public_success_confirmation($singleSuccess, 'guest@example.test');
});
$assert($directSuccessRender['headline'] === 'Pass Claimed', 'Pass Claims success renderer should preserve the success headline.');
$assert($directSuccessRender['content_html'] === $expectedSingleSuccess, 'Pass Claims success renderer should hand the exact single-pass markup to the public shell.');

$multiSuccess = array(
	'event_title' => '<b>Launch Night</b>',
	'event_date' => '<bad date>',
	'venue_name' => 'Venue <script>alert(1)</script>',
	'reference' => '<strong>GL-44</strong>',
	'scan_url' => '',
	'admission_token' => 'group-primary',
	'admission_tokens' => array(
		array(
			'entry_id' => 31,
			'token' => 'group one<script>',
			'reference' => '<em>Ref 1</em>',
		),
		array(
			'entry_id' => 32,
			'token' => '',
			'reference' => 'GL-32',
		),
		array(
			'entry_id' => 33,
			'token' => 'group three',
			'reference' => '<svg>Ref 3</svg>',
		),
	),
	'party_size' => 3,
	'email_sent' => false,
	'email_result' => array(
		'message' => '<a href="javascript:bad()">Mailbox full</a>',
	),
);
$multiQrOne = bvmgr_pass_claims_qr_image_url('vms-admission:group one<script>');
$multiQrThree = bvmgr_pass_claims_qr_image_url('vms-admission:group three');
$multiEmailMessage = sprintf(
	'Your pass is confirmed, but the email was not sent: %s. Screenshot this page and use it at the gate.',
	'<a href="javascript:bad()">Mailbox full</a>'
);
$expectedMultiSuccess = '<h1>You Are Confirmed</h1>';
$expectedMultiSuccess .= '<div class="vms-pass-success">Your pass has been claimed and your reservation is confirmed.</div>';
$expectedMultiSuccess .= '<div class="vms-pass-ticket">';
$expectedMultiSuccess .= '<h2>Show these passes at the gate</h2>';
$expectedMultiSuccess .= '<p class="vms-pass-hint">Each person has their own QR code, so your group can arrive separately.</p>';
$expectedMultiSuccess .= '<div class="vms-pass-qr-grid">';
$expectedMultiSuccess .= '<div class="vms-pass-qr-item"><strong>Pass 1 of 3</strong><img class="vms-pass-qr" src="' . esc_url($multiQrOne) . '" alt="Gate QR code"><span>&lt;em&gt;Ref 1&lt;/em&gt;</span></div>';
$expectedMultiSuccess .= '<div class="vms-pass-qr-item"><strong>Pass 3 of 3</strong><img class="vms-pass-qr" src="' . esc_url($multiQrThree) . '" alt="Gate QR code"><span>&lt;svg&gt;Ref 3&lt;/svg&gt;</span></div>';
$expectedMultiSuccess .= '</div>';
$expectedMultiSuccess .= '<p class="vms-pass-meta"><strong>Event:</strong> &lt;b&gt;Launch Night&lt;/b&gt;</p>';
$expectedMultiSuccess .= '<p class="vms-pass-meta"><strong>Date:</strong> &lt;bad date&gt;</p>';
$expectedMultiSuccess .= '<p class="vms-pass-meta"><strong>Venue:</strong> Venue &lt;script&gt;alert(1)&lt;/script&gt;</p>';
$expectedMultiSuccess .= '<p class="vms-pass-meta"><strong>Admissions:</strong> 3 individual passes</p>';
$expectedMultiSuccess .= '<p class="vms-pass-meta"><strong>Reference:</strong> &lt;strong&gt;GL-44&lt;/strong&gt;</p>';
$expectedMultiSuccess .= '<p class="vms-pass-hint">Screenshot this page or open it at the gate. Door staff can scan each QR code or search your name/phone.</p>';
$expectedMultiSuccess .= '</div>';
$expectedMultiSuccess .= '<p class="vms-pass-note vms-pass-note-warning">' . esc_html($multiEmailMessage) . '</p>';

$resetRuntime();
$assert(bvmgr_pass_claims_public_success_confirmation_html($multiSuccess, 'group@example.test') === $expectedMultiSuccess, 'Pass Claims success-confirmation helper should preserve the multi-pass success markup and warning branch exactly.');
$assert($GLOBALS['vms_test_public_pass_url_calls'] === 0, 'Pass Claims multi-pass success rendering without a scan URL should not request a public pass URL.');
$assert(strpos($expectedMultiSuccess, 'Pass 2 of 3') === false, 'Pass Claims multi-pass success rendering should continue skipping empty admission-token rows.');
foreach (array('<form', '<input', '<button', 'onclick=', 'onerror=', 'data-', 'aria-', '<script', '<style') as $forbidden) {
	$assert(strpos($expectedMultiSuccess, $forbidden) === false, 'Pass Claims success-confirmation helper should not introduce unsupported markup or attributes into the multi-pass output: ' . $forbidden);
}
$assert(strpos($expectedMultiSuccess, 'vms-pass-actions') === false, 'Pass Claims multi-pass success rendering without a scan URL should omit the action-button paragraph.');

$minimalSuccess = array(
	'event_title' => 'Quiet Show',
	'event_date' => '',
	'venue_name' => '',
	'reference' => 'GL-99',
	'scan_url' => '',
	'admission_token' => '',
	'admission_tokens' => array(),
	'party_size' => 1,
	'email_sent' => false,
	'email_result' => array(),
);
$expectedMinimalSuccess = '<h1>You Are Confirmed</h1>';
$expectedMinimalSuccess .= '<div class="vms-pass-success">Your pass has been claimed and your reservation is confirmed.</div>';
$expectedMinimalSuccess .= '<div class="vms-pass-ticket">';
$expectedMinimalSuccess .= '<h2>Show this pass at the gate</h2>';
$expectedMinimalSuccess .= '<p class="vms-pass-meta"><strong>Event:</strong> Quiet Show</p>';
$expectedMinimalSuccess .= '<p class="vms-pass-meta"><strong>Reference:</strong> GL-99</p>';
$expectedMinimalSuccess .= '<p class="vms-pass-hint">Screenshot this page or open it at the gate. Door staff can scan each QR code or search your name/phone.</p>';
$expectedMinimalSuccess .= '</div>';

$resetRuntime();
$assert(bvmgr_pass_claims_public_success_confirmation_html($minimalSuccess, '') === $expectedMinimalSuccess, 'Pass Claims success-confirmation helper should preserve the minimal success markup when optional data is absent.');
$assert(strpos($expectedMinimalSuccess, 'vms-pass-qr') === false && strpos($expectedMinimalSuccess, 'vms-pass-actions') === false && strpos($expectedMinimalSuccess, 'Admissions:') === false && strpos($expectedMinimalSuccess, 'Date:') === false && strpos($expectedMinimalSuccess, 'Venue:') === false && strpos($expectedMinimalSuccess, 'We also emailed') === false && strpos($expectedMinimalSuccess, 'email was not sent') === false, 'Pass Claims minimal success rendering should omit QR, actions, optional metadata, and email notes when the source data is absent.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $baseBatch(array(
	'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
));
$GLOBALS['vms_test_eligible_return'] = array(
	array(
		'id' => 11,
		'title' => 'Launch Event',
		'event_date' => '2026-08-01',
		'venue_name' => 'River Hall',
	),
);
$GLOBALS['vms_test_nonce_return'] = false;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'vms_pass_claim_submit' => '1',
	'_bvmgr_pass_claim_nonce' => 'bad-nonce',
	'first_name' => 'Ada',
	'last_name' => 'Lovelace',
	'phone' => '555-0100',
	'email' => 'ada@example.test',
	'event_plan_id' => '11',
	'party_size' => '1',
	'opt_in' => '1',
);
$invalidNonceRender = $captureShellRender(static function (): void {
	bvmgr_pass_claims_render_public_claim('invalid-nonce-token');
});
$assert($invalidNonceRender['headline'] === 'Claim Your Pass', 'Invalid nonce Pass Claims submission should remain on the interactive claim-form family.');
$assert(strpos($invalidNonceRender['content_html'], 'Invalid request. Please refresh and try again.') !== false && strpos($invalidNonceRender['content_html'], '<form method="post">') !== false, 'Invalid nonce Pass Claims submission should preserve the form family and its validation message.');
$assert(strpos($invalidNonceRender['content_html'], 'You Are Confirmed') === false, 'Invalid nonce Pass Claims submission should stay outside the success-confirmation family.');
$assert($GLOBALS['vms_test_nonce_calls'] === 2 && $GLOBALS['vms_test_nonce_args'] === array(array('bad-nonce', 'bvmgr_pass_claim_submit'), array('bad-nonce', 'vms_pass_claim_submit')), 'Invalid nonce Pass Claims submission should try the exact canonical action before its legacy fallback.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_eligible_calls'] === 1 && $GLOBALS['vms_test_create_claim_calls'] === 0 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Invalid nonce Pass Claims submission should stop before claim mutation while preserving the pre-form reads.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $baseBatch(array(
	'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
));
$GLOBALS['vms_test_eligible_return'] = array(
	array(
		'id' => 11,
		'title' => 'Launch Event',
		'event_date' => '2026-08-01',
		'venue_name' => 'River Hall',
	),
);
$GLOBALS['vms_test_nonce_return'] = true;
$GLOBALS['vms_test_create_claim_return'] = new WP_Error('claim_failed', '<b>Could not claim.</b>');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'vms_pass_claim_submit' => '1',
	'_bvmgr_pass_claim_nonce' => 'ok-nonce',
	'first_name' => 'Ada',
	'last_name' => 'Lovelace',
	'phone' => '555-0100',
	'email' => 'ada@example.test',
	'event_plan_id' => '11',
	'party_size' => '1',
	'opt_in' => '1',
);
$claimFailureRender = $captureShellRender(static function (): void {
	bvmgr_pass_claims_render_public_claim('claim-failure-token');
});
$assert($claimFailureRender['headline'] === 'Claim Your Pass', 'Failed Pass Claims submission should remain on the interactive claim-form family.');
$assert(strpos($claimFailureRender['content_html'], '&lt;b&gt;Could not claim.&lt;/b&gt;') !== false && strpos($claimFailureRender['content_html'], '<form method="post">') !== false, 'Failed Pass Claims submission should preserve the form family and escape the returned error message.');
$assert(strpos($claimFailureRender['content_html'], 'You Are Confirmed') === false, 'Failed Pass Claims submission should stay outside the success-confirmation family.');
$assert($GLOBALS['vms_test_create_claim_calls'] === 1 && $GLOBALS['vms_test_nonce_calls'] === 1 && $GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_eligible_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Failed Pass Claims submission should preserve the existing single claim-mutation attempt and pre-form reads.');

$routeSuccess = array(
	'event_title' => 'Route Event <b>X</b>',
	'event_date' => '2026-09-12',
	'venue_name' => 'Route Hall <script>',
	'reference' => 'GL-501',
	'scan_url' => 'https://scan.example.test/route?token=route',
	'admission_token' => 'route token',
	'admission_tokens' => array(
		array(
			'entry_id' => 501,
			'token' => 'route token',
			'reference' => 'GL-501',
		),
	),
	'party_size' => 1,
	'email_sent' => false,
	'email_result' => array(
		'message' => 'Mailbox <full>',
	),
);
$routePassUrl = 'https://passes.example.test/route?token=route token&unsafe="quote"<tag>';
$routeQrUrl = bvmgr_pass_claims_qr_image_url('vms-admission:route token');
$routeWarning = sprintf(
	'Your pass is confirmed, but the email was not sent: %s. Screenshot this page and use it at the gate.',
	'Mailbox <full>'
);
$expectedRouteSuccess = '<h1>You Are Confirmed</h1>';
$expectedRouteSuccess .= '<div class="vms-pass-success">Your pass has been claimed and your reservation is confirmed.</div>';
$expectedRouteSuccess .= '<div class="vms-pass-ticket">';
$expectedRouteSuccess .= '<h2>Show this pass at the gate</h2>';
$expectedRouteSuccess .= '<div class="vms-pass-qr-wrap"><img class="vms-pass-qr" src="' . esc_url($routeQrUrl) . '" alt="Gate QR code"></div>';
$expectedRouteSuccess .= '<p class="vms-pass-meta"><strong>Event:</strong> Route Event &lt;b&gt;X&lt;/b&gt;</p>';
$expectedRouteSuccess .= '<p class="vms-pass-meta"><strong>Date:</strong> September 12, 2026</p>';
$expectedRouteSuccess .= '<p class="vms-pass-meta"><strong>Venue:</strong> Route Hall &lt;script&gt;</p>';
$expectedRouteSuccess .= '<p class="vms-pass-meta"><strong>Reference:</strong> GL-501</p>';
$expectedRouteSuccess .= '<p class="vms-pass-actions"><a class="vms-pass-button" href="' . esc_url($routePassUrl) . '" target="_blank" rel="noopener">View / Print Passes</a></p>';
$expectedRouteSuccess .= '<p class="vms-pass-hint">Screenshot this page or open it at the gate. Door staff can scan each QR code or search your name/phone.</p>';
$expectedRouteSuccess .= '</div>';
$expectedRouteSuccess .= '<p class="vms-pass-note vms-pass-note-warning">' . esc_html($routeWarning) . '</p>';

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $baseBatch(array(
	'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
));
$GLOBALS['vms_test_eligible_return'] = array(
	array(
		'id' => 11,
		'title' => 'Route Event <b>X</b>',
		'event_date' => '2026-09-12',
		'venue_name' => 'Route Hall <script>',
	),
);
$GLOBALS['vms_test_nonce_return'] = true;
$GLOBALS['vms_test_create_claim_return'] = $routeSuccess;
$GLOBALS['vms_test_public_pass_url_return'] = $routePassUrl;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'vms_pass_claim_submit' => '1',
	'_bvmgr_pass_claim_nonce' => 'ok-nonce',
	'first_name' => 'Ada',
	'last_name' => 'Lovelace',
	'phone' => '555-0100',
	'email' => ' ada@example.test ',
	'event_plan_id' => '11',
	'party_size' => '1',
	'opt_in' => '1',
);
$successfulRouteRender = $captureShellRender(static function (): void {
	$GLOBALS['vms_test_query_vars']['vms_pass_claim_token'] = 'success-route-token';
	bvmgr_pass_claims_template_router();
});
$assert($successfulRouteRender['headline'] === 'Pass Claimed', 'Successful Pass Claims submission should preserve the success headline.');
$assert($successfulRouteRender['content_html'] === $expectedRouteSuccess, 'Successful Pass Claims submission should preserve the exact success-confirmation markup.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_eligible_calls'] === 1 && $GLOBALS['vms_test_create_claim_calls'] === 1 && $GLOBALS['vms_test_nonce_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Successful Pass Claims submission should preserve the existing success-path read and mutation counts.');
$assert($GLOBALS['vms_test_nonce_args'] === array(array('ok-nonce', 'bvmgr_pass_claim_submit')), 'Successful Pass Claims submission should validate the canonical nonce action without a legacy retry.');
$assert($GLOBALS['vms_test_public_pass_url_calls'] === 1 && $GLOBALS['vms_test_public_pass_url_args'] === array(array('route token', true)), 'Successful Pass Claims submission should preserve the public-pass URL lookup inputs.');
$assert($GLOBALS['vms_test_find_token_tokens'] === array('success-route-token'), 'Pass Claims template router should pass the resolved success-route token unchanged into the token lookup.');
$assert($GLOBALS['vms_test_get_batch_ids'] === array(9), 'Successful Pass Claims submission should preserve the batch lookup input.');
$assert(count($GLOBALS['vms_test_create_claim_args']) === 1, 'Successful Pass Claims submission should execute the claim mutation exactly once.');
$createClaimArgs = $GLOBALS['vms_test_create_claim_args'][0];
$assert($createClaimArgs[0] === $baseToken(), 'Successful Pass Claims submission should pass the resolved token row unchanged into claim creation.');
$assert($createClaimArgs[1] === $GLOBALS['vms_test_get_batch_return'], 'Successful Pass Claims submission should pass the resolved batch unchanged into claim creation.');
$assert($createClaimArgs[2] === $GLOBALS['vms_test_eligible_return'][0], 'Successful Pass Claims submission should pass the selected eligible event unchanged into claim creation.');
$assert($createClaimArgs[3] === array(
	'first_name' => 'Ada',
	'last_name' => 'Lovelace',
	'phone' => '555-0100',
	'email' => 'ada@example.test',
	'event_plan_id' => 11,
	'party_size' => 1,
	'opt_in' => 1,
), 'Successful Pass Claims submission should preserve the sanitized and normalized claim input passed into claim creation.');

fwrite(STDOUT, "Pass Claims public success output remediation OK.\n");
