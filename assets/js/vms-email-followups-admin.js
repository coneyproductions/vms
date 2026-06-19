(function () {
	'use strict';

	function recipientBoxes(form) {
		return Array.prototype.slice.call(form.querySelectorAll('[data-vms-efu-recipient]'));
	}

	function updateSelectedCount(form) {
		var counter = form.querySelector('.vms-efu-selected-count');
		if (!counter) {
			return;
		}
		var boxes = recipientBoxes(form);
		var selected = boxes.filter(function (box) { return box.checked; }).length;
		counter.textContent = selected + ' selected';
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-vms-efu-select]');
		if (!button) {
			return;
		}
		var form = button.closest('form');
		if (!form) {
			return;
		}
		var check = button.getAttribute('data-vms-efu-select') === 'all';
		recipientBoxes(form).forEach(function (box) {
			box.checked = check;
		});
		updateSelectedCount(form);
	});

	document.addEventListener('change', function (event) {
		if (!event.target.matches('[data-vms-efu-recipient]')) {
			return;
		}
		var form = event.target.closest('form');
		if (form) {
			updateSelectedCount(form);
		}
	});

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form.classList.contains('vms-efu-manual-send-form') && !form.classList.contains('vms-efu-batch-continue-form')) {
			return;
		}
		var boxes = recipientBoxes(form);
		if (boxes.length && !boxes.some(function (box) { return box.checked; })) {
			event.preventDefault();
			window.alert('Select at least one recipient before sending.');
			return;
		}
		form.classList.add('vms-efu-is-sending');
		var progress = form.querySelector('.vms-efu-send-progress');
		if (progress) {
			progress.textContent = 'Sending now — leave this tab open until the page returns.';
		}
		Array.prototype.slice.call(form.querySelectorAll('button[type="submit"]')).forEach(function (button) {
			button.disabled = true;
			button.dataset.originalText = button.textContent;
			button.textContent = 'Sending...';
		});
	});

	document.querySelectorAll('.vms-efu-manual-send-form').forEach(updateSelectedCount);
}());
