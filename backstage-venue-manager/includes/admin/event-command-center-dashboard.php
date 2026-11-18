<?php
/** Show-day presentation only. All amounts and populations come from shared snapshots. */
if (!defined('ABSPATH')) {
    exit;
}

function bvmgr_event_command_center_operational_readiness(array $payload): array
{
    $items = array();
    $staff = (array) ($payload['staffing'] ?? array());
    $ticket = (array) ($payload['ticket'] ?? array());
    $weather = (array) ($payload['weather'] ?? array());
    $context = (array) ($payload['context'] ?? array());
    $edit = (string) ($payload['header']['edit_url'] ?? '');
    $staff_url = $edit !== '' ? $edit . '#vms-ep-staff-headcount-summary' : '';
    foreach ((array) ($payload['alerts'] ?? array()) as $alert) {
        if (!is_array($alert)) { continue; }
        // Optional promotional setup is not a show-day blocker. Pending review remains actionable.
        if (in_array($alert['code'] ?? '', array('promo', 'social', 'staffing_conflict', 'staffing_critical', 'staffing_open'), true)) { continue; }
        if (($alert['severity'] ?? '') === 'informational') { continue; }
        $items[] = $alert;
    }
    $add = static function ($severity, $title, $detail, $url, $label) use (&$items): void {
        $items[] = array('severity' => $severity, 'title' => $title, 'detail' => $detail, 'action_url' => $url, 'action_label' => $label);
    };
    if (!empty($staff['conflict_count'])) {
        $add('red', __('Staffing conflict detected', 'backstage-venue-manager'), __('Resolve the conflicts flagged by the shared staffing service.', 'backstage-venue-manager'), $staff_url, __('Review staffing', 'backstage-venue-manager'));
    }
    if (!empty($staff['critical_open_headcount'])) {
        $add('red', __('Critical staffing gap', 'backstage-venue-manager'), __('Required critical positions remain open.', 'backstage-venue-manager'), $staff_url, __('Review staffing', 'backstage-venue-manager'));
    } elseif (!empty($staff['open_headcount_total'])) {
        $add('yellow', __('Open staffing positions', 'backstage-venue-manager'), __('Required positions still need assignments.', 'backstage-venue-manager'), $staff_url, __('Review staffing', 'backstage-venue-manager'));
    }
    if (!empty($staff['overlap_warnings'])) {
        $add('yellow', __('Staffing overlap needs review', 'backstage-venue-manager'), __('The staffing service flags tentative overlaps with other assignments. Review these before confirming coverage.', 'backstage-venue-manager'), $staff_url, __('Review staffing', 'backstage-venue-manager'));
    }
    if (empty($staff['ok']) || empty($staff['headcount_needed_total']) || ($staff['headcount_context']['wired'] ?? null) === false) {
        $add('informational', __('Staffing scope needs review', 'backstage-venue-manager'), __('No verified staffing requirement is available. Confirm the staffing plan before treating this event as ready.', 'backstage-venue-manager'), $staff_url, __('Review staffing', 'backstage-venue-manager'));
    }
    if (!empty($staff['proposed_headcount'])) {
        /* translators: %d: number of proposed staff assignments. */
        $add('yellow', __('Tentative staffing', 'backstage-venue-manager'), sprintf(__('%d proposed assignments await confirmation.', 'backstage-venue-manager'), (int) $staff['proposed_headcount']), $staff_url, __('Review staffing', 'backstage-venue-manager'));
    }
    if (!empty($staff['legacy_status_unknown_headcount'])) {
        $add('informational', __('Staff responses unknown', 'backstage-venue-manager'), __('Legacy assignments have no verified lifecycle response.', 'backstage-venue-manager'), $staff_url, __('Review staffing', 'backstage-venue-manager'));
    }
    if (!in_array($ticket['ticket_state'] ?? '', array('CURRENT', 'VALID_ZERO'), true)) {
        $add('informational', __('Ticket data needs verification', 'backstage-venue-manager'), (string) ($ticket['status_label'] ?? __('Current sales are unavailable.', 'backstage-venue-manager')), '#vms-cc-audience', __('Review audience data', 'backstage-venue-manager'));
    }
    if (!empty($weather['concern'])) {
        $add('yellow', __('Weather concern', 'backstage-venue-manager'), (string) ($weather['summary'] ?? ''), (string) ($weather['url'] ?? ''), __('Review weather', 'backstage-venue-manager'));
    }
    if (!empty($weather['active']) && ($weather['state'] ?? 'unavailable') !== 'current') {
        $add('informational', __('Weather data incomplete', 'backstage-venue-manager'), (string) (($weather['freshness_label'] ?? '') ?: __('No current event forecast is available.', 'backstage-venue-manager')), (string) ($weather['url'] ?? ''), __('Review weather', 'backstage-venue-manager'));
    }
    $receipt = (array) ($payload['financial']['revenue']['tickets'] ?? array());
    if (!isset($receipt['amount_cents'])) {
        $add('informational', __('Transaction receipts unavailable', 'backstage-venue-manager'), __('Current ticket revenue cannot be verified. Forecast values remain separate.', 'backstage-venue-manager'), '#vms-cc-financial', __('Review financial basis', 'backstage-venue-manager'));
    }
    $comms = (array) ($context['communications'] ?? array());
    if (!empty($comms['pending']) || !empty($comms['failed']) || !empty($comms['review_required'])) {
        $add('yellow', __('Customer communications need attention', 'backstage-venue-manager'), (string) ($comms['summary'] ?? ''), (string) ($comms['url'] ?? ''), __('Review communications', 'backstage-venue-manager'));
    }
    if (!empty($payload['marketing']['promo_submission_pending'])) {
        $add('yellow', __('Promo submission awaiting review', 'backstage-venue-manager'), __('Review the submitted clip before publishing it.', 'backstage-venue-manager'), '#vms-cc-promo', __('Review promo controls', 'backstage-venue-manager'));
    }
    foreach ((array) ($context['documents']['issues'] ?? array()) as $issue) {
        if (is_array($issue)) { $items[] = array_merge(array('severity' => 'yellow'), $issue); }
    }
    $rank = array('red' => 3, 'yellow' => 2, 'informational' => 1);
    usort($items, static function ($a, $b) use ($rank) { return ($rank[$b['severity'] ?? ''] ?? 1) <=> ($rank[$a['severity'] ?? ''] ?? 1); });
    $highest = $items ? ($rank[$items[0]['severity'] ?? ''] ?? 1) : 0;
    $states = array(
        array('status' => 'ready', 'label' => __('Ready', 'backstage-venue-manager'), 'tone' => 'good'),
        array('status' => 'incomplete', 'label' => __('Incomplete data', 'backstage-venue-manager'), 'tone' => 'muted'),
        array('status' => 'attention', 'label' => __('Needs attention', 'backstage-venue-manager'), 'tone' => 'warning'),
        array('status' => 'blocked', 'label' => __('Blocked', 'backstage-venue-manager'), 'tone' => 'critical'),
    );
    return array_merge($states[$highest], array('items' => $items));
}

