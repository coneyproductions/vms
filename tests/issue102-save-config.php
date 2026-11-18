<?php
declare(strict_types=1);

// Isolated CLI fixture. No WordPress bootstrap, database, Woo writes or HTTP.
require __DIR__ . '/issue102-function-loader.php';
$root = $argv[1] ?? dirname(__DIR__) . '/backstage-venue-manager';
$phase_b = $root . '/includes/integrations/ticketing-phase-b.php';
load_functions($phase_b, array_map(static fn($name) => 'bvmgr_ticketing_v2_' . $name, [
    'ajax_save_config', 'set_config', 'get_config', 'default_config', 'normalize_config', 'hash_config',
    'hash_config_for_sync', 'sort_for_hash', 'is_list_array', 'primary_ticket_index',
    'hydrate_legacy_primary_ticket_image', 'truthy', 'money_string',
    'config_from_storage', 'config_revision', 'get_admin_config_snapshot', 'check_editor_revision',
]));
load_functions($root . '/includes/integrations/ticketing-rules-v2.php', ['bvmgr_ticketing_v2_render_entitlements_block']);

function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_title($value) { return sanitize_key($value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return sanitize_text_field($value); }
function wp_kses_post($value) { return strip_tags($value, '<strong><em><p><br>'); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function __($value, ...$args) { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function esc_html__($value, ...$args) { return esc_html($value); }
function esc_attr__($value, ...$args) { return esc_html($value); }
function wp_json_encode($value) { return json_encode($value); }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function wp_cache_delete(...$args) {}
function bvmgr_ticketing_v2_sanitize_plain_text_label($value) { return sanitize_text_field($value); }
function bvmgr_ticketing_v2_get_plan_event_anchor_datetimes(...$args) { return []; }
function bvmgr_ticketing_v2_apply_relative_and_guarded_ticket_dates($row, ...$args) { return $row; }
function bvmgr_ticketing_v2_normalize_relative_days($value) { return ''; }
function bvmgr_ticketing_v2_normalize_allowed_programs($value) { return []; }
function bvmgr_ticketing_claims_allowed_grant_types() { return ['event_ticket_eligibility']; }
function bvmgr_ticketing_v2_hydrate_missing_sales_windows($config, ...$args) { return $config; }
function bvmgr_ticketing_v2_sync_legacy_primary_ticket_image_meta(...$args) { ++$GLOBALS['legacy_image_sync']; }
function bvmgr_entitlements_sync_plan_image_changes($plan, $before, $after) {
    $images = static fn($cfg) => array_column($cfg['entitlements'], 'image_id', 'entitlement_id');
    if ($images($before) != $images($after)) ++$GLOBALS['image_sync'];
    return [];
}
function bvmgr_nonce_action_for_request(...$args) { return 'synthetic'; }
function check_ajax_referer(...$args) { return true; }
function current_user_can($cap, ...$args) { return $cap === 'edit_post'; }
function bvmgr_request_read_absint($data, $key) { return absint($data[$key]); }
function bvmgr_ticketing_b_request_payload_value($data, $key, &$present, &$valid, &$bytes) {
    $present = $valid = true;
    return $data[$key];
}
function bvmgr_ticketing_v2_validate_config_payload($config) { return is_array($config); }
function bvmgr_ticketing_v2_k($kind) { return $kind; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$key] ?? ''; }
function update_post_meta($id, $key, $value, $previous = '') {
    ++$GLOBALS['writes'];
    if ($GLOBALS['write_mode'] === 'reject') return false;
    if ($GLOBALS['write_mode'] === 'pretend') return true;
    if ($GLOBALS['write_mode'] === 'competing') {
        $GLOBALS['meta'][$key] = $GLOBALS['competing'];
    }
    if ($previous !== '' && get_post_meta($id, $key, true) !== $previous) return false;
    $GLOBALS['meta'][$key] = wp_unslash($value);
    return true;
}
function bvmgr_ticketing_v2_ajax_send_json_success_fast($data, ...$args) { $GLOBALS['success'] = $data; }
function bvmgr_ticketing_v2_ajax_send_error($data, $status) { $GLOBALS['error'] = [$status, $data]; }

function WC() { return null; }
function bvmgr_tec_is_cancelled_event($id) { return false; }
function bvmgr_ticketing_v2_event_is_past(...$args) { return false; }
function bvmgr_ticketing_v2_has_purchasable_qualifying_native_ticket(...$args) { return true; }
function bvmgr_ticketing_v2_get_sync($plan) {
    return ['map' => ['entitlements' => ['first' => ['woo_product_id' => 71], 'second' => ['woo_product_id' => 72]]]];
}
function bvmgr_ticketing_v2_resolve_eligibility_for_product(...$args) { return []; }
function get_post_type($id) { return $id === 902 ? 'tribe_events' : 'product'; }
function bvmgr_ticketing_b_get_linked_tec_event_id($id) { return $GLOBALS['linked_event'] ?? 902; }
function wp_cache_post_id_gc($id, $all) { $GLOBALS['cache_purges'][] = [$id, $all]; }
function get_permalink($id) { return 'https://example.test/event/'; }
function add_query_arg($data, $url) { return $url . '?' . http_build_query($data); }
function wp_get_attachment_image_src(...$args) { return false; }
function wc_get_product($id) {
    return new class {
        function is_visible() { return true; }
        function is_purchasable() { return true; }
        function is_in_stock() { return true; }
        function get_name() { return 'Fixture rental'; }
        function get_price() { return 9; }
        function get_price_html() { return '$9.00'; }
        function get_max_purchase_quantity() { return 4; }
    };
}
function expect($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}
function save_config(array $config, int $return_config = 1, ?string $revision = null): void {
    $GLOBALS['success'] = $GLOBALS['error'] = null;
    $_POST = ['plan_id' => 901, 'config' => $config, 'return_config' => $return_config];
    $_POST['config_revision'] = $revision ?? bvmgr_ticketing_v2_get_admin_config_snapshot(901)['revision'];
    bvmgr_ticketing_v2_ajax_save_config();
}

$fixture = [
    'mode' => 'vms_managed',
    'entitlements' => [
        ['entitlement_id' => 'first', 'enabled' => 1, 'label' => 'Fixture rental A', 'capacity' => 2,
         'price' => '9.00', 'short_desc' => 'Original A', 'more_info' => 'Original details', 'selector_mode' => 'checkbox'],
        ['entitlement_id' => 'second', 'enabled' => 1, 'label' => 'Fixture rental B', 'capacity' => 2,
         'price' => '9.00', 'short_desc' => 'Original B'],
    ],
];
$baseline = bvmgr_ticketing_v2_normalize_config($fixture, 901);

foreach (['short_desc' => 'Updated short copy', 'more_info' => "<strong>Guest's notes</strong>\nUse path C:\\fixture.", 'selector_mode' => 'stepper'] as $field => $value) {
    $meta = ['config' => $baseline];
    $writes = $legacy_image_sync = $image_sync = 0;
    $cache_purges = [];
    $write_mode = 'normal';
    $changed = $baseline;
    $changed['entitlements'][0][$field] = $value;
    $sync_hash = bvmgr_ticketing_v2_hash_config_for_sync($baseline);
    save_config($changed);
    expect($success['config_changed'] === true && $writes === 1, "$field-only edit writes once");
    $reloaded = bvmgr_ticketing_v2_get_config(901);
    expect($reloaded['entitlements'][0][$field] === $value && $success['config']['entitlements'][0][$field] === $value, "$field survives reload and matches verified response");
    expect(bvmgr_ticketing_v2_hash_config_for_sync($reloaded) === $sync_hash, "$field does not invalidate the synchronization hash");
    expect($cache_purges === [[902, false]], "$field invalidates only the linked public event page");
    expect($legacy_image_sync === 0 && $image_sync === 0, "$field does not alter image or product state");
    $markup = bvmgr_ticketing_v2_render_entitlements_block(902, 901);
    $expected_html = match ($field) {
        'short_desc' => $value,
        'more_info' => "<strong>Guest's notes</strong>",
        default => 'vms-addon-controls--stepper',
    };
    expect(str_contains($markup, $expected_html), "$field is reflected in the public renderer");
    save_config($reloaded, 0);
    expect($success['config_changed'] === false && $writes === 1 && $success['config_omitted'] && $cache_purges === [[902, false]], "$field unchanged save skips storage and cache invalidation");
}

$meta = ['config' => $baseline];
$changed = $baseline;
$changed['entitlements'] = array_reverse($changed['entitlements']);
save_config($changed);
expect(array_column(bvmgr_ticketing_v2_get_config(901)['entitlements'], 'entitlement_id') === ['second','first'], 'entitlement order survives reload');
$markup = bvmgr_ticketing_v2_render_entitlements_block(902, 901);
$second_position = strpos($markup, 'data-vms-product-id="72"');
$first_position = strpos($markup, 'data-vms-product-id="71"');
expect($second_position !== false && $first_position !== false && $second_position < $first_position, 'public renderer follows committed order');
expect(bvmgr_ticketing_v2_hash_config_for_sync($meta['config']) === bvmgr_ticketing_v2_hash_config_for_sync($baseline), 'presentation reordering preserves Preview/Commit hash');

$meta = ['config' => $baseline];
$changed = $baseline;
$changed['entitlements'][0]['image_id'] = 15;
save_config($changed);
expect($success['config_changed'] && $meta['config']['entitlements'][0]['image_id'] === 15 && $image_sync === 1, 'image-only edit retains existing image change path');

$meta = ['config' => $baseline];
$changed['entitlements'][0]['capacity'] = 3;
save_config($changed);
expect($success['config_changed'] && $meta['config']['entitlements'][0]['capacity'] === 3, 'capacity updates still persist');
expect(bvmgr_ticketing_v2_hash_config_for_sync($meta['config']) !== bvmgr_ticketing_v2_hash_config_for_sync($baseline), 'catalog-relevant edit still invalidates prior Preview');

foreach (['reject','pretend'] as $write_mode) {
    $meta = ['config' => $baseline];
    $cache_purges = [];
    save_config($changed);
    expect($success === null && $error[1]['code'] === 'config_storage_unverified' && $meta['config'] === $baseline && !$cache_purges, "$write_mode produces a visible verification error without success");
}

$meta = ['config' => $baseline];
$competing = $baseline;
$competing['entitlements'][0]['more_info'] = 'Concurrent editor update';
$write_mode = 'competing';
save_config($changed);
expect($success === null && $error[1]['code'] === 'config_storage_unverified' &&
    $meta['config'] === $competing, 'concurrent different config is not overwritten or acknowledged');

$write_mode = 'normal';
$legacy = $fixture;
unset($legacy['entitlements'][0]['selector_mode']);
$meta = ['config' => $legacy];
$writes = 0;
save_config(bvmgr_ticketing_v2_get_config(901));
expect($success['config_changed'] === false && $writes === 0, 'normalized equivalent legacy config stays on the no-op path');

$meta = [];
$writes = 0;
save_config($baseline);
expect($success['config_changed'] && !$success['had_saved_config'] && $writes === 1 &&
    bvmgr_ticketing_v2_get_config(901) === $baseline, 'first save is written and verified');

$meta = ['config' => $baseline];
$linked_event = 71;
$cache_purges = [];
save_config($changed);
expect($success['config_changed'] === true && !$cache_purges, 'non-event linked ID is not used for cache invalidation');


$meta = ['config' => $baseline];
$writes = $legacy_image_sync = $image_sync = 0;
$cache_purges = [];
$linked_event = 902;
$editor_a = bvmgr_ticketing_v2_get_admin_config_snapshot(901);
$editor_b = bvmgr_ticketing_v2_get_admin_config_snapshot(901);
expect($editor_a === $editor_b, 'both editors open the same persisted revision');

$editor_b['config']['entitlements'][0]['short_desc'] = 'Saved by editor B';
save_config($editor_b['config'], 0, $editor_b['revision']);
$new_revision = $success['config_revision'];
expect($success['config_changed'] && $success['config_omitted'] && $new_revision !== $editor_b['revision'] &&
    $success['config_hash'] === bvmgr_ticketing_v2_hash_config_for_sync($baseline),
    'editor B receives a new storage revision even when the sync hash is unchanged');

$editor_a['config']['entitlements'][0]['more_info'] = 'Unsaved editor A notes';
save_config($editor_a['config'], 0, $editor_a['revision']);
expect($success === null && $error[0] === 409 && $error[1]['code'] === 'config_revision_conflict',
    'stale editor A receives an explicit conflict');
expect($meta['config'] === $editor_b['config'] && $writes === 1 && $cache_purges === [[902, false]] &&
    $legacy_image_sync === 0 && $image_sync === 0,
    'stale editor A causes no metadata write, image update or additional cache purge');

save_config($editor_b['config'], 0, $editor_b['revision']);
expect($success === null && $error[0] === 409 && $writes === 1,
    'an outdated revision cannot pass through the unchanged-save path');

save_config($editor_b['config'], 0, $new_revision);
expect($success['config_changed'] === false && $success['config_revision'] === $new_revision &&
    $writes === 1 && $cache_purges === [[902, false]], 'a current unchanged save reuses its revision without writing or purging');

unset($_POST['config_revision']);
$GLOBALS['success'] = $GLOBALS['error'] = null;
bvmgr_ticketing_v2_ajax_save_config();
expect($success === null && $error[0] === 409 && $writes === 1,
    'missing editor revision fails closed without writing');

$editor_a = bvmgr_ticketing_v2_get_admin_config_snapshot(901);
$editor_a['config']['entitlements'][0]['more_info'] = 'Reapplied editor A notes';
save_config($editor_a['config'], 0, $editor_a['revision']);
expect($success['config_changed'] && $meta['config'] === $editor_a['config'] && $writes === 2,
    'reloading the latest revision allows an intentional edit while preserving editor B notes');
