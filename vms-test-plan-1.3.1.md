# BVM 1.3.1 Test Plan — Ticket Surface and Rental Purchase UI

1. Confirm `backstage-venue-manager.php`, `BVMGR_VERSION`, `vms-build.txt`, and the readme stable tag all report `1.3.1`.
2. Run PHP lint, JavaScript syntax checks, `tests/ticketing-server-mount-native-footer-remediation.php`, `tests/ticketing-output-buffer-lifecycle-characterization.php`, `tests/ticketing-ui-authority.php`, and `tests/ticketing-public-lifecycle.js`. The authority test must reject ticket JavaScript enqueue paths that replace `BVMGR_VERSION` with filesystem mtimes.
3. Run the public-release build pipeline, release compatibility harness, and reproducibility suite from the clean committed 1.3.1 checkpoint.
4. Build twice from the exact commit. Require identical archive SHA-256 values, identical normalized manifests, one `backstage-venue-manager/` root, and the filename `backstage-venue-manager-1.3.1-public-release.zip`.
5. Install that exact ZIP on staging. Confirm the installed manifest matches the ZIP, all three public version markers report `1.3.1`, and ticket JavaScript/CSS URLs use `ver=1.3.1`.
6. Safe Mode: test visitor and administrator at desktop and mobile widths. TEC must be the single ticket owner; the form and all expected rows must remain visibly rendered for at least 2.2 settled seconds.
7. Progressive: test visitor and administrator at desktop and mobile widths. BVM must be the single owner; no fallback controller may compete.
8. In every case, require sponsorship before the ticket form, ticket rows before the canonical purchase mount, and eligible add-ons/rentals inside that mount with no stray or duplicate rental region.
9. Use `elementFromPoint()` plus actual clicks to prove rental `−` and `+` are pointer-accessible. Verify quantity, terms, add-ons, subtotal, GA, Child ratio, Veteran/First Responder behavior, and an intercepted cart payload containing rental product `6014` without submitting an order.
10. Require clean browser console/network telemetry and unchanged ticket/product/order/rental persistence. Stop before production.
