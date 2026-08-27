<?php
defined('ABSPATH') || exit;

/**
 * Ticketing Integration (Phase A)
 *
 * Goals:
 * - Link an Event Plan to an existing TEC event (legacy imports).
 * - Detect Woo ticket products for that TEC event.
 * - Cache ticket stats on the Event Plan (sold + revenue) via an explicit refresh.
 *
 * Safety:
 * - Linking does NOT modify the TEC event.
 * - Stats are computed only when the operator clicks “Refresh ticket stats”.
 */

/**
 * Meta keys (registry-backed when available).
 */
function bvmgr_ticketing_meta_key(string $field, string $fallback): string
{
    if (function_exists('bvmgr_meta_key')) {
        $k = (string) bvmgr_meta_key('event_plan', $field);
        if ($k !== '') {
            return $k;
        }
    }
    return $fallback;
}

function bvmgr_ticketing_can_manage_plan(int $plan_id): bool
{
    if ($plan_id <= 0) {
        return false;
    }
    return current_user_can('edit_post', $plan_id);
}

function bvmgr_ticketing_admin_query_absint(string $key): int
{
    return bvmgr_request_read_absint($_GET, $key); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin ticketing screen state only scopes asset localization.
}


/**
 * AJAX helpers: keep JSON responses valid even if something prints output.
 * Any buffered output is discarded and included in the response for admins (truncated).
 */
function bvmgr_ticketing_ajax_attach_noise(array $data): array
{
    $noise = '';
    if (!empty($GLOBALS['bvmgr_ajax_ob_started'])) {
        $noise = (string) ob_get_contents();
        // We started this buffer explicitly in integrations/load.php for AJAX requests.
        // Close it now so our JSON response is not buffered behind any later output.
        @ob_end_clean();
        $GLOBALS['bvmgr_ajax_ob_started'] = false;
    }

    $noise = wp_strip_all_tags((string) $noise, false);
    if ($noise !== '' && current_user_can('manage_options')) {
        $data['_vms_ajax_noise'] = mb_substr($noise, 0, 400);
    }

    return $data;
}

function bvmgr_ticketing_ajax_send_success(array $data = array(), int $http_status = 200): void
{
    $data = bvmgr_ticketing_ajax_attach_noise($data);
    wp_send_json_success($data, $http_status);
}

function bvmgr_ticketing_ajax_send_error(array $data = array(), int $http_status = 400): void
{
    $data = bvmgr_ticketing_ajax_attach_noise($data);
    wp_send_json_error($data, $http_status);
}

function bvmgr_ticketing_ajax_discard_owned_buffer(): void
{
    if (empty($GLOBALS['bvmgr_ajax_ob_started'])) {
        return;
    }

    if (ob_get_level() > 0) {
        @ob_end_clean();
    }

    $GLOBALS['bvmgr_ajax_ob_started'] = false;
}

function bvmgr_ticketing_v2_ajax_send_success($data = null, ?int $status_code = null, int $flags = 0): void
{
    bvmgr_ticketing_ajax_discard_owned_buffer();

    if (func_num_args() < 2) {
        wp_send_json_success($data);
    } elseif (func_num_args() < 3) {
        wp_send_json_success($data, $status_code);
    } else {
        wp_send_json_success($data, $status_code, $flags);
    }
}

function bvmgr_ticketing_v2_ajax_send_error($data = null, ?int $status_code = null, int $flags = 0): void
{
    bvmgr_ticketing_ajax_discard_owned_buffer();

    if (func_num_args() < 2) {
        wp_send_json_error($data);
    } elseif (func_num_args() < 3) {
        wp_send_json_error($data, $status_code);
    } else {
        wp_send_json_error($data, $status_code, $flags);
    }
}

function bvmgr_ticketing_is_tec_active(): bool
{
    return post_type_exists('tribe_events');
}

function bvmgr_ticketing_is_woo_active(): bool
{
    return class_exists('WooCommerce') && function_exists('wc_get_product');
}

