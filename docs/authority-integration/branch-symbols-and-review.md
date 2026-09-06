# Independent integration source review — 2026-09-06

Scope: read-only review of `/private/tmp/bvm-authority-integration-20260906/packages/vms-github-reconcile` while reconciliation was in progress. HEAD was Financial `02619f276ffde1a4fc04426c4404237d1bc2d8f6`, with staffing changes staged and integration changes unstaged. Required preflight reported expected integration dirt only; stash present and diff check clean. The coordinator owns all current edits. No runtime or DB operations were performed by this reviewer.

## Assessment

The reviewed architecture is coherent: `bvmgr_staffing_get_financial_labor()` uses a repeatable read snapshot under the lifecycle lock rather than stored rollups. `bvmgr_financial_get_event_snapshot()` no longer caches the combined financial snapshot, so same-request staffing mutations can refresh labor independently from the accepted ticket request-cache contract. Planned labor prices required active positions, including empty positions; committed labor prices confirmed assignments; proposed/confirmed are separate, declined/canceled excluded, and paid payroll remains unavailable. A legacy-only staffing record fails unavailable rather than zero. Empty normalized staffing is known zero. Unknown statuses/duplicate active assignees fail closed. Invalid rates or hourly windows null the affected amount. Forecast subtracts planned labor once; committed labor is a separate estimate, not added a second time. Profitability explicitly labels its transaction/manual/planning mixture and propagates unavailable operands and summary coverage counts.

No obvious ordinary-path double-counting, stale stored-rollup reuse, or false paid-payroll claim was found in the inspected reconciliation. This is not a green runtime certification: contained database tests, concurrent mutation tests, and full compatibility matrices belong to the coordinator's test run.

## Findings and bounded follow-ups

1. **P2: Include role metadata in the transactional-engine contract.** `includes/core/staffing-financial.php:bvmgr_staffing_get_financial_labor()` reads term metadata for rate/pay-type inside its advertised coherent repeatable snapshot. `includes/core/staffing-lifecycle.php:bvmgr_staffing_require_transaction_schema()` checks assignments/slots/audit/rollups/posts/postmeta, but not `$wpdb->termmeta`. On a nontransactional termmeta table, a concurrent role-rate update can become visible independently of the slot snapshot. Validate termmeta engine for the read projection, or explicitly narrow/document the coherence guarantee. Ordinary InnoDB installations do not reproduce this edge case. This was reported to the coordinator before final testing.

2. **P2 freshness caveat: operator entries are read before cache invalidation.** `financial-snapshot.php:bvmgr_financial_get_event_snapshot()` reads direct/processing/manual concessions before `bvmgr_staffing_get_financial_labor()` clears plan metadata cache. Normal `update_post_meta()` mutations invalidate the cache and are fine. If another authorized raw DB writer changed those entries earlier in the request, one snapshot can contain stale manual values while labor is current. A simple early metadata invalidation would align the stated re-read guarantee; alternatively specify that WP API writes are the supported mutation contract. Do not claim this is a reproduced normal UI defect.

3. **P2 test hardening: authority flag and amount consistency.** The pure `bvmgr_financial_build_snapshot()` currently consumes numeric `staffing_labor.planned_cents` and `committed_cents` regardless of `staffing_labor.availability`. The in-tree reader always nulls failure output, so ordinary adapter failure is safe. A future adapter returning `availability=unavailable` with stale numeric fields would emit numeric PLANNED/COMMITTED values with unavailable freshness. A contract assertion/guard is preferable to relying on all future adapters keeping those two signals synchronized.

4. **Document penny rounding.** `bvmgr_staffing_financial_estimate()` rounds an entire planned slot after multiplying headcount, while committed values round per individual assignment. Identical full staffing can therefore differ by a penny (e.g. three people, 10.01/hour, one minute: planned 50 cents, committed 51 cents). This is not double counting, but tests and wording should specify the chosen rounding policy so deterministic differences are explainable.

