# BVM Add-on Compatibility Phase 7 — Release and Deployment Closeout

Date: 2026-08-31

Status: complete. The repository push, staged rollout, production rollout, and final non-business verification completed after the explicit `confirm compatibility closeout deployment` authorization.

## A. Repository and environment baseline

- Repository branch: `work/unreleased-2026-06-18`
- HEAD: `36391a4d563b355f91630e677dcefef281b23165` (`Classify DRM Events Bridge compatibility`)
- Starting worktree: clean; required preflight passed.
- Protected stash: `WPORG-16D preserve unrelated sidebar+doc work` present and untouched.
- Origin branch before authorization: `bf13cc94d5fd08b789cd1189cb50c1297227632b`.
- Origin branch after the authorized normal push: `36391a4d563b355f91630e677dcefef281b23165`; local and origin are `0` ahead and `0` behind. No history rewrite, merge, tag, or release was created.
- Production BVM: active `backstage-venue-manager` `1.2.0`, `383` files, canonical tree SHA-256 `6abda77506b33e3c8f9f5cf3e375947f6e7c8f7bdf442cb4c189e296436d2684`, entry SHA-256 `1c2f6c652838b32dc71e8f4c0c9a45b756d0edfe508f68565a5300d562f42b0b`. The legacy production `vms` `1.1.0` tree is inactive.
- Staging BVM/VMS: active `vms` `1.1.0`, `382` files, canonical tree SHA-256 `03bcae7e494f5f8db2da4314f79f38697714ebdeec942c48e363fec1bedd8ad0`.

## B. Campaign commit classification

| Commit | Classification | Deployment meaning |
| --- | --- | --- |
| `dfd0992451fe98fa50bbc8430d4c5e558bb719d1` | `ARTIFACT`, `TEST/HARNESS`; the patch encodes separate `ADD-ON RUNTIME` for Fill Dates | No BVM runtime file; release through Fill Dates only. |
| `74817b246c305bb16e9f09fd2201f699e3eac8a5` | `ARTIFACT`, `TEST/HARNESS`; the patches encode separate `ADD-ON RUNTIME` for Fill Dates | No BVM runtime file; release through Fill Dates only. |
| `d7a2a20fc2a8704e6dc21f47fb38c0af284a7ebe` | `TEST/HARNESS`, `DOCUMENTATION/EVIDENCE` | Repository-only. |
| `a9c31145ddbb1abb3bb1837d2a6fbda3dff4eccb` | `TEST/HARNESS`, `DOCUMENTATION/EVIDENCE` | Repository-only. |
| `8833dd2fb9314fb9921e7680a62e47542da6057d` | `ADD-ON RUNTIME` inside a separate Commerce artifact, `ARTIFACT`, `TEST/HARNESS`, `DOCUMENTATION/EVIDENCE` | Release Commerce `0.2.12`; no BVM runtime file. |
| `36391a4d563b355f91630e677dcefef281b23165` | `TEST/HARNESS`, `DOCUMENTATION/EVIDENCE` | Repository-only; authoritative Bridge source remains its own Git release. |

No campaign commit changes a PHP, JavaScript, CSS, or other runtime file shipped inside the public BVM plugin.

## C. Fill Dates reconciliation and release

### Authoritative material

- Untouched `0.1.7` baseline ZIP: `vms-fill-dates-0.1.7-tour-i18n-log-cleanup.zip`, SHA-256 `c0cc5d203aa42e4a682f290376e246a9dcd8758ce6f96b19b34bebdf30ed1560`, canonical 9-file tree `320cc0cf132b77815ee5ddd066154f45509452ff49eb8dc18410e56796afbb7a`.
- Phase 2A patch SHA-256: `dc102680bcd00721dc4711dff96bb49eef05ad161add6c5dd99cee947e3809ac`.
- Phase 2B incremental patch SHA-256: `8a81157e5e9cb8ddb63a353e72e508723ee3cfc951f789aaf1f8d790e54de75c`.
- Cumulative Phase 2A+2B patch SHA-256: `84a5eaad0e3d2b56222d6ae1a4de628cb441d021d01a84be5725c9356f8d428d`.
- Applying the cumulative patch to untouched `0.1.7` reproduces the installed corrected 9-file tree byte-for-byte at canonical tree SHA-256 `5abccf40f0edbf190c7fe6859bd2a87974af06b1e2afadad801e2182a1e53d9c`.

### Release decision

