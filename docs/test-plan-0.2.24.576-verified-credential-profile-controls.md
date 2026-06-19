# VMS 0.2.24.576 Test Plan - Verified Credential Profile Controls

## Goal

Verify that admins can manually approve/revoke verified ticket credentials from a WordPress user profile and that revocation fully removes both user meta and the matching `vms_verified_*` role.

## Build under test

- Plugin: `vms`
- Version: `0.2.24.576`
- Package: `vms-0.2.24.576-verified-credential-profile-controls.zip`

## Preconditions

1. Install/replace VMS Core with `vms-0.2.24.576-verified-credential-profile-controls.zip`.
2. Confirm WordPress shows VMS version `0.2.24.576`.
3. Confirm `vms/vms-build.txt` reads `0.2.24.576`.
4. Confirm `vendor-management-system.php` header and `includes/core/registry/constants.php` define version `0.2.24.576`.
5. Log in as an administrator or a role with VMS verification-management permissions.

## Tests

### 1. User profile manual credential controls

1. Open **Users** in wp-admin.
2. Edit a test customer user, or your own admin user for flow testing.
3. Confirm the profile shows **VMS Verified Ticket Credentials** above **VMS Verified Allowances**.
4. Confirm each configured verified ticket program appears with an **Approved for verified ticket access** checkbox.
5. Check one program, add an optional note, save the profile.
6. Reload the profile.
7. Confirm the same program remains checked and shows the approval details/note.

### 2. Front-end eligibility follows manual approval

1. Log in as the edited user.
2. Open an event with a ticket requiring the approved verified program.
3. Confirm the verified ticket is accessible according to the existing VMS ticketing rules.
4. Confirm other unapproved verified programs still behave as unapproved.

### 3. Manual revocation clears access completely

1. Return to the user profile as an admin.
2. Uncheck the approved program and save.
3. Reload the profile and confirm the program shows **Not currently approved**.
4. Confirm the user's `vms_verified_programs` user meta no longer contains that program.
5. Confirm the matching role, such as `vms_verified_veteran`, is removed from the user.
6. Log in as that user and confirm the verified ticket flow now treats the user as not approved.

### 4. Existing approval queue revocation regression

1. Find or create a normal verification request and approve it through **VMS → Vendors & Staff → Eligibility Approvals**.
2. Confirm the user becomes verified.
3. Revoke that approval from the Approved view.
4. Confirm access is removed and the matching `vms_verified_*` role does not remain behind.

### 5. Public customer signup retest

1. With your own test status revoked, visit the customer-facing verification entry point from My Account or a verified ticket prompt.
2. Submit a fresh credential request.
3. Confirm the request appears in **Eligibility Approvals**.
4. Approve or deny the request and confirm the existing customer notification behavior still works.

## Regression checks

- **VMS → Vendors & Staff → Eligibility Approvals** still loads.
- Program label editing still saves.
- Verified ticket allowance defaults still save.
- User-specific allowance overrides still save.
- Verified ticket cart enforcement still recognizes approved users and blocks unapproved users.

## Codex testing note

🚨 If Codex makes even a minimal code repair while testing this build, Codex must update all relevant version markers and packaging docs in the same pass before returning a replacement zip. At minimum this includes the plugin header version, `VMS_VERSION`, `vms-build.txt`, changelog/revision notes, this test plan or follow-up test notes, Codex handoff notes, and the package filename. Do not return a modified build with stale versioning/docs.
