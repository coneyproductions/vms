const { chromium } = require('../../../../vms-dev/node_modules/playwright');
const fs = require('fs');

function check(condition, message) {
  if (!condition) throw new Error(message);
}

(async () => {
  const fixture = JSON.parse(process.env.OUTREACH_PARTY_REFERRAL_FIXTURE || '{}');
  const user = process.env.VMS_ADMIN_USER;
  const pass = process.env.VMS_ADMIN_PASS;
  const httpUser = process.env.VMS_HTTP_USER;
  const httpPass = process.env.VMS_HTTP_PASS;
  const output = process.env.OUTREACH_PARTY_REFERRAL_EVIDENCE;
  if (!fixture.admin_url || !user || !pass || !output) throw new Error('Fixture, disposable administrator credentials, and evidence directory are required.');
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  let storageState;

  for (const viewport of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
    const contextOptions = { ignoreHTTPSErrors: true, viewport, storageState };
    if (httpUser && httpPass) contextOptions.httpCredentials = { username: httpUser, password: httpPass };
    const context = await browser.newContext(contextOptions);
    const page = await context.newPage();
    const errors = [];
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('pageerror', error => errors.push(error.message));
    if (!storageState) {
      await page.goto(new URL('/wp-login.php', fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
      await page.locator('#user_login').fill(user);
      await page.locator('#user_pass').fill(pass);
      await page.locator('#wp-submit').click();
      await page.waitForURL(url => !url.pathname.endsWith('/wp-login.php'), { waitUntil: 'domcontentloaded', timeout: 60000 });
      check(!new URL(page.url()).pathname.endsWith('/wp-login.php'), `Administrator login failed: ${await page.locator('#login_error').textContent().catch(() => 'unknown error')}`);
      storageState = await context.storageState();
    }

    const directory = new URL(fixture.admin_url);
    directory.searchParams.delete('view');
    directory.searchParams.delete('party_id');
    await page.goto(directory.toString(), { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: `${output}/party-unified-workflow-${viewport.name}-diagnostic.png`, fullPage: true });
    check(await page.getByRole('heading', { name: 'Realtor / Partner workflow' }).count() === 1, `${viewport.name}: unified workflow heading missing at ${page.url()} (headings: ${(await page.locator('h1,h2,h3').allTextContents()).join(' | ')}).`);
    check(await page.getByText('nothing runs on upgrade', { exact: false }).count() === 1, `${viewport.name}: non-automatic safety boundary missing.`);
    check(await page.locator('#outreach-party-adoption').count() === 1, `${viewport.name}: adoption panel missing.`);
    check(await page.locator('#outreach-party-campaign').count() === 1, `${viewport.name}: campaign panel missing.`);
    check(await page.locator('#outreach-party-bulk-links').count() === 1, `${viewport.name}: bulk-link panel missing.`);
    for (const selector of ['#outreach-party-adoption', '#outreach-party-campaign', '#outreach-party-bulk-links']) {
      await page.locator(selector).evaluate(node => { node.open = true; });
    }
    check(!(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2)), `${viewport.name}: horizontal overflow detected with workflow panels open.`);

    const adoption = page.locator('#outreach-party-adoption');
    await adoption.locator('select[name="source_id"]').selectOption(String(fixture.source_id));
    await adoption.locator('select[name="campaign_ids[]"]').selectOption(fixture.historical_campaign_ids.map(String));
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), adoption.getByRole('button', { name: 'Preview unique people' }).click()]);
    check(await page.getByText('206 snapshots → 103 proposed people', { exact: false }).count() === 1, `${viewport.name}: zero-contact-ID adoption cardinality is incorrect.`);
    check(await page.getByText('103 compound cross-campaign matches', { exact: false }).count() === 1, `${viewport.name}: compound match count is missing.`);
    check(await page.getByText('Identity evidence:', { exact: true }).count() === 103, `${viewport.name}: explicit identity evidence is not shown for every proposed person.`);
    check(!(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2)), `${viewport.name}: adoption review overflow detected.`);

    const bulk = page.locator('#outreach-party-bulk-links');
    await bulk.evaluate(node => { node.open = true; });
    await bulk.locator('select[name="partner_campaign_id"]').selectOption(String(fixture.campaign_id));
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), bulk.getByRole('button', { name: 'Load Source Parties' }).click()]);
    check(await page.getByText(fixture.marker, { exact: true }).count() >= 1, `${viewport.name}: Source-associated Party did not load.`);
    check(await page.locator('input[data-vms-party-select][value="' + fixture.party_id + '"]').count() === 1, `${viewport.name}: bulk selection checkbox missing.`);
    check(!(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2)), `${viewport.name}: horizontal overflow detected with Source Party table.`);
    await page.screenshot({ path: `${output}/party-unified-workflow-${viewport.name}.png`, fullPage: true });
    check(errors.length === 0, `${viewport.name}: console errors: ${errors.join(' | ')}`);
    await context.close();
  }

  await browser.close();
  console.log('Unified Party workflow browser PASS (1440px and 390px)');
})().catch(error => { console.error(error); process.exit(1); });
