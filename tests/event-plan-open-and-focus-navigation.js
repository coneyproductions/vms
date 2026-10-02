const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js'), 'utf8');

function extractFunction(name) {
  const asyncStart = source.indexOf('async function ' + name + '(');
  const start = asyncStart !== -1 ? asyncStart : source.indexOf('function ' + name + '(');
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

const calls = [];
const factory = new Function(
  'openSection',
  'persistRequestedSection',
  'waitForSectionLayout',
  'scrollSectionWrapperIntoWorkingPosition',
  extractFunction('openAndFocusSection') + '\nreturn openAndFocusSection;'
);
const openAndFocus = factory(
  async (section, force) => {
    calls.push(['open', section.dataset.sectionKey, force]);
    return true;
  },
  (key) => calls.push(['persist', key]),
  async (section) => calls.push(['layout', section.dataset.sectionKey]),
  (section) => calls.push(['scroll', section.dataset.sectionKey])
);

function createPersistController(initialUrl, fullTicketingEditorPresent) {
  const window = {
    location: { href: initialUrl },
    history: {
      replaceState(state, title, nextUrl) {
        window.location.href = String(nextUrl || '');
      },
    },
  };
  const document = {
    getElementById(id) {
      return id === 'vms-ticketing-v2-editor' && fullTicketingEditorPresent ? {} : null;
    },
  };
  const persist = new Function(
    'window',
    'document',
    'normalizeRequestedSectionKey',
    'resolveAnchorIdForSection',
    extractFunction('persistRequestedSection') + '\nreturn persistRequestedSection;'
  )(
    window,
    document,
    (key) => String(key || ''),
    (key) => key === 'ticketing_v2' ? 'vms_event_plan_ticketing_v2' : 'vms-event-plan-' + key
  );
  return { persist, window };
}

(async () => {
  const destinations = ['basics', 'schedule', 'compensation', 'secondary_vendors', 'staff', 'ticketing_v2', 'readiness_details', 'cancellation'];
  for (const key of destinations) {
    calls.length = 0;
    const section = { dataset: { sectionKey: key } };
    assert.equal(await openAndFocus(section, true), true, key + ' opens successfully.');
    assert.deepEqual(calls, [
      ['open', key, true],
      ['persist', key],
      ['layout', key],
      ['scroll', key],
    ], key + ' follows the same open, settle, and scroll path.');
  }

  const summaryController = createPersistController(
    'https://serenaderange.local/wp-admin/post.php?post=16531&action=edit',
    false
  );
  const summaryUrl = summaryController.persist('ticketing_v2');
  assert.equal(
    summaryUrl,
    'https://serenaderange.local/wp-admin/post.php?post=16531&action=edit#vms_event_plan_ticketing_v2',
    'Opening the Ticketing summary preserves its anchor without falsely recording the full-editor server trigger.'
  );
  assert.equal(
    new URL(summaryController.window.location.href).searchParams.has('vms_ep_load_section'),
    false,
    'The Ticketing summary URL remains different from the full-editor loader URL.'
  );

  const staleSummaryController = createPersistController(
    'https://serenaderange.local/wp-admin/post.php?post=16531&action=edit&vms_ep_load_section=ticketing_v2#vms_event_plan_ticketing_v2',
    false
  );
  staleSummaryController.persist('ticketing_v2');
  assert.equal(
    new URL(staleSummaryController.window.location.href).searchParams.has('vms_ep_load_section'),
    false,
    'A summary-only document removes a stale Ticketing full-editor trigger from browser history.'
  );

  const fullEditorController = createPersistController(
    'https://serenaderange.local/wp-admin/post.php?post=16531&action=edit&vms_ep_load_section=ticketing_v2',
    true
  );
  fullEditorController.persist('ticketing_v2');
  assert.equal(
    new URL(fullEditorController.window.location.href).searchParams.get('vms_ep_load_section'),
    'ticketing_v2',
    'A genuinely rendered full Ticketing editor retains its server-render trigger.'
  );

  const scheduleController = createPersistController(
    'https://serenaderange.local/wp-admin/post.php?post=16531&action=edit',
    false
  );
  scheduleController.persist('schedule');
  assert.equal(
    new URL(scheduleController.window.location.href).searchParams.get('vms_ep_load_section'),
    'schedule',
    'Unrelated Event Plan sections retain normal requested-section persistence.'
  );

  assert.match(source, /await openAndFocusDestination\(target, true\);/, 'Save & Continue and dirty Save & Open use the shared destination-aware open-and-focus path.');
  assert.match(source, /openAndFocusSection\(section, true\);/, 'Requested section reveal uses open-and-focus.');
  assert.match(source, /else if \(requestedSection\) \{\s*openAndFocusSection\(requestedSection, false\);/, 'data-vms-open-section actions use open-and-focus.');
  assert.match(source, /if \(isLazySectionUnloaded\(section\)\)[\s\S]*await loadLazySection\(section\)/, 'The shared open path waits for lazy section loading.');
  assert.doesNotMatch(source, /setTimeout\([^)]*scrollIntoView/, 'Section focus must not depend on the old fixed-delay scroll.');

  console.log('event plan open-and-focus navigation contracts: PASS');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
