# CODEX HANDOFF — VMS 0.2.24.599 — Left Rail Launchers + Registry Discovery

## Goal

Correct the 0.2.24.598 menu behavior. The grouped registry model was useful, but the full child-page directory was shown in the WordPress left flyout, recreating the original mile-long menu problem.

This pass keeps the registry/discovery architecture while restoring the WP left VMS menu to a short launcher list.

## Primary File

- `includes/admin-ui/nav.php`

## Functional Contract

The WordPress left rail should show only:

- Dashboard
- Planning
- Vendors & Staff
- Marketing & Social
- Venues
- Settings
- Tools

Detailed registered pages/modules should be discovered through:

- VMS top navigation/category lists
- All VMS Pages
- section hubs
- direct URLs

## New Module Rule

New modules should still register once:

```php
add_action('vms_admin_register_pages', function () {
    vms_register_admin_page(array(
        'slug'       => 'vms-example-module',
        'page_title' => 'Example Module',
        'menu_title' => 'Example Module',
        'section'    => 'tools_integrity',
        'capability' => 'manage_options',
        'callback'   => 'vms_example_module_render_page',
        'source'     => 'vmsx-example-module',
    ));
});
```

That one registration should make the module discoverable in VMS without expanding the WP left flyout.

## Important Test Focus

🚨 Browser-test both contexts:

1. `/wp-admin/index.php` hover over VMS:
   - must show only primary launchers.
   - must not show dozens of links.

2. Direct URLs:
   - `vms-square-sync-protection`
   - `vms-data-tools`
   - `vms-guided-tours`
   - `vms-admin-pages`
   - `vms-email-followups`
   - `vms-ticket-integrity`

Expected: no permission screen, normal render, short WP left flyout remains short.

## Expected Outcome

The menu should be boring and predictable:

> WP left menu = primary category launchers.
> VMS top nav / All Pages = detailed module discovery.
