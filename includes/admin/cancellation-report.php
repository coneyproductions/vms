<?php
/** Persistent cancellation completion and explicit refund preflight. */
defined('ABSPATH') || exit;

function bvmgr_cancel_report_url(int $event_plan_id, string $job_id = '', string $run_id = ''): string
{
    return add_query_arg(array('page' => 'bvm-cancellation-report', 'event_plan_id' => $event_plan_id, 'job_id' => $job_id, 'run_id' => $run_id), admin_url('admin.php'));
}

function bvmgr_cancel_report_links(int $event_plan_id, array $summary): void
{
    if (!current_user_can('manage_woocommerce')) return;
    echo '<p><a class="button" href="' . esc_url(bvmgr_cancel_report_url($event_plan_id)) . '">' . esc_html__('Cancellation Report / Refund Preflight', 'backstage-venue-manager') . '</a></p>';
    foreach (($summary['previous_jobs'] ?? array()) as $job_id => $job) {
        echo '<p><a href="' . esc_url(bvmgr_cancel_report_url($event_plan_id, $job_id)) . '">' . esc_html($job_id . ' — ' . ($job['created_at_gmt'] ?? '')) . '</a></p>';
    }
}

function bvmgr_cancel_report_select(array $summary, string $job_id, string $run_id): array
{
    if ($job_id !== '' && ($summary['job_id'] ?? '') !== $job_id) $summary = $summary['previous_jobs'][$job_id] ?? array();
    if ($run_id !== '') {
        foreach (($summary['runs'] ?? array()) as $run) if (($run['run_id'] ?? '') === $run_id) {
            $summary['purchase_report'] = $run['purchase_report'] ?? array('status' => 'attention_required', 'warnings' => array('Historical run coverage is unverified.'));
            return $summary;
        }
        return array();
    }
    return $summary;
}

function bvmgr_cancel_report_label(string $value): string
{
    $labels = array(
        'event_product' => __('Event product', 'backstage-venue-manager'),
        'event_snapshot' => __('Event-linked purchase', 'backstage-venue-manager'),
        'express_bar' => __('Express Bar / preorders', 'backstage-venue-manager'),
        'refunded_or_zero' => __('Refunded / no balance', 'backstage-venue-manager'),
        'expected' => __('Expected refund', 'backstage-venue-manager'),
        'actual' => __('Actual additional refund', 'backstage-venue-manager'),
        'excluded' => __('Excluded', 'backstage-venue-manager'),
        'residual' => __('Unexplained residual', 'backstage-venue-manager'),
        'expected_residual' => __('Expected residual after proposal', 'backstage-venue-manager'),
    );
    return $labels[$value] ?? ucfirst(str_replace('_', ' ', $value));
}

function bvmgr_cancel_report_money($value, string $currency): string
{
    return $currency . ' ' . number_format((float) $value, (function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2), '.', ',');
}

