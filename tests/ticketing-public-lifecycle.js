'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {page: fixture} = require('./ticketing-public-fixture.cjs');
const playwrightRoot = process.env.BVM_PLAYWRIGHT_ROOT;
if (!playwrightRoot) throw new Error('BVM_PLAYWRIGHT_ROOT must point to a node_modules directory containing playwright-core.');
const playwright = require(path.join(playwrightRoot, 'playwright-core'));
const assetRoot = process.env.BVM_TICKETING_ASSET_ROOT || path.join(__dirname, '../assets');
const executablePath = process.env.BVM_TEST_EXECUTABLE || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const source = name => fs.readFileSync(path.join(assetRoot, name), 'utf8');
const controllerNames = ['vms-ticketing-front.js', 'vms-ticketing-front-fallback.js', 'vms-ticketing-progressive-ui.js'];
const assetNames = ['vms-ticketing-post-cart-offer.js', ...controllerNames];
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }

async function installRoutes(page, mode, layout, requests, failRef) {
  await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.pathname === '/event/') return route.fulfill({body: fixture(mode, layout), contentType: 'text/html'});
    if (url.pathname.startsWith('/assets/')) {
      const name = path.basename(url.pathname);
      let text = source(name);
      if (name === 'vms-ticketing-front.js') {
        for (const fn of ['refresh', 'boot', 'init']) {
          text = text.replace(new RegExp('(function ' + fn + '\\([^)]*\\) \\{)'), `$1 window.__test.calls['${name}:${fn}']=(window.__test.calls['${name}:${fn}']||0)+1;`);
        }
        text = text.replace('  installPageShowReset();', '  if (window.__test) { window.__test.refresh = refresh; window.__test.init = init; }\n  installPageShowReset();');
      }
      return route.fulfill({body: text, contentType: 'application/javascript'});
    }
    requests.push({path: url.pathname, method: route.request().method(), body: route.request().postData()});
    if (url.pathname === '/api/cart-context') return route.fulfill({json: {success: true, data: {ga_qty: 0, prior_qualifying_qty: 0, pool_qty_by_key: {}, prior_pool_qty_by_key: {}}}});
    if (url.pathname === '/api/validate-assignee') return route.fulfill({json: {success: true, data: {ok: true, assignee_user_id: 42, assignee_email: 'guest@example.test', message: 'Guest approved.'}}});
    if (url.pathname === '/api/atomic-add') {
      if (failRef.value) { failRef.value = false; return route.fulfill({json: {success: false, data: {message: 'Test cart error'}}}); }
      return route.fulfill({json: {success: true, data: {ok: true, cart_url: '/cart/'}}});
    }
    if (url.pathname === '/cart/') return route.fulfill({body: '<h1>Test cart</h1>', contentType: 'text/html'});
    throw new Error('Unexpected network request: ' + route.request().url());
  });
}

