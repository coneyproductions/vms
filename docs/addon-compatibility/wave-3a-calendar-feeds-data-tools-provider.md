# Wave 3A — Calendar Feeds Navigation and Data Tools Provider Decoupling

Date: 2026-09-06  
Scope: local source/architecture only

## Decision

- Backstage Calendar Feeds `0.1.4`: **READY FOR CONTROLLED LOCAL PROMOTION**.
- VMS Data Tools `0.5.55`: **READY FOR CONTROLLED LOCAL PROMOTION**.
- BVM `1.2.0` reporting-provider integration: **READY** and synchronized between this mirror and the canonical active local BVM tree. This synchronization changed source only; it did not change activation or the normal-local database.
- Installed Calendar Feeds remains `0.1.3`; installed Data Tools remains `0.5.54`. Neither candidate was promoted.

## A. Preflight

The required `scripts/codex-preflight.sh` passed before task work on branch `work/unreleased-2026-06-18`, HEAD `79da784f5bfcc66bd058c0a7f54e08d7b15bb5d5`, with local-ref upstream relation `0/0` and an empty index. The accepted dirty P0/Waves 1–2C/unrelated baseline was retained. The protected stash remained `stash@{0}` at `d08e726804712dc233f0e37b217abd6389963863`, named `WPORG-16D preserve unrelated sidebar+doc work`.

The normal `active_plugins` value remained 34 entries and its emitted-JSON SHA-256 remained `27115e8644f764f4be9403d7aa0c356c2b98a5543a1ef2fe079b0231d7fff98c`. Accepted starting source manifests included Calendar Feeds `0.1.3` at `c732bb48971360e892fd17834d4a1b018a1de27e58777635a12164e58eb5e1e8`, Data Tools `0.5.54` at `7f997b79f675b2534188d44f750afb4bd4eec9d03db9f75b6020b0afe02e9039`, Weather `0.1.12` at `e019619ce1bd5bef851dbdb4573fd527332983cda34004bd5fc531ae50cc5c0b`, Commerce `0.2.13` at `03bcb58b402f22048f63a9ee0876f06dda929d9af00923ef7550e60d7c8cc3f8`, Intake `0.2.4` at `1cf481a7834076b61c838a3e44d87db8d0efba8cf04793e4509e59a8dfa7e670`, Router `0.1.3` at `a1a2bcbd2f1f000eac07376a42eacbdc57092ec53577f1cd938b7e26d15fad7f`, Bridge `0.2.2` at `075878dc3628d5f6f26ce96e51ea328c0ce040ddf2b7036e3c18136029d979b3`, and Sponsorships `0.1.28` at `a1ec835bc577d86955ea010789319a9edd638e1d79bdb731e837a6276a9e47f5`.

## B. Calendar Feeds current architecture

Installed `0.1.3` requires its classes from its entry file, boots once on `plugins_loaded`, and registers `init`, `query_vars`, `template_redirect`, `admin_menu`, and `admin_post_bcf_regenerate_secret`. Its administrator capability and page slug are `manage_options` and `backstage`. It always registered a standalone top-level `Backstage` page and same-slug submenu; it had no BVM detection or BVM registry integration.

The public feed is the unchanged rewrite `^backstage-calendar-feeds/([A-Za-z0-9_-]{43})\.ics$` using query variable `bcf_feed_token`. The profile ID is `dalene-band-availability`. A 32-byte random, base64url capability token is hash-validated in constant time and encrypted with Sodium in option `bcf_feed_profile_secrets`. Rotation retains the `manage_options` and `bcf_regenerate_secret` nonce gates. Invalid tokens return `404`; an unavailable profile/provider returns `503`; valid results remain private RFC 5545 output.

The only calendar dependency is the explicit DRM Calendar Intake router contract: router contract version `2`, query callback `drm_ci_query_router_records`, source-discovery contract version `1`, and `drm_ci_query_router_sources`. Missing or incompatible provider functions fail closed. Calendar Feeds has no Event Plan integration and no legacy VMS runtime prerequisite. Its other persistent state is bounded publication history `bcf_publication_ledger_v1` with transient lock option `bcf_publication_ledger_lock_v1`. Activation creates the first secret and flushes rewrite rules; deactivation only flushes rewrite rules and preserves configuration/secrets.

