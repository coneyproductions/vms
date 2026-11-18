'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const plugin = process.argv[2] || path.join(__dirname, '../backstage-venue-manager');
const source = fs.readFileSync(path.join(plugin, 'assets/admin-ticketing.js'), 'utf8');
const saveFunction = source.match(/^  function saveV2Config\(opts\) \{[\s\S]*?^  \}/m);
assert.ok(saveFunction, 'Save Config function exists');

async function run() {
  const oldConfig = { entitlements: [{ short_desc: 'Original A' }] };
  const edits = { entitlements: [{ short_desc: 'Original A', more_info: 'Unsaved editor A notes' }] };
  const editor = { dataset: { configRevision: 'original-revision', initialConfig: JSON.stringify(oldConfig), configExists: '1' } };
  const messages = [];
  let successEffects = 0;
  let result;
  const context = {
    v2Editor: editor,
    planId: 901,
    getV2ConfigFromUI: () => edits,
    setV2Msg: (message, type) => messages.push([message, type]),
    humanizeV2Message: (message) => message,
    persistRequestedSectionTarget: () => ++successEffects,
    clearV2PreviewState: () => ++successEffects,
    renderV2: () => { throw new Error('Minimal responses must leave the live editor fields alone'); },
    postJSON: async (action, payload) => {
      assert.equal(action, 'vms_ticketing_v2_save_config');
      assert.equal(payload.config_revision, 'original-revision');
      assert.equal(payload.config, edits);
      assert.equal(payload.return_config, 0);
      return result;
    },
  };
  const save = vm.runInNewContext(saveFunction[0] + '\nsaveV2Config', context);
  const message = 'Your unsaved edits are still here.';
  result = { success: false, data: { message, code: 'config_revision_conflict' } };

  for (const quiet of [false, true]) {
    await assert.rejects(save({ quiet }), { message });
    assert.deepEqual(messages.at(-1), [message, 'error']);
    assert.equal(editor.dataset.configRevision, 'original-revision');
    assert.equal(editor.dataset.initialConfig, JSON.stringify(oldConfig));
    assert.equal(edits.entitlements[0].more_info, 'Unsaved editor A notes');
    assert.equal(successEffects, 0);
  }
  console.log('PASS regular and quiet saves retain unsaved input and display the conflict');

  result = { success: true, data: { config_revision: 'verified-revision' } };
  assert.equal(await save({ quiet: true }), edits);
  assert.equal(editor.dataset.configRevision, 'verified-revision');
  assert.equal(editor.dataset.initialConfig, JSON.stringify(edits));
  assert.equal(successEffects, 2);
  console.log('PASS minimal successful saves adopt the verified revision and preserve the editor fields');
}

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
