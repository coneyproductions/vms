<?php
defined('ABSPATH') || exit;

/**
 * Lightweight Event Plan save profiler and module dirty map.
 *
 * 0.2.24.661 expanded the earlier slow-save profiler into an always-on,
 * compact Event Plan save diagnostic. 0.2.24.662 adds the missing active-state
 * helper required by downstream save guards. 0.2.24.663 adds publish/status
 * transition diagnostics so publish-path work can be measured separately from
 * ordinary WordPress Updates. It records which major Event Plan modules were
 * touched, which heavy work was skipped/triggered, and enough timing/meta detail
 * for staging/local testing without storing full ticket/customer payloads.
 */

function bvmgr_event_plan_save_profiler_threshold_seconds(): float
{
    // 0.2.24.661+: record every Event Plan save by default so operators/testers
    // can prove lightweight saves stayed lightweight. Sites can raise this with
    // the existing filter/constant if they only want slow-save records.
    $threshold = 0.0;
    if (defined('VMS_EVENT_PLAN_SAVE_PROFILER_THRESHOLD')) {
        $threshold = (float) VMS_EVENT_PLAN_SAVE_PROFILER_THRESHOLD;
    }

    /**
     * Filter the slow-save threshold before a profile is stored/logged.
     * Return 0 to record every Event Plan save.
     */
    $threshold = (float) apply_filters('vms_event_plan_save_profiler_threshold_seconds', $threshold);
    return max(0.0, $threshold);
}

function bvmgr_event_plan_save_profiler_enabled(): bool
{
    if (defined('VMS_EVENT_PLAN_SAVE_PROFILER_DISABLED') && VMS_EVENT_PLAN_SAVE_PROFILER_DISABLED) {
        return false;
    }

    return (bool) apply_filters('vms_event_plan_save_profiler_enabled', true);
}

function bvmgr_event_plan_save_profiler_recording_enabled(): bool
{
	if (!bvmgr_event_plan_save_profiler_enabled()) {
		return false;
	}

	if (defined('VMS_EVENT_PLAN_SAVE_PROFILER_RECORDING_DISABLED') && VMS_EVENT_PLAN_SAVE_PROFILER_RECORDING_DISABLED) {
		return false;
	}

	$enabled = defined('VMS_EP_PERF_TRACE') && VMS_EP_PERF_TRACE;
	if (function_exists('bvmgr_event_plan_perf_trace_enabled')) {
		$enabled = bvmgr_event_plan_perf_trace_enabled();
	}

	return (bool) apply_filters('vms_event_plan_save_profiler_recording_enabled', $enabled);
}

function bvmgr_event_plan_save_profiler_state(): array
{
    $state = $GLOBALS['bvmgr_event_plan_save_profiler_state'] ?? array();
    return is_array($state) ? $state : array();
}

function bvmgr_event_plan_save_profiler_active(): bool
{
    $state = bvmgr_event_plan_save_profiler_state();
    return !empty($state['active']);
}

function bvmgr_event_plan_save_profiler_sanitize_note_value($value): string
{
    if (is_scalar($value) || $value === null) {
        return sanitize_text_field((string) $value);
    }

    $encoded = wp_json_encode($value);
    return sanitize_text_field(is_string($encoded) ? $encoded : '');
}

function bvmgr_event_plan_save_profiler_deferred_state_for_post(int $post_id): array
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return array();
    }

    $deferred = $GLOBALS['bvmgr_event_plan_save_profiler_deferred'] ?? array();
    $state = is_array($deferred[$post_id] ?? null) ? $deferred[$post_id] : array();
    return $state;
}

function bvmgr_event_plan_save_profiler_save_deferred_state_for_post(int $post_id, array $state): void
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return;
    }

    if (!isset($GLOBALS['bvmgr_event_plan_save_profiler_deferred']) || !is_array($GLOBALS['bvmgr_event_plan_save_profiler_deferred'])) {
        $GLOBALS['bvmgr_event_plan_save_profiler_deferred'] = array();
    }

    $GLOBALS['bvmgr_event_plan_save_profiler_deferred'][$post_id] = $state;
}

function bvmgr_event_plan_save_profiler_clear_deferred_state_for_post(int $post_id): void
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return;
    }

    if (isset($GLOBALS['bvmgr_event_plan_save_profiler_deferred'][$post_id])) {
        unset($GLOBALS['bvmgr_event_plan_save_profiler_deferred'][$post_id]);
    }
}

function bvmgr_event_plan_save_profiler_defer_context_for_post(int $post_id, array $context): void
{
    $post_id = absint($post_id);
    if ($post_id <= 0 || empty($context)) {
        return;
    }

    $state = bvmgr_event_plan_save_profiler_deferred_state_for_post($post_id);
    if (!isset($state['context']) || !is_array($state['context'])) {
        $state['context'] = array();
    }

    foreach ($context as $key => $value) {
        $key = sanitize_key((string) $key);
        if ($key === '') {
            continue;
        }
        if (is_array($value)) {
            $state['context'][$key] = array_map('sanitize_text_field', array_map('strval', $value));
        } else {
            $state['context'][$key] = sanitize_text_field((string) $value);
        }
    }

    bvmgr_event_plan_save_profiler_save_deferred_state_for_post($post_id, $state);
}

function bvmgr_event_plan_save_profiler_defer_note_for_post(int $post_id, string $key, $value): void
{
    $post_id = absint($post_id);
    $key = sanitize_key($key);
    if ($post_id <= 0 || $key === '') {
        return;
    }

    $state = bvmgr_event_plan_save_profiler_deferred_state_for_post($post_id);
    if (!isset($state['notes']) || !is_array($state['notes'])) {
        $state['notes'] = array();
    }
    if (!isset($state['notes'][$key]) || !is_array($state['notes'][$key])) {
        $state['notes'][$key] = array();
    }

    $state['notes'][$key][] = bvmgr_event_plan_save_profiler_sanitize_note_value($value);
    $state['notes'][$key] = array_slice(array_values(array_unique(array_filter($state['notes'][$key]))), -10);
    bvmgr_event_plan_save_profiler_save_deferred_state_for_post($post_id, $state);
}

function bvmgr_event_plan_save_profiler_defer_heavy_action_for_post(int $post_id, string $action, string $status, string $reason = ''): void
{
    $post_id = absint($post_id);
    $action = sanitize_key($action);
    $status = sanitize_key($status);
    if ($post_id <= 0 || $action === '' || $status === '') {
        return;
    }

    $state = bvmgr_event_plan_save_profiler_deferred_state_for_post($post_id);
    if (!isset($state['heavy_actions']) || !is_array($state['heavy_actions'])) {
        $state['heavy_actions'] = array();
    }

    $state['heavy_actions'][$action] = array(
        'status' => $status,
        'reason' => sanitize_text_field($reason),
        'recorded_at_gmt' => gmdate('Y-m-d H:i:s'),
        'deferred' => true,
    );

    bvmgr_event_plan_save_profiler_save_deferred_state_for_post($post_id, $state);
}

function bvmgr_event_plan_save_profiler_force_effective_meta_key_for_post(int $post_id, string $meta_key): void
{
	$post_id = absint($post_id);
	$meta_key = sanitize_key($meta_key);
	if ($post_id <= 0 || $meta_key === '' || bvmgr_event_plan_save_profiler_ignored_meta_key($meta_key)) {
		return;
	}

	$state = bvmgr_event_plan_save_profiler_state();
	if (!empty($state['active']) && absint($state['post_id'] ?? 0) === $post_id) {
		if (!isset($state['forced_effective_meta_keys']) || !is_array($state['forced_effective_meta_keys'])) {
			$state['forced_effective_meta_keys'] = array();
		}
		$state['forced_effective_meta_keys'][$meta_key] = true;
		$GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;
		return;
	}

	$state = bvmgr_event_plan_save_profiler_deferred_state_for_post($post_id);
	if (!isset($state['forced_effective_meta_keys']) || !is_array($state['forced_effective_meta_keys'])) {
		$state['forced_effective_meta_keys'] = array();
	}
	$state['forced_effective_meta_keys'][$meta_key] = true;
	bvmgr_event_plan_save_profiler_save_deferred_state_for_post($post_id, $state);
}

