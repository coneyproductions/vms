<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers custom post types for VMS.
 */
class VMS_Post_Types {

    /**
     * Register all custom post types.
     */
    public function register() {
        $this->register_vendor_post_type();
        $this->register_event_plan_post_type();
    }

    /**
     * Register the Vendor post type.
     */
    protected function register_vendor_post_type() {
        $labels = array(
            'name'               => __( 'Vendors', 'vms' ),
            'singular_name'      => __( 'Vendor', 'vms' ),
            'add_new'            => __( 'Add New Vendor', 'vms' ),
            'add_new_item'       => __( 'Add New Vendor', 'vms' ),
            'edit_item'          => __( 'Edit Vendor', 'vms' ),
            'new_item'           => __( 'New Vendor', 'vms' ),
            'view_item'          => __( 'View Vendor', 'vms' ),
            'search_items'       => __( 'Search Vendors', 'vms' ),
            'not_found'          => __( 'No vendors found', 'vms' ),
            'not_found_in_trash' => __( 'No vendors found in Trash', 'vms' ),
            'menu_name'          => __( 'Vendors', 'vms' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false, // Not publicly queryable – managed via portal.
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_position'      => 26,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => array( 'title', 'editor' ), // title = vendor name, editor = notes.
            'capability_type'    => 'post',
            'has_archive'        => false,
            'rewrite'            => false,
        );

        register_post_type( 'vms_vendor', $args );
    }

    /**
     * Register the Event Plan post type.
     *
     * Each Event Plan represents the configuration for a specific date/event slot,
     * including schedule, compensation, notes, etc.
     */
    protected function register_event_plan_post_type() {
        $labels = array(
            'name'               => __( 'Event Plans', 'vms' ),
            'singular_name'      => __( 'Event Plan', 'vms' ),
            'add_new'            => __( 'Add New Event Plan', 'vms' ),
            'add_new_item'       => __( 'Add New Event Plan', 'vms' ),
            'edit_item'          => __( 'Edit Event Plan', 'vms' ),
            'new_item'           => __( 'New Event Plan', 'vms' ),
            'view_item'          => __( 'View Event Plan', 'vms' ),
            'search_items'       => __( 'Search Event Plans', 'vms' ),
            'not_found'          => __( 'No event plans found', 'vms' ),
            'not_found_in_trash' => __( 'No event plans found in Trash', 'vms' ),
            'menu_name'          => __( 'Event Plans', 'vms' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=vms_vendor', // group under Vendors menu.
            'supports'           => array( 'title', 'editor', 'thumbnail' ), // featured image = banner for the date.
            'capability_type'    => 'post',
            'has_archive'        => false,
            'rewrite'            => false,
        );

        register_post_type( 'vms_event_plan', $args );
    }
}
