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

final class WP_Error
{
	public string $code;
	public string $message;

	public function __construct(string $code, string $message)
	{
		$this->code = $code;
		$this->message = $message;
	}
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
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
	public array $query_response_sequences = array();
	public array $query_calls = array();

	public function __construct(array $actions, array $query_response_sequences = array())
	{
		$this->actions = $actions;
		$this->query_response_sequences = $query_response_sequences;
	}

	public function query_actions(array $args)
	{
		$hook = (string) ($args['hook'] ?? '');
		$status = (string) ($args['status'] ?? '');
		$perPage = max(1, (int) ($args['per_page'] ?? 50));
		$this->query_calls[] = array(
			'hook' => $hook,
			'status' => $status,
		);

		if (array_key_exists($status, $this->query_response_sequences) && count($this->query_response_sequences[$status]) > 0) {
			return array_shift($this->query_response_sequences[$status]);
		}

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
	&& $GLOBALS['vms_test_actions']['vms_square_nightly_sync'][0] === 'bvmgr_retired_square_nightly_sync_callback',
	'The retired Square nightly sync hook should register a no-op callback.'
);
bvmgr_retired_square_nightly_sync_callback();

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
$cronPreferred = bvmgr_cleanup_legacy_square_nightly_sync_wp_cron(bvmgr_legacy_square_nightly_sync_hook_name());
vms_legacy_square_cleanup_assert($cronPreferred['method'] === 'wp_unschedule_hook', 'The preferred WP-Cron cleanup should use wp_unschedule_hook when available.');
vms_legacy_square_cleanup_assert(!empty($cronPreferred['complete']), 'The preferred WP-Cron cleanup should complete when wp_unschedule_hook succeeds.');
vms_legacy_square_cleanup_assert($cronPreferred['found'] === 2 && $cronPreferred['cleared'] === 2, 'The preferred WP-Cron cleanup should remove all retired-hook argument variants.');
vms_legacy_square_cleanup_assert($cronPreferred['remaining'] === 0, 'The preferred WP-Cron cleanup should verify that no retired-hook cron entries remain.');
vms_legacy_square_cleanup_assert($GLOBALS['vms_test_unschedule_hook_calls'] === array('vms_square_nightly_sync'), 'The preferred WP-Cron cleanup should unschedule the exact retired hook.');
vms_legacy_square_cleanup_assert(empty($GLOBALS['vms_test_cleared_hooks']), 'The preferred WP-Cron cleanup should not fall back to wp_clear_scheduled_hook when wp_unschedule_hook is available.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_wp_cron'][100]['vms_square_nightly_sync']), 'The preferred WP-Cron cleanup should remove every retired-hook cron entry.');
vms_legacy_square_cleanup_assert(isset($GLOBALS['vms_test_wp_cron'][100]['vms_other_hook']), 'The preferred WP-Cron cleanup should leave unrelated cron hooks untouched.');

