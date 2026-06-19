# Codex Handoff — VMS 0.2.24.664

## Build purpose

This package follows 0.2.24.663. It should reduce dirty-map noise and known no-effect meta churn without changing public ticketing behavior.

## Main things to prove

1. Content-only and title-only Event Plan updates still skip Ticket Integrity and staffing heavy work.
2. Content-only saves should classify as Core only again unless a real module value changes.
3. Title-only saves should classify as Core only or at least show substantially less vendor/finance noise than 0.2.24.663.
4. No-effect writes should appear under `no_effect_meta_write_keys` rather than making modules look dirty.
5. Publish diagnostics should still work.
6. Ticketing V2 Save Config / Preview Sync should still work.
7. Primary and secondary vendor sections should show safe `Add new vendor` shortcuts.

## Testing environment note

The user generally installs each candidate version on staging even when local testing is requested. Prefer local first for code-level checks. Use staging when behavior may depend on real hosting, WP-Cron, caching, Woo/TEC checkout, public pages, or other online-only conditions.

## Do not over-test

This is not a full public ticketing rewrite. A focused local pass plus reduced staging smoke is enough unless the local run finds a material failure.
