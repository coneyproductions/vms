## 0.2.24.610 Thread Update

- Build: `0.2.24.610`
- Package: `vms-0.2.24.610-email-followups-recipient-batch-send.zip`
- Scope: Email Follow-Ups manual-send safety/polish.
- Fixes the duplicate empty “Custom Follow-Up” template bug introduced by the Add Template starter body.
- Adds a cleanup migration for empty placeholder custom templates created by that bug.
- Adds selected-recipient manual sends so admins can include/exclude individual ticket buyers or send to one person.
- Adds 50-recipient batch steps with a Continue Sending flow to reduce timeout risk and avoid guessing who received an email.
- Test plan: `docs/test-plan-0.2.24.610-email-followups-recipient-batch-send.md`.

## 0.2.24.609 Thread Update

- Build: `0.2.24.609`
- Package: `vms-0.2.24.609-email-followups-template-save-character-cleanup.zip`
- Scope: Email Follow-Ups Templates UI polish and text cleanup.
- Added per-template save buttons below each template body and inside the Add Template card.
- Added sanitizer/migration coverage for mojibake/smart-character artifacts seen after saving Email Follow-Up templates.
- Test plan: `docs/test-plan-0.2.24.609-email-followups-template-save-character-cleanup.md`.

## 0.2.24.608 Thread Update

- Build: `0.2.24.608`
- Purpose: make Email Follow-Ups easier to understand and more flexible after live preview/test usage exposed that global timing fields were not obvious.
- Added per-template send timing controls so each template clearly says whether it is manual-only, before the event, day-of, or after the event.
- Added a global default signature plus `{signature}` token and migrated existing templates to include that token when missing.
- Added custom template creation/deletion from the Templates tab for one-off or reusable follow-up needs beyond the starter templates.
- Test plan: `docs/test-plan-0.2.24.608-email-followups-template-timing-signatures.md`.

## 0.2.24.607 Thread Update

- Build: `0.2.24.607`
- Purpose: add safer first-name personalization to Email Follow-Ups after live test emails succeeded.
- Added `{customer_first_name}` and `{customer_greeting}` tokens. `{customer_greeting}` is preferred for salutations because it falls back cleanly to `Hi there,` when the customer first name is unavailable.
- Updated default/saved templates to include the smart greeting without duplicating templates that already contain a customer/name token.
- Test plan: `docs/test-plan-0.2.24.607-email-followups-smart-greeting-tokens.md`.

## 0.2.24.606 Thread Update

- Build: `0.2.24.606`
- Purpose: repair Email Follow-Ups preview/testing so post-event feedback invitations can target recently ended events, not only future Event Plans.
- Key behavior: Preview & Test now includes past Event Plans from a one-year lookback, future Event Plans from a one-year lookahead, labels past/today entries, and keeps the currently selected Event Plan available even if outside the default window.
- Safety: automatic scheduled sends remain controlled by Email Follow-Ups settings; this pass only changes admin preview/test event selection behavior.
- Test plan: `docs/test-plan-0.2.24.606-email-followups-past-event-preview.md`.

## 0.2.24.605 Thread Update

- Build: `0.2.24.605`
- Purpose: integrate Email Follow-Ups with the private Event Feedback MVP so post-event thank-you emails can include an event-specific feedback survey link.
- Key behavior: `{feedback_url}` now resolves inside the Post-Event Thank You template; post-event email preview links are available from the Event Feedback admin screen and Event Plan sidebar metabox; feedback submissions are logged back into Email Follow-Ups.
- Safety: automatic scheduled sends remain off unless explicitly enabled in Email Follow-Ups settings; staging/Codex verification required before sending to real buyers.
- Test plan: `docs/test-plan-0.2.24.605-feedback-email-integration.md`.

## 0.2.24.597 Thread Update

- Build: `0.2.24.597`
- Purpose: repair the 0.2.24.596 direct-URL permissions regression caused by compacting the VMS submenu too early in WordPress admin boot.
- Change: left-rail compaction now runs at `admin_head` after WordPress has already validated direct admin page access, but still before the visible left menu renders.
- Testing: staging/Codex verification required before production; see `docs/test-plan-0.2.24.597-admin-menu-direct-url-access.md`.

## 0.2.24.595 Thread Update

