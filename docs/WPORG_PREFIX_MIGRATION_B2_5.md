# WordPress.org Prefix Migration — Phase B2.5

Date: 2026-08-26

## Status and decision

B2.5 is complete at implementation checkpoint `d1f9a0e441fc496793052132a6fe62b2b55a2337`. The chosen template strategy is **PREFIXED GLOBAL VARIABLES**. All `38` plugin-owned variables in the globally executed vendor-profile template and all three omitted loader variables were renamed to descriptive `bvmgr_` identifiers. No closure, immediately invoked function, or template-loader redesign was introduced, and no Event Plan partial variable was cosmetically renamed.

The fresh packaged scanner is fully reconciled. The exact verdict is `B2.5 COMPLETE — SCANNER MODEL RECONCILED — READY FOR B3`. This is a readiness handoff only; B3 still requires explicit authorization and was not started.

## Exact additive 41-slot map

The normative machine map, including every token site, is `docs/wporg-prefix-migration-manifest.json#/completed_batches/B2_5/symbol_map`. It is additive and deliberately separate from the immutable original B2 map.

| Scope | Legacy variable | Canonical variable | Token sites |
| --- | --- | --- | ---: |
| Loader | `$hook` | `$bvmgr_social_cron_hook` | 2 |
| Loader | `$pt` | `$bvmgr_vendor_application_post_type` | 6 |
| Loader | `$tax_file` | `$bvmgr_vendor_tax_profile_file` | 3 |
| Vendor template | `$attrs` | `$bvmgr_vendor_profile_social_icon_attributes` | 2 |
| Vendor template | `$city` | `$bvmgr_vendor_profile_city` | 7 |
| Vendor template | `$email` | `$bvmgr_vendor_profile_email` | 9 |
| Vendor template | `$gallery_images` | `$bvmgr_vendor_profile_gallery_images` | 4 |
| Vendor template | `$i` | `$bvmgr_vendor_profile_gallery_image_index` | 4 |
| Vendor template | `$image_url` | `$bvmgr_vendor_profile_gallery_image_url` | 3 |
| Vendor template | `$k_primary_email` | `$bvmgr_vendor_profile_primary_email_meta_key` | 2 |
| Vendor template | `$k_primary_phone` | `$bvmgr_vendor_profile_primary_phone_meta_key` | 2 |
| Vendor template | `$k_show_e` | `$bvmgr_vendor_profile_show_email_meta_key` | 2 |
| Vendor template | `$k_show_loc` | `$bvmgr_vendor_profile_show_location_meta_key` | 2 |
| Vendor template | `$k_show_p` | `$bvmgr_vendor_profile_show_phone_meta_key` | 2 |
| Vendor template | `$k_show_w` | `$bvmgr_vendor_profile_show_website_meta_key` | 2 |
| Vendor template | `$k_vendor_web` | `$bvmgr_vendor_profile_website_meta_key` | 2 |
| Vendor template | `$legacy_email_key` | `$bvmgr_vendor_profile_legacy_email_meta_key` | 2 |
| Vendor template | `$legacy_loc` | `$bvmgr_vendor_profile_legacy_location` | 3 |
| Vendor template | `$legacy_phone_key` | `$bvmgr_vendor_profile_legacy_phone_meta_key` | 2 |
| Vendor template | `$next_show_markup` | `$bvmgr_vendor_profile_next_show_markup` | 3 |
| Vendor template | `$parts` | `$bvmgr_vendor_profile_legacy_location_parts` | 7 |
| Vendor template | `$phone` | `$bvmgr_vendor_profile_phone` | 9 |
| Vendor template | `$profile_markup_allowed_html` | `$bvmgr_vendor_profile_allowed_html` | 21 |
| Vendor template | `$raw_show_e` | `$bvmgr_vendor_profile_raw_show_email` | 3 |
| Vendor template | `$raw_show_loc` | `$bvmgr_vendor_profile_raw_show_location` | 3 |
| Vendor template | `$raw_show_p` | `$bvmgr_vendor_profile_raw_show_phone` | 3 |
| Vendor template | `$raw_show_w` | `$bvmgr_vendor_profile_raw_show_website` | 3 |
| Vendor template | `$show_email` | `$bvmgr_vendor_profile_show_email` | 4 |
| Vendor template | `$show_loc` | `$bvmgr_vendor_profile_show_location` | 2 |
| Vendor template | `$show_phone` | `$bvmgr_vendor_profile_show_phone` | 4 |
| Vendor template | `$show_website` | `$bvmgr_vendor_profile_show_website` | 4 |
| Vendor template | `$social_icon_allowed_html` | `$bvmgr_vendor_profile_social_icon_allowed_html` | 2 |
| Vendor template | `$social_markup` | `$bvmgr_vendor_profile_social_markup` | 3 |
| Vendor template | `$state` | `$bvmgr_vendor_profile_state` | 7 |
| Vendor template | `$tag` | `$bvmgr_vendor_profile_social_icon_tag` | 5 |
| Vendor template | `$url` | `$bvmgr_vendor_profile_gallery_image_candidate_url` | 3 |
| Vendor template | `$vendor` | `$bvmgr_vendor_profile_post` | 5 |
| Vendor template | `$vendor_id` | `$bvmgr_vendor_profile_post_id` | 22 |
| Vendor template | `$video_embed` | `$bvmgr_vendor_profile_video_embed` | 7 |
| Vendor template | `$video_url` | `$bvmgr_vendor_profile_video_url` | 4 |
| Vendor template | `$website` | `$bvmgr_vendor_profile_website` | 9 |
| **Total** | **41 semantic slots** | **41 canonical slots** | **194** |

