---
title: 03 Feature Enhancements
slug: feature-enhancements
since: 0.2.24.455
---

# Feature Enhancements

Use `docs/future_enhancements.txt` as the canonical long-form enhancement / idea-pad tracker. This file remains the current sprint / stabilization enhancement-note layer inside the zip.

This is the in-zip working enhancement list for items that are valid, wanted, and still pending.

## Near-term enhancements

- Agreement / contract planning: add core booking-term foundations for cancellation policy profiles, proposal acknowledgements, deposit support, rider uploads, and no-show/nonperformance documentation; render formal agreement packets later through premium add-on `vmsx-agreements`.

- Continue post-publish Event Plan change awareness / unpublished changes workflow
- Agent fee support in Event Plans separate from vendor pay
- Better operator workflow around vendor interest / "I'm Interested" follow-up
- Daily ticket health digest / "State of the Range" polish and expansion
- Better portal-driven "You've been booked" automation flow

## Structural enhancements

- Keep agreement-related operational data in core and document/render/export behavior in premium add-ons; avoid duplicating cancellation/deposit/rider truth across proposal and agreement layers.

- Split Event Plans into smaller feature-owned files using the loader/module audit as the ownership map
- Consolidate ticketing front-end asset ownership
- Expand guided tours coverage so every major module ships with usable help
- Continue universal naming cleanup across admin and portal layers

## Project-process enhancements

- Keep working handoff, bug log, and enhancement log inside every shipping zip
- Prefer docs that explain purpose, risk, and next move — not just raw notes
