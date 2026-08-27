<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_CLI', true);
define('MINUTE_IN_SECONDS', 60);

function g13_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g13_same($expected, $actual, string $message): void
{
	g13_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
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
	for ($offset = $brace + 1, $length = strlen($source); $offset < $length; $offset++) {
		$depth += $source[$offset] === '{' ? 1 : 0;
		$depth -= $source[$offset] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($offset - $start) + 1);
		}
	}

	throw new RuntimeException('Unable to parse function ' . $name . '.');
}

function g13_function_projection(string $source, string $name): string
{
	return str_replace(g13_extract_function($source, $name), '/* owned function: ' . $name . ' */', $source);
}

/**
 * @param array<string,string>                                      $sources Source contents keyed by owned source name.
 * @param string[]                                                  $allowed_codes Exact source codes permitted by this slice.
 * @param array<int,array{source:string,line:int,code:string}>      $expected_rows Exact annotation locations.
 * @return string[]
 */
function g13_db_annotation_errors(array $sources, array $allowed_codes, array $expected_rows): array
{
	$errors = array();
	$actual_rows = array();

	foreach ($sources as $source_name => $source) {
		$lines = preg_split('/\R/', $source);
		if (!is_array($lines)) {
			$errors[] = 'Unable to split source: ' . $source_name;
			continue;
		}

		foreach ($lines as $index => $line) {
			if (strpos($line, 'phpcs:') === false || preg_match('/(?:WordPress\.DB|PluginCheck\.Security\.DirectDB)/i', $line) !== 1) {
				continue;
			}

			$directive = substr($line, (int) strpos($line, 'phpcs:'));
			$reason_offset = strpos($directive, ' -- ');
			if ($reason_offset !== false) {
				$directive = substr($directive, 0, $reason_offset);
			}
			$directive = trim($directive);
			if (preg_match('/^phpcs:([a-z]+)\b(?:\s+(.+))?$/i', $directive, $matches) !== 1) {
				$errors[] = sprintf('%s:%d has an unparseable DB annotation.', $source_name, $index + 1);
				continue;
			}

			$verb = strtolower($matches[1]);
			$code = isset($matches[2]) ? trim($matches[2]) : '';
			if ($verb !== 'ignore') {
				$errors[] = sprintf('%s:%d uses forbidden phpcs:%s suppression.', $source_name, $index + 1, $verb);
				continue;
			}
			if (!in_array($code, $allowed_codes, true)) {
				$errors[] = sprintf('%s:%d uses a broad, mixed, or unowned DB source code: %s', $source_name, $index + 1, $code);
				continue;
			}

			$actual_rows[] = array('source' => $source_name, 'line' => $index + 1, 'code' => $code);
		}
	}

	$signature = static function (array $row): string {
		return $row['source'] . ':' . $row['line'] . ':' . $row['code'];
	};
	$actual_signatures = array_map($signature, $actual_rows);
	$expected_signatures = array_map($signature, $expected_rows);
	sort($actual_signatures);
	sort($expected_signatures);
	if ($actual_signatures !== $expected_signatures) {
		$errors[] = 'DB annotation locations differ from the exact expected inventory.';
	}

	return $errors;
}

/**
 * @param array<int,array{code:string,reason:string}> $rows Owned annotations for one source.
 * @return array{source:string,removed:int}
 */
function g13_strip_owned_annotations(string $source, array $rows, string $label): array
{
	$removed = 0;
	foreach ($rows as $row) {
		$marker = ' // phpcs:ignore ' . $row['code'] . ' -- ' . $row['reason'];
		g13_same(1, substr_count($source, $marker), $label . ' must contain each exact owned annotation once.');
		$count = 0;
		$source = str_replace($marker, '', $source, $count);
		g13_same(1, $count, $label . ' must strip each exact owned annotation once.');
		$removed += $count;
	}

	return array('source' => $source, 'removed' => $removed);
}

$root = dirname(__DIR__);
$shadow_root = dirname($root, 2) . '/vms';
$relative_paths = array(
	'calendar_feed' => 'includes/core/calendar-feed.php',
	'calendar_ticket_counts' => 'includes/core/calendar-ticket-counts.php',
	'cancellation_adapters' => 'includes/core/cancellation-adapters.php',
	'cli_stale_check' => 'includes/core/cli/stale-check.php',
	'event_credits' => 'includes/core/event-credits.php',
	'event_feedback' => 'includes/core/event-feedback.php',
	'ticket_sales_resolver' => 'includes/core/ticket-sales-resolver.php',
);
$mirror_sources = array();
$shadow_sources = array();
foreach ($relative_paths as $source_name => $relative_path) {
	$mirror_path = $root . '/' . $relative_path;
	$shadow_path = $shadow_root . '/' . $relative_path;
	g13_assert(is_file($mirror_path), 'Missing mirror source: ' . $relative_path);
	g13_assert(is_file($shadow_path), 'Missing shadow-live source: ' . $relative_path);
	$mirror_sources[$source_name] = (string) file_get_contents($mirror_path);
	$shadow_sources[$source_name] = (string) file_get_contents($shadow_path);
	g13_assert($mirror_sources[$source_name] !== '' && $shadow_sources[$source_name] !== '', 'Owned source must be readable: ' . $relative_path);
}

