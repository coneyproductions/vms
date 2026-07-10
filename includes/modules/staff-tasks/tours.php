<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_tasks_register_tours')) {
	/**
	 * @param array<int,array<string,mixed>> $tours
	 * @return array<int,array<string,mixed>>
	 */
	function vms_tasks_register_tours(array $tours): array
	{
		$tours[] = array(
			'id' => 'vms_staff_tasks_overview',
			'title' => __('Staff Tasks Overview', 'backstage-venue-manager'),
			'version' => 1,
			'contexts' => array(
				array(
					'context_key' => 'vms-staff-tasks-overview',
					'screen_id' => 'vms-dashboard_page_vms-tasks',
					'page_hook' => 'vms-dashboard_page_vms-tasks',
					'url' => 'admin.php?page=vms-tasks',
				),
			),
			'steps' => array(
				array('anchor' => 'tasks.help', 'title' => __('Help', 'backstage-venue-manager'), 'content' => __('Relaunch this tour any time with Help.', 'backstage-venue-manager'), 'placement' => 'left'),
				array('anchor' => 'tasks.add', 'title' => __('Add Task', 'backstage-venue-manager'), 'content' => __('Create event-linked or non-event tasks from this form.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'tasks.event-filter', 'title' => __('Event Filter', 'backstage-venue-manager'), 'content' => __('Switch between all tasks, event-linked tasks, and tasks not linked to an event.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'tasks.assignment', 'title' => __('Assignment', 'backstage-venue-manager'), 'content' => __('Set person, role, or scheduled role assignment per task.', 'backstage-venue-manager'), 'placement' => 'top'),
				array('anchor' => 'tasks.repeatable', 'title' => __('Repeatable Flow', 'backstage-venue-manager'), 'content' => __('Save task definitions as templates and include them in checklists for automatic generation.', 'backstage-venue-manager'), 'placement' => 'top'),
				array('anchor' => 'tasks.list', 'title' => __('Task List', 'backstage-venue-manager'), 'content' => __('Track open/done/skipped status and adjust assignments without leaving this screen.', 'backstage-venue-manager'), 'placement' => 'top'),
			),
		);

		$tours[] = array(
			'id' => 'vms_staff_tasks_templates',
			'title' => __('Task Templates', 'backstage-venue-manager'),
			'version' => 1,
			'contexts' => array(
				array(
					'context_key' => 'vms-staff-tasks-templates',
					'screen_id' => 'vms-dashboard_page_vms-task-templates',
					'page_hook' => 'vms-dashboard_page_vms-task-templates',
					'url' => 'admin.php?page=vms-task-templates',
				),
			),
			'steps' => array(
				array('anchor' => 'templates.help', 'title' => __('Help', 'backstage-venue-manager'), 'content' => __('Use Help to relaunch this tour later.', 'backstage-venue-manager'), 'placement' => 'left'),
				array('anchor' => 'templates.scope', 'title' => __('Template Context', 'backstage-venue-manager'), 'content' => __('Choose event-linked or not-linked-to-event task context.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'templates.assignment', 'title' => __('Assignment Rule', 'backstage-venue-manager'), 'content' => __('Set role, person, or scheduled role defaults.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'templates.due', 'title' => __('Due Behavior', 'backstage-venue-manager'), 'content' => __('Control due offsets and fixed-time behavior for generated tasks.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'templates.repeatable', 'title' => __('Repeatable Setup', 'backstage-venue-manager'), 'content' => __('Saved templates become repeatable when attached to checklist templates.', 'backstage-venue-manager'), 'placement' => 'top'),
				array('anchor' => 'templates.table', 'title' => __('Existing Templates', 'backstage-venue-manager'), 'content' => __('Open existing templates to edit lifecycle and defaults.', 'backstage-venue-manager'), 'placement' => 'top'),
			),
		);

		$tours[] = array(
			'id' => 'vms_staff_tasks_checklists',
			'title' => __('Checklist Templates', 'backstage-venue-manager'),
			'version' => 1,
			'contexts' => array(
				array(
					'context_key' => 'vms-staff-tasks-checklists',
					'screen_id' => 'vms-dashboard_page_vms-checklist-templates',
					'page_hook' => 'vms-dashboard_page_vms-checklist-templates',
					'url' => 'admin.php?page=vms-checklist-templates',
				),
			),
			'steps' => array(
				array('anchor' => 'checklists.help', 'title' => __('Help', 'backstage-venue-manager'), 'content' => __('Use Help to relaunch this checklist tour.', 'backstage-venue-manager'), 'placement' => 'left'),
				array('anchor' => 'checklists.scope', 'title' => __('Checklist Context', 'backstage-venue-manager'), 'content' => __('Checklist context limits which task templates can be included.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'checklists.apply-mode', 'title' => __('Apply Mode', 'backstage-venue-manager'), 'content' => __('Event checklists can apply globally, by venue, or by event type.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'checklists.tasks', 'title' => __('Template Inclusion', 'backstage-venue-manager'), 'content' => __('Choose templates to generate as task instances for matching events.', 'backstage-venue-manager'), 'placement' => 'top'),
				array('anchor' => 'checklists.generated', 'title' => __('Generation', 'backstage-venue-manager'), 'content' => __('Saved checklists are consumed by the event task generator.', 'backstage-venue-manager'), 'placement' => 'top'),
				array('anchor' => 'checklists.table', 'title' => __('Existing Checklists', 'backstage-venue-manager'), 'content' => __('Edit active checklists here as event operations change.', 'backstage-venue-manager'), 'placement' => 'top'),
			),
		);

		$tours[] = array(
			'id' => 'vms_staff_tasks_settings',
			'title' => __('Task Settings', 'backstage-venue-manager'),
			'version' => 1,
			'contexts' => array(
				array(
					'context_key' => 'vms-staff-tasks-settings',
					'screen_id' => 'vms-dashboard_page_vms-task-settings',
					'page_hook' => 'vms-dashboard_page_vms-task-settings',
					'url' => 'admin.php?page=vms-task-settings',
				),
			),
			'steps' => array(
				array('anchor' => 'task-settings.help', 'title' => __('Help', 'backstage-venue-manager'), 'content' => __('Relaunch this settings tour from Help at any time.', 'backstage-venue-manager'), 'placement' => 'left'),
				array('anchor' => 'task-settings.generation', 'title' => __('Generation Settings', 'backstage-venue-manager'), 'content' => __('These controls define event task generation windows and regeneration rules.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'task-settings.notifications', 'title' => __('Notifications', 'backstage-venue-manager'), 'content' => __('Enable assignment, due-soon, overdue, and digest notifications.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'task-settings.digest', 'title' => __('Digest Timing', 'backstage-venue-manager'), 'content' => __('Choose digest time and window for daily summaries.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'task-settings.dashboard', 'title' => __('Dashboard Cards', 'backstage-venue-manager'), 'content' => __('Configure dashboard visibility and event lookahead range.', 'backstage-venue-manager'), 'placement' => 'bottom'),
				array('anchor' => 'task-settings.save', 'title' => __('Save', 'backstage-venue-manager'), 'content' => __('Save settings to apply changes to future runs.', 'backstage-venue-manager'), 'placement' => 'top'),
			),
		);

		return $tours;
	}
}
add_filter('vms_register_tours', 'vms_tasks_register_tours');
