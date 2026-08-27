<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

final class WP_Post
{
	public int $ID;

	public function __construct(int $id)
	{
		$this->ID = $id;
	}
}

final class G10_Support_WPDB_Spy
{
	public string $prefix;
	public string $posts;
	/** @var array<int,array{template:string,args:array<int,mixed>,sql:string}> */
	public array $prepares = array();
	/** @var array<int,array<string,mixed>> */
	public array $operations = array();
	/** @var array<int,mixed> */
	public array $query_queue = array();
	/** @var array<int,mixed> */
	public array $results_queue = array();
	/** @var array<int,mixed> */
	public array $col_queue = array();
	/** @var array<int,mixed> */
	public array $insert_queue = array();

	public function __construct(string $prefix = 'wp_')
	{
		$this->prefix = $prefix;
		$this->posts = $prefix . 'posts';
	}

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
					$parts = explode('.', (string) $value);
					return implode('.', array_map(static fn(string $part): string => '`' . str_replace('`', '``', $part) . '`', $parts));
				}
				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$template
		);
		if (!is_string($sql) || $index !== count($args) || preg_match('/(?<!%)%(?:\d+\$)?[sdfi]/', $sql) === 1) {
			throw new RuntimeException('Prepared query retained a placeholder: ' . $template);
		}

		$call = array('template' => $template, 'args' => array_values($args), 'sql' => $sql);
		$this->prepares[] = $call;
		$this->operations[] = array('kind' => 'prepare') + $call;
		return $sql;
	}

	public function query(string $sql)
	{
		$result = $this->query_queue === array() ? 1 : array_shift($this->query_queue);
		$this->operations[] = array('kind' => 'query', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_results(string $sql, $format = ARRAY_A)
	{
		$result = $this->results_queue === array() ? array() : array_shift($this->results_queue);
		$this->operations[] = array('kind' => 'get_results', 'sql' => $sql, 'format' => $format, 'result' => $result);
		return $result;
	}

	public function get_col(string $sql)
	{
		$result = $this->col_queue === array() ? array() : array_shift($this->col_queue);
		$this->operations[] = array('kind' => 'get_col', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function insert(string $table, array $data, array $format)
	{
		$result = $this->insert_queue === array() ? 1 : array_shift($this->insert_queue);
		$this->operations[] = array('kind' => 'insert', 'table' => $table, 'data' => $data, 'format' => $format, 'result' => $result);
		return $result;
	}
}

$GLOBALS['g10_support_options'] = array();
$GLOBALS['g10_support_option_updates'] = array();
$GLOBALS['g10_support_legacy_type'] = 'vms_verification_request';
$GLOBALS['g10_support_canonical_type'] = 'vms_verify_req';
$GLOBALS['g10_support_get_posts_calls'] = array();
$GLOBALS['g10_support_get_posts_queue'] = array();
$GLOBALS['g10_support_config'] = array();
$GLOBALS['g10_support_sync'] = array();
$GLOBALS['g10_support_meta'] = array();
$GLOBALS['g10_support_titles'] = array();
$GLOBALS['g10_support_contexts'] = array();
$GLOBALS['g10_support_source_model'] = array();
$GLOBALS['g10_support_user_id'] = 0;
$GLOBALS['g10_support_now'] = '2026-08-08 12:34:56';

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	$value = is_scalar($value) ? strtolower((string) $value) : '';
	$sanitized = preg_replace('/[^a-z0-9_\-]/', '', $value);
	return is_string($sanitized) ? $sanitized : '';
}

function sanitize_text_field($value): string
{
	return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
}

function get_option(string $key, $default = false)
{
	return array_key_exists($key, $GLOBALS['g10_support_options']) ? $GLOBALS['g10_support_options'][$key] : $default;
}

function update_option(string $key, $value, $autoload = null): bool
{
	$GLOBALS['g10_support_options'][$key] = $value;
	$GLOBALS['g10_support_option_updates'][] = array($key, $value, $autoload);
	return true;
}

function bvmgr_ticketing_verification_request_post_type_legacy(): string
{
	return (string) $GLOBALS['g10_support_legacy_type'];
}

function bvmgr_ticketing_verification_request_post_type(): string
{
	return (string) $GLOBALS['g10_support_canonical_type'];
}

/** @return string[] */
function bvmgr_ticketing_verification_request_post_types(): array
{
	return array('vms_verify_req', 'vms_verification_request');
}

function get_posts(array $args)
{
	$GLOBALS['g10_support_get_posts_calls'][] = $args;
	return $GLOBALS['g10_support_get_posts_queue'] === array() ? array() : array_shift($GLOBALS['g10_support_get_posts_queue']);
}

function bvmgr_ticketing_v2_get_config(int $event_plan_id): array
{
	unset($event_plan_id);
	return (array) $GLOBALS['g10_support_config'];
}

function bvmgr_ticketing_v2_get_sync(int $event_plan_id): array
{
	unset($event_plan_id);
	return (array) $GLOBALS['g10_support_sync'];
}

function bvmgr_ticketing_v2_product_meta_key(string $field): string
{
	return '_vms_' . $field;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	return $GLOBALS['g10_support_meta'][$post_id][$key] ?? ($single ? '' : array());
}

function get_the_title(int $post_id): string
{
	return (string) ($GLOBALS['g10_support_titles'][$post_id] ?? ('Product #' . $post_id));
}

function bvmgr_ticketing_claims_table_reservations(): string
{
	global $wpdb;
	return $wpdb->prefix . 'vms_ticketing_claim_reservations';
}

function bvmgr_ticketing_v2_resolve_verified_ticket_context(int $product_id): array
{
	return (array) ($GLOBALS['g10_support_contexts'][$product_id] ?? array());
}

function vms_square_ticket_mirror_log_table_name(): string
{
	global $wpdb;
	return $wpdb->prefix . 'vms_square_ticket_mirror_log';
}

function vms_square_ticket_mirror_canonical_product_id(int $product_id): int
{
	return absint($product_id);
}

function vms_square_ticket_mirror_build_source_model(int $product_id): array
{
	unset($product_id);
	return (array) $GLOBALS['g10_support_source_model'];
}

function get_current_user_id(): int
{
	return (int) $GLOBALS['g10_support_user_id'];
}

function vms_square_ticket_mirror_now_gmt(): string
{
	return (string) $GLOBALS['g10_support_now'];
}

function g10_support_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g10_support_same($expected, $actual, string $message): void
{
	g10_support_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function g10_support_contains(string $needle, string $haystack, string $message): void
{
	g10_support_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function g10_support_not_contains(string $needle, string $haystack, string $message): void
{
	g10_support_assert(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle);
}

function g10_support_normalize_sql(string $sql): string
{
	$normalized = preg_replace('/\s+/', ' ', trim($sql));
	return is_string($normalized) ? $normalized : '';
}

function g10_support_extract_function(string $source, string $name): string
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

function g10_support_extract_block(string $source, string $start_marker, string $end_marker): string
{
	$start = strpos($source, $start_marker);
	$end = $start === false ? false : strpos($source, $end_marker, $start);
	if ($start === false || $end === false) {
		throw new RuntimeException('Unable to find owned source block.');
	}
	return substr($source, $start, ($end - $start) + strlen($end_marker));
}

/**
 * @param string[] $expected_directives
 */
function g10_support_validate_directives(string $scope, array $expected_directives): void
{
	if (preg_match('/phpcs:(?:disable|enable|ignoreFile)/', $scope) === 1) {
		throw new RuntimeException('Broad PHPCS directive found.');
	}

	$allowed_codes = array(
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => true,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => true,
		'WordPress.DB.PreparedSQL.NotPrepared' => true,
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_key' => true,
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => true,
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_value' => true,
	);
	$actual = array();
	foreach (preg_split('/\R/', $scope) ?: array() as $line) {
		if (strpos($line, 'phpcs:') === false || (strpos($line, 'WordPress.DB') === false && strpos($line, 'PluginCheck.Security.DirectDB') === false)) {
			continue;
		}
		$trimmed = trim($line);
		if (!preg_match('/^\/\/ phpcs:ignore ([^\s]+) -- (.+)$/', $trimmed, $match)) {
			throw new RuntimeException('DB directive must be an exact justified line-local ignore: ' . $trimmed);
		}
		$codes = explode(',', $match[1]);
		foreach ($codes as $code) {
			if (!isset($allowed_codes[$code])) {
				throw new RuntimeException('Broad, unowned, or unrelated DB directive code: ' . $code);
			}
		}
		if (strlen(trim($match[2])) < 48) {
			throw new RuntimeException('DB directive reason is not operation-specific: ' . $trimmed);
		}
		$actual[] = $trimmed;
	}
	sort($actual);
	sort($expected_directives);
	g10_support_same($expected_directives, $actual, 'Exact owned DB directive inventory changed.');
}

/**
 * @param string[] $directives
 */
function g10_support_strip_directives(string $source, array $directives): string
{
	foreach ($directives as $directive) {
		$pattern = '/^[ \t]*' . preg_quote($directive, '/') . '\R/m';
		$source = (string) preg_replace($pattern, '', $source, 1, $removed);
		g10_support_same(1, $removed, 'Owned directive should strip exactly once: ' . $directive);
	}
	return $source;
}

function g10_support_project_to_parent(string $stripped_source, string $relative_file): string
{
	if ($relative_file === 'includes/integrations/ticketing-claims-admin.php') {
		$stripped_source = str_replace(' FROM %i', ' FROM {$table}', $stripped_source, $identifier_count);
		g10_support_same(1, $identifier_count, 'Claims identifier projection count changed.');
		$stripped_source = str_replace("\n\t\t\t\t\$table,\n\t\t\t\t\$event_id", "\n\t\t\t\t\$event_id", $stripped_source, $argument_count);
		g10_support_same(1, $argument_count, 'Claims identifier-argument projection count changed.');
	}
	if ($relative_file === 'includes/integrations/square-ticket-mirror.php') {
		$stripped_source = str_replace('SELECT * FROM %i WHERE', 'SELECT * FROM {$table} WHERE', $stripped_source, $identifier_count);
		g10_support_same(1, $identifier_count, 'Square identifier projection count changed.');
		$stripped_source = str_replace("\n        \$table,\n        \$product_id,", "\n        \$product_id,", $stripped_source, $argument_count);
		g10_support_same(1, $argument_count, 'Square identifier-argument projection count changed.');
	}
	return $stripped_source;
}

function g10_support_reset_runtime(): void
{
	$GLOBALS['g10_support_get_posts_calls'] = array();
	$GLOBALS['g10_support_get_posts_queue'] = array();
	$GLOBALS['g10_support_option_updates'] = array();
	$GLOBALS['g10_support_config'] = array();
	$GLOBALS['g10_support_sync'] = array();
	$GLOBALS['g10_support_meta'] = array();
	$GLOBALS['g10_support_titles'] = array();
	$GLOBALS['g10_support_contexts'] = array();
	$GLOBALS['g10_support_source_model'] = array();
	$GLOBALS['g10_support_user_id'] = 0;
}

$root = dirname(__DIR__);
$shadow_root = dirname(__DIR__, 3) . '/vms';
$relative_files = array(
	'includes/integrations/ticketing-verifications.php',
	'includes/integrations/ticketing-claims-admin.php',
	'includes/integrations/square-ticket-mirror.php',
	'includes/integrations/square-sync-firewall.php',
);
$mirror_sources = array();
$shadow_sources = array();
foreach ($relative_files as $relative_file) {
	$mirror_sources[$relative_file] = (string) file_get_contents($root . '/' . $relative_file);
	$shadow_sources[$relative_file] = (string) file_get_contents($shadow_root . '/' . $relative_file);
	g10_support_assert($mirror_sources[$relative_file] !== '' && $shadow_sources[$relative_file] !== '', 'Mirror/shadow source should be readable: ' . $relative_file);
}

$artifact_rows = array(
	array('includes/integrations/ticketing-claims-admin.php', 442, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query'),
	array('includes/integrations/ticketing-claims-admin.php', 640, 'PluginCheck.Security.DirectDB.UnescapedDBParameter'),
	array('includes/integrations/ticketing-claims-admin.php', 640, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'),
	array('includes/integrations/ticketing-claims-admin.php', 640, 'WordPress.DB.DirectDatabaseQuery.NoCaching'),
	array('includes/integrations/ticketing-claims-admin.php', 643, 'WordPress.DB.PreparedSQL.InterpolatedNotPrepared'),
	array('includes/integrations/ticketing-claims-admin.php', 1585, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query'),
	array('includes/integrations/ticketing-verifications.php', 668, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'),
	array('includes/integrations/ticketing-verifications.php', 668, 'WordPress.DB.DirectDatabaseQuery.NoCaching'),
	array('includes/integrations/ticketing-verifications.php', 668, 'WordPress.DB.PreparedSQL.NotPrepared'),
	array('includes/integrations/ticketing-verifications.php', 1153, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_key'),
	array('includes/integrations/ticketing-verifications.php', 1154, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_value'),
	array('includes/integrations/ticketing-verifications.php', 2962, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query'),
	array('includes/integrations/square-ticket-mirror.php', 1460, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'),
	array('includes/integrations/square-ticket-mirror.php', 1475, 'WordPress.DB.PreparedSQL.InterpolatedNotPrepared'),
	array('includes/integrations/square-ticket-mirror.php', 1480, 'PluginCheck.Security.DirectDB.UnescapedDBParameter'),
	array('includes/integrations/square-ticket-mirror.php', 1480, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'),
	array('includes/integrations/square-ticket-mirror.php', 1480, 'WordPress.DB.DirectDatabaseQuery.NoCaching'),
	array('includes/integrations/square-ticket-mirror.php', 1480, 'WordPress.DB.PreparedSQL.NotPrepared'),
	array('includes/integrations/square-sync-firewall.php', 519, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'),
	array('includes/integrations/square-sync-firewall.php', 519, 'WordPress.DB.DirectDatabaseQuery.NoCaching'),
);
$expected_rule_counts = array(
	'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 2,
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 5,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 4,
	'WordPress.DB.PreparedSQL.InterpolatedNotPrepared' => 2,
	'WordPress.DB.PreparedSQL.NotPrepared' => 2,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_key' => 1,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => 3,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_value' => 1,
);
g10_support_same(20, count($artifact_rows), 'Authoritative G10 support inventory should contain exactly 20 DB rows.');
$actual_rule_counts = array_count_values(array_column($artifact_rows, 2));
ksort($actual_rule_counts);
ksort($expected_rule_counts);
g10_support_same($expected_rule_counts, $actual_rule_counts, 'Authoritative rule-code mix should remain U2/D5/N4/I2/P2/K1/Q3/V1.');
$file_counts = array_count_values(array_column($artifact_rows, 0));
ksort($file_counts);
$expected_file_counts = array(
	'includes/integrations/ticketing-claims-admin.php' => 6,
	'includes/integrations/ticketing-verifications.php' => 6,
	'includes/integrations/square-ticket-mirror.php' => 6,
	'includes/integrations/square-sync-firewall.php' => 2,
);
ksort($expected_file_counts);
g10_support_same($expected_file_counts, $file_counts, 'Authoritative per-file inventory should remain 6/6/6/2.');

$artifact_path = '/tmp/wporg-wave4-integrated.nTzezu/plugin-check/plugin-check.strict.json';
if (is_file($artifact_path)) {
	g10_support_same('278819f58c585c226824fd89d541fc5ab107c11897240e281683fa6abad8d179', hash_file('sha256', $artifact_path), 'Authoritative strict-JSON hash changed.');
	$decoded = json_decode((string) file_get_contents($artifact_path), true);
	g10_support_assert(is_array($decoded), 'Authoritative strict JSON should decode.');
	$expected_keys = array();
	foreach ($artifact_rows as $row) {
		$expected_keys[implode(':', $row)] = true;
	}
	$actual_keys = array();
	foreach ($decoded as $row) {
		$file = preg_replace('#^/private#', '', (string) ($row['file'] ?? ''));
		if (!is_string($file) || !in_array($file, $relative_files, true) || strpos((string) ($row['code'] ?? ''), 'DB') === false) {
			continue;
		}
		$key = $file . ':' . (int) ($row['line'] ?? 0) . ':' . (string) ($row['code'] ?? '');
		$actual_keys[$key] = true;
	}
	ksort($actual_keys);
	ksort($expected_keys);
	g10_support_same($expected_keys, $actual_keys, 'Strict JSON should reconcile every exact owned row identity.');
}

$directives_by_file = array(
	'includes/integrations/ticketing-verifications.php' => array(
		'// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- One-time option-guarded post-type migration executes the immediately prepared UPDATE before recording its completion marker; a mutation result cannot be served from cache.',
		'// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Exact-user verification lookup is restricted to the established user_id key and returns at most the single latest request.',
		'// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact-user verification lookup is restricted to one normalized user ID and returns at most the single latest request.',
		'// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Capability-gated admin listing is capped at 250 requests and applies one exact configured-program filter.',
	),
	'includes/integrations/ticketing-claims-admin.php' => array(
		'// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Event-scoped fallback is capped at 200 products and checks only the two established exact event-link keys.',
		'// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Request-fresh reservation counts drive immediate claim availability; the event-scoped grouped read uses a prepared identifier/value and must not persist stale state.',
		'// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Event-scoped verified-ticket discovery is capped at 300 products across the two established exact event-link keys.',
	),
	'includes/integrations/square-ticket-mirror.php' => array(
		'// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Append-only Square mirror diagnostics must be inserted immediately so the caller\'s action/result ordering remains authoritative.',
		'// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Request-fresh diagnostics read the immediately prepared identifier/value query for one product with a limit clamped to 1-50.',
	),
	'includes/integrations/square-sync-firewall.php' => array(
		'// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Incremental firewall enforcement reads the current product table directly with an ID cursor and a batch limit clamped to 1-1000.',
	),
);
$all_expected_directives = array_merge(...array_values($directives_by_file));
$all_mirror_source = implode("\n", $mirror_sources);
$all_shadow_source = implode("\n", $shadow_sources);
g10_support_validate_directives($all_mirror_source, $all_expected_directives);
g10_support_validate_directives($all_shadow_source, $all_expected_directives);

$directive_anchors = array(
	array('includes/integrations/ticketing-verifications.php', $directives_by_file['includes/integrations/ticketing-verifications.php'][0], '$wpdb->query($sql);'),
	array('includes/integrations/ticketing-verifications.php', $directives_by_file['includes/integrations/ticketing-verifications.php'][1], "'meta_key'       => 'user_id',"),
	array('includes/integrations/ticketing-verifications.php', $directives_by_file['includes/integrations/ticketing-verifications.php'][2], "'meta_value'     => (string) \$user_id,"),
	array('includes/integrations/ticketing-verifications.php', $directives_by_file['includes/integrations/ticketing-verifications.php'][3], "\$query_args['meta_query'] = array("),
	array('includes/integrations/ticketing-claims-admin.php', $directives_by_file['includes/integrations/ticketing-claims-admin.php'][0], "'meta_query' => array("),
	array('includes/integrations/ticketing-claims-admin.php', $directives_by_file['includes/integrations/ticketing-claims-admin.php'][1], '$rows = $wpdb->get_results('),
	array('includes/integrations/ticketing-claims-admin.php', $directives_by_file['includes/integrations/ticketing-claims-admin.php'][2], "'meta_query' => array("),
	array('includes/integrations/square-ticket-mirror.php', $directives_by_file['includes/integrations/square-ticket-mirror.php'][0], '$wpdb->insert($table, $data, $format);'),
	array('includes/integrations/square-ticket-mirror.php', $directives_by_file['includes/integrations/square-ticket-mirror.php'][1], '$rows = $wpdb->get_results($sql, ARRAY_A);'),
	array('includes/integrations/square-sync-firewall.php', $directives_by_file['includes/integrations/square-sync-firewall.php'][0], '$ids = $wpdb->get_col($wpdb->prepare('),
);
foreach ($directive_anchors as $anchor) {
	foreach (array('mirror' => $mirror_sources, 'shadow' => $shadow_sources) as $tree => $sources) {
		$source = $sources[$anchor[0]];
		$pattern = '/^[ \t]*' . preg_quote($anchor[1], '/') . '\R([^\r\n]+)/m';
		g10_support_assert(preg_match($pattern, $source, $match) === 1, 'Directive should occur exactly before its owned operation: ' . $tree . '.');
		g10_support_contains($anchor[2], $match[1], 'Directive adjacency anchor changed: ' . $tree . '.');
	}
}
g10_support_same(16, 3 + 1 + 1 + 1 + 1 + 2 + 1 + 1 + 3 + 2, 'Ten exact directives should reconcile 16 scanner rows.');
g10_support_same(4, $expected_rule_counts['PluginCheck.Security.DirectDB.UnescapedDBParameter'] + $expected_rule_counts['WordPress.DB.PreparedSQL.InterpolatedNotPrepared'], 'Two identifier preparations should clear the four remaining scanner rows without suppression.');
g10_support_not_contains('PluginCheck.Security.DirectDB.UnescapedDBParameter', $all_mirror_source, 'Safe identifier preparation should not suppress UnescapedDBParameter.');
g10_support_not_contains('WordPress.DB.PreparedSQL.InterpolatedNotPrepared', $all_mirror_source, 'Safe identifier preparation should not suppress interpolated SQL.');

foreach (array(
	$all_mirror_source . "\n// phpcs:disable WordPress.DB",
	$all_mirror_source . "\n// phpcs:ignore WordPress.DB -- forbidden broad one-line ignore",
	$all_mirror_source . "\n// phpcs:ignore WordPress.DB.SlowDBQuery -- forbidden family ignore",
	$all_mirror_source . "\n// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.Security.EscapeOutput.OutputNotEscaped -- forbidden unowned mixed list",
	$all_mirror_source . "\n// phpcs:ignore PluginCheck.Security.DirectDB -- forbidden Plugin Check family ignore",
	$all_mirror_source . "\n// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- invented unowned occurrence",
) as $negative_scope) {
	$rejected = false;
	try {
		g10_support_validate_directives($negative_scope, $all_expected_directives);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	g10_support_assert($rejected, 'Broad/family/unowned suppression negative control should be rejected.');
}

$stripped_hashes = array(
	'mirror' => array(
		'includes/integrations/ticketing-verifications.php' => '2cac369f343d890120d3d527008e24d97c83d27cfb96ebab2459a7ccadf4a025',
		'includes/integrations/ticketing-claims-admin.php' => '0b19cbb38e1e58ba7f5893c11610d5c8e0a67838bd7e67356322c7f08a6edcc6',
		'includes/integrations/square-ticket-mirror.php' => '87bb5e334c1613e360d1e7ab76c520f1199dc033f0c73466c896965e73302b06',
		'includes/integrations/square-sync-firewall.php' => '3be74b7d8cdf959fd6dbf7b2d383f46dc599e1a7f14eb46d3e5b41efd55157f8',
	),
	'shadow' => array(
		'includes/integrations/ticketing-verifications.php' => '1a212622df49ea057d5c0ea062152412ca37eeaebf98e4a745f8a47b9e6381a4',
		'includes/integrations/ticketing-claims-admin.php' => 'b20453ff6271633d2d6c879ea08a2392bc1b4d2a2710add40bb915edbd648e8e',
		'includes/integrations/square-ticket-mirror.php' => '87bb5e334c1613e360d1e7ab76c520f1199dc033f0c73466c896965e73302b06',
		'includes/integrations/square-sync-firewall.php' => '3be74b7d8cdf959fd6dbf7b2d383f46dc599e1a7f14eb46d3e5b41efd55157f8',
	),
);
$parent_hashes = array(
	'mirror' => array(
		'includes/integrations/ticketing-verifications.php' => '2cac369f343d890120d3d527008e24d97c83d27cfb96ebab2459a7ccadf4a025',
		'includes/integrations/ticketing-claims-admin.php' => '9c33acb2dc5c4c7f1d3a52dfcb8b06e02b5c6129aab57f72a4900bc0fc5ac82c',
		'includes/integrations/square-ticket-mirror.php' => '7e4fe2e986f263e3241dc0dd714473e21a35d41f6b3cab26f9364fc051252a57',
		'includes/integrations/square-sync-firewall.php' => '3be74b7d8cdf959fd6dbf7b2d383f46dc599e1a7f14eb46d3e5b41efd55157f8',
	),
	'shadow' => array(
		'includes/integrations/ticketing-verifications.php' => '1a212622df49ea057d5c0ea062152412ca37eeaebf98e4a745f8a47b9e6381a4',
		'includes/integrations/ticketing-claims-admin.php' => 'b1c71b2ebb31af489d2e496160287b75f407fa43a55e0bcbb4419a704dcf0666',
		'includes/integrations/square-ticket-mirror.php' => '7e4fe2e986f263e3241dc0dd714473e21a35d41f6b3cab26f9364fc051252a57',
		'includes/integrations/square-sync-firewall.php' => '3be74b7d8cdf959fd6dbf7b2d383f46dc599e1a7f14eb46d3e5b41efd55157f8',
	),
);
$stripped_sources = array('mirror' => array(), 'shadow' => array());
foreach (array('mirror' => $mirror_sources, 'shadow' => $shadow_sources) as $tree => $sources) {
	foreach ($sources as $relative_file => $source) {
		$stripped = g10_support_strip_directives($source, $directives_by_file[$relative_file]);
		$stripped_sources[$tree][$relative_file] = $stripped;
		g10_support_same($stripped_hashes[$tree][$relative_file], hash('sha256', $stripped), 'Full annotation-stripped source hash changed: ' . $tree . ' ' . $relative_file);
		$projected = g10_support_project_to_parent($stripped, $relative_file);
		g10_support_same($parent_hashes[$tree][$relative_file], hash('sha256', $projected), 'Whole-source projection outside the two identifier preparations changed: ' . $tree . ' ' . $relative_file);
	}
}

$mutation_anchors = array(
	'includes/integrations/ticketing-verifications.php' => array("'posts_per_page' => 1", "'posts_per_page' => 2"),
	'includes/integrations/ticketing-claims-admin.php' => array("'posts_per_page' => 200", "'posts_per_page' => 201"),
	'includes/integrations/square-ticket-mirror.php' => array('ORDER BY id DESC', 'ORDER BY id ASC'),
	'includes/integrations/square-sync-firewall.php' => array('min(1000, absint($limit))', 'min(999, absint($limit))'),
);
foreach ($stripped_sources as $tree => $sources) {
	foreach ($sources as $relative_file => $source) {
		$mutated = str_replace($mutation_anchors[$relative_file][0], $mutation_anchors[$relative_file][1], $source, $count);
		g10_support_assert($count > 0, 'Runtime mutation anchor should exist: ' . $relative_file);
		g10_support_assert(hash('sha256', $mutated) !== $stripped_hashes[$tree][$relative_file], 'Non-comment runtime mutation must fail the full-source hash: ' . $tree . ' ' . $relative_file);
	}
}

g10_support_same($mirror_sources['includes/integrations/square-ticket-mirror.php'], $shadow_sources['includes/integrations/square-ticket-mirror.php'], 'Square mirror should retain whole-file mirror/shadow parity.');
g10_support_same($mirror_sources['includes/integrations/square-sync-firewall.php'], $shadow_sources['includes/integrations/square-sync-firewall.php'], 'Square firewall should retain whole-file mirror/shadow parity.');
g10_support_assert($mirror_sources['includes/integrations/ticketing-verifications.php'] !== $shadow_sources['includes/integrations/ticketing-verifications.php'], 'Verification whole-file divergence should remain intentional.');
g10_support_assert($mirror_sources['includes/integrations/ticketing-claims-admin.php'] !== $shadow_sources['includes/integrations/ticketing-claims-admin.php'], 'Claims-admin whole-file divergence should remain intentional.');
foreach (array(
	'includes/integrations/ticketing-verifications.php' => array('bvmgr_ticketing_verification_migrate_legacy_post_type_once', 'bvmgr_ticketing_verification_get_latest_request'),
	'includes/integrations/ticketing-claims-admin.php' => array('bvmgr_ticketing_claims_event_ticket_options', 'bvmgr_ticketing_claims_reservation_usage_map', 'bvmgr_ticketing_claims_get_event_verified_ticket_contexts'),
) as $relative_file => $functions) {
	foreach ($functions as $function) {
		g10_support_same(g10_support_extract_function($mirror_sources[$relative_file], $function), g10_support_extract_function($shadow_sources[$relative_file], $function), 'Owned function mirror/shadow parity changed: ' . $function);
	}
}
$admin_query_block = g10_support_extract_block($mirror_sources['includes/integrations/ticketing-verifications.php'], '$query_status = ($status_filter === \'all\')', '$requests = get_posts($query_args);');
g10_support_same($admin_query_block, g10_support_extract_block($shadow_sources['includes/integrations/ticketing-verifications.php'], '$query_status = ($status_filter === \'all\')', '$requests = get_posts($query_args);'), 'Owned verification admin query block parity changed.');
$shadow_cleanup = g10_support_extract_function($shadow_sources['includes/integrations/ticketing-verifications.php'], 'bvmgr_ticketing_verification_cleanup_old_proofs');
g10_support_contains("'key'     => 'proof_file_path'", $shadow_cleanup, 'Live-only cleanup meta-query boundary should remain present.');
g10_support_contains("'compare' => 'EXISTS'", $shadow_cleanup, 'Live-only cleanup EXISTS semantics changed.');
g10_support_not_contains('WordPress.DB.SlowDBQuery.slow_db_query_meta_query', $shadow_cleanup, 'Out-of-scope live-only cleanup meta query must remain unsuppressed.');

g10_support_contains("\$action . '\" class=\"vms-claims-detached-form\"", $mirror_sources['includes/integrations/ticketing-claims-admin.php'], 'Adjacent claims-admin output finding source should remain present.');
g10_support_not_contains('WordPress.Security.EscapeOutput.OutputNotEscaped', $mirror_sources['includes/integrations/ticketing-claims-admin.php'], 'Adjacent claims-admin OutputNotEscaped finding must remain unsuppressed.');

$owned_functions = array(
	array('includes/integrations/ticketing-verifications.php', 'bvmgr_ticketing_verification_migrate_legacy_post_type_once'),
	array('includes/integrations/ticketing-verifications.php', 'bvmgr_ticketing_verification_get_latest_request'),
	array('includes/integrations/ticketing-claims-admin.php', 'bvmgr_ticketing_claims_event_ticket_options'),
	array('includes/integrations/ticketing-claims-admin.php', 'bvmgr_ticketing_claims_reservation_usage_map'),
	array('includes/integrations/ticketing-claims-admin.php', 'bvmgr_ticketing_claims_get_event_verified_ticket_contexts'),
	array('includes/integrations/square-ticket-mirror.php', 'vms_square_ticket_mirror_log'),
	array('includes/integrations/square-ticket-mirror.php', 'vms_square_ticket_mirror_recent_logs'),
	array('includes/integrations/square-sync-firewall.php', 'vms_square_firewall_query_product_ids'),
);
$owned_runtime_source = $admin_query_block;
foreach ($owned_functions as $owned_function) {
	$function_source = g10_support_extract_function($mirror_sources[$owned_function[0]], $owned_function[1]);
	$owned_runtime_source .= "\n" . $function_source;
	eval($function_source);
}
g10_support_not_contains('wp_cache_', $owned_runtime_source, 'Owned operations should not gain persistent object caching.');
g10_support_not_contains('set_transient(', $owned_runtime_source, 'Owned operations should not gain persistent transient caching.');

$admin_query_callable = eval(
	'return static function (string $status_filter, string $program_filter, string $order): array {' . "\n"
	. $admin_query_block . "\n"
	. 'return array($query_args, $requests);' . "\n"
	. '};'
);
g10_support_assert(is_callable($admin_query_callable), 'Verification admin query block should be executable.');

// One-time verification migration preserves option gating, prepare order, rendered SQL, and failure-marker behavior.
g10_support_reset_runtime();
$wpdb = new G10_Support_WPDB_Spy('wp_migrate_');
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['g10_support_options'] = array('vms_ticketing_verification_pt_migrated_v1' => '1');
bvmgr_ticketing_verification_migrate_legacy_post_type_once();
g10_support_same(array(), $wpdb->operations, 'Completed migration marker should avoid database work.');
g10_support_same(array(), $GLOBALS['g10_support_option_updates'], 'Completed migration marker should remain unchanged.');

$wpdb = new G10_Support_WPDB_Spy('wp_migrate_');
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['g10_support_options'] = array();
$GLOBALS['g10_support_legacy_type'] = 'same_type';
$GLOBALS['g10_support_canonical_type'] = 'same_type';
bvmgr_ticketing_verification_migrate_legacy_post_type_once();
g10_support_same(array(), $wpdb->operations, 'Equal post types should avoid an UPDATE.');
g10_support_same(array(array('vms_ticketing_verification_pt_migrated_v1', '1', false)), $GLOBALS['g10_support_option_updates'], 'Equal post types should retain lifecycle-marker behavior.');

$wpdb = new G10_Support_WPDB_Spy('wp_migrate_');
$wpdb->query_queue[] = false;
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['g10_support_options'] = array();
$GLOBALS['g10_support_option_updates'] = array();
$GLOBALS['g10_support_legacy_type'] = 'vms_verification_request';
$GLOBALS['g10_support_canonical_type'] = 'vms_verify_req';
bvmgr_ticketing_verification_migrate_legacy_post_type_once();
g10_support_same(1, count($wpdb->prepares), 'Migration should prepare exactly once.');
g10_support_same(array('vms_verify_req', 'vms_verification_request'), $wpdb->prepares[0]['args'], 'Migration value argument order changed.');
g10_support_same("UPDATE wp_migrate_posts SET post_type = 'vms_verify_req' WHERE post_type = 'vms_verification_request'", g10_support_normalize_sql($wpdb->prepares[0]['sql']), 'Migration rendered SQL changed.');
g10_support_same(array('prepare', 'query'), array_column($wpdb->operations, 'kind'), 'Migration prepare/query ordering changed.');
g10_support_same(false, $wpdb->operations[1]['result'], 'Migration failure result should remain observable to the immediate call boundary.');
g10_support_same(array(array('vms_ticketing_verification_pt_migrated_v1', '1', false)), $GLOBALS['g10_support_option_updates'], 'Migration should retain existing completion-marker behavior after query failure.');

// Latest verification request remains an exact-user, one-row query with empty/failure/result branches.
g10_support_reset_runtime();
g10_support_same(null, bvmgr_ticketing_verification_get_latest_request(0), 'Invalid verification user should fail closed.');
g10_support_same(array(), $GLOBALS['g10_support_get_posts_calls'], 'Invalid verification user should not query.');
g10_support_same(null, bvmgr_ticketing_verification_get_latest_request(7, array('', '!!!')), 'Empty sanitized statuses should fail closed.');
g10_support_same(array(), $GLOBALS['g10_support_get_posts_calls'], 'Empty statuses should not query.');
$latest = new WP_Post(501);
$GLOBALS['g10_support_get_posts_queue'][] = array($latest);
g10_support_same($latest, bvmgr_ticketing_verification_get_latest_request(-7, array('Pending!', 'pending', 'Denied!')), 'Latest verification result changed.');
g10_support_same(array(
	'post_type' => array('vms_verify_req', 'vms_verification_request'),
	'post_status' => array('pending', 'denied'),
	'posts_per_page' => 1,
	'orderby' => 'date',
	'order' => 'DESC',
	'no_found_rows' => true,
	'meta_key' => 'user_id',
	'meta_value' => '7',
), $GLOBALS['g10_support_get_posts_calls'][0], 'Latest verification get_posts arguments changed.');
$GLOBALS['g10_support_get_posts_queue'][] = array('not-a-post');
g10_support_same(null, bvmgr_ticketing_verification_get_latest_request(7, array('pending')), 'Non-post result should fail closed.');
g10_support_same(2, count($GLOBALS['g10_support_get_posts_calls']), 'Latest verification lookup should remain request-fresh without a new cache.');

// Verification admin listing executes the exact owned production query block for filtered and unfiltered cases.
g10_support_reset_runtime();
$GLOBALS['g10_support_get_posts_queue'][] = array(new WP_Post(601));
list($admin_args, $admin_results) = $admin_query_callable('pending', 'vip', 'asc');
g10_support_same(array(
	'post_type' => array('vms_verify_req', 'vms_verification_request'),
	'post_status' => array('pending'),
	'posts_per_page' => 250,
	'orderby' => 'date',
	'order' => 'ASC',
	'no_found_rows' => true,
	'meta_query' => array(array('key' => 'program', 'value' => 'vip', 'compare' => '=')),
), $admin_args, 'Filtered verification admin query arguments changed.');
g10_support_same($GLOBALS['g10_support_get_posts_calls'][0], $admin_args, 'Filtered admin query must execute its complete production argument array.');
g10_support_same(601, $admin_results[0]->ID, 'Filtered admin result passthrough changed.');
$GLOBALS['g10_support_get_posts_queue'][] = array();
list($admin_all_args, $admin_all_results) = $admin_query_callable('all', 'all', 'desc');
g10_support_same(array('pending', 'approved', 'denied'), $admin_all_args['post_status'], 'All-status admin query changed.');
g10_support_same('DESC', $admin_all_args['order'], 'Admin sort direction changed.');
g10_support_assert(!array_key_exists('meta_query', $admin_all_args), 'All-program admin query should not add a meta join.');
g10_support_same(array(), $admin_all_results, 'Empty admin results changed.');

// Claims event-ticket options retain configuration precedence, deduplication, exact meta fallback, args, and results.
g10_support_reset_runtime();
$GLOBALS['g10_support_config'] = array('tickets' => array(
	array('ticket_key' => 'vip', 'title' => 'VIP Pass'),
	array('ticket_key' => 'disabled', 'title' => 'Disabled', 'enabled' => false),
	array('key' => 'ga', 'title' => 'General Admission'),
));
$GLOBALS['g10_support_sync'] = array('map' => array('tickets' => array(
	'vip' => array('woo_product_id' => 101),
	'ga' => array('woo_product_id' => 102),
)));
$GLOBALS['g10_support_get_posts_queue'][] = array(101, 103, 0, 103);
$GLOBALS['g10_support_meta'][103]['_vms_ticket_key'] = '';
$GLOBALS['g10_support_meta'][103]['_vms_ticketing_ticket_key'] = 'legacy-key';
$GLOBALS['g10_support_titles'][103] = 'Legacy Product';
$options = bvmgr_ticketing_claims_event_ticket_options(44, 55);
g10_support_same(array(
	array('product_id' => 101, 'ticket_key' => 'vip', 'label' => 'VIP Pass'),
	array('product_id' => 102, 'ticket_key' => 'ga', 'label' => 'General Admission'),
	array('product_id' => 103, 'ticket_key' => 'legacy-key', 'label' => 'Legacy Product'),
), $options, 'Event-ticket option results/deduplication changed.');
$event_link_query = array(
	'relation' => 'OR',
	array('key' => '_vms_ticket_event_id', 'value' => 55, 'compare' => '=', 'type' => 'NUMERIC'),
	array('key' => '_tribe_wooticket_for_event', 'value' => 55, 'compare' => '=', 'type' => 'NUMERIC'),
);
g10_support_same(array(
	'post_type' => 'product',
	'post_status' => array('publish', 'private', 'draft', 'pending'),
	'fields' => 'ids',
	'posts_per_page' => 200,
	'meta_query' => $event_link_query,
), $GLOBALS['g10_support_get_posts_calls'][0], 'Event-ticket fallback get_posts arguments changed.');
g10_support_reset_runtime();
g10_support_same(array(), bvmgr_ticketing_claims_event_ticket_options(0, 0), 'Empty ticket-option inputs should return empty.');
g10_support_same(array(), $GLOBALS['g10_support_get_posts_calls'], 'Empty ticket-option inputs should not query.');

// Reservation usage prepares the identifier/value, preserves grouped results/failures, and stays request-fresh.
$wpdb = new G10_Support_WPDB_Spy('wp_claims_');
$GLOBALS['wpdb'] = $wpdb;
g10_support_same(array(), bvmgr_ticketing_claims_reservation_usage_map(0), 'Invalid reservation event should fail closed.');
g10_support_same(array(), $wpdb->operations, 'Invalid reservation event should not touch the database.');
$wpdb->results_queue[] = array(
	array('direct_grant_id' => '4', 'status' => 'reserved', 'cnt' => '2'),
	array('direct_grant_id' => '4', 'status' => 'claimed', 'cnt' => '3'),
	array('direct_grant_id' => '0', 'status' => 'reserved', 'cnt' => '9'),
	array('direct_grant_id' => '5', 'status' => '', 'cnt' => '4'),
);
g10_support_same(array(4 => array('reserved' => 2, 'claimed' => 3)), bvmgr_ticketing_claims_reservation_usage_map(77), 'Reservation grouped result mapping changed.');
g10_support_same(array('wp_claims_vms_ticketing_claim_reservations', 77), $wpdb->prepares[0]['args'], 'Reservation prepare identifier/value order changed.');
g10_support_same('SELECT direct_grant_id, status, COUNT(1) AS cnt FROM `wp_claims_vms_ticketing_claim_reservations` WHERE event_id = 77 AND direct_grant_id > 0 GROUP BY direct_grant_id, status', g10_support_normalize_sql($wpdb->prepares[0]['sql']), 'Reservation rendered SQL changed.');
g10_support_same(array('prepare', 'get_results'), array_column($wpdb->operations, 'kind'), 'Reservation prepare/read ordering changed.');
$wpdb->results_queue[] = false;
g10_support_same(array(), bvmgr_ticketing_claims_reservation_usage_map(77), 'Reservation database failure should remain an empty map.');
g10_support_same(2, count(array_filter($wpdb->operations, static fn(array $call): bool => $call['kind'] === 'get_results')), 'Reservation aggregate should remain request-fresh without persistent caching.');

// Verified-ticket contexts preserve exact query arguments, filtering, invalid and empty result branches.
g10_support_reset_runtime();
g10_support_same(array(), bvmgr_ticketing_claims_get_event_verified_ticket_contexts(0), 'Invalid verified-ticket event should fail closed.');
g10_support_same(array(), $GLOBALS['g10_support_get_posts_calls'], 'Invalid verified-ticket event should not query.');
$GLOBALS['g10_support_get_posts_queue'][] = array(201, 0, 202, 203);
$GLOBALS['g10_support_contexts'] = array(
	201 => array('visibility_mode' => 'verified', 'ticket_key' => 'vip'),
	202 => array('visibility_mode' => 'public', 'ticket_key' => 'ga'),
	203 => array('visibility_mode' => 'verified', 'ticket_key' => 'artist'),
);
$GLOBALS['g10_support_titles'] = array(201 => 'VIP Product', 203 => 'Artist Product');
$contexts = bvmgr_ticketing_claims_get_event_verified_ticket_contexts(88);
g10_support_same(array(
	array('visibility_mode' => 'verified', 'ticket_key' => 'vip', 'product_id' => 201, 'product_title' => 'VIP Product'),
	array('visibility_mode' => 'verified', 'ticket_key' => 'artist', 'product_id' => 203, 'product_title' => 'Artist Product'),
), $contexts, 'Verified-ticket context filtering/result mapping changed.');
$verified_query = $GLOBALS['g10_support_get_posts_calls'][0];
g10_support_same(300, $verified_query['posts_per_page'], 'Verified-ticket product cap changed.');
g10_support_same(array(
	'relation' => 'OR',
	array('key' => '_vms_ticket_event_id', 'value' => 88, 'compare' => '=', 'type' => 'NUMERIC'),
	array('key' => '_tribe_wooticket_for_event', 'value' => 88, 'compare' => '=', 'type' => 'NUMERIC'),
), $verified_query['meta_query'], 'Verified-ticket meta query changed.');
g10_support_same(array('post_type', 'post_status', 'fields', 'posts_per_page', 'meta_query'), array_keys($verified_query), 'Verified-ticket query gained or lost arguments.');
$GLOBALS['g10_support_get_posts_queue'][] = array();
g10_support_same(array(), bvmgr_ticketing_claims_get_event_verified_ticket_contexts(88), 'Empty verified-ticket result changed.');

// Square log insert preserves typed data/order and ignores the immediate insert failure as before.
g10_support_reset_runtime();
$wpdb = new G10_Support_WPDB_Spy('wp_square_');
$wpdb->insert_queue[] = false;
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['g10_support_user_id'] = 22;
vms_square_ticket_mirror_log(9, 'Mirror SUCCESS!', array(
	'source_model' => array('event_plan_id' => 70, 'tec_event_id' => 80),
	'status_before' => 'Mirror Stale!',
	'status_after' => 'Mirrored',
	'item_id' => ' item-1 ',
	'variation_id' => ' var-2 ',
	'location_id' => ' loc-3 ',
	'request_json' => '{"request":true}',
	'response_json' => '{"response":false}',
	'error_code' => 'No Error!',
	'error_message' => '<b>none</b>',
));
g10_support_same(1, count($wpdb->operations), 'Square log should perform exactly one immediate insert.');
$insert = $wpdb->operations[0];
g10_support_same('insert', $insert['kind'], 'Square log operation changed.');
g10_support_same('wp_square_vms_square_ticket_mirror_log', $insert['table'], 'Square log table changed.');
g10_support_same(array(
	'product_id' => 9,
	'event_plan_id' => 70,
	'tec_event_id' => 80,
	'action' => 'mirrorsuccess',
	'status_before' => 'mirrorstale',
	'status_after' => 'mirrored',
	'item_id' => 'item-1',
	'variation_id' => 'var-2',
	'location_id' => 'loc-3',
	'request_json' => '{"request":true}',
	'response_json' => '{"response":false}',
	'error_code' => 'noerror',
	'error_message' => 'none',
	'actor_user_id' => 22,
	'created_at_gmt' => '2026-08-08 12:34:56',
), $insert['data'], 'Square log insert data/order changed.');
g10_support_same(array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'), $insert['format'], 'Square log formats changed.');
g10_support_same(false, $insert['result'], 'Square insert failure should remain immediate and ignored by the void logger.');

// Square recent logs preserve identifier/value preparation, limit clamps, failures, results, and no cache.
$wpdb = new G10_Support_WPDB_Spy('wp_square_');
$GLOBALS['wpdb'] = $wpdb;
g10_support_same(array(), vms_square_ticket_mirror_recent_logs(0, 8), 'Invalid Square product should fail closed.');
g10_support_same(array(), $wpdb->operations, 'Invalid Square product should not query.');
$row = array('id' => 1, 'product_id' => 9, 'action' => 'mirrored');
$wpdb->results_queue[] = array($row);
g10_support_same(array($row), vms_square_ticket_mirror_recent_logs(-9, 999), 'Square recent-log result changed.');
g10_support_same(array('wp_square_vms_square_ticket_mirror_log', 9, 50), $wpdb->prepares[0]['args'], 'Square recent-log prepare args/clamp changed.');
g10_support_same('SELECT * FROM `wp_square_vms_square_ticket_mirror_log` WHERE product_id = 9 ORDER BY id DESC LIMIT 50', g10_support_normalize_sql($wpdb->prepares[0]['sql']), 'Square recent-log rendered SQL changed.');
g10_support_same(array('prepare', 'get_results'), array_column($wpdb->operations, 'kind'), 'Square recent-log prepare/read ordering changed.');
$wpdb->results_queue[] = false;
g10_support_same(array(), vms_square_ticket_mirror_recent_logs(9, 0), 'Square recent-log failure should remain empty.');
g10_support_same(array('wp_square_vms_square_ticket_mirror_log', 9, 1), $wpdb->prepares[1]['args'], 'Square recent-log minimum limit changed.');
g10_support_same(2, count(array_filter($wpdb->operations, static fn(array $call): bool => $call['kind'] === 'get_results')), 'Square recent logs should remain request-fresh without persistent caching.');

// Firewall batches preserve cursor/limit preparation, rendered SQL, normalization, failures, and no cache.
$wpdb = new G10_Support_WPDB_Spy('wp_firewall_');
$GLOBALS['wpdb'] = $wpdb;
$wpdb->col_queue[] = array('7', 0, '-8', 'bad', 7);
g10_support_same(array(7, 8, 7), vms_square_firewall_query_product_ids(-5, 5000), 'Firewall product ID normalization/order changed.');
g10_support_same(array(5, 1000), $wpdb->prepares[0]['args'], 'Firewall cursor/maximum limit preparation changed.');
g10_support_same("SELECT ID FROM wp_firewall_posts WHERE post_type IN ('product', 'product_variation') AND post_status NOT IN ('trash', 'auto-draft', 'inherit') AND ID > 5 ORDER BY ID ASC LIMIT 1000", g10_support_normalize_sql($wpdb->prepares[0]['sql']), 'Firewall rendered SQL changed.');
g10_support_same(array('prepare', 'get_col'), array_column($wpdb->operations, 'kind'), 'Firewall prepare/read ordering changed.');
$wpdb->col_queue[] = false;
g10_support_same(array(), vms_square_firewall_query_product_ids(0, 0), 'Firewall database failure should remain empty.');
g10_support_same(array(0, 1), $wpdb->prepares[1]['args'], 'Firewall minimum cursor/limit preparation changed.');
g10_support_same(2, count(array_filter($wpdb->operations, static fn(array $call): bool => $call['kind'] === 'get_col')), 'Firewall batch query should remain request-fresh without persistent caching.');

fwrite(STDOUT, "G10 support repository SQL remediation checks passed.\n");
