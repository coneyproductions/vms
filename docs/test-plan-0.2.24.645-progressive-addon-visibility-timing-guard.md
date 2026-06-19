# VMS 0.2.24.645 Test Plan — Progressive Add-on Visibility Timing Guard

## Purpose
Verify that Progressive Ticket UI add-ons remain visible on active public events even when the GA sale-window helper is stale/out of sync, while preserving add-on qualification, stock, and checkout validation.

## Setup
- Deploy VMS 0.2.24.645 to staging.
- Use one event that previously failed to show Fire Pits & Tables while another event did show it.
- Test logged out/incognito first.

## Tests
1. Confirm version markers:
   - `/wp-content/plugins/vms/vms-build.txt` reports `0.2.24.645`.
   - Public assets load with the current version/build stamp.
2. Open the problem event logged out/incognito.
   - Expected: Tickets section appears.
   - Expected: Fire Pits & Tables/add-on section appears if the event has mapped add-on products.
3. Expand Fire Pits & Tables.
   - Expected: fire pit/table add-on rows render with names, prices, controls, and images if configured.
4. Try adding an add-on with too few qualifying tickets.
   - Expected: VMS still blocks or warns according to qualification rules.
5. Add enough GA tickets, then add the add-on.
   - Expected: add-on can be added and cart/checkout remains reachable.
6. Compare a known-working future event.
   - Expected: no regression in add-on display or heading/subtext copy.
7. Open an event with no mapped add-ons.
   - Expected: no empty Fire Pits & Tables section.
8. Open a cancelled or clearly past event.
   - Expected: add-ons do not appear as active purchase options.

## Notes
This patch intentionally removes the public add-on suppression tied only to `vms_ticketing_v2_ga_is_on_sale_now()`. Actual purchase safety remains governed by mapped products, stock, qualification, and checkout/add-to-cart validation.
