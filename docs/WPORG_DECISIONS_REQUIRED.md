# WordPress.org Decisions Required

Date: 2026-06-19

This file now records the approved WordPress.org decisions applied during `WPORG-01B`, plus the remaining items that are still validation gates rather than unresolved identity choices.

## Approved And Applied

| Item | Approved Decision | Applied In Repo | Notes |
| --- | --- | --- | --- |
| Public plugin name | `VMS – Venue Management System` | Yes | Applied in `vendor-management-system.php` and root `readme.txt`. |
| Preferred WordPress.org slug | `vms` | Yes | Slug and text domain remain `vms`. |
| First public version | `1.0.0` | Yes | Synced in `vendor-management-system.php`, `includes/core/registry/constants.php`, `vms-build.txt`, and root `readme.txt`. |
| Author | `Coney Productions` | Yes | Retained in the plugin header and readme. |
| Author URI | `https://coneyproductions.booklivetalent.com/` | Yes | Added to the plugin header. |
| Plugin URI | `https://coneyproductions.booklivetalent.com/vms/` | Yes | Added to the plugin header and readme. |
| Private security email | `coneyproductionsllc@gmail.com` | Yes | Added to root `readme.txt` security-reporting copy. |
| WordPress.org username | `coneyproductions` | Yes | Used in root `readme.txt` contributors. |
| License | `GPLv2 or later` | Yes | Added to the plugin header, root `readme.txt`, and root `LICENSE.txt`. |
| Root license file | `LICENSE.txt` | Yes | Added at the plugin root. |
| Dependencies | WooCommerce, The Events Calendar, and Event Tickets remain optional feature-gated integrations. | Yes | Documented in root `readme.txt` and readiness docs. |
| Missing dependencies | VMS should continue loading; only dependent features fail closed. | Yes | Documented in root `readme.txt` and readiness docs. |
| Multisite | Not officially supported or verified for `1.0.0`. | Yes | Documented in root `readme.txt` and readiness docs. |
| Uninstall | Retain operational data by default and disclose clearly. | Yes | Documented in root `readme.txt`; runtime behavior unchanged. |
| Privacy exporter / eraser | Manual handling for `1.0.0`; automation tracked separately. | Yes | Documented in root `readme.txt`; follow-up gate remains open. |
| Telemetry | No passive telemetry. | Yes | Documented in root `readme.txt`. |
| Add-ons | Separate plugins; operator-initiated installation only. | Yes | Documented in root `readme.txt`; audit follow-up remains open. |
| Remote code delivery | None in the WordPress.org core plugin. | Yes | Documented in root `readme.txt`. |

## Provenance Mapping

- Public version `1.0.0` in this repository maps to the current internal `0.2.24.748` repo state.
- The last proven public artifact remains `0.2.24.747`.
- This pass is intended to change public metadata and documentation only, plus the canonical version markers required to represent public `1.0.0`.

## Still Open

These are still open because they need additional validation, not because the public identity decisions are unresolved.

| Item | Current State | Why It Stays Open |
| --- | --- | --- |
| Minimum WordPress version | Not locked yet. | `WPORG-01B` did not run the formal minimum-version compatibility matrix, so the repo should not invent a minimum claim. |
| Minimum PHP version | Not locked yet. | `WPORG-01B` did not run the formal minimum-version compatibility matrix, so the repo should not invent a minimum claim. |
| Plugin Check | Not run in this pass. | Leave open for `WPORG-02`. |
| Readme validator | Not run in this pass. | Leave open for `WPORG-02`. |
| PHPCS / WPCS | Not run in this pass. | Leave open for `WPORG-02`. |
| Compatibility matrix | Not run in this pass. | Leave open for `WPORG-02`, including activation, deactivation, upgrade, and dependency-absence smoke on the chosen RC. |
| Add-on installer policy audit | Not complete. | Public copy is documented, but the dedicated policy audit still needs an explicit compliance pass. |
| Privacy exporter / eraser automation | Not implemented. | Manual handling is documented for `1.0.0`; automation remains future work. |
| Uninstall cleanup tooling | Not implemented. | Current behavior intentionally retains operational data by default. |
| Screenshots and directory assets | Pending. | Root `readme.txt` includes a screenshot list, but no `.wordpress-org/` assets were added in this pass. |
| WordPress.org SVN steps | Pending approval. | Submission and post-approval SVN work are explicitly out of scope for this task. |
