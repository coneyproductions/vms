# WordPress.org Metadata Audit

Date: 2026-06-21

Scope note:

- Working branch: `work/unreleased-2026-06-18`
- HEAD: `ede2db7e5b5a4a3affd303e348536f284ac06149` (`ede2db7`)
- Current repo lineage before this public pass: internal `0.2.24.748`
- Last proven public artifact before this RC: `0.2.24.747`
- Public version introduced in this pass: `1.0.0`

## Metadata State

| Item | Current State After `WPORG-04R` | Evidence | Status | Follow-up |
| --- | --- | --- | --- | --- |
| Plugin Name | `VMS – Venue Management System` | `vendor-management-system.php`; `readme.txt` | Applied | None in this pass. |
| Plugin URI | `https://coneyproductions.booklivetalent.com/vms/` | `vendor-management-system.php`; `readme.txt` | Applied | None in this pass. |
| Description | Public-facing, concise, and source-backed. | `vendor-management-system.php`; `readme.txt` | Applied | Final wording can still be tuned before submission if desired. |
| Version | `1.0.0` synchronized across canonical version markers. | `vendor-management-system.php`; `includes/core/registry/constants.php`; `vms-build.txt`; `readme.txt` | Applied | Keep provenance note with internal `0.2.24.748` / proven `0.2.24.747`. |
| Author | `Coney Productions` | `vendor-management-system.php`; `readme.txt` | Applied | None in this pass. |
| Author URI | `https://coneyproductions.booklivetalent.com/` | `vendor-management-system.php` | Applied | None in this pass. |
| License | `GPLv2 or later` | `vendor-management-system.php`; `readme.txt`; `LICENSE.txt` | Applied | None in this pass. |
| License URI | `https://www.gnu.org/licenses/gpl-2.0.html` | `vendor-management-system.php`; `readme.txt` | Applied | None in this pass. |
| Root license file | Present at the plugin root. | `LICENSE.txt` | Applied | Confirmed not excluded from public packaging. |
| Text Domain | `vms` | `vendor-management-system.php`; `includes/core/registry/constants.php` | Applied | None in this pass. |
| Domain Path | `/languages` | `vendor-management-system.php`; existing `load_plugin_textdomain()` call | Applied | No `languages/` files are bundled yet; the 04R extracted-package rerun reintroduced the pre-existing domain-path warning, but the metadata and packaging state did not change in this batch. |
| Root `readme.txt` | Present at the plugin root with WordPress.org-oriented content. | `readme.txt`; readme-validator rerun | Applied | Keep validator notes limited to optional listing polish items. |
| Contributors | `coneyproductions` | `readme.txt` | Applied | None in this pass. |
| Stable tag | `1.0.0` | `readme.txt` | Applied | Keep synchronized with version markers. |
| Requires at least | `6.8` | `vendor-management-system.php`; `readme.txt`; `test-results/wporg-02-wp68-directroot/*` | Applied | Only lower this if a lower WordPress runtime is proven in a future pass. |
| Requires PHP | `8.3` | `vendor-management-system.php`; `readme.txt`; PHP `8.3.30` lint/build/boot evidence from `WPORG-03` | Applied | Only lower this if equivalent lower-PHP runtime evidence is gathered. |
| Tested up to | `7.0` | `readme.txt`; `test-results/wporg-02-wp70-directroot/*` | Applied | Keep synchronized with future runtime evidence. |
| Changelog | Root readme includes a public `1.0.0` changelog entry. | `readme.txt` | Applied | Expand only if submission review requires more depth. |
| Upgrade Notice | Initial public-release notice added. | `readme.txt` | Applied | Optional to keep if later review prefers omission. |
| Privacy / retention text | Present and aligned with approved uninstall and manual privacy-handling decisions. | `readme.txt` | Applied | Exporter / eraser automation still open. |
| External services disclosure | Present for Cloudflare Turnstile, QRServer / goQR.me, Freemius, vendor ICS fetches, and operator-configured webhooks. | `readme.txt`; source audit from `WPORG-02` | Applied | Reconfirm if service integrations change. |
| Dependency behavior | Optional integrations and fail-closed behavior documented. | `readme.txt`; lifecycle evidence from `WPORG-02`; repo-root load smokes from `WPORG-03` | Applied | Reconfirm during future browser QA if needed. |
| Support and security reporting | WordPress.org support forum plus product docs page; private security email documented. | `readme.txt` | Applied | None in this pass. |
| Screenshots | Listed in the readme, but no screenshot assets were added. | `readme.txt` | Open | Add assets later if desired. |
| Directory banners / icons | Not added in this pass. | tree inventory | Open | Optional pre-submission improvement. |

## Packaging Inclusion Audit

- `release-public-excludes.txt` does not exclude root `readme.txt`.
- `release-public-excludes.txt` does not exclude root `LICENSE.txt`.
- Result: both root metadata files were confirmed in the final package as `vms/readme.txt` and `vms/LICENSE.txt`.

