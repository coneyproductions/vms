<?php
defined('ABSPATH') || exit;

function vms_ticket_integrity_daily_hook(): string
{
	return 'vms_ticket_integrity_daily_scan';
}

function vms_ticket_integrity_spot_hook(): string
{
	return 'vms_ticket_integrity_spot_scan';
}

function vms_ticket_integrity_daily_report_hook(): string
{
	return 'vms_ticket_integrity_daily_report';
}

function vms_ticket_integrity_register_cron_schedules(array $schedules): array
{
	if (!isset($schedules['vms_ticket_integrity_fifteen_minutes'])) {
		$schedules['vms_ticket_integrity_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display' => __('Every 15 Minutes (VMS Ticket Integrity)', 'backstage-venue-manager'),
		);
	}

	return $schedules;
}
add_filter('cron_schedules', 'vms_ticket_integrity_register_cron_schedules');

function vms_ticket_integrity_next_daily_timestamp(): int
{
	$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
	$now = new DateTimeImmutable('now', $tz);
	$target = $now->setTime(3, 17, 0);
	if ($target <= $now) {
		$target = $target->modify('+1 day');
	}

	return (int) $target->getTimestamp();
}

function vms_ticket_integrity_next_daily_report_timestamp(): int
{
	$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
	$now = new DateTimeImmutable('now', $tz);
	$target = $now->setTime(6, 5, 0);
	if ($target <= $now) {
		$target = $target->modify('+1 day');
	}

	return (int) $target->getTimestamp();
}

function vms_ticket_integrity_get_scheduled_timestamps(string $hook): array
{
	$timestamps = array();
	if (!function_exists('_get_cron_array')) {
		return $timestamps;
	}

	$cron = _get_cron_array();
	foreach ((array) $cron as $timestamp => $hooks) {
		if (empty($hooks[$hook]) || !is_array($hooks[$hook])) {
			continue;
		}

		foreach ((array) $hooks[$hook] as $event) {
			$timestamps[] = absint($timestamp);
		}
	}

	sort($timestamps);
	return $timestamps;
}

function vms_ticket_integrity_unschedule_all_events(string $hook): void
{
	$timestamps = vms_ticket_integrity_get_scheduled_timestamps($hook);
	foreach ($timestamps as $timestamp) {
		wp_unschedule_event($timestamp, $hook);
	}
}

function vms_ticket_integrity_daily_schedule_health(string $hook, int $next_timestamp, int $target_timestamp): array
{
	$timestamps = vms_ticket_integrity_get_scheduled_timestamps($hook);
	$event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event($hook) : null;
	$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
	$scheduled_local = ($next_timestamp > 0 && function_exists('wp_date')) ? wp_date('H:i', $next_timestamp, $tz) : '';
	$expected_local = ($target_timestamp > 0 && function_exists('wp_date')) ? wp_date('H:i', $target_timestamp, $tz) : '';

	$health = array(
		'needs_reset' => false,
		'reason' => '',
		'timestamps' => $timestamps,
		'count' => count($timestamps),
		'scheduled_local' => $scheduled_local,
		'expected_local' => $expected_local,
	);

	if ($next_timestamp <= 0) {
		return $health;
	}

	if (count($timestamps) !== 1) {
		$health['needs_reset'] = true;
		$health['reason'] = 'duplicate_events';
		return $health;
	}

	if ($event && !empty($event->schedule) && $event->schedule !== 'daily') {
		$health['needs_reset'] = true;
		$health['reason'] = 'unexpected_recurrence';
		return $health;
	}

	if ($scheduled_local !== '' && $expected_local !== '' && $scheduled_local !== $expected_local) {
		$health['needs_reset'] = true;
		$health['reason'] = 'local_time_drift';
	}

	return $health;
}

