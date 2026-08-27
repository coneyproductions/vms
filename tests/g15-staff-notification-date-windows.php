<?php

declare(strict_types=1);

$g15_plugin_root = dirname(__DIR__);
$g15_shadow_root = dirname(dirname($g15_plugin_root)) . '/vms';

function g15_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function g15_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function g15_replace_once(string $search, string $replacement, string $subject, string $message): string
{
    $count = 0;
    $result = str_replace($search, $replacement, $subject, $count);
    g15_assert_same(1, $count, $message);
    return $result;
}

$g15_relative_file = 'includes/modules/staff-tasks/notifications.php';
$g15_mirror_file = $g15_plugin_root . '/' . $g15_relative_file;
$g15_shadow_file = $g15_shadow_root . '/' . $g15_relative_file;
$g15_artifact_file = '/tmp/wporg-dbzero-g14.qulnlt/plugin-check.strict.json';
$g15_artifact_hash = 'c5fe4d23b3cdf632f239632a23f2c58f9ccf7b8e293ff4b9e71f65101527aa17';
$g15_package_file = '/tmp/wporg-dbzero-g14.qulnlt/build/backstage-venue-manager-1.2.0-public-release.zip';
$g15_package_hash = 'fec238a519108c7013659b4114e69e9aad93c5c6f864551d4290737d30a609e5';
$g15_report_file = '/tmp/wporg-dbzero-g14.qulnlt/build/backstage-venue-manager-1.2.0-public-release.report.json';
$g15_pre_edit_hash = '10182f149f92eb6ee563d53ddae2c0c4e676754d499128a966bc8efaeaf2ce2d';
$g15_expected_staff_rows = array(
    '/privateincludes/modules/staff-tasks/notifications.php:147:17',
    '/privateincludes/modules/staff-tasks/notifications.php:234:11',
    '/privateincludes/modules/staff-tasks/notifications.php:237:11',
    '/privateincludes/modules/staff-tasks/notifications.php:239:10',
    '/privateincludes/modules/staff-tasks/notifications.php:252:12',
);
g15_assert_same(5, count($g15_expected_staff_rows), 'Embedded staff artifact inventory must contain exactly five rows.');

if (is_file($g15_artifact_file)) {
g15_assert_same($g15_artifact_hash, hash_file('sha256', $g15_artifact_file), 'Authoritative artifact hash must match.');

$g15_findings = json_decode((string) file_get_contents($g15_artifact_file), true, 512, JSON_THROW_ON_ERROR);
g15_assert_true(is_array($g15_findings), 'Authoritative artifact must decode to an array.');
g15_assert_same(181, count($g15_findings), 'Authoritative artifact total must remain pinned.');
g15_assert_same(139, count(array_filter($g15_findings, static fn (array $finding): bool => ($finding['type'] ?? '') === 'ERROR')), 'Authoritative error total must remain pinned.');
g15_assert_same(42, count(array_filter($g15_findings, static fn (array $finding): bool => ($finding['type'] ?? '') === 'WARNING')), 'Authoritative warning total must remain pinned.');
g15_assert_same(0, count(array_filter($g15_findings, static fn (array $finding): bool => str_starts_with((string) ($finding['code'] ?? ''), 'WordPress.DB.'))), 'Authoritative DB family must remain at zero.');
g15_assert_same(42, count(array_filter(
    $g15_findings,
    static fn (array $finding): bool => in_array(
        (string) ($finding['code'] ?? ''),
        array('WordPress.PHP.DevelopmentFunctions.error_log_error_log', 'WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace'),
        true
    )
)), 'Authoritative logging family must remain pinned at 42.');

g15_assert_true(is_file($g15_package_file), 'Authoritative package must exist.');
g15_assert_same($g15_package_hash, hash_file('sha256', $g15_package_file), 'Authoritative package hash must match.');
$g15_report = json_decode((string) file_get_contents($g15_report_file), true, 512, JSON_THROW_ON_ERROR);
g15_assert_same('2c8f790b9128d547b8bc0a27a714253fb6671bea', $g15_report['git']['commit'] ?? '', 'Authoritative package source commit must match the isolated base.');
g15_assert_same($g15_package_hash, $g15_report['artifact']['sha256'] ?? '', 'Authoritative package report must pin the same package hash.');

$g15_date_findings = array_values(array_filter(
    $g15_findings,
    static fn (array $finding): bool => ($finding['code'] ?? '') === 'WordPress.DateTime.RestrictedFunctions.date_date'
));
g15_assert_same(14, count($g15_date_findings), 'Authoritative artifact must contain the expected 14 date findings.');

$g15_staff_rows = array_map(
    static fn (array $finding): string => ($finding['file'] ?? '') . ':' . ($finding['line'] ?? 0) . ':' . ($finding['column'] ?? 0),
    array_values(array_filter(
        $g15_date_findings,
        static fn (array $finding): bool => str_ends_with((string) ($finding['file'] ?? ''), 'includes/modules/staff-tasks/notifications.php')
    ))
);
g15_assert_same(
    $g15_expected_staff_rows,
    $g15_staff_rows,
    'Authoritative artifact must identify exactly the five owned staff rows.'
);
}

