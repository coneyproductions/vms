# Codex Handoff — VMS 0.2.24.740

## What changed

- Converted the public event vendor sidebar into a grouped Event Plan vendor-assignment renderer.
- Public vendor output now resolves from the linked Event Plan and renders grouped public vendor sections instead of limiting the sidebar to a single primary/secondary teaser path.
- Food-related vendor types now merge into one `Food Vendors` section with compact vendor cards that show logo, display name, and cuisine/sub-category when available.
- Legacy `vms_vendor_teaser` and `vms_secondary_vendor_teaser` combinations now collapse into one grouped sidebar on event pages.

## Source-of-truth boundary

- The public renderer reads only Event Plan-owned vendor assignment data:
  - linked Event Plan resolution from the TEC event
  - Event Plan lineup entries
  - Event Plan secondary/vendor assignment map
  - legacy Event Plan fallback fields when canonical Event Plan data is absent
- It does not read ADD-specific storage, review queues, logs, audit state, or temporary dispatch workflow state directly.

## Intentionally not changed

- No ADD assignment logic changes.
- No ADD review/confirm workflow changes.
- No removal of legacy Event Plan fallback fields yet.
- No production deployment.

## Local verification performed

- `php -l` passed for:
  - `vms/includes/public/vendor-profiles.php`
  - `vms/assets/css/vendor-profile-public.css`
- Live Event Plan-backed checks confirmed:
  - event `2881` renders `Food Vendors` (`4` cards) plus `Market Vendor` (`1` card)
  - live one-vendor event `2119` renders one grouped container with one card
  - live event `124` returns no public vendor sidebar markup
  - event `2881` with both legacy shortcodes still renders exactly one grouped container
- Synthetic renderer probes confirmed:
  - one music vendor + one food vendor
  - one music vendor + two food vendors
  - multiple food vendors with subtitle metadata
  - missing-logo placeholder behavior
  - missing-subtitle behavior without empty wrappers
  - market vendor section rendering
- Headless Chrome previews confirmed the compact layout at:
  - `390px` image width
  - `1024px` image width

## Packaging note

- Package name: `vms-0.2.24.740-public-event-vendor-sidebar-grouped-renderer.zip`