The vendor-template subset is `38 / 183`; the loader subset is `3 / 11`. Plugin Check had reported `52` vendor-template rows and five loader rows. `$tag` is the additional semantic slot its prefix sniff missed.

## Behavior boundary

Only variable identifiers and the release-excluded evidence/tooling changed. Template selection, execution order, HTML, CSS classes, URLs, request and stored keys, hook values, CPT/taxonomy values, cron values, and public data presentation are unchanged. The intentional `$GLOBALS['bvmgr_vendor_profile_post']` carrier remains the vendor identity bridge.

Focused runtime coverage exercises template selection, the full and missing-vendor renders, vendor identity/title, primary and legacy contact/location data, visibility branches, telephone/mail/web links, next-show and social markup, `the_content`, video embed/fallback, gallery, thumbnail/placeholder branches, allowed SVG/path markup, oEmbed width, postdata reset, and header/footer order. Loader coverage proves the same taxonomy file gate/path, three vendor-application post-type hook paths, social cron hook constant/fallback, and queue batch behavior.

## Complete semantic accounting

The original B1/B2 evidence remains immutable:

- Historical incomplete B1 prohibited surface: `4,696`.
- Historical original B2 result: `4,696 -> 4,521`, reduction `175`.
- Original B2 map: `175` symbols, including `44` globals at `232` token sites.

The corrected complete semantic ledger is:

- Complete pre-B2 prohibited surface: `4,737`.
- Complete state after the original B2 map: `4,562`.
- Complete state after B2.5: `4,521`.

The current semantic global inventory is `85` canonical slots at `426` token sites: original B2 `44 / 232` plus B2.5 `41 / 194`. These are unique semantic slots and token sites, not scanner rows.

An independent PHP-token audit now enumerates top-level assignments without relying on Plugin Check or the curated migration map. It proves all live vendor-template and loader assignments are mapped canonical globals. It also distinguishes the ten Event Plan partial families that inherit method scope. The only additional syntactic top-level candidate is `$path` in `includes/rest-dashboard.php`; it is deterministically unreachable because `includes/schedule/helpers.php` is required before the guarded fallback, so the guard can never enter. It is reason-coded as dead/non-runtime rather than silently counted as a live global.

## Scanner inventory and migration gate

The durable per-row artifact is `docs/wporg-prefix-scanner-inventory.json`, generated by `scripts/generate-wporg-prefix-scanner-inventory.php` through `scripts/lib/wporg-prefix-scanner-inventory.php`. Every prefix finding records its code, file, line, column, type, extracted identifier, stable finding ID/occurrence, semantic classification, owning batch, disposition, and evidence.

The only accepted categories are:

- `REQUIRED_MIGRATION_B3`: procedural function declarations.
- `REQUIRED_MIGRATION_B7`: plugin-owned literal and reviewed dynamic hook rows.
- `SCANNER_FALSE_POSITIVE_METHOD_SCOPE`: exact variable rows in ten Event Plan partials included from private `BVMGR_Admin_Event_Plans::render_event_plan_partial()` method scope.
- `THIRD_PARTY_OR_CORE_CONTRACT`: exact dependency/core hooks with recorded owners.

