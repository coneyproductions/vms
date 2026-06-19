# VMS 0.2.24.597 Test Plan — Admin menu direct URL access repair

🚨 **Codex/staging test required before production.** This pass repairs an admin-menu timing regression from 0.2.24.596. If Codex makes any code changes during testing, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision log, handoff notes, this test plan or a follow-up test plan, and the package filename before returning a replacement zip.

## Install/version checks

1. Install `vms-0.2.24.597-admin-menu-direct-url-access-repair.zip` over the current staging build.
2. Confirm WordPress shows VMS version `0.2.24.597`.
3. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.597`.
4. Confirm `vms-build.txt` contains `0.2.24.597`.

## Regression that must be fixed

1. Log in as an administrator.
2. Visit `wp-admin/admin.php?page=vms-square-sync-protection` directly.
3. Expected: the Square Sync Protection page renders in the VMS shell/top navigation.
4. Not expected: WordPress shows “Sorry, you are not allowed to access this page.”

## Direct URL smoke tests

Confirm each URL returns HTTP 200 and renders the intended VMS page or safe VMS shell fallback:

- `wp-admin/admin.php?page=vms-admin-pages`
- `wp-admin/admin.php?page=vms-square-sync-protection`
- `wp-admin/admin.php?page=vms-guided-tours`
- `wp-admin/admin.php?page=vms-data-tools`
- `wp-admin/admin.php?page=vms-ticket-integrity`
- `wp-admin/admin.php?page=vms-event-command-center`
- `wp-admin/edit.php?post_type=vms_event_plan`

## Left-menu compactness check

1. Visit `wp-admin/index.php`.
2. Hover/open the WordPress left **VMS** menu.
3. Expected visible entries should remain the compact primary section launchers only:
   - Dashboard
   - Planning
   - Vendors & Staff
   - Marketing & Social
   - Venues
   - Settings
   - Tools
4. Secondary pages should not flood the WordPress left flyout.

## Discovery checks

1. Open **VMS > Tools** from the compact left menu.
2. Confirm the VMS top navigation and/or Tools area can reach Square Sync Protection, Data Tools, Ticket Integrity, and All VMS Pages.
3. Open **All VMS Pages** and confirm hidden/direct pages are still listed for discovery.

## Non-goals / guardrails

- No ticketing templates, TEC/Woo ticket quantity controls, checkout, Square product-sync firewall logic, Event Plan saves, refunds, or Express Bar behavior should change in this pass.
- This is an admin-menu timing/access repair only.