$meta_key_code = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_key';
$meta_query_code = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query';
$meta_value_code = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_value';
$inventory = array(
	array('source' => 'event_credits', 'file' => $relative_paths['event_credits'], 'line' => 125, 'column' => 17, 'code' => $meta_key_code, 'anchor' => "'meta_key' => '_vms_event_credit_code'", 'reason' => 'Credit-code generation performs at most 20 single-ID collision checks against the canonical event-credit code meta key.'),
	array('source' => 'event_credits', 'file' => $relative_paths['event_credits'], 'line' => 126, 'column' => 17, 'code' => $meta_value_code, 'anchor' => "'meta_value' => \$code", 'reason' => 'Credit-code generation performs at most 20 single-ID collision checks for each generated exact credit-code value.'),
	array('source' => 'event_credits', 'file' => $relative_paths['event_credits'], 'line' => 165, 'column' => 13, 'code' => $meta_query_code, 'anchor' => "'meta_query' => array(", 'reason' => 'Credit issuance performs one single-ID lookup by the exact original Event Plan and order pair to preserve idempotency.'),
	array('source' => 'cancellation_adapters', 'file' => $relative_paths['cancellation_adapters'], 'line' => 201, 'column' => 21, 'code' => $meta_query_code, 'anchor' => "'meta_query' => \$meta_query", 'reason' => 'Cancellation discovery intentionally retrieves every product ID linked to the Event Plan or TEC event so refund and sales-stop processing cannot omit products.'),
	array('source' => 'cancellation_adapters', 'file' => $relative_paths['cancellation_adapters'], 'line' => 550, 'column' => 17, 'code' => $meta_query_code, 'anchor' => "'meta_query' => array(", 'reason' => 'When the provider API yields no RSVP tickets, cancellation fallback intentionally retrieves every RSVP ticket ID for the exact TEC event before disabling it.'),
	array('source' => 'cancellation_adapters', 'file' => $relative_paths['cancellation_adapters'], 'line' => 1381, 'column' => 17, 'code' => $meta_key_code, 'anchor' => "'meta_key' => '_vms_staff_id'", 'reason' => 'Staff notification resolution performs one single-user fallback by the canonical Staff-link meta key only when the direct link has no usable email.'),
	array('source' => 'cancellation_adapters', 'file' => $relative_paths['cancellation_adapters'], 'line' => 1382, 'column' => 17, 'code' => $meta_value_code, 'anchor' => "'meta_value' => (string) \$staff_id", 'reason' => 'Staff notification resolution performs one single-user fallback by the exact Staff ID only when the direct link has no usable email.'),
	array('source' => 'calendar_feed', 'file' => $relative_paths['calendar_feed'], 'line' => 1020, 'column' => 13, 'code' => $meta_key_code, 'anchor' => "'meta_key' => \$k_date", 'reason' => 'Calendar feed ordering intentionally uses canonical event-date metadata for the complete requested date/venue result set before response caching.'),
	array('source' => 'calendar_feed', 'file' => $relative_paths['calendar_feed'], 'line' => 1023, 'column' => 13, 'code' => $meta_query_code, 'anchor' => "'meta_query' => \$meta_query", 'reason' => 'Calendar feed intentionally retrieves the complete Event Plan ID set within the requested date and optional Venue bounds before response caching.'),
	array('source' => 'calendar_ticket_counts', 'file' => $relative_paths['calendar_ticket_counts'], 'line' => 177, 'column' => 13, 'code' => $meta_query_code, 'anchor' => "'meta_query' => array(", 'reason' => 'Ticket-count refresh intentionally retrieves every Event Plan ID linked to the exact TEC event so each affected plan count is recalculated.'),
	array('source' => 'calendar_ticket_counts', 'file' => $relative_paths['calendar_ticket_counts'], 'line' => 279, 'column' => 13, 'code' => $meta_query_code, 'anchor' => "'meta_query' => array(", 'reason' => 'Nightly ticket-count maintenance intentionally retrieves the complete Event Plan ID set in its configured future date window before recalculation.'),
	array('source' => 'cli_stale_check', 'file' => $relative_paths['cli_stale_check'], 'line' => 182, 'column' => 17, 'code' => $meta_query_code, 'anchor' => "'meta_query' => array(", 'reason' => 'BUG-01 stale-check intentionally samples at most 300 Event Plan IDs with draft workflow status and linked TEC metadata for diagnosis.'),
	array('source' => 'cli_stale_check', 'file' => $relative_paths['cli_stale_check'], 'line' => 337, 'column' => 17, 'code' => $meta_query_code, 'anchor' => "'meta_query' => array(", 'reason' => 'BUG-03 stale-check intentionally samples at most 150 Event Plan IDs missing either start- or end-time metadata for diagnosis.'),
	array('source' => 'cli_stale_check', 'file' => $relative_paths['cli_stale_check'], 'line' => 1098, 'column' => 17, 'code' => $meta_query_code, 'anchor' => "'meta_query' => array(", 'reason' => 'CAN-01 stale-check intentionally samples at most 300 cancelled Event Plan IDs to audit their cancellation-job state.'),
	array('source' => 'event_feedback', 'file' => $relative_paths['event_feedback'], 'line' => 677, 'column' => 19, 'code' => $meta_query_code, 'anchor' => "\$args['meta_query'] = array(", 'reason' => 'The response list applies this exact Event Plan metadata filter only when requested; the caller-supplied limit continues to control result scope.'),
	array('source' => 'event_feedback', 'file' => $relative_paths['event_feedback'], 'line' => 901, 'column' => 13, 'code' => $meta_query_code, 'anchor' => "'meta_query' => array(", 'reason' => 'Submission deduplication performs one single-ID response lookup by the exact Event Plan and selected metadata token.'),
	array('source' => 'event_feedback', 'file' => $relative_paths['event_feedback'], 'line' => 935, 'column' => 13, 'code' => $meta_query_code, 'anchor' => "'meta_query' => array(", 'reason' => 'Recent-submission deduplication performs one single-ID lookup by exact identity fingerprints inside the configured UTC window.'),
	array('source' => 'ticket_sales_resolver', 'file' => $relative_paths['ticket_sales_resolver'], 'line' => 47, 'column' => 13, 'code' => $meta_query_code, 'anchor' => "'meta_query' => array(", 'reason' => 'Ticket-sales resolution retrieves the complete attendee ID set for one exact order and order-item pair once per request-local cache key.'),
);

g13_same(18, count($inventory), 'Wave 4 core-services ownership must remain exactly 18 DB rows.');
$code_counts = array_count_values(array_column($inventory, 'code'));
ksort($code_counts);
$expected_code_counts = array($meta_key_code => 3, $meta_query_code => 13, $meta_value_code => 2);
ksort($expected_code_counts);
g13_same($expected_code_counts, $code_counts, 'Artifact-derived rule split must remain K3/Q13/V2.');

foreach ($inventory as $row) {
	$lines = preg_split('/\R/', $mirror_sources[$row['source']]);
	g13_assert(is_array($lines) && isset($lines[$row['line'] - 1]), 'Artifact-owned line should exist: ' . $row['file'] . ':' . $row['line']);
	$line = $lines[$row['line'] - 1];
	g13_contains($row['anchor'], $line, 'Owned annotation must remain on its exact query anchor: ' . $row['file'] . ':' . $row['line']);
	$annotation = 'phpcs:ignore ' . $row['code'] . ' -- ' . $row['reason'];
	g13_contains($annotation, $line, 'Owned row must carry its exact installed source code and rationale: ' . $row['file'] . ':' . $row['line']);
	g13_same(1, substr_count($line, 'phpcs:ignore'), 'Owned row must contain exactly one line-local annotation: ' . $row['file'] . ':' . $row['line']);
}

