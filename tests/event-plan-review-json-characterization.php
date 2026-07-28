<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

chdir(dirname(__DIR__));

const VMS_TEST_PLAN_ID = 123;
const VMS_TEST_CURRENT_USER_ID = 7;
const VMS_TEST_CURRENT_TIME = '2026-07-23 14:15:16';
const VMS_TEST_SNAPSHOT_AT = '2026-07-21 08:09:10';
const VMS_TEST_CHANGES_AT = '2026-07-22 10:00:00';
const VMS_TEST_POST_MODIFIED_GMT = '2026-07-22 09:00:00';

final class WP_Post
{
	public int $ID = 0;
	public string $post_type = 'vms_event_plan';
}

final class WP_User
{
	public string $display_name = '';

	public function __construct(string $display_name = '')
	{
		$this->display_name = $display_name;
	}
}

final class WP_Term
{
	public string $name = '';

	public function __construct(string $name = '')
	{
		$this->name = $name;
	}
}

$GLOBALS['vms_test_meta'] = array();
$GLOBALS['vms_test_updates'] = array();
$GLOBALS['vms_test_deletes'] = array();
$GLOBALS['vms_test_post_titles'] = array();
$GLOBALS['vms_test_post_types'] = array();
$GLOBALS['vms_test_post_fields'] = array();
$GLOBALS['vms_test_lineup_rows'] = array();
$GLOBALS['vms_test_users'] = array(
	VMS_TEST_CURRENT_USER_ID => new WP_User('Editor User'),
);
$GLOBALS['vms_test_terms'] = array(
	'food_truck' => new WP_Term('Food Truck'),
	'merch' => new WP_Term('Merch'),
	'photo' => new WP_Term('Photo'),
);
$GLOBALS['vms_test_current_user_id'] = VMS_TEST_CURRENT_USER_ID;
$GLOBALS['vms_test_current_time'] = VMS_TEST_CURRENT_TIME;

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function _n(string $single, string $plural, int $number, string $domain = ''): string
{
	unset($domain);
	return $number === 1 ? $single : $plural;
}

function esc_html__($text, string $domain = ''): string
{
	unset($domain);
	return is_scalar($text) ? (string) $text : '';
}

function esc_html($text): string
{
	return is_scalar($text) ? (string) $text : '';
}

function esc_url_raw($url): string
{
	return is_scalar($url) ? trim((string) $url) : '';
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $value));
}

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$text = trim((string) $value);
	$text = preg_replace('/\s+/', ' ', $text);
	return is_string($text) ? $text : '';
}

function wp_unslash($value)
{
	return $value;
}

function wp_strip_all_tags($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	return strip_tags((string) $value);
}

function wp_json_encode($value)
{
	$json = json_encode($value);
	return is_string($json) ? $json : false;
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function get_bloginfo(string $show): string
{
	unset($show);
	return 'UTF-8';
}

function add_action(...$args): bool
{
	unset($args);
	return true;
}

function current_user_can(string $capability, int $post_id = 0): bool
{
	unset($capability, $post_id);
	return true;
}

function wp_is_post_revision(int $post_id): bool
{
	unset($post_id);
	return false;
}

function get_current_user_id(): int
{
	return (int) ($GLOBALS['vms_test_current_user_id'] ?? 0);
}

function current_time(string $type): string
{
	unset($type);
	return (string) ($GLOBALS['vms_test_current_time'] ?? VMS_TEST_CURRENT_TIME);
}

function get_post_type(int $post_id): string
{
	return (string) ($GLOBALS['vms_test_post_types'][$post_id] ?? '');
}

function get_the_title(int $post_id): string
{
	return (string) ($GLOBALS['vms_test_post_titles'][$post_id] ?? '');
}

function get_post_field(string $field, int $post_id)
{
	return $GLOBALS['vms_test_post_fields'][$post_id][$field] ?? '';
}

function get_post_meta(int $post_id, string $meta_key, bool $single = false)
{
	$has_key = array_key_exists($meta_key, $GLOBALS['vms_test_meta'][$post_id] ?? array());
	if (!$has_key) {
		return $single ? '' : array();
	}

	$value = $GLOBALS['vms_test_meta'][$post_id][$meta_key];
	if ($single) {
		return $value;
	}

	return is_array($value) ? $value : array($value);
}

function update_post_meta(int $post_id, string $meta_key, $value): bool
{
	$GLOBALS['vms_test_meta'][$post_id][$meta_key] = $value;
	$GLOBALS['vms_test_updates'][] = array($post_id, $meta_key, $value);
	return true;
}

function delete_post_meta(int $post_id, string $meta_key): bool
{
	unset($GLOBALS['vms_test_meta'][$post_id][$meta_key]);
	$GLOBALS['vms_test_deletes'][] = array($post_id, $meta_key);
	return true;
}

function get_userdata(int $user_id)
{
	return $GLOBALS['vms_test_users'][$user_id] ?? false;
}

function get_term_by($field, $value, $taxonomy)
{
	unset($field, $taxonomy);
	return $GLOBALS['vms_test_terms'][sanitize_key($value)] ?? false;
}

function vms_event_plan_get_status(int $plan_id, string $context = ''): string
{
	unset($context);
	return sanitize_key((string) get_post_meta($plan_id, '_vms_event_plan_status', true));
}

function vms_get_event_plan_lineup_entries(int $plan_id): array
{
	unset($plan_id);
	return (array) ($GLOBALS['vms_test_lineup_rows'] ?? array());
}

function vms_event_command_center_time_ago_label(string $raw, bool $gmt = false): string
{
	unset($gmt);
	return 'ago:' . $raw;
}

function vms_event_command_center_parse_datetime(string $raw, bool $gmt = false)
{
	unset($gmt);
	try {
		return new DateTimeImmutable($raw, new DateTimeZone('UTC'));
	} catch (Throwable $throwable) {
		unset($throwable);
		return null;
	}
}

function vms_event_command_center_clean_text($text): string
{
	if (function_exists('vms_event_plan_review_clean_text')) {
		return vms_event_plan_review_clean_text(is_scalar($text) ? (string) $text : '');
	}

	return sanitize_text_field($text);
}

function vms_admin_ui_page_url(string $slug): string
{
	return '/wp-admin/admin.php?page=' . rawurlencode($slug);
}

function vms_event_command_center_admin_url(array $args = array()): string
{
	$query = http_build_query($args);
	return '/wp-admin/admin.php?page=vms-event-command-center' . ($query !== '' ? '&' . $query : '');
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
		);
	}
}

function vms_test_array_is_list(array $value): bool
{
	$index = 0;
	foreach (array_keys($value) as $key) {
		if ($key !== $index) {
			return false;
		}
		$index++;
	}

	return true;
}

