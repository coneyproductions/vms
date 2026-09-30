const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const shell = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js'), 'utf8');

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
    'initialControlState',
    'controlDirty',
    'isTransientActionControl',
    'sectionDirty',
    'resetSectionBaseline',
  ].map(extractFunction).join('\n') +
  '\nfunction setFlag() {}\n' +
  'return { readControlState, initialControlState, controlDirty, sectionDirty, resetSectionBaseline };'
);
const contract = factory();

function select(name, value, persistedState) {
  const options = [value].map((optionValue) => ({
    value: optionValue,
    selected: true,
    defaultSelected: true,
  }));
  return {
    name,
    value,
    disabled: false,
    type: 'select-one',
    tagName: 'SELECT',
    options,
    dataset: persistedState === undefined ? {} : { vmsPersistedState: persistedState },
    matches() { return false; },
  };
}

function initialize(control) {
  control.dataset.vmsInitialState = contract.initialControlState(control);
  return control;
}

function body(controls) {
  return { querySelectorAll() { return controls; } };
}

function section(controls) {
  return {
    querySelectorAll() { return controls; },
  };
}

const venueFallback = initialize(select('vms_venue_id', '74', ''));
const startFallback = initialize(select('vms_start_time', '18:30', ''));
const endFallback = initialize(select('vms_end_time', '22:30', ''));
assert.equal(contract.sectionDirty(body([venueFallback])), true, 'A rendered Venue fallback with no persisted Venue must make Event Details unsaved.');
assert.equal(contract.sectionDirty(body([startFallback, endFallback])), true, 'Rendered time defaults with no persisted times must make Schedule unsaved.');

const unrelatedControl = initialize(select('vms_comp_structure', 'flat_fee'));
assert.equal(contract.sectionDirty(body([unrelatedControl])), false, 'DOM-only baseline behavior must remain unchanged for unrelated controls.');

contract.resetSectionBaseline(section([venueFallback]), true);
contract.resetSectionBaseline(section([startFallback, endFallback]), true);
assert.equal(contract.sectionDirty(body([venueFallback])), false, 'Successful Event Details save must reset the Venue baseline to Saved.');
assert.equal(contract.sectionDirty(body([startFallback, endFallback])), false, 'Successful Schedule save must reset time baselines to Saved.');
assert.equal(venueFallback.dataset.vmsPersistedState, '74', 'Successful save must advance the Venue persisted-state marker.');
assert.equal(startFallback.dataset.vmsPersistedState, '18:30', 'Successful save must advance the Start Time persisted-state marker.');
assert.equal(endFallback.dataset.vmsPersistedState, '22:30', 'Successful save must advance the End Time persisted-state marker.');

const savedVenue = initialize(select('vms_venue_id', '74', '74'));
const savedStart = initialize(select('vms_start_time', '18:30', '18:30'));
const savedEnd = initialize(select('vms_end_time', '22:30', '22:30'));
assert.equal(contract.sectionDirty(body([savedVenue])), false, 'A genuinely persisted matching Venue must remain Saved.');
assert.equal(contract.sectionDirty(body([savedStart, savedEnd])), false, 'Genuinely persisted matching times must remain Saved.');

assert.ok(
  shell.includes('control.dataset.vmsInitialState = initialControlState(control);'),
  'Workspace initialization must use the persisted-state-aware baseline.'
);

console.log('event plan saved-default truthfulness browser contract: PASS');
