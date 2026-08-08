<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('VMS_VENDOR_APP_CPT', 'vms_vendor_app');
define('VMS_VENDOR_APP_CPT_LEGACY', 'vms_vendor_application');
define('VMS_VENDOR_CPT', 'vms_vendor');

final class WP_Error
{
}

final class WP_Query
{
	/** @var array<int,array<string,mixed>> */
	public static array $calls = array();
	/** @var int[] */
	public static array $found_posts_queue = array();
	public int $found_posts;

	public function __construct(array $args)
	{
		self::$calls[] = $args;
		$this->found_posts = self::$found_posts_queue === array() ? 0 : (int) array_shift(self::$found_posts_queue);
		$GLOBALS['g11_vendor_timeline'][] = array('kind' => 'WP_Query', 'args' => $args, 'found_posts' => $this->found_posts);
	}
}

final class G11_Vendor_WPDB_Spy
{
	public string $posts;
	/** @var array<int,array{template:string,args:array<int,mixed>,sql:string}> */
	public array $prepares = array();
	/** @var array<int,array{sql:string,result:mixed}> */
	public array $queries = array();
	/** @var array<int,mixed> */
	public array $query_queue = array();

	public function __construct(string $prefix = 'wp_')
	{
		$this->posts = $prefix . 'posts';
	}

	public function prepare(string $template, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}
		preg_match_all('/(?<!%)%(?:\d+\$)?[sdfi]/', $template, $matches);
		if (count($matches[0]) !== count($args)) {
			throw new RuntimeException('Prepare placeholder mismatch: ' . $template);
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
				if ($type === 'i') {
					return '`' . str_replace('`', '``', (string) $value) . '`';
				}
				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$template
		);
		if (!is_string($sql) || $index !== count($args)) {
			throw new RuntimeException('Prepare argument mismatch.');
		}
		$call = array('template' => $template, 'args' => array_values($args), 'sql' => $sql);
		$this->prepares[] = $call;
		$GLOBALS['g11_vendor_timeline'][] = array('kind' => 'prepare') + $call;
		return $sql;
	}

	public function query(string $sql)
	{
		$result = $this->query_queue === array() ? 1 : array_shift($this->query_queue);
		$this->queries[] = array('sql' => $sql, 'result' => $result);
		$GLOBALS['g11_vendor_timeline'][] = array('kind' => 'query', 'sql' => $sql, 'result' => $result);
		return $result;
	}
}

$GLOBALS['g11_vendor_options'] = array();
$GLOBALS['g11_vendor_option_updates'] = array();
$GLOBALS['g11_vendor_added_options'] = array();
$GLOBALS['g11_vendor_timeline'] = array();
$GLOBALS['g11_vendor_confirmation_key'] = '_vms_app_confirmation_state';
$GLOBALS['g11_vendor_get_posts_calls'] = array();
$GLOBALS['g11_vendor_get_posts_queue'] = array();
$GLOBALS['g11_vendor_guard_result'] = array('lock' => 'owned');
$GLOBALS['g11_vendor_meta'] = array();
$GLOBALS['g11_vendor_post_types'] = array();
$GLOBALS['g11_vendor_sync_results'] = array();
$GLOBALS['g11_vendor_link_results'] = array();
$GLOBALS['g11_vendor_now'] = '2026-08-08 13:00:00';
$GLOBALS['g11_vendor_invalid_canonical'] = '!!!';
$GLOBALS['g11_vendor_invalid_legacy'] = 'vms_vendor_application';

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

function get_option(string $key, $default = false)
{
	return array_key_exists($key, $GLOBALS['g11_vendor_options']) ? $GLOBALS['g11_vendor_options'][$key] : $default;
}

function update_option(string $key, $value, $autoload = null): bool
{
	$GLOBALS['g11_vendor_options'][$key] = $value;
	$GLOBALS['g11_vendor_option_updates'][] = array($key, $value, $autoload);
	$GLOBALS['g11_vendor_timeline'][] = array('kind' => 'update_option', 'key' => $key, 'value' => $value, 'autoload' => $autoload);
	return true;
}

