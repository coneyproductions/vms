<?php

/**
 * Legacy admin page: “Season Board”.
 *
 * This file remains for backwards compatibility.
 * The feature was renamed to “Schedule”, and the current implementation lives in
 * admin/schedule.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('vms_render_season_board_page')) {
    function vms_render_season_board_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $schedule_url = admin_url('admin.php?page=vms');

        echo '<div class="wrap">';
        echo '<h1>Season Board</h1>';
        echo '<div class="notice notice-info"><p>';
        echo 'This page has been renamed to <strong>Schedule</strong>. ';
        echo '<a href="' . esc_url($schedule_url) . '">Go to Schedule</a>.';
        echo '</p></div>';
        echo '</div>';
    }
}
