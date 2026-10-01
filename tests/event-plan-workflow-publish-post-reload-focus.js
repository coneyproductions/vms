const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const shell = fs.readFileSync(path.resolve(__dirname, '../assets/js/vms-event-plan-shell.js'), 'utf8');
const workspaceStatus = fs.readFileSync(path.resolve(__dirname, '../includes/cpt/event-plans/partials/workspace-status.php'), 'utf8');

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

function makeResolver(workflowDestination, sectionDestination) {
  const factory = new Function(
    'normalizeRequestedSectionKey',
    'resolveWorkflowPublishDestination',
    'form',
    'cssEscapeValue',
    extractFunction('resolveRequestedDestination') + '\nreturn resolveRequestedDestination;'
  );
  return factory(
    (key) => String(key || ''),
    () => workflowDestination,
    { querySelector: () => sectionDestination },
    (value) => String(value || '')
  );
}

async function exerciseLayoutSettlement() {
  const listeners = {};
  const frames = [];
  const fakeDocument = { readyState: 'loading' };
  const fakeWindow = {
    addEventListener(type, handler) { listeners[type] = handler; },
    requestAnimationFrame(handler) { frames.push(handler); },
    setTimeout,
  };
  const waitFactory = new Function(
    'document',
    'window',
    extractFunction('waitForEventPlanWorkspaceReady') + '\n' +
      extractFunction('waitForSectionLayout') + '\n' +
      'return waitForSectionLayout;'
  );
  const waitForLayout = waitFactory(fakeDocument, fakeWindow);
  const positions = [900, 120, 120, 120];
  let measurements = 0;
  const target = {
    getBoundingClientRect() {
      const top = positions[Math.min(measurements, positions.length - 1)];
      measurements += 1;
      return { top, left: 40, width: 720, height: 150 };
    },
  };

  let settled = false;
  const pending = waitForLayout(target).then(() => { settled = true; });
  await Promise.resolve();
  assert.equal(frames.length, 0, 'Destination measurement waits for the page-load lifecycle.');
  assert.equal(settled, false, 'Destination focus cannot complete before page load.');

  listeners.load();
  await Promise.resolve();
  while (frames.length) {
    frames.shift()();
    await Promise.resolve();
  }
  await pending;
  assert.equal(measurements, 4, 'Layout settlement observes relocation and two consecutive stable frames.');
}

async function exerciseWorkflowFocus(workflowDestination) {
  const calls = [];
  const action = { focus(options) { calls.push(['focus', options]); } };
  workflowDestination.querySelector = (selector) => selector.includes('data-vms-workflow-action') ? action : null;
  const factory = new Function(
    'resolveWorkflowPublishDestination',
    'openWorkflowPublish',
    'persistRequestedSection',
    'waitForSectionLayout',
    'scrollSectionWrapperIntoWorkingPosition',
    extractFunction('openAndFocusWorkflowPublish') + '\nreturn openAndFocusWorkflowPublish;'
  );
  const focusWorkflow = factory(
    () => workflowDestination,
    async (force) => { calls.push(['open', force]); return true; },
    (key) => calls.push(['persist', key]),
    async (target) => calls.push(['settle', target]),
    (target) => calls.push(['scroll', target])
  );

  assert.equal(await focusWorkflow(true), true, 'Workflow / Publish focus succeeds.');
  assert.deepEqual(calls.slice(0, 4), [
    ['open', true],
    ['persist', 'workflow_publish'],
    ['settle', workflowDestination],
    ['scroll', workflowDestination],
  ], 'Workflow focus waits for the destination layout before scrolling the exact component.');
  assert.deepEqual(calls[4], ['focus', { preventScroll: true }], 'The actionable control receives focus without undoing the settled scroll.');
}

function exerciseOffsetScroll() {
  const scrolls = [];
  const fakeWindow = {
    pageYOffset: 500,
    scrollTo(options) { scrolls.push(options); },
  };
  const fakeDocument = {
    getElementById(id) {
      return id === 'wpadminbar' ? { getBoundingClientRect: () => ({ height: 32 }) } : null;
    },
  };
  const factory = new Function(
    'document',
    'window',
    extractFunction('scrollSectionWrapperIntoWorkingPosition') + '\nreturn scrollSectionWrapperIntoWorkingPosition;'
  );
  factory(fakeDocument, fakeWindow)({ getBoundingClientRect: () => ({ top: 120 }) });
  assert.deepEqual(scrolls, [{ top: 572, behavior: 'smooth' }], 'Shared scrolling accounts for the admin bar and working-position margin.');
}

(async () => {
  const workflowDestination = { dataset: { vmsWorkflowDestinationLabel: 'Workflow / Publish' } };
  const sectionDestination = { dataset: { sectionKey: 'schedule' } };
  const resolveDestination = makeResolver(workflowDestination, sectionDestination);

  assert.equal(resolveDestination('workflow_publish'), workflowDestination, 'workflow_publish resolves to the actual Workflow / Publish component.');
  assert.equal(resolveDestination('schedule'), sectionDestination, 'Ordinary collapsible sections continue through the shared resolver.');
  assert.match(workspaceStatus, /id="vms-event-plan-workspace-status"[\s\S]*data-vms-workflow-publish-destination/, 'The resolved target is the stable Workflow / Publish component.');
  assert.doesNotMatch(workspaceStatus, /Credential Grants|ADD Responses|vms-collapsible-section/, 'The focus target does not wrap unrelated lower Event Plan sections.');

  await exerciseLayoutSettlement();
  await exerciseWorkflowFocus(workflowDestination);
  exerciseOffsetScroll();

  assert.match(shell, /inside\.insertBefore\(statusRoot, inside\.firstChild\)/, 'The shell explicitly relocates the server-rendered destination before focus.');
  assert.doesNotMatch(extractFunction('waitForSectionLayout'), /setTimeout\([^,]+,\s*(75|150|250)\)/, 'Layout settlement does not use an arbitrary fixed focus delay.');

  console.log('event plan Workflow / Publish post-reload focus: PASS');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
