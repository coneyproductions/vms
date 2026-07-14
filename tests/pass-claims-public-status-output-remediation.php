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

if (!function_exists('wp_unslash')) {
	function wp_unslash($value)
	{
		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}

		return is_string($value) ? stripslashes($value) : $value;
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
		return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

if (!function_exists('wp_kses')) {
	function wp_kses($html, $allowed_html): string
	{
		return (string) preg_replace_callback(
			'~<(/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*)>~',
			static function (array $matches) use ($allowed_html): string {
				$closing = $matches[1] === '/';
				$tag = strtolower((string) $matches[2]);
				if (!array_key_exists($tag, $allowed_html)) {
					return '';
				}
				if ($closing) {
					return '</' . $tag . '>';
				}

				$attrs = '';
				$allowed_attrs = is_array($allowed_html[$tag]) ? $allowed_html[$tag] : array();
				if ($allowed_attrs !== array()) {
					preg_match_all(
						'~\s+([a-zA-Z0-9:-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~',
						(string) ($matches[3] ?? ''),
						$attr_matches,
						PREG_SET_ORDER
					);

					foreach ($attr_matches as $attr_match) {
						$name = strtolower((string) $attr_match[1]);
						if (!array_key_exists($name, $allowed_attrs)) {
							continue;
						}

						$value = '';
						if (array_key_exists(2, $attr_match) && $attr_match[2] !== '') {
							$value = (string) $attr_match[2];
						} elseif (array_key_exists(3, $attr_match) && $attr_match[3] !== '') {
							$value = (string) $attr_match[3];
						} elseif (array_key_exists(4, $attr_match) && $attr_match[4] !== '') {
							$value = (string) $attr_match[4];
						}

						if ($name === 'href' && preg_match('~^\s*(?:javascript|data|vbscript):~i', html_entity_decode($value, ENT_QUOTES, 'UTF-8'))) {
							continue;
						}

						$attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
					}
				}

				return '<' . $tag . $attrs . '>';
			},
			(string) $html
		);
	}
}

if (!function_exists('vms_pass_claims_render_public_shell')) {
	function vms_pass_claims_render_public_shell(string $headline, string $content_html): void
	{
		$GLOBALS['vms_test_shell_calls'][] = array(
			'headline' => $headline,
			'content_html' => $content_html,
		);
		throw new VmsPassClaimsPublicShellRendered('rendered');
	}
}

if (!function_exists('vms_pass_claims_find_token_by_raw')) {
	function vms_pass_claims_find_token_by_raw(string $raw_token): ?array
	{
		$GLOBALS['vms_test_find_token_calls']++;
		$GLOBALS['vms_test_find_token_tokens'][] = $raw_token;
		$return = $GLOBALS['vms_test_find_token_return'];
		return is_array($return) ? $return : null;
	}
}

if (!function_exists('vms_pass_claims_get_batch_by_id')) {
	function vms_pass_claims_get_batch_by_id(int $batch_id): ?array
	{
		$GLOBALS['vms_test_get_batch_calls']++;
		$GLOBALS['vms_test_get_batch_ids'][] = $batch_id;
		$return = $GLOBALS['vms_test_get_batch_return'];
		return is_array($return) ? $return : null;
	}
}

if (!function_exists('vms_pass_claims_rate_limit_hit')) {
	function vms_pass_claims_rate_limit_hit(string $ip, string $token_public_key): bool
	{
		$GLOBALS['vms_test_rate_limit_calls']++;
		$GLOBALS['vms_test_rate_limit_args'][] = array($ip, $token_public_key);
		return !empty($GLOBALS['vms_test_rate_limit_return']);
	}
}

if (!function_exists('vms_pass_claims_eligible_events_for_batch')) {
	function vms_pass_claims_eligible_events_for_batch(array $batch): array
	{
		$GLOBALS['vms_test_eligible_calls']++;
		$GLOBALS['vms_test_eligible_batches'][] = $batch;
		return (array) $GLOBALS['vms_test_eligible_return'];
	}
}

if (!function_exists('vms_pass_claims_empty_events_notice')) {
	function vms_pass_claims_empty_events_notice(array $batch): array
	{
		$GLOBALS['vms_test_empty_notice_calls']++;
		$GLOBALS['vms_test_empty_notice_batches'][] = $batch;
		return (array) $GLOBALS['vms_test_empty_notice_return'];
	}
}

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
	);
};

$captureShellRender = static function (callable $callback) use ($assert): array {
	try {
		$callback();
	} catch (VmsPassClaimsPublicShellRendered $e) {
	}
	$assert(count($GLOBALS['vms_test_shell_calls']) === 1, 'Selected Pass Claims public family should render the shell exactly once per branch.');
	return $GLOBALS['vms_test_shell_calls'][0];
};

