<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('BVMGR_Admin_Event_Plans')) {
    require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}

// The local WordPress bootstrap may load a separate installed BVM tree. Load the
// repository-mandated sibling live tree under its legacy vms_ namespace so this
// regression always exercises the synchronized Phase A implementation.
if (!function_exists('bvmgr_event_plan_status_after_deferred_calendar_publish_queue')) {
    require_once dirname(__DIR__, 3) . '/vms/includes/runtime-guards.php';
    require_once dirname(__DIR__, 3) . '/vms/includes/core/event-plan-performance.php';
    require_once dirname(__DIR__, 3) . '/vms/includes/cpt/event-plans.php';
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$createdPosts = array();
$trackedPlanIds = array();

$registerPost = static function (int $postId) use (&$createdPosts): int {
    $createdPosts[] = $postId;
    return $postId;
};

$trackPlan = static function (int $planId) use (&$trackedPlanIds): int {
    $trackedPlanIds[] = $planId;
    return $planId;
};

$clearPlanQueue = static function (int $planId): void {
    wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish', array($planId));
    wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish_recovery', array($planId));
    $clearLock = function_exists('bvmgr_event_plan_perf_job_clear_lock')
        ? 'bvmgr_event_plan_perf_job_clear_lock'
        : 'vms_event_plan_perf_job_clear_lock';
    if (function_exists($clearLock)) {
        $clearLock('calendar_publish', $planId);
    }
};

$cleanup = static function () use (&$createdPosts, &$trackedPlanIds, $clearPlanQueue): void {
    foreach ($trackedPlanIds as $planId) {
        $clearPlanQueue((int) $planId);
    }
    foreach (array_reverse($createdPosts) as $postId) {
        wp_delete_post((int) $postId, true);
    }
};

$countScheduled = static function (string $hook, array $args): int {
    $count = 0;
    $cron = _get_cron_array();
    if (!is_array($cron)) {
        return 0;
    }
    foreach ($cron as $hooks) {
        if (empty($hooks[$hook]) || !is_array($hooks[$hook])) {
            continue;
        }
        foreach ($hooks[$hook] as $event) {
            if (is_array($event) && (array) ($event['args'] ?? array()) === $args) {
                $count++;
            }
        }
    }
    return $count;
};

try {
    wp_set_current_user(1);

    $prefix = function_exists('bvmgr_event_plan_status_after_deferred_calendar_publish_queue') ? 'bvmgr_' : 'vms_';
    $schedulePublish = $prefix . 'event_plan_schedule_deferred_calendar_publish';
    $queueStatus = $prefix . 'event_plan_status_after_deferred_calendar_publish_queue';
    $healthCheck = $prefix . 'event_plan_deferred_calendar_publish_health';
    $staleAfter = $prefix . 'event_plan_deferred_calendar_publish_stale_after';
    $runWorker = $prefix . 'event_plan_run_deferred_calendar_publish';
    $recoverInterrupted = $prefix . 'event_plan_recover_interrupted_deferred_calendar_publish';
    $lockKey = $prefix . 'event_plan_perf_job_lock_key';
    $hasLock = $prefix . 'event_plan_perf_job_has_lock';

    $assert(function_exists($schedulePublish), 'Deferred calendar publish scheduler is unavailable.');
    $assert(function_exists($queueStatus), 'Deferred calendar publish workflow-status helper is unavailable.');
    $assert(function_exists($healthCheck), 'Deferred calendar publish health helper is unavailable.');
    $assert(function_exists($staleAfter), 'Deferred calendar publish stale threshold helper is unavailable.');
    $assert(function_exists($runWorker), 'Deferred calendar publish worker is unavailable.');
	$assert(function_exists('bvmgr_event_plan_workflow_success_payload'), 'Workflow success response helper is unavailable.');
    $assert(false !== has_action('vms_event_plan_deferred_calendar_publish_recovery', $recoverInterrupted), 'Deferred calendar publish recovery hook is not registered.');

    $mirrorSource = file_get_contents(dirname(__DIR__) . '/includes/cpt/event-plans.php');
    $assert(is_string($mirrorSource) && $mirrorSource !== '', 'Unable to read the mirror Event Plan implementation.');
    $assert(strpos($mirrorSource, '$publish_queued = bvmgr_event_plan_schedule_deferred_calendar_publish') !== false, 'Mirror publish workflow should distinguish queued from published.');
    $assert(strpos($mirrorSource, '$new_status = bvmgr_event_plan_status_after_deferred_calendar_publish_queue($current_status, $post_id)') !== false, 'Mirror publish workflow should verify the linked TEC event before preserving Published.');
    $assert(strpos($mirrorSource, "add_action('vms_event_plan_deferred_calendar_publish_recovery', 'bvmgr_event_plan_recover_interrupted_deferred_calendar_publish'") !== false, 'Mirror implementation should register the recovery watchdog.');
    $assert(strpos($mirrorSource, "&& \$existing_tec_post->post_status === 'publish'") !== false, 'Mirror signature fast path should require an actually Published TEC event.');
    $assert(strpos($mirrorSource, 'The original link was preserved for retry.') !== false, 'Mirror update failure should fail closed without creating another TEC event.');

    $vendorId = wp_insert_post(array(
        'post_type' => 'vms_vendor',
        'post_status' => 'publish',
        'post_title' => 'Deferred Publish Vendor',
    ), true);
    if (is_wp_error($vendorId) || (int) $vendorId <= 0) {
        throw new RuntimeException('Failed to create the deferred publish vendor fixture.');
    }
    $vendorId = $registerPost((int) $vendorId);

	$venueId = wp_insert_post(array(
		'post_type' => 'vms_venue',
		'post_status' => 'publish',
		'post_title' => 'Deferred Publish Venue',
	), true);
	if (is_wp_error($venueId) || (int) $venueId <= 0) {
		throw new RuntimeException('Failed to create the deferred publish venue fixture.');
	}
	$venueId = $registerPost((int) $venueId);

    $createPlan = static function (string $title) use ($registerPost, $trackPlan, $vendorId, $venueId): int {
        $planId = wp_insert_post(array(
            'post_type' => 'vms_event_plan',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => 'Deferred calendar publication regression body.',
        ), true);
        if (is_wp_error($planId) || (int) $planId <= 0) {
            throw new RuntimeException('Failed to create an Event Plan fixture.');
        }
        $planId = $trackPlan($registerPost((int) $planId));
        update_post_meta($planId, '_vms_event_plan_status', 'ready');
        update_post_meta($planId, '_vms_event_date', '2027-06-12');
        update_post_meta($planId, '_vms_start_time', '19:00');
        update_post_meta($planId, '_vms_end_time', '22:00');
        update_post_meta($planId, '_vms_band_vendor_id', $vendorId);
		update_post_meta($planId, '_vms_venue_id', $venueId);
        update_post_meta($planId, '_vms_comp_structure', 'flat_fee');
        update_post_meta($planId, '_vms_flat_fee_amount', '500.00');
        return $planId;
    };

    $createTecEvent = static function (string $title, int $planId = 0, string $status = 'draft') use ($registerPost): int {
        $meta = array(
            '_EventStartDate' => '2027-06-12 19:00:00',
            '_EventEndDate' => '2027-06-12 22:00:00',
        );
        if ($planId > 0) {
            $meta['_vms_event_plan_id'] = $planId;
        }
        $tecId = wp_insert_post(array(
            'post_type' => 'tribe_events',
            'post_status' => $status,
            'post_title' => $title,
            'meta_input' => $meta,
        ), true);
        if (is_wp_error($tecId) || (int) $tecId <= 0) {
            throw new RuntimeException('Failed to create a TEC event fixture.');
        }
        if ($planId > 0) {
            update_post_meta((int) $tecId, '_vms_event_plan_id', $planId);
        }
        return $registerPost((int) $tecId);
    };

    $countLinkedTecEvents = static function (int $planId): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(DISTINCT p.ID) FROM %i AS p INNER JOIN %i AS pm ON pm.post_id = p.ID WHERE p.post_type = %s AND p.post_status <> %s AND pm.meta_key = %s AND pm.meta_value = %s',
            $wpdb->posts,
            $wpdb->postmeta,
            'tribe_events',
            'trash',
            '_vms_event_plan_id',
            (string) $planId
        ));
    };

    // A Published plan may stay Published only while the exact linked TEC event is verifiably Published.
    $publishedTecPlanId = $createPlan('Published TEC Resync Plan');
    $publishedTecId = $createTecEvent('Published TEC Resync Event', $publishedTecPlanId, 'publish');
    update_post_meta($publishedTecPlanId, '_vms_event_plan_status', 'published');
    update_post_meta($publishedTecPlanId, '_vms_tec_event_id', $publishedTecId);
    $assert($queueStatus('published', $publishedTecPlanId) === 'published', 'A verified Published TEC event may preserve the Event Plan Published status during resync.');

    $draftTecPlanId = $createPlan('Draft TEC Recovery Plan');
    $draftTecId = $createTecEvent('Draft TEC Recovery Event', $draftTecPlanId);
    update_post_meta($draftTecPlanId, '_vms_event_plan_status', 'published');
    update_post_meta($draftTecPlanId, '_vms_tec_event_id', $draftTecId);
    $assert($schedulePublish($draftTecPlanId, 'publish_retry'), 'A previously Published plan with a Draft TEC event should queue recovery.');
    update_post_meta($draftTecPlanId, '_vms_event_plan_status', $queueStatus('published', $draftTecPlanId));
    $assert(get_post_meta($draftTecPlanId, '_vms_event_plan_status', true) === 'ready', 'A Draft linked TEC event must demote the queued Event Plan to Ready.');

    $missingTecPlanId = $createPlan('Missing TEC Recovery Plan');
    update_post_meta($missingTecPlanId, '_vms_event_plan_status', 'published');
    update_post_meta($missingTecPlanId, '_vms_tec_event_id', 987654321);
    $assert($schedulePublish($missingTecPlanId, 'publish_retry'), 'A previously Published plan with a missing TEC event should queue recovery.');
    update_post_meta($missingTecPlanId, '_vms_event_plan_status', $queueStatus('published', $missingTecPlanId));
    $assert(get_post_meta($missingTecPlanId, '_vms_event_plan_status', true) === 'ready', 'A missing linked TEC event must demote the queued Event Plan to Ready.');

    // Successful scheduling must be proven, remain idempotent, and leave workflow Ready.
    $successPlanId = $createPlan('Deferred Publish Success Plan');
    $recoverableTecId = $createTecEvent('Recoverable TEC Event', $successPlanId);
    $findRecoverable = $prefix . 'event_plan_find_recoverable_tec_event_id';
    $foundRecoverableTecId = $findRecoverable($successPlanId);
    $assert(
        $foundRecoverableTecId === $recoverableTecId,
        'Reverse-linked TEC recovery lookup should find the existing event; found ' . $foundRecoverableTecId
            . ', type ' . (string) get_post_type($recoverableTecId)
            . ', status ' . (string) get_post_status($recoverableTecId)
            . ', reverse meta ' . (int) get_post_meta($recoverableTecId, '_vms_event_plan_id', true)
    );
    $assert($queueStatus('ready', $successPlanId) === 'ready', 'A queued Ready plan must remain Ready.');
    $assert($queueStatus('published', $successPlanId) === 'ready', 'A Draft recovered TEC event must not preserve Published.');
    $assert($schedulePublish($successPlanId, 'publish_now'), 'Successful scheduling should return true.');
    $firstQueuedAt = (int) get_post_meta($successPlanId, '_vms_calendar_publish_queued_at', true);
    $firstAttempts = (int) get_post_meta($successPlanId, '_vms_calendar_publish_attempt_count', true);
    $assert($firstQueuedAt > 0, 'Successful scheduling should persist a queued timestamp.');
    $assert(get_post_meta($successPlanId, '_vms_calendar_publish_queue_state', true) === 'queued', 'Successful scheduling should persist queued state.');
    $assert(get_post_meta($successPlanId, '_vms_event_plan_status', true) === 'ready', 'Queuing alone must not mark the Event Plan Published.');
	$queuedWorkspaceStatus = bvmgr_event_plan_workspace_status($successPlanId);
	$queuedResponse = bvmgr_event_plan_workflow_success_payload('publish_now', $queuedWorkspaceStatus);
	$assert(($queuedResponse['state'] ?? '') === 'queued', 'The immediate Publish Now response must report queued state.');
	$assert(stripos((string) ($queuedResponse['message'] ?? ''), 'queued') !== false, 'The immediate Publish Now response must explicitly say publication is queued.');
	$assert(stripos((string) ($queuedResponse['message'] ?? ''), 'remain Ready') !== false, 'The queued response must explain that the Event Plan remains Ready until publication is confirmed.');
    $assert(false !== wp_next_scheduled('vms_event_plan_deferred_calendar_publish', array($successPlanId)), 'Successful scheduling should prove a publish worker exists.');
    $assert(false !== wp_next_scheduled('vms_event_plan_deferred_calendar_publish_recovery', array($successPlanId)), 'Successful scheduling should prove a recovery watchdog exists.');
    $assert($countScheduled('vms_event_plan_deferred_calendar_publish', array($successPlanId)) === 1, 'Successful scheduling should create exactly one publish worker.');
    $assert($countScheduled('vms_event_plan_deferred_calendar_publish_recovery', array($successPlanId)) === 1, 'Successful scheduling should create exactly one recovery watchdog.');

    $assert($schedulePublish($successPlanId, 'publish_now'), 'An already-valid queued job should return true.');
    $assert((int) get_post_meta($successPlanId, '_vms_calendar_publish_queued_at', true) === $firstQueuedAt, 'An already-valid queued job should not rewrite its queued timestamp.');
    $assert((int) get_post_meta($successPlanId, '_vms_calendar_publish_attempt_count', true) === $firstAttempts, 'An already-valid queued job should not increment attempts.');
    $assert($countScheduled('vms_event_plan_deferred_calendar_publish', array($successPlanId)) === 1, 'Repeated queue requests must not duplicate publish workers.');
    $assert($countScheduled('vms_event_plan_deferred_calendar_publish_recovery', array($successPlanId)) === 1, 'Repeated queue requests must not duplicate recovery watchdogs.');

    // A watchdog consumed before the stale threshold must re-arm for the same attempt.
    $earlyWatchdogAttempt = (int) get_post_meta($successPlanId, '_vms_calendar_publish_attempt_count', true);
    wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish', array($successPlanId));
    wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish_recovery', array($successPlanId));
    $recoverInterrupted($successPlanId);
    $rearmedWatchdogAt = wp_next_scheduled('vms_event_plan_deferred_calendar_publish_recovery', array($successPlanId));
    $assert($rearmedWatchdogAt !== false, 'An early watchdog must re-arm while queued work remains active.');
    $assert((int) $rearmedWatchdogAt > time() + MINUTE_IN_SECONDS, 'An early watchdog should re-arm for a future stale check.');
    $assert((int) $rearmedWatchdogAt <= $firstQueuedAt + $staleAfter() + (2 * MINUTE_IN_SECONDS), 'Re-arming should honor the original attempt stale window rather than restart it.');
    $assert((int) get_post_meta($successPlanId, '_vms_calendar_publish_attempt_count', true) === $earlyWatchdogAttempt, 'Re-arming must stay on the current attempt.');
    $assert((int) get_post_meta($successPlanId, '_vms_calendar_publish_watchdog_attempt', true) === $earlyWatchdogAttempt, 'The re-armed watchdog must be owned by the current attempt.');

    // Simulate WordPress consuming the worker event, then run the real worker.
    wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish', array($successPlanId));
    $runWorker($successPlanId);
    clean_post_cache($recoverableTecId);
    $assert(
		get_post_meta($successPlanId, '_vms_calendar_publish_queue_state', true) === 'complete',
		'A successful worker should complete the queue; got state ' . (string) get_post_meta($successPlanId, '_vms_calendar_publish_queue_state', true)
			. ', error ' . (string) get_post_meta($successPlanId, '_vms_calendar_publish_last_error', true)
			. ', message ' . (string) get_post_meta($successPlanId, '_vms_calendar_publish_last_error_message', true)
			. ', TEC status ' . (string) get_post_status($recoverableTecId)
	);
    $assert(get_post_meta($successPlanId, '_vms_event_plan_status', true) === 'published', 'A successful verified worker should transition the Event Plan to Published.');
	$publishedWorkspaceStatus = bvmgr_event_plan_workspace_status($successPlanId);
	$publishedResponse = bvmgr_event_plan_workflow_success_payload('publish_now', $publishedWorkspaceStatus);
	$assert(($publishedResponse['state'] ?? '') === 'published', 'The response helper must report Published only after the verified worker completes.');
    $assert(
        get_post_status($recoverableTecId) === 'publish',
        'A successful worker should leave the linked TEC event Published; got ' . (string) get_post_status($recoverableTecId)
            . ' with expected ID ' . $recoverableTecId
            . ' and linked ID ' . (int) get_post_meta($successPlanId, '_vms_tec_event_id', true)
    );
    $assert((int) get_post_meta($successPlanId, '_vms_tec_event_id', true) === $recoverableTecId, 'The worker should recover the reverse-linked TEC event rather than create a duplicate.');
    $linkedTecCount = $countLinkedTecEvents($successPlanId);
    $assert($linkedTecCount === 1, 'Successful recovery should not duplicate the TEC event.');
    $assert(false === wp_next_scheduled('vms_event_plan_deferred_calendar_publish_recovery', array($successPlanId)), 'Successful completion should clear the recovery watchdog.');

    // WordPress scheduling failure must be reported, not treated as queued success.
    $scheduleFailurePlanId = $createPlan('Deferred Publish Scheduling Failure Plan');
    $forceScheduleFailure = static function ($pre, $event, $wpError) {
        unset($wpError);
        if (is_object($event) && ($event->hook ?? '') === 'vms_event_plan_deferred_calendar_publish') {
            return new WP_Error('forced_schedule_failure', 'Forced deferred publish scheduling failure.');
        }
        return $pre;
    };
    add_filter('pre_schedule_event', $forceScheduleFailure, 10, 3);
    $assert(!$schedulePublish($scheduleFailurePlanId, 'publish_now'), 'Scheduling failure should return false.');
    remove_filter('pre_schedule_event', $forceScheduleFailure, 10);
    $assert(get_post_meta($scheduleFailurePlanId, '_vms_calendar_publish_queue_state', true) === 'failed', 'Scheduling failure should persist failed state.');
    $assert(get_post_meta($scheduleFailurePlanId, '_vms_calendar_publish_last_error', true) === 'forced_schedule_failure', 'Scheduling failure should persist the WordPress error code.');
    $assert(false === wp_next_scheduled('vms_event_plan_deferred_calendar_publish', array($scheduleFailurePlanId)), 'Scheduling failure should not leave an unproven worker.');
    $assert(get_post_meta($scheduleFailurePlanId, '_vms_event_plan_status', true) === 'ready', 'Scheduling failure must leave the workflow Ready.');

    $watchdogFailurePlanId = $createPlan('Deferred Publish Watchdog Failure Plan');
    $forceWatchdogFailure = static function ($pre, $event, $wpError) {
        unset($wpError);
        if (is_object($event) && ($event->hook ?? '') === 'vms_event_plan_deferred_calendar_publish_recovery') {
            return new WP_Error('forced_watchdog_failure', 'Forced deferred publish watchdog failure.');
        }
        return $pre;
    };
    add_filter('pre_schedule_event', $forceWatchdogFailure, 10, 3);
    $assert(!$schedulePublish($watchdogFailurePlanId, 'publish_now'), 'Watchdog scheduling failure should fail the queue request.');
    remove_filter('pre_schedule_event', $forceWatchdogFailure, 10);
    $assert(get_post_meta($watchdogFailurePlanId, '_vms_calendar_publish_last_error', true) === 'recovery_schedule_failed', 'Watchdog scheduling failure should persist a recovery-specific code.');
    $assert(false === wp_next_scheduled('vms_event_plan_deferred_calendar_publish', array($watchdogFailurePlanId)), 'Watchdog scheduling failure should roll back its publish worker.');
    $assert(false === wp_next_scheduled('vms_event_plan_deferred_calendar_publish_recovery', array($watchdogFailurePlanId)), 'Watchdog scheduling failure should not leave an unproven watchdog.');

    // A stale queued lock without a worker is interrupted, even if the transient still exists.
    $staleQueuedPlanId = $createPlan('Deferred Publish Stale Queued Plan');
    update_post_meta($staleQueuedPlanId, '_vms_calendar_publish_queue_state', 'queued');
    update_post_meta($staleQueuedPlanId, '_vms_calendar_publish_queued_at', time() - (30 * MINUTE_IN_SECONDS));
    set_transient(
        $lockKey('calendar_publish', $staleQueuedPlanId),
        array(
            'state' => 'pending',
            'request_id' => 'stale-queued-test',
            'updated_at_gmt' => gmdate('Y-m-d H:i:s', time() - (30 * MINUTE_IN_SECONDS)),
        ),
        HOUR_IN_SECONDS
    );
    $staleQueuedHealth = $healthCheck($staleQueuedPlanId, true);
    $assert(!empty($staleQueuedHealth['interrupted']), 'A stale queued lock with no worker should be detected as interrupted.');
    $assert(get_post_meta($staleQueuedPlanId, '_vms_calendar_publish_queue_state', true) === 'failed', 'A stale queued lock with no worker should surface failed state.');
    $assert(get_post_meta($staleQueuedPlanId, '_vms_calendar_publish_last_error', true) === 'interrupted_queued_worker_missing', 'A stale queued lock should persist a recoverable interruption code.');
    $assert(!$hasLock('calendar_publish', $staleQueuedPlanId), 'Interrupted queued state should clear its stale lock.');

    // A materially overdue cron row must not keep an old queued attempt healthy forever.
    $overdueWorkerPlanId = $createPlan('Deferred Publish Overdue Worker Plan');
    update_post_meta($overdueWorkerPlanId, '_vms_calendar_publish_queue_state', 'queued');
    update_post_meta($overdueWorkerPlanId, '_vms_calendar_publish_queued_at', time() - ($staleAfter() + (2 * MINUTE_IN_SECONDS)));
    $overdueWorkerAt = time() - ($staleAfter() + MINUTE_IN_SECONDS);
    $overdueScheduleResult = wp_schedule_single_event($overdueWorkerAt, 'vms_event_plan_deferred_calendar_publish', array($overdueWorkerPlanId), true);
    $assert(!is_wp_error($overdueScheduleResult) && $overdueScheduleResult !== false, 'Overdue worker fixture should be scheduled.');
    $overdueHealth = $healthCheck($overdueWorkerPlanId, true);
    $assert(empty($overdueHealth['valid_queued']), 'A worker overdue beyond the stale threshold must not be considered a valid queued worker.');
    $assert(!empty($overdueHealth['worker_schedule_is_overdue']), 'Health metadata should identify a materially overdue worker.');
    $assert(!empty($overdueHealth['interrupted']), 'An old queue with only an overdue worker should be recoverably interrupted.');
    $assert(get_post_meta($overdueWorkerPlanId, '_vms_calendar_publish_queue_state', true) === 'failed', 'An overdue orphaned queue should persist failed state.');
    $assert(false === wp_next_scheduled('vms_event_plan_deferred_calendar_publish', array($overdueWorkerPlanId)), 'Persisting an overdue interruption should remove the obsolete worker cron row.');

    // A worker that started and then lost its lock/cron event must not remain Running forever.
    $staleRunningPlanId = $createPlan('Deferred Publish Stale Running Plan');
    update_post_meta($staleRunningPlanId, '_vms_calendar_publish_queue_state', 'running');
    update_post_meta($staleRunningPlanId, '_vms_calendar_publish_started_at', time() - (30 * MINUTE_IN_SECONDS));
    set_transient(
        $lockKey('calendar_publish', $staleRunningPlanId),
        array(
            'state' => 'running',
            'request_id' => 'stale-running-test',
            'updated_at_gmt' => gmdate('Y-m-d H:i:s', time() - (30 * MINUTE_IN_SECONDS)),
        ),
        HOUR_IN_SECONDS
    );
    $recoverInterrupted($staleRunningPlanId);
    $assert(get_post_meta($staleRunningPlanId, '_vms_calendar_publish_queue_state', true) === 'failed', 'A stale running worker should be changed to a recoverable failed state.');
    $assert(get_post_meta($staleRunningPlanId, '_vms_calendar_publish_last_error', true) === 'interrupted_running_worker_lost', 'A stale running worker should persist its interruption code.');
    $assert(get_post_meta($staleRunningPlanId, '_vms_calendar_publish_recovery_needed', true) === '1', 'A stale running worker should persist recovery-needed metadata.');

    // A new retry must replace, not inherit, an obsolete watchdog from the interrupted attempt.
    $oldAttempt = max(1, (int) get_post_meta($staleRunningPlanId, '_vms_calendar_publish_attempt_count', true));
    update_post_meta($staleRunningPlanId, '_vms_calendar_publish_attempt_count', $oldAttempt);
    update_post_meta($staleRunningPlanId, '_vms_calendar_publish_watchdog_attempt', $oldAttempt);
    $obsoleteWatchdogAt = time() + MINUTE_IN_SECONDS;
    $obsoleteWatchdogResult = wp_schedule_single_event($obsoleteWatchdogAt, 'vms_event_plan_deferred_calendar_publish_recovery', array($staleRunningPlanId), true);
    $assert(!is_wp_error($obsoleteWatchdogResult) && $obsoleteWatchdogResult !== false, 'Obsolete watchdog fixture should be scheduled.');
    $assert($schedulePublish($staleRunningPlanId, 'publish_retry'), 'Interrupted running work should create a new retry attempt.');
    $replacementWatchdogAt = wp_next_scheduled('vms_event_plan_deferred_calendar_publish_recovery', array($staleRunningPlanId));
    $newAttempt = (int) get_post_meta($staleRunningPlanId, '_vms_calendar_publish_attempt_count', true);
    $assert($newAttempt === $oldAttempt + 1, 'A retry after interruption should increment the attempt exactly once.');
    $assert($replacementWatchdogAt !== false && (int) $replacementWatchdogAt > $obsoleteWatchdogAt, 'A retry must replace a too-early obsolete watchdog.');
    $assert((int) get_post_meta($staleRunningPlanId, '_vms_calendar_publish_watchdog_attempt', true) === $newAttempt, 'The replacement watchdog must belong to the new retry attempt.');

    // TEC can report an update ID while another filter prevents publication; the postcondition must catch that.
    $tecFailurePlanId = $createPlan('Deferred Publish TEC Failure Plan');
    $tecFailureId = $createTecEvent('TEC Publication Failure Event');
    update_post_meta($tecFailurePlanId, '_vms_tec_event_id', $tecFailureId);
    $forceTecDraft = static function (array $data): array {
        if (($data['post_type'] ?? '') === 'tribe_events') {
            $data['post_status'] = 'draft';
        }
        return $data;
    };
    add_filter('wp_insert_post_data', $forceTecDraft, PHP_INT_MAX, 1);
    $assert($schedulePublish($tecFailurePlanId, 'publish_now'), 'TEC failure fixture should queue successfully.');
    wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish', array($tecFailurePlanId));
    $runWorker($tecFailurePlanId);
    remove_filter('wp_insert_post_data', $forceTecDraft, PHP_INT_MAX);
    clean_post_cache($tecFailureId);
    $assert(get_post_status($tecFailureId) === 'draft', 'TEC failure fixture should remain Draft.');
    $assert(get_post_meta($tecFailurePlanId, '_vms_calendar_publish_queue_state', true) === 'failed', 'An unverified TEC publication should fail the queue.');
    $assert(get_post_meta($tecFailurePlanId, '_vms_calendar_publish_last_error', true) === 'linked_tec_event_not_published', 'TEC publication failure should persist the exact postcondition code.');
    $assert(get_post_meta($tecFailurePlanId, '_vms_event_plan_status', true) === 'ready', 'TEC publication failure must leave the Event Plan Ready.');

    // Explicit retry should queue once, preserve recovery history, and complete without another TEC event.
    $attemptsBeforeRetry = (int) get_post_meta($tecFailurePlanId, '_vms_calendar_publish_attempt_count', true);
    $assert($schedulePublish($tecFailurePlanId, 'publish_retry'), 'A failed publication should be safely retryable.');
    $retryQueuedAt = (int) get_post_meta($tecFailurePlanId, '_vms_calendar_publish_queued_at', true);
    $assert($schedulePublish($tecFailurePlanId, 'publish_retry'), 'A repeated retry request should resolve to the valid queued job.');
    $assert((int) get_post_meta($tecFailurePlanId, '_vms_calendar_publish_queued_at', true) === $retryQueuedAt, 'Repeated retry should not rewrite the queue timestamp.');
    $assert((int) get_post_meta($tecFailurePlanId, '_vms_calendar_publish_attempt_count', true) === $attemptsBeforeRetry + 1, 'Repeated retry should add exactly one attempt.');
    $assert($countScheduled('vms_event_plan_deferred_calendar_publish', array($tecFailurePlanId)) === 1, 'Repeated retry should keep exactly one publish worker.');
    $assert($countScheduled('vms_event_plan_deferred_calendar_publish_recovery', array($tecFailurePlanId)) === 1, 'Repeated retry should keep exactly one recovery watchdog.');
    $assert(get_post_meta($tecFailurePlanId, '_vms_calendar_publish_last_recovery_code', true) === 'linked_tec_event_not_published', 'Retry should retain the terminal error it recovered from.');
    wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish', array($tecFailurePlanId));
    $runWorker($tecFailurePlanId);
    clean_post_cache($tecFailureId);
    $assert(
        get_post_meta($tecFailurePlanId, '_vms_calendar_publish_queue_state', true) === 'complete',
        'Successful retry should complete the queue; got state ' . (string) get_post_meta($tecFailurePlanId, '_vms_calendar_publish_queue_state', true)
            . ', error ' . (string) get_post_meta($tecFailurePlanId, '_vms_calendar_publish_last_error', true)
            . ', TEC status ' . (string) get_post_status($tecFailureId)
    );
    $assert(get_post_meta($tecFailurePlanId, '_vms_event_plan_status', true) === 'published', 'Successful retry should transition the Event Plan to Published.');
    $assert((int) get_post_meta($tecFailurePlanId, '_vms_tec_event_id', true) === $tecFailureId, 'Successful retry should retain the same TEC event ID.');

    // A failed update of a usable linked/recovered TEC event must fail closed without creating a duplicate.
    $updateFailurePlanId = $createPlan('Deferred Publish Existing Update Failure Plan');
    $updateFailureTecId = $createTecEvent('Existing TEC Update Failure Event', $updateFailurePlanId);
    $linkedBeforeUpdateFailure = $countLinkedTecEvents($updateFailurePlanId);
    $forceTecUpdateFailure = static function ($args, int $eventId) use ($updateFailureTecId) {
        if ($eventId === $updateFailureTecId) {
            return new WP_Error('forced_tec_update_failure', 'Forced existing TEC update failure.');
        }
        return $args;
    };
    add_filter('tribe_events_event_update_args', $forceTecUpdateFailure, PHP_INT_MAX, 2);
    $assert($schedulePublish($updateFailurePlanId, 'publish_now'), 'Existing TEC update failure fixture should queue successfully.');
    wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish', array($updateFailurePlanId));
    $runWorker($updateFailurePlanId);
    remove_filter('tribe_events_event_update_args', $forceTecUpdateFailure, PHP_INT_MAX);
    clean_post_cache($updateFailureTecId);
    $assert(get_post_meta($updateFailurePlanId, '_vms_calendar_publish_queue_state', true) === 'failed', 'A failed existing TEC update should fail the deferred publication attempt.');
    $assert((int) get_post_meta($updateFailurePlanId, '_vms_tec_event_id', true) === $updateFailureTecId, 'A failed update must preserve the recovered original TEC link.');
    $assert((int) get_post_meta($updateFailurePlanId, '_vms_calendar_publish_last_tec_event_id', true) === $updateFailureTecId, 'A failed update should retain the affected TEC event ID in failure metadata.');
    $assert(get_post_meta($updateFailurePlanId, '_vms_calendar_publish_last_tec_error', true) === 'tribe_update_event_failed', 'A failed update should retain a useful TEC failure code.');
    $assert($countLinkedTecEvents($updateFailurePlanId) === $linkedBeforeUpdateFailure, 'A failed existing TEC update must not create another TEC event.');

    $assert($schedulePublish($updateFailurePlanId, 'publish_retry'), 'A later retry should continue against the preserved TEC event.');
    wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish', array($updateFailurePlanId));
    $runWorker($updateFailurePlanId);
    clean_post_cache($updateFailureTecId);
    $assert(get_post_meta($updateFailurePlanId, '_vms_calendar_publish_queue_state', true) === 'complete', 'A later retry against the original TEC event should complete.');
    $assert((int) get_post_meta($updateFailurePlanId, '_vms_tec_event_id', true) === $updateFailureTecId, 'A successful retry must keep the original TEC event ID.');
    $assert(get_post_status($updateFailureTecId) === 'publish', 'A successful retry should publish the original TEC event.');
    $assert($countLinkedTecEvents($updateFailurePlanId) === $linkedBeforeUpdateFailure, 'A successful retry must not leave a duplicate TEC event.');
    $assert(get_post_meta($updateFailurePlanId, '_vms_calendar_publish_last_tec_error', true) === '', 'Successful retry should clear the prior TEC update error metadata.');

    fwrite(STDOUT, "event plan deferred calendar publish stabilization: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'event plan deferred calendar publish stabilization: FAIL - ' . $e->getMessage() . "\n");
    $cleanup();
    exit(1);
}

$cleanup();
