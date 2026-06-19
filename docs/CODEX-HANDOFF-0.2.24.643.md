# Codex Handoff — VMS 0.2.24.643

## Focus
Regression fix for Progressive ticket UI add-ons/amenities not being visible to logged-out visitors.

## Changed
- `assets/vms-ticketing-progressive-ui.js`
  - Adds a robust lookup for the server-rendered `#vms-reserved-addons` block.
  - Moves the block into the Progressive add-on section when found.
  - Rechecks add-on visibility after short delays and on window load so late-mounted public markup is not hidden permanently.
- Version markers bumped to `0.2.24.643`.

## Must Test
Use a logged-out/incognito session on an event with GA + add-ons.
Confirm add-ons are visible and qualification rules still block under-qualified add-on purchases.
