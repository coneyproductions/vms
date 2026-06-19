# Codex Handoff — VMS 0.2.24.674

## Focus

Ship a replacement for the broken `0.2.24.662` Event Plan create/save/publish behavior that keeps brand-new no-ticket Event Plans lightweight and guarantees VMS-created TEC events always have a real author.

## High-priority assertions

1. A brand-new Event Plan with no effective tickets must not queue Ticket Integrity, staff-task generation, or duplicate per-event maintenance jobs.
2. Heavy vendor/category and staffing seed work must not run inline during the editor save path for that empty-event case.
3. Per-event background jobs must stay single-flight via `wp_next_scheduled()` plus a transient lock.
4. Every VMS-owned TEC event create/update path must set or backfill a valid `post_author`.
5. Repeated saves of the same empty Event Plan must remain lightweight and must not create duplicate TEC events.

## Known scope

- No DT code changed in this follow-up. DT remains `0.5.46`.
- VMS continues to create the linked TEC event for empty Event Plans so the calendar/public URL path stays intact.
- Ticket Integrity monitor target detection now keys off effective ticket state instead of the mere presence of default ticketing metadata.

## Release package

- Versioned zip filename: `VMS_674_event_plan_lightweight_save_and_tec_author_fix.zip`
- Canonical convenience zip: `vms.zip`