$allowed_codes = array($meta_key_code, $meta_query_code, $meta_value_code);
$mirror_expected_annotations = array_map(
	static function (array $row): array {
		return array('source' => $row['source'], 'line' => $row['line'], 'code' => $row['code']);
	},
	$inventory
);
$shadow_expected_annotations = array_map(
	static function (array $row): array {
		return array(
			'source' => $row['source'],
			'line' => $row['line'] - ($row['source'] === 'calendar_feed' ? 4 : 0),
			'code' => $row['code'],
		);
	},
	$inventory
);
g13_same(array(), g13_db_annotation_errors($mirror_sources, $allowed_codes, $mirror_expected_annotations), 'Mirror DB annotations must equal the exact 18-row inventory.');
g13_same(array(), g13_db_annotation_errors($shadow_sources, $allowed_codes, $shadow_expected_annotations), 'Shadow-live DB annotations must equal the exact 18-row inventory.');

$negative_annotations = array(
	'block disable' => '// phpcs:disable WordPress.DB',
	'block enable' => '// phpcs:enable WordPress.DB',
	'file ignore' => '// phpcs:ignoreFile WordPress.DB',
	'DB category' => '// phpcs:ignore WordPress.DB -- forbidden category suppression',
	'slow-query family' => '// phpcs:ignore WordPress.DB.SlowDBQuery -- forbidden family suppression',
	'prepared-SQL family' => '// phpcs:ignore WordPress.DB.PreparedSQL -- forbidden family suppression',
	'direct-query family' => '// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- forbidden family suppression',
	'Plugin Check direct-DB family' => '// phpcs:ignore PluginCheck.Security.DirectDB -- forbidden family suppression',
	'mixed installed codes' => '// phpcs:ignore ' . $meta_key_code . ',' . $meta_query_code . ' -- forbidden mixed-list suppression',
);
foreach ($negative_annotations as $label => $annotation) {
	$mutated_sources = $mirror_sources;
	$mutated_sources['ticket_sales_resolver'] .= "\n" . $annotation . "\n";
	g13_assert(g13_db_annotation_errors($mutated_sources, $allowed_codes, $mirror_expected_annotations) !== array(), 'Annotation audit must reject negative control: ' . $label);
}

$event_credit_current_date = "\t\t\t\$today = wp_date('Y-m-d', time(), wp_timezone());";
$event_credit_historical_date = "\t\t\t\$today = function_exists('wp_date') ? wp_date('Y-m-d', time(), wp_timezone()) : date('Y-m-d');";
foreach (array('mirror' => $mirror_sources['event_credits'], 'shadow' => $shadow_sources['event_credits']) as $tree_name => $event_credit_source) {
	$date_lines = preg_split('/\R/', $event_credit_source);
	g13_assert(is_array($date_lines) && isset($date_lines[843]), 'Adjacent Event Credits date boundary line must remain present: ' . $tree_name);
	g13_same($event_credit_current_date, $date_lines[843], 'Adjacent Event Credits date boundary must use the direct site-local WordPress API: ' . $tree_name);
	g13_assert(strpos($date_lines[843], 'phpcs:') === false, 'Adjacent Event Credits date boundary must remain unsuppressed: ' . $tree_name);
}

$baseline_hashes = array(
	'mirror' => array(
		'calendar_feed' => '227307882b706b4eeaa6f86b9e92987dadeedeef3aa6939e10b985ef607a1ab5',
		'calendar_ticket_counts' => 'fc483ca2a66386c1897fca5386ba800cd6e2e9b14672d9f5f49ff1050fc3b5e3',
		'cancellation_adapters' => 'd7692f94b717ee1518ce41b5afa3f228a7dfd8c8ae9b64184572b0069509894e',
		'cli_stale_check' => 'a2a87825eea75a4c7b07f86d74c3b622736d58756a8913a6d7da4aab122854d4',
		'event_credits' => '9072259cf9d5514038ff2f12b6c8f7ed0acbbf58d9702f004c7b07ce58320739',
		'event_feedback' => 'bf00e40ec4188b5faab145f65f4bcbd7bdb8ba5c80b71d0c24480b38c89e4d82',
		'ticket_sales_resolver' => 'd7fdb7514713f14f3c833df320359916d79e50c30f7ecd35edd017d865760b9e',
	),
	'shadow' => array(
		'calendar_feed' => 'b94b0fa846050d01ef90ecfb9b6b31b7b7662b4f8a473f5e763429d86c3a859a',
		'calendar_ticket_counts' => 'fc483ca2a66386c1897fca5386ba800cd6e2e9b14672d9f5f49ff1050fc3b5e3',
		'cancellation_adapters' => 'd7692f94b717ee1518ce41b5afa3f228a7dfd8c8ae9b64184572b0069509894e',
		'cli_stale_check' => 'a2a87825eea75a4c7b07f86d74c3b622736d58756a8913a6d7da4aab122854d4',
		'event_credits' => '9072259cf9d5514038ff2f12b6c8f7ed0acbbf58d9702f004c7b07ce58320739',
		'event_feedback' => 'bf00e40ec4188b5faab145f65f4bcbd7bdb8ba5c80b71d0c24480b38c89e4d82',
		'ticket_sales_resolver' => 'd7fdb7514713f14f3c833df320359916d79e50c30f7ecd35edd017d865760b9e',
	),
);
$projected_sources = array();
foreach (array('mirror' => $mirror_sources, 'shadow' => $shadow_sources) as $tree_name => $sources) {
	$total_removed = 0;
	foreach ($sources as $source_name => $source) {
		$rows = array_values(array_filter(
			$inventory,
			static function (array $row) use ($source_name): bool {
				return $row['source'] === $source_name;
			}
		));
		$projection = g13_strip_owned_annotations($source, $rows, $tree_name . ' ' . $source_name);
		if ($source_name === 'event_credits') {
			$date_projection_count = 0;
			$projection['source'] = str_replace($event_credit_current_date, $event_credit_historical_date, $projection['source'], $date_projection_count);
			g13_same(1, $date_projection_count, ucfirst($tree_name) . ' Event Credits date projection must restore exactly one historical statement.');
		}
		g13_same($baseline_hashes[$tree_name][$source_name], hash('sha256', $projection['source']), $tree_name . ' ' . $source_name . ' must remain annotation-only.');
		$projected_sources[$tree_name][$source_name] = $projection['source'];
		$total_removed += $projection['removed'];
	}
	g13_same(18, $total_removed, ucfirst($tree_name) . ' projection must strip exactly 18 owned comments.');
}

$mutation_count = 0;
$mutated_projection = str_replace("'posts_per_page' => 1,\n\t\t\t\t'meta_key' => '_vms_event_credit_code'", "'posts_per_page' => 2,\n\t\t\t\t'meta_key' => '_vms_event_credit_code'", $projected_sources['mirror']['event_credits'], $mutation_count);
g13_same(1, $mutation_count, 'Runtime mutation control must change exactly one credit-code query limit.');
g13_assert(!hash_equals($baseline_hashes['mirror']['event_credits'], hash('sha256', $mutated_projection)), 'Whole-source projection hash must reject non-annotation runtime drift.');

