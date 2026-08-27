<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

function g14_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g14_same($expected, $actual, string $message): void
{
	g14_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function g14_extract_braced_declaration(string $source, string $needle): string
{
	$start = strpos($source, $needle);
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to find declaration: ' . $needle);
	}

	$depth = 1;
	$quote = '';
	$escaped = false;
	for ($index = $brace + 1, $length = strlen($source); $index < $length; $index++) {
		$character = $source[$index];
		if ($quote !== '') {
			if ($escaped) {
				$escaped = false;
				continue;
			}
			if ($character === '\\') {
				$escaped = true;
				continue;
			}
			if ($character === $quote) {
				$quote = '';
			}
			continue;
		}
		if ($character === "'" || $character === '"') {
			$quote = $character;
			continue;
		}
		$depth += $character === '{' ? 1 : 0;
		$depth -= $character === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($index - $start) + 1);
		}
	}

	throw new RuntimeException('Unable to parse declaration: ' . $needle);
}

function g14_extract_function(string $source, string $name): string
{
	return g14_extract_braced_declaration($source, 'function ' . $name . '(');
}

function g14_replace_once(string $source, string $search, string $replacement, string $message): string
{
	$source = str_replace($search, $replacement, $source, $count);
	g14_same(1, $count, $message);
	return $source;
}

function g14_source_line(string $source, string $statement): string
{
	$matches = array();
	foreach (preg_split('/\R/', $source) ?: array() as $line) {
		if (trim($line) === $statement) {
			$matches[] = trim($line);
		}
	}
	g14_same(1, count($matches), 'Date statement must occur on exactly one line: ' . $statement);
	return $matches[0];
}

/** @param array<string,string> $replacements */
function g14_statement_callback(string $statement, string $parameters, string $return_expression, array $replacements = array()): Closure
{
	$statement = strtr($statement, $replacements);
	$callback = eval('return static function (' . $parameters . ') { ' . $statement . "\nreturn " . $return_expression . '; };');
	if (!$callback instanceof Closure) {
		throw new RuntimeException('Unable to build deterministic boundary callback.');
	}
	return $callback;
}

function g14_validate_no_date_suppressions(string $source): void
{
	if (preg_match('/phpcs:(?:disable|enable|ignoreFile)[^\r\n]*(?:WordPress\.DateTime|RestrictedFunctions\.date_date)/i', $source) === 1) {
		throw new RuntimeException('Block/file DateTime suppression is forbidden.');
	}
	if (preg_match('/phpcs:ignore[^\r\n]*(?:WordPress\.DateTime|RestrictedFunctions\.date_date)/i', $source) === 1) {
		throw new RuntimeException('DateTime ignores are forbidden for behaviorally remediated boundaries.');
	}
}

function g14_restore_g17_helper_logging(string $source): string
{
	$current = <<<'CURRENT'
            if (function_exists('vms_record_operational_issue')) {
                vms_record_operational_issue(
                    'tax_profile_meta_shape_invalid',
                    array(
                        'entity_id' => $id,
                        'entity_type' => 'vendor',
                        'operation' => 'read_meta',
                        'status' => 'invalid',
                    )
                );
            }
CURRENT;
	$historical = "            error_log('[VMS] tax missing_items: non-scalar meta for key ' . \$key . ' on post_id ' . \$id);";
	return g14_replace_once($source, $current, $historical, 'G17 helper logging projection changed');
}

$root = dirname(__DIR__);
$shadow_root = dirname($root, 2) . '/vms';
$owned_files = array(
	'includes/helpers.php',
	'includes/admin/ticket-integrity-page.php',
	'includes/admin/staff-tax-sidebar.php',
	'includes/admin/tax-profile-admin-metabox.php',
	'includes/admin/square-sync-protection.php',
	'includes/cpt/event-plans/partials/time-lineup.php',
	'includes/schedule/season-dates.php',
	'includes/core/cli/state-of-range.php',
);

$sources = array('mirror' => array(), 'shadow' => array());
foreach ($owned_files as $file) {
	$mirror_source = file_get_contents($root . '/' . $file);
	$shadow_source = file_get_contents($shadow_root . '/' . $file);
	if (!is_string($mirror_source) || !is_string($shadow_source)) {
		throw new RuntimeException('Unable to read mirror/shadow source: ' . $file);
	}
	$sources['mirror'][$file] = $mirror_source;
	$sources['shadow'][$file] = $shadow_source;
}

