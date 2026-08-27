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

if (!function_exists('is_wp_error')) {
	function is_wp_error($thing): bool
	{
		return $thing instanceof WP_Error;
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

require_once dirname(__DIR__) . '/includes/modules/admissions/pass-claims.php';

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';
$passClaimsSource = file_get_contents($pluginRoot . '/includes/modules/admissions/pass-claims.php');
$livePassClaimsSource = file_get_contents($livePluginRoot . '/includes/modules/admissions/pass-claims.php');
$adminShellSource = file_get_contents($pluginRoot . '/includes/admin-ui/shell.php');
$publicJsSource = file_get_contents($pluginRoot . '/assets/js/vms-pass-claims-public.js');
$publicCssSource = file_get_contents($pluginRoot . '/assets/css/vms-pass-claims-public.css');

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
		'batch_name' => 'Fall <b>Batch</b>',
	);
};

$formEvents = static function (): array {
	return array(
		array(
			'id' => 11,
			'title' => '<i>Launch Event</i>',
			'event_date' => '2026-09-12',
			'venue_name' => 'Main <script>Hall</script>',
		),
		array(
			'id' => 12,
			'title' => 'Late Show',
			'event_date' => '',
			'venue_name' => '',
		),
	);
};

$expectedFormHtml = static function (array $batch, array $eligibleEvents, array $posted, string $error, int $maxPartySize): string {
	$html = '<h1>Claim Your Pass</h1>';
	$html .= '<p class="vms-pass-meta">Complete this claim before arrival. Door staff can only check in claimed reservations.</p>';
	$html .= '<p class="vms-pass-note"><strong>Batch:</strong> ' . esc_html((string) ($batch['batch_name'] ?? '')) . '</p>';
	if ($error !== '') {
		$html .= '<p class="vms-pass-error">' . esc_html($error) . '</p>';
	}

	$html .= '<form method="post">';
	$html .= '<input type="hidden" name="_vms_pass_claim_nonce" value="nonce">';
	$html .= '<div class="vms-pass-grid">';
	$html .= '<label>First Name<input type="text" name="first_name" value="' . esc_attr((string) ($posted['first_name'] ?? '')) . '" required></label>';
	$html .= '<label>Last Name<input type="text" name="last_name" value="' . esc_attr((string) ($posted['last_name'] ?? '')) . '" required></label>';
	$html .= '<label>Phone<input type="text" name="phone" value="' . esc_attr((string) ($posted['phone'] ?? '')) . '" required></label>';
	$html .= '<label>Email (optional)<input type="email" name="email" value="' . esc_attr((string) ($posted['email'] ?? '')) . '"></label>';
	$html .= '<label class="vms-pass-span-2">Select Event<select name="event_plan_id" required>';
	$html .= '<option value="">Choose an event</option>';
		foreach ($eligibleEvents as $event) {
			$eventId = (int) ($event['id'] ?? 0);
			$label = (string) ($event['title'] ?? 'Event');
			if (!empty($event['event_date'])) {
				$label .= ' (' . bvmgr_pass_claims_format_public_date((string) $event['event_date']) . ')';
			}
			if (!empty($event['venue_name'])) {
				$label .= ' - ' . (string) $event['venue_name'];
			}
			$html .= '<option value="' . esc_attr((string) $eventId) . '"' . selected((int) ($posted['event_plan_id'] ?? 0), $eventId, false) . '>' . esc_html($label) . '</option>';
		}
	$html .= '</select></label>';
	$html .= '<label class="vms-pass-span-2">How many people will use this pass?<span class="vms-pass-number-control"><button type="button" class="vms-pass-number-control__button" data-vms-pass-party-decrease aria-label="Decrease party size">-</button><input type="number" name="party_size" min="1" max="' . esc_attr((string) $maxPartySize) . '" step="1" inputmode="numeric" data-vms-pass-party-size value="' . esc_attr((string) max(1, min($maxPartySize, (int) ($posted['party_size'] ?? 1)))) . '"><button type="button" class="vms-pass-number-control__button" data-vms-pass-party-increase aria-label="Increase party size">+</button></span><span class="vms-pass-field-help">This link can admit up to ' . esc_html((string) $maxPartySize) . ' people.</span></label>';
	$html .= '<label class="vms-pass-span-2 vms-pass-checkbox"><input type="checkbox" name="opt_in" value="1"' . checked(1, (int) ($posted['opt_in'] ?? 0), false) . '> <span>Send me event updates and reminders (optional). Your pass email is sent automatically if you enter an email.</span></label>';
	$html .= '</div>';
	$html .= '<p class="vms-pass-actions"><button type="submit" name="vms_pass_claim_submit" value="1">Claim Pass</button></p>';
	$html .= '</form>';

	return $html;
};

