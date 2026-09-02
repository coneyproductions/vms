# WordPress.org Prefix Migration — Phase B1

Date: 2026-08-26

## Authority and scope

`docs/WPORG_PREFIX_MIGRATION_B0.md` is the controlling architecture. It records the approved canonical family `bvmgr_`, `BVMGR_`, `BackstageVenueManager\`, `bvmgr-`, `backstage-venue-manager/v1`, and `X-Backstage-Venue-Manager-*` after reviewer clarification that prefixes must contain at least four characters.

This checkpoint implements inventory, compatibility preparation, and migration guardrails only. It does not rename runtime symbols, migrate persistent data, edit add-ons, load migration infrastructure in production, create a private bridge, or begin B2-B8.

## Authoritative machine manifest

- Manifest: `docs/wporg-prefix-migration-manifest.json`
- Immutable ratchet allowance set: `docs/wporg-prefix-prohibited-global-baseline.json`
- Deterministic generator: `scripts/generate-wporg-prefix-manifest.php`
- Semantic inventory library: `scripts/lib/wporg-prefix-inventory.php`
- Scope: `271` public-package PHP files; release-excluded tests, scripts, documentation, historical evidence, fixtures, and `includes/safety/` are not treated as public runtime declarations.
- Categories: all `25` B0 categories A-Y. Each category carries the current identifier/family, canonical target, B0 strategy, compatibility class, persistence or external-contract status, planned batch, explicit retention decision, and risk.
- Semantic PHP inventory at the historical B1 checkpoint: `4,521` functions at `4,541` declaration sites, `23` classes, `1` interface, `107` constants at `116` definition sites, no namespaces/traits/enums, and `44` global slots at `232` sites. B2.5 later corrected this incomplete global inventory to `85` current canonical slots at `426` token sites while preserving the original B1/B2 map and ratchet as historical evidence.
- Ratchet baseline: `4,696` B1-era prohibited short-prefix PHP declarations/slots. Later removals are allowed; new names are not.
- Public extension surface: exactly `13` entry points/types in six families.
- Known add-ons: exactly `5` contract maps.

The manifest is generated from PHP tokens, not raw grep output. `--check` compares a fresh deterministic render to the committed JSON and fails on drift.

### B0 evidence refinements

B1's token scanner correctly skips PHP's ampersand token before a by-reference function name. This found two real public-runtime functions that the B0 probe missed:

- `vms_event_plan_runtime_redirect_targets`
- `vms_module_registry`

The function baseline is therefore `4,521` unique / `4,541` declarations rather than the B0 probe's `4,519` / `4,539`. Both functions remain ordinary B3 Strategy 1 work, create no new public-extension family, and do not alter the B0 architecture or risk classification. The B0 document remains unchanged as authoritative historical design evidence; the correction is machine-recorded in `b0_evidence_corrections`.

B1 also widened the dynamic scanner beyond locally declared function names so guarded optional or removed contracts cannot disappear from later inventories. The authoritative baseline now contains `3,310` function-existence identifiers at `6,338` sites, `711` direct literal callback identifiers at `767` sites, one reflection identifier, and `3,613` combined function-resolution requirements. Exactly `20` of those requirements are optional/external or dynamic map-only contracts with no current core declaration. This is a safety-map refinement inside the already approved dynamic-reference strategy, not a new compatibility class.

### B1 add-on dependency-map correction

Before B2 implementation, a compatibility stop found that the original B1 add-on map had not compared the five add-on trees against every B2-owned class, interface, constant, and request-global symbol. The add-ons were therefore rescanned read-only with PHP tokens. The corrected scanner ignores comments and partial prose, records direct identifiers, and records exact string literals used by dynamic APIs such as `defined()` and `class_exists()`.

The complete result is exactly seven dependencies across three add-ons:

- `vms-events-slider`: `VMS_CALENDAR_FEED_CACHE_BUST_OPTION` → `BVMGR_CALENDAR_FEED_CACHE_BUST_OPTION`
- `vms-data-tools`: `VMS_Vendor_Schema_Registry` → `BVMGR_Vendor_Schema_Registry`
- `vms-data-tools`: `VMS_VENDOR_PRIMARY_USER_META_KEY` → `BVMGR_VENDOR_PRIMARY_USER_META_KEY`
- `vms-data-tools`: `VMS_USER_PRIMARY_VENDOR_META_KEY` → `BVMGR_USER_PRIMARY_VENDOR_META_KEY`
- `vms-data-tools`: `VMS_VENUE_CPT` → `BVMGR_VENUE_CPT`
- `vms-express-bar`: `VMS_PLUGIN_FILE` → `BVMGR_PLUGIN_FILE`
- `vms-express-bar`: `VMS_VERSION` → `BVMGR_VERSION`

No B2-owned dependency was found in `vms-fill-dates` or `vms-refer-a-friend`, and no B2 interface or request-global dependency was found in any of the five add-ons. This correction changes the B2 coordination gate, not the B0 architecture: the three affected add-ons require isolated symbol-only cutovers before the core rename, and the public core package still receives no blanket legacy PHP aliases.

## Guardrails

`tests/wporg-prefix-manifest-guardrails.php` enforces:

- all 25 B0 categories and required policy fields;
- exact semantic and dynamic baselines;
- manifest generation staleness;
- the exact 4,696-entry forbidden-global ratchet;
- semantic rejection of a new 2/3-letter plugin-owned PHP declaration while accepting retained storage, hook, fixture, and compatibility strings;
- current-or-canonical resolution for locally owned dynamic function contracts;
- the exact 20-entry optional/dynamic map-only baseline;
- duplicate guarded function and constant family baselines;
- canonical-target uniqueness and absence of existing core collisions;
- the 13-entry public API list, the five-add-on list, and compatibility-map policies;
- the exact seven-entry B2 add-on dependency map and a semantic rescan of all five available installed add-ons;
- the release exclusion of all B1 documentation, scripts, and tests.

The guard is a required default public-release precondition. The separate B1 allowance file is sorted, unique, and its creation mode refuses to overwrite it. A future intentional migration batch updates the current deterministic manifest and reviews its semantic diff while the original allowance set remains fixed; removals pass, but a newly introduced prohibited name fails even after ordinary manifest regeneration.

## Migration-state architecture

`scripts/lib/wporg-prefix-migration-state.php` is a release-excluded, production-inert reference implementation for later persistence batches.

- Independent version marker: `bvmgr_prefix_migration_version`
- Retry journal: `bvmgr_prefix_migration_state`
- Versioning: independent of plugin version `1.2.0`; a new target version resets older completed-step IDs, while an older target can never downgrade a newer marker.
- Idempotency: completed step IDs and the final marker suppress already verified work.
- Interruption: status, current step, attempts, cursor, and error class are checkpointed; retries resume the incomplete step.
- Per-site/multisite: every site runs an isolated state machine; a network runner de-duplicates site IDs and never uses a network-wide completion marker to mask an incomplete site.
- Cutover: copy legacy to absent canonical storage, verify the copy, and retain the legacy value.
- Rollback: canonical-first reads/writes; legacy fallback remains; write mirroring is opt-in only for explicitly rollback-safe values.

No WordPress option, meta, table, role, capability, cron, or other persistent value is read or changed by B1.

## Known add-on contracts

The exact identifiers and evidence files are frozen under `known_addons` in the manifest. All five installed sibling trees were inspected read-only and have no planned canonical PHP symbol collision.

| Add-on | B2 PHP symbols | Core functions | Hooks | Retained physical identifiers | Handles | B1 preparation | Later dependency |
| --- | ---: | ---: | ---: | ---: | ---: | --- | --- |
| `vms-events-slider` | 1 | 9 | 1 | 1 | 0 | token-scanned exact consumers frozen; source unchanged | coordinated B2 constant; B3 functions; B7 hooks |
| `vms-fill-dates` | 0 | 18 | 4 | 4 | 0 | token-scanned exact consumers frozen; source unchanged | coordinated B3 functions; B7 hooks; physical values remain under Strategy 6 |
| `vms-data-tools` | 4 | 33 | 5 | 4 | 0 | token-scanned exact consumers frozen; source unchanged | coordinated B2 class/constants; B3 functions; B7 hooks; physical values remain under Strategy 6 |
| `vms-express-bar` | 2 | 2 | 3 | 1 | 0 | token-scanned exact consumers frozen; source unchanged | coordinated B2 bootstrap constants; B3 functions; B7 hooks; generated CPT hooks remain legacy because the CPT is retained |
| `vms-refer-a-friend` | 0 | 3 | 1 | 0 | 1 | token-scanned exact consumers frozen; source unchanged | coordinated `vms_register_admin_page` cutover in B3; dependency-only handle alias in B4; hook transition in B7 |

No add-on tree was edited. The read-only live-tree boundary remains intact.

## Public extension API map

The manifest freezes these 13 plausible external PHP contracts and their canonical equivalents:

1. `vms_register_admin_page` → `bvmgr_register_admin_page`
2. `vms_register_tour` → `bvmgr_register_tour`
3. `vms_get_registered_tours` → `bvmgr_get_registered_tours`
4. `vms_get_tour_registry` → `bvmgr_get_tour_registry`
5. `vms_sch_is_venue_open_on_date` → `bvmgr_sch_is_venue_open_on_date`
6. `vms_sch_season_generate_active_dates` → `bvmgr_sch_season_generate_active_dates`
7. `vms_docs_sources` → `bvmgr_docs_sources`
8. `vms_docs_index` → `bvmgr_docs_index`
9. `VMS_Social_Provider_Interface` → `BVMGR_Social_Provider_Interface`
10. `vms_social_get_providers` → `bvmgr_social_get_providers`
11. `vms_social_get_provider` → `bvmgr_social_get_provider`
12. `vms_notify_get_providers` → `bvmgr_notify_get_providers`
13. `vms_notify_user` → `bvmgr_notify_user`

All require coordinated cutover. The only proven known-add-on consumer of this 13-entry list is `vms-refer-a-friend` consuming `vms_register_admin_page`. No legacy PHP wrapper may ship in the public package. A separately distributed private bridge remains only a conditional contract for a proven unknown private integration; B1 does not create it.

## Release and scanner boundary

Every B1 implementation file is under `docs/`, `scripts/`, or `tests/`, all of which are excluded by `release-public-excludes.txt`. The public-package PHP inventory remains exactly `271` files and no packaged runtime file, release identity file, or durable Plugin Check state file changed. Consequently the last scanner-verified reference remains `125` known nonblocking findings, `0` unmapped findings, and `0` submission blockers; B1 adds no Plugin Check-visible code. No ZIP or package was created for this checkpoint.

## B2 handoff

B2 may implement only its authorized class/interface, constant, and request-global batch using this manifest and the controlling B0 strategies. Before core cutover it must prepare isolated copies or worktrees for the three affected add-ons and update exactly the seven mapped consumers to their canonical symbols. It must preserve the physical-storage and external-contract retention decisions, including the retained `vms_calendar_feed_cache_bust`, `_vms_vendor_user_id`, `_vms_vendor_id`, and `vms_venue` values, update the manifest through the generator, and keep the dynamic-resolution and collision tests green. B3 procedural migration, all persistence batches, public hook/HTTP transitions, tooling residual cleanup, and any private bridge remain outside B2 unless separately authorized.

Phase B1 changes no B0 risk classification.

## B2.5 semantic-inventory correction

The B2.5 global-scope audit found `41` plugin-owned semantic slots omitted by this original inventory: `38` variables in the globally executed vendor-profile template and three loader variables. They account for `194` token sites and `57` Plugin Check rows; the template loop variable `$tag` is a real semantic slot that Plugin Check did not report. The correction is additive at `docs/wporg-prefix-migration-manifest.json#/completed_batches/B2_5` and does not rewrite the original `175`-entry B2 map or its immutable `4,696 -> 4,521` historical ratchet.

The complete semantic ledger is therefore `4,737` prohibited slots before B2, `4,562` after the original B2 map, and `4,521` after B2.5. Those figures count unique semantic identifiers/slots. They are intentionally separate from Plugin Check row counts. The current deterministic inventory contains `85` canonical global slots at `426` token sites: the original `44 / 232` B2 set plus the additive `41 / 194` B2.5 set.

The companion scanner inventory is `docs/wporg-prefix-scanner-inventory.json`. It records every packaged prefix row individually and applies a migration-aware gate: historical residuals remain immutable, mapped B3/B7 findings may reduce monotonically, method-scope and external-contract exceptions remain exact, and any unexpected, unmapped, category-increasing, or completed-batch residual fails. See `docs/WPORG_PREFIX_MIGRATION_B2_5.md` for the authoritative correction and fresh-scan evidence.