function vms_ticket_integrity_ensure_daily_schedule(bool $enabled, string $hook, callable $timestamp_callback): int
{
	$next = absint(wp_next_scheduled($hook));
	$target = absint(call_user_func($timestamp_callback));

	if (!$enabled) {
		if ($next > 0) {
			vms_ticket_integrity_unschedule_all_events($hook);
		}
		return 0;
	}

	$health = vms_ticket_integrity_daily_schedule_health($hook, $next, $target);
	if (!empty($health['needs_reset'])) {
		vms_ticket_integrity_unschedule_all_events($hook);
		if (function_exists('vms_ticket_integrity_log_event')) {
			vms_ticket_integrity_log_event(
				'cron_schedule_repaired',
				__('Ticket integrity cron schedule was repaired.', 'backstage-venue-manager'),
				array(
					'hook' => $hook,
					'reason' => (string) ($health['reason'] ?? 'unknown'),
					'prior_count' => absint($health['count'] ?? 0),
					'prior_local_time' => (string) ($health['scheduled_local'] ?? ''),
					'expected_local_time' => (string) ($health['expected_local'] ?? ''),
				)
			);
		}
		$next = 0;
	}

	if ($next <= 0) {
		wp_schedule_event($target, 'daily', $hook);
		$next = $target;
	}

	return absint(wp_next_scheduled($hook) ?: $next);
}

function vms_ticket_integrity_payment_gateway_schedule(array $settings = array()): string
{
	if (empty($settings)) {
		$settings = function_exists('vms_ticket_integrity_get_settings')
			? vms_ticket_integrity_get_settings()
			: array();
	}

	$schedule = sanitize_key((string) ($settings['payment_gateway_health_interval'] ?? 'vms_ticket_integrity_fifteen_minutes'));
	return in_array($schedule, array('vms_ticket_integrity_fifteen_minutes', 'hourly'), true)
		? $schedule
		: 'vms_ticket_integrity_fifteen_minutes';
}

function vms_ticket_integrity_maybe_schedule_cron(): void
{
	if (function_exists('vms_should_run_runtime_maintenance') && !vms_should_run_runtime_maintenance()) {
		return;
	}
	$settings = function_exists('vms_ticket_integrity_get_settings')
		? vms_ticket_integrity_get_settings()
		: array('nightly_enabled' => 1, 'daily_report_enabled' => 0);
	$hook = vms_ticket_integrity_daily_hook();
	vms_ticket_integrity_ensure_daily_schedule(
		!empty($settings['nightly_enabled']),
		$hook,
		'vms_ticket_integrity_next_daily_timestamp'
	);

	$report_hook = vms_ticket_integrity_daily_report_hook();
	$report_next = vms_ticket_integrity_ensure_daily_schedule(
		!empty($settings['daily_report_enabled']),
		$report_hook,
		'vms_ticket_integrity_next_daily_report_timestamp'
	);
	if (function_exists('vms_ticket_integrity_patch_daily_report_state')) {
		vms_ticket_integrity_patch_daily_report_state(
			array(
				'next_scheduled_run_at' => $report_next,
			)
		);
	}

	$payment_hook = function_exists('vms_ticket_integrity_payment_gateway_health_hook')
		? vms_ticket_integrity_payment_gateway_health_hook()
		: 'vms_ticket_integrity_payment_gateway_health';
	$payment_next = wp_next_scheduled($payment_hook);
	$payment_schedule = vms_ticket_integrity_payment_gateway_schedule($settings);
	$payment_event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event($payment_hook) : null;

	if (!empty($settings['payment_gateway_health_enabled'])) {
		if (!function_exists('vms_schedule_exists') || !vms_schedule_exists($payment_schedule)) {
			return;
		}

		if ($payment_event && !empty($payment_event->schedule) && $payment_event->schedule !== $payment_schedule) {
			while ($payment_next) {
				wp_unschedule_event($payment_next, $payment_hook);
				$payment_next = wp_next_scheduled($payment_hook);
			}
			$payment_next = false;
		}

		if (!$payment_next) {
			wp_schedule_event(time() + 120, $payment_schedule, $payment_hook);
		}
		return;
	}

	while ($payment_next) {
		wp_unschedule_event($payment_next, $payment_hook);
		$payment_next = wp_next_scheduled($payment_hook);
	}
}
add_action('init', 'vms_ticket_integrity_maybe_schedule_cron', 40);

function vms_ticket_integrity_run_daily_scan(): void
{
	if (function_exists('vms_resource_fingerprint_flag')) {
		vms_resource_fingerprint_flag('cron_run', array('hook' => vms_ticket_integrity_daily_hook()));
		vms_resource_fingerprint_flag('vms_queue', array('hook' => vms_ticket_integrity_daily_hook(), 'action' => 'run'));
	}
	if (!function_exists('vms_ticket_integrity_scan_all')) {
		return;
	}

	vms_ticket_integrity_scan_all(
		array(
			'trigger' => 'cron',
			'compact_diagnostics' => true,
		)
	);
}
add_action('vms_ticket_integrity_daily_scan', 'vms_ticket_integrity_run_daily_scan');

