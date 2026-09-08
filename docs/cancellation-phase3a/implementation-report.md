# Phase 3A — Cancellation refund completeness and completion report

Date: 2026-09-07 (America/Chicago). Recommendation: **ready for local acceptance**, as an isolated development candidate. No normal-local installation or operational acceptance has been performed.

## Authority and preservation

Implementation branch: `work/cancellation-refund-completeness-20260907`, based on accepted normal-local source `89f75a55678fe57c19c9e4380b1f084873af3db2`.

Worktree: `/private/tmp/bvm-cancellation-phase3a-20260907/packages/vms-github-reconcile`. Shared runtime files are synchronized only to `/private/tmp/bvm-cancellation-phase3a-20260907/vms`.

The starting directory was the historical dirty mirror at `79da784`. Phase 2 explicitly identifies it as preserved evidence rather than runtime authority. Its dirt was neither cleaned nor imported. The accepted-source worktree passed preflight; a fresh Phase 3A worktree and disposable sibling then passed preflight before edits. No commit was authorized or made.

Final read-only preservation verification confirms:

- Frozen release branch remains `04caf99ae7a98ac507c067a7535d2a672efa457b`.
- Qualified ZIP remains SHA-256 `2c2a488395d32d419741a99a1211c18fe649f73e567c1cff8de749d44d08c8e3`, 396 files. ZIP was read for verification, never rebuilt or written.
- Protected stash remains `d08e726804712dc233f0e37b217abd6389963863`.
- Eleven regression-sensitive runtime files match accepted source byte-for-byte, including staffing, reschedule/communications, reporting providers, private files, ECC and ticket integrity.

No installed normal-local/legacy plugin, production, staging, activation, database, customer email, actual refund, release submission, packaging, remote Git or protected-stash mutation occurred.

## A. Canonical architecture

`includes/core/cancellation-purchases.php` owns discovery, the proposal, exclusions, acceptance, execution and independent reconciliation. Existing cancellation adapters delegate their discovery and execution to this model. The old item-matching helper delegates to the same identity resolver, eliminating its competing product-first interpretation.

A Woo component has a stable `order_id:item_id` identity, item type, product/role/description, provider and relationship evidence, quantity/refunded quantity, original discounted total plus tax, previous itemized refunds, remaining refundable amount, proposed amount, eligibility/reason and exclusion audit. Customers and currency are grouped at order level. Fee/shipping lines with explicit event association also participate; ordinary unrelated fees and shipping are not assigned to an event by inference.

