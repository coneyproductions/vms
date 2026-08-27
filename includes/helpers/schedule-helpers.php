<?php
defined('ABSPATH') || exit;
/**
 * VMS Schedule Helpers
 */

/**
 * Trim common date suffix patterns from a title.
 *
 * Examples removed:
 *  - " — 2026-01-16"
 *  - " - Jan 17, 2026"
 *  - " (01/17/2026)"
 */
function bvmgr_trim_title_date_suffix($title) {
    $title = trim((string) $title);

    $patterns = array(
        '/\s*[–—-]\s*\w+\s+\d{1,2},\s+\d{4}\s*$/u',      // - Jan 17, 2026
        '/\s*[–—-]\s*\d{1,2}\/\d{1,2}\/\d{2,4}\s*$/u',   // - 01/17/2026
        '/\s*[–—-]\s*\d{4}-\d{2}-\d{2}\s*$/u',           // - 2026-01-16
        '/\s*\(\s*\w+\s+\d{1,2},\s+\d{4}\s*\)\s*$/u',     // (Jan 17, 2026)
        '/\s*\(\s*\d{1,2}\/\d{1,2}\/\d{2,4}\s*\)\s*$/u', // (01/17/2026)
        '/\s*\(\s*\d{4}-\d{2}-\d{2}\s*\)\s*$/u'          // (2026-01-16)
    );

    return trim(preg_replace($patterns, '', $title));
}

/**
 * Build a compact, deterministic display label for an Event Plan.
 *
 * Rules (compact, shared across Schedule list + calendar):
 * - If VMS vendor name and TEC title both exist and match (case-insensitive), show vendor name.
 * - If VMS vendor name exists, show vendor name.
 * - If VMS vendor name is blank but TEC title exists, show "TEC: {title}".
 * - If both are blank, show "TBD - draft" (or "TBD ({Status})" if not Draft).
 */
function bvmgr_event_plan_compact_label($plan_id): string {
    $plan_id = (int) $plan_id;
    if ($plan_id <= 0) {
        return 'TBD - draft';
    }

    // Resolve plan status (meta-driven; stable for Schedule labeling).
    $status = '';
    if (function_exists('bvmgr_event_plan_get_status')) {
        $status = (string) bvmgr_event_plan_get_status($plan_id, 'schedule_admin');
    }
    if ($status === '') {
        $status = sanitize_key((string) get_post_meta($plan_id, bvmgr_meta_key('event_plan', 'status'), true));
    }
    $status = sanitize_key((string) $status);
    if ($status === 'canceled') {
        $status = 'cancelled';
    }
    if ($status === '') {
        $status = 'draft';
    }

    // Compact status suffix for schedule cells.
    $suffix = '';
    if ($status !== 'published') {
        $label = '';
        if ($status === 'cancelled') {
            $label = 'Cancelled';
        } elseif ($status === 'postponed') {
            $label = 'Postponed';
        } elseif ($status === 'ready') {
            $label = 'Ready';
        } elseif ($status === 'draft') {
            $label = 'Draft';
        } elseif ($status === 'confirmed') {
            $label = 'Confirmed';
        } else {
            $label = function_exists('bvmgr_event_plan_status_label')
                ? (string) bvmgr_event_plan_status_label($status)
                : ucwords(str_replace(array('_', '-'), ' ', $status));
        }
        $suffix = ' (' . $label . ')';
    }

    // Use the Event Plan's own title as the single source of truth for schedule labeling.
    $base = trim((string) get_the_title($plan_id));

    // Normalize any trailing status markers so we never show duplicate status text.
    if ($base !== '') {
        $base = preg_replace('/\s*[\(\[]\s*(draft|ready|cancelled|canceled|postponed|confirmed)\s*[\)\]]\s*$/iu', '', $base);
        $base = preg_replace('/\s*[–—-]\s*(draft|ready|cancelled|canceled|postponed|confirmed)\s*$/iu', '', $base);
        $base = trim((string) $base);
    }

    if ($base === '') {
        if ($status === 'draft') {
            return 'TBD - draft';
        }
        return 'TBD' . $suffix;
    }

    return $base . $suffix;
}


/**
 * Get a plan's associated TEC event ID.
 */
function bvmgr_get_plan_tec_event_id($plan_id) {
    $plan_id = (int) $plan_id;
    if ($plan_id <= 0) return 0;

    return (int) get_post_meta($plan_id, '_vms_tec_event_id', true);
}

/**
 * Build the headliner link HTML for a VMS plan.
 *
 * Uses the shared schedule label and links to the Event Plan edit screen.
 * This keeps Schedule list + calendar consistent and avoids TEC title ambiguity.
 */
function bvmgr_get_plan_headliner_link_html($plan_id, $css_class = 'vms-event-headliner-link') {
    $plan_id = (int) $plan_id;
    if ($plan_id <= 0) return '';

    // Admin Schedule should always be deterministic and consistent with Calendar view.
    // Always link to the plan edit screen and use the shared schedule label.
    $label = function_exists('bvmgr_event_plan_compact_label')
        ? bvmgr_event_plan_compact_label($plan_id)
        : vms_sch_plan_label($plan_id);

    $edit_url = get_edit_post_link($plan_id, 'raw');
    if (empty($edit_url)) {
        $edit_url = admin_url('post.php?post=' . (int) $plan_id . '&action=edit');
    }

    return '<a href="' . esc_url($edit_url) . '" class="' . esc_attr($css_class) . '">' .
        esc_html($label) .
        '</a>';
}

/**
 * Normalize a "plan row" into a plan_id.
 *
 * Supports:
 *  - int plan id
 *  - array with ['plan_id' => 123]
 */
function bvmgr_get_plan_id_from_row($row) {
    if (is_array($row) && isset($row['plan_id']) && is_numeric($row['plan_id'])) {
        return (int) $row['plan_id'];
    }
    if (is_numeric($row)) {
        return (int) $row;
    }
    return 0;
}

add_filter('vms_schedule_items_for_dashboard', function ($items, $args) {
  // Call the same function Schedule uses to fetch events for a date range.
  // Replace the function name below with your real one.

  // return vms_schedule_query_items($args);

  return $items;
}, 10, 2);
