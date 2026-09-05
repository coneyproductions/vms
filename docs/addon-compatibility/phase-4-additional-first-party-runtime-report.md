# BVM Add-on Compatibility Phase 4 Report

## Baseline and scope

- Branch: `work/unreleased-2026-06-18`
- Starting HEAD: `d7a2a20fc2a8704e6dc21f47fb38c0af284a7ebe`
- Starting worktree: clean
- Protected stash: `WPORG-16D preserve unrelated sidebar+doc work` present at `stash@{0}`
- Phase 3 JSON SHA-256: `3b9d63787c2048771491b82664e74c38d1f9c4bed9b782294ac79f8fb44218d2`
- Phase 3 text SHA-256: `979ae95aa5687fd7666c77bcd6a6330adbadbe615856b47a3ce562cacd8da587`
- Runtime: WordPress `7.1`, BVM `1.2.0`, PHP `8.3.33`

Phase 4 changed only harness, test, documentation, and evidence files in this repository. It did not edit BVM production runtime, the original five add-ons, any additional integration source, either DRM repository, the sibling live BVM tree, or the normal Local database/activation state.

## Authoritative inventory

Paths below are relative to the normal Local site's `wp-content/plugins` directory. “Installed” describes the read-only normal-site inventory; it does not mean Phase 4 changed activation state.

| Candidate | Frozen source | Version | Installed state / alternates | Declared requirements | Direct relationship |
| --- | --- | ---: | --- | --- | --- |
| DRM Calendar Intake | `drm-calendar-intake/drm-calendar-intake.php` at clean Git `590f2ac40e346a5a1aae384a9e90b37d72b4cf80` | 0.2.4 | Active; authoritative standalone Git repository | WP 6.4, PHP 7.4; no plugin header dependency | Administrative presence check only: tests for BVM's `vms_event_plan` CPT |
| DRM Events Bridge | `drm-events-bridge/drm-events-bridge.php` at Git `7300161eba1bd053061192e0000812801b1aa4d2` | 0.2.1 | Active, but authoritative repository was behind three commits and had tracked modifications/deletions plus untracked tests | WP 6.8, PHP 8.3 | Direct, guarded legacy/future VMS mapping; **BLOCKED — concurrent source movement** |
| VMS Commerce Discounts | `vms-commerce-discounts-0.2.11.zip` | 0.2.11 | Installed directory 0.2.4 inactive; active temporary directory reports 0.2.9; archive history 0.2.1–0.2.10 excluded | No header requirements; runtime requires WooCommerce and, due current eager subclass declaration, WooCommerce Square 5.2.0 | BVM data-contract only (`vms_event_plan` and `_vms_*` commerce linkage) |
| VMS Investor Portal | `vms-investor-portal/vms-investor-portal.php` | 0.2.2 | Active; matching production/staging candidate ZIPs and older `vms-investor-portal 2.zip` excluded | No header requirements | Optional BVM registry, reporting API, CPT/meta, and menu integration; standalone fallback |
| VMS Meta Ads Builder | `vms-meta-ads/vms-meta-ads.php` | 0.1.106 | Active; installed source is newer than nearby 0.1.105 artifacts | No header requirements | Required BVM module registry, event/venue APIs, CPT/meta, menus, and hooks |
| VMS Ops Console Premium | `vms-ops-console-premium/vms-ops-console-premium.php` | 0.1.66 | Active; installed source selected | WP 6.0, PHP 7.4 | Optional BVM administrative/data integration; standalone PWA/private-club functions remain useful |
| VMS Safety Toolkit Pro | `vms-safety-pro/vms-safety-pro.php` | 0.1.0 | Inactive; directory name is `vms-safety-pro`, not the display-name-derived candidate slug | No header requirements | Required extension of BVM Safety functions, hooks, capability, and `vms_doc` CPT |
| VMS Season Passes | `vms-season-passes/vms-season-passes.php` | 0.1.0 | Active; installed source selected | WP 6.8, PHP 8.3; runtime BVM minimum 1.1.0 | Required BVM module/admin registry and admissions/event contracts; WooCommerce and Ops optional |
| VMS Sponsorships | `vms-sponsorships/vms-sponsorships.php` | 0.1.27 | Active; matching 0.1.27 RC and older archives excluded | No header requirements | Optional BVM registry/event-plan integration; standalone menu fallback; TEC optional |
| VMS Checkout Policies | `vmsx-checkout-policies/vmsx-checkout-policies.php` | 0.1.8 | Active; installed source selected | Declares WooCommerce through `Requires Plugins` | Optional BVM Settings/ticket-meta integration; WooCommerce fallback menu without BVM |
| VMSX Weather Risk | `VMS WEATHER RISK ZIP ARCHIVES/vmsx-weather-risk-0.1.12-title-cleanup.zip` | 0.1.12 | Active installed directory is stale 0.1.3; versioned archives 0.1.1–0.1.12 and root 0.1.0 ZIP excluded | WP 6.0, PHP 7.4; runtime BVM minimum 0.2.24.505 | Required BVM module/admin/event/venue integration; Data Tools optional |

