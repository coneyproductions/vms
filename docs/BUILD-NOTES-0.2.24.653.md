# VMS 0.2.24.653 — Legacy GA Public Visibility Guard

## Why this patch exists

A live Event Plan could show **General Admission** as enabled in the saved ticketing config while the public event page omitted GA from the progressive ticket UI.

The trigger was a legacy single-GA sync map combined with a newer template where **Early General Admission** appeared first and was disabled. Older runtime fallback logic could still allow that disabled first row to claim the legacy GA product ID, which made the disabled-ticket public guard hide the real General Admission product.

## What changed

- Disabled ticket public-runtime hiding now only lets a disabled row claim the legacy `map.ga` product when the row clearly represents the real General Admission ticket.
- Specialized rows such as Early / Advance / Presale / VIP / Children / Veteran / Police / Fire / EMT / Nurse / Teacher are not allowed to claim the legacy GA map just because they appear first or carry a reused key.
- Public ticket access/remaining maps now let the real enabled General Admission row inherit the legacy GA product when appropriate, rather than relying on absolute row index `0`.
- The legacy GA helper now checks specialized labels before broad key fallbacks.

## Expected result

For an Event Plan with:

- Early General Admission disabled
- General Admission enabled
- old sync map product `#3885` stored under `map.ga`

The public ticket UI should show General Admission and should not hide product `#3885` because of the disabled Early GA row.

## Files changed

- `includes/integrations/ticketing-phase-b.php`
- `includes/integrations/ticketing-rules-v2.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`

