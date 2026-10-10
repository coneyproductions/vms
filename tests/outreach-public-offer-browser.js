const { chromium } = require(process.env.PLAYWRIGHT_PATH || '../../../../vms-dev/node_modules/playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  const fixture = JSON.parse(process.env.BVM_PUBLIC_OFFER_FIXTURE || '{}');
  if (!fixture.public_url || !['single', 'multi'].includes(fixture.offer_scope)) {
    throw new Error('A valid public Guest Pass fixture is required.');
  }
  const output = path.resolve(process.env.BVM_PUBLIC_OFFER_SCREENSHOTS || path.join(__dirname, '../artifacts/outreach-public-offer'));
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  for (const testCase of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: testCase.width, height: testCase.height } });
    const page = await context.newPage();
    const errors = [];
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('pageerror', (error) => errors.push(error.message));
    const response = await page.goto(fixture.public_url, { waitUntil: 'networkidle' });
    if (!response || response.status() !== 200) throw new Error(`${testCase.name}: Guest Pass form did not return HTTP 200.`);
    const body = await page.locator('body').innerText();
    if (!body.includes('Claim Your Guest Passes') || !body.includes('Up to 2 complimentary admissions')) throw new Error(`${testCase.name}: Guest Pass heading or benefit is missing.`);
    if (!body.includes('Shared by Main Street Coffee') || body.includes('Referred by:')) throw new Error(`${testCase.name}: referral presentation is incorrect.`);
    if (body.includes('contact information is never copied')) throw new Error(`${testCase.name}: removed explanatory copy is present.`);
    const quantity = page.locator('select[name="party_size"]');
    if (await quantity.count() !== 1 || await quantity.locator('option').allTextContents().then((values) => values.join(',')) !== '1,2' || await quantity.inputValue() !== '2') throw new Error(`${testCase.name}: bounded quantity/default is incorrect.`);
    if (await page.locator('input[name="opt_in"]').isChecked()) throw new Error(`${testCase.name}: optional event updates defaulted on.`);
    if (!body.includes('Claim 2 Guest Passes')) throw new Error(`${testCase.name}: quantity-aware submit label is missing.`);
    const eventSelect = page.locator('select[name="event_plan_id"]');
    if (fixture.offer_scope === 'single') {
      if (await eventSelect.count() !== 0 || await page.locator('input[type="hidden"][name="event_plan_id"]').count() !== 1) throw new Error(`${testCase.name}: single-event selection was not automatic.`);
      if (!body.includes(fixture.event_titles[0]) || !body.includes(fixture.event_dates[0])) throw new Error(`${testCase.name}: single-event title/date is missing.`);
    } else {
      if (await eventSelect.count() !== 1 || await eventSelect.locator('option').count() !== 3) throw new Error(`${testCase.name}: multi-event selector is incorrect.`);
    }
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow > 1) throw new Error(`${testCase.name}: form overflows by ${overflow}px.`);
    if (errors.length) throw new Error(`${testCase.name}: console errors: ${errors.join(' | ')}`);
    await page.screenshot({ path: path.join(output, `${fixture.offer_scope}-${testCase.name}.png`), fullPage: true });
    await context.close();
  }
  await browser.close();
  console.log(JSON.stringify({ output, scope: fixture.offer_scope, url: fixture.public_url }));
})().catch((error) => { console.error(error); process.exit(1); });
