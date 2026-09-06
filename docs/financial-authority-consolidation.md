# Financial authority consolidation — source-only candidate

Date: 2026-09-06. Base: `checkpoint/bvm-accepted-local-2026-09-06`, exactly `1e53fa3e4afc4301ff9c5b912df1a4bfc83f7444`. The task calls this the accepted Wave 4 checkpoint; its commit subject records reconciliation through Wave 3B-2. This work uses the requested SHA, not an inferred later baseline.

## Recovery and ownership

Old contaminated worktree: `/private/tmp/bvm-financial-authority/packages/vms-github-reconcile`, branch `work/financial-authority`, still at the checkpoint when inventoried. Recovery inventory found changes in `includes/admin/event-command-center.php`, `includes/admin/event-profitability-report.php`, `includes/admin/goals-forecast.php`, `includes/core/goals-forecast.php`, `includes/core/load.php`, and `tests/p0-source-consistency-repair.php`, plus an untracked `includes/core/financial-snapshot.php`. Tracked diff: 72 insertions, 109 deletions at inventory time. None of those tracked hunks was authored by this session. This session authored the initial creation of the unfinished service, but did not have independent byte-level proof that its then-current contents were exclusively its own. **No source was salvaged or mixed diff applied.**

New worktree: `/private/tmp/bvm-financial-authority-v2-01a077b1/packages/vms-github-reconcile`, branch `work/financial-authority-v2`, created directly from the specified SHA. Clean status and successful preflight were confirmed before editing. The adjacent `/private/tmp/bvm-financial-authority-v2-01a077b1/vms` and `backstage-venue-manager` are disposable source replicas, not normal Local plugins. Recovery receipts are outside the worktree in `/private/tmp/bvm-financial-authority-v2-01a077b1/`. The old worktree was inventoried read-only and otherwise left alone.

## A–B. Authority map and ambiguities

Amounts below are cents unless described otherwise. “Calculated” means this request calculated from available records; it does **not** certify upstream payment-system synchronization. No source below establishes finalized event accounting.