function add_option(string $key, $value, string $deprecated = '', $autoload = null): bool
{
	$GLOBALS['g11_vendor_options'][$key] = $value;
	$GLOBALS['g11_vendor_added_options'][] = array($key, $value, $deprecated, $autoload);
	$GLOBALS['g11_vendor_timeline'][] = array('kind' => 'add_option', 'key' => $key, 'value' => $value);
	return true;
}

/** @return string[] */
function vms_vendor_app_cpt_slugs(): array
{
	return array(VMS_VENDOR_APP_CPT, VMS_VENDOR_APP_CPT_LEGACY);
}

function vms_vendor_app_meta_key(string $field): string
{
	unset($field);
	return (string) $GLOBALS['g11_vendor_confirmation_key'];
}

function get_posts(array $args)
{
	$GLOBALS['g11_vendor_get_posts_calls'][] = $args;
	$GLOBALS['g11_vendor_timeline'][] = array('kind' => 'get_posts', 'args' => $args);
	return $GLOBALS['g11_vendor_get_posts_queue'] === array() ? array() : array_shift($GLOBALS['g11_vendor_get_posts_queue']);
}

function wp_date(string $format): string
{
	unset($format);
	return (string) $GLOBALS['g11_vendor_now'];
}

function vms_admin_guard_begin(string $name, array $args)
{
	$GLOBALS['g11_vendor_timeline'][] = array('kind' => 'guard_begin', 'name' => $name, 'args' => $args);
	return $GLOBALS['g11_vendor_guard_result'];
}

function vms_admin_guard_finish(array $guard, array $stats): void
{
	$GLOBALS['g11_vendor_timeline'][] = array('kind' => 'guard_finish', 'guard' => $guard, 'stats' => $stats);
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	return $GLOBALS['g11_vendor_meta'][$post_id][$key] ?? ($single ? '' : array());
}

function get_post_type(int $post_id): string
{
	return (string) ($GLOBALS['g11_vendor_post_types'][$post_id] ?? '');
}

function vms_vendor_app_sync_vendor_from_application(int $app_id, int $vendor_id): int
{
	$GLOBALS['g11_vendor_timeline'][] = array('kind' => 'sync', 'app_id' => $app_id, 'vendor_id' => $vendor_id);
	return (int) ($GLOBALS['g11_vendor_sync_results'][$app_id] ?? 0);
}