function bvmgr_ticketing_should_normalize_tec_ticket_state($tickets): bool
{
    if (!is_object($tickets)) {
        return false;
    }

    if (!method_exists($tickets, 'exist') || !method_exists($tickets, 'in_date_range') || !method_exists($tickets, 'sold_out')) {
        return false;
    }

    if (!$tickets->exist() || !$tickets->in_date_range() || !$tickets->sold_out()) {
        return false;
    }

    if (!isset($tickets['stock'])) {
        return false;
    }

    $stock = $tickets['stock'];
    if (!is_object($stock)) {
        return false;
    }

    $available = isset($stock->available) ? trim(wp_strip_all_tags((string) $stock->available)) : '';
    $sold_out  = isset($stock->sold_out) ? trim(wp_strip_all_tags((string) $stock->sold_out)) : '';

    return $available !== '' && $sold_out !== '';
}

function bvmgr_ticketing_ticket_link_label(): string
{
    $ticket_label = function_exists('tribe_get_ticket_label_plural')
        ? trim((string) tribe_get_ticket_label_plural('list_view_buy_now_button'))
        : '';

    if ($ticket_label === '') {
        $ticket_label = 'Tickets';
    }

    return sprintf('Get %s', $ticket_label);
}

function bvmgr_ticketing_normalize_tec_ticket_state($post, $event, $output, $filter)
{
    if (!$post instanceof WP_Post || $post->post_type !== 'tribe_events') {
        return $post;
    }

    if (!isset($post->tickets) || !bvmgr_ticketing_should_normalize_tec_ticket_state($post->tickets)) {
        return $post;
    }

    $stock = $post->tickets['stock'];
    $stock->sold_out = '';
    $post->tickets['stock'] = $stock;

    $link = isset($post->tickets['link']) && is_object($post->tickets['link'])
        ? $post->tickets['link']
        : (object) array();

    $link_label = isset($link->label) ? trim(wp_strip_all_tags((string) $link->label)) : '';
    $link_anchor = isset($link->anchor) ? trim((string) $link->anchor) : '';

    if ($link_label === '') {
        $link->label = bvmgr_ticketing_ticket_link_label();
    }

    if ($link_anchor === '') {
        $link->anchor = get_permalink($post) . (function_exists('tribe_tickets_new_views_is_enabled') && tribe_tickets_new_views_is_enabled()
            ? '#tribe-tickets__tickets-form'
            : '#tribe-tickets');
    }

    $post->tickets['link'] = $link;

    return $post;
}
add_filter('tribe_get_event_after', 'bvmgr_ticketing_normalize_tec_ticket_state', 30, 4);

function bvmgr_ticketing_can_search_products(): bool
{
    if (!is_admin()) {
        return false;
    }
    if (!post_type_exists("product")) {
        return false;
    }

    // Prefer Woo capability checks when available, but keep a reasonable fallback.
    if (current_user_can("manage_woocommerce")) {
        return true;
    }
    if (current_user_can("edit_products")) {
        return true;
    }
    return current_user_can("edit_posts");
}

/**
 * Get ticket product IDs for a given TEC event.
 *
 * Prefer the existing helper (meta query on _tribe_wooticket_for_event).
 */
function bvmgr_ticketing_get_ticket_product_ids_for_tec_event(int $tec_event_id): array
{
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return array();
    }

    if (function_exists('bvmgr_get_ticket_product_ids_for_event')) {
        $ids = bvmgr_get_ticket_product_ids_for_event($tec_event_id);
        return is_array($ids) ? array_values(array_unique(array_map('absint', $ids))) : array();
    }

    $q = new WP_Query(array(
        'post_type'      => 'product',
        'post_status'    => array('publish', 'draft', 'private'),
        'posts_per_page' => -1,
        'fields'         => 'ids',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Ticket statistics require an exhaustive ID-only lookup of every ticket product linked to this single TEC event; the native meta relation is the compatibility contract.
        'meta_query'     => array(
            array(
                'key'     => '_tribe_wooticket_for_event',
                'value'   => $tec_event_id,
                'compare' => '=',
            ),
        ),
    ));

    return array_values(array_unique(array_map('absint', $q->posts ?? array())));
}

/**
 * Compute sold + revenue for a set of Woo products.
 *
 * Notes:
 * - If Woo Analytics lookup tables are present, we use them for qty/revenue.
 * - Otherwise we fall back to WC_Product total_sales and price (approximate revenue).
 */
