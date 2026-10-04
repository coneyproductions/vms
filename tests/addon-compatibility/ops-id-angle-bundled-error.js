const fs = require('fs');
const path = require('path');

if (process.argv.length < 4 || process.argv.length > 5) {
  process.stderr.write('Usage: node ops-id-angle-bundled-error.js <candidate-app.js> <plugin-root> [playwright-module]\n');
  process.exit(2);
}

const appPath = path.resolve(process.argv[2]);
const pluginRoot = path.resolve(process.argv[3]);
const playwrightModule = process.argv[4] ? path.resolve(process.argv[4]) : 'playwright';
const { chromium } = require(playwrightModule);
const source = fs.readFileSync(appPath, 'utf8');
const libraryPath = path.join(pluginRoot, 'pwa', 'assets', 'vendor', 'zxing-library.min.js');
const browserPath = path.join(pluginRoot, 'pwa', 'assets', 'vendor', 'zxing-browser.min.js');

function fail(message) {
  throw new Error(message);
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

    const result = await page.evaluate(async ({ drawSource, loopSource }) => {
      const hints = new Map();
      hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.PDF_417]);
      const shippedReader = new ZXingBrowser.BrowserMultiFormatReader(hints);
      const blank = document.createElement('canvas');
      blank.width = 640;
      blank.height = 480;
      const blankContext = blank.getContext('2d');
      blankContext.fillStyle = '#fff';
      blankContext.fillRect(0, 0, blank.width, blank.height);

      let bundledError = null;
      try {
        shippedReader.decodeFromCanvas(blank);
      } catch (error) {
        bundledError = error;
      }
      if (!bundledError) throw new Error('blank bundled PDF417 decode did not throw');

      const kind = typeof bundledError.getKind === 'function' ? String(bundledError.getKind() || '') : '';
      const name = String(bundledError.name || '');
      const constructorName = String(bundledError.constructor?.name || '');
      if (kind !== 'NotFoundException') throw new Error(`unexpected bundled error kind: ${kind || '(empty)'}`);
      if (['NotFoundException', 'ChecksumException', 'FormatException'].includes(name)
        || ['NotFoundException', 'ChecksumException', 'FormatException'].includes(constructorName)) {
        throw new Error('bundled error unexpectedly has an unminified name fallback');
      }

      const readers = [];
      let fallbackAttempts = 0;
      class HarnessReader {
        constructor(readerHints, interval) {
          this.hints = readerHints;
          this.interval = interval;
          readers.push(this);
        }

        async decodeFromVideoElement(video, callback) {
          this.video = video;
          this.callback = callback;
          return { stop() {} };
        }

        decodeFromCanvas() {
          fallbackAttempts += 1;
          throw bundledError;
        }
      }

      window.ZXingBrowser = { ...window.ZXingBrowser, BrowserMultiFormatReader: HarnessReader };
      const video = document.createElement('canvas');
      video.width = 1920;
      video.height = 1080;
      Object.defineProperties(video, {
        readyState: { value: 4 },
        videoWidth: { value: 1920 },
        videoHeight: { value: 1080 },
      });

      const state = { scannerActive: true, scannerMode: 'id', scannerZxingReader: null, scannerZxingControls: null };
      const els = { scanVideo: video };
      const ensureZxingLoaded = async () => {};
      const scannerFormats = () => ({ zxing: ['PDF_417'] });
      const scannerScanInterval = () => 210;
      const onScannerDecoded = () => {};
      const drawScannerRotationFallbackFrame = eval(`(${drawSource})`);
      const startZxingDetectorLoop = eval(`(${loopSource})`);

      await startZxingDetectorLoop('id');
      if (readers.length !== 2) throw new Error(`expected two ID readers, got ${readers.length}`);
      readers[0].callback(null, bundledError, { stop() {} });
      const afterFirstMiss = fallbackAttempts;
      readers[0].callback(null, bundledError, { stop() {} });

      return { kind, name, constructorName, afterFirstMiss, afterSecondMiss: fallbackAttempts };
    }, {
      drawSource: extractFunction('drawScannerRotationFallbackFrame'),
      loopSource: extractFunction('startZxingDetectorLoop'),
    });

    if (result.afterFirstMiss !== 0) fail('first bundled miss should not run fallback');
    if (result.afterSecondMiss !== 1) fail('second bundled miss should run one rotation fallback');
    process.stdout.write(`Bundled ZXing error regression: PASS (kind=${result.kind}, name=${result.name || '(empty)'}, constructor=${result.constructorName || '(empty)'})\n`);
  } finally {
    await browser.close();
  }
})().catch((error) => {
  process.stderr.write(`${error.stack || error.message || error}\n`);
  process.exit(1);
});