function vms_test_canonicalize($value)
{
	if (!is_array($value)) {
		return $value;
	}

	if (vms_test_array_is_list($value)) {
		$normalized = array();
		foreach ($value as $item) {
			$normalized[] = vms_test_canonicalize($item);
		}
		return $normalized;
	}

	$normalized = array();
	foreach ($value as $key => $item) {
		$normalized[$key] = vms_test_canonicalize($item);
	}
	ksort($normalized);

	return $normalized;
}

function vms_test_assert_json_equivalent($expected, $actual, string $message): void
{
	$expected_json = wp_json_encode(vms_test_canonicalize($expected));
	$actual_json = wp_json_encode(vms_test_canonicalize($actual));
	vms_test_assert_same($expected_json, $actual_json, $message);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) !== false, $message);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) === false, $message);
}

function vms_test_assert_no_warnings(array $captured, string $message): void
{
	vms_test_assert_same(array(), (array) ($captured['warnings'] ?? array()), $message);
}

function vms_test_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name . '(';
	$start = strpos($source, $needle);
	if ($start === false) {
		throw new RuntimeException('Unable to locate function ' . $name . '.');
	}

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		throw new RuntimeException('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	$inSingleQuote = false;
	$inDoubleQuote = false;
	$inLineComment = false;
	$inBlockComment = false;
	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		$next_char = $i + 1 < $length ? $source[$i + 1] : '';
		$previous_char = $i > 0 ? $source[$i - 1] : '';

		if ($inLineComment) {
			if ($char === "\n") {
				$inLineComment = false;
			}
			continue;
		}
		if ($inBlockComment) {
			if ($char === '*' && $next_char === '/') {
				$inBlockComment = false;
				$i++;
			}
			continue;
		}
		if ($inSingleQuote) {
			if ($char === "'" && $previous_char !== '\\') {
				$inSingleQuote = false;
			}
			continue;
		}
		if ($inDoubleQuote) {
			if ($char === '"' && $previous_char !== '\\') {
				$inDoubleQuote = false;
			}
			continue;
		}

		if ($char === '/' && $next_char === '/') {
			$inLineComment = true;
			$i++;
			continue;
		}
		if ($char === '/' && $next_char === '*') {
			$inBlockComment = true;
			$i++;
			continue;
		}
		if ($char === "'") {
			$inSingleQuote = true;
			continue;
		}
		if ($char === '"') {
			$inDoubleQuote = true;
			continue;
		}

		if ($char === '{') {
			$depth++;
		} elseif ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	throw new RuntimeException('Unable to locate closing brace for ' . $name . '.');
}

function vms_test_capture(callable $callback): array
{
	$warnings = array();
	set_error_handler(
		static function (int $errno, string $errstr, string $errfile, int $errline) use (&$warnings): bool {
			$warnings[] = array(
				'errno' => $errno,
				'message' => $errstr,
				'file' => $errfile,
				'line' => $errline,
			);
			return true;
		}
	);

	try {
		$result = $callback();
	} finally {
		restore_error_handler();
	}

	return array(
		'result' => $result,
		'warnings' => $warnings,
	);
}

function vms_test_capture_render(callable $callback): array
{
	ob_start();
	$captured = vms_test_capture($callback);
	$html = (string) ob_get_clean();

	return array(
		'html' => $html,
		'warnings' => $captured['warnings'],
		'result' => $captured['result'],
	);
}

function vms_test_reset_environment(): void
{
	$GLOBALS['vms_test_meta'] = array();
	$GLOBALS['vms_test_updates'] = array();
	$GLOBALS['vms_test_deletes'] = array();
	$GLOBALS['vms_test_post_titles'] = array();
	$GLOBALS['vms_test_post_types'] = array();
	$GLOBALS['vms_test_post_fields'] = array();
	$GLOBALS['vms_test_lineup_rows'] = array();
	$GLOBALS['vms_test_current_user_id'] = VMS_TEST_CURRENT_USER_ID;
	$GLOBALS['vms_test_current_time'] = VMS_TEST_CURRENT_TIME;
}

function vms_test_published_state(): array
{
	return array(
		'plan_title' => 'Published Plan',
		'meta' => array(
			'_vms_event_date' => '2026-08-01',
			'_vms_start_time' => '18:00',
			'_vms_end_time' => '21:00',
			'_vms_venue_id' => 10,
			'_vms_event_plan_status' => 'published',
			'_vms_band_vendor_id' => 50,
			'_vms_secondary_vendor_type' => 'food_truck',
			'_vms_secondary_vendor_ids' => array(61, 62),
		),
		'lineup_rows' => array(
			array(
				'vendor_id' => 50,
				'role' => 'primary',
				'set_start' => '18:00',
				'set_end' => '20:00',
				'guaranteed_fee' => '500.00',
				'sort_order' => 0,
			),
		),
	);
}

function vms_test_current_state(): array
{
	return array(
		'plan_title' => 'Current Draft Plan',
		'meta' => array(
			'_vms_event_date' => '2026-08-02',
			'_vms_start_time' => '19:00',
			'_vms_end_time' => '22:00',
			'_vms_venue_id' => 45,
			'_vms_event_plan_status' => 'draft',
			'_vms_band_vendor_id' => 51,
			'_vms_secondary_vendor_type' => 'merch',
			'_vms_secondary_vendor_ids' => array(61, 63),
		),
		'lineup_rows' => array(
			array(
				'vendor_id' => 51,
				'role' => 'primary',
				'set_start' => '19:00',
				'set_end' => '21:00',
				'guaranteed_fee' => '650.00',
				'sort_order' => 0,
			),
			array(
				'vendor_id' => 63,
				'role' => 'supporting',
				'set_start' => '21:00',
				'set_end' => '22:00',
				'guaranteed_fee' => '125.00',
				'sort_order' => 1,
			),
		),
	);
}

function vms_test_apply_state(array $state): void
{
	$GLOBALS['vms_test_post_types'][VMS_TEST_PLAN_ID] = 'vms_event_plan';
	$GLOBALS['vms_test_post_titles'][VMS_TEST_PLAN_ID] = (string) ($state['plan_title'] ?? '');
	$GLOBALS['vms_test_post_titles'][10] = 'Main Hall';
	$GLOBALS['vms_test_post_titles'][45] = 'Skyline Room';
	$GLOBALS['vms_test_post_titles'][50] = 'Alpha Band';
	$GLOBALS['vms_test_post_titles'][51] = 'Beta Band';
	$GLOBALS['vms_test_post_titles'][61] = 'Taco Truck';
	$GLOBALS['vms_test_post_titles'][62] = 'Merch Booth';
	$GLOBALS['vms_test_post_titles'][63] = 'Photo Booth';
	$GLOBALS['vms_test_post_fields'][VMS_TEST_PLAN_ID] = array(
		'post_modified_gmt' => VMS_TEST_POST_MODIFIED_GMT,
	);

	foreach ((array) ($state['meta'] ?? array()) as $meta_key => $value) {
		$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID][$meta_key] = $value;
	}
	$GLOBALS['vms_test_lineup_rows'] = (array) ($state['lineup_rows'] ?? array());
}

