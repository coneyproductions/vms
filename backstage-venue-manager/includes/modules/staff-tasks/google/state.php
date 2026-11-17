<?php
/** Google projection state. No schema, scheduling or state writes during bootstrap/reads. */
defined('ABSPATH') || exit;

function bvmgr_google_state(): array
{
    global $wpdb;
    $json = $wpdb->get_var($wpdb->prepare('SELECT option_value FROM %i WHERE option_name=%s', $wpdb->options, 'bvmgr_tasks_google_v1'));
    if ($json === null) return array('version'=>1, 'connections'=>array(), 'mirrors'=>array());
    $state = json_decode($json, true);
    if (!is_array($state) || ($state['version'] ?? 0) !== 1) throw new RuntimeException('google_state_invalid');
    return $state;
}

/** Dedicated no-reconnect connection and connection-scoped lock; never a task transaction. */
function bvmgr_google_exclusive(callable $operation)
{
    global $wpdb;
    if (!empty($GLOBALS['bvmgr_google_lock']) || !empty($GLOBALS['bvmgr_tasks_transaction']) || bvmgr_staffing_transaction_active() || get_class($wpdb) !== 'wpdb') return new WP_Error('google_busy', 'Google synchronization unavailable in this context.');
    $original = $wpdb;
    $reader = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $reader->set_prefix($original->prefix);
    $wpdb = new BVMGR_Staffing_Transaction_DB($reader);
    $lock = 'bvm_google_' . substr(hash('sha256', DB_NAME . ':' . $wpdb->prefix), 0, 48);
    $locked = false;
    try {
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)', $lock)) !== 1) return new WP_Error('google_busy', 'Another Google operation is running.');
        $locked = true;
        $GLOBALS['bvmgr_google_lock'] = $lock;
        return $operation();
    } catch (Throwable $e) {
        // Never surface provider exception messages or credential-bearing response data.
        return new WP_Error('google_operation_failed', 'Google operation stopped safely. Check connection status and retry.');
    } finally {
        unset($GLOBALS['bvmgr_google_lock']);
        if ($locked) { try { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); } catch (Throwable $ignored) {} }
        $wpdb = $original;
        $reader->close();
    }
}

function bvmgr_google_save(array $state): void
{
    global $wpdb;
    $lock = $GLOBALS['bvmgr_google_lock'] ?? '';
    if (!$lock || (int)$wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)=CONNECTION_ID()', $lock)) !== 1) throw new RuntimeException('google_lock_lost');
    $json = wp_json_encode($state);
    if (!is_string($json)) throw new RuntimeException('google_state_invalid');
    $wpdb->query($wpdb->prepare('INSERT INTO %i (option_name,option_value,autoload) VALUES (%s,%s,%s) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value),autoload=VALUES(autoload)', $wpdb->options, 'bvmgr_tasks_google_v1', $json, 'no'));
    // Reads deliberately bypass WP option caches so independent workers see durable state.
}

function bvmgr_google_configured(): bool
{
    return defined('BVM_GOOGLE_CLIENT_ID') && is_string(BVM_GOOGLE_CLIENT_ID) && BVM_GOOGLE_CLIENT_ID !== ''
        && defined('BVM_GOOGLE_CLIENT_SECRET') && is_string(BVM_GOOGLE_CLIENT_SECRET) && BVM_GOOGLE_CLIENT_SECRET !== ''
        && defined('BVM_GOOGLE_TOKEN_KEY') && is_string(BVM_GOOGLE_TOKEN_KEY)
        && strlen((string)base64_decode(BVM_GOOGLE_TOKEN_KEY, true)) === 32
        && function_exists('sodium_crypto_secretbox');
}

/** Authenticated encryption follows the accepted social-token model, with a separate deployment key. */
function bvmgr_google_seal(array $value): string
{
    if (!bvmgr_google_configured()) throw new RuntimeException('google_not_configured');
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox(wp_json_encode($value), $nonce, base64_decode(BVM_GOOGLE_TOKEN_KEY, true)));
}
function bvmgr_google_unseal(string $value): array
{
    if (!bvmgr_google_configured()) return array();
    $raw = base64_decode($value, true);
    if (!is_string($raw) || strlen($raw) < 40) return array();
    try { $plain = sodium_crypto_secretbox_open(substr($raw, 24), substr($raw, 0, 24), base64_decode(BVM_GOOGLE_TOKEN_KEY, true)); }
    catch (Throwable $e) { return array(); }
    return $plain ? (json_decode($plain, true) ?: array()) : array();
}
function bvmgr_google_allowed_user(int $user): bool
{
    return $user > 0 && bvmgr_tasks_person_valid($user) && (user_can($user, bvmgr_tasks_cap_view_self()) || user_can($user, bvmgr_tasks_cap_manage_all()) || user_can($user, 'manage_options'));
}
