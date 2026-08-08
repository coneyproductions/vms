<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class WP_Post
{
	public int $ID;
	public string $post_type;
	public string $post_status;
	public string $post_modified_gmt;
	public string $post_modified;
	public string $post_date;

	public function __construct(int $id, string $post_type = 'vms_event_plan', string $post_status = 'publish', string $modified = '2026-08-01 12:00:00')
	{
		$this->ID = $id;
		$this->post_type = $post_type;
		$this->post_status = $post_status;
		$this->post_modified_gmt = $modified;
		$this->post_modified = $modified;
		$this->post_date = $modified;
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

$GLOBALS['g13_tail_get_posts_calls'] = array();
$GLOBALS['g13_tail_get_posts_queue'] = array();
$GLOBALS['g13_tail_meta'] = array();
$GLOBALS['g13_tail_posts'] = array();
$GLOBALS['g13_tail_statuses'] = array();
$GLOBALS['g13_tail_titles'] = array();
$GLOBALS['g13_tail_excluded'] = array();
$GLOBALS['g13_tail_reset_postdata'] = 0;
$GLOBALS['g13_tail_nonce_valid'] = true;
$GLOBALS['g13_tail_rating_fields'] = array();
$GLOBALS['g13_tail_rating_plan_id'] = 0;
$GLOBALS['g13_tail_attended'] = false;
$GLOBALS['g13_tail_vendor_post_type'] = 'vms_vendor_application';
$GLOBALS['g13_tail_post_type_exists'] = true;

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

function sanitize_text_field($value): string
{
	return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
}

function sanitize_email($value): string
{
	return is_scalar($value) ? strtolower(trim((string) $value)) : '';
}

function wp_unslash($value)
{
	return $value;
}

function wp_verify_nonce(string $nonce, string $action): bool
{
	unset($nonce, $action);
	return (bool) $GLOBALS['g13_tail_nonce_valid'];
}

function get_posts(array $args): array
{
	$GLOBALS['g13_tail_get_posts_calls'][] = $args;
	return $GLOBALS['g13_tail_get_posts_queue'] === array() ? array() : array_shift($GLOBALS['g13_tail_get_posts_queue']);
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	return $GLOBALS['g13_tail_meta'][$post_id][$key] ?? ($single ? '' : array());
}

function get_post(int $post_id)
{
	return $GLOBALS['g13_tail_posts'][$post_id] ?? null;
}

function get_post_status(int $post_id): string
{
	$post = get_post($post_id);
	return $post instanceof WP_Post ? $post->post_status : '';
}

function get_the_title(int $post_id): string
{
	return $GLOBALS['g13_tail_titles'][$post_id] ?? ('Post ' . $post_id);
}

function vms_meta_key(string $scope, string $field): string
{
	unset($scope);
	$keys = array(
		'venue_id' => '_vms_venue_id',
		'date' => '_vms_event_date',
		'status' => '_vms_event_plan_status',
		'ticketing_config_v2' => '_vms_ticketing_config_v2',
		'calendar_unpublished_suppress' => '_vms_calendar_unpublished_suppress',
	);
	return $keys[$field] ?? ('_vms_' . $field);
}

function vms_event_plan_get_status(int $post_id, string $context): string
{
	unset($context);
	return $GLOBALS['g13_tail_statuses'][$post_id] ?? 'draft';
}

function vms_event_plan_should_include(int $post_id, string $context, array $flags): bool
{
	unset($context, $flags);
	return !in_array($post_id, $GLOBALS['g13_tail_excluded'], true);
}

function vms_sch_is_valid_ymd(string $ymd): bool
{
	return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) === 1;
}

function vms_sch_is_date_in_window(string $ymd, string $start_ymd, string $end_ymd): bool
{
	return $ymd >= $start_ymd && $ymd <= $end_ymd;
}

function vms_get_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function wp_reset_postdata(): void
{
	$GLOBALS['g13_tail_reset_postdata']++;
}

function vms_rating_submitted_text_field(string $key): string
{
	return (string) ($GLOBALS['g13_tail_rating_fields'][$key] ?? '');
}

function vms_rating_submitted_email(string $key): string
{
	return sanitize_email($GLOBALS['g13_tail_rating_fields'][$key] ?? '');
}

function vms_rating_submitted_value(string $key): int
{
	return (int) ($GLOBALS['g13_tail_rating_fields'][$key] ?? 0);
}

function vms_rating_submitted_comment(): string
{
	return (string) ($GLOBALS['g13_tail_rating_fields']['vms_rating_comment'] ?? '');
}

function vms_get_event_plan_for_tec_event(int $event_id): int
{
	unset($event_id);
	return (int) $GLOBALS['g13_tail_rating_plan_id'];
}

function vms_has_attended_event(int $event_id, string $email): bool
{
	unset($event_id, $email);
	return (bool) $GLOBALS['g13_tail_attended'];
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function vms_approvals_queue_default_vendor_post_type(): string
{
	return (string) $GLOBALS['g13_tail_vendor_post_type'];
}

function post_type_exists(string $post_type): bool
{
	unset($post_type);
	return (bool) $GLOBALS['g13_tail_post_type_exists'];
}

function vms_vendor_app_meta_key(string $field): string
{
	return $field === 'confirmation_state' ? '_vms_app_confirmation_state' : '';
}

function get_option(string $key, $default = false)
{
	$options = array('date_format' => 'Y-m-d', 'time_format' => 'H:i');
	return $options[$key] ?? $default;
}

function mysql2date(string $format, string $date, bool $translate = true): string
{
	unset($format, $translate);
	return $date;
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function g13_tail_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g13_tail_same($expected, $actual, string $message): void
{
	g13_tail_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function g13_tail_contains(string $needle, string $haystack, string $message): void
{
	g13_tail_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function g13_tail_extract_function(string $source, string $name): string
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

function g13_tail_extract_assignment_call(string $source, string $marker): string
{
	$start = strpos($source, $marker);
	$open = $start === false ? false : strpos($source, '(', $start);
	if ($start === false || $open === false) {
		throw new RuntimeException('Unable to find assignment call: ' . $marker);
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
	throw new RuntimeException('Unable to parse assignment call: ' . $marker);
}

function g13_tail_strip_annotations(string $source): string
{
	return (string) preg_replace(
		'/^[ \t]*\/\/ phpcs:ignore WordPress\.DB\.SlowDBQuery\.slow_db_query_meta_query -- [^\r\n]*(?:\r?\n|$)/m',
		'',
		$source
	);
}

function g13_tail_assert_projection(string $expected_hash, string $source, string $label): void
{
	g13_tail_same($expected_hash, hash('sha256', g13_tail_strip_annotations($source)), $label . ' projection outside annotations changed.');
}

function g13_tail_validate_directives(string $scope): void
{
	if (preg_match('/phpcs:(?:disable|enable|ignoreFile)/', $scope) === 1) {
		throw new RuntimeException('Broad PHPCS directive found.');
	}
	foreach (preg_split('/\R/', $scope) ?: array() as $line) {
		if (strpos($line, 'phpcs:') === false) {
			continue;
		}
		if (!preg_match('/phpcs:ignore ([^\s]+) -- (.+)$/', $line, $match)) {
			throw new RuntimeException('Directive must be a justified one-line ignore: ' . $line);
		}
		if ($match[1] !== 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query') {
			throw new RuntimeException('Broad, mixed, or unrelated directive found: ' . $match[1]);
		}
		if (strlen(trim($match[2])) < 32) {
			throw new RuntimeException('Directive reason is not operation-specific.');
		}
	}
}

function g13_tail_reset(): void
{
	$GLOBALS['g13_tail_get_posts_calls'] = array();
	$GLOBALS['g13_tail_get_posts_queue'] = array();
	$GLOBALS['g13_tail_meta'] = array();
	$GLOBALS['g13_tail_posts'] = array();
	$GLOBALS['g13_tail_statuses'] = array();
	$GLOBALS['g13_tail_titles'] = array();
	$GLOBALS['g13_tail_excluded'] = array();
	$GLOBALS['g13_tail_reset_postdata'] = 0;
	WP_Query::$calls = array();
	WP_Query::$queue = array();
}

$root = dirname(__DIR__);
$shadow_root = dirname(__DIR__, 3) . '/vms';
$relative_files = array(
	'includes/cpt/ratings.php',
	'includes/helpers.php',
	'includes/schedule/schedule.php',
	'includes/admin/integrity-calendar-reconcile.php',
	'includes/admin/approvals-review-queue.php',
);
$mirror_sources = array();
$shadow_sources = array();
foreach ($relative_files as $relative_file) {
	$mirror_sources[$relative_file] = (string) file_get_contents($root . '/' . $relative_file);
	$shadow_sources[$relative_file] = (string) file_get_contents($shadow_root . '/' . $relative_file);
	g13_tail_assert($mirror_sources[$relative_file] !== '' && $shadow_sources[$relative_file] !== '', 'Mirror and shadow source should be readable: ' . $relative_file);
}

$mirror_baselines = array(
	'includes/cpt/ratings.php' => 'd451c6819955d81974b0b66f4660f21e1871590f0a13d5961ceff9fc8800cc7c',
	'includes/helpers.php' => '08f81dfebfb260c0e13477d0c4054c4b323becc6c1c575761df44203553dc108',
	'includes/schedule/schedule.php' => '4c29b99c1754c1581d48c73ee94e8f8812bf1aa356ba700074dd6e2b627b7a89',
	'includes/admin/integrity-calendar-reconcile.php' => 'e14ef230c1d6ab3664319343aacdcf7f639f6fa3a085f1d2ce38a26ea96e3438',
	'includes/admin/approvals-review-queue.php' => '30c2d9dc6cfbc945caef6bdbdfd55c0535864b12d85a7599b7712033b7347517',
);
$shadow_baselines = array(
	'includes/cpt/ratings.php' => '52205e38858b59f3e8e0bf71871c794d411d92420c8bb5fd00c9155f1bca23fa',
	'includes/helpers.php' => '06af21875cc11fd32854e675feadaa85c4954563ac295a08790da0624b1d56c4',
	'includes/schedule/schedule.php' => '4c29b99c1754c1581d48c73ee94e8f8812bf1aa356ba700074dd6e2b627b7a89',
	'includes/admin/integrity-calendar-reconcile.php' => '83531df78810b490d0c9c29acf5ec11132fff4b107af084f695e1fd255c916c4',
	'includes/admin/approvals-review-queue.php' => 'f8578dbac46382485ada390f200bb2fe95890befacf7115cc468400a7b0a5d71',
);
foreach ($relative_files as $relative_file) {
	g13_tail_assert_projection($mirror_baselines[$relative_file], $mirror_sources[$relative_file], 'Mirror ' . $relative_file);
	g13_tail_assert_projection($shadow_baselines[$relative_file], $shadow_sources[$relative_file], 'Shadow ' . $relative_file);
}

g13_tail_same($mirror_sources['includes/schedule/schedule.php'], $shadow_sources['includes/schedule/schedule.php'], 'Schedule should retain whole-file mirror/shadow parity.');
foreach (array_diff($relative_files, array('includes/schedule/schedule.php')) as $divergent_file) {
	g13_tail_assert($mirror_sources[$divergent_file] !== $shadow_sources[$divergent_file], 'Intentional whole-file divergence should remain: ' . $divergent_file);
}

$owned_function_map = array(
	'includes/cpt/ratings.php' => array('vms_get_band_rating_summary', 'vms_handle_rating_submission'),
	'includes/helpers.php' => array('vms_resolve_event_plan_for_tec_event', 'vms_get_ticket_product_ids_for_event', 'vms_get_event_titles_by_date', 'vms_get_comp_packages_for_venue'),
	'includes/schedule/schedule.php' => array('vms_sch_get_plans_by_date', 'vms_sch_get_plans_by_date_all'),
	'includes/admin/approvals-review-queue.php' => array('vms_approvals_queue_vendor_summary'),
);
$owned_chunks = array();
foreach ($owned_function_map as $relative_file => $functions) {
	foreach ($functions as $function) {
		$chunk = g13_tail_extract_function($mirror_sources[$relative_file], $function);
		g13_tail_same($chunk, g13_tail_extract_function($shadow_sources[$relative_file], $function), 'Owned function mirror/shadow parity changed: ' . $function);
		$owned_chunks[] = $chunk;
	}
}
$integrity_call = g13_tail_extract_assignment_call($mirror_sources['includes/admin/integrity-calendar-reconcile.php'], '$suppressed_ids = get_posts(array(');
g13_tail_same(
	$integrity_call,
	g13_tail_extract_assignment_call($shadow_sources['includes/admin/integrity-calendar-reconcile.php'], '$suppressed_ids = get_posts(array('),
	'Integrity diagnostic query occurrence should retain mirror/shadow parity.'
);
$owned_chunks[] = $integrity_call;
$owned_source = implode("\n", $owned_chunks);

$artifact_inventory = array(
	'includes/cpt/ratings.php' => 2,
	'includes/helpers.php' => 4,
	'includes/schedule/schedule.php' => 4,
	'includes/admin/integrity-calendar-reconcile.php' => 1,
	'includes/admin/approvals-review-queue.php' => 1,
);
$rule = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query';
g13_tail_same(12, array_sum($artifact_inventory), 'Corrected authoritative tail inventory should contain exactly 12 DB rows.');
foreach ($artifact_inventory as $relative_file => $expected) {
	g13_tail_same($expected, substr_count($mirror_sources[$relative_file], $rule), 'Mirror artifact-derived inventory changed: ' . $relative_file);
	g13_tail_same($expected, substr_count($shadow_sources[$relative_file], $rule), 'Shadow artifact-derived inventory changed: ' . $relative_file);
}
g13_tail_same(12, substr_count($owned_source, $rule), 'Owned directive inventory should contain exactly 12 rows.');
g13_tail_validate_directives($owned_source);
preg_match_all('/^[ \t]*\/\/ phpcs:ignore ([^\s]+) -- [^\r\n]+\R([^\r\n]+)/m', $owned_source, $directive_matches, PREG_SET_ORDER);
g13_tail_same(12, count($directive_matches), 'Each finding should have exactly one physical line-local directive.');
foreach ($directive_matches as $directive_match) {
	g13_tail_same($rule, $directive_match[1], 'Directive code changed.');
	g13_tail_contains("'meta_query'", $directive_match[2], 'Directive must be immediately adjacent to its meta_query token.');
}
foreach (array(
	$owned_source . "\n// phpcs:disable WordPress.DB",
	$owned_source . "\n// phpcs:ignore WordPress.DB -- broad family",
	$owned_source . "\n// phpcs:ignore WordPress.DB.SlowDBQuery -- broad category",
	$owned_source . "\n// phpcs:ignore {$rule},WordPress.Security.EscapeOutput.OutputNotEscaped -- mixed list",
) as $negative_scope) {
	$rejected = false;
	try {
		g13_tail_validate_directives($negative_scope);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	g13_tail_assert($rejected, 'Broad family/category/mixed-list negative control should be rejected.');
}

$mutated_schedule = str_replace("'posts_per_page' => -1", "'posts_per_page' => 1", $mirror_sources['includes/schedule/schedule.php'], $mutation_count);
g13_tail_assert($mutation_count > 0, 'Runtime mutation negative-control anchor should exist.');
$mutation_rejected = false;
try {
	g13_tail_assert_projection($mirror_baselines['includes/schedule/schedule.php'], $mutated_schedule, 'Mutated schedule');
} catch (RuntimeException $exception) {
	$mutation_rejected = true;
}
g13_tail_assert($mutation_rejected, 'Annotation-stripped projection guard should reject runtime mutation.');

$all_runtime_source = implode("\n", $mirror_sources);
g13_tail_contains('error_log(', $mirror_sources['includes/helpers.php'], 'Neighboring helpers error_log finding should remain present.');
g13_tail_contains('error_log(', $mirror_sources['includes/admin/approvals-review-queue.php'], 'Neighboring approvals error_log finding should remain present.');
g13_tail_contains("date('Y-m-d', strtotime(\$start))", $mirror_sources['includes/helpers.php'], 'First neighboring date finding should remain present.');
g13_tail_contains("date('Y-m-d', strtotime(\$end))", $mirror_sources['includes/helpers.php'], 'Second neighboring date finding should remain present.');
g13_tail_contains("date('w', strtotime(\$ymd))", $mirror_sources['includes/helpers.php'], 'Third neighboring date finding should remain present.');
g13_tail_contains("echo '<form method=\"post\" action=\"' . \$action", $mirror_sources['includes/helpers.php'], 'Neighboring helpers output finding should remain present.');
g13_tail_same(0, substr_count($all_runtime_source, 'WordPress.PHP.DevelopmentFunctions.error_log_error_log'), 'Neighboring error_log findings must remain unsuppressed.');
g13_tail_same(0, substr_count($all_runtime_source, 'WordPress.DateTime.RestrictedFunctions.date_date'), 'Neighboring date findings must remain unsuppressed.');
g13_tail_same(0, substr_count($all_runtime_source, 'WordPress.Security.EscapeOutput.OutputNotEscaped'), 'Neighboring output finding must remain unsuppressed.');

foreach ($owned_function_map as $relative_file => $functions) {
	foreach ($functions as $function) {
		eval(g13_tail_extract_function($mirror_sources[$relative_file], $function));
	}
}

// Complete ratings aggregate semantics: invalid/empty inputs, filters, IDs, and aggregate results.
g13_tail_reset();
g13_tail_same(array('average' => null, 'count' => 0), vms_get_band_rating_summary(0), 'Invalid band summary result changed.');
g13_tail_same(0, count($GLOBALS['g13_tail_get_posts_calls']), 'Invalid band should not query ratings.');
$GLOBALS['g13_tail_get_posts_queue'] = array(array());
g13_tail_same(array('average' => null, 'count' => 0), vms_get_band_rating_summary(44), 'Empty rating summary result changed.');
$empty_rating_args = $GLOBALS['g13_tail_get_posts_calls'][0];
g13_tail_same(-1, $empty_rating_args['posts_per_page'], 'Rating aggregate must remain complete rather than count-capped.');
g13_tail_same('ids', $empty_rating_args['fields'], 'Rating aggregate result shape changed.');
g13_tail_same(2, count($empty_rating_args['meta_query']), 'Verified-only rating filters changed.');
g13_tail_same(array('_vms_band_id', '_vms_verified_attendance'), array_column($empty_rating_args['meta_query'], 'key'), 'Verified rating keys changed.');

g13_tail_reset();
$GLOBALS['g13_tail_get_posts_queue'] = array(array(11, 12, 13));
$GLOBALS['g13_tail_meta'] = array(
	11 => array('_vms_rating_value' => 5),
	12 => array('_vms_rating_value' => 3),
	13 => array('_vms_rating_value' => 0),
);
g13_tail_same(array('average' => 4.0, 'count' => 2), vms_get_band_rating_summary(44, false), 'Rating aggregate average/count changed.');
$rating_args = $GLOBALS['g13_tail_get_posts_calls'][0];
g13_tail_same(array(array('key' => '_vms_band_id', 'value' => 44)), $rating_args['meta_query'], 'Unverified rating filter changed.');

// The full rating handler retains nonce failure, exact one-row duplicate lookup, and empty-result continuation.
g13_tail_reset();
$_POST = array('vms_rating_nonce' => 'nonce');
$GLOBALS['g13_tail_rating_fields'] = array(
	'vms_reviewer_name' => 'Reviewer',
	'vms_reviewer_email' => 'reviewer@example.test',
	'vms_rating_value' => 4,
	'vms_rating_comment' => 'Good show',
);
$GLOBALS['g13_tail_nonce_valid'] = false;
$nonce_failure = vms_handle_rating_submission(700, 77);
g13_tail_same(false, $nonce_failure['success'], 'Invalid rating nonce should still fail.');
g13_tail_same(0, count($GLOBALS['g13_tail_get_posts_calls']), 'Invalid rating nonce should not query duplicates.');

$GLOBALS['g13_tail_nonce_valid'] = true;
$GLOBALS['g13_tail_rating_plan_id'] = 701;
$GLOBALS['g13_tail_meta'] = array(
	701 => array('_vms_band_vendor_id' => 77, '_vms_event_date' => '', '_vms_start_time' => ''),
);
$GLOBALS['g13_tail_get_posts_queue'] = array(array(901));
$duplicate_failure = vms_handle_rating_submission(700, 77);
g13_tail_same(false, $duplicate_failure['success'], 'Existing duplicate rating should still fail.');
g13_tail_contains('already submitted', $duplicate_failure['message'], 'Duplicate rating failure message changed.');
$duplicate_args = $GLOBALS['g13_tail_get_posts_calls'][0];
g13_tail_same(1, $duplicate_args['posts_per_page'], 'Duplicate lookup must remain capped at one.');
g13_tail_same('any', $duplicate_args['post_status'], 'Duplicate lookup status scope changed.');
g13_tail_same('AND', $duplicate_args['meta_query']['relation'], 'Duplicate lookup relation changed.');
g13_tail_same(
	array('_vms_band_id', '_vms_event_id', '_vms_reviewer_email'),
	array_column(array_slice($duplicate_args['meta_query'], 1), 'key'),
	'Duplicate tuple keys changed.'
);
g13_tail_same(array(77, 700, 'reviewer@example.test'), array_column(array_slice($duplicate_args['meta_query'], 1), 'value'), 'Duplicate tuple values changed.');

g13_tail_reset();
$GLOBALS['g13_tail_nonce_valid'] = true;
$GLOBALS['g13_tail_rating_plan_id'] = 701;
$GLOBALS['g13_tail_rating_fields'] = array(
	'vms_reviewer_name' => 'Reviewer',
	'vms_reviewer_email' => 'reviewer@example.test',
	'vms_rating_value' => 4,
	'vms_rating_comment' => 'Good show',
);
$GLOBALS['g13_tail_meta'] = array(
	701 => array('_vms_band_vendor_id' => 77, '_vms_event_date' => '', '_vms_start_time' => ''),
);
$GLOBALS['g13_tail_get_posts_queue'] = array(array());
$GLOBALS['g13_tail_attended'] = false;
$no_duplicate = vms_handle_rating_submission(700, 77);
g13_tail_same(false, $no_duplicate['success'], 'Empty duplicate result should continue to attendance validation.');
g13_tail_contains("couldn't find a ticket", $no_duplicate['message'], 'Empty duplicate-result continuation changed.');

// Linked Event Plan lifecycle resolution retains complete candidate enumeration and deterministic ranking.
g13_tail_reset();
g13_tail_same(0, vms_resolve_event_plan_for_tec_event(0), 'Invalid TEC resolver result changed.');
g13_tail_same(0, count($GLOBALS['g13_tail_get_posts_calls']), 'Invalid TEC ID should not query Event Plans.');
$GLOBALS['g13_tail_get_posts_queue'] = array(array());
g13_tail_same(0, vms_resolve_event_plan_for_tec_event(88), 'Empty TEC resolver result changed.');
$resolver_args = $GLOBALS['g13_tail_get_posts_calls'][0];
g13_tail_same(-1, $resolver_args['posts_per_page'], 'TEC resolver must inspect all linked lifecycle candidates.');
g13_tail_same('ids', $resolver_args['fields'], 'TEC resolver result shape changed.');
g13_tail_same(array('key' => '_vms_tec_event_id', 'value' => '88', 'compare' => '='), $resolver_args['meta_query'][0], 'TEC resolver linkage filter changed.');

g13_tail_reset();
$GLOBALS['g13_tail_get_posts_queue'] = array(array(101, 102, 103));
$GLOBALS['g13_tail_posts'] = array(
	101 => new WP_Post(101, 'vms_event_plan', 'publish', '2026-08-03 12:00:00'),
	102 => new WP_Post(102, 'vms_event_plan', 'publish', '2026-08-01 12:00:00'),
	103 => new WP_Post(103, 'vms_event_plan', 'private', '2026-08-04 12:00:00'),
);
$GLOBALS['g13_tail_statuses'] = array(101 => 'cancelled', 102 => 'published', 103 => 'published');
$GLOBALS['g13_tail_meta'] = array(
	101 => array('_vms_ticketing_config_v2' => array('enabled' => true)),
	102 => array('_vms_ticketing_config_v2' => array()),
	103 => array('_vms_ticketing_config_v2' => array('enabled' => true)),
);
g13_tail_same(102, vms_resolve_event_plan_for_tec_event(88), 'TEC resolver lifecycle ranking changed.');

// Ticket-product lookup retains invalid/empty behavior, complete exact linkage, and integer results.
g13_tail_reset();
g13_tail_same(array(), vms_get_ticket_product_ids_for_event(0), 'Invalid ticket-event result changed.');
g13_tail_same(0, count($GLOBALS['g13_tail_get_posts_calls']), 'Invalid ticket-event ID should not query products.');
$GLOBALS['g13_tail_get_posts_queue'] = array(array('401', 402));
g13_tail_same(array(401, 402), vms_get_ticket_product_ids_for_event(90), 'Ticket product ID normalization changed.');
$ticket_args = $GLOBALS['g13_tail_get_posts_calls'][0];
g13_tail_same(-1, $ticket_args['posts_per_page'], 'Ticket lookup must return every linked product.');
g13_tail_same(array('key' => '_tribe_wooticket_for_event', 'value' => 90), $ticket_args['meta_query'][0], 'Ticket product linkage filter changed.');

// Legacy TEC title mapping executes WP_Query, preserves the whole dated-event scan, then filters in PHP.
g13_tail_reset();
g13_tail_same(array(), vms_get_event_titles_by_date(array()), 'Empty active-date title map changed.');
g13_tail_same(0, count(WP_Query::$calls), 'Empty active dates should not execute WP_Query.');
WP_Query::$queue = array(array(501, 502, 503));
$GLOBALS['g13_tail_meta'] = array(
	501 => array('_EventStartDate' => '2026-09-10 19:00:00'),
	502 => array('_EventStartDate' => '2026-09-11 19:00:00'),
	503 => array('_EventStartDate' => 'This is not a date'),
);
$GLOBALS['g13_tail_titles'] = array(501 => 'Included Event', 502 => 'Outside Event');
g13_tail_same(array('2026-09-10' => 'Included Event'), vms_get_event_titles_by_date(array(' 2026-09-10 ')), 'TEC title PHP date filtering changed.');
$title_args = WP_Query::$calls[0];
g13_tail_same(-1, $title_args['posts_per_page'], 'Legacy title helper must retain its whole dated-event scan.');
g13_tail_same('ids', $title_args['fields'], 'Legacy title query result shape changed.');
g13_tail_same(array('key' => '_EventStartDate', 'compare' => 'EXISTS'), $title_args['meta_query'][0], 'Legacy title dated-event filter changed.');
g13_tail_same(1, $GLOBALS['g13_tail_reset_postdata'], 'Legacy title query should retain postdata reset.');

// Comp-package selector retains complete venue/global scopes and direct get_posts results.
g13_tail_reset();
$package = new WP_Post(601, 'vms_comp_package', 'publish');
$GLOBALS['g13_tail_get_posts_queue'] = array(array($package), array());
g13_tail_same(array($package), vms_get_comp_packages_for_venue(12, true), 'Venue/global package results changed.');
$package_global_args = $GLOBALS['g13_tail_get_posts_calls'][0];
g13_tail_same(-1, $package_global_args['posts_per_page'], 'Comp-package choices must remain complete.');
g13_tail_same('OR', $package_global_args['meta_query']['relation'], 'Venue/global package relation changed.');
g13_tail_same(array(12, 0), array_column(array_slice($package_global_args['meta_query'], 1, 2), 'value'), 'Venue/global package values changed.');
g13_tail_same(array(), vms_get_comp_packages_for_venue(12, false), 'Empty venue-only package result changed.');
$package_venue_args = $GLOBALS['g13_tail_get_posts_calls'][1];
g13_tail_same(array(array('key' => '_vms_venue_id', 'value' => 12, 'compare' => '=', 'type' => 'NUMERIC')), $package_venue_args['meta_query'], 'Venue-only package scope changed.');

// Single-venue schedule query retains bounded primary args/results and the unbounded historical fallback with PHP revalidation.
g13_tail_reset();
g13_tail_same(array(), vms_sch_get_plans_by_date(0, '2026-09-01', '2026-09-30'), 'Invalid venue schedule result changed.');
g13_tail_same(0, count($GLOBALS['g13_tail_get_posts_calls']), 'Invalid venue should not query schedule plans.');
$GLOBALS['g13_tail_get_posts_queue'] = array(array(701, 702, 703));
$GLOBALS['g13_tail_meta'] = array(
	701 => array('_vms_event_date' => '2026-09-05', '_vms_venue_id' => 12),
	702 => array('_vms_event_date' => '2026-10-01', '_vms_venue_id' => 12),
	703 => array('_vms_event_date' => '2026-09-06', '_vms_venue_id' => 12),
);
$GLOBALS['g13_tail_posts'] = array(
	701 => new WP_Post(701, 'vms_event_plan', 'publish'),
	702 => new WP_Post(702, 'vms_event_plan', 'draft'),
	703 => new WP_Post(703, 'vms_event_plan', 'publish'),
);
$GLOBALS['g13_tail_statuses'] = array(701 => 'published', 702 => 'draft', 703 => 'published');
$GLOBALS['g13_tail_excluded'] = array(703);
$single_map = vms_sch_get_plans_by_date(12, '2026-09-01', '2026-09-30', false, array('context' => 'schedule_admin'));
g13_tail_same(array(701), array_column($single_map['2026-09-05'], 'plan_id'), 'Single-venue schedule PHP filtering/result changed.');
$single_args = $GLOBALS['g13_tail_get_posts_calls'][0];
g13_tail_same(-1, $single_args['posts_per_page'], 'Single-venue schedule completeness changed.');
g13_tail_same('AND', $single_args['meta_query']['relation'], 'Single-venue primary relation changed.');
g13_tail_same(array(12, array('2026-09-01', '2026-09-30')), array_column(array_slice($single_args['meta_query'], 1), 'value'), 'Single-venue primary bounds changed.');

g13_tail_reset();
$GLOBALS['g13_tail_get_posts_queue'] = array(array(), array(704, 705));
$GLOBALS['g13_tail_meta'] = array(
	704 => array('_vms_event_date' => '2026-09-08', '_vms_venue_id' => 12),
	705 => array('_vms_event_date' => '2026-10-08', '_vms_venue_id' => 12),
);
$GLOBALS['g13_tail_posts'] = array(
	704 => new WP_Post(704, 'vms_event_plan', 'publish'),
	705 => new WP_Post(705, 'vms_event_plan', 'publish'),
);
$GLOBALS['g13_tail_statuses'] = array(704 => 'published', 705 => 'published');
$single_fallback_map = vms_sch_get_plans_by_date(12, '2026-09-01', '2026-09-30');
g13_tail_same(array(704), array_column($single_fallback_map['2026-09-08'], 'plan_id'), 'Single-venue fallback PHP window filtering changed.');
g13_tail_same(2, count($GLOBALS['g13_tail_get_posts_calls']), 'Empty primary schedule query should invoke one fallback.');
g13_tail_same(1, count($GLOBALS['g13_tail_get_posts_calls'][1]['meta_query']), 'Single-venue fallback must remove only the date constraint.');
g13_tail_same(12, $GLOBALS['g13_tail_get_posts_calls'][1]['meta_query'][0]['value'], 'Single-venue fallback venue bound changed.');
g13_tail_same(-1, $GLOBALS['g13_tail_get_posts_calls'][1]['posts_per_page'], 'Single-venue fallback remains unbounded across that venue history.');

// Multi-venue schedule query retains finite venue/date primary bounds and the selected-venues historical fallback.
g13_tail_reset();
g13_tail_same(array(), vms_sch_get_plans_by_date_all(array(), '2026-09-01', '2026-09-30'), 'Empty multi-venue schedule result changed.');
g13_tail_same(0, count($GLOBALS['g13_tail_get_posts_calls']), 'Empty venue set should not query schedule plans.');
$GLOBALS['g13_tail_get_posts_queue'] = array(array(711));
$GLOBALS['g13_tail_meta'] = array(711 => array('_vms_event_date' => '2026-09-09', '_vms_venue_id' => 21));
$GLOBALS['g13_tail_posts'] = array(711 => new WP_Post(711, 'vms_event_plan', 'publish'));
$GLOBALS['g13_tail_statuses'] = array(711 => 'published');
$all_map = vms_sch_get_plans_by_date_all(array(21, 22), '2026-09-01', '2026-09-30');
g13_tail_same(array(array('plan_id' => 711, 'venue_id' => 21, 'plan_status' => 'published')), $all_map['2026-09-09'], 'Multi-venue schedule primary result changed.');
$all_args = $GLOBALS['g13_tail_get_posts_calls'][0];
g13_tail_same('IN', $all_args['meta_query'][0]['compare'], 'Multi-venue primary venue comparison changed.');
g13_tail_same(array(21, 22), $all_args['meta_query'][0]['value'], 'Multi-venue primary venue set changed.');
g13_tail_same(array('2026-09-01', '2026-09-30'), $all_args['meta_query'][1]['value'], 'Multi-venue primary date bounds changed.');

g13_tail_reset();
$GLOBALS['g13_tail_get_posts_queue'] = array(array(), array(712, 713));
$GLOBALS['g13_tail_meta'] = array(
	712 => array('_vms_event_date' => '2026-09-10', '_vms_venue_id' => 22),
	713 => array('_vms_event_date' => '2026-10-10', '_vms_venue_id' => 21),
);
$GLOBALS['g13_tail_posts'] = array(
	712 => new WP_Post(712, 'vms_event_plan', 'publish'),
	713 => new WP_Post(713, 'vms_event_plan', 'publish'),
);
$GLOBALS['g13_tail_statuses'] = array(712 => 'published', 713 => 'published');
$all_fallback_map = vms_sch_get_plans_by_date_all(array(21, 22), '2026-09-01', '2026-09-30');
g13_tail_same(array(712), array_column($all_fallback_map['2026-09-10'], 'plan_id'), 'Multi-venue fallback PHP window filtering changed.');
g13_tail_same(2, count($GLOBALS['g13_tail_get_posts_calls']), 'Empty multi-venue primary query should invoke one fallback.');
g13_tail_same(1, count($GLOBALS['g13_tail_get_posts_calls'][1]['meta_query']), 'Multi-venue fallback must remove only the date constraint.');
g13_tail_same(array(21, 22), $GLOBALS['g13_tail_get_posts_calls'][1]['meta_query'][0]['value'], 'Multi-venue fallback selected venue set changed.');

// Integrity diagnostic occurrence remains finite, exact-marker filtered, and returns get_posts results unchanged.
$integrity_runner = eval(
	'return static function (int $limit, string $k_sup): array {'
	. $integrity_call
	. ' return (array) $suppressed_ids; };'
);
g13_tail_assert(is_callable($integrity_runner), 'Integrity diagnostic query runner should be executable.');
g13_tail_reset();
$GLOBALS['g13_tail_get_posts_queue'] = array(array(801, 802), array());
g13_tail_same(array(801, 802), $integrity_runner(37, '_vms_calendar_unpublished_suppress'), 'Integrity diagnostic query result changed.');
$integrity_args = $GLOBALS['g13_tail_get_posts_calls'][0];
g13_tail_same(37, $integrity_args['posts_per_page'], 'Integrity diagnostic request limit changed.');
g13_tail_same(true, $integrity_args['no_found_rows'], 'Integrity diagnostic count-query behavior changed.');
g13_tail_same(array('key' => '_vms_calendar_unpublished_suppress', 'value' => '1', 'compare' => '='), $integrity_args['meta_query'][0], 'Integrity diagnostic marker filter changed.');
g13_tail_same(array(), $integrity_runner(0, '_vms_calendar_unpublished_suppress'), 'Empty integrity diagnostic result changed.');
g13_tail_same(500, $GLOBALS['g13_tail_get_posts_calls'][1]['posts_per_page'], 'Integrity diagnostic zero-limit fallback changed.');

// Approvals summary retains missing-post-type/empty branches, five-row cap, exact nested filters, and result mapping.
g13_tail_reset();
$GLOBALS['g13_tail_post_type_exists'] = false;
g13_tail_same(array(), vms_approvals_queue_vendor_summary(), 'Missing vendor post type should return an empty summary.');
g13_tail_same(0, count($GLOBALS['g13_tail_get_posts_calls']), 'Missing vendor post type should not query applications.');
$GLOBALS['g13_tail_post_type_exists'] = true;
$GLOBALS['g13_tail_get_posts_queue'] = array(array(), array(new WP_Post(901, 'vms_vendor_application', 'pending', '2026-08-06 10:00:00'), (object) array('ID' => 902)));
g13_tail_same(array(), vms_approvals_queue_vendor_summary(), 'Empty approvals query result changed.');
$approval_args = $GLOBALS['g13_tail_get_posts_calls'][0];
g13_tail_same(5, $approval_args['posts_per_page'], 'Approvals summary cap changed.');
g13_tail_same('AND', $approval_args['meta_query']['relation'], 'Approvals outer relation changed.');
g13_tail_same('_vms_app_status', $approval_args['meta_query'][0]['key'], 'Approvals pending key changed.');
g13_tail_same('pending', $approval_args['meta_query'][0]['value'], 'Approvals pending value changed.');
g13_tail_same('OR', $approval_args['meta_query'][1]['relation'], 'Approvals confirmation relation changed.');
g13_tail_same(array('confirmed', null), array($approval_args['meta_query'][1][0]['value'], $approval_args['meta_query'][1][1]['value'] ?? null), 'Approvals confirmed-or-legacy values changed.');
$GLOBALS['g13_tail_titles'][901] = '';
$GLOBALS['g13_tail_meta'][901] = array('_vms_app_email' => 'APPLICANT@EXAMPLE.TEST');
$approval_items = vms_approvals_queue_vendor_summary();
g13_tail_same(array(array('title' => 'Application #901', 'meta' => 'applicant@example.test - 2026-08-06 10:00:00')), $approval_items, 'Approvals summary result mapping changed.');

echo "G13 corrected tail repository SQL remediation checks passed.\n";
