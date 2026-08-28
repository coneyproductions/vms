<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['bvmgr_test_query_vars'] = array();
$GLOBALS['bvmgr_test_actions'] = array();
$GLOBALS['bvmgr_test_options'] = array();
$GLOBALS['bvmgr_test_option_writes'] = array();
$GLOBALS['bvmgr_test_rewrite_flushes'] = array();

function get_query_var(string $key, $default = '')
{
	return array_key_exists($key, $GLOBALS['bvmgr_test_query_vars'])
		? $GLOBALS['bvmgr_test_query_vars'][$key]
		: $default;
}
function wp_unslash($value)
{
	return $value;
}
function add_action(string $hook, $callback, int $priority = 10): void
{
	$GLOBALS['bvmgr_test_actions'][] = array($hook, $callback, $priority);
}
function get_option(string $key, $default = false)
{
	return $GLOBALS['bvmgr_test_options'][$key] ?? $default;
}
function update_option(string $key, $value, $autoload = null): bool
{
	$GLOBALS['bvmgr_test_options'][$key] = $value;
	$GLOBALS['bvmgr_test_option_writes'][] = array($key, $value, $autoload);
	return true;
}
function flush_rewrite_rules(bool $hard = true): void
{
	$GLOBALS['bvmgr_test_rewrite_flushes'][] = $hard;
}

$_GET = array();
$_POST = array();
$_REQUEST = array();

$root = dirname(__DIR__);
require_once $root . '/includes/core/prefix-b4-compat.php';
require_once $root . '/scripts/lib/wporg-prefix-b4.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$assert(in_array(array('init', 'bvmgr_prefix_b4_maybe_flush_rewrite_rules', 100), $GLOBALS['bvmgr_test_actions'], true), 'B4 must register its versioned rewrite flush after every reviewed rewrite producer.');

$GLOBALS['bvmgr_test_query_vars'] = array('bvmgr_pass_claim_token' => 'canonical-qv', 'vms_pass_claim_token' => 'legacy-qv');
$_GET = array('bvmgr_pass_claim_token' => 'canonical-get', 'vms_pass_claim_token' => 'legacy-get');
$assert(bvmgr_get_query_var_compat('bvmgr_pass_claim_token') === 'canonical-qv', 'A canonical parsed query var must win over every legacy source.');

$GLOBALS['bvmgr_test_query_vars'] = array('bvmgr_pass_claim_token' => '', 'vms_pass_claim_token' => 'legacy-qv');
$_GET = array('bvmgr_pass_claim_token' => 'canonical-get', 'vms_pass_claim_token' => 'legacy-get');
$assert(bvmgr_get_query_var_compat('bvmgr_pass_claim_token') === 'canonical-get', 'A canonical query-string value must win before the legacy parsed query var.');

$GLOBALS['bvmgr_test_query_vars'] = array('vms_pass_claim_token' => 'legacy-qv');
$_GET = array();
$assert(bvmgr_get_query_var_compat('bvmgr_pass_claim_token') === 'legacy-qv', 'A registered legacy parsed query var must remain accepted indefinitely.');

$GLOBALS['bvmgr_test_query_vars'] = array();
$_GET = array('vms_pass_claim_token' => 'legacy-get');
$assert(bvmgr_get_query_var_compat('bvmgr_pass_claim_token') === 'legacy-get', 'A legacy query-string value must remain accepted indefinitely.');

$GLOBALS['bvmgr_test_query_vars'] = array();
$_GET = array('bvmgr_pass_claim_token' => array('bad'), 'vms_pass_claim_token' => array('bad'));
$assert(bvmgr_get_query_var_compat('bvmgr_pass_claim_token', 'safe-default') === 'safe-default', 'Array-shaped canonical and legacy query values must be rejected.');

