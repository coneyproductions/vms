<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Vendor Defaults (Admin)
 *
 * Purpose:
 * - Stores vendor pay-preference reference values
 * - Stores default compensation settings for this vendor
 * - These values are copied into Event Plans when:
 *   - "Apply Band Defaults" is clicked
 *   - OR auto-applied if enabled
 * 
 */

/**
 * Register metabox
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_vendor_defaults',
        __('Pay Structure + Booking Defaults', 'backstage-venue-manager'),
        'vms_render_vendor_defaults_metabox',
        'vms_vendor',
        'normal',
        'default'
    );
});

/**
 * Save handler
 */
add_action('save_post_vms_vendor', function ($post_id, $post) {

    if ($post->post_type !== 'vms_vendor') return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $nonce = (isset($_POST['vms_vendor_defaults_nonce']) && !is_array($_POST['vms_vendor_defaults_nonce']))
        ? sanitize_text_field(wp_unslash((string) $_POST['vms_vendor_defaults_nonce']))
        : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_save_vendor_defaults')) {
        return;
    }
 
    $get = function ($key) {
        return isset($_POST[$key])
            ? sanitize_text_field(wp_unslash($_POST[$key]))
            : '';
    };
    $vk = function ($field, $fallback) {
        if (!function_exists('vms_meta_key')) {
            return $fallback;
        }
        $mapped = (string) vms_meta_key('vendor', $field);
        return ($mapped !== '') ? $mapped : $fallback;
    };

    $k_structure = $vk('default_comp_structure', '_vms_default_comp_structure');
    $k_flat      = $vk('default_flat_fee_amount', '_vms_default_flat_fee_amount');
    $k_support_flat = $vk('default_supporting_flat_fee_amount', '_vms_default_supporting_flat_fee_amount');
    $k_split     = $vk('default_door_split_percent', '_vms_default_door_split_percent');
    $k_bonus_mode = $vk('default_attendance_bonus_mode', '_vms_default_attendance_bonus_mode');
    $k_bonus_start = $vk('default_attendance_bonus_start_count', '_vms_default_attendance_bonus_start_count');
    $k_bonus_step_size = $vk('default_attendance_bonus_step_size', '_vms_default_attendance_bonus_step_size');
    $k_bonus_step_bonus = $vk('default_attendance_bonus_step_bonus', '_vms_default_attendance_bonus_step_bonus');
    $k_bonus_per_ticket = $vk('default_attendance_bonus_per_ticket_rate', '_vms_default_attendance_bonus_per_ticket_rate');
    $k_bonus_max = $vk('default_attendance_bonus_max_bonus', '_vms_default_attendance_bonus_max_bonus');
    $k_comp_package = $vk('default_comp_package_id', '_vms_default_comp_package_id');
    $k_by_venue  = $vk('default_comp_by_venue', '_vms_vendor_default_comp_by_venue');
    $k_by_venue_dow = $vk('default_comp_by_venue_dow', '_vms_vendor_default_comp_by_venue_dow');
    $k_comm      = $vk('default_commission_percent', '_vms_default_commission_percent');
    $k_mode      = $vk('default_commission_mode', '_vms_default_commission_mode');
    $k_fee_min   = $vk('fee_min', '_vms_fee_min');
    $k_fee_max   = $vk('fee_max', '_vms_fee_max');
    $k_min_rate  = $vk('min_show_rate', '_vms_min_show_rate');

    // Compensation defaults
    update_post_meta($post_id, $k_structure, $get('vms_default_comp_structure'));

    $flat = $get('vms_default_flat_fee_amount');
    update_post_meta($post_id, $k_flat, $flat === '' ? '' : (float) $flat);

    $support_flat = $get('vms_default_supporting_flat_fee_amount');
    if ($support_flat === '') delete_post_meta($post_id, $k_support_flat);
    else update_post_meta($post_id, $k_support_flat, (float) $support_flat);

    $split = $get('vms_default_door_split_percent');
    update_post_meta($post_id, $k_split, $split === '' ? '' : (float) $split);

    $bonus_mode = $get('vms_default_attendance_bonus_mode');
    if (!in_array($bonus_mode, array('step', 'continuous'), true)) {
        $bonus_mode = '';
    }

    $bonus_start = $get('vms_default_attendance_bonus_start_count');
    $bonus_start = ($bonus_start === '') ? '' : max(0, (int) floor((float) $bonus_start));

    $bonus_step_size = $get('vms_default_attendance_bonus_step_size');
    $bonus_step_size = ($bonus_step_size === '') ? '' : max(1, (int) floor((float) $bonus_step_size));

    $bonus_step_bonus = $get('vms_default_attendance_bonus_step_bonus');
    $bonus_step_bonus = ($bonus_step_bonus === '') ? '' : max(0, (float) $bonus_step_bonus);

    $bonus_per_ticket = $get('vms_default_attendance_bonus_per_ticket_rate');
    $bonus_per_ticket = ($bonus_per_ticket === '') ? '' : max(0, (float) $bonus_per_ticket);

    $bonus_max = $get('vms_default_attendance_bonus_max_bonus');
    $bonus_max = ($bonus_max === '') ? '' : max(0, (float) $bonus_max);

    $comm = $get('vms_default_commission_percent');
    $mode = $get('vms_default_commission_mode');
    if (!in_array($mode, ['artist_fee', 'gross'], true)) {
        $mode = 'artist_fee';
    }

    if ($get('vms_default_comp_structure') === 'attendance_bonus') {
        update_post_meta($post_id, $k_bonus_mode, $bonus_mode);
        if ($bonus_start === '') delete_post_meta($post_id, $k_bonus_start);
        else update_post_meta($post_id, $k_bonus_start, (int) $bonus_start);

        if ($bonus_max === '') delete_post_meta($post_id, $k_bonus_max);
        else update_post_meta($post_id, $k_bonus_max, (float) $bonus_max);

        if ($bonus_mode === 'step') {
            if ($bonus_step_size === '') delete_post_meta($post_id, $k_bonus_step_size);
            else update_post_meta($post_id, $k_bonus_step_size, (int) $bonus_step_size);

            if ($bonus_step_bonus === '') delete_post_meta($post_id, $k_bonus_step_bonus);
            else update_post_meta($post_id, $k_bonus_step_bonus, (float) $bonus_step_bonus);

            delete_post_meta($post_id, $k_bonus_per_ticket);
        } elseif ($bonus_mode === 'continuous') {
            if ($bonus_per_ticket === '') delete_post_meta($post_id, $k_bonus_per_ticket);
            else update_post_meta($post_id, $k_bonus_per_ticket, (float) $bonus_per_ticket);

            delete_post_meta($post_id, $k_bonus_step_size);
            delete_post_meta($post_id, $k_bonus_step_bonus);
        } else {
            delete_post_meta($post_id, $k_bonus_step_size);
            delete_post_meta($post_id, $k_bonus_step_bonus);
            delete_post_meta($post_id, $k_bonus_per_ticket);
        }
    } else {
        delete_post_meta($post_id, $k_bonus_mode);
        delete_post_meta($post_id, $k_bonus_start);
        delete_post_meta($post_id, $k_bonus_step_size);
        delete_post_meta($post_id, $k_bonus_step_bonus);
        delete_post_meta($post_id, $k_bonus_per_ticket);
        delete_post_meta($post_id, $k_bonus_max);
    }

    $selected_comp_package_id = isset($_POST['vms_default_comp_package_id']) ? absint($_POST['vms_default_comp_package_id']) : 0;
    if ($selected_comp_package_id > 0 && get_post_type($selected_comp_package_id) === 'vms_comp_package') {
        update_post_meta($post_id, $k_comp_package, $selected_comp_package_id);
    } else {
        delete_post_meta($post_id, $k_comp_package);
    }

    $create_template_name = isset($_POST['vms_create_comp_template_name'])
        ? sanitize_text_field(wp_unslash($_POST['vms_create_comp_template_name']))
        : '';
    if ($create_template_name !== '') {
        $package_type = 'flat';
        $package_structure = $get('vms_default_comp_structure');
        if ($package_structure === 'flat_fee_door_split') {
            $package_type = 'flat_plus_split';
        } elseif ($package_structure === 'door_split') {
            $package_type = 'door_split';
        } elseif ($package_structure === 'attendance_bonus') {
            $package_type = 'attendance_bonus';
        }

        $new_package_id = wp_insert_post(array(
            'post_type' => 'vms_comp_package',
            'post_status' => 'publish',
            'post_title' => $create_template_name,
        ), true);

        if (!is_wp_error($new_package_id) && $new_package_id > 0) {
            update_post_meta($new_package_id, '_vms_venue_id', 0);
            update_post_meta($new_package_id, '_vms_comp_type', $package_type);
            if ($flat === '') {
                delete_post_meta($new_package_id, '_vms_flat_fee');
                delete_post_meta($new_package_id, '_vms_flat_fee_amount');
            } else {
                update_post_meta($new_package_id, '_vms_flat_fee', (float) $flat);
                update_post_meta($new_package_id, '_vms_flat_fee_amount', (float) $flat);
            }

            if ($split === '') {
                delete_post_meta($new_package_id, '_vms_split_percent_artist');
                delete_post_meta($new_package_id, '_vms_door_split_percent');
            } else {
                update_post_meta($new_package_id, '_vms_split_percent_artist', (float) $split);
                update_post_meta($new_package_id, '_vms_door_split_percent', (float) $split);
            }
            update_post_meta($new_package_id, '_vms_split_basis', 'gross');

            if ($comm === '') {
                delete_post_meta($new_package_id, '_vms_commission_percent');
            } else {
                update_post_meta($new_package_id, '_vms_commission_percent', (float) $comm);
            }
            update_post_meta($new_package_id, '_vms_commission_mode', ($mode === 'gross') ? 'gross' : 'artist_fee');
            update_post_meta($new_package_id, '_vms_commission_base', 'flat_fee');

            if ($package_structure === 'attendance_bonus') {
                if ($bonus_mode === '') {
                    delete_post_meta($new_package_id, '_vms_attendance_bonus_mode');
                } else {
                    update_post_meta($new_package_id, '_vms_attendance_bonus_mode', $bonus_mode);
                }
                if ($bonus_start === '') delete_post_meta($new_package_id, '_vms_attendance_bonus_start_count');
                else update_post_meta($new_package_id, '_vms_attendance_bonus_start_count', (int) $bonus_start);
                if ($bonus_max === '') delete_post_meta($new_package_id, '_vms_attendance_bonus_max_bonus');
                else update_post_meta($new_package_id, '_vms_attendance_bonus_max_bonus', (float) $bonus_max);

                if ($bonus_mode === 'step') {
                    if ($bonus_step_size === '') delete_post_meta($new_package_id, '_vms_attendance_bonus_step_size');
                    else update_post_meta($new_package_id, '_vms_attendance_bonus_step_size', (int) $bonus_step_size);
                    if ($bonus_step_bonus === '') delete_post_meta($new_package_id, '_vms_attendance_bonus_step_bonus');
                    else update_post_meta($new_package_id, '_vms_attendance_bonus_step_bonus', (float) $bonus_step_bonus);
                    delete_post_meta($new_package_id, '_vms_attendance_bonus_per_ticket_rate');
                } elseif ($bonus_mode === 'continuous') {
                    if ($bonus_per_ticket === '') delete_post_meta($new_package_id, '_vms_attendance_bonus_per_ticket_rate');
                    else update_post_meta($new_package_id, '_vms_attendance_bonus_per_ticket_rate', (float) $bonus_per_ticket);
                    delete_post_meta($new_package_id, '_vms_attendance_bonus_step_size');
                    delete_post_meta($new_package_id, '_vms_attendance_bonus_step_bonus');
                } else {
                    delete_post_meta($new_package_id, '_vms_attendance_bonus_step_size');
                    delete_post_meta($new_package_id, '_vms_attendance_bonus_step_bonus');
                    delete_post_meta($new_package_id, '_vms_attendance_bonus_per_ticket_rate');
                }
            } else {
                delete_post_meta($new_package_id, '_vms_attendance_bonus_mode');
                delete_post_meta($new_package_id, '_vms_attendance_bonus_start_count');
                delete_post_meta($new_package_id, '_vms_attendance_bonus_step_size');
                delete_post_meta($new_package_id, '_vms_attendance_bonus_step_bonus');
                delete_post_meta($new_package_id, '_vms_attendance_bonus_per_ticket_rate');
                delete_post_meta($new_package_id, '_vms_attendance_bonus_max_bonus');
            }

            update_post_meta($post_id, $k_comp_package, (int) $new_package_id);
        }
    }

    $by_venue_raw = (isset($_POST['vms_default_comp_by_venue']) && is_array($_POST['vms_default_comp_by_venue']))
        ? $_POST['vms_default_comp_by_venue']
        : array();
    $by_venue_out = array();

    foreach ($by_venue_raw as $venue_key => $row_raw) {
        $venue_id = absint($venue_key);
        if ($venue_id <= 0 || !is_array($row_raw)) {
            continue;
        }
        if (get_post_type($venue_id) !== 'vms_venue') {
            continue;
        }

        $structure = isset($row_raw['structure'])
            ? sanitize_key((string) wp_unslash($row_raw['structure']))
            : '';
        if (!in_array($structure, array('flat_fee', 'flat_fee_door_split', 'door_split'), true)) {
            $structure = '';
        }

        $flat_raw = isset($row_raw['flat_fee_amount'])
            ? trim((string) wp_unslash($row_raw['flat_fee_amount']))
            : '';
        $flat_raw = preg_replace('/[^0-9.\-]/', '', $flat_raw);

        $split_raw = isset($row_raw['door_split_percent'])
            ? trim((string) wp_unslash($row_raw['door_split_percent']))
            : '';
        $split_raw = preg_replace('/[^0-9.\-]/', '', $split_raw);

        $row = array();
        if ($structure !== '') {
            $row['structure'] = $structure;
        }
        if ($flat_raw !== '') {
            $flat_val = (float) $flat_raw;
            if ($flat_val < 0) $flat_val = 0;
            $row['flat_fee_amount'] = $flat_val;
        }
        if ($split_raw !== '') {
            $split_val = (float) $split_raw;
            if ($split_val < 0) $split_val = 0;
            if ($split_val > 100) $split_val = 100;
            $row['door_split_percent'] = $split_val;
        }

        if (!empty($row)) {
            $by_venue_out[(string) $venue_id] = $row;
        }
    }

    if (empty($by_venue_out)) {
        delete_post_meta($post_id, $k_by_venue);
    } else {
        update_post_meta($post_id, $k_by_venue, $by_venue_out);
    }

    $by_venue_dow_raw = (isset($_POST['vms_default_comp_by_venue_dow']) && is_array($_POST['vms_default_comp_by_venue_dow']))
        ? $_POST['vms_default_comp_by_venue_dow']
        : array();
    $by_venue_dow_out = array();

    foreach ($by_venue_dow_raw as $venue_key => $dow_rows_raw) {
        $venue_id = absint($venue_key);
        if ($venue_id <= 0 || !is_array($dow_rows_raw)) {
            continue;
        }
        if (get_post_type($venue_id) !== 'vms_venue') {
            continue;
        }

        $venue_rows = array();
        foreach ($dow_rows_raw as $dow_key => $row_raw) {
            $dow = (int) $dow_key;
            if ($dow < 0 || $dow > 6 || !is_array($row_raw)) {
                continue;
            }

            $structure = isset($row_raw['structure'])
                ? sanitize_key((string) wp_unslash($row_raw['structure']))
                : '';
            if (!in_array($structure, array('flat_fee', 'flat_fee_door_split', 'door_split'), true)) {
                $structure = '';
            }

            $flat_raw = isset($row_raw['flat_fee_amount'])
                ? trim((string) wp_unslash($row_raw['flat_fee_amount']))
                : '';
            $flat_raw = preg_replace('/[^0-9.\-]/', '', $flat_raw);

            $split_raw = isset($row_raw['door_split_percent'])
                ? trim((string) wp_unslash($row_raw['door_split_percent']))
                : '';
            $split_raw = preg_replace('/[^0-9.\-]/', '', $split_raw);

            $row = array();
            if ($structure !== '') {
                $row['structure'] = $structure;
            }
            if ($flat_raw !== '') {
                $flat_val = (float) $flat_raw;
                if ($flat_val < 0) $flat_val = 0;
                $row['flat_fee_amount'] = $flat_val;
            }
            if ($split_raw !== '') {
                $split_val = (float) $split_raw;
                if ($split_val < 0) $split_val = 0;
                if ($split_val > 100) $split_val = 100;
                $row['door_split_percent'] = $split_val;
            }

            if (!empty($row)) {
                $venue_rows[(string) $dow] = $row;
            }
        }

        if (!empty($venue_rows)) {
            $by_venue_dow_out[(string) $venue_id] = $venue_rows;
        }
    }

    if (empty($by_venue_dow_out)) {
        delete_post_meta($post_id, $k_by_venue_dow);
    } else {
        update_post_meta($post_id, $k_by_venue_dow, $by_venue_dow_out);
    }

    $comm = $get('vms_default_commission_percent');
    update_post_meta($post_id, $k_comm, $comm === '' ? '' : (float) $comm);

    $mode = $get('vms_default_commission_mode');
    if (!in_array($mode, ['artist_fee', 'gross'], true)) {
        $mode = 'artist_fee';
    }
    update_post_meta($post_id, $k_mode, $mode);

    $fee_min = $get('vms_fee_min');
    $fee_max = $get('vms_fee_max');
    $min_rate = $get('vms_min_show_rate');

    if ($fee_min === '') delete_post_meta($post_id, $k_fee_min);
    else update_post_meta($post_id, $k_fee_min, (float) $fee_min);

    if ($fee_max === '') delete_post_meta($post_id, $k_fee_max);
    else update_post_meta($post_id, $k_fee_max, (float) $fee_max);

    if ($min_rate === '') delete_post_meta($post_id, $k_min_rate);
    else update_post_meta($post_id, $k_min_rate, (float) $min_rate);

}, 10, 2);

