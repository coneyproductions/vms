<?php
if (!defined('ABSPATH')) exit;

/**
 * Parent menu slug used by VMS (your existing top-level menu).
 * If this ever changes, update this constant in ONE place.
 */
if (!defined('BVMGR_ADMIN_PARENT_SLUG')) {
    define('BVMGR_ADMIN_PARENT_SLUG', 'vms-season-board');
}

/**
 * Post type slug for vendor applications.
 */
if (!defined('BVMGR_VENDOR_APP_CPT')) {
    define('BVMGR_VENDOR_APP_CPT', 'vms_vendor_app');
}

/**
 * Vendor CPT slug (must match your vendors system).
 */
if (!defined('BVMGR_VENDOR_CPT')) {
    define('BVMGR_VENDOR_CPT', 'vms_vendor');
}

if (!defined('BVMGR_VENUE_CPT')) {
    define('BVMGR_VENUE_CPT', 'vms_venue');
}

add_action('init', 'bvmgr_register_venue_cpt');

function bvmgr_register_venue_cpt()
{

    $labels = array(
        'name'               => __('Venues', 'backstage-venue-manager'),
        'singular_name'      => __('Venue', 'backstage-venue-manager'),
        'menu_name'          => __('Venues', 'backstage-venue-manager'),
        'add_new'            => __('Add New', 'backstage-venue-manager'),
        'add_new_item'       => __('Add New Venue', 'backstage-venue-manager'),
        'edit_item'          => __('Edit Venue', 'backstage-venue-manager'),
        'new_item'           => __('New Venue', 'backstage-venue-manager'),
        'view_item'          => __('View Venue', 'backstage-venue-manager'),
        'search_items'       => __('Search Venues', 'backstage-venue-manager'),
        'not_found'          => __('No venues found', 'backstage-venue-manager'),
        'not_found_in_trash' => __('No venues found in Trash', 'backstage-venue-manager'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => false,
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-location-alt',
        'supports'           => array('title', 'editor', 'thumbnail'),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'rewrite'            => false,
    );

    register_post_type('vms_venue', $args);
}


if (!function_exists('bvmgr_venue_meta_key')) {
    function bvmgr_venue_meta_key(string $field, string $fallback = ''): string
    {
        if (function_exists('bvmgr_meta_key')) {
            $key = (string) bvmgr_meta_key('venue', $field);
            if ($key !== '') {
                return $key;
            }
        }

        return $fallback;
    }
}

if (!function_exists('bvmgr_get_venue_location_data')) {
    /**
     * Return normalized venue location data used by Meta Ads, social tools, and TEC venue sync.
     *
     * @return array<string,string>
     */
    function bvmgr_get_venue_location_data(int $venue_id): array
    {
        $venue_id = absint($venue_id);
        if ($venue_id <= 0) {
            return array(
                'address' => '',
                'address_2' => '',
                'city' => '',
                'state' => '',
                'zip' => '',
                'country' => '',
                'latitude' => '',
                'longitude' => '',
            );
        }

        $data = array(
            'address' => trim((string) get_post_meta($venue_id, bvmgr_venue_meta_key('address', '_vms_address'), true)),
            'address_2' => trim((string) get_post_meta($venue_id, bvmgr_venue_meta_key('address_2', '_vms_address_2'), true)),
            'city' => trim((string) get_post_meta($venue_id, bvmgr_venue_meta_key('city', '_vms_city'), true)),
            'state' => strtoupper(trim((string) get_post_meta($venue_id, bvmgr_venue_meta_key('state', '_vms_state'), true))),
            'zip' => trim((string) get_post_meta($venue_id, bvmgr_venue_meta_key('zip', '_vms_zip'), true)),
            'country' => strtoupper(trim((string) get_post_meta($venue_id, bvmgr_venue_meta_key('country', '_vms_country'), true))),
            'latitude' => trim((string) get_post_meta($venue_id, bvmgr_venue_meta_key('latitude', '_vms_latitude'), true)),
            'longitude' => trim((string) get_post_meta($venue_id, bvmgr_venue_meta_key('longitude', '_vms_longitude'), true)),
        );

        if ($data['country'] === '') {
            $data['country'] = 'US';
        }

        return $data;
    }
}

if (!function_exists('bvmgr_venue_submitted_nonce')) {
    function bvmgr_venue_submitted_nonce(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading the submitted venue nonce is required before local verification.
        return bvmgr_request_read_text_field($_POST, $key);
    }
}

if (!function_exists('bvmgr_venue_notice_request_post_id')) {
    function bvmgr_venue_notice_request_post_id(): int
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only venue notice state only affects admin feedback.
        return bvmgr_request_read_absint($_GET, 'post');
    }
}

