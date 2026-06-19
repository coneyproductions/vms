# VMS 0.2.24.668 Test Plan

🚨 **Codex repair/versioning protocol:** If Codex makes even a small directly-related code repair while testing/troubleshooting this build, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision/build notes, this test plan or a follow-up test plan, and the package filename before returning a replacement zip.

## A. Version markers
1. Install the zip as the canonical `vms` plugin folder.
2. Confirm `wp-content/plugins/vms/vms-build.txt` shows `0.2.24.668`.
3. Confirm the plugin header and `VMS_VERSION` report `0.2.24.668`.

## B. Event Command Center source-of-truth ticket snapshot
1. Keep VMS Data Tools active if available.
2. Open **VMS → Event Command Center** for The Tuxedo Cats on staging/production-like data, or the closest locally populated paid-ticket Event Plan if local does not have The Tuxedo Cats.
3. Confirm the Ticket Snapshot no longer shows `0` paid tickets / `$0.00` gross sales when paid ticket rows exist.
4. Compare against **Data Tools → Event Profitability** or the single-event detail source:
   - Paid tickets should match DT paid ticket count.
   - Gross sales should match DT ticket sales basis.
   - Comp/free should include free admission rows when present.
5. Confirm the note shows total admitted/ticketed when paid + comp/free differ.

## B.5. Manual true-comp/admitted note regression
1. On the local substitute plan used for section B, temporarily set `_vms_comp_headcount_true=1` or another small non-zero value.
2. Reload Event Command Center.
3. Confirm **Guest list / comps** updates to the temporary comp/free count.
4. Confirm the Ticket Snapshot renders a note similar to `Total admitted/ticketed: 9 (8 paid + 1 comp/free).`
5. Confirm the total admitted/ticketed value is at least `paid + comp/free`, even when the reporting model's `total_qty` still only reflects paid rows.
6. Cleanup: restore `_vms_comp_headcount_true` to its prior value.

## C. Fallback without Data Tools
1. Temporarily deactivate VMS Data Tools on staging only.
2. Reopen ECC for the same event.
3. Confirm ECC still shows online Woo/VMS ticket revenue from core ticket revenue rows instead of falling back to zero/stale cached stats.
4. Reactivate Data Tools after this check.

## D. Free/children low-inventory suppression
1. Run/open Ticket Integrity for an event with a free/children/qualified ticket row that has low remaining inventory.
2. Confirm the free/children/qualified `$0` row does not create a low-inventory issue by default.
3. Confirm paid ticket rows still create low-inventory alerts when they cross the configured threshold.

## E. Regression checks
1. Open Ticket Integrity daily report / State of the Range if available.
2. Open a normal paid-ticket event with no free tickets.
3. Confirm paid capacity, remaining counts, and sell-through still render normally.
4. Confirm no fatal errors or new warnings appear in debug logs.

## F. Resource-spike diagnostic carry-forward
This section is included because the shared cPanel account showed CPU/SPEED spikes around May 14, 2026 even when no Event Plan was being published. cPanel Process List showed many `lsphp` workers, but HTTP Queries and Database Queries both showed `no results found`.

1. Do **not** assume new spikes are caused only by Event Plan publish.
2. While running staging tests, note whether staging and production share the same cPanel resource pool.
3. If a spike occurs, collect the cPanel snapshot time, Process List, HTTP Queries, and Database Queries. If HTTP/DB tabs show `no results found`, record that explicitly.
4. Inspect likely PHP/process-churn sources:
   - plugin install/activation/update hooks
   - VMS or Data Tools activation/update routines
   - ECC ticket snapshot calculation
   - Data Tools profitability/report calculation
   - WP-Cron
   - Woo Action Scheduler
   - MailPoet jobs
   - TEC scheduled tasks or ticket/order recalculation
   - admin-ajax / Heartbeat loops from open admin tabs
   - cache purge/preload or scanner activity
5. For the next diagnostic patch if evidence remains inconclusive, propose or add a temporary threshold-based **VMS Resource Fingerprint** logger that records only slow/heavy requests and task runs, including:
   - timestamp
   - request URI and admin page/action
   - whether the request is CRON, AJAX, REST, WP-CLI, or normal admin
   - current user ID when available
   - plugin activation/update context
   - ECC/DT report calculation markers
   - WP-Cron due count
   - Action Scheduler pending/running counts if available
   - VMS queues/tasks due
   - runtime and peak memory at shutdown
6. Keep any diagnostic logger temporary, threshold-based, and non-invasive so it does not create more overhead than it diagnoses.


Correction / clarification for ECC ticket snapshot testing:

Do not require “The Tuxedo Cats” to exist on local. That event is the real production symptom/reference, not a mandatory local fixture.

Use test targets this way:

1. Local
   - Use any populated local Event Plan that has real ticket/order fixture data.
   - Current known substitute from prior Codex run: plan 76 “Whitehouse Opry.”
   - Local pass/fail should focus on logic:
     - ECC does not show 0 paid / $0 gross when fallback ticket data exists.
     - ECC agrees with Data Tools when DT is active and has matching report totals.
     - ECC fallback still works when DT is inactive.
     - comp/free headcount causes the Total admitted/ticketed note to appear.
     - free/qualified/children-style tickets do not trigger misleading low-stock alerts by default.

2. Staging
   - The Tuxedo Cats may exist on staging, but staging does not have the real live production ticket sales.
   - Do not expect staging ECC totals to match production TEC/DT sales.
   - Use staging mainly for:
     - page load behavior
     - no fatal errors
     - no duplicate DT install behavior
     - UI rendering
     - low-stock suppression behavior
     - resource-spike diagnostics if plugin install/report loading triggers cPanel activity

3. Production / SR.com
   - The Tuxedo Cats production data is the real-world symptom:
     - production TEC Orders showed 71 completed paid tickets and $1,264
     - ECC incorrectly showed 0 paid / $0 gross
     - DT/profitability appears to have the combined reporting source that ECC should ultimately use
   - Production should be treated as read-only verification unless the operator explicitly approves a live-site test.
   - Do not create, edit, refund, sync, or mutate live production ticket/order/event data during this diagnostic.

Important:
TEC Orders is not the final source of truth for ECC reporting. Data Tools / DT combined online + door-sales reporting should be preferred where available. TEC Orders is only useful as a symptom comparison or secondary reference.