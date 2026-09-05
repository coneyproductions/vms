<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['bvmgr_test_nonce_results'] = array();
$GLOBALS['bvmgr_test_nonce_calls'] = array();
$GLOBALS['bvmgr_test_nonce_actions'] = array();

function wp_verify_nonce($nonce, $action)
{
	$GLOBALS['bvmgr_test_nonce_calls'][] = array($nonce, $action);
	return $GLOBALS['bvmgr_test_nonce_results'][$action] ?? false;
}
function wp_unslash($value)
{
	return $value;
}
function sanitize_text_field($value): string
{
	return is_scalar($value) ? trim((string) $value) : '';
}
function do_action($hook, ...$args): void
{
	$GLOBALS['bvmgr_test_nonce_actions'][] = array($hook, $args);
}
function wp_nonce_ays($action): void
{
	throw new RuntimeException('nonce-ays:' . (string) $action);
}
function wp_doing_ajax(): bool
{
	return false;
}
function wp_die($message = '', $title = '', $args = array()): void
{
	throw new RuntimeException('wp-die:' . (string) $message);
}

$_GET = array(
	'vms_rating_nonce' => 'legacy-get',
	'bvmgr_rating_nonce' => 'canonical-get',
);
$_POST = array('vms_feedback_nonce' => 'legacy-post');
$_REQUEST = array(
	'vms_admin_heavy_nonce' => 'first-heavy',
	'_vms_admin_heavy_nonce' => 'second-heavy',
	'vms_rating_nonce' => 'legacy-request',
	'bvmgr_rating_nonce' => 'canonical-request',
);

require_once dirname(__DIR__) . '/includes/core/prefix-b4-compat.php';
require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-b4.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$assert($_GET['bvmgr_rating_nonce'] === 'canonical-get', 'Canonical GET nonce fields must win over legacy input.');
$assert(($_POST['bvmgr_feedback_nonce'] ?? null) === 'legacy-post', 'Legacy POST nonce fields must normalize into an absent canonical field.');
$assert($_REQUEST['bvmgr_rating_nonce'] === 'canonical-request', 'Canonical REQUEST nonce fields must win over legacy input.');
$assert(($_REQUEST['_bvmgr_admin_heavy_nonce'] ?? null) === 'first-heavy', 'The historically primary unprefixed heavy-admin field must win when both legacy spellings are submitted.');

$map = BVMGR_WPORG_Prefix_B4::loadJson(dirname(__DIR__) . '/' . BVMGR_WPORG_Prefix_B4::MAP_PATH);
$expectedFields = array();
foreach ((array) $map['categories']['nonce_fields'] as $row) {
	$expectedFields[(string) $row['legacy_identifier']] = (string) $row['canonical_identifier'];
}
$runtimeFields = bvmgr_prefix_b4_nonce_field_map();
ksort($expectedFields, SORT_STRING);
ksort($runtimeFields, SORT_STRING);
$assert($runtimeFields === $expectedFields, 'Runtime legacy-field normalization must exactly equal all 73 frozen field rows.');
$assert(count(array_unique($runtimeFields)) === 72, 'Runtime nonce fields must preserve the reviewed 73-to-72 heavy-admin convergence.');

$GLOBALS['bvmgr_test_nonce_calls'] = array();
$GLOBALS['bvmgr_test_nonce_results'] = array('bvmgr_example_action' => 2, 'vms_example_action' => 1);
$canonicalAction = bvmgr_nonce_action_for_value('canonical-token', 'bvmgr_example_action');
$assert($canonicalAction === 'bvmgr_example_action', 'Canonical nonce verification must select the canonical action first.');
$assert(wp_verify_nonce('canonical-token', $canonicalAction) === 2, 'The native verifier must accept the selected canonical action.');
$assert($GLOBALS['bvmgr_test_nonce_calls'] === array(array('canonical-token', 'bvmgr_example_action'), array('canonical-token', 'bvmgr_example_action')), 'A valid canonical nonce must not trigger a legacy retry before the native verifier runs.');

$GLOBALS['bvmgr_test_nonce_calls'] = array();
$GLOBALS['bvmgr_test_nonce_results'] = array('vms_example_action' => 1);
$legacyAction = bvmgr_nonce_action_for_value('legacy-token', 'bvmgr_example_action');
$assert($legacyAction === 'vms_example_action', 'A valid legacy nonce must select only its exact fallback action.');
$assert(wp_verify_nonce('legacy-token', $legacyAction) === 1, 'The native verifier must accept the selected legacy action.');
$assert($GLOBALS['bvmgr_test_nonce_calls'] === array(array('legacy-token', 'bvmgr_example_action'), array('legacy-token', 'vms_example_action'), array('legacy-token', 'vms_example_action')), 'Legacy verification must occur only after the canonical action fails and then pass through the native verifier.');

