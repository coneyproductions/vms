<?php

/**
 * Plugin Name: Vendor Management System (VMS)
 * Description: A vendor management, booking, and scheduling system for venues and events.
 * Author: Coney Productions
 * Version: 0.1.0
 * Text Domain: vms
 * Domain Path: /languages
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Load includes
 */
$base = plugin_dir_path(__FILE__) . 'includes/';

$includes = array(
    'helpers.php',

    // // CPT
    'cpt/vendors.php',
    'cpt/venues.php',
    'cpt/event-plans.php',
    'cpt/ratings.php',

    // Integrations
    'integrations/tec-sync.php',
    'integrations/attendance-woo.php',
    // 'integrations/availability-engine.php',

    // Vendor system
    'vendor-applications.php',
    'portal/vendor-portal.php',

    // Admin
    'admin/menu.php',
    'admin/venue-context.php',
    'admin/season-board.php',
    'admin/settings-page.php',

);

foreach ($includes as $file) {
    $path = $base . $file;
    if (file_exists($path)) {
        include_once $path;
    } else {
        error_log('VMS missing include: ' . $path);
    }
}

add_action('after_setup_theme', function () {
    add_post_type_support('vms_vendor', 'thumbnail');
});

// Plugin constants.
define('VMS_VERSION', '0.1.0');
define('VMS_PLUGIN_FILE', __FILE__);
define('VMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SR_EVENT_PLAN_STATUS_KEY', '_sr_event_plan_status'); // draft|ready|published

// Adjust these to match your existing meta keys:
define('SR_EVENT_PLAN_BAND_KEY',   '_sr_event_plan_band_vendor_id');
define('SR_EVENT_PLAN_START_KEY',  '_sr_event_plan_start_time');   // e.g. "19:00"
define('SR_EVENT_PLAN_END_KEY',    '_sr_event_plan_end_time');     // e.g. "22:00"

register_activation_hook(__FILE__, 'vms_add_vendor_role');
function vms_add_vendor_role()
{
    add_role('vms_vendor', 'Vendor', array(
        'read'         => true,
        'upload_files' => true,
    ));
}

// Activation / deactivation hooks.
register_activation_hook(__FILE__, 'vms_activate_plugin');
register_deactivation_hook(__FILE__, 'vms_deactivate_plugin');


/**
 * Runs on plugin deactivation.
 */
function vms_deactivate_plugin()
{
    // Flush rewrite rules to clean up.
    flush_rewrite_rules();
}


function vms_render_season_dates_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle save.
    if (
        isset($_POST['vms_season_settings_nonce']) &&
        wp_verify_nonce($_POST['vms_season_settings_nonce'], 'vms_save_season_settings')
    ) {

        $rules = array();

        for ($i = 1; $i <= 2; $i++) {
            $start = isset($_POST["season{$i}_start"]) ? sanitize_text_field($_POST["season{$i}_start"]) : '';
            $end   = isset($_POST["season{$i}_end"]) ? sanitize_text_field($_POST["season{$i}_end"]) : '';
            $days  = isset($_POST["season{$i}_days"]) && is_array($_POST["season{$i}_days"])
                ? array_map('intval', $_POST["season{$i}_days"])
                : array();

            if ($start && $end && !empty($days)) {
                $rules[] = array(
                    'start' => $start, // "YYYY-MM-DD"
                    'end'   => $end,
                    'days'  => $days,  // [5,6] for Fri/Sat, etc.
                );
            }
        }

        // Save rules.
        update_option('vms_season_rules', $rules);

        // Generate and save active dates.
        $active_dates = vms_generate_active_dates($rules);
        update_option('vms_active_dates', $active_dates);

        echo '<div class="notice notice-success"><p>Season settings saved. '
            . count($active_dates)
            . ' active booking dates generated.</p></div>';
    }

    // Load existing values for the form.
    $rules = get_option('vms_season_rules', array());

    $season1 = isset($rules[0]) ? $rules[0] : array('start' => '', 'end' => '', 'days' => array(5, 6));
    $season2 = isset($rules[1]) ? $rules[1] : array('start' => '', 'end' => '', 'days' => array());