## Provenance Audit

- `1.0.0` is a public-facing version alias for the current `0.2.24.748` repository state.
- The last proven public artifact before this pass remains `0.2.24.747`, as recorded in `docs/provenance/reports/0.2.24.747/public-release.report.txt`.
- `WPORG-03` adds:
  - repo-root builder proof from the git-backed workspace,
  - Local PHP `8.3.30` proof,
  - final rebuilt RC artifact `dist/wporg-03-rc-final/vms-1.0.0-public-release.zip`,
  - final SHA-256 `37752f55c30d10939b12d5bb40cbd89ea902da9fca979ffd216e022b44f78593`.
- `WPORG-04A` adds:
  - rebuilt RC artifact `dist/wporg-04a/vms-1.0.0-public-release.zip`,
  - SHA-256 `fd97b45b61f9a1131d12b954080228cb0a441df172d04516597e513e0ba44a67`,
  - packaged Plugin Check reduction from `3888` to `3808` findings after the first high-density blocker batch.
- `WPORG-04B` adds:
  - rebuilt RC artifact `dist/wporg-04b/vms-1.0.0-public-release.zip`,
  - SHA-256 `f04938e13855920759e68307946dcf73de31e4b411245392675522373baee5ef`,
  - packaged Plugin Check reduction from `3808` to `3695` findings after the budget-calculator batch and the limited Event Plans admin-list micro-slice.
- `WPORG-04D` adds:
  - rebuilt RC artifact `dist/wporg-04d/vms-1.0.0-public-release.zip`,
  - SHA-256 `7987b619acec510e397677074eba3f0442a8511b2a5492112583fc5f7ea9e6f3`,
  - dedicated Event Plans blocker audit map `docs/WPORG_EVENT_PLANS_HARDENING_MAP_1.0.0.md`,
  - packaged Plugin Check reduction from `3695` to `3692` findings after one protected Event Plans admin-list helper/output slice.
- `WPORG-04E` adds:
  - current rebuilt RC artifact `dist/wporg-04e/vms-1.0.0-public-release.zip`,
  - current SHA-256 `ca120b97c574ccdd72bb124defc8e712ed7291f4f9730d334423b6b1176d34be`,
  - packaged Plugin Check reduction from `3692` to `3605` findings after the safe high-density `due-dates.php` plus `holidays.php` batch,
  - remaining isolated Event Plans regression scripts now aligned to the shared bootstrap resolver and passing from the nested repo workspace.
- `WPORG-04G` adds:
  - current rebuilt RC artifact `dist/wporg-04g/vms-1.0.0-public-release.zip`,
  - current SHA-256 `e2f4f6a45593b26c319dea37b4179f174e54558aa25acdc0a1131f6cbe553f6d`,
  - packaged Plugin Check reduction from `3605` to `3554` findings after the safe error-heavy `vendor-command-center.php` plus `vendor-availability.php` batch,
  - `tests/vendor-availability-ux.php` and `tests/add-dispatch-open-vendor-needs.php` now aligned to the shared bootstrap resolver.
- `WPORG-04H` adds:
  - current rebuilt RC artifact `dist/wporg-04h/vms-1.0.0-public-release.zip`,
  - current SHA-256 `b66aded43d758b2d8bc5de66b57f8ceb8e69927d89eb91c6dadf1a26ed9a734c`,
  - packaged Plugin Check reduction from `3554` to `3491` findings after the safe admin-only `event-command-center.php` render/i18n/date batch,
  - packaged validation used a temporary extracted plugin slug for the rerun so the local `vms/` install stayed untouched.
- `WPORG-04I` adds:
  - current rebuilt RC artifact `dist/wporg-04i/vms-1.0.0-public-release.zip`,
  - current SHA-256 `aceda39376ec454c49106a1a41ec88a96ec5ff49acfb97ae730308c93120aaa8`,
  - packaged Plugin Check reduction from `3491` to `3435` findings after the safe admin-only `staffing.php` escaping/request/i18n batch,
  - cleaned raw findings in `docs/plugin-check-1.0.0-raw.txt` with external dependency deprecation noise captured separately in `test-results/wporg-04i-plugin-check.stderr.txt`.
- `WPORG-04J` adds:
  - current rebuilt RC artifact `dist/wporg-04j/vms-1.0.0-public-release.zip`,
  - current SHA-256 `06905c9a2c62788056adf9d99857dce37df82e4f7f87a6e7fbb57df5c0d498c5`,
  - packaged Plugin Check reduction from `3435` to `3408` findings after the safe Staff Portal render/i18n/read-only-query batch,
  - cleaned raw findings in `docs/plugin-check-1.0.0-raw.txt` with external dependency deprecation noise captured separately in `test-results/wporg-04j-plugin-check.stderr.txt`.
