<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin functionality for VMS Vendors.
 */
class VMS_Admin_Vendors {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
        add_action( 'save_post_vms_vendor', array( $this, 'save_vendor_meta' ), 10, 2 );
    }

    /**
     * Register meta boxes for the Vendor post type.
     */
    public function register_meta_boxes() {
        add_meta_box(
            'vms_vendor_details',
            __( 'Vendor Details', 'vms' ),
            array( $this, 'render_vendor_details_meta_box' ),
            'vms_vendor',
            'normal',
            'default'
        );
    }

    /**
     * Render the Vendor Details meta box.
     */
    public function render_vendor_details_meta_box( $post ) {
        // Security nonce.
        wp_nonce_field( 'vms_save_vendor_details', 'vms_vendor_details_nonce' );

        $contact_name      = get_post_meta( $post->ID, '_vms_contact_name', true );
        $contact_email     = get_post_meta( $post->ID, '_vms_contact_email', true );
        $contact_phone     = get_post_meta( $post->ID, '_vms_contact_phone', true );
        $website_url       = get_post_meta( $post->ID, '_vms_website_url', true );
        $fee_min           = get_post_meta( $post->ID, '_vms_fee_min', true );
        $fee_max           = get_post_meta( $post->ID, '_vms_fee_max', true );
        $min_show_rate     = get_post_meta( $post->ID, '_vms_min_show_rate', true );
        ?>

        <p>
            <label for="vms_contact_name"><strong><?php esc_html_e( 'Contact Name', 'vms' ); ?></strong></label><br />
            <input type="text" id="vms_contact_name" name="vms_contact_name" class="regular-text"
                   value="<?php echo esc_attr( $contact_name ); ?>" />
        </p>

        <p>
            <label for="vms_contact_email"><strong><?php esc_html_e( 'Contact Email', 'vms' ); ?></strong></label><br />
            <input type="email" id="vms_contact_email" name="vms_contact_email" class="regular-text"
                   value="<?php echo esc_attr( $contact_email ); ?>" />
        </p>

        <p>
            <label for="vms_contact_phone"><strong><?php esc_html_e( 'Contact Phone', 'vms' ); ?></strong></label><br />
            <input type="text" id="vms_contact_phone" name="vms_contact_phone" class="regular-text"
                   value="<?php echo esc_attr( $contact_phone ); ?>" />
        </p>

        <p>
            <label for="vms_website_url"><strong><?php esc_html_e( 'Website / Social Link', 'vms' ); ?></strong></label><br />
            <input type="url" id="vms_website_url" name="vms_website_url" class="regular-text"
                   value="<?php echo esc_attr( $website_url ); ?>" />
        </p>

        <hr />

        <p>
            <label><strong><?php esc_html_e( 'Preferred Flat Fee Range', 'vms' ); ?></strong></label><br />
            <span><?php esc_html_e( 'Min', 'vms' ); ?></span>
            <input type="number" id="vms_fee_min" name="vms_fee_min" style="width: 120px;"
                   value="<?php echo esc_attr( $fee_min ); ?>" /> &nbsp;
            <span><?php esc_html_e( 'Max', 'vms' ); ?></span>
            <input type="number" id="vms_fee_max" name="vms_fee_max" style="width: 120px;"
                   value="<?php echo esc_attr( $fee_max ); ?>" />
        </p>

        <p>
            <label for="vms_min_show_rate"><strong><?php esc_html_e( 'Minimum Acceptable Show Rate', 'vms' ); ?></strong></label><br />
            <input type="number" id="vms_min_show_rate" name="vms_min_show_rate" style="width: 120px;"
                   value="<?php echo esc_attr( $min_show_rate ); ?>" />
        </p>

        <?php
    }

    /**
     * Save Vendor meta fields.
     */
    public function save_vendor_meta( $post_id, $post ) {
        // Check nonce.
        if ( ! isset( $_POST['vms_vendor_details_nonce'] ) ||
             ! wp_verify_nonce( $_POST['vms_vendor_details_nonce'], 'vms_save_vendor_details' ) ) {
            return;
        }

        // Avoid autosaves.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check user capability.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Sanitize and save fields.
        $fields = array(
            '_vms_contact_name'   => isset( $_POST['vms_contact_name'] ) ? sanitize_text_field( $_POST['vms_contact_name'] ) : '',
            '_vms_contact_email'  => isset( $_POST['vms_contact_email'] ) ? sanitize_email( $_POST['vms_contact_email'] ) : '',
            '_vms_contact_phone'  => isset( $_POST['vms_contact_phone'] ) ? sanitize_text_field( $_POST['vms_contact_phone'] ) : '',
            '_vms_website_url'    => isset( $_POST['vms_website_url'] ) ? esc_url_raw( $_POST['vms_website_url'] ) : '',
            '_vms_fee_min'        => isset( $_POST['vms_fee_min'] ) ? floatval( $_POST['vms_fee_min'] ) : '',
            '_vms_fee_max'        => isset( $_POST['vms_fee_max'] ) ? floatval( $_POST['vms_fee_max'] ) : '',
            '_vms_min_show_rate'  => isset( $_POST['vms_min_show_rate'] ) ? floatval( $_POST['vms_min_show_rate'] ) : '',
        );

        foreach ( $fields as $meta_key => $value ) {
            if ( $value === '' || $value === null ) {
                delete_post_meta( $post_id, $meta_key );
            } else {
                update_post_meta( $post_id, $meta_key, $value );
            }
        }
    }
}
 