function vms_ticket_integrity_run_payment_gateway_health_cron(): void
{
	$hook = function_exists('vms_ticket_integrity_payment_gateway_health_hook')
		? vms_ticket_integrity_payment_gateway_health_hook()
		: 'vms_ticket_integrity_payment_gateway_health';
	if (function_exists('vms_resource_fingerprint_flag')) {
		vms_resource_fingerprint_flag('cron_run', array('hook' => $hook));
		vms_resource_fingerprint_flag('vms_queue', array('hook' => $hook, 'action' => 'run'));
	}

	if (!function_exists('vms_ticket_integrity_run_payment_gateway_health_check')) {
		return;
	}

	vms_ticket_integrity_run_payment_gateway_health_check(
		array(
			'trigger' => 'cron',
			'persist' => true,
		)
	);
}
add_action('vms_ticket_integrity_payment_gateway_health', 'vms_ticket_integrity_run_payment_gateway_health_cron');

function vms_ticket_integrity_queue_spot_scan(int $plan_id, string $reason = ''): void
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0 || !function_exists('vms_ticket_integrity_scan_event_now')) {
		return;
	}

	$trace = function_exists('vms_event_plan_perf_span_start')
		? vms_event_plan_perf_span_start(
			'vms_ticket_integrity_queue_spot_scan',
			$plan_id,
			array(
				'job_name' => 'ticket_integrity_spot_scan',
				'reason' => $reason,
			)
		)
		: '';
	$profiler_active = function_exists('vms_event_plan_save_profiler_active') && vms_event_plan_save_profiler_active();
	$queue_note = 'scheduled';
	$heavy_action_status = 'scheduled';
	$heavy_action_reason = (string) $reason;

	try {
		if (function_exists('vms_event_plan_has_effective_tickets') && !vms_event_plan_has_effective_tickets($plan_id)) {
			$queue_note = 'skipped_no_effective_tickets';
			$heavy_action_status = 'skipped';
			$heavy_action_reason = 'no_effective_tickets';
			if (function_exists('vms_event_plan_perf_log')) {
				vms_event_plan_perf_log(
					'vms_ticket_integrity_queue_spot_scan',
					$plan_id,
					array(
						'job_name' => 'ticket_integrity_spot_scan',
						'reason' => $reason,
						'skipped' => 1,
						'skip_reason' => 'no_effective_tickets',
					)
				);
			}
			return;
		}

		$hook = vms_ticket_integrity_spot_hook();
		$args = array($plan_id);
		$already_scheduled = (bool) wp_next_scheduled($hook, $args);
		$already_locked = function_exists('vms_event_plan_perf_job_has_lock')
			? vms_event_plan_perf_job_has_lock('ticket_integrity_spot_scan', $plan_id)
			: false;
		$scheduled_now = false;
		if (!$already_locked && !$already_scheduled) {
			wp_schedule_single_event(time() + 90, $hook, $args);
			$scheduled_now = true;
			if (function_exists('vms_event_plan_perf_job_set_lock')) {
				vms_event_plan_perf_job_set_lock('ticket_integrity_spot_scan', $plan_id, 'pending', 15 * MINUTE_IN_SECONDS);
			}
		}

		if ($already_locked) {
			$queue_note = 'already_locked';
			$heavy_action_status = 'skipped';
			$heavy_action_reason = 'already_locked';
		} elseif ($already_scheduled) {
			$queue_note = 'already_scheduled';
			$heavy_action_status = 'skipped';
			$heavy_action_reason = 'already_scheduled';
		}

		if (function_exists('vms_resource_fingerprint_flag')) {
			vms_resource_fingerprint_flag('vms_queue', array(
				'hook' => $hook,
				'plan_id' => $plan_id,
				'reason' => $reason,
				'already_scheduled' => $already_scheduled ? 1 : 0,
				'already_locked' => $already_locked ? 1 : 0,
			));
		}
		if (function_exists('vms_event_plan_perf_log')) {
			vms_event_plan_perf_log(
				'vms_ticket_integrity_queue_spot_scan',
				$plan_id,
				array(
					'job_name' => 'ticket_integrity_spot_scan',
					'reason' => $reason,
					'already_scheduled' => $already_scheduled ? 1 : 0,
					'already_locked' => $already_locked ? 1 : 0,
					'scheduled_now' => $scheduled_now ? 1 : 0,
				)
			);
		}

		// 0.2.24.656: avoid writing a duplicate integrity log row on every save/meta
		// touch while a spot scan for the same plan is already queued. The cron queue
		// itself was already deduped; this keeps Event Plan saves from creating extra
		// logging churn under repeated Draft/Ready/Update actions.
		if ($scheduled_now && function_exists('vms_ticket_integrity_log_event')) {
			vms_ticket_integrity_log_event(
				'spot_scan_queued',
				__('Ticket integrity spot scan queued.', 'backstage-venue-manager'),
				array(
					'plan_id' => $plan_id,
					'reason' => $reason,
				)
			);
		}
	} finally {
		if (function_exists('vms_event_plan_save_profiler_note')) {
			vms_event_plan_save_profiler_note('ticket_integrity_spot_scan', $queue_note);
		}
		if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
			vms_event_plan_save_profiler_note_heavy_action('ticket_integrity_spot_scan', $heavy_action_status, $heavy_action_reason);
		}

		// Publish transitions queue Ticket Integrity before save_post starts, so the
		// Event Plan save profiler is not active yet. Defer that note so the follow-up
		// save profile can show that publish queued/skipped the spot scan.
		if (!$profiler_active && $reason === 'event_plan_publish') {
			if (function_exists('vms_event_plan_save_profiler_defer_note_for_post')) {
				vms_event_plan_save_profiler_defer_note_for_post($plan_id, 'ticket_integrity_spot_scan', $queue_note);
			}
			if (function_exists('vms_event_plan_save_profiler_defer_heavy_action_for_post')) {
				vms_event_plan_save_profiler_defer_heavy_action_for_post($plan_id, 'ticket_integrity_spot_scan', $heavy_action_status, $heavy_action_reason);
			}
		}

		if (function_exists('vms_event_plan_perf_span_finish')) {
			vms_event_plan_perf_span_finish(
				'vms_ticket_integrity_queue_spot_scan',
				$plan_id,
				$trace,
				array(
					'job_name' => 'ticket_integrity_spot_scan',
					'reason' => $reason,
					'queue_note' => $queue_note,
				)
			);
		}
	}
}

