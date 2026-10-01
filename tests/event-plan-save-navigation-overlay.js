const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const shell = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js'), 'utf8');
const css = fs.readFileSync(path.resolve(__dirname, '../assets/css/vms-admin.css'), 'utf8');
const adminUiAssets = fs.readFileSync(path.resolve(__dirname, '../includes/admin-ui/assets.php'), 'utf8');
const timeLineup = fs.readFileSync(path.resolve(__dirname, '../includes/cpt/event-plans/partials/time-lineup.php'), 'utf8');

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

function createWindow() {
  return {
    location: {
      href: 'https://serenaderange.local/wp-admin/post.php?post=123&action=edit',
      assign() {},
      reload() {},
    },
  };
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

function createSection(key, label, controls = []) {
  const body = {
    querySelectorAll(selector) {
      return selector === 'input, select, textarea' ? controls : [];
    },
  };
  const feedback = { textContent: '' };
  return {
    dataset: { sectionKey: key },
    classList: {
      contains(className) { return className === 'is-collapsed'; },
    },
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
  const window = options.window || createWindow();
  const calls = [];
  const saveSection = options.saveSection || (async () => ({ ok: true, message: 'Saved.' }));
  const openAndFocusSection = options.openAndFocusSection || (async (section, force) => {
    calls.push(['open', section.dataset.sectionKey, force]);
    return true;
  });
  const factory = new Function(
    'document',
    'window',
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
    'nextWorkflowSection',
    'form',
    'initExistingSection',
    'editableSectionKeys',
    'var transitionInFlight = false;\n' +
      'var transitionNavigationAllowed = false;\n' +
      'var transitionNavigationSection = null;\n' +
      'var activeSection = null;\n' +
      'var pendingSwitchSection = null;\n' +
      'var suppressBeforeUnload = false;\n' +
      extractFunction('sectionLabel') + '\n' +
      extractFunction('ensureTransitionOverlay') + '\n' +
      extractFunction('beginTransition') + '\n' +
      extractFunction('endTransition') + '\n' +
      extractFunction('blockEventDuringTransition') + '\n' +
      extractFunction('saveAndMaybeOpen') + '\n' +
      extractFunction('handleSectionActionClick') + '\n' +
      extractFunction('handleDirtyPromptClick') + '\n' +
      extractFunction('handleSectionToggleClick') + '\n' +
      'return {' +
        'saveAndMaybeOpen,' +
        'beginTransition,' +
        'endTransition,' +
        'blockEventDuringTransition,' +
        'handleSectionActionClick,' +
        'handleDirtyPromptClick,' +
        'handleSectionToggleClick,' +
        'setDirtyContext: function (current, target) { activeSection = current; pendingSwitchSection = target; },' +
        'pendingSwitchSection: function () { return pendingSwitchSection; },' +
        'isTransitionInFlight: function () { return transitionInFlight; },' +
        'isTransitionNavigationAllowed: function () { return transitionNavigationAllowed; }' +
      '};'
  );
  const controller = factory(
    document,
    window,
    async (section) => {
      calls.push(['save', section.dataset.sectionKey]);
      if (options.onSave) options.onSave(document.transitionState());
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
    openAndFocusSection,
    options.nextWorkflowSection || (() => null),
    options.form || { contains: () => true },
    options.initExistingSection || (() => {}),
    options.editableSectionKeys || new Set(['basics', 'schedule', 'compensation'])
  );
  return { controller, document, calls };
}

function createSectionActionEvent(action, section) {
  const button = {
    dataset: { vmsSectionAction: action },
    closest(selector) {
      return selector === '.vms-collapsible-section[data-section-key]' ? section : null;
    },
  };
  return {
    prevented: false,
    target: {
      closest(selector) {
        return selector === '[data-vms-section-action]' ? button : null;
      },
    },
    preventDefault() { this.prevented = true; },
  };
}

function createDirtySaveEvent(prompt) {
  const choice = {
    dataset: { vmsDirtyChoice: 'save' },
    closest(selector) {
      if (selector === '#vms-event-plan-dirty-prompt') return prompt;
      return null;
    },
  };
  return {
    target: {
      closest(selector) {
        return selector === '[data-vms-dirty-choice]' ? choice : null;
      },
    },
  };
}

function createSectionToggleEvent(section) {
  const toggle = {
    closest(selector) {
      return selector === '.vms-collapsible-section[data-section-key]' ? section : null;
    },
  };
  return {
    prevented: false,
    target: {
      closest(selector) {
        return selector === '.vms-collapsible-toggle' ? toggle : null;
      },
    },
    preventDefault() { this.prevented = true; },
  };
}

function createPrimaryPerformerControl(initialValue, nextValue) {
  const options = [initialValue, nextValue].map((value, index) => ({
    value,
    selected: index === 0,
    defaultSelected: index === 0,
  }));
  return {
    id: 'vms_band_vendor_id',
    name: 'vms_band_vendor_id',
    tagName: 'SELECT',
    type: 'select-one',
    disabled: false,
    dataset: { vmsInitialState: initialValue },
    options,
    matches() { return false; },
    select(value) {
      options.forEach((option) => { option.selected = option.value === value; });
    },
  };
}

function createDirtyStateController() {
  const factory = new Function(
    extractFunction('readControlState') + '\n' +
      extractFunction('controlDirty') + '\n' +
      extractFunction('isTransientActionControl') + '\n' +
      extractFunction('sectionDirty') + '\n' +
      'return { controlDirty: controlDirty, sectionDirty: sectionDirty };'
  );
  return factory();
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

async function assertHandlerKeepsOverlayVisible(description, invokeHandler, expectedTargetKey) {
  const save = deferred();
  const current = createSection('schedule', 'Schedule');
  const destination = createSection('compensation', 'Compensation');
  const saveSnapshots = [];
  const fixture = createTransitionController({
    saveSection: () => save.promise,
    nextWorkflowSection: () => destination,
    onSave: (state) => saveSnapshots.push({ hidden: state.overlay.hidden, className: state.overlay.className }),
  });
  fixture.controller.setDirtyContext(current, destination);

  const transition = invokeHandler(fixture, current, destination);
  await new Promise((resolve) => setImmediate(resolve));

  assert.equal(fixture.document.transitionState().overlay.hidden, false, description + ' makes the overlay visible through the actual click handler.');
  assert.deepEqual(saveSnapshots, [{ hidden: false, className: 'vms-ep-transition-overlay' }], description + ' shows the rendered overlay before the save starts.');
  assert.equal(fixture.controller.isTransitionInFlight(), true, description + ' keeps the interaction lock while the save is unresolved.');
  assert.equal(fixture.calls.filter((call) => call[0] === 'save').length, 1, description + ' routes to exactly one save.');
  assert.deepEqual(fixture.calls.filter((call) => call[0] === 'open'), [], description + ' does not navigate before the save resolves.');

  save.resolve({ ok: true, message: 'Saved.' });
  assert.equal(await transition, true, description + ' completes after the save resolves.');
  const openCalls = fixture.calls.filter((call) => call[0] === 'open');
  if (expectedTargetKey) {
    assert.deepEqual(openCalls, [['open', expectedTargetKey, true]], description + ' opens the expected destination after saving.');
  } else {
    assert.deepEqual(openCalls, [], description + ' does not navigate after saving.');
  }

  assert.equal(fixture.document.transitionState().overlay.hidden, true, description + ' dismisses the overlay after completion.');
  assert.equal(fixture.controller.isTransitionInFlight(), false, description + ' releases the interaction lock after dismissal.');
}

(async () => {
  await assertDuplicateSaveIsBlocked('Dirty Save & Open', true);
  await assertDuplicateSaveIsBlocked('Save & Continue', true);
  await assertDuplicateSaveIsBlocked('Plain Save Changes', false);

  await assertHandlerKeepsOverlayVisible(
    'Plain Save Changes',
    (fixture, current) => fixture.controller.handleSectionActionClick(createSectionActionEvent('save', current)),
    null
  );
  await assertHandlerKeepsOverlayVisible(
    'Save & Continue',
    (fixture, current) => fixture.controller.handleSectionActionClick(createSectionActionEvent('next', current)),
    'compensation'
  );
  await assertHandlerKeepsOverlayVisible(
    'Dirty Save & Open',
    (fixture) => {
      const prompt = { hidden: false };
      return fixture.controller.handleDirtyPromptClick(createDirtySaveEvent(prompt));
    },
    'compensation'
  );

  const cleanHeaderCalls = [];
  const cleanHeaderFixture = createTransitionController({
    openAndFocusSection: async (section, force) => {
      cleanHeaderCalls.push(['open', section.dataset.sectionKey, force]);
      return true;
    },
  });
  const cleanScheduleHeader = createSection('schedule', 'Schedule');
  const cleanHeaderEvent = createSectionToggleEvent(cleanScheduleHeader);
  assert.equal(cleanHeaderFixture.controller.handleSectionToggleClick(cleanHeaderEvent), true, 'The rendered Schedule header reaches the actual delegated toggle handler.');
  assert.equal(cleanHeaderEvent.prevented, true, 'The actual Schedule header handler prevents native button behavior.');
  assert.deepEqual(cleanHeaderCalls, [['open', 'schedule', false]], 'A section-header click routes to clean navigation rather than the save action handler.');
  assert.equal(cleanHeaderFixture.calls.filter((call) => call[0] === 'save').length, 0, 'A clean section-header click does not claim to save the current section.');
  assert.equal(cleanHeaderFixture.document.transitionState().overlay, null, 'A clean section-header click does not create a save overlay.');

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
  await contentFixture.controller.endTransition();
  assert.equal(contentFixture.controller.blockEventDuringTransition({}), false, 'Actions are admitted after the lock releases.');

  const plainFixture = createTransitionController();
  assert.equal(plainFixture.controller.beginTransition(schedule, null), true, 'Plain saves use the same overlay.');
  assert.equal(
    plainFixture.document.transitionState().copy.textContent,
    'Please keep this page open while the section is saved.',
    'Plain saves use non-navigation progress copy.'
  );
  await plainFixture.controller.endTransition();

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

  assert.match(
    timeLineup,
    /<select id="vms_band_vendor_id" name="vms_band_vendor_id"/,
    'The Primary Performer regression uses the real Event Plan control ID and name.'
  );
  const dirtyState = createDirtyStateController();
  const primaryPerformer = createPrimaryPerformerControl('41', '77');
  const basics = createSection('basics', 'Event Details', [primaryPerformer]);
  const scheduleAfterPerformerChange = createSection('schedule', 'Schedule & Lineup');
  assert.equal(dirtyState.sectionDirty(basics.body), false, 'Event Details starts clean before the Primary Performer changes.');
  primaryPerformer.select('77');
  assert.equal(dirtyState.controlDirty(primaryPerformer), true, 'The real Primary Performer select is recognized as dirty after selection changes.');
  assert.equal(dirtyState.sectionDirty(basics.body), true, 'The active Event Details section becomes dirty from the Primary Performer change.');

  const dirtyPromptCalls = [];
  const dirtyNavigationCalls = [];
  const dirtyNavigationController = cleanNavigationFactory(
    basics,
    dirtyState.sectionDirty,
    (current, target) => dirtyPromptCalls.push([current.dataset.sectionKey, target.dataset.sectionKey]),
    (section, collapsed) => dirtyNavigationCalls.push(['collapse', section.dataset.sectionKey, collapsed]),
    () => false,
    async () => true
  );
  const performerSave = deferred();
  const performerSaveSnapshots = [];
  const sharedNavigationCalls = [];
  const performerFixture = createTransitionController({
    saveSection: () => performerSave.promise,
    onSave: (state) => performerSaveSnapshots.push({ hidden: state.overlay.hidden, busy: state.overlay.attributes['aria-busy'] }),
    openAndFocusSection: async (section, force) => {
      sharedNavigationCalls.push([section.dataset.sectionKey, force]);
      return dirtyNavigationController.openSection(section, force);
    },
  });
  const performerNavigationEvent = createSectionToggleEvent(scheduleAfterPerformerChange);
  assert.equal(performerFixture.controller.handleSectionToggleClick(performerNavigationEvent), true, 'Clicking Schedule & Lineup reaches the shared section navigation handler.');
  assert.equal(performerNavigationEvent.prevented, true, 'The Schedule & Lineup click is handled by the Event Plan shell.');
  assert.deepEqual(dirtyPromptCalls, [['basics', 'schedule']], 'The dirty Primary Performer path opens the unsaved-changes prompt.');
  assert.deepEqual(sharedNavigationCalls, [['schedule', false]], 'The dirty navigation attempt uses the shared navigation path without forcing the section open.');

  performerFixture.controller.setDirtyContext(basics, scheduleAfterPerformerChange);
  const performerPrompt = { hidden: false };
  const performerTransition = performerFixture.controller.handleDirtyPromptClick(createDirtySaveEvent(performerPrompt));
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(performerPrompt.hidden, true, 'Save & Open dismisses the dirty prompt as the transition begins.');
  assert.equal(performerFixture.controller.isTransitionInFlight(), true, 'Save & Open acquires the transition lock for the Primary Performer path.');
  assert.equal(performerFixture.document.transitionState().overlay.hidden, false, 'The overlay is visible while the Primary Performer save is pending.');
  assert.deepEqual(performerSaveSnapshots, [{ hidden: false, busy: 'true' }], 'The overlay is shown before the Primary Performer save starts.');
  assert.equal(performerFixture.calls.filter((call) => call[0] === 'save').length, 1, 'The Primary Performer path starts exactly one save.');
  assert.deepEqual(sharedNavigationCalls, [['schedule', false]], 'Schedule does not open before the Primary Performer save succeeds.');

  performerSave.resolve({ ok: true, message: 'Saved.' });
  assert.equal(await performerTransition, true, 'The Primary Performer Save & Open transition succeeds.');
  assert.deepEqual(sharedNavigationCalls, [
    ['schedule', false],
    ['schedule', true],
  ], 'Schedule opens through the shared navigation path after the Primary Performer save succeeds.');
  assert.equal(performerFixture.controller.isTransitionInFlight(), false, 'The Primary Performer transition releases its lock after navigation.');

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
  assert.match(shell, /prompt\.addEventListener\('click', handleDirtyPromptClick\)/, 'The dirty prompt is wired to the exercised save handler.');
  assert.match(shell, /if \(handleSectionActionClick\(event\)\) return;/, 'Section action clicks are wired to the exercised save handler.');
  assert.match(shell, /if \(handleSectionToggleClick\(event\)\) return;/, 'Rendered section headers are wired to the exercised clean-navigation handler.');
  assert.ok((shell.match(/blockEventDuringTransition\(event\)/g) || []).length >= 4, 'Dirty-prompt, click, workflow, and submit entry points share the transition guard.');
  assert.match(shell, /var workflowSubmit[\s\S]*workflowSubmit && blockEventDuringTransition\(event\)/, 'Native workflow submissions are blocked while locked.');
  assert.match(shell, /role="status" aria-live="assertive" aria-atomic="true"/, 'The overlay exposes accessible live status semantics.');
  assert.doesNotMatch(extractFunction('ensureTransitionOverlay'), /<button/, 'The in-flight overlay has no close button.');
  assert.doesNotMatch(shell, /EP-OVERLAY-DIAG-1|overlayDiagnostic|recordOverlayDiagnostic|EP shell diagnostic/, 'Temporary browser diagnostic instrumentation is absent.');
  assert.doesNotMatch(shell, /transitionMinimumVisibleMs/, 'The disproven arbitrary minimum-duration workaround is not retained.');
  assert.match(adminUiAssets, /in_array\(\$environment, array\('local', 'development'\), true\)/, 'File-based shell cache busting is restricted to local/development environments.');
  assert.match(adminUiAssets, /filemtime\(\$asset_path\)/, 'Local Event Plan asset URLs use deterministic file modification time cache busting.');
  assert.match(adminUiAssets, /bvmgr_admin_ui_local_asset_version\('assets\/js\/vms-event-plan-shell\.js'\)/, 'The Event Plan shell uses the local/development file version helper.');
  assert.match(adminUiAssets, /bvmgr_admin_ui_local_asset_version\('assets\/css\/vms-admin\.css'\)/, 'The Event Plan overlay CSS uses the local/development file version helper.');
  assert.match(adminUiAssets, /registered\['bvmgr-admin'\]->ver = \$event_plan_admin_style_version/, 'The registered Event Plan admin stylesheet receives its file-based local version.');
  assert.match(adminUiAssets, /-local-/, 'Local shell URLs use a neutral file-based version suffix.');
  assert.doesNotMatch(adminUiAssets, /ep-overlay-diag/, 'The cache-busting version contains no diagnostic marker.');
  assert.match(css, /\.vms-ep-transition-overlay \{[\s\S]*position: fixed;[\s\S]*inset: 0;/, 'The overlay blocks the full viewport.');
  assert.match(css, /\.vms-ep-transition-overlay\[hidden\] \{\s*display: none;/, 'The overlay can be dismissed after success or failure.');

  console.log('event plan save/navigation overlay contracts: PASS');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
