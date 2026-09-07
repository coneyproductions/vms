# Event Command Center 2.0 — consolidated implementation report

Source/disposable testing only. September 7, 2026 UTC. No normal-local promotion.

## A. Source authority

Base: `work/bvm-authority-integration` at `5200bebbf0eb12c70c2fb56e367ff678b1f32853`, the exact accepted promotion source. New branch: `work/event-command-center-2`. Worktree: `/private/tmp/bvm-ecc2-20260907/packages/vms-github-reconcile`. Isolated sibling: `/private/tmp/bvm-ecc2-20260907/vms`.

The original working folder is the historical dirty tree at `79da784f5bfcc66bd058c0a7f54e08d7b15bb5d5`; its initial preflight warning matched the expressly excluded historical worktree. The accepted authority and newly created task worktree both passed clean preflight before edits. No historical dirt was imported. The promotion report, integration consolidation/design brief/ecosystem audit and relevant remediation ledger evidence were reused.

## B–D. Audit, responsibility and final information architecture

The complete [baseline inventory and route audit](ia-audit.md) records each old section/control, source, authority, prominence, actionability, duplication and disposition. Main issues: four visually equal overview cards, repeated health/ticket/financial data, assigned staff appearing fully covered, weather availability standing in for risk, misleading admitted/ticketed totals, dense promotional controls and missing report access.

Event Plan owns configuration, staffing requirements and management, schedule/compensation editing and planning inputs. ECC owns operational evidence, exceptions and action destinations. Dedicated reports own dense printable/admissions/accounting output. Portals own authenticated individual responses and private documents.

The redesigned page has:

1. Event command bar: identity, date/show time/venue/status, Today/upcoming/past orientation, explicit readiness and ticket freshness; three principal actions including Event-Day Guest List.
2. Ranked attention/readiness with visible reason and action for each condition.
3. Audience/admissions alongside staffing, preserving independent populations and lifecycle states.
4. Show schedule alongside cached weather risk.
5. Financial snapshot grouped by transaction, forecast/planned and reported/manual basis.
6. Talent/vendors/documents alongside customer communications and collapsed marketing detail.
7. Available show-day tools/reports; populated notes; collapsed recent activity and existing promo controls.

The Event Plan module hub, direct ECC routes and existing write handlers remain compatible. The promo manager is retained as a collapsed canonical workflow because it currently has no equivalent editing destination elsewhere; no duplicate editor or new write path was created. Moving it to Event Plan requires a separate parity change.

## E. Explainable readiness

Priority is Blocked → Needs attention → Incomplete data → Ready. Each non-ready classification has an on-page reason and action. There is no score or percentage.

| Classification | Deterministic evidence |
| --- | --- |
| Blocked | Existing critical setup/integrity alerts; canonical staffing conflicts or critical required positions open. |
| Needs attention | Proposed assignments, required positions open, canonical soft overlap warnings, review/public-listing/lineup/ticket warnings, elevated cached weather band, pending/failed/uncertain occurrence-change notices, pending promo review, active agreement packet exceptions. |
| Incomplete data | No verified staffing scope, explicitly unwired headcount context, unknown legacy lifecycle responses, stale/pending/unavailable tickets, enabled Weather with no current assessment, unavailable transaction receipts. |
| Ready | None of the conditions above. Explicitly limited to current checks, not safety clearance or finalized accounting. |

Optional Weather/Agreements absence is shown honestly and does not become a mandatory add-on requirement. Optional missing promo assets or intentional social suppression do not block show-day readiness. Future staffing positions remain distinct from currently required positions. Past events change orientation to review/closeout without inventing a new lifecycle or suppressing unresolved evidence.

## F. Audience and admissions

Paid tickets, comp/free tickets and paid inventory remaining are separate. Comp basis remains transaction records, operator-reported, forecast, or unavailable. Valid zero remains numeric zero; unavailable never becomes zero. Stale numeric cache can remain visible with the accepted stale label. Removed the arithmetic “total admitted/ticketed” implication: no check-in or deduplicated attendance population is invented. Canonical Event-Day report and admissions/check-in workflow own those details. Ticket source and meaningful freshness are retained without inventing a global “last synced” timestamp.

## G. Staffing

