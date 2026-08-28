<?php
if (!defined('ABSPATH')) exit;

add_action('init', 'bvmgr_register_vendor_cpt');

// Integrity guard: if a vendor is deleted, any published/ready Event Plans pointing at that vendor
// are reverted to Draft and flagged for review.
add_action('before_delete_post', 'bvmgr_vendor_delete_revert_event_plans', 10, 2);
function bvmgr_vendor_delete_revert_event_plans(int $vendor_id, $post = null): void
{
    if ($vendor_id <= 0) return;

    if (!$post) $post = get_post($vendor_id);
    if (!$post || $post->post_type !== 'vms_vendor') return;

    $vendor_title = (string) get_post_field('post_title', $vendor_id, 'raw');
    $vendor_title = $vendor_title ? wp_strip_all_tags($vendor_title) : '';

    $band_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
    $status_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
    $secondary_ids_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
    $secondary_idx_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';

    $plan_ids = get_posts(array(
        'post_type'              => 'vms_event_plan',
        'post_status'            => 'any',
        'fields'                 => 'ids',
        'posts_per_page'         => -1,
        'no_found_rows'          => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
        'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Vendor deletion must enumerate the complete all-status Event Plan set with this exact primary-vendor link so every broken reference is cleared and flagged.
            array(
                'key'     => $band_key,
                'value'   => $vendor_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
    ));

    $sec_plan_ids = get_posts(array(
        'post_type'              => 'vms_event_plan',
        'post_status'            => 'any',
        'fields'                 => 'ids',
        'posts_per_page'         => -1,
        'no_found_rows'          => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
        'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Vendor deletion must enumerate the complete all-status Event Plan set with this exact indexed secondary-vendor link so every broken reference is removed and flagged.
            array(
                'key'     => $secondary_idx_key,
                'value'   => $vendor_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
    ));

    $plan_ids = is_array($plan_ids) ? $plan_ids : array();
    $sec_plan_ids = is_array($sec_plan_ids) ? $sec_plan_ids : array();
    $all_plan_ids = array_values(array_unique(array_map('absint', array_merge($plan_ids, $sec_plan_ids))));
    if (empty($all_plan_ids)) return;

    $count = 0;
    foreach ($all_plan_ids as $plan_id) {
        $plan_id = (int) $plan_id;
        if ($plan_id <= 0) continue;

        $is_primary = ((int) get_post_meta($plan_id, $band_key, true) === (int) $vendor_id);

        if (!$is_primary) {
            // Remove from secondary vendor list and rebuild index.
            $sec_ids = get_post_meta($plan_id, $secondary_ids_key, true);
            if (!is_array($sec_ids)) $sec_ids = array();
            $sec_ids = array_values(array_unique(array_filter(array_map('absint', $sec_ids), fn($v) => $v > 0)));
            $sec_ids = array_values(array_diff($sec_ids, array((int) $vendor_id)));

            if (!empty($sec_ids)) update_post_meta($plan_id, $secondary_ids_key, $sec_ids);
            else delete_post_meta($plan_id, $secondary_ids_key);

            delete_post_meta($plan_id, $secondary_idx_key);
            foreach ($sec_ids as $vid) {
                add_post_meta($plan_id, $secondary_idx_key, (int) $vid, false);
            }

            // Flag (don’t overwrite a more severe missing headliner flag)
            $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $issue_now = (string) get_post_meta($plan_id, $k_issue, true);
            if ($issue_now !== 'missing_vendor') {
                if (function_exists('bvmgr_event_plan_flag_missing_secondary_vendor')) {
                    bvmgr_event_plan_flag_missing_secondary_vendor($plan_id, $vendor_id, $vendor_title);
                } else {
                    update_post_meta($plan_id, '_vms_integrity_issue', 'missing_secondary_vendor');
                    update_post_meta($plan_id, '_vms_integrity_vendor_id', $vendor_id);
                    if ($vendor_title !== '') update_post_meta($plan_id, '_vms_integrity_vendor_title', $vendor_title);
                    update_post_meta($plan_id, '_vms_integrity_ts', (string) wp_date('Y-m-d H:i:s'));
                }
            }

            update_post_meta($plan_id, $status_key, 'draft');

            $wp_post = get_post($plan_id);
            if ($wp_post && $wp_post->post_status === 'publish') {
                wp_update_post(array('ID' => $plan_id, 'post_status' => 'draft'));
            }

            $count++;
            continue;
        }

        // Flag and clear broken pointer (non-destructive; does not touch TEC events automatically).
        if (function_exists('bvmgr_event_plan_flag_missing_vendor')) {
            bvmgr_event_plan_flag_missing_vendor($plan_id, $vendor_id, $vendor_title);
        } else {
            // Fallback: set minimal meta flags if helpers are unavailable.
            update_post_meta($plan_id, '_vms_integrity_issue', 'missing_vendor');
            update_post_meta($plan_id, '_vms_integrity_vendor_id', $vendor_id);
            if ($vendor_title !== '') update_post_meta($plan_id, '_vms_integrity_vendor_title', $vendor_title);
            update_post_meta($plan_id, '_vms_integrity_ts', (string) wp_date('Y-m-d H:i:s'));
        }

        update_post_meta($plan_id, $band_key, 0);
        update_post_meta($plan_id, $status_key, 'draft');

        $wp_post = get_post($plan_id);
        if ($wp_post && $wp_post->post_status === 'publish') {
            wp_update_post(array('ID' => $plan_id, 'post_status' => 'draft'));
        }

        $count++;
    }

    if ($count > 0 && function_exists('bvmgr_add_admin_notice')) {
        /* translators: %d: number used in this message. */
        bvmgr_add_admin_notice(sprintf(__('🚩 Vendor deleted. %d event plan(s) were reverted to Draft and flagged for review.', 'backstage-venue-manager'), $count), 'warning');
    }
}




function bvmgr_register_vendor_cpt()
{
    $labels = array(
        'name'               => __('Vendors', 'backstage-venue-manager'),
        'singular_name'      => __('Vendor', 'backstage-venue-manager'),
        'add_new'            => __('Add New Vendor', 'backstage-venue-manager'),
        'add_new_item'       => __('Add New Vendor', 'backstage-venue-manager'),
        'edit_item'          => __('Edit Vendor', 'backstage-venue-manager'),
        'new_item'           => __('New Vendor', 'backstage-venue-manager'),
        'view_item'          => __('View Vendor', 'backstage-venue-manager'),
        'search_items'       => __('Search Vendors', 'backstage-venue-manager'),
        'not_found'          => __('No vendors found', 'backstage-venue-manager'),
        'not_found_in_trash' => __('No vendors found in Trash', 'backstage-venue-manager'),
        'menu_name'          => __('Vendors', 'backstage-venue-manager'),
    );

    $args = array(
        'labels'          => $labels,
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => false,
        'menu_position'   => 26,
        'menu_icon'       => 'dashicons-groups',
        'supports'        => array('title', 'editor', 'thumbnail'), // add thumbnail for logo support
        'capability_type' => 'post',
        'has_archive'     => false,
        'rewrite'         => false,
    );

    register_post_type('vms_vendor', $args);
}

if (!function_exists('bvmgr_vendor_has_public_profile_type')) {
    function bvmgr_vendor_has_public_profile_type(int $vendor_id): bool
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return false;
        }

        if (function_exists('bvmgr_vendor_primary_type_slug')) {
            return trim((string) bvmgr_vendor_primary_type_slug($vendor_id)) !== '';
        }

        $terms = get_the_terms($vendor_id, 'vms_vendor_type');
        return is_array($terms) && !is_wp_error($terms) && !empty($terms);
    }
}

