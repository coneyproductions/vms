<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS', 86400);

final class G10_WPDB_Spy
{
	public string $prefix = 'wp_';
	/** @var array<int,array{template:string,args:array<int,mixed>,sql:string}> */
	public array $prepares = array();
	/** @var array<int,array{sql:string,result:mixed}> */
	public array $get_var_calls = array();
	/** @var array<int,array{sql:string,output:mixed,result:mixed}> */
	public array $get_results_calls = array();
	/** @var array<int,mixed> */
	public array $get_var_queue = array();
	/** @var array<int,mixed> */
	public array $get_results_queue = array();

	public function prepare(string $template, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}

		preg_match_all('/(?<!%)%(?:\d+\$)?[sdfi]/', $template, $matches);
		if (count($matches[0]) !== count($args)) {
			throw new RuntimeException('Prepared-query placeholder mismatch: ' . $template);
		}

		$index = 0;
		$sql = preg_replace_callback(
			'/(?<!%)%(?:\d+\$)?[sdfi]/',
			static function (array $match) use (&$index, $args): string {
				$value = $args[$index++];
				$type = substr($match[0], -1);
				if ($type === 'd') {
					return (string) (int) $value;
				}
				if ($type === 'f') {
					return (string) (float) $value;
				}
				if ($type === 'i') {
					return chr(96) . str_replace(chr(96), chr(96) . chr(96), (string) $value) . chr(96);
				}
				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$template
		);
		if (!is_string($sql) || $index !== count($args)) {
			throw new RuntimeException('Unable to render prepared query.');
		}

		$this->prepares[] = array('template' => $template, 'args' => $args, 'sql' => $sql);
		return $sql;
	}

	public function get_var(string $sql)
	{
		$result = $this->get_var_queue === array() ? false : array_shift($this->get_var_queue);
		$this->get_var_calls[] = array('sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_results(string $sql, $output = ARRAY_A)
	{
		$result = $this->get_results_queue === array() ? array() : array_shift($this->get_results_queue);
		$this->get_results_calls[] = array('sql' => $sql, 'output' => $output, 'result' => $result);
		return $result;
	}
}

final class G10_Product
{
	private int $sales;
	private float $price;

	public function __construct(int $sales, float $price)
	{
		$this->sales = $sales;
		$this->price = $price;
	}

	public function get_total_sales(): int
	{
		return $this->sales;
	}

	public function get_price(): float
	{
		return $this->price;
	}
}

final class WP_Query
{
	/** @var mixed */
	public $posts;
	public int $max_num_pages;

	public function __construct(array $args)
	{
		$entry = $GLOBALS['g10_wp_query_queue'] === array()
			? array('posts' => array(), 'max_num_pages' => 0)
			: array_shift($GLOBALS['g10_wp_query_queue']);
		$this->posts = $entry['posts'] ?? array();
		$this->max_num_pages = (int) ($entry['max_num_pages'] ?? 0);
		$GLOBALS['g10_wp_query_calls'][] = array(
			'args' => $args,
			'posts' => $this->posts,
			'max_num_pages' => $this->max_num_pages,
		);
	}
}

function g10_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g10_same($expected, $actual, string $message): void
{
	g10_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function g10_contains(string $needle, string $haystack, string $message): void
{
	g10_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function g10_ends_with(string $haystack, string $needle): bool
{
	return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
}

function g10_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to find function ' . $name . '.');
	}

	$depth = 1;
	for ($index = $brace + 1, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '{' ? 1 : 0;
		$depth -= $source[$index] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($index - $start) + 1);
		}
	}

	throw new RuntimeException('Unable to parse function ' . $name . '.');
}

/**
 * @param string[] $function_names
 */
function g10_projection(string $source, array $function_names): string
{
	foreach ($function_names as $function_name) {
		$source = str_replace(
			g10_extract_function($source, $function_name),
			'/* owned function: ' . $function_name . ' */',
			$source
		);
	}
	return $source;
}

/**
 * @param array<int,array{marker:string,codes:array<int,string>}> $specs
 * @return array{source:string,comments:int,codes:int}
 */
function g10_strip_owned_annotations(string $source, array $specs, string $label): array
{
	$comments = 0;
	$codes = 0;
	foreach ($specs as $spec) {
		g10_same(1, substr_count($source, $spec['marker']), $label . ' must contain each exact owned annotation once.');
		$count = 0;
		$source = str_replace($spec['marker'], '', $source, $count);
		g10_same(1, $count, $label . ' must strip each exact owned annotation once.');
		$comments += $count;
		$codes += count($spec['codes']);
	}
	return array('source' => $source, 'comments' => $comments, 'codes' => $codes);
}

/** @return array{source:string,replacements:int,rows:int} */
function g10_project_g15_monitor_dates(string $source, string $label): array
{
	$specs = array(
		array(
			'current' => "\treturn wp_date('Y-m-d g:i a', \$timestamp, wp_timezone());",
			'historical' => "\tif (function_exists('wp_date')) {\n\t\treturn wp_date('Y-m-d g:i a', \$timestamp, wp_timezone());\n\t}\n\n\treturn date('Y-m-d g:i a', \$timestamp);",
			'rows' => 1,
		),
		array(
			'current' => "\t\$tz = wp_timezone();\n\t\$start_date = wp_date('Y-m-d', \$now, \$tz);\n\t\$end_date = wp_date('Y-m-d', \$cutoff, \$tz);",
			'historical' => "\t\$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');\n\t\$start_date = function_exists('wp_date') ? wp_date('Y-m-d', \$now, \$tz) : date('Y-m-d', \$now);\n\t\$end_date = function_exists('wp_date') ? wp_date('Y-m-d', \$cutoff, \$tz) : date('Y-m-d', \$cutoff);",
			'rows' => 2,
		),
	);
	$replacements = 0;
	$rows = 0;
	foreach ($specs as $spec) {
		g10_same(1, substr_count($source, $spec['current']), $label . ' must contain each exact G15 date replacement once.');
		$count = 0;
		$source = str_replace($spec['current'], $spec['historical'], $source, $count);
		g10_same(1, $count, $label . ' must project each exact G15 date replacement once.');
		$replacements += $count;
		$rows += $spec['rows'];
	}
	return array('source' => $source, 'replacements' => $replacements, 'rows' => $rows);
}