function vms_test_snapshot_from_state(array $state): array
{
	vms_test_reset_environment();
	vms_test_apply_state($state);
	return vms_event_plan_review_current_snapshot(VMS_TEST_PLAN_ID);
}

function vms_test_meta_value(string $meta_key)
{
	return $GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID][$meta_key] ?? null;
}

function vms_test_meta_exists(string $meta_key): bool
{
	return array_key_exists($meta_key, $GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID] ?? array());
}

function vms_test_count_updates(string $meta_key): int
{
	$count = 0;
	foreach ((array) $GLOBALS['vms_test_updates'] as $update) {
		if (($update[1] ?? '') === $meta_key) {
			$count++;
		}
	}
	return $count;
}

function vms_test_count_deletes(string $meta_key): int
{
	$count = 0;
	foreach ((array) $GLOBALS['vms_test_deletes'] as $delete) {
		if (($delete[1] ?? '') === $meta_key) {
			$count++;
		}
	}
	return $count;
}

function vms_test_last_update_value(string $meta_key)
{
	$last = null;
	foreach ((array) $GLOBALS['vms_test_updates'] as $update) {
		if (($update[1] ?? '') === $meta_key) {
			$last = $update[2] ?? null;
		}
	}
	return $last;
}

function vms_test_generic_activity_visible(array $activity): bool
{
	foreach ($activity as $row) {
		if (($row['title'] ?? '') === 'Unpublished changes tracked') {
			return true;
		}
	}
	return false;
}

function vms_test_specific_review_alert_visible(array $alerts): bool
{
	foreach ($alerts as $alert) {
		if (($alert['title'] ?? '') === 'Needs review before republish') {
			return true;
		}
	}
	return false;
}

function vms_test_invalid_utf8_json_object(): string
{
	return "{\"value\":\"" . chr(0xC3) . chr(0x28) . "\"}";
}

function vms_test_deep_json_object(int $depth): string
{
	$raw = '';
	for ($i = 0; $i < $depth; $i++) {
		$raw .= '{"x":';
	}
	$raw .= '"leaf"';
	for ($i = 0; $i < $depth; $i++) {
		$raw .= '}';
	}
	return $raw;
}

function vms_test_large_unknown_object(int $count): string
{
	$payload = array();
	for ($i = 0; $i < $count; $i++) {
		$payload['unknown_key_' . $i] = 'value_' . $i;
	}
	$json = wp_json_encode($payload);
	return is_string($json) ? $json : '{}';
}

function vms_test_command_center_inputs(): array
{
	return array(
		'header' => array(
			'venue_id' => 45,
			'date_raw' => '2026-08-02',
			'edit_url' => '/wp-admin/post.php?post=123&action=edit',
			'ticket_url' => '',
		),
		'ticket' => array(
			'integrity_status' => 'green',
			'low_inventory_flag' => false,
		),
		'lineup' => array(
			'primary' => array('vendor_id' => 51),
			'warnings' => array(),
		),
		'staffing' => array(
			'critical_open_headcount' => 0,
			'open_headcount_total' => 0,
			'conflict_count' => 0,
		),
		'marketing' => array(
			'event_page_public' => false,
			'social_ready' => false,
			'social_url' => '/wp-admin/admin.php?page=vms-social',
			'promo_video_id' => 0,
			'promo_external_url' => '',
			'promo_submission_pending' => false,
		),
		'weather' => array(),
	);
}

function vms_test_seed_snapshot_state($raw, bool $set_raw, bool $set_markers): void
{
	if ($set_raw) {
		$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID]['_vms_published_snapshot_json'] = $raw;
	}

	if ($set_markers) {
		$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID]['_vms_published_snapshot_at'] = VMS_TEST_SNAPSHOT_AT;
		$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID]['_vms_published_snapshot_by'] = VMS_TEST_CURRENT_USER_ID;
	}
}

function vms_test_seed_changes_state($raw, bool $set_raw, bool $set_markers, string $source = 'event_plan_editor'): void
{
	if ($set_raw) {
		$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID]['_vms_unpublished_changes_json'] = $raw;
	}

	if ($set_markers) {
		$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID]['_vms_unpublished_changes_at'] = VMS_TEST_CHANGES_AT;
		$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID]['_vms_unpublished_changes_by'] = VMS_TEST_CURRENT_USER_ID;
		$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID]['_vms_unpublished_changes_source'] = $source;
	}
}

function vms_test_read_review_surfaces(): array
{
	$snapshot_state = vms_test_capture(
		static function () {
			return vms_event_plan_review_get_snapshot_state(VMS_TEST_PLAN_ID);
		}
	);
	$snapshot = vms_test_capture(
		static function () {
			return vms_event_plan_review_get_snapshot(VMS_TEST_PLAN_ID);
		}
	);
	$changes_state = vms_test_capture(
		static function () {
			return vms_event_plan_review_get_changes_state(VMS_TEST_PLAN_ID);
		}
	);
	$changes = vms_test_capture(
		static function () {
			return vms_event_plan_review_get_changes(VMS_TEST_PLAN_ID);
		}
	);
	$integrity = vms_test_capture(
		static function () {
			return vms_event_plan_review_get_integrity_issue(VMS_TEST_PLAN_ID);
		}
	);
	$has_changes = vms_test_capture(
		static function () {
			return vms_event_plan_review_has_changes(VMS_TEST_PLAN_ID);
		}
	);

	$post = new WP_Post();
	$post->ID = VMS_TEST_PLAN_ID;
	$post->post_type = 'vms_event_plan';

	$banner = vms_test_capture_render(
		static function () use ($post): void {
			vms_event_plan_review_render_banner($post);
		}
	);
	$status_note = vms_test_capture_render(
		static function (): void {
			vms_event_plan_review_render_status_note('vms_plan_status', VMS_TEST_PLAN_ID);
		}
	);
	$activity = vms_test_capture(
		static function () {
			return vms_event_command_center_collect_activity(VMS_TEST_PLAN_ID);
		}
	);
	$command_center_inputs = vms_test_command_center_inputs();
	$alerts = vms_test_capture(
		static function () use ($command_center_inputs) {
			return vms_event_command_center_build_alerts(
				VMS_TEST_PLAN_ID,
				(array) $command_center_inputs['header'],
				(array) $command_center_inputs['ticket'],
				(array) $command_center_inputs['lineup'],
				(array) $command_center_inputs['staffing'],
				(array) $command_center_inputs['marketing'],
				(array) $command_center_inputs['weather']
			);
		}
	);
	$health = vms_test_capture(
		static function () use ($alerts) {
			return vms_event_command_center_get_health((array) $alerts['result']);
		}
	);

	return array(
		'snapshot_state' => $snapshot_state,
		'snapshot' => $snapshot,
		'changes_state' => $changes_state,
		'changes' => $changes,
		'integrity' => $integrity,
		'has_changes' => $has_changes,
		'banner' => $banner,
		'status_note' => $status_note,
		'activity' => $activity,
		'alerts' => $alerts,
		'health' => $health,
		'generic_activity_visible' => vms_test_generic_activity_visible((array) $activity['result']),
		'specific_review_alert_visible' => vms_test_specific_review_alert_visible((array) $alerts['result']),
	);
}

