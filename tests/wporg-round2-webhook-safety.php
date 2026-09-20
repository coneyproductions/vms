<?php
/** Run with wp eval-file only in a fresh guarded disposable WordPress database. */
if (!defined('ABSPATH') || getenv('BVM_P1B_DISPOSABLE') !== '1') {
    throw new RuntimeException('Disposable P1-B WordPress fixture required.');
}
$root = getenv('BVM_P1B_SOURCE') ?: dirname(__DIR__);
global $wpdb;
require_once $root . '/includes/core/prefix-b4-compat.php';
require_once $root . '/includes/social-share/load.php';
require_once $root . '/includes/social-share/admin.php';
require_once $root . '/includes/social-share/event-plan-panel.php';
bvmgr_social_db_maybe_install();
register_post_type('vms_event_plan', array('public' => false, 'show_ui' => true, 'capability_type' => 'post'));
register_post_type('vms_venue', array('public' => false, 'show_ui' => true, 'capability_type' => 'post'));

final class BVMGR_Webhook_Test_Denied extends RuntimeException {}
final class BVMGR_Webhook_Test_Redirect extends RuntimeException {}
add_filter('wp_die_handler', static function () {
    return static function ($message) { throw new BVMGR_Webhook_Test_Denied(is_wp_error($message) ? $message->get_error_message() : (string) $message); };
});
add_filter('wp_redirect', static function ($url) { throw new BVMGR_Webhook_Test_Redirect($url); }, 0);
set_error_handler(static function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
    return false;
});
$checks = 0;
$assert = static function ($ok, $label) use (&$checks) {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$users = array();
foreach (array('author', 'editor', 'subscriber', 'administrator') as $role) {
    $users[$role] = wp_insert_user(array('user_login' => 'webhook_' . $role, 'user_pass' => 'disposable', 'role' => $role));
    $assert(!is_wp_error($users[$role]), 'Create native user');
}
$delegate = wp_insert_user(array('user_login' => 'webhook_delegate', 'user_pass' => 'disposable', 'role' => 'subscriber'));
(new WP_User($delegate))->add_cap(bvmgr_social_manage_capability());
$own = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'draft', 'post_title' => 'Owned event', 'post_author' => $users['author']));
$other = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'draft', 'post_title' => 'Other event', 'post_author' => $users['editor']));
$page = wp_insert_post(array('post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Wrong type', 'post_author' => $users['editor']));
$venue = wp_insert_post(array('post_type' => 'vms_venue', 'post_status' => 'draft', 'post_title' => 'Venue'));
update_post_meta($own, '_vms_venue_id', $venue);
update_post_meta($other, '_vms_venue_id', $venue);
$safe_url = 'https://8.8.8.8/webhook?token=disposable-token';
$account = bvmgr_social_account_save(array('platform' => 'webhook', 'label' => 'Fixture', 'auth_state' => 'connected', 'token_json' => array('webhook_url' => $safe_url, 'signing_secret' => 'disposable-secret')));
$map = bvmgr_social_venue_map_save(array('venue_id' => $venue, 'platform' => 'webhook', 'account_id' => $account, 'destination_id' => 'configured', 'is_enabled' => 1));
$queue = bvmgr_social_queue_create(array('event_plan_id' => $other, 'platform' => 'webhook', 'status' => 'failed', 'payload_snapshot_json' => array('account_id' => $account)));
$own_queue = bvmgr_social_queue_create(array('event_plan_id' => $own, 'platform' => 'webhook', 'status' => 'failed', 'payload_snapshot_json' => array('account_id' => $account)));
$tables = array(bvmgr_social_table_accounts(), bvmgr_social_table_venue_map(), bvmgr_social_table_templates(), bvmgr_social_table_queue(), bvmgr_social_table_audit());
$snapshot = static function () use ($tables, $own, $other, $page) {
    global $wpdb;
    $state = array();
    foreach ($tables as $table) $state[$table] = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id', $table), ARRAY_A);
    $state['settings'] = bvmgr_social_get_settings();
    foreach (array($own, $other, $page) as $id) $state[$id] = get_post_meta($id);
    return $state;
};
$writes = array();
add_filter('query', static function ($sql) use (&$writes) {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql)) $writes[] = $sql;
    return $sql;
});
$prepare = static function ($user, $action, $post = array(), $prefix = 'bvmgr') use (&$writes) {
    wp_set_current_user($user);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = array();
    $_POST = $post;
    $_REQUEST = array('_wpnonce' => wp_create_nonce($prefix . '_social_' . $action));
    $writes = array();
};
$invoke = static function ($action) { do_action('admin_post_vms_social_' . $action); };
$reject = static function ($action, $label) use ($snapshot, $assert, $invoke, &$writes) {
    $before = $snapshot();
    $denied = false;
    try { $invoke($action); } catch (BVMGR_Webhook_Test_Denied $e) { $denied = true; }
    catch (BVMGR_Webhook_Test_Redirect $e) {}
    $assert($denied, $label . ': must reject');
    $assert($writes === array(), $label . ': zero SQL mutations');
    $assert($snapshot() === $before, $label . ': unchanged social and event state');
};
$success = static function ($action) use ($assert, $invoke) {
    $redirected = false;
    try { $invoke($action); } catch (BVMGR_Webhook_Test_Redirect $e) { $redirected = true; }
    $assert($redirected, $action . ': success redirect');
};
$config = array(
    'save_account' => array('platform' => 'webhook', 'label' => 'New webhook', 'webhook_url' => $safe_url, 'signing_secret' => 'disposable-secret'),
    'save_settings' => array('enabled' => '1', 'max_attempts' => '5'),
    'delete_account' => array('id' => (string) $account),
    'save_venue_map' => array('venue_id' => (string) $venue, 'platform' => 'webhook', 'account_id' => (string) $account, 'is_enabled' => '1'),
    'delete_venue_map' => array('id' => (string) $map),
    'run_queue_now' => array(),
);
foreach ($config as $action => $data) {
    foreach (array(0, $users['subscriber'], $users['author'], $users['editor']) as $user) {
        $prepare($user, $action, $data);
        $reject($action, 'Configuration unauthorized');
    }
    foreach (array(null, 'bad', array('bad')) as $nonce) {
        $prepare($delegate, $action, $data);
        $_REQUEST = $nonce === null ? array() : array('_wpnonce' => $nonce);
        $reject($action, 'Configuration nonce');
    }
    foreach (array('GET', 'PUT', 'DELETE') as $method) {
        $prepare($delegate, $action, $data); $_SERVER['REQUEST_METHOD'] = $method;
        $reject($action, 'Configuration method');
    }
    $prepare($delegate, $action, $data); $_POST['malformed'] = array('bad');
    $reject($action, 'Configuration shape');
}
$prepare($users['author'], 'save_account');
$assert(bvmgr_social_current_user_can_manage() && !bvmgr_social_current_user_can_configure(), 'Operator access preserved without configuration authority');
$assert(!isset(bvmgr_social_admin_tabs()['accounts']) && isset(bvmgr_social_admin_tabs()['queue']), 'Operator tabs retain workflow, omit configuration');
$prepare($delegate, 'save_account');
$assert(!current_user_can('manage_options') && bvmgr_social_current_user_can_configure(), 'Delegated existing custom capability accepted');
$prepare($users['administrator'], 'save_settings', $config['save_settings']); $success('save_settings');
foreach (array('bvmgr', 'vms') as $prefix) {
    $prepare($delegate, 'save_account', $config['save_account'], $prefix); $success('save_account');
}
$unsafe = array('', 'ftp://8.8.8.8/file', 'file:///etc/passwd', 'javascript:alert(1)', 'http://127.0.0.1/hook', 'http://localhost/hook', 'http://10.0.0.1/hook', 'http://172.16.0.1/hook', 'http://192.168.1.1/hook', 'http://169.254.169.254/latest', 'http://[::1]/hook', 'http://user:pass@8.8.8.8/hook', 'https://8.8.8.8:22/hook', 'http:///broken', array('bad'));
foreach ($unsafe as $url) {
    $data = $config['save_account']; $data['webhook_url'] = $url;
    $prepare($delegate, 'save_account', $data); $reject('save_account', 'Unsafe account destination: ' . wp_json_encode($url));
}
$data = $config['save_account']; $data['platform'] = 'unknown';
$prepare($delegate, 'save_account', $data); $reject('save_account', 'Unknown platform');
$event_data = array('event_plan_id' => (string) $own, 'platform' => 'webhook');
foreach (array('event_queue', 'queue_retry', 'queue_cancel') as $action) {
    $data = $action === 'event_queue' ? $event_data : array('queue_id' => (string) $own_queue, 'event_plan_id' => (string) $own);
    foreach (array(null, 'bad', array('bad')) as $nonce) {
        $prepare($users['author'], $action, $data); $_REQUEST = $nonce === null ? array() : array('_wpnonce' => $nonce);
        $reject($action, 'Event nonce');
    }
    $prepare($users['author'], $action, $data); $_SERVER['REQUEST_METHOD'] = 'GET'; $reject($action, 'Event method');
    foreach (array(0, $users['subscriber']) as $user) {
        $prepare($user, $action, $data); $reject($action, 'Event caller');
    }
    foreach (array('0', '-1', 'bad', $own . 'junk', array('bad'), str_repeat('9', 40)) as $id) {
        $bad = $data; $bad[$action === 'event_queue' ? 'event_plan_id' : 'queue_id'] = $id;
        $prepare($users['author'], $action, $bad); $reject($action, 'Event malformed ID');
    }
    $bad = $data;
    if ($action === 'event_queue') $bad['event_plan_id'] = (string) $other;
    else $bad['queue_id'] = (string) $queue; // Spoofed own event cannot authorize another row.
    $prepare($users['author'], $action, $bad); $reject($action, 'Foreign event or spoofed retry');
    $prepare($users['author'], $action, $data); $success($action);
}
$prepare($users['editor'], 'event_queue', array('event_plan_id' => (string) $page, 'platform' => 'webhook'));
$reject('event_queue', 'Non-event object');
$prepare($users['editor'], 'event_queue', array('event_plan_id' => (string) $other, 'platform' => 'webhook'));
$success('event_queue');

