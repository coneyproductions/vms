<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');

final class VMS_Ticketing_Core_WPDB_Spy
{
	public string $prefix;
	public string $posts;
	/** @var array<int,array{template:string,args:array<int,mixed>,sql:string}> */
	public array $prepares = array();
	/** @var array<int,array{kind:string,sql:string,result:mixed}> */
	public array $executions = array();
	/** @var array<int,mixed> */
	public array $get_var_queue = array();
	/** @var array<int,mixed> */
	public array $get_col_queue = array();
	/** @var array<int,mixed> */
	public array $get_row_queue = array();
	/** @var array<int,string> */
	public array $esc_like_calls = array();

	public function __construct(string $prefix)
	{
		$this->prefix = $prefix;
		$this->posts = $prefix . 'posts';
	}

	public function prepare(string $query, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}
		$index = 0;
		$sql = preg_replace_callback(
			'/(?<!%)%(?:\d+\$)?[sdi]/',
			static function (array $match) use (&$index, $args): string {
				if (!array_key_exists($index, $args)) {
					throw new RuntimeException('Missing prepared-query argument.');
				}
				$value = $args[$index++];
				$type = substr($match[0], -1);
				if ($type === 'd') {
					return (string) (int) $value;
				}
				if ($type === 'i') {
					return implode('.', array_map(static fn(string $part): string => '`' . str_replace('`', '``', $part) . '`', explode('.', (string) $value)));
				}
				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$query
		);
		if (!is_string($sql) || $index !== count($args)) {
			throw new RuntimeException('Prepared-query placeholder/argument mismatch.');
		}
		$this->prepares[] = array('template' => $query, 'args' => $args, 'sql' => $sql);
		return $sql;
	}

	public function esc_like(string $value): string
	{
		$this->esc_like_calls[] = $value;
		return addcslashes($value, '_%\\');
	}

