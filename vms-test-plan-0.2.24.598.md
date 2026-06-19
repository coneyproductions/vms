# VMS Test Plan — 0.2.24.598 — Registry-Generated Grouped Left Menu

## Purpose

Stop treating each new VMS module link as a one-off menu patch.

This pass rebuilds the visible WordPress VMS flyout from the VMS admin page registry so the left menu uses the same category/source-of-truth model as the VMS top navigation and All VMS Pages directory.

## Files Changed

- `includes/admin-ui/nav.php`
- `assets/css/vms-admin-menu.css`
- `docs/vms_add-on_convention.md`
- version/build markers

## What Changed

- The VMS left flyout now shows primary categories with registered pages underneath them.
- Registry pages are grouped by their declared `section`.
- Direct `add_submenu_page()` entries from older modules are still captured and guessed into a category if they have not migrated to the registry yet.
- The current page is highlighted when it exists in the grouped flyout.
- Direct URLs remain registered before the menu is rebuilt, preserving WordPress permission checks.
- New add-ons/modules should register once with `vms_register_admin_page()` and declare a `section`.

## Required Browser Checks

🚨 Codex/browser testing is required before considering this menu architecture stable.

### 1. WordPress Dashboard Hover

From `/wp-admin/index.php`:

1. Hover the VMS left menu.
2. Confirm the flyout is grouped by primary category:
   - Dashboard
   - Planning
   - Vendors & Staff
   - Marketing & Social
   - Venues
   - Settings
   - Tools
3. Confirm pages appear under the expected category.
4. Confirm the menu does not show an unstructured raw wall of links.

### 2. Direct URL Access

Visit these direct URLs as an admin:

- `/wp-admin/admin.php?page=vms-square-sync-protection`
- `/wp-admin/admin.php?page=vms-data-tools`
- `/wp-admin/admin.php?page=vms-guided-tours`
- `/wp-admin/admin.php?page=vms-admin-pages`
- `/wp-admin/admin.php?page=vms-email-followups`

Expected:

- No “Sorry, you are not allowed to access this page.”
- Page renders normally.
- VMS top navigation appears where expected.

### 3. Category Placement Spot Checks

Confirm these placements in the VMS flyout and top nav/category lists:

| Page | Expected Category |
| --- | --- |
| Schedule | Planning |
| Event Plans | Planning |
| Ticket Integrity | Planning or Tools depending active registry section |
| Vendor Availability | Vendors & Staff |
| Staff Tasks | Vendors & Staff |
| Email Follow-Ups | Marketing & Social |
| Data Tools | Tools |
| Square Sync Protection | Tools |
| Guided Tours | Settings |
| Venues | Venues |

### 4. All VMS Pages Safety Net

Open `/wp-admin/admin.php?page=vms-admin-pages`.

Expected:

- Page count is non-zero.
- New/registry pages list with a section.
- No unexpected callback missing spike.
- Unclassified count should be low; any unclassified VMS module should be fixed by adding/changing its registry `section`.

### 5. Add-on Registration Smoke Test

Use a temporary test add-on or snippet during local testing only:

```php
add_action('vms_admin_register_pages', function () {
    vms_register_admin_page(array(
        'slug'       => 'vms-test-registry-module',
        'page_title' => 'Test Registry Module',
        'menu_title' => 'Test Registry Module',
        'section'    => 'marketing_sales',
        'capability' => 'manage_options',
        'callback'   => function () {
            echo '<div class="wrap"><h1>Test Registry Module</h1></div>';
        },
        'source'     => 'test-snippet',
    ));
});
```

Expected:

- The page appears under Marketing & Social.
- The page appears in All VMS Pages.
- `/wp-admin/admin.php?page=vms-test-registry-module` opens.
- Removing the snippet removes it from the menu without touching VMS menu code.

## Rollback

Rollback to `0.2.24.597` if:

- direct VMS admin URLs are blocked again;
- the VMS flyout disappears completely;
- top navigation rendering fails on VMS admin pages.

## Notes

This pass intentionally does not chase visual polish. The goal is a reliable navigation contract:

> Register page once, declare section once, and VMS places it consistently.
