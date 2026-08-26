<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class VMS_Schema_Query_Guard
{
	/** @var array<int,string> */
	public array $calls = array();

	public function __call(string $name, array $arguments)
	{
		unset($arguments);
		$this->calls[] = $name;
		throw new RuntimeException('Descriptor boundary called direct wpdb method: ' . $name);
	}
}

final class WP_Query
{
	public function __construct(array $args = array())
	{
		unset($args);
		vms_schema_record_query_call('WP_Query');
	}
}

function vms_schema_record_query_call(string $name): void
{
	$GLOBALS['vms_schema_query_calls'][] = $name;
	throw new RuntimeException('Descriptor boundary called query API: ' . $name);
}

function get_posts(array $args = array()): array
{
	unset($args);
	vms_schema_record_query_call('get_posts');
}

function get_users(array $args = array()): array
{
	unset($args);
	vms_schema_record_query_call('get_users');
}

function query_posts(array $args = array()): array
{
	unset($args);
	vms_schema_record_query_call('query_posts');
}

function absint($value): int
{
	return abs((int) $value);
}

function vms_meta_key(string $scope, string $name): string
{
	$GLOBALS['vms_schema_meta_key_calls'][] = array($scope, $name);
	if ($scope === 'event_plan' && $name === 'checkin_close_at') {
		return '_checkin_close_at';
	}

	return '_vms_' . $scope . '_' . $name;
}

function apply_filters(string $hook, $value)
{
	$GLOBALS['vms_schema_filter_calls'][] = $hook;
	return $value;
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('UTC');
}

function vms_ops_ticket_post_show_scan_buffer_hours(): int
{
	return 4;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($single);
	$GLOBALS['vms_schema_meta_api_calls'][] = array('get_post_meta', $post_id, $key);
	return $GLOBALS['vms_schema_post_meta'][$post_id][$key] ?? '';
}

function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['vms_schema_meta_api_calls'][] = array('update_post_meta', $post_id, $key, $value);
	$GLOBALS['vms_schema_post_meta'][$post_id][$key] = $value;
	return true;
}

function delete_post_meta(int $post_id, string $key): bool
{
	$GLOBALS['vms_schema_meta_api_calls'][] = array('delete_post_meta', $post_id, $key);
	unset($GLOBALS['vms_schema_post_meta'][$post_id][$key]);
	return true;
}