function bvmgr_ticketing_compute_stats(array $product_ids): array
{
    $product_ids = array_values(array_filter(array_map('absint', $product_ids)));
    if (empty($product_ids) || !bvmgr_ticketing_is_woo_active()) {
        return array(
            'provider'         => 'none',
            'qty_sold'         => 0,
            'revenue'          => 0.0,
            'revenue_label'    => 'N/A',
            'currency'         => '',
            'computed_at_gmt'  => time(),
        );
    }

    $currency = function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '';

    // Attempt Woo Analytics lookup table.
    global $wpdb;
    $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';

    $has_lookup = false;
    $cols = array();
    if ($wpdb && is_string($lookup_table)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Woo Analytics capability detection must inspect current lookup-table existence before selecting the statistics fallback; no WordPress API exposes this table state.
        $tbl = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $lookup_table));
        if ($tbl === $lookup_table) {
            $has_lookup = true;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Woo Analytics capability detection must inspect current lookup-table columns before choosing compatible quantity and revenue fields; no WordPress API exposes this schema.
            $cols_raw = $wpdb->get_col($wpdb->prepare('SHOW COLUMNS FROM %i', $lookup_table));
            if (is_array($cols_raw)) {
                $cols = array_flip(array_map('strtolower', $cols_raw));
            }
        }
    }

    if ($has_lookup) {
        $product_id_placeholders = implode(',', array_fill(0, count($product_ids), '%d'));

        $qty_col   = isset($cols['product_qty']) ? 'product_qty' : '';
        $gross_col = isset($cols['product_gross_revenue']) ? 'product_gross_revenue' : '';
        $net_col   = isset($cols['product_net_revenue']) ? 'product_net_revenue' : '';

        if ($qty_col !== '' && ($gross_col !== '' || $net_col !== '')) {
            $rev_col = $gross_col !== '' ? $gross_col : $net_col;
            $label   = $gross_col !== '' ? 'Gross revenue (Woo analytics)' : 'Net revenue (Woo analytics)';

            $sql = 'SELECT SUM(%i) AS qty, SUM(%i) AS revenue FROM %i WHERE product_id IN (' . $product_id_placeholders . ')'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The dynamic fragment is only the counted product-ID placeholder list; every identifier and product ID is prepared below.
            $prepare_args = array_merge(array($qty_col, $rev_col, $lookup_table), $product_ids);
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The aggregate contains only prepared allowlisted column identifiers, the Woo lookup-table identifier, and integer product IDs.
            $prepared = $wpdb->prepare($sql, $prepare_args);
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket statistics require request-fresh Woo lookup aggregates using the detected compatible columns; no WooCommerce API preserves this exact result contract.
            $row = $wpdb->get_row($prepared, ARRAY_A);

            $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
            $rev = isset($row['revenue']) ? (float) $row['revenue'] : 0.0;

            return array(
                'provider'         => 'woo_analytics',
                'qty_sold'         => max(0, $qty),
                'revenue'          => max(0.0, $rev),
                'revenue_label'    => $label,
                'currency'         => $currency,
                'computed_at_gmt'  => time(),
            );
        }
    }

    // Fallback: total_sales + product price (approximate revenue).
    $qty = 0;
    $rev = 0.0;
    foreach ($product_ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p) {
            continue;
        }
        $sold = (int) $p->get_total_sales();
        $price = (float) $p->get_price();
        $qty += max(0, $sold);
        $rev += max(0.0, ($sold * $price));
    }

    return array(
        'provider'         => 'woo_product_totals',
        'qty_sold'         => max(0, $qty),
        'revenue'          => max(0.0, $rev),
        'revenue_label'    => 'Estimated revenue (price × sold; excludes discounts, taxes, refunds)',
        'currency'         => $currency,
        'computed_at_gmt'  => time(),
    );
}

function bvmgr_ticketing_format_money(float $amount, string $currency = ''): string
{
    if (function_exists('wc_price')) {
        return (string) wc_price($amount);
    }
    $amount = number_format($amount, 2, '.', ',');
    if ($currency !== '') {
        return $currency . ' ' . $amount;
    }
    return '$' . $amount;
}

