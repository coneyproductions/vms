# VMS Test Plan — 0.2.24.539

## Scope
Vendor intro video review workflow, Event Command Center promo-video source controls, and public rendering of approved upload vs external link sources.

## Preflight
- Install the zip over the latest working VMS build.
- Clear any page/cache layers.
- Confirm **VMS → Event Command Center** loads normally.

## Test 1 — Vendor submission stays pending
1. Link a vendor account to a headliner vendor profile.
2. Assign that vendor as the primary/headliner on an upcoming Event Plan.
3. Log in as that vendor and open **Vendor Portal → Dashboard**.
4. Submit a short intro clip.
5. Confirm:
   - the portal says the clip was **submitted for review**
   - the submitted clip preview appears in the vendor portal
   - the public event page does **not** change yet just from the vendor submission alone

## Test 2 — Approve vendor-submitted clip from Event Command Center
1. Open **VMS → Event Command Center** for the same Event Plan.
2. In **Promo Video Control**, confirm the vendor submission appears under **Waiting for review**.
3. Click **Use Submitted Clip**.
4. Confirm:
   - the current public source updates
   - the pending vendor submission clears
   - the public event page now shows the clip
   - the vendor profile next-show area also shows the clip

## Test 3 — Upload replacement from admin side
1. In **Promo Video Control**, upload a different MP4/MOV/WebM under **Upload a replacement video**.
2. Confirm the newly uploaded file becomes the live public source.
3. Reload the event page and vendor profile next-show area to confirm the replacement clip is what renders.

## Test 4 — Switch to external link source
1. In **Promo Video Control**, paste a direct YouTube or Vimeo video URL.
2. Save the external link.
3. Confirm:
   - the current public source changes to the external video
   - the event page and vendor profile next-show area render the external source or a valid open-video fallback
   - the vendor portal current-public preview reflects the external source

## Test 5 — Clear current live promo
1. In **Promo Video Control**, click **Clear Current Public Video**.
2. Confirm the public event page no longer shows a promo video.
3. Confirm the vendor submission area remains empty unless another pending clip exists.

## Test 6 — Remove submitted clip
1. Submit a vendor clip again so a pending review item exists.
2. In **Promo Video Control**, click **Remove Submitted Clip**.
3. Confirm the pending review preview disappears.
4. Confirm the live public source is unchanged if one was already active.

## Test 7 — Soft-requirement status labels
1. For a headliner booking with no clip, confirm the vendor portal still shows **Video needed**.
2. Submit a clip but do not approve it yet.
3. Confirm the vendor portal changes to **Video submitted for review**.
4. Approve it and confirm the label changes to **Video ready**.

## Regression checks
- Public event page still appends the simplified intro-video block below the event description.
- Vendor profile next-show promo area still renders cleanly.
- Event Command Center page still loads and the menu link remains present.
- No PHP warnings/fatals on vendor portal, vendor profile, or Event Command Center.
