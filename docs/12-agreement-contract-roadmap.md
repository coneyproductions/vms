---
title: 12 Agreement Contract Roadmap
slug: agreement-contract-roadmap
since: 0.2.24.566
---

# VMS Agreement / Contract Roadmap

Package note: this is a planning and continuity document. It records the agreement/proposal/cancellation architecture discussed before implementation begins. It does not implement runtime behavior by itself.

## Executive decision

The agreement/contract workflow should become a premium add-on plugin, but the shared booking terms that drive proposals, cancellations, deposits, riders, and audit trails belong in VMS core.

Recommended add-on slug: `vmsx-agreements`.

Core owns the facts and operational terms. The premium add-on renders those facts into agreement packets, PDFs, signed-copy storage, and eventual DocuSign/manual-signature handoff.

## Primary goal

Reduce subjective pressure around difficult booking and cancellation decisions by making key terms objective, visible, acknowledged, and documented before the event is confirmed.

The system should help operators avoid the loop of "is this a good call?" by surfacing agreed criteria such as ticket-sales thresholds, weather/safety conditions, vendor obligations, deposits, rider status, and nonperformance consequences.

## Core responsibilities

VMS core should own these data/workflow foundations:

- Cancellation policy profiles.
- Event viability thresholds and review deadlines.
- Cancellation reason categories.
- Per-event cancellation/booking-term snapshots.
- Proposal-facing cancellation and event viability summary.
- Clause status tracking: included, modified, bypassed, replaced, not applicable.
- Clause bypass reason capture, permission guardrails, and audit trail.
- Deposit fields as part of the compensation model.
- Technical rider and hospitality rider upload/review states.
- No-show / nonperformance documentation workflow.
- Proposal acknowledgement audit records.
- Hooks/data providers so add-ons can render the same terms into formal documents.

## Premium agreement add-on responsibilities

`vmsx-agreements` should own these document-focused features:

- Agreement templates.
- Plain-English agreement/proposal walkthrough content.
- Agreement packet generation.
- PDF export.
- Signed-copy upload and storage metadata.
- Agreement version history.
- Manual DocuSign handoff packet: PDF, signer name/email, suggested email copy, agreement title.
- Later DocuSign / e-sign provider integration.

## Proposal vs final agreement

### Proposal

The proposal should include the plain-English guided tour and acknowledgement checkboxes. This is where the vendor is clearly shown the important business terms before accepting the booking.

Proposal should include:

- Plain-English walkthrough cards.
- Section-level acknowledgements.
- Cancellation / viability explanation.
- Production expectations.
- Compensation and deposit explanation.
- Rider rules.
- Performance commitment / no-show explanation.
- Clause bypass visibility where appropriate for internal/admin review.
- A clear statement that the summary does not replace the final agreement.
- A versioned acceptance/audit record.

### Final agreement

The final agreement should stay clean and formal. It should include the actual clauses and terms, but not the walkthrough checkboxes unless attached as a separate exhibit or receipt.

Final agreement should include:

- Formal agreement language.
- Same terms that were acknowledged in the proposal.
- Formal cancellation/viability clause unless intentionally bypassed or replaced.
- Formal deposit terms.
- Formal rider terms.
- Formal performance commitment / nonperformance terms.
- Approved rider exhibits where applicable.
- Link/reference back to the accepted proposal version.

## Plain-English guided tour

The proposal should include a friendly walkthrough before acceptance. It should explicitly say that it does not replace reading the full agreement.

Suggested intro:

> This walkthrough explains the major parts of the proposal in plain English. It does not replace the final agreement. The goal is to make sure you understand what matters most before accepting or signing final booking terms.

Recommended walkthrough sections:

- Pay & Compensation.
- Deposit, if any.
- Sound, Stage & Production.
- Cancellation & Event Viability.
- Reschedule Preference.
- Performance Commitment & Nonperformance.
- Riders & Additional Requirements.
- Guest List / Comps.
- Promo / communication expectations.

Tone requirement: fair, trust-building, and human. The walkthrough should highlight where terms protect the artist/vendor as well as where they protect the venue.

## Proposal acknowledgements and visible audit receipt

Acknowledgements should live primarily in the proposal, not the final agreement.

Each acknowledgement should visibly show the vendor that it is being recorded. Do not hide this only in an internal log.

Display next to each completed acknowledgement:

- Acknowledged date/time with timezone.
- Acknowledged by name/email or portal identity.
- IP address.
- Browser/device user agent summary when practical.
- Proposal version.
- Acknowledgement ID.

Example display:

> Acknowledged on April 25, 2026 at 5:42 PM CDT by vendor@example.com. IP: 123.45.67.89. Proposal version: 1.2. Acknowledgement ID: ACK-20260425-0007.

Accepted proposal PDFs should include an Acknowledgement Receipt page summarizing all acknowledgements and audit details.

## Cancellation and event viability terms

This is urgent and should not wait for the premium agreement add-on.

Core should support cancellation policy profiles and event-level snapshots so the same policy can drive:

- Proposal language.
- Cancellation decision workflow.
- Vendor agreement packet.
- Refund / event credit workflow.
- Vendor/staff/customer communications.
- Audit history.

Recommended cancellation categories:

- Safety / weather / force majeure.
- Venue operational conditions.
- Event viability / minimum ticket or revenue threshold.
- Vendor cancellation / no-show / nonperformance.
- Mutual written agreement.

Business viability should not be hidden inside force majeure. It should be clearly labeled as an Event Viability Threshold.

Recommended fields:

- Viability review deadline.
- Minimum tickets sold.
- Minimum gross ticket revenue.
- Optional projected attendance threshold.
- Who makes the final call.
- Evidence required.
- Default outcome: cancel, reschedule, or manual review.
- Vendor payment if cancelled before review deadline.
- Vendor payment if cancelled after review deadline.
- Reschedule window.
- Customer handling: refund and/or Event Credit option.