function bvmgr_event_plan_save_profiler_capture_pre_update_context(int $post_id, array $data): void
{
    if (!bvmgr_event_plan_save_profiler_enabled()) {
        return;
    }

    $post_id = absint($post_id);
    if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
        return;
    }

    $before = get_post($post_id);
    if (!$before instanceof WP_Post || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    $previous_status = sanitize_key((string) $before->post_status);
    $incoming_status = sanitize_key((string) ($data['post_status'] ?? ''));
    $incoming_title = isset($data['post_title']) ? (string) $data['post_title'] : (string) $before->post_title;
    $incoming_content = isset($data['post_content']) ? (string) $data['post_content'] : (string) $before->post_content;
    $incoming_excerpt = isset($data['post_excerpt']) ? (string) $data['post_excerpt'] : (string) $before->post_excerpt;

    $field_changes = array();
    if ($incoming_status !== '' && $incoming_status !== $previous_status) {
        $field_changes[] = 'status';
    }
    if ($incoming_title !== (string) $before->post_title) {
        $field_changes[] = 'title';
    }
    if (sha1($incoming_content) !== sha1((string) $before->post_content)) {
        $field_changes[] = 'content';
    }
    if (sha1($incoming_excerpt) !== sha1((string) $before->post_excerpt)) {
        $field_changes[] = 'excerpt';
    }

    bvmgr_event_plan_save_profiler_defer_context_for_post($post_id, array(
        'previous_status' => $previous_status,
        'incoming_status' => $incoming_status,
        'previous_title_hash' => sha1((string) $before->post_title),
        'incoming_title_hash' => sha1($incoming_title),
        'previous_content_hash' => sha1((string) $before->post_content),
        'incoming_content_hash' => sha1($incoming_content),
        'previous_excerpt_hash' => sha1((string) $before->post_excerpt),
        'incoming_excerpt_hash' => sha1($incoming_excerpt),
        'pre_update_field_changes' => $field_changes,
        'pre_update_captured_at_gmt' => gmdate('Y-m-d H:i:s'),
    ));

    if (!empty($field_changes)) {
        bvmgr_event_plan_save_profiler_defer_note_for_post($post_id, 'post_field_changes', implode(',', $field_changes));
    }
}
add_action('pre_post_update', 'bvmgr_event_plan_save_profiler_capture_pre_update_context', 1, 2);

function bvmgr_event_plan_save_profiler_capture_status_transition(string $new_status, string $old_status, WP_Post $post): void
{
    if (!bvmgr_event_plan_save_profiler_enabled() || $post->post_type !== 'vms_event_plan') {
        return;
    }

    $post_id = absint($post->ID);
    if ($post_id <= 0 || $new_status === $old_status) {
        return;
    }

    bvmgr_event_plan_save_profiler_defer_context_for_post($post_id, array(
        'transition_old_status' => sanitize_key($old_status),
        'transition_new_status' => sanitize_key($new_status),
        'transition_captured_at_gmt' => gmdate('Y-m-d H:i:s'),
    ));
    bvmgr_event_plan_save_profiler_defer_note_for_post($post_id, 'status_transition', sanitize_key($old_status) . '_to_' . sanitize_key($new_status));

    if ($new_status === 'publish' && $old_status !== 'publish') {
        bvmgr_event_plan_save_profiler_defer_note_for_post($post_id, 'publish_transition', 'entered_publish');
    } elseif ($old_status === 'publish' && $new_status !== 'publish') {
        bvmgr_event_plan_save_profiler_defer_note_for_post($post_id, 'publish_transition', 'left_publish');
    }
}
add_action('transition_post_status', 'bvmgr_event_plan_save_profiler_capture_status_transition', 1, 3);

function bvmgr_event_plan_save_profiler_modules(): array
{
    return array(
        'core' => __('Core Event Details', 'backstage-venue-manager'),
        'tickets' => __('Tickets & Add-ons', 'backstage-venue-manager'),
        'vendors' => __('Lineup & Vendors', 'backstage-venue-manager'),
        'staffing' => __('Staffing', 'backstage-venue-manager'),
        'finance' => __('Compensation / Finance', 'backstage-venue-manager'),
        'marketing' => __('Marketing / Promo', 'backstage-venue-manager'),
        'agreements' => __('Agreements', 'backstage-venue-manager'),
        'ops' => __('Ops / Guest List', 'backstage-venue-manager'),
    );
}

function bvmgr_event_plan_save_profiler_normalize_module(string $module): string
{
    $module = sanitize_key($module);
    $aliases = array(
        'ticketing' => 'tickets',
        'ticket' => 'tickets',
        'addons' => 'tickets',
        'add_ons' => 'tickets',
        'lineup' => 'vendors',
        'vendor' => 'vendors',
        'compensation' => 'finance',
        'financial' => 'finance',
        'ads' => 'marketing',
        'meta_ads' => 'marketing',
        'promo' => 'marketing',
        'guest_list' => 'ops',
        'scanner' => 'ops',
    );

    if (isset($aliases[$module])) {
        $module = $aliases[$module];
    }

    return isset(bvmgr_event_plan_save_profiler_modules()[$module]) ? $module : 'core';
}

function bvmgr_event_plan_save_profiler_empty_dirty_map(): array
{
    return array_fill_keys(array_keys(bvmgr_event_plan_save_profiler_modules()), false);
}

