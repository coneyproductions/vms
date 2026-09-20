import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
let handler;
const document = { activeElement: null, addEventListener(name, callback, options) {
  assert.equal(name, 'wheel'); assert.equal(options.passive, false); handler = callback;
}};
vm.runInNewContext(fs.readFileSync(new URL('../../assets/vms-number-input-guard.js', import.meta.url), 'utf8'), { document });
let count = 0;
for (const [owner, focused, disabled, readOnly, expected] of [
  ['', true, false, false, false], ['vms-portal', true, false, false, true],
  ['vms-admin', true, false, false, true], ['vms-pass-public-page', true, false, false, true],
  ['vms-portal', false, false, false, false], ['vms-portal', true, true, false, false],
  ['vms-portal', true, false, true, false],
]) {
  let prevented = false, blurred = false;
  const target = { tagName: 'INPUT', type: 'number', disabled, readOnly,
    closest(selector) { return selector === 'input[type="number"]' ? this : (owner && selector.split(', ').includes('.' + owner) ? {} : null); },
    blur() { blurred = true; }
  };
  document.activeElement = focused ? target : null;
  handler({ target, preventDefault() { prevented = true; } });
  assert.equal(prevented, expected); assert.equal(blurred, expected); count++;
}
console.log(`PASS ${count} actual JavaScript ownership/focus/state cases`);
