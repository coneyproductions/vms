const { chromium } = require(process.env.PLAYWRIGHT_PATH || '../../../../vms-dev/node_modules/playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  const fixture = JSON.parse(process.env.BVM_SCREENSHOT_FIXTURE || '{}');
  const user = process.env.VMS_ADMIN_USER;
  const pass = process.env.VMS_ADMIN_PASS;
  if (!fixture.public_url) {
	throw new Error('A public fixture URL is required.');
  }
  if ((user && !pass) || (!user && pass)) throw new Error('Both VMS admin credentials are required for the optional operator screenshot.');
  const output = process.env.BVM_DISCOUNT_SCREENSHOTS
    ? path.resolve(process.env.BVM_DISCOUNT_SCREENSHOTS)
    : path.resolve(__dirname, '../artifacts/business-discount-qr');
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  if (user && pass && fixture.admin_url) {
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
	  page.setDefaultTimeout(15000);
    await page.goto(new URL('/wp-login.php', fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
    await page.locator('#user_login').fill(user);
    await page.locator('#user_pass').fill(pass);
    await page.locator('#wp-submit').click();
    await page.waitForLoadState('domcontentloaded');
	  await page.goto(fixture.admin_url, { waitUntil: 'domcontentloaded' });
    await page.locator('#backstage-outreach-partners').scrollIntoViewIfNeeded();
    await page.locator('#backstage-outreach-partners').screenshot({ path: path.join(output, 'operator-distribution.png') });
    await context.close();
  }
  for (const testCase of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
    const publicContext = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: testCase.width, height: testCase.height } });
    const publicPage = await publicContext.newPage();
    const errors = [];
    publicPage.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    publicPage.on('pageerror', (error) => errors.push(error.message));
    const response = await publicPage.goto(fixture.public_url, { waitUntil: 'networkidle' });
    if (!response || response.status() !== 200) throw new Error(`${testCase.name}: paid offer did not return HTTP 200.`);
    const body = await publicPage.locator('body').innerText();
	const bodyLower = body.toLowerCase();
	if (!body.includes(`${fixture.offer_text} Discount Voucher — up to 2 discounted admissions`)) throw new Error(`${testCase.name}: exact paid benefit is missing.`);
    if (!body.includes('Shared by Main Street Coffee')) throw new Error(`${testCase.name}: business attribution is not secondary and visible.`);
	if (!body.includes('Choose discounted tickets') || !body.includes('applied automatically')) throw new Error(`${testCase.name}: discounted-ticket choice or automatic-discount guidance is missing.`);
	if (!bodyLower.includes('neighborhood offer') || !body.includes('Discount Voucher')) throw new Error(`${testCase.name}: customer-facing paid-offer terminology is missing.`);
	if (body.includes('Commerce Discount') || body.includes('managed coupon') || body.includes('complimentary or already purchased') === false) throw new Error(`${testCase.name}: public offer distinction or implementation language is incorrect.`);
    const highlight = publicPage.locator('.vms-offer-event-highlight');
    const highlightText = await highlight.count() === 1 ? await highlight.innerText() : '';
    if (!highlightText.toLowerCase().includes('valid for this event') || !highlightText.includes(fixture.event_title) || !highlightText.includes(fixture.event_date)) throw new Error(`${testCase.name}: single-event title/date is not prominent (${JSON.stringify({ highlightText, expectedTitle: fixture.event_title, expectedDate: fixture.event_date })}).`);
    if (await publicPage.locator('.vms-offer-event img, .vms-offer-event-fallback').count() < 1) throw new Error(`${testCase.name}: event image/fallback presentation is missing.`);
    if (await publicPage.locator('details.vms-offer-terms').count() !== 1) throw new Error(`${testCase.name}: secondary terms are not in accessible details.`);
    const overflow = await publicPage.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow > 1) throw new Error(`${testCase.name}: public offer overflows by ${overflow}px.`);
    if (errors.length) throw new Error(`${testCase.name}: console errors: ${errors.join(' | ')}`);
    await publicPage.screenshot({ path: path.join(output, `${testCase.name}-customer-offer.png`), fullPage: true });
    await publicContext.close();
  }
  console.log(JSON.stringify({ output, url: fixture.public_url }));
  await browser.close();
})().catch((error) => { console.error(error); process.exit(1); });
