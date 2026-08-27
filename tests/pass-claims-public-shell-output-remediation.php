<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('BVMGR_PLUGIN_URL')) {
	define('BVMGR_PLUGIN_URL', 'https://plugin.example.test/wp-content/plugins/backstage-venue-manager/');
}

if (!defined('BVMGR_VERSION')) {
	define('BVMGR_VERSION', 'test-version');
}

final class VmsPassClaimsPublicShellFooterReached extends RuntimeException
{
}

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_filters'] = array();
$GLOBALS['vms_test_status_headers'] = array();
$GLOBALS['vms_test_nocache_headers'] = array();
$GLOBALS['vms_test_enqueued_styles'] = array();
$GLOBALS['vms_test_enqueued_scripts'] = array();
$GLOBALS['vms_test_header_calls'] = 0;
$GLOBALS['vms_test_footer_calls'] = 0;

if (!function_exists('add_action')) {
	function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
	{
		if (!isset($GLOBALS['vms_test_actions'][(string) $hook_name])) {
			$GLOBALS['vms_test_actions'][(string) $hook_name] = array();
		}
		if (!isset($GLOBALS['vms_test_actions'][(string) $hook_name][(int) $priority])) {
			$GLOBALS['vms_test_actions'][(string) $hook_name][(int) $priority] = array();
		}
		$GLOBALS['vms_test_actions'][(string) $hook_name][(int) $priority][] = array(
			'callback' => $callback,
			'accepted_args' => (int) $accepted_args,
		);
		return true;
	}
}

if (!function_exists('add_filter')) {
	function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
	{
		$GLOBALS['vms_test_filters'][] = array(
			'hook' => (string) $hook_name,
			'callback' => $callback,
			'priority' => (int) $priority,
			'accepted_args' => (int) $accepted_args,
		);
		return true;
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
		$GLOBALS['vms_test_enqueued_styles'][] = array(
			'handle' => (string) $handle,
			'src' => (string) $src,
			'deps' => (array) $deps,
			'ver' => $ver,
			'media' => (string) $media,
		);
		return true;
	}
}

if (!function_exists('wp_enqueue_script')) {
	function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false): bool
	{
		$GLOBALS['vms_test_enqueued_scripts'][] = array(
			'handle' => (string) $handle,
			'src' => (string) $src,
			'deps' => (array) $deps,
			'ver' => $ver,
			'in_footer' => (bool) $in_footer,
		);
		return true;
	}
}

if (!function_exists('get_header')) {
	function get_header(): void
	{
		$GLOBALS['vms_test_header_calls']++;
		echo '<header-marker></header-marker>';
	}
}

