# VMS 0.2.24.677 — Vendor Application Holding Queue + Operator Responses

## Summary
Adds a safer middle path for vendor applications that should not remain as active Pending items but should also not be rejected or fully approved.

## Changes
- Added a new vendor application status: `holding` / **Holding / Keep on File**.
- Added a status filter dropdown on the Vendor Applications list so operators can quickly view Pending, Holding, Approved, or Rejected applications.
- Changed Pending/Holding row action behavior to route operators to **Review / Respond** instead of one-click approve/reject, so applicant messaging is not skipped.
- Added an **Operator response** area to the application edit screen:
  - Message to applicant.
  - Email-this-message checkbox when an applicant email is available.
  - Private internal operator note.
  - Buttons for Move to Holding, Approve, Reject, Return to Pending, and Save Note Only.
- Holding/Rejection decisions do not create vendor records.
- Approval still creates or syncs the VMS vendor profile and links the submitting user when possible.
- Stores latest response metadata on the application:
  - `_vms_app_last_response_status`
  - `_vms_app_last_response_message`
  - `_vms_app_last_response_sent_at`
  - `_vms_app_last_response_sent_to`
  - `_vms_app_last_response_sent_by`
  - `_vms_app_last_response_email_sent`
  - `_vms_app_operator_internal_note`
- Approval Queue transition audit now records transitions into Holding as well as Approved/Rejected.

## Operator workflow
Use **Holding / Keep on File** for applicants who may be a fit later but should not be treated as approved vendors yet. Holding applications leave the action-needed Pending badge, stay searchable/filterable, and can receive a clear message explaining that Serenade Range is not ready to book immediately without evidence of sufficient ticket draw.

Use **Approve** only when the applicant should become an active VMS vendor record.

## Versioning
Updated:
- `vendor-management-system.php` plugin header to `0.2.24.677`
- `includes/core/registry/constants.php` `VMS_VERSION` to `0.2.24.677`
- `vms-build.txt` to `0.2.24.677`
