const fs = require('fs');
const path = require('path');

if (process.argv.length < 5 || process.argv.length > 6) {
  process.stderr.write('Usage: node ops-id-background-fallback.js <candidate-app.js> <plugin-root> <fixtures-root> [playwright-module]\n');
  process.exit(2);
}

const appPath = path.resolve(process.argv[2]);
const pluginRoot = path.resolve(process.argv[3]);
const fixturesRoot = path.resolve(process.argv[4]);
const playwrightModule = process.argv[5] ? path.resolve(process.argv[5]) : 'playwright';
const { chromium } = require(playwrightModule);
const source = fs.readFileSync(appPath, 'utf8');
const libraryPath = path.join(pluginRoot, 'pwa', 'assets', 'vendor', 'zxing-library.min.js');
const browserPath = path.join(pluginRoot, 'pwa', 'assets', 'vendor', 'zxing-browser.min.js');
const expected = 'OPS14-SYNTHETIC-PDF417-ANGLE-TOLERANCE-20261004';
const backgrounds = ['white', 'gray', 'black', 'patterned'];
const angles = [10, -10];

function fail(message) {
  throw new Error(message);
}

function asDataUrl(file) {
  return `data:image/png;base64,${fs.readFileSync(file).toString('base64')}`;
}

function extractFunction(name) {
  const match = new RegExp(`(?:async\\s+)?function\\s+${name}\\s*\\(`).exec(source);
  if (!match) fail(`could not find ${name}`);
  const signatureEnd = source.indexOf(') {', match.index);
  const braceAt = signatureEnd + 2;
  let depth = 0;
  let quote = '';
  let escaped = false;
  let lineComment = false;
  let blockComment = false;

  for (let index = braceAt; index < source.length; index += 1) {
    const char = source[index];
    const next = source[index + 1];
    if (lineComment) {
      if (char === '\n') lineComment = false;
      continue;
    }
    if (blockComment) {
      if (char === '*' && next === '/') {
        blockComment = false;
        index += 1;
      }
      continue;
    }
    if (quote) {
      if (escaped) escaped = false;
      else if (char === '\\') escaped = true;
      else if (char === quote) quote = '';
      continue;
    }
    if (char === '/' && next === '/') {
      lineComment = true;
      index += 1;
      continue;
    }
    if (char === '/' && next === '*') {
      blockComment = true;
      index += 1;
      continue;
    }
    if (char === '"' || char === "'" || char === '`') {
      quote = char;
      continue;
    }
    if (char === '{') depth += 1;
    if (char === '}' && --depth === 0) return source.slice(match.index, index + 1);
  }
  fail(`could not extract ${name}`);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    await page.setContent('<!doctype html><html><body></body></html>');
    await page.addScriptTag({ path: libraryPath });
    await page.addScriptTag({ path: browserPath });

    const fixtures = [];
    for (const background of backgrounds) {
      for (const angle of angles) {
        const label = angle < 0 ? `minus${Math.abs(angle)}` : `plus${angle}`;
        fixtures.push({
          background,
          angle,
          dataUrl: asDataUrl(path.join(fixturesRoot, 'backgrounds', background, `pdf417-${label}.png`)),
        });
      }
    }

    const results = await page.evaluate(async ({ fixtures, expectedText, drawSource, loopSource }) => {
      const OriginalReader = window.ZXingBrowser.BrowserMultiFormatReader;
      const originalDateNow = Date.now;
      const rows = [];
      try {
        for (const fixture of fixtures) {
          const image = new Image();
          image.src = fixture.dataUrl;
          await image.decode();
          const video = document.createElement('canvas');
          video.width = image.naturalWidth;
          video.height = image.naturalHeight;
          video.getContext('2d').drawImage(image, 0, 0);
          Object.defineProperties(video, {
            readyState: { value: 4 },
            videoWidth: { value: image.naturalWidth },
            videoHeight: { value: image.naturalHeight },
          });

          const hints = new Map();
          hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.PDF_417]);
          let ordinaryError = null;
          try {
            new OriginalReader(hints).decodeFromCanvas(video);
          } catch (error) {
            ordinaryError = error;
          }
          if (!ordinaryError || ordinaryError.getKind?.() !== 'NotFoundException') {
            throw new Error(`${fixture.background} ${fixture.angle} did not produce a bundled NotFoundException`);
          }

          const readers = [];
          const decoded = [];
          let fallbackAttempts = 0;
          class HarnessReader {
            constructor(readerHints, interval) {
              this.reader = new OriginalReader(readerHints);
              this.interval = interval;
              readers.push(this);
            }

            async decodeFromVideoElement(sourceVideo, callback) {
              this.video = sourceVideo;
              this.callback = callback;
              return { stop() {} };
            }

            decodeFromCanvas(canvas) {
              fallbackAttempts += 1;
              return this.reader.decodeFromCanvas(canvas);
            }
          }

          window.ZXingBrowser = { ...window.ZXingBrowser, BrowserMultiFormatReader: HarnessReader };
          let now = 1000;
          Date.now = () => now;
          const state = { scannerActive: true, scannerMode: 'id', scannerZxingReader: null, scannerZxingControls: null };
          const els = { scanVideo: video };
          const ensureZxingLoaded = async () => {};
          const scannerFormats = () => ({ zxing: ['PDF_417'] });
          const scannerScanInterval = () => 210;
          const onScannerDecoded = (text) => decoded.push(text);
          const drawScannerRotationFallbackFrame = eval(`(${drawSource})`);
          const startZxingDetectorLoop = eval(`(${loopSource})`);

          await startZxingDetectorLoop('id');
          readers[0].callback(null, ordinaryError, { stop() {} });
          readers[0].callback(null, ordinaryError, { stop() {} });
          for (let attempt = 0; decoded.length < 2 && attempt < 8; attempt += 1) {
            now += 500;
            readers[0].callback(null, ordinaryError, { stop() {} });
          }
          rows.push({
            background: fixture.background,
            angle: fixture.angle,
            fallbackAttempts,
            decoded,
          });
          window.ZXingBrowser = { ...window.ZXingBrowser, BrowserMultiFormatReader: OriginalReader };
        }
      } finally {
        Date.now = originalDateNow;
        window.ZXingBrowser = { ...window.ZXingBrowser, BrowserMultiFormatReader: OriginalReader };
      }
      return rows;
    }, {
      fixtures,
      expectedText: expected,
      drawSource: extractFunction('drawScannerRotationFallbackFrame'),
      loopSource: extractFunction('startZxingDetectorLoop'),
    });

    for (const row of results) {
      if (row.decoded.length !== 2 || row.decoded.some((value) => value !== expected)) {
        fail(`${row.background} ${row.angle} did not complete the two-read fallback path`);
      }
      process.stdout.write(
        `${row.background}\t${row.angle}\tattempts=${row.fallbackAttempts}\tstability_reads=${row.decoded.length}\tPASS\n`
      );
    }
    process.stdout.write(`Ops ID background fallback: PASS (${results.length} bundled-decoder cases)\n`);
  } finally {
    await browser.close();
  }
})().catch((error) => {
  process.stderr.write(`${error.stack || error.message || error}\n`);
  process.exit(1);
});