function g10_project_g16_monitor_logging(string $source, string $label): string
{
	$start = strpos($source, 'function vms_ticket_integrity_fatal_operation(');
	$last = g10_extract_function($source, 'vms_ticket_integrity_fatal_operational_context');
	$last_start = strpos($source, $last, (int) $start);
	g10_assert($start !== false && $last_start !== false, $label . ' G16 helper bounds changed.');
	$block = substr($source, (int) $start, (int) $last_start - (int) $start + strlen($last));
	g10_same('136b427e6633803250e472bc8416a419dd19f3160906b5b049dd169312c146f6', hash('sha256', $block), $label . ' G16 helper block changed.');
	$source = str_replace($block . "\n\n", '', $source, $count);
	g10_same(1, $count, $label . ' G16 helper removal count changed.');
	$current = g10_extract_function($source, 'vms_ticket_integrity_fatal_guard_shutdown');
	g10_same('3080ee643e6b24b893d7d212b6ea001c5d2bc95940e45522f7064e2470e94f8f', hash('sha256', $current), $label . ' G16 shutdown contract changed.');
	$fixture = (string) file_get_contents(__DIR__ . '/g16-operational-logging-group-c.php');
	g10_same(1, preg_match('/\$g16c_ticket_shutdown_historical = \'([^\']+)\'/s', $fixture, $match), $label . ' G16 historical shutdown fixture changed.');
	$historical = base64_decode($match[1], true);
	g10_assert(is_string($historical) && $historical !== '', $label . ' G16 historical shutdown decode failed.');
	return str_replace($current, $historical, $source, $count);
}

/**
 * @param array<string,string> $function_sources
 * @param array<string,array<int,string>> $expected
 * @return string[]
 */
function g10_db_annotation_errors(array $function_sources, array $expected): array
{
	$errors = array();
	$actual = array();
	foreach ($function_sources as $function_key => $source) {
		$lines = preg_split('/\R/', $source);
		if (!is_array($lines)) {
			$errors[] = 'Unable to split owned function: ' . $function_key;
			continue;
		}
		foreach ($lines as $line_number => $line) {
			if (
				strpos($line, 'phpcs:') === false
				|| preg_match('/(?:WordPress\.DB|PluginCheck\.Security\.DirectDB)/i', $line) !== 1
			) {
				continue;
			}
			if (
				preg_match(
					'/phpcs:([a-z]+)(?:\s+([A-Za-z0-9_.]+(?:,[A-Za-z0-9_.]+)*))?(?:\s+--|$)/i',
					$line,
					$matches
				) !== 1
			) {
				$errors[] = $function_key . ':' . ($line_number + 1) . ' has an unparseable DB directive.';
				continue;
			}
			$directive = 'phpcs:' . strtolower($matches[1]);
			if (isset($matches[2]) && $matches[2] !== '') {
				$directive .= ' ' . $matches[2];
			}
			$actual[$function_key][] = $directive;
		}
	}

	foreach ($actual as &$directives) {
		sort($directives);
	}
	unset($directives);
	foreach ($expected as &$directives) {
		sort($directives);
	}
	unset($directives);
	ksort($actual);
	ksort($expected);
	if ($actual !== $expected) {
		$errors[] = 'Owned DB directives differ from the exact expected function/directive inventory.';
	}
	return $errors;
}

function g10_normalize_sql(string $sql): string
{
	$normalized = preg_replace('/\s+/', ' ', trim($sql));
	return is_string($normalized) ? $normalized : '';
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	$value = is_scalar($value) ? strtolower((string) $value) : '';
	$clean = preg_replace('/[^a-z0-9_\-]/', '', $value);
	return is_string($clean) ? $clean : '';
}

function __($text, $domain = ''): string
{
	unset($domain);
	return (string) $text;
}

function apply_filters(string $hook_name, $value)
{
	if ($hook_name === 'vms_ticket_integrity_target_query_batch_size') {
		return $GLOBALS['g10_batch_size'];
	}
	if ($hook_name === 'vms_ticket_integrity_daily_report_statuses') {
		return $GLOBALS['g10_report_statuses'];
	}
	return $value;
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('UTC');
}

function wp_date(string $format, int $timestamp, ?DateTimeZone $timezone = null): string
{
	unset($timezone);
	return gmdate($format, $timestamp);
}

function current_time(string $format): string
{
	return gmdate($format);
}

function vms_ticket_integrity_get_settings(): array
{
	return array('days_ahead' => $GLOBALS['g10_days_ahead']);
}

function bvmgr_ticketing_b_meta_key(string $name, string $fallback): string
{
	unset($name);
	return $fallback;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	return $GLOBALS['g10_meta'][$post_id][$key] ?? ($single ? '' : array());
}

function vms_ticket_integrity_parse_wp_datetime(string $value): int
{
	$timestamp = strtotime($value . ' UTC');
	return $timestamp === false ? 0 : $timestamp;
}

function vms_ticket_integrity_event_timestamp(int $plan_id, int $tec_event_id): int
{
	unset($plan_id, $tec_event_id);
	return 0;
}

function bvmgr_tec_is_cancelled_event(int $tec_event_id): bool
{
	return !empty($GLOBALS['g10_cancelled'][$tec_event_id]);
}

function vms_ticket_integrity_plan_uses_ticketing(int $plan_id, int $tec_event_id): bool
{
	unset($tec_event_id);
	return !empty($GLOBALS['g10_uses_ticketing'][$plan_id]);
}

function get_the_title(int $post_id): string
{
	return $GLOBALS['g10_titles'][$post_id] ?? ('Post ' . $post_id);
}

function wp_reset_postdata(): void
{
	$GLOBALS['g10_reset_postdata_calls']++;
}

function vms_add_dispatch_event_meta_key(string $name, string $fallback): string
{
	unset($name);
	return $fallback;
}

function get_posts(array $args)
{
	$result = $GLOBALS['g10_get_posts_queue'] === array() ? array() : array_shift($GLOBALS['g10_get_posts_queue']);
	$GLOBALS['g10_get_posts_calls'][] = array('args' => $args, 'result' => $result);
	return $result;
}

function vms_add_dispatch_get_event_plan_context(int $event_plan_id): ?array
{
	$context = $GLOBALS['g10_add_contexts'][$event_plan_id] ?? null;
	return is_array($context) ? $context : null;
}

function vms_add_dispatch_context_exclusion_reason(?array $context, array $options = array()): string
{
	unset($options);
	return is_array($context) ? (string) ($context['exclude_reason'] ?? '') : 'Invalid Event Plan';
}

function vms_add_dispatch_context_vendor_need_rows(array $context, bool $eligible_only = true): array
{
	unset($eligible_only);
	return (array) ($context['rows'] ?? array());
}

function bvmgr_event_plan_perf_log(string $name, int $object_id, array $context): void
{
	$GLOBALS['g10_perf_logs'][] = compact('name', 'object_id', 'context');
}

function vms_ticket_integrity_queue_spot_scan(int $object_id, string $reason): void
{
	$GLOBALS['g10_spot_scans'][] = compact('object_id', 'reason');
}

