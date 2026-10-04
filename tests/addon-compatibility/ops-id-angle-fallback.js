const fs = require('fs');
const path = require('path');
const vm = require('vm');

if (process.argv.length !== 3) {
  process.stderr.write('Usage: node ops-id-angle-fallback.js <candidate-app.js>\n');
  process.exit(2);
}

const appPath = path.resolve(process.argv[2]);
const source = fs.readFileSync(appPath, 'utf8');

function fail(message) {
  throw new Error(message);
}

function same(actual, expected, message) {
  if (JSON.stringify(actual) !== JSON.stringify(expected)) {
    fail(`${message}: expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);
  }
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

function createHarness(mode) {
  const readers = [];
  const decoded = [];
  const rotations = [];
  let now = 1000;

  class Reader {
    constructor(hints, interval) {
      this.hints = hints;
      this.interval = interval;
      this.fallbackResults = [];
      readers.push(this);
    }

    async decodeFromVideoElement(video, callback) {
      this.video = video;
      this.callback = callback;
      return { stop() {} };
    }

    decodeFromCanvas() {
      const result = this.fallbackResults.shift();
      if (result instanceof Error) throw result;
      return result;
    }
  }

  const context2d = {
    save() {},
    setTransform() {},
    clearRect() {},
    translate() {},
    rotate(radians) { rotations.push(Math.round((radians * 180 / Math.PI) * 10) / 10); },
    drawImage() {},
    restore() {}
  };
  const canvas = { width: 0, height: 0, getContext: () => context2d };
  const state = { scannerActive: true, scannerMode: mode, scannerZxingReader: null, scannerZxingControls: null };
  const sandbox = {
    state,
    els: { scanVideo: { readyState: 4, videoWidth: 1920, videoHeight: 1080 } },
    document: { createElement: (name) => name === 'canvas' ? canvas : null },
    window: {
      ZXingBrowser: { BrowserMultiFormatReader: Reader },
      ZXing: { DecodeHintType: { POSSIBLE_FORMATS: 'formats' } }
    },
    Date: { now: () => now },
    ensureZxingLoaded: async () => {},
    scannerFormats: (scannerMode) => ({ zxing: scannerMode === 'id' ? ['PDF_417'] : ['QR_CODE'] }),
    scannerScanInterval: () => 210,
    onScannerDecoded: (text) => decoded.push(text),
  };
  vm.createContext(sandbox);
  vm.runInContext(`
    ${extractFunction('drawScannerRotationFallbackFrame')}
    ${extractFunction('startZxingDetectorLoop')}
    this.api = { startZxingDetectorLoop };
  `, sandbox);

  return { sandbox, readers, decoded, rotations, advance(ms) { now += ms; } };
}

function notFound() {
  const error = new Error('not found');
  error.name = 'NotFoundException';
  return error;
}

async function run() {
  const id = createHarness('id');
  await id.sandbox.api.startZxingDetectorLoop('id');
  same(id.readers.length, 2, 'ID mode should create ordinary and fallback readers');
  same(id.readers[0].interval, 210, 'ordinary reader should retain the existing scan interval');

  id.readers[1].fallbackResults.push(notFound(), { getText: () => 'SYNTHETIC-ID' }, { getText: () => 'SYNTHETIC-ID' });
  id.readers[0].callback(null, notFound(), { stop() {} });
  same(id.rotations, [], 'first ordinary miss should not run fallback');
  id.readers[0].callback(null, notFound(), { stop() {} });
  same(id.rotations, [-10], 'second ordinary miss should try the first small correction');
  same(id.decoded, [], 'failed fallback should not emit a value');

  id.advance(500);
  id.readers[0].callback(null, notFound(), { stop() {} });
  same(id.rotations, [-10, 10], 'next throttled miss should advance to the opposite correction');
  same(id.decoded, ['SYNTHETIC-ID'], 'successful fallback should use the normal decoded-value path');

  id.advance(500);
  id.readers[0].callback(null, notFound(), { stop() {} });
  same(id.rotations, [-10, 10, 10], 'successful correction should repeat for the two-read stability gate');
  same(id.decoded, ['SYNTHETIC-ID', 'SYNTHETIC-ID'], 'fallback should supply the stability read');

  id.readers[0].callback({ getText: () => 'STRAIGHT' }, null, { stop() {} });
  same(id.decoded.at(-1), 'STRAIGHT', 'ordinary decoding should remain first and unchanged');
  id.advance(500);
  id.readers[0].callback(null, notFound(), { stop() {} });
  same(id.rotations.length, 3, 'ordinary success should reset the miss gate');

  const ticket = createHarness('ticket');
  await ticket.sandbox.api.startZxingDetectorLoop('ticket');
  same(ticket.readers.length, 1, 'ticket mode should not create a rotation reader');
  ticket.readers[0].callback(null, notFound(), { stop() {} });
  ticket.advance(500);
  ticket.readers[0].callback(null, notFound(), { stop() {} });
  same(ticket.rotations, [], 'ticket failures should not run angle correction');

  process.stdout.write('ID scanner angle fallback tests: PASS\n');
}

run().catch((error) => {
  process.stderr.write(`${error.stack || error.message || error}\n`);
  process.exit(1);
});
