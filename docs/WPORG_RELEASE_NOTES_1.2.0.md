# WPORG Release Notes 1.2.0

Date: 2026-07-25

## Release Identity

- Product line: `Backstage Venue Manager` public core
- Public version: `1.2.0`
- Public slug: `backstage-venue-manager`
- Canonical main plugin file: `backstage-venue-manager.php`
- Source of truth: the repository mirror `packages/vms-github-reconcile`

## Public-Core Summary

- Establishes `1.2.0` as the authoritative public-core release line without changing the active live `vms` production plugin.
- Carries forward the accepted WordPress.org remediation work for request boundaries, output handling, JSON handling, file-system and upload/download boundaries, executable admin assets, AJAX lifecycle behavior, packaging controls, and Turnstile disclosure alignment.
- Preserves existing venue, vendor, Event Plan, admissions, and ticketing behavior within the public core package.
- Leaves database schemas, migration routines, and internal compatibility basenames unchanged.

## Main-Filename Compatibility

- The public package exposes one WordPress plugin header at `backstage-venue-manager/backstage-venue-manager.php`.
- `vendor-management-system.php` remains as a headerless same-directory bridge so an existing active basename can load the canonical bootstrap and migrate to `backstage-venue-manager.php` without creating a second Plugins-screen entry.
- Both single-site `active_plugins` and multisite `active_sitewide_plugins` basename values are migrated, preserving the network activation timestamp.
- A directory change from an existing `vms/` install to the public `backstage-venue-manager/` package remains a controlled replacement boundary; the in-package bridge cannot safely rewrite an old basename after an updater has removed the old directory.

## Package Exclusions

- The public package excludes repository-only material such as `docs/`, `tests/`, `scripts/`, `dist/`, versioned `BUILD-NOTES-*.md`, nested ZIP artifacts, and internal instruction files such as `AGENTS.md`.
- The public package is built from the mirror repository only; it does not depend on the sibling live `vms` tree as its release source.

## Plugin Check Residuals

- This boundary does not claim a zero-finding package.
- The known accepted packaged Plugin Check residual set remains the current tracked baseline already documented in the packaged raw findings and triage records.
- The unchanged notable packaged families still include the bounded `OffloadedContent` and `ExceptionNotEscaped` findings; `MissingVersion`, `unexpected_markdown_file`, and `MissingTranslatorsComment` are not part of the accepted residual set for this release boundary.

## Outreach Boundary

- Backstage Outreach is not part of the `1.2.0` public core package.
- Outreach extraction remains a separate production-convergence project and is not claimed as complete by this release boundary.

## Production Boundary

- The active live basename remains `vms/vendor-management-system.php`.
- The active live version remains `1.1.0`.
- No production deployment, live replacement, or side-by-side coexistence is authorized by this release boundary.
- The public package must not be installed alongside an active `vms` core copy.
- WordPress.org resubmission review does not itself authorize production deployment.

## Future Convergence Prerequisites

- Complete the separate Outreach extraction project.
- Complete the duplicate-core safety and replacement sequence for production.
- Run the explicit production migration and rollback plan only after those prerequisites are approved.

## Final Prereview Steps

- Build a fresh public package from the mirror repository.
- Reconfirm the packaged header version, Stable tag, build marker, changelog, and Upgrade Notice.
- Re-run the packaged strict-json Plugin Check and confirm the accepted counts remain unchanged.
- Complete the final repository prereview and submission gate before any upload decision.

## Submission State

- Nothing has been uploaded or submitted to WordPress.org yet.