function vms_test_run_read_case(array $config): array
{
	vms_test_reset_environment();
	vms_test_apply_state((array) ($config['applied_state'] ?? vms_test_current_state()));
	vms_test_seed_snapshot_state($config['snapshot_raw'] ?? null, !empty($config['set_snapshot_meta']), !empty($config['set_snapshot_markers']));
	vms_test_seed_changes_state(
		$config['changes_raw'] ?? null,
		!empty($config['set_changes_meta']),
		!empty($config['set_changes_markers']),
		(string) ($config['changes_source'] ?? 'event_plan_editor')
	);

	$result = vms_test_read_review_surfaces();
	$result['changes_writes'] = vms_test_count_updates('_vms_unpublished_changes_json');
	$result['snapshot_writes'] = vms_test_count_updates('_vms_published_snapshot_json');
	$result['changes_clears'] = vms_test_count_deletes('_vms_unpublished_changes_json');
	$result['remaining_changes_json'] = vms_test_meta_value('_vms_unpublished_changes_json');
	$result['remaining_snapshot_json'] = vms_test_meta_value('_vms_published_snapshot_json');

	return $result;
}

function vms_test_run_touch_case(array $config): array
{
	vms_test_reset_environment();
	vms_test_apply_state((array) ($config['applied_state'] ?? vms_test_current_state()));
	vms_test_seed_snapshot_state($config['snapshot_raw'] ?? null, !empty($config['set_snapshot_meta']), !empty($config['set_snapshot_markers']));
	vms_test_seed_changes_state(
		$config['changes_raw'] ?? null,
		!empty($config['set_changes_meta']),
		!empty($config['set_changes_markers']),
		(string) ($config['changes_source'] ?? 'event_plan_editor')
	);

	$touch = vms_test_capture(
		static function () {
			return vms_event_plan_review_touch(VMS_TEST_PLAN_ID, 'event_plan_editor', VMS_TEST_CURRENT_USER_ID);
		}
	);

	$result = vms_test_read_review_surfaces();
	$result['touch'] = $touch;
	$result['changes_writes'] = vms_test_count_updates('_vms_unpublished_changes_json');
	$result['snapshot_writes'] = vms_test_count_updates('_vms_published_snapshot_json');
	$result['changes_clears'] = vms_test_count_deletes('_vms_unpublished_changes_json');
	$result['snapshot_clears'] = vms_test_count_deletes('_vms_published_snapshot_json');
	$result['remaining_changes_json'] = vms_test_meta_value('_vms_unpublished_changes_json');
	$result['remaining_changes_at'] = vms_test_meta_value('_vms_unpublished_changes_at');
	$result['remaining_changes_by'] = vms_test_meta_value('_vms_unpublished_changes_by');
	$result['remaining_changes_source'] = vms_test_meta_value('_vms_unpublished_changes_source');
	$result['remaining_snapshot_json'] = vms_test_meta_value('_vms_published_snapshot_json');
	$result['remaining_snapshot_at'] = vms_test_meta_value('_vms_published_snapshot_at');
	$result['remaining_snapshot_by'] = vms_test_meta_value('_vms_published_snapshot_by');

	return $result;
}

function vms_test_build_duplicate_snapshot_json(): string
{
	$lineup_json = wp_json_encode(
		array(
			array(
				'vendor_id' => 50,
				'vendor_label' => 'Alpha Band',
				'role' => 'primary',
				'set_start' => '18:00',
				'set_end' => '20:00',
				'guaranteed_fee' => 500,
				'sort_order' => 0,
			),
		)
	);
	$secondary_json = wp_json_encode(array(61, 62));
	return '{"title":"Wrong","title":"Published Plan","event_date":"2026-08-01","start_time":"18:00","end_time":"21:00","venue_id":10,"status":"published","primary_vendor_id":50,"secondary_vendor_type":"food_truck","secondary_vendor_ids":' . $secondary_json . ',"lineup_rows":' . $lineup_json . '}';
}

function vms_test_build_large_valid_snapshot_json(array $snapshot): string
{
	$payload = $snapshot;
	for ($i = 0; $i < 150; $i++) {
		$payload['extra_snapshot_key_' . $i] = 'value_' . $i;
	}

	$json = wp_json_encode($payload);
	return is_string($json) ? $json : '';
}

function vms_test_build_large_valid_changes_json(array $payload): string
{
	$large = $payload;
	for ($i = 0; $i < 150; $i++) {
		$large['extra_changes_key_' . $i] = 'value_' . $i;
	}

	$json = wp_json_encode($large);
	return is_string($json) ? $json : '';
}

$mirror_review_path = getcwd() . '/includes/core/event-plan-review.php';
$live_review_path = realpath(getcwd() . '/../../vms/includes/core/event-plan-review.php');
$command_center_path = getcwd() . '/includes/admin/event-command-center.php';

vms_test_assert_true(is_string($live_review_path) && $live_review_path !== '', 'Live event-plan-review.php path should resolve.');

$mirror_review_source = (string) file_get_contents($mirror_review_path);
$live_review_source = (string) file_get_contents($live_review_path);
$command_center_source = (string) file_get_contents($command_center_path);

