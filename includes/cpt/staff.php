<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Staff (Labor Contractors)
 * CPT: vms_staff
 * Taxonomy (editable UI titles): vms_staff_role
 */

add_action('init', function () {

    // CPT: Staff
    register_post_type('vms_staff', array(
        'labels' => array(
            'name'               => __('Staff', 'vms'),
            'singular_name'      => __('Staff Member', 'vms'),
            'add_new'            => __('Add Staff', 'vms'),
            'add_new_item'       => __('Add New Staff Member', 'vms'),
            'edit_item'          => __('Edit Staff Member', 'vms'),
            'new_item'           => __('New Staff Member', 'vms'),
            'view_item'          => __('View Staff Member', 'vms'),
            'search_items'       => __('Search Staff', 'vms'),
            'not_found'          => __('No staff found', 'vms'),
            'not_found_in_trash' => __('No staff found in trash', 'vms'),
            'menu_name'          => __('Staff', 'vms'),
        ),
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'vms-season-board', // CPT menu item appears automatically under VMS parent via your menu setup
        'show_in_rest'        => false,
        'capability_type'     => 'post',
        'supports'            => array('title', 'thumbnail'),
        'has_archive'         => false,
        'rewrite'             => false,
        'menu_position'       => 27,
        'menu_icon'           => 'dashicons-id-alt',
    ));

    // Taxonomy: Staff Roles (editable in UI)
    register_taxonomy('vms_staff_role', array('vms_staff'), array(
        'labels' => array(
            'name'          => __('Staff Roles', 'vms'),
            'singular_name' => __('Staff Role', 'vms'),
            'menu_name'     => __('Roles', 'vms'),
            'all_items'     => __('All Roles', 'vms'),
            'edit_item'     => __('Edit Role', 'vms'),
            'view_item'     => __('View Role', 'vms'),
            'update_item'   => __('Update Role', 'vms'),
            'add_new_item'  => __('Add New Role', 'vms'),
            'new_item_name' => __('New Role Name', 'vms'),
            'search_items'  => __('Search Roles', 'vms'),
        ),
        'public'            => false,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => false,
        'hierarchical'      => true,
        'rewrite'           => false,
    ));
}, 5);

/**
 * Seed default roles once (editable later in UI).
 */
add_action('admin_init', function () {
    if (get_option('vms_staff_roles_seeded')) return;

    $defaults = array('Bar', 'Cleanup', 'Ticket Checker');

    foreach ($defaults as $name) {
        if (!term_exists($name, 'vms_staff_role')) {
            wp_insert_term($name, 'vms_staff_role');
        }
    }

    update_option('vms_staff_roles_seeded', 1);
});