| Datum / surface | Source and provenance at checkpoint | Freshness / fallbacks | Classification and treatment |
| --- | --- | --- | --- |
| Paid ticket receipts / ECC and Event Plan ticket cards | `includes/admin/event-command-center.php` ticket reporting resolver; active reporting provider first | Request cache; provider optional freshness; Woo/core report next; sales cache last | Transaction evidence. Accepted P0 paid/free/refund/add-on exclusion retained. Shared adapter now in `includes/core/financial-ticket-source.php`; compatibility function names remain. |
| Website/Woo quantity and net receipts | `includes/core/ticket-sales-resolver.php` → `BVMGR_Ticket_Revenue_Service` → `includes/core/ticket-revenue.php`; order lines, discounted subtotal less refunds; tax separate | Direct available Woo records; core must actually be available before an empty report can mean zero | `TRANSACTIONAL_ACTUAL`, ticket channels only. A label of accounting “gross” would misstate the net-after-refund basis. |
| Data Tools total ticket revenue | `companion-plugins/vms-data-tools/includes/integrations/bvm-reporting-provider.php`, `vms_dt_bvm_reporting_event_summary`; model costs `ticket_sales_total_cents`, summary `total_ticket_sales_cents` | Active provider contract v1, Data Tools 0.5.55; request-calculated model. It does not supply a universal upstream sync timestamp | Transaction-derived ticket component of a broader model. Do not reclassify the entire model as actual. Preserve provider ID/version/source/warnings. |
| Square/POS ticket component | Same accepted provider; Website + Square ticket evidence, `square_scope_mode=full_day` | Imported Square evidence and provider date/location scope; may cover a venue day | Included once in provider ticket total. Do not add separate POS total to that total. Full-day attribution remains a completeness limitation. |
| Vendor Portal ticket/bonus basis | `includes/portal/vendor-portal.php`, provider `scope=vendor_portal`; Data Tools Website/Square merged paid-ticket rollup | Website sold-date cut-off and Square full-day evidence differ from ECC lifetime scope; Woo/core/cache/admissions fallback retained | Existing operational paid-basis contract unchanged. Different date/channel scopes are not interchangeable event totals. |
| Add-ons / food / bar / merch / other POS | Data Tools reporting module and revenue-intelligence model retain distinct Website add-on and onsite buckets | Import/report scope and local model freshness, not exposed as independent complete channels by BVM ticket-provider v1 | New financial contract explicitly returns unavailable for separate add-ons and non-ticket POS. It does not reach through Data Tools implementation files or invent a channel contract. |
| `_vms_event_actuals_totals` gross, ticket, add-on, concessions, other, direct, fees, overhead, true profit | `includes/core/goals-forecast.php` manual-total getter plus POS refresh; admin goal save | `_vms_event_actuals_provider`, `_vms_event_actuals_pulled_at_utc` are aggregate history, not per-field verification. Getter/save pad absent keys with zero; refresh replaces revenue but retains costs | Ambiguous stored observations. Retain original observed amount, provider and time, with `UNAVAILABLE` authoritative amount and unverified provenance. A nonzero field must not switch all revenue/cost fields to “actual.” |
| Manual concession actual | `_vms_concessions_actual_cents` plus `_vms_concessions_actual_source=manual` | Current saved operator entry; no independent verification timestamp. `source=provider` is an import, not a manual entry | Separate `MANUAL_ACTUAL`, explicitly operator-reported and `verified=false`. Never overrides or is automatically added to current ticket receipts. |
| Direct cost input | `_vms_event_direct_costs_cents` written by Event Plan finance save | Explicit scalar metadata, including saved zero; no independent verification record | Separate operator-reported `MANUAL_ACTUAL`, incomplete costs. This is aggregate direct cost, not necessarily vendor-only pay. |
| Vendor compensation estimate | `bvmgr_goals_get_default_direct_costs_cents`: configured direct field, otherwise eligible flat compensation and artist-fee commission | Read of current configured terms; missing data defaults to model zero in legacy goals | `FORECAST` planning component. Not proof that a bill was paid or all lineup/door-split/bonus liabilities are included. |
| Staffing labor | `bvmgr_staffing_resolve_event_snapshot` rollup `est_labor_cost_total` in dollars | Existing fresh/recomputed rollup with `computed_at`; missing/dirty/derived rollup not used as current labor estimate here | `FORECAST`, scheduled labor, converted to cents once. No paid-payroll source. Financial read passes `skip_rollup_recompute=true` to avoid persisting a rollup. |
| Processing fees | `_vms_event_processing_fees_cents`; legacy default getter | Saved operator amount; absence is unavailable for actuals, model default remains model-only | Reported direct cost, not inferred from Woo order totals or Square gross. Explicit zero is retained. |
| Goal forecast revenue | `bvmgr_goals_get_event_pnl(forecast)`: headcount × average ticket price, concessions buyer rate × modeled spend | Current settings/headcount and cached ticket-price sample; no transaction freshness claim | `FORECAST`; valid modeled zero remains a forecast zero. |
| Goal ticketed / “true” scenario | `bvmgr_goals_get_event_pnl(ticketed/true)`; ticketed/true headcount, model assumptions or mixed saved totals | Can estimate ticket revenue from headcount; mode named `true` does not make result actual | Existing arithmetic preserved for compatibility. Output adds `financial_basis=FORECAST` or `LEGACY_MIXED_SCENARIO`, `finalized=false`; UI labels planning/entered scenarios. |
| Goal gross, direct cost, fees, overhead, event profit and true profit | Same P&L; overhead flat/per-attendee/percent/hybrid allocation | Allocation is a model, even when based on reported headcount or costs | Table headings specify scenario basis; “True Profit” becomes profit after allocated overhead (scenario). These calculations intentionally remain planning tools. |
| Goal progress, remaining target, required average, trailing projection, break-even | `bvmgr_goals_compute_goal_progress`, metric selector, break-even helper | Past events use legacy `true` mode; period cap and assumptions retained | `actual_to_date_cents` is retained as a compatibility key but explicitly marked mixed modeled/entered progress. Dashboard no longer calls it actual to date. |
| ECC financial gross/margin | Previously chose manual-vs-forecast based on any nonzero stored total and subtracted estimated labor | Could show modeled $0 while ticket card knew paid transactions; absent costs also became zero | Uses shared snapshot. Ticket receipts, known reported costs, provisional contribution, forecast and final-unavailable are separate. Module hub uses scoped reported-cost and forecast labels. |
| Event Plan financial summaries | `includes/admin/goals-forecast.php` metabox plus ECC-backed modules | Previously “True Profit” and “Ticketed Profit” concealed modeling | Shared actual/forecast summary plus clearly labeled legacy scenarios. Existing input save/nonce/capability paths unchanged. |
| Profitability ticket revenue, concessions, vendor/direct, labor, core profit, night score | `includes/admin/event-profitability-report.php` previously mixed cached ticket stats and any positive manual totals, plus 65% concession margin | Positive-only fallbacks erased legitimate zero; unavailable was zero; past date was “Final” | Shared receipt/manual/forecast components. Scorecard remains a `MIXED_PLANNING_ESTIMATE`. It now deducts explicitly reported processing fees and requires needed inputs; missing rows are counted, not coerced. Past events remain provisional. |
| Investor ticket/channel sales | Investor Portal 0.2.3 `class-vms-investor-metrics-provider.php`, existing live resolution and cached snapshot display; documented checkpoint compatibility patch | Stored snapshot timestamp and explicit manual override history; live and cached are distinct | Companion source patch marks cached currency metrics `RECORDED_ACTUAL`, preserves override and automatic values, and does not claim cached data are current. |
| Investor labor, performer, tax, direct, concession cost, other expenses, estimated event net | Same provider; Data Tools model/default costs could carry `auto_actual` | Mixed estimates, local records and optional explicit overrides; partial components can yield a net estimate | Companion patch classifies modeled amounts as `FORECAST`/fallback with visible labels; overrides remain separately identified, unverified manual values. Estimated net is partial and never final. |
| Investor ads | Existing measured-vs-budget/fallback distinction | Source-dependent timestamp and scope | Budget/fallback remains an estimate; measured path retained. Patch does not change Ads behavior or spend. |
| Payables / settlement-related terms | `includes/core/payables.php`: normalized export bill from Event Plan/pay snapshot or configured compensation | Current terms/snapshot used for export; deterministic bill number, no complete reconciled ledger or posting/payment authority | Payable/export preparation is not `ACCOUNTING_FINAL`. Source unchanged. Finalized settlement/accounting import is deferred until a legitimate source and reconciliation contract exist. |