All eleven are independently installable WordPress plugin directories or archives. “Independently installable” does not mean useful without their declared/runtime dependencies.

The latest monotonic versioned archives were selected for Commerce Discounts and Weather Risk to avoid testing the demonstrably stale active directories. Every staged tree and entry file has a deterministic SHA-256 in the canonical JSON source manifest.

## Direct, blocked, and indirect boundaries

The principal runtime matrix contains ten frozen direct integrations. DRM Events Bridge remains a direct candidate but was not staged because its authoritative Git tree could not be frozen without crossing the concurrency boundary.

These discoveries stay outside the direct matrix:

- DRM Event Router: produces `vms_projection` data but neither detects nor invokes BVM runtime.
- Backstage Calendar Feeds 0.1.3: consumes DRM Calendar Intake provider contracts; no BVM function, constant, identity, hook, or data-structure dependency was found.
- Event Venue Map Modal 1.2.4: integrates with The Events Calendar presentation only; no BVM runtime contract was found.

## Contract inventory

The machine-readable definition is `tests/addon-compatibility/additional-runtime-contracts.php`. Required BVM APIs are separated from guarded compatibility fallbacks and optional companion APIs so missing third-party features are not mislabeled as BVM failures.

| Integration | BVM contracts captured |
| --- | --- |
| DRM Calendar Intake | `vms_event_plan` CPT presence check; three quarantine-admin submenus; private options; automatic-sync cron callback |
| Commerce Discounts | `vms_event_plan`, product/event linkage meta, Woo submenu/returned hook, two AJAX contracts; no BVM PHP API call |
| Investor Portal | 18 required BVM reporting/admin/event functions, `VMS_PLUGIN_PATH`, `vms_admin_register_pages`, `vms_event_plan`/meta, investor table/option; five guarded Data Tools/Ops companion functions |
| Meta Ads | Seven required BVM functions, BVM path/URL/version constants, four guarded capability constants, module/docs/tour hooks, five BVM submenus, `vms-ma/v1`, AJAX, cron, event/venue meta and module tables/options |
| Ops Console Premium | Eight BVM functions, one guarded venue-template constant, add-on-manifest hook, BVM member menu integration, `vms-ops/v1`, presence cron, BVM event/venue meta and Ops tables/options |
| Safety Toolkit Pro | Three Safety functions, `VMS_CAP_SAFETY_TEMPLATES`, `vms_doc`, Safety tabs/render hooks; no independent menu |
| Season Passes | Eight required BVM module/admin/admission/event functions, BVM version check, admin registry/menu, event meta and three tables; ten guarded optional Ops functions |
| Sponsorships | Seven current BVM admin/event functions, four guarded legacy registry functions, admin/event-plan hooks, seven registry pages, event-plan meta and sponsorship tables/options |
| Checkout Policies | Two guarded ticket-meta APIs, BVM version/Settings detection, BVM Settings section, Woo fallback page, product meta and option |
| Weather Risk | Five required BVM module/admin/event/venue functions, BVM path/version constants, five BVM hooks, two submenus, AJAX/cron and event/venue meta; four guarded Data Tools functions |
| DRM Events Bridge (blocked) | Static snapshot showed three BVM functions, `vms_event_plan`/`vms_vendor`, and `_vms_event_plan_status`; no runtime conclusion was drawn |

