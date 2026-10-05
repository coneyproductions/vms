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
  const output = path.resolve(__dirname, '../artifacts/business-discount-qr');
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
  await context.clearCookies();
  await page.goto(fixture.public_url, { waitUntil: 'domcontentloaded' });
  await page.screenshot({ path: path.join(output, 'customer-offer.png'), fullPage: true });
  console.log(JSON.stringify({ output, title: await page.title(), url: page.url() }));
  await browser.close();
})().catch((error) => { console.error(error); process.exit(1); });
