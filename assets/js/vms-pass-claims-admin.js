(function () {
  var batchForm = document.querySelector('[data-vms-pass-batch-form]');
  if (batchForm) {
    var validity = batchForm.querySelector('[name="validity_type"]');
    var valueType = batchForm.querySelector('[name="value_type"]');
    var commit = batchForm.querySelector('[data-vms-pass-commit]');
    var stale = batchForm.querySelector('[data-vms-pass-stale]');

    var updateConditionalFields = function () {
      var validityValue = validity ? String(validity.value || '') : '';
      batchForm.querySelectorAll('[data-vms-validity-fields]').forEach(function (wrapper) {
        var applies = String(wrapper.getAttribute('data-vms-validity-fields') || '').split(/\s+/).indexOf(validityValue) !== -1;
        wrapper.hidden = !applies;
        wrapper.querySelectorAll('input, select, textarea').forEach(function (field) {
          field.required = applies && (field.name === 'single_event_plan_id' || field.name === 'start_date' || field.name === 'end_date');
        });
      });
      var amount = batchForm.querySelector('[data-vms-discount-amount]');
      if (amount) {
        amount.hidden = valueType && String(valueType.value || '') === 'free';
        var amountInput = amount.querySelector('input');
        if (amountInput) {
          amountInput.required = !amount.hidden;
        }
      }
    };

    updateConditionalFields();
    var reviewedSnapshot = new URLSearchParams(new FormData(batchForm)).toString();
    var updateStaleState = function () {
      updateConditionalFields();
      if (!commit) {
        return;
      }
      var current = new URLSearchParams(new FormData(batchForm)).toString();
      var changed = current !== reviewedSnapshot;
      commit.disabled = changed;
      if (stale) {
        stale.hidden = !changed;
      }
    };
    batchForm.addEventListener('input', updateStaleState);
    batchForm.addEventListener('change', updateStaleState);
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target && event.target.closest ? event.target.closest('[data-vms-copy]') : null;
    if (!trigger) {
      return;
    }

    var selector = String(trigger.getAttribute('data-vms-copy') || '');
    if (!selector) {
      return;
    }

    var input = document.querySelector(selector);
    if (!input) {
      return;
    }

    var text = String(input.value || '');
    if (!text) {
      return;
    }

    var original = String(trigger.textContent || 'Copy');

    var markDone = function () {
      trigger.textContent = 'Copied';
      window.setTimeout(function () {
        trigger.textContent = original;
      }, 1400);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(markDone).catch(function () {
        if (input.select) {
          input.select();
          document.execCommand('copy');
          markDone();
        }
      });
      return;
    }

    if (input.select) {
      input.select();
      document.execCommand('copy');
      markDone();
    }
  });
})();
