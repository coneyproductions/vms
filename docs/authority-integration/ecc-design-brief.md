# ECC design brief — documentation only

Source inspected: integrated `includes/admin/event-command-center.php`, particularly `build_payload`, `build_module_hub_payload`, `build_module_hub_cards`, `get_staffing_snapshot`, `get_timeline_rows`, `build_alerts`, `get_marketing_snapshot`, and full-page rendering. Current ECC mixes an operational overview, repeated ticket/financial summaries, participant lists and a promo-video manager; the Event Plan also embeds a six-card module hub. This task changes only required authority labels and the shared financial summary, not layout or workflow.

## Proposed information architecture

| Area | Primary content | Detail/action destination |
| --- | --- | --- |
| Show-day operations (default) | Event identity/status/time/venue; current weather risk; paid/free/attendance counts with source/freshness; planned/assigned/proposed/confirmed staff; current schedule; ranked actionable issues | Event Plan for event configuration; ticket integrity/attendance reports; Weather workspace; staffing editor |
| Financial | Transaction ticket receipts; separate modeled revenue; planned labor and confirmed commitment estimate; operator-reported direct/processing costs; explicit margin components and missing costs | Dedicated financial report for comparisons, channels, provenance and historic evidence; Event Plan for compensation inputs |
| People | Talent and vendors with actual booking/status evidence; staff by lifecycle state; missing qualifications and confirmed conflicts; links to relevant agreements | Event Plan roster/rates/templates; owning staff portal Accept/Decline; vendor portal submissions |
| Marketing / communications | Public listing readiness, promo review status, enabled delivery capability, upcoming/failed scheduled messages and explicit unknown states | Ads workspace, Social Sharing queue, Outreach workspace and communication ledger; no send-on-open |
| Documents / agreements | Required/missing/signed agreement status and securely authorized document links | Agreements workflow and private document owner surface |
| Quick actions / reports | A small context-sensitive action list ordered by actual issue severity; ticket, staffing, financial and communication reports | Dedicated reports; avoid second copies of full editor forms |

## Placement rules

Event Plan owns durable intent: event details, schedule configuration, ticket links, roster/positions/compensation, deliberate lifecycle actions, marketing assets, document requirements and planning inputs. ECC answers what is happening, what evidence supports it, and what action is needed now. Reports own cross-event comparisons and dense provenance. Staff/vendor portals show only the authenticated person's assignments, response actions, agreements and submissions; never general financial/private staffing data.

## Authority and display requirements

- Assigned is Proposed + Confirmed; never use a green assigned count alone as proof of committed coverage. Display both states and open planned/required-now counts. Declined/Canceled remain history and do not cover positions.
- Planned labor estimates active required positions, including unfilled positions. Committed labor estimates confirmed assignments at configured/overridden rates. They overlap, so never add them. Actual paid labor is unavailable absent payroll evidence.
- Ticket receipts retain provider/channel/refund/freshness scope. Manual costs are explicitly reported, forecasts modeled. The provisional ticket contribution excludes labor and missing expenses; planning scorecards use planned labor. No universal total or final profit badge without sufficient evidence.
- Unknown provider availability must not turn into zero or an all-clear health status. Show calculation time separately from upstream synchronization time.
- Financial provider failure and staffing availability are independent. A stale financial provider must not hide current staffing decisions.
- Weather tracking availability is not itself a venue safety clearance. Marketing workspace availability is not proof of a published campaign, event-level spend or successful delivery.

## Later implementation acceptance

Use the shared staffing and financial contracts on full/light ECC and Event Plan. Verify proposed/confirmed/declined/canceled, empty/unavailable authority, paid/zero/refunded/stale receipts, permissions and portal isolation. At narrow/mobile widths prioritize event identity, critical issues and next action; defer dense financial provenance and configuration. Preserve unsaved Event Plan fields when navigating lifecycle actions. Test keyboard access, named controls, focus after actions and server error feedback. Decide information architecture with a read-only prototype before implementing the full redesign.
