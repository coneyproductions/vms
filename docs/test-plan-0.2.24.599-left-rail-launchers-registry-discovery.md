# VMS Test Plan — 0.2.24.599 — Left Rail Launchers + Registry Discovery

## Purpose

Correct the 0.2.24.598 overcorrection.

The WordPress left VMS flyout must **not** become the full VMS module directory. It should remain a short launcher list for the primary VMS categories, while detailed module/page lists live inside VMS itself.

## Files Changed

- `includes/admin-ui/nav.php`
- version/build markers
- docs/revision/test-plan notes

## What Changed

- The WordPress VMS left flyout is reduced back to primary category launchers only:
  - Dashboard
  - Planning
  - Vendors & Staff
  - Marketing & Social
  - Venues
  - Settings
  - Tools
- The registry grouping from 0.2.24.598 is preserved for VMS-owned discovery surfaces:
  - VMS top navigation
  - active section/category detection
  - All VMS Pages directory
  - direct URL access
- A new module/page still registers once with `vms_register_admin_page()` and declares its `section`.
- The module should **not** be dumped into the WordPress left rail. It should appear in the correct VMS category lists and All VMS Pages.

## Required Browser Checks

🚨 Codex/browser testing is required before considering this menu stable.

### 1. WordPress Dashboard Hover

From `/wp-admin/index.php`:

1. Hover the VMS item in the WordPress left admin menu.
2. Confirm the VMS flyout shows only the primary launchers:
   - Dashboard
   - Planning
   - Vendors & Staff
   - Marketing & Social
   - Venues
   - Settings
   - Tools
3. Confirm the flyout does **not** show the long child-page list.
4. Confirm no older wall-of-links behavior returns from the normal WP Dashboard.

### 2. VMS Page Expanded Left Menu

From `/wp-admin/admin.php?page=vms-dashboard`:

1. Confirm the visible VMS submenu in the WP left rail is still short.
2. Confirm it does **not** expand into dozens of child links.
3. Confirm the active section highlights appropriately:
   - Dashboard pages highlight Dashboard.
   - Event/ticket pages highlight Planning.
   - Vendor/staff pages highlight Vendors & Staff.
   - Email/social/meta pages highlight Marketing & Social.
   - Venue pages highlight Venues.
   - Settings/docs/tours pages highlight Settings.
   - Tools/data/sync/integrity pages highlight Tools.

### 3. Direct URL Access

Visit these direct URLs as an admin:

- `/wp-admin/admin.php?page=vms-square-sync-protection`
- `/wp-admin/admin.php?page=vms-data-tools`
- `/wp-admin/admin.php?page=vms-guided-tours`
- `/wp-admin/admin.php?page=vms-admin-pages`
- `/wp-admin/admin.php?page=vms-email-followups`
- `/wp-admin/admin.php?page=vms-ticket-integrity`

Expected:

- No “Sorry, you are not allowed to access this page.”
- Page renders normally.
- VMS top navigation appears where expected.
- The WP left VMS flyout stays short.

### 4. Registry Discovery Surfaces

Open `/wp-admin/admin.php?page=vms-admin-pages`.

Expected:

- Page count is non-zero.
- Registered/detected VMS pages still appear in the directory.
- Pages show a section/source.
- New module pages should appear here even if they are not visible in the WP left flyout.

### 5. Top Navigation / Category Lists

Open representative pages and confirm their links appear in the correct VMS category lists:

| Page | Expected VMS Category |
| --- | --- |
| Schedule | Planning |
| Event Plans | Planning |
| Ticket Integrity | Planning/Tools depending registry mapping |
| Vendor Availability | Vendors & Staff |
| Staff Tasks | Vendors & Staff |
| Email Follow-Ups | Marketing & Social |
| Data Tools | Tools |
| Square Sync Protection | Tools |
| Guided Tours | Settings |
| Venues | Venues |

### 6. Add-on Registration Smoke Test

Use a temporary local-only test add-on/snippet:

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

- The page appears in All VMS Pages.
- The page is discoverable in the Marketing & Social VMS navigation/category list when `top_nav` is true.
- `/wp-admin/admin.php?page=vms-test-registry-module` opens.
- The page does **not** make the WordPress left flyout longer.
- Removing the snippet removes the page without touching VMS menu code.

## Rollback

Rollback to `0.2.24.598` only if the short launcher menu disappears completely or direct URL access fails. Otherwise, menu placement issues should be fixed by registry `section` metadata or section-to-cluster mapping, not by adding one-off left-menu links.

## Notes

Functional rule going forward:

> The WordPress left menu is a launcher. VMS itself is the directory.

New modules declare their section once. VMS handles discovery through top nav, category lists, All VMS Pages, direct URLs, and diagnostics.
