# WordPress.org Prefix Migration — Phase B4

Status: verified complete in the isolated branch; exact package, strict scan, lifecycle, and browser QA passed
Authorized baseline: `bdd84df7bcbfcec65ee57fedf561bf4e167761f6`
Branch: `codex/wporg-reviewer-identity-alignment`
Scope: browser globals, script/style handles, nonce actions and fields, query/rewrite identifiers, and WP-CLI paths only

## Frozen authority and evidence corrections

`docs/wporg-prefix-b4-identifier-map.json` is the immutable row-level authority for B4. Its SHA-256 is `d7c67987a332ac3355d6bb61c6e155f3ea4fbf61db97ee21b2f01087ed8067f0`. It freezes every identifier, canonical target, exact source/producer/consumer site, compatibility policy, and known add-on impact:

- 29 browser globals.
- 64 asset handles across 99 enqueue/register calls, 105 resolved source sites, 34 dependency sites, and 19 consumer sites.
- 218 nonce-action families: 154 static and 64 normalized dynamic families.
- 73 legacy custom nonce fields mapping to 72 canonical fields because the two historical heavy-admin spellings intentionally converge.
- 14 query vars, four rewrite tags, seven rewrite rules, and three CLI paths.

The row-level freeze corrected three aggregate/prose defects in the prior evidence before runtime work began. The historical static-nonce total was 156 but both the authorized source and the original manifest-introduction commit enumerate exactly 154. The five documented `window.VMS_*` globals were only a subset of the 29 shipped plugin-owned browser globals. Refer a Friend's single `vms-admin` occurrence is an admin menu parent slug, not an asset-handle consumer; it remains unchanged and the known-add-on B4 impact is zero.

The deterministic control surface is `scripts/generate-wporg-prefix-b4.php`, `scripts/lib/wporg-prefix-b4.php`, `scripts/apply-wporg-prefix-b4.php`, and `tests/wporg-prefix-b4-guardrails.php`. The generator refuses to overwrite the frozen map.

## Browser globals and asset handles

All 29 frozen plugin-owned globals use their map-approved canonical `BVMGR_*`/`bvmgr*` identity at PHP bootstrap/localization producers and JavaScript readers/writers. There is no legacy browser alias: all producer/consumer changes are atomic, so initialization remains single-owner.

All 64 `vms-*` handles are canonicalized to `bvmgr-*` at registrations, dependency arrays, localization relationships, and consumer APIs. The semantic graph proves that each canonical handle resolves and that no legacy or canonical alias creates a duplicate source URL. No known add-on is an asset-API consumer, so no dependency-only handle alias was warranted.

## Nonces

New output generates the 218 canonical `bvmgr_*` actions/families and the 72 canonical custom field names. Incoming legacy field names normalize only when the canonical field is absent. Verification is canonical-first and falls back only to the exact mapped `vms_*` counterpart; invalid and wrong-action nonces remain invalid.

Native `wp_verify_nonce()`, `check_admin_referer()`, and `check_ajax_referer()` calls remain at every verification site so WordPressCS and Plugin Check retain data-flow visibility. Two action selectors choose the exact canonical or legacy action before the native verifier executes. No capability, authorization, sanitization, REST, AJAX action, or B7 hook contract changed.

Legacy action/field acceptance is temporary and bounded by the last pre-B4 page/form cache plus WordPress's nonce validity window. The exact removal criteria are frozen in `docs/wporg-prefix-b4-compatibility-retirement.json`.

## Query vars, rewrites, and CLI

New URLs and all seven rewrite-rule targets use canonical `bvmgr_*` query vars. The 14 canonical vars are registered and read first, while all 14 legacy inbound vars and the four legacy tags remain accepted. Public route regexes, CPT/taxonomy values, the vendor-application confirmation-token hash salt, the B7 endpoint hook, and existing `vms_rewrite_*` options remain unchanged.

`bvmgr_prefix_b4_rewrite_version` is the only new persistent B4 marker. It guards one soft rewrite flush at version `1`, records completion after the flush, and is idempotent on later requests.

The canonical commands are `bvmgr stale-check`, `bvmgr square-ticket-mirror`, and `bvmgr state-of-range`. Their three legacy `vms ...` paths register as transitional aliases of the same callback/class, preserving output and avoiding duplicate side effects.

## Compatibility retirement and add-ons

`docs/wporg-prefix-b4-compatibility-retirement.json` (SHA-256 `5690812be0a30be2110bcd686373f40bf01ec05abe64cf54a36ba16ede3082fb`) freezes 308 entries: 294 temporary nonce/CLI items and 14 indefinite legacy inbound query contracts. Temporary compatibility may be removed only after its record's cache/nonce/operational window and release-floor conditions are satisfied. The query contracts survive indefinitely because they protect bookmarks, cached URLs, and existing content.

