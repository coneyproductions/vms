<?php
defined('ABSPATH') || exit;

/**
 * Venue Health Check (admin-only)
 *
 * Goals:
 * - Surface missing schedule fields for legacy venues.
 * - Prevent "why is Schedule empty?" confusion.
 * - Provide a fast "Fix now" jump to the relevant metabox.
 */

add_action('add_meta_boxes', function (): void {
    add_meta_box(
        'vms_venue_health_check',
        __('Venue Health Check', 'backstage-venue-manager'),
        'vms_render_venue_health_check_box',
        'vms_venue',
        'side',
        'high'
    );
});

/**
 * @param WP_Post $post
 */
function vms_render_venue_health_check_box($post): void {

    $post_id = isset($post->ID) ? (int) $post->ID : 0;
    if ($post_id <= 0) {
        echo '<p>' . esc_html__('No venue selected.', 'backstage-venue-manager') . '</p>';
        return;
    }

    $issues = vms_get_venue_health_check_issues($post_id);
    $has_errors = !empty($issues['errors']);
    $has_warnings = !empty($issues['warnings']);

    $pill_class = ($has_errors) ? 'is-not-ready' : 'is-ready';
    $pill_text  = ($has_errors) ? __('Action required', 'backstage-venue-manager') : __('Looks good', 'backstage-venue-manager');

    echo '<div class="vms-healthcheck">';
    echo '<p class="vms-healthcheck__pillrow">';
    echo '<span class="vms-status-pill ' . esc_attr($pill_class) . '">' . esc_html($pill_text) . '</span>';
    echo '</p>';

    if (!$has_errors && !$has_warnings) {
        echo '<p class="vms-healthcheck__desc">' . esc_html__('Critical venue schedule fields are set.', 'backstage-venue-manager') . '</p>';
        echo '</div>';
        return;
    }

    if ($has_errors) {
        echo '<div class="vms-healthcheck__section">';
        echo '<div class="vms-healthcheck__heading">' . esc_html__('Must fix', 'backstage-venue-manager') . '</div>';
        echo '<ul class="vms-healthcheck__list">';
        foreach ($issues['errors'] as $row) {
            echo '<li>' . wp_kses($row, vms_healthcheck_allowed_html()) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    if ($has_warnings) {
        echo '<div class="vms-healthcheck__section">';
        echo '<div class="vms-healthcheck__heading">' . esc_html__('Recommended', 'backstage-venue-manager') . '</div>';
        echo '<ul class="vms-healthcheck__list">';
        foreach ($issues['warnings'] as $row) {
            echo '<li>' . wp_kses($row, vms_healthcheck_allowed_html()) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    if (!empty($issues['notes'])) {
        echo '<div class="vms-healthcheck__section">';
        echo '<div class="vms-healthcheck__heading">' . esc_html__('Notes', 'backstage-venue-manager') . '</div>';
        echo '<ul class="vms-healthcheck__list">';
        foreach ($issues['notes'] as $row) {
            echo '<li>' . wp_kses($row, vms_healthcheck_allowed_html()) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    echo '</div>';
}

/**
 * Minimal allowlist for health check list items.
 */
function vms_healthcheck_allowed_html(): array {
    return array(
        'a' => array(
            'href'  => true,
            'class' => true,
        ),
        'strong' => array(),
        'span' => array(
            'class' => true,
        ),
    );
}

/**
 * Returns arrays of HTML-safe list item strings.
 *
 * @return array{errors: string[], warnings: string[], notes: string[]}
 */
function vms_get_venue_health_check_issues(int $venue_id): array {

    $out = array(
        'errors'   => array(),
        'warnings' => array(),
        'notes'    => array(),
    );

    // Weekly open days
    $open_days_raw = get_post_meta($venue_id, '_vms_venue_open_days', true);
    $open_days = vms_healthcheck_normalize_open_days($open_days_raw);

    if (empty($open_days)) {
        $out['errors'][] = sprintf(
            '%s <a class="button button-small" href="#vms_venue_schedule">%s</a>',
            esc_html__('Weekly Open Days are not set.', 'backstage-venue-manager'),
            esc_html__('Fix now', 'backstage-venue-manager')
        );
    } else {
        // Detect legacy/odd formats (string/number) so operators know why things might look weird.
        if (!is_array($open_days_raw)) {
            $out['notes'][] = esc_html__('Legacy Open Days format detected; it will be normalized automatically on save/publish.', 'backstage-venue-manager');
        }
    }

    // Default event times
    $start = (string) get_post_meta($venue_id, '_vms_default_start_time', true);
    $dur   = (int) get_post_meta($venue_id, '_vms_default_duration_min', true);
    $end   = (string) get_post_meta($venue_id, '_vms_default_end_time', true);

    $has_start = ($start !== '');
    $has_end_or_duration = ($end !== '' || $dur > 0);

    if (!$has_start || !$has_end_or_duration) {
        $missing = array();
        if (!$has_start) {
            $missing[] = esc_html__('Default Start', 'backstage-venue-manager');
        }
        if (!$has_end_or_duration) {
            $missing[] = esc_html__('Default End or Duration', 'backstage-venue-manager');
        }

        $out['warnings'][] = sprintf(
            '%s <strong>%s</strong>. <a class="button button-small" href="#vms_venue_default_times">%s</a>',
            esc_html__('Default Event Times missing:', 'backstage-venue-manager'),
            esc_html(implode(', ', $missing)),
            esc_html__('Fix now', 'backstage-venue-manager')
        );
    }

    // Seasons sanity
    $year_round = (int) get_post_meta($venue_id, '_vms_venue_open_year_round', true);
    $seasons = get_post_meta($venue_id, '_vms_venue_seasons', true);
    if (!is_array($seasons)) {
        $seasons = array();
    }

    if ($year_round !== 1 && !empty($seasons)) {
        foreach ($seasons as $s) {
            if (!is_array($s)) {
                $out['notes'][] = esc_html__('Legacy Seasons format detected; consider re-saving the Venue Schedule.', 'backstage-venue-manager');
                break;
            }
            $st = isset($s['start']) ? (string) $s['start'] : '';
            $en = isset($s['end']) ? (string) $s['end'] : '';
            if ($st === '' || $en === '') {
                $out['notes'][] = esc_html__('A Season range appears incomplete; consider re-saving the Venue Schedule.', 'backstage-venue-manager');
                break;
            }
        }
    }

    return $out;
}

/**
 * Normalize open days from legacy formats (array|numeric|string) to int array 0..6.
 */
function vms_healthcheck_normalize_open_days($raw): array {
    // Prefer the canonical helper if available.
    if (function_exists('bvmgr_normalize_int_array')) {
        $arr = bvmgr_normalize_int_array($raw);
    } else {
        $arr = array();
        if (is_array($raw)) {
            $arr = $raw;
        } elseif (is_numeric($raw)) {
            $arr = array((int) $raw);
        } elseif (is_string($raw) && $raw !== '') {
            $parts = preg_split('/[^0-9]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
            $arr = is_array($parts) ? $parts : array();
        }
    }

    $clean = array();
    foreach ((array) $arr as $v) {
        $n = (int) $v;
        if ($n >= 0 && $n <= 6) {
            $clean[] = $n;
        }
    }
    $clean = array_values(array_unique($clean));
    sort($clean);
    return $clean;
}
