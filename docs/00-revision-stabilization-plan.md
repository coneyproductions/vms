---
title: 00 Revision & Stabilization Plan
slug: revision-stabilization-plan
since: 0.2.24.455
---

# Revision & Stabilization Plan

This document lives inside the plugin on purpose so the current cleanup plan travels with the zip.

## Why this pass exists

VMS has reached the point where the biggest risk is no longer missing features. The biggest risk is letting a rich codebase grow without enough consolidation, documentation, and regression discipline.

The goal of this pass is to **tighten the structure without slowing down future feature work**.

## Current read

### Strong already

- Vendor Command Center
- Admissions / Passes / Guest List
- Availability & Date Dispatch
- Ticket Integrity monitoring and daily reporting
- Staff Tasks
- Vendor booking / onboarding automations

### Working but still wants cleanup

- Core bootstrap and loaders
- Event Plans
- Ticketing
- Vendor Portal / Staff Portal
- Venues / Schedule / Holidays
- Settings and admin shell consistency
- Guided tours coverage

### Staged or dormant

- Safety module load path
- Express Bar remnants in core
- Placeholder dashboard subviews
- Ops Console placeholder pages

## Stabilization order

### Phase 1 — Low-risk structural cleanup

Purpose: tighten the foundation without touching the most fragile revenue-critical logic.

Targets:

- Normalize version drift and stale fallback values
- Clean up loader comments and canonical include paths
- Keep admin-only screens out of front-end bootstrap where possible
- Restore in-zip project docs so handoff / bugs / backlog travel with the code

Exit condition:

- Current zip contains the working project docs
- No known stale build fallbacks remain in active loader paths
- Bootstrap is slightly leaner and easier to reason about

### Phase 2 — Loader and inactive-module cleanup

Purpose: make it obvious what is active, staged, or intentionally dormant.

Targets:

- Audit each loader for overlap and legacy residue
- Quarantine or remove inactive remnants where safe
- Clarify module ownership for shared admin pages and assets
- Reduce commented-out historical include lines that create ambiguity

Exit condition:

- One clear path per active area
- Dormant modules clearly parked instead of half-present in live paths

### Phase 3 — Event Plans split and cleanup

Purpose: reduce regression risk in the biggest operational file in core.

Targets:

- Split large Event Plan UI responsibilities into smaller files
- Move inline admin scripting into asset files as touched
- Isolate reusable helpers from metabox rendering
- Preserve feature parity while shrinking blast radius for edits

Exit condition:

- Event Plan changes become more localized and safer to test

### Phase 4 — Ticketing consolidation

Purpose: keep one canonical direction for the most revenue-sensitive area.

Targets:

- Review front-end ticketing asset ownership
- Reduce ambiguity between legacy/fallback/current ticketing paths
- Keep quantity controls and protected interactions stable
- Keep diagnostics and integrity tooling aligned with the canonical path

Exit condition:

- Easier to answer “which file owns this behavior?”
- Lower chance of fixing one ticketing path while leaving another behind

### Phase 5 — Portal polish and universal naming

Purpose: improve clarity without losing capability.

Targets:

- Reduce runtime UI workarounds in portals
- Continue replacing music-specific assumptions in core language
- Make screens feel more venue-neutral and operator-clear

Exit condition:

- Less clutter, fewer one-off UI patches, more universal terminology

## Working rules for future passes

- Do not add new high-risk features on top of unclear ownership
- Prefer surgical cleanup with a visible before/after purpose
- When touching styling, move it into CSS files instead of inline output
- Keep build metadata updated every patch pass
- Keep project docs inside the zip so any thread can recover context faster

## Immediate next targets

1. Finish the low-risk structure pass started in `0.2.24.455`
2. Use the new module audit doc as the active map for core/staged/dormant ownership
3. Decide what stays in core versus what belongs in separate modules
4. Begin the Event Plans file split plan once the foundation is cleaner

## Notes for future handoff updates

Whenever this file is updated, also update:

- `docs/01-project-handoff.md`
- `docs/02-bug-log.md`
- `docs/03-feature-enhancements.md`
- `vms-build.txt`
