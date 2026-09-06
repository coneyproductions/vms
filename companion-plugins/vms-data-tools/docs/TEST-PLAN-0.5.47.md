# VMS Data Tools 0.5.47 Test Plan

## A. Version and pairing

1. Confirm `vms-data-tools/vms-data-tools.php` reports `Version: 0.5.47`.
2. Confirm `VMS_DT_VERSION` is `0.5.47`.
3. Confirm `vms-data-tools/vms-build.txt` reports `Build: 0.5.47`.
4. Confirm paired VMS core is `0.2.24.688`.

## B. DT home page

1. Open `/wp-admin/admin.php?page=vms-data-tools`.
2. Expected:
   - The page loads normally.
   - No PHP memory fatal occurs.
   - A resource fingerprint entry is captured for the DT home request.

## C. Ticket Pace containment

1. Open `/wp-admin/admin.php?page=vms-dt-report-ticket-pace&event_plan_id=<large_event_plan_id>`.
2. Expected:
   - The page loads without exhausting PHP memory.
   - The daily pace table is paginated instead of rendering every row in one response.
   - If PHP memory usage is already high, the page shows an admin warning instead of fataling.
   - DT traces show a `ticket_pace_page` entry with row counts and timing.

## D. Single Event containment

1. Open `/wp-admin/admin.php?page=vms-dt-report-single-event&event_plan_id=<large_event_plan_id>`.
2. Expected:
   - The summary sections load normally.
   - The raw Square audit and supporting-data row tables are deferred by default.
   - The page shows “Load row detail” controls instead of immediately dumping all rows.

3. Click to load the Square audit detail.
4. Expected:
   - The audit tables render in paginated slices.
   - A “Hide row detail” control is present.
   - DT traces show `single_event_square_fetch_audit`.

5. Click to load the supporting-data detail.
6. Expected:
   - The supporting-data tables render in paginated slices.
   - Labor rows are paginated as well.
   - If memory is already high, DT shows an admin warning and skips further row rendering instead of fataling.
   - DT traces show `single_event_supporting_data`.

## E. Regression checks

1. Re-open the same Single Event and Ticket Pace pages a second time.
2. Expected:
   - Normal render output is preserved.
   - Ticket Pace request cache hits may appear in the fingerprint flags.

3. Open:
   - one public event page
   - cart
   - checkout
4. Expected:
   - No DT admin containment change affects public ticketing.
