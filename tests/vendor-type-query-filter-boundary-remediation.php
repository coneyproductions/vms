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

vms_test_assert_same(
	'4ae832840023a8cd2d4c9a805e839927b003f71b29e0efa61bc1415944ff8c87',
	vms_test_sha256($live_vendor_type_path),
	'The live vendor-type file changed unexpectedly.'
);
vms_test_assert_same(
	'27770ef0be288290a7f7d5e5e7a92ee27e93f79e55d9f95d29637671415dcdfc',
	vms_test_sha256($ticket_integrity_path),
	'The Ticket Integrity monitor changed unexpectedly.'
);
vms_test_assert_same(
	'0e630063e869cd6ce6816a4c5cfb4710a9bf29e90d1c37f5b9fd5bffeb50beac',
	vms_test_sha256($ticket_integrity_test_path),
	'The Ticket Integrity boundary test changed unexpectedly.'
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
