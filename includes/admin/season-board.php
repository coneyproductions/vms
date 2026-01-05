<?php

add_action('admin_menu', function () {
    add_submenu_page(
        'vms',
        __('Season Board', 'vms'),
        __('Season Board', 'vms'),
        'manage_options',
        'vms-season-board',
        'vms_render_season_board_page'
    );
});


function vms_render_season_board_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    vms_render_current_venue_selector();

    $active_dates = get_option('vms_active_dates', array());
    if (!is_array($active_dates)) $active_dates = array();

    echo '<div class="wrap">';
    echo '<h1>Season Board</h1>';
    echo '<p>Broad season view of dates, booked vendors, and event plan status.</p>';

    if (empty($active_dates)) {
        echo '<p><em>No season dates configured yet.</em></p>';
        echo '</div>';
        return;
    }

    // Build a lookup: date => plan_id
    $plans = get_posts(array(
        'post_type'      => 'vms_event_plan',
        'posts_per_page' => -1,
        'post_status'    => array('publish', 'draft', 'pending'),
        'meta_query'     => array(
            array(
                'key'     => '_vms_event_date',
                'compare' => 'EXISTS',
            ),
        ),
        'fields' => 'ids',
    ));

    $plan_by_date = array();
    foreach ($plans as $plan_id) {
        $d = get_post_meta($plan_id, '_vms_event_date', true);
        if ($d) $plan_by_date[$d] = (int) $plan_id;
    }

    echo '<table class="widefat striped" style="max-width:1100px;">';
    echo '<thead><tr>';
    echo '<th style="width:160px;">Date</th>';
    echo '<th style="width:70px;">Day</th>';
    echo '<th style="width:140px;">Event Plan</th>';
    echo '<th>Band</th>';
    echo '<th style="width:170px;">Plan Status</th>';
    echo '<th>Food Truck</th>';
    echo '<th style="width:150px;">Publish</th>';
    echo '</tr></thead><tbody>';

    $current_month = '';

    foreach ($active_dates as $date_str) {
        $ts = strtotime($date_str);
        if (!$ts) continue;

        $month_label = date_i18n('F Y', $ts);
        if ($month_label !== $current_month) {
            $current_month = $month_label;
            echo '<tr class="vms-month-header"><td colspan="7"><strong>' . esc_html($month_label) . '</strong></td></tr>';
        }

        $day_short = date_i18n('D', $ts);
        $nice_date = date_i18n('M j, Y', $ts);

        $plan_id = isset($plan_by_date[$date_str]) ? (int) $plan_by_date[$date_str] : 0;

        $band_name = '—';
        $food_name = '—';
        $plan_status = '—';
        $publish_info = '—';

        if ($plan_id) {
            $edit_link = get_edit_post_link($plan_id);
            $plan_status = get_post_meta($plan_id, '_vms_event_plan_status', true);
            if (!$plan_status) $plan_status = get_post_status($plan_id);

            $band_id = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
            if ($band_id) {
                $band_name = '<a href="' . esc_url(get_edit_post_link($band_id)) . '">' . esc_html(get_the_title($band_id)) . '</a>';
            }

            $food_id = (int) get_post_meta($plan_id, '_vms_food_vendor_id', true);
            if ($food_id) {
                $food_name = '<a href="' . esc_url(get_edit_post_link($food_id)) . '">' . esc_html(get_the_title($food_id)) . '</a>';
            }

            // Publish info (optional)
            $publish_on = get_post_meta($plan_id, '_vms_publish_on', true);
            $tec_id = (int) get_post_meta($plan_id, '_vms_tec_event_id', true);

            $publish_info_parts = array();
            if ($publish_on) $publish_info_parts[] = 'Publish on: ' . esc_html($publish_on);
            if ($tec_id) $publish_info_parts[] = 'TEC: <a href="' . esc_url(get_edit_post_link($tec_id)) . '">edit</a>';

            if (!empty($publish_info_parts)) {
                $publish_info = implode('<br>', $publish_info_parts);
            }

            $plan_cell = $edit_link ? '<a class="button button-small" href="' . esc_url($edit_link) . '">Open</a>' : 'Open';
        } else {
            // No plan yet
            $plan_cell = '<em>—</em>';
        }

        echo '<tr>';
        echo '<td>' . esc_html($nice_date) . '</td>';
        echo '<td>' . esc_html($day_short) . '</td>';
        echo '<td>' . ($plan_id ? $plan_cell : '<em>No plan</em>') . '</td>';
        echo '<td>' . $band_name . '</td>';
        echo '<td>' . esc_html(ucfirst((string)$plan_status)) . '</td>';
        echo '<td>' . $food_name . '</td>';
        echo '<td>' . $publish_info . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}