/**
 * Admin: enqueue assets on Event Plan edit screens only.
 */
function bvmgr_ticketing_admin_enqueue_assets($hook): void
{
    if (!is_admin()) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || empty($screen->post_type) || $screen->post_type !== 'vms_event_plan') {
        return;
    }

    // Only on post editor screens.
    if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
        return;
    }

    $ver = function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '');
    $handle = 'vms-admin-ticketing';
    $src = defined('BVMGR_PLUGIN_URL') ? (BVMGR_PLUGIN_URL . 'assets/admin-ticketing.js') : '';
    if ($src === '') {
        return;
    }
    wp_enqueue_script($handle, $src, array(), $ver, true);

    $verification_programs = array();
    if (function_exists('bvmgr_ticketing_verification_programs')) {
        foreach ((array) bvmgr_ticketing_verification_programs() as $program_key => $program_label) {
            $program_key = sanitize_key((string) $program_key);
            if ($program_key === '') {
                continue;
            }
            $verification_programs[$program_key] = sanitize_text_field((string) $program_label);
        }
    }

    $plan_id = 0;
    $plan_id = bvmgr_ticketing_admin_query_absint('post');
    if ($plan_id <= 0) {
        // post-new.php can still have a real post ID (auto-draft). Prefer the global post when available.
        global $post;
        if ($post && isset($post->ID)) {
            $plan_id = absint($post->ID);
        }
    }

    wp_add_inline_script(
        $handle,
        'window.VMS_TICKETING = ' . wp_json_encode(array(
            'planId' => $plan_id,
            'nonce'  => wp_create_nonce('vms_ticketing_nonce'),
            'ticketUiOverridesNonce' => wp_create_nonce('vms_event_plan_ticket_ui_overrides_save'),
            'verificationPrograms' => $verification_programs,
            // Used by admin-ticketing.js to navigate from post-new.php (auto-draft) to post.php for the same plan.
            'editUrlBase' => admin_url('post.php?post='),
        )) . ';',
        'before'
    );
}
add_action('admin_enqueue_scripts', 'bvmgr_ticketing_admin_enqueue_assets');


/**
 * Helper: return legacy/import identifiers (if any) for a TEC event.
 * These are non-canonical IDs that may exist on imported events (EA/ICS/etc).
 *
 * Returned format:
 * - array(
 *     array('key' => 'meta_key', 'label' => 'Human label', 'value' => '…'),
 *     …
 *   )
 */
function bvmgr_ticketing_get_tec_legacy_identifiers(int $tec_event_id): array
{
    $out = array();

    if ($tec_event_id <= 0) {
        return $out;
    }

    $candidates = array(
        '_EventOriginID'               => 'Origin ID',
        '_EventUID'                    => 'Event UID',
        '_tribe_aggregator_uid'        => 'Aggregator UID',
        '_tribe_aggregator_record_uid' => 'Aggregator Record UID',
        '_tribe_aggregator_record_id'  => 'Aggregator Record ID',
    );

    foreach ($candidates as $key => $label) {
        $val = get_post_meta($tec_event_id, $key, true);

        if (is_array($val) || is_object($val)) {
            continue;
        }

        $val = trim((string) $val);
        if ($val === '') {
            continue;
        }

        $out[] = array(
            'key'   => (string) $key,
            'label' => (string) $label,
            'value' => $val,
        );
    }
    // Extra heuristic: if the event title contains a legacy-looking "ID# 12345", expose it for clarity.
    $title = (string) get_the_title($tec_event_id);
    if ($title !== '' && preg_match('/\bID#\s*([0-9]{5,})\b/i', $title, $m)) {
        $candidate = trim((string) ($m[1] ?? ''));
        if ($candidate !== '') {
            $dup = false;
            foreach ($out as $row) {
                if (is_array($row) && isset($row['value']) && (string) $row['value'] === $candidate) {
                    $dup = true;
                    break;
                }
            }
            if (!$dup) {
                $out[] = array(
                    'key'   => 'title_id',
                    'label' => 'Legacy ID',
                    'value' => $candidate,
                );
            }
        }
    }



    return $out;
}