The principal ambiguity was **selection based on nonzero values instead of provenance**. Other problems were “true”/“final” terminology, combining full-day POS with event-scoped numbers without a scope label, treating unknown costs as zero, and treating scheduled labor as a paid expense.

## C–E. Semantic model, snapshot and authority policy

`bvmgr_financial_get_event_snapshot(plan_id)` returns contract version 1. `bvmgr_financial_build_snapshot` is the pure normalizer used by disposable tests. Every monetary value carries `amount_cents` (integer or null), `basis`, `source`, `scope`, provider ID where known, calculation/freshness evidence, confidence/completeness, `verified`, and `finalized`.

- `TRANSACTIONAL_ACTUAL`: transaction-derived ticket receipts, including provider-qualified POS ticket evidence.
- `MANUAL_ACTUAL`: explicitly sourced operator-reported actual fields. Existing BVM has no independent verification workflow; these values explicitly carry `verified=false` and operator-reported confidence. They do not qualify as a verified revenue override.
- `FORECAST`: goals/models/configured cost and scheduled labor estimates.
- `DERIVED_ACTUAL`: provisional ticket contribution from transaction receipts less known reported costs; exposes its transaction/manual component bases. It is incomplete and excludes labor and other missing costs.
- `UNAVAILABLE`: no authoritative amount; null never becomes fake zero.
- `ACCOUNTING_FINAL`: reserved conceptual type; no producer exists and no value is emitted with this basis. `final` is unavailable and `finalized=false` throughout.
- Investor-only `RECORDED_ACTUAL`: a cached presentation observation, not current evidence. Legacy ambiguous aggregate BVM totals remain observations with unavailable authority instead.

There is no single FINAL → MANUAL → TRANSACTION → FORECAST ladder. Precedence is field-specific:

1. Current ticket receipts: active provider using accepted ECC scope → available Woo/core report → timestamp-qualified sales cache → unavailable. Explicit stale provider results fall back, retaining a warning. Stale cache values remain inspectable in existing P0 ticket UI but do not become current financial receipts.
2. A calculated provider zero remains valid under the accepted provider v1 contract. A core empty report means zero only when Woo and the core service are available. Provider errors/exceptions remain isolated. Provider freshness not supplied is not fabricated.
3. Manual concessions/direct costs/processing remain separate fields with explicit provenance. Ambiguous aggregate snapshots never override current transaction evidence. No event-gross manual override policy is invented.
4. Known costs sum only present direct/processing entries. With no known costs, contribution is unavailable. With some known costs, it is explicitly **partial provisional ticket contribution**, not event profit. Paid labor and final accounting remain unavailable.
5. Forecast gross and direct costs retain existing goal assumptions. Forecast direct margin subtracts scheduled labor once, excludes overhead, and is unavailable when labor evidence is missing/stale. This is distinct from the legacy goal scenario profit after allocated overhead.
6. Complete event actual gross remains unavailable because provider v1 does not establish every distinct revenue channel. The contract exposes transactional ticket receipts separately; it never calls that complete accounting gross.
7. No additive Square enrichment is performed beyond the accepted provider total; this avoids double counting. No direct filesystem coupling to Data Tools is introduced.

The ticket adapter is extracted from checkpoint code, not reimplemented from the contaminated worktree. Existing ECC public function names remain wrappers. The provider contract/version and Data Tools 0.5.55 implementation are unchanged. Core availability checks and stale-provider exclusion are the intentional source-resolution refinements.

## F–I. Surface changes

Event Plan and ECC use `bvmgr_financial_render_summary` and its shared labels. Profitability includes that same evidence summary while retaining its separate planning scorecard purpose. Receipts/forecast are shown side by side; source/freshness and calculation time are visible, and warnings are escaped. Missing values display “Unavailable.” No broad visual redesign was performed.

Profitability's estimated core contribution is ticket receipts minus reported/configured direct costs, estimated labor, and reported processing fees. Estimated night score adds manual concessions at the existing 65% modeled contribution rate. Fees are now part of this estimate; overhead, other unavailable expenses, and channel completeness remain explicitly excluded. Aggregate totals sum available rows with per-metric omitted-event counts; an entirely unavailable total stays null. A past date no longer labels an event “Final.”

Goals retains its arithmetic and compatibility keys. Its three scenario columns and progress labels identify the modeled/entered basis. Input validation, save/refresh authorization, REST, AJAX, nonce and capability behavior were not changed.

Investor Portal is a separately owned add-on. `financial-authority-investor-0.2.3.patch` is a **source-only companion change**, tested against the exact 0.2.3 provider file whose before/after SHA-256 values are in `financial-authority-investor-baseline.json`. It adds read-only financial classification/labels to both live metrics and cached display, retaining all values, automatic evidence, manual override precedence, storage and permissions. The patch uses zero context to keep the patch artifact whitespace-clean; apply it with `git apply --unidiff-zero` only to a source copy matching the recorded baseline SHA. It is not installed or activated by BVM and has not been deployed. Full add-on acceptance remains a future integration gate.

## J. Migration and deliberate limits

No schema, persisted fields, data migration, automatic refresh, payment operation or finalization is added. Existing explicit scalar inputs are interpreted read-only. Old writers can persist default zeroes and do not record verification, so the new contract never claims those entries were independently verified. Historical mixed aggregate values cannot safely be upgraded to verified actuals.

Remaining source contracts: nonoverlapping add-on/non-ticket POS revenue; verified manual event overrides; paid payroll; complete expense reconciliation; finalized accounting imports; source-level upstream freshness guarantees. These return unavailable now instead of invented values. Investor's legitimate cached workflow is labeled, not silently replaced by live recalculation. Provider code may retain its existing optional diagnostics/cache behavior; this task did not execute external providers against normal Local or promise new provider internals are side-effect-free.

## K. Validation

All test data were in-memory PHP fixtures or isolated source copies. No WordPress site boot, DB connection, WP-CLI activation, external HTTP, normal-local guard installation, or production/staging access occurred.