5. **Legacy compatibility helper is not the new report authority.** `event-profitability-report.php:bvmgr_event_profitability_get_labor_cost_cents()` still recomputes/reads rollup and returns zero for unavailable, but `bvmgr_event_profitability_get_rows()` no longer calls it; new rows use financial snapshot's planned amount. Do not mistake this dead-to-current-report helper for a regression in the report. Keep as compatibility debt unless a separate caller inventory authorizes changing its return contract.

6. **Staff Tasks remains a proposed-inclusive operational assignment policy.** `includes/modules/staff-tasks/store.php:bvmgr_tasks_resolve_scheduled_role_user_id()` includes proposed/confirmed/checked_in and excludes declined/canceled. `generator.php` receives `vms_staffing_event_saved`; lifecycle code defers this until after successful commit. This preserves event-hook continuity. It does not certify a task recipient has confirmed employment, and task completion is not actual paid labor.

## Focused acceptance required from integrated runtime

- Read proposed, confirm in same PHP request, read again: same planned; changed committed amount/revision and same current Event Plan/ECC basis.
- Read before/after decline/cancel, assignment override rate, slot count/rate, role rate and event time changes.
- Empty source versus legacy-only source versus unavailable schema/DB/adapter/lock and unknown status.
- Invalid rate and hourly window, explicit zero rate/pay_type none; valid partial amounts retain field-specific unavailable basis.
- Stale ticket provider plus current staffing; ticket zero/refund/manual cost paths; no committed-plus-planned subtraction.
- Repeatable read proof under concurrent lifecycle writes; no nested/external transaction corruption; post/term metadata cache residue checks.
- Role metadata engine preflight and rounding regression as above.

## Branch symbol comparison methodology

The following maps compare named PHP function/class/interface/trait bodies from accepted Wave4 `1e53fa3e4afc4301ff9c5b912df1a4bfc83f7444` independently to Financial `02619f276ffde1a4fc04426c4404237d1bc2d8f6` and Staffing `18146de3d652a269cb9b056da23efffda9141382` (includes forensic predecessor). Generated with PHP tokenizer and balanced token braces; + added, ~ body changed, - removed. Includes tests, excludes anonymous functions as independent symbols (their enclosing named body changes are captured); class hashes include methods. Method entries are file-local names. Top-level/bootstrap changes are marked separately. Assets and documents appear in coordinator's exact file diff inventory; the Investor patch is a separately carried source patch and not a loaded runtime class change in BVM.

## financial symbol map

### `includes/admin/event-command-center.php`

- `~ T_FUNCTION bvmgr_event_command_center_summarize_ticket_report_rows`
- `~ T_FUNCTION bvmgr_event_command_center_get_ticket_reporting_truth`
- `~ T_FUNCTION bvmgr_event_command_center_normalize_ticket_cache`
- `~ T_FUNCTION bvmgr_event_command_center_build_ticket_sales_snapshot`
- `~ T_FUNCTION bvmgr_event_command_center_get_financial_snapshot`
- `~ T_FUNCTION bvmgr_event_command_center_render_page_content`
- `~ T_FUNCTION bvmgr_event_command_center_build_module_hub_cards`

### `includes/admin/event-profitability-report.php`

- `~ T_FUNCTION bvmgr_event_profitability_stage_label`
- `~ T_FUNCTION bvmgr_event_profitability_get_rows`
- `~ T_FUNCTION bvmgr_event_profitability_render_admin_page`

### `includes/admin/goals-forecast.php`

- `~ T_FUNCTION bvmgr_goals_render_goals_tab`
- `~ T_FUNCTION bvmgr_goals_render_forecast_defaults_tab`
- `~ T_FUNCTION bvmgr_goals_event_plan_metabox_html`
- `~ T_FUNCTION bvmgr_goals_render_dashboard_panel`

### `includes/core/financial-snapshot.php`

- `+ T_FUNCTION bvmgr_financial_value`
- `+ T_FUNCTION bvmgr_financial_source_is_stale`
- `+ T_FUNCTION bvmgr_financial_build_snapshot`
- `+ T_FUNCTION bvmgr_financial_get_event_snapshot`
- `+ T_FUNCTION bvmgr_financial_display_rows`
- `+ T_FUNCTION bvmgr_financial_money`
- `+ T_FUNCTION bvmgr_financial_render_summary`

