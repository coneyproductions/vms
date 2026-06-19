VMS ADD-ON CONVENTION (Codex Rules)

- Core plugins: vms-<module>
- Add-on plugins: vmsx-<module>

- Add-on folder slug MUST start with: vmsx-
- Add-on main PHP file should match folder: vmsx-<module>.php

- Add-on classes: VMSX_*
- Add-on functions: vmsx_*
- Add-on options: vmsx_*
- Never use venue-specific names or prefixes anywhere.
Agreement add-on planning note (0.2.24.566)
- Proposed premium agreement/contract add-on slug: `vmsx-agreements`.
- Agreement add-on should render proposal/agreement packets from core VMS booking-term snapshots; it should not own cancellation policies, deposits, rider source data, or Event Plan compensation truth.
- Core owns shared operational terms; add-ons render, export, upload, or integrate those terms.

## Admin page registration convention (0.2.24.585+)

Add-ons should not hardwire themselves into the VMS left menu with their own assumptions about the current menu layout. Register add-on admin pages through the VMS admin menu registry:

```php
add_action('vms_admin_register_pages', function () {
    vms_register_admin_page(array(
        'slug' => 'vms-example-addon',
        'page_title' => 'Example Add-on',
        'menu_title' => 'Example Add-on',
        'section' => 'tools_integrity',
        'capability' => 'manage_options',
        'callback' => 'vms_example_addon_render_page',
        'source' => 'vmsx-example-addon',
    ));
});
```

Recommended sections:

- `dashboard`
- `events_schedule`
- `tickets_admissions`
- `vendors_staff`
- `marketing_sales`
- `reports_finance`
- `venue_setup`
- `tools_integrity`
- `settings_addons`

Pages registered this way are automatically available to the VMS page directory. Unknown add-on slugs should not be silently hidden by the compact left-menu pass.

### Left menu visibility rule (0.2.24.586+)

Registry pages are directory/top-nav discoverable by default and should **not** assume left WordPress menu visibility. Only durable section-level landing pages should opt into the left rail with:

```php
'left_menu' => true,
```

Normal add-on/module pages should leave `left_menu` omitted or set it to `false`. They will still appear in **All VMS Pages** and remain available by direct URL.

### Registry metadata migration rule (0.2.24.587+)

Core and legacy pages may be cataloged in the registry with:

```php
'register' => false,
```

Use this when an existing page still has a direct `add_submenu_page()` callback that should not be moved yet. The registry entry supplies discovery, section, source, and directory metadata without changing the actual page renderer.

The compact VMS left rail now reads its durable section specs from the registry layer through `vms_admin_menu_left_rail_specs`. Do not add another independent hardcoded left-menu list elsewhere. If a future add-on truly needs a durable left-rail section, use the `vms_admin_menu_left_rail_specs` filter deliberately and keep the section count small.

### Shared admin shell rule (0.2.24.594+)

Registering a page in the VMS registry controls discovery: top navigation grouping, All VMS Pages, active cluster mapping, and whether the page belongs in the compact left rail. It does **not** automatically fix a legacy callback that prints its own standalone WordPress `<div class="wrap">` page.

Any VMS core page or add-on page that should look like part of VMS must render through the shared shell:

```php
function vms_example_addon_render_page(): void {
    if (function_exists('vms_admin_ui_render_shell')) {
        vms_admin_ui_render_shell(
            array(
                'title' => __('Example Add-on', 'vms'),
                'subtitle' => __('Short operator-facing description.', 'vms'),
                'shell_id' => 'vms-example-addon-wrap',
            ),
            'vms_example_addon_render_page_content'
        );
        return;
    }

    echo '<div class="wrap" id="vms-example-addon-wrap">';
    echo '<h1>' . esc_html__('Example Add-on', 'vms') . '</h1>';
    vms_example_addon_render_page_content();
    echo '</div>';
}
```

Codex must verify new pages by direct URL and confirm the rendered HTML includes the VMS shell/top navigation, not just HTTP 200. This prevents a page from being discoverable but visually orphaned.

### Left rail launcher rule (0.2.24.599+)

The WordPress VMS flyout is **not** the full module directory. It should stay short and only show durable primary launchers:

- Dashboard
- Planning
- Vendors & Staff
- Marketing & Social
- Venues
- Settings
- Tools

Normal add-ons/modules should register once, declare their `section`, and let VMS place them in the proper internal discovery surfaces:

```php
add_action('vms_admin_register_pages', function () {
    vms_register_admin_page(array(
        'slug'       => 'vms-my-new-module',
        'page_title' => 'My New Module',
        'menu_title' => 'My New Module',
        'section'    => 'marketing_sales',
        'capability' => 'manage_options',
        'callback'   => 'vms_my_new_module_render_page',
        'source'     => 'vmsx-my-new-module',
    ));
});
```

That one registration should be enough for normal discovery:

- appears in the correct VMS top navigation/category list when `top_nav` is true
- appears in All VMS Pages when `directory` is true
- keeps direct URL access through WordPress page registration
- highlights the correct primary left-rail category while active
- does **not** make the WordPress left flyout longer

Do not add new one-off arrays for the left menu, top navigation, or page directory. If a page does not land where expected, fix its registry `section` or the shared section-to-cluster mapping instead of patching an individual link.

Only VMS core should add or change durable left-rail launchers through `vms_admin_menu_left_rail_specs`, and that list should remain intentionally small.

