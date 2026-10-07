const { chromium } = require('../../../../vms-dev/node_modules/playwright');
const fs = require('fs');

function check(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

(async () => {
  const fixture = JSON.parse(process.env.BVM_BUSINESS_SOURCE_FIXTURE || '{}');
  const user = process.env.VMS_ADMIN_USER;
  const pass = process.env.VMS_ADMIN_PASS;
  const outputDir = process.env.BVM_BUSINESS_STEP4_SCREENSHOTS;
  if (!fixture.admin_url || !fixture.existing_source_id || !fixture.existing_free_batch_id || !user || !pass || !outputDir) {
    throw new Error('Fixture, temporary admin credentials, and screenshot directory are required.');
  }

  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: { width: 1440, height: 1000 },
  });
  const page = await context.newPage();
  page.setDefaultTimeout(60000);
  const consoleErrors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text());
    }
  });
  page.on('pageerror', (error) => consoleErrors.push(error.message));

  await page.goto(new URL('/wp-login.php', fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(user);
  await page.locator('#user_pass').fill(pass);
  await page.locator('#wp-submit').click();
  await page.waitForLoadState('domcontentloaded');
  await page.goto(fixture.admin_url, { waitUntil: 'domcontentloaded' });

  await page.locator('input[name="recipient_source_mode"][value="business_source"]').check();
  const route = page.locator('[data-vms-recipient-source-business]');
  const campaignName = 'Focused Café — Step 4 validation';
  await route.locator('input[name="business_campaign_name"]').fill(campaignName);
  await route.locator('select[name="related_source_id"]').selectOption(String(fixture.existing_source_id));
  check(await page.getByRole('heading', { name: 'Step 4 — Create Campaign', exact: true }).count() === 1, 'Fresh business route does not have exactly one Step 4 heading.');
  check(await page.getByRole('heading', { name: 'Step 4 — Campaign Creation', exact: true }).count() === 0, 'Obsolete Step 4 heading remains on the fresh business route.');

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    route.locator('xpath=ancestor::form').evaluate((form) => {
      const mode = document.createElement('input');
      mode.type = 'hidden';
      mode.name = 'save_mode';
      mode.value = 'business_source_preview';
      form.appendChild(mode);
      form.submit();
    }),
  ]);
  await page.waitForTimeout(750);
  check(await route.locator('input[name="business_campaign_name"]').inputValue() === campaignName, 'Campaign name did not survive server validation.');
  check(await page.evaluate(() => document.activeElement && document.activeElement.getAttribute('name')) === 'related_batch_id', 'First invalid prerequisite did not receive focus.');

  await route.locator('select[name="related_source_id"]').selectOption(String(fixture.existing_source_id));
  await route.locator('select[name="related_batch_id"]').selectOption(String(fixture.existing_free_batch_id));
  await route.locator('button[value="business_source_preview"]').click();
  await page.waitForLoadState('domcontentloaded');

  const createStep = page.locator('.vms-pass-business-campaign-step');
  const createButton = createStep.getByRole('button', { name: 'Create Campaign and Continue to Business QR Setup' });
  check(await page.getByRole('heading', { name: 'Step 4 — Create Campaign', exact: true }).count() === 1, 'Reviewed business route does not have exactly one Step 4 heading.');
  check(await page.getByRole('heading', { name: 'Step 4 — Campaign Creation', exact: true }).count() === 0, 'Obsolete Step 4 heading remains after review.');
  check(await createStep.locator('input[name="business_campaign_name"]').inputValue() === campaignName, 'Campaign name did not survive business review.');
  check(await createButton.count() === 1 && await createButton.isEnabled(), 'Reviewed create action is not inside the consolidated Step 4 section.');
  check(await page.locator('[data-vms-nonbusiness-create-actions]').isHidden(), 'Individual-route create actions remain visible on the business route.');
  await createStep.locator('input[name="business_campaign_name"]').focus();
  await page.keyboard.press('Tab');
  check(await page.evaluate(() => document.activeElement && document.activeElement.hasAttribute('data-vms-create-campaign-button')), 'Keyboard order does not move from campaign name to the creation action.');
  await page.screenshot({ path: `${outputDir}/step4-desktop.png`, fullPage: true });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForTimeout(150);
  const mobileGeometry = await createStep.evaluate((section) => {
    const button = section.querySelector('[data-vms-create-campaign-button]');
    const sectionRect = section.getBoundingClientRect();
    const buttonRect = button.getBoundingClientRect();
    return {
      documentOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      sectionOverflow: section.scrollWidth - section.clientWidth,
      buttonInside: buttonRect.left >= sectionRect.left && buttonRect.right <= sectionRect.right + 1,
    };
  });
  check(mobileGeometry.documentOverflow <= 1 && mobileGeometry.sectionOverflow <= 1 && mobileGeometry.buttonInside, `390px Step 4 overflowed (${JSON.stringify(mobileGeometry)}).`);
  await page.screenshot({ path: `${outputDir}/step4-mobile.png`, fullPage: true });

  await createButton.click();
  await page.waitForLoadState('domcontentloaded');
  check(new URL(page.url()).hash === '#backstage-outreach-partners', 'Campaign creation did not continue directly to Step 5.');
  const stepFive = page.locator('#backstage-outreach-partners');
  await stepFive.waitFor({ state: 'visible' });
  const stepFivePosition = await stepFive.evaluate((panel) => panel.getBoundingClientRect().top);
  check(stepFivePosition < 844, `Step 5 was not brought into the mobile viewport (${stepFivePosition}px).`);
  check(consoleErrors.length === 0, `Console errors were reported: ${consoleErrors.join(' | ')}`);

  await context.close();
  await browser.close();
  console.log('Reusable-business Step 4 focused browser checks passed.');
})().catch((error) => {
  console.error(error.stack || error.message || error);
  process.exit(1);
});
