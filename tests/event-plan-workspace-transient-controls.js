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
  ].map(extractFunction).join('\n') +
  '\nfunction setFlag() {}\n' +
  'return { controlDirty, sectionDirty, sectionPersistedDirty, sectionTransientDirty, resetSectionBaseline, workflowActionConsumesTransient };'
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
const nextNavigation = shell.indexOf('await openSection(target, true);', transientGuard);
assert.ok(transientGuard >= 0 && nextNavigation > transientGuard, 'Save & Continue must test transient intent before navigation.');
assert.ok(shell.includes('window.location.reload();'), 'Discard Changes must reload authoritative saved state.');

console.log('event plan workspace transient controls: PASS');