Uses the existing shared snapshot unchanged: planned positions, assigned total, required now, proposed/tentative, confirmed, open planned, open required now and conflicts. Declined/canceled do not cover roles. Hard conflicts block; soft cross-event overlap warnings need review even when the local assignee is confirmed. Explicitly unwired headcount scope is incomplete. Action opens the existing `#vms-ep-staff-headcount-summary` on this Event Plan. No ECC status writes, lifecycle service, rollup or calculation was added.

## H. Weather

Weather Risk 0.1.12 is optional. The adapter reads `VMSX_Weather_Risk_Advisory_Engine::get_snapshot` only, checks capability/settings/event identity/window/provider response and uses the actual weather band/reason. It never calls refresh or HTTP transport. Cache-age presentation follows the accepted scheduler cadence; expired event windows are stale. Displays risk, primary concern, window, freshness and scoped details link. Module absence, disabled state, malformed/mismatched cache and provider exception fail unavailable.

## I. Financial

The accepted financial snapshot and shared display vocabulary remain the only amounts/calculations. Current transactions show ticket receipts and explicitly provisional contribution; forecast/planned shows modeled revenue/margin and planned/committed labor; reported/manual shows operator concessions/costs. Planned and committed labor overlap and are never added. Payroll actual/final accounting remain unavailable in the provenance disclosure. When every financial value is unavailable, one compact empty state replaces a list of empty metrics. Profitability links to the real all-events report, explicitly labeled because it has no exact event-ID filter.

## J–L. People, communications and tools

People summarizes existing assignments without turning assignment into signed agreement or payment evidence. Optional Agreements APIs supply active packet status/current terms and review destinations; acknowledged current packets are calm, stale/pending active packets need review, voided/superseded history is excluded. No required-document policy or settlement status is invented. Existing private tech documents retain their permission-checked secure URLs.

Customer communications use canonical occurrence history, ledger identity checks, summary and unfinished-attempt classification. Duplicate operation IDs are deduplicated; pending/failed/uncertain and missing affected-ledger cases are visible. Resolved sent/manual/excluded states are not relabeled as delivered marketing. Social/marketing workspaces are independent and shown only when registered.

Tools include canonical Event-Day report; event-scoped admissions/check-in; registered all-events profitability; permission-checked tech documents; event-enabled Express Bar with Woo/capability/registered-route gates; and existing public event/ticket links. No unverified Ops Console or public door preselection route was invented. Empty Sponsorship and other optional add-on cards are omitted.

## M. Event-Day report integration

ECC calls the existing `bvmgr_event_day_report_url($plan_id)` after valid Event Plan/type and admission-management capability checks. Header and tools use the same URL. Existing `admin-post.php?action=vms_event_day_report&event_plan_id=ID` and event-scoped nonce/action/handler are unchanged. Admissions link opens this Event Plan's `#vms_guest_list_comp_admission`. No report-generation logic, audience calculation or security handler was duplicated.

## N–O. Conditional display, accessibility and responsiveness

No empty notes/activity card; marketing/promo/provenance are native keyboard disclosures; optional tools require actual availability. Minimal financial state is compact. Weather/documents/communication gaps use explicit bounded unavailable copy. Show schedule uses actual saved event/lineup anchors; no unsupported separate doors-time field was fabricated.

Real ECC and BVM admin-shell styles are tested inside a minimal WordPress fixture wrapper at 1440, 1024 and 390 pixels. All 16 cases stack without horizontal page overflow. Headings and text communicate status independently of color. Native links/disclosures support keyboard interaction. Shell specificity defects found during acceptance were fixed for event-title size, button targets and visible 3px focus outline. Selected ECC text contrast ratios on white are 6.30:1–15.89:1. This is targeted acceptance, not a full WCAG certification.

## P. Exact changed files

Runtime:

- `assets/css/vms-event-command-center.css`
- `includes/admin/event-command-center.php`
- `includes/admin/event-command-center-context.php` (new read-only optional adapters)
- `includes/admin/event-command-center-dashboard.php` (new presentation/readiness)

Tests:

- `tests/event-command-center-2-fixtures.php` (new)
- `tests/event-command-center-context.php` (new)
- `tests/p0-source-consistency-repair.php` (renderer-location assertions; existing dynamic behavior assertions retained)

Documentation:

- `docs/event-command-center-2/ia-audit.md`
- `docs/event-command-center-2/consolidated-report.md`
- `docs/event-command-center-2/validation-summary.json`
- `docs/wporg-remediation-ledger.md`