The archive history is monotonic through `0.1.7`; `0.1.8` is the appropriate next patch release. The release candidate changes no new functional behavior beyond Phase 2A/2B. It advances the header/constant/build metadata, adds release handoff/test-plan files, and preserves all corrected runtime files.

- Release metadata patch SHA-256: `4072ca4ae17232507eb39353da5b59f93d1ca73d232d4b88e83b2872fe161ea6`.
- Release ZIP: `docs/addon-compatibility/artifacts/vms-fill-dates-0.1.8.zip`.
- ZIP SHA-256: `e1e4a1c653fd7b6f51033b4163661c9a8c7b98285debfce8cd18559b7b7ff88c`.
- Exact candidate: `11` files, canonical tree SHA-256 `1e6534274b4314d720e409926e73b2eee9f9f2a62722aadba56be0ec5f4556f2`, entry SHA-256 `7d39adc5ac729095fef53d91213c4c94573ae28ddf1da81beab9407ba13479dc`.
- Two independent normalized builds produced byte-identical ZIPs.
- Untouched `0.1.7` + cumulative Phase 2 patch + release metadata patch reproduces the ZIP tree byte-for-byte.

### Installed state

| Environment | State | Evidence |
| --- | --- | --- |
| Local | `CURRENT` for Phase 2 behavior; active `0.1.7`; needs release metadata only to become the `0.1.8` candidate | Exact match to the patch-reconstructed corrected tree. |
| Staging | `CURRENT`; active `0.1.8` | Deployed in place under the preserved `vms-fill-dates-0.1.4` basename; exact 11-file release tree `1e6534274b4314d720e409926e73b2eee9f9f2a62722aadba56be0ec5f4556f2`. |
| Production | `CURRENT`; inactive `0.1.8` | Deployed in place under the preserved `vms-fill-dates-0.1.4` basename; exact 11-file release tree `1e6534274b4314d720e409926e73b2eee9f9f2a62722aadba56be0ec5f4556f2`. |

Updating either remote `0.1.4` tree to `0.1.8` changes `assets/admin.css`, `includes/admin-page.php`, `includes/helpers.php`, `includes/tours.php`, `vms-build.txt`, and `vms-fill-dates.php`, and adds the four versioned files under `docs/`. `README.md` remains unchanged.

## D. Commerce Discounts reconciliation

- Untouched `0.2.11` ZIP SHA-256: `4f06637af51f06c576588c6a0d74e31f01b4c9a4a2da9b01ac5cfd8a654ea0b7`.
- Patch SHA-256: `026eb45df397d987538915cc24be6cd45d62f99ab468f746f635c9e8554cdb0e`.
- Corrected `0.2.12` ZIP SHA-256: `0cd5f4d2d0ce3dd9484d85442dff38783bfa45f17f46bdd942d1e9ba9962b001`.
- Corrected candidate: `26` files, canonical tree SHA-256 `3a3528dfa1ed5d76608f27504ffed4990d1c2c320f1897d4c0cb49200531a060`, entry SHA-256 `b2f8e43c542af7ae30b3c29ef71896555eeb635e3d94023bea4fac31785dfb18`.
- ZIP integrity passed. Applying the patch to fresh `0.2.11` reproduces the candidate byte-for-byte.
- The focused no-Square / Square-present / no-WooCommerce / deterministic-repeat regression passed.

| Environment | State | Evidence |
| --- | --- | --- |
| Local | `NEEDS UPDATE`; inactive `0.2.4` | Exact match to the archived `0.2.4` source tree. |
| Staging | `CURRENT`; active `0.2.12` | Exact 26-file release tree `3a3528dfa1ed5d76608f27504ffed4990d1c2c320f1897d4c0cb49200531a060`; ordinary WooCommerce and Square-present paths passed. |
| Production | `CURRENT`; inactive `0.2.12` | Exact 26-file release tree `3a3528dfa1ed5d76608f27504ffed4990d1c2c320f1897d4c0cb49200531a060`; isolated bootstrap and focused regressions passed. |

Production `0.2.11 -> 0.2.12` changes `includes/class-vms-discounts-loader.php`, `readme.txt`, `vms-build.txt`, and `vms-commerce-discounts.php`, and adds `vms-commerce-discounts-test-plan-0.2.12.md`. The Square bridge itself remains byte-identical.

## E. DRM Events Bridge

