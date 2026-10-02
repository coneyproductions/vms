const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const shell = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js'), 'utf8');
const compensation = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-compensation.js'), 'utf8');
const eventPlans = fs.readFileSync(path.resolve(__dirname, '../includes/cpt/event-plans.php'), 'utf8');
const helpers = fs.readFileSync(path.resolve(__dirname, '../includes/helpers.php'), 'utf8');
const compensationPartial = fs.readFileSync(path.resolve(__dirname, '../includes/cpt/event-plans/partials/compensation.php'), 'utf8');
const compensationAckPartial = fs.readFileSync(path.resolve(__dirname, '../includes/cpt/event-plans/partials/comp-ack.php'), 'utf8');
const adminUiAssets = fs.readFileSync(path.resolve(__dirname, '../includes/admin-ui/assets.php'), 'utf8');
const metaKeys = fs.readFileSync(path.resolve(__dirname, '../includes/core/registry/meta-keys.php'), 'utf8');
const keysMap = fs.readFileSync(path.resolve(__dirname, '../includes/core/registry/vms-keys-map.php'), 'utf8');

function extractFunctionFrom(source, name) {
  const start = source.indexOf('function ' + name + '(');
  assert.notEqual(start, -1, 'Missing function: ' + name);
  const brace = source.indexOf('{', start);
  let depth = 1;
  for (let index = brace + 1; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}') depth -= 1;
    if (depth === 0) return source.slice(start, index + 1);
  }
  throw new Error('Unable to parse function: ' + name);
}

function extractFunction(name) {
  return extractFunctionFrom(shell, name);
}

function classList(initial = []) {
  const values = new Set(initial);
  return {
    toggle(name, force) {
      if (force) values.add(name); else values.delete(name);
    },
    contains(name) { return values.has(name); },
    values,
  };
}

function button(action, initialClasses = ['button']) {
  return {
    dataset: { vmsSectionAction: action },
    disabled: false,
    textContent: '',
    classList: classList(initialClasses),
    attributes: {},
    setAttribute(name, value) { this.attributes[name] = value; },
    getAttribute(name) { return this.attributes[name] || null; },
  };
}

function fixture(lockState, withLock = true) {
  const save = button('save', ['button', 'button-secondary']);
  const next = button('next', ['button', 'button-primary']);
  const discard = button('discard', ['button']);
  const acknowledgment = { checked: false, disabled: false, dataset: { vmsIgnoreSectionDirty: '1' } };
  const lock = withLock ? button('', ['button', 'button-primary']) : null;
  if (lock) {
    lock.name = 'vms_event_plan_action';
    lock.value = 'lock_draft_pay';
    lock.dataset.vmsServerDisabled = '0';
    lock.dataset.vmsAckDisabled = '0';
    lock.closest = () => section;
  }
  const guidance = { textContent: 'Authoritative saved guidance.', dataset: {} };
  const actions = {
    querySelector(selector) {
      if (selector === '[data-vms-section-action="save"]') return save;
      if (selector === '[data-vms-section-action="next"]') return next;
      if (selector === '[data-vms-section-action="discard"]') return discard;
      return null;
    },
  };
  const lockRoot = {
    dataset: { vmsLockState: lockState },
    querySelector(selector) {
      if (selector === '[data-vms-lock-pay-submit]') return lock;
      if (selector === '[data-vms-lock-pay-guidance]') return guidance;
      return null;
    },
  };
  const feedback = { textContent: '' };
  const body = {
    querySelector(selector) {
      if (selector === '.vms-ep-section-actions') return actions;
      if (selector === '[data-vms-lock-pay-actions]') return lockRoot;
      if (selector === '[data-vms-ignore-section-dirty="1"]') return acknowledgment;
      return null;
    },
  };
  const section = {
    dataset: { sectionKey: 'compensation' },
    querySelector(selector) {
      if (selector === '.vms-collapsible-body') return body;
      if (selector === '[data-vms-section-feedback]') return feedback;
      return null;
    },
  };
  return { section, body, save, next, discard, acknowledgment, lock, guidance, feedback };
}

