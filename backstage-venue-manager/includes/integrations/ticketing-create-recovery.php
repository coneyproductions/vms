<?php
defined('ABSPATH') || exit;

class BVMGR_Ticketing_Commit_Ownership_Lost extends RuntimeException {}

/** A reconnected request must never resume writes under another request's lock. */
function bvmgr_ticketing_v2_commit_sync(int $plan_id, string $preview_id, array $options = array()): array {
    global $wpdb;
    if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
        return bvmgr_ticketing_v2_commit_sync_unlocked($plan_id, $preview_id, $options);
    }
    if (!empty($GLOBALS['bvmgr_ticketing_commit_fence'])) {
        return array('ok' => false, 'message' => 'commit_in_progress', 'http' => 409);
    }
    $name = 'bvm_tix_' . md5($wpdb->prefix . ':' . $plan_id);
    $lock = $wpdb->get_row($wpdb->prepare('SELECT CONNECTION_ID() AS owner_id, GET_LOCK(%s, 0) AS acquired', $name), ARRAY_A);
    if (!is_array($lock) || (string) ($lock['acquired'] ?? '') !== '1') {
        return array('ok' => false, 'message' => 'commit_in_progress', 'detail' => 'Another ticket Commit is running. Wait for its result, then reload Preview.', 'http' => 409);
    }
    $GLOBALS['bvmgr_ticketing_commit_lock'] = $name;
    $GLOBALS['bvmgr_ticketing_commit_fence'] = array('name' => $name, 'owner_id' => (int) $lock['owner_id'], 'lost' => false);
    $GLOBALS['bvmgr_ticketing_commit_diagnostic'] = array('plan_id' => $plan_id, 'preview_id' => sanitize_key($preview_id), 'phase' => sanitize_key($options['phase'] ?? 'prepare'), 'cursor' => absint($options['cursor'] ?? 0));
    bvmgr_ticketing_v2_register_fatal_diagnostic();
    try {
        bvmgr_ticketing_v2_assert_commit_lock();
        wp_cache_delete($plan_id, 'post_meta');
        $result = bvmgr_ticketing_v2_commit_sync_unlocked($plan_id, $preview_id, $options);
        bvmgr_ticketing_v2_assert_commit_lock();
        return $result;
    } catch (Throwable $error) {
        // A zero-row fenced write can surface as a provider/checkpoint error.
        // Identify ownership loss before considering any failure checkpoint.
        try {
            bvmgr_ticketing_v2_assert_commit_lock();
        } catch (BVMGR_Ticketing_Commit_Ownership_Lost $lost) {
            return array('ok' => false, 'message' => 'commit_ownership_lost', 'error_summary' => 'The database connection or Commit owner changed. This request stopped without checkpointing its stale state. Reload Preview before continuing.', 'http' => 409);
        }
        return array('ok' => false, 'message' => 'commit_validation_failed', 'error_summary' => sanitize_text_field($error->getMessage()) . ' Review the saved mapping and run a fresh Preview before continuing.', 'http' => 409);
    } finally {
        unset($GLOBALS['bvmgr_ticketing_commit_diagnostic'], $GLOBALS['bvmgr_ticketing_commit_lock']);
        $fence = $GLOBALS['bvmgr_ticketing_commit_fence'];
        if (empty($fence['lost'])) {
            try {
                bvmgr_ticketing_v2_assert_commit_lock();
                // Native driver call: cleanup must not reconnect and adopt a new connection.
                $released = mysqli_query($wpdb->dbh, $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
                if ($released instanceof mysqli_result) { mysqli_free_result($released); }
            } catch (BVMGR_Ticketing_Commit_Ownership_Lost $error) {
                // The poisoned request remains fenced through all shutdown callbacks.
            }
        }
        if (empty($GLOBALS['bvmgr_ticketing_commit_fence']['lost'])) {
            unset($GLOBALS['bvmgr_ticketing_commit_fence']);
        }
    }
}

function bvmgr_ticketing_v2_register_fatal_diagnostic(): void {
    static $registered = false;
    if ($registered) { return; }
    $registered = true;
    $GLOBALS['bvmgr_ticketing_emergency_memory'] = str_repeat('r', 262144);
    register_shutdown_function('bvmgr_ticketing_v2_record_commit_fatal');
    // WP/CLI error rendering can exit before later shutdown functions run.
    add_filter('wp_php_error_message', static function ($message, $error) {
        bvmgr_ticketing_v2_record_commit_fatal($error);
        return $message;
    }, -PHP_INT_MAX, 2);
}

