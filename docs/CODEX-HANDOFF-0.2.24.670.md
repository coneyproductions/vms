# Codex Handoff — VMS 0.2.24.670

## Focus

This is a narrow follow-up to `0.2.24.669`. Staging exposed a fatal during plugin activation under WP-CLI because the new fingerprint lifecycle callback assumed a strict boolean `network_wide` flag. On this host, WP-CLI can pass `null`.

## High-priority assertions

1. VMS / DT activation and reactivation should not fatal under WP-CLI.
2. The fingerprint lifecycle callbacks should still log plugin activation/deactivation context when applicable.
3. The `0.2.24.669` fingerprint viewer, DT/ECC timing markers, memoization, and Action Scheduler async-runner suppression should behave the same after this compatibility fix.

## Known scope

No DT logic changed in this follow-up. DT remains `0.5.46`. This build exists only to harden the VMS lifecycle hook compatibility discovered during staging validation.