## C. Canonical BVM extension mechanism

The supported mechanism is `bvmgr_register_admin_page()` during `vms_admin_register_pages`, with BVM's `vms-dashboard` rail/registry and `bvmgr_admin_ui_render_shell()`. No obsolete `vms_addons_manifest_entries` or `vms_event_plan_after_modules` compatibility was introduced.

Calendar Feeds uses a late `admin_menu` fallback only when registry registration did not succeed. Legacy-VMS-only therefore follows the same safe standalone path as BVM-absent operation; legacy VMS is neither detected nor required.

## D. Calendar Feeds exact source change and version

The clean candidate is `companion-plugins/backstage-calendar-feeds`, 17 files, normalized SHA-256 `20f2607ec61c62f2d7120cd99b657b46e15bc169f6d92d14f7951540e33fc20e`. The material behavior change requires successor version `0.1.4`.

Only three installed-source paths differ from `0.1.3`:

- `backstage-calendar-feeds.php`: header and `BCF_VERSION` become `0.1.4`.
- `README.md`: successor version/navigation notes.
- `includes/class-plugin.php`: canonical registry registration, single-registration state, late standalone fallback, and optional BVM shell rendering.

The BVM entry has ID `backstage-calendar-feeds`, stable direct slug `backstage`, capability `manage_options`, section `events_schedule`, order `50`, source `backstage-calendar-feeds`, and top-nav/directory/shell/register flags. The direct URL remains `admin.php?page=backstage`. The 12 feed/token/DRM/storage implementation paths are byte-identical to installed `0.1.3`.

## E. Calendar Feeds tests

- Candidate unit suite: `PASS: 109 isolated assertions`.
- Navigation/source regression: `PASS` for BVM-first, add-on-first, BVM absent, legacy-only, one registry row, no duplicate top-level menu, direct slug/capability, shell and standalone rendering, unchanged route/token/provider/storage source.
- Expanded disposable runtime: BVM-first, add-on-first, and standalone/provider-absent Calendar scenarios all pass, as do both full-coexistence orders.
- DRM Intake unavailable behavior is fail-safe; compatible Intake behavior is proven in the disposable WordPress runtime. No external HTTP is part of the feed tests.

## F. Calendar Feeds promotion and rollback

Controlled promotion must be separately authorized. Before it, rerun preflight; record installed/candidate tree hashes, `active_plugins`, the three BCF options, rewrite state, and the current feed URL without exposing its token; retain an exact `0.1.3` source backup outside the web root. Run the contained candidate precheck, then atomically replace the same `backstage-calendar-feeds` basename without a deactivate/reactivate cycle. Under the containment guard, verify version `0.1.4`, one BVM registry entry, no duplicate top-level menu, the direct admin URL, provider health, unchanged option bytes/token, unchanged activation state, and valid/invalid feed behavior; then rerun focused and expanded matrices.

Rollback is an atomic same-basename restoration of the exact `0.1.3` tree. Preserve `bcf_feed_profile_secrets`, `bcf_publication_ledger_v1`, and its lock semantics; do not regenerate a token or reset saved state. Flush rewrite rules only if route acceptance proves it necessary, restore only verified incidental lifecycle state, and repeat the guarded acceptance receipt.

## G–H. Complete BVM/Data Tools coupling inventory

