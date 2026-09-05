# WordPress.org Prefix Migration — Phase B2

Date: 2026-08-26

## Status

B2 implementation is complete at core commit `d781aa4ef8f0941384502d4a8740e5c9176ef957`. This document preserves the B2-time package and scan evidence, but its architecture-review hold and incomplete global interpretation are superseded by the verified B2.5 correction in `docs/WPORG_PREFIX_MIGRATION_B2_5.md`. B3 still requires separate explicit authorization.

This checkpoint does not authorize or implement B3 functions, B4 browser identifiers, B5 persistent keys, B6 schedules/storage, B7 public contracts, or B8 final-package cleanup.

## Recovery and corrected B1 base

- The unverified partial B2 work was preserved outside the repository at `/private/tmp/bvm-wporg-b2-partial-recovery/` and `/private/tmp/bvm-wporg-b2-partial-recovery.tar.gz` before reset.
- Preserved scope: `233` paths (`232` tracked plus the new B2 foundation test).
- Binary patch SHA-256: `b11f2ff562fbd99b009ae3311ed05ac43853a4f861be7c8f65fe0b3085de26bf`.
- Untracked patch SHA-256: `265a882fda1166b01f1555d1bef656522e7124c4f1cf4463079ff458dca6d2de`.
- Recovery archive SHA-256: `6781a3a11f0df7b2b417ac7a2e681a7eb969b5d88938ba8a826f3117eff92ffd`.
- The isolated core was reset only to `87d071629677007dc97c6906c4088b859c4fa003` and passed preflight.
- The semantic five-add-on rescan found exactly the seven omitted dependencies already recorded below and no additional B2 dependency.
- Corrected B1 checkpoint: `3430925213b1750a8741d5acf321f04de4705da3` (`Correct WPORG B2 add-on dependency map`).

## Exact B2 symbol map

The normative old-to-new map is the ordered `175`-entry array at `docs/wporg-prefix-migration-manifest.json#/completed_batches/B2/symbol_map`. Each entry records its kind, exact legacy identifier, exact canonical identifier, and every declaration site. The committed manifest SHA-256 at the B2 implementation checkpoint is `4c78ced6a65e126ad91a9f05027e0842501de7f094eed797854bbf6e98fa3f12`.

| Kind | Unique old→new mappings | Declaration/slot sites | Rule |
| --- | ---: | ---: | --- |
| Classes | 23 | 23 | `VMS_*` → `BVMGR_*` |
| Interface | 1 | 1 | `VMS_Social_Provider_Interface` → `BVMGR_Social_Provider_Interface` |
| Constants | 107 | 116 | `VMS_*` PHP symbol → `BVMGR_*`; value retained unless B0 expressly says otherwise |
| Request/global slots | 44 | 232 | `vms_*` slot/loader identifier → `bvmgr_*` |
| Total | 175 | 372 | Atomic declaration/reference cutover |

The map is deterministic: `php -d memory_limit=1G scripts/generate-wporg-prefix-manifest.php --check` reproduces it. No blanket legacy class, interface, or constant aliases ship in the public package.

The nine guarded duplicate constant families now resolve only under `BVMGR_`: `BVMGR_ADMIN_PARENT_SLUG`, `BVMGR_CPT_EVENT_PLAN`, `BVMGR_DB_TABLE_VENDOR_USER_LINKS_SUFFIX`, `BVMGR_SCH_CURRENT_SCOPE_META_KEY`, `BVMGR_SCH_CURRENT_VENUE_META_KEY`, `BVMGR_USER_PRIMARY_VENDOR_META_KEY`, `BVMGR_VENDOR_APP_CPT`, `BVMGR_VENDOR_CPT`, and `BVMGR_VENDOR_PRIMARY_USER_META_KEY`.

## Implementation boundary

- All B2 class/interface declarations and references, type checks, factories, registries, reflection/type literals, constant symbols, and frozen request-global slots use the canonical family.
- Bootstrap constants use `BVMGR_`; loader-local names from the frozen map use `bvmgr_`.
- `backstage-venue-manager.php` remains the canonical entry file. The headerless `vendor-management-system.php` bridge and delegating `vms.php` path remain operational.
- The `4,521` unique B3 procedural functions at `4,541` declaration sites remain `vms_*` and callable.
- All eight function/global collision names retain their function side for B3 while only their global slot side changes.
- Function-existence, callback, and type-literal inventories remain resolvable; no half-renamed callback registry was introduced.
- No persistent identifier, public hook, REST route, AJAX action, shortcode, handle, cron hook, Action Scheduler contract, table, option, meta key, capability, CPT/taxonomy value, or protocol value was migrated.

