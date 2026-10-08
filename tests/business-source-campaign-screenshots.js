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
  let publicOfferUrl = '';
  let createdCampaignAdminUrl = '';
  let createdBatchIdForMobile = '';
  let adminStorageState = null;
	let loginStorageState = null;
  const cases = [
    { name: 'desktop', width: 1440, height: 1000, submit: true },
    { name: 'mobile', width: 390, height: 844, submit: false },
  ];

  for (const testCase of cases) {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      viewport: { width: testCase.width, height: testCase.height },
	  storageState: loginStorageState || undefined,
	  permissions: ['clipboard-read', 'clipboard-write'],
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
	if (!loginStorageState) {
	  await page.goto(new URL('/wp-login.php', fixture.admin_url).toString(), { waitUntil: 'domcontentloaded' });
	  await page.locator('#user_login').fill(user);
	  await page.locator('#user_pass').fill(pass);
	  await Promise.all([
		page.waitForURL((url) => !url.pathname.endsWith('/wp-login.php'), { waitUntil: 'domcontentloaded' }),
		page.locator('#loginform').evaluate((form) => form.submit()),
	  ]);
	  loginStorageState = await context.storageState();
	}
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
      await route.locator('select[name="business_batch_validity_type"]').selectOption('single_event');
      await route.locator('select[name="business_batch_single_event_plan_id"]').selectOption(String(fixture.event_plan_id));
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
      createdBatchIdForMobile = createdBatchId;
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

    if (!testCase.submit && await page.locator('#vms-outreach-business-source-preview').count() === 0) {
      check(createdBatchIdForMobile !== '', 'mobile: created batch identity was unavailable for an independent preview.');
      await page.locator('input[name="recipient_source_mode"][value="business_source"]').check();
      const mobileRoute = page.locator('[data-vms-recipient-source-business]');
      await mobileRoute.locator('select[name="related_source_id"]').selectOption(String(fixture.source_id));
      await mobileRoute.locator('select[name="related_batch_id"]').selectOption(createdBatchIdForMobile);
      await mobileRoute.locator('button[value="business_source_preview"]').click();
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
    check(await page.getByText('Reusable-business sharing does not use individual-recipient templates or reserve individual invitation links.', { exact: true }).count() === 1, `${testCase.name}: business-sharing guidance is missing.`);
    check(await page.locator('[data-vms-business-message-secondary] input[name="email_subject"], [data-vms-business-message-secondary] textarea[name="message_template"]').count() === 0, `${testCase.name}: misleading individual-recipient template controls remain on the business route.`);
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
      const businessBoxes = qrPanel.locator('input[data-backstage-business]');
      await businessBoxes.last().uncheck();
      await qrPanel.locator('input[name="expires_at"]').fill('2031-04-05T18:45');
      await qrPanel.getByRole('button', { name: 'Review Selection' }).click();
      await page.waitForLoadState('domcontentloaded');
      let linkReview = page.locator('[data-vms-tour="outreach-reviewed-preview"]');
      let linkReviewText = await linkReview.innerText();
      check(linkReviewText.includes('34 active business links will remain or become active.'), 'mobile: partial reusable-link review did not retain exactly 34 businesses.');
      check(linkReviewText.includes('April 5, 2031 at 6:45 pm'), 'mobile: reviewed nonblank expiry was not displayed in the site timezone.');
      await qrPanel.locator('[data-backstage-select-all]').check();
      check(await linkReview.getByRole('button', { name: 'Save Reviewed Links' }).isDisabled(), 'mobile: editing a reviewed selection did not invalidate Save Reviewed Links.');
      check(await linkReview.locator('[data-vms-business-review-stale]').isVisible(), 'mobile: edited review did not explain that another review is required.');
      await qrPanel.getByRole('button', { name: 'Review Selection' }).click();
      await page.waitForLoadState('domcontentloaded');
      linkReview = page.locator('[data-vms-tour="outreach-reviewed-preview"]');
      linkReviewText = await linkReview.innerText();
      check(linkReviewText.includes('35 active business links will remain or become active.'), 'mobile: all-business review did not retain all 35 businesses.');
      await page.getByRole('button', { name: 'Save Reviewed Links' }).click();
      await page.waitForLoadState('domcontentloaded');
      check(await qrPanel.locator('input[name="expires_at"]').inputValue() === '2031-04-05T18:45', 'mobile: saved/reloaded Step 5 expiry did not match the reviewed site-timezone value.');
      const resultRows = page.locator('[data-vms-tour="outreach-business-results"] tbody tr');
      check(await resultRows.count() === 35, 'mobile: expected 35 saved reusable business links.');
      const reusableLinks = await resultRows.locator('input[data-backstage-copy-value]').evaluateAll((inputs) => inputs.map((input) => input.value));
      const customerLinks = reusableLinks.filter((value) => value.includes('/guest-pass/partner/'));
      const flyerLinks = reusableLinks.filter((value) => value.includes('/guest-pass/business-flyer/'));
      check(new Set(customerLinks).size === 35, 'mobile: reusable business links were not 35 unique signed partner URLs.');
      check(new Set(flyerLinks).size === 35, 'mobile: flyer links were not 35 unique signed public URLs.');
      check(await resultRows.getByRole('group', { name: 'Customer offer' }).count() === 35, 'mobile: customer-offer controls are not grouped per business.');
      check(await resultRows.getByRole('group', { name: 'Printable flyer' }).count() === 35, 'mobile: flyer controls are not grouped per business.');
      check(await resultRows.getByRole('group', { name: 'Manage' }).count() === 35, 'mobile: lifecycle controls are not grouped per business.');
      check(await resultRows.getByRole('group', { name: 'Printable flyer' }).getByRole('button', { name: 'Copy link' }).count() === 35, 'mobile: flyer copy actions are missing.');
      const printableQrUrl = await resultRows.first().getByRole('link', { name: 'Print QR' }).getAttribute('href');
      const printableQrPage = await context.newPage();
      await printableQrPage.goto(printableQrUrl, { waitUntil: 'networkidle' });
      check(await printableQrPage.locator('img.logo, .venue').count() === 1, 'mobile: Printable QR is missing venue branding.');
      check(await printableQrPage.locator('.qr-wrap img.qr').count() === 1, 'mobile: Printable QR is missing its scannable white quiet-zone presentation.');
      await printableQrPage.screenshot({ path: path.join(outputDir, 'printable-customer-qr.png'), fullPage: true });
      await printableQrPage.close();

      const design = page.locator('#backstage-outreach-flyer-design');
      check(await design.locator('input[name="campaign_artwork_mode"][value="inherit"]').isChecked(), 'mobile: new campaign did not retain automatic artwork mode.');
      check((await design.locator('[data-vms-flyer-artwork-source]').innerText()).includes('Selected event artwork'), 'mobile: One Event batch did not automatically resolve its linked event artwork.');
      await design.locator('input[name="campaign_flyer_heading"]').fill('Live Music at Café Serenade — ひらがな é');
      await design.locator('input[name="campaign_flyer_subheading"]').fill('You’ve found a reception-desk offer for tonight’s stage.');
	  await design.locator('input[name="campaign_layout_mode"][value="custom"]').check();
	  await design.locator('select[name="campaign_orientation"]').selectOption('portrait');
	  await design.locator('select[name="campaign_composition"]').selectOption('panels');
	  check(await design.locator('input[name="campaign_panel_position"]').inputValue() === 'bottom', 'mobile: campaign composition did not retain the full-width offer-band arrangement.');
      await design.getByRole('button', { name: 'Choose campaign artwork' }).click();
      const mediaDialog = page.locator('.media-modal');
      await mediaDialog.waitFor({ state: 'visible' });
	  const mediaLibraryTab = mediaDialog.getByRole('tab', { name: 'Media Library' });
	  if (await mediaLibraryTab.count()) {
		await mediaLibraryTab.click();
	  }
	  const mediaSearch = mediaDialog.locator('input[type="search"]');
	  if (await mediaSearch.count()) {
		await mediaSearch.fill('Business Source Browser Fixture Portrait Artwork');
	  }
      await mediaDialog.locator(`.attachment[data-id="${fixture.artwork_id}"]`).click();
      await mediaDialog.getByRole('button', { name: 'Use this artwork' }).click();
      check(await design.locator('input[name="campaign_artwork_mode"][value="custom"]').isChecked(), 'mobile: selecting campaign artwork did not select the custom-artwork mode.');
      await design.getByRole('button', { name: 'Save flyer design' }).click();
      await page.waitForLoadState('domcontentloaded');
      check(await page.locator('#backstage-outreach-flyer-design img').count() >= 1, 'mobile: saved campaign artwork preview is missing.');
	  check((await page.locator('#backstage-outreach-flyer-design [data-vms-flyer-artwork-source]').innerText()).includes('Campaign artwork override'), 'mobile: custom artwork did not remain the explicit source after save.');
	  check(await page.locator('#backstage-outreach-flyer-design input[name="campaign_layout_mode"][value="custom"]').isChecked(), 'mobile: campaign layout override was not restored after save.');
	  check(await page.locator('#backstage-outreach-flyer-design select[name="campaign_orientation"]').inputValue() === 'portrait', 'mobile: portrait layout was not restored after save.');
	  check(await page.locator('#backstage-outreach-flyer-design input[name="campaign_panel_position"]').inputValue() === 'bottom', 'mobile: normalized full-width offer band was not restored after save.');
      const routeAwarePanel = page.locator('#vms-outreach-recipients');
      check(await routeAwarePanel.getByRole('heading', { name: 'Business Contacts & Sharing' }).count() === 1, 'mobile: campaign management did not identify the reusable-business route.');
      check(await routeAwarePanel.getByText('Import from CSV', { exact: true }).count() === 0 && await routeAwarePanel.getByText('Select saved Outreach contacts', { exact: true }).count() === 0, 'mobile: reusable-business management still presents individual-recipient creation as a next step.');
      const share = page.locator('#backstage-outreach-business-share');
	  const multilineIntroduction = 'Hello {contact_name}… ひらがな é\n\nFirst paragraph for {business_name}.\n\nSecond paragraph keeps a blank line.\n\n**Bold markers stay literal.**';
      await share.locator('input[name="business_share_subject"]').fill('Café — {business_name} offer');
      await share.locator('textarea[name="business_share_message"]').fill(multilineIntroduction);
      await share.getByRole('button', { name: 'Save Template & Review Personalized Messages' }).click();
      await page.waitForLoadState('domcontentloaded');
	  check(await page.locator('#backstage-outreach-business-share textarea[name="business_share_message"]').inputValue() === multilineIntroduction, 'mobile: saved multiline business introduction did not survive reload exactly.');
	  check(await page.locator('#backstage-outreach-business-share').getByText('Emails are plain text. Paragraphs and blank lines are preserved', { exact: false }).count() === 1, 'mobile: plain-text and Markdown guidance is missing.');
      const shareReview = page.locator('#backstage-outreach-business-share-review');
      const shareRows = shareReview.locator('tbody tr');
      check(await shareRows.count() === 35, 'mobile: personalized sharing did not include all 35 linked businesses.');
      check(await shareRows.getByText('Not provided', { exact: true }).count() === 14, 'mobile: personalized sharing did not retain 14 copy-only businesses without email.');
      check(await shareRows.locator('textarea').first().inputValue().then((value) => value.includes('ひらがな é\n\nFirst paragraph') && value.includes('\n\nSecond paragraph keeps a blank line.\n\n**Bold markers stay literal.**\n\nOffer:') && value.includes('Customer offer URL:') && value.includes('Printable flyer URL:') && value.includes('does not reserve admissions')), 'mobile: personalized message omitted multiline formatting, UTF-8, links, or shared-capacity qualification.');
      const firstMessage = await shareRows.locator('textarea').first().inputValue();
      check(firstMessage.includes(customerLinks[0]) && firstMessage.includes(flyerLinks[0]), 'mobile: first business message did not use that business’s own offer and flyer links.');
	  await shareRows.first().getByRole('button', { name: 'Copy message' }).click();
	  check(await page.evaluate(() => navigator.clipboard.readText()) === firstMessage, 'mobile: copied business invitation did not retain the exact multiline preview body.');
      check(await shareReview.getByRole('button', { name: 'Hand Off Reviewed Business Emails' }).isDisabled(), 'mobile: draft campaign allowed business email delivery before activation.');
      publicFlyerUrl = flyerLinks[0] || '';
      publicOfferUrl = customerLinks[0] || '';
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
      createdCampaignAdminUrl = page.url();
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
    if (testCase.name === 'mobile') {
      adminStorageState = await context.storageState();
    }
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
    check(await flyerPage.getByRole('button', { name: 'Print full flyer' }).count() === 1, `${flyerCase.name}: labeled full-flyer print control is missing.`);
    check(await flyerPage.getByRole('button', { name: 'Download full-flyer PDF' }).count() === 1, `${flyerCase.name}: labeled full-flyer PDF control is missing.`);
    check(await flyerPage.getByRole('button', { name: 'Print Landscape Letter offer only' }).count() === 0, `${flyerCase.name}: unsupported portrait offer-only print control is visible.`);
    check(await flyerPage.getByRole('button', { name: 'Download Landscape Letter offer-only PDF' }).count() === 0, `${flyerCase.name}: unsupported portrait offer-only PDF control is visible.`);
    check(await flyerPage.getByRole('heading', { name: 'Live Music at Café Serenade — ひらがな é' }).count() === 1, `${flyerCase.name}: campaign flyer heading or UTF-8 is missing.`);
    check(await flyerPage.getByText('You’ve found a reception-desk offer for tonight’s stage.', { exact: true }).count() === 1, `${flyerCase.name}: campaign flyer subheading is missing.`);
    check(await flyerPage.locator('img.artwork').count() === 1, `${flyerCase.name}: selected Media Library artwork is missing from printable image content.`);
    check(await flyerPage.getByText('Complimentary Guest Passes for up to 2 people per customer', { exact: true }).count() === 1, `${flyerCase.name}: complimentary flyer wording is inaccurate.`);
    const flyerText = await flyerPage.locator('body').innerText();
    check(flyerText.includes('Maximum through this business: 2 Guest Pass admissions.'), `${flyerCase.name}: capped flyer omits its real per-business maximum.`);
    check(!flyerText.includes('reserved'), `${flyerCase.name}: flyer uses reserved-allotment wording.`);
    check(!flyerText.toLowerCase().includes('credentials'), `${flyerCase.name}: flyer exposes internal credential terminology.`);
    check(flyerText.includes('Scan to choose an eligible event and claim your passes'), `${flyerCase.name}: complimentary scan instruction is not customer-facing.`);
    check(await flyerPage.locator('img.qr').count() === 1 && (await flyerPage.locator('img.qr').getAttribute('src') || '').startsWith('data:image/png;base64,'), `${flyerCase.name}: flyer QR was not rendered from the actual customer offer link.`);
	check(await flyerPage.locator('.sheet.flyer-orientation-portrait.flyer-composition-panels.flyer-panel-bottom').count() === 1, `${flyerCase.name}: portrait composition was not rendered as stacked artwork and offer sections.`);
    await flyerPage.evaluate(() => window.backstageOutreachFlyerReady);
    const flyerGeometry = await flyerPage.evaluate(() => ({ overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth, sheetWidth: document.querySelector('.stage').getBoundingClientRect().width }));
    check(flyerGeometry.overflow <= 1 && flyerGeometry.sheetWidth <= flyerCase.width, `${flyerCase.name}: flyer overflows its viewport.`);
    check(flyerErrors.length === 0, `${flyerCase.name}: flyer console errors: ${flyerErrors.join(' | ')}`);
    await flyerPage.screenshot({ path: path.join(outputDir, `${flyerCase.name}-public-flyer.png`), fullPage: true });
    if (flyerCase.name === 'desktop') {
	  await flyerPage.evaluate(() => { window.__backstagePrintVariants = []; window.print = () => { window.__backstagePrintVariants.push(document.querySelector('[data-flyer-sheet]').classList.contains('flyer-offer-only') ? 'offer' : 'full'); }; });
	  await flyerPage.getByRole('button', { name: 'Print full flyer' }).click();
	  check(await flyerPage.evaluate(() => window.__backstagePrintVariants.join(',') === 'full'), 'desktop: portrait print control did not preserve the full-flyer composition.');
	  const fullDownloadPromise = flyerPage.waitForEvent('download');
	  await flyerPage.getByRole('button', { name: 'Download full-flyer PDF' }).click();
	  const fullDownload = await fullDownloadPromise;
	  await fullDownload.saveAs(path.join(outputDir, 'public-flyer-portrait-full.pdf'));
    }
    await flyerContext.close();
  }

  check(publicOfferUrl !== '', 'public customer offer URL was not captured from QR results.');

  check(adminStorageState !== null, 'authenticated browser state was not retained for flyer-design follow-up checks.');
  const removalContext = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1024, height: 768 }, storageState: adminStorageState });
  const removalPage = await removalContext.newPage();
  await removalPage.goto(createdCampaignAdminUrl, { waitUntil: 'domcontentloaded' });
  const removalDesign = removalPage.locator('#backstage-outreach-flyer-design');
	await removalDesign.locator('select[name="campaign_orientation"]').selectOption('landscape');
	await removalDesign.locator('select[name="campaign_composition"]').selectOption('full');
	await removalDesign.getByRole('button', { name: 'Replace artwork' }).click();
	const landscapeMediaDialog = removalPage.locator('.media-modal');
	await landscapeMediaDialog.waitFor({ state: 'visible' });
	const landscapeMediaLibraryTab = landscapeMediaDialog.getByRole('tab', { name: 'Media Library' });
	if (await landscapeMediaLibraryTab.count()) {
	  await landscapeMediaLibraryTab.click();
	}
	const landscapeMediaSearch = landscapeMediaDialog.locator('input[type="search"]');
	if (await landscapeMediaSearch.count()) {
	  await landscapeMediaSearch.fill('Business Source Browser Fixture Landscape Artwork');
	}
	await landscapeMediaDialog.locator(`.attachment[data-id="${fixture.landscape_artwork_id}"]`).click();
	await landscapeMediaDialog.getByRole('button', { name: 'Use this artwork' }).click();
	await removalDesign.getByRole('button', { name: 'Save flyer design' }).click();
	await removalPage.waitForLoadState('domcontentloaded');
	check(await removalPage.locator('#backstage-outreach-flyer-design select[name="campaign_orientation"]').inputValue() === 'landscape', 'landscape layout did not survive save/reload.');
	const landscapeContext = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
	const landscapePage = await landscapeContext.newPage();
	await landscapePage.goto(publicFlyerUrl, { waitUntil: 'networkidle' });
	await landscapePage.evaluate(() => window.backstageOutreachFlyerReady);
	check(await landscapePage.locator('.sheet.flyer-orientation-landscape.flyer-composition-full.flyer-panel-bottom').count() === 1, 'saved landscape/full-page/bottom composition was not used.');
	const landscapeGeometry = await landscapePage.locator('[data-flyer-sheet]').evaluate((sheet) => {
	  const box = (selector) => {
		const element = sheet.querySelector(selector);
		const rect = element ? element.getBoundingClientRect() : null;
		return rect ? { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom, width: rect.width, height: rect.height } : null;
	  };
	  const offer = sheet.querySelector('.offer-panel');
	  return {
		sheet: box('.composition'), art: box('.art-panel'), offer: box('.offer-panel'), brand: box('.offer-brand'), copy: box('.offer-copy'), qr: box('.qr-block'),
		offerBackground: offer ? getComputedStyle(offer).backgroundColor : '',
		offerFontSize: offer ? parseFloat(getComputedStyle(offer.querySelector('.offer')).fontSize) : 0,
	  };
	});
	check(landscapeGeometry.offer.height >= 236 && landscapeGeometry.offer.height <= 244, `landscape: offer band is not compact (${JSON.stringify(landscapeGeometry.offer)}).`);
	check(landscapeGeometry.art.height > landscapeGeometry.offer.height * 1.7, 'landscape: artwork did not receive substantially more space than the offer band.');
	check(landscapeGeometry.brand.left < landscapeGeometry.copy.left && landscapeGeometry.copy.left < landscapeGeometry.qr.left, 'landscape: logo, offer copy, and QR are not three deliberate left-to-right regions.');
	check(Math.max(landscapeGeometry.brand.top, landscapeGeometry.copy.top, landscapeGeometry.qr.top) - Math.min(landscapeGeometry.brand.top, landscapeGeometry.copy.top, landscapeGeometry.qr.top) <= 12, 'landscape: logo, offer copy, and QR are not top-aligned.');
	check(landscapeGeometry.sheet.right - landscapeGeometry.qr.right <= 42, 'landscape: QR is not positioned at the far right of the offer band.');
	check(landscapeGeometry.offerBackground === 'rgb(255, 255, 255)' && landscapeGeometry.offerFontSize >= 19, `landscape: offer band is not fully opaque with readable typography (${JSON.stringify(landscapeGeometry)}).`);
	await landscapePage.screenshot({ path: path.join(outputDir, 'desktop-public-flyer-landscape.png'), fullPage: true });
	const landscapeFullDownloadPromise = landscapePage.waitForEvent('download');
	await landscapePage.getByRole('button', { name: 'Download full-flyer PDF' }).click();
	const landscapeFullDownload = await landscapeFullDownloadPromise;
	await landscapeFullDownload.saveAs(path.join(outputDir, 'public-flyer-landscape-full.pdf'));
	const landscapeOfferDownloadPromise = landscapePage.waitForEvent('download');
	check(await landscapePage.getByRole('group', { name: 'Offer only — Landscape Letter, ink saving' }).count() === 1, 'landscape: offer-only controls do not identify Landscape Letter output.');
	const landscapePrintVariants = await landscapePage.evaluate(() => { window.__backstageLandscapePrintVariants = []; window.print = () => { window.__backstageLandscapePrintVariants.push(document.querySelector('[data-flyer-sheet]').classList.contains('flyer-offer-only') ? 'offer' : 'full'); }; return true; });
	check(landscapePrintVariants, 'landscape: print interception could not be installed.');
	await landscapePage.getByRole('button', { name: 'Print Landscape Letter offer only' }).click();
	check(await landscapePage.evaluate(() => window.__backstageLandscapePrintVariants.join(',') === 'offer'), 'landscape: offer-only print action did not select the ink-saving composition.');
	await landscapePage.getByRole('button', { name: 'Download Landscape Letter offer-only PDF' }).click();
	const landscapeOfferDownload = await landscapeOfferDownloadPromise;
	await landscapeOfferDownload.saveAs(path.join(outputDir, 'public-flyer-landscape-offer-only.pdf'));
	await landscapePage.locator('[data-flyer-sheet]').evaluate((element) => element.classList.add('flyer-offer-only'));
	const offerOnlyGeometry = await landscapePage.locator('[data-flyer-sheet]').evaluate((sheet) => {
	  const offer = sheet.querySelector('.offer-panel').getBoundingClientRect();
	  const qr = sheet.querySelector('.qr-block').getBoundingClientRect();
	  const sheetRect = sheet.getBoundingClientRect();
	  return { offerHeight: offer.height, offerBottom: offer.bottom - sheetRect.top, sheetHeight: sheetRect.height, qrRight: sheetRect.right - qr.right, borderWidth: getComputedStyle(sheet.querySelector('.offer-panel')).borderTopWidth };
	});
	check(offerOnlyGeometry.offerHeight <= 244 && offerOnlyGeometry.offerBottom < offerOnlyGeometry.sheetHeight / 2 && offerOnlyGeometry.qrRight <= 52 && offerOnlyGeometry.borderWidth === '0px', `landscape offer-only: content did not use a compact borderless full-width band (${JSON.stringify(offerOnlyGeometry)}).`);
	await landscapePage.screenshot({ path: path.join(outputDir, 'desktop-public-flyer-landscape-offer-only.png'), fullPage: true });
	await landscapePage.locator('[data-flyer-sheet]').evaluate((element) => element.classList.remove('flyer-offer-only'));
	await landscapeContext.close();
	await removalPage.goto(createdCampaignAdminUrl, { waitUntil: 'domcontentloaded' });
	const refreshedRemovalDesign = removalPage.locator('#backstage-outreach-flyer-design');
	await refreshedRemovalDesign.locator('input[name="campaign_artwork_mode"][value="none"]').check();
	await refreshedRemovalDesign.getByRole('button', { name: 'Save flyer design' }).click();
  await removalPage.waitForLoadState('domcontentloaded');
  const noArtworkContext = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
  const noArtworkPage = await noArtworkContext.newPage();
  await noArtworkPage.goto(publicFlyerUrl, { waitUntil: 'networkidle' });
  await noArtworkPage.evaluate(() => window.backstageOutreachFlyerReady);
  check(await noArtworkPage.locator('img.artwork').count() === 0, 'campaign artwork removal did not remove artwork from the public flyer.');
  check(await noArtworkPage.locator('.offer-brand img.logo, .offer-brand .venue').count() === 1, 'no-artwork fallback is missing venue identity.');
  check(await noArtworkPage.getByText('Complimentary Guest Passes for up to 2 people per customer', { exact: true }).count() === 1, 'no-artwork fallback is missing the exact offer.');
  const noArtworkLayout = await noArtworkPage.locator('[data-flyer-sheet]').evaluate((element) => {
    const art = element.querySelector('.art-panel');
    const offer = element.querySelector('.offer-panel');
    const offerStyle = offer ? getComputedStyle(offer) : null;
    return {
      noArt: element.classList.contains('flyer-no-art'),
      artworkVisible: !!art && getComputedStyle(art).display !== 'none',
      offerBackground: offerStyle ? offerStyle.backgroundColor : '',
      offerTop: offer ? Math.round(offer.getBoundingClientRect().top - element.getBoundingClientRect().top) : -1,
	  offerHeight: offer ? Math.round(offer.getBoundingClientRect().height) : -1,
	  offerBorder: offerStyle ? offerStyle.borderTopWidth : '',
    };
  });
  check(noArtworkLayout.noArt && !noArtworkLayout.artworkVisible && noArtworkLayout.offerBackground === 'rgb(255, 255, 255)' && noArtworkLayout.offerTop <= 50 && noArtworkLayout.offerHeight <= 244 && noArtworkLayout.offerBorder === '0px', `no-artwork fallback is not a compact, borderless, top-aligned white composition (${JSON.stringify(noArtworkLayout)}).`);
  await noArtworkPage.screenshot({ path: path.join(outputDir, 'desktop-public-flyer-no-artwork.png'), fullPage: true });
  const noArtworkDownloadPromise = noArtworkPage.waitForEvent('download');
  await noArtworkPage.getByRole('button', { name: 'Download full-flyer PDF' }).click();
  const noArtworkDownload = await noArtworkDownloadPromise;
  await noArtworkDownload.saveAs(path.join(outputDir, 'public-flyer-landscape-no-artwork.pdf'));

	await removalPage.goto(createdCampaignAdminUrl, { waitUntil: 'domcontentloaded' });
	const defaultDesign = removalPage.locator('#backstage-outreach-flyer-design');
	await defaultDesign.locator('input[name="campaign_flyer_heading"]').fill('');
	await defaultDesign.locator('input[name="campaign_flyer_subheading"]').fill('');
	await defaultDesign.locator('input[name="campaign_artwork_mode"][value="inherit"]').check();
	await defaultDesign.getByRole('button', { name: 'Save flyer design' }).click();
	await removalPage.waitForLoadState('domcontentloaded');
	check((await removalPage.locator('#backstage-outreach-flyer-design [data-vms-flyer-artwork-source]').innerText()).includes('Selected event artwork'), 'automatic mode did not return to the reviewed One Event artwork after explicit none.');
	await noArtworkPage.goto(publicFlyerUrl, { waitUntil: 'networkidle' });
	await noArtworkPage.evaluate(() => window.backstageOutreachFlyerReady);
	const defaultHeading = await noArtworkPage.locator('h1.heading').innerText();
	check(defaultHeading.startsWith('Live music at ') && !defaultHeading.includes('Café Serenade'), 'venue-default flyer heading did not replace the campaign override.');
	check((await noArtworkPage.locator('img.artwork').getAttribute('src') || '').includes('business-source-browser-fixture-event'), 'automatic public flyer did not use the linked event artwork.');
	await noArtworkPage.screenshot({ path: path.join(outputDir, 'desktop-public-flyer-default.png'), fullPage: true });

  await removalPage.goto(createdCampaignAdminUrl, { waitUntil: 'domcontentloaded' });
  const pauseUrl = await removalPage.getByRole('link', { name: 'Pause' }).first().getAttribute('href');
  const pauseResponse = await removalPage.request.get(pauseUrl, { maxRedirects: 0 });
  check(pauseResponse.status() === 302, 'paused-state setup did not use the protected lifecycle action.');
  const pausedResponse = await noArtworkPage.goto(publicFlyerUrl, { waitUntil: 'domcontentloaded' });
  check(pausedResponse && pausedResponse.status() === 410, 'paused flyer did not return HTTP 410.');
  check(await noArtworkPage.getByRole('heading', { name: 'Offer unavailable' }).count() === 1 && await noArtworkPage.getByRole('link', { name: 'Visit the venue homepage' }).count() === 1, 'paused flyer did not show the branded plain-language unavailable state.');
  await removalPage.goto(createdCampaignAdminUrl, { waitUntil: 'domcontentloaded' });
  const resumeUrl = await removalPage.getByRole('link', { name: 'Resume' }).first().getAttribute('href');
  const resumeResponse = await removalPage.request.get(resumeUrl, { maxRedirects: 0 });
  check(resumeResponse.status() === 302, 'paused fixture link could not be restored after the state check.');
  const tamperedFlyerUrl = publicFlyerUrl.slice(0, -1) + (publicFlyerUrl.endsWith('a') ? 'b' : 'a');
  const tamperedResponse = await noArtworkPage.goto(tamperedFlyerUrl, { waitUntil: 'domcontentloaded' });
  check(tamperedResponse && tamperedResponse.status() === 404, 'tampered flyer did not return HTTP 404.');
  check(await noArtworkPage.getByRole('link', { name: 'Visit the venue homepage' }).count() === 1, 'tampered flyer did not retain branded venue navigation.');
  await noArtworkContext.close();
  await removalContext.close();

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
