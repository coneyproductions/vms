# BVM Add-on Compatibility Phase 5A — Commerce Missing-Square Repair

## A. Baseline

- Repository/worktree: `/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public/wp-content/plugins/packages/vms-github-reconcile`
- Branch: `work/unreleased-2026-06-18`
- Starting HEAD: `a9c31145ddbb1abb3bb1837d2a6fbda3dff4eccb` (`Audit additional BVM integrations at runtime`)
- Starting worktree and `git diff --check`: clean
- Protected stash: exact `WPORG-16D preserve unrelated sidebar+doc work` entry present at `stash@{0}`
- Phase 4 evidence SHA-256: JSON `c27d8f7ba54de6274dd5d603c13c6322cc7fe9600cbe023c707becaa54109383`; text `9c1ef8dc3a7358f6261db1564b197e62065878a1034f887cb0d81d680a1e9a34`; narrative `09ba74f831c4fa15edd452db68e5ba32a683146ba318aa086b313cc395331951`
- Authoritative Commerce source: normal plugin inventory archive `vms-commerce-discounts-0.2.11.zip`; it is not Git-controlled
- Baseline archive SHA-256: `4f06637af51f06c576588c6a0d74e31f01b4c9a4a2da9b01ac5cfd8a654ea0b7`
- Baseline extracted tree: `25` files, canonical tree SHA-256 `cdc3d7cdbbce0e5383ba54e1247ef27b3fe237cacbf00b646c139e2dbc18e3bf`
- Baseline entry SHA-256: `01648834536998aeb44deba7c59b22cbb5d1714c6c0fed3d5841f7dc523cf341`, exactly matching the Phase 4 source manifest
- Excluded nearby stale/older copies: installed `0.2.4`; temporary directory whose header reports `0.2.9`; root `0.2.8` guard ZIP; archive directory packages `0.2.1`, `0.2.4` (unversioned filename), `0.2.5`, `0.2.6`, `0.2.7`, `0.2.8`, `0.2.9`, and `0.2.10`

## B. Pre-fix reproduction

The fresh disposable PHP 8.3 / WordPress runtime had BVM, WooCommerce, TEC, Event Tickets, Event Tickets Plus, and the original five active. WooCommerce Square was absent when Commerce Discounts `0.2.11` activation was attempted.

- Activation exit: `255`
- Fatal: `Class "WooCommerce\Square\Gateway\API\Requests\Orders" not found`
- Source: `vms-commerce-discounts/includes/class-vms-discounts-square-bridge.php:295`
- Include/activation path: loader `require_files()` at line `107`, called from `activate()` at line `90`
- Active state after failure: Commerce absent from `active_plugins`
- Partial state: `_vms_discounts_migrated` remained absent, so activation stopped before its only migration mutation
- Fresh pre-fix raw-log SHA-256: `0836477528c89ef4765a88b16850b6b990d59af8aaa66b90f562027ff8e045eb`
- Fresh Phase 5A pre-fix report SHA-256: `45ec8c208ae4517184fef7d96cc2c387c83e0de311716972206779f913ef3628`

The focused permanent regression also failed before production source changed with the same class, file, line, loader path, and exit `255`.

## C. Root cause and dependency inventory

`vms-commerce-discounts.php` registers `VMS_Discounts_Loader::boot()` on `plugins_loaded` priority `20` and registers `VMS_Discounts_Loader::activate()` as the activation callback. Both paths reach `require_files()`. The `0.2.11` loader always required `class-vms-discounts-square-bridge.php`, regardless of Square availability.

That bridge file declares exactly two classes:

1. `VMS_Discounts_Square_Bridge`
2. `VMS_Discounts_Square_Order_Request`

The second class unconditionally extended `WooCommerce\Square\Gateway\API\Requests\Orders`. PHP must resolve a parent class while declaring a subclass, so the fatal occurred before activation could finish. The later `class_exists()` checks inside `create_square_order()` were runtime guards and could not protect an earlier declaration-time dependency. The ordinary-runtime `try/catch` could register only a generic initialization-failure notice; activation called `require_files()` outside that `try/catch`, so it fatally exited first.

The complete source scan found no other unsafe Square declaration-time dependency. Direct `Square\*` references are confined to the guarded bridge file. Its return types and `instanceof` checks are safe declaration/runtime references once that file is conditionally loaded, while `SquareClient` and the request parent retain the bridge's existing runtime `class_exists()` check.

## D. Implementation

The corrected non-Git Commerce source changes only these production/release files:

- `includes/class-vms-discounts-loader.php`
- `vms-commerce-discounts.php`
- `readme.txt`
- `vms-build.txt`
- new `vms-commerce-discounts-test-plan-0.2.12.md`

The loader now checks for `WooCommerce\Square\Gateway\API\Requests\Orders` before requiring the Square-only bridge file. It instantiates the bridge only when both the parent and the expected bridge class exist. When Square is absent, Commerce keeps its non-Square runtime active, registers no Square gateway callback, and shows administrators: `WooCommerce Square integration is unavailable. Commerce Discounts will continue without Square-specific discount synchronization.` The existing optional debug logger records the same state when enabled.

The generic initialization-failure notice was not a usable dependency state: it incorrectly implied the whole plugin failed and disappeared once the fatal boundary was removed. The new notice is therefore narrowly limited to the unavailable Square-specific integration.

When Square is present, the original bridge file is byte-identical to `0.2.11` (`0772e154d112ab08bfb7d6afd3177b65971adce03ce13cea277011d412b6cda0`), and the same two filters are registered by the same bridge constructor.

## E. Regression coverage

Permanent coverage:

