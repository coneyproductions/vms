<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BVMGR_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('BVMGR_VERSION', 'test-version');

$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_scripts'] = array();
$GLOBALS['vms_test_script_calls'] = array();
$GLOBALS['vms_test_filters'] = array();
$GLOBALS['vms_test_manage_options'] = false;
$GLOBALS['vms_test_logged_in'] = false;
$GLOBALS['vms_test_user_email'] = 'member@example.test';

if (!class_exists('WP_User')) {
	class WP_User
	{
		public string $user_email = '';
	}
}

if (!class_exists('WP_Post')) {
	class WP_Post {}
}

function __(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_html__(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_attr__(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_url(string $url): string { return $url; }
function wp_unslash($value) { return $value; }
function sanitize_email(string $email): string { return trim($email); }
function sanitize_key(string $value): string { return strtolower(preg_replace('/[^a-z0-9_-]/', '', $value) ?? ''); }
function sanitize_title(string $value): string { return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? ''), '-'); }
function sanitize_text_field(string $value): string { return trim($value); }
function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function add_shortcode(string $tag, $callback): void { unset($tag, $callback); }
function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
	unset($priority, $accepted_args);
	if (!isset($GLOBALS['vms_test_filters'][$hook])) {
		$GLOBALS['vms_test_filters'][$hook] = array();
	}
	$GLOBALS['vms_test_filters'][$hook][] = $callback;
}
function apply_filters(string $hook, $value)
{
	$args = func_get_args();
	array_shift($args);

	if (empty($GLOBALS['vms_test_filters'][$hook]) || !is_array($GLOBALS['vms_test_filters'][$hook])) {
		return $value;
	}

	$filtered = $value;
	foreach ($GLOBALS['vms_test_filters'][$hook] as $callback) {
		$filtered = $callback(...array_merge(array($filtered), array_slice($args, 1)));
	}

	return $filtered;
}
function get_option(string $option, $default = false)
{
	return array_key_exists($option, $GLOBALS['vms_test_options']) ? $GLOBALS['vms_test_options'][$option] : $default;
}
function is_wp_error($thing): bool { return false; }
function taxonomy_exists(string $taxonomy): bool { unset($taxonomy); return false; }
function wp_json_encode($value): string { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null'; }
function is_user_logged_in(): bool { return !empty($GLOBALS['vms_test_logged_in']); }
function current_user_can(string $capability): bool
{
	return $capability === 'manage_options' ? !empty($GLOBALS['vms_test_manage_options']) : false;
}
function wp_get_current_user(): WP_User
{
	$user = new WP_User();
	$user->user_email = (string) $GLOBALS['vms_test_user_email'];
	return $user;
}
function bvmgr_request_method(): string { return 'get'; }
function bvmgr_request_read_scalar(array $source, string $key): string { return isset($source[$key]) && !is_array($source[$key]) ? trim((string) $source[$key]) : ''; }
function bvmgr_request_read_text_field(array $source, string $key): string { return sanitize_text_field(bvmgr_request_read_scalar($source, $key)); }
function bvmgr_request_read_key(array $source, string $key): string { return isset($source[$key]) && !is_array($source[$key]) ? (string) $source[$key] : ''; }
function bvmgr_request_read_bool_flag(array $source, string $key): bool { return !empty($source[$key]); }
function bvmgr_asset_url(string $asset_rel): string { return BVMGR_PLUGIN_URL . ltrim($asset_rel, '/'); }
function bvmgr_asset_version_for(string $asset_rel): string { unset($asset_rel); return BVMGR_VERSION; }
function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void
{
	$record = compact('handle', 'src', 'deps', 'ver', 'in_footer');
	$GLOBALS['vms_test_scripts'][$handle] = $record;
	$GLOBALS['vms_test_script_calls'][] = $record;
}
function wp_nonce_field(string $action, string $name): void { echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($action) . '" />'; }
function add_query_arg(array $args, string $url): string { return $url . '?' . http_build_query($args); }
function home_url(string $path = '/'): string { return 'https://example.test' . $path; }
function get_page_by_path(string $path) { unset($path); return null; }
function get_permalink($post): string { unset($post); return 'https://example.test/permalink/'; }
function bvmgr_vendor_apply_render_success_screen(bool $is_logged_in_submitter, bool $is_portal_add_flow): string
{
	unset($is_logged_in_submitter, $is_portal_add_flow);
	return 'SCREEN_SUCCESS';
}
function bvmgr_vendor_apply_render_confirmation_pending_screen(int $app_id, array $args = array()): string
{
	return 'SCREEN_PENDING:' . $app_id . ':' . (string) ($args['notice'] ?? '');
}
function bvmgr_vendor_apply_render_existing_status_screen(int $app_id, string $kind): string
{
	return 'SCREEN_EXISTING:' . $app_id . ':' . $kind;
}
function bvmgr_vendor_app_find_application_by_public_lookup_key(string $ref): int
{
	return $ref !== '' ? 321 : 0;
}

require_once dirname(__DIR__) . '/includes/vendor-applications.php';

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$resetEnvironment = static function (): void {
	$GLOBALS['vms_test_options'] = array();
	$GLOBALS['vms_test_scripts'] = array();
	$GLOBALS['vms_test_script_calls'] = array();
	$GLOBALS['vms_test_filters'] = array();
	$GLOBALS['vms_test_manage_options'] = false;
	$GLOBALS['vms_test_logged_in'] = false;
	$GLOBALS['vms_test_user_email'] = 'member@example.test';
	$_GET = array();
	$_POST = array();
};

$readFile = static function (string $path) use ($assert): string {
	$contents = @file_get_contents($path);
	$assert(is_string($contents) && $contents !== '', 'Expected readable source file: ' . $path);
	return $contents;
};

$extractFunctionSource = static function (string $path, string $functionName) use ($assert, $readFile): string {
	$source = $readFile($path);
	$tokens = token_get_all($source);
	$lines = file($path);
	$assert(is_array($lines), 'Expected line-oriented source for ' . $path);
	$count = count($tokens);

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];
		if (!is_array($token) || $token[0] !== T_FUNCTION) {
			continue;
		}

		$j = $i + 1;
		while ($j < $count) {
			$next = $tokens[$j];
			if (is_array($next) && $next[0] === T_WHITESPACE) {
				$j++;
				continue;
			}
			if ($next === '&') {
				$j++;
				continue;
			}
			break;
		}

		if ($j >= $count || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $functionName) {
			continue;
		}

		$startLine = $token[2];
		$endLine = $startLine;
		$braceDepth = 0;
		$started = false;

		for ($k = $j; $k < $count; $k++) {
			$current = $tokens[$k];
			$text = is_array($current) ? $current[1] : $current;
			if ($text === '{') {
				$braceDepth++;
				$started = true;
			} elseif ($text === '}') {
				$braceDepth--;
				if ($started && $braceDepth === 0) {
					break;
				}
			}

			if (is_array($current)) {
				$lineCount = substr_count($current[1], "\n");
				if ($lineCount > 0) {
					$endLine = $current[2] + $lineCount;
				}
			}
		}

		$functionSource = '';
		for ($line = $startLine; $line <= $endLine; $line++) {
			$functionSource .= $lines[$line - 1];
		}

		return $functionSource;
	}

	throw new RuntimeException('Unable to isolate function source for ' . $functionName . '.');
};