$g15_sources = array(
    'mirror' => file_get_contents($g15_mirror_file),
    'shadow' => file_get_contents($g15_shadow_file),
);
g15_assert_true(is_string($g15_sources['mirror']), 'Mirror notification source must be readable.');
g15_assert_true(is_string($g15_sources['shadow']), 'Shadow notification source must be readable.');
g15_assert_same($g15_sources['mirror'], $g15_sources['shadow'], 'Mirror and shadow notification source must match exactly.');

$g15_helper_block = <<<'PHP'
if (!function_exists('vms_tasks_notification_format_floating_local_datetime')) {
	function vms_tasks_notification_format_floating_local_datetime(string $format, string $expression): string
	{
		$utc = new DateTimeZone('UTC');
		try {
			$datetime = trim($expression) === ''
				? new DateTimeImmutable('@0')
				: new DateTimeImmutable($expression, $utc);
		} catch (Exception) {
			$datetime = new DateTimeImmutable('@0');
		}

		return $datetime->setTimezone($utc)->format($format);
	}
}
PHP;

$g15_replacements = array(
    "vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', \$now . ' +' . \$window_minutes . ' minutes')" => "date('Y-m-d H:i:s', strtotime(\$now . ' +' . \$window_minutes . ' minutes'))",
    "vms_tasks_notification_format_floating_local_datetime('Y-m-d 23:59:59', \$now)" => "date('Y-m-d 23:59:59', strtotime(\$now))",
    "vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', \$now . ' +7 days')" => "date('Y-m-d H:i:s', strtotime(\$now . ' +7 days'))",
    "vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', \$now . ' +3 days')" => "date('Y-m-d H:i:s', strtotime(\$now . ' +3 days'))",
    "vms_tasks_notification_format_floating_local_datetime('Y-m-d', \$now)" => "date('Y-m-d', strtotime(\$now))",
);

foreach ($g15_sources as $g15_tree => $g15_source) {
    g15_assert_same(0, preg_match_all('/(?<![A-Za-z0-9_])date\s*\(/', $g15_source), $g15_tree . ' must contain zero native date() calls.');
    g15_assert_same(0, preg_match_all('/phpcs:(?:ignore|disable)[^\n]*WordPress\.DateTime/i', $g15_source), $g15_tree . ' must not suppress DateTime findings.');
    g15_assert_same(7, substr_count($g15_source, 'vms_tasks_notification_format_floating_local_datetime'), $g15_tree . ' must contain one guard, one helper definition, and five calls.');
    g15_assert_same(1, substr_count($g15_source, "new DateTimeZone('UTC')"), $g15_tree . ' helper must use one explicit UTC zone.');
    g15_assert_same(2, substr_count($g15_source, "new DateTimeImmutable('@0')"), $g15_tree . ' helper must preserve blank and invalid epoch fallbacks.');
    g15_assert_same(1, substr_count($g15_source, 'new DateTimeImmutable($expression, $utc)'), $g15_tree . ' helper must parse floating expressions in explicit UTC.');
    g15_assert_same(1, substr_count($g15_source, 'return $datetime->setTimezone($utc)->format($format);'), $g15_tree . ' helper must format in explicit UTC.');
    g15_assert_same(0, substr_count($g15_source, 'wp_date('), $g15_tree . ' must not introduce a site-zone double shift.');
    g15_assert_same(0, substr_count($g15_source, 'date_default_timezone_set('), $g15_tree . ' must not mutate process timezone.');

    $g15_projection = g15_replace_once($g15_helper_block . "\n\n", '', $g15_source, $g15_tree . ' helper block must be removable exactly once.');
    foreach ($g15_replacements as $g15_current => $g15_historical) {
        g15_assert_same(1, substr_count($g15_source, $g15_current), $g15_tree . ' must contain each replacement exactly once.');
        g15_assert_same(0, substr_count($g15_source, $g15_historical), $g15_tree . ' must remove each historical date expression.');
        $g15_projection = g15_replace_once($g15_current, $g15_historical, $g15_projection, $g15_tree . ' projection must reverse each owned replacement once.');
    }
    g15_assert_same($g15_pre_edit_hash, hash('sha256', $g15_projection), $g15_tree . ' immutable pre-edit projection hash must match.');

    $g15_mutation = g15_replace_once("'limit' => 1000", "'limit' => 999", $g15_projection, $g15_tree . ' mutation anchor must occur once.');
    g15_assert_true(hash('sha256', $g15_mutation) !== $g15_pre_edit_hash, $g15_tree . ' non-date mutation must fail projection proof.');
}