/**
 * AJAX: search TEC events by title.
 */
function bvmgr_ticketing_ajax_search_tec_events(): void
{
    if (!check_ajax_referer('vms_ticketing_nonce', 'nonce', false)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    if (!current_user_can('edit_posts')) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    if (!bvmgr_ticketing_is_tec_active()) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'tec_inactive'), 400);
    }

    $q = bvmgr_request_read_text_field($_POST, 'q');
    $q = trim($q);
    if (strlen($q) < 2) {
        bvmgr_ticketing_ajax_send_success(array('items' => array()));
    }

    global $wpdb;

    $numeric_id = 0;
    if ($q !== '' && ctype_digit($q)) {
        $numeric_id = absint($q);
    }

    $like = '%' . $wpdb->esc_like($q) . '%';

    if ($numeric_id > 0) {
        $sql = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft') AND (ID = %d OR post_title LIKE %s) ORDER BY post_date DESC LIMIT 15",
            'tribe_events',
            $numeric_id,
            $like
        );
    } else {
        $sql = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft') AND post_title LIKE %s ORDER BY post_date DESC LIMIT 15",
            'tribe_events',
            $like
        );
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This authenticated AJAX search executes one of the two immediately prepared catalog queries and must return current TEC event matches.
    $ids = $wpdb->get_col($sql);

    $items = array();
    foreach (($ids ?? array()) as $id) {
        $id = absint($id);
        if ($id <= 0) {
            continue;
        }

        $start = (string) get_post_meta($id, '_EventStartDate', true);
        $items[] = array(
            'id'        => $id, // backward-compat
            'wp_id'     => $id,
            'legacy'    => function_exists('bvmgr_ticketing_get_tec_legacy_identifiers') ? bvmgr_ticketing_get_tec_legacy_identifiers($id) : array(),
            'title'     => (string) get_the_title($id),
            'start'     => $start,
            'permalink' => (string) get_permalink($id),
        );
    }

    bvmgr_ticketing_ajax_send_success(array('items' => $items));
}
add_action('wp_ajax_vms_ticketing_search_tec_events', 'bvmgr_ticketing_ajax_search_tec_events');

/**
 * AJAX: search Woo products by title.
 */
function bvmgr_ticketing_ajax_search_products(): void
{
    if (!check_ajax_referer('vms_ticketing_nonce', 'nonce', false)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    if (!bvmgr_ticketing_can_search_products()) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    if (!bvmgr_ticketing_is_woo_active()) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'woo_inactive'), 400);
    }

    $q = bvmgr_request_read_text_field($_POST, 'q');
    $q = trim($q);
    if (strlen($q) < 2) {
        bvmgr_ticketing_ajax_send_success(array('items' => array()));
    }

    $query = new WP_Query(array(
        'post_type'      => 'product',
        'post_status'    => array('publish', 'draft', 'private'),
        's'              => $q,
        'posts_per_page' => 15,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ));

    $items = array();
    foreach (($query->posts ?? array()) as $pid) {
        $pid = absint($pid);
        if ($pid <= 0) {
            continue;
        }

        $p = wc_get_product($pid);
        if (!$p) {
            continue;
        }

        $items[] = array(
            'id'     => $pid,
            'title'  => (string) $p->get_name(),
            'price'  => function_exists('wc_price') ? (string) wc_price((float) $p->get_price()) : '',
            'status' => (string) get_post_status($pid),
            'edit'   => (string) get_edit_post_link($pid, 'raw'),
        );
    }

    bvmgr_ticketing_ajax_send_success(array('items' => $items));
}
add_action('wp_ajax_vms_ticketing_search_products', 'bvmgr_ticketing_ajax_search_products');

/**
 * Helper: get manual product IDs attached to a plan.
 */
function bvmgr_ticketing_get_manual_product_ids(int $plan_id): array
{
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array();
    }

    $k_manual = bvmgr_ticketing_meta_key('ticket_manual_product_ids', '_vms_ticket_manual_product_ids_v1');
    $pids = get_post_meta($plan_id, $k_manual, true);
    if (!is_array($pids)) {
        $pids = array();
    }

    return array_values(array_unique(array_filter(array_map('absint', $pids))));
}

