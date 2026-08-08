<?php
declare(strict_types=1);

function vms_test_fail(string $message): void
{
	throw new RuntimeException($message);
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	vms_test_fail($message);
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected === $actual) {
		return;
	}

	vms_test_fail(
		$message
		. "\nExpected: " . var_export($expected, true)
		. "\nActual: " . var_export($actual, true)
	);
}

function vms_test_read_file(string $path): string
{
	$contents = file_get_contents($path);
	if (!is_string($contents) || $contents === '') {
		vms_test_fail('Failed to read source file: ' . $path);
	}

	return $contents;
}

function vms_test_extract_function(string $source, string $name): string
{
	$pattern = '~function\s+' . preg_quote($name, '~') . '\s*\(~';
	if (!preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
		vms_test_fail('Unable to locate function ' . $name . '.');
	}

	$start = (int) $matches[0][1];
	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		vms_test_fail('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		if ($char === '{') {
			$depth++;
			continue;
		}
		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	vms_test_fail('Unable to locate closing brace for ' . $name . '.');
}

function vms_test_project_g16_monitor_logging(string $source): string
{
	$start = strpos($source, 'function vms_ticket_integrity_fatal_operation(');
	$last = vms_test_extract_function($source, 'vms_ticket_integrity_fatal_operational_context');
	$last_start = strpos($source, $last, (int) $start);
	vms_test_assert_true($start !== false && $last_start !== false, 'G16 monitor helper bounds changed.');
	$block = substr($source, (int) $start, (int) $last_start - (int) $start + strlen($last));
	vms_test_assert_same('136b427e6633803250e472bc8416a419dd19f3160906b5b049dd169312c146f6', hash('sha256', $block), 'G16 monitor helper block changed.');
	$source = str_replace($block . "\n\n", '', $source, $count);
	vms_test_assert_same(1, $count, 'G16 monitor helper removal changed.');
	$current = vms_test_extract_function($source, 'vms_ticket_integrity_fatal_guard_shutdown');
	vms_test_assert_same('3080ee643e6b24b893d7d212b6ea001c5d2bc95940e45522f7064e2470e94f8f', hash('sha256', $current), 'G16 monitor shutdown changed.');
	$fixture = vms_test_read_file(__DIR__ . '/g16-operational-logging-group-c.php');
	vms_test_assert_same(1, preg_match('/\$g16c_ticket_shutdown_historical = \'([^\']+)\'/s', $fixture, $match), 'G16 historical shutdown fixture changed.');
	$historical = base64_decode($match[1], true);
	vms_test_assert_true(is_string($historical) && $historical !== '', 'G16 historical shutdown decode failed.');
	$source = str_replace($current, $historical, $source, $count);
	vms_test_assert_same(1, $count, 'G16 shutdown reverse count changed.');
	return $source;
}

function vms_test_project_ticket_boundary_g16_c_companion(string $source, string $label): array
{
	$helper_function = vms_test_extract_function($source, 'vms_test_project_g16_monitor_logging');
	$helper_fragment = "\n" . $helper_function . "\n";
	vms_test_assert_same(1, substr_count($source, $helper_fragment), $label . ' G16-C monitor helper count changed.');
	$helper_source = str_replace($helper_fragment, '', $source, $helper_count);
	vms_test_assert_same(1, $helper_count, $label . ' G16-C monitor helper removal changed.');
	$specs = array(array(
		'current' => '$live_monitor_projection = vms_test_project_g16_monitor_logging($live_monitor_source);',
		'historical' => '$live_monitor_projection = $live_monitor_source;',
		'units' => 1,
	));
	$projection = vms_test_project_known_fragments($helper_source, $specs, $label . ' G16-C monitor assertion');
	return array('source' => $projection['source'], 'regions' => 1 + $projection['units']);
}

function vms_test_count_pattern(string $pattern, string $contents): int
{
	$count = preg_match_all($pattern, $contents);
	if ($count === false) {
		vms_test_fail('Failed counting pattern: ' . $pattern);
	}

	return $count;
}

function vms_test_sha256(string $path): string
{
	$hash = hash_file('sha256', $path);
	if (!is_string($hash) || $hash === '') {
		vms_test_fail('Failed hashing file: ' . $path);
	}

	return $hash;
}

/** @param array<int,array{current:string,historical:string,units:int}> $specs */
function vms_test_project_known_fragments(string $source, array $specs, string $label): array
{
	$units = 0;
	foreach ($specs as $index => $spec) {
		vms_test_assert_same(1, substr_count($source, $spec['current']), $label . ' fragment count changed at index ' . $index . '.');
		$count = 0;
		$source = str_replace($spec['current'], $spec['historical'], $source, $count);
		vms_test_assert_same(1, $count, $label . ' must project each known fragment once.');
		$units += $spec['units'];
	}
	return array('source' => $source, 'units' => $units);
}

function vms_test_strip_known_region(string $source, string $start_marker, string $end_marker, string $label): array
{
	vms_test_assert_same(1, substr_count($source, $start_marker), $label . ' start-marker count changed.');
	vms_test_assert_same(1, substr_count($source, $end_marker), $label . ' end-marker count changed.');
	$start = strpos($source, $start_marker);
	$end = strpos($source, $end_marker, $start === false ? 0 : $start);
	if ($start === false || $end === false) {
		vms_test_fail('Unable to project known region: ' . $label);
	}
	$end += strlen($end_marker);
	return array('source' => substr($source, 0, $start) . substr($source, $end), 'regions' => 1);
}

function vms_test_project_ticket_boundary_g16_companion(string $source, string $label): array
{
	$helper_function = vms_test_extract_function($source, 'vms_test_project_g16_vendor_logging');
	$helper_fragment = "\n" . $helper_function . "\n";
	vms_test_assert_same(1, substr_count($source, $helper_fragment), $label . ' G16-B vendor helper count changed.');
	$helper_source = str_replace($helper_fragment, '', $source, $helper_count);
	vms_test_assert_same(1, $helper_count, $label . ' G16-B vendor helper removal changed.');
	$startMarker = '$vendor_type_projection = vms_test_project_g16_vendor_logging($vendor_type_source);';
	$endMarker = "vms_test_assert_true(\$vendor_mutation_rejected, 'Vendor projection must reject a mutated owned logging context.');\n";
	vms_test_assert_same(1, substr_count($helper_source, $startMarker), $label . ' G16 assertion start changed.');
	vms_test_assert_same(1, substr_count($helper_source, $endMarker), $label . ' G16 assertion end changed.');
	$start = strpos($helper_source, $startMarker);
	$end = strpos($helper_source, $endMarker, (int) $start);
	vms_test_assert_true($start !== false && $end !== false, $label . ' G16 assertion region must be present.');
	$end += strlen($endMarker);
	$historicalAssertion = "vms_test_assert_same(\n"
		. "\t'ac036bef295173d9d26b7165871a09797de2a61add12247ee985a547f3f74b4e',\n"
		. "\thash_file('sha256', \$vendor_type_path),\n"
		. "\t'Vendor-type source should remain unchanged in this child.'\n"
		. ");\n";
	$projected = substr($helper_source, 0, (int) $start)
		. $historicalAssertion
		. substr($helper_source, $end);
	return array(
		'source' => $projected,
		'regions' => 2,
	);
}

/**
 * @param array<int,array<string,mixed>> $calls
 * @return array<int,int>
 */
function vms_test_object_term_call_ids(array $calls): array
{
	$ids = array();
	foreach ($calls as $call) {
		$ids[] = (int) ($call['object_id'] ?? 0);
	}

	sort($ids);
	return $ids;
}

function vms_test_make_term(int $term_id, string $slug, string $name): WP_Term
{
	$term = new WP_Term();
	$term->term_id = $term_id;
	$term->slug = $slug;
	$term->name = $name;
	return $term;
}

function vms_test_reset_runtime_state(): void
{
	$GLOBALS['vms_test_taxonomy_exists'] = true;
	$GLOBALS['vms_test_options'] = array();
	$GLOBALS['vms_test_terms'] = array();
	$GLOBALS['vms_test_term_meta'] = array();
	$GLOBALS['vms_test_object_ids_in_term'] = array();
	$GLOBALS['vms_test_post_meta'] = array();
	$GLOBALS['vms_test_get_posts_args'] = array();
	$GLOBALS['vms_test_get_posts_return'] = array();
	$GLOBALS['vms_test_set_object_terms_calls'] = array();
	$GLOBALS['vms_test_remove_object_terms_calls'] = array();
	$GLOBALS['vms_test_update_term_meta_calls'] = array();
	$GLOBALS['vms_test_update_post_meta_calls'] = array();
	$GLOBALS['vms_test_delete_term_calls'] = array();
	$GLOBALS['vms_test_update_option_calls'] = array();
	$GLOBALS['vms_test_insert_term_calls'] = array();
	$GLOBALS['vms_test_update_term_calls'] = array();
}

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!class_exists('WP_Term')) {
	class WP_Term
	{
		/** @var int */
		public $term_id = 0;

		/** @var string */
		public $slug = '';

		/** @var string */
		public $name = '';
	}
}

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		unset($domain);
		return $text;
	}
}