- `tests/financial-authority.php`: 198 assertions with Woo available and 198 with `--no-woo`. Covers zero sales, paid/free/comp, refund-adjusted fallback, actual + forecast, manual entries and ambiguous imports, provider exceptions/WP_Error, unavailable/stale provider/cache, valid-zero cache, missing costs, direct-only, labor-only, fees-only, negative and positive contributions, incomplete aggregates, actual report rendering and actual Event Plan metabox rendering, ECC compatibility wrapper, escaping, and identical shared financial evidence. Write/network traps remain in the test environment.
- `tests/data-tools-reporting-provider.php`: real 0.5.55 adapter in both load orders, Website/Square evidence and full-day scope, valid zero and failure isolation; added financial-basis and no-double-count assertions.
- `tests/reporting-provider-contract.php`, `tests/data-tools-provider-decoupling.php`, `tests/p0-source-consistency-repair.php`, `tests/goals-forecast-repository-sql-remediation.php`: passed. Extraction-based tests now load the extracted shared adapter; all original P0 ticket/staffing behavior assertions remain.
- Staffing repository, final repository, matrix/rollup reporting, admin inline assets, Event Plan staff output, and ticketing core repository SQL suites: passed against disposable source replicas.
- `tests/financial-investor-semantics.php`: patched isolated Investor source passed model/cached/manual-zero/underlying-evidence/unavailable semantics; patch application and candidate hash checked independently.
- Changed PHP syntax checks, changed-file replica parity, and `git diff --check`: passed before commit.
- Additional historical audit `tests/g15-payables-tax-credit-dates.php` could not run: hardcoded `/tmp/wporg-dbzero-g14.qulnlt/plugin-check.strict.json` is absent. The test and payables code are unchanged; no fabricated historical artifact or weakened assertion was substituted.
- Full official-five/additional WordPress runtime matrices were **not run**: their normal-local containment/activation setup is outside this task's source-only/no-normal-local-mutation boundary. Earlier checkpoint results are not represented as fresh results here.

## L–O. Files, commit and integration hotspots

Runtime: `includes/core/financial-ticket-source.php`, `includes/core/financial-snapshot.php`, `includes/core/load.php`, `includes/core/goals-forecast.php`, `includes/admin/event-command-center.php`, `includes/admin/goals-forecast.php`, `includes/admin/event-profitability-report.php`.

Tests: `tests/financial-authority.php`, `tests/financial-investor-semantics.php`, `tests/p0-source-consistency-repair.php`, `tests/data-tools-provider-decoupling.php`, `tests/data-tools-reporting-provider.php`. Documentation: this report, companion Investor patch/hash manifest, and the remediation ledger addendum.

The local commit is identified in the task closeout (and can be found by this report's Git history); no push is authorized. Merge hotspots are ECC ticket-function extraction/financial rendering/module hub, `core/load.php`, Event Plan Goals metabox, profitability row collector, and the P0 extraction test. Reconcile any competing financial-authority work semantically; do not combine the old contaminated diff.

Staffing Lifecycle overlap is the **read contract**, not a change to `includes/core/staffing.php`: financial consumers use `rollup.est_labor_cost_total`, `computed_at`, `rollup_state`, and `skip_rollup_recompute`. The new service intentionally distinguishes scheduled labor from paid payroll. A later Staffing Lifecycle change must preserve those keys or provide an adapter. No staffing branch edits, cherry-picks or merges were performed.

## P. Preservation proof

Read-only before/after fingerprints matched for the original dirty repository (1,783 files), normal active BVM (384 files), legacy `vms` (408), Data Tools (81), and Investor Portal (8). The protected stash remained `d08e726804712dc233f0e37b217abd6389963863`. Detailed file-count/tree-digest receipts are at `/private/tmp/bvm-financial-authority-v2-01a077b1/preservation.json`; fingerprint method hashes ordered relative paths and contents, excluding `.git`.

These are source-preservation receipts, not a claim to have inspected or frozen normal Local's database. The execution boundary is stronger for this task's actions: no WordPress boot or database tooling was invoked, so no business data, activation list, cron, normal-local state, staging or production was modified by these tests. No communications, payment, SSH, push, packaging, ZIP, tag, deployment or stash operation occurred. Only task-branch source and disposable `/private/tmp` artifacts changed.

Preserved source digests (ordered path/content SHA-256):

| Tree | Digest |
| --- | --- |
| `vms-github-reconcile` | `9703053c1b9276a318f37f5df308a55f4d7d52afeaee4b583235e7057f8a14a5` |
| `backstage-venue-manager` | `7ef8fae6160269fa975e52b730fa6cf91fb96afd54f0f3c80f6dc8eec41b23f4` |
| `vms` | `5a2ce0b7dbe547e00235ba9891a3853e9729fca8fe1f3f148ee85f8b20478c98` |
| `vms-data-tools` | `c5faa1e433e3c703e538005bb5e538bc0c3c4518cbc0989b0d96254208986dc1` |
| `vms-investor-portal` | `efe667e8ce2b5b8f13056589dabfc41899d667b2d5672e0d2bb6937180709dda` |