$GLOBALS['vms_test_wp_cron'] = array(
	150 => array(
		'vms_square_nightly_sync' => array(
			'no_args' => array('args' => array()),
			'with_args' => array('args' => array('venue' => 55)),
		),
		'vms_other_hook' => array(
			'other' => array('args' => array('keep' => true)),
		),
	),
);
$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_unschedule_hook_calls'] = array();
$cronPreferredFalse = bvmgr_run_legacy_square_nightly_sync_cleanup(array(
	'action_scheduler_store' => new VmsLegacySquareCleanupFakeStore(array()),
	'wp_unschedule_hook_callback' => static function (string $hook) {
		$GLOBALS['vms_test_unschedule_hook_calls'][] = $hook;
		return false;
	},
));
vms_legacy_square_cleanup_assert(empty($cronPreferredFalse['cron']['complete']), 'WP-Cron cleanup should remain incomplete when wp_unschedule_hook returns false.');
vms_legacy_square_cleanup_assert(empty($cronPreferredFalse['complete']), 'Overall cleanup should remain incomplete when wp_unschedule_hook returns false.');
vms_legacy_square_cleanup_assert($cronPreferredFalse['cron']['remaining'] === 2, 'A false wp_unschedule_hook result should leave retired-hook cron entries detectable.');
vms_legacy_square_cleanup_assert($cronPreferredFalse['cron']['failed_calls'] === 1, 'A false wp_unschedule_hook result should be recorded as a failed cleanup call.');
vms_legacy_square_cleanup_assert($cronPreferredFalse['cron']['failure_codes'] === array('wp_unschedule_hook_returned_false'), 'A false wp_unschedule_hook result should preserve its failure code.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_options'][bvmgr_legacy_square_nightly_sync_cleanup_marker_key()]), 'A false wp_unschedule_hook result must not persist the cleanup marker.');
vms_legacy_square_cleanup_assert(isset($GLOBALS['vms_test_wp_cron'][150]['vms_square_nightly_sync']), 'A false wp_unschedule_hook result should not make remaining retired-hook cron entries look cleared.');
vms_legacy_square_cleanup_assert(isset($GLOBALS['vms_test_wp_cron'][150]['vms_other_hook']), 'A false wp_unschedule_hook result should leave unrelated cron hooks untouched.');

$GLOBALS['vms_test_wp_cron'] = array(
	175 => array(
		'vms_square_nightly_sync' => array(
			'no_args' => array('args' => array()),
			'with_args' => array('args' => array('venue' => 66)),
		),
		'vms_other_hook' => array(
			'other' => array('args' => array('keep' => true)),
		),
	),
);
$GLOBALS['vms_test_options'] = array();
$cronPreferredError = bvmgr_run_legacy_square_nightly_sync_cleanup(array(
	'action_scheduler_store' => new VmsLegacySquareCleanupFakeStore(array()),
	'wp_unschedule_hook_callback' => static function (string $hook) {
		unset($hook);
		return new WP_Error('cron_failure', 'Unschedule failed.');
	},
));
vms_legacy_square_cleanup_assert(empty($cronPreferredError['cron']['complete']), 'WP-Cron cleanup should remain incomplete when wp_unschedule_hook returns WP_Error.');
vms_legacy_square_cleanup_assert(empty($cronPreferredError['complete']), 'Overall cleanup should remain incomplete when wp_unschedule_hook returns WP_Error.');
vms_legacy_square_cleanup_assert($cronPreferredError['cron']['remaining'] === 2, 'A WP_Error wp_unschedule_hook result should leave retired-hook cron entries detectable.');
vms_legacy_square_cleanup_assert($cronPreferredError['cron']['failure_codes'] === array('wp_unschedule_hook_wp_error'), 'A WP_Error wp_unschedule_hook result should preserve its failure code.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_options'][bvmgr_legacy_square_nightly_sync_cleanup_marker_key()]), 'A WP_Error wp_unschedule_hook result must not persist the cleanup marker.');
vms_legacy_square_cleanup_assert(isset($GLOBALS['vms_test_wp_cron'][175]['vms_other_hook']), 'A WP_Error wp_unschedule_hook result should leave unrelated cron hooks untouched.');

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
$cronFallback = bvmgr_cleanup_legacy_square_nightly_sync_wp_cron_fallback(bvmgr_legacy_square_nightly_sync_hook_name());
vms_legacy_square_cleanup_assert($cronFallback['found'] === 2, 'Fallback WP-Cron cleanup should detect both empty-arg and non-empty-arg legacy entries.');
vms_legacy_square_cleanup_assert($cronFallback['cleared'] === 2, 'Fallback WP-Cron cleanup should clear every legacy argument variant.');
vms_legacy_square_cleanup_assert(!empty($cronFallback['complete']) && $cronFallback['remaining'] === 0, 'Fallback WP-Cron cleanup should verify that no retired-hook cron entries remain.');
vms_legacy_square_cleanup_assert(count($GLOBALS['vms_test_cleared_hooks']) === 2, 'Fallback WP-Cron cleanup should clear each argument variant separately.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_wp_cron'][200]['vms_square_nightly_sync']), 'Fallback WP-Cron cleanup should remove the retired hook entries.');
vms_legacy_square_cleanup_assert(isset($GLOBALS['vms_test_wp_cron'][200]['vms_unrelated_hook']), 'Fallback WP-Cron cleanup should leave unrelated cron hooks untouched.');

