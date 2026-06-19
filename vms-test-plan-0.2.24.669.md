# VMS 0.2.24.669 Test Plan

🚨 **Codex repair/versioning protocol:** If Codex makes even a small directly-related code repair while testing/troubleshooting this build, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision/build notes, this test plan or a follow-up test plan, paired DT version markers if DT code changed, and the package filename before returning a replacement zip.

## A. Version markers
1. Install the zip as the canonical `vms` plugin folder.
2. Confirm `wp-content/plugins/vms/vms-build.txt` shows `0.2.24.669`.
3. Confirm the plugin header and `VMS_VERSION` report `0.2.24.669`.
4. If Data Tools is part of the same patch, confirm the paired DT build is `0.5.46`.

## B. Resource Fingerprint screen
1. Open `VMS > Dashboard: Onboarding & Health`.
2. Confirm the `VMS Resource Fingerprints` screen loads without fatal errors.
3. Confirm the threshold text states `3.0s` runtime or `128 MB` peak memory.
4. Clear old entries before the run if you want a clean comparison set.

## C. ECC fingerprint / timing
1. Open Event Command Center for a ticketed Event Plan.
2. Confirm the page loads without fatal errors and the Ticket Snapshot UI renders.
3. If the request is slow/heavy or calculation-flagged, confirm a fingerprint entry appears with `ecc_calculation` flags and markers such as:
   - `ecc.build_payload`
   - `ecc.ticket_reporting_truth`
   - `ecc.ticket_integrity_scan`
4. Record runtime, peak memory, due WP-Cron count, Action Scheduler pending/running counts, and whether `action_scheduler_async_blocked` appears.
5. Note editor/ECC page roundtrip timing separately.

## D. DT report fingerprint / staging diagnostic
1. Open the DT root page and record first-load timing.
2. Open one DT single-event report and record first-load timing.
3. Refresh the same single-event report and compare second-load timing against the first.
4. Confirm fingerprint entries show `dt_report` flags and markers such as:
   - `dt.ticket_report`
   - `dt.report_dataset`
   - `dt.event_model`
   - `dt.single_event_evidence`
   - `dt.labor_overhead`
   - `dt.event_costs`
   - `dt.single_event_page`
5. Confirm the pages load without fatal errors or broken/missing report UI.
6. Record runtime, peak memory, due WP-Cron count, and Action Scheduler pending/running counts from the fingerprint entries.
7. If cPanel Resource Usage is available, note `SPEED/CPU`, `NPROC`, `Entry Processes`, memory, approximate `lsphp` worker count, and whether `HTTP Queries` / `Database Queries` show useful rows or `no results found`.

## E. Queue / overlap behavior
1. During or just after heavy ECC/DT loads, note whether fingerprints show `cron_run`, `action_scheduler_run`, or `vms_queue`.
2. Confirm heavy DT/ECC admin loads show `action_scheduler_async_blocked` instead of spawning extra async queue-runner requests from those pages.
3. If WP-Cron or Action Scheduler still overlap with the page load, record the counts/markers rather than assuming Event Plan publish/save caused the spike.

## F. Guardrail regressions
1. Confirm Data Tools still lives only in the canonical `vms-data-tools` folder.
2. Confirm free/children/verified `$0` ticket rows do not create misleading low-stock warnings by default.
3. Confirm ECC and DT report pages still load without fatal errors.
4. Remember the source-of-truth rule: TEC Orders is not the final ECC reporting source. Prefer DT combined online + door-sales reporting where available, with VMS fallback when DT is unavailable.