defined('ABSPATH') || define('ABSPATH', $g15_plugin_root . '/');
defined('MINUTE_IN_SECONDS') || define('MINUTE_IN_SECONDS', 60);

$GLOBALS['g15_settings'] = array();
$GLOBALS['g15_now'] = '';
$GLOBALS['g15_rows'] = array();
$GLOBALS['g15_queries'] = array();
$GLOBALS['g15_options'] = array();
$GLOBALS['g15_updates'] = array();
$GLOBALS['g15_notifications'] = array();
$GLOBALS['g15_actions'] = array();
$GLOBALS['g15_logs'] = array();

function g15_reset_runtime(): void
{
    $GLOBALS['g15_settings'] = array();
    $GLOBALS['g15_now'] = '';
    $GLOBALS['g15_rows'] = array();
    $GLOBALS['g15_queries'] = array();
    $GLOBALS['g15_options'] = array();
    $GLOBALS['g15_updates'] = array();
    $GLOBALS['g15_notifications'] = array();
    $GLOBALS['g15_actions'] = array();
    $GLOBALS['g15_logs'] = array();
}

function add_action(string $hook, callable|string|array $callback, int $priority = 10, int $accepted_args = 1): bool
{
    return true;
}

function add_filter(string $hook, callable|string|array $callback, int $priority = 10, int $accepted_args = 1): bool
{
    return true;
}

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function sanitize_key(string $key): string
{
    return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $key));
}

function absint(mixed $value): int
{
    return abs((int) $value);
}

