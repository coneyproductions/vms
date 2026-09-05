# Event reschedule customer communications

The occurrence service preserves the affected audience automatically but never sends customer email during reschedule APPLY. Written notice is a separate, reviewed workflow.

## Durable storage

Each occurrence operation owns one unique Event Plan post-meta record:

```text
_vms_event_communication_v1_<operation-uuid>
```

The record contains the operation and Event Plan IDs, reason, old/new occurrence, event and venue snapshots, timestamp/actor/source, and a deduplicated `audience`. Each audience recipient preserves the purchase-time name, email validity and raw email snapshot, user/phone identity where present, every affected order and order item, entitlement kind/label/quantity, reservation identity, and any direct custom-admission record.

An `audience_fingerprint` covers all immutable operation and audience fields. Mutable `recipient_states` are separate and contain Include state, written-notice state, exact BVM subject/body snapshots, two-phase send attempts, informal contact logs, and audit entries. Updates use a compare-and-swap post-meta write and revalidate the immutable fingerprint. This is intentionally one bounded operation-specific record instead of a new table or a plan-wide growing blob.

## Reschedule transaction

After the occurrence service revalidates the preview and migrates affected order items, but before it appends operation history or commits, it builds and uniquely inserts the ledger. The service verifies that the stored audience contains every previewed Woo order-item ID and every affected custom-admission ID. It then reads the stored record back and verifies its fingerprint. A missing service, missing recipient, lost entitlement, failed insert, failed readback, or failed fingerprint aborts and rolls back the complete occurrence transaction.

The transaction does not call the mail transport. A successful operation reports `Date change complete` and the number of affected customers needing written notice.

## Deduplication and states

Recipient identity prefers normalized valid purchase email, then WordPress customer ID, then normalized phone. Order/admission identity is used only when no usable shared identity exists. Matching tokens link a recipient while all orders and entitlements remain nested below that one recipient.

Every recipient starts Include=ON and written notice `Pending`, including reservation-only and missing/invalid-email recipients. Resolved states are `Sent by BVM (accepted)`, `Sent manually / outside BVM`, and `Intentionally excluded`. `Failed` and `Pending` remain unresolved. Informal Phone, In person, or Other contact is logged separately and never resolves written notice.

Exclusion is available only for unresolved recipients and requires a confirmation plus reason; actor and UTC time are retained. Re-inclusion restores Pending. A manual notice requires a written channel and retains actor/time, optional note, and address used without fabricating a BVM message body.

## Reviewed sending

The Event Plan history shows orders, deduplicated customers, aggregate written-notice state, and an operation-specific recipient-ledger link. The ledger contains the Include/customer/email/orders/items/contact/written-state table, editable reason-specific subject and body, Send Pending, Retry Failed, Send Test, explicit per-recipient resend, copy-address, and protected CSV controls. The unresolved warning remains on this Event Plan workflow until every recipient is resolved.

Date-correction copy says the event was incorrectly listed on the old date. Rescheduled copy says the event moved from the old date. Both include event/venue, new date/time, transfer/no-repurchase assurance, and venue-contact guidance. Customer messages are individual. The final subject/body and transport acceptance or failure are stored for each attempt. Normal sending refuses sent/manual/excluded recipients; explicit resend requires confirmation and appends another attempt.

## Event Plan editor isolation

Customer communication controls render inside the Event Plan editor, but they do not belong to WordPress's ordinary post-update form. Every preview, bootstrap, send, retry, test, export, recipient-state, contact, manual-notice, and resend control explicitly targets its own detached `admin-post.php` form rendered in the administrator footer. Communication-specific HTML `required` checks therefore run only when that communication action is submitted. An ordinary Event Plan Update neither validates those controls nor includes their action, nonce, approval, subject, message, or recipient fields.

The Customer communications area uses the same native collapsible-section pattern as Change event date and Event date history. Its collapsed summary is derived from the selected operation's durable state. Fully resolved ledgers and no-impact operations default collapsed. Pending or failed recipients, an affected historical operation with a missing ledger, an unfinished send attempt, or a failed administrator action default expanded. Opening or closing the section is browser-only presentation state and does not write the ledger or occurrence history.

## Retroactive operation bootstrap

Historical bootstrap is scoped to one Event Plan and operation UUID. It requires matching occurrence history and reconstructs only the exact audit order IDs whose items carry that operation UUID and target effective occurrence. Counts and order IDs must match the operation audit. Refund ambiguity, missing/mismatched items, or historical custom admissions that were not operation-stamped fail closed. Preview writes nothing; apply revalidates the fingerprint in a transaction and is idempotent.

```bash
wp --user=ADMIN_ID bvmgr event communication bootstrap EVENT_PLAN_ID \
  --operation-id=OPERATION_UUID \
  --dry-run

wp --user=ADMIN_ID bvmgr event communication bootstrap EVENT_PLAN_ID \
  --operation-id=OPERATION_UUID \
  --apply --confirm=BOOTSTRAP-COMMUNICATIONS
```

Mark one recipient, or omit `--recipient-id` to mark every included unresolved recipient, only after review:

```bash
wp --user=ADMIN_ID bvmgr event communication mark-manual EVENT_PLAN_ID \
  --operation-id=OPERATION_UUID \
  --channel=email_outside_bvm \
  --dry-run

wp --user=ADMIN_ID bvmgr event communication mark-manual EVENT_PLAN_ID \
  --operation-id=OPERATION_UUID \
  --channel=email_outside_bvm \
  --note='Previously sent and reviewed outside BVM.' \
  --apply --confirm=MARK-MANUAL
```

The transitional local-runtime path is `wp ... vms event communication ...`; it delegates to the equivalent legacy-named service.

## Future Reputation procedure — do not run as part of this feature

For Event Plan `5568`, operation `de1814a7-5ada-4e6e-b587-46c1e80eff89`, a later separately authorized production task should:

1. Run the bootstrap dry-run with the exact values below.
2. Require exactly `7` recipients, `8` orders, no ambiguity, and `Email sent by bootstrap: NO`.
3. Apply the identical bootstrap with the confirmation token.
4. Run the manual-notice dry-run and require exactly seven eligible reviewed recipients.
5. Apply the manual status with the confirmation token.
6. Verify `7` resolved / `0` unresolved and send no email.

```bash
wp --user=ADMIN_ID bvmgr event communication bootstrap 5568 \
  --operation-id=de1814a7-5ada-4e6e-b587-46c1e80eff89 \
  --dry-run

wp --user=ADMIN_ID bvmgr event communication bootstrap 5568 \
  --operation-id=de1814a7-5ada-4e6e-b587-46c1e80eff89 \
  --apply --confirm=BOOTSTRAP-COMMUNICATIONS

wp --user=ADMIN_ID bvmgr event communication mark-manual 5568 \
  --operation-id=de1814a7-5ada-4e6e-b587-46c1e80eff89 \
  --channel=email_outside_bvm \
  --dry-run

wp --user=ADMIN_ID bvmgr event communication mark-manual 5568 \
  --operation-id=de1814a7-5ada-4e6e-b587-46c1e80eff89 \
  --channel=email_outside_bvm \
  --note='Venue previously sent and reviewed these notices outside BVM.' \
  --apply --confirm=MARK-MANUAL
```

These commands are documentation only. Installing the feature does not bootstrap this operation, mark anyone notified, or send any message.