function vms_ticket_integrity_run_spot_scan(int $plan_id): void
{
	$plan_id = absint($plan_id);
	if (function_exists('vms_resource_fingerprint_flag')) {
		vms_resource_fingerprint_flag('cron_run', array('hook' => vms_ticket_integrity_spot_hook(), 'plan_id' => $plan_id));
		vms_resource_fingerprint_flag('vms_queue', array('hook' => vms_ticket_integrity_spot_hook(), 'plan_id' => $plan_id, 'action' => 'run'));
	}
	$trace = function_exists('vms_event_plan_perf_span_start')
		? vms_event_plan_perf_span_start('vms_ticket_integrity_run_spot_scan', $plan_id, array('job_name' => 'ticket_integrity_spot_scan'))
		: '';
	$lock = function_exists('vms_event_plan_perf_job_get_lock')
		? vms_event_plan_perf_job_get_lock('ticket_integrity_spot_scan', $plan_id)
		: array();
	if (($lock['state'] ?? '') === 'running') {
		if (function_exists('vms_event_plan_perf_log')) {
			vms_event_plan_perf_log(
				'vms_ticket_integrity_run_spot_scan',
				$plan_id,
				array(
					'job_name' => 'ticket_integrity_spot_scan',
					'skipped' => 1,
					'skip_reason' => 'job_already_running',
				)
			);
		}
		if (function_exists('vms_event_plan_perf_span_finish')) {
			vms_event_plan_perf_span_finish('vms_ticket_integrity_run_spot_scan', $plan_id, $trace, array('job_name' => 'ticket_integrity_spot_scan', 'skipped' => 1));
		}
		return;
	}
	if (!function_exists('vms_ticket_integrity_scan_event_now')) {
		if (function_exists('vms_event_plan_perf_job_clear_lock')) {
			vms_event_plan_perf_job_clear_lock('ticket_integrity_spot_scan', $plan_id);
		}
		if (function_exists('vms_event_plan_perf_span_finish')) {
			vms_event_plan_perf_span_finish('vms_ticket_integrity_run_spot_scan', $plan_id, $trace, array('job_name' => 'ticket_integrity_spot_scan', 'skipped' => 1));
		}
		return;
	}
	if (function_exists('vms_event_plan_has_effective_tickets') && !vms_event_plan_has_effective_tickets($plan_id)) {
		if (function_exists('vms_event_plan_perf_log')) {
			vms_event_plan_perf_log(
				'vms_ticket_integrity_run_spot_scan',
				$plan_id,
				array(
					'job_name' => 'ticket_integrity_spot_scan',
					'skipped' => 1,
					'skip_reason' => 'no_effective_tickets',
				)
			);
		}
		if (function_exists('vms_event_plan_perf_job_clear_lock')) {
			vms_event_plan_perf_job_clear_lock('ticket_integrity_spot_scan', $plan_id);
		}
		if (function_exists('vms_event_plan_perf_span_finish')) {
			vms_event_plan_perf_span_finish('vms_ticket_integrity_run_spot_scan', $plan_id, $trace, array('job_name' => 'ticket_integrity_spot_scan', 'skipped' => 1));
		}
		return;
	}

	if (function_exists('vms_event_plan_perf_job_set_lock')) {
		vms_event_plan_perf_job_set_lock('ticket_integrity_spot_scan', $plan_id, 'running', 15 * MINUTE_IN_SECONDS);
	}

	try {
		vms_ticket_integrity_scan_event_now(
			$plan_id,
			array(
				'trigger' => 'spot_scan',
			)
		);
	} finally {
		if (function_exists('vms_event_plan_perf_job_clear_lock')) {
			vms_event_plan_perf_job_clear_lock('ticket_integrity_spot_scan', $plan_id);
		}
		if (function_exists('vms_event_plan_perf_span_finish')) {
			vms_event_plan_perf_span_finish('vms_ticket_integrity_run_spot_scan', $plan_id, $trace, array('job_name' => 'ticket_integrity_spot_scan'));
		}
	}
}
add_action('vms_ticket_integrity_spot_scan', 'vms_ticket_integrity_run_spot_scan', 10, 1);

