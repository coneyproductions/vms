const { chromium } = require('../../../../vms-dev/node_modules/playwright');
const fs = require('fs');
const path = require('path');

function check(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

(async () => {
  const fixture = JSON.parse(process.env.BVM_BUSINESS_SOURCE_FIXTURE || '{}');
  const user = process.env.VMS_ADMIN_USER;
  const pass = process.env.VMS_ADMIN_PASS;
  const outputDir = process.env.BVM_BUSINESS_SOURCE_SCREENSHOTS;
  if (!fixture.admin_url || !fixture.source_id || !fixture.batch_id || !user || !pass || !outputDir) {
    throw new Error('Fixture, temporary admin credentials, and screenshot directory are required.');
  }
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const cases = [
    { name: 'desktop', width: 1440, height: 1000, submit: true },
    { name: 'mobile', width: 390, height: 844, submit: false },
  ];

  for (const testCase of cases) {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      viewport: { width: testCase.width, height: testCase.height },
    });
    const page = await context.newPage();
    page.setDefaultTimeout(20000);
    await page.goto(new URL('/wp-login.php', fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
    await page.locator('#user_login').fill(user);
    await page.locator('#user_pass').fill(pass);
    await page.locator('#wp-submit').click();
    await page.waitForLoadState('domcontentloaded');
    await page.goto(fixture.admin_url, { waitUntil: 'domcontentloaded' });

    if (testCase.submit) {
      await page.locator('input[name="campaign_name"]').fill('Disposable Browser Business Campaign');
      await page.locator('input[name="recipient_source_mode"][value="business_source"]').check();
      const route = page.locator('[data-vms-recipient-source-business]');
      await route.locator('select[name="related_source_id"]').selectOption(String(fixture.source_id));
      await route.locator('select[name="related_batch_id"]').selectOption(String(fixture.batch_id));
      await route.locator('button[value="business_source_preview"]').click();
      await page.waitForLoadState('domcontentloaded');
    }

    const preview = page.locator('#vms-outreach-business-source-preview');
    await preview.waitFor({ state: 'visible' });
    const rows = preview.locator('.vms-pass-business-source-table tbody tr');
    check(await rows.count() === 35, `${testCase.name}: expected all 35 active businesses.`);
    check(await preview.getByText('35', { exact: true }).count() >= 2, `${testCase.name}: business totals are incomplete.`);
    check(await preview.getByText('Reference only - email delivery is not prepared', { exact: true }).count() === 21, `${testCase.name}: expected 21 email-reference indicators.`);
    check(await preview.getByText('Email delivery unavailable', { exact: true }).count() === 14, `${testCase.name}: expected 14 missing-email indicators.`);
    check(await preview.getByText('9', { exact: true }).count() >= 1, `${testCase.name}: individual batch quantity is missing.`);
    check(await preview.getByText('70', { exact: true }).count() >= 1, `${testCase.name}: shared admission cap is missing.`);
    check(await page.getByText('SAMPLE DATA - NOT A DELIVERY PREVIEW', { exact: true }).count() === 1, `${testCase.name}: message sample label is not prominent.`);
    check(await page.getByRole('button', { name: 'Create Campaign and Continue to Business QR Setup' }).count() === 1, `${testCase.name}: Continue action is missing.`);
    check(await page.locator('.vms-pass-business-source-table script, .vms-pass-business-source-table img').count() === 0, `${testCase.name}: stored HTML was not escaped.`);

    const geometry = await page.evaluate(() => {
      const table = document.querySelector('.vms-pass-business-source-table');
      const firstCell = table ? table.querySelector('tbody td') : null;
      return {
        documentOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        tableMinWidth: table ? getComputedStyle(table).minWidth : '',
        firstCellDisplay: firstCell ? getComputedStyle(firstCell).display : '',
        firstCellLabel: firstCell ? firstCell.getAttribute('data-label') : '',
        offenders: Array.from(document.querySelectorAll('body *')).map((element) => {
          const rect = element.getBoundingClientRect();
          return { element, right: rect.right, width: rect.width };
        }).filter((item) => item.right > document.documentElement.clientWidth + 1 || item.width > document.documentElement.clientWidth + 1)
          .slice(0, 8)
          .map((item) => `${item.element.tagName.toLowerCase()}.${item.element.className || ''}:${Math.round(item.width)}@${Math.round(item.right)}`),
      };
    });
    check(geometry.documentOverflow <= 1, `${testCase.name}: page has horizontal overflow (${geometry.documentOverflow}px): ${geometry.offenders.join(', ')}`);
    if (testCase.name === 'mobile') {
      check(geometry.tableMinWidth === '0px', 'mobile: business table did not remove its desktop minimum width.');
      check(geometry.firstCellDisplay === 'grid' && geometry.firstCellLabel === 'Business', 'mobile: business rows did not switch to labeled cards.');
    }

    await preview.scrollIntoViewIfNeeded();
    await preview.screenshot({ path: path.join(outputDir, `${testCase.name}-business-review.png`) });
    await context.close();
  }

  await browser.close();
  process.stdout.write(`Business Source campaign browser inspection PASS (${outputDir})\n`);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