Fresh disposable copies of all five known add-ons under `/private/tmp/bvm-wporg-b4-addon-isolation` prove zero semantic B4 consumers and therefore zero patches. `docs/wporg-prefix-b4-addon-compatibility.json` records source provenance, tree hashes, semantic scan results, and installed-baseline checks. Installed/live add-ons remain untouched.

## Verification and checkpoints

The coherent B4 checkpoints are:

1. `19c5e0a` — browser globals and asset handles.
2. `9fccd66` — nonce actions, fields, and bounded compatibility.
3. `ff9b753` — query vars, rewrites, CLI paths, and disposable add-on proof.
4. Final verification/documentation checkpoint — native nonce-verifier visibility repair, exact package/scan/lifecycle/browser evidence, and closeout.

Every implemented checkpoint passes the manifest generator/guardrail, B2/B2.5, B3 map/progress/guardrail, B4 map/category/compatibility guardrails, scanner inventory tests, five-add-on installed and disposable checks, plugin identity, runtime stubs, release compatibility self-test, PHP lint, JavaScript syntax, release-builder self-test, and diff checks.

The exact public release ZIP at `/private/tmp/bvm-wporg-b4-9d031cc/backstage-venue-manager-1.2.0-public-release.zip` has SHA-256 `367d534ede2e8c6ced2c8576e3ca8c8044abacc662c1ad1266006203c554b660`. It passed package integrity with 375 staged files, 272 PHP lints, and 55 JavaScript syntax checks. The build report SHA-256 is `f383b9e3514c4ebf88f93ba866b16d9a19cb832a5b3fffd0720e5f3caafe270d`.

Strict packaged Plugin Check produced `/private/tmp/bvm-wporg-b4-9d031cc/plugin-check/plugin-check.strict.json`, SHA-256 `738c37c327175e2cb44b985d4b1b439e8822440101cb928fbf00b195c08389dd`. Its 733 rows reconcile exactly to 125 historical errors and 608 warnings: 123 `OutputNotEscaped`, one `OffloadedContent`, one `NonEnqueuedStylesheet`, 187 non-prefixed hook names, one dynamic hook, and 420 method-scope variables. The phase-aware scanner gate classifies exactly B7 `182`, method scope `420`, and external/core `6`, with zero B3/B4 residual, unexpected, or unmapped findings. Recording a later exact scan permits source-coordinate relocation only after proving the historical semantic multiset is unchanged.

The full exact-ZIP lifecycle report at `/private/tmp/bvm-wporg-b4-9d031cc/compatibility-full/backstage-venue-manager-1.2.0-release-compatibility.report.json`, SHA-256 `d35fd28e57d12a460e784726c713ec12f1e480b6ed350b11f4ba345cdadbf242`, completed all seven dependency scenarios without a plugin fatal. Scenarios A, B, and E pass; C, D, F, and G warn only for captured dependency/PHP deprecations. Clean activation/deactivation/reactivation, historical-basename upgrade, interruption recovery, fixture preservation, and uninstall preservation all completed; no duplicate scheduled-work ownership or preservation regression occurred.

Browser QA on a disposable exact-ZIP site passed. The report at `/private/tmp/bvm-wporg-b4-9d031cc/browser-qa/browser-qa.report.json` has SHA-256 `d0b30a273b5cb901d49de9595c67dc0448b5273cea0dcb640e0606152a814a78`. The BVM dashboard, Event Plan editor, and Ticket Integrity screen initialized only canonical globals and canonical asset IDs with no duplicate URLs or console errors. Canonical and legacy pass-claim URLs rendered equivalent behavior with the same canonical assets, and the public vendor-application page had no duplicate URL or console error.

The primary development checkout remains at `bf13cc94d5fd08b789cd1189cb50c1297227632b`; the protected stash `WPORG-16D preserve unrelated sidebar+doc work` remains intact. Installed/live core and all five add-ons remain equal to their frozen B3 hashes. No live sync, push, package publication, deployment, WordPress.org action, or reviewer reply occurred.

## Scope boundary and B5 handoff

B4 does not rename B7 hooks, AJAX actions, REST contracts, shortcodes, option/meta/storage identifiers, cron/Action Scheduler contracts, tables, CPT/taxonomy stored values, physical paths, or historical protocol values. Eight same-spelled nonce/AJAX identifiers changed only in their nonce role; their `wp_ajax_vms_*` registrations and payload actions remain intact for B7.

B5 may begin only after explicit authorization. Its persistent option/meta/settings inventory must treat `bvmgr_prefix_b4_rewrite_version` as an authorized B4 migration marker and must not reinterpret it as an unreviewed B5 rename.
