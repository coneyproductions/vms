<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_safety_register_tours')) {
	/**
	 * @param array<int,array<string,mixed>> $tours
	 * @return array<int,array<string,mixed>>
	 */
	function vms_safety_register_tours(array $tours): array
	{
		$tours[] = array(
			'id' => 'vms_safety_overview',
			'title' => __('Safety Toolkit Tour', 'backstage-venue-manager'),
			'version' => 1,
			'contexts' => array(
				array(
					'context_key' => 'vms-safety',
					'screen_id' => 'vms-dashboard_page_vms-safety',
					'page_hook' => 'vms-dashboard_page_vms-safety',
					'url' => 'admin.php?page=vms-safety',
				),
			),
			'steps' => array(
				array('anchor' => 'safety.help', 'title' => __('What this module is', 'backstage-venue-manager'), 'content' => __('This toolkit helps operational documentation. It is not legal advice and does not certify compliance.', 'backstage-venue-manager'), 'placement' => 'left'),
				array('anchor' => 'safety.tabs', 'title' => __('Work Areas', 'backstage-venue-manager'), 'content' => __('Use tabs to move between incidents, private documents, checklists, and settings.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'safety.incidents.form', 'title' => __('Incident Reports', 'backstage-venue-manager'), 'content' => __('Capture event details, actions, and attachments in one place.', 'backstage-venue-manager'), 'placement' => 'top'),
				array('anchor' => 'safety.documents.upload', 'title' => __('Private Document Vault', 'backstage-venue-manager'), 'content' => __('Upload files to private storage and track review dates/version lineage.', 'backstage-venue-manager'), 'placement' => 'top'),
				array('anchor' => 'safety.checklists.templates', 'title' => __('Checklist Templates', 'backstage-venue-manager'), 'content' => __('Create reusable checklists and generate event-linked instances.', 'backstage-venue-manager'), 'placement' => 'top'),
				array('anchor' => 'safety.savebar', 'title' => __('Mobile Save Bar', 'backstage-venue-manager'), 'content' => __('On mobile, edited forms expose a sticky save action for faster operations.', 'backstage-venue-manager'), 'placement' => 'top'),
			),
		);

		return $tours;
	}
}
add_filter('vms_register_tours', 'vms_safety_register_tours');