function admin_url(string $path = ''): string
{
    return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function add_query_arg(array $args, string $url): string
{
    return $url . '?' . http_build_query($args);
}

function get_the_title(int $post_id): string
{
    return 'Event ' . $post_id;
}

function do_action(string $hook, mixed ...$args): void
{
    $GLOBALS['g15_actions'][] = array($hook, $args);
}

function wp_json_encode(mixed $value): string|false
{
    return json_encode($value);
}

function vms_tasks_get_settings(): array
{
    return $GLOBALS['g15_settings'];
}

function vms_tasks_now_local_mysql(): string
{
    return $GLOBALS['g15_now'];
}

function vms_tasks_get_instances(array $args): array
{
    $GLOBALS['g15_queries'][] = $args;
    return $GLOBALS['g15_rows'];
}

function vms_tasks_has_task_action_log(int $instance_id, string $action): bool
{
    return false;
}

function vms_tasks_log_task_action(int $instance_id, string $action, mixed $actor_id = null, ?string $details = null): void
{
    $GLOBALS['g15_logs'][] = array($instance_id, $action, $actor_id, $details);
}

function bvmgr_notify_user(int $user_id, string $event_key, string $template_key, array $vars): void
{
    $GLOBALS['g15_notifications'][] = array($user_id, $event_key, $template_key, $vars);
}

function get_option(string $name, mixed $default = false): mixed
{
    return $GLOBALS['g15_options'][$name] ?? $default;
}

function update_option(string $name, mixed $value, bool $autoload = true): bool
{
    $GLOBALS['g15_options'][$name] = $value;
    $GLOBALS['g15_updates'][] = array($name, $value, $autoload);
    return true;
}

require $g15_mirror_file;

$g15_original_timezone = date_default_timezone_get();
date_default_timezone_set('UTC');

$g15_legacy_utc = static function (string $format, string $expression): string {
    $timestamp = strtotime($expression);
    return gmdate($format, $timestamp === false ? 0 : $timestamp);
};

$g15_legacy_cases = array(
    array('Y-m-d H:i:s', '2026-03-08 01:30:00 +120 minutes'),
    array('Y-m-d H:i:s', '2026-11-01 00:30:00 +120 minutes'),
    array('Y-m-d 23:59:59', '2026-06-18 09:15:00'),
    array('Y-m-d H:i:s', '2026-06-18 09:15:00 +7 days'),
    array('Y-m-d H:i:s', '2026-06-18 09:15:00 +3 days'),
    array('Y-m-d', '2026-06-18 09:15:00'),
    array('Y-m-d H:i:s', ''),
    array('Y-m-d H:i:s', 'not-a-date'),
    array('Y-m-d H:i:s', '@0'),
    array('Y-m-d H:i:s', '1969-12-31 23:59:59'),
    array('Y-m-d H:i:s', '2026-02-30 10:00:00'),
    array('Y-m-d H:i:s', '2026-01-15 10:00:00-05:00 +120 minutes'),
);
foreach ($g15_legacy_cases as [$g15_format, $g15_expression]) {
    g15_assert_same(
        $g15_legacy_utc($g15_format, $g15_expression),
        vms_tasks_notification_format_floating_local_datetime($g15_format, $g15_expression),
        'Helper must preserve the supported WordPress-UTC result for ' . var_export($g15_expression, true) . '.'
    );
}

g15_assert_same('1970-01-01 00:00:00', vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', ''), 'Blank direct input must preserve the epoch fallback.');
g15_assert_same('1970-01-01 00:00:00', vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', 'not-a-date'), 'Invalid direct input must preserve the epoch fallback.');
g15_assert_same('1970-01-01 00:00:00', vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '@0'), 'Explicit epoch input must remain valid.');
g15_assert_same('1969-12-31 23:59:59', vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '1969-12-31 23:59:59'), 'Pre-epoch input must remain representable.');
g15_assert_same('2026-03-02 10:00:00', vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '2026-02-30 10:00:00'), 'Lenient calendar normalization must remain supported.');
g15_assert_same('2026-01-15 17:00:00', vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '2026-01-15 10:00:00-05:00 +120 minutes'), 'Explicit-offset input must normalize to UTC.');

