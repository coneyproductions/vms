const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const shell = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js'), 'utf8');
const css = fs.readFileSync(path.resolve(__dirname, '../assets/css/vms-admin.css'), 'utf8');

function extractFunction(name) {
  const asyncStart = shell.indexOf('async function ' + name + '(');
  const start = asyncStart !== -1 ? asyncStart : shell.indexOf('function ' + name + '(');
  assert.notEqual(start, -1, 'Missing function: ' + name);
  const brace = shell.indexOf('{', start);
  let depth = 1;
  for (let index = brace + 1; index < shell.length; index += 1) {
    if (shell[index] === '{') depth += 1;
    if (shell[index] === '}') depth -= 1;
    if (depth === 0) return shell.slice(start, index + 1);
  }
  throw new Error('Unable to parse function: ' + name);
}

function deferred() {
  let resolve;
  const promise = new Promise((done) => { resolve = done; });
  return { promise, resolve };
}

function createDocument() {
  const title = { textContent: '' };
  const copy = { textContent: '' };
  let overlay = null;
  return {
    body: {
      appendChild(node) { overlay = node; },
    },
    createElement() {
      return {
        hidden: true,
        attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        querySelector(selector) {
          if (selector === '[data-vms-transition-title]') return title;
          if (selector === '[data-vms-transition-copy]') return copy;
          return null;
        },
      };
    },
    getElementById(id) {
      return id === 'vms-event-plan-transition-overlay' ? overlay : null;
    },
    transitionState() { return { overlay, title, copy }; },
  };
}

function createSection(key, label) {
  const body = {};
  const feedback = { textContent: '' };
  return {
    dataset: { sectionKey: key },
    querySelector(selector) {
      if (selector === '.vms-collapsible-label') return { textContent: label };
      if (selector === '.vms-collapsible-body') return body;
      if (selector === '[data-vms-section-feedback]') return feedback;
      return null;
    },
    body,
    feedback,
  };
}

function createTransitionController(options = {}) {
  const document = createDocument();
  const calls = [];
  const saveSection = options.saveSection || (async () => ({ ok: true, message: 'Saved.' }));
  const openAndFocusSection = options.openAndFocusSection || (async (section, force) => {
    calls.push(['open', section.dataset.sectionKey, force]);
    return true;
  });
  const factory = new Function(
    'document',
    'saveSection',
    'setCollapsed',
    'setSectionStatus',
    'canonicalizePersistedEventPlanUrl',
    'resetSectionBaseline',
    'applyLockPayState',
    'applyCanonicalReadinessState',
    'applyAuthoritativeDerivedState',
    'sectionTransientDirty',
    'openAndFocusSection',
    'var transitionInFlight = false;\n' +
      'var transitionNavigationAllowed = false;\n' +
      'var transitionNavigationSection = null;\n' +
      extractFunction('sectionLabel') + '\n' +
      extractFunction('ensureTransitionOverlay') + '\n' +
      extractFunction('beginTransition') + '\n' +
      extractFunction('endTransition') + '\n' +
      extractFunction('blockEventDuringTransition') + '\n' +
      extractFunction('saveAndMaybeOpen') + '\n' +
      'return {' +
        'saveAndMaybeOpen,' +
        'beginTransition,' +
        'endTransition,' +
        'blockEventDuringTransition,' +
        'isTransitionInFlight: function () { return transitionInFlight; },' +
        'isTransitionNavigationAllowed: function () { return transitionNavigationAllowed; }' +
      '};'
  );
  const controller = factory(
    document,
    async (section) => {
      calls.push(['save', section.dataset.sectionKey]);
      return saveSection(section);
    },
    (section, collapsed) => calls.push(['collapse', section.dataset.sectionKey, collapsed]),
    (section, label, state) => calls.push(['status', section.dataset.sectionKey, label, state]),
    (url, key) => calls.push(['canonicalize', url || '', key]),
    (section, persistedOnly) => calls.push(['baseline', section.dataset.sectionKey, persistedOnly]),
    () => true,
    () => true,
    () => true,
    () => false,
    openAndFocusSection
  );
  return { controller, document, calls };
}

