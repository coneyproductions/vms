const { chromium } = require('/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public/wp-content/vms-dev/node_modules/playwright');
const fs = require('fs');

function check(condition, message) {
  if (!condition) throw new Error(message);
}

(async () => {
  const fixture = JSON.parse(process.env.OUTREACH_PARTY_FIXTURE || '{}');
  const user = process.env.VMS_ADMIN_USER;
  const pass = process.env.VMS_ADMIN_PASS;
  const output = process.env.OUTREACH_PARTY_EVIDENCE;
  if (!fixture.admin_url || !fixture.person_id || !user || !pass || !output) throw new Error('Fixture, credentials, and evidence directory are required.');
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
      await page.goto(new URL('/wp-login.php', fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
      await page.locator('#user_login').fill(user);
      await page.locator('#user_pass').fill(pass);
      await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/wp-login.php')), page.locator('#wp-submit').click()]);
      storageState = await context.storageState();
    }
    await page.goto(fixture.admin_url, { waitUntil: 'domcontentloaded' });
    check(await page.getByRole('heading', { name: 'Contacts & Partners', exact: true }).count() === 1, `${viewport.name}: canonical directory heading missing.`);
    check(await page.getByText('Directory identity is not campaign eligibility.', { exact: true }).count() === 1, `${viewport.name}: authority boundary missing.`);
    check(await page.getByText('Email not provided; email delivery is unavailable.', { exact: true }).count() >= 1, `${viewport.name}: missing-email state absent.`);
    await page.locator('input[name="party_search"]').fill('Café — 李');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Apply filters' }).click()]);
    check(await page.getByText(`${fixture.marker} Café — 李`, { exact: true }).count() === 1, `${viewport.name}: UTF-8 Party search failed.`);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2);
    check(!overflow, `${viewport.name}: page overflow detected.`);
    await page.screenshot({ path: `${output}/party-directory-${viewport.name}.png`, fullPage: true });
    check(errors.length === 0, `${viewport.name}: console errors: ${errors.join(' | ')}`);
    await context.close();
  }

  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 }, storageState });
  const page = await context.newPage();
  const review = new URL(fixture.admin_url);
  review.searchParams.set('legacy_type', 'campaign_recipient');
  review.searchParams.set('legacy_id', String(fixture.recipient_id));
  await page.goto(review.toString(), { waitUntil: 'domcontentloaded' });
  check(await page.getByText('Ambiguous—review required', { exact: true }).count() === 2, 'Shared-email legacy review did not expose both ambiguous suggestions.');
  check(await page.getByRole('heading', { name: 'Immutable legacy snapshot' }).count() === 1, 'Immutable legacy snapshot is missing.');
  const noNonce = await context.request.post(new URL('/wp-admin/admin-post.php', fixture.admin_url).toString(), { form: { action: 'backstage_outreach_party_save', party_type: 'person', display_name: `${fixture.marker} forged` }, maxRedirects: 0 });
  check(noNonce.status() === 403, `Missing nonce did not fail closed (${noNonce.status()}).`);
  await context.close();
  await browser.close();
  console.log('Outreach Party foundation browser PASS');
})().catch(error => { console.error(error); process.exit(1); });