$failingFallbackCallback = static function (string $hook, array $args) {
	$GLOBALS['vms_test_cleared_hooks'][] = array(
		'hook' => $hook,
		'args' => $args,
	);

	if ($args === array(88)) {
		return false;
	}

	return vms_test_remove_cron_entries($hook, $args === array() ? array() : array_values($args));
};

$GLOBALS['vms_test_wp_cron'] = array(
	250 => array(
		'vms_square_nightly_sync' => array(
			'no_args' => array('args' => array()),
			'with_args' => array('args' => array('venue' => 88)),
		),
		'vms_unrelated_hook' => array(
			'keep_me' => array('args' => array('safe' => true)),
		),
	),
);
$GLOBALS['vms_test_cleared_hooks'] = array();
$cronFallbackFailure = bvmgr_cleanup_legacy_square_nightly_sync_wp_cron(bvmgr_legacy_square_nightly_sync_hook_name(), array(
	'force_wp_cron_fallback' => true,
	'wp_clear_scheduled_hook_callback' => $failingFallbackCallback,
));
vms_legacy_square_cleanup_assert(empty($cronFallbackFailure['complete']), 'Fallback WP-Cron cleanup should remain incomplete when one clear call fails.');
vms_legacy_square_cleanup_assert($cronFallbackFailure['cleared'] === 1, 'Fallback WP-Cron cleanup should not count a failed clear call as successful.');
vms_legacy_square_cleanup_assert($cronFallbackFailure['failed_calls'] === 1, 'Fallback WP-Cron cleanup should report a failed clear call.');
vms_legacy_square_cleanup_assert($cronFallbackFailure['remaining'] === 1, 'Fallback WP-Cron cleanup should detect the remaining retired-hook cron entry after a failed clear call.');
vms_legacy_square_cleanup_assert(count($cronFallbackFailure['failed_variants']) === 1 && $cronFallbackFailure['failed_variants'][0]['arg_count'] === 1, 'Fallback WP-Cron cleanup should report which argument variant failed without exposing raw args.');
vms_legacy_square_cleanup_assert(isset($GLOBALS['vms_test_wp_cron'][250]['vms_square_nightly_sync']['with_args']), 'A failed fallback clear should leave the targeted argument variant detectable.');
vms_legacy_square_cleanup_assert(isset($GLOBALS['vms_test_wp_cron'][250]['vms_unrelated_hook']), 'A failed fallback clear should leave unrelated cron hooks untouched.');