function bvmgr_ticketing_v2_record_commit_fatal(?array $error = null): void {
    static $recorded = false;
    unset($GLOBALS['bvmgr_ticketing_emergency_memory']);
    $context = $GLOBALS['bvmgr_ticketing_commit_diagnostic'] ?? null;
    $error = $error ?? error_get_last();
    if ($recorded || !is_array($context) || !$error || !in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) { return; }
    $recorded = true;
    update_option('bvmgr_ticketing_v2_last_fatal', array_merge($context, array('at_gmt' => gmdate('c'), 'type' => $error['type'], 'file' => str_replace(ABSPATH, '', $error['file']), 'line' => $error['line'], 'message' => substr(sanitize_text_field($error['message']), 0, 1000))), false);
}

function bvmgr_ticketing_v2_assert_commit_lock(): void {
    global $wpdb;
    $fence = $GLOBALS['bvmgr_ticketing_commit_fence'] ?? null;
    if (!is_array($fence) || !empty($fence['lost'])) {
        throw new BVMGR_Ticketing_Commit_Ownership_Lost('Ticket Commit ownership is unavailable.');
    }
    $owned = false;
    try {
        $dbh = $wpdb->dbh;
        if ($dbh instanceof mysqli && mysqli_thread_id($dbh) === $fence['owner_id']) {
            $result = mysqli_query($dbh, $wpdb->prepare('SELECT IS_USED_LOCK(%s) = CONNECTION_ID()', $fence['name']));
            if ($result instanceof mysqli_result) {
                $row = mysqli_fetch_row($result);
                mysqli_free_result($result);
                $owned = (string) ($row[0] ?? '') === '1';
            }
        }
    } catch (Throwable $error) {
        $owned = false;
    }
    if (!$owned) {
        $GLOBALS['bvmgr_ticketing_commit_fence']['lost'] = true;
        throw new BVMGR_Ticketing_Commit_Ownership_Lost('The original database connection no longer owns this Commit.');
    }
}

/** SQL lexical positions, excluding literals, identifiers, comments and subqueries. */
function bvmgr_ticketing_v2_sql_words(string $sql, bool $reject_line_comments = false): array {
    $words = array(); $length = strlen($sql); $depth = 0;
    for ($i = 0; $i < $length;) {
        $char = $sql[$i];
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char; ++$i;
            while ($i < $length) {
                if ($sql[$i] === '\\') { $i += 2; continue; }
                if ($sql[$i] === $quote) { if (($sql[$i + 1] ?? '') === $quote) { $i += 2; continue; } ++$i; break; }
                ++$i;
            }
            continue;
        }
        if (substr($sql, $i, 2) === '/*') { $end = strpos($sql, '*/', $i + 2); $i = $end === false ? $length : $end + 2; continue; }
        if ($char === '#' || substr($sql, $i, 2) === '--') { if ($reject_line_comments) { throw new RuntimeException('Unsupported SQL line comment during ticket Commit; no mutation was sent.'); } $end = strpos($sql, "\n", $i); $i = $end === false ? $length : $end + 1; continue; }
        if ($char === '(') { ++$depth; ++$i; continue; }
        if ($char === ')') { --$depth; ++$i; continue; }
        if ($depth === 0 && (ctype_alpha($char) || $char === '_')) {
            $start = $i++; while ($i < $length && (ctype_alnum($sql[$i]) || $sql[$i] === '_')) { ++$i; }
            $words[] = array('word' => strtoupper(substr($sql, $start, $i - $start)), 'start' => $start, 'end' => $i);
            continue;
        }
        ++$i;
    }
    return $words;
}

function bvmgr_ticketing_v2_sql_condition(string $sql, string $predicate, string $keyword = 'WHERE'): string {
    $words = bvmgr_ticketing_v2_sql_words($sql); $condition = null; $suffix = strlen(rtrim($sql, " ;\t\r\n"));
    foreach ($words as $word) {
        if ($word['word'] === $keyword) { $condition = $word; }
        if (in_array($word['word'], array('ORDER', 'LIMIT', 'RETURNING', 'FOR'), true) || ($keyword === 'WHERE' && in_array($word['word'], array('GROUP', 'HAVING'), true))) { $suffix = min($suffix, $word['start']); }
    }
    if ($condition !== null && $condition['start'] < $suffix) {
        return substr($sql, 0, $condition['end']) . ' (' . trim(substr($sql, $condition['end'], $suffix - $condition['end'])) . ') AND ' . $predicate . ' ' . substr($sql, $suffix);
    }
    return substr($sql, 0, $suffix) . ' ' . $keyword . ' ' . $predicate . ' ' . substr($sql, $suffix);
}

