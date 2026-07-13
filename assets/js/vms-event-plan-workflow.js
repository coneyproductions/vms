(function () {
  function initBulkCancellationRetryConfirmation() {
    var form = document.getElementById('post');
    var hiddenConfirm;
    var button;

    if (!form) {
      return;
    }

    hiddenConfirm = document.getElementById('vms_cancel_bulk_retry_confirm');
    button = form.querySelector('button[type="submit"][name="vms_event_plan_action"][value="retry_cancellation_all"]');

    if (!button || !hiddenConfirm) {
      return;
    }
    if (button.dataset.vmsWorkflowRetryBound === '1') {
      return;
    }

    button.dataset.vmsWorkflowRetryBound = '1';
    button.addEventListener('click', function (event) {
      var requiresRefundConfirm;
      var ok;

      hiddenConfirm.value = '0';
      requiresRefundConfirm = button.getAttribute('data-vms-requires-refund-confirm') === '1';
      if (!requiresRefundConfirm) {
        return;
      }

      ok = window.confirm('Refund execution is currently failed or blocked. Retrying all steps may attempt refund execution again. Continue?');
      if (!ok) {
        event.preventDefault();
        event.stopPropagation();
        return;
      }

      hiddenConfirm.value = '1';
    });
  }

  function initLiveRefundConfirmation() {
    var button = document.getElementById('vms_run_live_refunds_now_button');

    if (!button) {
      return;
    }
    if (button.dataset.vmsWorkflowLiveRefundBound === '1') {
      return;
    }

    button.dataset.vmsWorkflowLiveRefundBound = '1';
    button.addEventListener('click', function (event) {
      var href = button.getAttribute('href') || '';
      var ok;

      if (!href) {
        event.preventDefault();
        window.alert('Unable to start the live refund action because the request link is missing.');
        return;
      }

      ok = window.confirm('Run LIVE refunds now for this already-cancelled event? This does not save the Event Plan. VMS will attempt WooCommerce gateway refunds for remaining eligible ticket lines and queue anything unsafe for manual review.');
      if (!ok) {
        event.preventDefault();
        return;
      }

      button.classList.add('disabled');
      button.setAttribute('aria-disabled', 'true');
      button.style.pointerEvents = 'none';
      window.location.href = href;
      event.preventDefault();
    });
  }

  function initMarkCancelledConfirmation() {
    var form = document.getElementById('post');
    var button;
    var dateField;
    var policyField;
    var autoRefundConfirmField;

    if (!form) {
      return;
    }

    button = form.querySelector('button[type="submit"][name="vms_event_plan_action"][value="mark_cancelled"]');
    dateField = document.getElementById('vms_reschedule_event_date');
    policyField = document.getElementById('vms_cancel_policy');
    autoRefundConfirmField = document.getElementById('vms_cancel_auto_refund_confirmed');

    if (!button || button.disabled) {
      return;
    }
    if (button.dataset.vmsWorkflowMarkCancelledBound === '1') {
      return;
    }

    button.dataset.vmsWorkflowMarkCancelledBound = '1';
    button.addEventListener('click', function (event) {
      var replacementDate;
      var policy;
      var usesAutoRefund;
      var message;
      var ok;

      if (autoRefundConfirmField) {
        autoRefundConfirmField.value = '0';
      }

      replacementDate = dateField ? String(dateField.value || '').trim() : '';
      policy = policyField ? String(policyField.value || '').trim() : '';
      usesAutoRefund = policy === 'stop_sales_auto_refund' || policy === 'stop_sales_auto_refund_remove_attendees';

      message = 'Are you sure you want to mark this event as Cancelled?';
      if (replacementDate !== '') {
        message += ' VMS will also create a linked Draft Event Plan for ' + replacementDate + '.';
      }
      if (usesAutoRefund) {
        message += ' This will attempt LIVE payment refunds for matching ticket orders through WooCommerce. Mixed orders will refund only the cancelled event ticket lines when possible, and anything unsafe will be queued for manual review.';
      }

      ok = window.confirm(message);
      if (ok) {
        if (usesAutoRefund && autoRefundConfirmField) {
          autoRefundConfirmField.value = '1';
        }
        return;
      }

      event.preventDefault();
      event.stopPropagation();
    });
  }

  function initWorkflowControllers() {
    var form = document.getElementById('post');

    if (!form) {
      return false;
    }

    initBulkCancellationRetryConfirmation();
    initLiveRefundConfirmation();
    initMarkCancelledConfirmation();
    return true;
  }

  if (!initWorkflowControllers() && document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWorkflowControllers, { once: true });
  }
})();
