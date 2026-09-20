<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

$GLOBALS['t1a_scope_filters'] = array();
$GLOBALS['t1a_scope_capabilities'] = array(11 => array('bvmgr_service_requests_view' => true));
$GLOBALS['t1a_scope_meta'] = array(501 => array('_vms_venue_id' => 20));
$GLOBALS['t1a_scope_current_user'] = 11;

function t1a_scope_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function t1a_scope_same($expected, $actual, string $message): void
{
	t1a_scope_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key(string $value): string
{
	return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?? '';
}

function user_can(int $user_id, string $capability): bool
{
	return !empty($GLOBALS['t1a_scope_capabilities'][$user_id][$capability]);
}

function get_current_user_id(): int
{
	return (int) $GLOBALS['t1a_scope_current_user'];
}

function get_post_type(int $post_id): string
{
	return $post_id === 501 ? 'vms_event_plan' : 'post';
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	return $GLOBALS['t1a_scope_meta'][$post_id][$key] ?? ($single ? '' : array());
}

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
	$GLOBALS['t1a_scope_filters'][$hook][$priority][] = array($callback, $accepted_args);
}

function apply_filters(string $hook, $value, ...$args)
{
	$callbacks = $GLOBALS['t1a_scope_filters'][$hook] ?? array();
	ksort($callbacks);
	foreach ($callbacks as $rows) {
		foreach ($rows as [$callback, $accepted_args]) {
			$value = $callback(...array_slice(array_merge(array($value), $args), 0, $accepted_args));
		}
	}
	return $value;
}

require dirname(__DIR__) . '/includes/core/operational-scope.php';

$allowed = bvmgr_operational_scope_decision(11, 'bvmgr_service_requests_view', 501);
t1a_scope_same(true, $allowed['allowed'], 'Capability-compatible default should remain allowed.');
t1a_scope_same(20, $allowed['venue_id'], 'Event Plan venue should populate an omitted venue scope.');
t1a_scope_same('capability_default', $allowed['reason'], 'Default compatibility reason changed.');

$missing = bvmgr_operational_scope_decision(12, 'bvmgr_service_requests_view', 501, 20);
t1a_scope_same(false, $missing['allowed'], 'Missing WordPress capability must deny.');
t1a_scope_same('missing_capability', $missing['reason'], 'Missing-capability reason changed.');

$mismatch = bvmgr_operational_scope_decision(11, 'bvmgr_service_requests_view', 501, 21);
t1a_scope_same(false, $mismatch['allowed'], 'Event/venue mismatch must deny.');
t1a_scope_same('event_venue_mismatch', $mismatch['reason'], 'Event/venue mismatch reason changed.');

$invalid = bvmgr_operational_scope_decision(11, 'bvmgr_service_requests_view', 999, 20);
t1a_scope_same(false, $invalid['allowed'], 'Non-Event-Plan scope must deny.');
t1a_scope_same('invalid_event_plan', $invalid['reason'], 'Invalid Event Plan reason changed.');

add_filter('bvmgr_operational_scope_decision', static function (array $decision, array $context): array {
	if (($context['surface'] ?? '') === 'event_command_center') {
		$decision['allowed'] = false;
	}
	return $decision;
}, 10, 2);

$narrowed = bvmgr_operational_scope_decision(11, 'bvmgr_service_requests_view', 501, 20, array('surface' => 'event_command_center'));
t1a_scope_same(false, $narrowed['allowed'], 'Operational scope filter failed to narrow an allowed capability.');
t1a_scope_same('outside_operational_scope', $narrowed['reason'], 'Narrowed scope reason changed.');
t1a_scope_same(false, bvmgr_current_user_can_operate_context('bvmgr_service_requests_view', 501, 20, array('surface' => 'event_command_center')), 'Current-user scope wrapper bypassed the centralized decision.');

$GLOBALS['t1a_scope_capabilities'][12]['bvmgr_service_requests_view'] = false;
add_filter('bvmgr_operational_scope_decision', static function (array $decision): array {
	$decision['allowed'] = true;
	return $decision;
});
$cannot_grant = bvmgr_operational_scope_decision(12, 'bvmgr_service_requests_view', 501, 20);
t1a_scope_same(false, $cannot_grant['allowed'], 'Scope extension point must never grant a missing base capability.');

echo "Tranche 1A operational-scope tests passed.\n";
