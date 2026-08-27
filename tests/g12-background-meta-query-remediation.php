<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);

final class WP_Post
{
	public int $ID;
	public string $post_type;
	public string $post_status;

	public function __construct(int $id, string $post_type = 'vms_event_plan', string $post_status = 'publish')
	{
		$this->ID = $id;
		$this->post_type = $post_type;
		$this->post_status = $post_status;
	}
}

function g12_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g12_same($expected, $actual, string $message): void
{
	g12_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function g12_contains(string $needle, string $haystack, string $message): void
{
	g12_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function g12_extract_function(string $source, string $name): string
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

/**
 * @param array<string, string>                                              $sources Source contents keyed by owned source name.
 * @param string[]                                                          $allowed_codes Exact installed source codes permitted by this slice.
 * @param array<int, array{source: string, line: int, code: string}>         $expected_rows Expected DB annotation inventory.
 * @return string[]
 */
function g12_db_annotation_errors(array $sources, array $allowed_codes, array $expected_rows): array
{
	$errors = array();
	$actual_rows = array();

	foreach ($sources as $source_name => $source) {
		$lines = preg_split('/\R/', $source);
		if (!is_array($lines)) {
			$errors[] = 'Unable to split source into lines: ' . $source_name;
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
				$errors[] = sprintf('%s:%d has an unparseable DB-related PHPCS annotation.', $source_name, $index + 1);
				continue;
			}

			$verb = strtolower($matches[1]);
			$code = isset($matches[2]) ? trim($matches[2]) : '';
			if ($verb !== 'ignore') {
				$errors[] = sprintf('%s:%d uses forbidden phpcs:%s DB suppression.', $source_name, $index + 1, $verb);
				continue;
			}
			if (!in_array($code, $allowed_codes, true)) {
				$errors[] = sprintf('%s:%d uses a non-exact DB source code: %s', $source_name, $index + 1, $code);
				continue;
			}

			$actual_rows[] = array(
				'source' => $source_name,
				'line' => $index + 1,
				'code' => $code,
			);
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
		$errors[] = 'DB annotation occurrences differ from the exact expected inventory.';
	}

	return $errors;
}

/**
 * @param array<int, array{code: string, reason: string}> $owned_rows Owned annotations to remove.
 * @return array{source: string, removed: int}
 */
function g12_strip_owned_annotations(string $source, array $owned_rows, string $label): array
{
	$removed = 0;
	foreach ($owned_rows as $row) {
		$annotation = ' // phpcs:ignore ' . $row['code'] . ' -- ' . $row['reason'];
		g12_same(1, substr_count($source, $annotation), $label . ' must contain each owned annotation exactly once before projection.');
		$replacement_count = 0;
		$source = str_replace($annotation, '', $source, $replacement_count);
		g12_same(1, $replacement_count, $label . ' must strip each owned annotation exactly once.');
		$removed += $replacement_count;
	}

	return array('source' => $source, 'removed' => $removed);
}

/** @return int[] */
function g12_post_ids(array $posts): array
{
	return array_values(array_map(
		static function ($post): int {
			return $post instanceof WP_Post ? $post->ID : (int) $post;
		},
		$posts
	));
}

function add_action(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): void
{
	unset($hook_name, $callback, $priority, $accepted_args);
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

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('UTC');
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
	$timestamp = $timestamp ?? time();
	$timezone = $timezone ?? wp_timezone();
	return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format);
}

function bvmgr_meta_key(string $scope, string $field): string
{
	if ($scope === 'event_plan' && $field === 'import_key') {
		return '_vms_import_event_key';
	}

	return '_vms_' . $scope . '_' . $field;
}

function vms_email_followups_settings(): array
{
	return array(
		'enabled' => true,
		'auto_send_enabled' => true,
		'reminder_window_hours' => 48,
		'templates_enabled' => array('scheduled_notice' => true),
	);
}

function vms_email_followups_template_definitions(): array
{
	return array(
		'scheduled_notice' => array(
			'kind' => 'scheduled',
			'offset_days' => 0,
			'send_hour' => 0,
		),
	);
}

function vms_email_followups_event_context(int $event_plan_id): array
{
	return array(
		'valid' => $event_plan_id > 0,
		'event_date' => wp_date('Y-m-d', time(), wp_timezone()),
		'post_status' => 'publish',
		'plan_status' => 'ready',
	);
}

function vms_email_followups_was_sent(int $event_plan_id, string $email_key): bool
{
	unset($event_plan_id, $email_key);
	return false;
}

function get_post(int $post_id): ?WP_Post
{
	return $GLOBALS['g12_posts_by_id'][$post_id] ?? null;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['g12_post_meta'][$post_id][$key] ?? '';
}

function get_posts(array $args = array()): array
{
	$per_page = isset($args['posts_per_page']) ? (int) $args['posts_per_page'] : 0;
	$label = '';
	$result = array();

	if ($per_page === 200 && ($args['orderby'] ?? '') === 'meta_value') {
		$label = 'recipient_choices';
		$result = array(
			$GLOBALS['g12_posts_by_id'][201],
			$GLOBALS['g12_posts_by_id'][202],
			$GLOBALS['g12_posts_by_id'][203],
		);
	} elseif ($per_page === 100 && ($args['fields'] ?? '') === 'ids') {
		$label = 'scheduler_due_items';
		$result = array(301, 302);
	} elseif ($per_page === -1 && ($args['fields'] ?? '') === 'ids') {
		$label = 'import_complete_map';
		$result = array(401, 402, 403, 0);
	} elseif ($per_page === 1 && ($args['fields'] ?? '') === 'ids') {
		$label = 'import_exact_fallback';
		$value = (string) ($args['meta_query'][0]['value'] ?? '');
		$result = $value === 'late-key' ? array(499) : array();
	} else {
		throw new RuntimeException('Unexpected get_posts() query shape: ' . var_export($args, true));
	}

	$GLOBALS['g12_get_posts_calls'][] = array(
		'label' => $label,
		'args' => $args,
		'result' => $result,
	);
	return $result;
}

$plugin_root = dirname(__DIR__);
$shadow_root = dirname($plugin_root, 2) . '/vms';
$relative_paths = array(
	'recipients' => 'includes/modules/email-followups/recipients.php',
	'scheduler' => 'includes/modules/email-followups/scheduler.php',
	'import' => 'includes/services/event-plan-import/event-plan-import-engine.php',
);
$mirror_sources = array();
$shadow_sources = array();
foreach ($relative_paths as $key => $relative_path) {
	$mirror_path = $plugin_root . '/' . $relative_path;
	$shadow_path = $shadow_root . '/' . $relative_path;
	g12_assert(is_file($mirror_path), 'Missing mirror source: ' . $mirror_path);
	g12_assert(is_file($shadow_path), 'Missing shadow-live source: ' . $shadow_path);
	$mirror_sources[$key] = (string) file_get_contents($mirror_path);
	$shadow_sources[$key] = (string) file_get_contents($shadow_path);
	g12_assert($mirror_sources[$key] !== '' && $shadow_sources[$key] !== '', 'Target source should be readable: ' . $relative_path);
}

g12_same($mirror_sources['recipients'], $shadow_sources['recipients'], 'Email Follow-Ups recipients must retain full mirror/shadow-live parity.');
g12_same($mirror_sources['scheduler'], $shadow_sources['scheduler'], 'Email Follow-Ups scheduler must retain full mirror/shadow-live parity.');
g12_assert($mirror_sources['import'] !== $shadow_sources['import'], 'The Event Plan import engine should retain its intentional whole-file divergence.');

$import_target_functions = array(
	'bvmgr_event_plan_import_find_existing_plan_lookup',
	'bvmgr_event_plan_import_find_plan_id_by_key',
);
foreach ($import_target_functions as $function_name) {
	g12_same(
		g12_extract_function($mirror_sources['import'], $function_name),
		g12_extract_function($shadow_sources['import'], $function_name),
		'Event Plan import target function must retain mirror/shadow-live parity: ' . $function_name
	);
}

$meta_key_rule = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_key';
$meta_query_rule = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query';
$wave3_inventory = array(
	array('source' => 'recipients', 'file' => $relative_paths['recipients'], 'line' => 224, 'column' => 13, 'code' => $meta_key_rule, 'property' => "'meta_key'", 'reason' => 'Event choices intentionally order the bounded two-year Event Plan window by canonical event-date metadata.'),
	array('source' => 'recipients', 'file' => $relative_paths['recipients'], 'line' => 225, 'column' => 13, 'code' => $meta_query_rule, 'property' => "'meta_query'", 'reason' => 'Event choices intentionally filter the bounded two-year Event Plan window by canonical event-date metadata; no indexed domain date field exists.'),
	array('source' => 'scheduler', 'file' => $relative_paths['scheduler'], 'line' => 73, 'column' => 13, 'code' => $meta_key_rule, 'property' => "'meta_key'", 'reason' => 'The hourly cron query intentionally orders at most 100 Event Plans across its 130-day window by canonical event-date metadata.'),
	array('source' => 'scheduler', 'file' => $relative_paths['scheduler'], 'line' => 74, 'column' => 13, 'code' => $meta_query_rule, 'property' => "'meta_query'", 'reason' => 'The hourly cron query intentionally filters at most 100 Event Plans to its 130-day window by canonical event-date metadata.'),
	array('source' => 'import', 'file' => $relative_paths['import'], 'line' => 822, 'column' => 13, 'code' => $meta_query_rule, 'property' => "'meta_query'", 'reason' => 'Preview and commit each build the complete existing import-key map once before row processing; the canonical import key has no indexed domain field.'),
	array('source' => 'import', 'file' => $relative_paths['import'], 'line' => 2116, 'column' => 13, 'code' => $meta_query_rule, 'property' => "'meta_query'", 'reason' => 'Commit performs this exact one-row import-key fallback only when the key is absent from the complete prebuilt map, preserving late or concurrent plan discovery.'),
);

g12_same(6, count($wave3_inventory), 'Wave 3 ownership must remain exactly six packaged rows.');
$code_counts = array_count_values(array_column($wave3_inventory, 'code'));
g12_same(
	array($meta_key_rule => 2, $meta_query_rule => 4),
	$code_counts,
	'Wave 3 ownership should remain exactly two meta-key plus four meta-query rows.'
);

$resolved_rows = 0;
foreach ($wave3_inventory as $row) {
	$lines = preg_split('/\R/', $mirror_sources[$row['source']]);
	g12_assert(is_array($lines) && isset($lines[$row['line'] - 1]), 'Owned Wave 3 source line should still exist: ' . $row['file'] . ':' . $row['line']);
	$line = $lines[$row['line'] - 1];
	g12_contains($row['property'], $line, 'Owned annotation must remain on its exact query argument: ' . $row['file'] . ':' . $row['line']);
	$annotation = 'phpcs:ignore ' . $row['code'] . ' -- ' . $row['reason'];
	g12_contains($annotation, $line, 'Owned row must carry its exact installed-source annotation and rationale: ' . $row['file'] . ':' . $row['line']);
	g12_same(1, substr_count($line, 'phpcs:ignore'), 'Owned row must use exactly one line-local annotation: ' . $row['file'] . ':' . $row['line']);
	g12_same(1, substr_count($line, $row['code']), 'Owned row must name its exact scanner code once: ' . $row['file'] . ':' . $row['line']);
	g12_assert(strpos($line, ',WordPress.') === false, 'Owned row must not suppress multiple scanner codes: ' . $row['file'] . ':' . $row['line']);
	$resolved_rows++;
}
g12_same(0, count($wave3_inventory) - $resolved_rows, 'All six owned Wave 3 rows should project to zero residual target findings.');

$allowed_db_codes = array($meta_key_rule, $meta_query_rule);
$mirror_expected_annotations = array_map(
	static function (array $row): array {
		return array('source' => $row['source'], 'line' => $row['line'], 'code' => $row['code']);
	},
	$wave3_inventory
);
$shadow_expected_annotations = array(
	array('source' => 'recipients', 'line' => 224, 'code' => $meta_key_rule),
	array('source' => 'recipients', 'line' => 225, 'code' => $meta_query_rule),
	array('source' => 'scheduler', 'line' => 73, 'code' => $meta_key_rule),
	array('source' => 'scheduler', 'line' => 74, 'code' => $meta_query_rule),
	array('source' => 'import', 'line' => 666, 'code' => $meta_query_rule),
	array('source' => 'import', 'line' => 1875, 'code' => $meta_query_rule),
);
g12_same(
	array(),
	g12_db_annotation_errors($mirror_sources, $allowed_db_codes, $mirror_expected_annotations),
	'Mirror DB-related PHPCS annotations must use only the two exact installed source codes at the six owned occurrences.'
);
g12_same(
	array(),
	g12_db_annotation_errors($shadow_sources, $allowed_db_codes, $shadow_expected_annotations),
	'Shadow-live DB-related PHPCS annotations must use only the two exact installed source codes at the six owned occurrences.'
);

$broad_ignore_controls = array(
	'slow-query family' => '// phpcs:ignore WordPress.DB.SlowDBQuery -- forbidden broad family suppression',
	'DB category' => '// phpcs:ignore WordPress.DB -- forbidden broad category suppression',
	'prepared-SQL family' => '// phpcs:ignore WordPress.DB.PreparedSQL -- forbidden adjacent family suppression',
	'Plugin Check direct-DB family' => '// phpcs:ignore PluginCheck.Security.DirectDB -- forbidden broad family suppression',
	'multiple installed codes' => '// phpcs:ignore ' . $meta_key_rule . ',' . $meta_query_rule . ' -- forbidden multi-code suppression',
);
foreach ($broad_ignore_controls as $control_name => $control_annotation) {
	$mutated_sources = $mirror_sources;
	$mutated_sources['import'] .= "\n" . $control_annotation . "\n";
	g12_assert(
		g12_db_annotation_errors($mutated_sources, $allowed_db_codes, $mirror_expected_annotations) !== array(),
		'The DB annotation audit must reject the negative control: ' . $control_name
	);
}

$combined_source = implode("\n", $mirror_sources);
g12_same(2, substr_count($combined_source, $meta_key_rule), 'Owned sources should contain exactly two slow-meta-key annotations.');
g12_same(4, substr_count($combined_source, $meta_query_rule), 'Owned sources should contain exactly four slow-meta-query annotations.');
g12_same(0, preg_match_all('/phpcs:(?:disable|enable|ignoreFile)\b/i', $combined_source), 'G12 remediation must not use file-wide or block-wide PHPCS suppression.');

$projection_baselines = array(
	'mirror' => array(
		'recipients' => '68c8a7804077207dcbba0bdc58d6b56cb5d53af6bbd478059d38fba1f4f8d7eb',
		'scheduler' => 'f60b72dacfd2150abf77524fde694928fe5961ba09db77cd410c718788b06973',
		'import' => '1e298d59ed6afecfc5f0cbcdd7015d0b1c8de64d76470ab43a2fbfacf3000b4b',
	),
	'shadow' => array(
		'recipients' => '68c8a7804077207dcbba0bdc58d6b56cb5d53af6bbd478059d38fba1f4f8d7eb',
		'scheduler' => 'f60b72dacfd2150abf77524fde694928fe5961ba09db77cd410c718788b06973',
		'import' => '28476d864096c8e8ceba2d5c2421fada58e6ec86e2c35359089688bdc43086d2',
	),
);
$projected_sources = array();
foreach (array('mirror' => $mirror_sources, 'shadow' => $shadow_sources) as $tree_name => $sources) {
	$tree_removed = 0;
	foreach ($sources as $source_name => $source) {
		$owned_rows = array_values(array_filter(
			$wave3_inventory,
			static function (array $row) use ($source_name): bool {
				return $row['source'] === $source_name;
			}
		));
		$projection = g12_strip_owned_annotations($source, $owned_rows, $tree_name . ' ' . $source_name);
		g12_same(2, $projection['removed'], $tree_name . ' ' . $source_name . ' must project exactly two owned comments.');
		g12_same(
			$projection_baselines[$tree_name][$source_name],
			hash('sha256', $projection['source']),
			$tree_name . ' ' . $source_name . ' must be annotation-only relative to its immutable baseline.'
		);
		$projected_sources[$tree_name][$source_name] = $projection['source'];
		$tree_removed += $projection['removed'];
	}
	g12_same(6, $tree_removed, ucfirst($tree_name) . ' projection must strip exactly the six owned comments.');
}

$mutation_count = 0;
$mutated_projection = str_replace(
	"'posts_per_page' => 200,",
	"'posts_per_page' => 201,",
	$projected_sources['mirror']['recipients'],
	$mutation_count
);
g12_same(1, $mutation_count, 'Projection drift negative control must mutate exactly one recipient query argument.');
g12_assert(
	!hash_equals($projection_baselines['mirror']['recipients'], hash('sha256', $mutated_projection)),
	'Immutable projection hash must detect a non-annotation runtime mutation.'
);

$preview_source = g12_extract_function($mirror_sources['import'], 'bvmgr_event_plan_import_build_preview_from_csv');
$commit_source = g12_extract_function($mirror_sources['import'], 'bvmgr_event_plan_import_run_commit');
g12_same(1, substr_count($preview_source, 'bvmgr_event_plan_import_find_existing_plan_lookup()'), 'Preview should build the complete import-key map exactly once.');
g12_same(1, substr_count($commit_source, 'bvmgr_event_plan_import_find_existing_plan_lookup()'), 'Commit should build the complete import-key map exactly once.');

$GLOBALS['g12_posts_by_id'] = array(
	201 => new WP_Post(201),
	202 => new WP_Post(202),
	203 => new WP_Post(203),
);
$GLOBALS['g12_post_meta'] = array(
	201 => array('_vms_event_date' => wp_date('Y-m-d', time() + DAY_IN_SECONDS, wp_timezone())),
	202 => array('_vms_event_date' => wp_date('Y-m-d', time() - DAY_IN_SECONDS, wp_timezone())),
	203 => array('_vms_event_date' => wp_date('Y-m-d', time() + (5 * DAY_IN_SECONDS), wp_timezone())),
	401 => array('_vms_import_event_key' => 'alpha'),
	402 => array('_vms_import_event_key' => 'beta'),
	403 => array('_vms_import_event_key' => 'alpha'),
);
$GLOBALS['g12_get_posts_calls'] = array();

require $plugin_root . '/' . $relative_paths['recipients'];
require $plugin_root . '/' . $relative_paths['scheduler'];
require $plugin_root . '/' . $relative_paths['import'];

$choice_started = time();
$choices = vms_email_followups_event_choices(2);
$choice_finished = time();
g12_same(array(202, 201), g12_post_ids($choices), 'Recipient choices should preserve nearest-date ordering and the requested result limit.');
g12_same('recipient_choices', $GLOBALS['g12_get_posts_calls'][0]['label'] ?? null, 'First captured query should be the recipient-choice query.');
$recipient_args = $GLOBALS['g12_get_posts_calls'][0]['args'];
$recipient_window = $recipient_args['meta_query'][0]['value'] ?? array();
g12_assert(is_array($recipient_window) && count($recipient_window) === 2, 'Recipient query should retain its two-value date window.');
g12_assert(in_array($recipient_window[0], array(wp_date('Y-m-d', $choice_started - (365 * DAY_IN_SECONDS), wp_timezone()), wp_date('Y-m-d', $choice_finished - (365 * DAY_IN_SECONDS), wp_timezone())), true), 'Recipient lower date bound should remain one year before the query run.');
g12_assert(in_array($recipient_window[1], array(wp_date('Y-m-d', $choice_started + (365 * DAY_IN_SECONDS), wp_timezone()), wp_date('Y-m-d', $choice_finished + (365 * DAY_IN_SECONDS), wp_timezone())), true), 'Recipient upper date bound should remain one year after the query run.');
g12_same(730, (int) (new DateTimeImmutable($recipient_window[0]))->diff(new DateTimeImmutable($recipient_window[1]))->format('%a'), 'Recipient query should retain its bounded two-year date span.');
g12_same(
	array(
		'post_type' => 'vms_event_plan',
		'post_status' => array('publish', 'draft', 'pending', 'private'),
		'posts_per_page' => 200,
		'orderby' => 'meta_value',
		'order' => 'DESC',
		'meta_key' => '_vms_event_date',
		'meta_query' => array(array('key' => '_vms_event_date', 'value' => $recipient_window, 'compare' => 'BETWEEN', 'type' => 'DATE')),
	),
	$recipient_args,
	'Recipient get_posts() arguments should remain unchanged.'
);
g12_same(array(201, 202, 203), g12_post_ids($GLOBALS['g12_get_posts_calls'][0]['result']), 'Captured recipient get_posts() result should pass through unchanged before local ordering.');

$scheduler_started = time();
$due_items = vms_email_followups_due_items();
$scheduler_finished = time();
g12_same(array(301, 302), array_column($due_items, 'event_plan_id'), 'Scheduler should preserve every due plan returned by its bounded query.');
g12_same('scheduler_due_items', $GLOBALS['g12_get_posts_calls'][1]['label'] ?? null, 'Second captured query should be the scheduler query.');
$scheduler_args = $GLOBALS['g12_get_posts_calls'][1]['args'];
$scheduler_window = $scheduler_args['meta_query'][0]['value'] ?? array();
g12_assert(is_array($scheduler_window) && count($scheduler_window) === 2, 'Scheduler query should retain its two-value date window.');
g12_assert(in_array($scheduler_window[0], array(wp_date('Y-m-d', $scheduler_started - (65 * DAY_IN_SECONDS), wp_timezone()), wp_date('Y-m-d', $scheduler_finished - (65 * DAY_IN_SECONDS), wp_timezone())), true), 'Scheduler lower date bound should remain 65 days before the cron run.');
g12_assert(in_array($scheduler_window[1], array(wp_date('Y-m-d', $scheduler_started + (65 * DAY_IN_SECONDS), wp_timezone()), wp_date('Y-m-d', $scheduler_finished + (65 * DAY_IN_SECONDS), wp_timezone())), true), 'Scheduler upper date bound should remain 65 days after the cron run.');
g12_same(130, (int) (new DateTimeImmutable($scheduler_window[0]))->diff(new DateTimeImmutable($scheduler_window[1]))->format('%a'), 'Scheduler query should retain its 130-day cron window.');
g12_same(
	array(
		'post_type' => 'vms_event_plan',
		'post_status' => 'publish',
		'posts_per_page' => 100,
		'fields' => 'ids',
		'orderby' => 'meta_value',
		'order' => 'ASC',
		'meta_key' => '_vms_event_date',
		'meta_query' => array(array('key' => '_vms_event_date', 'value' => $scheduler_window, 'compare' => 'BETWEEN', 'type' => 'DATE')),
	),
	$scheduler_args,
	'Scheduler get_posts() arguments should remain unchanged.'
);
g12_same(array(301, 302), $GLOBALS['g12_get_posts_calls'][1]['result'], 'Captured scheduler get_posts() result should pass through unchanged.');

$plan_lookup = bvmgr_event_plan_import_find_existing_plan_lookup();
g12_same(array('alpha' => 401, 'beta' => 402), $plan_lookup, 'Complete import lookup should preserve first-ID wins and skip invalid or duplicate keys.');
g12_same('import_complete_map', $GLOBALS['g12_get_posts_calls'][2]['label'] ?? null, 'Third captured query should build the complete import-key map.');
g12_same(
	array(
		'post_type' => 'vms_event_plan',
		'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'meta_query' => array(array('key' => '_vms_import_event_key', 'compare' => 'EXISTS')),
	),
	$GLOBALS['g12_get_posts_calls'][2]['args'],
	'Complete import-map get_posts() arguments should remain unchanged.'
);
g12_same(array(401, 402, 403, 0), $GLOBALS['g12_get_posts_calls'][2]['result'], 'Captured complete import-map result should pass through unchanged.');

$call_count = count($GLOBALS['g12_get_posts_calls']);
g12_same(401, bvmgr_event_plan_import_find_plan_id_by_key('alpha', $plan_lookup), 'Prebuilt import-map hits should return without a fallback query.');
g12_same($call_count, count($GLOBALS['g12_get_posts_calls']), 'Prebuilt import-map hits must not issue another get_posts() query.');
g12_same(0, bvmgr_event_plan_import_find_plan_id_by_key('  ', $plan_lookup), 'Blank import keys should fail closed without a fallback query.');
g12_same($call_count, count($GLOBALS['g12_get_posts_calls']), 'Blank import keys must not issue another get_posts() query.');

g12_same(499, bvmgr_event_plan_import_find_plan_id_by_key('late-key', $plan_lookup), 'Absent import keys should preserve the exact one-row fallback result.');
g12_same(499, $plan_lookup['late-key'] ?? 0, 'Exact fallback matches should be retained in the request-local lookup map.');
g12_same('import_exact_fallback', $GLOBALS['g12_get_posts_calls'][3]['label'] ?? null, 'Fourth captured query should be the exact one-row import fallback.');
g12_same(
	array(
		'post_type' => 'vms_event_plan',
		'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
		'posts_per_page' => 1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'meta_query' => array(array('key' => '_vms_import_event_key', 'value' => 'late-key', 'compare' => '=')),
	),
	$GLOBALS['g12_get_posts_calls'][3]['args'],
	'Exact import fallback get_posts() arguments should remain unchanged.'
);
g12_same(array(499), $GLOBALS['g12_get_posts_calls'][3]['result'], 'Captured exact fallback result should pass through unchanged.');
g12_same(4, count($GLOBALS['g12_get_posts_calls']), 'Focused runtime coverage should issue exactly four captured get_posts() calls.');

fwrite(STDOUT, "G12 background meta-query remediation: PASS (Wave 3 rows 6 -> projected 0; meta_key -2, meta_query -4)\n");
