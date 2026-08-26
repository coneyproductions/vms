<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

chdir(dirname(__DIR__));

const VMS_TOURS_TEST_CURRENT_USER_ID = 101;
const VMS_TOURS_TEST_OTHER_USER_ID = 202;
const VMS_TOURS_TEST_NOW = '2026-07-23 14:15:16';

$pluginRoot = dirname(__DIR__);
$liveRoot = $pluginRoot . '/../../vms';

$GLOBALS['vms_test_user_meta'] = array();
$GLOBALS['vms_test_user_meta_reads'] = array();
$GLOBALS['vms_test_user_meta_updates'] = array();
$GLOBALS['vms_test_user_meta_deletes'] = array();
$GLOBALS['vms_test_runtime_warnings'] = array();
$GLOBALS['vms_test_current_user_caps'] = array(
	'manage_options' => true,
	'read' => true,
);
$GLOBALS['vms_test_current_user_id'] = VMS_TOURS_TEST_CURRENT_USER_ID;
$GLOBALS['vms_test_is_user_logged_in'] = true;
$GLOBALS['vms_test_current_time'] = VMS_TOURS_TEST_NOW;
$GLOBALS['vms_test_current_time_int'] = 1_785_000_000;
$GLOBALS['vms_test_capability_reads'] = 0;
$GLOBALS['vms_test_legacy_registry'] = array(
	array(
		'id' => 'tour-alpha',
		'title' => 'Tour Alpha',
		'version' => '3',
		'contexts' => array(
			array(
				'context_key' => 'admin:alpha',
				'screen_id' => 'alpha',
				'url' => '/wp-admin/admin.php?page=alpha',
			),
		),
		'steps' => array(
			array(
				'anchor' => '#alpha',
			),
		),
	),
	array(
		'id' => 'tour-beta',
		'title' => 'Tour Beta',
		'version' => '4',
		'contexts' => array(
			array(
				'context_key' => 'admin:beta',
				'screen_id' => 'beta',
				'url' => '/wp-admin/admin.php?page=beta',
			),
		),
		'steps' => array(
			array(
				'anchor' => '#beta',
			),
		),
	),
);

set_error_handler(
	static function (int $errno, string $errstr, string $errfile = '', int $errline = 0): bool {
		$GLOBALS['vms_test_runtime_warnings'][] = array(
			'type' => $errno,
			'message' => $errstr,
			'file' => $errfile,
			'line' => $errline,
		);
		return true;
	}
);

function vms_test_fail(string $message): void
{
	throw new RuntimeException($message);
}

function vms_test_assert_true($condition, string $message): void
{
	if (!$condition) {
		vms_test_fail($message);
	}
}

function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected !== $actual) {
		vms_test_fail(
			$message
			. "\nExpected: " . var_export($expected, true)
			. "\nActual: " . var_export($actual, true)
		);
	}
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	if (strpos($haystack, $needle) === false) {
		vms_test_fail($message . "\nMissing needle: " . $needle);
	}
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	if (strpos($haystack, $needle) !== false) {
		vms_test_fail($message . "\nUnexpected needle: " . $needle);
	}
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function esc_html__($text, string $domain = ''): string
{
	return esc_html(__((string) $text, $domain));
}