$restoreTurnstileLoggingBaseline = static function (string $source) use ($assert): string {
	$historical = array(
		'vendor_apply_turnstile_config_missing' => "error_log('[VMS] vendor-apply: Turnstile keys missing; blocking submission.');",
		'vendor_apply_turnstile_request_failed' => "error_log('[VMS] vendor-apply: Turnstile siteverify request failed: ' . \$resp->get_error_message());",
		'vendor_apply_turnstile_response_failed' => "error_log('[VMS] vendor-apply: Turnstile siteverify non-2xx or empty body. HTTP ' . \$code);",
		'vendor_apply_turnstile_payload_invalid' => "error_log('[VMS] vendor-apply: Turnstile siteverify returned an invalid JSON payload.');",
	);
	foreach ($historical as $eventCode => $statement) {
		$needle = "bvmgr_record_operational_issue('" . $eventCode . "'";
		$assert(substr_count($source, $needle) === 1, 'Known G16 Turnstile call count changed: ' . $eventCode);
		$call = strpos($source, $needle);
		$lineStart = strrpos(substr($source, 0, (int) $call), "\n");
		$lineStart = $lineStart === false ? 0 : $lineStart + 1;
		$callEnd = strpos($source, ");\n", (int) $call);
		$assert($callEnd !== false, 'Known G16 Turnstile call must end on its own line: ' . $eventCode);
		$callEnd += 3;
		$indent = substr($source, $lineStart, (int) $call - $lineStart);
		$source = substr($source, 0, $lineStart) . $indent . $statement . "\n" . substr($source, $callEnd);
	}
	return $source;
};

