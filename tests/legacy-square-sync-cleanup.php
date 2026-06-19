<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_unschedule_hook_calls'] = array();
$GLOBALS['vms_test_cleared_hooks'] = array();
$GLOBALS['vms_test_wp_cron'] = array();
$GLOBALS['vms_test_is_admin'] = false;

function vms_legacy_square_cleanup_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($priority, $acceptedArgs);
	$GLOBALS['vms_test_actions'][$hook][] = $callback;
	return true;
}

function get_option(string $key, $default = '')
{
	return $GLOBALS['vms_test_options'][$key] ?? $default;
}

function update_option(string $key, $value, bool $autoload = false): bool
{
	unset($autoload);
	$GLOBALS['vms_test_options'][$key] = $value;
	return true;
}

function flush_rewrite_rules(): void
{
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_is_admin']);
}

function _get_cron_array(): array
{
	return $GLOBALS['vms_test_wp_cron'];
}

function vms_test_remove_cron_entries(string $hook, ?array $targetArgs = null): int
{
	$removed = 0;
	foreach ((array) $GLOBALS['vms_test_wp_cron'] as $timestamp => $events) {
		if (!is_array($events)) {
			continue;
		}

		foreach ($events as $scheduledHook => $instances) {
			if ((string) $scheduledHook !== $hook || !is_array($instances)) {
				continue;
			}

			foreach ($instances as $signature => $instance) {
				$args = isset($instance['args']) && is_array($instance['args']) ? array_values($instance['args']) : array();
				if ($targetArgs !== null && $args !== $targetArgs) {
					continue;
				}

				unset($GLOBALS['vms_test_wp_cron'][$timestamp][$scheduledHook][$signature]);
				$removed++;
			}

			if (empty($GLOBALS['vms_test_wp_cron'][$timestamp][$scheduledHook])) {
				unset($GLOBALS['vms_test_wp_cron'][$timestamp][$scheduledHook]);
			}
		}

		if (empty($GLOBALS['vms_test_wp_cron'][$timestamp])) {
			unset($GLOBALS['vms_test_wp_cron'][$timestamp]);
		}
	}

	return $removed;
}

function wp_unschedule_hook(string $hook): int
{
	$GLOBALS['vms_test_unschedule_hook_calls'][] = $hook;
	return vms_test_remove_cron_entries($hook);
}

function wp_clear_scheduled_hook(string $hook, array $args = array()): int
{
	$GLOBALS['vms_test_cleared_hooks'][] = array(
		'hook' => $hook,
		'args' => $args,
	);

	return vms_test_remove_cron_entries($hook, $args === array() ? array() : array_values($args));
}

final class VmsLegacySquareCleanupFakeStore
{
	public array $actions = array();
	public array $canceled_ids = array();
	public array $deleted_ids = array();

	public function __construct(array $actions)
	{
		$this->actions = $actions;
	}

	public function query_actions(array $args): array
	{
		$hook = (string) ($args['hook'] ?? '');
		$status = (string) ($args['status'] ?? '');
		$perPage = max(1, (int) ($args['per_page'] ?? 50));

		$matches = array();
		foreach ($this->actions as $actionId => $action) {
			if ((string) ($action['hook'] ?? '') !== $hook) {
				continue;
			}
			if ((string) ($action['status'] ?? '') !== $status) {
				continue;
			}

			$matches[] = (int) $actionId;
			if (count($matches) >= $perPage) {
				break;
			}
		}

		return $matches;
	}

	public function cancel_action(int $actionId): void
	{
		if (!isset($this->actions[$actionId])) {
			return;
		}

		$this->actions[$actionId]['status'] = 'canceled';
		$this->canceled_ids[] = $actionId;
	}

	public function delete_action(int $actionId): void
	{
		if (!isset($this->actions[$actionId])) {
			return;
		}

		unset($this->actions[$actionId]);
		$this->deleted_ids[] = $actionId;
	}
}

require dirname(__DIR__) . '/includes/activation.php';

vms_legacy_square_cleanup_assert(
	isset($GLOBALS['vms_test_actions']['vms_square_nightly_sync'][0])
	&& $GLOBALS['vms_test_actions']['vms_square_nightly_sync'][0] === 'vms_retired_square_nightly_sync_callback',
	'The retired Square nightly sync hook should register a no-op callback.'
);
vms_retired_square_nightly_sync_callback();