foreach (array('UTC', 'America/Chicago', 'Asia/Tokyo') as $g15_runtime_timezone) {
    date_default_timezone_set($g15_runtime_timezone);
    g15_assert_same('2026-03-08 03:30:00', vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '2026-03-08 01:30:00 +120 minutes'), $g15_runtime_timezone . ' must preserve nominal spring-forward wall arithmetic.');
    g15_assert_same('2026-11-01 02:30:00', vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', '2026-11-01 00:30:00 +120 minutes'), $g15_runtime_timezone . ' must preserve nominal fall-back wall arithmetic.');
    g15_assert_same('1970-01-01 00:00:00', vms_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', 'invalid'), $g15_runtime_timezone . ' must preserve invalid-input epoch behavior.');
}

g15_assert_same('2026-06-18 23:59:59', vms_tasks_notification_digest_window_end('today', '2026-06-18 09:15:00'), 'Today digest must end at local-wall end of day.');
g15_assert_same('2026-06-25 09:15:00', vms_tasks_notification_digest_window_end('next7', '2026-06-18 09:15:00'), 'Next-seven digest must retain nominal wall time.');
g15_assert_same('2026-06-21 09:15:00', vms_tasks_notification_digest_window_end('next3', '2026-06-18 09:15:00'), 'Next-three digest must retain nominal wall time.');
g15_assert_same('2026-06-21 09:15:00', vms_tasks_notification_digest_window_end('unknown', '2026-06-18 09:15:00'), 'Unknown digest windows must retain the next-three default.');
g15_assert_same('1970-01-01 23:59:59', vms_tasks_notification_digest_window_end('today', ''), 'Blank today input must preserve epoch end-of-day behavior.');
g15_assert_same('1970-01-01 23:59:59', vms_tasks_notification_digest_window_end('today', 'invalid'), 'Invalid today input must preserve epoch end-of-day behavior.');
g15_assert_same('1970-01-08 00:00:00', vms_tasks_notification_digest_window_end('next7', '@0'), 'Next-seven must preserve explicit epoch-relative behavior.');

$g15_blank_window_started = time() + (7 * 86400) - 2;
$g15_blank_window_result = strtotime(vms_tasks_notification_digest_window_end('next7', '') . ' UTC');
$g15_blank_window_finished = time() + (7 * 86400) + 2;
g15_assert_true(is_int($g15_blank_window_result), 'Blank next-seven output must remain parseable.');
g15_assert_true($g15_blank_window_result >= $g15_blank_window_started && $g15_blank_window_result <= $g15_blank_window_finished, 'Blank next-seven must preserve current-time-relative behavior.');

date_default_timezone_set($g15_original_timezone);

foreach (array('UTC', 'America/Chicago', 'Asia/Tokyo') as $g15_runtime_timezone) {
    g15_reset_runtime();
    date_default_timezone_set($g15_runtime_timezone);
    $GLOBALS['g15_settings'] = array('notify_due_soon_alerts' => true);
    $GLOBALS['g15_now'] = '2026-03-08 01:30:00';
    vms_tasks_notification_scan_due_soon();
    g15_assert_same(
        array(array(
            'status' => 'open',
            'due_after' => '2026-03-08 01:30:00',
            'due_before' => '2026-03-08 03:30:00',
            'limit' => 500,
        )),
        $GLOBALS['g15_queries'],
        $g15_runtime_timezone . ' due-soon scan must preserve exact query arguments.'
    );
}
date_default_timezone_set('UTC');

g15_reset_runtime();
$GLOBALS['g15_settings'] = array(
    'notify_daily_digest' => true,
    'notify_digest_time' => '08:00',
);
$GLOBALS['g15_now'] = '2026-06-18 07:59:59';
vms_tasks_notification_run_digest();
g15_assert_same(array(), $GLOBALS['g15_queries'], 'Digest must not query before its configured local-wall run time.');
g15_assert_same(array(), $GLOBALS['g15_updates'], 'Digest must not update its gate before the configured run time.');

$GLOBALS['g15_now'] = '2026-06-18 09:00:00';
$GLOBALS['g15_rows'] = array(array(
    'id' => 77,
    'event_id' => 501,
    'title' => 'Open house',
    'due_at_local' => '2026-06-20 18:00:00',
    'assignee_user_id' => 42,
));
vms_tasks_notification_run_digest();

g15_assert_same(
    array(array(
        'status' => 'open',
        'due_after' => '2026-06-18 09:00:00',
        'due_before' => '2026-06-21 09:00:00',
        'limit' => 1000,
    )),
    $GLOBALS['g15_queries'],
    'Digest must preserve its exact default-next-three query arguments.'
);
g15_assert_same(
    array(array('vms_tasks_digest_last_run_day', '2026-06-18', false)),
    $GLOBALS['g15_updates'],
    'Digest must record the exact once-per-date gate without autoload.'
);

$g15_task_context = array(
    'task_instance_id' => 77,
    'task_title' => 'Open house',
    'due_datetime' => '2026-06-20 18:00:00',
    'event_id' => 501,
    'event_context' => 'Event 501',
    'task_url' => 'https://example.test/wp-admin/admin.php?page=vms-tasks&task_instance_id=77&assignee_user_id=42',
);
$g15_expected_digest_payload = array(
    'recipient_user_id' => 42,
    'window' => 'next3',
    'due_before' => '2026-06-21 09:00:00',
    'task_count' => 1,
    'tasks' => array($g15_task_context),
    'task_url' => 'https://example.test/wp-admin/admin.php?page=vms-tasks&assignee_user_id=42',
);
g15_assert_same(
    array(array(42, 'vms_task_digest', 'staff_tasks.task_digest_daily', $g15_expected_digest_payload)),
    $GLOBALS['g15_notifications'],
    'Digest must preserve its exact recipient and payload contract.'
);

vms_tasks_notification_run_digest();
g15_assert_same(1, count($GLOBALS['g15_queries']), 'A second digest on the same local date must not query again.');
g15_assert_same(1, count($GLOBALS['g15_updates']), 'A second digest on the same local date must not rewrite its gate.');
g15_assert_same(1, count($GLOBALS['g15_notifications']), 'A second digest on the same local date must not notify again.');

date_default_timezone_set($g15_original_timezone);
fwrite(STDOUT, "PASS: G15 staff notification date windows\n");
