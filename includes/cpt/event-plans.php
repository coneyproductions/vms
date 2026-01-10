<?php
if (! defined('ABSPATH')) {
    exit;
}

add_action('init', 'vms_register_event_plan_cpt');

function vms_register_event_plan_cpt()
{
    register_post_type('vms_event_plan', array(
        'labels' => array(
            'name'          => __('Event Plans', 'vms'),
            'singular_name' => __('Event Plan', 'vms'),
            'menu_name'     => __('Event Plans', 'vms'),
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => 'vms-season-board',
        'menu_icon'     => 'dashicons-calendar-alt',
        'supports'      => array('title', 'editor', 'thumbnail'),
        'capability_type' => 'post',
        'has_archive'   => false,
        'rewrite'       => false,
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
    }

    /**
     * Register meta boxes for the Event Plan post type.
     */
    public function register_meta_boxes()
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

    /**
     * Render the Event Plan Details meta box.
     */
    public function render_event_plan_details_meta_box($post)
    {
        // Security nonce.
        wp_nonce_field('vms_save_event_plan_details', 'vms_event_plan_details_nonce');

        // Existing meta values.
        $event_date          = get_post_meta($post->ID, '_vms_event_date', true);
        $start_time          = get_post_meta($post->ID, '_vms_start_time', true);
        $end_time            = get_post_meta($post->ID, '_vms_end_time', true);
        // Default display values (only when blank)
        if ($start_time === '' || $start_time === null) $start_time = '19:00'; // default start time
        if ($end_time === '' || $end_time === null)     $end_time   = '21:00'; // default end time

        $location_label      = get_post_meta($post->ID, '_vms_location_label', true);
        $venue_id            = (int) get_post_meta($post->ID, '_vms_venue_id', true);
        if ($venue_id <= 0 && function_exists('vms_get_current_venue_id')) {
            $maybe = (int) vms_get_current_venue_id();
            if ($maybe > 0) {
                $venue_id = $maybe; // UI default only (meta not saved until Update)
            }
        }

        $auto_title          = get_post_meta($post->ID, '_vms_auto_title', true);
        if ($auto_title === '') $auto_title = '1'; // default ON
        $auto_comp = get_post_meta($post->ID, '_vms_auto_comp', true);
        if ($auto_comp === '') $auto_comp = '1';

        $venues = get_posts(array(
            'post_type'      => 'vms_venue',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $comp_structure      = get_post_meta($post->ID, '_vms_comp_structure', true);
        if (empty($comp_structure)) {
            $comp_structure = 'flat_fee'; // default.
        }

        $flat_fee_amount    = get_post_meta($post->ID, '_vms_flat_fee_amount', true);
        $door_split_percent = get_post_meta($post->ID, '_vms_door_split_percent', true);

        $allow_vendor_propose = get_post_meta($post->ID, '_vms_allow_vendor_propose', true);
        $proposal_min         = get_post_meta($post->ID, '_vms_proposal_min', true);
        $proposal_max         = get_post_meta($post->ID, '_vms_proposal_max', true);
        $proposal_cap         = get_post_meta($post->ID, '_vms_proposal_cap', true);

        // -------------------------------
        // Tickets / Add-ons settings (Plan → Products)
        // -------------------------------
        $ga_price = get_post_meta($post->ID, '_vms_price_ga', true);
        if ($ga_price === '' || $ga_price === null) $ga_price = '20';

        $enable_tables   = get_post_meta($post->ID, '_vms_enable_tables', true);
        if ($enable_tables === '') $enable_tables = '1';

        $enable_firepits = get_post_meta($post->ID, '_vms_enable_firepits', true);
        if ($enable_firepits === '') $enable_firepits = '1';

        $enable_pools    = get_post_meta($post->ID, '_vms_enable_pools', true);
        if ($enable_pools === '') $enable_pools = '0';

        // counts
        $table_count   = get_post_meta($post->ID, '_vms_table_count', true);
        if ($table_count === '' || $table_count === null) $table_count = '6';

        $firepit_count = get_post_meta($post->ID, '_vms_firepit_count', true);
        if ($firepit_count === '' || $firepit_count === null) $firepit_count = '6';

        // prices
        $table_price   = get_post_meta($post->ID, '_vms_price_table', true);
        if ($table_price === '' || $table_price === null) $table_price = '30';

        $firepit_price = get_post_meta($post->ID, '_vms_price_firepit', true);
        if ($firepit_price === '' || $firepit_price === null) $firepit_price = '30';

        $pool_price    = get_post_meta($post->ID, '_vms_price_pool', true);
        if ($pool_price === '' || $pool_price === null) $pool_price = '10';

        // qualification rules
        $firepit_min_tickets = get_post_meta($post->ID, '_vms_min_tickets_per_firepit', true);
        if ($firepit_min_tickets === '' || $firepit_min_tickets === null) $firepit_min_tickets = '2';

        $table_min_tickets = get_post_meta($post->ID, '_vms_min_tickets_per_table', true);
        if ($table_min_tickets === '' || $table_min_tickets === null) $table_min_tickets = '2';

        // NEW: Event plan status (draft / ready / published).
        $plan_status = get_post_meta($post->ID, '_vms_event_plan_status', true);
        if (empty($plan_status)) {
            $plan_status = 'draft';
        }

        $agenda_text = get_post_meta($post->ID, '_vms_agenda_text', true);
?>

        <hr />
        <h4><?php esc_html_e('Agenda / Event Description', 'vms'); ?></h4>

        <p>
            <textarea name="vms_agenda_text" id="vms_agenda_text" rows="6" style="width:100%;"><?php
                                                                                                echo esc_textarea($agenda_text);
                                                                                                ?></textarea>
        </p>

        <p class="description">
            This text will appear publicly on the event page in The Events Calendar.
        </p>

        <p>
            <label for="vms_event_date"><strong><?php esc_html_e('Event Date', 'vms'); ?></strong></label><br />
            <input type="date" id="vms_event_date" name="vms_event_date"
                value="<?php echo esc_attr($event_date); ?>" />
        </p>

        <p>
            <label for="vms_venue_id"><strong><?php esc_html_e('Venue', 'vms'); ?></strong></label><br />

            <select id="vms_venue_id" name="vms_venue_id" style="min-width:260px;" required>
                <option value=""><?php esc_html_e('-- Select a Venue --', 'vms'); ?></option>

                <?php foreach ($venues as $venue) : ?>
                    <option value="<?php echo esc_attr($venue->ID); ?>" <?php selected($venue_id, $venue->ID); ?>>
                        <?php echo esc_html($venue->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <br /><span class="description">
                <?php esc_html_e('Required. This scopes the event plan to a specific venue.', 'vms'); ?>
            </span>
        </p>

        <p>
            <label for="vms_start_time"><strong><?php esc_html_e('Start Time', 'vms'); ?></strong></label><br />
            <input type="time" id="vms_start_time" name="vms_start_time"
                value="<?php echo esc_attr($start_time); ?>" />
        </p>

        <p>
            <label for="vms_end_time"><strong><?php esc_html_e('End Time', 'vms'); ?></strong></label><br />
            <input type="time" id="vms_end_time" name="vms_end_time"
                value="<?php echo esc_attr($end_time); ?>" />
        </p>

        <?php
        // Load vendors (bands) to populate dropdown. 
        // We’ll refine filtering later when food trucks get added.
        $bands = get_posts(array(
            'post_type'      => 'vms_vendor',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        // Current selected band
        $selected_band_id = get_post_meta($post->ID, '_vms_band_vendor_id', true);
        ?>

        <?php
        // $event_date should already be loaded earlier in this function as _vms_event_date.
        $event_date = get_post_meta($post->ID, '_vms_event_date', true);
        ?>

        <p>
            <label for="vms_band_vendor_id"><strong><?php esc_html_e('Band / Headliner', 'vms'); ?></strong></label><br />

            <select id="vms_band_vendor_id" name="vms_band_vendor_id" style="min-width:260px;">
                <option value=""><?php esc_html_e('-- Select a Band --', 'vms'); ?></option>

                <?php foreach ($bands as $band) : ?>
                    <?php
                    $label = $band->post_title;

                    // If we have an event date, decorate the label with availability.
                    if ($event_date) {
                        $availability = vms_get_vendor_availability_for_date($band->ID, $event_date);

                        if ($availability === 'available') {
                            $label .= ' [✓]';
                        } elseif ($availability === 'unavailable') {
                            $label .= ' [✖]';
                        } else {
                            $label .= ' [?]';
                        }
                    }

                    ?>
                    <option value="<?php echo esc_attr($band->ID); ?>"
                        <?php selected($selected_band_id, $band->ID); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($event_date) : ?>
                <?php
                $ts   = strtotime($event_date);
                $nice = $ts ? date_i18n('M j, Y', $ts) : $event_date;
                ?>
                <br />
                <span class="description">
                    <?php
                    printf(
                        /* translators: %s is the formatted date */
                        esc_html__('Availability for %s: [✓] Available, [✖] Not Available, [?] Unknown', 'vms'),
                        esc_html($nice)
                    );
                    ?>
                </span>
            <?php else : ?>
                <br />
                <span class="description">
                    <?php esc_html_e('Set the Event Date to see per-band availability hints here.', 'vms'); ?>
                </span>
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
            <input type="text" id="vms_location_label" name="vms_location_label" class="regular-text"
                value="<?php echo esc_attr($location_label); ?>" />
            <br /><span class="description">
                <?php esc_html_e('Example: Main Stage, Patio, Food Truck Row, etc.', 'vms'); ?>
            </span>
        </p>

        <hr />

        <?php
        $venue_id       = (int) get_post_meta($post->ID, '_vms_venue_id', true);
        $current_pkg_id = (int) get_post_meta($post->ID, '_vms_comp_package_id', true);
        $packages       = ($venue_id > 0) ? vms_get_comp_packages_for_venue($venue_id, true) : array();

        $snapshot = get_post_meta($post->ID, '_vms_comp_snapshot', true);
        if (!is_array($snapshot)) $snapshot = array();
        ?>

        <hr />
        <h4><?php esc_html_e('Compensation', 'vms'); ?></h4>

        <p class="description">
            <?php esc_html_e('Select a compensation package and click Apply to lock in the agreed terms for this plan.', 'vms'); ?>
        </p>

        <?php if ($venue_id <= 0) : ?>

            <p><em><?php esc_html_e('Select a Venue above, then click “Save Draft” (or Update) to load comp packages.', 'vms'); ?></em></p>

        <?php else : ?>

            <p>
                <label for="vms_comp_package_id"><strong><?php esc_html_e('Comp Package', 'vms'); ?></strong></label><br />
                <select id="vms_comp_package_id" name="vms_comp_package_id" style="min-width:320px;">
                    <option value=""><?php esc_html_e('-- Select a Package --', 'vms'); ?></option>
                    <?php foreach ($packages as $pkg) : ?>
                        <option value="<?php echo esc_attr($pkg->ID); ?>" <?php selected($current_pkg_id, $pkg->ID); ?>>
                            <?php echo esc_html($pkg->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <strong><?php esc_html_e('Quick Apply:', 'vms'); ?></strong><br />
                <button type="submit" name="vms_event_plan_action" value="apply_band_defaults" class="button">
                    <?php esc_html_e('Apply Band Defaults', 'vms'); ?>
                </button>

                <button type="submit" name="vms_event_plan_action" value="apply_comp_package" class="button button-secondary">
                    <?php esc_html_e('Apply Comp Package', 'vms'); ?>
                </button>
            </p>

            <p class="description">
                <?php esc_html_e('Applying writes a snapshot so future package edits won’t change this plan unless you apply again.', 'vms'); ?>
            </p>

        <?php endif; ?>

        <?php if (!empty($snapshot)) : ?>
            <div style="padding:10px 12px; border:1px solid #dcdcde; border-radius:8px; background:#fff; max-width:720px;">
                <strong><?php esc_html_e('Applied Snapshot', 'vms'); ?></strong><br />
                <span class="description">
                    <?php
                    $pkg_title = $snapshot['package_title'] ?? '';
                    $applied_at = $snapshot['applied_at'] ?? '';
                    echo esc_html($pkg_title ? $pkg_title : '—');
                    echo $applied_at ? ' • ' . esc_html($applied_at) : '';
                    ?>
                </span>
                <div style="margin-top:6px;">
                    <?php
                    $line = array();
                    if (!empty($snapshot['structure'])) $line[] = 'Structure: ' . strtoupper((string)$snapshot['structure']);
                    if ($snapshot['flat_fee_amount'] !== null) $line[] = 'Flat: $' . number_format((float)$snapshot['flat_fee_amount'], 2);
                    if ($snapshot['door_split_percent'] !== null) $line[] = 'Split: ' . rtrim(rtrim((string)$snapshot['door_split_percent'], '0'), '.') . '%';
                    if ($snapshot['commission_percent'] !== null) {
                        $mode = $snapshot['commission_mode'] ?? 'artist_fee';
                        $line[] = 'Commission: ' . rtrim(rtrim((string)$snapshot['commission_percent'], '0'), '.') . '% (' . $mode . ')';
                    }
                    echo esc_html(implode(' | ', $line));
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <p>
            <label>
                <input type="checkbox" name="vms_auto_comp" value="1" <?php checked($auto_comp, '1'); ?> />
                <?php esc_html_e('Auto-fill compensation from band defaults (if set)', 'vms'); ?>
            </label>
        </p>

        <p>
            <label for="vms_comp_structure"><strong><?php esc_html_e('Structure', 'vms'); ?></strong></label><br />
            <select id="vms_comp_structure" name="vms_comp_structure">
                <option value="flat_fee" <?php selected($comp_structure, 'flat_fee'); ?>>
                    <?php esc_html_e('Flat Fee Only', 'vms'); ?>
                </option>
                <option value="flat_fee_door_split" <?php selected($comp_structure, 'flat_fee_door_split'); ?>>
                    <?php esc_html_e('Flat Fee + Door Split', 'vms'); ?>
                </option>
                <option value="door_split" <?php selected($comp_structure, 'door_split'); ?>>
                    <?php esc_html_e('Door Split Only', 'vms'); ?>
                </option>
            </select>
        </p>

        <p>
            <label for="vms_flat_fee_amount"><strong><?php esc_html_e('Flat Fee Amount', 'vms'); ?></strong></label><br />
            <input type="number" id="vms_flat_fee_amount" name="vms_flat_fee_amount" style="width: 150px;"
                value="<?php echo esc_attr($flat_fee_amount); ?>" />
        </p>

        <p>
            <label for="vms_door_split_percent"><strong><?php esc_html_e('Door Split Percentage', 'vms'); ?></strong></label><br />
            <input type="number" id="vms_door_split_percent" name="vms_door_split_percent" style="width: 150px;"
                value="<?php echo esc_attr($door_split_percent); ?>" /> %
        </p>

        <p>
            <label>
                <input type="checkbox" name="vms_allow_vendor_propose" value="1"
                    <?php checked($allow_vendor_propose, '1'); ?> />
                <?php esc_html_e('Let vendor propose flat fee for this event instead of using the fixed amount above', 'vms'); ?>
            </label>
        </p>

        <div style="margin-left: 18px;">
            <p>
                <label><strong><?php esc_html_e('Suggested Proposal Range (optional)', 'vms'); ?></strong></label><br />
                <span><?php esc_html_e('Min', 'vms'); ?></span>
                <input type="number" id="vms_proposal_min" name="vms_proposal_min" style="width: 120px;"
                    value="<?php echo esc_attr($proposal_min); ?>" /> &nbsp;
                <span><?php esc_html_e('Max', 'vms'); ?></span>
                <input type="number" id="vms_proposal_max" name="vms_proposal_max" style="width: 120px;"
                    value="<?php echo esc_attr($proposal_max); ?>" />
            </p>

            <p>
                <label for="vms_proposal_cap"><strong><?php esc_html_e('Hard Proposal Cap (optional)', 'vms'); ?></strong></label><br />
                <input type="number" id="vms_proposal_cap" name="vms_proposal_cap" style="width: 150px;"
                    value="<?php echo esc_attr($proposal_cap); ?>" />
                <br /><span class="description">
                    <?php esc_html_e('If vendor proposes above this, it will be flagged for review.', 'vms'); ?>
                </span>
            </p>
        </div>

        <hr />
        <p class="description">
            <?php esc_html_e('Featured Image: use the standard WordPress featured image box for this event\'s banner/hero.', 'vms'); ?>
        </p>

        <hr />
        <h4><?php esc_html_e('Tickets & Add-ons (Woo Products)', 'vms'); ?></h4>

        <p class="description">
            <?php esc_html_e('These settings control which WooCommerce products are generated/updated when you Publish.', 'vms'); ?>
        </p>

        <p>
            <label for="vms_price_ga"><strong><?php esc_html_e('GA Ticket Price', 'vms'); ?></strong></label><br />
            <input type="number" step="0.01" min="0" id="vms_price_ga" name="vms_price_ga" style="width:140px;"
                value="<?php echo esc_attr($ga_price); ?>" />
        </p>

        <p>
            <label>
                <input type="checkbox" name="vms_enable_tables" value="1" <?php checked($enable_tables, '1'); ?> />
                <?php esc_html_e('Enable Tables', 'vms'); ?>
            </label>
            &nbsp;&nbsp;
            <label>
                <?php esc_html_e('Count', 'vms'); ?>
                <input type="number" min="0" step="1" name="vms_table_count" style="width:80px;"
                    value="<?php echo esc_attr($table_count); ?>" />
            </label>
            &nbsp;&nbsp;
            <label>
                <?php esc_html_e('Price', 'vms'); ?>
                <input type="number" step="0.01" min="0" name="vms_price_table" style="width:100px;"
                    value="<?php echo esc_attr($table_price); ?>" />
            </label>
            &nbsp;&nbsp;
            <label>
                <?php esc_html_e('Min Tickets', 'vms'); ?>
                <input type="number" min="0" step="1" name="vms_min_tickets_per_table" style="width:80px;"
                    value="<?php echo esc_attr($table_min_tickets); ?>" />
            </label>
        </p>

        <p>
            <label>
                <input type="checkbox" name="vms_enable_firepits" value="1" <?php checked($enable_firepits, '1'); ?> />
                <?php esc_html_e('Enable Fire Pits', 'vms'); ?>
            </label>
            &nbsp;&nbsp;
            <label>
                <?php esc_html_e('Count', 'vms'); ?>
                <input type="number" min="0" step="1" name="vms_firepit_count" style="width:80px;"
                    value="<?php echo esc_attr($firepit_count); ?>" />
            </label>
            &nbsp;&nbsp;
            <label>
                <?php esc_html_e('Price', 'vms'); ?>
                <input type="number" step="0.01" min="0" name="vms_price_firepit" style="width:100px;"
                    value="<?php echo esc_attr($firepit_price); ?>" />
            </label>
            &nbsp;&nbsp;
            <label>
                <?php esc_html_e('Min Tickets', 'vms'); ?>
                <input type="number" min="0" step="1" name="vms_min_tickets_per_firepit" style="width:80px;"
                    value="<?php echo esc_attr($firepit_min_tickets); ?>" />
            </label>
        </p>

        <p>
            <label>
                <input type="checkbox" name="vms_enable_pools" value="1" <?php checked($enable_pools, '1'); ?> />
                <?php esc_html_e('Enable Kiddie Pools', 'vms'); ?>
            </label>
            &nbsp;&nbsp;
            <label>
                <?php esc_html_e('Price', 'vms'); ?>
                <input type="number" step="0.01" min="0" name="vms_price_pool" style="width:100px;"
                    value="<?php echo esc_attr($pool_price); ?>" />
            </label>
            <br />
            <span class="description"><?php esc_html_e('Pools have no ticket minimum (per your rules).', 'vms'); ?></span>
        </p>

        <!-- NEW: Status + workflow buttons -->
        <hr />

        <h4><?php esc_html_e('Event Plan Status & Workflow', 'vms'); ?></h4>

        <p>
            <strong><?php esc_html_e('Status:', 'vms'); ?></strong>
            <?php echo esc_html(strtoupper($plan_status)); ?>
        </p>

        <p>
            <!-- Save Draft -->
            <button type="submit"
                name="vms_event_plan_action"
                value="save_draft"
                class="button">
                <?php esc_html_e('Save Draft', 'vms'); ?>
            </button>

            <!-- Mark Ready -->
            <button type="submit"
                name="vms_event_plan_action"
                value="mark_ready"
                class="button button-secondary">
                <?php esc_html_e('Mark Ready', 'vms'); ?>
            </button>

            <!-- Publish Now (only enabled when READY or PUBLISHED) -->
            <button type="submit"
                name="vms_event_plan_action"
                value="publish_now"
                class="button button-primary"
                <?php echo ($plan_status === 'ready' || $plan_status === 'published') ? '' : ' disabled="disabled"'; ?>>
                <?php esc_html_e('Publish Now', 'vms'); ?>
            </button>
        </p>

        <p class="description">
            <?php esc_html_e('“Publish Now” is only available once the plan is Ready.', 'vms'); ?>
        </p>

        <script>
            (function() {
                const bandSel = document.getElementById('vms_band_vendor_id');
                const dateInput = document.getElementById('vms_event_date');
                const autoTitle = document.querySelector('input[name="vms_auto_title"]');
                const previewEl = document.getElementById('vms_title_preview_text');

                // WP title input (classic editor + block editor usually still exposes this)
                const wpTitleInput =
                    document.getElementById('title') ||
                    document.querySelector('textarea.editor-post-title__input') ||
                    document.querySelector('h1.editor-post-title__input');

                function formatDate(yyyy_mm_dd) {
                    if (!yyyy_mm_dd) return '';
                    // Build date in local time without timezone shifting
                    const parts = yyyy_mm_dd.split('-');
                    if (parts.length !== 3) return yyyy_mm_dd;
                    const y = parseInt(parts[0], 10),
                        m = parseInt(parts[1], 10) - 1,
                        d = parseInt(parts[2], 10);
                    const dt = new Date(y, m, d);
                    if (isNaN(dt.getTime())) return yyyy_mm_dd;

                    return dt.toLocaleDateString(undefined, {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                }

                function getBandName() {
                    if (!bandSel) return '';
                    const opt = bandSel.options[bandSel.selectedIndex];
                    if (!opt) return '';
                    // Strip your availability hint like " [✓]"
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
                    if (!isAuto) {
                        previewEl.textContent = '(auto-title disabled)';
                        return;
                    }

                    const t = buildTitle();
                    previewEl.textContent = t || '(select Band + Date to preview)';
                }

                function updateWpTitleBox() {
                    const isAuto = autoTitle ? autoTitle.checked : true;
                    if (!isAuto) return;

                    const t = buildTitle();
                    if (!t) return;

                    // Only update the WP title field if it looks untouched / empty-ish.
                    if (wpTitleInput) {
                        const current = (wpTitleInput.value || wpTitleInput.textContent || '').trim();
                        if (!current || current.toLowerCase() === 'auto draft') {
                            if ('value' in wpTitleInput) wpTitleInput.value = t;
                            else wpTitleInput.textContent = t;
                        }
                    }
                }

                function onChange() {
                    updatePreview();
                    updateWpTitleBox(); // optional: comment this out if you only want preview
                }

                if (bandSel) bandSel.addEventListener('change', onChange);
                if (dateInput) dateInput.addEventListener('change', onChange);
                if (autoTitle) autoTitle.addEventListener('change', onChange);

                // initial
                updatePreview();
            })();
        </script>

    <?php
    }

    /**
     * Save Event Plan meta fields.
     */
    public function save_event_plan_meta($post_id, $post)
    {
        // Check nonce.
        if (
            ! isset($_POST['vms_event_plan_details_nonce']) ||
            ! wp_verify_nonce($_POST['vms_event_plan_details_nonce'], 'vms_save_event_plan_details')
        ) {
            return;
        }

        if (isset($_POST['vms_band_vendor_id'])) {
            update_post_meta($post_id, '_vms_band_vendor_id', absint($_POST['vms_band_vendor_id']));
        }
        if (isset($_POST['vms_agenda_text'])) {
            update_post_meta($post_id, '_vms_agenda_text', wp_kses_post($_POST['vms_agenda_text']));
        }

        // Avoid autosaves.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user capability.
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        // Sanitize fields.
        $event_date         = isset($_POST['vms_event_date']) ? sanitize_text_field($_POST['vms_event_date']) : '';
        $start_time         = isset($_POST['vms_start_time']) ? sanitize_text_field($_POST['vms_start_time']) : '';
        $end_time           = isset($_POST['vms_end_time']) ? sanitize_text_field($_POST['vms_end_time']) : '';
        if ($start_time === '') $start_time = '19:00'; // default start time
        if ($end_time === '')   $end_time   = '21:00'; // default end time
        $location_label     = isset($_POST['vms_location_label']) ? sanitize_text_field($_POST['vms_location_label']) : '';

        $venue_id = isset($_POST['vms_venue_id']) ? absint($_POST['vms_venue_id']) : 0;

        // Tickets / Add-ons settings
        $ga_price = isset($_POST['vms_price_ga']) ? floatval($_POST['vms_price_ga']) : 20;

        $enable_tables   = isset($_POST['vms_enable_tables']) ? '1' : '0';
        $enable_firepits = isset($_POST['vms_enable_firepits']) ? '1' : '0';
        $enable_pools    = isset($_POST['vms_enable_pools']) ? '1' : '0';

        $table_count   = isset($_POST['vms_table_count']) ? max(0, absint($_POST['vms_table_count'])) : 6;
        $firepit_count = isset($_POST['vms_firepit_count']) ? max(0, absint($_POST['vms_firepit_count'])) : 6;

        $table_price   = isset($_POST['vms_price_table']) ? floatval($_POST['vms_price_table']) : 30;
        $firepit_price = isset($_POST['vms_price_firepit']) ? floatval($_POST['vms_price_firepit']) : 30;
        $pool_price    = isset($_POST['vms_price_pool']) ? floatval($_POST['vms_price_pool']) : 10;

        $table_min_tickets   = isset($_POST['vms_min_tickets_per_table']) ? max(0, absint($_POST['vms_min_tickets_per_table'])) : 2;
        $firepit_min_tickets = isset($_POST['vms_min_tickets_per_firepit']) ? max(0, absint($_POST['vms_min_tickets_per_firepit'])) : 2;

        $comp_structure     = isset($_POST['vms_comp_structure']) ? sanitize_text_field($_POST['vms_comp_structure']) : 'flat_fee';
        $flat_fee_amount    = isset($_POST['vms_flat_fee_amount']) ? floatval($_POST['vms_flat_fee_amount']) : '';
        $door_split_percent = isset($_POST['vms_door_split_percent']) ? floatval($_POST['vms_door_split_percent']) : '';
        $comp_package_id = isset($_POST['vms_comp_package_id']) ? absint($_POST['vms_comp_package_id']) : 0;
        if ($comp_package_id > 0) {
            update_post_meta($post_id, '_vms_comp_package_id', $comp_package_id);
        } else {
            delete_post_meta($post_id, '_vms_comp_package_id');
        }

        $allow_vendor_propose = isset($_POST['vms_allow_vendor_propose']) ? '1' : '0';
        $proposal_min         = isset($_POST['vms_proposal_min']) ? floatval($_POST['vms_proposal_min']) : '';
        $proposal_max         = isset($_POST['vms_proposal_max']) ? floatval($_POST['vms_proposal_max']) : '';
        $proposal_cap         = isset($_POST['vms_proposal_cap']) ? floatval($_POST['vms_proposal_cap']) : '';

        $auto_title = isset($_POST['vms_auto_title']) ? '1' : '0';
        $auto_comp  = isset($_POST['vms_auto_comp']) ? '1' : '0';

        $fields = array(
            '_vms_event_date'         => $event_date,
            '_vms_start_time'         => $start_time,
            '_vms_end_time'           => $end_time,
            '_vms_location_label'     => $location_label,
            '_vms_venue_id'           => $venue_id,

            '_vms_price_ga' => $ga_price,

            '_vms_enable_tables' => $enable_tables,
            '_vms_enable_firepits' => $enable_firepits,
            '_vms_enable_pools' => $enable_pools,

            '_vms_table_count' => $table_count,
            '_vms_firepit_count' => $firepit_count,

            '_vms_price_table' => $table_price,
            '_vms_price_firepit' => $firepit_price,
            '_vms_price_pool' => $pool_price,

            '_vms_min_tickets_per_table' => $table_min_tickets,
            '_vms_min_tickets_per_firepit' => $firepit_min_tickets,

            '_vms_comp_structure'     => $comp_structure,
            '_vms_flat_fee_amount'    => $flat_fee_amount,
            '_vms_door_split_percent' => $door_split_percent,
            '_vms_allow_vendor_propose' => $allow_vendor_propose,
            '_vms_proposal_min'       => $proposal_min,
            '_vms_proposal_max'       => $proposal_max,
            '_vms_proposal_cap'       => $proposal_cap,
            '_vms_auto_title'         => $auto_title,
            '_vms_auto_comp'          => $auto_comp,
        );

        foreach ($fields as $meta_key => $value) {
            if ($value === '' || $value === null) {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        if ($auto_title === '1') {
            vms_force_event_plan_title($post_id);
        }

        if ($auto_comp === '1') {
            vms_maybe_apply_band_comp_defaults_to_plan($post_id);
        }

        $comp_package_id = isset($_POST['vms_comp_package_id']) ? absint($_POST['vms_comp_package_id']) : 0;

        // Always persist selection (so dropdown stays selected even if you don't click Apply)
        if ($comp_package_id > 0) {
            update_post_meta($post_id, '_vms_comp_package_id', $comp_package_id);
        } else {
            delete_post_meta($post_id, '_vms_comp_package_id');
        }

        // Handle workflow status (Save Draft / Mark Ready / Publish Now).
        if (isset($_POST['vms_event_plan_action'])) {
            $action = sanitize_text_field($_POST['vms_event_plan_action']);

            $current_status = get_post_meta($post_id, '_vms_event_plan_status', true);
            if (empty($current_status)) {
                $current_status = 'draft';
            }

            $new_status = $current_status;

            switch ($action) {
                case 'save_draft':
                    // Very permissive – just set draft.
                    $new_status = 'draft';
                    vms_add_admin_notice('Event plan saved as Draft.', 'success');
                    break;

                case 'mark_ready':
                    $errors = vms_validate_event_plan($post_id);

                    if (empty($errors)) {
                        // Auto-set title if possible.
                        vms_maybe_autoset_event_plan_title($post_id);

                        $new_status = 'ready';
                        vms_add_admin_notice('Event plan marked Ready.', 'success');
                    } else {
                        $new_status = 'draft';
                        vms_add_admin_notice(
                            'Cannot mark Ready: ' . implode(' ', $errors),
                            'error'
                        );
                    }
                    break;

                case 'publish_now':
                    // Only allow publish if already ready or published.
                    if (!in_array($current_status, array('ready', 'published'), true)) {
                        vms_add_admin_notice('Event must be Ready before publishing.', 'error');
                        break;
                    }

                    $errors = vms_validate_event_plan($post_id);
                    if (!empty($errors)) {
                        vms_add_admin_notice(
                            'Cannot publish: ' . implode(' ', $errors),
                            'error'
                        );
                        break;
                    }

                    // Stub publish to Events Calendar (replace later).
                    $published = vms_publish_event_to_calendar($post_id, $post);

                    if ($published) {
                        $new_status = 'published';
                        vms_add_admin_notice('Event published successfully.', 'success');

                        // After TEC publish success
                        $result = vms_publish_products_from_plan($post_id);

                        if (!empty($result['ok'])) {
                            vms_add_admin_notice(
                                sprintf('Woo products updated. %d created, %d updated.', (int)$result['created'], (int)$result['updated']),
                                'success'
                            );
                        } else {
                            vms_add_admin_notice(
                                'Woo product publish failed: ' . (string)($result['error'] ?? 'unknown error'),
                                'error'
                            );
                        }
                    } else {
                        vms_add_admin_notice(
                            'Failed to publish event to calendar. Please check settings.',
                            'error'
                        );
                    }
                    break;

                case 'apply_band_defaults':
                    vms_apply_band_comp_defaults_to_plan($post_id);
                    vms_add_admin_notice('Band defaults applied (where available).', 'success');
                    break;

                case 'apply_comp_package':
                    if ($venue_id <= 0) {
                        vms_add_admin_notice('Please select a Venue first, then apply the package.', 'error');
                        break;
                    }

                    if ($comp_package_id <= 0) {
                        vms_add_admin_notice('Please select a comp package first.', 'error');
                        break;
                    }

                    $ok = vms_apply_comp_package_to_plan((int)$post_id, (int)$comp_package_id);
                    if ($ok) {
                        vms_add_admin_notice('Comp package applied and snapshotted for this event plan.', 'success');
                    } else {
                        vms_add_admin_notice('Failed to apply comp package. (Check package type/meta.)', 'error');
                    }
                    break;
            }

            // 1. Update VMS plan status
            update_post_meta($post_id, '_vms_event_plan_status', $new_status);

            // 2. Sync TEC status (VMS → TEC)
            vms_sync_tec_status_from_plan($post_id);
        }
    }
}

function vms_validate_event_plan($post_id)
{
    $errors = array();

    // Core fields
    $event_date = get_post_meta($post_id, '_vms_event_date', true);

    $start_time = get_post_meta($post_id, '_vms_start_time', true);
    $end_time   = get_post_meta($post_id, '_vms_end_time', true);

    if (empty($event_date)) {
        $errors[] = 'Event date is required.';
    }

    if (empty($start_time) || empty($end_time)) {
        $errors[] = 'Start time and end time are required.';
    } else {
        $start_ts = strtotime($event_date . ' ' . $start_time);
        $end_ts   = strtotime($event_date . ' ' . $end_time);

        if (!$start_ts || !$end_ts) {
            $errors[] = 'Start time or end time is not a valid time.';
        } elseif ($end_ts <= $start_ts) {
            $errors[] = 'End time must be after start time.';
        }
    }

    // Compensation structure sanity checks
    $comp_structure     = get_post_meta($post_id, '_vms_comp_structure', true);
    $flat_fee_amount    = get_post_meta($post_id, '_vms_flat_fee_amount', true);
    $door_split_percent = get_post_meta($post_id, '_vms_door_split_percent', true);

    if (empty($comp_structure)) {
        $comp_structure = 'flat_fee';
    }

    // If flat fee is involved, require a non-zero amount
    if (in_array($comp_structure, array('flat_fee', 'flat_fee_door_split'), true)) {
        if ($flat_fee_amount === '' || $flat_fee_amount === null) {
            $errors[] = 'Flat fee amount is required for this compensation structure.';
        } elseif (!is_numeric($flat_fee_amount) || (float) $flat_fee_amount <= 0) {
            $errors[] = 'Flat fee amount must be a positive number.';
        }
    }

    // If door split is involved, require a reasonable percentage
    if (in_array($comp_structure, array('door_split', 'flat_fee_door_split'), true)) {
        if ($door_split_percent === '' || $door_split_percent === null) {
            $errors[] = 'Door split percentage is required for this compensation structure.';
        } elseif (!is_numeric($door_split_percent)) {
            $errors[] = 'Door split percentage must be a number.';
        } else {
            $pct = (float) $door_split_percent;
            if ($pct <= 0 || $pct > 100) {
                $errors[] = 'Door split percentage must be between 1 and 100.';
            }
        }
    }

    // Optional: proposal sanity (only if you’re using those fields)
    $allow_vendor_propose = get_post_meta($post_id, '_vms_allow_vendor_propose', true);
    if ($allow_vendor_propose) {
        $proposal_min = get_post_meta($post_id, '_vms_proposal_min', true);
        $proposal_max = get_post_meta($post_id, '_vms_proposal_max', true);
        $proposal_cap = get_post_meta($post_id, '_vms_proposal_cap', true);

        // Only validate if values are provided
        if ($proposal_min !== '' && $proposal_max !== '' && is_numeric($proposal_min) && is_numeric($proposal_max)) {
            if ((float) $proposal_min > (float) $proposal_max) {
                $errors[] = 'Proposal minimum cannot be greater than proposal maximum.';
            }
        }

        if (
            $proposal_cap !== '' && is_numeric($proposal_cap) &&
            $proposal_max !== '' && is_numeric($proposal_max)
        ) {
            if ((float) $proposal_cap < (float) $proposal_max) {
                $errors[] = 'Proposal cap should be greater than or equal to the proposal maximum.';
            }
        }
    }

    // ----------------------------------------
    // Band selection + availability
    // ----------------------------------------
    $band_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);

    // Must have a band to be Ready.
    if (!$band_id) {
        $errors[] = 'A band must be selected before marking this event Ready.';
        return $errors;
    }

    // If we also have a date, enforce that the band is not explicitly "unavailable".
    if ($event_date) {
        $availability = vms_get_vendor_availability_for_date($band_id, $event_date); // uses main plugin helper

        if ($availability === 'unavailable') {
            $band_post = get_post($band_id);
            $band_name = $band_post ? $band_post->post_title : 'Selected band';

            $nice_date = date_i18n('M j, Y', strtotime($event_date));

            $errors[] = sprintf(
                '%s is marked Not Available on %s.',
                $band_name,
                $nice_date
            );
        }
    }

    // Require vendor tax profile completion before Ready/Publish
    $band_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
    if ($band_id > 0) {
        $missing = function_exists('vms_vendor_tax_profile_missing_items')
            ? vms_vendor_tax_profile_missing_items($band_id)
            : [];

        if (!empty($missing)) {
            $vendor_name = get_the_title($band_id);
            $errors[] = sprintf(
                'Band "%s" is missing required Tax Profile items: %s.',
                $vendor_name ? $vendor_name : '#' . $band_id,
                implode(', ', $missing)
            );
        }
    } else {
        // If you already require a band to mark Ready, ignore this.
        // If not, you can decide whether band is required or not.
    }
    return $errors;
}

/**
 * Auto-generate an Event Plan title like "Band Name — May 9, 2026"
 * if the title is currently empty or still "Auto Draft".
 */
function vms_maybe_autoset_event_plan_title($post_id)
{
    $post = get_post($post_id);
    if (! $post) {
        return;
    }

    $current_title = trim($post->post_title);

    // Only auto-set if title is empty or still the default "Auto Draft".
    if ($current_title && strcasecmp($current_title, 'Auto Draft') !== 0) {
        return;
    }

    // Get band name.
    $band_id   = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
    $band_post = $band_id ? get_post($band_id) : null;
    $band_name = $band_post ? $band_post->post_title : '';

    // Get event date.
    $event_date = get_post_meta($post_id, '_vms_event_date', true); // "YYYY-MM-DD"
    $formatted_date = '';

    if ($event_date) {
        $ts = strtotime($event_date);
        if ($ts) {
            // Example: "May 9, 2026"
            $formatted_date = date_i18n('F j, Y', $ts);
        } else {
            $formatted_date = $event_date; // fallback raw
        }
    }

    if (! $band_name || ! $formatted_date) {
        // Not enough info to build a nice title.
        return;
    }

    $new_title = $band_name . ' — ' . $formatted_date;

    // Update post title + slug.
    wp_update_post(array(
        'ID'         => $post_id,
        'post_title' => $new_title,
        'post_name'  => sanitize_title($new_title),
    ));

    // Optional: add a little notice so you see it happened.
    vms_add_admin_notice(
        sprintf('Event title set to "%s".', $new_title),
        'success'
    );
}

function vms_add_admin_notice($message, $type = 'success')
{
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }

    $notices = get_transient('vms_event_plan_notices_' . $user_id);
    if (!is_array($notices)) {
        $notices = array();
    }

    $notices[] = array(
        'type'    => $type,  // 'success' or 'error'
        'message' => $message,
    );

    // Keep for a minute – enough to survive the redirect.
    set_transient('vms_event_plan_notices_' . $user_id, $notices, 60);
}

add_action('admin_notices', 'vms_render_admin_notices');
function vms_render_admin_notices()
{
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }

    $notices = get_transient('vms_event_plan_notices_' . $user_id);
    if (!is_array($notices) || empty($notices)) {
        return;
    }

    delete_transient('vms_event_plan_notices_' . $user_id);

    foreach ($notices as $notice) {
        $class = ($notice['type'] === 'error') ? 'notice notice-error' : 'notice notice-success';
        printf(
            '<div class="%s"><p>%s</p></div>',
            esc_attr($class),
            esc_html($notice['message'])
        );
    }
}

/**
 * Create or update a The Events Calendar event for this Event Plan.
 *
 * @param int   $post_id VMS Event Plan ID.
 * @param WP_Post $post  VMS Event Plan post object.
 * @return bool          True on success, false on failure.
 */
function vms_publish_event_to_calendar($post_id, $post)
{
    // Make sure TEC functions exist.
    if (! function_exists('tribe_create_event') || ! function_exists('tribe_update_event')) {
        vms_add_admin_notice(
            'The Events Calendar functions are not available. Is the plugin active?',
            'error'
        );
        return false;
    }

    $args = vms_build_tec_event_args($post_id);
    if (empty($args)) {
        vms_add_admin_notice(
            'Unable to build event data for The Events Calendar.',
            'error'
        );
        return false;
    }

    // See if we already have a linked TEC event.
    $existing_tec_id = (int) get_post_meta($post_id, '_vms_tec_event_id', true);
    $tec_event_id    = 0;

    if ($existing_tec_id > 0) {
        // Update existing TEC event.
        $updated_id = tribe_update_event($existing_tec_id, $args);

        if ($updated_id && ! is_wp_error($updated_id)) {
            $tec_event_id = $updated_id;
        } else {
            // If update fails, we could optionally try to create a fresh event.
            vms_add_admin_notice(
                'Failed to update existing Events Calendar event. Will attempt to create a new one.',
                'error'
            );
        }
    }

    // If we don't have a valid TEC event ID yet, create a new event.
    if (! $tec_event_id) {
        $created_id = tribe_create_event($args);
        if ($created_id && ! is_wp_error($created_id)) {
            $tec_event_id = $created_id;
            update_post_meta($post_id, '_vms_tec_event_id', $tec_event_id);
        } else {
            vms_add_admin_notice(
                'Failed to create event in The Events Calendar.',
                'error'
            );
            return false;
        }
    }

    // Sync featured image from Event Plan to TEC event (optional but nice).
    $thumb_id = get_post_thumbnail_id($post_id);
    if ($thumb_id) {
        set_post_thumbnail($tec_event_id, $thumb_id);
    }

    // You could also sync categories, tags, or venues here if desired.

    // Save a link back for convenience (optional).
    $tec_permalink = get_permalink($tec_event_id);
    if ($tec_permalink) {
        update_post_meta($post_id, '_vms_tec_event_url', esc_url_raw($tec_permalink));
    }

    return true;
}

/**
 * Add a "Plan Status" column to the Event Plans list table.
 */
add_filter('manage_vms_event_plan_posts_columns', 'vms_add_event_plan_status_column');
function vms_add_event_plan_status_column($columns)
{
    $new = array();

    foreach ($columns as $key => $label) {
        // Insert our column just before the Date column.
        if ($key === 'date') {
            $new['vms_plan_status'] = __('Plan Status', 'vms');
        }
        $new[$key] = $label;
    }

    // If for some reason "date" wasn't there, make sure we add ours.
    if (!isset($new['vms_plan_status'])) {
        $new['vms_plan_status'] = __('Plan Status', 'vms');
    }

    return $new;
}

/**
 * Render the "Plan Status" column cells.
 */
add_action('manage_vms_event_plan_posts_custom_column', 'vms_render_event_plan_status_column', 10, 2);
function vms_render_event_plan_status_column($column, $post_id)
{
    if ($column !== 'vms_plan_status') {
        return;
    }

    $status = get_post_meta($post_id, '_vms_event_plan_status', true);
    if (!$status) {
        $status = 'draft';
    }

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

    $label = isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    $class = isset($classes[$status]) ? $classes[$status] : 'vms-pill-grey';

    echo '<span class="vms-status-pill ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
}

/**
 * Add basic styling for the Plan Status pills on the Event Plan list screen.
 */
add_action('admin_head-edit.php', 'vms_event_plan_status_column_css');
function vms_event_plan_status_column_css()
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'vms_event_plan') {
        return;
    }
    ?>
    <style>
        .column-vms_plan_status {
            width: 110px;
        }

        .vms-status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.4;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .vms-pill-grey {
            background: #e5e7eb;
            /* gray-200 */
            color: #374151;
            /* gray-700 */
        }

        .vms-pill-yellow {
            background: #fef3c7;
            /* amber-100 */
            color: #92400e;
            /* amber-800 */
        }

        .vms-pill-green {
            background: #d1fae5;
            /* emerald-100 */
            color: #065f46;
            /* emerald-800 */
        }
    </style>
<?php
}

/**
 * Build The Events Calendar event args array from a VMS Event Plan.
 *
 * @param int $post_id  The VMS Event Plan post ID.
 * @return array        Args suitable for tribe_create_event() / tribe_update_event().
 */
function vms_build_tec_event_args($post_id)
{
    $event_plan = get_post($post_id);
    if (! $event_plan) {
        return array();
    }

    // Event date + times from VMS meta.
    $event_date  = get_post_meta($post_id, '_vms_event_date', true);      // "YYYY-MM-DD"
    $start_time  = get_post_meta($post_id, '_vms_start_time', true);      // "HH:MM" (24h)
    $end_time    = get_post_meta($post_id, '_vms_end_time', true);        // "HH:MM" (24h)

    // Band / headliner.
    $band_id   = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
    $band_post = $band_id ? get_post($band_id) : null;
    $band_name = $band_post ? $band_post->post_title : '';

    // Agenda / description (optional; use if you add an agenda meta field).
    $agenda_text = get_post_meta($post_id, '_vms_agenda_text', true);

    // Fallback to Event Plan content if no agenda meta is set.
    if (empty($agenda_text)) {
        $agenda_text = $event_plan->post_content;
    }

    // Build event title.
    $title = trim($event_plan->post_title);
    if ($band_name && $event_date) {
        $ts             = strtotime($event_date);
        $formatted_date = $ts ? date_i18n('F j, Y', $ts) : $event_date;
        $default_title  = $band_name . ' — ' . $formatted_date;

        if (! $title || strcasecmp($title, 'Auto Draft') === 0) {
            $title = $default_title;
        }
    }

    // Get VMS plan status
    $plan_status = vms_get_event_plan_status($post_id);
    $tec_status  = vms_map_plan_status_to_tec_post_status($plan_status);

    $args = array(
        'post_type'    => 'tribe_events',
        'post_title'   => $title,
        'post_content' => $agenda_text,
        'post_status'  => $tec_status,
    );

    // Dates: TEC expects date-only here.
    if ($event_date) {
        $args['EventStartDate'] = $event_date;  // "YYYY-MM-DD"
        $args['EventEndDate']   = $event_date;  // same-day events for concerts
    }

    // Times: TEC expects separate hour/minute fields, 00–23 if no meridian.
    if ($start_time) {
        $parts = explode(':', $start_time);
        $hour  = isset($parts[0]) ? (int) $parts[0] : 0;
        $min   = isset($parts[1]) ? (int) $parts[1] : 0;

        $args['EventStartHour']   = sprintf('%02d', $hour); // "19"
        $args['EventStartMinute'] = sprintf('%02d', $min);  // "00"
    }

    if ($end_time) {
        $parts = explode(':', $end_time);
        $hour  = isset($parts[0]) ? (int) $parts[0] : 0;
        $min   = isset($parts[1]) ? (int) $parts[1] : 0;

        $args['EventEndHour']   = sprintf('%02d', $hour);
        $args['EventEndMinute'] = sprintf('%02d', $min);
    }

    // If we have times, explicitly mark as not all-day.
    if ($start_time || $end_time) {
        $args['EventAllDay'] = false;
    }

    return $args;
}

/**
 * Add "View in Calendar" row action link on the Event Plans list page.
 */
add_filter('post_row_actions', 'vms_event_plan_row_actions', 10, 2);
function vms_event_plan_row_actions($actions, $post)
{
    if ($post->post_type !== 'vms_event_plan') {
        return $actions;
    }

    $tec_url = get_post_meta($post->ID, '_vms_tec_event_url', true);
    if ($tec_url) {
        $actions['vms_view_tec'] =
            '<a href="' . esc_url($tec_url) . '" target="_blank" rel="noopener">'
            . esc_html__('View in Calendar', 'vms')
            . '</a>';
    }

    return $actions;
}

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-vms_event_plan') return;

    $venue_id = vms_get_current_venue_id();
    if ($venue_id <= 0) return;

    $meta_query = (array) $query->get('meta_query');
    $meta_query[] = array(
        'key'   => '_vms_venue_id',
        'value' => $venue_id,
        'compare' => '=',
        'type'  => 'NUMERIC',
    );
    $query->set('meta_query', $meta_query);
});

// Bootstrap admin hooks for Event Plans.
if (is_admin()) {
    new VMS_Admin_Event_Plans();
}

/**
 * Route B: Publish/Update Woo products from an Event Plan (idempotent via _vms_wc_product_map).
 *
 * Stores mapping on plan:
 *   _vms_wc_product_map = [ 'ga' => 123, 'vip' => 124, 'firepit_01' => 130, ... ]
 */
function vms_publish_products_from_plan(int $plan_id): array
{
    if (!class_exists('WC_Product')) {
        return array('ok' => false, 'error' => 'WooCommerce is not active.');
    }

    $event_date = (string) get_post_meta($plan_id, '_vms_event_date', true); // YYYY-MM-DD
    if (!$event_date) {
        return array('ok' => false, 'error' => 'Missing event date.');
    }

    $band_id   = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
    $band_name = $band_id ? get_the_title($band_id) : '';
    if (!$band_name) {
        $band_name = 'Event'; // fallback
    }

    // Format date for titles
    $ts = strtotime($event_date);
    $nice_date = $ts ? date_i18n('M j, Y', $ts) : $event_date;

    // Link to TEC event if exists (nice for later)
    $tec_event_id = (int) get_post_meta($plan_id, '_vms_tec_event_id', true);

    // Load existing map
    $map = get_post_meta($plan_id, '_vms_wc_product_map', true);
    if (!is_array($map)) $map = array();

    // Build blueprint of products to ensure exist.
    // NOTE: Start simple (GA + Fire Pits + Pools), then we’ll extend to VIP/tiers easily.
    $blueprint = vms_build_product_blueprint_for_plan($plan_id, $nice_date, $band_name);

    $created = 0;
    $updated = 0;

    foreach ($blueprint as $key => $spec) {
        $existing_id = isset($map[$key]) ? (int) $map[$key] : 0;

        $product_id = vms_upsert_plan_product($plan_id, $existing_id, $spec, $tec_event_id);

        if (!$product_id) {
            // If one fails, keep going, but report it
            continue;
        }

        if ($existing_id && $existing_id === $product_id) {
            $updated++;
        } else {
            $created++;
        }

        $map[$key] = (int) $product_id;
    }

    // Optional: if something used to exist but no longer in blueprint, disable it
    // (seasonal toggles)
    foreach ($map as $key => $pid) {
        if (!isset($blueprint[$key])) {
            vms_disable_plan_product((int)$pid);
        }
    }

    update_post_meta($plan_id, '_vms_wc_product_map', $map);

    return array('ok' => true, 'created' => $created, 'updated' => $updated, 'map' => $map);
}

/**
 * Build a product blueprint for the plan.
 * Each item defines: name, price, type, tags, meta, stock rules, etc.
 */
function vms_build_product_blueprint_for_plan(int $plan_id, string $nice_date, string $band_name): array
{

    $ga_price = (float) get_post_meta($plan_id, '_vms_price_ga', true);
    if ($ga_price <= 0) $ga_price = 20.00;

    $enable_tables   = get_post_meta($plan_id, '_vms_enable_tables', true) === '1';
    $enable_firepits = get_post_meta($plan_id, '_vms_enable_firepits', true) === '1';
    $enable_pools    = get_post_meta($plan_id, '_vms_enable_pools', true) === '1';

    $table_count   = (int) get_post_meta($plan_id, '_vms_table_count', true);
    if ($table_count < 0) $table_count = 0;
    if ($table_count === 0) $table_count = 6;

    $firepit_count = (int) get_post_meta($plan_id, '_vms_firepit_count', true);
    if ($firepit_count < 0) $firepit_count = 0;
    if ($firepit_count === 0) $firepit_count = 6;

    $table_price   = (float) get_post_meta($plan_id, '_vms_price_table', true);
    if ($table_price <= 0) $table_price = 30.00;

    $firepit_price = (float) get_post_meta($plan_id, '_vms_price_firepit', true);
    if ($firepit_price <= 0) $firepit_price = 30.00;

    $pool_price    = (float) get_post_meta($plan_id, '_vms_price_pool', true);
    if ($pool_price <= 0) $pool_price = 10.00;

    $table_min_tickets = (int) get_post_meta($plan_id, '_vms_min_tickets_per_table', true);
    if ($table_min_tickets < 0) $table_min_tickets = 0;
    if ($table_min_tickets === 0) $table_min_tickets = 2;

    $firepit_min_tickets = (int) get_post_meta($plan_id, '_vms_min_tickets_per_firepit', true);
    if ($firepit_min_tickets < 0) $firepit_min_tickets = 0;
    if ($firepit_min_tickets === 0) $firepit_min_tickets = 2;

    $items = array();

    // --- GA Ticket ---
    $items['ga'] = array(
        'name'        => "{$nice_date} — {$band_name} — GA Ticket",
        'price'       => $ga_price,
        'is_ticket'   => true,
        'sku_suffix'  => 'GA',
        'meta'        => array(
            '_sr_addon_qualifier' => 'yes',
        ),
        'tags'        => array('ticket'),
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
                'tags'       => array(),
                'stock_qty'  => 1,
            );
        }
    }

    // --- Fire Pits (unique per pit number, qty=1) ---
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
                'tags'       => array(),
                'stock_qty'  => 1,
            );
        }
    }

    // --- Kiddie Pools (example add-on, no minimum tickets) ---
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

/**
 * Create/update a single product for a plan.
 */
function vms_upsert_plan_product(int $plan_id, int $existing_product_id, array $spec, int $tec_event_id = 0): int
{
    $name  = isset($spec['name']) ? (string) $spec['name'] : '';
    $price = isset($spec['price']) ? (float) $spec['price'] : 0.0;

    if ($name === '') return 0;

    $product = null;
    $is_update = false;

    if ($existing_product_id > 0) {
        $product = wc_get_product($existing_product_id);
        if ($product) $is_update = true;
    }

    if (!$product) {
        $product = new WC_Product_Simple();
    }

    // Core fields
    $product->set_name($name);
    $product->set_regular_price($price);
    $product->set_status('publish'); // we can change to 'draft' until Ready if you prefer
    $product->set_catalog_visibility('visible');

    // Stock rules (optional)
    if (isset($spec['stock_qty'])) {
        $product->set_manage_stock(true);
        $product->set_stock_quantity((int)$spec['stock_qty']);
        $product->set_stock_status(((int)$spec['stock_qty'] > 0) ? 'instock' : 'outofstock');
        $product->set_sold_individually(true); // important for fire pits
    } else {
        // Tickets often are unlimited; keep stock off unless you want caps
        $product->set_manage_stock(false);
    }

    // Save to get an ID
    $product_id = $product->save();
    if (!$product_id) return 0;

    // Link product back to plan + TEC (super helpful for searching later)
    update_post_meta($product_id, '_vms_event_plan_id', $plan_id);
    if ($tec_event_id > 0) {
        update_post_meta($product_id, '_vms_tec_event_id', $tec_event_id);
    }

    // Tags (tickets get 'ticket' tag; add-ons do not)
    $tags = isset($spec['tags']) && is_array($spec['tags']) ? $spec['tags'] : array();
    if (!empty($tags)) {
        wp_set_object_terms($product_id, $tags, 'product_tag', false);
    } else {
        // Ensure add-ons do NOT accidentally keep the ticket tag
        wp_set_object_terms($product_id, array(), 'product_tag', false);
    }

    // Meta
    if (isset($spec['meta']) && is_array($spec['meta'])) {
        foreach ($spec['meta'] as $k => $v) {
            update_post_meta($product_id, (string)$k, $v);
        }
    }

    // Convenience meta to help order readability / searching
    update_post_meta($product_id, '_vms_product_role', !empty($spec['is_ticket']) ? 'ticket' : 'addon');

    return (int) $product_id;
}

/**
 * Disable a product that is no longer in the blueprint (seasonal off, removed, etc.)
 * You can choose 'draft', 'private', or stock out.
 */
function vms_disable_plan_product(int $product_id): void
{
    $p = wc_get_product($product_id);
    if (!$p) return;

    // safest: draft so it can't be purchased
    $p->set_status('draft');
    $p->save();
}

function vms_force_event_plan_title($post_id)
{
    static $running = false;
    if ($running) return; // prevents recursion

    $band_id    = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
    $event_date = (string) get_post_meta($post_id, '_vms_event_date', true);

    if (!$band_id || !$event_date) return;

    $band_name = get_the_title($band_id);
    if (!$band_name) return;

    $ts = strtotime($event_date);
    $formatted = $ts ? date_i18n('F j, Y', $ts) : $event_date;

    $new_title = $band_name . ' — ' . $formatted;

    // Don’t update if it’s already correct (saves time + avoids extra saves)
    $current = get_post_field('post_title', $post_id);
    if ((string)$current === (string)$new_title) return;

    $running = true;

    wp_update_post(array(
        'ID'         => $post_id,
        'post_title' => $new_title,
        'post_name'  => sanitize_title($new_title),
    ));

    $running = false;
}


function vms_maybe_apply_band_comp_defaults_to_plan($plan_id)
{
    // Only fill blanks
    $has_structure = get_post_meta($plan_id, '_vms_comp_structure', true);
    $has_flat      = get_post_meta($plan_id, '_vms_flat_fee_amount', true);
    $has_split     = get_post_meta($plan_id, '_vms_door_split_percent', true);

    if ($has_structure || $has_flat || $has_split) return;

    vms_apply_band_comp_defaults_to_plan($plan_id);
}

function vms_apply_band_comp_defaults_to_plan($plan_id)
{
    $band_id = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
    if (!$band_id) return;

    $structure = get_post_meta($band_id, '_vms_default_comp_structure', true);
    $flat      = get_post_meta($band_id, '_vms_default_flat_fee_amount', true);
    $split     = get_post_meta($band_id, '_vms_default_door_split_percent', true);

    if ($structure) update_post_meta($plan_id, '_vms_comp_structure', sanitize_text_field($structure));
    if ($flat !== '' && $flat !== null) update_post_meta($plan_id, '_vms_flat_fee_amount', (float) $flat);
    if ($split !== '' && $split !== null) update_post_meta($plan_id, '_vms_door_split_percent', (float) $split);
}
