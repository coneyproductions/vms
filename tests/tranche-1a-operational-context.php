<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);

$GLOBALS['t1a_context_meta'] = array();
$GLOBALS['t1a_context_filters'] = array();
$GLOBALS['t1a_context_query'] = array();

function t1a_context_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function t1a_context_same($expected, $actual, string $message): void
{
	t1a_context_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key(string $value): string
{
	return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?? '';
}

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
	$GLOBALS['t1a_context_filters'][$hook][$priority][] = array($callback, $accepted_args);
}

function apply_filters(string $hook, $value, ...$args)
{
	$callbacks = $GLOBALS['t1a_context_filters'][$hook] ?? array();
	ksort($callbacks);
	foreach ($callbacks as $rows) {
		foreach ($rows as [$callback, $accepted_args]) {
			$value = $callback(...array_slice(array_merge(array($value), $args), 0, $accepted_args));
		}
	}
	return $value;
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function bvmgr_meta_key(string $entity, string $field): string
{
	unset($entity);
	$keys = array(
		'operational_start_local' => '_bvmgr_event_plan_operational_start_local',
		'operational_end_local' => '_bvmgr_event_plan_operational_end_local',
		'operational_start_offset_minutes' => '_bvmgr_event_plan_operational_start_offset_minutes',
		'operational_end_offset_minutes' => '_bvmgr_event_plan_operational_end_offset_minutes',
	);
	return $keys[$field] ?? '';
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	return $GLOBALS['t1a_context_meta'][$post_id][$key] ?? ($single ? '' : array());
}

function get_post_type(int $post_id): string
{
	return isset($GLOBALS['t1a_context_meta'][$post_id]) ? 'vms_event_plan' : 'post';
}

function get_the_title(int $post_id): string
{
	return 'Event ' . $post_id;
}

function get_posts(array $args): array
{
	$GLOBALS['t1a_context_query'] = $args;
	$venue_id = (int) ($args['meta_query'][0]['value'] ?? 0);
	return array_values(array_map(
		'intval',
		array_keys(array_filter($GLOBALS['t1a_context_meta'], static fn(array $row): bool => (int) ($row['_vms_venue_id'] ?? 0) === $venue_id))
	));
}

function bvmgr_event_plan_get_status(int $post_id, string $context = ''): string
{
	unset($context);
	return (string) get_post_meta($post_id, '_vms_event_plan_status', true);
}

function bvmgr_event_plan_start_datetime(int $post_id): ?DateTimeImmutable
{
	return $post_id === 10 ? new DateTimeImmutable('2026-09-18 14:00:00', wp_timezone()) : null;
}

function bvmgr_event_plan_end_datetime(int $post_id): ?DateTimeImmutable
{
	return $post_id === 10 ? new DateTimeImmutable('2026-09-18 17:00:00', wp_timezone()) : null;
}

$GLOBALS['t1a_context_meta'] = array(
	1 => array('_vms_venue_id' => 10, '_vms_event_plan_status' => 'published', '_vms_event_date' => '2026-09-18', '_vms_start_time' => '18:00', '_vms_end_time' => '22:00'),
	2 => array('_vms_venue_id' => 20, '_vms_event_plan_status' => 'ready', '_vms_event_date' => '2026-09-18', '_vms_start_time' => '23:00', '_vms_end_time' => '02:00'),
	3 => array('_vms_venue_id' => 30, '_vms_event_plan_status' => 'confirmed', '_vms_event_date' => '2026-09-18', '_vms_start_time' => '17:00', '_vms_end_time' => '21:00'),
	4 => array('_vms_venue_id' => 30, '_vms_event_plan_status' => 'published', '_vms_event_date' => '2026-09-18', '_vms_start_time' => '19:00', '_vms_end_time' => '23:00'),
	5 => array('_vms_venue_id' => 40, '_vms_event_plan_status' => 'cancelled', '_vms_event_date' => '2026-09-18', '_vms_start_time' => '18:00', '_vms_end_time' => '22:00'),
	6 => array('_vms_venue_id' => 50, '_vms_event_plan_status' => 'published', '_vms_event_date' => '2026-09-18', '_vms_start_time' => '18:00'),
	7 => array(
		'_vms_venue_id' => 60,
		'_vms_event_plan_status' => 'ready',
		'_vms_event_date' => '2026-09-18',
		'_vms_start_time' => '18:00',
		'_vms_end_time' => '22:00',
		'_bvmgr_event_plan_operational_start_offset_minutes' => -120,
		'_bvmgr_event_plan_operational_end_offset_minutes' => 60,
	),
	8 => array(
		'_vms_venue_id' => 70,
		'_vms_event_plan_status' => 'published',
		'_vms_event_date' => '2026-09-18',
		'_vms_start_time' => '18:00',
		'_vms_end_time' => '22:00',
		'_bvmgr_event_plan_operational_start_local' => '2026-09-18 15:30:00',
		'_bvmgr_event_plan_operational_end_local' => '2026-09-19 01:15:00',
	),
	9 => array(
		'_vms_venue_id' => 80,
		'_vms_event_plan_status' => 'published',
		'_vms_event_date' => '2026-09-18',
		'_vms_start_time' => '18:00',
		'_vms_end_time' => '22:00',
		'_bvmgr_event_plan_operational_start_local' => 'not-a-date',
	),
	10 => array('_vms_venue_id' => 90, '_vms_event_plan_status' => 'published'),
);

require dirname(__DIR__) . '/includes/core/operational-context.php';

$keys = bvmgr_operational_window_meta_keys();
t1a_context_same('_bvmgr_event_plan_operational_start_local', $keys['start_local'], 'Canonical start metadata key changed.');

$single = bvmgr_resolve_operational_event_context(10, '2026-09-18 18:00', array('candidate_ids' => array(1)));
t1a_context_same('single', $single['status'], 'Window start must be inclusive.');
t1a_context_same(1, $single['event_plan_id'], 'Single resolution returned the wrong Event Plan.');
t1a_context_same('none', bvmgr_resolve_operational_event_context(10, '2026-09-18 22:00', array('candidate_ids' => array(1)))['status'], 'Window end must be exclusive.');
t1a_context_same('none', bvmgr_resolve_operational_event_context(10, '2026-09-18 17:59', array('candidate_ids' => array(1)))['status'], 'Pre-window timestamp must not resolve.');

$overnight = bvmgr_resolve_operational_event_context(20, '2026-09-19 01:00', array('candidate_ids' => array(2)));
t1a_context_same('single', $overnight['status'], 'Overnight Event Plan did not resolve after midnight.');
t1a_context_same('2026-09-19 02:00:00', $overnight['candidates'][0]['operational_end_local'], 'Overnight end boundary changed.');

$ambiguous = bvmgr_resolve_operational_event_context(30, '2026-09-18 20:00', array('candidate_ids' => array(4, 3)));
t1a_context_same('ambiguous', $ambiguous['status'], 'Overlapping events must be ambiguous.');
t1a_context_same(0, $ambiguous['event_plan_id'], 'Ambiguous resolution must not choose an Event Plan.');
t1a_context_same(array(3, 4), $ambiguous['event_plan_ids'], 'Ambiguous results must be deterministic by window start and ID.');

add_filter('bvmgr_operational_event_context_result', static function (array $result, int $venue_id): array {
	if ($venue_id === 30) {
		$result['status'] = 'single';
		$result['event_plan_id'] = 3;
		$result['consumer_marker'] = 'present';
	}
	return $result;
}, 10, 2);
$guarded_ambiguous = bvmgr_resolve_operational_event_context(30, '2026-09-18 20:00', array('candidate_ids' => array(3, 4)));
t1a_context_same('ambiguous', $guarded_ambiguous['status'], 'Final context filter must not collapse ambiguity.');
t1a_context_same(0, $guarded_ambiguous['event_plan_id'], 'Final context filter must not choose an ambiguous Event Plan.');
t1a_context_same('present', $guarded_ambiguous['consumer_marker'], 'Final context filter should retain non-canonical consumer data.');

t1a_context_same('none', bvmgr_resolve_operational_event_context(40, '2026-09-18 20:00', array('candidate_ids' => array(5)))['status'], 'Cancelled Event Plans must remain excluded.');
t1a_context_same('incomplete_event_timing', bvmgr_event_plan_operational_window(6)['reason'], 'Incomplete timing must return an explicit reason.');

$offset_window = bvmgr_event_plan_operational_window(7);
t1a_context_same('event_schedule_offsets', $offset_window['source'], 'Offset-based source changed.');
t1a_context_same('2026-09-18 16:00:00', $offset_window['start_local'], 'Setup offset was not applied.');
t1a_context_same('2026-09-18 23:00:00', $offset_window['end_local'], 'Teardown offset was not applied.');

$explicit_window = bvmgr_event_plan_operational_window(8);
t1a_context_same('explicit_meta', $explicit_window['source'], 'Explicit operational bounds must identify their source.');
t1a_context_same('2026-09-19 01:15:00', $explicit_window['end_local'], 'Explicit overnight bound changed.');
t1a_context_same('invalid_explicit_start', bvmgr_event_plan_operational_window(9)['reason'], 'Malformed explicit metadata must fail closed.');

$stored_datetime_window = bvmgr_event_plan_operational_window(10);
t1a_context_same('event_plan_datetime', $stored_datetime_window['source'], 'Existing Event Plan datetime metadata must remain the preferred occurrence source.');
t1a_context_same('2026-09-18 14:00:00', $stored_datetime_window['start_local'], 'Existing Event Plan datetime start changed.');

$queried = bvmgr_resolve_operational_event_context(10, '2026-09-18 20:00');
t1a_context_same('single', $queried['status'], 'Venue-scoped query path did not resolve the Event Plan.');
t1a_context_same(-1, $GLOBALS['t1a_context_query']['posts_per_page'], 'Canonical venue resolution must not silently truncate candidate Event Plans.');

echo "Tranche 1A operational-context tests passed.\n";
