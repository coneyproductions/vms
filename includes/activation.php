<?php
if (!defined('ABSPATH')) exit;

/**
 * Runs on plugin activation (hooked from the main plugin file).
 */
function bvmgr_activate_plugin(): void
{
	if (function_exists('bvmgr_resource_fingerprint_flag')) {
		bvmgr_resource_fingerprint_flag('plugin_activation', bvmgr_plugin_lifecycle_basename());
	}

	if (function_exists('bvmgr_run_legacy_square_nightly_sync_cleanup')) {
		bvmgr_run_legacy_square_nightly_sync_cleanup();
	}

	if (function_exists('vms_require_internal_file') && vms_require_internal_file('includes/db/migrations.php', 'missing_db_migrations_activation', 'Database migrations')) {
		if (function_exists('vms_db_migrate_vendor_core_v7')) {
			vms_db_migrate_vendor_core_v7();
		} elseif (function_exists('vms_db_migrate_vendor_core_v6')) {
			vms_db_migrate_vendor_core_v6();
		} elseif (function_exists('vms_db_migrate_vendor_core_v5')) {
			vms_db_migrate_vendor_core_v5();
		} elseif (function_exists('vms_db_migrate_vendor_core_v4')) {
			vms_db_migrate_vendor_core_v4();
		} elseif (function_exists('vms_db_migrate_vendor_core_v3')) {
			vms_db_migrate_vendor_core_v3();
		} elseif (function_exists('vms_db_migrate_vendor_core_v2')) {
			vms_db_migrate_vendor_core_v2();
		} elseif (function_exists('vms_db_migrate_vendor_core_v1')) {
			vms_db_migrate_vendor_core_v1();
		}
	}

	// Create/ensure public pages
	bvmgr_install_public_pages();

	$recurring_bootstraps = array(
		'vms_social_schedule_cron',
		'vms_tasks_notifications_ensure_cron',
		'vms_tasks_schedule_nightly_generator',
		'vms_email_followups_schedule_cron',
		'vms_calendar_ticket_counts_schedule_cron',
		'vms_vendor_booking_onboarding_schedule_event',
		'vms_notify_ensure_digest_cron',
		'vms_ticket_integrity_maybe_schedule_cron',
		'vms_integrity_schedule_daily_scan',
		'vms_ticketing_v2_legacy_cleanup_cron_init',
		'vms_ticketing_verification_schedule_cleanup',
	);
	foreach ($recurring_bootstraps as $bootstrap) {
		if (function_exists($bootstrap)) {
			$bootstrap();
		}
	}

	// One-time notice flag
	update_option('vms_show_first_run_notice', '1', false);

	// If your plugin registers rewrite rules, flush once on activation
	flush_rewrite_rules();
}

function bvmgr_deactivate_plugin(): void
{
	if (function_exists('bvmgr_resource_fingerprint_flag')) {
		bvmgr_resource_fingerprint_flag('plugin_deactivation', bvmgr_plugin_lifecycle_basename());
	}

	if (function_exists('bvmgr_run_legacy_square_nightly_sync_cleanup')) {
		bvmgr_run_legacy_square_nightly_sync_cleanup();
	}

	if (function_exists('vms_unschedule_all_owned_cron_hooks')) {
		vms_unschedule_all_owned_cron_hooks();
	}
	flush_rewrite_rules();
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_hook_name')) {
	function bvmgr_legacy_square_nightly_sync_hook_name(): string
	{
		return 'vms_square_nightly_sync';
	}
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_cleanup_marker_key')) {
	function bvmgr_legacy_square_nightly_sync_cleanup_marker_key(): string
	{
		return 'vms_cleanup_legacy_square_nightly_sync_0_2_24_748';
	}
}

if (!function_exists('bvmgr_retired_square_nightly_sync_callback')) {
	function bvmgr_retired_square_nightly_sync_callback(): void
	{
		// Retired hook placeholder. Intentionally no-op.
	}
}

if (function_exists('add_action')) {
	add_action(bvmgr_legacy_square_nightly_sync_hook_name(), 'bvmgr_retired_square_nightly_sync_callback', 1, 0);
}