function controller(initialDirty = false) {
  let dirty = initialDirty;
  let transitionInFlight = false;
  let transitionCalls = 0;
  let status = null;
  const form = { querySelector: () => null };
  const factory = new Function(
    'form',
    'sectionPersistedDirty',
    'setSectionStatus',
    'externalBeginTransition',
    'var transitionInFlight = false;\n' +
      'var suppressBeforeUnload = false;\n' +
      extractFunction('setCompensationButtonPrimary') + '\n' +
      extractFunction('updateCompensationActionState') + '\n' +
      'function beginTransition(section, target, presentation) {' +
        'if (transitionInFlight) return false;' +
        'transitionInFlight = true;' +
        'externalBeginTransition(section, target, presentation);' +
        'return true;' +
      '}\n' +
      extractFunction('blockEventDuringTransition') + '\n' +
      extractFunction('handleCompensationLockSubmit') + '\n' +
      'return {' +
        'updateCompensationActionState,' +
        'handleCompensationLockSubmit,' +
        'end: function () { transitionInFlight = false; },' +
        'inFlight: function () { return transitionInFlight; },' +
        'suppressed: function () { return suppressBeforeUnload; }' +
      '};'
  );
  const api = factory(
    form,
    () => dirty,
    (section, label, state) => { status = [label, state]; },
    () => { transitionCalls += 1; }
  );
  return {
    api,
    setDirty(value) { dirty = value; },
    transitionCalls() { return transitionCalls; },
    status() { return status; },
  };
}

function eventFor(submitter) {
  return {
    submitter,
    prevented: 0,
    stopped: 0,
    preventDefault() { this.prevented += 1; },
    stopPropagation() { this.stopped += 1; },
  };
}

const comparisonContract = new Function(
  extractFunctionFrom(compensation, 'differs') + '\n' +
  extractFunctionFrom(compensation, 'compensationLockValidationMessage') + '\n' +
  'return { differs, compensationLockValidationMessage };'
)();
const dirtyControlContract = new Function(
  extractFunction('controlDirty') + '\n' +
  extractFunction('isTransientActionControl') + '\n' +
  extractFunction('sectionDirty') + '\n' +
  extractFunction('sectionPersistedDirty') + '\n' +
  extractFunction('sectionTransientDirty') + '\n' +
  'return { controlDirty, sectionPersistedDirty, sectionTransientDirty };'
)();
const comparablePay = {
  structure: 'flat_fee',
  flat: 500,
  split: null,
  attendance_bonus_mode: '',
  attendance_bonus_start_count: null,
  attendance_bonus_step_size: null,
  attendance_bonus_step_bonus: null,
  attendance_bonus_per_ticket_rate: null,
  attendance_bonus_max_bonus: null,
  commission_percent: 0,
  commission_mode: 'artist_fee',
};

assert.equal(
  comparisonContract.compensationLockValidationMessage({ ...comparablePay, flat: 0 }),
  '',
  'A zero Flat Fee is valid for Lock.'
);
assert.equal(
  comparisonContract.compensationLockValidationMessage({ ...comparablePay, flat: -1 }),
  'Flat Fee Amount must be $0 or greater before locking Draft Pay.',
  'A negative Flat Fee remains invalid for Lock.'
);
assert.equal(
  comparisonContract.compensationLockValidationMessage({ ...comparablePay, structure: 'flat_fee_door_split', flat: 0, split: 20 }),
  '',
  'Flat Fee + Door Split permits a zero Flat Fee when the split is valid.'
);
assert.equal(
  comparisonContract.differs(
    { ...comparablePay, commission_percent: 0, commission_mode: 'gross' },
    { ...comparablePay, commission_percent: null, commission_mode: 'artist_fee' }
  ),
  false,
  'Blank/zero Agent Fee ignores Agent Fee Basis differences.'
);
assert.equal(
  comparisonContract.differs(
    { ...comparablePay, commission_percent: 10, commission_mode: 'gross' },
    { ...comparablePay, commission_percent: 10, commission_mode: 'artist_fee' }
  ),
  true,
  'A non-zero Agent Fee keeps Agent Fee Basis meaningful.'
);
assert.equal(
  comparisonContract.differs(
    { ...comparablePay, commission_percent: 10, commission_mode: 'gross' },
    { ...comparablePay, commission_percent: 10, commission_mode: 'gross' }
  ),
  false,
  'Applying matching vendor defaults removes the meaningful drift condition.'
);
assert.equal(
  comparisonContract.differs(
    { ...comparablePay, flat: 100 },
    { ...comparablePay, flat: null }
  ),
  false,
  'An unconfigured default amount does not differ from a valid Draft Pay amount.'
);
assert.equal(
  comparisonContract.compensationLockValidationMessage({ ...comparablePay, structure: 'flat_fee_door_split', flat: 500, split: 0 }),
  'Door Split must be between 1% and 100% for Flat Fee + Door Split. Enter a split or choose Flat Fee.',
  'A zero split blocks Lock with the required inline reason.'
);
assert.equal(
  dirtyControlContract.controlDirty({ disabled: false, dataset: { vmsIgnoreSectionDirty: '1' } }),
  false,
  'The acknowledgment action control does not mark Compensation dirty.'
);
const ignoredAcknowledgment = {
  disabled: false,
  checked: true,
  defaultChecked: false,
  type: 'checkbox',
  tagName: 'INPUT',
  dataset: { vmsIgnoreSectionDirty: '1' },
  matches(selector) { return selector === 'input[type="checkbox"],input[type="radio"]'; },
};
const acknowledgmentBody = { querySelectorAll() { return [ignoredAcknowledgment]; } };
assert.equal(dirtyControlContract.sectionPersistedDirty(acknowledgmentBody), false, 'Checked acknowledgment is ignored by persisted section dirty detection.');
assert.equal(dirtyControlContract.sectionTransientDirty(acknowledgmentBody), false, 'Checked acknowledgment is ignored by transient section dirty detection.');

