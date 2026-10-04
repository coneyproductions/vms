# Ops ID Scanner Starting-Zoom Candidate

Date: 2026-10-04
Show deadline: 2026-10-10
Repository baseline: `51d7d39bb8256bbaf8ff1fbd0a0e066b839c9dd6`
Focused branch: `fix/ops-id-default-zoom-20261004`

## Result

The focused ID-only implementation is complete and locally verified. The canonical local Ops source is now `0.1.68.1`. A separate three-file `0.1.70.1` overlay is reproducible against the exact fresh staging `0.1.70` tree, so staging can receive the scanner correction without rolling back its unrelated venue-mapping work.

No staging or production write was performed. The staging deployment workflow was exercised in checksum dry-run mode and identified exactly three candidate runtime files. Actual-phone framing, camera telemetry, PDF417 decoding, lifecycle, and delivered-cache acceptance are `NOT RUN` because no phone/browser/PWA control surface was available to this task.

## Root-cause classification

The code-level cause is confirmed:

- `vms_ops_scanner_prefs_v1` stores separate ID and ticket zoom values.
- A positive saved ID zoom overrides the normal fallback during every capability analysis.
- Startup and manual adjustment both previously used `applyScannerZoomValue()`, so the stored value had no provenance distinguishing an automatic startup application from an operator choice.
- Warm reuse, camera flip/recovery, full stream restart, page reload, and PWA relaunch all return through capability analysis and therefore restore that saved value.

The phone-specific incident cause remains inferred. No physical-phone evidence was available to prove the scanner's current `Z_old`, supported `min/max/step`, selected camera, `getSettings().zoom`, saved `id.zoomValue`, or the effective value after one existing **Zoom -** click. The implementation follows that exact existing subtraction and clamping behavior; it does not claim that the phone is above its supported minimum.

## Fresh bounded runtime inventory

Before implementation, the relevant preference, camera-selection/start, zoom/capability, warm-stream, open/reopen, and flip functions were extracted by function name from all three actual runtime files. Their combined SHA-256 was identical: `094ec91998b62247db604ae67837ba88389b94b4d3706a83c26e348d3316fb25`.

| Runtime | Version | Entry SHA-256 | `app.js` SHA-256 | `sw.js` SHA-256 | Build SHA-256 |
| --- | --- | --- | --- | --- | --- |
| Local, pre-change | `0.1.68` | `02ea5831de0471afd08f42fd0c84c04c63f5e40047c7d1376e52147572b54244` | `5ee94fd4a4300c27da2f56f2d4e3b74c3e0da80734d1b6e9ac7dae5a775af839` | `8b18a4e22ac63b4db1c1e5f96e6b177a556242ad23542729e9e84d1215a71c70` | `f36e5c36623e2850676d809ce997fe88f9783d7c5f75d797dc9bc90de3da0d88` |
| Staging, current | `0.1.70` | `9ef49a508f35f65b58c402cbc0bed407bf9cd351907e6e83c35a6bf19d40ce45` | `ebf2577e120f632b117d9d28eba5dc0353a7bde82c4f72403b79e84fa0a964cc` | `8b18a4e22ac63b4db1c1e5f96e6b177a556242ad23542729e9e84d1215a71c70` | `40ca81c66cb0f588d154523b7ee70cd2fbad4c4da3aa2f52eb66f1745ff86fd9` |
| Production, current | `0.1.65.2` | `39f868dd8470ed8220fd5da955346d278c1d476c8c7850f6fafe6c78ed76fe25` | `5adb0d986219760671ce10d4cfa6425f1e20da55945e702df8a3d1cd35a753d4` | `8b18a4e22ac63b4db1c1e5f96e6b177a556242ad23542729e9e84d1215a71c70` | `cd1f9c0074b0470155aa6a306125d36ceaf12a5d8a856e012ef41c834f240333` |

The target reads were performed over the configured read-only SSH path. No barcode, identity, token, customer, presence, admission, or other business data was read or retained.

