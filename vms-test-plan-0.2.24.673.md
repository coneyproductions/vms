# VMS 0.2.24.673 Test Plan

🚨 **Codex repair/versioning protocol:** If Codex makes even a small directly-related code repair while testing/troubleshooting this build, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision/build notes, this test plan or a follow-up test plan, paired DT version markers if DT code changed, and the package filename before returning a replacement zip.

## A. Version markers
1. Install/update the canonical `vms` plugin folder from `VMS_673_async_suppression_marker_context_fix.zip` or the equivalent canonical package contents.
2. Confirm `wp-content/plugins/vms/vms-build.txt` shows `0.2.24.673`.
3. Confirm the plugin header and `VMS_VERSION` report `0.2.24.673`.
4. Confirm DT remains `0.5.46` unless a DT code change is intentionally part of the patch.

## B. Carry-forward safety
1. Activate/deactivate/reactivate VMS Data Tools once, including a WP-CLI path if available.
2. Confirm the nullable `network_wide` lifecycle-hook compatibility from `0.2.24.671` still holds.
3. Confirm there is no fatal error on Dashboard, ECC, DT pages, Ticket Integrity, Plugins, Orders, or Event Plan edit.

## C. Scoped async suppression fingerprints
1. Clear the VMS Resource Fingerprint log.
2. Load these scoped heavy pages:
   - Event Command Center
   - DT root
   - DT single-event report
   - Event Plan edit screen
3. Confirm each scoped page logs `action_scheduler_async_blocked`.
4. Confirm the stored flag/marker values include readable `page`, `page_slug`, `scope_reason`, and `mode` fields instead of collapsed `...` values.
5. Confirm ECC still renders its Ticket Snapshot UI.

## D. Non-scoped admin sanity
1. Clear the fingerprint log again if you want a clean unrelated-page pass.
2. Load unrelated admin pages such as:
   - WordPress Dashboard
   - Plugins
   - WooCommerce Orders
3. Confirm those pages do not log `action_scheduler_async_blocked`.
4. Confirm an Action Scheduler async runner request can still appear/run outside the scoped heavy pages when pending work exists.

## E. Regression checks
1. Confirm Ticket Integrity still monitors paid GA rows.
2. Confirm verified/free/children/qualified `$0` rows still default to `should_monitor=false`.