$occurrences = array(
	'H-start' => array(
		'file' => 'includes/helpers.php',
		'current' => "\$start = gmdate('Y-m-d', strtotime(\$start));",
		'api' => 'gmdate',
	),
	'H-end' => array(
		'file' => 'includes/helpers.php',
		'current' => "\$end   = gmdate('Y-m-d', strtotime(\$end));",
		'api' => 'gmdate',
	),
	'H-weekday' => array(
		'file' => 'includes/helpers.php',
		'current' => "\$w = intval(gmdate('w', strtotime(\$ymd))); // 0=Sun..6=Sat",
		'api' => 'gmdate',
	),
	'TI-generated' => array(
		'file' => 'includes/admin/ticket-integrity-page.php',
		'current' => "\$generated_at = wp_date('Y-m-d H:i:s T', time(), wp_timezone());",
		'api' => 'wp_date',
	),
	'TI-filename' => array(
		'file' => 'includes/admin/ticket-integrity-page.php',
		'current' => "\$filename = 'ticket-integrity-report-plan-' . \$plan_id . '-' . wp_date('Ymd-His', time(), wp_timezone()) . '.md';",
		'api' => 'wp_date',
	),
	'Staff-today' => array(
		'file' => 'includes/admin/staff-tax-sidebar.php',
		'current' => "\$today = wp_date('Y-m-d', time(), wp_timezone());",
		'api' => 'wp_date',
	),
	'Tax-received' => array(
		'file' => 'includes/admin/tax-profile-admin-metabox.php',
		'current' => "update_post_meta(\$post_id, \$k_w9_recv, wp_date('Y-m-d', time(), wp_timezone()));",
		'api' => 'wp_date',
	),
	'Square-readable' => array(
		'file' => 'includes/admin/square-sync-protection.php',
		'current' => "\$readable = wp_date('Y-m-d H:i', \$ts, wp_timezone());",
		'api' => 'wp_date',
	),
	'Lineup-until' => array(
		'file' => 'includes/cpt/event-plans/partials/time-lineup.php',
		'current' => "\$tax_bypass_default_until = wp_date('Y-m-d', strtotime('+30 days'), wp_timezone());",
		'api' => 'wp_date',
	),
	'Season-key' => array(
		'file' => 'includes/schedule/season-dates.php',
		'current' => "\$d = gmdate('Y-m-d', \$ts);",
		'api' => 'gmdate',
	),
	'CLI-label' => array(
		'file' => 'includes/core/cli/state-of-range.php',
		'current' => "return wp_date('Y-m-d H:i:s', \$timestamp, wp_timezone());",
		'api' => 'wp_date',
	),
);

$artifact_code = 'WordPress.DateTime.RestrictedFunctions.date_date';
$artifact_rows = array(
	'includes/admin/ticket-integrity-page.php:607:5' => array('file' => 'includes/admin/ticket-integrity-page.php', 'line' => 607, 'column' => 5, 'code' => $artifact_code, 'occurrence' => 'TI-generated'),
	'includes/admin/ticket-integrity-page.php:832:139' => array('file' => 'includes/admin/ticket-integrity-page.php', 'line' => 832, 'column' => 139, 'code' => $artifact_code, 'occurrence' => 'TI-filename'),
	'includes/helpers.php:3320:18' => array('file' => 'includes/helpers.php', 'line' => 3320, 'column' => 18, 'code' => $artifact_code, 'occurrence' => 'H-start'),
	'includes/helpers.php:3321:18' => array('file' => 'includes/helpers.php', 'line' => 3321, 'column' => 18, 'code' => $artifact_code, 'occurrence' => 'H-end'),
	'includes/helpers.php:3380:17' => array('file' => 'includes/helpers.php', 'line' => 3380, 'column' => 17, 'code' => $artifact_code, 'occurrence' => 'H-weekday'),
	'includes/schedule/season-dates.php:628:30' => array('file' => 'includes/schedule/season-dates.php', 'line' => 628, 'column' => 30, 'code' => $artifact_code, 'occurrence' => 'Season-key'),
	'includes/core/cli/state-of-range.php:241:11' => array('file' => 'includes/core/cli/state-of-range.php', 'line' => 241, 'column' => 11, 'code' => $artifact_code, 'occurrence' => 'CLI-label'),
	'includes/cpt/event-plans/partials/time-lineup.php:229:43' => array('file' => 'includes/cpt/event-plans/partials/time-lineup.php', 'line' => 229, 'column' => 43, 'code' => $artifact_code, 'occurrence' => 'Lineup-until'),
	'includes/admin/staff-tax-sidebar.php:400:85' => array('file' => 'includes/admin/staff-tax-sidebar.php', 'line' => 400, 'column' => 85, 'code' => $artifact_code, 'occurrence' => 'Staff-today'),
	'includes/admin/tax-profile-admin-metabox.php:114:123' => array('file' => 'includes/admin/tax-profile-admin-metabox.php', 'line' => 114, 'column' => 123, 'code' => $artifact_code, 'occurrence' => 'Tax-received'),
	'includes/admin/square-sync-protection.php:256:101' => array('file' => 'includes/admin/square-sync-protection.php', 'line' => 256, 'column' => 101, 'code' => $artifact_code, 'occurrence' => 'Square-readable'),
);

