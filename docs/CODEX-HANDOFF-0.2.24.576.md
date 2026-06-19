# CODEX HANDOFF - VMS Core 0.2.24.576 Verified Credential Profile Controls

## Build

- Plugin: `vms`
- Version: `0.2.24.576`
- Package target: `vms-0.2.24.576-verified-credential-profile-controls.zip`
- Baseline: `0.2.24.575-staffing-template-migration-and-qualification-severity-fix`

## Why this follow-up exists

The operator needs to reset their own verified ticket credential status in order to walk through the customer verification signup flow and identify possible drop-off points.

While reviewing the existing verification flow, the revocation helper also showed a real durability issue: it removed the `vms_verified_programs` user meta entry but did not remove the matching `vms_verified_*` WordPress role. Since VMS eligibility reads both meta and roles, some revocations could leave access active.

## What changed

1. **User profile credential controls**
   - Added **VMS Verified Ticket Credentials** to WordPress user profiles for admins / verification managers.
   - Each configured verified ticket program now has a checkbox to manually approve or revoke access.
   - Added an optional internal note field for the current profile save.
   - Manual changes update the same VMS eligibility storage used by the approval queue.

2. **Revocation durability fix**
   - Updated `vms_ticketing_verification_remove_program()` to remove the matching `vms_verified_*` role.
   - Updated the removal path so user meta is cleaned without reintroducing the revoked program from role-derived status.

3. **Audit metadata**
   - Manual profile changes record reviewed-at, reviewed-by, action, and note metadata for each changed program.

## Files touched

- `includes/integrations/ticketing-verifications.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/06-test-plan.md`
- `docs/01-project-handoff.md`
- `docs/CODEX-HANDOFF-0.2.24.576.md`
- `docs/test-plan-0.2.24.576-verified-credential-profile-controls.md`
- `vms-test-plan-0.2.24.576.md`

## What should be retested

- User profile manual approval and revocation
- Eligibility Approvals approve/revoke flow
- Customer-facing verified ticket signup flow after revoking an existing user
- Verified ticket cart/access behavior for approved vs unapproved users
- Verified ticket program labels and allowance defaults
- User-specific verified allowance overrides

## Important behavior note

Manual user-profile changes do **not** upload proof files and do **not** send customer decision emails. The approval queue remains the normal proof-review path; the profile controls are for admin correction, customer support, and testing.