async function runLifecycle(browser, mode, contextOptions, label) {
  const context = await browser.newContext(contextOptions);
  const page = await context.newPage();
  page.setDefaultTimeout(7000);
  const requests = [];
  const errors = [];
  const failRef = {value: false};
  page.on('pageerror', error => errors.push(String(error)));
  await page.addInitScript(() => {
    window.__test = {calls: {}, observers: 0, callbacks: 0, listeners: 0, mutations: 0, childMutations: 0};
    const NativeObserver = window.MutationObserver;
    window.MutationObserver = class extends NativeObserver {
      constructor(cb) { window.__test.observers++; super((records, observer) => { window.__test.callbacks++; cb(records, observer); }); }
    };
    new NativeObserver(records => {
      window.__test.mutations += records.length;
      window.__test.childMutations += records.filter(record => record.type === 'childList').length;
    }).observe(document, {subtree: true, childList: true, attributes: true});
    const add = EventTarget.prototype.addEventListener;
    EventTarget.prototype.addEventListener = function (...args) { window.__test.listeners++; return add.apply(this, args); };
  });
  await installRoutes(page, mode, 'progressive', requests, failRef);
  await page.goto('https://ticketing.example.test/event/');
  const qty = id => page.locator(`#tribe-tickets__tickets-item-quantity-number--${id}`);
  const row = id => page.locator(`#tribe-block-tickets-item-${id}`);
  const details = id => row(id).locator('.vms-qualified-ticket-more-info');
  await page.waitForFunction(() => document.querySelectorAll('.vms-qualified-ticket-more-info').length === 2);
  await sleep(350);

  const sample = () => page.evaluate(() => ({calls: {...window.__test.calls}, mutations: window.__test.mutations, callbacks: window.__test.callbacks, nodes: document.querySelectorAll('*').length}));
  const idleStart = await sample(); await sleep(900); const idleEnd = await sample();
  check(JSON.stringify(idleStart) === JSON.stringify(idleEnd), `${label}/${mode}: page settles when idle`);

  await details(6998).locator('summary').click();
  await details(6999).locator('summary').click();
  check(await details(6998).evaluate(node => node.open), `${label}/${mode}: first disclosure remains open`);
  check(await details(6999).evaluate(node => node.open), `${label}/${mode}: second disclosure opens independently`);
  await page.evaluate(() => { window.__savedDetails = [...document.querySelectorAll('.vms-qualified-ticket-more-info')]; });

  const bindStart = await page.evaluate(() => ({listeners: window.__test.listeners, observers: window.__test.observers}));
  for (const name of controllerNames) await page.evaluate(source(name));
  const bindEnd = await page.evaluate(() => ({listeners: window.__test.listeners, observers: window.__test.observers}));
  check(JSON.stringify(bindStart) === JSON.stringify(bindEnd), `${label}/${mode}: duplicate bundles add no listeners or observers`);
  await page.evaluate(() => { for (let i = 0; i < 10; i++) window.__test.init(); });
  await sleep(150);
  check(await page.evaluate(() => window.__test.listeners) === bindEnd.listeners, `${label}/${mode}: repeated init adds no listeners`);
  await page.evaluate(() => { for (let i = 0; i < 15; i++) window.__test.refresh(window.BVMGR_TICKETING_FRONT_BUNDLE.state, {clampAddons: true}); });
  await sleep(200);
  check(await page.evaluate(() => window.__savedDetails.every((node, index) => node === document.querySelectorAll('.vms-qualified-ticket-more-info')[index] && node.open)), `${label}/${mode}: refresh retains disclosure nodes and state`);
  await page.evaluate(() => { window.__beforeChildren = window.__test.childMutations; window.__test.refresh(window.BVMGR_TICKETING_FRONT_BUNDLE.state, {clampAddons: true}); });
  await sleep(80);
  check(await page.evaluate(() => window.__test.childMutations === window.__beforeChildren), `${label}/${mode}: unchanged refresh does not rebuild markup`);

  await qty(6996).fill('1'); await qty(6996).dispatchEvent('change');
  await qty(6997).fill('4'); await qty(6997).dispatchEvent('change'); await sleep(120);
  check(await qty(6997).inputValue() === '3', `${label}/${mode}: one GA permits three Child tickets`);
  await qty(6996).fill('0'); await qty(6996).dispatchEvent('change'); await sleep(120);
  check(await qty(6997).inputValue() === '0', `${label}/${mode}: Child tickets cannot self-qualify`);
  await qty(6996).fill('2'); await qty(6996).dispatchEvent('change'); await sleep(100);

  const addonToggle = page.locator('.vms-ticket-ui-addons .vms-ticket-progressive-toggle');
  await addonToggle.click();
  await page.locator('[data-vms-product-id="7000"] .vms-addon-checkbox-wrap').first().click();
  await page.locator('[data-vms-product-id="7006"] .vms-addon-plus').first().click();
  await page.locator('[data-test-extension-qty]').fill('1');
  await page.locator('[data-test-extension-terms]').check();
  await sleep(150);
  check((await page.locator('.vms-ticketing-subtotal__primary').textContent()).includes('77.50'), `${label}/${mode}: ticket, add-on, and extension subtotal is $77.50`);
  const extensionSummary = await page.locator('[data-vms-addon-summary]').textContent();
  check(extensionSummary.includes('Rental fan') && extensionSummary.includes('7.50'), `${label}/${mode}: extension contribution appears in summary (${extensionSummary})`);
  await addonToggle.click(); await sleep(80);
  await qty(6996).fill('3'); await qty(6996).dispatchEvent('change'); await sleep(100);
  check(await addonToggle.getAttribute('aria-expanded') === 'false', `${label}/${mode}: user-collapsed Amenities state survives refresh`);

  await qty(6998).fill(mode === 'verified' ? '2' : '1'); await qty(6998).dispatchEvent('change'); await sleep(130);
  const guestInput = row(6998).locator('.vms-claim-seat-input');
  await guestInput.fill('guest@example.test');
  await row(6998).getByRole('button', {name: 'Add Registered Guest', exact: true}).click(); await sleep(250);
  check(requests.filter(request => request.path === '/api/validate-assignee').length === 1, `${label}/${mode}: one explicit guest validation request`);

  if (mode === 'guest') {
    failRef.value = true;
    await page.locator('#tribe-tickets__tickets-submit').click(); await sleep(250);
    check(requests.filter(request => request.path === '/api/atomic-add').length === 1, `${label}/${mode}: failed cart request is not retried`);
    check(!(await page.evaluate(() => window.BVMGR_TICKETING_FRONT_BUNDLE.state.isSubmitting)), `${label}/${mode}: failed request releases busy state`);
  }
  await page.locator('#tribe-tickets__tickets-submit').click();
  await page.waitForURL('**/cart/');
  const atomic = requests.filter(request => request.path === '/api/atomic-add');
  const requestBody = JSON.parse(atomic[mode === 'guest' ? 1 : 0].body);
  check(requestBody.ticket_lines.some(line => line.product_id === 6996 && line.qty === 3), `${label}/${mode}: payload retains ticket quantity`);
  check(requestBody.addon_lines.length === 2, `${label}/${mode}: payload retains both add-ons`);
  check(requestBody.extensions.test_extension.quantity === 1 && requestBody.extensions.test_extension.termsAccepted, `${label}/${mode}: payload retains validated extension state`);
  await page.goBack(); await page.waitForSelector('.vms-qualified-ticket-more-info'); await sleep(250);
  await page.evaluate(() => { window.BVMGR_TICKETING_FRONT_BUNDLE.state.isSubmitting = true; window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted: true})); });
  await sleep(80);
  check(!(await page.evaluate(() => window.BVMGR_TICKETING_FRONT_BUNDLE.state.isSubmitting)), `${label}/${mode}: bfcache pageshow clears busy state`);
  await qty(6996).evaluate(input => input.replaceWith(input.cloneNode(true)));
  await qty(6996).fill('2'); await qty(6996).dispatchEvent('change'); await sleep(100);
  check((await page.locator('.vms-ticketing-subtotal__primary').textContent()).includes('40.00'), `${label}/${mode}: delegated handlers support a replaced TEC quantity input`);
  check(errors.length === 0, `${label}/${mode}: no uncaught errors: ${errors.join('; ')}`);
  await context.close();
}

