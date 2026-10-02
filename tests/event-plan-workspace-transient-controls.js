const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const shellPath = path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js');
const shell = fs.readFileSync(shellPath, 'utf8');

function extractFunction(name) {
  const start = shell.indexOf('function ' + name + '(');
  assert.notEqual(start, -1, 'Missing workspace function: ' + name);
  const brace = shell.indexOf('{', start);
  let depth = 1;
  for (let index = brace + 1; index < shell.length; index += 1) {
    if (shell[index] === '{') depth += 1;
    if (shell[index] === '}') depth -= 1;
    if (depth === 0) return shell.slice(start, index + 1);
  }
  throw new Error('Unable to parse workspace function: ' + name);
}

const factory = new Function(
  [
    'readControlState',
    'controlDirty',
    'isTransientActionControl',
    'sectionDirty',
    'sectionPersistedDirty',
    'sectionTransientDirty',
    'resetSectionBaseline',
    'workflowActionConsumesTransient',
    'workflowActionConsumesPersistedChanges',
    'validateCancellationMarkAction',
  ].map(extractFunction).join('\n') +
  '\nfunction setFlag() {}\n' +
  'function setSectionStatus(section, label, state) { section.status = { label, state }; }\n' +
  'return { controlDirty, sectionDirty, sectionPersistedDirty, sectionTransientDirty, resetSectionBaseline, workflowActionConsumesTransient, workflowActionConsumesPersistedChanges, validateCancellationMarkAction };'
);

const contract = factory();

function input(name, value, initial, transient, type = 'text') {
  return {
    name,
    value,
    defaultValue: initial,
    checked: false,
    defaultChecked: false,
    disabled: false,
    type,
    tagName: 'INPUT',
    options: [],
    dataset: {
      vmsInitialState: initial,
      ...(transient ? { vmsTransientActionControl: '1' } : {}),
    },
    matches() { return false; },
  };
}

const replacementDate = input('vms_reschedule_event_date', '2026-11-14', '', true, 'date');
const refundConfirmation = input('vms_cancel_auto_refund_confirmed', '1', '0', true, 'hidden');
const policy = input('vms_cancel_policy', 'stop_sales', 'status_only', false);
const controls = [replacementDate, refundConfirmation, policy];
const body = { querySelectorAll() { return controls; } };
const section = {
  dataset: { sectionKey: 'cancellation' },
  querySelectorAll() { return controls; },
};

assert.equal(contract.sectionTransientDirty(body), true, 'Replacement/refund action intent must be dirty.');
assert.equal(contract.sectionPersistedDirty(body), true, 'Changed persisted cancellation settings must be dirty.');

const acknowledgment = input('vms_pay_override_ack', '1', '', false, 'checkbox');
acknowledgment.checked = true;
acknowledgment.dataset.vmsIgnoreSectionDirty = '1';
const acknowledgmentBody = { querySelectorAll() { return [acknowledgment]; } };
assert.equal(contract.sectionPersistedDirty(acknowledgmentBody), false, 'Acknowledgment must be ignored by persisted dirty detection.');
assert.equal(contract.sectionTransientDirty(acknowledgmentBody), false, 'Acknowledgment must be ignored by transient dirty detection.');

contract.resetSectionBaseline(section, true);
assert.equal(contract.sectionPersistedDirty(body), false, 'Successful ordinary save must reset persisted cancellation fields.');
assert.equal(contract.sectionTransientDirty(body), true, 'Successful ordinary save must not reset replacement/refund action intent.');
assert.equal(contract.sectionDirty(body), true, 'Cancellation section must remain dirty while action intent is pending.');

assert.equal(
  contract.workflowActionConsumesTransient({ value: 'mark_cancelled' }, section),
  true,
  'Guarded Mark Cancelled submission must consume transient intent.'
);
assert.equal(
  contract.workflowActionConsumesPersistedChanges({ value: 'mark_cancelled' }, section),
  true,
  'Mark Cancelled must consume the current persisted Cancellation inputs in the same native submit.'
);
assert.equal(
  contract.workflowActionConsumesPersistedChanges({ value: 'create_rescheduled_draft' }, section),
  false,
  'Other Cancellation actions retain the saved-state guard.'
);

const reason = {
  value: '',
  attributes: {},
  focused: false,
  setAttribute(name, value) { this.attributes[name] = value; },
  removeAttribute(name) { delete this.attributes[name]; },
  focus() { this.focused = true; },
};
const feedback = { textContent: '' };
const markSection = {
  dataset: { sectionKey: 'cancellation' },
  querySelector(selector) {
    if (selector === '#vms_cancel_reason_code') return reason;
    if (selector === '[data-vms-section-feedback]') return feedback;
    return null;
  },
};
assert.equal(
  contract.validateCancellationMarkAction({ value: 'mark_cancelled' }, markSection),
  false,
  'Mark Cancelled must stop with explicit feedback when the required reason is missing.'
);
assert.equal(reason.attributes['aria-invalid'], 'true', 'Missing cancellation reason is marked invalid.');
assert.equal(reason.focused, true, 'Missing cancellation reason receives focus.');
assert.match(feedback.textContent, /Choose a cancellation reason/, 'Missing reason receives actionable inline guidance.');
reason.value = 'weather';
assert.equal(
  contract.validateCancellationMarkAction({ value: 'mark_cancelled' }, markSection),
  true,
  'A selected reason allows the atomic Mark Cancelled submit.'
);
assert.equal(reason.attributes['aria-invalid'], undefined, 'Valid cancellation reason clears invalid state.');
assert.equal(
  contract.workflowActionConsumesTransient({ value: 'create_rescheduled_draft' }, section),
  true,
  'Guarded reschedule submission must consume the replacement date.'
);
assert.equal(
  contract.workflowActionConsumesTransient({ value: 'publish_now' }, section),
  false,
  'Unrelated workflow actions must not consume cancellation intent.'
);

replacementDate.value = replacementDate.dataset.vmsInitialState;
refundConfirmation.value = refundConfirmation.dataset.vmsInitialState;
assert.equal(contract.sectionDirty(body), false, 'Discard/revert must clear transient cancellation intent.');

const transientGuard = shell.indexOf("if (sectionTransientDirty(section.querySelector('.vms-collapsible-body'))) {");
const nextNavigation = shell.indexOf('await openAndFocusDestination(target, true);', transientGuard);
assert.ok(transientGuard >= 0 && nextNavigation > transientGuard, 'Save & Continue must test transient intent before navigation.');
assert.ok(shell.includes('window.location.reload();'), 'Discard Changes must reload authoritative saved state.');
assert.match(shell, /dataset\.vmsIgnoreSectionDirty === '1'/, 'The shell uses a narrow section-dirty exclusion without reclassifying the control as transient.');
assert.match(shell, /var saveButton = key === 'cancellation'\s*\? ''/, 'Cancellation omits the separate generic Save Changes action.');
assert.match(shell, /workflowActionConsumesPersistedChanges\(workflowSubmit, activeSection\)/, 'The dirty-section click guard permits the atomic Mark Cancelled action.');
assert.match(shell, /!workflowActionConsumesPersistedChanges\(submitter, activeSection\)/, 'The submit guard preserves the same atomic Cancellation exception.');
assert.match(shell, /handleCancellationMarkSubmit\(event, submitter\)/, 'Mark Cancelled acquires the shared transition lock before native submission.');
assert.match(shell, /Discard Changes<\/button>/, 'Cancellation retains Discard Changes.');

console.log('event plan workspace transient controls: PASS');
