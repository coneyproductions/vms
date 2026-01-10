<?php
if (!defined('ABSPATH')) exit;

add_action('init', 'vms_register_venue_cpt');

function vms_register_venue_cpt()
{

    $labels = array(
        'name'               => __('Venues', 'vms'),
        'singular_name'      => __('Venue', 'vms'),
        'menu_name'          => __('Venues', 'vms'),
        'add_new'            => __('Add New', 'vms'),
        'add_new_item'       => __('Add New Venue', 'vms'),
        'edit_item'          => __('Edit Venue', 'vms'),
        'new_item'           => __('New Venue', 'vms'),
        'view_item'          => __('View Venue', 'vms'),
        'search_items'       => __('Search Venues', 'vms'),
        'not_found'          => __('No venues found', 'vms'),
        'not_found_in_trash' => __('No venues found in Trash', 'vms'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => 'vms-season-board',
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-location-alt',
        'supports'           => array('title', 'editor', 'thumbnail'),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'rewrite'            => false,
    );

    register_post_type('vms_venue', $args);
}