| BVM consumption | Prior mechanism | Classification | Wave 3A result |
| --- | --- | --- | --- |
| Event Command Center event ticket model | Direct `vms_dt_reporting_build_event_model()` call | `INTERNAL IMPLEMENTATION BEING REACHED` / `NO CURRENT CONTRACT` | Narrow BVM provider resolver |
| Vendor Portal reporting loader | `require_once` of `WP_PLUGIN_DIR/vms-data-tools/includes/admin/page-revenue-intelligence.php` and `page-reporting-module.php` when files existed | `INTERNAL IMPLEMENTATION BEING REACHED`; filesystem presence incorrectly acted as activation authority | Removed completely |
| Website ticket details | Direct Data Tools reporting helper | `INTERNAL IMPLEMENTATION BEING REACHED` / `NO CURRENT CONTRACT` | Owned inside active Data Tools provider |
| Square line evidence | Direct Data Tools reporting helper | `INTERNAL IMPLEMENTATION BEING REACHED` / `NO CURRENT CONTRACT` | Owned inside active Data Tools provider |
| Website/Square ticket-source rollup | Direct Data Tools reporting helper | `INTERNAL IMPLEMENTATION BEING REACHED` / `NO CURRENT CONTRACT` | Owned inside active Data Tools provider |
| Woo ticket revenue/cache/admissions fallback | BVM-owned ticket report and existing cached projections | `PUBLIC BVM CONTRACT`; legitimate fallback | Retained, not duplicated |
| Data Tools menu renderer/capability | Guarded optional `vms_dt_render_tools_home` integration in BVM admin UI | Public compatibility/UI surface, not business-provider loading | Retained unchanged |
| Data Tools page/context/catalog metadata | Static `vms-data-tools` slug entries in admin context, navigation, and registry catalogs | BVM-owned UI metadata; no Data Tools source execution | Retained unchanged |
| Data Tools lifecycle/sensitive-page telemetry | Static recognized plugin basename and admin-page scope in runtime guards | BVM-owned observation/guard metadata; no activation authority or include | Retained unchanged |

No BVM reads of Data Tools tables were found. The exact filesystem defect was confined to Vendor Portal's direct loading of the two Data Tools admin/reporting files; the subsequent four Data Tools reporting calls were direct internal coupling. The remaining BVM matches are the guarded UI renderer/capability, static page/basename telemetry and catalog metadata, or comments describing optional reporting. Final searches find no `WP_PLUGIN_DIR`/`VMS_DT_ADMIN_DIR` Data Tools path and no direct `vms_dt_reporting_*` call in BVM provider consumers.

## I. Provider/API contract

BVM contract version `1` accepts registered providers with a sanitized unique ID, provider version, matching contract version, `event_ticket_sales` capability, priority, label, and callable. Duplicate IDs are rejected. Providers sort deterministically by priority then ID. `bvmgr_reporting_resolve_event_ticket_sales($event_plan_id, $context)` invokes only registered active providers and never loads provider source.

Results normalize availability/calculation state; paid/free/total quantities; revenue/headcount; website/door/Square subdivisions; countable-data, freshness, warnings, and errors; and provider/source/version/contract provenance. A calculated all-zero result is valid. `WP_Error`, invalid return types, explicit unavailability, and thrown exceptions are isolated and recorded in `provider_attempts`; absence produces a safe empty result.

## J. BVM source changes

- `includes/core/reporting-providers.php`: new provider registry, normalizer, resolver, provenance, deterministic priority, duplicate rejection, and failure isolation.
- `includes/core/load.php`: loads the contract before consumers.
- `includes/admin/event-command-center.php`: resolves `event_command_center` through the provider and then retains the existing BVM core ticket-revenue fallback.
- `includes/portal/vendor-portal.php`: removes direct external-plugin file loading and internal calls, resolves `vendor_portal`, maps the normalized result to its existing snapshot, and retains existing BVM-safe fallback behavior.

These four files are byte-identical in the repository mirror and `../../backstage-venue-manager`. Their hashes are core load `aa344226ff63799140ea5e2fd152c0b41c262075a29cc4829fb529afb711dc78`, provider contract `6ce14e7d600abd982e8ca3a0ea004b426e68fdd51ac1b35404bfffb3a8e2a686`, Event Command Center `c56accb8ba26b4757c93b867f454ef4b7bbc791a846eeb4264c8817d47ea57b5`, and Vendor Portal `e88051421dd0fbf6bbe30f9bf0822ab4f9b51b0b871ba3392a5caa4d0836403e`. The complete canonical active BVM tree is 384 files at normalized SHA-256 `268e1e956bf8b92990cda5b82f7debee4352032a2815ad71a3f16e45a609c54f`. BVM remains the unreleased `1.2.0`; no release metadata change was warranted for this source branch.

