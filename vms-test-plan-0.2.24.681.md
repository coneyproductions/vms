# VMS 0.2.24.681 Test Plan — Relative Ticket Sale Dates + Event-End Sales Guardrail

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.681`.
3. Confirm `VMS_VERSION` reports `0.2.24.681`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.681`.

Expected: all markers match `0.2.24.681`.

## B. Relative Early Price End

Use an Event Plan dated `2026-06-12` from `7:00pm` to `9:00pm`.

1. Open the Ticketing v2 config.
2. On General Admission, set:
   - Regular price: `20`
   - Early price: `15`
   - Early ends → Ends days before event: `31`
3. Confirm the Early ends datetime resolves to `2026-05-12 7:00pm` in the editor.
4. Save config.
5. Reload the Event Plan editor.

Expected: the relative value `31` is preserved and the resolved Early ends datetime remains tied to the Event Plan start time.

## C. Relative Dates Recalculate on Another Event

1. Save the current ticket config as a template.
2. Apply that template to a different Event Plan date/time.
3. Confirm the Early ends datetime recalculates from the new Event Plan start time rather than carrying the old event's absolute date.
4. Save config.

Expected: the template keeps the relative rule and does not drag stale absolute early-sale dates into the new Event Plan.

## D. Sales End Cannot Exceed Event End

Use an Event Plan dated `2026-06-12` from `7:00pm` to `9:00pm`.

1. In Ticketing v2, manually set a ticket Sales end after the event end, such as `2026-06-13 12:00am`.
2. Confirm the admin guardrail warns that Sales end is after the event end.
3. Click the reset action.
4. Confirm Sales end resets to `2026-06-12 9:00pm`.
5. Save config.

Expected: no saved ticket config keeps Sales end later than the event end.

## E. Commit / Woo Product Sync

1. Run Preview sync on the Event Plan.
2. Commit the sync.
3. Inspect the synced Woo ticket product meta:
   - `_ticket_start_date`
   - `_ticket_end_date`
   - `_sale_price_dates_from`
   - `_sale_price_dates_to`
4. Confirm `_ticket_end_date` is not later than the TEC event end.
5. Confirm early sale price metadata matches the resolved early price dates.

Expected: the public product dates are safe and match the resolved Ticketing v2 config.

## F. Syntax / Packaging

Run:

```bash
php -l vendor-management-system.php
php -l includes/core/registry/constants.php
php -l includes/integrations/ticketing-phase-b.php
node --check assets/admin-ticketing.js
zip -T VMS_681_relative_ticket_sale_dates.zip
```

Expected: all commands pass.