function bvmgr_event_command_center_dashboard_link(string $url, string $label, bool $primary = false): void
{
    if ($url === '') { return; }
    echo '<a class="button' . ($primary ? ' button-primary' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
}

function bvmgr_event_command_center_dashboard_section(string $id, string $title): void
{
    echo '<section class="vms-cc-panel" id="' . esc_attr($id) . '" aria-labelledby="' . esc_attr($id . '-title') . '"><div class="vms-cc-panel-heading"><h3 id="' . esc_attr($id . '-title') . '">' . esc_html($title) . '</h3></div>';
}

function bvmgr_event_command_center_render_dashboard(int $plan_id, array $payload): void
{
    $header = (array) ($payload['header'] ?? array());
    $ticket = (array) ($payload['ticket'] ?? array());
    $staff = (array) ($payload['staffing'] ?? array());
    $weather = (array) ($payload['weather'] ?? array());
    $financial = (array) ($payload['financial'] ?? array());
    $marketing = (array) ($payload['marketing'] ?? array());
    $lineup = (array) ($payload['lineup'] ?? array());
    $context = (array) ($payload['context'] ?? array());
    $readiness = bvmgr_event_command_center_operational_readiness($payload);
    $edit = (string) ($header['edit_url'] ?? '');
    $report = (string) ($context['event_day_url'] ?? '');
    $past = isset($header['days_until']) && $header['days_until'] < 0;
    $today = isset($header['days_until']) && $header['days_until'] === 0;
    $available = !empty($ticket['display_sales']);
    $unknown = __('Unavailable', 'backstage-venue-manager');
    $staff_known = !empty($staff['ok']);
    $financial_rows = !empty($financial['revenue']) && function_exists('bvmgr_financial_display_rows') ? bvmgr_financial_display_rows($financial) : array();
    $financial_available = false;
    foreach ($financial_rows as $row) {
        if (isset($row[1]['amount_cents'])) { $financial_available = true; break; }
    }
    ?>
    <div class="vms-event-command-center vms-cc-dashboard">
        <header class="vms-cc-command">
            <div class="vms-cc-command-main">
                <p class="vms-cc-eyebrow"><?php echo esc_html($past ? __('Event review / closeout', 'backstage-venue-manager') : ($today ? __('Show day', 'backstage-venue-manager') : __('Event operations', 'backstage-venue-manager'))); ?></p>
                <h2><?php echo esc_html((string) ($header['title'] ?? '')); ?></h2>
                <p class="vms-cc-command-meta"><?php echo esc_html(implode(' · ', array_filter(array($header['date_label'] ?? '', $header['time_label'] ?? '', $header['venue_label'] ?? '', $header['status_label'] ?? '')))); ?></p>
                <p class="vms-cc-command-meta"><?php echo esc_html((string) ($header['days_until_label'] ?? '')); ?></p>
                <div class="vms-cc-inline-actions">
                    <?php bvmgr_event_command_center_dashboard_link($report, __('Event-Day Guest List', 'backstage-venue-manager'), true); ?>
                    <?php bvmgr_event_command_center_dashboard_link($edit, __('Open Event Plan', 'backstage-venue-manager')); ?>
                    <?php bvmgr_event_command_center_dashboard_link('#vms-cc-tools', __('Show-day tools & reports', 'backstage-venue-manager')); ?>
                </div>
            </div>
            <div class="vms-cc-command-status">
                <span class="vms-cc-eyebrow"><?php echo esc_html__('Operational readiness', 'backstage-venue-manager'); ?></span>
                <a class="vms-cc-readiness is-<?php echo esc_attr($readiness['tone']); ?>" href="#vms-cc-attention"><?php echo esc_html($readiness['label']); ?></a>
                <span><?php echo esc_html($available ? sprintf(/* translators: %d: paid ticket count. */ __('%d paid tickets', 'backstage-venue-manager'), (int) ($ticket['sold'] ?? 0)) : __('Paid tickets unavailable', 'backstage-venue-manager')); ?></span>
                <span><?php echo esc_html((string) ($ticket['status_label'] ?? $unknown)); ?></span>
                <small><?php echo esc_html((string) ($ticket['stats_age_label'] ?? '')); ?></small>
            </div>
        </header>
        <nav class="vms-cc-jump" aria-label="<?php echo esc_attr__('Event sections', 'backstage-venue-manager'); ?>">
            <a href="#vms-cc-attention"><?php echo esc_html__('Attention', 'backstage-venue-manager'); ?></a>
            <a href="#vms-cc-audience"><?php echo esc_html__('Audience', 'backstage-venue-manager'); ?></a>
            <a href="#vms-cc-staffing"><?php echo esc_html__('Staffing', 'backstage-venue-manager'); ?></a>
            <a href="#vms-cc-financial"><?php echo esc_html__('Financial', 'backstage-venue-manager'); ?></a>
            <a href="#vms-cc-people"><?php echo esc_html__('People & documents', 'backstage-venue-manager'); ?></a>
            <a href="#vms-cc-communications"><?php echo esc_html__('Communications', 'backstage-venue-manager'); ?></a>
        </nav>
        <?php bvmgr_event_command_center_dashboard_section('vms-cc-attention', __('Attention & readiness', 'backstage-venue-manager')); ?>
            <p class="vms-cc-note"><?php echo esc_html__('Readiness reflects the checks below. It is not a safety clearance or final accounting sign-off.', 'backstage-venue-manager'); ?></p>
            <?php if (!$readiness['items']) : ?>
                <p class="vms-cc-calm"><?php echo esc_html__('No active issues under the current checks. Review the schedule and optional workspaces as needed.', 'backstage-venue-manager'); ?></p>
            <?php else : ?>
                <ul class="vms-cc-alert-list">
                <?php foreach ($readiness['items'] as $item) : ?>
                    <li class="vms-cc-alert is-<?php echo esc_attr((string) ($item['severity'] ?? 'informational')); ?>">
                        <div class="vms-cc-alert__body">
                            <span class="vms-cc-alert-kind"><?php echo esc_html(($item['severity'] ?? '') === 'red' ? __('Blocker', 'backstage-venue-manager') : (($item['severity'] ?? '') === 'yellow' ? __('Action needed', 'backstage-venue-manager') : __('Data gap', 'backstage-venue-manager'))); ?></span>
                            <strong><?php echo esc_html((string) ($item['title'] ?? '')); ?></strong>
                            <p><?php echo esc_html((string) ($item['detail'] ?? '')); ?></p>
                        </div>
                        <?php bvmgr_event_command_center_dashboard_link((string) ($item['action_url'] ?? ''), (string) ($item['action_label'] ?? __('Review', 'backstage-venue-manager'))); ?>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <div class="vms-cc-operations-grid">
            <?php bvmgr_event_command_center_dashboard_section('vms-cc-audience', __('Audience & admissions', 'backstage-venue-manager')); ?>
                <p class="vms-cc-note"><strong><?php echo esc_html((string) ($ticket['status_label'] ?? $unknown)); ?></strong></p>
                <div class="vms-cc-metrics">
                <?php
                bvmgr_event_command_center_render_metric(__('Paid tickets', 'backstage-venue-manager'), $available ? (string) ($ticket['sold'] ?? 0) : $unknown);
                $comp_basis = (string) ($ticket['comp_count_basis'] ?? 'none');
                $comp_labels = array('transaction_free' => __('Transaction records', 'backstage-venue-manager'), 'actual_override' => __('Operator reported', 'backstage-venue-manager'), 'forecast' => __('Forecast only', 'backstage-venue-manager'));
                bvmgr_event_command_center_render_metric(__('Comp / free tickets', 'backstage-venue-manager'), isset($comp_labels[$comp_basis]) ? (string) ($ticket['comp_count'] ?? 0) : $unknown, $comp_labels[$comp_basis] ?? __('No verified comp count', 'backstage-venue-manager'));
                if (isset($ticket['remaining'])) { bvmgr_event_command_center_render_metric(__('Paid inventory remaining', 'backstage-venue-manager'), (string) $ticket['remaining']); }
                ?>
                </div>
                <p class="vms-cc-note"><?php echo esc_html__('Ticket counts are not check-ins or a deduplicated attendance total. Open the Event-Day report for admissions, guests and reservations.', 'backstage-venue-manager'); ?></p>
                <p class="vms-cc-note"><?php echo esc_html(implode(' · ', array_filter(array($ticket['ticket_source_label'] ?? '', $ticket['stats_age_label'] ?? '')))); ?></p>
                <?php foreach ((array) ($ticket['ticket_source_warnings'] ?? array()) as $warning) : ?><p class="vms-cc-note"><?php echo esc_html((string) $warning); ?></p><?php endforeach; ?>
                <div class="vms-cc-inline-actions"><?php bvmgr_event_command_center_dashboard_link($report, __('Open guest list & admissions report', 'backstage-venue-manager')); ?></div>
            </section>
            <?php bvmgr_event_command_center_dashboard_section('vms-cc-staffing', __('Staffing', 'backstage-venue-manager')); ?>
                <?php
                $staff_label = !$staff_known || empty($staff['headcount_needed_total']) || ($staff['headcount_context']['wired'] ?? null) === false ? __('Setup / incomplete data', 'backstage-venue-manager') : (!empty($staff['conflict_count']) ? __('Conflict', 'backstage-venue-manager') : (!empty($staff['open_headcount_total']) ? __('Open positions', 'backstage-venue-manager') : (!empty($staff['overlap_warnings']) ? __('Overlap needs review', 'backstage-venue-manager') : (!empty($staff['open_positions']) ? __('Planned positions open', 'backstage-venue-manager') : (!empty($staff['proposed_headcount']) ? __('Tentative', 'backstage-venue-manager') : (!empty($staff['legacy_status_unknown_headcount']) ? __('Responses unknown', 'backstage-venue-manager') : __('Ready', 'backstage-venue-manager')))))));
                ?>
                <p class="vms-cc-note"><strong><?php echo esc_html($staff_label); ?></strong></p>
                <div class="vms-cc-metrics vms-cc-metrics--staff">
                <?php
                foreach (array(
                    'headcount_needed_total' => __('Planned positions', 'backstage-venue-manager'),
                    'assigned_headcount' => __('Assigned total', 'backstage-venue-manager'),
                    'required_now_headcount_total' => __('Required now', 'backstage-venue-manager'),
                    'proposed_headcount' => __('Proposed / tentative', 'backstage-venue-manager'),
                    'confirmed_headcount' => __('Confirmed', 'backstage-venue-manager'),
                    'open_positions' => __('Open planned positions', 'backstage-venue-manager'),
                    'open_headcount_total' => __('Open required now', 'backstage-venue-manager'),
                    'conflict_count' => __('Conflicts', 'backstage-venue-manager'),
                ) as $key => $label) { bvmgr_event_command_center_render_metric($label, $staff_known && isset($staff[$key]) ? (string) $staff[$key] : $unknown); }
                ?>
                </div>
                <p class="vms-cc-note"><?php echo esc_html__('Proposed and confirmed assignments cover different commitment states. Declined and canceled assignments do not cover positions.', 'backstage-venue-manager'); ?></p>
                <div class="vms-cc-inline-actions"><?php bvmgr_event_command_center_dashboard_link($edit !== '' ? $edit . '#vms-ep-staff-headcount-summary' : '', __('Manage staffing in Event Plan', 'backstage-venue-manager')); ?></div>
            </section>
        </div>
        <div class="vms-cc-operations-grid vms-cc-operations-grid--schedule">
            <?php bvmgr_event_command_center_dashboard_section('vms-cc-schedule', __('Show schedule', 'backstage-venue-manager')); ?>
                <?php if (!empty($payload['timeline'])) : ?>
                <ol class="vms-cc-timeline">
                    <?php foreach ($payload['timeline'] as $row) : ?>
                    <li class="vms-cc-timeline__row"><span class="vms-cc-timeline__time"><?php echo esc_html((string) ($row['time'] ?? '')); ?></span><div><strong><?php echo esc_html((string) ($row['label'] ?? '')); ?></strong><p class="vms-cc-note"><?php echo esc_html((string) ($row['detail'] ?? '')); ?></p></div></li>
                    <?php endforeach; ?>
                </ol>
                <?php else : ?><p class="vms-cc-note"><?php echo esc_html__('No schedule anchors are available. Set show times in Event Plan.', 'backstage-venue-manager'); ?></p><?php endif; ?>
            </section>
            <?php bvmgr_event_command_center_dashboard_section('vms-cc-weather', __('Weather & event risk', 'backstage-venue-manager')); ?>
                <p><strong><?php echo esc_html((string) ($weather['label'] ?? $unknown)); ?></strong></p>
                <p><?php echo esc_html((string) ($weather['summary'] ?? '')); ?></p>
                <p class="vms-cc-note"><?php echo esc_html((string) ($weather['window_label'] ?? '')); ?></p>
                <p class="vms-cc-note"><?php echo esc_html((string) ($weather['freshness_label'] ?? '')); ?></p>
                <?php if (!empty($weather['active'])) : ?><div class="vms-cc-inline-actions"><?php bvmgr_event_command_center_dashboard_link((string) ($weather['url'] ?? ''), __('Open weather details', 'backstage-venue-manager')); ?></div><?php endif; ?>
            </section>
        </div>
        <?php bvmgr_event_command_center_dashboard_section('vms-cc-financial', __('Financial snapshot', 'backstage-venue-manager')); ?>
            <p class="vms-cc-note"><?php echo esc_html__('Transaction receipts, reported entries and estimates keep their own basis. Planned and committed labor overlap; do not add them.', 'backstage-venue-manager'); ?></p>
            <?php if ($financial_available) : ?>
                <div class="vms-cc-financial-groups">
                <?php foreach (array(
                    array(__('Current / transactional', 'backstage-venue-manager'), array(0, 3)),
                    array(__('Forecast / planned', 'backstage-venue-manager'), array(4, 5, 6, 7)),
                    array(__('Reported / manual', 'backstage-venue-manager'), array(1, 2)),
                ) as $group) : ?>
                    <div class="vms-cc-financial-group"><h4><?php echo esc_html($group[0]); ?></h4><dl>
                    <?php foreach ($group[1] as $index) : $row = $financial_rows[$index]; ?>
                        <dt><?php echo esc_html($row[0]); ?></dt><dd><strong><?php echo esc_html(bvmgr_financial_money($row[1]['amount_cents'] ?? null)); ?></strong><span><?php echo esc_html($row[2]); ?></span></dd>
                    <?php endforeach; ?>
                    </dl></div>
                <?php endforeach; ?>
                </div>
                <details class="vms-cc-details"><summary><?php echo esc_html__('Source, freshness & accounting limits', 'backstage-venue-manager'); ?></summary>
                    <?php bvmgr_financial_render_summary($financial); ?>
                </details>
            <?php else : ?><p><?php echo esc_html__('Financial authority is unavailable. No actual or forecast totals can be verified.', 'backstage-venue-manager'); ?></p><?php endif; ?>
        </section>
        <div class="vms-cc-operations-grid">
            <?php bvmgr_event_command_center_dashboard_section('vms-cc-people', __('Talent, vendors & documents', 'backstage-venue-manager')); ?>
                <ul class="vms-cc-people-list">
                <?php
                $people = array_merge(!empty($lineup['primary']) ? array($lineup['primary']) : array(), (array) ($lineup['supporting'] ?? array()), (array) ($lineup['secondary'] ?? array()));
                foreach ($people as $person) {
                    echo '<li><strong>' . esc_html((string) ($person['display_name'] ?? $person['vendor_title'] ?? '')) . '</strong><span>' . esc_html((string) ($person['role_label'] ?? $person['role'] ?? __('Assigned in Event Plan', 'backstage-venue-manager'))) . '</span></li>';
                }
                ?>
                </ul>
                <?php if (!$people) : ?><p class="vms-cc-note"><?php echo esc_html__('No talent or vendor assignments are available. Review the Event Plan roster.', 'backstage-venue-manager'); ?></p><?php endif; ?>
                <p class="vms-cc-note"><?php echo esc_html((string) ($context['documents']['summary'] ?? __('Agreement and document readiness has not been verified here. Review the owning workflow.', 'backstage-venue-manager'))); ?></p>
                <div class="vms-cc-inline-actions"><?php bvmgr_event_command_center_dashboard_link($edit, __('Roster & compensation details', 'backstage-venue-manager')); ?>
                <?php bvmgr_event_command_center_dashboard_link((string) ($context['documents']['url'] ?? ''), __('Agreements & documents', 'backstage-venue-manager')); ?>
                <?php foreach ((array) ($context['documents']['links'] ?? array()) as $link) { bvmgr_event_command_center_dashboard_link((string) ($link['url'] ?? ''), (string) ($link['label'] ?? '')); } ?></div>
            </section>
            <?php bvmgr_event_command_center_dashboard_section('vms-cc-communications', __('Communications & marketing', 'backstage-venue-manager')); ?>
                <h4><?php echo esc_html__('Customer communications', 'backstage-venue-manager'); ?></h4>
                <p><?php echo esc_html((string) ($context['communications']['summary'] ?? __('No communication status is available.', 'backstage-venue-manager'))); ?></p>
                <div class="vms-cc-inline-actions"><?php bvmgr_event_command_center_dashboard_link((string) ($context['communications']['url'] ?? ''), __('Review customer communications', 'backstage-venue-manager')); ?></div>
                <details class="vms-cc-details"><summary><?php echo esc_html__('Marketing & public listing', 'backstage-venue-manager'); ?></summary>
                    <p><?php echo esc_html((string) ($marketing['event_page_label'] ?? '')); ?></p>
                    <p><?php echo esc_html(empty($marketing['social_ready']) ? __('Social posting suppressed in Event Plan.', 'backstage-venue-manager') : __('Social posting is allowed; delivery and campaign results are managed in their own workspaces.', 'backstage-venue-manager')); ?></p>
                    <p><?php echo esc_html((string) ($marketing['promo_video_label'] ?? '')); ?></p>
                    <div class="vms-cc-inline-actions"><?php foreach ((array) ($context['marketing_tools'] ?? array()) as $tool) { bvmgr_event_command_center_dashboard_link((string) $tool['url'], (string) $tool['label']); } ?></div>
                </details>
            </section>
        </div>
        <?php bvmgr_event_command_center_dashboard_section('vms-cc-tools', __('Show-day tools & reports', 'backstage-venue-manager')); ?>
            <div class="vms-cc-tool-list">
            <?php foreach ((array) ($context['tools'] ?? array()) as $tool) { bvmgr_event_command_center_dashboard_link((string) ($tool['url'] ?? ''), (string) ($tool['label'] ?? '')); } ?>
            <?php bvmgr_event_command_center_dashboard_link((string) ($header['public_event_url'] ?? ''), __('Public event page', 'backstage-venue-manager')); ?>
            <?php bvmgr_event_command_center_dashboard_link((string) ($header['ticket_url'] ?? ''), __('Public ticket page', 'backstage-venue-manager')); ?>
            </div>
            <p class="vms-cc-note"><?php echo esc_html__('Reports retain their existing permissions and event scope. Event Plan owns configuration; portals own individual staff and vendor interactions.', 'backstage-venue-manager'); ?></p>
        </section>
        <?php if (!empty($payload['notes']['has_notes'])) : ?>
            <?php bvmgr_event_command_center_dashboard_section('vms-cc-notes', __('Internal notes', 'backstage-venue-manager')); ?>
                <div class="vms-cc-notes"><?php echo nl2br(esc_html((string) ($payload['notes']['notes'] ?? ''))); ?></div>
            </section>
        <?php endif; ?>
        <?php if (!empty($payload['activity'])) : ?>
            <details class="vms-cc-panel vms-cc-details"><summary><?php echo esc_html__('Recent activity', 'backstage-venue-manager'); ?></summary>
                <ul class="vms-cc-activity-list"><?php foreach ($payload['activity'] as $item) : ?><li><strong><?php echo esc_html((string) ($item['title'] ?? '')); ?></strong><p><?php echo esc_html((string) ($item['detail'] ?? '')); ?></p><span><?php echo esc_html((string) ($item['when'] ?? '')); ?></span></li><?php endforeach; ?></ul>
            </details>
        <?php endif; ?>
        <?php if (bvmgr_event_command_center_can_manage_promo_video($plan_id)) : ?>
            <details class="vms-cc-panel vms-cc-details" id="vms-cc-promo"><summary><?php echo esc_html__('Promo video controls', 'backstage-venue-manager'); ?></summary>
                <?php bvmgr_event_command_center_render_promo_video_manager($plan_id, $marketing); ?>
            </details>
        <?php endif; ?>
        <?php if (function_exists('bvmgr_ecc_render_registered_extensions')) : ?>
            <?php bvmgr_ecc_render_registered_extensions($plan_id, $payload); ?>
        <?php endif; ?>
    </div>
    <?php
}
