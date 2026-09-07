# Staffing population retention and migration gate

This policy is for explicit, backed-up maintenance, not automatic cleanup at boot,
activation, migration, or Event Plan deletion. It does not authorize a promotion.

## Meaning and exact predicates

An operational orphan slot is a row in `vms_event_role_slots` for which no
`posts` row has both `ID = slot.event_plan_id` and `post_type = 'vms_event_plan'`.
An invalid rollup root uses the same predicate on `vms_staffing_event_rollups`.
A trashed Event Plan still exists and is not an orphan. A wrong-type post is a
blocking reference error, never permission to reassign or delete the row.
Role identity requires a real term joined to taxonomy `vms_staff_role`.

Slots hold normalized planning state and can also preserve canceled/history
semantics: their presence suppresses legacy fallback. Keep slots for existing
plans, including canceled slots and plans in trash. Keep assignment rows in all
states; the lifecycle migration adds fields but does not merge, delete, backfill,
relabel or impose unconditional `(slot_id, staff_id)` uniqueness. At most one
active (proposed/confirmed) row per pair is supported; terminal duplicate history
is retained and reported. Multiple slots for a role can represent separate shifts
and are diagnostics, not automatically duplicates to delete.

Staffing audit contains self-contained before/after snapshots and later immutable
lifecycle identities. An audit row may intentionally reference a deleted plan.
Do not add an audit-to-current-post foreign key or treat that as an operational
orphan. Never create synthetic actors, dates, plans or assignment history.

Rollups are overwriteable derived caches, not payroll or historical staffing
ledgers. Dirty rows and rows without normalized slots are valid cache shapes for
an existing plan. Cache values must not establish actual work, payments, or a
staffing commitment. Copied event dates/statuses can be the last remaining cached
context after deletion; preserve exact cache bytes in the rollback archive rather
than claiming these obsolete values can be reconstructed from current data.

## Explicit maintenance rules

- **S1 — safe deletion of audit-covered abandoned planning slots:** the referenced
  post is wholly absent, no assignment of any status references the slot, no
  current Event Plan/legacy/structured slot metadata references it, and at least
  one retained valid audit snapshot matches every stored slot column, including
  nulls, identities and timestamps. The audited snapshots contain no assignments,
  and the row has no actual-work/payroll history. Recheck all evidence under the
  maintenance transaction and retain the complete audit table. This is narrower
  than deletion merely because a row is orphaned.
- **R1 — safe deletion of abandoned derived caches:** post wholly absent; row is
  produced by the supported cache writer, not a separate historical ledger; no
  assignment/work/payroll record depends on it. Archive the complete cached
  context. Delete instead of calling the positive-ID cache writer, which can
  recreate an orphan cache. Keep all audit and non-staffing records.
- **Retain/review:** any assignment reference, incomplete archival evidence,
  wrong-type target, unique business-history dependence or unexplained row blocks
  S1/R1. Never infer a replacement relationship. A deterministic repair requires
  independently proven identity of exactly one canonical target.
- No automatic exemption permits a missing-plan slot through preflight merely
  because it is canceled or old. Such historical compatibility needs an explicit
  representable contract and tests first. Existing-plan canceled slots, terminal
  assignment history, audit-only references and trash are already supported.

## September 6 population evidence

Fresh Local inventory: 165 active slots across 55 absent plans, each with roles
Bar (2), Cleanup (3), Ticket Checker (4), needed=1, no pay, no explicit time or
notes, no assignments or Event Plan metadata. All 165 have complete matching
snapshots in 108 retained `event_staffing_save` audit rows; none of those
snapshots contains an assignment. Thus the primary classification is abandoned
planning state with audit coverage, not a claim that every plan was a test.
No role failures, wrong-type targets, trash targets, duplicate role groups,
recoverable mappings or unexplained slot shapes occur in this population.

The 100 missing-plan caches comprise 55 with three matching unassigned slots
(53 dirty / 2 clean-flagged) and 45 with no slots and zero staffing counters
(39 dirty placeholders / 6 clean-flagged empty calculations). All 100 have zero
filled headcount. No cache is a payroll record. No duplicate rollup key occurs.

Systematic causes: matrix saves persist three default planning roles and full
snapshots; Event Plan saves can upsert a dirty cache even with no slots. Custom
staffing tables have no cascading foreign keys and no complete Event Plan
post-deletion cleanup path. Removing posts leaves these custom rows behind;
positive-ID cache writers can also recreate cache-only roots. Creation spans
February–June 2026; cache calculations extend through August. Dates alone do not
prove test provenance or authorize deletion. Audit actions establish matrix-save
origin, not legacy-migration origin. The existing preflight only joined outward
from assignments, so entirely unassigned slots and cache-only roots escaped it.

## Expanded pre-migration gate

The offline CLI and explicit runtime migration use the same SQL-only inspector.
Before additive DDL it checks transactional tables, role taxonomy references,
base columns/identity keys, lifecycle columns/indexes, orphan assignments/slots,
active pair duplicates, invalid slot/assignment values, active assignments on
inactive slots, orphan/malformed/duplicate rollups, revisions, duplicate audit
operations, confirmed windows and overlapping commitments. Failures report
categories/counts and block migration; they never perform cleanup or advance a
version marker. Every reader failure remains fatal.

The gate reports dirty caches, multiple role slots, terminal duplicate pairs and
slots for trashed plans without treating these supported shapes as corruption.
Unknown assignment statuses remain untouched but block operational migration
pending explicit disposition; they are never reinterpreted as completed work.
Missing optional planning times and historical NULL audit identifiers remain valid.

Maintenance/no concurrent uncoordinated writers and post-migration verification
remain prerequisites of a separately authorized promotion. The gate does not
claim foreign keys, a global transactional snapshot across unsupported third-party
writers, or certification of unrelated domains. No automatic deletion hook is
introduced by this change.


## Resumed execution and retention receipt

After disk recovery, every captured population/context row and all 246 table
hashes still matched the stopped task. Under a serializable maintenance
transaction and the staffing advisory lock, exact schemas, rows, typed references,
all Event Plan metadata, role relationships, full audit coverage, options and
legacy slot references were revalidated. S1 deleted exactly 165 audit-covered
abandoned slots. R1 deleted exactly 100 caches: 55 associated with those slots,
39 empty dirty placeholders, and six empty clean calculations. No guessed repair
or cache regeneration occurred. Every audit and assignment row remains intact.

Result: 3,234 slots, five assignments, 1,337 audits and 1,103 rollups. Every invalid
blocker is zero. One legitimate canceled existing-plan slot and 1,064 existing-plan
cached canceled/trash contexts are reported as tolerated historical state; these
counts do not represent tolerated missing-plan roots. The 1,080 dirty valid-root
caches are supported diagnostics, not corruption. Other rows remain under their
normal retention semantics. All original schema/auto-increments and options are
unchanged; only the two authorized table contents differ. Complete exact IDs,
backup verification and disposable/final gate proofs are in the private resumed
report. No normal-local lifecycle migration or BVM source promotion occurred.