foreach (array('calendar_ticket_counts', 'cancellation_adapters', 'cli_stale_check', 'event_credits', 'event_feedback', 'ticket_sales_resolver') as $source_name) {
	g13_same($mirror_sources[$source_name], $shadow_sources[$source_name], 'Full mirror/shadow-live parity must remain intact: ' . $relative_paths[$source_name]);
}
g13_assert($mirror_sources['calendar_feed'] !== $shadow_sources['calendar_feed'], 'Calendar Feed whole-file structural divergence must remain intact.');
g13_same(g13_extract_function($mirror_sources['calendar_feed'], 'bvmgr_get_calendar_events'), g13_extract_function($shadow_sources['calendar_feed'], 'bvmgr_get_calendar_events'), 'Owned Calendar Feed function must retain exact mirror/shadow-live parity.');
g13_same('745b266c3e0b4569ecf63842d082db14018a5c60b2542583290d005f8923177c', hash('sha256', g13_function_projection($mirror_sources['calendar_feed'], 'bvmgr_get_calendar_events')), 'Mirror Calendar Feed outside-owned projection changed.');
g13_same('0915721d579ebf17b8ecb196cbaeebfdce7bca39af2ab2c65b2fff5459b06dc2', hash('sha256', g13_function_projection($shadow_sources['calendar_feed'], 'bvmgr_get_calendar_events')), 'Shadow-live Calendar Feed outside-owned projection changed.');

final class WP_Post
{
	public int $ID;
	public string $post_type;
	public string $post_status;
	public string $post_title;

	public function __construct(int $id, string $post_type = '', string $post_status = '', string $post_title = '')
	{
		$this->ID = $id;
		$this->post_type = $post_type;
		$this->post_status = $post_status;
		$this->post_title = $post_title;
	}
}

final class WP_User
{
	public int $ID;
	public string $user_email;
	public string $display_name;

	public function __construct(int $id, string $email, string $display_name = '')
	{
		$this->ID = $id;
		$this->user_email = $email;
		$this->display_name = $display_name;
	}
}

final class WP_Error
{
	private string $message;

	public function __construct(string $message)
	{
		$this->message = $message;
	}

	public function get_error_message(): string
	{
		return $this->message;
	}
}

final class WP_CLI
{
	public static array $commands = array();

	public static function add_command(string $name, string $class): void
	{
		self::$commands[$name] = $class;
	}

	public static function warning(string $message): void
	{
		unset($message);
	}

	public static function log(string $message): void
	{
		unset($message);
	}
}

final class WP_Query
{
	public $posts;

	public function __construct(array $args)
	{
		$this->posts = g13_take_query('WP_Query', $args);
	}
}

$GLOBALS['g13_query_queue'] = array();
$GLOBALS['g13_query_calls'] = array();
$GLOBALS['g13_post_meta'] = array();
$GLOBALS['g13_posts'] = array();
$GLOBALS['g13_users'] = array();
$GLOBALS['g13_user_query_queue'] = array();
$GLOBALS['g13_user_query_calls'] = array();
$GLOBALS['g13_registered_filters'] = array();
$GLOBALS['g13_updates'] = array();
$GLOBALS['g13_transients'] = array();
$GLOBALS['g13_filter_values'] = array();
$GLOBALS['g13_password_queue'] = array();
$GLOBALS['g13_coupon_ids'] = array();
$GLOBALS['g13_ticket_product_ids'] = array();

function g13_reset_runtime(): void
{
	$GLOBALS['g13_query_queue'] = array();
	$GLOBALS['g13_query_calls'] = array();
	$GLOBALS['g13_post_meta'] = array();
	$GLOBALS['g13_posts'] = array();
	$GLOBALS['g13_users'] = array();
	$GLOBALS['g13_user_query_queue'] = array();
	$GLOBALS['g13_user_query_calls'] = array();
	$GLOBALS['g13_updates'] = array();
	$GLOBALS['g13_transients'] = array();
	$GLOBALS['g13_filter_values'] = array();
	$GLOBALS['g13_password_queue'] = array();
	$GLOBALS['g13_coupon_ids'] = array();
	$GLOBALS['g13_ticket_product_ids'] = array();
}

function g13_take_query(string $api, array $args)
{
	if ($GLOBALS['g13_query_queue'] === array()) {
		throw new RuntimeException('Unexpected query without a queued result: ' . $api . ' ' . var_export($args, true));
	}
	$result = array_shift($GLOBALS['g13_query_queue']);
	$GLOBALS['g13_query_calls'][] = array('api' => $api, 'args' => $args, 'result' => $result);
	return $result;
}

function get_posts(array $args = array())
{
	return g13_take_query('get_posts', $args);
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($hook, $callback, $priority, $accepted_args);
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	$GLOBALS['g13_registered_filters'][$hook][] = array('callback' => $callback, 'priority' => $priority, 'accepted_args' => $accepted_args);
	return true;
}

function apply_filters(string $hook, $value, ...$args)
{
	unset($args);
	return array_key_exists($hook, $GLOBALS['g13_filter_values']) ? $GLOBALS['g13_filter_values'][$hook] : $value;
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

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
	return is_scalar($value) ? trim((string) $value) : '';
}

function sanitize_email($value): string
{
	return is_scalar($value) ? strtolower(trim((string) $value)) : '';
}

function is_email($value): bool
{
	return filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false;
}

function wp_strip_all_tags(string $value): string
{
	return strip_tags($value);
}

function wp_json_encode($value): string
{
	$encoded = json_encode($value);
	return is_string($encoded) ? $encoded : '';
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('UTC');
}

function bvmgr_get_timezone(): DateTimeZone
{
	return wp_timezone();
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
	$timestamp = $timestamp ?? time();
	$timezone = $timezone ?? wp_timezone();
	return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format);
}