No additional plugin directly includes or requires a file inside the BVM installation tree. Continued `vms_*` and `VMS_*` identifiers are current contracts, not debt by themselves.

## Dependency expectations

- Calendar Intake: remains a functional quarantine/intake plugin without BVM; its local CPT check controls an advisory notice only.
- Commerce Discounts: remains disabled with one native notice without WooCommerce. Its current 0.2.11 code also requires WooCommerce Square to be loaded before the Square bridge subclass can be declared, despite no header dependency.
- Investor Portal and Sponsorships: remain useful standalone and intentionally create top-level fallback menus without BVM.
- Meta Ads, Season Passes, and Weather Risk: fail closed without BVM, show their native dependency notice, and create no dependent menu.
- Ops Console Premium: remains useful standalone; BVM enriches its admin/data paths.
- Safety Toolkit Pro: its hooks remain inert without BVM Safety and it emits no bootstrap dependency notice.
- Checkout Policies: requires WooCommerce. With WooCommerce but no BVM it uses a Woo submenu; with BVM it integrates into BVM Settings and creates no duplicate menu.
- WooCommerce, WooCommerce Square, TEC, Event Tickets, Event Tickets Plus, the original five, and optional companions were staged only in scenarios that required them.

## Harness architecture

`BVM_COMPAT_SUITE` now explicitly selects `official_five` (the unchanged default) or `additional_first_party`. The additional suite delegates to a separate orchestration file but reuses the Phase 3 preload/isolation design.

Each additional run:

1. hashes the normal Local `active_plugins` option and `wp-config.php`;
2. verifies DRM Calendar Intake is clean and stable while copying, and refuses to stage DRM Events Bridge;
3. copies WordPress to a temporary tree and stages BVM only as `backstage-venue-manager/vendor-management-system.php` using the same public-package exclusions as Phase 3;
4. rejects the historical VMS bootstrap and nonexistent alternate BVM bootstraps;
5. creates a unique guarded `bvm_compat_*` database, installs schemas there, blocks external HTTP, and suppresses only disposable onboarding redirects;
6. runs each scenario in a fresh WP-CLI process through real plugin, `plugins_loaded`, `init`, admin, REST, AJAX-registration, cron-registration, menu, notice, and enqueue lifecycles;
7. never dispatches email, payments, advertisements, remote sync, weather lookup, webhook, or business mutation callbacks;
8. removes the disposable database/tree and verifies the normal-site hashes again.

The probe does not fabricate venue, event, order, investor, sponsor, passholder, safety, or campaign records. Those deeper flows are explicitly outside this registration-level milestone.

## Runtime compatibility matrix

| Plugin | Version | BVM Detection | APIs | Menu/UI | Notices | BVM-Absent | Load Order | Overall |
| --- | ---: | --- | --- | --- | --- | --- | --- | --- |
| DRM Calendar Intake | 0.2.4 | PASS | PASS | PASS | PASS | PASS | PASS | **PASS — BVM-only runtime compatible** |
| VMS Commerce Discounts | 0.2.11 | PASS | PASS | PASS | PASS | PASS | PASS | **PARTIAL — BVM compatible; missing WooCommerce Square fatals during activation** |
| VMS Investor Portal | 0.2.2 | PASS | PASS | PASS | PASS | PASS | PASS | **PASS — BVM-only runtime compatible** |
| VMS Meta Ads Builder | 0.1.106 | PASS | PASS | PASS | PASS | PASS | PASS | **PASS — BVM-only runtime compatible** |
| VMS Ops Console Premium | 0.1.66 | PASS | PASS | PASS | PASS | PASS | PASS | **PASS — BVM-only runtime compatible** |
| VMS Safety Toolkit Pro | 0.1.0 | PASS | FAIL | PASS | PASS | PASS | FAIL | **FAIL — effectively incompatible with BVM-only core** |
| VMS Season Passes | 0.1.0 | PASS | PASS | PASS | PASS | PASS | PASS | **PASS — BVM-only runtime compatible** |
| VMS Sponsorships | 0.1.27 | PASS | PASS | PASS | PASS | PASS | PASS | **PASS — BVM-only runtime compatible** |
| VMS Checkout Policies | 0.1.8 | PASS | PASS | PASS | PASS | PASS | PASS | **PASS — BVM-only runtime compatible** |
| VMSX Weather Risk | 0.1.12 | PASS | PASS | PASS | PASS | PASS | PASS | **PASS — BVM-only runtime compatible** |
| DRM Events Bridge | 0.2.1 | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED | **BLOCKED — concurrent source movement** |

