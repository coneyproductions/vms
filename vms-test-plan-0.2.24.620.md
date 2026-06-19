# VMS Test Plan — 0.2.24.620 Progressive Ticket UI Polish

## Scope

Validate the streamlined Progressive public ticket UI after copy and layout cleanup.

## Setup

- Use a public TEC event connected to an Event Plan with Ticket UI Layout set to **Progressive (Tickets + Amenities)**.
- Use an event with:
  - at least one standard paid ticket,
  - at least one qualified/free ticket,
  - at least one gated amenity/add-on such as a fire pit/table.

## 1. Initial visual load

1. Open the public event page in a clean/incognito session.
2. Scroll to the ticket purchase area.
3. Confirm the ticket section shows one customer-facing title: **Tickets**.
4. Confirm there is no duplicate **Admission** + **Tickets** heading stack.
5. Confirm there is no ticket subtitle under the Tickets header.
6. Confirm the old paragraph beginning “Choose the ticket type for each guest...” is not visible.
7. Confirm the ticket rows are visible immediately.

Expected: The first impression is a simple Tickets area with ticket rows exposed and minimal instructional copy.

## 2. Ticket grouping

1. Confirm General Admission/standard tickets and qualified tickets are in the same Tickets area.
2. Confirm qualified ticket rows still show their short row-level descriptions.
3. Confirm no separate Qualified Discounts section appears.

Expected: Customers choose the ticket type that applies without being pushed through a separate qualified section.

## 3. Qualified-ticket helper disclosure

1. With all quantities at zero, confirm qualified-ticket login/register/claim helper panels are hidden.
2. Increase a qualified ticket quantity to 1.
3. Confirm the relevant helper/claim/login panel appears for that selected qualified row.
4. Decrease the qualified ticket quantity back to 0.
5. Confirm the helper panel hides again.

Expected: Qualified help appears only after the customer indicates they need a qualified ticket.

## 4. Amenities collapsed state

1. Reload the event page.
2. Confirm the amenities accordion title is **Amenities**.
3. Confirm the phrase **/ Add-ons** is not present in the accordion title.
4. Confirm the subtext remains: “Want to add a fire pit, table, or other extras?”
5. Confirm Amenities is collapsed by default.
6. Confirm the collapsed Amenities section does not leave a large empty blank area underneath.

Expected: Amenities stays optional and low-noise until the customer expands it.

## 5. Amenities expansion and selection

1. Expand Amenities.
2. Confirm the expected add-on rows render.
3. Select an add-on that is eligible based on ticket quantity rules, or add the required ticket quantity first and then select the add-on.
4. Confirm subtotal and Add items to cart behavior remains correct.

Expected: Copy/layout cleanup does not change entitlement, quantity, subtotal, or cart rules.

## 6. Mobile/tablet check

1. Test at phone width around 390px.
2. Test at tablet width around 768px to 1024px.
3. Confirm Tickets remains readable, non-duplicated, and exposed.
4. Confirm Amenities remains collapsed and compact.
5. Confirm there is no horizontal overflow.

Expected: The streamlined two-section flow is readable and compact across devices.

## 7. Regression guardrails

- Legacy/classic ticket UI should not be affected by the Progressive-only ticket help suppression.
- Existing add-on gating should still block ineligible add-ons.
- Existing qualified-ticket verification/claim logic should still enforce account/allowance rules.
- The public calendar 16:9 list-card fix from 0.2.24.619 should remain intact.