function vms_schema_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function vms_schema_same($expected, $actual, string $message): void
{
	vms_schema_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

/** @return array<int,string> */
function vms_schema_annotation_lines(string $source): array
{
	$lines = preg_split('/\R/', $source);
	if (!is_array($lines)) {
		return array();
	}

	return array_values(array_filter(
		$lines,
		static fn(string $line): bool => strpos($line, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_key') !== false
	));
}

$GLOBALS['wpdb'] = new VMS_Schema_Query_Guard();
$GLOBALS['vms_schema_query_calls'] = array();
$GLOBALS['vms_schema_meta_key_calls'] = array();
$GLOBALS['vms_schema_filter_calls'] = array();
$GLOBALS['vms_schema_meta_api_calls'] = array();
$GLOBALS['vms_schema_post_meta'] = array(
	701 => array(
		'_vms_event_plan_start_datetime' => '2026-08-08 18:00:00',
		'_vms_event_plan_end_datetime' => '2026-08-08 21:00:00',
	),
);

$plugin_root = dirname(__DIR__);
$shadow_root = dirname(__DIR__, 3) . '/vms';
$schema_path = $plugin_root . '/includes/core/registry/vendor-schema.php';
$meta_registry_path = $plugin_root . '/includes/core/registry/class-vms-vendor-meta-registry.php';
$checkin_path = $plugin_root . '/includes/helpers/checkin-close.php';
$shadow_schema_path = $shadow_root . '/includes/core/registry/vendor-schema.php';
$shadow_checkin_path = $shadow_root . '/includes/helpers/checkin-close.php';

foreach (array($schema_path, $meta_registry_path, $checkin_path, $shadow_schema_path, $shadow_checkin_path) as $required_path) {
	vms_schema_assert(is_file($required_path), 'Required schema/check-in path is missing: ' . $required_path);
}

$schema_source = (string) file_get_contents($schema_path);
$checkin_source = (string) file_get_contents($checkin_path);
$shadow_schema_source = (string) file_get_contents($shadow_schema_path);
$shadow_checkin_source = (string) file_get_contents($shadow_checkin_path);
vms_schema_assert($schema_source !== '' && $checkin_source !== '', 'Mirror runtime sources should be readable.');
vms_schema_same($schema_source, $shadow_schema_source, 'Vendor schema must remain mirror/shadow-live byte-identical.');
vms_schema_same($checkin_source, $shadow_checkin_source, 'Check-in close helper must remain mirror/shadow-live byte-identical.');

require $schema_path;
require $meta_registry_path;
require $checkin_path;

$schema = vms_vendor_schema();
vms_schema_same(31, count($schema), 'Vendor schema should retain all 31 canonical field descriptors.');
vms_schema_same(
	array('display_name', 'primary_email', 'primary_phone', 'email', 'phone', 'website', 'vendor_type'),
	array_slice(array_keys($schema), 0, 7),
	'Vendor schema should retain canonical identity/contact ordering.'
);

$meta_fields = array_filter(
	$schema,
	static fn(array $definition): bool => ($definition['storage'] ?? '') === 'meta'
);
vms_schema_same(28, count($meta_fields), 'Vendor schema should retain exactly 28 metadata descriptors.');
foreach ($meta_fields as $field => $definition) {
	vms_schema_assert(
		isset($definition['meta_key']) && is_string($definition['meta_key']) && $definition['meta_key'] !== '',
		'Metadata descriptor should retain a concrete meta_key: ' . $field
	);
}

vms_schema_assert(isset($schema['tax_tin']) && is_array($schema['tax_tin']), 'Sensitive tax_tin descriptor should remain present.');
vms_schema_same('ingest_only', $schema['tax_tin']['storage'] ?? null, 'tax_tin should remain ingest-only.');
vms_schema_same(false, $schema['tax_tin']['persist'] ?? null, 'tax_tin should remain explicitly nonpersisted.');
vms_schema_same(true, $schema['tax_tin']['sensitive'] ?? null, 'tax_tin should remain explicitly sensitive.');
vms_schema_same(true, $schema['tax_tin']['importable'] ?? null, 'tax_tin should remain ingestable.');
vms_schema_assert(!array_key_exists('meta_key', $schema['tax_tin']), 'tax_tin must not acquire a persistence meta key.');

$registry = BVMGR_Vendor_Meta_Registry::get();
vms_schema_same(28, count($registry), 'Derived vendor meta registry should retain exactly 28 registrations.');
vms_schema_same(
	array_values(array_column($meta_fields, 'meta_key')),
	array_keys($registry),
	'Derived meta registrations should preserve schema order and exact keys.'
);
vms_schema_assert(!isset($registry['_vms_vendor_tax_tin']), 'Derived registry must never register sensitive tax_tin.');
vms_schema_same(array('vms_vendor_meta_registry'), $GLOBALS['vms_schema_filter_calls'], 'Derived registry should retain its extension filter.');

$stored = vms_event_plan_store_checkin_close_meta(701);
vms_schema_assert(($stored['datetime'] ?? null) instanceof DateTimeImmutable, 'Stored check-in result should retain its DateTimeImmutable contract.');
vms_schema_same('2026-08-09 01:00:00', $stored['datetime']->format('Y-m-d H:i:s'), 'Check-in close should retain the four-hour post-show buffer.');
vms_schema_same('stored', $stored['reason'] ?? null, 'Stored check-in result should retain its reason.');
vms_schema_same(true, $stored['stored'] ?? null, 'Stored check-in result should report persistence.');
vms_schema_same('2026-08-09 01:00:00', $stored['checkin_close_at'] ?? null, 'Stored check-in result should retain its serialized value.');
vms_schema_same('_checkin_close_at', $stored['meta_key'] ?? null, 'Stored check-in result should expose its metadata descriptor.');
vms_schema_same('2026-08-09 01:00:00', $GLOBALS['vms_schema_post_meta'][701]['_checkin_close_at'] ?? null, 'Stored check-in value should reach the WordPress metadata API.');

$missing = vms_event_plan_store_checkin_close_meta(702);
vms_schema_same('missing_schedule', $missing['reason'] ?? null, 'Missing schedule should retain its failure reason.');
vms_schema_same(false, $missing['stored'] ?? null, 'Missing schedule should report no stored value.');
vms_schema_same('', $missing['checkin_close_at'] ?? null, 'Missing schedule should return an empty serialized value.');
vms_schema_same('_checkin_close_at', $missing['meta_key'] ?? null, 'Missing schedule result should still expose its metadata descriptor.');

vms_schema_same(array(), $GLOBALS['vms_schema_query_calls'], 'Schema and check-in descriptor boundaries must not call a WordPress query API.');
vms_schema_same(array(), $GLOBALS['wpdb']->calls, 'Schema and check-in descriptor boundaries must not call direct wpdb methods.');

$exact_rule = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_key';
$vendor_reason = 'phpcs:ignore ' . $exact_rule . ' -- Vendor-schema result descriptor only; no query is executed here.';
$checkin_reason = 'phpcs:ignore ' . $exact_rule . ' -- Check-in result descriptor only; no query is executed here.';
$schema_annotation_lines = vms_schema_annotation_lines($schema_source);
$checkin_annotation_lines = vms_schema_annotation_lines($checkin_source);
vms_schema_same(28, count($schema_annotation_lines), 'Each of the 28 vendor meta descriptors should carry one exact line-local annotation.');
vms_schema_same(1, count($checkin_annotation_lines), 'The returned check-in meta_key descriptor should carry one exact line-local annotation.');
foreach ($schema_annotation_lines as $line) {
	vms_schema_assert(strpos($line, "'meta_key'") !== false, 'Vendor annotation must stay inline on its exact meta_key descriptor.');
	vms_schema_assert(strpos($line, $vendor_reason) !== false, 'Vendor annotation must use the exact rule and bounded descriptor rationale.');
}
vms_schema_assert(strpos($checkin_annotation_lines[0], "\$resolved['meta_key'] = \$meta_key;") !== false, 'Check-in annotation must stay inline on the returned descriptor assignment.');
vms_schema_assert(strpos($checkin_annotation_lines[0], $checkin_reason) !== false, 'Check-in annotation must use the exact rule and bounded descriptor rationale.');
vms_schema_same(29, substr_count($schema_source . $checkin_source, $exact_rule), 'Owned runtime scope should contain exactly 29 exact scanner annotations.');

preg_match_all('/^\s*[\x27]meta_key[\x27]\s*=>/m', $schema_source, $schema_descriptor_matches);
vms_schema_same(28, count($schema_descriptor_matches[0]), 'All and only 28 vendor meta_key descriptor rows should be annotated.');
preg_match_all('/phpcs:(?:disable|ignoreFile)\b/i', $schema_source . $checkin_source, $broad_suppressions);
vms_schema_same(0, count($broad_suppressions[0]), 'Descriptor remediation must not use file-wide or block-wide suppression.');
foreach (array_merge($schema_annotation_lines, $checkin_annotation_lines) as $line) {
	vms_schema_same(1, substr_count($line, 'phpcs:ignore'), 'Every owned occurrence should use one line-local PHPCS annotation.');
	vms_schema_same(1, substr_count($line, $exact_rule), 'Every owned occurrence should name only the exact slow-meta-key rule.');
	vms_schema_assert(strpos($line, ',WordPress.') === false, 'Owned annotations must not suppress multiple rules.');
	vms_schema_assert(preg_match('/\b(?:WP_Query|get_posts|get_users|query_posts|wpdb)\b/', $line) !== 1, 'Annotated descriptor lines must not execute a query API.');
}

fwrite(STDOUT, "vendor schema descriptor slow-query remediation: PASS\n");