function bvmgr_meta_key(string $scope, string $field): string
{
	$map = array(
		'event_plan:date' => '_vms_event_date',
		'event_plan:venue_id' => '_vms_venue_id',
		'event_plan:tec_event_id' => '_vms_tec_event_id',
		'event_plan:tec_event_url' => '_vms_tec_event_url',
		'event_plan:status' => '_vms_event_plan_status',
		'event_plan:integrity_issue' => '_vms_integrity_issue',
		'event_plan:cancel_job_id' => '_vms_cancel_job_id',
		'event_plan:cancel_job_state' => '_vms_cancel_job_state',
		'event_plan:cancel_job_summary' => '_vms_cancel_job_summary',
		'event_plan:cancel_requires_operator_review' => '_vms_cancel_requires_operator_review',
		'event_plan:tickets_sold_count' => '_vms_tickets_sold_count',
		'product:event_plan_id' => '_vms_event_plan_id',
		'product:tec_event_id' => '_vms_tec_event_id',
		'product:product_role' => '_vms_product_role',
		'product:ticketing_entitlement_id' => '_vms_ticketing_entitlement_id',
	);
	return $map[$scope . ':' . $field] ?? ('_vms_' . $field);
}

function get_option(string $key, $default = false)
{
	unset($key);
	return $default;
}

function get_transient(string $key)
{
	return array_key_exists($key, $GLOBALS['g13_transients']) ? $GLOBALS['g13_transients'][$key] : false;
}

function set_transient(string $key, $value, int $expiration): bool
{
	unset($expiration);
	$GLOBALS['g13_transients'][$key] = $value;
	return true;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	return $GLOBALS['g13_post_meta'][$post_id][$key] ?? ($single ? '' : array());
}

function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['g13_post_meta'][$post_id][$key] = $value;
	$GLOBALS['g13_updates'][] = array($post_id, $key, $value);
	return true;
}

function delete_post_meta(int $post_id, string $key, $value = null): bool
{
	unset($value);
	unset($GLOBALS['g13_post_meta'][$post_id][$key]);
	return true;
}

function add_post_meta(int $post_id, string $key, $value, bool $unique = false): int
{
	unset($post_id, $key, $value, $unique);
	return 1;
}

function get_post(int $post_id)
{
	return $GLOBALS['g13_posts'][$post_id] ?? null;
}

function get_post_type(int $post_id): string
{
	$post = get_post($post_id);
	return $post instanceof WP_Post ? $post->post_type : '';
}

function get_post_status(int $post_id): string
{
	$post = get_post($post_id);
	return $post instanceof WP_Post ? $post->post_status : '';
}

function get_the_title(int $post_id): string
{
	$post = get_post($post_id);
	return $post instanceof WP_Post ? $post->post_title : '';
}