g14_same(11, count($artifact_rows), 'Wave 4 artifact inventory must contain exactly eleven G14 rows.');
g14_same(array($artifact_code => 11), array_count_values(array_column($artifact_rows, 'code')), 'Artifact-derived date_date inventory changed.');

$covered_rows = array();
foreach ($artifact_rows as $row_id => $row) {
	$occurrence_id = $row['occurrence'];
	g14_assert(isset($occurrences[$occurrence_id]), 'Artifact row lacks a current occurrence: ' . $row_id);
	$occurrence = $occurrences[$occurrence_id];
	g14_same($row['file'], $occurrence['file'], 'Artifact row moved files: ' . $row_id);
	foreach (array('mirror', 'shadow') as $tree) {
		g14_same(1, substr_count($sources[$tree][$row['file']], $occurrence['current']), 'Current occurrence must exist exactly once: ' . $tree . '/' . $occurrence_id);
	}
	g14_assert(preg_match('/(?<![A-Za-z0-9_])date\s*\(/', $occurrence['current']) !== 1, 'Native date() remains at ' . $occurrence_id);
	$covered_rows[$row_id] = true;
}
g14_same(array_keys($artifact_rows), array_keys($covered_rows), 'Zero-residual proof must preserve every artifact row identity.');
g14_same(array('gmdate' => 4, 'wp_date' => 7), array_count_values(array_column($occurrences, 'api')), 'Per-occurrence API split changed.');

foreach ($sources as $tree => $tree_sources) {
	$combined = implode("\n", $tree_sources);
	g14_assert(preg_match('/(?<![A-Za-z0-9_])date\s*\(/', $combined) !== 1, 'Native date() remains in G14 files: ' . $tree);
	g14_validate_no_date_suppressions($combined);
}

