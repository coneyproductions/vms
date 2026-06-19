# VMS 0.2.24.671 Test Plan

🚨 **Codex repair/versioning protocol:** If Codex makes even a small directly-related code repair while testing/troubleshooting this build, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision/build notes, this test plan or a follow-up test plan, paired DT version markers if DT code changed, and the package filename before returning a replacement zip.

## A. Version markers
1. Install/update the canonical `vms` plugin folder.
2. Confirm `wp-content/plugins/vms/vms-build.txt` shows `0.2.24.671`.
3. Confirm the plugin header and `VMS_VERSION` report `0.2.24.671`.
4. If paired Data Tools is part of the run, confirm DT remains `0.5.46`.

## B. Activation / reactivation regression
1. With VMS active, activate/deactivate/reactivate VMS Data Tools once, including a WP-CLI path if available.
2. Confirm there is no fatal error and no parse error during the cycle.
3. Confirm plugin lifecycle fingerprints still record activation/deactivation context when the request qualifies for logging.

## C. Diagnostic follow-through
1. Re-run the `0.2.24.669` staging diagnostic pages after the activation cycle succeeds:
   - `VMS > Dashboard: Onboarding & Health`
   - Event Command Center
   - Ticket Integrity
   - DT root
   - DT single-event report
2. Confirm the diagnostic/performance instrumentation remains intact.