function bvmgr_ticketing_set_manual_product_ids(int $plan_id, array $pids): void
{
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return;
    }

    $k_manual = bvmgr_ticketing_meta_key('ticket_manual_product_ids', '_vms_ticket_manual_product_ids_v1');
    $pids = array_values(array_unique(array_filter(array_map('absint', $pids))));

    // Keep this list small to avoid accidental bloat.
    if (count($pids) > 25) {
        $pids = array_slice($pids, 0, 25);
    }

    update_post_meta($plan_id, $k_manual, $pids);
}

/**
 * AJAX: attach a Woo product to an Event Plan for ticket stats.
 */
function bvmgr_ticketing_ajax_attach_product(): void
{
    if (!check_ajax_referer('vms_ticketing_nonce', 'nonce', false)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    $pid     = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

    if (!bvmgr_ticketing_can_manage_plan($plan_id)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'forbidden'), 403);
    }
    if (!bvmgr_ticketing_is_woo_active()) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'woo_inactive'), 400);
    }
    if ($pid <= 0 || get_post_type($pid) !== 'product') {
        bvmgr_ticketing_ajax_send_error(array('message' => 'invalid_product'), 400);
    }

    $pids = bvmgr_ticketing_get_manual_product_ids($plan_id);
    $pids[] = $pid;
    $pids = array_values(array_unique(array_filter(array_map('absint', $pids))));

    bvmgr_ticketing_set_manual_product_ids($plan_id, $pids);

    bvmgr_ticketing_ajax_send_success(array('manual_product_ids' => $pids));
}
add_action('wp_ajax_vms_ticketing_attach_product', 'bvmgr_ticketing_ajax_attach_product');

/**
 * AJAX: detach a Woo product from an Event Plan.
 */
function bvmgr_ticketing_ajax_detach_product(): void
{
    if (!check_ajax_referer('vms_ticketing_nonce', 'nonce', false)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    $pid     = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

    if (!bvmgr_ticketing_can_manage_plan($plan_id)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'forbidden'), 403);
    }
    if ($pid <= 0) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'invalid_product'), 400);
    }

    $pids = bvmgr_ticketing_get_manual_product_ids($plan_id);
    $pids = array_values(array_diff($pids, array($pid)));

    bvmgr_ticketing_set_manual_product_ids($plan_id, $pids);

    bvmgr_ticketing_ajax_send_success(array('manual_product_ids' => $pids));
}
add_action('wp_ajax_vms_ticketing_detach_product', 'bvmgr_ticketing_ajax_detach_product');


/**
 * AJAX: link (or re-link) a TEC event to an Event Plan.
 */
function bvmgr_ticketing_ajax_link_tec_event(): void
{
    if (!check_ajax_referer('vms_ticketing_nonce', 'nonce', false)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    $tec_id  = isset($_POST['tec_event_id']) ? absint($_POST['tec_event_id']) : 0;

    if (!bvmgr_ticketing_can_manage_plan($plan_id)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'forbidden'), 403);
    }
    if (!bvmgr_ticketing_is_tec_active()) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'tec_inactive'), 400);
    }
    if ($tec_id <= 0) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'missing_tec_id'), 400);
    }

    $tec_post = get_post($tec_id);
    if (!$tec_post || $tec_post->post_type !== 'tribe_events') {
        bvmgr_ticketing_ajax_send_error(array('message' => 'invalid_tec_event'), 400);
    }

    $k_id   = bvmgr_ticketing_meta_key('tec_event_id', '_vms_tec_event_id');
    $k_url  = bvmgr_ticketing_meta_key('tec_event_url', '_vms_tec_event_url');
    $k_pids = bvmgr_ticketing_meta_key('ticket_product_ids', '_vms_ticket_product_ids_v1');
    $k_stat = bvmgr_ticketing_meta_key('ticket_stats', '_vms_ticket_stats_v1');

    update_post_meta($plan_id, $k_id, $tec_id);
    $permalink = get_permalink($tec_id);
    if ($permalink) {
        update_post_meta($plan_id, $k_url, esc_url_raw($permalink));
    }

    // Verify the save took effect (helps avoid silent failures and confusing reloads).
    $saved_id = (int) get_post_meta($plan_id, $k_id, true);
    if ($saved_id !== $tec_id) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'save_failed'), 500);
    }

    // Clear cached ticket stats. (Operator must explicitly refresh.)
    delete_post_meta($plan_id, $k_pids);
    delete_post_meta($plan_id, $k_stat);

    bvmgr_ticketing_ajax_send_success(array(
        'linked' => true,
        'plan_id' => $plan_id,
        'tec_event_id' => $tec_id,
        'tec_event_title' => (string) get_the_title($tec_id),
        'tec_event_url' => (string) get_post_meta($plan_id, $k_url, true),
    ));
}
add_action('wp_ajax_vms_ticketing_link_tec_event', 'bvmgr_ticketing_ajax_link_tec_event');

