# VMS 0.2.24.694 Test Plan — State of the Range Metric Accuracy

Use the environment's WordPress CLI wrapper for these checks. On Local, that is `app/public/bin/wp-local`; on staging, run the equivalent commands with `wp` from the site root.

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.694`.
3. Confirm `VMS_VERSION` reports `0.2.24.694`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.694`.

Expected: all markers match `0.2.24.694`.

## B. Event 3884 Row Preview

Run:

```bash
wp eval '$event = null; $store = get_option(vms_ticket_integrity_results_option_key(), array()); foreach ((array) ($store["events"] ?? array()) as $candidate) { if (is_array($candidate) && (int) ($candidate["tec_event_id"] ?? 0) === 3884) { $event = $candidate; break; } } if (!$event) { echo "{}"; return; } echo wp_json_encode(vms_ticket_integrity_build_state_of_range_event_row($event), JSON_UNESCAPED_SLASHES);'
```

Expected for production-equivalent semantics:

1. `tickets_sold` reflects completed quantity across all active mapped ticket rows.
2. `paid_tickets_sold` reflects completed paid quantity only.
3. `free_tickets_sold` reflects completed zero-dollar/qualified quantity.
4. `gross_sales` reflects completed net ticket revenue.
5. `tickets_left` reflects current available ticket inventory.
6. `total_capacity` reflects summed active mapped ticket-row inventory totals.

## C. Plain-Text Email Preview

Run:

```bash
wp eval '$event = null; $store = get_option(vms_ticket_integrity_results_option_key(), array()); foreach ((array) ($store["events"] ?? array()) as $candidate) { if (is_array($candidate) && (int) ($candidate["tec_event_id"] ?? 0) === 3884) { $event = $candidate; break; } } if (!$event) { echo "{}"; return; } $email = vms_ticket_integrity_build_state_of_range_email(array("events" => array($event), "summary" => array("events_scanned" => 1, "green" => 1, "yellow" => 0, "red" => 0), "last_scan" => array("completed_at_gmt" => time()))); echo $email["body"];'
```

Expected:

1. Event row labels read `Available inventory`, `Ticket capacity`, and `Free/qualified sold`.
2. The body uses literal `$`, dashes, and ampersands.
3. The body does not contain `&#36;`, `&#8211;`, `&#038;`, or `&amp;`.

## D. Staging Report Send

Run:

```bash
wp eval '$r = vms_ticket_integrity_send_state_of_range_report("manual_test_plan_report_694"); var_export($r);'
wp eval '$state = vms_ticket_integrity_get_daily_report_state(); var_export(array("last_status" => $state["last_status"] ?? "", "last_trigger" => $state["last_trigger"] ?? "", "last_recipient" => $state["last_recipient"] ?? "", "used_stale_snapshot" => $state["used_stale_snapshot"] ?? null));'
```

Expected:

1. The report reaches staging mail handoff without a fatal.
2. `last_status = sent`.
3. `last_trigger = manual_test_plan_report_694`.
4. The body text uses the updated labels and decoded plain text.

## E. Cron Hooks Remain Scheduled

Run:

```bash
wp cron event list --fields=hook,next_run_gmt,next_run_relative | grep 'vms_ticket_integrity_daily_'
```

Expected:

1. `vms_ticket_integrity_daily_scan` is still scheduled.
2. `vms_ticket_integrity_daily_report` is still scheduled.

## F. Syntax Check

Run:

```bash
php -l includes/ticketing/ticket-integrity-daily-report.php
```

Expected: the command passes.