The immutable prohibited-global allowance set remains `4,696`. Current prohibited declarations are `4,521`, an exact reduction of `175` matching only the completed B2 map; no new prohibited declaration appeared.

## Retained values and coordinated add-ons

No canonical Git repository was found for the three affected installed add-ons. Their installed trees remained read-only; disposable copies and provenance live under `/private/tmp/bvm-wporg-b2-addon-isolation/`.

| Add-on | Legacy core symbol | Canonical core symbol | Retained underlying value/behavior |
| --- | --- | --- | --- |
| Events Slider | `VMS_CALENDAR_FEED_CACHE_BUST_OPTION` | `BVMGR_CALENDAR_FEED_CACHE_BUST_OPTION` | `vms_calendar_feed_cache_bust` |
| Data Tools | `VMS_Vendor_Schema_Registry` | `BVMGR_Vendor_Schema_Registry` | Same registry methods and vendor-import adapter behavior |
| Data Tools | `VMS_VENDOR_PRIMARY_USER_META_KEY` | `BVMGR_VENDOR_PRIMARY_USER_META_KEY` | `_vms_vendor_user_id` |
| Data Tools | `VMS_USER_PRIMARY_VENDOR_META_KEY` | `BVMGR_USER_PRIMARY_VENDOR_META_KEY` | `_vms_vendor_id` |
| Data Tools | `VMS_VENUE_CPT` | `BVMGR_VENUE_CPT` | Physical CPT `vms_venue` |
| Express Bar | `VMS_PLUGIN_FILE` | `BVMGR_PLUGIN_FILE` | Canonical core entry-file path |
| Express Bar | `VMS_VERSION` | `BVMGR_VERSION` | Version `1.2.0`; Event Plan fallback remains `vms_event_plan` |

Events Slider changes one file; Data Tools changes six files; Express Bar changes one file. The final patches are:

- `/private/tmp/bvm-wporg-b2-addon-isolation/provenance/vms-events-slider/compatibility.final.patch`, SHA-256 `0be9670ea4f3a4f93d8fcdf052660f448df8a5aca2fd3832da29b94a7554893f`.
- `/private/tmp/bvm-wporg-b2-addon-isolation/provenance/vms-data-tools/compatibility.final.patch`, SHA-256 `2e1dfb1ee34ff37d67509e08720ccc7485a97ed79a9d6313fd90a21021dc6c30`.
- `/private/tmp/bvm-wporg-b2-addon-isolation/provenance/vms-express-bar/compatibility.final.patch`, SHA-256 `bf1a65efc44b2020b2798771f2104db9b8bb210bd663e8a4b503b0e87ae7f424`.

`/private/tmp/bvm-wporg-b2-addon-isolation/tests/addon-compatibility.php` passes against the actual migrated core and proves all seven canonical consumers plus the retained physical values. No synthetic add-on commit was created because repository provenance is absent.

## Verification

The deterministic manifest check, B1/B2 guardrails, add-on contracts, migration-state reference, plugin identity, release-compatibility self-test, public-release pipeline test, runtime-stub guards, PHP lint for every changed/new PHP file, and `git diff --check` passed. The core implementation checkpoint changes `234` paths (`233` tracked paths plus one new test).

The disposable compatibility harness first passed the full seven-scenario dependency matrix against a code-identical development artifact. The final exact-hash candidate then passed standalone and supported-stack boot, fresh activation, deactivation, reactivation, repeated activation, authenticated admin and public requests, controlled upgrade from `vms/vendor-management-system.php`, repeat upgrade, interruption recovery, fixture preservation, and uninstall preservation. Its report status is `WARN` only because the third-party TEC stack emits PHP 8.5 deprecations; standalone is `0` fatal, `0` warning, `0` deprecated.

Final clean package:

- Source: `d781aa4ef8f0941384502d4a8740e5c9176ef957`.
- ZIP: `/private/tmp/bvm-wporg-b2-final/build/backstage-venue-manager-1.2.0-public-release.zip`.
- SHA-256: `81e67194396108e312d4c989fc014bb66003e9d15743327110383a5bc1a9b848`.
- Builder: `PASS`; `374` staged files, `271` PHP lints, `55` JavaScript syntax checks, clean Git state, and all package-integrity checks passed.
- Compatibility report: `/private/tmp/bvm-wporg-b2-final/compatibility/backstage-venue-manager-1.2.0-release-compatibility.report.json`, SHA-256 `c7ece8c793d536d8ef8d8763c9997db477380b2cdf906ada4f2ad864c7ef4930`.

## Strict Plugin Check contradiction

Canonical Plugin Check exited `0`. WP-CLI emitted only its known PHP 8.5 `react/promise` deprecation (`204` stderr bytes and the same prefix on raw stdout); the normalized strict JSON is `/private/tmp/bvm-wporg-b2-final/plugin-check/plugin-check.strict.json`, SHA-256 `315c84098035526ded25fb58f56850f4e0845dfccd6bf90fe0dc941d9d9cd92d`.

The established `125` residual rows are exactly identical after sorting by file, line, column, type, code, message, and docs: `OutputNotEscaped 123 + OffloadedContent 1 + NonEnqueuedStylesheet 1`.

The full scan nevertheless reports `5,331` rows: `125` errors and `5,206` warnings across seven codes. The four new codes are all `WordPress.NamingConventions.PrefixAllGlobals`:

- `NonPrefixedFunctionFound 4,541`: exact match to the frozen B3 declaration-site count.
- `NonPrefixedHooknameFound 187`: `181` `vms_*` hook sites assigned to later hook/schedule batches plus six retained third-party hook sites.
- `DynamicHooknameFound 1`: the B7 dynamic custom-hook family.
- `NonPrefixedVariableFound 477`: a later runtime-scope audit corrected this B2-time interpretation to `420` Event Plan partial rows that execute in method scope, `52` genuine vendor-profile template globals, and five genuine loader globals.

This occurs because Plugin Check discards prefixes shorter than four characters. Before B2 it found no valid prefix and did not activate the WPCS global-prefix sniff. B2 introduces `BVMGR`, which activates the sniff while B3/B6/B7 are intentionally unfinished. A canonical intermediate-package scan therefore cannot both preserve the approved batch boundary and report zero new Plugin Check codes.

The five true loader-scope rows are an additional B1 inventory contradiction: `$tax_file` at `includes/portal/vendor-portal.php:32`, `$pt` at `includes/vendor-applications.php:1266`, `1286`, and `1328`, and `$hook` at `includes/social-share/queue-runner.php:452`. They are three identifiers at five scanner sites, were not among the frozen `44` B2 slots / `232` sites, and were subsequently handled by the additive B2.5 map rather than being folded into the immutable B2 record. The same audit found `52` scanner rows across `37` names in the globally executed vendor-profile template plus the scanner-missed `$tag`, yielding `41` omitted semantic slots and `194` token sites in total.

At this historical B2 checkpoint the requested strict delta gate was not satisfied: four new Plugin Check codes and `5,206` newly surfaced warnings existed. The B2.5 migration-aware gate now supersedes that misleading historical comparison without weakening final release requirements. It keeps the `125` historical rows separate, classifies every remaining prefix row, and requires both `UNEXPECTED_PREFIX_FINDING=0` and `UNMAPPED_PREFIX_FINDING=0` while completed batches remain scanner-zero.

## Architecture decision resolved by B2.5

The authorized decision was the phase-aware approach. B2.5 prefixes the `38` globally executed vendor-profile template variables and the three loader globals without a closure/template-loader redesign; preserves all `420` Event Plan partial rows as individually inventoried method-scope exceptions; preserves the exact six external/core hook contracts; and records the corrected complete semantic ledger separately from the original B1/B2 ratchet. The fresh B2.5 scan and gate are authoritative for B3 readiness.

## Handoff

Historical B2 verdict: `B2 COMPLETE — ARCHITECTURE REVIEW REQUIRED BEFORE B3`. Superseded current verdict: `B2.5 COMPLETE — SCANNER MODEL RECONCILED — READY FOR B3`.

No push, merge, upload, tag, deployment, staging/production change, primary-worktree change, installed/live add-on change, sibling live-core sync, production convergence, or protected-stash mutation occurred.