$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_wp_cron'] = array(
	275 => array(
		'vms_square_nightly_sync' => array(
			'no_args' => array('args' => array()),
			'with_args' => array('args' => array('venue' => 88)),
		),
		'vms_unrelated_hook' => array(
			'keep_me' => array('args' => array('safe' => true)),
		),
	),
);
$cronFallbackOverallFailure = bvmgr_run_legacy_square_nightly_sync_cleanup(array(
	'action_scheduler_store' => new VmsLegacySquareCleanupFakeStore(array()),
	'force_wp_cron_fallback' => true,
	'wp_clear_scheduled_hook_callback' => $failingFallbackCallback,
));
vms_legacy_square_cleanup_assert(empty($cronFallbackOverallFailure['complete']), 'Overall cleanup should remain incomplete when a fallback clear call fails.');
vms_legacy_square_cleanup_assert(empty($cronFallbackOverallFailure['cron']['complete']), 'Overall cleanup should preserve incomplete fallback WP-Cron status.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_options'][bvmgr_legacy_square_nightly_sync_cleanup_marker_key()]), 'A failed fallback clear must not persist the cleanup marker.');

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
$cleanupComplete = bvmgr_run_legacy_square_nightly_sync_cleanup(array(
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
vms_legacy_square_cleanup_assert($cleanupComplete['action_scheduler']['remaining_found'] === 0, 'Legacy Square cleanup should verify that no retired-hook Action Scheduler rows remain after a completed pass.');
vms_legacy_square_cleanup_assert(!isset($disposableStore->actions[11]) && !isset($disposableStore->actions[12]) && !isset($disposableStore->actions[13]), 'Legacy Square cleanup should remove all disposable retired-hook Action Scheduler records.');
vms_legacy_square_cleanup_assert(isset($disposableStore->actions[21]) && isset($disposableStore->actions[22]), 'Legacy Square cleanup should not touch unrelated Action Scheduler hooks.');
vms_legacy_square_cleanup_assert(($GLOBALS['vms_test_options'][bvmgr_legacy_square_nightly_sync_cleanup_marker_key()] ?? '') === '1', 'Legacy Square cleanup should persist its completion marker only after full cleanup succeeds.');

$queryNullStore = new VmsLegacySquareCleanupFakeStore(
	array(
		41 => array('hook' => 'vms_square_nightly_sync', 'status' => 'failed'),
		51 => array('hook' => 'vms_square_live_sync', 'status' => 'pending'),
	),
	array(
		'failed' => array(null),
	)
);
$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_wp_cron'] = array();
$cleanupQueryNull = bvmgr_run_legacy_square_nightly_sync_cleanup(array(
	'action_scheduler_store' => $queryNullStore,
));
vms_legacy_square_cleanup_assert(empty($cleanupQueryNull['action_scheduler']['complete']), 'Action Scheduler cleanup should remain incomplete when query_actions returns null.');
vms_legacy_square_cleanup_assert(empty($cleanupQueryNull['complete']), 'Overall cleanup should remain incomplete when query_actions returns null.');
vms_legacy_square_cleanup_assert(!empty($cleanupQueryNull['action_scheduler']['query_failed']), 'Action Scheduler cleanup should report query failure when query_actions returns null.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_options'][bvmgr_legacy_square_nightly_sync_cleanup_marker_key()]), 'A null Action Scheduler query result must not persist the cleanup marker.');
vms_legacy_square_cleanup_assert(isset($queryNullStore->actions[41]) && isset($queryNullStore->actions[51]), 'A null Action Scheduler query result should leave targeted and unrelated actions untouched.');

$queryFalseStore = new VmsLegacySquareCleanupFakeStore(
	array(
		61 => array('hook' => 'vms_square_nightly_sync', 'status' => 'pending'),
		71 => array('hook' => 'vms_square_live_sync', 'status' => 'failed'),
	),
	array(
		'pending' => array(false),
	)
);
$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_wp_cron'] = array();
$cleanupQueryFalse = bvmgr_run_legacy_square_nightly_sync_cleanup(array(
	'action_scheduler_store' => $queryFalseStore,
));
vms_legacy_square_cleanup_assert(empty($cleanupQueryFalse['action_scheduler']['complete']), 'Action Scheduler cleanup should remain incomplete when query_actions returns false.');
vms_legacy_square_cleanup_assert(empty($cleanupQueryFalse['complete']), 'Overall cleanup should remain incomplete when query_actions returns false.');
vms_legacy_square_cleanup_assert(!empty($cleanupQueryFalse['action_scheduler']['query_failed']), 'Action Scheduler cleanup should report query failure when query_actions returns false.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_options'][bvmgr_legacy_square_nightly_sync_cleanup_marker_key()]), 'A false Action Scheduler query result must not persist the cleanup marker.');
vms_legacy_square_cleanup_assert(isset($queryFalseStore->actions[61]) && isset($queryFalseStore->actions[71]), 'A false Action Scheduler query result should leave targeted and unrelated actions untouched.');

