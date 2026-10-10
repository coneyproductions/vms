const { chromium } = require('../../../../vms-dev/node_modules/playwright');

function check(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

(async () => {
  const fixture = JSON.parse(process.env.BVM_BUSINESS_SOURCE_FIXTURE || '{}');
  const user = process.env.VMS_ADMIN_USER;
  const pass = process.env.VMS_ADMIN_PASS;
  check(fixture.admin_url && fixture.existing_source_id && fixture.existing_free_batch_id && user && pass, 'Fixture and temporary admin credentials are required.');

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  page.setDefaultTimeout(60000);

  await page.goto(new URL('/wp-login.php', fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(user);
  await page.locator('#user_pass').fill(pass);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/wp-login.php')),
    page.locator('#wp-submit').click(),
  ]);
  await page.goto(fixture.admin_url, { waitUntil: 'domcontentloaded' });

  await page.locator('input[name="recipient_source_mode"][value="business_source"]').check();
  const route = page.locator('[data-vms-recipient-source-business]');
  await route.locator('input[name="business_campaign_name"]').fill('Focused distribution lifecycle fixture');
  await route.locator('select[name="related_source_id"]').selectOption(String(fixture.existing_source_id));
  await route.locator('select[name="related_batch_id"]').selectOption(String(fixture.existing_free_batch_id));
  await route.locator('button[value="business_source_preview"]').click();
  await page.waitForLoadState('domcontentloaded');
  await page.getByRole('button', { name: 'Create Campaign and Continue to Business QR Setup' }).click();
  await page.waitForLoadState('domcontentloaded');

  const selection = page.locator('#backstage-outreach-business-selection');
  await selection.getByRole('button', { name: 'Review Selection' }).click();
  await page.waitForLoadState('domcontentloaded');
  await page.getByRole('button', { name: 'Save Reviewed Links' }).click();
  await page.waitForLoadState('domcontentloaded');

  const activation = page.locator('#backstage-outreach-business-share [data-vms-status-submit-form]');
  if (await activation.count()) {
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      activation.getByRole('button', { name: 'Activate Campaign' }).click(),
    ]);
  } else {
    const status = page.locator('select[name="status"]');
    const activationPost = await status.evaluate((field) => {
      const form = field.form;
      const fields = Object.fromEntries(Array.from(new FormData(form).entries()));
      fields.status = 'active';
      fields.save_mode = 'standard';
      return { action: new URL(form.getAttribute('action') || window.location.href, window.location.href).toString(), fields };
    });
    const response = await page.request.post(activationPost.action, { form: activationPost.fields, maxRedirects: 0 });
    check(response.status() === 302, `Legacy activation returned ${response.status()}.`);
    await page.goto(new URL(response.headers().location, fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
  }

  const campaignUrl = page.url();
  await page.locator('.vms-pass-secondary-business-controls').evaluate((details) => { details.open = true; });
  let row = page.locator('[data-vms-tour="outreach-business-results"] tbody tr').first();
  const pause = row.getByRole('link', { name: 'Pause', exact: true });
  check(await pause.getAttribute('href'), 'Scoped active distribution has no Pause action.');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    pause.click(),
  ]);
  await page.locator('.vms-pass-secondary-business-controls').evaluate((details) => { details.open = true; });
  row = page.locator('[data-vms-tour="outreach-business-results"] tbody tr').first();
  const pausedStatus = (await row.locator('td').nth(1).innerText()).trim();
  const resume = row.getByRole('link', { name: 'Resume', exact: true });
  const resumeCount = await resume.count();
  const notices = await page.locator('.notice').allInnerTexts();
  console.log(JSON.stringify({ pausedStatus, resumeCount, pauseLocation: page.url(), notices }));
  check(pausedStatus === 'paused' && resumeCount === 1, 'Scoped Pause request did not render the same distribution as resumable.');

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    resume.click(),
  ]);
  await page.locator('.vms-pass-secondary-business-controls').evaluate((details) => { details.open = true; });
  row = page.locator('[data-vms-tour="outreach-business-results"] tbody tr').first();
  check((await row.locator('td').nth(1).innerText()).trim() === 'active', 'Resume did not restore the scoped distribution to active.');

  await browser.close();
  console.log('Focused Business distribution Pause → Resume browser check passed.');
})().catch((error) => {
  console.error(error.stack || error.message || error);
  process.exit(1);
});
