const { chromium } = require('../../../../vms-dev/node_modules/playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  const fixture = JSON.parse(process.env.BVM_SCREENSHOT_FIXTURE || '{}');
  const user = process.env.VMS_ADMIN_USER;
  const pass = process.env.VMS_ADMIN_PASS;
  if (!fixture.admin_url || !fixture.public_url || !user || !pass) {
    throw new Error('Fixture URLs and VMS admin credentials are required.');
  }
  const output = process.env.BVM_DISCOUNT_SCREENSHOTS
    ? path.resolve(process.env.BVM_DISCOUNT_SCREENSHOTS)
    : path.resolve(__dirname, '../artifacts/business-discount-qr');
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ headless: true });
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
  for (const testCase of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
    const publicContext = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: testCase.width, height: testCase.height } });
    const publicPage = await publicContext.newPage();
    const errors = [];
    publicPage.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    publicPage.on('pageerror', (error) => errors.push(error.message));
    const response = await publicPage.goto(fixture.public_url, { waitUntil: 'networkidle' });
    if (!response || response.status() !== 200) throw new Error(`${testCase.name}: paid offer did not return HTTP 200.`);
    const body = await publicPage.locator('body').innerText();
	if (!body.includes(`${fixture.offer_text} for up to 2 people`)) throw new Error(`${testCase.name}: exact paid benefit is missing.`);
    if (!body.includes('Shared by Main Street Coffee')) throw new Error(`${testCase.name}: business attribution is not secondary and visible.`);
    if (!body.includes('Choose tickets') || !body.includes('applied automatically')) throw new Error(`${testCase.name}: ticket choice or automatic-discount guidance is missing.`);
    if (body.includes('Commerce Discount') || body.includes('managed coupon') || body.includes('Neighborhood Offer')) throw new Error(`${testCase.name}: public implementation jargon or mandatory branding remains.`);
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