const dirtyFixture = fixture('available');
const dirtyController = controller(true);
dirtyController.api.updateCompensationActionState(dirtyFixture.section);
assert.equal(dirtyFixture.save.textContent, 'Save Compensation', 'Dirty Compensation exposes an explicit save action.');
assert.equal(dirtyFixture.save.classList.contains('button-primary'), true, 'Save Compensation is the sole primary action while dirty.');
assert.equal(dirtyFixture.next.classList.contains('button-primary'), false, 'Save & Continue does not compete as a primary action while dirty.');
assert.equal(dirtyFixture.lock.classList.contains('button-primary'), false, 'Lock does not compete as a primary action while dirty.');
assert.equal(dirtyFixture.lock.disabled, true, 'Lock is disabled against unsaved Draft Pay.');
assert.equal(dirtyFixture.acknowledgment.disabled, true, 'Acknowledgment is disabled while persisted Compensation edits are dirty.');
assert.equal(dirtyFixture.discard.disabled, false, 'Discard Changes remains available while Compensation is dirty.');
assert.match(dirtyFixture.guidance.textContent, /Save Compensation before locking Draft Pay/, 'Dirty lock guidance explains the required sequence.');

const staleLockEvent = eventFor(dirtyFixture.lock);
assert.equal(dirtyController.api.handleCompensationLockSubmit(staleLockEvent, dirtyFixture.lock), true, 'A stale lock submission is consumed.');
assert.equal(staleLockEvent.prevented, 1, 'A stale lock submission cannot reach the legacy full-form handler.');
assert.equal(dirtyController.transitionCalls(), 0, 'A stale lock does not start a lock transition.');
assert.equal(dirtyFixture.feedback.textContent, 'Save Compensation before locking Draft Pay.', 'The operator receives local actionable feedback.');

dirtyController.setDirty(false);
dirtyController.api.updateCompensationActionState(dirtyFixture.section);
assert.equal(dirtyFixture.save.disabled, true, 'Save Compensation is unavailable when there is nothing to save.');
assert.equal(dirtyFixture.discard.disabled, true, 'Discard Changes is unavailable when Compensation is clean.');
assert.equal(dirtyFixture.acknowledgment.disabled, false, 'Acknowledgment becomes available after Compensation is saved.');
assert.equal(dirtyFixture.lock.disabled, false, 'Lock becomes available after the Compensation baseline is saved.');
assert.equal(dirtyFixture.lock.classList.contains('button-primary'), true, 'Lock is the primary finalization action for clean unlocked Draft Pay.');
assert.equal(dirtyFixture.next.textContent, 'Continue Without Locking', 'Continue truthfully identifies that Lock is optional.');
assert.equal(dirtyFixture.next.classList.contains('button-primary'), false, 'Optional Continue remains secondary while Lock is the suggested next action.');

