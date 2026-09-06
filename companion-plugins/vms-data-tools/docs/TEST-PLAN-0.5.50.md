# VMS Data Tools 0.5.50 Test Plan

## A. Version

1. Confirm `vms-data-tools/vms-data-tools.php` reports `Version: 0.5.50`.
2. Confirm `VMS_DT_VERSION` is `0.5.50`.
3. Confirm `vms-data-tools/vms-build.txt` reports `Build: 0.5.50`.

## B. Supporting links

1. Open `/wp-admin/admin.php?page=vms-dt-report-single-event&event_plan_id=<event_id>`.
2. Without manually loading row detail first, click:
   - `Website collected`
   - `Square collected`
   - `Gross cashflow seen`
   - `Refunds / overlap held`
   - `Net after known costs`
3. Expected:
   - The page reloads with `vms_dt_detail=single_event_supporting` in the query string.
   - The browser lands on the intended supporting section anchor.
   - The supporting section is visible after reload.

## C. Reconciliation table

1. On the same Single Event page, open the supporting section.
2. Find `Gross cashflow to profitability reconciliation`.
3. Expected:
   - The table shows gross cashflow seen, tips, service charges, website refunds already reflected, Square overlap held/excluded, Square unclassified, Square ignored, other adjustments, profitability basis, known cost total, and net after known costs.
   - The profitability basis row matches `counted_total_cents`.
   - The net row matches the `Net after known costs` card.

## D. Held ticket-like visibility

1. Find `Held ticket-like Square rows`.
2. Expected:
   - The section shows explicit gross, tax, and net totals.
   - The `Refunds / overlap held` card note matches those held ticket-like gross/tax/net figures.
   - Each held ticket-like row shows close time, embedded event date when present, order id, qty, gross, tax, net, source, and why it was held.

## E. Future-event ticket breakout

1. For an event whose time window captures later-event ticket rows, find `Future-event Square ticket rows in this time window`.
2. Expected:
   - The section appears only when such rows exist.
   - It shows the embedded future event date for each row.
   - It shows whether each row is currently counted or held.

## F. Regression checks

1. Open the Single Event page with supporting detail already enabled.
2. Click the same support links again.
3. Expected:
   - Links scroll/open in-page without reloading to a missing target.
   - No duplicate-id anchor confusion occurs.

4. Open the `Match check` section.
5. Expected:
   - `Square collected total` copy mentions taxes, tips, and service charges.

## G. Technical verification

1. Run:
   - `php -l vms-data-tools.php`
   - `php -l includes/admin/page-reporting-module.php`
2. Expected:
   - No syntax errors.

## H. Known local constraint from this patch pass

1. WP-CLI smoke execution may fail if the local site database is unavailable.
2. If that happens:
   - Record the connection failure.
   - Treat syntax validation plus admin-browser verification as the required local checks before staging/production packaging approval.