$captureShellRender = static function (callable $callback) use ($assert): array {
	try {
		$callback();
	} catch (VmsPassClaimsPublicShellRendered $e) {
	}
	$assert(count($GLOBALS['vms_test_shell_calls']) === 1, 'The selected Pass Claims public form family should render the shell exactly once per branch.');
	return $GLOBALS['vms_test_shell_calls'][0];
};

$assert(is_string($passClaimsSource) && $passClaimsSource !== '', 'Pass Claims source should be readable.');
$assert(is_string($livePassClaimsSource) && $livePassClaimsSource !== '', 'Live Pass Claims source should be readable.');
$assert(is_string($adminShellSource) && $adminShellSource !== '', 'Administrator shell source should be readable.');
$assert(is_string($publicJsSource) && $publicJsSource !== '', 'Pass Claims public JS source should be readable.');
$assert(is_string($publicCssSource) && $publicCssSource !== '', 'Pass Claims public CSS source should be readable.');

$assert(isset($GLOBALS['vms_test_actions']['init'][30]) && in_array('vms_pass_claims_register_rewrite', $GLOBALS['vms_test_actions']['init'][30], true), 'Pass Claims should register its public rewrite callback on init at priority 30.');
$assert(isset($GLOBALS['vms_test_actions']['template_redirect'][0]) && in_array('vms_pass_claims_template_router', $GLOBALS['vms_test_actions']['template_redirect'][0], true), 'Pass Claims should register its public template router on template_redirect at priority 0.');

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

$assert(strpos($passClaimsSource, '$_SERVER') === false, 'Mirror Pass Claims runtime should not retain direct $_SERVER reads.');
$assert(strpos($livePassClaimsSource, '$_SERVER') === false, 'Live Pass Claims runtime should not retain direct $_SERVER reads.');
$assert(substr_count($passClaimsSource, 'vms_request_current_uri()') === 1, 'Mirror Pass Claims runtime should use the shared current-URI helper for claim-token routing.');
$assert(substr_count($livePassClaimsSource, 'vms_request_current_uri()') === 2, 'Live Pass Claims runtime should use the shared current-URI helper for claim and invite routing.');
$assert(substr_count($passClaimsSource, 'vms_request_remote_addr()') === 2, 'Mirror Pass Claims runtime should use the shared remote-address helper at each claim boundary.');
$assert(substr_count($livePassClaimsSource, 'vms_request_remote_addr()') === 2, 'Live Pass Claims runtime should use the shared remote-address helper at each claim boundary.');
$assert(substr_count($passClaimsSource, 'vms_request_user_agent()') === 1, 'Mirror Pass Claims runtime should use the shared user-agent helper during claim persistence.');
$assert(substr_count($livePassClaimsSource, 'vms_request_user_agent()') === 1, 'Live Pass Claims runtime should use the shared user-agent helper during claim persistence.');
$assert(substr_count($passClaimsSource, "vms_request_method() === 'post'") === 1, 'Mirror Pass Claims runtime should preserve the POST-only public submit gate through the shared method helper.');
$assert(substr_count($livePassClaimsSource, "vms_request_method() === 'post'") === 1, 'Live Pass Claims runtime should preserve the POST-only public submit gate through the shared method helper.');
$assert(strpos($passClaimsSource, "vms_pass_claims_rate_limit_hit(\$ip, (string) (\$token_row['token_public_key'] ?? ''))") !== false, 'Mirror Pass Claims runtime should preserve the rate-limit public-key handoff.');
$assert(strpos($livePassClaimsSource, "vms_pass_claims_rate_limit_hit(\$ip, (string) (\$token_row['token_public_key'] ?? ''))") !== false, 'Live Pass Claims runtime should preserve the rate-limit public-key handoff.');

