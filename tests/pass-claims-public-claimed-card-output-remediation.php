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
	function vms_pass_claims_render_public_shell(string $headline, callable $render_content): void
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
	$assert(count($GLOBALS['vms_test_shell_calls']) === 1, 'The already-claimed Pass Claims family should render the shell exactly once per branch.');
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

$assert(strpos($passClaimsSource, 'function vms_pass_claims_render_public_shell(string $headline, callable $render_content): void') !== false, 'Pass Claims public shell should accept a renderer callback.');
$assert(strpos($passClaimsSource, "echo '<main id=\"primary\" class=\"site-main vms-pass-public-page\" role=\"main\">';") !== false, 'Pass Claims public shell should preserve the outer main wrapper.');
$assert(strpos($passClaimsSource, "echo '<div class=\"vms-pass-wrap\"><div class=\"vms-pass-card\">';") !== false, 'Pass Claims public shell should preserve the nested pass wrappers.');
$assert(strpos($passClaimsSource, '$render_content();') !== false, 'Pass Claims public shell should invoke the renderer callback at the content insertion point.');
$assert(strpos($passClaimsSource, 'echo $content_html;') === false, 'Pass Claims public shell should remove the raw content_html sink.');
$assert(strpos($passClaimsSource, "echo '</div></div>';") !== false && strpos($passClaimsSource, "echo '</main>';") !== false, 'Pass Claims public shell should preserve the closing wrappers.');
$assert(strpos($adminShellSource, 'echo $captured_notices_html;') !== false && strpos($adminShellSource, 'echo $content_html;') !== false, 'Administrator shell raw captured and content sinks should remain unchanged.');

$claimedHelperStart = strpos($passClaimsSource, 'function vms_pass_claims_public_claimed_card_html(int $entry_id): string');
$claimedHelperEnd = strpos($passClaimsSource, "if (!function_exists('vms_pass_claims_render_public_shell'))");
$assert($claimedHelperStart !== false && $claimedHelperEnd !== false && $claimedHelperEnd > $claimedHelperStart, 'Pass Claims claimed-card helper block should be locatable.');
$claimedHelperSource = substr($passClaimsSource, (int) $claimedHelperStart, (int) $claimedHelperEnd - (int) $claimedHelperStart);

$assert(strpos($claimedHelperSource, 'function vms_pass_claims_public_claimed_card_html(int $entry_id): string') !== false, 'Pass Claims should define a dedicated already-claimed card HTML helper.');
$assert(strpos($claimedHelperSource, 'function vms_pass_claims_render_public_claimed_card(int $entry_id): void') !== false, 'Pass Claims should define a dedicated already-claimed card renderer.');
$assert(strpos($claimedHelperSource, 'wp_kses(') === false, 'Pass Claims already-claimed card should rely on direct escaping rather than a local KSES contract.');
$assert(strpos($claimedHelperSource, 'wp_kses_post(') === false, 'Pass Claims already-claimed card should not use wp_kses_post().');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $claimedHelperSource), 'Pass Claims already-claimed card should not use the broad post allowlist.');
$assert(strpos($claimedHelperSource, '$wpdb') === false && strpos($claimedHelperSource, 'get_post(') === false && strpos($claimedHelperSource, 'get_posts(') === false && strpos($claimedHelperSource, 'get_transient(') === false && strpos($claimedHelperSource, 'set_transient(') === false && strpos($claimedHelperSource, 'delete_transient(') === false, 'Pass Claims already-claimed card helper should not add new provider or storage operations.');

$expectedClaimedNoReference = '<h1>Already Claimed</h1><p class="vms-pass-note">This pass has already been claimed.</p>';
$expectedClaimedWithReference = $expectedClaimedNoReference . '<p class="vms-pass-meta"><strong>Reference:</strong> GL-17</p>';
$assert(vms_pass_claims_public_claimed_card_html(0) === $expectedClaimedNoReference, 'Pass Claims already-claimed card should preserve the base heading and note when no admission reference exists.');
$assert(vms_pass_claims_public_claimed_card_html(17) === $expectedClaimedWithReference, 'Pass Claims already-claimed card should preserve the optional reference paragraph exactly.');
$assert(vms_pass_claims_public_claimed_card_html(-5) === $expectedClaimedNoReference, 'Pass Claims already-claimed card should only expose a reference paragraph for positive admission IDs.');

