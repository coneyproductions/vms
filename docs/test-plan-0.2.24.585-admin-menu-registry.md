# VMS 0.2.24.585 Test Plan — Admin Menu Registry + Page Directory

## Scope

This pass stabilizes the VMS admin menu without attempting a full visual redesign.

Changes covered:

- Adds a central VMS admin page registry contract in `includes/core/registry/admin-menu.php`.
- Adds `VMS > Tools & Integrity / All VMS Pages` via slug `vms-admin-pages`.
- Adds a searchable/detectable page directory and menu health summary.
- Adds the `vms_admin_register_pages` action and `vms_register_admin_page()` helper for future add-ons.
- Stops the compact left-menu logic from automatically hiding unknown VMS add-on submenu slugs.
- Moves the compact-left-menu hiding rule and top-nav build label styling out of PHP inline output and into `assets/css/vms-admin-ui.css`.

## Static checks

Run from the plugin root:

```bash
php -l includes/core/registry/admin-menu.php
php -l includes/admin-ui/nav.php
php -l includes/admin-ui/context.php
php -l includes/bootstrap.php
php -l vendor-management-system.php
```

Expected: all return `No syntax errors detected`.

Also verify no new inline menu CSS remains in touched PHP files:

```bash
grep -R "<style\|style=\"" -n includes/admin-ui/nav.php includes/core/registry/admin-menu.php
```

Expected: no output.

## WordPress admin smoke tests

🚨 **CODEX / STAGING TESTING REQUIRED BEFORE PRODUCTION RELIANCE**

1. Install/activate VMS `0.2.24.585`.
2. Open **VMS** in the WordPress admin left menu.
3. Confirm the left menu shows the durable section labels:
   - Dashboard
   - Events & Schedule
   - Tickets & Admissions
   - Vendors & Staff
   - Marketing & Sales
   - Reports & Finance
   - Venue Setup
   - Tools & Integrity
   - Settings & Add-ons
4. Open **All VMS Pages**.
5. Confirm the page renders without fatal errors and shows health cards plus a table of VMS pages.
6. Confirm existing important pages still open:
   - Dashboard
   - Schedule
   - Event Plans list
   - Vendor Command Center
   - Ticket Integrity
   - Data Tools
   - Add-ons
   - Square Sync Protection
   - Guided Tours
7. Confirm hidden/secondary pages remain reachable directly from the top nav or directory.
8. Confirm the global VMS top nav still renders on VMS screens.

## Add-on registration smoke test

Temporarily add this snippet in a staging-only mu-plugin or test add-on:

```php
add_action('vms_admin_register_pages', function () {
    vms_register_admin_page(array(
        'slug' => 'vms-test-addon-page',
        'page_title' => 'VMS Test Add-on Page',
        'menu_title' => 'Test Add-on',
        'section' => 'marketing_sales',
        'capability' => 'manage_options',
        'callback' => function () {
            echo '<div class="wrap"><h1>VMS Test Add-on Page</h1><p>Registry test.</p></div>';
        },
        'source' => 'test-addon',
    ));
});
```

Expected:

- `VMS Test Add-on Page` opens at `wp-admin/admin.php?page=vms-test-addon-page`.
- It appears in **All VMS Pages**.
- It is not silently hidden solely because its slug was not hardcoded in VMS core.

Remove the temporary snippet after testing.

## Regression checks

- Existing direct admin URLs should continue to resolve.
- VMS pages should still be recognized by VMS admin UI shell/top nav logic.
- Unknown future add-on pages should be discoverable instead of buried.
- Current secondary core pages may still be compacted from the left menu, but remain visible in the directory/top nav.