$GLOBALS['bvmgr_test_options'] = array();
$GLOBALS['bvmgr_test_option_writes'] = array();
$GLOBALS['bvmgr_test_rewrite_flushes'] = array();
bvmgr_prefix_b4_maybe_flush_rewrite_rules();
bvmgr_prefix_b4_maybe_flush_rewrite_rules();
$assert($GLOBALS['bvmgr_test_rewrite_flushes'] === array(false), 'The B4 rewrite migration must perform exactly one soft flush.');
$assert($GLOBALS['bvmgr_test_option_writes'] === array(array('bvmgr_prefix_b4_rewrite_version', '1', false)), 'The B4 rewrite migration must write only its reviewed version marker.');

$map = BVMGR_WPORG_Prefix_B4::loadJson($root . '/' . BVMGR_WPORG_Prefix_B4::MAP_PATH);
$source = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/includes', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
	if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php' && !str_contains(str_replace('\\', '/', $fileInfo->getPathname()), '/includes/safety/')) {
		$source .= "\n" . (string) file_get_contents($fileInfo->getPathname());
	}
}

foreach ((array) $map['categories']['query_vars'] as $row) {
	$canonical = (string) $row['canonical_identifier'];
	$legacy = (string) $row['legacy_identifier'];
	$assert(str_contains($source, "'{$canonical}'"), "B4 runtime must contain the canonical query var {$canonical}.");
	$assert(str_contains($source, "'{$legacy}'") || str_contains($source, "'%{$legacy}%'"), "B4 runtime must retain the legacy inbound query var {$legacy}.");
}
foreach ((array) $map['categories']['rewrite_tags'] as $row) {
	$canonical = (string) $row['canonical_identifier'];
	$legacy = (string) $row['legacy_identifier'];
	$assert(str_contains($source, "add_rewrite_tag('{$canonical}'"), "B4 must register canonical rewrite tag {$canonical}.");
	$assert(str_contains($source, "add_rewrite_tag('{$legacy}'"), "B4 must retain legacy rewrite tag {$legacy}.");
}
foreach ((array) $map['categories']['rewrite_rules'] as $row) {
	$canonical = trim((string) $row['canonical_identifier'], "'");
	$legacy = trim((string) $row['legacy_identifier'], "'");
	$assert(str_contains($source, "'{$canonical}'"), "B4 rewrite target must use {$canonical}.");
	$assert(!str_contains($source, "'{$legacy}'"), "B4 rewrite target must no longer write {$legacy}.");
}

$expectedCli = array(
	'bvmgr' => 'BVMGR_CLI_Stale_Check_Command',
	'vms' => 'BVMGR_CLI_Stale_Check_Command',
	'bvmgr square-ticket-mirror' => 'BVMGR_CLI_Square_Ticket_Mirror_Command',
	'vms square-ticket-mirror' => 'BVMGR_CLI_Square_Ticket_Mirror_Command',
	'bvmgr state-of-range' => 'BVMGR_CLI_State_Of_Range_Command',
	'vms state-of-range' => 'BVMGR_CLI_State_Of_Range_Command',
	'bvmgr event reschedule' => 'BVMGR_CLI_Event_Reschedule_Command',
	'vms event reschedule' => 'BVMGR_CLI_Event_Reschedule_Command',
	'bvmgr event integrity' => 'BVMGR_CLI_Event_Integrity_Command',
	'vms event integrity' => 'BVMGR_CLI_Event_Integrity_Command',
);
foreach ($expectedCli as $path => $callback) {
	$assert(str_contains($source, "WP_CLI::add_command('{$path}', '{$callback}')"), "WP-CLI path {$path} must resolve to the reviewed command class.");
}
$assert(substr_count($source, "WP_CLI::add_command('") === 10, 'Runtime must retain the exact three B4 command pairs plus the two post-B4 event-occurrence command pairs.');

$assert(str_contains($source, "|vms_vendor_app_confirm|"), 'B4 must preserve the historical vendor-confirmation token-hash salt.');
$assert(str_contains($source, "^docs/vms/"), 'B4 must preserve the physical documentation route regex.');

if ($failures !== array()) {
	fwrite(STDERR, "B4 query/rewrite/CLI failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "B4 query vars, rewrite compatibility, one-time flush, and CLI aliases passed.\n";