function vms_ticket_integrity_plan_save_request_action(array $source): string
{
	return vms_request_read_key($source, 'vms_event_plan_action');
}

function vms_ticket_integrity_plan_save_should_queue(int $post_id, WP_Post $post, bool $update): bool
{
	$post_id = absint($post_id);
	if ($post_id <= 0 || $post->post_type !== 'vms_event_plan') {
		return false;
	}

	if (!in_array($post->post_status, array('publish', 'draft', 'pending', 'private'), true)) {
		return false;
	}

	$request_action = vms_ticket_integrity_plan_save_request_action($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- save_post already represents the accepted Event Plan save boundary; this helper only gates whether an existing post-save integrity scan should be queued.
	if (in_array($request_action, array('publish_now', 'mark_cancelled', 'run_live_refunds_now'), true)) {
		return true;
	}

	// 0.2.24.660: normal WordPress editor saves are intentionally treated as
	// lightweight Event Plan shell saves. A content-only Update on a published
	// plan should not queue Ticket Integrity. Real ticketing changes are already
	// watched through ticketing config/sync meta updates below, while publish and
	// cancellation actions still queue through their explicit action/transition
	// paths. The filter exists only as an escape hatch for legacy installs.
	$queue_general_save = (bool) apply_filters('vms_ticket_integrity_queue_on_general_plan_save', false, $post_id, $post, $update);
	if (!$queue_general_save) {
		if (function_exists('vms_event_plan_save_profiler_note')) {
			vms_event_plan_save_profiler_note('ticket_integrity_plan_save', 'skipped_general_editor_save');
		}
		if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
			vms_event_plan_save_profiler_note_heavy_action('ticket_integrity_plan_save', 'skipped', 'general_editor_save');
		}
		return false;
	}

	$throttle = (int) apply_filters('vms_ticket_integrity_plan_save_queue_throttle_seconds', 300, $post_id, $post, $update);
	if ($throttle > 0) {
		$last = (int) get_post_meta($post_id, '_vms_ticket_integrity_last_plan_save_queue_at', true);
		if ($last > 0 && (time() - $last) < $throttle) {
			if (function_exists('vms_event_plan_save_profiler_note')) {
				vms_event_plan_save_profiler_note('ticket_integrity_plan_save', 'skipped_throttled');
			}
			if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
				vms_event_plan_save_profiler_note_heavy_action('ticket_integrity_plan_save', 'skipped', 'throttled');
			}
			return false;
		}
	}

	return true;
}

