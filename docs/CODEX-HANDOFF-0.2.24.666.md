# Codex Handoff — VMS 0.2.24.666

## Focus

Test cancelled-event public safety. The user normally installs candidate builds on staging too, so use local first for deterministic checks and staging when public/online behavior matters: cache, TEC/Woo rendering, logged-out/incognito pages, WP-Cron, real checkout/cart behavior, and hosting-specific behavior.

## High-priority assertions

1. Cancelled events should disappear from `[vms_events_photo]` output by default.
2. Public logged-out TEC event pages for cancelled events should show cancellation state clearly and should not expose internal cancellation reason/note text.
3. All ticket purchase controls should be unavailable/removed on cancelled event pages, including free children tickets and qualified/free tickets.
4. Direct Woo add-to-cart for any ticket/add-on tied to a cancelled event should fail.
5. Cart/checkout should block if a stale cancelled-event item is already present.
6. Staff cancellation notification support should be verified if a fixture has staff assignments.

## Known scope

This build is a safety hardening pass. It does not rebuild the entire cancellation workflow or redesign public cancellation copy.
