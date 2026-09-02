# Agreements Vendor Portal Routing Closeout

Date: 2026-09-01 / 2026-09-02 UTC

Status: **PRODUCTION PASS WITH SEPARATE PORTAL DEBT**. VMS Agreements `0.3.48` is active on SerenadeRange.com and the real vendor `5505` Agreements route now renders the correct packet surface. Backstage Venue Manager remained on public `1.2.0` and was not changed or deployed.

## Root cause and ownership

- BVM validates `tab` through `vms_vendor_portal_allowed_tabs` before selecting or rendering the requested portal branch.
- Agreements `0.3.47` added its navigation link and badge through `vms_vendor_portal_nav_links`, registered its content provider through `vms_vendor_portal_render_custom_tab`, and appended its panel to Dashboard, but never added `agreements` to the allowed-tab filter.
- Therefore `?tab=agreements` survived in the URL and the badge remained accurate, while BVM normalized the requested tab to `dashboard`. This made Dashboard active and rendered Dashboard cards before Agreements' dashboard panel.
- Agreements owns the missing integration registration. Release `0.3.48` registers `agreements` at priority `20`, preserves all previously allowed slugs, and leaves BVM's vendor-context and authorization flow unchanged.

## Source, release, and repository evidence

The authoritative non-Git add-on source is `/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public/wp-content/plugins/vms-agreements`. Changed source files from the untouched `0.3.47` baseline:

- `includes/vendor-portal.php`
- `tests/vendor-portal-routing-regression.php`
- `vms-agreements.php`
- `vms-agreements-build.txt`
- `docs/CHANGELOG.md`
- `docs/HANDOFF.md`
- `docs/README.md`
- `docs/CODEX-HANDOFF-0.3.48.md`
- `docs/TEST-PLAN-0.3.48.md`

Repository evidence:

- `docs/addon-compatibility/vms-agreements-0.3.47-to-0.3.48.patch`
- `docs/addon-compatibility/artifacts/vms-agreements-0.3.48.zip`
- `tests/addon-compatibility/installed-addon-runtime-probe.php`
- this closeout and the remediation-ledger addendum

The deterministic release rebuilt byte-for-byte twice. Package `vms-agreements-0.3.48-vendor-portal-routing-registration.zip` has SHA-256 `dbdb32b93d37b8a52868d14da704e1ea758c29edd093d0caeac7fa7a2477b24b`, contains `122` files, passes `unzip -t`, and extracts byte-identically to the canonical source. The patch applies cleanly to the untouched `0.3.47` archive and reconstructs the candidate. Installed production and canonical trees share normalized SHA-256 `dd47b9755de280449756eafac45e0d9b9244a518e9233542bc6cc6adeec59` across `122` files.

## Verification

- All `12` add-on PHP files passed `php -l`.
- The focused Agreements route regression passed against the mirror source and the exact downloaded production BVM `1.2.0` portal source. It covers the portal tab matrix, direct/clicked routing, active state, badge count, current and acknowledged packet actions, no-agreement empty state, invalid-tab fallback, and cross-vendor query isolation.
- Public-BVM identity coverage passed modern, legacy, old, and absent scenarios.
- Repository runtime contracts, additional runtime contracts, request sanitization, admissions read-only state, runtime stub guards, and release-harness self-tests passed.
- The disposable PHP `8.3` installed-add-on sweep passed both core-first and add-ons-first load orders at `23/23` checks with exact production BVM `1.2.0` and production Sponsorships `0.1.7.1`. The system PHP `8.5` run was an infrastructure-only WP-CLI deprecation failure; a first PHP `8.3` attempt against the newer local Sponsorships `0.1.27` stopped on the harness's intentional `0.1.7.1` assertion before the exact production fixture completed both scenarios.
- The full public BVM release builder was also attempted but stopped on a pre-existing Ticket UI explicit-save-intent fixture unrelated to Agreements. Exact production BVM source and the relevant focused/runtime suites passed, so no BVM package was built or deployed.

## Production deployment and acceptance

- Before: active BVM `1.2.0`, active Agreements `0.3.47`, no `agreements` allowed-tab callback, and production Agreements source matched the local `0.3.47` baseline.
- Backup: `/home/coney/vms-agreements-backups/20260902T010618Z/vms-agreements-0.3.47-pre-routing-fix.tgz`, mode `0600`, SHA-256 `6d0343df12bc10edd215bf535d9cee57f988b482dc53d4f9c4a53627e4f2895e`.
- Deploy input: owner-only `/home/coney/vms-agreements-deployments/vms-agreements-0.3.48-vendor-portal-routing-registration.zip`, exact package checksum above.
- After: active Agreements `0.3.48`; `agreements` appears in the BVM allowlist and `vmsa_register_vendor_portal_tab` is registered at priority `20`. The active-plugin list SHA-256 remained `395cd6b76b77023095b456e3fcf1d76c60d297f8dd1c210ea69a39df91b0914e`.
- Signed-in desktop smoke at `/vendor-portal/?tab=agreements&vendor_id=5505`: Agreements alone was active; Dashboard was inactive; Dashboard cards were absent; badge `1`, `Taylor Swift tribute - Reputation`, `Viewed`, `Review Agreement`, `Print / Save PDF`, and seven historical packets rendered.
- Dashboard remained active on its own route and Tech Docs remained active on its own route. Add a Business remained present and was not opened because it is a write-oriented application path.
- At `390 x 844`, Agreements remained active, all required packet/action text rendered, Dashboard content stayed absent, and the document had no horizontal overflow.
- No safe no-agreement production vendor was used; the focused regression covers the empty state without exposing or mutating production data.

## Data and log preservation

- Packet `7467` stayed `viewed`, its `post_modified_gmt` stayed `2026-08-22 05:20:42`, and its complete meta SHA-256 stayed `40dae543dc0b1f2f086c47987111ee8d57ae5b84380435f13bcd30e3cec4f2cb`.
- Vendor `5505` retained packet/status pairs `7449/7453/7456/7458/7460/7463/7465 = superseded` and `7467 = viewed`; the list SHA-256 stayed `2fcfd7930c9e2f4d806beba594d164cdee7d0cd9e28cf44c9ddcd5d3d07aed64`.
- No Review Agreement, acknowledgement, signature, print, application, or other write action was submitted during smoke testing.
- Two malformed read-only WP-CLI probes made during pre-deployment diagnosis appended isolated CLI-only fatal entries: the first called the historical portal helper name, and the second lost quoting around a post-query array key. Both were corrected with encoded read-only probes before deployment; neither came from a web request or changed WordPress data. From the deployment onward, the log gained only existing `[VMS DT TRACE]` bootstrap lines. No post-deployment PHP fatal, warning, parse error, notice, deprecation, database error, or Agreements-owned error appeared.

## Remaining debt and boundaries

- Production Guest List is visible in navigation but its core module does not add `guest-list` to BVM's allowed-tab filter; its direct route independently falls back to Dashboard. This is a pre-existing BVM-owned portal defect, not caused by Agreements `0.3.48`. It needs a separate BVM repair/release because changing frozen core would have violated this add-on-only production fix.
- No BVM mirror/live runtime file, historical inactive VMS package, unrelated add-on, protected stash, cache, agreement record, activation state, or public core package changed. No push, tag, WordPress.org action, or rollback was performed.