function vms_ticketing_v2_k(string $name): string
{
	return '_vms_v2_' . $name;
}

function get_post_type(int $post_id): string
{
	return $GLOBALS['g10_post_types'][$post_id] ?? '';
}

function vms_ticketing_is_woo_active(): bool
{
	return $GLOBALS['g10_woo_active'];
}

function wc_get_product(int $product_id)
{
	return $GLOBALS['g10_products'][$product_id] ?? null;
}

function g10_reset_runtime(G10_WPDB_Spy $db): void
{
	$db->prepares = array();
	$db->get_var_calls = array();
	$db->get_results_calls = array();
	$db->get_var_queue = array();
	$db->get_results_queue = array();
	$GLOBALS['g10_wp_query_calls'] = array();
	$GLOBALS['g10_wp_query_queue'] = array();
	$GLOBALS['g10_get_posts_calls'] = array();
	$GLOBALS['g10_get_posts_queue'] = array();
	$GLOBALS['g10_reset_postdata_calls'] = 0;
	$GLOBALS['g10_meta'] = array();
	$GLOBALS['g10_cancelled'] = array();
	$GLOBALS['g10_uses_ticketing'] = array();
	$GLOBALS['g10_titles'] = array();
	$GLOBALS['g10_add_contexts'] = array();
	$GLOBALS['g10_perf_logs'] = array();
	$GLOBALS['g10_spot_scans'] = array();
	$GLOBALS['g10_post_types'] = array();
	$GLOBALS['g10_products'] = array();
	$GLOBALS['g10_batch_size'] = 100;
	$GLOBALS['g10_days_ahead'] = 30;
	$GLOBALS['g10_report_statuses'] = array('wc-completed');
	$GLOBALS['g10_woo_active'] = true;
}

$root = dirname(__DIR__);
$shadow_root = dirname($root, 2) . '/vms';
$paths = array(
	'daily' => 'includes/ticketing/ticket-integrity-daily-report.php',
	'monitor' => 'includes/ticketing/ticket-integrity-monitor.php',
	'cron' => 'includes/ticketing/ticket-integrity-cron.php',
	'add' => 'includes/modules/availability-date-dispatch/helpers.php',
);
$mirror_sources = array();
$shadow_sources = array();
foreach ($paths as $key => $path) {
	g10_assert(is_file($root . '/' . $path), 'Missing mirror source: ' . $path);
	g10_assert(is_file($shadow_root . '/' . $path), 'Missing shadow source: ' . $path);
	$mirror_sources[$key] = (string) file_get_contents($root . '/' . $path);
	$shadow_sources[$key] = (string) file_get_contents($shadow_root . '/' . $path);
	g10_assert($mirror_sources[$key] !== '' && $shadow_sources[$key] !== '', 'Owned sources must be readable: ' . $path);
}

$u_code = 'PluginCheck.Security.DirectDB.UnescapedDBParameter';
$d_code = 'WordPress.DB.DirectDatabaseQuery.DirectQuery';
$n_code = 'WordPress.DB.DirectDatabaseQuery.NoCaching';
$p_code = 'WordPress.DB.PreparedSQL.NotPrepared';
$k_code = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_key';
$q_code = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query';

$artifact_rows = array(
	array('file' => $paths['daily'], 'line' => 455, 'column' => 13, 'code' => $d_code),
	array('file' => $paths['daily'], 'line' => 455, 'column' => 13, 'code' => $n_code),
	array('file' => $paths['daily'], 'line' => 527, 'column' => 13, 'code' => $d_code),
	array('file' => $paths['daily'], 'line' => 527, 'column' => 13, 'code' => $n_code),
	array('file' => $paths['daily'], 'line' => 527, 'column' => 17, 'code' => $u_code),
	array('file' => $paths['daily'], 'line' => 527, 'column' => 44, 'code' => $p_code),
	array('file' => $paths['monitor'], 'line' => 765, 'column' => 17, 'code' => $k_code),
	array('file' => $paths['monitor'], 'line' => 769, 'column' => 17, 'code' => $q_code),
	array('file' => $paths['cron'], 'line' => 695, 'column' => 17, 'code' => $k_code),
	array('file' => $paths['add'], 'line' => 453, 'column' => 13, 'code' => $k_code),
	array('file' => $paths['add'], 'line' => 457, 'column' => 29, 'code' => $q_code),
);
g10_same(11, count($artifact_rows), 'Wave 4 G10 integrity ownership must remain exactly 11 rows.');
$artifact_counts = array_count_values(array_column($artifact_rows, 'code'));
ksort($artifact_counts);
$expected_counts = array($u_code => 1, $d_code => 2, $n_code => 2, $p_code => 1, $k_code => 3, $q_code => 2);
ksort($expected_counts);
g10_same($expected_counts, $artifact_counts, 'Artifact rule split must remain U1/D2/N2/P1/K3/Q2.');

$artifact_path = '/tmp/wporg-wave4-integrated.nTzezu/plugin-check/plugin-check.strict.json';
if (is_file($artifact_path)) {
	g10_same(
		'278819f58c585c226824fd89d541fc5ab107c11897240e281683fa6abad8d179',
		hash_file('sha256', $artifact_path),
		'Authoritative Wave 4 strict JSON hash changed.'
	);
	$decoded = json_decode((string) file_get_contents($artifact_path), true);
	g10_assert(is_array($decoded), 'Authoritative Wave 4 strict JSON should decode.');
	$actual_rows = array();
	foreach ($decoded as $row) {
		if (!is_array($row) || !in_array((string) ($row['code'] ?? ''), array_keys($expected_counts), true)) {
			continue;
		}
		foreach ($paths as $path) {
			if (g10_ends_with((string) ($row['file'] ?? ''), $path)) {
				$actual_rows[] = array(
					'file' => $path,
					'line' => (int) ($row['line'] ?? 0),
					'column' => (int) ($row['column'] ?? 0),
					'code' => (string) ($row['code'] ?? ''),
				);
				break;
			}
		}
	}
	$signature = static function (array $row): string {
		return $row['file'] . ':' . $row['line'] . ':' . $row['column'] . ':' . $row['code'];
	};
	$expected_signatures = array_map($signature, $artifact_rows);
	$actual_signatures = array_map($signature, $actual_rows);
	sort($expected_signatures);
	sort($actual_signatures);
	g10_same($expected_signatures, $actual_signatures, 'Strict JSON target rows must equal the embedded 11-row inventory.');
}