Only the four runtime files are synchronized to the disposable sibling. Existing mirror/sibling structural differences are preserved. No version bump.

## Q. Visual evidence

Evidence root: `/private/tmp/bvm-ecc2-20260907/evidence/ui`. Nine screenshots use synthetic data and the actual production renderer/CSS; they do not claim normal-local browser acceptance or a live report nonce. Representative [show-day desktop](/private/tmp/bvm-ecc2-20260907/evidence/ui/02-show-day-1440.png), [show-day mobile](/private/tmp/bvm-ecc2-20260907/evidence/ui/02-show-day-390.png), [staffing blockers](/private/tmp/bvm-ecc2-20260907/evidence/ui/07-staffing-conflict-open-1440.png), [minimal mobile](/private/tmp/bvm-ecc2-20260907/evidence/ui/15-minimal-optionals-unavailable-390.png).

Visual review confirms event/report priority, calm healthy state, text-labeled exceptions, distinct financial groups and coherent stacking. Initial fixture route labels and unsupported sample tools were corrected to match real APIs before final capture. Final test receipts follow below.

## R–T. Validation and containment

Final machine-readable [validation summary](validation-summary.json) retains each final official/additional scenario result and full-report hash/link, resource guard, focused suite results, UI receipt and four runtime hashes. Detailed private logs are in `/private/tmp/bvm-ecc2-20260907/validation/evidence`; [validation narrative](/private/tmp/bvm-ecc2-20260907/validation/VALIDATION-REPORT.md) and [complete cleanup proof](/private/tmp/bvm-ecc2-20260907/validation/evidence/final-closeout.json) retain setup diagnostics and final outcomes.

| Gate | Final result |
| --- | --- |
| PHP 8.3.30 / JS | All six changed PHP files lint clean; unchanged Event-Day JavaScript syntax passes. No new JavaScript is introduced. |
| ECC semantic/component | 103 assertions, 16 requested representative event states. Includes soft cross-event staffing overlap, unknown headcount wiring and all-unavailable financial empty state. |
| Optional context | 35 assertions: genuine provider absence, capability/type/scope, real communication ledger helpers, cached Weather states/exceptions/no refresh, elevated band without reason, agreement terms/history, private docs and conditional tools. |
| Browser | 248 assertions; 16 states × 1440/1024/390; nine screenshots; 18 focused controls with solid 3px outline; Enter opens/closes disclosures; zero overflow/tiny targets/JS errors/external page requests. |
| CSS | Production CSS parsed/applied by Chromium with actual shell styles; computed hierarchy/focus/target behavior and selected contrast checked. No repository CSS linter is configured; optional Python tinycss2 is absent. |
| Focused foundation | 36/36 suites pass: P0/ticket authority, Staffing/Financial, provider, Event Plan and relevant Calendar/Weather/Commerce/Data Tools/DRM/Vendor Portal contracts. Exact suite names retained in JSON. |
| Financial / cross-domain | Financial 234 assertions with Woo and 234 without; shared Staffing–Financial contracts 127. Four real provider/load-order modes × 78 pass. |
| Real database Staffing | Gate 176; lifecycle/handler 102; genuine concurrency 86; deadlock/retry 41; Staffing–Financial SQL 33; additive migration/idempotence pass. |
| Event-Day and ticket routes | Actual registered report handler 7/7: no capability, missing ID, wrong post type, missing/invalid/cross-plan nonce, and successful real report dispatch. Discoverability 33; Event Plan legacy ticketing, ticket UI, checkout safety and date-derived-state suites pass. |
| Full ECC runtime | 9 assertions pass for actual synthetic Event Plan build and complete renderer with no PHP warnings, real scoped report URL and omission of privileged tools for anonymous context. |
| Official five | 19/19 PASS on exact final runtime source. |
| Additional ecosystem | 52/52 PASS on exact final runtime source. |
| Resource supervision | 16 real-process fault cases pass. Final guard `ok:true`, no owned process or database residue, database/socket/datadir removed. |
| Review/whitespace/parity | Own source diff reviewed; working/staged diff checks required before commit; four runtime files match isolated sibling. |

