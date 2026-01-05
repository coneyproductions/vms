<?php


// ============================
// VMS Rating custom post type
// ============================
add_action('init', 'vms_register_rating_cpt');
function vms_register_rating_cpt()
{

    $labels = array(
        'name'               => __('Ratings', 'vms'),
        'singular_name'      => __('Rating', 'vms'),
        'menu_name'          => __('Ratings', 'vms'),
        'name_admin_bar'     => __('Rating', 'vms'),
        'add_new'            => __('Add New', 'vms'),
        'add_new_item'       => __('Add New Rating', 'vms'),
        'new_item'           => __('New Rating', 'vms'),
        'edit_item'          => __('Edit Rating', 'vms'),
        'view_item'          => __('View Rating', 'vms'),
        'all_items'          => __('All Ratings', 'vms'),
        'search_items'       => __('Search Ratings', 'vms'),
        'not_found'          => __('No ratings found.', 'vms'),
        'not_found_in_trash' => __('No ratings found in Trash.', 'vms'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,            // internal only
        'show_ui'            => true,             // visible in admin
        'show_in_menu'       => 'vms-dashboard',
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-star-half',
        'supports'           => array('title', 'editor'), // editor = comment body
        'has_archive'        => false,
        'capability_type'    => 'post',
    );

    register_post_type('vms_rating', $args);
}

// ============================
// Rating Details meta box
// ============================
add_action('add_meta_boxes', 'vms_add_rating_details_metabox');
function vms_add_rating_details_metabox()
{
    add_meta_box(
        'vms_rating_details',
        __('Rating Details', 'vms'),
        'vms_render_rating_details_metabox',
        'vms_rating',
        'normal',
        'high'
    );
}

function vms_render_rating_details_metabox($post)
{
    wp_nonce_field('vms_save_rating_details', 'vms_rating_details_nonce');

    $band_id    = (int) get_post_meta($post->ID, '_vms_band_id', true);
    $event_id   = (int) get_post_meta($post->ID, '_vms_event_id', true);
    $stars      = (int) get_post_meta($post->ID, '_vms_rating_value', true);
    $rev_name   = get_post_meta($post->ID, '_vms_reviewer_name', true);
    $rev_email  = get_post_meta($post->ID, '_vms_reviewer_email', true);
    $verified   = (int) get_post_meta($post->ID, '_vms_verified_attendance', true);

    // Load bands (vendors)
    $bands = get_posts(array(
        'post_type'      => 'vms_vendor',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    // Load recent TEC events (limit for sanity)
    $events = get_posts(array(
        'post_type'      => 'tribe_events',
        'posts_per_page' => 30,
        'orderby'        => 'event_date',
        'order'          => 'DESC',
    ));
?>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="vms_band_id"><?php esc_html_e('Band / Vendor', 'vms'); ?></label></th>
            <td>
                <select id="vms_band_id" name="vms_band_id" style="min-width:260px;">
                    <option value=""><?php esc_html_e('-- Select Band --', 'vms'); ?></option>
                    <?php foreach ($bands as $band) : ?>
                        <option value="<?php echo esc_attr($band->ID); ?>"
                            <?php selected($band_id, $band->ID); ?>>
                            <?php echo esc_html($band->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="vms_event_id"><?php esc_html_e('Event', 'vms'); ?></label></th>
            <td>
                <select id="vms_event_id" name="vms_event_id" style="min-width:260px;">
                    <option value=""><?php esc_html_e('-- Select Event --', 'vms'); ?></option>
                    <?php foreach ($events as $event) : ?>
                        <?php
                        $ts   = strtotime($event->post_date);
                        $date = $ts ? date_i18n('M j, Y', $ts) : '';
                        ?>
                        <option value="<?php echo esc_attr($event->ID); ?>"
                            <?php selected($event_id, $event->ID); ?>>
                            <?php echo esc_html($event->post_title . ($date ? " ({$date})" : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="vms_rating_value"><?php esc_html_e('Rating (Stars)', 'vms'); ?></label></th>
            <td>
                <select id="vms_rating_value" name="vms_rating_value">
                    <option value=""><?php esc_html_e('-- Select --', 'vms'); ?></option>
                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                        <option value="<?php echo esc_attr($i); ?>"
                            <?php selected($stars, $i); ?>>
                            <?php echo esc_html($i . ' ★'); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="vms_reviewer_name"><?php esc_html_e('Reviewer Name', 'vms'); ?></label></th>
            <td>
                <input type="text" id="vms_reviewer_name" name="vms_reviewer_name"
                    class="regular-text"
                    value="<?php echo esc_attr($rev_name); ?>" />
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="vms_reviewer_email"><?php esc_html_e('Reviewer Email', 'vms'); ?></label></th>
            <td>
                <input type="email" id="vms_reviewer_email" name="vms_reviewer_email"
                    class="regular-text"
                    value="<?php echo esc_attr($rev_email); ?>" />
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('Verified Attendance', 'vms'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="vms_verified_attendance" value="1"
                        <?php checked($verified, 1); ?> />
                    <?php esc_html_e('Mark this rating as from a verified attendee', 'vms'); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('In the future, this will be set automatically based on ticket/attendance checks.', 'vms'); ?>
                </p>
            </td>
        </tr>
    </table>
<?php
}

add_action('save_post_vms_rating', 'vms_save_rating_details_meta', 10, 2);
function vms_save_rating_details_meta($post_id, $post)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post->post_type !== 'vms_rating') return;

    if (
        !isset($_POST['vms_rating_details_nonce']) ||
        !wp_verify_nonce($_POST['vms_rating_details_nonce'], 'vms_save_rating_details')
    ) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) return;

    $band_id   = isset($_POST['vms_band_id']) ? absint($_POST['vms_band_id']) : 0;
    $event_id  = isset($_POST['vms_event_id']) ? absint($_POST['vms_event_id']) : 0;
    $stars     = isset($_POST['vms_rating_value']) ? (int) $_POST['vms_rating_value'] : 0;
    $rev_name  = isset($_POST['vms_reviewer_name']) ? sanitize_text_field($_POST['vms_reviewer_name']) : '';
    $rev_email = isset($_POST['vms_reviewer_email']) ? sanitize_email($_POST['vms_reviewer_email']) : '';
    $verified  = isset($_POST['vms_verified_attendance']) ? 1 : 0;

    update_post_meta($post_id, '_vms_band_id', $band_id);
    update_post_meta($post_id, '_vms_event_id', $event_id);
    update_post_meta($post_id, '_vms_rating_value', $stars);
    update_post_meta($post_id, '_vms_reviewer_name', $rev_name);
    update_post_meta($post_id, '_vms_reviewer_email', $rev_email);
    update_post_meta($post_id, '_vms_verified_attendance', $verified);
}

/**
 * Get rating summary for a band.
 *
 * @param int $band_id vms_vendor post ID
 * @param bool $verified_only Whether to only include verified ratings
 * @return array { 'average' => float|null, 'count' => int }
 */
function vms_get_band_rating_summary($band_id, $verified_only = true)
{
    $band_id = (int) $band_id;
    if (!$band_id) {
        return array('average' => null, 'count' => 0);
    }

    $meta_query = array(
        array(
            'key'   => '_vms_band_id',
            'value' => $band_id,
        ),
    );

    if ($verified_only) {
        $meta_query[] = array(
            'key'   => '_vms_verified_attendance',
            'value' => 1,
        );
    }

    $ratings = get_posts(array(
        'post_type'      => 'vms_rating',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => $meta_query,
        'fields'         => 'ids',
    ));

    if (empty($ratings)) {
        return array('average' => null, 'count' => 0);
    }

    $sum   = 0;
    $count = 0;

    foreach ($ratings as $rating_id) {
        $value = (int) get_post_meta($rating_id, '_vms_rating_value', true);
        if ($value > 0) {
            $sum += $value;
            $count++;
        }
    }

    if ($count === 0) {
        return array('average' => null, 'count' => 0);
    }

    return array(
        'average' => round($sum / $count, 2),
        'count'   => $count,
    );
}

// ============================
// Front-end Rating Shortcode
// ============================
add_shortcode('vms_rate_band', 'vms_rate_band_shortcode');

function vms_rate_band_shortcode($atts) {
    $atts = shortcode_atts(array(
        'event' => 0,
        'band'  => 0,
    ), $atts, 'vms_rate_band');

    // Allow event/band to come from query string as well.
    $event_id = isset($_GET['event']) ? absint($_GET['event']) : absint($atts['event']);
    $band_id  = isset($_GET['band'])  ? absint($_GET['band'])  : absint($atts['band']);

    // If we don't even have IDs, bail.
    if (!$event_id || !$band_id) {
        return '<p>Oops! This rating link is missing event or band information.</p>';
    }

    // Look up Event Plan for this TEC event.
    $plan_id = vms_get_event_plan_for_tec_event($event_id);
    if (!$plan_id) {
        return '<p>Nope. This doesn\'t look like a valid show in our system.</p>';
    }

    // Make sure band matches what was actually scheduled.
    $plan_band_id = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
    if ($plan_band_id !== (int) $band_id) {
        return '<p>Nope. This band isn\'t scheduled for that event. '
             . 'Please use the rating link we sent for the correct show.</p>';
    }

    // Only allow rating once the event has started (or passed).
    $event_date = get_post_meta($plan_id, '_vms_event_date', true); // 'YYYY-MM-DD'
    $start_time = get_post_meta($plan_id, '_vms_start_time', true); // 'HH:MM' or ''

    if ($event_date) {
        // Simple version: block until the calendar date has arrived.
        $today = current_time('Y-m-d');
        if ($event_date > $today) {
            return '<p>Hold up! Ratings for this show will be available after the event date.</p>';
        }

        // If you want time-based gating instead, replace the date-only check with this:
        /*
        $tz        = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $time_part = $start_time ? $start_time : '00:00';
        $event_dt  = DateTime::createFromFormat('Y-m-d H:i', $event_date . ' ' . $time_part, $tz);
        $now       = new DateTime('now', $tz);

        if ($event_dt && $now < $event_dt) {
            return '<p>Hold up! Ratings for this show will be available after the event has started.</p>';
        }
        */
    }

    // At this point:
    // - Event exists and is managed by VMS
    // - Band actually played that event
    // - Event date has arrived/passed
    $event_title = get_the_title($event_id);
    $band_title  = get_the_title($band_id);

    // Handle form submission
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_rating_submit'])) {
        $result  = vms_handle_rating_submission($event_id, $band_id);
        $message = $result['message'];
    }

    ob_start();

    if (!empty($message)) {
        echo '<div class="vms-rating-message">' . wp_kses_post($message) . '</div>';
    }

    ?>
    <div class="vms-rating-form-wrapper">
        <h2><?php echo esc_html("Rate {$band_title}"); ?></h2>
        <p><?php echo esc_html("Event: {$event_title}"); ?></p>

        <form method="post">
            <?php wp_nonce_field('vms_submit_rating', 'vms_rating_nonce'); ?>

            <p>
                <label for="vms_reviewer_name"><strong>Your Name</strong></label><br>
                <input type="text" id="vms_reviewer_name" name="vms_reviewer_name"
                    value="<?php echo isset($_POST['vms_reviewer_name']) ? esc_attr($_POST['vms_reviewer_name']) : ''; ?>"
                    class="regular-text" required>
            </p>

            <p>
                <label for="vms_reviewer_email"><strong>Your Email</strong></label><br>
                <input type="email" id="vms_reviewer_email" name="vms_reviewer_email"
                    value="<?php echo isset($_POST['vms_reviewer_email']) ? esc_attr($_POST['vms_reviewer_email']) : ''; ?>"
                    class="regular-text" required>
            </p>

            <p>
                <label for="vms_rating_value"><strong>Your Rating</strong></label><br>
                <select id="vms_rating_value" name="vms_rating_value" required>
                    <option value="">-- Select --</option>
                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                        <option value="<?php echo esc_attr($i); ?>"
                            <?php selected(isset($_POST['vms_rating_value']) ? (int) $_POST['vms_rating_value'] : '', $i); ?>>
                            <?php echo esc_html($i . ' ★'); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </p>

            <p>
                <label for="vms_rating_comment"><strong>Comments (optional)</strong></label><br>
                <textarea id="vms_rating_comment" name="vms_rating_comment" rows="4" style="width:100%;"><?php
                    echo isset($_POST['vms_rating_comment']) ? esc_textarea($_POST['vms_rating_comment']) : '';
                ?></textarea>
            </p>

            <p>
                <button type="submit" name="vms_rating_submit" class="button button-primary">
                    Submit Rating
                </button>
            </p>
        </form>
    </div>
    <?php

    return ob_get_clean();
}

/**
 * Handle rating submission from the shortcode form.
 *
 * @param int $event_id TEC event ID
 * @param int $band_id  vms_vendor ID
 * @return array { 'success' => bool, 'message' => string }
 */
function vms_handle_rating_submission($event_id, $band_id)
{
    if (
        !isset($_POST['vms_rating_nonce']) ||
        !wp_verify_nonce($_POST['vms_rating_nonce'], 'vms_submit_rating')
    ) {

        return array(
            'success' => false,
            'message' => '<p>Security check failed. Please refresh the page and try again.</p>',
        );
    }

    $name    = isset($_POST['vms_reviewer_name']) ? sanitize_text_field($_POST['vms_reviewer_name']) : '';
    $email   = isset($_POST['vms_reviewer_email']) ? sanitize_email($_POST['vms_reviewer_email']) : '';
    $stars   = isset($_POST['vms_rating_value']) ? (int) $_POST['vms_rating_value'] : 0;
    $comment = isset($_POST['vms_rating_comment']) ? wp_kses_post($_POST['vms_rating_comment']) : '';

    if (empty($name) || empty($email) || !$stars) {
        return array(
            'success' => false,
            'message' => '<p>Please fill in your name, email, and rating.</p>',
        );
    }

    // 1) Make sure this event is managed by an Event Plan.
    $plan_id = vms_get_event_plan_for_tec_event($event_id);
    if (!$plan_id) {
        return array(
            'success' => false,
            'message' => '<p>This event is not managed by our scheduling system, so ratings are not available.</p>',
        );
    }

    // 2) Make sure the band actually matches the Event Plan.
    $plan_band_id = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
    if ($plan_band_id !== (int) $band_id) {
        return array(
            'success' => false,
            'message' => '<p>The band in this link doesn\'t match the band scheduled for this event. '
                . 'Please use the rating link we sent for the correct show.</p>',
        );
    }

    // 3) Only allow rating once the event has started (or passed).
    $event_date = get_post_meta($plan_id, '_vms_event_date', true); // 'YYYY-MM-DD'
    $start_time = get_post_meta($plan_id, '_vms_start_time', true); // 'HH:MM' or ''

    if ($event_date) {
        // Build a DateTime in site timezone.
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');

        $time_part = $start_time ? $start_time : '00:00';
        $event_dt  = DateTime::createFromFormat('Y-m-d H:i', $event_date . ' ' . $time_part, $tz);
        $now       = new DateTime('now', $tz);

        if ($event_dt && $now < $event_dt) {
            return array(
                'success' => false,
                'message' => '<p>Ratings for this show will be available after the event has started. '
                    . 'Please come back later.</p>',
            );
        }
    }


    // Prevent duplicate ratings: same event + band + email.
    $existing = get_posts(array(
        'post_type'      => 'vms_rating',
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'   => '_vms_band_id',
                'value' => $band_id,
            ),
            array(
                'key'   => '_vms_event_id',
                'value' => $event_id,
            ),
            array(
                'key'   => '_vms_reviewer_email',
                'value' => $email,
            ),
        ),
        'fields'         => 'ids',
    ));

    if (!empty($existing)) {
        return array(
            'success' => false,
            'message' => '<p>It looks like you\'ve already submitted a rating for this band and event. Thank you!</p>',
        );
    }

    // Attendance check – this is the gatekeeper.
    $has_attended = vms_has_attended_event($event_id, $email);

    if (!$has_attended) {
        return array(
            'success' => false,
            'message' => '<p>We couldn\'t find a ticket for that email address for this event. '
                . 'Only attendees can submit ratings.</p>',
        );
    }

    // Build Rating post.
    $band_title  = get_the_title($band_id);
    $event_title = get_the_title($event_id);

    $post_title = sprintf(
        'Rating: %s – %s – %d★',
        $band_title,
        $event_title,
        $stars
    );

    $rating_post = array(
        'post_type'    => 'vms_rating',
        'post_status'  => 'publish', // you can switch to 'pending' if you want review
        'post_title'   => $post_title,
        'post_content' => $comment,
    );

    $rating_id = wp_insert_post($rating_post);

    if (!$rating_id || is_wp_error($rating_id)) {
        return array(
            'success' => false,
            'message' => '<p>There was a problem saving your rating. Please try again later.</p>',
        );
    }

    // Save meta (same keys we use in the admin).
    update_post_meta($rating_id, '_vms_band_id', $band_id);
    update_post_meta($rating_id, '_vms_event_id', $event_id);
    update_post_meta($rating_id, '_vms_rating_value', $stars);
    update_post_meta($rating_id, '_vms_reviewer_name', $name);
    update_post_meta($rating_id, '_vms_reviewer_email', $email);
    update_post_meta($rating_id, '_vms_verified_attendance', 1); // because check passed

    return array(
        'success' => true,
        'message' => '<p>Thank you! Your rating has been recorded.</p>',
    );
}

/**
 * Check if an email address has attended a given TEC event.
 *
 * MVP: always returns true, but passes through a filter so you can
 * plug in Event Tickets / WooCommerce ticket checks.
 *
 * @param int    $event_id TEC event ID
 * @param string $email    Attendee email
 * @return bool
 */
function vms_has_attended_event($event_id, $email)
{
    $event_id = (int) $event_id;
    $email    = sanitize_email($email);

    $has_attended = true; // TODO: replace with real ticket lookup logic.

    /**
     * Filter: vms_has_attended_event
     *
     * Allows integration with Event Tickets / WooCommerce.
     * Return true if the attendee has a valid ticket for this event.
     */
    $has_attended = apply_filters('vms_has_attended_event', $has_attended, $event_id, $email);

    return (bool) $has_attended;
}
 