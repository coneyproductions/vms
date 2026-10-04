const fs = require('fs');
const path = require('path');

if (process.argv.length < 4 || process.argv.length > 5) {
  process.stderr.write('Usage: node ops-id-angle-matrix.js <plugin-root> <fixtures-root> [playwright-module]\n');
  process.exit(2);
}

const pluginRoot = path.resolve(process.argv[2]);
const fixturesRoot = path.resolve(process.argv[3]);
const playwrightModule = process.argv[4] ? path.resolve(process.argv[4]) : 'playwright';
const { chromium } = require(playwrightModule);
const libraryPath = path.join(pluginRoot, 'pwa', 'assets', 'vendor', 'zxing-library.min.js');
const browserPath = path.join(pluginRoot, 'pwa', 'assets', 'vendor', 'zxing-browser.min.js');
const expected = 'OPS14-SYNTHETIC-PDF417-ANGLE-TOLERANCE-20261004';
const fixtureSets = ['full', 'roi-current', 'roi-expanded'];
const angles = ['plus0', 'plus5', 'minus5', 'plus10', 'minus10', 'plus15', 'minus15'];

function asDataUrl(file) {
  return `data:image/png;base64,${fs.readFileSync(file).toString('base64')}`;
}

function fail(message) {
  throw new Error(message);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.setContent('<!doctype html><html><body></body></html>');
  await page.addScriptTag({ path: libraryPath });
  await page.addScriptTag({ path: browserPath });

  const rows = [];
  for (const fixtureSet of fixtureSets) {
    for (const angle of angles) {
      const dataUrl = asDataUrl(path.join(fixturesRoot, fixtureSet, `pdf417-${angle}.png`));
      const result = await page.evaluate(async ({ dataUrl, expected }) => {
        const image = new Image();
        image.src = dataUrl;
        await image.decode();

        async function decode(tryHarder, correction = 0) {
          const hints = new Map();
          hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.PDF_417]);
          if (tryHarder) hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
          const reader = new ZXingBrowser.BrowserMultiFormatReader(hints);
          let source = image;
          if (correction !== 0) {
            const canvas = document.createElement('canvas');
            canvas.width = image.naturalWidth;
            canvas.height = image.naturalHeight;
            const context = canvas.getContext('2d');
            context.fillStyle = '#fff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.translate(canvas.width / 2, canvas.height / 2);
            context.rotate(correction * Math.PI / 180);
            context.drawImage(image, -image.naturalWidth / 2, -image.naturalHeight / 2);
            source = canvas;
          }
          try {
            const decoded = source === image
              ? await reader.decodeFromImageElement(source)
              : reader.decodeFromCanvas(source);
            return String(decoded.getText()) === expected;
          } catch (_error) {
            return false;
          }
        }

        const corrections = {};
        for (const correction of [-15, -10, -5, 5, 10, 15]) {
          corrections[String(correction)] = await decode(false, correction);
        }
        return { ordinary: await decode(false), tryHarder: await decode(true), corrections };
      }, { dataUrl, expected });
      rows.push({ fixtureSet, angle, ...result });
    }
  }

  await browser.close();
  for (const row of rows) {
    const successfulCorrections = Object.entries(row.corrections)
      .filter(([, passed]) => passed)
      .map(([correction]) => correction)
      .join(',') || '-';
    process.stdout.write(`${row.fixtureSet}\t${row.angle}\tordinary=${row.ordinary ? 'PASS' : 'FAIL'}\ttry_harder=${row.tryHarder ? 'PASS' : 'FAIL'}\tcorrections=${successfulCorrections}\n`);
  }
  for (const row of rows) {
    const isNearLevel = row.angle === 'plus0' || row.angle === 'plus5' || row.angle === 'minus5';
    if (isNearLevel) {
      if (!row.ordinary || !row.tryHarder) fail(`${row.fixtureSet} ${row.angle} near-level decode failed`);
      continue;
    }
    if (row.ordinary || row.tryHarder) fail(`${row.fixtureSet} ${row.angle} unexpectedly decoded without correction`);
    const signedAngle = row.angle.startsWith('minus')
      ? -Number(row.angle.slice(5))
      : Number(row.angle.slice(4));
    const correction = String(-signedAngle);
    if (!row.corrections[correction]) fail(`${row.fixtureSet} ${row.angle} failed its ${correction}-degree correction`);
  }

  process.stdout.write('Ops ID synthetic PDF417 angle matrix: PASS (21 fixtures)\n');
})().catch((error) => {
  process.stderr.write(`${error.stack || error.message || error}\n`);
  process.exit(1);
});
