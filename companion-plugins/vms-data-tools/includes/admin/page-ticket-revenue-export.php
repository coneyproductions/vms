<?php
if (!defined('ABSPATH')) {
    exit;
}

function vms_dt_register_ticket_revenue_export_page(): void
{
    $parent_slug = 'vms-data-tools';
    $cap = function_exists('vms_dt_manage_capability') ? vms_dt_manage_capability() : 'manage_options';

    add_submenu_page(
        $parent_slug,
        __('Ticket Revenue Export', 'vms-data-tools'),
        __('Ticket Revenue Export', 'vms-data-tools'),
        $cap,
        vms_dt_get_menu_slug_ticket_revenue_export(),
        'vms_dt_render_ticket_revenue_export_page'
    );
}

function vms_dt_ticket_revenue_money(int $cents): string
{
    return '$' . number_format($cents / 100, 2, '.', ',');
}

function vms_dt_ticket_revenue_preset_options(): array
{
    return array(
        'custom' => __('Custom', 'vms-data-tools'),
        'this_month' => __('This Month', 'vms-data-tools'),
        'last_month' => __('Last Month', 'vms-data-tools'),
        'this_quarter' => __('This Quarter', 'vms-data-tools'),
        'last_quarter' => __('Last Quarter', 'vms-data-tools'),
        'this_year' => __('This Year', 'vms-data-tools'),
        'last_year' => __('Last Year', 'vms-data-tools'),
        'all_time' => __('All Time', 'vms-data-tools'),
    );
}

function vms_dt_ticket_revenue_today_ymd(): string
{
    return vms_dt_has_core_function('vms_ticket_revenue_wp_now_ymd')
        ? vms_dt_call_core_function('vms_ticket_revenue_wp_now_ymd')
        : wp_date('Y-m-d', time(), wp_timezone());
}

function vms_dt_ticket_revenue_preset_range(string $preset): array
{
    $preset = sanitize_key($preset);
    $tz = wp_timezone();
    $today = new DateTimeImmutable(vms_dt_ticket_revenue_today_ymd(), $tz);
    $start = '';
    $end = '';
    $as_of = vms_dt_ticket_revenue_today_ymd();

    switch ($preset) {
        case 'this_month':
            $start_dt = $today->modify('first day of this month');
            $end_dt = $today;
            break;
        case 'last_month':
            $anchor = $today->modify('first day of this month')->modify('-1 day');
            $start_dt = $anchor->modify('first day of this month');
            $end_dt = $anchor->modify('last day of this month');
            break;
        case 'this_quarter':
            $month = (int) $today->format('n');
            $quarter_start_month = (int) (floor(($month - 1) / 3) * 3) + 1;
            $start_dt = new DateTimeImmutable($today->format('Y') . '-' . str_pad((string) $quarter_start_month, 2, '0', STR_PAD_LEFT) . '-01', $tz);
            $end_dt = $today;
            break;
        case 'last_quarter':
            $month = (int) $today->format('n');
            $quarter_start_month = (int) (floor(($month - 1) / 3) * 3) + 1;
            $this_quarter_start = new DateTimeImmutable($today->format('Y') . '-' . str_pad((string) $quarter_start_month, 2, '0', STR_PAD_LEFT) . '-01', $tz);
            $end_dt = $this_quarter_start->modify('-1 day');
            $last_q_month = (int) $end_dt->format('n');
            $last_q_start_month = (int) (floor(($last_q_month - 1) / 3) * 3) + 1;
            $start_dt = new DateTimeImmutable($end_dt->format('Y') . '-' . str_pad((string) $last_q_start_month, 2, '0', STR_PAD_LEFT) . '-01', $tz);
            break;
        case 'this_year':
            $start_dt = new DateTimeImmutable($today->format('Y') . '-01-01', $tz);
            $end_dt = $today;
            break;
        case 'last_year':
            $year = (int) $today->format('Y') - 1;
            $start_dt = new DateTimeImmutable($year . '-01-01', $tz);
            $end_dt = new DateTimeImmutable($year . '-12-31', $tz);
            break;
        case 'all_time':
            return array(
                'sold_from' => '',
                'sold_to' => '',
                'as_of_date' => $as_of,
            );
        case 'custom':
        default:
            return array();
    }

    $start = $start_dt->format('Y-m-d');
    $end = $end_dt->format('Y-m-d');
    $as_of = $end;
    if ($as_of > vms_dt_ticket_revenue_today_ymd()) {
        $as_of = vms_dt_ticket_revenue_today_ymd();
    }

    return array(
        'sold_from' => $start,
        'sold_to' => $end,
        'as_of_date' => $as_of,
    );
}

