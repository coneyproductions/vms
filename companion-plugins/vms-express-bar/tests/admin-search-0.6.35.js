'use strict';

const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const listeners = {};
const search = {
  value: '',
  addEventListener(event, callback) {
    listeners[event] = callback;
  },
};

const makeRow = (searchValue) => {
  const fields = {
    '[data-vmseb-field="enabled"]': { checked: false },
    '[data-vmseb-field="bucket_eligible"]': { checked: false },
    '[data-vmseb-field="age_gate"]': { checked: false },
    '[data-vmseb-field="sort"]': { value: '0' },
    '[data-vmseb-field="online_cap"]': { value: '0' },
    '[data-vmseb-field="bar_only_threshold"]': { value: '0' },
    '[data-vmseb-row-save]': { disabled: true, textContent: '', addEventListener() {} },
    '[data-vmseb-row-feedback]': { textContent: '', dataset: {} },
  };
  return {
    dataset: { token: '', productId: '0', variationId: '0' },
    style: { display: '' },
    classList: { toggle() {} },
    getAttribute(name) {
      return name === 'data-search' ? searchValue : '';
    },
    querySelector(selector) {
      return fields[selector] || null;
    },
    querySelectorAll() {
      return [];
    },
  };
};

const rows = [
  makeRow('House Red Wine HR-101 product 101 simple product'),
  makeRow('Draft Beer Pint DB-PINT product 202 variation 203'),
];

const document = {
  addEventListener(event, callback) {
    if (event === 'DOMContentLoaded') callback();
  },
  getElementById(id) {
    return id === 'vmseb-registry-search' ? search : null;
  },
  querySelectorAll() {
    return rows;
  },
  querySelector() {
    return null;
  },
};

const source = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'admin.js'), 'utf8');
vm.runInNewContext(source, { document, window: {}, URLSearchParams, fetch, WeakMap, JSON, Number, String, Math, Error });

const assert = (condition, message) => {
  if (!condition) {
    process.stderr.write(`FAIL: ${message}\n`);
    process.exit(1);
  }
};

assert(typeof listeners.input === 'function', 'filter input listener was not registered');
search.value = 'db-pint';
listeners.input();
assert(rows[0].style.display === 'none', 'nonmatching product row stayed visible');
assert(rows[1].style.display === '', 'matching SKU/variation row was hidden');

search.value = '101';
listeners.input();
assert(rows[0].style.display === '', 'matching product ID row was hidden');
assert(rows[1].style.display === 'none', 'nonmatching variation row stayed visible');

search.value = '';
listeners.input();
assert(rows.every((row) => row.style.display === ''), 'clearing the filter did not restore all rows');

process.stdout.write('PASS: Express Bar admin search 0.6.35\n');
