const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const shell = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js'), 'utf8');
const primaryVendor = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-primary-vendor.js'), 'utf8');
const schedulePartial = fs.readFileSync(path.resolve(__dirname, '../includes/cpt/event-plans/partials/time-lineup.php'), 'utf8');

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

const workspaceCount = { textContent: '5 blocking issues' };
const readinessCount = { textContent: '5 blocking issues' };
const readinessSection = {
  dataset: { vmsLazySection: 'readiness_details', vmsLazyLoaded: '1' },
  querySelector(selector) {
    return selector === '[data-vms-readiness-blocking-meta]' ? readinessCount : null;
  },
};
const statusRoot = {
  querySelector(selector) {
    return selector === '[data-vms-workspace-readiness-count]' ? workspaceCount : null;
  },
};
const form = {
  querySelector(selector) {
    return selector.includes('readiness_details') ? readinessSection : null;
  },
};
const readinessFactory = new Function(
  'statusRoot',
  'form',
  extractFunction(shell, 'applyCanonicalReadinessState') + '\nreturn applyCanonicalReadinessState;'
);
const applyReadiness = readinessFactory(statusRoot, form);
assert.equal(applyReadiness({
  blocking_issue_count: 1,
  blocking_issue_label: '1 blocking issue',
  blockers: [{ code: 'missing_primary_vendor', label: 'Primary Vendor' }],
}), true, 'Canonical server readiness payload must apply immediately.');
assert.equal(workspaceCount.textContent, '1 blocking issue', 'Workspace Readiness banner must refresh without reload.');
assert.equal(readinessCount.textContent, '1 blocking issue', 'Readiness section summary must use the same canonical count.');
assert.equal(readinessSection.dataset.vmsLazyLoaded, '0', 'Previously loaded Readiness details must be invalidated after saved state changes.');
assert.equal(applyReadiness({ blocking_issue_count: 'not-a-number', blocking_issue_label: '' }), false, 'Malformed readiness data must fail closed.');
assert.ok(shell.includes('applyCanonicalReadinessState(result.readinessState);'), 'Verified section saves must apply canonical readiness before continuing.');
assert.ok(shell.includes('readinessState: payload.data && payload.data.readiness_state'), 'The scoped-save transport must expose canonical readiness state.');

const fallbackUntil = {
  value: '2026-10-30',
  defaultValue: '',
  dataset: { vmsInitialState: '' },
};
const fallbackReason = {
  value: '',
  defaultValue: '',
  dataset: { vmsInitialState: '' },
};
const baselineEvents = [];
function CustomEventMock(type, options) {
  this.type = type;
  this.detail = options.detail;
}
const documentMock = {
  dispatchEvent(event) { baselineEvents.push(event); },
};
const bypassFactory = new Function(
  'bypassUntil',
  'bypassReason',
  'document',
  'CustomEvent',
  extractFunction(primaryVendor, 'establishRenderedBypassBaseline') + '\nreturn establishRenderedBypassBaseline;'
);
bypassFactory(fallbackUntil, fallbackReason, documentMock, CustomEventMock)();
assert.equal(fallbackUntil.dataset.vmsInitialState, '2026-10-30', 'Generated tax-bypass fallback must become the rendered baseline, not a false operator edit.');
assert.equal(fallbackUntil.defaultValue, '2026-10-30', 'Native dirty fallback must agree with the generated rendered baseline.');
assert.equal(baselineEvents.length, 2, 'Shell status must be reevaluated after tax-bypass controls finish initial rendering.');
assert.equal(baselineEvents[0].type, 'vms:event-plan-control-baseline-refreshed', 'Tax-bypass initialization must use the bounded baseline refresh event.');
assert.ok(primaryVendor.indexOf('render();\n    establishRenderedBypassBaseline();') !== -1, 'Tax-bypass baseline must be captured after its initial fallback is rendered.');
assert.ok(!/<input[^>]+id="vms-tax-bypass-until"[^>]+name=/.test(schedulePartial), 'Rendered tax-bypass fallback must remain outside ordinary Schedule persistence.');

console.log('event plan Schedule/readiness browser follow-up: PASS');
