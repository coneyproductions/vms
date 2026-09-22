# Release Version Audit 1.3.1

Audit baseline: Issue #8 commit `2a1e491d7a46a3122abc2d23cca7b1ccc0e1748d`, tree `660e22de40b2bfc087885efdcfb824f94e539cbe`.

## Classification and disposition

| Classification | Paths or values | Disposition |
| --- | --- | --- |
| `PLUGIN-RELEASE-MARKER` | `backstage-venue-manager.php` header; `includes/core/registry/constants.php` `BVMGR_VERSION`; `vms-build.txt`; `readme.txt` stable tag and current-release operative prose | Updated from `1.3.0` to `1.3.1` so frontend assets receive a new cache-busting query version. |
| `CURRENT-REPOSITORY-EXPECTATION` | Public-release pipeline, release-compatibility harness, official/additional add-on compatibility contracts and probes, current normal-local compatibility assertions | Updated to require public BVM `1.3.1`. |
| `COMPONENT/SCHEMA-VERSION` | Staff Tasks schema target/readiness value; admissions migration-interruption fixture | Preserved at `1.3.0`; these identify persisted schema state, not the public plugin release. |
| `TEST-FIXTURE` | Synthetic `1.2.3`, `9.9.9`, portal-hook `1.3.0`, migration, provenance, and old-artifact fixture values | Preserved where synthetic or historical. |
| `HISTORICAL-DOCUMENTATION` | 1.3.0 build/release notes, architecture decisions, security contracts, ledger history, prior artifact receipts | Preserved verbatim. |
| `OTHER` | Component, companion, add-on, schema/content, migration, and internal `0.2.24.*` versions | Preserved; none is the public BVM release marker. |

The historical `VMS_VERSION` compatibility name is not reintroduced. The canonical public runtime constant is `BVMGR_VERSION`, and it is the version used by BVM asset enqueues and the release builder.

The ticket enqueue owner was audited separately because it previously resolved `BVMGR_VERSION` and then replaced that value with per-file mtimes for four JavaScript handles. The 1.3.1 release removes those overrides and retains the existing release-version fallback, making the ticket JavaScript and CSS cache keys consistently `1.3.1`.
