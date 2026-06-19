# Codex Handoff — VMS 0.2.24.642

## Purpose

Patch the remaining Progressive ticket UI polish issue after 0.2.24.641: configured ticket help copy was still not reliably visible on the public Progressive layout, and the add-on section heading/subtext needed an obvious per-event edit path.

## Changes

- Progressive JS now re-applies the configured Tickets help block inside the Progressive section content after the accordion wrapper has moved/reorganized the DOM.
- Add-on help is handled through the same Progressive helper placement path.
- Event Plan Ticketing controls now include per-event fields for:
  - Add-on section heading override
  - Add-on section subtext override
- Public config now resolves add-on heading/subtext as:
  1. Event Plan override
  2. Global VMS Settings value
  3. Built-in default
- Explicit Event Plan ticket/add-on help overrides are allowed to display even when the global help toggle is off, preventing event-specific instructions from being accidentally suppressed.

## Files changed

- `vendor-management-system.php`
- `vms-build.txt`
- `includes/core/registry/constants.php`
- `includes/helpers.php`
- `includes/integrations/ticketing-rules-v2.php`
- `includes/cpt/event-plans.php`
- `includes/cpt/event-plans/partials/advanced-controls.php`
- `assets/vms-ticketing-progressive-ui.js`
- `vms-test-plan-0.2.24.642.md`
- `docs/test-plan-0.2.24.642-progressive-help-addon-heading.md`
- `docs/CODEX-HANDOFF-0.2.24.642.md`

## Primary regression target

Use an event with Progressive ticket UI enabled, GA tickets, verified/free tickets, and add-ons. Confirm the ticket help block appears under **Tickets**, add-on heading/subtext can be changed from the Event Plan, and the 0.2.24.641 GA quantity hotfix still passes.