function vms_vendor_app_link_submitting_user_to_vendor(int $app_id, int $vendor_id, int $actor_user_id = 0)
{
	$GLOBALS['g11_vendor_timeline'][] = array('kind' => 'link', 'app_id' => $app_id, 'vendor_id' => $vendor_id, 'actor_user_id' => $actor_user_id);
	return $GLOBALS['g11_vendor_link_results'][$app_id] ?? 1;
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function g11_vendor_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g11_vendor_same($expected, $actual, string $message): void
{
	g11_vendor_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function g11_vendor_contains(string $needle, string $haystack, string $message): void
{
	g11_vendor_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function g11_vendor_not_contains(string $needle, string $haystack, string $message): void
{
	g11_vendor_assert(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle);
}

function g11_vendor_throws(callable $callback, string $message): void
{
	try {
		$callback();
	} catch (RuntimeException $exception) {
		return;
	}
	throw new RuntimeException($message);
}

function g11_vendor_extract_function(string $source, string $name): string
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

/** @param string[] $expected */
function g11_vendor_validate_directives(string $scope, array $expected): void
{
	if (preg_match('/phpcs:ignoreFile|phpcs:(?:disable|enable)[^\r\n]*(?:WordPress\.DB|PluginCheck\.Security\.DirectDB)/', $scope) === 1) {
		throw new RuntimeException('Broad PHPCS directive found.');
	}
	$allowed = array(
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => true,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => true,
		'WordPress.DB.PreparedSQL.NotPrepared' => true,
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => true,
	);
	$actual = array();
	foreach (preg_split('/\R/', $scope) ?: array() as $line) {
		if (
			strpos($line, 'phpcs:') === false
			|| (strpos($line, 'WordPress.DB') === false && strpos($line, 'PluginCheck.Security.DirectDB') === false)
		) {
			continue;
		}
		$line = trim($line);
		if (!preg_match('/^\/\/ phpcs:ignore ([^\s]+) -- (.+)$/', $line, $match)) {
			throw new RuntimeException('DB directive is not an exact justified line ignore: ' . $line);
		}
		foreach (explode(',', $match[1]) as $code) {
			if (!isset($allowed[$code])) {
				throw new RuntimeException('Broad or unowned DB code: ' . $code);
			}
		}
		if (strlen(trim($match[2])) < 48) {
			throw new RuntimeException('DB rationale is not occurrence-specific.');
		}
		$actual[] = $line;
	}
	sort($actual);
	sort($expected);
	g11_vendor_same($expected, $actual, 'Exact DB directive inventory changed.');
}

/** @param string[] $directives */
function g11_vendor_strip_directives(string $source, array $directives): string
{
	foreach ($directives as $directive) {
		$source = (string) preg_replace('/^[ \t]*' . preg_quote($directive, '/') . '\R/m', '', $source, 1, $removed);
		g11_vendor_same(1, $removed, 'Directive should strip exactly once: ' . $directive);
	}
	return $source;
}

function g11_vendor_normalize_sql(string $sql): string
{
	$normalized = preg_replace('/\s+/', ' ', trim($sql));
	return is_string($normalized) ? $normalized : '';
}

function g11_vendor_reset(): void
{
	$GLOBALS['g11_vendor_options'] = array();
	$GLOBALS['g11_vendor_option_updates'] = array();
	$GLOBALS['g11_vendor_added_options'] = array();
	$GLOBALS['g11_vendor_timeline'] = array();
	$GLOBALS['g11_vendor_confirmation_key'] = '_vms_app_confirmation_state';
	$GLOBALS['g11_vendor_get_posts_calls'] = array();
	$GLOBALS['g11_vendor_get_posts_queue'] = array();
	$GLOBALS['g11_vendor_guard_result'] = array('lock' => 'owned');
	$GLOBALS['g11_vendor_meta'] = array();
	$GLOBALS['g11_vendor_post_types'] = array();
	$GLOBALS['g11_vendor_sync_results'] = array();
	$GLOBALS['g11_vendor_link_results'] = array();
	WP_Query::$calls = array();
	WP_Query::$found_posts_queue = array();
}

$root = dirname(__DIR__);
$shadow_root = dirname(__DIR__, 3) . '/vms';
$runtime_path = $root . '/includes/vendor-applications.php';
$shadow_path = $shadow_root . '/includes/vendor-applications.php';
$artifact_path = '/tmp/wporg-wave4-integrated.nTzezu/plugin-check/plugin-check.strict.json';
$source = file_get_contents($runtime_path);
$shadow_source = file_get_contents($shadow_path);
g11_vendor_assert(is_string($source), 'Mirror vendor-applications source must be readable.');
g11_vendor_assert(is_string($shadow_source), 'Shadow vendor-applications source must be readable.');

$directives = array(
	'// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The approvals badge intentionally counts every pending application in the exact confirmed-or-legacy state while returning only one ID.',
	'// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The review filter intentionally counts every application in the selected exact confirmation state while returning only one ID.',
	'// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- One-time option-guarded post-type migration executes the immediately prepared UPDATE before recording its completion marker; a mutation result cannot be served from cache.',
	'// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The lock-guarded one-time backfill intentionally scans every approved application so no existing vendor source-of-truth link is skipped.',
);
$directive_anchors = array(
	$directives[0] => "'meta_query'     => array(",
	$directives[1] => "'meta_query' => \$meta_query,",
	$directives[2] => '$wpdb->query($sql);',
	$directives[3] => "'meta_query' => array(",
);

g11_vendor_validate_directives($source, $directives);
g11_vendor_validate_directives($shadow_source, $directives);
foreach ($directive_anchors as $directive => $anchor) {
	$pattern = '~^[ \\t]*' . preg_quote($directive, '~') . '\\R[ \\t]*' . preg_quote($anchor, '~') . '$~m';
	g11_vendor_same(1, preg_match($pattern, $source), 'Mirror directive moved away from its owned occurrence: ' . $anchor);
	g11_vendor_same(1, preg_match($pattern, $shadow_source), 'Shadow directive moved away from its owned occurrence: ' . $anchor);
}

$stripped_source = g11_vendor_strip_directives($source, $directives);
$stripped_shadow_source = g11_vendor_strip_directives($shadow_source, $directives);
g11_vendor_same(
	'9dcab9c95561bd23815dc0c755fb730f06c538e2ed383dd3243d3b15e6375f95',
	hash('sha256', $stripped_source),
	'Mirror runtime changed beyond the four approved comments.'
);
g11_vendor_same(
	'e440227fc398fe14234d897d89bc62fe8c37f7bebe13367dcb99e9a8b8d2cfdd',
	hash('sha256', $stripped_shadow_source),
	'Shadow runtime changed beyond the four approved comments.'
);
g11_vendor_assert(hash('sha256', $source) !== hash('sha256', $shadow_source), 'Intentional whole-file mirror/shadow divergence must remain.');

$mutated_source = preg_replace("/('posts_per_page' => )-1,/", '${1}1,', $stripped_source, 1, $mutation_count);
g11_vendor_same(1, $mutation_count, 'Runtime mutation control must alter one exhaustive backfill limit.');
g11_vendor_assert(is_string($mutated_source) && hash('sha256', $mutated_source) !== hash('sha256', $stripped_source), 'Stripped-source hash must reject a non-comment runtime mutation.');

g11_vendor_assert(is_file($artifact_path), 'Authoritative Wave 4 strict-JSON artifact is missing.');
g11_vendor_same(
	'278819f58c585c226824fd89d541fc5ab107c11897240e281683fa6abad8d179',
	hash_file('sha256', $artifact_path),
	'Authoritative Wave 4 strict-JSON artifact changed.'
);
$artifact = json_decode((string) file_get_contents($artifact_path), true);
g11_vendor_assert(is_array($artifact), 'Authoritative Wave 4 strict JSON must decode to an array.');
$file_rows = array_values(array_filter(
	$artifact,
	static fn(array $row): bool => ($row['file'] ?? '') === '/privateincludes/vendor-applications.php'
));
g11_vendor_same(18, count($file_rows), 'Same-file strict finding inventory changed.');
$db_rows = array_values(array_filter(
	$file_rows,
	static fn(array $row): bool => strpos((string) ($row['code'] ?? ''), 'WordPress.DB.') === 0
));
$actual_db_inventory = array_map(
	static fn(array $row): string => sprintf('%d:%d:%s', (int) $row['line'], (int) $row['column'], (string) $row['code']),
	$db_rows
);
$expected_db_inventory = array(
	'1052:9:WordPress.DB.SlowDBQuery.slow_db_query_meta_query',
	'1132:13:WordPress.DB.SlowDBQuery.slow_db_query_meta_query',
	'1202:9:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'1202:9:WordPress.DB.DirectDatabaseQuery.NoCaching',
	'1202:22:WordPress.DB.PreparedSQL.NotPrepared',
	'3213:17:WordPress.DB.SlowDBQuery.slow_db_query_meta_query',
);
sort($actual_db_inventory);
sort($expected_db_inventory);
g11_vendor_same($expected_db_inventory, $actual_db_inventory, 'Exact six-row G11 artifact inventory changed.');

$expected_code_counts = array(
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 1,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 1,
	'WordPress.DB.PreparedSQL.NotPrepared' => 1,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => 3,
);
$artifact_code_counts = array_count_values(array_column($db_rows, 'code'));
ksort($artifact_code_counts);
ksort($expected_code_counts);
g11_vendor_same($expected_code_counts, $artifact_code_counts, 'Artifact code split must remain D1/N1/P1/Q3.');

$adjacent_counts = array_count_values(array_column(array_values(array_filter(
	$file_rows,
	static fn(array $row): bool => strpos((string) ($row['code'] ?? ''), 'WordPress.DB.') !== 0
)), 'code'));
$expected_adjacent_counts = array(
	'PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent' => 1,
	'WordPress.PHP.DevelopmentFunctions.error_log_error_log' => 9,
	'WordPress.Security.EscapeOutput.OutputNotEscaped' => 2,
);
ksort($adjacent_counts);
ksort($expected_adjacent_counts);
g11_vendor_same($expected_adjacent_counts, $adjacent_counts, 'Adjacent non-DB artifact rows must remain outside this slice.');

$covered_code_counts = array();
foreach ($directives as $directive) {
	preg_match('/^\/\/ phpcs:ignore ([^ ]+) -- /', $directive, $matches);
	g11_vendor_assert(isset($matches[1]), 'Owned directive must expose exact codes.');
	foreach (explode(',', $matches[1]) as $code) {
		$covered_code_counts[$code] = ($covered_code_counts[$code] ?? 0) + 1;
	}
}
ksort($covered_code_counts);
g11_vendor_same($expected_code_counts, $covered_code_counts, 'Four exact directives must account for all six artifact rows and zero others.');

$invalid_directives = array(
	'// phpcs:disable WordPress.DB',
	'// phpcs:ignoreFile',
	'// phpcs:ignore WordPress.DB -- A broad one-line database ignore must not pass this guard even with a long rationale.',
	'// phpcs:ignore WordPress.DB.SlowDBQuery -- A family-level one-line database ignore must not pass this guard.',
	'// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- An unowned exact code must not pass this guard.',
	'// phpcs:ignore PluginCheck.Security.DirectDB -- A Plugin Check database family ignore must not pass this guard.',
	'// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.Security.EscapeOutput.OutputNotEscaped -- Mixed database and non-database suppressions must not pass this guard.',
);
foreach ($invalid_directives as $invalid_directive) {
	g11_vendor_throws(
		static function () use ($invalid_directive): void {
			g11_vendor_validate_directives($invalid_directive, array($invalid_directive));
		},
		'Broad, family, unowned, or mixed suppression guard failed: ' . $invalid_directive
	);
}

g11_vendor_same(9, substr_count($source, 'error_log('), 'All nine adjacent error_log findings must remain present.');
g11_vendor_contains('https://challenges.cloudflare.com/turnstile/v0/api.js', $source, 'Adjacent Turnstile offloaded-content finding must remain present.');
g11_vendor_contains('echo $msg;', $source, 'Adjacent unescaped message output must remain present.');
g11_vendor_contains('<?php echo $variant_map_json; ?>', $source, 'Adjacent unescaped JSON output must remain present.');
foreach (array(
	'WordPress.PHP.DevelopmentFunctions.error_log_error_log',
	'PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent',
	'WordPress.Security.EscapeOutput.OutputNotEscaped',
) as $unowned_code) {
	g11_vendor_not_contains('phpcs:ignore ' . $unowned_code, $source, 'Adjacent non-DB finding must not be suppressed: ' . $unowned_code);
}

$owned_functions = array(
	'vms_vendor_app_count_pending',
	'vms_vendor_app_count_by_review_filter',
	'vms_vendor_applications_migrate_legacy_post_type_once',
	'vms_vendor_app_backfill_vendor_sot_once',
);
foreach ($owned_functions as $owned_function) {
	$mirror_function = g11_vendor_extract_function($source, $owned_function);
	$shadow_function = g11_vendor_extract_function($shadow_source, $owned_function);
	g11_vendor_same(
		hash('sha256', $mirror_function),
		hash('sha256', $shadow_function),
		'Owned function parity changed for ' . $owned_function . '.'
	);
	eval($mirror_function);
}

$owned_source = implode("\n", array_map(
	static fn(string $function): string => g11_vendor_extract_function($source, $function),
	$owned_functions
));
g11_vendor_not_contains('wp_cache_', $owned_source, 'No cache contract may be added to owned queries.');
g11_vendor_not_contains('set_transient(', $owned_source, 'No persistent caching may be added to owned queries.');

g11_vendor_reset();
$GLOBALS['g11_vendor_confirmation_key'] = '';
WP_Query::$found_posts_queue = array(17);
g11_vendor_same(17, vms_vendor_app_count_pending(), 'Pending count must return WP_Query found_posts.');
$pending_meta_query = array(
	'relation' => 'AND',
	array(
		'key' => '_vms_app_status',
		'value' => 'pending',
		'compare' => '=',
	),
	array(
		'relation' => 'OR',
		array(
			'key' => '_vms_app_confirmation_state',
			'value' => 'confirmed',
			'compare' => '=',
		),
		array(
			'key' => '_vms_app_confirmation_state',
			'compare' => 'NOT EXISTS',
		),
	),
);
g11_vendor_same(array(
	'post_type' => array('vms_vendor_app', 'vms_vendor_application'),
	'post_status' => array('publish', 'draft', 'pending', 'private'),
	'posts_per_page' => 1,
	'fields' => 'ids',
	'meta_query' => $pending_meta_query,
), WP_Query::$calls[0], 'Pending count query args changed.');
g11_vendor_assert(!array_key_exists('no_found_rows', WP_Query::$calls[0]), 'Pending count must preserve found_posts calculation.');

g11_vendor_reset();
WP_Query::$found_posts_queue = array(0);
g11_vendor_same(0, vms_vendor_app_count_pending(), 'Empty pending count must remain zero.');
g11_vendor_same(1, count(WP_Query::$calls), 'Pending count must execute a fresh query on every invocation.');

$confirmation_key = '_vms_custom_confirmation_state';
$review_cases = array(
	'ready' => array(
		'count' => 8,
		'meta_query' => array(
			'relation' => 'AND',
			array('key' => '_vms_app_status', 'value' => 'pending', 'compare' => '='),
			array(
				'relation' => 'OR',
				array('key' => $confirmation_key, 'value' => 'confirmed', 'compare' => '='),
				array('key' => $confirmation_key, 'compare' => 'NOT EXISTS'),
			),
		),
	),
	'AWAITING_CONFIRMATION!!' => array(
		'count' => 3,
		'meta_query' => array(
			array('key' => $confirmation_key, 'value' => 'unconfirmed', 'compare' => '='),
		),
	),
	'expired_confirmation' => array(
		'count' => 2,
		'meta_query' => array(
			array('key' => $confirmation_key, 'value' => 'expired', 'compare' => '='),
		),
	),
);
foreach ($review_cases as $filter => $case) {
	g11_vendor_reset();
	$GLOBALS['g11_vendor_confirmation_key'] = $confirmation_key;
	WP_Query::$found_posts_queue = array($case['count']);
	g11_vendor_same($case['count'], vms_vendor_app_count_by_review_filter($filter), 'Review-filter count result changed for ' . $filter . '.');
	g11_vendor_same(array(
		'post_type' => array('vms_vendor_app', 'vms_vendor_application'),
		'post_status' => array('publish', 'draft', 'pending', 'private'),
		'posts_per_page' => 1,
		'fields' => 'ids',
		'meta_query' => $case['meta_query'],
	), WP_Query::$calls[0], 'Review-filter query args changed for ' . $filter . '.');
	g11_vendor_assert(!array_key_exists('no_found_rows', WP_Query::$calls[0]), 'Review-filter count must preserve found_posts calculation.');
}

g11_vendor_reset();
g11_vendor_same(0, vms_vendor_app_count_by_review_filter('not-a-review-filter'), 'Invalid review filter must return zero.');
g11_vendor_same(array(), WP_Query::$calls, 'Invalid review filter must not execute a query.');

$migration_source = g11_vendor_extract_function($source, 'vms_vendor_applications_migrate_legacy_post_type_once');
$invalid_migration_source = str_replace(
	'function vms_vendor_applications_migrate_legacy_post_type_once(',
	'function g11_vendor_applications_migrate_invalid_once(',
	$migration_source,
	$rename_count
);
g11_vendor_same(1, $rename_count, 'Invalid migration test must rename the owned function once.');
$invalid_migration_source = str_replace(
	'$canonical = sanitize_key((string) VMS_VENDOR_APP_CPT);',
	'$canonical = sanitize_key((string) $GLOBALS[\'g11_vendor_invalid_canonical\']);',
	$invalid_migration_source,
	$canonical_replacement_count
);
$invalid_migration_source = str_replace(
	'$legacy = sanitize_key((string) VMS_VENDOR_APP_CPT_LEGACY);',
	'$legacy = sanitize_key((string) $GLOBALS[\'g11_vendor_invalid_legacy\']);',
	$invalid_migration_source,
	$legacy_replacement_count
);
g11_vendor_same(1, $canonical_replacement_count, 'Invalid migration test must replace the canonical source once.');
g11_vendor_same(1, $legacy_replacement_count, 'Invalid migration test must replace the legacy source once.');
eval($invalid_migration_source);

$migration_marker = 'vms_vendor_app_pt_migrated_v1';
g11_vendor_reset();
$wpdb = new G11_Vendor_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['g11_vendor_options'][$migration_marker] = '1';
vms_vendor_applications_migrate_legacy_post_type_once();
g11_vendor_same(array(), $wpdb->prepares, 'Completed migration must not prepare SQL again.');
g11_vendor_same(array(), $wpdb->queries, 'Completed migration must not execute SQL again.');
g11_vendor_same(array(), $GLOBALS['g11_vendor_option_updates'], 'Completed migration must not rewrite its marker.');

g11_vendor_reset();
$wpdb = new G11_Vendor_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['g11_vendor_invalid_canonical'] = '!!!';
$GLOBALS['g11_vendor_invalid_legacy'] = 'vms_vendor_application';
g11_vendor_applications_migrate_invalid_once();
g11_vendor_same(array(), $wpdb->prepares, 'Invalid migration slugs must not prepare SQL.');
g11_vendor_same(array(), $wpdb->queries, 'Invalid migration slugs must not execute SQL.');
g11_vendor_same(array(array($migration_marker, '1', false)), $GLOBALS['g11_vendor_option_updates'], 'Invalid migration must still record its completion marker.');
g11_vendor_same(array('update_option'), array_column($GLOBALS['g11_vendor_timeline'], 'kind'), 'Invalid migration ordering changed.');

foreach (array(4, false, 0) as $query_result) {
	g11_vendor_reset();
	$wpdb = new G11_Vendor_WPDB_Spy();
	$wpdb->query_queue = array($query_result);
	$GLOBALS['wpdb'] = $wpdb;
	vms_vendor_applications_migrate_legacy_post_type_once();
	g11_vendor_same(1, count($wpdb->prepares), 'Migration must prepare exactly one statement.');
	g11_vendor_same(
		'UPDATE wp_posts SET post_type = %s WHERE post_type = %s',
		$wpdb->prepares[0]['template'],
		'Migration SQL template changed.'
	);
	g11_vendor_same(array('vms_vendor_app', 'vms_vendor_application'), $wpdb->prepares[0]['args'], 'Migration prepare args changed.');
	g11_vendor_same(
		"UPDATE wp_posts SET post_type = 'vms_vendor_app' WHERE post_type = 'vms_vendor_application'",
		g11_vendor_normalize_sql($wpdb->prepares[0]['sql']),
		'Migration rendered SQL changed.'
	);
	g11_vendor_same(array(array('sql' => $wpdb->prepares[0]['sql'], 'result' => $query_result)), $wpdb->queries, 'Migration query result contract changed.');
	g11_vendor_same(array(array($migration_marker, '1', false)), $GLOBALS['g11_vendor_option_updates'], 'Migration completion marker changed.');
	g11_vendor_same(array('prepare', 'query', 'update_option'), array_column($GLOBALS['g11_vendor_timeline'], 'kind'), 'Migration must record its marker after the immediate query result.');
}

$backfill_marker = 'vms_vendor_app_vendor_sot_backfill_0242431';
$expected_backfill_args = array(
	'post_type' => array('vms_vendor_app', 'vms_vendor_application'),
	'post_status' => 'any',
	'fields' => 'ids',
	'posts_per_page' => -1,
	'no_found_rows' => true,
	'update_post_meta_cache' => false,
	'update_post_term_cache' => false,
	'meta_query' => array(
		array(
			'key' => '_vms_app_status',
			'value' => 'approved',
			'compare' => '=',
		),
	),
);
$expected_guard_args = array(
	'task' => 'vendor_app_vendor_sot_backfill',
	'allow_action' => 'vendor_app_vendor_sot_backfill',
	'lock_name' => $backfill_marker,
	'lock_ttl' => 300,
);
$empty_stats = array(
	'apps_scanned' => 0,
	'vendors_synced' => 0,
	'meta_updates' => 0,
	'links_created' => 0,
	'ran_at' => '2026-08-08 13:00:00',
);

g11_vendor_reset();
$GLOBALS['g11_vendor_options'][$backfill_marker] = array('already' => 'done');
vms_vendor_app_backfill_vendor_sot_once();
g11_vendor_same(array(), $GLOBALS['g11_vendor_timeline'], 'Completed backfill must not acquire a guard or query again.');
g11_vendor_same(array(), $GLOBALS['g11_vendor_get_posts_calls'], 'Completed backfill must not execute get_posts.');

g11_vendor_reset();
$GLOBALS['g11_vendor_guard_result'] = false;
vms_vendor_app_backfill_vendor_sot_once();
g11_vendor_same(array('guard_begin'), array_column($GLOBALS['g11_vendor_timeline'], 'kind'), 'Rejected guard must stop the backfill immediately.');
g11_vendor_same($expected_guard_args, $GLOBALS['g11_vendor_timeline'][0]['args'], 'Backfill guard args changed.');
g11_vendor_same(array(), $GLOBALS['g11_vendor_get_posts_calls'], 'Rejected guard must prevent the exhaustive query.');
g11_vendor_same(array(), $GLOBALS['g11_vendor_added_options'], 'Rejected guard must not record a completion marker.');

foreach (array(array(), false) as $empty_result) {
	g11_vendor_reset();
	$GLOBALS['g11_vendor_get_posts_queue'] = array($empty_result);
	vms_vendor_app_backfill_vendor_sot_once();
	g11_vendor_same(array($expected_backfill_args), $GLOBALS['g11_vendor_get_posts_calls'], 'Empty/failure backfill query args changed.');
	g11_vendor_same(
		array($backfill_marker, $empty_stats, '', false),
		$GLOBALS['g11_vendor_added_options'][0],
		'Empty/failure backfill completion stats changed.'
	);
	g11_vendor_same(array('guard_begin', 'get_posts', 'add_option', 'guard_finish'), array_column($GLOBALS['g11_vendor_timeline'], 'kind'), 'Empty/failure backfill ordering changed.');
	g11_vendor_same($empty_stats, $GLOBALS['g11_vendor_timeline'][3]['stats'], 'Guard finish must receive empty/failure stats.');
}

g11_vendor_reset();
$GLOBALS['g11_vendor_get_posts_queue'] = array(array(10, 0, 11, 12, 13));
$GLOBALS['g11_vendor_meta'] = array(
	10 => array('_vms_vendor_id' => 20),
	11 => array('_vms_vendor_id' => 0),
	12 => array('_vms_vendor_id' => 22),
	13 => array('_vms_vendor_id' => 23),
);
$GLOBALS['g11_vendor_post_types'] = array(20 => 'vms_vendor', 22 => 'post', 23 => 'vms_vendor');
$GLOBALS['g11_vendor_sync_results'] = array(10 => 2, 13 => 1);
$GLOBALS['g11_vendor_link_results'] = array(10 => 1, 13 => new WP_Error());
vms_vendor_app_backfill_vendor_sot_once();
$populated_stats = array(
	'apps_scanned' => 4,
	'vendors_synced' => 2,
	'meta_updates' => 3,
	'links_created' => 1,
	'ran_at' => '2026-08-08 13:00:00',
);
g11_vendor_same(array($expected_backfill_args), $GLOBALS['g11_vendor_get_posts_calls'], 'Populated backfill must preserve its exhaustive approved-application query.');
g11_vendor_same(
	array($backfill_marker, $populated_stats, '', false),
	$GLOBALS['g11_vendor_added_options'][0],
	'Populated backfill stats or marker contract changed.'
);
g11_vendor_same(
	array('guard_begin', 'get_posts', 'sync', 'link', 'sync', 'link', 'add_option', 'guard_finish'),
	array_column($GLOBALS['g11_vendor_timeline'], 'kind'),
	'Populated backfill operation ordering changed.'
);
g11_vendor_same($populated_stats, $GLOBALS['g11_vendor_timeline'][7]['stats'], 'Guard finish must receive populated stats after marker creation.');

fwrite(STDOUT, "G11 vendor-applications repository SQL remediation checks passed.\n");