if (!function_exists('bvmgr_sync_tec_venue_from_vms_venue')) {
    function bvmgr_sync_tec_venue_from_vms_venue(int $vms_venue_id, int $tec_venue_id = 0): int
    {
        $vms_venue_id = absint($vms_venue_id);
        $tec_venue_id = absint($tec_venue_id);

        if ($vms_venue_id <= 0) {
            return 0;
        }

        if ($tec_venue_id <= 0) {
            $tec_venue_id = (int) get_post_meta($vms_venue_id, bvmgr_venue_meta_key('tec_venue_id', '_vms_tec_venue_id'), true);
        }

        if ($tec_venue_id <= 0 || !get_post_status($tec_venue_id)) {
            return 0;
        }

        $venue_name = trim((string) get_the_title($vms_venue_id));
        if ($venue_name !== '') {
            wp_update_post(array(
                'ID' => $tec_venue_id,
                'post_title' => $venue_name,
            ));
        }

        $location = bvmgr_get_venue_location_data($vms_venue_id);
        $meta_map = array(
            '_VenueAddress' => (string) ($location['address'] ?? ''),
            '_VenueCity' => (string) ($location['city'] ?? ''),
            '_VenueStateProvince' => (string) ($location['state'] ?? ''),
            '_VenueState' => (string) ($location['state'] ?? ''),
            '_VenueZip' => (string) ($location['zip'] ?? ''),
            '_VenueCountry' => (string) ($location['country'] ?? ''),
            '_VenueLat' => (string) ($location['latitude'] ?? ''),
            '_VenueLng' => (string) ($location['longitude'] ?? ''),
        );

        foreach ($meta_map as $meta_key => $meta_value) {
            $meta_value = trim((string) $meta_value);
            if ($meta_value === '') {
                delete_post_meta($tec_venue_id, $meta_key);
            } else {
                update_post_meta($tec_venue_id, $meta_key, $meta_value);
            }
        }

        return $tec_venue_id;
    }
}