async function runOwnershipVariants(browser) {
  for (const variant of ['ticket-only', 'classic', 'fallback']) {
    const context = await browser.newContext({viewport: {width: 1100, height: 800}});
    const page = await context.newPage();
    let html = fixture('guest', variant === 'ticket-only' ? 'progressive' : 'classic');
    if (variant === 'fallback') html = html.replace(/<script src="\/assets\/vms-ticketing-front.js"><\/script>/g, '');
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.pathname === '/event/') return route.fulfill({body: html, contentType: 'text/html'});
      if (url.pathname.startsWith('/assets/')) return route.fulfill({body: source(path.basename(url.pathname)), contentType: 'application/javascript'});
      if (url.pathname === '/api/cart-context') return route.fulfill({json: {success: true, data: {ga_qty: 0, prior_qualifying_qty: 0, pool_qty_by_key: {}, prior_pool_qty_by_key: {}}}});
      throw new Error('Unexpected variant request: ' + url);
    });
    await page.addInitScript(variantName => {
      window.__variantMutations = 0;
      new MutationObserver(records => window.__variantMutations += records.length).observe(document, {subtree: true, childList: true, attributes: true});
      document.addEventListener('readystatechange', () => {
        if (document.readyState !== 'interactive') return;
        const sourceBlock = document.querySelector('#vms-reserved-addons');
        if (variantName === 'ticket-only') { sourceBlock.remove(); return; }
        if (variantName === 'fallback') {
          sourceBlock.removeAttribute('data-vms-render-mode');
          sourceBlock.querySelectorAll('.vms-addon-controls').forEach(control => {
            const link = document.createElement('a'); link.className = 'vms-entitlements-add';
            link.href = '/?add-to-cart=' + control.dataset.vmsProductId; link.dataset.vmsMaxQty = '4'; link.textContent = 'Reserve'; control.replaceWith(link);
          });
        }
      });
    }, variant);
    await page.goto('https://ticketing.example.test/event/'); await sleep(450);
    if (variant === 'fallback') {
      check(await page.locator('[data-vms-fallback-active="1"]').count() === 1, 'fallback owns legacy markup once');
      await page.evaluate(source('vms-ticketing-front-fallback.js'));
      check(await page.locator('[data-vms-fallback-active="1"]').count() === 1, 'duplicate fallback script does not remount');
    } else if (variant === 'classic') {
      check(await page.locator('[data-vms-fallback-active="1"]').count() === 1, 'classic: fallback owns server-controls add-ons once');
      check(await page.locator('#tribe-tickets__tickets-form[data-vms-ticket-surface-owner="tec-native"]').count() === 1, 'classic: TEC retains native ticket-surface authority');
    } else {
      check(await page.locator('[data-vms-fallback-active="1"]').count() === 0, `${variant}: fallback does not compete with primary`);
    }
    await sleep(250); const start = await page.evaluate(() => window.__variantMutations); await sleep(700);
    check(await page.evaluate(() => window.__variantMutations) === start, `${variant}: lifecycle settles at idle`);
    await context.close();
  }
}