## Implementation

The preference key remains `vms_ops_scanner_prefs_v1`. A top-level `idZoomDefaultVersion` marker is added; the current marker version is `1`. The marker is global to ID mode because the existing ID zoom preference is itself shared across camera selection. Making it device-specific would risk another subtraction when the operator flips cameras.

On the first supported ID capability analysis without marker version `1`:

- a positive legacy ID zoom becomes `clamp(savedZoom - supportedStep, min, max)`;
- an unset ID zoom becomes `clamp(normalZoom - supportedStep, min, max)`;
- the browser's positive finite `capabilities.zoom.step` is used when available;
- otherwise the existing fallback `max((max - min) * 0.08, 0.05)` is used;
- the marker is recorded only after `applyConstraints()` succeeds.

After that successful application, the corrected zoom is restored without another subtraction. Manual **Zoom -** and **Zoom +** choices continue to update the saved ID zoom while retaining the marker. ID reset now returns to the same one-step-out ID default. Ticket startup, saved zoom, controls, reset target, preference object, camera choice, torch behavior, and unsupported-zoom behavior remain unchanged.

When zoom is missing, fixed, throws during capability discovery, or rejects the optional constraint, scanning continues with zoom controls unavailable. A rejected constraint stores neither the marker nor an incorrect new zoom value.

The candidate preference/camera/zoom/lifecycle function-set SHA-256 is `5e65e12745ca9127e1a5f1fc2be68fd92134dc68b639ce0e8f8a0a3d315e051` on both the canonical `0.1.68.1` candidate and the staging `0.1.70.1` overlay.

## Canonical local candidate and reproducibility

Standalone changed paths:

- `pwa/assets/js/app.js`
- `tests/js/test-id-scanner-zoom-default.js`
- `vms-ops-console-premium.php`
- `vms-build.txt`

Tracked reproduction artifact:

- `docs/addon-compatibility/vms-ops-console-premium-0.1.68-to-0.1.68.1.patch.b64`
- decoded patch SHA-256: `bd6f6455a807c51d4f2bffc43fa858d55571dc707d71d34a3aceef5eef0333c6`
- encoded artifact SHA-256: `40809ebbe07b9bb56b7e1ad826cc34d7024b12c1bbf3f709125177af7eac93e1`
- exact base: reconstructed `0.1.68`, 63 files, canonical tree SHA-256 `9bbebff4a8dcf063c1fae2c695cac85ea2465f92ce453741399b1d3c3d991d1c`
- exact candidate: `0.1.68.1`, 64 files, canonical tree SHA-256 `e998e6674387d6bc85bb15528b37b9ded3b37fb5f1ad8a45045a9e3ef4bdefcd`

Candidate file SHA-256 values:

| Path | SHA-256 |
| --- | --- |
| `pwa/assets/js/app.js` | `3aa0e6e8a3002f1e6a104c93444de2fe50eca782fffa7a453809533968d057e8` |
| `tests/js/test-id-scanner-zoom-default.js` | `0a7d78e13908923fe78df2f77af30b8b9050aa8b62ed4199d8eb75a8e9766323` |
| `vms-ops-console-premium.php` | `e7dfb4149da747a7471cecf1e06020db7ab805475897e39d26d2bf2007bce5fe` |
| `vms-build.txt` | `082eac826d00d6a7c6cebdb197212f1c4ed55af5b4b9f5f8ce417fc65e27a31c` |

The base was freshly reconstructed from immutable `vms-ops-console-premium-0.1.67.zip` plus the recorded `0.1.67-to-0.1.68` patch. Applying the new decoded patch to another fresh reconstruction produced a byte-identical candidate (`diff -qr` clean and equal canonical tree hashes).

## Staging overlay candidate

The staging overlay changes only `pwa/assets/js/app.js`, `vms-ops-console-premium.php`, and `vms-build.txt` on the exact 62-file staging `0.1.70` tree.

