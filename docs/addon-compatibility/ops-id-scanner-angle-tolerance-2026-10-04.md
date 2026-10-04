# Ops ID Scanner Angle-Tolerance Candidate

Date: 2026-10-04
Repository baseline: `f8ff89c1d323b7557130dc8dca29e9051b180cb1`
Focused branch: `fix/ops-id-angle-tolerance-20261004`
Staging URL: `https://staging.serenaderange.com/vms-ops/`

## Result

The affected phone/PWA is using the bundled ZXing path. The roughly 10-degree PDF417 failure is decoder angle sensitivity, not native target-region crop clipping. A focused, staging-only `0.1.70.2` candidate is prepared directly from the exact current staging `0.1.70` tree.

The candidate preserves the ordinary full-video ZXing decode as the first path. Only after ordinary ID decoding misses twice does it try one throttled, small-angle corrected canvas frame. Ticket scanning, the native `BarcodeDetector` path, camera selection/settings, zoom and scanner preferences, the visible target, duplicate handling, and the existing two-read ID stability gate are unchanged.

The paused `0.1.70.1` starting-zoom candidate was not applied or folded into this candidate. Staging and production were not modified.

## Affected-phone engine identification

The read-only staging access log contains one iPhone/Safari PWA asset sequence on 2026-10-04:

- `app.js?ver=0.1.70` at `12:42:04`
- `zxing-library.min.js?ver=0.1.70` at `12:42:08`
- `zxing-browser.min.js?ver=0.1.70` at `12:42:08`
- browser family: iPhone Safari/WebKit

No client address, cookie, token, ID payload, identity, or business record was retained. The ZXing bundles are loaded lazily by `ensureZxingLoaded()`, which is called by `startZxingDetectorLoop()`. Their request immediately after the PWA opened the scanner identifies the engine actually selected for that phone session as ZXing.

`startZxingDetectorLoop()` decodes the video element directly. It does not call `getScannerRoiRect()`. Therefore the native target-region crop cannot be the cause of this affected-phone failure.

## Synthetic PDF417 evidence

The fixture payload was synthetic and non-personal:

- payload: `OPS14-SYNTHETIC-PDF417-ANGLE-TOLERANCE-20261004`
- payload SHA-256: `c2a90dea3692c0bfbc8b962b0451f72d615acd42ee0d77240e59d800afc4c980`
- angles: `0`, `+5`, `-5`, `+10`, `-10`, `+15`, and `-15` degrees
- views: intact `1920x1080` frame, current native ROI geometry, and vertically expanded ROI geometry

The generated PNGs remain outside Git. Their fixed-payload generator, executable source fallback test, and bundled-ZXing matrix test are tracked under `tests/addon-compatibility/`. No supplied ID screenshot or ID payload was copied, decoded, or committed.

| Fixture view | 0° ordinary | ±5° ordinary | ±10° ordinary | ±15° ordinary | `TRY_HARDER` difference | Exact inverse rotation |
| --- | --- | --- | --- | --- | --- | --- |
| Full video frame | PASS | PASS | FAIL | FAIL | None | PASS at every failed angle |
| Current native ROI | PASS | PASS | FAIL | FAIL | None | PASS at every failed angle |
| Expanded native ROI | PASS | PASS | FAIL | FAIL | None | PASS at every failed angle |

The intact full-frame failures at `±10°` and `±15°` rule out crop clipping as the cause on ZXing. Near-level `±5°` remains on the ordinary fast path. Expanding the crop does not change the result. Setting the bundled implementation's `TRY_HARDER` hint also changes no result, so the candidate does not enable that hint. Applying the matching `±10°` or `±15°` correction restores every failed fixture; adjacent corrections also succeed in several cases, demonstrating overlap between the fallback angles.

The current native ROI can geometrically clip the top and bottom of a long PDF417 symbol at about 10 degrees, but that is a separate potential native-path concern. The affected phone did not use that path, so the candidate does not change native ROI behavior speculatively.

## Focused implementation

The fallback is confined to `startZxingDetectorLoop('id')`:

- the existing video-element decode always runs first;
- a fallback is eligible only after two ordinary decode misses;
- no more than one corrected frame is tried every `420 ms` (or twice the configured interval if longer);
- corrections cycle `-10`, `+10`, `-5`, `+5`, `-15`, `+15` degrees;
- after a correction succeeds, the same correction is retained for the next stability read;
- the decoded text still enters the unchanged `onScannerDecoded()` duplicate/stability path;
- ticket mode creates no fallback reader or canvas;
- no decoder hint, camera constraint, lens choice, zoom value, preference, ROI, UI, REST, AJAX, authentication, authorization, nonce, or payload handling changes.

Because the fallback starts only after ordinary misses, straight-on decoding retains its current fast path and receives no rotation work.

## Exact staging base and candidate

The three staging base files were re-read over the configured read-only SSH path immediately before final candidate verification:

| Path | Current staging `0.1.70` SHA-256 | Candidate `0.1.70.2` SHA-256 |
| --- | --- | --- |
| `pwa/assets/js/app.js` | `ebf2577e120f632b117d9d28eba5dc0353a7bde82c4f72403b79e84fa0a964cc` | `d6f1b9f017e58167db220c6828acb8102e2142985b32edcb88ee9d31cd4e1dfc` |
| `vms-ops-console-premium.php` | `9ef49a508f35f65b58c402cbc0bed407bf9cd351907e6e83c35a6bf19d40ce45` | `3ac32c7f781e4efc1da0e62e43d34bdf9a2bb447c293e7ad73327cdee52a34a9` |
| `vms-build.txt` | `40ca81c66cb0f588d154523b7ee70cd2fbad4c4da3aa2f52eb66f1745ff86fd9` | `6504d2120c19ee89ae78f735c8b9424716f41228b44f64b30b254177a6d0fce1` |

