const fs = require('fs');
const path = require('path');

if (process.argv.length < 4 || process.argv.length > 5) {
  process.stderr.write('Usage: node ops-id-background-matrix.js <plugin-root> <fixtures-root> [playwright-module]\n');
  process.exit(2);
}

const pluginRoot = path.resolve(process.argv[2]);
const fixturesRoot = path.resolve(process.argv[3]);
const playwrightModule = process.argv[4] ? path.resolve(process.argv[4]) : 'playwright';
const { chromium } = require(playwrightModule);
const libraryPath = path.join(pluginRoot, 'pwa', 'assets', 'vendor', 'zxing-library.min.js');
const browserPath = path.join(pluginRoot, 'pwa', 'assets', 'vendor', 'zxing-browser.min.js');
const expected = 'OPS14-SYNTHETIC-PDF417-ANGLE-TOLERANCE-20261004';
const backgrounds = ['white', 'gray', 'black', 'patterned'];
const angles = [0, 5, -5, 10, -10, 15, -15];
const paddedRegion = { x: 100, y: 240, width: 1720, height: 840 };

function asDataUrl(file) {
  return `data:image/png;base64,${fs.readFileSync(file).toString('base64')}`;
}

function angleLabel(angle) {
  return angle < 0 ? `minus${Math.abs(angle)}` : `plus${angle}`;
}