$assert(is_string($passClaimsSource) && $passClaimsSource !== '', 'Pass Claims source should be readable.');
$assert(is_string($adminShellSource) && $adminShellSource !== '', 'Administrator shell source should be readable.');

$assert(isset($GLOBALS['vms_test_actions']['init'][30]) && in_array('vms_pass_claims_register_rewrite', $GLOBALS['vms_test_actions']['init'][30], true), 'Pass Claims should register its public rewrite callback on init at priority 30.');
$assert(isset($GLOBALS['vms_test_actions']['template_redirect'][0]) && in_array('vms_pass_claims_template_router', $GLOBALS['vms_test_actions']['template_redirect'][0], true), 'Pass Claims should register its public template router on template_redirect at priority 0.');

$GLOBALS['vms_test_rewrite_tags'] = array();
$GLOBALS['vms_test_rewrite_rules'] = array();
vms_pass_claims_register_rewrite();
$assert($GLOBALS['vms_test_rewrite_tags'] === array(array('%vms_pass_claim_token%', '([^&]+)')), 'Pass Claims rewrite registration should preserve the claim-token tag.');
$assert($GLOBALS['vms_test_rewrite_rules'] === array(array('^pass/claim/([^/]+)/?$', 'index.php?vms_pass_claim_token=$matches[1]', 'top')), 'Pass Claims rewrite registration should preserve the public claim route.');

$resetRuntime();
$GLOBALS['vms_test_query_vars']['vms_pass_claim_token'] = 'query-token';
$assert(vms_pass_claims_get_request_token() === 'query-token', 'Pass Claims request token helper should prefer the registered query var.');

$resetRuntime();
$_GET['vms_pass_claim_token'] = 'get%20token';
$assert(vms_pass_claims_get_request_token() === 'get token', 'Pass Claims request token helper should fall back to the query-string token.');

$resetRuntime();
$_SERVER['REQUEST_URI'] = '/pass/claim/uri%20token?ref=1';
$assert(vms_pass_claims_get_request_token() === 'uri token', 'Pass Claims request token helper should fall back to the routed request URI token.');

$resetRuntime();
$GLOBALS['vms_test_is_admin'] = true;
$GLOBALS['vms_test_query_vars']['vms_pass_claim_token'] = 'ignored';
vms_pass_claims_template_router();
$assert($GLOBALS['vms_test_shell_calls'] === array(), 'Pass Claims template router should stay inactive in wp-admin contexts.');

$resetRuntime();
vms_pass_claims_template_router();
$assert($GLOBALS['vms_test_shell_calls'] === array(), 'Pass Claims template router should stay silent when no claim token is present.');

$assert(strpos($passClaimsSource, 'function vms_pass_claims_render_public_shell(string $headline, string $content_html): void') !== false, 'Pass Claims public shell should retain the raw content_html signature.');
$assert(strpos($passClaimsSource, "echo '<main id=\"primary\" class=\"site-main vms-pass-public-page\" role=\"main\">';") !== false, 'Pass Claims public shell should preserve the outer main wrapper.');
$assert(strpos($passClaimsSource, "echo '<div class=\"vms-pass-wrap\"><div class=\"vms-pass-card\">';") !== false, 'Pass Claims public shell should preserve the nested pass wrappers.');
$assert(strpos($passClaimsSource, 'echo $content_html;') !== false, 'Pass Claims public shell raw content sink should remain unchanged for unselected families.');
$assert(strpos($passClaimsSource, "echo '</div></div>';") !== false && strpos($passClaimsSource, "echo '</main>';") !== false, 'Pass Claims public shell should preserve the closing wrappers.');
$assert(strpos($adminShellSource, 'echo $captured_notices_html;') !== false && strpos($adminShellSource, 'echo $content_html;') !== false, 'Administrator shell raw captured and content sinks should remain unchanged.');

$statusHelperStart = strpos($passClaimsSource, 'function vms_pass_claims_public_status_allowed_html(): array');
$statusHelperEnd = strpos($passClaimsSource, "if (!function_exists('vms_pass_claims_render_public_shell'))");
$assert($statusHelperStart !== false && $statusHelperEnd !== false && $statusHelperEnd > $statusHelperStart, 'Pass Claims public status helper block should be locatable.');
$statusHelperSource = substr($passClaimsSource, (int) $statusHelperStart, (int) $statusHelperEnd - (int) $statusHelperStart);