if (!function_exists('bvmgr_is_safe_legacy_square_cleanup_context')) {
	function bvmgr_is_safe_legacy_square_cleanup_context(): bool
	{
		if (defined('WP_CLI') && WP_CLI) {
			return true;
		}

		if (function_exists('wp_doing_cron') && wp_doing_cron()) {
			return true;
		}

		if (defined('DOING_CRON') && DOING_CRON) {
			return true;
		}

		return function_exists('is_admin') && is_admin();
	}
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_is_wp_error')) {
	function bvmgr_legacy_square_nightly_sync_is_wp_error($value): bool
	{
		return function_exists('is_wp_error') && is_wp_error($value);
	}
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_supports_wp_error_flag')) {
	function bvmgr_legacy_square_nightly_sync_supports_wp_error_flag(string $function_name, int $parameter_count): bool
	{
		if (!function_exists($function_name)) {
			return false;
		}

		try {
			$reflection = new ReflectionFunction($function_name);
			return $reflection->getNumberOfParameters() >= $parameter_count;
		} catch (ReflectionException $e) {
			return false;
		}
	}
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_collect_cron_entries')) {
	function bvmgr_legacy_square_nightly_sync_collect_cron_entries(string $hook): array
	{
		$result = array(
			'available' => false,
			'found' => 0,
			'variants' => array(),
		);

		if (!function_exists('_get_cron_array')) {
			return $result;
		}

		$result['available'] = true;
		$cron = _get_cron_array();
		if (!is_array($cron) || empty($cron)) {
			return $result;
		}

		foreach ($cron as $events) {
			if (!is_array($events)) {
				continue;
			}

			foreach ($events as $scheduled_hook => $instances) {
				if ((string) $scheduled_hook !== $hook || !is_array($instances)) {
					continue;
				}

				foreach ($instances as $instance) {
					$result['found']++;
					$args = isset($instance['args']) && is_array($instance['args']) ? array_values($instance['args']) : array();
					$signature = md5(serialize($args));
					if (!isset($result['variants'][$signature])) {
						$result['variants'][$signature] = array(
							'signature' => $signature,
							'arg_count' => count($args),
							'args' => $args,
						);
					}
				}
			}
		}

		return $result;
	}
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_call_wp_unschedule_hook')) {
	function bvmgr_legacy_square_nightly_sync_call_wp_unschedule_hook(string $hook, array $options = array())
	{
		if (array_key_exists('wp_unschedule_hook_callback', $options) && is_callable($options['wp_unschedule_hook_callback'])) {
			return $options['wp_unschedule_hook_callback']($hook);
		}

		if (!function_exists('wp_unschedule_hook')) {
			return null;
		}

		if (bvmgr_legacy_square_nightly_sync_supports_wp_error_flag('wp_unschedule_hook', 2)) {
			return wp_unschedule_hook($hook, true);
		}

		return wp_unschedule_hook($hook);
	}
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_call_wp_clear_scheduled_hook')) {
	function bvmgr_legacy_square_nightly_sync_call_wp_clear_scheduled_hook(string $hook, array $args, array $options = array())
	{
		if (array_key_exists('wp_clear_scheduled_hook_callback', $options) && is_callable($options['wp_clear_scheduled_hook_callback'])) {
			return $options['wp_clear_scheduled_hook_callback']($hook, $args);
		}

		if (!function_exists('wp_clear_scheduled_hook')) {
			return null;
		}

		if (bvmgr_legacy_square_nightly_sync_supports_wp_error_flag('wp_clear_scheduled_hook', 3)) {
			return wp_clear_scheduled_hook($hook, $args, true);
		}

		if (!empty($args)) {
			return wp_clear_scheduled_hook($hook, $args);
		}

		return wp_clear_scheduled_hook($hook);
	}
}