function isComputedVisible(node) {
  if (!node || !node.isConnected || node.hidden) return false;
  let current = node;
  while (current && current.nodeType === 1) {
    const style = window.getComputedStyle(current);
    if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) return false;
    current = current.parentElement;
  }
  const rect = node.getBoundingClientRect();
  return rect.width > 0 && rect.height > 0;
}

async function runSettledVisibilityMatrix(browser) {
  for (const layout of ['classic', 'progressive']) {
    for (const identity of ['guest', 'admin']) {
      const label = `${layout}/${identity}`;
      const context = await browser.newContext({viewport: {width: 1280, height: 900}});
      const page = await context.newPage();
      page.setDefaultTimeout(7000);
      const requests = [];
      const errors = [];
      const warnings = [];
      const failRef = {value: false};
      page.on('pageerror', error => errors.push(String(error)));
      page.on('console', message => {
        if (message.type() === 'error') errors.push(message.text());
        if (message.type() === 'warning') warnings.push(message.text());
      });
      await page.addInitScript(() => { window.__test = {calls: {}}; });
      await installRoutes(page, identity, layout, requests, failRef);
      await page.goto('https://ticketing.example.test/event/');
      await page.waitForSelector('#tribe-tickets__tickets-form');

      // The incident occurred after MutationObserver/requestAnimationFrame work,
      // so this acceptance window must remain at least two settled seconds.
      await sleep(2200);
      const visibility = await page.evaluate(isComputedVisible => {
        const visible = eval(`(${isComputedVisible})`);
        const form = document.querySelector('#tribe-tickets__tickets-form');
        const rows = Array.from(document.querySelectorAll('.tribe-tickets__tickets-item'));
        return {
          formCount: document.querySelectorAll('#tribe-tickets__tickets-form').length,
          rowCount: rows.length,
          visibleForm: visible(form),
          visibleRows: rows.filter(visible).length,
          owner: form ? form.getAttribute('data-vms-ticket-surface-owner') : '',
          fallbackOwners: document.querySelectorAll('[data-vms-fallback-active="1"]').length,
          addonOwners: document.querySelectorAll('[data-vms-addon-controller-owner]').length,
          progressiveEnhancers: document.querySelectorAll('[data-vms-progressive-enhancer="presentation-only"]').length,
          progressiveObservers: window.__test ? window.__test.progressiveObservers || 0 : 0,
          bundleLoaded: !!(window.BVMGR_TICKETING_FRONT_BUNDLE && window.BVMGR_TICKETING_FRONT_BUNDLE.loaded),
          bundleState: !!(window.BVMGR_TICKETING_FRONT_BUNDLE && window.BVMGR_TICKETING_FRONT_BUNDLE.state),
          layout: window.BVMGR_TICKETING_FRONT && window.BVMGR_TICKETING_FRONT.uiLayout
        };
      }, isComputedVisible.toString());
      check(visibility.formCount === 1 && visibility.rowCount === 4, `${label}: exactly one native form and four ticket rows remain connected`);
      check(visibility.visibleForm && visibility.visibleRows === 4, `${label}: form and all ticket rows have computed visibility after 2.2 seconds`);
      if (layout === 'classic') {
        check(visibility.owner === 'tec-native', `${label}: TEC is the sole ticket-surface owner`);
        check(visibility.fallbackOwners === 1 && visibility.addonOwners === 1, `${label}: fallback owns only the server-controls add-ons`);
        check(visibility.progressiveEnhancers === 0, `${label}: progressive enhancer is inactive`);
      } else {
        check(visibility.owner === 'bvmgr-ticketing-front', `${label}: unified controller is the sole ticket-surface owner (${JSON.stringify(visibility)}; ${errors.join('; ')})`);
        check(visibility.fallbackOwners === 0 && visibility.addonOwners === 0, `${label}: fallback does not compete for server_controls`);
        check(visibility.progressiveEnhancers === 1, `${label}: progressive presentation attaches once`);
      }

      const ga = page.locator('#tribe-tickets__tickets-item-quantity-number--6996');
      await page.locator('#tribe-block-tickets-item-6996 .tribe-tickets__tickets-item-quantity-add').click();
      check(await ga.inputValue() === '1', `${label}: native GA increment remains functional`);
      await page.locator('#tribe-block-tickets-item-6996 .tribe-tickets__tickets-item-quantity-remove').click();
      check(await ga.inputValue() === '0', `${label}: native GA decrement remains functional`);
      await ga.fill('2'); await ga.dispatchEvent('change'); await sleep(100);
      if (layout === 'progressive') {
        const addonToggle = page.locator('.vms-ticket-ui-addons .vms-ticket-progressive-toggle');
        if (await addonToggle.getAttribute('aria-expanded') === 'false') await addonToggle.click();
      }
      const addonPlus = page.locator('[data-vms-product-id="7006"] .vms-addon-plus').first();
      await addonPlus.click(); await sleep(100);
      check(!(await addonPlus.isDisabled()), `${label}: server-controls add-on remains available`);
      await page.locator('[data-test-extension-qty]').fill('1');
      await page.locator('[data-test-extension-terms]').check();
      await page.locator('#tribe-tickets__tickets-submit').click();
      await page.waitForURL('**/cart/');
      const atomic = requests.filter(request => request.path === '/api/atomic-add');
      check(atomic.length === 1, `${label}: cart handoff remains single-request`);
      const payload = JSON.parse(atomic[0].body);
      check(payload.ticket_lines.some(line => line.product_id === 6996 && line.qty === 2), `${label}: cart handoff preserves native ticket quantity`);
      check(payload.addon_lines.some(line => line.product_id === 7006 && line.qty === 1), `${label}: cart handoff preserves add-on quantity`);
      check(errors.length === 0 && warnings.length === 0, `${label}: no BVM console errors or warnings (${errors.concat(warnings).join('; ')})`);
      await context.close();
    }
  }
}

