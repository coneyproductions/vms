<?php
/**
 * Run with `wp eval-file` in a fresh, disposable WordPress database only.
 * Uses real WordPress nonce, capability, hook and metadata APIs; no BVM activation.
 */
if (!defined('ABSPATH') || getenv('BVM_P1A_DISPOSABLE') !== '1') {
    throw new RuntimeException('A guarded disposable WordPress fixture is required.');
}

final class BVMGR_Tax_Test_Rejection extends RuntimeException {}
final class BVMGR_Tax_Test_Redirect extends RuntimeException {}
$root = dirname(__DIR__);
require_once $root . '/includes/core/prefix-b4-compat.php';
require_once $root . '/includes/admin/vendors/tax-bulk-actions.php';
require_once $root . '/includes/admin/vendors/tax-metabox.php';

// Match the shipped vendor CPT's existing post-based capability model.
register_post_type(BVMGR_CPT_VENDOR, array('public' => false, 'show_ui' => true, 'capability_type' => 'post'));
add_filter('wp_die_handler', static function () {
    return static function ($message) { throw new BVMGR_Tax_Test_Rejection((string) $message); };
});
add_filter('wp_redirect', static function ($location) {
    throw new BVMGR_Tax_Test_Redirect((string) $location);
}, 0);
set_error_handler(static function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
    return false;
});

