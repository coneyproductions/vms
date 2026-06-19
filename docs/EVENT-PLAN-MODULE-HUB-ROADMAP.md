# Event Plan Module Hub Roadmap

## North star

The Event Plan should become the command center for an event, not one giant form that submits every connected system at once.

The Event Plan should preserve visibility across modules while each heavy module owns its own editing, validation, syncing, and save path.

## Desired model

Each Event Plan section should eventually become a module summary card with:

- status
- short summary
- warnings / action items
- last updated / last pushed signals where useful
- direct manage/edit link to the owning module workspace

The Event Plan should remain useful at a glance, but a normal Event Plan update should not force ticketing, staffing, agreements, marketing, vendors, ops, and other modules to reprocess unless their data actually changed.

## Phase 0 — visible hub pattern

Build `0.2.24.659` adds the first Event Module Hub metabox on the Event Plan editor.

This is intentionally read-only. It creates the operator-facing pattern without changing save behavior yet.

## Phase 1 — save responsibility map

Audit every Event Plan section and classify it as:

1. **Core shell field** — should remain editable directly on the Event Plan.
2. **Module-owned field** — should move to a module workspace and summarize back to the Event Plan.
3. **Derived/read-only value** — should never be submitted from the Event Plan form.
4. **Publish/sync action** — should run only from an intentional module or publish workflow, not from normal Update.

Likely core shell fields:

- title
- date/time
- venue
- primary status
- featured image
- public description / basic presentation
- primary vendor identity where needed for event identity

Likely module-owned areas:

- Tickets & Add-ons
- Staffing
- Agreements
- Marketing / Meta Ads Builder
- Sponsorships
- Accommodations / hospitality
- Vendor media / promo assets
- Ops / guest list / scanner readiness
- Feedback / post-event survey

## Phase 2 — independent module saves

Convert each heavy section to its own save endpoint or existing workspace flow.

Rules:

- no nested forms inside the WordPress post edit form
- no JSON or pipe-delimited operator inputs
- use buttons/links/admin-post/AJAX/REST actions intentionally
- track module dirty state separately from Event Plan dirty state
- show module summaries back on the Event Plan

## Phase 3 — lightweight Event Plan Update

Normal WordPress Update should save only core shell fields and cheap metadata.

It should not:

- rebuild all ticket products
- resync every ticket history/version row
- recompute every staffing/finance/reporting object unless dirty
- push to TEC/Woo unless a publish/sync action explicitly requires it

## Phase 4 — publish checklist

Publishing should become a controlled checklist.

Example checks:

- core date/time/venue valid
- ticketing valid and pushed
- public image/description ready
- vendor/lineup valid
- staffing warnings visible
- agreement status visible
- marketing/ad warnings visible

Some items may block publish. Others should remain visible warnings.

## Performance goal

The Event Plan form should stop behaving like a full event rebuild.

A simple Event Plan update should be cheap, predictable, and safe even while other operators or Codex are testing module-specific workflows.