$resetRuntime();
$GLOBALS['vms_test_is_admin'] = true;
$GLOBALS['vms_test_query_vars']['vms_pass_claim_token'] = 'ignored';
bvmgr_pass_claims_template_router();
$assert($GLOBALS['vms_test_shell_calls'] === array(), 'Pass Claims template router should stay inactive in wp-admin contexts.');

$resetRuntime();
bvmgr_pass_claims_template_router();
$assert($GLOBALS['vms_test_shell_calls'] === array(), 'Pass Claims template router should stay silent when no claim token is present.');

$assert(strpos($passClaimsSource, 'function vms_pass_claims_render_public_shell(string $headline, callable $render_content): void') !== false, 'Pass Claims public shell should accept a renderer callback.');
$assert(strpos($passClaimsSource, "echo '<main id=\"primary\" class=\"site-main vms-pass-public-page\" role=\"main\">';") !== false, 'Pass Claims public shell should preserve the outer main wrapper.');
$assert(strpos($passClaimsSource, "echo '<div class=\"vms-pass-wrap\"><div class=\"vms-pass-card\">';") !== false, 'Pass Claims public shell should preserve the nested pass wrappers.');
$assert(strpos($passClaimsSource, '$render_content();') !== false, 'Pass Claims public shell should invoke the renderer callback at the content insertion point.');
$assert(strpos($passClaimsSource, 'echo $content_html;') === false, 'Pass Claims public shell should remove the raw content_html sink.');
$assert(strpos($adminShellSource, 'echo $captured_notices_html;') !== false && strpos($adminShellSource, 'echo $content_html;') !== false, 'Administrator shell raw captured and content sinks should remain unchanged.');

$formHelperStart = strpos($passClaimsSource, 'function vms_pass_claims_public_form_html(array $batch, array $eligible_events, array $posted, string $error, int $max_party_size): string');
$formHelperEnd = strpos($passClaimsSource, "if (!function_exists('vms_pass_claims_render_public_shell'))");
$assert($formHelperStart !== false && $formHelperEnd !== false && $formHelperEnd > $formHelperStart, 'Pass Claims public form helper block should be locatable.');
$formHelperSource = substr($passClaimsSource, (int) $formHelperStart, (int) $formHelperEnd - (int) $formHelperStart);

$assert(strpos($formHelperSource, 'function vms_pass_claims_public_form_html(array $batch, array $eligible_events, array $posted, string $error, int $max_party_size): string') !== false, 'Pass Claims should define a dedicated public form HTML helper.');
$assert(strpos($formHelperSource, 'function vms_pass_claims_render_public_form(array $batch, array $eligible_events, array $posted, string $error, int $max_party_size): void') !== false, 'Pass Claims should define a dedicated public form renderer.');
$assert(strpos($formHelperSource, 'wp_kses(') === false, 'Pass Claims public form family should rely on direct escaping rather than a local KSES contract.');
$assert(strpos($formHelperSource, 'wp_kses_post(') === false, 'Pass Claims public form family should not use wp_kses_post().');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $formHelperSource), 'Pass Claims public form family should not use the broad post allowlist.');
$assert(strpos($formHelperSource, '$wpdb') === false && strpos($formHelperSource, 'get_post(') === false && strpos($formHelperSource, 'get_posts(') === false && strpos($formHelperSource, 'get_transient(') === false && strpos($formHelperSource, 'set_transient(') === false && strpos($formHelperSource, 'delete_transient(') === false, 'Pass Claims public form helper should not add provider or storage operations.');
$assert(strpos($formHelperSource, 'wp_verify_nonce(') === false && strpos($formHelperSource, 'vms_pass_claims_create_claim(') === false && strpos($formHelperSource, '$_POST') === false, 'Pass Claims public form helper should stay outside nonce verification, mutation, and direct request parsing.');
$assert(strpos($formHelperSource, "wp_nonce_field('vms_pass_claim_submit', '_vms_pass_claim_nonce', true, false)") !== false, 'Pass Claims public form helper should preserve the exact nonce helper call.');

