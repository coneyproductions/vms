# Phase B0 migration specification

> Authority: This is the authoritative Phase B0 migration specification produced after the WordPress.org reviewer clarification that prefixes must contain at least four characters. Its architecture is controlling for Phase B implementation.

No files changed. Both worktrees remain clean, and the protected stash is intact.

The official handbook requires at least four-character global prefixes and recommends five; its common-issues guidance rejects two- and three-letter prefixes. It explicitly covers functions, types, namespaces, globals, options, and transients. [Plugin Handbook best practices](https://developer.wordpress.org/plugins/plugin-basics/best-practices/), [Plugin Review common issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/).

## Isolation verification

- Primary worktree: `work/unreleased-2026-06-18`, HEAD `bf13cc94d5fd08b789cd1189cb50c1297227632b`, clean.
- Isolated worktree: `codex/wporg-reviewer-identity-alignment`, HEAD `32a118f57b07827446a06e0d93962ca92c21d663`, clean.
- Phase A lineage verified:
  - `eb8f39372a3aca0fd68acbcda5b1075f84f5d6c5`
  - `3c783f8474d471970e90211024858cb22767596e`
  - `32a118f57b07827446a06e0d93962ca92c21d663`
- Protected stash: `WPORG-16D preserve unrelated sidebar+doc work`.
- Preflight’s only warning was the expected non-writable sibling live tree.
- Phase A evidence is recorded in [wporg-remediation-ledger.md (line 444)]\(/private/tmp/bvm-wporg-identity-phase-a/packages/vms-github-reconcile/docs/wporg-remediation-ledger.md:444).

Four read-only specialists covered non-overlapping inventories; none spawned nested agents.

## Canonical prefix decision

Confirm:

- Procedural PHP/functions/hooks/options: `bvmgr_`
- Constants and low-churn global classes: `BVMGR_`
- New properly namespaced OO code: `BackstageVenueManager\`
- Asset handles: `bvmgr-*`
- REST namespace: `backstage-venue-manager/v1`
- Protocol headers: `X-Backstage-Venue-Manager-*`

Exact matches for `bvmgr_`, `BVMGR_`, or `BackstageVenueManager` were zero across:

- The complete isolated repository
- Bundled dependencies
- The installed plugin root
- Known sibling/add-on trees

The prefix is five letters, product-related, and locally collision-free.

## Exact category inventory and migration matrix

Counts are semantic identifiers, not text-match counts. Categories M and X are projections of identifiers also represented elsewhere and therefore should not be added to other totals.

| CategoryExact current inventoryCanonical target and strategyCompatibility and risk |                                                                                                              |                                                                                     |                                                                                                      |
| ---------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| A. PHP functions                                                                   | Public package: 4,519 unique / 4,539 declarations. Complete mirror including excluded Safety: 4,577 / 4,597. | `bvmgr_*`; Strategy 1                                                               | No blanket wrappers. Critical.                                                                       |
| B. Classes/interfaces/traits                                                       | 23 classes, 1 interface, 0 traits/enums                                                                      | `BVMGR_*`; Strategy 1. Use `BackstageVenueManager\` for future OO work.             | Public provider interface requires coordinated migration. High.                                      |
| C. Constants                                                                       | Package: 107 unique / 116 definitions. Complete mirror: 116 / 125.                                           | PHP names → `BVMGR_*`; Strategy 1. Values follow their owning storage/API category. | Do not define blanket `VMS_*` aliases. High.                                                         |
| D. Namespaces                                                                      | 0                                                                                                            | New code → `BackstageVenueManager\`; Strategy 1                                     | Do not combine a full namespace conversion with the procedural rename.                               |
| E. Global variables                                                                | 44: 35 $GLOBALS slots, 4 direct globals, 5 loader temporaries                                                | `bvmgr_*`; Strategy 1                                                               | Atomic readers/writers update. Medium.                                                               |
| F. Custom hooks                                                                    | 182: 152 filters, 23 literal actions, 4 task actions, 1 dynamic family, 2 dormant filters                    | `bvmgr_*`; Strategy 5                                                               | Dual-fire old names; internal listeners bind only canonical names. High.                             |
| G. Options/site options                                                            | 115 static + 2 dynamic option families; 0 plugin site options                                                | `bvmgr_*`; Strategies 3/4. Retain dangerous historical schema markers under 6.      | Per-site, resumable copy-before-cutover. Critical for schema markers.                                |
| H. Post meta                                                                       | 610 static plugin-owned keys + 3 open dynamic families                                                       | `_bvmgr_*`; Strategies 3/4/6 by use                                                 | Query/join/relationship keys may remain physical legacy storage. Critical.                           |
| I. User meta                                                                       | 24 static + 9 dynamic families                                                                               | `_bvmgr_*`/`bvmgr_*`; Strategies 3/4/6                                              | Usermeta is network-global on multisite. High.                                                       |
| J. Transients                                                                      | 8 static + 26 dynamic families; 0 site transients                                                            | `bvmgr_*`; Strategy 1 for disposable data, 3 for locks/jobs                         | Preserve in-flight jobs through maximum TTL. Medium.                                                 |
| K. Tables                                                                          | 40 physical plugin table suffixes                                                                            | Canonical `BVMGR_*` accessors returning legacy table names; Strategy 6              | Do not physically rename. Critical.                                                                  |
| L. Cron hooks                                                                      | 23: 22 active + 1 cleanup-only                                                                               | `bvmgr_*`; Strategies 4/5                                                           | Schedule canonical first, listen to both, then clear legacy recurring events. High.                  |
| M. Action Scheduler                                                                | 3 hooks + 2 active groups                                                                                    | `bvmgr_*` hooks and `backstage-venue-manager-*` groups; Strategies 3/5              | Let old queued rows drain; never rewrite AS tables directly. High.                                   |
| N. REST                                                                            | 1 namespace, 16 routes, 17 method-route registrations                                                        | `backstage-venue-manager/v1`; Strategy 5                                            | Register all 17 under both namespaces temporarily. High.                                             |
| O. AJAX                                                                            | 41 action values, 45 registrations; 4 include `nopriv`                                                       | `bvmgr_*`; Strategy 5                                                               | Dual-register the same callbacks; preserve auth/nonces/responses. High.                              |
| P. Shortcodes                                                                      | 17: 16 `vms_*` plus `event_ticket_button`                                                                    | Add `bvmgr_*`; Strategy 5                                                           | Retain old tags because they are stored in content. High.                                            |
| Q. Handles                                                                         | 66 handles, 99 registration sites, 27 concrete consumers                                                     | `bvmgr-*`; Strategy 2                                                               | Dependency-only legacy aliases for externally consumed handles. Medium–High.                         |
| R. CPT/taxonomy                                                                    | 15 CPTs, 3 taxonomies, 2 unregistered legacy CPT aliases; 651 further semantic uses                          | Canonical accessors, physical `vms_*` values retained; Strategy 6                   | Renaming storage changes post types, taxonomies, hooks, URLs, and add-ons. Critical.                 |
| S. Capabilities/roles                                                              | 15 capabilities; 3 static roles + 1 dynamic family                                                           | `bvmgr_*`; Strategy 4 with temporary dual authorization                             | Preserve old grants during transition. Critical lockout risk.                                        |
| T. Nonces                                                                          | 220 action families: 156 static + 64 dynamic; 73 custom request-field names                                  | Actions → `bvmgr_*`, fields → `_bvmgr_*`; Strategies 5/3                            | Generate canonical, temporarily verify/read both. Medium.                                            |
| U. Query/rewrite                                                                   | 14 query vars, 15 registrations, 7 rules, 4 rewrite tags, 27 consumers                                       | `bvmgr_*`; Strategy 5                                                               | Keep legacy inbound URLs; one guarded rewrite flush. High.                                           |
| V. CLI                                                                             | 3 command paths                                                                                              | `bvmgr`, `bvmgr square-ticket-mirror`, `bvmgr state-of-range`; Strategy 5           | Retain deprecated `vms` paths temporarily. Low–Medium.                                               |
| W. Email/header/protocol                                                           | 5 headers + `vms-admission:`                                                                                 | New product-qualified headers/protocol; Strategies 3/5/6                            | Permanently accept already-issued QR protocol values. High for QR/signatures.                        |
| X. Public extension APIs                                                           | 6 documented semantic families, 13 named PHP entry points/types                                              | `bvmgr_*` or namespaced interfaces; Strategies 1/8                                  | No legacy PHP wrappers in the public ZIP; coordinate add-ons or use a separate private bridge. High. |
| Y. Tests/tooling/assets                                                            | 195 identifier-bearing tests, 5 scripts, 147 docs, 150 root historical/tooling files, 27 shipped assets      | Current tests/tooling → canonical names; Strategies 1/7. Historical evidence → 6.   | Preserve intentional legacy fixtures and historical records. High test-drift risk.                   |

Additional Y declaration counts:

- Tests: 743 unique prefixed functions, 38 classes, 1 interface, 16 constants.
- Scripts: 1 function and 3 classes.
- Browser globals: 5 confirmed shipped `window.VMS_*` globals.
- Shipped asset inventory: 994 prefix occurrences across 27 files.

## PHP global-symbol architecture

A token/AST-assisted migration is feasible, but a textual replacement is not.

The symbol map must cover:

- All declarations and direct references
- 3,644 declared functions appearing as exact string literals
- 3,288 functions checked through `function_exists()`
- At least 710 unique direct-literal callback functions across 766 registrations
- Reflection and registry strings
- Twenty duplicate function-definition families
- Nine duplicate guarded constant families

All 4,519 shipped functions are technically externally callable, but the repository documents no stable `@api` subset. Thirteen entry points/types are explicitly plausible extension APIs.

The public WordPress.org package should contain zero legacy `vms_*` function wrappers. Such wrappers would still be prohibited global declarations. For the 13 extension APIs:

1. Update known add-ons to recognize canonical names before the core cutover.
2. If unknown private integrations require compatibility, provide a separately distributed private compatibility plugin.
3. Do not ship that bridge in the WordPress.org ZIP.
4. Do not mass-convert the current procedural system into namespaces during the same batch.

## Persistent-data architecture

Use a dedicated marker such as `bvmgr_prefix_migration_version`; do not use the public plugin version as the migration trigger.

- Ordinary options: copy legacy → canonical when canonical is absent; thereafter read canonical first and write canonical.
- Schema/backfill markers: retain physical legacy values where losing the marker could rerun destructive or expensive migrations.
- Post/user meta: migrate ordinary scalar settings lazily or in resumable batches. Retain physical keys used in queries, joins, relationships, or external integrations unless a separate schema migration is justified.
- Tables: retain all 40 physical names.
- Capabilities/roles: provision canonical names, migrate assignments per site, accept both during transition, and retain legacy grants until rollback support expires.
- Transients: let disposable values expire; bridge locks, rate limits, previews, and resumable jobs through their maximum TTL.
- Never delete legacy values in the first compatibility release.

For rollback-sensitive writes, either temporarily mirror safe writes to the legacy key or provide a tested canonical-to-legacy reverse projector. A database backup alone is not an adequate migration mechanism.

## Hooks and public contracts

- Filters: apply the canonical filter first, then pass its result through the deprecated legacy filter. Internal callbacks must register only on the canonical side.
- Actions: fire canonical, then deprecated legacy, with identical arguments.
- REST: register the same permission callbacks, schemas, and handlers under both namespaces.
- AJAX: bind the same handler to old and new action names; the four `nopriv` contracts require public-client regression tests.
- Shortcodes: retain old registrations indefinitely until a content audit proves they are unused.
- Handles: legacy aliases should depend on the canonical handle with no source, avoiding double loading.
- Nonces: accept legacy actions only for a bounded cache/nonce lifetime; new pages generate only canonical values.
- QR/protocol identifiers: accept old `vms-admission:` values indefinitely.
- `vms_square_nightly_sync` remains a cleanup-only historical identifier until its cleanup gate is retired.

Known add-on dependencies confirm the need for this compatibility layer:

- `vms-events-slider` consumes `vms_event_plan`.
- `vms-fill-dates` queries multiple `vms_*` CPT/taxonomy values.
- `vms-data-tools` calls core functions.
- `vms-express-bar` binds Event Plan hooks.
- `vms-refer-a-friend` depends on the `vms-admin` handle.

## Implementation batches and dependencies

`B1 guardrails/add-on preparation → B2 foundation symbols → B3 procedural symbols → B4 nonpersistent clients → B5 persistent keys → B6 schedules/storage → B7 contract cutover → B8 package audit`

1. **B1 — Inventory manifest and compatibility preparation**
   - Add the exact old→new semantic manifest and forbidden-global tests.
   - Add canonical hook/API aliases while legacy code still exists.
   - Update known add-ons to tolerate canonical contracts.
   - Define migration-state and rollback architecture.
2. **B2 — Bootstrap, classes, constants, registries**
   - Rename bootstrap constants, classes/interfaces, globals, and central registries.
   - Update every reference atomically.
   - Preserve storage/API string values according to the matrix.
3. **B3 — Procedural PHP functions**
   - Rename functions by dependency-connected subsystem.
   - Update cross-subsystem references globally within each slice.
   - Remove all shipped `vms_*` function declarations.
   - No public-package wrapper generation.
4. **B4 — Browser and nonpersistent identifiers**
   - JavaScript globals, handles, nonce producers/readers, query vars, rewrites, CLI.
   - Keep bounded aliases and old inbound URL support.
5. **B5 — Options, meta, capabilities, roles, transients**
   - Run per-site, resumable migrations using independent markers.
   - Copy/verify before canonical cutover.
   - Preserve rollback data.
6. **B6 — Cron, Action Scheduler, tables, CPTs/taxonomies**
   - Migrate scheduling names without stranding work.
   - Retain table and CPT/taxonomy physical identifiers.
   - Verify network activation and interruption recovery.
7. **B7 — Public contracts**
   - Move producers to canonical hooks, REST, AJAX, shortcodes, headers, and protocols.
   - Deprecate rather than immediately remove legacy contracts.
   - Complete coordinated add-on verification.
8. **B8 — Tests, tooling, residual audit, release verification**
   - Normalize current tests and release tooling.
   - Preserve historical/legacy fixtures.
   - Run the full package lifecycle and Plugin Check gates.

## Required tests by batch

- **Before B2/B3:** token-based symbol manifest, callback-callability audit, duplicate-definition audit, full PHP lint, standalone and supported-stack boot.
- **Before B4:** PHP-localization/JavaScript consumer pairing, `node --check`, handle dependency graph, nonce fallback, old/new query URL and CLI tests.
- **Before B5:** seeded old-install fixtures for all option/meta/capability families; new-write, interruption, retry, rollback, per-site and network tests.
- **Before B6:** cron timestamp/args preservation, old queued jobs, recurring deduplication, Action Scheduler draining, table/schema invariance, activation/deactivation/reactivation.
- **Before B7:** hook argument/filter-order tests, REST schema/auth parity, AJAX response/auth parity, stored shortcode content, add-on contract tests, QR/header compatibility.
- **B8:** full focused suite, PHP lint, JavaScript syntax, semantic forbidden-global scan, reason-coded residual allowlist, clean ZIP build, upgrade/interruption/uninstall lifecycle, multisite, strict packaged Plugin Check, and no new/unmapped findings.

The residual test must be semantic. A blanket grep for `vms_*` would incorrectly reject legitimate legacy storage, fixtures, basenames, and compatibility contracts.

## Release/version implications

Version `1.2.0` can remain the first WordPress.org version because nothing has been uploaded. Do not change it during Phase B.

Private installations already identifying as `1.2.0` cannot rely on version comparison to trigger migration. All durable work must therefore run from independent idempotent migration markers during boot/activation until complete.

If `1.2.0` is ever distributed publicly before Phase B finishes, a later prefix release would require a normal version increment.

## Risk register

| RiskLevelFailure mode and affected statePreventionRecovery |          |                                                     |                                                                 |                                                                |
| ---------------------------------------------------------- | -------- | --------------------------------------------------- | --------------------------------------------------------------- | -------------------------------------------------------------- |
| PHP symbol migration                                       | Critical | Fatal calls, missing callbacks, broken add-ons      | Token map, callback-resolution gate, dependency-cluster tests   | Restore prior code; coordinated add-on fallback/private bridge |
| Post/user meta and options                                 | Critical | Silent data loss, broken queries/relationships      | Copy-verify-cutover, resumable markers, seeded fixtures         | Keep legacy data; reverse projection or DB restore             |
| Tables/CPTs/taxonomies                                     | Critical | Orphaned records and broken integrations            | Strategy 6; assert physical names unchanged                     | Revert accessors; no physical data movement                    |
| Capabilities/roles                                         | Critical | Administrator/staff lockout                         | Dual authorization, per-site assignment tests                   | Retain/regrant legacy caps and roles                           |
| Cron/Action Scheduler                                      | High     | Stranded or duplicated work                         | Schedule-new-before-clear, dual listeners, timestamp/args tests | Re-enable legacy listeners and schedules                       |
| Hooks/REST/AJAX/shortcodes                                 | High     | Silent third-party/client breakage                  | Dual contracts and response/argument parity tests               | Keep legacy registrations active                               |
| JS globals/handles/nonces                                  | High     | Immediate browser failures or rejected cached forms | Producer-consumer tests and bounded aliases                     | Restore aliases/fallback verification                          |
| Same-version private upgrade                               | High     | Migration never runs                                | Independent migration marker                                    | Repeat idempotent migration                                    |
| Tests/tooling                                              | Medium   | False confidence or broken packaging                | Update source-extraction tests with each slice                  | Revert tooling independently                                   |

## Estimated implementation scope

- Public package baseline: 374 files, including 271 PHP files.
- Complete mirror global-symbol map: 4,761 unique PHP globals/types/constants/shared slots.
- Likely inspection/change surface:
  - Up to 271 packaged PHP files
  - 27 shipped asset files
  - 195 test files
  - 5 release/tooling scripts
  - Approximately 450–500 active files overall
- Minimum durable migrations:
  - Options
  - Post/user meta
  - Capabilities/roles
  - Cron/Action Scheduler
  - Rewrite state
- Public-package PHP compatibility wrappers: **0**
- Potential private compatibility bridge: 13 public entry points/types.
- WordPress compatibility registrations potentially retained:
  - 182 custom hooks
  - 23 cron hooks
  - 3 Action Scheduler hooks and 2 groups
  - 17 REST registrations
  - 45 AJAX registrations
  - 16 legacy-prefixed shortcodes
  - Up to 66 handles
  - 220 nonce-action families
  - 14 query vars
  - 3 CLI paths
  - 6 protocol/header identifiers

## Explicitly do not rename

- `Backstage Venue Manager`, `backstage-venue-manager`, and the text domain.
- `backstage-venue-manager.php`.
- Phase A basename compatibility literals, `vendor-management-system.php`, and `vms.php`.
- `vms-build.txt` during prefix batches.
- The live `/vms/` Plugin URI while it remains the verified public route.
- All 40 physical database table names.
- Existing CPT/taxonomy values and the two legacy CPT aliases.
- Stored legacy shortcode tags.
- Previously issued `vms-admission:` QR payloads.
- Cleanup-only `vms_square_nightly_sync`.
- Existing external SKU/protocol values and operational `VMS-managed` terminology where changing them alters persisted or integration behavior.
- WordPress, WooCommerce, TEC/Event Tickets, Square, Turnstile, and other third-party hooks, options, meta, tables, handles, roles, and capabilities.
- CSS/body selectors derived from retained CPTs, taxonomies, or admin page slugs.
- Historical remediation records, raw scan evidence, and legacy-upgrade fixtures.
- Release-excluded Safety code until that prototype is separately reactivated or explicitly brought into the public package.

**PHASE B DESIGN COMPLETE — SAFE TO IMPLEMENT IN CONTROLLED BATCHES**