foreach (array(
	'// phpcs:disable WordPress.DateTime',
	'// phpcs:enable WordPress.DateTime',
	'// phpcs:ignoreFile WordPress.DateTime',
	'// phpcs:ignore WordPress.DateTime -- invented broad category',
	'// phpcs:ignore WordPress.DateTime.RestrictedFunctions -- invented family',
	'// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date,WordPress.Security.EscapeOutput.OutputNotEscaped -- invented mixed suppression',
) as $negative_directive) {
	$rejected = false;
	try {
		g14_validate_no_date_suppressions($sources['mirror']['includes/helpers.php'] . "\n" . $negative_directive);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	g14_assert($rejected, 'Broad/family/mixed DateTime suppression was accepted.');
}

$pre_hashes = array(
	'mirror' => array(
		'includes/helpers.php' => '31b8474db26ba75604adee9dd56ac6c8aa7b496cace43484cac07c1c41b88f81',
		'includes/admin/ticket-integrity-page.php' => '2a569a5a60d2fcea7babb5cc94cb3f24b4b39bb08e6ae50fb368120084cd6d57',
		'includes/admin/staff-tax-sidebar.php' => 'b6364ff17bd018eb6c76d8f9e60c8c45740d6cd67720c81ce09315dc212f8c4e',
		'includes/admin/tax-profile-admin-metabox.php' => '1877e46672217c9f5c1044d895f809b4797cb6615d03e2e9f02858238a718d39',
		'includes/admin/square-sync-protection.php' => '39c7fc47a330385b86d3e4aceeff4ada18cf4b22a71b00e3dd6c923f2405ffad',
		'includes/cpt/event-plans/partials/time-lineup.php' => 'e76d0a192c1e61b53752efda2303ff0a3bd7abc51acaf6b779126041c3bff3ad',
		'includes/schedule/season-dates.php' => 'b941307acce629b07f627b8bc84d6354e9047b100a8e7af8b2498c766b42774c',
		'includes/core/cli/state-of-range.php' => 'a99cb8ffd44c3d148bf8d865a468906c5bbce859865e52c8704ce9f731b6b457',
	),
	'shadow' => array(
		'includes/helpers.php' => '5b2aad40f8d4c36cd6c6a730dd3c295684a634072bc6784c3fa7be047f3b5b35',
		'includes/admin/ticket-integrity-page.php' => 'b3a37da3c754ed2c339443db64ee4abef46ec8af33ccce5f70c33f75309d8026',
		'includes/admin/staff-tax-sidebar.php' => '39cee33b6d26eb275abfcee902b60bee39a3eae0fca99021accfbfe4f572ea1f',
		'includes/admin/tax-profile-admin-metabox.php' => '67bef863ce8b30ac5b5e408c8c2503757185aa30d51586c338a542665e7c764c',
		'includes/admin/square-sync-protection.php' => 'eb1ede5427d43c7ab8c697dbaae3a3e1210fafa07b990802987d75fbb9e0cbbb',
		'includes/cpt/event-plans/partials/time-lineup.php' => 'e76d0a192c1e61b53752efda2303ff0a3bd7abc51acaf6b779126041c3bff3ad',
		'includes/schedule/season-dates.php' => 'b941307acce629b07f627b8bc84d6354e9047b100a8e7af8b2498c766b42774c',
		'includes/core/cli/state-of-range.php' => 'a99cb8ffd44c3d148bf8d865a468906c5bbce859865e52c8704ce9f731b6b457',
	),
);

$historical = array(
	'mirror' => array(
		'H-start' => "\$start = date('Y-m-d', strtotime(\$start));",
		'H-end' => "\$end   = date('Y-m-d', strtotime(\$end));",
		'H-weekday' => "\$w = intval(date('w', strtotime(\$ymd))); // 0=Sun..6=Sat",
		'TI-generated' => "\$generated_at = function_exists('wp_date')\n\t\t? wp_date('Y-m-d H:i:s T', time(), wp_timezone())\n\t\t: date('Y-m-d H:i:s');",
		'TI-filename' => "\$filename = 'ticket-integrity-report-plan-' . \$plan_id . '-' . (function_exists('wp_date') ? wp_date('Ymd-His', time(), wp_timezone()) : date('Ymd-His')) . '.md';",
		'Staff-today' => "\$today = function_exists('wp_date') ? wp_date('Y-m-d', time(), wp_timezone()) : date('Y-m-d');",
		'Tax-received' => "update_post_meta(\$post_id, \$k_w9_recv, function_exists('wp_date') ? wp_date('Y-m-d', time(), wp_timezone()) : date('Y-m-d'));",
		'Square-readable' => "\$readable = function_exists('wp_date') ? wp_date('Y-m-d H:i', \$ts, wp_timezone()) : date('Y-m-d H:i', \$ts);",
		'Lineup-until' => "\$tax_bypass_default_until = function_exists('wp_date')\n                                        ? wp_date('Y-m-d', strtotime('+30 days'), wp_timezone())\n                                        : date('Y-m-d', strtotime('+30 days'));",
		'Season-key' => "\$d = date('Y-m-d', \$ts);",
		'CLI-label' => "return date('Y-m-d H:i:s', \$timestamp);",
	),
	'shadow' => array(),
);
$historical['shadow'] = $historical['mirror'];
$historical['shadow']['Tax-received'] = "update_post_meta(\$post_id, \$k_w9_recv, date('Y-m-d'));";

foreach (array('mirror', 'shadow') as $tree) {
	foreach ($owned_files as $file) {
		$current = $sources[$tree][$file];
		$projected = $current;
		$current_outside = $current;
		if ($file === 'includes/helpers.php') {
			$projected = g14_restore_g17_helper_logging($projected);
			$current_outside = g14_restore_g17_helper_logging($current_outside);
		}
		foreach ($occurrences as $occurrence_id => $occurrence) {
			if ($occurrence['file'] !== $file) {
				continue;
			}
			$projected = g14_replace_once(
				$projected,
				$occurrence['current'],
				$historical[$tree][$occurrence_id],
				'Semantic projection replacement changed: ' . $tree . '/' . $occurrence_id
			);
			$current_outside = g14_replace_once(
				$current_outside,
				$occurrence['current'],
				'/* G14:' . $occurrence_id . ' */',
				'Current outside projection changed: ' . $tree . '/' . $occurrence_id
			);
		}
		g14_same($pre_hashes[$tree][$file], hash('sha256', $projected), 'Immutable whole-source semantic projection changed: ' . $tree . '/' . $file);

		$historical_outside = $projected;
		foreach ($occurrences as $occurrence_id => $occurrence) {
			if ($occurrence['file'] !== $file) {
				continue;
			}
			$historical_outside = g14_replace_once(
				$historical_outside,
				$historical[$tree][$occurrence_id],
				'/* G14:' . $occurrence_id . ' */',
				'Historical outside projection changed: ' . $tree . '/' . $occurrence_id
			);
		}
		g14_same(hash('sha256', $historical_outside), hash('sha256', $current_outside), 'Outside-owned projection changed: ' . $tree . '/' . $file);
	}
}

$full_match_files = array(
	'includes/cpt/event-plans/partials/time-lineup.php',
	'includes/schedule/season-dates.php',
	'includes/core/cli/state-of-range.php',
);
foreach ($full_match_files as $file) {
	g14_same($sources['mirror'][$file], $sources['shadow'][$file], 'Full-match mirror/shadow file diverged: ' . $file);
}
foreach (array_diff($owned_files, $full_match_files) as $file) {
	g14_assert($sources['mirror'][$file] !== $sources['shadow'][$file], 'Intentional whole-file divergence disappeared: ' . $file);
}

$mutated = str_replace('return false; // closed until configured', 'return true; // mutation control', $sources['mirror']['includes/helpers.php'], $mutation_count);
g14_same(1, $mutation_count, 'Runtime mutation control must alter one non-owned helper branch.');
$mutated_projected = g14_restore_g17_helper_logging($mutated);
foreach ($occurrences as $occurrence_id => $occurrence) {
	if ($occurrence['file'] !== 'includes/helpers.php') {
		continue;
	}
	$mutated_projected = g14_replace_once(
		$mutated_projected,
		$occurrence['current'],
		$historical['mirror'][$occurrence_id],
		'Mutation projection replacement changed: ' . $occurrence_id
	);
}
g14_assert(hash('sha256', $mutated_projected) !== $pre_hashes['mirror']['includes/helpers.php'], 'Immutable projection failed to reject a non-comment runtime mutation.');

$GLOBALS['g14_site_timezone'] = new DateTimeZone('UTC');
$GLOBALS['g14_post_meta'] = array();
$GLOBALS['g14_updated_meta'] = array();
$GLOBALS['g14_venue_in_season'] = true;
$GLOBALS['g14_season_rules'] = array();

function wp_timezone(): DateTimeZone
{
	return $GLOBALS['g14_site_timezone'];
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
	$timestamp = $timestamp ?? time();
	$timezone = $timezone ?? wp_timezone();
	return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format);
}

