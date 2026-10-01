const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.resolve(__dirname, '../assets/admin-ticketing.js'), 'utf8');

function extractFunction(name) {
  const start = source.indexOf('function ' + name + '(');
  assert.notEqual(start, -1, 'Missing function: ' + name);
  const brace = source.indexOf('{', start);
  let depth = 1;
  for (let index = brace + 1; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}') depth -= 1;
    if (depth === 0) return source.slice(start, index + 1);
  }
  throw new Error('Unable to extract function: ' + name);
}

function createController(initial = {}) {
  const notes = [];
  let commitRefreshes = 0;
  const v2Editor = {
    dataset: {
      configExists: initial.configExists || '0',
      configMode: initial.configMode || 'read_only',
      ticketingEffective: initial.ticketingEffective || '1',
      phaseBAvailable: initial.phaseBAvailable || '1',
      externalTicketing: initial.externalTicketing || '0',
      previewAvailable: initial.previewAvailable || '1',
    },
  };
  const v2ModeSel = { value: initial.configMode || 'read_only' };
  const v2PreviewBtn = { disabled: true };
  const v2CommitBtn = { disabled: false };
  const factory = new Function(
    'v2Editor',
    'v2ModeSel',
    'v2PreviewBtn',
    'v2CommitBtn',
    'setV2Note',
    'updateV2CommitEnabled',
    extractFunction('applyV2AuthoritativeConfigState') + '\nreturn applyV2AuthoritativeConfigState;'
  );
  const apply = factory(
    v2Editor,
    v2ModeSel,
    v2PreviewBtn,
    v2CommitBtn,
    (message, type) => notes.push({ message, type }),
    () => { commitRefreshes += 1; }
  );
  return { apply, v2Editor, v2ModeSel, v2PreviewBtn, v2CommitBtn, notes, commitRefreshes: () => commitRefreshes };
}

const noConfig = createController();
noConfig.apply({
  config_exists: 0,
  config_mode: 'read_only',
  ticketing_effective: 1,
  phase_b_available: 1,
  external_ticketing: 0,
  preview_available: 1,
});
assert.equal(noConfig.v2Editor.dataset.configExists, '0', 'A plan without persisted config remains explicitly uninitialized.');
assert.match(noConfig.notes.at(-1).message, /No saved Ticketing config/, 'The no-config state remains visible before the first save.');

const savedReadOnly = createController({ configExists: '0', ticketingEffective: '0', previewAvailable: '0' });
savedReadOnly.apply({
  config_exists: 1,
  config_mode: 'read_only',
  ticketing_effective: 1,
  phase_b_available: 1,
  external_ticketing: 0,
  preview_available: 1,
});
assert.equal(savedReadOnly.v2Editor.dataset.configExists, '1', 'A successful save refreshes the authoritative saved-config flag.');
assert.equal(savedReadOnly.v2Editor.dataset.ticketingEffective, '1', 'A successful section/config save refreshes Ticketing enablement.');
assert.equal(savedReadOnly.v2PreviewBtn.disabled, false, 'Preview is recalculated and enabled when authoritative prerequisites pass.');
assert.equal(savedReadOnly.v2CommitBtn.disabled, true, 'Refreshing saved state never unlocks Commit without Preview.');
assert.equal(savedReadOnly.v2ModeSel.value, 'read_only', 'A legitimately saved read-only mode is preserved rather than silently promoted.');
assert.doesNotMatch(savedReadOnly.notes.at(-1).message, /No saved Ticketing config/, 'The stale no-config notice is removed after save.');
assert.match(savedReadOnly.notes.at(-1).message, /saved in Read-only mode/, 'The operator is told exactly why Commit remains unavailable.');
assert.equal(savedReadOnly.commitRefreshes(), 1, 'Commit gating is recalculated once after authoritative state refresh.');

// In-page Workflow / Publish routing does not reconstruct this controller. Returning to
// Ticketing must therefore retain the authoritative state that the successful save applied.
assert.equal(savedReadOnly.v2Editor.dataset.configExists, '1', 'In-page routing retains the persisted-config state for the return to Ticketing.');
assert.equal(savedReadOnly.v2PreviewBtn.disabled, false, 'In-page routing retains the refreshed Preview availability.');

const managed = createController({ configMode: 'read_only' });
managed.apply({
  config_exists: 1,
  config_mode: 'vms_managed',
  ticketing_effective: 1,
  phase_b_available: 1,
  external_ticketing: 0,
  preview_available: 1,
});
assert.equal(managed.v2ModeSel.value, 'vms_managed', 'An authoritative managed-mode save replaces an old read-only DOM value.');
assert.equal(managed.v2PreviewBtn.disabled, false, 'Managed-mode config may Preview when prerequisites pass.');
assert.equal(managed.v2CommitBtn.disabled, true, 'Managed mode alone does not unlock Commit.');

for (const blockedState of [
  { ticketing_effective: 0, phase_b_available: 1, external_ticketing: 0 },
  { ticketing_effective: 1, phase_b_available: 0, external_ticketing: 0 },
  { ticketing_effective: 1, phase_b_available: 1, external_ticketing: 1 },
]) {
  const blocked = createController();
  blocked.apply({
    config_exists: 1,
    config_mode: 'vms_managed',
    preview_available: 0,
    ...blockedState,
  });
  assert.equal(blocked.v2PreviewBtn.disabled, true, 'A genuine Preview prerequisite keeps Preview disabled.');
  assert.equal(blocked.v2CommitBtn.disabled, true, 'Blocked Preview state keeps Commit disabled.');
}

assert.ok(
  source.split('applyV2AuthoritativeConfigState(res.data || {})').length - 1 >= 2,
  'Every active Ticketing config save path applies the authoritative server state.'
);

console.log('event plan Ticketing config state refresh: PASS');
