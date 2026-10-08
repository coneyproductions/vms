const { chromium } = require('../../../../vms-dev/node_modules/playwright');
const fs = require('fs');

function check(condition, message) {
  if (!condition) throw new Error(message);
}

(async () => {
  const fixture = JSON.parse(process.env.OUTREACH_PARTY_REFERRAL_FIXTURE || '{}');
  const user = process.env.VMS_ADMIN_USER;
  const pass = process.env.VMS_ADMIN_PASS;
  const cookieName = process.env.VMS_ADMIN_COOKIE_NAME;
  const cookieValue = process.env.VMS_ADMIN_COOKIE_VALUE;
  const secureCookieName = process.env.VMS_ADMIN_SECURE_COOKIE_NAME;
  const secureCookieValue = process.env.VMS_ADMIN_SECURE_COOKIE_VALUE;
  const output = process.env.OUTREACH_PARTY_REFERRAL_EVIDENCE;
  if (!fixture.admin_url || ((!user || !pass) && (!cookieName || !cookieValue)) || !output) throw new Error('Fixture, administrator authentication, and evidence directory are required.');
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  let storageState;
  for (const viewport of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport, storageState });
    const page = await context.newPage();
    const errors = [];
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('pageerror', error => errors.push(error.message));
    if (!storageState) {
      if (cookieName && cookieValue) {
        const cookies = [{ name: cookieName, value: cookieValue, url: new URL('/', fixture.admin_url).toString(), httpOnly: true, secure: true, sameSite: 'Lax' }];
        if (secureCookieName && secureCookieValue) cookies.push({ name: secureCookieName, value: secureCookieValue, url: new URL('/wp-admin/', fixture.admin_url).toString(), httpOnly: true, secure: true, sameSite: 'Lax' });
        await context.addCookies(cookies);
      } else {
        await page.goto(new URL('/wp-login.php', fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
        await page.locator('#user_login').fill(user);
        await page.locator('#user_pass').fill(pass);
        await page.locator('#wp-submit').click();
        await page.waitForURL(url => !url.pathname.endsWith('/wp-login.php'), { waitUntil: 'domcontentloaded', timeout: 30000 });
      }
      storageState = await context.storageState();
    }
    await page.goto(fixture.admin_url, { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: `${output}/party-paid-referral-${viewport.name}.png`, fullPage: true });
    check(await page.getByRole('heading', { name: 'Reusable paid Partner Admission Offers' }).count() === 1, `${viewport.name}: Partner offer panel missing at ${page.url()}.`);
    check(await page.getByText('This does not create a business, recipient, Guest Pass token, email, or message.', { exact: false }).count() === 1, `${viewport.name}: safe operator boundary missing.`);
    check(await page.locator('select[name="campaign_id"] option[value="' + fixture.campaign_id + '"]').count() === 1, `${viewport.name}: compatible reviewed campaign missing.`);
    check(!(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2)), `${viewport.name}: horizontal overflow detected.`);
    if (viewport.name === 'desktop') {
      await page.locator('select[name="campaign_id"]').selectOption(String(fixture.campaign_id));
      await page.locator('input[name="admission_cap"]').fill('4');
      await page.locator('input[name="order_cap"]').fill('3');
      await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Review Partner offer' }).click()]);
      check(await page.getByRole('heading', { name: 'Reviewed Partner offer' }).count() === 1, 'Reviewed state did not render.');
      check(await page.getByText('50% off admission', { exact: false }).count() >= 1, 'Reviewed percentage was wrong.');
      await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Generate reviewed Partner link' }).click()]);
      check(await page.getByRole('heading', { name: 'Existing Partner links' }).count() === 1, 'Generated Partner link did not render.');
      const link = await page.locator('input[readonly][value*="/admission-offer/partner/"]').inputValue();
      check(link.includes('/admission-offer/partner/'), 'Distinct signed Partner URL missing.');
    }
    await page.screenshot({ path: `${output}/party-paid-referral-${viewport.name}.png`, fullPage: true });
    check(errors.length === 0, `${viewport.name}: console errors: ${errors.join(' | ')}`);
    await context.close();
  }
  await browser.close();
  console.log('Party paid referral browser PASS');
})().catch(error => { console.error(error); process.exit(1); });