$assert(strpos($statusHelperSource, 'function vms_pass_claims_public_status_allowed_html(): array') !== false, 'Pass Claims should define a dedicated public status allowlist helper.');
$assert(strpos($statusHelperSource, 'function vms_pass_claims_public_status_fragment(string $title, string $message): string') !== false, 'Pass Claims should define a dedicated public status fragment helper.');
$assert(strpos($statusHelperSource, 'function vms_pass_claims_render_public_status_screen(string $headline, string $title, string $message): void') !== false, 'Pass Claims should define a dedicated public status screen renderer.');
$assert(preg_match('~return\s+wp_kses\s*\(\s*\$html\s*,\s*vms_pass_claims_public_status_allowed_html\s*\(\s*\)\s*\)\s*;~', $statusHelperSource) === 1, 'Pass Claims public status fragment should apply its dedicated allowlist at the local family boundary.');
$assert(strpos($statusHelperSource, 'wp_kses_post(') === false, 'Pass Claims public status fragment should not use wp_kses_post().');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $statusHelperSource), 'Pass Claims public status fragment should not use the broad post allowlist.');
$assert(strpos($statusHelperSource, '$wpdb') === false && strpos($statusHelperSource, 'get_post(') === false && strpos($statusHelperSource, 'get_posts(') === false && strpos($statusHelperSource, 'get_transient(') === false && strpos($statusHelperSource, 'set_transient(') === false && strpos($statusHelperSource, 'delete_transient(') === false, 'Pass Claims public status helper should not add new provider or storage operations.');

$assert(vms_pass_claims_public_status_allowed_html() === array(
	'h1' => array(),
	'p' => array(
		'class' => true,
	),
), 'Pass Claims public status allowlist should contain only h1 and p[class].');

$expectedStatusFragment = '<h1>Pass Not Found</h1><p class="vms-pass-error">This pass link is invalid or has expired.</p>';
$assert(vms_pass_claims_public_status_fragment('Pass Not Found', 'This pass link is invalid or has expired.') === $expectedStatusFragment, 'Pass Claims public status fragment should preserve the fixed invalid-link markup.');

$escapedStatusFragment = vms_pass_claims_public_status_fragment('<strong>Bad</strong>', '<script>alert(1)</script>');
$assert($escapedStatusFragment === '<h1>&lt;strong&gt;Bad&lt;/strong&gt;</h1><p class="vms-pass-error">&lt;script&gt;alert(1)&lt;/script&gt;</p>', 'Pass Claims public status fragment should keep HTML-like title and message text inert.');

$unsafeStatusHtml = '<h1 onclick="evil()">Please Wait</h1><p class="vms-pass-error" data-note="x" aria-live="assertive">Too many<script>alert(1)</script><span>bad</span></p><form><input type="hidden" value="1"></form>';
$filteredStatusHtml = wp_kses($unsafeStatusHtml, vms_pass_claims_public_status_allowed_html());
$assert(strpos($filteredStatusHtml, '<h1>Please Wait</h1>') !== false, 'Pass Claims public status contract should preserve the heading.');
$assert(strpos($filteredStatusHtml, '<p class="vms-pass-error">Too manyalert(1)bad</p>') !== false, 'Pass Claims public status contract should preserve only the allowed paragraph wrapper and text.');
foreach (array('onclick=', 'data-note=', 'aria-live=', '<script', '<span', '<form', '<input') as $forbidden) {
	$assert(strpos($filteredStatusHtml, $forbidden) === false, 'Pass Claims public status contract should reject unsupported markup or attributes: ' . $forbidden);
}

$allowlistUseLines = array();
foreach ((array) preg_split('/\R/', $statusHelperSource) as $line) {
	if (strpos($line, 'vms_pass_claims_public_status_allowed_html()') === false || strpos($line, 'function vms_pass_claims_public_status_allowed_html') !== false) {
		continue;
	}
	$allowlistUseLines[] = $line;
	$assert(preg_match('~wp_kses\s*\(\s*\$html\s*,\s*vms_pass_claims_public_status_allowed_html\s*\(\s*\)\s*\)~', $line) === 1, 'Pass Claims public status allowlist should only be applied directly to the local status fragment.');
}
$assert(count($allowlistUseLines) === 1, 'Pass Claims public status allowlist should be used exactly once outside its definition.');

$assert(substr_count($passClaimsSource, 'vms_pass_claims_render_public_status_screen(') === 7, 'Pass Claims should route exactly the six selected public status branches through the dedicated status renderer.');
$assert(strpos($passClaimsSource, "vms_pass_claims_render_public_shell(__('Claim Pass', 'backstage-venue-manager'), '<h1>' . esc_html__('Pass Not Found'") === false, 'The invalid-token status branch should no longer hand raw concatenated status HTML directly to the public shell.');