if (!function_exists('bvmgr_cleanup_legacy_square_nightly_sync_wp_cron_fallback')) {
	function bvmgr_cleanup_legacy_square_nightly_sync_wp_cron_fallback(string $hook, array $options = array()): array
	{
		$result = array(
			'available' => false,
			'complete' => false,
			'method' => 'fallback',
			'found' => 0,
			'cleared' => 0,
			'remaining' => 0,
			'failed_calls' => 0,
			'failure_codes' => array(),
			'failed_variants' => array(),
		);

		if (!function_exists('_get_cron_array') || !function_exists('wp_clear_scheduled_hook')) {
			return $result;
		}

		$result['available'] = true;
		$before = bvmgr_legacy_square_nightly_sync_collect_cron_entries($hook);
		$result['found'] = (int) $before['found'];

		foreach ($before['variants'] as $variant) {
			$clear_result = bvmgr_legacy_square_nightly_sync_call_wp_clear_scheduled_hook($hook, $variant['args'], $options);
			if ($clear_result === false || bvmgr_legacy_square_nightly_sync_is_wp_error($clear_result)) {
				$result['failed_calls']++;
				$result['failure_codes'][] = $clear_result === false ? 'wp_clear_scheduled_hook_returned_false' : 'wp_clear_scheduled_hook_wp_error';
				$result['failed_variants'][] = array(
					'signature' => $variant['signature'],
					'arg_count' => $variant['arg_count'],
				);
				continue;
			}

			$result['cleared']++;
		}

		$after = bvmgr_legacy_square_nightly_sync_collect_cron_entries($hook);
		$result['remaining'] = (int) $after['found'];
		$result['complete'] = !empty($after['available']) && $result['failed_calls'] === 0 && $result['remaining'] === 0;
		return $result;
	}
}

if (!function_exists('bvmgr_cleanup_legacy_square_nightly_sync_wp_cron')) {
	function bvmgr_cleanup_legacy_square_nightly_sync_wp_cron(string $hook, array $options = array()): array
	{
		if (!empty($options['force_wp_cron_fallback'])) {
			return bvmgr_cleanup_legacy_square_nightly_sync_wp_cron_fallback($hook, $options);
		}

		if (function_exists('wp_unschedule_hook')) {
			$before = bvmgr_legacy_square_nightly_sync_collect_cron_entries($hook);
			$result = array(
				'available' => true,
				'complete' => false,
				'method' => 'wp_unschedule_hook',
				'found' => (int) $before['found'],
				'cleared' => 0,
				'remaining' => 0,
				'failed_calls' => 0,
				'failure_codes' => array(),
				'failed_variants' => array(),
			);

			$removed = bvmgr_legacy_square_nightly_sync_call_wp_unschedule_hook($hook, $options);
			if ($removed === false || bvmgr_legacy_square_nightly_sync_is_wp_error($removed)) {
				$result['failed_calls'] = 1;
				$result['failure_codes'][] = $removed === false ? 'wp_unschedule_hook_returned_false' : 'wp_unschedule_hook_wp_error';
			} else {
				$result['cleared'] = max(0, is_numeric($removed) ? (int) $removed : 0);
			}

			$after = bvmgr_legacy_square_nightly_sync_collect_cron_entries($hook);
			$result['remaining'] = (int) $after['found'];
			$result['complete'] = !empty($after['available']) && $result['failed_calls'] === 0 && $result['remaining'] === 0;
			return $result;
		}

		return bvmgr_cleanup_legacy_square_nightly_sync_wp_cron_fallback($hook, $options);
	}
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_action_scheduler_store')) {
	function bvmgr_legacy_square_nightly_sync_action_scheduler_store(array $options = array())
	{
		if (array_key_exists('action_scheduler_store', $options)) {
			return $options['action_scheduler_store'];
		}

		if (!class_exists('ActionScheduler') || !method_exists('ActionScheduler', 'store')) {
			return null;
		}

		try {
			return ActionScheduler::store();
		} catch (Throwable $e) {
			return null;
		}
	}
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_action_scheduler_statuses')) {
	function bvmgr_legacy_square_nightly_sync_action_scheduler_statuses(string $type): array
	{
		if ($type === 'pending') {
			if (defined('ActionScheduler_Store::STATUS_PENDING')) {
				return array((string) constant('ActionScheduler_Store::STATUS_PENDING'));
			}

			return array('pending');
		}

		if ($type === 'failed') {
			if (defined('ActionScheduler_Store::STATUS_FAILED')) {
				return array((string) constant('ActionScheduler_Store::STATUS_FAILED'));
			}

			return array('failed');
		}

		$statuses = array();
		if (defined('ActionScheduler_Store::STATUS_CANCELED')) {
			$statuses[] = (string) constant('ActionScheduler_Store::STATUS_CANCELED');
		}
		if (defined('ActionScheduler_Store::STATUS_CANCELLED')) {
			$statuses[] = (string) constant('ActionScheduler_Store::STATUS_CANCELLED');
		}
		$statuses[] = 'canceled';
		$statuses[] = 'cancelled';

		return array_values(array_unique(array_filter(array_map('strval', $statuses))));
	}
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_query_action_ids')) {
	function bvmgr_legacy_square_nightly_sync_query_action_ids($store, string $hook, string $status, int $batch_size): ?array
	{
		if (!is_object($store) || !method_exists($store, 'query_actions')) {
			return null;
		}

		try {
			$ids = $store->query_actions(array(
				'hook' => $hook,
				'status' => $status,
				'per_page' => $batch_size,
				'orderby' => 'none',
			));
		} catch (Throwable $e) {
			return null;
		}

		if ($ids === null || $ids === false || !is_array($ids)) {
			return null;
		}

		return array_values(array_filter(array_map('intval', $ids), static function (int $action_id): bool {
			return $action_id > 0;
		}));
	}
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_action_scheduler_phase')) {
	function bvmgr_legacy_square_nightly_sync_action_scheduler_phase($store, string $hook, array $statuses, string $operation, int $batch_size, int $max_batches): array
	{
		$result = array(
			'complete' => true,
			'found' => 0,
			'processed' => 0,
			'batch_limit_reached' => false,
			'query_failed' => false,
		);

		$method = $operation === 'cancel' ? 'cancel_action' : 'delete_action';
		$statuses = array_values(array_unique(array_filter(array_map('strval', $statuses))));
		if (empty($statuses)) {
			return $result;
		}

		foreach ($statuses as $status) {
			for ($batch = 0; $batch < $max_batches; $batch++) {
				$ids = bvmgr_legacy_square_nightly_sync_query_action_ids($store, $hook, $status, $batch_size);
				if ($ids === null) {
					$result['complete'] = false;
					$result['query_failed'] = true;
					return $result;
				}
				if (empty($ids)) {
					break;
				}

				$result['found'] += count($ids);
				if (!method_exists($store, $method)) {
					$result['complete'] = false;
					return $result;
				}

				foreach ($ids as $action_id) {
					try {
						$store->{$method}($action_id);
						$result['processed']++;
					} catch (Throwable $e) {
						$result['complete'] = false;
						return $result;
					}
				}

				if (count($ids) < $batch_size) {
					break;
				}

				if ($batch === $max_batches - 1) {
					$result['complete'] = false;
					$result['batch_limit_reached'] = true;
				}
			}

			if ($result['batch_limit_reached']) {
				break;
			}
		}

		return $result;
	}
}