### `includes/core/financial-ticket-source.php`

- `+ T_FUNCTION bvmgr_financial_request_cache`
- `+ T_FUNCTION bvmgr_reporting_summarize_ticket_rows`
- `+ T_FUNCTION bvmgr_reporting_get_ticket_truth`
- `+ T_FUNCTION bvmgr_reporting_normalize_ticket_cache`

### `includes/core/goals-forecast.php`

- `~ T_FUNCTION bvmgr_goals_get_event_pnl`
- `~ T_FUNCTION bvmgr_goals_compute_goal_progress`

### `includes/core/load.php`

Changes outside named PHP symbol bodies only.

### `tests/data-tools-provider-decoupling.php`

- `+ T_CLASS WooCommerce`
- `+ T_CLASS BVMGR_Ticket_Revenue_Service`
- `+ T_FUNCTION wc_get_orders`

### `tests/data-tools-reporting-provider.php`

- `~ T_FUNCTION dt_provider_run`

### `tests/financial-authority.php`

- `+ T_FUNCTION __`
- `+ T_FUNCTION esc_html__`
- `+ T_FUNCTION esc_html`
- `+ T_FUNCTION esc_attr__`
- `+ T_FUNCTION esc_attr`
- `+ T_FUNCTION esc_url`
- `+ T_FUNCTION absint`
- `+ T_FUNCTION sanitize_key`
- `+ T_FUNCTION sanitize_text_field`
- `+ T_FUNCTION wp_strip_all_tags`
- `+ T_FUNCTION add_action`
- `+ T_FUNCTION get_post_meta`
- `+ T_FUNCTION update_post_meta`
- `+ T_FUNCTION update_option`
- `+ T_FUNCTION wp_remote_get`
- `+ T_FUNCTION bvmgr_goals_fmt_money`
- `+ T_FUNCTION bvmgr_goals_get_event_pnl`
- `+ T_FUNCTION bvmgr_staffing_resolve_event_snapshot`
- `+ T_FUNCTION bvmgr_ticket_revenue_build_report`
- `+ T_CLASS WooCommerce`
- `+ T_CLASS BVMGR_Ticket_Revenue_Service`
- `+ T_FUNCTION wc_get_orders`
- `+ T_CLASS WP_Error`
- `+ T_FUNCTION get_error_code`
- `+ T_FUNCTION get_error_message`
- `+ T_FUNCTION is_wp_error`
- `+ T_FUNCTION same`
- `+ T_FUNCTION transaction`
- `+ T_CLASS WP_Query`
- `+ T_FUNCTION __construct`
- `+ T_FUNCTION get_post_status`
- `+ T_FUNCTION get_the_title`
- `+ T_FUNCTION get_edit_post_link`
- `+ T_FUNCTION wp_date`
- `+ T_FUNCTION get_option`
- `+ T_FUNCTION bvmgr_event_profitability_get_event_timestamp`
- `+ T_FUNCTION current_user_can`
- `+ T_FUNCTION admin_url`
- `+ T_FUNCTION add_query_arg`
- `+ T_CLASS WP_Post`
- `+ T_FUNCTION bvmgr_pos_provider_detect`
- `+ T_FUNCTION bvmgr_goals_get_manual_event_actual_totals`
- `+ T_FUNCTION bvmgr_goals_event_plan_refresh_url`
- `+ T_FUNCTION bvmgr_goals_get_active_goal`
- `+ T_FUNCTION bvmgr_goals_break_even_headcount`
- `+ T_FUNCTION bvmgr_goals_query_value`
- `+ T_FUNCTION bvmgr_goals_admin_url`
- `+ T_FUNCTION wp_nonce_field`
- `+ T_FUNCTION selected`
- `+ T_FUNCTION wp_json_encode`

### `tests/financial-investor-semantics.php`

- `+ T_FUNCTION __`

### `tests/p0-source-consistency-repair.php`

Changes outside named PHP symbol bodies only.


## staffing symbol map

