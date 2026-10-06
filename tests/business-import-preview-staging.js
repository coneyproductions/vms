const { chromium } = require('../../../../vms-dev/node_modules/playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');

function check(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function readLogin(file) {
  const values = {};
  for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
    const match = line.match(/^([a-z]+):\s*(.+)$/i);
    if (match) {
      values[match[1].toLowerCase()] = match[2];
    }
  }
  check(values.username && values.password, 'The staging login file is incomplete.');
  return values;
}

function csvCell(value) {
  const text = String(value ?? '');
  return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

function csv(headers, rows) {
  return [headers, ...rows].map((row) => row.map(csvCell).join(',')).join('\n') + '\n';
}

(async () => {
  const baseUrl = process.env.BVM_STAGING_URL || 'https://staging.serenaderange.com';
  const loginFile = process.env.BVM_STAGING_LOGIN;
  const sourceId = Number(process.env.BVM_STAGING_SOURCE_ID || 0);
  const outputDir = process.env.BVM_STAGING_ACCEPTANCE_OUTPUT;
  check(loginFile && outputDir && sourceId > 0, 'Staging login, Source ID, and output directory are required.');
  const credentials = readLogin(loginFile);
  fs.mkdirSync(outputDir, { recursive: true });
  const fixtureDir = fs.mkdtempSync(path.join(os.tmpdir(), 'bvm-business-import-staging-'));
  const headers = ['external_id', 'business_name', 'contact_name', 'email', 'phone', 'website', 'address', 'city', 'state', 'postal_code', 'notes'];
  const rows = [];
  for (let index = 1; index <= 35; index += 1) {
    rows.push([
      `staging-preview-${index}`,
      `Staging Preview Fitness ${index}`,
      `Preview Contact ${index}`,
      (index - 1) % 10 === 0 ? '' : `preview${index}@example.test`,
      `555-90${String(index).padStart(2, '0')}`,
      `https://preview${index}.example.test/a/very/long/review/path`,
      `${index} Preview Avenue`,
      'Staging City',
      'IL',
      `60${String(index).padStart(3, '0')}`,
      `Synthetic staging research metadata ${index}`,
    ]);
  }
  const fixtures = {
    clean: path.join(fixtureDir, 'businesses-clean.csv'),
    unsupported: path.join(fixtureDir, 'businesses-unsupported.csv'),
    duplicate: path.join(fixtureDir, 'businesses-duplicate.csv'),
    invalid: path.join(fixtureDir, 'businesses-invalid.csv'),
  };
  fs.writeFileSync(fixtures.clean, csv(headers, rows));
  fs.writeFileSync(fixtures.unsupported, csv(['business_name', 'email', 'research_score'], [['Unsupported Header Fitness', '', 'A+']]));
  fs.writeFileSync(fixtures.duplicate, csv(['business_name', 'address', 'address_line'], [['Duplicate Address Fitness', 'First Address', 'Second Address']]));
  fs.writeFileSync(fixtures.invalid, csv(['business_name', 'email', 'notes'], [['Valid Fitness', '', 'Allowed blank email'], ['', 'owner@example.test', 'Missing name'], ['Bad Email Fitness', 'not-an-email', 'Invalid email']]));

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  page.setDefaultTimeout(20000);
  const consoleErrors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text());
    }
  });

  await page.goto(new URL('/wp-login.php', baseUrl).toString(), { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(credentials.username);
  await page.locator('#user_pass').fill(credentials.password);
  await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('#wp-submit').click()]);
  check(!page.url().includes('wp-login.php'), 'Staging WordPress login failed.');

  const adminUrl = new URL('/wp-admin/admin.php', baseUrl);
  adminUrl.searchParams.set('page', 'vms-passes');
  adminUrl.searchParams.set('tab', 'sources');
  adminUrl.searchParams.set('source_id', String(sourceId));
  await page.goto(adminUrl.toString(), { waitUntil: 'domcontentloaded' });
  const bodyClass = await page.locator('body').getAttribute('class');
  const userMatch = String(bodyClass || '').match(/\buser-id-(\d+)\b/);
  const userId = userMatch ? Number(userMatch[1]) : 0;
  const importCard = () => page.locator('section.vms-pass-card').filter({ has: page.locator('h3', { hasText: 'Import Businesses' }) }).first();
  check(await importCard().count() === 1, 'Import Businesses card is unavailable on the staging Source.');
  check(await page.locator('link[href*="/backstage-outreach/assets/css/outreach-admin.css"]').count() === 1, 'The standalone Outreach stylesheet is unavailable on the staging Sources screen.');

  async function preview(file) {
    const card = importCard();
    await card.locator('input[name="business_csv"]').setInputFiles(file);
    await Promise.all([page.waitForLoadState('domcontentloaded'), card.locator('button', { hasText: 'Preview CSV' }).click()]);
    check(await importCard().count() === 1, 'Import Businesses card disappeared after preview.');
  }

  await preview(fixtures.clean);
  let review = importCard().locator('[data-vms-business-import-review]');
  check(await review.count() === 1, 'The clean staging preview did not render.');
  check((await review.locator('.vms-business-import-record').count()) === 35, 'The staging preview did not retain all 35 businesses.');
  check((await review.locator('.vms-business-import-record-notes details').count()) === 35, 'Every staging record must retain expandable Notes.');
  check((await review.locator('.vms-business-import-review__summary').innerText()).trim() === '35 valid · 0 invalid · 4 missing email', 'The staging clean-preview summary is incorrect.');
  check((await review.locator('.vms-business-import-email-note').count()) === 4, 'The staging preview must identify all four unavailable email deliveries.');
  const firstRecordText = await review.locator('.vms-business-import-record').first().innerText();
  check(firstRecordText.includes('1 Preview Avenue') && firstRecordText.includes('Staging City IL 60001'), 'The address alias did not render the complete first address.');
  check(await review.locator('.vms-business-import-review__commit button').isEnabled(), 'The clean staging preview should be committable, but acceptance must not click it.');
  await review.scrollIntoViewIfNeeded();
  await page.screenshot({ path: path.join(outputDir, 'staging-desktop-review-viewport.png') });
  await page.screenshot({ path: path.join(outputDir, 'staging-desktop-clean.png'), fullPage: true });

  await page.setViewportSize({ width: 390, height: 844 });
  review = importCard().locator('[data-vms-business-import-review]');
  const mobileGeometry = await page.evaluate(() => {
    const viewportWidth = document.documentElement.clientWidth;
    const review = document.querySelector('[data-vms-business-import-review]').getBoundingClientRect();
    const record = document.querySelector('.vms-business-import-record').getBoundingClientRect();
    return {
      viewportWidth,
      reviewLeft: review.left,
      reviewRight: review.right,
      recordLeft: record.left,
      recordRight: record.right,
      tableDisplay: getComputedStyle(document.querySelector('.vms-business-import-review__table')).display,
    };
  });
  check(mobileGeometry.reviewLeft >= 0 && mobileGeometry.recordLeft >= 0 && mobileGeometry.reviewRight <= mobileGeometry.viewportWidth + 1 && mobileGeometry.recordRight <= mobileGeometry.viewportWidth + 1, 'The staging import review exceeds the mobile viewport.');
  check(mobileGeometry.tableDisplay === 'block', 'The staging mobile preview did not switch to stacked cards.');
  await review.locator('.vms-business-import-record-notes details').first().locator('summary').click();
  await review.scrollIntoViewIfNeeded();
  await page.screenshot({ path: path.join(outputDir, 'staging-mobile-review-viewport.png') });
  await page.screenshot({ path: path.join(outputDir, 'staging-mobile-clean.png'), fullPage: true });
  await page.setViewportSize({ width: 1440, height: 1000 });

  await preview(fixtures.unsupported);
  review = importCard().locator('[data-vms-business-import-review]');
  await page.screenshot({ path: path.join(outputDir, 'staging-unsupported-header.png'), fullPage: true });
  check(await page.getByText('Unsupported CSV column "research_score". Remove it before committing.', { exact: true }).isVisible(), 'Unsupported headers are not visibly blocked on staging.');
  check(await review.locator('.vms-business-import-review__commit button').isDisabled(), 'Unsupported headers must disable staging Commit.');

  await preview(fixtures.duplicate);
  review = importCard().locator('[data-vms-business-import-review]');
  check(await page.getByText('CSV columns "address" and "address_line" both map to "address_line". Keep only one of them.', { exact: true }).isVisible(), 'Duplicate address mappings are not visibly rejected on staging.');
  check(await review.locator('.vms-business-import-review__commit button').isDisabled(), 'Duplicate address mappings must disable staging Commit.');

  await preview(fixtures.invalid);
  review = importCard().locator('[data-vms-business-import-review]');
  check((await review.locator('.vms-business-import-record--invalid').count()) === 2, 'Both invalid staging rows must remain visible.');
  const invalidText = await review.innerText();
  check(invalidText.includes('Business name is required.') && invalidText.includes('Enter a valid email address or leave it blank.'), 'Specific staging row errors are missing.');
  check(await review.locator('.vms-business-import-review__commit button').isDisabled(), 'Invalid rows must disable staging Commit.');
  await review.locator('.vms-business-import-record--invalid').first().scrollIntoViewIfNeeded();
  await page.screenshot({ path: path.join(outputDir, 'staging-invalid-rows-viewport.png') });
  await page.screenshot({ path: path.join(outputDir, 'staging-invalid-rows.png'), fullPage: true });

  await preview(fixtures.clean);
  check(await importCard().locator('.vms-business-import-review__commit button').isEnabled(), 'The replacement clean preview did not become current.');
  await preview({ name: 'failed-replacement.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('not a csv') });
  check(await importCard().locator('[data-vms-business-import-review]').count() === 0, 'A failed replacement left the prior preview visible.');

  const result = {
    status: 'PASS',
    baseUrl,
    sourceId,
    userId,
    cleanRecords: 35,
    missingEmail: 4,
    addressAlias: true,
    unsupportedHeaderBlocked: true,
    duplicateHeaderBlocked: true,
    invalidRowsBlocked: 2,
    failedReplacementRemovedPreview: true,
    commitClicked: false,
    consoleErrors,
    screenshots: fs.readdirSync(outputDir).sort(),
  };
  fs.writeFileSync(path.join(outputDir, 'acceptance.json'), JSON.stringify(result, null, 2) + '\n');
  process.stdout.write(JSON.stringify(result) + '\n');
  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