$annotation_specs = array(
	'daily' => array(
		array(
			'function' => 'vms_ticket_integrity_report_table_exists',
			'codes' => array($d_code, $n_code),
			'marker' => "\t// phpcs:ignore {$d_code},{$n_code} -- Schema readiness performs a prepared exact-name probe for each of two WooCommerce lookup tables; the result must reflect current schema availability.\n",
			'fragment' => "\t// phpcs:ignore {$d_code},{$n_code} -- Schema readiness performs a prepared exact-name probe for each of two WooCommerce lookup tables; the result must reflect current schema availability.\n\treturn (\$wpdb->get_var(\$wpdb->prepare('SHOW TABLES LIKE %s', \$table_name)) === \$table_name);",
		),
		array(
			'function' => 'vms_ticket_integrity_report_lookup_metrics',
			'codes' => array($u_code, $p_code, $d_code, $n_code),
			'marker' => "\t// phpcs:ignore {$u_code},{$p_code},{$d_code},{$n_code} -- The daily report prepares both table identifiers and every product/status value before one request-fresh aggregate; no WooCommerce API exposes this grouped metric contract.\n",
			'fragment' => "\t// phpcs:ignore {$u_code},{$p_code},{$d_code},{$n_code} -- The daily report prepares both table identifiers and every product/status value before one request-fresh aggregate; no WooCommerce API exposes this grouped metric contract.\n\t\$rows = \$wpdb->get_results(\$wpdb->prepare(\$sql, \$args), ARRAY_A);",
		),
	),
	'monitor' => array(
		array(
			'function' => 'vms_ticket_integrity_build_targets',
			'codes' => array($k_code),
			'marker' => " // phpcs:ignore {$k_code} -- Ticket Integrity intentionally orders each published Event Plan batch by canonical event-date metadata across the configured date window.",
			'fragment' => "'meta_key' => '_vms_event_date', // phpcs:ignore {$k_code} -- Ticket Integrity intentionally orders each published Event Plan batch by canonical event-date metadata across the configured date window.",
		),
		array(
			'function' => 'vms_ticket_integrity_build_targets',
			'codes' => array($q_code),
			'marker' => " // phpcs:ignore {$q_code} -- Ticket Integrity intentionally paginates the complete published, linked Event Plan set inside the configured date window before applying ticketing and activity checks.",
			'fragment' => "'meta_query' => array( // phpcs:ignore {$q_code} -- Ticket Integrity intentionally paginates the complete published, linked Event Plan set inside the configured date window before applying ticketing and activity checks.",
		),
	),
	'cron' => array(
		array(
			'function' => 'vms_ticket_integrity_watch_ticketing_meta',
			'codes' => array($k_code),
			'marker' => " // phpcs:ignore {$k_code} -- This diagnostic payload field records the exact watched metadata key; it is not a WordPress query argument.",
			'fragment' => "'meta_key' => \$meta_key, // phpcs:ignore {$k_code} -- This diagnostic payload field records the exact watched metadata key; it is not a WordPress query argument.",
		),
	),
	'add' => array(
		array(
			'function' => 'vms_add_dispatch_get_event_plan_need_scan',
			'codes' => array($k_code),
			'marker' => " // phpcs:ignore {$k_code} -- ADD intentionally orders its bounded 50-to-300 Event Plan candidate sample by canonical event-date metadata before selecting open vendor needs.",
			'fragment' => "'meta_key' => \$date_key, // phpcs:ignore {$k_code} -- ADD intentionally orders its bounded 50-to-300 Event Plan candidate sample by canonical event-date metadata before selecting open vendor needs.",
		),
		array(
			'function' => 'vms_add_dispatch_get_event_plan_need_scan',
			'codes' => array($q_code),
			'marker' => " // phpcs:ignore {$q_code} -- ADD applies the canonical event-date lower bound only to the same bounded 50-to-300 candidate sample when past events are excluded.",
			'fragment' => "\$candidate_args['meta_query'] = array( // phpcs:ignore {$q_code} -- ADD applies the canonical event-date lower bound only to the same bounded 50-to-300 candidate sample when past events are excluded.",
		),
	),
);

$function_sources = array();
$expected_directives = array();
foreach ($annotation_specs as $source_key => $specs) {
	foreach ($specs as $spec) {
		$function_key = $source_key . '::' . $spec['function'];
		if (!isset($function_sources[$function_key])) {
			$function_sources[$function_key] = g10_extract_function($mirror_sources[$source_key], $spec['function']);
		}
		g10_contains($spec['fragment'], $function_sources[$function_key], 'Annotation must remain attached to its exact owned anchor.');
		$expected_directives[$function_key][] = 'phpcs:ignore ' . implode(',', $spec['codes']);
	}
}
g10_same(array(), g10_db_annotation_errors($function_sources, $expected_directives), 'Owned DB directives must equal the exact seven-comment inventory.');

$negative_controls = array(
	'disable' => '// phpcs:disable WordPress.DB',
	'enable' => '// phpcs:enable WordPress.DB',
	'ignoreFile' => '// phpcs:ignoreFile WordPress.DB',
	'DB category' => '// phpcs:ignore WordPress.DB -- forbidden category',
	'slow-query family' => '// phpcs:ignore WordPress.DB.SlowDBQuery -- forbidden family',
	'direct-query family' => '// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- forbidden family',
	'prepared-SQL family' => '// phpcs:ignore WordPress.DB.PreparedSQL -- forbidden family',
	'Plugin Check family' => '// phpcs:ignore PluginCheck.Security.DirectDB -- forbidden family',
	'unowned exact code' => '// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- forbidden code',
	'mixed extra code' => '// phpcs:ignore ' . $k_code . ',' . $q_code . ' -- forbidden mixed occurrence',
);
foreach ($negative_controls as $label => $annotation) {
	$mutated_functions = $function_sources;
	$mutated_functions['cron::vms_ticket_integrity_watch_ticketing_meta'] .= "\n" . $annotation;
	g10_assert(
		g10_db_annotation_errors($mutated_functions, $expected_directives) !== array(),
		'DB annotation audit must reject negative control: ' . $label
	);
}

