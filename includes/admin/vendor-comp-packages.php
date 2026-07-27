<?php
if (!defined('ABSPATH')) exit;

/**
 * Vendor Comp Packages (Venue-scoped)
 * CPT: vms_comp_package
 *
 * Goal: UI-driven, flexible comp rules; avoid hardcoded assumptions.
 */

add_action('init', function () {

    register_post_type('vms_comp_package', array(
        'labels' => array(
            'name'          => __('Comp Packages', 'backstage-venue-manager'),
            'singular_name' => __('Comp Package', 'backstage-venue-manager'),
            'menu_name'     => __('Comp Packages', 'backstage-venue-manager'),
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => false,
        'menu_icon'     => 'dashicons-money-alt',
        'supports'      => array('title'),
        'has_archive'   => false,
        'rewrite'       => false,
    ));
});

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_comp_package_details',
        __('Comp Package Details', 'backstage-venue-manager'),
        'vms_render_comp_package_meta_box',
        'vms_comp_package',
        'normal',
        'default'
    );
});

if (!function_exists('vms_comp_package_admin_screen_is_target')) {
    function vms_comp_package_admin_screen_is_target($screen): bool
    {
        if (!is_object($screen)) {
            return false;
        }

        if (!in_array((string) ($screen->base ?? ''), array('post', 'post-new'), true)) {
            return false;
        }

        return (string) ($screen->post_type ?? '') === 'vms_comp_package';
    }
}

if (!function_exists('vms_comp_package_admin_enqueue_assets')) {
    function vms_comp_package_admin_enqueue_assets(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!vms_comp_package_admin_screen_is_target($screen)) {
            return;
        }

        $version = function_exists('vms_asset_version')
            ? vms_asset_version()
            : (defined('VMS_VERSION') ? (string) VMS_VERSION : '');

        wp_enqueue_script(
            'vms-compensation-admin',
            VMS_PLUGIN_URL . 'assets/js/vms-compensation-admin.js',
            array(),
            $version,
            true
        );

        wp_localize_script(
            'vms-compensation-admin',
            'vmsCompPackageAdmin',
            array(
                'labels' => array(
                    'basePay' => __('Base Pay', 'backstage-venue-manager'),
                    'flatFeeAmount' => __('Flat Fee Amount', 'backstage-venue-manager'),
                ),
            )
        );
    }
}
add_action('admin_enqueue_scripts', 'vms_comp_package_admin_enqueue_assets', 50);

