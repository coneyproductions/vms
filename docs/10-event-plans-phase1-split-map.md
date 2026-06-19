# Event Plans Phase 1 Split Map

## 0.2.24.488
- Extracted the **Secondary Vendors** server-rendered collapsible shell/wrapper into `includes/cpt/event-plans/partials/secondary-vendors-section.php`.
- Kept the inner Secondary Vendors editor UI in `partials/secondary-vendors.php` and left save-path logic untouched.

## 0.2.24.486
- Hotfix only: Secondary Vendors and Staff now use server-rendered collapsible shells, and the shared editor initializer wires existing shells as well as any remaining bare-title sections.

## Target build
- Build: `0.2.24.488`
- Focus: first safe extraction slices from `includes/cpt/event-plans.php`

## What was extracted in this pass

### 1. Secondary Vendors render block
- New file: `includes/cpt/event-plans/partials/secondary-vendors.php`
- Ownership: render-only Event Plan editor UI for selecting and reviewing secondary vendors
- Why this was safe first: already self-contained and already buffered into HTML before output

### 2. Compensation acknowledgment render block
- New file: `includes/cpt/event-plans/partials/comp-ack.php`
- Ownership: render-only acknowledgment UI for pay-default drift / low-guarantee warnings
- Why this was safe first: self-contained display logic with no save-path ownership

### 3. Time + Lineup & Schedule render block
- New file: `includes/cpt/event-plans/partials/time-lineup.php`
- Ownership: render-only Event Plan editor UI for event bounds, lineup summary, lineup editor, timeline preview, and schedule health
- Why this was the next safe slice: large, render-heavy, and self-contained, but still outside the save path

### 4. Title render block
- New file: `includes/cpt/event-plans/partials/title.php`
- Ownership: render-only Event Plan title/auto-title UI
- Why this was safe: small adjacent render block with no independent persistence path

### 5. Partial rendering helpers
- Lives in: `includes/cpt/event-plans.php`
- Purpose: give Event Plans a canonical way to render/capture template partials before deeper splitting



### 6. Workflow / Status render block
- New file: `includes/cpt/event-plans/partials/workflow-status.php`
- Ownership: render-only Event Plan editor UI for cancellation controls, current status display, and workflow action buttons
- Why this was the next safe slice: self-contained operator UI that meaningfully shrinks the monolith without intentionally changing save logic

### 7. Title toggle behavior hardening
- Lives in: `includes/cpt/event-plans.php` and `includes/cpt/event-plans/partials/title.php`
- Ownership: keep the extracted Title partial behaving like the pre-extraction editor by restoring preview refresh and lock-note visibility on checkbox toggle
- Why this follow-up mattered: Phase 1B exposed a small regression in the extracted auto-title UI, so this pass hardens the render-side behavior without changing Event Plan save logic



### 8. Advanced Controls render block
- New file: `includes/cpt/event-plans/partials/advanced-controls.php`
- Ownership: render-only Event Plan editor UI for calendar troubleshooting, legacy/imported TEC linking, Ticketing v2 host rendering, and the calendar unpublished-warning override
- Why this was the next safe slice: it is a large self-contained operator UI surface that meaningfully shrinks the monolith while leaving the save-path intent in `event-plans.php`

### 9. Staff render block
- New file: `includes/cpt/event-plans/partials/staff.php`
- Ownership: render-only Event Plan editor UI for staffing role cards, threshold/headcount summaries, shift controls, assignment checkboxes, and staffing inline warnings
- Why this was the next safe slice: it is a large self-contained operator UI surface that meaningfully shrinks the monolith while leaving staffing save-path intent in `event-plans.php`

### 10. Primary Vendor Compensation render block
- New file: `includes/cpt/event-plans/partials/compensation.php`
- Ownership: render-only Event Plan editor UI for compensation option tiles, Draft Pay structure editor, lock-pay controls, and pay-acknowledgment rendering
- Why this was the next safe slice: it is one of the largest remaining self-contained operator UI surfaces and meaningfully shrinks the monolith while leaving compensation persistence logic in `event-plans.php`

### 11. Basic details + notices render block
- New file: `includes/cpt/event-plans/partials/basic-details.php`
- Ownership: render-only Event Plan editor UI for top-of-editor integrity notices, booking-prefill notices, Event Date, Venue, and the Holiday card
- Why this was the next safe slice: it removes the remaining top-level context wrapper from the monolith while keeping all save-path and default-pay calculations in `event-plans.php`

## What still stays in the main file for now
- Event Plan meta/data loading and default-pay calculations around the remaining wrappers
- staffing data preparation + save logic
- compensation save/update logic
- Event Plan save logic
- editor runtime scripts / warning logic

## Next safest slices

### Priority A
- remaining host wrappers around primary/basic details surfaces
- read-only formatting helpers around Event Plan admin summaries
- any remaining self-contained admin host wrappers around calendar/ticket helpers

### Priority B
- basic details host wrappers around primary vendor / venue / packages
- read-only Event Plan formatting helpers

### Leave for later
- save-path surgery
- vendor/staff persistence normalization
- publish/ready/cancel logic rewrites
- deep compensation calculation changes

## Rule for the next pass
Keep extracting **render-only** or **read-only helper** sections first.
Do not mix UI extraction with save-logic rewrites in the same pass.
Treat any Event Plan render-path extraction as a Codex checkpoint.

Guided-tour stabilization note:
- `0.2.24.469` makes all tours bound to `admin:vms_event_plan` manual-launch only so Phase 1 editor testing is not blocked by auto-running overlays.


Guided-tour stabilization follow-up:
- `0.2.24.470` adds a manual-launch fallback for the inline Vendor-Managed Guest Admissions **Start Guided Tour** trigger so help remains available without reintroducing editor auto-run.

Latest Phase 1 extraction note:
- `0.2.24.472` moves the Event Plan **Status & Workflow** section into a dedicated partial while keeping save-path ownership in the main file.

Latest stabilization follow-up:
- `0.2.24.473` keeps the Phase 1D extraction in place but fixes post-render workflow button-state drift and re-hardens the Vendor Guest inline tours fallback path after the first smoke run exposed follow-up issues.


Latest Phase 1 extraction note:
- `0.2.24.479` moves the Event Plan **Primary Vendor Compensation** section, including its compensation tile-sync script and Draft Pay editor markup, into a dedicated partial while keeping save-path ownership in the main file.
- `0.2.24.480` moves the Event Plan **basic details + notices** section, including top-of-editor integrity/prefill notices plus the Event Date / Venue / Holiday card UI, into a dedicated partial while keeping default-pay calculations and save-path ownership in the main file.


## 0.2.24.481
- Extracted the Event Plan editor inline script host into `partials/editor-scripts.php`.
- This is a structure-only move intended to preserve behavior while shrinking `event-plans.php`.