The report's overall result is `FAIL` by design because a compatibility regression harness must fail while either reproduced functional defect remains.

## Detailed findings

### DRM Calendar Intake 0.2.4

Both BVM load orders and the no-BVM state passed. The plugin registered its private CPT, three intended submenus, automatic-sync callback, and no operational network call. With BVM, no false notice appeared; without BVM, one warning said that VMS did not appear active. This is an advisory administrative presence check, not a required local dependency.

Classification: **PASS**. The “VMS” notice wording is branding/documentation debt only.

### VMS Commerce Discounts 0.2.11

With WooCommerce 11.0.1, WooCommerce Square 5.2.0, TEC, Event Tickets, and Event Tickets Plus, both BVM load orders and the BVM-absent scenario passed. The Woo menu appeared once, assets used WordPress's returned hook, BVM-shaped data contracts remained available, and no BVM detection was attempted or required. Missing WooCommerce failed closed with its native notice. Missing Square during an ordinary already-active runtime also failed closed with the plugin's initialization notice.

Activation without Square is different: `includes/class-vms-discounts-square-bridge.php:295` declares `VMS_Discounts_Square_Order_Request extends WooCommerce\Square\Gateway\API\Requests\Orders` unconditionally. The activation process exited `255` with a class-not-found fatal before a native dependency notice could register. This is a functional add-on dependency defect, not a BVM recognition defect.

Classification: **PARTIAL**. Owner: Commerce Discounts. Proposed regression: activation with WooCommerce active and Square absent must complete without a fatal and leave a clear inactive/compatibility state.

### VMS Investor Portal 0.2.2

Both BVM orders passed with one registry-owned `vms-investor-portal` entry. Without BVM, one intended top-level fallback appeared. Required BVM APIs/constants, shortcode bootstrap, table setup, and notices passed. Optional Data Tools/Ops enrichments were not treated as BVM requirements.

Classification: **PASS**; business reporting with real investor/event data was not exercised.

### VMS Meta Ads Builder 0.1.106

Both BVM orders passed the module gate, five BVM submenus, REST namespace, AJAX, cron callback, hooks, and required APIs. Guarded historical capability constants correctly fell back when absent. Without BVM, the plugin failed closed, created no menu, and emitted its native module-system warning. External Meta calls and campaigns were never dispatched.

Classification: **PASS**; campaign creation and remote API behavior were not exercised.

### VMS Ops Console Premium 0.1.66

Both BVM orders passed its required API, manifest, BVM menu, REST, cron, and data registration checks. The guarded venue-template constant fallback worked. The plugin also loaded without BVM as its standalone PWA/private-club architecture intends. No REST endpoint, alert, presence operation, or scanner workflow was dispatched.

Classification: **PASS**; operational scanner/member workflows were not exercised.

### VMS Safety Toolkit Pro 0.1.0