if (!function_exists('apply_filters')) {
	function apply_filters(string $tag, $value)
	{
		unset($tag);
		return $value;
	}
}

if (!function_exists('sanitize_title')) {
	function sanitize_title(string $title): string
	{
		$title = strtolower(trim($title));
		$title = preg_replace('/[^a-z0-9_\-\s]+/', '', $title);
		$title = preg_replace('/[\s\-]+/', '-', (string) $title);
		return trim((string) $title, '-');
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($key): string
	{
		$key = strtolower((string) $key);
		$key = preg_replace('/[^a-z0-9_\-]/', '', $key);
		return is_string($key) ? $key : '';
	}
}

if (!function_exists('wp_strip_all_tags')) {
	function wp_strip_all_tags(string $text): string
	{
		return trim(strip_tags($text));
	}
}

if (!function_exists('taxonomy_exists')) {
	function taxonomy_exists(string $taxonomy): bool
	{
		return $taxonomy === 'vms_vendor_type' && !empty($GLOBALS['vms_test_taxonomy_exists']);
	}
}

if (!function_exists('get_option')) {
	function get_option(string $option, $default = false)
	{
		return $GLOBALS['vms_test_options'][$option] ?? $default;
	}
}

if (!function_exists('update_option')) {
	function update_option(string $option, $value, bool $autoload = true): bool
	{
		$GLOBALS['vms_test_options'][$option] = $value;
		$GLOBALS['vms_test_update_option_calls'][] = array(
			'option' => $option,
			'value' => $value,
			'autoload' => $autoload,
		);
		return true;
	}
}

if (!function_exists('get_term_by')) {
	function get_term_by(string $field, string $value, string $taxonomy)
	{
		if ($taxonomy !== 'vms_vendor_type') {
			return false;
		}

		foreach ($GLOBALS['vms_test_terms'] as $term) {
			if (!$term instanceof WP_Term) {
				continue;
			}

			if ($field === 'slug' && (string) $term->slug === $value) {
				return $term;
			}

			if ($field === 'name' && (string) $term->name === $value) {
				return $term;
			}
		}

		return false;
	}
}

if (!function_exists('wp_insert_term')) {
	function wp_insert_term(string $term, string $taxonomy, array $args = array())
	{
		$new_id = count($GLOBALS['vms_test_terms']) + 100;
		$slug = (string) ($args['slug'] ?? sanitize_key($term));
		$GLOBALS['vms_test_terms'][$new_id] = vms_test_make_term($new_id, $slug, $term);
		$GLOBALS['vms_test_insert_term_calls'][] = array(
			'term' => $term,
			'taxonomy' => $taxonomy,
			'args' => $args,
		);
		return array('term_id' => $new_id);
	}
}

if (!function_exists('get_term')) {
	function get_term(int $term_id, string $taxonomy)
	{
		if ($taxonomy !== 'vms_vendor_type') {
			return false;
		}

		return $GLOBALS['vms_test_terms'][$term_id] ?? false;
	}
}

if (!function_exists('wp_update_term')) {
	function wp_update_term(int $term_id, string $taxonomy, array $args = array())
	{
		if ($taxonomy === 'vms_vendor_type' && isset($GLOBALS['vms_test_terms'][$term_id]) && $GLOBALS['vms_test_terms'][$term_id] instanceof WP_Term) {
			if (isset($args['name'])) {
				$GLOBALS['vms_test_terms'][$term_id]->name = (string) $args['name'];
			}
		}

		$GLOBALS['vms_test_update_term_calls'][] = array(
			'term_id' => $term_id,
			'taxonomy' => $taxonomy,
			'args' => $args,
		);

		return array('term_id' => $term_id);
	}
}

if (!function_exists('get_terms')) {
	function get_terms(array $args = array())
	{
		unset($args);
		return array_values($GLOBALS['vms_test_terms']);
	}
}

if (!function_exists('get_objects_in_term')) {
	function get_objects_in_term(int $term_id, string $taxonomy)
	{
		if ($taxonomy !== 'vms_vendor_type') {
			return array();
		}

		return $GLOBALS['vms_test_object_ids_in_term'][$term_id] ?? array();
	}
}

if (!function_exists('wp_set_object_terms')) {
	function wp_set_object_terms(int $object_id, array $terms, string $taxonomy, bool $append = false)
	{
		$GLOBALS['vms_test_set_object_terms_calls'][] = array(
			'object_id' => $object_id,
			'terms' => $terms,
			'taxonomy' => $taxonomy,
			'append' => $append,
		);

		return $terms;
	}
}

if (!function_exists('wp_remove_object_terms')) {
	function wp_remove_object_terms(int $object_id, array $terms, string $taxonomy)
	{
		$GLOBALS['vms_test_remove_object_terms_calls'][] = array(
			'object_id' => $object_id,
			'terms' => $terms,
			'taxonomy' => $taxonomy,
		);

		return true;
	}
}

if (!function_exists('get_term_meta')) {
	function get_term_meta(int $term_id, string $meta_key, bool $single = true)
	{
		unset($single);
		return $GLOBALS['vms_test_term_meta'][$term_id][$meta_key] ?? '';
	}
}

if (!function_exists('update_term_meta')) {
	function update_term_meta(int $term_id, string $meta_key, $meta_value): bool
	{
		$GLOBALS['vms_test_term_meta'][$term_id][$meta_key] = $meta_value;
		$GLOBALS['vms_test_update_term_meta_calls'][] = array(
			'term_id' => $term_id,
			'meta_key' => $meta_key,
			'meta_value' => $meta_value,
		);
		return true;
	}
}

if (!function_exists('wp_delete_term')) {
	function wp_delete_term(int $term_id, string $taxonomy)
	{
		unset($GLOBALS['vms_test_terms'][$term_id]);
		$GLOBALS['vms_test_delete_term_calls'][] = array(
			'term_id' => $term_id,
			'taxonomy' => $taxonomy,
		);
		return true;
	}
}

if (!function_exists('is_wp_error')) {
	function is_wp_error($thing): bool
	{
		return false;
	}
}

if (!function_exists('vms_meta_key')) {
	function vms_meta_key(string $group, string $field): string
	{
		if ($group === 'event_plan' && $field === 'secondary_vendor_type') {
			return '_vms_secondary_vendor_type';
		}

		return '';
	}
}

if (!function_exists('get_posts')) {
	function get_posts(array $args = array()): array
	{
		$GLOBALS['vms_test_get_posts_args'][] = $args;
		return $GLOBALS['vms_test_get_posts_return'];
	}
}

if (!function_exists('get_post_meta')) {
	function get_post_meta(int $post_id, string $meta_key, bool $single = true)
	{
		unset($single);
		return $GLOBALS['vms_test_post_meta'][$post_id][$meta_key] ?? '';
	}
}

if (!function_exists('update_post_meta')) {
	function update_post_meta(int $post_id, string $meta_key, $meta_value): bool
	{
		$GLOBALS['vms_test_post_meta'][$post_id][$meta_key] = $meta_value;
		$GLOBALS['vms_test_update_post_meta_calls'][] = array(
			'post_id' => $post_id,
			'meta_key' => $meta_key,
			'meta_value' => $meta_value,
		);
		return true;
	}
}

if (!function_exists('absint')) {
	function absint($value): int
	{
		return abs((int) $value);
	}
}

$vendor_type_path = dirname(__DIR__) . '/includes/taxonomies/vendor-type.php';
$live_vendor_type_path = dirname(__DIR__, 3) . '/vms/includes/taxonomies/vendor-type.php';
$ticket_integrity_path = dirname(__DIR__) . '/includes/ticketing/ticket-integrity-monitor.php';
$ticket_integrity_test_path = __DIR__ . '/ticket-integrity-query-filter-boundary-remediation.php';

$source = vms_test_read_file($vendor_type_path);
$ticket_integrity_source = vms_test_read_file($ticket_integrity_path);
$ticket_integrity_test_source = vms_test_read_file($ticket_integrity_test_path);

$suppression = "// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- get_posts() already defaults suppress_filters to true; keep the explicit value to document this one-time canonical vendor-type migration across all event plans when normalizing legacy secondary vendor type meta.";
vms_test_assert_true(strpos($source, $suppression) !== false, 'The vendor-type suppress_filters suppression is missing or changed.');
vms_test_assert_same(
	1,
	vms_test_count_pattern("/'suppress_filters'\\s*=>\\s*true/", $source),
	'vendor-type.php should contain exactly one explicit suppress_filters=true assignment.'
);
vms_test_assert_same(
	1,
	substr_count($source, 'WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters'),
	'vendor-type.php should contain exactly one suppress_filters ignore token.'
);
vms_test_assert_true(
	strpos($source, "add_action('init', 'vms_vendor_type_maybe_canonicalize_terms', 22);") !== false,
	'The canonicalization hook registration must remain on init priority 22.'
);

$vendor_logging_projection_specs = array(
	array(
		'current' => "\t\t\t\tvms_record_operational_issue(\n"
			. "\t\t\t\t\t'vendor_type_default_term_ensure_failed',\n"
			. "\t\t\t\t\tarray(\n"
			. "\t\t\t\t\t\t'service' => 'vendor_taxonomy',\n"
			. "\t\t\t\t\t\t'entity_type' => 'vendor_type',\n"
			. "\t\t\t\t\t\t'operation' => 'ensure_default',\n"
			. "\t\t\t\t\t\t'stage' => 'term_insert',\n"
			. "\t\t\t\t\t\t'status' => 'failed',\n"
			. "\t\t\t\t\t),\n"
			. "\t\t\t\t\t\$created\n"
			. "\t\t\t\t);",
		'historical' => " \t\t\t\terror_log('[VMS] vendor-type: failed to ensure default term ' . \$slug . ' (' . \$created->get_error_message() . ')');",
		'units' => 1,
	),
	array(
		'current' => "\t\t\t\t\tvms_record_operational_issue(\n"
			. "\t\t\t\t\t\t'vendor_type_duplicate_term_delete_failed',\n"
			. "\t\t\t\t\t\tarray(\n"
			. "\t\t\t\t\t\t\t'service' => 'vendor_taxonomy',\n"
			. "\t\t\t\t\t\t\t'entity_type' => 'vendor_type',\n"
			. "\t\t\t\t\t\t\t'operation' => 'delete_duplicate',\n"
			. "\t\t\t\t\t\t\t'stage' => 'canonicalization',\n"
			. "\t\t\t\t\t\t\t'status' => 'failed',\n"
			. "\t\t\t\t\t\t\t'entity_id' => (int) \$term->term_id,\n"
			. "\t\t\t\t\t\t),\n"
			. "\t\t\t\t\t\t\$deleted\n"
			. "\t\t\t\t\t);",
		'historical' => " \t\t\t\t\terror_log('[VMS] vendor-type: failed deleting duplicate term #' . (int) \$term->term_id . ' (' . \$deleted->get_error_message() . ')');",
		'units' => 1,
	),
);
$live_vendor_source = vms_test_read_file($live_vendor_type_path);
$live_vendor_projection = vms_test_project_known_fragments($live_vendor_source, $vendor_logging_projection_specs, 'Live vendor G16 group-B');
vms_test_assert_same(2, $live_vendor_projection['units'], 'Live vendor projection must strip exactly two G16 group-B calls.');
vms_test_assert_same(
	'4ae832840023a8cd2d4c9a805e839927b003f71b29e0efa61bc1415944ff8c87',
	hash('sha256', $live_vendor_projection['source']),
	'The live vendor-type semantic baseline changed outside G16 group B.'
);
$mutated_live_vendor = str_replace("'stage' => 'term_insert'", "'stage' => 'unexpected_stage'", $live_vendor_source, $vendor_mutation_count);
vms_test_assert_same(1, $vendor_mutation_count, 'Vendor projection mutation control must alter one owned stage.');
$vendor_mutation_rejected = false;
try {
	vms_test_project_known_fragments($mutated_live_vendor, $vendor_logging_projection_specs, 'Mutated live vendor G16 group-B');
} catch (RuntimeException $exception) {
	$vendor_mutation_rejected = true;
}
vms_test_assert_true($vendor_mutation_rejected, 'Vendor projection must reject a mutated owned logging context.');
$monitor_projection_specs = array(
	array(
		'current' => ' // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Ticket Integrity intentionally orders each published Event Plan batch by canonical event-date metadata across the configured date window.',
		'historical' => '',
		'units' => 1,
	),
	array(
		'current' => ' // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Ticket Integrity intentionally paginates the complete published, linked Event Plan set inside the configured date window before applying ticketing and activity checks.',
		'historical' => '',
		'units' => 1,
	),
	array(
		'current' => "\treturn wp_date('Y-m-d g:i a', \$timestamp, wp_timezone());",
		'historical' => "\tif (function_exists('wp_date')) {\n\t\treturn wp_date('Y-m-d g:i a', \$timestamp, wp_timezone());\n\t}\n\n\treturn date('Y-m-d g:i a', \$timestamp);",
		'units' => 1,
	),
	array(
		'current' => "\t\$tz = wp_timezone();\n\t\$start_date = wp_date('Y-m-d', \$now, \$tz);\n\t\$end_date = wp_date('Y-m-d', \$cutoff, \$tz);",
		'historical' => "\t\$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');\n\t\$start_date = function_exists('wp_date') ? wp_date('Y-m-d', \$now, \$tz) : date('Y-m-d', \$now);\n\t\$end_date = function_exists('wp_date') ? wp_date('Y-m-d', \$cutoff, \$tz) : date('Y-m-d', \$cutoff);",
		'units' => 2,
	),
);
$monitor_pre_g16 = vms_test_project_g16_monitor_logging($ticket_integrity_source);
$monitor_projection = vms_test_project_known_fragments($monitor_pre_g16, $monitor_projection_specs, 'Ticket Integrity monitor G10+G15');
vms_test_assert_same(5, $monitor_projection['units'], 'Monitor projection must strip exactly two G10 and three G15 owned rows.');
vms_test_assert_same(
	'27770ef0be288290a7f7d5e5e7a92ee27e93f79e55d9f95d29637671415dcdfc',
	hash('sha256', $monitor_projection['source']),
	'Ticket Integrity monitor changed outside known G10+G15 ownership.'
);
$mutated_monitor = str_replace("'post_status' => 'publish'", "'post_status' => 'draft'", $ticket_integrity_source, $mutation_count);
vms_test_assert_same(1, $mutation_count, 'Monitor negative control must mutate one non-owned query argument.');
$mutated_monitor_pre_g16 = vms_test_project_g16_monitor_logging($mutated_monitor);
$mutated_monitor_projection = vms_test_project_known_fragments($mutated_monitor_pre_g16, $monitor_projection_specs, 'mutated Ticket Integrity monitor');
vms_test_assert_true(
	hash('sha256', $mutated_monitor_projection['source']) !== '27770ef0be288290a7f7d5e5e7a92ee27e93f79e55d9f95d29637671415dcdfc',
	'Monitor semantic projection must reject a non-owned runtime mutation.'
);

$boundary_projection = vms_test_project_ticket_boundary_g16_c_companion($ticket_integrity_test_source, 'Ticket Integrity boundary');
vms_test_assert_same(2, $boundary_projection['regions'], 'Boundary projection must strip the exact G16-C helper and assertion changes.');
$boundary_projection = vms_test_project_ticket_boundary_g16_companion($boundary_projection['source'], 'Ticket Integrity boundary');
vms_test_assert_same(2, $boundary_projection['regions'], 'Boundary projection must strip the exact G16-B helper and assertion regions.');
$boundary_projection = vms_test_strip_known_region(
	$boundary_projection['source'],
	'$live_monitor_g15_projection_rows = 0;',
	"vms_test_assert_same(3, \$live_monitor_g15_projection_rows, 'Live monitor projection must reverse exactly three G15 date rows.');\n",
	'Ticket Integrity boundary G15 projection block'
);
vms_test_assert_same(1, $boundary_projection['regions'], 'Boundary projection must strip one exact G15 block covering three date rows.');
$boundary_projection = vms_test_strip_known_region(
	$boundary_projection['source'],
	"\n\$live_monitor_projection = \$live_monitor_source;",
	"vms_test_assert_same(\n\t2,\n\t\$live_monitor_projection_removals,\n\t'Live Ticket Integrity monitor projection should strip exactly the two authorized G10 query annotations.'\n);\n",
	'Ticket Integrity boundary G10 projection block'
);
$boundary_g10_specs = array(
	array(
		'current' => "\$live_monitor_source = vms_test_read_file(\$live_monitor_path);\n",
		'historical' => '',
		'units' => 1,
	),
	array(
		'current' => "\thash('sha256', \$live_monitor_projection),\n\t'Live Ticket Integrity monitor must retain its semantic baseline after projecting G10 annotations and G15 date calls.'",
		'historical' => "\thash_file('sha256', \$live_monitor_path),\n\t'Live Ticket Integrity monitor must remain unchanged in this mirror-only child.'",
		'units' => 1,
	),
);
$boundary_projection = vms_test_project_known_fragments($boundary_projection['source'], $boundary_g10_specs, 'Ticket Integrity boundary G10 companions');
vms_test_assert_same(2, $boundary_projection['units'], 'Boundary projection must strip the two remaining exact G10 companion changes.');
vms_test_assert_same(
	'a8572971dbfee9d6b10c52fb379e14ad32e8b619ce79fcbff9eef0ecb9155714',
	hash('sha256', $boundary_projection['source']),
	'Ticket Integrity boundary test changed outside known G10+G15 companion ownership.'
);
$mutated_boundary = str_replace(
	'Ticket Integrity target query should stay scoped to Event Plans.',
	'MUTATED target scope assertion.',
	$ticket_integrity_test_source,
	$boundary_mutation_count
);
vms_test_assert_same(1, $boundary_mutation_count, 'Boundary negative control must mutate one non-owned assertion.');
$mutated_boundary = vms_test_project_ticket_boundary_g16_c_companion($mutated_boundary, 'mutated Ticket Integrity boundary');
vms_test_assert_same(2, $mutated_boundary['regions'], 'Mutated boundary projection must strip the exact G16-C helper and assertion changes.');
$mutated_boundary = vms_test_project_ticket_boundary_g16_companion($mutated_boundary['source'], 'mutated Ticket Integrity boundary');
vms_test_assert_same(2, $mutated_boundary['regions'], 'Mutated boundary projection must strip the exact G16-B helper and assertion regions.');
$mutated_boundary = vms_test_strip_known_region(
	$mutated_boundary['source'],
	'$live_monitor_g15_projection_rows = 0;',
	"vms_test_assert_same(3, \$live_monitor_g15_projection_rows, 'Live monitor projection must reverse exactly three G15 date rows.');\n",
	'mutated Ticket Integrity boundary G15 block'
);
$mutated_boundary = vms_test_strip_known_region(
	$mutated_boundary['source'],
	"\n\$live_monitor_projection = \$live_monitor_source;",
	"vms_test_assert_same(\n\t2,\n\t\$live_monitor_projection_removals,\n\t'Live Ticket Integrity monitor projection should strip exactly the two authorized G10 query annotations.'\n);\n",
	'mutated Ticket Integrity boundary G10 block'
);
$mutated_boundary = vms_test_project_known_fragments($mutated_boundary['source'], $boundary_g10_specs, 'mutated Ticket Integrity boundary companions');
vms_test_assert_true(
	hash('sha256', $mutated_boundary['source']) !== 'a8572971dbfee9d6b10c52fb379e14ad32e8b619ce79fcbff9eef0ecb9155714',
	'Boundary semantic projection must reject a non-owned assertion mutation.'
);

eval(vms_test_extract_function($source, 'vms_vendor_type_registry'));
eval(vms_test_extract_function($source, 'vms_vendor_type_alias_map'));
eval(vms_test_extract_function($source, 'vms_vendor_type_normalize_slug'));
eval(vms_test_extract_function($source, 'vms_vendor_type_select_options'));
eval(vms_test_extract_function($source, 'vms_vendor_type_canonical_slug_for_term'));
eval(vms_test_extract_function($source, 'vms_vendor_type_maybe_canonicalize_terms'));

vms_test_reset_runtime_state();

$GLOBALS['vms_test_terms'][11] = vms_test_make_term(11, 'band', 'Music Vendor');
$GLOBALS['vms_test_terms'][12] = vms_test_make_term(12, 'bands', 'Bands');
$GLOBALS['vms_test_terms'][13] = vms_test_make_term(13, 'food_truck', 'Food Vendor');
$GLOBALS['vms_test_term_meta'][11]['_vms_vendor_type_category_label'] = '';
$GLOBALS['vms_test_term_meta'][12]['_vms_vendor_type_category_label'] = 'Stage Vendor';
$GLOBALS['vms_test_object_ids_in_term'][12] = array(501, 0, 502, 501);
$GLOBALS['vms_test_get_posts_return'] = array(701, 702, 703);
$GLOBALS['vms_test_post_meta'][701]['_vms_secondary_vendor_type'] = 'bands';
$GLOBALS['vms_test_post_meta'][702]['_vms_secondary_vendor_type'] = 'food-truck';
$GLOBALS['vms_test_post_meta'][703]['_vms_secondary_vendor_type'] = '';

vms_vendor_type_maybe_canonicalize_terms();

$expected_query_args = array(
	'post_type' => 'vms_event_plan',
	'post_status' => 'any',
	'numberposts' => -1,
	'fields' => 'ids',
	'no_found_rows' => true,
	'suppress_filters' => true,
);
vms_test_assert_same(
	array($expected_query_args),
	$GLOBALS['vms_test_get_posts_args'],
	'The canonicalization query args changed unexpectedly.'
);
vms_test_assert_same(
	array(501, 502),
	vms_test_object_term_call_ids($GLOBALS['vms_test_set_object_terms_calls']),
	'Alias-term vendor assignments should be migrated onto the canonical term.'
);
vms_test_assert_same(
	array(501, 502),
	vms_test_object_term_call_ids($GLOBALS['vms_test_remove_object_terms_calls']),
	'Alias-term vendor removals should mirror the migrated assignments.'
);
vms_test_assert_same(
	array(
		array(
			'term_id' => 11,
			'meta_key' => '_vms_vendor_type_category_label',
			'meta_value' => 'Stage Vendor',
		),
	),
	$GLOBALS['vms_test_update_term_meta_calls'],
	'Alias-term category-label meta should be copied to the canonical term when needed.'
);
vms_test_assert_same(
	array(
		array(
			'post_id' => 701,
			'meta_key' => '_vms_secondary_vendor_type',
			'meta_value' => 'band',
		),
		array(
			'post_id' => 702,
			'meta_key' => '_vms_secondary_vendor_type',
			'meta_value' => 'food_truck',
		),
	),
	$GLOBALS['vms_test_update_post_meta_calls'],
	'Legacy secondary vendor type meta should normalize to canonical slugs only when needed.'
);
vms_test_assert_same(
	array(
		array(
			'option' => 'vms_vendor_type_canonicalized_v1',
			'value' => '1',
			'autoload' => false,
		),
	),
	$GLOBALS['vms_test_update_option_calls'],
	'The one-shot canonicalization option should be marked complete.'
);
vms_test_assert_same(
	array(
		array(
			'term_id' => 12,
			'taxonomy' => 'vms_vendor_type',
		),
	),
	$GLOBALS['vms_test_delete_term_calls'],
	'Only the duplicate alias term should be deleted.'
);

$initial_query_count = count($GLOBALS['vms_test_get_posts_args']);
vms_vendor_type_maybe_canonicalize_terms();
vms_test_assert_same(
	$initial_query_count,
	count($GLOBALS['vms_test_get_posts_args']),
	'The one-shot option guard should prevent a second canonicalization query.'
);

echo "Vendor-type query filter boundary remediation test passed.\n";