$GLOBALS['vms_test_wp_cron'] = array(
	100 => array(
		'vms_square_nightly_sync' => array(
			'no_args' => array('args' => array()),
			'with_args' => array('args' => array('venue' => 44)),
		),
		'vms_other_hook' => array(
			'other' => array('args' => array('keep' => true)),
		),
	),
);
$GLOBALS['vms_test_unschedule_hook_calls'] = array();
$GLOBALS['vms_test_cleared_hooks'] = array();
$cronPreferred = vms_cleanup_legacy_square_nightly_sync_wp_cron(vms_legacy_square_nightly_sync_hook_name());
vms_legacy_square_cleanup_assert($cronPreferred['method'] === 'wp_unschedule_hook', 'The preferred WP-Cron cleanup should use wp_unschedule_hook when available.');
vms_legacy_square_cleanup_assert($GLOBALS['vms_test_unschedule_hook_calls'] === array('vms_square_nightly_sync'), 'The preferred WP-Cron cleanup should unschedule the exact retired hook.');
vms_legacy_square_cleanup_assert(empty($GLOBALS['vms_test_cleared_hooks']), 'The preferred WP-Cron cleanup should not fall back to wp_clear_scheduled_hook when wp_unschedule_hook is available.');
vms_legacy_square_cleanup_assert(isset($GLOBALS['vms_test_wp_cron'][100]['vms_other_hook']), 'The preferred WP-Cron cleanup should leave unrelated cron hooks untouched.');

$GLOBALS['vms_test_wp_cron'] = array(
	200 => array(
		'vms_square_nightly_sync' => array(
			'no_args' => array('args' => array()),
			'with_args' => array('args' => array('venue' => 77)),
		),
		'vms_unrelated_hook' => array(
			'keep_me' => array('args' => array('safe' => true)),
		),
	),
);
$GLOBALS['vms_test_cleared_hooks'] = array();
$cronFallback = vms_cleanup_legacy_square_nightly_sync_wp_cron_fallback(vms_legacy_square_nightly_sync_hook_name());
vms_legacy_square_cleanup_assert($cronFallback['found'] === 2, 'Fallback WP-Cron cleanup should detect both empty-arg and non-empty-arg legacy entries.');
vms_legacy_square_cleanup_assert($cronFallback['cleared'] === 2, 'Fallback WP-Cron cleanup should clear every legacy argument variant.');
vms_legacy_square_cleanup_assert(count($GLOBALS['vms_test_cleared_hooks']) === 2, 'Fallback WP-Cron cleanup should clear each argument variant separately.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_wp_cron'][200]['vms_square_nightly_sync']), 'Fallback WP-Cron cleanup should remove the retired hook entries.');
vms_legacy_square_cleanup_assert(isset($GLOBALS['vms_test_wp_cron'][200]['vms_unrelated_hook']), 'Fallback WP-Cron cleanup should leave unrelated cron hooks untouched.');

$disposableStore = new VmsLegacySquareCleanupFakeStore(array(
	11 => array('hook' => 'vms_square_nightly_sync', 'status' => 'pending'),
	12 => array('hook' => 'vms_square_nightly_sync', 'status' => 'failed'),
	13 => array('hook' => 'vms_square_nightly_sync', 'status' => 'canceled'),
	21 => array('hook' => 'vms_square_live_sync', 'status' => 'pending'),
	22 => array('hook' => 'vms_square_live_sync', 'status' => 'failed'),
));
$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_is_admin'] = true;
$GLOBALS['vms_test_wp_cron'] = array();
$cleanupComplete = vms_run_legacy_square_nightly_sync_cleanup(array(
	'action_scheduler_store' => $disposableStore,
));
vms_legacy_square_cleanup_assert(!empty($cleanupComplete['complete']), 'Legacy Square cleanup should complete when exact-hook cron and Action Scheduler cleanup both succeed.');
vms_legacy_square_cleanup_assert($cleanupComplete['action_scheduler']['pending_found'] === 1, 'Legacy Square cleanup should find the disposable pending retired action.');
vms_legacy_square_cleanup_assert($cleanupComplete['action_scheduler']['pending_canceled'] === 1, 'Legacy Square cleanup should cancel the disposable pending retired action.');
vms_legacy_square_cleanup_assert($cleanupComplete['action_scheduler']['failed_found'] === 1, 'Legacy Square cleanup should find the disposable failed retired action.');
vms_legacy_square_cleanup_assert($cleanupComplete['action_scheduler']['failed_deleted'] === 1, 'Legacy Square cleanup should delete the disposable failed retired action.');
vms_legacy_square_cleanup_assert($cleanupComplete['action_scheduler']['canceled_found'] === 1, 'Legacy Square cleanup should find the disposable canceled retired action.');
vms_legacy_square_cleanup_assert($cleanupComplete['action_scheduler']['canceled_deleted'] === 1, 'Legacy Square cleanup should delete the disposable canceled retired action.');
vms_legacy_square_cleanup_assert($cleanupComplete['action_scheduler']['post_cancel_canceled_deleted'] === 1, 'Legacy Square cleanup should delete the pending action after it is canceled.');
vms_legacy_square_cleanup_assert(!isset($disposableStore->actions[11]) && !isset($disposableStore->actions[12]) && !isset($disposableStore->actions[13]), 'Legacy Square cleanup should remove all disposable retired-hook Action Scheduler records.');
vms_legacy_square_cleanup_assert(isset($disposableStore->actions[21]) && isset($disposableStore->actions[22]), 'Legacy Square cleanup should not touch unrelated Action Scheduler hooks.');
vms_legacy_square_cleanup_assert(($GLOBALS['vms_test_options'][vms_legacy_square_nightly_sync_cleanup_marker_key()] ?? '') === '1', 'Legacy Square cleanup should persist its completion marker only after full cleanup succeeds.');