?>
    <div class="wrap">
        <h1><?php esc_html_e('Season Dates', 'vms'); ?></h1>
        <p>Define your booking seasons. Active dates will be used for vendor availability grids.</p>

        <form method="post">
            <?php wp_nonce_field('vms_save_season_settings', 'vms_season_settings_nonce'); ?>

            <h2>Season 1</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="season1_start">Start Date</label></th>
                    <td><input type="date" id="season1_start" name="season1_start"
                            value="<?php echo esc_attr($season1['start']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="season1_end">End Date</label></th>
                    <td><input type="date" id="season1_end" name="season1_end"
                            value="<?php echo esc_attr($season1['end']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Days of Week</th>
                    <td>
                        <?php vms_render_weekday_checkboxes('season1_days', $season1['days']); ?>
                    </td>
                </tr>
            </table>

            <hr />

            <h2>Season 2</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="season2_start">Start Date</label></th>
                    <td><input type="date" id="season2_start" name="season2_start"
                            value="<?php echo esc_attr($season2['start']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="season2_end">End Date</label></th>
                    <td><input type="date" id="season2_end" name="season2_end"
                            value="<?php echo esc_attr($season2['end']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Days of Week</th>
                    <td>
                        <?php vms_render_weekday_checkboxes('season2_days', $season2['days']); ?>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Season Settings'); ?>
        </form>
    </div>
<?php
}

function vms_render_weekday_checkboxes($name, $selected_days)
{
    $labels = array(
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    );

    foreach ($labels as $num => $label) {
        printf(
            '<label style="margin-right:10px;"><input type="checkbox" name="%1$s[]" value="%2$d" %3$s> %4$s</label>',
            esc_attr($name),
            (int) $num,
            checked(in_array($num, (array) $selected_days, true), true, false),
            esc_html($label)
        );
    }
}

/**
 * Generate active booking dates from recurring season rules.
 *
 * Rules define month/day ranges & days of week, example:
 *  [
 *    ['start' => '03-01', 'end' => '06-30', 'days' => [5,6]], // Fri/Sat Spring
 *    ['start' => '09-01', 'end' => '12-15', 'days' => [5,6]], // Fri/Sat Fall
 *  ]
 *
 * This function:
 * - repeats the pattern for current year + next year
 * - generates YYYY-MM-DD active booking dates
 * - ignores invalid ranges (like Feb 30)
 *
 * @param array $rules
 * @return array Sorted array of "YYYY-MM-DD"
 */
function vms_generate_active_dates($rules)
{
    $dates = array();

    $current_year = (int) date('Y');
    $target_years = array($current_year, $current_year + 1);

    foreach ($target_years as $year) {
        foreach ($rules as $rule) {
            $start_md = isset($rule['start']) ? $rule['start'] : '';
            $end_md   = isset($rule['end'])   ? $rule['end']   : '';
            $days     = isset($rule['days'])  ? (array) $rule['days'] : array();

            if (!$start_md || !$end_md || empty($days)) {
                continue;
            }

            // Build YYYY-MM-DD strings
            $start_date_str = $year . '-' . $start_md;
            $end_date_str   = $year . '-' . $end_md;

            $start_ts = strtotime($start_date_str);
            $end_ts   = strtotime($end_date_str);

            // Validate timestamps + ordering
            if (!$start_ts || !$end_ts || $end_ts < $start_ts) {
                continue;
            }

            // Walk every day in the range
            for ($ts = $start_ts; $ts <= $end_ts; $ts = strtotime('+1 day', $ts)) {
                $dow = (int) date('w', $ts); // 0=Sun, 6=Sat
                if (in_array($dow, $days, true)) {
                    $dates[] = date('Y-m-d', $ts);
                }
            }
        }
    }

    // Deduplicate + sort
    $dates = array_values(array_unique($dates));
    sort($dates);

    return $dates;
}


add_action('add_meta_boxes', 'vms_add_vendor_availability_metabox');
function vms_add_vendor_availability_metabox()
{
    add_meta_box(
        'vms_vendor_availability',
        __('Availability (Season Dates)', 'vms'),
        'vms_render_vendor_availability_metabox',
        'vms_vendor',
        'normal',
        'default'
    );
}