function vms_dt_ticket_revenue_request_args(string $method = 'get'): array
{
    $source = strtolower($method) === 'post' ? $_POST : $_GET;
    $statuses = isset($source['order_statuses']) ? (array) wp_unslash($source['order_statuses']) : array();
    $date_preset = isset($source['date_preset']) ? sanitize_key((string) wp_unslash($source['date_preset'])) : 'custom';
    if (!isset(vms_dt_ticket_revenue_preset_options()[$date_preset])) {
        $date_preset = 'custom';
    }

    $args = array(
        'date_preset' => $date_preset,
        'sold_from' => isset($source['sold_from']) ? sanitize_text_field((string) wp_unslash($source['sold_from'])) : '',
        'sold_to' => isset($source['sold_to']) ? sanitize_text_field((string) wp_unslash($source['sold_to'])) : '',
        'event_from' => isset($source['event_from']) ? sanitize_text_field((string) wp_unslash($source['event_from'])) : '',
        'event_to' => isset($source['event_to']) ? sanitize_text_field((string) wp_unslash($source['event_to'])) : '',
        'as_of_date' => isset($source['as_of_date']) ? sanitize_text_field((string) wp_unslash($source['as_of_date'])) : '',
        'recognition_status' => isset($source['recognition_status']) ? sanitize_key((string) wp_unslash($source['recognition_status'])) : 'all',
        'order_statuses' => array_map('sanitize_key', $statuses),
        'preview_limit' => 200,
        'unresolved_limit' => 150,
    );

    if ($date_preset !== 'custom') {
        $args = array_merge($args, vms_dt_ticket_revenue_preset_range($date_preset));
    }

    return $args;
}

function vms_dt_ticket_revenue_download_hidden_fields(array $args): void
{
    foreach (array('date_preset', 'sold_from', 'sold_to', 'event_from', 'event_to', 'as_of_date', 'recognition_status') as $key) {
        echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) ($args[$key] ?? '')) . '" />';
    }

    foreach ((array) ($args['order_statuses'] ?? array()) as $status) {
        echo '<input type="hidden" name="order_statuses[]" value="' . esc_attr((string) $status) . '" />';
    }
}

