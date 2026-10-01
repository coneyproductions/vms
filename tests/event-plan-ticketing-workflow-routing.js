const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const shell = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js'), 'utf8');
const ticketing = fs.readFileSync(path.resolve(__dirname, '../assets/admin-ticketing.js'), 'utf8');
const workspaceStatus = fs.readFileSync(path.resolve(__dirname, '../includes/cpt/event-plans/partials/workspace-status.php'), 'utf8');
const adminUiAssets = fs.readFileSync(path.resolve(__dirname, '../includes/admin-ui/assets.php'), 'utf8');

function extractFunction(source, name) {
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
    body: { appendChild(node) { overlay = node; } },
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
    state() { return { overlay, title, copy }; },
  };
}

function createSection(key, label) {
  const body = {};
  const feedback = { textContent: '' };
  return {
    dataset: { sectionKey: key },
    classList: { add() {}, remove() {} },
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

function createStatusRoot() {
  const workflowButton = {
    focused: 0,
    focus() { this.focused += 1; },
  };
  return {
    dataset: { vmsWorkflowDestinationLabel: 'Workflow / Publish' },
    querySelector(selector) {
      if (selector === '[data-vms-workflow-action]:not([disabled])') return workflowButton;
      if (selector === '[data-vms-workflow-action]') return workflowButton;
      return null;
    },
    workflowButton,
  };
}

function createSaveController(statusRoot, saveSection, calls) {
  const document = createDocument();
  const factory = new Function(
    'document',
    'statusRoot',
    'saveSection',
    'openAndFocusDestination',
    'setCollapsed',
    'setSectionStatus',
    'canonicalizePersistedEventPlanUrl',
    'resetSectionBaseline',
    'applyLockPayState',
    'applyCanonicalReadinessState',
    'applyAuthoritativeDerivedState',
    'sectionTransientDirty',
    'form',
    'cssEscapeValue',
    'resolveRequestedDestination',
    'resolveWorkflowPublishDestination',
    'var transitionInFlight = false;\n' +
      'var transitionNavigationAllowed = false;\n' +
      'var transitionNavigationSection = null;\n' +
      'var continuationSectionOrder = ["basics", "schedule", "compensation", "secondary_vendors", "staff", "ticketing_v2", "workflow_publish"];\n' +
      extractFunction(shell, 'nextWorkflowSection') + '\n' +
      extractFunction(shell, 'sectionLabel') + '\n' +
      extractFunction(shell, 'ensureTransitionOverlay') + '\n' +
      extractFunction(shell, 'beginTransition') + '\n' +
      extractFunction(shell, 'endTransition') + '\n' +
      extractFunction(shell, 'saveAndMaybeOpen') + '\n' +
      'return {' +
        'nextWorkflowSection: nextWorkflowSection,' +
        'saveAndMaybeOpen: saveAndMaybeOpen,' +
        'isLocked: function () { return transitionInFlight; }' +
      '};'
  );
  const controller = factory(
    document,
    statusRoot,
    async (section) => {
      calls.push(['save', section.dataset.sectionKey, document.state().overlay ? document.state().overlay.hidden : null]);
      return saveSection(section);
    },
    async (target, force) => {
      calls.push(['open', target === statusRoot ? 'workflow_publish' : target.dataset.sectionKey, force]);
      return true;
    },
    (section, collapsed) => calls.push(['collapse', section.dataset.sectionKey, collapsed]),
    (section, label, state) => calls.push(['status', section.dataset.sectionKey, label, state]),
    (url, key) => calls.push(['canonicalize', url || '', key]),
    (section) => calls.push(['baseline', section.dataset.sectionKey]),
    () => true,
    () => true,
    () => true,
    () => false,
    { querySelector: () => null },
    (value) => String(value || ''),
    (key) => key === 'workflow_publish' ? statusRoot : null,
    () => statusRoot
  );
  return { controller, document };
}

(async () => {
  const statusRoot = createStatusRoot();
  const ticketingSection = createSection('ticketing_v2', 'Ticketing');
  const destinationCalls = [];
  const destinationFactory = new Function(
    'statusRoot',
    'resolveWorkflowPublishDestination',
    'initialActiveSection',
    'sectionDirty',
    'showDirtyPrompt',
    'setCollapsed',
    'persistRequestedSection',
    'waitForSectionLayout',
    'scrollSectionWrapperIntoWorkingPosition',
    'openAndFocusSection',
    'var activeSection = initialActiveSection;\n' +
      'var transitionInFlight = false;\n' +
      'var transitionNavigationAllowed = false;\n' +
      'var transitionNavigationSection = null;\n' +
      'var lastTouchedSectionKey = "";\n' +
      extractFunction(shell, 'openWorkflowPublish') + '\n' +
      extractFunction(shell, 'openAndFocusWorkflowPublish') + '\n' +
      extractFunction(shell, 'openAndFocusDestination') + '\n' +
      'return { openAndFocusWorkflowPublish, openAndFocusDestination };'
  );
  const destinationController = destinationFactory(
    statusRoot,
    () => statusRoot,
    ticketingSection,
    () => false,
    () => destinationCalls.push(['prompt']),
    (section, collapsed) => destinationCalls.push(['collapse', section.dataset.sectionKey, collapsed]),
    (key) => destinationCalls.push(['persist', key]),
    async (target) => destinationCalls.push(['layout', target === statusRoot ? 'workflow_publish' : 'other']),
    (target) => destinationCalls.push(['scroll', target === statusRoot ? 'workflow_publish' : 'other']),
    async () => false
  );
  assert.equal(await destinationController.openAndFocusDestination(statusRoot, false), true, 'Workflow / Publish opens through the shared destination-aware path.');
  assert.deepEqual(destinationCalls, [
    ['collapse', 'ticketing_v2', true],
    ['persist', 'workflow_publish'],
    ['layout', 'workflow_publish'],
    ['scroll', 'workflow_publish'],
  ], 'The shared path closes Ticketing, persists the destination, settles layout, and scrolls to Workflow / Publish.');
  assert.equal(statusRoot.workflowButton.focused, 1, 'The shared path focuses an actionable Workflow / Publish control.');

  const pendingSave = deferred();
  const calls = [];
  const fixture = createSaveController(statusRoot, () => pendingSave.promise, calls);
  const workflowDestination = fixture.controller.nextWorkflowSection(ticketingSection);
  assert.equal(workflowDestination, statusRoot, 'Ticketing Save & Continue resolves to the Workflow / Publish component.');
  const transition = fixture.controller.saveAndMaybeOpen(ticketingSection, workflowDestination);
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(fixture.controller.isLocked(), true, 'Ticketing continuation acquires the transition lock.');
  assert.equal(fixture.document.state().overlay.hidden, false, 'Ticketing continuation shows the overlay before saving.');
  assert.equal(fixture.document.state().copy.textContent, 'Your changes are being saved before Workflow / Publish opens.', 'The operator-facing destination label is Workflow / Publish.');
  assert.deepEqual(calls.filter((call) => call[0] === 'save'), [['save', 'ticketing_v2', false]], 'Ticketing saves exactly once with the overlay already visible.');
  assert.deepEqual(calls.filter((call) => call[0] === 'open'), [], 'Workflow / Publish does not open before Ticketing save succeeds.');
  assert.equal(await fixture.controller.saveAndMaybeOpen(ticketingSection, workflowDestination), false, 'The transition lock blocks duplicate Ticketing continuation.');
  assert.equal(calls.filter((call) => call[0] === 'save').length, 1, 'A duplicate continuation does not start another Ticketing save.');
  pendingSave.resolve({ ok: true, message: 'Saved.' });
  assert.equal(await transition, true, 'Successful Ticketing continuation completes.');
  assert.deepEqual(calls.filter((call) => call[0] === 'open'), [['open', 'workflow_publish', true]], 'Workflow / Publish opens through the shared navigation path after save success.');
  assert.equal(fixture.controller.isLocked(), false, 'The Ticketing transition lock releases after navigation.');

  const failedCalls = [];
  const failedFixture = createSaveController(statusRoot, async () => ({ ok: false, message: 'Ticketing save failed.' }), failedCalls);
  assert.equal(await failedFixture.controller.saveAndMaybeOpen(ticketingSection, statusRoot), false, 'A failed Ticketing save does not continue.');
  assert.deepEqual(failedCalls.filter((call) => call[0] === 'open'), [], 'A failed Ticketing save leaves the operator in Ticketing.');
  assert.deepEqual(failedCalls.filter((call) => call[0] === 'collapse'), [['collapse', 'ticketing_v2', false]], 'Failure keeps Ticketing expanded and usable.');

  let nativeCommitQueries = 0;
  const externalSection = {
    dataset: { sectionKey: 'ticketing_v2' },
    classList: { add() {}, remove() {} },
    querySelector(selector) {
      if (selector === '#vms-ticketing-v2-save-config-btn') return null;
      if (selector === '#vms-ticketing-v2-commit-sync-btn') nativeCommitQueries += 1;
      return null;
    },
    querySelectorAll() {
      return [{
        disabled: false,
        name: 'vms_ticketing_sales_mode',
        type: 'select-one',
        tagName: 'SELECT',
        multiple: false,
        value: 'external',
      }];
    },
  };
  const externalSaveFactory = new Function(
    'window',
    'document',
    'setSectionStatus',
    'var sectionSaveUrl = "https://serenaderange.local/wp-admin/admin-ajax.php";\n' +
      'var sectionSaveNonce = "nonce";\n' +
      'var postId = 123;\n' +
      extractFunction(shell, 'waitForSpecialSave') + '\n' +
      extractFunction(shell, 'serializeSection') + '\n' +
      extractFunction(shell, 'saveSection') + '\n' +
      'return saveSection;'
  );
  const externalSave = externalSaveFactory(
    {
      fetch: async () => ({
        ok: true,
        json: async () => ({ success: true, data: { message: 'Saved.' } }),
      }),
      setTimeout,
      clearTimeout,
    },
    { addEventListener() {}, removeEventListener() {} },
    () => {}
  );
  const externalResult = await externalSave(externalSection);
  assert.equal(externalResult.ok, true, 'External-ticketing scoped save succeeds without the native editor.');
  assert.equal(nativeCommitQueries, 0, 'External-ticketing Save & Continue does not inspect or invoke native ticket publication.');
  const externalCalls = [];
  const externalFixture = createSaveController(statusRoot, () => externalSave(externalSection), externalCalls);
  assert.equal(
    await externalFixture.controller.saveAndMaybeOpen(externalSection, externalFixture.controller.nextWorkflowSection(externalSection)),
    true,
    'External-ticketing Save & Continue succeeds through the scoped save path.'
  );
  assert.deepEqual(
    externalCalls.filter((call) => call[0] === 'open'),
    [['open', 'workflow_publish', true]],
    'External-ticketing Save & Continue routes to Workflow / Publish.'
  );
  assert.equal(nativeCommitQueries, 0, 'External-ticketing continuation never invokes native ticket publication.');

  const commitCalls = [];
  const completeCommit = new Function(
    'setV2Msg',
    'setBusyTitle',
    'persistRequestedSectionTarget',
    'safeReload',
    extractFunction(ticketing, 'completeV2CommitNavigation') + '\nreturn completeV2CommitNavigation;'
  )(
    (message, type) => commitCalls.push(['message', message, type]),
    (busy) => commitCalls.push(['busy', busy]),
    (key) => commitCalls.push(['persist', key]),
    (delay) => commitCalls.push(['reload', delay])
  );
  completeCommit();
  assert.deepEqual(commitCalls, [
    ['message', 'Sync complete. Opening Workflow / Publish…', 'success'],
    ['busy', false],
    ['persist', 'workflow_publish'],
    ['reload', 0],
  ], 'Successful native Ticketing commit reloads into Workflow / Publish only after completion.');

  assert.match(workspaceStatus, /data-vms-workflow-destination-label="<\?php esc_attr_e\('Workflow \/ Publish'/, 'The destination exposes the operator-facing Workflow / Publish label.');
  assert.match(workspaceStatus, />\<\?php esc_html_e\('Workflow \/ Publish'/, 'The destination visibly identifies itself as Workflow / Publish.');
  assert.doesNotMatch(workspaceStatus, />\s*(readiness_details|workflow_status)\s*</i, 'The destination does not expose internal keys to operators.');
  assert.match(adminUiAssets, /bvmgr_admin_ui_local_asset_version\('assets\/admin-ticketing\.js'\)/, 'Local/development Ticketing routing changes receive deterministic cache busting.');

  console.log('event plan Ticketing to Workflow / Publish routing: PASS');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