$assertApplicationJsonOnly = static function (string $html, string $context) use ($assert): void {
	preg_match_all('~<script\b([^>]*)>(.*?)</script>~is', $html, $matches, PREG_SET_ORDER);
	$assert(count($matches) === 1, $context . ' should only render the JSON configuration payload script.');
	$type = '';
	if (preg_match('~\btype\s*=\s*(["\'])([^"\']+)\1~i', $matches[0][1], $typeMatch)) {
		$type = strtolower(trim((string) $typeMatch[2]));
	}
	$assert($type === 'application/json', $context . ' should only render an application/json script payload.');
};

$scriptCallsForHandle = static function (string $handle): array {
	return array_values(array_filter(
		$GLOBALS['vms_test_script_calls'],
		static function (array $call) use ($handle): bool {
			return ($call['handle'] ?? '') === $handle;
		}
	));
};

$renderShortcode = static function (array $options, bool $manageOptions = false, array $get = array(), array $filters = array(), bool $loggedIn = false) use ($resetEnvironment): array {
	$resetEnvironment();
	$GLOBALS['vms_test_options'] = $options;
	$GLOBALS['vms_test_manage_options'] = $manageOptions;
	$GLOBALS['vms_test_logged_in'] = $loggedIn;
	$_GET = $get;

	foreach ($filters as $hook => $callbacks) {
		foreach ($callbacks as $callback) {
			add_filter($hook, $callback);
		}
	}

	$html = bvmgr_vendor_apply_shortcode();

	return array(
		'html' => $html,
		'scripts' => $GLOBALS['vms_test_scripts'],
		'script_calls' => $GLOBALS['vms_test_script_calls'],
	);
};

$vendorApplicationsPath = dirname(__DIR__) . '/includes/vendor-applications.php';
$readmePath = dirname(__DIR__) . '/readme.txt';
$assetPath = dirname(__DIR__) . '/assets/js/vms-vendor-apply.js';
$vendorApplicationsSource = $readFile($vendorApplicationsPath);
$readmeSource = $readFile($readmePath);
$assetSource = $readFile($assetPath);

$assert(function_exists('bvmgr_vendor_apply_turnstile_is_configured'), 'Expected Turnstile complete-configuration helper to exist.');

$helperSource = $extractFunctionSource($vendorApplicationsPath, 'bvmgr_vendor_apply_turnstile_is_configured');
$assert(strpos($helperSource, 'bvmgr_vendor_apply_turnstile_site_key()') !== false, 'Complete-configuration helper should derive the site key through the existing helper.');
$assert(strpos($helperSource, 'bvmgr_vendor_apply_turnstile_secret_key()') !== false, 'Complete-configuration helper should derive the secret key through the existing helper.');
$assert(strpos($helperSource, 'get_option(') === false, 'Complete-configuration helper should not duplicate raw option reads.');