$whole_hashes = array(
	'mirror' => array(
		'daily' => '5e87a6ac6ddf0d8eae830d1ec1f045f4ac7cf39ad2b3f6fa4f10943d04eb0da8',
		'monitor' => 'c3530db3d1c3e0ff84eff6cb8088e2b9fb918d8f731897c2b0308de7e6845785',
		'cron' => 'a38c00a0170f4790dc55b96ff77e0adf0f3c03463ea0bef33146c393b7e15428',
		'add' => '2c688a6e78da3305c601e1e13d749d39dbc0501f6eaac3eca1696ec4d1806436',
	),
	'shadow' => array(
		'daily' => '5e87a6ac6ddf0d8eae830d1ec1f045f4ac7cf39ad2b3f6fa4f10943d04eb0da8',
		'monitor' => '2e31519133ece34e30e25ca502b4c0658d37bd000e0d389f2e273fb33b57f378',
		'cron' => '286c7f48a9ae1e016c1caa832b5f9a120cf06d10436ac48b50a278922130f930',
		'add' => '3bc4a9550e6e6e3c4a345a72c919ade3a780674b444949f9f4c2066e94966c5b',
	),
);
$stripped_hashes = array(
	'mirror' => array(
		'daily' => 'f611ac14a1211f8aaf0df7acce1475a581f052aa98b91dc5801e52d8875ba9d0',
		'monitor' => '27770ef0be288290a7f7d5e5e7a92ee27e93f79e55d9f95d29637671415dcdfc',
		'cron' => '40838be08010dcc82efb4551771ab2ef596ea5f0798b71e0cb21360f5d4aa51e',
		'add' => '4e5d616426fb5911068e21d36e10e781e996a5ce59211056ff96947eb6748c5d',
	),
	'shadow' => array(
		'daily' => 'f611ac14a1211f8aaf0df7acce1475a581f052aa98b91dc5801e52d8875ba9d0',
		'monitor' => '066eeaf16b910c930d4ad23eeca2b48669dbc889713d62dafcc80a7c58848122',
		'cron' => '435ab3f23502a451491ac4a4fc36455f42a94721ef948be5bfa6227f38687c2d',
		'add' => 'a976cd43939d93419636eaf5003267aba7ac8ffe9baec4b4057fbe90098a3a0b',
	),
);
$projection_functions = array(
	'daily' => array('vms_ticket_integrity_report_table_exists', 'vms_ticket_integrity_report_lookup_metrics'),
	'monitor' => array('vms_ticket_integrity_build_targets'),
	'cron' => array('vms_ticket_integrity_watch_ticketing_meta'),
	'add' => array('vms_add_dispatch_get_event_plan_need_scan'),
);
$projection_hashes = array(
	'mirror' => array(
		'daily' => '7aaedd98b4d057899e277832fd126e7098950b4a6acc2bd46fbc414b3cf0a67d',
		'monitor' => 'a7e5938c2f7cbaef9ccf627b66eb577ee67598c16d482a18488021d1598195b7',
		'cron' => '2d8a48ef4461bc2e5ce7b5fe7899aa11296c102d294b1b887f39c0a6577230fa',
		'add' => 'f5f2f5400f44e956ec9a7ccd9cb09e66e80fbefe4d4bfa59223912660537c78f',
	),
	'shadow' => array(
		'daily' => '7aaedd98b4d057899e277832fd126e7098950b4a6acc2bd46fbc414b3cf0a67d',
		'monitor' => 'a7e5938c2f7cbaef9ccf627b66eb577ee67598c16d482a18488021d1598195b7',
		'cron' => '12f56e9b20152972b2937b3d9d83d8692b5c21f3cc62cd939f108b5425846045',
		'add' => '0e902836fd8052b61ddf2492aca61a192fa6ec7e7d90c54f51601d739da98cf5',
	),
);

$stripped_sources = array('mirror' => array(), 'shadow' => array());
$total_comments = 0;
$total_codes = 0;
$g15_date_projection_rows = 0;
foreach (array('mirror' => $mirror_sources, 'shadow' => $shadow_sources) as $tree => $sources) {
	foreach ($sources as $source_key => $source) {
		$hash_source = $source;
		if ($source_key === 'monitor') {
			$hash_source = g10_project_g16_monitor_logging($hash_source, $tree . ':monitor');
			$g15_projection = g10_project_g15_monitor_dates($hash_source, $tree . ':monitor');
			g10_same(2, $g15_projection['replacements'], $tree . ' monitor G15 projection replacement count changed.');
			g10_same(3, $g15_projection['rows'], $tree . ' monitor G15 projection row count changed.');
			$g15_date_projection_rows += $g15_projection['rows'];
			$hash_source = $g15_projection['source'];
		}
		g10_same($whole_hashes[$tree][$source_key], hash('sha256', $hash_source), $tree . ' projected whole-source hash changed: ' . $source_key);
		$stripped = g10_strip_owned_annotations($hash_source, $annotation_specs[$source_key], $tree . ':' . $source_key);
		g10_same($stripped_hashes[$tree][$source_key], hash('sha256', $stripped['source']), $tree . ' annotation-stripped source changed: ' . $source_key);
		g10_same(
			$projection_hashes[$tree][$source_key],
			hash('sha256', g10_projection($hash_source, $projection_functions[$source_key])),
			$tree . ' outside-owned-function projection changed: ' . $source_key
		);
		$stripped_sources[$tree][$source_key] = $stripped['source'];
		$total_comments += $stripped['comments'];
		$total_codes += $stripped['codes'];
	}
}
g10_same(6, $g15_date_projection_rows, 'Mirror and shadow must project exactly three G15 monitor date rows each.');
g10_same(14, $total_comments, 'Mirror and shadow must each contain exactly seven owned annotations.');
g10_same(22, $total_codes, 'Mirror and shadow annotations must each cover exactly 11 artifact codes.');
$mutated_monitor = str_replace(
	"'posts_per_page' => \$batch_size",
	"'posts_per_page' => 999",
	$stripped_sources['mirror']['monitor'],
	$mutation_count
);
g10_same(1, $mutation_count, 'Runtime mutation control must alter one exact monitor argument.');
g10_assert(
	hash('sha256', $mutated_monitor) !== $stripped_hashes['mirror']['monitor'],
	'Annotation-stripped whole-source hash must reject a non-comment runtime mutation.'
);

g10_same($mirror_sources['daily'], $shadow_sources['daily'], 'Daily-report mirror/shadow files must remain exact.');
g10_same(
	g10_extract_function($mirror_sources['cron'], 'vms_ticket_integrity_watch_ticketing_meta'),
	g10_extract_function($shadow_sources['cron'], 'vms_ticket_integrity_watch_ticketing_meta'),
	'Cron owned watcher must remain exact across mirror/shadow.'
);
g10_same(
	g10_extract_function($mirror_sources['add'], 'vms_add_dispatch_get_event_plan_need_scan'),
	g10_extract_function($shadow_sources['add'], 'vms_add_dispatch_get_event_plan_need_scan'),
	'ADD owned scan must remain exact across mirror/shadow.'
);
$monitor_prior_annotation = "\t\t\t\t// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- Ticket Integrity scans require the canonical unfiltered event-plan dataset; query scope is bounded by published status, linked TEC event, the date window, and batch pagination.\n";
$mirror_monitor_function = str_replace(
	$monitor_prior_annotation,
	'',
	g10_extract_function($mirror_sources['monitor'], 'vms_ticket_integrity_build_targets'),
	$monitor_prior_count
);
g10_same(1, $monitor_prior_count, 'Mirror must retain the prior suppress_filters annotation.');
g10_same(
	$mirror_monitor_function,
	g10_extract_function($shadow_sources['monitor'], 'vms_ticket_integrity_build_targets'),
	'Monitor owned query behavior must remain exact after removing the preserved mirror-only annotation.'
);
g10_assert($mirror_sources['monitor'] !== $shadow_sources['monitor'], 'Monitor whole-file divergence must remain preserved.');
g10_assert($mirror_sources['cron'] !== $shadow_sources['cron'], 'Cron whole-file divergence must remain preserved.');
g10_assert($mirror_sources['add'] !== $shadow_sources['add'], 'ADD whole-file divergence must remain preserved.');