function wp_update_post(array $data, bool $wp_error = false)
{
	unset($wp_error);
	return absint($data['ID'] ?? 0);
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function get_users(array $args): array
{
	$result = $GLOBALS['g13_user_query_queue'] === array() ? array() : array_shift($GLOBALS['g13_user_query_queue']);
	$GLOBALS['g13_user_query_calls'][] = array('args' => $args, 'result' => $result);
	return is_array($result) ? $result : array();
}

function get_user_by(string $field, $value)
{
	unset($field);
	return $GLOBALS['g13_users'][absint($value)] ?? false;
}

function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string
{
	unset($length, $special_chars, $extra_special_chars);
	return $GLOBALS['g13_password_queue'] === array() ? 'DEFAULT1' : (string) array_shift($GLOBALS['g13_password_queue']);
}

function wp_generate_uuid4(): string
{
	return '00000000-0000-4000-8000-000000000000';
}

function wc_get_coupon_id_by_code(string $code): int
{
	return absint($GLOBALS['g13_coupon_ids'][$code] ?? 0);
}

function vms_get_ticket_product_ids_for_event(int $tec_event_id): array
{
	unset($tec_event_id);
	return $GLOBALS['g13_ticket_product_ids'];
}

foreach ($relative_paths as $relative_path) {
	require_once $root . '/' . $relative_path;
}

g13_same('BVMGR_CLI_Stale_Check_Command', WP_CLI::$commands['vms'] ?? '', 'CLI stale-check command registration must remain intact.');

// Calendar Feed retains complete date/venue query arguments, empty/failure handling, and request-key caching.
g13_reset_runtime();
$calendar_args = array(
	'context' => 'public',
	'start_date' => '2026-08-10',
	'end_date' => '2026-08-01',
	'venue_ids' => array(7, 7, 0),
	'include_statuses' => array('published', 'ready'),
	'include_vendor_types' => array('Band', 'food_truck'),
	'viewer_vendor_id' => 44,
	'include_past' => false,
	'include_open_close_shading' => true,
);
$GLOBALS['g13_query_queue'][] = array();
g13_same(array(), bvmgr_get_calendar_events($calendar_args), 'Empty Calendar Feed query should retain its empty result.');
$calendar_query = $GLOBALS['g13_query_calls'][0];
$calendar_meta_query = array(
	'relation' => 'AND',
	array('key' => '_vms_event_date', 'value' => '2026-08-01', 'compare' => '>=', 'type' => 'DATE'),
	array('key' => '_vms_event_date', 'value' => '2026-08-10', 'compare' => '<=', 'type' => 'DATE'),
	array('key' => '_vms_venue_id', 'value' => array(7), 'compare' => 'IN', 'type' => 'NUMERIC'),
);
g13_same(
	array(
		'post_type' => 'vms_event_plan',
		'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
		'posts_per_page' => -1,
		'fields' => 'ids',
		'meta_key' => '_vms_event_date',
		'orderby' => 'meta_value',
		'order' => 'ASC',
		'meta_query' => $calendar_meta_query,
		'no_found_rows' => true,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => false,
	),
	$calendar_query['args'],
	'Calendar Feed get_posts() arguments must remain exact.'
);
g13_same(array(), $calendar_query['result'], 'Captured Calendar Feed query result must remain unchanged.');
$calendar_call_count = count($GLOBALS['g13_query_calls']);
g13_same(array(), bvmgr_get_calendar_events($calendar_args), 'Cached empty Calendar Feed result should remain empty.');
g13_same($calendar_call_count, count($GLOBALS['g13_query_calls']), 'Cached Calendar Feed result must not repeat get_posts().');
$GLOBALS['g13_query_queue'][] = false;
$failed_calendar_args = $calendar_args;
$failed_calendar_args['start_date'] = '2026-09-01';
$failed_calendar_args['end_date'] = '2026-09-02';
g13_same(array(), bvmgr_get_calendar_events($failed_calendar_args), 'Non-array Calendar Feed query failures should fail closed.');
g13_same(false, $GLOBALS['g13_query_calls'][1]['result'], 'Calendar Feed failure result should be captured unchanged.');

// Credit-code collision probes remain single-ID, bounded to 20 attempts, and preserve their UUID fallback.
g13_reset_runtime();
$GLOBALS['g13_password_queue'] = array('TAKEN001', 'FREE0001');
$GLOBALS['g13_coupon_ids']['EVENT-CREDIT-TAKEN001'] = 41;
$GLOBALS['g13_query_queue'] = array(array(901), array());
g13_same('EVENT-CREDIT-FREE0001', vms_event_credit_generate_code(), 'Credit-code generation should preserve collision retry behavior.');
g13_same(2, count($GLOBALS['g13_query_calls']), 'Credit generation should issue one query per attempted code.');
g13_same(
	array(
		'post_type' => BVMGR_CPT_EVENT_CREDIT,
		'post_status' => array('publish', 'private', 'draft'),
		'fields' => 'ids',
		'posts_per_page' => 1,
		'meta_key' => '_vms_event_credit_code',
		'meta_value' => 'EVENT-CREDIT-TAKEN001',
	),
	$GLOBALS['g13_query_calls'][0]['args'],
	'First credit-code collision query arguments must remain exact.'
);
g13_same(array(901), $GLOBALS['g13_query_calls'][0]['result'], 'First credit-code query result must remain unchanged.');
g13_same('EVENT-CREDIT-FREE0001', $GLOBALS['g13_query_calls'][1]['args']['meta_value'], 'Second credit-code probe must use the next exact generated value.');
g13_same(array(), $GLOBALS['g13_query_calls'][1]['result'], 'Available credit-code query result must remain unchanged.');

g13_reset_runtime();
for ($index = 0; $index < 20; $index++) {
	$GLOBALS['g13_password_queue'][] = sprintf('C%07d', $index);
	$GLOBALS['g13_query_queue'][] = array(1000 + $index);
}
g13_same('EVENT-CREDIT-00000000-0000-4000-8000-000000000000', vms_event_credit_generate_code(), 'Twenty collisions should retain the UUID fallback.');
g13_same(20, count($GLOBALS['g13_query_calls']), 'Credit collision probing must remain capped at 20 queries.');

g13_reset_runtime();
g13_same(0, vms_event_credit_find_existing(0, 10), 'Invalid credit identity should fail closed without querying.');
g13_same(array(), $GLOBALS['g13_query_calls'], 'Invalid credit identity must not query.');
$GLOBALS['g13_query_queue'][] = array(333);
g13_same(333, vms_event_credit_find_existing(12, 34), 'Existing credit lookup should return the first exact match.');
g13_same(
	array(
		'post_type' => BVMGR_CPT_EVENT_CREDIT,
		'post_status' => array('publish', 'private', 'draft'),
		'fields' => 'ids',
		'posts_per_page' => 1,
		'orderby' => 'ID',
		'order' => 'DESC',
		'meta_query' => array(
			'relation' => 'AND',
			array('key' => '_vms_event_credit_original_event_plan_id', 'value' => 12, 'compare' => '='),
			array('key' => '_vms_event_credit_original_order_id', 'value' => 34, 'compare' => '='),
		),
	),
	$GLOBALS['g13_query_calls'][0]['args'],
	'Existing credit get_posts() arguments must remain exact.'
);
g13_same(array(333), $GLOBALS['g13_query_calls'][0]['result'], 'Existing credit query result must remain unchanged.');
$GLOBALS['g13_query_queue'][] = array();
g13_same(0, vms_event_credit_find_existing(12, 35), 'Missing credit identity should retain zero fallback.');

// Cancellation discovery retains complete linked products, fallback RSVP coverage, and single-user resolution.
g13_reset_runtime();
g13_same(array(), vms_cancellation_get_event_refundable_product_ids(0, 0), 'Invalid cancellation identity should fail closed.');
g13_same(array(), $GLOBALS['g13_query_calls'], 'Invalid cancellation identity must not query.');
$GLOBALS['g13_ticket_product_ids'] = array(11, 12);
$GLOBALS['g13_post_meta'][13] = array('_vms_product_role' => 'ticket', '_vms_tec_event_id' => 55);
$GLOBALS['g13_query_queue'][] = array(13, 0, 13);
g13_same(array(11, 12, 13), vms_cancellation_get_event_refundable_product_ids(0, 55), 'Cancellation product discovery should preserve merged, normalized results.');
g13_same(
	array(
		'post_type' => 'product',
		'posts_per_page' => -1,
		'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
		'fields' => 'ids',
		'meta_query' => array(
			'relation' => 'OR',
			array('key' => '_tribe_wooticket_for_event', 'value' => '55', 'compare' => '='),
			array('key' => '_vms_tec_event_id', 'value' => '55', 'compare' => '='),
		),
	),
	$GLOBALS['g13_query_calls'][0]['args'],
	'Cancellation product-discovery arguments must remain exact.'
);
g13_same(array(13, 0, 13), $GLOBALS['g13_query_calls'][0]['result'], 'Cancellation product query result must remain unchanged before normalization.');
$GLOBALS['g13_query_queue'][] = false;
g13_same(array(11, 12), vms_cancellation_get_event_refundable_product_ids(0, 56), 'Cancellation query failure should retain provider-derived ticket IDs.');

g13_reset_runtime();
$provider_callbacks = $GLOBALS['g13_registered_filters']['vms_cancellation_run_step'] ?? array();
g13_assert(isset($provider_callbacks[0]['callback']) && is_callable($provider_callbacks[0]['callback']), 'Cancellation provider-sales-stop callback should remain registered.');
$GLOBALS['g13_post_meta'][21]['_vms_tec_event_id'] = 501;
$GLOBALS['g13_query_queue'] = array(array(), array(), array(701, 701, 0));
$provider_result = $provider_callbacks[0]['callback'](null, 21, 'refunds', 'provider_sales_stop', array());
g13_same('done', $provider_result['status'] ?? '', 'Cancellation sales-stop fallback should complete when updates succeed.');
g13_same(array(701), $provider_result['data']['rsvp_tickets_disabled'] ?? array(), 'Cancellation RSVP fallback should normalize and disable every matched ID.');
g13_same(3, count($GLOBALS['g13_query_calls']), 'Cancellation fallback should preserve product, parent, and RSVP-meta query sequence.');
g13_same(
	array(
		'post_type' => 'tribe_rsvp_tickets',
		'posts_per_page' => -1,
		'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
		'fields' => 'ids',
		'meta_query' => array(array('key' => '_tribe_rsvp_for_event', 'value' => '501', 'compare' => '=')),
	),
	$GLOBALS['g13_query_calls'][2]['args'],
	'Cancellation RSVP fallback meta-query arguments must remain exact.'
);
g13_same(array(701, 701, 0), $GLOBALS['g13_query_calls'][2]['result'], 'Cancellation RSVP fallback result must remain unchanged before normalization.');

g13_reset_runtime();
g13_same(0, vms_cancellation_resolve_staff_notification_recipient(0)['staff_id'], 'Invalid Staff recipient should fail closed.');
g13_same(array(), $GLOBALS['g13_user_query_calls'], 'Invalid Staff recipient must not query users.');
$GLOBALS['g13_user_query_queue'][] = array(900);
$GLOBALS['g13_users'][900] = new WP_User(900, 'staff@example.test', 'Staff Person');
$staff_recipient = vms_cancellation_resolve_staff_notification_recipient(55);
g13_same('staff@example.test', $staff_recipient['email'], 'Staff reverse-meta fallback should preserve resolved email.');
g13_same('user_staff_meta', $staff_recipient['email_source'], 'Staff reverse-meta fallback should preserve email source.');
g13_same(
	array('meta_key' => '_vms_staff_id', 'meta_value' => '55', 'number' => 1, 'fields' => 'ids'),
	$GLOBALS['g13_user_query_calls'][0]['args'],
	'Staff fallback get_users() arguments must remain exact.'
);
g13_same(array(900), $GLOBALS['g13_user_query_calls'][0]['result'], 'Staff fallback query result must remain unchanged.');
$GLOBALS['g13_post_meta'][56]['_vms_linked_user_id'] = 901;
$GLOBALS['g13_users'][901] = new WP_User(901, 'linked@example.test', 'Linked Person');
$user_query_count = count($GLOBALS['g13_user_query_calls']);
g13_same('linked@example.test', vms_cancellation_resolve_staff_notification_recipient(56)['email'], 'Direct Staff/user link should still take precedence.');
g13_same($user_query_count, count($GLOBALS['g13_user_query_calls']), 'Usable direct Staff/user link must not issue fallback usermeta query.');

// Calendar ticket counts retain exact linkage and configured nightly-window queries.
g13_reset_runtime();
g13_same(array(), vms_calendar_ticket_counts_find_plan_ids_by_tec_event(0), 'Invalid TEC identity should fail closed.');
g13_same(array(), $GLOBALS['g13_query_calls'], 'Invalid TEC identity must not query Event Plans.');
$GLOBALS['g13_query_queue'][] = array(41, 41, 0, 42);
g13_same(array(41, 42), vms_calendar_ticket_counts_find_plan_ids_by_tec_event(77), 'TEC-linked plan lookup should preserve normalized complete results.');
g13_same(
	array(
		'post_type' => 'vms_event_plan',
		'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'meta_query' => array(array('key' => '_vms_tec_event_id', 'value' => 77, 'compare' => '=', 'type' => 'NUMERIC')),
	),
	$GLOBALS['g13_query_calls'][0]['args'],
	'TEC-linked plan get_posts() arguments must remain exact.'
);
g13_same(array(41, 41, 0, 42), $GLOBALS['g13_query_calls'][0]['result'], 'TEC-linked plan query result must remain unchanged before normalization.');
$today = wp_date('Y-m-d', time(), wp_timezone());
$end = gmdate('Y-m-d', strtotime('+60 days', strtotime($today)));
$GLOBALS['g13_query_queue'][] = array(51, 52);
vms_calendar_ticket_counts_nightly_scan();
g13_same(
	array(
		'post_type' => 'vms_event_plan',
		'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'meta_query' => array(
			'relation' => 'AND',
			array('key' => '_vms_event_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'),
			array('key' => '_vms_event_date', 'value' => $end, 'compare' => '<=', 'type' => 'DATE'),
		),
	),
	$GLOBALS['g13_query_calls'][1]['args'],
	'Nightly ticket-count get_posts() arguments must remain exact.'
);
g13_same(array(51, 52), $GLOBALS['g13_query_calls'][1]['result'], 'Nightly ticket-count query result must remain unchanged.');

