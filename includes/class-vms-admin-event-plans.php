<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin functionality for VMS Event Plans.
 */
class VMS_Admin_Event_Plans {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
        add_action( 'save_post_vms_event_plan', array( $this, 'save_event_plan_meta' ), 10, 2 );
    }

    /**
     * Register meta boxes for the Event Plan post type.
     */
    public function register_meta_boxes() {
        add_meta_box(
            'vms_event_plan_details',
            __( 'Event Plan Details', 'vms' ),
            array( $this, 'render_event_plan_details_meta_box' ),
            'vms_event_plan',
            'normal',
            'default'
        );
    }

    /**
     * Render the Event Plan Details meta box.
     */
    public function render_event_plan_details_meta_box( $post ) {
        // Security nonce.
        wp_nonce_field( 'vms_save_event_plan_details', 'vms_event_plan_details_nonce' );

        $event_date          = get_post_meta( $post->ID, '_vms_event_date', true );
        $start_time          = get_post_meta( $post->ID, '_vms_start_time', true );
        $end_time            = get_post_meta( $post->ID, '_vms_end_time', true );
        $location_label      = get_post_meta( $post->ID, '_vms_location_label', true );

        $comp_structure      = get_post_meta( $post->ID, '_vms_comp_structure', true );
        if ( empty( $comp_structure ) ) {
            $comp_structure = 'flat_fee'; // default.
        }

        $flat_fee_amount     = get_post_meta( $post->ID, '_vms_flat_fee_amount', true );
        $door_split_percent  = get_post_meta( $post->ID, '_vms_door_split_percent', true );

        $allow_vendor_propose = get_post_meta( $post->ID, '_vms_allow_vendor_propose', true );
        $proposal_min         = get_post_meta( $post->ID, '_vms_proposal_min', true );
        $proposal_max         = get_post_meta( $post->ID, '_vms_proposal_max', true );
        $proposal_cap         = get_post_meta( $post->ID, '_vms_proposal_cap', true );
        ?>

        <p>
            <label for="vms_event_date"><strong><?php esc_html_e( 'Event Date', 'vms' ); ?></strong></label><br />
            <input type="date" id="vms_event_date" name="vms_event_date"
                   value="<?php echo esc_attr( $event_date ); ?>" />
        </p>

        <p>
            <label for="vms_start_time"><strong><?php esc_html_e( 'Start Time', 'vms' ); ?></strong></label><br />
            <input type="time" id="vms_start_time" name="vms_start_time"
                   value="<?php echo esc_attr( $start_time ); ?>" />
        </p>

        <p>
            <label for="vms_end_time"><strong><?php esc_html_e( 'End Time', 'vms' ); ?></strong></label><br />
            <input type="time" id="vms_end_time" name="vms_end_time"
                   value="<?php echo esc_attr( $end_time ); ?>" />
        </p>

        <p>
            <label for="vms_location_label"><strong><?php esc_html_e( 'Location / Resource', 'vms' ); ?></strong></label><br />
            <input type="text" id="vms_location_label" name="vms_location_label" class="regular-text"
                   value="<?php echo esc_attr( $location_label ); ?>" />
            <br /><span class="description">
                <?php esc_html_e( 'Example: Main Stage, Patio, Food Truck Row, etc.', 'vms' ); ?>
            </span>
        </p>

        <hr />

        <h4><?php esc_html_e( 'Compensation Structure', 'vms' ); ?></h4>

        <p>
            <label for="vms_comp_structure"><strong><?php esc_html_e( 'Structure', 'vms' ); ?></strong></label><br />
            <select id="vms_comp_structure" name="vms_comp_structure">
                <option value="flat_fee" <?php selected( $comp_structure, 'flat_fee' ); ?>>
                    <?php esc_html_e( 'Flat Fee Only', 'vms' ); ?>
                </option>
                <option value="flat_fee_door_split" <?php selected( $comp_structure, 'flat_fee_door_split' ); ?>>
                    <?php esc_html_e( 'Flat Fee + Door Split', 'vms' ); ?>
                </option>
                <option value="door_split" <?php selected( $comp_structure, 'door_split' ); ?>>
                    <?php esc_html_e( 'Door Split Only', 'vms' ); ?>
                </option>
            </select>
        </p>

        <p>
            <label for="vms_flat_fee_amount"><strong><?php esc_html_e( 'Flat Fee Amount', 'vms' ); ?></strong></label><br />
            <input type="number" id="vms_flat_fee_amount" name="vms_flat_fee_amount" style="width: 150px;"
                   value="<?php echo esc_attr( $flat_fee_amount ); ?>" />
        </p>

        <p>
            <label for="vms_door_split_percent"><strong><?php esc_html_e( 'Door Split Percentage', 'vms' ); ?></strong></label><br />
            <input type="number" id="vms_door_split_percent" name="vms_door_split_percent" style="width: 150px;"
                   value="<?php echo esc_attr( $door_split_percent ); ?>" /> %
        </p>

        <p>
            <label>
                <input type="checkbox" name="vms_allow_vendor_propose" value="1"
                    <?php checked( $allow_vendor_propose, '1' ); ?> />
                <?php esc_html_e( 'Let vendor propose flat fee for this event instead of using the fixed amount above', 'vms' ); ?>
            </label>
        </p>

        <div style="margin-left: 18px;">
            <p>
                <label><strong><?php esc_html_e( 'Suggested Proposal Range (optional)', 'vms' ); ?></strong></label><br />
                <span><?php esc_html_e( 'Min', 'vms' ); ?></span>
                <input type="number" id="vms_proposal_min" name="vms_proposal_min" style="width: 120px;"
                       value="<?php echo esc_attr( $proposal_min ); ?>" /> &nbsp;
                <span><?php esc_html_e( 'Max', 'vms' ); ?></span>
                <input type="number" id="vms_proposal_max" name="vms_proposal_max" style="width: 120px;"
                       value="<?php echo esc_attr( $proposal_max ); ?>" />
            </p>

            <p>
                <label for="vms_proposal_cap"><strong><?php esc_html_e( 'Hard Proposal Cap (optional)', 'vms' ); ?></strong></label><br />
                <input type="number" id="vms_proposal_cap" name="vms_proposal_cap" style="width: 150px;"
                       value="<?php echo esc_attr( $proposal_cap ); ?>" />
                <br /><span class="description">
                    <?php esc_html_e( 'If vendor proposes above this, it will be flagged for review.', 'vms' ); ?>
                </span>
            </p>
        </div>

        <hr />
        <p class="description">
            <?php esc_html_e( 'Featured Image: use the standard WordPress featured image box for this event\'s banner/hero.', 'vms' ); ?>
        </p>

        <?php
    }

    /**
     * Save Event Plan meta fields.
     */
    public function save_event_plan_meta( $post_id, $post ) {
        // Check nonce.
        if ( ! isset( $_POST['vms_event_plan_details_nonce'] ) ||
             ! wp_verify_nonce( $_POST['vms_event_plan_details_nonce'], 'vms_save_event_plan_details' ) ) {
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

        // Sanitize fields.
        $event_date         = isset( $_POST['vms_event_date'] ) ? sanitize_text_field( $_POST['vms_event_date'] ) : '';
        $start_time         = isset( $_POST['vms_start_time'] ) ? sanitize_text_field( $_POST['vms_start_time'] ) : '';
        $end_time           = isset( $_POST['vms_end_time'] ) ? sanitize_text_field( $_POST['vms_end_time'] ) : '';
        $location_label     = isset( $_POST['vms_location_label'] ) ? sanitize_text_field( $_POST['vms_location_label'] ) : '';

        $comp_structure     = isset( $_POST['vms_comp_structure'] ) ? sanitize_text_field( $_POST['vms_comp_structure'] ) : 'flat_fee';
        $flat_fee_amount    = isset( $_POST['vms_flat_fee_amount'] ) ? floatval( $_POST['vms_flat_fee_amount'] ) : '';
        $door_split_percent = isset( $_POST['vms_door_split_percent'] ) ? floatval( $_POST['vms_door_split_percent'] ) : '';

        $allow_vendor_propose = isset( $_POST['vms_allow_vendor_propose'] ) ? '1' : '0';
        $proposal_min         = isset( $_POST['vms_proposal_min'] ) ? floatval( $_POST['vms_proposal_min'] ) : '';
        $proposal_max         = isset( $_POST['vms_proposal_max'] ) ? floatval( $_POST['vms_proposal_max'] ) : '';
        $proposal_cap         = isset( $_POST['vms_proposal_cap'] ) ? floatval( $_POST['vms_proposal_cap'] ) : '';

        $fields = array(
            '_vms_event_date'         => $event_date,
            '_vms_start_time'         => $start_time,
            '_vms_end_time'           => $end_time,
            '_vms_location_label'     => $location_label,
            '_vms_comp_structure'     => $comp_structure,
            '_vms_flat_fee_amount'    => $flat_fee_amount,
            '_vms_door_split_percent' => $door_split_percent,
            '_vms_allow_vendor_propose' => $allow_vendor_propose,
            '_vms_proposal_min'       => $proposal_min,
            '_vms_proposal_max'       => $proposal_max,
            '_vms_proposal_cap'       => $proposal_cap,
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
