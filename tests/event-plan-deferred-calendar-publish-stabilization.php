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
    $runWorker = $prefix . 'event_plan_run_deferred_calendar_publish';
    $recoverInterrupted = $prefix . 'event_plan_recover_interrupted_deferred_calendar_publish';
    $lockKey = $prefix . 'event_plan_perf_job_lock_key';
    $hasLock = $prefix . 'event_plan_perf_job_has_lock';

    $assert(function_exists($schedulePublish), 'Deferred calendar publish scheduler is unavailable.');
    $assert(function_exists($queueStatus), 'Deferred calendar publish workflow-status helper is unavailable.');
    $assert(function_exists($healthCheck), 'Deferred calendar publish health helper is unavailable.');
    $assert(function_exists($runWorker), 'Deferred calendar publish worker is unavailable.');
    $assert(false !== has_action('vms_event_plan_deferred_calendar_publish_recovery', $recoverInterrupted), 'Deferred calendar publish recovery hook is not registered.');

    $mirrorSource = file_get_contents(dirname(__DIR__) . '/includes/cpt/event-plans.php');
    $assert(is_string($mirrorSource) && $mirrorSource !== '', 'Unable to read the mirror Event Plan implementation.');
    $assert(strpos($mirrorSource, '$publish_queued = bvmgr_event_plan_schedule_deferred_calendar_publish') !== false, 'Mirror publish workflow should distinguish queued from published.');
    $assert(strpos($mirrorSource, '$new_status = bvmgr_event_plan_status_after_deferred_calendar_publish_queue($current_status)') !== false, 'Mirror publish workflow should preserve Ready while deferred publication is queued.');
    $assert(strpos($mirrorSource, "add_action('vms_event_plan_deferred_calendar_publish_recovery', 'bvmgr_event_plan_recover_interrupted_deferred_calendar_publish'") !== false, 'Mirror implementation should register the recovery watchdog.');
    $assert(strpos($mirrorSource, "&& \$existing_tec_post->post_status === 'publish'") !== false, 'Mirror signature fast path should require an actually Published TEC event.');

    $vendorId = wp_insert_post(array(
        'post_type' => 'vms_vendor',
        'post_status' => 'publish',
        'post_title' => 'Deferred Publish Vendor',
    ), true);
    if (is_wp_error($vendorId) || (int) $vendorId <= 0) {
        throw new RuntimeException('Failed to create the deferred publish vendor fixture.');
    }
    $vendorId = $registerPost((int) $vendorId);

    $createPlan = static function (string $title) use ($registerPost, $trackPlan, $vendorId): int {
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
        update_post_meta($planId, '_vms_comp_structure', 'flat_fee');
        update_post_meta($planId, '_vms_flat_fee_amount', '500.00');
        return $planId;
    };

    $createTecEvent = static function (string $title, int $planId = 0) use ($registerPost): int {
        $meta = array(
            '_EventStartDate' => '2027-06-12 19:00:00',
            '_EventEndDate' => '2027-06-12 22:00:00',
        );
        if ($planId > 0) {
            $meta['_vms_event_plan_id'] = $planId;
        }
        $tecId = wp_insert_post(array(
            'post_type' => 'tribe_events',
            'post_status' => 'draft',
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
    $assert($queueStatus('ready') === 'ready', 'A queued Ready plan must remain Ready.');
    $assert($queueStatus('published') === 'published', 'A retry of an already Published plan must preserve Published.');
    $assert($schedulePublish($successPlanId, 'publish_now'), 'Successful scheduling should return true.');
    $firstQueuedAt = (int) get_post_meta($successPlanId, '_vms_calendar_publish_queued_at', true);
    $firstAttempts = (int) get_post_meta($successPlanId, '_vms_calendar_publish_attempt_count', true);
    $assert($firstQueuedAt > 0, 'Successful scheduling should persist a queued timestamp.');
    $assert(get_post_meta($successPlanId, '_vms_calendar_publish_queue_state', true) === 'queued', 'Successful scheduling should persist queued state.');
    $assert(get_post_meta($successPlanId, '_vms_event_plan_status', true) === 'ready', 'Queuing alone must not mark the Event Plan Published.');
    $assert(false !== wp_next_scheduled('vms_event_plan_deferred_calendar_publish', array($successPlanId)), 'Successful scheduling should prove a publish worker exists.');
    $assert(false !== wp_next_scheduled('vms_event_plan_deferred_calendar_publish_recovery', array($successPlanId)), 'Successful scheduling should prove a recovery watchdog exists.');
    $assert($countScheduled('vms_event_plan_deferred_calendar_publish', array($successPlanId)) === 1, 'Successful scheduling should create exactly one publish worker.');
    $assert($countScheduled('vms_event_plan_deferred_calendar_publish_recovery', array($successPlanId)) === 1, 'Successful scheduling should create exactly one recovery watchdog.');

    $assert($schedulePublish($successPlanId, 'publish_now'), 'An already-valid queued job should return true.');
    $assert((int) get_post_meta($successPlanId, '_vms_calendar_publish_queued_at', true) === $firstQueuedAt, 'An already-valid queued job should not rewrite its queued timestamp.');
    $assert((int) get_post_meta($successPlanId, '_vms_calendar_publish_attempt_count', true) === $firstAttempts, 'An already-valid queued job should not increment attempts.');
    $assert($countScheduled('vms_event_plan_deferred_calendar_publish', array($successPlanId)) === 1, 'Repeated queue requests must not duplicate publish workers.');
    $assert($countScheduled('vms_event_plan_deferred_calendar_publish_recovery', array($successPlanId)) === 1, 'Repeated queue requests must not duplicate recovery watchdogs.');

    // Simulate WordPress consuming the worker event, then run the real worker.
    wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish', array($successPlanId));
    $runWorker($successPlanId);
    clean_post_cache($recoverableTecId);
    $assert(get_post_meta($successPlanId, '_vms_calendar_publish_queue_state', true) === 'complete', 'A successful worker should complete the queue.');
    $assert(get_post_meta($successPlanId, '_vms_event_plan_status', true) === 'published', 'A successful verified worker should transition the Event Plan to Published.');
    $assert(
        get_post_status($recoverableTecId) === 'publish',
        'A successful worker should leave the linked TEC event Published; got ' . (string) get_post_status($recoverableTecId)
            . ' with expected ID ' . $recoverableTecId
            . ' and linked ID ' . (int) get_post_meta($successPlanId, '_vms_tec_event_id', true)
    );
    $assert((int) get_post_meta($successPlanId, '_vms_tec_event_id', true) === $recoverableTecId, 'The worker should recover the reverse-linked TEC event rather than create a duplicate.');
    global $wpdb;
    $linkedTecCount = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(DISTINCT p.ID) FROM %i AS p INNER JOIN %i AS pm ON pm.post_id = p.ID WHERE p.post_type = %s AND p.post_status <> %s AND pm.meta_key = %s AND pm.meta_value = %s',
        $wpdb->posts,
        $wpdb->postmeta,
        'tribe_events',
        'trash',
        '_vms_event_plan_id',
        (string) $successPlanId
    ));
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

    fwrite(STDOUT, "event plan deferred calendar publish stabilization: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'event plan deferred calendar publish stabilization: FAIL - ' . $e->getMessage() . "\n");
    $cleanup();
    exit(1);
}

$cleanup();