The plugin bootstrap itself did not fatal in either order, but the public BVM package deliberately excludes and does not bootstrap its parked `includes/safety/` prototype. Consequently all three consumed Safety functions were absent, `VMS_CAP_SAFETY_TEMPLATES` was absent, and `vms_doc` was unregistered. The add-on's callbacks attach to hooks that public BVM never emits, no Safety menu exists, and no bootstrap notice explains the unsupported dependency. The feature is therefore unusable with the supported public BVM-only core.

Classification: **FAIL — effectively incompatible with BVM-only core**. Severity: medium (feature entirely unavailable, no broader site fatal). Owner: BVM release architecture and/or Safety add-on dependency model. Proposed regression: either the supported core must expose the full Safety contract, or the add-on must detect its absence and fail closed with a native notice; Phase 4 does not choose or implement that architecture.

### VMS Season Passes 0.1.0

Both BVM orders, BVM-absent, and BVM-present/WooCommerce-absent cases passed. The module/admin registry, admissions functions, menu, schema registration, and native no-BVM notice behaved as declared. Optional Ops scanner contracts were separated from the BVM requirement.

Classification: **PASS**; passholder, order, email, and scan workflows were not exercised.

### VMS Sponsorships 0.1.27

Both BVM orders produced exactly seven registry-owned pages with no top-level duplicate. Without BVM, the standalone top-level/child menu set appeared. TEC absence did not disable administrative behavior or create a false notice. Current BVM functions and guarded legacy registry fallbacks behaved correctly.

Classification: **PASS**; applications, uploads, notifications, assignments, and public rendering were not exercised.

### VMS Checkout Policies 0.1.8

Both BVM orders integrated settings without a duplicate menu. With WooCommerce and no BVM, one Woo fallback page appeared; without WooCommerce, no fallback page appeared. Guarded ticket-meta functions were available with BVM. No cart, order, payment, or customer record was created.

Classification: **PASS**; checkout acknowledgement workflow was not exercised.

### VMSX Weather Risk 0.1.12

Both BVM orders passed the minimum-version/module gate, two BVM submenus, hooks, AJAX/cron registration, and event/venue data contracts. Without BVM, it created no menu and showed its native warning. Optional Data Tools functions were separated. External weather providers and event synchronization were never called.

Classification: **PASS**; forecasting, geocoding, and operational advisory workflows were not exercised.

### DRM Events Bridge 0.2.1

No runtime classification is made. Its authoritative Git source was concurrently dirty and behind its remote, so staging it would have produced ambiguous evidence. Static inspection only is preserved in the contract/blocker record.

Classification: **BLOCKED — concurrent source movement**.

## Supported coexistence

Two supported coexistence orders passed with BVM, the original five, all nine compatible frozen additional integrations, WooCommerce, WooCommerce Square, TEC, Event Tickets, and Event Tickets Plus. Safety Pro was excluded after its individual contract failed; DRM Events Bridge was excluded because blocked.

The supported set produced no fatal, database error, owned warning/notice, `doing_it_wrong` event, duplicate tested menu, false dependency notice, REST namespace failure, AJAX registration failure, or cron callback registration failure. This was a registration/runtime-contract test, not a business-workflow test.

## DRM architecture finding

Calendar Intake's authoritative README/code boundary stops at private intake, reconciliation, and human-review quarantine. It does not create or modify BVM Event Plans. Its only local BVM integration is the `vms_event_plan` presence check used by an advisory notice; the plugin remains useful when BVM is not locally active. A warning that “VMS does not appear active” is therefore not proof that DaleneRichelle.com should run BVM.

Events Bridge now treats DRM Event Router as authoritative for its principal provider path. The dirty snapshot retains guarded VMS status/meta mapping for legacy or future enrichment, but available source did not establish locally active BVM as a required principal dependency. Its final relationship remains an architecture question until the source is frozen and the owning workflow confirms intent.

## Finding classification

### Functional defects

1. Safety Toolkit Pro 0.1.0 is unusable with the public BVM 1.2.0 package because the complete Safety contract is absent.
2. Commerce Discounts 0.2.11 activation fatals when WooCommerce is active but WooCommerce Square is absent. Its valid BVM runtime is compatible once the implicit Square prerequisite is supplied.