$GLOBALS['bvmgr_test_nonce_calls'] = array();
$GLOBALS['bvmgr_test_nonce_results'] = array('vms_other_action' => 1);
$wrongAction = bvmgr_nonce_action_for_value('wrong-action-token', 'bvmgr_example_action');
$assert($wrongAction === 'bvmgr_example_action' && wp_verify_nonce('wrong-action-token', $wrongAction) === false, 'A nonce valid for a different action must remain invalid through the native verifier.');
$assert($GLOBALS['bvmgr_test_nonce_calls'] === array(array('wrong-action-token', 'bvmgr_example_action'), array('wrong-action-token', 'vms_example_action'), array('wrong-action-token', 'bvmgr_example_action')), 'Wrong-action verification must stay bounded to the exact canonical/legacy pair.');

$_REQUEST = array('_bvmgr_test_nonce' => 'admin-token');
$GLOBALS['bvmgr_test_nonce_results'] = array('vms_admin_test' => 1);
$assert(bvmgr_nonce_action_for_request('bvmgr_admin_test', '_bvmgr_test_nonce') === 'vms_admin_test', 'Admin referer compatibility must select a still-valid legacy action nonce for the native core verifier.');
$_REQUEST = array('_ajax_nonce' => 'ajax-token');
$GLOBALS['bvmgr_test_nonce_results'] = array('bvmgr_ajax_test' => 2);
$assert(bvmgr_nonce_action_for_request('bvmgr_ajax_test', false) === 'bvmgr_ajax_test', 'AJAX compatibility must select a valid canonical action for the native core verifier.');
$_REQUEST = array('_ajax_nonce' => array('bad'));
$GLOBALS['bvmgr_test_nonce_results'] = array();
$assert(bvmgr_nonce_action_for_request('bvmgr_ajax_test', false) === 'bvmgr_ajax_test', 'Array-shaped or invalid AJAX nonce input must return the canonical action so the native verifier rejects it.');

$root = dirname(__DIR__);
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/apply-wporg-prefix-b4.php') . ' --check-nonces 2>&1';
$output = array();
$status = 0;
exec($command, $output, $status);
$assert($status === 0, 'Every frozen nonce action and field site must use its canonical identifier/helper: ' . implode(' ', $output));
$nativeCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/apply-wporg-prefix-b4.php') . ' --check-native-nonce-verifiers 2>&1';
$nativeOutput = array();
$nativeStatus = 0;
exec($nativeCommand, $nativeOutput, $nativeStatus);
$assert($nativeStatus === 0, 'Every B4 nonce verifier must remain visible to native WordPressCS analysis: ' . implode(' ', $nativeOutput));

$b7Overlap = array(
	'vms_get_venue_comp_defaults',
	'vms_set_dashboard_prefs',
	'vms_social_load_event_panel',
	'vms_ticketing_claims_log_client_action',
	'vms_ticketing_claims_validate_assignee',
	'vms_ticketing_v2_atomic_add_to_cart',
	'vms_ticketing_v2_cart_context',
	'vms_ticketing_v2_silent_add',
);
$runtimeSource = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/includes', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
	if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php' && !str_contains(str_replace('\\', '/', $fileInfo->getPathname()), '/includes/safety/')) {
		$runtimeSource .= (string) file_get_contents($fileInfo->getPathname());
	}
}
foreach ($b7Overlap as $legacyAction) {
	$assert(str_contains($runtimeSource, 'wp_ajax_' . $legacyAction), "B4 must retain the B7 AJAX registration for {$legacyAction}.");
}
$siteSources = array();
foreach ((array) $map['categories']['nonce_actions'] as $row) {
	$combined = '';
	foreach (array_merge((array) ($row['producer_sites'] ?? array()), (array) ($row['verifier_sites'] ?? array())) as $site) {
		$file = (string) $site['file'];
		if (!isset($siteSources[$file])) {
			$siteSources[$file] = (string) file_get_contents($root . '/' . $file);
		}
		$combined .= $siteSources[$file];
	}
	$canonical = (string) $row['canonical_identifier'];
	$needle = strstr($canonical, '{*}', true);
	$needle = $needle === false ? $canonical : $needle;
	$assert($needle !== '' && str_contains($combined, $needle), "Canonical nonce action/family must resolve at a frozen producer or verifier file: {$canonical}.");
}
foreach ((array) $map['categories']['nonce_fields'] as $row) {
	$combined = '';
	foreach ((array) ($row['all_exact_sites'] ?? array()) as $site) {
		$file = (string) $site['file'];
		if (!isset($siteSources[$file])) {
			$siteSources[$file] = (string) file_get_contents($root . '/' . $file);
		}
		$combined .= $siteSources[$file];
	}
	$assert(str_contains($combined, (string) $row['canonical_identifier']), 'Canonical nonce field must resolve at a frozen shipped site: ' . (string) $row['canonical_identifier'] . '.');
}
$assert(str_contains($runtimeSource, "wp_verify_nonce(\$nonce, 'wp_rest')"), 'Core wp_rest nonce verification must remain outside B4 compatibility rewriting.');
$assert(str_contains($runtimeSource, "wp_verify_nonce(\$post_nonce, 'update-post_'"), 'Core update-post nonce verification must remain outside B4 compatibility rewriting.');
$assert(str_contains($runtimeSource, "'vms_admission_bad_nonce'"), 'The B7 REST error code vms_admission_bad_nonce must remain unchanged.');

if ($failures !== array()) {
	fwrite(STDERR, "B4 nonce failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "B4 nonce action and field compatibility tests passed.\n";