/**
 * AJAX: unlink the TEC event from an Event Plan.
 */
function bvmgr_ticketing_ajax_unlink_tec_event(): void
{
    if (!check_ajax_referer('vms_ticketing_nonce', 'nonce', false)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    if (!bvmgr_ticketing_can_manage_plan($plan_id)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    $k_id   = bvmgr_ticketing_meta_key('tec_event_id', '_vms_tec_event_id');
    $k_url  = bvmgr_ticketing_meta_key('tec_event_url', '_vms_tec_event_url');
    $k_pids = bvmgr_ticketing_meta_key('ticket_product_ids', '_vms_ticket_product_ids_v1');
    $k_stat = bvmgr_ticketing_meta_key('ticket_stats', '_vms_ticket_stats_v1');

    delete_post_meta($plan_id, $k_id);
    delete_post_meta($plan_id, $k_url);
    delete_post_meta($plan_id, $k_pids);
    delete_post_meta($plan_id, $k_stat);

    $still = (int) get_post_meta($plan_id, $k_id, true);
    if ($still > 0) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'unlink_failed'), 500);
    }

    bvmgr_ticketing_ajax_send_success(array('unlinked' => true, 'plan_id' => $plan_id));
}
add_action('wp_ajax_vms_ticketing_unlink_tec_event', 'bvmgr_ticketing_ajax_unlink_tec_event');

/**
 * AJAX: refresh ticket stats and store on the Event Plan.
 */
function bvmgr_ticketing_ajax_refresh_stats(): void
{
    if (!check_ajax_referer('vms_ticketing_nonce', 'nonce', false)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    if (!bvmgr_ticketing_can_manage_plan($plan_id)) {
        bvmgr_ticketing_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    $k_id   = bvmgr_ticketing_meta_key('tec_event_id', '_vms_tec_event_id');
    $k_pids = bvmgr_ticketing_meta_key('ticket_product_ids', '_vms_ticket_product_ids_v1');
    $k_stat = bvmgr_ticketing_meta_key('ticket_stats', '_vms_ticket_stats_v1');

    $tec_id = (int) get_post_meta($plan_id, $k_id, true);

    $detected = array();
    if ($tec_id > 0) {
        $detected = bvmgr_ticketing_get_ticket_product_ids_for_tec_event($tec_id);
    }

    $manual = bvmgr_ticketing_get_manual_product_ids($plan_id);

    $pids = array_values(array_unique(array_filter(array_map('absint', array_merge($detected, $manual)))));
    if (empty($pids)) {
        bvmgr_ticketing_ajax_send_error(array(
            'message' => 'no_ticket_sources',
            'detail'  => 'No ticket products were detected and no Woo products are attached.',
        ), 400);
    }

    $stats = bvmgr_ticketing_compute_stats($pids);
    $stats['detected_product_ids'] = $detected;
    $stats['manual_product_ids']   = $manual;

    update_post_meta($plan_id, $k_pids, $pids);
    update_post_meta($plan_id, $k_stat, $stats);

    bvmgr_ticketing_ajax_send_success(array(
        'ticket_product_ids' => $pids,
        'stats'              => $stats,
    ));
}
add_action('wp_ajax_vms_ticketing_refresh_stats', 'bvmgr_ticketing_ajax_refresh_stats');