if (!function_exists('bvmgr_legacy_square_nightly_sync_action_scheduler_remaining_rows')) {
	function bvmgr_legacy_square_nightly_sync_action_scheduler_remaining_rows($store, string $hook, int $batch_size): ?array
	{
		$remaining = array();
		$statuses = array_values(array_unique(array_merge(
			bvmgr_legacy_square_nightly_sync_action_scheduler_statuses('pending'),
			bvmgr_legacy_square_nightly_sync_action_scheduler_statuses('failed'),
			bvmgr_legacy_square_nightly_sync_action_scheduler_statuses('canceled')
		)));

		foreach ($statuses as $status) {
			$ids = bvmgr_legacy_square_nightly_sync_query_action_ids($store, $hook, $status, $batch_size);
			if ($ids === null) {
				return null;
			}

			$remaining[$status] = count($ids);
		}

		return $remaining;
	}
}

if (!function_exists('bvmgr_cleanup_legacy_square_nightly_sync_action_scheduler')) {
	function bvmgr_cleanup_legacy_square_nightly_sync_action_scheduler(string $hook, array $options = array()): array
	{
		$batch_size = max(1, (int) ($options['batch_size'] ?? 50));
		$max_batches = max(1, (int) ($options['max_batches'] ?? 5));
		$result = array(
			'available' => false,
			'store_ready' => false,
			'complete' => false,
			'batch_size' => $batch_size,
			'max_batches' => $max_batches,
			'batch_limit_reached' => false,
			'pending_found' => 0,
			'pending_canceled' => 0,
			'failed_found' => 0,
			'failed_deleted' => 0,
			'canceled_found' => 0,
			'canceled_deleted' => 0,
			'post_cancel_canceled_deleted' => 0,
			'query_failed' => false,
			'remaining_found' => 0,
		);

		$store = bvmgr_legacy_square_nightly_sync_action_scheduler_store($options);
		if (!is_object($store) || !method_exists($store, 'query_actions')) {
			return $result;
		}

		$result['available'] = true;
		$result['store_ready'] = true;

		$failed_phase = bvmgr_legacy_square_nightly_sync_action_scheduler_phase(
			$store,
			$hook,
			bvmgr_legacy_square_nightly_sync_action_scheduler_statuses('failed'),
			'delete',
			$batch_size,
			$max_batches
		);
		$result['failed_found'] = (int) $failed_phase['found'];
		$result['failed_deleted'] = (int) $failed_phase['processed'];

		$canceled_phase = bvmgr_legacy_square_nightly_sync_action_scheduler_phase(
			$store,
			$hook,
			bvmgr_legacy_square_nightly_sync_action_scheduler_statuses('canceled'),
			'delete',
			$batch_size,
			$max_batches
		);
		$result['canceled_found'] = (int) $canceled_phase['found'];
		$result['canceled_deleted'] = (int) $canceled_phase['processed'];

		$pending_phase = bvmgr_legacy_square_nightly_sync_action_scheduler_phase(
			$store,
			$hook,
			bvmgr_legacy_square_nightly_sync_action_scheduler_statuses('pending'),
			'cancel',
			$batch_size,
			$max_batches
		);
		$result['pending_found'] = (int) $pending_phase['found'];
		$result['pending_canceled'] = (int) $pending_phase['processed'];

		$post_cancel_phase = bvmgr_legacy_square_nightly_sync_action_scheduler_phase(
			$store,
			$hook,
			bvmgr_legacy_square_nightly_sync_action_scheduler_statuses('canceled'),
			'delete',
			$batch_size,
			$max_batches
		);
		$result['post_cancel_canceled_deleted'] = (int) $post_cancel_phase['processed'];

		$result['batch_limit_reached'] = !empty($failed_phase['batch_limit_reached'])
			|| !empty($canceled_phase['batch_limit_reached'])
			|| !empty($pending_phase['batch_limit_reached'])
			|| !empty($post_cancel_phase['batch_limit_reached']);
		$result['query_failed'] = !empty($failed_phase['query_failed'])
			|| !empty($canceled_phase['query_failed'])
			|| !empty($pending_phase['query_failed'])
			|| !empty($post_cancel_phase['query_failed']);
		$result['complete'] = false;

		if (
			$result['batch_limit_reached']
			|| $result['query_failed']
			|| empty($failed_phase['complete'])
			|| empty($canceled_phase['complete'])
			|| empty($pending_phase['complete'])
			|| empty($post_cancel_phase['complete'])
		) {
			return $result;
		}

		$remaining_rows = bvmgr_legacy_square_nightly_sync_action_scheduler_remaining_rows($store, $hook, $batch_size);
		if ($remaining_rows === null) {
			$result['query_failed'] = true;
			return $result;
		}

		$result['remaining_found'] = array_sum(array_map('intval', $remaining_rows));
		$result['complete'] = $result['remaining_found'] === 0;

		return $result;
	}
}

