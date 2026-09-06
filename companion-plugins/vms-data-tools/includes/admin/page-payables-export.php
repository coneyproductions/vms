<?php
if (!defined('ABSPATH')) {
    exit;
}

function vms_dt_register_payables_export_page()
{
$parent_slug = 'vms-data-tools';

$cap = function_exists('vms_dt_manage_capability') ? vms_dt_manage_capability() : 'manage_options';

add_submenu_page(
    $parent_slug,
    'Payables Export (QBO Bills CSV)',
    'Payables Export',
    $cap,
    vms_dt_get_menu_slug_payables_export(),
    'vms_dt_render_payables_export_page'
);
}

/**
 * Render: Payables Export
 * v1: Builds a QBO Bills CSV compatible with importer apps (SaasAnt / Request.finance style mapping).
 */
function vms_dt_render_payables_export_page()
{
    if (!function_exists('vms_dt_current_user_can_manage_tools') || !vms_dt_current_user_can_manage_tools()) {
        return;
    }

    $page_slug = function_exists('vms_dt_get_menu_slug_payables_export') ? vms_dt_get_menu_slug_payables_export() : 'vms-dt-payables-export';

    $event_date = isset($_GET['event_date']) ? sanitize_text_field((string) wp_unslash($_GET['event_date'])) : '';
    if ($event_date === '') {
        $event_date = date('Y-m-d');
    }

    $venue_ids = [];
    if (isset($_GET['venue_ids'])) {
        $raw = (array) wp_unslash($_GET['venue_ids']);
        $venue_ids = array_values(array_filter(array_map('intval', $raw)));
    }

    $terms_days = isset($_GET['terms_days']) ? (int) $_GET['terms_days'] : 0;

    $default_account = isset($_GET['default_account']) ? sanitize_text_field((string) wp_unslash($_GET['default_account'])) : 'Contract Labor';
    $default_tax_code = isset($_GET['default_tax_code']) ? sanitize_text_field((string) wp_unslash($_GET['default_tax_code'])) : '';

    $do_preview = isset($_GET['vms_dt_preview']) && ((string) $_GET['vms_dt_preview'] === '1');

    echo '<div class="wrap vms-dt-wrap">';
    echo '<h1>' . esc_html__('Payables Export (QBO Bills CSV)', 'vms-data-tools') . '</h1>';

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<p>' . esc_html__('Select an event date and one or more venues. VMS will build one Bill per contractor per venue per date, using the Event Plan flat fee amount.', 'vms-data-tools') . '</p>';
    echo '<p><strong>' . esc_html__('Important:', 'vms-data-tools') . '</strong> ' . esc_html__('Most US QuickBooks Online accounts do not provide a native Bills CSV import. This export is designed for importer apps (for example, SaasAnt Transactions) that map your CSV columns into QuickBooks Bills.', 'vms-data-tools') . '</p>';
    echo '</div>';

    // Build venue list
    $venue_post_type = (string) vms_dt_core_constant(
        'VMS_VENUE_CPT',
        vms_dt_core_constant('VMS_CPT_VENUE', 'vms_venue')
    );
    $venues = get_posts([
        'post_type'      => $venue_post_type,
        'post_status'    => ['publish', 'draft', 'private'],
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ]);

    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '" />';

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<h2>' . esc_html__('Selection', 'vms-data-tools') . '</h2>';

    echo '<p><label><strong>' . esc_html__('Event date', 'vms-data-tools') . '</strong><br />';
    echo '<input type="date" name="event_date" value="' . esc_attr($event_date) . '" /></label></p>';

    echo '<p><strong>' . esc_html__('Venues', 'vms-data-tools') . '</strong><br />';

    if (empty($venues)) {
        echo '<em>' . esc_html__('No venues found.', 'vms-data-tools') . '</em>';
    } else {
        foreach ($venues as $vid) {
            $vid = (int) $vid;
            $label = (string) get_the_title($vid);
            if ($label === '') {
                $label = 'Venue #' . $vid;
            }
            $checked = in_array($vid, $venue_ids, true) ? 'checked' : '';
            echo '<label style="display:block;margin:6px 0;">';
            echo '<input type="checkbox" name="venue_ids[]" value="' . esc_attr((string) $vid) . '" ' . $checked . ' /> ';
            echo esc_html($label);
            echo '</label>';
        }
    }
    echo '</p>';

    echo '<p><label><strong>' . esc_html__('Terms (optional)', 'vms-data-tools') . '</strong><br />';
    echo '<select name="terms_days">';
    $terms_opts = [
        0  => 'Due on event date (Net 0)',
        7  => 'Net 7',
        14 => 'Net 14',
        30 => 'Net 30',
    ];
    foreach ($terms_opts as $days => $label) {
        $sel = ((int) $terms_days === (int) $days) ? 'selected' : '';
        echo '<option value="' . esc_attr((string) $days) . '" ' . $sel . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label></p>';

    echo '<p><label><strong>' . esc_html__('Default expense account (QBO "Category Account")', 'vms-data-tools') . '</strong><br />';
    echo '<input type="text" name="default_account" value="' . esc_attr($default_account) . '" style="min-width:280px;" /></label></p>';

    echo '<p><label><strong>' . esc_html__('Default tax code (optional)', 'vms-data-tools') . '</strong><br />';
    echo '<input type="text" name="default_tax_code" value="' . esc_attr($default_tax_code) . '" style="min-width:180px;" />';
    echo '<br /><span class="description">' . esc_html__('Leave blank if your importer/QBO setup does not require a tax code. If required, a common value is "NON" (but it must exist in your QuickBooks company).', 'vms-data-tools') . '</span>';
    echo '</label></p>';

    echo '<p><button class="button button-primary" type="submit" name="vms_dt_preview" value="1">' . esc_html__('Preview', 'vms-data-tools') . '</button></p>';

    echo '</div>';
    echo '</form>';

    if (!$do_preview) {
        echo '</div>';
        return;
    }

    if (!vms_dt_has_core_function('vms_payables_build_bills_for_export')) {
        echo '<div class="notice notice-error"><p>' . esc_html__('VMS core payables builder is missing. Please update/activate the VMS core plugin.', 'vms-data-tools') . '</p></div>';
        echo '</div>';
        return;
    }

    $result = vms_dt_call_core_function('vms_payables_build_bills_for_export', $event_date, $venue_ids, [
        'terms_days' => $terms_days,
    ]);

    $bills = isset($result['bills']) ? (array) $result['bills'] : [];
    $warnings = isset($result['warnings']) ? (array) $result['warnings'] : [];

    if (!empty($warnings)) {
        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Notes / skipped items', 'vms-data-tools') . '</strong></p><ul style="margin-left:18px;">';
        foreach ($warnings as $w) {
            echo '<li>' . esc_html((string) $w) . '</li>';
        }
        echo '</ul></div>';
    }

    if (empty($bills)) {
        echo '<div class="notice notice-info"><p>' . esc_html__('No payable bills found for that date/venue selection (or all items were skipped).', 'vms-data-tools') . '</p></div>';
        echo '</div>';
        return;
    }

    // Compute totals for preview
    $grand_total = 0.0;
    foreach ($bills as $b) {
        $lines = isset($b['lines']) ? (array) $b['lines'] : [];
        foreach ($lines as $ln) {
            $grand_total += isset($ln['amount']) ? (float) $ln['amount'] : 0.0;
        }
    }

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<h2>' . esc_html__('Preview', 'vms-data-tools') . '</h2>';
    echo '<p><strong>' . esc_html__('Bills:', 'vms-data-tools') . '</strong> ' . esc_html((string) count($bills)) . ' &nbsp; ';
    echo '<strong>' . esc_html__('Total:', 'vms-data-tools') . '</strong> $' . esc_html(number_format($grand_total, 2)) . '</p>';

    echo '<div class="vms-dt-grid">';
    foreach ($bills as $b) {
        $supplier = isset($b['supplier']) ? (string) $b['supplier'] : '';
        $bill_no  = isset($b['bill_no']) ? (string) $b['bill_no'] : '';
        $venue_id = isset($b['venue_id']) ? (int) $b['venue_id'] : 0;
        $venue_name = $venue_id ? (string) get_the_title($venue_id) : '';
        if ($venue_name === '') {
            $venue_name = 'Venue #' . $venue_id;
        }

        $bill_total = 0.0;
        $lines = isset($b['lines']) ? (array) $b['lines'] : [];
        foreach ($lines as $ln) {
            $bill_total += isset($ln['amount']) ? (float) $ln['amount'] : 0.0;
        }

        echo '<div class="vms-dt-card">';
        echo '<div style="font-weight:700;">' . esc_html($supplier) . '</div>';
        echo '<div style="opacity:.8;margin-top:4px;">' . esc_html($venue_name) . '</div>';
        echo '<div style="opacity:.8;">' . esc_html__('Bill No:', 'vms-data-tools') . ' ' . esc_html($bill_no) . '</div>';
        echo '<div style="margin-top:8px;"><strong>$' . esc_html(number_format($bill_total, 2)) . '</strong> <span style="opacity:.75">(' . esc_html((string) count($lines)) . ' line(s))</span></div>';
        echo '</div>';
    }
    echo '</div>';

    // Download button (POST)
    $action = 'vms_dt_payables_export_csv';
    $nonce  = wp_create_nonce('vms_dt_payables_export_csv');

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:14px;">';
    echo '<input type="hidden" name="action" value="' . esc_attr($action) . '" />';
    echo '<input type="hidden" name="nonce" value="' . esc_attr($nonce) . '" />';
    echo '<input type="hidden" name="event_date" value="' . esc_attr($event_date) . '" />';
    foreach ($venue_ids as $vid) {
        echo '<input type="hidden" name="venue_ids[]" value="' . esc_attr((string) (int) $vid) . '" />';
    }
    echo '<input type="hidden" name="terms_days" value="' . esc_attr((string) (int) $terms_days) . '" />';
    echo '<input type="hidden" name="default_account" value="' . esc_attr($default_account) . '" />';
    echo '<input type="hidden" name="default_tax_code" value="' . esc_attr($default_tax_code) . '" />';

    echo '<p style="margin-top:16px;"><button class="button button-primary" type="submit">' . esc_html__('Download Bills CSV', 'vms-data-tools') . '</button></p>';
    echo '</form>';

    echo '</div>';
    echo '</div>';
}

/**
 * Handler: build + download the QBO Bills CSV.
 */
add_action('admin_post_vms_dt_payables_export_csv', 'vms_dt_handle_payables_export_download');

function vms_dt_handle_payables_export_download()
{
    if (!function_exists('vms_dt_current_user_can_manage_tools') || !vms_dt_current_user_can_manage_tools()) {
        wp_die('Not allowed.');
    }

    $nonce = isset($_POST['nonce']) ? (string) wp_unslash($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'vms_dt_payables_export_csv')) {
        wp_die('Bad nonce.');
    }

    $event_date = isset($_POST['event_date']) ? sanitize_text_field((string) wp_unslash($_POST['event_date'])) : '';
    $venue_ids = [];
    if (isset($_POST['venue_ids'])) {
        $raw = (array) wp_unslash($_POST['venue_ids']);
        $venue_ids = array_values(array_filter(array_map('intval', $raw)));
    }

    $terms_days = isset($_POST['terms_days']) ? (int) $_POST['terms_days'] : 0;
    $default_account = isset($_POST['default_account']) ? sanitize_text_field((string) wp_unslash($_POST['default_account'])) : 'Contract Labor';
    $default_tax_code = isset($_POST['default_tax_code']) ? sanitize_text_field((string) wp_unslash($_POST['default_tax_code'])) : '';

    if (!vms_dt_has_core_function('vms_payables_build_bills_for_export')) {
        wp_die('VMS core payables builder missing.');
    }

    $result = vms_dt_call_core_function('vms_payables_build_bills_for_export', $event_date, $venue_ids, [
        'terms_days' => $terms_days,
    ]);

    $bills = isset($result['bills']) ? (array) $result['bills'] : [];

    $rows = [];
    // Header (importer-friendly names; map into QBO Bills fields)
    $rows[] = [
        'BillNo',
        'Supplier',
        'BillDate',
        'DueDate',
        'Account',
        'LineDescription',
        'LineAmount',
        'LineTaxCode',
    ];

    foreach ($bills as $b) {
        $bill_no = isset($b['bill_no']) ? (string) $b['bill_no'] : '';
        $supplier = isset($b['supplier']) ? (string) $b['supplier'] : '';
        $bill_date = isset($b['bill_date']) ? (string) $b['bill_date'] : '';
        $due_date = isset($b['due_date']) ? (string) $b['due_date'] : '';

        // Use US-friendly date format (MM/DD/YYYY). Most importer tools let you choose the format anyway.
        $bill_date_fmt = $bill_date ? date('m/d/Y', strtotime($bill_date)) : '';
        $due_date_fmt  = $due_date ? date('m/d/Y', strtotime($due_date)) : '';

        $lines = isset($b['lines']) ? (array) $b['lines'] : [];
        foreach ($lines as $ln) {
            $amt = isset($ln['amount']) ? (float) $ln['amount'] : 0.0;
            $desc = isset($ln['description']) ? (string) $ln['description'] : '';

            $rows[] = [
                $bill_no,
                $supplier,
                $bill_date_fmt,
                $due_date_fmt,
                $default_account,
                $desc,
                number_format($amt, 2, '.', ''),
                $default_tax_code,
            ];
        }
    }

    $filename = 'vms-qbo-bills-' . preg_replace('/[^0-9]/', '', $event_date) . '.csv';
    // Build CSV string
    $fh = fopen('php://temp', 'w+');
    foreach ($rows as $r) {
        fputcsv($fh, $r);
    }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);

    if (function_exists('vms_dt_download_csv_response')) {
        vms_dt_download_csv_response($filename, $csv);
        exit;
    }

    // Fallback: output CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    echo $csv;
    exit;
}
