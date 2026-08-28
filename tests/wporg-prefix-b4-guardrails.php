<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-b4.php';

$root = dirname(__DIR__);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$mapPath = $root . '/' . BVMGR_WPORG_Prefix_B4::MAP_PATH;
$retirementPath = $root . '/' . BVMGR_WPORG_Prefix_B4::RETIREMENT_PATH;
$manifestPath = $root . '/docs/wporg-prefix-migration-manifest.json';
$map = BVMGR_WPORG_Prefix_B4::loadJson($mapPath);
$retirement = BVMGR_WPORG_Prefix_B4::loadJson($retirementPath);
$manifest = BVMGR_WPORG_Prefix_B4::loadJson($manifestPath);

$expectedSummary = array(
	'browser_globals' => 29,
	'asset_handles' => 64,
	'asset_registration_call_sites' => 99,
	'asset_resolved_source_sites' => 105,
	'asset_dependency_sites' => 34,
	'asset_consumer_sites' => 19,
	'nonce_static_actions' => 154,
	'nonce_dynamic_action_families' => 64,
	'nonce_fields' => 73,
	'query_vars' => 14,
	'rewrite_tags' => 4,
	'rewrite_rules' => 7,
	'cli_paths' => 3,
);
$assert(($map['schema_version'] ?? null) === 1, 'Frozen B4 identifier map schema must be version 1.');
$assert(($map['summary'] ?? null) === $expectedSummary, 'Frozen B4 identifier-map summary must match the exact reviewed inventory.');
$assert(($map['authority']['authorized_starting_head'] ?? null) === 'bdd84df7bcbfcec65ee57fedf561bf4e167761f6', 'Frozen B4 map must retain the authorized starting HEAD.');
$assert(($manifest['b4_identifier_inventory']['sha256'] ?? null) === hash_file('sha256', $mapPath), 'Manifest must bind the exact frozen B4 map hash.');
$assert(($manifest['b4_identifier_inventory']['summary'] ?? null) === $expectedSummary, 'Manifest and frozen B4 map summaries must agree exactly.');
$assert(in_array(($manifest['b4_identifier_inventory']['implementation_state'] ?? null), array('not_started', 'browser_assets_complete', 'nonce_complete', 'complete'), true), 'B4 implementation state must use one reviewed checkpoint value.');

$categories = (array) ($map['categories'] ?? array());
$expectedCategoryCounts = array(
	'browser_globals' => 29,
	'asset_handles' => 64,
	'nonce_actions' => 218,
	'nonce_fields' => 73,
	'query_vars' => 14,
	'rewrite_tags' => 4,
	'rewrite_rules' => 7,
	'cli_paths' => 3,
);
foreach ($expectedCategoryCounts as $category => $expectedCount) {
	$assert(count((array) ($categories[$category] ?? array())) === $expectedCount, "B4 {$category} must contain exactly {$expectedCount} rows.");
}

$prefixRules = array(
	'browser_globals' => array('/^(?:VMS|Vms|__vms|vms)/', '/^BVMGR_/'),
	'asset_handles' => array('/^vms-/', '/^bvmgr-/'),
	'nonce_actions' => array('/^vms_/', '/^bvmgr_/'),
	'nonce_fields' => array('/^_?vms_/', '/^_?bvmgr_/'),
	'query_vars' => array('/^vms_/', '/^bvmgr_/'),
	'rewrite_tags' => array('/^%vms_/', '/^%bvmgr_/'),
	'cli_paths' => array('/^vms(?: |$)/', '/^bvmgr(?: |$)/'),
);
foreach ($prefixRules as $category => [$legacyPattern, $canonicalPattern]) {
	$legacy = array();
	foreach ((array) $categories[$category] as $row) {
		$old = (string) ($row['legacy_identifier'] ?? '');
		$new = (string) ($row['canonical_identifier'] ?? '');
		$assert(preg_match($legacyPattern, $old) === 1, "B4 {$category} legacy identifier must match its reviewed prefix form: {$old}.");
		$assert(preg_match($canonicalPattern, $new) === 1, "B4 {$category} canonical identifier must match its reviewed prefix form: {$new}.");
		$legacy[] = $old;
		foreach (array('registration_source_sites', 'dependency_sites', 'consumer_sites', 'producer_sites', 'verifier_sites', 'registration_sites', 'all_exact_sites') as $siteField) {
			foreach ((array) ($row[$siteField] ?? array()) as $site) {
				$file = (string) ($site['file'] ?? '');
				$assert($file !== '' && is_file($root . '/' . $file), "B4 {$category} site must resolve to a shipped file: {$file}.");
			}
		}
	}
	$assert(count($legacy) === count(array_unique($legacy)), "B4 {$category} legacy identifiers must be unique.");
}