## K. Data Tools source and version

The candidate is `companion-plugins/vms-data-tools`, 81 files, normalized SHA-256 `8e36b9b6cf1e337627a86a782f2eafc49681d09b8711665e54ab78d6b005988b`. It is successor `0.5.55`; installed `0.5.54` remains 78 files at its unchanged hash.

Changed/new candidate paths are `vms-data-tools.php`, `vms-build.txt`, `includes/bootstrap.php`, `includes/integrations/bvm-reporting-provider.php`, `docs/BUILD-NOTES-0.5.55.md`, and `docs/TEST-PLAN-0.5.55.md`. When Data Tools itself is active, its integration registers provider `vms-data-tools` at priority `20` immediately if BVM is available and again safely at `plugins_loaded` priority `20`; static and BVM duplicate guards ensure exactly one registration across either load order. The callback lazily loads only Data Tools-owned reporting dependencies, and only after the active plugin registered and BVM invoked the provider.

The `event_command_center` scope preserves Data Tools event-model paid/free/total/revenue and diagnostics. The `vendor_portal` scope preserves event-date filtering, Website detail rows, Square full-day evidence, ticket-source rollup, paid/free/headcount/revenue, and warning/error behavior.

Data Tools controlled promotion must also be separately authorized. Before it, rerun preflight; record candidate/installed hashes, `active_plugins`, Data Tools options, owned table schemas/row manifests, cron hooks, and capabilities; verify the `0.5.55` source adds no activation/schema migration; and retain an exact `0.5.54` tree outside the web root. Run a contained current-shape precheck, atomically replace the same `vms-data-tools` basename without deactivation/reactivation, then use the guard to verify version `0.5.55`, exactly one provider in both resolved load-order models, ECC and Vendor Portal provenance/results, unchanged activation, and no option/table/cron/capability delta. Rerun focused and both full matrices.

Rollback restores the exact `0.5.54` tree atomically at the same basename. No schema rollback is expected because this candidate adds no schema change; restore database/lifecycle state only if the before/after receipt identifies an incidental task-owned delta. Confirm the provider is absent after rollback to `0.5.54`, BVM Woo/core fallback is active, activation is unchanged, and repeat the guarded suites.

The BVM integration needs no further local activation promotion: its four source files are already synchronized under the repository's mirror/active-tree rule. A future BVM release must include all four together through the normal BVM release workflow, never only a consumer without the core contract. BVM rollback requires exact restoration of the pre-Wave-3A versions of those same four paths in both mirror and active tree, followed by byte-parity, P0, provider-absent fallback, Vendor Portal, and both compatibility matrices. Do not leave the mirror and active tree on different contract states.

## L–P. Provider matrix and consumer results

- Active Data Tools candidate with BVM first: pass; one provider, exact provenance, valid data, valid calculated empty data, Vendor Portal Website/Square result, and isolated exception.
- Active Data Tools candidate with Data Tools first: pass with the same outcomes and exactly one later registration.
- Inactive but files present: pass; no Data Tools constant, bootstrap/init/provider function, hook, cron/action registration, or implementation PHP appears; BVM returns its safe unavailable result and uses its core fallback where supported.
- Data Tools directory physically absent: pass in a dedicated disposable scenario; no path assumption or fatal.
- ECC/full Command Center: active provider retains Data Tools truth and observable provenance; unavailable provider retains the BVM Woo/core ticket revenue path.
- Event Plan ticket summary: accepted P0 ticket report/resolver behavior and Event Plan scoping remain covered by P0/ticketing regressions; no Data Tools internal is called from the Event Plan path.
- Vendor Portal: active provider returns the merged Website/Square snapshot; absent/failing provider remains safe and preserves the existing fallback/empty presentation contract.
- Square/POS: Website rows after the event date remain excluded, Square uses `full_day`, paid/free door quantities and revenue are retained, and errors/warnings remain observable. Tests use synthetic rows only and no Square or other external request.

