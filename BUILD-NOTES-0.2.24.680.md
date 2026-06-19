# VMS 0.2.24.680 — Ticket Sale Styling and Template Replacement Cleanup

## Purpose

This build follows `0.2.24.679` after production visual review found the sale price over-emphasized and a template-replacement workflow left old ticket products visible on the public event page.

## Changes

- Reduces public sale price emphasis so the active sale price is red and slightly stronger without becoming oversized.
- Makes the `On Sale` badge slimmer while keeping the label readable.
- Styles the sale deadline as inline metadata next to the `On Sale` badge instead of forcing another line above the ticket title.
- Adds safe cleanup handling for VMS-owned ticket products that are attached to the linked TEC event but no longer appear in the current Ticketing v2 config.
- Preview now adds `ticket_cleanup / retire_unmapped` actions for stale, VMS-owned ticket products instead of only warning that unmapped products exist.
- Commit now retires those stale products by setting them to `draft` and hidden catalog visibility, preserving order history instead of deleting products.
- Cleanup is safety-gated so products that are not clearly VMS-owned for the current Event Plan / TEC event are left alone and reported as warnings.

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `includes/integrations/ticketing-phase-b.php`
- `assets/css/ticketing-front/95-ticket-public-polish.css`
- `assets/css/vms-ticketing-front.css`
- `BUILD-NOTES-0.2.24.680.md`
- `vms-test-plan-0.2.24.680.md`

## Validation Performed

- `php -l` across all plugin PHP files
- `node --check` across all non-minified plugin JS files
- `zip -T VMS_680_ticket_template_cleanup_sale_polish.zip`

## Notes / Caveats

- This does not hard-delete ticket products. It retires stale VMS-owned ticket products as `draft` + hidden so historical orders remain intact.
- Manually-created or not-clearly-VMS-owned ticket products attached to the same TEC event are intentionally left alone.