### Hardening opportunities

- Commerce Discounts should make Square integration lazy/guarded and declare or explain its dependency; this is corrective work for the reproduced activation defect, not Phase 4 scope.
- Meta Ads' guarded capability-constant fallbacks, Ops' guarded venue-template fallback, Sponsorships' legacy registry guards, and optional companion guards all passed and require no compatibility change.

### Branding/documentation debt

- Calendar Intake, Meta Ads, Season Passes, Weather Risk, and other plugins retain intentional/historical “VMS” wording. The wording did not cause BVM misdetection and is not a functional defect.
- Calendar Intake's warning should eventually document that BVM is downstream/optional locally if that architecture is confirmed by the owning workflow.

### Architecture questions

- Whether Safety should return to the public BVM package or Safety Pro should be retired/reframed cannot be decided by the compatibility audit.
- DRM Events Bridge's retained VMS enrichment intent requires a stable source snapshot and workflow-owner confirmation.

### Test gaps

The registration-level audit did not exercise real campaigns, payments, checkout, orders, passholders, scans, sponsor applications/assets, investor calculations, safety records/files, email, webhooks, external synchronization, or weather APIs. Those require separately authorized disposable business fixtures and are not implied by runtime-contract PASS.

## Evidence and validation

- Expanded scenarios: `38` total; `35` pass and `3` intentionally failing defect scenarios (two Safety load orders and Commerce activation without Square).
- Supported coexistence: two orders, both pass.
- Database cleanup: PASS in both canonical runs.
- Runtime-tree cleanup: PASS in both canonical runs.
- External HTTP: blocked.
- Normal active-plugin SHA-256 before/after: `24c5bdcdfcf8e0759ffcdefce52810291dc4389fb9ad8d69d5a106a9dbd02088`.
- Normal `wp-config.php`: unchanged in every run.
- Additional JSON SHA-256: `c27d8f7ba54de6274dd5d603c13c6322cc7fe9600cbe023c707becaa54109383`.
- Additional text SHA-256: `9c1ef8dc3a7358f6261db1564b197e62065878a1034f887cb0d81d680a1e9a34`.
- Repeatability: both normalized reports were byte-identical across two independently created/destroyed canonical runs.
- Original Phase 3 JSON/text hashes remained byte-identical after suite expansion.

The canonical report intentionally exits nonzero while the two functional defects remain. A nonzero audit result is expected evidence here, not an isolation/cleanup failure.

## Files

- `scripts/test-bvm-addon-runtime-compatibility.sh`
- `scripts/test-bvm-additional-runtime-compatibility.sh`
- `tests/addon-compatibility/additional-runtime-contracts.php`
- `tests/addon-compatibility/additional-runtime-contracts-test.php`
- `tests/addon-compatibility/additional-runtime-probe.php`
- `tests/addon-compatibility/additional-source-manifest.php`
- `tests/addon-compatibility/additional-build-report.php`
- `docs/addon-compatibility/bvm-only-runtime-harness.md`
- `docs/addon-compatibility/phase-4-additional-first-party-runtime-report.md`
- `test-results/bvm-additional-runtime-compatibility.report.json`
- `test-results/bvm-additional-runtime-compatibility.report.txt`

## Recommended remediation order

1. Keep the nine passing integrations and the original five functionally unchanged.
2. In a separately authorized add-on milestone, fix Commerce Discounts' activation-time Square class dependency and add the captured missing-Square activation regression.
3. Make an explicit product/architecture decision for Safety Toolkit Pro before changing either BVM or the add-on; then implement only the chosen contract with a focused regression.
4. Rerun DRM Events Bridge after its source is clean and frozen; do not infer a local BVM deployment requirement from old “VMS” wording.
5. Handle branding/notice wording separately from runtime compatibility.
6. Move to indirect interoperability testing for DRM Event Router and other data-contract companions after direct defects are resolved or consciously accepted.

No production remediation, push, package, deployment, WordPress.org action, reviewer reply, or normal-site mutation occurred in Phase 4.