function vms_ticket_integrity_watch_plan_save(int $post_id, WP_Post $post, bool $update): void
{
	$deferred_state = function_exists('vms_event_plan_save_profiler_deferred_state_for_post')
		? vms_event_plan_save_profiler_deferred_state_for_post($post_id)
		: array();
	$deferred_context = is_array($deferred_state['context'] ?? null) ? $deferred_state['context'] : array();
	$trace = function_exists('vms_event_plan_perf_span_start')
		? vms_event_plan_perf_span_start(
			'vms_ticket_integrity_watch_plan_save',
			$post_id,
			array(
				'create' => $update ? 0 : 1,
				'update' => $update ? 1 : 0,
				'old_status' => sanitize_key((string) ($deferred_context['transition_old_status'] ?? '')),
				'new_status' => sanitize_key((string) ($deferred_context['transition_new_status'] ?? $post->post_status)),
				'job_name' => 'ticket_integrity_watch_plan_save',
			)
		)
		: '';

	if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
		if (function_exists('vms_event_plan_perf_span_finish')) {
			vms_event_plan_perf_span_finish('vms_ticket_integrity_watch_plan_save', $post_id, $trace, array('job_name' => 'ticket_integrity_watch_plan_save', 'skipped' => 1));
		}
		return;
	}

	if (!vms_ticket_integrity_plan_save_should_queue($post_id, $post, $update)) {
		if (function_exists('vms_event_plan_perf_span_finish')) {
			vms_event_plan_perf_span_finish('vms_ticket_integrity_watch_plan_save', $post_id, $trace, array('job_name' => 'ticket_integrity_watch_plan_save', 'skipped' => 1));
		}
		return;
	}

	try {
		vms_ticket_integrity_queue_spot_scan($post_id, $update ? 'event_plan_save' : 'event_plan_create');
		if (!function_exists('vms_event_plan_has_effective_tickets') || vms_event_plan_has_effective_tickets($post_id)) {
			update_post_meta(absint($post_id), '_vms_ticket_integrity_last_plan_save_queue_at', time());
		}
	} finally {
		if (function_exists('vms_event_plan_perf_span_finish')) {
			vms_event_plan_perf_span_finish('vms_ticket_integrity_watch_plan_save', $post_id, $trace, array('job_name' => 'ticket_integrity_watch_plan_save'));
		}
	}
}
add_action('save_post_vms_event_plan', 'vms_ticket_integrity_watch_plan_save', 20, 3);

function vms_ticket_integrity_watch_publish_transition(string $new_status, string $old_status, WP_Post $post): void
{
	$plan_id = 0;
	if ($post->post_type === 'vms_event_plan') {
		$plan_id = absint($post->ID);
	} elseif ($post->post_type === 'tribe_events' && function_exists('vms_ticketing_v2_find_plan_id_by_tec_event_id')) {
		$plan_id = absint(vms_ticketing_v2_find_plan_id_by_tec_event_id((int) $post->ID));
	}
	$trace = function_exists('vms_event_plan_perf_span_start')
		? vms_event_plan_perf_span_start(
			'vms_ticket_integrity_watch_publish_transition',
			$plan_id,
			array(
				'create' => in_array($old_status, array('new', 'auto-draft'), true) ? 1 : 0,
				'update' => in_array($old_status, array('new', 'auto-draft'), true) ? 0 : 1,
				'old_status' => $old_status,
				'new_status' => $new_status,
				'job_name' => 'ticket_integrity_watch_publish_transition',
				'post_type' => $post->post_type,
			)
		)
		: '';

	if ($new_status !== 'publish' || $old_status === 'publish') {
		if (function_exists('vms_event_plan_perf_span_finish')) {
			vms_event_plan_perf_span_finish('vms_ticket_integrity_watch_publish_transition', $plan_id, $trace, array('job_name' => 'ticket_integrity_watch_publish_transition', 'skipped' => 1));
		}
		return;
	}

	if ($post->post_type === 'vms_event_plan') {
		try {
			vms_ticket_integrity_queue_spot_scan((int) $post->ID, 'event_plan_publish');
		} finally {
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_ticket_integrity_watch_publish_transition', $plan_id, $trace, array('job_name' => 'ticket_integrity_watch_publish_transition', 'post_type' => $post->post_type));
			}
		}
		return;
	}

	if ($post->post_type === 'tribe_events' && function_exists('vms_ticketing_v2_find_plan_id_by_tec_event_id')) {
		if ($plan_id > 0) {
			try {
				vms_ticket_integrity_queue_spot_scan($plan_id, 'tec_event_publish');
			} finally {
				if (function_exists('vms_event_plan_perf_span_finish')) {
					vms_event_plan_perf_span_finish('vms_ticket_integrity_watch_publish_transition', $plan_id, $trace, array('job_name' => 'ticket_integrity_watch_publish_transition', 'post_type' => $post->post_type));
				}
			}
			return;
		}
	}

	if (function_exists('vms_event_plan_perf_span_finish')) {
		vms_event_plan_perf_span_finish('vms_ticket_integrity_watch_publish_transition', $plan_id, $trace, array('job_name' => 'ticket_integrity_watch_publish_transition', 'post_type' => $post->post_type, 'skipped' => 1));
	}
}
add_action('transition_post_status', 'vms_ticket_integrity_watch_publish_transition', 20, 3);

