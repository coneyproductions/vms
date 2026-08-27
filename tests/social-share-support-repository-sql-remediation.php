<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

final class VMS_Social_Support_WPDB_Spy
{
	public string $prefix = 'wp_';
	public array $log = array();
	public array $prepares = array();
	public array $get_var_queue = array();
	public array $get_row_queue = array();
	public array $get_results_queue = array();
	public array $insert_queue = array();

	public function prepare(string $query, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}

		preg_match_all('/(?<!%)%(?:\d+\$)?[sdfi]/', $query, $matches);
		if (count($matches[0]) !== count($args)) {
			throw new RuntimeException('Prepared-query placeholder count changed: ' . $query);
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
					return implode('.', array_map(
						static fn(string $part): string => '`' . str_replace('`', '``', $part) . '`',
						$parts
					));
				}
				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$query
		);

		if (!is_string($sql) || $index !== count($args) || preg_match('/(?<!%)%(?:\d+\$)?[sdfi]/', $sql) === 1) {
			throw new RuntimeException('Prepared-query arguments did not resolve exactly: ' . $query);
		}

		$call = array('kind' => 'prepare', 'query' => $query, 'args' => $args, 'sql' => $sql);
		$this->prepares[] = $call;
		$this->log[] = $call;
		return $sql;
	}

	public function get_var(string $sql)
	{
		$result = $this->shift($this->get_var_queue, null);
		$this->log[] = array('kind' => 'get_var', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_row(string $sql, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift($this->get_row_queue, null);
		$this->log[] = array('kind' => 'get_row', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_results(string $sql, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift($this->get_results_queue, array());
		$this->log[] = array('kind' => 'get_results', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function insert(string $table, array $data, array $format)
	{
		$result = $this->shift($this->insert_queue, 1);
		$this->log[] = array(
			'kind' => 'insert',
			'table' => $table,
			'data' => $data,
			'format' => $format,
			'result' => $result,
		);
		return $result;
	}

	public function esc_like(string $text): string
	{
		return addcslashes($text, '_%\\');
	}

	public function get_charset_collate(): string
	{
		return '';
	}

	private function shift(array &$queue, $default)
	{
		return $queue === array() ? $default : array_shift($queue);
	}
}

function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
	unset($hook_name, $callback, $priority, $accepted_args);
}

function current_time(string $type, bool $gmt = false): string
{
	unset($type, $gmt);
	return '2026-08-08 04:05:06';
}

function get_option(string $key, $default = false)
{
	return $GLOBALS['vms_social_support_options'][$key] ?? $default;
}

function update_option(string $key, $value, bool $autoload = false): bool
{
	unset($autoload);
	$GLOBALS['vms_social_support_options'][$key] = $value;
	return true;
}

function wp_parse_args($args, $defaults = array()): array
{
	return array_merge(is_array($defaults) ? $defaults : array(), is_array($args) ? $args : array());
}

function sanitize_key($value): string
{
	$value = is_scalar($value) ? strtolower((string) $value) : '';
	$clean = preg_replace('/[^a-z0-9_\-]/', '', $value);
	return is_string($clean) ? $clean : '';
}

function sanitize_text_field($value): string
{
	return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
}

function sanitize_title($value): string
{
	$value = is_scalar($value) ? strtolower(trim((string) $value)) : '';
	$clean = preg_replace('/[^a-z0-9]+/', '-', $value);
	return is_string($clean) ? trim($clean, '-') : '';
}

function absint($value): int
{
	return abs((int) $value);
}

function wp_json_encode($value)
{
	return json_encode($value);
}

function get_current_user_id(): int
{
	return 41;
}

function apply_filters(string $hook_name, $value, ...$args)
{
	unset($hook_name, $args);
	return $value;
}

function esc_url_raw(string $url): string
{
	return $url;
}

function add_query_arg(array $args, string $url): string
{
	$query = http_build_query($args);
	return $url . (strpos($url, '?') === false ? '?' : '&') . $query;
}

function social_check(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function social_same($expected, $actual, string $message): void
{
	social_check(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function social_contains(string $needle, string $haystack, string $message): void
{
	social_check(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function social_reset(VMS_Social_Support_WPDB_Spy $db): void
{
	$db->log = array();
	$db->prepares = array();
	$db->get_var_queue = array();
	$db->get_row_queue = array();
	$db->get_results_queue = array();
	$db->insert_queue = array();
}

function social_calls(VMS_Social_Support_WPDB_Spy $db, string $kind): array
{
	return array_values(array_filter(
		$db->log,
		static fn(array $call): bool => ($call['kind'] ?? '') === $kind
	));
}

function social_last_prepare(VMS_Social_Support_WPDB_Spy $db): array
{
	$call = end($db->prepares);
	social_check(is_array($call), 'Expected a prepared query call.');
	return $call;
}

function social_extract_function(string $source, string $name): string
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

function social_without_function(string $source, string $name): string
{
	return str_replace(social_extract_function($source, $name), '__OWNED_FUNCTION__', $source);
}

function social_validate_narrow_db_annotations(string $source): void
{
	if (preg_match('/phpcs:(?:disable|enable|ignoreFile)\b/i', $source) === 1) {
		throw new RuntimeException('Broad PHPCS suppression is forbidden in the social-support slice.');
	}
	$allowed = array(
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => true,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => true,
	);
	foreach (preg_split('/\R/', $source) ?: array() as $line) {
		if (strpos($line, 'phpcs:') === false || (strpos($line, 'WordPress.DB.') === false && strpos($line, 'PluginCheck.Security.DirectDB') === false)) {
			continue;
		}
		if (preg_match('/phpcs:ignore ([^\s]+) -- (.+)$/', $line, $matches) !== 1) {
			throw new RuntimeException('Every DB annotation must be one-line, exact, and justified: ' . $line);
		}
		foreach (explode(',', $matches[1]) as $code) {
			if (!isset($allowed[$code])) {
				throw new RuntimeException('Unclassified or broad DB suppression: ' . $code);
			}
		}
		if (strlen(trim($matches[2])) < 40) {
			throw new RuntimeException('DB annotation lacks an occurrence-specific reason.');
		}
	}
}

$db = new VMS_Social_Support_WPDB_Spy();
$GLOBALS['wpdb'] = $db;
$GLOBALS['vms_social_support_options'] = array();
$root = dirname(__DIR__);
$paths = array(
	'audit' => $root . '/includes/social-share/audit.php',
	'installer' => $root . '/includes/social-share/installer.php',
	'event_panel' => $root . '/includes/social-share/event-plan-panel.php',
	'template_engine' => $root . '/includes/social-share/template-engine.php',
);
$sources = array();
foreach ($paths as $key => $path) {
	$source = file_get_contents($path);
	social_check(is_string($source) && $source !== '', 'Social-support source should be readable: ' . $key);
	$sources[$key] = $source;
}

require $paths['installer'];
require $paths['audit'];
require $paths['template_engine'];
require $paths['event_panel'];

// Existing template rows short-circuit seeding after one fully prepared identifier count.
social_reset($db);
$db->get_var_queue = array(2);
bvmgr_social_seed_default_templates();
social_same(array('prepare', 'get_var'), array_column($db->log, 'kind'), 'Existing templates should prevent every seed insert.');
$prepare = social_last_prepare($db);
social_same('SELECT COUNT(*) FROM %i', $prepare['query'], 'Installer count SQL shape changed.');
social_same(array('wp_vms_social_templates'), $prepare['args'], 'Installer count should prepare only its table identifier.');
social_same('SELECT COUNT(*) FROM `wp_vms_social_templates`', $prepare['sql'], 'Installer count should execute fully prepared SQL.');

// Empty installations retain the exact four-row seed order, defaults, formats, and timestamp.
social_reset($db);
$db->get_var_queue = array(0);
bvmgr_social_seed_default_templates();
$inserts = social_calls($db, 'insert');
social_same(4, count($inserts), 'Empty installations should seed exactly four templates.');
social_same(array('facebook', 'linkedin', 'x', 'mock'), array_column(array_column($inserts, 'data'), 'platform'), 'Default template seed ordering changed.');
social_same(array(1, 0, 0, 0), array_column(array_column($inserts, 'data'), 'is_default'), 'Default template selection changed.');
foreach ($inserts as $insert) {
	social_same('wp_vms_social_templates', $insert['table'], 'Seed inserts should retain the plugin-owned templates table.');
	social_same(array('%s', '%s', '%s', '%s', '%d', '%s', '%s'), $insert['format'], 'Seed insert formats changed.');
	social_same('2026-08-08 04:05:06', $insert['data']['created_at'], 'Seed creation timestamp changed.');
	social_same($insert['data']['created_at'], $insert['data']['updated_at'], 'Seed timestamps should remain identical.');
}

// Audit writes retain sanitization/redaction and the existing insert shape.
social_reset($db);
bvmgr_social_audit_log('Publish Now!', array('client_secret' => 'hidden', 'result' => ' ok '), 0, 'Web Hook!');
$audit_insert = social_calls($db, 'insert')[0];
social_same('wp_vms_social_audit', $audit_insert['table'], 'Audit writes should retain their plugin-owned table.');
social_same(array('%d', '%s', '%d', '%s', '%s', '%s'), $audit_insert['format'], 'Audit insert formats changed.');
social_same(41, $audit_insert['data']['actor_user_id'], 'Audit actor fallback changed.');
social_same('publishnow', $audit_insert['data']['action'], 'Audit action sanitization changed.');
social_same(null, $audit_insert['data']['queue_id'], 'Non-positive queue IDs should remain null.');
social_same('webhook', $audit_insert['data']['platform'], 'Audit platform sanitization changed.');
$audit_details = json_decode((string) $audit_insert['data']['details_json'], true);
social_same(array('client_secret' => '[redacted]', 'result' => 'ok'), $audit_details, 'Audit detail redaction changed.');

// Both audit-history branches retain search fields, ordering, limits, prepare arguments, and fresh reads.
social_reset($db);
$db->get_results_queue = array(array(array('id' => 3)));
social_same(array(array('id' => 3)), bvmgr_social_audit_recent(0, ''), 'Unfiltered audit results changed.');
$prepare = social_last_prepare($db);
social_same('SELECT * FROM %i ORDER BY id DESC LIMIT %d', $prepare['query'], 'Unfiltered audit query changed.');
social_same(array('wp_vms_social_audit', 1), $prepare['args'], 'Unfiltered audit prepare arguments changed.');

social_reset($db);
$db->get_results_queue = array(array(array('id' => 4)));
social_same(array(array('id' => 4)), bvmgr_social_audit_recent(999, ' Need_100% '), 'Filtered audit results changed.');
$prepare = social_last_prepare($db);
$like = '%Need\\_100\\%%';
social_same(
	array('wp_vms_social_audit', $like, $like, $like, 500),
	$prepare['args'],
	'Filtered audit should prepare the table, all three LIKE values, and the capped limit in order.'
);
social_contains('WHERE action LIKE ', $prepare['sql'], 'Audit action search disappeared.');
social_contains(' OR platform LIKE ', $prepare['sql'], 'Audit platform search disappeared.');
social_contains(' OR details_json LIKE ', $prepare['sql'], 'Audit details search disappeared.');
social_contains(' ORDER BY id DESC LIMIT 500', $prepare['sql'], 'Filtered audit ordering or limit changed.');

social_reset($db);
$db->get_results_queue = array(array(array('id' => 5)), array(array('id' => 6)));
social_same(5, bvmgr_social_audit_recent(10)[0]['id'], 'First audit read changed.');
social_same(6, bvmgr_social_audit_recent(10)[0]['id'], 'Repeated audit reads should remain request-fresh.');
social_same(2, count(social_calls($db, 'get_results')), 'Audit history must not acquire persistent or request caching.');

// Template reads preserve invalid-ID failure, exact selection, default ordering, and fresh repository state.
social_reset($db);
social_same(null, bvmgr_social_template_get(0), 'Invalid template IDs should still fail closed.');
social_same(array(), $db->log, 'Invalid template IDs should not query the database.');

social_reset($db);
$db->get_row_queue = array(array('id' => 9, 'platform' => 'facebook'));
social_same(array('id' => 9, 'platform' => 'facebook'), bvmgr_social_template_get(9), 'Template ID read result changed.');
$prepare = social_last_prepare($db);
social_same('SELECT * FROM %i WHERE id = %d', $prepare['query'], 'Template ID query changed.');
social_same(array('wp_vms_social_templates', 9), $prepare['args'], 'Template ID prepare arguments changed.');

social_reset($db);
$db->get_row_queue = array(array('id' => 7), array('id' => 8));
social_same(7, bvmgr_social_template_default_for_platform('Linked In!')['id'], 'First default-template read changed.');
social_same(8, bvmgr_social_template_default_for_platform('Linked In!')['id'], 'Repeated default-template reads should remain current.');
foreach ($db->prepares as $prepare) {
	social_same(
		'SELECT * FROM %i WHERE platform = %s ORDER BY is_default DESC, id ASC LIMIT 1',
		$prepare['query'],
		'Default-template selection SQL changed.'
	);
	social_same(array('wp_vms_social_templates', 'linkedin'), $prepare['args'], 'Default-template prepare arguments changed.');
}
social_same(2, count(social_calls($db, 'get_row')), 'Template reads must not acquire persistent or request caching.');

social_reset($db);
$db->get_row_queue = array(null, array('id' => 12, 'platform' => 'x'));
social_same(12, bvmgr_social_template_for_platform('x', 99)['id'], 'Missing requested templates should retain default fallback selection.');
social_same(array('wp_vms_social_templates', 99), $db->prepares[0]['args'], 'Requested-template lookup order changed.');
social_same(array('wp_vms_social_templates', 'x'), $db->prepares[1]['args'], 'Default-template fallback order changed.');

// Posted-queue checks preserve exact status semantics, boolean results, prepare order, and request-fresh reads.
social_reset($db);
$db->get_var_queue = array(0, 2);
social_same(false, bvmgr_social_event_has_posted_queue(71), 'Zero posted rows should remain false.');
social_same(true, bvmgr_social_event_has_posted_queue(71), 'Positive posted rows should remain true.');
foreach ($db->prepares as $prepare) {
	social_same(
		"SELECT COUNT(*) FROM %i WHERE event_plan_id = %d AND status = 'posted'",
		$prepare['query'],
		'Posted-queue SQL shape changed.'
	);
	social_same(array('wp_vms_social_queue', 71), $prepare['args'], 'Posted-queue prepare arguments changed.');
	social_same("SELECT COUNT(*) FROM `wp_vms_social_queue` WHERE event_plan_id = 71 AND status = 'posted'", $prepare['sql'], 'Posted-queue SQL should execute fully prepared.');
}
social_same(2, count(social_calls($db, 'get_var')), 'Posted-queue checks must remain request-fresh.');

// Reconcile the exact 18-row Wave 3 ownership and prove every row has zero residual intent.
$baseline_inventory = array(
	'audit' => array(
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 3,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => 2,
	),
	'installer' => array(
		'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 1,
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 2,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => 1,
		'WordPress.DB.PreparedSQL.InterpolatedNotPrepared' => 1,
	),
	'event_panel' => array(
		'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 1,
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 1,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => 1,
		'WordPress.DB.PreparedSQL.InterpolatedNotPrepared' => 1,
	),
	'template_engine' => array(
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 2,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => 2,
	),
);
social_same(array('audit' => 5, 'installer' => 5, 'event_panel' => 4, 'template_engine' => 4), array_map('array_sum', $baseline_inventory), 'Per-file Wave 3 ownership changed.');
social_same(18, array_sum(array_map('array_sum', $baseline_inventory)), 'Social-support Wave 3 ownership must remain exactly 18 rows.');

$coverage = array(
	array('source' => $sources['audit'], 'fragment' => 'Social audit writes append an authoritative row', 'rows' => 1),
	array('source' => $sources['audit'], 'fragment' => 'Recent audit history must read request-fresh', 'rows' => 2),
	array('source' => $sources['audit'], 'fragment' => 'Filtered audit history must read request-fresh', 'rows' => 2),
	array('source' => $sources['installer'], 'fragment' => "get_var(\$wpdb->prepare('SELECT COUNT(*) FROM %i', \$table))", 'rows' => 4),
	array('source' => $sources['installer'], 'fragment' => 'Default-template seeding appends the fixed ordered rows', 'rows' => 1),
	array('source' => $sources['event_panel'], 'fragment' => "prepare(\"SELECT COUNT(*) FROM %i WHERE event_plan_id = %d AND status = 'posted'\", \$table, \$event_plan_id)", 'rows' => 4),
	array('source' => $sources['template_engine'], 'fragment' => 'Template selection must read current plugin-owned template state', 'rows' => 2),
	array('source' => $sources['template_engine'], 'fragment' => 'Default-template selection must read current plugin-owned ordering', 'rows' => 2),
);
$covered_rows = 0;
foreach ($coverage as $boundary) {
	social_contains($boundary['fragment'], $boundary['source'], 'Missing occurrence-specific social-support remediation.');
	$covered_rows += $boundary['rows'];
}
social_same(0, 18 - $covered_rows, 'All 18 owned baseline rows should have zero residual intent.');

$expected_annotations = array(
	'audit' => array('WordPress.DB.DirectDatabaseQuery.DirectQuery' => 3, 'WordPress.DB.DirectDatabaseQuery.NoCaching' => 2),
	'installer' => array('WordPress.DB.DirectDatabaseQuery.DirectQuery' => 2, 'WordPress.DB.DirectDatabaseQuery.NoCaching' => 1),
	'event_panel' => array('WordPress.DB.DirectDatabaseQuery.DirectQuery' => 1, 'WordPress.DB.DirectDatabaseQuery.NoCaching' => 1),
	'template_engine' => array('WordPress.DB.DirectDatabaseQuery.DirectQuery' => 2, 'WordPress.DB.DirectDatabaseQuery.NoCaching' => 2),
);
foreach ($expected_annotations as $file => $codes) {
	foreach ($codes as $code => $expected) {
		social_same($expected, substr_count($sources[$file], $code), 'Narrow annotation inventory changed for ' . $file . ' / ' . $code . '.');
	}
	social_validate_narrow_db_annotations($sources[$file]);
}
$combined_source = implode("\n", $sources);
social_same(0, substr_count($combined_source, 'PluginCheck.Security.DirectDB.UnescapedDBParameter'), 'Prepared %i identifiers must eliminate UnescapedDBParameter without suppression.');
social_same(0, substr_count($combined_source, 'WordPress.DB.PreparedSQL.InterpolatedNotPrepared'), 'Prepared %i identifiers must eliminate interpolation findings without suppression.');
$audit_log_source = social_extract_function($sources['audit'], 'bvmgr_social_audit_log');
$seed_source = social_extract_function($sources['installer'], 'bvmgr_social_seed_default_templates');
social_same(0, substr_count($audit_log_source, 'WordPress.DB.DirectDatabaseQuery.NoCaching'), 'Audit inserts should receive DirectQuery-only annotation.');
social_same(1, substr_count($audit_log_source, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'), 'Audit insert should have one DirectQuery annotation.');
social_same(1, substr_count($seed_source, 'WordPress.DB.DirectDatabaseQuery.NoCaching'), 'Only the installer count should have NoCaching annotation.');
social_same(2, substr_count($seed_source, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'), 'Installer count and seed insert should each have one DirectQuery annotation.');

$negative_scope = $sources['audit'] . "\n// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- invented broad family suppression";
$negative_rejected = false;
try {
	social_validate_narrow_db_annotations($negative_scope);
} catch (RuntimeException $exception) {
	$negative_rejected = true;
}
social_check($negative_rejected, 'The annotation guard should reject an invented broad family suppression.');

// Three shared files retain full shadow parity; the divergent event panel retains only owned-function parity and untouched projections.
$shadow_root = dirname($root, 2) . '/vms';
foreach (array('audit.php', 'installer.php', 'template-engine.php') as $filename) {
	$mirror = (string) file_get_contents($root . '/includes/social-share/' . $filename);
	$shadow = (string) file_get_contents($shadow_root . '/includes/social-share/' . $filename);
	social_same($mirror, $shadow, $filename . ' should retain full mirror/shadow parity.');
}
$shadow_event_source = (string) file_get_contents($shadow_root . '/includes/social-share/event-plan-panel.php');
social_check($shadow_event_source !== '', 'Shadow event-panel source should be readable.');
social_check(hash('sha256', $sources['event_panel']) !== hash('sha256', $shadow_event_source), 'Intentional whole-file event-panel divergence should remain preserved.');
social_same(
	social_extract_function($sources['event_panel'], 'bvmgr_social_event_has_posted_queue'),
	social_extract_function($shadow_event_source, 'bvmgr_social_event_has_posted_queue'),
	'Owned posted-queue function should retain mirror/shadow parity.'
);
social_same(
	'e78ef742e9e2efbe3b786e56150d8cc4c506b4f4cc85a25ea7720db0a38c69a3',
	hash('sha256', social_without_function($sources['event_panel'], 'bvmgr_social_event_has_posted_queue')),
	'Mirror event-panel projection outside the owned function changed.'
);
social_same(
	'f6ffef88e5886ba8fdb36ea1f7399dc6c2cdb80cd04c58940f79bf9ecb95544e',
	hash('sha256', social_without_function($shadow_event_source, 'bvmgr_social_event_has_posted_queue')),
	'Shadow event-panel projection outside the owned function changed.'
);

fwrite(STDOUT, "social-share support repository SQL remediation: PASS\n");