function sanitize_text_field($value): string
{
	return trim(strip_tags((string) $value));
}

function maybe_unserialize($value)
{
	return $value;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['g14_post_meta'][$post_id][$key] ?? '';
}

function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['g14_updated_meta'][$post_id][$key] = $value;
	return true;
}

function bvmgr_normalize_int_array($value): array
{
	return array_values(array_unique(array_map('intval', is_array($value) ? $value : array())));
}

function bvmgr_venue_is_in_season(int $venue_id, string $ymd): bool
{
	unset($venue_id, $ymd);
	return (bool) $GLOBALS['g14_venue_in_season'];
}

function vms_sch_season_is_valid_ymd(string $ymd): bool
{
	$date = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new DateTimeZone('UTC'));
	return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $ymd;
}

function vms_sch_season_get_rules(int $venue_id): array
{
	return $GLOBALS['g14_season_rules'][$venue_id] ?? array();
}

function vms_sch_season_sanitize_note($note): string
{
	return sanitize_text_field($note);
}

$mirror = $sources['mirror'];
$helper_start = g14_statement_callback(
	g14_source_line($mirror['includes/helpers.php'], $occurrences['H-start']['current']),
	'string $start',
	'$start'
);
$helper_end = g14_statement_callback(
	g14_source_line($mirror['includes/helpers.php'], $occurrences['H-end']['current']),
	'string $end',
	'$end'
);
$helper_weekday = g14_statement_callback(
	g14_source_line($mirror['includes/helpers.php'], $occurrences['H-weekday']['current']),
	'string $ymd',
	'$w'
);
$ticket_generated = g14_statement_callback(
	g14_source_line($mirror['includes/admin/ticket-integrity-page.php'], $occurrences['TI-generated']['current']),
	'int $timestamp',
	'$generated_at',
	array('time()' => '$timestamp')
);
$ticket_filename = g14_statement_callback(
	g14_source_line($mirror['includes/admin/ticket-integrity-page.php'], $occurrences['TI-filename']['current']),
	'int $plan_id, int $timestamp',
	'$filename',
	array('time()' => '$timestamp')
);
$staff_today = g14_statement_callback(
	g14_source_line($mirror['includes/admin/staff-tax-sidebar.php'], $occurrences['Staff-today']['current']),
	'int $timestamp',
	'$today',
	array('time()' => '$timestamp')
);
$tax_received = g14_statement_callback(
	g14_source_line($mirror['includes/admin/tax-profile-admin-metabox.php'], $occurrences['Tax-received']['current']),
	'int $post_id, string $k_w9_recv, int $timestamp',
	'$GLOBALS["g14_updated_meta"][$post_id][$k_w9_recv] ?? null',
	array('time()' => '$timestamp')
);
$square_readable = g14_statement_callback(
	g14_source_line($mirror['includes/admin/square-sync-protection.php'], $occurrences['Square-readable']['current']),
	'int $ts',
	'$readable'
);
$lineup_until = g14_statement_callback(
	g14_source_line($mirror['includes/cpt/event-plans/partials/time-lineup.php'], $occurrences['Lineup-until']['current']),
	'int $timestamp',
	'$tax_bypass_default_until',
	array("strtotime('+30 days')" => '$timestamp')
);
$season_key = g14_statement_callback(
	g14_source_line($mirror['includes/schedule/season-dates.php'], $occurrences['Season-key']['current']),
	'int $ts',
	'$d'
);

