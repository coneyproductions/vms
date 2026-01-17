<?php
if (!defined('ABSPATH')) exit;

add_action('init', 'vms_register_vendor_cpt');

add_action('save_post_vms_vendor', function ($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (
        empty($_POST['vms_vendor_tax_nonce']) ||
        !wp_verify_nonce($_POST['vms_vendor_tax_nonce'], 'vms_save_vendor_tax_profile')
    ) {
        return;
    }

    update_post_meta($post_id, '_vms_tax_type', sanitize_text_field($_POST['vms_tax_type'] ?? ''));
    update_post_meta($post_id, '_vms_tax_name', sanitize_text_field($_POST['vms_tax_name'] ?? ''));
    update_post_meta($post_id, '_vms_tax_address1', sanitize_text_field($_POST['vms_tax_address1'] ?? ''));
    update_post_meta($post_id, '_vms_tax_city', sanitize_text_field($_POST['vms_tax_city'] ?? ''));
    update_post_meta($post_id, '_vms_tax_state', sanitize_text_field($_POST['vms_tax_state'] ?? ''));
    update_post_meta($post_id, '_vms_tax_zip', sanitize_text_field($_POST['vms_tax_zip'] ?? ''));

}, 20);
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
        'show_in_menu'    => 'vms',
        'menu_position'   => 26,
        'menu_icon'       => 'dashicons-groups',
        'supports'        => array('title', 'editor', 'thumbnail'), // add thumbnail for logo support
        'capability_type' => 'post',
        'has_archive'     => false,
        'rewrite'         => false,
    );

    register_post_type('vms_vendor', $args);
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

        add_action('add_meta_boxes', function () {
            add_meta_box(
                'vms_vendor_tax_profile',
                __('Tax Profile', 'vms'),
                'vms_render_vendor_tax_profile_metabox',
                'vms_vendor',
                'side',
                'high'
            );
        });
    }

    /**
     * Render the Vendor Details meta box.
     */
    public function render_vendor_details_meta_box($post)
    {
        // Security nonce.
        wp_nonce_field('vms_save_vendor_details', 'vms_vendor_details_nonce');

        $contact_name      = get_post_meta($post->ID, '_vms_contact_name', true);
        $contact_email     = get_post_meta($post->ID, '_vms_contact_email', true);
        $contact_phone     = get_post_meta($post->ID, '_vms_contact_phone', true);
        $website_url       = get_post_meta($post->ID, '_vms_website_url', true);
        $fee_min           = get_post_meta($post->ID, '_vms_fee_min', true);
        $fee_max           = get_post_meta($post->ID, '_vms_fee_max', true);
        $min_show_rate     = get_post_meta($post->ID, '_vms_min_show_rate', true);
        // Vendor payment defaults
        $default_comp_structure = get_post_meta($post->ID, '_vms_default_comp_structure', true);
        $default_flat_fee       = get_post_meta($post->ID, '_vms_default_flat_fee_amount', true);
        $default_door_split     = get_post_meta($post->ID, '_vms_default_door_split_percent', true);

        if (!$default_comp_structure) $default_comp_structure = 'flat_fee';
?>

        <hr />
        <h4><?php esc_html_e('Payment Defaults (used to auto-fill Event Plans)', 'vms'); ?></h4>

        <p>
            <label for="vms_default_comp_structure"><strong><?php esc_html_e('Default Structure', 'vms'); ?></strong></label><br />
            <select id="vms_default_comp_structure" name="vms_default_comp_structure">
                <option value="flat_fee" <?php selected($default_comp_structure, 'flat_fee'); ?>>
                    <?php esc_html_e('Flat Fee Only', 'vms'); ?>
                </option>
                <option value="flat_fee_door_split" <?php selected($default_comp_structure, 'flat_fee_door_split'); ?>>
                    <?php esc_html_e('Flat Fee + Door Split', 'vms'); ?>
                </option>
                <option value="door_split" <?php selected($default_comp_structure, 'door_split'); ?>>
                    <?php esc_html_e('Door Split Only', 'vms'); ?>
                </option>
            </select>
        </p>

        <p>
            <label for="vms_default_flat_fee_amount"><strong><?php esc_html_e('Default Flat Fee Amount', 'vms'); ?></strong></label><br />
            <input type="number" id="vms_default_flat_fee_amount" name="vms_default_flat_fee_amount" style="width: 140px;"
                value="<?php echo esc_attr($default_flat_fee); ?>" step="0.01" min="0" />
        </p>

        <p>
            <label for="vms_default_door_split_percent"><strong><?php esc_html_e('Default Door Split Percentage', 'vms'); ?></strong></label><br />
            <input type="number" id="vms_default_door_split_percent" name="vms_default_door_split_percent" style="width: 140px;"
                value="<?php echo esc_attr($default_door_split); ?>" step="0.01" min="0" max="100" /> %
        </p>

        <p>
            <label for="vms_contact_name"><strong><?php esc_html_e('Contact Name', 'vms'); ?></strong></label><br />
            <input type="text" id="vms_contact_name" name="vms_contact_name" class="regular-text"
                value="<?php echo esc_attr($contact_name); ?>" />
        </p>

        <p>
            <label for="vms_contact_email"><strong><?php esc_html_e('Contact Email', 'vms'); ?></strong></label><br />
            <input type="email" id="vms_contact_email" name="vms_contact_email" class="regular-text"
                value="<?php echo esc_attr($contact_email); ?>" />
        </p>

        <p>
            <label for="vms_contact_phone"><strong><?php esc_html_e('Contact Phone', 'vms'); ?></strong></label><br />
            <input type="text" id="vms_contact_phone" name="vms_contact_phone" class="regular-text"
                value="<?php echo esc_attr($contact_phone); ?>" />
        </p>

        <p>
            <label for="vms_website_url"><strong><?php esc_html_e('Website / Social Link', 'vms'); ?></strong></label><br />
            <input type="url" id="vms_website_url" name="vms_website_url" class="regular-text"
                value="<?php echo esc_attr($website_url); ?>" />
        </p>

        <hr />

        <p>
            <label><strong><?php esc_html_e('Preferred Flat Fee Range', 'vms'); ?></strong></label><br />
            <span><?php esc_html_e('Min', 'vms'); ?></span>
            <input type="number" id="vms_fee_min" name="vms_fee_min" style="width: 120px;"
                value="<?php echo esc_attr($fee_min); ?>" /> &nbsp;
            <span><?php esc_html_e('Max', 'vms'); ?></span>
            <input type="number" id="vms_fee_max" name="vms_fee_max" style="width: 120px;"
                value="<?php echo esc_attr($fee_max); ?>" />
        </p>

        <p>
            <label for="vms_min_show_rate"><strong><?php esc_html_e('Minimum Acceptable Show Rate', 'vms'); ?></strong></label><br />
            <input type="number" id="vms_min_show_rate" name="vms_min_show_rate" style="width: 120px;"
                value="<?php echo esc_attr($min_show_rate); ?>" />
        </p>

<?php
    }

    /**
     * Save Vendor meta fields.
     */
    public function save_vendor_meta($post_id, $post)
    {
        // Check nonce.
        if (
            ! isset($_POST['vms_vendor_details_nonce']) ||
            ! wp_verify_nonce($_POST['vms_vendor_details_nonce'], 'vms_save_vendor_details')
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

        // Sanitize and save fields.
        $fields = array(
            '_vms_contact_name'   => isset($_POST['vms_contact_name']) ? sanitize_text_field($_POST['vms_contact_name']) : '',
            '_vms_contact_email'  => isset($_POST['vms_contact_email']) ? sanitize_email($_POST['vms_contact_email']) : '',
            '_vms_contact_phone'  => isset($_POST['vms_contact_phone']) ? sanitize_text_field($_POST['vms_contact_phone']) : '',
            '_vms_website_url'    => isset($_POST['vms_website_url']) ? esc_url_raw($_POST['vms_website_url']) : '',
            '_vms_fee_min'        => isset($_POST['vms_fee_min']) ? floatval($_POST['vms_fee_min']) : '',
            '_vms_fee_max'        => isset($_POST['vms_fee_max']) ? floatval($_POST['vms_fee_max']) : '',
            '_vms_min_show_rate'  => isset($_POST['vms_min_show_rate']) ? floatval($_POST['vms_min_show_rate']) : '',
            '_vms_default_comp_structure'       => isset($_POST['vms_default_comp_structure']) ? sanitize_text_field($_POST['vms_default_comp_structure']) : '',
            '_vms_default_flat_fee_amount'      => isset($_POST['vms_default_flat_fee_amount']) ? floatval($_POST['vms_default_flat_fee_amount']) : '',
            '_vms_default_door_split_percent'   => isset($_POST['vms_default_door_split_percent']) ? floatval($_POST['vms_default_door_split_percent']) : '',
        );

        foreach ($fields as $meta_key => $value) {
            if ($value === '' || $value === null) {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }
    }
}

// function vms_render_vendor_tax_profile_metabox($post) {

//     $type   = get_post_meta($post->ID, '_vms_tax_type', true);
//     $name   = get_post_meta($post->ID, '_vms_tax_name', true);
//     $addr1  = get_post_meta($post->ID, '_vms_tax_address1', true);
//     $city   = get_post_meta($post->ID, '_vms_tax_city', true);
//     $state  = get_post_meta($post->ID, '_vms_tax_state', true);
//     $zip    = get_post_meta($post->ID, '_vms_tax_zip', true);

//     wp_nonce_field('vms_save_vendor_tax_profile', 'vms_vendor_tax_nonce');

//     echo '<p><strong>' . esc_html__('Required for booking', 'vms') . '</strong></p>';

//     echo '<p>
//         <label>Tax Type</label><br>
//         <select name="vms_tax_type">
//             <option value="">— Select —</option>
//             <option value="individual" ' . selected($type, 'individual', false) . '>Individual</option>
//             <option value="llc" ' . selected($type, 'llc', false) . '>LLC</option>
//             <option value="corporation" ' . selected($type, 'corporation', false) . '>Corporation</option>
//         </select>
//     </p>';

//     echo '<p>
//         <label>Legal Name</label><br>
//         <input type="text" name="vms_tax_name" value="' . esc_attr($name) . '" style="width:100%;">
//     </p>';

//     echo '<p>
//         <label>Address</label><br>
//         <input type="text" name="vms_tax_address1" value="' . esc_attr($addr1) . '" style="width:100%;">
//     </p>';

//     echo '<p>
//         <label>City / State / ZIP</label><br>
//         <input type="text" name="vms_tax_city" value="' . esc_attr($city) . '" style="width:48%;"> 
//         <input type="text" name="vms_tax_state" value="' . esc_attr($state) . '" style="width:18%;"> 
//         <input type="text" name="vms_tax_zip" value="' . esc_attr($zip) . '" style="width:28%;">
//     </p>';

//     if (!vms_is_vendor_tax_profile_complete($post->ID)) {
//         echo '<p style="color:#b32d2e;"><strong>Incomplete</strong></p>';
//     } else {
//         echo '<p style="color:#0f766e;"><strong>Complete ✓</strong></p>';
//     }
// }