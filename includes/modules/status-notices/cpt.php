<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_status_notice_post_type')) {
	function bvmgr_status_notice_post_type(): string
	{
		return 'vms_notice';
	}
}

if (!function_exists('bvmgr_status_notice_register_cpt')) {
	function bvmgr_status_notice_register_cpt(): void
	{
		register_post_type(bvmgr_status_notice_post_type(), array(
			'labels' => array(
				'name' => __('Status Notices', 'backstage-venue-manager'),
				'singular_name' => __('Status Notice', 'backstage-venue-manager'),
			),
			'public' => false,
			'show_ui' => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'supports' => array('title', 'revisions'),
			'capability_type' => 'post',
			'map_meta_cap' => true,
		));
	}
}
add_action('init', 'bvmgr_status_notice_register_cpt', 9);
