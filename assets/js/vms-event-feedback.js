(function () {
	'use strict';

	function resetField(field) {
		if (!field || field.disabled) {
			return;
		}
		if (field.tagName === 'SELECT') {
			field.selectedIndex = 0;
			return;
		}
		if (field.type === 'checkbox' || field.type === 'radio') {
			field.checked = false;
			return;
		}
		if (field.type !== 'hidden') {
			field.value = '';
		}
	}

	function setConditionalBlockState(block, enabled) {
		if (!block) {
			return;
		}
		block.hidden = !enabled;
		block.setAttribute('aria-hidden', enabled ? 'false' : 'true');
		if (!enabled) {
			Array.prototype.forEach.call(block.querySelectorAll('details'), function (detail) {
				detail.open = false;
			});
		}
		Array.prototype.forEach.call(block.querySelectorAll('input, select, textarea, button'), function (field) {
			if (field.type === 'hidden') {
				return;
			}
			if (!enabled) {
				resetField(field);
			}
			field.disabled = !enabled;
		});
	}

	function syncWebsiteSection(form) {
		var section = form.querySelector('[data-vms-feedback-role="website"]');
		if (!section) {
			return;
		}
		var control = section.querySelector('select[name="website[website_used]"]');
		var block = section.querySelector('[data-vms-feedback-website-details="1"]');
		var enabled = !!(control && control.value && control.value !== 'did_not_use');
		setConditionalBlockState(block, enabled);
	}

	function syncVendorSections(form) {
		Array.prototype.forEach.call(form.querySelectorAll('[data-vms-feedback-role="secondary-vendor"]'), function (section) {
			var control = section.querySelector('select[name$="[did_order]"]');
			var block = section.querySelector('[data-vms-feedback-vendor-details="1"]');
			var enabled = !!(control && control.value === 'yes');
			setConditionalBlockState(block, enabled);
		});
	}

	function syncConditionalBlocks(form) {
		if (!form || !form.classList || !form.classList.contains('vms-feedback-form')) {
			return;
		}
		syncWebsiteSection(form);
		syncVendorSections(form);
	}

	function initForms() {
		Array.prototype.forEach.call(document.querySelectorAll('form.vms-feedback-form'), syncConditionalBlocks);
	}

	function handleChange(event) {
		var target = event.target;
		var form = target && target.form;
		var name = target && typeof target.name === 'string' ? target.name : '';
		if (!form || !form.classList || !form.classList.contains('vms-feedback-form')) {
			return;
		}
		if (name === 'website[website_used]' || /\[did_order\]$/.test(name)) {
			syncConditionalBlocks(form);
		}
	}

	function handleSubmit(event) {
		var form = event.target;
		if (!form || !form.classList || !form.classList.contains('vms-feedback-form')) {
			return;
		}
		syncConditionalBlocks(form);
		if (form.dataset.vmsFeedbackSubmitting === '1') {
			event.preventDefault();
			return;
		}
		form.dataset.vmsFeedbackSubmitting = '1';
		var button = form.querySelector('[data-vms-feedback-submit="1"]');
		if (button) {
			button.dataset.vmsOriginalLabel = button.textContent || '';
			button.textContent = button.getAttribute('data-vms-submitting-label') || 'Submitting...';
			button.disabled = true;
			button.setAttribute('aria-disabled', 'true');
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initForms);
	} else {
		initForms();
	}
	document.addEventListener('change', handleChange, true);
	document.addEventListener('submit', handleSubmit, true);
})();
