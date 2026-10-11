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
    const email = page.locator('input[name="email"]');
    const phone = page.locator('input[name="phone"]');
    if (await email.getAttribute('required') === null || await email.getAttribute('type') !== 'email') throw new Error(`${testCase.name}: email is not client-required.`);
    if (await phone.getAttribute('required') !== null || await phone.getAttribute('type') !== 'tel') throw new Error(`${testCase.name}: phone is not optional.`);
    if (!body.includes('Required for pass confirmation and event-related communication. This does not enroll you in marketing.')) throw new Error(`${testCase.name}: email-use explanation is missing.`);
    if (body.includes('Choose 1 to 2.')) throw new Error(`${testCase.name}: redundant admission-limit helper text is present.`);
    const quantity = page.locator('select[name="party_size"]');
    if (await quantity.count() !== 1 || await quantity.locator('option').allTextContents().then((values) => values.join(',')) !== '1,2' || await quantity.inputValue() !== '2') throw new Error(`${testCase.name}: bounded quantity/default is incorrect.`);
    if (await page.locator('input[name="opt_in"]').isChecked()) throw new Error(`${testCase.name}: optional event updates defaulted on.`);
    const optInAlignment = await page.locator('.vms-pass-checkbox').evaluate((element) => ({ display: getComputedStyle(element).display, align: getComputedStyle(element).alignItems, direction: getComputedStyle(element).flexDirection }));
    if (optInAlignment.display !== 'flex' || optInAlignment.align !== 'center' || optInAlignment.direction !== 'row') throw new Error(`${testCase.name}: marketing opt-in is not centered inline: ${JSON.stringify(optInAlignment)}.`);
    if (!body.includes('Claim 2 Guest Passes')) throw new Error(`${testCase.name}: quantity-aware submit label is missing.`);
    const eventSelect = page.locator('select[name="event_plan_id"]');
    if (fixture.offer_scope === 'single') {
      if (await eventSelect.count() !== 0 || await page.locator('input[type="hidden"][name="event_plan_id"]').count() !== 1) throw new Error(`${testCase.name}: single-event selection was not automatic.`);
      if (!body.includes(fixture.event_titles[0]) || !body.includes(fixture.event_dates[0])) throw new Error(`${testCase.name}: single-event title/date is missing.`);
      const artwork = page.locator('.vms-pass-event-artwork');
      if (await artwork.count() !== 1 || !(await artwork.getAttribute('alt') || '').includes('event artwork 1')) throw new Error(`${testCase.name}: single-event TEC artwork or alt text is missing.`);
    } else {
      if (await eventSelect.count() !== 1 || await eventSelect.locator('option').count() !== 3) throw new Error(`${testCase.name}: multi-event selector is incorrect.`);
      const preview = page.locator('[data-backstage-event-preview]');
      if (!await preview.evaluate((element) => element.classList.contains('is-neutral'))) throw new Error(`${testCase.name}: multi-event preview did not start neutral.`);
      await eventSelect.selectOption(String(fixture.post_ids[0]));
      const firstImage = await preview.locator('[data-backstage-event-image]').getAttribute('src');
      await eventSelect.selectOption(String(fixture.post_ids[1]));
      const secondImage = await preview.locator('[data-backstage-event-image]').getAttribute('src');
      if (!firstImage || !secondImage || firstImage === secondImage || !(await preview.innerText()).includes(fixture.event_titles[1])) throw new Error(`${testCase.name}: multi-event artwork did not follow selection.`);
    }
    await email.fill('invalid');
    if (await email.evaluate((element) => element.checkValidity())) throw new Error(`${testCase.name}: invalid email passed client validation.`);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow > 1) throw new Error(`${testCase.name}: form overflows by ${overflow}px.`);
    if (errors.length) throw new Error(`${testCase.name}: console errors: ${errors.join(' | ')}`);
    await page.screenshot({ path: path.join(output, `${fixture.offer_scope}-${testCase.name}.png`), fullPage: true });
    await context.close();
  }
  await browser.close();
  console.log(JSON.stringify({ output, scope: fixture.offer_scope, url: fixture.public_url }));
})().catch((error) => { console.error(error); process.exit(1); });