/** The predicate is evaluated by MySQL on every actual execution, including wpdb retries. */
function bvmgr_ticketing_v2_fence_query(string $sql): string {
    global $wpdb;
    $fence = $GLOBALS['bvmgr_ticketing_commit_fence'] ?? null;
    if (!is_array($fence)) { return $sql; }
    $words = bvmgr_ticketing_v2_sql_words($sql);
    $verb = $words[0]['word'] ?? '';
    if (in_array($verb, array('SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN', 'SET', 'START', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'RELEASE'), true)) { return $sql; }
    // A trailing line comment could consume an appended ownership predicate.
    // No provider mutation needs this shape; reject every double-dash outside
    // literals, including subtraction and alternate server comment delimiters.
    try { bvmgr_ticketing_v2_sql_words($sql, true); }
    catch (RuntimeException $error) { if (!empty($fence['lost'])) { return 'SELECT 0 WHERE 0 = 1'; } throw $error; }
    if (empty($fence['lost'])) { bvmgr_ticketing_v2_assert_commit_lock(); }
    $predicate = '(CONNECTION_ID() = ' . $fence['owner_id'] . " AND IS_USED_LOCK('" . $fence['name'] . "') = " . $fence['owner_id'] . ')';
    if (!empty($fence['lost'])) { $predicate = '(0 = 1)'; }
    if ($verb === 'UPDATE' || $verb === 'DELETE') { return bvmgr_ticketing_v2_sql_condition($sql, $predicate); }
    if ($verb === 'INSERT' || $verb === 'REPLACE') {
        foreach ($words as $word) {
            if ($word['word'] === 'VALUES' || $word['word'] === 'VALUE') {
                $remaining = ltrim(substr($sql, $word['end'])); $rows = array();
                while (str_starts_with($remaining, '(')) {
                    // Wrap one VALUES tuple as SELECT so the ownership test belongs to the INSERT.
                    $depth = 0; $quote = ''; $end = null;
                    for ($i = 0, $n = strlen($remaining); $i < $n; ++$i) {
                        $c = $remaining[$i];
                        if ($quote !== '') { if ($c === '\\') { ++$i; } elseif ($c === $quote) { if (($remaining[$i + 1] ?? '') === $quote) { ++$i; } else { $quote = ''; } } continue; }
                        if ($c === "'" || $c === '"' || $c === '`') { $quote = $c; continue; }
                        if ($c === '(') { ++$depth; } elseif ($c === ')' && --$depth === 0) { $end = $i; break; }
                    }
                    if ($end === null) { throw new RuntimeException('Cannot fence an incomplete ticket INSERT.'); }
                    $rows[] = 'SELECT ' . substr($remaining, 1, $end - 1) . ' WHERE ' . $predicate;
                    $remaining = ltrim(substr($remaining, $end + 1));
                    if (!str_starts_with($remaining, ',')) { break; }
                    $remaining = ltrim(substr($remaining, 1));
                }
                if (!$rows || ($remaining !== '' && !preg_match('/^(ON\s+DUPLICATE\s+KEY\s+UPDATE\b|;)/i', $remaining))) { throw new RuntimeException('Unsupported ticket INSERT shape; no mutation was sent.'); }
                return substr($sql, 0, $word['start']) . implode(' UNION ALL ', $rows) . ' ' . $remaining;
            }
            if ($word['word'] === 'SELECT') {
                $select = substr($sql, $word['start']);
                foreach (bvmgr_ticketing_v2_sql_words($select) as $part) { if ($part['word'] === 'UNION') { throw new RuntimeException('Unsupported union INSERT during ticket Commit.'); } }
                $suffix = '';
                $parts = bvmgr_ticketing_v2_sql_words($select);
                foreach ($parts as $index => $part) {
                    if ($part['word'] === 'ON' && ($parts[$index + 1]['word'] ?? '') === 'DUPLICATE' && ($parts[$index + 2]['word'] ?? '') === 'KEY' && ($parts[$index + 3]['word'] ?? '') === 'UPDATE') {
                        $offset = $part['start']; $suffix = substr($select, $offset); $select = substr($select, 0, $offset); break;
                    }
                }
                return substr($sql, 0, $word['start']) . bvmgr_ticketing_v2_sql_condition($select, $predicate, 'HAVING') . ' ' . $suffix;
            }
        }
    }
    if (!empty($fence['lost'])) { return 'SELECT 0 WHERE 0 = 1'; }
    throw new RuntimeException('Unsupported SQL mutation during fenced ticket Commit: ' . $verb);
}
add_filter('query', 'bvmgr_ticketing_v2_fence_query', PHP_INT_MAX);

