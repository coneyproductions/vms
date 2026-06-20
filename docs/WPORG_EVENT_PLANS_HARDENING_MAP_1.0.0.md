# WordPress.org Event Plans Hardening Map 1.0.0

Date: 2026-06-19

## Scope

- Target file: `includes/cpt/event-plans.php`
- Scan source: `docs/plugin-check-1.0.0-raw.txt`
- Scan target: installed package built from `dist/wporg-04d/vms-1.0.0-public-release.zip`
- Current artifact SHA-256: `7987b619acec510e397677074eba3f0442a8511b2a5492112583fc5f7ea9e6f3`

## Current Findings

- `241` total findings
- `108` errors
- `133` warnings

Findings by Plugin Check category:

- nonce/input: `121`
- DB/SQL: `9`
- escaping: `14`
- other: `97`

Code-family breakdown:

- `83` `WordPress.WP.I18n.MissingTranslatorsComment`
- `60` `WordPress.Security.NonceVerification.Recommended`
- `27` `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`
- `25` `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`
- `14` `WordPress.Security.EscapeOutput.OutputNotEscaped`
- `6` `WordPress.Security.NonceVerification.Missing`
- `6` `WordPress.WP.I18n.UnorderedPlaceholdersText`
- `6` `WordPress.DB.SlowDBQuery.slow_db_query_meta_query`
- `4` `WordPress.WP.I18n.NonSingularStringLiteralText`
- `4` `WordPress.PHP.DevelopmentFunctions.error_log_error_log`
- `3` `WordPress.Security.ValidatedSanitizedInput.InputNotValidated`
- `1` `WordPress.DB.DirectDatabaseQuery.DirectQuery`
- `1` `WordPress.DB.DirectDatabaseQuery.NoCaching`
- `1` `WordPress.DB.PreparedSQL.NotPrepared`

## Business Surface Split

- `A. Low-risk admin display/list UI`: `21`
- `B. Medium-risk admin request helpers`: `8`
- `C. High-risk Event Plan save/publish path`: `93`
- `D. High-risk integrations`: `106`
- `E. Unclear / requires dedicated investigation`: `13`

## Top Functions

| Findings | Function | Line range | Business surface | Dominant issue types |
| ---: | --- | --- | --- | --- |
| `69` | `save_event_plan_meta()` | `8825-11301` | `C` | request unslash/sanitize, nonce/input, i18n |
| `56` | `render_event_plan_details_meta_box()` | `4660-5924` | `D` | admin render mixed with integration state, nonce/input, i18n |
| `25` | `render_cancellation_step_data()` | `3538-3756` | `D` | translators comments in cancellation UI |
| `13` | `vms_validate_event_plan()` | `11546-11761` | `C` | validation copy, placeholder ordering |
| `7` | `render_event_plan_ticketing_v2_host_meta_box()` | `616-630` | `A` | output escaping on captured partial output |
| `7` | `format_cancellation_notification_result_row()` | `3454-3536` | `D` | translators comments in cancellation result strings |
| `6` | `vms_integrity_scan_event_plans_for_missing_vendors()` | `13363-13620` | `E` | query shape plus list-view follow-on usage |
| `5` | `build_event_plan_readiness_summary_context()` | `2084-2173` | `A` | translators comments in read-only readiness labels |

## Exact Low-Risk Candidate Slices

### Completed in `WPORG-04D`

- `11304-11311` `vms_admin_event_plan_list_query_value()`
  - added an explicit PHPCS ignore on the centralized raw list-filter GET read
  - rationale: read-only admin-list helper, callers sanitize by expected type
- `11358-11385` `vms_admin_event_plan_list_add_include_drafts_toggle()`
  - wrapped the rendered help-button HTML in `wp_kses_post()`
  - rationale: final output escaping on a non-mutating admin list control

Batch result:

- `includes/cpt/event-plans.php`: `244` -> `241`
- expected business surface touched: `A` only

### Still low-risk, but deferred in this task

- `2084-2173` `build_event_plan_readiness_summary_context()`
  - mostly translators comments on read-only readiness labels
  - low behavioral risk, but broader text-touch than this protected slice
- `616-642` `render_event_plan_ticketing_v2_host_meta_box()` and `render_event_plan_advanced_controls_host_meta_box()`
  - output-escaping findings on captured partial output
  - still admin render only, but requires careful validation that intentionally-rendered HTML is preserved
- `2245-2348` `is_event_plan_admin_section_requested()` plus `ajax_get_venue_comp_defaults()`
  - read-only request handling
  - safer than save paths, but the AJAX handler still deserves explicit regression coverage before changes beyond the list toggle

## Exact High-Risk Slices To Defer

- `4660-5924` `render_event_plan_details_meta_box()`
  - mixed Event Plan editor rendering with ticketing, vendor, and integration state
- `8825-11301` `save_event_plan_meta()`
  - central save path; broad nonce/input cluster
- `11546-11761` `vms_validate_event_plan()`
  - publish-readiness validation and operator messaging tied to save/status behavior
- `11961-12171` live refund request handlers
  - refund and cancellation side effects
- `13914-15550` legacy ticket cleanup, calendar maintenance, TEC publish/resync, and row actions
  - integration-heavy and side-effectful

## Recommended Future Test Coverage

- Add a focused admin-list regression for the `include_drafts` toggle:
  - nonce present
  - preference persists only when nonce is valid
  - current request still affects same-page filtering
- Add a save-path harness around `save_event_plan_meta()`:
  - preserve venue/date/meta writes
  - preserve runtime redirect targets
  - preserve pay-lock and compensation branches
- Add dedicated request/save coverage for:
  - calendar resync
  - unpublished-calendar suppress save
  - live refunds now
  - legacy ticket cleanup scheduling
- Keep reusing the existing Event Plan regression set:
  - `event-plan-ticket-ui-overrides-isolated`
  - `event-plan-legacy-ticketing-integration-smoke`
  - `event-plan-editor-vendor-preservation`
  - `event-plan-secondary-vendor-assignments`
  - `event-plan-module-reopen-and-market-layout`

## Micro-Slice Chosen For This Task

- Slice: Event Plans admin list `include_drafts` helper/output follow-up
- Functions touched:
  - `vms_admin_event_plan_list_query_value()`
  - `vms_admin_event_plan_list_add_include_drafts_toggle()`
- Exact findings addressed:
  - `11311` `WordPress.Security.NonceVerification.Recommended`
  - `11311` `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`
  - `11385` `WordPress.Security.EscapeOutput.OutputNotEscaped`
- Why it was safe:
  - admin list only
  - non-mutating display/filter path
  - save/publish/ticketing/cancellation/vendor/staffing/TEC/Woo mutation paths untouched

## Current Recommendation

- `WPORG-04F`
- Rationale:
  - the remaining Event Plans density is concentrated in `save_event_plan_meta()`, validation, cancellation/refund flows, and TEC/ticketing integrations
  - another low-risk Event Plans slice is possible, but the remaining yield is small compared with the risk of moving deeper without dedicated regression coverage
