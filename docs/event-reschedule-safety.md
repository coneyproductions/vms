# Published Event Plan occurrence safety

Published Event Plans no longer accept ordinary date/time edits. Draft plans remain editable. In the published editor, **Change event date…** opens the controlled preview/apply workflow. The same domain service powers the admin workflow and WP-CLI.

## Current and historical storage

| Surface | Current/effective occurrence | Historical data that is preserved |
| --- | --- | --- |
| Event Plan | `_vms_event_date`, `_vms_start_time`, `_vms_end_time`, `_vms_event_plan_start_datetime`, `_vms_event_plan_end_datetime` | `_vms_event_occurrence_history_v1` |
| Linked TEC event | `_EventStartDate`, `_EventEndDate`, `_EventStartDateUTC`, `_EventEndDateUTC`, `_EventTimezone` | Event Plan occurrence history |
| Ticket/reservation products | Linked plan/event IDs, current product title, `_ticket_start_date`, `_ticket_end_date` | Product ID, SKU, prices, stock, total sales |
| Woo order items | `_vms_effective_event_*` and the human-facing `When` value | `_vms_event_date_snapshot`, `_vms_event_when_snapshot`, `_vms_event_title_snapshot`, `_vms_original_order_item_name_snapshot` |
| Attendees | Existing TEC event/product/order/order-item links | Attendee ID, security code, check-in state |
| Guest assignments and custom admissions | Existing order-item or Event Plan identity | Assignment payload, admission ID, quantities, check-in state |

Order IDs, order/item IDs, order status, payments, prices, discounts, taxes, quantities, inventory, attendee identities, reservation identities, refunds, purchaser details, and check-in values are invariants. Apply aborts and rolls back if they change.

## Legacy purchase-snapshot compatibility

Occurrence reads accept strictly validated date-only purchase snapshots in `Y-m-d` and the evidenced historical `M j, Y` form (for example, `Sep 12, 2026`). These values are interpreted as calendar dates without creating a time or converting between timezones, so September 12 cannot shift to September 11 or 13. Impossible dates, ambiguous numeric forms, arbitrary prose, partial dates, and unsupported formats remain unrecognized.

This compatibility is read-only. Integrity checks and reschedule previews do not backfill or normalize historical order-item metadata, and order-level notes are never used as authority for an individual line item's occurrence. Effective-occurrence metadata retains precedence, followed by the immutable date snapshot and the existing `When` fallback.

## Date correction and reschedule

- **Date correction** records that the stored occurrence was wrong.
- **Event rescheduled** records a real scheduling change.
- With no affected entitlements, the preview labels the operation as a lightweight correction, but it still uses the audited service.
- With tickets, admissions, reservations, or guest assignments, the preview shows affected orders, paid/free units, admission/reservation categories, numbered and multi-quantity reservations, assignments, products, attendees, and unique contacts.
- Repair mode supports an Event Plan that is already on the new date while linked order items/products still carry the expected old occurrence. It never requires reverting the plan.
- Any third occurrence, mismatched link, unknown entitlement type, missing calendar event, or unauthorized actor blocks apply.
- External ticketing produces a warning; VMS does not mutate third-party providers.

Apply starts a database transaction, re-runs the preview, writes the whole current occurrence, appends audit history, verifies invariants and current-occurrence integrity, then commits. Any exception or failed verification rolls back. Repeating an already recorded operation is a no-op.

The Event-Day Guest & Reservations Report and Event Plan editor show a calculated mismatch warning. Preserved purchase snapshots and event history do not count as mismatches after an effective occurrence is present.
The standalone Event-Day document uses `assets/css/vms-event-day-report.css` and `assets/js/vms-event-day-report.js`; it does not emit executable inline assets.

## WP-CLI

Always preview first with an explicit user who can edit the Event Plan. `--user` is a WP-CLI global parameter and must appear before the BVM command path:

```bash
wp --user=ADMIN_ID bvmgr event reschedule EVENT_PLAN_ID \
  --old-start='YYYY-MM-DD HH:MM' \
  --new-start='YYYY-MM-DD HH:MM' \
  --reason=date_correction \
  --dry-run
```

After reviewing an allowed preview, apply the identical inputs with the explicit token:

```bash
wp --user=ADMIN_ID bvmgr event reschedule EVENT_PLAN_ID \
  --old-start='YYYY-MM-DD HH:MM' \
  --new-start='YYYY-MM-DD HH:MM' \
  --reason=date_correction \
  --apply --confirm=RESCHEDULE
```

Verify current/effective integrity independently:

```bash
wp bvmgr event integrity EVENT_PLAN_ID
```

The transitional `wp vms event reschedule` and `wp vms event integrity` aliases invoke the same command classes for compatibility with existing operational instructions.

Installing or updating this code performs no occurrence migration. Operators must explicitly preview and apply each correction/reschedule.