## Clause bypass / alternate terms

VMS must allow some clauses to be bypassed or replaced, especially event viability clauses for proven high-draw performers.

Do not frame this as silently removing a clause. Prefer "Use alternate cancellation terms" or "Clause status".

Clause statuses:

- Included.
- Modified.
- Bypassed.
- Replaced.
- Not applicable.

Bypass guardrails:

- Permission-controlled access.
- Required reason.
- Timestamp.
- User who approved it.
- Event Plan snapshot.
- Warning before proposal is sent.
- Warning before agreement packet is generated.
- Visible internal/admin note.
- Version history.

Suggested internal note:

> Event Viability Clause bypassed. Reason: Established high-draw performer. Operator approved guaranteed booking without ticket-sales threshold. Approved by [user] on [date/time].

Even when event viability is bypassed, the agreement/proposal should still preserve weather, safety, operational-condition, no-show, and mutual-reschedule language.

## Deposit support in core compensation

Deposits should be added to VMS core compensation, especially for primary/band vendors.

Deposit must be separate from base pay, guarantee, bonus, and agent fee.

Recommended deposit fields:

- Deposit required: yes/no.
- Deposit amount.
- Deposit due date.
- Deposit paid: yes/no.
- Deposit paid date.
- Payment method.
- Deposit treatment: refundable, nonrefundable, creditable toward guarantee, separate from guarantee.
- Deposit applies to: vendor pay, agent fee, other.
- Cancellation treatment: retained, refunded, converted to reschedule credit, manual review.
- No-show/nonperformance treatment: subject to return or manual review.
- Notes.

The Event Plan/proposal/agreement snapshot must capture deposit terms exactly as they existed at the time of proposal acceptance or agreement generation.

## Technical and hospitality riders

Vendor portal should support two separate upload lanes:

### Technical rider

For stage plot, input list, monitor needs, power needs, backline needs, soundcheck requirements, and other production details.

### Hospitality rider

For food, drinks, dressing room, towels, lodging, brand preferences, and other hospitality requests.

Rider states:

- No rider.
- Requested.
- Uploaded.
- Under Review.
- Approved.
- Approved with Exceptions.
- Rejected.
- Superseded.

Critical vendor-facing language:

> Uploading a rider does not automatically make it part of the agreement. Rider items are requests unless accepted by the venue in writing.

Agreement/proposal rider rule:

- Riders must be submitted by a configured deadline.
- No rider is binding unless approved in writing.
- Approved riders may become exhibits/attachments.
- If rider and agreement conflict, the agreement controls unless the exception is approved in writing.
- Unapproved hospitality preferences or brand requests are not a valid reason to refuse performance.

## No-show / nonperformance handling

Do not use a default flat penalty fee.

Preferred structure: documented facts, documented direct costs, reasonable cover costs, payment withholding where applicable, future booking consequences, and formal notice workflow.

No-show/nonperformance cases should cover:

- Absolute no-show.
- Same-day artist cancellation.
- Arrival too late to reasonably perform.
- Refusal to perform.
- Leaving before agreed performance is complete.
- Attempting to make performance conditional on unapproved demands.
- Failure to communicate when there is a problem.

Suggested plain-English proposal wording:

> This section is important because the venue, customers, staff, and other vendors are relying on you to appear and perform as agreed. If you do not show up, cancel at the last minute, arrive too late to perform, or refuse to perform for reasons not accepted in writing, the venue may treat that as nonperformance. That may affect unpaid compensation, future bookings, and documented direct costs caused by the nonperformance.

VMS no-show review should document:

- What happened.
- Decision time.
- Attempted contacts.
- Customer impact.
- Staff/vendor impact.
- Ticket/refund/event-credit impact.
- Direct costs / cover costs.
- Payment status.
- Proposed next step.
- Operator notice sent to vendor.

## Implementation order recommendation

Start with core foundations first, then add the premium agreement renderer.

### Phase 1 — Core booking terms foundation

- Add cancellation policy profile data model.
- Add Event Plan booking/cancellation-term snapshot data.
- DONE in 0.2.24.567: added Event Plan compensation deposit fields, canonical meta keys, save/render plumbing, comp hash coverage, and Locked Pay snapshot coverage.
- Add rider upload metadata/status model for vendor portal.
- Add no-show/nonperformance review data model.
- Add hooks/data getters for proposal/agreement rendering later.

### Phase 2 — Proposal terms and acknowledgements

- Add proposal-visible plain-English terms summary.
- Add acknowledgements with visible audit receipt.
- Add proposal version snapshot and accepted-proposal PDF/export record.
- Add clause status/bypass UI with reason/audit trail.

### Phase 3 — Premium add-on agreement packets

- Build `vmsx-agreements`.
- Render agreement packet from core proposal/booking-term snapshot.
- Add PDF export.
- Add signed-copy upload.
- Add manual DocuSign handoff helper.

### Phase 4 — Optional e-sign integration

- Add provider abstraction.
- Add DocuSign or other e-sign provider later.
- Preserve manual/offline path.

## Next implementation thread starting point

Begin with Phase 1 core foundations, but keep the first code pass narrow.

Recommended first coding target:

1. Add core constants/meta-key definitions for deposits, cancellation policy snapshots, rider upload states, and no-show/nonperformance review records.
2. DONE in 0.2.24.567: Event Plan compensation deposit fields with safe save/render plumbing.
3. Add docs/test scaffolding around these fields.
4. Do not build PDFs, DocuSign, or full proposal acknowledgements in the first code pass.

Reason: deposits and rider/cancellation facts are core booking data. The agreement add-on should consume them later, not invent separate copies.
