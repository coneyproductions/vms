# Release Version Audit 1.3.0

Audit baseline: certified source commit `0ca4eb0e505f26e16348b20cbfd54243c241ad80`, tree `0794d4caf45ff523289138fcb22140ecb4941c27`.

## Classification and disposition

| Classification | Paths or values | Disposition |
| --- | --- | --- |
| `PLUGIN-RELEASE-MARKER` | `backstage-venue-manager.php` header; `includes/core/registry/constants.php` `BVMGR_VERSION`; `vms-build.txt`; `readme.txt` stable tag and current-release operative prose | Updated from `1.2.0` to `1.3.0`. Added current changelog and upgrade-notice entries while retaining the 1.2.0 history. |
| `COMPONENT-VERSION` | `includes/modules/staff-tasks/staff-tasks.php` component version | Preserved. It identifies that component contract, not the plugin release. |
| `CONTENT/TOUR-VERSION` | `includes/tours/class-vms-tours-service.php` tour/content version | Preserved. It controls content migration/versioning independently of the plugin release. |
| `HISTORICAL-DOCUMENTATION` | Existing 1.2.0 release notes, metadata audits, prereview reports, handoffs, add-on reports/patches, scan-state receipts, ledger history, and prior build evidence | Preserved verbatim. These values identify historical artifacts and decisions. |
| `TEST-FIXTURE` | Synthetic `1.2.3`, `9.9.9`, legacy-version, migration, provenance, and old-artifact fixture values | Preserved where synthetic or historical. Current-repository expectations were updated to `1.3.0`. |
| `OTHER` | Compatibility fallbacks, internal `0.2.24.*` history, add-on versions, schema/content versions | Preserved. None is a public BVM plugin-release marker. |

The audit intentionally did not perform a repository-wide replacement. Only the four hard markers, the readme statements describing the current release, and tests/contracts that assert the current public BVM version were changed.
