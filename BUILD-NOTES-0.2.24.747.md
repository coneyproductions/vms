# Build Notes 0.2.24.747

- Release type: provenance-only production marker for the successfully deployed `0.2.24.747` public artifact.
- Runtime delta from the QA-passed `0.2.24.746` RC: version markers only in `vendor-management-system.php`, `includes/core/registry/constants.php`, and `vms-build.txt`.
- Deployed public ZIP SHA-256: `0683c78b4d1f300184430e38d08f7aa4f77d6e36f261bbc758aca9fe1c1d003d`.
- Git provenance reconcile target:
  - exact deployed source is isolated on `release/0.2.24.747`
  - stale pre-reconcile repository state is preserved on `archive/pre-provenance-reconcile-2026-06-18`
  - unreleased local runtime changes are isolated on `work/unreleased-2026-06-18`
- Provenance inputs preserved with this reconcile:
  - `docs/provenance/releases/0.2.24.747.json`
  - `docs/provenance/reports/0.2.24.747/`
  - `scripts/verify-public-release-provenance.php`
- The working `wp-content/plugins/vms` tree was restored after the production packaging step and therefore does not itself represent the deployed release without this repo reconcile.
