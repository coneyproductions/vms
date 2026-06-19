## 0.2.24.542
- Scope: adds editable public help copy above tickets and add-ons, plus an optional checkbox-style control mode for bundle-style reserved add-ons.
- If rolled back, ticket/add-on explanation copy will disappear from the public flow and any add-ons configured for checkbox mode will revert to the normal quantity-stepper presentation.
- Re-check one normal add-on event and one fire-pit-style event after install: confirm the correct help copy appears, the checkbox add-on can be selected/deselected cleanly, and the standard add-on stepper still works unchanged.

## 0.2.24.539
- Scope: moves vendor intro videos into an operator-review workflow and adds Event Command Center controls for approving submissions, uploading replacements, or switching the public source to an external video link.
- If rolled back, vendor uploads will go back to becoming the live public promo immediately, and operators will lose the new Event Command Center source-selection / review controls.
- Re-check one headliner event with a submitted clip and one event using an external link to confirm the public event page, vendor profile next-show area, and vendor portal all still show the intended source.

## 0.2.24.534
- Scope: adds a CTA button beside the date badge in the VMS native photo-grid cards.
- If rolled back, the VMS photo-grid will still use the polished card layout from **0.2.24.533**, but the empty space beside the date badge will return and the card-level CTA button will disappear.

## 0.2.24.533
- Scope: CSS-only reapply of the VMS native photo-grid polish on top of the **0.2.24.532** baseline.
- If rolled back, the refund-routing fix from **0.2.24.532** remains in the prior baseline, but the VMS photo-grid cards will revert to the taller, less polished layout.
- Re-check any page using **`[vms_events_photo]`** or **`[vms_events_photo_grid]`** for tighter card spacing, polished date badges, and status-pill styling after install.

## 0.2.24.532
- **Run Live Refunds Now** now routes through the canonical Event Plan edit-screen request (`post.php?...&vms_live_refund_now=1`) instead of `admin-post.php`.
- This avoids the blank standalone screen path while still keeping the refund run separate from normal Event Plan saves.
- If rolled back, the cancelled-plan live-refund rerun may return to the blank-screen standalone action failure.

## 0.2.24.531
- Standalone live-refund action now resolves linked TEC-event IDs back to the canonical Event Plan before validating the request.
- Adds clearer invalid-request diagnostics so future routing failures surface the received ID/type.

## 0.2.24.531
- **Run Live Refunds Now** now uses a direct nonce-protected standalone action link instead of a nested hidden form, avoiding invalid nested-form markup inside the post editor.
- Safer on Safari/iPhone and should stop the “standalone refund request form is missing” failure.

## 0.2.24.528
- **Run Live Refunds Now** on cancelled Event Plans is now a standalone admin-post action rather than a submit button inside the main post editor form.
- This prevents unrelated Event Plan save validation from blocking or polluting the refund rerun request.

## 0.2.24.526
- Cancelled Event Plans now show a **Run Live Refunds Now** button when the cancellation policy supports refunds.
- Clicking that button re-scans/refunds remaining eligible orders without requiring the plan to be uncancelled.
- If rolled back, already-cancelled plans will lose the one-click live-refund rerun path and operators will again be stuck with manual per-order refunds after the original cancel pass.
- Re-check a previously cancelled event that had refund candidates and confirm the button appears, confirmation copy is clear, the cancellation job reruns refund discovery/execution, and already refunded lines are not refunded twice.


## 0.2.24.522 rollback focus
- Confirm a newly created reschedule draft opens with **Tickets for this event = On** instead of inheriting the cancelled source plan's forced-off override.
- Confirm a cancelled public event page still shows the reschedule banner/link, but now uses the revised body copy.
- Confirm cancelled/rescheduled event artwork uses the new diagonal ribbon treatment on the single event page, public venue calendar, and any front-end cards that render via `get_the_post_thumbnail()`.
- Confirm cancelled events remain visible on the VMS public venue calendar and show **View Details** instead of **Get Tickets** in the popup CTA.
## 0.2.24.489
- Rollback-safe to `0.2.24.486`.
- Scope is render-only: moves the already-working Secondary Vendors server-rendered collapsible shell/wrapper into its own partial.
- If this checkpoint fails, roll back to `0.2.24.486` and re-check Secondary Vendors collapse/toggle behavior plus standard Event Plan smoke.

