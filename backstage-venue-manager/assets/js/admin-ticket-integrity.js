(function () {
	'use strict';

	function focusRow(target) {
		if (!(target instanceof HTMLElement)) {
			return;
		}

		target.classList.add('vms-ticket-integrity__focused-row');

		var details = target.querySelector('details');
		if (details instanceof HTMLDetailsElement) {
			details.open = true;
		}

		window.setTimeout(function () {
			target.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}, 80);
	}

	function findTargetRow() {
		var params = new URLSearchParams(window.location.search);
		var planId = params.get('event');
		if (planId) {
			return document.getElementById('vms-ticket-integrity-event-' + planId);
		}

		if (window.location.hash) {
			try {
				return document.querySelector(window.location.hash);
			} catch (error) {
				return null;
			}
		}

		return null;
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		var config = window.BVMGR_TICKET_INTEGRITY_ADMIN || {};
		var message = 'Continue?';

		if (form.matches('[data-vms-ticket-integrity-confirm="rebuild"]')) {
			message = typeof config.confirmRebuild === 'string' && config.confirmRebuild
				? config.confirmRebuild
				: 'Continue?';
		} else if (form.matches('[data-vms-ticket-integrity-confirm="cleanup-duplicates"]')) {
			message = typeof config.confirmCleanupDuplicates === 'string' && config.confirmCleanupDuplicates
				? config.confirmCleanupDuplicates
				: 'Continue?';
		} else {
			return;
		}

		if (!window.confirm(message)) {
			event.preventDefault();
		}
	});

	document.addEventListener('DOMContentLoaded', function () {
		focusRow(findTargetRow());
	});
}());