- Build: `0.2.24.595`
- Purpose: repair the broken `0.2.24.594` package by rebuilding from `0.2.24.593`, preserving Guided Tours files, and keeping Square Sync Protection inside the shared VMS admin shell.
- Testing: staging/Codex verification required before production; see `docs/test-plan-0.2.24.595-square-sync-shell-package-repair.md`.

## 0.2.24.593 Thread Update

- Build: `0.2.24.593`
- Purpose: correct the compact admin menu IA so the left WordPress VMS submenu matches the primary VMS top-nav headings without expanding into individual page sprawl.
- Key check: left VMS submenu should show Dashboard, Planning, Vendors & Staff, Marketing & Social, Venues, Settings, Tools.
- Important: Event Command Center and Square Sync Protection remain top-nav/directory/direct URL pages, not standalone left-menu items.

## 0.2.24.592 Thread Update

- Corrected the admin menu overcorrection from 0.2.24.591: the WordPress left VMS rail remains a concise section-launcher menu, not a page-by-page list.
- Event Command Center and Square Sync Protection are registered for top-nav and All VMS Pages discovery instead of being forced into the left rail.
- Kept direct URLs and VMS shell/active-section handling intact for `admin.php?page=vms-event-command-center` and `admin.php?page=vms-square-sync-protection`.
- No ticketing, Square payment, product sync, Event Plan save, or Express Bar product logic was changed in this pass.

---
title: 01 Project Handoff
slug: project-handoff
since: 0.2.24.455
---

# Project Handoff

This file is the in-zip working handoff so the current operational picture is never separated from the code package.

## Current build

- Build: `0.2.24.605`
- Focus: Event Feedback + Email Follow-Ups integration.
- Immediate goal: send a post-event thank-you email that includes a secure event-specific feedback link, then review submitted feedback privately in VMS.
- Operator action after install: open VMS → Event Feedback, select the recent Event Plan, use **Open Post-Event Email Preview**, send a test email to the admin address, submit one feedback response, and confirm the Email Follow-Ups log records the submission.

## Canonical continuity docs restored

- `docs/VMS — Market Readiness Checklist (CANONICAL).txt`
- `docs/VMS_MASTER_HANDOFF.txt`
- `docs/bugs.txt`
- `docs/backlog.txt`
- `docs/future_enhancements.txt`
- `docs/idea_pad_context.txt`
- `docs/KEYS-CONSTANTS-REGISTRY.md`
- `docs/vms_add-on_convention.md`

These restored files are now the authoritative continuity binder inside the shipped zip. The current stabilization-thread snapshot still lives in this file plus `docs/05-revision-log.md`, `docs/06-test-plan.md`, and `docs/10-event-plans-phase1-split-map.md`.

## What was just done

- Integrated Email Follow-Ups with the private Event Feedback MVP.
- Added `{feedback_url}` as an Email Follow-Ups template token that resolves to the selected event's private survey URL.
- Updated the Post-Event Thank You default template and added a one-time migration so existing stored post-event templates receive the feedback link if missing.
- Added invite/recipient hash markers to emailed feedback URLs without exposing raw order IDs or raw customer email addresses in the link.
- Logged feedback submissions back into Email Follow-Ups with a `feedback_submission` action when the logging module is available.
- Added Event Feedback → Post-Event Email Preview shortcuts in the Event Feedback admin card and Event Plan sidebar metabox.
- Preserved first-pass safety: automatic scheduled sends remain off unless explicitly enabled, and manual sends still require a confirmation checkbox.

## 0.2.24.604 Thread Update

- Added a private, one-stop post-event survey for Event Plans so customers can review the venue, bar, bathrooms, primary vendor, and secondary vendors from one link.
- Added event-aware survey generation that pulls the Event Plan title/date, venue, primary vendor, and assigned secondary vendors.
- Added food-truck-friendly diagnostics for slow service: wait time/speed, friendliness, ordering/payment, selection, price/value, food/product quality, accuracy, bring-back preference, and likely wait causes.
- Added private VMS admin review with response count, average ratings, secondary vendor summaries, and detailed response cards.
- Added Event Plan sidebar metabox for copyable survey URL and quick response review.
- Next recommended test: run `docs/test-plan-0.2.24.604-event-feedback-mvp.md`, then share the private link with recent attendees.