$dynamic = array_filter((array) $categories['nonce_actions'], static fn(array $row): bool => ($row['family_kind'] ?? '') === 'dynamic');
$static = array_filter((array) $categories['nonce_actions'], static fn(array $row): bool => ($row['family_kind'] ?? '') === 'static');
$assert(count($static) === 154 && count($dynamic) === 64, 'B4 nonce action rows must reconcile to exactly 154 static and 64 dynamic families.');
$nonceFieldTargets = array_column((array) $categories['nonce_fields'], 'canonical_identifier');
$assert(count(array_unique($nonceFieldTargets)) === 72, 'The 73 legacy nonce fields must intentionally converge to exactly 72 canonical fields.');
$assert(count(array_filter($nonceFieldTargets, static fn(string $name): bool => $name === '_bvmgr_admin_heavy_nonce')) === 2, 'Only the two reviewed heavy-admin legacy fields may converge on one canonical field.');
foreach ((array) $categories['asset_handles'] as $row) {
	$assert(($row['known_addon_external_consumers'] ?? null) === array(), 'B4 handle rows must retain zero known add-on asset-API consumers.');
	$assert(($row['legacy_inbound_compatibility_required'] ?? null) === false, 'B4 handles must not invent dependency aliases without an external semantic consumer.');
}
foreach ((array) $categories['browser_globals'] as $row) {
	$assert(($row['legacy_inbound_compatibility_required'] ?? null) === false, 'B4 browser globals must use the reviewed atomic cutover with no cache alias.');
}

$assert(($retirement['schema_version'] ?? null) === 1, 'B4 compatibility-retirement schema must be version 1.');
$assert(($retirement['summary'] ?? null) === array('total' => 308, 'temporary' => 294, 'indefinite' => 14), 'B4 compatibility ledger must retain exactly 308 entries: 294 temporary and 14 indefinite.');
$items = (array) ($retirement['items'] ?? array());
$classes = array_count_values(array_column($items, 'compatibility_type'));
ksort($classes, SORT_STRING);
$assert($classes === array('legacy_cli_alias' => 3, 'legacy_nonce_field_read' => 73, 'legacy_nonce_verification' => 218, 'legacy_query_inbound' => 14), 'B4 compatibility entries must be limited to exact nonce, query-var, and CLI contracts.');
$assert(BVMGR_WPORG_Prefix_B4::retirementMap($map) === $retirement, 'B4 compatibility-retirement artifact must regenerate exactly from the frozen map.');

$vendorApp = (string) file_get_contents($root . '/includes/core/vendor-application-confirmation.php');
$assert(str_contains($vendorApp, "'vms_vendor_app_confirm'"), 'B4 must retain the historical vendor-application token-hash salt homonym.');
$referAFriend = array_values(array_filter((array) ($manifest['known_addons'] ?? array()), static fn(array $row): bool => ($row['slug'] ?? '') === 'vms-refer-a-friend'));
$assert(count($referAFriend) === 1 && ($referAFriend[0]['consumed_contracts']['asset_handles'] ?? null) === array(), 'Refer a Friend must remain classified as having no B4 asset-handle consumer.');

$implementationState = (string) ($manifest['b4_identifier_inventory']['implementation_state'] ?? '');
if (in_array($implementationState, array('browser_assets_complete', 'nonce_complete', 'complete'), true)) {
	$cutoverCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/apply-wporg-prefix-b4.php') . ' --check-browser-assets 2>&1';
	$cutoverOutput = array();
	$cutoverStatus = 0;
	exec($cutoverCommand, $cutoverOutput, $cutoverStatus);
	$assert($cutoverStatus === 0, 'B4 browser/asset semantic cutover must be complete: ' . implode(' ', $cutoverOutput));
	foreach (array('browser_globals', 'asset_handles') as $category) {
		foreach ((array) $categories[$category] as $row) {
			$canonical = (string) $row['canonical_identifier'];
			$siteFiles = array();
			foreach (array('registration_source_sites', 'dependency_sites', 'consumer_sites', 'producer_sites') as $siteField) {
				foreach ((array) ($row[$siteField] ?? array()) as $site) {
					$siteFiles[(string) $site['file']] = true;
				}
			}
			$found = false;
			foreach (array_keys($siteFiles) as $file) {
				if (str_contains((string) file_get_contents($root . '/' . $file), $canonical)) {
					$found = true;
					break;
				}
			}
			$assert($found, "B4 {$category} canonical identifier must resolve at a frozen producer/consumer file: {$canonical}.");
		}
	}
}
if (in_array($implementationState, array('nonce_complete', 'complete'), true)) {
	$nonceCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/apply-wporg-prefix-b4.php') . ' --check-nonces 2>&1';
	$nonceOutput = array();
	$nonceStatus = 0;
	exec($nonceCommand, $nonceOutput, $nonceStatus);
	$assert($nonceStatus === 0, 'B4 nonce semantic cutover must be complete: ' . implode(' ', $nonceOutput));
}
if ($implementationState === 'complete') {
	$transitionCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/wporg-prefix-b4-query-rewrite-cli.php') . ' 2>&1';
	$transitionOutput = array();
	$transitionStatus = 0;
	exec($transitionCommand, $transitionOutput, $transitionStatus);
	$assert($transitionStatus === 0, 'B4 query/rewrite/CLI transition must be complete: ' . implode(' ', $transitionOutput));
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/generate-wporg-prefix-b4.php') . ' --check 2>&1';
$output = array();
$status = 0;
exec($command, $output, $status);
$assert($status === 0, 'Frozen B4 generator check must pass: ' . implode(' ', $output));

if ($failures !== array()) {
	fwrite(STDERR, "B4 prefix guardrail failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "B4 exact identifier-map and compatibility guardrails passed.\n";
