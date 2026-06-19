<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_cleared_hooks'] = array();
$GLOBALS['vms_test_unscheduled_actions'] = array();

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

function wp_clear_scheduled_hook(string $hook): int
{
	$GLOBALS['vms_test_cleared_hooks'][] = $hook;
	return 1;
}

function as_unschedule_all_actions(string $hook): int
{
	$GLOBALS['vms_test_unscheduled_actions'][] = $hook;
	return 1;
}

function flush_rewrite_rules(): void
{
}

require dirname(__DIR__) . '/includes/activation.php';

vms_cleanup_legacy_square_nightly_sync_hook();
vms_legacy_square_cleanup_assert($GLOBALS['vms_test_cleared_hooks'] === array('vms_square_nightly_sync'), 'Legacy Square cleanup should clear the orphaned WP-Cron hook.');
vms_legacy_square_cleanup_assert($GLOBALS['vms_test_unscheduled_actions'] === array('vms_square_nightly_sync'), 'Legacy Square cleanup should unschedule the orphaned Action Scheduler hook.');

$GLOBALS['vms_test_cleared_hooks'] = array();
$GLOBALS['vms_test_unscheduled_actions'] = array();
vms_maybe_cleanup_legacy_square_nightly_sync_hook();
vms_legacy_square_cleanup_assert($GLOBALS['vms_test_cleared_hooks'] === array('vms_square_nightly_sync'), 'One-time legacy Square cleanup should run when the option flag is missing.');
vms_legacy_square_cleanup_assert($GLOBALS['vms_test_unscheduled_actions'] === array('vms_square_nightly_sync'), 'One-time legacy Square cleanup should unschedule actions when the option flag is missing.');
vms_legacy_square_cleanup_assert(($GLOBALS['vms_test_options']['vms_cleanup_legacy_square_nightly_sync_0_2_24_748'] ?? '') === '1', 'One-time legacy Square cleanup should persist its completion marker.');

$GLOBALS['vms_test_cleared_hooks'] = array();
$GLOBALS['vms_test_unscheduled_actions'] = array();
vms_maybe_cleanup_legacy_square_nightly_sync_hook();
vms_legacy_square_cleanup_assert($GLOBALS['vms_test_cleared_hooks'] === array(), 'One-time legacy Square cleanup should not repeat after its marker is stored.');
vms_legacy_square_cleanup_assert($GLOBALS['vms_test_unscheduled_actions'] === array(), 'One-time legacy Square cleanup should not re-unschedule after its marker is stored.');

fwrite(STDOUT, "Legacy Square sync cleanup OK.\n");
