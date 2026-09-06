(() => {
    'use strict';
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-staffing-action]');
        if (!button || button.disabled) return;
        const panel = button.closest('.bvm-staffing-lifecycle');
        const row = button.closest('[data-staffing-assignment]');
        const feedback = panel.querySelector('[data-staffing-feedback]');
        const reasonInput = row.querySelector('[data-staffing-reason-input]');
        const reason = reasonInput ? reasonInput.value.trim() : '';
        if (button.dataset.staffingReason === '1' && !reason) {
            feedback.textContent = button.dataset.staffingReasonLabel;
            if (reasonInput) reasonInput.focus();
            return;
        }
        const data = new URLSearchParams({
            action: `bvmgr_staffing_${button.dataset.staffingContext}_transition`,
            assignment_id: row.dataset.staffingAssignment,
            event_plan_id: button.dataset.staffingPlan,
            revision: button.dataset.staffingRevision,
            target: button.dataset.staffingAction,
            nonce: button.dataset.staffingNonce,
            operation_id: button.dataset.staffingOperation,
            reason
        });
        feedback.textContent = panel.dataset.staffingSaving;
        const buttons = [...row.querySelectorAll('button')];
        buttons.forEach((item) => { item.disabled = true; });
        try {
            const response = await fetch(panel.dataset.staffingUrl, { method: 'POST', credentials: 'same-origin', body: data });
            const payload = await response.json();
            feedback.textContent = payload.data.message;
            if (payload.success) {
                // Preserve unsaved editor fields. A reload refreshes every derived
                // summary; do not replace them with a stale checkbox submission.
                if (reasonInput) reasonInput.disabled = true;
                row.querySelector('[data-staffing-state]').textContent = payload.data.assignment.label;
                row.querySelector('[data-staffing-state]').className = `bvm-staffing-state is-${payload.data.assignment.status}`;
            } else {
                buttons.forEach((item) => { item.disabled = false; });
            }
        } catch (error) {
            feedback.textContent = panel.dataset.staffingNetworkError;
            buttons.forEach((item) => { item.disabled = false; });
        }
    });
})();