$fixed_timestamp = (new DateTimeImmutable('2026-03-08 05:30:00', new DateTimeZone('UTC')))->getTimestamp();
$local_expectations = array(
	'UTC' => array(
		'generated' => '2026-03-08 05:30:00 UTC',
		'filename' => 'ticket-integrity-report-plan-77-20260308-053000.md',
		'date' => '2026-03-08',
		'minute' => '2026-03-08 05:30',
	),
	'America/Chicago' => array(
		'generated' => '2026-03-07 23:30:00 CST',
		'filename' => 'ticket-integrity-report-plan-77-20260307-233000.md',
		'date' => '2026-03-07',
		'minute' => '2026-03-07 23:30',
	),
);
$exercised_occurrences = array();
foreach ($local_expectations as $timezone_name => $expected) {
	$GLOBALS['g14_site_timezone'] = new DateTimeZone($timezone_name);
	$GLOBALS['g14_updated_meta'] = array();
	g14_same($expected['generated'], $ticket_generated($fixed_timestamp), 'Ticket report label timezone changed: ' . $timezone_name);
	g14_same($expected['filename'], $ticket_filename(77, $fixed_timestamp), 'Ticket export filename stamp changed: ' . $timezone_name);
	g14_same($expected['date'], $staff_today($fixed_timestamp), 'Staff compliance date changed: ' . $timezone_name);
	g14_same($expected['date'], $tax_received(31, '_received', $fixed_timestamp), 'W-9 received date changed: ' . $timezone_name);
	g14_same($expected['minute'], $square_readable($fixed_timestamp), 'Square report label changed: ' . $timezone_name);
	g14_same($expected['date'], $lineup_until($fixed_timestamp), 'Lineup default-until local date changed: ' . $timezone_name);
}
foreach (array('mirror', 'shadow') as $tree) {
	foreach (array(
		"vms_staff_employee_packet_set_flag(\$staff_id, vms_staff_employee_w4_received_key(), '_vms_employee_w4_received_date', \$w4, \$today);",
		"vms_staff_employee_packet_set_flag(\$staff_id, vms_staff_employee_i9_verified_key(), '_vms_employee_i9_verified_date', \$i9, \$today);",
		"vms_staff_employee_packet_set_flag(\$staff_id, vms_staff_employee_direct_deposit_received_key(), '_vms_employee_direct_deposit_received_date', \$dd, \$today);",
	) as $persist_call) {
		g14_same(1, substr_count($sources[$tree]['includes/admin/staff-tax-sidebar.php'], $persist_call), 'Staff compliance persistence propagation changed: ' . $tree);
	}
}
foreach (array('TI-generated', 'TI-filename', 'Staff-today', 'Tax-received', 'Square-readable', 'Lineup-until') as $occurrence_id) {
	$exercised_occurrences[$occurrence_id] = true;
}

date_default_timezone_set('UTC');
$GLOBALS['g14_site_timezone'] = new DateTimeZone('America/Chicago');
g14_same('2026-03-08 05:30:00', date('Y-m-d H:i:s', $fixed_timestamp), 'Historical raw fallback characterization changed.');
g14_same('2026-03-07 23:30:00 CST', $ticket_generated($fixed_timestamp), 'Supported WordPress path must remain site-local.');
g14_assert(date('Y-m-d H:i:s', $fixed_timestamp) !== $ticket_generated($fixed_timestamp), 'Removed raw fallback should remain observably different from site-local output at the UTC/local boundary.');

foreach (array('UTC', 'America/Chicago') as $runtime_timezone) {
	date_default_timezone_set($runtime_timezone);
	g14_same('2026-03-08', $helper_start('2026-03-08'), 'Season start normalization changed at DST start: ' . $runtime_timezone);
	g14_same('2026-11-01', $helper_end('2026-11-01'), 'Season end normalization changed at DST end: ' . $runtime_timezone);
	g14_same('1970-01-01', $helper_start('not-a-date'), 'Invalid season input must retain UTC epoch normalization: ' . $runtime_timezone);
	g14_same('1970-01-01', $helper_end('@0'), 'Epoch season input changed: ' . $runtime_timezone);
	g14_same(0, $helper_weekday('2026-03-08'), 'DST-start Sunday decision changed: ' . $runtime_timezone);
	g14_same(0, $helper_weekday('2026-11-01'), 'DST-end Sunday decision changed: ' . $runtime_timezone);
	g14_same(4, $helper_weekday('1970-01-01'), 'Epoch weekday decision changed: ' . $runtime_timezone);
	g14_same(4, $helper_weekday('not-a-date'), 'Invalid weekday fallback changed: ' . $runtime_timezone);
	g14_same('2026-03-08', $season_key($fixed_timestamp), 'UTC season key changed: ' . $runtime_timezone);
}
foreach (array('H-start', 'H-end', 'H-weekday', 'Season-key') as $occurrence_id) {
	$exercised_occurrences[$occurrence_id] = true;
}

