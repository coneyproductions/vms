# Ops ID Scanner Production Angle-Only Candidate

Date: 2026-10-04
Focused branch: `fix/ops-id-angle-tolerance-20261004`
Candidate: `0.1.65.3`
Status: **deployed and server-verified in production**

## Result

The production candidate is a three-file overlay built directly from a fresh read of the active production `0.1.65.2` plugin. It carries only the accepted ID-camera rotation fallback and the corrected bundled-ZXing miss classification from staging `0.1.70.3`.

The paused starting-zoom migration is not present. No other staging-only application, PHP, build, camera, scanner, ticket, preference, or PWA change is included. Production now runs the reviewed `0.1.65.3` overlay; staging remains `0.1.70.3`.

## Accepted operator evidence

- angled ID scanning on staging `0.1.70.3` worked much better;
- the current starting zoom is satisfactory, so the separate zoom candidate remains paused;
- IDs remain reliable over white paper, almost never scan over black/dark surfaces, and are inconsistent in midair;
- controlled bundled-decoder evidence did not show a gain from padded cropping, while washed-out/blurred capture could not be recovered after capture;
- a light, neutral backing with even illumination and reduced glare is the operational workaround for the remaining physical capture limitation.

No ID image, decoded payload, or identity data was retained.

## Exact production base and candidate

The active production base was re-read twice before preparation and again before the rollback archive was created.

| Path | Production base `0.1.65.2` SHA-256 | Candidate `0.1.65.3` SHA-256 |
| --- | --- | --- |
| `pwa/assets/js/app.js` | `5adb0d986219760671ce10d4cfa6425f1e20da55945e702df8a3d1cd35a753d4` | `f11a44cead1b611b985d132e313e23627f082d94fa6e20e4b1d05eea2bbe330e` |
| `vms-ops-console-premium.php` | `39f868dd8470ed8220fd5da955346d278c1d476c8c7850f6fafe6c78ed76fe25` | `a965e560e04c1afc19e44375c0094259a27dc4b29708e9bf62451d287718b5d7` |
| `vms-build.txt` | `cd1f9c0074b0470155aa6a306125d36ceaf12a5d8a856e012ef41c834f240333` | `79fb4844db669478135468fac518f1b917bd4a3fd2abbf012b502cf6a8abdcbb` |

- base: 61 files, canonical tree SHA-256 `029816fedf703f10da41f728461623f4670ca4b7e89c7835baf75aaa6715b760`
- candidate: 61 files, canonical tree SHA-256 `060e5c85b26cdeea386349ebfcee1813b6e3d9d5e7d52b20a16292f5c5dcfaee`
- complete-tree comparison: exactly the three table paths differ
- production active state: PASS

## Narrow implementation

The production `drawScannerRotationFallbackFrame()` and `startZxingDetectorLoop()` candidate functions are byte-identical to their accepted staging `0.1.70.3` counterparts.

- ordinary ZXing video decoding remains first;
- ID fallback becomes eligible after two ordinary misses;
- one correction is tried per throttled interval in the accepted `-10`, `+10`, `-5`, `+5`, `-15`, `+15` sequence;
- a successful correction is retained for the existing two-read stability gate;
- shipped minified ZXing errors are classified by stable `error.getKind()` first, with the existing error-name fallbacks retained when that API is absent;
- decoded text still enters the existing duplicate/stability/result path;
- ticket mode creates no rotation reader or canvas;
- native `BarcodeDetector`, camera choice/constraints, focus, zoom, torch, preferences, storage, authentication, authorization, REST, AJAX, and payload handling are unchanged.

No identifier from the paused `0.1.70.1` zoom migration is present in the production candidate.

## Reproducible overlay

- artifact: `docs/addon-compatibility/vms-ops-console-premium-0.1.65.2-to-0.1.65.3-angle.patch.b64`
- decoded patch SHA-256: `9f31975feb11606351ffe80b46ab6935415bc07d1407b04b34c7a449615107f1`
- encoded artifact SHA-256: `9a7e68d30f3dfc28a4c82826962a94a825dd689c06b84d1bb329da721223f86a`

Applying the decoded patch with `patch -p1` to a fresh copy of the exact 61-file production base reproduced the candidate byte-for-byte. The reconstructed tree and all three target hashes match the candidate values above.

## Focused verification

Passed against the production-based candidate:

- repository preflight and protected-stash check;
- application JavaScript syntax and entry-file PHP lint;
- exact function parity with accepted staging `0.1.70.3`;
- exact three-file scope and full-tree comparison;
- mock-error fallback sequencing, throttle, successful-angle retention, decoded-value handoff, and ticket exclusion;
- real bundled-error regression (`getKind() === 'NotFoundException'`, minified names `e`);
- original 21-case synthetic PDF417 angle matrix;
- 28-case white/gray/black/patterned background matrix, with zero padded-only gains;
- eight-case actual candidate-fallback/background harness, including both stability reads;
- three existing Ops JavaScript suites;
- patch decoding, clean application, full byte comparison, target hashes, file count, and canonical tree hash;
- `git diff --check` and manual three-file diff inspection.

Generated fixtures use only the fixed non-personal payload `OPS14-SYNTHETIC-PDF417-ANGLE-TOLERANCE-20261004` and remain outside Git.

## Rollback and deployment dry run

Exact rollback originals are archived outside the web root at:

`/home/coney/codex-backups/ops-id-angle-production-0.1.65.2-20261004T235614Z`

The owner-only candidate upload and dry-run tree are outside the web root at:

`/home/coney/codex-staging/ops-id-angle-production-0.1.65.3-20261004T235614Z`

Both checksum manifests pass. The dry run started from the archived originals, prepared same-directory temporary files, atomically replaced exactly the three simulated targets, verified candidate hashes/version/lint, atomically restored all three originals, and reverified every original hash. No temporary deployment file remained. The live production plugin was not touched.

## Production deployment receipt

The explicitly authorized production deployment ran from `2026-10-05 00:31:12` through `00:31:15 UTC` (`2026-10-04 19:31:12` through `19:31:15 CDT`). Immediately before the first runtime write, production was freshly reverified as active `0.1.65.2`: all three base hashes, the 61-file inventory, and canonical tree SHA-256 `029816fedf703f10da41f728461623f4670ca4b7e89c7835baf75aaa6715b760` matched this report. The candidate and rollback manifests passed again.

Only the three reviewed runtime paths were copied to same-directory temporary files and atomically renamed over their corresponding targets. Server-side failure handling was armed to atomically restore all three archived originals if any deployment-integrity, lint, active-state, or version check failed. No rollback was required.

Post-deployment verification passed:

- all three deployed file hashes exactly match the `0.1.65.3` candidate column;
- the complete deployed tree remains 61 files and matches canonical SHA-256 `060e5c85b26cdeea386349ebfcee1813b6e3d9d5e7d52b20a16292f5c5dcfaee`;
- the plugin remains active, reports `0.1.65.3`, and its entry file passes remote PHP lint;
- a fresh HTTP 200 response from `https://serenaderange.com/wp-content/plugins/vms-ops-console-premium/pwa/assets/js/app.js?ver=0.1.65.3` has exact SHA-256 `f11a44cead1b611b985d132e313e23627f082d94fa6e20e4b1d05eea2bbe330e` and contains the `getKind()`-first classification;
- `https://serenaderange.com/vms-ops/sw.js?ver=0.1.65.3` returns HTTP 200, has SHA-256 `4b857367564098cf5d7cd3eb3728997419d062f88790c3f925b927cac55824ac`, uses cache `vms-ops-shell-0.1.65.3`, and references the `0.1.65.3` app and bundled ZXing assets;
- the production manifest returns HTTP 200 with production start URL and scope `https://serenaderange.com/vms-ops/`;
- the unauthenticated PWA route retains its expected HTTP 302 login redirect;
- production `error_log` remained byte- and timestamp-identical at `16,933,175` bytes, and both `wp-content/debug.log` and a plugin-local `error_log` remained absent;
- no `.codex-*` deployment file remains in the production plugin tree;
- the candidate and rollback manifests still pass after deployment;
- a fresh public staging worker check confirms staging remains `0.1.70.3`.

Scanner preferences and site storage were not read, cleared, migrated, or changed. No ID image, identity information, or decoded payload was retained.

## Remaining production acceptance checks

| Check | Status |
| --- | --- |
| Production deployment and server-side integrity/runtime checks | `PASS` |
| Delivered `app.js?ver=0.1.65.3` and service-worker cache identity | `PASS` |
| Actual production-phone level, approximately `+10°`, and approximately `-10°` ID scans | `NOT RUN` |
| Production-phone straight-on versus angled timing and preview continuity | `NOT RUN` |
| Production-phone camera choice, zoom/framing, close/reopen, and PWA relaunch continuity | `NOT RUN` |
| Authorized production ticket scan | `NOT RUN` |
| Zoom-unsupported or native-PDF417 device, if available | `NOT RUN` |

The dark-background limitation is not claimed fixed. Use a light backing and even, glare-controlled lighting during acceptance.
