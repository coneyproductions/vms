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
			'title' => __('Staff Tasks Overview', 'vms'),
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
				array('anchor' => 'tasks.help', 'title' => __('Help', 'vms'), 'content' => __('Relaunch this tour any time with Help.', 'vms'), 'placement' => 'left'),
				array('anchor' => 'tasks.add', 'title' => __('Add Task', 'vms'), 'content' => __('Create event-linked or non-event tasks from this form.', 'vms'), 'placement' => 'bottom'),
				array('anchor' => 'tasks.event-filter', 'title' => __('Event Filter', 'vms'), 'content' => __('Switch between all tasks, event-linked tasks, and tasks not linked to an event.', 'vms'), 'placement' => 'bottom'),
				array('anchor' => 'tasks.assignment', 'title' => __('Assignment', 'vms'), 'content' => __('Set person, role, or scheduled role assignment per task.', 'vms'), 'placement' => 'top'),
				array('anchor' => 'tasks.repeatable', 'title' => __('Repeatable Flow', 'vms'), 'content' => __('Save task definitions as templates and include them in checklists for automatic generation.', 'vms'), 'placement' => 'top'),
				array('anchor' => 'tasks.list', 'title' => __('Task List', 'vms'), 'content' => __('Track open/done/skipped status and adjust assignments without leaving this screen.', 'vms'), 'placement' => 'top'),
			),
		);

		$tours[] = array(
			'id' => 'vms_staff_tasks_templates',
			'title' => __('Task Templates', 'vms'),
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
				array('anchor' => 'templates.help', 'title' => __('Help', 'vms'), 'content' => __('Use Help to relaunch this tour later.', 'vms'), 'placement' => 'left'),
				array('anchor' => 'templates.scope', 'title' => __('Template Context', 'vms'), 'content' => __('Choose event-linked or not-linked-to-event task context.', 'vms'), 'placement' => 'bottom'),
				array('anchor' => 'templates.assignment', 'title' => __('Assignment Rule', 'vms'), 'content' => __('Set role, person, or scheduled role defaults.', 'vms'), 'placement' => 'bottom'),
				array('anchor' => 'templates.due', 'title' => __('Due Behavior', 'vms'), 'content' => __('Control due offsets and fixed-time behavior for generated tasks.', 'vms'), 'placement' => 'bottom'),
				array('anchor' => 'templates.repeatable', 'title' => __('Repeatable Setup', 'vms'), 'content' => __('Saved templates become repeatable when attached to checklist templates.', 'vms'), 'placement' => 'top'),
				array('anchor' => 'templates.table', 'title' => __('Existing Templates', 'vms'), 'content' => __('Open existing templates to edit lifecycle and defaults.', 'vms'), 'placement' => 'top'),
			),
		);

		$tours[] = array(
			'id' => 'vms_staff_tasks_checklists',
			'title' => __('Checklist Templates', 'vms'),
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
				array('anchor' => 'checklists.help', 'title' => __('Help', 'vms'), 'content' => __('Use Help to relaunch this checklist tour.', 'vms'), 'placement' => 'left'),
				array('anchor' => 'checklists.scope', 'title' => __('Checklist Context', 'vms'), 'content' => __('Checklist context limits which task templates can be included.', 'vms'), 'placement' => 'bottom'),
				array('anchor' => 'checklists.apply-mode', 'title' => __('Apply Mode', 'vms'), 'content' => __('Event checklists can apply globally, by venue, or by event type.', 'vms'), 'placement' => 'bottom'),
				array('anchor' => 'checklists.tasks', 'title' => __('Template Inclusion', 'vms'), 'content' => __('Choose templates to generate as task instances for matching events.', 'vms'), 'placement' => 'top'),
				array('anchor' => 'checklists.generated', 'title' => __('Generation', 'vms'), 'content' => __('Saved checklists are consumed by the event task generator.', 'vms'), 'placement' => 'top'),
				array('anchor' => 'checklists.table', 'title' => __('Existing Checklists', 'vms'), 'content' => __('Edit active checklists here as event operations change.', 'vms'), 'placement' => 'top'),
			),
		);

		$tours[] = array(
			'id' => 'vms_staff_tasks_settings',
			'title' => __('Task Settings', 'vms'),
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
				array('anchor' => 'task-settings.help', 'title' => __('Help', 'vms'), 'content' => __('Relaunch this settings tour from Help at any time.', 'vms'), 'placement' => 'left'),
				array('anchor' => 'task-settings.generation', 'title' => __('Generation Settings', 'vms'), 'content' => __('These controls define event task generation windows and regeneration rules.', 'vms'), 'placement' => 'bottom'),
				array('anchor' => 'task-settings.notifications', 'title' => __('Notifications', 'vms'), 'content' => __('Enable assignment, due-soon, overdue, and digest notifications.', 'vms'), 'placement' => 'bottom'),
				array('anchor' => 'task-settings.digest', 'title' => __('Digest Timing', 'vms'), 'content' => __('Choose digest time and window for daily summaries.', 'vms'), 'placement' => 'bottom'),
				array('anchor' => 'task-settings.dashboard', 'title' => __('Dashboard Cards', 'vms'), 'content' => __('Configure dashboard visibility and event lookahead range.', 'vms'), 'placement' => 'bottom'),
				array('anchor' => 'task-settings.save', 'title' => __('Save', 'vms'), 'content' => __('Save settings to apply changes to future runs.', 'vms'), 'placement' => 'top'),
			),
		);

		return $tours;
	}
}
add_filter('vms_register_tours', 'vms_tasks_register_tours');