date_default_timezone_set('UTC');
g14_same('2026-03-08', date('Y-m-d', $fixed_timestamp), 'UTC historical date() baseline changed.');
date_default_timezone_set('America/Chicago');
g14_same('2026-03-07', date('Y-m-d', $fixed_timestamp), 'Non-UTC historical date() baseline changed.');
g14_same('2026-03-08', $season_key($fixed_timestamp), 'gmdate() must keep UTC identifier semantics under a non-UTC runtime.');
g14_same('1969-12-31', date('Y-m-d', 0), 'Non-UTC historical invalid/epoch drift characterization changed.');
g14_same('1970-01-01', $helper_start('not-a-date'), 'Invalid helper input must remain stable at the UTC epoch.');

g14_same(
	g14_extract_function($sources['mirror']['includes/helpers.php'], 'bvmgr_get_venue_schedule_config'),
	g14_extract_function($sources['shadow']['includes/helpers.php'], 'bvmgr_get_venue_schedule_config'),
	'Venue schedule normalization function must match across mirror/shadow.'
);
g14_same(
	g14_extract_function($sources['mirror']['includes/helpers.php'], 'bvmgr_venue_is_open_on_date'),
	g14_extract_function($sources['shadow']['includes/helpers.php'], 'bvmgr_venue_is_open_on_date'),
	'Venue weekday decision function must match across mirror/shadow.'
);
g14_same(
	g14_extract_function($sources['mirror']['includes/admin/ticket-integrity-page.php'], 'bvmgr_ticket_integrity_build_event_report_markdown'),
	g14_extract_function($sources['shadow']['includes/admin/ticket-integrity-page.php'], 'bvmgr_ticket_integrity_build_event_report_markdown'),
	'Ticket report date-label function must match across mirror/shadow.'
);
g14_same(
	g14_extract_function($sources['mirror']['includes/admin/ticket-integrity-page.php'], 'bvmgr_ticket_integrity_handle_export_report'),
	g14_extract_function($sources['shadow']['includes/admin/ticket-integrity-page.php'], 'bvmgr_ticket_integrity_handle_export_report'),
	'Ticket export filename function must match across mirror/shadow.'
);

eval(g14_extract_function($mirror['includes/helpers.php'], 'bvmgr_get_venue_schedule_config'));
eval(g14_extract_function($mirror['includes/helpers.php'], 'bvmgr_venue_is_open_on_date'));

$GLOBALS['g14_post_meta'][7] = array(
	'_vms_venue_open_days' => array(0, 4),
	'_vms_venue_open_year_round' => '0',
	'_vms_venue_seasons' => array(
		array('start' => '2026-03-08', 'end' => '2026-11-01'),
		array('start' => 'not-a-date', 'end' => '@0'),
		array('start' => '2026-12-31', 'end' => '2026-01-01'),
		array('start' => '', 'end' => '2026-01-01'),
		'bad-row',
	),
);
$expected_schedule = array(
	'open_days' => array(0, 4),
	'open_year_round' => false,
	'seasons' => array(
		array('start' => '2026-03-08', 'end' => '2026-11-01'),
		array('start' => '1970-01-01', 'end' => '1970-01-01'),
	),
);
foreach (array('UTC', 'America/Chicago') as $runtime_timezone) {
	date_default_timezone_set($runtime_timezone);
	g14_same($expected_schedule, bvmgr_get_venue_schedule_config(7), 'Extracted venue schedule behavior changed: ' . $runtime_timezone);
	g14_same(true, bvmgr_venue_is_open_on_date(7, '2026-03-08'), 'DST-start Sunday must remain open: ' . $runtime_timezone);
	g14_same(true, bvmgr_venue_is_open_on_date(7, '2026-11-01'), 'DST-end Sunday must remain open: ' . $runtime_timezone);
	g14_same(true, bvmgr_venue_is_open_on_date(7, '1970-01-01'), 'Epoch Thursday must retain its weekday: ' . $runtime_timezone);
	g14_same(true, bvmgr_venue_is_open_on_date(7, 'not-a-date'), 'Invalid date must retain the historical epoch-Thursday path: ' . $runtime_timezone);
	g14_same(false, bvmgr_venue_is_open_on_date(7, '2026-03-07'), 'Saturday closure decision changed: ' . $runtime_timezone);
}

$season_function = g14_extract_function($mirror['includes/schedule/season-dates.php'], 'vms_sch_season_get_blackout_notes_map');
g14_same(
	$season_function,
	g14_extract_function($sources['shadow']['includes/schedule/season-dates.php'], 'vms_sch_season_get_blackout_notes_map'),
	'Season blackout range function must match across mirror/shadow.'
);
eval($season_function);