- `tests/addon-compatibility/commerce-square-activation-fixture.php`
- `tests/addon-compatibility/commerce-square-activation-regression.php`
- Phase 5A mode in the existing additional-runtime manifest, probe, report builder, source manifest, and disposable shell harness

The focused regression proves:

- activation without Square completes and reaches the legacy migration marker;
- no Square bridge/request class or Square gateway filter is registered while Square is absent;
- non-Square Commerce callbacks remain available;
- the targeted native warning is present and deterministic across repeated isolated runs;
- Square-present activation declares both bridge classes and preserves both gateway filters exactly once;
- the existing no-WooCommerce warning remains graceful.

Before: exit `255` with the line-295 fatal. After: focused regression `PASS` for no-Square, Square-present, no-WooCommerce, and deterministic repeat.

## F. Runtime results

The corrected Phase 5A runtime evidence is `test-results/bvm-commerce-discounts-phase5a-runtime.report.json` and `.txt`.

| Scenario | Result |
| --- | --- |
| BVM + WooCommerce + Square, BVM first | PASS |
| BVM + WooCommerce + Square, add-on first | PASS |
| BVM + WooCommerce, Square absent, activation | PASS; exit `0`, Commerce active, migration marker `1` |
| BVM + WooCommerce, Square absent, normal runtime | PASS; normal Commerce menu/callbacks present, Square classes/callbacks absent, targeted warning present |
| WooCommerce absent | PASS; existing WooCommerce notice preserved |
| BVM absent | PASS; Commerce retains its independent WooCommerce contract |
| Supported coexistence, BVM first | PASS |
| Supported coexistence, add-ons first | PASS |

Commerce's matrix result is `PASS — BVM-only runtime compatible`. The complete Phase 4-derived suite intentionally remains `FAIL` only because the known Safety Toolkit Pro defect is still represented; it was not masked or changed.

## G. Compatibility preservation

Phase 5A changes no discount calculation, cart calculation, order mutation, order meta, BVM API/contract, WooCommerce API, Square request implementation, Square API behavior when available, option/meta/table identity, route, public hook/action name, other add-on, or BVM core/runtime file. No shared BVM mirror/live runtime synchronization was required because no BVM runtime file changed.

No payment, order, email, Square API request, advertisement, webhook, external HTTP request, or production synchronization was dispatched.

## H. Reproducibility and versioning

The established Commerce release convention uses monotonic patch versions and updates the plugin header/constant, readme stable tag/changelog, build note, and version-specific test plan together. Because production source changed, Phase 5A uses `0.2.12`.

- Untouched baseline artifact: external `vms-commerce-discounts-0.2.11.zip`, SHA-256 `4f06637af51f06c576588c6a0d74e31f01b4c9a4a2da9b01ac5cfd8a654ea0b7`
- Incremental patch: `docs/addon-compatibility/vms-commerce-discounts-0.2.11-to-0.2.12.patch`, SHA-256 `026eb45df397d987538915cc24be6cd45d62f99ab468f746f635c9e8554cdb0e`
- Corrected artifact: `docs/addon-compatibility/artifacts/vms-commerce-discounts-0.2.12.zip`, SHA-256 `0cd5f4d2d0ce3dd9484d85442dff38783bfa45f17f46bdd942d1e9ba9962b001`
- Corrected/reconstructed tree: `26` files, SHA-256 `3a3528dfa1ed5d76608f27504ffed4990d1c2c320f1897d4c0cb49200531a060`
- Corrected entry SHA-256: `b2f8e43c542af7ae30b3c29ef71896555eeb635e3d94023bea4fac31785dfb18`

Applying the committed zero-context patch with `git apply --unidiff-zero` to a fresh extraction of the authoritative baseline produced a byte-identical corrected tree (`diff -qr` clean and identical canonical tree hashes). The ZIP exists only as committed reproducibility/release material; it was not installed, deployed, uploaded, tagged, or pushed.

## I. Validation

- Repository preflight before work: PASS
- Focused regression before source repair: expected FAIL, line-295 class-not-found fatal
- Focused regression after source repair, from staged tree and committed ZIP: PASS
- PHP 8.3 lint: all `12` Commerce PHP files and all modified/new PHP harness files PASS
- Shell syntax: modified additional-runtime harness PASS
- Additional runtime contract self-test: PASS (`10` runnable, `1` blocked)
- Corrected Phase 4-derived matrix: all Commerce and coexistence scenarios PASS; known Safety failure retained
- Corrected matrix determinism: JSON, text, and source manifest were byte-identical across two independent runs
- Phase 3 official-five suite: PASS with canonical JSON/text hashes `3b9d6378…218d2` / `979ae95a…a587`
- Patch apply/check and byte-for-byte reconstruction: PASS
- Corrected ZIP integrity: PASS
- External HTTP: blocked by the disposable harness
- Final `git diff --check`, staged-diff check, diff inspection, and repository preflight are recorded at closeout

## J. Commit and delivery state

Commerce has no independent Git repository, so the source correction is preserved in this BVM compatibility repository as the authoritative baseline hash, incremental patch, corrected ZIP, permanent regression, runtime evidence, and narrative. Phase 5A uses one focused repository commit; its SHA is reported in the task handoff because a commit cannot self-record its own SHA.

No push, deployment, normal-site activation/deactivation, normal database change, WordPress.org submission, reviewer reply, or protected-stash manipulation occurred.

## K. Remaining compatibility defects

The unresolved Phase 4 items remain unchanged and outside Phase 5A:

1. Safety Toolkit Pro / public BVM Safety contract architecture.
2. DRM Events Bridge runtime classification, blocked until its authoritative source is stable.