function bvmgr_ticketing_v2_create_intent_key(int $plan_id, int $event_id, string $scope, string $key): string {
    return 'bvm_tix_create_' . md5($plan_id . ':' . $event_id . ':' . $scope . ':' . $key);
}

/** Direct, uncached inventory of every SKU path Woo may consult, including stale lookup rows. */
function bvmgr_ticketing_v2_recovery_sku_candidates(string $sku): array {
    global $wpdb;
    $ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value=%s", $sku));
    if ($wpdb->last_error !== '') { throw new RuntimeException('SKU dependency inventory is unreadable; no product may be reused or created.'); }
    $lookup = $wpdb->get_col($wpdb->prepare("SELECT product_id FROM {$wpdb->prefix}wc_product_meta_lookup WHERE sku=%s", $sku));
    if ($wpdb->last_error !== '') { throw new RuntimeException('Woo SKU lookup inventory is unreadable; reconcile before Commit.'); }
    return array_values(array_unique(array_map('intval', array_merge($ids, $lookup))));
}

function bvmgr_ticketing_v2_expected_product_sku(int $event_id, string $scope, string $key): string {
    return $scope === 'entitlement' ? 'VMS-TEC' . $event_id . '-ENT-' . substr($key, 0, 12) : 'VMS-' . $event_id . '-' . sanitize_key($key);
}

/** Check persisted maps before reuse OR retirement; owner markers cannot override another map. */
function bvmgr_ticketing_v2_assert_product_mapping_owner(int $id, int $plan_id): void {
    global $wpdb;
    $other_maps = $wpdb->get_results($wpdb->prepare("SELECT post_id,meta_value FROM {$wpdb->postmeta} WHERE meta_key='_vms_ticketing_sync_v2' AND post_id<>%d AND meta_value LIKE %s", $plan_id, '%' . $id . '%'), ARRAY_A);
    if ($wpdb->last_error !== '') { throw new RuntimeException('Canonical ownership inventory is unreadable.'); }
    foreach ($other_maps as $other_map) {
        $other_sync = maybe_unserialize($other_map['meta_value']);
        if (is_array($other_sync) && in_array($id, bvmgr_ticketing_v2_collect_sync_map_product_ids($other_sync['map'] ?? array()), true)) { throw new RuntimeException('Product #' . $id . ' is mapped by another Event Plan #' . absint($other_map['post_id'])); }
    }
}