async function assertDuplicateSaveIsBlocked(description, target) {
  const save = deferred();
  const fixture = createTransitionController({ saveSection: () => save.promise });
  const current = createSection('schedule', 'Schedule');
  const destination = target ? createSection('compensation', 'Compensation') : null;
  const first = fixture.controller.saveAndMaybeOpen(current, destination);

  assert.equal(fixture.controller.isTransitionInFlight(), true, description + ' acquires the lock before awaiting the save.');
  assert.equal(fixture.document.transitionState().overlay.hidden, false, description + ' shows the overlay immediately.');
  assert.equal(await fixture.controller.saveAndMaybeOpen(current, destination), false, description + ' ignores a duplicate request.');
  assert.equal(fixture.calls.filter((call) => call[0] === 'save').length, 1, description + ' starts only one save.');

  save.resolve({ ok: true, message: 'Saved.' });
  assert.equal(await first, true, description + ' completes the original request.');
  assert.equal(fixture.controller.isTransitionInFlight(), false, description + ' releases the lock on completion.');
}

(async () => {
  await assertDuplicateSaveIsBlocked('Dirty Save & Open', true);
  await assertDuplicateSaveIsBlocked('Save & Continue', true);
  await assertDuplicateSaveIsBlocked('Plain Save Changes', false);

  const navigation = deferred();
  let navigationCalls = 0;
  const successFixture = createTransitionController({
    openAndFocusSection: async () => {
      navigationCalls += 1;
      return navigation.promise;
    },
  });
  const schedule = createSection('schedule', 'Schedule');
  const compensation = createSection('compensation', 'Compensation');
  const successfulOpen = successFixture.controller.saveAndMaybeOpen(schedule, compensation);
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(navigationCalls, 1, 'Successful Save & Open uses the shared open-and-focus path.');
  assert.equal(successFixture.controller.isTransitionInFlight(), true, 'The lock remains active while navigation settles.');
  assert.equal(successFixture.controller.isTransitionNavigationAllowed(), true, 'The owned shared navigation path is admitted while locked.');
  assert.equal(successFixture.document.transitionState().overlay.hidden, false, 'The overlay remains visible while navigation settles.');
  navigation.resolve(true);
  assert.equal(await successfulOpen, true, 'Successful Save & Open completes after navigation.');
  assert.equal(successFixture.controller.isTransitionInFlight(), false, 'The lock releases after navigation finishes.');
  assert.equal(successFixture.document.transitionState().overlay.hidden, true, 'The overlay dismisses after navigation finishes.');

  let failedNavigationCalls = 0;
  const failureFixture = createTransitionController({
    saveSection: async () => ({ ok: false, message: 'Server rejected the save.' }),
    openAndFocusSection: async () => { failedNavigationCalls += 1; },
  });
  const failedSection = createSection('schedule', 'Schedule');
  assert.equal(await failureFixture.controller.saveAndMaybeOpen(failedSection, compensation), false, 'A failed save reports failure.');
  assert.equal(failedNavigationCalls, 0, 'A failed save does not open the destination.');
  assert.equal(failedSection.feedback.textContent, 'Server rejected the save.', 'Existing save-failure feedback remains visible.');
  assert.equal(failureFixture.controller.isTransitionInFlight(), false, 'A failed save releases the lock.');
  assert.equal(failureFixture.document.transitionState().overlay.hidden, true, 'A failed save dismisses the overlay.');

  const contentFixture = createTransitionController();
  assert.equal(contentFixture.controller.beginTransition(schedule, compensation), true, 'A transition can begin.');
  assert.equal(contentFixture.document.transitionState().title.textContent, 'Saving Schedule…', 'Overlay title identifies the current section.');
  assert.equal(
    contentFixture.document.transitionState().copy.textContent,
    'Your changes are being saved before Compensation opens.',
    'Overlay copy identifies the destination section.'
  );
  let prevented = 0;
  let stopped = 0;
  assert.equal(contentFixture.controller.blockEventDuringTransition({
    preventDefault() { prevented += 1; },
    stopPropagation() { stopped += 1; },
  }), true, 'Workflow and form events are blocked while the transition lock is active.');
  assert.equal(prevented, 1, 'Blocked actions have their default behavior prevented.');
  assert.equal(stopped, 1, 'Blocked actions do not propagate to another controller.');
  contentFixture.controller.endTransition();
  assert.equal(contentFixture.controller.blockEventDuringTransition({}), false, 'Actions are admitted after the lock releases.');

  const plainFixture = createTransitionController();
  assert.equal(plainFixture.controller.beginTransition(schedule, null), true, 'Plain saves use the same overlay.');
  assert.equal(
    plainFixture.document.transitionState().copy.textContent,
    'Please keep this page open while the section is saved.',
    'Plain saves use non-navigation progress copy.'
  );
  plainFixture.controller.endTransition();

  const openSectionSource = extractFunction('openSection');
  const cleanNavigationCalls = [];
  const cleanNavigationFactory = new Function(
    'initialActiveSection',
    'sectionDirty',
    'showDirtyPrompt',
    'setCollapsed',
    'isLazySectionUnloaded',
    'loadLazySection',
    'var transitionInFlight = false;\n' +
      'var transitionNavigationAllowed = false;\n' +
      'var transitionNavigationSection = null;\n' +
      'var activeSection = initialActiveSection;\n' +
      'var lastTouchedSectionKey = "";\n' +
      openSectionSource + '\n' +
      'return {' +
        'openSection: openSection,' +
        'setTransition: function (inFlight, allowed, section) {' +
          'transitionInFlight = inFlight;' +
          'transitionNavigationAllowed = allowed;' +
          'transitionNavigationSection = section;' +
        '}' +
      '};'
  );
  const currentCleanSection = createSection('schedule', 'Schedule');
  const cleanDestination = createSection('compensation', 'Compensation');
  const cleanNavigationController = cleanNavigationFactory(
    currentCleanSection,
    () => false,
    () => cleanNavigationCalls.push(['prompt']),
    (section, collapsed) => cleanNavigationCalls.push(['collapse', section.dataset.sectionKey, collapsed]),
    () => false,
    async () => true
  );
  assert.equal(await cleanNavigationController.openSection(cleanDestination, false), true, 'Clean section-to-section navigation remains immediate.');
  assert.deepEqual(cleanNavigationCalls, [
    ['collapse', 'schedule', true],
    ['collapse', 'compensation', false],
  ], 'Clean navigation neither prompts nor starts a save transition.');
  cleanNavigationCalls.length = 0;
  cleanNavigationController.setTransition(true, false, cleanDestination);
  assert.equal(await cleanNavigationController.openSection(cleanDestination, true), false, 'An unrelated navigation request is ignored while locked.');
  assert.deepEqual(cleanNavigationCalls, [], 'Blocked navigation does not collapse or open a section.');
  cleanNavigationController.setTransition(true, true, cleanDestination);
  assert.equal(await cleanNavigationController.openSection(cleanDestination, true), true, 'The single save-owned navigation request is admitted.');
  assert.equal(await cleanNavigationController.openSection(cleanDestination, true), false, 'The navigation permit is consumed so a duplicate request is ignored.');
  assert.doesNotMatch(openSectionSource, /beginTransition/, 'Clean section navigation does not start the save overlay.');
  assert.match(openSectionSource, /!transitionNavigationAllowed \|\| section !== transitionNavigationSection/, 'Unowned navigation is rejected while a transition is active.');
  assert.match(shell, /function showDirtyPrompt[\s\S]*if \(transitionInFlight\) return false;/, 'The dirty prompt cannot reopen while locked.');
  assert.ok((shell.match(/blockEventDuringTransition\(event\)/g) || []).length >= 4, 'Dirty-prompt, click, workflow, and submit entry points share the transition guard.');
  assert.match(shell, /var workflowSubmit[\s\S]*workflowSubmit && blockEventDuringTransition\(event\)/, 'Native workflow submissions are blocked while locked.');
  assert.match(shell, /role="status" aria-live="assertive" aria-atomic="true"/, 'The overlay exposes accessible live status semantics.');
  assert.doesNotMatch(extractFunction('ensureTransitionOverlay'), /<button/, 'The in-flight overlay has no close button.');
  assert.match(css, /\.vms-ep-transition-overlay \{[\s\S]*position: fixed;[\s\S]*inset: 0;/, 'The overlay blocks the full viewport.');
  assert.match(css, /\.vms-ep-transition-overlay\[hidden\] \{\s*display: none;/, 'The overlay can be dismissed after success or failure.');

  console.log('event plan save/navigation overlay contracts: PASS');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