/** Historical money comes from the snapshot; Woo reads supply safe order edit links. */
function bvmgr_cancel_report_render(array $report, bool $preflight = false): void
{
    $status = $report['status'] ?? 'attention_required';
    echo '<h2>' . esc_html(str_replace('_', ' ', ucfirst($status))) . '</h2>';
    if (in_array($status, array('attention_required', 'failed', 'planned'), true)) echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Cancellation refunds are not complete. Review outstanding components and exceptions below.', 'backstage-venue-manager') . '</strong></p></div>';
    if (!$preflight) foreach (($report['totals'] ?? array()) as $currency => $totals) {
        if ((float) ($totals['residual'] ?? 0) > 0) echo '<div class="notice notice-error inline"><p><strong style="font-size:1.2em">' . esc_html__('Unexplained residual:', 'backstage-venue-manager') . ' ' . esc_html(bvmgr_cancel_report_money($totals['residual'], $currency)) . '</strong><br>' . esc_html__('The expected refund has not been fully accounted for. Review the outstanding components below.', 'backstage-venue-manager') . '</p></div>';
    }
    foreach (($report['warnings'] ?? array()) as $warning) echo '<div class="notice notice-error inline"><p>' . esc_html(bvmgr_cancel_report_label($warning)) . '</p></div>';
    echo '<p>' . esc_html__('Affected orders:', 'backstage-venue-manager') . ' ' . esc_html((string) count($report['orders'] ?? array())) . '</p>';
    foreach (($report['counts'] ?? array()) as $label => $count) echo '<span>' . esc_html(bvmgr_cancel_report_label($label) . ': ' . $count) . ' &nbsp; </span>';
    foreach (($report['totals'] ?? array()) as $currency => $totals) {
        echo '<p><strong>' . esc_html($currency) . '</strong> ';
        foreach ($totals as $label => $amount) echo esc_html(($preflight && $label === 'residual' ? __('Balance before execution', 'backstage-venue-manager') : bvmgr_cancel_report_label($label)) . ': ' . bvmgr_cancel_report_money($amount, $currency)) . ' &nbsp; ';
        echo '</p>';
    }
    $orders = $report['orders'] ?? array();
    usort($orders, static function ($a, $b) {
        $rank = array('failure' => 0, 'partial' => 0, 'attention_required' => 0, 'exclusions' => 1, 'planned' => 2, 'success' => 3);
        return ($rank[$a['status']] ?? 0) <=> ($rank[$b['status']] ?? 0);
    });
    foreach ($orders as $order) {
        $currency = $order['currency'];
        echo '<section><h3>' . esc_html('#' . $order['order_number'] . ' — ' . $order['customer']) . '</h3>';
        // Woo supplies the HPOS/legacy edit route; never construct an order-post URL.
        $woo = function_exists('wc_get_order') && is_numeric($order['order_id']) ? wc_get_order($order['order_id']) : null;
        if ($woo && current_user_can('edit_shop_order', $order['order_id'])) echo '<p><a href="' . esc_url($woo->get_edit_order_url()) . '">' . esc_html__('Order details', 'backstage-venue-manager') . '</a></p>';
        echo '<p><strong>' . esc_html(bvmgr_cancel_report_label($order['status'] ?? '')) . '</strong> ' . esc_html(bvmgr_cancel_report_label($order['failure'] ?? '')) . '</p>';
        echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr>';
        foreach (array('Component / source / quantity', 'Original', 'Previously refunded', 'Proposed additional', 'Actual additional', 'Expected residual after proposal', 'Residual', 'Outcome / reason') as $heading) echo '<th>' . esc_html($heading) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($order['line_items'] as $line) {
            echo '<tr' . (in_array($line['outcome'] ?? $line['state'], array('unresolved', 'excluded'), true) ? ' style="background:#fff4e5"' : '') . '><td>' . esc_html($line['name'] . ' / ' . bvmgr_cancel_report_label($line['provider']) . ' / ' . $line['qty']) . '</td>';
            foreach (array('original_amount', 'previously_refunded', 'proposed', 'actual', 'expected_residual', 'residual') as $field) echo '<td>' . esc_html(bvmgr_cancel_report_money($line[$field] ?? 0, $currency)) . '</td>';
            echo '<td><strong>' . esc_html(bvmgr_cancel_report_label($preflight ? $line['state'] : ($line['outcome'] ?? $line['state']))) . '</strong> ' . esc_html($line['state'] === 'excluded' ? $line['reason'] : bvmgr_cancel_report_label($line['reason'])) . '</td></tr>';
            if (!empty($line['exclusion'])) echo '<tr><td colspan="8">' . esc_html('Excluded by user ' . $line['exclusion']['actor'] . ' at ' . $line['exclusion']['at_gmt'] . ' UTC; job ' . $line['exclusion']['job_id']) . '</td></tr>';
            if ($preflight && $line['remaining'] > 0) echo '<tr><td colspan="8"><label>' . esc_html__('Exclude this component only with an auditable reason (leave blank to refund):', 'backstage-venue-manager') . '<br><textarea form="bvm-cancel-accept" name="exclusions[' . esc_attr($line['key']) . ']" rows="2" cols="65" style="max-width:100%;box-sizing:border-box"></textarea></label></td></tr>';
        }
        echo '</tbody></table></div></section>';
    }
    foreach (($report['new_components'] ?? array()) as $line) echo '<p><strong>' . esc_html('Unaccepted component ' . $line['key'] . ': ' . $line['name'] . '; remaining ' . $line['remaining']) . '</strong></p>';
}

