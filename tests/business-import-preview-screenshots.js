const { chromium } = require('../../../../vms-dev/node_modules/playwright');
const fs = require('fs');
const path = require('path');

function check(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

(async () => {
  const htmlPath = process.env.BVM_BUSINESS_IMPORT_HTML;
  const outputDir = process.env.BVM_BUSINESS_IMPORT_SCREENSHOTS;
  if (!htmlPath || !outputDir) {
    throw new Error('BVM_BUSINESS_IMPORT_HTML and BVM_BUSINESS_IMPORT_SCREENSHOTS are required.');
  }
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const cases = [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'mobile', width: 390, height: 844 },
  ];
  for (const testCase of cases) {
    const context = await browser.newContext({ viewport: { width: testCase.width, height: testCase.height } });
    const page = await context.newPage();
    await page.goto('file://' + path.resolve(htmlPath), { waitUntil: 'load' });
    const recordCount = await page.locator('.vms-business-import-record').count();
    const detailsCount = await page.locator('.vms-business-import-record-notes details').count();
    check(recordCount === 35, `${testCase.name}: expected all 35 records.`);
    check(detailsCount === 35, `${testCase.name}: expected 35 expandable Notes controls.`);
    check(await page.locator('.vms-business-import-review__summary').innerText() === '35 valid · 0 invalid · 4 missing email', `${testCase.name}: summary changed.`);
    check(await page.locator('.vms-business-import-review__commit button').isEnabled(), `${testCase.name}: clean preview commit should be enabled.`);
    const geometry = await page.evaluate(() => {
      const table = document.querySelector('.vms-business-import-review__table');
      const commit = document.querySelector('.vms-business-import-review__commit');
      const lastNotes = document.querySelector('.vms-business-import-record-notes:last-of-type');
      return {
        documentOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        tableDisplay: getComputedStyle(table).display,
        commitTop: commit.getBoundingClientRect().top,
        lastNotesBottom: lastNotes ? lastNotes.getBoundingClientRect().bottom : 0,
      };
    });
    check(geometry.documentOverflow <= 1, `${testCase.name}: page has horizontal overflow (${geometry.documentOverflow}px).`);
    check(geometry.commitTop >= geometry.lastNotesBottom, `${testCase.name}: commit must follow the complete review table.`);
    if (testCase.name === 'mobile') {
      check(geometry.tableDisplay === 'block', 'mobile: review table did not switch to the stacked layout.');
      const firstBusiness = page.locator('.vms-business-import-record').first().locator('td').first();
      check(await firstBusiness.getAttribute('data-label') === 'Business Name', 'mobile: stacked cells must retain visible field labels.');
    }
    await page.locator('.vms-business-import-record-notes details').first().locator('summary').click();
    check(await page.locator('.vms-business-import-record-notes details').first().getAttribute('open') !== null, `${testCase.name}: Notes details did not expand.`);
    await page.screenshot({ path: path.join(outputDir, `${testCase.name}-top.png`) });
    await page.screenshot({ path: path.join(outputDir, `${testCase.name}.png`), fullPage: true });
    await page.locator('.vms-business-import-review__commit').scrollIntoViewIfNeeded();
    await page.screenshot({ path: path.join(outputDir, `${testCase.name}-bottom.png`) });
    await context.close();
  }
  await browser.close();
  process.stdout.write(`business import preview browser inspection: PASS (${outputDir})\n`);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