The permanent source-boundary test additionally asserts that installed Data Tools remains `0.5.54`, installed Calendar remains `0.1.3`, and inactive-but-present Data Tools cannot execute merely because its directory exists.

## Q. Legacy-tree test debt

Classification: **STALE TEST**. `tests/ticketing-claims-repository-sql-remediation.php` hardcoded `../../vms/includes/integrations/ticketing-claims-framework.php`, even though the accepted required function exists in the mirror and canonical active BVM while the intentionally inactive legacy tree lacks it. The test now targets `../../backstage-venue-manager`; runtime source was not degraded to satisfy the legacy assertion. The corrected test passes.

The older `wave2b-source-authority.php` is separately historical and not a Wave 3A failure: it intentionally asserts that active Bridge is the pre-Wave-2C `0.2.1` Git worktree. After the accepted Wave 2C same-basename `0.2.2` promotion, immutable Bridge history is supplied to current harnesses through `BVM_COMPAT_DRM_BRIDGE_REPO`.

## R. Combined compatibility result

Official-five evidence: **PASS 19/19** at `/var/folders/33/ltvj2kb927dcmnpdb1x8qd0h0000gn/T/bvm-addon-compat-report.Klttfp/`. This is the prior 18-case matrix plus physical Data Tools directory absence.

Additional ecosystem evidence: **PASS 52/52** at `/var/folders/33/ltvj2kb927dcmnpdb1x8qd0h0000gn/T/bvm-addon-compat-report.X6Wj0k/`. This is the prior 49-case matrix plus Calendar BVM-first, Calendar add-on-first, and Calendar standalone/provider-absent. Both full-coexistence orders pass with the Calendar and Data Tools candidates.

Two pre-final harness outcomes were test-infrastructure findings, not product failures. The first additional invocation stopped before fixture setup because the post-Wave-2C active Bridge directory is correctly a release tree rather than the former Git worktree; the rerun supplied the preserved immutable object store. A subsequent 51/52 run exposed one probe that assumed Calendar provider health was always an array even though missing Intake correctly returns `WP_Error`; the probe was corrected to test the supported health outcome, containment stayed clean, and the final exact-source rerun passed 52/52.

Focused passes include Calendar `109` assertions; Calendar navigation; BVM provider valid/priority/duplicate/empty/error/exception/absent cases; Data Tools both-order/provider/vendor/Square/failure cases; inactive source decoupling and Woo/refund/add-on fallback; Vendor Portal paid-basis; P0 source consistency; staffing repository/final/matrix; ticketing core and corrected claims SQL; Commerce business contracts; Weather Command Center and provider/persistence contracts; Intake source discovery and venue registry; Router policy/candidate/storage; and Bridge legacy-admin/provider probes. PHP lint passed for 83 task/candidate files and shell syntax passed for both harnesses.

## S. Files changed by Wave 3A

- Candidate trees: `companion-plugins/backstage-calendar-feeds/` and `companion-plugins/vms-data-tools/`.
- BVM runtime: the four paths listed in section J, synchronized to canonical active BVM.
- Focused tests: `tests/calendar-feeds-bvm-navigation.php`, `tests/reporting-provider-contract.php`, `tests/data-tools-reporting-provider.php`, `tests/data-tools-provider-decoupling.php`, `tests/vendor-portal-bonus-progress-paid-basis.php`, and `tests/ticketing-claims-repository-sql-remediation.php`.
- Runtime matrices: both compatibility shell drivers and their official/additional contract, probe, source-manifest, and report support files.
- Documentation: this report, the runtime-harness guide, and the remediation ledger append-only entry.

No legacy `../../vms`, installed Calendar Feeds, or installed Data Tools file was changed.

## T–U. Normal-local state and containment

Normal-local database and activation changes: **NONE**. Candidate mutations ran only in uniquely named disposable databases/runtimes. Each final matrix reports database cleanup, runtime cleanup, blocked external HTTP before transport, an independently launched Local process blocked for the bounded run, residue removal asserted, unchanged normal cron/Weather/database state, guard cleanup, and unchanged normal active plugins. The expanded run additionally asserts unchanged Bridge forensic worktree. No disposable database, runtime, MU guard, or lock residue remained.

