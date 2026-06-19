# VMS Test Plan — 0.2.24.507

## Scope
Vendor-default source transparency in Event Plan Compensation on top of 0.2.24.506.

## Verify
1. Open an Event Plan that shows the **Live Primary Vendor default differs from Draft Pay** warning.
2. Confirm the card now shows:
   - **Winning source**
   - **Source ladder** for global / venue-specific / venue+day defaults
   - **Fields that differ** between the live resolved default and current Draft Pay
3. For a vendor with a venue-specific override, confirm the ladder makes it obvious why the venue-specific amount wins over the global vendor default.
4. If the only mismatch is something hidden before (for example agent-fee settings), confirm the differing-fields list makes that explicit.
5. Click **Apply current Primary Vendor default to Draft Pay** and confirm Draft Pay updates as expected.
6. Save/reload and confirm the warning disappears once Draft Pay matches the live resolved default.
7. Confirm layout, save, reload, compensation tiles, and lock-pay controls still behave normally.

## Guardrails
- No CSS changes
- No layout-shell changes
- No section moves
- No silent overwrite behavior changes