- `WPORG-04K` adds:
  - current rebuilt RC artifact `dist/wporg-04k/vms-1.0.0-public-release.zip`,
  - current SHA-256 `894cf8280489f4d52561be45e88b4ee317693ad2b61cc400c45ad41b4dceb209`,
  - packaged Plugin Check reduction from `3408` to `3319` findings after the safe Vendor Portal render/i18n/read-only-query batch,
  - cleaned raw findings in `docs/plugin-check-1.0.0-raw.txt` with external dependency deprecation noise captured separately in `test-results/wporg-04k-plugin-check.stderr.txt`.
- `WPORG-04L` adds:
  - current rebuilt RC artifact `dist/wporg-04l/vms-1.0.0-public-release.zip`,
  - current SHA-256 `2814fe4b4867cfb67a03cef47c135dacf785963e0e46cf47af5282a40c80d03b`,
  - packaged Plugin Check reduction from `3319` to `3290` findings after the safe public calendar render/read-only-filter batch,
  - cleaned raw findings in `docs/plugin-check-1.0.0-raw.txt` with external dependency deprecation noise captured separately in `test-results/wporg-04l-plugin-check.stderr.txt`.
- `WPORG-04M` adds:
  - current rebuilt RC artifact `dist/wporg-04m/vms-1.0.0-public-release.zip`,
  - current SHA-256 `08bbe1f22254facca50dfabb096ed06b45b06126efe1111d872ac5c3202ca1e3`,
  - packaged Plugin Check reduction from `3290` to `3278` findings after the safe public vendor profiles render/i18n batch,
  - cleaned raw findings in `docs/plugin-check-1.0.0-raw.txt` with external dependency deprecation noise captured separately in `test-results/wporg-04m-plugin-check.stderr.txt`.
- `WPORG-04N` adds:
  - current rebuilt RC artifact `dist/wporg-04n/vms-1.0.0-public-release.zip`,
  - current SHA-256 `51c6d2c127845440ffce9eee2c07428ce67b5c8dc90a1b3208c6a0601680b8a9`,
  - packaged Plugin Check reduction from `3278` to `3274` findings after the safe public vendor profile template render batch,
  - cleaned raw findings in `docs/plugin-check-1.0.0-raw.txt` with external dependency deprecation noise captured separately in `test-results/wporg-04n-plugin-check.stderr.txt`.
- `WPORG-04O` adds:
  - current rebuilt RC artifact `dist/wporg-04o/vms-1.0.0-public-release.zip`,
  - current SHA-256 `b5ff1494aa35b48e3d108f51d8efc584bacde4fbeceb433acca60ebdac06b690`,
  - packaged Plugin Check reduction from `3274` to `3270` findings after the safe social template-engine read-only SQL batch,
  - cleaned raw findings in `docs/plugin-check-1.0.0-raw.txt` with external dependency deprecation noise captured separately in `test-results/wporg-04o-plugin-check.stderr.txt`.
- `WPORG-04P` adds:
  - current rebuilt RC artifact `dist/wporg-04p/vms-1.0.0-public-release.zip`,
  - current SHA-256 `720dc9a32f3609ebb54ef77227b0cf85123776554f7b62c347e8a77077fcf152`,
  - packaged Plugin Check reduction from `3270` to `3268` findings after the safe social audit read-only SQL error batch,
  - cleaned raw findings in `docs/plugin-check-1.0.0-raw.txt` with external dependency deprecation noise captured separately in `test-results/wporg-04p-plugin-check.stderr.txt`.
- `WPORG-04Q` adds:
  - current rebuilt RC artifact `dist/wporg-04q/vms-1.0.0-public-release.zip`,
  - current SHA-256 `bdb050f722c55de68a34c1690a7f8143f024801e638a7f00f1a14975c96d3671`,
  - packaged Plugin Check reduction from `3268` to `3255` findings after the safe lineup-schedule translator-comment batch,
  - the selected file `includes/core/lineup-schedule.php` reduced from `12` findings to `0`,
  - the extracted-package rerun also stopped emitting the pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope,
  - cleaned raw findings in `docs/plugin-check-1.0.0-raw.txt` with WP-CLI deprecation noise captured separately in `test-results/wporg-04q-plugin-check.stderr.txt`.
- `WPORG-04R` adds:
  - current rebuilt RC artifact `dist/wporg-04r/vms-1.0.0-public-release.zip`,
  - current SHA-256 `4f336a3eb71714ac703633ca0b8f7222ed371881372416779b9159ca9203dd5d`,
  - packaged Plugin Check reduction from `3255` to `3224` findings after the safe vendor-user-links translator-comment batch,
  - the selected file `includes/core/vendor-user-links.php` reduced from `68` findings to `36`,
  - the extracted-package rerun reintroduced the previously observed `plugin_header_nonexistent_domain_path` warning outside the selected file scope,
  - cleaned raw findings in `docs/plugin-check-1.0.0-raw.txt` with WP-CLI deprecation noise stripped from the packaged output stream and mirrored in `test-results/wporg-04r-plugin-check.stderr.txt`.