### `includes/core/event-reschedule.php`

- `~ T_FUNCTION bvmgr_event_occurrence_apply`

### `includes/core/load.php`

Changes outside named PHP symbol bodies only.

### `includes/core/staffing-lifecycle-ui.php`

- `+ T_FUNCTION bvmgr_staffing_status_label`
- `+ T_FUNCTION bvmgr_staffing_lifecycle_nonce`
- `+ T_FUNCTION bvmgr_staffing_lifecycle_message`
- `+ T_FUNCTION bvmgr_staffing_handle_lifecycle_request`
- `+ T_FUNCTION bvmgr_staffing_lifecycle_public_result`
- `+ T_FUNCTION bvmgr_staffing_lifecycle_ajax`
- `+ T_FUNCTION bvmgr_staffing_render_lifecycle_controls`
- `+ T_FUNCTION bvmgr_staffing_lifecycle_enqueue_assets`

### `includes/core/staffing-lifecycle.php`

- `+ T_CLASS BVMGR_Staffing_Failure`
- `+ T_CLASS BVMGR_Staffing_Transaction_DB`
- `+ T_FUNCTION __construct`
- `+ T_FUNCTION finish_transaction`
- `+ T_FUNCTION check_connection`
- `+ T_FUNCTION insert`
- `+ T_FUNCTION update`
- `+ T_FUNCTION delete`
- `+ T_FUNCTION query`
- `+ T_FUNCTION bvmgr_staffing_transaction_active`
- `+ T_FUNCTION bvmgr_staffing_lock_name`
- `+ T_FUNCTION bvmgr_staffing_transaction_tables`
- `+ T_FUNCTION bvmgr_staffing_require_transaction_schema`
- `+ T_FUNCTION bvmgr_staffing_atomic`
- `+ T_FUNCTION bvmgr_staffing_defer_event_saved`
- `+ T_FUNCTION bvmgr_staffing_lifecycle_row`
- `+ T_FUNCTION bvmgr_staffing_lifecycle_window`
- `+ T_FUNCTION bvmgr_staffing_assignment_overlaps`
- `+ T_FUNCTION bvmgr_staffing_lifecycle_dirty`
- `+ T_FUNCTION bvmgr_staffing_lifecycle_record`
- `+ T_FUNCTION bvmgr_staffing_transition_assignment`
- `+ T_FUNCTION bvmgr_staffing_matrix_proposals`
- `+ T_FUNCTION bvmgr_staffing_sync_lifecycle_window`
- `+ T_FUNCTION bvmgr_staffing_event_overlap_warnings`
- `+ T_FUNCTION bvmgr_staffing_guard_time_metadata`
- `+ T_FUNCTION bvmgr_staffing_guard_time_add`
- `+ T_FUNCTION bvmgr_staffing_guard_time_delete`
- `+ T_FUNCTION bvmgr_staffing_guard_time_mutation`
- `+ T_FUNCTION bvmgr_staffing_guard_time_update_by_mid`
- `+ T_FUNCTION bvmgr_staffing_guard_time_delete_by_mid`

### `includes/core/staffing.php`

- `~ T_FUNCTION bvmgr_staffing_apply_template_to_event`
- `~ T_FUNCTION bvmgr_staffing_event_plan_datetime`
- `~ T_FUNCTION bvmgr_staffing_resolve_anchor_local`
- `~ T_FUNCTION bvmgr_staffing_resolve_slot_window`
- `~ T_FUNCTION bvmgr_staffing_sync_assignment_shift_timestamps_for_slot`
- `~ T_FUNCTION bvmgr_staffing_seed_event_slots_from_template`
- `~ T_FUNCTION bvmgr_staffing_resolve_event_snapshot`
- `~ T_FUNCTION bvmgr_staffing_save_event_roles_matrix`
- `~ T_FUNCTION bvmgr_staffing_compute_rollup`

### `includes/cpt/event-plans.php`

- `~ T_CLASS BVMGR_Admin_Event_Plans`
- `~ T_FUNCTION build_event_plan_staff_response_payload`
- `~ T_FUNCTION build_event_plan_staff_response_role_rows`
- `~ T_FUNCTION render_event_plan_staff_response_html`
- `~ T_FUNCTION render_event_plan_staff_response_role_card_html`

