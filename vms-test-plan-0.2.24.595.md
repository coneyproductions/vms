# VMS 0.2.24.595 — Square Sync shell package repair test plan

🚨 Codex/staging verification required before production.

## Purpose

Repair the broken `0.2.24.594` package by rebuilding from the last full-good package (`0.2.24.593`), preserving the Guided Tours files, and re-applying only the Square Sync Protection shell alignment.

## Critical install check

1. Install this zip on staging.
2. Confirm WordPress does not fatal on activation/load.
3. Confirm this file exists in the installed plugin: `wp-content/plugins/vms/includes/tours/tours.php`.

## Admin navigation checks

1. Open `wp-admin/admin.php?page=vms-square-sync-protection`.
2. Confirm the page renders inside the shared VMS admin shell/top navigation.
3. Confirm the left VMS menu remains concise and uses the aligned headings: Dashboard, Planning, Vendors & Staff, Marketing & Social, Venues, Settings, Tools.
4. Confirm Square Sync Protection is discoverable under the Tools navigation path and/or All VMS Pages.
5. Confirm `wp-admin/admin.php?page=vms-event-command-center` still loads and remains discoverable through the Planning navigation path.

## Square Sync Protection behavior checks

1. Run **Scan protected products** first.
2. Confirm the scan reports VMS/TEC ticket/add-on/admission products as protected candidates.
3. Confirm normal Square-owned catalog products such as shirts, eggs, alcohol/menu items, and merch are skipped unless they have explicit VMS/TEC ticketing markers.
4. Only after reviewing scan counts, run **Repair protected products** on staging.
5. Download/export the report if available and confirm it does not include normal reusable catalog products.

## Regression checks

1. VMS Guided Tours page loads without fatal.
2. VMS Data Tools link still loads in the VMS shell if the add-on is active.
3. Schedule page loads with the VMS top nav.
4. Express Bar / Bar Menu navigation entries still appear where expected.
5. No unexpected individual module pages are added back to the WordPress left VMS rail.

## Rollback

If this build fatals, immediately roll back to `0.2.24.593`.