if (!function_exists('bvmgr_vendor_availability_snapshot_month')) {
    function bvmgr_vendor_availability_snapshot_month(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only availability snapshot month only changes which calendar view is displayed.
        return bvmgr_request_read_text_field($_GET, 'vms_vendor_month');
    }
}

/**
 * Admin functionality for VMS Vendors.
 */


class BVMGR_Admin_Vendors
{

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        add_action('save_post_vms_vendor', array($this, 'save_vendor_meta'), 10, 2);
    }

    /**
     * Register meta boxes for the Vendor post type.
     */
    public function register_meta_boxes()
    {
        add_meta_box(
            'vms_vendor_details',
            __('Vendor Details', 'backstage-venue-manager'),
            array($this, 'render_vendor_details_meta_box'),
            'vms_vendor',
            'normal',
            'default'
        );

        add_meta_box(
            'vms_vendor_public_profile',
            __('Public Profile', 'backstage-venue-manager'),
            array($this, 'render_vendor_public_profile_meta_box'),
            'vms_vendor',
            'side',
            'default'
        );

        add_meta_box(
            'vms_vendor_availability_snapshot',
            __('Availability Snapshot', 'backstage-venue-manager'),
            array($this, 'render_vendor_availability_snapshot_meta_box'),
            'vms_vendor',
            'normal',
            'default'
        );

} 

    /**
     * Render the Vendor Details meta box.
     */
    public function render_vendor_details_meta_box($post)
    {
        // Security nonce.
        wp_nonce_field('bvmgr_save_vendor_details', 'bvmgr_vendor_details_nonce');

        $k_contact_name  = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'contact_name') : '_vms_contact_name';
        $k_primary_email = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email';
        $k_primary_phone = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone';
        $k_website       = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'website') : '_vms_vendor_website';

        $contact_name    = get_post_meta($post->ID, $k_contact_name, true);
        $primary_email   = get_post_meta($post->ID, $k_primary_email, true);
        $primary_phone   = get_post_meta($post->ID, $k_primary_phone, true);
        $website_url     = get_post_meta($post->ID, $k_website, true);

        // Legacy fallbacks (dev-only transitional support): pull existing values if canonical fields are empty.
        $legacy_email = get_post_meta($post->ID, '_vms_contact_email', true);
        $legacy_phone = get_post_meta($post->ID, '_vms_contact_phone', true);
        $legacy_web   = get_post_meta($post->ID, '_vms_website_url', true);

        if ($primary_email === '' && is_string($legacy_email) && $legacy_email !== '') {
            $primary_email = $legacy_email;
        }
        if ($primary_phone === '' && is_string($legacy_phone) && $legacy_phone !== '') {
            $primary_phone = $legacy_phone;
        }
        if ($website_url === '' && is_string($legacy_web) && $legacy_web !== '') {
            $website_url = $legacy_web;
        }