$monitor_shutdown_source = g10_extract_function($mirror_sources['monitor'], 'vms_ticket_integrity_fatal_guard_shutdown');
g10_same(1, substr_count($monitor_shutdown_source, 'error_log('), 'G16 monitor direct fallback count changed.');
g10_same(1, substr_count($monitor_shutdown_source, 'DevelopmentFunctions.error_log_error_log'), 'G16 monitor fallback must retain one exact line-local suppression.');
g10_contains("if (function_exists('error_log'))", $monitor_shutdown_source, 'G16 monitor fallback availability guard changed.');
$monitor_format_source = g10_extract_function($mirror_sources['monitor'], 'vms_ticket_integrity_format_datetime');
$monitor_target_source = g10_extract_function($mirror_sources['monitor'], 'vms_ticket_integrity_build_targets');
g10_contains("return wp_date('Y-m-d g:i a', \$timestamp, wp_timezone());", $monitor_format_source, 'G15 monitor formatter remediation changed.');
g10_contains("\$tz = wp_timezone();", $monitor_target_source, 'G15 monitor target timezone resolution changed.');
g10_contains("\$start_date = wp_date('Y-m-d', \$now, \$tz);", $monitor_target_source, 'G15 monitor start-date remediation changed.');
g10_contains("\$end_date = wp_date('Y-m-d', \$cutoff, \$tz);", $monitor_target_source, 'G15 monitor end-date remediation changed.');
g10_same(0, preg_match_all('/(?<![A-Za-z0-9_])date\s*\(/', $monitor_format_source . "\n" . $monitor_target_source), 'G15 monitor functions must contain zero native date() calls.');
g10_assert(strpos($monitor_format_source . $monitor_target_source, 'WordPress.DateTime') === false, 'G15 monitor date remediation must not add DateTime suppressions.');

eval(g10_extract_function($mirror_sources['daily'], 'vms_ticket_integrity_report_statuses'));
eval(g10_extract_function($mirror_sources['daily'], 'vms_ticket_integrity_report_table_exists'));
eval(g10_extract_function($mirror_sources['daily'], 'vms_ticket_integrity_report_lookup_metrics'));
eval(g10_extract_function($mirror_sources['monitor'], 'vms_ticket_integrity_build_targets'));
eval(g10_extract_function($mirror_sources['cron'], 'vms_ticket_integrity_watch_ticketing_meta'));
eval(g10_extract_function($mirror_sources['add'], 'vms_add_dispatch_get_event_plan_need_scan'));

$wpdb = new G10_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
g10_reset_runtime($wpdb);

g10_same(false, vms_ticket_integrity_report_table_exists('  '), 'Blank table probes should fail closed.');
g10_same(array(), $wpdb->prepares, 'Blank table probes must not reach wpdb.');
$GLOBALS['wpdb'] = null;
g10_same(false, vms_ticket_integrity_report_table_exists('wp_wc_order_stats'), 'Missing wpdb should fail closed.');
$wpdb = new G10_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_var_queue = array('wp_wc_order_stats', 'different_table');
g10_same(true, vms_ticket_integrity_report_table_exists('wp_wc_order_stats'), 'Exact table probe should succeed.');
g10_same(false, vms_ticket_integrity_report_table_exists('wp_wc_order_stats'), 'Mismatched table probe should fail closed.');
g10_same(2, count($wpdb->get_var_calls), 'Schema probes must remain request-fresh rather than adding persistent caching.');
g10_same('SHOW TABLES LIKE %s', $wpdb->prepares[0]['template'], 'Schema probe template changed.');
g10_same(array('wp_wc_order_stats'), $wpdb->prepares[0]['args'], 'Schema probe argument changed.');
g10_same("SHOW TABLES LIKE 'wp_wc_order_stats'", $wpdb->prepares[0]['sql'], 'Schema probe rendered SQL changed.');

g10_reset_runtime($wpdb);
g10_same(
	array('provider' => 'none', 'statuses' => array('wc-completed'), 'qty' => 0, 'net_revenue' => 0.0, 'gross_revenue' => 0.0, 'by_product' => array()),
	vms_ticket_integrity_report_lookup_metrics(array(0), array('wc-completed')),
	'Empty normalized product set should retain the empty result.'
);
g10_same(array(), $wpdb->get_var_calls, 'Empty product set must not query.');
$GLOBALS['g10_woo_active'] = false;
g10_same('none', vms_ticket_integrity_report_lookup_metrics(array(10), array('wc-completed'))['provider'], 'Inactive WooCommerce should retain empty provider.');
g10_same(array(), $wpdb->get_var_calls, 'Inactive WooCommerce must not query.');

g10_reset_runtime($wpdb);
$GLOBALS['g10_products'][10] = new G10_Product(3, 5.5);
$wpdb->get_var_queue = array('wp_wc_order_product_lookup', false);
$fallback = vms_ticket_integrity_report_lookup_metrics(array(10, 20), array('wc-completed'));
g10_same(
	array(
		'provider' => 'woo_product_totals',
		'statuses' => array('wc-completed'),
		'qty' => 3,
		'net_revenue' => 16.5,
		'gross_revenue' => 16.5,
		'by_product' => array(10 => array('qty' => 3, 'net_revenue' => 16.5, 'gross_revenue' => 16.5)),
	),
	$fallback,
	'Missing lookup schema should preserve WooCommerce product-total fallback results.'
);
g10_same(array(), $wpdb->get_results_calls, 'Missing lookup schema must not execute the aggregate.');