function fail(message) {
  throw new Error(message);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    await page.setContent('<!doctype html><html><body></body></html>');
    await page.addScriptTag({ path: libraryPath });
    await page.addScriptTag({ path: browserPath });

    const rows = [];
    for (const background of backgrounds) {
      for (const angle of angles) {
        const fixture = path.join(fixturesRoot, 'backgrounds', background, `pdf417-${angleLabel(angle)}.png`);
        const result = await page.evaluate(async ({ dataUrl, expectedText, correction, padded }) => {
          const image = new Image();
          image.src = dataUrl;
          await image.decode();

          function frameCanvas(filter = 'none') {
            const canvas = document.createElement('canvas');
            canvas.width = image.naturalWidth;
            canvas.height = image.naturalHeight;
            const context = canvas.getContext('2d');
            context.filter = filter;
            context.drawImage(image, 0, 0);
            context.filter = 'none';
            return canvas;
          }

          function cropCanvas(source, rect) {
            const canvas = document.createElement('canvas');
            canvas.width = rect.width;
            canvas.height = rect.height;
            canvas.getContext('2d').drawImage(
              source,
              rect.x,
              rect.y,
              rect.width,
              rect.height,
              0,
              0,
              rect.width,
              rect.height
            );
            return canvas;
          }

          function rotateCanvas(source, degrees, fill = '') {
            const canvas = document.createElement('canvas');
            canvas.width = source.width;
            canvas.height = source.height;
            const context = canvas.getContext('2d');
            if (fill) {
              context.fillStyle = fill;
              context.fillRect(0, 0, canvas.width, canvas.height);
            } else {
              context.clearRect(0, 0, canvas.width, canvas.height);
            }
            context.translate(canvas.width / 2, canvas.height / 2);
            context.rotate(degrees * Math.PI / 180);
            context.drawImage(source, -source.width / 2, -source.height / 2);
            return canvas;
          }

          function decode(source) {
            const hints = new Map();
            hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.PDF_417]);
            const reader = new ZXingBrowser.BrowserMultiFormatReader(hints);
            try {
              return String(reader.decodeFromCanvas(source).getText()) === expectedText;
            } catch (_error) {
              return false;
            }
          }

          const frame = frameCanvas();
          const paddedFrame = cropCanvas(frame, padded);
          const ordinary = decode(frame);
          const fullCorrection = correction === 0 ? ordinary : decode(rotateCanvas(frame, correction));
          const paddedCorrection = correction === 0
            ? decode(paddedFrame)
            : decode(rotateCanvas(paddedFrame, correction));
          const paddedWhiteCorrection = correction === 0
            ? decode(paddedFrame)
            : decode(rotateCanvas(paddedFrame, correction, '#fff'));

          return { ordinary, fullCorrection, paddedCorrection, paddedWhiteCorrection };
        }, {
          dataUrl: asDataUrl(fixture),
          expectedText: expected,
          correction: -angle,
          padded: paddedRegion,
        });
        rows.push({ background, angle, ...result });
      }
    }

    const captureRows = [];
    const captureVariants = {
      normal: 'none',
      washed: 'brightness(1.55) contrast(0.28)',
      blurred: 'blur(3px)',
      washedBlurred: 'brightness(1.55) contrast(0.28) blur(3px)',
    };
    for (const angle of [0, 10, -10]) {
      const fixture = path.join(fixturesRoot, 'backgrounds', 'black', `pdf417-${angleLabel(angle)}.png`);
      const results = await page.evaluate(async ({ dataUrl, expectedText, correction, padded, variants }) => {
        const image = new Image();
        image.src = dataUrl;
        await image.decode();

        function render(filter, rect = null) {
          const full = document.createElement('canvas');
          full.width = image.naturalWidth;
          full.height = image.naturalHeight;
          const fullContext = full.getContext('2d');
          fullContext.filter = filter;
          fullContext.drawImage(image, 0, 0);
          const source = rect ? (() => {
            const crop = document.createElement('canvas');
            crop.width = rect.width;
            crop.height = rect.height;
            crop.getContext('2d').drawImage(full, rect.x, rect.y, rect.width, rect.height, 0, 0, rect.width, rect.height);
            return crop;
          })() : full;
          if (correction === 0) return source;
          const rotated = document.createElement('canvas');
          rotated.width = source.width;
          rotated.height = source.height;
          const context = rotated.getContext('2d');
          context.translate(rotated.width / 2, rotated.height / 2);
          context.rotate(correction * Math.PI / 180);
          context.drawImage(source, -source.width / 2, -source.height / 2);
          return rotated;
        }

        function decode(source) {
          const hints = new Map();
          hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.PDF_417]);
          const reader = new ZXingBrowser.BrowserMultiFormatReader(hints);
          try {
            return String(reader.decodeFromCanvas(source).getText()) === expectedText;
          } catch (_error) {
            return false;
          }
        }

        const output = {};
        for (const [name, filter] of Object.entries(variants)) {
          output[name] = {
            full: decode(render(filter)),
            padded: decode(render(filter, padded)),
          };
        }
        return output;
      }, {
        dataUrl: asDataUrl(fixture),
        expectedText: expected,
        correction: -angle,
        padded: paddedRegion,
        variants: captureVariants,
      });
      captureRows.push({ angle, results });
    }

    for (const row of rows) {
      process.stdout.write(
        `${row.background}\t${row.angle}\tordinary=${row.ordinary ? 'PASS' : 'FAIL'}`
        + `\tfull=${row.fullCorrection ? 'PASS' : 'FAIL'}`
        + `\tpadded=${row.paddedCorrection ? 'PASS' : 'FAIL'}`
        + `\tpadded_white=${row.paddedWhiteCorrection ? 'PASS' : 'FAIL'}\n`
      );
    }
    for (const row of captureRows) {
      for (const [variant, result] of Object.entries(row.results)) {
        process.stdout.write(
          `capture-black\t${row.angle}\t${variant}`
          + `\tfull=${result.full ? 'PASS' : 'FAIL'}\tpadded=${result.padded ? 'PASS' : 'FAIL'}\n`
        );
      }
    }

    for (const row of rows) {
      if (Math.abs(row.angle) <= 5 && !row.ordinary) {
        fail(`${row.background} ${row.angle} failed ordinary near-level decode`);
      }
      if (Math.abs(row.angle) >= 10 && !row.fullCorrection) {
        fail(`${row.background} ${row.angle} failed the existing exact-angle correction`);
      }
    }

    const paddedGains = rows.filter((row) => !row.fullCorrection && row.paddedCorrection);
    const paddedWhiteGains = rows.filter((row) => !row.fullCorrection && row.paddedWhiteCorrection);
    if (paddedGains.length !== 0 || paddedWhiteGains.length !== 0) {
      fail('padded decoding unexpectedly added a controlled-background success');
    }
    const capturePaddedGains = captureRows.flatMap((row) => Object.entries(row.results).filter(
      ([, result]) => !result.full && result.padded
    ));
    if (capturePaddedGains.length !== 0) {
      fail('padded decoding unexpectedly recovered a degraded capture');
    }
    for (const row of captureRows) {
      if (row.results.washedBlurred.full || row.results.washedBlurred.padded) {
        fail(`washed/blurred ${row.angle} capture unexpectedly decoded`);
      }
    }
    process.stdout.write(
      `Ops ID background matrix: PASS (${rows.length} fixtures; padded-only gains=${paddedGains.length})\n`
    );
  } finally {
    await browser.close();
  }
})().catch((error) => {
  process.stderr.write(`${error.stack || error.message || error}\n`);
  process.exit(1);
});