$current_snapshot_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_current_snapshot');
$decode_snapshot_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_decode_snapshot_json');
$decode_changes_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_decode_changes_json');
$get_snapshot_state_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_get_snapshot_state');
$get_changes_state_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_get_changes_state');
$get_snapshot_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_get_snapshot');
$get_changes_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_get_changes');
$build_changes_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_build_changes');
$clear_changes_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_clear_changes');
$mark_published_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_mark_published');
$touch_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_touch');
$has_changes_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_has_changes');
$integrity_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_get_integrity_issue');
$banner_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_render_banner');
$status_note_body = vms_test_extract_function($mirror_review_source, 'vms_event_plan_review_render_status_note');
$activity_body = vms_test_extract_function($command_center_source, 'vms_event_command_center_collect_activity');
$alerts_body = vms_test_extract_function($command_center_source, 'vms_event_command_center_build_alerts');
$health_body = vms_test_extract_function($command_center_source, 'vms_event_command_center_get_health');

vms_test_assert_same(1, substr_count($decode_snapshot_body, 'json_decode('), 'Snapshot decoder should retain exactly one raw json_decode() call.');
vms_test_assert_same(1, substr_count($decode_changes_body, 'json_decode('), 'Changes decoder should retain exactly one raw json_decode() call.');
vms_test_assert_same(2, substr_count($mirror_review_source, 'json_decode('), 'Event Plan Review runtime should retain exactly two raw json_decode() calls.');
vms_test_assert_true(strpos($get_snapshot_body, 'json_decode(') === false, 'Snapshot compatibility wrapper should no longer decode raw JSON directly.');
vms_test_assert_true(strpos($get_changes_body, 'json_decode(') === false, 'Changes compatibility wrapper should no longer decode raw JSON directly.');
vms_test_assert_true(strpos($mirror_review_source, 'vms_json_decode_associative(') === false, 'Event Plan Review should not delegate these fields to the shared JSON helper.');
vms_test_assert_true(strpos($mirror_review_source, 'vms_event_plan_review_decode_snapshot_json') !== false && strpos($mirror_review_source, 'vms_event_plan_review_decode_changes_json') !== false, 'Specialized JSON decoders should exist.');
vms_test_assert_true(strpos($mirror_review_source, 'vms_event_plan_review_get_snapshot_state') !== false && strpos($mirror_review_source, 'vms_event_plan_review_get_changes_state') !== false, 'State-aware post readers should exist.');
vms_test_assert_contains("update_post_meta(\$plan_id, vms_event_plan_review_meta_key('snapshot_json'), wp_json_encode(\$snapshot));", $mark_published_body, 'Snapshot writer should remain wp_json_encode()-backed.');
vms_test_assert_contains("update_post_meta(\$plan_id, vms_event_plan_review_meta_key('changes_json'), wp_json_encode(\$payload));", $touch_body, 'Changes writer should remain wp_json_encode()-backed.');
vms_test_assert_contains("'snapshot_json' => '_vms_published_snapshot_json'", $mirror_review_source, 'Snapshot meta key should remain unchanged.');
vms_test_assert_contains("'changes_json' => '_vms_unpublished_changes_json'", $mirror_review_source, 'Changes meta key should remain unchanged.');
vms_test_assert_contains("'count' => count(\$changes)", $touch_body, 'Changes payload should still contain count.');
vms_test_assert_contains("'changes' => \$changes", $touch_body, 'Changes payload should still contain changes.');
vms_test_assert_contains("vms_event_plan_review_get_snapshot_state(\$plan_id)", $touch_body, 'touch() should use snapshot state.');
$touch_invalid_pos = strpos($touch_body, "if ('invalid' === (\$snapshot_state['state'] ?? ''))");
$touch_write_pos = strpos($touch_body, "update_post_meta(\$plan_id, vms_event_plan_review_meta_key('changes_json'), wp_json_encode(\$payload));");
vms_test_assert_true(is_int($touch_invalid_pos) && is_int($touch_write_pos) && $touch_invalid_pos < $touch_write_pos, 'Invalid snapshot branch should return before the changes write path.');
$touch_user_id_pos = strpos($touch_body, 'if ($user_id <= 0)');
$touch_invalid_slice = is_int($touch_invalid_pos) && is_int($touch_user_id_pos) && $touch_invalid_pos < $touch_user_id_pos ? substr($touch_body, $touch_invalid_pos, $touch_user_id_pos - $touch_invalid_pos) : '';
vms_test_assert_contains("return 'valid' === (\$changes_state['state'] ?? '') ? (array) (\$changes_state['value'] ?? array()) : array();", $touch_invalid_slice, 'Invalid snapshot branch should preserve only stored valid changes.');
vms_test_assert_not_contains("vms_event_plan_review_clear_changes(\$plan_id)", $touch_invalid_slice, 'Invalid snapshot branch should not clear changes.');
vms_test_assert_contains("'invalid' === (\$snapshot_state['state'] ?? '')", $has_changes_body, 'has_changes() should consult invalid snapshot state.');
vms_test_assert_contains("'invalid' === (\$changes_state['state'] ?? '')", $has_changes_body, 'has_changes() should consult invalid changes state.');
vms_test_assert_contains("vms_event_plan_review_get_changes(\$plan_id)", $activity_body, 'Command Center activity should still read changes through the review helper.');
vms_test_assert_contains("vms_event_plan_review_has_changes(\$plan_id)", $alerts_body, 'Command Center alerts should still consult has_changes() through the review helper.');
vms_test_assert_true(strpos($mirror_review_source, 'snapshot_version') === false && strpos($mirror_review_source, 'changes_version') === false, 'No migration or version marker should be added.');
vms_test_assert_true($live_review_source !== '', 'Live event-plan-review.php should remain readable while this mirror-only remediation leaves ../../vms untouched.');
$command_center_diffs = trim((string) shell_exec("git diff --name-only -- includes/admin/event-command-center.php"));
vms_test_assert_same('', $command_center_diffs, 'Event Command Center should remain unchanged in this slice.');

require $mirror_review_path;
eval($activity_body);
eval($alerts_body);
eval($health_body);

$published_snapshot = vms_test_snapshot_from_state(vms_test_published_state());
$current_snapshot = vms_test_snapshot_from_state(vms_test_current_state());
$published_snapshot_json = (string) wp_json_encode($published_snapshot);
$current_snapshot_json = (string) wp_json_encode($current_snapshot);
vms_test_assert_true($published_snapshot_json !== '' && $current_snapshot_json !== '', 'Valid snapshot fixtures should JSON-encode successfully.');

$computed_changes = vms_event_plan_review_build_changes($published_snapshot, $current_snapshot);
vms_test_assert_true(count($computed_changes) > 0, 'Published and current snapshot fixtures should produce tracked changes.');
$valid_changes_payload = array(
	'count' => count($computed_changes),
	'changes' => $computed_changes,
);
$valid_changes_json = (string) wp_json_encode($valid_changes_payload);
$valid_zero_changes_payload = array(
	'count' => 0,
	'changes' => array(),
);
$valid_zero_changes_json = (string) wp_json_encode($valid_zero_changes_payload);
vms_test_assert_true($valid_changes_json !== '' && $valid_zero_changes_json !== '', 'Valid changes fixtures should JSON-encode successfully.');

