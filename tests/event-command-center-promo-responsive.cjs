// Run after PHP fixture generation: node tests/event-command-center-promo-responsive.cjs <fixture-directory>.
// BVM_PLAYWRIGHT_MODULE may point to an existing local Playwright installation.
const { chromium } = require(process.env.BVM_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
(async () => {
    const html = fs.readFileSync(path.join(process.argv[2], '02-show-day.html'), 'utf8');
    const browser = await chromium.launch({ headless: true });
    const checks = [];
    try {
        const context = await browser.newContext({ serviceWorkers: 'block' });
        await context.route('**/*', route => route.abort('blockedbyclient'));
        const page = await context.newPage();
        // 354 models the narrower content area inside normal WordPress mobile chrome.
        for (const width of [1440, 1024, 390, 354]) {
            await page.setViewportSize({ width, height: 1000 });
            await page.setContent(html);
            const summary = page.getByText('Promo video controls', { exact: true });
            await summary.focus();
            await page.keyboard.press('Enter');
            assert.equal(await summary.evaluate(el => el.parentElement.open), true);
            const layout = await page.locator('.vms-cc-promo-grid').evaluate(grid => {
                const box = grid.getBoundingClientRect();
                const controls = [...grid.querySelectorAll('input:not([type="hidden"]),button')];
                return {
                    width: innerWidth,
                    gridWidth: box.width,
                    overflow: [...grid.querySelectorAll('*')].filter(el => el.checkVisibility({ visibilityProperty: true }) && el.getBoundingClientRect().right > box.right + 1).map(el => el.tagName),
                    controls: controls.length,
                    upload: !!grid.querySelector('input[type="file"]'),
                    externalURL: !!grid.querySelector('input[type="url"]'),
                };
            });
            assert.deepEqual(layout.overflow, [], `Expanded promo controls overflow at ${width}`);
            assert.ok(layout.upload && layout.externalURL && layout.controls >= 5);
            await page.keyboard.press('Enter');
            assert.equal(await summary.evaluate(el => el.parentElement.open), false);
            checks.push(layout);
        }
        console.log(JSON.stringify({ ok: true, viewports: checks }));
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