## 0.2.24.603 Thread Update

- Follow-up to `0.2.24.602` after live/customer-style testing showed the duplicate-email fix was not enough: the UI could still treat the logged-in approved buyer as covering only one qualified ticket.
- Root cause addressed: the per-assignee allowance resolver was using the public ticket max-qty value as a cap on the verified profile/default allowance. That made higher profile overrides or direct grants appear to save but not change the public claim flow.
- Updated allowance resolution so verified/profile allowances and direct-grant quantities can cover the buyer's own selected tickets before asking for additional guest emails.
- Next recommended test: run `docs/test-plan-0.2.24.603-qualified-ticket-self-allowance-fix.md`, especially default Veteran allowance `2`, per-user override `4`, and over-limit rejection.

## 0.2.24.602 Thread Update

- Started from stable `0.2.24.600`; do not treat `0.2.24.601` as the active baseline unless separately revalidated.
- Fixed qualified-ticket multi-claim validation so an approved assignee can use multiple tickets up to the effective event allowance instead of being blocked by a premature duplicate-email guard.
- Updated front-end assignment validation, AJAX guest-email validation, cart/add-to-cart/checkout revalidation, version markers, and packaged test docs.
- Next recommended test: run `docs/test-plan-0.2.24.602-qualified-ticket-multi-claim-fix.md`, especially default Veteran allowance `2`, per-user override `4`, and over-limit rejection.

## Current priority order

1. Staffing follow-through: rerun the repaired template migration/apply path on real Event Plans
2. Event Plans stabilization / split phase 1 follow-through
3. Dormant / staged module audit cleanup
4. Ticketing consolidation
5. Portal polish and universal naming cleanup

## Watchouts

- Event Plans is still the largest high-risk file in the plugin even after the first extraction.
- Ticketing remains business-critical and fragile because of surface area.
- Vendor Portal is powerful but still carries workaround-style UI logic in places.
- Some modules still appear present in code even when they are not clearly active.

## Next recommended task

Run the packaged qualified-ticket regression for `0.2.24.579`, with special attention to multi-guest approved-email claiming, mixed paid + qualified orders, mobile layout, and accessibility basics on the event purchase page.

## Build notes

- Keep `vendor-management-system.php`, `includes/core/registry/constants.php`, and `vms-build.txt` synchronized.
- Keep the docs in this folder updated whenever a meaningful pass lands.


- 0.2.24.482: extracted Event Plan editor inline scripts into `includes/cpt/event-plans/partials/editor-scripts.php` (no intended behavior change).


- 0.2.24.486: added server-rendered collapsible shells for Secondary Vendors and Staff and updated the shared editor initializer to wire existing collapsible sections.


## 0.2.24.489
- Event Plans Phase 1K: moved the working Secondary Vendors server-rendered shell/wrapper into its own partial (`partials/secondary-vendors-section.php`) while keeping the inner editor UI and save-path logic unchanged.


## 0.2.24.567
- Added Event Plan Deposit / Advance fields to Draft Pay: amount, status, treatment, due date, paid date, and notes.
- Added canonical deposit meta keys and included deposit terms in compensation term reads, hashes, snapshots, and summary lines.
- Preserved event-specific deposit terms when applying/re-applying compensation packages.
- Added packaged regression test notes for deposit persistence, Locked Pay snapshots, hash drift, and clearing behavior.
## 0.2.24.566
- Docs-only/planning package for Agreements / Proposals / Booking Terms.
- Added `docs/12-agreement-contract-roadmap.md` as the canonical implementation outline for cancellation/event viability policy profiles, proposal acknowledgements, clause bypass, deposit support, rider uploads, no-show/nonperformance documentation, and the future `vmsx-agreements` premium add-on.
- Updated backlog, idea pad, future enhancements, add-on convention, revision log, and packaged test notes so the next thread can start actual code writing from one current zip.
- No runtime feature behavior was intentionally changed beyond version/build marker synchronization.


## 0.2.24.568 Thread Update

- Added Event Plan final payment terms and `ACH / Direct Deposit` payment method as core compensation data.
- Agreement add-on should consume these terms from `vms_get_event_plan_comp_terms()` for vendor-facing payment summaries.
