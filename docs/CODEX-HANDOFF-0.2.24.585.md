# Codex Handoff — VMS 0.2.24.585 Admin Menu Registry

## Purpose

Pause feature work and stabilize the VMS admin menu architecture. The immediate issue was that VMS admin pages and add-ons were being registered from several different places, while the compact left-menu pass later hid any submenu item that was not in a hardcoded list. That made new add-ons look like they failed to register.

## Files changed

- `includes/core/registry/admin-menu.php`
- `includes/bootstrap.php`
- `includes/admin-ui/nav.php`
- `includes/admin-ui/context.php`
- `assets/css/vms-admin-ui.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/test-plan-0.2.24.585-admin-menu-registry.md`
- `docs/CODEX-HANDOFF-0.2.24.585.md`
- `docs/05-revision-log.md`
- `docs/06-test-plan.md`
- `docs/vms_add-on_convention.md`

## What changed

- Added a first-pass VMS admin page registry with `vms_register_admin_page()`.
- Added `do_action('vms_admin_register_pages')` so future VMS add-ons can register pages without editing core.
- Added `All VMS Pages` / `vms-admin-pages` as a discoverability and health screen.
- Added directory collection from both registry entries and existing WordPress submenu entries.
- Updated compact left-menu behavior so known secondary core pages may still be compacted, but unknown add-on pages are no longer automatically assigned the hidden menu class.
- Moved the compact menu CSS rule out of inline PHP output and into `assets/css/vms-admin-ui.css`.
- Moved the top-nav build label inline style into CSS.

## Key implementation notes

The registry is intentionally conservative. It does not migrate every legacy `add_submenu_page()` call yet. Instead, it creates the future-compatible registration contract and gives the current menu a safety net.

Future add-ons should use:

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

## Testing focus

🚨 Run the admin-menu smoke tests in `docs/test-plan-0.2.24.585-admin-menu-registry.md` before relying on this on production.

Focus especially on:

- WordPress admin menu appearance under VMS.
- All VMS Pages rendering.
- Existing pages still opening.
- VMS Data Tools still discoverable.
- Add-ons page still discoverable.
- A temporary test add-on page registering through the new registry.
- Unknown add-on slugs not being silently hidden.

## Not intentionally changed

- No ticket UI behavior.
- No Square Sync Firewall logic.
- No Event Plan save logic.
- No TEC/Woo ticket inventory behavior.
- No visual redesign beyond plain functional menu labels and directory cards.