// Exercise the other authorized configuration paths without dispatching live traffic.
$prepare($delegate, 'save_venue_map', $config['save_venue_map']); $success('save_venue_map');
$prepare($delegate, 'delete_venue_map', $config['delete_venue_map']); $success('delete_venue_map');
$accounts = bvmgr_social_account_rows('webhook');
$prepare($delegate, 'delete_account', array('id' => (string) $accounts[0]['id'])); $success('delete_account');
$assert(bvmgr_social_account_get((int) $accounts[0]['id']) === null, 'Authorized account deletion');
$prepare($delegate, 'save_settings', array('enabled' => '1', 'kill_switch' => '1', 'max_attempts' => '5')); $success('save_settings');
$prepare($delegate, 'run_queue_now'); $success('run_queue_now');
$data = $config['save_account']; $data['signing_secret'] = array('bad');
$prepare($delegate, 'save_account', $data); $reject('save_account', 'Malformed signing secret');

// Substitute only the wire transport. Core HTTP safety and redirect handling still run.
final class BVMGR_Webhook_Test_Transport implements \WpOrg\Requests\Transport {
    public static $calls = array();
    public static $redirect = '';
    public function request($url, $headers = array(), $data = array(), $options = array()) {
        self::$calls[] = compact('url', 'headers', 'data', 'options');
        if (self::$redirect !== '' && count(self::$calls) === 1) return "HTTP/1.1 307 Temporary Redirect\r\nLocation: " . self::$redirect . "\r\nContent-Length: 0\r\n\r\n";
        return "HTTP/1.1 204 No Content\r\nContent-Length: 0\r\n\r\n";
    }
    public function request_multiple($requests, $options) { throw new RuntimeException('Unexpected batch transport'); }
    public static function test($capabilities = array()) { return true; }
}
$transports = new ReflectionProperty(\WpOrg\Requests\Requests::class, 'transports');
$transports->setAccessible(true);
$transports->setValue(null, array(BVMGR_Webhook_Test_Transport::class));
\WpOrg\Requests\Requests::$transport = array();
remove_all_filters('pre_http_request');
$provider = new BVMGR_Social_Provider_Webhook();
$set_url = static function ($url, $secret = 'disposable-secret') use ($account) {
    bvmgr_social_account_save(array('id' => $account, 'platform' => 'webhook', 'label' => 'Fixture', 'auth_state' => 'connected', 'token_json' => array('webhook_url' => $url, 'signing_secret' => $secret)));
    BVMGR_Webhook_Test_Transport::$calls = array();
    BVMGR_Webhook_Test_Transport::$redirect = '';
};
$payload = array('rendered_caption' => 'Test event', 'queue_id' => 123);
foreach ($unsafe as $url) {
    $set_url($url); $before = $snapshot(); $writes = array();
    $assert(!$provider->validate_connection($account)['ok'], 'Unsafe connection fails');
    $denied = false;
    try { $provider->publish($account, 'configured', $payload); } catch (RuntimeException $e) { $denied = true; }
    $assert($denied && !BVMGR_Webhook_Test_Transport::$calls, 'Unsafe destination: zero wire requests');
    $assert(!$writes && $snapshot() === $before, 'Unsafe delivery: zero database changes');
}
foreach (array('disposable-secret', '') as $secret) {
    $set_url($safe_url, $secret);
    $assert($provider->validate_connection($account)['ok'], 'Safe connection valid');
    $result = $provider->publish($account, 'configured', $payload);
    $call = BVMGR_Webhook_Test_Transport::$calls[0];
    $assert($result['http_code'] === 204 && $result['destination_id'] === 'configured' && strpos($result['platform_post_id'], 'webhook-') === 0, 'Existing publish result');
    $assert(count(BVMGR_Webhook_Test_Transport::$calls) === 1 && $call['url'] === $safe_url && $call['data'] === wp_json_encode($payload), 'Existing destination and JSON body');
    $assert($call['options']['timeout'] == 12 && $call['headers']['Content-Type'] === 'application/json', 'Existing timeout and content type');
    $assert($secret === '' ? !isset($call['headers']['X-VMS-Signature']) : hash_equals(hash_hmac('sha256', $call['data'], $secret), $call['headers']['X-VMS-Signature']), 'Optional HMAC contract');
}
foreach (array('http://127.0.0.1/redirect', 'http://169.254.169.254/redirect', 'ftp://8.8.8.8/redirect', 'https://1.1.1.1/redirect') as $target) {
    $set_url($safe_url); BVMGR_Webhook_Test_Transport::$redirect = $target;
    $failed = false;
    try { $provider->publish($account, 'configured', $payload); } catch (RuntimeException $e) { $failed = true; }
    $safe = $target === 'https://1.1.1.1/redirect';
    $assert($safe ? (!$failed && count(BVMGR_Webhook_Test_Transport::$calls) === 2) : ($failed && count(BVMGR_Webhook_Test_Transport::$calls) === 1), 'Core validates every redirect before wire request');
}
$assert($wpdb->last_error === '', 'No final database error');
restore_error_handler();
echo "PASS webhook safety: $checks assertions; native WordPress authorization, nonce, database, safe HTTP and redirect checks; fake wire only.\n";
