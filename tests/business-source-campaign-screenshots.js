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
  if (!fixture.admin_url || !fixture.source_id || !fixture.existing_source_id || !user || !pass || !outputDir) {
    throw new Error('Fixture, temporary admin credentials, and screenshot directory are required.');
  }
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  let publicFlyerUrl = '';
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

    if (testCase.submit) {
      const routeChoice = page.locator('[data-vms-section-id="recipient_source"]');
      const campaignSection = page.locator('[data-vms-section-id="campaign"]');
      check((await routeChoice.boundingBox()).y < (await campaignSection.boundingBox()).y, 'desktop: route choice did not appear before route-specific campaign fields.');
      const standardCampaign = page.locator('[data-vms-nonbusiness-campaign-fields]');
      const standardOffer = page.locator('[data-vms-nonbusiness-offer-fields]');
      await standardCampaign.locator('input[name="campaign_name"]').fill('Fresh Café — route switch draft');
      await standardOffer.locator('input[name="admissions_per_recipient"]').fill('3');
      await standardOffer.locator('select[name="validity_type"]').selectOption('date_range');
      await standardOffer.locator('input[name="start_date"]').fill('2031-03-01');
      await standardOffer.locator('input[name="end_date"]').fill('2031-03-31');
      await page.locator('input[name="recipient_source_mode"][value="business_source"]').check();
      const route = page.locator('[data-vms-recipient-source-business]');
      check(await route.locator('input[name="business_campaign_name"]').inputValue() === 'Fresh Café — route switch draft', 'desktop: fresh-page campaign name was not transferred to the business route.');
      check(await route.locator('input[name="business_batch_admissions_per_link"]').inputValue() === '3', 'desktop: admissions-per-customer draft was not transferred.');
      check(await route.locator('select[name="business_batch_validity_type"]').inputValue() === 'date_range', 'desktop: date-range scope was not transferred.');
      check(await route.locator('input[name="business_batch_start_date"]').inputValue() === '2031-03-01' && await route.locator('input[name="business_batch_end_date"]').inputValue() === '2031-03-31', 'desktop: date-range values were not transferred.');
      await route.locator('[data-vms-business-batch-create]').evaluate((details) => { details.open = true; });
      await route.locator('input[name="business_batch_start_date"]').fill('2031-03-02');
      await page.locator('input[name="recipient_source_mode"][value="csv_new"]').check();
      await standardOffer.locator('input[name="start_date"]').fill('2031-03-03');
      await page.locator('input[name="recipient_source_mode"][value="business_source"]').check();
      await page.waitForTimeout(100);
      check(await route.locator('input[name="business_batch_start_date"]').inputValue() === '2031-03-02', 'desktop: independently edited business draft was overwritten on route switch.');
      const conflictState = {
        hidden: await page.locator('[data-vms-business-draft-conflict]').getAttribute('hidden'),
        touched: await route.locator('input[name="business_batch_touched_fields"]').inputValue(),
        source: await standardOffer.locator('input[name="start_date"]').inputValue(),
        target: await route.locator('input[name="business_batch_start_date"]').inputValue(),
        batch: await route.locator('select[name="related_batch_id"]').inputValue(),
      };
      check(conflictState.hidden === null, `desktop: conflicting route drafts were not explained (${JSON.stringify(conflictState)}).`);
      check(await route.locator('button[value="business_source_preview"]').isDisabled(), 'desktop: Preview was enabled before selecting prerequisites.');
      await route.locator('select[name="related_source_id"]').selectOption(String(fixture.existing_source_id));
      check(await route.locator('select[name="related_batch_id"] option').count() === 3, 'desktop: existing Source did not expose exactly its two eligible batches.');
      check(await route.locator(`select[name="related_batch_id"] option[value="${fixture.existing_free_batch_id}"]`).count() === 1, 'desktop: existing complimentary batch is unavailable.');
      check(await route.locator(`select[name="related_batch_id"] option[value="${fixture.existing_paid_batch_id}"]`).count() === 1, 'desktop: existing 50%-off batch is unavailable.');
      await route.locator('select[name="related_batch_id"]').selectOption(String(fixture.existing_paid_batch_id));
      check(await route.getByText('Admission Offer — 50% off admission for up to 2 people', { exact: true }).count() >= 1, 'desktop: existing paid batch wording is not explicit.');
      await route.locator('select[name="related_source_id"]').selectOption(String(fixture.source_id));
      check(await route.locator('select[name="related_batch_id"] option').count() === 1, 'desktop: 35-business Source exposed an inactive, unsupported, or unrelated batch.');
      check(await route.locator('[data-vms-business-empty]').isVisible(), 'desktop: guided no-batch empty state is missing.');
      check(await route.getByText('No eligible offer batch for this Source', { exact: true }).count() === 1, 'desktop: no-batch empty-state message is duplicated.');
      check(await route.getByRole('button', { name: 'Create an offer batch for this Source' }).count() === 1, 'desktop: in-Outreach batch action is missing.');
      check(await route.locator('button[value="business_source_preview"]').isDisabled(), 'desktop: Preview remained enabled without an eligible pair.');
      await route.getByRole('button', { name: 'Create an offer batch for this Source' }).click();
      await route.locator('select[name="business_batch_validity_type"]').selectOption('date_range');
      const compactWidths = await route.evaluate((root) => {
        const date = root.querySelector('input[name="business_batch_start_date"]');
        const expiry = root.querySelector('input[name="business_batch_expires_at"]');
        return {
          date: date ? date.getBoundingClientRect().width : 0,
          expiry: expiry ? expiry.getBoundingClientRect().width : 0,
        };
      });
      check(compactWidths.date > 0 && compactWidths.date <= 193, `desktop: date input is not compact (${compactWidths.date}px).`);
      check(compactWidths.expiry > compactWidths.date && compactWidths.expiry <= 305, `desktop: expiry input width is not appropriately distinct (${compactWidths.expiry}px).`);
      await route.locator('select[name="business_batch_validity_type"]').selectOption('any_event');
      await route.locator('button[value="business_batch_preview"]').click();
      await page.waitForLoadState('domcontentloaded');
      check(new URL(page.url()).hash === '#vms-outreach-business-source-setup', 'desktop: batch validation did not return to the stable workflow anchor.');
      await page.waitForTimeout(750);
      check(await page.evaluate(() => document.activeElement && document.activeElement.getAttribute('name')) === 'business_batch_offer_type', 'desktop: first invalid batch field did not receive focus.');
      check(await page.locator('[data-vms-business-batch-create]').getAttribute('open') !== null, 'desktop: relevant batch section did not reopen after validation.');
      await route.locator('input[name="business_campaign_name"]').fill('Draft Café — preserved across batch setup');
      await route.locator('input[name="business_batch_name"]').fill('Business Source Browser Fixture Created In Outreach');
      await route.locator('select[name="business_batch_offer_type"]').selectOption('free');
      check(await route.locator('[data-vms-business-offer-amount]').isHidden() && await route.locator('input[name="business_batch_offer_amount"]').isDisabled(), 'desktop: complimentary offer exposed a discount amount.');
      await route.locator('select[name="business_batch_offer_type"]').selectOption('percent');
      check(await route.locator('[data-vms-business-offer-amount]').isVisible() && !(await route.locator('input[name="business_batch_offer_amount"]').isDisabled()), 'desktop: percentage offer did not expose its amount.');
      check(await route.locator('[data-vms-business-offer-suffix]').isVisible() && await route.locator('input[name="business_batch_offer_amount"]').getAttribute('max') === '100', 'desktop: percentage amount did not expose percent units and bounds.');
      await route.locator('input[name="business_batch_offer_amount"]').fill('37.5');
      await route.locator('select[name="business_batch_offer_type"]').selectOption('fixed');
      check(await route.locator('[data-vms-business-offer-prefix]').isVisible() && await route.locator('input[name="business_batch_offer_amount"]').inputValue() === '37.5' && await route.locator('input[name="business_batch_offer_amount"]').getAttribute('max') === '99999999.99', 'desktop: fixed offer did not preserve the switched draft amount or currency bound.');
      await route.locator('select[name="business_batch_offer_type"]').selectOption('free');
      check(await route.locator('input[name="business_batch_quantity"]').count() === 0, 'desktop: reusable-business setup still requests an individual claim-link quantity.');
      await route.locator('input[name="business_batch_admissions_per_link"]').fill('2');
      await route.locator('input[name="business_batch_total_admission_cap"]').fill('70');
      await route.locator('input[name="business_batch_per_business_admission_cap"]').fill('2');
      await route.locator('select[name="business_batch_validity_type"]').selectOption('date_range');
      await route.locator('input[name="business_batch_start_date"]').fill('');
      await route.locator('input[name="business_batch_end_date"]').fill('');
      await route.locator('button[value="business_batch_preview"]').click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(250);
      check(await route.locator('select[name="business_batch_validity_type"]').inputValue() === 'date_range', 'desktop: validation did not restore the selected scope.');
      check(await page.evaluate(() => document.activeElement && document.activeElement.getAttribute('name')) === 'business_batch_start_date', 'desktop: restored Date Range error did not focus the first invalid scope field.');
      check(!(await route.locator('input[name="business_batch_start_date"]').isDisabled()) && !(await route.locator('input[name="business_batch_end_date"]').isDisabled()), 'desktop: restored Date Range controls were not enabled.');
      check(await route.locator('select[name="business_batch_single_event_plan_id"]').isDisabled() && await route.locator('input[name="business_batch_season_label"]').isDisabled(), 'desktop: restored Date Range exposed irrelevant controls.');
      await route.locator('select[name="business_batch_validity_type"]').selectOption('any_event');
      const eventControl = route.locator('select[name="business_batch_single_event_plan_id"]');
      const startControl = route.locator('input[name="business_batch_start_date"]');
      const endControl = route.locator('input[name="business_batch_end_date"]');
      const seasonControl = route.locator('input[name="business_batch_season_label"]');
      check(await eventControl.isDisabled() && await startControl.isDisabled() && await endControl.isDisabled() && await seasonControl.isDisabled(), 'desktop: Any Event left irrelevant controls enabled.');
      await route.locator('select[name="business_batch_validity_type"]').selectOption('date_range');
      check(!(await startControl.isDisabled()) && !(await endControl.isDisabled()) && await eventControl.isDisabled() && await seasonControl.isDisabled(), 'desktop: Date Range conditional controls are incorrect.');
      await startControl.fill('2030-01-01');
      await endControl.fill('2030-02-01');
      await route.locator('select[name="business_batch_validity_type"]').selectOption('season');
      check(!(await startControl.isDisabled()) && !(await endControl.isDisabled()) && !(await seasonControl.isDisabled()) && await eventControl.isDisabled(), 'desktop: Season conditional controls are incorrect.');
      await seasonControl.fill('Café Winter — 2030');
      await route.locator('select[name="business_batch_validity_type"]').selectOption('any_event');
      await route.locator('select[name="business_batch_validity_type"]').selectOption('season');
      check(await startControl.inputValue() === '2030-01-01' && await endControl.inputValue() === '2030-02-01' && await seasonControl.inputValue() === 'Café Winter — 2030', 'desktop: scope switching did not preserve draft values.');
      await route.locator('select[name="business_batch_validity_type"]').selectOption('any_event');
      await route.locator('textarea[name="business_batch_notes"]').fill('Café — You’ve… retained ひらがな é');
      await route.locator('button[value="business_batch_preview"]').click();
      await page.waitForLoadState('domcontentloaded');
      check(await page.getByRole('heading', { name: 'Read-only Batch Review' }).count() === 1, 'desktop: read-only new-batch review is missing.');
      check(await route.locator('select[name="related_batch_id"] option').count() === 1, 'desktop: read-only review mutated the batch list before confirmation.');
      check(await route.locator('input[name="business_campaign_name"]').inputValue() === 'Draft Café — preserved across batch setup', 'desktop: campaign draft was not preserved through batch review.');
      const batchCommitButton = route.locator('button[value="business_batch_commit"]');
      const batchCommitReplay = await batchCommitButton.locator('xpath=ancestor::form').evaluate((form) => ({
        action: new URL(form.getAttribute('action') || window.location.href, window.location.href).toString(),
        fields: Object.fromEntries(Array.from(new FormData(form).entries())),
      }));
      await batchCommitButton.click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(250);
      check(new URL(page.url()).hash === '#vms-outreach-business-review-action', 'desktop: confirmed batch did not bring the next business-review action into view.');
      const reviewFocusState = await page.evaluate(() => {
        const button = document.querySelector('[data-vms-business-preview-button]');
        const rect = button ? button.getBoundingClientRect() : null;
        return {
          disabled: !!button && button.disabled,
          top: rect ? rect.top : -1,
          bottom: rect ? rect.bottom : -1,
          viewport: window.innerHeight,
          source: document.querySelector('[data-vms-business-source]') ? document.querySelector('[data-vms-business-source]').value : '',
          batch: document.querySelector('[data-vms-business-batch]') ? document.querySelector('[data-vms-business-batch]').value : '',
        };
      });
      check(!reviewFocusState.disabled && reviewFocusState.top >= 50 && reviewFocusState.bottom <= reviewFocusState.viewport, `desktop: confirmed batch did not enable and bring the next Business Review action into view (${JSON.stringify(reviewFocusState)}).`);
      const createdBatchId = await route.locator('select[name="related_batch_id"]').inputValue();
      check(createdBatchId !== '0', 'desktop: confirmed batch was not selected on return.');
      check(await route.getByText('Complimentary admission', { exact: true }).count() >= 1, 'desktop: confirmed complimentary batch wording is missing.');
      check(await route.locator('input[name="business_campaign_name"]').inputValue() === 'Draft Café — preserved across batch setup', 'desktop: campaign draft was not preserved after batch creation.');
      check(await route.locator('select[name="related_batch_id"] option').count() === 2, 'desktop: picker did not contain exactly the placeholder and eligible Source batch.');
      check(await route.locator(`select[name="related_batch_id"] option[value="${fixture.unrelated_batch_id}"]`).count() === 0, 'desktop: unrelated eligible batch was exposed.');
      const duplicateResponse = await page.request.post(encodeURI(batchCommitReplay.action), { form: batchCommitReplay.fields, maxRedirects: 0 });
      check(duplicateResponse.status() === 302, 'desktop: duplicate batch confirmation did not fail closed with a redirect.');
      await page.goto(fixture.admin_url, { waitUntil: 'domcontentloaded' });
      await page.locator('input[name="recipient_source_mode"][value="business_source"]').check();
      const replayRoute = page.locator('[data-vms-recipient-source-business]');
      await replayRoute.locator('select[name="related_source_id"]').selectOption(String(fixture.source_id));
      check(await replayRoute.locator('select[name="related_batch_id"] option').count() === 2, 'desktop: duplicate confirmation created a second batch definition.');
      await replayRoute.locator('select[name="related_batch_id"]').selectOption(createdBatchId);
      check(await replayRoute.locator('input[name="business_campaign_name"]').inputValue() === 'Draft Café — preserved across batch setup', 'desktop: campaign draft was lost after duplicate confirmation was rejected.');
      check(!(await replayRoute.locator('button[value="business_source_preview"]').isDisabled()), 'desktop: Preview did not enable for a valid pair.');
      await replayRoute.locator('button[value="business_source_preview"]').click();
      await page.waitForLoadState('domcontentloaded');
    }

    const preview = page.locator('#vms-outreach-business-source-preview');
    await preview.waitFor({ state: 'visible' });
    let screenshotTarget = preview;
    const rows = preview.locator('.vms-pass-business-source-table tbody tr');
    check(await rows.count() === 35, `${testCase.name}: expected all 35 active businesses.`);
    check(await preview.getByText('35', { exact: true }).count() >= 2, `${testCase.name}: business totals are incomplete.`);
    check(await preview.getByText('Reference only - email delivery is not prepared', { exact: true }).count() === 21, `${testCase.name}: expected 21 email-reference indicators.`);
    check(await preview.getByText('Email delivery unavailable', { exact: true }).count() === 14, `${testCase.name}: expected 14 missing-email indicators.`);
    check(await preview.getByText('Admissions per customer', { exact: true }).count() >= 1, `${testCase.name}: per-customer admissions label is missing.`);
    check(await preview.getByText('Total admissions available across all businesses', { exact: true }).count() >= 1, `${testCase.name}: total admissions label is missing.`);
    check(await preview.getByText('Total admissions allowed per business', { exact: true }).count() >= 1, `${testCase.name}: per-business admission label is missing.`);
    check(await preview.getByText('70', { exact: true }).count() >= 1, `${testCase.name}: shared admission cap is missing.`);
    check(await preview.getByText('2', { exact: true }).count() >= 1, `${testCase.name}: per-business admission cap is missing.`);
    check(await page.getByText('SAMPLE DATA - NOT A DELIVERY PREVIEW', { exact: true }).count() === 1, `${testCase.name}: message sample label is not prominent.`);
    check(await page.getByRole('button', { name: 'Create Campaign and Continue to Business QR Setup' }).count() === 1, `${testCase.name}: Continue action is missing.`);
    check(await page.getByText('Admission Offer — 50% off admission for up to 2 people', { exact: true }).count() === 0, `${testCase.name}: complimentary fixture was mislabeled as paid.`);
    check(await page.locator('.vms-pass-business-source-table script, .vms-pass-business-source-table img').count() === 0, `${testCase.name}: stored HTML was not escaped.`);

    if (testCase.name === 'mobile') {
      const route = page.locator('[data-vms-recipient-source-business]');
      const createdBatchId = await route.locator('select[name="related_batch_id"]').inputValue();
      check(createdBatchId !== '0', 'mobile: the reviewed created batch was not preserved.');
      await route.locator('select[name="related_source_id"]').selectOption(String(fixture.existing_source_id));
      check(await route.locator('select[name="related_batch_id"]').inputValue() === '0', 'mobile: incompatible batch selection was not cleared after Source change.');
      check(await page.getByRole('button', { name: 'Create Campaign and Continue to Business QR Setup' }).isDisabled(), 'mobile: reviewed campaign was not invalidated after Source change.');
      check(await route.locator('[data-vms-preview-stale-note]').isVisible(), 'mobile: stale-review guidance was not exposed after Source change.');
      await route.locator('select[name="related_source_id"]').selectOption(String(fixture.source_id));
      await route.locator('select[name="related_batch_id"]').selectOption(createdBatchId);
      check(await page.getByRole('button', { name: 'Create Campaign and Continue to Business QR Setup' }).isDisabled(), 'mobile: stale review was incorrectly restored by reverting selections.');
      await route.locator('button[value="business_source_preview"]').click();
      await page.waitForLoadState('domcontentloaded');
      await page.getByRole('button', { name: 'Create Campaign and Continue to Business QR Setup' }).click();
      await page.waitForLoadState('domcontentloaded');
      const qrPanel = page.locator('#backstage-outreach-partners');
      await qrPanel.waitFor({ state: 'visible' });
      screenshotTarget = qrPanel;
      check(await qrPanel.locator('input[data-backstage-business]').count() === 35, 'mobile: QR setup did not carry all 35 reviewed businesses.');
      check(await qrPanel.locator('select[name="distribution_type"]').inputValue() === 'complimentary', 'mobile: complimentary batch did not retain complimentary behavior.');
      check(await qrPanel.locator('input[name="admission_cap"]').inputValue() === '2', 'mobile: reviewed per-business limit did not carry into QR setup.');
      await qrPanel.getByRole('button', { name: 'Review Selection' }).click();
      await page.waitForLoadState('domcontentloaded');
      const linkReviewText = await page.locator('[data-vms-tour="outreach-reviewed-preview"]').innerText();
      check(linkReviewText.includes('35 active business links will remain or become active.'), 'mobile: reusable-link review did not retain all 35 businesses.');
      await page.getByRole('button', { name: 'Save Reviewed Links' }).click();
      await page.waitForLoadState('domcontentloaded');
      const resultRows = page.locator('[data-vms-tour="outreach-business-results"] tbody tr');
      check(await resultRows.count() === 35, 'mobile: expected 35 saved reusable business links.');
      const reusableLinks = await resultRows.locator('input[data-backstage-copy-value]').evaluateAll((inputs) => inputs.map((input) => input.value));
      const customerLinks = reusableLinks.filter((value) => value.includes('/guest-pass/partner/'));
      const flyerLinks = reusableLinks.filter((value) => value.includes('/guest-pass/business-flyer/'));
      check(new Set(customerLinks).size === 35, 'mobile: reusable business links were not 35 unique signed partner URLs.');
      check(new Set(flyerLinks).size === 35, 'mobile: flyer links were not 35 unique signed public URLs.');
      check(await resultRows.getByRole('button', { name: 'Copy flyer link' }).count() === 35, 'mobile: Copy flyer link actions are missing.');
      publicFlyerUrl = flyerLinks[0] || '';
      const campaignStatus = page.locator('select[name="status"]');
      const activationPost = await campaignStatus.evaluate((field) => {
        const form = field.form;
        const fields = Object.fromEntries(Array.from(new FormData(form).entries()));
        fields.status = 'active';
        fields.save_mode = 'standard';
        return { action: new URL(form.getAttribute('action') || window.location.href, window.location.href).toString(), fields };
      });
      const activationResponse = await page.request.post(activationPost.action, { form: activationPost.fields, maxRedirects: 0 });
      check(activationResponse.status() === 302, 'mobile: disposable campaign activation did not return a safe redirect.');
      await page.goto(new URL(activationResponse.headers().location, fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
      check(await page.locator('select[name="status"]').inputValue() === 'active', 'mobile: disposable campaign could not be activated for public flyer inspection.');
    }

    const geometry = await page.evaluate(() => {
      const table = document.querySelector('.vms-pass-business-source-table') || document.querySelector('[data-vms-tour="outreach-business-results"]');
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
    check(consoleErrors.length === 0, `${testCase.name}: browser console errors: ${consoleErrors.join(' | ')}`);

    await screenshotTarget.scrollIntoViewIfNeeded();
    await screenshotTarget.screenshot({ path: path.join(outputDir, `${testCase.name}-business-review.png`) });
    await page.screenshot({ path: path.join(outputDir, `${testCase.name}-business-review-viewport.png`), fullPage: false });
    await context.close();
  }

  check(publicFlyerUrl !== '', 'public flyer URL was not captured from QR results.');
  for (const flyerCase of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
    const flyerContext = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: flyerCase.width, height: flyerCase.height } });
    const flyerPage = await flyerContext.newPage();
    const flyerErrors = [];
    flyerPage.on('console', (message) => { if (message.type() === 'error') flyerErrors.push(message.text()); });
    flyerPage.on('pageerror', (error) => flyerErrors.push(error.message));
    const response = await flyerPage.goto(publicFlyerUrl, { waitUntil: 'networkidle' });
    check(response && response.status() === 200, `${flyerCase.name}: public flyer did not return HTTP 200.`);
    check(!flyerPage.url().includes('wp-login.php'), `${flyerCase.name}: public flyer required WordPress login.`);
    check(await flyerPage.getByRole('button', { name: 'Print / Save as PDF' }).count() === 1, `${flyerCase.name}: print/PDF control is missing.`);
    check(await flyerPage.getByText('Complimentary Guest Passes for up to 2 people per customer', { exact: true }).count() === 1, `${flyerCase.name}: complimentary flyer wording is inaccurate.`);
    check(await flyerPage.getByText('Total admissions allowed through this business:', { exact: true }).count() === 1, `${flyerCase.name}: capped flyer omits its real per-business limit.`);
    check(await flyerPage.locator('img.qr').count() === 1 && (await flyerPage.locator('img.qr').getAttribute('src') || '').startsWith('data:image/png;base64,'), `${flyerCase.name}: flyer QR was not rendered from the actual customer offer link.`);
    const flyerGeometry = await flyerPage.evaluate(() => ({ overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth, sheetWidth: document.querySelector('.sheet').getBoundingClientRect().width }));
    check(flyerGeometry.overflow <= 1 && flyerGeometry.sheetWidth <= flyerCase.width, `${flyerCase.name}: flyer overflows its viewport.`);
    check(flyerErrors.length === 0, `${flyerCase.name}: flyer console errors: ${flyerErrors.join(' | ')}`);
    await flyerPage.screenshot({ path: path.join(outputDir, `${flyerCase.name}-public-flyer.png`), fullPage: true });
    if (flyerCase.name === 'desktop') {
      await flyerPage.emulateMedia({ media: 'print' });
      await flyerPage.pdf({ path: path.join(outputDir, 'public-flyer-letter.pdf'), format: 'Letter', printBackground: true, preferCSSPageSize: true });
    }
    await flyerContext.close();
  }

  const noScriptContext = await browser.newContext({
    ignoreHTTPSErrors: true,
    javaScriptEnabled: false,
    viewport: { width: 1024, height: 768 },
  });
  const noScriptPage = await noScriptContext.newPage();
  await noScriptPage.goto(new URL('/wp-login.php', fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
  await noScriptPage.locator('#user_login').fill(user);
  await noScriptPage.locator('#user_pass').fill(pass);
  await noScriptPage.locator('#wp-submit').click();
  await noScriptPage.waitForLoadState('domcontentloaded');
  await noScriptPage.goto(fixture.admin_url, { waitUntil: 'domcontentloaded' });
  const noScriptForm = noScriptPage.locator('button[value="business_source_preview"]').locator('xpath=ancestor::form');
  const noScriptPost = await noScriptForm.evaluate((form) => ({
    action: new URL(form.getAttribute('action') || window.location.href, window.location.href).toString(),
    nonce: form.querySelector('input[name="_wpnonce"]').value,
  }));
  const emptyResponse = await noScriptContext.request.post(noScriptPost.action, {
    form: {
      action: 'vms_pass_outreach_campaign_save',
      _wpnonce: noScriptPost.nonce,
      campaign_id: '0',
      save_mode: 'business_source_preview',
      recipient_source_mode: 'business_source',
      related_source_id: '0',
      related_batch_id: '0',
    },
    maxRedirects: 0,
  });
  check(emptyResponse.status() === 302 && (emptyResponse.headers().location || '').endsWith('#vms-outreach-business-source-setup'), 'no-script: empty submission did not return to the stable workflow anchor.');
  await noScriptPage.goto(new URL(emptyResponse.headers().location, fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
  check(await noScriptPage.locator('select[name="related_source_id"][aria-invalid="true"]').count() === 1, 'no-script: empty submission did not expose the Source error accessibly.');
  const forgedNonce = await noScriptPage.locator('button[value="business_source_preview"]').locator('xpath=ancestor::form').locator('input[name="_wpnonce"]').inputValue();
  const forgedResponse = await noScriptContext.request.post(noScriptPost.action, {
    form: {
      action: 'vms_pass_outreach_campaign_save',
      _wpnonce: forgedNonce,
      campaign_id: '0',
      save_mode: 'business_source_preview',
      recipient_source_mode: 'business_source',
      related_source_id: String(fixture.source_id),
      related_batch_id: String(fixture.existing_free_batch_id),
    },
    maxRedirects: 0,
  });
  check(forgedResponse.status() === 302 && (forgedResponse.headers().location || '').endsWith('#vms-outreach-business-source-setup'), 'no-script: forged Source/batch mismatch did not fail closed at the stable anchor.');
  await noScriptContext.close();

  await browser.close();
  process.stdout.write(`Business Source campaign browser inspection PASS (${outputDir})\n`);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