$assert(strpos($passClaimsSource, 'vms_pass_claims_render_public_form($batch, $eligible_events, $posted, $error, $max_party_size);') !== false, 'Pass Claims should route the interactive form family through the dedicated public form renderer.');
$assert(strpos($passClaimsSource, "vms_pass_claims_render_public_shell(__('Claim Your Pass', 'backstage-venue-manager'), \$html);") === false, 'Pass Claims should remove the old raw form html handoff.');
$assert(strpos($passClaimsSource, 'function vms_pass_claims_public_status_allowed_html(): array') !== false, 'The accepted Pass Claims public status family should remain defined.');
$assert(strpos($passClaimsSource, 'function vms_pass_claims_public_claimed_card_html(int $entry_id): string') !== false, 'The accepted Pass Claims already-claimed family should remain defined.');
$assert(strpos($passClaimsSource, 'function vms_pass_claims_public_success_confirmation_html(array $success, string $posted_email): string') !== false, 'The accepted Pass Claims success family should remain defined.');

$assert(strpos($publicJsSource, '[data-vms-pass-party-decrease]') !== false, 'Pass Claims public JS should preserve the decrease-button selector.');
$assert(strpos($publicJsSource, '[data-vms-pass-party-increase]') !== false, 'Pass Claims public JS should preserve the increase-button selector.');
$assert(strpos($publicJsSource, '.vms-pass-number-control') !== false, 'Pass Claims public JS should preserve the number-control wrapper selector.');
$assert(strpos($publicJsSource, '[data-vms-pass-party-size]') !== false, 'Pass Claims public JS should preserve the party-size input selector.');
foreach (array('.vms-pass-grid', '.vms-pass-span-2', '.vms-pass-actions', '.vms-pass-checkbox', '.vms-pass-field-help', '.vms-pass-number-control', '.vms-pass-number-control__button') as $selector) {
	$assert(strpos($publicCssSource, $selector) !== false, 'Pass Claims public CSS should preserve selector: ' . $selector);
}

$batch = $baseBatch();
$events = $formEvents();
$maxPartySize = 3;
$defaultPosted = array(
	'first_name' => '',
	'last_name' => '',
	'phone' => '',
	'email' => '',
	'event_plan_id' => '',
	'party_size' => 1,
	'opt_in' => 0,
);
$expectedInitialForm = $expectedFormHtml($batch, $events, $defaultPosted, '', $maxPartySize);

