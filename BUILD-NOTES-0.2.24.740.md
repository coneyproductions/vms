# VMS 0.2.24.740

## Scope

- Ship the public event vendor sidebar grouping pass as a versioned VMS package.
- Keep the renderer sourced from Event Plan-owned data only.
- Preserve legacy Event Plan fallback fields for compatibility in this pass.

## Source of truth

- Public vendor output resolves `tribe_events -> linked Event Plan`.
- The renderer reads:
  - Event Plan lineup entries
  - Event Plan secondary/vendor assignment map
  - legacy Event Plan fallback fields only when canonical Event Plan data is missing
- The renderer does not read:
  - ADD dispatch tables
  - ADD review queues
  - ADD logs/audit trails
  - temporary ADD workflow state

## What changed

- Added grouped public vendor rendering in `includes/public/vendor-profiles.php`.
- Food-related vendor types now merge under a shared `Food Vendors` heading.
- Public vendor cards use a compact layout with logo, vendor display name, and cuisine/sub-category when available.
- Empty groups are skipped.
- Legacy `vms_vendor_teaser` plus `vms_secondary_vendor_teaser` combinations now resolve to one grouped sidebar instead of duplicate output.
- Added an inline source-of-truth docblock near the Event Plan vendor-group builder so future edits stay aligned with the public data-boundary rules.

## Intentionally not changed

- No direct ADD assignment logic.
- No ADD review workflow behavior.
- No removal of legacy Event Plan fallback fields yet.
- No production deployment.

## Files changed

- `includes/public/vendor-profiles.php`
- `assets/css/vendor-profile-public.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.740.md`
- `vms-test-plan-0.2.24.740.md`
- `docs/CODEX-HANDOFF-0.2.24.740.md`

## Local verification summary

- `php -l` passed for:
  - `includes/public/vendor-profiles.php`
  - `assets/css/vendor-profile-public.css`
- Live Event Plan-backed checks confirmed:
  - event `2881` renders `Food Vendors` with `4` cards plus `Market Vendor` with `1` card
  - a one-vendor event still renders the same grouped container/card layout
  - an event with no assigned public vendors returns no sidebar markup
  - combined legacy shortcodes render one grouped container instead of duplicates
- Synthetic renderer probes covered:
  - one music vendor + one food vendor
  - one music vendor + two food vendors
  - multiple food vendors with subtitle text
  - missing logo placeholder behavior
  - missing subtitle behavior without empty wrappers
  - market vendor presence
- Headless Chrome visual probes confirmed:
  - desktop `1024px` preview kept food cards at `204px` widths in a readable two-column grid
  - narrow `390px` preview remained compact and readable with stacked group sections and comfortable card widths

## Notes

- Local WP-CLI checks still emit unrelated PHP `8.5` deprecation noise from The Events Calendar / Event Tickets. That noise was outside the touched VMS files and did not correspond to renderer failures.
- Headless Chrome emitted transient macOS `task_policy_set` warnings while dumping DOM metrics; screenshots and layout output still rendered correctly.

## Package

- Staging candidate package slug: `vms-0.2.24.740-public-event-vendor-sidebar-grouped-renderer.zip`
