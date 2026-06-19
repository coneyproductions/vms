# VMS Test Plan — 0.2.24.612 — Debug Log Cleanup

## Version checks

1. Confirm plugin header reports `0.2.24.612`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.612`.
3. Confirm `vms-build.txt` begins with `0.2.24.612`.

## Primary checks

1. Clear `debug.log`.
2. Hit homepage, event page, cart, checkout, and `wp-cron.php`.
3. Load WP Admin dashboard plus VMS dashboard, Event Plans, Email Follow-Ups, and Feedback Results.
4. Confirm no fresh early `vms` translation notices appear.
5. Confirm no fresh PHP 8.3 null deprecations appear from admin title/menu registration.
6. Confirm `vms_social_process_queue` and `vms_tasks_notifications_tick` still use valid custom schedules.
7. Confirm no fresh `invalid_schedule` errors are logged for `vms_*` hooks.

## Expected outcomes

1. Core VMS schedule labels no longer trigger early translation notices.
2. Hidden VMS pages no longer feed null values into WordPress admin title/path handling.
3. `debug.log` stays small and useful during normal smoke coverage.
