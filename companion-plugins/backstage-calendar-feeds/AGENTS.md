# AGENTS.md

## Scope

- This repository contains the standalone Backstage Calendar Feeds WordPress plugin.
- Product code must remain generic; consumer-specific names belong only in feed-profile configuration.

## Safety boundaries

- Treat all upstream provider systems as read-only.
- Consume DRM Calendar Intake only through its safe router contract.
- Never read private Intake post meta from this plugin.
- Never create, update, delete, or move Google Calendar events.
- Never change Router, Events Bridge, VMS/BVM, Event Plans, TEC/tickets, notifications, or destination subscriptions.
- Preserve conservative privacy and false-BUSY behavior.
- Do not expose raw occurrence/source identities or capability tokens in diagnostics or logs.

## Validation

- Run `php tests/run.php` after changes.
- Validate live data through the safe provider contract only.
- Do not manufacture canary state in a live Intake database.