/**
 * Venue Location
 * - Physical address fields for Meta Ads targeting, social/location context, and TEC sync.
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_venue_location',
        __('Venue Location', 'backstage-venue-manager'),
        'bvmgr_render_venue_location_box',
        'vms_venue',
        'normal',
        'high'
    );
});

function bvmgr_render_venue_location_box($post)
{
    wp_nonce_field('bvmgr_save_venue_location', 'bvmgr_venue_location_nonce');

    $location = function_exists('bvmgr_get_venue_location_data')
        ? bvmgr_get_venue_location_data((int) $post->ID)
        : array(
            'address' => '',
            'address_2' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => 'US',
            'latitude' => '',
            'longitude' => '',
        );

    echo '<div class="vms-venue-location-admin">';
    echo '<p class="description vms-venue-location-admin__intro">' .
        esc_html__('Store the real physical venue address here. Meta Ads uses this for radius targeting, and Backstage Venue Manager can also sync it to the linked TEC venue record.', 'backstage-venue-manager') .
    '</p>';

    echo '<div class="vms-venue-location-admin__grid">';

    echo '<p class="vms-venue-location-admin__field vms-venue-location-admin__field--full">';
    echo '<label for="vms_venue_address"><strong>' . esc_html__('Address Line 1', 'backstage-venue-manager') . '</strong></label>';
    echo '<input type="text" id="vms_venue_address" name="vms_venue_address" value="' . esc_attr((string) ($location['address'] ?? '')) . '" class="widefat" placeholder="4021 S State Hwy 161">';
    echo '</p>';

    echo '<p class="vms-venue-location-admin__field vms-venue-location-admin__field--full">';
    echo '<label for="vms_venue_address_2"><strong>' . esc_html__('Address Line 2 (optional)', 'backstage-venue-manager') . '</strong></label>';
    echo '<input type="text" id="vms_venue_address_2" name="vms_venue_address_2" value="' . esc_attr((string) ($location['address_2'] ?? '')) . '" class="widefat" placeholder="Suite, gate, entrance note, etc.">';
    echo '</p>';

    echo '<p class="vms-venue-location-admin__field">';
    echo '<label for="vms_venue_city"><strong>' . esc_html__('City', 'backstage-venue-manager') . '</strong></label>';
    echo '<input type="text" id="vms_venue_city" name="vms_venue_city" value="' . esc_attr((string) ($location['city'] ?? '')) . '" class="widefat" placeholder="Tyler">';
    echo '</p>';

    echo '<p class="vms-venue-location-admin__field">';
    echo '<label for="vms_venue_state"><strong>' . esc_html__('State', 'backstage-venue-manager') . '</strong></label>';
    echo '<input type="text" id="vms_venue_state" name="vms_venue_state" value="' . esc_attr((string) ($location['state'] ?? '')) . '" class="widefat" maxlength="2" placeholder="TX">';
    echo '</p>';

    echo '<p class="vms-venue-location-admin__field">';
    echo '<label for="vms_venue_zip"><strong>' . esc_html__('ZIP', 'backstage-venue-manager') . '</strong></label>';
    echo '<input type="text" id="vms_venue_zip" name="vms_venue_zip" value="' . esc_attr((string) ($location['zip'] ?? '')) . '" class="widefat" placeholder="75701">';
    echo '</p>';

    echo '<p class="vms-venue-location-admin__field">';
    echo '<label for="vms_venue_country"><strong>' . esc_html__('Country', 'backstage-venue-manager') . '</strong></label>';
    echo '<input type="text" id="vms_venue_country" name="vms_venue_country" value="' . esc_attr((string) ($location['country'] ?? 'US')) . '" class="widefat" maxlength="2" placeholder="US">';
    echo '</p>';

    echo '<p class="vms-venue-location-admin__field">';
    echo '<label for="vms_venue_latitude"><strong>' . esc_html__('Latitude (optional)', 'backstage-venue-manager') . '</strong></label>';
    echo '<input type="text" id="vms_venue_latitude" name="vms_venue_latitude" value="' . esc_attr((string) ($location['latitude'] ?? '')) . '" class="widefat" placeholder="32.123456">';
    echo '</p>';

    echo '<p class="vms-venue-location-admin__field">';
    echo '<label for="vms_venue_longitude"><strong>' . esc_html__('Longitude (optional)', 'backstage-venue-manager') . '</strong></label>';
    echo '<input type="text" id="vms_venue_longitude" name="vms_venue_longitude" value="' . esc_attr((string) ($location['longitude'] ?? '')) . '" class="widefat" placeholder="-95.123456">';
    echo '</p>';

    echo '</div>';

    echo '<p class="description vms-venue-location-admin__tip">' .
        esc_html__('Use a real street address whenever possible. City/state alone is too weak for reliable Meta radius targeting.', 'backstage-venue-manager') .
    '</p>';
    echo '</div>';
}

add_action('save_post_vms_venue', function ($post_id, $post) {
    if ($post->post_type !== 'vms_venue') return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $nonce = bvmgr_venue_submitted_nonce('bvmgr_venue_location_nonce');
    if ($nonce === '' || !bvmgr_verify_nonce_compat($nonce, 'bvmgr_save_venue_location')) {
        return;
    }

    $read_text = static function (string $key): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This save handler verifies vms_save_venue_location before reading location fields.
        return bvmgr_request_read_text_field($_POST, $key);
    };

    $address = $read_text('vms_venue_address');
    $address_2 = $read_text('vms_venue_address_2');
    $city = $read_text('vms_venue_city');
    $state = strtoupper($read_text('vms_venue_state'));
    $zip = $read_text('vms_venue_zip');
    $country = strtoupper($read_text('vms_venue_country'));
    $latitude = trim($read_text('vms_venue_latitude'));
    $longitude = trim($read_text('vms_venue_longitude'));

    if ($country === '') {
        $country = 'US';
    }
    if ($state !== '') {
        $state = substr($state, 0, 2);
    }
    if ($country !== '') {
        $country = substr($country, 0, 2);
    }

    $latitude = preg_match('/^-?\d{1,3}(?:\.\d+)?$/', $latitude) ? $latitude : '';
    $longitude = preg_match('/^-?\d{1,3}(?:\.\d+)?$/', $longitude) ? $longitude : '';

    $updates = array(
        bvmgr_venue_meta_key('address', '_vms_address') => $address,
        bvmgr_venue_meta_key('address_2', '_vms_address_2') => $address_2,
        bvmgr_venue_meta_key('city', '_vms_city') => $city,
        bvmgr_venue_meta_key('state', '_vms_state') => $state,
        bvmgr_venue_meta_key('zip', '_vms_zip') => $zip,
        bvmgr_venue_meta_key('country', '_vms_country') => $country,
        bvmgr_venue_meta_key('latitude', '_vms_latitude') => $latitude,
        bvmgr_venue_meta_key('longitude', '_vms_longitude') => $longitude,
    );

    foreach ($updates as $meta_key => $meta_value) {
        if ($meta_value === '') {
            delete_post_meta($post_id, $meta_key);
        } else {
            update_post_meta($post_id, $meta_key, $meta_value);
        }
    }

    if (function_exists('bvmgr_sync_tec_venue_from_vms_venue')) {
        bvmgr_sync_tec_venue_from_vms_venue((int) $post_id);
    }
}, 18, 2);

/**
 * Venue Default Event Times
 * - Default start time
 * - Default duration (minutes)
 * - Optional default end time
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_venue_default_times',
        __('Default Event Times', 'backstage-venue-manager'),
        'bvmgr_render_venue_default_times_box',
        'vms_venue',
        'side',
        'default'
    );
});

function bvmgr_render_venue_default_times_box($post) {
    wp_nonce_field('bvmgr_save_venue_default_times', 'bvmgr_venue_default_times_nonce');

    $start = (string) get_post_meta($post->ID, '_vms_default_start_time', true);
    $dur   = (string) get_post_meta($post->ID, '_vms_default_duration_min', true);
    $end   = (string) get_post_meta($post->ID, '_vms_default_end_time', true);

    echo '<div class="vms-venue-default-times-admin">';

    echo '<p class="description vms-venue-times-desc">' .
        esc_html__('Used to pre-fill Start/End time when creating a new Event Plan for this venue.', 'backstage-venue-manager') .
    '</p>';

    echo '<p class="vms-venue-times-field">';
    echo '<label for="vms_default_start_time"><strong>' . esc_html__('Default Start', 'backstage-venue-manager') . '</strong></label><br>';
    echo '<input type="time" id="vms_default_start_time" name="vms_default_start_time" value="' . esc_attr($start) . '" class="vms-venue-times-input">';
    echo '</p>';

    echo '<p class="vms-venue-times-field">';
    echo '<label for="vms_default_duration_min"><strong>' . esc_html__('Default Duration (minutes)', 'backstage-venue-manager') . '</strong></label><br>';
    echo '<input type="number" min="0" step="1" id="vms_default_duration_min" name="vms_default_duration_min" value="' . esc_attr($dur) . '" class="vms-venue-times-input" placeholder="120">';
    echo '</p>';

    echo '<p class="vms-venue-times-field">';
    echo '<label for="vms_default_end_time"><strong>' . esc_html__('Default End (optional)', 'backstage-venue-manager') . '</strong></label><br>';
    echo '<input type="time" id="vms_default_end_time" name="vms_default_end_time" value="' . esc_attr($end) . '" class="vms-venue-times-input">';
    echo '<span class="description">' . esc_html__('If set, this overrides duration-based end time defaults.', 'backstage-venue-manager') . '</span>';
    echo '</p>';

    echo '</div>';
}

add_action('save_post_vms_venue', function ($post_id, $post) {
    if ($post->post_type !== 'vms_venue') return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $nonce = (isset($_POST['bvmgr_venue_default_times_nonce']) && !is_array($_POST['bvmgr_venue_default_times_nonce']))
        ? sanitize_text_field(wp_unslash((string) $_POST['bvmgr_venue_default_times_nonce']))
        : '';
    if ($nonce === '' || !bvmgr_verify_nonce_compat($nonce, 'bvmgr_save_venue_default_times')) {
        return;
    }

    $start = isset($_POST['vms_default_start_time']) ? sanitize_text_field(wp_unslash($_POST['vms_default_start_time'])) : '';
    $end   = isset($_POST['vms_default_end_time']) ? sanitize_text_field(wp_unslash($_POST['vms_default_end_time'])) : '';
    $dur   = isset($_POST['vms_default_duration_min']) ? absint($_POST['vms_default_duration_min']) : 0;

    // Basic time format guard: allow '' or HH:MM
    if ($start !== '' && !preg_match('/^\d{2}:\d{2}$/', $start)) $start = '';
    if ($end !== '' && !preg_match('/^\d{2}:\d{2}$/', $end)) $end = '';

    update_post_meta($post_id, '_vms_default_start_time', $start);
    update_post_meta($post_id, '_vms_default_end_time', $end);
    update_post_meta($post_id, '_vms_default_duration_min', $dur);
}, 20, 2);

/**
 * ==========================================================
 * Venue Schedule (Open Days + Optional Seasons)
 * ==========================================================
 *
 * Rules:
 * - CLOSED 24/7 by default until at least one Open Day is selected.
 * - If Open Year-Round is enabled, seasons are ignored.
 * - If Open Year-Round is disabled and at least one season is configured,
 *   the venue is open only within those ranges (and only on Open Days).
 * - Date overrides (open/closed) are stored separately and applied first.
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_venue_schedule',
        __('Venue Schedule', 'backstage-venue-manager'),
        'bvmgr_render_venue_schedule_box',
        'vms_venue',
        'normal',
        'default'
    );
});

function bvmgr_render_venue_schedule_box($post)
{
    wp_nonce_field('bvmgr_save_venue_schedule', 'bvmgr_venue_schedule_nonce');

    $open_days = get_post_meta($post->ID, '_vms_venue_open_days', true);
    if (!is_array($open_days)) $open_days = array();
    $open_days = array_values(array_unique(array_map('intval', $open_days)));

    $year_round = (int) get_post_meta($post->ID, '_vms_venue_open_year_round', true);

    $seasons = get_post_meta($post->ID, '_vms_venue_seasons', true);
    if (!is_array($seasons)) $seasons = array();

    // Normalize to exactly 2 season slots
    $slots = array(
        array('start' => '', 'end' => ''),
        array('start' => '', 'end' => ''),
    );
    for ($i = 0; $i < 2; $i++) {
        if (isset($seasons[$i]) && is_array($seasons[$i])) {
            $slots[$i]['start'] = isset($seasons[$i]['start']) ? (string) $seasons[$i]['start'] : '';
            $slots[$i]['end']   = isset($seasons[$i]['end'])   ? (string) $seasons[$i]['end']   : '';
        }
    }

    $days = array(
        0 => __('Sunday', 'backstage-venue-manager'),
        1 => __('Monday', 'backstage-venue-manager'),
        2 => __('Tuesday', 'backstage-venue-manager'),
        3 => __('Wednesday', 'backstage-venue-manager'),
        4 => __('Thursday', 'backstage-venue-manager'),
        5 => __('Friday', 'backstage-venue-manager'),
        6 => __('Saturday', 'backstage-venue-manager'),
    );

    echo '<div class="vms-venue-schedule-layout">';

    // Open Days
    echo '<div>';
    echo '<h3 class="vms-venue-schedule-subhead">' . esc_html__('Weekly Open Days', 'backstage-venue-manager') . '</h3>';
    echo '<p class="description vms-venue-schedule-help">' .
        esc_html__('Select the days this venue is normally open. If no days are selected, the venue is treated as closed until configured.', 'backstage-venue-manager') .
    '</p>';

    echo '<div class="vms-venue-open-days-grid">';
    foreach ($days as $num => $label) {
        $checked = in_array((int) $num, $open_days, true) ? 'checked' : '';
        echo '<label class="vms-venue-day-label">';
        echo '<input type="checkbox" name="vms_venue_open_days[]" value="' . esc_attr((int) $num) . '" ' . $checked . '>';
        echo '<span>' . esc_html($label) . '</span>';
        echo '</label>';
    }
    echo '</div>';

    echo '<div class="vms-venue-year-round-wrap">';
    echo '<label class="vms-venue-year-round-label">';
    echo '<input type="checkbox" name="vms_venue_open_year_round" value="1" ' . checked(1, $year_round, false) . '>';
    echo '<span><strong>' . esc_html__('Open year-round', 'backstage-venue-manager') . '</strong><br>';
    echo '<span class="description">' . esc_html__('If enabled, seasons are ignored.', 'backstage-venue-manager') . '</span></span>';
    echo '</label>';
    echo '</div>';

    echo '</div>';

    // Seasons
    echo '<div>';
    echo '<h3 class="vms-venue-schedule-subhead">' . esc_html__('Optional Seasons', 'backstage-venue-manager') . '</h3>';
    echo '<p class="description vms-venue-schedule-help">' .
        esc_html__('If Open year-round is off, you can optionally limit booking to one or two date ranges. Leave blank for year-round scheduling (based on Open Days).', 'backstage-venue-manager') .
    '</p>';

    for ($i = 0; $i < 2; $i++) {
        $idx = $i + 1;
        $start = $slots[$i]['start'];
        $end   = $slots[$i]['end'];

        echo '<div class="vms-venue-season-card">';
        /* translators: %d: number used in this message. */
        echo '<div class="vms-venue-season-title">' . esc_html(sprintf(__('Season %d', 'backstage-venue-manager'), $idx)) . '</div>';
        echo '<div class="vms-venue-season-row">';

        echo '<div class="vms-venue-season-field">';
        echo '<label><strong>' . esc_html__('Start', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<input type="date" name="vms_venue_season_start[' . esc_attr($i) . ']" value="' . esc_attr($start) . '" class="vms-venue-season-date">';
        echo '</div>';

        echo '<div class="vms-venue-season-field">';
        echo '<label><strong>' . esc_html__('End', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<input type="date" name="vms_venue_season_end[' . esc_attr($i) . ']" value="' . esc_attr($end) . '" class="vms-venue-season-date">';
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    echo '<p class="description vms-venue-schedule-tip">' .
        esc_html__('Tip: Use the Schedule page to mark individual exceptions (open/closed) once we add overrides.', 'backstage-venue-manager') .
    '</p>';

    echo '</div>';

    echo '</div>';
}

add_action('save_post_vms_venue', function ($post_id, $post) {
    if ($post->post_type !== 'vms_venue') return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $nonce = bvmgr_venue_submitted_nonce('bvmgr_venue_schedule_nonce');
    if ($nonce === '' || !bvmgr_verify_nonce_compat($nonce, 'bvmgr_save_venue_schedule')) {
        return;
    }

    $open_days = (isset($_POST['vms_venue_open_days']) && is_array($_POST['vms_venue_open_days']))
        ? (array) wp_unslash($_POST['vms_venue_open_days']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Venue schedule arrays are unslashed here and normalized element-by-element below.
        : array();
    $open_days = array_values(array_unique(array_map('intval', $open_days)));
    $open_days = array_values(array_filter($open_days, fn($d) => $d >= 0 && $d <= 6));

    $year_round = bvmgr_request_read_bool_flag($_POST, 'vms_venue_open_year_round') ? 1 : 0;

    $starts = (isset($_POST['vms_venue_season_start']) && is_array($_POST['vms_venue_season_start']))
        ? (array) wp_unslash($_POST['vms_venue_season_start']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Venue season dates are unslashed here and validated per entry below.
        : array();
    $ends   = (isset($_POST['vms_venue_season_end']) && is_array($_POST['vms_venue_season_end']))
        ? (array) wp_unslash($_POST['vms_venue_season_end']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Venue season dates are unslashed here and validated per entry below.
        : array();

    $seasons = array();
    for ($i = 0; $i < 2; $i++) {
        $start = isset($starts[$i]) ? sanitize_text_field(wp_unslash($starts[$i])) : '';
        $end   = isset($ends[$i])   ? sanitize_text_field(wp_unslash($ends[$i]))   : '';

        // Allow blanks; if one is set, both must be valid YYYY-MM-DD
        if ($start === '' && $end === '') continue;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end = '';

        if ($start !== '' && $end !== '') {
            if ($end < $start) {
                $tmp = $start;
                $start = $end;
                $end = $tmp;
            }
            $seasons[] = array('start' => $start, 'end' => $end);
        }
    }

    update_post_meta($post_id, '_vms_venue_open_days', $open_days);
    update_post_meta($post_id, '_vms_venue_open_year_round', $year_round);
    update_post_meta($post_id, '_vms_venue_seasons', $seasons);

}, 21, 2);

/**
 * Enforce CLOSED-by-default: prevent publishing until at least one Open Day is selected.
 */
add_filter('wp_insert_post_data', function ($data, $postarr) {
    if (empty($data['post_type']) || $data['post_type'] !== 'vms_venue') return $data;

    // Only block publish.
    if (empty($data['post_status']) || $data['post_status'] !== 'publish') return $data;

    $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
    if ($post_id <= 0) return $data;

    // Prefer the just-submitted value during a publish attempt.
    // wp_insert_post_data runs before save_post_* updates post_meta, so relying only on saved meta can incorrectly block first-time publishes.
    $open_days_raw = null;
    $submitted_open_days = array();
    $has_submitted_open_days = false;
    $schedule_nonce = bvmgr_venue_submitted_nonce('bvmgr_venue_schedule_nonce');
    $has_verified_schedule_request = ($schedule_nonce !== '' && bvmgr_verify_nonce_compat($schedule_nonce, 'bvmgr_save_venue_schedule'));

    if ($has_verified_schedule_request) {
        $submitted_open_days = (isset($_POST['vms_venue_open_days']) && is_array($_POST['vms_venue_open_days']))
            ? (array) wp_unslash($_POST['vms_venue_open_days']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The publish gate reads submitted open days only after verifying the schedule nonce.
            : array();
    }
    if (!empty($submitted_open_days)) {
        $open_days_raw = $submitted_open_days;
        $has_submitted_open_days = true;
    } else {
        $open_days_raw = get_post_meta($post_id, '_vms_venue_open_days', true);
    }
    $open_days = array();

    if (is_array($open_days_raw)) {
        $open_days = $open_days_raw;
    } elseif (is_numeric($open_days_raw)) {
        $open_days = array((int) $open_days_raw);
    } elseif (is_string($open_days_raw)) {
        // Older / inconsistent storage: sometimes this was saved as "6" or "1,3,5"
        $parts = preg_split('/[^0-9]+/', $open_days_raw, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($parts) && !empty($parts)) {
            $open_days = $parts;
        }
    }

    $open_days = array_values(array_filter(array_map('intval', (array) $open_days)));

    $open_days_clean = array();
    foreach ($open_days as $v) {
        if ($v >= 0 && $v <= 6) $open_days_clean[] = $v;
    }
    $open_days = array_values(array_unique($open_days_clean));

    // Persist normalized values so future reads behave consistently.
    if (!empty($open_days) && ($has_submitted_open_days || !is_array($open_days_raw))) {
        update_post_meta($post_id, '_vms_venue_open_days', $open_days);
    }

    if (empty($open_days)) {
        $data['post_status'] = 'draft';

        // Store a short-lived notice keyed to this post.
        set_transient('vms_venue_schedule_notice_' . $post_id, 1, 60);
    }

    return $data;
}, 10, 2);

add_action('admin_notices', function () {
    if (!is_admin()) return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'vms_venue') return;

    $post_id = bvmgr_venue_notice_request_post_id();
    if ($post_id <= 0) return;

    $key = 'vms_venue_schedule_notice_' . $post_id;
    if (!get_transient($key)) return;

    delete_transient($key);

    echo '<div class="notice notice-warning is-dismissible">';
    echo '<p><strong>' . esc_html__('Venue not published.', 'backstage-venue-manager') . '</strong> ' . esc_html__('Select at least one Weekly Open Day in Venue Schedule, then publish again.', 'backstage-venue-manager') . '</p>';
    echo '</div>';
});


/**
 * Venue Restore Failsafe:
 * WordPress sometimes restores CPTs from Trash as Draft.
 * We remember the pre-trash status and attempt to restore it on untrash.
 */
add_action('wp_trash_post', function ($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) return;
    if (get_post_type($post_id) !== 'vms_venue') return;

    $p = get_post($post_id);
    if (!$p) return;

    update_post_meta($post_id, '_vms_pretrash_post_status', (string) $p->post_status);
}, 5);

add_action('untrash_post', function ($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) return;
    if (get_post_type($post_id) !== 'vms_venue') return;

    $prev = (string) get_post_meta($post_id, '_vms_pretrash_post_status', true);
    $prev = sanitize_key($prev);

    // Always clear it so it does not stick around forever.
    delete_post_meta($post_id, '_vms_pretrash_post_status');

    if (!$prev) return;

    $p = get_post($post_id);
    if (!$p) return;

    $current = sanitize_key((string) $p->post_status);

    // If WP restored the venue to a different status (commonly Draft), try to restore the original.
    if ($current !== $prev) {
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => $prev,
        ));

        set_transient('vms_venue_restore_notice_' . $post_id, $prev, 30);
    }
}, 5);

