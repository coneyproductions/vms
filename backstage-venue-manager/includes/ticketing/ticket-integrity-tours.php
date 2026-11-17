<?php
defined('ABSPATH') || exit;

function bvmgr_ticket_integrity_register_tours(array $tours): array
{
	$tours[] = array(
		'id' => 'vms.ticket_integrity.monitor',
		'title' => __('Ticket Integrity Monitor', 'backstage-venue-manager'),
		'version' => 5,
		'contexts' => array(
			array(
				'context_key' => 'vms-ticket-integrity',
				'screen_id' => 'vms-dashboard_page_vms-ticket-integrity',
				'page_hook' => 'vms-dashboard_page_vms-ticket-integrity',
				'url' => 'admin.php?page=vms-ticket-integrity',
			),
		),
		'steps' => array(
			array(
				'anchor' => 'ticket-integrity.help',
				'title' => __('Help + Tour Relaunch', 'backstage-venue-manager'),
				'content' => __('Use this button any time you need to relaunch the monitor walkthrough after operators change settings or workflows.', 'backstage-venue-manager'),
				'placement' => 'left',
			),
			array(
				'anchor' => 'ticket-integrity.run',
				'title' => __('Run Check Now', 'backstage-venue-manager'),
				'content' => __('Manual runs scan the current upcoming event window on demand. The monitor stays read-only unless you explicitly choose a repair action later.', 'backstage-venue-manager'),
				'placement' => 'bottom',
			),
			array(
				'anchor' => 'ticket-integrity.summary',
				'title' => __('Severity Overview', 'backstage-venue-manager'),
				'content' => __('Green means no current issues, Yellow means suspicious drift, Red means likely customer-facing failure, and Informational marks cleanup-only residue or inactive-event context.', 'backstage-venue-manager'),
				'placement' => 'bottom',
			),
			array(
				'anchor' => 'ticket-integrity.settings',
				'title' => __('Nightly Scan + Alerts', 'backstage-venue-manager'),
				'content' => __('These settings control the daily scan window and optional alert emails. Email stays off by default so new installs do not spam operators.', 'backstage-venue-manager'),
				'placement' => 'top',
			),
			array(
				'anchor' => 'ticket-integrity.table',
				'title' => __('Operator Results Table', 'backstage-venue-manager'),
				'content' => __('This table is the working queue: read the plain-English issue summary first, then open details to inspect product lineage, mappings, and event-specific actions.', 'backstage-venue-manager'),
				'placement' => 'top',
			),
			array(
				'anchor' => 'ticket-integrity.rebuild',
				'title' => __('Rebuild Ticket Config', 'backstage-venue-manager'),
				'content' => __('Rebuild now attempts a real repair. It can normalize stale mappings, rerun the live sync path, and then report whether anything changed, whether conflicts remain, or whether the event was too ambiguous to repair safely.', 'backstage-venue-manager'),
				'placement' => 'top',
			),
			array(
				'anchor' => 'ticket-integrity.diagnostics',
				'title' => __('Mutation Diagnostics', 'backstage-venue-manager'),
				'content' => __('Use this section to see whether an event looks VMS-native or imported legacy, which process last touched the mapping, whether drift has come back after repair, and what the next operator action should be.', 'backstage-venue-manager'),
				'placement' => 'top',
			),
			array(
				'anchor' => 'ticket-integrity.inventory',
				'title' => __('Inventory Forensics', 'backstage-venue-manager'),
				'content' => __('This panel now treats Woo as the primary inventory layer to verify. Use it to compare VMS intent, current Woo sellability, and current TEC availability so you can tell whether Woo is wrong, TEC is only reflecting Woo, or Woo was repaired and then later re-corrupted.', 'backstage-venue-manager'),
				'placement' => 'top',
			),
			array(
				'anchor' => 'ticket-integrity.repair-diagnostics',
				'title' => __('Repair Diagnostics', 'backstage-venue-manager'),
				'content' => __('Use this section after Rebuild Ticket Config runs. It now records whether Woo verified cleanly, whether TEC still needs follow-up after Woo is correct, and whether a later writer appears to have undone the repair.', 'backstage-venue-manager'),
				'placement' => 'top',
			),
		),
	);

	return $tours;
}
add_filter('vms_register_tours', 'bvmgr_ticket_integrity_register_tours');
