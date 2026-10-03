# VMS Express Bar 0.6.31 — scalable event selector polish

## Event selector UX
- Keeps the 0.6.30 event-safety, admission-awareness, and tip implementation unchanged.
- The **Change event** panel now lists only alternative events; the already-selected event remains visible in the primary event card instead of being duplicated inside the selector.
- The event-card grid is height-bounded and scrollable, so a long concert season does not expand the Express Bar page into a wall of cards.
- Desktop retains a two-column event grid; mobile uses a one-column scrollable list with a smaller maximum height.
- All eligible upcoming published Express Bar-enabled events remain reachable; the UI is compact without arbitrarily hiding later events.

## Compatibility
- No changes to resolver ranking, Event Plan eligibility, admission detection, cart metadata, order metadata, age gate, ordering windows, gratuity calculations, or checkout behavior.