foreach (array('<a', '<button', '<form', '<input', '<img', '<ul', '<ol', '<table', 'data-', 'aria-', ' id=', ' style=') as $forbidden) {
	$assert(strpos($expectedClaimedWithReference, $forbidden) === false, 'Pass Claims already-claimed card should not introduce unsupported markup or attributes: ' . $forbidden);
}

$assert(strpos($passClaimsSource, 'vms_pass_claims_render_public_claimed_card((int) ($token_row[\'reservation_entry_id\'] ?? 0));') !== false, 'Pass Claims should route the claimed-token branch through the dedicated already-claimed renderer.');
$assert(strpos($passClaimsSource, '$claimed_html') === false, 'Pass Claims should remove the old claimed_html handoff variable from the public claimed branch.');
$assert(strpos($passClaimsSource, 'function vms_pass_claims_public_status_allowed_html(): array') !== false, 'The accepted Pass Claims public status family should remain defined.');

$resetRuntime();
$directClaimedCard = $captureShellRender(static function (): void {
	vms_pass_claims_render_public_claimed_card(17);
});
$assert($directClaimedCard['headline'] === 'Already Claimed', 'Pass Claims already-claimed renderer should preserve the public shell headline.');
$assert($directClaimedCard['content_html'] === $expectedClaimedWithReference, 'Pass Claims already-claimed renderer should preserve the full claimed-card markup.');
$assert($GLOBALS['vms_test_find_token_calls'] === 0 && $GLOBALS['vms_test_get_batch_calls'] === 0 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Pass Claims already-claimed renderer should not perform provider or storage reads on its own.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken(array(
	'status' => 'claimed',
	'reservation_entry_id' => 17,
));
$GLOBALS['vms_test_get_batch_return'] = $baseBatch(array(
	'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
));
$routeClaimed = $captureShellRender(static function (): void {
	$GLOBALS['vms_test_query_vars']['vms_pass_claim_token'] = 'claimed-route-token';
	vms_pass_claims_template_router();
});
$assert($routeClaimed['headline'] === 'Already Claimed', 'Pass Claims template router should preserve the already-claimed card headline.');
$assert($routeClaimed['content_html'] === $expectedClaimedWithReference, 'Pass Claims template router should route claimed tokens to the preserved already-claimed card markup.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Pass Claims already-claimed routing should stop after the token and batch lookups.');
$assert($GLOBALS['vms_test_find_token_tokens'] === array('claimed-route-token'), 'Pass Claims template router should pass the resolved claim token unchanged into the claimed-token lookup.');
$assert($GLOBALS['vms_test_get_batch_ids'] === array(9), 'Pass Claims claimed routing should preserve the batch lookup input.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken(array(
	'status' => 'claimed',
	'reservation_entry_id' => 0,
));
$GLOBALS['vms_test_get_batch_return'] = $baseBatch(array(
	'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
));
$claimedWithoutReference = $captureShellRender(static function (): void {
	vms_pass_claims_render_public_claim('claimed-no-reference');
});
$assert($claimedWithoutReference['content_html'] === $expectedClaimedNoReference, 'Pass Claims already-claimed rendering should omit the reference paragraph when no admission ID is linked.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Pass Claims already-claimed rendering without a reference should still stop before rate limiting and event eligibility.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken(array(
	'status' => 'claimed',
	'reservation_entry_id' => '<script>alert(1)</script>',
));
$GLOBALS['vms_test_get_batch_return'] = $baseBatch(array(
	'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
));
$claimedEscapedReference = $captureShellRender(static function (): void {
	vms_pass_claims_render_public_claim('claimed-escaped-reference');
});
$assert($claimedEscapedReference['content_html'] === $expectedClaimedNoReference, 'Pass Claims already-claimed rendering should keep HTML-like stored reservation IDs inert by casting them away from markup.');
foreach (array('<script', 'alert(1)', 'GL-') as $forbidden) {
	$assert(strpos($claimedEscapedReference['content_html'], $forbidden) === false, 'Pass Claims already-claimed rendering should not expose invalid stored reference content: ' . $forbidden);
}
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Pass Claims already-claimed rendering should not add reads when reservation_entry_id storage is malformed.');

fwrite(STDOUT, "Pass Claims already-claimed card output remediation OK.\n");
