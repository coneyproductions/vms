const { chromium } = require('../../../../vms-dev/node_modules/playwright');
const fs = require('fs');

function check(condition, message) {
  if (!condition) throw new Error(message);
}

(async () => {
  const url = process.env.OUTREACH_UNSUBSCRIBE_URL;
  const output = process.env.OUTREACH_UNSUBSCRIBE_EVIDENCE;
  const httpUser = process.env.VMS_HTTP_USER;
  const httpPass = process.env.VMS_HTTP_PASS;
  if (!url || !output) throw new Error('Synthetic unsubscribe URL and evidence directory are required.');
  if ((httpUser && !httpPass) || (!httpUser && httpPass)) throw new Error('Both HTTP authentication values are required when either is set.');
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ headless: true });

  for (const viewport of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      viewport,
      ...(httpUser ? { httpCredentials: { username: httpUser, password: httpPass } } : {}),
    });
    const page = await context.newPage();
    const errors = [];
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('pageerror', error => errors.push(error.message));
    const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
    check(response && response.status() === 200, `${viewport.name}: confirmation GET did not return 200.`);
    check(await page.getByRole('heading', { name: 'Outreach email preferences' }).count() === 1, `${viewport.name}: heading is missing.`);
    check(await page.getByRole('button', { name: 'Confirm unsubscribe' }).count() === 1, `${viewport.name}: explicit confirmation control is missing.`);
    check(await page.getByText('This changes only Backstage Outreach promotional email.', { exact: false }).count() === 1, `${viewport.name}: MailPoet separation note is missing.`);
    check(!(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2)), `${viewport.name}: horizontal overflow detected.`);
    check(errors.length === 0, `${viewport.name}: console errors: ${errors.join(' | ')}`);
    await page.screenshot({ path: `${output}/outreach-unsubscribe-${viewport.name}.png`, fullPage: true });
    await context.close();
  }

  await browser.close();
  console.log('Outreach unsubscribe browser PASS (1440px and 390px, GET only).');
})().catch(error => { console.error(error); process.exit(1); });
