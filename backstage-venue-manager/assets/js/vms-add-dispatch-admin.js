(function () {
	function initRequestBuilder() {
		var sendButton = document.querySelector('[data-vms-add-send-button]');
		var root;
		var filterForm;
		var selectButton;
		var clearButton;
		var eligibleCount;
		var selectedCount;

		if (!sendButton) {
			return;
		}

		root = sendButton.form;
		if (!root) {
			return;
		}
		if (root.dataset.vmsAddDispatchBound === '1') {
			return;
		}
		root.dataset.vmsAddDispatchBound = '1';

		filterForm = root.previousElementSibling;
		selectButton = root.querySelector('[data-vms-add-select-all]');
		clearButton = root.querySelector('[data-vms-add-clear-all]');
		eligibleCount = root.querySelector('[data-vms-add-eligible-count]');
		selectedCount = root.querySelector('[data-vms-add-selected-count]');

		function filterChecked(name) {
			var field = filterForm ? filterForm.querySelector('[name="' + name + '"]') : null;
			return !!(field && field.checked);
		}

		function syncHidden(name, checked) {
			var fields = root.querySelectorAll('input[type="hidden"][name="' + name + '"]');
			var i;
			for (i = 0; i < fields.length; i++) {
				fields[i].value = checked ? '1' : '0';
			}
		}

		function rowDetail(row, reason) {
			var detail = row.querySelector('[data-vms-add-decision-detail]');
			if (detail) {
				detail.textContent = reason || '';
			}
		}

		function rowLabel(row, label) {
			var target = row.querySelector('[data-vms-add-decision-label]');
			if (target) {
				target.textContent = label;
			}
		}

		function isEligible(row) {
			var state = row.getAttribute('data-vms-add-state') || '';
			if (row.getAttribute('data-vms-add-contactable') !== '1') {
				rowDetail(row, row.getAttribute('data-vms-add-no-email-detail') || 'No vendor email on file.');
				return false;
			}
			if (row.getAttribute('data-vms-add-base-selectable') !== '1') {
				rowDetail(row, row.getAttribute('data-vms-add-default-detail') || 'Not eligible.');
				return false;
			}
			if (state === 'no-response' && !filterChecked('include_no_response')) {
				rowDetail(row, row.getAttribute('data-vms-add-no-response-detail') || 'Enable no-response vendors to select this contact.');
				return false;
			}
			if (state === 'tentative' && !filterChecked('include_tentative')) {
				rowDetail(row, row.getAttribute('data-vms-add-tentative-detail') || 'Enable tentative vendors to select this contact.');
				return false;
			}
			if (row.getAttribute('data-vms-add-previously-contacted') === '1' && !filterChecked('include_previously_contacted')) {
				rowDetail(row, row.getAttribute('data-vms-add-previous-detail') || 'Enable previously contacted vendors to select this contact.');
				return false;
			}

			rowDetail(
				row,
				state === 'no-response'
					? 'No response / no setup. Select this row to contact them.'
					: (row.getAttribute('data-vms-add-default-detail') || 'Select this row to contact them.')
			);
			return true;
		}

		function boxes() {
			return root.querySelectorAll('.vms-add-recipient-checkbox');
		}

		function update() {
			var eligible = 0;
			var selected = 0;
			var rows = root.querySelectorAll('.vms-add-recipient-row');
			var i;
			var box;
			var ok;

			syncHidden('include_no_response', filterChecked('include_no_response'));
			syncHidden('include_unknown', filterChecked('include_no_response'));
			syncHidden('include_tentative', filterChecked('include_tentative'));
			syncHidden('include_previously_contacted', filterChecked('include_previously_contacted'));

			for (i = 0; i < rows.length; i++) {
				box = rows[i].querySelector('.vms-add-recipient-checkbox');
				ok = isEligible(rows[i]);
				if (box) {
					box.disabled = !ok;
					if (!ok) {
						box.checked = false;
					}
					if (box.checked) {
						selected++;
					}
				}
				if (ok) {
					eligible++;
					rowLabel(rows[i], 'Selectable.');
				} else {
					rowLabel(rows[i], 'Excluded.');
				}
			}

			if (eligibleCount) {
				eligibleCount.textContent = String(eligible);
			}
			if (selectedCount) {
				selectedCount.textContent = String(selected);
			}
			sendButton.disabled = selected <= 0;
		}

		if (selectButton) {
			selectButton.addEventListener('click', function () {
				var checkboxNodes = boxes();
				var i;
				for (i = 0; i < checkboxNodes.length; i++) {
					if (!checkboxNodes[i].disabled) {
						checkboxNodes[i].checked = true;
					}
				}
				update();
			});
		}

		if (clearButton) {
			clearButton.addEventListener('click', function () {
				var checkboxNodes = boxes();
				var i;
				for (i = 0; i < checkboxNodes.length; i++) {
					checkboxNodes[i].checked = false;
				}
				update();
			});
		}

		(function bindBoxListeners() {
			var checkboxNodes = boxes();
			var i;
			for (i = 0; i < checkboxNodes.length; i++) {
				checkboxNodes[i].addEventListener('change', update);
			}
		}());

		if (filterForm) {
			(function bindFilterListeners() {
				var fields = filterForm.querySelectorAll('[name="include_no_response"],[name="include_tentative"],[name="include_previously_contacted"]');
				var i;
				for (i = 0; i < fields.length; i++) {
					fields[i].addEventListener('change', update);
				}
			}());
		}

		update();
	}

	initRequestBuilder();
}());
