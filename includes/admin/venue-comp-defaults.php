<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Venue Default Compensation by Day of Week
 *
 * Stores per-day defaults on vms_venue as meta:
 *   _vms_default_comp_by_dow (array)
 *
 * Day-of-week keys:
 *   0 = Sunday . . . 6 = Saturday  (matches PHP date('w'))
 *
 * Each day stores:
 *   structure          flat_fee | flat_fee_door_split | door_split
 *   flat_fee_amount    float
 *   door_split_percent float
 *   commission_percent float   (optional; useful for agency venues)
 *   commission_mode    artist_fee | gross
 */

/**
 * Register metabox on Venue edit screen.
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_venue_comp_defaults',
        __('Default Pay (By Day)', 'vms'),
        'vms_render_venue_comp_defaults_metabox',
        'vms_venue',
        'normal',
        'default'
    );
});

/**
 * Save metabox data.
 */
add_action('save_post_vms_venue', function ($post_id, $post) {

    if (!$post || $post->post_type !== 'vms_venue') return;

    // Autosave / revisions guards
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    // Nonce
    if (
        empty($_POST['vms_venue_comp_defaults_nonce']) ||
        !wp_verify_nonce($_POST['vms_venue_comp_defaults_nonce'], 'vms_save_venue_comp_defaults')
    ) {
        return;
    }

    // Permission
    if (!current_user_can('edit_post', $post_id)) return;

    $incoming = isset($_POST['vms_venue_comp_by_dow']) && is_array($_POST['vms_venue_comp_by_dow'])
        ? (array) $_POST['vms_venue_comp_by_dow']
        : array();

    $out = array();

    // Expected days 0..6
    for ($dow = 0; $dow <= 6; $dow++) {
        $row = isset($incoming[$dow]) && is_array($incoming[$dow]) ? $incoming[$dow] : array();

        $structure = isset($row['structure']) ? sanitize_text_field(wp_unslash($row['structure'])) : '';
        if (!in_array($structure, array('flat_fee', 'flat_fee_door_split', 'door_split'), true)) {
            $structure = 'flat_fee';
        }

        // Numbers: allow blank -> '' (meaning “no default provided”)
        $flat  = isset($row['flat_fee_amount']) ? trim((string) wp_unslash($row['flat_fee_amount'])) : '';
        $split = isset($row['door_split_percent']) ? trim((string) wp_unslash($row['door_split_percent'])) : '';
        $comm  = isset($row['commission_percent']) ? trim((string) wp_unslash($row['commission_percent'])) : '';

        $flat_val  = ($flat === '') ? '' : (float) $flat;
        $split_val = ($split === '') ? '' : (float) $split;
        $comm_val  = ($comm === '') ? '' : (float) $comm;

        // Commission mode
        $comm_mode = isset($row['commission_mode']) ? sanitize_text_field(wp_unslash($row['commission_mode'])) : 'artist_fee';
        if (!in_array($comm_mode, array('artist_fee', 'gross'), true)) {
            $comm_mode = 'artist_fee';
        }

        // Normalize bounds
        if ($split_val !== '' && ($split_val < 0 || $split_val > 100)) $split_val = '';
        if ($comm_val !== '' && ($comm_val < 0 || $comm_val > 100)) $comm_val = '';
        if ($flat_val !== '' && $flat_val < 0) $flat_val = '';

        $out[$dow] = array(
            'structure'          => $structure,
            'flat_fee_amount'    => $flat_val,
            'door_split_percent' => $split_val,
            'commission_percent' => $comm_val,
            'commission_mode'    => $comm_mode,
        );
    }

    update_post_meta($post_id, '_vms_default_comp_by_dow', $out);

}, 20, 2);

/**
 * Render metabox UI.
 */