// CLI diagnostics retain exact bounded WP_Query shapes and empty/non-empty reporting branches.
g13_reset_runtime();
$command = new BVMGR_CLI_Stale_Check_Command();
$invoke_private = static function (object $object, string $method_name): array {
	$method = new ReflectionMethod($object, $method_name);
	$result = $method->invoke($object);
	return is_array($result) ? $result : array();
};
$GLOBALS['g13_query_queue'][] = false;
$bug_01 = $invoke_private($command, 'check_bug_01');
g13_same('pass', $bug_01['signal'] ?? '', 'BUG-01 non-array query result should retain empty/pass fallback.');
g13_same(
	array(
		'post_type' => 'vms_event_plan',
		'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
		'posts_per_page' => 300,
		'fields' => 'ids',
		'meta_query' => array(
			'relation' => 'AND',
			array('key' => '_vms_event_plan_status', 'value' => 'draft', 'compare' => '='),
			array('key' => '_vms_tec_event_id', 'compare' => 'EXISTS'),
		),
	),
	$GLOBALS['g13_query_calls'][0]['args'],
	'BUG-01 WP_Query arguments must remain exact.'
);
g13_same(false, $GLOBALS['g13_query_calls'][0]['result'], 'BUG-01 query failure result must remain unchanged.');
$GLOBALS['g13_query_queue'][] = array(61, 62);
$bug_03 = $invoke_private($command, 'check_bug_03');
g13_same('warn', $bug_03['signal'] ?? '', 'BUG-03 non-empty sample should retain warning result.');
g13_same(
	array(
		'post_type' => 'vms_event_plan',
		'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
		'posts_per_page' => 150,
		'fields' => 'ids',
		'meta_query' => array(
			'relation' => 'OR',
			array('key' => '_vms_start_time', 'compare' => 'NOT EXISTS'),
			array('key' => '_vms_end_time', 'compare' => 'NOT EXISTS'),
		),
	),
	$GLOBALS['g13_query_calls'][1]['args'],
	'BUG-03 WP_Query arguments must remain exact.'
);
g13_same(array(61, 62), $GLOBALS['g13_query_calls'][1]['result'], 'BUG-03 sampled IDs must remain unchanged.');
$GLOBALS['g13_query_queue'][] = array();
$can_01 = $invoke_private($command, 'check_can_01');
g13_same('pass', $can_01['signal'] ?? '', 'CAN-01 empty sample should retain pass result.');
g13_same(
	array(
		'post_type' => 'vms_event_plan',
		'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
		'posts_per_page' => 300,
		'fields' => 'ids',
		'meta_query' => array(array('key' => '_vms_event_plan_status', 'value' => 'cancelled', 'compare' => '=')),
	),
	$GLOBALS['g13_query_calls'][2]['args'],
	'CAN-01 WP_Query arguments must remain exact.'
);
g13_same(array(), $GLOBALS['g13_query_calls'][2]['result'], 'CAN-01 empty query result must remain unchanged.');