$GLOBALS['vms_test_options'] = array();
$cleanupUnavailable = vms_run_legacy_square_nightly_sync_cleanup();
vms_legacy_square_cleanup_assert(empty($cleanupUnavailable['complete']), 'Legacy Square cleanup should remain incomplete when Action Scheduler is unavailable.');
vms_legacy_square_cleanup_assert(empty($cleanupUnavailable['action_scheduler']['available']), 'Legacy Square cleanup should report Action Scheduler unavailable when no store is available.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_options'][vms_legacy_square_nightly_sync_cleanup_marker_key()]), 'Legacy Square cleanup should not set the completion marker when Action Scheduler is unavailable.');

$limitedStore = new VmsLegacySquareCleanupFakeStore(array(
	31 => array('hook' => 'vms_square_nightly_sync', 'status' => 'failed'),
	32 => array('hook' => 'vms_square_nightly_sync', 'status' => 'failed'),
	33 => array('hook' => 'vms_square_nightly_sync', 'status' => 'failed'),
));
$GLOBALS['vms_test_options'] = array();
$cleanupLimited = vms_run_legacy_square_nightly_sync_cleanup(array(
	'action_scheduler_store' => $limitedStore,
	'batch_size' => 2,
	'max_batches' => 1,
));
vms_legacy_square_cleanup_assert(empty($cleanupLimited['complete']), 'Legacy Square cleanup should remain incomplete when the batch limit is reached.');
vms_legacy_square_cleanup_assert(!empty($cleanupLimited['action_scheduler']['batch_limit_reached']), 'Legacy Square cleanup should report when the Action Scheduler batch limit is reached.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_options'][vms_legacy_square_nightly_sync_cleanup_marker_key()]), 'Legacy Square cleanup should not set the completion marker when cleanup remains incomplete.');

$idempotentStore = new VmsLegacySquareCleanupFakeStore(array());
$idempotentFirst = vms_cleanup_legacy_square_nightly_sync_action_scheduler(vms_legacy_square_nightly_sync_hook_name(), array(
	'action_scheduler_store' => $idempotentStore,
));
$idempotentSecond = vms_cleanup_legacy_square_nightly_sync_action_scheduler(vms_legacy_square_nightly_sync_hook_name(), array(
	'action_scheduler_store' => $idempotentStore,
));
vms_legacy_square_cleanup_assert(!empty($idempotentFirst['complete']) && !empty($idempotentSecond['complete']), 'Repeated completed Action Scheduler cleanup should stay complete.');
vms_legacy_square_cleanup_assert($idempotentFirst['pending_found'] === 0 && $idempotentSecond['pending_found'] === 0, 'Repeated completed Action Scheduler cleanup should stay idempotent with no new retired actions found.');

fwrite(
	STDOUT,
	sprintf(
		"Legacy Square sync cleanup OK. fixture_counts pending=%d failed=%d canceled=%d\n",
		$cleanupComplete['action_scheduler']['pending_found'],
		$cleanupComplete['action_scheduler']['failed_found'],
		$cleanupComplete['action_scheduler']['canceled_found']
	)
);
