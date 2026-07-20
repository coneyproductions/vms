(function () {
	function initQualificationBuilder(builder) {
		if (!builder || builder.dataset.vmsQualificationInit === '1') {
			return;
		}

		var rowsWrap = builder.querySelector('[data-vms-qualification-rows="1"]');
		var rowTemplate = builder.querySelector('[data-vms-qualification-row-template="1"]');
		var addBtn = builder.querySelector('[data-vms-qualification-add="1"]');
		if (!rowsWrap || !rowTemplate || !addBtn) {
			return;
		}

		builder.dataset.vmsQualificationInit = '1';

		function buildRow(idx) {
			var wrapper = document.createElement('div');
			wrapper.innerHTML = rowTemplate.innerHTML.replace(/__INDEX__/g, String(idx));
			return wrapper.firstElementChild;
		}

		addBtn.addEventListener('click', function () {
			var idx = rowsWrap.querySelectorAll('[data-vms-qualification-row="1"]').length;
			var row = buildRow(idx);
			if (row) {
				rowsWrap.appendChild(row);
			}
		});

		rowsWrap.addEventListener('click', function (event) {
			var btn = event.target.closest('[data-vms-qualification-remove="1"]');
			if (!btn) {
				return;
			}

			event.preventDefault();
			var rows = rowsWrap.querySelectorAll('[data-vms-qualification-row="1"]');
			if (rows.length <= 1) {
				rows[0].querySelectorAll('input').forEach(function (input) {
					input.value = '';
				});
				var select = rows[0].querySelector('select');
				if (select) {
					select.value = 'warn';
				}
				return;
			}

			var row = btn.closest('[data-vms-qualification-row="1"]');
			if (row) {
				row.remove();
			}
		});
	}

	function toggleNodes(nodes, show) {
		nodes.forEach(function (node) {
			node.classList.toggle('vms-tpl-hidden', !show);
			node.querySelectorAll('input, select, textarea').forEach(function (field) {
				field.disabled = !show;
			});
		});
	}

	function templateState(row) {
		var mode = (row.querySelector('[data-vms-tpl-time-mode-input="1"]') || {}).value || 'absolute';
		var durationField = row.querySelector('[data-vms-tpl-duration-input="1"]');
		var duration = durationField ? parseInt(durationField.value || '0', 10) : 0;
		if (!Number.isFinite(duration)) {
			duration = 0;
		}
		var roleField = row.querySelector('[data-vms-tpl-role-input="1"]');
		var roleId = roleField ? parseInt(roleField.value || '0', 10) : 0;
		if (!Number.isFinite(roleId)) {
			roleId = 0;
		}
		var needField = row.querySelector('[data-vms-tpl-headcount-input="1"]');
		var need = needField ? parseInt(needField.value || '0', 10) : 0;
		if (!Number.isFinite(need)) {
			need = 0;
		}
		var shiftStart = row.querySelector('[data-vms-tpl-shift-start-input="1"]');
		var shiftEnd = row.querySelector('[data-vms-tpl-shift-end-input="1"]');
		return {
			timeMode: mode === 'relative' ? 'relative' : 'absolute',
			duration: duration,
			roleInUse: roleId > 0 && need > 0,
			shiftStart: shiftStart ? shiftStart.value : '',
			shiftEnd: shiftEnd ? shiftEnd.value : ''
		};
	}

	function syncTimingVisibility(row, state) {
		var absoluteFields = Array.prototype.slice.call(row.querySelectorAll('[data-vms-tpl-absolute-field="1"]'));
		var relativeFields = Array.prototype.slice.call(row.querySelectorAll('[data-vms-tpl-relative-field="1"]'));
		var endFields = Array.prototype.slice.call(row.querySelectorAll('[data-vms-tpl-end-field="1"]'));
		var showAbsolute = state.timeMode === 'absolute';
		var showRelative = !showAbsolute;

		toggleNodes(absoluteFields, showAbsolute);
		toggleNodes(relativeFields, showRelative);

		if (state.duration > 0) {
			toggleNodes(
				endFields.filter(function (node) {
					return showAbsolute
						? node.hasAttribute('data-vms-tpl-absolute-field')
						: node.hasAttribute('data-vms-tpl-relative-field');
				}),
				false
			);
		}
	}

	function renderTemplateRow(row) {
		var state = templateState(row);
		syncTimingVisibility(row, state);
		var absoluteWarning = row.querySelector('[data-vms-tpl-absolute-warning]');
		if (!absoluteWarning) {
			return;
		}

		var showWarning = state.roleInUse
			&& state.timeMode === 'absolute'
			&& (state.shiftStart === '' || (state.shiftEnd === '' && state.duration <= 0));
		absoluteWarning.classList.toggle('vms-hidden', !showWarning);
	}

	function initTemplateRow(row) {
		if (!row || row.dataset.vmsTplInit === '1') {
			return;
		}

		row.dataset.vmsTplInit = '1';
		row.querySelectorAll('input, select').forEach(function (field) {
			field.addEventListener('input', function () {
				renderTemplateRow(row);
			});
			field.addEventListener('change', function () {
				renderTemplateRow(row);
			});
		});
		renderTemplateRow(row);
	}

	function initTemplatesPage() {
		var slotsWrap = document.getElementById('vms-tpl-slots');
		var addBtn = document.getElementById('vms-tpl-add-row');
		var rowTemplate = document.getElementById('vms-tpl-slot-row-template');
		if (!slotsWrap || !addBtn || !rowTemplate) {
			return;
		}

		function rowCount() {
			return slotsWrap.querySelectorAll('[data-vms-tpl-slot-row="1"]').length;
		}

		function buildRow(idx) {
			var wrapper = document.createElement('div');
			wrapper.innerHTML = rowTemplate.innerHTML.replace(/__INDEX__/g, String(idx));
			var row = wrapper.firstElementChild;
			initTemplateRow(row);
			return row;
		}

		slotsWrap.querySelectorAll('[data-vms-tpl-slot-row="1"]').forEach(initTemplateRow);

		addBtn.addEventListener('click', function () {
			slotsWrap.appendChild(buildRow(rowCount()));
		});

		slotsWrap.addEventListener('click', function (event) {
			var btn = event.target.closest('.vms-tpl-remove-row');
			if (!btn) {
				return;
			}

			event.preventDefault();
			var rows = slotsWrap.querySelectorAll('[data-vms-tpl-slot-row="1"]');
			if (rows.length <= 1) {
				return;
			}

			var row = btn.closest('[data-vms-tpl-slot-row="1"]');
			if (row) {
				row.remove();
			}
		});
	}

	function init() {
		document.querySelectorAll('[data-vms-qualification-builder="1"]').forEach(initQualificationBuilder);
		initTemplatesPage();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, { once: true });
	} else {
		init();
	}
})();
