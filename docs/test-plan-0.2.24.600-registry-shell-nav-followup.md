# VMS Test Plan — 0.2.24.600 — Registry Shell/Nav Follow-up

## Purpose

Repair the issues found during Codex testing of `0.2.24.599` without changing the intended menu model.

The WordPress left VMS menu should remain a short launcher list. Detailed module discovery should happen inside VMS through the top navigation, section/category lists, All VMS Pages, and direct URLs.

## Files Changed

- `includes/core/registry/admin-menu.php`
- `includes/admin-ui/nav.php`
- version/build markers
- docs/test-plan notes

## What Changed

- Changed the registry default `shell` behavior from `true` to `false`.
  - A newly registered module with a normal callback now receives the shared global VMS top navigation instead of being incorrectly treated as a self-rendered shell page.
  - Pages that already render their own shell continue to be handled by the explicit shell-page list.
- Added `Email Follow-Ups` to the hardcoded Marketing & Social top-nav/category list.
- Preserved the compact WordPress left menu as primary launchers only:
  - Dashboard
  - Planning
  - Vendors & Staff
  - Marketing & Social
  - Venues
  - Settings
  - Tools

## Required Browser Checks

🚨 Codex/browser testing is required before considering this menu stable.

### 1. WordPress Left Menu Remains Short

From `/wp-admin/index.php`:

1. Hover the VMS item in the WordPress left admin menu.
2. Confirm the flyout shows only the primary launchers:
   - Dashboard
   - Planning
   - Vendors & Staff
   - Marketing & Social
   - Venues
   - Settings
   - Tools
3. Confirm the long child-page list does not return.

### 2. Add-on Registration Smoke Test

Use a temporary local-only test snippet:

```php
add_action('vms_admin_register_pages', function () {
    vms_register_admin_page(array(
        'slug'       => 'vms-test-registry-module',
        'page_title' => 'Test Registry Module',
        'menu_title' => 'Test Registry Module',
        'section'    => 'marketing_sales',
        'capability' => 'manage_options',
        'callback'   => function () {
            echo '<div class="wrap"><h1>Test Registry Module</h1><p>Registry smoke test.</p></div>';
        },
        'source'     => 'test-snippet',
    ));
});
```

Expected:

- `/wp-admin/admin.php?page=vms-test-registry-module` opens without permission errors.
- The page shows `nav.vms-admin-topnav` / the VMS top navigation.
- The active cluster is Marketing & Social.
- The Marketing & Social category list includes `Test Registry Module`.
- The WordPress left VMS flyout does not get longer.
- The page appears in All VMS Pages.
- Removing the snippet removes the page without editing VMS menu code.

### 3. Staff Tasks Nav Regression

Open `/wp-admin/admin.php?page=vms-tasks`.

Expected:

- The page loads normally.
- `nav.vms-admin-topnav` renders.
- Vendors & Staff is active.
- The Vendors & Staff category list is visible.
- The WordPress left VMS menu highlights the Vendors & Staff launcher.

### 4. Email Follow-Ups Marketing List Regression

Open `/wp-admin/admin.php?page=vms-email-followups`.

Expected:

- The page loads normally.
- Marketing & Social is active.
- The Marketing & Social category list includes `Email Follow-Ups`.
- The WordPress left VMS menu highlights the Marketing & Social launcher.

### 5. Direct URL Access

Visit these direct URLs as an admin:

- `/wp-admin/admin.php?page=vms-square-sync-protection`
- `/wp-admin/admin.php?page=vms-data-tools`
- `/wp-admin/admin.php?page=vms-guided-tours`
- `/wp-admin/admin.php?page=vms-admin-pages`
- `/wp-admin/admin.php?page=vms-email-followups`
- `/wp-admin/admin.php?page=vms-ticket-integrity`
- `/wp-admin/admin.php?page=vms-tasks`

Expected:

- No “Sorry, you are not allowed to access this page.”
- Page renders normally.
- The WordPress left VMS flyout remains short.

### 6. All VMS Pages

Open `/wp-admin/admin.php?page=vms-admin-pages`.

Expected:

- Registered/detected page count is non-zero.
- Missing callbacks should remain `0` unless the local environment intentionally has disabled add-ons.
- New registry module appears in the directory during the smoke test.
- No registry-driven module needs to be added manually to the WordPress left menu.

## Rollback

Rollback to `0.2.24.599` only if direct URL access breaks or the VMS left menu disappears. Do not roll back merely because a child link is not in the WordPress left menu; child/module links belong inside VMS discovery surfaces.

## Notes

The core rule remains:

> WordPress left menu = primary launchers only. VMS internal navigation = detailed module directory.
