# VMS Data Tools 0.5.46 Test Plan

## A. Version and folder sanity
1. Install as the canonical `vms-data-tools` plugin folder.
2. Confirm `wp-content/plugins/vms-data-tools/vms-build.txt` shows `0.5.46`.
3. Confirm no parallel folder such as `vms-data-tools-1`, `vms-data-tools-0.5.45`, or another copy is active.
4. Confirm paired VMS core is `0.2.24.669`.

## B. Activation and load safety
1. Activate VMS Core first.
2. Activate VMS Data Tools.
3. Confirm there is no fatal error.
4. If an admin notice says another copy is active, remove/deactivate the duplicate and keep the canonical `vms-data-tools` folder.

## C. DT root and single-event performance
1. Open the DT root page and record first-load timing.
2. Open one single-event report and record first-load timing.
3. Refresh the same single-event report and compare second-load timing against the first.
4. Confirm the pages still render normally and do not lose ticket/profitability UI sections.

## D. Fingerprints and markers
1. Open `VMS > Dashboard: Onboarding & Health` after the DT page loads.
2. Confirm fingerprint entries show `dt_report` flags and relevant markers such as:
   - `dt.ticket_report`
   - `dt.report_dataset`
   - `dt.event_model`
   - `dt.single_event_evidence`
   - `dt.labor_overhead`
   - `dt.event_costs`
   - `dt.single_event_page`
3. Record runtime, peak memory, due WP-Cron count, and Action Scheduler pending/running counts.
4. On repeat loads, note whether cache-hit flags appear, such as `dt_ticket_report_cache`, `dt_report_dataset_cache`, or `dt_event_model_cache`.

## E. Scope / no obvious full rebuild
1. For a single-event request, inspect the markers and timing to judge whether work stays reasonably scoped to the selected event.
2. Confirm there is no fatal error or obvious all-events rebuild on every single-event admin load.
3. If one span still dominates, capture that marker as the next optimization target.

## F. Regression checks
1. Open Reporting Module, Event Profitability, Single Event Detail, Ticket Pacing, Revenue Intelligence, and any Square-connected report normally used on staging.
2. Confirm no fatal errors or duplicate menu/nav stacks are introduced.
