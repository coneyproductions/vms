# VMS 0.2.24.693 Test Plan — Ticket Integrity Memory Hardening

Use the environment's WordPress CLI wrapper for these checks. On Local, that is `app/public/bin/wp-local`; on staging, run the equivalent commands with `wp` from the site root.

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.693`.
3. Confirm `VMS_VERSION` reports `0.2.24.693`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.693`.

Expected: all markers match `0.2.24.693`.

## B. CLI Scan Path

Run:

```bash
wp eval '$r = vms_ticket_integrity_scan_all(array("trigger" => "manual_test_plan_scan")); var_export(array("ok" => $r["ok"] ?? null, "events_scanned" => $r["events_scanned"] ?? null, "message" => $r["message"] ?? ""));'
wp eval '$logs = array_slice(vms_ticket_integrity_get_logs(), 0, 6); foreach ($logs as $row) { echo ($row["type"] ?? "") . "|" . ($row["message"] ?? "") . PHP_EOL; }'
```

Expected:

1. The scan returns `ok => true`.
2. The command completes without a PHP fatal or memory exhaustion.
3. Recent logs include `scan_started` followed by `scan_completed`.
4. Recent logs do not include a new `scan_failed` or `scan_failed_memory` entry for this trigger.

## C. Daily Report Path

Run:

```bash
wp eval '$r = vms_ticket_integrity_send_state_of_range_report("manual_test_plan_report"); var_export($r);'
wp eval '$state = vms_ticket_integrity_get_daily_report_state(); var_export(array("last_status" => $state["last_status"] ?? "", "last_trigger" => $state["last_trigger"] ?? "", "used_stale_snapshot" => $state["used_stale_snapshot"] ?? null, "last_error" => $state["last_error"] ?? ""));'
```

Expected:

1. The report reaches mail handoff or the environment's mail-capture path without a fatal.
2. The result returns `ok => true`.
3. Daily report state shows `last_status = sent` and `last_trigger = manual_test_plan_report`.
4. Recent logs include `daily_report_started` and `daily_report_sent`.

## D. Stale Snapshot Fallback

Run:

```bash
wp eval '
$store = vms_ticket_integrity_get_results_store();
$last_scan = is_array($store["last_scan"] ?? null) ? $store["last_scan"] : array();
$last_scan["completed_at_gmt"] = time() - (25 * HOUR_IN_SECONDS);
$store["last_scan"] = $last_scan;
update_option(vms_ticket_integrity_results_option_key(), $store, false);
set_transient(vms_ticket_integrity_scan_lock_key(), array("started_gmt" => time(), "source" => "test_plan"), 10 * MINUTE_IN_SECONDS);
$r = vms_ticket_integrity_send_state_of_range_report("manual_test_plan_stale");
delete_transient(vms_ticket_integrity_scan_lock_key());
var_export($r);
'
wp eval '$state = vms_ticket_integrity_get_daily_report_state(); var_export(array("last_status" => $state["last_status"] ?? "", "used_stale_snapshot" => $state["used_stale_snapshot"] ?? null, "last_error" => $state["last_error"] ?? ""));'
```

Expected:

1. The report still returns `ok => true` when a prior snapshot exists.
2. Daily report state records `used_stale_snapshot = 1`.
3. Recent logs show `daily_report_sent` with refresh-failure context instead of a silent stop.
4. The email body includes the refresh-failure warning banner.

## E. No Snapshot / Scan-Locked Failure Path

Run:

```bash
wp eval '
$store = vms_ticket_integrity_get_results_store();
$store["events"] = array();
$store["summary"] = array();
$last_scan = is_array($store["last_scan"] ?? null) ? $store["last_scan"] : array();
$last_scan["completed_at_gmt"] = time() - (25 * HOUR_IN_SECONDS);
$store["last_scan"] = $last_scan;
update_option(vms_ticket_integrity_results_option_key(), $store, false);
set_transient(vms_ticket_integrity_scan_lock_key(), array("started_gmt" => time(), "source" => "test_plan"), 10 * MINUTE_IN_SECONDS);
$r = vms_ticket_integrity_send_state_of_range_report("manual_test_plan_skip");
delete_transient(vms_ticket_integrity_scan_lock_key());
var_export($r);
'
wp eval '$state = vms_ticket_integrity_get_daily_report_state(); var_export(array("last_status" => $state["last_status"] ?? "", "used_stale_snapshot" => $state["used_stale_snapshot"] ?? null, "last_error" => $state["last_error"] ?? ""));'
```

Expected:

1. The report returns `ok => false`.
2. Daily report state shows `last_status = failed`, `last_error = scan_refresh_failed`, and `used_stale_snapshot = 0`.
3. Recent logs include `daily_report_skipped_scan_failed` and `daily_report_failed`.
4. The command does not fatal or silently exit.

After this check, restore the environment with a fresh scan:

```bash
wp eval 'var_export(vms_ticket_integrity_scan_all(array("trigger" => "manual_test_plan_restore")));'
```

## F. Cron Hooks Remain Scheduled

Run:

```bash
wp cron event list --fields=hook,next_run_gmt,next_run_relative | grep 'vms_ticket_integrity_daily_'
```

Expected:

1. `vms_ticket_integrity_daily_scan` is still scheduled.
2. `vms_ticket_integrity_daily_report` is still scheduled.
3. Neither hook is left unscheduled by the patch.

## G. Syntax Checks

Run:

```bash
php -l includes/integrations/ticketing-phase-b.php
php -l includes/ticketing/ticket-integrity-monitor.php
php -l includes/ticketing/ticket-integrity-daily-report.php
```

Expected: all commands pass.