function vms_ticket_integrity_watch_ticketing_meta(int $meta_id, int $object_id, string $meta_key, $meta_value): void
{
	unset($meta_id, $meta_value);

	$object_id = absint($object_id);
	if ($object_id <= 0 || get_post_type($object_id) !== 'vms_event_plan') {
		return;
	}

	$watched_keys = array(
		'_vms_ticketing_enabled_override',
	);
	if (function_exists('vms_ticketing_v2_k')) {
		$watched_keys[] = vms_ticketing_v2_k('config');
		$watched_keys[] = vms_ticketing_v2_k('sync');
	}

	$meta_key = (string) $meta_key;
	if (!in_array($meta_key, array_filter($watched_keys), true)) {
		return;
	}

	if (function_exists('vms_event_plan_perf_log')) {
		vms_event_plan_perf_log(
			'vms_ticket_integrity_watch_ticketing_meta',
			$object_id,
			array(
				'job_name' => 'ticket_integrity_ticketing_meta',
				'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This diagnostic payload field records the exact watched metadata key; it is not a WordPress query argument.
			)
		);
	}
	vms_ticket_integrity_queue_spot_scan($object_id, 'ticketing_meta_update');
}
add_action('updated_post_meta', 'vms_ticket_integrity_watch_ticketing_meta', 10, 4);
add_action('added_post_meta', 'vms_ticket_integrity_watch_ticketing_meta', 10, 4);

function vms_ticket_integrity_watch_vms_settings_change($old_value, $new_value, string $option): void
{
	unset($option);

	$old = is_array($old_value) ? $old_value : array();
	$new = is_array($new_value) ? $new_value : array();
	if (($old['ticketing_enabled_default'] ?? null) === ($new['ticketing_enabled_default'] ?? null)) {
		return;
	}

	if (!wp_next_scheduled(vms_ticket_integrity_daily_hook())) {
		wp_schedule_single_event(time() + 180, vms_ticket_integrity_daily_hook());
	}
	if (function_exists('vms_ticket_integrity_log_event')) {
		vms_ticket_integrity_log_event(
			'full_scan_queued',
			__('Ticket integrity full scan queued after VMS settings change.', 'backstage-venue-manager'),
			array('option' => 'vms_settings')
		);
	}
}
add_action('update_option_vms_settings', 'vms_ticket_integrity_watch_vms_settings_change', 10, 3);

function vms_ticket_integrity_run_daily_report(): void
{
	if (!function_exists('vms_ticket_integrity_send_state_of_range_report')) {
		return;
	}
	if (function_exists('vms_ticket_integrity_patch_daily_report_state')) {
		vms_ticket_integrity_patch_daily_report_state(
			array(
				'last_scheduled_run_at' => time(),
				'next_scheduled_run_at' => absint(wp_next_scheduled(vms_ticket_integrity_daily_report_hook())),
			)
		);
	}

	vms_ticket_integrity_send_state_of_range_report('cron');
}
add_action('vms_ticket_integrity_daily_report', 'vms_ticket_integrity_run_daily_report');