	public function get_var(string $sql)
	{
		$result = $this->get_var_queue === array() ? null : array_shift($this->get_var_queue);
		$this->executions[] = array('kind' => 'get_var', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_col(string $sql)
	{
		$result = $this->get_col_queue === array() ? array() : array_shift($this->get_col_queue);
		$this->executions[] = array('kind' => 'get_col', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_row(string $sql, string $output = 'OBJECT')
	{
		$result = $this->get_row_queue === array() ? null : array_shift($this->get_row_queue);
		$this->executions[] = array('kind' => 'get_row:' . $output, 'sql' => $sql, 'result' => $result);
		return $result;
	}
}

final class WP_Query
{
	/** @var array<int,array<string,mixed>> */
	public static array $calls = array();
	/** @var array<int,array<int,mixed>> */
	public static array $queue = array();
	/** @var array<int,mixed> */
	public array $posts = array();

	public function __construct(array $args)
	{
		self::$calls[] = $args;
		$this->posts = self::$queue === array() ? array() : array_shift(self::$queue);
	}
}

final class VMS_Ticketing_Core_Product_Stub
{
	private int $total_sales;
	private string $price;

	public function __construct(int $total_sales, string $price)
	{
		$this->total_sales = $total_sales;
		$this->price = $price;
	}

	public function get_total_sales(): int
	{
		return $this->total_sales;
	}

	public function get_price(): string
	{
		return $this->price;
	}
}

final class VMS_Ticketing_Core_Ajax_Response extends RuntimeException
{
	public bool $success;
	/** @var array<string,mixed> */
	public array $payload;
	public int $status;

	/** @param array<string,mixed> $payload */
	public function __construct(bool $success, array $payload, int $status)
	{
		parent::__construct($success ? 'ajax_success' : 'ajax_error');
		$this->success = $success;
		$this->payload = $payload;
		$this->status = $status;
	}
}

$GLOBALS['ticketing_core_woo_active'] = true;
$GLOBALS['ticketing_core_tec_active'] = true;
$GLOBALS['ticketing_core_can_edit_posts'] = true;
$GLOBALS['ticketing_core_currency'] = 'USD';
$GLOBALS['ticketing_core_products'] = array();
$GLOBALS['ticketing_core_product_calls'] = array();
$GLOBALS['ticketing_core_post_meta'] = array();
$GLOBALS['ticketing_core_titles'] = array();
$GLOBALS['ticketing_core_permalinks'] = array();
$GLOBALS['ticketing_core_nonce_calls'] = array();
$GLOBALS['ticketing_core_helper_ids'] = array();

function absint($value): int
{
	return abs((int) $value);
}

function bvmgr_ticketing_is_woo_active(): bool
{
	return (bool) $GLOBALS['ticketing_core_woo_active'];
}

function bvmgr_ticketing_is_tec_active(): bool
{
	return (bool) $GLOBALS['ticketing_core_tec_active'];
}

function get_woocommerce_currency(): string
{
	return (string) $GLOBALS['ticketing_core_currency'];
}

function wc_get_product(int $product_id)
{
	$GLOBALS['ticketing_core_product_calls'][] = $product_id;
	return $GLOBALS['ticketing_core_products'][$product_id] ?? null;
}

function current_user_can(string $capability): bool
{
	return $capability === 'edit_posts' && (bool) $GLOBALS['ticketing_core_can_edit_posts'];
}

function check_ajax_referer(string $action, string $query_arg): bool
{
	$GLOBALS['ticketing_core_nonce_calls'][] = array($action, $query_arg);
	return true;
}

/** @param array<string,mixed> $source */
function bvmgr_request_read_text_field(array $source, string $key): string
{
	$value = $source[$key] ?? '';
	return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['ticketing_core_post_meta'][$post_id][$key] ?? '';
}

function get_the_title(int $post_id): string
{
	return (string) ($GLOBALS['ticketing_core_titles'][$post_id] ?? '');
}

function get_permalink(int $post_id): string
{
	return (string) ($GLOBALS['ticketing_core_permalinks'][$post_id] ?? '');
}

function bvmgr_ticketing_get_tec_legacy_identifiers(int $post_id): array
{
	return array('legacy_id' => 'legacy-' . $post_id);
}

/** @param array<string,mixed> $data */
function bvmgr_ticketing_ajax_send_success(array $data): void
{
	throw new VMS_Ticketing_Core_Ajax_Response(true, $data, 200);
}

/** @param array<string,mixed> $data */
function bvmgr_ticketing_ajax_send_error(array $data, int $status = 400): void
{
	throw new VMS_Ticketing_Core_Ajax_Response(false, $data, $status);
}

function ticketing_core_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function ticketing_core_same($expected, $actual, string $message): void
{
	ticketing_core_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function ticketing_core_contains(string $needle, string $haystack, string $message): void
{
	ticketing_core_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function ticketing_core_no_placeholders(string $sql): void
{
	ticketing_core_assert(preg_match('/(?<!%)%(?:\d+\$)?[sdi]/', $sql) !== 1, 'Executed SQL retains a placeholder: ' . $sql);
}

function ticketing_core_normalize_sql(string $sql): string
{
	$normalized = preg_replace('/\s+/', ' ', trim($sql));
	if (!is_string($normalized)) {
		throw new RuntimeException('Unable to normalize SQL.');
	}
	return $normalized;
}

function ticketing_core_extract_function(string $source, string $name): string
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

function ticketing_core_extract_between(string $source, string $start_marker, string $end_marker): string
{
	$start = strpos($source, $start_marker);
	$end = $start === false ? false : strpos($source, $end_marker, $start);
	if ($start === false || $end === false) {
		throw new RuntimeException('Unable to extract owned source region.');
	}
	return substr($source, $start, ($end - $start) + strlen($end_marker));
}

/**
 * @param array<string,array{text:string,inline:bool}> $directives
 * @return array{source:string,removed:int}
 */
function ticketing_core_project_pre_remediation(string $source, array $directives): array
{
	$removed = 0;
	foreach ($directives as $id => $directive) {
		if ($directive['inline']) {
			$source = str_replace(' ' . $directive['text'], '', $source, $count);
		} else {
			$pattern = '/^[ \t]*' . preg_quote($directive['text'], '/') . '(?:\r\n|\n|\r)/m';
			$source = (string) preg_replace($pattern, '', $source, 1, $count);
		}
		ticketing_core_same(1, $count, 'Owned directive must project exactly once: ' . $id . '.');
		$removed += $count;
	}

	$replacements = array(
		'$wpdb->get_col($wpdb->prepare(\'SHOW COLUMNS FROM %i\', $lookup_table))' => '$wpdb->get_col("SHOW COLUMNS FROM {$lookup_table}")',
		"        \$product_id_placeholders = implode(',', array_fill(0, count(\$product_ids), '%d'));" => "        \$in = implode(',', array_map('absint', \$product_ids));",
	);
	foreach ($replacements as $current => $historical) {
		$source = str_replace($current, $historical, $source, $count);
		ticketing_core_same(1, $count, 'Owned runtime projection changed unexpectedly: ' . $current . '.');
	}

	$current_aggregate = <<<'PHP'
            $sql = 'SELECT SUM(%i) AS qty, SUM(%i) AS revenue FROM %i WHERE product_id IN (' . $product_id_placeholders . ')';
            $prepare_args = array_merge(array($qty_col, $rev_col, $lookup_table), $product_ids);
            $prepared = $wpdb->prepare($sql, $prepare_args);
            $row = $wpdb->get_row($prepared, ARRAY_A);
PHP;
	$historical_aggregate = <<<'PHP'
            $sql = "SELECT SUM({$qty_col}) AS qty, SUM({$rev_col}) AS revenue FROM {$lookup_table} WHERE product_id IN ({$in})";
            $row = $wpdb->get_row($sql, ARRAY_A);
PHP;
	$source = str_replace($current_aggregate, $historical_aggregate, $source, $count);
	ticketing_core_same(1, $count, 'Aggregate runtime projection changed unexpectedly.');

	return array('source' => $source, 'removed' => $removed);
}

/** @param array<int,string> $expected */
function ticketing_core_validate_db_annotations(string $source, array $expected): void
{
	if (preg_match('/phpcs:(?:disable|enable|ignoreFile)/', $source) === 1) {
		throw new RuntimeException('Block/file PHPCS directives are forbidden.');
	}
	$actual = array();
	foreach (preg_split('/\R/', $source) ?: array() as $line) {
		if (strpos($line, 'phpcs:') === false || (strpos($line, 'WordPress.DB') === false && strpos($line, 'PluginCheck.Security.DirectDB') === false)) {
			continue;
		}
		$directive_start = strpos($line, '// phpcs:');
		if ($directive_start === false) {
			throw new RuntimeException('Every DB annotation must be a line-local ignore.');
		}
		$actual[] = substr($line, $directive_start);
	}
	sort($actual);
	sort($expected);
	ticketing_core_same($expected, $actual, 'Every DB-related annotation must be one of the seven exact owned directives.');
}

function ticketing_core_validate_directive_anchor(string $source, string $directive, string $target, bool $inline, string $id): void
{
	$lines = preg_split('/\R/', $source) ?: array();
	$matches = array();
	foreach ($lines as $index => $line) {
		if (strpos($line, $directive) !== false) {
			$matches[] = $index;
		}
	}
	ticketing_core_same(1, count($matches), 'Directive must occur exactly once at anchor ' . $id . '.');
	$index = $matches[0];
	if ($inline) {
		$without_directive = str_replace(' ' . $directive, '', trim($lines[$index]), $count);
		ticketing_core_same(1, $count, 'Inline directive placement changed at anchor ' . $id . '.');
		ticketing_core_same($target, $without_directive, 'Inline directive target changed at anchor ' . $id . '.');
		return;
	}
	ticketing_core_same($target, trim($lines[$index + 1] ?? ''), 'Line-local directive moved away from its exact target at anchor ' . $id . '.');
}

$root = dirname(__DIR__);
$mirror_path = $root . '/includes/integrations/ticketing.php';
$shadow_root = dirname($root, 2) . '/vms';
$shadow_path = $shadow_root . '/includes/integrations/ticketing.php';
$source = file_get_contents($mirror_path);
$shadow_source = file_get_contents($shadow_path);
if (!is_string($source) || !is_string($shadow_source)) {
	throw new RuntimeException('Unable to read mirror/shadow ticketing sources.');
}

$directives = array(
	'Q1' => array(
		'text' => '// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Ticket statistics require an exhaustive ID-only lookup of every ticket product linked to this single TEC event; the native meta relation is the compatibility contract.',
		'inline' => false,
	),
	'T1' => array(
		'text' => '// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Woo Analytics capability detection must inspect current lookup-table existence before selecting the statistics fallback; no WordPress API exposes this table state.',
		'inline' => false,
	),
	'C1' => array(
		'text' => '// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Woo Analytics capability detection must inspect current lookup-table columns before choosing compatible quantity and revenue fields; no WordPress API exposes this schema.',
		'inline' => false,
	),
	'A1-template' => array(
		'text' => '// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The dynamic fragment is only the counted product-ID placeholder list; every identifier and product ID is prepared below.',
		'inline' => true,
	),
	'A1-prepare' => array(
		'text' => '// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The aggregate contains only prepared allowlisted column identifiers, the Woo lookup-table identifier, and integer product IDs.',
		'inline' => false,
	),
	'A1-execute' => array(
		'text' => '// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket statistics require request-fresh Woo lookup aggregates using the detected compatible columns; no WooCommerce API preserves this exact result contract.',
		'inline' => false,
	),
	'S1' => array(
		'text' => '// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This authenticated AJAX search executes one of the two immediately prepared catalog queries and must return current TEC event matches.',
		'inline' => false,
	),
);

$expected_directives = array_column($directives, 'text');
ticketing_core_validate_db_annotations($source, $expected_directives);
ticketing_core_validate_db_annotations($shadow_source, $expected_directives);

$anchors = array(
	'Q1' => array('target' => "'meta_query'     => array(", 'inline' => false),
	'T1' => array('target' => '$tbl = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $lookup_table));', 'inline' => false),
	'C1' => array('target' => '$cols_raw = $wpdb->get_col($wpdb->prepare(\'SHOW COLUMNS FROM %i\', $lookup_table));', 'inline' => false),
	'A1-template' => array('target' => '$sql = \'SELECT SUM(%i) AS qty, SUM(%i) AS revenue FROM %i WHERE product_id IN (\' . $product_id_placeholders . \')\';', 'inline' => true),
	'A1-prepare' => array('target' => '$prepared = $wpdb->prepare($sql, $prepare_args);', 'inline' => false),
	'A1-execute' => array('target' => '$row = $wpdb->get_row($prepared, ARRAY_A);', 'inline' => false),
	'S1' => array('target' => '$ids = $wpdb->get_col($sql);', 'inline' => false),
);
foreach ($anchors as $id => $anchor) {
	ticketing_core_validate_directive_anchor($source, $directives[$id]['text'], $anchor['target'], $anchor['inline'], 'mirror/' . $id);
	ticketing_core_validate_directive_anchor($shadow_source, $directives[$id]['text'], $anchor['target'], $anchor['inline'], 'shadow/' . $id);
}

$mirror_projection = ticketing_core_project_pre_remediation($source, $directives);
$shadow_projection = ticketing_core_project_pre_remediation($shadow_source, $directives);
ticketing_core_same(7, $mirror_projection['removed'], 'Mirror projection must strip exactly seven owned annotations.');
ticketing_core_same(7, $shadow_projection['removed'], 'Shadow projection must strip exactly seven owned annotations.');
ticketing_core_same('8980181edc0df051578f5ba6a2e79f230100f0316b08a15370088cf765e313c0', hash('sha256', $mirror_projection['source']), 'Mirror changed outside the exact owned remediation.');
ticketing_core_same('f3b3d4914bb80b0fb56c3e1fc320945656fee036d2df2a1a484f276a514b36da', hash('sha256', $shadow_projection['source']), 'Shadow changed outside the exact owned remediation.');
ticketing_core_assert(hash('sha256', $source) !== hash('sha256', $shadow_source), 'Intentional whole-file mirror/shadow divergence must remain.');

foreach (array('mirror' => $source, 'shadow' => $shadow_source) as $tree => $tree_source) {
	$mutated = str_replace("return array_values(array_unique(array_map('absint', \$q->posts ?? array())));", "return array_values(array_map('absint', \$q->posts ?? array()));", $tree_source, $count);
	ticketing_core_same(1, $count, 'Runtime mutation control should alter one ticket-product result contract: ' . $tree . '.');
	$mutation_projection = ticketing_core_project_pre_remediation($mutated, $directives);
	$baseline_hash = $tree === 'mirror' ? '8980181edc0df051578f5ba6a2e79f230100f0316b08a15370088cf765e313c0' : 'f3b3d4914bb80b0fb56c3e1fc320945656fee036d2df2a1a484f276a514b36da';
	ticketing_core_assert(hash('sha256', $mutation_projection['source']) !== $baseline_hash, 'Immutable projection must reject non-comment runtime mutation: ' . $tree . '.');
}

ticketing_core_same(
	ticketing_core_extract_function($source, 'bvmgr_ticketing_get_ticket_product_ids_for_tec_event'),
	ticketing_core_extract_function($shadow_source, 'bvmgr_ticketing_get_ticket_product_ids_for_tec_event'),
	'Ticket-product query boundary must match across mirror/shadow.'
);
ticketing_core_same(
	ticketing_core_extract_function($source, 'bvmgr_ticketing_compute_stats'),
	ticketing_core_extract_function($shadow_source, 'bvmgr_ticketing_compute_stats'),
	'Ticket statistics repository boundary must match across mirror/shadow.'
);
$mirror_search = ticketing_core_extract_function($source, 'bvmgr_ticketing_ajax_search_tec_events');
$shadow_search = ticketing_core_extract_function($shadow_source, 'bvmgr_ticketing_ajax_search_tec_events');
ticketing_core_assert($mirror_search !== $shadow_search, 'Pre-existing search request-reader divergence must remain preserved.');
ticketing_core_same(
	ticketing_core_extract_between($mirror_search, '$like =', '$items = array();'),
	ticketing_core_extract_between($shadow_search, '$like =', '$items = array();'),
	'Owned TEC search SQL boundary must match across mirror/shadow.'
);

$query_code = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query';
$direct_code = 'WordPress.DB.DirectDatabaseQuery.DirectQuery';
$no_cache_code = 'WordPress.DB.DirectDatabaseQuery.NoCaching';
$unescaped_code = 'PluginCheck.Security.DirectDB.UnescapedDBParameter';
$interpolated_code = 'WordPress.DB.PreparedSQL.InterpolatedNotPrepared';
$not_prepared_code = 'WordPress.DB.PreparedSQL.NotPrepared';

// Immutable Wave 4 strict-JSON evidence: fourteen rows on five physical DB boundaries.
$artifact_rows = array(
	'249:9:Q' => array('line' => 249, 'column' => 9, 'code' => $query_code, 'occurrence' => 'Q1'),
	'291:16:D' => array('line' => 291, 'column' => 16, 'code' => $direct_code, 'occurrence' => 'T1'),
	'291:16:N' => array('line' => 291, 'column' => 16, 'code' => $no_cache_code, 'occurrence' => 'T1'),
	'294:25:D' => array('line' => 294, 'column' => 25, 'code' => $direct_code, 'occurrence' => 'C1'),
	'294:25:N' => array('line' => 294, 'column' => 25, 'code' => $no_cache_code, 'occurrence' => 'C1'),
	'294:32:U' => array('line' => 294, 'column' => 32, 'code' => $unescaped_code, 'occurrence' => 'C1'),
	'294:40:I' => array('line' => 294, 'column' => 40, 'code' => $interpolated_code, 'occurrence' => 'C1'),
	'313:20:D' => array('line' => 313, 'column' => 20, 'code' => $direct_code, 'occurrence' => 'A1'),
	'313:20:N' => array('line' => 313, 'column' => 20, 'code' => $no_cache_code, 'occurrence' => 'A1'),
	'313:27:U' => array('line' => 313, 'column' => 27, 'code' => $unescaped_code, 'occurrence' => 'A1'),
	'313:35:P' => array('line' => 313, 'column' => 35, 'code' => $not_prepared_code, 'occurrence' => 'A1'),
	'547:12:D' => array('line' => 547, 'column' => 12, 'code' => $direct_code, 'occurrence' => 'S1'),
	'547:12:N' => array('line' => 547, 'column' => 12, 'code' => $no_cache_code, 'occurrence' => 'S1'),
	'547:27:P' => array('line' => 547, 'column' => 27, 'code' => $not_prepared_code, 'occurrence' => 'S1'),
);

$artifact_counts = array_count_values(array_column($artifact_rows, 'code'));
ksort($artifact_counts);
$expected_artifact_counts = array(
	$query_code => 1,
	$direct_code => 4,
	$no_cache_code => 4,
	$unescaped_code => 2,
	$interpolated_code => 1,
	$not_prepared_code => 2,
);
ksort($expected_artifact_counts);
ticketing_core_same($expected_artifact_counts, $artifact_counts, 'Artifact rows must derive the exact U2/D4/N4/I1/P2/Q1 inventory.');
ticketing_core_same(14, count($artifact_rows), 'Ticketing core artifact inventory must remain exactly fourteen rows.');

$ticket_ids_source = ticketing_core_extract_function($source, 'bvmgr_ticketing_get_ticket_product_ids_for_tec_event');
$stats_source = ticketing_core_extract_function($source, 'bvmgr_ticketing_compute_stats');
$occurrence_proof = array(
	'Q1' => array(
		'body' => $ticket_ids_source,
		'fragments' => array($directives['Q1']['text'], "'meta_query'     => array(", "'key'     => '_tribe_wooticket_for_event'"),
	),
	'T1' => array(
		'body' => $stats_source,
		'fragments' => array($directives['T1']['text'], '$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $lookup_table))'),
	),
	'C1' => array(
		'body' => $stats_source,
		'fragments' => array($directives['C1']['text'], '$wpdb->get_col($wpdb->prepare(\'SHOW COLUMNS FROM %i\', $lookup_table))'),
	),
	'A1' => array(
		'body' => $stats_source,
		'fragments' => array(
			$directives['A1-template']['text'],
			$directives['A1-prepare']['text'],
			$directives['A1-execute']['text'],
			'implode(\',\', array_fill(0, count($product_ids), \'%d\'))',
			'array_merge(array($qty_col, $rev_col, $lookup_table), $product_ids)',
			'$row = $wpdb->get_row($prepared, ARRAY_A);',
		),
	),
	'S1' => array(
		'body' => $mirror_search,
		'fragments' => array($directives['S1']['text'], '$ids = $wpdb->get_col($sql);'),
	),
);

foreach ($occurrence_proof as $occurrence_id => $proof) {
	foreach ($proof['fragments'] as $fragment) {
		ticketing_core_contains($fragment, $proof['body'], 'Exact source anchor changed at occurrence ' . $occurrence_id . '.');
	}
}
ticketing_core_assert(strpos($stats_source, '$wpdb->get_col("SHOW COLUMNS FROM {$lookup_table}")') === false, 'Raw schema identifier interpolation must remain eliminated.');
ticketing_core_assert(strpos($stats_source, 'SELECT SUM({$qty_col})') === false, 'Raw aggregate identifier/value interpolation must remain eliminated.');
ticketing_core_assert(strpos($stats_source, '$wpdb->get_row($sql, ARRAY_A)') === false, 'Unsafe aggregate execution variable must remain eliminated.');

$covered_artifact_rows = array();
foreach ($artifact_rows as $artifact_id => $artifact_row) {
	ticketing_core_assert(isset($occurrence_proof[$artifact_row['occurrence']]), 'Artifact row has no exact current occurrence proof: ' . $artifact_id . '.');
	$covered_artifact_rows[$artifact_id] = $artifact_row;
}
ticketing_core_same(array(), array_diff_key($artifact_rows, $covered_artifact_rows), 'Every artifact row must have zero residual intent through an exact current occurrence.');
ticketing_core_same(array_keys($artifact_rows), array_keys($covered_artifact_rows), 'Artifact coverage must preserve every row identity, not only aggregate counts.');

foreach (array(
	$source . "\n// phpcs:disable WordPress.DB",
	$source . "\n// phpcs:ignore WordPress.DB -- invented broad DB category",
	$source . "\n// phpcs:ignore WordPress.DB.PreparedSQL -- invented prepared-SQL family",
	$source . "\n// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- invented direct-query family",
	$source . "\n// phpcs:ignore PluginCheck.Security.DirectDB -- invented Plugin Check family",
	$source . "\n// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.Security.EscapeOutput.OutputNotEscaped -- invented mixed-category suppression",
	$source . "\n// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- invented mixed-occurrence suppression",
) as $negative_source) {
	$rejected = false;
	try {
		ticketing_core_validate_db_annotations($negative_source, $expected_directives);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	ticketing_core_assert($rejected, 'Broad, family, category, or mixed-code DB suppression must be rejected.');
}

/** @return array{template:string,args:array<int,mixed>,sql:string} */
function ticketing_core_find_prepare(VMS_Ticketing_Core_WPDB_Spy $wpdb, string $needle): array
{
	foreach ($wpdb->prepares as $prepare) {
		if (strpos($prepare['template'], $needle) !== false) {
			return $prepare;
		}
	}
	throw new RuntimeException('Unable to find prepare call containing ' . $needle . '.');
}

/** @return array{kind:string,sql:string,result:mixed} */
function ticketing_core_find_execution(VMS_Ticketing_Core_WPDB_Spy $wpdb, string $needle): array
{
	foreach ($wpdb->executions as $execution) {
		if (strpos($execution['sql'], $needle) !== false) {
			return $execution;
		}
	}
	throw new RuntimeException('Unable to find execution containing ' . $needle . '.');
}

/** @param array<string,mixed> $stats @return array<string,mixed> */
function ticketing_core_without_computed_time(array $stats): array
{
	ticketing_core_assert(isset($stats['computed_at_gmt']) && is_int($stats['computed_at_gmt']) && $stats['computed_at_gmt'] > 0, 'Statistics timestamp contract changed.');
	unset($stats['computed_at_gmt']);
	return $stats;
}

/** @return VMS_Ticketing_Core_Ajax_Response */
function ticketing_core_capture_ajax(callable $callback): VMS_Ticketing_Core_Ajax_Response
{
	try {
		$callback();
	} catch (VMS_Ticketing_Core_Ajax_Response $response) {
		return $response;
	}
	throw new RuntimeException('AJAX callback returned without an owned response.');
}

foreach (array(
	'bvmgr_ticketing_get_ticket_product_ids_for_tec_event',
	'bvmgr_ticketing_compute_stats',
	'bvmgr_ticketing_ajax_search_tec_events',
) as $runtime_function) {
	eval(ticketing_core_extract_function($source, $runtime_function));
}

// The fallback WP_Query remains exhaustive, ID-only, and preserves normalized order/duplicates semantics.
WP_Query::$calls = array();
WP_Query::$queue = array();
ticketing_core_same(array(), bvmgr_ticketing_get_ticket_product_ids_for_tec_event(0), 'Invalid TEC event IDs must still avoid querying.');
ticketing_core_same(array(), WP_Query::$calls, 'Invalid TEC event IDs must not instantiate WP_Query.');

WP_Query::$queue[] = array(7, '0', 7, -8, 'bad');
ticketing_core_same(array(7, 0, 8), bvmgr_ticketing_get_ticket_product_ids_for_tec_event(-44), 'Fallback ticket-product ID normalization changed.');
$expected_ticket_query = array(
	'post_type' => 'product',
	'post_status' => array('publish', 'draft', 'private'),
	'posts_per_page' => -1,
	'fields' => 'ids',
	'meta_query' => array(
		array(
			'key' => '_tribe_wooticket_for_event',
			'value' => 44,
			'compare' => '=',
		),
	),
);
ticketing_core_same(array($expected_ticket_query), WP_Query::$calls, 'Ticket-product WP_Query arguments must remain exact, including no added cache or limit arguments.');

$GLOBALS['ticketing_core_helper_calls'] = array();
eval('function bvmgr_get_ticket_product_ids_for_event(int $event_id): array { $GLOBALS[\'ticketing_core_helper_calls\'][] = $event_id; return $GLOBALS[\'ticketing_core_helper_ids\']; }');
$GLOBALS['ticketing_core_helper_ids'] = array(9, '9', -3, 'bad');
ticketing_core_same(array(9, 3, 0), bvmgr_ticketing_get_ticket_product_ids_for_tec_event(52), 'Preferred helper ticket-product normalization changed.');
ticketing_core_same(array(52), $GLOBALS['ticketing_core_helper_calls'], 'Preferred helper event ID changed.');
ticketing_core_same(1, count(WP_Query::$calls), 'Preferred helper branch must not add a fallback query.');

// Empty/disabled inputs keep the no-provider result and avoid every DB/product call.
$GLOBALS['ticketing_core_woo_active'] = true;
$wpdb = new VMS_Ticketing_Core_WPDB_Spy('wp_none_');
$GLOBALS['wpdb'] = $wpdb;
ticketing_core_same(
	array('provider' => 'none', 'qty_sold' => 0, 'revenue' => 0.0, 'revenue_label' => 'N/A', 'currency' => ''),
	ticketing_core_without_computed_time(bvmgr_ticketing_compute_stats(array(0, 'bad'))),
	'Empty product IDs should retain the no-provider result.'
);
ticketing_core_same(array(), $wpdb->executions, 'Empty product IDs must not touch the database.');

$GLOBALS['ticketing_core_woo_active'] = false;
ticketing_core_same(
	array('provider' => 'none', 'qty_sold' => 0, 'revenue' => 0.0, 'revenue_label' => 'N/A', 'currency' => ''),
	ticketing_core_without_computed_time(bvmgr_ticketing_compute_stats(array(6))),
	'Inactive WooCommerce should retain the no-provider result.'
);
$GLOBALS['ticketing_core_woo_active'] = true;

// Gross-analytics branch: schema identifiers, aggregate identifiers, and every duplicate product ID are prepared in exact order.
$wpdb = new VMS_Ticketing_Core_WPDB_Spy('wp_gross_');
$lookup_table = 'wp_gross_wc_order_product_lookup';
$wpdb->get_var_queue[] = $lookup_table;
$wpdb->get_col_queue[] = array('product_qty', 'product_gross_revenue', 'product_net_revenue');
$wpdb->get_row_queue[] = array('qty' => '12', 'revenue' => '345.67');
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['ticketing_core_currency'] = 'USD';
$gross_stats = bvmgr_ticketing_compute_stats(array(5, '0', -7, '5', 'bad'));
ticketing_core_same(
	array('provider' => 'woo_analytics', 'qty_sold' => 12, 'revenue' => 345.67, 'revenue_label' => 'Gross revenue (Woo analytics)', 'currency' => 'USD'),
	ticketing_core_without_computed_time($gross_stats),
	'Gross Woo Analytics result contract changed.'
);
ticketing_core_same(3, count($wpdb->prepares), 'Gross analytics should prepare exactly two schema probes and one aggregate.');
ticketing_core_same(array($lookup_table), $wpdb->prepares[0]['args'], 'Lookup-table probe prepare arguments changed.');
ticketing_core_same(array($lookup_table), $wpdb->prepares[1]['args'], 'Column probe prepare arguments changed.');
ticketing_core_same(array('product_qty', 'product_gross_revenue', $lookup_table, 5, 7, 5), $wpdb->prepares[2]['args'], 'Gross aggregate prepare argument order changed.');
ticketing_core_same("SHOW TABLES LIKE 'wp_gross_wc_order_product_lookup'", ticketing_core_normalize_sql($wpdb->prepares[0]['sql']), 'Rendered table-existence SQL changed.');
ticketing_core_same('SHOW COLUMNS FROM `wp_gross_wc_order_product_lookup`', ticketing_core_normalize_sql($wpdb->prepares[1]['sql']), 'Rendered prepared column-probe SQL changed.');
$expected_gross_sql = 'SELECT SUM(`product_qty`) AS qty, SUM(`product_gross_revenue`) AS revenue FROM `wp_gross_wc_order_product_lookup` WHERE product_id IN (5,7,5)';
ticketing_core_same($expected_gross_sql, ticketing_core_normalize_sql($wpdb->prepares[2]['sql']), 'Rendered gross aggregate SQL changed.');
ticketing_core_same($expected_gross_sql, ticketing_core_normalize_sql(ticketing_core_find_execution($wpdb, 'SELECT SUM')['sql']), 'Executed gross aggregate SQL changed.');
foreach ($wpdb->executions as $execution) {
	ticketing_core_no_placeholders($execution['sql']);
}

// Net-column selection and null-row behavior remain unchanged.
$wpdb = new VMS_Ticketing_Core_WPDB_Spy('wp_net_');
$lookup_table = 'wp_net_wc_order_product_lookup';
$wpdb->get_var_queue[] = $lookup_table;
$wpdb->get_col_queue[] = array('PRODUCT_QTY', 'PRODUCT_NET_REVENUE');
$wpdb->get_row_queue[] = array('qty' => '2', 'revenue' => '-4.50');
$GLOBALS['wpdb'] = $wpdb;
$net_stats = bvmgr_ticketing_compute_stats(array(11));
ticketing_core_same(
	array('provider' => 'woo_analytics', 'qty_sold' => 2, 'revenue' => 0.0, 'revenue_label' => 'Net revenue (Woo analytics)', 'currency' => 'USD'),
	ticketing_core_without_computed_time($net_stats),
	'Net Woo Analytics selection/clamping changed.'
);
ticketing_core_same(array('product_qty', 'product_net_revenue', $lookup_table, 11), ticketing_core_find_prepare($wpdb, 'SELECT SUM')['args'], 'Net aggregate prepare arguments changed.');

$wpdb = new VMS_Ticketing_Core_WPDB_Spy('wp_null_');
$lookup_table = 'wp_null_wc_order_product_lookup';
$wpdb->get_var_queue[] = $lookup_table;
$wpdb->get_col_queue[] = array('product_qty', 'product_gross_revenue');
$wpdb->get_row_queue[] = null;
$GLOBALS['wpdb'] = $wpdb;
ticketing_core_same(
	array('provider' => 'woo_analytics', 'qty_sold' => 0, 'revenue' => 0.0, 'revenue_label' => 'Gross revenue (Woo analytics)', 'currency' => 'USD'),
	ticketing_core_without_computed_time(bvmgr_ticketing_compute_stats(array(13))),
	'Null aggregate row fallback changed.'
);

// Missing schema falls through to the existing per-product approximation, preserving duplicate/order behavior.
$wpdb = new VMS_Ticketing_Core_WPDB_Spy('wp_fallback_');
$wpdb->get_var_queue[] = null;
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['ticketing_core_products'] = array(5 => new VMS_Ticketing_Core_Product_Stub(3, '10.50'));
$GLOBALS['ticketing_core_product_calls'] = array();
$fallback_stats = bvmgr_ticketing_compute_stats(array(5, 5, 7));
ticketing_core_same(
	array('provider' => 'woo_product_totals', 'qty_sold' => 6, 'revenue' => 63.0, 'revenue_label' => 'Estimated revenue (price × sold; excludes discounts, taxes, refunds)', 'currency' => 'USD'),
	ticketing_core_without_computed_time($fallback_stats),
	'Woo product fallback totals changed.'
);
ticketing_core_same(array(5, 5, 7), $GLOBALS['ticketing_core_product_calls'], 'Woo fallback product call order/duplicates changed.');
ticketing_core_same(1, count($wpdb->executions), 'Missing lookup schema should execute only the current table probe before fallback.');

// Numeric TEC search: exact SQL/prepare arguments, fresh result ordering, normalization, and payload shape.
$wpdb = new VMS_Ticketing_Core_WPDB_Spy('wp_search_');
$wpdb->get_col_queue[] = array('9', 0, 'bad', -4);
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['ticketing_core_can_edit_posts'] = true;
$GLOBALS['ticketing_core_tec_active'] = true;
$GLOBALS['ticketing_core_nonce_calls'] = array();
$GLOBALS['ticketing_core_post_meta'] = array(
	9 => array('_EventStartDate' => '2026-09-09 20:00:00'),
	4 => array('_EventStartDate' => '2026-09-04 19:30:00'),
);
$GLOBALS['ticketing_core_titles'] = array(9 => 'Ninth Event', 4 => 'Fourth Event');
$GLOBALS['ticketing_core_permalinks'] = array(9 => 'https://example.test/event/9', 4 => 'https://example.test/event/4');
$_POST = array('q' => ' 42 ');
$numeric_response = ticketing_core_capture_ajax(static function (): void {
	bvmgr_ticketing_ajax_search_tec_events();
});
ticketing_core_same(true, $numeric_response->success, 'Numeric TEC search should retain a success response.');
ticketing_core_same(200, $numeric_response->status, 'Numeric TEC search success status changed.');
ticketing_core_same(array(
	'items' => array(
		array(
			'id' => 9,
			'wp_id' => 9,
			'legacy' => array('legacy_id' => 'legacy-9'),
			'title' => 'Ninth Event',
			'start' => '2026-09-09 20:00:00',
			'permalink' => 'https://example.test/event/9',
		),
		array(
			'id' => 4,
			'wp_id' => 4,
			'legacy' => array('legacy_id' => 'legacy-4'),
			'title' => 'Fourth Event',
			'start' => '2026-09-04 19:30:00',
			'permalink' => 'https://example.test/event/4',
		),
	),
), $numeric_response->payload, 'Numeric TEC search result normalization/order changed.');
ticketing_core_same(array('42'), $wpdb->esc_like_calls, 'Numeric TEC search LIKE escaping input changed.');
ticketing_core_same(array('tribe_events', 42, '%42%'), $wpdb->prepares[0]['args'], 'Numeric TEC search prepare argument order changed.');
$expected_numeric_search_sql = "SELECT ID FROM wp_search_posts WHERE post_type = 'tribe_events' AND post_status NOT IN ('trash','auto-draft') AND (ID = 42 OR post_title LIKE '%42%') ORDER BY post_date DESC LIMIT 15";
ticketing_core_same($expected_numeric_search_sql, ticketing_core_normalize_sql($wpdb->prepares[0]['sql']), 'Rendered numeric TEC search SQL changed.');
ticketing_core_same($expected_numeric_search_sql, ticketing_core_normalize_sql($wpdb->executions[0]['sql']), 'Executed numeric TEC search SQL changed.');
ticketing_core_same(array(array('vms_ticketing_nonce', 'nonce')), $GLOBALS['ticketing_core_nonce_calls'], 'Numeric TEC search nonce contract changed.');
ticketing_core_no_placeholders($wpdb->executions[0]['sql']);

// Text search: wildcard escaping, nonnumeric branch, and null read failure keep an empty success payload.
$wpdb = new VMS_Ticketing_Core_WPDB_Spy('wp_text_');
$wpdb->get_col_queue[] = null;
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['ticketing_core_nonce_calls'] = array();
$_POST = array('q' => 'Oak_100%');
$text_response = ticketing_core_capture_ajax(static function (): void {
	bvmgr_ticketing_ajax_search_tec_events();
});
ticketing_core_same(true, $text_response->success, 'Text TEC search should retain a success response.');
ticketing_core_same(array('items' => array()), $text_response->payload, 'Null TEC search read should retain an empty result.');
ticketing_core_same(array('Oak_100%'), $wpdb->esc_like_calls, 'Text TEC search LIKE input changed.');
ticketing_core_same(array('tribe_events', '%Oak\\_100\\%%'), $wpdb->prepares[0]['args'], 'Text TEC search prepare argument changed.');
$expected_text_search_sql = "SELECT ID FROM wp_text_posts WHERE post_type = 'tribe_events' AND post_status NOT IN ('trash','auto-draft') AND post_title LIKE '%Oak\\\\_100\\\\%%' ORDER BY post_date DESC LIMIT 15";
ticketing_core_same($expected_text_search_sql, ticketing_core_normalize_sql($wpdb->prepares[0]['sql']), 'Rendered text TEC search SQL changed.');
ticketing_core_same($expected_text_search_sql, ticketing_core_normalize_sql($wpdb->executions[0]['sql']), 'Executed text TEC search SQL changed.');
ticketing_core_no_placeholders($wpdb->executions[0]['sql']);

// Short query, authorization failure, and TEC-unavailable branches remain DB-free and retain response/nonce ordering.
$wpdb = new VMS_Ticketing_Core_WPDB_Spy('wp_short_');
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['ticketing_core_nonce_calls'] = array();
$_POST = array('q' => 'x');
$short_response = ticketing_core_capture_ajax(static function (): void {
	bvmgr_ticketing_ajax_search_tec_events();
});
ticketing_core_same(array('items' => array()), $short_response->payload, 'Short TEC query response changed.');
ticketing_core_same(array(), $wpdb->prepares, 'Short TEC query must remain DB-free.');
ticketing_core_same(array(array('vms_ticketing_nonce', 'nonce')), $GLOBALS['ticketing_core_nonce_calls'], 'Short TEC query nonce ordering changed.');

$wpdb = new VMS_Ticketing_Core_WPDB_Spy('wp_forbidden_');
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['ticketing_core_can_edit_posts'] = false;
$GLOBALS['ticketing_core_nonce_calls'] = array();
$_POST = array('q' => 'blocked');
$forbidden_response = ticketing_core_capture_ajax(static function (): void {
	bvmgr_ticketing_ajax_search_tec_events();
});
ticketing_core_same(false, $forbidden_response->success, 'Forbidden TEC search response kind changed.');
ticketing_core_same(array('message' => 'forbidden'), $forbidden_response->payload, 'Forbidden TEC search message changed.');
ticketing_core_same(403, $forbidden_response->status, 'Forbidden TEC search status changed.');
ticketing_core_same(array(array('vms_ticketing_nonce', 'nonce')), $GLOBALS['ticketing_core_nonce_calls'], 'Nonce verification must still precede capability denial.');
ticketing_core_same(array(), $wpdb->executions, 'Forbidden TEC search must remain DB-free.');

$wpdb = new VMS_Ticketing_Core_WPDB_Spy('wp_tec_off_');
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['ticketing_core_can_edit_posts'] = true;
$GLOBALS['ticketing_core_tec_active'] = false;
$GLOBALS['ticketing_core_nonce_calls'] = array();
$_POST = array('q' => 'offline');
$tec_off_response = ticketing_core_capture_ajax(static function (): void {
	bvmgr_ticketing_ajax_search_tec_events();
});
ticketing_core_same(false, $tec_off_response->success, 'Inactive TEC response kind changed.');
ticketing_core_same(array('message' => 'tec_inactive'), $tec_off_response->payload, 'Inactive TEC response message changed.');
ticketing_core_same(400, $tec_off_response->status, 'Inactive TEC response status changed.');
ticketing_core_same(array(array('vms_ticketing_nonce', 'nonce')), $GLOBALS['ticketing_core_nonce_calls'], 'Inactive TEC nonce ordering changed.');
ticketing_core_same(array(), $wpdb->executions, 'Inactive TEC search must remain DB-free.');

fwrite(STDOUT, "PASS: Ticketing core WP_Query/wpdb behavior, exact preparation/results/failures, fourteen-row inventory, narrow annotations, immutable projections, and mirror/shadow DB parity are covered.\n");