/** A deterministic SKU is a collision detector, never ownership evidence. */
function bvmgr_ticketing_v2_validate_product_reuse(int $id, int $plan_id, int $event_id, string $scope, string $key): void {
    global $wpdb;
    if (!empty($GLOBALS['bvmgr_ticketing_commit_fence'])) { bvmgr_ticketing_v2_assert_commit_lock(); }
    if ($id <= 0 || get_post_type($id) !== 'product' || get_post_status($id) === 'trash') { throw new RuntimeException('Missing or trashed canonical product #' . $id . '. Reconcile its mapping before Commit.'); }
    $bucket = $scope === 'ticket' ? 'tickets' : 'entitlements';
    $sync = bvmgr_ticketing_v2_get_sync($plan_id);
    $mapped = (int) ($sync['map'][$bucket][$key]['woo_product_id'] ?? 0) === $id;
    foreach (array('tickets','entitlements') as $other_bucket) {
        foreach (($sync['map'][$other_bucket] ?? array()) as $other_key => $row) {
            if ((int) ($row['woo_product_id'] ?? 0) === $id && ($other_bucket !== $bucket || $other_key !== $key)) { throw new RuntimeException('Ambiguous canonical mapping for product #' . $id); }
        }
    }
    bvmgr_ticketing_v2_assert_product_mapping_owner($id, $plan_id);
    $intent = get_option(bvmgr_ticketing_v2_create_intent_key($plan_id, $event_id, $scope, $key), array());
    $token = (string) ($intent['token'] ?? '');
    $proven_intent = $token !== '' && (int) ($intent['product_id'] ?? 0) === $id && (get_post_meta($id, '_bvm_ticket_create_token', true) === $token || get_post_field('post_name', $id) === 'bvm-create-' . $token);
    foreach (array('_vms_event_plan_id' => $plan_id, '_vms_ticketing_source_plan_id' => $plan_id, '_vms_tec_event_id' => $event_id) as $marker => $expected) {
        $actual = get_post_meta($id, $marker, true);
        if ($actual !== '' && (int) $actual !== $expected) { throw new RuntimeException('Foreign ownership on product #' . $id . ': ' . $marker); }
    }
    $identity_key = $scope === 'ticket' ? '_vms_ticketing_ticket_key' : '_vms_ticketing_entitlement_id';
    $actual_key = (string) get_post_meta($id, $identity_key, true);
    if ($actual_key !== '' && $actual_key !== $key) { throw new RuntimeException('Conflicting ticket identity on product #' . $id); }
    if ($scope === 'ticket') {
        $linked = absint(get_post_meta($id, '_tribe_wooticket_for_event', true));
        if (($linked !== $event_id && !$proven_intent) || ($linked > 0 && $linked !== $event_id)) { throw new RuntimeException('Conflicting calendar ownership on product #' . $id); }
    } elseif (!$proven_intent && (absint(get_post_meta($id, '_vms_event_plan_id', true)) !== $plan_id || $actual_key !== $key)) {
        throw new RuntimeException('Missing canonical add-on identity on product #' . $id);
    }
    if (!$mapped && !$proven_intent) { throw new RuntimeException('Unproven product reuse #' . $id . '. A SKU or title does not establish ownership.'); }
    if (!$mapped || ($sync['map'][$bucket][$key]['sync_status'] ?? '') === 'recovering' || ($intent && ($intent['status'] ?? '') !== 'complete')) {
        $deps = bvmgr_ticketing_v2_recovery_dependencies($id);
        if ($deps) { throw new RuntimeException('Unsafe recovery product #' . $id . ': ' . wp_json_encode($deps)); }
    }
    $sku = bvmgr_ticketing_v2_expected_product_sku($event_id, $scope, $key);
    $ids = bvmgr_ticketing_v2_recovery_sku_candidates($sku);
    $others = array_values(array_diff(array_map('intval', $ids), array($id)));
    if ($others) { throw new RuntimeException('Ambiguous SKU ' . $sku . ': products #' . implode(', #', $others) . '. No product was changed.'); }
}

