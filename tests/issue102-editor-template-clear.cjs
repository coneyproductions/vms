'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = process.argv[2] || path.join(__dirname, '../backstage-venue-manager');
const source = fs.readFileSync(path.join(root, 'assets/admin-ticketing.js'), 'utf8');

async function run() {
  for (const operation of ['apply_template', 'clear_config']) {
    const original = '{"entitlements":[{"more_info":"Unsaved notes"}]}';
    const editor = { dataset: { initialConfig: original, configRevision: 'old-revision', configExists: '1' } };
    const messages = [];
    let applied = 0;
    let callback;
    let result = { success: false, data: { code: 'config_revision_conflict', message: 'Compare the saved plan in another tab.' } };
    const button = { disabled: false, addEventListener: (event, handler) => { callback = handler; } };
    const context = {
      v2Editor: editor, planId: 901, v2TplApplyBtn: button, v2TplClearBtn: button,
      v2TemplateGuardrailWrap: null,
      window: { confirm: () => true },
      sessionStorage: { setItem() {}, removeItem() {} },
      getCurrentShowDatetimeForServer: () => '',
      setV2Msg: (message, type) => messages.push([message, type]),
      parseJSONAttr: JSON.parse,
      finalizeTemplateApply: () => { ++applied; },
      renderV2: () => { ++applied; },
      clearGuardrail() {}, clearV2PreviewState() {}, setV2Note() {},
      postJSON: async (action, payload) => {
        assert.equal(action, 'vms_ticketing_v2_' + operation);
        assert.equal(payload.config_revision, 'old-revision');
        return result;
      },
    };
    let invoke;
    if (operation === 'apply_template') {
      const match = source.match(/^  function applyTemplateToPlan\(templateId, options\) \{[\s\S]*?^  \}/m);
      assert.ok(match);
      const apply = vm.runInNewContext(match[0] + '\napplyTemplateToPlan', context);
      invoke = () => apply('fixture', {});
    } else {
      const start = source.indexOf('  if (v2TplClearBtn && v2Editor && planId > 0) {');
      const end = source.indexOf('  if (v2InitLegacyBtn', start);
      assert.ok(start > 0 && end > start);
      vm.runInNewContext(source.slice(start, end), context);
      invoke = async () => {
        callback();
        await new Promise(resolve => setImmediate(resolve));
      };
    }
    await invoke();
    assert.equal(editor.dataset.initialConfig, original);
    assert.equal(editor.dataset.configRevision, 'old-revision');
    assert.equal(editor.dataset.configExists, '1');
    assert.equal(applied, 0);
    assert.equal(button.disabled, false);
    assert.deepEqual(messages.at(-1), [result.data.message, 'error']);
    console.log('PASS ' + operation + ' sends revision and preserves unsaved editor work on conflict');

    result = { success: true, data: { config: {}, config_revision: 'verified-revision' } };
    await invoke();
    assert.equal(editor.dataset.configRevision, 'verified-revision');
    assert.equal(applied, 1);
    assert.equal(button.disabled, false);
    if (operation === 'clear_config') {
      assert.equal(editor.dataset.initialConfig, '{}');
      assert.equal(editor.dataset.configExists, '0');
    }
    console.log('PASS ' + operation + ' adopts the successful revision and updates the editor');
  }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
