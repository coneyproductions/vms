<?php

defined('ABSPATH') || exit;
 
if (!function_exists('vms_register_tour')) {
	/**
	 * Runtime registry API for modules/add-ons.
	 *
	 * @param string $tour_id
	 * @param array<string,mixed> $args
	 */
	function vms_register_tour(string $tour_id, array $args): void
	{
		$tour_id = sanitize_key($tour_id);
		if ($tour_id === '') {
			return;
		}
		if (!isset($GLOBALS['vms_registered_tours']) || !is_array($GLOBALS['vms_registered_tours'])) {
			$GLOBALS['vms_registered_tours'] = array();
		}
		$args['id'] = $tour_id;
		$GLOBALS['vms_registered_tours'][$tour_id] = $args;
	}
}

if (!function_exists('vms_get_registered_tours')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_get_registered_tours(): array
	{
		$rows = isset($GLOBALS['vms_registered_tours']) && is_array($GLOBALS['vms_registered_tours'])
			? $GLOBALS['vms_registered_tours']
			: array();
		return array_values($rows);
	}
}

if (!function_exists('vms_get_tour_registry')) {
	/**
	 * Returns canonical tour registry.
	 */
	function vms_get_tour_registry(): array
	{
		$tours = array(
			array(
				'id'       => 'vms_welcome',
				'title'    => 'Backstage Venue Manager Guided Tour',
				'version'  => 1,
				'contexts' => array(
					array(
						'context_key' => 'vms-dashboard',
						'screen_id'   => 'toplevel_page_vms-dashboard',
						'page_hook'   => 'toplevel_page_vms-dashboard',
						'url'         => 'admin.php?page=vms-dashboard',
					),
				),
				'steps'    => array(
					array(
						'anchor'    => 'dashboard_welcome',
						'title'     => 'Welcome',
						'content'   => '<p>Welcome to the Backstage Venue Manager dashboard; pick your venue and scope filters before anything else.</p>',
						'placement' => 'bottom',
					),
					array(
						'anchor'    => 'dashboard_health',
						'title'     => 'Tour Health',
						'content'   => '<p>This panel surfaces drift health, lets you run scans, and copy the report for Codex.</p>',
						'placement' => 'left',
					),
					array(
						'anchor'    => 'dashboard_quick_actions',
						'title'     => 'Quick Actions',
						'content'   => '<p>Quickly start a venue, event plan, or vendor so you can keep operating momentum.</p>',
						'placement' => 'top',
					),
					array(
						'anchor'    => 'dashboard_start_venue',
						'title'     => 'Start a Venue',
						'content'   => '<p>Click here to begin your next venue setup.</p>',
						'placement' => 'right',
					),
				),
			),
		);

		$dynamic = vms_get_registered_tours();
		if (!empty($dynamic)) {
			$tours = array_merge($tours, $dynamic);
		}

		return (array) apply_filters('vms_register_tours', $tours);
	}
}