Accepted companion coverage retains Calendar Feeds 0.1.4, Weather Risk 0.1.12, Commerce Discounts 0.2.13, Data Tools 0.5.55, DRM Intake 0.2.4, Router 0.1.3, Bridge 0.2.2, Sponsorships 0.1.28 and the matrices' other integrations. Agreements-specific adapter contracts and original installed source preservation are additionally checked. Assertions overlap across suites and are not a count of unique scenarios.

Containment uses a fresh guarded MySQL 8.0.35 instance with networking disabled, unique socket/database and separately copied source WordPress. Harness fields named “normal” refer to that disposable source fixture, never normal Local. HTTP-before-transport and independent-process 503 boundaries, mail blocking, residue canaries, runtime/database removal, guard removal and lock release pass. Final MySQL error-log peak is 1,805 bytes against 8 MiB; minimum disk guard 3 GiB; free disk after final run 191,056,850,944 bytes. Browser server port 8769 is no longer listening and browsers closed. All validation source/runtime replicas were removed after final 798-file parity proof. Retained artifacts are the task worktree and required isolated sibling plus private tooling/evidence; no copied WordPress/database runtime remains.

Initial failures were fixture/setup findings: missing copied fixtures/dependencies, first-activation redirect, late admin/CPT bootstrap, site ticketing default, and lifecycle migration occurring after date-writing ticket fixtures. Their diagnostic logs remain; corrected disposable setup ran unchanged product/test assertions successfully. The P0 static test was updated solely because renderer code moved files. Early cleanup inspections requiring process-list permission were followed by independent process-group exit proof and removal of their exact owned datadirs. No product guard was bypassed or weakened.

The final Weather fallback wording correction was independently tested and both matrices rerun against its exact source. Historical payables strict-JSON fixture debt remains unavailable and explicitly unpassed; no replacement was fabricated.

## U–V. Local commit and promotion requirements

One local task commit contains this report and the validated source. Its exact SHA is supplied in the final delivery and `/private/tmp/bvm-ecc2-20260907/evidence/local-commit.json` (a commit cannot embed its own hash). No push. This source candidate requires a separately authorized controlled normal-local promotion: verify exact commit/file manifest, preserve active source backup, confirm accepted 5200beb authority and companion versions, synchronize the four files including both new includes, and perform actual admin/navigation/report acceptance for representative saved events. No new schema migration or activation change is introduced. Restore those four runtime paths from the pre-promotion source backup if acceptance fails; do not roll back accepted Staffing/Financial authority.

## W–X. Limits and follow-up recommendations

- Browser fixtures exercise actual rendering with synthetic payloads and a minimal WordPress wrapper; normal Local was not booted or modified.
- No separate canonical doors field was found in this ECC/Event Plan path; saved schedule anchors remain authoritative.
- Check-ins/reservations remain in their canonical report/workflow, not duplicated into a speculative attendance aggregate.
- No final payroll/accounting authority, mandatory agreement-requirement policy, unified marketing delivery ledger, exact-ID profitability filter or reliable event-scoped Ops Console route was invented.
- Follow-up: Event Plan visual cleanup and promo-manager relocation with parity; unified report launcher; exact event filtering in profitability/public door selection; explicit show-day mode; richer Ops Console/service-request integrations; authoritative required-document policy; existing ecosystem and payables-fixture debt.

## Y. Preservation

Before/after file manifests cover original dirty source (1,783 files), installed canonical BVM (393), legacy vms (408) and thirteen companion trees (581): 3,165 files total. Original branch/HEAD/status/index and protected stash are included. Initial and post-UI manifests are byte-identical SHA-256 `89e6027d2bab73406e12f0ef00a94acb63a92e5b0e3b2ea227ac873b4d68a175`. Protected stash remains `d08e726804712dc233f0e37b217abd6389963863` with exact message `WPORG-16D preserve unrelated sidebar+doc work`.

Normal Local was not booted or connected to by this task; no database-preservation claim is made about unrelated external activity. Authorized shared Git metadata gains this branch/worktree/local commit, but original working contents/index/branch are unchanged. No protected stash operation, push, deployment, staging/production/SSH, external communication or business action occurred.

## Z. Final classification

**EVENT COMMAND CENTER 2.0: READY FOR CONTROLLED LOCAL PROMOTION.**

Implementation and current acceptance gates pass within the source/disposable boundary. Normal Local remains unchanged; promotion requires its own explicit authorization.
