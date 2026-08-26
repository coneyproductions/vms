<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BVMGR_VENUE_TEMPLATE_META_KEY', '_vms_is_venue_template');

final class G13_WPDB_Spy
{
	public string $usermeta = 'wp_usermeta';
	/** @var array<int,array{template:string,args:array<int,mixed>,sql:string}> */
	public array $prepares = array();
	/** @var array<int,array{sql:string,result:mixed}> */
	public array $queries = array();
	/** @var array<int,mixed> */
	public array $query_queue = array();

	public function esc_like(string $text): string
	{
		return addcslashes($text, '_%\\');
	}

	public function prepare(string $template, ...$args): string
	{
		$index = 0;
		$sql = preg_replace_callback(
			'/(?<!%)%(?:\d+\$)?[sd]/',
			static function (array $match) use (&$index, $args): string {
				if (!array_key_exists($index, $args)) {
					throw new RuntimeException('Missing prepared-query argument.');
				}
				$value = $args[$index++];
				if (substr($match[0], -1) === 'd') {
					return (string) (int) $value;
				}
				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$template
		);
		if (!is_string($sql) || $index !== count($args)) {
			throw new RuntimeException('Prepared-query placeholder/argument mismatch.');
		}
		$this->prepares[] = array('template' => $template, 'args' => $args, 'sql' => $sql);
		return $sql;
	}

	public function query(string $sql)
	{
		$result = $this->query_queue === array() ? 1 : array_shift($this->query_queue);
		$this->queries[] = array('sql' => $sql, 'result' => $result);
		return $result;
	}
}

final class WP_Post
{
	public int $ID;
	public string $post_status;

	public function __construct(int $id, string $post_status = 'publish')
	{
		$this->ID = $id;
		$this->post_status = $post_status;
	}
}

$GLOBALS['g13_get_posts_calls'] = array();
$GLOBALS['g13_get_posts_queue'] = array();
$GLOBALS['g13_wc_orders_calls'] = array();
$GLOBALS['g13_wc_orders_queue'] = array();
$GLOBALS['g13_meta'] = array();
$GLOBALS['g13_statuses'] = array();
$GLOBALS['g13_excluded'] = array();
$GLOBALS['g13_screen'] = (object) array('base' => 'post', 'post_type' => 'vms_venue');

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	$value = is_scalar($value) ? strtolower((string) $value) : '';
	$result = preg_replace('/[^a-z0-9_\-]/', '', $value);
	return is_string($result) ? $result : '';
}

function get_posts(array $args): array
{
	$GLOBALS['g13_get_posts_calls'][] = $args;
	return $GLOBALS['g13_get_posts_queue'] === array() ? array() : array_shift($GLOBALS['g13_get_posts_queue']);
}

function wc_get_orders(array $args): array
{
	$GLOBALS['g13_wc_orders_calls'][] = $args;
	return $GLOBALS['g13_wc_orders_queue'] === array() ? array() : array_shift($GLOBALS['g13_wc_orders_queue']);
}

function wc_get_order_statuses(): array
{
	return array('wc-processing' => 'Processing', 'wc-completed' => 'Completed');
}

function wc_get_product(int $product_id)
{
	unset($product_id);
	return null;
}

function vms_ticketing_v2_calc_sold_qty_for_entitlement_scope(int $plan_id, string $entitlement_id, string $sku, int $product_id): array
{
	unset($plan_id, $entitlement_id, $sku, $product_id);
	return array('ok' => true, 'sold_qty' => 0);
}

function vms_ticketing_v2_product_meta_key(string $field): string
{
	return '_vms_' . $field;
}

function vms_meta_key(string $scope, string $field): string
{
	unset($scope);
	return $field === 'date' ? '_vms_event_date' : '_vms_' . $field;
}

function vms_budget_calculator_event_plan_date_key(): string
{
	return '_vms_event_date';
}

function vms_budget_calculator_event_plan_status_key(): string
{
	return '_vms_event_plan_status';
}

function vms_budget_calculator_ensure_inclusion_helpers(): void
{
}

function vms_budget_calculator_is_valid_ymd(string $value): bool
{
	return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
}