g10_reset_runtime($wpdb);
$statuses = array('wc-completed', 'wc-processing');
$raw_rows = array(
	array('product_id' => '10', 'qty' => '2.4', 'net_revenue' => '12.5', 'gross_revenue' => '15'),
	array('product_id' => '20', 'qty' => '-1', 'net_revenue' => '-2', 'gross_revenue' => '3'),
	array('product_id' => '0', 'qty' => '100', 'net_revenue' => '100', 'gross_revenue' => '100'),
);
$wpdb->get_var_queue = array('wp_wc_order_product_lookup', 'wp_wc_order_stats');
$wpdb->get_results_queue = array($raw_rows);
$metrics = vms_ticket_integrity_report_lookup_metrics(array(10, 20), $statuses);
$aggregate_prepare = $wpdb->prepares[2];
$expected_template = 'SELECT product_lookup.product_id AS product_id, COALESCE(SUM(product_lookup.product_qty), 0) AS qty, COALESCE(SUM(product_lookup.product_net_revenue), 0) AS net_revenue, COALESCE(SUM(product_lookup.product_gross_revenue), 0) AS gross_revenue FROM %i product_lookup INNER JOIN %i order_stats ON order_stats.order_id = product_lookup.order_id WHERE product_lookup.product_id IN (%d, %d) AND order_stats.status IN (%s, %s) GROUP BY product_lookup.product_id';
g10_same($expected_template, g10_normalize_sql($aggregate_prepare['template']), 'Aggregate SQL template changed.');
g10_same(
	array('wp_wc_order_product_lookup', 'wp_wc_order_stats', 10, 20, 'wc-completed', 'wc-processing'),
	$aggregate_prepare['args'],
	'Aggregate identifier/value preparation order changed.'
);
$tick = chr(96);
$expected_sql = "SELECT product_lookup.product_id AS product_id, COALESCE(SUM(product_lookup.product_qty), 0) AS qty, COALESCE(SUM(product_lookup.product_net_revenue), 0) AS net_revenue, COALESCE(SUM(product_lookup.product_gross_revenue), 0) AS gross_revenue FROM {$tick}wp_wc_order_product_lookup{$tick} product_lookup INNER JOIN {$tick}wp_wc_order_stats{$tick} order_stats ON order_stats.order_id = product_lookup.order_id WHERE product_lookup.product_id IN (10, 20) AND order_stats.status IN ('wc-completed', 'wc-processing') GROUP BY product_lookup.product_id";
g10_same($expected_sql, g10_normalize_sql($aggregate_prepare['sql']), 'Rendered aggregate SQL changed.');
g10_same($raw_rows, $wpdb->get_results_calls[0]['result'], 'Raw aggregate result capture changed.');
g10_same(
	array(
		'provider' => 'woo_lookup_completed',
		'statuses' => $statuses,
		'qty' => 2,
		'net_revenue' => 12.5,
		'gross_revenue' => 18.0,
		'by_product' => array(
			10 => array('qty' => 2, 'net_revenue' => 12.5, 'gross_revenue' => 15.0),
			20 => array('qty' => 0, 'net_revenue' => 0.0, 'gross_revenue' => 3.0),
		),
	),
	$metrics,
	'Daily aggregate normalization/results changed.'
);

g10_reset_runtime($wpdb);
$wpdb->get_var_queue = array('wp_wc_order_product_lookup', 'wp_wc_order_stats');
$wpdb->get_results_queue = array(false);
$failed_metrics = vms_ticket_integrity_report_lookup_metrics(array(10), array('wc-completed'));
g10_same('woo_lookup_completed', $failed_metrics['provider'], 'Aggregate failure provider changed.');
g10_same(0, $failed_metrics['qty'], 'Aggregate failure should remain empty.');
g10_same(false, $wpdb->get_results_calls[0]['result'], 'Aggregate failure result must be captured unchanged.');
$prepares_before_repeat = count($wpdb->prepares);
$wpdb->get_var_queue = array('wp_wc_order_product_lookup', 'wp_wc_order_stats');
$wpdb->get_results_queue = array(array());
vms_ticket_integrity_report_lookup_metrics(array(10), array('wc-completed'));
g10_same($prepares_before_repeat + 3, count($wpdb->prepares), 'Repeated report lookup must preserve request-fresh table probes and aggregate execution.');

g10_reset_runtime($wpdb);
$GLOBALS['g10_batch_size'] = 2;
$started = time();
$date_late = gmdate('Y-m-d', $started + (3 * DAY_IN_SECONDS));
$date_early = gmdate('Y-m-d', $started + DAY_IN_SECONDS);
$GLOBALS['g10_meta'] = array(
	101 => array('_vms_tec_event_id' => 501, '_vms_event_date' => $date_late, '_vms_start_time' => '12:00:00'),
	102 => array('_vms_tec_event_id' => 0, '_vms_event_date' => $date_early, '_vms_start_time' => '12:00:00'),
	103 => array('_vms_tec_event_id' => 503, '_vms_event_date' => $date_early, '_vms_start_time' => '11:00:00'),
);
$GLOBALS['g10_uses_ticketing'] = array(101 => true, 103 => true);
$GLOBALS['g10_titles'] = array(501 => 'Later Event', 503 => 'Earlier Event');
$GLOBALS['g10_wp_query_queue'] = array(
	array('posts' => array(101, 0, 102), 'max_num_pages' => 2),
	array('posts' => array(103), 'max_num_pages' => 2),
);
$targets = vms_ticket_integrity_build_targets(array('days_ahead' => 30));
$finished = time();
g10_same(array(103, 101), array_column($targets, 'plan_id'), 'Monitor targets should preserve chronological ordering across pages.');
g10_same(2, count($GLOBALS['g10_wp_query_calls']), 'Monitor should paginate through every reported page.');
$first_args = $GLOBALS['g10_wp_query_calls'][0]['args'];
$start_date = (string) ($first_args['meta_query'][0]['value'][0] ?? '');
$end_date = (string) ($first_args['meta_query'][0]['value'][1] ?? '');
g10_assert(in_array($start_date, array(gmdate('Y-m-d', $started), gmdate('Y-m-d', $finished)), true), 'Monitor start date must stay in the current UTC day.');
g10_assert(in_array($end_date, array(gmdate('Y-m-d', $started + (30 * DAY_IN_SECONDS)), gmdate('Y-m-d', $finished + (30 * DAY_IN_SECONDS))), true), 'Monitor end date must stay in the configured finite window.');
$expected_monitor_args = array(
	'post_type' => 'vms_event_plan',
	'post_status' => 'publish',
	'posts_per_page' => 25,
	'paged' => 1,
	'fields' => 'ids',
	'no_found_rows' => false,
	'meta_key' => '_vms_event_date',
	'orderby' => 'meta_value',
	'meta_type' => 'DATE',
	'order' => 'ASC',
	'meta_query' => array(
		array('key' => '_vms_event_date', 'value' => array($start_date, $end_date), 'compare' => 'BETWEEN', 'type' => 'DATE'),
		array('key' => '_vms_tec_event_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'),
	),
	'update_post_meta_cache' => false,
	'update_post_term_cache' => false,
	'cache_results' => false,
	'lazy_load_term_meta' => false,
	'suppress_filters' => true,
);
g10_same($expected_monitor_args, $first_args, 'First monitor WP_Query arguments changed.');
$expected_monitor_args['paged'] = 2;
g10_same($expected_monitor_args, $GLOBALS['g10_wp_query_calls'][1]['args'], 'Second monitor WP_Query arguments changed.');
g10_same(2, $GLOBALS['g10_reset_postdata_calls'], 'Monitor should reset postdata once per page.');