async function runPostCart(browser) {
  const context = await browser.newContext();
  const page = await context.newPage();
  await page.route('https://offer.example.test/', route => route.fulfill({body: '<!doctype html><body></body>', contentType: 'text/html'}));
  await page.goto('https://offer.example.test/');
  await page.evaluate(source('vms-ticketing-post-cart-offer.js'));
  await page.evaluate(() => { window.BVMGR_TICKETING_FRONT = {eventPlanId: 41, checkoutUrl: '/checkout/', postCartOffer: {enabled: 1, id: 'express', primaryUrl: '#bar'}}; });
  check(await page.evaluate(() => window.BVMGR_TICKETING_POST_CART_OFFER.handle({data: {cart_url: '/cart/'}}, {isSubmitting: false})), 'post-cart offer handles an eligible success');
  await page.waitForSelector('#bvmgr-post-cart-offer.is-open');
  check(await page.locator('[role="dialog"][aria-modal="true"]').count() === 1, 'post-cart offer exposes one accessible modal');
  check(await page.evaluate(() => document.activeElement.classList.contains('bvmgr-post-cart-offer__primary')), 'post-cart offer moves focus to primary action');
  await page.locator('.bvmgr-post-cart-offer__primary').click();
  await page.evaluate(() => document.getElementById('bvmgr-post-cart-offer').remove());
  check(!(await page.evaluate(() => window.BVMGR_TICKETING_POST_CART_OFFER.handle({}, {isSubmitting: false}))), 'same event/offer is suppressed inside 60 minutes');
  await page.evaluate(() => localStorage.setItem('bvmgr-post-cart-offer:v2:41:express', String(Date.now() - 61 * 60 * 1000)));
  check(await page.evaluate(() => window.BVMGR_TICKETING_POST_CART_OFFER.handle({}, {isSubmitting: false})), 'suppression expires after 60 minutes');
  await page.waitForSelector('#bvmgr-post-cart-offer.is-open');
  await page.evaluate(() => { document.getElementById('bvmgr-post-cart-offer').remove(); window.BVMGR_TICKETING_FRONT.eventPlanId = 42; });
  check(await page.evaluate(() => window.BVMGR_TICKETING_POST_CART_OFFER.handle({}, {isSubmitting: false})), 'suppression does not leak to another event');
  await page.waitForSelector('#bvmgr-post-cart-offer.is-open');
  await page.evaluate(() => { document.getElementById('bvmgr-post-cart-offer').remove(); window.BVMGR_TICKETING_FRONT.eventPlanId = 41; window.BVMGR_TICKETING_FRONT.postCartOffer.id = 'dessert'; });
  check(await page.evaluate(() => window.BVMGR_TICKETING_POST_CART_OFFER.handle({}, {isSubmitting: false})), 'suppression does not leak to another offer');
  await context.close();
}

(async () => {
  const browser = await playwright.chromium.launch({headless: true, executablePath});
  try {
    if (process.env.BVM_TEST_MATRIX_ONLY === '1') {
      await runSettledVisibilityMatrix(browser);
      console.log(`${checks} settled ticket visibility assertions passed; all requests were contained and mocked.`);
      return;
    }
    for (const mode of ['guest', 'unverified', 'verified']) await runLifecycle(browser, mode, {viewport: {width: 1280, height: 900}}, 'desktop');
    await runLifecycle(browser, 'guest', {...playwright.devices['iPhone 13']}, 'mobile');
    await runOwnershipVariants(browser);
    await runSettledVisibilityMatrix(browser);
    await runPostCart(browser);
    console.log(`${checks} public ticketing browser assertions passed; all requests were contained and mocked.`);
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