- artifact: `docs/addon-compatibility/vms-ops-console-premium-0.1.70-to-0.1.70.1.patch.b64`
- decoded patch SHA-256: `ae26563df0c60f7b33a14618ce3d773da6713dfb92633c32661ea84440efaa11`
- encoded artifact SHA-256: `ead0db4ec7590100bb601d92bf2604a41d1a602726779c0110a9fb2b50b85184`
- exact staging base: 62 files, canonical tree SHA-256 `b899e5f00ec7db26883b068200ba369a582f8f4236ad734de0fbcba0b670e85d`
- exact staging candidate: 62 files, canonical tree SHA-256 `4c91d096cb10ee2bcf48bce5fd197775a246d71eaa119d2ea59fd982c76f5f51`
- candidate `app.js`: `2c0a36acbbbd254560fabbe426d65c8f4b5b4b46e9b76763842c3786a603f65d`
- candidate entry: `41448002f0ea1db6760a4274eb7531bde09bd16c96b93467c817421a5f570ca8`
- candidate build file: `8e6381ce30b7b88fc614aff770c141baa6a65013a10f583f27643b99f6cdfabb`

Patch application to a fresh staging-base copy reproduced the candidate byte-for-byte. Checksum deployment dry-run reported exactly those three files and no deletion. A final remote read confirmed staging still has the three pre-change hashes in the inventory table. Because no write occurred, no rollback was needed or executed. Before any later authorized staging write, those exact three base files should be archived outside the web root and restored atomically if acceptance fails.

## Focused verification

Passed:

- `node --check pwa/assets/js/app.js`
- `node --check tests/js/test-id-scanner-zoom-default.js`
- all canonical Ops JavaScript tests
- focused ID zoom behavior against canonical `0.1.68.1`
- focused ID zoom behavior against the staging `0.1.70.1` overlay
- scanner-readiness JavaScript continuity test against both candidates
- `php -l vms-ops-console-premium.php` against both candidates
- canonical and staging patch application/reconstruction
- byte-for-byte candidate comparison and canonical tree-hash parity
- staging checksum deployment dry-run limited to three files

The new behavioral test executes the actual preference and zoom functions from `app.js`. It covers positive legacy migration once, fresh default, minimum clamping, browser step, fallback step, manual retention, warm reopen, cold restart, constraint rejection, missing and throwing capabilities, fixed zoom, ID reset, unchanged ticket restore/reset, and unchanged opposite-mode preferences.

## Actual-device acceptance

| Case | Status |
| --- | --- |
| Pre-change build, camera identity, capabilities, settings zoom, saved ID zoom, and one-click target | `NOT RUN` |
| Candidate starts at baseline plus exactly one existing **Zoom -** click | `NOT RUN` |
| ID Zoom +/- and reset on the scanner phone | `NOT RUN` |
| Warm close/reopen | `NOT RUN` |
| Full stream stop/restart | `NOT RUN` |
| Page reload and PWA relaunch | `NOT RUN` |
| Authorized test-ID PDF417 decoding and resume-after-scan | `NOT RUN` |
| Unsupported camera and rejected optional constraint | `NOT RUN` |
| Ticket scanner and camera-choice regression | `NOT RUN` |
| Delivered build/cache identity on the phone | `NOT RUN` |

No device PASS is claimed. If the phone reports that its opening value is already the supported minimum, the candidate must not be promoted as a framing success; retain scanning and record that exact constraint for a separate narrow follow-up. Production remains `0.1.65.2` and pending explicit promotion authorization after actual-phone acceptance.

## Boundaries

No lens selection, camera ranking, barcode decoding, CSS, authentication, authorization, nonce, REST, AJAX, ticket scanning, admission, presence, customer data, database state, activation state, staging runtime, production runtime, service-worker strategy, WordPress.org state, package, ZIP, tag, or deployment was changed. The service-worker source stays byte-identical; the Ops version bump supplies a fresh versioned cache name and asset URLs when a candidate is eventually deployed.
