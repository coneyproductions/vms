<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);

final class WP_User
{
	public int $ID;

	public function __construct(int $id)
	{
		$this->ID = $id;
	}
}

function t1a_live_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function t1a_live_same($expected, $actual, string $message): void
{
	t1a_live_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key(string $value): string
{
	return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?? '';
}

function sanitize_text_field(string $value): string
{
	return trim(strip_tags($value));
}

function apply_filters(string $hook, $value, ...$args)
{
	unset($hook, $args);
	return $value;
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function vms_meta_key(string $entity, string $field): string
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
	if ($post_id === 501 && $key === '_vms_venue_id') {
		return 20;
	}
	return $single ? '' : array();
}

function get_post_type(int $post_id): string
{
	return $post_id === 501 ? 'vms_event_plan' : 'post';
}

function get_the_title(int $post_id): string
{
	return 'Legacy Event ' . $post_id;
}

function vms_event_plan_start_datetime(int $post_id): ?DateTimeImmutable
{
	return $post_id === 501 ? new DateTimeImmutable('2026-09-18 18:00:00', wp_timezone()) : null;
}

function vms_event_plan_end_datetime(int $post_id): ?DateTimeImmutable
{
	return $post_id === 501 ? new DateTimeImmutable('2026-09-18 22:00:00', wp_timezone()) : null;
}

function vms_event_plan_get_status(int $post_id, string $context = ''): string
{
	unset($post_id, $context);
	return 'published';
}

function vms_staffing_role_map_by_id(bool $include_inactive = true): array
{
	unset($include_inactive);
	return array(7 => array('slug' => 'venue-operations', 'name' => 'Venue Operations'));
}

function vms_staffing_get_event_slots(int $event_plan_id, bool $include_canceled = false): array
{
	unset($event_plan_id, $include_canceled);
	return array(array(
		'slot_id' => 100,
		'role_id' => 7,
		'assignments' => array(array('assignment_id' => 1, 'staff_id' => 101, 'status' => 'confirmed')),
	));
}

function vms_staffing_resolve_slot_window(int $event_plan_id, array $slot): array
{
	unset($event_plan_id, $slot);
	$start = vms_event_plan_start_datetime(501);
	$end = vms_event_plan_end_datetime(501);
	return array('start_ts' => $start?->getTimestamp(), 'end_ts' => $end?->getTimestamp(), 'start_local' => $start, 'end_local' => $end);
}

function vms_staffing_get_staff_user(int $staff_id): ?WP_User
{
	return $staff_id === 101 ? new WP_User(11) : null;
}

$root = dirname(__DIR__);
$plugins_root = dirname($root, 2) . '/public/wp-content/plugins';
$live = $plugins_root . '/vms';
$canonical = $plugins_root . '/backstage-venue-manager';
foreach (array('operational-context.php', 'operational-scope.php', 'staffing-dispatch.php', 'ecc-extensions.php') as $file) {
	$mirror_path = $root . '/includes/core/' . $file;
	$live_path = $live . '/includes/core/' . $file;
	$canonical_path = $canonical . '/includes/core/' . $file;
	t1a_live_assert(is_file($live_path), 'Live local Core contract file is missing: ' . $file);
	t1a_live_assert(is_file($canonical_path), 'Canonical active Core contract file is missing: ' . $file);
	t1a_live_same(hash_file('sha256', $mirror_path), hash_file('sha256', $live_path), 'Mirror/live Core contract file drifted: ' . $file);
	t1a_live_same(hash_file('sha256', $mirror_path), hash_file('sha256', $canonical_path), 'Mirror/canonical Core contract file drifted: ' . $file);
}

require $root . '/includes/core/operational-context.php';
require $root . '/includes/core/staffing-dispatch.php';

$context = bvmgr_resolve_operational_event_context(20, '2026-09-18 20:00', array('candidate_ids' => array(501)));
t1a_live_same('single', $context['status'], 'Legacy local Event Plan helpers did not resolve operational context.');
t1a_live_same('event_plan_datetime', $context['candidates'][0]['window_source'], 'Legacy local occurrence helper source changed.');

$dispatch = bvmgr_staffing_resolve_dispatch_assignments(501, 7);
t1a_live_same(array(11), $dispatch['eligible_user_ids'], 'Legacy local staffing helpers did not resolve a dispatchable user.');
t1a_live_same(1, $dispatch['assignments'][0]['assignment_id'], 'Legacy local staffing assignment identity changed.');

$live_loader = (string) file_get_contents($live . '/includes/core/load.php');
$canonical_loader = (string) file_get_contents($canonical . '/includes/core/load.php');
foreach (array('operational-context.php', 'operational-scope.php', 'staffing-dispatch.php', 'ecc-extensions.php') as $file) {
	t1a_live_assert(strpos($live_loader, "'/$file'") !== false, 'Live local Core loader is missing: ' . $file);
	t1a_live_assert(strpos($canonical_loader, "'/$file'") !== false, 'Canonical active Core loader is missing: ' . $file);
}
$live_ecc = (string) file_get_contents($live . '/includes/admin/event-command-center.php');
$live_dashboard_path = $live . '/includes/admin/event-command-center-dashboard.php';
$live_dashboard = is_file($live_dashboard_path) ? (string) file_get_contents($live_dashboard_path) : '';
$renderer_call = 'bvmgr_ecc_render_registered_extensions($plan_id, $payload)';
$mirror_ecc = (string) file_get_contents($root . '/includes/admin/event-command-center.php');
$mirror_dashboard = (string) file_get_contents($root . '/includes/admin/event-command-center-dashboard.php');
t1a_live_same(1, substr_count($mirror_ecc . $mirror_dashboard, $renderer_call), 'Mirror ECC extension renderer must be invoked exactly once.');
t1a_live_same(1, substr_count($live_ecc . $live_dashboard, $renderer_call), 'Live local ECC extension renderer must be invoked exactly once.');

$canonical_ecc = (string) file_get_contents($canonical . '/includes/admin/event-command-center.php');
$canonical_dashboard = (string) file_get_contents($canonical . '/includes/admin/event-command-center-dashboard.php');
t1a_live_same(1, substr_count($canonical_ecc . $canonical_dashboard, $renderer_call), 'Canonical active ECC extension renderer must be invoked exactly once.');

echo "Tranche 1A live compatibility tests passed.\n";