$resetRuntime();
$routeInvalid = $captureShellRender(static function (): void {
	$GLOBALS['vms_test_query_vars']['vms_pass_claim_token'] = 'route-token';
	vms_pass_claims_template_router();
});
$assert($routeInvalid['headline'] === 'Claim Pass', 'Pass Claims template router should preserve the status-screen headline.');
$assert($routeInvalid['content_html'] === $expectedStatusFragment, 'Pass Claims template router should route invalid tokens to the preserved public status fragment.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 0 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Invalid-token Pass Claims routing should stop after the token lookup and render exactly once.');
$assert($GLOBALS['vms_test_find_token_tokens'] === array('route-token'), 'Pass Claims template router should pass the resolved token through unchanged to the token lookup.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$batchMissing = $captureShellRender(static function (): void {
	vms_pass_claims_render_public_claim('batch-missing');
});
$assert($batchMissing['content_html'] === '<h1>Batch Not Found</h1><p class="vms-pass-error">This pass batch is no longer available.</p>', 'Pass Claims batch-missing status screen should preserve its markup and wording.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Batch-missing Pass Claims status rendering should stop after the batch lookup.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $baseBatch(array('status' => 'paused'));
$inactiveBatch = $captureShellRender(static function (): void {
	vms_pass_claims_render_public_claim('inactive-token');
});
$assert($inactiveBatch['content_html'] === '<h1>Pass Unavailable</h1><p class="vms-pass-error">This pass is not currently active.</p>', 'Pass Claims unavailable status screen should preserve its markup and wording.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 0, 'Unavailable Pass Claims status rendering should stop before rate limiting and event-eligibility checks.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $baseBatch(array('expires_at' => gmdate('Y-m-d H:i:s', time() - 3600)));
$expiredBatch = $captureShellRender(static function (): void {
	vms_pass_claims_render_public_claim('expired-token');
});
$assert($expiredBatch['content_html'] === '<h1>Pass Expired</h1><p class="vms-pass-error">This pass link has expired.</p>', 'Pass Claims expired-token status screen should preserve its markup and wording.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 0, 'Expired Pass Claims status rendering should stop before rate-limiting and event-eligibility checks.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $baseBatch(array('expires_at' => gmdate('Y-m-d H:i:s', time() + 3600)));
$GLOBALS['vms_test_rate_limit_return'] = true;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$rateLimited = $captureShellRender(static function (): void {
	vms_pass_claims_render_public_claim('rate-limited-token');
});
$assert($rateLimited['content_html'] === '<h1>Please Wait</h1><p class="vms-pass-error">Too many attempts. Please try again shortly.</p>', 'Pass Claims rate-limit status screen should preserve its markup and wording.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 1 && $GLOBALS['vms_test_eligible_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Rate-limited Pass Claims status rendering should stop after the rate-limit check.');
$assert($GLOBALS['vms_test_rate_limit_args'] === array(array('127.0.0.1', 'pubkey')), 'Pass Claims rate-limit status rendering should preserve the existing IP and token-public-key rate-limit inputs.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $baseBatch(array('expires_at' => gmdate('Y-m-d H:i:s', time() + 3600)));
$GLOBALS['vms_test_eligible_return'] = array();
$emptyEligible = $captureShellRender(static function (): void {
	vms_pass_claims_render_public_claim('empty-events-token');
});
$assert($emptyEligible['content_html'] === '<h1>No Eligible Events</h1><p class="vms-pass-error">There are no eligible published events for this pass right now.</p>', 'Pass Claims empty-events status screen should preserve its default markup and wording.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 1 && $GLOBALS['vms_test_empty_notice_calls'] === 1, 'Empty-events Pass Claims status rendering should stop after the eligibility and empty-notice lookups.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $baseBatch(array('expires_at' => gmdate('Y-m-d H:i:s', time() + 3600)));
$GLOBALS['vms_test_eligible_return'] = array();
$GLOBALS['vms_test_empty_notice_return'] = array(
	'title' => '<strong>Event Cancelled</strong>',
	'message' => '<script>alert(1)</script>',
);
$escapedEmptyEligible = $captureShellRender(static function (): void {
	vms_pass_claims_render_public_claim('empty-events-escaped-token');
});
$assert($escapedEmptyEligible['content_html'] === '<h1>&lt;strong&gt;Event Cancelled&lt;/strong&gt;</h1><p class="vms-pass-error">&lt;script&gt;alert(1)&lt;/script&gt;</p>', 'Pass Claims empty-events status rendering should keep provider-supplied HTML-like notice text inert.');

fwrite(STDOUT, "Pass Claims public status output remediation OK.\n");