dirtyFixture.acknowledgment.checked = true;
dirtyController.api.updateCompensationActionState(dirtyFixture.section);
assert.equal(dirtyFixture.next.disabled, false, 'Checked acknowledgment does not block Continue Without Locking.');
assert.equal(dirtyFixture.next.textContent, 'Continue Without Locking', 'Checked acknowledgment preserves the unlocked continuation contract.');
const firstLockEvent = eventFor(dirtyFixture.lock);
assert.equal(dirtyController.api.handleCompensationLockSubmit(firstLockEvent, dirtyFixture.lock), false, 'A clean eligible Lock is admitted to the native server action.');
assert.equal(dirtyFixture.lock.disabled, false, 'The native Lock submitter remains a successful form control while submission begins.');
assert.equal(`${dirtyFixture.lock.name}=${dirtyFixture.lock.value}`, 'vms_event_plan_action=lock_draft_pay', 'The native Lock action name/value remains serializable in the POST.');
assert.equal(dirtyFixture.save.disabled, true, 'Checking acknowledgment does not require another Compensation save before Lock.');
assert.equal(dirtyController.transitionCalls(), 1, 'Lock acquires the shared transition exactly once.');
assert.equal(dirtyController.api.inFlight(), true, 'The transition remains locked while the native request is pending.');
assert.deepEqual(dirtyController.status(), ['Locking…', 'saving'], 'Lock exposes useful in-progress status.');
assert.equal(dirtyController.api.suppressed(), true, 'The accepted native navigation is not mistaken for unsaved-page abandonment.');

const duplicateLockEvent = eventFor(dirtyFixture.lock);
assert.equal(dirtyController.api.handleCompensationLockSubmit(duplicateLockEvent, dirtyFixture.lock), true, 'A duplicate Lock is blocked.');
assert.equal(duplicateLockEvent.prevented, 1, 'The duplicate native submit is prevented.');
assert.equal(dirtyController.transitionCalls(), 1, 'Duplicate Lock does not execute again.');

dirtyController.api.end();
dirtyFixture.lock.disabled = false;
dirtyFixture.lock.setAttribute('aria-disabled', 'false');
assert.equal(dirtyController.api.handleCompensationLockSubmit(eventFor(dirtyFixture.lock), dirtyFixture.lock), false, 'After a failed/reloaded lock lifecycle, the unlocked action can be retried.');

const lockedFixture = fixture('locked', false);
const lockedController = controller(false);
lockedController.api.updateCompensationActionState(lockedFixture.section);
lockedFixture.acknowledgment.checked = true;
lockedController.api.updateCompensationActionState(lockedFixture.section);
assert.equal(lockedFixture.next.textContent, 'Continue', 'A current locked snapshot advances with a simple Continue action.');
assert.equal(lockedFixture.next.disabled, false, 'Checked acknowledgment does not block Continue after Lock.');
assert.equal(lockedFixture.next.classList.contains('button-primary'), true, 'Continue is the sole primary action once Draft Pay is locked.');
assert.equal(lockedFixture.save.disabled, true, 'A no-op save does not compete in the locked state.');
assert.equal(lockedFixture.discard.disabled, true, 'Discard Changes stays disabled in the clean locked state.');

const invalidSplitFixture = fixture('available');
invalidSplitFixture.lock.dataset.vmsValidationDisabled = '1';
invalidSplitFixture.lock.dataset.vmsValidationMessage = 'Door Split must be between 1% and 100% for Flat Fee + Door Split. Enter a split or choose Flat Fee.';
controller(false).api.updateCompensationActionState(invalidSplitFixture.section);
assert.equal(invalidSplitFixture.lock.disabled, true, 'Client-visible compensation validity disables Lock.');
assert.equal(invalidSplitFixture.guidance.textContent, invalidSplitFixture.lock.dataset.vmsValidationMessage, 'Client-visible compensation validity supplies inline Lock guidance.');