- exact base: `0.1.70`, 62 files, canonical tree SHA-256 `b899e5f00ec7db26883b068200ba369a582f8f4236ad734de0fbcba0b670e85d`
- exact candidate: `0.1.70.2`, 62 files, canonical tree SHA-256 `73c752ee5a1f102c76a6a6b811f7baec83dcd6a37cb7a66bb27698e313285e52`
- changed runtime paths: exactly the three files in the table
- rollback source: the exact three base files retained in the task-owned base tree outside the web root

`0.1.70.2` is intentionally based directly on `0.1.70`: version `0.1.70.1` identifies the separate, paused zoom candidate and is not a prerequisite for this overlay.

## Git reproduction artifact

- artifact: `docs/addon-compatibility/vms-ops-console-premium-0.1.70-to-0.1.70.2-angle.patch.b64`
- decoded patch SHA-256: `79847c5f54b05685f27b38ccb00c3510706008920ef618883e634edf64b55402`
- encoded artifact SHA-256: `16220fe72d1e2d5288180562b55891b1f2fb87ba25772d7981f31dc88309578a`

Applying the decoded patch with `patch -p1` to a fresh exact staging-base copy reproduced the candidate byte-for-byte. `diff -qr` was clean and the reconstructed canonical tree hash matched `73c752ee5a1f102c76a6a6b811f7baec83dcd6a37cb7a66bb27698e313285e52`.

## Focused verification

Passed:

- repository preflight before work and again on the focused branch
- live staging base re-hash and version check (`0.1.70`)
- JavaScript syntax for candidate `app.js` and the focused fallback harness
- all three staging-base Ops JavaScript tests
- focused executable fallback behavior: ordinary-first, two-miss gate, throttle, correction cycling, successful-correction retention, normal decoded-value handoff, and ticket exclusion
- bundled ZXing synthetic matrix for full/current-ROI/expanded-ROI at `0°`, `±5°`, `±10°`, and `±15°`
- bundled `TRY_HARDER` comparison with no improvement at any failed angle
- PHP lint for the candidate entry file
- exact three-file diff inspection and whitespace checks
- patch decode, fresh-base application, byte parity, file count, and canonical tree-hash parity

Reproduction sources:

- `tests/addon-compatibility/generate-ops-id-angle-fixtures.php`
- `tests/addon-compatibility/ops-id-angle-fallback.js`
- `tests/addon-compatibility/ops-id-angle-matrix.js`

The generator accepts a TCPDF `pdf417.php` implementation and an empty output directory. It writes the 21 fixed synthetic fixtures plus a hash receipt. The fallback test accepts candidate `app.js`; the matrix accepts the candidate plugin root, generated fixture root, and an optional Playwright module path. The matrix checks ordinary `0°`/`±5°`, failed ordinary `±10°`/`±15°`, unchanged `TRY_HARDER` results, and successful inverse-angle correction in all three views. Phone timing remains part of acceptance rather than a desktop performance claim.

## Short actual-phone test

After a separately authorized staging deployment, use the same phone, installed PWA, fixed stand, distance, and lighting:

1. Open `https://staging.serenaderange.com/vms-ops/` and confirm build `0.1.70.2`; confirm `app.js?ver=0.1.70.2` is delivered rather than a cached `0.1.70` or paused `0.1.70.1` asset.
2. With an authorized test ID, scan nearly level and confirm the normal response remains prompt.
3. Rotate approximately `+10°` without changing distance or framing; expect a normal response within about three seconds.
4. Repeat at approximately `-10°`.
5. Recheck level orientation to confirm the straight-on path remains prompt.
6. Confirm ID zoom/framing and camera choice are unchanged; close/reopen and relaunch the PWA once.
7. Scan an authorized ticket QR/barcode and confirm ticket-scanner continuity.
8. If a device using native `BarcodeDetector` with PDF417 is available, record it separately; otherwise mark that case `NOT RUN`.

Do not retain or report barcode payloads or identity data. Record only build, browser/device family, angle, approximate time-to-response, and PASS/FAIL.

## Acceptance status and boundaries

| Case | Status |
| --- | --- |
| Affected-phone engine identification | PASS — ZXing |
| Synthetic straight-on/full-frame decode | PASS |
| Synthetic ordinary `±5°` and corrected `±10°`/`±15°` | PASS |
| Existing duplicate/two-read stability path | PASS — unchanged; repeated fallback handoff exercised by focused harness |
| Ticket fallback exclusion | PASS — executable harness |
| Candidate delivery/cache identity on phone | `NOT RUN` |
| Actual authorized-ID level and `±10°` scans | `NOT RUN` |
| Actual-phone camera/zoom continuity | `NOT RUN` |
| Actual ticket scan | `NOT RUN` |
| Native PDF417 device | `NOT RUN` |

No staging or production write, activation change, preference/storage clear, device setting change, package, ZIP, tag, or deployment occurred. Production remains unchanged. The zoom candidate remains paused and undeployed.