$duplicate_snapshot_json = vms_test_build_duplicate_snapshot_json();
$large_valid_snapshot_json = vms_test_build_large_valid_snapshot_json($published_snapshot);
$large_valid_changes_json = vms_test_build_large_valid_changes_json($valid_changes_payload);
$changes_with_unknown_keys_json = (string) wp_json_encode(
	array(
		'count' => 999,
		'changes' => $computed_changes,
		'ignored_top_level' => 'ok',
	)
);
$duplicate_count_changes_json = '{"count":99,"count":1,"changes":[{"field":"status","label":"Plan status","before":"published","after":"draft","before_label":"Published","after_label":"Draft","summary":"Plan status changed from Published to Draft"}]}';
$invalid_snapshot_message = vms_event_plan_review_integrity_message();

$snapshot_state_cases = array(
	'true_no_baseline' => array(
		'set_snapshot_meta' => false,
		'set_snapshot_markers' => false,
		'expected_state' => 'missing',
	),
	'blank_without_marker' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '',
		'set_snapshot_markers' => false,
		'expected_state' => 'missing',
	),
	'blank_with_marker' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'valid_expected_object' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'expected_state' => 'valid',
	),
	'empty_object' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '{}',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'list' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '[1]',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'empty_list' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '[]',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'scalar' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '"hello"',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'number' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '123',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'true' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => 'true',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'false' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => 'false',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'json_null' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => 'null',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'malformed' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '{"title":',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'truncated' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '{"title":"Published Plan"',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'invalid_utf8' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => vms_test_invalid_utf8_json_object(),
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'excessive_depth' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => vms_test_deep_json_object(520),
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'numeric_key_object' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '{"0":"x"}',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'unknown_key_object' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '{"foo":"bar"}',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'missing_required_keys' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '{"title":"Published Plan"}',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'invalid_nested_fields' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => '{"title":{"nested":"value"},"event_date":"2026-08-01","start_time":"18:00","end_time":"21:00","venue_id":10,"status":"published","primary_vendor_id":50,"secondary_vendor_type":"food_truck","secondary_vendor_ids":[{"vendor":61}],"lineup_rows":[{"vendor_id":50}]}',
		'set_snapshot_markers' => true,
		'expected_state' => 'invalid',
	),
	'duplicate_keys' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $duplicate_snapshot_json,
		'set_snapshot_markers' => true,
		'expected_state' => 'valid',
	),
	'large_valid_object' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $large_valid_snapshot_json,
		'set_snapshot_markers' => true,
		'expected_state' => 'valid',
	),
);

$snapshot_state_results = array();
foreach ($snapshot_state_cases as $case_name => $case_config) {
	$snapshot_state_results[$case_name] = vms_test_run_read_case($case_config);
	vms_test_assert_no_warnings($snapshot_state_results[$case_name]['snapshot_state'], $case_name . ' snapshot state read should be warning-free.');
	vms_test_assert_no_warnings($snapshot_state_results[$case_name]['snapshot'], $case_name . ' snapshot compatibility read should be warning-free.');
	vms_test_assert_same($case_config['expected_state'], $snapshot_state_results[$case_name]['snapshot_state']['result']['state'] ?? '', $case_name . ' should report the expected snapshot state.');
}

vms_test_assert_json_equivalent($published_snapshot, $snapshot_state_results['valid_expected_object']['snapshot_state']['result']['value'] ?? array(), 'Valid snapshot state should normalize to the canonical writer schema.');
vms_test_assert_json_equivalent($published_snapshot, $snapshot_state_results['valid_expected_object']['snapshot']['result'], 'Snapshot compatibility wrapper should return the normalized snapshot for valid state.');
vms_test_assert_same(array(), $snapshot_state_results['blank_with_marker']['snapshot']['result'], 'Snapshot compatibility wrapper should return an empty array for invalid state.');
vms_test_assert_same(array(), $snapshot_state_results['blank_without_marker']['snapshot']['result'], 'Snapshot compatibility wrapper should return an empty array for missing state.');
vms_test_assert_same('Published Plan', $snapshot_state_results['duplicate_keys']['snapshot_state']['result']['value']['title'] ?? '', 'Duplicate snapshot keys should resolve to the final duplicate value after validation.');
vms_test_assert_json_equivalent($published_snapshot, $snapshot_state_results['large_valid_object']['snapshot_state']['result']['value'] ?? array(), 'Large valid snapshot objects should ignore unknown top-level keys after schema validation.');

$touch_first_publish = vms_test_run_touch_case(
	array(
		'set_snapshot_meta' => false,
		'set_snapshot_markers' => false,
		'set_changes_meta' => false,
		'set_changes_markers' => false,
	)
);
vms_test_assert_no_warnings($touch_first_publish['touch'], 'First-publish touch should be warning-free.');
vms_test_assert_same(array(), $touch_first_publish['touch']['result'], 'First-publish touch should remain clean when no baseline exists.');
vms_test_assert_same(0, $touch_first_publish['changes_writes'], 'First-publish touch should not write derived changes.');
vms_test_assert_same(1, $touch_first_publish['changes_clears'], 'First-publish touch should preserve the current clear-on-missing behavior.');

$touch_valid_unchanged = vms_test_run_touch_case(
	array(
		'applied_state' => vms_test_current_state(),
		'set_snapshot_meta' => true,
		'snapshot_raw' => $current_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => $valid_changes_json,
		'set_changes_markers' => true,
	)
);
vms_test_assert_same(array(), $touch_valid_unchanged['touch']['result'], 'Valid unchanged baseline should clear changes on touch.');
vms_test_assert_same(null, $touch_valid_unchanged['remaining_changes_json'], 'Valid unchanged baseline should clear stored derived changes.');
vms_test_assert_same(1, $touch_valid_unchanged['changes_clears'], 'Valid unchanged baseline should clear the derived changes payload.');

$touch_valid_changed = vms_test_run_touch_case(
	array(
		'applied_state' => vms_test_current_state(),
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => false,
		'set_changes_markers' => false,
	)
);
vms_test_assert_true($touch_valid_changed['changes_writes'] > 0, 'Valid changed baseline should write canonical derived changes.');
vms_test_assert_json_equivalent($valid_changes_payload, $touch_valid_changed['touch']['result'], 'Valid changed baseline should return the canonical derived changes payload.');