$assert(bvmgr_pass_claims_public_form_html($batch, $events, $defaultPosted, '', $maxPartySize) === $expectedInitialForm, 'Pass Claims public form helper should preserve the initial GET form markup exactly.');
foreach (array('<a', 'href=', 'action=', 'id=', 'style=', 'onclick=', 'onchange=', 'data-extra=', 'aria-describedby=', '<script', '<img', '<ul', '<table') as $forbidden) {
	$assert(strpos($expectedInitialForm, $forbidden) === false, 'Pass Claims public form contract should not introduce unsupported markup or attributes: ' . $forbidden);
}
$assert(strpos($expectedInitialForm, 'data-vms-pass-party-decrease') !== false && strpos($expectedInitialForm, 'data-vms-pass-party-increase') !== false && strpos($expectedInitialForm, 'data-vms-pass-party-size') !== false, 'Pass Claims public form contract should preserve the existing JS data selectors.');
$assert(strpos($expectedInitialForm, 'aria-label="Decrease party size"') !== false && strpos($expectedInitialForm, 'aria-label="Increase party size"') !== false, 'Pass Claims public form contract should preserve the existing ARIA button labels.');
$assert(substr_count($expectedInitialForm, '<form method="post">') === 1 && substr_count($expectedInitialForm, 'name="_vms_pass_claim_nonce"') === 1 && substr_count($expectedInitialForm, 'name="vms_pass_claim_submit" value="1"') === 1, 'Pass Claims public form contract should preserve one form, one nonce field, and one submit control.');
$assert(strpos($expectedInitialForm, '&lt;i&gt;Launch Event&lt;/i&gt; (September 12, 2026) - Main &lt;script&gt;Hall&lt;/script&gt;') !== false, 'Pass Claims public form contract should keep HTML-like event labels inert.');
$assert(strpos($expectedInitialForm, 'checked="checked"') === false && strpos($expectedInitialForm, 'selected="selected"') === false, 'Pass Claims initial GET form should start with no selected event and no checked opt-in state.');
$assert(strpos($expectedInitialForm, 'value="1"') !== false, 'Pass Claims initial GET form should preserve the default party-size value.');
$assert($GLOBALS['vms_test_find_token_calls'] === 0 && $GLOBALS['vms_test_get_batch_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 0 && $GLOBALS['vms_test_nonce_calls'] === 0 && $GLOBALS['vms_test_create_claim_calls'] === 0 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Pass Claims public form helper should not perform routing, provider, nonce, or mutation work on its own.');

$resetRuntime();
$directFormRender = $captureShellRender(static function () use ($batch, $events, $defaultPosted, $maxPartySize): void {
	bvmgr_pass_claims_render_public_form($batch, $events, $defaultPosted, '', $maxPartySize);
});
$assert($directFormRender['headline'] === 'Claim Your Pass', 'Pass Claims public form renderer should preserve the public shell headline.');
$assert($directFormRender['content_html'] === $expectedInitialForm, 'Pass Claims public form renderer should hand the exact form markup to the public shell.');
$assert($GLOBALS['vms_test_find_token_calls'] === 0 && $GLOBALS['vms_test_get_batch_calls'] === 0 && $GLOBALS['vms_test_eligible_calls'] === 0 && $GLOBALS['vms_test_nonce_calls'] === 0 && $GLOBALS['vms_test_create_claim_calls'] === 0 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Pass Claims public form renderer should not add provider, nonce, or mutation work.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $batch;
$GLOBALS['vms_test_eligible_return'] = $events;
$initialGetRender = $captureShellRender(static function (): void {
	bvmgr_pass_claims_render_public_claim('initial-get-token');
});
$assert($initialGetRender['headline'] === 'Claim Your Pass', 'Initial GET Pass Claims route should enter the interactive form family.');
$assert($initialGetRender['content_html'] === $expectedInitialForm, 'Initial GET Pass Claims route should preserve the complete default form markup.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_eligible_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0 && $GLOBALS['vms_test_nonce_calls'] === 0 && $GLOBALS['vms_test_create_claim_calls'] === 0, 'Initial GET Pass Claims form rendering should stop after the token, batch, and eligible-event reads.');

$invalidNonceExpected = $expectedFormHtml($batch, $events, $defaultPosted, 'Invalid request. Please refresh and try again.', $maxPartySize);
$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $batch;
$GLOBALS['vms_test_eligible_return'] = $events;
$GLOBALS['vms_test_nonce_return'] = false;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'vms_pass_claim_submit' => '1',
	'_vms_pass_claim_nonce' => 'bad-nonce',
	'first_name' => '<b>Ada</b>',
	'last_name' => '<i>Lovelace</i>',
	'phone' => '555-0100',
	'email' => 'ada@example.test',
	'event_plan_id' => '11',
	'party_size' => '2',
	'opt_in' => '1',
);
$invalidNonceRender = $captureShellRender(static function (): void {
	bvmgr_pass_claims_render_public_claim('invalid-nonce-token');
});
$assert($invalidNonceRender['headline'] === 'Claim Your Pass', 'Invalid nonce Pass Claims submission should remain in the interactive form family.');
$assert($invalidNonceRender['content_html'] === $invalidNonceExpected, 'Invalid nonce Pass Claims submission should preserve the exact error form markup without reposted values.');
$assert($GLOBALS['vms_test_nonce_calls'] === 1 && $GLOBALS['vms_test_nonce_args'] === array(array('bad-nonce', 'vms_pass_claim_submit')), 'Invalid nonce Pass Claims submission should preserve the exact nonce validation path.');
$assert($GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_eligible_calls'] === 1 && $GLOBALS['vms_test_create_claim_calls'] === 0 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Invalid nonce Pass Claims submission should stop before claim mutation while preserving the pre-form reads.');

$invalidEventPosted = array(
	'first_name' => 'Ada "Quoted" Tag',
	'last_name' => 'Lovelace & Co.',
	'phone' => '555-0100 "quoted"',
	'email' => 'adascripttagscript@example.test',
	'event_plan_id' => 99,
	'party_size' => 2,
	'opt_in' => 1,
);
$invalidEventExpected = $expectedFormHtml($batch, $events, $invalidEventPosted, 'Please choose a valid event.', $maxPartySize);
$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $batch;
$GLOBALS['vms_test_eligible_return'] = $events;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'vms_pass_claim_submit' => '1',
	'_vms_pass_claim_nonce' => 'ok-nonce',
	'first_name' => 'Ada "Quoted" <b>Tag</b>',
	'last_name' => 'Lovelace & Co.',
	'phone' => '555-0100 "quoted"',
	'email' => 'ada<script>tag</script>@example.test',
	'event_plan_id' => '99',
	'party_size' => '2',
	'opt_in' => '1',
);
$invalidEventRender = $captureShellRender(static function (): void {
	bvmgr_pass_claims_render_public_claim('invalid-event-token');
});
$assert($invalidEventRender['content_html'] === $invalidEventExpected, 'Invalid-event Pass Claims submission should preserve exact repopulated form markup and error ordering.');
$assert(strpos($invalidEventRender['content_html'], 'selected="selected"') === false, 'Invalid-event Pass Claims submission should not force a selected event when the submitted event is not eligible.');
$assert(strpos($invalidEventRender['content_html'], 'checked="checked"') !== false, 'Invalid-event Pass Claims submission should preserve the checked opt-in state.');
$assert($GLOBALS['vms_test_nonce_calls'] === 1 && $GLOBALS['vms_test_create_claim_calls'] === 0 && $GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_eligible_calls'] === 1 && $GLOBALS['vms_test_rate_limit_calls'] === 0 && $GLOBALS['vms_test_empty_notice_calls'] === 0, 'Invalid-event Pass Claims submission should preserve the existing validation counts without mutation.');

$partySizePosted = array(
	'first_name' => 'Ada',
	'last_name' => 'Lovelace',
	'phone' => '555-0100',
	'email' => 'ada@example.test',
	'event_plan_id' => 11,
	'party_size' => 9,
	'opt_in' => 0,
);
$partySizeExpected = $expectedFormHtml($batch, $events, $partySizePosted, 'Party size must be between 1 and 3.', $maxPartySize);
$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $batch;
$GLOBALS['vms_test_eligible_return'] = $events;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'vms_pass_claim_submit' => '1',
	'_vms_pass_claim_nonce' => 'ok-nonce',
	'first_name' => 'Ada',
	'last_name' => 'Lovelace',
	'phone' => '555-0100',
	'email' => 'ada@example.test',
	'event_plan_id' => '11',
	'party_size' => '9',
);
$partySizeRender = $captureShellRender(static function (): void {
	bvmgr_pass_claims_render_public_claim('party-size-token');
});
$assert($partySizeRender['content_html'] === $partySizeExpected, 'Party-size validation should preserve the exact form markup, selected event, and clamped quantity output.');
$assert(strpos($partySizeRender['content_html'], 'value="3"') !== false, 'Party-size validation should clamp the rendered party-size value to the existing maximum.');
$assert(strpos($partySizeRender['content_html'], 'selected="selected"') !== false, 'Party-size validation should preserve the selected eligible event.');
$assert($GLOBALS['vms_test_nonce_calls'] === 1 && $GLOBALS['vms_test_create_claim_calls'] === 0 && $GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_eligible_calls'] === 1, 'Party-size validation should not trigger claim mutation.');

$claimFailurePosted = array(
	'first_name' => 'Ada "Quoted"',
	'last_name' => 'Lovelace Team',
	'phone' => '555-0100 "quoted"',
	'email' => 'claimfail@example.test',
	'event_plan_id' => 11,
	'party_size' => 2,
	'opt_in' => 1,
);
$claimFailureExpected = $expectedFormHtml($batch, $events, $claimFailurePosted, '<b>Need first name.</b>', $maxPartySize);
$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $batch;
$GLOBALS['vms_test_eligible_return'] = $events;
$GLOBALS['vms_test_create_claim_return'] = new WP_Error('invalid_claim_input', '<b>Need first name.</b>');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'vms_pass_claim_submit' => '1',
	'_vms_pass_claim_nonce' => 'ok-nonce',
	'first_name' => 'Ada "Quoted"',
	'last_name' => 'Lovelace <em>Team</em>',
	'phone' => '555-0100 "quoted"',
	'email' => 'claim<fail>@example.test',
	'event_plan_id' => '11',
	'party_size' => '2',
	'opt_in' => '1',
);
$claimFailureRender = $captureShellRender(static function (): void {
	bvmgr_pass_claims_render_public_claim('claim-failure-token');
});
$assert($claimFailureRender['content_html'] === $claimFailureExpected, 'Claim-failure Pass Claims submission should preserve the exact repopulated form markup and escaped error message.');
$assert(strpos($claimFailureRender['content_html'], '&lt;b&gt;Need first name.&lt;/b&gt;') !== false, 'Claim-failure Pass Claims submission should keep HTML-like error text inert.');
$assert($GLOBALS['vms_test_create_claim_calls'] === 1 && $GLOBALS['vms_test_nonce_calls'] === 1 && $GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_eligible_calls'] === 1, 'Claim-failure Pass Claims submission should preserve the existing single mutation attempt.');
$assert(count($GLOBALS['vms_test_create_claim_args']) === 1, 'Claim-failure Pass Claims submission should execute claim creation exactly once.');
$claimFailureArgs = $GLOBALS['vms_test_create_claim_args'][0];
$assert($claimFailureArgs[3] === array(
	'first_name' => 'Ada "Quoted"',
	'last_name' => 'Lovelace Team',
	'phone' => '555-0100 "quoted"',
	'email' => 'claimfail@example.test',
	'event_plan_id' => 11,
	'party_size' => 2,
	'opt_in' => 1,
), 'Claim-failure Pass Claims submission should preserve the sanitized and normalized input passed into claim creation.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = null;
$invalidTokenRender = $captureShellRender(static function (): void {
	$GLOBALS['vms_test_query_vars']['vms_pass_claim_token'] = 'invalid-token';
	bvmgr_pass_claims_template_router();
});
$assert($invalidTokenRender['headline'] === 'Claim Pass', 'Invalid-token Pass Claims route should remain outside the interactive form family.');
$assert(strpos($invalidTokenRender['content_html'], '<form method="post">') === false, 'Invalid-token Pass Claims route should not fall through into the form family.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken(array(
	'status' => 'claimed',
	'reservation_entry_id' => 17,
));
$GLOBALS['vms_test_get_batch_return'] = $batch;
$alreadyClaimedRender = $captureShellRender(static function (): void {
	bvmgr_pass_claims_render_public_claim('claimed-token');
});
$assert($alreadyClaimedRender['headline'] === 'Already Claimed', 'Already-claimed Pass Claims route should remain outside the interactive form family.');
$assert(strpos($alreadyClaimedRender['content_html'], '<form method="post">') === false, 'Already-claimed Pass Claims route should not fall through into the form family.');

$resetRuntime();
$GLOBALS['vms_test_find_token_return'] = $baseToken();
$GLOBALS['vms_test_get_batch_return'] = $batch;
$GLOBALS['vms_test_eligible_return'] = $events;
$GLOBALS['vms_test_create_claim_return'] = array(
	'event_title' => 'Success Event',
	'event_date' => '',
	'venue_name' => '',
	'reference' => 'GL-501',
	'scan_url' => '',
	'admission_token' => '',
	'admission_tokens' => array(),
	'party_size' => 1,
	'email_sent' => false,
	'email_result' => array(),
);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'vms_pass_claim_submit' => '1',
	'_vms_pass_claim_nonce' => 'ok-nonce',
	'first_name' => 'Ada',
	'last_name' => 'Lovelace',
	'phone' => '555-0100',
	'email' => '',
	'event_plan_id' => '11',
	'party_size' => '1',
);
$successOutsideFormRender = $captureShellRender(static function (): void {
	bvmgr_pass_claims_render_public_claim('success-token');
});
$assert($successOutsideFormRender['headline'] === 'Pass Claimed', 'Successful Pass Claims submission should remain outside the interactive form family.');
$assert(strpos($successOutsideFormRender['content_html'], '<form method="post">') === false && strpos($successOutsideFormRender['content_html'], 'You Are Confirmed') !== false, 'Successful Pass Claims submission should hand off to the accepted success family rather than falling through into the form.');
$assert($GLOBALS['vms_test_create_claim_calls'] === 1 && $GLOBALS['vms_test_nonce_calls'] === 1 && $GLOBALS['vms_test_find_token_calls'] === 1 && $GLOBALS['vms_test_get_batch_calls'] === 1 && $GLOBALS['vms_test_eligible_calls'] === 1, 'Successful Pass Claims submission should preserve the existing success-path counts while staying outside the form family.');

fwrite(STDOUT, "Pass Claims public form output remediation OK.\n");