function vms_render_vendor_availability_metabox($post)
{
    wp_nonce_field('vms_save_vendor_availability', 'vms_vendor_availability_nonce');

    $active_dates = get_option('vms_active_dates', array());
    $availability = get_post_meta($post->ID, '_vms_availability', true);
    if (!is_array($availability)) {
        $availability = array();
    }

    if (empty($active_dates)) {
        echo '<p>No season dates are defined yet. '
            . 'Go to <strong>Vendors → Season Dates</strong> to configure your seasons.</p>';
        return;
    }

    echo '<p>Set availability for each active booking date. Leave blank if unknown.</p>';
    echo '<table class="widefat striped" style="max-width:600px;">';
    echo '<thead><tr><th>Date</th><th>Day</th><th>Availability</th></tr></thead><tbody>';

    $current_month = '';

    foreach ($active_dates as $date_str) {

        $ts = strtotime($date_str);
        if (!$ts) continue;

        $month_label = date_i18n('F Y', $ts); // e.g. "March 2026"

        // New month header row
        if ($month_label !== $current_month) {
            $current_month = $month_label;

            echo '<tr class="vms-month-header">';
            echo '<td colspan="3"><strong>' . esc_html($month_label) . '</strong></td>';
            echo '</tr>';
        }

        $day  = date_i18n('D', $ts);
        $nice = date_i18n('M j, Y', $ts);
        $val  = isset($availability[$date_str]) ? $availability[$date_str] : '';

        echo '<tr>';
        echo '<td>' . esc_html($nice) . '</td>';
        echo '<td>' . esc_html($day) . '</td>';
        echo '<td>';
        echo '<select name="vms_availability[' . esc_attr($date_str) . ']">';
        echo '<option value="" ' . selected($val, '', false) . '>—</option>';
        echo '<option value="available" ' . selected($val, 'available', false) . '>Available</option>';
        echo '<option value="unavailable" ' . selected($val, 'unavailable', false) . '>Not Available</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

add_action('save_post_vms_vendor', 'vms_save_vendor_availability_meta', 20, 2);
function vms_save_vendor_availability_meta($post_id, $post)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if ($post->post_type !== 'vms_vendor') {
        return;
    }
    if (
        !isset($_POST['vms_vendor_availability_nonce']) ||
        !wp_verify_nonce($_POST['vms_vendor_availability_nonce'], 'vms_save_vendor_availability')
    ) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $incoming = isset($_POST['vms_availability']) && is_array($_POST['vms_availability'])
        ? $_POST['vms_availability']
        : array();

    $clean = array();
    foreach ($incoming as $date => $state) {
        $date  = sanitize_text_field($date);
        $state = sanitize_text_field($state);

        if ($state === 'available' || $state === 'unavailable') {
            $clean[$date] = $state;
        }
        // if blank, we just don't store it (remains "unknown").
    }

    update_post_meta($post_id, '_vms_availability', $clean);
}

/**
 * Save the selected band vendor on the event.
 * Adjust 'tribe_events' if your event post type is named differently.
 */
add_action('save_post_tribe_events', 'vms_save_event_band_vendor_meta', 20, 2);
function vms_save_event_band_vendor_meta($post_id, $post)
{

    // Autosave / revisions guard
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Make sure we're on the right post type
    if ($post->post_type !== 'tribe_events') {
        return;
    }

    // Basic permission check
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    // If the select field exists on the form, save it
    if (isset($_POST['vms_band_vendor_id'])) {
        $band_id = absint($_POST['vms_band_vendor_id']);

        if ($band_id) {
            // ✅ store the selected band on the event
            update_post_meta($post_id, '_vms_band_vendor_id', $band_id);
        } else {
            // If they cleared it or chose the placeholder, remove the meta
            delete_post_meta($post_id, '_vms_band_vendor_id');
        }
    }
}

/**
 * Get a vendor's availability status for a specific date.
 *
 * @param int    $vendor_id Vendor (vms_vendor) post ID.
 * @param string $date_str  Date in "YYYY-MM-DD" format.
 * @return string           'available', 'unavailable', or 'unknown'.
 */
function vms_get_vendor_availability_for_date($vendor_id, $date_str)
{
    if (!$vendor_id || !$date_str) {
        return 'unknown';
    }

    $availability = get_post_meta($vendor_id, '_vms_availability', true);
    if (!is_array($availability)) {
        $availability = array();
    }

    if (isset($availability[$date_str])) {
        return $availability[$date_str]; // 'available' or 'unavailable'
    }

    return 'unknown';
}

/**
 * Get the internal VMS Event Plan status.
 *
 * @param int $post_id
 * @return string 'draft', 'ready', 'pending', 'published'
 */
function vms_get_event_plan_status($post_id)
{
    $status = get_post_meta($post_id, '_vms_event_plan_status', true);
    if (!$status) {
        $status = 'draft';
    }
    return $status;
}

/**
 * Map VMS Event Plan status to The Events Calendar post_status.
 *
 * VMS is the source of truth. Direction: VMS → TEC.
 *
 * VMS Draft / Ready / Pending  → TEC unpublished (draft)
 * VMS Published                → TEC published
 *
 * @param string $plan_status
 * @return string TEC post_status value
 */
function vms_map_plan_status_to_tec_post_status($plan_status)
{
    switch ($plan_status) {
        case 'published':
            return 'publish'; // live & public

        case 'pending':
            // If you ever want "pending review" behavior in TEC, you could
            // return 'pending' here. For now we keep it hidden from public:
            return 'draft';

        case 'ready':
        case 'draft':
        default:
            return 'draft';  // not publicly visible
    }
}

/**
 * When an Event Plan is trashed, also trash the linked TEC event.
 */
add_action('trash_post', 'vms_trash_linked_tec_event');
function vms_trash_linked_tec_event($post_id)
{
    $post = get_post($post_id);
    if (!$post) {
        return;
    }

    // Adjust this CPT slug if needed.
    if ($post->post_type !== 'vms_event_plan') {
        return;
    }

    $tec_id = (int) get_post_meta($post_id, '_vms_tec_event_id', true);
    if ($tec_id && get_post($tec_id)) {
        wp_trash_post($tec_id);
    }
}

/**
 * When an Event Plan is untrashed, optionally restore the TEC event.
 * (Only if you want that behavior.)
 */
add_action('untrash_post', 'vms_untrash_linked_tec_event');
function vms_untrash_linked_tec_event($post_id)
{
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'vms_event_plan') {
        return;
    }

    $tec_id = (int) get_post_meta($post_id, '_vms_tec_event_id', true);
    if ($tec_id) {
        // If the TEC event is in trash, restore it.
        $tec_post = get_post($tec_id);
        if ($tec_post && $tec_post->post_status === 'trash') {
            wp_untrash_post($tec_id);
        }
    }
}