if (!function_exists('bvmgr_cleanup_legacy_square_nightly_sync_hook')) {
	function bvmgr_cleanup_legacy_square_nightly_sync_hook(array $options = array()): array
	{
		$hook = isset($options['hook']) ? (string) $options['hook'] : bvmgr_legacy_square_nightly_sync_hook_name();
		$result = array(
			'hook' => $hook,
			'cron' => bvmgr_cleanup_legacy_square_nightly_sync_wp_cron($hook, $options),
			'action_scheduler' => bvmgr_cleanup_legacy_square_nightly_sync_action_scheduler($hook, $options),
			'complete' => false,
		);

		$result['complete'] = !empty($result['cron']['complete']) && !empty($result['action_scheduler']['complete']);
		return $result;
	}
}

if (!function_exists('bvmgr_run_legacy_square_nightly_sync_cleanup')) {
	function bvmgr_run_legacy_square_nightly_sync_cleanup(array $options = array()): array
	{
		$result = bvmgr_cleanup_legacy_square_nightly_sync_hook($options);
		if (!empty($result['complete']) && function_exists('update_option')) {
			update_option(bvmgr_legacy_square_nightly_sync_cleanup_marker_key(), '1', false);
		}

		return $result;
	}
}

if (!function_exists('bvmgr_maybe_cleanup_legacy_square_nightly_sync_hook')) {
	function bvmgr_maybe_cleanup_legacy_square_nightly_sync_hook(): void
	{
		if (!function_exists('get_option')) {
			return;
		}

		if (get_option(bvmgr_legacy_square_nightly_sync_cleanup_marker_key(), '') === '1') {
			return;
		}

		if (!bvmgr_is_safe_legacy_square_cleanup_context()) {
			return;
		}

		bvmgr_run_legacy_square_nightly_sync_cleanup();
	}
}
if (function_exists('add_action')) {
	add_action('init', 'bvmgr_maybe_cleanup_legacy_square_nightly_sync_hook', 5);
}

