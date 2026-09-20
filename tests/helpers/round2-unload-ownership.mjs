import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const ticketing = fs.readFileSync(path.join(root, 'assets/admin-ticketing.js'), 'utf8');
const title = fs.readFileSync(path.join(root, 'assets/js/vms-event-plan-title.js'), 'utf8');
let checks = 0;
function fixture() {
  const listeners = []; const submits = []; const delays = []; const navigations = [];
  const foreign = () => 'Foreign editor has unsaved changes';
  const post = { dataset: {}, addEventListener: (type, fn) => { if (type === 'submit') submits.push(fn); } };
  const window = { BVMGR_TICKETING: {planId: 23, editUrlBase: '/wp-admin/post.php?post='}, onbeforeunload: foreign,
    addEventListener: (type, fn, capture) => listeners.push({type,fn,capture}),
    setTimeout: (fn, delay) => { delays.push(delay); fn(); },
    location: { href: '/wp-admin/post.php?post=23', hash: '', reload: () => navigations.push('reload') } };
  const document = {getElementById: id => id === 'post' ? post : null, querySelector: () => null, readyState: 'complete'};
  const context = vm.createContext({window, document});
  function verify(label) {
    assert.equal(window.onbeforeunload, foreign, label + ': foreign property handler preserved'); checks++;
    const event = { stopped: false, preventDefault() {}, stopImmediatePropagation() { this.stopped=true; } };
    for (const x of listeners.filter(x => x.type === 'beforeunload')) x.fn(event);
    assert.equal(event.stopped, false, label + ': foreign listener propagation preserved'); checks++;
    assert.equal(window.onbeforeunload(), 'Foreign editor has unsaved changes', label + ': foreign warning preserved'); checks++;
  }
  return {window,document,context,submits,delays,navigations,verify};
}
// Run the actual complete title controller, including any registered submit handler.
const t=fixture(); vm.runInContext(title,t.context); for(const fn of t.submits)fn({});t.verify('Title form submission');
// Run the actual ticketing prologue and navigation functions. The rest of the
// ticket editor is intentionally not simulated by this navigation fixture.
const end=ticketing.indexOf('  function persistRequestedSectionTarget(');
assert.ok(end>0,'Navigation source boundary exists');
const navigation=ticketing.slice(0,end)+'globalThis.navigation = {safeReload, goToPlanEdit};})();';
for (const action of ['reload-now','reload-delayed','edit-plan']) {
 const f=fixture();vm.runInContext(navigation,f.context);
 if(action==='edit-plan') {f.context.navigation.goToPlanEdit(23);assert.equal(f.window.location.href,'/wp-admin/post.php?post=23&action=edit#vms_event_plan_advanced_controls');}
 else {f.context.navigation.safeReload(action==='reload-delayed'?250:0);assert.deepEqual(f.navigations,['reload']);}
 checks++;f.verify(action);
}
console.log(`PASS ${checks} actual JavaScript navigation and foreign unload-handler checks`);