function vms_dt_ticket_revenue_render_event_summary_table(array $event_summary): void
{
    echo '<div class="vms-dt-table-wrap"><table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Event', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Event date', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Recognition', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Orders', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Lines', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Net sales', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Tax', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Cash total', 'vms-data-tools') . '</th>';
    echo '</tr></thead><tbody>';

    if (empty($event_summary)) {
        echo '<tr><td colspan="8">' . esc_html__('No event-linked lines matched the current filters.', 'vms-data-tools') . '</td></tr>';
    } else {
        foreach ($event_summary as $row) {
            $event_name = trim((string) ($row['event_plan_title'] ?: $row['event_title'] ?: __('Unknown event', 'vms-data-tools')));
            echo '<tr>';
            echo '<td><strong>' . esc_html($event_name) . '</strong>';
            if (!empty($row['tec_event_id'])) {
                echo '<div class="vms-dt-subtle">TEC #' . esc_html((string) $row['tec_event_id']) . '</div>';
            }
            echo '</td>';
            echo '<td>' . esc_html((string) ($row['event_date'] ?: '—')) . '</td>';
            echo '<td>' . esc_html(ucfirst((string) ($row['recognition_status'] ?? 'unknown'))) . '</td>';
            echo '<td>' . esc_html((string) ((int) ($row['order_count'] ?? 0))) . '</td>';
            echo '<td>' . esc_html((string) ((int) ($row['line_count'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['net_subtotal_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['tax_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['cash_total_cents'] ?? 0))) . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table></div>';
}

function vms_dt_ticket_revenue_render_line_preview(array $rows, int $preview_limit): void
{
    echo '<div class="vms-dt-table-wrap"><table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Sold', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Order', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Customer', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Event', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Event date', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Item', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Qty', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Net sales', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Tax', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Cash total', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Recognition', 'vms-data-tools') . '</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="11">' . esc_html__('No lines matched the current filters.', 'vms-data-tools') . '</td></tr>';
    } else {
        $slice = array_slice($rows, 0, $preview_limit);
        foreach ($slice as $row) {
            $event_name = trim((string) ($row['event_plan_title'] ?: $row['event_title'] ?: __('Unknown event', 'vms-data-tools')));
            $customer = trim((string) ($row['customer_name'] ?? ''));
            if ($customer === '') {
                $customer = (string) ($row['customer_email'] ?? '');
            }
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['sold_datetime'] ?: $row['sold_date'])) . '</td>';
            echo '<td>#' . esc_html((string) ($row['order_number'] ?? $row['order_id'])) . '<div class="vms-dt-subtle">' . esc_html((string) ($row['order_status'] ?? '')) . '</div></td>';
            echo '<td>' . esc_html($customer !== '' ? $customer : '—') . '</td>';
            echo '<td><strong>' . esc_html($event_name) . '</strong><div class="vms-dt-subtle">' . esc_html((string) ($row['resolution_source'] ?? '')) . '</div></td>';
            echo '<td>' . esc_html((string) ($row['event_date'] ?: '—')) . '</td>';
            echo '<td>' . esc_html((string) ($row['item_name'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ((int) ($row['quantity'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['net_subtotal_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['tax_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['cash_total_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(ucfirst((string) ($row['recognition_status'] ?? 'unknown'))) . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table></div>';
}

function vms_dt_ticket_revenue_render_unresolved_table(array $rows, int $captured, int $total): void
{
    if (empty($rows)) {
        return;
    }

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<h2>' . esc_html__('Unresolved lines', 'vms-data-tools') . '</h2>';
    echo '<p class="vms-dt-section-desc">' . esc_html__('These Woo order lines were scanned but could not be tied to an event from product meta or order-item snapshots. Fix these before relying on the totals.', 'vms-data-tools') . '</p>';
    if ($captured < $total) {
        echo '<p class="description">' . sprintf(esc_html__('Showing the first %1$d unresolved lines out of %2$d total.', 'vms-data-tools'), (int) $captured, (int) $total) . '</p>';
    }

    echo '<div class="vms-dt-table-wrap"><table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Sold', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Order', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Item', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Candidate product IDs', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Snapshots', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Reason', 'vms-data-tools') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $snapshot_bits = array();
        if (!empty($row['item_tec_event_id'])) {
            $snapshot_bits[] = 'TEC #' . (int) $row['item_tec_event_id'];
        }
        if (!empty($row['item_event_plan_id'])) {
            $snapshot_bits[] = 'Plan #' . (int) $row['item_event_plan_id'];
        }
        if (!empty($row['event_title_snapshot'])) {
            $snapshot_bits[] = (string) $row['event_title_snapshot'];
        }
        if (!empty($row['event_date_snapshot'])) {
            $snapshot_bits[] = (string) $row['event_date_snapshot'];
        }
        if (!empty($row['resolution_source'])) {
            $snapshot_bits[] = 'Source: ' . (string) $row['resolution_source'];
        }
        echo '<tr>';
        echo '<td>' . esc_html((string) ($row['sold_datetime'] ?: $row['sold_date'])) . '</td>';
        echo '<td>#' . esc_html((string) ($row['order_number'] ?? $row['order_id'])) . '<div class="vms-dt-subtle">' . esc_html((string) ($row['order_status'] ?? '')) . '</div></td>';
        echo '<td><strong>' . esc_html((string) ($row['item_name'] ?? '')) . '</strong><div class="vms-dt-subtle">Qty ' . esc_html((string) ((int) ($row['quantity'] ?? 0))) . '</div></td>';
        echo '<td>' . esc_html((string) ($row['candidate_product_ids'] ?: '—')) . '</td>';
        echo '<td>' . esc_html(!empty($snapshot_bits) ? implode(' | ', $snapshot_bits) : '—') . '</td>';
        echo '<td>' . esc_html((string) ($row['reason'] ?? '')) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
    echo '</div>';
}

function vms_dt_render_ticket_revenue_export_page(): void
{
    if (!function_exists('vms_dt_current_user_can_manage_tools') || !vms_dt_current_user_can_manage_tools()) {
        return;
    }

    $page_slug = vms_dt_get_menu_slug_ticket_revenue_export();
    $raw_args = vms_dt_ticket_revenue_request_args('get');
    $args = vms_dt_has_core_function('vms_ticket_revenue_normalize_args')
        ? vms_dt_call_core_function('vms_ticket_revenue_normalize_args', $raw_args)
        : $raw_args;
    $args['date_preset'] = (string) ($raw_args['date_preset'] ?? 'custom');

    $available_statuses = vms_dt_has_core_function('vms_ticket_revenue_available_statuses')
        ? vms_dt_call_core_function('vms_ticket_revenue_available_statuses')
        : array();

    $do_preview = isset($_GET['vms_dt_preview']) && (string) $_GET['vms_dt_preview'] === '1';

    echo '<div class="wrap vms-dt-wrap">';
    echo '<h1>' . esc_html__('Ticket Revenue Export', 'vms-data-tools') . '</h1>';

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<p>' . esc_html__('Use Woo + TEC as the source of truth for online ticketing revenue. This export resolves each event-linked Woo order line to an event, then classifies it as Deferred or Earned as of the date you choose.', 'vms-data-tools') . '</p>';
    echo '<div class="vms-dt-callout">';
    echo '<strong>' . esc_html__('Recognition rule', 'vms-data-tools') . '</strong>';
    echo '<p>' . esc_html__('Conservative default: if the event date is today or in the future, the line stays Deferred. It flips to Earned the day after the event date unless you export with a later “as of” date.', 'vms-data-tools') . '</p>';
    echo '</div>';
    echo '</div>';

    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '" />';

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<h2>' . esc_html__('Filters', 'vms-data-tools') . '</h2>';
    echo '<div class="vms-dt-form-grid">';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Sold-date preset', 'vms-data-tools') . '</span>';
    echo '<select name="date_preset">';
    foreach (vms_dt_ticket_revenue_preset_options() as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected((string) ($args['date_preset'] ?? 'custom'), $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label>';

    echo '<label class="vms-dt-field"><span>' . esc_html__('Sold from', 'vms-data-tools') . '</span><input type="date" name="sold_from" value="' . esc_attr((string) ($args['sold_from'] ?? '')) . '" /></label>';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Sold to', 'vms-data-tools') . '</span><input type="date" name="sold_to" value="' . esc_attr((string) ($args['sold_to'] ?? '')) . '" /></label>';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Event from', 'vms-data-tools') . '</span><input type="date" name="event_from" value="' . esc_attr((string) ($args['event_from'] ?? '')) . '" /></label>';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Event to', 'vms-data-tools') . '</span><input type="date" name="event_to" value="' . esc_attr((string) ($args['event_to'] ?? '')) . '" /></label>';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Recognition as of', 'vms-data-tools') . '</span><input type="date" name="as_of_date" value="' . esc_attr((string) ($args['as_of_date'] ?? '')) . '" /></label>';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Recognition status', 'vms-data-tools') . '</span>';
    echo '<select name="recognition_status">';
    foreach (array(
        'all' => __('All lines', 'vms-data-tools'),
        'deferred' => __('Deferred only', 'vms-data-tools'),
        'earned' => __('Earned only', 'vms-data-tools'),
        'unknown' => __('Unknown event date only', 'vms-data-tools'),
    ) as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected((string) ($args['recognition_status'] ?? 'all'), $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label>';
    echo '</div>';

    echo '<div class="vms-dt-field-group">';
    echo '<div class="vms-dt-field-group__label">' . esc_html__('Order statuses to include', 'vms-data-tools') . '</div>';
    echo '<div class="vms-dt-check-grid">';
    foreach ($available_statuses as $status => $label) {
        $checked = in_array((string) $status, (array) ($args['order_statuses'] ?? array()), true);
        echo '<label class="vms-dt-check"><input type="checkbox" name="order_statuses[]" value="' . esc_attr((string) $status) . '" ' . checked($checked, true, false) . ' /> <span>' . esc_html((string) $label) . '</span></label>';
    }
    echo '</div>';
    echo '</div>';

    echo '<div class="vms-dt-actions">';
    echo '<button class="button button-primary" type="submit" name="vms_dt_preview" value="1">' . esc_html__('Preview report', 'vms-data-tools') . '</button>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . $page_slug)) . '">' . esc_html__('Reset filters', 'vms-data-tools') . '</a>';
    echo '</div>';
    echo '<p class="description">' . esc_html__('Preset periods populate the sold-date range and a safe default “Recognition as of” date. Switch back to Custom any time and hand-edit the dates.', 'vms-data-tools') . '</p>';
    echo '</div>';
    echo '</form>';

    if (!$do_preview) {
        echo '</div>';
        return;
    }

    if (!vms_dt_has_core_function('vms_ticket_revenue_build_report')) {
        echo '<div class="notice notice-error"><p>' . esc_html__('VMS core ticket revenue service is missing. Please update/activate the VMS core plugin.', 'vms-data-tools') . '</p></div>';
        echo '</div>';
        return;
    }

    $report = vms_dt_call_core_function('vms_ticket_revenue_build_report', $args);
    $summary = (array) ($report['summary'] ?? array());
    $rows = (array) ($report['rows'] ?? array());
    $event_summary = (array) ($report['event_summary'] ?? array());
    $counts = (array) ($report['counts'] ?? array());
    $unresolved_rows = (array) ($report['unresolved_rows'] ?? array());
    $preview_limit = max(1, (int) ($args['preview_limit'] ?? 200));

    if (!empty($report['warnings'])) {
        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Notes', 'vms-data-tools') . '</strong></p><ul class="vms-dt-list">';
        foreach ((array) $report['warnings'] as $warning) {
            echo '<li>' . esc_html((string) $warning) . '</li>';
        }
        echo '</ul></div>';
    }

    echo '<div class="vms-dt-grid vms-dt-kpi-grid">';
    foreach (array(
        array('label' => __('Orders', 'vms-data-tools'), 'value' => (string) ((int) ($summary['order_count'] ?? 0))),
        array('label' => __('Event lines', 'vms-data-tools'), 'value' => (string) ((int) ($summary['line_count'] ?? 0))),
        array('label' => __('Cash total', 'vms-data-tools'), 'value' => vms_dt_ticket_revenue_money((int) ($summary['cash_total_cents'] ?? 0))),
        array('label' => __('Deferred', 'vms-data-tools'), 'value' => vms_dt_ticket_revenue_money((int) ($summary['deferred_cents'] ?? 0))),
        array('label' => __('Earned', 'vms-data-tools'), 'value' => vms_dt_ticket_revenue_money((int) ($summary['earned_cents'] ?? 0))),
        array('label' => __('Tax', 'vms-data-tools'), 'value' => vms_dt_ticket_revenue_money((int) ($summary['tax_cents'] ?? 0))),
    ) as $card) {
        echo '<div class="vms-dt-card vms-dt-kpi">';
        echo '<div class="vms-dt-kpi__label">' . esc_html((string) $card['label']) . '</div>';
        echo '<div class="vms-dt-kpi__value">' . esc_html((string) $card['value']) . '</div>';
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<h2>' . esc_html__('Download', 'vms-data-tools') . '</h2>';
    echo '<p class="vms-dt-section-desc">' . esc_html__('Download either the line-level ledger or the per-event summary using the same filters shown above.', 'vms-data-tools') . '</p>';
    echo '<div class="vms-dt-actions">';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="vms_dt_ticket_revenue_export_lines_csv" />';
    echo '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce('vms_dt_ticket_revenue_export_lines_csv')) . '" />';
    vms_dt_ticket_revenue_download_hidden_fields($args);
    echo '<button class="button button-primary" type="submit">' . esc_html__('Download line CSV', 'vms-data-tools') . '</button>';
    echo '</form>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="vms_dt_ticket_revenue_export_event_summary_csv" />';
    echo '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce('vms_dt_ticket_revenue_export_event_summary_csv')) . '" />';
    vms_dt_ticket_revenue_download_hidden_fields($args);
    echo '<button class="button" type="submit">' . esc_html__('Download event summary CSV', 'vms-data-tools') . '</button>';
    echo '</form>';

    echo '</div>';
    echo '</div>';

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<h2>' . esc_html__('Event summary', 'vms-data-tools') . '</h2>';
    echo '<p class="vms-dt-section-desc">' . esc_html__('Rollup of event-linked Woo lines grouped by resolved event / Event Plan.', 'vms-data-tools') . '</p>';
    vms_dt_ticket_revenue_render_event_summary_table($event_summary);
    echo '</div>';

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<h2>' . esc_html__('Line preview', 'vms-data-tools') . '</h2>';
    echo '<p class="vms-dt-section-desc">' . sprintf(esc_html__('Showing up to the first %d matching lines in preview. Use the CSV download for the full set.', 'vms-data-tools'), $preview_limit) . '</p>';
    vms_dt_ticket_revenue_render_line_preview($rows, $preview_limit);
    echo '</div>';

    vms_dt_ticket_revenue_render_unresolved_table(
        $unresolved_rows,
        (int) ($counts['unresolved_rows_captured'] ?? count($unresolved_rows)),
        (int) ($counts['line_items_skipped_unlinked'] ?? 0)
    );

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<h2>' . esc_html__('Scan summary', 'vms-data-tools') . '</h2>';
    echo '<ul class="vms-dt-list">';
    echo '<li>' . sprintf(esc_html__('Orders scanned: %d', 'vms-data-tools'), (int) ($counts['orders_scanned'] ?? 0)) . '</li>';
    echo '<li>' . sprintf(esc_html__('Orders with event-linked lines: %d', 'vms-data-tools'), (int) ($counts['orders_with_event_lines'] ?? 0)) . '</li>';
    echo '<li>' . sprintf(esc_html__('Woo line items scanned: %d', 'vms-data-tools'), (int) ($counts['line_items_scanned'] ?? 0)) . '</li>';
    echo '<li>' . sprintf(esc_html__('Exported event-linked lines: %d', 'vms-data-tools'), (int) ($counts['line_items_exported'] ?? 0)) . '</li>';
    echo '<li>' . sprintf(esc_html__('Skipped lines with no product reference: %d', 'vms-data-tools'), (int) ($counts['line_items_skipped_no_product'] ?? 0)) . '</li>';
    echo '<li>' . sprintf(esc_html__('Skipped lines with unresolved event linkage: %d', 'vms-data-tools'), (int) ($counts['line_items_skipped_unresolved_event'] ?? 0)) . '</li>';
    echo '<li>' . sprintf(esc_html__('Skipped by filters: %d', 'vms-data-tools'), (int) ($counts['line_items_skipped_filtered'] ?? 0)) . '</li>';
    echo '</ul>';
    echo '</div>';

    echo '</div>';
}

function vms_dt_ticket_revenue_post_args(): array
{
    return vms_dt_ticket_revenue_request_args('post');
}

function vms_dt_ticket_revenue_download_line_csv(): void
{
    if (!function_exists('vms_dt_current_user_can_manage_tools') || !vms_dt_current_user_can_manage_tools()) {
        wp_die(esc_html__('You do not have permission to download this file.', 'vms-data-tools'));
    }

    check_admin_referer('vms_dt_ticket_revenue_export_lines_csv', 'nonce');

    if (!vms_dt_has_core_function('vms_ticket_revenue_build_report')) {
        wp_die(esc_html__('VMS core ticket revenue service is missing.', 'vms-data-tools'));
    }

    $report = vms_dt_call_core_function('vms_ticket_revenue_build_report', vms_dt_ticket_revenue_post_args());
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, array(
        'order_id',
        'order_number',
        'order_status',
        'sold_date',
        'sold_datetime',
        'customer_name',
        'customer_email',
        'payment_method',
        'product_id',
        'product_sku',
        'item_kind',
        'item_name',
        'quantity',
        'refunded_quantity',
        'gross_subtotal',
        'discount',
        'net_subtotal',
        'tax',
        'refunded_subtotal',
        'refunded_tax',
        'cash_total',
        'tec_event_id',
        'event_title',
        'event_slug',
        'event_date',
        'event_plan_id',
        'event_plan_title',
        'recognition_status',
        'recognition_as_of_date',
        'resolution_source',
        'currency',
    ));

    foreach ((array) ($report['rows'] ?? array()) as $row) {
        fputcsv($fh, array(
            (int) ($row['order_id'] ?? 0),
            (string) ($row['order_number'] ?? ''),
            (string) ($row['order_status'] ?? ''),
            (string) ($row['sold_date'] ?? ''),
            (string) ($row['sold_datetime'] ?? ''),
            (string) ($row['customer_name'] ?? ''),
            (string) ($row['customer_email'] ?? ''),
            (string) ($row['payment_method'] ?? ''),
            (int) ($row['product_id'] ?? 0),
            (string) ($row['product_sku'] ?? ''),
            (string) ($row['item_kind'] ?? ''),
            (string) ($row['item_name'] ?? ''),
            (int) ($row['quantity'] ?? 0),
            (int) ($row['refunded_quantity'] ?? 0),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['gross_subtotal_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['discount_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['net_subtotal_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['tax_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['refunded_subtotal_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['refunded_tax_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['cash_total_cents'] ?? 0)),
            (int) ($row['tec_event_id'] ?? 0),
            (string) ($row['event_title'] ?? ''),
            (string) ($row['event_slug'] ?? ''),
            (string) ($row['event_date'] ?? ''),
            (int) ($row['event_plan_id'] ?? 0),
            (string) ($row['event_plan_title'] ?? ''),
            (string) ($row['recognition_status'] ?? ''),
            (string) ($row['recognition_as_of_date'] ?? ''),
            (string) ($row['resolution_source'] ?? ''),
            (string) ($row['order_currency'] ?? ''),
        ));
    }

    rewind($fh);
    $csv = (string) stream_get_contents($fh);
    fclose($fh);

    $filename = 'vms-ticket-revenue-lines-' . wp_date('Ymd-His', time(), wp_timezone()) . '.csv';
    vms_dt_download_csv_response($filename, $csv);
}
add_action('admin_post_vms_dt_ticket_revenue_export_lines_csv', 'vms_dt_ticket_revenue_download_line_csv');

function vms_dt_ticket_revenue_download_event_summary_csv(): void
{
    if (!function_exists('vms_dt_current_user_can_manage_tools') || !vms_dt_current_user_can_manage_tools()) {
        wp_die(esc_html__('You do not have permission to download this file.', 'vms-data-tools'));
    }

    check_admin_referer('vms_dt_ticket_revenue_export_event_summary_csv', 'nonce');

    if (!vms_dt_has_core_function('vms_ticket_revenue_build_report')) {
        wp_die(esc_html__('VMS core ticket revenue service is missing.', 'vms-data-tools'));
    }

    $report = vms_dt_call_core_function('vms_ticket_revenue_build_report', vms_dt_ticket_revenue_post_args());
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, array(
        'event_plan_id',
        'event_plan_title',
        'tec_event_id',
        'event_title',
        'event_slug',
        'event_date',
        'recognition_status',
        'order_count',
        'line_count',
        'gross_subtotal',
        'discount',
        'net_subtotal',
        'tax',
        'refunded_subtotal',
        'refunded_tax',
        'cash_total',
    ));

    foreach ((array) ($report['event_summary'] ?? array()) as $row) {
        fputcsv($fh, array(
            (int) ($row['event_plan_id'] ?? 0),
            (string) ($row['event_plan_title'] ?? ''),
            (int) ($row['tec_event_id'] ?? 0),
            (string) ($row['event_title'] ?? ''),
            (string) ($row['event_slug'] ?? ''),
            (string) ($row['event_date'] ?? ''),
            (string) ($row['recognition_status'] ?? ''),
            (int) ($row['order_count'] ?? 0),
            (int) ($row['line_count'] ?? 0),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['gross_subtotal_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['discount_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['net_subtotal_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['tax_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['refunded_subtotal_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['refunded_tax_cents'] ?? 0)),
            vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', (int) ($row['cash_total_cents'] ?? 0)),
        ));
    }

    rewind($fh);
    $csv = (string) stream_get_contents($fh);
    fclose($fh);

    $filename = 'vms-ticket-revenue-events-' . wp_date('Ymd-His', time(), wp_timezone()) . '.csv';
    vms_dt_download_csv_response($filename, $csv);
}
add_action('admin_post_vms_dt_ticket_revenue_export_event_summary_csv', 'vms_dt_ticket_revenue_download_event_summary_csv');