/**
 * Render metabox
 */
function vms_render_vendor_defaults_metabox($post)
{
    wp_nonce_field('vms_save_vendor_defaults', 'vms_vendor_defaults_nonce');
    $vk = function ($field, $fallback) {
        if (!function_exists('vms_meta_key')) {
            return $fallback;
        }
        $mapped = (string) vms_meta_key('vendor', $field);
        return ($mapped !== '') ? $mapped : $fallback;
    };

    $m = function ($key, $default = '') use ($post) {
        $v = get_post_meta($post->ID, $key, true);
        return ($v === '' || $v === null) ? $default : $v;
    };

    $structure = $m($vk('default_comp_structure', '_vms_default_comp_structure'), 'flat_fee');
    $flat_fee  = $m($vk('default_flat_fee_amount', '_vms_default_flat_fee_amount'));
    $supporting_flat_fee = $m($vk('default_supporting_flat_fee_amount', '_vms_default_supporting_flat_fee_amount'));
    $split     = $m($vk('default_door_split_percent', '_vms_default_door_split_percent'));
    $bonus_mode = $m($vk('default_attendance_bonus_mode', '_vms_default_attendance_bonus_mode'));
    $bonus_start = $m($vk('default_attendance_bonus_start_count', '_vms_default_attendance_bonus_start_count'));
    $bonus_step_size = $m($vk('default_attendance_bonus_step_size', '_vms_default_attendance_bonus_step_size'));
    $bonus_step_bonus = $m($vk('default_attendance_bonus_step_bonus', '_vms_default_attendance_bonus_step_bonus'));
    $bonus_per_ticket = $m($vk('default_attendance_bonus_per_ticket_rate', '_vms_default_attendance_bonus_per_ticket_rate'));
    $bonus_max = $m($vk('default_attendance_bonus_max_bonus', '_vms_default_attendance_bonus_max_bonus'));
    $default_comp_package_id = (int) $m($vk('default_comp_package_id', '_vms_default_comp_package_id'), 0);
    $comp_packages = get_posts(array(
        'post_type'      => 'vms_comp_package',
        'post_status'    => array('publish', 'private', 'draft', 'pending'),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ));
    $k_by_venue = $vk('default_comp_by_venue', '_vms_vendor_default_comp_by_venue');
    $by_venue_saved = get_post_meta($post->ID, $k_by_venue, true);
    if (!is_array($by_venue_saved)) {
        $by_venue_saved = array();
    }
    $k_by_venue_dow = $vk('default_comp_by_venue_dow', '_vms_vendor_default_comp_by_venue_dow');
    $by_venue_dow_saved = get_post_meta($post->ID, $k_by_venue_dow, true);
    if (!is_array($by_venue_dow_saved)) {
        $by_venue_dow_saved = array();
    }
    $comm      = $m($vk('default_commission_percent', '_vms_default_commission_percent'));
    $mode      = $m($vk('default_commission_mode', '_vms_default_commission_mode'), 'artist_fee');
    $fee_min   = $m($vk('fee_min', '_vms_fee_min'));
    $fee_max   = $m($vk('fee_max', '_vms_fee_max'));
    $min_rate  = $m($vk('min_show_rate', '_vms_min_show_rate'));
    $venues = get_posts(array(
        'post_type'      => 'vms_venue',
        'post_status'    => array('publish', 'private', 'draft', 'pending'),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ));
    $days = array(
        1 => __('Mon', 'backstage-venue-manager'),
        2 => __('Tue', 'backstage-venue-manager'),
        3 => __('Wed', 'backstage-venue-manager'),
        4 => __('Thu', 'backstage-venue-manager'),
        5 => __('Fri', 'backstage-venue-manager'),
        6 => __('Sat', 'backstage-venue-manager'),
        0 => __('Sun', 'backstage-venue-manager'),
    );
    ?>



    <div class="vms-vendor-defaults-ui">
        <div class="vms-vendor-defaults-intro">
            <div>
                <h4 class="vms-vendor-defaults-intro__title"><?php esc_html_e('Event Plan Pay Defaults', 'backstage-venue-manager'); ?></h4>
                <p class="description vms-vendor-defaults-intro__desc">
                    <?php esc_html_e(
                        'Set the reusable starting point for this vendor. Event Plans follow this order: selected template → vendor defaults below → event-specific edits.',
                        'backstage-venue-manager'
                    ); ?>
                </p>
            </div>
            <div class="vms-vendor-defaults-intro__pills">
                <span class="vms-pill vms-pill-blue"><?php esc_html_e('Template optional', 'backstage-venue-manager'); ?></span>
                <span class="vms-pill vms-pill-green"><?php esc_html_e('Defaults auto-fill Event Plans', 'backstage-venue-manager'); ?></span>
                <span class="vms-pill vms-pill-muted"><?php esc_html_e('Existing plans stay unchanged', 'backstage-venue-manager'); ?></span>
            </div>
        </div>

        <div class="vms-vendor-defaults-top-grid">
            <section class="vms-vendor-defaults-card vms-vendor-defaults-card--template">
                <div class="vms-vendor-defaults-card__header">
                    <h4><?php esc_html_e('Reusable Template', 'backstage-venue-manager'); ?></h4>
                    <p class="description">
                        <?php esc_html_e(
                            'Use a saved Comp Package as this vendor\'s baseline. You can also save the current defaults here as a new editable template.',
                            'backstage-venue-manager'
                        ); ?>
                    </p>
                </div>

                <div class="vms-vendor-defaults-grid vms-vendor-defaults-grid--template">
                    <div class="vms-field vms-field--full">
                        <label for="vms_default_comp_package_id"><?php esc_html_e('Default Template', 'backstage-venue-manager'); ?></label>
                        <select id="vms_default_comp_package_id" name="vms_default_comp_package_id">
                            <option value="0"><?php esc_html_e('No template selected', 'backstage-venue-manager'); ?></option>
                            <?php foreach ($comp_packages as $pkg) :
                                $pkg_id = (int) $pkg->ID;
                                $pkg_terms = function_exists('vms_get_comp_package_terms') ? vms_get_comp_package_terms($pkg_id) : array();
                                $pkg_commission_percent = function_exists('vms_normalize_agent_fee_percent') ? vms_normalize_agent_fee_percent(get_post_meta($pkg_id, '_vms_commission_percent', true)) : null;
                                $pkg_commission_mode = function_exists('vms_normalize_agent_fee_mode') ? vms_normalize_agent_fee_mode(get_post_meta($pkg_id, '_vms_commission_mode', true)) : '';
                                if ($pkg_commission_percent !== null && $pkg_commission_percent > 0) {
                                    $pkg_terms['commission_percent'] = $pkg_commission_percent;
                                    $pkg_terms['commission_mode'] = ($pkg_commission_mode !== '') ? $pkg_commission_mode : 'artist_fee';
                                }
                                $pkg_terms_json = wp_json_encode($pkg_terms);
                                $pkg_venue_id = (int) get_post_meta($pkg_id, '_vms_venue_id', true);
                                $pkg_scope = ($pkg_venue_id > 0) ? get_the_title($pkg_venue_id) : __('Global', 'backstage-venue-manager');
                                $pkg_edit_url = get_edit_post_link($pkg_id, '');
                            ?>
                                <option value="<?php echo esc_attr((string) $pkg_id); ?>"
                                        <?php selected($default_comp_package_id, $pkg_id); ?>
                                        data-terms="<?php echo esc_attr((string) $pkg_terms_json); ?>"
                                        data-edit-url="<?php echo esc_url($pkg_edit_url ?: ''); ?>"
                                        data-scope="<?php echo esc_attr((string) $pkg_scope); ?>">
                                    <?php echo esc_html($pkg->post_title . ' — ' . $pkg_scope); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="vms-help"><?php esc_html_e('This template becomes the starting point for this vendor. The Global Event Plan Defaults below can override it.', 'backstage-venue-manager'); ?></div>
                    </div>

                    <div class="vms-field vms-field--full">
                        <label for="vms_create_comp_template_name"><?php esc_html_e('Create New Template from Current Defaults', 'backstage-venue-manager'); ?></label>
                        <input type="text" id="vms_create_comp_template_name" name="vms_create_comp_template_name" value="" placeholder="<?php echo esc_attr__('Example: Friday Bonus 150/200/250', 'backstage-venue-manager'); ?>">
                        <div class="vms-help"><?php esc_html_e('Enter a name, click Update, and VMS will save the current Global Event Plan Defaults as a new editable template and select it for this vendor.', 'backstage-venue-manager'); ?></div>
                    </div>
                </div>

                <div class="vms-template-actions">
                    <button type="button" class="button" id="vms-load-comp-template-btn"><?php esc_html_e('Copy Template Into Vendor Defaults', 'backstage-venue-manager'); ?></button>
                    <a href="#" class="button button-secondary" id="vms-edit-comp-template-link"><?php esc_html_e('Edit Selected Template', 'backstage-venue-manager'); ?></a>
                </div>

                <div id="vms-comp-template-preview" class="vms-vendor-defaults-preview-card vms-vendor-defaults-preview-card--template"></div>
            </section>

            <details class="vms-vendor-defaults-card vms-vendor-defaults-card--reference vms-vendor-defaults-details">
                <summary>
                    <span class="vms-vendor-defaults-details__title"><?php esc_html_e('Planning-Only Reference Preferences', 'backstage-venue-manager'); ?></span>
                    <span class="vms-vendor-defaults-details__meta"><?php esc_html_e('Not auto-filled into Event Plans', 'backstage-venue-manager'); ?></span>
                </summary>
                <div class="vms-vendor-defaults-details__body">
                    <p class="description">
                        <?php esc_html_e(
                            'Use these as your negotiation guardrails. They stay here for quick reference but do not populate Event Plans.',
                            'backstage-venue-manager'
                        ); ?>
                    </p>

                    <div class="vms-vendor-defaults-grid vms-vendor-defaults-grid--reference">
                        <div class="vms-field">
                            <label for="vms_fee_min"><?php esc_html_e('Preferred Flat Fee Min ($)', 'backstage-venue-manager'); ?></label>
                            <input type="number" step="0.01" min="0"
                                   id="vms_fee_min"
                                   name="vms_fee_min"
                                   value="<?php echo esc_attr($fee_min); ?>">
                        </div>

                        <div class="vms-field">
                            <label for="vms_fee_max"><?php esc_html_e('Preferred Flat Fee Max ($)', 'backstage-venue-manager'); ?></label>
                            <input type="number" step="0.01" min="0"
                                   id="vms_fee_max"
                                   name="vms_fee_max"
                                   value="<?php echo esc_attr($fee_max); ?>">
                        </div>

                        <div class="vms-field">
                            <label for="vms_min_show_rate"><?php esc_html_e('Minimum Acceptable Show Rate ($)', 'backstage-venue-manager'); ?></label>
                            <input type="number" step="0.01" min="0"
                                   id="vms_min_show_rate"
                                   name="vms_min_show_rate"
                                   value="<?php echo esc_attr($min_rate); ?>">
                        </div>
                    </div>
                </div>
            </details>
        </div>

        <section class="vms-vendor-defaults-card vms-vendor-defaults-card--booking">
            <div class="vms-vendor-defaults-card__header">
                <h4><?php esc_html_e('Primary / Headliner Event Plan Defaults', 'backstage-venue-manager'); ?></h4>
                <p class="description">
                    <?php esc_html_e(
                        'These values auto-fill when this vendor is booked as the primary / featured lineup entry. They do not retroactively change older plans.',
                        'backstage-venue-manager'
                    ); ?>
                </p>
            </div>

            <div class="vms-vendor-defaults-grid vms-vendor-defaults-grid--booking">
                <div class="vms-field">
                    <label for="vms_default_comp_structure"><?php esc_html_e('Comp Structure', 'backstage-venue-manager'); ?></label>
                    <select id="vms_default_comp_structure" name="vms_default_comp_structure">
                        <option value="flat_fee" <?php selected($structure, 'flat_fee'); ?>>Flat Fee Only</option>
                        <option value="flat_fee_door_split" <?php selected($structure, 'flat_fee_door_split'); ?>>Flat Fee + Door Split</option>
                        <option value="door_split" <?php selected($structure, 'door_split'); ?>>Door Split Only</option>
                        <option value="attendance_bonus" <?php selected($structure, 'attendance_bonus'); ?>>Base + Attendance Bonus</option>
                    </select>
                </div>

                <div class="vms-field vms-vendor-structure-field" data-show-when-structures="flat_fee,flat_fee_door_split,attendance_bonus">
                    <label for="vms_default_flat_fee_amount"><span id="vms-default-flat-fee-label"><?php echo esc_html(($structure === 'attendance_bonus') ? __('Base Pay ($)', 'backstage-venue-manager') : __('Flat Fee ($)', 'backstage-venue-manager')); ?></span></label>
                    <input type="number" step="0.01" min="0"
                           id="vms_default_flat_fee_amount"
                           name="vms_default_flat_fee_amount"
                           value="<?php echo esc_attr($flat_fee); ?>">
                    <div class="vms-help<?php echo ($structure === 'attendance_bonus') ? '' : ' vms-hidden'; ?>" id="vms-default-flat-fee-help"><?php esc_html_e('This becomes the guaranteed base pay before any attendance bonus is added.', 'backstage-venue-manager'); ?></div>
                </div>

                <div class="vms-field">
                    <label for="vms_default_supporting_flat_fee_amount"><?php esc_html_e('Supporting Act Guaranteed Fee ($)', 'backstage-venue-manager'); ?></label>
                    <input type="number" step="0.01" min="0"
                           id="vms_default_supporting_flat_fee_amount"
                           name="vms_default_supporting_flat_fee_amount"
                           value="<?php echo esc_attr($supporting_flat_fee); ?>">
                    <div class="vms-help"><?php esc_html_e('Auto-fills when this vendor is booked in a supporting lineup slot. Leave blank to require a manual event-level fee.', 'backstage-venue-manager'); ?></div>
                </div>

                <div class="vms-field vms-vendor-structure-field" data-show-when-structures="flat_fee_door_split,door_split">
                    <label for="vms_default_door_split_percent"><?php esc_html_e('Door Split %', 'backstage-venue-manager'); ?></label>
                    <input type="number" step="0.01" min="0" max="100"
                           id="vms_default_door_split_percent"
                           name="vms_default_door_split_percent"
                           value="<?php echo esc_attr($split); ?>">
                </div>

                <div class="vms-field">
                    <label for="vms_default_commission_percent"><?php esc_html_e('Default Agent Fee %', 'backstage-venue-manager'); ?></label>
                    <input type="number" step="0.01" min="0" max="100"
                           id="vms_default_commission_percent"
                           name="vms_default_commission_percent"
                           value="<?php echo esc_attr($comm); ?>">
                    <div class="vms-help"><?php esc_html_e('Leave blank for no default agent fee.', 'backstage-venue-manager'); ?></div>
                </div>

                <div class="vms-field">
                    <label for="vms_default_commission_mode"><?php esc_html_e('Agent Fee Mode', 'backstage-venue-manager'); ?></label>
                    <select id="vms_default_commission_mode" name="vms_default_commission_mode">
                        <option value="artist_fee" <?php selected($mode, 'artist_fee'); ?>>
                            <?php esc_html_e('Added on top of vendor pay', 'backstage-venue-manager'); ?>
                        </option>
                        <option value="gross" <?php selected($mode, 'gross'); ?>>
                            <?php esc_html_e('Based on gross / settlement', 'backstage-venue-manager'); ?>
                        </option>
                    </select>
                </div>
            </div>

            <div class="vms-vendor-defaults-bonus-block<?php echo ($structure === 'attendance_bonus') ? '' : ' vms-hidden'; ?>" id="vms-vendor-defaults-bonus-block">
                <div class="vms-vendor-defaults-bonus-block__header">
                    <h5><?php esc_html_e('Attendance Bonus Setup', 'backstage-venue-manager'); ?></h5>
                    <p class="description"><?php esc_html_e('Use this when pay is based on a guaranteed base plus a bonus tied to attendance.', 'backstage-venue-manager'); ?></p>
                </div>

                <div class="vms-vendor-defaults-grid vms-vendor-defaults-grid--bonus">
                    <div class="vms-field vms-vendor-bonus-field" data-show-when-structure="attendance_bonus">
                        <label for="vms_default_attendance_bonus_mode"><?php esc_html_e('Bonus Style', 'backstage-venue-manager'); ?></label>
                        <select id="vms_default_attendance_bonus_mode" name="vms_default_attendance_bonus_mode">
                            <option value="" <?php selected($bonus_mode, ''); ?>><?php esc_html_e('Select bonus style', 'backstage-venue-manager'); ?></option>
                            <option value="step" <?php selected($bonus_mode, 'step'); ?>><?php esc_html_e('Step', 'backstage-venue-manager'); ?></option>
                            <option value="continuous" <?php selected($bonus_mode, 'continuous'); ?>><?php esc_html_e('Continuous', 'backstage-venue-manager'); ?></option>
                        </select>
                    </div>

                    <div class="vms-field vms-vendor-bonus-field" data-show-when-structure="attendance_bonus">
                        <label for="vms_default_attendance_bonus_start_count"><?php esc_html_e('Starts After Attendance', 'backstage-venue-manager'); ?></label>
                        <input type="number" step="1" min="0"
                               id="vms_default_attendance_bonus_start_count"
                               name="vms_default_attendance_bonus_start_count"
                               value="<?php echo esc_attr($bonus_start); ?>">
                        <div class="vms-help"><?php esc_html_e('No bonus is earned until attendance goes above this number.', 'backstage-venue-manager'); ?></div>
                    </div>

                    <div class="vms-field vms-vendor-bonus-field" data-show-when-structure="attendance_bonus" data-show-when-mode="step">
                        <label for="vms_default_attendance_bonus_step_size"><?php esc_html_e('Step Size', 'backstage-venue-manager'); ?></label>
                        <input type="number" step="1" min="1"
                               id="vms_default_attendance_bonus_step_size"
                               name="vms_default_attendance_bonus_step_size"
                               value="<?php echo esc_attr($bonus_step_size); ?>">
                        <div class="vms-help"><?php esc_html_e('How many additional tickets are needed to earn each bonus step.', 'backstage-venue-manager'); ?></div>
                    </div>

                    <div class="vms-field vms-vendor-bonus-field" data-show-when-structure="attendance_bonus" data-show-when-mode="step">
                        <label for="vms_default_attendance_bonus_step_bonus"><?php esc_html_e('Bonus Per Step', 'backstage-venue-manager'); ?></label>
                        <input type="number" step="0.01" min="0"
                               id="vms_default_attendance_bonus_step_bonus"
                               name="vms_default_attendance_bonus_step_bonus"
                               value="<?php echo esc_attr($bonus_step_bonus); ?>">
                        <div class="vms-help"><?php esc_html_e('The amount added each time a step is reached.', 'backstage-venue-manager'); ?></div>
                    </div>

                    <div class="vms-field vms-vendor-bonus-field" data-show-when-structure="attendance_bonus" data-show-when-mode="continuous">
                        <label for="vms_default_attendance_bonus_per_ticket_rate"><?php esc_html_e('Bonus Per Ticket', 'backstage-venue-manager'); ?></label>
                        <input type="number" step="0.01" min="0"
                               id="vms_default_attendance_bonus_per_ticket_rate"
                               name="vms_default_attendance_bonus_per_ticket_rate"
                               value="<?php echo esc_attr($bonus_per_ticket); ?>">
                        <div class="vms-help"><?php esc_html_e('The amount added for each ticket above the starting count.', 'backstage-venue-manager'); ?></div>
                    </div>

                    <div class="vms-field vms-vendor-bonus-field" data-show-when-structure="attendance_bonus">
                        <label for="vms_default_attendance_bonus_max_bonus"><?php esc_html_e('Bonus Cap ($)', 'backstage-venue-manager'); ?></label>
                        <input type="number" step="0.01" min="0"
                               id="vms_default_attendance_bonus_max_bonus"
                               name="vms_default_attendance_bonus_max_bonus"
                               value="<?php echo esc_attr($bonus_max); ?>">
                        <div class="vms-help"><?php esc_html_e('Optional. Leave blank if the bonus should keep climbing with attendance.', 'backstage-venue-manager'); ?></div>
                    </div>
                </div>
            </div>

            <div class="vms-vendor-defaults-preview-grid">
                <div id="vms-vendor-defaults-summary" class="vms-vendor-defaults-preview-card vms-vendor-defaults-preview-card--summary"></div>
                <div id="vms-vendor-attendance-bonus-preview" class="vms-attendance-preview<?php echo ($structure === 'attendance_bonus') ? '' : ' vms-hidden'; ?> vms-vendor-attendance-preview">
                    <strong class="vms-attendance-preview__title"><?php esc_html_e('Attendance Bonus Preview', 'backstage-venue-manager'); ?></strong>
                    <div id="vms-vendor-attendance-bonus-formula" class="description vms-mt-6"></div>
                    <div id="vms-vendor-attendance-bonus-preview-note" class="description vms-mt-6"></div>
                    <div id="vms-vendor-attendance-bonus-preview-table" class="vms-attendance-preview__table vms-mt-8"></div>
                </div>
            </div>
        </section>

        <details class="vms-vendor-defaults-card vms-vendor-defaults-details">
            <summary>
                <span class="vms-vendor-defaults-details__title"><?php esc_html_e('Per-Venue Event Plan Default Overrides', 'backstage-venue-manager'); ?></span>
                <span class="vms-vendor-defaults-details__meta"><?php esc_html_e('Optional', 'backstage-venue-manager'); ?></span>
            </summary>
            <div class="vms-vendor-defaults-details__body">
                <p class="description">
                    <?php esc_html_e(
                        'Use this only when a venue should override the global defaults above. Blank values fall back to the global defaults.',
                        'backstage-venue-manager'
                    ); ?>
                </p>

                <?php if (!empty($venues)) : ?>
                    <div class="vms-vendor-defaults-table-wrap">
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Venue', 'backstage-venue-manager'); ?></th>
                                    <th><?php esc_html_e('Comp Structure', 'backstage-venue-manager'); ?></th>
                                    <th><?php esc_html_e('Flat Fee ($)', 'backstage-venue-manager'); ?></th>
                                    <th><?php esc_html_e('Door Split %', 'backstage-venue-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($venues as $venue) :
                                    $venue_id = (int) $venue->ID;
                                    $venue_row = array();
                                    if (isset($by_venue_saved[(string) $venue_id]) && is_array($by_venue_saved[(string) $venue_id])) {
                                        $venue_row = $by_venue_saved[(string) $venue_id];
                                    } elseif (isset($by_venue_saved[$venue_id]) && is_array($by_venue_saved[$venue_id])) {
                                        $venue_row = $by_venue_saved[$venue_id];
                                    }

                                    $venue_structure = isset($venue_row['structure']) ? sanitize_key((string) $venue_row['structure']) : '';
                                    if (!in_array($venue_structure, array('flat_fee', 'flat_fee_door_split', 'door_split'), true)) {
                                        $venue_structure = '';
                                    }
                                    $venue_flat = (isset($venue_row['flat_fee_amount']) && $venue_row['flat_fee_amount'] !== '' && $venue_row['flat_fee_amount'] !== null)
                                        ? (string) $venue_row['flat_fee_amount']
                                        : '';
                                    $venue_split = (isset($venue_row['door_split_percent']) && $venue_row['door_split_percent'] !== '' && $venue_row['door_split_percent'] !== null)
                                        ? (string) $venue_row['door_split_percent']
                                        : '';
                                ?>
                                <tr>
                                    <td><?php echo esc_html(get_the_title($venue_id)); ?></td>
                                    <td>
                                        <select name="vms_default_comp_by_venue[<?php echo esc_attr((string) $venue_id); ?>][structure]">
                                            <option value="" <?php selected($venue_structure, ''); ?>><?php esc_html_e('Use global', 'backstage-venue-manager'); ?></option>
                                            <option value="flat_fee" <?php selected($venue_structure, 'flat_fee'); ?>><?php esc_html_e('Flat Fee Only', 'backstage-venue-manager'); ?></option>
                                            <option value="flat_fee_door_split" <?php selected($venue_structure, 'flat_fee_door_split'); ?>><?php esc_html_e('Flat Fee + Door Split', 'backstage-venue-manager'); ?></option>
                                            <option value="door_split" <?php selected($venue_structure, 'door_split'); ?>><?php esc_html_e('Door Split Only', 'backstage-venue-manager'); ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="vms_default_comp_by_venue[<?php echo esc_attr((string) $venue_id); ?>][flat_fee_amount]" value="<?php echo esc_attr($venue_flat); ?>">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="100" name="vms_default_comp_by_venue[<?php echo esc_attr((string) $venue_id); ?>][door_split_percent]" value="<?php echo esc_attr($venue_split); ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <p class="description"><?php esc_html_e('No venues found yet. Create a venue to configure per-venue vendor defaults.', 'backstage-venue-manager'); ?></p>
                <?php endif; ?>
            </div>
        </details>

        <details class="vms-vendor-defaults-card vms-vendor-defaults-details">
            <summary>
                <span class="vms-vendor-defaults-details__title"><?php esc_html_e('Per-Venue + Day-of-Week Vendor Overrides', 'backstage-venue-manager'); ?></span>
                <span class="vms-vendor-defaults-details__meta"><?php esc_html_e('Most specific override layer', 'backstage-venue-manager'); ?></span>
            </summary>
            <div class="vms-vendor-defaults-details__body">
                <p class="description">
                    <?php esc_html_e(
                        'These are the most specific defaults. For each venue/day, blank values fall back to the per-venue row above, then to the global vendor defaults.',
                        'backstage-venue-manager'
                    ); ?>
                </p>

                <?php if (!empty($venues)) : ?>
                    <?php foreach ($venues as $venue) :
                        $venue_id = (int) $venue->ID;
                        $venue_dow_rows = array();
                        if (isset($by_venue_dow_saved[(string) $venue_id]) && is_array($by_venue_dow_saved[(string) $venue_id])) {
                            $venue_dow_rows = $by_venue_dow_saved[(string) $venue_id];
                        } elseif (isset($by_venue_dow_saved[$venue_id]) && is_array($by_venue_dow_saved[$venue_id])) {
                            $venue_dow_rows = $by_venue_dow_saved[$venue_id];
                        }
                    ?>
                    <details class="vms-vendor-dow-venue">
                        <summary><strong><?php echo esc_html(get_the_title($venue_id)); ?></strong></summary>
                        <div class="vms-vendor-defaults-table-wrap">
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Day', 'backstage-venue-manager'); ?></th>
                                        <th><?php esc_html_e('Comp Structure', 'backstage-venue-manager'); ?></th>
                                        <th><?php esc_html_e('Flat Fee ($)', 'backstage-venue-manager'); ?></th>
                                        <th><?php esc_html_e('Door Split %', 'backstage-venue-manager'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($days as $dow => $label) :
                                        $day_row = array();
                                        if (isset($venue_dow_rows[(string) $dow]) && is_array($venue_dow_rows[(string) $dow])) {
                                            $day_row = $venue_dow_rows[(string) $dow];
                                        } elseif (isset($venue_dow_rows[$dow]) && is_array($venue_dow_rows[$dow])) {
                                            $day_row = $venue_dow_rows[$dow];
                                        }

                                        $day_structure = isset($day_row['structure']) ? sanitize_key((string) $day_row['structure']) : '';
                                        if (!in_array($day_structure, array('flat_fee', 'flat_fee_door_split', 'door_split'), true)) {
                                            $day_structure = '';
                                        }
                                        $day_flat = (isset($day_row['flat_fee_amount']) && $day_row['flat_fee_amount'] !== '' && $day_row['flat_fee_amount'] !== null)
                                            ? (string) $day_row['flat_fee_amount']
                                            : '';
                                        $day_split = (isset($day_row['door_split_percent']) && $day_row['door_split_percent'] !== '' && $day_row['door_split_percent'] !== null)
                                            ? (string) $day_row['door_split_percent']
                                            : '';
                                        $name = 'vms_default_comp_by_venue_dow[' . (int) $venue_id . '][' . (int) $dow . ']';
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($label); ?></td>
                                        <td>
                                            <select name="<?php echo esc_attr($name . '[structure]'); ?>">
                                                <option value="" <?php selected($day_structure, ''); ?>><?php esc_html_e('Use venue/global', 'backstage-venue-manager'); ?></option>
                                                <option value="flat_fee" <?php selected($day_structure, 'flat_fee'); ?>><?php esc_html_e('Flat Fee Only', 'backstage-venue-manager'); ?></option>
                                                <option value="flat_fee_door_split" <?php selected($day_structure, 'flat_fee_door_split'); ?>><?php esc_html_e('Flat Fee + Door Split', 'backstage-venue-manager'); ?></option>
                                                <option value="door_split" <?php selected($day_structure, 'door_split'); ?>><?php esc_html_e('Door Split Only', 'backstage-venue-manager'); ?></option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" name="<?php echo esc_attr($name . '[flat_fee_amount]'); ?>" value="<?php echo esc_attr($day_flat); ?>">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="100" name="<?php echo esc_attr($name . '[door_split_percent]'); ?>" value="<?php echo esc_attr($day_split); ?>">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="description"><?php esc_html_e('No venues found yet. Create a venue to configure day-of-week vendor overrides.', 'backstage-venue-manager'); ?></p>
                <?php endif; ?>
            </div>
        </details>
    </div>

    <script>
    (function() {
        const structure = document.getElementById('vms_default_comp_structure');
        const bonusMode = document.getElementById('vms_default_attendance_bonus_mode');
        const flatField = document.getElementById('vms_default_flat_fee_amount');
        const supportingFlatField = document.getElementById('vms_default_supporting_flat_fee_amount');
        const splitField = document.getElementById('vms_default_door_split_percent');
        const commissionPercentField = document.getElementById('vms_default_commission_percent');
        const commissionModeField = document.getElementById('vms_default_commission_mode');
        const templateSelect = document.getElementById('vms_default_comp_package_id');
        const loadTemplateBtn = document.getElementById('vms-load-comp-template-btn');
        const editTemplateLink = document.getElementById('vms-edit-comp-template-link');
        const templatePreview = document.getElementById('vms-comp-template-preview');
        const summaryCard = document.getElementById('vms-vendor-defaults-summary');
        const flatLabel = document.getElementById('vms-default-flat-fee-label');
        const flatHelp = document.getElementById('vms-default-flat-fee-help');
        const bonusBlock = document.getElementById('vms-vendor-defaults-bonus-block');
        const previewWrap = document.getElementById('vms-vendor-attendance-bonus-preview');
        const previewFormula = document.getElementById('vms-vendor-attendance-bonus-formula');
        const previewNote = document.getElementById('vms-vendor-attendance-bonus-preview-note');
        const previewTable = document.getElementById('vms-vendor-attendance-bonus-preview-table');
        if (!structure) return;

        const getStructureLabel = (value) => {
            switch (String(value || '').trim()) {
                case 'flat_fee_door_split': return 'Flat Fee + Door Split';
                case 'door_split': return 'Door Split Only';
                case 'attendance_bonus': return 'Base + Attendance Bonus';
                case 'flat_fee':
                default: return 'Flat Fee Only';
            }
        };

        const formatMoney = (value) => {
            const num = Number(value || 0);
            return '$' + num.toFixed(2);
        };

        const escapeHtml = (value) => String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const nonNegativeMoney = (value) => {
            const raw = String(value === undefined || value === null ? '' : value).trim();
            if (raw === '') return null;
            const parsed = Number(raw);
            if (!Number.isFinite(parsed)) return null;
            return Math.max(0, parsed);
        };

        const nonNegativeInt = (value) => {
            const raw = String(value === undefined || value === null ? '' : value).trim();
            if (raw === '') return null;
            const parsed = Number(raw);
            if (!Number.isFinite(parsed)) return null;
            return Math.max(0, Math.floor(parsed));
        };

        const getCurrentStructure = () => String(structure.value || '').trim();
        const getCurrentBonusMode = () => bonusMode ? String(bonusMode.value || '').trim() : '';

        const attendanceState = () => ({
            mode: getCurrentBonusMode(),
            start: nonNegativeInt(document.getElementById('vms_default_attendance_bonus_start_count') ? document.getElementById('vms_default_attendance_bonus_start_count').value : ''),
            stepSize: nonNegativeInt(document.getElementById('vms_default_attendance_bonus_step_size') ? document.getElementById('vms_default_attendance_bonus_step_size').value : ''),
            stepBonus: nonNegativeMoney(document.getElementById('vms_default_attendance_bonus_step_bonus') ? document.getElementById('vms_default_attendance_bonus_step_bonus').value : ''),
            perTicketRate: nonNegativeMoney(document.getElementById('vms_default_attendance_bonus_per_ticket_rate') ? document.getElementById('vms_default_attendance_bonus_per_ticket_rate').value : ''),
            maxBonus: nonNegativeMoney(document.getElementById('vms_default_attendance_bonus_max_bonus') ? document.getElementById('vms_default_attendance_bonus_max_bonus').value : ''),
        });

        const attendanceCapInfo = (state) => {
            if (state.maxBonus === null || state.start === null) return null;
            if (state.mode === 'step' && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null && state.stepBonus > 0) {
                const stepsToCap = Math.max(0, Math.ceil(state.maxBonus / state.stepBonus));
                return { count: state.start + (stepsToCap * state.stepSize), steps: stepsToCap };
            }
            if (state.mode === 'continuous' && state.perTicketRate !== null && state.perTicketRate > 0) {
                const ticketsToCap = Math.max(0, Math.ceil(state.maxBonus / state.perTicketRate));
                return { count: state.start + ticketsToCap, tickets: ticketsToCap };
            }
            return null;
        };

        const buildAttendancePreviewCounts = (state) => {
            const counts = [];
            const pushCount = (value) => {
                const safe = Math.max(0, Math.floor(Number(value || 0)));
                if (!counts.includes(safe)) counts.push(safe);
            };
            const start = state.start ?? 0;
            const capInfo = attendanceCapInfo(state);

            if (state.mode === 'step') {
                const stepSize = state.stepSize ?? 0;
                pushCount(start);
                if (capInfo && Number.isFinite(Number(capInfo.steps))) {
                    const exactSteps = Math.max(0, Number(capInfo.steps || 0));
                    if (exactSteps <= 10) {
                        for (let stepIndex = 1; stepIndex <= exactSteps; stepIndex += 1) {
                            pushCount(start + (stepIndex * stepSize));
                        }
                    } else {
                        [1, 2, 3, 5, Math.ceil(exactSteps / 2), exactSteps - 1, exactSteps].forEach((stepIndex) => {
                            if (stepIndex > 0) pushCount(start + (stepIndex * stepSize));
                        });
                    }
                } else {
                    [1, 2, 3, 5, 8].forEach((stepIndex) => pushCount(start + (stepIndex * stepSize)));
                }
            } else {
                pushCount(start);
                if (capInfo && Number.isFinite(Number(capInfo.tickets))) {
                    const exactTickets = Math.max(0, Number(capInfo.tickets || 0));
                    if (exactTickets <= 10) {
                        for (let ticketIndex = 1; ticketIndex <= exactTickets; ticketIndex += 1) {
                            pushCount(start + ticketIndex);
                        }
                    } else {
                        [1, 5, 10, Math.ceil(exactTickets * 0.25), Math.ceil(exactTickets * 0.5), Math.ceil(exactTickets * 0.75), exactTickets].forEach((ticketIndex) => {
                            if (ticketIndex > 0) pushCount(start + ticketIndex);
                        });
                    }
                } else {
                    [1, 5, 10, 25, 50].forEach((ticketIndex) => pushCount(start + ticketIndex));
                }
            }

            counts.sort((a, b) => a - b);
            return counts.slice(0, 8);
        };

        const calculateAttendancePreviewPayout = (base, state, attendanceCount) => {
            const safeAttendance = Math.max(0, Math.floor(Number(attendanceCount || 0)));
            const safeBase = Math.max(0, Number(base || 0));
            let bonus = 0;

            if (state.mode === 'step' && state.start !== null && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null) {
                const stepsReached = Math.floor(Math.max(0, safeAttendance - state.start) / state.stepSize);
                bonus = stepsReached * state.stepBonus;
            } else if (state.mode === 'continuous' && state.start !== null && state.perTicketRate !== null) {
                bonus = Math.max(0, safeAttendance - state.start) * state.perTicketRate;
            }

            bonus = Math.max(0, Number(bonus || 0));
            if (state.maxBonus !== null) {
                bonus = Math.min(state.maxBonus, bonus);
            }

            return {
                base: safeBase,
                bonus: bonus,
                payout: safeBase + bonus,
            };
        };

        const getSelectedTemplateTerms = () => {
            if (!templateSelect) return null;
            const option = templateSelect.options[templateSelect.selectedIndex] || null;
            if (!option || !option.value || option.value === '0') return null;
            try {
                return JSON.parse(option.getAttribute('data-terms') || '{}');
            } catch (err) {
                return null;
            }
        };

        const summaryChip = (label, value, tone = '') => {
            const toneClass = tone ? ' ' + tone : '';
            return '<span class="vms-vendor-defaults-chip' + toneClass + '"><strong>' + escapeHtml(label) + ':</strong> ' + escapeHtml(value) + '</span>';
        };

        const renderTemplateUI = () => {
            if (!templateSelect || !templatePreview) return;
            const option = templateSelect.options[templateSelect.selectedIndex] || null;
            const hasSelection = !!(option && option.value && option.value !== '0');

            if (editTemplateLink) {
                const href = hasSelection ? String(option.getAttribute('data-edit-url') || '').trim() : '';
                editTemplateLink.style.display = hasSelection ? '' : 'none';
                editTemplateLink.setAttribute('href', href || '#');
            }

            if (!hasSelection) {
                templatePreview.innerHTML = '<div class="vms-vendor-defaults-preview-card__title">No template selected</div><p class="description">This vendor will rely only on the Global Event Plan Defaults below.</p>';
                return;
            }

            const scope = String(option.getAttribute('data-scope') || '').trim();
            const terms = getSelectedTemplateTerms() || {};
            const chips = [];
            chips.push(summaryChip('Structure', getStructureLabel(terms.structure || 'flat_fee'), 'vms-vendor-defaults-chip--blue'));
            if (terms.flat_fee_amount !== undefined && terms.flat_fee_amount !== null && terms.flat_fee_amount !== '') {
                chips.push(summaryChip('Base', formatMoney(terms.flat_fee_amount)));
            }
            if (terms.door_split_percent !== undefined && terms.door_split_percent !== null && terms.door_split_percent !== '') {
                chips.push(summaryChip('Door split', String(terms.door_split_percent) + '%'));
            }
            if (terms.commission_percent !== undefined && terms.commission_percent !== null && terms.commission_percent !== '') {
                const feeMode = (String(terms.commission_mode || '') === 'gross') ? 'gross-based' : 'on top';
                chips.push(summaryChip('Agent fee', String(terms.commission_percent) + '% (' + feeMode + ')'));
            }

            let bonusLine = '';
            if (String(terms.structure || '') === 'attendance_bonus') {
                const modeLabel = (String(terms.attendance_bonus_mode || '') === 'continuous') ? 'continuous' : 'step';
                bonusLine = '<p class="description vms-vendor-defaults-preview-card__note">Attendance bonus: ' + modeLabel;
                if (terms.attendance_bonus_start_count !== undefined && terms.attendance_bonus_start_count !== null && terms.attendance_bonus_start_count !== '') {
                    bonusLine += ' • starts after ' + terms.attendance_bonus_start_count;
                }
                if (terms.attendance_bonus_mode === 'step' && terms.attendance_bonus_step_size !== undefined && terms.attendance_bonus_step_size !== null && terms.attendance_bonus_step_size !== '' && terms.attendance_bonus_step_bonus !== undefined && terms.attendance_bonus_step_bonus !== null && terms.attendance_bonus_step_bonus !== '') {
                    bonusLine += ' • +' + formatMoney(terms.attendance_bonus_step_bonus) + ' every ' + terms.attendance_bonus_step_size;
                }
                if (terms.attendance_bonus_mode === 'continuous' && terms.attendance_bonus_per_ticket_rate !== undefined && terms.attendance_bonus_per_ticket_rate !== null && terms.attendance_bonus_per_ticket_rate !== '') {
                    bonusLine += ' • +' + formatMoney(terms.attendance_bonus_per_ticket_rate) + ' per ticket';
                }
                if (terms.attendance_bonus_max_bonus !== undefined && terms.attendance_bonus_max_bonus !== null && terms.attendance_bonus_max_bonus !== '') {
                    bonusLine += ' • cap ' + formatMoney(terms.attendance_bonus_max_bonus);
                }
                bonusLine += '</p>';
            }

            templatePreview.innerHTML = [
                '<div class="vms-vendor-defaults-preview-card__title">Selected Template</div>',
                '<div class="vms-vendor-defaults-preview-card__subtitle">' + escapeHtml(option.text) + '</div>',
                scope ? '<p class="description vms-vendor-defaults-preview-card__scope">Scope: ' + escapeHtml(scope) + '</p>' : '',
                '<div class="vms-vendor-defaults-chip-row">' + chips.join('') + '</div>',
                bonusLine,
                '<p class="description vms-vendor-defaults-preview-card__note">Event Plans start here, then the defaults below can customize this vendor further.</p>'
            ].join('');
        };

        const renderCurrentDefaultsSummary = () => {
            if (!summaryCard) return;

            const currentStructure = getCurrentStructure();
            const flat = nonNegativeMoney(flatField ? flatField.value : '');
            const supportingFlat = nonNegativeMoney(supportingFlatField ? supportingFlatField.value : '');
            const split = nonNegativeMoney(splitField ? splitField.value : '');
            const comm = nonNegativeMoney(commissionPercentField ? commissionPercentField.value : '');
            const commMode = String(commissionModeField ? commissionModeField.value : 'artist_fee');
            const chips = [summaryChip('Structure', getStructureLabel(currentStructure), 'vms-vendor-defaults-chip--blue')];
            const notes = [];

            if (currentStructure !== 'door_split' && flat !== null) {
                chips.push(summaryChip(currentStructure === 'attendance_bonus' ? 'Base pay' : 'Flat fee', formatMoney(flat)));
            }
            if ((currentStructure === 'flat_fee_door_split' || currentStructure === 'door_split') && split !== null) {
                chips.push(summaryChip('Door split', String(split) + '%'));
            }
            if (supportingFlat !== null) {
                chips.push(summaryChip('Supporting act', formatMoney(supportingFlat), 'vms-vendor-defaults-chip--green'));
            } else {
                notes.push('No supporting-act default fee is set yet.');
            }
            if (comm !== null && comm > 0) {
                chips.push(summaryChip('Agent fee', String(comm) + '%', 'vms-vendor-defaults-chip--amber'));
                notes.push((commMode === 'gross') ? 'Agent fee is calculated from gross / settlement.' : 'Agent fee is added on top of vendor pay.');
            } else {
                notes.push('No default agent fee is set.');
            }

            if (currentStructure === 'attendance_bonus') {
                const state = attendanceState();
                if (flat !== null && state.maxBonus !== null) {
                    notes.unshift('Potential max payout: ' + formatMoney(flat + state.maxBonus) + '.');
                } else if (flat !== null) {
                    notes.unshift('No bonus cap is set, so payout can keep climbing above ' + formatMoney(flat) + '.');
                }
            }

            summaryCard.innerHTML = [
                '<div class="vms-vendor-defaults-preview-card__title">What Event Plans Get by Default</div>',
                '<div class="vms-vendor-defaults-chip-row">' + chips.join('') + '</div>',
                '<p class="description vms-vendor-defaults-preview-card__note">' + notes.join(' ') + '</p>'
            ].join('');
        };

        const renderAttendancePreview = () => {
            if (!previewWrap || !previewFormula || !previewTable || !previewNote) return;

            const currentStructure = getCurrentStructure();
            const base = nonNegativeMoney(flatField ? flatField.value : '');
            const state = attendanceState();
            const isAttendance = (currentStructure === 'attendance_bonus');

            previewWrap.classList.toggle('vms-hidden', !isAttendance);
            if (!bonusBlock) {
                return;
            }
            bonusBlock.classList.toggle('vms-hidden', !isAttendance);
            if (!isAttendance) {
                previewFormula.textContent = '';
                previewNote.textContent = '';
                previewTable.innerHTML = '';
                return;
            }

            const isStepValid = (base !== null && state.mode === 'step' && state.start !== null && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null);
            const isContinuousValid = (base !== null && state.mode === 'continuous' && state.start !== null && state.perTicketRate !== null);

            if (!isStepValid && !isContinuousValid) {
                let msg = 'Complete Base Pay, Bonus Style, and the attendance bonus fields to preview payouts.';
                if (state.mode === 'step' && state.stepSize !== null && state.stepSize < 1) {
                    msg = 'Step Size must be at least 1 for step-mode attendance bonuses.';
                }
                previewFormula.textContent = msg;
                previewNote.textContent = '';
                previewTable.innerHTML = '';
                return;
            }

            const capInfo = attendanceCapInfo(state);
            const counts = buildAttendancePreviewCounts(state);
            if (state.mode === 'step') {
                const parts = [
                    'Base pay ' + formatMoney(base) + '.',
                    'No bonus is earned through ' + state.start + ' attendance.',
                    'Add ' + formatMoney(state.stepBonus) + ' every ' + state.stepSize + ' tickets after that.'
                ];
                if (state.maxBonus !== null) {
                    parts.push(capInfo && capInfo.count !== null
                        ? 'Total bonus caps at ' + formatMoney(state.maxBonus) + ' once attendance reaches ' + capInfo.count + '.'
                        : 'Total bonus caps at ' + formatMoney(state.maxBonus) + '.');
                }
                previewFormula.textContent = parts.join(' ');
            } else {
                const parts = [
                    'Base pay ' + formatMoney(base) + '.',
                    'No bonus is earned through ' + state.start + ' attendance.',
                    'Add ' + formatMoney(state.perTicketRate) + ' per ticket after that.'
                ];
                if (state.maxBonus !== null) {
                    parts.push(capInfo && capInfo.count !== null
                        ? 'Total bonus caps at ' + formatMoney(state.maxBonus) + ' once attendance reaches ' + capInfo.count + '.'
                        : 'Total bonus caps at ' + formatMoney(state.maxBonus) + '.');
                }
                previewFormula.textContent = parts.join(' ');
            }

            if (state.maxBonus !== null) {
                previewNote.textContent = 'Potential max payout: ' + formatMoney(base + state.maxBonus) + '.';
            } else {
                previewNote.textContent = 'No bonus cap is set. Payout will continue to rise beyond the preview rows.';
            }

            const rows = counts.map((count) => {
                const payout = calculateAttendancePreviewPayout(base, state, count);
                return '<tr><td>' + count + '</td><td>' + formatMoney(payout.bonus) + '</td><td>' + formatMoney(payout.payout) + '</td></tr>';
            }).join('');

            previewTable.innerHTML = '<table class="widefat striped"><thead><tr><th>Attendance</th><th>Bonus</th><th>Total Pay</th></tr></thead><tbody>' + rows + '</tbody></table>';
        };

        const refreshFieldVisibility = () => {
            const currentStructure = getCurrentStructure();
            const currentMode = getCurrentBonusMode();

            document.querySelectorAll('.vms-vendor-bonus-field').forEach((el) => {
                const needStructure = String(el.getAttribute('data-show-when-structure') || '').trim();
                const needMode = String(el.getAttribute('data-show-when-mode') || '').trim();
                const showStructure = (needStructure === '' || needStructure === currentStructure);
                const showMode = (needMode === '' || needMode === currentMode);
                el.style.display = (showStructure && showMode) ? '' : 'none';
            });

            document.querySelectorAll('.vms-vendor-structure-field').forEach((el) => {
                const allowedStructures = String(el.getAttribute('data-show-when-structures') || '').split(',').map((value) => value.trim()).filter(Boolean);
                el.classList.toggle('vms-hidden', allowedStructures.length ? !allowedStructures.includes(currentStructure) : false);
            });

            if (flatLabel) {
                flatLabel.textContent = (currentStructure === 'attendance_bonus') ? 'Base Pay ($)' : 'Flat Fee ($)';
            }
            if (flatHelp) {
                flatHelp.classList.toggle('vms-hidden', currentStructure !== 'attendance_bonus');
            }
        };

        const refresh = () => {
            refreshFieldVisibility();
            renderTemplateUI();
            renderCurrentDefaultsSummary();
            renderAttendancePreview();
        };

        if (templateSelect) {
            templateSelect.addEventListener('change', refresh);
        }

        if (loadTemplateBtn) {
            loadTemplateBtn.addEventListener('click', function() {
                const terms = getSelectedTemplateTerms();
                if (!terms) return;
                const setValue = (id, value) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.value = (value === undefined || value === null) ? '' : String(value);
                };
                setValue('vms_default_comp_structure', terms.structure || 'flat_fee');
                setValue('vms_default_flat_fee_amount', terms.flat_fee_amount);
                setValue('vms_default_door_split_percent', terms.door_split_percent);
                setValue('vms_default_commission_percent', terms.commission_percent);
                setValue('vms_default_commission_mode', (String(terms.commission_mode || '') === 'gross') ? 'gross' : 'artist_fee');
                setValue('vms_default_attendance_bonus_mode', terms.attendance_bonus_mode);
                setValue('vms_default_attendance_bonus_start_count', terms.attendance_bonus_start_count);
                setValue('vms_default_attendance_bonus_step_size', terms.attendance_bonus_step_size);
                setValue('vms_default_attendance_bonus_step_bonus', terms.attendance_bonus_step_bonus);
                setValue('vms_default_attendance_bonus_per_ticket_rate', terms.attendance_bonus_per_ticket_rate);
                setValue('vms_default_attendance_bonus_max_bonus', terms.attendance_bonus_max_bonus);
                refresh();
            });
        }

        [
            structure,
            bonusMode,
            flatField,
            splitField,
            commissionPercentField,
            commissionModeField,
            document.getElementById('vms_default_attendance_bonus_start_count'),
            document.getElementById('vms_default_attendance_bonus_step_size'),
            document.getElementById('vms_default_attendance_bonus_step_bonus'),
            document.getElementById('vms_default_attendance_bonus_per_ticket_rate'),
            document.getElementById('vms_default_attendance_bonus_max_bonus')
        ].filter(Boolean).forEach((el) => {
            el.addEventListener('change', refresh);
            el.addEventListener('input', refresh);
        });

        refresh();
    })();
    </script>

    <?php
}
