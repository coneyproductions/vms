<?php
defined('ABSPATH') || exit;

/** Availability describes the vendor's calendar. Venue booking rules use their own date policy. */
function bvmgr_vendor_portal_availability_dates(): array
{
    $day = new DateTimeImmutable('today', wp_timezone());
    $end = $day->modify('first day of this month')->modify('+24 months');
    $dates = array();
    for (; $day < $end; $day = $day->modify('+1 day')) {
        $dates[] = $day->format('Y-m-d');
    }
    return $dates;
}

/** Read current assignments across venues, without relying on cached calendar output. */
function bvmgr_vendor_portal_availability_assignments(int $vendor_id, array $dates): array
{
    if (!$dates) {
        return array();
    }
    $date_key = bvmgr_meta_key('event_plan', 'date') ?: '_vms_event_date';
    $plans = get_posts(array(
        'post_type' => 'vms_event_plan',
        'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
        'numberposts' => -1,
        'fields' => 'ids',
        'cache_results' => false,
        'meta_query' => array(array('key' => $date_key, 'value' => $dates, 'compare' => 'IN')),
    ));
    if (!$plans) {
        return array();
    }
    update_meta_cache('post', $plans);
    $assignments = array();
    foreach ($plans as $plan_id) {
        $state = bvmgr_calendar_assignment_status_for_plan(bvmgr_event_plan_get_status($plan_id, 'vendor'));
        if ($state === null) {
            continue;
        }
        $vendors = bvmgr_calendar_plan_vendor_ids($plan_id);
        if ($vendors['band_id'] !== $vendor_id && !in_array($vendor_id, $vendors['lineup_ids'], true)
            && !in_array($vendor_id, $vendors['secondary_ids'], true)) {
            continue;
        }
        $date = (string) get_post_meta($plan_id, $date_key, true);
        if ($state === 'booked' || !isset($assignments[$date])) {
            $assignments[$date] = $state;
        }
    }
    return $assignments;
}

/** Serialize the existing availability meta writes for a vendor. */
function bvmgr_vendor_availability_lock(int $vendor_id, callable $write, string $layer = 'manual')
{
    global $wpdb;
    $key = 'bvmgr_vendor_avail_' . md5(DB_NAME . ':' . $wpdb->prefix . ':' . $vendor_id . ':' . $layer);
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)', $key)) !== 1) {
        return new WP_Error('availability_busy', __('Another availability save is in progress. Try again.', 'backstage-venue-manager'));
    }
    try {
        wp_cache_delete($vendor_id, 'post_meta');
        return $write();
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
    }
}

/**
 * Apply per-date changes to the stored map. Portal requests supply their prior
 * value for every edit. ADD retains its existing update and restore semantics.
 */
function bvmgr_vendor_availability_apply(int $vendor_id, array $changes, string $source, ?array $expected = null)
{
    if (!$changes) {
        return array();
    }
    $states = array('', 'available', 'unavailable');
    $allowed = $source !== 'availability_manual' ? null : array_fill_keys(bvmgr_vendor_portal_availability_dates(), true);
    foreach ($changes as $date => $state) {
        if (!is_string($date) || !bvmgr_sch_season_is_valid_ymd($date) || !in_array($state, $states, true)
            || ($allowed !== null && !isset($allowed[$date]))
            || ($expected !== null && !in_array($expected[$date] ?? null, $states, true))
            || ($allowed !== null && $expected === null)) {
            return new WP_Error('availability_invalid', __('This availability edit is invalid or outside the editable calendar. Reload the page and try again.', 'backstage-venue-manager'));
        }
    }

    return bvmgr_vendor_availability_lock($vendor_id, static function () use ($vendor_id, $changes, $expected, $source, $states) {
        $stored = get_post_meta($vendor_id, '_vms_availability_manual', true);
        $manual = is_array($stored) ? $stored : array();
        $previous = array();
        if ($source === 'availability_manual' && bvmgr_vendor_portal_availability_assignments($vendor_id, array_keys($changes))) {
            return new WP_Error('availability_assigned', __('Booked and tentative dates are read-only. Reload the calendar before making other changes.', 'backstage-venue-manager'));
        }
        foreach ($changes as $date => $state) {
            $before = $manual[$date] ?? '';
            $before = in_array($before, $states, true) ? $before : '';
            if ($expected !== null && $before !== $state && $before !== $expected[$date]) {
                return new WP_Error('availability_conflict', __('Availability changed in another session. Reload the calendar before saving.', 'backstage-venue-manager'));
            }
            $previous[$date] = $before;
            if ($state === '') {
                unset($manual[$date]);
            } else {
                $manual[$date] = $state;
            }
        }
        $changed = $manual !== (is_array($stored) ? $stored : array());
        if ($changed && !update_post_meta($vendor_id, '_vms_availability_manual', $manual)) {
            return new WP_Error('availability_write', __('Availability could not be saved. Try again.', 'backstage-venue-manager'));
        }
        update_post_meta($vendor_id, '_vms_availability_preferred_method', 'manual');
        if ($changed || $source !== 'availability_manual') {
            bvmgr_vendor_flag_vendor_update($vendor_id, $source);
        }
        return $previous;
    });
}

/** Accept only explicitly edited dates from the fallback form. */
function bvmgr_vendor_portal_apply_availability_form(int $vendor_id, string $payload)
{
    $edits = json_decode($payload, true);
    if (!is_array($edits) || count($edits) > 731) {
        return new WP_Error('availability_invalid', __('Reload the calendar before saving availability.', 'backstage-venue-manager'));
    }
    $changes = $expected = array();
    foreach ($edits as $date => $edit) {
        if (!is_array($edit) || !is_string($edit['state'] ?? null) || !is_string($edit['previous'] ?? null)) {
            return new WP_Error('availability_invalid', __('Reload the calendar before saving availability.', 'backstage-venue-manager'));
        }
        $changes[$date] = $edit['state'];
        $expected[$date] = $edit['previous'];
    }
    return bvmgr_vendor_availability_apply($vendor_id, $changes, 'availability_manual', $expected);
}
