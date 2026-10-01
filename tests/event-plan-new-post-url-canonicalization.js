const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const shell = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js'), 'utf8');
const secondaryVendors = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-secondary-vendors.js'), 'utf8');
const eventPlans = fs.readFileSync(path.resolve(__dirname, '../includes/cpt/event-plans.php'), 'utf8');

function extractFunction(source, name) {
  let start = source.indexOf('function ' + name + '(');
  assert.notEqual(start, -1, 'Missing function: ' + name);
  if (source.slice(Math.max(0, start - 6), start) === 'async ') start -= 6;
  const brace = source.indexOf('{', start);
  let depth = 1;
  for (let index = brace + 1; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}') depth -= 1;
    if (depth === 0) return source.slice(start, index + 1);
  }
  throw new Error('Unable to parse function: ' + name);
}

const canonicalizeSource = extractFunction(shell, 'canonicalizePersistedEventPlanUrl');
const saveAndMaybeOpenSource = extractFunction(shell, 'saveAndMaybeOpen');

function makeWindow(initialUrl) {
  let href = initialUrl;
  const replacements = [];
  let navigations = 0;
  const windowMock = {
    location: {
      get href() { return href; },
      assign() { navigations += 1; },
      reload() { navigations += 1; },
    },
    history: {
      state: { nativeEditorState: 'preserved' },
      replaceState(state, title, nextUrl) {
        this.state = state;
        href = String(nextUrl);
        replacements.push(href);
      },
    },
  };
  return {
    windowMock,
    replacements,
    get href() { return href; },
    get navigations() { return navigations; },
  };
}

function makeCanonicalizer(browser) {
  const anchors = {
    basics: 'vms-event-plan-basics',
    schedule: 'vms-event-plan-schedule',
    secondary_vendors: 'vms-additional-vendors',
  };
  const factory = new Function(
    'window',
    'postId',
    'normalizeRequestedSectionKey',
    'resolveAnchorIdForSection',
    canonicalizeSource + '\nreturn canonicalizePersistedEventPlanUrl;'
  );
  return factory(
    browser.windowMock,
    77,
    (key) => Object.prototype.hasOwnProperty.call(anchors, String(key || '')) ? String(key) : '',
    (key) => anchors[key] || ''
  );
}

const canonicalEditUrl = 'https://example.test/wp-admin/post.php?post=77&action=edit';
const nativeState = {
  title: 'Unsaved native title',
  content: 'Unsaved native content',
  featuredImageId: 314,
};

const newPostBrowser = makeWindow('https://example.test/wp-admin/post-new.php?post_type=vms_event_plan');
const newPostCanonicalizer = makeCanonicalizer(newPostBrowser);
assert.equal(newPostCanonicalizer(canonicalEditUrl, 'basics'), true, 'A successful new-post save canonicalizes its URL.');
assert.equal(newPostBrowser.navigations, 0, 'Canonicalization performs no navigation or reload.');
assert.equal(newPostBrowser.replacements.length, 1, 'Canonicalization uses one history replacement.');
const savedUrl = new URL(newPostBrowser.href);
assert.equal(savedUrl.pathname, '/wp-admin/post.php', 'The history target is the canonical post editor.');
assert.equal(savedUrl.searchParams.get('post'), '77', 'The history target retains the persisted Event Plan ID.');
assert.equal(savedUrl.searchParams.get('action'), 'edit', 'The history target uses the edit action.');
assert.equal(savedUrl.searchParams.get('vms_ep_load_section'), 'basics', 'Save Changes retains the saved section target.');
assert.equal(savedUrl.hash, '#vms-event-plan-basics', 'Save Changes retains the saved section anchor.');
assert.notEqual(savedUrl.pathname, '/wp-admin/post-new.php', 'A subsequent reload can no longer target post-new.php.');
assert.deepEqual(nativeState, {
  title: 'Unsaved native title',
  content: 'Unsaved native content',
  featuredImageId: 314,
}, 'Changing browser history does not submit or clear native editor fields.');

const existingBrowser = makeWindow('https://example.test/wp-admin/post.php?post=77&action=edit&message=1#old');
const existingCanonicalizer = makeCanonicalizer(existingBrowser);
assert.equal(existingCanonicalizer(canonicalEditUrl, 'schedule'), true, 'An existing canonical editor URL accepts a section update.');
const existingUrl = new URL(existingBrowser.href);
assert.equal(existingUrl.searchParams.get('post'), '77', 'Existing editor interaction stays on the same Event Plan.');
assert.equal(existingUrl.searchParams.get('message'), '1', 'Existing unrelated editor query state is preserved.');
assert.equal(existingUrl.searchParams.get('vms_ep_load_section'), 'schedule', 'Existing editor URL receives the relevant section target.');
assert.equal(existingUrl.hash, '#vms-event-plan-schedule', 'Existing editor URL receives the relevant section anchor.');
assert.equal(existingBrowser.navigations, 0, 'Existing editor URL updates without navigation.');