function vms_event_plan_should_include(int $plan_id, string $context, array $args): bool
{
	unset($context, $args);
	return !in_array($plan_id, $GLOBALS['g13_excluded'], true);
}

function vms_event_plan_get_status(int $plan_id, string $context): string
{
	unset($context);
	return $GLOBALS['g13_statuses'][$plan_id] ?? 'draft';
}

function vms_event_plan_status_label(string $status): string
{
	return strtoupper($status);
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	$value = $GLOBALS['g13_meta'][$post_id][$key] ?? ($single ? '' : array());
	return $value;
}

function get_the_title(int $post_id): string
{
	return 'Plan ' . $post_id;
}

function get_edit_post_link(int $post_id, string $context = ''): string
{
	unset($context);
	return 'https://example.test/wp-admin/post.php?post=' . $post_id;
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function vms_vendor_command_center_terms_days(): int
{
	return 30;
}

function current_user_can(string $capability): bool
{
	return in_array($capability, array('manage_options', 'edit_posts'), true);
}

function get_current_screen()
{
	return $GLOBALS['g13_screen'];
}

function g13_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g13_same($expected, $actual, string $message): void
{
	g13_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function g13_contains(string $needle, string $haystack, string $message): void
{
	g13_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function g13_extract_function(string $source, string $name): string
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

function g13_extract_assignment_call(string $source, string $marker): string
{
	$start = strpos($source, $marker);
	$open = $start === false ? false : strpos($source, '(', $start);
	if ($start === false || $open === false) {
		throw new RuntimeException('Unable to find call assignment: ' . $marker);
	}
	$depth = 0;
	for ($index = $open, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '(' ? 1 : 0;
		$depth -= $source[$index] === ')' ? 1 : 0;
		if ($depth === 0) {
			$semicolon = strpos($source, ';', $index);
			if ($semicolon !== false) {
				return substr($source, $start, ($semicolon - $start) + 1);
			}
		}
	}
	throw new RuntimeException('Unable to parse call assignment: ' . $marker);
}

function g13_extract_call_expression(string $source, string $marker): string
{
	$start = strpos($source, $marker);
	$open = $start === false ? false : strpos($source, '(', $start);
	if ($start === false || $open === false) {
		throw new RuntimeException('Unable to find call expression: ' . $marker);
	}
	$depth = 0;
	for ($index = $open, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '(' ? 1 : 0;
		$depth -= $source[$index] === ')' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($index - $start) + 1);
		}
	}
	throw new RuntimeException('Unable to parse call expression: ' . $marker);
}

function g13_strip_owned_annotations(string $source): string
{
	return (string) preg_replace(
		'/^[ \t]*\/\/ phpcs:ignore WordPress\.DB\.[^\r\n]* -- [^\r\n]*(?:\r?\n|$)/m',
		'',
		$source
	);
}

function g13_project_g16_settings_logging(string $source, string $label): string
{
	$fixture = (string) file_get_contents(__DIR__ . '/g16-operational-logging-group-c.php');
	$start = strpos($fixture, "\$g16c_reverse_specs['settings'] = array(");
	$end = strpos($fixture, "\n\$g16c_reverse_specs['notifications']", (int) $start);
	g13_assert($start !== false && $end !== false, $label . ' G16 settings fixture bounds changed.');
	eval(substr($fixture, (int) $start, (int) $end - (int) $start));
	g13_assert(isset($g16c_reverse_specs['settings']), $label . ' G16 settings fixture failed to load.');
	foreach ($g16c_reverse_specs['settings'] as $index => $spec) {
		g13_same(1, substr_count($source, $spec['current']), $label . ' G16 settings fragment count changed at ' . $index . '.');
		$source = str_replace($spec['current'], $spec['historical'], $source, $count);
		g13_same(1, $count, $label . ' G16 settings reverse count changed at ' . $index . '.');
	}
	return $source;
}

function g13_validate_narrow_suppressions(string $scope): void
{
	if (preg_match('/phpcs:(?:disable|enable|ignoreFile)/', $scope) === 1) {
		throw new RuntimeException('Broad PHPCS directives are forbidden in the G13 DB slice.');
	}
	$allowed = array(
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => true,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => true,
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_key' => true,
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => true,
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_value' => true,
	);
	foreach (preg_split('/\R/', $scope) ?: array() as $line) {
		if (strpos($line, 'phpcs:') === false) {
			continue;
		}
		if (!preg_match('/phpcs:ignore ([^\s]+) -- (.+)$/', $line, $match)) {
			throw new RuntimeException('Every suppression must be an exact justified one-line ignore: ' . $line);
		}
		foreach (explode(',', $match[1]) as $code) {
			if (!isset($allowed[$code])) {
				throw new RuntimeException('Broad or unclassified scanner suppression: ' . $code);
			}
		}
		if (strlen(trim($match[2])) < 30) {
			throw new RuntimeException('Scanner suppression lacks an operation-specific reason.');
		}
	}
}

function g13_reset_query_spies(): void
{
	$GLOBALS['g13_get_posts_calls'] = array();
	$GLOBALS['g13_get_posts_queue'] = array();
	$GLOBALS['g13_wc_orders_calls'] = array();
	$GLOBALS['g13_wc_orders_queue'] = array();
}

$root = dirname(__DIR__);
$shadow_root = dirname(__DIR__, 3) . '/vms';
$relative_files = array(
	'includes/admin/budget-calculator.php',
	'includes/admin/event-command-center.php',
	'includes/admin/event-feedback.php',
	'includes/admin/express-bar.php',
	'includes/admin/schedule.php',
	'includes/admin/settings-page.php',
	'includes/admin/settings/class-vms-settings-tours.php',
	'includes/admin/vendor-command-center.php',
	'includes/admin/venue-duplicate-templates.php',
);
$mirror_sources = array();
$shadow_sources = array();
foreach ($relative_files as $relative_file) {
	$mirror_sources[$relative_file] = (string) file_get_contents($root . '/' . $relative_file);
	$shadow_sources[$relative_file] = (string) file_get_contents($shadow_root . '/' . $relative_file);
	g13_assert($mirror_sources[$relative_file] !== '' && $shadow_sources[$relative_file] !== '', 'Mirror and shadow source should be readable: ' . $relative_file);
}

$mirror_baselines = array(
	'includes/admin/budget-calculator.php' => 'ea80897cec8ca077ff816ace6b9ebcfbc1cbfe82df7dbea1a67670811e7506a4',
	'includes/admin/event-command-center.php' => '019b4df6c82d048722303b35729289f32ff50b142d0669e87cb03893cd18d341',
	'includes/admin/event-feedback.php' => '9419db50efdb3955926089ac8f655f725599f60a683500204a3e551275143915',
	'includes/admin/express-bar.php' => '73f3ee11685aad6c7adcae276c2a14235731f49e840e9aeebe148b1985a8d231',
	'includes/admin/schedule.php' => '3b970b392e32bb08973a8a39cdeb38d43298e6201401d6858a4c585def3400cd',
	'includes/admin/settings-page.php' => 'df6f3701b4fb3ae818c40672fd50942a10f25f078807e94cffad8b66e2aaf5b5',
	'includes/admin/settings/class-vms-settings-tours.php' => '548e2c1858d265324964b24f72ad84d9b504d7500dc75f5e4f02d62c6b16b586',
	'includes/admin/vendor-command-center.php' => 'a2520f97e04abdfa761fdcf78c531e2ffb4d8727d93a3aadc49fce70731581e1',
	'includes/admin/venue-duplicate-templates.php' => '8d7f5484f32db6e7610b6601244095abe8ef41e657dc47b80439a32c3e3459ee',
);
$shadow_baselines = array(
	'includes/admin/budget-calculator.php' => 'ea80897cec8ca077ff816ace6b9ebcfbc1cbfe82df7dbea1a67670811e7506a4',
	'includes/admin/event-command-center.php' => 'e2af5a5c31aa58aa4c4fdc9b341efa6fec1993293a3cccd716a7d04fbdcd2cff',
	'includes/admin/event-feedback.php' => '62cc05cc7a8c9312232d81f246ad46384ad445620b7efc2df21edf781429b373',
	'includes/admin/express-bar.php' => '00fd3232fea92e27f4b86e2507e9cb8169ec203ce153c68b3b6344204cd323c8',
	'includes/admin/schedule.php' => '08bd1678600a6f2bea719f39229496f34dcaf5322fae2bf83ec28df90c93d710',
	'includes/admin/settings-page.php' => '47bdc06dc72da5b037b8ae649e6b9ad0c1412a7abf81ca8fddf71a6b36cada8d',
	'includes/admin/settings/class-vms-settings-tours.php' => '1ea74e86300ac38d4758dce94e083a3006210fc66c1e64e238f790e7233a5a14',
	'includes/admin/vendor-command-center.php' => '36b4b699a207e7ceb3622e16adb5284e945254234c627d83190d85928ae0f59c',
	'includes/admin/venue-duplicate-templates.php' => '8d7f5484f32db6e7610b6601244095abe8ef41e657dc47b80439a32c3e3459ee',
);
foreach ($relative_files as $relative_file) {
	$mirror_projection = $relative_file === 'includes/admin/settings-page.php' ? g13_project_g16_settings_logging($mirror_sources[$relative_file], 'mirror settings') : $mirror_sources[$relative_file];
	$shadow_projection = $relative_file === 'includes/admin/settings-page.php' ? g13_project_g16_settings_logging($shadow_sources[$relative_file], 'shadow settings') : $shadow_sources[$relative_file];
	g13_same($mirror_baselines[$relative_file], hash('sha256', g13_strip_owned_annotations($mirror_projection)), 'Mirror projection outside owned annotations changed: ' . $relative_file);
	g13_same($shadow_baselines[$relative_file], hash('sha256', g13_strip_owned_annotations($shadow_projection)), 'Shadow projection outside owned annotations changed: ' . $relative_file);
}

foreach (array('includes/admin/budget-calculator.php', 'includes/admin/venue-duplicate-templates.php') as $full_parity_file) {
	g13_same($mirror_sources[$full_parity_file], $shadow_sources[$full_parity_file], 'Whole-file mirror/shadow parity changed: ' . $full_parity_file);
}
foreach (array_diff($relative_files, array('includes/admin/budget-calculator.php', 'includes/admin/venue-duplicate-templates.php')) as $divergent_file) {
	g13_assert($mirror_sources[$divergent_file] !== $shadow_sources[$divergent_file], 'Intentional whole-file mirror/shadow divergence was erased: ' . $divergent_file);
}

$owned_chunks = array();
$owned_function_map = array(
	'includes/admin/budget-calculator.php' => array('vms_budget_calculator_collect_event_plans_for_year'),
	'includes/admin/event-command-center.php' => array('vms_event_command_center_get_plan_ids'),
	'includes/admin/event-feedback.php' => array('vms_feedback_recent_event_plans'),
	'includes/admin/settings-page.php' => array('vms_handle_sync_entitlement_images', 'vms_ticketing_stock_reconcile_scan'),
	'includes/admin/settings/class-vms-settings-tours.php' => array('handle_reset_current_user'),
	'includes/admin/vendor-command-center.php' => array('vms_vendor_command_center_collect_plan_maps'),
	'includes/admin/venue-duplicate-templates.php' => array('vms_render_create_from_template_panel'),
);
foreach ($owned_function_map as $relative_file => $functions) {
	foreach ($functions as $function) {
		$chunk = g13_extract_function($mirror_sources[$relative_file], $function);
		g13_same($chunk, g13_extract_function($shadow_sources[$relative_file], $function), 'Owned function mirror/shadow parity changed: ' . $function);
		$owned_chunks[] = $chunk;
	}
}
$express_call = g13_extract_assignment_call($mirror_sources['includes/admin/express-bar.php'], '$orders = wc_get_orders(array(');
$schedule_call = g13_extract_assignment_call($mirror_sources['includes/admin/schedule.php'], '$existing = get_posts(array(');
g13_same($express_call, g13_extract_assignment_call($shadow_sources['includes/admin/express-bar.php'], '$orders = wc_get_orders(array('), 'Express Bar owned query mirror/shadow parity changed.');
g13_same($schedule_call, g13_extract_assignment_call($shadow_sources['includes/admin/schedule.php'], '$existing = get_posts(array('), 'Schedule owned query mirror/shadow parity changed.');
$owned_chunks[] = $express_call;
$owned_chunks[] = $schedule_call;
$owned_source = implode("\n", $owned_chunks);

$scanner_inventory = array(
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 1,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 1,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_key' => 5,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => 6,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_value' => 2,
);
g13_same(15, array_sum($scanner_inventory), 'The artifact-truthful G13 DB inventory should remain exactly 15 rows.');
foreach ($scanner_inventory as $code => $expected) {
	g13_same($expected, substr_count($owned_source, $code), 'Owned scanner coverage count changed for ' . $code . '.');
}
g13_validate_narrow_suppressions($owned_source);
preg_match_all('/^[ \t]*\/\/ phpcs:ignore ([^\s]+) -- [^\r\n]+\R([^\r\n]+)/m', $owned_source, $annotation_matches, PREG_SET_ORDER);
g13_same(14, count($annotation_matches), 'Each of the 14 physical finding lines should have exactly one adjacent annotation.');
foreach ($annotation_matches as $annotation_match) {
	$codes = $annotation_match[1];
	$next_line = $annotation_match[2];
	if (strpos($codes, 'slow_db_query_meta_key') !== false) {
		g13_contains("'meta_key'", $next_line, 'A meta_key annotation must be directly adjacent to its token.');
	}
	if (strpos($codes, 'slow_db_query_meta_query') !== false) {
		g13_contains("'meta_query'", $next_line, 'A meta_query annotation must be directly adjacent to its token.');
	}
	if (strpos($codes, 'slow_db_query_meta_value') !== false) {
		g13_contains("'meta_value'", $next_line, 'A meta_value annotation must be directly adjacent to its token.');
	}
	if (strpos($codes, 'DirectDatabaseQuery') !== false) {
		g13_contains('$wpdb->query(', $next_line, 'The direct-query annotation must be directly adjacent to the DELETE call.');
	}
}
foreach (array(
	$owned_source . "\n// phpcs:disable WordPress.DB",
	$owned_source . "\n// phpcs:ignore WordPress.DB -- broad family ignore",
	$owned_source . "\n// phpcs:ignore WordPress.DB.SlowDBQuery -- broad one-line ignore",
) as $negative_scope) {
	$rejected = false;
	try {
		g13_validate_narrow_suppressions($negative_scope);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	g13_assert($rejected, 'A broad suppression negative control should be rejected.');
}

$all_runtime_source = implode("\n", $mirror_sources);
g13_same(0, substr_count($mirror_sources['includes/admin/settings-page.php'], 'error_log('), 'G16 settings must contain no direct logging fallback.');
g13_contains("vms_entitlements_sync_image_log('entitlement_image_sync_backfill_completed'", $mirror_sources['includes/admin/settings-page.php'], 'G16 settings must prefer the PhaseB adapter.');
g13_contains("vms_record_operational_issue('entitlement_image_sync_backfill_completed'", $mirror_sources['includes/admin/settings-page.php'], 'G16 settings must retain the foundation fallback.');
g13_contains('implode(\'\', $website_rows)', $mirror_sources['includes/admin/event-feedback.php'], 'The neighboring feedback output finding should remain present.');
g13_contains('echo vms_express_bar_action_form(', $mirror_sources['includes/admin/express-bar.php'], 'The neighboring Express Bar output findings should remain present.');
g13_contains('echo $content_html;', $mirror_sources['includes/admin/settings-page.php'], 'The neighboring settings content output finding should remain present.');
g13_contains('echo vms_settings_page_ticketing_stock_notice_placeholder();', $mirror_sources['includes/admin/settings-page.php'], 'The neighboring settings placeholder output finding should remain present.');
g13_same(0, substr_count($all_runtime_source, 'WordPress.PHP.DevelopmentFunctions.error_log_error_log'), 'The G16 settings remediation must not add a logging suppression.');
g13_same(0, substr_count($all_runtime_source, 'WordPress.Security.EscapeOutput.OutputNotEscaped'), 'The neighboring output findings must remain unsuppressed.');

foreach (array(
	'vms_budget_calculator_collect_event_plans_for_year',
	'vms_event_command_center_get_plan_ids',
	'vms_feedback_recent_event_plans',
	'vms_ticketing_stock_reconcile_scan',
	'vms_vendor_command_center_collect_plan_maps',
	'vms_render_create_from_template_panel',
) as $function) {
	$function_file = '';
	foreach ($owned_function_map as $relative_file => $functions) {
		if (in_array($function, $functions, true)) {
			$function_file = $relative_file;
			break;
		}
	}
	g13_assert($function_file !== '', 'Executable function source mapping should exist: ' . $function);
	eval(g13_extract_function($mirror_sources[$function_file], $function));
}

// The budget report retains the bounded primary lookup and the real unbounded fallback, then revalidates in PHP.
g13_reset_query_spies();
$GLOBALS['g13_get_posts_queue'] = array(array(42, 41));
$GLOBALS['g13_meta'] = array(
	41 => array('_vms_event_date' => '2026-01-12', '_vms_venue_id' => 9),
	42 => array('_vms_event_date' => '2026-09-08', '_vms_venue_id' => 10),
);
$budget_rows = vms_budget_calculator_collect_event_plans_for_year(2026, true);
g13_same(array(41, 42), array_column($budget_rows, 'plan_id'), 'Budget report result ordering changed.');
g13_same(1, count($GLOBALS['g13_get_posts_calls']), 'A nonempty bounded budget lookup should not invoke fallback.');
$budget_args = $GLOBALS['g13_get_posts_calls'][0];
g13_same(-1, $budget_args['posts_per_page'], 'Budget report enumeration semantics changed.');
g13_same('ids', $budget_args['fields'], 'Budget report result shape changed.');
g13_same(array('2026-01-01', '2026-12-31'), $budget_args['meta_query'][0]['value'], 'Budget report year bounds changed.');
g13_same('BETWEEN', $budget_args['meta_query'][0]['compare'], 'Budget report comparison changed.');

g13_reset_query_spies();
$GLOBALS['g13_get_posts_queue'] = array(array(), array(51, 52, 53));
$GLOBALS['g13_meta'] = array(
	51 => array('_vms_event_date' => '2026-05-20', '_vms_venue_id' => 11),
	52 => array('_vms_event_date' => '2025-12-31', '_vms_venue_id' => 12),
	53 => array('_vms_event_date' => 'legacy-date', '_vms_venue_id' => 13),
);
$fallback_rows = vms_budget_calculator_collect_event_plans_for_year(2026, false);
g13_same(array(51), array_column($fallback_rows, 'plan_id'), 'Budget fallback PHP year/date filtering changed.');
g13_same(2, count($GLOBALS['g13_get_posts_calls']), 'Empty bounded lookup should invoke exactly one legacy fallback.');
g13_same(array(), $GLOBALS['g13_get_posts_calls'][1]['meta_query'], 'Budget fallback must still remove the meta join.');
g13_same(-1, $GLOBALS['g13_get_posts_calls'][1]['posts_per_page'], 'Budget fallback must remain a real unbounded all-Event-Plans path.');

// Finite get_posts selectors retain exact limits, ordering, and returned values.
g13_reset_query_spies();
$GLOBALS['g13_get_posts_queue'] = array(array(0, '61', 62));
g13_same(array(61, 62), vms_event_command_center_get_plan_ids(), 'Command Center ID normalization changed.');
$command_args = $GLOBALS['g13_get_posts_calls'][0];
g13_same(200, $command_args['posts_per_page'], 'Command Center finite limit changed.');
g13_same('_vms_event_date', $command_args['meta_key'], 'Command Center date ordering key changed.');
g13_same('ASC', $command_args['order'], 'Command Center ordering direction changed.');
g13_same(true, $command_args['no_found_rows'], 'Command Center count-query behavior changed.');

g13_reset_query_spies();
$feedback_post = new WP_Post(71);
$GLOBALS['g13_get_posts_queue'] = array(array($feedback_post));
g13_same(array($feedback_post), vms_feedback_recent_event_plans(), 'Feedback selector returned objects changed.');
$feedback_args = $GLOBALS['g13_get_posts_calls'][0];
g13_same(75, $feedback_args['posts_per_page'], 'Feedback selector default limit changed.');
g13_same('_vms_event_date', $feedback_args['meta_key'], 'Feedback selector date key changed.');
g13_same('DESC', $feedback_args['order'], 'Feedback selector order changed.');

// Settings candidate scans retain complete enumeration and exact plugin-owned marker relations.
g13_reset_query_spies();
$GLOBALS['g13_get_posts_queue'] = array(array());
$stock_summary = vms_ticketing_stock_reconcile_scan(false);
g13_same('preview', $stock_summary['mode'], 'Stock preview mode changed.');
g13_same(0, $stock_summary['checked'], 'Empty stock preview result changed.');
$stock_args = $GLOBALS['g13_get_posts_calls'][0];
g13_same(-1, $stock_args['posts_per_page'], 'Stock reconcile must enumerate every candidate.');
g13_same('OR', $stock_args['meta_query']['relation'], 'Stock reconcile marker relation changed.');
g13_same(array('_vms_product_role', '_vms_ticketing_entitlement_id'), array_column(array_slice($stock_args['meta_query'], 1), 'key'), 'Stock reconcile marker keys changed.');

$sync_call = g13_extract_assignment_call($mirror_sources['includes/admin/settings-page.php'], '$product_ids = get_posts(array(');
$sync_runner = eval(
	'return static function (string $ent_meta_key, string $role_meta_key, string $plan_meta_key): array {'
	. $sync_call
	. ' return (array) $product_ids; };'
);
g13_assert(is_callable($sync_runner), 'Settings sync query runner should be executable.');
g13_reset_query_spies();
$GLOBALS['g13_get_posts_queue'] = array(array(81, 82));
g13_same(array(81, 82), $sync_runner('_vms_ticketing_entitlement_id', '_vms_product_role', '_vms_event_plan_id'), 'Settings sync get_posts result changed.');
$sync_args = $GLOBALS['g13_get_posts_calls'][0];
g13_same(-1, $sync_args['posts_per_page'], 'Settings sync must enumerate every candidate.');
g13_same('OR', $sync_args['meta_query']['relation'], 'Settings sync marker relation changed.');
g13_same(
	array('_vms_ticketing_entitlement_id', '_vms_product_role', '_vms_product_role', '_vms_event_plan_id'),
	array_column(array_slice($sync_args['meta_query'], 1), 'key'),
	'Settings sync marker keys changed.'
);

// The two divergent large functions execute their exact owned query occurrences without collapsing surrounding divergence.
$schedule_runner = eval(
	'return static function (string $ymd, int $venue_id): array {'
	. $schedule_call
	. ' return (array) $existing; };'
);
g13_assert(is_callable($schedule_runner), 'Schedule duplicate query runner should be executable.');
g13_reset_query_spies();
$GLOBALS['g13_get_posts_queue'] = array(array(91));
g13_same(array(91), $schedule_runner('2026-10-04', 17), 'Schedule duplicate lookup result changed.');
$schedule_args = $GLOBALS['g13_get_posts_calls'][0];
g13_same(-1, $schedule_args['posts_per_page'], 'Schedule duplicate lookup completeness changed.');
g13_same('AND', $schedule_args['meta_query']['relation'], 'Schedule duplicate relation changed.');
g13_same(array('_vms_event_date', '_vms_venue_id'), array_column(array_slice($schedule_args['meta_query'], 1), 'key'), 'Schedule duplicate keys changed.');
g13_same(array('2026-10-04', 17), array_column(array_slice($schedule_args['meta_query'], 1), 'value'), 'Schedule duplicate values changed.');

$express_runner = eval('return static function (): array {' . $express_call . ' return (array) $orders; };');
g13_assert(is_callable($express_runner), 'Express Bar order query runner should be executable.');
g13_reset_query_spies();
$express_order = (object) array('id' => 101);
$GLOBALS['g13_wc_orders_queue'] = array(array($express_order));
g13_same(array($express_order), $express_runner(), 'Express Bar order result changed.');
$express_args = $GLOBALS['g13_wc_orders_calls'][0];
g13_same(100, $express_args['limit'], 'Express Bar finite order limit changed.');
g13_same('_vms_express_bar_order', $express_args['meta_key'], 'Express Bar marker key changed.');
g13_same('1', $express_args['meta_value'], 'Express Bar marker value changed.');
g13_same(array('wc-processing', 'wc-completed'), $express_args['status'], 'Express Bar statuses changed.');

// The vendor map remains a finite temporal enumeration even though it intentionally has no count cap.
g13_reset_query_spies();
$GLOBALS['g13_get_posts_queue'] = array(array());
g13_same(array('next_map' => array(), 'payables_map' => array()), vms_vendor_command_center_collect_plan_maps(), 'Empty vendor map result shape changed.');
$vendor_args = $GLOBALS['g13_get_posts_calls'][0];
g13_same(-1, $vendor_args['posts_per_page'], 'Vendor map completeness changed.');
g13_same('_vms_event_date', $vendor_args['meta_key'], 'Vendor map ordering key changed.');
g13_same('BETWEEN', $vendor_args['meta_query'][0]['compare'], 'Vendor map temporal comparison changed.');
$vendor_start = new DateTimeImmutable($vendor_args['meta_query'][0]['value'][0]);
$vendor_end = new DateTimeImmutable($vendor_args['meta_query'][0]['value'][1]);
g13_same(485, (int) $vendor_start->diff($vendor_end)->days, 'Vendor map finite 485-day window changed.');

// Venue templates remain a complete plugin-marker selector on the authorized Add Venue screen.
g13_reset_query_spies();
$GLOBALS['g13_get_posts_queue'] = array(array());
vms_render_create_from_template_panel(new WP_Post(0, 'auto-draft'));
$venue_args = $GLOBALS['g13_get_posts_calls'][0];
g13_same(-1, $venue_args['posts_per_page'], 'Venue template enumeration changed.');
g13_same(BVMGR_VENUE_TEMPLATE_META_KEY, $venue_args['meta_key'], 'Venue template marker key changed.');
g13_same('1', $venue_args['meta_value'], 'Venue template marker value changed.');

// The actual settings-tour operation is an immediate prepared DELETE, not a table probe.
$tour_method = g13_extract_function($mirror_sources['includes/admin/settings/class-vms-settings-tours.php'], 'handle_reset_current_user');
$capability_position = strpos($tour_method, "current_user_can('manage_options')");
$nonce_position = strpos($tour_method, "check_admin_referer('vms_tours_reset_current_user')");
$delete_position = strpos($tour_method, '$wpdb->query(');
g13_assert(
	$capability_position !== false && $nonce_position !== false && $delete_position !== false
		&& $capability_position < $nonce_position && $nonce_position < $delete_position,
	'Tour bulk cleanup must remain capability- and nonce-gated in that order.'
);
$tour_expression = g13_extract_call_expression($mirror_sources['includes/admin/settings/class-vms-settings-tours.php'], '$wpdb->query(');
$tour_runner = eval('return static function (G13_WPDB_Spy $wpdb, int $user_id) { return ' . $tour_expression . '; };');
g13_assert(is_callable($tour_runner), 'Tour reset DELETE runner should be executable.');
$wpdb = new G13_WPDB_Spy();
$wpdb->query_queue = array(3, false);
g13_same(3, $tour_runner($wpdb, 73), 'Tour reset should preserve the affected-row result from the immediate DELETE.');
g13_same(false, $tour_runner($wpdb, 73), 'Tour reset should preserve the failure result from the immediate DELETE.');
g13_same(2, count($wpdb->queries), 'Tour reset should execute one DELETE per invocation without caching.');
g13_same(array(73, 'vms\\_tour\\_seen\\_%'), $wpdb->prepares[0]['args'], 'Tour reset prepared values changed.');
g13_same('DELETE FROM wp_usermeta WHERE user_id = %d AND meta_key LIKE %s', $wpdb->prepares[0]['template'], 'Tour reset DELETE template changed.');
g13_contains('DELETE FROM wp_usermeta WHERE user_id = 73', $wpdb->queries[0]['sql'], 'Tour reset should execute the prepared user-scoped DELETE.');
g13_contains("meta_key LIKE 'vms\\\\_tour\\\\_seen\\\\_%'", $wpdb->queries[0]['sql'], 'Tour reset LIKE pattern changed.');
g13_assert(strpos($tour_expression, 'SELECT') === false && strpos($tour_expression, 'SHOW TABLES') === false, 'Tour reset must not be characterized or changed into a table probe.');

echo "G13 admin/report repository SQL remediation checks passed.\n";