$assert(hash('sha256', $extractFunctionSource($vendorApplicationsPath, 'bvmgr_vendor_apply_is_rate_limited')) === '082c25ed3a27c59ae785ccafd9e57b2a1b40a760fa6ee76aa5ed2131fca982c1', 'Rate limiting function should remain unchanged.');
$assert(hash('sha256', $extractFunctionSource($vendorApplicationsPath, 'bvmgr_vendor_apply_parse_turnstile_siteverify_body')) === 'b16d36431e7d78f48cd52a69548498425a4671e5c54eb6ec1b97586855b42c7d', 'Turnstile siteverify parser should remain unchanged.');
$turnstileVerifySource = $extractFunctionSource($vendorApplicationsPath, 'bvmgr_vendor_apply_verify_turnstile');
$turnstileHistoricalProjection = $restoreTurnstileLoggingBaseline($turnstileVerifySource);
$assert(hash('sha256', $turnstileHistoricalProjection) === '5802d9b120a3434857a72ce4160b54aefc46ea31e1e816b7d292c05d3e57b8af', 'Turnstile verification should differ from its immutable baseline only by the known G16 logging migration.');
$turnstileMutation = str_replace("'timeout' => 8", "'timeout' => 9", $turnstileHistoricalProjection, $turnstileMutationCount);
$assert($turnstileMutationCount === 1 && hash('sha256', $turnstileMutation) !== '5802d9b120a3434857a72ce4160b54aefc46ea31e1e816b7d292c05d3e57b8af', 'Turnstile immutable projection must reject a non-logging runtime mutation.');
$assert(substr_count($turnstileVerifySource, 'bvmgr_record_operational_issue(') === 4, 'Mirror Turnstile verification should contain exactly four structured operational branches.');
$assert(hash('sha256', $extractFunctionSource($vendorApplicationsPath, 'bvmgr_vendor_apply_request_fingerprint')) === 'e6700ad7ba8ff855318160c10640fb4443f1f832384276cd5b49cbc160d06827', 'Request fingerprinting should remain unchanged.');
$assert(hash('sha256', $extractFunctionSource($vendorApplicationsPath, 'bvmgr_vendor_apply_handle_frontend_post')) === '375e812b9016e3200c4e2dc8c14f69a8ae6f8f7b72f068949d2e5508543530d4', 'Frontend POST handler should remain unchanged.');
$assert(hash('sha256', $assetSource) === '1856166b2a3785803148bc9019867e7b9e387e6b953f2099959ebb2af99e685c', 'Unrelated Vendor Application asset should remain unchanged.');

$shortcodeSource = $extractFunctionSource($vendorApplicationsPath, 'bvmgr_vendor_apply_shortcode');
$assert(strpos($shortcodeSource, "'https://challenges.cloudflare.com/turnstile/v0/api.js'") !== false, 'Shortcode should keep the explicit Cloudflare Turnstile client URL.');
$assert(strpos($shortcodeSource, 'BVMGR_VERSION') !== false, 'Shortcode should enqueue Turnstile with BVMGR_VERSION.');
$assert(strpos($shortcodeSource, __('Vendor applications are temporarily unavailable.', 'backstage-venue-manager')) !== false, 'Shortcode should contain the public unavailable notice.');
$assert(strpos($shortcodeSource, 'Turnstile requires both a site key and a secret key before the Vendor Application form can be used.') !== false, 'Shortcode should contain the bounded administrator diagnostic.');

$assert(strpos($vendorApplicationsSource, 'https://challenges.cloudflare.com/turnstile/v0/siteverify') !== false, 'Verification source should retain the explicit siteverify endpoint.');
$assert(strpos($vendorApplicationsSource, "'timeout' => 8") !== false, 'Verification source should retain the existing timeout.');
$assert(strpos($vendorApplicationsSource, "'secret'   => \$secret") !== false, 'Verification source should retain the secret field.');
$assert(strpos($vendorApplicationsSource, "'response' => \$token") !== false, 'Verification source should retain the response field.');
$assert(strpos($vendorApplicationsSource, "'remoteip' => \$ip") !== false, 'Verification source should retain the remoteip field.');
$assert(strpos($vendorApplicationsSource, "if (!bvmgr_vendor_apply_verify_turnstile()) {") !== false, 'Frontend POST path should retain the fail-closed Turnstile gate.');

$assert(strpos($readmeSource, 'optional protection for the public Vendor Application form against automated abuse') !== false, 'Readme should describe Cloudflare Turnstile as an optional external service.');
$assert(strpos($readmeSource, 'both a site key and a secret key') !== false, 'Readme should document the two-key enablement requirement.');
$assert(strpos($readmeSource, "the visitor's browser loads Cloudflare's Turnstile client from challenges.cloudflare.com before the application is submitted") !== false, 'Readme should document render-time browser contact.');
$assert(strpos($readmeSource, 'Turnstile response token') !== false, 'Readme should document the Turnstile response token.');
$assert(strpos($readmeSource, 'visitor IP address') !== false, 'Readme should document the visitor IP address.');
$assert(strpos($readmeSource, 'configured secret key') !== false, 'Readme should document secret-key authentication.');
$assert(strpos($readmeSource, 'Vendor Application form contents are not sent to Cloudflare through this integration.') !== false, 'Readme should document that Vendor Application contents are not sent to Cloudflare.');
$assert(strpos($readmeSource, 'If either key is missing, the active Vendor Application form is unavailable and the Cloudflare client is not loaded.') !== false, 'Readme should document incomplete-configuration behavior.');
$assert(strpos($readmeSource, 'https://developers.cloudflare.com/turnstile/get-started/server-side-validation/') !== false, 'Readme should retain the Cloudflare service documentation URL.');
$assert(strpos($readmeSource, 'https://www.cloudflare.com/turnstile-privacy-policy/') !== false, 'Readme should retain the Cloudflare privacy URL.');