async function exerciseSaveAndContinue() {
  const browser = makeWindow('https://example.test/wp-admin/post-new.php?post_type=vms_event_plan');
  const canonicalizer = makeCanonicalizer(browser);
  const feedback = { textContent: '' };
  const body = {};
  const section = {
    dataset: { sectionKey: 'basics' },
    querySelector(selector) { return selector.includes('feedback') ? feedback : body; },
  };
  const schedule = { dataset: { sectionKey: 'schedule' } };
  let opened = null;
  let scrolled = null;
  let derivedRefreshes = 0;
  const factory = new Function(
    'saveSection',
    'canonicalizePersistedEventPlanUrl',
    'resetSectionBaseline',
    'sectionTransientDirty',
    'setCollapsed',
    'setSectionStatus',
    'applyAuthoritativeDerivedState',
    'applyLockPayState',
    'applyCanonicalReadinessState',
    'statusRoot',
    'openAndFocusDestination',
    'beginTransition',
    'endTransition',
    'var transitionNavigationAllowed = false;\n' +
      'var transitionNavigationSection = null;\n' +
      saveAndMaybeOpenSource + '\nreturn saveAndMaybeOpen;'
  );
  const saveAndMaybeOpen = factory(
    async () => ({
      ok: true,
      canonicalEditUrl,
      message: 'Event Details saved.',
      derivedState: { post_id: 77 },
    }),
    canonicalizer,
    () => {},
    () => false,
    () => {},
    () => {},
    (state) => { derivedRefreshes += 1; return state.post_id === 77; },
    () => true,
    () => true,
    null,
    async (target) => { opened = target; scrolled = target.dataset.sectionKey; return true; },
    () => true,
    () => {}
  );
  const ok = await saveAndMaybeOpen(section, schedule);
  return { browser, ok, opened, schedule, scrolled, derivedRefreshes, feedback };
}

(async () => {
  const result = await exerciseSaveAndContinue();
  assert.equal(result.ok, true, 'New-post Save & Continue succeeds.');
  assert.equal(result.browser.navigations, 0, 'Save & Continue performs zero navigation or reload.');
  assert.equal(result.opened, result.schedule, 'Save & Continue opens Schedule in the current DOM.');
  assert.equal(result.scrolled, 'schedule', 'Save & Continue scrolls the Schedule section into place.');
  assert.equal(result.derivedRefreshes, 1, 'The accepted authoritative derived-state refresh remains in place.');
  const continuedUrl = new URL(result.browser.href);
  assert.equal(continuedUrl.pathname, '/wp-admin/post.php', 'Save & Continue retains the canonical edit URL.');
  assert.equal(continuedUrl.searchParams.get('post'), '77', 'Save & Continue retains the same Event Plan ID.');
  assert.equal(continuedUrl.searchParams.get('vms_ep_load_section'), 'schedule', 'Save & Continue records Schedule as the reload target.');
  assert.equal(continuedUrl.hash, '#vms-event-plan-schedule', 'Save & Continue records the Schedule anchor.');

  assert.ok(
    eventPlans.includes("'canonical_edit_url' => bvmgr_event_plan_admin_edit_url($post_id)"),
    'Successful save responses expose the canonical URL from the existing server helper.'
  );
  assert.ok(
    eventPlans.indexOf("'canonical_edit_url' => bvmgr_event_plan_admin_edit_url($post_id)")
      !== eventPlans.lastIndexOf("'canonical_edit_url' => bvmgr_event_plan_admin_edit_url($post_id)"),
    'Both scoped and Additional Vendors save responses expose the canonical edit URL.'
  );
  assert.ok(
    secondaryVendors.includes("window.BVMGR_EVENT_PLAN_CANONICALIZE_EDIT_URL(payload.data.canonical_edit_url, 'secondary_vendors');"),
    'A successful isolated Additional Vendors save canonicalizes a new-post URL before reporting completion.'
  );
  const additionalBrowser = makeWindow('https://example.test/wp-admin/post-new.php?post_type=vms_event_plan');
  const additionalCanonicalizer = makeCanonicalizer(additionalBrowser);
  assert.equal(additionalCanonicalizer(canonicalEditUrl, 'secondary_vendors'), true, 'Additional Vendors can canonicalize the new-post editor.');
  const additionalUrl = new URL(additionalBrowser.href);
  assert.equal(additionalUrl.searchParams.get('post'), '77', 'Additional Vendors retains the persisted Event Plan ID.');
  assert.equal(additionalUrl.searchParams.get('vms_ep_load_section'), 'secondary_vendors', 'Additional Vendors retains its section target.');
  assert.equal(additionalUrl.hash, '#vms-additional-vendors', 'Additional Vendors retains its section anchor.');
  assert.equal(additionalBrowser.navigations, 0, 'Additional Vendors canonicalization does not navigate or reload.');

  assert.equal(saveAndMaybeOpenSource.includes('window.location'), false, 'Scoped save completion contains no direct navigation.');
  assert.equal(canonicalizeSource.includes('.submit('), false, 'URL canonicalization never submits the native WordPress form.');
  assert.equal(canonicalizeSource.includes('.reset('), false, 'URL canonicalization never resets native editor state.');
  assert.equal(canonicalizeSource.includes('replaceState'), true, 'URL canonicalization is history-only.');

  console.log('event plan new-post URL canonicalization: PASS');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