?>

        <p class="description">
            <?php esc_html_e('Contact and website fields live here. Mailing city/state now live in the "Tax Profile (Admin)" box so there is only one source of truth for shared tax/profile location fields. Pay structure and Event Plan defaults are managed in the "Pay Structure + Event Plan Defaults" box.', 'backstage-venue-manager'); ?>
        </p>

        <p class="description">
            <?php esc_html_e('Social links, featured video, and gallery photos are managed in the "Public Profile" box.', 'backstage-venue-manager'); ?>
        </p>

        <p class="description">
            <?php esc_html_e("Use the Vendor Categories box to store this vendor's categories, such as Genre or Cuisine. Those categories can sync to Event Plans and TEC event categories.", 'backstage-venue-manager'); ?>
        </p>

        <p>
            <label for="vms_contact_name"><strong><?php esc_html_e('Primary Contact Name (optional)', 'backstage-venue-manager'); ?></strong></label><br />
            <input type="text" id="vms_contact_name" name="vms_contact_name" class="regular-text"
                value="<?php echo esc_attr($contact_name); ?>" />
        </p>

        <p>
            <label for="vms_primary_email"><strong><?php esc_html_e('Primary Contact Email', 'backstage-venue-manager'); ?></strong></label><br />
            <input type="email" id="vms_primary_email" name="vms_primary_email" class="regular-text"
                value="<?php echo esc_attr($primary_email); ?>" />
        </p>

        <p>
            <label for="vms_primary_phone"><strong><?php esc_html_e('Primary Contact Phone', 'backstage-venue-manager'); ?></strong></label><br />
            <input type="text" id="vms_primary_phone" name="vms_primary_phone" class="regular-text"
                value="<?php echo esc_attr($primary_phone); ?>" />
        </p>

        <p>
            <label for="vms_website_url"><strong><?php esc_html_e('Website URL', 'backstage-venue-manager'); ?></strong></label><br />
            <input type="url" id="vms_website_url" name="vms_website_url" class="regular-text"
                value="<?php echo esc_attr($website_url); ?>" />
        </p>