function vms_render_comp_package_meta_box($post)
{
    wp_nonce_field('vms_save_comp_package', 'vms_comp_package_nonce');

    // Venue scope
    $venue_id = (int) get_post_meta($post->ID, '_vms_venue_id', true);

    // Core type
    $type = (string) get_post_meta($post->ID, '_vms_comp_type', true);
    if (!$type) $type = 'flat'; // flat | flat_plus_split | door_split

    // Flat fee
    $flat_fee = get_post_meta($post->ID, '_vms_flat_fee', true);

    // Door split
    $split_basis = (string) get_post_meta($post->ID, '_vms_split_basis', true);
    if (!$split_basis) $split_basis = 'gross'; // gross | net

    $split_percent_artist = get_post_meta($post->ID, '_vms_split_percent_artist', true); // 0-100

    // Commission (abstract)
    $commission_percent = get_post_meta($post->ID, '_vms_commission_percent', true); // 0-100
    $commission_mode = (string) get_post_meta($post->ID, '_vms_commission_mode', true);
    if (!$commission_mode) $commission_mode = 'none'; // none | add_on_top | deduct_from_artist

    $commission_base = (string) get_post_meta($post->ID, '_vms_commission_base', true);
    if (!$commission_base) $commission_base = 'flat_fee'; // flat_fee | gross | net

    // Optional guardrails
    $min_guarantee = get_post_meta($post->ID, '_vms_min_guarantee', true);
    $cap_amount    = get_post_meta($post->ID, '_vms_cap_amount', true);

    $attendance_bonus_mode = (string) get_post_meta($post->ID, '_vms_attendance_bonus_mode', true);
    $attendance_bonus_start_count = get_post_meta($post->ID, '_vms_attendance_bonus_start_count', true);
    $attendance_bonus_step_size = get_post_meta($post->ID, '_vms_attendance_bonus_step_size', true);
    $attendance_bonus_step_bonus = get_post_meta($post->ID, '_vms_attendance_bonus_step_bonus', true);
    $attendance_bonus_per_ticket_rate = get_post_meta($post->ID, '_vms_attendance_bonus_per_ticket_rate', true);
    $attendance_bonus_max_bonus = get_post_meta($post->ID, '_vms_attendance_bonus_max_bonus', true);

    $notes = (string) get_post_meta($post->ID, '_vms_notes', true);

    // Venue list
    $venues = get_posts(array(
        'post_type'      => 'vms_venue',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    $tour_button = '<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.comp_package.editor.basics" data-vms-tour="comp-package.help-action">' . esc_html__('Start Guided Tour', 'backstage-venue-manager') . '</button>';
    if (function_exists('vms_render_help_button')) {
        $tour_button = vms_render_help_button(array(
            'tour_id' => 'vms.comp_package.editor.basics',
            'anchor' => 'comp-package.help-action',
            'label' => __('Start Guided Tour', 'backstage-venue-manager'),
        ));
    }
    ?>
    <div class="vms-comp-package-admin">
    <p class="description vms-comp-package-help" data-vms-tour="comp-package.help">
        <?php esc_html_e('Need a refresher on package setup?', 'backstage-venue-manager'); ?>
        <?php echo ' ' . $tour_button; ?>
    </p>

    <p data-vms-tour="comp-package.venue">
        <label for="vms_venue_id"><strong><?php esc_html_e('Venue Scope', 'backstage-venue-manager'); ?></strong></label><br />
        <select id="vms_venue_id" name="vms_venue_id" class="vms-comp-package-select-wide">
            <option value="0" <?php selected($venue_id, 0); ?>>
                <?php esc_html_e('— Global Template (optional) —', 'backstage-venue-manager'); ?>
            </option>
            <?php foreach ($venues as $v) : ?>
                <option value="<?php echo esc_attr($v->ID); ?>" <?php selected($venue_id, (int)$v->ID); ?>>
                    <?php echo esc_html($v->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br><span class="description">
            <?php esc_html_e('Typically set to a specific venue. Global templates are optional and can be ignored.', 'backstage-venue-manager'); ?>
        </span>
    </p>

    <hr />

    <p data-vms-tour="comp-package.type">
        <label for="vms_comp_type"><strong><?php esc_html_e('Comp Type', 'backstage-venue-manager'); ?></strong></label><br />
        <select id="vms_comp_type" name="vms_comp_type" class="vms-comp-package-select-wide">
            <option value="flat" <?php selected($type, 'flat'); ?>>
                <?php esc_html_e('Flat Fee', 'backstage-venue-manager'); ?>
            </option>
            <option value="flat_plus_split" <?php selected($type, 'flat_plus_split'); ?>>
                <?php esc_html_e('Flat Fee + Door Split', 'backstage-venue-manager'); ?>
            </option>
            <option value="door_split" <?php selected($type, 'door_split'); ?>>
                <?php esc_html_e('Door Split Only', 'backstage-venue-manager'); ?>
            </option>
            <option value="attendance_bonus" <?php selected($type, 'attendance_bonus'); ?>>
                <?php esc_html_e('Base + Attendance Bonus', 'backstage-venue-manager'); ?>
            </option>
        </select>
    </p>

    <p data-vms-tour="comp-package.base-pay">
        <label for="vms_flat_fee"><strong><span id="vms_flat_fee_label_text"><?php echo esc_html($type === 'attendance_bonus' ? __('Base Pay', 'backstage-venue-manager') : __('Flat Fee Amount', 'backstage-venue-manager')); ?></span></strong></label><br />
        <input type="number" step="0.01" id="vms_flat_fee" name="vms_flat_fee" class="vms-comp-package-input-money"
               value="<?php echo esc_attr($flat_fee); ?>" />
        <br><span id="vms_flat_fee_help" class="description<?php echo ($type === 'attendance_bonus') ? '' : ' vms-hidden'; ?>">
            <?php esc_html_e('The guaranteed amount paid before attendance bonuses are added.', 'backstage-venue-manager'); ?>
        </span>
    </p>

    <div class="vms-comp-package-block" data-show-when="flat_plus_split,door_split">
        <hr />

        <h4 class="vms-comp-package-subhead"><?php esc_html_e('Door Split', 'backstage-venue-manager'); ?></h4>

        <p>
            <label for="vms_split_basis"><strong><?php esc_html_e('Split Basis', 'backstage-venue-manager'); ?></strong></label><br />
            <select id="vms_split_basis" name="vms_split_basis" class="vms-comp-package-select-wide">
                <option value="gross" <?php selected($split_basis, 'gross'); ?>>
                    <?php esc_html_e('Gross (simpler)', 'backstage-venue-manager'); ?>
                </option>
                <option value="net" <?php selected($split_basis, 'net'); ?>>
                    <?php esc_html_e('Net (more accurate)', 'backstage-venue-manager'); ?>
                </option>
            </select>
        </p>

        <p>
            <label for="vms_split_percent_artist"><strong><?php esc_html_e('Artist Split %', 'backstage-venue-manager'); ?></strong></label><br />
            <input type="number" step="0.01" min="0" max="100"
                   id="vms_split_percent_artist" name="vms_split_percent_artist" class="vms-comp-package-input-money"
                   value="<?php echo esc_attr($split_percent_artist); ?>" /> %
            <br><span class="description">
                <?php esc_html_e('Venue split is implicitly (100 - Artist%).', 'backstage-venue-manager'); ?>
            </span>
        </p>
    </div>

    <div class="vms-comp-package-block vms-comp-package-attendance" data-show-when="attendance_bonus" data-vms-tour="comp-package.attendance">
        <hr />

        <h4 class="vms-comp-package-subhead"><?php esc_html_e('Attendance Bonus', 'backstage-venue-manager'); ?></h4>

        <p>
            <label for="vms_attendance_bonus_mode"><strong><?php esc_html_e('Bonus Style', 'backstage-venue-manager'); ?></strong></label><br />
            <select id="vms_attendance_bonus_mode" name="vms_attendance_bonus_mode" class="vms-comp-package-select-wide">
                <option value="" <?php selected($attendance_bonus_mode, ''); ?>><?php esc_html_e('Select bonus style', 'backstage-venue-manager'); ?></option>
                <option value="step" <?php selected($attendance_bonus_mode, 'step'); ?>><?php esc_html_e('Step', 'backstage-venue-manager'); ?></option>
                <option value="continuous" <?php selected($attendance_bonus_mode, 'continuous'); ?>><?php esc_html_e('Continuous', 'backstage-venue-manager'); ?></option>
            </select>
        </p>

        <p>
            <label for="vms_attendance_bonus_start_count"><strong><?php esc_html_e('Bonus Starts After', 'backstage-venue-manager'); ?></strong></label><br />
            <input type="number" step="1" min="0" id="vms_attendance_bonus_start_count" name="vms_attendance_bonus_start_count" class="vms-comp-package-input-money"
                   value="<?php echo esc_attr($attendance_bonus_start_count); ?>" />
            <br><span class="description">
                <?php esc_html_e('No attendance bonus is earned until attendance goes above this number.', 'backstage-venue-manager'); ?>
            </span>
        </p>

        <p class="vms-comp-package-mode-block" data-show-when-mode="step">
            <label for="vms_attendance_bonus_step_size"><strong><?php esc_html_e('Step Size', 'backstage-venue-manager'); ?></strong></label><br />
            <input type="number" step="1" min="1" id="vms_attendance_bonus_step_size" name="vms_attendance_bonus_step_size" class="vms-comp-package-input-money"
                   value="<?php echo esc_attr($attendance_bonus_step_size); ?>" />
            <br><span class="description">
                <?php esc_html_e('How many additional tickets are needed to earn each bonus step.', 'backstage-venue-manager'); ?>
            </span>
        </p>

        <p class="vms-comp-package-mode-block" data-show-when-mode="step">
            <label for="vms_attendance_bonus_step_bonus"><strong><?php esc_html_e('Bonus Per Step', 'backstage-venue-manager'); ?></strong></label><br />
            <input type="number" step="0.01" min="0" id="vms_attendance_bonus_step_bonus" name="vms_attendance_bonus_step_bonus" class="vms-comp-package-input-money"
                   value="<?php echo esc_attr($attendance_bonus_step_bonus); ?>" />
            <br><span class="description">
                <?php esc_html_e('The amount added each time a step is reached.', 'backstage-venue-manager'); ?>
            </span>
        </p>

        <p class="vms-comp-package-mode-block" data-show-when-mode="continuous">
            <label for="vms_attendance_bonus_per_ticket_rate"><strong><?php esc_html_e('Bonus Per Ticket', 'backstage-venue-manager'); ?></strong></label><br />
            <input type="number" step="0.01" min="0" id="vms_attendance_bonus_per_ticket_rate" name="vms_attendance_bonus_per_ticket_rate" class="vms-comp-package-input-money"
                   value="<?php echo esc_attr($attendance_bonus_per_ticket_rate); ?>" />
            <br><span class="description">
                <?php esc_html_e('The amount added for each ticket above the starting count.', 'backstage-venue-manager'); ?>
            </span>
        </p>

        <p>
            <label for="vms_attendance_bonus_max_bonus"><strong><?php esc_html_e('Max Bonus', 'backstage-venue-manager'); ?></strong></label><br />
            <input type="number" step="0.01" min="0" id="vms_attendance_bonus_max_bonus" name="vms_attendance_bonus_max_bonus" class="vms-comp-package-input-money"
                   value="<?php echo esc_attr($attendance_bonus_max_bonus); ?>" />
            <br><span class="description">
                <?php esc_html_e('Optional cap on the total attendance bonus. Leave blank for no cap.', 'backstage-venue-manager'); ?>
            </span>
        </p>
    </div>

    <hr />

    <h4 class="vms-comp-package-subhead"><?php esc_html_e('Agency Commission (Abstract)', 'backstage-venue-manager'); ?></h4>

    <p>
        <label for="vms_commission_mode"><strong><?php esc_html_e('Commission Mode', 'backstage-venue-manager'); ?></strong></label><br />
        <select id="vms_commission_mode" name="vms_commission_mode" class="vms-comp-package-select-wide">
            <option value="none" <?php selected($commission_mode, 'none'); ?>>
                <?php esc_html_e('None', 'backstage-venue-manager'); ?>
            </option>
            <option value="add_on_top" <?php selected($commission_mode, 'add_on_top'); ?>>
                <?php esc_html_e('Add on top (venue pays artist fee + commission)', 'backstage-venue-manager'); ?>
            </option>
            <option value="deduct_from_artist" <?php selected($commission_mode, 'deduct_from_artist'); ?>>
                <?php esc_html_e('Deduct from artist (commission taken from payout)', 'backstage-venue-manager'); ?>
            </option>
        </select>
    </p>

    <p>
        <label for="vms_commission_percent"><strong><?php esc_html_e('Commission %', 'backstage-venue-manager'); ?></strong></label><br />
        <input type="number" step="0.01" min="0" max="100"
               id="vms_commission_percent" name="vms_commission_percent" class="vms-comp-package-input-money"
               value="<?php echo esc_attr($commission_percent); ?>" /> %
    </p>

    <p>
        <label for="vms_commission_base"><strong><?php esc_html_e('Commission Base', 'backstage-venue-manager'); ?></strong></label><br />
        <select id="vms_commission_base" name="vms_commission_base" class="vms-comp-package-select-wide">
            <option value="flat_fee" <?php selected($commission_base, 'flat_fee'); ?>>
                <?php esc_html_e('Flat Fee', 'backstage-venue-manager'); ?>
            </option>
            <option value="gross" <?php selected($commission_base, 'gross'); ?>>
                <?php esc_html_e('Gross', 'backstage-venue-manager'); ?>
            </option>
            <option value="net" <?php selected($commission_base, 'net'); ?>>
                <?php esc_html_e('Net', 'backstage-venue-manager'); ?>
            </option>
        </select>
    </p>

    <hr />

    <h4 class="vms-comp-package-subhead"><?php esc_html_e('Guardrails (Optional)', 'backstage-venue-manager'); ?></h4>

    <p>
        <label for="vms_min_guarantee"><strong><?php esc_html_e('Minimum Guarantee', 'backstage-venue-manager'); ?></strong></label><br />
        <input type="number" step="0.01" id="vms_min_guarantee" name="vms_min_guarantee" class="vms-comp-package-input-money"
               value="<?php echo esc_attr($min_guarantee); ?>" />
    </p>

    <p>
        <label for="vms_cap_amount"><strong><?php esc_html_e('Cap Amount', 'backstage-venue-manager'); ?></strong></label><br />
        <input type="number" step="0.01" id="vms_cap_amount" name="vms_cap_amount" class="vms-comp-package-input-money"
               value="<?php echo esc_attr($cap_amount); ?>" />
    </p>

    <p>
        <label for="vms_notes"><strong><?php esc_html_e('Internal Notes', 'backstage-venue-manager'); ?></strong></label><br />
        <textarea id="vms_notes" name="vms_notes" rows="4" class="vms-comp-package-notes"><?php echo esc_textarea($notes); ?></textarea>
    </p>
    </div>
    <?php
}

add_action('save_post_vms_comp_package', function ($post_id, $post) {

    $nonce = (isset($_POST['vms_comp_package_nonce']) && !is_array($_POST['vms_comp_package_nonce']))
        ? sanitize_text_field(wp_unslash((string) $_POST['vms_comp_package_nonce']))
        : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_save_comp_package')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $venue_id = isset($_POST['vms_venue_id']) ? absint($_POST['vms_venue_id']) : 0;

    $type = isset($_POST['vms_comp_type']) ? sanitize_key((string) wp_unslash($_POST['vms_comp_type'])) : 'flat';
    if (!in_array($type, array('flat', 'flat_plus_split', 'door_split', 'attendance_bonus'), true)) $type = 'flat';

    $parse_nonnegative_float = static function ($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        if (!is_string($value) || $value === '' || !is_numeric($value)) {
            return '';
        }
        return max(0, (float) $value);
    };

    $parse_nonnegative_int = static function ($value, int $min = 0) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        if (!is_string($value) || $value === '' || !is_numeric($value)) {
            return '';
        }
        return max($min, (int) floor((float) $value));
    };

    $flat_fee = $parse_nonnegative_float(vms_request_read_scalar($_POST, 'vms_flat_fee'));

    $split_basis = vms_request_read_key($_POST, 'vms_split_basis');
    if ($split_basis === '') {
        $split_basis = 'gross';
    }
    if (!in_array($split_basis, array('gross', 'net'), true)) $split_basis = 'gross';

    $split_pct_artist = $parse_nonnegative_float(vms_request_read_scalar($_POST, 'vms_split_percent_artist'));
    if ($split_pct_artist !== '' && $split_pct_artist > 100) {
        $split_pct_artist = 100.0;
    }

    $commission_mode = vms_request_read_key($_POST, 'vms_commission_mode');
    if ($commission_mode === '') {
        $commission_mode = 'none';
    }
    if (!in_array($commission_mode, array('none', 'add_on_top', 'deduct_from_artist'), true)) $commission_mode = 'none';

    $commission_pct  = $parse_nonnegative_float(vms_request_read_scalar($_POST, 'vms_commission_percent'));
    if ($commission_pct !== '' && $commission_pct > 100) {
        $commission_pct = 100.0;
    }
    $commission_base = vms_request_read_key($_POST, 'vms_commission_base');
    if ($commission_base === '') {
        $commission_base = 'flat_fee';
    }
    if (!in_array($commission_base, array('flat_fee', 'gross', 'net'), true)) $commission_base = 'flat_fee';

    $min_guarantee = $parse_nonnegative_float(vms_request_read_scalar($_POST, 'vms_min_guarantee'));
    $cap_amount    = $parse_nonnegative_float(vms_request_read_scalar($_POST, 'vms_cap_amount'));

    $attendance_bonus_mode = vms_request_read_key($_POST, 'vms_attendance_bonus_mode');
    if (!in_array($attendance_bonus_mode, array('step', 'continuous'), true)) {
        $attendance_bonus_mode = '';
    }

    $attendance_bonus_start_count = $parse_nonnegative_int(vms_request_read_scalar($_POST, 'vms_attendance_bonus_start_count'));
    $attendance_bonus_step_size = $parse_nonnegative_int(vms_request_read_scalar($_POST, 'vms_attendance_bonus_step_size'), 1);
    $attendance_bonus_step_bonus = $parse_nonnegative_float(vms_request_read_scalar($_POST, 'vms_attendance_bonus_step_bonus'));
    $attendance_bonus_per_ticket_rate = $parse_nonnegative_float(vms_request_read_scalar($_POST, 'vms_attendance_bonus_per_ticket_rate'));
    $attendance_bonus_max_bonus = $parse_nonnegative_float(vms_request_read_scalar($_POST, 'vms_attendance_bonus_max_bonus'));

    $notes = vms_request_read_textarea_field($_POST, 'vms_notes');

    update_post_meta($post_id, '_vms_venue_id', $venue_id);
    update_post_meta($post_id, '_vms_comp_type', $type);
    if ($flat_fee === '') {
        delete_post_meta($post_id, '_vms_flat_fee');
        delete_post_meta($post_id, '_vms_flat_fee_amount');
    } else {
        update_post_meta($post_id, '_vms_flat_fee', (float) $flat_fee);
        update_post_meta($post_id, '_vms_flat_fee_amount', (float) $flat_fee);
    }

    update_post_meta($post_id, '_vms_split_basis', $split_basis);
    if ($split_pct_artist === '') {
        delete_post_meta($post_id, '_vms_split_percent_artist');
        delete_post_meta($post_id, '_vms_door_split_percent');
    } else {
        update_post_meta($post_id, '_vms_split_percent_artist', (float) $split_pct_artist);
        update_post_meta($post_id, '_vms_door_split_percent', (float) $split_pct_artist);
    }

    update_post_meta($post_id, '_vms_commission_mode', $commission_mode);
    if ($commission_pct === '') {
        delete_post_meta($post_id, '_vms_commission_percent');
    } else {
        update_post_meta($post_id, '_vms_commission_percent', (float) $commission_pct);
    }
    update_post_meta($post_id, '_vms_commission_base', $commission_base);

    if ($min_guarantee === '') {
        delete_post_meta($post_id, '_vms_min_guarantee');
    } else {
        update_post_meta($post_id, '_vms_min_guarantee', (float) $min_guarantee);
    }
    if ($cap_amount === '') {
        delete_post_meta($post_id, '_vms_cap_amount');
    } else {
        update_post_meta($post_id, '_vms_cap_amount', (float) $cap_amount);
    }

    update_post_meta($post_id, '_vms_notes', $notes);

    if ($type === 'attendance_bonus') {
        if ($attendance_bonus_mode === '') {
            delete_post_meta($post_id, '_vms_attendance_bonus_mode');
        } else {
            update_post_meta($post_id, '_vms_attendance_bonus_mode', $attendance_bonus_mode);
        }

        if ($attendance_bonus_start_count === '') {
            delete_post_meta($post_id, '_vms_attendance_bonus_start_count');
        } else {
            update_post_meta($post_id, '_vms_attendance_bonus_start_count', (int) $attendance_bonus_start_count);
        }

        if ($attendance_bonus_max_bonus === '') {
            delete_post_meta($post_id, '_vms_attendance_bonus_max_bonus');
        } else {
            update_post_meta($post_id, '_vms_attendance_bonus_max_bonus', (float) $attendance_bonus_max_bonus);
        }

        delete_post_meta($post_id, '_vms_split_percent_artist');
        delete_post_meta($post_id, '_vms_door_split_percent');

        if ($attendance_bonus_mode === 'step') {
            if ($attendance_bonus_step_size === '') {
                delete_post_meta($post_id, '_vms_attendance_bonus_step_size');
            } else {
                update_post_meta($post_id, '_vms_attendance_bonus_step_size', (int) $attendance_bonus_step_size);
            }

            if ($attendance_bonus_step_bonus === '') {
                delete_post_meta($post_id, '_vms_attendance_bonus_step_bonus');
            } else {
                update_post_meta($post_id, '_vms_attendance_bonus_step_bonus', (float) $attendance_bonus_step_bonus);
            }

            delete_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate');
        } elseif ($attendance_bonus_mode === 'continuous') {
            if ($attendance_bonus_per_ticket_rate === '') {
                delete_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate');
            } else {
                update_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate', (float) $attendance_bonus_per_ticket_rate);
            }

            delete_post_meta($post_id, '_vms_attendance_bonus_step_size');
            delete_post_meta($post_id, '_vms_attendance_bonus_step_bonus');
        } else {
            delete_post_meta($post_id, '_vms_attendance_bonus_step_size');
            delete_post_meta($post_id, '_vms_attendance_bonus_step_bonus');
            delete_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate');
        }
    } else {
        delete_post_meta($post_id, '_vms_attendance_bonus_mode');
        delete_post_meta($post_id, '_vms_attendance_bonus_start_count');
        delete_post_meta($post_id, '_vms_attendance_bonus_step_size');
        delete_post_meta($post_id, '_vms_attendance_bonus_step_bonus');
        delete_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate');
        delete_post_meta($post_id, '_vms_attendance_bonus_max_bonus');
    }

}, 10, 2);