The method-scope family rule is token-proved against the controller and still maps every one of the `420` rows individually. The retained external hooks are `wootickets_attendee_insert_args`, `event_ticket_woo_attendee_created`, `event_tickets_woocommerce_ticket_created`, `event_tickets_woocommerce_tickets_generated_for_product`, `event_tickets_woocommerce_tickets_generated`, and WordPress core `the_content`; none was renamed or dual-fired.

The migration-aware gate separates the immutable historical `125` residuals from expected B3/B7 work and approved exceptions. It fails on a new/unmapped prefix row, a completed-batch residual, any category increase, or a historical-baseline change. Removal of mapped migration rows is monotonic progress. This replaces the misleading comparison of intermediate prefix rows against a pre-prefix `NEW_FINDING=0` baseline without weakening the final release requirement.

## Fresh exact package and strict scan

- Source commit: `d1f9a0e441fc496793052132a6fe62b2b55a2337`.
- ZIP: `/private/tmp/bvm-wporg-b2-5-final.74cU0p/build/backstage-venue-manager-1.2.0-public-release.zip`.
- ZIP SHA-256: `26f550399fb43ae8628a61f1f33d1ebb4fcfdd792ee7c05f975d44a0385c7156`.
- Strict JSON: `/private/tmp/bvm-wporg-b2-5-final.74cU0p/plugin-check/plugin-check.strict.json`.
- Strict JSON SHA-256: `e09ed3d9f43dc0b8553888300a8aa1f8185b59b2c31ec8f7fd97645aeddcad62`.
- Plugin Check stderr: `0` bytes.
- Scanner inventory SHA-256: `e9677f75b211bcb70a2636ae503e60a239c6d1cb80b1ea05952a26f0ac9e138c` at generation time.

Fresh strict counts are `125` errors plus `5,149` prefix warnings, `5,274` total, and seven rule codes:

| Finding class | Rows |
| --- | ---: |
| Historical residual (`OutputNotEscaped 123`, `OffloadedContent 1`, `NonEnqueuedStylesheet 1`) | 125 |
| `REQUIRED_MIGRATION_B3` | 4,541 |
| `REQUIRED_MIGRATION_B7` | 182 |
| `SCANNER_FALSE_POSITIVE_METHOD_SCOPE` | 420 |
| `THIRD_PARTY_OR_CORE_CONTRACT` | 6 |
| Unexpected prefix | 0 |
| Unmapped prefix | 0 |

All `57` genuine B2 omission rows are eliminated, the unreported `$tag` semantic global is eliminated, completed B2/B2.5 symbols remain scanner-zero, and the historical `125` normalized rows are exact. The remaining expected migration surface is `4,723`; approved scanner exceptions are `426`.

## Verification and boundaries

PHP lint, manifest generation/guardrails, B2 foundation, B2.5 runtime behavior, add-on contracts, migration state, plugin identity, runtime-stub guards, release compatibility self-tests, public-release pipeline tests, scanner inventory generation/check/gate/negative tests, and Git whitespace checks pass.

The clean public builder staged `374` files, linted `271` packaged PHP files, syntax-checked `55` JavaScript files, and passed package integrity. WordPress-dependent default builder tests were kept outside the standalone builder and covered by focused tests plus the disposable compatibility harness. Against the exact ZIP, all seven dependency states completed without fatal errors; standalone and Woo-only states passed cleanly, TEC-bearing states recorded dependency-era warnings/deprecations, clean activation/deactivation/reactivation, authenticated admin/public smoke, legacy-basename upgrade, interruption recovery, fixture preservation, and uninstall preservation all completed. The compatibility report is `/private/tmp/bvm-wporg-b2-5-final.74cU0p/compatibility/backstage-venue-manager-1.2.0-release-compatibility.report.json`, SHA-256 `4e2bbc976770b9846eac695e62dc8731dec7f707188735a9a4582c3e1fccbbb7`, overall `WARN` with no fatal.

Only the isolated release worktree changed. The primary development worktree, installed/live plugin, five installed add-ons, protected stash, staging, production, and WordPress.org state were not modified. No push, merge, upload, tag, deployment, reviewer reply, or B3 work occurred.

## Handoff

Verdict: `B2.5 COMPLETE — SCANNER MODEL RECONCILED — READY FOR B3`.

Stop and await explicit authorization before B3.