# VMS Rollback Notes

## 0.2.24.504

- Rollback-safe with immediate Event Plan editor retest.
- Scope: behind-the-scenes extraction only for the Event Plan staff render-context preparation block.
- If rolled back, re-check Staff section rendering, assignments, tax badges, staffing threshold notes, Save Draft, and reload behavior only.

## 0.2.24.503

- Rollback-safe with immediate Event Plan editor retest.
- Scope: wording-only cleanup for remaining `Supporting Entry / Supporting entries` text in the Event Plan lineup UI.
- If rolled back, re-check the add-supporting button label, supporting summary card label, supporting lineup card eyebrow, and empty-state fallback wording only.

## 0.2.24.495

- Rollback-safe with immediate Event Plan editor retest.
- Scope: restore the Event Plan editor shell to the last confirmed visually-correct `0.2.24.479` baseline while preserving unrelated later plugin changes.
- If rolled back, re-check whether the Event Plan layout drift returns around Primary Vendor Compensation → Secondary Vendors → Staff.

## 0.2.24.486

- Rollback-safe with immediate Event Plan editor retest.
- Scope: restore reliable Secondary Vendors and Staff collapsible shells by rendering those wrappers on the server and wiring them through the shared editor initializer.
- If rolled back, expect Secondary Vendors / Staff collapse behavior to risk returning to the missing-shell state seen after `0.2.24.482`.

## 0.2.24.482

- Rollback-safe with immediate Event Plan editor retest.
- Scope: hotfix for the `0.2.24.481` packaging regression that left the compensation setup block outside PHP execution.
- If rolled back, expect the Event Plan editor to risk cutting off after the extracted top section again.

## 0.2.24.480

- Rollback-safe with immediate Event Plan editor retest.
- Scope: Event Plans render extraction only for the basic-details / notices section.
- If rolled back, re-check the top-of-editor notices, Event Date, Venue, Holiday card, Save Draft, and the carried-forward guided-tour/manual-launch behavior.

## 0.2.24.479

- Rollback-safe with immediate Event Plan editor retest.
- Scope: Event Plans render extraction only for the Primary Vendor Compensation section.
- If rolled back, re-check compensation tiles, Draft Pay fields, lock-pay control state, pay-acknowledgment visibility, Save Draft, and the carried-forward guided-tour/manual-launch behavior.

## 0.2.24.478

- Rollback-safe with immediate Event Plan editor retest.
- Scope: Event Plans render extraction only for the Staff section plus a docs/backlog host correction.
- If rolled back, re-check Staff section rendering, assignment checked state, staffing warnings, Save Draft, and the carried-forward guided-tour/manual-launch behavior.

## 0.2.24.476

- Rollback-safe with immediate Event Plan editor retest.
- Scope: Event Plans render extraction only for the Advanced Controls / ticketing-host section.
- If rolled back, re-check Advanced Controls rendering, Ticketing v2 host mounting, and linked TEC action buttons.

## 0.2.24.475

- Rollback-safe with immediate Event Plan editor retest.
- Scope: guided-tours runtime fallback timing only.
- If rolled back, re-check the inline Vendor Guest **Start Guided Tour** button, floating Help behavior, and normal Event Plan editor load/save interactions.

## 0.2.24.474

- Rollback-safe.
- Scope: canonical continuity docs restore only.
- Runtime behavior intentionally matches `0.2.24.473`; rollback only matters if you need to revert the packaged docs set.


## 0.2.24.482
- Rollback-safe in principle, but this pass moves the inline Event Plan editor scripts into a partial.
- If behavior regresses, compare `includes/cpt/event-plans.php` and `includes/cpt/event-plans/partials/editor-scripts.php`.


## 0.2.24.499

