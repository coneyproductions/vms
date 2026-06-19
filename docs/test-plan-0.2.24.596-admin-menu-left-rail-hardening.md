# VMS 0.2.24.596 Test Plan — Admin menu left-rail hardening

🚨 **Codex/staging test required before production.** This pass changes VMS admin menu registration/visibility behavior. If Codex makes any code changes during testing, update the plugin header version, `VMS_VERSION`, `vms-build.txt`, revision log, handoff notes, this test plan or a follow-up test plan, and the package filename before returning a replacement zip.

## Install / version checks

1. Install `vms-0.2.24.596-admin-menu-left-rail-hardening.zip` over the current staging build.
2. Confirm WordPress shows VMS version `0.2.24.596`.
3. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.596`.
4. Confirm `vms-build.txt` contains `0.2.24.596`.

## PHP / asset sanity

1. Run PHP lint against all VMS PHP files.
2. Confirm `assets/css/vms-admin-menu.css` exists.
3. Confirm `includes/admin-ui/assets.php` enqueues `vms-admin-menu` globally in wp-admin.
4. Confirm no console-blocking JavaScript errors appear while opening VMS pages.

## Main regression: WordPress Dashboard hover menu

1. Go to `wp-admin/index.php`.
2. Hover the left **VMS** menu.
3. Confirm the visible submenu is concise and only shows the primary VMS section launchers:
   - Dashboard
   - Planning
   - Vendors & Staff
   - Marketing & Social
   - Venues
   - Settings
   - Tools
4. Confirm detailed pages do **not** appear in that hover flyout, including:
   - Dashboard: Operations
   - Dashboard: Finance
   - Budget Calculator
   - Event Plans
   - Vendor Availability
   - Staff
   - Guest Passes
   - Ticket Integrity
   - Square Sync Protection
   - Guided Tours
   - Data Tools
   - Meta Ads Builder / Logs / Settings

## VMS screen left menu

1. Open `wp-admin/admin.php?page=vms-dashboard`.
2. Confirm the VMS left menu remains limited to the same primary section launchers.
3. Open `wp-admin/admin.php?page=vms-schedule`.
4. Confirm **Planning** is the selected VMS left-rail section.
5. Open `wp-admin/edit.php?post_type=vms_event_plan`.
6. Confirm the VMS parent stays active and **Planning** is the selected left-rail section.

## Top navigation / detailed page access

1. Open the VMS top navigation and confirm secondary links still appear inside the appropriate top-nav dropdown/secondary row.
2. Confirm these direct URLs still load:
   - `wp-admin/admin.php?page=vms-square-sync-protection`
   - `wp-admin/admin.php?page=vms-ticket-integrity`
   - `wp-admin/admin.php?page=vms-guided-tours`
   - `wp-admin/admin.php?page=vms-data-tools`
   - `wp-admin/admin.php?page=vms-admin-pages`
3. Confirm those pages render inside the VMS shell/top navigation where expected.
4. Confirm opening a secondary page highlights the correct primary section in the left rail.

## All VMS Pages directory

1. Open `wp-admin/admin.php?page=vms-admin-pages`.
2. Confirm the directory still lists hidden/direct pages, including:
   - `vms-square-sync-protection`
   - `vms-ticket-integrity`
   - `vms-guided-tours`
   - `vms-data-tools`
   - `vms-add-dispatch`
   - `vms-email-followups`
3. Confirm visible section launchers are marked as left-menu/visible.
4. Confirm secondary pages remain discoverable but are not visible in the WordPress left flyout.

## Non-goals / regression guardrails

- Do not change ticketing UI, TEC quantity controls, checkout behavior, Event Plan save behavior, Square sync/firewall logic, Express Bar, refunds, or customer-facing output.
- Do not reintroduce a long WordPress VMS submenu.
- Do not move detailed module pages back into the left rail unless they become a true primary section launcher.
