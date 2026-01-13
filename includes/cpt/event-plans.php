<?php
/**
 * VMS Event Plans — Admin + Publishing
 *
 * Notes:
 * - This file intentionally contains ONLY Event Plan related logic.
 * - It assumes some helper functions exist elsewhere in the plugin (ex: vms_get_current_venue_id()).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register CPT: vms_event_plan
 */
add_action('init', 'vms_register_event_plan_cpt');
function vms_register_event_plan_cpt(): void
{
    register_post_type('vms_event_plan', array(
        'labels' => array(
            'name'          => __('Event Plans', 'vms'),
            'singular_name' => __('Event Plan', 'vms'),
            'menu_name'     => __('Event Plans', 'vms'),
        ),
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => 'vms-season-board',
        'menu_icon'       => 'dashicons-calendar-alt',
        'supports'        => array('title', 'editor', 'thumbnail'),
        'capability_type' => 'post',
        'has_archive'     => false,
        'rewrite'         => false,
    ));
}

/**
 * Admin functionality for VMS Event Plans.
 */
class VMS_Admin_Event_Plans
{
    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        add_action('save_post_vms_event_plan', array($this, 'save_event_plan_meta'), 10, 2);

        // AJAX: Venue default comp (by date)
        add_action('wp_ajax_vms_get_venue_comp_defaults', array($this, 'ajax_get_venue_comp_defaults'));
    }

    public function register_meta_boxes(): void
    {
        add_meta_box(
            'vms_event_plan_details',
            __('Event Plan Details', 'vms'),
            array($this, 'render_event_plan_details_meta_box'),
            'vms_event_plan',
            'normal',
            'default'
        );
    }

    public function ajax_get_venue_comp_defaults(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not allowed'), 403);
        }

        $venue_id   = isset($_POST['venue_id']) ? absint($_POST['venue_id']) : 0;
        $event_date = isset($_POST['event_date']) ? sanitize_text_field(wp_unslash($_POST['event_date'])) : '';

        if ($venue_id <= 0 || $event_date === '') {
            wp_send_json_success(array('row' => array()));
        }

        if (!function_exists('vms_get_venue_default_comp_for_date')) {
            wp_send_json_error(array('message' => 'Venue default helper not loaded'), 500);
        }

        $row = vms_get_venue_default_comp_for_date($venue_id, $event_date);

        foreach (array('flat_fee_amount', 'door_split_percent', 'commission_percent') as $k) {
            if (isset($row[$k]) && $row[$k] !== '') {
                $row[$k] = (string) $row[$k];
            }
        }

        wp_send_json_success(array('row' => $row));
    }

    /**
     * Render meta box
     */
    public function render_event_plan_details_meta_box(WP_Post $post): void
    {
        wp_nonce_field('vms_save_event_plan_details', 'vms_event_plan_details_nonce');

        // ----------------------------
        // Load core meta
        // ----------------------------
        $event_date     = (string) get_post_meta($post->ID, '_vms_event_date', true);
        $start_time     = (string) get_post_meta($post->ID, '_vms_start_time', true);
        $end_time       = (string) get_post_meta($post->ID, '_vms_end_time', true);
        $location_label = (string) get_post_meta($post->ID, '_vms_location_label', true);
        $agenda_text    = (string) get_post_meta($post->ID, '_vms_agenda_text', true);

        // Venue: saved vs UI default (important for "packages show on first load")
        $venue_id_saved = (int) get_post_meta($post->ID, '_vms_venue_id', true);

        $venue_id_ui = 0;
        if ($venue_id_saved <= 0 && function_exists('vms_get_current_venue_id')) {
            $venue_id_ui = (int) vms_get_current_venue_id();
        }

        // Effective venue for rendering: show packages immediately on new plan
        $venue_id_effective = $venue_id_saved > 0 ? $venue_id_saved : $venue_id_ui;

        // Default times (use effective venue)
        if ($venue_id_effective > 0 && (empty($start_time) || empty($end_time)) && function_exists('vms_get_venue_default_times')) {
            $defaults = (array) vms_get_venue_default_times($venue_id_effective);

            if (empty($start_time) && !empty($defaults['start'])) {
                $start_time = (string) $defaults['start'];
            }
            if (empty($end_time)) {
                if (!empty($defaults['end'])) {
                    $end_time = (string) $defaults['end'];
                } elseif (!empty($start_time) && !empty($defaults['dur']) && function_exists('vms_time_add_minutes')) {
                    $end_time = (string) vms_time_add_minutes($start_time, (int) $defaults['dur']);
                }
            }
        }
        if (empty($start_time)) $start_time = '19:00';
        if (empty($end_time))   $end_time   = '21:00';

        $auto_title = (string) get_post_meta($post->ID, '_vms_auto_title', true);
        if ($auto_title === '') $auto_title = '1';

        $auto_comp = (string) get_post_meta($post->ID, '_vms_auto_comp', true);
        if ($auto_comp === '') $auto_comp = '1';

        $auto_comp_venue = (string) get_post_meta($post->ID, '_vms_auto_comp_venue', true);
        if ($auto_comp_venue === '') $auto_comp_venue = '0';

        // Draft pay fields
        $comp_structure      = (string) get_post_meta($post->ID, '_vms_comp_structure', true);
        if ($comp_structure === '') $comp_structure = 'flat_fee';

        $flat_fee_amount     = get_post_meta($post->ID, '_vms_flat_fee_amount', true);
        $door_split_percent  = get_post_meta($post->ID, '_vms_door_split_percent', true);

        // Current package selection
        $current_pkg_id = (int) get_post_meta($post->ID, '_vms_comp_package_id', true);

        // Snapshot (locked pay)
        $snapshot = get_post_meta($post->ID, '_vms_comp_snapshot', true);
        if (!is_array($snapshot)) $snapshot = array();

        $needs_snapshot = (get_post_meta($post->ID, '_vms_comp_needs_snapshot', true) === '1');

        $current_hash  = function_exists('vms_comp_hash_for_plan') ? (string) vms_comp_hash_for_plan((int)$post->ID) : '';
        $snapshot_hash = isset($snapshot['comp_hash']) ? (string) $snapshot['comp_hash'] : '';

        $out_of_sync = false;
        if (!empty($snapshot)) {
            if ($snapshot_hash !== '' && $current_hash !== '' && $snapshot_hash !== $current_hash) $out_of_sync = true;
            if ($needs_snapshot) $out_of_sync = true;
        }

        // Load packages for effective venue (+ global)
        $packages = array();
        if ($venue_id_effective > 0 && function_exists('vms_get_comp_packages_for_venue')) {
            $packages = (array) vms_get_comp_packages_for_venue($venue_id_effective, true);
        }

        // Plan status
        $plan_status = (string) get_post_meta($post->ID, '_vms_event_plan_status', true);
        if ($plan_status === '') $plan_status = 'draft';

        // ----------------------------
        // Data for dropdowns
        // ----------------------------
        $venues = get_posts(array(
            'post_type'      => 'vms_venue',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $bands = get_posts(array(
            'post_type'      => 'vms_vendor',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $selected_band_id = (int) get_post_meta($post->ID, '_vms_band_vendor_id', true);

        // Tickets/addons
        $ga_price = get_post_meta($post->ID, '_vms_price_ga', true);
        if ($ga_price === '' || $ga_price === null) $ga_price = '20';

        $enable_tables   = (string) get_post_meta($post->ID, '_vms_enable_tables', true);
        if ($enable_tables === '') $enable_tables = '1';

        $enable_firepits = (string) get_post_meta($post->ID, '_vms_enable_firepits', true);
        if ($enable_firepits === '') $enable_firepits = '1';

        $enable_pools    = (string) get_post_meta($post->ID, '_vms_enable_pools', true);
        if ($enable_pools === '') $enable_pools = '0';

        $table_count   = get_post_meta($post->ID, '_vms_table_count', true);
        if ($table_count === '' || $table_count === null) $table_count = '6';

        $firepit_count = get_post_meta($post->ID, '_vms_firepit_count', true);
        if ($firepit_count === '' || $firepit_count === null) $firepit_count = '6';

        $table_price   = get_post_meta($post->ID, '_vms_price_table', true);
        if ($table_price === '' || $table_price === null) $table_price = '30';

        $firepit_price = get_post_meta($post->ID, '_vms_price_firepit', true);
        if ($firepit_price === '' || $firepit_price === null) $firepit_price = '30';

        $pool_price    = get_post_meta($post->ID, '_vms_price_pool', true);
        if ($pool_price === '' || $pool_price === null) $pool_price = '10';

        $firepit_min_tickets = get_post_meta($post->ID, '_vms_min_tickets_per_firepit', true);
        if ($firepit_min_tickets === '' || $firepit_min_tickets === null) $firepit_min_tickets = '2';

        $table_min_tickets = get_post_meta($post->ID, '_vms_min_tickets_per_table', true);
        if ($table_min_tickets === '' || $table_min_tickets === null) $table_min_tickets = '2';

        // Staff UI
        $roles = get_terms(array(
            'taxonomy'   => 'vms_staff_role',
            'hide_empty' => false,
        ));
        $assignments = get_post_meta($post->ID, '_vms_staff_assignments', true);
        if (!is_array($assignments)) $assignments = array();

        // ----------------------------
        // Render UI
        // ----------------------------
        ?>
        <hr />
        <h4><?php esc_html_e('Agenda / Event Description', 'vms'); ?></h4>
        <p>
            <textarea name="vms_agenda_text" id="vms_agenda_text" rows="6" style="width:100%;"><?php echo esc_textarea($agenda_text); ?></textarea>
        </p>
        <p class="description"><?php esc_html_e('This text will appear publicly on the event page in The Events Calendar.', 'vms'); ?></p>

        <p>
            <label for="vms_event_date"><strong><?php esc_html_e('Event Date', 'vms'); ?></strong></label><br />
            <input type="date" id="vms_event_date" name="vms_event_date" value="<?php echo esc_attr($event_date); ?>" />
        </p>

        <p>
            <label for="vms_venue_id"><strong><?php esc_html_e('Venue', 'vms'); ?></strong></label><br />
            <select id="vms_venue_id" name="vms_venue_id" style="min-width:260px;" required>
                <option value=""><?php esc_html_e('-- Select a Venue --', 'vms'); ?></option>
                <?php foreach ($venues as $venue): ?>
                    <option value="<?php echo esc_attr($venue->ID); ?>" <?php selected($venue_id_effective, $venue->ID); ?>>
                        <?php echo esc_html($venue->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br /><span class="description"><?php esc_html_e('Required. This scopes the event plan to a specific venue.', 'vms'); ?></span>
        </p>

        <?php
        // Holiday panel
        $holiday = null;
        if ($venue_id_effective > 0 && $event_date && function_exists('vms_get_venue_holiday_for_date')) {
            $holiday = vms_get_venue_holiday_for_date($venue_id_effective, $event_date);
        }

        echo '<hr />';
        echo '<h4>' . esc_html__('Holiday', 'vms') . '</h4>';
        echo '<div style="max-width:720px;padding:12px 14px;border:1px solid #dcdcde;border-radius:12px;background:#fff;">';

        if ($venue_id_effective <= 0 || !$event_date) {
            echo '<p class="description" style="margin:0;">' . esc_html__('Select a Venue and Event Date to see holiday status.', 'vms') . '</p>';
        } elseif (!$holiday) {
            echo '<p class="description" style="margin:0;">' . esc_html__('No holiday is configured for this venue on the selected date.', 'vms') . '</p>';
            echo '<p class="description" style="margin:8px 0 0;">' . esc_html__('Holiday pay is role-dependent and will apply automatically once holidays are configured.', 'vms') . '</p>';
        } else {
            $badge_style = (($holiday['status'] ?? '') === 'closed')
                ? 'display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;'
                : 'display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;';

            echo '<p style="margin:0 0 8px;">';
            echo '<span style="' . esc_attr($badge_style) . '">';
            echo (($holiday['status'] ?? '') === 'closed') ? esc_html__('CLOSED', 'vms') : esc_html__('OPEN', 'vms');
            echo '</span>';

            $name = trim((string)($holiday['name'] ?? ''));
            if ($name !== '') {
                echo ' <strong style="margin-left:8px;">' . esc_html($name) . '</strong>';
            }
            echo '</p>';

            if (($holiday['status'] ?? '') === 'closed') {
                echo '<p class="description" style="margin:0;">' . esc_html__('This venue is marked CLOSED on this holiday. This Event Plan cannot be marked READY or Published.', 'vms') . '</p>';
            } else {
                echo '<p class="description" style="margin:0;">' . esc_html__('Holiday pay/hours are role-dependent and will be applied automatically (once holiday rules are configured).', 'vms') . '</p>';
            }
        }

        echo '</div>';
        ?>

        <p>
            <label for="vms_start_time"><strong><?php esc_html_e('Start Time', 'vms'); ?></strong></label><br />
            <input type="time" id="vms_start_time" name="vms_start_time" value="<?php echo esc_attr($start_time); ?>" />
        </p>

        <p>
            <label for="vms_end_time"><strong><?php esc_html_e('End Time', 'vms'); ?></strong></label><br />
            <input type="time" id="vms_end_time" name="vms_end_time" value="<?php echo esc_attr($end_time); ?>" />
        </p>

        <p>
            <label for="vms_band_vendor_id"><strong><?php esc_html_e('Band / Headliner', 'vms'); ?></strong></label><br />
            <select id="vms_band_vendor_id" name="vms_band_vendor_id" style="min-width:260px;">
                <option value=""><?php esc_html_e('-- Select a Band --', 'vms'); ?></option>
                <?php foreach ($bands as $band): ?>
                    <?php
                    $label = (string) $band->post_title;

                    if ($event_date && function_exists('vms_get_vendor_availability_for_date')) {
                        $availability = vms_get_vendor_availability_for_date((int)$band->ID, $event_date);
                        if ($availability === 'available') $label .= ' [✓]';
                        elseif ($availability === 'unavailable') $label .= ' [✖]';
                        else $label .= ' [?]';
                    }

                    $missing = function_exists('vms_vendor_tax_profile_missing_items')
                        ? (array) vms_vendor_tax_profile_missing_items((int)$band->ID)
                        : array();

                    $tax_ok = empty($missing);
                    $label .= $tax_ok ? ' [T✓]' : ' [T⚠]';

                    $missing_str = $tax_ok ? '' : implode(' | ', $missing);
                    ?>
                    <option
                        value="<?php echo esc_attr($band->ID); ?>"
                        <?php selected($selected_band_id, $band->ID); ?>
                        data-tax-ok="<?php echo $tax_ok ? '1' : '0'; ?>"
                        data-tax-missing="<?php echo esc_attr($missing_str); ?>">
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($event_date): ?>
                <?php $ts = strtotime($event_date); $nice = $ts ? date_i18n('M j, Y', $ts) : $event_date; ?>
                <br />
                <span class="description">
                    <?php
                    printf(
                        esc_html__('Availability for %s: [✓] Available, [✖] Not Available, [?] Unknown', 'vms'),
                        esc_html($nice)
                    );
                    ?>
                </span>
                <div id="vms-tax-status" style="margin-top:10px;"></div>
            <?php else: ?>
                <br />
                <span class="description"><?php esc_html_e('Set the Event Date to see per-band availability hints here.', 'vms'); ?></span>
                <div id="vms-tax-status" style="margin-top:10px;"></div>
            <?php endif; ?>
        </p>

        <p>
            <label>
                <input type="checkbox" name="vms_auto_title" value="1" <?php checked($auto_title, '1'); ?> />
                <?php esc_html_e('Auto-update title to Band — Date', 'vms'); ?>
            </label>
        </p>

        <p id="vms_title_preview_wrap" style="margin-top:6px;">
            <span class="description">
                <strong><?php esc_html_e('Title preview:', 'vms'); ?></strong>
                <span id="vms_title_preview_text"><?php echo esc_html(get_the_title($post->ID)); ?></span>
            </span>
        </p>

        <p>
            <label for="vms_location_label"><strong><?php esc_html_e('Location / Resource', 'vms'); ?></strong></label><br />
            <input type="text" id="vms_location_label" name="vms_location_label" class="regular-text" value="<?php echo esc_attr($location_label); ?>" />
            <br /><span class="description"><?php esc_html_e('Example: Main Stage, Patio, Food Truck Row, etc.', 'vms'); ?></span>
        </p>

        <hr />
        <h4 id="vms-compensation"><?php esc_html_e('Compensation', 'vms'); ?></h4>

        <div style="max-width:860px;margin:12px 0 16px;padding:14px 16px;border-radius:14px;background:#f5faff;border:1px solid #cce3ff;">
            <strong><?php esc_html_e('How this works:', 'vms'); ?></strong>
            <ol style="margin:8px 0 0 18px;">
                <li><strong><?php esc_html_e('Draft Pay', 'vms'); ?></strong> <?php esc_html_e('is what you’re editing.', 'vms'); ?></li>
                <li><strong><?php esc_html_e('Locked Pay', 'vms'); ?></strong> <?php esc_html_e('(Used for payout) is the agreed terms for THIS event.', 'vms'); ?></li>
                <li><?php esc_html_e('Use the buttons to fill Draft Pay, review/edit, then lock it to protect this event from future default changes.', 'vms'); ?></li>
            </ol>
        </div>

        <?php if (!empty($snapshot)): ?>
            <div style="max-width:860px;margin:0 0 14px;padding:14px 16px;border-radius:14px;background:#f3f4f6;border:1px solid #d1d5db;">
                <strong><?php esc_html_e('Locked Pay (Used for payout)', 'vms'); ?></strong><br>
                <div style="margin-top:6px;">
                    <?php
                    $summary = function_exists('vms_snapshot_summary_line') ? (string) vms_snapshot_summary_line($snapshot) : '';
                    echo esc_html($summary !== '' ? $summary : __('Pay structure locked, but no values recorded.', 'vms'));
                    ?>
                </div>

                <?php if (!empty($snapshot['package_title'])): ?>
                    <div class="description" style="margin-top:6px;">
                        <strong><?php esc_html_e('Package:', 'vms'); ?></strong> <?php echo esc_html((string)$snapshot['package_title']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($snapshot['applied_at'])): ?>
                    <div class="description" style="margin-top:4px;">
                        <strong><?php esc_html_e('Applied:', 'vms'); ?></strong>
                        <?php echo esc_html(date_i18n('D M j, Y g:i A', strtotime((string)$snapshot['applied_at']))); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($out_of_sync): ?>
            <div style="max-width:860px;margin:0 0 14px;padding:12px 14px;border-radius:14px;background:#fffbeb;border:1px solid #fed7aa;">
                <strong style="color:#92400e;">⚠️ <?php esc_html_e('Draft Pay differs from Locked Snapshot', 'vms'); ?></strong><br>
                <span class="description"><?php esc_html_e('Something changed since the snapshot was saved. If the new values are correct, lock again to refresh the snapshot.', 'vms'); ?></span>
            </div>
        <?php endif; ?>

        <div style="max-width:860px;margin:0 0 14px;padding:14px 16px;border-radius:14px;background:#fefce8;border:1px solid #fde68a;">
            <strong><?php esc_html_e('Fill Draft Pay from Defaults or a Package', 'vms'); ?></strong>

            <p class="description" style="margin-top:6px;">
                <?php esc_html_e('These buttons update Draft Pay fields. Locked Pay will not change unless you lock draft pay or apply a package that writes a snapshot.', 'vms'); ?>
            </p>

            <p style="margin:10px 0 0;">
                <label>
                    <input type="checkbox" id="vms_auto_comp_venue" name="vms_auto_comp_venue" value="1" <?php checked($auto_comp_venue, '1'); ?> />
                    <?php esc_html_e('Auto-fill Draft Pay from Venue Defaults when Venue + Date are set', 'vms'); ?>
                </label>
                <br />
                <span id="vms-venue-defaults-hint" class="description"></span>
            </p>

            <?php if ($venue_id_effective <= 0): ?>
                <p class="description" style="margin:10px 0 0;">
                    <em><?php esc_html_e('Select a Venue above to load packages.', 'vms'); ?></em>
                </p>
            <?php else: ?>
                <?php if (empty($packages)): ?>
                    <div class="notice notice-info inline" style="margin:10px 0 12px;">
                        <p>
                            <strong><?php esc_html_e('No Comp Packages are available for the selected venue yet.', 'vms'); ?></strong><br>
                            <?php
                            if ($venue_id_saved <= 0) {
                                // This is the key improvement: packages should usually still show now (because we query by effective venue),
                                // but if they’re truly empty, we give an actionable message without requiring a "mystery save".
                                esc_html_e('If you expected packages, confirm they exist for this venue (or are global).', 'vms');
                            } else {
                                esc_html_e('Create packages under Comp Packages, then come back here.', 'vms');
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>

                <p style="margin-top:10px;">
                    <label for="vms_comp_package_id"><strong><?php esc_html_e('Comp Package', 'vms'); ?></strong></label><br />
                    <select id="vms_comp_package_id" name="vms_comp_package_id" style="min-width:360px;">
                        <option value=""><?php esc_html_e('-- Select a Package --', 'vms'); ?></option>
                        <?php foreach ($packages as $pkg): ?>
                            <?php if (!is_object($pkg) || empty($pkg->ID)) continue; ?>
                            <option value="<?php echo esc_attr($pkg->ID); ?>" <?php selected($current_pkg_id, $pkg->ID); ?>>
                                <?php echo esc_html((string)$pkg->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <br>
                    <span class="description"><?php esc_html_e('Pick a package, then click “Apply Package” below.', 'vms'); ?></span>
                </p>

                <p style="margin-top:10px;">
                    <button type="submit" name="vms_event_plan_action" value="apply_venue_defaults" class="button">
                        <?php esc_html_e('Apply Venue Defaults', 'vms'); ?>
                    </button>

                    <button type="submit" name="vms_event_plan_action" value="apply_band_defaults" class="button">
                        <?php esc_html_e('Apply Band Defaults', 'vms'); ?>
                    </button>

                    <button type="submit" name="vms_event_plan_action" value="apply_comp_package" class="button button-primary">
                        <?php esc_html_e('Apply Package', 'vms'); ?>
                    </button>
                </p>

                <p class="description" style="margin-top:6px;">
                    <?php esc_html_e('Most common flow:', 'vms'); ?> <strong><?php esc_html_e('Apply Venue Defaults → review → Apply Package', 'vms'); ?></strong>
                </p>
            <?php endif; ?>
        </div>

        <div style="max-width:860px;margin:0 0 14px;padding:14px 16px;border-radius:14px;background:#fff;border:1px solid #e5e7eb;">
            <strong><?php esc_html_e('Draft Pay (Editable)', 'vms'); ?></strong>
            <p class="description" style="margin-top:6px;"><?php esc_html_e('These are the fields the buttons fill. You can override manually any time.', 'vms'); ?></p>

            <p>
                <label for="vms_comp_structure"><strong><?php esc_html_e('Structure', 'vms'); ?></strong></label><br />
                <select id="vms_comp_structure" name="vms_comp_structure">
                    <option value="flat_fee" <?php selected($comp_structure, 'flat_fee'); ?>><?php esc_html_e('Flat Fee', 'vms'); ?></option>
                    <option value="door_split" <?php selected($comp_structure, 'door_split'); ?>><?php esc_html_e('Door Split', 'vms'); ?></option>
                    <option value="flat_fee_door_split" <?php selected($comp_structure, 'flat_fee_door_split'); ?>><?php esc_html_e('Flat Fee + Door Split', 'vms'); ?></option>
                </select>
            </p>

            <p>
                <label for="vms_flat_fee_amount"><strong><?php esc_html_e('Flat Fee Amount', 'vms'); ?></strong></label><br />
                <input type="number" id="vms_flat_fee_amount" name="vms_flat_fee_amount" style="width:180px;" value="<?php echo esc_attr($flat_fee_amount); ?>" />
            </p>

            <p>
                <label for="vms_door_split_percent"><strong><?php esc_html_e('Door Split Percentage', 'vms'); ?></strong></label><br />
                <input type="number" id="vms_door_split_percent" name="vms_door_split_percent" style="width:180px;" value="<?php echo esc_attr($door_split_percent); ?>" /> %
            </p>

            <p style="margin-top:10px;">
                <button type="submit" name="vms_event_plan_action" value="lock_draft_pay" class="button button-primary">
                    🔒 <?php esc_html_e('Lock Draft Pay for This Event', 'vms'); ?>
                </button>
            </p>
            <p class="description" style="margin-top:-4px;">
                <?php esc_html_e('Locks the current Draft Pay into the plan’s snapshot (Locked Pay) so this event is protected from future default changes.', 'vms'); ?>
            </p>
        </div>

        <hr />
        <p class="description"><?php esc_html_e('Featured Image: use the standard WordPress featured image box for this event’s banner/hero.', 'vms'); ?></p>

        <hr />
        <h4><?php esc_html_e('Tickets & Add-ons (Woo Products)', 'vms'); ?></h4>

        <p class="description"><?php esc_html_e('These settings control which WooCommerce products are generated/updated when you Publish.', 'vms'); ?></p>

        <p>
            <label for="vms_price_ga"><strong><?php esc_html_e('GA Ticket Price', 'vms'); ?></strong></label><br />
            <input type="number" step="0.01" min="0" id="vms_price_ga" name="vms_price_ga" style="width:140px;" value="<?php echo esc_attr($ga_price); ?>" />
        </p>

        <p>
            <label>
                <input type="checkbox" name="vms_enable_tables" value="1" <?php checked($enable_tables, '1'); ?> />
                <?php esc_html_e('Enable Tables', 'vms'); ?>
            </label>
            &nbsp;&nbsp;
            <label><?php esc_html_e('Count', 'vms'); ?>
                <input type="number" min="0" step="1" name="vms_table_count" style="width:80px;" value="<?php echo esc_attr($table_count); ?>" />
            </label>
            &nbsp;&nbsp;
            <label><?php esc_html_e('Price', 'vms'); ?>
                <input type="number" step="0.01" min="0" name="vms_price_table" style="width:100px;" value="<?php echo esc_attr($table_price); ?>" />
            </label>
            &nbsp;&nbsp;
            <label><?php esc_html_e('Min Tickets', 'vms'); ?>
                <input type="number" min="0" step="1" name="vms_min_tickets_per_table" style="width:80px;" value="<?php echo esc_attr($table_min_tickets); ?>" />
            </label>
        </p>

        <p>
            <label>
                <input type="checkbox" name="vms_enable_firepits" value="1" <?php checked($enable_firepits, '1'); ?> />
                <?php esc_html_e('Enable Fire Pits', 'vms'); ?>
            </label>
            &nbsp;&nbsp;
            <label><?php esc_html_e('Count', 'vms'); ?>
                <input type="number" min="0" step="1" name="vms_firepit_count" style="width:80px;" value="<?php echo esc_attr($firepit_count); ?>" />
            </label>
            &nbsp;&nbsp;
            <label><?php esc_html_e('Price', 'vms'); ?>
                <input type="number" step="0.01" min="0" name="vms_price_firepit" style="width:100px;" value="<?php echo esc_attr($firepit_price); ?>" />
            </label>
            &nbsp;&nbsp;
            <label><?php esc_html_e('Min Tickets', 'vms'); ?>
                <input type="number" min="0" step="1" name="vms_min_tickets_per_firepit" style="width:80px;" value="<?php echo esc_attr($firepit_min_tickets); ?>" />
            </label>
        </p>

        <p>
            <label>
                <input type="checkbox" name="vms_enable_pools" value="1" <?php checked($enable_pools, '1'); ?> />
                <?php esc_html_e('Enable Kiddie Pools', 'vms'); ?>
            </label>
            &nbsp;&nbsp;
            <label><?php esc_html_e('Price', 'vms'); ?>
                <input type="number" step="0.01" min="0" name="vms_price_pool" style="width:100px;" value="<?php echo esc_attr($pool_price); ?>" />
            </label>
            <br />
            <span class="description"><?php esc_html_e('Pools have no ticket minimum (per your rules).', 'vms'); ?></span>
        </p>

        <hr />
        <h4><?php esc_html_e('Labor / Staff', 'vms'); ?></h4>
        <p class="description"><?php esc_html_e('Assign staff contractors to this event by role. Tax Profile is required to mark Ready.', 'vms'); ?></p>

        <?php if (empty($roles) || is_wp_error($roles)): ?>
            <p><em><?php esc_html_e('No staff roles exist yet. Add roles under Staff → Roles.', 'vms'); ?></em></p>
        <?php else: ?>
            <?php foreach ($roles as $role): ?>
                <?php
                $role_id   = (int) $role->term_id;
                $saved_ids = (isset($assignments[$role_id]) && is_array($assignments[$role_id]))
                    ? array_map('intval', $assignments[$role_id])
                    : array();

                $staff_in_role = get_posts(array(
                    'post_type'      => 'vms_staff',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'tax_query'      => array(
                        array(
                            'taxonomy' => 'vms_staff_role',
                            'field'    => 'term_id',
                            'terms'    => array($role_id),
                        )
                    ),
                ));
                ?>

                <p style="margin:12px 0 6px;"><strong><?php echo esc_html($role->name); ?></strong></p>

                <?php if (empty($staff_in_role)): ?>
                    <p class="description" style="margin-top:0;"><?php esc_html_e('No staff found for this role yet.', 'vms'); ?></p>
                <?php else: ?>
                    <select name="vms_staff_assignments[<?php echo esc_attr($role_id); ?>][]" multiple style="min-width:360px; min-height:90px;">
                        <?php foreach ($staff_in_role as $s): ?>
                            <?php
                            $sid = (int) $s->ID;
                            $missing = function_exists('vms_vendor_tax_profile_missing_items') ? (array) vms_vendor_tax_profile_missing_items($sid) : array();
                            $hint = empty($missing) ? ' ✓' : ' ⚠';
                            ?>
                            <option value="<?php echo esc_attr($sid); ?>" <?php echo in_array($sid, $saved_ids, true) ? 'selected' : ''; ?>>
                                <?php echo esc_html((string)$s->post_title . $hint); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e('Tip: “✓” means Tax Profile complete. “⚠” means missing items.', 'vms'); ?>
                    </p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <hr />
        <h4><?php esc_html_e('Event Plan Status & Workflow', 'vms'); ?></h4>

        <p>
            <strong><?php esc_html_e('Status:', 'vms'); ?></strong>
            <?php echo esc_html(strtoupper($plan_status)); ?>
        </p>

        <p>
            <button type="submit" name="vms_event_plan_action" value="save_draft" class="button">
                <?php esc_html_e('Save Draft', 'vms'); ?>
            </button>

            <button type="submit" name="vms_event_plan_action" value="mark_ready" class="button button-secondary">
                <?php esc_html_e('Mark Ready', 'vms'); ?>
            </button>

            <button type="submit" name="vms_event_plan_action" value="publish_now" class="button button-primary"
                <?php echo ($plan_status === 'ready' || $plan_status === 'published') ? '' : ' disabled="disabled"'; ?>>
                <?php esc_html_e('Publish Now', 'vms'); ?>
            </button>
        </p>

        <p class="description"><?php esc_html_e('“Publish Now” is only available once the plan is Ready.', 'vms'); ?></p>

        <style>
            #vms-tax-status .vms-tax-box{border:1px solid #dcdcde;border-radius:12px;padding:10px 12px;background:#fff;max-width:720px;}
            #vms-tax-status .ok{border-color:#a7f3d0;background:#ecfdf5;}
            #vms-tax-status .bad{border-color:#fed7aa;background:#fffbeb;}
            #vms-tax-status .title{font-weight:800;margin-bottom:6px;}
            #vms-tax-status .muted{color:#646970;font-size:12px;}
        </style>

        <script>
        (function(){
            const bandSel = document.getElementById('vms_band_vendor_id');
            const wrap = document.getElementById('vms-tax-status');
            if (!bandSel || !wrap) return;

            function escapeHtml(str){
                return String(str).replace(/[&<>"']/g, s => ({
                    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
                }[s]));
            }

            function render(){
                const opt = bandSel.options[bandSel.selectedIndex];
                if (!opt || !opt.value){
                    wrap.innerHTML =
                        '<div class="vms-tax-box">' +
                            '<div class="title">Tax Profile</div>' +
                            '<div class="muted">Select a band to see tax requirements.</div>' +
                        '</div>';
                    return;
                }

                const ok = opt.getAttribute('data-tax-ok') === '1';
                const missing = (opt.getAttribute('data-tax-missing') || '').trim();

                if (ok){
                    wrap.innerHTML =
                        '<div class="vms-tax-box ok">' +
                            '<div class="title">✅ Tax Profile Complete</div>' +
                            '<div class="muted">This vendor is eligible for Ready/Publish (tax-wise).</div>' +
                        '</div>';
                } else {
                    wrap.innerHTML =
                        '<div class="vms-tax-box bad">' +
                            '<div class="title">⚠️ Tax Profile Incomplete</div>' +
                            '<div class="muted"><strong>Missing:</strong> ' + escapeHtml(missing || '—') + '</div>' +
                            '<div class="muted" style="margin-top:6px;">This will block “Mark Ready” until resolved.</div>' +
                        '</div>';
                }
            }

            bandSel.addEventListener('change', render);
            render();
        })();
        </script>

        <script>
        (function() {
            const bandSel = document.getElementById('vms_band_vendor_id');
            const dateInput = document.getElementById('vms_event_date');
            const autoTitle = document.querySelector('input[name="vms_auto_title"]');
            const previewEl = document.getElementById('vms_title_preview_text');

            const wpTitleInput =
                document.getElementById('title') ||
                document.querySelector('textarea.editor-post-title__input') ||
                document.querySelector('h1.editor-post-title__input');

            function formatDate(yyyy_mm_dd) {
                if (!yyyy_mm_dd) return '';
                const parts = yyyy_mm_dd.split('-');
                if (parts.length !== 3) return yyyy_mm_dd;
                const y = parseInt(parts[0], 10), m = parseInt(parts[1], 10) - 1, d = parseInt(parts[2], 10);
                const dt = new Date(y, m, d);
                if (isNaN(dt.getTime())) return yyyy_mm_dd;
                return dt.toLocaleDateString(undefined, { year:'numeric', month:'long', day:'numeric' });
            }

            function getBandName() {
                if (!bandSel) return '';
                const opt = bandSel.options[bandSel.selectedIndex];
                if (!opt) return '';
                return (opt.text || '').replace(/\s*\[\s*[^\]]+\s*\]\s*$/, '').trim();
            }

            function buildTitle() {
                const band = getBandName();
                const date = formatDate(dateInput ? dateInput.value : '');
                if (!band || !date) return '';
                return `${band} — ${date}`;
            }

            function updatePreview() {
                if (!previewEl) return;
                const isAuto = autoTitle ? autoTitle.checked : true;
                if (!isAuto) { previewEl.textContent = '(auto-title disabled)'; return; }
                const t = buildTitle();
                previewEl.textContent = t || '(select Band + Date to preview)';
            }

            function updateWpTitleBox() {
                const isAuto = autoTitle ? autoTitle.checked : true;
                if (!isAuto) return;
                const t = buildTitle();
                if (!t) return;

                if (wpTitleInput) {
                    const current = (wpTitleInput.value || wpTitleInput.textContent || '').trim();
                    if (!current || current.toLowerCase() === 'auto draft') {
                        if ('value' in wpTitleInput) wpTitleInput.value = t;
                        else wpTitleInput.textContent = t;
                    }
                }
            }

            function onChange() { updatePreview(); updateWpTitleBox(); }

            if (bandSel) bandSel.addEventListener('change', onChange);
            if (dateInput) dateInput.addEventListener('change', onChange);
            if (autoTitle) autoTitle.addEventListener('change', onChange);

            updatePreview();
        })();
        </script>

        <script>
        (function() {
            const venueSel = document.getElementById('vms_venue_id');
            const dateInp  = document.getElementById('vms_event_date');
            const autoChk  = document.getElementById('vms_auto_comp_venue');
            const hint     = document.getElementById('vms-venue-defaults-hint');

            const fStruct = document.getElementById('vms_comp_structure');
            const fFlat   = document.getElementById('vms_flat_fee_amount');
            const fSplit  = document.getElementById('vms_door_split_percent');

            if (!venueSel || !dateInp || !autoChk || !fStruct) return;

            let dirty = false;
            [fStruct, fFlat, fSplit].forEach(el => {
                if (!el) return;
                el.addEventListener('change', () => dirty = true);
                el.addEventListener('input',  () => dirty = true);
            });

            function setHint(msg, type) {
                if (!hint) return;
                hint.textContent = msg || '';
                hint.style.color = (type === 'warn') ? '#92400e' : (type === 'ok' ? '#065f46' : '');
            }

            function applyRow(row) {
                if (!row || !row.structure) {
                    setHint('No venue defaults found for that day.', 'warn');
                    return;
                }
                if (dirty) {
                    setHint('Defaults available, but you have manual edits. Click “Apply Venue Defaults” to overwrite.', 'warn');
                    return;
                }
                fStruct.value = row.structure || 'flat_fee';
                if (fFlat && typeof row.flat_fee_amount !== 'undefined') fFlat.value = row.flat_fee_amount ?? '';
                if (fSplit && typeof row.door_split_percent !== 'undefined') fSplit.value = row.door_split_percent ?? '';
                setHint('Venue defaults applied for this date. (Override anytime.)', 'ok');
            }

            async function fetchDefaults() {
                const venue_id = venueSel.value || '';
                const event_date = dateInp.value || '';
                if (!venue_id || !event_date) return null;

                const form = new FormData();
                form.append('action', 'vms_get_venue_comp_defaults');
                form.append('venue_id', venue_id);
                form.append('event_date', event_date);

                const resp = await fetch(ajaxurl, { method:'POST', credentials:'same-origin', body: form });
                const json = await resp.json();
                if (!json || !json.success) return null;
                return (json.data && json.data.row) ? json.data.row : null;
            }

            async function onVenueOrDateChange() {
                if (!autoChk.checked) {
                    setHint('Auto-fill is off. (Enable it to apply venue defaults automatically.)', '');
                    return;
                }
                const row = await fetchDefaults();
                if (!row) { setHint('Select Venue + Date to load defaults.', ''); return; }
                applyRow(row);
            }

            venueSel.addEventListener('change', onVenueOrDateChange);
            dateInp.addEventListener('change', onVenueOrDateChange);
            autoChk.addEventListener('change', function() { if (autoChk.checked) dirty = false; onVenueOrDateChange(); });

            setHint('Select a Venue and Event Date to use venue defaults.', '');
        })();
        </script>

        <?php
        // Scroll helper (optional)
        $scroll_to = (string) get_post_meta($post->ID, '_vms_admin_scroll_to', true);
        if ($scroll_to) {
            delete_post_meta($post->ID, '_vms_admin_scroll_to');
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const el = document.getElementById('<?php echo esc_js($scroll_to); ?>');
                if (!el) return;
                setTimeout(() => el.scrollIntoView({ behavior:'smooth', block:'start' }), 150);
            });
            </script>
            <?php
        }
        ?>
        <?php
    }

    /**
     * Save Event Plan meta fields + handle actions
     */
    public function save_event_plan_meta(int $post_id, WP_Post $post): void
    {
        if (!isset($_POST['vms_event_plan_details_nonce']) || !wp_verify_nonce($_POST['vms_event_plan_details_nonce'], 'vms_save_event_plan_details')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Band
        if (isset($_POST['vms_band_vendor_id'])) {
            update_post_meta($post_id, '_vms_band_vendor_id', absint($_POST['vms_band_vendor_id']));
        }

        // Agenda
        if (isset($_POST['vms_agenda_text'])) {
            update_post_meta($post_id, '_vms_agenda_text', wp_kses_post($_POST['vms_agenda_text']));
        }

        // Staff assignments
        if (isset($_POST['vms_staff_assignments']) && is_array($_POST['vms_staff_assignments'])) {
            $raw = $_POST['vms_staff_assignments'];
            $clean = array();

            foreach ($raw as $term_id => $ids) {
                $term_id = absint($term_id);
                if ($term_id <= 0) continue;

                if (!is_array($ids)) $ids = array();
                $ids = array_map('absint', $ids);
                $ids = array_values(array_filter($ids, fn($v) => $v > 0));

                if (!empty($ids)) $clean[$term_id] = $ids;
            }

            if (!empty($clean)) update_post_meta($post_id, '_vms_staff_assignments', $clean);
            else delete_post_meta($post_id, '_vms_staff_assignments');
        } else {
            delete_post_meta($post_id, '_vms_staff_assignments');
        }

        // Core
        $event_date     = isset($_POST['vms_event_date']) ? sanitize_text_field($_POST['vms_event_date']) : '';
        $start_time     = isset($_POST['vms_start_time']) ? sanitize_text_field($_POST['vms_start_time']) : '';
        $end_time       = isset($_POST['vms_end_time']) ? sanitize_text_field($_POST['vms_end_time']) : '';
        $location_label = isset($_POST['vms_location_label']) ? sanitize_text_field($_POST['vms_location_label']) : '';
        if ($start_time === '') $start_time = '19:00';
        if ($end_time === '')   $end_time   = '21:00';

        $venue_id = isset($_POST['vms_venue_id']) ? absint($_POST['vms_venue_id']) : 0;

        // Ticket settings
        $ga_price = isset($_POST['vms_price_ga']) ? (float) $_POST['vms_price_ga'] : 20.0;

        $enable_tables   = isset($_POST['vms_enable_tables']) ? '1' : '0';
        $enable_firepits = isset($_POST['vms_enable_firepits']) ? '1' : '0';
        $enable_pools    = isset($_POST['vms_enable_pools']) ? '1' : '0';

        $table_count   = isset($_POST['vms_table_count']) ? max(0, absint($_POST['vms_table_count'])) : 6;
        $firepit_count = isset($_POST['vms_firepit_count']) ? max(0, absint($_POST['vms_firepit_count'])) : 6;

        $table_price   = isset($_POST['vms_price_table']) ? (float) $_POST['vms_price_table'] : 30.0;
        $firepit_price = isset($_POST['vms_price_firepit']) ? (float) $_POST['vms_price_firepit'] : 30.0;
        $pool_price    = isset($_POST['vms_price_pool']) ? (float) $_POST['vms_price_pool'] : 10.0;

        $table_min_tickets   = isset($_POST['vms_min_tickets_per_table']) ? max(0, absint($_POST['vms_min_tickets_per_table'])) : 2;
        $firepit_min_tickets = isset($_POST['vms_min_tickets_per_firepit']) ? max(0, absint($_POST['vms_min_tickets_per_firepit'])) : 2;

        // Draft pay
        $comp_structure      = isset($_POST['vms_comp_structure']) ? sanitize_text_field($_POST['vms_comp_structure']) : 'flat_fee';
        $flat_fee_amount     = isset($_POST['vms_flat_fee_amount']) ? $_POST['vms_flat_fee_amount'] : '';
        $door_split_percent  = isset($_POST['vms_door_split_percent']) ? $_POST['vms_door_split_percent'] : '';

        // Auto toggles
        $auto_title      = isset($_POST['vms_auto_title']) ? '1' : '0';
        $auto_comp       = isset($_POST['vms_auto_comp']) ? '1' : '0';
        $auto_comp_venue = isset($_POST['vms_auto_comp_venue']) ? '1' : '0';

        // Package selection (persist so dropdown sticks even without Apply)
        $comp_package_id = isset($_POST['vms_comp_package_id']) ? absint($_POST['vms_comp_package_id']) : 0;

        // Save core meta
        $fields = array(
            '_vms_event_date'     => $event_date,
            '_vms_start_time'     => $start_time,
            '_vms_end_time'       => $end_time,
            '_vms_location_label' => $location_label,
            '_vms_venue_id'       => $venue_id,

            '_vms_price_ga' => $ga_price,

            '_vms_enable_tables'   => $enable_tables,
            '_vms_enable_firepits' => $enable_firepits,
            '_vms_enable_pools'    => $enable_pools,

            '_vms_table_count'   => $table_count,
            '_vms_firepit_count' => $firepit_count,

            '_vms_price_table'   => $table_price,
            '_vms_price_firepit' => $firepit_price,
            '_vms_price_pool'    => $pool_price,

            '_vms_min_tickets_per_table'   => $table_min_tickets,
            '_vms_min_tickets_per_firepit' => $firepit_min_tickets,

            '_vms_comp_structure'     => $comp_structure,
            '_vms_flat_fee_amount'    => ($flat_fee_amount === '' ? '' : (float)$flat_fee_amount),
            '_vms_door_split_percent' => ($door_split_percent === '' ? '' : (float)$door_split_percent),

            '_vms_auto_title'      => $auto_title,
            '_vms_auto_comp'       => $auto_comp,
            '_vms_auto_comp_venue' => $auto_comp_venue,
        );

        foreach ($fields as $k => $v) {
            if ($v === '' || $v === null) delete_post_meta($post_id, $k);
            else update_post_meta($post_id, $k, $v);
        }

        if ($comp_package_id > 0) update_post_meta($post_id, '_vms_comp_package_id', $comp_package_id);
        else delete_post_meta($post_id, '_vms_comp_package_id');

        // Auto behaviors
        if ($auto_title === '1' && function_exists('vms_force_event_plan_title')) {
            vms_force_event_plan_title($post_id);
        }
        if ($auto_comp === '1' && function_exists('vms_maybe_apply_band_comp_defaults_to_plan')) {
            vms_maybe_apply_band_comp_defaults_to_plan($post_id);
        }

        // Handle actions
        if (!isset($_POST['vms_event_plan_action'])) {
            return;
        }

        $action = sanitize_text_field($_POST['vms_event_plan_action']);
        $current_status = (string) get_post_meta($post_id, '_vms_event_plan_status', true);
        if ($current_status === '') $current_status = 'draft';
        $new_status = $current_status;

        switch ($action) {
            case 'save_draft':
                $new_status = 'draft';
                vms_add_admin_notice(__('Event plan saved as Draft.', 'vms'), 'success');
                break;

            case 'mark_ready':
                $errors = vms_validate_event_plan($post_id);
                if (empty($errors)) {
                    vms_maybe_autoset_event_plan_title($post_id);
                    $new_status = 'ready';
                    vms_add_admin_notice(__('Event plan marked Ready.', 'vms'), 'success');
                } else {
                    $new_status = 'draft';
                    vms_add_admin_notice(__('Cannot mark Ready:', 'vms') . ' ' . implode(' ', $errors), 'error');
                }
                break;

            case 'publish_now':
                if (!in_array($current_status, array('ready', 'published'), true)) {
                    vms_add_admin_notice(__('Event must be Ready before publishing.', 'vms'), 'error');
                    break;
                }

                $errors = vms_validate_event_plan($post_id);
                if (!empty($errors)) {
                    vms_add_admin_notice(__('Cannot publish:', 'vms') . ' ' . implode(' ', $errors), 'error');
                    break;
                }

                $published = vms_publish_event_to_calendar($post_id, $post);
                if ($published) {
                    $new_status = 'published';
                    vms_add_admin_notice(__('Event published successfully.', 'vms'), 'success');

                    $result = vms_publish_products_from_plan($post_id);
                    if (!empty($result['ok'])) {
                        vms_add_admin_notice(
                            sprintf(__('Woo products updated. %d created, %d updated.', 'vms'), (int)$result['created'], (int)$result['updated']),
                            'success'
                        );
                    } else {
                        vms_add_admin_notice(__('Woo product publish failed:', 'vms') . ' ' . (string)($result['error'] ?? 'unknown error'), 'error');
                    }
                } else {
                    vms_add_admin_notice(__('Failed to publish event to calendar. Please check settings.', 'vms'), 'error');
                }
                break;

            case 'apply_band_defaults':
                if (function_exists('vms_apply_band_comp_defaults_to_plan')) {
                    vms_apply_band_comp_defaults_to_plan($post_id);
                    update_post_meta($post_id, '_vms_comp_needs_snapshot', '1');
                    vms_add_admin_notice(__('Band defaults applied (where available).', 'vms'), 'success');
                    vms_admin_scroll_to_compensation($post_id);
                }
                break;

            case 'apply_venue_defaults':
                $venue_id   = isset($_POST['vms_venue_id']) ? absint($_POST['vms_venue_id']) : 0;
                $event_date = isset($_POST['vms_event_date']) ? sanitize_text_field($_POST['vms_event_date']) : '';

                if ($venue_id <= 0 || !$event_date) {
                    vms_add_admin_notice(__('Select a Venue and Event Date first.', 'vms'), 'error');
                    break;
                }
                if (!function_exists('vms_get_venue_default_comp_for_date')) {
                    vms_add_admin_notice(__('Venue defaults helper is missing.', 'vms'), 'error');
                    break;
                }

                $row = vms_get_venue_default_comp_for_date($venue_id, $event_date);
                if (empty($row) || empty($row['structure'])) {
                    vms_add_admin_notice(__('No venue defaults found for that date/day.', 'vms'), 'error');
                    break;
                }

                update_post_meta($post_id, '_vms_comp_structure', sanitize_text_field($row['structure']));

                if (array_key_exists('flat_fee_amount', $row)) {
                    $val = $row['flat_fee_amount'];
                    if ($val === '' || $val === null) delete_post_meta($post_id, '_vms_flat_fee_amount');
                    else update_post_meta($post_id, '_vms_flat_fee_amount', (float) $val);
                }

                if (array_key_exists('door_split_percent', $row)) {
                    $val = $row['door_split_percent'];
                    if ($val === '' || $val === null) delete_post_meta($post_id, '_vms_door_split_percent');
                    else update_post_meta($post_id, '_vms_door_split_percent', (float) $val);
                }

                update_post_meta($post_id, '_vms_comp_needs_snapshot', '1');
                vms_add_admin_notice(__('Venue defaults applied for this date.', 'vms'), 'success');
                vms_admin_scroll_to_compensation($post_id);
                break;

            case 'apply_comp_package':
                if ($venue_id <= 0) {
                    vms_add_admin_notice(__('Please select a Venue first, then apply the package.', 'vms'), 'error');
                    break;
                }
                if ($comp_package_id <= 0) {
                    vms_add_admin_notice(__('Please select a comp package first.', 'vms'), 'error');
                    break;
                }
                if (!function_exists('vms_apply_comp_package_to_plan')) {
                    vms_add_admin_notice(__('Package apply helper is missing (vms_apply_comp_package_to_plan).', 'vms'), 'error');
                    break;
                }

                $ok = vms_apply_comp_package_to_plan($post_id, $comp_package_id);
                vms_admin_scroll_to_compensation($post_id);

                if ($ok) vms_add_admin_notice(__('Comp package applied and snapshotted for this event plan.', 'vms'), 'success');
                else vms_add_admin_notice(__('Failed to Apply Package. (Check package type/meta.)', 'vms'), 'error');
                break;

            case 'lock_draft_pay':
                $structure = (string) get_post_meta($post_id, '_vms_comp_structure', true);
                if ($structure === '') $structure = 'flat_fee';

                $flat  = get_post_meta($post_id, '_vms_flat_fee_amount', true);
                $split = get_post_meta($post_id, '_vms_door_split_percent', true);

                $flat  = ($flat === '' || $flat === null) ? null : (float) $flat;
                $split = ($split === '' || $split === null) ? null : (float) $split;

                if (in_array($structure, array('flat_fee', 'flat_fee_door_split'), true) && ($flat === null || $flat <= 0)) {
                    vms_add_admin_notice(__('Cannot lock Draft Pay: Flat Fee Amount is required for this structure.', 'vms'), 'error');
                    break;
                }
                if (in_array($structure, array('door_split', 'flat_fee_door_split'), true) && ($split === null || $split <= 0 || $split > 100)) {
                    vms_add_admin_notice(__('Cannot lock Draft Pay: Door Split % must be between 1 and 100 for this structure.', 'vms'), 'error');
                    break;
                }

                $pkg_id    = (int) get_post_meta($post_id, '_vms_comp_package_id', true);
                $pkg_title = $pkg_id ? (string) get_the_title($pkg_id) : '';

                $snapshot = array(
                    'locked_via'         => 'manual_lock',
                    'package_id'         => $pkg_id ?: null,
                    'package_title'      => $pkg_title ?: null,
                    'applied_at'         => current_time('mysql'),
                    'structure'          => $structure,
                    'flat_fee_amount'    => $flat,
                    'door_split_percent' => $split,
                );

                $hash = function_exists('vms_comp_hash_for_plan')
                    ? (string) vms_comp_hash_for_plan($post_id)
                    : md5(wp_json_encode(array('structure'=>$structure,'flat'=>$flat,'split'=>$split)));

                $snapshot['comp_hash'] = $hash;

                update_post_meta($post_id, '_vms_comp_snapshot', $snapshot);
                delete_post_meta($post_id, '_vms_comp_needs_snapshot');

                vms_add_admin_notice(__('Draft Pay locked for this event (snapshot created).', 'vms'), 'success');
                vms_admin_scroll_to_compensation($post_id);
                break;
        }

        update_post_meta($post_id, '_vms_event_plan_status', $new_status);

        if (function_exists('vms_sync_tec_status_from_plan')) {
            vms_sync_tec_status_from_plan($post_id);
        }
    }
}

/**
 * Bootstrap admin hooks for Event Plans.
 */
if (is_admin()) {
    new VMS_Admin_Event_Plans();
}

/**
 * Validation — used for READY and PUBLISH
 */
function vms_validate_event_plan(int $post_id): array
{
    $errors = array();

    $event_date = (string) get_post_meta($post_id, '_vms_event_date', true);
    $start_time = (string) get_post_meta($post_id, '_vms_start_time', true);
    $end_time   = (string) get_post_meta($post_id, '_vms_end_time', true);

    if ($event_date === '') $errors[] = __('Event date is required.', 'vms');

    if ($start_time === '' || $end_time === '') {
        $errors[] = __('Start time and end time are required.', 'vms');
    } else {
        $start_ts = strtotime($event_date . ' ' . $start_time);
        $end_ts   = strtotime($event_date . ' ' . $end_time);
        if (!$start_ts || !$end_ts) $errors[] = __('Start time or end time is not a valid time.', 'vms');
        elseif ($end_ts <= $start_ts) $errors[] = __('End time must be after start time.', 'vms');
    }

    $comp_structure     = (string) get_post_meta($post_id, '_vms_comp_structure', true);
    if ($comp_structure === '') $comp_structure = 'flat_fee';

    $flat_fee_amount    = get_post_meta($post_id, '_vms_flat_fee_amount', true);
    $door_split_percent = get_post_meta($post_id, '_vms_door_split_percent', true);

    if (in_array($comp_structure, array('flat_fee', 'flat_fee_door_split'), true)) {
        if ($flat_fee_amount === '' || $flat_fee_amount === null) $errors[] = __('Flat fee amount is required for this compensation structure.', 'vms');
        elseif (!is_numeric($flat_fee_amount) || (float)$flat_fee_amount <= 0) $errors[] = __('Flat fee amount must be a positive number.', 'vms');
    }

    if (in_array($comp_structure, array('door_split', 'flat_fee_door_split'), true)) {
        if ($door_split_percent === '' || $door_split_percent === null) $errors[] = __('Door split percentage is required for this compensation structure.', 'vms');
        elseif (!is_numeric($door_split_percent)) $errors[] = __('Door split percentage must be a number.', 'vms');
        else {
            $pct = (float) $door_split_percent;
            if ($pct <= 0 || $pct > 100) $errors[] = __('Door split percentage must be between 1 and 100.', 'vms');
        }
    }

    // Band required
    $band_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
    if (!$band_id) {
        $errors[] = __('A band must be selected before marking this event Ready.', 'vms');
        return $errors;
    }

    if ($event_date && function_exists('vms_get_vendor_availability_for_date')) {
        $availability = vms_get_vendor_availability_for_date($band_id, $event_date);
        if ($availability === 'unavailable') {
            $band_name = get_the_title($band_id) ?: __('Selected band', 'vms');
            $nice_date = date_i18n('M j, Y', strtotime($event_date));
            $errors[] = sprintf(__('%s is marked Not Available on %s.', 'vms'), $band_name, $nice_date);
        }
    }

    // Vendor tax profile required unless bypass is active
    if ($band_id > 0) {
        $missing = function_exists('vms_vendor_tax_profile_missing_items') ? (array) vms_vendor_tax_profile_missing_items($band_id) : array();
        if (!empty($missing)) {
            if (function_exists('vms_tax_bypass_is_active') && vms_tax_bypass_is_active($band_id)) {
                if (function_exists('vms_add_admin_notice')) {
                    vms_add_admin_notice(
                        sprintf(
                            __('Tax profile bypass active for "%s" until %s. READY allowed, but W-9 is still required.', 'vms'),
                            get_the_title($band_id),
                            (string) get_post_meta($band_id, '_vms_tax_bypass_until', true)
                        ),
                        'warning'
                    );
                }
            } else {
                $vendor_name = get_the_title($band_id);
                $errors[] = sprintf(
                    __('Band "%s" is missing required Tax Profile items: %s.', 'vms'),
                    $vendor_name ? $vendor_name : '#' . $band_id,
                    implode(', ', $missing)
                );
            }
        }
    }

    // Staff tax completeness
    $assignments = get_post_meta($post_id, '_vms_staff_assignments', true);
    if (is_array($assignments)) {
        foreach ($assignments as $role_id => $staff_ids) {
            if (!is_array($staff_ids)) continue;
            foreach ($staff_ids as $sid) {
                $sid = (int) $sid;
                if ($sid <= 0) continue;

                $missing = function_exists('vms_vendor_tax_profile_missing_items') ? (array) vms_vendor_tax_profile_missing_items($sid) : array();
                if (!empty($missing)) {
                    $staff_name = get_the_title($sid);
                    $errors[] = sprintf(
                        __('Staff "%s" is missing required Tax Profile items: %s.', 'vms'),
                        $staff_name ? $staff_name : '#' . $sid,
                        implode(', ', $missing)
                    );
                }
            }
        }
    }

    // Venue closed holiday blocks Ready/Publish
    $venue_id = (int) get_post_meta($post_id, '_vms_venue_id', true);
    if ($venue_id > 0 && $event_date && function_exists('vms_is_venue_closed_on_date') && vms_is_venue_closed_on_date($venue_id, $event_date)) {
        $h = function_exists('vms_get_venue_holiday_for_date') ? vms_get_venue_holiday_for_date($venue_id, $event_date) : null;
        $holiday_name = ($h && !empty($h['name'])) ? (string)$h['name'] : __('Holiday', 'vms');
        $errors[] = sprintf(__('Venue is CLOSED for "%s" on %s.', 'vms'), $holiday_name, $event_date);
    }

    return $errors;
}

/**
 * Auto-generate Event Plan title if empty/Auto Draft
 */
function vms_maybe_autoset_event_plan_title(int $post_id): void
{
    $post = get_post($post_id);
    if (!$post) return;

    $current_title = trim((string)$post->post_title);
    if ($current_title && strcasecmp($current_title, 'Auto Draft') !== 0) return;

    $band_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
    $band_name = $band_id ? (string) get_the_title($band_id) : '';
    $event_date = (string) get_post_meta($post_id, '_vms_event_date', true);

    if ($band_name === '' || $event_date === '') return;

    $ts = strtotime($event_date);
    $formatted_date = $ts ? date_i18n('F j, Y', $ts) : $event_date;
    $new_title = $band_name . ' — ' . $formatted_date;

    wp_update_post(array(
        'ID'         => $post_id,
        'post_title' => $new_title,
        'post_name'  => sanitize_title($new_title),
    ));

    vms_add_admin_notice(sprintf(__('Event title set to "%s".', 'vms'), $new_title), 'success');
}

/**
 * Admin notices — transient-based
 */
function vms_add_admin_notice(string $message, string $type = 'success'): void
{
    $user_id = get_current_user_id();
    if (!$user_id) return;

    $key = 'vms_event_plan_notices_' . $user_id;
    $notices = get_transient($key);
    if (!is_array($notices)) $notices = array();

    $notices[] = array('type' => $type, 'message' => $message);
    set_transient($key, $notices, 60);
}

add_action('admin_notices', 'vms_render_event_planadmin_notices');
function vms_render_event_planadmin_notices(): void
{
    $user_id = get_current_user_id();
    if (!$user_id) return;

    $key = 'vms_event_plan_notices_' . $user_id;
    $notices = get_transient($key);
    if (!is_array($notices) || empty($notices)) return;

    delete_transient($key);

    foreach ($notices as $notice) {
        $type = (string)($notice['type'] ?? 'success');
        $class = ($type === 'error') ? 'notice notice-error' : (($type === 'warning') ? 'notice notice-warning' : 'notice notice-success');
        printf('<div class="%s"><p>%s</p></div>', esc_attr($class), esc_html((string)($notice['message'] ?? '')));
    }
}

/**
 * List table: status pill column
 */
add_filter('manage_vms_event_plan_posts_columns', 'vms_add_event_plan_status_column');
function vms_add_event_plan_status_column(array $columns): array
{
    $new = array();
    foreach ($columns as $key => $label) {
        if ($key === 'date') $new['vms_plan_status'] = __('Plan Status', 'vms');
        $new[$key] = $label;
    }
    if (!isset($new['vms_plan_status'])) $new['vms_plan_status'] = __('Plan Status', 'vms');
    return $new;
}

add_action('manage_vms_event_plan_posts_custom_column', 'vms_render_event_plan_status_column', 10, 2);
function vms_render_event_plan_status_column(string $column, int $post_id): void
{
    if ($column !== 'vms_plan_status') return;

    $status = (string) get_post_meta($post_id, '_vms_event_plan_status', true);
    if ($status === '') $status = 'draft';

    $labels = array(
        'draft'     => __('Draft', 'vms'),
        'ready'     => __('Ready', 'vms'),
        'published' => __('Published', 'vms'),
    );

    $classes = array(
        'draft'     => 'vms-pill-grey',
        'ready'     => 'vms-pill-yellow',
        'published' => 'vms-pill-green',
    );

    $label = $labels[$status] ?? ucfirst($status);
    $class = $classes[$status] ?? 'vms-pill-grey';

    echo '<span class="vms-status-pill ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
}

add_action('admin_head-edit.php', 'vms_event_plan_status_column_css');
function vms_event_plan_status_column_css(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'vms_event_plan') return;
    ?>
    <style>
        .column-vms_plan_status{width:110px;}
        .vms-status-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;line-height:1.4;text-transform:uppercase;letter-spacing:0.03em;}
        .vms-pill-grey{background:#e5e7eb;color:#374151;}
        .vms-pill-yellow{background:#fef3c7;color:#92400e;}
        .vms-pill-green{background:#d1fae5;color:#065f46;}
    </style>
    <?php
}

/**
 * TEC Publish
 */
function vms_publish_event_to_calendar(int $post_id, WP_Post $post): bool
{
    if (!function_exists('tribe_create_event') || !function_exists('tribe_update_event')) {
        vms_add_admin_notice(__('The Events Calendar functions are not available. Is the plugin active?', 'vms'), 'error');
        return false;
    }

    $args = vms_build_tec_event_args($post_id);
    if (empty($args)) {
        vms_add_admin_notice(__('Unable to build event data for The Events Calendar.', 'vms'), 'error');
        return false;
    }

    $existing_tec_id = (int) get_post_meta($post_id, '_vms_tec_event_id', true);
    $tec_event_id = 0;

    if ($existing_tec_id > 0) {
        $updated_id = tribe_update_event($existing_tec_id, $args);
        if ($updated_id && !is_wp_error($updated_id)) $tec_event_id = (int)$updated_id;
        else vms_add_admin_notice(__('Failed to update existing Events Calendar event. Will attempt to create a new one.', 'vms'), 'error');
    }

    if (!$tec_event_id) {
        $created_id = tribe_create_event($args);
        if ($created_id && !is_wp_error($created_id)) {
            $tec_event_id = (int)$created_id;
            update_post_meta($post_id, '_vms_tec_event_id', $tec_event_id);
        } else {
            vms_add_admin_notice(__('Failed to create event in The Events Calendar.', 'vms'), 'error');
            return false;
        }
    }

    $thumb_id = get_post_thumbnail_id($post_id);
    if ($thumb_id) set_post_thumbnail($tec_event_id, $thumb_id);

    $tec_permalink = get_permalink($tec_event_id);
    if ($tec_permalink) update_post_meta($post_id, '_vms_tec_event_url', esc_url_raw($tec_permalink));

    return true;
}

function vms_build_tec_event_args(int $post_id): array
{
    $event_plan = get_post($post_id);
    if (!$event_plan) return array();

    $event_date  = (string) get_post_meta($post_id, '_vms_event_date', true);
    $start_time  = (string) get_post_meta($post_id, '_vms_start_time', true);
    $end_time    = (string) get_post_meta($post_id, '_vms_end_time', true);

    $band_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
    $band_name = $band_id ? (string) get_the_title($band_id) : '';

    $agenda_text = (string) get_post_meta($post_id, '_vms_agenda_text', true);
    if ($agenda_text === '') $agenda_text = (string) $event_plan->post_content;

    $title = trim((string)$event_plan->post_title);
    if ($band_name && $event_date) {
        $ts = strtotime($event_date);
        $formatted_date = $ts ? date_i18n('F j, Y', $ts) : $event_date;
        $default_title = $band_name . ' — ' . $formatted_date;
        if (!$title || strcasecmp($title, 'Auto Draft') === 0) $title = $default_title;
    }

    $plan_status = function_exists('vms_get_event_plan_status') ? (string) vms_get_event_plan_status($post_id) : (string) get_post_meta($post_id, '_vms_event_plan_status', true);
    if ($plan_status === '') $plan_status = 'draft';

    $tec_status = function_exists('vms_map_plan_status_to_tec_post_status')
        ? (string) vms_map_plan_status_to_tec_post_status($plan_status)
        : (($plan_status === 'published') ? 'publish' : 'draft');

    $args = array(
        'post_type'    => 'tribe_events',
        'post_title'   => $title,
        'post_content' => $agenda_text,
        'post_status'  => $tec_status,
    );

    if ($event_date) {
        $args['EventStartDate'] = $event_date;
        $args['EventEndDate']   = $event_date;
    }

    if ($start_time) {
        $parts = explode(':', $start_time);
        $args['EventStartHour']   = sprintf('%02d', (int)($parts[0] ?? 0));
        $args['EventStartMinute'] = sprintf('%02d', (int)($parts[1] ?? 0));
    }
    if ($end_time) {
        $parts = explode(':', $end_time);
        $args['EventEndHour']   = sprintf('%02d', (int)($parts[0] ?? 0));
        $args['EventEndMinute'] = sprintf('%02d', (int)($parts[1] ?? 0));
    }
    if ($start_time || $end_time) $args['EventAllDay'] = false;

    return $args;
}

add_filter('post_row_actions', 'vms_event_plan_row_actions', 10, 2);
function vms_event_plan_row_actions(array $actions, WP_Post $post): array
{
    if ($post->post_type !== 'vms_event_plan') return $actions;

    $tec_url = (string) get_post_meta($post->ID, '_vms_tec_event_url', true);
    if ($tec_url) {
        $actions['vms_view_tec'] =
            '<a href="' . esc_url($tec_url) . '" target="_blank" rel="noopener">' .
            esc_html__('View in Calendar', 'vms') .
            '</a>';
    }
    return $actions;
}

/**
 * Filter list by current venue (admin)
 */
add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-vms_event_plan') return;

    if (!function_exists('vms_get_current_venue_id')) return;

    $venue_id = (int) vms_get_current_venue_id();
    if ($venue_id <= 0) return;

    $meta_query = (array) $query->get('meta_query');
    $meta_query[] = array(
        'key'     => '_vms_venue_id',
        'value'   => $venue_id,
        'compare' => '=',
        'type'    => 'NUMERIC',
    );
    $query->set('meta_query', $meta_query);
});

/**
 * Woo publish pipeline
 */
function vms_publish_products_from_plan(int $plan_id): array
{
    if (!class_exists('WC_Product')) {
        return array('ok' => false, 'error' => 'WooCommerce is not active.');
    }

    $event_date = (string) get_post_meta($plan_id, '_vms_event_date', true);
    if (!$event_date) return array('ok' => false, 'error' => 'Missing event date.');

    $band_id   = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
    $band_name = $band_id ? (string) get_the_title($band_id) : '';
    if (!$band_name) $band_name = 'Event';

    $ts = strtotime($event_date);
    $nice_date = $ts ? date_i18n('M j, Y', $ts) : $event_date;

    $tec_event_id = (int) get_post_meta($plan_id, '_vms_tec_event_id', true);

    $map = get_post_meta($plan_id, '_vms_wc_product_map', true);
    if (!is_array($map)) $map = array();

    $blueprint = vms_build_product_blueprint_for_plan($plan_id, $nice_date, $band_name);

    $created = 0;
    $updated = 0;

    foreach ($blueprint as $key => $spec) {
        $existing_id = isset($map[$key]) ? (int)$map[$key] : 0;
        $product_id = vms_upsert_plan_product($plan_id, $existing_id, $spec, $tec_event_id);
        if (!$product_id) continue;

        if ($existing_id && $existing_id === $product_id) $updated++;
        else $created++;

        $map[$key] = $product_id;
    }

    foreach ($map as $key => $pid) {
        if (!isset($blueprint[$key])) {
            vms_disable_plan_product((int)$pid);
        }
    }

    update_post_meta($plan_id, '_vms_wc_product_map', $map);

    return array('ok' => true, 'created' => $created, 'updated' => $updated, 'map' => $map);
}

function vms_build_product_blueprint_for_plan(int $plan_id, string $nice_date, string $band_name): array
{
    $ga_price = (float) get_post_meta($plan_id, '_vms_price_ga', true);
    if ($ga_price <= 0) $ga_price = 20.0;

    $enable_tables   = (get_post_meta($plan_id, '_vms_enable_tables', true) === '1');
    $enable_firepits = (get_post_meta($plan_id, '_vms_enable_firepits', true) === '1');
    $enable_pools    = (get_post_meta($plan_id, '_vms_enable_pools', true) === '1');

    $table_count   = (int) get_post_meta($plan_id, '_vms_table_count', true);
    if ($table_count <= 0) $table_count = 6;

    $firepit_count = (int) get_post_meta($plan_id, '_vms_firepit_count', true);
    if ($firepit_count <= 0) $firepit_count = 6;

    $table_price   = (float) get_post_meta($plan_id, '_vms_price_table', true);
    if ($table_price <= 0) $table_price = 30.0;

    $firepit_price = (float) get_post_meta($plan_id, '_vms_price_firepit', true);
    if ($firepit_price <= 0) $firepit_price = 30.0;

    $pool_price    = (float) get_post_meta($plan_id, '_vms_price_pool', true);
    if ($pool_price <= 0) $pool_price = 10.0;

    $table_min_tickets = (int) get_post_meta($plan_id, '_vms_min_tickets_per_table', true);
    if ($table_min_tickets <= 0) $table_min_tickets = 2;

    $firepit_min_tickets = (int) get_post_meta($plan_id, '_vms_min_tickets_per_firepit', true);
    if ($firepit_min_tickets <= 0) $firepit_min_tickets = 2;

    $items = array();

    $items['ga'] = array(
        'name'       => "{$nice_date} — {$band_name} — GA Ticket",
        'price'      => $ga_price,
        'is_ticket'  => true,
        'sku_suffix' => 'GA',
        'meta'       => array('_sr_addon_qualifier' => 'yes'),
        'tags'       => array('ticket'),
    );

    if ($enable_tables) {
        for ($i = 1; $i <= $table_count; $i++) {
            $num = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $items["table_{$num}"] = array(
                'name'       => "{$nice_date} — {$band_name} — Table #{$num}",
                'price'      => $table_price,
                'is_ticket'  => false,
                'sku_suffix' => "TB{$num}",
                'meta'       => array(
                    '_sr_required_qualifiers_per_unit' => $table_min_tickets,
                    '_sr_addon_qualifier'              => 'no',
                    '_sr_addon_type'                   => 'table',
                    '_sr_addon_unit_label'             => "Table #{$num}",
                ),
                'tags'      => array(),
                'stock_qty' => 1,
            );
        }
    }

    if ($enable_firepits) {
        for ($i = 1; $i <= $firepit_count; $i++) {
            $num = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $items["firepit_{$num}"] = array(
                'name'       => "{$nice_date} — {$band_name} — Fire Pit #{$num}",
                'price'      => $firepit_price,
                'is_ticket'  => false,
                'sku_suffix' => "FP{$num}",
                'meta'       => array(
                    '_sr_required_qualifiers_per_unit' => $firepit_min_tickets,
                    '_sr_addon_qualifier'              => 'no',
                    '_sr_addon_type'                   => 'fire_pit',
                    '_sr_addon_unit_label'             => "Fire Pit #{$num}",
                ),
                'tags'      => array(),
                'stock_qty' => 1,
            );
        }
    }

    if ($enable_pools) {
        $items['pool'] = array(
            'name'       => "{$nice_date} — {$band_name} — Kiddie Pool Rental",
            'price'      => $pool_price,
            'is_ticket'  => false,
            'sku_suffix' => 'POOL',
            'meta'       => array(
                '_sr_required_qualifiers_per_unit' => 0,
                '_sr_addon_qualifier'              => 'no',
                '_sr_addon_type'                   => 'pool',
            ),
            'tags'       => array(),
        );
    }

    return $items;
}

function vms_upsert_plan_product(int $plan_id, int $existing_product_id, array $spec, int $tec_event_id = 0): int
{
    if (!class_exists('WC_Product_Simple')) return 0;

    $name  = (string)($spec['name'] ?? '');
    $price = (float)($spec['price'] ?? 0.0);
    if ($name === '') return 0;

    $product = null;
    if ($existing_product_id > 0) {
        $product = wc_get_product($existing_product_id);
    }
    if (!$product) {
        $product = new WC_Product_Simple();
    }

    $product->set_name($name);
    $product->set_regular_price($price);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');

    if (isset($spec['stock_qty'])) {
        $product->set_manage_stock(true);
        $product->set_stock_quantity((int)$spec['stock_qty']);
        $product->set_stock_status(((int)$spec['stock_qty'] > 0) ? 'instock' : 'outofstock');
        $product->set_sold_individually(true);
    } else {
        $product->set_manage_stock(false);
    }

    $product_id = (int) $product->save();
    if (!$product_id) return 0;

    update_post_meta($product_id, '_vms_event_plan_id', $plan_id);
    if ($tec_event_id > 0) update_post_meta($product_id, '_vms_tec_event_id', $tec_event_id);

    $tags = (isset($spec['tags']) && is_array($spec['tags'])) ? $spec['tags'] : array();
    wp_set_object_terms($product_id, $tags, 'product_tag', false);

    $meta = (isset($spec['meta']) && is_array($spec['meta'])) ? $spec['meta'] : array();
    foreach ($meta as $k => $v) {
        update_post_meta($product_id, (string)$k, $v);
    }

    update_post_meta($product_id, '_vms_product_role', !empty($spec['is_ticket']) ? 'ticket' : 'addon');

    return $product_id;
}

function vms_disable_plan_product(int $product_id): void
{
    $p = wc_get_product($product_id);
    if (!$p) return;
    $p->set_status('draft');
    $p->save();
}