if (!function_exists('get_footer')) {
	function get_footer(): void
	{
		$GLOBALS['vms_test_footer_calls']++;
		echo '<footer-marker></footer-marker>';
		throw new VmsPassClaimsPublicShellFooterReached('footer');
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

$assert(is_string($passClaimsSource) && $passClaimsSource !== '', 'Pass Claims source should be readable.');
$assert(is_string($adminShellSource) && $adminShellSource !== '', 'Administrator shell source should be readable.');

$shellStart = strpos($passClaimsSource, "if (!function_exists('bvmgr_pass_claims_render_public_shell'))");
$shellEnd = strpos($passClaimsSource, "if (!function_exists('bvmgr_pass_claims_render_public_claim'))");
$assert($shellStart !== false && $shellEnd !== false && $shellEnd > $shellStart, 'Pass Claims public shell block should be locatable.');
$shellSource = substr($passClaimsSource, (int) $shellStart, (int) $shellEnd - (int) $shellStart);

$assert(strpos($shellSource, 'function bvmgr_pass_claims_render_public_shell(string $headline, callable $render_content): void') !== false, 'Pass Claims public shell should accept a callable renderer.');
$assert(strpos($shellSource, '@param callable():void $render_content Package-owned renderer callback that echoes one accepted Pass Claims public family.') !== false, 'Pass Claims public shell should document the package-owned renderer contract.');
$assert(strpos($shellSource, 'string $content_html') === false, 'Pass Claims public shell should no longer expose a raw content_html string parameter.');
$assert(strpos($shellSource, 'echo $content_html;') === false, 'Pass Claims public shell should remove the raw content_html sink.');
$assert(strpos($shellSource, '$render_content();') !== false, 'Pass Claims public shell should invoke the renderer callback directly.');
$assert(strpos($shellSource, 'ob_start(') === false && strpos($shellSource, 'ob_get_clean(') === false, 'Pass Claims public shell should not buffer and re-emit combined renderer output.');
$assert(strpos($shellSource, 'wp_kses(') === false && strpos($shellSource, 'wp_kses_post(') === false, 'Pass Claims public shell should not introduce a broad shared allowlist.');
$assert(strpos($shellSource, 'call_user_func(') === false && strpos($shellSource, 'call_user_func_array(') === false, 'Pass Claims public shell should invoke the renderer directly.');
$assert(strpos($shellSource, 'do_action(') === false && strpos($shellSource, 'apply_filters(') === false, 'Pass Claims public shell should not expose the renderer through hooks or filters.');
$assert(strpos($shellSource, "echo '<!doctype html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"></head><body>';") !== false, 'Pass Claims public shell should preserve the fallback document opener.');
$assert(strpos($shellSource, "echo '<main id=\"primary\" class=\"site-main vms-pass-public-page\" role=\"main\">';") !== false, 'Pass Claims public shell should preserve the main wrapper.');
$assert(strpos($shellSource, "echo '<div class=\"vms-pass-wrap\"><div class=\"vms-pass-card\">';") !== false, 'Pass Claims public shell should preserve the card wrapper.');
$assert(strpos($shellSource, "echo '</div></div>';") !== false && strpos($shellSource, "echo '</main>';") !== false, 'Pass Claims public shell should preserve the closing wrappers.');
$assert(strpos($shellSource, "echo '</body></html>';") !== false, 'Pass Claims public shell should preserve the fallback document closer.');
$assert(strpos($shellSource, 'exit;') !== false, 'Pass Claims public shell should preserve explicit termination.');

$assert(substr_count($passClaimsSource, 'bvmgr_pass_claims_render_public_shell(') === 5, 'Pass Claims public shell should have exactly four production call sites plus its definition.');
$assert(preg_match('~function bvmgr_pass_claims_render_public_status_screen\(string \$headline, string \$title, string \$message\): void\s*\{\s*bvmgr_pass_claims_render_public_shell\(\$headline,\s*static function \(\) use \(\$title, \$message\): void \{\s*echo bvmgr_pass_claims_public_status_fragment\(\$title, \$message\);\s*\}\);\s*\}~s', $passClaimsSource) === 1, 'Pass Claims public status renderer should pass a package-owned closure into the public shell.');
$assert(preg_match('~function bvmgr_pass_claims_render_public_claimed_card\(int \$entry_id\): void\s*\{\s*bvmgr_pass_claims_render_public_shell\(__\(\'Already Claimed\', \'backstage-venue-manager\'\),\s*static function \(\) use \(\$entry_id\): void \{\s*echo bvmgr_pass_claims_public_claimed_card_html\(\$entry_id\);\s*\}\);\s*\}~s', $passClaimsSource) === 1, 'Pass Claims already-claimed renderer should pass a package-owned closure into the public shell.');
$assert(preg_match('~function bvmgr_pass_claims_render_public_success_confirmation\(array \$success, string \$posted_email\): void\s*\{\s*bvmgr_pass_claims_render_public_shell\(__\(\'Pass Claimed\', \'backstage-venue-manager\'\),\s*static function \(\) use \(\$success, \$posted_email\): void \{\s*echo bvmgr_pass_claims_public_success_confirmation_html\(\$success, \$posted_email\);\s*\}\);\s*\}~s', $passClaimsSource) === 1, 'Pass Claims success renderer should pass a package-owned closure into the public shell.');
$assert(preg_match('~function bvmgr_pass_claims_render_public_form\(array \$batch, array \$eligible_events, array \$posted, string \$error, int \$max_party_size\): void\s*\{\s*bvmgr_pass_claims_render_public_shell\(__\(\'Claim Your Pass\', \'backstage-venue-manager\'\),\s*static function \(\) use \(\$batch, \$eligible_events, \$posted, \$error, \$max_party_size\): void \{\s*echo bvmgr_pass_claims_public_form_html\(\$batch, \$eligible_events, \$posted, \$error, \$max_party_size\);\s*\}\);\s*\}~s', $passClaimsSource) === 1, 'Pass Claims public form renderer should pass a package-owned closure into the public shell.');
$assert(strpos($adminShellSource, 'echo $captured_notices_html;') !== false && strpos($adminShellSource, 'echo $content_html;') !== false, 'Administrator shell raw captured and content sinks should remain unchanged.');

$renderCount = 0;
$renderOutput = '';
$GLOBALS['vms_test_filters'] = array();
$GLOBALS['vms_test_status_headers'] = array();
$GLOBALS['vms_test_nocache_headers'] = array();
$GLOBALS['vms_test_enqueued_styles'] = array();
$GLOBALS['vms_test_enqueued_scripts'] = array();
$GLOBALS['vms_test_header_calls'] = 0;
$GLOBALS['vms_test_footer_calls'] = 0;

ob_start();
try {
	bvmgr_pass_claims_render_public_shell('Shell Headline', static function () use (&$renderCount, &$renderOutput): void {
		$renderCount++;
		$renderOutput = '<section class="payload">Rendered payload</section>';
		echo $renderOutput;
	});
} catch (VmsPassClaimsPublicShellFooterReached $e) {
}
$actualOutput = ob_get_clean();

$assert($renderCount === 1, 'Pass Claims public shell should invoke the renderer callback exactly once.');
$assert($GLOBALS['vms_test_status_headers'] === array(200), 'Pass Claims public shell should preserve the 200 status header.');
$assert($GLOBALS['vms_test_nocache_headers'] === array(true), 'Pass Claims public shell should preserve nocache headers.');
$assert($GLOBALS['vms_test_header_calls'] === 1 && $GLOBALS['vms_test_footer_calls'] === 1, 'Pass Claims public shell should preserve get_header() and get_footer() execution.');
$assert($GLOBALS['vms_test_enqueued_styles'] === array(
	array(
		'handle' => 'vms-pass-claims-public',
		'src' => BVMGR_PLUGIN_URL . 'assets/css/vms-pass-claims-public.css',
		'deps' => array(),
		'ver' => BVMGR_VERSION,
		'media' => 'all',
	),
), 'Pass Claims public shell should preserve the public stylesheet enqueue.');
$assert($GLOBALS['vms_test_enqueued_scripts'] === array(
	array(
		'handle' => 'vms-pass-claims-public',
		'src' => BVMGR_PLUGIN_URL . 'assets/js/vms-pass-claims-public.js',
		'deps' => array(),
		'ver' => BVMGR_VERSION,
		'in_footer' => true,
	),
), 'Pass Claims public shell should preserve the public script enqueue.');
$assert(count($GLOBALS['vms_test_filters']) === 1, 'Pass Claims public shell should register exactly one document title filter.');
$assert($GLOBALS['vms_test_filters'][0]['hook'] === 'document_title_parts' && $GLOBALS['vms_test_filters'][0]['priority'] === 20 && $GLOBALS['vms_test_filters'][0]['accepted_args'] === 1, 'Pass Claims public shell should preserve the document_title_parts filter registration.');
$titleParts = array(
	'title' => 'Old Title',
	'site' => 'Serenade Range',
);
$filterCallback = $GLOBALS['vms_test_filters'][0]['callback'];
$assert(is_callable($filterCallback), 'Pass Claims public shell should register a callable document title filter.');
$assert($filterCallback($titleParts) === array(
	'title' => 'Shell Headline',
	'site' => 'Serenade Range',
), 'Pass Claims public shell should preserve the document title rewrite behavior.');

$expectedOutput = '<header-marker></header-marker>';
$expectedOutput .= '<main id="primary" class="site-main vms-pass-public-page" role="main">';
$expectedOutput .= '<div class="vms-pass-wrap"><div class="vms-pass-card">';
$expectedOutput .= $renderOutput;
$expectedOutput .= '</div></div>';
$expectedOutput .= '</main>';
$expectedOutput .= '<footer-marker></footer-marker>';
$assert($actualOutput === $expectedOutput, 'Pass Claims public shell should preserve the exact wrapper ordering while inserting renderer output at the original content position.');

fwrite(STDOUT, "Pass Claims public shell output remediation OK.\n");