function esc_html($text): string
{
	return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_attr($text): string
{
	return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_url($url): string
{
	return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_url_raw($url): string
{
	return is_scalar($url) ? trim((string) $url) : '';
}

function wp_kses_post($text): string
{
	return is_scalar($text) ? (string) $text : '';
}

function wp_kses($text, array $allowed = array()): string
{
	unset($allowed);
	return is_scalar($text) ? (string) $text : '';
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
	return is_string($sanitized) ? $sanitized : '';
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

function absint($value): int
{
	return abs(intval($value));
}

function sanitize_html_class($class): string
{
	return sanitize_key($class);
}

function wp_parse_args($args, $defaults = array()): array
{
	return array_merge((array) $defaults, (array) $args);
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	return is_string($value) ? stripslashes($value) : $value;
}

function wp_json_encode($value)
{
	$json = json_encode($value);
	return is_string($json) ? $json : false;
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
}

function admin_url(string $path = ''): string
{
	return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function is_admin(): bool
{
	return true;
}

function wp_parse_url(string $url)
{
	return parse_url($url);
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
	$timezone = $timezone ?: wp_timezone();
	$timestamp = null === $timestamp ? (int) ($GLOBALS['vms_test_current_time_int'] ?? time()) : $timestamp;
	$date = new DateTimeImmutable('@' . $timestamp);
	return $date->setTimezone($timezone)->format($format);
}

function current_user_can(string $capability): bool
{
	$GLOBALS['vms_test_capability_reads']++;
	return !empty($GLOBALS['vms_test_current_user_caps'][$capability]);
}

function is_user_logged_in(): bool
{
	return !empty($GLOBALS['vms_test_is_user_logged_in']);
}

function get_current_user_id(): int
{
	return (int) ($GLOBALS['vms_test_current_user_id'] ?? 0);
}

function get_user_meta(int $user_id, string $key = '', bool $single = false)
{
	$GLOBALS['vms_test_user_meta_reads'][] = array($user_id, $key, $single);

	$store = $GLOBALS['vms_test_user_meta'][$user_id] ?? array();
	if ($key === '') {
		return $store;
	}

	if (!array_key_exists($key, $store)) {
		return $single ? '' : array();
	}

	$value = $store[$key];
	if ($single) {
		return $value;
	}

	return is_array($value) ? $value : array($value);
}

function update_user_meta(int $user_id, string $meta_key, $meta_value, $prev_value = '')
{
	unset($prev_value);
	$GLOBALS['vms_test_user_meta'][$user_id][$meta_key] = $meta_value;
	$GLOBALS['vms_test_user_meta_updates'][] = array($user_id, $meta_key, $meta_value);
	return true;
}

function delete_user_meta(int $user_id, string $meta_key, $meta_value = '')
{
	unset($meta_value);
	unset($GLOBALS['vms_test_user_meta'][$user_id][$meta_key]);
	$GLOBALS['vms_test_user_meta_deletes'][] = array($user_id, $meta_key);
	return true;
}

function vms_get_tour_registry(): array
{
	return (array) ($GLOBALS['vms_test_legacy_registry'] ?? array());
}

if (!class_exists('WP_REST_Request')) {
	class WP_REST_Request
	{
	}
}

if (!class_exists('BVMGR_Tours_Service')) {
	class BVMGR_Tours_Service
	{
		/**
		 * @var array<int,array<string,mixed>>
		 */
		private $registry;

		/**
		 * @param array<int,array<string,mixed>> $registry
		 */
		public function __construct(array $registry = array())
		{
			$this->registry = $registry;
		}

		/**
		 * @return array<int,array<string,mixed>>
		 */
		public function get_registry(): array
		{
			return $this->registry;
		}
	}
}

require_once $pluginRoot . '/includes/tours/class-vms-tours-storage.php';
require_once $pluginRoot . '/includes/core/tours/class-vms-tours.php';
require_once $pluginRoot . '/includes/tours/class-vms-tours-admin.php';

$storageSource = (string) file_get_contents($pluginRoot . '/includes/tours/class-vms-tours-storage.php');
$coreSource = (string) file_get_contents($pluginRoot . '/includes/core/tours/class-vms-tours.php');
$adminSource = (string) file_get_contents($pluginRoot . '/includes/tours/class-vms-tours-admin.php');
$serviceSource = (string) file_get_contents($pluginRoot . '/includes/tours/class-vms-tours-service.php');
$registrySource = (string) file_get_contents($pluginRoot . '/includes/tours/class-vms-tours-registry.php');
$runtimeJsSource = (string) file_get_contents($pluginRoot . '/assets/js/vms-tours-runtime.js');
$legacyJsSource = (string) file_get_contents($pluginRoot . '/assets/js/vms-tours.js');
$liveStorageSource = (string) file_get_contents($liveRoot . '/includes/tours/class-vms-tours-storage.php');
$liveCoreSource = (string) file_get_contents($liveRoot . '/includes/core/tours/class-vms-tours.php');
$liveRuntimeJsSource = (string) file_get_contents($liveRoot . '/assets/js/vms-tours-runtime.js');
$liveLegacyJsSource = (string) file_get_contents($liveRoot . '/assets/js/vms-tours.js');
$coreHash = hash('sha256', $coreSource);
$liveCoreHash = hash('sha256', $liveCoreSource);

vms_test_assert_true($storageSource !== '', 'Storage source should be readable.');
vms_test_assert_true($coreSource !== '', 'Legacy Tours source should be readable.');
vms_test_assert_true($adminSource !== '', 'Tours admin source should be readable.');
vms_test_assert_true($serviceSource !== '', 'Tours service source should be readable.');
vms_test_assert_true($runtimeJsSource !== '', 'Tours runtime JS should be readable.');
vms_test_assert_true($legacyJsSource !== '', 'Tours legacy JS should be readable.');

vms_test_assert_same($storageSource, $liveStorageSource, 'Mirror/live storage PHP must remain byte-identical.');
vms_test_assert_same($runtimeJsSource, $liveRuntimeJsSource, 'Mirror/live Tours runtime JS must remain byte-identical.');
vms_test_assert_same($legacyJsSource, $liveLegacyJsSource, 'Mirror/live Tours legacy JS must remain byte-identical.');
vms_test_assert_same(
	'bdbbd7f5df1c30c9de79530e962fc37487d7bc066e353fdf91ff44e555eb6343',
	$liveCoreHash,
	'Live legacy Tours PHP should remain on the untouched pre-T4 source hash until live-tree convergence is explicitly authorized.'
);
vms_test_assert_true(
	$coreHash !== $liveCoreHash,
	'Mirror/live legacy Tours PHP hashes should intentionally differ after the mirror-only T4 passive request-state remediation.'
);

vms_test_assert_same(1, substr_count($storageSource, 'json_decode('), 'Storage runtime should retain exactly one raw json_decode() call.');
vms_test_assert_same(1, substr_count($coreSource, 'json_decode('), 'Legacy Tours runtime should retain exactly one raw json_decode() call.');
preg_match_all('/function\s+([A-Za-z0-9_]*decode[A-Za-z0-9_]*)\s*\(/', $storageSource . "\n" . $coreSource, $decodeHelpers);
vms_test_assert_same(array(), $decodeHelpers[1], 'Tours runtime should not add a decoder helper in this pass.');
vms_test_assert_contains("const USER_META_STATE = 'vms_tours_state';", $storageSource, 'Storage key should remain vms_tours_state.');
vms_test_assert_contains("const USER_META_STATE            = 'vms_tours_state';", $coreSource, 'Legacy key should remain vms_tours_state.');
vms_test_assert_contains("$state = \$this->storage->get_user_state(get_current_user_id());", $adminSource, 'Tours admin labels should continue to read through the current storage helper.');
vms_test_assert_contains("$state = \$this->storage->get_user_state(\$user_id);", $serviceSource, 'Tours service payload should continue to use the current storage helper.');
vms_test_assert_contains("add_action('wp_ajax_vms_tours_mark_complete', array(\$this, 'ajax_mark_complete'));", $serviceSource, 'Current runtime should preserve the AJAX state writer hook.');
vms_test_assert_contains("check_ajax_referer('vms_tours', 'nonce');", $serviceSource, 'Current runtime writer should preserve the Tours nonce gate.');
vms_test_assert_contains("if (!is_user_logged_in() || !current_user_can('read')) {", $serviceSource, 'Current runtime writer should preserve the read capability gate.');
vms_test_assert_contains("\$this->storage->mark_tour_seen(\$user_id, \$tour_id);", $serviceSource, 'Current runtime should preserve the seen-state write path.');
vms_test_assert_contains("\$this->storage->mark_tour_complete(\$user_id, \$tour_id, \$tour_version);", $serviceSource, 'Current runtime should preserve the complete-state write path.');
vms_test_assert_contains("add_action('wp_ajax_vms_tours_update_state', array(__CLASS__, 'ajax_update_state'));", $coreSource, 'Legacy runtime should preserve the AJAX state writer hook.');
vms_test_assert_contains("check_ajax_referer('vms_tours_state', 'nonce');", $coreSource, 'Legacy runtime writer should preserve the legacy nonce gate.');
vms_test_assert_contains("update_user_meta(\$user_id, self::USER_META_STATE, wp_json_encode(\$state));", $coreSource, 'Legacy runtime should preserve the JSON-string write path.');
vms_test_assert_contains("update_user_meta(\$user_id, 'vms_tour_seen_' . \$tour_id, \$version);", $coreSource, 'Legacy runtime should preserve the dedicated seen-meta mirror.');
vms_test_assert_contains("\$seen_version = absint(get_user_meta(\$user_id, 'vms_tour_seen_' . \$tour_id, true));", $coreSource, 'Legacy bridge should preserve the dedicated seen-meta read.');
vms_test_assert_contains("'status' => 'completed',", $coreSource, 'Legacy bridge should continue to synthesize completed status.');
vms_test_assert_contains("'step_index' => 0,", $coreSource, 'Legacy bridge should continue to synthesize zero progress.');
vms_test_assert_contains("'updated_at' => time(),", $coreSource, 'Legacy bridge should continue to synthesize a dynamic updated_at value.');
vms_test_assert_contains("'version' => (string) (\$tour['version'] ?? ''),", $registrySource, 'Current runtime payload tour versions should remain string-normalized.');
vms_test_assert_contains("if (state[tour.id] && state[tour.id].completed_version === tour.version) {", $runtimeJsSource, 'Current runtime autorun suppression should remain completed_version-only.');
vms_test_assert_contains("user.state[tourId] = res.data.state;", $runtimeJsSource, 'Current runtime should continue to trust only returned per-tour state rows.');
vms_test_assert_contains("return ajaxPost('vms_tours_mark_complete', {", $runtimeJsSource, 'Current runtime should continue to write state only through explicit AJAX actions.');
vms_test_assert_contains("mode: 'seen',", $runtimeJsSource, 'Current runtime should preserve seen-state writes.');
vms_test_assert_contains("mode: 'complete',", $runtimeJsSource, 'Current runtime should preserve complete-state writes.');
vms_test_assert_contains("if (seen && seen.version_seen === tour.version && (seen.status === 'completed' || seen.status === 'dismissed')) {", $legacyJsSource, 'Legacy autostart suppression should remain version/status based.');
vms_test_assert_contains("status: 'in_progress',", $legacyJsSource, 'Legacy progress writes should preserve in_progress status.');
vms_test_assert_contains("step_index: idx", $legacyJsSource, 'Legacy progress writes should preserve step_index during progress.');
vms_test_assert_contains("status: endedStatus,", $legacyJsSource, 'Legacy completion writes should preserve end-state status.');
vms_test_assert_contains("step_index: lastActiveIndex", $legacyJsSource, 'Legacy completion writes should preserve last step_index.');
vms_test_assert_not_contains('vms_json_decode_associative(', $storageSource, 'Tours storage should not delegate its raw decode to the shared JSON helper.');
vms_test_assert_not_contains('vms_json_decode_associative(', $coreSource, 'Legacy Tours state should not delegate its raw decode to the shared JSON helper.');

function vms_test_reset_environment(): void
{
	$GLOBALS['vms_test_user_meta'] = array();
	$GLOBALS['vms_test_user_meta_reads'] = array();
	$GLOBALS['vms_test_user_meta_updates'] = array();
	$GLOBALS['vms_test_user_meta_deletes'] = array();
	$GLOBALS['vms_test_runtime_warnings'] = array();
	$GLOBALS['vms_test_current_user_caps'] = array(
		'manage_options' => true,
		'read' => true,
	);
	$GLOBALS['vms_test_current_user_id'] = VMS_TOURS_TEST_CURRENT_USER_ID;
	$GLOBALS['vms_test_is_user_logged_in'] = true;
	$GLOBALS['vms_test_current_time'] = VMS_TOURS_TEST_NOW;
	$GLOBALS['vms_test_current_time_int'] = 1_785_000_000;
	$GLOBALS['vms_test_capability_reads'] = 0;
}

function vms_test_capture(callable $callable): array
{
	$startWarnings = count($GLOBALS['vms_test_runtime_warnings']);
	try {
		$result = $callable();
		$exception = null;
	} catch (Throwable $throwable) {
		$result = null;
		$exception = get_class($throwable) . ': ' . $throwable->getMessage();
	}

	return array(
		'result' => $result,
		'exception' => $exception,
		'warnings' => array_slice($GLOBALS['vms_test_runtime_warnings'], $startWarnings),
	);
}

function vms_test_set_user_state_raw(int $userId, $value, bool $present = true): void
{
	if ($present) {
		$GLOBALS['vms_test_user_meta'][$userId][BVMGR_Tours_Storage::USER_META_STATE] = $value;
	} else {
		unset($GLOBALS['vms_test_user_meta'][$userId][BVMGR_Tours_Storage::USER_META_STATE]);
	}
}

function vms_test_set_user_meta(int $userId, string $key, $value, bool $present = true): void
{
	if ($present) {
		$GLOBALS['vms_test_user_meta'][$userId][$key] = $value;
	} else {
		unset($GLOBALS['vms_test_user_meta'][$userId][$key]);
	}
}

function vms_test_storage_autorun_state(array $storageState, string $tourId = 'tour-alpha', string $tourVersion = '3'): string
{
	if (
		isset($storageState[$tourId])
		&& is_array($storageState[$tourId])
		&& (($storageState[$tourId]['completed_version'] ?? null) === $tourVersion)
	) {
		return 'suppressed';
	}

	return 'show';
}

function vms_test_legacy_autostart_state(array $coreState, string $tourId = 'tour-alpha', int $tourVersion = 3): string
{
	$row = $coreState[$tourId] ?? null;
	if (
		is_array($row)
		&& absint($row['version_seen'] ?? 0) === $tourVersion
		&& in_array($row['status'] ?? null, array('completed', 'dismissed'), true)
	) {
		return 'suppressed';
	}

	return 'show';
}

function vms_test_legacy_progress_summary(array $coreState, string $tourId = 'tour-alpha'): string
{
	$row = $coreState[$tourId] ?? null;
	if (!is_array($row)) {
		return 'none';
	}

	$hasStatus = array_key_exists('status', $row);
	$hasStep = array_key_exists('step_index', $row);
	if (!$hasStatus && !$hasStep) {
		return 'none';
	}

	$status = is_scalar($row['status'] ?? null) ? (string) $row['status'] : gettype($row['status'] ?? null);
	$step = is_scalar($row['step_index'] ?? null) ? (string) $row['step_index'] : gettype($row['step_index'] ?? null);
	return 'status=' . $status . ';step=' . $step;
}

function vms_test_render_admin_status_label(BVMGR_Tours_Storage $storage): array
{
	$service = new BVMGR_Tours_Service(
		array(
			array(
				'id' => 'tour-alpha',
				'screen' => 'admin:alpha',
				'level' => 'beginner',
				'version' => '3',
				'auto_run' => true,
				'priority' => 10,
			),
		)
	);
	$admin = new BVMGR_Tours_Admin($service, $storage);

	$htmlCapture = vms_test_capture(
		static function () use ($admin): string {
			$renderer = Closure::bind(
				function (): void {
					$this->render_registry_table();
				},
				$admin,
				BVMGR_Tours_Admin::class
			);
			vms_test_assert_true($renderer instanceof Closure, 'Expected a bound Closure for the private registry-table renderer.');

			ob_start();
			$renderer();
			return (string) ob_get_clean();
		}
	);

	$html = is_string($htmlCapture['result']) ? $htmlCapture['result'] : '';
	$label = 'unknown';
	$knownLabels = array(
		'Completed at version 3 (2026-07-01 09:00:00)',
		'Completed at version 3 (2026-07-03 09:00:00)',
		'Completed older version 2 (current 3)',
		'Completed older version Array (current 3)',
		'Not completed',
	);
	foreach ($knownLabels as $candidate) {
		if (strpos($html, $candidate) !== false) {
			$label = $candidate;
			break;
		}
	}
	if ($label === 'unknown' && preg_match('/Completed at version [^<]+ \([^<]+\)/', $html, $matches) === 1) {
		$label = (string) $matches[0];
	}
	if ($label === 'unknown' && preg_match('/Completed older version [^<]+ \(current [^<]+\)/', $html, $matches) === 1) {
		$label = (string) $matches[0];
	}

	return array(
		'label' => $label,
		'html' => $html,
		'warnings' => $htmlCapture['warnings'],
		'exception' => $htmlCapture['exception'],
		'capability_reads' => (int) $GLOBALS['vms_test_capability_reads'],
		'writes' => count($GLOBALS['vms_test_user_meta_updates']),
		'deletes' => count($GLOBALS['vms_test_user_meta_deletes']),
	);
}

function vms_test_normalize_state($value)
{
	if (!is_array($value)) {
		return $value;
	}

	$out = array();
	foreach ($value as $key => $item) {
		if ($key === 'updated_at' && is_int($item)) {
			$out[$key] = '<dynamic-int>';
			continue;
		}

		if (is_array($item)) {
			$out[$key] = vms_test_normalize_state($item);
			continue;
		}

		$out[$key] = $item;
	}

	return $out;
}

function vms_test_warning_messages(array $warnings): array
{
	return array_map(
		static function (array $warning): string {
			return (string) ($warning['message'] ?? '');
		},
		$warnings
	);
}

function vms_test_make_deep_json(int $depth): string
{
	$json = '0';
	for ($i = 0; $i < $depth; $i++) {
		$json = '{"deep":' . $json . '}';
	}

	return $json;
}

function vms_test_make_large_state(int $count): array
{
	$state = array();
	for ($i = 1; $i <= $count; $i++) {
		$key = 'tour-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
		$state[$key] = array(
			'completed_version' => (string) $i,
			'completed_at' => '2026-07-01 09:00:00',
			'last_seen_at' => '2026-07-02 10:00:00',
		);
	}

	return $state;
}

function vms_test_run_case(array $case, BVMGR_Tours_Storage $storage): array
{
	vms_test_reset_environment();

	$present = !empty($case['present']);
	if (array_key_exists('raw', $case)) {
		vms_test_set_user_state_raw(VMS_TOURS_TEST_CURRENT_USER_ID, $case['raw'], $present);
	}

	foreach ((array) ($case['legacy'] ?? array()) as $tourId => $value) {
		vms_test_set_user_meta(VMS_TOURS_TEST_CURRENT_USER_ID, 'vms_tour_seen_' . $tourId, $value, true);
	}

	foreach ((array) ($case['extra_meta'] ?? array()) as $key => $value) {
		vms_test_set_user_meta(VMS_TOURS_TEST_CURRENT_USER_ID, (string) $key, $value, true);
	}

	foreach ((array) ($case['other_user_meta'] ?? array()) as $key => $value) {
		vms_test_set_user_meta(VMS_TOURS_TEST_OTHER_USER_ID, (string) $key, $value, true);
	}

	$storageCapture = vms_test_capture(
		static function () use ($storage): array {
			return $storage->get_user_state(VMS_TOURS_TEST_CURRENT_USER_ID);
		}
	);
	$storageReads = $GLOBALS['vms_test_user_meta_reads'];
	$storageWrites = count($GLOBALS['vms_test_user_meta_updates']);
	$storageDeletes = count($GLOBALS['vms_test_user_meta_deletes']);

	$GLOBALS['vms_test_user_meta_reads'] = array();
	$GLOBALS['vms_test_user_meta_updates'] = array();
	$GLOBALS['vms_test_user_meta_deletes'] = array();
	$GLOBALS['vms_test_capability_reads'] = 0;

	$coreCapture = vms_test_capture(
		static function (): array {
			return BVMGR_Tours::get_current_user_state();
		}
	);
	$coreReads = $GLOBALS['vms_test_user_meta_reads'];
	$coreWrites = count($GLOBALS['vms_test_user_meta_updates']);
	$coreDeletes = count($GLOBALS['vms_test_user_meta_deletes']);

	$GLOBALS['vms_test_user_meta_reads'] = array();
	$GLOBALS['vms_test_user_meta_updates'] = array();
	$GLOBALS['vms_test_user_meta_deletes'] = array();
	$GLOBALS['vms_test_capability_reads'] = 0;

	$adminResult = vms_test_render_admin_status_label($storage);

	$storageState = is_array($storageCapture['result']) ? $storageCapture['result'] : array();
	$coreState = is_array($coreCapture['result']) ? $coreCapture['result'] : array();

	return array(
		'id' => (int) $case['id'],
		'label' => (string) $case['label'],
		'storage_state' => vms_test_normalize_state($storageState),
		'core_state' => vms_test_normalize_state($coreState),
		'storage_keys' => array_values(array_map('strval', array_keys($storageState))),
		'core_keys' => array_values(array_map('strval', array_keys($coreState))),
		'storage_warnings' => vms_test_warning_messages($storageCapture['warnings']),
		'core_warnings' => vms_test_warning_messages($coreCapture['warnings']),
		'admin_warnings' => vms_test_warning_messages((array) ($adminResult['warnings'] ?? array())),
		'storage_exception' => $storageCapture['exception'],
		'core_exception' => $coreCapture['exception'],
		'admin_exception' => $adminResult['exception'] ?? null,
		'storage_reads' => $storageReads,
		'core_reads' => $coreReads,
		'storage_writes' => $storageWrites,
		'core_writes' => $coreWrites,
		'admin_writes' => (int) ($adminResult['writes'] ?? 0),
		'storage_deletes' => $storageDeletes,
		'core_deletes' => $coreDeletes,
		'admin_deletes' => (int) ($adminResult['deletes'] ?? 0),
		'admin_label' => (string) ($adminResult['label'] ?? 'unknown'),
		'admin_capability_reads' => (int) ($adminResult['capability_reads'] ?? 0),
		'current_runtime_autorun' => vms_test_storage_autorun_state($storageState),
		'legacy_autostart' => vms_test_legacy_autostart_state($coreState),
		'legacy_progress' => vms_test_legacy_progress_summary($coreState),
	);
}

$storage = new BVMGR_Tours_Storage();

$currentCases = array(
	array('id' => 1, 'label' => 'no_meta', 'present' => false),
	array('id' => 2, 'label' => 'db_null', 'present' => true, 'raw' => null),
	array('id' => 3, 'label' => 'empty_string', 'present' => true, 'raw' => ''),
	array('id' => 4, 'label' => 'whitespace_only_string', 'present' => true, 'raw' => " \n\t "),
	array('id' => 5, 'label' => 'valid_empty_object', 'present' => true, 'raw' => '{}'),
	array('id' => 6, 'label' => 'valid_empty_list', 'present' => true, 'raw' => '[]'),
	array(
		'id' => 7,
		'label' => 'valid_object_keyed_by_tour_id',
		'present' => true,
		'raw' => '{"tour-alpha":{"completed_version":"3","completed_at":"2026-07-01 09:00:00","last_seen_at":"2026-07-02 10:00:00"}}',
	),
	array(
		'id' => 8,
		'label' => 'valid_list_of_rows',
		'present' => true,
		'raw' => '[{"completed_version":"3","completed_at":"2026-07-01 09:00:00","last_seen_at":"2026-07-02 10:00:00"}]',
	),
	array(
		'id' => 9,
		'label' => 'direct_php_array_object_style',
		'present' => true,
		'raw' => array(
			'tour-alpha' => array(
				'completed_version' => '3',
				'completed_at' => '2026-07-01 09:00:00',
				'last_seen_at' => '2026-07-02 10:00:00',
			),
		),
	),
	array(
		'id' => 10,
		'label' => 'direct_php_array_list_style',
		'present' => true,
		'raw' => array(
			array(
				'completed_version' => '3',
				'completed_at' => '2026-07-01 09:00:00',
				'last_seen_at' => '2026-07-02 10:00:00',
			),
		),
	),
	array('id' => 11, 'label' => 'scalar_string_json', 'present' => true, 'raw' => '"hello"'),
	array('id' => 12, 'label' => 'number', 'present' => true, 'raw' => 123),
	array('id' => 13, 'label' => 'true', 'present' => true, 'raw' => true),
	array('id' => 14, 'label' => 'false', 'present' => true, 'raw' => false),
	array('id' => 15, 'label' => 'json_null', 'present' => true, 'raw' => 'null'),
	array('id' => 16, 'label' => 'malformed_json', 'present' => true, 'raw' => '{"tour-alpha":'),
	array('id' => 17, 'label' => 'truncated_json', 'present' => true, 'raw' => '{"tour-alpha":{"completed_version":"3"'),
	array(
		'id' => 18,
		'label' => 'invalid_utf8',
		'present' => true,
		'raw' => '{"tour-alpha":"' . hex2bin('B1') . '"}',
	),
	array('id' => 19, 'label' => 'excessive_depth', 'present' => true, 'raw' => vms_test_make_deep_json(600)),
	array(
		'id' => 20,
		'label' => 'numeric_key_object',
		'present' => true,
		'raw' => '{"0":{"completed_version":"3","completed_at":"2026-07-01 09:00:00","last_seen_at":"2026-07-02 10:00:00"},"01":{"completed_version":"4","completed_at":"2026-07-03 09:00:00","last_seen_at":"2026-07-04 10:00:00"}}',
	),
	array(
		'id' => 21,
		'label' => 'unknown_tour_id',
		'present' => true,
		'raw' => '{"rogue.tour":{"completed_version":"9","completed_at":"2026-07-01 09:00:00","last_seen_at":"2026-07-02 10:00:00"}}',
	),
	array(
		'id' => 22,
		'label' => 'missing_required_row_fields',
		'present' => true,
		'raw' => '{"tour-alpha":{"other":"value"}}',
	),
	array(
		'id' => 23,
		'label' => 'invalid_completion_value',
		'present' => true,
		'raw' => '{"tour-alpha":{"completed_version":["bad"],"completed_at":"2026-07-01 09:00:00","last_seen_at":"2026-07-02 10:00:00"}}',
	),
	array(
		'id' => 24,
		'label' => 'invalid_progress_value',
		'present' => true,
		'raw' => '{"tour-alpha":{"version_seen":"3","status":["bad"],"step_index":["bad"],"updated_at":"1"}}',
	),
	array(
		'id' => 25,
		'label' => 'unexpected_nested_structures',
		'present' => true,
		'raw' => '{"tour-alpha":{"completed_version":["bad"],"completed_at":["bad"],"last_seen_at":["bad"]}}',
	),
	array(
		'id' => 26,
		'label' => 'duplicate_keys',
		'present' => true,
		'raw' => '{"tour-alpha":{"completed_version":"2","completed_at":"2026-07-01 09:00:00","last_seen_at":"2026-07-02 10:00:00"},"tour-alpha":{"completed_version":"3","completed_at":"2026-07-03 09:00:00","last_seen_at":"2026-07-04 10:00:00"}}',
	),
	array(
		'id' => 27,
		'label' => 'very_large_state_object',
		'present' => true,
		'raw' => wp_json_encode(vms_test_make_large_state(40)),
	),
	array(
		'id' => 28,
		'label' => 'mixed_valid_invalid_rows',
		'present' => true,
		'raw' => array(
			'tour-alpha' => array(
				'completed_version' => '3',
				'completed_at' => '2026-07-01 09:00:00',
				'last_seen_at' => '2026-07-02 10:00:00',
			),
			'tour-beta' => 'bad-row',
			'Bad ID!!' => array(
				'completed_version' => '2',
				'completed_at' => '2026-07-05 09:00:00',
				'last_seen_at' => '2026-07-06 10:00:00',
			),
			'tour-gamma' => array(
				'completed_version' => array('nested'),
				'completed_at' => '2026-07-07 09:00:00',
				'last_seen_at' => '2026-07-08 10:00:00',
			),
			'' => array(
				'completed_version' => '7',
			),
		),
	),
	array(
		'id' => 29,
		'label' => 'alternate_legacy_shape_discovered',
		'present' => true,
		'raw' => '{"tour-alpha":{"version_seen":"3","status":"completed","step_index":1,"updated_at":999}}',
	),
);

$legacyCases = array(
	array('id' => 1, 'label' => 'no_current_no_legacy_seen', 'present' => false),
	array('id' => 2, 'label' => 'no_current_legacy_seen_true', 'present' => false, 'legacy' => array('tour-alpha' => 3)),
	array('id' => 3, 'label' => 'no_current_legacy_seen_false', 'present' => false, 'legacy' => array('tour-alpha' => 0)),
	array('id' => 4, 'label' => 'malformed_current_legacy_seen_true', 'present' => true, 'raw' => '{"tour-alpha":', 'legacy' => array('tour-alpha' => 3)),
	array('id' => 5, 'label' => 'malformed_current_legacy_seen_false', 'present' => true, 'raw' => '{"tour-alpha":', 'legacy' => array('tour-alpha' => 0)),
	array(
		'id' => 6,
		'label' => 'direct_array_current_incomplete_legacy_seen_true',
		'present' => true,
		'raw' => array(
			'tour-alpha' => array(
				'version_seen' => 2,
				'status' => 'in_progress',
				'step_index' => 1,
				'updated_at' => 55,
			),
		),
		'legacy' => array('tour-alpha' => 3),
	),
	array(
		'id' => 7,
		'label' => 'json_current_completed_legacy_seen_false',
		'present' => true,
		'raw' => '{"tour-alpha":{"version_seen":3,"status":"completed","step_index":1,"updated_at":55}}',
		'legacy' => array('tour-alpha' => 0),
	),
	array(
		'id' => 8,
		'label' => 'current_and_multiple_legacy_keys',
		'present' => true,
		'raw' => '{"tour-alpha":{"version_seen":2,"status":"in_progress","step_index":1,"updated_at":55}}',
		'legacy' => array('tour-alpha' => 3, 'tour-beta' => 4),
	),
	array(
		'id' => 9,
		'label' => 'unknown_legacy_key',
		'present' => false,
		'extra_meta' => array('vms_tour_seen_unknown-tour' => 9),
	),
	array(
		'id' => 10,
		'label' => 'legacy_key_malformed_value',
		'present' => false,
		'legacy' => array('tour-alpha' => 'abc'),
	),
);

$currentResults = array_map(
	static function (array $case) use ($storage): array {
		return vms_test_run_case($case, $storage);
	},
	$currentCases
);

$legacyResults = array_map(
	static function (array $case) use ($storage): array {
		return vms_test_run_case($case, $storage);
	},
	$legacyCases
);

foreach ($currentResults as $result) {
	vms_test_assert_same(null, $result['storage_exception'], 'Storage reader should not throw for case ' . $result['label'] . '.');
	vms_test_assert_same(null, $result['core_exception'], 'Legacy reader should not throw for case ' . $result['label'] . '.');
	vms_test_assert_same(null, $result['admin_exception'], 'Admin label renderer should not throw for case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['storage_writes'], 'Storage reader should not write during case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['core_writes'], 'Legacy reader should not write during case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['admin_writes'], 'Admin label renderer should not write during case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['storage_deletes'], 'Storage reader should not delete during case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['core_deletes'], 'Legacy reader should not delete during case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['admin_deletes'], 'Admin label renderer should not delete during case ' . $result['label'] . '.');
}

foreach ($legacyResults as $result) {
	vms_test_assert_same(null, $result['storage_exception'], 'Storage reader should not throw for legacy case ' . $result['label'] . '.');
	vms_test_assert_same(null, $result['core_exception'], 'Legacy reader should not throw for legacy case ' . $result['label'] . '.');
	vms_test_assert_same(null, $result['admin_exception'], 'Admin label renderer should not throw for legacy case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['storage_writes'], 'Storage reader should not write during legacy case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['core_writes'], 'Legacy reader should not write during legacy case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['admin_writes'], 'Admin label renderer should not write during legacy case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['storage_deletes'], 'Storage reader should not delete during legacy case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['core_deletes'], 'Legacy reader should not delete during legacy case ' . $result['label'] . '.');
	vms_test_assert_same(0, $result['admin_deletes'], 'Admin label renderer should not delete during legacy case ' . $result['label'] . '.');
}

$currentById = array();
foreach ($currentResults as $result) {
	$currentById[$result['id']] = $result;
}

$legacyById = array();
foreach ($legacyResults as $result) {
	$legacyById[$result['id']] = $result;
}

vms_test_assert_same(array(), $currentById[1]['storage_state'], 'Missing state should decode to an empty current storage array.');
vms_test_assert_same(array(), $currentById[2]['storage_state'], 'NULL state should decode to an empty current storage array.');
vms_test_assert_same(array(), $currentById[5]['storage_state'], 'Empty JSON object should decode to an empty current storage array.');
vms_test_assert_same(
	array(
		'tour-alpha' => array(
			'completed_version' => '3',
			'completed_at' => '2026-07-01 09:00:00',
			'last_seen_at' => '2026-07-02 10:00:00',
		),
	),
	$currentById[7]['storage_state'],
	'Current storage should preserve valid keyed state rows.'
);
vms_test_assert_same('Completed at version 3 (2026-07-01 09:00:00)', $currentById[7]['admin_label'], 'Valid completed current state should show the exact completed label.');
vms_test_assert_same('suppressed', $currentById[7]['current_runtime_autorun'], 'Valid completed current state should suppress current autorun.');
vms_test_assert_same('show', $currentById[7]['legacy_autostart'], 'Current-format state should not suppress the legacy autostart path.');
vms_test_assert_same(array('0'), $currentById[8]['storage_keys'], 'List-style current state should be retained under numeric keys.');
vms_test_assert_same(array('0'), $currentById[10]['storage_keys'], 'Direct PHP list-style current state should be retained under numeric keys.');
vms_test_assert_same(
	array(
		'tour-alpha' => array(
			'completed_version' => '3',
			'completed_at' => '2026-07-01 09:00:00',
			'last_seen_at' => '2026-07-02 10:00:00',
		),
	),
	$currentById[9]['core_state'],
	'Direct PHP array state should bypass the legacy JSON decode and bridge path.'
);
vms_test_assert_same(array(), $currentById[16]['storage_state'], 'Malformed JSON should fall back to empty current storage state.');
vms_test_assert_same(array(), $currentById[16]['core_state'], 'Malformed JSON should fall back to empty legacy core state.');
vms_test_assert_same(array('0', '01'), $currentById[20]['storage_keys'], 'Numeric-key objects should be retained as numeric string tour IDs.');
vms_test_assert_same(array('rogue.tour'), $currentById[21]['storage_keys'], 'Unknown keyed rows should be retained by current storage.');
vms_test_assert_same(
	array(
		'tour-alpha' => array(
			'completed_version' => '',
			'completed_at' => '',
			'last_seen_at' => '',
		),
	),
	$currentById[22]['storage_state'],
	'Missing current-format row fields should collapse to empty strings.'
);
vms_test_assert_same(array('Array to string conversion'), $currentById[23]['storage_warnings'], 'Invalid completed_version arrays should emit the expected storage warning.');
vms_test_assert_same('Completed older version Array (current 3)', $currentById[23]['admin_label'], 'Malformed completed_version arrays should only affect admin labeling with the stringified value.');
vms_test_assert_same('show', $currentById[23]['current_runtime_autorun'], 'Malformed completed_version arrays should re-show current tours instead of suppressing them.');
vms_test_assert_same(
	array(
		'tour-alpha' => array(
			'completed_version' => '',
			'completed_at' => '',
			'last_seen_at' => '',
		),
	),
	$currentById[24]['storage_state'],
	'Legacy-format progress rows should not populate current storage completion fields.'
);
vms_test_assert_same('show', $currentById[24]['legacy_autostart'], 'Invalid legacy status arrays should not suppress legacy autostart.');
vms_test_assert_same(array('status=array;step=array'), array($currentById[24]['legacy_progress']), 'Legacy progress summaries should expose malformed array progress inputs without mutation.');
vms_test_assert_same(
	array(
		'Array to string conversion',
		'Array to string conversion',
		'Array to string conversion',
	),
	$currentById[25]['storage_warnings'],
	'Nested array field values should emit one storage warning per string-cast field.'
);
vms_test_assert_same(array('tour-alpha'), $currentById[26]['storage_keys'], 'Duplicate JSON object keys should collapse to the last keyed row.');
vms_test_assert_same('3', $currentById[26]['storage_state']['tour-alpha']['completed_version'], 'Duplicate JSON keys should retain the last completed_version.');
vms_test_assert_same(40, count($currentById[27]['storage_keys']), 'Large current state payloads should retain every keyed row.');
vms_test_assert_same(true, in_array('badid', $currentById[28]['storage_keys'], true), 'Sanitized current-state IDs should be retained when non-empty.');
vms_test_assert_same(true, in_array('tour-gamma', $currentById[28]['storage_keys'], true), 'Mixed current-state payloads should keep array rows even when nested values warn.');
vms_test_assert_same(array('Array to string conversion'), $currentById[28]['storage_warnings'], 'Mixed current-state payloads should emit only the nested-value warning.');
vms_test_assert_same('Not completed', $currentById[29]['admin_label'], 'Legacy-format JSON rows should not appear as completed in the current admin label consumer.');
vms_test_assert_same('suppressed', $currentById[29]['legacy_autostart'], 'Legacy-format JSON rows should still suppress the legacy autostart path.');

vms_test_assert_same(array(), $legacyById[1]['core_state'], 'No current state and no legacy seen keys should return an empty legacy state.');
vms_test_assert_same(
	array(
		'tour-alpha' => array(
			'version_seen' => 3,
			'status' => 'completed',
			'step_index' => 0,
			'updated_at' => '<dynamic-int>',
		),
	),
	$legacyById[2]['core_state'],
	'Legacy seen keys should synthesize completed legacy state when current state is absent.'
);
vms_test_assert_same('suppressed', $legacyById[2]['legacy_autostart'], 'Synthesized legacy completion should suppress the legacy autostart path.');
vms_test_assert_same('Not completed', $legacyById[2]['admin_label'], 'Current admin labels should not consult legacy seen-key synthesis.');
vms_test_assert_same(array(), $legacyById[3]['core_state'], 'Falsy legacy seen values should not synthesize legacy state.');
vms_test_assert_same(
	array(
		'tour-alpha' => array(
			'version_seen' => 3,
			'status' => 'completed',
			'step_index' => 0,
			'updated_at' => '<dynamic-int>',
		),
	),
	$legacyById[4]['core_state'],
	'Malformed current JSON should still allow legacy seen-key synthesis.'
);
vms_test_assert_same(array(), $legacyById[5]['core_state'], 'Malformed current JSON without a positive legacy seen key should remain empty.');
vms_test_assert_same(
	array(
		'tour-alpha' => array(
			'version_seen' => 2,
			'status' => 'in_progress',
			'step_index' => 1,
			'updated_at' => '<dynamic-int>',
		),
	),
	$legacyById[6]['core_state'],
	'Direct PHP array current state should bypass legacy seen-key synthesis entirely.'
);
vms_test_assert_same('show', $legacyById[6]['legacy_autostart'], 'Direct-array incomplete legacy state should continue to show the legacy tour.');
vms_test_assert_same('suppressed', $legacyById[7]['legacy_autostart'], 'Completed legacy-format current state should suppress the legacy autostart path.');
vms_test_assert_same(
	array(
		'tour-alpha' => array(
			'version_seen' => 3,
			'status' => 'completed',
			'step_index' => 0,
			'updated_at' => '<dynamic-int>',
		),
		'tour-beta' => array(
			'version_seen' => 4,
			'status' => 'completed',
			'step_index' => 0,
			'updated_at' => '<dynamic-int>',
		),
	),
	$legacyById[8]['core_state'],
	'Multiple positive legacy seen keys should override smaller current versions and add missing tours.'
);
vms_test_assert_same(array(), $legacyById[9]['core_state'], 'Unknown legacy seen keys outside the registry should be ignored.');
vms_test_assert_same(array(), $legacyById[10]['core_state'], 'Malformed non-numeric legacy seen values should not synthesize completion.');

vms_test_reset_environment();
vms_test_set_user_state_raw(
	VMS_TOURS_TEST_OTHER_USER_ID,
	array(
		'tour-alpha' => array(
			'completed_version' => '3',
			'completed_at' => '2026-07-01 09:00:00',
			'last_seen_at' => '2026-07-02 10:00:00',
		),
	),
	true
);
$otherUserAdmin = vms_test_render_admin_status_label($storage);
vms_test_assert_same('Not completed', $otherUserAdmin['label'], 'Admin labels should not expose another user\'s tour state.');
vms_test_assert_same(0, $otherUserAdmin['capability_reads'], 'Registry-table label rendering should not derive capability decisions from completion state.');
vms_test_assert_same(0, $otherUserAdmin['writes'], 'Registry-table label rendering should not mutate user meta.');
vms_test_assert_same(0, $otherUserAdmin['deletes'], 'Registry-table label rendering should not delete user meta.');

$report = array(
	'source_assertions' => array(
		'storage_json_decode_count' => substr_count($storageSource, 'json_decode('),
		'core_json_decode_count' => substr_count($coreSource, 'json_decode('),
		'storage_key' => BVMGR_Tours_Storage::USER_META_STATE,
		'legacy_bridge_present' => true,
		'admin_uses_storage_helper' => true,
		'current_runtime_uses_storage_payload' => true,
		'current_runtime_autorun_condition' => 'completed_version === tour.version',
		'legacy_autostart_condition' => 'version_seen === tour.version && status in [completed,dismissed]',
		'current_runtime_progress_suppression' => 'none; current runtime only checks completed_version',
	),
	'parity' => array(
		'mirror_storage_sha256' => hash('sha256', $storageSource),
		'live_storage_sha256' => hash('sha256', $liveStorageSource),
		'mirror_core_sha256' => hash('sha256', $coreSource),
		'live_core_sha256' => hash('sha256', $liveCoreSource),
		'mirror_runtime_js_sha256' => hash('sha256', $runtimeJsSource),
		'live_runtime_js_sha256' => hash('sha256', $liveRuntimeJsSource),
		'mirror_legacy_js_sha256' => hash('sha256', $legacyJsSource),
		'live_legacy_js_sha256' => hash('sha256', $liveLegacyJsSource),
	),
	'current_state_matrix' => $currentResults,
	'legacy_bridge_matrix' => $legacyResults,
	'admin_consumer_checks' => array(
		'valid_completed_label' => $currentById[7]['admin_label'],
		'valid_incomplete_label' => $currentById[22]['admin_label'],
		'malformed_completed_label' => $currentById[23]['admin_label'],
		'legacy_seen_only_label' => $legacyById[2]['admin_label'],
		'other_user_label' => $otherUserAdmin['label'],
		'capability_reads' => $otherUserAdmin['capability_reads'],
		'writes' => $otherUserAdmin['writes'],
		'deletes' => $otherUserAdmin['deletes'],
	),
	'risk_summary' => array(
		'current_runtime_malformed_effect' => 're-show only unless admin label stringifies malformed completed_version as Array',
		'legacy_runtime_malformed_effect' => 're-show or lose progress visibility only',
		'mutation_on_read' => false,
		'cross_user_exposure' => false,
		'privileged_side_effect_on_read' => false,
	),
);

fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDOUT, "tours user-state json characterization: PASS\n");