$GLOBALS['vms_test_options'] = array();
$cleanupUnavailable = bvmgr_run_legacy_square_nightly_sync_cleanup();
vms_legacy_square_cleanup_assert(empty($cleanupUnavailable['complete']), 'Legacy Square cleanup should remain incomplete when Action Scheduler is unavailable.');
vms_legacy_square_cleanup_assert(empty($cleanupUnavailable['action_scheduler']['available']), 'Legacy Square cleanup should report Action Scheduler unavailable when no store is available.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_options'][bvmgr_legacy_square_nightly_sync_cleanup_marker_key()]), 'Legacy Square cleanup should not set the completion marker when Action Scheduler is unavailable.');

$limitedStore = new VmsLegacySquareCleanupFakeStore(array(
	31 => array('hook' => 'vms_square_nightly_sync', 'status' => 'failed'),
	32 => array('hook' => 'vms_square_nightly_sync', 'status' => 'failed'),
	33 => array('hook' => 'vms_square_nightly_sync', 'status' => 'failed'),
));
$GLOBALS['vms_test_options'] = array();
$cleanupLimited = bvmgr_run_legacy_square_nightly_sync_cleanup(array(
	'action_scheduler_store' => $limitedStore,
	'batch_size' => 2,
	'max_batches' => 1,
));
vms_legacy_square_cleanup_assert(empty($cleanupLimited['complete']), 'Legacy Square cleanup should remain incomplete when the batch limit is reached.');
vms_legacy_square_cleanup_assert(!empty($cleanupLimited['action_scheduler']['batch_limit_reached']), 'Legacy Square cleanup should report when the Action Scheduler batch limit is reached.');
vms_legacy_square_cleanup_assert(!isset($GLOBALS['vms_test_options'][bvmgr_legacy_square_nightly_sync_cleanup_marker_key()]), 'Legacy Square cleanup should not set the completion marker when cleanup remains incomplete.');

$idempotentStore = new VmsLegacySquareCleanupFakeStore(array());
$idempotentFirst = bvmgr_cleanup_legacy_square_nightly_sync_action_scheduler(bvmgr_legacy_square_nightly_sync_hook_name(), array(
	'action_scheduler_store' => $idempotentStore,
));
$idempotentSecond = bvmgr_cleanup_legacy_square_nightly_sync_action_scheduler(bvmgr_legacy_square_nightly_sync_hook_name(), array(
	'action_scheduler_store' => $idempotentStore,
));
vms_legacy_square_cleanup_assert(!empty($idempotentFirst['complete']) && !empty($idempotentSecond['complete']), 'Repeated completed Action Scheduler cleanup should stay complete.');
vms_legacy_square_cleanup_assert($idempotentFirst['pending_found'] === 0 && $idempotentSecond['pending_found'] === 0, 'Repeated completed Action Scheduler cleanup should stay idempotent with no new retired actions found.');
vms_legacy_square_cleanup_assert(empty($idempotentFirst['query_failed']) && empty($idempotentSecond['query_failed']), 'Successful empty Action Scheduler queries should not be treated as query failures.');
vms_legacy_square_cleanup_assert($idempotentFirst['remaining_found'] === 0 && $idempotentSecond['remaining_found'] === 0, 'Successful empty Action Scheduler queries should still verify that no retired-hook rows remain.');

fwrite(
	STDOUT,
	sprintf(
		"Legacy Square sync cleanup OK. fixture_counts pending=%d failed=%d canceled=%d\n",
		$cleanupComplete['action_scheduler']['pending_found'],
		$cleanupComplete['action_scheduler']['failed_found'],
		$cleanupComplete['action_scheduler']['canceled_found']
	)
);
