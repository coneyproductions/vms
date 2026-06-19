# VMS 0.2.24.705 Test Plan — State of the Range Scheduling and Diagnostics

## Pre-checks

1. Install/activate VMS `0.2.24.705`.
2. Confirm version markers:
   - Plugin page shows `0.2.24.705`.
   - `vms/includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.705`.
   - `vms/vms-build.txt` begins with `0.2.24.705`.
3. Confirm the site timezone is the intended production timezone.

## Scheduling verification

1. Run `wp vms state-of-range status`.
2. Confirm:
   - hook is `vms_ticket_integrity_daily_report`
   - expected local time is `06:05`
   - scheduled hook count is `1`
   - next scheduled run is populated
3. Run `wp vms state-of-range reschedule`.
4. Confirm the hook count stays `1` and the next scheduled run remains aligned to `06:05` local time.

## Admin diagnostic verification

1. Open `VMS -> Ticket Integrity`.
2. Confirm the new `State of the Range Status` panel renders without PHP fatals.
3. Confirm the panel shows:
   - last scheduled run
   - last successful render
   - last send attempt
   - last result
   - configured recipient
   - next scheduled run
   - last error
4. Click `Preview Today’s Report`.
5. Confirm a preview panel appears and no email is sent.
6. Click `Dry-Run Diagnostic`.
7. Confirm the dry run succeeds and still does not mark the report as sent.

## Send-path verification

1. Use `wp vms state-of-range render --date=YYYY-MM-DD --dry-run` for a controlled date.
2. Confirm the body renders and past events before the local report day are excluded.
3. Confirm same-day and future events remain included.
4. If an explicit admin send test is approved, use either:
   - `Send Test to Admin` in the Ticket Integrity page, or
   - `wp vms state-of-range send-test --to=admin@example.com`
5. Confirm:
   - a failed send records `last_result` / `last_error`
   - a successful send records `last_successful_send_at`
   - dry-run/preview do not populate successful-send state

## Automated/local checks

1. Run:
   - `php vms/tests/state-of-range-upcoming-filter.php`
   - `php vms/tests/state-of-range-delivery-state.php`
   - `php vms/tests/ticket-integrity-scan-lock.php`
2. Expected:
   - same-day/future filtering passes
   - dry-run and send-state transitions pass
   - stale scan-lock recovery passes