add_action('admin_notices', function () {
    $post_id = bvmgr_venue_notice_request_post_id();
    if ($post_id <= 0) return;
    if (get_post_type($post_id) !== 'vms_venue') return;

    $key = 'vms_venue_restore_notice_' . $post_id;
    $prev = get_transient($key);
    if (!$prev) return;

    delete_transient($key);

    echo '<div class="notice notice-info is-dismissible">';
    echo '<p><strong>' . esc_html__('Venue restored.', 'backstage-venue-manager') . '</strong> ' . sprintf(
        /* translators: %s: status label. */
        esc_html__('Status was restored to "%s" (pre-trash state).', 'backstage-venue-manager'),
        esc_html($prev)
    ) . '</p>';
    echo '</div>';
});

/**
 * Venues — make Draft status impossible to miss.
 *
 * Goals:
 * - If there are venues but NONE are Published/Private, warn loudly (Schedule will look empty).
 * - Make Draft venues read as “NOT READY — Draft” in the Venues list.
 * - Add a simple “Readiness” column with a red/green pill.
 */

// Replace the default subtle “Draft” post state with a loud label.
add_filter('display_post_states', function (array $states, $post): array {

    if (!is_object($post) || !isset($post->post_type) || (string) $post->post_type !== 'vms_venue') {
        return $states;
    }

    $st = isset($post->post_status) ? (string) $post->post_status : '';

    if ($st === 'draft' || $st === 'pending') {

        // Remove any existing “Draft” state so we don’t show both.
        foreach ($states as $k => $v) {
            if (is_string($v) && strtolower($v) === 'draft') {
                unset($states[$k]);
            }
        }

        $states['vms_not_ready'] = __('NOT READY — Draft', 'backstage-venue-manager');
    }

    return $states;
}, 10, 2);

