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
        'show_in_menu'  => 'vms-dashboard',
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
        $location_label      = get_post_meta($post->ID, '_vms_location_label', true);

        $venue_id            = (int) get_post_meta($post->ID, '_vms_venue_id', true);

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
            <label for="vms_location_label"><strong><?php esc_html_e('Location / Resource', 'vms'); ?></strong></label><br />
            <input type="text" id="vms_location_label" name="vms_location_label" class="regular-text"
                value="<?php echo esc_attr($location_label); ?>" />
            <br /><span class="description">
                <?php esc_html_e('Example: Main Stage, Patio, Food Truck Row, etc.', 'vms'); ?>
            </span>
        </p>

        <hr />

        <h4><?php esc_html_e('Compensation Structure', 'vms'); ?></h4>

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
        $location_label     = isset($_POST['vms_location_label']) ? sanitize_text_field($_POST['vms_location_label']) : '';

        $venue_id = isset($_POST['vms_venue_id']) ? absint($_POST['vms_venue_id']) : 0;

        $comp_structure     = isset($_POST['vms_comp_structure']) ? sanitize_text_field($_POST['vms_comp_structure']) : 'flat_fee';
        $flat_fee_amount    = isset($_POST['vms_flat_fee_amount']) ? floatval($_POST['vms_flat_fee_amount']) : '';
        $door_split_percent = isset($_POST['vms_door_split_percent']) ? floatval($_POST['vms_door_split_percent']) : '';

        $allow_vendor_propose = isset($_POST['vms_allow_vendor_propose']) ? '1' : '0';
        $proposal_min         = isset($_POST['vms_proposal_min']) ? floatval($_POST['vms_proposal_min']) : '';
        $proposal_max         = isset($_POST['vms_proposal_max']) ? floatval($_POST['vms_proposal_max']) : '';
        $proposal_cap         = isset($_POST['vms_proposal_cap']) ? floatval($_POST['vms_proposal_cap']) : '';

        $fields = array(
            '_vms_event_date'         => $event_date,
            '_vms_start_time'         => $start_time,
            '_vms_end_time'           => $end_time,
            '_vms_location_label'     => $location_label,
            '_vms_venue_id'           => $venue_id,
            '_vms_comp_structure'     => $comp_structure,
            '_vms_flat_fee_amount'    => $flat_fee_amount,
            '_vms_door_split_percent' => $door_split_percent,
            '_vms_allow_vendor_propose' => $allow_vendor_propose,
            '_vms_proposal_min'       => $proposal_min,
            '_vms_proposal_max'       => $proposal_max,
            '_vms_proposal_cap'       => $proposal_cap,
        );

        foreach ($fields as $meta_key => $value) {
            if ($value === '' || $value === null) {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
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
                    } else {
                        vms_add_admin_notice(
                            'Failed to publish event to calendar. Please check settings.',
                            'error'
                        );
                    }
                    break;
            }

            // 1. Update VMS plan status
            update_post_meta($post_id, '_vms_event_plan_status', $new_status);

            // 2. Sync TEC status (VMS → TEC)
            vms_sync_tec_status_from_plan($post_id);

            // 3. If publishing, sync TEC content/title/etc
            if ($new_status === 'published') {
                vms_publish_event_to_calendar($post_id, $post);
            }
            if (isset($_POST['vms_band_vendor_id'])) {
                update_post_meta($post_id, '_vms_band_vendor_id', absint($_POST['vms_band_vendor_id']));
            }
            if (isset($_POST['vms_agenda_text'])) {
                update_post_meta($post_id, '_vms_agenda_text', wp_kses_post($_POST['vms_agenda_text']));
            }
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
