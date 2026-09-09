# Phase 4A real Google acceptance — 2026-09-08

**ACTIVE / ACCEPTED LOCALLY**. Real OAuth and Calendar acceptance passed on the disposable localhost site, including the user's final same-account reconnect. The corrected runtime was then promoted to normal-local under a separate verified source rollback. Normal-local has no Google credentials or connection state; the connected test account remains confined to the disposable site.

This acceptance supersedes the original candidate's pending real-account gate. Original candidate `77805caf5494481ac01323b3f68f97a16a2475fe` remains immutable. The narrowly necessary follow-up is the commit containing this report on `work/google-phase4a-live-corrections-20260908`; its exact accepted SHA is recorded in the private `live-google/closeout.json` and acceptance report. Phase 4B has not begun.

## Environment and boundaries

The fresh LocalWP site is `http://localhost:10014`, with its own database/socket and synthetic BVM fixtures. BVM's exact configured callback is `http://localhost:10014/wp-admin/admin-post.php?action=bvmgr_google_callback`. It is a minimal 52-table BVM site, not a copy of normal-local data. Private configuration stays outside the web root. Real mail remains blocked and external HTTP is limited by the disposable helper to the required OAuth token and Calendar API operations. A temporary CLI filter allowed external-edit/deletion acceptance only after checking the exact synthetic event's ownership; the permanent transport helper was not broadened.

The user completed consent and same-account reconnect in the actual BVM UI. The fresh token exchange returned HTTP 200 and exactly `openid` plus `calendar.app.created`. Safe observers recorded operation/method/status and a scope-match boolean only. Credential values, authorization codes and raw token responses were not emitted. A value-based scan of evidence and Local logs found no configured credential/current-token matches. The temporary observer, read flag and test HTTP session were removed; the permanent mail/HTTP/origin containment remains.

Only one dedicated secondary **BVM Tasks** calendar was created. Its identity is retained in private `calendar-result.json` and `context.json`. Repeated setup, worker runs, Sync Now and reconnect retained that identity. There was no personal-calendar enumeration or access to unrelated calendars/events. All real event requests used deterministic IDs of the owned synthetic fixtures.

## Observed correction

Google returned timed events using equivalent UTC dateTime strings while retaining the recorded IANA zone, and omitted the default `transparency=opaque`. The original strict string comparison therefore issued six unnecessary PATCH requests across two unchanged syncs. It did not create duplicate events.

The only runtime correction is in `google/sync.php`: compare valid offset-bearing RFC3339 dateTime values by instant, and treat an absent remote transparency as Google's opaque default. IANA zones, all-day/timed shape, ownership, explicit transparency and every other owned field remain strict. Unowned preferences remain preserved. No OAuth, task authority, schema or transport implementation changed.

After correction, the same two live syncs performed two calendar GETs and eight event GETs with **zero PATCHes and zero inserts**. Sixteen permanent focused assertions cover equivalent representations, real differences, fractional precision, invalid inputs, DST folds, timezone/ownership checks and unowned preferences. The complete deterministic suite was rerun against the corrected source.

## Real acceptance results

| Case | Result and durable receipt under `live-google/` |
| --- | --- |
| Calendar creation and idempotence | One calendar POST; repeated workers and reconnect retained the original marker/identity. `calendar-result.json`, `reconnect-final-result.json`. |
| Scheduled task | Exact start/end instants and America/Chicago zone verified against real API response. `timing-result.json`. |
| Date-only task | Correct all-day date and exclusive next-day end; transparent. `timing-result.json`. |
| Due-only exact time | Exact deadline instant, transparent one-minute marker with explicit label. `timing-result.json`. |
| Start-only schedule | Exact start, transparent one-minute marker identifying unspecified end. `timing-result.json`. |
| Unscheduled / timing review | No invented Google events; respective local eligibility/review states preserved. `timing-result.json`. |
| Update, completion, reopen, cancellation | Same deterministic event ID, current canonical timing and documented lifecycle labels/availability. Repeated identical task save retained revision. `lifecycle-result.json`. |
| Reassignment | Former mirror retired, new unconnected assignee respected, reassignment back restored the same original mirror. No second real Google account was used. `lifecycle-result.json`. |
| Repeated sync / retries | Corrected unchanged runs caused no writes; three authenticated BVM Sync Now POSTs completed with clean local redirects. One fixture-only injected 503 produced retryable state; real retry updated the same event without changing canonical authority. `idempotence-corrected-result.json`, `ui-sync-result.json`, `refresh-retry-result.json`. |
| External edit | Independent real Google PATCH changed an owned title and unowned color/reminders. BVM task hash stayed unchanged; reconciliation restored the title and retained preferences. `external-result.json`. |
| External deletion | Independent DELETE produced a real cancelled tombstone with ETag. BVM retained its task and restored the same deterministic Google ID to confirmed state. `tombstone-observation.json`, `tombstone-reconciliation.json`. |
| Refresh / reconnect | Forced local cached expiry triggered a successful real refresh. User-completed fresh reconnect issued the exact scopes and retained calendar/account identity. Post-cleanup sync created no mirrors. `refresh-retry-result.json`, `reconnect-final-result.json`. |

