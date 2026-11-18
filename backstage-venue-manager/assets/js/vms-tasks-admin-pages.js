(function () {
	function initTasksPage() {
		var eventSelect = document.getElementById('vms_tasks_one_off_event');
		var venueRow = document.getElementById('vms_tasks_create_venue_row');
		var assignmentMode = document.getElementById('vms_tasks_one_off_assignment_mode');
		var scheduledOption = document.getElementById('vms_tasks_one_off_assignment_scheduled');
		var checklistSelect = document.getElementById('vms_tasks_one_off_repeatable_checklist');
		var recurrencePattern = document.getElementById('vms_tasks_one_off_recurrence_pattern');
		var recurrenceNDays = document.getElementById('vms_tasks_one_off_recurrence_n_days');
		var recurrenceNote = document.getElementById('vms_tasks_one_off_recurrence_note');
		if (!eventSelect || !venueRow || !assignmentMode || !scheduledOption || !checklistSelect || !recurrencePattern || !recurrenceNDays || !recurrenceNote) {
			return;
		}
		if (eventSelect.dataset.vmsTasksCreateBound === '1') {
			return;
		}
		eventSelect.dataset.vmsTasksCreateBound = '1';

		function syncContext() {
			var hasEvent = parseInt(eventSelect.value || '0', 10) > 0;
			var scope = hasEvent ? 'event' : 'general';
			var i;
			var option;
			var optionScope;

			venueRow.style.display = hasEvent ? 'none' : '';
			scheduledOption.hidden = !hasEvent;
			if (!hasEvent && assignmentMode.value === 'scheduled_role') {
				assignmentMode.value = 'role';
			}

			recurrencePattern.disabled = hasEvent;
			recurrenceNDays.disabled = hasEvent;
			recurrenceNote.style.display = hasEvent ? 'none' : '';
			if (hasEvent) {
				recurrencePattern.value = 'none';
				recurrenceNDays.style.display = 'none';
			}
			recurrenceNDays.style.display = recurrencePattern.value === 'every_n_days' && !hasEvent ? '' : 'none';

			for (i = 0; i < checklistSelect.options.length; i++) {
				option = checklistSelect.options[i];
				optionScope = option.getAttribute('data-scope');
				if (!optionScope) {
					option.hidden = false;
					continue;
				}

				option.hidden = optionScope !== scope;
				if (option.hidden && option.selected) {
					checklistSelect.value = '0';
				}
			}
		}

		eventSelect.addEventListener('change', syncContext);
		recurrencePattern.addEventListener('change', syncContext);
		syncContext();
	}

	function initChecklistTemplatesPage() {
		var scopeSelect = document.getElementById('vms_tasks_checklist_scope');
		var applyModeRow = document.getElementById('vms_tasks_checklist_apply_mode_row');
		var venueRow = document.getElementById('vms_tasks_checklist_venue_row');
		var eventTypeRow = document.getElementById('vms_tasks_checklist_event_type_row');
		var applyModeSelect = document.getElementById('vms_tasks_apply_mode');
		if (!scopeSelect || !applyModeRow || !venueRow || !eventTypeRow || !applyModeSelect) {
			return;
		}
		if (scopeSelect.dataset.vmsTasksChecklistBound === '1') {
			return;
		}
		scopeSelect.dataset.vmsTasksChecklistBound = '1';

		function syncChecklistContext() {
			var isGeneral = scopeSelect.value === 'general';
			applyModeRow.style.display = isGeneral ? 'none' : '';
			venueRow.style.display = isGeneral ? 'none' : '';
			eventTypeRow.style.display = isGeneral ? 'none' : '';
			if (isGeneral) {
				applyModeSelect.value = 'default_all_events';
			}
		}

		scopeSelect.addEventListener('change', syncChecklistContext);
		syncChecklistContext();
	}

	initTasksPage();
	initChecklistTemplatesPage();
}());