The containment helper, forced-failure self-test, and MU guard remained on their accepted source; Wave 3A changed only the scenario staging/probes needed for the two candidates and the explicit absent-directory case.

## V. Accepted-state preservation

Final accepted manifests remain Weather `e019619ce1bd5bef851dbdb4573fd527332983cda34004bd5fc531ae50cc5c0b`, Commerce `03bcb58b402f22048f63a9ee0876f06dda929d9af00923ef7550e60d7c8cc3f8`, Intake `1cf481a7834076b61c838a3e44d87db8d0efba8cf04793e4509e59a8dfa7e670`, Router `a1a2bcbd2f1f000eac07376a42eacbdc57092ec53577f1cd938b7e26d15fad7f`, Bridge `075878dc3628d5f6f26ce96e51ea328c0ce040ddf2b7036e3c18136029d979b3`, and Sponsorships `a1ec835bc577d86955ea010789319a9edd638e1d79bdb731e837a6276a9e47f5`.

Unchanged BVM P0 hashes are profitability `e6f2f6989b7d7d4174d1a5f3c93719c75fafa80f627322aa93e830d81135d55a`, staffing `2c1a90986e409f1f5f44c52d724993b6be89f4b514b68daaed90185b6c7a45e7`, Event Plans `1c505140b509c5f53954beb06a8d40d0774173931305e3fd058463a24ccc5500`, Pass Claims `ebcf893380bea92a14ffb40de3606140eab8b4033eb8eb9b89430c10d992d436`, ticket resolver `be4273c6ad822d8a48185f84a78d9471f0e6b78e640e86e2ea24b4c890f31b9f`, ticket revenue `d8298e39112230ac2348ec466e4967fccf27e66e4417d09b3c58e2f2c028e035`, and P0 test `320433e7654077615c02ecbf3c467e7e93407ffb161458d63bf47604f03d234c`. Event Command Center changed intentionally for the provider consumer and is now `c56accb8ba26b4757c93b867f454ef4b7bbc791a846eeb4264c8817d47ea57b5` in both mirror and active BVM.

## W–X. Git and unrelated-work proof

At closeout the branch, HEAD, upstream relation, and empty index remain unchanged. The empty staged-diff SHA-256 is `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`; tracked binary diff SHA-256 is `df789a59e229f040f2117d0c83d3df0a353252f16d98fb8f876bba5ba929fe7a`; full porcelain status SHA-256 is `b8a9561e2d8e875ed17af9a24e2d9c5daab692cbfaa2dbfa6c01921b49d96cd5`. The worktree remains deliberately dirty with the accepted pre-existing inventory plus the narrow Wave 3A paths; the final preflight's only warning is that expected dirty state. No commit was created. `git diff --check` passes, and the Wave 3A diff was inspected by component and against installed/canonical counterparts.

The protected stash remains at the same object ID/name and was not applied, popped, dropped, rewritten, or recreated. No unrelated accepted dirty file, legacy/dormant source directory, rollback artifact, or concurrent user state was cleaned up or rewritten.

## Y. Boundary proof

This work was local only. There was no staging or production access, SSH, remote WP-CLI, real external HTTP, payment/business action, communication, deployment, upload, normal-local plugin activation/deactivation, Calendar/Data Tools promotion, package/ZIP/tag/release, WordPress.org/reviewer action, commit, push, fetch, pull, `ls-remote`, checkout, reset, rebase, or stash operation.

## Z. Recommended next task

Authorize one controlled local promotion at a time, starting with Calendar Feeds `0.1.4` because it is source-only and has no upgrade routine. Capture its guarded acceptance/rollback receipt before separately authorizing the Data Tools `0.5.55` source replacement. For Data Tools, first verify that `0.5.55` adds no schema/activation migration, preserve an exact `0.5.54` source backup and relevant options/tables/cron/capabilities, then use the same-basename atomic replacement and both-load-order provider acceptance. BVM rollback, if needed before either promotion, is an exact restoration of its four pre-Wave-3A files in both mirror and active tree followed by the P0/provider/compatibility suites; do not partially revert only one tree.