/** Fail closed: any historical order, attendee, hold or claim prevents orphan adoption. */
function bvmgr_ticketing_v2_recovery_dependencies(int $product_id): array {
    global $wpdb;
    $checks = array(
        'order_lines' => $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key IN ('_product_id','_variation_id') AND meta_value=%s", (string) $product_id),
        'post_references' => $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id<>%d AND meta_value=%s AND meta_key IN ('_tribe_wooticket_product','_tribe_rsvp_product','_product_id','_variation_id','_ticket_id','_ticket_product_id','ticket_product_id')", $product_id, (string) $product_id),
    );
    $dependencies = array();
    foreach ($checks as $label => $sql) {
        $value = $wpdb->get_var($sql);
        if ($wpdb->last_error !== '' || $value === null) { $dependencies[$label] = 'unknown'; }
        elseif ((int) $value > 0) { $dependencies[$label] = (int) $value; }
    }
    // Include optional HPOS lookups, Woo holds, native groups, BVM claims and rentals.
    foreach (array('wc_order_product_lookup','wc_reserved_stock','tec_posts_and_ticket_groups','vms_ticketing_claim_reservations','vms_ticketing_direct_grants','vms_pass_claims','vms_admission_entries','vms_ticketing_claim_log','bvm_rentals_event_config','bvm_rentals_reservations') as $suffix) {
        $table = $wpdb->prefix . $suffix;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) { if ($wpdb->last_error !== '') { $dependencies[$suffix] = 'unknown'; } continue; }
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0);
        if ($wpdb->last_error !== '') { $dependencies[$suffix] = 'unknown'; continue; }
        foreach (array_intersect(array('product_id','variation_id','ticket_id','ticket_product_id','woo_product_id','post_id'), $columns) as $column) {
            $value = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}`=%d", $product_id));
            if ($wpdb->last_error !== '' || $value === null) { $dependencies[$suffix . '.' . $column] = 'unknown'; }
            elseif ((int) $value > 0) { $dependencies[$suffix . '.' . $column] = (int) $value; }
        }
    }
    foreach (array('total_sales','_vms_ticketing_sold_qty_v2','_vms_ticketing_entitlement_sold_qty_v2') as $key) {
        $value = get_post_meta($product_id, $key, true);
        if ($value !== '' && (!is_numeric($value) || (float) $value !== 0.0)) { $dependencies[$key] = $value; }
    }
    return $dependencies;
}

/** Capture identity before provider callbacks; the insert slug covers even earlier failure. */
function bvmgr_ticketing_v2_capture_created_product(int $product_id): void {
    $active = $GLOBALS['bvmgr_ticketing_active_create'] ?? null;
    if (!is_array($active) || get_post_type($product_id) !== 'product' || get_post_field('post_name', $product_id) !== $active['slug']) { return; }
    bvmgr_ticketing_v2_assert_commit_lock();
    $intent = get_option($active['option'], array());
    if (!empty($intent['product_id']) && (int) $intent['product_id'] !== $product_id) {
        throw new RuntimeException('CREATE identity already belongs to product #' . absint($intent['product_id']));
    }
    // Persist the ID first, before any marker can invoke third-party hooks.
    $intent['product_id'] = $product_id;
    $intent['status'] = 'inserted';
    update_option($active['option'], $intent, false);
    if (get_option($active['option']) !== $intent) { throw new RuntimeException('Cannot persist CREATE product identity.'); }
    update_post_meta($product_id, '_bvm_ticket_create_token', $intent['token']);
    bvmgr_ticketing_v2_stamp_product_markers($product_id, $intent['plan_id'], $intent['event_id'], $intent['scope'] === 'ticket' ? 'ga_ticket' : 'entitlement', $intent['scope'] === 'ticket' ? '' : $intent['key']);
    if ($intent['scope'] === 'ticket') { update_post_meta($product_id, '_vms_ticketing_ticket_key', $intent['key']); }
    $sync = bvmgr_ticketing_v2_get_sync($intent['plan_id']);
    $bucket = $intent['scope'] === 'ticket' ? 'tickets' : 'entitlements';
    $sync['map'][$bucket][$intent['key']] = array('woo_product_id' => $product_id, 'tec_ticket_id' => $product_id, 'ticket_key' => $intent['key'], 'sync_status' => 'recovering', 'last_error' => 'Interrupted CREATE must be verified before reuse.');
    $sync['tec_event_id'] = $intent['event_id'];
    bvmgr_ticketing_v2_set_sync($intent['plan_id'], $sync);
    if (bvmgr_ticketing_v2_get_sync($intent['plan_id']) !== $sync) { throw new RuntimeException('Cannot persist captured ticket mapping.'); }
}

add_filter('wp_insert_post_data', static function (array $data, array $postarr): array {
    $active = $GLOBALS['bvmgr_ticketing_active_create'] ?? null;
    if (is_array($active) && ($data['post_type'] ?? '') === 'product' && empty($postarr['ID']) && wp_unslash($data['post_title'] ?? '') === $active['title']) {
        bvmgr_ticketing_v2_assert_commit_lock();
        if (!empty($active['insert_started'])) { throw new RuntimeException('Provider attempted a second INSERT for the same CREATE intent.'); }
        $GLOBALS['bvmgr_ticketing_active_create']['insert_started'] = true;
        $data['post_status'] = 'draft';
        $data['post_name'] = $active['slug'];
    }
    return $data;
}, PHP_INT_MAX, 2);
add_action('added_post_meta', static function ($meta_id, $product_id, $key): void {
    if ($key === '_tribe_wooticket_for_event') { bvmgr_ticketing_v2_capture_created_product((int) $product_id); }
}, -PHP_INT_MAX, 3);
add_action('transition_post_status', static function ($new, $old, $post): void {
    if ($old === 'new' && $post instanceof WP_Post) { bvmgr_ticketing_v2_capture_created_product((int) $post->ID); }
}, -PHP_INT_MAX, 3);

function bvmgr_ticketing_v2_recoverable_create(int $plan_id, int $event_id, string $scope, string $key, string $hash, string $title, string $preview_id, callable $create, callable $update): array {
    global $wpdb;
    bvmgr_ticketing_v2_assert_commit_lock();
    $option = bvmgr_ticketing_v2_create_intent_key($plan_id, $event_id, $scope, $key);
    $intent = get_option($option, array());
    $label = bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match($title);
    $candidates = array();
    $ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON p.ID=m.post_id AND m.meta_key='_tribe_wooticket_for_event' WHERE p.post_type='product' AND (m.meta_value=%s OR p.post_name=%s OR p.ID=%d)", (string) $event_id, 'bvm-create-' . ($intent['token'] ?? 'absent'), absint($intent['product_id'] ?? 0)));
    if ($wpdb->last_error !== '') { throw new RuntimeException('Cannot inspect interrupted CREATE candidates.'); }
    $sku_ids = bvmgr_ticketing_v2_recovery_sku_candidates(bvmgr_ticketing_v2_expected_product_sku($event_id, $scope, $key));
    $ids = array_values(array_unique(array_merge($ids, $sku_ids)));
    foreach ($ids as $id) {
        $id = absint($id);
        $same_title = bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match((string) get_post_field('post_title', $id, 'raw')) === $label;
        $same_token = !empty($intent['token']) && (get_post_meta($id, '_bvm_ticket_create_token', true) === $intent['token'] || get_post_field('post_name', $id) === 'bvm-create-' . $intent['token']);
        if ($same_title || $same_token || in_array($id, array_map('intval', $sku_ids), true) || $id === absint($intent['product_id'] ?? 0)) { $candidates[$id] = $same_token; }
    }
    // Add-ons need direct ownership lookup as they are not native TEC tickets.
    if ($scope === 'entitlement') {
        $owned = $wpdb->get_col($wpdb->prepare("SELECT a.post_id FROM {$wpdb->postmeta} a JOIN {$wpdb->postmeta} b ON a.post_id=b.post_id WHERE a.meta_key='_vms_event_plan_id' AND a.meta_value=%s AND b.meta_key='_vms_ticketing_entitlement_id' AND b.meta_value=%s", (string) $plan_id, $key));
        foreach ($owned as $id) { if (!isset($candidates[$id])) { $candidates[$id] = !empty($intent['token']) && get_post_meta($id, '_bvm_ticket_create_token', true) === $intent['token']; } }
    }
    if (count($candidates) > 1) { throw new RuntimeException('Ambiguous interrupted CREATE. Reconcile products #' . implode(', #', array_keys($candidates)) . ' before retrying.'); }
    if ($candidates) {
        $id = (int) array_key_first($candidates);
        $owner = absint(get_post_meta($id, '_vms_event_plan_id', true));
        $linked = absint(get_post_meta($id, '_tribe_wooticket_for_event', true));
        $deps = bvmgr_ticketing_v2_recovery_dependencies($id);
        foreach (array('_vms_event_plan_id' => $plan_id, '_vms_ticketing_source_plan_id' => $plan_id, '_vms_tec_event_id' => $event_id) as $marker => $expected) {
            $actual = get_post_meta($id, $marker, true);
            if ($actual !== '' && (int) $actual !== $expected) { $deps[$marker] = 'conflicting_owner'; }
        }
        $identity_key = $scope === 'ticket' ? '_vms_ticketing_ticket_key' : '_vms_ticketing_entitlement_id';
        $actual_key = get_post_meta($id, $identity_key, true);
        if ($actual_key !== '' && $actual_key !== $key) { $deps[$identity_key] = 'conflicting_identity'; }
        if (!$candidates[$id] || ($intent['ticket_hash'] ?? '') !== $hash || ($owner && $owner !== $plan_id) || ($linked && $linked !== $event_id) || get_post_status($id) === 'trash' || $deps) {
            throw new RuntimeException('Unsafe interrupted CREATE candidate #' . $id . '. Verify ownership, configuration, orders, attendees and claims. Dependencies: ' . wp_json_encode($deps));
        }
        $intent['product_id'] = $id;
        $intent['status'] = 'inserted';
        update_option($option, $intent, false);
        if (get_option($option) !== $intent) { throw new RuntimeException('Cannot persist recovered CREATE identity.'); }
        update_post_meta($id, '_bvm_ticket_create_token', $intent['token']);
        bvmgr_ticketing_v2_validate_product_reuse($id, $plan_id, $event_id, $scope, $key);
        $result = $update($id);
        $result['woo_product_id'] = $id;
        $result['tec_ticket_id'] = $id;
        return $result;
    }
    if (!empty($intent['product_id'])) { throw new RuntimeException('Previously captured CREATE product is missing. Reconcile #' . absint($intent['product_id']) . ' before retrying.'); }
    if (!$intent) {
        $intent = array('plan_id' => $plan_id, 'event_id' => $event_id, 'scope' => $scope, 'key' => $key, 'ticket_hash' => $hash, 'config_hash' => bvmgr_ticketing_v2_hash_config_for_sync(bvmgr_ticketing_v2_get_config($plan_id)), 'preview_id' => $preview_id, 'started_at_gmt' => gmdate('c'), 'token' => str_replace('-', '', wp_generate_uuid4()), 'status' => 'prepared', 'product_id' => 0);
        if (!add_option($option, $intent, '', false)) { throw new RuntimeException('Cannot persist CREATE intent. Reload Preview.'); }
    } elseif (($intent['ticket_hash'] ?? '') !== $hash) { throw new RuntimeException('Interrupted CREATE configuration changed. Reconcile the saved intent before retrying.'); }
    $GLOBALS['bvmgr_ticketing_active_create'] = array('option' => $option, 'slug' => 'bvm-create-' . $intent['token'], 'title' => bvmgr_ticketing_v2_compose_product_admin_title($title, $event_id));
    try { return $create(); }
    finally { unset($GLOBALS['bvmgr_ticketing_active_create']); }
}

/** Save successful actions immediately, including when the next action terminates. */
function bvmgr_ticketing_v2_checkpoint_action(int $plan_id, int $event_id, array &$map, array $row): void {
    bvmgr_ticketing_v2_assert_commit_lock();
    $sync = bvmgr_ticketing_v2_get_sync($plan_id);
    foreach (array('tickets', 'entitlements') as $bucket) {
        foreach (($sync['map'][$bucket] ?? array()) as $key => $value) {
            if (!isset($map[$bucket][$key]) && ($value['sync_status'] ?? '') === 'recovering') { $map[$bucket][$key] = $value; }
        }
    }
    $sync['map'] = $map;
    $sync['tec_event_id'] = $event_id;
    bvmgr_ticketing_v2_set_sync($plan_id, $sync);
    if (bvmgr_ticketing_v2_get_sync($plan_id) !== $sync) { throw new RuntimeException('Cannot persist ticket action checkpoint.'); }
    $scope = $row['scope'] ?? '';
    $key = $scope === 'ticket' ? ($row['ticket_key'] ?? '') : ($row['entitlement_id'] ?? '');
    if (!empty($row['ok']) && !empty($row['woo_product_id']) && in_array($scope, array('ticket','entitlement'), true)) {
        $option = bvmgr_ticketing_v2_create_intent_key($plan_id, $event_id, $scope, $key);
        $intent = get_option($option, array());
        if ($intent && (int) ($intent['product_id'] ?? 0) === (int) $row['woo_product_id']) {
            $intent['status'] = 'complete';
            update_option($option, $intent, false);
        }
    }
}

/** Select the real Woo provider for newly managed events; never switch an existing provider. */
function bvmgr_ticketing_v2_prepare_woo_provider(int $event_id): array {
    global $wpdb;
    $provider = Tribe__Tickets__Tickets::get_ticket_provider_instance('woo');
    if (!$provider) { return array('ok' => false, 'message' => 'event_tickets_woo_unavailable'); }
    $key = tribe('tickets.handler')->key_provider_field;
    $saved = (string) get_post_meta($event_id, $key, true);
    if ($saved !== '' && $saved !== $provider->class_name) {
        return array('ok' => false, 'message' => 'ticket_provider_conflict', 'detail' => 'The calendar event already selects another ticket provider. Reconcile its tickets before selecting WooCommerce.');
    }
    if ($saved === '') {
        $other = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_tribe_rsvp_for_event','_tec_tickets_commerce_event') AND meta_value=%s", (string) $event_id));
        if ($wpdb->last_error !== '' || $other) { return array('ok' => false, 'message' => 'ticket_provider_conflict', 'detail' => 'Existing non-Woo ticket paths or an unreadable provider inventory require reconciliation.'); }
        update_post_meta($event_id, $key, $provider->class_name);
        if (get_post_meta($event_id, $key, true) !== $provider->class_name) { return array('ok' => false, 'message' => 'ticket_provider_save_failed'); }
    }
    return array('ok' => true);
}
