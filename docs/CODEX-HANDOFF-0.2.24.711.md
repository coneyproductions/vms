# Codex Handoff — VMS 0.2.24.711

## What changed

- Added a fail-closed registry probe for premium modules in VMS core.
- `vms_module_is_enabled()` now returns `false` for unregistered modules instead of treating them as effectively enabled.
- This patch is the companion core requirement for MAB `0.1.90`, which now defers privileged boot until the module registry is available and verified.

## Intentionally not changed

- No new licensing options were added.
- No admin-menu redesign was introduced.
- No deployment or staging push was performed.

## Local verification performed

- `php -l` passed for `includes/modules/load.php`, `includes/core/registry/constants.php`, and `vendor-management-system.php`.
- Companion MAB smoke confirmed:
  - empty premium list stays locked
  - explicitly disabled stays locked
  - explicitly enabled loads
  - missing core keeps the add-on locked
  - changed load order still allows safe deferred boot

## Packaging note

- Ship this package together with `vms-meta-ads 0.1.90`.
- Older VMS core builds do not satisfy the intended MAB fail-closed gate behavior.