function bvmgr_event_plan_save_profiler_post_data(): array
{
    static $request = null;
    if (is_array($request)) {
        return $request;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Save-profiler reads request context only for diagnostics and never authorizes or mutates state.
    $request = (isset($_POST) && is_array($_POST)) ? wp_unslash($_POST) : array();
    return is_array($request) ? $request : array();
}

function bvmgr_event_plan_save_profiler_request_keys(): array
{
    $request = bvmgr_event_plan_save_profiler_post_data();
    if (empty($request)) {
        return array();
    }

    $keys = array();
    foreach (array_keys($request) as $key) {
        $key = is_scalar($key) ? sanitize_key((string) wp_unslash($key)) : '';
        if ($key !== '') {
            $keys[] = $key;
        }
    }

    $keys = array_values(array_unique($keys));
    sort($keys, SORT_STRING);
    return array_slice($keys, 0, 80);
}

function bvmgr_event_plan_save_profiler_classify_key(string $key): string
{
    $key = strtolower($key);

    if ($key === '') {
        return 'core';
    }

    $ticket_needles = array('ticket', 'ticketing', 'add_on', 'addon', 'capacity', 'inventory', 'entitlement', 'tec_event', 'tribe_ticket', 'woocommerce_product');
    foreach ($ticket_needles as $needle) {
        if (strpos($key, $needle) !== false) {
            return 'tickets';
        }
    }

    $staff_needles = array('staff', 'shift', 'role_assignment', 'schedule_template', 'tabc', 'qualification');
    foreach ($staff_needles as $needle) {
        if (strpos($key, $needle) !== false) {
            return 'staffing';
        }
    }

    $vendor_needles = array('vendor', 'artist', 'lineup', 'supporting', 'opener', 'headliner', 'food_truck', 'secondary_vendor');
    foreach ($vendor_needles as $needle) {
        if (strpos($key, $needle) !== false) {
            return 'vendors';
        }
    }

    $finance_needles = array('pay', 'fee', 'compensation', 'expense', 'deposit', 'bonus', 'settlement', 'payout', 'finance');
    foreach ($finance_needles as $needle) {
        if (strpos($key, $needle) !== false) {
            return 'finance';
        }
    }

    $marketing_needles = array('meta_ads', 'facebook', 'campaign', 'adset', 'promo', 'social', 'marketing', 'pixel', 'featured_image', 'thumbnail');
    foreach ($marketing_needles as $needle) {
        if (strpos($key, $needle) !== false) {
            return 'marketing';
        }
    }

    $agreement_needles = array('agreement', 'contract', 'packet', 'acknowledg', 'legal');
    foreach ($agreement_needles as $needle) {
        if (strpos($key, $needle) !== false) {
            return 'agreements';
        }
    }

    $ops_needles = array('guest', 'scanner', 'checkin', 'check_in', 'ops', 'door', 'comp');
    foreach ($ops_needles as $needle) {
        if (strpos($key, $needle) !== false) {
            return 'ops';
        }
    }

    return 'core';
}

function bvmgr_event_plan_save_profiler_mark_module(string $module, string $reason = ''): void
{
    $state = bvmgr_event_plan_save_profiler_state();
    if (empty($state['active'])) {
        return;
    }

    $module = bvmgr_event_plan_save_profiler_normalize_module($module);
    if (!isset($state['dirty_map']) || !is_array($state['dirty_map'])) {
        $state['dirty_map'] = bvmgr_event_plan_save_profiler_empty_dirty_map();
    }
    $state['dirty_map'][$module] = true;

    if ($reason !== '') {
        if (!isset($state['dirty_reasons']) || !is_array($state['dirty_reasons'])) {
            $state['dirty_reasons'] = array();
        }
        if (!isset($state['dirty_reasons'][$module]) || !is_array($state['dirty_reasons'][$module])) {
            $state['dirty_reasons'][$module] = array();
        }
        $state['dirty_reasons'][$module][] = sanitize_text_field($reason);
        $state['dirty_reasons'][$module] = array_slice(array_values(array_unique(array_filter($state['dirty_reasons'][$module]))), -8);
    }

    $GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;
}

function bvmgr_event_plan_save_profiler_note(string $key, $value): void
{
    $state = bvmgr_event_plan_save_profiler_state();
    if (empty($state['active'])) {
        return;
    }

    $key = sanitize_key($key);
    if ($key === '') {
        return;
    }

    if (!isset($state['notes']) || !is_array($state['notes'])) {
        $state['notes'] = array();
    }

    if (!isset($state['notes'][$key])) {
        $state['notes'][$key] = array();
    }

    $state['notes'][$key][] = is_scalar($value) ? sanitize_text_field((string) $value) : wp_json_encode($value);
    $state['notes'][$key] = array_slice(array_values(array_filter($state['notes'][$key])), -10);
    $GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;
}

function bvmgr_event_plan_save_profiler_note_heavy_action(string $action, string $status, string $reason = ''): void
{
    $state = bvmgr_event_plan_save_profiler_state();
    if (empty($state['active'])) {
        return;
    }

    $action = sanitize_key($action);
    $status = sanitize_key($status);
    if ($action === '' || $status === '') {
        return;
    }

    if (!isset($state['heavy_actions']) || !is_array($state['heavy_actions'])) {
        $state['heavy_actions'] = array();
    }

    $state['heavy_actions'][$action] = array(
        'status' => $status,
        'reason' => sanitize_text_field($reason),
        'recorded_at_gmt' => gmdate('Y-m-d H:i:s'),
    );

	$GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;
}

function bvmgr_event_plan_save_profiler_effective_meta_keys_from_state(array $state, int $post_id = 0): array
{
	$post_id = $post_id > 0 ? absint($post_id) : absint($state['post_id'] ?? 0);
	if ($post_id <= 0) {
		return array();
	}

	$meta_keys = is_array($state['meta_keys'] ?? null) ? $state['meta_keys'] : array();
	$meta_initial_hashes = is_array($state['meta_initial_hashes'] ?? null) ? $state['meta_initial_hashes'] : array();
	$effective = array();

	foreach (array_keys($meta_keys) as $meta_key) {
		$meta_key = is_string($meta_key) ? $meta_key : '';
		if ($meta_key === '' || bvmgr_event_plan_save_profiler_ignored_meta_key($meta_key)) {
			continue;
		}

		if (!array_key_exists($meta_key, $meta_initial_hashes)) {
			$effective[$meta_key] = true;
			continue;
		}

		$current_hash = bvmgr_event_plan_save_profiler_meta_values_hash($post_id, $meta_key);
		if ($current_hash !== (string) $meta_initial_hashes[$meta_key]) {
			$effective[$meta_key] = true;
		}
	}

	$forced_effective_meta_keys = is_array($state['forced_effective_meta_keys'] ?? null) ? $state['forced_effective_meta_keys'] : array();
	foreach (array_keys($forced_effective_meta_keys) as $meta_key) {
		$meta_key = sanitize_key((string) $meta_key);
		if ($meta_key === '' || bvmgr_event_plan_save_profiler_ignored_meta_key($meta_key)) {
			continue;
		}
		$effective[$meta_key] = true;
	}

	return array_values(array_keys($effective));
}

function bvmgr_event_plan_save_profiler_effective_meta_keys(int $post_id = 0): array
{
	$state = bvmgr_event_plan_save_profiler_state();
	if (empty($state['active'])) {
		return array();
	}
	if ($post_id > 0 && absint($state['post_id'] ?? 0) !== absint($post_id)) {
		return array();
	}

	return bvmgr_event_plan_save_profiler_effective_meta_keys_from_state($state, $post_id);
}

function bvmgr_event_plan_save_profiler_current_save_scope(int $post_id = 0): string
{
	$state = bvmgr_event_plan_save_profiler_state();
	if (empty($state['active'])) {
		return '';
	}
	if ($post_id > 0 && absint($state['post_id'] ?? 0) !== absint($post_id)) {
		return '';
	}

	$request_action = sanitize_key((string) ($state['request_action'] ?? ''));
	$post_field_changes = is_array($state['post_field_changes'] ?? null) ? $state['post_field_changes'] : array();
	$post_field_changes = array_values(array_filter(array_map('sanitize_key', $post_field_changes)));
	$effective_meta_keys = bvmgr_event_plan_save_profiler_effective_meta_keys_from_state($state, $post_id);
	$effective_meta_keys = bvmgr_event_plan_save_profiler_scope_effective_meta_keys($effective_meta_keys);

	if (empty($effective_meta_keys) && empty($post_field_changes)) {
		return 'no_op';
	}

	if ($request_action === '' && empty($post_field_changes) && $effective_meta_keys === array('_thumbnail_id')) {
		return 'featured_image_only';
	}

	return 'mixed';
}

function bvmgr_event_plan_save_profiler_is_featured_image_only(int $post_id = 0): bool
{
	return bvmgr_event_plan_save_profiler_current_save_scope($post_id) === 'featured_image_only';
}

function bvmgr_event_plan_save_profiler_featured_image_sync_completed(int $post_id = 0): bool
{
	$state = bvmgr_event_plan_save_profiler_state();
	if (empty($state['active'])) {
		return false;
	}
	if ($post_id > 0 && absint($state['post_id'] ?? 0) !== absint($post_id)) {
		return false;
	}

	$sync = is_array($state['featured_image_sync'] ?? null) ? $state['featured_image_sync'] : array();
	return absint($sync['completed_count'] ?? 0) > 0;
}

function bvmgr_event_plan_save_profiler_track_featured_image_sync(string $source, array $result): void
{
	$state = bvmgr_event_plan_save_profiler_state();
	$source = sanitize_key($source);
	if ($source === '') {
		$source = 'unspecified';
	}
	$plan_id = absint($result['plan_id'] ?? 0);

	$sync = array();
	if (empty($state['active'])) {
		if ($plan_id <= 0) {
			return;
		}
		$deferred = bvmgr_event_plan_save_profiler_deferred_state_for_post($plan_id);
		$sync = is_array($deferred['featured_image_sync'] ?? null) ? $deferred['featured_image_sync'] : array();
	} else {
		if (!isset($state['featured_image_sync']) || !is_array($state['featured_image_sync'])) {
			$state['featured_image_sync'] = array();
		}
		$sync = $state['featured_image_sync'];
	}

	$sync['attempt_count'] = absint($sync['attempt_count'] ?? 0) + 1;
	$sync['updated_count'] = absint($sync['updated_count'] ?? 0);
	$sync['completed_count'] = absint($sync['completed_count'] ?? 0);
	$sync['sources'] = is_array($sync['sources'] ?? null) ? $sync['sources'] : array();
	$sync['reasons'] = is_array($sync['reasons'] ?? null) ? $sync['reasons'] : array();
	$sync['sources'][] = $source;
	$sync['sources'] = array_slice(array_values(array_unique(array_filter(array_map('sanitize_key', $sync['sources'])))), -10);

	$reason = sanitize_key((string) ($result['reason'] ?? ''));
	if ($reason !== '') {
		$sync['reasons'][] = $reason;
		$sync['reasons'] = array_slice(array_values(array_unique(array_filter(array_map('sanitize_key', $sync['reasons'])))), -10);
	}

	$completed = !empty($result['ok']) && absint($result['tec_event_id'] ?? 0) > 0 && $reason !== 'request_guard_duplicate';
	if ($completed) {
		$sync['completed_count']++;
	}
	if (!empty($result['updated'])) {
		$sync['updated_count']++;
	}

	$sync['last_source'] = $source;
	$sync['last_reason'] = $reason;
	$sync['last_tec_event_id'] = absint($result['tec_event_id'] ?? 0);
	$sync['last_plan_thumbnail_id'] = absint($result['plan_thumbnail_id'] ?? 0);
	$sync['last_tec_thumbnail_id'] = absint($result['tec_thumbnail_id'] ?? 0);
	$sync['completed_once'] = ($sync['completed_count'] === 1);
	$sync['ran_once'] = ($sync['attempt_count'] === 1);

	if (empty($state['active'])) {
		$deferred['featured_image_sync'] = $sync;
		bvmgr_event_plan_save_profiler_save_deferred_state_for_post($plan_id, $deferred);
		return;
	}

	$state['featured_image_sync'] = $sync;
	$GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;
}

function bvmgr_event_plan_save_profiler_module_touched(string $module): bool
{
    $state = bvmgr_event_plan_save_profiler_state();
    $module = bvmgr_event_plan_save_profiler_normalize_module($module);
    $dirty_map = is_array($state['dirty_map'] ?? null) ? $state['dirty_map'] : array();

    return !empty($dirty_map[$module]);
}

function bvmgr_event_plan_save_profiler_meta_key_touched($keys): bool
{
    $keys = is_array($keys) ? $keys : array($keys);
    $state = bvmgr_event_plan_save_profiler_state();
    $meta_keys = is_array($state['meta_keys'] ?? null) ? $state['meta_keys'] : array();

    foreach ($keys as $key) {
        $key = is_scalar($key) ? (string) $key : '';
        if ($key !== '' && isset($meta_keys[$key])) {
            return true;
        }
    }

    return false;
}

function bvmgr_event_plan_save_profiler_post_field_changes(array $deferred_context, WP_Post $post): array
{
    $changes = array();
    $pre_changes = $deferred_context['pre_update_field_changes'] ?? array();
    if (is_array($pre_changes)) {
        foreach ($pre_changes as $change) {
            $change = sanitize_key((string) $change);
            if ($change !== '') {
                $changes[] = $change;
            }
        }
    }

    $previous_status = sanitize_key((string) ($deferred_context['previous_status'] ?? ($deferred_context['transition_old_status'] ?? '')));
    if ($previous_status !== '' && $previous_status !== sanitize_key((string) $post->post_status)) {
        $changes[] = 'status';
    }

    $previous_title_hash = sanitize_text_field((string) ($deferred_context['previous_title_hash'] ?? ''));
    if ($previous_title_hash !== '' && $previous_title_hash !== sha1((string) $post->post_title)) {
        $changes[] = 'title';
    }

    $previous_content_hash = sanitize_text_field((string) ($deferred_context['previous_content_hash'] ?? ''));
    if ($previous_content_hash !== '' && $previous_content_hash !== sha1((string) $post->post_content)) {
        $changes[] = 'content';
    }

    $previous_excerpt_hash = sanitize_text_field((string) ($deferred_context['previous_excerpt_hash'] ?? ''));
    if ($previous_excerpt_hash !== '' && $previous_excerpt_hash !== sha1((string) $post->post_excerpt)) {
        $changes[] = 'excerpt';
    }

    return array_values(array_unique(array_filter($changes)));
}

function bvmgr_event_plan_save_profiler_infer_save_type(array $state, WP_Post $post): string
{
    $request_action = sanitize_key((string) ($state['request_action'] ?? ''));
    $wp_action = sanitize_key((string) ($state['wp_action'] ?? ''));
    $status_at_start = sanitize_key((string) ($state['status_at_start'] ?? ''));
    $status_at_end = sanitize_key((string) $post->post_status);

    if ($status_at_start !== '' && $status_at_end !== '' && $status_at_start !== $status_at_end) {
        if ($status_at_end === 'publish' && $status_at_start !== 'publish') {
            return 'publish_transition';
        }
        if ($status_at_start === 'publish' && $status_at_end !== 'publish') {
            return 'unpublish_transition';
        }
        return 'status_transition';
    }

    if ($request_action !== '') {
        if (strpos($request_action, 'publish') !== false) {
            return 'publish_flow';
        }
        if (strpos($request_action, 'cancel') !== false || strpos($request_action, 'refund') !== false) {
            return 'cancellation_flow';
        }
        if (strpos($request_action, 'ticket') !== false) {
            return 'ticketing_action';
        }
        return 'event_plan_action';
    }

    if ($wp_action === 'editpost') {
        return $post->post_status === 'publish' ? 'core_wp_update' : 'core_wp_save';
    }

    return $wp_action !== '' ? $wp_action : 'event_plan_save';
}

function bvmgr_event_plan_save_profiler_start(int $post_id, WP_Post $post, bool $update): void
{
    if (!bvmgr_event_plan_save_profiler_enabled()) {
        return;
    }
    if ($post->post_type !== 'vms_event_plan' || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    $request = bvmgr_event_plan_save_profiler_post_data();
    $request_action = isset($request['vms_event_plan_action']) ? sanitize_key((string) $request['vms_event_plan_action']) : '';
    $wp_action = isset($request['action']) ? sanitize_key((string) $request['action']) : '';
    $ticketing_key = function_exists('bvmgr_ticketing_v2_k') ? (string) bvmgr_ticketing_v2_k('config') : '_vms_ticketing_v2_config';
    $sync_key = function_exists('bvmgr_ticketing_v2_k') ? (string) bvmgr_ticketing_v2_k('sync') : '_vms_ticketing_v2_sync';

	$deferred = bvmgr_event_plan_save_profiler_deferred_state_for_post($post_id);
	$deferred_context = is_array($deferred['context'] ?? null) ? $deferred['context'] : array();
	$deferred_notes = is_array($deferred['notes'] ?? null) ? $deferred['notes'] : array();
	$deferred_heavy_actions = is_array($deferred['heavy_actions'] ?? null) ? $deferred['heavy_actions'] : array();
	$deferred_featured_image_sync = is_array($deferred['featured_image_sync'] ?? null) ? $deferred['featured_image_sync'] : array();
	$deferred_forced_effective_meta_keys = is_array($deferred['forced_effective_meta_keys'] ?? null) ? $deferred['forced_effective_meta_keys'] : array();
	$previous_status = sanitize_key((string) ($deferred_context['previous_status'] ?? ($deferred_context['transition_old_status'] ?? get_post_status($post_id))));
    $post_field_changes = bvmgr_event_plan_save_profiler_post_field_changes($deferred_context, $post);
    bvmgr_event_plan_save_profiler_clear_deferred_state_for_post($post_id);

    $request_keys = bvmgr_event_plan_save_profiler_request_keys();
    $dirty_map = bvmgr_event_plan_save_profiler_empty_dirty_map();
    $dirty_reasons = array();

    if ($wp_action === 'editpost' || isset($request['post_title']) || isset($request['content']) || isset($request['excerpt'])) {
        $dirty_map['core'] = true;
        $dirty_reasons['core'] = array('normal_wp_editor_save');
    }
    if (in_array('status', $post_field_changes, true)) {
        $dirty_map['core'] = true;
        $dirty_reasons['core'][] = 'status_transition';
    }

    // Do not treat every submitted form key as dirty. The current Event Plan
    // editor is still a large form, so many ticket/staffing/vendor field names
    // may be present even during a content-only update. Module dirty state is
    // based on actual meta writes and explicit VMS actions instead.
    if ($request_action !== '') {
        $explicit_module = bvmgr_event_plan_save_profiler_classify_key($request_action);
        if ($explicit_module !== 'core') {
            $dirty_map[$explicit_module] = true;
            $dirty_reasons[$explicit_module] = array('request_action:' . $request_action);
        }
    }

    foreach ($dirty_reasons as $module => $reasons) {
        $dirty_reasons[$module] = array_slice(array_values(array_unique(array_filter($reasons))), -8);
    }

	$GLOBALS['bvmgr_event_plan_save_profiler_state'] = array(
		'active' => true,
		'post_id' => absint($post_id),
        'started_at' => microtime(true),
        'update' => (bool) $update,
        'status_at_start' => $previous_status,
        'request_action' => $request_action,
        'wp_action' => $wp_action,
        'request_keys' => $request_keys,
        'dirty_map' => $dirty_map,
        'dirty_reasons' => $dirty_reasons,
        'module_meta_writes' => array_fill_keys(array_keys(bvmgr_event_plan_save_profiler_modules()), 0),
        'heavy_actions' => $deferred_heavy_actions,
        'meta_writes' => 0,
        'meta_update_attempts' => 0,
        'noop_meta_update_attempts' => 0,
        'noop_meta_update_keys' => array(),
        'meta_keys' => array(),
        'forced_effective_meta_keys' => $deferred_forced_effective_meta_keys,
        'post_field_changes' => $post_field_changes,
        'deferred_context' => $deferred_context,
		'ticket_config_writes' => 0,
		'ticket_sync_writes' => 0,
		'queue_meta_writes' => 0,
		'queue_meta_keys' => array(),
		'wp_cron_scheduled_count' => 0,
		'wp_cron_hooks' => array(),
		'action_scheduler_enqueue_count' => 0,
		'action_scheduler_hooks' => array(),
		'featured_image_sync' => $deferred_featured_image_sync + array(
			'attempt_count' => 0,
			'completed_count' => 0,
			'updated_count' => 0,
			'sources' => array(),
			'reasons' => array(),
			'completed_once' => false,
			'ran_once' => false,
		),
		'watched_keys' => array(
			$ticketing_key => 'ticket_config_writes',
			$sync_key => 'ticket_sync_writes',
			'_vms_ticketing_enabled_override' => 'ticketing_override_writes',
		),
        'ticketing_override_writes' => 0,
        'notes' => $deferred_notes,
    );
}
add_action('save_post_vms_event_plan', 'bvmgr_event_plan_save_profiler_start', 1, 3);

function bvmgr_event_plan_save_profiler_ignored_meta_key(string $meta_key): bool
{
    return in_array($meta_key, array('_vms_last_save_profile', '_vms_event_plan_save_profile_history'), true);
}

function bvmgr_event_plan_save_profiler_scope_housekeeping_meta_keys(): array
{
	return array(
		'_vms_event_plan_actor_user_source',
		'_vms_admin_scroll_to',
		'_vms_unpublished_changes_at',
	);
}

function bvmgr_event_plan_save_profiler_scope_effective_meta_keys(array $meta_keys): array
{
	$housekeeping = array_fill_keys(bvmgr_event_plan_save_profiler_scope_housekeeping_meta_keys(), true);
	$scope_keys = array();

	foreach ($meta_keys as $meta_key) {
		$meta_key = sanitize_key((string) $meta_key);
		if ($meta_key === '' || isset($housekeeping[$meta_key])) {
			continue;
		}
		$scope_keys[$meta_key] = true;
	}

	$scope_keys = array_values(array_keys($scope_keys));
	sort($scope_keys, SORT_STRING);
	return $scope_keys;
}

function bvmgr_event_plan_save_profiler_meta_values_hash(int $post_id, string $meta_key): string
{
    if ($post_id <= 0 || $meta_key === '') {
        return '';
    }

    return sha1(maybe_serialize(get_post_meta($post_id, $meta_key, false)));
}

function bvmgr_event_plan_save_profiler_capture_meta_baseline(array &$state, int $object_id, string $meta_key): void
{
    if (empty($state['active']) || absint($state['post_id'] ?? 0) !== absint($object_id)) {
        return;
    }
    if (bvmgr_event_plan_save_profiler_ignored_meta_key($meta_key)) {
        return;
    }

    if (!isset($state['meta_initial_hashes']) || !is_array($state['meta_initial_hashes'])) {
        $state['meta_initial_hashes'] = array();
    }

    if (!array_key_exists($meta_key, $state['meta_initial_hashes'])) {
        $state['meta_initial_hashes'][$meta_key] = bvmgr_event_plan_save_profiler_meta_values_hash(absint($object_id), $meta_key);
    }
}

function bvmgr_event_plan_save_profiler_track_meta_add_attempt($check, int $object_id, string $meta_key, $meta_value, $unique)
{
    unset($meta_value, $unique);

    $state = bvmgr_event_plan_save_profiler_state();
    bvmgr_event_plan_save_profiler_capture_meta_baseline($state, absint($object_id), $meta_key);
    $GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;

    return $check;
}
add_filter('add_post_metadata', 'bvmgr_event_plan_save_profiler_track_meta_add_attempt', 1, 5);

function bvmgr_event_plan_save_profiler_track_meta_delete_attempt($check, int $object_id, string $meta_key, $meta_value, $delete_all)
{
    unset($meta_value, $delete_all);

    $state = bvmgr_event_plan_save_profiler_state();
    bvmgr_event_plan_save_profiler_capture_meta_baseline($state, absint($object_id), $meta_key);
    $GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;

    return $check;
}
add_filter('delete_post_metadata', 'bvmgr_event_plan_save_profiler_track_meta_delete_attempt', 1, 5);

function bvmgr_event_plan_save_profiler_track_meta_update_attempt($check, int $object_id, string $meta_key, $meta_value, $prev_value)
{
    unset($prev_value);

    $state = bvmgr_event_plan_save_profiler_state();
    if (empty($state['active']) || absint($state['post_id'] ?? 0) !== absint($object_id)) {
        return $check;
    }

    if (bvmgr_event_plan_save_profiler_ignored_meta_key($meta_key)) {
        return $check;
    }

    bvmgr_event_plan_save_profiler_capture_meta_baseline($state, absint($object_id), $meta_key);

    $state['meta_update_attempts'] = absint($state['meta_update_attempts'] ?? 0) + 1;

    $existing_values = get_post_meta(absint($object_id), $meta_key, false);
    if (is_array($existing_values) && count($existing_values) === 1 && maybe_serialize(reset($existing_values)) === maybe_serialize($meta_value)) {
        $state['noop_meta_update_attempts'] = absint($state['noop_meta_update_attempts'] ?? 0) + 1;
        if (!isset($state['noop_meta_update_keys']) || !is_array($state['noop_meta_update_keys'])) {
            $state['noop_meta_update_keys'] = array();
        }
        if (!isset($state['noop_meta_update_keys'][$meta_key])) {
            $state['noop_meta_update_keys'][$meta_key] = 0;
        }
        $state['noop_meta_update_keys'][$meta_key]++;
    }

    $GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;
    return $check;
}
add_filter('update_post_metadata', 'bvmgr_event_plan_save_profiler_track_meta_update_attempt', 1, 5);

function bvmgr_event_plan_save_profiler_track_meta_write($meta_id, int $object_id, string $meta_key, $meta_value): void
{
    unset($meta_id, $meta_value);

    $state = bvmgr_event_plan_save_profiler_state();
    if (empty($state['active']) || absint($state['post_id'] ?? 0) !== absint($object_id)) {
        return;
    }

    if (bvmgr_event_plan_save_profiler_ignored_meta_key($meta_key)) {
        return;
    }

    bvmgr_event_plan_save_profiler_capture_meta_baseline($state, absint($object_id), $meta_key);

    $state['meta_writes'] = absint($state['meta_writes'] ?? 0) + 1;

    if (!isset($state['meta_keys']) || !is_array($state['meta_keys'])) {
        $state['meta_keys'] = array();
    }
    if (!isset($state['meta_keys'][$meta_key])) {
        $state['meta_keys'][$meta_key] = 0;
    }
    $state['meta_keys'][$meta_key]++;

    $module = bvmgr_event_plan_save_profiler_classify_key($meta_key);
    if (!isset($state['module_meta_writes']) || !is_array($state['module_meta_writes'])) {
        $state['module_meta_writes'] = array_fill_keys(array_keys(bvmgr_event_plan_save_profiler_modules()), 0);
    }
    $state['module_meta_writes'][$module] = absint($state['module_meta_writes'][$module] ?? 0) + 1;

    if (!isset($state['dirty_map']) || !is_array($state['dirty_map'])) {
        $state['dirty_map'] = bvmgr_event_plan_save_profiler_empty_dirty_map();
    }
    $state['dirty_map'][$module] = true;

    if (!isset($state['dirty_reasons']) || !is_array($state['dirty_reasons'])) {
        $state['dirty_reasons'] = array();
    }
    if (!isset($state['dirty_reasons'][$module]) || !is_array($state['dirty_reasons'][$module])) {
        $state['dirty_reasons'][$module] = array();
    }
    $state['dirty_reasons'][$module][] = 'meta_key:' . $meta_key;
    $state['dirty_reasons'][$module] = array_slice(array_values(array_unique(array_filter($state['dirty_reasons'][$module]))), -8);

	$watched = is_array($state['watched_keys'] ?? null) ? $state['watched_keys'] : array();
	if (isset($watched[$meta_key])) {
        $counter = sanitize_key((string) $watched[$meta_key]);
        if ($counter !== '') {
            $state[$counter] = absint($state[$counter] ?? 0) + 1;
		}
	}

	if (function_exists('bvmgr_event_plan_perf_is_queue_meta_key') && bvmgr_event_plan_perf_is_queue_meta_key($meta_key)) {
		$state['queue_meta_writes'] = absint($state['queue_meta_writes'] ?? 0) + 1;
		if (!isset($state['queue_meta_keys']) || !is_array($state['queue_meta_keys'])) {
			$state['queue_meta_keys'] = array();
		}
		if (!isset($state['queue_meta_keys'][$meta_key])) {
			$state['queue_meta_keys'][$meta_key] = 0;
		}
		$state['queue_meta_keys'][$meta_key]++;
	}

	$GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;
}
add_action('added_post_meta', 'bvmgr_event_plan_save_profiler_track_meta_write', 1, 4);
add_action('updated_post_meta', 'bvmgr_event_plan_save_profiler_track_meta_write', 1, 4);
add_action('deleted_post_meta', 'bvmgr_event_plan_save_profiler_track_meta_write', 1, 4);

function bvmgr_event_plan_save_profiler_track_scheduled_event($event)
{
	if (!is_object($event) || !bvmgr_event_plan_save_profiler_active()) {
		return $event;
	}

	$state = bvmgr_event_plan_save_profiler_state();
	$post_id = absint($state['post_id'] ?? 0);
	if ($post_id <= 0) {
		return $event;
	}

	$hook = sanitize_key((string) ($event->hook ?? ''));
	if ($hook === '') {
		return $event;
	}

	$state['wp_cron_scheduled_count'] = absint($state['wp_cron_scheduled_count'] ?? 0) + 1;
	if (!isset($state['wp_cron_hooks']) || !is_array($state['wp_cron_hooks'])) {
		$state['wp_cron_hooks'] = array();
	}
	$state['wp_cron_hooks'][] = $hook;
	$state['wp_cron_hooks'] = array_slice(array_values(array_unique(array_filter(array_map('sanitize_key', $state['wp_cron_hooks'])))), -20);
	$GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;

	return $event;
}
add_filter('schedule_event', 'bvmgr_event_plan_save_profiler_track_scheduled_event', 1, 1);

function bvmgr_event_plan_save_profiler_track_action_scheduler_enqueue($pre, string $hook, array $args, string $group, int $priority, bool $unique)
{
	unset($args, $group, $priority, $unique);

	if (!bvmgr_event_plan_save_profiler_active()) {
		return $pre;
	}

	$state = bvmgr_event_plan_save_profiler_state();
	$hook = sanitize_key($hook);
	if ($hook === '') {
		return $pre;
	}

	$state['action_scheduler_enqueue_count'] = absint($state['action_scheduler_enqueue_count'] ?? 0) + 1;
	if (!isset($state['action_scheduler_hooks']) || !is_array($state['action_scheduler_hooks'])) {
		$state['action_scheduler_hooks'] = array();
	}
	$state['action_scheduler_hooks'][] = $hook;
	$state['action_scheduler_hooks'] = array_slice(array_values(array_unique(array_filter(array_map('sanitize_key', $state['action_scheduler_hooks'])))), -20);
	$GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;

	return $pre;
}
add_filter('pre_as_enqueue_async_action', 'bvmgr_event_plan_save_profiler_track_action_scheduler_enqueue', 1, 6);

function bvmgr_event_plan_save_profiler_track_action_scheduler_single($pre, int $timestamp, string $hook, array $args, string $group, int $priority)
{
	unset($timestamp, $args, $group, $priority);

	if (!bvmgr_event_plan_save_profiler_active()) {
		return $pre;
	}

	$state = bvmgr_event_plan_save_profiler_state();
	$hook = sanitize_key($hook);
	if ($hook === '') {
		return $pre;
	}

	$state['action_scheduler_enqueue_count'] = absint($state['action_scheduler_enqueue_count'] ?? 0) + 1;
	if (!isset($state['action_scheduler_hooks']) || !is_array($state['action_scheduler_hooks'])) {
		$state['action_scheduler_hooks'] = array();
	}
	$state['action_scheduler_hooks'][] = $hook;
	$state['action_scheduler_hooks'] = array_slice(array_values(array_unique(array_filter(array_map('sanitize_key', $state['action_scheduler_hooks'])))), -20);
	$GLOBALS['bvmgr_event_plan_save_profiler_state'] = $state;

	return $pre;
}
add_filter('pre_as_schedule_single_action', 'bvmgr_event_plan_save_profiler_track_action_scheduler_single', 1, 6);

function bvmgr_event_plan_save_profiler_store_profile(int $post_id, array $profile): void
{
    if ($post_id <= 0 || empty($profile) || !bvmgr_event_plan_save_profiler_recording_enabled()) {
        return;
    }

    update_post_meta($post_id, '_vms_last_save_profile', $profile);

    $history = get_post_meta($post_id, '_vms_event_plan_save_profile_history', true);
    $history = is_array($history) ? $history : array();
    array_unshift($history, $profile);
    $history = array_slice($history, 0, 5);
    update_post_meta($post_id, '_vms_event_plan_save_profile_history', $history);
}

function bvmgr_event_plan_save_profiler_finish(int $post_id, WP_Post $post, bool $update): void
{
    unset($update);

    $state = bvmgr_event_plan_save_profiler_state();
    if (empty($state['active']) || absint($state['post_id'] ?? 0) !== absint($post_id)) {
        return;
    }

    $started_at = (float) ($state['started_at'] ?? microtime(true));
    $elapsed = max(0.0, microtime(true) - $started_at);
    $threshold = bvmgr_event_plan_save_profiler_threshold_seconds();
    $always = (defined('VMS_EVENT_PLAN_SAVE_PROFILER_ALWAYS') && VMS_EVENT_PLAN_SAVE_PROFILER_ALWAYS);
	$recording_enabled = bvmgr_event_plan_save_profiler_recording_enabled();

    $meta_keys = is_array($state['meta_keys'] ?? null) ? $state['meta_keys'] : array();
    arsort($meta_keys);
    $top_meta_keys = array_slice($meta_keys, 0, 20, true);
	$post_field_changes = is_array($state['post_field_changes'] ?? null) ? $state['post_field_changes'] : array();
	$post_field_changes = array_values(array_filter(array_map('sanitize_key', $post_field_changes)));

    $meta_initial_hashes = is_array($state['meta_initial_hashes'] ?? null) ? $state['meta_initial_hashes'] : array();
    $effective_meta_keys = array();
    $no_effect_meta_write_keys = array();
    foreach (array_keys($meta_keys) as $meta_key) {
        $meta_key = is_string($meta_key) ? $meta_key : '';
        if ($meta_key === '' || bvmgr_event_plan_save_profiler_ignored_meta_key($meta_key)) {
            continue;
        }

        if (!array_key_exists($meta_key, $meta_initial_hashes)) {
            $effective_meta_keys[$meta_key] = true;
            continue;
        }

        $current_hash = bvmgr_event_plan_save_profiler_meta_values_hash(absint($post_id), $meta_key);
        if ($current_hash !== (string) $meta_initial_hashes[$meta_key]) {
            $effective_meta_keys[$meta_key] = true;
        } else {
            $no_effect_meta_write_keys[$meta_key] = absint($meta_keys[$meta_key] ?? 0);
        }
    }
	$forced_effective_meta_keys = is_array($state['forced_effective_meta_keys'] ?? null) ? $state['forced_effective_meta_keys'] : array();
	foreach (array_keys($forced_effective_meta_keys) as $meta_key) {
		$meta_key = sanitize_key((string) $meta_key);
		if ($meta_key === '' || bvmgr_event_plan_save_profiler_ignored_meta_key($meta_key)) {
			continue;
		}
		$effective_meta_keys[$meta_key] = true;
	}
	$scope_effective_meta_keys = bvmgr_event_plan_save_profiler_scope_effective_meta_keys(array_keys($effective_meta_keys));

	$dirty_map = is_array($state['dirty_map'] ?? null) ? $state['dirty_map'] : bvmgr_event_plan_save_profiler_empty_dirty_map();
	$dirty_map = array_merge(bvmgr_event_plan_save_profiler_empty_dirty_map(), array_intersect_key($dirty_map, bvmgr_event_plan_save_profiler_empty_dirty_map()));
	$dirty_reasons = is_array($state['dirty_reasons'] ?? null) ? $state['dirty_reasons'] : array();
	$save_scope = bvmgr_event_plan_save_profiler_current_save_scope((int) $post_id);
	if ($save_scope === '') {
		$save_scope = 'mixed';
	}

    // A giant Event Plan editor save can delete/re-add index meta or attempt to
    // persist identical module values. Do not classify a module as changed unless
    // one of its touched meta keys has a different final value than it had at the
    // start of this save, or the module was marked dirty by an explicit non-meta
    // reason/action. This keeps "content-only" and "title-only" saves from being
    // polluted by vendor/finance/marketing/ops no-op churn.
    foreach (array_keys(bvmgr_event_plan_save_profiler_modules()) as $module) {
        if ($module === 'core') {
            continue;
        }

        $reasons = is_array($dirty_reasons[$module] ?? null) ? $dirty_reasons[$module] : array();
        $kept_reasons = array();
        foreach ($reasons as $reason) {
            $reason = is_scalar($reason) ? (string) $reason : '';
            if ($reason === '') {
                continue;
            }
            if (strpos($reason, 'meta_key:') === 0) {
                $reason_key = substr($reason, strlen('meta_key:'));
                if ($reason_key !== '' && isset($effective_meta_keys[$reason_key])) {
                    $kept_reasons[] = $reason;
                }
                continue;
            }
            $kept_reasons[] = $reason;
        }

        if (empty($kept_reasons)) {
            $dirty_map[$module] = false;
            unset($dirty_reasons[$module]);
        } else {
            $dirty_map[$module] = true;
            $dirty_reasons[$module] = array_slice(array_values(array_unique($kept_reasons)), -8);
        }
    }

    $changed_modules = array_keys(array_filter($dirty_map));
    if (empty($changed_modules)) {
        $changed_modules = array('core');
        $dirty_map['core'] = true;
    }

    $profile = array(
        'version' => defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '',
        'recorded_at_gmt' => gmdate('Y-m-d H:i:s'),
        'post_id' => absint($post_id),
        'title' => sanitize_text_field((string) get_the_title($post_id)),
        'save_type' => bvmgr_event_plan_save_profiler_infer_save_type($state, $post),
        'elapsed_ms' => (int) round($elapsed * 1000),
        'threshold_ms' => (int) round($threshold * 1000),
        'status_at_start' => sanitize_key((string) ($state['status_at_start'] ?? '')),
        'status_at_end' => sanitize_key((string) $post->post_status),
        'request_action' => sanitize_key((string) ($state['request_action'] ?? '')),
        'wp_action' => sanitize_key((string) ($state['wp_action'] ?? '')),
        'changed_modules' => $changed_modules,
        'module_dirty_map' => $dirty_map,
        'dirty_reasons' => $dirty_reasons,
        'module_meta_writes' => is_array($state['module_meta_writes'] ?? null) ? $state['module_meta_writes'] : array(),
        'effective_meta_keys' => array_slice(array_keys($effective_meta_keys), 0, 40),
        'scope_effective_meta_keys' => array_slice($scope_effective_meta_keys, 0, 20),
        'no_effect_meta_write_keys' => array_slice($no_effect_meta_write_keys, 0, 40, true),
		'heavy_actions' => is_array($state['heavy_actions'] ?? null) ? $state['heavy_actions'] : array(),
		'meta_writes' => absint($state['meta_writes'] ?? 0),
		'internal_wp_update_post_count' => function_exists('bvmgr_event_plan_perf_get_plan_counter')
			? absint(bvmgr_event_plan_perf_get_plan_counter('internal_wp_update_post_count', (int) $post_id))
			: 0,
		'ticket_config_writes' => absint($state['ticket_config_writes'] ?? 0),
		'ticket_sync_writes' => absint($state['ticket_sync_writes'] ?? 0),
		'ticketing_override_writes' => absint($state['ticketing_override_writes'] ?? 0),
		'queue_meta_writes' => absint($state['queue_meta_writes'] ?? 0),
		'queue_meta_keys' => is_array($state['queue_meta_keys'] ?? null) ? array_slice($state['queue_meta_keys'], 0, 20, true) : array(),
		'wp_cron_scheduled_count' => absint($state['wp_cron_scheduled_count'] ?? 0),
		'wp_cron_hooks' => is_array($state['wp_cron_hooks'] ?? null) ? array_values(array_slice($state['wp_cron_hooks'], 0, 20)) : array(),
		'action_scheduler_enqueue_count' => absint($state['action_scheduler_enqueue_count'] ?? 0),
		'action_scheduler_hooks' => is_array($state['action_scheduler_hooks'] ?? null) ? array_values(array_slice($state['action_scheduler_hooks'], 0, 20)) : array(),
		'featured_image_sync' => is_array($state['featured_image_sync'] ?? null) ? $state['featured_image_sync'] : array(),
		'meta_update_attempts' => absint($state['meta_update_attempts'] ?? 0),
		'noop_meta_update_attempts' => absint($state['noop_meta_update_attempts'] ?? 0),
        'noop_meta_update_keys' => is_array($state['noop_meta_update_keys'] ?? null) ? array_slice($state['noop_meta_update_keys'], 0, 20, true) : array(),
        'post_field_changes' => is_array($state['post_field_changes'] ?? null) ? array_values(array_filter(array_map('sanitize_key', $state['post_field_changes']))) : array(),
        'deferred_context' => is_array($state['deferred_context'] ?? null) ? $state['deferred_context'] : array(),
		'top_meta_keys' => $top_meta_keys,
		'notes' => is_array($state['notes'] ?? null) ? $state['notes'] : array(),
		'save_scope' => $save_scope,
	);

    $GLOBALS['bvmgr_event_plan_save_profiler_state'] = array();

    if (!$recording_enabled) {
        return;
    }

    if (!$always && $elapsed < $threshold) {
        return;
    }

    bvmgr_event_plan_save_profiler_store_profile($post_id, $profile);
}
add_action('save_post_vms_event_plan', 'bvmgr_event_plan_save_profiler_finish', PHP_INT_MAX, 3);

function bvmgr_event_plan_save_profiler_record_module_meta_profile($meta_id, int $object_id, string $meta_key, $meta_value): void
{
    unset($meta_id, $meta_value);

    if (!bvmgr_event_plan_save_profiler_recording_enabled() || bvmgr_event_plan_save_profiler_ignored_meta_key($meta_key)) {
        return;
    }

    $state = bvmgr_event_plan_save_profiler_state();
    if (!empty($state['active'])) {
        return;
    }

    $object_id = absint($object_id);
    if ($object_id <= 0 || get_post_type($object_id) !== 'vms_event_plan') {
        return;
    }

    $module = bvmgr_event_plan_save_profiler_classify_key($meta_key);
    if ($module === 'core') {
        return;
    }

    // Ticketing/staffing/vendor module AJAX saves do not always pass through
    // save_post_vms_event_plan. Record a tiny profile when module-owned meta is
    // written outside a normal Event Plan save so the hub does not misleadingly
    // keep showing a prior "Core only" save.
    $dirty_map = bvmgr_event_plan_save_profiler_empty_dirty_map();
    $dirty_map[$module] = true;

    $profile = array(
        'version' => defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '',
        'recorded_at_gmt' => gmdate('Y-m-d H:i:s'),
        'post_id' => $object_id,
        'title' => sanitize_text_field((string) get_the_title($object_id)),
        'save_type' => 'module_meta_update',
        'elapsed_ms' => 0,
        'threshold_ms' => 0,
        'status_at_start' => sanitize_key((string) get_post_status($object_id)),
        'status_at_end' => sanitize_key((string) get_post_status($object_id)),
        'request_action' => isset(bvmgr_event_plan_save_profiler_post_data()['action']) ? sanitize_key((string) bvmgr_event_plan_save_profiler_post_data()['action']) : '',
        'wp_action' => isset(bvmgr_event_plan_save_profiler_post_data()['action']) ? sanitize_key((string) bvmgr_event_plan_save_profiler_post_data()['action']) : '',
        'changed_modules' => array($module),
        'module_dirty_map' => $dirty_map,
        'dirty_reasons' => array($module => array('meta_key:' . $meta_key)),
        'module_meta_writes' => array_merge(array_fill_keys(array_keys(bvmgr_event_plan_save_profiler_modules()), 0), array($module => 1)),
        'heavy_actions' => array(),
        'meta_writes' => 1,
        'ticket_config_writes' => $module === 'tickets' && strpos($meta_key, 'config') !== false ? 1 : 0,
        'ticket_sync_writes' => $module === 'tickets' && strpos($meta_key, 'sync') !== false ? 1 : 0,
        'ticketing_override_writes' => $meta_key === '_vms_ticketing_enabled_override' ? 1 : 0,
        'meta_update_attempts' => 0,
        'noop_meta_update_attempts' => 0,
        'noop_meta_update_keys' => array(),
        'post_field_changes' => array(),
        'deferred_context' => array(),
        'top_meta_keys' => array($meta_key => 1),
        'notes' => array('module_meta_update' => array('recorded_outside_save_post')),
    );

    bvmgr_event_plan_save_profiler_store_profile($object_id, $profile);
}
add_action('added_post_meta', 'bvmgr_event_plan_save_profiler_record_module_meta_profile', 100, 4);
add_action('updated_post_meta', 'bvmgr_event_plan_save_profiler_record_module_meta_profile', 100, 4);

function bvmgr_event_plan_save_profiler_changed_modules_label(array $profile): string
{
    $modules = bvmgr_event_plan_save_profiler_modules();
    $changed = is_array($profile['changed_modules'] ?? null) ? $profile['changed_modules'] : array();
    $labels = array();

    foreach ($changed as $module) {
        $module = bvmgr_event_plan_save_profiler_normalize_module((string) $module);
        $labels[] = $modules[$module] ?? ucwords(str_replace('_', ' ', $module));
    }

    $labels = array_values(array_unique(array_filter($labels)));
    if (empty($labels)) {
        return __('None detected', 'backstage-venue-manager');
    }
    if (count($labels) === 1 && $labels[0] === ($modules['core'] ?? 'Core Event Details')) {
        return __('Core only', 'backstage-venue-manager');
    }

    return implode(', ', $labels);
}

function bvmgr_event_plan_save_profiler_heavy_action_summary(array $profile): array
{
    $heavy = is_array($profile['heavy_actions'] ?? null) ? $profile['heavy_actions'] : array();
    $summary = array(
        'skipped' => array(),
        'triggered' => array(),
        'other' => array(),
    );

    foreach ($heavy as $action => $data) {
        $data = is_array($data) ? $data : array();
        $status = sanitize_key((string) ($data['status'] ?? ''));
        $label = ucwords(str_replace('_', ' ', sanitize_key((string) $action)));
        $reason = sanitize_text_field((string) ($data['reason'] ?? ''));
        if ($reason !== '') {
            $label .= ' (' . $reason . ')';
        }

        if ($status === 'skipped') {
            $summary['skipped'][] = $label;
        } elseif (in_array($status, array('triggered', 'scheduled', 'queued'), true)) {
            $summary['triggered'][] = $label;
        } else {
            $summary['other'][] = $label;
        }
    }

    return $summary;
}

/**
 * Surface the latest profiler record in wp-admin so staging testers do not need
 * database access to confirm what happened during Event Plan saves.
 */
function bvmgr_event_plan_save_profiler_add_meta_box(): void
{
    if (!current_user_can('edit_posts')) {
        return;
    }

    add_meta_box(
        'vms_event_plan_save_profile',
        __('Backstage Venue Manager Save Profile', 'backstage-venue-manager'),
        'bvmgr_event_plan_save_profiler_render_meta_box',
        'vms_event_plan',
        'side',
        'low'
    );
}
add_action('add_meta_boxes_vms_event_plan', 'bvmgr_event_plan_save_profiler_add_meta_box');

function bvmgr_event_plan_save_profiler_render_meta_box(WP_Post $post): void
{
    $profile = get_post_meta((int) $post->ID, '_vms_last_save_profile', true);
    if (!is_array($profile) || empty($profile)) {
        echo '<p>' . esc_html__('No Event Plan save profile has been recorded yet.', 'backstage-venue-manager') . '</p>';
        echo '<p class="description">' . esc_html__('Save this Event Plan once to record the module dirty map and heavy-work skip/trigger summary.', 'backstage-venue-manager') . '</p>';
        return;
    }

    $elapsed = absint($profile['elapsed_ms'] ?? 0);
    $recorded = sanitize_text_field((string) ($profile['recorded_at_gmt'] ?? ''));
    $status_start = sanitize_key((string) ($profile['status_at_start'] ?? ''));
    $status_end = sanitize_key((string) ($profile['status_at_end'] ?? ''));
    $request_action = sanitize_key((string) ($profile['request_action'] ?? ''));
    $wp_action = sanitize_key((string) ($profile['wp_action'] ?? ''));
    $save_type = sanitize_key((string) ($profile['save_type'] ?? ''));
    $heavy_summary = bvmgr_event_plan_save_profiler_heavy_action_summary($profile);

    echo '<p><strong>' . esc_html__('Last Event Plan save', 'backstage-venue-manager') . '</strong></p>';
    echo '<ul style="margin-left:1em;list-style:disc;">';
    /* translators: %s: type. */
    echo '<li>' . esc_html(sprintf(__('Type: %s', 'backstage-venue-manager'), $save_type ?: '-')) . '</li>';
    /* translators: %s: changed. */
    echo '<li>' . esc_html(sprintf(__('Changed: %s', 'backstage-venue-manager'), bvmgr_event_plan_save_profiler_changed_modules_label($profile))) . '</li>';
    /* translators: %d: elapsed. */
    echo '<li>' . esc_html(sprintf(__('Elapsed: %d ms', 'backstage-venue-manager'), $elapsed)) . '</li>';
    if ($recorded !== '') {
        /* translators: %s: recorded gmt. */
        echo '<li>' . esc_html(sprintf(__('Recorded GMT: %s', 'backstage-venue-manager'), $recorded)) . '</li>';
    }
    /* translators: 1: previous status label, 2: new status label. */
    echo '<li>' . esc_html(sprintf(__('Status: %1$s → %2$s', 'backstage-venue-manager'), $status_start ?: '-', $status_end ?: '-')) . '</li>';
    $post_field_changes = is_array($profile['post_field_changes'] ?? null) ? array_values(array_filter(array_map('sanitize_key', $profile['post_field_changes']))) : array();
    if (!empty($post_field_changes)) {
        /* translators: %s: post fields changed. */
        echo '<li>' . esc_html(sprintf(__('Post fields changed: %s', 'backstage-venue-manager'), implode(', ', $post_field_changes))) . '</li>';
    }
    /* translators: %s: request action. */
    echo '<li>' . esc_html(sprintf(__('Request action: %s', 'backstage-venue-manager'), $request_action ?: '-')) . '</li>';
    /* translators: %s: wp action. */
    echo '<li>' . esc_html(sprintf(__('WP action: %s', 'backstage-venue-manager'), $wp_action ?: '-')) . '</li>';
    /* translators: %d: meta writes. */
    echo '<li>' . esc_html(sprintf(__('Meta writes: %d', 'backstage-venue-manager'), absint($profile['meta_writes'] ?? 0))) . '</li>';
    /* translators: %d: meta update attempts. */
    echo '<li>' . esc_html(sprintf(__('Meta update attempts: %d', 'backstage-venue-manager'), absint($profile['meta_update_attempts'] ?? 0))) . '</li>';
    /* translators: %d: no-op meta update attempts. */
    echo '<li>' . esc_html(sprintf(__('No-op meta update attempts: %d', 'backstage-venue-manager'), absint($profile['noop_meta_update_attempts'] ?? 0))) . '</li>';
    /* translators: %d: ticket config writes. */
    echo '<li>' . esc_html(sprintf(__('Ticket config writes: %d', 'backstage-venue-manager'), absint($profile['ticket_config_writes'] ?? 0))) . '</li>';
    /* translators: %d: ticket sync writes. */
    echo '<li>' . esc_html(sprintf(__('Ticket sync writes: %d', 'backstage-venue-manager'), absint($profile['ticket_sync_writes'] ?? 0))) . '</li>';
    echo '</ul>';

    if (!empty($heavy_summary['triggered']) || !empty($heavy_summary['skipped'])) {
        echo '<details open><summary>' . esc_html__('Heavy work summary', 'backstage-venue-manager') . '</summary><ul style="margin-left:1em;list-style:disc;">';
        if (!empty($heavy_summary['triggered'])) {
            /* translators: %s: triggered. */
            echo '<li>' . esc_html(sprintf(__('Triggered: %s', 'backstage-venue-manager'), implode(', ', $heavy_summary['triggered']))) . '</li>';
        }
        if (!empty($heavy_summary['skipped'])) {
            /* translators: %s: skipped. */
            echo '<li>' . esc_html(sprintf(__('Skipped: %s', 'backstage-venue-manager'), implode(', ', $heavy_summary['skipped']))) . '</li>';
        }
        echo '</ul></details>';
    }

    $notes = is_array($profile['notes'] ?? null) ? $profile['notes'] : array();
    if (!empty($notes)) {
        echo '<details><summary>' . esc_html__('Queue / hook notes', 'backstage-venue-manager') . '</summary><pre style="white-space:pre-wrap;max-height:180px;overflow:auto;">';
        echo esc_html(wp_json_encode($notes, JSON_PRETTY_PRINT));
        echo '</pre></details>';
    }

    $top_meta = is_array($profile['top_meta_keys'] ?? null) ? $profile['top_meta_keys'] : array();
    if (!empty($top_meta)) {
        echo '<details><summary>' . esc_html__('Top meta keys touched', 'backstage-venue-manager') . '</summary><pre style="white-space:pre-wrap;max-height:180px;overflow:auto;">';
        echo esc_html(wp_json_encode($top_meta, JSON_PRETTY_PRINT));
        echo '</pre></details>';
    }

    $noop_meta = is_array($profile['noop_meta_update_keys'] ?? null) ? $profile['noop_meta_update_keys'] : array();
    if (!empty($noop_meta)) {
        echo '<details><summary>' . esc_html__('No-op meta update attempts', 'backstage-venue-manager') . '</summary><pre style="white-space:pre-wrap;max-height:180px;overflow:auto;">';
        echo esc_html(wp_json_encode($noop_meta, JSON_PRETTY_PRINT));
        echo '</pre></details>';
    }
}

function bvmgr_event_plan_save_profiler_render_hub_summary(int $post_id): void
{
    $profile = get_post_meta(absint($post_id), '_vms_last_save_profile', true);
    if (!is_array($profile) || empty($profile)) {
        echo '<div class="vms-ep-save-profile vms-ep-save-profile--empty">';
        echo '<h4>' . esc_html__('Last Event Plan Save', 'backstage-venue-manager') . '</h4>';
        echo '<p>' . esc_html__('No save profile has been recorded yet. Save the Event Plan once to show what changed and what heavy work was skipped or triggered.', 'backstage-venue-manager') . '</p>';
        echo '</div>';
        return;
    }

    $save_type = sanitize_key((string) ($profile['save_type'] ?? 'event_plan_save'));
    $elapsed = absint($profile['elapsed_ms'] ?? 0);
    $recorded = sanitize_text_field((string) ($profile['recorded_at_gmt'] ?? ''));
    $changed = bvmgr_event_plan_save_profiler_changed_modules_label($profile);
    $heavy_summary = bvmgr_event_plan_save_profiler_heavy_action_summary($profile);

    echo '<div class="vms-ep-save-profile">';
    echo '<div class="vms-ep-save-profile__header">';
    echo '<div><h4>' . esc_html__('Last Event Plan Save', 'backstage-venue-manager') . '</h4>';
    echo '<p>' . esc_html__('Module-aware diagnostics for the most recent Event Plan/module save.', 'backstage-venue-manager') . '</p></div>';
    echo '<span class="vms-cc-chip vms-cc-chip--info">' . esc_html($save_type) . '</span>';
    echo '</div>';
    echo '<div class="vms-ep-save-profile__facts">';
    echo '<span><strong>' . esc_html__('Changed:', 'backstage-venue-manager') . '</strong> ' . esc_html($changed) . '</span>';
    /* translators: %d: number of items described in this message. */
    echo '<span><strong>' . esc_html__('Duration:', 'backstage-venue-manager') . '</strong> ' . esc_html(sprintf(__('%d ms', 'backstage-venue-manager'), $elapsed)) . '</span>';
    if ($recorded !== '') {
        echo '<span><strong>' . esc_html__('Recorded GMT:', 'backstage-venue-manager') . '</strong> ' . esc_html($recorded) . '</span>';
    }
    $status_start = sanitize_key((string) ($profile['status_at_start'] ?? ''));
    $status_end = sanitize_key((string) ($profile['status_at_end'] ?? ''));
    if ($status_start !== '' && $status_end !== '' && $status_start !== $status_end) {
        echo '<span><strong>' . esc_html__('Status:', 'backstage-venue-manager') . '</strong> ' . esc_html($status_start . ' → ' . $status_end) . '</span>';
    }
    $post_field_changes = is_array($profile['post_field_changes'] ?? null) ? array_values(array_filter(array_map('sanitize_key', $profile['post_field_changes']))) : array();
    if (!empty($post_field_changes)) {
        echo '<span><strong>' . esc_html__('Post fields:', 'backstage-venue-manager') . '</strong> ' . esc_html(implode(', ', $post_field_changes)) . '</span>';
    }
    $noop_attempts = absint($profile['noop_meta_update_attempts'] ?? 0);
    if ($noop_attempts > 0) {
        echo '<span><strong>' . esc_html__('No-op meta attempts:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) $noop_attempts) . '</span>';
    }
    echo '</div>';

    if (!empty($heavy_summary['triggered']) || !empty($heavy_summary['skipped'])) {
        echo '<div class="vms-ep-save-profile__heavy">';
        if (!empty($heavy_summary['triggered'])) {
            echo '<p class="vms-ep-save-profile__triggered"><strong>' . esc_html__('Triggered:', 'backstage-venue-manager') . '</strong> ' . esc_html(implode(', ', $heavy_summary['triggered'])) . '</p>';
        }
        if (!empty($heavy_summary['skipped'])) {
            echo '<p class="vms-ep-save-profile__skipped"><strong>' . esc_html__('Skipped:', 'backstage-venue-manager') . '</strong> ' . esc_html(implode(', ', $heavy_summary['skipped'])) . '</p>';
        }
        echo '</div>';
    }

    echo '</div>';
}
