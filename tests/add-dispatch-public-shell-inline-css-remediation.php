<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('VMS_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('VMS_VERSION', 'test-version');

$GLOBALS['vms_test_asset_version'] = 'test-asset-version';
$GLOBALS['vms_test_actions'] = array();

function __(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_html__(string $text, string $domain = ''): string { unset($domain); return $text; }
function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
	$GLOBALS['vms_test_actions'][$hook][$priority][] = array(
		'callback' => $callback,
		'accepted_args' => $accepted_args,
	);
}
function has_action(string $hook, $callback = false)
{
	if (!isset($GLOBALS['vms_test_actions'][$hook])) {
		return false;
	}

	foreach ($GLOBALS['vms_test_actions'][$hook] as $priority => $callbacks) {
		foreach ($callbacks as $entry) {
			if ($callback === false || $entry['callback'] === $callback) {
				return (int) $priority;
			}
		}
	}

	return false;
}
function add_rewrite_tag(string $tag, string $regex): void { unset($tag, $regex); }
function add_rewrite_rule(string $regex, string $query, string $position = 'bottom'): void { unset($regex, $query, $position); }
function get_option(string $option, $default = false) { unset($option); return $default; }
function flush_rewrite_rules(bool $hard = true): void { unset($hard); }
function update_option(string $option, $value, $autoload = null): void { unset($option, $value, $autoload); }
function esc_html($text): string { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_url($url): string { return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
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
function add_query_arg($key, $value = '', $url = '')
{
	if (is_array($key)) {
		$args = $key;
		$url = (string) $value;
	} else {
		$args = array((string) $key => (string) $value);
	}

	$parts = parse_url((string) $url);
	$base = '';
	if (isset($parts['scheme'])) {
		$base .= $parts['scheme'] . '://';
	}
	if (isset($parts['host'])) {
		$base .= $parts['host'];
	}
	if (isset($parts['port'])) {
		$base .= ':' . $parts['port'];
	}
	$base .= $parts['path'] ?? '';

	$query = array();
	if (!empty($parts['query'])) {
		parse_str((string) $parts['query'], $query);
	}
	foreach ($args as $arg_key => $arg_value) {
		$query[(string) $arg_key] = (string) $arg_value;
	}

	$rebuilt = $base;
	if ($query !== array()) {
		$rebuilt .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}
	if (!empty($parts['fragment'])) {
		$rebuilt .= '#' . $parts['fragment'];
	}

	return $rebuilt;
}
function vms_asset_version(): string { return (string) $GLOBALS['vms_test_asset_version']; }

require_once dirname(__DIR__) . '/includes/modules/availability-date-dispatch/public.php';

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';

$publicPath = $pluginRoot . '/includes/modules/availability-date-dispatch/public.php';
$assetPath = $pluginRoot . '/assets/css/vms-add-dispatch-public-shell.css';
$outputTestPath = $pluginRoot . '/tests/add-dispatch-public-shell-output-remediation.php';
$ledgerPath = $pluginRoot . '/docs/wporg-remediation-ledger.md';
$prereviewPath = $pluginRoot . '/docs/WPORG_PREREVIEW_REMEDIATION.md';
$adminUiPath = $pluginRoot . '/includes/modules/availability-date-dispatch/admin-ui.php';
$helpersPath = $pluginRoot . '/includes/modules/availability-date-dispatch/helpers.php';
$openNeedsTestPath = $pluginRoot . '/tests/add-dispatch-open-vendor-needs.php';
$livePublicPath = $livePluginRoot . '/includes/modules/availability-date-dispatch/public.php';
$liveAssetPath = $livePluginRoot . '/assets/css/vms-add-dispatch-public-shell.css';

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$readFile = static function (string $path) use ($assert): string {
	$contents = @file_get_contents($path);
	$assert(is_string($contents) && $contents !== '', 'Expected readable source file: ' . $path);
	return $contents;
};

$extractFunctionSource = static function (string $source, string $functionName) use ($assert): string {
	$tokens = token_get_all($source);
	$capturing = false;
	$seenName = false;
	$braceDepth = 0;
	$buffer = '';

	foreach ($tokens as $token) {
		$text = is_array($token) ? $token[1] : $token;

		if (!$capturing) {
			if (is_array($token) && $token[0] === T_FUNCTION) {
				$capturing = true;
				$seenName = false;
				$braceDepth = 0;
				$buffer = $text;
			}
			continue;
		}

		$buffer .= $text;
		if (!$seenName) {
			if ($text === '(') {
				$capturing = false;
				$buffer = '';
				continue;
			}
			if (is_array($token) && $token[0] === T_STRING) {
				if ($token[1] !== $functionName) {
					$capturing = false;
					$buffer = '';
					continue;
				}
				$seenName = true;
			}
			continue;
		}

		if ($text === '{') {
			$braceDepth++;
			continue;
		}

		if ($text === '}') {
			$braceDepth--;
			if ($braceDepth === 0) {
				return $buffer;
			}
		}
	}

	$assert(false, 'Unable to extract function source for ' . $functionName . '.');
	return '';
};

try {
	$publicSource = $readFile($publicPath);
	$assetSource = $readFile($assetPath);
	$outputTestSource = $readFile($outputTestPath);
	$ledgerSource = $readFile($ledgerPath);
	$prereviewSource = $readFile($prereviewPath);
	$adminUiSource = $readFile($adminUiPath);
	$helpersSource = $readFile($helpersPath);
	$openNeedsTestSource = $readFile($openNeedsTestPath);
	$livePublicSource = $readFile($livePublicPath);
	$liveAssetSource = $readFile($liveAssetPath);

	$shellSource = $extractFunctionSource($publicSource, 'vms_add_dispatch_render_public_shell');
	$stylesheetUrlSource = $extractFunctionSource($publicSource, 'vms_add_dispatch_public_shell_stylesheet_url');
	$routerSource = $extractFunctionSource($publicSource, 'vms_add_dispatch_template_router');
	$renderResponseSource = $extractFunctionSource($publicSource, 'vms_add_dispatch_render_public_response');
	$rewriteSource = $extractFunctionSource($publicSource, 'vms_add_dispatch_register_rewrite');
	$flushSource = $extractFunctionSource($publicSource, 'vms_add_dispatch_maybe_flush_rewrites');

	$assert(has_action('init', 'vms_add_dispatch_register_rewrite') === 30, 'ADD public response rewrite registration should remain on init priority 30.');
	$assert(has_action('admin_init', 'vms_add_dispatch_maybe_flush_rewrites') === 30, 'ADD public response flush guard should remain on admin_init priority 30.');
	$assert(has_action('template_redirect', 'vms_add_dispatch_template_router') === 0, 'ADD public response router should remain on template_redirect priority 0.');

	$assert(strpos($rewriteSource, "add_rewrite_tag('%vms_add_dispatch_token%', '([^&]+)');") !== false, 'ADD public response rewrite tag should remain unchanged.');
	$assert(strpos($rewriteSource, "add_rewrite_rule('^availability-dispatch/respond/([^/]+)/?$', 'index.php?vms_add_dispatch_token=\$matches[1]', 'top');") !== false, 'ADD public response rewrite rule should remain unchanged.');
	$assert(strpos($flushSource, "\$key = 'vms_rewrite_flushed_add_dispatch_v1';") !== false, 'ADD public response flush key should remain unchanged.');
	$assert(strpos($flushSource, 'flush_rewrite_rules(false);') !== false, 'ADD public response flush behavior should remain unchanged.');
	$assert(strpos($flushSource, "update_option(\$key, '1', false);") !== false, 'ADD public response flush persistence should remain unchanged.');

	$assert(strpos($shellSource, '<style>') === false, 'ADD public shell should no longer emit a targeted inline <style> block.');
	$assert(strpos($publicSource, 'wp_add_inline_style(') === false, 'ADD public shell should not move styles into wp_add_inline_style().');
	$assert(strpos($shellSource, '$stylesheet_url = vms_add_dispatch_public_shell_stylesheet_url();') !== false, 'ADD public shell should resolve the standalone stylesheet through a dedicated helper.');
	$assert(
		preg_match(
			'~echo \'<title>\' \. esc_html\(\$headline\) \. \'</title>\';\s*if \(\$stylesheet_url !== \'\'\) \{\s*echo \'<link rel="stylesheet" href="\' \. esc_url\(\$stylesheet_url\) \. \'">\';\s*\}\s*echo \'</head><body><div class="vms-add-public"><div class="vms-add-card">\';~s',
			$shellSource
		) === 1,
		'ADD public shell should replace the inline CSS block with one escaped stylesheet link in the existing head wrapper.'
	);
	$assert(strpos($shellSource, 'status_header(200);') !== false, 'ADD public shell should preserve the 200 status header.');
	$assert(strpos($shellSource, 'nocache_headers();') !== false, 'ADD public shell should preserve nocache headers.');
	$assert(strpos($shellSource, "echo '<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">';") !== false, 'ADD public shell should preserve the standalone document opener.');
	$assert(strpos($shellSource, "echo '</div></div></body></html>';") !== false, 'ADD public shell should preserve the standalone document closer.');
	$assert(strpos($shellSource, 'wp_kses($content_html, vms_add_dispatch_public_response_allowed_html())') !== false, 'ADD public shell should preserve the final response-fragment allowlist sink.');
	$assert(strpos($shellSource, 'wp_head(') === false, 'ADD public shell should remain a standalone document without wp_head().');
	$assert(strpos($shellSource, 'wp_enqueue_style(') === false, 'ADD public shell should not globally enqueue the standalone stylesheet.');
	$assert(strpos($shellSource, 'exit;') !== false, 'ADD public shell should preserve explicit response termination.');

	$assert(strpos($stylesheetUrlSource, "VMS_PLUGIN_URL . 'assets/css/vms-add-dispatch-public-shell.css'") !== false, 'ADD public shell stylesheet helper should point to assets/css/vms-add-dispatch-public-shell.css.');
	$assert(strpos($stylesheetUrlSource, "function_exists('vms_asset_version') ? trim((string) vms_asset_version()) : ''") !== false, 'ADD public shell stylesheet helper should prefer vms_asset_version().');
	$assert(strpos($stylesheetUrlSource, "if (\$version === '' && defined('VMS_VERSION')) {") !== false, 'ADD public shell stylesheet helper should fall back to VMS_VERSION when the helper is unavailable or empty.');
	$assert(strpos($stylesheetUrlSource, "\$stylesheet_url = add_query_arg('ver', \$version, \$stylesheet_url);") !== false, 'ADD public shell stylesheet helper should add the version query through add_query_arg().');

	$GLOBALS['vms_test_asset_version'] = 'test-asset-version';
	$assert(
		vms_add_dispatch_public_shell_stylesheet_url() === 'https://example.test/wp-content/plugins/backstage-venue-manager/assets/css/vms-add-dispatch-public-shell.css?ver=test-asset-version',
		'ADD public shell stylesheet helper should prefer the asset-version helper result.'
	);
	$GLOBALS['vms_test_asset_version'] = '';
	$assert(
		vms_add_dispatch_public_shell_stylesheet_url() === 'https://example.test/wp-content/plugins/backstage-venue-manager/assets/css/vms-add-dispatch-public-shell.css?ver=test-version',
		'ADD public shell stylesheet helper should fall back to VMS_VERSION when the helper result is empty.'
	);

	$expectedAssetSource = implode("\n", array(
		'body{margin:0;background:#eef2f6;color:#12253d;font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}',
		'.vms-add-public{max-width:760px;margin:28px auto;padding:0 14px;}',
		'.vms-add-card{background:#fff;border:1px solid #d6e0eb;border-radius:16px;box-shadow:0 16px 40px rgba(18,37,61,.08);padding:20px;}',
		'h1{margin:0 0 10px;font-size:30px;line-height:1.15;}',
		'.vms-add-meta{background:#f6f9fc;border:1px solid #dce6f1;border-radius:12px;padding:14px 16px;margin:0 0 14px;}',
		'.vms-add-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:18px;}',
		'.vms-add-btn{display:block;text-align:center;text-decoration:none;padding:14px 16px;border-radius:12px;font-weight:700;}',
		'.vms-add-btn--yes{background:#1f7a4c;color:#fff;}',
		'.vms-add-btn--no{background:#8b2d2d;color:#fff;}',
		'.vms-add-note,.vms-add-error,.vms-add-success{border-radius:12px;padding:12px 14px;margin:14px 0;}',
		'.vms-add-note{background:#f6f9fc;border:1px solid #dce6f1;color:#334b63;}',
		'.vms-add-error{background:#fff0f0;border:1px solid #e7b0b0;color:#7a1d1d;}',
		'.vms-add-success{background:#ecfbf2;border:1px solid #abd5b7;color:#13472b;}',
		'@media (max-width:760px){.vms-add-actions{grid-template-columns:1fr;}}',
	)) . "\n";
	$assert($assetSource === $expectedAssetSource, 'ADD public shell stylesheet asset should preserve the exact migrated static rule set and order.');
	$assert(strpos($assetSource, '<?php') === false && strpos($assetSource, '<?=') === false, 'ADD public shell stylesheet should remain purely static CSS.');

	$assert(strpos($renderResponseSource, '<style>') === false, 'ADD public response renderer should not retain inline CSS emitters.');
	$assert(strpos($renderResponseSource, "esc_url(vms_add_dispatch_build_response_url(\$response, 'available'))") !== false, 'ADD public response should preserve the available-link escaping boundary.');
	$assert(strpos($renderResponseSource, "esc_url(vms_add_dispatch_build_response_url(\$response, 'unavailable'))") !== false, 'ADD public response should preserve the unavailable-link escaping boundary.');
	$assert(strpos($renderResponseSource, "nl2br(esc_html((string) \$request['message']))") !== false, 'ADD public response should preserve the escaped note-message boundary.');
	$assert(strpos($renderResponseSource, 'vms_add_dispatch_find_response_by_raw_token($raw_token)') !== false, 'ADD public response should preserve the raw-token lookup boundary.');
	$assert(strpos($renderResponseSource, "sanitize_key((string) (\$request['status'] ?? '')) !== 'active'") !== false, 'ADD public response should preserve the active-request status check.');
	$assert(strpos($renderResponseSource, 'vms_add_dispatch_response_expired($response)') !== false, 'ADD public response should preserve the expired-link guard.');
	$assert(strpos($renderResponseSource, '$choice = vms_add_dispatch_get_request_choice();') !== false, 'ADD public response should preserve the request-choice handling.');

	$assert(strpos($routerSource, 'if (is_admin()) {') !== false, 'ADD public shell router should preserve the is_admin() guard.');
	$assert(strpos($routerSource, '$token = vms_add_dispatch_get_request_token();') !== false, 'ADD public shell router should preserve the request-token fetch.');
	$assert(strpos($routerSource, "if (\$token === '') {") !== false, 'ADD public shell router should preserve the empty-token early return.');
	$assert(strpos($routerSource, 'vms_add_dispatch_render_public_response($token);') !== false, 'ADD public shell router should preserve the response render dispatch.');

	$expectedAllowedHtml = array(
		'a' => array(
			'class' => true,
			'href' => true,
		),
		'br' => array(),
		'div' => array(
			'class' => true,
		),
		'h1' => array(),
		'p' => array(
			'class' => true,
		),
		'strong' => array(),
	);
	$assert(vms_add_dispatch_public_response_allowed_html() === $expectedAllowedHtml, 'ADD public shell allowlist should remain unchanged.');

	$assert(strpos($outputTestSource, 'ADD public shell output remediation OK.') !== false, 'Existing ADD public shell output remediation test should remain present.');
	$assert(strpos($adminUiSource, "VMS_PLUGIN_URL . 'assets/css/vms-add-dispatch-admin.css'") !== false, 'ADD admin asset ownership should remain unchanged in this slice.');
	$assert(strpos($helpersSource, 'function vms_add_dispatch_get_event_plan_need_scan(int $limit = 12, int $excluded_limit = 8, array $options = array()): array') !== false, 'ADD helper/open-needs query logic should remain unchanged in this slice.');
	$assert(strpos($openNeedsTestSource, 'Future Event Plan with missing Primary Vendor should appear in ADD open needs.') !== false, 'The adjudicated ADD open-needs baseline diagnostic should remain unchanged.');

	$assert($publicSource === $livePublicSource, 'Mirror/live ADD public-shell PHP should remain byte-identical before commit.');
	$assert($assetSource === $liveAssetSource, 'Mirror/live ADD public-shell CSS should remain byte-identical before commit.');

	$assert(strpos($ledgerSource, 'WPORG-22R-J') !== false, 'Ledger should record the WPORG-22R-J closeout.');
	$assert(strpos($prereviewSource, 'WPORG-22R-J') !== false, 'Prereview remediation should record the WPORG-22R-J closeout.');
	$assert(strpos($prereviewSource, '`WPORG-24` is now closed') !== false, 'Prereview remediation should keep WPORG-24 closed.');
	$assert(strpos($prereviewSource, 'Review-10 Upload APIs Result') !== false, 'Prereview remediation should keep Review-10 closed.');

	fwrite(STDOUT, "ADD public shell inline css remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'ADD public shell inline css remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
