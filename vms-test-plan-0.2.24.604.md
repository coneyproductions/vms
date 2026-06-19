# VMS Test Plan — 0.2.24.604 — Private Event Feedback MVP

## Purpose

Verify the first-pass private post-event feedback module can collect one-stop customer feedback for an Event Plan without touching ticketing, checkout, Square sync, Express Bar, or OPS behavior.

The MVP adds an event-specific survey link, a public one-page survey, private response storage, admin summaries, response review, and an Event Plan sidebar link.

## Changed Files

- `includes/core/event-feedback.php`
- `includes/public/event-feedback.php`
- `includes/admin/event-feedback.php`
- `includes/core/load.php`
- `includes/public/load.php`
- `includes/admin/load.php`
- `includes/admin/menu.php`
- `assets/css/vms-event-feedback.css`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- packaged handoff/test docs

## 🚨 Required Browser Verification

This pass adds a new public survey endpoint and admin page. Test on staging/local before production if possible. It does **not** intentionally change ticketing, checkout, Square sync, Express Bar, OPS, vendor portal, or Event Plan save behavior.

### 1. Version markers

1. Confirm the plugin header version is `0.2.24.604`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.604`.
3. Confirm `vms-build.txt` contains `0.2.24.604`.

Expected: all version markers match.

### 2. Admin page loads

1. Go to **VMS → Tools → Event Feedback** or direct URL `wp-admin/admin.php?page=vms-event-feedback`.
2. Confirm the page renders inside the VMS admin shell/top navigation.
3. Confirm the event selector appears.
4. Select a recent Event Plan and click **View Feedback**.

Expected: the page shows a private survey link, response count, at-a-glance ratings, secondary-vendor summary area when applicable, and a responses section.

### 3. Event Plan sidebar link

1. Edit a saved Event Plan.
2. Confirm the **Post-Event Feedback** sidebar metabox appears.
3. Confirm it shows a read-only survey URL and a **View Responses** button.
4. Confirm the metabox does not add a nested form inside the Event Plan editor.

Expected: the Event Plan screen remains saveable and the feedback link can be copied.

### 4. Public survey renders

1. Open the survey link in a logged-out/incognito browser.
2. Confirm the public survey page loads without requiring login.
3. Confirm it shows the event title/date, venue section, bar/bathroom quick ratings, primary vendor section when assigned, and secondary vendor sections when assigned.
4. Expand the bar, bathroom, and vendor detail sections.

Expected: the one-page form is mobile-friendly, short by default, and only reveals extra detail questions when expanded.

### 5. Submit response

1. Fill out a test response with:
   - Overall event rating
   - Bar and bathroom ratings
   - Bar/bathroom detail comments
   - Primary vendor feedback
   - Food truck/secondary vendor feedback including wait-cause checkboxes
   - Final comments
2. Submit the form.

Expected: the page shows a thank-you message and does not expose the response publicly.

### 6. Admin results update

1. Return to `wp-admin/admin.php?page=vms-event-feedback&event_plan_id=EVENT_ID`.
2. Confirm the response count increased.
3. Confirm at-a-glance averages reflect the submitted ratings.
4. Confirm the response appears in the responses list with private comments visible to admin.

Expected: response details are readable internally and grouped by venue, primary vendor, and secondary vendors.

### 7. Security/safety checks

1. Change the `key` query parameter in the survey URL.
2. Confirm the survey shows a feedback-link unavailable message.
3. Submit a form with an invalid or missing token.
4. Confirm it is rejected.
5. Confirm `vms_feedback` posts are not publicly visible or searchable.

Expected: only a valid event-specific link can submit feedback; responses remain private/internal.

### 8. Regression spot checks

1. Open a public event page with tickets.
2. Confirm ticket quantity controls still load.
3. Open Woo checkout.
4. Confirm checkout still loads.
5. Open Express Bar page/menu if the standalone module is active.
6. Confirm no visible change.

Expected: no unrelated ticketing, checkout, or Express Bar regression.

## Rollback

Rollback to `0.2.24.603` if the feedback page causes PHP fatals, public pages break globally, or admin navigation fails. If only an individual survey response is incorrect, keep the build installed and repair the smallest feedback-module issue.