/**
 * Sync TEC event post_status from VMS Event Plan status.
 *
 * Direction: VMS → TEC
 * - VMS draft/ready/pending => TEC draft (unpublished)
 * - VMS published           => TEC publish
 */
function vms_sync_tec_status_from_plan($post_id)
{
    // If there's no linked TEC event, nothing to do.
    $tec_id = (int) get_post_meta($post_id, '_vms_tec_event_id', true);
    if (!$tec_id || !get_post($tec_id)) {
        return;
    }

    // Optional future lock setting.
    $lock = get_option('vms_lock_tec_status'); // checkbox you can add later
    if ($lock) {
        return;
    }

    // Get VMS plan status and map to TEC post_status.
    $plan_status = vms_get_event_plan_status($post_id);
    $tec_status  = vms_map_plan_status_to_tec_post_status($plan_status);

    // If TEC is already in the desired status, skip.
    $current = get_post_status($tec_id);
    if ($current === $tec_status) {
        return;
    }

    // Build update args.
    $update = array(
        'ID'          => $tec_id,
        'post_status' => $tec_status,
    );

    // Use TEC helper if available, otherwise plain wp_update_post.
    if (function_exists('tribe_update_event')) {
        // tribe_update_event expects a full args array including post_type.
        $update['post_type'] = 'tribe_events';
        tribe_update_event($tec_id, $update);
    } else {
        wp_update_post($update);
    }
}

add_action('init', function () {
    load_plugin_textdomain('vms', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
