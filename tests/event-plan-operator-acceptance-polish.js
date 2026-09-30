const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const shell = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js'), 'utf8');
const compensation = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-compensation.js'), 'utf8');

function extractFunction(source, name) {
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

const derivedFactory = new Function(
  'document',
  'controlDirty',
  extractFunction(shell, 'updateEventDetailsDerivedState') + '\nreturn updateEventDetailsDerivedState;'
);
const eventDate = { dirty: false };
const venue = { dirty: false };
const holidayAuthoritative = { hidden: false };
const holidayUnsaved = { hidden: true };
const scheduleAuthoritative = { hidden: false };
const scheduleUnsaved = { hidden: true };
const derivedContainers = [
  { querySelector(selector) { return selector.includes('authoritative') ? holidayAuthoritative : holidayUnsaved; } },
  { querySelector(selector) { return selector.includes('authoritative') ? scheduleAuthoritative : scheduleUnsaved; } },
];
const derivedDocument = {
  getElementById(id) { return id === 'vms_event_date' ? eventDate : venue; },
  querySelectorAll() { return derivedContainers; },
};
const updateDerived = derivedFactory(derivedDocument, (control) => control.dirty);
updateDerived();
assert.equal(holidayAuthoritative.hidden, false, 'Saved holiday result stays visible.');
assert.equal(scheduleAuthoritative.hidden, false, 'Saved availability result stays visible.');
eventDate.dirty = true;
updateDerived();
assert.equal(holidayAuthoritative.hidden, true, 'Unsaved Event Date hides stale holiday authority.');
assert.equal(holidayUnsaved.hidden, false, 'Unsaved Event Date shows save-first holiday guidance.');
assert.equal(scheduleAuthoritative.hidden, true, 'Unsaved Event Date hides stale availability authority.');
assert.equal(scheduleUnsaved.hidden, false, 'Unsaved Event Date shows save-first availability guidance.');

const sectionMap = {};
['basics', 'schedule', 'compensation', 'secondary_vendors', 'staff', 'ticketing_v2', 'readiness_details', 'cancellation'].forEach((key) => {
  sectionMap[key] = { dataset: { sectionKey: key } };
});
const navigationFactory = new Function(
  'form',
  'cssEscapeValue',
  'continuationSectionOrder',
  extractFunction(shell, 'nextWorkflowSection') + '\nreturn nextWorkflowSection;'
);
const nextWorkflowSection = navigationFactory(
  {
    querySelector(selector) {
      const match = selector.match(/data-section-key="([^"]+)"/);
      return match ? sectionMap[match[1]] || null : null;
    },
  },
  (value) => value,
  ['basics', 'schedule', 'compensation', 'secondary_vendors', 'staff', 'ticketing_v2', 'readiness_details']
);
assert.equal(nextWorkflowSection(sectionMap.basics), sectionMap.schedule, 'Event Details continues to Schedule.');
assert.equal(nextWorkflowSection(sectionMap.ticketing_v2), sectionMap.readiness_details, 'Ticketing continues to Readiness/Review.');
assert.equal(nextWorkflowSection(sectionMap.cancellation), null, 'Cancellation is excluded from normal continuation.');

function select(value) {
  return {
    value,
    dataset: {},
    listeners: {},
    addEventListener(name, callback) { this.listeners[name] = callback; },
  };
}
function conditionalField(attribute, value) {
  const control = { disabled: false, value: 'preserve me' };
  return {
    hidden: false,
    control,
    getAttribute(name) { return name === attribute ? value : ''; },
    setAttribute(name, next) { this[name] = next; },
    querySelectorAll() { return [control]; },
  };
}
const timing = select('day_of_event');
const method = select('check');
const depositStatus = select('not_required');
const days = conditionalField('data-vms-final-payment-timing', 'days_after');
const fixed = conditionalField('data-vms-final-payment-timing', 'fixed_date');
const custom = conditionalField('data-vms-final-payment-timing', 'custom');
const other = conditionalField('data-vms-final-payment-method', 'other');
const depositTreatment = conditionalField('data-vms-deposit-details', '');
const compDocument = {
  getElementById(id) {
    if (id === 'vms_final_payment_timing') return timing;
    if (id === 'vms_final_payment_method') return method;
    return depositStatus;
  },
  querySelectorAll(selector) {
    if (selector.includes('timing')) return [days, fixed, custom];
    if (selector.includes('method')) return [other];
    return [depositTreatment];
  },
};
const conditionalFactory = new Function(
  'document',
  extractFunction(compensation, 'initFinalPaymentConditionalFields') + '\nreturn initFinalPaymentConditionalFields;'
);
const initConditional = conditionalFactory(compDocument);
assert.equal(initConditional(), true, 'Conditional final-payment controller initializes.');
assert.equal(days.hidden, true, 'Days After is hidden for day-of-event timing.');
assert.equal(days.control.disabled, true, 'Irrelevant Days After input is disabled.');
assert.equal(other.hidden, true, 'Other Method is hidden for check payments.');
assert.equal(depositTreatment.hidden, true, 'Deposit details hide when no deposit is required.');
assert.equal(depositTreatment.control.disabled, false, 'Hidden deposit details retain the existing submission contract.');
assert.equal(depositTreatment.control.value, 'preserve me', 'Hiding deposit details preserves their browser values.');
timing.value = 'days_after';
timing.listeners.change();
assert.equal(days.hidden, false, 'Days After is shown for N-days timing.');
assert.equal(days.control.disabled, false, 'Relevant Days After input is enabled.');
assert.equal(days.control.value, 'preserve me', 'Conditional changes preserve browser values for reversal.');
timing.value = 'fixed_date';
timing.listeners.change();
assert.equal(days.hidden, true, 'Days After hides when timing changes.');
assert.equal(fixed.hidden, false, 'Specific Pay Date shows for fixed-date timing.');
method.value = 'other';
method.listeners.change();
assert.equal(other.hidden, false, 'Other Method shows only for Other.');
assert.equal(other.control.disabled, false, 'Other Method becomes submit-capable when relevant.');
depositStatus.value = 'unpaid';
depositStatus.listeners.change();
assert.equal(depositTreatment.hidden, false, 'Deposit details show when a deposit status is selected.');
assert.equal(depositTreatment.control.disabled, false, 'Visible deposit details are submit-capable.');
assert.equal(depositTreatment.control.value, 'preserve me', 'Revealing deposit details restores the preserved value.');

console.log('event plan operator acceptance polish browser contracts: PASS');