g10_reset_runtime($wpdb);
$GLOBALS['g10_wp_query_queue'][] = array('posts' => false, 'max_num_pages' => 4);
g10_same(array(), vms_ticket_integrity_build_targets(array('days_ahead' => 7)), 'Non-array monitor query result should fail closed.');
g10_same(1, count($GLOBALS['g10_wp_query_calls']), 'Empty monitor result must terminate pagination.');

g10_reset_runtime($wpdb);
$GLOBALS['g10_post_types'][77] = 'vms_event_plan';
vms_ticket_integrity_watch_ticketing_meta(1, 0, '_vms_ticketing_enabled_override', 'x');
vms_ticket_integrity_watch_ticketing_meta(2, 77, '_unwatched', 'x');
g10_same(array(), $GLOBALS['g10_perf_logs'], 'Invalid/unwatched metadata must not log.');
g10_same(array(), $GLOBALS['g10_spot_scans'], 'Invalid/unwatched metadata must not queue.');
vms_ticket_integrity_watch_ticketing_meta(3, 77, '_vms_ticketing_enabled_override', 'x');
g10_same(
	array(array('name' => 'vms_ticket_integrity_watch_ticketing_meta', 'object_id' => 77, 'context' => array('job_name' => 'ticket_integrity_ticketing_meta', 'meta_key' => '_vms_ticketing_enabled_override'))),
	$GLOBALS['g10_perf_logs'],
	'Cron watcher diagnostic payload changed.'
);
g10_same(
	array(array('object_id' => 77, 'reason' => 'ticketing_meta_update')),
	$GLOBALS['g10_spot_scans'],
	'Cron watcher queue behavior changed.'
);

g10_reset_runtime($wpdb);
$today = current_time('Y-m-d');
$GLOBALS['g10_add_contexts'] = array(
	11 => array('event_plan_id' => 11, 'event_title' => 'Later', 'event_date' => '2026-09-20', 'post_status' => 'publish', 'event_status' => 'draft', 'exclude_reason' => '', 'rows' => array('later-row')),
	12 => array('event_plan_id' => 12, 'event_title' => 'Excluded', 'event_date' => '2026-09-10', 'post_status' => 'draft', 'event_status' => 'draft', 'exclude_reason' => 'Past event', 'rows' => array()),
	13 => array('event_plan_id' => 13, 'event_title' => 'Earlier', 'event_date' => '2026-09-15', 'post_status' => 'future', 'event_status' => 'draft', 'exclude_reason' => '', 'rows' => array('earlier-row')),
);
$GLOBALS['g10_get_posts_queue'] = array(array(11, 12), array(12, 13));
$scan = vms_add_dispatch_get_event_plan_need_scan(2, 1);
$expected_candidate_args = array(
	'post_type' => 'vms_event_plan',
	'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
	'posts_per_page' => 50,
	'orderby' => 'meta_value',
	'order' => 'ASC',
	'meta_key' => '_vms_event_date',
	'fields' => 'ids',
	'meta_query' => array(array('key' => '_vms_event_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE')),
);
$expected_diagnostic_args = array(
	'post_type' => 'vms_event_plan',
	'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
	'posts_per_page' => 20,
	'orderby' => 'modified',
	'order' => 'DESC',
	'fields' => 'ids',
);
g10_same($expected_candidate_args, $GLOBALS['g10_get_posts_calls'][0]['args'], 'ADD candidate get_posts arguments changed.');
g10_same(array(11, 12), $GLOBALS['g10_get_posts_calls'][0]['result'], 'ADD candidate query result capture changed.');
g10_same($expected_diagnostic_args, $GLOBALS['g10_get_posts_calls'][1]['args'], 'ADD diagnostic get_posts arguments changed.');
g10_same(array(12, 13), $GLOBALS['g10_get_posts_calls'][1]['result'], 'ADD diagnostic query result capture changed.');
g10_same(array(13, 11), array_column($scan['contexts'], 'event_plan_id'), 'ADD included contexts should remain date-sorted.');
g10_same(array('earlier-row'), $scan['contexts'][0]['vendor_need_rows'], 'ADD eligible rows should remain attached to context.');
g10_same(
	array(array('event_plan_id' => 12, 'event_title' => 'Excluded', 'event_date' => '2026-09-10', 'post_status' => 'draft', 'event_status' => 'draft', 'reason' => 'Past event')),
	$scan['excluded'],
	'ADD excluded diagnostic result changed.'
);

g10_reset_runtime($wpdb);
$GLOBALS['g10_get_posts_queue'][] = array();
$include_past = vms_add_dispatch_get_event_plan_need_scan(20, 0, array('include_past_events' => true));
g10_same(array('contexts' => array(), 'excluded' => array()), $include_past, 'ADD include-past empty result changed.');
$include_past_args = $GLOBALS['g10_get_posts_calls'][0]['args'];
g10_assert(!isset($include_past_args['meta_query']), 'ADD include-past query must continue omitting the date lower bound.');
g10_same(300, $include_past_args['posts_per_page'], 'ADD candidate cap changed.');
g10_same(1, count($GLOBALS['g10_get_posts_calls']), 'Zero excluded limit must continue skipping the diagnostic query.');

g10_reset_runtime($wpdb);
$GLOBALS['g10_get_posts_queue'] = array(false, false);
g10_same(
	array('contexts' => array(), 'excluded' => array()),
	vms_add_dispatch_get_event_plan_need_scan(1, 1),
	'Two non-array ADD query failures should fail closed.'
);
g10_same(false, $GLOBALS['g10_get_posts_calls'][0]['result'], 'ADD candidate failure must be captured unchanged.');
g10_same(false, $GLOBALS['g10_get_posts_calls'][1]['result'], 'ADD diagnostic failure must be captured unchanged.');

g10_reset_runtime($wpdb);
$GLOBALS['g10_get_posts_queue'] = array(array(), array());
vms_add_dispatch_get_event_plan_need_scan(1, 0);
vms_add_dispatch_get_event_plan_need_scan(1, 0);
g10_same(2, count($GLOBALS['g10_get_posts_calls']), 'ADD candidate scan must preserve request-fresh uncached query behavior.');

fwrite(STDOUT, "G10 integrity repositories DB remediation: PASS (Wave 4 rows 11 -> projected 0; U -1, D -2, N -2, P -1, K -3, Q -2)\n");