External edits/deletions used independent API calls against verified fixture IDs rather than a Google Calendar browser client. Browser automation was unavailable. API responses establish event dates, instants, zones and lifecycle; no visual Google Calendar-client presentation claim is made. Destructive real grant revocation was not performed; revoked/invalid grants, auth-required state and reconnect recovery are covered by the deterministic suite. These are disclosed test-method limits, not claims of additional live cases.

## Read containment and regressions

Connected disposable acceptance made 18 authenticated GETs across Staff Tasks, My Tasks, Google status/history, review-task detail, Event Plan edit and ECC. All 52 table row/schema manifests stayed identical; there were zero HTTP attempts and zero SQL mutation attempts during the window. The acceptance helper suppresses two existing diagnostic option writes, and the read window additionally suppressed the ordinary navigation preference and edit-lock metadata writes. This proves task/Google read containment under the documented platform containment, not that those unrelated platform preferences never write during ordinary use.

After normal-local promotion, 33 repeated guarded renderer/projection reads preserved **all 246 tables**, options, private files and binlog exactly. Normal legacy tasks **7 and 8** remained timing-review blocked. The normal module loaded from installed source, was unconfigured, and created no Google state. Temporary normal read guards were removed. Receipts: `normal-readonly/result.json`, `read.php.stdout`, `cleanup.json`.

The corrected full Phase 4A real-SQL/fake-provider suite passed **451 assertions** (369 Google workflow plus 82 foundation), with **16 additional** focused representation assertions. The supervised 95-table environment proved process exit and database-residue cleanup. Fresh real-SQL regressions passed Phase 3D 205, staffing lifecycle 104, concurrency 86, deadlock 41, financial 33, idempotent migration/history preservation, Phase 3C notifications 132 and deadlock/recovery 41 with 44 unique committed identities. The 15 selected prior Phase 3A/3B/private-document/Event Plan/ECC/reporting/security suites and three further task/ticket suites passed.

Three previously documented structural baseline failures remain: `staff-tasks-overrides-json-remediation`, `staff-tasks-repository-sql-remediation`, and `ticket-integrity-query-filter-boundary-remediation`. They are not reported as passing. No new regression failure remains. Supervisor receipts and `regressions/results.json` retain exact outcomes; earlier failed harness and original-candidate attempts are retained separately. The initial timing harness compared dateTime strings too strictly and was corrected to compare instants; it was not a timing-payload defect.

## Cleanup, promotion and rollback

All six synthetic task rows and their history, two synthetic staff posts, the synthetic Event Plan, additional WP user, fixture notifications/staffing rows and temporary staff linkage were removed. Four active Google fixture events were deleted and verified non-active. All 50 disposable tables other than options/usermeta returned to exact original row hashes/counts. Expected connection/calendar/cache/session/navigation bookkeeping remains in options/usermeta. No synthetic mirror records remain. The connected test account and dedicated empty calendar are retained. Receipts: `cleanup-result.json`, `cleanup-verify-result.json`, `helper-cleanup.json`.

Before promotion, the complete normal plugin source and all preserved authorities matched the live baseline, and the seven target paths matched accepted Phase 3D `f5a3df7c600ea1d4c7946ae1adc4072512f05ccc`. A new owner-only normal rollback captured the actual installed loader and recorded the six previously absent Google files. Only these seven runtime paths were installed; the loader was replaced last. No database migration, activation change, credential copy or connection-state copy occurred. All other normal files remained byte-identical. Source parity covers 413 normal files and 417 disposable files against the isolated source, preserving existing tree-specific differences.

Private `normal-rollback/restore.py --check-only` validates installed hashes/backups; `--restore` deliberately restores the original loader first and removes only the six matching new components. It refuses drift. It does not alter any database, task history, Google identity or calendar. Do not restore read snapshots over later work. See the private `normal-rollback/RESTORE.md`.

Historical dirty workspace/index/HEAD, inactive legacy VMS, all 13 companions and protected stash `d08e726804712dc233f0e37b217abd6389963863` remain unchanged. Frozen release HEAD remains `04caf99ae7a98ac507c067a7535d2a672efa457b`, clean; both qualified ZIPs remain 396 files at SHA-256 `2c2a488395d32d419741a99a1211c18fe649f73e567c1cff8de749d44d08c8e3`. No push, package/ZIP creation, tag, production/staging modification, WordPress.org action or message to others occurred.

## Extra OAuth attempt

The first diagnosed failure was the disposable blanket transport guard, which blocked token exchange after the one-use OAuth state was consumed. The user also observed an additional failed fresh attempt after correction. Retained evidence does not contain its Google HTTP/error code, so its exact cause cannot be determined retrospectively. Expired/reused code, replaced state/session and transient transport/provider failure cannot be distinguished; none is asserted. The unchanged OAuth implementation subsequently completed consent, real refresh and fresh same-account reconnect. There is no evidence of a persistent OAuth payload defect. The Calendar comparison correction is unrelated. Details: `oauth-attempt-characterization.md`.

Durable evidence root: `/Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-4a/live-google/`.