- Authoritative release: `0.2.2`, commit `b1efcc974233a3b43c2a9efa30533c6688f87320`, Git tree `3e2c1e49411c6811065f7f8eacca8fdaf736ed60`.
- Known release ZIP SHA-256: `d1f536c199a4583ed4c3f7a189e615ba5667bd1d1e1e06c16d3c7eda354f30fd`.
- Final staging and production SSH inspection found Bridge active at `0.2.2`; all nine files on both sites match the authoritative commit byte-for-byte at normalized source-manifest SHA-256 `075878dc3628d5f6f26ce96e51ea328c0ce040ddf2b7036e3c18136029d979b3`.
- Both final public endpoint checks returned HTTP 200 with 50 records, the exact 13-field allowlist, 44 `normal`, six `private_event`, zero invalid shapes, and zero unsafe private records.
- Fresh Phase 6A runtime evidence passed and retained the dirty local Bridge fingerprint `6cdc2a1cde71acbab832a682aa0fb3d6f784d6c51fdacec93d2cab55396126c6` unchanged.
- Decision: `NO CHANGE`; do not redeploy Bridge.

## F. BVM core deployment decision

**NO BVM CORE DEPLOYMENT REQUIRED FOR COMPATIBILITY CAMPAIGN**

The campaign commits contain patches, add-on release material, harness code, tests, documentation, and evidence only. No runtime file belonging to the public BVM plugin changed in this campaign.

## G. Final test baseline

- Required repository preflight: PASS.
- Fill Dates cumulative patch reconstruction: PASS, byte-for-byte.
- Fill Dates exact `0.1.8` PHP lint: PASS.
- Fill Dates Phase 2A returned-hook test: PASS against exact candidate.
- Fill Dates Phase 2B native-notice test: PASS against exact candidate.
- Fill Dates ZIP integrity and two-build reproducibility: PASS.
- Official-five disposable suite: PASS, `18/18`, against exact Fill Dates `0.1.8`; cleanup and normal active-plugin preservation passed.
- Commerce exact `0.2.12` regression: PASS for no Square, Square present, no WooCommerce, and deterministic repeat.
- Commerce patch/ZIP reconstruction: PASS, byte-for-byte.
- Phase 6A supported ecosystem: PASS for Bridge, Commerce activation, both coexistence orders, all nine compatible Phase 4 integrations, blocked external HTTP, cleanup, normal state preservation, and unchanged Bridge forensic source.
- Safety Toolkit Pro remained retired and excluded.

Conclusion: no supported direct first-party BVM integration has a confirmed compatibility failure.

## H. Git push status

A normal fast-forward push moved origin from `bf13cc94d5fd08b789cd1189cb50c1297227632b` to `36391a4d563b355f91630e677dcefef281b23165`, publishing exactly the six classified campaign commits. Local and origin now match at `0` ahead and `0` behind. No rewrite, merge, force push, tag, or release occurred.

The Phase 7 artifact/evidence changes were not part of that six-commit push. Phase 7B separately authorized one closeout-evidence commit containing this report, the ledger update, the Fill Dates release patch, and the tracked Fill Dates release ZIP. Its final commit SHA and push result are recorded in the task handoff rather than self-referentially here.

## I. Rollback readiness

All rollback archives are stored outside the public web roots under `/home/coney/bvm-compatibility-closeout-rollbacks/2026-08-31/`. Directories are owner-only and every archive/receipt is mode `0600`. Each archive passed gzip/tar integrity and file-count verification.

| Target | Component | Version/state | Files | Archive SHA-256 |
| --- | --- | --- | ---: | --- |
| Staging | Fill Dates | `0.1.4`, active | 7 | `4bc65976467949052e8ea5375f93d753bb459ddf37c400677a81bdcf3ea9e7b7` |
| Staging | Commerce | `0.2.7`, active | 19 | `42e5560a6f22955e3c31b2ae747e27642b0241d975fdcbcf06b6f3ee5697869a` |
| Production | Fill Dates | `0.1.4`, inactive | 7 | `6b0f05fe067b56fe9af36a58243895c5d31028c435dabd1231e9b46950ef9e3c` |
| Production | Commerce | `0.2.11`, inactive | 25 | `88c9a6f3d88c183673627ae076458ee16ee58938d860a2e69195f5b38d7d962d` |

## J. Final release/deployment table

