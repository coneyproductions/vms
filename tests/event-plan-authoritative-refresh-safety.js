const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const shell = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js'), 'utf8');

function extractFunction(name) {
  let start = shell.indexOf('function ' + name + '(');
  assert.notEqual(start, -1, 'Missing workspace function: ' + name);
  if (shell.slice(Math.max(0, start - 6), start) === 'async ') start -= 6;
  const brace = shell.indexOf('{', start);
  let depth = 1;
  for (let index = brace + 1; index < shell.length; index += 1) {
    if (shell[index] === '{') depth += 1;
    if (shell[index] === '}') depth -= 1;
    if (depth === 0) return shell.slice(start, index + 1);
  }
  throw new Error('Unable to parse workspace function: ' + name);
}

function derivedContainer() {
  const authoritative = { hidden: true, innerHTML: '' };
  const unsaved = { hidden: false };
  return {
    dataset: {},
    authoritative,
    unsaved,
    querySelector(selector) {
      return selector.includes('authoritative') ? authoritative : unsaved;
    },
  };
}
const holidayContainer = derivedContainer();
const scheduleContainer = derivedContainer();
const basicsSummary = { textContent: '' };
const basicsSection = { querySelector() { return basicsSummary; } };
const dispatched = [];
const documentMock = {
  querySelector(selector) {
    return selector.includes('holiday') ? holidayContainer : scheduleContainer;
  },
  dispatchEvent(event) { dispatched.push(event); },
};
const formMock = { querySelector() { return basicsSection; } };
function CustomEventMock(type, options) {
  this.type = type;
  this.detail = options.detail;
}
const applyFactory = new Function(
  'document',
  'form',
  'postId',
  'CustomEvent',
  extractFunction('applyAuthoritativeDerivedState') + '\nreturn applyAuthoritativeDerivedState;'
);
const applyAuthoritative = applyFactory(documentMock, formMock, 77, CustomEventMock);
const derivedState = {
  post_id: 77,
  event_date: '2026-12-24',
  venue_id: 9,
  venue_label: 'Main Room',
  holiday_state: 'open',
  holiday_status: 'open',
  holiday_name: 'Christmas Eve',
  holiday_html: '<p>OPEN Christmas Eve</p>',
  schedule_date_state: 'ready',
  schedule_date_label: 'Dec 24, 2026',
  schedule_date_html: '<p>Availability for Dec 24, 2026</p>',
  vendor_options: { primary_html: '<option></option>', supporting_html: '<option></option>' },
};
assert.equal(applyAuthoritative(derivedState), true, 'Same-post authoritative payload applies in place.');
assert.equal(holidayContainer.authoritative.innerHTML, '<p>OPEN Christmas Eve</p>', 'Holiday fragment updates from server output.');
assert.equal(scheduleContainer.authoritative.innerHTML, '<p>Availability for Dec 24, 2026</p>', 'Schedule fragment updates from server output.');
assert.equal(holidayContainer.authoritative.hidden, false, 'Authoritative content becomes visible.');
assert.equal(scheduleContainer.unsaved.hidden, true, 'Unsaved guidance clears after verified persistence.');
assert.equal(holidayContainer.dataset.vmsSavedVenueId, '9', 'Saved Venue is recorded in the in-place UI state.');
assert.equal(scheduleContainer.dataset.vmsSavedEventDate, '2026-12-24', 'Saved Event Date is recorded in Schedule state.');
assert.equal(basicsSummary.textContent, '2026-12-24 · Main Room', 'Event Details summary updates in place.');
assert.equal(dispatched[0].type, 'vms:event-plan-derived-state-refreshed', 'Lineup receives the same authoritative state.');
assert.equal(applyAuthoritative({ ...derivedState, post_id: 78 }), false, 'Payload for a different Event Plan is rejected.');

const saveAndMaybeOpenSource = extractFunction('saveAndMaybeOpen');
assert.equal(saveAndMaybeOpenSource.includes('window.location'), false, 'Scoped Event Details save must not navigate or reload.');
assert.equal(saveAndMaybeOpenSource.includes('persistRequestedSection'), false, 'Scoped Event Details save must not rebuild a post-new URL.');
assert.ok(shell.includes("params.set('post_id', String(postId))"), 'Scoped save must retain the current Event Plan ID.');

async function exerciseUrl(url, target) {
  let navigations = 0;
  let opened = null;
  let scrolled = null;
  global.window = {
    location: {
      href: url,
      assign() { navigations += 1; },
      reload() { navigations += 1; },
    },
  };
  const feedback = { textContent: '' };
  const body = {};
  const section = {
    dataset: { sectionKey: 'basics' },
    querySelector(selector) { return selector.includes('feedback') ? feedback : body; },
  };
  const factory = new Function(
    'saveSection',
    'resetSectionBaseline',
    'sectionTransientDirty',
    'setCollapsed',
    'setSectionStatus',
    'applyAuthoritativeDerivedState',
    'openSection',
    'scrollSectionTargetIntoView',
    saveAndMaybeOpenSource + '\nreturn saveAndMaybeOpen;'
  );
  const saveAndMaybeOpen = factory(
    async () => ({ ok: true, message: 'Event Details saved.', derivedState }),
    () => {},
    () => false,
    () => {},
    () => {},
    (state) => state.post_id === 77,
    async (destination) => { opened = destination; return true; },
    (key) => { scrolled = key; }
  );
  const ok = await saveAndMaybeOpen(section, target);
  return { ok, navigations, opened, scrolled, feedback: feedback.textContent };
}

(async () => {
  const schedule = { dataset: { sectionKey: 'schedule' } };
  const review = { dataset: { sectionKey: 'readiness_details' } };
  const newPostStay = await exerciseUrl('https://example.test/wp-admin/post-new.php?post_type=vms_event_plan', null);
  assert.equal(newPostStay.ok, true, 'New-post Save Changes succeeds in place.');
  assert.equal(newPostStay.navigations, 0, 'New-post Save Changes never reloads post-new.php.');
  assert.equal(newPostStay.opened, null, 'Save Changes stays in Event Details.');

  const newPostContinue = await exerciseUrl('https://example.test/wp-admin/post-new.php?post_type=vms_event_plan', schedule);
  assert.equal(newPostContinue.navigations, 0, 'New-post Save & Continue never creates another auto-draft through navigation.');
  assert.equal(newPostContinue.opened, schedule, 'New-post Save & Continue opens Schedule.');
  assert.equal(newPostContinue.scrolled, 'schedule', 'New-post Save & Continue smooth-scrolls to Schedule.');

  const existingPost = await exerciseUrl('https://example.test/wp-admin/post.php?post=77&action=edit', review);
  assert.equal(existingPost.navigations, 0, 'Existing-post Save & Open also stays on the current page.');
  assert.equal(existingPost.opened, review, 'Dirty-prompt Save & Open reaches its requested destination.');
  assert.equal(existingPost.scrolled, 'readiness_details', 'Dirty-prompt destination is scrolled into working position.');

  console.log('event plan authoritative in-place refresh safety: PASS');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
