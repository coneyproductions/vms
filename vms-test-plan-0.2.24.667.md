# VMS 0.2.24.667 Test Plan

## A. Version markers
1. Install the zip as the canonical `vms` plugin folder.
2. Confirm `wp-content/plugins/vms/vms-build.txt` shows `0.2.24.667`.
3. Confirm the plugin header and `VMS_VERSION` report `0.2.24.667`.

## B. Event Command Center source-of-truth ticket snapshot
1. Keep VMS Data Tools active if available.
2. Open **VMS → Event Command Center** for The Tuxedo Cats.
3. Confirm the Ticket Snapshot no longer shows `0` paid tickets / `$0.00` gross sales.
4. Compare against **Data Tools → Event Profitability** or the single-event detail source:
   - Paid tickets should match DT paid ticket count.
   - Gross sales should match DT ticket sales basis.
   - Comp/free should include free admission rows when present.
5. Confirm the note shows total admitted/ticketed when paid + comp/free differ.

## C. Fallback without Data Tools
1. Temporarily deactivate VMS Data Tools on staging only.
2. Reopen ECC for the same event.
3. Confirm ECC still shows online Woo/VMS ticket revenue from core ticket revenue rows instead of falling back to zero/stale cached stats.
4. Reactivate Data Tools after this check.

## D. Free/children low-inventory suppression
1. Run/open Ticket Integrity for an event with a free/children ticket row that has low remaining inventory.
2. Confirm the free/children row does not create a low-inventory issue by default.
3. Confirm paid ticket rows still create low-inventory alerts when they cross the configured threshold.

## E. Regression checks
1. Open Ticket Integrity daily report / State of the Range if available.
2. Open a normal paid-ticket event with no free tickets.
3. Confirm paid capacity, remaining counts, and sell-through still render normally.
4. Confirm no fatal errors or new warnings appear in debug logs.