| Component | Final production/repository state | Release | Result |
| --- | --- | --- | --- |
| BVM compatibility repository | origin/local `36391a4d`; `0` ahead, `0` behind | Six committed campaign commits | `PUSHED` |
| BVM core | active `1.2.0`, canonical tree `6abda775…` | None | `NO CHANGE` |
| Fill Dates | inactive `0.1.8`, exact 11-file tree `1e653427…` | ZIP `e1e4a1c6…` | `DEPLOYED` |
| Commerce Discounts | inactive `0.2.12`, exact 26-file tree `3a3528df…` | ZIP `0cd5f4d2…` | `DEPLOYED` |
| DRM Events Bridge | active `0.2.2`, exact nine-file commit match | Same `0.2.2` | `NO CHANGE` |
| Phase 7 repository artifact/evidence | This report, ledger update, Fill release patch, and Fill release ZIP | One separately authorized Phase 7B closeout commit | `INCLUDED IN CLOSEOUT COMMIT` |

## K. Executed deployment and verification

1. The repository/remote/rollback drift check passed, and the six existing campaign commits were pushed normally.
2. Staging Fill Dates was updated in place from active `0.1.4` to active `0.1.8`; its basename and activation-list SHA-256 `1044b7edd058a908174dc739208f9e63d4afcdb48c5c8c0d8521170dd3e1024f` were preserved.
3. Staging Fill validation passed exact-tree comparison, PHP lint, WordPress bootstrap, single-menu/returned-hook/assets/tour checks, native notice placement, both focused Phase 2 tests, and log inspection.
4. Staging Commerce was updated in place from active `0.2.7` to active `0.2.12`; its basename, activation-list hash, and existing `_vms_discounts_migrated=1` marker were preserved.
5. Staging Commerce and ecosystem validation passed exact-tree comparison, PHP lint, WooCommerce/Square bootstrap, no false Square warning, focused no-Square/Square/no-WooCommerce/deterministic regression, one intended Fill and Commerce menu each, HTTP 200 home/login checks, and no new fatal or database log entries. A synthetic all-admin-hook probe produced only known WP-CLI current-screen warnings from WooCommerce/other plugins; the targeted probes and final smoke added no log entries.
6. Production rollback hashes were reverified. Fill Dates was updated in place from inactive `0.1.4` to inactive `0.1.8`, and Commerce from inactive `0.2.11` to inactive `0.2.12`. The production activation-list SHA-256 remained `b672249064094ae6a5cd45c8186671b3ed139a4d96b597ed34716f2c1a8b0232`; neither add-on was activated.
7. Both production trees matched their release candidates byte-for-byte. PHP lint, non-persistent WordPress bootstrap/menu/assets/notice probes, Fill Phase 2 tests, Commerce focused regressions, BVM/WooCommerce/TEC/database health, and HTTP 200 home/login checks passed. The production error log remained unchanged at its checkpoint and no `wp-content/debug.log` appeared.
8. Dalene staging and production retained active DRM Calendar Intake `0.2.4`, Router `0.1.4`, and Bridge `0.2.2`. Both Bridge trees were exact nine-file matches to commit `b1efcc9`, both public contracts passed, and the dirty local Bridge fingerprint remained `6cdc2a1cde71acbab832a682aa0fb3d6f784d6c51fdacec93d2cab55396126c6` with a clean index.

Neither release introduced a new database migration. Commerce retains its existing activation callback, but preserving activation state did not run it; its existing migration marker remained `1`. Fill Dates has no deployment-time migration.

## L. Campaign closure

**BVM DIRECT FIRST-PARTY ADD-ON COMPATIBILITY: FUNCTIONALLY COMPLETE AND DEPLOYED**

Final production validation date: `2026-08-31`.

- Released/deployed versions: Fill Dates `0.1.8`; Commerce Discounts `0.2.12`.
- Unchanged supported foundation: BVM `1.2.0`, WooCommerce `11.0.1`, The Events Calendar `6.17.3.1`, DRM Events Bridge `0.2.2`.
- Bridge architecture conclusion: Router contract v2 is authoritative; DaleneRichelle.com does not require local BVM, and BVM remains optional legacy/future enrichment only.
- Safety Toolkit Pro `0.1.0` remains a retired, intentionally unsupported prototype.
- Non-blocking hardening debt remains: Express Bar returned-hook storage, Data Tools menu-ownership cleanup, and VMS-to-BVM wording/text cleanup.
- Out-of-scope work remained untouched: BVM core deployment, Bridge source/deployment, Safety changes, Event Reschedule/outreach, GigMeasure, branding/hardening implementation, and the dirty local Bridge worktree.
- Rollback archives remain available outside the public web roots. No rollback was required.
- Phase 7 documentation/artifact changes are included in the separately authorized Phase 7B closeout commit; its SHA and push result are recorded in the task handoff.