function vms_render_venue_comp_defaults_metabox($post) {

    wp_nonce_field('vms_save_venue_comp_defaults', 'vms_venue_comp_defaults_nonce');

    $saved = get_post_meta($post->ID, '_vms_default_comp_by_dow', true);
    if (!is_array($saved)) $saved = array();

    // Default row template
    $defaults = array(
        'structure'          => 'flat_fee',
        'flat_fee_amount'    => '',
        'door_split_percent' => '',
        'commission_percent' => '',
        'commission_mode'    => 'artist_fee',
    );

    // Days in UI order (Mon..Sun is easier for humans)
    $days = array(
        1 => __('Mon', 'vms'),
        2 => __('Tue', 'vms'),
        3 => __('Wed', 'vms'),
        4 => __('Thu', 'vms'),
        5 => __('Fri', 'vms'),
        6 => __('Sat', 'vms'),
        0 => __('Sun', 'vms'),
    );

    echo '<style>
.vms-comp-table{width:100%;max-width:980px;border-collapse:separate;border-spacing:0;}
.vms-comp-table th,.vms-comp-table td{padding:10px 10px;border-bottom:1px solid #dcdcde;vertical-align:top;}
.vms-comp-table thead th{background:#f6f7f7;font-weight:700;}
.vms-comp-table .vms-day{width:70px;font-weight:800;}
.vms-comp-table select,.vms-comp-table input{width:100%;max-width:220px;}
@media (max-width: 900px){
  .vms-comp-table select,.vms-comp-table input{max-width:none;}
}
.vms-help{color:#646970;font-size:12px;margin:6px 0 0;}
.vms-note{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:10px 12px;margin:0 0 12px;max-width:980px;}
</style>';

    echo '<div class="vms-note">';
    echo '<strong>' . esc_html__('How this works:', 'vms') . '</strong> ';
    echo esc_html__('When you create/edit an Event Plan, we can auto-fill compensation based on Venue + Event Date. Event Plan values remain the final override.', 'vms');
    echo '<div class="vms-help">' . esc_html__('Holidays/closed days will be layered on top in the next step.', 'vms') . '</div>';
    echo '</div>';

    echo '<table class="vms-comp-table widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Day', 'vms') . '</th>';
    echo '<th>' . esc_html__('Structure', 'vms') . '</th>';
    echo '<th>' . esc_html__('Flat Fee', 'vms') . '</th>';
    echo '<th>' . esc_html__('Door Split %', 'vms') . '</th>';
    echo '<th>' . esc_html__('Commission %', 'vms') . '</th>';
    echo '<th>' . esc_html__('Commission Mode', 'vms') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($days as $dow => $label) {

        $row = isset($saved[$dow]) && is_array($saved[$dow]) ? $saved[$dow] : array();
        $row = array_merge($defaults, $row);

        $name = 'vms_venue_comp_by_dow[' . (int)$dow . ']';

        echo '<tr>';
        echo '<td class="vms-day">' . esc_html($label) . '</td>';

        // Structure
        echo '<td>';
        echo '<select name="' . esc_attr($name . '[structure]') . '">';
        echo '<option value="flat_fee" ' . selected($row['structure'], 'flat_fee', false) . '>' . esc_html__('Flat Fee Only', 'vms') . '</option>';
        echo '<option value="flat_fee_door_split" ' . selected($row['structure'], 'flat_fee_door_split', false) . '>' . esc_html__('Flat Fee + Door Split', 'vms') . '</option>';
        echo '<option value="door_split" ' . selected($row['structure'], 'door_split', false) . '>' . esc_html__('Door Split Only', 'vms') . '</option>';
        echo '</select>';
        echo '</td>';

        // Flat fee
        echo '<td>';
        echo '<input type="number" step="0.01" min="0" name="' . esc_attr($name . '[flat_fee_amount]') . '" value="' . esc_attr($row['flat_fee_amount']) . '" placeholder="0.00">';
        echo '</td>';

        // Split
        echo '<td>';
        echo '<input type="number" step="0.01" min="0" max="100" name="' . esc_attr($name . '[door_split_percent]') . '" value="' . esc_attr($row['door_split_percent']) . '" placeholder="0">';
        echo '</td>';

        // Commission %
        echo '<td>';
        echo '<input type="number" step="0.01" min="0" max="100" name="' . esc_attr($name . '[commission_percent]') . '" value="' . esc_attr($row['commission_percent']) . '" placeholder="15">';
        echo '<div class="vms-help">' . esc_html__('Optional (agency venues typically use 15%).', 'vms') . '</div>';
        echo '</td>';

        // Commission mode
        echo '<td>';
        echo '<select name="' . esc_attr($name . '[commission_mode]') . '">';
        echo '<option value="artist_fee" ' . selected($row['commission_mode'], 'artist_fee', false) . '>' . esc_html__('Added to artist fee', 'vms') . '</option>';
        echo '<option value="gross" ' . selected($row['commission_mode'], 'gross', false) . '>' . esc_html__('Taken from gross', 'vms') . '</option>';
        echo '</select>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<p class="vms-help" style="max-width:980px;margin-top:10px;">' .
        esc_html__('Tip: Leaving a value blank means “no default” for that day; the Event Plan can still be set manually.', 'vms') .
        '</p>';
}

/**
 * Helper: get all per-day defaults for a venue.
 */
function vms_get_venue_default_comp_by_dow(int $venue_id): array {
    $saved = get_post_meta($venue_id, '_vms_default_comp_by_dow', true);
    return is_array($saved) ? $saved : array();
}


function vms_get_venue_default_comp_for_date(int $venue_id, string $event_date): array {
    $event_date = trim($event_date);
    if ($venue_id <= 0 || $event_date === '') return array();

    // Use VMS timezone helper if you have it; fallback to WP timezone.
    $tz = null;
    if (function_exists('vms_get_timezone')) {
        $tz = vms_get_timezone(); // expected DateTimeZone
    }
    if (!$tz instanceof DateTimeZone) {
        $tz = wp_timezone();
    }

    // Parse date safely
    try {
        $dt = new DateTimeImmutable($event_date, $tz);
    } catch (Exception $e) {
        return array();
    }

    $dow = (int) $dt->format('w'); // 0..6 (Sun..Sat)

    $all = vms_get_venue_default_comp_by_dow($venue_id);
    if (!isset($all[$dow]) || !is_array($all[$dow])) return array();

    // Normalize output keys
    $row = $all[$dow];

    $out = array(
        'structure'          => isset($row['structure']) ? (string) $row['structure'] : 'flat_fee',
        'flat_fee_amount'    => $row['flat_fee_amount'] ?? '',
        'door_split_percent' => $row['door_split_percent'] ?? '',
        'commission_percent' => $row['commission_percent'] ?? '',
        'commission_mode'    => isset($row['commission_mode']) ? (string) $row['commission_mode'] : 'artist_fee',
    );

    if (!in_array($out['structure'], array('flat_fee', 'flat_fee_door_split', 'door_split'), true)) {
        $out['structure'] = 'flat_fee';
    }
    if (!in_array($out['commission_mode'], array('artist_fee', 'gross'), true)) {
        $out['commission_mode'] = 'artist_fee';
    }

    return $out;
}