$editor = wp_insert_user(array('user_login' => 'tax_editor', 'user_pass' => 'disposable', 'role' => 'editor'));
$author = wp_insert_user(array('user_login' => 'tax_author', 'user_pass' => 'disposable', 'role' => 'author'));
$subscriber = wp_insert_user(array('user_login' => 'tax_subscriber', 'user_pass' => 'disposable', 'role' => 'subscriber'));
foreach (array($editor, $author, $subscriber) as $id) {
    if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
}
$own = wp_insert_post(array('post_type' => BVMGR_CPT_VENDOR, 'post_status' => 'draft', 'post_title' => 'Owned vendor', 'post_author' => $author));
$other = wp_insert_post(array('post_type' => BVMGR_CPT_VENDOR, 'post_status' => 'draft', 'post_title' => 'Other vendor', 'post_author' => $editor));
$page = wp_insert_post(array('post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Not a vendor', 'post_author' => $editor));
$keys = array();
foreach (array('tax_profile_completed_at', 'w9_attested_at', 'w9_provider', 'w9_received_date', 'w9_upload_id') as $name) $keys[$name] = bvmgr_meta_key('vendor', $name);
$checks = 0;
$assert = static function ($condition, $message) use (&$checks) {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$seed = static function () use ($own, $other, $page, $keys) {
    foreach (array($own, $other, $page) as $id) {
        foreach ($keys as $name => $key) update_post_meta($id, $key, $name === 'w9_provider' ? 'tax1099_email' : 'original');
        update_post_meta($id, '_tax_test_unrelated', 'retain');
    }
};
$snapshot = static function () use ($own, $other, $page) {
    return array_map(static function ($id) { return get_post_meta($id); }, array($own, $other, $page));
};
$writes = array();
foreach (array('added_post_meta', 'updated_post_meta', 'deleted_post_meta') as $hook) {
    add_action($hook, static function ($meta_id, $post_id, $key) use (&$writes, $keys) {
        if (in_array($key, $keys, true)) $writes[] = array($post_id, $key);
    }, 10, 3);
}
$prepare = static function ($user, $nonce_action = 'bulk-posts') use ($seed, &$writes) {
    wp_set_current_user($user);
    $_GET = $_POST = array();
    $_REQUEST = array('_wpnonce' => wp_create_nonce($nonce_action));
    $seed();
    $writes = array();
};
$bulk = static function ($action, $ids) {
    return apply_filters('handle_bulk_actions-edit-' . BVMGR_CPT_VENDOR, '/wp-admin/edit.php?post_type=vms_vendor', $action, $ids);
};
$reject = static function ($callback, $label) use ($assert, $snapshot, &$writes) {
    $before = $snapshot();
    $denied = false;
    try { $callback(); } catch (BVMGR_Tax_Test_Rejection $e) { $denied = true; }
    $assert($denied, $label . ': must reject');
    $assert($writes === array(), $label . ': must perform zero tax writes');
    $assert($snapshot() === $before, $label . ': all target state unchanged');
};

foreach (array('vms_tax_mark_complete', 'vms_tax_mark_incomplete') as $action) {
    $prepare($editor);
    $url = $bulk($action, array($own, (string) $other));
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $assert(($query['vms_tax_bulk_done'] ?? '') === '2' && ($query['vms_tax_bulk_action'] ?? '') === $action, 'Authorized redirect/count contract');
    foreach (array($own, $other) as $id) {
        $done = get_post_meta($id, $keys['tax_profile_completed_at'], true);
        $assert($action === 'vms_tax_mark_complete' ? (int) $done > 0 : $done === '', 'Authorized bulk mutation');
        $assert(get_post_meta($id, $keys['w9_received_date'], true) === 'original' && get_post_meta($id, $keys['w9_upload_id'], true) === 'original', 'Bulk receipt/upload untouched');
        foreach (array('w9_attested_at', 'w9_provider') as $name) {
            $value = get_post_meta($id, $keys[$name], true);
            $assert($action === 'vms_tax_mark_complete' ? $value !== '' : $value === '', 'Attestation/provider behavior preserved');
        }
    }
    $prepare($author);
    $assert(!current_user_can('manage_options') && current_user_can('edit_post', $own) && !current_user_can('edit_post', $other), 'Real object capability model');
    $bulk($action, array($own));
    $assert(count($writes) > 0, 'Authorized nonadministrator succeeds');
    foreach (array(array($subscriber, array($own)), array($author, array($other)), array($author, array($own, $other)), array($editor, array($own, $page))) as [$user, $ids]) {
        $prepare($user);
        $reject(static function () use ($bulk, $action, $ids) { $bulk($action, $ids); }, 'Denied user/object/mixed batch');
    }
    foreach (array(null, '', 'invalid', array('bad')) as $nonce) {
        $prepare($editor);
        $_REQUEST = $nonce === null ? array() : array('_wpnonce' => $nonce);
        $reject(static function () use ($bulk, $action, $own) { $bulk($action, array($own)); }, 'Missing/invalid/malformed nonce');
    }
    foreach (array(0, -1, 'bogus', $own . 'junk', array($own), true, (float) $own, null, PHP_INT_MAX, str_repeat('9', 40)) as $bad_id) {
        $prepare($editor);
        $reject(static function () use ($bulk, $action, $own, $bad_id) { $bulk($action, array($own, $bad_id)); }, 'Invalid target after valid target');
    }
    $prepare($editor);
    $reject(static function () use ($bulk, $action, $own) { $bulk($action, (string) $own); }, 'Nonarray selection');
}
$prepare($editor);
$_REQUEST = array();
$before = $snapshot();
$assert($bulk('unrelated_action', array($own)) === '/wp-admin/edit.php?post_type=vms_vendor' && $snapshot() === $before && !$writes, 'Unrelated bulk action untouched');

foreach (array('complete', 'incomplete') as $mode) {
    $hook = 'admin_post_vms_vendor_tax_mark_' . $mode;
    foreach (array('bvmgr', 'vms') as $prefix) {
        $prepare($author, $prefix . '_vendor_tax_mark_' . $mode . '_' . $own);
        $_GET['vendor_id'] = (string) $own;
        try { do_action($hook); throw new RuntimeException('Expected redirect'); }
        catch (BVMGR_Tax_Test_Redirect $e) {
            $assert(strpos($e->getMessage(), 'vms_tax_notice=' . $mode) !== false, 'Single-item redirect retained');
        }
        $assert($writes !== array(), 'Authorized single-item canonical/legacy nonce succeeds');
        $assert(get_post_meta($own, $keys['w9_received_date'], true) === 'original', 'Single-item existing receipt retained');
    }
    foreach (array(array($subscriber, $own), array($author, $other), array($editor, $page), array($editor, array($own)), array($editor, $own . 'junk'), array($editor, 0), array($editor, null)) as [$user, $id]) {
        $prepare($user, 'bvmgr_vendor_tax_mark_' . $mode . '_' . (is_scalar($id) ? (int) $id : 0));
        $_GET['vendor_id'] = $id;
        $reject(static function () use ($hook) { do_action($hook); }, 'Single-item invalid/unauthorized target');
    }
    foreach (array(null, 'invalid') as $nonce) {
        $prepare($author);
        $_GET['vendor_id'] = (string) $own;
        $_REQUEST = $nonce === null ? array() : array('_wpnonce' => $nonce);
        $reject(static function () use ($hook) { do_action($hook); }, 'Single-item missing/invalid nonce');
    }
}
restore_error_handler();
echo "PASS vendor tax authorization: $checks assertions; real WordPress users, capabilities, nonces, hooks and metadata.\n";