$assert(hash('sha256', $assetSource) === '1856166b2a3785803148bc9019867e7b9e387e6b953f2099959ebb2af99e685c', 'Unrelated Vendor Application assets should remain unchanged.');

$assert(!bvmgr_vendor_apply_turnstile_is_configured(), 'Turnstile should be incomplete with no configured keys.');

$incompleteCases = array(
	'none' => array(),
	'site_only' => array('vms_turnstile_site_key' => 'site-only-key'),
	'secret_only' => array('vms_turnstile_secret_key' => 'secret-only-key'),
);

foreach ($incompleteCases as $label => $options) {
	$public = $renderShortcode($options);
	$assert(!bvmgr_vendor_apply_turnstile_is_configured(), $label . ' should not count as a complete Turnstile configuration.');
	$assert(!isset($public['scripts']['cf-turnstile']), $label . ' should not enqueue the Cloudflare Turnstile client.');
	$assert(!isset($public['scripts']['bvmgr-vendor-apply']), $label . ' should not enqueue the active form asset when the form is unavailable.');
	$assert(strpos($public['html'], 'https://challenges.cloudflare.com/turnstile/v0/api.js') === false, $label . ' should not expose the Cloudflare client URL in rendered output.');
	$assert(strpos($public['html'], 'class="cf-turnstile"') === false, $label . ' should not render the Turnstile widget markup.');
	$assert(strpos($public['html'], 'class="vms-vendor-apply-form"') === false, $label . ' should not render the active Vendor Application form.');
	$assert(strpos($public['html'], 'Vendor applications are temporarily unavailable.') !== false, $label . ' should render the public unavailable notice.');
	$assert(strpos($public['html'], 'Please try again later.') !== false, $label . ' should render the public unavailable follow-up.');
	$assert(strpos($public['html'], 'Turnstile requires both a site key and a secret key before the Vendor Application form can be used.') === false, $label . ' should not show the administrator diagnostic to ordinary visitors.');
	foreach ($options as $value) {
		$assert(strpos($public['html'], (string) $value) === false, $label . ' should not reveal configured key material in output.');
	}

	$admin = $renderShortcode($options, true);
	$assert(!isset($admin['scripts']['cf-turnstile']), $label . ' admin view should not enqueue the Cloudflare Turnstile client.');
	$assert(!isset($admin['scripts']['bvmgr-vendor-apply']), $label . ' admin view should not enqueue the active form asset when the form is unavailable.');
	$assert(strpos($admin['html'], 'Vendor applications are temporarily unavailable.') !== false, $label . ' admin view should still render the public unavailable notice.');
	$assert(strpos($admin['html'], 'Turnstile requires both a site key and a secret key before the Vendor Application form can be used.') !== false, $label . ' admin view should render the bounded administrator diagnostic.');
	foreach ($options as $value) {
		$assert(strpos($admin['html'], (string) $value) === false, $label . ' admin view should not reveal configured key material in output.');
	}
}

$publicSiteKey = 'public<site>&"';
$secretKey = 'super-secret-key';
$configured = $renderShortcode(
	array(
		'vms_turnstile_site_key' => $publicSiteKey,
		'vms_turnstile_secret_key' => $secretKey,
	)
);