function bvmgr_cancel_report_page(): void
{
    $event_plan_id = bvmgr_request_read_absint($_GET, 'event_plan_id');
    if (get_post_type($event_plan_id) !== 'vms_event_plan' || !current_user_can('edit_post', $event_plan_id) || !current_user_can('manage_woocommerce')) wp_die(esc_html__('You cannot view this cancellation report.', 'backstage-venue-manager'), '', array('response' => 403));
    $job_id = bvmgr_request_read_text_field($_GET, 'job_id');
    $run_id = bvmgr_request_read_text_field($_GET, 'run_id');
    $current = (array) get_post_meta($event_plan_id, '_vms_cancel_job_summary', true);
    $summary = bvmgr_cancel_report_select($current, $job_id, $run_id);
    echo '<div class="wrap"><h1>' . esc_html__('Cancellation Completion / Cancellation Report', 'backstage-venue-manager') . '</h1>';
    echo '<p><a class="button" href="' . esc_url(get_edit_post_link($event_plan_id, 'raw')) . '">' . esc_html__('Back to Event Plan', 'backstage-venue-manager') . '</a></p>';
    echo '<h2>' . esc_html(get_the_title($event_plan_id)) . '</h2>';
    if (empty($summary['job_id'])) { echo '<p>' . esc_html__('No saved cancellation run. Historical coverage is unverified.', 'backstage-venue-manager') . '</p></div>'; return; }
    echo '<p>' . esc_html($summary['job_id'] . ' / ' . $run_id . ' — user ' . ($summary['created_by_user_id'] ?? '') . ' — ' . ($summary['created_at_gmt'] ?? '') . ' UTC') . '</p>';
    $report = $summary['purchase_report'] ?? array('status' => 'attention_required', 'warnings' => array('Historical coverage is unverified. No financial completion claim can be made.'));
    echo '<p>' . esc_html('Occurrence: ' . ($report['occurrence'] ?? 'unverified') . '; linked event #' . ($report['tec_event_id'] ?? 0) . '; report recorded ' . ($summary['last_run_at_gmt'] ?? 'unknown') . ' UTC') . '</p>';
    if ($run_id === '' && $summary['job_id'] === ($current['job_id'] ?? '') && get_post_meta($event_plan_id, '_vms_cancel_job_state', true) === 'running') $report['status'] = 'processing';
    $preflight = $run_id === '' && $summary['job_id'] === ($current['job_id'] ?? '') && empty($summary['purchase_preflight']['accepted_by']) && in_array($summary['policy'], bvmgr_cancellation_auto_refund_policies(), true);
    if ($preflight) {
        $scope = bvmgr_cancel_purchase_discover($event_plan_id);
        $report = bvmgr_cancel_preflight_report($scope);
        $execution_ready = false;
        foreach ($summary['steps'] as $step) {
            if ($step['key'] === 'provider_sales_stop' && $step['status'] === 'done') $execution_ready = true;
            if ($step['key'] !== 'refund_execution' && in_array($step['status'], array('blocked', 'failed'), true)) $report['warnings'][] = $step['key'] . ': ' . ($step['message'] ?? '');
        }
        if (!$execution_ready || $report['warnings']) $report['status'] = 'attention_required';
        echo '<h2>' . esc_html__('Refund Preflight', 'backstage-venue-manager') . '</h2><p>' . esc_html__('Review every customer and component. Unresolved items require a reasoned exclusion or correction. Coverage failures block execution.', 'backstage-venue-manager') . '</p>';
        echo '<p>' . esc_html('Occurrence: ' . $scope['occurrence'] . '; linked event #' . $scope['tec_event_id']) . '</p>';
        echo '<form id="bvm-cancel-accept" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        foreach (array('action' => 'bvm_cancel_accept_preflight', 'event_plan_id' => $event_plan_id, 'job_id' => $summary['job_id'], 'scope_hash' => bvmgr_cancel_scope_hash($scope)) as $key => $value) echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
        wp_nonce_field('bvm_cancel_accept_' . $event_plan_id . '_' . $summary['job_id']);
        echo '</form>';
    }
    bvmgr_cancel_report_render($report, $preflight);
    if ($preflight && $execution_ready && !empty($scope['coverage_complete']) && empty($scope['warnings'])) echo '<p><button class="button button-primary" form="bvm-cancel-accept" type="submit">' . esc_html__('Accept preflight and execute refunds', 'backstage-venue-manager') . '</button></p>';
    echo '<h2>' . esc_html__('Run history', 'backstage-venue-manager') . '</h2>';
    foreach (($summary['runs'] ?? array()) as $run) if (!empty($run['run_id'])) echo '<p><a href="' . esc_url(bvmgr_cancel_report_url($event_plan_id, $summary['job_id'], $run['run_id'])) . '">' . esc_html($run['run_id'] . ' — ' . ($run['state_after'] ?? '')) . '</a></p>';
    echo '<p>' . esc_html__('For unresolved or uncertain refunds, inspect Woo order/refund and gateway records before any manual retry. Reopening this report performs no refunds.', 'backstage-venue-manager') . '</p></div>';
}

add_action('admin_menu', static function () {
    add_submenu_page(null, __('Cancellation Report', 'backstage-venue-manager'), __('Cancellation Report', 'backstage-venue-manager'), 'manage_woocommerce', 'bvm-cancellation-report', 'bvmgr_cancel_report_page');
});
add_action('admin_post_bvm_cancel_accept_preflight', static function () {
    if (bvmgr_request_server_value('REQUEST_METHOD') !== 'POST') wp_die('POST required', '', array('response' => 405));
    $id = bvmgr_request_read_absint($_POST, 'event_plan_id');
    $job = bvmgr_request_read_text_field($_POST, 'job_id');
    check_admin_referer('bvm_cancel_accept_' . $id . '_' . $job);
    $result = bvmgr_cancel_accept_preflight($id, $job, bvmgr_request_read_text_field($_POST, 'scope_hash'), (array) bvmgr_request_read_array($_POST, 'exclusions'));
    if (empty($result['ok'])) wp_die(esc_html($result['error']), '', array('response' => 409));
    bvmgr_cancellation_run_job($id, array('job_id' => $job));
    wp_safe_redirect(bvmgr_cancel_report_url($id, $job));
    exit;
});
// Redirect only after a cancellation operation actually ran in this request.
add_action('vms_cancellation_job_ran', static function ($id) { $GLOBALS['bvm_cancel_report_redirect_id'] = (int) $id; }, 20, 1);
add_filter('redirect_post_location', static function ($location, $id) {
    return ($GLOBALS['bvm_cancel_report_redirect_id'] ?? 0) === (int) $id ? bvmgr_cancel_report_url((int) $id) : $location;
}, PHP_INT_MAX, 2);