$touch_invalid_baseline_preserves_valid_changes = vms_test_run_touch_case(
	array(
		'applied_state' => vms_test_current_state(),
		'set_snapshot_meta' => true,
		'snapshot_raw' => '{}',
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => $valid_changes_json,
		'set_changes_markers' => true,
	)
);
vms_test_assert_same(0, $touch_invalid_baseline_preserves_valid_changes['changes_clears'], 'Invalid snapshot should not clear existing derived changes.');
vms_test_assert_same(0, $touch_invalid_baseline_preserves_valid_changes['changes_writes'], 'Invalid snapshot should not overwrite existing derived changes.');
vms_test_assert_same(0, $touch_invalid_baseline_preserves_valid_changes['snapshot_writes'], 'Invalid snapshot should not rewrite snapshot_json.');
vms_test_assert_same($valid_changes_json, $touch_invalid_baseline_preserves_valid_changes['remaining_changes_json'], 'Invalid snapshot should preserve the previously stored valid derived changes payload.');
vms_test_assert_json_equivalent($valid_changes_payload, $touch_invalid_baseline_preserves_valid_changes['touch']['result'], 'Invalid snapshot should return the best stored valid changes payload.');
vms_test_assert_same('snapshot_invalid', $touch_invalid_baseline_preserves_valid_changes['integrity']['result']['type'] ?? '', 'Invalid snapshot should surface a snapshot integrity issue.');

$touch_invalid_baseline_no_changes = vms_test_run_touch_case(
	array(
		'applied_state' => vms_test_current_state(),
		'set_snapshot_meta' => true,
		'snapshot_raw' => '[1]',
		'set_snapshot_markers' => true,
		'set_changes_meta' => false,
		'set_changes_markers' => false,
	)
);
vms_test_assert_same(0, $touch_invalid_baseline_no_changes['changes_clears'], 'Invalid snapshot without stored changes should not clear anything.');
vms_test_assert_same(0, $touch_invalid_baseline_no_changes['changes_writes'], 'Invalid snapshot without stored changes should not fabricate derived changes.');
vms_test_assert_same(null, $touch_invalid_baseline_no_changes['remaining_changes_json'], 'Invalid snapshot without stored changes should leave derived changes absent.');
vms_test_assert_same(true, (bool) $touch_invalid_baseline_no_changes['has_changes']['result'], 'Invalid snapshot without stored changes should still be review-needed.');

$changes_state_cases = array(
	'true_no_changes' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => false,
		'set_changes_markers' => false,
		'expected_state' => 'missing',
	),
	'blank_without_marker' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '',
		'set_changes_markers' => false,
		'expected_state' => 'missing',
	),
	'blank_with_marker' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'valid_positive' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => $valid_changes_json,
		'set_changes_markers' => true,
		'expected_state' => 'valid',
	),
	'valid_zero' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => $valid_zero_changes_json,
		'set_changes_markers' => true,
		'expected_state' => 'valid',
	),
	'empty_object' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '{}',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'list' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '[{"field":"status"}]',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'empty_list' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '[]',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'scalar' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '"hello"',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'number' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '123',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'true' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => 'true',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'false' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => 'false',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'json_null' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => 'null',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'malformed' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '{"count":',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'truncated' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '{"count":1',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'invalid_utf8' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => vms_test_invalid_utf8_json_object(),
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'excessive_depth' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => vms_test_deep_json_object(520),
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'missing_changes' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '{"count":1}',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'malformed_changes_list' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '{"count":1,"changes":{"field":"status","summary":"Broken"}}',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'invalid_row_shape' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '{"count":1,"changes":[{"field":["bad"],"summary":{"bad":"x"},"label":"Broken"}]}',
		'set_changes_markers' => true,
		'expected_state' => 'invalid',
	),
	'duplicate_count' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => $duplicate_count_changes_json,
		'set_changes_markers' => true,
		'expected_state' => 'valid',
	),
	'unknown_keys' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => $changes_with_unknown_keys_json,
		'set_changes_markers' => true,
		'expected_state' => 'valid',
	),
	'large_valid_object' => array(
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => $large_valid_changes_json,
		'set_changes_markers' => true,
		'expected_state' => 'valid',
	),
);

$changes_state_results = array();
foreach ($changes_state_cases as $case_name => $case_config) {
	$changes_state_results[$case_name] = vms_test_run_read_case($case_config);
	vms_test_assert_no_warnings($changes_state_results[$case_name]['changes_state'], $case_name . ' changes state read should be warning-free.');
	vms_test_assert_no_warnings($changes_state_results[$case_name]['changes'], $case_name . ' changes compatibility read should be warning-free.');
	vms_test_assert_same($case_config['expected_state'], $changes_state_results[$case_name]['changes_state']['result']['state'] ?? '', $case_name . ' should report the expected changes state.');
}

vms_test_assert_json_equivalent($valid_changes_payload, $changes_state_results['valid_positive']['changes_state']['result']['value'] ?? array(), 'Valid changes payload should normalize to the canonical derived schema.');
vms_test_assert_same(0, $changes_state_results['valid_zero']['changes_state']['result']['value']['count'] ?? -1, 'Valid zero-change payload should remain valid with a zero count.');
vms_test_assert_same(array(), $changes_state_results['valid_zero']['changes_state']['result']['value']['changes'] ?? array('x'), 'Valid zero-change payload should preserve an empty changes list.');
vms_test_assert_same(1, $changes_state_results['duplicate_count']['changes_state']['result']['value']['count'] ?? 0, 'Duplicate count keys should be recomputed from the normalized filtered changes list.');
vms_test_assert_same(count($computed_changes), $changes_state_results['unknown_keys']['changes_state']['result']['value']['count'] ?? 0, 'Unknown top-level changes keys should be ignored after schema validation.');
vms_test_assert_same($changes_with_unknown_keys_json, $changes_state_results['unknown_keys']['remaining_changes_json'], 'Read-only changes state should not rewrite stored data.');

