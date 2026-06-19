# CODEX HANDOFF — VMS 0.2.24.598 — Registry-Generated Grouped Left Menu

## Goal

Stop link whack-a-mole. The VMS left flyout should no longer be a hand-maintained list that breaks whenever a module/add-on is introduced.

This pass makes the visible WordPress VMS flyout rebuild from the VMS admin page registry:

- primary categories first
- registered pages under their declared category/section
- direct URLs still registered and accessible
- older direct submenu pages still captured and grouped as a fallback

## Primary Files

- `includes/admin-ui/nav.php`
- `assets/css/vms-admin-menu.css`
- `docs/vms_add-on_convention.md`
- `docs/test-plan-0.2.24.598-registry-generated-grouped-left-menu.md`

## Key Behavior

New modules should use:

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

The module should then appear automatically:

- in the grouped VMS left flyout
- in the matching top nav/category list when `top_nav` is true
- in All VMS Pages when `directory` is true
- by direct URL

## Important Test Focus

🚨 Browser-test the WordPress Dashboard hover state, not just VMS pages.

The original failure was visible from `/wp-admin/index.php` when hovering the left VMS menu.

Also test direct URLs, especially:

- `vms-square-sync-protection`
- `vms-data-tools`
- `vms-guided-tours`
- `vms-admin-pages`
- `vms-email-followups`

## Expected Outcome

The left VMS menu can be boring. It does not need to be visually perfect.

It must be reliable:

> Register once. Declare section once. Link lands where it belongs.