assert.match(eventPlans, /in_array\(\$scope, array\('basics', 'schedule', 'compensation'\), true\)/, 'Compensation saves request authoritative lock-action HTML.');
assert.match(eventPlans, /data-vms-lock-state="<\?php echo esc_attr\(\$action_state\); \?>"/, 'Server-rendered lock state drives the browser hierarchy.');
assert.match(eventPlans, /Draft Pay locked/, 'A current snapshot is represented as status rather than another active Lock button.');
assert.match(eventPlans, /\$state\['out_of_sync'\]/, 'Saved Draft Pay changes distinguish Lock from re-Lock state.');
assert.match(eventPlans, /id="vms-lock-pay-status"[\s\S]*data-vms-lock-pay-actions/, 'The rendered Lock Pay status area has a stable landing anchor.');
assert.match(eventPlans, /update_post_meta\(\$post_id, '_vms_comp_snapshot', \$snapshot\);[\s\S]*update_post_meta\(\$post_id, '_vms_admin_scroll_to', 'vms-lock-pay-status'\);/, 'A successful Lock reload targets the rendered Lock Pay status anchor.');
assert.match(eventPlans, /in_array\(\$structure, array\('flat_fee', 'flat_fee_door_split'\), true\) && \(\$flat === null \|\| \$flat < 0\)/, 'Server Lock validation accepts zero Flat Fee and rejects negative values.');
assert.match(eventPlans, /in_array\(\$comp_structure, array\('flat_fee', 'flat_fee_door_split'\), true\)[\s\S]*\(float\)\$flat_fee_amount < 0/, 'Saved-state validation accepts zero Flat Fee and rejects negative values.');
assert.match(compensationPartial, /vms-ep-card vms-ep-card--blue vms-mt-10/, 'Primary Vendor default drift uses the informational blue card treatment.');
assert.doesNotMatch(compensationPartial, /vms_show_vendor_default_drift_notice[\s\S]{0,300}vms-notice--warning/, 'Primary Vendor default drift is not presented as a warning.');
assert.doesNotMatch(compensationPartial, /This is allowed\./, 'Primary Vendor default drift copy avoids design-conversation wording.');
assert.match(compensationPartial, /Use Primary Vendor default instead/, 'The optional replacement action uses non-required language.');
assert.doesNotMatch(compensationPartial, /id="vms_door_split_percent"[^>]*\/>\s*%/, 'Door Split does not render a duplicate standalone percent suffix.');
assert.match(compensationPartial, /Base Pay \(\$\)/, 'Dynamic Base Pay labels include their currency unit.');
assert.match(compensationPartial, /Flat Fee Amount \(\$\)/, 'Dynamic Flat Fee labels include their currency unit.');
assert.doesNotMatch(compensationPartial, /Agent Fee percentage help/, 'Agent Fee field-level help is removed.');
assert.equal((compensationPartial.match(/Agent Fee help/g) || []).length, 1, 'Only heading-level Agent Fee help remains.');
assert.match(compensationAckPartial, /data-vms-ignore-section-dirty="1"/, 'Acknowledgment uses the narrow dirty-state exclusion marker.');
assert.doesNotMatch(compensationAckPartial, /data-vms-transient-action-control/, 'Acknowledgment is not a transient action control.');
assert.match(helpers, /if \(\$field === 'commission_percent' && !\$actual_commission_active && !\$default_commission_active\)/, 'Server-rendered drift comparison ignores inactive Agent Fee percentages.');
assert.match(helpers, /if \(\$field === 'commission_mode' && \(!\$actual_commission_active && !\$default_commission_active\)\)/, 'Server-rendered Agent Fee Basis remains comparable only while Agent Fee is active.');
assert.match(eventPlans, /\$vms_show_vendor_default_drift_notice = \(\$vms_vendor_default_has_terms && !\$vms_vendor_default_matches_draft\);/, 'The vendor-default card disappears when all meaningful normalized terms match.');
assert.match(eventPlans, /bvmgr_comp_merge_configured_defaults\([\s\S]*bvmgr_get_event_plan_comp_terms\(\$post_id\)/, 'Applying vendor defaults preserves Draft values where defaults are unconfigured.');
assert.doesNotMatch([compensation, compensationPartial, eventPlans, helpers, metaKeys, keysMap].join('\n'), /vms_comp_arrangement|_vms_comp_arrangement|bvmgr_comp_arrangement|Compensation Arrangement|trade_barter|no_compensation/, 'Compensation arrangement selector, meta, helpers, and snapshot/hash integration are removed.');
assert.match(shell, /vms:event-plan-lock-pay-state-refreshed/, 'Authoritative server replacement notifies the Compensation controller.');
assert.match(compensation, /addEventListener\('vms:event-plan-lock-pay-state-refreshed', render\)/, 'Acknowledgment gating is reapplied to a replaced Lock control.');
assert.match(adminUiAssets, /bvmgr_admin_ui_local_asset_version\('assets\/js\/vms-event-plan-compensation\.js'\)/, 'Local/development Compensation assets receive deterministic file-based cache busting.');
assert.match(adminUiAssets, /'bvmgr-event-plan-compensation',[\s\S]*\$event_plan_compensation_version/, 'The Compensation enqueue uses its local/development file version.');

console.log('event plan compensation action state: PASS');
