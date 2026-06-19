# VMS 0.2.24.747 Production Release Prep

Date: 2026-06-18
Source RC: `vms/dist/vms-0.2.24.746-public-release.zip`
Source RC SHA-256: `e55302b89eb56a7f2808d94e901c8fb16f96b069352cee5fe0b961883724224d`

## Final Artifact

- ZIP filename: `vms-0.2.24.747-public-release.zip`
- ZIP path: `vms/dist/vms-0.2.24.747-public-release.zip`
- Stable copy: `packages/vms-final-0.2.24.747-artifacts/vms-0.2.24.747-public-release.zip`
- SHA-256: `0683c78b4d1f300184430e38d08f7aa4f77d6e36f261bbc758aca9fe1c1d003d`
- Build report path: `vms/dist/vms-0.2.24.747-public-release.report.txt`
- Build report JSON: `vms/dist/vms-0.2.24.747-public-release.report.json`

## Build Method

- The live `vms/` source tree was not a clean `0.2.24.746` RC snapshot; it contained additional packaged runtime changes beyond the QA-passed RC.
- To keep the final production package provenance-clean, the build used a temporary RC overlay at the real `wp-content/plugins/vms` path, rebuilt `0.2.24.747`, copied the final artifact out, and then restored the original local source tree.
- The current local `vms/` source tree has been restored to its pre-build state; only the final `0.2.24.747` artifact and reports were copied back into `vms/dist/`.

## Package Integrity

- Standalone integrity validation: `PASS`
- Command: `php tests/check-package-integrity.php dist/vms-0.2.24.747-public-release.zip`

## Required Release Test Allowlist

Executed via the canonical build pipeline `php scripts/build-public-release.php`.

- Admissions REST permission regression: `PASS`
- Qualified-ticket assignee validation regression: `PASS`
- Ticket checkout safety regression: `PASS`
- Legacy ticketing smoke regression: `PASS`
- Ticket UI isolation regression: `PASS`

## Build Warnings

- `WARN`: plugin header still lacks one or both minimum-requirement fields
- `WARN`: git metadata unavailable in this workspace, so tree state is reported as unknown
- `WARN`: activation-hook portability review remains manual by design because the public-release pipeline does not mutate a site

These warnings are carried forward from the release pipeline and did not block the QA-passed RC or this provenance-only package rebuild.

## Diff From QA-Passed RC

Packaged runtime diff against `vms-0.2.24.746-public-release.zip` is limited to these files:

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`

Observed content changes:

- `vendor-management-system.php`: plugin header `Version` changed from `0.2.24.746` to `0.2.24.747`
- `includes/core/registry/constants.php`: `VMS_VERSION` changed from `0.2.24.746` to `0.2.24.747`
- `vms-build.txt`: build marker changed from `0.2.24.746` to `0.2.24.747`

Confirmation:

- No runtime logic changed from the QA-passed RC.
- No additional runtime files were added, removed, or modified in the final package.

## Provenance-Only Source Edits Used During Build

These were applied in the temporary RC-overlay build source:

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.747.md`

## Separate Backlog Items

- Historical `vms_square_nightly_sync` failed actions with no registered callback are not blockers for this release and should be tracked as cleanup separately.
- Expired admissions nonce handling fails safely, but the admin message remains generic (`Could not add entry.`); this is a minor UX follow-up, not a release blocker.

## Production Deployment Checklist

- Back up the production `wp-content/plugins/vms` directory.
- Record the current production plugin version plus manifest/provenance details before replacement.
- Install `vms-0.2.24.747-public-release.zip`.
- Confirm the active plugin remains `vms`.
- Confirm the plugin version reads `0.2.24.747`.
- Confirm the deployed manifest matches the package contents.
- Run syntax checks.
- Run non-destructive smoke checks.
- Verify the ticketing page loads.
- Verify Event Plan admin loads.
- Verify admissions / Ops loads.
- Verify no recent fatal errors appear in logs.
- Document the rollback path to the production backup.

## Release Gate

- `0.2.24.747` is ready for explicit production deployment approval.
- This task did not deploy to production.