### `includes/cpt/event-plans/partials/staff.php`

Changes outside named PHP symbol bodies only.

### `includes/db/staffing-lifecycle.php`

- `+ T_FUNCTION bvmgr_staffing_migrate_lifecycle`
- `+ T_FUNCTION bvmgr_staffing_lifecycle_preflight`

### `includes/portal/staff-portal.php`

- `~ T_FUNCTION bvmgr_staff_portal_assignment_status_label`
- `~ T_FUNCTION bvmgr_staff_portal_render_dashboard`

### `tests/event-plan-staff-eligibility.php`

Changes outside named PHP symbol bodies only.

### `tests/event-plan-staff-inline-js-remediation.php`

Changes outside named PHP symbol bodies only.

### `tests/p0-source-consistency-repair.php`

Changes outside named PHP symbol bodies only.

### `tests/staff-portal-inline-js-remediation.php`

Changes outside named PHP symbol bodies only.

### `tests/staff-portal-safe-html-output-remediation.php`

Changes outside named PHP symbol bodies only.

### `tests/staffing-final-repository-sql-remediation.php`

- `+ T_FUNCTION bvmgr_staffing_transaction_active`

### `tests/staffing-lifecycle-checkpoint-forensics.php`

- `+ T_FUNCTION forensic`
- `+ T_FUNCTION matrix`

### `tests/staffing-lifecycle/bootstrap.php`

Changes outside named PHP symbol bodies only.

### `tests/staffing-lifecycle/concurrency.php`

- `+ T_FUNCTION race`

### `tests/staffing-lifecycle/deadlock-worker.php`

Changes outside named PHP symbol bodies only.

### `tests/staffing-lifecycle/deadlock.php`

Changes outside named PHP symbol bodies only.

### `tests/staffing-lifecycle/integration.php`

Changes outside named PHP symbol bodies only.

### `tests/staffing-lifecycle/runtime.php`

- `+ T_FUNCTION check`
- `+ T_FUNCTION fixture`
- `+ T_FUNCTION options`
- `+ T_FUNCTION audits`
- `+ T_FUNCTION request_for`

### `tests/staffing-lifecycle/worker.php`

Changes outside named PHP symbol bodies only.

### `tests/staffing-matrix-rollup-reporting-repository-sql-remediation.php`

- `+ T_FUNCTION bvmgr_staffing_defer_event_saved`
- `+ T_FUNCTION bvmgr_staffing_lifecycle_window`
- `+ T_FUNCTION bvmgr_staffing_transaction_active`
- `+ T_FUNCTION bvmgr_staffing_matrix_proposals`
- `+ T_FUNCTION bvmgr_staffing_assignment_overlaps`
- `~ T_FUNCTION vms_test_run_save_event_roles_matrix_assertions`
- `~ T_FUNCTION vms_test_run_compute_rollup_assertions`

### `tests/staffing-repository-sql-remediation.php`

- `+ T_FUNCTION bvmgr_staffing_transaction_active`
- `+ T_FUNCTION bvmgr_staffing_matrix_proposals`
- `+ T_FUNCTION bvmgr_staffing_sync_lifecycle_window`
- `~ T_CLASS VMS_Test_WPDB`
- `~ T_FUNCTION update`


## Coordinator dispositions after review

Termmeta and term_taxonomy now require InnoDB; raw role metadata and role/staff identities are read inside the repeatable SQL snapshot. Plan metadata is captured directly and passed through a temporary scoped metadata filter for canonical window resolution, then the filter is removed. Financial operator metadata cache is invalidated before reading reported entries. Pure financial builder rejects numeric labor whenever availability is unavailable. Per-slot planned and per-person committed cent rounding is intentional and may differ by a penny even at equal headcount. Compatibility helper remains unchanged and is not the report authority. Final real-database labor projection tests passed 33 assertions, and pure cross-domain tests passed 127; full runtime matrices are recorded separately.