<?php
    }

    /**
     * Render the Public Profile meta box (Vendor).
     */
    public function render_vendor_public_profile_meta_box($post)
    {
        // Security nonce for this meta box.
        wp_nonce_field('bvmgr_save_vendor_public_profile', 'bvmgr_vendor_public_profile_nonce');

        $post_id = (int) $post->ID;

        $k_enabled  = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_enabled') : '_vms_vendor_public_profile_enabled';
        $k_show_e   = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_email') : '_vms_vendor_public_profile_show_email';
        $k_show_p   = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_phone') : '_vms_vendor_public_profile_show_phone';
        $k_show_w   = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_website') : '_vms_vendor_public_profile_show_website';
        $k_show_loc = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_location') : '_vms_vendor_public_profile_show_location';

        $enabled  = get_post_meta($post_id, $k_enabled, true);
        $show_e   = get_post_meta($post_id, $k_show_e, true);
        $show_p   = get_post_meta($post_id, $k_show_p, true);
        $show_w   = get_post_meta($post_id, $k_show_w, true);
        $show_loc = get_post_meta($post_id, $k_show_loc, true);

        $enabled_bool = ($enabled === '1' || $enabled === 1 || $enabled === true || $enabled === 'yes' || $enabled === 'on');
        $has_vendor_type = bvmgr_vendor_has_public_profile_type($post_id);

        $profile_url = function_exists('bvmgr_vendor_profile_url') ? bvmgr_vendor_profile_url($post_id) : '';

        echo '<p><label><input type="checkbox" name="vms_public_profile_enabled" value="1" ' . checked($enabled_bool, true, false) . ' /> ' . esc_html__('Enable public profile', 'backstage-venue-manager') . '</label></p>';
        echo '<p class="description">' . esc_html__('When disabled, the public profile returns a 404.', 'backstage-venue-manager') . '</p>';
        echo '<p class="description">' . esc_html__('Public profiles require at least one Vendor Type.', 'backstage-venue-manager') . '</p>';
        if (!$has_vendor_type) {
            echo '<p class="description"><strong>' . esc_html__('Assign a Vendor Type before enabling this public profile.', 'backstage-venue-manager') . '</strong></p>';
            if ($enabled_bool) {
                echo '<p class="description">' . esc_html__('This profile is still blocked on the public site until a Vendor Type is assigned.', 'backstage-venue-manager') . '</p>';
            }
        }

        echo '<hr />';

        $social_fields = array(
            'facebook'  => array('label' => __('Facebook URL', 'backstage-venue-manager'),  'key' => '_vms_vendor_social_facebook'),
            'instagram' => array('label' => __('Instagram URL', 'backstage-venue-manager'), 'key' => '_vms_vendor_social_instagram'),
            'x'         => array('label' => __('X / Twitter URL', 'backstage-venue-manager'),'key' => '_vms_vendor_social_x'),
            'tiktok'    => array('label' => __('TikTok URL', 'backstage-venue-manager'),    'key' => '_vms_vendor_social_tiktok'),
            'youtube'   => array('label' => __('YouTube URL', 'backstage-venue-manager'),   'key' => '_vms_vendor_social_youtube'),
            'spotify'   => array('label' => __('Spotify URL', 'backstage-venue-manager'),   'key' => '_vms_vendor_social_spotify'),
        );
        $featured_video_url = (string) get_post_meta($post_id, '_vms_vendor_featured_video_url', true);

        echo '<p><strong>' . esc_html__('Visible fields', 'backstage-venue-manager') . '</strong></p>';
        echo '<p><label><input type="checkbox" name="vms_public_profile_show_location" value="1" ' . checked(($show_loc === '' || $show_loc === '1'), true, false) . ' /> ' . esc_html__('Location (City/State)', 'backstage-venue-manager') . '</label></p>';
        echo '<p><label><input type="checkbox" name="vms_public_profile_show_phone" value="1" ' . checked(($show_p === '' || $show_p === '1'), true, false) . ' /> ' . esc_html__('Phone', 'backstage-venue-manager') . '</label></p>';
        echo '<p><label><input type="checkbox" name="vms_public_profile_show_email" value="1" ' . checked(($show_e === '' || $show_e === '1'), true, false) . ' /> ' . esc_html__('Email', 'backstage-venue-manager') . '</label></p>';
        echo '<p><label><input type="checkbox" name="vms_public_profile_show_website" value="1" ' . checked(($show_w === '' || $show_w === '1'), true, false) . ' /> ' . esc_html__('Website button', 'backstage-venue-manager') . '</label></p>';

        echo '<hr />';
        echo '<p><strong>' . esc_html__('Social links (icon-only on public profile)', 'backstage-venue-manager') . '</strong></p>';
        foreach ($social_fields as $field) {
            $value = (string) get_post_meta($post_id, $field['key'], true);
            echo '<p><label><strong>' . esc_html($field['label']) . '</strong></label><br />';
            echo '<input type="url" class="widefat" name="vms_vendor_social[' . esc_attr($field['key']) . ']" value="' . esc_attr($value) . '" placeholder="https://" /></p>';
        }

        echo '<hr />';
        echo '<p><strong>' . esc_html__('Featured media', 'backstage-venue-manager') . '</strong></p>';
        echo '<p><label><strong>' . esc_html__('Featured video URL', 'backstage-venue-manager') . '</strong></label><br />';
        echo '<input type="url" class="widefat" name="vms_vendor_featured_video_url" value="' . esc_attr($featured_video_url) . '" placeholder="https://www.youtube.com/watch?v=..." />';
        echo '<span class="description" style="display:block;margin-top:4px;">' . esc_html__('YouTube works best. Facebook video links may work when oEmbed is available in your setup.', 'backstage-venue-manager') . '</span></p>';

        echo '<p><strong>' . esc_html__('Gallery photos (up to 5)', 'backstage-venue-manager') . '</strong></p>';
        for ($i = 1; $i <= 5; $i++) {
            $gallery_value = (string) get_post_meta($post_id, '_vms_vendor_gallery_image_' . $i, true);
            /* translators: %d: gallery photo number. */
            echo '<p><label><strong>' . sprintf(esc_html__('Photo %d URL', 'backstage-venue-manager'), $i) . '</strong></label><br />';
            echo '<input type="url" class="widefat" name="vms_vendor_gallery_image_' . esc_attr((string) $i) . '" value="' . esc_attr($gallery_value) . '" placeholder="https://" /></p>';
        }

        echo '<p class="description">' . esc_html__('For now these are curated/admin-managed fields, which keeps the public profile moderated. Vendors do not edit these public profile media/social fields from their portal yet.', 'backstage-venue-manager') . '</p>';

        if ($profile_url) {
            if ($enabled_bool && $has_vendor_type) {
                echo '<p><a class="button button-secondary" href="' . esc_url($profile_url) . '" target="_blank" rel="noopener">' . esc_html__('Open profile', 'backstage-venue-manager') . '</a></p>';
            } else {
                echo '<p class="description">' . esc_html__('Enable the profile to activate the public URL.', 'backstage-venue-manager') . '</p>';
            }
        }
    }


    /**
     * Render a read-only availability snapshot that mirrors the vendor's resolved availability.
     */
    public function render_vendor_availability_snapshot_meta_box($post)
    {
        $post_id = (int) $post->ID;
        $month = bvmgr_vendor_availability_snapshot_month();

        if (function_exists('bvmgr_render_vendor_availability_vendor_profile_calendar')) {
            bvmgr_render_vendor_availability_vendor_profile_calendar($post_id, $month);
            return;
        }

        echo '<p>' . esc_html__('Availability snapshot is unavailable right now.', 'backstage-venue-manager') . '</p>';
    }

    /**
     * Save Vendor meta fields.
     */
    public function save_vendor_meta($post_id, $post)
    {
        // Check nonce(s). Either meta box nonce should allow saving its own fields.
        $details_nonce = bvmgr_request_read_text_field($_POST, 'bvmgr_vendor_details_nonce'); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading the submitted Vendor Details nonce is required before local verification.
        $profile_nonce = bvmgr_request_read_text_field($_POST, 'bvmgr_vendor_public_profile_nonce'); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading the submitted Vendor Public Profile nonce is required before local verification.
        $details_ok = ($details_nonce !== '' && wp_verify_nonce($details_nonce, bvmgr_nonce_action_for_value($details_nonce, 'bvmgr_save_vendor_details')));
        $profile_ok = ($profile_nonce !== '' && wp_verify_nonce($profile_nonce, bvmgr_nonce_action_for_value($profile_nonce, 'bvmgr_save_vendor_public_profile')));

        if (!$details_ok && !$profile_ok) {
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

        if ($details_ok) {

            $k_contact_name  = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'contact_name') : '_vms_contact_name';
            $k_primary_email = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email';
            $k_primary_phone = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone';
            $k_website       = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'website') : '_vms_vendor_website';

            // Sanitize and save Vendor Details-only fields.
            // City/State intentionally live only in Tax Profile (Admin) to avoid duplicate shared inputs.
            $fields = array(
                $k_contact_name  => bvmgr_request_read_text_field($_POST, 'vms_contact_name'),
                $k_primary_email => bvmgr_request_read_email($_POST, 'vms_primary_email'),
                $k_primary_phone => bvmgr_request_read_text_field($_POST, 'vms_primary_phone'),
                $k_website       => esc_url_raw(bvmgr_request_read_scalar($_POST, 'vms_website_url')),
            );

            foreach ($fields as $meta_key => $value) {
                if ($value === '' || $value === null) {
                    delete_post_meta($post_id, $meta_key);
                } else {
                    update_post_meta($post_id, $meta_key, $value);
                }
            }
        }

        // Save Public Profile fields (if its nonce was present/valid).
        if ($profile_ok) {
            $k_enabled  = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_enabled') : '_vms_vendor_public_profile_enabled';
            $k_show_e   = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_email') : '_vms_vendor_public_profile_show_email';
            $k_show_p   = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_phone') : '_vms_vendor_public_profile_show_phone';
            $k_show_w   = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_website') : '_vms_vendor_public_profile_show_website';
            $k_show_loc = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_location') : '_vms_vendor_public_profile_show_location';

            $enabled  = isset($_POST['vms_public_profile_enabled']) ? '1' : '0';
            if ($enabled === '1' && !bvmgr_vendor_has_public_profile_type($post_id)) {
                $enabled = '0';
                if (function_exists('bvmgr_add_admin_notice')) {
                    bvmgr_add_admin_notice(__('Public profiles require a Vendor Type. Assign a Vendor Type before enabling this public profile.', 'backstage-venue-manager'), 'error');
                }
            }
            $show_e   = isset($_POST['vms_public_profile_show_email']) ? '1' : '0';
            $show_p   = isset($_POST['vms_public_profile_show_phone']) ? '1' : '0';
            $show_w   = isset($_POST['vms_public_profile_show_website']) ? '1' : '0';
            $show_loc = isset($_POST['vms_public_profile_show_location']) ? '1' : '0';

            update_post_meta($post_id, $k_enabled, $enabled);
            update_post_meta($post_id, $k_show_e, $show_e);
            update_post_meta($post_id, $k_show_p, $show_p);
            update_post_meta($post_id, $k_show_w, $show_w);
            update_post_meta($post_id, $k_show_loc, $show_loc);

            $social_keys = array(
                '_vms_vendor_social_facebook',
                '_vms_vendor_social_instagram',
                '_vms_vendor_social_x',
                '_vms_vendor_social_tiktok',
                '_vms_vendor_social_youtube',
                '_vms_vendor_social_spotify',
            );
            $social_raw = (isset($_POST['vms_vendor_social']) && is_array($_POST['vms_vendor_social']))
                ? (array) wp_unslash($_POST['vms_vendor_social']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Vendor social URLs are unslashed here and sanitized element-by-element below.
                : array();
            foreach ($social_keys as $social_key) {
                $raw_value = $social_raw[$social_key] ?? '';
                $value = is_scalar($raw_value) ? esc_url_raw((string) $raw_value) : '';
                if ($value === '') {
                    delete_post_meta($post_id, $social_key);
                } else {
                    update_post_meta($post_id, $social_key, $value);
                }
            }

            $featured_video_url = isset($_POST['vms_vendor_featured_video_url']) ? esc_url_raw((string) wp_unslash($_POST['vms_vendor_featured_video_url'])) : '';
            if ($featured_video_url === '') {
                delete_post_meta($post_id, '_vms_vendor_featured_video_url');
            } else {
                update_post_meta($post_id, '_vms_vendor_featured_video_url', $featured_video_url);
            }

            for ($i = 1; $i <= 5; $i++) {
                $field = 'vms_vendor_gallery_image_' . $i;
                $meta_key = '_vms_vendor_gallery_image_' . $i;
                $value = isset($_POST[$field]) ? esc_url_raw((string) wp_unslash($_POST[$field])) : '';
                if ($value === '') {
                    delete_post_meta($post_id, $meta_key);
                } else {
                    update_post_meta($post_id, $meta_key, $value);
                }
            }
        }

    }
}

if (is_admin()) {
    add_action('init', function () {
        if (class_exists('BVMGR_Admin_Vendors')) {
            new BVMGR_Admin_Vendors();
        }
    }, 20);
}
