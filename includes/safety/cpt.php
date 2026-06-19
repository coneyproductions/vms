<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_safety_register_cpts')) {
	function vms_safety_register_cpts(): void
	{
		$common = array(
			'public' => false,
			'show_ui' => false,
			'show_in_menu' => false,
			'show_in_admin_bar' => false,
			'show_in_nav_menus' => false,
			'supports' => array('title', 'editor'),
			'rewrite' => false,
			'has_archive' => false,
			'map_meta_cap' => true,
		);

		register_post_type('vms_incident', array_merge($common, array(
			'labels' => array(
				'name' => __('Incident Reports', 'vms'),
				'singular_name' => __('Incident Report', 'vms'),
			),
		)));

		register_post_type('vms_doc', array_merge($common, array(
			'labels' => array(
				'name' => __('Safety Documents', 'vms'),
				'singular_name' => __('Safety Document', 'vms'),
			),
		)));

		register_post_type('vms_checklist_tpl', array_merge($common, array(
			'labels' => array(
				'name' => __('Safety Checklist Templates', 'vms'),
				'singular_name' => __('Safety Checklist Template', 'vms'),
			),
		)));

		register_post_type('vms_checklist', array_merge($common, array(
			'labels' => array(
				'name' => __('Safety Checklists', 'vms'),
				'singular_name' => __('Safety Checklist', 'vms'),
			),
		)));
	}
}
add_action('init', 'vms_safety_register_cpts', 25);
