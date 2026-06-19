<?php
if (!defined('ABSPATH')) exit;

add_action('init', 'vms_register_vendor_cpt');

// Integrity guard: if a vendor is deleted, any published/ready Event Plans pointing at that vendor
// are reverted to Draft and flagged for review.
add_action('before_delete_post', 'vms_vendor_delete_revert_event_plans', 10, 2);
function vms_vendor_delete_revert_event_plans(int $vendor_id, $post = null): void
{
    if ($vendor_id <= 0) return;

    if (!$post) $post = get_post($vendor_id);
    if (!$post || $post->post_type !== 'vms_vendor') return;

    $vendor_title = (string) get_post_field('post_title', $vendor_id, 'raw');
    $vendor_title = $vendor_title ? wp_strip_all_tags($vendor_title) : '';

    $band_key = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
    $status_key = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'status') : '_vms_event_plan_status';
    $secondary_ids_key = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
    $secondary_idx_key = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';

    $plan_ids = get_posts(array(
        'post_type'              => 'vms_event_plan',
        'post_status'            => 'any',
        'fields'                 => 'ids',
        'posts_per_page'         => -1,
        'no_found_rows'          => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
        'meta_query'             => array(
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
        'meta_query'             => array(
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
            $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $issue_now = (string) get_post_meta($plan_id, $k_issue, true);
            if ($issue_now !== 'missing_vendor') {
                if (function_exists('vms_event_plan_flag_missing_secondary_vendor')) {
                    vms_event_plan_flag_missing_secondary_vendor($plan_id, $vendor_id, $vendor_title);
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
        if (function_exists('vms_event_plan_flag_missing_vendor')) {
            vms_event_plan_flag_missing_vendor($plan_id, $vendor_id, $vendor_title);
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

    if ($count > 0 && function_exists('vms_add_admin_notice')) {
        vms_add_admin_notice(sprintf(__('🚩 Vendor deleted. %d event plan(s) were reverted to Draft and flagged for review.', 'vms'), $count), 'warning');
    }
}




function vms_register_vendor_cpt()
{
    $labels = array(
        'name'               => __('Vendors', 'vms'),
        'singular_name'      => __('Vendor', 'vms'),
        'add_new'            => __('Add New Vendor', 'vms'),
        'add_new_item'       => __('Add New Vendor', 'vms'),
        'edit_item'          => __('Edit Vendor', 'vms'),
        'new_item'           => __('New Vendor', 'vms'),
        'view_item'          => __('View Vendor', 'vms'),
        'search_items'       => __('Search Vendors', 'vms'),
        'not_found'          => __('No vendors found', 'vms'),
        'not_found_in_trash' => __('No vendors found in Trash', 'vms'),
        'menu_name'          => __('Vendors', 'vms'),
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

if (!function_exists('vms_vendor_has_public_profile_type')) {
    function vms_vendor_has_public_profile_type(int $vendor_id): bool
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return false;
        }

        if (function_exists('vms_vendor_primary_type_slug')) {
            return trim((string) vms_vendor_primary_type_slug($vendor_id)) !== '';
        }

        $terms = get_the_terms($vendor_id, 'vms_vendor_type');
        return is_array($terms) && !is_wp_error($terms) && !empty($terms);
    }
}

/**
 * Admin functionality for VMS Vendors.
 */


class VMS_Admin_Vendors
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
            __('Vendor Details', 'vms'),
            array($this, 'render_vendor_details_meta_box'),
            'vms_vendor',
            'normal',
            'default'
        );

        add_meta_box(
            'vms_vendor_public_profile',
            __('Public Profile', 'vms'),
            array($this, 'render_vendor_public_profile_meta_box'),
            'vms_vendor',
            'side',
            'default'
        );

        add_meta_box(
            'vms_vendor_availability_snapshot',
            __('Availability Snapshot', 'vms'),
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
        wp_nonce_field('vms_save_vendor_details', 'vms_vendor_details_nonce');

        $k_contact_name  = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'contact_name') : '_vms_contact_name';
        $k_primary_email = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email';
        $k_primary_phone = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone';
        $k_website       = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'website') : '_vms_vendor_website';

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
            <?php esc_html_e('Contact and website fields live here. Mailing city/state now live in the "Tax Profile (Admin)" box so there is only one source of truth for shared tax/profile location fields. Pay structure and Event Plan defaults are managed in the "Pay Structure + Event Plan Defaults" box.', 'vms'); ?>
        </p>

        <p class="description">
            <?php esc_html_e('Social links, featured video, and gallery photos are managed in the "Public Profile" box.', 'vms'); ?>
        </p>

        <p class="description">
            <?php esc_html_e("Use the Vendor Categories box to store this vendor's categories, such as Genre or Cuisine. Those categories can sync to Event Plans and TEC event categories.", 'vms'); ?>
        </p>

        <p>
            <label for="vms_contact_name"><strong><?php esc_html_e('Primary Contact Name (optional)', 'vms'); ?></strong></label><br />
            <input type="text" id="vms_contact_name" name="vms_contact_name" class="regular-text"
                value="<?php echo esc_attr($contact_name); ?>" />
        </p>

        <p>
            <label for="vms_primary_email"><strong><?php esc_html_e('Primary Contact Email', 'vms'); ?></strong></label><br />
            <input type="email" id="vms_primary_email" name="vms_primary_email" class="regular-text"
                value="<?php echo esc_attr($primary_email); ?>" />
        </p>

        <p>
            <label for="vms_primary_phone"><strong><?php esc_html_e('Primary Contact Phone', 'vms'); ?></strong></label><br />
            <input type="text" id="vms_primary_phone" name="vms_primary_phone" class="regular-text"
                value="<?php echo esc_attr($primary_phone); ?>" />
        </p>

        <p>
            <label for="vms_website_url"><strong><?php esc_html_e('Website URL', 'vms'); ?></strong></label><br />
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
        wp_nonce_field('vms_save_vendor_public_profile', 'vms_vendor_public_profile_nonce');

        $post_id = (int) $post->ID;

        $k_enabled  = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_enabled') : '_vms_vendor_public_profile_enabled';
        $k_show_e   = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_email') : '_vms_vendor_public_profile_show_email';
        $k_show_p   = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_phone') : '_vms_vendor_public_profile_show_phone';
        $k_show_w   = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_website') : '_vms_vendor_public_profile_show_website';
        $k_show_loc = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_location') : '_vms_vendor_public_profile_show_location';

        $enabled  = get_post_meta($post_id, $k_enabled, true);
        $show_e   = get_post_meta($post_id, $k_show_e, true);
        $show_p   = get_post_meta($post_id, $k_show_p, true);
        $show_w   = get_post_meta($post_id, $k_show_w, true);
        $show_loc = get_post_meta($post_id, $k_show_loc, true);

        $enabled_bool = ($enabled === '1' || $enabled === 1 || $enabled === true || $enabled === 'yes' || $enabled === 'on');
        $has_vendor_type = vms_vendor_has_public_profile_type($post_id);

        $profile_url = function_exists('vms_vendor_profile_url') ? vms_vendor_profile_url($post_id) : '';

        echo '<p><label><input type="checkbox" name="vms_public_profile_enabled" value="1" ' . checked($enabled_bool, true, false) . ' /> ' . esc_html__('Enable public profile', 'vms') . '</label></p>';
        echo '<p class="description">' . esc_html__('When disabled, the public profile returns a 404.', 'vms') . '</p>';
        echo '<p class="description">' . esc_html__('Public profiles require at least one Vendor Type.', 'vms') . '</p>';
        if (!$has_vendor_type) {
            echo '<p class="description"><strong>' . esc_html__('Assign a Vendor Type before enabling this public profile.', 'vms') . '</strong></p>';
            if ($enabled_bool) {
                echo '<p class="description">' . esc_html__('This profile is still blocked on the public site until a Vendor Type is assigned.', 'vms') . '</p>';
            }
        }

        echo '<hr />';

        $social_fields = array(
            'facebook'  => array('label' => __('Facebook URL', 'vms'),  'key' => '_vms_vendor_social_facebook'),
            'instagram' => array('label' => __('Instagram URL', 'vms'), 'key' => '_vms_vendor_social_instagram'),
            'x'         => array('label' => __('X / Twitter URL', 'vms'),'key' => '_vms_vendor_social_x'),
            'tiktok'    => array('label' => __('TikTok URL', 'vms'),    'key' => '_vms_vendor_social_tiktok'),
            'youtube'   => array('label' => __('YouTube URL', 'vms'),   'key' => '_vms_vendor_social_youtube'),
            'spotify'   => array('label' => __('Spotify URL', 'vms'),   'key' => '_vms_vendor_social_spotify'),
        );
        $featured_video_url = (string) get_post_meta($post_id, '_vms_vendor_featured_video_url', true);

        echo '<p><strong>' . esc_html__('Visible fields', 'vms') . '</strong></p>';
        echo '<p><label><input type="checkbox" name="vms_public_profile_show_location" value="1" ' . checked(($show_loc === '' || $show_loc === '1'), true, false) . ' /> ' . esc_html__('Location (City/State)', 'vms') . '</label></p>';
        echo '<p><label><input type="checkbox" name="vms_public_profile_show_phone" value="1" ' . checked(($show_p === '' || $show_p === '1'), true, false) . ' /> ' . esc_html__('Phone', 'vms') . '</label></p>';
        echo '<p><label><input type="checkbox" name="vms_public_profile_show_email" value="1" ' . checked(($show_e === '' || $show_e === '1'), true, false) . ' /> ' . esc_html__('Email', 'vms') . '</label></p>';
        echo '<p><label><input type="checkbox" name="vms_public_profile_show_website" value="1" ' . checked(($show_w === '' || $show_w === '1'), true, false) . ' /> ' . esc_html__('Website button', 'vms') . '</label></p>';

        echo '<hr />';
        echo '<p><strong>' . esc_html__('Social links (icon-only on public profile)', 'vms') . '</strong></p>';
        foreach ($social_fields as $field) {
            $value = (string) get_post_meta($post_id, $field['key'], true);
            echo '<p><label><strong>' . esc_html($field['label']) . '</strong></label><br />';
            echo '<input type="url" class="widefat" name="vms_vendor_social[' . esc_attr($field['key']) . ']" value="' . esc_attr($value) . '" placeholder="https://" /></p>';
        }

        echo '<hr />';
        echo '<p><strong>' . esc_html__('Featured media', 'vms') . '</strong></p>';
        echo '<p><label><strong>' . esc_html__('Featured video URL', 'vms') . '</strong></label><br />';
        echo '<input type="url" class="widefat" name="vms_vendor_featured_video_url" value="' . esc_attr($featured_video_url) . '" placeholder="https://www.youtube.com/watch?v=..." />';
        echo '<span class="description" style="display:block;margin-top:4px;">' . esc_html__('YouTube works best. Facebook video links may work when oEmbed is available in your setup.', 'vms') . '</span></p>';

        echo '<p><strong>' . esc_html__('Gallery photos (up to 5)', 'vms') . '</strong></p>';
        for ($i = 1; $i <= 5; $i++) {
            $gallery_value = (string) get_post_meta($post_id, '_vms_vendor_gallery_image_' . $i, true);
            echo '<p><label><strong>' . sprintf(esc_html__('Photo %d URL', 'vms'), $i) . '</strong></label><br />';
            echo '<input type="url" class="widefat" name="vms_vendor_gallery_image_' . esc_attr((string) $i) . '" value="' . esc_attr($gallery_value) . '" placeholder="https://" /></p>';
        }

        echo '<p class="description">' . esc_html__('For now these are curated/admin-managed fields, which keeps the public profile moderated. Vendors do not edit these public profile media/social fields from their portal yet.', 'vms') . '</p>';

        if ($profile_url) {
            if ($enabled_bool && $has_vendor_type) {
                echo '<p><a class="button button-secondary" href="' . esc_url($profile_url) . '" target="_blank" rel="noopener">' . esc_html__('Open profile', 'vms') . '</a></p>';
            } else {
                echo '<p class="description">' . esc_html__('Enable the profile to activate the public URL.', 'vms') . '</p>';
            }
        }
    }


    /**
     * Render a read-only availability snapshot that mirrors the vendor's resolved availability.
     */
    public function render_vendor_availability_snapshot_meta_box($post)
    {
        $post_id = (int) $post->ID;
        $month = '';
        if (isset($_GET['vms_vendor_month'])) {
            $month = sanitize_text_field((string) wp_unslash($_GET['vms_vendor_month']));
        }

        if (function_exists('vms_render_vendor_availability_vendor_profile_calendar')) {
            vms_render_vendor_availability_vendor_profile_calendar($post_id, $month);
            return;
        }

        echo '<p>' . esc_html__('Availability snapshot is unavailable right now.', 'vms') . '</p>';
    }

    /**
     * Save Vendor meta fields.
     */
    public function save_vendor_meta($post_id, $post)
    {
        // Check nonce(s). Either meta box nonce should allow saving its own fields.
        $details_ok = (isset($_POST['vms_vendor_details_nonce']) && wp_verify_nonce($_POST['vms_vendor_details_nonce'], 'vms_save_vendor_details'));
        $profile_ok = (isset($_POST['vms_vendor_public_profile_nonce']) && wp_verify_nonce($_POST['vms_vendor_public_profile_nonce'], 'vms_save_vendor_public_profile'));

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

            $k_contact_name  = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'contact_name') : '_vms_contact_name';
            $k_primary_email = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email';
            $k_primary_phone = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone';
            $k_website       = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'website') : '_vms_vendor_website';

            // Sanitize and save Vendor Details-only fields.
            // City/State intentionally live only in Tax Profile (Admin) to avoid duplicate shared inputs.
            $fields = array(
                $k_contact_name  => isset($_POST['vms_contact_name']) ? sanitize_text_field($_POST['vms_contact_name']) : '',
                $k_primary_email => isset($_POST['vms_primary_email']) ? sanitize_email($_POST['vms_primary_email']) : '',
                $k_primary_phone => isset($_POST['vms_primary_phone']) ? sanitize_text_field($_POST['vms_primary_phone']) : '',
                $k_website       => isset($_POST['vms_website_url']) ? esc_url_raw($_POST['vms_website_url']) : '',
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
            $k_enabled  = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_enabled') : '_vms_vendor_public_profile_enabled';
            $k_show_e   = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_email') : '_vms_vendor_public_profile_show_email';
            $k_show_p   = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_phone') : '_vms_vendor_public_profile_show_phone';
            $k_show_w   = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_website') : '_vms_vendor_public_profile_show_website';
            $k_show_loc = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_location') : '_vms_vendor_public_profile_show_location';

            $enabled  = isset($_POST['vms_public_profile_enabled']) ? '1' : '0';
            if ($enabled === '1' && !vms_vendor_has_public_profile_type($post_id)) {
                $enabled = '0';
                if (function_exists('vms_add_admin_notice')) {
                    vms_add_admin_notice(__('Public profiles require a Vendor Type. Assign a Vendor Type before enabling this public profile.', 'vms'), 'error');
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
            $social_raw = isset($_POST['vms_vendor_social']) && is_array($_POST['vms_vendor_social']) ? (array) $_POST['vms_vendor_social'] : array();
            foreach ($social_keys as $social_key) {
                $value = isset($social_raw[$social_key]) ? esc_url_raw((string) wp_unslash($social_raw[$social_key])) : '';
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
        if (class_exists('VMS_Admin_Vendors')) {
            new VMS_Admin_Vendors();
        }
    }, 20);
}