// Add a “Readiness” column to the Venues list table.
add_filter('manage_vms_venue_posts_columns', function (array $cols): array {

    $out = array();
    foreach ($cols as $k => $v) {
        $out[$k] = $v;

        if ($k === 'title') {
            $out['vms_readiness'] = __('Readiness', 'backstage-venue-manager');
        }
    }

    // Fallback: if title column was missing (unexpected), append at end.
    if (!isset($out['vms_readiness'])) {
        $out['vms_readiness'] = __('Readiness', 'backstage-venue-manager');
    }

    return $out;
});

// Render the “Readiness” column content.
add_action('manage_vms_venue_posts_custom_column', function (string $column, int $post_id): void {

    if ($column !== 'vms_readiness') {
        return;
    }

    $st = (string) get_post_status($post_id);

    $is_ready = ($st === 'publish' || $st === 'private');

    $pill_class = $is_ready ? 'is-ready' : 'is-not-ready';

    $status_label = ($st !== '') ? ucfirst($st) : 'Draft';

    $text = $is_ready
        ? ('Ready — ' . $status_label)
        : ('NOT READY — ' . $status_label);

    echo '<span class="vms-status-pill ' . esc_attr($pill_class) . '">' . esc_html($text) . '</span>';
}, 10, 2);

// Urgent warnings when there are venues, but none are Published/Private.
add_action('admin_notices', function (): void {

    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (!function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
    if (!is_object($screen)) {
        return;
    }

    $is_venue_list = (isset($screen->id) && (string) $screen->id === 'edit-vms_venue');
    $is_venue_edit = (isset($screen->id) && (string) $screen->id === 'vms_venue');

    if (!$is_venue_list && !$is_venue_edit) {
        return;
    }

    // Do we have ANY venues at all (including Draft)?
    $any_ids = get_posts(array(
        'post_type'      => 'vms_venue',
        'post_status'    => array('publish', 'private', 'draft', 'pending'),
        'posts_per_page' => 2,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ));

    if (empty($any_ids)) {
        return;
    }

    // Do we have at least one “ready” venue?
    $ready_ids = get_posts(array(
        'post_type'      => 'vms_venue',
        'post_status'    => array('publish', 'private'),
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ));

    if (!empty($ready_ids)) {
        // We have a ready venue, so no global siren needed.
        return;
    }

    $list_url = admin_url('edit.php?post_type=vms_venue');

    $only_id = (count($any_ids) === 1) ? (int) $any_ids[0] : 0;
    $only_status = ($only_id > 0) ? (string) get_post_status($only_id) : '';
    $only_title = ($only_id > 0) ? (string) get_the_title($only_id) : '';
    $only_edit = ($only_id > 0) ? get_edit_post_link($only_id, '') : '';

    echo '<div class="notice notice-error">';
    echo '<p><strong>Backstage Venue Manager:</strong> Action required — no Published venues are available. Schedule will appear empty until at least one venue is Published.</p>';

    if ($only_id > 0) {
        $only_status_label = $only_status !== '' ? ucfirst($only_status) : 'Draft';
        echo '<p><strong>Your only venue is currently ' . esc_html($only_status_label) . ':</strong> ' . esc_html($only_title) . '</p>';
    }

    echo '<p>';
    if ($only_edit) {
        echo '<a class="button button-primary" href="' . esc_url($only_edit) . '">Open venue</a> ';
    }
    echo '<a class="button" href="' . esc_url($list_url) . '">View venues</a>';
    echo '</p>';

    echo '<p class="description">If this is intentional (setup in progress), you can ignore this warning until you are ready to publish.</p>';
    echo '</div>';

}, 20);
