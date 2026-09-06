# VMS Data Tools 0.5.44 Test Plan — Init Boot / Translation Hardening

## Build

- Plugin: `vms-data-tools`
- Version: `0.5.44`
- Slug: `init-translation-boot-hardening`

## Repair/versioning protocol

🚨 If Codex or any tester makes even a minimal code repair during testing, update all relevant version markers and package notes before returning the modified zip. At minimum check/update:

- `vms-data-tools.php` plugin header version
- `VMS_DT_VERSION`
- `vms-build.txt`
- this test plan or replacement test notes
- package filename

Do not return a modified build with stale versioning/docs.

## Primary checks

1. Install/replace the plugin zip.
2. Confirm WordPress shows VMS Data Tools version `0.5.44`.
3. Clear `wp-content/debug.log`.
4. Load a mix of requests:
   - homepage
   - one public event page
   - cart
   - checkout
   - `wp-cron.php`
   - one VMS Data Tools admin page
   - one Vendor Invites admin page
5. Confirm:
   - no early translation-loading notices from VMS Data Tools
   - no WooCommerce/domain-dependent boot notices caused by Data Tools loading too early
   - public requests do not needlessly load Vendor Invites admin/tour UI wiring
   - admin pages still load the expected Vendor Invites management UI

## Regression checks

1. Open a Data Tools report/import page that depends on plugin strings and confirm labels still render translated through the `vms-data-tools` textdomain.
2. Open a Vendor Invites admin page and confirm the admin UI, claim/portal helpers, and related actions still function.
3. Confirm no fatal errors in the admin screens or PHP error log.