/**
 * Ensure a WP Page exists by slug. Creates it if missing.
 * Existing pages are adopted only when plugin ownership can be established.
 * A managed page is rewritten only for an explicit repair request.
 *
 * @return int Page ID (0 on failure)
 */
function bvmgr_public_page_is_managed(WP_Post $page, array $args): bool
{
	$managed_key = isset($args['managed_key']) ? sanitize_key((string) $args['managed_key']) : '';
	if ($managed_key !== '') {
		if ((string) get_post_meta($page->ID, '_vms_managed_public_page', true) === $managed_key) {
			return true;
		}

		if (absint(get_option('vms_page_' . $managed_key, 0)) === (int) $page->ID) {
			return true;
		}
	}

	$content = isset($args['content']) ? (string) $args['content'] : '';
	if (!preg_match('/\[\s*([A-Za-z0-9_-]+)/', $content, $matches)) {
		return false;
	}

	return function_exists('has_shortcode') && has_shortcode((string) $page->post_content, (string) $matches[1]);
}

function bvmgr_ensure_page_exists(array $args): int
{
	$slug    = isset($args['slug']) ? sanitize_title((string) $args['slug']) : '';
	$title   = isset($args['title']) ? sanitize_text_field((string) $args['title']) : '';
	$content = isset($args['content']) ? (string) $args['content'] : '';
	$managed_key = isset($args['managed_key']) ? sanitize_key((string) $args['managed_key']) : '';
	$repair_existing = !empty($args['repair_existing']);

	if ($slug === '' || $title === '') {
		return 0;
	}

	$existing = get_page_by_path($slug, OBJECT, 'page');

	if ($existing instanceof WP_Post) {
		if (!bvmgr_public_page_is_managed($existing, $args)) {
			return 0;
		}

		if ($repair_existing) {
			$update = [
				'ID'           => $existing->ID,
				'post_title'   => $title,
				'post_content' => $content,
			];

			if ($existing->post_status === 'trash') {
				$update['post_status'] = 'draft';
			}

			wp_update_post($update);
		}

		if ($managed_key !== '') {
			update_post_meta($existing->ID, '_vms_managed_public_page', $managed_key);
		}
		return (int) $existing->ID;
	}

	$new_id = wp_insert_post([
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
	], true);

	if (is_wp_error($new_id) || !$new_id) {
		return 0;
	}

	if ($managed_key !== '') {
		update_post_meta((int) $new_id, '_vms_managed_public_page', $managed_key);
	}

	return (int) $new_id;
}

/**
 * Create/ensure VMS public pages (Vendor Portal, Staff Portal, etc.)
 * Called from activation.
 */
function bvmgr_install_public_pages(): void
{
	$pages = function_exists('vms_required_public_pages')
		? (array) vms_required_public_pages()
		: array(
			'vendor_application' => array(
				'slug'    => 'vendor-application',
				'title'   => 'Vendor Application',
				'content' => "[vms_vendor_apply]\n",
			),
			'vendor_portal' => array(
				'slug'    => 'vendor-portal',
				'title'   => 'Vendor Portal',
				'content' => "[vms_vendor_portal]\n",
			),
			'staff_portal' => array(
				'slug'    => 'staff-portal',
				'title'   => 'Staff Portal',
				'content' => "[vms_staff_portal]\n",
			),
			'public_calendar' => array(
				'slug'    => 'events-calendar',
				'title'   => 'Public Calendar',
				'content' => "[vms_public_calendar]\n",
			),
		);

	foreach ($pages as $key => $p) {
		$p['managed_key'] = sanitize_key((string) $key);
		$page_id = bvmgr_ensure_page_exists($p);
		if ($page_id > 0) {
			update_option('vms_page_' . $key, $page_id, false);
		}
	}
}