// Feedback lists and duplicate guards retain caller limits, exact identity keys, and fail-closed branches.
g13_reset_runtime();
$response_posts = array(new WP_Post(71, BVMGR_CPT_FEEDBACK_RESPONSE, 'private'), new WP_Post(72, BVMGR_CPT_FEEDBACK_RESPONSE, 'publish'));
$GLOBALS['g13_query_queue'][] = $response_posts;
g13_same($response_posts, vms_feedback_get_responses(44, 25), 'Filtered feedback list should return get_posts() results unchanged.');
g13_same(
	array(
		'post_type' => BVMGR_CPT_FEEDBACK_RESPONSE,
		'post_status' => array('private', 'publish'),
		'posts_per_page' => 25,
		'orderby' => 'date',
		'order' => 'DESC',
		'no_found_rows' => true,
		'meta_query' => array(array('key' => '_vms_feedback_event_plan_id', 'value' => 44, 'compare' => '=')),
	),
	$GLOBALS['g13_query_calls'][0]['args'],
	'Filtered feedback list arguments must remain exact.'
);
g13_same($response_posts, $GLOBALS['g13_query_calls'][0]['result'], 'Feedback list query result must remain unchanged.');
g13_same(0, vms_feedback_existing_response_by_meta(0, 'submission_uid_hash', 'x'), 'Invalid feedback identity should fail closed.');
$query_count = count($GLOBALS['g13_query_calls']);
g13_same(0, vms_feedback_existing_response_by_meta(44, '', 'x'), 'Blank feedback meta key should fail closed.');
g13_same($query_count, count($GLOBALS['g13_query_calls']), 'Invalid feedback identities must not query.');
$GLOBALS['g13_query_queue'][] = array(88);
g13_same(88, vms_feedback_existing_response_by_meta(44, 'submission_uid_hash', 'token'), 'Exact feedback token lookup should return its first result.');
g13_same(
	array(
		'post_type' => BVMGR_CPT_FEEDBACK_RESPONSE,
		'post_status' => array('private', 'publish'),
		'posts_per_page' => 1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'meta_query' => array(
			'relation' => 'AND',
			array('key' => '_vms_feedback_event_plan_id', 'value' => 44, 'compare' => '='),
			array('key' => '_vms_feedback_submission_uid_hash', 'value' => 'token', 'compare' => '='),
		),
	),
	$GLOBALS['g13_query_calls'][1]['args'],
	'Exact feedback-token lookup arguments must remain exact.'
);
g13_same(array(88), $GLOBALS['g13_query_calls'][1]['result'], 'Exact feedback-token result must remain unchanged.');
$recent_started = time();
$GLOBALS['g13_query_queue'][] = array(99);
g13_same(99, vms_feedback_existing_recent_duplicate(44, 'fingerprint', 'request-hash', 7200), 'Recent feedback duplicate lookup should return its first result.');
$recent_finished = time();
$recent_args = $GLOBALS['g13_query_calls'][2]['args'];
$recent_threshold = (string) ($recent_args['meta_query'][3]['value'] ?? '');
g13_assert(
	in_array($recent_threshold, array(gmdate('Y-m-d H:i:s', $recent_started - 7200), gmdate('Y-m-d H:i:s', $recent_finished - 7200)), true),
	'Recent feedback threshold must remain inside the requested UTC window.'
);
g13_same(
	array(
		'post_type' => BVMGR_CPT_FEEDBACK_RESPONSE,
		'post_status' => array('private', 'publish'),
		'posts_per_page' => 1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'meta_query' => array(
			'relation' => 'AND',
			array('key' => '_vms_feedback_event_plan_id', 'value' => 44, 'compare' => '='),
			array('key' => '_vms_feedback_duplicate_fingerprint', 'value' => 'fingerprint', 'compare' => '='),
			array('key' => '_vms_feedback_request_hash', 'value' => 'request-hash', 'compare' => '='),
			array('key' => '_vms_feedback_submitted_at_gmt', 'value' => $recent_threshold, 'compare' => '>=', 'type' => 'DATETIME'),
		),
	),
	$recent_args,
	'Recent feedback duplicate arguments must remain exact.'
);
g13_same(array(99), $GLOBALS['g13_query_calls'][2]['result'], 'Recent feedback duplicate result must remain unchanged.');
$GLOBALS['g13_query_queue'][] = false;
g13_same(0, vms_feedback_existing_recent_duplicate(44, 'other-fingerprint', 'other-hash', 60), 'Non-array recent-feedback query failures should fail closed.');

// Ticket-sales resolution retains a complete exact-pair lookup and request-local cache behavior.
g13_reset_runtime();
$attendee_cache = array();
g13_same(array(), vms_ticket_sales_resolver_attendee_ids_for_order_item(0, 5, $attendee_cache), 'Invalid attendee identity should fail closed.');
g13_same(array(), $GLOBALS['g13_query_calls'], 'Invalid attendee identity must not query.');
$GLOBALS['g13_query_queue'][] = array(5, 5, 0, 6);
g13_same(array(5, 6), vms_ticket_sales_resolver_attendee_ids_for_order_item(70, 80, $attendee_cache), 'Attendee lookup should preserve normalized complete results.');
g13_same(
	array(
		'post_type' => 'tribe_wooticket',
		'post_status' => 'any',
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'orderby' => 'ID',
		'order' => 'ASC',
		'meta_query' => array(
			'relation' => 'AND',
			array('key' => '_tribe_wooticket_order', 'value' => '70'),
			array('key' => '_tribe_wooticket_order_item', 'value' => '80'),
		),
	),
	$GLOBALS['g13_query_calls'][0]['args'],
	'Attendee get_posts() arguments must remain exact.'
);
g13_same(array(5, 5, 0, 6), $GLOBALS['g13_query_calls'][0]['result'], 'Attendee query result must remain unchanged before normalization.');
$attendee_call_count = count($GLOBALS['g13_query_calls']);
g13_same(array(5, 6), vms_ticket_sales_resolver_attendee_ids_for_order_item(70, 80, $attendee_cache), 'Attendee request-local cache should preserve normalized results.');
g13_same($attendee_call_count, count($GLOBALS['g13_query_calls']), 'Attendee request-local cache hit must not repeat get_posts().');
$GLOBALS['g13_query_queue'][] = false;
g13_same(array(), vms_ticket_sales_resolver_attendee_ids_for_order_item(71, 81, $attendee_cache), 'Non-array attendee query failure should fail closed.');

fwrite(STDOUT, "G13 core-services meta-query remediation: PASS (Wave 4 rows 18 -> projected 0; meta_key -3, meta_query -13, meta_value -2)\n");
