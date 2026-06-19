# VMS 0.2.24.670 Test Plan

🚨 **Codex repair/versioning protocol:** If Codex makes even a small directly-related code repair while testing/troubleshooting this build, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision/build notes, this test plan or a follow-up test plan, paired DT version markers if DT code changed, and the package filename before returning a replacement zip.

## A. Version markers
1. Install/update the canonical `vms` plugin folder.
2. Confirm `wp-content/plugins/vms/vms-build.txt` shows `0.2.24.670`.
3. Confirm the plugin header and `VMS_VERSION` report `0.2.24.670`.
4. If paired Data Tools is part of the run, confirm DT remains `0.5.46`.

## B. Activation / reactivation regression
1. With VMS active, deactivate VMS Data Tools once.
2. Reactivate VMS Data Tools once, including a WP-CLI activation path if available.
3. Confirm there is no fatal error during activation or deactivation.
4. Confirm plugin lifecycle fingerprints still record activation/deactivation context when the request qualifies for logging.

## C. Resource fingerprint viewer
1. Open `VMS > Dashboard: Onboarding & Health`.
2. Confirm the `VMS Resource Fingerprints` screen still loads cleanly.
3. Confirm slow/heavy or flagged admin requests produce entries with runtime, memory, context, queue counts, and markers.

## D. DT / ECC performance follow-through
1. Re-run the `0.2.24.669` DT root, DT single-event, ECC, Ticket Integrity, and Event Plan edit sanity checks.
2. Confirm the earlier activation-fatal blocker is gone and the diagnostic/performance instrumentation remains intact.