- Rollback-safe with immediate Event Plan editor retest.
- Scope: wording-only cleanup for remaining operator-facing `Band` text in Event Plans.
- If rolled back, re-check Event Plan notices, Ready-state validation copy, tax helper empty-state text, and vendor availability helper text.


## 0.2.24.500

- Rollback-safe with immediate Event Plan editor retest.
- Scope: wording-only label cleanup for the primary vendor selector in the Event Plan lineup block.
- If rolled back, re-check the primary vendor field label and placeholder text only.


## 0.2.24.505

- Rollback-safe with immediate Event Plan editor retest.
- Scope: behind-the-scenes extraction of the compensation render-context/acknowledgment prep only.
- If rolled back, re-check the Primary Vendor Compensation section, compensation tiles, Draft Pay fields, and acknowledgment messaging.


## 0.2.24.506

- Rollback-safe with immediate Event Plan editor retest.
- Scope: Compensation warning visibility when Draft Pay drifts from the live Primary Vendor default, plus an explicit apply action.
- If rolled back, re-check the Compensation section for the vendor-default drift warning card and the apply-default button behavior.


## 0.2.24.507

- Rollback-safe with immediate Event Plan editor retest.
- Scope: diagnostic transparency inside the existing vendor-default drift warning card.
- If rolled back, re-check the Compensation section for the winning-source label, source ladder, and differing-fields list.

## 0.2.24.521
- If rolled back, cancelled TEC public event pages will lose the replacement-event promotion and revert to the generic cancelled-only banner/overlay.
- Re-check that a cancelled public event with a published replacement shows **Event Rescheduled**, a **View New Date** link, and a **Rescheduled** image overlay.
- Re-check that cancelled public events without a live replacement still show the standard cancelled messaging.

## 0.2.24.520

- If rolled back, the cancel/reschedule feature from 0.2.24.519 remains present, but the replacement draft may again inherit cancelled state and the one-save redirect may drop back to a generic admin URL.
- Re-check that entering a replacement date before clicking **Mark Cancelled** opens the new replacement Draft editor directly.
- Re-check that both the one-save path and the already-cancelled fallback path create replacement plans in **Draft**.

## 0.2.24.519
- If rolled back, the cancellation screen will still allow post-cancel rescheduling only in builds at or after 0.2.24.518, but the one-save cancel+reschedule path introduced in 0.2.24.519 will be gone.
- Re-check that entering a replacement date before clicking **Mark Cancelled** still auto-creates and redirects into a linked Draft Event Plan.
- Re-check that the fallback post-cancel **Create Rescheduled Draft** action still appears on already-cancelled plans.

## 0.2.24.518

- Rollback-safe with immediate Event Plan editor retest.
- Scope: cancelled-plan reschedule bridge, linked replacement-draft creation, and redirect into the new draft after creation.
- If rolled back, re-check cancelled Event Plans for the replacement-date field, the Create Rescheduled Draft button, source/destination cross-links, and the post-save redirect target.


## 0.2.24.525
- Cancellation auto-refund is now live by default after explicit operator confirmation on Mark Cancelled.
- Refund execution sends gateway refunds through WooCommerce, handles line-tax refunds, and queues unsupported orders for manual review.

## 0.2.24.524
- Rollback-safe with immediate homepage/public photo-grid retest.
- Scope: new VMS-native public photo-grid shortcode plus front-end card styling for cancelled/rescheduled overlays.
- If rolled back, pages using **`[vms_events_photo]`** or **`[vms_events_photo_grid]`** will stop rendering the VMS card grid until the shortcode is restored or replaced.
- Re-check that a homepage/public page using **`[vms_events_photo limit="4"]`** renders cards, keeps cancelled/rescheduled overlays visible, and links each card to the expected public event page.

## 0.2.24.536
- Rollback-safe with immediate homepage/public photo-grid retest.
- Scope: VMS native photo-grid card layout only; restores centered title/date/button composition and calmer CTA hover behavior.
- If rolled back, the photo grid will return to the side-by-side date/button composition from 0.2.24.534.
- Re-check that active cards show **Get Tickets**, rescheduled cards still route to the replacement listing, and the card stack remains visually centered.