$assert(bvmgr_vendor_apply_turnstile_is_configured(), 'Both keys should count as a complete Turnstile configuration.');
$assert(isset($configured['scripts']['bvmgr-vendor-apply']), 'Configured form should enqueue the canonical Vendor Applications asset.');
$assert(isset($configured['scripts']['cf-turnstile']), 'Configured form should enqueue the Cloudflare Turnstile client.');
$assert(count($scriptCallsForHandle('cf-turnstile')) === 1, 'Configured form should enqueue the Cloudflare Turnstile client exactly once.');
$assert(($configured['scripts']['cf-turnstile']['src'] ?? '') === 'https://challenges.cloudflare.com/turnstile/v0/api.js', 'Configured form should keep the explicit Cloudflare Turnstile client URL.');
$assert(($configured['scripts']['cf-turnstile']['deps'] ?? null) === array(), 'Configured form should keep an empty dependency list for the Cloudflare Turnstile client.');
$assert(($configured['scripts']['cf-turnstile']['ver'] ?? null) === BVMGR_VERSION, 'Configured form should use BVMGR_VERSION for the Cloudflare Turnstile client.');
$assert(($configured['scripts']['cf-turnstile']['in_footer'] ?? null) === true, 'Configured form should keep the Cloudflare Turnstile client in the footer.');
$assert(strpos($configured['html'], 'class="vms-vendor-apply-form"') !== false, 'Configured form should render the active Vendor Application form.');
$assert(strpos($configured['html'], 'name="vms_vendor_apply_nonce"') !== false, 'Configured form should preserve the form nonce field.');
$assert(strpos($configured['html'], 'class="cf-turnstile" data-sitekey="' . htmlspecialchars($publicSiteKey, ENT_QUOTES, 'UTF-8') . '"') !== false, 'Configured form should render the widget markup with the escaped public site key.');
$assert(strpos($configured['html'], $secretKey) === false, 'Configured form should never render the secret key.');
$assert(strpos($configured['html'], 'Vendor applications are temporarily unavailable.') === false, 'Configured form should not show the unavailable notice.');
$assertApplicationJsonOnly($configured['html'], 'Configured form');

$filterConfigured = $renderShortcode(
	array(),
	false,
	array(),
	array(
		'bvmgr_vendor_apply_turnstile_site_key' => array(
			static function (string $value): string {
				unset($value);
				return 'filtered-site-key';
			},
		),
		'bvmgr_vendor_apply_turnstile_secret_key' => array(
			static function (string $value): string {
				unset($value);
				return 'filtered-secret-key';
			},
		),
	)
);

$assert(bvmgr_vendor_apply_turnstile_is_configured(), 'Supported filters should be able to provide a complete Turnstile configuration.');
$assert(isset($filterConfigured['scripts']['cf-turnstile']), 'Filter-provided keys should still enqueue the Cloudflare Turnstile client.');
$assert(strpos($filterConfigured['html'], 'data-sitekey="filtered-site-key"') !== false, 'Filter-provided public site key should render in the widget markup.');
$assert(strpos($filterConfigured['html'], 'filtered-secret-key') === false, 'Filter-provided secret key should never render.');

$successState = $renderShortcode(array(), false, array('vms_app' => 'success'));
$assert($successState['html'] === 'SCREEN_SUCCESS', 'Success state should remain ahead of the Turnstile configuration gate.');
$assert(strpos($successState['html'], 'Vendor applications are temporarily unavailable.') === false, 'Success state should not be replaced by the unavailable notice.');

$pendingState = $renderShortcode(array(), false, array(
	'vms_app' => 'confirm_pending',
	'vms_app_ref' => 'abc123',
	'vms_app_notice' => 'resent',
));
$assert($pendingState['html'] === 'SCREEN_PENDING:321:resent', 'Pending confirmation state should remain ahead of the Turnstile configuration gate.');

$existingPendingState = $renderShortcode(array(), false, array(
	'vms_app' => 'already_pending',
	'vms_app_ref' => 'abc123',
));
$assert($existingPendingState['html'] === 'SCREEN_EXISTING:321:pending', 'Existing pending-application state should remain ahead of the Turnstile configuration gate.');

$existingApprovedState = $renderShortcode(array(), false, array(
	'vms_app' => 'already_approved',
	'vms_app_ref' => 'abc123',
));
$assert($existingApprovedState['html'] === 'SCREEN_EXISTING:321:approved', 'Other established early-return states should remain ahead of the Turnstile configuration gate.');

fwrite(STDOUT, "vendor apply Turnstile contract remediation: PASS\n");
