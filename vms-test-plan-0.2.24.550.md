# VMS Test Plan — 0.2.24.550

## Scope
Consolidated help editors + per-event help editors + guest list admin cache-hardening.

## Test Areas
1. In VMS Settings → Ticketing, confirm both help fields use a normal WP editor with basics like bold, lists, alignment, link, and text color.
2. Confirm the old separate font-size and color inputs are gone.
3. In an Event Plan → Advanced Controls, confirm both public help override fields now use the same editor style.
4. Save a global help message with bold and colored text, then confirm it renders publicly.
5. Save a per-event override with different formatting, then confirm it overrides the global copy publicly.
6. Leave a per-event override blank and confirm the global help copy is inherited.
7. Guest List / Comp Admission: add a new entry, refresh the page, and confirm it remains visible.
8. Add the same guest again and confirm the duplicate warning appears while the prior entry still remains visible after refresh.