vms_test_reset_environment();
vms_test_apply_state(vms_test_current_state());
$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID]['_vms_unpublished_changes_json'] = $valid_changes_json;
$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID]['_vms_unpublished_changes_at'] = VMS_TEST_CHANGES_AT;
$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID]['_vms_unpublished_changes_by'] = VMS_TEST_CURRENT_USER_ID;
$GLOBALS['vms_test_meta'][VMS_TEST_PLAN_ID]['_vms_unpublished_changes_source'] = 'event_plan_editor';
$first_publish_mark = vms_test_capture(
	static function () {
		return vms_event_plan_review_mark_published(VMS_TEST_PLAN_ID, 'event_plan_editor', VMS_TEST_CURRENT_USER_ID);
	}
);
vms_test_assert_same($current_snapshot, $first_publish_mark['result'], 'mark_published() should snapshot the current Event Plan state.');
vms_test_assert_true(vms_test_count_updates('_vms_published_snapshot_json') > 0, 'mark_published() should write snapshot_json.');
vms_test_assert_true(vms_test_count_updates('_vms_published_snapshot_at') > 0, 'mark_published() should write snapshot_at.');
vms_test_assert_true(vms_test_count_updates('_vms_published_snapshot_by') > 0, 'mark_published() should write snapshot_by.');
vms_test_assert_same(null, vms_test_meta_value('_vms_unpublished_changes_json'), 'mark_published() should clear tracked changes on publish.');
vms_test_assert_same($current_snapshot_json, vms_test_last_update_value('_vms_published_snapshot_json'), 'mark_published() should persist the canonical snapshot JSON as written.');

$valid_visibility = $changes_state_results['valid_positive'];
vms_test_assert_same(true, (bool) $valid_visibility['has_changes']['result'], 'Valid derived changes should remain review-needed.');
vms_test_assert_true($valid_visibility['banner']['html'] !== '', 'Valid derived changes should preserve the detailed editor banner.');
vms_test_assert_contains('Needs Review', $valid_visibility['banner']['html'], 'Valid derived changes banner should retain the existing review heading.');
vms_test_assert_contains('Source: Event Plan editor', $valid_visibility['banner']['html'], 'Valid derived changes banner should preserve changes_source metadata.');
vms_test_assert_contains('By: Editor User', $valid_visibility['banner']['html'], 'Valid derived changes banner should preserve changes_by metadata.');
vms_test_assert_contains('Updated: ' . VMS_TEST_CHANGES_AT, $valid_visibility['banner']['html'], 'Valid derived changes banner should preserve changes_at metadata.');
vms_test_assert_same('', $changes_state_results['true_no_changes']['banner']['html'], 'True clean state should not render the editor banner.');

$invalid_snapshot_visibility = vms_test_run_read_case(
	array(
		'applied_state' => vms_test_current_state(),
		'set_snapshot_meta' => true,
		'snapshot_raw' => '{}',
		'set_snapshot_markers' => true,
		'set_changes_meta' => false,
		'set_changes_markers' => false,
	)
);
vms_test_assert_same(true, (bool) $invalid_snapshot_visibility['has_changes']['result'], 'Invalid snapshot state should remain review-needed.');
vms_test_assert_contains($invalid_snapshot_message, $invalid_snapshot_visibility['banner']['html'], 'Invalid snapshot should render the generic integrity warning.');
vms_test_assert_not_contains('{"', $invalid_snapshot_visibility['banner']['html'], 'Invalid snapshot banner should not expose raw JSON.');
vms_test_assert_not_contains('snapshot-marker-without-valid-json', $invalid_snapshot_visibility['banner']['html'], 'Invalid snapshot banner should not expose internal reasons.');
vms_test_assert_true($invalid_snapshot_visibility['status_note']['html'] !== '', 'Invalid snapshot should keep the list-table review note visible.');
vms_test_assert_same(false, $invalid_snapshot_visibility['generic_activity_visible'], 'Invalid snapshot without changes markers should not fabricate Command Center activity.');
vms_test_assert_same(true, $invalid_snapshot_visibility['specific_review_alert_visible'], 'Invalid snapshot should surface the specific review alert through has_changes().');
vms_test_assert_same('at-risk', $invalid_snapshot_visibility['health']['result']['status'] ?? '', 'Invalid snapshot should produce the safer Command Center review-risk status with the existing yellow stack.');
vms_test_assert_not_contains('Plan status changed from Published to Draft', $invalid_snapshot_visibility['banner']['html'], 'Invalid snapshot banner should not fabricate detailed change summaries.');

$invalid_changes_visibility = $changes_state_results['blank_with_marker'];
vms_test_assert_same(true, (bool) $invalid_changes_visibility['has_changes']['result'], 'Invalid changes state should remain review-needed.');
vms_test_assert_contains($invalid_snapshot_message, $invalid_changes_visibility['banner']['html'], 'Invalid changes should render the generic integrity warning.');
vms_test_assert_not_contains('changes-marker-without-valid-json', $invalid_changes_visibility['banner']['html'], 'Invalid changes banner should not expose internal reasons.');
vms_test_assert_same(true, $invalid_changes_visibility['generic_activity_visible'], 'Invalid changes with changes_at should preserve generic Command Center activity.');
vms_test_assert_same(true, $invalid_changes_visibility['specific_review_alert_visible'], 'Invalid changes should surface the specific review alert through has_changes().');
vms_test_assert_same('at-risk', $invalid_changes_visibility['health']['result']['status'] ?? '', 'Invalid changes should produce the safer Command Center review-risk status.');
vms_test_assert_not_contains('Plan status changed from Published to Draft', $invalid_changes_visibility['banner']['html'], 'Invalid changes should not show fabricated detailed change summaries.');

$touch_repairs_invalid_changes = vms_test_run_touch_case(
	array(
		'applied_state' => vms_test_current_state(),
		'set_snapshot_meta' => true,
		'snapshot_raw' => $published_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '{"count":1,"changes":{"field":"status","summary":"Broken"}}',
		'set_changes_markers' => true,
	)
);
vms_test_assert_true($touch_repairs_invalid_changes['changes_writes'] > 0, 'Valid snapshot plus invalid derived changes should be repaired by touch().');
vms_test_assert_json_equivalent($valid_changes_payload, $touch_repairs_invalid_changes['touch']['result'], 'touch() should recompute canonical derived changes when the snapshot is valid.');
vms_test_assert_json_equivalent($valid_changes_payload, vms_event_plan_review_get_changes(VMS_TEST_PLAN_ID), 'Repaired derived changes should read back through the compatibility wrapper.');

$touch_clears_invalid_changes_when_clean = vms_test_run_touch_case(
	array(
		'applied_state' => vms_test_current_state(),
		'set_snapshot_meta' => true,
		'snapshot_raw' => $current_snapshot_json,
		'set_snapshot_markers' => true,
		'set_changes_meta' => true,
		'changes_raw' => '{"count":1,"changes":{"field":"status","summary":"Broken"}}',
		'set_changes_markers' => true,
	)
);
vms_test_assert_same(array(), $touch_clears_invalid_changes_when_clean['touch']['result'], 'Valid snapshot with no actual differences should clear stale invalid derived changes.');
vms_test_assert_same(null, $touch_clears_invalid_changes_when_clean['remaining_changes_json'], 'Valid snapshot with no actual differences should clear stale invalid derived changes from storage.');

echo "event plan review json characterization: PASS\n";