$GLOBALS['g14_season_rules'] = array(
	9 => array(
		array('enabled' => 1, 'type' => 'blackout_range', 'start_ymd' => '2026-03-07', 'end_ymd' => '2026-03-10', 'note' => 'Venue DST'),
	),
	0 => array(
		array('enabled' => 1, 'type' => 'blackout_range', 'start_ymd' => '2026-03-08', 'end_ymd' => '2026-03-09', 'note' => 'Global DST'),
	),
);
$expected_dst_start = array(
	'2026-03-07' => array('Venue DST'),
	'2026-03-08' => array('Venue DST', 'Global DST'),
	'2026-03-09' => array('Venue DST', 'Global DST'),
	'2026-03-10' => array('Venue DST'),
);
foreach (array('UTC', 'America/Chicago') as $runtime_timezone) {
	date_default_timezone_set($runtime_timezone);
	g14_same($expected_dst_start, vms_sch_season_get_blackout_notes_map(9, '2026-03-07', '2026-03-10'), 'DST-start range keys changed: ' . $runtime_timezone);
}

$GLOBALS['g14_season_rules'] = array(
	9 => array(
		array('enabled' => 1, 'type' => 'blackout_range', 'start_ymd' => '2026-10-31', 'end_ymd' => '2026-11-02', 'note' => 'DST end'),
	),
	0 => array(),
);
$expected_dst_end = array(
	'2026-10-31' => array('DST end'),
	'2026-11-01' => array('DST end'),
	'2026-11-02' => array('DST end'),
);
foreach (array('UTC', 'America/Chicago') as $runtime_timezone) {
	date_default_timezone_set($runtime_timezone);
	g14_same($expected_dst_end, vms_sch_season_get_blackout_notes_map(9, '2026-10-31', '2026-11-02'), 'DST-end range keys changed: ' . $runtime_timezone);
}
g14_same(array(), vms_sch_season_get_blackout_notes_map(9, 'invalid', '2026-11-02'), 'Invalid season window must fail closed.');
$GLOBALS['g14_season_rules'][9] = array(
	array('enabled' => 1, 'type' => 'blackout_range', 'start_ymd' => '1970-01-01', 'end_ymd' => '1970-01-02', 'note' => 'Epoch'),
);
date_default_timezone_set('UTC');
g14_same(array(), vms_sch_season_get_blackout_notes_map(9, '1970-01-01', '1970-01-02'), 'Zero epoch timestamp must retain the historical skipped-range behavior.');
date_default_timezone_set('America/Chicago');
g14_same(
	array('1970-01-01' => array('Epoch'), '1970-01-02' => array('Epoch')),
	vms_sch_season_get_blackout_notes_map(9, '1970-01-01', '1970-01-02'),
	'Non-UTC epoch date strings must retain their historical nonzero range behavior.'
);

$cli_method = g14_extract_braced_declaration($mirror['includes/core/cli/state-of-range.php'], 'private function format_timestamp(');
$cli_function = preg_replace('/^private function format_timestamp/', 'function g14_cli_format_timestamp', $cli_method, 1, $cli_replace_count);
g14_same(1, $cli_replace_count, 'CLI fallback method extraction changed.');
if (!is_string($cli_function)) {
	throw new RuntimeException('Unable to transform CLI fallback method.');
}
eval($cli_function);
g14_assert(!function_exists('bvmgr_ticket_integrity_format_datetime'), 'CLI test must exercise the direct fallback, not the normal helper branch.');
g14_same('Never', g14_cli_format_timestamp(0), 'CLI zero timestamp behavior changed.');
$GLOBALS['g14_site_timezone'] = new DateTimeZone('UTC');
g14_same('2026-03-08 05:30:00', g14_cli_format_timestamp($fixed_timestamp), 'CLI UTC fallback output changed.');
$GLOBALS['g14_site_timezone'] = new DateTimeZone('America/Chicago');
g14_same('2026-03-07 23:30:00', g14_cli_format_timestamp($fixed_timestamp), 'CLI non-UTC fallback must use the site timezone.');
g14_same(
	wp_date('Y-m-d H:i:s', $fixed_timestamp, wp_timezone()),
	g14_cli_format_timestamp($fixed_timestamp),
	'CLI fallback must align with the normal bvmgr_ticket_integrity_format_datetime() site-local timezone contract.'
);
$exercised_occurrences['CLI-label'] = true;

$expected_exercised = array_keys($occurrences);
$actual_exercised = array_keys($exercised_occurrences);
sort($expected_exercised);
sort($actual_exercised);
g14_same($expected_exercised, $actual_exercised, 'Every exact G14 date boundary must execute behaviorally.');
date_default_timezone_set('UTC');

fwrite(STDOUT, "PASS: G14 exact eleven-row inventory, UTC/site-local date behavior, DST/invalid/epoch contracts, immutable projections, and mirror/shadow parity are covered.\n");