WooCommerce remains the money authority. Remaining merchandise amount uses `get_total_refunded_for_item`; remaining tax uses `get_tax_refunded_for_item` by rate and item type; quantity uses `get_qty_refunded_for_item`. This handles monetary-only, quantity and tax partials without inferring dollars from refunded quantity. An order-level balance conflict becomes unresolved instead of silently capping a larger set of refund lines. Execution retains `wc_create_refund`, `refund_payment=true`, `restock_items=false`, existing sales-stop prerequisites and the existing automatic-refund capability/environment/confirmation guard. [Woo reference](https://woocommerce.github.io/code-reference/classes/WC-Order.html).

## B. Provider inventory

| Provider/component | Event association and disposition |
| --- | --- |
| TEC/Woo tickets | Item snapshots first; otherwise TEC ticket catalog/sync map and `_tribe_wooticket_for_event` / canonical product links. |
| Express Bar/preorders | Purchase-time `_vms_express_bar_event_plan_id`, as written by the companion's checkout and used by its own queue. Shared SKUs are not globally assigned to one event. |
| Native tables/fire pits/pool/add-ons/entitlements | Canonical Event Plan/TEC item snapshots or product relationships; existing roles and sync-map catalog retained. No inference from product names. Numbered/assigned ticket metadata remains untouched. |
| Other event-linked Woo purchases | `_vms_event_plan_id`, `vms_event_plan_id`, `_vms_tec_event_post_id`, `_vms_tec_event_id`, `_tribe_wooticket_for_event`; canonical product relationships also work without an allowed ticket-role name. |
| Event-linked fee/shipping lines | Explicit item snapshot, with Woo refund APIs receiving the actual item type. |
| Nonpurchase admissions/pass reservations | Inspected admissions/pass-claim source represents reservations/discount entitlements, not a Woo payment balance. It does not manufacture a refundable purchase or change admissions state. A paid numbered/reservation product recorded in Woo uses the purchase paths above. |
| Future Woo adapters | Register `plan_keys`, `tec_keys`, or a `match($item, $context)` callback through `vms_cancellation_purchase_providers`. Callback returns null or relationship evidence including `matches`; exceptions are visible and block certification. |
| Future separate-storage adapters | Optional `discover($context)` returns `coverage_complete`, `exceptions`, and `components`. Components require stable `id`, `original_amount`, `remaining`, `currency`; optional name/customer/quantity/prior-refund values are retained. Positive external balances enter the same report as unresolved and require correction or an audited exclusion; core never sends them to Woo as invented order IDs. Zero balances remain explicitly represented. |

Conflicting snapshots are unresolved. An explicit other-event item snapshot outranks a shared product catalog match. Missing/malformed event ownership and unrecognized event/occurrence/reservation metadata surface as unresolved. Product-only evidence clearly belonging to another event remains outside scope. Completely unmarked external data cannot establish event ownership; new components must register their storage/relationship adapter.

## C. Preflight and exclusions

Discovery uses HPOS-compatible `wc_get_orders`, all registered order statuses, deterministic ID ordering and paginated total/page evidence. It no longer requires a TEC link or nonempty ticket catalog to scan purchases. The default work budget is 100 pages of 100 orders (`vms_cancellation_purchase_max_pages`); reaching it without proving exhaustion blocks acceptance. Exact boundaries, duplicate orders, population changes, malformed query results and provider failures are not reported as complete coverage. Merely raising the old 500 limit was not the fix.

Discovery is allowed even when sales-stop fails, so the affected customers are visible. Execution and acceptance still require successful sales-stop. A bar-only plan without TEC linkage can now be discovered, but the retained sales-stop adapter's missing-TEC block remains an explicit operational prerequisite; this task does not invent a new companion sales-control mechanism.

The first automatic cancellation run stops at a persisted proposal. The dedicated report shows every affected order without the previous twelve-order preview limit, original/prior/proposed amounts, expected residual after the proposal, unresolved items and reason fields. Ordinary preflight orders are labeled planned, never failed solely because execution has not started.

Acceptance requires a POST, job-bound nonce, Event Plan edit permission and `manage_woocommerce`; actual execution additionally preserves the existing configurable automatic-refund guard (default `manage_options`). A fresh scope hash rejects a stale browser proposal. A compare-and-set claim prevents acceptance while the job is running. Accepted scope is immutable for that job.

A nonblank item reason creates an explicit exclusion with order/item identity, reason, actor, UTC time and job identity. It can account for an individually unresolved component, but cannot waive incomplete coverage/provider failure. Reasons are retained inside the existing job summary. Source conflicts, non-paid/unverified statuses, existing event credits and unallocated refunds require correction or a reasoned exclusion. No unknown balance silently disappears.

## D. Execution and reconciliation

The accepted snapshot supplies the exact execution set. Before transport, current order scope is compared with the raw accepted order snapshot. Changed lines/balances block that order. Other unaffected accepted orders can still succeed; the result remains partial/attention-required where needed.

Each order/event has a Woo CRUD metadata intent, written and read back before transport. A succeeded intent prevents another transport; an initiated/uncertain intent prevents blind replay, including across replacement cancellation jobs. Woo API errors and exceptions remain visible. This deliberately requires manual Woo/gateway investigation for uncertain outcomes; it is not a claim of gateway exactly-once delivery. Successful and failed order results coexist in the report.

After transport, independent discovery reads current Woo facts and joins by stable identity. Actual additional refund is the change in itemized refunded value from the accepted baseline, not the amount the API was asked to return. Reconciliation includes disappeared lines, newly discovered lines/orders, unresolved ownership, excess/short refunds, exclusions and remaining event-linked balances. New components contribute to residual totals rather than merely adding a log warning. Totals are separate per currency.

Synthetic #7555-style evidence: fictional Order **97555**, two GA tickets totaling $20 and six Corona preorders totaling $36. Normal execution discovers/refunds both ($56), with zero residual. A fake refund provider intentionally omitting the preorder records $20 actual, **$36 unexplained residual**, and attention-required status. These prices and records are invented. Historical real Order #7555 remains absent and unverified locally.

## E. Persistent report and statuses

Route: `admin.php?page=bvm-cancellation-report&event_plan_id=...`, with optional job/run identity. Cancellation save redirects and the existing dedicated live-refund action redirect to this report. Event Plan has a clear report/preflight link; prior jobs and runs are reopenable. Order links use Woo's edit URL and order-edit permission, supporting HPOS. Back to Event Plan is always present.

The report shows event/date, linked event, initiating actor/time, job/run history, overall status, affected orders/customers, expected/actual/excluded/residual totals, success/partial/failure counts, component outcomes and actionable exceptions. Exception orders precede settled ones. UI fixtures verify 1440px and 390px layouts; large tables scroll within their container.

Status semantics:

- `planned`: scope captured, operator acceptance outstanding.
- `running` / report `processing`: existing worker claim is active.
- `completed` in legacy job state, `completed_successfully` in financial report: reconciliation succeeded.
- `completed_with_exclusions`: all remaining balances are deliberate audited exclusions.
- `attention_required`: unresolved, partial, failed, changed, incompletely scanned or uncertain outcomes; upstream/notification errors are also prominent.
- Existing `failed` and historical `completed_with_errors` remain recognized.

Historical jobs without canonical evidence are explicitly coverage-unverified. Reads never reclassify or backfill them. GET renders stored report facts; preflight GET performs fresh read-only discovery. Reopening never refunds or emails. Reports are run snapshots, not a claim that gateway settlement or later manual adjustments were freshly verified on every view.

## F. Verification

`php tests/cancellation-purchase-completeness.php <evidence-directory>` passes **56 deterministic checks**, with PHP warnings promoted to exceptions. It invokes actual BVM helpers, acceptance, runner, provider filters and report renderer with in-memory Woo/WordPress substitutes. No WordPress bootstrap/database/real payment or mail function is loaded. Exact check names are in `test-results.json` beside this report.

Coverage includes tickets; synthetic #7555 mixed and deliberately omitted refunds; multiple add-ons; partial amount/quantity/taxes; zero quantity with residual money; zero values; exclusions; unknown/malformed/conflicting ownership; shared other-event SKU; bar-only/no catalog; provider failures; Woo failures; mixed orders; repeated runs; uncertain attempts; fresh/new/changed components; excess refunds; >500-order pagination and exact exhaustion; >12 affected orders; fees/shipping; external provider components; stale acceptance; concurrent acceptance; permissions; persistence/historical selection; Event Plan links; redirects; report/preflight/no-job reads; credit and unallocated-refund conflicts; sales-stop failure.

Additional passed checks:

| Command | Outcome |
| --- | --- |
| `python3 tests/cancellation-report-browser.py <evidence-directory>` | 4 fixture/viewport combinations; no external requests, correct form owners and exception display, no page overflow. |
| `php tests/reporting-provider-contract.php` | PASS. |
| `php tests/ticket-integrity-scan-lock.php` | PASS. |
| `php tests/private-file-operations-boundary-remediation.php` | PASS. |
| `php tests/financial-authority.php` | 234 assertions PASS. |
| `php tests/event-command-center-context.php` | 35 assertions PASS. |
| `php tests/event-command-center-2-fixtures.php <disposable-output>` | 103 semantic assertions PASS. |
| `php -l` on six runtime files | PASS. |
| `git diff --check`; own diff inspection; six-file sibling parity | PASS. |

Full WordPress/SQL reschedule and staffing integration suites were not run against normal-local data. Their relevant source is unchanged; this is source/synthetic verification, not an operational promotion receipt.

## G. Changed sources

| File | Purpose |
| --- | --- |
| `includes/core/cancellation-purchases.php` (new) | Canonical scope/providers, money projection, acceptance/exclusions, transport intent, reconciliation and preflight/report projections. |
| `includes/core/cancellation-adapters.php` | Delegate matching/discovery/execution; preserve policy, sales-stop, guard and notification machinery. |
| `includes/core/cancellation.php` | Preserve prior jobs, new statuses and report snapshots, discovery visibility despite sales-stop failure, preserve execution prerequisite; initialize previously undefined cancellation vendor-message meta key encountered by strict tests. |
| `includes/core/load.php` | Load dedicated admin report. |
| `includes/admin/cancellation-report.php` (new) | Secure report/acceptance routes, full order/component UI, read-only history and navigation, redirects. |
| `includes/cpt/event-plans.php` | Historical report entry; remove view-time cancellation-envelope backfill; preserve operator-review flag; dedicated live-action redirect. No communications editor/form changes. |
| `tests/cancellation-purchase-completeness.php` (new) | Deterministic integration coverage and synthetic HTML fixtures. |
| `tests/cancellation-report-browser.py` (new) | Offline fixture layout/form/exception verification. |
| `docs/cancellation-phase3a/*` (new) | Architecture, exact tests and preservation evidence. |
| `docs/wporg-remediation-ledger.md` | Bounded source/disposable task receipt. |

## H. Data/schema and retry behavior

No table, schema-version, option, cron, activation or historical-data migration is introduced. Existing Event Plan `_vms_cancel_job_summary` gains versioned `purchase_preflight`, `purchase_report`, per-run report snapshots and a flattened `previous_jobs` collection. Existing job state/review meta uses the documented additional states. New fields are written only by explicit workflow actions. Old records remain readable without migration.

Woo order metadata gains `_vms_cancel_purchase_attempt_` plus `md5('event:' . event_plan_id)`, accessed through Woo CRUD rather than postmeta. This stores intent/result identity, time, amount and refund ID; Woo refund objects still own monetary facts. Prior old `_vms_cancel_refund_*` markers are neither deleted nor used as proof of unsettled money.

Successful intent replay does not create another refund. Failed/uncertain transport requires manual reconciliation, and newly changed accepted scope is blocked rather than silently repriced. Positive separate-storage provider amounts never use Woo transport. This is conservative refund safety, not automated recovery of every historical gateway ambiguity.

## I. Acceptance recommendation

**Ready for local acceptance.** The known ticket-only omission class is repaired and deterministically detected after execution. The work is an uncommitted isolated candidate, not locally accepted or installed.

Local acceptance should exercise the actual WordPress/Woo gateway-sandbox UI and roles, realistic order volume, taxes/refund objects, interrupted persistence, existing cancellation history and companion sales-stop prerequisites with disposable data and mail blocked. The current evidence does not establish production gateway behavior or authorize promotion. Frozen public BVM 1.2.0 remains unchanged.
