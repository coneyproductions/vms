<?php

if (!defined('ABSPATH')) { exit; }

/*
 * Ticketing v2 eligibility enforcement
 *
 * Rules enforced:
 * - Entitlement items may require a minimum GA quantity per unit
 * - Optional caps per entitlement per order, and per GA ticket
 * - Blocks carts containing v2 items for multiple event plans
 */


/**
 * Read product meta with a safe fallback to the parent product when the cart line is a variation.
 * This prevents variation lines from bypassing eligibility/pool rules when meta is stored on the parent.
 */
function vms_ticketing_v2_meta_get(int $product_id, string $meta_key) {
    $product_id = absint($product_id);
    if ($product_id <= 0 || $meta_key === '') {
        return '';
    }

    $val = get_post_meta($product_id, $meta_key, true);

    // Only fall back when the meta is genuinely missing (empty string). Do not treat "0" as missing.
    if ($val === '' || $val === null) {
        $parent_id = wp_get_post_parent_id($product_id);
        if ($parent_id > 0) {
            $parent_val = get_post_meta($parent_id, $meta_key, true);
            if ($parent_val !== '' && $parent_val !== null) {
                return $parent_val;
            }
        }
    }

    return $val;
}

function vms_ticketing_v2_meta_truthy($value, bool $default = true): bool
{
    $raw = strtolower(trim((string) $value));
    if ($raw === '') {
        return $default;
    }
    if (in_array($raw, array('0', 'false', 'no', 'off'), true)) {
        return false;
    }
    return true;
}

function vms_ticketing_v2_claim_grant_type_values(): array
{
    if (function_exists('vms_ticketing_claims_allowed_grant_types')) {
        return (array) vms_ticketing_claims_allowed_grant_types();
    }
    return array('event_ticket_eligibility', 'event_free_admit', 'credential_benefit_override', 'event_grant');
}

/**
 * True if the given cart/product ID matches the mapped Woo product ID.
 *
 * Note: tickets are commonly variable products. Woo cart lines may reference
 * the variation ID, while VMS mapping typically stores the parent product ID.
 */
function vms_ticketing_v2_pid_matches_mapped(int $pid, int $mapped_pid): bool
{
    $pid = absint($pid);
    $mapped_pid = absint($mapped_pid);
    if ($pid <= 0 || $mapped_pid <= 0) {
        return false;
    }
    if ($pid === $mapped_pid) {
        return true;
    }
    $parent = wp_get_post_parent_id($pid);
    return ($parent > 0 && $parent === $mapped_pid);
}

/**
 * Session key used to carry short-lived GA qualification hints between
 * ticket add and reserved add-on validation.
 */
function vms_ticketing_v2_session_ga_hint_key(): string
{
    return 'vms_ticketing_v2_ga_hint_by_plan_v1';
}

/**
 * @return array<int,array<string,mixed>>
 */
function vms_ticketing_v2_session_get_ga_hints(): array
{
    if (!function_exists('WC')) {
        return array();
    }

    $wc = WC();
    if (!$wc || !isset($wc->session) || !$wc->session) {
        return array();
    }

    $raw = $wc->session->get(vms_ticketing_v2_session_ga_hint_key(), array());
    if (!is_array($raw) || empty($raw)) {
        return array();
    }

    $now = time();
    $out = array();
    $changed = false;

    foreach ($raw as $plan_key => $row) {
        $plan_id = absint($plan_key);
        if ($plan_id <= 0 || !is_array($row)) {
            $changed = true;
            continue;
        }

        $qty = absint($row['qty'] ?? 0);
        if ($qty <= 0) {
            $changed = true;
            continue;
        }

        $expires_at = absint($row['expires_at'] ?? 0);
        if ($expires_at > 0 && $expires_at < $now) {
            $changed = true;
            continue;
        }

        $out[$plan_id] = array(
            'qty' => $qty,
            'set_at' => absint($row['set_at'] ?? 0),
            'expires_at' => $expires_at,
            'source' => sanitize_key((string) ($row['source'] ?? '')),
        );
    }

    if ($changed) {
        $wc->session->set(vms_ticketing_v2_session_ga_hint_key(), $out);
    }

    return $out;
}

/**
 * @param array<int,array<string,mixed>> $hints
 */
function vms_ticketing_v2_session_set_ga_hints(array $hints): void
{
    if (!function_exists('WC')) {
        return;
    }

    $wc = WC();
    if (!$wc || !isset($wc->session) || !$wc->session) {
        return;
    }

    $clean = array();
    foreach ($hints as $plan_key => $row) {
        $plan_id = absint($plan_key);
        if ($plan_id <= 0 || !is_array($row)) {
            continue;
        }

        $qty = absint($row['qty'] ?? 0);
        if ($qty <= 0) {
            continue;
        }

        $clean[$plan_id] = array(
            'qty' => $qty,
            'set_at' => absint($row['set_at'] ?? 0),
            'expires_at' => absint($row['expires_at'] ?? 0),
            'source' => sanitize_key((string) ($row['source'] ?? '')),
        );
    }

    $wc->session->set(vms_ticketing_v2_session_ga_hint_key(), $clean);
}

function vms_ticketing_v2_session_seed_ga_hint(int $plan_id, int $ga_qty, string $source = ''): void
{
    $plan_id = absint($plan_id);
    $ga_qty = absint($ga_qty);
    if ($plan_id <= 0 || $ga_qty <= 0) {
        return;
    }

    $ttl = (int) apply_filters('vms_ticketing_v2_ga_hint_ttl_seconds', 300, $plan_id, $source);
    if ($ttl < 30) {
        $ttl = 30;
    }

    $hints = vms_ticketing_v2_session_get_ga_hints();
    $existing_qty = absint($hints[$plan_id]['qty'] ?? 0);
    $now = time();

    $hints[$plan_id] = array(
        'qty' => max($ga_qty, $existing_qty),
        'set_at' => $now,
        'expires_at' => ($now + $ttl),
        'source' => sanitize_key($source),
    );

    vms_ticketing_v2_session_set_ga_hints($hints);
}

function vms_ticketing_v2_session_clear_ga_hint(int $plan_id = 0): void
{
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        vms_ticketing_v2_session_set_ga_hints(array());
        return;
    }

    $hints = vms_ticketing_v2_session_get_ga_hints();
    if (isset($hints[$plan_id])) {
        unset($hints[$plan_id]);
        vms_ticketing_v2_session_set_ga_hints($hints);
    }
}

function vms_ticketing_v2_effective_ga_qty_for_plan(int $plan_id, int $cart_ga_qty): int
{
    $plan_id = absint($plan_id);
    $cart_ga_qty = max(0, absint($cart_ga_qty));
    if ($plan_id <= 0) {
        return $cart_ga_qty;
    }

    $hints = vms_ticketing_v2_session_get_ga_hints();
    $hint_qty = 0;
    if (!empty($hints[$plan_id]) && is_array($hints[$plan_id])) {
        $hint_qty = max(0, absint($hints[$plan_id]['qty'] ?? 0));
    }

    if ($cart_ga_qty > 0) {
        // Cart state is authoritative once it catches up to the hinted total.
        // Until then, keep the higher hinted total so add-ons can qualify in mixed flows.
        if ($hint_qty > 0 && $cart_ga_qty >= $hint_qty) {
            vms_ticketing_v2_session_clear_ga_hint($plan_id);
            return $cart_ga_qty;
        }
        return max($cart_ga_qty, $hint_qty);
    }

    return max($cart_ga_qty, $hint_qty);
}

function vms_ticketing_v2_request_cart_cache_key(): string
{
    if (!function_exists('WC')) {
        return 'no_wc';
    }

    $wc = WC();
    if (!$wc || !isset($wc->cart) || !$wc->cart) {
        return 'no_cart';
    }

    $rows = array();
    foreach ((array) $wc->cart->get_cart() as $item) {
        if (!is_array($item)) {
            continue;
        }

        $rows[] = implode(':', array(
            (string) absint($item['product_id'] ?? 0),
            (string) absint($item['variation_id'] ?? 0),
            (string) max(1, absint($item['quantity'] ?? 0)),
        ));
    }

    sort($rows, SORT_STRING);
    return md5(implode('|', $rows));
}

function vms_ticketing_v2_request_purchase_cache_scope(array $customer_ctx): string
{
    $user_id = absint($customer_ctx['user_id'] ?? 0);
    $email = strtolower(sanitize_email((string) ($customer_ctx['email'] ?? '')));
    $session_customer_id = '';

    if (function_exists('WC')) {
        $wc = WC();
        if ($wc && isset($wc->session) && $wc->session && method_exists($wc->session, 'get_customer_id')) {
            $session_customer_id = sanitize_text_field((string) $wc->session->get_customer_id());
        }
    }

    return md5($user_id . '|' . $email . '|' . $session_customer_id . '|' . vms_ticketing_v2_request_cart_cache_key());
}


function vms_ticketing_v2_cart_scan(): array {
    static $cache = array();
    $cache_key = vms_ticketing_v2_request_cart_cache_key();
    if (isset($cache[$cache_key]) && is_array($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $out = array(
        'plan_ids' => array(),
        'ga_qty_by_plan' => array(),
        'ticket_lines' => array(),
        'ent_lines' => array(),
    );

    if (!function_exists('WC')) {
        return $out;
    }
    $wc = WC();
    if (!$wc || !isset($wc->cart) || !$wc->cart) {
        return $out;
    }

    // Cache sync maps per plan to avoid repeated option/meta reads.
    $sync_cache = array();

    foreach ($wc->cart->get_cart() as $item_key => $item) {
        if (!is_array($item)) {
            continue;
        }

        $product_id = absint($item['product_id'] ?? 0);
        $variation_id = absint($item['variation_id'] ?? 0);
        $pid = $variation_id > 0 ? $variation_id : $product_id;
        if ($pid <= 0) {
            continue;
        }

        $qty = absint($item['quantity'] ?? 0);
        if ($qty < 1) {
            $qty = 1;
        }

        // Primary: v2 markers (also present on legacy v1 products via _vms_event_plan_id).
        $plan_id = absint(vms_ticketing_v2_meta_get($pid, vms_ticketing_v2_product_meta_key('event_plan_id')));
        $role = (string) vms_ticketing_v2_meta_get($pid, vms_ticketing_v2_product_meta_key('product_role'));

        // Fallback: infer role from legacy meta.
        if ($role === '') {
            $sr_qual = (string) vms_ticketing_v2_meta_get($pid, '_sr_addon_qualifier');
            $sr_type = (string) vms_ticketing_v2_meta_get($pid, '_sr_addon_type');
            $sr_req  = (string) vms_ticketing_v2_meta_get($pid, '_sr_required_qualifiers_per_unit');
            $sr_unit = (string) vms_ticketing_v2_meta_get($pid, '_sr_addon_unit_label');

            if ($sr_qual === 'yes') {
                $role = 'ga_ticket';
            } elseif ($sr_type !== '' || $sr_req !== '' || $sr_unit !== '') {
                $role = 'entitlement';
            }
        }

        // Fallback: Event Tickets products can be mapped to a plan via TEC event ID.
        if ($plan_id <= 0) {
            $tec_event_id = absint(vms_ticketing_v2_meta_get($pid, '_tribe_wooticket_for_event'));
            if ($tec_event_id > 0 && function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id')) {
                $plan_id = bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id);
                if ($role === '') {
                    $role = 'ga_ticket';
                }
            }
        }

        // If product markers are missing (common on legacy/adopted products), derive role from the plan sync map.
        if ($plan_id > 0 && $role === '' && function_exists('vms_ticketing_v2_get_sync')) {
            if (!isset($sync_cache[$plan_id])) {
                $sync = vms_ticketing_v2_get_sync((int) $plan_id);
                $sync_cache[$plan_id] = is_array($sync) ? $sync : array();
            }

            $sync = $sync_cache[$plan_id];
            $map  = (isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();

            $ticket_map = (isset($map['tickets']) && is_array($map['tickets'])) ? $map['tickets'] : array();
            foreach ($ticket_map as $ticket_row) {
                if (!is_array($ticket_row)) {
                    continue;
                }
                $mapped_ticket_pid = absint($ticket_row['woo_product_id'] ?? 0);
                if ($mapped_ticket_pid > 0 && vms_ticketing_v2_pid_matches_mapped($pid, $mapped_ticket_pid)) {
                    $role = 'ga_ticket';
                    break;
                }
            }

            if ($role === '') {
                $mapped_ga_pid = absint($map['ga']['woo_product_id'] ?? 0);
                if ($mapped_ga_pid > 0 && vms_ticketing_v2_pid_matches_mapped($pid, $mapped_ga_pid)) {
                    $role = 'ga_ticket';
                } else {
                    $emap = (isset($map['entitlements']) && is_array($map['entitlements'])) ? $map['entitlements'] : array();
                    foreach ($emap as $ent_row) {
                        if (!is_array($ent_row)) continue;
                        $mapped_ent_pid = absint($ent_row['woo_product_id'] ?? 0);
                        if ($mapped_ent_pid > 0 && vms_ticketing_v2_pid_matches_mapped($pid, $mapped_ent_pid)) {
                            $role = 'entitlement';
                            break;
                        }
                    }
                }
            }
        }

        if ($plan_id <= 0 || $role === '') {
            continue;
        }

        if (!in_array($plan_id, $out['plan_ids'], true)) {
            $out['plan_ids'][] = $plan_id;
        }

        if ($role === 'ga_ticket') {
            $counts_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_counts_toward_unlock')
                : '_vms_ticketing_counts_toward_unlock';
            $counts_meta = vms_ticketing_v2_meta_get($pid, $counts_key);
            $counts_toward_unlock = vms_ticketing_v2_meta_truthy($counts_meta, true);

            $ratio_enabled_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_ratio_rule_enabled')
                : '_vms_ticketing_ratio_rule_enabled';
            $ratio_max_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_ratio_rule_max_per_qualifying')
                : '_vms_ticketing_ratio_rule_max_per_qualifying';
            $ratio_mode_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_ratio_rule_qualifier_mode')
                : '_vms_ticketing_ratio_rule_qualifier_mode';
            $ratio_group_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_ratio_rule_group')
                : '_vms_ticketing_ratio_rule_group';
            $ratio_rule_enabled = vms_ticketing_v2_meta_truthy(vms_ticketing_v2_meta_get($pid, $ratio_enabled_key), false);
            $ratio_rule_max_per_qualifying = max(0, absint(vms_ticketing_v2_meta_get($pid, $ratio_max_key)));
            if (!$ratio_rule_enabled || $ratio_rule_max_per_qualifying <= 0) {
                $ratio_rule_enabled = false;
                $ratio_rule_max_per_qualifying = 0;
            }
            $ratio_rule_qualifier_mode = sanitize_key((string) vms_ticketing_v2_meta_get($pid, $ratio_mode_key));
            if (!in_array($ratio_rule_qualifier_mode, array('counts_toward_unlock'), true)) {
                $ratio_rule_qualifier_mode = 'counts_toward_unlock';
            }
            $ratio_rule_group = sanitize_title((string) vms_ticketing_v2_meta_get($pid, $ratio_group_key));
            if (!$ratio_rule_enabled) {
                $ratio_rule_group = '';
            }

            $visibility_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_visibility_mode')
                : '_vms_ticketing_visibility_mode';
            $program_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_verified_program')
                : '_vms_ticketing_verified_program';
            $allowed_programs_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_allowed_programs')
                : '_vms_ticketing_allowed_programs';
            $allow_direct_grants_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_allow_direct_grants')
                : '_vms_ticketing_allow_direct_grants';
            $claim_grant_type_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_claim_grant_type')
                : '_vms_ticketing_claim_grant_type';
            $claims_per_assignee_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_claims_per_assignee')
                : '_vms_ticketing_claims_per_assignee';
            $require_assignee_email_key = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_require_assignee_email')
                : '_vms_ticketing_require_assignee_email';
            $ticket_key_meta = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_ticket_key')
                : '_vms_ticketing_ticket_key';
            $visibility_mode = sanitize_key((string) vms_ticketing_v2_meta_get($pid, $visibility_key));
            if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
                $visibility_mode = 'public';
            }
            $verified_program = sanitize_key((string) vms_ticketing_v2_meta_get($pid, $program_key));
            $allowed_programs = function_exists('vms_ticketing_v2_normalize_allowed_programs')
                ? vms_ticketing_v2_normalize_allowed_programs(vms_ticketing_v2_meta_get($pid, $allowed_programs_key), $verified_program)
                : ($verified_program !== '' ? array($verified_program) : array());
            $allow_direct_grants = function_exists('vms_ticketing_v2_truthy')
                ? vms_ticketing_v2_truthy(vms_ticketing_v2_meta_get($pid, $allow_direct_grants_key), false)
                : vms_ticketing_v2_meta_truthy(vms_ticketing_v2_meta_get($pid, $allow_direct_grants_key), false);
            $claim_grant_type = sanitize_key((string) vms_ticketing_v2_meta_get($pid, $claim_grant_type_key));
            if (!in_array($claim_grant_type, vms_ticketing_v2_claim_grant_type_values(), true)) {
                $claim_grant_type = 'event_ticket_eligibility';
            }
            $claims_per_assignee = max(0, absint(vms_ticketing_v2_meta_get($pid, $claims_per_assignee_key)));
            if ($claims_per_assignee <= 0) {
                $claims_per_assignee = 1;
            }
            $require_assignee_email = function_exists('vms_ticketing_v2_truthy')
                ? vms_ticketing_v2_truthy(vms_ticketing_v2_meta_get($pid, $require_assignee_email_key), true)
                : vms_ticketing_v2_meta_truthy(vms_ticketing_v2_meta_get($pid, $require_assignee_email_key), true);
            $event_id = absint(vms_ticketing_v2_meta_get($pid, '_vms_ticket_event_id'));
            if ($event_id <= 0) {
                $event_id = absint(vms_ticketing_v2_meta_get($pid, '_tribe_wooticket_for_event'));
            }
            if ($event_id <= 0) {
                $tec_meta_key = function_exists('vms_ticketing_v2_product_meta_key')
                    ? vms_ticketing_v2_product_meta_key('tec_event_id')
                    : '_vms_tec_event_id';
                $event_id = absint(vms_ticketing_v2_meta_get($pid, $tec_meta_key));
            }
            $ticket_key = sanitize_key((string) vms_ticketing_v2_meta_get($pid, '_vms_ticket_key'));
            if ($ticket_key === '') {
                $ticket_key = sanitize_key((string) vms_ticketing_v2_meta_get($pid, $ticket_key_meta));
            }
            if ($visibility_mode !== 'verified') {
                $verified_program = '';
                $allowed_programs = array();
                $allow_direct_grants = false;
                $claim_grant_type = 'event_ticket_eligibility';
                $claims_per_assignee = 1;
                $require_assignee_email = true;
            } elseif ($verified_program === '' && !empty($allowed_programs)) {
                $verified_program = (string) $allowed_programs[0];
            }

            $out['ticket_lines'][] = array(
                'plan_id' => $plan_id,
                'product_id' => $pid,
                'qty' => $qty,
                'counts_toward_unlock' => $counts_toward_unlock ? 1 : 0,
                'ratio_rule_enabled' => $ratio_rule_enabled ? 1 : 0,
                'ratio_rule_max_per_qualifying' => $ratio_rule_max_per_qualifying,
                'ratio_rule_qualifier_mode' => $ratio_rule_qualifier_mode,
                'ratio_rule_group' => $ratio_rule_group,
                'visibility_mode' => $visibility_mode,
                'verified_program' => $verified_program,
                'allowed_programs' => $allowed_programs,
                'allow_direct_grants' => $allow_direct_grants ? 1 : 0,
                'claim_grant_type' => $claim_grant_type,
                'claims_per_assignee' => $claims_per_assignee,
                'require_assignee_email' => $require_assignee_email ? 1 : 0,
                'event_id' => $event_id,
                'ticket_key' => $ticket_key,
                'title' => (string) get_the_title($pid),
            );

            if ($counts_toward_unlock) {
                $out['ga_qty_by_plan'][$plan_id] = (int) ($out['ga_qty_by_plan'][$plan_id] ?? 0) + $qty;
            }
            continue;
        }

        if ($role === 'entitlement') {
            $ent_id = sanitize_key((string) vms_ticketing_v2_meta_get($pid, vms_ticketing_v2_product_meta_key('ticketing_entitlement_id')));
            // Legacy entitlements may not have a v2 entitlement_id; keep empty and enforce via product meta.
            $out['ent_lines'][] = array(
                'plan_id' => $plan_id,
                'entitlement_id' => $ent_id,
                'product_id' => $pid,
                'qty' => $qty,
                'title' => (string) get_the_title($pid),
            );
        }
    }

    $cache[$cache_key] = $out;
    return $cache[$cache_key];
}

function vms_ticketing_v2_find_entitlement_cfg(array $cfg, string $entitlement_id): ?array {
    $entitlement_id = sanitize_key($entitlement_id);
    if ($entitlement_id === '') {
        return null;
    }

    $ents = isset($cfg['entitlements']) && is_array($cfg['entitlements']) ? $cfg['entitlements'] : array();
    foreach ($ents as $e) {
        if (!is_array($e)) {
            continue;
        }
        $id = sanitize_key((string) ($e['entitlement_id'] ?? ''));
        if ($id === $entitlement_id) {
            return $e;
        }
    }
    return null;
}

function vms_ticketing_v2_resolve_eligibility_for_product(int $product_id, int $plan_id, ?array $ent_cfg = null): array
{
    $out = array(
        'pool_key' => '',
        'pool_max_total' => 0,
        'min_ga_per_unit' => 0,
        'allow_without_ga' => false,
        'max_units_per_order' => 0,
        'max_units_per_ga' => 0,
        'label' => '',
    );

    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    if ($product_id <= 0) {
        return $out;
    }

    $pool_max_set = false;

    if (is_array($ent_cfg)) {
        $out['label'] = (string) ($ent_cfg['label'] ?? '');
        $elig = (isset($ent_cfg['eligibility']) && is_array($ent_cfg['eligibility'])) ? $ent_cfg['eligibility'] : array();
        $out['pool_key'] = sanitize_key((string) ($elig['pool_key'] ?? ''));
        if (array_key_exists('pool_max_total', $elig)) {
            $out['pool_max_total'] = max(0, absint($elig['pool_max_total']));
            $pool_max_set = true;
        }
        $out['min_ga_per_unit'] = absint($elig['min_ga_per_unit'] ?? 0);
        $out['allow_without_ga'] = !empty($elig['allow_without_ga']);
        $out['max_units_per_order'] = absint($elig['max_units_per_order'] ?? 0);
        $out['max_units_per_ga'] = absint($elig['max_units_per_ga'] ?? 0);
    }

    // Legacy fallback meta (pre-v2 import/blueprint flow).
    // NOTE: legacy sites sometimes store these keys on the parent product (variations) and/or use slightly different
    // type values (e.g., table_01, tables). We normalize into a stable bucket.
    $sr_type_raw  = (string) vms_ticketing_v2_meta_get($product_id, '_sr_addon_type');
    $sr_type      = sanitize_key($sr_type_raw);
    $sr_req       = absint(vms_ticketing_v2_meta_get($product_id, '_sr_required_qualifiers_per_unit'));
    $sr_qual      = (string) vms_ticketing_v2_meta_get($product_id, '_sr_addon_qualifier');
    $sr_unit_label = (string) vms_ticketing_v2_meta_get($product_id, '_sr_addon_unit_label');

    // Normalize legacy type to a stable bucket so pool rules don’t silently fail.
    $sr_bucket = '';
    if ($sr_type !== '') {
        if (strpos($sr_type, 'table') === 0) {
            $sr_bucket = 'table';
        } elseif (strpos($sr_type, 'fire') === 0) {
            $sr_bucket = 'fire_pit';
        }
    }
    if ($sr_bucket === '' && $sr_unit_label !== '') {
        $ul = strtolower($sr_unit_label);
        if (strpos($ul, 'table') !== false) {
            $sr_bucket = 'table';
        } elseif (strpos($ul, 'fire') !== false) {
            $sr_bucket = 'fire_pit';
        }
    }
    if ($sr_bucket === '') {
        $t = strtolower((string) get_the_title($product_id));
        if (strpos($t, 'table') !== false) {
            $sr_bucket = 'table';
        } elseif (strpos($t, 'fire pit') !== false || strpos($t, 'firepit') !== false) {
            $sr_bucket = 'fire_pit';
        }
    }

    if ($out['label'] === '') {
        $out['label'] = (string) get_the_title($product_id);
    }

    // If the legacy qualifier explicitly says this is a qualifier, treat it as GA-like.
    if ($sr_qual === 'yes') {
        $out['allow_without_ga'] = true;
        return $out;
    }

    if ($out['min_ga_per_unit'] <= 0 && $sr_req > 0) {
        $out['min_ga_per_unit'] = $sr_req;
    }

    // Pool key: reserved seating groups tables + fire pits together.
    // Use normalized bucket so legacy type variations like "table_01" don’t bypass.
    if ($out['pool_key'] === '' && $sr_bucket !== '') {
        if (in_array($sr_bucket, array('table', 'fire_pit'), true)) {
            $out['pool_key'] = 'reserved_seating';
        }
    }

    if (!$pool_max_set) {
        // No implicit hard cap. Pool limits must be configured explicitly.
        $out['pool_max_total'] = 0;
    }
    if ($out['pool_key'] === '') {
        $out['pool_max_total'] = 0;
    }

    // Plan-level fallback for reserved seating minimums.
    if ($out['pool_key'] === 'reserved_seating' && $out['min_ga_per_unit'] <= 0 && $plan_id > 0) {
        if ($sr_bucket === 'table') {
            $out['min_ga_per_unit'] = absint(get_post_meta($plan_id, '_vms_min_tickets_per_table', true));
        } elseif ($sr_bucket === 'fire_pit') {
            $out['min_ga_per_unit'] = absint(get_post_meta($plan_id, '_vms_min_tickets_per_firepit', true));
        }
    }

    // Default allow_without_ga when there is no GA requirement.
    if ($out['min_ga_per_unit'] <= 0) {
        $out['allow_without_ga'] = true;
    }

    return $out;
}

/**
 * Current cart quantity by entitlement eligibility pool for a single event plan.
 * Mirrors server-side validation semantics used during add-to-cart/checkout.
 */
function vms_ticketing_v2_cart_pool_qty_by_key_for_plan(int $plan_id, array $cfg = array()): array
{
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array();
    }

    $scan = vms_ticketing_v2_cart_scan();
    $ent_lines = (isset($scan['ent_lines']) && is_array($scan['ent_lines'])) ? $scan['ent_lines'] : array();
    if (empty($ent_lines)) {
        return array();
    }

    $out = array();
    foreach ($ent_lines as $line) {
        if (!is_array($line)) {
            continue;
        }

        if (absint($line['plan_id'] ?? 0) !== $plan_id) {
            continue;
        }

        $product_id = absint($line['product_id'] ?? 0);
        $qty = absint($line['qty'] ?? 0);
        if ($product_id <= 0 || $qty < 1) {
            continue;
        }

        $ent_id = sanitize_key((string) ($line['entitlement_id'] ?? ''));
        $ent_cfg = null;
        if ($ent_id !== '' && is_array($cfg) && !empty($cfg)) {
            $ent_cfg = vms_ticketing_v2_find_entitlement_cfg($cfg, $ent_id);
            if (is_array($ent_cfg) && empty($ent_cfg['enabled'])) {
                continue;
            }
        }

        $elig = vms_ticketing_v2_resolve_eligibility_for_product(
            $product_id,
            $plan_id,
            is_array($ent_cfg) ? $ent_cfg : null
        );

        $pool_key = sanitize_key((string) ($elig['pool_key'] ?? ''));
        if ($pool_key === '') {
            continue;
        }
        if (!empty($elig['allow_without_ga'])) {
            continue;
        }

        $out[$pool_key] = (int) ($out[$pool_key] ?? 0) + $qty;
    }

    ksort($out, SORT_STRING);
    return $out;
}

function vms_ticketing_v2_qualifying_ticket_product_ids_for_plan(int $plan_id): array
{
    $plan_id = absint($plan_id);
    if ($plan_id <= 0 || !function_exists('vms_ticketing_v2_get_sync')) {
        return array();
    }

    $sync = vms_ticketing_v2_get_sync($plan_id);
    $map = (is_array($sync) && isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
    if (empty($map)) {
        return array();
    }

    $out = array();
    $ticket_map = (isset($map['tickets']) && is_array($map['tickets'])) ? $map['tickets'] : array();
    foreach ($ticket_map as $ticket_row) {
        if (!is_array($ticket_row)) {
            continue;
        }

        $counts_toward_unlock = array_key_exists('counts_toward_unlock', $ticket_row)
            ? !empty($ticket_row['counts_toward_unlock'])
            : true;
        if (!$counts_toward_unlock) {
            continue;
        }

        $pid = absint($ticket_row['woo_product_id'] ?? 0);
        if ($pid > 0) {
            $out[] = $pid;
        }
    }

    $ga_pid = absint($map['ga']['woo_product_id'] ?? 0);
    if ($ga_pid > 0) {
        $out[] = $ga_pid;
    }

    $out = array_values(array_unique(array_filter(array_map('absint', $out))));
    sort($out, SORT_NUMERIC);
    return $out;
}

function vms_ticketing_v2_entitlement_product_ids_by_pool_for_plan(int $plan_id, array $cfg = array()): array
{
    $plan_id = absint($plan_id);
    if ($plan_id <= 0 || !function_exists('vms_ticketing_v2_get_sync')) {
        return array();
    }

    if (empty($cfg) && function_exists('vms_ticketing_v2_get_config')) {
        $cfg = vms_ticketing_v2_get_config($plan_id);
    }

    $sync = vms_ticketing_v2_get_sync($plan_id);
    $map = (is_array($sync) && isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
    $emap = (isset($map['entitlements']) && is_array($map['entitlements'])) ? $map['entitlements'] : array();
    if (empty($emap)) {
        return array();
    }

    $ent_cfg_by_id = array();
    $ents = (isset($cfg['entitlements']) && is_array($cfg['entitlements'])) ? $cfg['entitlements'] : array();
    foreach ($ents as $ent) {
        if (!is_array($ent)) {
            continue;
        }
        $ent_id = sanitize_key((string) ($ent['entitlement_id'] ?? ''));
        if ($ent_id === '') {
            continue;
        }
        $ent_cfg_by_id[$ent_id] = $ent;
    }

    $out = array();
    foreach ($emap as $ent_id => $mapped) {
        $ent_id = sanitize_key((string) $ent_id);
        if ($ent_id === '') {
            continue;
        }

        $mapped = is_array($mapped) ? $mapped : array();
        $pid = absint($mapped['woo_product_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }

        $ent_cfg = (isset($ent_cfg_by_id[$ent_id]) && is_array($ent_cfg_by_id[$ent_id])) ? $ent_cfg_by_id[$ent_id] : null;
        $elig = vms_ticketing_v2_resolve_eligibility_for_product($pid, $plan_id, is_array($ent_cfg) ? $ent_cfg : null);
        $pool_key = sanitize_key((string) ($elig['pool_key'] ?? ''));
        if ($pool_key === '' || !empty($elig['allow_without_ga'])) {
            continue;
        }

        if (!isset($out[$pool_key]) || !is_array($out[$pool_key])) {
            $out[$pool_key] = array();
        }
        $out[$pool_key][] = $pid;
    }

    foreach ($out as $pool_key => $product_ids) {
        $product_ids = array_values(array_unique(array_filter(array_map('absint', (array) $product_ids))));
        sort($product_ids, SORT_NUMERIC);
        $out[$pool_key] = $product_ids;
    }

    ksort($out, SORT_STRING);
    return $out;
}

function vms_ticketing_v2_current_customer_purchase_context(): array
{
    $user_id = is_user_logged_in() ? absint(get_current_user_id()) : 0;
    $email = '';

    if (function_exists('WC')) {
        $wc = WC();
        if ($wc && isset($wc->customer) && $wc->customer) {
            if (method_exists($wc->customer, 'get_billing_email')) {
                $email = sanitize_email((string) $wc->customer->get_billing_email());
            }
            if ($email === '' && method_exists($wc->customer, 'get_email')) {
                $email = sanitize_email((string) $wc->customer->get_email());
            }
        }
    }

    if ($email === '' && $user_id > 0) {
        $user = get_userdata($user_id);
        if ($user instanceof WP_User) {
            $email = sanitize_email((string) $user->user_email);
        }
    }

    return array(
        'user_id' => $user_id,
        'email' => strtolower($email),
    );
}

function vms_ticketing_v2_customer_order_ids_for_purchase_context(array $customer_ctx): array
{
    $user_id = absint($customer_ctx['user_id'] ?? 0);
    $email = strtolower(sanitize_email((string) ($customer_ctx['email'] ?? '')));
    if (($user_id <= 0 && $email === '') || !function_exists('wc_get_orders')) {
        return array();
    }

    static $cache = array();
    $scope_key = vms_ticketing_v2_request_purchase_cache_scope($customer_ctx);
    $cache_key = $user_id . '|' . $email . '|' . $scope_key;
    if (isset($cache[$cache_key]) && is_array($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $statuses = array('processing', 'completed', 'on-hold');
    $order_ids = array();

    if ($user_id > 0) {
        $order_ids = array_merge($order_ids, (array) wc_get_orders(array(
            'customer_id' => $user_id,
            'status' => $statuses,
            'limit' => -1,
            'return' => 'ids',
        )));
    }

    if ($email !== '') {
        $order_ids = array_merge($order_ids, (array) wc_get_orders(array(
            'billing_email' => $email,
            'status' => $statuses,
            'limit' => -1,
            'return' => 'ids',
        )));
    }

    $order_ids = array_values(array_unique(array_filter(array_map('absint', $order_ids))));
    $cache[$cache_key] = $order_ids;
    return $order_ids;
}

function vms_ticketing_v2_purchased_product_qty_for_customer(array $customer_ctx, array $product_ids): int
{
    $product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
    if (empty($product_ids)) {
        return 0;
    }

    $order_ids = vms_ticketing_v2_customer_order_ids_for_purchase_context($customer_ctx);
    if (empty($order_ids)) {
        return 0;
    }

    $user_id = absint($customer_ctx['user_id'] ?? 0);
    $email = strtolower(sanitize_email((string) ($customer_ctx['email'] ?? '')));
    $scope_key = vms_ticketing_v2_request_purchase_cache_scope($customer_ctx);
    static $cache = array();
    $cache_key = $user_id . '|' . $email . '|' . $scope_key . '|' . implode(',', $product_ids);
    if (isset($cache[$cache_key])) {
        return max(0, absint($cache[$cache_key]));
    }

    static $order_line_cache = array();
    $pid_set = array_fill_keys($product_ids, true);
    $purchased_qty = 0;
    foreach ($order_ids as $order_id) {
        if (!isset($order_line_cache[$order_id]) || !is_array($order_line_cache[$order_id])) {
            $order_line_cache[$order_id] = array();

            $order = wc_get_order($order_id);
            if ($order) {
                foreach ($order->get_items('line_item') as $order_item) {
                    if (!is_object($order_item)) {
                        continue;
                    }

                    $qty = max(0, absint($order_item->get_quantity()));
                    if ($qty <= 0) {
                        continue;
                    }

                    $order_line_cache[$order_id][] = array(
                        'product_id' => absint($order_item->get_product_id()),
                        'variation_id' => absint($order_item->get_variation_id()),
                        'qty' => $qty,
                    );
                }
            }
        }

        foreach ($order_line_cache[$order_id] as $order_line) {
            $item_pid = absint($order_line['product_id'] ?? 0);
            $item_vid = absint($order_line['variation_id'] ?? 0);
            if (($item_pid > 0 && isset($pid_set[$item_pid])) || ($item_vid > 0 && isset($pid_set[$item_vid]))) {
                $purchased_qty += max(0, absint($order_line['qty'] ?? 0));
            }
        }
    }

    $cache[$cache_key] = max(0, absint($purchased_qty));
    return $cache[$cache_key];
}

function vms_ticketing_v2_prior_addon_history_for_plan(int $plan_id, array $cfg = array()): array
{
    $plan_id = absint($plan_id);
    $empty = array(
        'qualifying_qty' => 0,
        'pool_qty_by_key' => array(),
    );
    if ($plan_id <= 0) {
        return $empty;
    }

    $customer_ctx = vms_ticketing_v2_current_customer_purchase_context();
    $user_id = absint($customer_ctx['user_id'] ?? 0);
    $email = strtolower(sanitize_email((string) ($customer_ctx['email'] ?? '')));
    if ($user_id <= 0 && $email === '') {
        return $empty;
    }

    static $cache = array();
    $scope_key = vms_ticketing_v2_request_purchase_cache_scope($customer_ctx);
    $cache_key = $plan_id . '|' . $user_id . '|' . $email . '|' . $scope_key;
    if (isset($cache[$cache_key]) && is_array($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $qualifying_product_ids = vms_ticketing_v2_qualifying_ticket_product_ids_for_plan($plan_id);
    $pool_product_ids_by_key = vms_ticketing_v2_entitlement_product_ids_by_pool_for_plan($plan_id, $cfg);

    $result = $empty;
    if (!empty($qualifying_product_ids)) {
        $result['qualifying_qty'] = vms_ticketing_v2_purchased_product_qty_for_customer($customer_ctx, $qualifying_product_ids);
    }

    foreach ($pool_product_ids_by_key as $pool_key => $product_ids) {
        $pool_key = sanitize_key((string) $pool_key);
        if ($pool_key === '') {
            continue;
        }
        $result['pool_qty_by_key'][$pool_key] = vms_ticketing_v2_purchased_product_qty_for_customer($customer_ctx, (array) $product_ids);
    }

    ksort($result['pool_qty_by_key'], SORT_STRING);
    $cache[$cache_key] = $result;
    return $result;
}

function vms_ticketing_v2_resolve_qualifying_ticket_label(int $plan_id): string
{
    $plan_id = absint($plan_id);
    if ($plan_id <= 0 || !function_exists('vms_ticketing_v2_get_sync')) {
        return __('qualifying tickets', 'backstage-venue-manager');
    }

    $sync = vms_ticketing_v2_get_sync($plan_id);
    $map = (is_array($sync) && isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
    if (empty($map)) {
        return __('qualifying tickets', 'backstage-venue-manager');
    }

    $labels = array();
    $ticket_map = (isset($map['tickets']) && is_array($map['tickets'])) ? $map['tickets'] : array();
    foreach ($ticket_map as $ticket_row) {
        if (!is_array($ticket_row)) {
            continue;
        }

        $counts_toward_unlock = array_key_exists('counts_toward_unlock', $ticket_row)
            ? !empty($ticket_row['counts_toward_unlock'])
            : true;
        if (!$counts_toward_unlock) {
            continue;
        }

        $label = sanitize_text_field((string) ($ticket_row['title'] ?? ''));
        if ($label === '') {
            $mapped_pid = absint($ticket_row['woo_product_id'] ?? 0);
            if ($mapped_pid > 0) {
                $label = sanitize_text_field((string) get_the_title($mapped_pid));
            }
        }
        if ($label !== '') {
            $labels[$label] = true;
        }
    }

    if (empty($labels)) {
        $ga_row = (isset($map['ga']) && is_array($map['ga'])) ? $map['ga'] : array();
        $ga_label = sanitize_text_field((string) ($ga_row['title'] ?? ''));
        if ($ga_label === '') {
            $ga_pid = absint($ga_row['woo_product_id'] ?? 0);
            if ($ga_pid > 0) {
                $ga_label = sanitize_text_field((string) get_the_title($ga_pid));
            }
        }
        if ($ga_label !== '') {
            $labels[$ga_label] = true;
        }
    }

    $label_list = array_keys($labels);
    if (count($label_list) === 1) {
        return (string) $label_list[0];
    }

    return __('qualifying tickets', 'backstage-venue-manager');
}

function vms_ticketing_v2_qualifying_ticket_phrase(string $ticket_label, int $count = 2): string
{
    $count = max(1, absint($count));
    $label = trim($ticket_label);
    if ($label === '') {
        return ($count === 1) ? __('qualifying ticket', 'backstage-venue-manager') : __('qualifying tickets', 'backstage-venue-manager');
    }

    $normalized = function_exists('mb_strtolower') ? mb_strtolower($label) : strtolower($label);
    if ($normalized === 'qualifying ticket' || $normalized === 'qualifying tickets') {
        return ($count === 1) ? __('qualifying ticket', 'backstage-venue-manager') : __('qualifying tickets', 'backstage-venue-manager');
    }

    if (stripos($label, 'ticket') !== false) {
        return $label;
    }

    if ($count === 1) {
        /* translators: %s: human-readable value used in this message. */
        return sprintf(__('%s ticket', 'backstage-venue-manager'), $label);
    }

    /* translators: %s: human-readable value used in this message. */
    return sprintf(__('%s tickets', 'backstage-venue-manager'), $label);
}

function vms_ticketing_v2_enforce_cart_rules(): void {
    if (is_admin() && !wp_doing_ajax()) {
        return;
    }
    if (!function_exists('wc_add_notice')) {
        return;
    }

    $scan = vms_ticketing_v2_cart_scan();
    $ent_lines = $scan['ent_lines'] ?? array();
    if (empty($ent_lines)) {
        return;
    }

    $plan_ids = $scan['plan_ids'] ?? array();
    $plan_ids = array_values(array_unique(array_map('absint', (array) $plan_ids)));

    if (count($plan_ids) > 1) {
        wc_add_notice(__('You cannot purchase reserved items for multiple events in a single order. Please check out separately for each event.', 'backstage-venue-manager'), 'error');
        return;
    }

    $plan_id = (int) ($plan_ids[0] ?? 0);
    if ($plan_id <= 0) {
        wc_add_notice(__('Reserved items in your cart could not be validated. Please remove them and try again.', 'backstage-venue-manager'), 'error');
        return;
    }

    $cfg = array();
    if (function_exists('vms_ticketing_v2_get_config')) {
        $cfg = vms_ticketing_v2_get_config($plan_id);
    }

    // Align sale window: reserved add-ons should not be purchasable when GA tickets are not on sale.
    $is_admin_user = function_exists('current_user_can') ? current_user_can('manage_options') : false;
    if (!vms_ticketing_v2_ga_is_on_sale_now($cfg) && !$is_admin_user) {
        wc_add_notice(__('Reserved add-ons are not available until tickets go on sale.', 'backstage-venue-manager'), 'error');
        return;
    }
    $ga_qty_raw = (int) (($scan['ga_qty_by_plan'][$plan_id] ?? 0));
    $ga_qty = vms_ticketing_v2_effective_ga_qty_for_plan($plan_id, $ga_qty_raw);
    $prior_history = function_exists('vms_ticketing_v2_prior_addon_history_for_plan')
        ? vms_ticketing_v2_prior_addon_history_for_plan($plan_id, is_array($cfg) ? $cfg : array())
        : array('qualifying_qty' => 0, 'pool_qty_by_key' => array());
    $ga_qty += max(0, absint($prior_history['qualifying_qty'] ?? 0));
    $prior_pool_qty_by_key = (isset($prior_history['pool_qty_by_key']) && is_array($prior_history['pool_qty_by_key']))
        ? $prior_history['pool_qty_by_key']
        : array();
    $qualifying_ticket_label = vms_ticketing_v2_resolve_qualifying_ticket_label($plan_id);

    // Shared pool enforcement: limits combined quantities across multiple entitlements when they share the same eligibility pool_key.
    $pool = array();

    foreach ($ent_lines as $line) {
        if (!is_array($line)) {
            continue;
        }

        $product_id = absint($line['product_id'] ?? 0);
        $qty = absint($line['qty'] ?? 0);
        if ($qty < 1) {
            $qty = 1;
        }

        $ent_id = sanitize_key((string) ($line['entitlement_id'] ?? ''));

        $ent_cfg = null;
        if (is_array($cfg) && !empty($cfg) && $ent_id !== '') {
            $ent_cfg = vms_ticketing_v2_find_entitlement_cfg($cfg, $ent_id);
        }

        // If config is present and this entitlement is explicitly disabled, block.
        if (is_array($ent_cfg) && empty($ent_cfg['enabled'])) {
            $label = (string) ($ent_cfg['label'] ?? (string) get_the_title($product_id));
            /* translators: %s: human-readable value used in this message. */
            wc_add_notice(sprintf(__('A reserved item in your cart is not available for this event: “%s”. Please remove it and try again.', 'backstage-venue-manager'), $label), 'error');
            continue;
        }

        $elig = vms_ticketing_v2_resolve_eligibility_for_product($product_id, $plan_id, is_array($ent_cfg) ? $ent_cfg : null);
        $label = (string) ($elig['label'] ?? '');
        if ($label === '') {
            $label = (string) get_the_title($product_id);
        }

        $min_per = absint($elig['min_ga_per_unit'] ?? 0);
        $max_per_order = absint($elig['max_units_per_order'] ?? 0);
        $max_per_ga = absint($elig['max_units_per_ga'] ?? 0);
        $allow_without_ga = !empty($elig['allow_without_ga']);
        $pool_max_total = absint($elig['pool_max_total'] ?? 0);

        $pool_key = sanitize_key((string) ($elig['pool_key'] ?? ''));
        if ($pool_key !== '') {
            if (!isset($pool[$pool_key])) {
                $pool[$pool_key] = array(
                    'qty' => max(0, absint($prior_pool_qty_by_key[$pool_key] ?? 0)),
                    'min' => 0,
                    'hard_max' => 0,
                    'labels' => array(),
                );
            }
            $pool[$pool_key]['qty'] = (int) ($pool[$pool_key]['qty'] ?? 0) + $qty;
            if (!$allow_without_ga && $min_per > 0) {
                $pool[$pool_key]['min'] = max((int) ($pool[$pool_key]['min'] ?? 0), $min_per);
            }
            if ($pool_max_total > 0) {
                $current_hard = (int) ($pool[$pool_key]['hard_max'] ?? 0);
                $pool[$pool_key]['hard_max'] = ($current_hard > 0) ? min($current_hard, $pool_max_total) : $pool_max_total;
            }
            $pool[$pool_key]['labels'][$label] = true;
        }

        if ($max_per_order > 0 && $qty > $max_per_order) {
            /* translators: 1: maximum allowed quantity, 2: ticket label. */
            wc_add_notice(sprintf(__('You can only purchase up to %1$d of “%2$s” per order.', 'backstage-venue-manager'), $max_per_order, $label), 'error');
        }

        if (!$allow_without_ga && $min_per > 0) {
            $required = $min_per * $qty;
            if ($ga_qty < $required) {
                $ticket_phrase = vms_ticketing_v2_qualifying_ticket_phrase($qualifying_ticket_label, $required);
                /* translators: 1: value 1 used in this message, 2: number 2 used in this message, 3: value 3 used in this message, 4: value 4 used in this message. */
                wc_add_notice(sprintf(__('“%1$s” requires at least %2$d %3$s. Add more %3$s or remove this reservation.', 'backstage-venue-manager'), $label, $required, $ticket_phrase), 'error');
            }
        }

        if ($max_per_ga > 0 && $ga_qty > 0) {
            $allowed = $max_per_ga * $ga_qty;
            if ($qty > $allowed) {
                $ticket_phrase = vms_ticketing_v2_qualifying_ticket_phrase($qualifying_ticket_label, max(1, $ga_qty));
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message. */
                wc_add_notice(sprintf(__('“%1$s” exceeds what your current %2$s can support. Add more %2$s or remove this reservation.', 'backstage-venue-manager'), $label, $ticket_phrase), 'error');
            }
        }

        if (!$allow_without_ga && $ga_qty <= 0 && $min_per <= 0) {
            $ticket_phrase = vms_ticketing_v2_qualifying_ticket_phrase($qualifying_ticket_label, 2);
            /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
            wc_add_notice(sprintf(__('Add the required %2$s to reserve “%1$s”, or remove this reservation.', 'backstage-venue-manager'), $label, $ticket_phrase), 'error');
        }
    }

    // Enforce combined pool caps (example: 2 GA tickets qualify for 1 reserved seating add-on total, not one of each type).
    if (!empty($pool) && $ga_qty >= 0) {
        foreach ($pool as $pool_key => $p) {
            if (!is_array($p)) continue;
            $pool_min = (int) ($p['min'] ?? 0);
            $pool_hard_max = (int) ($p['hard_max'] ?? 0);
            $pool_qty = (int) ($p['qty'] ?? 0);
            if ($pool_key === '') continue;

            $allowed = -1;
            if ($pool_min > 0) {
                $allowed = ($ga_qty > 0) ? intdiv($ga_qty, $pool_min) : 0;
            }
            if ($pool_hard_max > 0) {
                $allowed = ($allowed < 0) ? $pool_hard_max : min($allowed, $pool_hard_max);
            }
            if ($allowed >= 0 && $pool_qty > $allowed) {
                $label = ucwords(str_replace('_', ' ', (string) $pool_key));
                if ($pool_min > 0) {
                    $required_total = max(0, $pool_qty * $pool_min);
                    $missing = max(0, $required_total - $ga_qty);
                    if ($missing > 0) {
                        $ticket_phrase = vms_ticketing_v2_qualifying_ticket_phrase($qualifying_ticket_label, $missing);
                        /* translators: 1: number 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message. */
                        wc_add_notice(sprintf(__('Your reserved spots require %1$d more %2$s. Add more %2$s or remove one or more reserved spots.', 'backstage-venue-manager'), $missing, $ticket_phrase), 'error');
                    } else {
                        $ticket_phrase = vms_ticketing_v2_qualifying_ticket_phrase($qualifying_ticket_label, 2);
                        /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                        wc_add_notice(sprintf(__('Your reserved spots currently require more %1$s. Add more %1$s or remove one or more reserved spots.', 'backstage-venue-manager'), $ticket_phrase), 'error');
                    }
                } else {
                    /* translators: 1: add-on pool label, 2: maximum allowed quantity. */
                    wc_add_notice(sprintf(__('Your selected add-ons in “%1$s” exceed the pool limit. Please reduce to %2$d.', 'backstage-venue-manager'), $label, $allowed), 'error');
                }
            }
        }
    }
}

function vms_ticketing_v2_enforce_ticket_visibility_rules(): void
{
    if (is_admin() && !wp_doing_ajax()) {
        return;
    }
    if (!function_exists('wc_add_notice')) {
        return;
    }

    $scan = vms_ticketing_v2_cart_scan();
    $ticket_lines = (isset($scan['ticket_lines']) && is_array($scan['ticket_lines'])) ? $scan['ticket_lines'] : array();
    if (empty($ticket_lines)) {
        return;
    }

    $already_notified = array(
        'login' => false,
        'verified_guest' => false,
        'verified_member' => false,
    );
    $qualification_removed = vms_ticketing_v2_public_ticket_qualification_removed();

    foreach ($ticket_lines as $line) {
        if (!is_array($line)) {
            continue;
        }

        $disabled_ticket_state = function_exists('vms_ticketing_v2_disabled_ticket_config_for_product')
            ? vms_ticketing_v2_disabled_ticket_config_for_product(absint($line['product_id'] ?? 0), absint($line['plan_id'] ?? 0))
            : array('disabled' => false);
        if (!empty($disabled_ticket_state['disabled'])) {
            wc_add_notice(vms_ticketing_v2_disabled_ticket_notice_text($disabled_ticket_state), 'error');
            continue;
        }

        $mode = sanitize_key((string) ($line['visibility_mode'] ?? 'public'));
        if (!in_array($mode, array('public', 'login', 'verified'), true)) {
            $mode = 'public';
        }
        if ($mode === 'public') {
            continue;
        }
        if ($mode === 'verified' && $qualification_removed) {
            continue;
        }

        if ($mode === 'login') {
            if (!is_user_logged_in() && !$already_notified['login']) {
                wc_add_notice(__('Your cart includes tickets that require login. Please log in to continue.', 'backstage-venue-manager'), 'error');
                $already_notified['login'] = true;
            }
            continue;
        }

        $program = sanitize_key((string) ($line['verified_program'] ?? ''));
        $allowed_programs = function_exists('vms_ticketing_v2_normalize_allowed_programs')
            ? vms_ticketing_v2_normalize_allowed_programs($line['allowed_programs'] ?? array(), $program)
            : ($program !== '' ? array($program) : array());
        $allow_direct_grants = function_exists('vms_ticketing_v2_truthy')
            ? vms_ticketing_v2_truthy($line['allow_direct_grants'] ?? false, false)
            : vms_ticketing_v2_meta_truthy($line['allow_direct_grants'] ?? false, false);
        $grant_type = sanitize_key((string) ($line['claim_grant_type'] ?? 'event_ticket_eligibility'));
        if (!in_array($grant_type, vms_ticketing_v2_claim_grant_type_values(), true)) {
            $grant_type = 'event_ticket_eligibility';
        }
        $require_assignee_email = function_exists('vms_ticketing_v2_truthy')
            ? vms_ticketing_v2_truthy($line['require_assignee_email'] ?? true, true)
            : vms_ticketing_v2_meta_truthy($line['require_assignee_email'] ?? true, true);
        $event_id = absint($line['event_id'] ?? 0);
        $ticket_key = sanitize_key((string) ($line['ticket_key'] ?? ''));
        if ($program === '' && !empty($allowed_programs)) {
            $program = (string) $allowed_programs[0];
        }

        $program_label_text = vms_ticketing_v2_claim_program_label_text($allowed_programs, $program);

        if (!is_user_logged_in()) {
            if (!$already_notified['verified_guest']) {
                if (!empty($allowed_programs) && !$allow_direct_grants) {
                    $guest_message = sprintf(
                        /* translators: %s: human-readable value used in this message. */
                        __('Your cart includes tickets that require %s verification. Please log in and submit verification to continue.', 'backstage-venue-manager'),
                        $program_label_text
                    );
                } elseif (empty($allowed_programs) && $allow_direct_grants) {
                    $guest_message = __('Your cart includes tickets that require event-specific account approval. Please log in to continue.', 'backstage-venue-manager');
                } else {
                    $guest_message = __('Your cart includes restricted tickets. Please log in and verify your account to continue.', 'backstage-venue-manager');
                }
                wc_add_notice(
                    $guest_message,
                    'error'
                );
                $already_notified['verified_guest'] = true;
            }
            continue;
        }

        $eligibility = vms_ticketing_v2_resolve_claim_eligibility_for_user(
            (int) get_current_user_id(),
            $event_id,
            absint($line['product_id'] ?? 0),
            $ticket_key,
            $program,
            $allowed_programs,
            $allow_direct_grants,
            $grant_type
        );

        if (empty($eligibility['eligible']) && !$already_notified['verified_member']) {
            $member_message = sanitize_text_field((string) ($eligibility['message'] ?? ''));
            if ($member_message === '') {
                $member_message = sprintf(
                    /* translators: %s: human-readable value used in this message. */
                    __('Your cart includes tickets that require %s verification. Submit your verification to continue.', 'backstage-venue-manager'),
                    $program_label_text
                );
            }
            wc_add_notice(
                $member_message,
                'error'
            );
            $already_notified['verified_member'] = true;
        }
    }
}

function vms_ticketing_v2_verified_ticket_program_label(string $program): string
{
    $program = sanitize_key($program);
    if ($program === '') {
        return __('Verified', 'backstage-venue-manager');
    }
    if (function_exists('vms_ticketing_verification_program_label')) {
        return vms_ticketing_verification_program_label($program);
    }
    return ucwords(str_replace('_', ' ', $program));
}

function vms_ticketing_v2_add_limit_notice_once(string $message): void
{
    if ($message === '' || !function_exists('wc_add_notice')) {
        return;
    }
    if (function_exists('wc_has_notice') && wc_has_notice($message, 'error')) {
        return;
    }
    wc_add_notice($message, 'error');
}

function vms_ticketing_v2_normalize_ticket_ratio_rule(array $ticket_row): array
{
    $enabled = !empty($ticket_row['ratio_rule_enabled']);
    $max_per = max(0, absint($ticket_row['ratio_rule_max_per_qualifying'] ?? 0));
    if (!$enabled || $max_per <= 0) {
        $enabled = false;
        $max_per = 0;
    }

    $mode = sanitize_key((string) ($ticket_row['ratio_rule_qualifier_mode'] ?? 'counts_toward_unlock'));
    if (!in_array($mode, array('counts_toward_unlock'), true)) {
        $mode = 'counts_toward_unlock';
    }

    $group = sanitize_title((string) ($ticket_row['ratio_rule_group'] ?? ''));
    if (!$enabled) {
        $group = '';
    }

    return array(
        'enabled' => $enabled,
        'max_per_qualifying' => $max_per,
        'qualifier_mode' => $mode,
        'group' => $group,
    );
}

function vms_ticketing_v2_config_ticket_rows_by_key(array $cfg): array
{
    $out = array();
    $tickets = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? $cfg['tickets'] : array();
    foreach ($tickets as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (array_key_exists('enabled', $row) && empty($row['enabled'])) {
            continue;
        }
        $key = sanitize_key((string) ($row['key'] ?? ($row['ticket_key'] ?? '')));
        if ($key === '') {
            continue;
        }
        $out[$key] = $row;
    }
    return $out;
}

function vms_ticketing_v2_ticket_key_for_product(int $product_id, int $plan_id = 0): string
{
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    if ($product_id <= 0) {
        return '';
    }

    $key = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, '_vms_ticket_key'));
    if ($key !== '') {
        return $key;
    }

    if (function_exists('vms_ticketing_v2_product_meta_key')) {
        $key = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, vms_ticketing_v2_product_meta_key('ticketing_ticket_key')));
        if ($key !== '') {
            return $key;
        }
    }

    if ($plan_id > 0 && function_exists('vms_ticketing_v2_get_sync')) {
        $sync = vms_ticketing_v2_get_sync($plan_id);
        $map = (is_array($sync) && isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
        $ticket_map = (isset($map['tickets']) && is_array($map['tickets'])) ? $map['tickets'] : array();
        foreach ($ticket_map as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mapped_pid = absint($row['woo_product_id'] ?? 0);
            if ($mapped_pid > 0 && vms_ticketing_v2_pid_matches_mapped($product_id, $mapped_pid)) {
                return sanitize_key((string) ($row['key'] ?? ($row['ticket_key'] ?? '')));
            }
        }
    }

    return '';
}

function vms_ticketing_v2_ratio_group_display_label(string $group): string
{
    $group = trim($group);
    if ($group === '') {
        return __('This ticket group', 'backstage-venue-manager');
    }
    $label = trim(preg_replace('/[-_]+/', ' ', $group));
    if ($label === '') {
        return __('This ticket group', 'backstage-venue-manager');
    }
    return ucwords($label);
}

function vms_ticketing_v2_collect_ticket_ratio_violations(int $plan_id, array $cfg = array(), ?array $scan = null, array $request_adjustments = array()): array
{
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array();
    }
    if (empty($cfg) && function_exists('vms_ticketing_v2_get_config')) {
        $cfg = vms_ticketing_v2_get_config($plan_id);
    }
    if (empty($cfg) || !is_array($cfg)) {
        return array();
    }

    $rows_by_key = vms_ticketing_v2_config_ticket_rows_by_key($cfg);
    if (empty($rows_by_key)) {
        return array();
    }
    if ($scan === null) {
        $scan = vms_ticketing_v2_cart_scan();
    }

    $qty_by_key = array();
    $line_counts_flags = array();
    $ticket_lines = (isset($scan['ticket_lines']) && is_array($scan['ticket_lines'])) ? $scan['ticket_lines'] : array();
    foreach ($ticket_lines as $line) {
        if (!is_array($line) || absint($line['plan_id'] ?? 0) !== $plan_id) {
            continue;
        }
        $product_id = absint($line['product_id'] ?? 0);
        $key = sanitize_key((string) ($line['ticket_key'] ?? ''));
        if ($key === '' && $product_id > 0) {
            $key = vms_ticketing_v2_ticket_key_for_product($product_id, $plan_id);
        }
        if ($key === '') {
            continue;
        }
        $qty = max(0, absint($line['qty'] ?? 0));
        if ($qty <= 0) {
            continue;
        }
        $qty_by_key[$key] = absint($qty_by_key[$key] ?? 0) + $qty;
        if (!isset($line_counts_flags[$key])) {
            $line_counts_flags[$key] = !empty($line['counts_toward_unlock']);
        }
    }

    foreach ($request_adjustments as $key => $qty_delta) {
        $key = sanitize_key((string) $key);
        if ($key === '') {
            continue;
        }
        $qty_by_key[$key] = max(0, absint($qty_by_key[$key] ?? 0) + absint($qty_delta));
    }

    $limited_groups = array();
    foreach ($rows_by_key as $limited_key => $row) {
        $rule = vms_ticketing_v2_normalize_ticket_ratio_rule($row);
        if (empty($rule['enabled']) || absint($rule['max_per_qualifying'] ?? 0) <= 0) {
            continue;
        }

        $shared_group = sanitize_title((string) ($rule['group'] ?? ''));
        $group_key = $shared_group !== '' ? 'shared:' . $shared_group : 'ticket:' . $limited_key;
        if (!isset($limited_groups[$group_key])) {
            $limited_groups[$group_key] = array(
                'shared_group' => $shared_group,
                'ticket_keys' => array(),
                'labels' => array(),
                'max_per_qualifying' => absint($rule['max_per_qualifying']),
                'qualifier_mode' => (string) ($rule['qualifier_mode'] ?? 'counts_toward_unlock'),
            );
        }
        $limited_groups[$group_key]['ticket_keys'][] = $limited_key;
        $limited_groups[$group_key]['max_per_qualifying'] = min(
            absint($limited_groups[$group_key]['max_per_qualifying']),
            absint($rule['max_per_qualifying'])
        );
        $label = sanitize_text_field((string) ($row['title'] ?? ($row['label'] ?? ($row['name'] ?? ''))));
        if ($label !== '') {
            $limited_groups[$group_key]['labels'][] = $label;
        }
    }

    if (empty($limited_groups)) {
        return array();
    }

    $violations = array();
    foreach ($limited_groups as $group_key => $group) {
        $ticket_keys = array_values(array_unique(array_map('sanitize_key', (array) ($group['ticket_keys'] ?? array()))));
        if (empty($ticket_keys)) {
            continue;
        }

        $selected_qty = 0;
        foreach ($ticket_keys as $ticket_key) {
            $selected_qty += absint($qty_by_key[$ticket_key] ?? 0);
        }
        if ($selected_qty <= 0) {
            continue;
        }

        $limited_lookup = array_fill_keys($ticket_keys, true);
        $qualifying_qty = 0;
        foreach ($rows_by_key as $candidate_key => $candidate_row) {
            if (isset($limited_lookup[$candidate_key])) {
                continue;
            }
            $candidate_qty = absint($qty_by_key[$candidate_key] ?? 0);
            if ($candidate_qty <= 0) {
                continue;
            }
            $counts = array_key_exists('counts_toward_unlock', $candidate_row)
                ? !empty($candidate_row['counts_toward_unlock'])
                : (isset($line_counts_flags[$candidate_key]) ? !empty($line_counts_flags[$candidate_key]) : true);
            if ($counts) {
                $qualifying_qty += $candidate_qty;
            }
        }

        $max_per = absint($group['max_per_qualifying'] ?? 0);
        if ($max_per <= 0) {
            continue;
        }
        $allowed = $qualifying_qty * $max_per;
        if ($selected_qty <= $allowed) {
            continue;
        }

        $shared_group = sanitize_title((string) ($group['shared_group'] ?? ''));
        if ($shared_group !== '') {
            $ticket_label = vms_ticketing_v2_ratio_group_display_label($shared_group);
        } else {
            $labels = array_values(array_unique(array_filter(array_map('strval', (array) ($group['labels'] ?? array())))));
            $ticket_label = !empty($labels) ? (string) $labels[0] : __('This ticket', 'backstage-venue-manager');
        }

        $qualifying_label = vms_ticketing_v2_resolve_qualifying_ticket_label($plan_id);
        $qualifying_phrase = vms_ticketing_v2_qualifying_ticket_phrase($qualifying_label, max(1, $qualifying_qty));

        if ($qualifying_qty <= 0) {
            $message = sprintf(
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message. */
                __('%1$s requires at least one qualifying ticket in the cart. Add a %2$s or reduce the %1$s quantity.', 'backstage-venue-manager'),
                $ticket_label,
                $qualifying_phrase
            );
        } else {
            $message = sprintf(
                /* translators: 1: value 1 used in this message, 2: number 2 used in this message, 3: value 3 used in this message, 4: number 4 used in this message, 5: number 5 used in this message. */
                __('%1$s is limited to %2$d total per %3$s. You selected %4$d; your current cart allows %5$d.', 'backstage-venue-manager'),
                $ticket_label,
                $max_per,
                $qualifying_phrase,
                $selected_qty,
                $allowed
            );
        }

        $violations[] = array(
            'ticket_key' => (string) ($ticket_keys[0] ?? ''),
            'ticket_keys' => $ticket_keys,
            'ratio_rule_group' => $shared_group,
            'ticket_label' => $ticket_label,
            'selected_qty' => $selected_qty,
            'qualifying_qty' => $qualifying_qty,
            'max_per_qualifying' => $max_per,
            'allowed_qty' => $allowed,
            'message' => $message,
        );
    }

    return $violations;
}

function vms_ticketing_v2_enforce_ticket_ratio_rules_in_cart(): void
{
    if (!function_exists('wc_add_notice') || !function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        return;
    }

    $scan = vms_ticketing_v2_cart_scan();
    $plan_ids = (isset($scan['plan_ids']) && is_array($scan['plan_ids'])) ? $scan['plan_ids'] : array();
    foreach ($plan_ids as $plan_id) {
        $violations = vms_ticketing_v2_collect_ticket_ratio_violations(absint($plan_id), array(), $scan);
        foreach ($violations as $violation) {
            vms_ticketing_v2_add_limit_notice_once((string) ($violation['message'] ?? ''));
        }
    }
}

function vms_ticketing_v2_validate_ticket_ratio_for_add(int $product_id, int $plan_id, int $request_qty): bool
{
    if (!empty($GLOBALS['bvmgr_ticketing_v2_atomic_add_in_progress'])) {
        return true;
    }
    if (!function_exists('wc_add_notice')) {
        return true;
    }
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    $request_qty = max(0, absint($request_qty));
    if ($product_id <= 0 || $plan_id <= 0 || $request_qty <= 0) {
        return true;
    }

    $ticket_key = vms_ticketing_v2_ticket_key_for_product($product_id, $plan_id);
    if ($ticket_key === '') {
        return true;
    }

    $violations = vms_ticketing_v2_collect_ticket_ratio_violations(
        $plan_id,
        array(),
        null,
        array($ticket_key => $request_qty)
    );
    if (empty($violations)) {
        return true;
    }

    foreach ($violations as $violation) {
        vms_ticketing_v2_add_limit_notice_once((string) ($violation['message'] ?? ''));
    }
    return false;
}

function vms_ticketing_v2_public_ticket_qualification_removed(): bool
{
    return false;
}


/**
 * Resolve the Event Plan that owns a ticket product without trusting only one marker.
 * Public ticket products may be legacy/adopted TEC products where VMS markers were
 * incomplete, so this helper mirrors the cart/add-to-cart fallbacks in one place.
 */
function vms_ticketing_v2_resolve_plan_id_for_ticket_product(int $product_id): int
{
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return 0;
    }

    $plan_id = 0;
    if (function_exists('vms_ticketing_v2_product_meta_key')) {
        $plan_id = absint(vms_ticketing_v2_meta_get($product_id, vms_ticketing_v2_product_meta_key('event_plan_id')));
    }

    if ($plan_id <= 0) {
        $tec_event_id = absint(vms_ticketing_v2_meta_get($product_id, '_tribe_wooticket_for_event'));
        if ($tec_event_id <= 0 && function_exists('vms_ticketing_v2_product_meta_key')) {
            $tec_event_id = absint(vms_ticketing_v2_meta_get($product_id, vms_ticketing_v2_product_meta_key('tec_event_id')));
        }
        if ($tec_event_id > 0 && function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id')) {
            $plan_id = absint(bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id));
        }
    }

    return max(0, $plan_id);
}

/**
 * Find the last committed sync-map row for a public ticket product.
 *
 * This is intentionally based on the sync map, not the editable config draft. The
 * sync map represents the last Ticket Push / Commit that actually touched public
 * Woo/TEC products, so it is the safest public runtime source while unsynced
 * config edits are pending.
 */
function vms_ticketing_v2_sync_ticket_row_for_product(int $product_id, int $plan_id = 0): array
{
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    if ($product_id <= 0 || !function_exists('vms_ticketing_v2_get_sync')) {
        return array();
    }

    if ($plan_id <= 0) {
        $plan_id = vms_ticketing_v2_resolve_plan_id_for_ticket_product($product_id);
    }
    if ($plan_id <= 0) {
        return array();
    }

    $sync = vms_ticketing_v2_get_sync($plan_id);
    $map = (is_array($sync) && isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();

    $ticket_rows = (isset($map['tickets']) && is_array($map['tickets'])) ? $map['tickets'] : array();
    foreach ($ticket_rows as $ticket_key => $row) {
        if (!is_array($row)) {
            continue;
        }
        $mapped_pid = absint($row['woo_product_id'] ?? 0);
        if ($mapped_pid > 0 && vms_ticketing_v2_pid_matches_mapped($product_id, $mapped_pid)) {
            $row['ticket_key'] = sanitize_key((string) ($row['ticket_key'] ?? $ticket_key));
            $row['plan_id'] = $plan_id;
            return $row;
        }
    }

    $ga_row = (isset($map['ga']) && is_array($map['ga'])) ? $map['ga'] : array();
    $mapped_ga_pid = absint($ga_row['woo_product_id'] ?? 0);
    if ($mapped_ga_pid > 0 && vms_ticketing_v2_pid_matches_mapped($product_id, $mapped_ga_pid)) {
        $ga_row['ticket_key'] = sanitize_key((string) ($ga_row['ticket_key'] ?? 'ga'));
        $ga_row['plan_id'] = $plan_id;
        return $ga_row;
    }

    return array();
}

/**
 * Return the disabled config row for a still-reachable public ticket product.
 *
 * Save Config is intentionally not supposed to mutate public Woo/TEC products.
 * However, if an operator disables a qualified/free ticket in config but has not
 * pushed the ticket changes yet, the old public product can still be reachable.
 * In that pending window, the safest behavior is to block purchase server-side
 * instead of treating the product as ordinary/free public admission.
 */
function vms_ticketing_v2_disabled_ticket_config_for_product(int $product_id, int $plan_id = 0): array
{
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    if ($product_id <= 0 || !function_exists('vms_ticketing_v2_get_config')) {
        return array('disabled' => false);
    }

    if ($plan_id <= 0) {
        $plan_id = vms_ticketing_v2_resolve_plan_id_for_ticket_product($product_id);
    }
    if ($plan_id <= 0) {
        return array('disabled' => false);
    }

    $sync_row = vms_ticketing_v2_sync_ticket_row_for_product($product_id, $plan_id);
    $ticket_key = sanitize_key((string) ($sync_row['ticket_key'] ?? ''));
    if ($ticket_key === '') {
        $ticket_key_meta = function_exists('vms_ticketing_v2_product_meta_key')
            ? vms_ticketing_v2_product_meta_key('ticketing_ticket_key')
            : '_vms_ticketing_ticket_key';
        $ticket_key = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, $ticket_key_meta));
    }
    if ($ticket_key === '') {
        $ticket_key = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, '_vms_ticket_key'));
    }
    if ($ticket_key === '') {
        return array('disabled' => false);
    }

    $cfg = vms_ticketing_v2_get_config($plan_id);
    $tickets = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? $cfg['tickets'] : array();
    foreach ($tickets as $row) {
        if (!is_array($row)) {
            continue;
        }
        $row_key = sanitize_key((string) ($row['ticket_key'] ?? $row['key'] ?? ''));
        if ($row_key === '' || $row_key !== $ticket_key) {
            continue;
        }
        if (!array_key_exists('enabled', $row) || !empty($row['enabled'])) {
            return array('disabled' => false);
        }

        return array(
            'disabled' => true,
            'plan_id' => $plan_id,
            'ticket_key' => $ticket_key,
            'label' => vms_ticketing_v2_sanitize_plain_text_label((string) ($row['title'] ?? get_the_title($product_id))),
            'product_id' => $product_id,
        );
    }

    return array('disabled' => false);
}


/**
 * Public runtime IDs for saved-config disabled tickets whose last-pushed products may still exist.
 *
 * Save Config can disable a row before Commit/Push retires the Woo/TEC ticket. The UI needs
 * the same fail-closed knowledge as add-to-cart validation so it can hide/neutralize stale
 * public controls instead of letting customers select a now-disabled free/qualified ticket.
 *
 * @return array{product_ids:array<int,int>,map:array<string,array<string,mixed>>}
 */
function vms_ticketing_v2_disabled_ticket_products_for_plan(int $plan_id): array
{
    $plan_id = absint($plan_id);
    $out = array(
        'product_ids' => array(),
        'map' => array(),
    );

    if ($plan_id <= 0 || !function_exists('vms_ticketing_v2_get_config') || !function_exists('vms_ticketing_v2_get_sync')) {
        return $out;
    }

    $cfg = vms_ticketing_v2_get_config($plan_id);
    $tickets = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? $cfg['tickets'] : array();
    if (empty($tickets)) {
        return $out;
    }

    $sync = vms_ticketing_v2_get_sync($plan_id);
    $map = (is_array($sync) && isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
    $ticket_map = (isset($map['tickets']) && is_array($map['tickets'])) ? $map['tickets'] : array();
    $legacy_ga_row = (isset($map['ga']) && is_array($map['ga'])) ? $map['ga'] : array();

    $enabled_keys = array();
    foreach ($tickets as $row) {
        if (!is_array($row)) {
            continue;
        }
        $row_key = sanitize_key((string) ($row['ticket_key'] ?? $row['key'] ?? ''));
        if ($row_key !== '' && (!array_key_exists('enabled', $row) || !empty($row['enabled']))) {
            $enabled_keys[$row_key] = true;
        }
    }

    foreach ($tickets as $idx => $row) {
        if (!is_array($row)) {
            continue;
        }
        if (!array_key_exists('enabled', $row) || !empty($row['enabled'])) {
            continue;
        }

        $ticket_key = sanitize_key((string) ($row['ticket_key'] ?? $row['key'] ?? ''));
        if ($ticket_key === '') {
            continue;
        }

        $sync_row = (isset($ticket_map[$ticket_key]) && is_array($ticket_map[$ticket_key])) ? $ticket_map[$ticket_key] : array();
        $label_for_legacy_match = vms_ticketing_v2_sanitize_plain_text_label((string) ($row['title'] ?? ($sync_row['title'] ?? $ticket_key)));
        $can_claim_legacy_ga = false;
        if (!empty($legacy_ga_row)) {
            if (function_exists('vms_ticketing_v2_should_apply_legacy_ga_map_to_ticket')) {
                $can_claim_legacy_ga = vms_ticketing_v2_should_apply_legacy_ga_map_to_ticket($ticket_key, $label_for_legacy_match);
            } else {
                $legacy_match_label = strtolower(trim((string) preg_replace('/\s+/u', ' ', $label_for_legacy_match)));
                $has_specialized_label = ($legacy_match_label !== '' && preg_match('/\b(early|advance|pre[-\s]?sale|presale|vip|child|children|kid|kids|veteran|police|fire|emt|nurse|teacher|school)\b/u', $legacy_match_label));
                $can_claim_legacy_ga = !$has_specialized_label && (
                    in_array($ticket_key, array('ga', 'general_admission', 'general-admission'), true)
                    || in_array($legacy_match_label, array('general admission', 'ga admission', 'general admission ticket'), true)
                );
            }
        }
        // Older single-GA sync maps used map.ga instead of map.tickets. Only a
        // row that still clearly represents the real GA ticket may inherit that
        // legacy map. Do not let a disabled first row such as Early GA hide the
        // live General Admission product on the public page.
        if (empty($sync_row) && $can_claim_legacy_ga) {
            $sync_row = $legacy_ga_row;
        }
        if (empty($sync_row)) {
            continue;
        }

        $label = vms_ticketing_v2_sanitize_plain_text_label((string) ($row['title'] ?? ($sync_row['title'] ?? __('this ticket', 'backstage-venue-manager'))));
        if ($label === '') {
            $label = __('this ticket', 'backstage-venue-manager');
        }

        $ids = array(
            absint($sync_row['woo_product_id'] ?? 0),
            absint($sync_row['tec_ticket_id'] ?? 0),
        );

        $woo_product_id = absint($sync_row['woo_product_id'] ?? 0);
        if ($woo_product_id > 0 && function_exists('wc_get_product')) {
            $product = wc_get_product($woo_product_id);
            if ($product && is_callable(array($product, 'get_children'))) {
                foreach ((array) $product->get_children() as $child_id) {
                    $ids[] = absint($child_id);
                }
            }
        }

        foreach ($ids as $id) {
            $id = absint($id);
            if ($id <= 0) {
                continue;
            }
            $out['product_ids'][$id] = $id;
            $out['map'][(string) $id] = array(
                'product_id' => $id,
                'plan_id' => $plan_id,
                'ticket_key' => $ticket_key,
                'label' => $label,
                'pending_sync' => 1,
                'enabled_ticket_keys' => array_keys($enabled_keys),
            );
        }
    }

    $out['product_ids'] = array_values($out['product_ids']);
    ksort($out['map'], SORT_NATURAL);

    return $out;
}

function vms_ticketing_v2_disabled_ticket_notice_text(array $state): string
{
    $label = trim((string) ($state['label'] ?? ''));
    if ($label === '') {
        $label = __('this ticket', 'backstage-venue-manager');
    }

    return sprintf(
        /* translators: %s: human-readable value used in this message. */
        __('"%s" is no longer available for this event. Please remove it from your cart or refresh the event page.', 'backstage-venue-manager'),
        $label
    );
}

function vms_ticketing_v2_resolve_verified_ticket_context(int $product_id): array
{
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return array(
            'visibility_mode' => 'public',
            'program' => '',
            'allowed_programs' => array(),
            'allow_direct_grants' => false,
            'claim_grant_type' => 'event_ticket_eligibility',
            'claims_per_assignee' => 1,
            'require_assignee_email' => true,
            'event_id' => 0,
            'ticket_key' => '',
            'ticket_max_qty' => 0,
        );
    }

    $visibility_key = function_exists('vms_ticketing_v2_product_meta_key')
        ? vms_ticketing_v2_product_meta_key('ticketing_visibility_mode')
        : '_vms_ticketing_visibility_mode';
    $program_key = function_exists('vms_ticketing_v2_product_meta_key')
        ? vms_ticketing_v2_product_meta_key('ticketing_verified_program')
        : '_vms_ticketing_verified_program';
    $allowed_programs_key = function_exists('vms_ticketing_v2_product_meta_key')
        ? vms_ticketing_v2_product_meta_key('ticketing_allowed_programs')
        : '_vms_ticketing_allowed_programs';
    $allow_direct_grants_key = function_exists('vms_ticketing_v2_product_meta_key')
        ? vms_ticketing_v2_product_meta_key('ticketing_allow_direct_grants')
        : '_vms_ticketing_allow_direct_grants';
    $claim_grant_type_key = function_exists('vms_ticketing_v2_product_meta_key')
        ? vms_ticketing_v2_product_meta_key('ticketing_claim_grant_type')
        : '_vms_ticketing_claim_grant_type';
    $claims_per_assignee_key = function_exists('vms_ticketing_v2_product_meta_key')
        ? vms_ticketing_v2_product_meta_key('ticketing_claims_per_assignee')
        : '_vms_ticketing_claims_per_assignee';
    $require_assignee_email_key = function_exists('vms_ticketing_v2_product_meta_key')
        ? vms_ticketing_v2_product_meta_key('ticketing_require_assignee_email')
        : '_vms_ticketing_require_assignee_email';
    $ticket_key_meta = function_exists('vms_ticketing_v2_product_meta_key')
        ? vms_ticketing_v2_product_meta_key('ticketing_ticket_key')
        : '_vms_ticketing_ticket_key';
    $max_qty_key = function_exists('vms_ticketing_v2_product_meta_key')
        ? vms_ticketing_v2_product_meta_key('ticketing_max_qty_per_order')
        : '_vms_ticketing_max_qty_per_order';

    $visibility_mode = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, $visibility_key));
    if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
        $visibility_mode = 'public';
    }

    $program = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, $program_key));
    $allowed_programs = function_exists('vms_ticketing_v2_normalize_allowed_programs')
        ? vms_ticketing_v2_normalize_allowed_programs(vms_ticketing_v2_meta_get($product_id, $allowed_programs_key), $program)
        : ($program !== '' ? array($program) : array());
    $allow_direct_grants = function_exists('vms_ticketing_v2_truthy')
        ? vms_ticketing_v2_truthy(vms_ticketing_v2_meta_get($product_id, $allow_direct_grants_key), false)
        : vms_ticketing_v2_meta_truthy(vms_ticketing_v2_meta_get($product_id, $allow_direct_grants_key), false);
    $claim_grant_type = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, $claim_grant_type_key));
    if (!in_array($claim_grant_type, vms_ticketing_v2_claim_grant_type_values(), true)) {
        $claim_grant_type = 'event_ticket_eligibility';
    }
    $claims_per_assignee = max(0, absint(vms_ticketing_v2_meta_get($product_id, $claims_per_assignee_key)));
    if ($claims_per_assignee <= 0) {
        $claims_per_assignee = 1;
    }
    $require_assignee_email = function_exists('vms_ticketing_v2_truthy')
        ? vms_ticketing_v2_truthy(vms_ticketing_v2_meta_get($product_id, $require_assignee_email_key), true)
        : vms_ticketing_v2_meta_truthy(vms_ticketing_v2_meta_get($product_id, $require_assignee_email_key), true);
    if ($visibility_mode !== 'verified') {
        $program = '';
        $allowed_programs = array();
        $allow_direct_grants = false;
        $claim_grant_type = 'event_ticket_eligibility';
        $claims_per_assignee = 1;
        $require_assignee_email = true;
    } elseif ($program === '' && !empty($allowed_programs)) {
        $program = (string) $allowed_programs[0];
    }

    $event_id = absint(vms_ticketing_v2_meta_get($product_id, '_vms_ticket_event_id'));
    if ($event_id <= 0) {
        $event_id = absint(vms_ticketing_v2_meta_get($product_id, '_tribe_wooticket_for_event'));
    }
    if ($event_id <= 0) {
        $tec_meta_key = function_exists('vms_ticketing_v2_product_meta_key')
            ? vms_ticketing_v2_product_meta_key('tec_event_id')
            : '_vms_tec_event_id';
        $event_id = absint(vms_ticketing_v2_meta_get($product_id, $tec_meta_key));
    }
    $ticket_key = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, '_vms_ticket_key'));
    if ($ticket_key === '') {
        $ticket_key = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, $ticket_key_meta));
    }

    $ticket_max_qty = max(0, absint(vms_ticketing_v2_meta_get($product_id, $max_qty_key)));

    // Safety fallback for legacy/adopted products whose runtime product meta may be
    // incomplete. The sync map reflects the last pushed public ticket state, while
    // saved config may already contain unpushed draft edits. Never let missing
    // product meta silently downgrade a last-pushed verified/login ticket to public.
    $sync_ticket_row = function_exists('vms_ticketing_v2_sync_ticket_row_for_product')
        ? vms_ticketing_v2_sync_ticket_row_for_product($product_id, 0)
        : array();
    if (is_array($sync_ticket_row) && !empty($sync_ticket_row)) {
        if ($ticket_key === '') {
            $ticket_key = sanitize_key((string) ($sync_ticket_row['ticket_key'] ?? ''));
        }

        $sync_visibility_mode = sanitize_key((string) ($sync_ticket_row['visibility_mode'] ?? ''));
        if (!in_array($sync_visibility_mode, array('public', 'login', 'verified'), true)) {
            $sync_visibility_mode = '';
        }

        $meta_looks_unprotected = (
            $visibility_mode === 'public'
            && $program === ''
            && empty($allowed_programs)
            && !$allow_direct_grants
        );

        if ($meta_looks_unprotected && in_array($sync_visibility_mode, array('login', 'verified'), true)) {
            $visibility_mode = $sync_visibility_mode;
            $program = sanitize_key((string) ($sync_ticket_row['verified_program'] ?? ''));
            $allowed_programs = function_exists('vms_ticketing_v2_normalize_allowed_programs')
                ? vms_ticketing_v2_normalize_allowed_programs($sync_ticket_row['allowed_programs'] ?? array(), $program)
                : ($program !== '' ? array($program) : array());
            $allow_direct_grants = function_exists('vms_ticketing_v2_truthy')
                ? vms_ticketing_v2_truthy($sync_ticket_row['allow_direct_grants'] ?? false, false)
                : vms_ticketing_v2_meta_truthy($sync_ticket_row['allow_direct_grants'] ?? false, false);
            $claim_grant_type = sanitize_key((string) ($sync_ticket_row['claim_grant_type'] ?? 'event_ticket_eligibility'));
            if (!in_array($claim_grant_type, vms_ticketing_v2_claim_grant_type_values(), true)) {
                $claim_grant_type = 'event_ticket_eligibility';
            }
            $claims_per_assignee = max(0, absint($sync_ticket_row['claims_per_assignee'] ?? 1));
            if ($claims_per_assignee <= 0) {
                $claims_per_assignee = 1;
            }
            $require_assignee_email = function_exists('vms_ticketing_v2_truthy')
                ? vms_ticketing_v2_truthy($sync_ticket_row['require_assignee_email'] ?? true, true)
                : vms_ticketing_v2_meta_truthy($sync_ticket_row['require_assignee_email'] ?? true, true);
            if ($program === '' && !empty($allowed_programs)) {
                $program = (string) $allowed_programs[0];
            }
        }

        if ($ticket_max_qty <= 0 && array_key_exists('max_qty_per_order', $sync_ticket_row)) {
            $ticket_max_qty = max(0, absint($sync_ticket_row['max_qty_per_order']));
        }
    }

    return array(
        'visibility_mode' => $visibility_mode,
        'program' => $program,
        'allowed_programs' => $allowed_programs,
        'allow_direct_grants' => $allow_direct_grants ? 1 : 0,
        'claim_grant_type' => $claim_grant_type,
        'claims_per_assignee' => $claims_per_assignee,
        'require_assignee_email' => $require_assignee_email ? 1 : 0,
        'event_id' => $event_id,
        'ticket_key' => $ticket_key,
        'ticket_max_qty' => $ticket_max_qty,
    );
}

function vms_ticketing_v2_cart_verified_qty_for_event_program(int $event_id, string $program): int
{
    $event_id = absint($event_id);
    $program = sanitize_key($program);
    if ($event_id <= 0 || $program === '' || !function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        return 0;
    }

    $qty = 0;
    foreach ((array) WC()->cart->get_cart() as $item) {
        if (!is_array($item)) {
            continue;
        }
        $item_pid = absint($item['variation_id'] ?? 0);
        if ($item_pid <= 0) {
            $item_pid = absint($item['product_id'] ?? 0);
        }
        if ($item_pid <= 0) {
            continue;
        }

        $ctx = vms_ticketing_v2_resolve_verified_ticket_context($item_pid);
        if (($ctx['visibility_mode'] ?? '') !== 'verified') {
            continue;
        }
        if (sanitize_key((string) ($ctx['program'] ?? '')) !== $program) {
            continue;
        }
        if (absint($ctx['event_id'] ?? 0) !== $event_id) {
            continue;
        }

        $qty += max(0, absint($item['quantity'] ?? 0));
    }

    return max(0, $qty);
}

function vms_ticketing_v2_resolve_verified_ticket_limit(int $user_id, string $program, int $ticket_max_qty): int
{
    $ticket_max_qty = max(0, absint($ticket_max_qty));
    if (function_exists('vms_ticketing_verification_resolve_ticket_limit')) {
        return max(0, absint(vms_ticketing_verification_resolve_ticket_limit($user_id, $program, $ticket_max_qty)));
    }
    if (function_exists('vms_ticketing_verification_get_program_default_allowance')) {
        $allowance = max(0, absint(vms_ticketing_verification_get_program_default_allowance($program)));
        if ($ticket_max_qty <= 0) {
            return $allowance;
        }
        if ($allowance <= 0) {
            return $ticket_max_qty;
        }
        return min($allowance, $ticket_max_qty);
    }
    return $ticket_max_qty;
}


function vms_ticketing_v2_should_enforce_ticket_max_qty(array $ctx): bool
{
    $limit = max(0, absint($ctx['limit'] ?? ($ctx['ticket_max_qty'] ?? 0)));
    if ($limit <= 0) {
        return false;
    }

    $visibility_mode = sanitize_key((string) ($ctx['visibility_mode'] ?? 'public'));
    if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
        $visibility_mode = 'public';
    }

    // Public/general admission quantities should be governed by inventory/capacity, not
    // VMS' qualified-ticket/customer allowance cap. This keeps one buyer from being
    // blocked when purchasing a normal group order while preserving limits for tickets
    // that actually need login/verification/guest-assignment controls.
    $should_enforce = ($visibility_mode !== 'public');

    /**
     * Allow a site-specific override if a venue intentionally wants public tickets to
     * have a VMS per-customer cap. Default is false for public tickets because the
     * Woo/TEC product stock already protects event capacity.
     *
     * @param bool  $should_enforce Whether VMS should enforce the max quantity cap.
     * @param array $ctx            Ticket max/visibility context.
     */
    return (bool) apply_filters('vms_ticketing_v2_should_enforce_ticket_max_qty', $should_enforce, $ctx);
}

function vms_ticketing_v2_enforce_verified_ticket_limit_for_add(int $product_id, int $request_qty): bool
{
    return vms_ticketing_v2_enforce_ticket_max_qty_for_add($product_id, $request_qty);
}

function vms_ticketing_v2_enforce_verified_ticket_limits_in_cart(): void
{
    // Enforced by vms_ticketing_v2_enforce_ticket_max_qtys_in_cart().
}

function vms_ticketing_v2_resolve_ticket_max_context(int $product_id): array
{
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return array(
            'event_id' => 0,
            'ticket_key' => '',
            'visibility_mode' => 'public',
            'program' => '',
            'allowed_programs' => array(),
            'allow_direct_grants' => false,
            'claim_grant_type' => 'event_ticket_eligibility',
            'claims_per_assignee' => 1,
            'require_assignee_email' => true,
            'ticket_max_qty' => 0,
            'ticket_product_id' => $product_id,
            'limit' => 0,
            'related_product_ids' => array(),
        );
    }

    $ctx = vms_ticketing_v2_resolve_verified_ticket_context($product_id);
    $ticket_key = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, '_vms_ticket_key'));
    if ($ticket_key === '') {
        $ticket_key_meta = function_exists('vms_ticketing_v2_product_meta_key')
            ? vms_ticketing_v2_product_meta_key('ticketing_ticket_key')
            : '_vms_ticketing_ticket_key';
        $ticket_key = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, $ticket_key_meta));
    }
    if ($ticket_key === '') {
        $canonical_pid = absint(wp_get_post_parent_id($product_id));
        if ($canonical_pid <= 0) {
            $canonical_pid = $product_id;
        }
        $ticket_key = 'pid_' . $canonical_pid;
    }

    $event_id = absint(vms_ticketing_v2_meta_get($product_id, '_vms_ticket_event_id'));
    if ($event_id <= 0) {
        $event_id = absint($ctx['event_id'] ?? 0);
    }
    if ($event_id <= 0) {
        $tec_meta_key = function_exists('vms_ticketing_v2_product_meta_key')
            ? vms_ticketing_v2_product_meta_key('tec_event_id')
            : '_vms_tec_event_id';
        $event_id = absint(vms_ticketing_v2_meta_get($product_id, $tec_meta_key));
    }

    $visibility_mode = sanitize_key((string) ($ctx['visibility_mode'] ?? 'public'));
    if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
        $visibility_mode = 'public';
    }

    $program = sanitize_key((string) ($ctx['program'] ?? ''));
    $allowed_programs = function_exists('vms_ticketing_v2_normalize_allowed_programs')
        ? vms_ticketing_v2_normalize_allowed_programs($ctx['allowed_programs'] ?? array(), $program)
        : ($program !== '' ? array($program) : array());
    $allow_direct_grants = function_exists('vms_ticketing_v2_truthy')
        ? vms_ticketing_v2_truthy($ctx['allow_direct_grants'] ?? false, false)
        : vms_ticketing_v2_meta_truthy($ctx['allow_direct_grants'] ?? false, false);
    $claim_grant_type = sanitize_key((string) ($ctx['claim_grant_type'] ?? 'event_ticket_eligibility'));
    if (!in_array($claim_grant_type, vms_ticketing_v2_claim_grant_type_values(), true)) {
        $claim_grant_type = 'event_ticket_eligibility';
    }
    $claims_per_assignee = max(0, absint($ctx['claims_per_assignee'] ?? 1));
    if ($claims_per_assignee <= 0) {
        $claims_per_assignee = 1;
    }
    $require_assignee_email = function_exists('vms_ticketing_v2_truthy')
        ? vms_ticketing_v2_truthy($ctx['require_assignee_email'] ?? true, true)
        : vms_ticketing_v2_meta_truthy($ctx['require_assignee_email'] ?? true, true);
    if ($visibility_mode !== 'verified') {
        $program = '';
        $allowed_programs = array();
        $allow_direct_grants = false;
        $claim_grant_type = 'event_ticket_eligibility';
        $claims_per_assignee = 1;
        $require_assignee_email = true;
    } elseif ($program === '' && !empty($allowed_programs)) {
        $program = (string) $allowed_programs[0];
    }

    $ticket_max_qty = max(0, absint($ctx['ticket_max_qty'] ?? 0));
    $qualification_removed = vms_ticketing_v2_public_ticket_qualification_removed();
    $use_buyer_allowance_limit = (!$qualification_removed && $visibility_mode === 'verified' && !$require_assignee_email);
    $verified_allowance = 0;
    if ($use_buyer_allowance_limit && $program !== '' && is_user_logged_in()) {
        $user_id = absint(get_current_user_id());
        if ($user_id > 0) {
            $verified_allowance = max(0, absint(vms_ticketing_v2_resolve_verified_ticket_limit($user_id, $program, 0)));
        }
    }

    if ($qualification_removed && $visibility_mode === 'verified') {
        $limit = $ticket_max_qty;
    } elseif ($visibility_mode === 'verified' && $require_assignee_email) {
        $limit = 0;
    } elseif ($ticket_max_qty > 0 && $verified_allowance > 0) {
        $limit = min($ticket_max_qty, $verified_allowance);
    } elseif ($ticket_max_qty > 0) {
        $limit = $ticket_max_qty;
    } else {
        $limit = $verified_allowance;
    }

    $group_ids = array();
    if ($event_id > 0 && $ticket_key !== '') {
        $cache_key = $event_id . '|' . $ticket_key;
        static $group_cache = array();
        if (!isset($group_cache[$cache_key])) {
            $group_cache[$cache_key] = array();

            $seed_ids = array($product_id);
            $parent_id = absint(wp_get_post_parent_id($product_id));
            if ($parent_id > 0) {
                $seed_ids[] = $parent_id;
            }

            $ticket_key_meta = function_exists('vms_ticketing_v2_product_meta_key')
                ? vms_ticketing_v2_product_meta_key('ticketing_ticket_key')
                : '_vms_ticketing_ticket_key';
            $query_sets = array(
                array(
                    'event_meta' => '_vms_ticket_event_id',
                    'ticket_meta' => '_vms_ticket_key',
                ),
                array(
                    'event_meta' => '_tribe_wooticket_for_event',
                    'ticket_meta' => $ticket_key_meta,
                ),
            );

            foreach ($query_sets as $qset) {
                $ids = get_posts(array(
                    'post_type' => 'product',
                    'post_status' => array('publish', 'private', 'draft', 'pending'),
                    'fields' => 'ids',
                    'posts_per_page' => -1,
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Ticket-limit grouping must collect all current and legacy product variants for one event/ticket key; the complete ID set is retained in a request-local cache.
                    'meta_query' => array(
                        array(
                            'key' => $qset['event_meta'],
                            'value' => $event_id,
                            'compare' => '=',
                            'type' => 'NUMERIC',
                        ),
                        array(
                            'key' => $qset['ticket_meta'],
                            'value' => $ticket_key,
                            'compare' => '=',
                        ),
                    ),
                ));
                if (is_array($ids) && !empty($ids)) {
                    $seed_ids = array_merge($seed_ids, $ids);
                }
            }

            $clean_ids = array();
            foreach ($seed_ids as $seed_id) {
                $seed_id = absint($seed_id);
                if ($seed_id <= 0) {
                    continue;
                }
                $clean_ids[$seed_id] = $seed_id;
            }

            $group_cache[$cache_key] = array_values($clean_ids);
        }

        $group_ids = is_array($group_cache[$cache_key]) ? $group_cache[$cache_key] : array();
    }

    $limit = max(0, absint($limit));
    $context = array(
        'event_id' => $event_id,
        'ticket_key' => $ticket_key,
        'visibility_mode' => $visibility_mode,
        'program' => $program,
        'allowed_programs' => $allowed_programs,
        'allow_direct_grants' => $allow_direct_grants ? 1 : 0,
        'claim_grant_type' => $claim_grant_type,
        'claims_per_assignee' => $claims_per_assignee,
        'require_assignee_email' => $require_assignee_email ? 1 : 0,
        'ticket_max_qty' => $ticket_max_qty,
        'ticket_product_id' => $product_id,
        'limit' => $limit,
        'related_product_ids' => $group_ids,
    );

    if (!vms_ticketing_v2_should_enforce_ticket_max_qty($context)) {
        $context['limit'] = 0;
    }

    return $context;
}

function vms_ticketing_v2_cart_qty_for_event_ticket(int $event_id, string $ticket_key): int
{
    $event_id = absint($event_id);
    $ticket_key = sanitize_key($ticket_key);
    if ($event_id <= 0 || $ticket_key === '' || !function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        return 0;
    }

    $product_ids = array();
    $seen_groups = array();

    foreach ((array) WC()->cart->get_cart() as $item) {
        if (!is_array($item)) {
            continue;
        }
        $item_pid = absint($item['variation_id'] ?? 0);
        if ($item_pid <= 0) {
            $item_pid = absint($item['product_id'] ?? 0);
        }
        if ($item_pid <= 0) {
            continue;
        }

        $ctx = vms_ticketing_v2_resolve_ticket_max_context($item_pid);
        if (absint($ctx['event_id'] ?? 0) !== $event_id) {
            continue;
        }
        if (sanitize_key((string) ($ctx['ticket_key'] ?? '')) !== $ticket_key) {
            continue;
        }

        $group_key = $event_id . '|' . $ticket_key;
        if (isset($seen_groups[$group_key])) {
            continue;
        }
        $seen_groups[$group_key] = true;

        foreach ((array) ($ctx['related_product_ids'] ?? array()) as $gid) {
            $gid = absint($gid);
            if ($gid > 0) {
                $product_ids[$gid] = $gid;
            }
        }
        if (empty($product_ids)) {
            $product_ids[$item_pid] = $item_pid;
        }
    }

    if (empty($product_ids)) {
        return 0;
    }

    $pid_set = array_fill_keys(array_map('absint', array_values($product_ids)), true);
    $qty = 0;
    foreach ((array) WC()->cart->get_cart() as $item) {
        if (!is_array($item)) {
            continue;
        }
        $item_qty = max(0, absint($item['quantity'] ?? 0));
        if ($item_qty <= 0) {
            continue;
        }
        $variation_pid = absint($item['variation_id'] ?? 0);
        $product_pid = absint($item['product_id'] ?? 0);
        if ($variation_pid > 0 && isset($pid_set[$variation_pid])) {
            $qty += $item_qty;
            continue;
        }
        if ($product_pid > 0 && isset($pid_set[$product_pid])) {
            $qty += $item_qty;
            continue;
        }
    }

    return max(0, absint($qty));
}

function vms_ticketing_v2_ticket_group_product_ids_from_context(array $ctx, int $fallback_product_id = 0): array
{
    $ids = array_map('absint', (array) ($ctx['related_product_ids'] ?? array()));
    $fallback_product_id = absint($fallback_product_id);
    if ($fallback_product_id > 0) {
        $ids[] = $fallback_product_id;
        $parent_id = absint(wp_get_post_parent_id($fallback_product_id));
        if ($parent_id > 0) {
            $ids[] = $parent_id;
        }
    }
    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    return $ids;
}

function vms_ticketing_v2_event_ticket_product_ids_for_event(int $event_id): array
{
    $event_id = absint($event_id);
    if ($event_id <= 0) {
        return array();
    }

    static $cache = array();
    if (isset($cache[$event_id])) {
        return is_array($cache[$event_id]) ? $cache[$event_id] : array();
    }

    $product_ids = array();

    if (function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id') && function_exists('vms_ticketing_v2_get_config')) {
        $plan_id = absint(bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($event_id));
        if ($plan_id > 0) {
            $cfg = vms_ticketing_v2_get_config($plan_id);
            $sync = function_exists('vms_ticketing_v2_get_sync') ? vms_ticketing_v2_get_sync($plan_id) : array();
            $sync_map = (is_array($sync) && isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
            $ticket_cfg_rows = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? $cfg['tickets'] : array();
            $ticket_sync_rows = (isset($sync_map['tickets']) && is_array($sync_map['tickets'])) ? $sync_map['tickets'] : array();
            $legacy_ga_pid = absint($sync_map['ga']['woo_product_id'] ?? 0);

            foreach ($ticket_cfg_rows as $ticket_cfg_row) {
                if (!is_array($ticket_cfg_row)) {
                    continue;
                }
                if (array_key_exists('enabled', $ticket_cfg_row) && empty($ticket_cfg_row['enabled'])) {
                    continue;
                }

                $ticket_key = sanitize_key((string) ($ticket_cfg_row['ticket_key'] ?? $ticket_cfg_row['key'] ?? ''));
                if ($ticket_key === '') {
                    continue;
                }

                $ticket_pid = 0;
                if (isset($ticket_sync_rows[$ticket_key]) && is_array($ticket_sync_rows[$ticket_key])) {
                    $ticket_pid = absint($ticket_sync_rows[$ticket_key]['woo_product_id'] ?? 0);
                }

                if ($ticket_pid <= 0 && $legacy_ga_pid > 0) {
                    $ticket_label = function_exists('vms_ticketing_v2_sanitize_plain_text_label')
                        ? vms_ticketing_v2_sanitize_plain_text_label((string) ($ticket_cfg_row['title'] ?? $ticket_key))
                        : sanitize_text_field((string) ($ticket_cfg_row['title'] ?? $ticket_key));
                    if (function_exists('vms_ticketing_v2_should_apply_legacy_ga_map_to_ticket')) {
                        if (vms_ticketing_v2_should_apply_legacy_ga_map_to_ticket($ticket_key, $ticket_label)) {
                            $ticket_pid = $legacy_ga_pid;
                        }
                    } else {
                        $legacy_match_label = strtolower(trim((string) preg_replace('/\s+/u', ' ', $ticket_label)));
                        $has_specialized_label = ($legacy_match_label !== '' && preg_match('/\b(early|advance|pre[-\s]?sale|presale|vip|child|children|kid|kids|veteran|police|fire|emt|nurse|teacher|school)\b/u', $legacy_match_label));
                        if (!$has_specialized_label && (
                            in_array($ticket_key, array('ga', 'general_admission', 'general-admission'), true)
                            || in_array($legacy_match_label, array('general admission', 'ga admission', 'general admission ticket'), true)
                        )) {
                            $ticket_pid = $legacy_ga_pid;
                        }
                    }
                }

                if ($ticket_pid > 0) {
                    $product_ids[$ticket_pid] = $ticket_pid;
                }
            }
        }
    }

    $ticket_objects = array();
    if (function_exists('tribe_tickets')) {
        try {
            $provider = tribe_tickets('woo');
            if (is_object($provider) && method_exists($provider, 'get_tickets')) {
                $provider_tickets = $provider->get_tickets($event_id);
                if (is_array($provider_tickets)) {
                    $ticket_objects = array_merge($ticket_objects, $provider_tickets);
                }
            }
        } catch (Throwable $e) {
            // Fall through to other discovery paths.
        }
    }
    if (class_exists('Tribe__Tickets__Tickets') && method_exists('Tribe__Tickets__Tickets', 'get_all_event_tickets')) {
        try {
            $all_tickets = Tribe__Tickets__Tickets::get_all_event_tickets($event_id);
            if (is_array($all_tickets)) {
                $ticket_objects = array_merge($ticket_objects, $all_tickets);
            }
        } catch (Throwable $e) {
            // Event Tickets versions vary; ignore lookup failures here.
        }
    }

    foreach ($ticket_objects as $ticket) {
        $candidates = array();
        if (is_object($ticket)) {
            foreach (array('get_product_id', 'get_id', 'get_ticket_id') as $method) {
                if (method_exists($ticket, $method)) {
                    try {
                        $candidates[] = $ticket->{$method}();
                    } catch (Throwable $e) {
                        // Ignore candidate lookup failures.
                    }
                }
            }
            foreach (array('product_id', 'ID', 'id', 'ticket_id') as $prop) {
                if (isset($ticket->{$prop})) {
                    $candidates[] = $ticket->{$prop};
                }
            }
        } elseif (is_array($ticket)) {
            foreach (array('product_id', 'ID', 'id', 'ticket_id') as $key) {
                if (isset($ticket[$key])) {
                    $candidates[] = $ticket[$key];
                }
            }
        }

        foreach ($candidates as $candidate) {
            $candidate_id = absint($candidate);
            if ($candidate_id > 0) {
                $product_ids[$candidate_id] = $candidate_id;
            }
        }
    }

    $cache[$event_id] = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
    return $cache[$event_id];
}

function vms_ticketing_v2_active_ticket_count_for_event_user(int $event_id, int $user_id): int
{
    $event_id = absint($event_id);
    $user_id = absint($user_id);
    if ($event_id <= 0 || $user_id <= 0) {
        return -1;
    }

    $product_ids = vms_ticketing_v2_event_ticket_product_ids_for_event($event_id);
    if (empty($product_ids) || !function_exists('vms_ticketing_v2_purchased_ticket_qty_for_user')) {
        return -1;
    }

    return max(0, absint(vms_ticketing_v2_purchased_ticket_qty_for_user($user_id, $product_ids)));
}

function vms_ticketing_v2_purchased_ticket_qty_for_user(int $user_id, array $product_ids): int
{
    $user_id = absint($user_id);
    $product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
    if ($user_id <= 0 || empty($product_ids)) {
        return 0;
    }

    sort($product_ids);
    static $cache = array();
    $cache_key = $user_id . '|' . implode(',', $product_ids);
    if (isset($cache[$cache_key])) {
        return max(0, absint($cache[$cache_key]));
    }

    $statuses = array('wc-processing', 'wc-completed', 'wc-on-hold');
    global $wpdb;

    if (isset($wpdb) && is_object($wpdb)) {
        $oi = $wpdb->prefix . 'woocommerce_order_items';
        $oim = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $stats_table = $wpdb->prefix . 'wc_order_stats';
        if (function_exists('vms_ticketing_v2_table_exists')) {
            $has_order_items = vms_ticketing_v2_table_exists($oi) && vms_ticketing_v2_table_exists($oim);
            $has_stats = vms_ticketing_v2_table_exists($stats_table);
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The load-order fallback performs prepared WooCommerce capability probes before the request-cached purchased-quantity read; no core API exposes table availability.
            $has_order_items = (($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $oi)) === $oi) && ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $oim)) === $oim));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The load-order fallback performs a prepared WooCommerce stats-table capability probe before the request-cached purchased-quantity read.
            $has_stats = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $stats_table)) === $stats_table);
        }

        if ($has_order_items && $has_stats) {
            $pid_placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
            $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $orders_table = $wpdb->prefix . 'wc_orders';
            if (function_exists('vms_ticketing_v2_table_exists')) {
                $has_wc_orders = vms_ticketing_v2_table_exists($orders_table);
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The load-order fallback performs a prepared HPOS-orders capability probe so refund-type detection can select the correct storage branch.
                $has_wc_orders = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $orders_table)) === $orders_table);
            }

            // HPOS stores the canonical refund type in wp_wc_orders.type. On those sites,
            // wp_posts may contain only a shop_order_placehold row for the refund ID.
            // Keep the CPT/post fallback for non-HPOS sites while allowing HPOS refunds
            // to subtract from the customer's active ticket count.
            $refund_order_join_sql = '';
            $refund_type_conditions = array("refund_posts.post_type = 'shop_order_refund'");
            if ($has_wc_orders) {
                $refund_order_join_sql = $wpdb->prepare("
                    LEFT JOIN %i refund_orders
                        ON refund_orders.id = refund_items.order_id
                ", $orders_table);
                $refund_type_conditions[] = "refund_orders.type = 'shop_order_refund'";
            }
            $refund_type_where_sql = 'AND (' . implode(' OR ', $refund_type_conditions) . ')';

            $sql = "
                SELECT COALESCE(SUM(GREATEST(0, line_items.qty - COALESCE(refunds.refunded_qty, 0))), 0)
                FROM (
                    SELECT
                        oi.order_item_id,
                        oi.order_id,
                        MAX(CASE WHEN oim.meta_key = '_product_id' THEN CAST(oim.meta_value AS UNSIGNED) ELSE 0 END) AS product_id,
                        MAX(CASE WHEN oim.meta_key = '_variation_id' THEN CAST(oim.meta_value AS UNSIGNED) ELSE 0 END) AS variation_id,
                        MAX(CASE WHEN oim.meta_key = '_qty' THEN CAST(oim.meta_value AS SIGNED) ELSE 0 END) AS qty
                    FROM %i oi
                    INNER JOIN %i oim
                        ON oim.order_item_id = oi.order_item_id
                    WHERE oi.order_item_type = 'line_item'
                      AND oim.meta_key IN ('_product_id', '_variation_id', '_qty')
                    GROUP BY oi.order_item_id, oi.order_id
                    HAVING product_id IN ({$pid_placeholders}) OR variation_id IN ({$pid_placeholders})
                ) line_items
                INNER JOIN %i stats
                    ON stats.order_id = line_items.order_id
                   AND stats.customer_id = %d
                LEFT JOIN (
                    SELECT
                        CAST(refunded_item.meta_value AS UNSIGNED) AS refunded_item_id,
                        SUM(ABS(CAST(refund_qty.meta_value AS SIGNED))) AS refunded_qty
                    FROM %i refund_items
                    INNER JOIN %i refunded_item
                        ON refunded_item.order_item_id = refund_items.order_item_id
                       AND refunded_item.meta_key = '_refunded_item_id'
                    INNER JOIN %i refund_qty
                        ON refund_qty.order_item_id = refund_items.order_item_id
                       AND refund_qty.meta_key = '_qty'
                    LEFT JOIN %i refund_posts
                        ON refund_posts.ID = refund_items.order_id
                    {$refund_order_join_sql}
                    WHERE refund_items.order_item_type = 'line_item'
                      {$refund_type_where_sql}
                    GROUP BY CAST(refunded_item.meta_value AS UNSIGNED)
                ) refunds
                    ON refunds.refunded_item_id = line_items.order_item_id
                WHERE line_items.qty > 0
                  AND stats.status IN ({$status_placeholders})
            "; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic fragments are bounded product/status placeholder lists plus one pre-prepared HPOS refund join and fixed refund-type conditions; identifiers and values remain wpdb-prepared.
            $params = array_merge(array($oi, $oim), $product_ids, $product_ids, array($stats_table, $user_id, $oi, $oim, $oim, $wpdb->posts), $statuses);
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The purchased-quantity aggregate contains only prepared identifiers/values and bounded placeholder/storage fragments.
            $prepared = $wpdb->prepare($sql, $params);
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket-limit enforcement requires a fresh WooCommerce order/refund aggregate on the first request-local cache lookup; no WooCommerce API preserves the grouped product/variation semantics.
            $cache[$cache_key] = max(0, absint($wpdb->get_var($prepared)));
            return max(0, absint($cache[$cache_key]));
        }
    }

    if (!function_exists('wc_get_orders')) {
        $cache[$cache_key] = 0;
        return 0;
    }

    $purchased_qty = 0;
    $orders = wc_get_orders(array(
        'customer_id' => $user_id,
        'status' => array('processing', 'completed', 'on-hold'),
        'limit' => -1,
        'return' => 'ids',
    ));
    if (!is_array($orders)) {
        $cache[$cache_key] = 0;
        return 0;
    }

    $pid_set = array_fill_keys($product_ids, true);
    foreach ($orders as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            continue;
        }
        foreach ($order->get_items('line_item') as $order_item) {
            if (!is_object($order_item)) {
                continue;
            }
            $item_pid = absint($order_item->get_product_id());
            $item_vid = absint($order_item->get_variation_id());
            if (($item_pid > 0 && isset($pid_set[$item_pid])) || ($item_vid > 0 && isset($pid_set[$item_vid]))) {
                $qty = max(0, absint($order_item->get_quantity()));
                $refunded_qty = 0;
                if (is_callable(array($order, 'get_qty_refunded_for_item')) && is_callable(array($order_item, 'get_id'))) {
                    $refunded_qty = max(0, absint(abs((float) $order->get_qty_refunded_for_item($order_item->get_id()))));
                }
                $purchased_qty += max(0, $qty - $refunded_qty);
            }
        }
    }

    $cache[$cache_key] = max(0, absint($purchased_qty));
    return max(0, absint($cache[$cache_key]));
}

if (!function_exists('vms_ticketing_v2_decode_stored_claim_assignment_rows')) {
    /**
     * Decode plugin-stored claim-assignment rows.
     *
     * @param mixed $raw
     * @return array<int, array<string, mixed>>
     */
    function vms_ticketing_v2_decode_stored_claim_assignment_rows($raw): array
    {
        if (!is_string($raw)) {
            return array();
        }

        $trimmed = trim($raw);
        if ($trimmed === '' || substr($trimmed, 0, 1) !== '[') {
            return array();
        }

        $decoded = json_decode($trimmed, true, 32);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return array();
        }

        if (function_exists('array_is_list')) {
            return array_is_list($decoded) ? $decoded : array();
        }

        return array_values($decoded) === $decoded ? $decoded : array();
    }
}

function vms_ticketing_v2_assignee_consumed_qty_for_event(int $event_id, string $assignee_email, array $product_ids): int
{
    $event_id = absint($event_id);
    $assignee_email = sanitize_email($assignee_email);
    $email_key = strtolower(trim($assignee_email));
    $product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
    if ($event_id <= 0 || $email_key === '' || empty($product_ids)) {
        return 0;
    }

    global $wpdb;
    $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
    $stats_table = $wpdb->prefix . 'wc_order_stats';
    $itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
    static $lookup_supported = null;

    if ($lookup_supported === null) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Assignee lookup capability is probed once per request with a prepared WooCommerce lookup-table name.
        $has_lookup = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $lookup_table)) === $lookup_table);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Assignee lookup capability is probed once per request with a prepared WooCommerce stats-table name.
        $has_stats = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $stats_table)) === $stats_table);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Assignee lookup capability is probed once per request with a prepared WooCommerce itemmeta-table name.
        $has_itemmeta = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $itemmeta_table)) === $itemmeta_table);
        $lookup_supported = ($has_lookup && $has_stats && $has_itemmeta);
    }

    $consumed = 0;
    if ($lookup_supported) {
        $statuses = array('wc-processing', 'wc-completed', 'wc-on-hold');
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $pid_placeholders = implode(',', array_fill(0, count($product_ids), '%d'));

        $sql = "
            SELECT lookup.order_item_id, claim_meta.meta_value AS assignments_json
            FROM %i lookup
            INNER JOIN %i stats ON stats.order_id = lookup.order_id
            INNER JOIN %i event_meta
                ON event_meta.order_item_id = lookup.order_item_id
               AND event_meta.meta_key = '_vms_tec_event_post_id'
            LEFT JOIN %i claim_meta
                ON claim_meta.order_item_id = lookup.order_item_id
               AND claim_meta.meta_key = '_vms_claim_assignments'
            WHERE stats.status IN ({$status_placeholders})
              AND (lookup.product_id IN ({$pid_placeholders}) OR lookup.variation_id IN ({$pid_placeholders}))
              AND CAST(event_meta.meta_value AS UNSIGNED) = %d
        "; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic fragments are bounded status/product placeholder lists; all WooCommerce identifiers and filter values remain wpdb-prepared.
        $params = array_merge(array($lookup_table, $stats_table, $itemmeta_table, $itemmeta_table), $statuses, $product_ids, $product_ids, array($event_id));
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The assignee lookup contains only prepared identifiers/values and bounded placeholder lists.
        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim-limit enforcement requires request-fresh assignment rows, and no WooCommerce API can join the event and plugin claim metadata with product/variation lookup rows.
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        foreach ((array) $rows as $row) {
            $assignments_json = (string) ($row['assignments_json'] ?? '');
            if ($assignments_json === '') {
                continue;
            }
            $decoded = vms_ticketing_v2_decode_stored_claim_assignment_rows($assignments_json);
            foreach ($decoded as $assignment_row) {
                if (!is_array($assignment_row)) {
                    continue;
                }
                $candidate = sanitize_email((string) ($assignment_row['assignee_email'] ?? ($assignment_row['email'] ?? '')));
                if ($candidate === '') {
                    continue;
                }
                if (strtolower($candidate) === $email_key) {
                    $consumed++;
                }
            }
        }
        return max(0, absint($consumed));
    }

    if (!function_exists('wc_get_orders')) {
        return 0;
    }

    $orders = wc_get_orders(array(
        'status' => array('processing', 'completed', 'on-hold'),
        'limit' => -1,
        'return' => 'objects',
    ));
    if (!is_array($orders)) {
        return 0;
    }

    $pid_set = array_fill_keys($product_ids, true);
    foreach ($orders as $order) {
        if (!is_object($order) || !method_exists($order, 'get_items')) {
            continue;
        }
        foreach ($order->get_items('line_item') as $order_item) {
            if (!is_object($order_item)) {
                continue;
            }
            $item_pid = absint($order_item->get_product_id());
            $item_vid = absint($order_item->get_variation_id());
            if (($item_pid <= 0 || !isset($pid_set[$item_pid])) && ($item_vid <= 0 || !isset($pid_set[$item_vid]))) {
                continue;
            }

            $item_event_id = absint($order_item->get_meta('_vms_tec_event_post_id', true));
            if ($item_event_id !== $event_id) {
                continue;
            }

            $assignment_rows = array();
            $assignment_json = $order_item->get_meta('_vms_claim_assignments', true);
            if (is_array($assignment_json)) {
                $assignment_rows = $assignment_json;
            } elseif (is_string($assignment_json) && $assignment_json !== '') {
                $assignment_rows = vms_ticketing_v2_decode_stored_claim_assignment_rows($assignment_json);
            }

            if (empty($assignment_rows) && method_exists($order_item, 'get_meta_data')) {
                foreach ((array) $order_item->get_meta_data() as $meta_obj) {
                    if (!is_object($meta_obj) || !method_exists($meta_obj, 'get_data')) {
                        continue;
                    }
                    $meta_data = $meta_obj->get_data();
                    $meta_key = sanitize_text_field((string) ($meta_data['key'] ?? ''));
                    if ($meta_key === '' || stripos($meta_key, 'seat ') !== 0 || stripos($meta_key, ' assignee') === false) {
                        continue;
                    }
                    $assignment_rows[] = array(
                        'assignee_email' => sanitize_email((string) ($meta_data['value'] ?? '')),
                    );
                }
            }

            foreach ($assignment_rows as $assignment_row) {
                if (!is_array($assignment_row)) {
                    continue;
                }
                $candidate = sanitize_email((string) ($assignment_row['assignee_email'] ?? ($assignment_row['email'] ?? '')));
                if ($candidate === '') {
                    continue;
                }
                if (strtolower($candidate) === $email_key) {
                    $consumed++;
                }
            }
        }
    }

    return max(0, absint($consumed));
}

/**
 * @param mixed $raw
 * @return array<int,array{seat:int,assignee_email:string}>
 */
function vms_ticketing_v2_claim_assignments_normalize($raw): array
{
    if (!is_array($raw)) {
        return array();
    }

    $out = array();
    $seat_fallback = 1;
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }

        $seat = absint($row['seat'] ?? ($row['seat_index'] ?? $seat_fallback));
        if ($seat <= 0) {
            $seat = $seat_fallback;
        }

        $email = sanitize_email((string) ($row['assignee_email'] ?? ($row['email'] ?? '')));
        $out[] = array(
            'seat' => $seat,
            'assignee_email' => $email,
        );
        $seat_fallback++;
    }

    usort($out, static function (array $a, array $b): int {
        return absint($a['seat'] ?? 0) <=> absint($b['seat'] ?? 0);
    });

    return $out;
}

function vms_ticketing_v2_payload_is_object_like_array(array $value): bool
{
    return empty($value) || !bvmgr_array_is_list_compat($value);
}

function vms_ticketing_v2_payload_has_scalar_value($value, int $max_length = 512): bool
{
    if ($value === null) {
        return true;
    }
    if (is_array($value) || is_object($value)) {
        return false;
    }

    return strlen((string) $value) <= $max_length;
}

function vms_ticketing_v2_validate_claim_assignment_payload($rows): bool
{
    if (!is_array($rows) || (!empty($rows) && !bvmgr_array_is_list_compat($rows)) || count($rows) > 100) {
        return false;
    }

    foreach ($rows as $row) {
        if (!is_array($row) || !vms_ticketing_v2_payload_is_object_like_array($row)) {
            return false;
        }
        if (isset($row['seat']) && !vms_ticketing_v2_payload_has_scalar_value($row['seat'], 16)) {
            return false;
        }
        if (isset($row['seat_index']) && !vms_ticketing_v2_payload_has_scalar_value($row['seat_index'], 16)) {
            return false;
        }
        if (isset($row['assignee_email']) && !vms_ticketing_v2_payload_has_scalar_value($row['assignee_email'], 254)) {
            return false;
        }
        if (isset($row['email']) && !vms_ticketing_v2_payload_has_scalar_value($row['email'], 254)) {
            return false;
        }
    }

    return true;
}

function vms_ticketing_v2_validate_atomic_ticket_line_payload($line): bool
{
    if (!is_array($line) || !vms_ticketing_v2_payload_is_object_like_array($line)) {
        return false;
    }

    foreach (array('product_id', 'productId', 'ticket_id', 'ticketId', 'qty', 'quantity', 'variation_id', 'variationId') as $scalar_key) {
        if (isset($line[$scalar_key]) && !vms_ticketing_v2_payload_has_scalar_value($line[$scalar_key], 32)) {
            return false;
        }
    }

    if (isset($line['variation'])) {
        if (!is_array($line['variation']) || !vms_ticketing_v2_payload_is_object_like_array($line['variation']) || count($line['variation']) > 20) {
            return false;
        }
        foreach ($line['variation'] as $variation_key => $variation_value) {
            if (!vms_ticketing_v2_payload_has_scalar_value($variation_key, 64) || !vms_ticketing_v2_payload_has_scalar_value($variation_value, 200)) {
                return false;
            }
        }
    }

    if (isset($line['attributes'])) {
        if (!is_array($line['attributes']) || !vms_ticketing_v2_payload_is_object_like_array($line['attributes']) || count($line['attributes']) > 20) {
            return false;
        }
        foreach ($line['attributes'] as $attribute_key => $attribute_value) {
            if (!vms_ticketing_v2_payload_has_scalar_value($attribute_key, 64) || !vms_ticketing_v2_payload_has_scalar_value($attribute_value, 200)) {
                return false;
            }
        }
    }

    foreach (array('claim_assignments', 'claimAssignments') as $assignment_key) {
        if (isset($line[$assignment_key]) && !vms_ticketing_v2_validate_claim_assignment_payload($line[$assignment_key])) {
            return false;
        }
    }

    return true;
}

function vms_ticketing_v2_validate_atomic_addon_line_payload($line): bool
{
    if (!is_array($line) || !vms_ticketing_v2_payload_is_object_like_array($line)) {
        return false;
    }

    foreach (array('product_id', 'productId', 'product', 'qty', 'quantity') as $scalar_key) {
        if (isset($line[$scalar_key]) && !vms_ticketing_v2_payload_has_scalar_value($line[$scalar_key], 32)) {
            return false;
        }
    }

    return true;
}

function vms_ticketing_v2_validate_atomic_add_payload(array $data): bool
{
    if (!vms_ticketing_v2_payload_is_object_like_array($data)) {
        return false;
    }

    foreach (array('nonce', 'tec_event_id', 'tecEventId', 'event_plan_id', 'eventPlanId') as $scalar_key) {
        if (isset($data[$scalar_key]) && !vms_ticketing_v2_payload_has_scalar_value($data[$scalar_key], 200)) {
            return false;
        }
    }

    $ticket_lines = $data['ticket_lines'] ?? ($data['tickets'] ?? array());
    if (!is_array($ticket_lines) || (!empty($ticket_lines) && !bvmgr_array_is_list_compat($ticket_lines)) || count($ticket_lines) > 50) {
        return false;
    }
    foreach ($ticket_lines as $line) {
        if (!vms_ticketing_v2_validate_atomic_ticket_line_payload($line)) {
            return false;
        }
    }

    $addon_lines = $data['addon_lines'] ?? ($data['addons'] ?? array());
    if (!is_array($addon_lines) || (!empty($addon_lines) && !bvmgr_array_is_list_compat($addon_lines)) || count($addon_lines) > 50) {
        return false;
    }
    foreach ($addon_lines as $line) {
        if (!vms_ticketing_v2_validate_atomic_addon_line_payload($line)) {
            return false;
        }
    }

    return true;
}

function vms_ticketing_v2_validate_silent_add_payload(array $data): bool
{
    if (!vms_ticketing_v2_payload_is_object_like_array($data)) {
        return false;
    }

    foreach (array('nonce', 'tec_event_id', 'event_plan_id', 'ga_qty_hint') as $scalar_key) {
        if (isset($data[$scalar_key]) && !vms_ticketing_v2_payload_has_scalar_value($data[$scalar_key], 200)) {
            return false;
        }
    }

    $items = $data['items'] ?? array();
    if (!is_array($items) || (!empty($items) && !bvmgr_array_is_list_compat($items)) || count($items) > 50) {
        return false;
    }
    foreach ($items as $item) {
        if (!vms_ticketing_v2_validate_atomic_addon_line_payload($item)) {
            return false;
        }
    }

    return true;
}

function vms_ticketing_v2_read_form_request_payload(array $source): array
{
    if (empty($source)) {
        return array();
    }

    $payload = wp_unslash($source);
    return is_array($payload) ? $payload : array();
}

/**
 * @return array{ok:bool,present:bool,payload:array<string,mixed>,error:string}
 */
function vms_ticketing_v2_read_json_request_payload(int $max_bytes): array
{
    $body = bvmgr_read_limited_stream('php://input', $max_bytes);
    if (empty($body['ok'])) {
        return array(
            'ok' => false,
            'present' => false,
            'payload' => array(),
            'error' => 'body_read_failed',
        );
    }

    $raw = trim((string) ($body['data'] ?? ''));
    if ($raw === '') {
        return array(
            'ok' => true,
            'present' => false,
            'payload' => array(),
            'error' => '',
        );
    }

    $content_type = strtolower(bvmgr_request_server_value('CONTENT_TYPE'));
    $top_level_token = bvmgr_json_top_level_token($raw);
    $expects_json = (strpos($content_type, 'application/json') !== false || $top_level_token === '{' || $top_level_token === '[');
    if (!$expects_json) {
        return array(
            'ok' => true,
            'present' => false,
            'payload' => array(),
            'error' => '',
        );
    }

    if (!empty($body['too_large'])) {
        return array(
            'ok' => false,
            'present' => true,
            'payload' => array(),
            'error' => 'body_too_large',
        );
    }

    $decoded = bvmgr_json_decode_associative($raw, 32);
    if (
        empty($decoded['ok'])
        || !is_array($decoded['value'])
        || !bvmgr_json_decoded_is_object($decoded['value'], (string) ($decoded['top_level_token'] ?? ''))
    ) {
        return array(
            'ok' => false,
            'present' => true,
            'payload' => array(),
            'error' => 'body_invalid_json',
        );
    }

    return array(
        'ok' => true,
        'present' => true,
        'payload' => $decoded['value'],
        'error' => '',
    );
}

/**
 * @return array<int,array{seat:int,assignee_email:string}>
 */
function vms_ticketing_v2_cart_item_claim_assignments(array $cart_item): array
{
    $raw = $cart_item['vms_claim_assignments'] ?? array();
    if (!is_array($raw) && isset($cart_item['_vms_claim_assignments']) && is_array($cart_item['_vms_claim_assignments'])) {
        $raw = $cart_item['_vms_claim_assignments'];
    }
    return vms_ticketing_v2_claim_assignments_normalize($raw);
}

/**
 * @return array{
 *   visibility_mode:string,
 *   event_id:int,
 *   ticket_key:string,
 *   legacy_program:string,
 *   allowed_programs:array<int,string>,
 *   allow_direct_grants:bool,
 *   claim_grant_type:string,
 *   claims_per_assignee:int,
 *   ticket_max_qty:int,
 *   require_assignee_email:bool
 * }
 */
function vms_ticketing_v2_claim_context_for_product(int $product_id): array
{
    $ctx = vms_ticketing_v2_resolve_verified_ticket_context($product_id);

    $visibility_mode = sanitize_key((string) ($ctx['visibility_mode'] ?? 'public'));
    if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
        $visibility_mode = 'public';
    }

    $legacy_program = sanitize_key((string) ($ctx['program'] ?? ''));
    $allowed_programs = function_exists('vms_ticketing_v2_normalize_allowed_programs')
        ? vms_ticketing_v2_normalize_allowed_programs($ctx['allowed_programs'] ?? array(), $legacy_program)
        : ($legacy_program !== '' ? array($legacy_program) : array());
    if ($legacy_program === '' && !empty($allowed_programs)) {
        $legacy_program = (string) $allowed_programs[0];
    }

    $allow_direct_grants = function_exists('vms_ticketing_v2_truthy')
        ? vms_ticketing_v2_truthy($ctx['allow_direct_grants'] ?? false, false)
        : vms_ticketing_v2_meta_truthy($ctx['allow_direct_grants'] ?? false, false);

    $claim_grant_type = sanitize_key((string) ($ctx['claim_grant_type'] ?? 'event_ticket_eligibility'));
    if (!in_array($claim_grant_type, vms_ticketing_v2_claim_grant_type_values(), true)) {
        $claim_grant_type = 'event_ticket_eligibility';
    }

    $claims_per_assignee = max(0, absint($ctx['claims_per_assignee'] ?? 1));
    if ($claims_per_assignee <= 0) {
        $claims_per_assignee = 1;
    }

    $require_assignee_email = function_exists('vms_ticketing_v2_truthy')
        ? vms_ticketing_v2_truthy($ctx['require_assignee_email'] ?? true, true)
        : vms_ticketing_v2_meta_truthy($ctx['require_assignee_email'] ?? true, true);

    return array(
        'visibility_mode' => $visibility_mode,
        'event_id' => absint($ctx['event_id'] ?? 0),
        'ticket_key' => sanitize_key((string) ($ctx['ticket_key'] ?? '')),
        'legacy_program' => $legacy_program,
        'allowed_programs' => $allowed_programs,
        'allow_direct_grants' => (bool) $allow_direct_grants,
        'claim_grant_type' => $claim_grant_type,
        'claims_per_assignee' => $claims_per_assignee,
        'ticket_max_qty' => max(0, absint($ctx['ticket_max_qty'] ?? 0)),
        'ticket_product_id' => $product_id,
        'require_assignee_email' => (bool) $require_assignee_email,
    );
}

function vms_ticketing_v2_claim_program_label_text(array $allowed_programs, string $legacy_program = ''): string
{
    $allowed_programs = function_exists('vms_ticketing_v2_normalize_allowed_programs')
        ? vms_ticketing_v2_normalize_allowed_programs($allowed_programs, $legacy_program)
        : array_values(array_unique(array_filter(array_map('sanitize_key', $allowed_programs))));
    $legacy_program = sanitize_key($legacy_program);

    $labels = array();
    if (function_exists('vms_ticketing_claims_program_labels')) {
        $labels = (array) vms_ticketing_claims_program_labels($allowed_programs);
    } else {
        foreach ($allowed_programs as $program) {
            $labels[] = vms_ticketing_v2_verified_ticket_program_label((string) $program);
        }
    }
    $labels = array_values(array_filter(array_unique(array_map('trim', $labels))));
    if (!empty($labels)) {
        return implode(', ', $labels);
    }
    return vms_ticketing_v2_verified_ticket_program_label($legacy_program);
}

function vms_ticketing_v2_claim_assignment_unknown_guest_message(): string
{
    return __("We couldn't find an approved qualified guest account for this email. The guest needs to register and be approved before this ticket can be claimed.", 'backstage-venue-manager');
}

function vms_ticketing_v2_claim_assignment_unapproved_guest_message(): string
{
    return __('This email is not approved for this ticket yet. The guest needs to register and be approved before this ticket can be claimed.', 'backstage-venue-manager');
}

function vms_ticketing_v2_claim_assignment_duplicate_guest_message(): string
{
    return __('This guest email has already been added.', 'backstage-venue-manager');
}

function vms_ticketing_v2_resolve_claim_eligibility_for_user(
    int $user_id,
    int $event_id,
    int $ticket_product_id,
    string $ticket_key,
    string $legacy_program,
    array $allowed_programs,
    bool $allow_direct_grants,
    string $grant_type
): array {
    $legacy_program = sanitize_key($legacy_program);
    $allowed_programs = function_exists('vms_ticketing_v2_normalize_allowed_programs')
        ? vms_ticketing_v2_normalize_allowed_programs($allowed_programs, $legacy_program)
        : array_values(array_unique(array_filter(array_map('sanitize_key', $allowed_programs))));

    $grant_type = sanitize_key($grant_type);
    if (!in_array($grant_type, vms_ticketing_v2_claim_grant_type_values(), true)) {
        $grant_type = 'event_ticket_eligibility';
    }

    $eligibility = array(
        'eligible' => false,
        'reason_code' => $user_id > 0 ? 'not_eligible' : 'login_required',
        'message' => '',
        'matched_rule_path' => '',
        'matched_grant_id' => 0,
    );

    if ($user_id <= 0) {
        return $eligibility;
    }

    if (function_exists('vms_ticketing_claims_resolve_eligibility')) {
        $resolved = (array) vms_ticketing_claims_resolve_eligibility(array(
            'user_id' => $user_id,
            'event_id' => $event_id,
            'ticket_product_id' => $ticket_product_id,
            'ticket_key' => $ticket_key,
            'legacy_program' => $legacy_program,
            'allowed_programs' => $allowed_programs,
            'allow_direct_grants' => $allow_direct_grants,
            'grant_type' => $grant_type,
        ));

        $eligibility['eligible'] = !empty($resolved['eligible']);
        $eligibility['reason_code'] = sanitize_key((string) ($resolved['reason_code'] ?? ($eligibility['eligible'] ? 'ok' : 'not_eligible')));
        $eligibility['message'] = sanitize_text_field((string) ($resolved['message'] ?? ''));
        $eligibility['matched_rule_path'] = sanitize_key((string) ($resolved['matched_rule_path'] ?? ''));
        $eligibility['matched_grant_id'] = absint($resolved['matched_grant_id'] ?? 0);

        return $eligibility;
    }

    if ($legacy_program !== '' && function_exists('vms_ticketing_user_is_verified_for_program')) {
        $eligible = vms_ticketing_user_is_verified_for_program($user_id, $legacy_program);
        $eligibility['eligible'] = $eligible;
        $eligibility['reason_code'] = $eligible ? 'ok' : 'credential_not_approved';
        return $eligibility;
    }

    $eligibility['reason_code'] = 'resolver_unavailable';
    return $eligibility;
}

/**
 * Resolve per-assignee event usage limit for claim assignment validation.
 * Falls back to ticket claim settings and raises cap for matched credential/direct-grant rules.
 */
function vms_ticketing_v2_assignee_claims_per_event_limit(array $context, WP_User $assignee_user, array $resolved = array()): int
{
    $base_limit = max(1, absint($context['claims_per_assignee'] ?? 1));
    $matched_rule_path = sanitize_key((string) ($resolved['matched_rule_path'] ?? ''));
    $matched_program = sanitize_key((string) ($resolved['matched_program'] ?? ''));
    if ($matched_program === '') {
        $allowed_programs = (array) ($context['allowed_programs'] ?? array());
        $allowed_programs = array_values(array_filter(array_map('sanitize_key', $allowed_programs)));
        if ($matched_rule_path === 'credential_program' && !empty($allowed_programs)) {
            $matched_program = (string) $allowed_programs[0];
        }
        if ($matched_program === '') {
            $matched_program = sanitize_key((string) ($context['legacy_program'] ?? ''));
        }
    }

    if ($matched_program !== '' && function_exists('vms_ticketing_v2_resolve_verified_ticket_limit')) {
        // Use the credential/profile allowance as the per-person claim cap.
        // Do not cap this value by the ticket's public "max qty per order" field:
        // that product/order control is separate from a verified account's
        // effective eligible-pass allowance, and capping here made per-user
        // overrides like 4 behave as though they were still limited to the
        // ticket/card setting.
        $program_limit = max(0, absint(vms_ticketing_v2_resolve_verified_ticket_limit((int) $assignee_user->ID, $matched_program, 0)));
        if ($program_limit > 0) {
            $base_limit = max($base_limit, $program_limit);
        }
    }

    if ($matched_rule_path === 'event_direct_grant' && function_exists('vms_ticketing_claims_get_direct_grant')) {
        $grant_id = absint($resolved['matched_grant_id'] ?? 0);
        if ($grant_id > 0) {
            $grant_row = vms_ticketing_claims_get_direct_grant($grant_id);
            if (is_array($grant_row)) {
                $grant_qty_limit = max(0, absint($grant_row['qty_limit'] ?? 0));
                if ($grant_qty_limit > 0) {
                    $base_limit = max($base_limit, $grant_qty_limit);
                }
            }
        }
    }

    if (!empty($context['allow_direct_grants']) && function_exists('vms_ticketing_claims_find_active_direct_grant')) {
        $grant_row = vms_ticketing_claims_find_active_direct_grant(array(
            'user_id' => (int) $assignee_user->ID,
            'event_id' => absint($context['event_id'] ?? 0),
            'ticket_product_id' => absint($context['ticket_product_id'] ?? 0),
            'ticket_key' => sanitize_key((string) ($context['ticket_key'] ?? '')),
            'grant_type' => sanitize_key((string) ($context['claim_grant_type'] ?? 'event_ticket_eligibility')),
            'allowed_programs' => (array) ($context['allowed_programs'] ?? array()),
        ));
        if (is_array($grant_row)) {
            $grant_qty_limit = max(0, absint($grant_row['qty_limit'] ?? 0));
            if ($grant_qty_limit > 0) {
                $base_limit = max($base_limit, $grant_qty_limit);
            }
        }
    }

    return max(1, $base_limit);
}

/**
 * @return array<string,int>
 */
function vms_ticketing_v2_cart_assignee_usage_for_event(int $event_id, string $ticket_key = '', string $exclude_cart_item_key = ''): array
{
    $event_id = absint($event_id);
    $ticket_key = sanitize_key($ticket_key);
    $exclude_cart_item_key = trim((string) $exclude_cart_item_key);
    if ($event_id <= 0 || !function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        return array();
    }

    $counts = array();
    foreach ((array) WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (!is_array($cart_item)) {
            continue;
        }
        if ($exclude_cart_item_key !== '' && (string) $cart_item_key === $exclude_cart_item_key) {
            continue;
        }

        $variation_id = absint($cart_item['variation_id'] ?? 0);
        $item_product_id = $variation_id > 0 ? $variation_id : absint($cart_item['product_id'] ?? 0);
        if ($item_product_id <= 0) {
            continue;
        }

        $ctx = vms_ticketing_v2_claim_context_for_product($item_product_id);
        if (sanitize_key((string) ($ctx['visibility_mode'] ?? '')) !== 'verified') {
            continue;
        }
        if (absint($ctx['event_id'] ?? 0) !== $event_id) {
            continue;
        }
        if ($ticket_key !== '' && sanitize_key((string) ($ctx['ticket_key'] ?? '')) !== $ticket_key) {
            continue;
        }

        $rows = vms_ticketing_v2_cart_item_claim_assignments($cart_item);
        foreach ($rows as $row) {
            $email_key = strtolower(trim((string) ($row['assignee_email'] ?? '')));
            if ($email_key === '') {
                continue;
            }
            $counts[$email_key] = absint($counts[$email_key] ?? 0) + 1;
        }
    }

    return $counts;
}

function vms_ticketing_v2_cart_line_has_claim_assignments(array $line): bool
{
    if (!function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        return false;
    }

    $event_id = absint($line['event_id'] ?? 0);
    $ticket_key = sanitize_key((string) ($line['ticket_key'] ?? ''));
    if ($event_id <= 0 || $ticket_key === '') {
        return false;
    }

    $matched_qty = 0;
    $assigned_qty = 0;

    foreach ((array) WC()->cart->get_cart() as $cart_item) {
        if (!is_array($cart_item)) {
            continue;
        }
        $variation_id = absint($cart_item['variation_id'] ?? 0);
        $item_product_id = $variation_id > 0 ? $variation_id : absint($cart_item['product_id'] ?? 0);
        if ($item_product_id <= 0) {
            continue;
        }

        $ctx = vms_ticketing_v2_claim_context_for_product($item_product_id);
        if (sanitize_key((string) ($ctx['visibility_mode'] ?? '')) !== 'verified') {
            continue;
        }
        if (absint($ctx['event_id'] ?? 0) !== $event_id) {
            continue;
        }
        if (sanitize_key((string) ($ctx['ticket_key'] ?? '')) !== $ticket_key) {
            continue;
        }
        if (empty($ctx['require_assignee_email'])) {
            continue;
        }

        $qty = max(0, absint($cart_item['quantity'] ?? 0));
        if ($qty <= 0) {
            continue;
        }
        $matched_qty += $qty;

        $rows = vms_ticketing_v2_cart_item_claim_assignments($cart_item);
        $assigned_qty += count($rows);
    }

    return ($matched_qty > 0 && $assigned_qty >= $matched_qty);
}

/**
 * @param array<int,array{seat:int,assignee_email:string}> $assignments
 * @param array{
 *   existing_counts?:array<string,int>,
 *   source?:string,
 *   log_results?:bool
 * } $options
 * @return array{
 *   ok:bool,
 *   message:string,
 *   reason_code:string,
 *   assignments:array<int,array<string,mixed>>,
 *   context:array<string,mixed>
 * }
 */
function vms_ticketing_v2_validate_claim_assignments(int $product_id, int $qty, array $assignments, int $buyer_user_id = 0, array $options = array()): array
{
    $product_id = absint($product_id);
    $qty = max(0, absint($qty));
    $buyer_user_id = absint($buyer_user_id);
    $source = sanitize_key((string) ($options['source'] ?? 'seat_assignment'));
    $log_results = !isset($options['log_results']) || !empty($options['log_results']);

    $context = vms_ticketing_v2_claim_context_for_product($product_id);
    $visibility_mode = sanitize_key((string) ($context['visibility_mode'] ?? 'public'));
    $require_assignee_email = !empty($context['require_assignee_email']);
    $event_id = absint($context['event_id'] ?? 0);
    $ticket_key = sanitize_key((string) ($context['ticket_key'] ?? ''));
    $legacy_program = sanitize_key((string) ($context['legacy_program'] ?? ''));
    $allowed_programs = (array) ($context['allowed_programs'] ?? array());
    $allow_direct_grants = !empty($context['allow_direct_grants']);
    $claim_grant_type = sanitize_key((string) ($context['claim_grant_type'] ?? 'event_ticket_eligibility'));
    $claims_per_assignee = max(1, absint($context['claims_per_assignee'] ?? 1));
    $program_label_text = vms_ticketing_v2_claim_program_label_text($allowed_programs, $legacy_program);
    $group_product_ids = vms_ticketing_v2_ticket_group_product_ids_from_context($context, $product_id);
    if (empty($group_product_ids)) {
        $group_product_ids = array($product_id);
    }

    $normalized = vms_ticketing_v2_claim_assignments_normalize($assignments);

    if ($visibility_mode !== 'verified' || !$require_assignee_email || $qty <= 0) {
        return array(
            'ok' => true,
            'message' => '',
            'reason_code' => 'ok',
            'assignments' => array(),
            'context' => $context,
        );
    }

    $seat_lookup = array();
    $prepared_rows = array();
    foreach ($normalized as $idx => $assignment) {
        $seat = max(1, absint($assignment['seat'] ?? ($idx + 1)));
        if ($seat > $qty) {
            return array(
                'ok' => false,
                /* translators: %d: number used in this message. */
                'message' => sprintf(__('Ticket assignment %d is out of range for this selection.', 'backstage-venue-manager'), $seat),
                'reason_code' => 'assignment_seat_invalid',
                'assignments' => array(),
                'context' => $context,
            );
        }
        if (isset($seat_lookup[$seat])) {
            return array(
                'ok' => false,
                /* translators: %d: ticket number. */
                'message' => sprintf(__('Ticket %d has multiple email assignments. Keep only one email per ticket.', 'backstage-venue-manager'), $seat),
                'reason_code' => 'assignment_seat_duplicate',
                'assignments' => array(),
                'context' => $context,
            );
        }

        $email = sanitize_email((string) ($assignment['assignee_email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            return array(
                'ok' => false,
                /* translators: %d: ticket number. */
                'message' => sprintf(__('Please enter a valid email for Ticket %d.', 'backstage-venue-manager'), $seat),
                'reason_code' => 'invalid_email',
                'assignments' => array(),
                'context' => $context,
            );
        }

        $seat_lookup[$seat] = true;
        $prepared_rows[] = array(
            'seat' => $seat,
            'assignee_email' => $email,
        );
    }
    $normalized = $prepared_rows;

    if (count($normalized) > $qty) {
        return array(
            'ok' => false,
            'message' => __('Too many email assignments were provided for the selected ticket quantity.', 'backstage-venue-manager'),
            'reason_code' => 'assignment_count_excess',
            'assignments' => array(),
            'context' => $context,
        );
    }

    if ($event_id <= 0 || $ticket_key === '') {
        return array(
            'ok' => false,
            'message' => __('Could not validate ticket assignments for this ticket right now. Please refresh and try again.', 'backstage-venue-manager'),
            'reason_code' => 'context_missing',
            'assignments' => array(),
            'context' => $context,
        );
    }

    $existing_counts = array();
    if (isset($options['existing_counts']) && is_array($options['existing_counts'])) {
        foreach ((array) $options['existing_counts'] as $email_key => $count) {
            $email_key = strtolower(trim((string) $email_key));
            if ($email_key === '') {
                continue;
            }
            $existing_counts[$email_key] = max(0, absint($count));
        }
    }

    $buyer_auto_fill_count = 0;
    if (count($normalized) < $qty && $buyer_user_id > 0) {
        $buyer_user = get_userdata($buyer_user_id);
        if ($buyer_user instanceof WP_User) {
            $buyer_email = sanitize_email((string) $buyer_user->user_email);
            if ($buyer_email !== '' && is_email($buyer_email)) {
                $buyer_resolved = function_exists('vms_ticketing_claims_resolve_eligibility')
                    ? (array) vms_ticketing_claims_resolve_eligibility(array(
                        'user_id' => (int) $buyer_user->ID,
                        'event_id' => $event_id,
                        'ticket_product_id' => $product_id,
                        'ticket_key' => $ticket_key,
                        'legacy_program' => $legacy_program,
                        'allowed_programs' => $allowed_programs,
                        'allow_direct_grants' => $allow_direct_grants,
                        'grant_type' => $claim_grant_type,
                    ))
                    : array(
                        'eligible' => false,
                        'reason_code' => 'resolver_unavailable',
                        'message' => __('Eligibility resolver is unavailable.', 'backstage-venue-manager'),
                        'matched_rule_path' => '',
                        'matched_grant_id' => 0,
                    );

                if (!empty($buyer_resolved['eligible'])) {
                    $buyer_limit = function_exists('vms_ticketing_v2_assignee_claims_per_event_limit')
                        ? vms_ticketing_v2_assignee_claims_per_event_limit($context, $buyer_user, $buyer_resolved)
                        : max(1, $claims_per_assignee);
                    $buyer_limit = max(1, absint($buyer_limit));
                    $buyer_email_key = strtolower($buyer_email);
                    $buyer_consumed_qty = function_exists('vms_ticketing_v2_assignee_consumed_qty_for_event')
                        ? absint(vms_ticketing_v2_assignee_consumed_qty_for_event($event_id, $buyer_email, $group_product_ids))
                        : 0;
                    $buyer_existing_qty = absint($existing_counts[$buyer_email_key] ?? 0);
                    $buyer_assigned_qty = 0;
                    foreach ($normalized as $row) {
                        $row_email_key = strtolower(sanitize_email((string) ($row['assignee_email'] ?? '')));
                        if ($row_email_key !== '' && $row_email_key === $buyer_email_key) {
                            $buyer_assigned_qty++;
                        }
                    }

                    $buyer_auto_available = max(0, $buyer_limit - $buyer_consumed_qty - $buyer_existing_qty - $buyer_assigned_qty);
                    $missing_qty = max(0, $qty - count($normalized));
                    if ($buyer_auto_available > 0 && $missing_qty > 0) {
                        $missing_seats = array();
                        for ($seat_num = 1; $seat_num <= $qty; $seat_num++) {
                            if (!isset($seat_lookup[$seat_num])) {
                                $missing_seats[] = $seat_num;
                            }
                        }

                        $auto_to_fill = min($missing_qty, $buyer_auto_available, count($missing_seats));
                        for ($i = 0; $i < $auto_to_fill; $i++) {
                            $seat_num = absint($missing_seats[$i] ?? 0);
                            if ($seat_num <= 0) {
                                continue;
                            }
                            $normalized[] = array(
                                'seat' => $seat_num,
                                'assignee_email' => $buyer_email,
                            );
                            $seat_lookup[$seat_num] = true;
                            $buyer_auto_fill_count++;
                        }
                    }
                }
            }
        }
    }

    if (count($normalized) !== $qty) {
        $missing_qty = max(0, $qty - count($normalized));
        $message = __('Please add one approved guest email per selected ticket before adding tickets to your cart.', 'backstage-venue-manager');
        if ($buyer_auto_fill_count > 0 && $missing_qty > 0) {
            $message = sprintf(
                /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                __('Your account automatically applied to %1$d ticket(s). Please enter %2$d additional approved guest email(s) to continue.', 'backstage-venue-manager'),
                $buyer_auto_fill_count,
                $missing_qty
            );
        }
        return array(
            'ok' => false,
            'message' => $message,
            'reason_code' => 'assignment_count_mismatch',
            'assignments' => array(),
            'context' => $context,
        );
    }

    usort($normalized, static function (array $a, array $b): int {
        return absint($a['seat'] ?? 0) <=> absint($b['seat'] ?? 0);
    });

    $local_counts = array();
    $assignee_consumed_cache = array();
    $validated_rows = array();

    foreach ($normalized as $idx => $assignment) {
        $seat = max(1, absint($assignment['seat'] ?? ($idx + 1)));
        $email = sanitize_email((string) ($assignment['assignee_email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            return array(
                'ok' => false,
                /* translators: %d: ticket number. */
                'message' => sprintf(__('Please enter a valid email for Ticket %d.', 'backstage-venue-manager'), $seat),
                'reason_code' => 'invalid_email',
                'assignments' => array(),
                'context' => $context,
            );
        }

        $email_key = strtolower($email);
        $user = get_user_by('email', $email);
        if (!($user instanceof WP_User)) {
            if ($log_results && function_exists('vms_ticketing_claims_log_result')) {
                vms_ticketing_claims_log_result(array(
                    'event_id' => $event_id,
                    'ticket_product_id' => $product_id,
                    'ticket_key' => $ticket_key,
                    'buyer_user_id' => $buyer_user_id,
                    'assignee_user_id' => 0,
                    'assignee_email' => $email,
                    'rule_path' => 'seat_assignment',
                    'direct_grant_id' => 0,
                    'result' => 'failure',
                    'reason_code' => 'account_not_found',
                    'message' => vms_ticketing_v2_claim_assignment_unknown_guest_message(),
                    'context' => array(
                        'source' => $source,
                        'seat' => $seat,
                    ),
                ));
            }
            return array(
                'ok' => false,
                /* translators: 1: number 1 used in this message, 2: value 2 used in this message. */
                'message' => sprintf(__('Ticket %1$d: %2$s', 'backstage-venue-manager'), $seat, vms_ticketing_v2_claim_assignment_unknown_guest_message()),
                'reason_code' => 'account_not_found',
                'assignments' => array(),
                'context' => $context,
            );
        }

        $resolved = function_exists('vms_ticketing_claims_resolve_eligibility')
            ? (array) vms_ticketing_claims_resolve_eligibility(array(
                'user_id' => (int) $user->ID,
                'event_id' => $event_id,
                'ticket_product_id' => $product_id,
                'ticket_key' => $ticket_key,
                'legacy_program' => $legacy_program,
                'allowed_programs' => $allowed_programs,
                'allow_direct_grants' => $allow_direct_grants,
                'grant_type' => $claim_grant_type,
            ))
            : array(
                'eligible' => false,
                'reason_code' => 'resolver_unavailable',
                'message' => __('Eligibility resolver is unavailable.', 'backstage-venue-manager'),
                'matched_rule_path' => '',
                'matched_grant_id' => 0,
            );

        $eligible = !empty($resolved['eligible']);
        $reason_code = sanitize_key((string) ($resolved['reason_code'] ?? ($eligible ? 'ok' : 'not_eligible')));
        $message = sanitize_text_field((string) ($resolved['message'] ?? ''));

        if (!$eligible && $message === '') {
            $message = vms_ticketing_v2_claim_assignment_unapproved_guest_message();
        }

        $assignee_claims_per_assignee = max(1, $claims_per_assignee);
        $consumed_qty = 0;
        $current_count = 0;
        if ($eligible) {
            $assignee_claims_per_assignee = function_exists('vms_ticketing_v2_assignee_claims_per_event_limit')
                ? vms_ticketing_v2_assignee_claims_per_event_limit($context, $user, $resolved)
                : max(1, $claims_per_assignee);
            if (!isset($assignee_consumed_cache[$email_key])) {
                $assignee_consumed_cache[$email_key] = function_exists('vms_ticketing_v2_assignee_consumed_qty_for_event')
                    ? absint(vms_ticketing_v2_assignee_consumed_qty_for_event($event_id, $email, $group_product_ids))
                    : 0;
            }
            $consumed_qty = absint($assignee_consumed_cache[$email_key] ?? 0);
            $current_count = absint($existing_counts[$email_key] ?? 0) + absint($local_counts[$email_key] ?? 0);
            // Multiple seats may use the same approved assignee email when that
            // account's effective event allowance is greater than one. The prior
            // duplicate guard incorrectly blocked seat #2+ before checking the
            // allowance, which made profile overrides/default allowances appear
            // to be ignored.
            $allowed_for_new_assignments = max(0, $assignee_claims_per_assignee - $consumed_qty);
            if (($current_count + 1) > $allowed_for_new_assignments) {
                $eligible = false;
                $reason_code = 'assignee_limit_reached';
                /* translators: %d: number used in this message. */
                $message = sprintf(__('This guest has already used the %d-ticket limit for this event.', 'backstage-venue-manager'), $assignee_claims_per_assignee);
            }
        }

        if ($log_results && function_exists('vms_ticketing_claims_log_result')) {
            vms_ticketing_claims_log_result(array(
                'event_id' => $event_id,
                'ticket_product_id' => $product_id,
                'ticket_key' => $ticket_key,
                'buyer_user_id' => $buyer_user_id,
                'assignee_user_id' => (int) $user->ID,
                'assignee_email' => sanitize_email((string) $user->user_email),
                'rule_path' => sanitize_key((string) ($resolved['matched_rule_path'] ?? '')),
                'direct_grant_id' => absint($resolved['matched_grant_id'] ?? 0),
                'result' => $eligible ? 'success' : 'failure',
                'reason_code' => $reason_code,
                'message' => $message,
                'context' => array(
                    'source' => $source,
                    'seat' => $seat,
                ),
            ));
        }

        if (!$eligible) {
            return array(
                'ok' => false,
                /* translators: 1: number 1 used in this message, 2: value 2 used in this message. */
                'message' => sprintf(__('Ticket %1$d: %2$s', 'backstage-venue-manager'), $seat, $message),
                'reason_code' => $reason_code,
                'assignments' => array(),
                'context' => $context,
            );
        }

        $local_counts[$email_key] = absint($local_counts[$email_key] ?? 0) + 1;
        $remaining_after_seat = max(0, $assignee_claims_per_assignee - $consumed_qty - $current_count - 1);
        $validated_rows[] = array(
            'seat' => $seat,
            'assignee_email' => sanitize_email((string) $user->user_email),
            'assignee_user_id' => (int) $user->ID,
            'rule_path' => sanitize_key((string) ($resolved['matched_rule_path'] ?? '')),
            'direct_grant_id' => absint($resolved['matched_grant_id'] ?? 0),
            'claims_per_assignee' => $assignee_claims_per_assignee,
            'consumed_qty' => $consumed_qty,
            'remaining_qty' => $remaining_after_seat,
        );
    }

    return array(
        'ok' => true,
        'message' => '',
        'reason_code' => 'ok',
        'assignments' => $validated_rows,
        'context' => $context,
    );
}

function vms_ticketing_v2_enforce_ticket_max_qty_for_add(int $product_id, int $request_qty): bool
{
    $ctx = vms_ticketing_v2_resolve_ticket_max_context($product_id);
    $event_id = absint($ctx['event_id'] ?? 0);
    $ticket_key = sanitize_key((string) ($ctx['ticket_key'] ?? ''));
    $limit = max(0, absint($ctx['limit'] ?? 0));
    if ($event_id <= 0 || $ticket_key === '' || $limit <= 0) {
        return true;
    }

    $request_qty = max(1, absint($request_qty));
    $cart_qty = vms_ticketing_v2_cart_qty_for_event_ticket($event_id, $ticket_key);

    $purchased_qty = 0;
    $user_id = is_user_logged_in() ? absint(get_current_user_id()) : 0;
    if ($user_id > 0) {
        $group_ids = vms_ticketing_v2_ticket_group_product_ids_from_context($ctx, $product_id);
        $purchased_qty = vms_ticketing_v2_purchased_ticket_qty_for_user($user_id, $group_ids);
    }

    $remaining = max(0, $limit - $purchased_qty - $cart_qty);
    if ($request_qty > $remaining) {
        $label = __('this ticket', 'backstage-venue-manager');
        if (!vms_ticketing_v2_public_ticket_qualification_removed() && sanitize_key((string) ($ctx['visibility_mode'] ?? '')) === 'verified') {
            $program = sanitize_key((string) ($ctx['program'] ?? ''));
            if ($program !== '') {
                /* translators: %s: human-readable value used in this message. */
                $label = sprintf(__('%s ticket', 'backstage-venue-manager'), vms_ticketing_v2_verified_ticket_program_label($program));
            }
        }
        $message = sprintf(
            /* translators: 1: number 1 used in this message, 2: value 2 used in this message, 3: number 3 used in this message. */
            __('Limit reached for this event: up to %1$d %2$s per customer (%3$d remaining).', 'backstage-venue-manager'),
            $limit,
            $label,
            $remaining
        );
        vms_ticketing_v2_add_limit_notice_once($message);
        return false;
    }

    return true;
}

function vms_ticketing_v2_enforce_ticket_max_qtys_in_cart(): void
{
    if (!function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        return;
    }

    $pairs = array();
    foreach ((array) WC()->cart->get_cart() as $item) {
        if (!is_array($item)) {
            continue;
        }
        $item_pid = absint($item['variation_id'] ?? 0);
        if ($item_pid <= 0) {
            $item_pid = absint($item['product_id'] ?? 0);
        }
        if ($item_pid <= 0) {
            continue;
        }

        $ctx = vms_ticketing_v2_resolve_ticket_max_context($item_pid);
        $event_id = absint($ctx['event_id'] ?? 0);
        $ticket_key = sanitize_key((string) ($ctx['ticket_key'] ?? ''));
        $limit = max(0, absint($ctx['limit'] ?? 0));
        if ($event_id <= 0 || $ticket_key === '' || $limit <= 0) {
            continue;
        }
        $pair_key = $event_id . '|' . $ticket_key;
        if (isset($pairs[$pair_key])) {
            continue;
        }
        $pairs[$pair_key] = array(
            'context' => $ctx,
            'sample_product_id' => $item_pid,
        );
    }

    foreach ($pairs as $pair) {
        if (!is_array($pair) || !isset($pair['context']) || !is_array($pair['context'])) {
            continue;
        }
        $ctx = $pair['context'];
        $limit = max(0, absint($ctx['limit'] ?? 0));
        if ($limit <= 0) {
            continue;
        }

        $event_id = absint($ctx['event_id'] ?? 0);
        $ticket_key = sanitize_key((string) ($ctx['ticket_key'] ?? ''));
        if ($event_id <= 0 || $ticket_key === '') {
            continue;
        }

        $cart_qty = vms_ticketing_v2_cart_qty_for_event_ticket($event_id, $ticket_key);
        if ($cart_qty <= 0) {
            continue;
        }

        $sample_product_id = absint($pair['sample_product_id'] ?? 0);
        $purchased_qty = 0;
        $user_id = is_user_logged_in() ? absint(get_current_user_id()) : 0;
        if ($user_id > 0) {
            $group_ids = vms_ticketing_v2_ticket_group_product_ids_from_context($ctx, $sample_product_id);
            $purchased_qty = vms_ticketing_v2_purchased_ticket_qty_for_user($user_id, $group_ids);
        }

        $allowed_in_cart = max(0, $limit - $purchased_qty);
        if ($cart_qty > $allowed_in_cart) {
            $remaining = 0;
            $label = __('this ticket', 'backstage-venue-manager');
            if (!vms_ticketing_v2_public_ticket_qualification_removed() && sanitize_key((string) ($ctx['visibility_mode'] ?? '')) === 'verified') {
                $program = sanitize_key((string) ($ctx['program'] ?? ''));
                if ($program !== '') {
                    /* translators: %s: human-readable value used in this message. */
                    $label = sprintf(__('%s ticket', 'backstage-venue-manager'), vms_ticketing_v2_verified_ticket_program_label($program));
                }
            }
            $message = sprintf(
                /* translators: 1: number 1 used in this message, 2: value 2 used in this message, 3: number 3 used in this message. */
                __('Limit reached for this event: up to %1$d %2$s per customer (%3$d remaining).', 'backstage-venue-manager'),
                $limit,
                $label,
                $remaining
            );
            vms_ticketing_v2_add_limit_notice_once($message);
        }
    }
}




function vms_ticketing_v2_product_cancelled_event_id(int $product_id): int
{
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return 0;
    }

    $event_id = function_exists('vms_ticketing_v2_resolve_event_id_for_product')
        ? absint(vms_ticketing_v2_resolve_event_id_for_product($product_id))
        : 0;
    if ($event_id > 0 && bvmgr_tec_is_cancelled_event($event_id)) {
        return $event_id;
    }

    return 0;
}

function vms_ticketing_v2_cancelled_event_sales_notice(int $event_id = 0): string
{
    $title = $event_id > 0 ? trim((string) get_the_title($event_id)) : '';
    if ($title !== '') {
        /* translators: %s: human-readable value used in this message. */
        return sprintf(__('Ticket sales are closed because “%s” has been cancelled.', 'backstage-venue-manager'), $title);
    }
    return __('Ticket sales are closed because this event has been cancelled.', 'backstage-venue-manager');
}

function vms_ticketing_v2_linked_tec_event_id_for_plan(int $plan_id): int
{
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return 0;
    }

    if (function_exists('vms_ticketing_b_get_linked_tec_event_id')) {
        $linked = absint(vms_ticketing_b_get_linked_tec_event_id($plan_id));
        if ($linked > 0) {
            return $linked;
        }
    }

    $tec_key = function_exists('bvmgr_ticketing_b_meta_key')
        ? (string) bvmgr_ticketing_b_meta_key('tec_event_id', '_vms_tec_event_id')
        : '_vms_tec_event_id';
    if ($tec_key === '') {
        $tec_key = '_vms_tec_event_id';
    }

    return absint(get_post_meta($plan_id, $tec_key, true));
}

function vms_ticketing_v2_cart_item_context_snapshot(array $cart_item): array
{
    $snapshot = isset($cart_item['_vms_ticketing_context']) && is_array($cart_item['_vms_ticketing_context'])
        ? $cart_item['_vms_ticketing_context']
        : array();

    if (!isset($snapshot['event_plan_id']) && isset($cart_item['_vms_event_plan_id'])) {
        $snapshot['event_plan_id'] = $cart_item['_vms_event_plan_id'];
    }
    if (!isset($snapshot['tec_event_id']) && isset($cart_item['_vms_tec_event_id'])) {
        $snapshot['tec_event_id'] = $cart_item['_vms_tec_event_id'];
    }
    if (!isset($snapshot['product_role']) && isset($cart_item['_vms_product_role'])) {
        $snapshot['product_role'] = $cart_item['_vms_product_role'];
    }
    if (!isset($snapshot['ticket_key']) && isset($cart_item['_vms_ticket_key'])) {
        $snapshot['ticket_key'] = $cart_item['_vms_ticket_key'];
    }
    if (!isset($snapshot['event_title_snapshot']) && isset($cart_item['_vms_event_title_snapshot'])) {
        $snapshot['event_title_snapshot'] = $cart_item['_vms_event_title_snapshot'];
    }
    if (!isset($snapshot['event_when_snapshot']) && isset($cart_item['_vms_event_when_snapshot'])) {
        $snapshot['event_when_snapshot'] = $cart_item['_vms_event_when_snapshot'];
    }
    if (!isset($snapshot['event_date_snapshot']) && isset($cart_item['_vms_event_date_snapshot'])) {
        $snapshot['event_date_snapshot'] = $cart_item['_vms_event_date_snapshot'];
    }

    return is_array($snapshot) ? $snapshot : array();
}

function vms_ticketing_v2_capture_cart_item_context(array $cart_item_data, int $product_id, int $variation_id): array
{
    $pid = absint($variation_id > 0 ? $variation_id : $product_id);
    if ($pid <= 0) {
        return $cart_item_data;
    }

    $plan_id = function_exists('vms_ticketing_v2_resolve_plan_id_for_ticket_product')
        ? absint(vms_ticketing_v2_resolve_plan_id_for_ticket_product($pid))
        : 0;
    $event_id = function_exists('vms_ticketing_v2_resolve_event_id_for_product')
        ? absint(vms_ticketing_v2_resolve_event_id_for_product($pid))
        : 0;
    $role = sanitize_key((string) vms_ticketing_v2_meta_get($pid, vms_ticketing_v2_product_meta_key('product_role')));
    if ($role === '' && function_exists('vms_ticketing_v2_product_is_entitlement') && vms_ticketing_v2_product_is_entitlement($pid)) {
        $role = 'entitlement';
    }
    if ($role === '' && ($plan_id > 0 || $event_id > 0)) {
        $role = 'ga_ticket';
    }
    if (!vms_ticketing_v2_role_supports_event_name_suffix($role)) {
        return $cart_item_data;
    }

    if ($event_id <= 0 && $plan_id > 0) {
        $event_id = vms_ticketing_v2_linked_tec_event_id_for_plan($plan_id);
    }

    $event_snapshot = function_exists('vms_ticketing_v2_resolve_event_snapshot_for_product')
        ? vms_ticketing_v2_resolve_event_snapshot_for_product($pid)
        : array();
    $event_when = $event_id > 0 ? vms_ticketing_v2_format_event_when($event_id) : '';

    $context = array(
        'event_plan_id' => $plan_id,
        'tec_event_id' => $event_id,
        'product_role' => $role,
        'ticket_key' => sanitize_key((string) vms_ticketing_v2_meta_get($pid, '_vms_ticket_key')),
        'event_title_snapshot' => trim((string) ($event_snapshot['title'] ?? '')),
        'event_date_snapshot' => trim((string) ($event_snapshot['date'] ?? '')),
        'event_when_snapshot' => trim((string) $event_when),
    );

    $cart_item_data['_vms_ticketing_context'] = $context;
    $cart_item_data['_vms_event_plan_id'] = $plan_id;
    $cart_item_data['_vms_tec_event_id'] = $event_id;
    $cart_item_data['_vms_product_role'] = $role;
    if ($context['ticket_key'] !== '') {
        $cart_item_data['_vms_ticket_key'] = $context['ticket_key'];
    }
    if ($context['event_title_snapshot'] !== '') {
        $cart_item_data['_vms_event_title_snapshot'] = $context['event_title_snapshot'];
    }
    if ($context['event_date_snapshot'] !== '') {
        $cart_item_data['_vms_event_date_snapshot'] = $context['event_date_snapshot'];
    }
    if ($context['event_when_snapshot'] !== '') {
        $cart_item_data['_vms_event_when_snapshot'] = $context['event_when_snapshot'];
    }

    return $cart_item_data;
}

function vms_ticketing_v2_sale_context_status_label(string $status): string
{
    $status = sanitize_key($status);
    if ($status === '') {
        return __('unavailable', 'backstage-venue-manager');
    }
    if (function_exists('bvmgr_event_plan_status_label')) {
        return (string) bvmgr_event_plan_status_label($status);
    }

    return ucwords(str_replace('_', ' ', $status));
}

function vms_ticketing_v2_sale_context_title(int $plan_id = 0, int $event_id = 0): string
{
    $title = $event_id > 0 ? trim((string) get_the_title($event_id)) : '';
    if ($title === '' && $plan_id > 0) {
        $title = trim((string) get_the_title($plan_id));
    }
    return $title;
}

function vms_ticketing_v2_sale_context_message(string $code, int $plan_id = 0, int $event_id = 0, array $context = array()): string
{
    $code = sanitize_key($code);
    $plan_id = absint($plan_id);
    $event_id = absint($event_id);
    $title = vms_ticketing_v2_sale_context_title($plan_id, $event_id);
    $status = sanitize_key((string) ($context['plan_status'] ?? ''));
    $product_label = trim((string) ($context['product_label'] ?? ''));

    switch ($code) {
        case 'event_cancelled':
            return vms_ticketing_v2_cancelled_event_sales_notice($event_id);
        case 'event_past':
            if ($title !== '') {
                /* translators: %s: human-readable value used in this message. */
                return sprintf(__('Ticket sales are closed because “%s” has already ended.', 'backstage-venue-manager'), $title);
            }
            return __('Ticket sales are closed because this event has already ended.', 'backstage-venue-manager');
        case 'product_detached':
            if ($product_label !== '') {
                /* translators: %s: human-readable value used in this message. */
                return sprintf(__('“%s” is no longer available for this event. Please remove it from your cart and refresh the event page.', 'backstage-venue-manager'), $product_label);
            }
            return __('This ticket is no longer available for this event. Please remove it from your cart and refresh the event page.', 'backstage-venue-manager');
        case 'ticketing_disabled':
            if ($title !== '') {
                /* translators: %s: human-readable value used in this message. */
                return sprintf(__('Ticket sales are currently unavailable for “%s”. Please remove it from your cart and refresh the event page.', 'backstage-venue-manager'), $title);
            }
            return __('Ticket sales are currently unavailable for this event. Please remove it from your cart and refresh the event page.', 'backstage-venue-manager');
        case 'event_plan_unpublished':
        case 'event_unpublished':
        case 'event_missing':
        case 'invalid_event_plan':
            if ($title !== '') {
                /* translators: %s: human-readable value used in this message. */
                return sprintf(__('Ticket sales are not live for “%s”. Please remove it from your cart and refresh the event page.', 'backstage-venue-manager'), $title);
            }
            return __('Ticket sales are not live for this event. Please remove it from your cart and refresh the event page.', 'backstage-venue-manager');
        case 'event_plan_not_live':
            $status_label = vms_ticketing_v2_sale_context_status_label($status);
            if ($title !== '') {
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                return sprintf(__('Ticket sales are closed because “%1$s” is currently %2$s.', 'backstage-venue-manager'), $title, $status_label);
            }
            /* translators: %s: human-readable value used in this message. */
            return sprintf(__('Ticket sales are closed because this event is currently %s.', 'backstage-venue-manager'), $status_label);
    }

    return __('This event can no longer be purchased. Please remove it from your cart and refresh the event page.', 'backstage-venue-manager');
}

function vms_ticketing_v2_validate_product_sale_context(int $product_id, int $plan_id = 0, int $tec_event_id = 0, string $role = ''): array
{
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    $tec_event_id = absint($tec_event_id);
    $role = sanitize_key($role);
    $product_label = $product_id > 0 ? trim((string) get_the_title($product_id)) : '';

    if ($product_id > 0) {
        if ($plan_id <= 0 && function_exists('vms_ticketing_v2_resolve_plan_id_for_ticket_product')) {
            $plan_id = absint(vms_ticketing_v2_resolve_plan_id_for_ticket_product($product_id));
        }
        if ($tec_event_id <= 0 && function_exists('vms_ticketing_v2_resolve_event_id_for_product')) {
            $tec_event_id = absint(vms_ticketing_v2_resolve_event_id_for_product($product_id));
        }
    }

    if ($plan_id > 0 && $tec_event_id <= 0) {
        $tec_event_id = vms_ticketing_v2_linked_tec_event_id_for_plan($plan_id);
    }

    if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
        $code = 'invalid_event_plan';
        return array(
            'ok' => false,
            'code' => $code,
            'http' => 409,
            'plan_id' => $plan_id,
            'event_id' => $tec_event_id,
            'message' => vms_ticketing_v2_sale_context_message($code, $plan_id, $tec_event_id),
        );
    }

    if ((string) get_post_status($plan_id) !== 'publish') {
        $code = 'event_plan_unpublished';
        return array(
            'ok' => false,
            'code' => $code,
            'http' => 403,
            'plan_id' => $plan_id,
            'event_id' => $tec_event_id,
            'message' => vms_ticketing_v2_sale_context_message($code, $plan_id, $tec_event_id),
        );
    }

    $plan_status = function_exists('bvmgr_event_plan_current_internal_status')
        ? sanitize_key((string) bvmgr_event_plan_current_internal_status($plan_id, 'generic'))
        : 'draft';
    if ($plan_status === 'cancelled') {
        $code = 'event_cancelled';
        return array(
            'ok' => false,
            'code' => $code,
            'http' => 410,
            'plan_id' => $plan_id,
            'event_id' => $tec_event_id,
            'message' => vms_ticketing_v2_sale_context_message($code, $plan_id, $tec_event_id, array('plan_status' => $plan_status)),
        );
    }
    if ($plan_status !== 'published') {
        $code = 'event_plan_not_live';
        return array(
            'ok' => false,
            'code' => $code,
            'http' => 403,
            'plan_id' => $plan_id,
            'event_id' => $tec_event_id,
            'message' => vms_ticketing_v2_sale_context_message($code, $plan_id, $tec_event_id, array('plan_status' => $plan_status)),
        );
    }

    if (function_exists('bvmgr_event_plan_is_ticketing_enabled') && !bvmgr_event_plan_is_ticketing_enabled($plan_id)) {
        $code = 'ticketing_disabled';
        return array(
            'ok' => false,
            'code' => $code,
            'http' => 403,
            'plan_id' => $plan_id,
            'event_id' => $tec_event_id,
            'message' => vms_ticketing_v2_sale_context_message($code, $plan_id, $tec_event_id),
        );
    }

    if ($product_id > 0 && !vms_ticketing_v2_product_matches_current_plan_runtime($product_id, $plan_id, $role)) {
        $code = 'product_detached';
        return array(
            'ok' => false,
            'code' => $code,
            'http' => 409,
            'plan_id' => $plan_id,
            'event_id' => $tec_event_id,
            'message' => vms_ticketing_v2_sale_context_message($code, $plan_id, $tec_event_id, array('product_label' => $product_label)),
        );
    }

    if ($tec_event_id <= 0 || get_post_type($tec_event_id) !== 'tribe_events') {
        $code = 'event_missing';
        return array(
            'ok' => false,
            'code' => $code,
            'http' => 409,
            'plan_id' => $plan_id,
            'event_id' => $tec_event_id,
            'message' => vms_ticketing_v2_sale_context_message($code, $plan_id, $tec_event_id),
        );
    }

    if (!in_array((string) get_post_status($tec_event_id), array('publish', 'future'), true)) {
        $code = 'event_unpublished';
        return array(
            'ok' => false,
            'code' => $code,
            'http' => 403,
            'plan_id' => $plan_id,
            'event_id' => $tec_event_id,
            'message' => vms_ticketing_v2_sale_context_message($code, $plan_id, $tec_event_id),
        );
    }

    if ($product_id > 0 && !vms_ticketing_v2_atomic_product_matches_event($product_id, $tec_event_id)) {
        $plan_event_id = vms_ticketing_v2_linked_tec_event_id_for_plan($plan_id);
        if ($plan_event_id !== $tec_event_id) {
            $code = 'product_detached';
            return array(
                'ok' => false,
                'code' => $code,
                'http' => 409,
                'plan_id' => $plan_id,
                'event_id' => $tec_event_id,
                'message' => vms_ticketing_v2_sale_context_message($code, $plan_id, $tec_event_id, array('product_label' => $product_label)),
            );
        }
    }

    if (bvmgr_tec_is_cancelled_event($tec_event_id)) {
        $code = 'event_cancelled';
        return array(
            'ok' => false,
            'code' => $code,
            'http' => 410,
            'plan_id' => $plan_id,
            'event_id' => $tec_event_id,
            'message' => vms_ticketing_v2_sale_context_message($code, $plan_id, $tec_event_id),
        );
    }

    if (function_exists('vms_ticketing_v2_event_is_past') && vms_ticketing_v2_event_is_past($tec_event_id, $plan_id)) {
        $code = 'event_past';
        return array(
            'ok' => false,
            'code' => $code,
            'http' => 410,
            'plan_id' => $plan_id,
            'event_id' => $tec_event_id,
            'message' => vms_ticketing_v2_sale_context_message($code, $plan_id, $tec_event_id),
        );
    }

    return array(
        'ok' => true,
        'code' => '',
        'http' => 200,
        'plan_id' => $plan_id,
        'event_id' => $tec_event_id,
        'role' => $role,
    );
}

function vms_ticketing_v2_product_matches_current_plan_runtime(int $product_id, int $plan_id, string $role = ''): bool
{
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    $role = sanitize_key($role);
    if ($product_id <= 0 || $plan_id <= 0) {
        return false;
    }

    $sync = function_exists('vms_ticketing_v2_get_sync') ? vms_ticketing_v2_get_sync($plan_id) : array();
    $map = (is_array($sync) && isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
    $has_runtime_map = false;

    if ($role === 'ga_ticket') {
        $ticket_rows = (isset($map['tickets']) && is_array($map['tickets'])) ? $map['tickets'] : array();
        $ga_row = (isset($map['ga']) && is_array($map['ga'])) ? $map['ga'] : array();
        $has_runtime_map = !empty($ticket_rows) || !empty($ga_row);

        if (function_exists('vms_ticketing_v2_sync_ticket_row_for_product') && !empty(vms_ticketing_v2_sync_ticket_row_for_product($product_id, $plan_id))) {
            return true;
        }
        if (function_exists('vms_ticketing_v2_disabled_ticket_config_for_product')) {
            $disabled_state = vms_ticketing_v2_disabled_ticket_config_for_product($product_id, $plan_id);
            if (!empty($disabled_state['disabled'])) {
                return true;
            }
        }
    } elseif ($role === 'entitlement') {
        $ent_rows = (isset($map['entitlements']) && is_array($map['entitlements'])) ? $map['entitlements'] : array();
        $has_runtime_map = !empty($ent_rows);
        foreach ($ent_rows as $ent_row) {
            if (!is_array($ent_row)) {
                continue;
            }
            $mapped_pid = absint($ent_row['woo_product_id'] ?? 0);
            if ($mapped_pid > 0 && vms_ticketing_v2_pid_matches_mapped($product_id, $mapped_pid)) {
                return true;
            }
        }
    }

    if ($has_runtime_map) {
        return false;
    }

    return vms_ticketing_v2_atomic_product_matches_plan($product_id, $plan_id);
}

function vms_ticketing_v2_enforce_live_event_items_in_cart(): void
{
    if (is_admin() && !wp_doing_ajax()) {
        return;
    }
    if (!function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart || !function_exists('wc_add_notice')) {
        return;
    }

    $seen_messages = array();
    foreach ((array) WC()->cart->get_cart() as $cart_item) {
        if (!is_array($cart_item)) {
            continue;
        }

        $variation_id = absint($cart_item['variation_id'] ?? 0);
        $product_id = $variation_id > 0 ? $variation_id : absint($cart_item['product_id'] ?? 0);
        if ($product_id <= 0) {
            continue;
        }

        $snapshot = vms_ticketing_v2_cart_item_context_snapshot($cart_item);
        $role = sanitize_key((string) ($snapshot['product_role'] ?? ''));
        if ($role === '') {
            $role = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, vms_ticketing_v2_product_meta_key('product_role')));
        }
        if ($role === '' && function_exists('vms_ticketing_v2_product_is_entitlement') && vms_ticketing_v2_product_is_entitlement($product_id)) {
            $role = 'entitlement';
        }
        if ($role === '' && (!empty($snapshot['event_plan_id']) || !empty($snapshot['tec_event_id']) || vms_ticketing_v2_product_cancelled_event_id($product_id) > 0)) {
            $role = 'ga_ticket';
        }
        if (!vms_ticketing_v2_role_supports_event_name_suffix($role)) {
            continue;
        }

        $validation = vms_ticketing_v2_validate_product_sale_context(
            $product_id,
            absint($snapshot['event_plan_id'] ?? 0),
            absint($snapshot['tec_event_id'] ?? 0),
            $role
        );
        if (!empty($validation['ok'])) {
            continue;
        }

        $message = sanitize_text_field((string) ($validation['message'] ?? ''));
        if ($message === '' || isset($seen_messages[$message])) {
            continue;
        }

        $seen_messages[$message] = true;
        vms_ticketing_v2_add_limit_notice_once($message);
    }
}

function vms_ticketing_v2_enforce_no_cancelled_event_items_in_cart(): void
{
    vms_ticketing_v2_enforce_live_event_items_in_cart();
}
add_action('woocommerce_check_cart_items', 'vms_ticketing_v2_enforce_no_cancelled_event_items_in_cart', 4);
add_action('woocommerce_checkout_process', 'vms_ticketing_v2_enforce_no_cancelled_event_items_in_cart', 4);

function vms_ticketing_v2_early_price_cap_notice(array $ctx, int $requested_qty = 0): string
{
    $ticket = (isset($ctx['ticket']) && is_array($ctx['ticket'])) ? $ctx['ticket'] : array();
    $state = (isset($ctx['state']) && is_array($ctx['state'])) ? $ctx['state'] : array();
    $label = trim(vms_ticketing_v2_plain_display_text($ticket['title'] ?? ($ticket['label'] ?? __('ticket', 'backstage-venue-manager'))));
    if ($label === '') {
        $label = __('ticket', 'backstage-venue-manager');
    }
    $remaining = (int) ($state['remaining_qty'] ?? 0);

    if ($remaining <= 0) {
        /* translators: %s: formatted price. */
        return sprintf(__('Early Bird pricing for “%s” is sold out. Please refresh the event page to continue at the regular price.', 'backstage-venue-manager'), $label);
    }

    return sprintf(
        /* translators: 1: number 1 used in this message, 2: value 2 used in this message. */
        _n('Only %1$d Early Bird ticket remains for “%2$s”. Please reduce that ticket quantity or refresh the event page.', 'Only %1$d Early Bird tickets remain for “%2$s”. Please reduce that ticket quantity or refresh the event page.', $remaining, 'backstage-venue-manager'),
        $remaining,
        $label
    );
}

function vms_ticketing_v2_early_price_cap_context_for_product(int $product_id): array
{
    $product_id = absint($product_id);
    if ($product_id <= 0 || !function_exists('vms_ticketing_v2_get_ticket_config_for_product_price') || !function_exists('vms_ticketing_v2_get_ticket_early_price_state')) {
        return array();
    }

    $ticket = vms_ticketing_v2_get_ticket_config_for_product_price($product_id);
    if (empty($ticket) || !is_array($ticket)) {
        return array();
    }
    $ticket['_vms_runtime_product_id'] = $product_id;
    $ticket['woo_product_id'] = $product_id;

    $state = vms_ticketing_v2_get_ticket_early_price_state($ticket);
    $cap = max(0, absint($state['early_price_cap'] ?? 0));
    if ($cap <= 0 || empty($state['valid']) || empty($state['active_by_date'])) {
        return array();
    }

    return array(
        'product_id' => $product_id,
        'ticket' => $ticket,
        'state' => $state,
    );
}

function vms_ticketing_v2_cart_qty_for_product_id(int $product_id, string $exclude_cart_item_key = ''): int
{
    $product_id = absint($product_id);
    if ($product_id <= 0 || !function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        return 0;
    }

    $qty = 0;
    foreach ((array) WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if ($exclude_cart_item_key !== '' && (string) $cart_item_key === $exclude_cart_item_key) {
            continue;
        }
        if (!is_array($cart_item)) {
            continue;
        }
        $variation_id = absint($cart_item['variation_id'] ?? 0);
        $cart_product_id = $variation_id > 0 ? $variation_id : absint($cart_item['product_id'] ?? 0);
        if ($cart_product_id === $product_id) {
            $qty += max(0, absint($cart_item['quantity'] ?? 0));
        }
    }

    return max(0, absint($qty));
}

function vms_ticketing_v2_validate_early_price_cap_for_add(int $product_id, int $quantity): bool
{
    if (!function_exists('wc_add_notice')) {
        return true;
    }

    $ctx = vms_ticketing_v2_early_price_cap_context_for_product($product_id);
    if (empty($ctx)) {
        return true;
    }

    $quantity = max(1, absint($quantity));
    $state = (array) ($ctx['state'] ?? array());
    $remaining = (int) ($state['remaining_qty'] ?? -1);
    if ($remaining <= 0) {
        return true;
    }

    $cart_qty = vms_ticketing_v2_cart_qty_for_product_id($product_id);
    $available_to_add = max(0, $remaining - $cart_qty);
    if ($quantity <= $available_to_add) {
        return true;
    }

    wc_add_notice(vms_ticketing_v2_early_price_cap_notice($ctx, $quantity), 'error');
    return false;
}

function vms_ticketing_v2_enforce_early_price_caps_in_cart(): void
{
    if (!function_exists('wc_add_notice') || !function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        return;
    }

    $qty_by_product = array();
    foreach ((array) WC()->cart->get_cart() as $cart_item) {
        if (!is_array($cart_item)) {
            continue;
        }
        $variation_id = absint($cart_item['variation_id'] ?? 0);
        $product_id = $variation_id > 0 ? $variation_id : absint($cart_item['product_id'] ?? 0);
        $qty = max(0, absint($cart_item['quantity'] ?? 0));
        if ($product_id <= 0 || $qty <= 0) {
            continue;
        }
        $qty_by_product[$product_id] = max(0, absint($qty_by_product[$product_id] ?? 0)) + $qty;
    }

    $seen = array();
    foreach ($qty_by_product as $product_id => $cart_qty) {
        $ctx = vms_ticketing_v2_early_price_cap_context_for_product((int) $product_id);
        if (empty($ctx)) {
            continue;
        }
        $state = (array) ($ctx['state'] ?? array());
        $remaining = (int) ($state['remaining_qty'] ?? -1);
        if ($remaining <= 0 || $cart_qty <= $remaining) {
            continue;
        }
        $message = vms_ticketing_v2_early_price_cap_notice($ctx, (int) $cart_qty);
        if (isset($seen[$message])) {
            continue;
        }
        $seen[$message] = true;
        wc_add_notice($message, 'error');
    }
}

function vms_ticketing_v2_validate_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array()) {
    if (!$passed) {
        return $passed;
    }
    if (!function_exists('wc_add_notice') || !function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        return $passed;
    }

    $pid = absint($variation_id ? $variation_id : $product_id);
    if ($pid <= 0) {
        return $passed;
    }

    $plan_id = absint(vms_ticketing_v2_meta_get($pid, vms_ticketing_v2_product_meta_key('event_plan_id')));
    $role = (string) vms_ticketing_v2_meta_get($pid, vms_ticketing_v2_product_meta_key('product_role'));

    // Legacy fallback: infer role for older add-on products.
    if ($role === '') {
        $sr_qual = (string) vms_ticketing_v2_meta_get($pid, '_sr_addon_qualifier');
        $sr_type = (string) vms_ticketing_v2_meta_get($pid, '_sr_addon_type');
        $sr_req  = (string) vms_ticketing_v2_meta_get($pid, '_sr_required_qualifiers_per_unit');
        $sr_unit = (string) vms_ticketing_v2_meta_get($pid, '_sr_addon_unit_label');
        if ($sr_qual === 'yes') {
            $role = 'ga_ticket';
        } elseif ($sr_type !== '' || $sr_req !== '' || $sr_unit !== '') {
            $role = 'entitlement';
        }
    }

    // Fallback for Event Tickets products: map tec_event_id to a plan when plan marker is missing.
    if ($plan_id <= 0) {
        $tec_event_id = absint(vms_ticketing_v2_meta_get($pid, '_tribe_wooticket_for_event'));
        if ($tec_event_id > 0 && function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id')) {
            $plan_id = bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id);
        }
    }

    // If product markers are missing (common on legacy/adopted products), derive role from the plan sync map.
    if ($plan_id > 0 && $role === '' && function_exists('vms_ticketing_v2_get_sync')) {
        $sync = vms_ticketing_v2_get_sync((int) $plan_id);
        $map  = (is_array($sync) && isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();

        $ticket_map = (isset($map['tickets']) && is_array($map['tickets'])) ? $map['tickets'] : array();
        foreach ($ticket_map as $ticket_row) {
            if (!is_array($ticket_row)) {
                continue;
            }
            $mapped_ticket_pid = absint($ticket_row['woo_product_id'] ?? 0);
            if ($mapped_ticket_pid > 0 && vms_ticketing_v2_pid_matches_mapped($pid, $mapped_ticket_pid)) {
                $role = 'ga_ticket';
                break;
            }
        }

        if ($role === '') {
            $mapped_ga_pid = absint($map['ga']['woo_product_id'] ?? 0);
            if ($mapped_ga_pid > 0 && vms_ticketing_v2_pid_matches_mapped($pid, $mapped_ga_pid)) {
                $role = 'ga_ticket';
            } else {
                $emap = (isset($map['entitlements']) && is_array($map['entitlements'])) ? $map['entitlements'] : array();
                foreach ($emap as $ent_row) {
                    if (!is_array($ent_row)) continue;
                    $mapped_ent_pid = absint($ent_row['woo_product_id'] ?? 0);
                    if ($mapped_ent_pid > 0 && vms_ticketing_v2_pid_matches_mapped($pid, $mapped_ent_pid)) {
                        $role = 'entitlement';
                        break;
                    }
                }
            }
        }
    }

    if (vms_ticketing_v2_role_supports_event_name_suffix($role)) {
        $sale_context = vms_ticketing_v2_validate_product_sale_context($pid, $plan_id, 0, $role);
        if (empty($sale_context['ok'])) {
            wc_add_notice((string) ($sale_context['message'] ?? __('This event can no longer be purchased. Please refresh the event page and try again.', 'backstage-venue-manager')), 'error');
            return false;
        }
        $plan_id = absint($sale_context['plan_id'] ?? $plan_id);
    }

    if ($plan_id > 0 && $role === 'ga_ticket') {
        $disabled_ticket_state = function_exists('vms_ticketing_v2_disabled_ticket_config_for_product')
            ? vms_ticketing_v2_disabled_ticket_config_for_product($pid, $plan_id)
            : array('disabled' => false);
        if (!empty($disabled_ticket_state['disabled'])) {
            wc_add_notice(vms_ticketing_v2_disabled_ticket_notice_text($disabled_ticket_state), 'error');
            return false;
        }

        $verified_ctx = vms_ticketing_v2_resolve_verified_ticket_context($pid);
            $visibility_mode = sanitize_key((string) ($verified_ctx['visibility_mode'] ?? 'public'));
            if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
                $visibility_mode = 'public';
            }
            $verified_program = sanitize_key((string) ($verified_ctx['program'] ?? ''));
            $allowed_programs = function_exists('vms_ticketing_v2_normalize_allowed_programs')
                ? vms_ticketing_v2_normalize_allowed_programs($verified_ctx['allowed_programs'] ?? array(), $verified_program)
                : ($verified_program !== '' ? array($verified_program) : array());
            $allow_direct_grants = function_exists('vms_ticketing_v2_truthy')
                ? vms_ticketing_v2_truthy($verified_ctx['allow_direct_grants'] ?? false, false)
                : vms_ticketing_v2_meta_truthy($verified_ctx['allow_direct_grants'] ?? false, false);
            $claim_grant_type = sanitize_key((string) ($verified_ctx['claim_grant_type'] ?? 'event_ticket_eligibility'));
            if (!in_array($claim_grant_type, vms_ticketing_v2_claim_grant_type_values(), true)) {
                $claim_grant_type = 'event_ticket_eligibility';
            }
            $claims_per_assignee = max(0, absint($verified_ctx['claims_per_assignee'] ?? 1));
            if ($claims_per_assignee <= 0) {
                $claims_per_assignee = 1;
            }
            $require_assignee_email = function_exists('vms_ticketing_v2_truthy')
                ? vms_ticketing_v2_truthy($verified_ctx['require_assignee_email'] ?? true, true)
                : vms_ticketing_v2_meta_truthy($verified_ctx['require_assignee_email'] ?? true, true);
            $event_id = absint($verified_ctx['event_id'] ?? 0);
            $ticket_key = sanitize_key((string) ($verified_ctx['ticket_key'] ?? ''));
            if ($verified_program === '' && !empty($allowed_programs)) {
                $verified_program = (string) $allowed_programs[0];
            }

            if ($visibility_mode === 'login' && !is_user_logged_in()) {
                wc_add_notice(__('Please log in to purchase this ticket.', 'backstage-venue-manager'), 'error');
                return false;
            }

            if ($visibility_mode === 'verified') {
                $incoming_claim_assignments = vms_ticketing_v2_claim_assignments_normalize($cart_item_data['vms_claim_assignments'] ?? array());
                $program_label = vms_ticketing_v2_claim_program_label_text($allowed_programs, $verified_program);

                if (!is_user_logged_in()) {
                    $guest_message = $program_label !== ''
                        /* translators: %s: human-readable value used in this message. */
                        ? sprintf(__('This ticket requires %s verification. Log in and submit verification first.', 'backstage-venue-manager'), $program_label)
                        : __('This ticket requires account verification. Log in and submit verification first.', 'backstage-venue-manager');
                    if (empty($allowed_programs) && $allow_direct_grants) {
                        $guest_message = __('This ticket requires event-specific account approval. Log in to continue.', 'backstage-venue-manager');
                    }
                    wc_add_notice(
                        $guest_message,
                        'error'
                    );
                    return false;
                }

                $eligibility = vms_ticketing_v2_resolve_claim_eligibility_for_user(
                    (int) get_current_user_id(),
                    $event_id,
                    $pid,
                    $ticket_key,
                    $verified_program,
                    $allowed_programs,
                    $allow_direct_grants,
                    $claim_grant_type
                );

                if (function_exists('vms_ticketing_claims_log_result')) {
                    vms_ticketing_claims_log_result(array(
                        'event_id' => $event_id,
                        'ticket_product_id' => $pid,
                        'ticket_key' => $ticket_key,
                        'buyer_user_id' => (int) get_current_user_id(),
                        'assignee_user_id' => (int) get_current_user_id(),
                        'assignee_email' => (string) wp_get_current_user()->user_email,
                        'rule_path' => sanitize_key((string) ($eligibility['matched_rule_path'] ?? '')),
                        'direct_grant_id' => absint($eligibility['matched_grant_id'] ?? 0),
                        'result' => !empty($eligibility['eligible']) ? 'success' : 'failure',
                        'reason_code' => sanitize_key((string) ($eligibility['reason_code'] ?? '')),
                        'message' => sanitize_text_field((string) ($eligibility['message'] ?? '')),
                        'context' => array(
                            'source' => 'add_to_cart_gate',
                            'product_id' => $pid,
                            'legacy_program' => $verified_program,
                            'allowed_programs' => $allowed_programs,
                            'allow_direct_grants' => $allow_direct_grants ? 1 : 0,
                            'claim_grant_type' => $claim_grant_type,
                            'claims_per_assignee' => $claims_per_assignee,
                            'require_assignee_email' => $require_assignee_email ? 1 : 0,
                        ),
                    ));
                }

                if (empty($eligibility['eligible'])) {
                    $message = sanitize_text_field((string) ($eligibility['message'] ?? ''));
                    if ($message === '') {
                        $message = sprintf(
                            /* translators: %s: human-readable value used in this message. */
                            __('Verification required for this ticket (%s). Submit your ID once for automatic recognition.', 'backstage-venue-manager'),
                            $program_label
                        );
                    }
                    wc_add_notice(
                        $message,
                        'error'
                    );
                    return false;
                }

                if ($require_assignee_email) {
                    $existing_counts = vms_ticketing_v2_cart_assignee_usage_for_event($event_id, $ticket_key);
                    $assignment_result = vms_ticketing_v2_validate_claim_assignments(
                        $pid,
                        absint($quantity),
                        $incoming_claim_assignments,
                        (int) get_current_user_id(),
                        array(
                            'source' => 'add_to_cart_assignment',
                            'log_results' => true,
                            'existing_counts' => $existing_counts,
                        )
                    );
                    if (empty($assignment_result['ok'])) {
                        $assignment_message = sanitize_text_field((string) ($assignment_result['message'] ?? ''));
                        if ($assignment_message === '') {
                            $assignment_message = __('Please add one approved guest email per selected ticket before adding tickets to your cart.', 'backstage-venue-manager');
                        }
                        wc_add_notice($assignment_message, 'error');
                        return false;
                    }
                }

            }

        if (!vms_ticketing_v2_validate_early_price_cap_for_add($pid, absint($quantity))) {
            return false;
        }

        if (!vms_ticketing_v2_enforce_ticket_max_qty_for_add($pid, absint($quantity))) {
            return false;
        }

        if (!vms_ticketing_v2_validate_ticket_ratio_for_add($pid, $plan_id, absint($quantity))) {
            return false;
        }

        return $passed;
    }

    if ($plan_id <= 0 || $role !== 'entitlement') {
        return $passed;
    }

    $cfg = array();
    if (function_exists('vms_ticketing_v2_get_config')) {
        $cfg = vms_ticketing_v2_get_config($plan_id);
    }

    $is_admin_user = function_exists('current_user_can') ? current_user_can('manage_options') : false;
    if (!vms_ticketing_v2_ga_is_on_sale_now($cfg) && !$is_admin_user) {
        wc_add_notice(__('Reserved add-ons are not available until tickets go on sale.', 'backstage-venue-manager'), 'error');
        return;
    }

    $ent_id = sanitize_key((string) vms_ticketing_v2_meta_get($pid, vms_ticketing_v2_product_meta_key('ticketing_entitlement_id')));
    $ent_cfg = null;
    if ($ent_id !== '' && is_array($cfg) && !empty($cfg)) {
        $ent_cfg = vms_ticketing_v2_find_entitlement_cfg($cfg, $ent_id);
        if (is_array($ent_cfg) && empty($ent_cfg['enabled'])) {
            wc_add_notice(__('That reserved add-on is not available for this event.', 'backstage-venue-manager'), 'error');
            return false;
        }
    }

    $elig = vms_ticketing_v2_resolve_eligibility_for_product($pid, $plan_id, is_array($ent_cfg) ? $ent_cfg : null);
    $pool_key = sanitize_key((string) ($elig['pool_key'] ?? ''));
    $pool_min = absint($elig['min_ga_per_unit'] ?? 0);
    $allow_without_ga = !empty($elig['allow_without_ga']);
    $pool_hard_max = absint($elig['pool_max_total'] ?? 0);

    if ($pool_key === '') {
        return $passed;
    }

    if ($pool_min <= 0 && $pool_hard_max <= 0) {
        return $passed;
    }

    // Pool-wide GA requirement: strictest min_ga_per_unit among entitlements in this pool (when config is available).
    if (is_array($cfg) && !empty($cfg)) {
        $ents = (isset($cfg['entitlements']) && is_array($cfg['entitlements'])) ? $cfg['entitlements'] : array();
        foreach ($ents as $e) {
            if (!is_array($e) || empty($e['enabled'])) {
                continue;
            }
            $e_elig = (isset($e['eligibility']) && is_array($e['eligibility'])) ? $e['eligibility'] : array();
            if (sanitize_key((string) ($e_elig['pool_key'] ?? '')) !== $pool_key) {
                continue;
            }
            $e_pool_max = absint($e_elig['pool_max_total'] ?? 0);
            if ($e_pool_max > 0) {
                $pool_hard_max = ($pool_hard_max > 0) ? min($pool_hard_max, $e_pool_max) : $e_pool_max;
            }
            if (empty($e_elig['allow_without_ga'])) {
                $pool_min = max($pool_min, absint($e_elig['min_ga_per_unit'] ?? 0));
            }
        }
    }

    $scan = vms_ticketing_v2_cart_scan();
    $ga_qty_raw = (int) (($scan['ga_qty_by_plan'][$plan_id] ?? 0));
    $ga_qty = vms_ticketing_v2_effective_ga_qty_for_plan($plan_id, $ga_qty_raw);
    $prior_history = function_exists('vms_ticketing_v2_prior_addon_history_for_plan')
        ? vms_ticketing_v2_prior_addon_history_for_plan($plan_id, is_array($cfg) ? $cfg : array())
        : array('qualifying_qty' => 0, 'pool_qty_by_key' => array());
    $ga_qty += max(0, absint($prior_history['qualifying_qty'] ?? 0));
    $prior_pool_qty_by_key = (isset($prior_history['pool_qty_by_key']) && is_array($prior_history['pool_qty_by_key']))
        ? $prior_history['pool_qty_by_key']
        : array();
    $qualifying_ticket_label = vms_ticketing_v2_resolve_qualifying_ticket_label($plan_id);

    if (!$allow_without_ga && $pool_min > 0 && $ga_qty < $pool_min) {
        $label = ucwords(str_replace('_', ' ', (string) $pool_key));
        $ticket_phrase = vms_ticketing_v2_qualifying_ticket_phrase($qualifying_ticket_label, $pool_min);
        /* translators: 1: value 1 used in this message, 2: number 2 used in this message, 3: value 3 used in this message, 4: value 4 used in this message. */
        wc_add_notice(sprintf(__('“%1$s” requires at least %2$d %3$s. Add more %3$s or remove this reservation.', 'backstage-venue-manager'), $label, $pool_min, $ticket_phrase), 'error');
        return false;
    }

    // Current pool quantity in cart (use the same eligibility resolver so legacy products are counted).
    $pool_qty = max(0, absint($prior_pool_qty_by_key[$pool_key] ?? 0));
    foreach ((array) ($scan['ent_lines'] ?? array()) as $line) {
        if (!is_array($line)) {
            continue;
        }
        if (absint($line['plan_id'] ?? 0) !== $plan_id) {
            continue;
        }
        $cart_pid = absint($line['product_id'] ?? 0);
        if ($cart_pid <= 0) {
            continue;
        }
        $cart_ent_id = sanitize_key((string) ($line['entitlement_id'] ?? ''));
        $cart_ent_cfg = null;
        if ($cart_ent_id !== '' && is_array($cfg) && !empty($cfg)) {
            $cart_ent_cfg = vms_ticketing_v2_find_entitlement_cfg($cfg, $cart_ent_id);
            if (is_array($cart_ent_cfg) && empty($cart_ent_cfg['enabled'])) {
                continue;
            }
        }

        $cart_elig = vms_ticketing_v2_resolve_eligibility_for_product($cart_pid, $plan_id, is_array($cart_ent_cfg) ? $cart_ent_cfg : null);
        if (sanitize_key((string) ($cart_elig['pool_key'] ?? '')) !== $pool_key) {
            continue;
        }
        if (!empty($cart_elig['allow_without_ga'])) {
            continue;
        }

        $pool_qty += absint($line['qty'] ?? 0);
    }

    $allowed = -1;
    if (!$allow_without_ga && $pool_min > 0) {
        $allowed = ($ga_qty > 0) ? intdiv($ga_qty, $pool_min) : 0;
    }
    if ($pool_hard_max > 0) {
        $allowed = ($allowed < 0) ? $pool_hard_max : min($allowed, $pool_hard_max);
    }
    if ($allowed < 0) {
        return $passed;
    }

    $req_qty = absint($quantity);
    if ($req_qty < 1) {
        $req_qty = 1;
    }

    if (($pool_qty + $req_qty) > $allowed) {
        $label = ucwords(str_replace('_', ' ', (string) $pool_key));
        if (!$allow_without_ga && $pool_min > 0) {
            $required_total = max(0, ($pool_qty + $req_qty) * $pool_min);
            $missing = max(0, $required_total - $ga_qty);
            if ($missing > 0) {
                $ticket_phrase = vms_ticketing_v2_qualifying_ticket_phrase($qualifying_ticket_label, $missing);
                /* translators: 1: number 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message. */
                wc_add_notice(sprintf(__('Your reserved spots require %1$d more %2$s. Add more %2$s or remove one or more reserved spots.', 'backstage-venue-manager'), $missing, $ticket_phrase), 'error');
            } else {
                $ticket_phrase = vms_ticketing_v2_qualifying_ticket_phrase($qualifying_ticket_label, 2);
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                wc_add_notice(sprintf(__('Your reserved spots currently require more %1$s. Add more %1$s or remove one or more reserved spots.', 'backstage-venue-manager'), $ticket_phrase), 'error');
            }
        } else {
            /* translators: 1: maximum allowed quantity, 2: add-on pool label. */
            wc_add_notice(sprintf(__('You can only select up to %1$d total add-ons in “%2$s”. Please choose fewer.', 'backstage-venue-manager'), $allowed, $label), 'error');
        }
        return false;
    }

    return $passed;
}

function vms_ticketing_v2_enforce_claim_assignments_in_cart(): void
{
    if (!function_exists('wc_add_notice') || !function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        return;
    }
    if (vms_ticketing_v2_public_ticket_qualification_removed()) {
        return;
    }

    $buyer_user_id = is_user_logged_in() ? (int) get_current_user_id() : 0;
    $notices_seen = array();
    $cart_needs_session_write = false;

    foreach ((array) WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (!is_array($cart_item)) {
            continue;
        }

        $variation_id = absint($cart_item['variation_id'] ?? 0);
        $item_product_id = $variation_id > 0 ? $variation_id : absint($cart_item['product_id'] ?? 0);
        if ($item_product_id <= 0) {
            continue;
        }

        $ctx = vms_ticketing_v2_claim_context_for_product($item_product_id);
        if (sanitize_key((string) ($ctx['visibility_mode'] ?? '')) !== 'verified' || empty($ctx['require_assignee_email'])) {
            continue;
        }

        $qty = max(0, absint($cart_item['quantity'] ?? 0));
        if ($qty <= 0) {
            continue;
        }

        $event_id = absint($ctx['event_id'] ?? 0);
        $ticket_key = sanitize_key((string) ($ctx['ticket_key'] ?? ''));
        if ($buyer_user_id <= 0) {
            continue;
        }

        $buyer_eligibility = vms_ticketing_v2_resolve_claim_eligibility_for_user(
            $buyer_user_id,
            $event_id,
            $item_product_id,
            $ticket_key,
            sanitize_key((string) ($ctx['legacy_program'] ?? '')),
            (array) ($ctx['allowed_programs'] ?? array()),
            !empty($ctx['allow_direct_grants']),
            sanitize_key((string) ($ctx['claim_grant_type'] ?? 'event_ticket_eligibility'))
        );
        if (empty($buyer_eligibility['eligible'])) {
            continue;
        }

        $assignments = vms_ticketing_v2_cart_item_claim_assignments($cart_item);
        $existing_counts = vms_ticketing_v2_cart_assignee_usage_for_event($event_id, $ticket_key, (string) $cart_item_key);

        $assignment_result = vms_ticketing_v2_validate_claim_assignments(
            $item_product_id,
            $qty,
            $assignments,
            $buyer_user_id,
            array(
                'source' => 'cart_revalidate',
                'log_results' => true,
                'existing_counts' => $existing_counts,
            )
        );

        if (!empty($assignment_result['ok'])) {
            $validated_assignments = vms_ticketing_v2_claim_assignments_normalize($assignment_result['assignments'] ?? array());
            $existing_snapshot = vms_ticketing_v2_claim_assignments_normalize($assignments);
            if (!empty($validated_assignments)) {
                $existing_json = wp_json_encode($existing_snapshot);
                $validated_json = wp_json_encode($validated_assignments);
                if ($existing_json !== $validated_json && isset(WC()->cart->cart_contents[$cart_item_key]) && is_array(WC()->cart->cart_contents[$cart_item_key])) {
                    WC()->cart->cart_contents[$cart_item_key]['vms_claim_assignments'] = $validated_assignments;
                    WC()->cart->cart_contents[$cart_item_key]['vms_claim_assignment_uid'] = function_exists('wp_generate_uuid4')
                        ? wp_generate_uuid4()
                        : uniqid('vms_claim_', true);
                    $cart_needs_session_write = true;
                }
            }
            continue;
        }

        $message = sanitize_text_field((string) ($assignment_result['message'] ?? ''));
        if ($message === '') {
            $message = __('Please add one approved guest email per special-access ticket in your cart.', 'backstage-venue-manager');
        }

        $product = function_exists('wc_get_product') ? wc_get_product($item_product_id) : null;
        $product_name = $product ? sanitize_text_field((string) $product->get_name()) : '';
        if ($product_name !== '') {
            /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
            $message = sprintf(__('Ticket "%1$s": %2$s', 'backstage-venue-manager'), $product_name, $message);
        }

        if (isset($notices_seen[$message])) {
            continue;
        }
        $notices_seen[$message] = true;
        wc_add_notice($message, 'error');
    }

    if ($cart_needs_session_write && method_exists(WC()->cart, 'set_session')) {
        WC()->cart->set_session();
    }
}

add_action('woocommerce_check_cart_items', 'vms_ticketing_v2_enforce_early_price_caps_in_cart', 16);
add_action('woocommerce_check_cart_items', 'vms_ticketing_v2_enforce_ticket_max_qtys_in_cart', 17);
add_action('woocommerce_check_cart_items', 'vms_ticketing_v2_enforce_verified_ticket_limits_in_cart', 18);
add_action('woocommerce_check_cart_items', 'vms_ticketing_v2_enforce_ticket_ratio_rules_in_cart', 18);
add_action('woocommerce_check_cart_items', 'vms_ticketing_v2_enforce_claim_assignments_in_cart', 19);
add_action('woocommerce_check_cart_items', 'vms_ticketing_v2_enforce_ticket_visibility_rules', 19);
add_action('woocommerce_check_cart_items', 'vms_ticketing_v2_enforce_cart_rules');
add_action('woocommerce_check_cart_items', 'vms_ticketing_v2_maybe_gate_cart_checkout_button', 25);
add_action('woocommerce_checkout_process', 'vms_ticketing_v2_enforce_early_price_caps_in_cart', 16);
add_action('woocommerce_checkout_process', 'vms_ticketing_v2_enforce_ticket_max_qtys_in_cart', 17);
add_action('woocommerce_checkout_process', 'vms_ticketing_v2_enforce_verified_ticket_limits_in_cart', 18);
add_action('woocommerce_checkout_process', 'vms_ticketing_v2_enforce_ticket_ratio_rules_in_cart', 18);
add_action('woocommerce_checkout_process', 'vms_ticketing_v2_enforce_claim_assignments_in_cart', 19);
add_action('woocommerce_checkout_process', 'vms_ticketing_v2_enforce_ticket_visibility_rules', 19);
add_action('woocommerce_checkout_process', 'vms_ticketing_v2_enforce_cart_rules', 20);
add_action('woocommerce_store_api_cart_errors', 'vms_ticketing_v2_store_api_add_checkout_blocker_errors', 20, 2);
add_action('woocommerce_store_api_checkout_update_order_meta', 'vms_ticketing_v2_store_api_checkout_update_order_meta', 20, 1);
add_action('woocommerce_store_api_validate_add_to_cart', 'vms_ticketing_v2_store_api_validate_add_to_cart', 20, 2);
add_filter('woocommerce_available_payment_gateways', 'vms_ticketing_v2_filter_available_payment_gateways', 20);
add_filter('woocommerce_order_button_html', 'vms_ticketing_v2_filter_checkout_order_button_html', 20);
add_filter('woocommerce_add_to_cart_validation', 'vms_ticketing_v2_validate_add_to_cart', 20, 6);
add_filter('woocommerce_add_cart_item_data', 'vms_ticketing_v2_capture_cart_item_context', 20, 3);

add_filter('woocommerce_get_item_data', 'vms_ticketing_v2_add_event_meta_to_cart_item', 20, 2);
add_action('woocommerce_checkout_create_order_line_item', 'vms_ticketing_v2_add_event_meta_to_order_item', 20, 4);
add_filter('woocommerce_cart_item_name', 'vms_ticketing_v2_filter_cart_item_name', 20, 3);
add_filter('woocommerce_order_item_name', 'vms_ticketing_v2_filter_order_item_name', 20, 3);

function vms_ticketing_v2_wallet_pdf_local_asset_paths_enabled(array $template_vars = array()): bool
{
    $enabled = true;

    /**
     * Allow local/staging operators to disable the filesystem-path mitigation if a site-specific
     * TCPDF/ImageMagick edge case appears during validation.
     */
    return (bool) apply_filters('vms_ticketing_v2_wallet_pdf_local_asset_paths_enabled', $enabled, $template_vars);
}

function vms_ticketing_v2_wallet_pdf_local_attachment_path(int $attachment_id, array $preferred_sizes = array()): string
{
    $attachment_id = absint($attachment_id);
    if ($attachment_id <= 0) {
        return '';
    }

    $original = (string) get_attached_file($attachment_id, true);
    if ($original === '' || !is_readable($original)) {
        return '';
    }

    $original_realpath = realpath($original);
    if ($original_realpath !== false) {
        $original = $original_realpath;
    }

    if (!wp_attachment_is_image($attachment_id)) {
        return $original;
    }

    foreach ($preferred_sizes as $size) {
        $size = is_string($size) ? trim($size) : '';
        if ($size === '' || $size === 'full') {
            continue;
        }

        $intermediate = image_get_intermediate_size($attachment_id, $size);
        if (!is_array($intermediate) || empty($intermediate['file'])) {
            continue;
        }

        $candidate = str_replace(wp_basename($original), (string) $intermediate['file'], $original);
        if (!is_readable($candidate)) {
            continue;
        }

        $candidate_realpath = realpath($candidate);
        return $candidate_realpath !== false ? $candidate_realpath : $candidate;
    }

    return $original;
}

function vms_ticketing_v2_wallet_pdf_header_image_path(): string
{
    if (!class_exists(\TEC\Tickets_Wallet_Plus\Passes\Pdf\Settings\Header_Image_Setting::class) || !function_exists('tribe')) {
        return '';
    }

    $setting = tribe(\TEC\Tickets_Wallet_Plus\Passes\Pdf\Settings\Header_Image_Setting::class);
    if (!is_object($setting) || !method_exists($setting, 'get_value')) {
        return '';
    }

    return vms_ticketing_v2_wallet_pdf_local_attachment_path((int) $setting->get_value());
}

function vms_ticketing_v2_wallet_pdf_post_image_path(int $event_id): string
{
    $event_id = absint($event_id);
    if ($event_id <= 0) {
        return '';
    }

    $thumbnail_id = get_post_thumbnail_id($event_id);
    if (!$thumbnail_id) {
        return '';
    }

    return vms_ticketing_v2_wallet_pdf_local_attachment_path((int) $thumbnail_id, array('medium_large', 'large'));
}

function vms_ticketing_v2_wallet_pdf_qr_link(array $attendee): string
{
    if (
        !class_exists(\TEC\Tickets\QR\Connector::class)
        || !function_exists('tribe')
        || empty($attendee['qr_ticket_id'])
        || empty($attendee['event_id'])
        || empty($attendee['security_code'])
    ) {
        return '';
    }

    $connector = tribe(\TEC\Tickets\QR\Connector::class);
    if (!is_object($connector) || !method_exists($connector, 'get_checkin_url')) {
        return '';
    }

    return (string) $connector->get_checkin_url(
        (int) $attendee['qr_ticket_id'],
        (int) $attendee['event_id'],
        (string) $attendee['security_code']
    );
}

function vms_ticketing_v2_wallet_pdf_existing_qr_path(string $link): string
{
    $link = trim($link);
    if ($link === '') {
        return '';
    }

    $upload_dir = wp_upload_dir();
    $base_dir = isset($upload_dir['basedir']) ? (string) $upload_dir['basedir'] : '';
    if ($base_dir === '') {
        return '';
    }

    $directory = trailingslashit($base_dir) . 'tec-tickets-qr';
    $hash = md5($link);
    $exact = $directory . '/qr_' . $hash . '.png';

    if (is_readable($exact)) {
        $exact_realpath = realpath($exact);
        return $exact_realpath !== false ? $exact_realpath : $exact;
    }

    $matches = glob($directory . '/qr_' . $hash . '*.png');
    if (!is_array($matches) || empty($matches)) {
        return '';
    }

    sort($matches, SORT_NATURAL);
    $match = (string) reset($matches);
    if ($match === '' || !is_readable($match)) {
        return '';
    }

    $match_realpath = realpath($match);
    return $match_realpath !== false ? $match_realpath : $match;
}

function vms_ticketing_v2_wallet_pdf_qr_image_path(array $attendee): string
{
    $link = vms_ticketing_v2_wallet_pdf_qr_link($attendee);
    if ($link === '') {
        return '';
    }

    $existing = vms_ticketing_v2_wallet_pdf_existing_qr_path($link);
    if ($existing !== '') {
        return $existing;
    }

    return '';
}

function vms_ticketing_v2_filter_wallet_pdf_template_vars(array $template_vars): array
{
    if (!vms_ticketing_v2_wallet_pdf_local_asset_paths_enabled($template_vars)) {
        return $template_vars;
    }

    $header_image_path = vms_ticketing_v2_wallet_pdf_header_image_path();
    if ($header_image_path !== '') {
        $template_vars['header_image_url'] = $header_image_path;
    }

    $event_id = 0;
    if (!empty($template_vars['attendee']) && is_array($template_vars['attendee'])) {
        $event_id = absint($template_vars['attendee']['event_id'] ?? 0);
    }
    if ($event_id <= 0 && !empty($template_vars['post']) && is_object($template_vars['post']) && !empty($template_vars['post']->ID)) {
        $event_id = absint($template_vars['post']->ID);
    }

    $post_image_path = vms_ticketing_v2_wallet_pdf_post_image_path($event_id);
    if ($post_image_path !== '') {
        $template_vars['post_image_url'] = $post_image_path;
    }

    if (!empty($template_vars['qr_enabled']) && !empty($template_vars['attendee']) && is_array($template_vars['attendee'])) {
        $qr_image_path = vms_ticketing_v2_wallet_pdf_qr_image_path($template_vars['attendee']);
        if ($qr_image_path !== '') {
            $template_vars['qr_image_url'] = $qr_image_path;
        }
    }

    return $template_vars;
}
add_filter('tec_tickets_wallet_plus_pdf_pass_template_vars', 'vms_ticketing_v2_filter_wallet_pdf_template_vars', 20);

function vms_ticketing_v2_async_woo_ticket_email_hook(): string
{
    return 'vms_ticketing_v2_async_send_woo_ticket_emails';
}

function vms_ticketing_v2_async_woo_ticket_email_group(): string
{
    return 'vms-ticketing';
}

function vms_ticketing_v2_async_woo_ticket_email_dispatch_active(?bool $set = null): bool
{
    static $active = false;

    if ($set !== null) {
        $active = $set;
    }

    return $active;
}

function vms_ticketing_v2_request_key(string $key): string
{
    return bvmgr_request_read_key($_REQUEST, $key); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Woo request routing state only selects cart and checkout behavior.
}

function vms_ticketing_v2_request_has_key(string $key): bool
{
    return isset($_REQUEST[$key]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence-only probe for read-only Woo request routing state.
}

function vms_ticketing_v2_query_text(string $key): string
{
    return bvmgr_request_read_text_field($_GET, $key); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Store API route state only selects request routing behavior.
}

function vms_ticketing_v2_is_wc_ajax_checkout_request(): bool
{
    if (!function_exists('wp_doing_ajax') || !wp_doing_ajax()) {
        return false;
    }

    $ajax_action = vms_ticketing_v2_request_key('wc-ajax');
    return $ajax_action === 'checkout';
}

function vms_ticketing_v2_queue_async_woo_ticket_email(int $order_id): bool
{
    $order_id = absint($order_id);
    if ($order_id <= 0) {
        return false;
    }

    $hook = vms_ticketing_v2_async_woo_ticket_email_hook();
    $args = array($order_id);
    $group = vms_ticketing_v2_async_woo_ticket_email_group();

    if (function_exists('as_has_scheduled_action') && function_exists('as_enqueue_async_action')) {
        if (as_has_scheduled_action($hook, $args, $group)) {
            return true;
        }

        return (int) as_enqueue_async_action($hook, $args, $group, true) > 0;
    }

    if (function_exists('wp_next_scheduled') && wp_next_scheduled($hook, $args)) {
        return true;
    }

    return (bool) wp_schedule_single_event(time() + 5, $hook, $args);
}

function vms_ticketing_v2_run_async_woo_ticket_email(int $order_id): void
{
    $order_id = absint($order_id);
    if ($order_id <= 0 || !class_exists('Tribe__Tickets_Plus__Commerce__WooCommerce__Main')) {
        return;
    }

    vms_ticketing_v2_async_woo_ticket_email_dispatch_active(true);

    try {
        $provider = Tribe__Tickets_Plus__Commerce__WooCommerce__Main::get_instance();
        if (is_object($provider) && method_exists($provider, 'send_tickets_email')) {
            $provider->send_tickets_email($order_id);
        }
    } finally {
        vms_ticketing_v2_async_woo_ticket_email_dispatch_active(false);
    }
}
add_action('vms_ticketing_v2_async_send_woo_ticket_emails', 'vms_ticketing_v2_run_async_woo_ticket_email', 10, 1);

function vms_ticketing_v2_maybe_defer_woo_ticket_email($pre, $to, $tickets, $args, $module)
{
    if ($pre !== null || vms_ticketing_v2_async_woo_ticket_email_dispatch_active()) {
        return $pre;
    }

    if (!vms_ticketing_v2_is_wc_ajax_checkout_request()) {
        return $pre;
    }

    $provider = sanitize_key((string) ($args['provider'] ?? ''));
    $order_id = absint($args['order_id'] ?? 0);
    if ($provider !== 'woo' || $order_id <= 0) {
        return $pre;
    }

    static $queued_orders = array();
    if (isset($queued_orders[$order_id])) {
        return true;
    }

    $queued = vms_ticketing_v2_queue_async_woo_ticket_email($order_id);
    if (!$queued) {
        return $pre;
    }

    $queued_orders[$order_id] = true;
    return true;
}
add_filter('tec_tickets_send_tickets_email_for_attendee_pre', 'vms_ticketing_v2_maybe_defer_woo_ticket_email', 20, 5);

function vms_ticketing_v2_product_role_for_naming(int $product_id): string
{
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return '';
    }

    $meta_key = function_exists('vms_ticketing_v2_product_meta_key')
        ? vms_ticketing_v2_product_meta_key('product_role')
        : '_vms_product_role';

    $role = sanitize_key((string) vms_ticketing_v2_meta_get($product_id, $meta_key));
    if ($role !== '') {
        return $role;
    }

    // Legacy/self-healing fallback: some older TEC ticket products may have the
    // VMS plan + TEC ticket link but lack the newer explicit product_role marker.
    $plan_meta_key = function_exists('vms_ticketing_v2_product_meta_key')
        ? vms_ticketing_v2_product_meta_key('event_plan_id')
        : '_vms_event_plan_id';
    $plan_id = absint(get_post_meta($product_id, $plan_meta_key, true));
    $tec_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
    if ($plan_id > 0 && $tec_event_id > 0) {
        return 'ga_ticket';
    }

    return '';
}

function vms_ticketing_v2_role_supports_event_name_suffix(string $role): bool
{
    return in_array(sanitize_key($role), array('ga_ticket', 'entitlement'), true);
}

function vms_ticketing_v2_resolve_event_id_for_product(int $product_id): int
{
    $product_id = absint($product_id);
    if ($product_id <= 0) return 0;

    // Primary: Event Tickets (Woo) links products to the TEC event post.
    $tec_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
    if ($tec_event_id > 0 && get_post_type($tec_event_id) === 'tribe_events') {
        return $tec_event_id;
    }

    // Secondary: if our stored tec_event_id happens to be the WP post ID.
    if (function_exists('vms_ticketing_v2_product_meta_key')) {
        $maybe = absint(get_post_meta($product_id, vms_ticketing_v2_product_meta_key('tec_event_id'), true));
        if ($maybe > 0 && get_post_type($maybe) === 'tribe_events') {
            return $maybe;
        }
    }

    // Fallback: derive from the linked Event Plan (if present).
    if (function_exists('vms_ticketing_v2_product_meta_key')) {
        $plan_id = absint(get_post_meta($product_id, vms_ticketing_v2_product_meta_key('event_plan_id'), true));
        if ($plan_id > 0) {
            $tec_key = (function_exists('bvmgr_ticketing_b_meta_key') ? bvmgr_ticketing_b_meta_key('tec_event_id', '_vms_tec_event_id') : '_vms_tec_event_id');
            $maybe = absint(get_post_meta($plan_id, $tec_key, true));
            if ($maybe > 0 && get_post_type($maybe) === 'tribe_events') {
                return $maybe;
            }
        }
    }

    return 0;
}

function vms_ticketing_v2_format_event_suffix_date(int $tec_event_id): string
{
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return '';
    }

    if (function_exists('tribe_get_start_date')) {
        $when = (string) tribe_get_start_date($tec_event_id, false, 'M j, Y');
        $when = trim($when);
        if ($when !== '') {
            return $when;
        }
    }

    $raw = (string) get_post_meta($tec_event_id, '_EventStartDate', true);
    if ($raw === '') {
        return '';
    }

    $ts = strtotime($raw);
    if (!$ts) {
        return '';
    }

    return (string) wp_date('M j, Y', $ts, wp_timezone());
}

function vms_ticketing_v2_resolve_event_snapshot_for_product(int $product_id): array
{
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return array(
            'title' => '',
            'date' => '',
            'tec_event_id' => 0,
            'event_plan_id' => 0,
        );
    }

    $plan_id = absint(vms_ticketing_v2_meta_get($product_id, vms_ticketing_v2_product_meta_key('event_plan_id')));
    $tec_event_id = vms_ticketing_v2_resolve_event_id_for_product($product_id);
    $title = $tec_event_id > 0 ? (string) get_the_title($tec_event_id) : '';
    $date = vms_ticketing_v2_format_event_suffix_date($tec_event_id);

    return array(
        'title' => trim($title),
        'date' => trim($date),
        'tec_event_id' => $tec_event_id,
        'event_plan_id' => $plan_id,
    );
}

function vms_ticketing_v2_build_event_name_suffix(string $event_title, string $event_date): string
{
    $event_title = trim($event_title);
    $event_date = trim($event_date);
    if ($event_title === '' || $event_date === '') {
        return '';
    }

    return ' — ' . $event_title . ' (' . $event_date . ')';
}

function vms_ticketing_v2_append_event_to_item_name($name, $product_id, $order_item = null): string
{
    $base_name = (string) $name;
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return $base_name;
    }

    $role = vms_ticketing_v2_product_role_for_naming($product_id);
    if (!vms_ticketing_v2_role_supports_event_name_suffix($role)) {
        return $base_name;
    }

    $event_title = '';
    $event_date = '';
    if (is_object($order_item) && method_exists($order_item, 'get_meta')) {
        $event_title = trim((string) $order_item->get_meta('_vms_event_title_snapshot', true));
        $event_date = trim((string) $order_item->get_meta('_vms_event_date_snapshot', true));
    }

    if ($event_title === '' || $event_date === '') {
        $snapshot = vms_ticketing_v2_resolve_event_snapshot_for_product($product_id);
        $event_title = $event_title !== '' ? $event_title : (string) ($snapshot['title'] ?? '');
        $event_date = $event_date !== '' ? $event_date : (string) ($snapshot['date'] ?? '');
    }

    $suffix = vms_ticketing_v2_build_event_name_suffix($event_title, $event_date);
    if ($suffix === '') {
        return $base_name;
    }

    $plain = html_entity_decode(wp_strip_all_tags($base_name), ENT_QUOTES);
    if (strpos($plain, $suffix) !== false) {
        return $base_name;
    }

    return $base_name . esc_html($suffix);
}

function vms_ticketing_v2_filter_cart_item_name($name, $cart_item, $cart_item_key): string
{
    // Titles are kept clean (no appended event/date) and the event context is displayed via item meta.
    return (string) $name;
}

function vms_ticketing_v2_filter_order_item_name($name, $item, $is_visible): string
{
    // Titles are kept clean (no appended event/date). Order/item context is shown via line-item meta.
    return (string) $name;
}

function vms_ticketing_v2_format_event_when(int $tec_event_id): string
{
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) return '';

    // Prefer TEC helpers when available.
    if (function_exists('tribe_get_start_date')) {
        $when = (string) tribe_get_start_date($tec_event_id, false, 'D, M j, Y g:ia');
        return trim($when);
    }

    // Fallback to raw meta.
    $raw = (string) get_post_meta($tec_event_id, '_EventStartDate', true);
    if ($raw === '') return '';

    $ts = strtotime($raw);
    if (!$ts) return '';

    return (string) wp_date('D, M j, Y g:ia', $ts, wp_timezone());
}

function vms_ticketing_v2_add_event_meta_to_cart_item(array $item_data, array $cart_item): array
{
    $product_id = absint($cart_item['product_id'] ?? 0);
    $snapshot = vms_ticketing_v2_cart_item_context_snapshot($cart_item);
    $variation_id = absint($cart_item['variation_id'] ?? 0);
    if ($variation_id > 0) {
        $product_id = $variation_id;
    }
    if ($product_id <= 0) {
        return $item_data;
    }

    $tec_event_id = vms_ticketing_v2_resolve_event_id_for_product($product_id);
    if ($tec_event_id <= 0) {
        $tec_event_id = absint($snapshot['tec_event_id'] ?? 0);
    }

    $title = $tec_event_id > 0 ? (string) get_the_title($tec_event_id) : '';
    if ($title === '') {
        $title = trim((string) ($snapshot['event_title_snapshot'] ?? ''));
    }

    $when = $tec_event_id > 0 ? vms_ticketing_v2_format_event_when($tec_event_id) : '';
    if ($when === '') {
        $when = trim((string) ($snapshot['event_when_snapshot'] ?? ($snapshot['event_date_snapshot'] ?? '')));
    }

    if ($title !== '') {
        $item_data[] = array(
            'key'   => __('Event', 'backstage-venue-manager'),
            'value' => esc_html($title),
        );
    }

    if ($when !== '') {
        $item_data[] = array(
            'key'   => __('When', 'backstage-venue-manager'),
            'value' => esc_html($when),
        );
    }

    $assignments = vms_ticketing_v2_cart_item_claim_assignments($cart_item);
    if (!empty($assignments)) {
        foreach ($assignments as $assignment) {
            $seat = max(1, absint($assignment['seat'] ?? 0));
            $email = sanitize_email((string) ($assignment['assignee_email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $item_data[] = array(
                /* translators: %d: number used in this message. */
                'key' => sprintf(__('Ticket %d Assignee', 'backstage-venue-manager'), $seat),
                'value' => esc_html($email),
            );
        }
    }

    return $item_data;
}

function vms_ticketing_v2_add_event_meta_to_order_item($item, string $cart_item_key, array $values, $order): void
{
    if (!is_object($item) || !method_exists($item, 'add_meta_data')) return;

    $variation_id = absint($values['variation_id'] ?? 0);
    $product_id = $variation_id > 0 ? $variation_id : absint($values['product_id'] ?? 0);
    if ($product_id <= 0) return;

    $role = vms_ticketing_v2_product_role_for_naming($product_id);
    if (!vms_ticketing_v2_role_supports_event_name_suffix($role)) {
        return;
    }

    $event_snapshot = vms_ticketing_v2_resolve_event_snapshot_for_product($product_id);
    $title = (string) ($event_snapshot['title'] ?? '');
    $event_date = (string) ($event_snapshot['date'] ?? '');
    $tec_event_id = absint($event_snapshot['tec_event_id'] ?? 0);
    $event_plan_id = absint($event_snapshot['event_plan_id'] ?? 0);
    $snapshot = vms_ticketing_v2_cart_item_context_snapshot($values);
    if ($title === '') {
        $title = trim((string) ($snapshot['event_title_snapshot'] ?? ''));
    }
    if ($event_date === '') {
        $event_date = trim((string) ($snapshot['event_date_snapshot'] ?? ''));
    }
    if ($tec_event_id <= 0) {
        $tec_event_id = absint($snapshot['tec_event_id'] ?? 0);
    }
    if ($event_plan_id <= 0) {
        $event_plan_id = absint($snapshot['event_plan_id'] ?? 0);
    }
    $event_when = $tec_event_id > 0 ? vms_ticketing_v2_format_event_when($tec_event_id) : '';
    if ($event_when === '') {
        $event_when = trim((string) ($snapshot['event_when_snapshot'] ?? $event_date));
    }

    // Make GA ticket order/email thumbnails self-healing at checkout time. Entitlement
    // add-ons already have their own image sync path; GA tickets need the ticket row
    // policy applied against the current Event Plan / linked TEC event image.
    if ($role === 'ga_ticket' && $event_plan_id > 0 && function_exists('vms_ticketing_v2_sync_ticket_product_image_with_result')) {
        vms_ticketing_v2_sync_ticket_product_image_with_result($product_id, $event_plan_id);
    }

    // Optional human-facing meta.
    if ($title !== '') {
        $item->add_meta_data(__('Event', 'backstage-venue-manager'), $title, true);
    }
    if ($event_when !== '') {
        $item->add_meta_data(__('When', 'backstage-venue-manager'), $event_when, true);
    }

    // Snapshot meta for stable cart/order/email line-item naming.
    if ($title !== '') {
        $item->add_meta_data('_vms_event_title_snapshot', $title, true);
    }
    if ($event_when !== '') {
        $item->add_meta_data('_vms_event_when_snapshot', $event_when, true);
    }
    if ($event_date !== '') {
        $item->add_meta_data('_vms_event_date_snapshot', $event_date, true);
    }
    if ($event_plan_id > 0) {
        $item->add_meta_data('_vms_event_plan_id', (string) $event_plan_id, true);
    }

    // Hidden meta for debugging / future use.
    if ($tec_event_id > 0) {
        $item->add_meta_data('_vms_tec_event_post_id', (string) $tec_event_id, true);
    }
    $claim_context = function_exists('vms_ticketing_v2_claim_context_for_product')
        ? vms_ticketing_v2_claim_context_for_product($product_id)
        : array();
    $claim_ticket_key = sanitize_key((string) ($claim_context['ticket_key'] ?? ''));
    if ($claim_ticket_key !== '') {
        $item->add_meta_data('_vms_claim_ticket_key', $claim_ticket_key, true);
    }

    $assignments = vms_ticketing_v2_cart_item_claim_assignments($values);
    if (!empty($assignments)) {
        $assignment_snapshot = array();
        foreach ($assignments as $assignment) {
            $seat = max(1, absint($assignment['seat'] ?? 0));
            $email = sanitize_email((string) ($assignment['assignee_email'] ?? ''));
            if ($email === '') {
                continue;
            }
            /* translators: %d: number used in this message. */
            $item->add_meta_data(sprintf(__('Ticket %d Assignee', 'backstage-venue-manager'), $seat), $email, true);
            $assignment_snapshot[] = array(
                'seat' => $seat,
                'assignee_email' => $email,
            );
        }
        if (!empty($assignment_snapshot)) {
            $item->add_meta_data('_vms_claim_assignments', wp_json_encode($assignment_snapshot), true);
        }
    }
}





/**
 * Front-end helper: show Backstage Venue Manager entitlement add-ons on TEC single event pages.
 * Note: Entitlements are Woo products (not Event Tickets ticket types), so they will not appear in the native TEC Tickets block.
 * This file also ships a front-end UX helper that makes add-ons feel like a single submission with the GA ticket form.
 */
add_action('wp_enqueue_scripts', 'vms_ticketing_v2_enqueue_front_bundle', 999);
add_filter('tec_tickets_my_tickets_link_ticket_count_by_type', 'vms_ticketing_v2_filter_my_tickets_link_ticket_count_by_type', 99, 3);

add_action('wp_ajax_vms_ticketing_v2_silent_add', 'vms_ticketing_v2_ajax_silent_add');
add_action('wp_ajax_nopriv_vms_ticketing_v2_silent_add', 'vms_ticketing_v2_ajax_silent_add');
add_action('wp_ajax_vms_ticketing_v2_atomic_add_to_cart', 'vms_ticketing_v2_ajax_atomic_add_to_cart');
add_action('wp_ajax_nopriv_vms_ticketing_v2_atomic_add_to_cart', 'vms_ticketing_v2_ajax_atomic_add_to_cart');
add_action('wp_ajax_vms_ticketing_v2_cart_context', 'vms_ticketing_v2_ajax_cart_context');
add_action('wp_ajax_nopriv_vms_ticketing_v2_cart_context', 'vms_ticketing_v2_ajax_cart_context');
add_filter('wc_add_to_cart_message_html', 'vms_ticketing_v2_suppress_entitlement_added_notice', 10, 3);
add_action('template_redirect', 'vms_ticketing_v2_prune_stale_success_notices', 5);


// Public cancellation UX for TEC single-event pages:
// - Prepend a clear cancellation notice.
// - Add a “Cancelled” overlay on the featured image when possible.
// - Suppress RSVP/ticket forms for cancelled events (public users).
add_filter('the_content', 'vms_tec_prepend_cancelled_notice', 8);
add_filter('post_thumbnail_html', 'vms_tec_cancelled_thumbnail_overlay', 20, 5);
add_filter('tribe_tickets_get_tickets_query_args', 'vms_tec_suppress_tickets_for_cancelled_events', 20);
add_filter('tribe_tickets_rsvp_tickets_form_hook', 'vms_tec_suppress_ticket_forms_for_cancelled_event', 20, 2);
add_filter('tribe_tickets_commerce_tickets_form_hook', 'vms_tec_suppress_ticket_forms_for_cancelled_event', 20, 2);
add_filter('body_class', 'vms_tec_cancelled_event_body_class', 20);

add_filter('tribe_template_before_include_html:tickets/v2/tickets/footer', 'vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount', 20, 4);
add_filter('tribe_tickets_get_tickets_query_args', 'vms_ticketing_v2_filter_disabled_ticket_query_args', 30, 1);
add_filter('the_content', 'vms_ticketing_v2_append_entitlements_to_tec_event', 25);
add_shortcode('vms_reserved_add_ons', 'vms_ticketing_v2_shortcode_reserved_add_ons');

/**
 * Ticket UI mode settings for front-end rendering.
 *
 * @return array{layout:string,layout_override:string,admin_preview:bool,is_admin_user:bool,effective_v2:bool,is_progressive:bool,show_availability:bool,availability_display:string,availability_low_threshold:int,sale_availability_display:string,sale_availability_low_threshold:int,show_safe_mode_notice:bool}
 */
function vms_ticketing_v2_plain_display_text($value): string
{
    return html_entity_decode(sanitize_text_field((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function vms_ticketing_v2_is_legacy_verified_ticket_copy($value): bool
{
    $text = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($text === '') {
        return false;
    }

    return (bool) (
        preg_match('/^Free with approved .+ verification\. Already approved\? Select your ticket here\.?(?: Requires registration\.?)?$/i', $text)
        || preg_match('/^Free after your account is approved\. Already approved\? Select your ticket here\.?(?: Requires registration\.?)?$/i', $text)
        || preg_match('/^Qualified ticket\./i', $text)
        || preg_match('/verification before checkout\./i', $text)
    );
}

function vms_ticketing_v2_filter_my_tickets_link_ticket_count_by_type(array $count_by_type, int $event_id, int $user_id): array
{
    if (is_admin() || !is_user_logged_in()) {
        return $count_by_type;
    }
    if ((function_exists('wp_doing_ajax') && wp_doing_ajax()) || (function_exists('wp_is_json_request') && wp_is_json_request()) || is_feed()) {
        return $count_by_type;
    }
    if (!function_exists('is_singular') || !is_singular('tribe_events')) {
        return $count_by_type;
    }

    $event_id = absint($event_id);
    if ($event_id <= 0 || get_post_type($event_id) !== 'tribe_events') {
        return $count_by_type;
    }

    $current_user_id = (int) get_current_user_id();
    $user_id = absint($user_id);
    if ($current_user_id <= 0 || $user_id <= 0 || $current_user_id !== $user_id) {
        return $count_by_type;
    }

    $active_count = function_exists('vms_ticketing_v2_active_ticket_count_for_event_user')
        ? vms_ticketing_v2_active_ticket_count_for_event_user($event_id, $user_id)
        : -1;
    if ($active_count < 0) {
        return $count_by_type;
    }

    $filtered_counts = $count_by_type;
    foreach ($filtered_counts as $ticket_type => $ticket_data) {
        if (!is_array($ticket_data)) {
            continue;
        }

        $filtered_counts[$ticket_type]['count'] = 0;
    }

    $ticket_entry = (isset($filtered_counts['ticket']) && is_array($filtered_counts['ticket']))
        ? $filtered_counts['ticket']
        : array();

    $ticket_singular = (isset($ticket_entry['singular']) && is_string($ticket_entry['singular']) && $ticket_entry['singular'] !== '')
        ? $ticket_entry['singular']
        : '';
    if ($ticket_singular === '' && function_exists('tribe_get_ticket_label_singular')) {
        $ticket_singular = (string) tribe_get_ticket_label_singular('my-tickets-view-link');
    }
    if ($ticket_singular === '') {
        $ticket_singular = 'Ticket';
    }

    $ticket_plural = (isset($ticket_entry['plural']) && is_string($ticket_entry['plural']) && $ticket_entry['plural'] !== '')
        ? $ticket_entry['plural']
        : '';
    if ($ticket_plural === '' && function_exists('tribe_get_ticket_label_plural')) {
        $ticket_plural = (string) tribe_get_ticket_label_plural('my-tickets-view-link');
    }
    if ($ticket_plural === '') {
        $ticket_plural = 'Tickets';
    }

    $ticket_entry['count'] = max(0, absint($active_count));
    $ticket_entry['singular'] = $ticket_singular;
    $ticket_entry['plural'] = $ticket_plural;
    $filtered_counts['ticket'] = $ticket_entry;

    return $filtered_counts;
}

function vms_ticketing_v2_front_ui_settings(int $plan_id = 0): array
{
    static $cache = array();

    $plan_id = absint($plan_id);
    $cache_key = (string) $plan_id;
    if (isset($cache[$cache_key]) && is_array($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $settings = (array) get_option('vms_settings', array());
    $layout = isset($settings['ticket_ui_layout']) ? sanitize_key((string) $settings['ticket_ui_layout']) : 'classic';
    if (!in_array($layout, array('classic', 'v2', 'progressive'), true)) {
        $layout = 'classic';
    }

    $layout_override = '';
    if ($plan_id > 0) {
        $layout_override = sanitize_key((string) get_post_meta($plan_id, '_vms_ticket_ui_layout_override', true));
        if (!in_array($layout_override, array('', 'classic', 'v2', 'progressive'), true)) {
            $layout_override = '';
        }
        if ($layout_override !== '') {
            $layout = $layout_override;
        }
    }

    $admin_preview = array_key_exists('ticket_ui_v2_admin_preview', $settings)
        ? !empty($settings['ticket_ui_v2_admin_preview'])
        : true;
    $availability_display = isset($settings['ticket_ui_availability_display'])
        ? sanitize_key((string) $settings['ticket_ui_availability_display'])
        : '';
    if (!in_array($availability_display, array('always', 'low', 'hide'), true)) {
        if (array_key_exists('ticket_ui_show_availability', $settings) && empty($settings['ticket_ui_show_availability'])) {
            $availability_display = 'hide';
        } else {
            $availability_display = 'low';
        }
    }
    $availability_low_threshold = max(1, absint($settings['ticket_ui_availability_low_threshold'] ?? 25));

    $sale_availability_display = isset($settings['ticket_ui_sale_availability_display'])
        ? sanitize_key((string) $settings['ticket_ui_sale_availability_display'])
        : 'when_capped';
    if (!in_array($sale_availability_display, array('when_capped', 'low', 'hide'), true)) {
        $sale_availability_display = 'when_capped';
    }
    $sale_availability_low_threshold = max(1, absint($settings['ticket_ui_sale_availability_low_threshold'] ?? 10));

    if ($plan_id > 0) {
        $availability_override = sanitize_key((string) get_post_meta($plan_id, '_vms_ticket_ui_availability_display_override', true));
        if (in_array($availability_override, array('always', 'low', 'hide'), true)) {
            $availability_display = $availability_override;
        }
        $sale_availability_override = sanitize_key((string) get_post_meta($plan_id, '_vms_ticket_ui_sale_availability_display_override', true));
        if (in_array($sale_availability_override, array('when_capped', 'low', 'hide'), true)) {
            $sale_availability_display = $sale_availability_override;
        }
    }

    $show_availability = ($availability_display !== 'hide');
    $is_admin_user = function_exists('current_user_can') ? current_user_can('manage_options') : false;
    $force_legacy = ($layout_override === 'classic');
    $effective_v2 = (!$force_legacy && ($layout === 'v2' || $layout === 'progressive')) || (!$force_legacy && $admin_preview && $is_admin_user);
    // Safe-mode render paths should identify themselves consistently, including on public mobile views.
    // The front-end still decides whether the active render path actually qualifies for the notice.
    $show_safe_mode_notice = ($layout === 'classic');

    $cache[$cache_key] = array(
        'layout' => $layout,
        'layout_override' => $layout_override,
        'admin_preview' => (bool) $admin_preview,
        'is_admin_user' => (bool) $is_admin_user,
        'effective_v2' => (bool) $effective_v2,
        'is_progressive' => ($layout === 'progressive'),
        'show_availability' => (bool) $show_availability,
        'availability_display' => $availability_display,
        'availability_low_threshold' => $availability_low_threshold,
        'sale_availability_display' => $sale_availability_display,
        'sale_availability_low_threshold' => $sale_availability_low_threshold,
        'show_safe_mode_notice' => (bool) $show_safe_mode_notice,
    );
    return $cache[$cache_key];
}

function vms_ticketing_v2_sale_quantity_text(array $sale_state, array $ticket_ui = array()): string
{
    $cap = max(0, absint($sale_state['early_price_cap'] ?? 0));
    $remaining = (int) ($sale_state['remaining_qty'] ?? -1);
    if ($cap <= 0 || $remaining < 0 || $remaining <= 0 || empty($sale_state['active'])) {
        return '';
    }

    $mode = sanitize_key((string) ($ticket_ui['sale_availability_display'] ?? 'when_capped'));
    if (!in_array($mode, array('when_capped', 'low', 'hide'), true)) {
        $mode = 'when_capped';
    }
    if ($mode === 'hide') {
        return '';
    }

    $low_threshold = max(1, absint($ticket_ui['sale_availability_low_threshold'] ?? 10));
    if ($mode === 'low' && $remaining > $low_threshold) {
        return '';
    }

    if ($remaining <= $low_threshold) {
        return sprintf(
            /* translators: %d: number used in this message. */
            _n('Only %d Early Bird ticket left', 'Only %d Early Bird tickets left', $remaining, 'backstage-venue-manager'),
            $remaining
        );
    }

    $early_price = max(0.0, (float) ($sale_state['early_price'] ?? 0));
    $price_text = '';
    if ($early_price > 0 && function_exists('wc_price')) {
        $price_text = trim(wp_strip_all_tags((string) wc_price($early_price)));
    }
    if ($price_text === '' && $early_price > 0) {
        $price_text = '$' . number_format($early_price, 2);
    }

    if ($price_text !== '') {
        /* translators: 1: number 1 used in this message, 2: value 2 used in this message. */
        return sprintf(__('Early Bird: %1$d available at %2$s', 'backstage-venue-manager'), $remaining, $price_text);
    }
    /* translators: %d: early bird. */
    return sprintf(_n('Early Bird: %d available', 'Early Bird: %d available', $remaining, 'backstage-venue-manager'), $remaining);
}

function vms_ticketing_v2_enqueue_front_bundle(): void
{
    if (is_admin()) return;

    $is_event = is_singular('tribe_events');
    $is_cart  = function_exists('is_cart') ? is_cart() : false;
    $is_checkout = function_exists('is_checkout') ? is_checkout() : false;

    if (!$is_event && !$is_cart && !$is_checkout) return;

    $front_script_path = trailingslashit(BVMGR_PLUGIN_PATH) . 'assets/vms-ticketing-front.js';
    $front_script_version = function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '');
    if (is_readable($front_script_path)) {
        $front_script_version = (string) filemtime($front_script_path);
    }

    $fallback_script_path = trailingslashit(BVMGR_PLUGIN_PATH) . 'assets/vms-ticketing-front-fallback.js';
    $fallback_script_version = function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '');
    if (is_readable($fallback_script_path)) {
        $fallback_script_version = (string) filemtime($fallback_script_path);
    }

    $build_stamp = $front_script_version !== '' ? $front_script_version : gmdate('YmdHis');

    // Bundle loads on event/cart/checkout pages for ticketing UI state + cart consistency guards.
    wp_enqueue_script(
        'vms-ticketing-front',
        plugins_url('assets/vms-ticketing-front.js', BVMGR_PLUGIN_FILE),
        array(),
        $front_script_version,
        true
    );

    wp_enqueue_script(
        'vms-ticketing-front-fallback',
        plugins_url('assets/vms-ticketing-front-fallback.js', BVMGR_PLUGIN_FILE),
        array(),
        $fallback_script_version,
        true
    );


    $front_style_deps = array();
    if (function_exists('wp_style_is')) {
        foreach (array('kadence-tribe-css', 'sr-tec-custom-css-css') as $maybe_dep) {
            if (wp_style_is($maybe_dep, 'registered') || wp_style_is($maybe_dep, 'enqueued')) {
                $front_style_deps[] = $maybe_dep;
            }
        }
    }

    wp_enqueue_style(
        'vms-ticketing-front',
        plugins_url('assets/css/vms-ticketing-front.css', BVMGR_PLUGIN_FILE),
        $front_style_deps,
        function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '')
    );

    $tec_event_id = 0;
    if ($is_event) {
        $tec_event_id = (int) get_queried_object_id();
    }

    $plan_id_for_event = 0;
    $cfg_for_event = array();
    $ticket_access_map = array();
    $ticket_remaining_map = array();
    $ticket_price_map = array();
    $event_ticket_product_ids = array();
    $disabled_ticket_product_ids = array();
    $disabled_ticket_map = array();
    $verification_url = '';
    $my_benefits_url = '';
    $recent_claim_emails = array();
    $current_user_email = '';
    $verified_programs = array();
    $verification_program_labels = array();
    $verification_allowance_defaults = array();
    $allowed_claim_grant_types = function_exists('vms_ticketing_claims_allowed_grant_types')
        ? (array) vms_ticketing_claims_allowed_grant_types()
        : array('event_ticket_eligibility', 'event_free_admit', 'credential_benefit_override', 'event_grant');
    $current_user_id = get_current_user_id();
    $current_user = ($current_user_id > 0) ? wp_get_current_user() : null;
    $current_user_email_key = '';
    if ($current_user instanceof WP_User) {
        $current_user_email = sanitize_email((string) $current_user->user_email);
        if ($current_user_email !== '') {
            $current_user_email_key = strtolower($current_user_email);
        }
    }
    $event_is_rsvp = false;

    if ($is_event && $tec_event_id > 0 && function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id') && function_exists('vms_ticketing_v2_get_config')) {
        $plan_id_for_event = bvmgr_ticketing_v2_find_plan_id_by_tec_event_id((int) $tec_event_id);
        if ($plan_id_for_event > 0) {
            $cfg_for_event = vms_ticketing_v2_get_config($plan_id_for_event);
            $sync_for_event = function_exists('vms_ticketing_v2_get_sync') ? vms_ticketing_v2_get_sync($plan_id_for_event) : array();
            $sync_map_for_event = (is_array($sync_for_event) && isset($sync_for_event['map']) && is_array($sync_for_event['map'])) ? $sync_for_event['map'] : array();

            $ticket_cfg_rows = (isset($cfg_for_event['tickets']) && is_array($cfg_for_event['tickets'])) ? $cfg_for_event['tickets'] : array();
            $ticket_sync_rows = (isset($sync_map_for_event['tickets']) && is_array($sync_map_for_event['tickets'])) ? $sync_map_for_event['tickets'] : array();
            $ticket_ui_for_event = vms_ticketing_v2_front_ui_settings((int) $plan_id_for_event);
            $legacy_ga_pid = absint($sync_map_for_event['ga']['woo_product_id'] ?? 0);
            $legacy_ga_ticket_id = absint($sync_map_for_event['ga']['tec_ticket_id'] ?? 0);

            $disabled_ticket_runtime = function_exists('vms_ticketing_v2_disabled_ticket_products_for_plan')
                ? vms_ticketing_v2_disabled_ticket_products_for_plan((int) $plan_id_for_event)
                : array();
            $disabled_ticket_product_ids = (isset($disabled_ticket_runtime['product_ids']) && is_array($disabled_ticket_runtime['product_ids']))
                ? array_values(array_unique(array_filter(array_map('absint', $disabled_ticket_runtime['product_ids']))))
                : array();
            $disabled_ticket_map = (isset($disabled_ticket_runtime['map']) && is_array($disabled_ticket_runtime['map']))
                ? $disabled_ticket_runtime['map']
                : array();

            $primary_ticket_key = '';
            foreach ($ticket_cfg_rows as $row) {
                if (!is_array($row)) continue;
                if (array_key_exists('enabled', $row) && empty($row['enabled'])) continue;
                $k = sanitize_key((string) ($row['ticket_key'] ?? $row['key'] ?? ''));
                if ($k !== '') { $primary_ticket_key = $k; break; }
            }


            foreach ($ticket_cfg_rows as $ticket_idx => $ticket_cfg_row) {
                if (!is_array($ticket_cfg_row)) {
                    continue;
                }
                if (array_key_exists('enabled', $ticket_cfg_row) && empty($ticket_cfg_row['enabled'])) {
                    continue;
                }

                $ticket_key = sanitize_key((string) ($ticket_cfg_row['ticket_key'] ?? $ticket_cfg_row['key'] ?? ''));
                if ($ticket_key === '') {
                    continue;
                }
                $is_primary_ticket = ($primary_ticket_key !== '' && $ticket_key === $primary_ticket_key);

                $visibility_mode = sanitize_key((string) ($ticket_cfg_row['visibility_mode'] ?? 'public'));
                if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
                    $visibility_mode = 'public';
                }

                $verified_program = sanitize_key((string) ($ticket_cfg_row['verified_program'] ?? ''));
                $allowed_programs = function_exists('vms_ticketing_v2_normalize_allowed_programs')
                    ? vms_ticketing_v2_normalize_allowed_programs($ticket_cfg_row['allowed_programs'] ?? array(), $verified_program)
                    : ($verified_program !== '' ? array($verified_program) : array());
                $allow_direct_grants = function_exists('vms_ticketing_v2_truthy')
                    ? vms_ticketing_v2_truthy($ticket_cfg_row['allow_direct_grants'] ?? false, false)
                    : vms_ticketing_v2_meta_truthy($ticket_cfg_row['allow_direct_grants'] ?? false, false);
                $claim_grant_type = sanitize_key((string) ($ticket_cfg_row['claim_grant_type'] ?? 'event_ticket_eligibility'));
                if (!in_array($claim_grant_type, $allowed_claim_grant_types, true)) {
                    $claim_grant_type = 'event_ticket_eligibility';
                }
                $claims_per_assignee = max(0, absint($ticket_cfg_row['claims_per_assignee'] ?? 1));
                if ($claims_per_assignee <= 0) {
                    $claims_per_assignee = 1;
                }
                $require_assignee_email = function_exists('vms_ticketing_v2_truthy')
                    ? vms_ticketing_v2_truthy($ticket_cfg_row['require_assignee_email'] ?? true, true)
                    : vms_ticketing_v2_meta_truthy($ticket_cfg_row['require_assignee_email'] ?? true, true);
                if ($visibility_mode !== 'verified') {
                    $verified_program = '';
                    $allowed_programs = array();
                    $allow_direct_grants = false;
                    $claim_grant_type = 'event_ticket_eligibility';
                    $claims_per_assignee = 1;
                    $require_assignee_email = true;
                } elseif ($verified_program === '' && !empty($allowed_programs)) {
                    $verified_program = (string) $allowed_programs[0];
                }
                $ticket_has_explicit_max_qty = is_array($ticket_cfg_row) && array_key_exists('max_qty_per_order', $ticket_cfg_row);
                $ticket_max_qty = $ticket_has_explicit_max_qty
                    ? max(0, absint($ticket_cfg_row['max_qty_per_order']))
                    : 0;
                if (!$ticket_has_explicit_max_qty && isset($ticket_sync_rows[$ticket_key]) && is_array($ticket_sync_rows[$ticket_key])) {
                    $ticket_max_qty = max(0, absint($ticket_sync_rows[$ticket_key]['max_qty_per_order'] ?? 0));
                }

                $ticket_pid = 0;
                $used_legacy_ga_map = false;
                if (isset($ticket_sync_rows[$ticket_key]) && is_array($ticket_sync_rows[$ticket_key])) {
                    $ticket_pid = absint($ticket_sync_rows[$ticket_key]['woo_product_id'] ?? 0);
                }
                if ($ticket_pid <= 0 && $legacy_ga_pid > 0) {
                    $ticket_label_for_legacy_match = vms_ticketing_v2_sanitize_plain_text_label((string) ($ticket_cfg_row['title'] ?? $ticket_key));
                    $can_claim_legacy_ga = false;
                    if (function_exists('vms_ticketing_v2_should_apply_legacy_ga_map_to_ticket')) {
                        $can_claim_legacy_ga = vms_ticketing_v2_should_apply_legacy_ga_map_to_ticket($ticket_key, $ticket_label_for_legacy_match);
                    } else {
                        $legacy_match_label = strtolower(trim((string) preg_replace('/\s+/u', ' ', $ticket_label_for_legacy_match)));
                        $has_specialized_label = ($legacy_match_label !== '' && preg_match('/\b(early|advance|pre[-\s]?sale|presale|vip|child|children|kid|kids|veteran|police|fire|emt|nurse|teacher|school)\b/u', $legacy_match_label));
                        $can_claim_legacy_ga = !$has_specialized_label && (
                            in_array($ticket_key, array('ga', 'general_admission', 'general-admission'), true)
                            || in_array($legacy_match_label, array('general admission', 'ga admission', 'general admission ticket'), true)
                        );
                    }
                    if ($can_claim_legacy_ga) {
                        $ticket_pid = $legacy_ga_pid;
                        $used_legacy_ga_map = true;
                    }
                }
                if ($ticket_pid <= 0) {
                    continue;
                }
                $event_ticket_product_ids[] = $ticket_pid;

                $tec_ticket_id = 0;
                if (isset($ticket_sync_rows[$ticket_key]) && is_array($ticket_sync_rows[$ticket_key])) {
                    $tec_ticket_id = absint($ticket_sync_rows[$ticket_key]['tec_ticket_id'] ?? 0);
                }
                if ($tec_ticket_id <= 0 && $used_legacy_ga_map && $legacy_ga_ticket_id > 0) {
                    $tec_ticket_id = $legacy_ga_ticket_id;
                }
                if ($tec_ticket_id <= 0) {
                    $tec_ticket_id = $ticket_pid;
                }

                $ticket_context = vms_ticketing_v2_resolve_ticket_max_context($ticket_pid);
                $context_ticket_key = sanitize_key((string) ($ticket_context['ticket_key'] ?? ''));
                if ($context_ticket_key === '') {
                    $context_ticket_key = $ticket_key;
                }
                $context_visibility_mode = sanitize_key((string) ($ticket_context['visibility_mode'] ?? $visibility_mode));
                if (!in_array($context_visibility_mode, array('public', 'login', 'verified'), true)) {
                    $context_visibility_mode = $visibility_mode;
                }
                $context_program = sanitize_key((string) ($ticket_context['program'] ?? $verified_program));
                $context_allowed_programs = function_exists('vms_ticketing_v2_normalize_allowed_programs')
                    ? vms_ticketing_v2_normalize_allowed_programs($ticket_context['allowed_programs'] ?? $allowed_programs, $context_program)
                    : ($context_program !== '' ? array($context_program) : array());
                $context_allow_direct_grants = function_exists('vms_ticketing_v2_truthy')
                    ? vms_ticketing_v2_truthy($ticket_context['allow_direct_grants'] ?? $allow_direct_grants, false)
                    : vms_ticketing_v2_meta_truthy($ticket_context['allow_direct_grants'] ?? $allow_direct_grants, false);
                $context_claim_grant_type = sanitize_key((string) ($ticket_context['claim_grant_type'] ?? $claim_grant_type));
                if (!in_array($context_claim_grant_type, $allowed_claim_grant_types, true)) {
                    $context_claim_grant_type = 'event_ticket_eligibility';
                }
                $context_claims_per_assignee = max(0, absint($ticket_context['claims_per_assignee'] ?? $claims_per_assignee));
                if ($context_claims_per_assignee <= 0) {
                    $context_claims_per_assignee = 1;
                }
                $context_require_assignee_email = function_exists('vms_ticketing_v2_truthy')
                    ? vms_ticketing_v2_truthy($ticket_context['require_assignee_email'] ?? $require_assignee_email, true)
                    : vms_ticketing_v2_meta_truthy($ticket_context['require_assignee_email'] ?? $require_assignee_email, true);
                if ($context_visibility_mode !== 'verified') {
                    $context_program = '';
                    $context_allowed_programs = array();
                    $context_allow_direct_grants = false;
                    $context_claim_grant_type = 'event_ticket_eligibility';
                    $context_claims_per_assignee = 1;
                    $context_require_assignee_email = true;
                } elseif ($context_program === '' && !empty($context_allowed_programs)) {
                    $context_program = (string) $context_allowed_programs[0];
                }
                $context_limit = max(0, absint($ticket_context['limit'] ?? 0));
                $group_ids = vms_ticketing_v2_ticket_group_product_ids_from_context($ticket_context, $ticket_pid);

                $current_user_eligibility = array(
                    'eligible' => 0,
                    'reason_code' => '',
                    'message' => '',
                    'matched_rule_path' => '',
                    'matched_grant_id' => 0,
                );
                $current_user_resolved = array();
                $current_user_claims_per_assignee = 0;
                $current_user_consumed_qty = 0;
                $current_user_assigned_cart_qty = 0;
                $current_user_claim_remaining_qty = -1;
                if ($current_user_id > 0 && $context_visibility_mode === 'verified') {
                    if (function_exists('vms_ticketing_claims_resolve_eligibility')) {
                        $resolved = vms_ticketing_claims_resolve_eligibility(array(
                            'user_id' => (int) $current_user_id,
                            'event_id' => (int) $tec_event_id,
                            'ticket_product_id' => $ticket_pid,
                            'ticket_key' => $context_ticket_key,
                            'legacy_program' => $context_program,
                            'allowed_programs' => $context_allowed_programs,
                            'allow_direct_grants' => $context_allow_direct_grants,
                            'grant_type' => $context_claim_grant_type,
                        ));
                        if (is_array($resolved)) {
                            $current_user_resolved = $resolved;
                            $current_user_eligibility['eligible'] = !empty($resolved['eligible']) ? 1 : 0;
                            $current_user_eligibility['reason_code'] = sanitize_key((string) ($resolved['reason_code'] ?? ''));
                            $current_user_eligibility['message'] = sanitize_text_field((string) ($resolved['message'] ?? ''));
                            $current_user_eligibility['matched_rule_path'] = sanitize_key((string) ($resolved['matched_rule_path'] ?? ''));
                            $current_user_eligibility['matched_grant_id'] = absint($resolved['matched_grant_id'] ?? 0);
                        }
                    } elseif ($context_program !== '' && function_exists('vms_ticketing_user_is_verified_for_program')) {
                        $fallback_eligible = vms_ticketing_user_is_verified_for_program((int) $current_user_id, $context_program);
                        $current_user_eligibility['eligible'] = $fallback_eligible ? 1 : 0;
                        $current_user_eligibility['reason_code'] = $fallback_eligible ? 'ok' : 'credential_not_approved';
                    }
                }

                if ($current_user instanceof WP_User && $current_user_email_key !== '' && !empty($current_user_eligibility['eligible'])) {
                    $current_user_claims_per_assignee = function_exists('vms_ticketing_v2_assignee_claims_per_event_limit')
                        ? max(1, absint(vms_ticketing_v2_assignee_claims_per_event_limit($ticket_context, $current_user, $current_user_resolved)))
                        : max(1, absint($context_claims_per_assignee));
                    $current_user_consumed_qty = function_exists('vms_ticketing_v2_assignee_consumed_qty_for_event')
                        ? absint(vms_ticketing_v2_assignee_consumed_qty_for_event((int) $tec_event_id, $current_user_email, $group_ids))
                        : 0;
                    $current_user_cart_counts = function_exists('vms_ticketing_v2_cart_assignee_usage_for_event')
                        ? (array) vms_ticketing_v2_cart_assignee_usage_for_event((int) $tec_event_id, $context_ticket_key)
                        : array();
                    $current_user_assigned_cart_qty = max(0, absint($current_user_cart_counts[$current_user_email_key] ?? 0));
                    $current_user_claim_remaining_qty = max(
                        0,
                        $current_user_claims_per_assignee - $current_user_consumed_qty - $current_user_assigned_cart_qty
                    );
                } elseif ($current_user_id > 0 && $context_visibility_mode === 'verified') {
                    $current_user_claim_remaining_qty = 0;
                }

                $used_prior_qty = 0;
                $used_cart_qty = 0;
                $remaining_qty = -1;
                if ($context_limit > 0) {
                    $used_cart_qty = absint(vms_ticketing_v2_cart_qty_for_event_ticket((int) $tec_event_id, $context_ticket_key));
                    if ($current_user_id > 0) {
                        $used_prior_qty = absint(vms_ticketing_v2_purchased_ticket_qty_for_user((int) $current_user_id, $group_ids));
                    }
                    $remaining_qty = max(0, $context_limit - $used_prior_qty - $used_cart_qty);
                }
                $is_disabled = ($context_limit > 0 && $remaining_qty <= 0);
                $allowed_qty = ($context_visibility_mode === 'verified') ? $context_limit : 0;
                $front_visibility_mode = $context_visibility_mode;
                $front_program = $context_program;
                $front_allowed_programs = $context_allowed_programs;
                $front_allow_direct_grants = $context_allow_direct_grants;
                $front_claim_grant_type = $context_claim_grant_type;
                $front_claims_per_assignee = $context_claims_per_assignee;
                $front_require_assignee_email = $context_require_assignee_email;
                $front_current_user_eligibility = $current_user_eligibility;
                $front_current_user_claims_per_assignee = $current_user_claims_per_assignee;
                $front_current_user_consumed_qty = $current_user_consumed_qty;
                $front_current_user_assigned_cart_qty = $current_user_assigned_cart_qty;
                $front_current_user_claim_remaining_qty = $current_user_claim_remaining_qty;
                if (vms_ticketing_v2_public_ticket_qualification_removed() && $front_visibility_mode === 'verified') {
                    $front_visibility_mode = 'public';
                    $front_program = '';
                    $front_allowed_programs = array();
                    $front_allow_direct_grants = false;
                    $front_claim_grant_type = 'event_ticket_eligibility';
                    $front_claims_per_assignee = 1;
                    $front_require_assignee_email = false;
                    $allowed_qty = 0;
                    $front_current_user_eligibility = array(
                        'eligible' => 0,
                        'reason_code' => '',
                        'message' => '',
                        'matched_rule_path' => '',
                        'matched_grant_id' => 0,
                    );
                    $front_current_user_claims_per_assignee = 0;
                    $front_current_user_consumed_qty = 0;
                    $front_current_user_assigned_cart_qty = 0;
                    $front_current_user_claim_remaining_qty = -1;
                }

                $is_rsvp_ticket = false;
                $display_label = vms_ticketing_v2_plain_display_text($ticket_cfg_row['title'] ?? '');

                $verified_helper_copy = '';
                if ($visibility_mode === 'verified') {
                    $verified_helper_copy = __('Requires registration', 'backstage-venue-manager');
                }

                $meta_flag = sanitize_key((string) get_post_meta($ticket_pid, '_vms_is_rsvp', true));
                if (in_array($meta_flag, array('yes', '1', 'true'), true)) {
                    $is_rsvp_ticket = true;
                } elseif ($is_primary_ticket) {
                    $p = function_exists('wc_get_product') ? wc_get_product($ticket_pid) : null;
                    $p_price = $p ? (float) $p->get_price() : (float) get_post_meta($ticket_pid, '_price', true);
                    if ($p_price <= 0) {
                        $is_rsvp_ticket = true;
                    }
                }

                if ($is_primary_ticket && $is_rsvp_ticket) {
                    $event_is_rsvp = true;
                    $display_label = 'RSVP';
                }

                $ratio_rule = function_exists('vms_ticketing_v2_normalize_ticket_ratio_rule')
                    ? vms_ticketing_v2_normalize_ticket_ratio_rule(is_array($ticket_cfg_row) ? $ticket_cfg_row : array())
                    : array('enabled' => false, 'max_per_qualifying' => 0, 'qualifier_mode' => 'counts_toward_unlock', 'group' => '');
                $ticket_runtime_cfg = is_array($ticket_cfg_row) ? $ticket_cfg_row : array();
                $ticket_runtime_cfg['_vms_runtime_product_id'] = $ticket_pid;
                $ticket_runtime_cfg['woo_product_id'] = $ticket_pid;
                $ticket_regular_price = max(0.0, (float) ($ticket_runtime_cfg['price'] ?? 0));
                $ticket_early_price = max(0.0, (float) ($ticket_runtime_cfg['early_price'] ?? 0));
                $ticket_sale_state = function_exists('vms_ticketing_v2_get_ticket_early_price_state')
                    ? vms_ticketing_v2_get_ticket_early_price_state($ticket_runtime_cfg)
                    : array('active' => false, 'early_price_cap' => 0, 'sold_qty' => -1, 'remaining_qty' => -1);
                $ticket_effective_price_for_sale = function_exists('vms_ticketing_v2_get_ticket_effective_price')
                    ? (float) vms_ticketing_v2_get_ticket_effective_price($ticket_runtime_cfg)
                    : $ticket_regular_price;
                $ticket_sale_active = !empty($ticket_sale_state['active']) && (
                    $ticket_regular_price > 0
                    && $ticket_early_price > 0
                    && $ticket_early_price < $ticket_regular_price
                    && abs($ticket_effective_price_for_sale - $ticket_early_price) < 0.00001
                );
                $ticket_sale_quantity_text = function_exists('vms_ticketing_v2_sale_quantity_text')
                    ? vms_ticketing_v2_sale_quantity_text($ticket_sale_state, is_array($ticket_ui_for_event ?? null) ? $ticket_ui_for_event : array())
                    : '';

                $ticket_description = vms_ticketing_v2_plain_display_text($ticket_cfg_row['description'] ?? '');
                if ($verified_helper_copy !== '') {
                    if ($ticket_description === '' || vms_ticketing_v2_is_legacy_verified_ticket_copy($ticket_description)) {
                        $ticket_description = $verified_helper_copy;
                    } elseif (stripos($ticket_description, $verified_helper_copy) === false) {
                        $ticket_description = trim($ticket_description . ' ' . $verified_helper_copy);
                    }
                }

                $access_row = array(
                    'ticket_key' => $context_ticket_key,
                    'label' => vms_ticketing_v2_plain_display_text($ticket_cfg_row['title'] ?? ''),
                    'display_label' => $display_label !== '' ? $display_label : vms_ticketing_v2_plain_display_text($ticket_cfg_row['title'] ?? ''),
                    'description' => $ticket_description,
                    'sort_order' => (int) ($ticket_cfg_row['sort_order'] ?? 0),
                    'is_primary' => $is_primary_ticket ? 1 : 0,
                    'is_rsvp' => $is_rsvp_ticket ? 1 : 0,
                    'visibility_mode' => $front_visibility_mode,
                    'verified_program' => $front_program,
                    'allowed_programs' => $front_allowed_programs,
                    'allow_direct_grants' => $front_allow_direct_grants ? 1 : 0,
                    'claim_grant_type' => $front_claim_grant_type,
                    'claims_per_assignee' => $front_claims_per_assignee,
                    'require_assignee_email' => $front_require_assignee_email ? 1 : 0,
                    'counts_toward_unlock' => !empty($ticket_cfg_row['counts_toward_unlock']) ? 1 : 0,
                    'ratio_rule_enabled' => !empty($ratio_rule['enabled']) ? 1 : 0,
                    'ratio_rule_max_per_qualifying' => max(0, absint($ratio_rule['max_per_qualifying'] ?? 0)),
                    'ratio_rule_qualifier_mode' => sanitize_key((string) ($ratio_rule['qualifier_mode'] ?? 'counts_toward_unlock')),
                    'ratio_rule_group' => sanitize_title((string) ($ratio_rule['group'] ?? '')),
                    'regular_price' => $ticket_regular_price,
                    'early_price' => $ticket_early_price,
                    'early_price_start' => sanitize_text_field((string) ($ticket_cfg_row['early_price_start'] ?? '')),
                    'early_price_end' => sanitize_text_field((string) ($ticket_cfg_row['early_price_end'] ?? '')),
                    'early_price_cap' => max(0, absint($ticket_sale_state['early_price_cap'] ?? 0)),
                    'early_price_sold_qty' => (int) ($ticket_sale_state['sold_qty'] ?? -1),
                    'early_price_remaining_qty' => (int) ($ticket_sale_state['remaining_qty'] ?? -1),
                    'sale_quantity_text' => sanitize_text_field((string) $ticket_sale_quantity_text),
                    'sale_availability_display' => sanitize_key((string) (($ticket_ui_for_event['sale_availability_display'] ?? 'when_capped'))),
                    'sale_availability_low_threshold' => max(1, absint($ticket_ui_for_event['sale_availability_low_threshold'] ?? 10)),
                    'sale_active' => $ticket_sale_active ? 1 : 0,
                    'max_qty_per_order' => $ticket_max_qty,
                    'allowed_qty' => max(0, absint($allowed_qty)),
                    'limit' => $context_limit,
                    'remaining_qty' => $remaining_qty,
                    'used_prior_qty' => max(0, absint($used_prior_qty)),
                    'used_cart_qty' => max(0, absint($used_cart_qty)),
                    'is_disabled' => $is_disabled ? 1 : 0,
                    'current_user_is_eligible' => max(0, absint($front_current_user_eligibility['eligible'] ?? 0)),
                    'current_user_reason_code' => sanitize_key((string) ($front_current_user_eligibility['reason_code'] ?? '')),
                    'current_user_message' => sanitize_text_field((string) ($front_current_user_eligibility['message'] ?? '')),
                    'current_user_rule_path' => sanitize_key((string) ($front_current_user_eligibility['matched_rule_path'] ?? '')),
                    'current_user_direct_grant_id' => absint($front_current_user_eligibility['matched_grant_id'] ?? 0),
                    'current_user_claims_per_assignee' => max(0, absint($front_current_user_claims_per_assignee)),
                    'current_user_consumed_qty' => max(0, absint($front_current_user_consumed_qty)),
                    'current_user_assigned_cart_qty' => max(0, absint($front_current_user_assigned_cart_qty)),
                    'current_user_claim_remaining_qty' => (int) $front_current_user_claim_remaining_qty,
                    'woo_product_id' => $ticket_pid,
                    'tec_ticket_id' => $tec_ticket_id,
                );

                $remaining_row = array(
                    'ticket_key' => $context_ticket_key,
                    'event_id' => (int) $tec_event_id,
                    'limit' => $context_limit,
                    'purchased' => max(0, absint($used_prior_qty)),
                    'in_cart' => max(0, absint($used_cart_qty)),
                    'remaining' => (int) $remaining_qty,
                    'is_disabled' => $is_disabled ? 1 : 0,
                    'woo_product_id' => $ticket_pid,
                    'tec_ticket_id' => $tec_ticket_id,
                );

                $ticket_price = 0.0;
                if (is_array($ticket_runtime_cfg) && isset($ticket_runtime_cfg['price']) && is_numeric($ticket_runtime_cfg['price'])) {
                    $ticket_price = function_exists('vms_ticketing_v2_get_ticket_effective_price')
                        ? vms_ticketing_v2_get_ticket_effective_price($ticket_runtime_cfg)
                        : (float) $ticket_runtime_cfg['price'];
                } else {
                    $ticket_product = function_exists('wc_get_product') ? wc_get_product($ticket_pid) : null;
                    if ($ticket_product && is_callable(array($ticket_product, 'get_price'))) {
                        $ticket_price = (float) $ticket_product->get_price();
                    }
                }

                $ticket_access_map[(string) $ticket_pid] = $access_row;
                $ticket_remaining_map[(string) $ticket_pid] = $remaining_row;
                $ticket_price_map[(string) $ticket_pid] = $ticket_price;
                if ($tec_ticket_id > 0) {
                    $ticket_access_map[(string) $tec_ticket_id] = $access_row;
                    $ticket_remaining_map[(string) $tec_ticket_id] = $remaining_row;
                    $ticket_price_map[(string) $tec_ticket_id] = $ticket_price;
                }
            }
        }
    }

    if (function_exists('vms_ticketing_verification_form_url')) {
        $verification_url = vms_ticketing_verification_form_url((int) $tec_event_id);
    }
    if (function_exists('vms_ticketing_get_current_user_verified_programs')) {
        $verified_programs = vms_ticketing_get_current_user_verified_programs();
    }
    if (function_exists('vms_ticketing_verification_programs')) {
        $verification_program_labels = vms_ticketing_verification_programs();
    }
    if (function_exists('vms_ticketing_verification_get_program_allowances')) {
        $verification_allowance_defaults = vms_ticketing_verification_get_program_allowances();
    }
    if (function_exists('vms_ticketing_claims_account_benefits_url')) {
        $my_benefits_url = (string) vms_ticketing_claims_account_benefits_url();
    } elseif (function_exists('vms_ticketing_verification_account_dashboard_url')) {
        $my_benefits_url = (string) add_query_arg('vms_benefits', '1', vms_ticketing_verification_account_dashboard_url()) . '#vms-benefits-panel';
    }
    if ($current_user_id > 0 && function_exists('vms_ticketing_claims_recent_assignee_emails_for_buyer')) {
        $recent_claim_emails = (array) vms_ticketing_claims_recent_assignee_emails_for_buyer($current_user_id, 8);
    }
    $redirect_after_login = ($tec_event_id > 0) ? get_permalink($tec_event_id) : home_url('/');

    $event_ticket_product_ids = array_values(array_unique(array_filter(array_map('absint', $event_ticket_product_ids))));
    if ($is_event && $tec_event_id > 0 && function_exists('vms_ticketing_v2_event_ticket_product_ids_for_event')) {
        $event_ticket_product_ids = array_values(array_unique(array_filter(array_merge(
            $event_ticket_product_ids,
            vms_ticketing_v2_event_ticket_product_ids_for_event((int) $tec_event_id)
        ))));
    }

    $my_active_ticket_count = -1;
    if ($is_event && $tec_event_id > 0 && $current_user_id > 0 && function_exists('vms_ticketing_v2_active_ticket_count_for_event_user')) {
        $my_active_ticket_count = vms_ticketing_v2_active_ticket_count_for_event_user((int) $tec_event_id, (int) $current_user_id);
    }

    $ticket_ui = vms_ticketing_v2_front_ui_settings((int) $plan_id_for_event);
    $ticket_ratio_qualifying_label = ($plan_id_for_event > 0 && function_exists('vms_ticketing_v2_resolve_qualifying_ticket_label'))
        ? (string) vms_ticketing_v2_resolve_qualifying_ticket_label((int) $plan_id_for_event)
        : __('qualifying tickets', 'backstage-venue-manager');

    wp_localize_script('vms-ticketing-front', 'vmsTicketingFront', array(
        'tecEventId' => (int) $tec_event_id,
        'eventPlanId' => (int) $plan_id_for_event,
        'isCart'     => $is_cart ? 1 : 0,
        'isCheckout' => $is_checkout ? 1 : 0,
        'isLoggedIn' => is_user_logged_in() ? 1 : 0,
        'uiLayout' => (string) ($ticket_ui['layout'] ?? 'classic'),
        'uiLayoutOverride' => (string) ($ticket_ui['layout_override'] ?? ''),
        'uiProgressive' => !empty($ticket_ui['is_progressive']) ? '1' : '0',
        'uiV2AdminPreview' => !empty($ticket_ui['admin_preview']) ? '1' : '0',
        'isAdminUser' => !empty($ticket_ui['is_admin_user']) ? '1' : '0',
        'uiSafeModeNotice' => !empty($ticket_ui['show_safe_mode_notice']) ? '1' : '0',
        'uiSafeModeNoticeText' => __('Ticket UI Safe Mode is active on this site. You are viewing the TEC fallback layout, not the unified V2 purchase UI.', 'backstage-venue-manager'),
        'uiSafeModeServerControlsNoticeText' => __('Ticket UI Safe Mode is active on this site. This event is using the server-controls safety layout instead of the full unified V2 ticket UI.', 'backstage-venue-manager'),
        'showTicketAvailability' => !empty($ticket_ui['show_availability']) ? 1 : 0,
        'ticketAvailabilityDisplay' => sanitize_key((string) ($ticket_ui['availability_display'] ?? 'low')),
        'ticketAvailabilityLowThreshold' => max(1, absint($ticket_ui['availability_low_threshold'] ?? 25)),
        'saleAvailabilityDisplay' => sanitize_key((string) ($ticket_ui['sale_availability_display'] ?? 'when_capped')),
        'saleAvailabilityLowThreshold' => max(1, absint($ticket_ui['sale_availability_low_threshold'] ?? 10)),
        'myActiveTicketCount' => (int) $my_active_ticket_count,
        'ticketHelpText' => (function_exists('bvmgr_ticketing_ui_help_should_render') && !bvmgr_ticketing_ui_help_should_render((int) $plan_id_for_event, 'tickets')) ? '' : (function_exists('bvmgr_ticketing_ui_help_effective_text') ? (string) bvmgr_ticketing_ui_help_effective_text((int) $plan_id_for_event, 'tickets') : ''),
        'ticketHelpStyle' => function_exists('bvmgr_ticketing_ui_help_global_style') ? (array) bvmgr_ticketing_ui_help_global_style('tickets') : array(),
        'addonHelpText' => (function_exists('bvmgr_ticketing_ui_help_should_render') && !bvmgr_ticketing_ui_help_should_render((int) $plan_id_for_event, 'addons')) ? '' : (function_exists('bvmgr_ticketing_ui_help_effective_text') ? (string) bvmgr_ticketing_ui_help_effective_text((int) $plan_id_for_event, 'addons') : ''),
        'addonHelpStyle' => function_exists('bvmgr_ticketing_ui_help_global_style') ? (array) bvmgr_ticketing_ui_help_global_style('addons') : array(),
        'addonSectionHeading' => function_exists('bvmgr_ticketing_ui_addons_section_heading_effective') ? (string) bvmgr_ticketing_ui_addons_section_heading_effective((int) $plan_id_for_event) : (function_exists('bvmgr_ticketing_ui_addons_section_heading') ? (string) bvmgr_ticketing_ui_addons_section_heading() : __('Fire Pits & Tables', 'backstage-venue-manager')),
        'addonSectionSubtext' => function_exists('bvmgr_ticketing_ui_addons_section_subtext_effective') ? (string) bvmgr_ticketing_ui_addons_section_subtext_effective((int) $plan_id_for_event) : (function_exists('bvmgr_ticketing_ui_addons_section_subtext') ? (string) bvmgr_ticketing_ui_addons_section_subtext() : __('Click here to add a fire pit or table to your order.', 'backstage-venue-manager')),
        'ticketRatioQualifyingLabel' => $ticket_ratio_qualifying_label,
        'loginUrl'   => wp_login_url($redirect_after_login),
        'registerUrl' => function_exists('wp_registration_url') ? wp_registration_url() : wp_login_url($redirect_after_login),
        'verificationUrl' => (string) $verification_url,
        'verifiedPrograms' => array_values(array_unique(array_filter(array_map('sanitize_key', (array) $verified_programs)))),
        'verificationPrograms' => is_array($verification_program_labels) ? $verification_program_labels : array(),
        'verificationProgramLabels' => is_array($verification_program_labels) ? $verification_program_labels : array(),
        'verificationAllowanceDefaults' => is_array($verification_allowance_defaults) ? $verification_allowance_defaults : array(),
        'myBenefitsUrl' => (string) $my_benefits_url,
        'recentClaimEmails' => array_values(array_unique(array_filter(array_map('sanitize_email', (array) $recent_claim_emails)))),
        'currentUserEmail' => $current_user_email,
        'buildStamp' => (string) $build_stamp,
        'hasCheckoutBlockers' => (($is_cart || $is_checkout) && !empty(vms_ticketing_v2_capture_checkout_blocker_error_messages())) ? 1 : 0,
        'checkoutBlockerMessages' => (($is_cart || $is_checkout) ? vms_ticketing_v2_capture_checkout_blocker_error_messages() : array()),
        'eventIsRsvp' => !empty($event_is_rsvp) ? 1 : 0,
        'ticketAccessMap' => $ticket_access_map,
        'ticketRemainingMap' => $ticket_remaining_map,
        'ticketPriceMap' => $ticket_price_map,
        'disabledTicketProductIds' => array_values(array_unique(array_filter(array_map('absint', $disabled_ticket_product_ids)))),
        'disabledTicketMap' => $disabled_ticket_map,
        'cartUrl' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
        'wcAjaxAddToCartUrl' => (class_exists('WC_AJAX') ? WC_AJAX::get_endpoint('add_to_cart') : home_url('/?wc-ajax=add_to_cart')),
        'cartContextUrl' => admin_url('admin-ajax.php?action=vms_ticketing_v2_cart_context'),
        'cartContextNonce' => wp_create_nonce('vms_ticketing_v2_cart_context'),
        'silentAddUrl' => admin_url('admin-ajax.php?action=vms_ticketing_v2_silent_add'),
        'silentAddNonce' => wp_create_nonce('vms_ticketing_v2_silent_add'),
        'atomicAddUrl' => admin_url('admin-ajax.php?action=vms_ticketing_v2_atomic_add_to_cart'),
        'atomicAddNonce' => wp_create_nonce('vms_ticketing_v2_atomic_add_to_cart'),
        'claimsClientLogUrl' => admin_url('admin-ajax.php?action=vms_ticketing_claims_log_client_action'),
        'claimsClientLogNonce' => wp_create_nonce('vms_ticketing_claims_log_client_action'),
        'claimsValidateUrl' => admin_url('admin-ajax.php?action=vms_ticketing_claims_validate_assignee'),
        'claimsValidateNonce' => wp_create_nonce('vms_ticketing_claims_validate_assignee'),
        'legacyReplayEnabled' => apply_filters('vms_ticketing_v2_legacy_replay_enabled', false) ? 1 : 0,
    ));

    if ($is_event && !empty($ticket_ui['is_progressive'])) {
        $progressive_script_path = trailingslashit(BVMGR_PLUGIN_PATH) . 'assets/vms-ticketing-progressive-ui.js';
        $progressive_script_version = function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '');
        if (is_readable($progressive_script_path)) {
            $progressive_script_version = (string) filemtime($progressive_script_path);
            wp_enqueue_script(
                'vms-ticketing-progressive-ui',
                plugins_url('assets/vms-ticketing-progressive-ui.js', BVMGR_PLUGIN_FILE),
                array('vms-ticketing-front'),
                $progressive_script_version,
                true
            );
        }
    }

    // Entitlements CSS only matters on event pages.
    if (!$is_event) return;

    if (!function_exists('vms_ticketing_v2_get_config') || !function_exists('vms_ticketing_v2_get_sync')) return;
    if (!function_exists('WC') || !function_exists('wc_get_product')) return;

    $tec_event_id = get_queried_object_id();
    if ($tec_event_id <= 0) return;

    $plan_id = $plan_id_for_event;
    if ($plan_id <= 0) {
        $plan_id = bvmgr_ticketing_v2_find_plan_id_by_tec_event_id((int) $tec_event_id);
    }
    if ($plan_id <= 0) return;

    $cfg = (!empty($cfg_for_event) && $plan_id === $plan_id_for_event) ? $cfg_for_event : vms_ticketing_v2_get_config($plan_id);
    $ents = (isset($cfg['entitlements']) && is_array($cfg['entitlements'])) ? $cfg['entitlements'] : array();

    $has_enabled = false;
    foreach ($ents as $ent) {
        if (!is_array($ent)) continue;
        if (!empty($ent['enabled'])) { $has_enabled = true; break; }
    }

    if (!$has_enabled) return;

    $entitlements_style_deps = array_values(array_unique(array_merge(array('vms-ticketing-front'), $front_style_deps)));
    wp_enqueue_style(
        'vms-ticketing-entitlements',
        plugins_url('assets/css/vms-entitlements-public.css', BVMGR_PLUGIN_FILE),
        $entitlements_style_deps,
        function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '')
    );
}


/**
 * True if the linked VMS Event Plan for a TEC event is cancelled.
 */
function bvmgr_tec_is_cancelled_event(int $tec_event_id): bool
{
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return false;
    }

    $plan_id = 0;
    if (function_exists('bvmgr_get_event_plan_for_tec_event')) {
        $plan_id = (int) bvmgr_get_event_plan_for_tec_event($tec_event_id);
    } elseif (function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id')) {
        $plan_id = (int) bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id);
    }

    if ($plan_id <= 0) {
        return false;
    }

    $k_status = function_exists('bvmgr_meta_key')
        ? (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status')
        : '_vms_event_plan_status';

    $status = sanitize_key((string) get_post_meta($plan_id, $k_status, true));
    return ($status === 'cancelled');
}

/**
 * Prepend a public cancellation notice on TEC single event pages when the linked VMS plan is cancelled.
 */

/**
 * Add a public body class when the current TEC event is linked to a cancelled VMS plan.
 */
function vms_tec_cancelled_event_body_class(array $classes): array
{
    if (is_admin() || !is_singular('tribe_events')) {
        return $classes;
    }
    $event_id = (int) get_queried_object_id();
    if ($event_id > 0 && bvmgr_tec_is_cancelled_event($event_id)) {
        $classes[] = 'vms-event-is-cancelled';
    }
    return array_values(array_unique(array_filter(array_map('sanitize_html_class', $classes))));
}

function vms_tec_prepend_cancelled_notice(string $content): string
{
    if (is_admin()) {
        return $content;
    }
    if (!is_singular('tribe_events')) {
        return $content;
    }
    if (function_exists('is_main_query') && !is_main_query()) {
        return $content;
    }
    if (function_exists('in_the_loop') && !in_the_loop()) {
        return $content;
    }

    $tec_event_id = (int) get_the_ID();
    if ($tec_event_id <= 0 || $tec_event_id !== (int) get_queried_object_id()) {
        return $content;
    }

    if (!bvmgr_tec_is_cancelled_event($tec_event_id)) {
        return $content;
    }

    // Pull cancellation reason from the linked plan (if present).
    $plan_id = function_exists('bvmgr_get_event_plan_for_tec_event')
        ? (int) bvmgr_get_event_plan_for_tec_event($tec_event_id)
        : (function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id') ? (int) bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id) : 0);

    $k_reason_code = function_exists('bvmgr_meta_key')
        ? (bvmgr_meta_key('event_plan', 'cancel_reason_code') ?: '_vms_cancel_reason_code')
        : '_vms_cancel_reason_code';
    $k_reason_note = function_exists('bvmgr_meta_key')
        ? (bvmgr_meta_key('event_plan', 'cancel_reason_note') ?: '_vms_cancel_reason_note')
        : '_vms_cancel_reason_note';

    $reason_code = $plan_id > 0 ? sanitize_key((string) get_post_meta($plan_id, $k_reason_code, true)) : '';
    $reason_note = $plan_id > 0 ? trim((string) get_post_meta($plan_id, $k_reason_note, true)) : '';

    $reason_label = '';
    if ($reason_code !== '' && function_exists('bvmgr_cancellation_reason_options')) {
        $opts = (array) bvmgr_cancellation_reason_options();
        if (isset($opts[$reason_code])) {
            $reason_label = (string) $opts[$reason_code];
        }
    }

    $rescheduled = ($plan_id > 0 && function_exists('bvmgr_event_plan_get_public_reschedule_destination'))
        ? (array) bvmgr_event_plan_get_public_reschedule_destination($plan_id)
        : array();
    $has_replacement_url = !empty($rescheduled['url']);
    $replacement_title = trim((string) ($rescheduled['title'] ?? ''));
    $replacement_date = trim((string) ($rescheduled['date_label'] ?? $rescheduled['date_raw'] ?? ''));

    $events_url = function_exists('bvmgr_get_public_event_calendar_url')
        ? (string) bvmgr_get_public_event_calendar_url()
        : (function_exists('tribe_get_events_link') ? (string) tribe_get_events_link() : home_url('/events/'));

    $html  = "\n" . '<div class="vms-event-status-banner vms-event-status-banner--cancelled' . ($has_replacement_url ? ' vms-event-status-banner--rescheduled' : '') . '" role="status" aria-live="polite">';
    if ($has_replacement_url) {
        $html .= '<div class="vms-event-status-banner__title">' . esc_html__('Event Rescheduled', 'backstage-venue-manager') . '</div>';
        $html .= '<div class="vms-event-status-banner__body">' . esc_html__('This event has been rescheduled. Please see updated details below.', 'backstage-venue-manager') . '</div>';
        if ($replacement_title !== '' || $replacement_date !== '') {
            $replacement_meta = $replacement_title;
            if ($replacement_title !== '' && $replacement_date !== '') {
                $replacement_meta .= ' — ' . $replacement_date;
            } elseif ($replacement_meta === '') {
                $replacement_meta = $replacement_date;
            }
            if ($replacement_meta !== '') {
                $html .= '<div class="vms-event-status-banner__meta"><strong>' . esc_html__('New date:', 'backstage-venue-manager') . '</strong> ' . esc_html($replacement_meta) . '</div>';
            }
        }
    } else {
        $html .= '<div class="vms-event-status-banner__title">' . esc_html__('Event Cancelled', 'backstage-venue-manager') . '</div>';
        $html .= '<div class="vms-event-status-banner__body">' . esc_html__('This event has been cancelled. Ticket sales and RSVPs are closed.', 'backstage-venue-manager') . '</div>';
    }

    // Keep internal cancellation reason/notes private. Public pages should show only
    // safe customer-facing cancellation/reschedule copy, not operator notes like
    // low sales, vendor issues, refund planning, or private logistics.

    $html .= '<div class="vms-event-status-banner__actions">';
    if ($has_replacement_url) {
        $html .= '<a class="vms-event-status-banner__link vms-event-status-banner__link--primary" href="' . esc_url((string) $rescheduled['url']) . '">' . esc_html__('View New Date', 'backstage-venue-manager') . '</a> ';
    }
    $html .= '<a class="vms-event-status-banner__link" href="' . esc_url($events_url) . '">' . esc_html__('Browse upcoming events', 'backstage-venue-manager') . '</a>';
    $html .= '</div>';
    $html .= '</div>' . "\n";

    // Avoid double-prepending if another filter runs twice.
    if (strpos($content, 'vms-event-status-banner--cancelled') !== false) {
        return $content;
    }

    return $html . $content;
}

/**
 * Wrap the featured image with a “Cancelled” overlay on TEC single events when applicable.
 */
function vms_tec_cancelled_thumbnail_overlay(string $html, int $post_id, int $post_thumbnail_id, $size, $attr): string
{
    if ($html === '') {
        return $html;
    }
    if (is_admin()) {
        return $html;
    }

    $post_id = absint($post_id);
    if ($post_id <= 0 || get_post_type($post_id) !== 'tribe_events') {
        return $html;
    }
    if (!bvmgr_tec_is_cancelled_event($post_id)) {
        return $html;
    }

    $plan_id = function_exists('bvmgr_get_event_plan_for_tec_event')
        ? (int) bvmgr_get_event_plan_for_tec_event($post_id)
        : (function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id') ? (int) bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($post_id) : 0);
    $rescheduled = ($plan_id > 0 && function_exists('bvmgr_event_plan_get_public_reschedule_destination'))
        ? (array) bvmgr_event_plan_get_public_reschedule_destination($plan_id)
        : array();
    $is_rescheduled = !empty($rescheduled['url']);

    // Prevent double-wrap.
    if (strpos($html, 'vms-cancelled-thumb') !== false) {
        return $html;
    }

    $label = $is_rescheduled ? __('Rescheduled', 'backstage-venue-manager') : __('Cancelled', 'backstage-venue-manager');
    $state_class = $is_rescheduled ? ' vms-cancelled-thumb--rescheduled' : ' vms-cancelled-thumb--cancelled';
    $label_class = $is_rescheduled ? ' vms-cancelled-thumb__label--rescheduled' : '';

    return '<div class="vms-cancelled-thumb' . esc_attr($state_class) . '"><div class="vms-cancelled-thumb__label' . esc_attr($label_class) . '">' . esc_html($label) . '</div>' . $html . '</div>';
}


/**
 * Suppress the printing of TEC ticket/RSVP forms on cancelled events.
 * Returning an empty hook prevents the form from rendering at all.
 */
function vms_tec_suppress_ticket_forms_for_cancelled_event(string $hook, $provider): string
{
    if (is_admin()) {
        return $hook;
    }
    if (!is_singular('tribe_events')) {
        return $hook;
    }
    $event_id = (int) get_queried_object_id();
    if ($event_id <= 0) {
        return $hook;
    }
    if (!bvmgr_tec_is_cancelled_event($event_id)) {
        return $hook;
    }
    return '';
}

/**
 * Suppress ticket/RSVP queries for cancelled events for public users.
 * This helps ensure TEC RSVP blocks are not available after cancellation.
 */
function vms_tec_suppress_tickets_for_cancelled_events(array $args): array
{
    if (is_admin()) {
        return $args;
    }

    // If we're on a cancelled TEC single-event page, suppress any ticket query regardless of provider/query-shape.
    if (is_singular('tribe_events')) {
        $current_event_id = (int) get_queried_object_id();
        if ($current_event_id > 0 && bvmgr_tec_is_cancelled_event($current_event_id)) {
            $args['post__in'] = array(0);
            return $args;
        }
    }

    // Otherwise, attempt to infer the event ID from common query args.
    $event_id = 0;

    // Some providers query tickets with post_parent set.
    if (isset($args['post_parent']) && is_numeric($args['post_parent'])) {
        $event_id = absint($args['post_parent']);
    }

    // Most providers query using a meta query pointing at the parent event.
    if ($event_id <= 0 && !empty($args['meta_query']) && is_array($args['meta_query'])) {
        foreach ($args['meta_query'] as $mq) {
            if (!is_array($mq)) {
                continue;
            }
            if (!isset($mq['value'])) {
                continue;
            }

            $val = $mq['value'];
            if (is_array($val)) {
                continue;
            }

            if (!is_numeric($val)) {
                continue;
            }

            $candidate = absint($val);
            if ($candidate > 0 && bvmgr_tec_is_cancelled_event($candidate)) {
                $event_id = $candidate;
                break;
            }
        }
    }

    if ($event_id <= 0) {
        return $args;
    }

    if (!bvmgr_tec_is_cancelled_event($event_id)) {
        return $args;
    }

    // Force the tickets query to return none.
    $args['post__in'] = array(0);

    return $args;
}

function vms_ticketing_v2_native_footer_mount_placed(int $tec_event_id, bool $mark_placed = false): bool
{
    static $placed_event_ids = array();

    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return false;
    }

    if ($mark_placed) {
        $placed_event_ids[$tec_event_id] = true;
    }

    return !empty($placed_event_ids[$tec_event_id]);
}

/**
 * @return array<int,string>
 */
function vms_ticketing_v2_ticket_query_event_meta_keys(): array
{
    $keys = array(
        '_tribe_rsvp_for_event',
        '_tribe_tpp_for_event',
        '_tec_tickets_commerce_event',
    );

    if (class_exists('\TEC\Tickets\Commerce\Ticket') && isset(\TEC\Tickets\Commerce\Ticket::$event_relation_meta_key)) {
        $keys[] = (string) \TEC\Tickets\Commerce\Ticket::$event_relation_meta_key;
    }

    return array_values(array_unique(array_filter(array_map('strval', $keys))));
}

function vms_ticketing_v2_event_id_from_ticket_query_args(array $args): int
{
    foreach (array('event', 'event_id', 'post_parent') as $key) {
        if (isset($args[$key]) && is_numeric($args[$key])) {
            $event_id = absint($args[$key]);
            if ($event_id > 0) {
                return $event_id;
            }
        }
    }

    if (!empty($args['meta_query']) && is_array($args['meta_query'])) {
        $meta_keys = vms_ticketing_v2_ticket_query_event_meta_keys();
        $queue = array($args['meta_query']);

        while (!empty($queue)) {
            $current = array_pop($queue);
            if (!is_array($current)) {
                continue;
            }

            foreach ($current as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }

                if (isset($candidate['key']) && in_array((string) $candidate['key'], $meta_keys, true)) {
                    $value = $candidate['value'] ?? null;
                    if (is_array($value)) {
                        if (count($value) !== 1) {
                            continue;
                        }

                        $value = reset($value);
                    }

                    if (!is_numeric($value)) {
                        continue;
                    }

                    $event_id = absint($value);
                    if ($event_id > 0) {
                        return $event_id;
                    }
                }

                $queue[] = $candidate;
            }
        }
    }

    if (is_singular('tribe_events')) {
        return (int) get_queried_object_id();
    }

    return 0;
}

function vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount(string $html, string $file, array $name, $template): string
{
    if ($html === '' || is_admin()) {
        return $html;
    }

    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
        return $html;
    }

    $is_rest_request = (defined('REST_REQUEST') && REST_REQUEST);
    $is_json_request = function_exists('wp_is_json_request') && wp_is_json_request();
    $is_feed_request = function_exists('is_feed') && is_feed();
    $is_trackback_request = function_exists('is_trackback') && is_trackback();
    $is_robots_request = function_exists('is_robots') && is_robots();
    if ($is_rest_request || $is_json_request || $is_feed_request || $is_trackback_request || $is_robots_request) {
        return $html;
    }

    $normalized_file = str_replace('\\', '/', $file);
    if ($normalized_file === '' || strpos($normalized_file, '/v2/tickets/footer.php') === false) {
        return $html;
    }

    $tec_event_id = 0;
    if (is_object($template) && method_exists($template, 'get')) {
        $tec_event_id = absint($template->get('post_id', 0));
    }
    if ($tec_event_id <= 0 && is_object($template) && method_exists($template, 'get_values')) {
        $template_values = (array) $template->get_values();
        $tec_event_id = absint($template_values['post_id'] ?? 0);
    }
    if ($tec_event_id <= 0 && is_singular('tribe_events')) {
        $tec_event_id = (int) get_queried_object_id();
    }
    if ($tec_event_id <= 0 || get_post_type($tec_event_id) !== 'tribe_events') {
        return $html;
    }

    if (bvmgr_tec_is_cancelled_event($tec_event_id)) {
        return $html;
    }

    if (vms_ticketing_v2_native_footer_mount_placed($tec_event_id)) {
        return $html;
    }

    $plan_id = function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id')
        ? absint(bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id))
        : 0;
    if ($plan_id <= 0) {
        return $html;
    }

    if (function_exists('get_post_field') && function_exists('has_shortcode')) {
        $event_content = (string) get_post_field('post_content', $tec_event_id);
        if ($event_content !== '' && has_shortcode($event_content, 'vms_reserved_add_ons')) {
            return $html;
        }
    }

    $mount_body = vms_ticketing_v2_render_entitlements_block($tec_event_id, $plan_id);
    if ($mount_body === '') {
        return $html;
    }

    $mount_html = "<div\n"
        . "    id=\"vms-addon-mount\"\n"
        . "    class=\"vms-addon-mount vms-addon-mount--server\"\n"
        . ">\n"
        . $mount_body
        . "\n</div>\n";

    vms_ticketing_v2_native_footer_mount_placed($tec_event_id, true);

    return $mount_html . $html;
}

function vms_ticketing_v2_filter_disabled_ticket_query_args(array $args): array
{
    if (is_admin()) {
        return $args;
    }

    $tec_event_id = vms_ticketing_v2_event_id_from_ticket_query_args($args);
    if ($tec_event_id <= 0 || get_post_type($tec_event_id) !== 'tribe_events') {
        return $args;
    }

    if (bvmgr_tec_is_cancelled_event($tec_event_id)) {
        return $args;
    }

    if (!function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id') || !function_exists('vms_ticketing_v2_disabled_ticket_products_for_plan')) {
        return $args;
    }

    $plan_id = absint(bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id));
    if ($plan_id <= 0) {
        return $args;
    }

    $disabled_runtime = vms_ticketing_v2_disabled_ticket_products_for_plan($plan_id);
    $disabled_ids = array();
    if (isset($disabled_runtime['product_ids']) && is_array($disabled_runtime['product_ids'])) {
        $disabled_ids = array_map('intval', $disabled_runtime['product_ids']);
        $disabled_ids = array_values(array_unique(array_filter($disabled_ids, static function ($product_id) {
            return $product_id > 0;
        })));
    }
    if (empty($disabled_ids)) {
        return $args;
    }

    $existing_exclusions = isset($args['post__not_in']) ? (array) $args['post__not_in'] : array();
    // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Disabled IDs are plan-scoped pending-sync ticket products for the resolved event, bounded by the saved plan config, and query-level exclusion preserves TEC ticket counts and pagination without broadening unrelated queries.
    $args['post__not_in'] = array_values(array_unique(array_filter(array_map('absint', array_merge($existing_exclusions, $disabled_ids)))));

    return $args;
}

function vms_ticketing_v2_append_entitlements_to_tec_event(string $content): string
{
    if (is_admin()) return $content;
    if (!is_singular('tribe_events')) return $content;

    // If the operator placed the shortcode manually, do not auto-append.
    if (function_exists('has_shortcode') && has_shortcode($content, 'vms_reserved_add_ons')) {
        return $content;
    }

    // Only append once to the main event content (avoid sidebar widgets/related events/etc).
    if (function_exists('is_main_query') && !is_main_query()) return $content;
    if (function_exists('in_the_loop') && !in_the_loop()) return $content;

    $tec_event_id = get_the_ID();
    if ($tec_event_id !== (int) get_queried_object_id()) return $content;
    if ($tec_event_id <= 0) return $content;

    if (vms_ticketing_v2_native_footer_mount_placed((int) $tec_event_id)) return $content;

    // Cancelled events should not show reserved add-ons.
    if (bvmgr_tec_is_cancelled_event((int) $tec_event_id)) return $content;
    $plan_id = bvmgr_ticketing_v2_find_plan_id_by_tec_event_id((int) $tec_event_id);
    if ($plan_id <= 0) return $content;

    $html = vms_ticketing_v2_render_entitlements_block((int) $tec_event_id, (int) $plan_id);
    if ($html === '') return $content;

    return $content . $html;
}

function vms_ticketing_v2_shortcode_reserved_add_ons($atts = array()): string
{
    if (is_admin()) return '';
    if (!function_exists('vms_ticketing_v2_get_config') || !function_exists('vms_ticketing_v2_get_sync')) return '';

    $a = shortcode_atts(array(
        'tec_event_id' => '0',
    ), (array) $atts, 'vms_reserved_add_ons');

    $tec_event_id = absint($a['tec_event_id'] ?? 0);
    if ($tec_event_id <= 0 && is_singular('tribe_events')) {
        $tec_event_id = (int) get_queried_object_id();
    }
    if ($tec_event_id <= 0) return '';

    if (bvmgr_tec_is_cancelled_event((int) $tec_event_id)) return '';

    $plan_id = bvmgr_ticketing_v2_find_plan_id_by_tec_event_id((int) $tec_event_id);
    if ($plan_id <= 0) return '';

    return vms_ticketing_v2_render_entitlements_block((int) $tec_event_id, (int) $plan_id);
}

function bvmgr_ticketing_v2_find_plan_id_by_tec_event_id(int $tec_event_id): int
{
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) return 0;

    if (function_exists('bvmgr_resolve_event_plan_for_tec_event')) {
        return (int) bvmgr_resolve_event_plan_for_tec_event($tec_event_id);
    }

    // Fallback for unusual load-order situations. Keep deterministic ordering even
    // when the shared resolver has not loaded yet.
    if (!class_exists('WP_Query')) return 0;

    $tec_key = (function_exists('bvmgr_ticketing_b_meta_key') ? bvmgr_ticketing_b_meta_key('tec_event_id', '_vms_tec_event_id') : '_vms_tec_event_id');

    $q = new WP_Query(array(
        'post_type'      => 'vms_event_plan',
        'posts_per_page' => 1,
        'post_status'    => array('publish','draft','pending','private'),
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The unusual-load-order fallback performs one exact, single-result TEC-event marker lookup with deterministic modified ordering.
        'meta_query'     => array(
            array(
                'key'     => $tec_key,
                'value'   => (string) $tec_event_id,
                'compare' => '=',
            ),
        ),
        'fields'        => 'ids',
        'orderby'       => 'modified',
        'order'         => 'DESC',
        'no_found_rows' => true,
    ));

    $plan_id = (!empty($q->posts) && isset($q->posts[0])) ? (int) $q->posts[0] : 0;

    wp_reset_postdata();

    return $plan_id;
}

function vms_ticketing_v2_event_is_past(int $tec_event_id, int $plan_id): bool
{
    $tz = function_exists('bvmgr_get_timezone') ? bvmgr_get_timezone() : wp_timezone();
    $now = time();

    if ($plan_id > 0) {
        $k_date = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'date') : '_vms_event_date';
        if ($k_date === '') {
            $k_date = '_vms_event_date';
        }

        $date_key = trim((string) get_post_meta($plan_id, $k_date, true));
        $end_hhmm = trim((string) get_post_meta($plan_id, '_vms_end_time', true));
        $start_hhmm = trim((string) get_post_meta($plan_id, '_vms_start_time', true));
        $time_value = ($end_hhmm !== '') ? $end_hhmm : $start_hhmm;

        if ($date_key !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_key)) {
            try {
                if ($time_value !== '') {
                    $dt = new DateTimeImmutable($date_key . ' ' . $time_value, $tz);
                } else {
                    $dt = new DateTimeImmutable($date_key . ' 23:59:59', $tz);
                }
                return ($dt->getTimestamp() < $now);
            } catch (Throwable $e) {
            }
        }
    }

    if ($tec_event_id > 0 && function_exists('tribe_get_end_date')) {
        $tec_end = trim((string) tribe_get_end_date($tec_event_id, false, 'Y-m-d H:i:s'));
        if ($tec_end !== '') {
            try {
                $dt = new DateTimeImmutable($tec_end, $tz);
                return ($dt->getTimestamp() < $now);
            } catch (Throwable $e) {
            }
        }
    }

    return false;
}

function vms_ticketing_v2_render_entitlements_block(int $tec_event_id, int $plan_id): string
{
    if (!function_exists('vms_ticketing_v2_get_config') || !function_exists('vms_ticketing_v2_get_sync')) return '';
    if (!function_exists('WC') || !function_exists('wc_get_product')) return '';


    // Cancelled events must not display add-ons or allow purchase flows.
    if ($tec_event_id > 0 && bvmgr_tec_is_cancelled_event($tec_event_id)) return '';

    // Respect per-event ticketing override (cancellations set this to off).
    if (function_exists('bvmgr_event_plan_is_ticketing_enabled') && $plan_id > 0 && !bvmgr_event_plan_is_ticketing_enabled($plan_id)) return '';
    $cfg  = vms_ticketing_v2_get_config($plan_id);
    $sync = vms_ticketing_v2_get_sync($plan_id);

    $ents = (isset($cfg['entitlements']) && is_array($cfg['entitlements'])) ? $cfg['entitlements'] : array();
    if (!$ents) return '';

    // Past events should render as archive pages, not active sales pages.
    if (vms_ticketing_v2_event_is_past($tec_event_id, $plan_id)) {
        return '';
    }

    // v0.2.24.645: Do not hide mapped add-ons just because the GA sale-window helper
    // returns false. Some live events can still have valid ticket/add-on UI while that
    // helper is out of sync with TEC/Woo timing. Visibility should be driven by active
    // event status plus mapped entitlement products; qualification and stock validation
    // still happen below and at add-to-cart/checkout.

    $map  = (isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
    $emap = (isset($map['entitlements']) && is_array($map['entitlements'])) ? $map['entitlements'] : array();

    $items = array();
    $debug = array();

    foreach ($ents as $ent) {
        if (!is_array($ent)) continue;
        if (empty($ent['enabled'])) continue;

        $ent_id = sanitize_key((string) ($ent['entitlement_id'] ?? ''));
        if ($ent_id === '') continue;

        $label = (string) ($ent['label'] ?? 'Reserved Item');
        $short_desc = isset($ent['short_desc']) ? sanitize_text_field((string) $ent['short_desc']) : '';
        $more_info_raw = isset($ent['more_info']) ? (string) $ent['more_info'] : '';
        $more_info = trim((string) wp_kses_post($more_info_raw));

        // Auto-generate a short description from more info when none provided.
        // If we truncate, the "More info" expander should reveal ONLY the clipped remainder (not repeat the snippet).
        if ($short_desc === '' && $more_info !== '') {
            $plain = trim((string) wp_strip_all_tags($more_info));
            if ($plain !== '') {
                $words = preg_split('/\s+/', $plain);
                $max_words = 18;

                if (is_array($words) && count($words) > $max_words) {
                    $short_words = array_slice($words, 0, $max_words);
                    $rest_words  = array_slice($words, $max_words);

                    $short_desc = implode(' ', $short_words) . '…';

                    $rest_plain = trim((string) implode(' ', $rest_words));
                    if ($rest_plain !== '') {
                        // Store remainder as plain text (safe + predictable), preserving line breaks.
                        $more_info = nl2br(esc_html($rest_plain));
                    } else {
                        $more_info = '';
                    }
                } else {
                    // No overflow: show it inline and omit the "More info" expander.
                    $short_desc = $plain;
                    $more_info = '';
                }
            }
        }

        // If "more_info" is empty after sanitization but raw exists, fall back to plain text.
        if ($more_info === '' && trim($more_info_raw) !== '') {
            $more_info = nl2br(esc_html(sanitize_textarea_field($more_info_raw)));
        }


        // If admins provided both fields (or content overlaps), avoid repeating the same text in the expander.
        // If the "more info" begins with the short description, only reveal the remainder.
        $short_plain = trim((string) wp_strip_all_tags((string) $short_desc));
        $short_plain = rtrim($short_plain, "\t\n\r\0\x0B.…");
        $more_plain  = trim((string) wp_strip_all_tags((string) $more_info));

        if ($short_plain !== '' && $more_plain !== '' && stripos($more_plain, $short_plain) === 0) {
            $rem = trim((string) substr($more_plain, strlen($short_plain)));
            $rem = ltrim($rem, "\t\n\r\0\x0B-–—:;,."); // trim common separators
            if ($rem !== '') {
                $more_info = nl2br(esc_html($rem));
            } else {
                $more_info = '';
            }
        }

        $m = (isset($emap[$ent_id]) && is_array($emap[$ent_id])) ? $emap[$ent_id] : array();
        $pid = absint($m['woo_product_id'] ?? 0);
        $selector_mode = isset($ent['selector_mode']) ? sanitize_key((string) $ent['selector_mode']) : 'stepper';
        if (!in_array($selector_mode, array('stepper', 'checkbox'), true)) {
            $selector_mode = 'stepper';
        }

        $resolved_elig = vms_ticketing_v2_resolve_eligibility_for_product($pid, $plan_id, $ent);
        $pool_key = sanitize_key((string) ($resolved_elig['pool_key'] ?? ''));
        $min_per = absint($resolved_elig['min_ga_per_unit'] ?? 0);
        $pool_max_total = absint($resolved_elig['pool_max_total'] ?? 0);
        $image_id = absint($ent['image_id'] ?? 0);
        $image_url = '';
        if ($image_id > 0) {
            $img = wp_get_attachment_image_src($image_id, 'thumbnail');
            if (empty($img) || empty($img[0])) {
                $img = wp_get_attachment_image_src($image_id, 'medium');
            }
            if (is_array($img) && !empty($img[0])) {
                $image_url = (string) $img[0];
            }
        }

        if ($pid > 0 && get_post_type($pid) === 'product') {
            $product = wc_get_product($pid);
            if ($product) {
                // Derive quantity limits from Woo product rules so we never hardcode caps per entitlement.
                // - If the product is sold individually, max is 1.
                // - If the product manages stock (and no backorders), max is the available stock.
                // - Otherwise, max is unlimited.
                $limited = false;
                $max_qty = 0;

                if (method_exists($product, 'get_max_purchase_quantity')) {
                    $mp = $product->get_max_purchase_quantity();
                    if (is_numeric($mp)) {
                        $mpi = (int) $mp;
                        if ($mpi >= 0) {
                            $limited = true;
                            $max_qty = $mpi;
                        }
                    }
                }

                // If limited, reduce by quantity already in cart so the UI reflects true remaining availability.
                if ($limited && $max_qty > 0 && function_exists('WC') && WC() && isset(WC()->cart) && WC()->cart && method_exists(WC()->cart, 'get_cart')) {
                    $cart_qty = 0;
                    foreach ((array) WC()->cart->get_cart() as $cart_item) {
                        if (!is_array($cart_item)) continue;
                        $cart_pid = absint($cart_item['product_id'] ?? 0);
                        $cart_vid = absint($cart_item['variation_id'] ?? 0);
                        if ($cart_pid === $pid || $cart_vid === $pid) {
                            $cart_qty += absint($cart_item['quantity'] ?? 0);
                        }
                    }
                    $max_qty = max(0, $max_qty - $cart_qty);
                }

                $can_add = (bool) $product->is_in_stock() && (!$limited || $max_qty > 0);

                $items[] = array(
                    'label'    => $label,
                    'short_desc' => $short_desc,
                    'more_info' => $more_info,
                    'ent_id'   => $ent_id,
                    'pool_key' => $pool_key,
                    'min_ga'   => $min_per,
                    'pool_max_total' => $pool_max_total,
                    'image_id' => $image_id,
                    'image_url' => $image_url,
                    'pid'      => $pid,
                    'price'    => (string) $product->get_price_html(),
                    'in_stock' => (bool) $product->is_in_stock(),
                    'limited'  => (bool) $limited,
                    'max_qty'  => (int) $max_qty,
                    'can_add'  => (bool) $can_add,
                    'url'      => (string) add_query_arg(array('add-to-cart' => $pid, 'quantity' => 1), get_permalink($tec_event_id)),
                    'selector_mode' => $selector_mode,
                );
                continue;
            }
        }

        $debug[] = array(
            'label' => $label,
            'pid'   => $pid,
            'note'  => ($pid > 0 ? 'Mapped product not found or invalid.' : 'Not synced yet.'),
        );
    }

    // Public output: only show purchasable mapped items.
    // Admin output: if nothing is mapped yet, show a compact diagnostic block.
    $is_admin_user = function_exists('current_user_can') ? current_user_can('manage_options') : false;

    if (!$items && (!$is_admin_user || !$debug)) {
        return '';
    }

    ob_start();
    ?>

    <?php
        $sync = function_exists('vms_ticketing_v2_get_sync') ? vms_ticketing_v2_get_sync($plan_id) : array();
        $sync_map = (is_array($sync) && isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
        $qualifying_ticket_pids = function_exists('vms_ticketing_v2_qualifying_ticket_product_ids_for_plan')
            ? vms_ticketing_v2_qualifying_ticket_product_ids_for_plan((int) $plan_id)
            : array();
        $ga_pid = absint($sync_map['ga']['woo_product_id'] ?? 0);

        $cart_ga_qty = 0;
        if (function_exists('vms_ticketing_v2_cart_scan')) {
            $cart_scan = vms_ticketing_v2_cart_scan();
            if (isset($cart_scan['ga_qty_by_plan']) && is_array($cart_scan['ga_qty_by_plan'])) {
                $cart_ga_qty = absint($cart_scan['ga_qty_by_plan'][$plan_id] ?? 0);
            }
        }
        $prior_history = function_exists('vms_ticketing_v2_prior_addon_history_for_plan')
            ? vms_ticketing_v2_prior_addon_history_for_plan((int) $plan_id, is_array($cfg) ? $cfg : array())
            : array('qualifying_qty' => 0, 'pool_qty_by_key' => array());
        $prior_qualifying_qty = max(0, absint($prior_history['qualifying_qty'] ?? 0));
        $prior_pool_qty_by_key = (isset($prior_history['pool_qty_by_key']) && is_array($prior_history['pool_qty_by_key']))
            ? $prior_history['pool_qty_by_key']
            : array();
        $cart_pool_qty_by_key = function_exists('vms_ticketing_v2_cart_pool_qty_by_key_for_plan')
            ? vms_ticketing_v2_cart_pool_qty_by_key_for_plan((int) $plan_id, is_array($cfg) ? $cfg : array())
            : array();
        $prior_pool_qty_json = wp_json_encode($prior_pool_qty_by_key);
        if (!is_string($prior_pool_qty_json) || $prior_pool_qty_json === '') {
            $prior_pool_qty_json = '{}';
        }
        $cart_pool_qty_json = wp_json_encode($cart_pool_qty_by_key);
        if (!is_string($cart_pool_qty_json) || $cart_pool_qty_json === '') {
            $cart_pool_qty_json = '{}';
        }
    ?>
    <?php $ticket_ui = function_exists('vms_ticketing_v2_front_ui_settings') ? vms_ticketing_v2_front_ui_settings((int) $plan_id) : array('effective_v2' => false); ?>
    <?php $addons_wrap_classes = array('vms-entitlements-block'); ?>
    <?php if (!empty($ticket_ui['effective_v2'])) { $addons_wrap_classes[] = 'vms-entitlements--compact'; } ?>
    <div id="vms-reserved-addons" class="<?php echo esc_attr(implode(' ', $addons_wrap_classes)); ?>" data-vms-tec-event-id="<?php echo esc_attr((string) $tec_event_id); ?>" data-vms-event-plan-id="<?php echo esc_attr((string) $plan_id); ?>" data-vms-ga-product-id="<?php echo esc_attr((string) $ga_pid); ?>" data-vms-qualifying-ticket-product-ids="<?php echo esc_attr(implode(',', $qualifying_ticket_pids)); ?>" data-vms-prior-qualifying-qty="<?php echo esc_attr((string) $prior_qualifying_qty); ?>" data-vms-prior-pool-qty="<?php echo esc_attr($prior_pool_qty_json); ?>" data-vms-cart-ga-qty="<?php echo esc_attr((string) $cart_ga_qty); ?>" data-vms-cart-pool-qty="<?php echo esc_attr($cart_pool_qty_json); ?>" data-vms-ui-layout="<?php echo esc_attr(!empty($ticket_ui['effective_v2']) ? 'v2' : 'classic'); ?>" data-vms-render-mode="server_controls">
        <h3><?php echo esc_html__('Reserve Your Spot (Optional)', 'backstage-venue-manager'); ?></h3>

        <?php if ($items): ?>
            <ul class="vms-entitlements-list">
                <?php foreach ($items as $it): ?>
                    <?php
                    $ent_row_classes = array('vms-entitlements-item', 'vms-ent-row');
                    $ent_row_classes[] = !empty($it['image_url']) ? 'vms-ent-row--has-image' : 'vms-ent-row--no-image';
                    ?>
                    <li class="<?php echo esc_attr(implode(' ', $ent_row_classes)); ?>"<?php
                        echo ' data-vms-entitlement-id="' . esc_attr((string) ($it['ent_id'] ?? '')) . '"';
                        echo ' data-vms-product-id="' . esc_attr((string) absint($it['pid'] ?? 0)) . '"';
                        if (!empty($it['pool_key'])) {
                            echo ' data-vms-pool-key="' . esc_attr((string) ($it['pool_key'])) . '"';
                            echo ' data-vms-pool-max="' . esc_attr((string) (int) ($it['pool_max_total'] ?? 0)) . '"';
                        }
                        echo ' data-vms-selector-mode="' . esc_attr((string) ($it['selector_mode'] ?? 'stepper')) . '"';
                    ?>>
                        <?php if (!empty($it['image_url'])): ?>
                            <div class="vms-ent-img">
                                <img src="<?php echo esc_url((string) $it['image_url']); ?>" alt="<?php echo esc_attr((string) $it['label']); ?>" loading="lazy" decoding="async" />
                            </div>
                        <?php endif; ?>
                        <div class="vms-entitlements-main vms-ent-main">
                            <strong class="vms-entitlements-label vms-ent-title"><?php echo esc_html($it['label']); ?></strong>
                            <?php if (!empty($it['short_desc']) || !empty($it['more_info'])): ?>
                                <div class="vms-ent-descline">
                                    <?php if (!empty($it['short_desc'])): ?>
                                        <span class="vms-entitlements-short vms-ent-short"><?php echo esc_html((string) $it['short_desc']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($it['more_info'])): ?>
                                        <details class="vms-entitlements-more vms-ent-more">
                                            <summary><?php echo esc_html__('More info', 'backstage-venue-manager'); ?></summary>
                                            <div class="vms-entitlements-more-body vms-ent-more-body"><?php echo wp_kses_post((string) $it['more_info']); ?></div>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="vms-ent-side">
                            <div class="vms-entitlements-price vms-ent-price"><?php echo wp_kses_post((string) $it['price']); ?></div>
                            <div class="vms-entitlements-qty vms-ent-qty">
                                <?php if (!$it['in_stock']): ?>
                                    <em class="vms-entitlements-soldout"><?php echo esc_html__('Sold out', 'backstage-venue-manager'); ?></em>
                                <?php else: ?>
                                    <?php
                                    $server_max_qty = 0;
                                    if (!empty($it['limited']) && !empty($it['max_qty'])) {
                                        $server_max_qty = (int) $it['max_qty'];
                                    } elseif (!empty($it['pool_max_total'])) {
                                        $server_max_qty = (int) $it['pool_max_total'];
                                    }
                                    $server_initial_note = empty($it['can_add']) ? __('Already in cart. Cart will be rechecked at add time.', 'backstage-venue-manager') : '';
                                    ?>
                                    <?php $selector_mode = (string) ($it['selector_mode'] ?? 'stepper'); ?>
                                    <div class="vms-rw-stepper vms-addon-controls <?php echo ($selector_mode === 'checkbox') ? 'vms-addon-controls--checkbox' : 'vms-addon-controls--stepper'; ?>"
                                        data-vms-server-stepper="1"
                                        data-vms-selector-mode="<?php echo esc_attr($selector_mode); ?>"
                                        data-vms-product-id="<?php echo esc_attr((string) absint($it['pid'] ?? 0)); ?>"
                                        data-vms-entitlement-id="<?php echo esc_attr((string) ($it['ent_id'] ?? '')); ?>"
                                        data-vms-can-add="<?php echo !empty($it['can_add']) ? '1' : '0'; ?>"
                                        <?php if ($server_initial_note !== ''): ?>data-vms-initial-note="<?php echo esc_attr($server_initial_note); ?>"<?php endif; ?>
                                        <?php if (!empty($it['pool_key'])): ?>data-vms-pool-key="<?php echo esc_attr((string) $it['pool_key']); ?>"<?php endif; ?>
                                        <?php if (!empty($it['pool_max_total'])): ?>data-vms-pool-max="<?php echo esc_attr((string) (int) $it['pool_max_total']); ?>"<?php endif; ?>
                                        <?php if (!empty($it['min_ga'])): ?>data-vms-pool-min-ga="<?php echo esc_attr((string) (int) $it['min_ga']); ?>"<?php endif; ?>
                                        <?php if ($server_max_qty > 0): ?>data-vms-max-qty="<?php echo esc_attr((string) $server_max_qty); ?>"<?php endif; ?>
                                    >
                                        <?php if ($selector_mode === 'checkbox') : ?>
                                            <label class="vms-addon-checkbox-wrap">
                                                <input
                                                    type="checkbox"
                                                    class="vms-addon-input vms-addon-input--checkbox"
                                                    value="1"
                                                    name="vms_addon_qty[<?php echo esc_attr((string) absint($it['pid'] ?? 0)); ?>]"
                                                    data-vms-product-id="<?php echo esc_attr((string) absint($it['pid'] ?? 0)); ?>"
                                                    <?php /* translators: %s: add-on ticket label. */ ?>
                                                    aria-label="<?php echo esc_attr(sprintf(__('Reserve %s', 'backstage-venue-manager'), (string) $it['label'])); ?>"
                                                />
                                                <span class="vms-addon-checkbox-label"><?php echo esc_html__('Reserve', 'backstage-venue-manager'); ?></span>
                                            </label>
                                            <button type="button" class="vms-rw-stepper__btn vms-rw-stepper__btn--minus vms-addon-minus vms-hidden" tabindex="-1" aria-hidden="true">−</button>
                                            <button type="button" class="vms-rw-stepper__btn vms-rw-stepper__btn--plus vms-addon-plus vms-hidden" tabindex="-1" aria-hidden="true">+</button>
                                        <?php else : ?>
                                            <?php /* translators: %s: add-on ticket label. */ ?>
                                            <button type="button" class="vms-rw-stepper__btn vms-rw-stepper__btn--minus vms-addon-minus" aria-label="<?php echo esc_attr(sprintf(__('Decrease %s quantity', 'backstage-venue-manager'), (string) $it['label'])); ?>">−</button>
                                            <input
                                                type="number"
                                                class="vms-rw-stepper__input vms-addon-input"
                                                inputmode="numeric"
                                                pattern="[0-9]*"
                                                min="0"
                                                <?php if ($server_max_qty > 0): ?>max="<?php echo esc_attr((string) $server_max_qty); ?>"<?php endif; ?>
                                                value="0"
                                                name="vms_addon_qty[<?php echo esc_attr((string) absint($it['pid'] ?? 0)); ?>]"
                                                data-vms-product-id="<?php echo esc_attr((string) absint($it['pid'] ?? 0)); ?>"
                                                <?php /* translators: %s: add-on ticket label. */ ?>
                                                aria-label="<?php echo esc_attr(sprintf(__('%s quantity', 'backstage-venue-manager'), (string) $it['label'])); ?>"
                                            />
                                            <?php /* translators: %s: add-on ticket label. */ ?>
                                            <button type="button" class="vms-rw-stepper__btn vms-rw-stepper__btn--plus vms-addon-plus" aria-label="<?php echo esc_attr(sprintf(__('Increase %s quantity', 'backstage-venue-manager'), (string) $it['label'])); ?>">+</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="vms-ent-note" aria-live="polite"></div>
                            <div class="vms-rw-addon__status" aria-live="polite"></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($is_admin_user && $debug): ?>
            <details class="vms-entitlements-debug">
                <summary><?php echo esc_html__('Admin diagnostics', 'backstage-venue-manager'); ?></summary>
                <ul class="vms-entitlements-debug-list">
                    <?php foreach ($debug as $d): ?>
                        <li>
                            <strong><?php echo esc_html($d['label']); ?></strong>
                            <?php if (!empty($d['pid'])): ?>
                                <?php echo esc_html(' (product #' . (int) $d['pid'] . ')'); ?>
                            <?php endif; ?>
                            <?php echo esc_html(' — ' . (string) $d['note']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="description">
                    <?php echo esc_html__('If these should be purchasable, run Ticketing Preview then Commit from the linked Event Plan to create/adopt products and store the map.', 'backstage-venue-manager'); ?>
                </p>
            </details>
        <?php endif; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}


/**
 * Clear WooCommerce success notices only (preserve validation/error notices).
 */
function vms_ticketing_v2_clear_success_notices(): void
{
    if (!function_exists('wc_get_notices') || !function_exists('wc_set_notices')) {
        return;
    }

    $notices = wc_get_notices();
    if (!is_array($notices) || empty($notices['success']) || !is_array($notices['success'])) {
        return;
    }

    unset($notices['success']);
    wc_set_notices($notices);
}

/**
 * Returns true when the current request is actively adding products to cart.
 */
function vms_ticketing_v2_request_is_add_to_cart(): bool
{
    if (vms_ticketing_v2_request_has_key('add-to-cart') || vms_ticketing_v2_request_has_key('added-to-cart')) {
        return true;
    }

    $wc_ajax = vms_ticketing_v2_request_key('wc-ajax');

    return ($wc_ajax === 'add_to_cart');
}

/**
 * Lightweight cart-empty probe from Woo session so we can prune stale success notices
 * before they leak onto unrelated pages.
 */
function vms_ticketing_v2_session_cart_is_empty(): bool
{
    if (!function_exists('WC')) {
        return true;
    }

    $wc = WC();
    if (!$wc || !isset($wc->session) || !$wc->session) {
        return true;
    }

    $raw_cart = $wc->session->get('cart', array());
    if (!is_array($raw_cart) || empty($raw_cart)) {
        return true;
    }

    foreach ($raw_cart as $line) {
        if (!is_array($line)) {
            continue;
        }

        if (absint($line['quantity'] ?? 0) > 0) {
            return false;
        }
    }

    return true;
}

/**
 * Guard against stale Woo "added to cart" success notices surfacing on unrelated pages
 * after the cart has already been emptied.
 */
function vms_ticketing_v2_prune_stale_success_notices(): void
{
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    if (!function_exists('WC') || !WC()) {
        return;
    }

    $is_cart = function_exists('is_cart') ? is_cart() : false;
    $is_checkout = function_exists('is_checkout') ? is_checkout() : false;
    if ($is_cart || $is_checkout) {
        return;
    }

    if (vms_ticketing_v2_request_is_add_to_cart()) {
        return;
    }

    if (!vms_ticketing_v2_session_cart_is_empty()) {
        return;
    }

    vms_ticketing_v2_clear_success_notices();
}

/**
 * Determine whether a product should be treated as a v2 entitlement.
 * This is used for notice suppression and silent add validation.
 */
function vms_ticketing_v2_product_is_entitlement(int $product_id): bool
{
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return false;
    }

    $role = (string) vms_ticketing_v2_meta_get($product_id, vms_ticketing_v2_product_meta_key('product_role'));
    if ($role === 'entitlement') {
        return true;
    }

    // Legacy fallback.
    $sr_type = (string) vms_ticketing_v2_meta_get($product_id, '_sr_addon_type');
    $sr_req  = (string) vms_ticketing_v2_meta_get($product_id, '_sr_required_qualifiers_per_unit');
    $sr_unit = (string) vms_ticketing_v2_meta_get($product_id, '_sr_addon_unit_label');
    return ($sr_type !== '' || $sr_req !== '' || $sr_unit !== '');
}


/**
 * Suppress WooCommerce “added to cart” notices for entitlement products.
 * This prevents delayed notices from appearing after cart empty or navigation.
 */
function vms_ticketing_v2_suppress_entitlement_added_notice($message, $products, $show_qty)
{
    if (empty($products) || !is_array($products)) {
        return $message;
    }

    foreach ($products as $pid => $qty) {
        $pid = absint($pid);
        if ($pid > 0 && vms_ticketing_v2_product_is_entitlement($pid)) {
            return '';
        }
    }

    return $message;
}


/**
 * Parse a site-timezone datetime string and return a Unix timestamp.
 * Expected format is the VMS canonical YYYY-MM-DD HH:MM:SS, but a few common variants are accepted.
 */
function vms_ticketing_v2_parse_wp_datetime_to_ts(string $dt): int
{
    $dt = trim($dt);
    if ($dt === '') {
        return 0;
    }

    if (!function_exists('wp_timezone')) {
        $ts = strtotime($dt);
        return $ts ? (int) $ts : 0;
    }

    $tz = wp_timezone();
    $formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d');

    foreach ($formats as $fmt) {
        $obj = DateTimeImmutable::createFromFormat($fmt, $dt, $tz);
        if ($obj instanceof DateTimeImmutable) {
            return (int) $obj->getTimestamp();
        }
    }

    try {
        $obj = new DateTimeImmutable($dt, $tz);
        return (int) $obj->getTimestamp();
    } catch (Throwable $e) {
        $ts = strtotime($dt);
        return $ts ? (int) $ts : 0;
    }
}


/**
 * Returns true when GA is currently within its sales window.
 * If no window is set, GA is considered on-sale.
 */
function vms_ticketing_v2_ga_is_on_sale_now(array $cfg): bool
{
    $ga = (isset($cfg['ga']) && is_array($cfg['ga'])) ? $cfg['ga'] : array();

    $start = trim((string) ($ga['sales_start'] ?? ''));
    $end   = trim((string) ($ga['sales_end'] ?? ''));

    $now = time();

    if ($start !== '') {
        $ts = vms_ticketing_v2_parse_wp_datetime_to_ts($start);
        if ($ts > 0 && $now < $ts) {
            return false;
        }
    }

    if ($end !== '') {
        $ts = vms_ticketing_v2_parse_wp_datetime_to_ts($end);
        if ($ts > 0 && $now > $ts) {
            return false;
        }
    }

    return true;
}

/**
 * Normalize Woo error notices into plain-text strings suitable for AJAX responses.
 *
 * @return string[]
 */
function vms_ticketing_v2_atomic_error_notices(): array
{
    if (!function_exists('wc_get_notices')) {
        return array();
    }

    $raw = wc_get_notices('error');
    if (!is_array($raw) || empty($raw)) {
        return array();
    }

    $out = array();
    foreach ($raw as $row) {
        $message = '';
        if (is_array($row) && isset($row['notice'])) {
            $message = (string) $row['notice'];
        } elseif (is_string($row)) {
            $message = $row;
        }
        $message = trim((string) wp_strip_all_tags($message));
        if ($message === '') {
            continue;
        }
        $out[$message] = true;
    }

    return array_keys($out);
}

/**
 * Capture add-on/cart-rule error notices without permanently altering the Woo notice stack.
 * Kept intentionally narrow so cart/checkout UI gating does not re-run the heavier claim validators.
 *
 * @return string[]
 */
function vms_ticketing_v2_capture_cart_rule_error_messages(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    if (!function_exists('wc_get_notices') || !function_exists('wc_set_notices') || !function_exists('wc_clear_notices')) {
        $cache = array();
        return $cache;
    }
    if (!function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        $cache = array();
        return $cache;
    }

    $before = wc_get_notices();
    wc_clear_notices();
    vms_ticketing_v2_enforce_cart_rules();
    $cache = vms_ticketing_v2_atomic_error_notices();
    wc_set_notices(is_array($before) ? $before : array());

    return $cache;
}

function vms_ticketing_v2_capture_checkout_blocker_error_messages(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    if (!function_exists('wc_get_notices') || !function_exists('wc_set_notices') || !function_exists('wc_clear_notices')) {
        $cache = array();
        return $cache;
    }
    if (!function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        $cache = array();
        return $cache;
    }

    $before = wc_get_notices();
    wc_clear_notices();

    vms_ticketing_v2_enforce_live_event_items_in_cart();
    vms_ticketing_v2_enforce_early_price_caps_in_cart();
    vms_ticketing_v2_enforce_ticket_max_qtys_in_cart();
    vms_ticketing_v2_enforce_verified_ticket_limits_in_cart();
    vms_ticketing_v2_enforce_ticket_ratio_rules_in_cart();
    vms_ticketing_v2_enforce_claim_assignments_in_cart();
    vms_ticketing_v2_enforce_ticket_visibility_rules();
    vms_ticketing_v2_enforce_cart_rules();

    $messages = vms_ticketing_v2_atomic_error_notices();
    $messages = array_values(array_unique(array_filter(array_map(static function ($message) {
        return sanitize_text_field((string) $message);
    }, (array) $messages))));

    wc_set_notices(is_array($before) ? $before : array());
    $cache = $messages;

    return $cache;
}

function vms_ticketing_v2_cart_has_checkout_blockers(): bool
{
    if (!function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
        return false;
    }

    if (function_exists('wc_notice_count') && wc_notice_count('error') > 0) {
        return true;
    }

    return !empty(vms_ticketing_v2_capture_checkout_blocker_error_messages());
}

function vms_ticketing_v2_store_api_request_path(): string
{
    $route = vms_ticketing_v2_query_text('rest_route');

    if ($route === '') {
        $request_path = wp_parse_url(bvmgr_request_current_uri(), PHP_URL_PATH);
        if (is_string($request_path)) {
            $route = $request_path;
        }
    }

    return strtolower(trim((string) $route));
}

function vms_ticketing_v2_store_api_request_is_checkout(): bool
{
    $route = vms_ticketing_v2_store_api_request_path();
    if ($route === '') {
        return false;
    }

    if (strpos($route, '/wc/store/') === false) {
        return false;
    }

    return strpos($route, '/checkout') !== false;
}

function vms_ticketing_v2_store_api_add_checkout_blocker_errors($errors, $cart = null): void
{
    if (!($errors instanceof WP_Error)) {
        return;
    }
    if (!vms_ticketing_v2_store_api_request_is_checkout()) {
        return;
    }

    foreach (vms_ticketing_v2_capture_checkout_blocker_error_messages() as $message) {
        $message = sanitize_text_field((string) $message);
        if ($message === '') {
            continue;
        }
        $errors->add('vms_checkout_blocker_' . md5($message), $message);
    }
}

function vms_ticketing_v2_store_api_checkout_update_order_meta($order): void
{
    $messages = vms_ticketing_v2_capture_checkout_blocker_error_messages();
    if (empty($messages)) {
        return;
    }

    throw new Exception(implode("\n", $messages)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Store API converts this plain-text validation summary into a JSON/REST error payload; escaping at construction would corrupt the consumer contract.
}

function vms_ticketing_v2_store_api_validate_add_to_cart($product, $request = array()): void
{
    if (!function_exists('wc_get_notices') || !function_exists('wc_set_notices') || !function_exists('wc_clear_notices')) {
        return;
    }

    $before = wc_get_notices();
    wc_clear_notices();

    $request = is_array($request) ? $request : array();
    $quantity = max(1, absint($request['quantity'] ?? 1));
    $product_id = absint($request['id'] ?? (is_object($product) && method_exists($product, 'get_id') ? $product->get_id() : 0));
    $variation_id = 0;
    if (is_object($product) && method_exists($product, 'is_type') && $product->is_type('variation')) {
        $variation_id = absint($product->get_id());
        if (method_exists($product, 'get_parent_id')) {
            $product_id = absint($product->get_parent_id());
        }
    }

    $variation = isset($request['variation']) && is_array($request['variation']) ? $request['variation'] : array();
    $cart_item_data = array();
    if (isset($request['cart_item_data']) && is_array($request['cart_item_data'])) {
        $cart_item_data = $request['cart_item_data'];
    }
    if (isset($request['extensions']) && is_array($request['extensions'])) {
        if (isset($request['extensions']['vms_claim_assignments']) && is_array($request['extensions']['vms_claim_assignments'])) {
            $cart_item_data['vms_claim_assignments'] = $request['extensions']['vms_claim_assignments'];
        } elseif (isset($request['extensions']['vms']) && is_array($request['extensions']['vms']) && isset($request['extensions']['vms']['claim_assignments']) && is_array($request['extensions']['vms']['claim_assignments'])) {
            $cart_item_data['vms_claim_assignments'] = $request['extensions']['vms']['claim_assignments'];
        }
    }

    $passed = vms_ticketing_v2_validate_add_to_cart(true, $product_id, $quantity, $variation_id, $variation, $cart_item_data);
    $messages = vms_ticketing_v2_atomic_error_notices();
    wc_set_notices(is_array($before) ? $before : array());

    if ($passed && empty($messages)) {
        return;
    }

    $message = '';
    foreach ((array) $messages as $candidate) {
        $candidate = sanitize_text_field((string) $candidate);
        if ($candidate !== '') {
            $message = $candidate;
            break;
        }
    }
    if ($message === '') {
        $message = __('This item could not be added to cart.', 'backstage-venue-manager');
    }

    throw new Exception($message); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Store API expects the first plain-text validation notice as the exception message for its JSON/REST error response; escaping belongs at the eventual output sink.
}

function vms_ticketing_v2_render_disabled_cart_checkout_button(): void
{
    echo '<button type="button" class="checkout-button button alt wc-forward" disabled="disabled" aria-disabled="true">' . esc_html__('Checkout unavailable — fix cart items above', 'backstage-venue-manager') . '</button>';
}

function vms_ticketing_v2_maybe_gate_cart_checkout_button(): void
{
    if (!function_exists('is_cart') || !is_cart()) {
        return;
    }
    if (!vms_ticketing_v2_cart_has_checkout_blockers()) {
        return;
    }

    remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
    add_action('woocommerce_proceed_to_checkout', 'vms_ticketing_v2_render_disabled_cart_checkout_button', 20);
}

function vms_ticketing_v2_filter_checkout_order_button_html(string $button_html): string
{
    if (!function_exists('is_checkout') || !is_checkout()) {
        return $button_html;
    }
    if (!vms_ticketing_v2_cart_has_checkout_blockers()) {
        return $button_html;
    }

    return '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr__('Checkout blocked — return to cart', 'backstage-venue-manager') . '" disabled="disabled" aria-disabled="true">' . esc_html__('Checkout blocked — return to cart', 'backstage-venue-manager') . '</button>';
}

function vms_ticketing_v2_filter_available_payment_gateways(array $gateways): array
{
    if (!function_exists('is_checkout') || !is_checkout()) {
        return $gateways;
    }
    if (!vms_ticketing_v2_cart_has_checkout_blockers()) {
        return $gateways;
    }

    return array();
}

/**
 * Normalize one requested ticket line to a stable structure.
 to a stable structure.
 *
 * @param mixed $line
 * @return array{
 *   product_id:int,
 *   qty:int,
 *   variation_id:int,
 *   variation:array<string,string>,
 *   claim_assignments:array<int,array{seat:int,assignee_email:string}>
 * }
 */
function vms_ticketing_v2_atomic_normalize_ticket_line($line): array
{
    if (!is_array($line)) {
        return array(
            'product_id' => 0,
            'qty' => 0,
            'variation_id' => 0,
            'variation' => array(),
            'claim_assignments' => array(),
        );
    }

    $product_id = absint($line['product_id'] ?? ($line['productId'] ?? ($line['ticket_id'] ?? ($line['ticketId'] ?? 0))));
    $qty = absint($line['qty'] ?? ($line['quantity'] ?? 0));
    $variation_id = absint($line['variation_id'] ?? ($line['variationId'] ?? 0));

    $variation_raw = array();
    if (isset($line['variation']) && is_array($line['variation'])) {
        $variation_raw = $line['variation'];
    } elseif (isset($line['attributes']) && is_array($line['attributes'])) {
        $variation_raw = $line['attributes'];
    }

    $variation = array();
    foreach ($variation_raw as $k => $v) {
        $key = sanitize_key((string) $k);
        if ($key === '') {
            continue;
        }
        if (strpos($key, 'attribute_') !== 0) {
            $key = 'attribute_' . $key;
        }
        $variation[$key] = sanitize_text_field(wp_unslash((string) $v));
    }

    $claim_assignments = vms_ticketing_v2_claim_assignments_normalize(
        $line['claim_assignments'] ?? ($line['claimAssignments'] ?? array())
    );

    return array(
        'product_id' => $product_id,
        'qty' => $qty,
        'variation_id' => $variation_id,
        'variation' => $variation,
        'claim_assignments' => $claim_assignments,
    );
}

/**
 * Normalize one requested add-on line to a stable structure.
 *
 * @param mixed $line
 * @return array{product_id:int,qty:int}
 */
function vms_ticketing_v2_atomic_normalize_addon_line($line): array
{
    if (!is_array($line)) {
        return array('product_id' => 0, 'qty' => 0);
    }

    $product_id = absint($line['product_id'] ?? ($line['productId'] ?? ($line['product'] ?? 0)));
    $qty = absint($line['qty'] ?? ($line['quantity'] ?? 0));

    return array(
        'product_id' => $product_id,
        'qty' => $qty,
    );
}

function vms_ticketing_v2_atomic_product_matches_event(int $product_id, int $tec_event_id): bool
{
    $product_id = absint($product_id);
    $tec_event_id = absint($tec_event_id);
    if ($product_id <= 0 || $tec_event_id <= 0) {
        return ($tec_event_id <= 0);
    }

    if (function_exists('vms_ticketing_v2_resolve_event_id_for_product')) {
        $resolved = absint(vms_ticketing_v2_resolve_event_id_for_product($product_id));
        if ($resolved > 0) {
            return ((int) $resolved === (int) $tec_event_id);
        }
    }

    $event_meta = absint(vms_ticketing_v2_meta_get($product_id, '_tribe_wooticket_for_event'));
    if ($event_meta > 0) {
        return ((int) $event_meta === (int) $tec_event_id);
    }

    return false;
}

function vms_ticketing_v2_atomic_product_matches_plan(int $product_id, int $plan_id): bool
{
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    if ($product_id <= 0 || $plan_id <= 0) {
        return ($plan_id <= 0);
    }

    $direct_plan = absint(vms_ticketing_v2_meta_get($product_id, vms_ticketing_v2_product_meta_key('event_plan_id')));
    if ($direct_plan > 0) {
        return ((int) $direct_plan === (int) $plan_id);
    }

    $event_id = function_exists('vms_ticketing_v2_resolve_event_id_for_product')
        ? absint(vms_ticketing_v2_resolve_event_id_for_product($product_id))
        : 0;
    if ($event_id > 0 && function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id')) {
        $mapped_plan = absint(bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($event_id));
        if ($mapped_plan > 0) {
            return ((int) $mapped_plan === (int) $plan_id);
        }
    }

    if (function_exists('vms_ticketing_v2_get_sync')) {
        $sync = vms_ticketing_v2_get_sync($plan_id);
        $map = (is_array($sync) && isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();

        $ticket_rows = (isset($map['tickets']) && is_array($map['tickets'])) ? $map['tickets'] : array();
        foreach ($ticket_rows as $ticket_row) {
            if (!is_array($ticket_row)) {
                continue;
            }
            $mapped_pid = absint($ticket_row['woo_product_id'] ?? 0);
            if ($mapped_pid > 0 && vms_ticketing_v2_pid_matches_mapped($product_id, $mapped_pid)) {
                return true;
            }
        }

        $mapped_ga = absint($map['ga']['woo_product_id'] ?? 0);
        if ($mapped_ga > 0 && vms_ticketing_v2_pid_matches_mapped($product_id, $mapped_ga)) {
            return true;
        }

        $ent_rows = (isset($map['entitlements']) && is_array($map['entitlements'])) ? $map['entitlements'] : array();
        foreach ($ent_rows as $ent_row) {
            if (!is_array($ent_row)) {
                continue;
            }
            $mapped_ent = absint($ent_row['woo_product_id'] ?? 0);
            if ($mapped_ent > 0 && vms_ticketing_v2_pid_matches_mapped($product_id, $mapped_ent)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Attempt to undo cart changes from this request when atomic add fails.
 *
 * @param string[] $cart_keys
 */
function vms_ticketing_v2_atomic_rollback_added_items(array $cart_keys): void
{
    if (!function_exists('WC')) {
        return;
    }
    $wc = WC();
    if (!$wc || !isset($wc->cart) || !$wc->cart) {
        return;
    }

    $seen = array();
    foreach ($cart_keys as $cart_key) {
        $cart_key = (string) $cart_key;
        if ($cart_key === '' || isset($seen[$cart_key])) {
            continue;
        }
        $seen[$cart_key] = true;
        $wc->cart->remove_cart_item($cart_key);
    }
    $wc->cart->calculate_totals();
}

/**
 * Atomic endpoint for event tickets + reserved add-ons in one request.
 * One click -> one server transaction -> one complete cart.
 */
function vms_ticketing_v2_ajax_atomic_add_to_cart(): void
{
    if (!function_exists('wp_send_json_error') || !function_exists('WC')) {
        status_header(400);
        exit;
    }

    $request_payload = vms_ticketing_v2_read_json_request_payload(65536);
    if (empty($request_payload['ok'])) {
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'invalid_payload'), 400);
    }

    $data = $request_payload['present'] ? $request_payload['payload'] : null;
    if (!is_array($data)) {
        $data = vms_ticketing_v2_read_form_request_payload($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Form fallback is shape-normalized only to extract and verify the existing atomic-add nonce before any cart mutation.
    }
    if (!is_array($data) || !vms_ticketing_v2_validate_atomic_add_payload($data)) {
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'invalid_payload'), 400);
    }

    $nonce = '';
    if (isset($data['nonce']) && !is_array($data['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash((string) $data['nonce']));
    } elseif (isset($_REQUEST['nonce']) && !is_array($_REQUEST['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash((string) $_REQUEST['nonce']));
    }
    if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_ticketing_v2_atomic_add_to_cart')) {
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'bad_nonce'), 403);
    }

    $tec_event_id = absint($data['tec_event_id'] ?? ($data['tecEventId'] ?? 0));
    $event_plan_id = absint($data['event_plan_id'] ?? ($data['eventPlanId'] ?? 0));
    $ticket_lines_raw = $data['ticket_lines'] ?? ($data['tickets'] ?? array());
    $addon_lines_raw = $data['addon_lines'] ?? ($data['addons'] ?? array());
    if (!is_array($ticket_lines_raw)) {
        $ticket_lines_raw = array();
    }
    if (!is_array($addon_lines_raw)) {
        $addon_lines_raw = array();
    }

    $ticket_lines = array();
    foreach ($ticket_lines_raw as $line) {
        $ticket_lines[] = vms_ticketing_v2_atomic_normalize_ticket_line($line);
    }
    $addon_lines = array();
    foreach ($addon_lines_raw as $line) {
        $addon_lines[] = vms_ticketing_v2_atomic_normalize_addon_line($line);
    }

    $has_ticket_lines = false;
    foreach ($ticket_lines as $line) {
        if (absint($line['product_id'] ?? 0) > 0 && absint($line['qty'] ?? 0) > 0) {
            $has_ticket_lines = true;
            break;
        }
    }
    $has_addon_lines = false;
    foreach ($addon_lines as $line) {
        if (absint($line['product_id'] ?? 0) > 0 && absint($line['qty'] ?? 0) > 0) {
            $has_addon_lines = true;
            break;
        }
    }
    if (!$has_ticket_lines && !$has_addon_lines) {
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'empty_selection'), 400);
    }

    if ($event_plan_id <= 0 && $tec_event_id > 0 && function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id')) {
        $event_plan_id = absint(bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id));
    }

    if (function_exists('wc_load_cart')) {
        wc_load_cart();
    }

    $wc = WC();
    if (!$wc || !isset($wc->cart) || !$wc->cart) {
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'cart_unavailable'), 400);
    }

    if (function_exists('wc_clear_notices')) {
        wc_clear_notices();
    }
    vms_ticketing_v2_clear_success_notices();

    if ($event_plan_id > 0 || $tec_event_id > 0) {
        $event_validation = vms_ticketing_v2_validate_product_sale_context(0, $event_plan_id, $tec_event_id, 'ga_ticket');
        if (empty($event_validation['ok'])) {
            vms_ticketing_v2_ajax_send_error(array(
                'ok' => false,
                'message' => sanitize_text_field((string) ($event_validation['code'] ?? 'event_unavailable')),
                'notice_message' => sanitize_text_field((string) ($event_validation['message'] ?? '')),
            ), (int) ($event_validation['http'] ?? 400));
        }
        $event_plan_id = absint($event_validation['plan_id'] ?? $event_plan_id);
        $tec_event_id = absint($event_validation['event_id'] ?? $tec_event_id);
    }

    $added_keys = array();
    $added_tickets = 0;
    $added_addons = 0;
    $errors = array();
    $request_assignee_counts = array();
    $buyer_user_id = is_user_logged_in() ? (int) get_current_user_id() : 0;

    $GLOBALS['bvmgr_ticketing_v2_atomic_add_in_progress'] = true;

    foreach ($ticket_lines as $idx => $line) {
        $pid = absint($line['product_id'] ?? 0);
        $qty = absint($line['qty'] ?? 0);
        $variation_id = absint($line['variation_id'] ?? 0);
        $variation = isset($line['variation']) && is_array($line['variation']) ? $line['variation'] : array();
        $cart_item_data = array();

        if ($pid <= 0 || $qty < 1) {
            $errors[] = array('line' => $idx, 'type' => 'ticket', 'code' => 'invalid_ticket_line');
            break;
        }
        if (vms_ticketing_v2_product_is_entitlement($pid)) {
            $errors[] = array('line' => $idx, 'type' => 'ticket', 'product_id' => $pid, 'code' => 'ticket_expected');
            break;
        }
        $sale_context = vms_ticketing_v2_validate_product_sale_context($pid, $event_plan_id, $tec_event_id, 'ga_ticket');
        if (empty($sale_context['ok'])) {
            $sale_message = sanitize_text_field((string) ($sale_context['message'] ?? ''));
            if ($sale_message !== '' && function_exists('wc_add_notice')) {
                wc_add_notice($sale_message, 'error');
            }
            $errors[] = array(
                'line' => $idx,
                'type' => 'ticket',
                'product_id' => $pid,
                'code' => sanitize_key((string) ($sale_context['code'] ?? 'event_unavailable')),
                'message' => $sale_message,
            );
            break;
        }
        $event_plan_id = absint($sale_context['plan_id'] ?? $event_plan_id);
        $tec_event_id = absint($sale_context['event_id'] ?? $tec_event_id);

        $disabled_ticket_state = function_exists('vms_ticketing_v2_disabled_ticket_config_for_product')
            ? vms_ticketing_v2_disabled_ticket_config_for_product($pid, $event_plan_id)
            : array('disabled' => false);
        if (!empty($disabled_ticket_state['disabled'])) {
            $disabled_message = vms_ticketing_v2_disabled_ticket_notice_text($disabled_ticket_state);
            if (function_exists('wc_add_notice')) {
                wc_add_notice($disabled_message, 'error');
            }
            $errors[] = array(
                'line' => $idx,
                'type' => 'ticket',
                'product_id' => $pid,
                'code' => 'ticket_disabled_pending_sync',
                'message' => $disabled_message,
            );
            break;
        }

        $claim_context = function_exists('vms_ticketing_v2_claim_context_for_product')
            ? vms_ticketing_v2_claim_context_for_product($pid)
            : array();
        $visibility_mode = sanitize_key((string) ($claim_context['visibility_mode'] ?? 'public'));
        $require_assignee_email = !empty($claim_context['require_assignee_email']);
        if ($visibility_mode === 'verified' && $require_assignee_email) {
            $claim_program = sanitize_key((string) ($claim_context['legacy_program'] ?? ''));
            $claim_allowed_programs = (array) ($claim_context['allowed_programs'] ?? array());
            $claim_program_label = vms_ticketing_v2_claim_program_label_text($claim_allowed_programs, $claim_program);
            $claim_allow_direct_grants = !empty($claim_context['allow_direct_grants']);
            $claim_grant_type = sanitize_key((string) ($claim_context['claim_grant_type'] ?? 'event_ticket_eligibility'));
            if (!is_user_logged_in()) {
                $guest_message = $claim_program_label !== ''
                    /* translators: %s: human-readable value used in this message. */
                    ? sprintf(__('This ticket requires %s verification. Log in and submit verification first.', 'backstage-venue-manager'), $claim_program_label)
                    : __('This ticket requires account verification. Log in and submit verification first.', 'backstage-venue-manager');
                if (empty($claim_allowed_programs) && $claim_allow_direct_grants) {
                    $guest_message = __('This ticket requires event-specific account approval. Log in to continue.', 'backstage-venue-manager');
                }
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($guest_message, 'error');
                }
                $errors[] = array(
                    'line' => $idx,
                    'type' => 'ticket',
                    'product_id' => $pid,
                    'code' => 'login_required',
                    'message' => $guest_message,
                );
                break;
            }

            $buyer_eligibility = vms_ticketing_v2_resolve_claim_eligibility_for_user(
                $buyer_user_id,
                absint($claim_context['event_id'] ?? 0),
                $pid,
                sanitize_key((string) ($claim_context['ticket_key'] ?? '')),
                $claim_program,
                $claim_allowed_programs,
                $claim_allow_direct_grants,
                $claim_grant_type
            );
            if (empty($buyer_eligibility['eligible'])) {
                $buyer_message = sanitize_text_field((string) ($buyer_eligibility['message'] ?? ''));
                if ($buyer_message === '') {
                    $buyer_message = sprintf(
                        /* translators: %s: human-readable value used in this message. */
                        __('Verification required for this ticket (%s). Submit your ID once for automatic recognition.', 'backstage-venue-manager'),
                        $claim_program_label
                    );
                }
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($buyer_message, 'error');
                }
                $errors[] = array(
                    'line' => $idx,
                    'type' => 'ticket',
                    'product_id' => $pid,
                    'code' => 'verification_required',
                    'reason_code' => sanitize_key((string) ($buyer_eligibility['reason_code'] ?? 'verification_required')),
                    'message' => $buyer_message,
                );
                break;
            }

            $incoming_assignments = vms_ticketing_v2_claim_assignments_normalize($line['claim_assignments'] ?? array());
            $claim_event_id = absint($claim_context['event_id'] ?? 0);
            $claim_ticket_key = sanitize_key((string) ($claim_context['ticket_key'] ?? ''));
            $counts_key = ($claim_event_id > 0 ? (string) $claim_event_id : '0') . '|' . $claim_ticket_key;
            $existing_counts = vms_ticketing_v2_cart_assignee_usage_for_event($claim_event_id, $claim_ticket_key);
            if (isset($request_assignee_counts[$counts_key]) && is_array($request_assignee_counts[$counts_key])) {
                foreach ($request_assignee_counts[$counts_key] as $email_key => $count) {
                    $email_key = strtolower(trim((string) $email_key));
                    if ($email_key === '') {
                        continue;
                    }
                    $existing_counts[$email_key] = absint($existing_counts[$email_key] ?? 0) + absint($count);
                }
            }

            $assignment_result = vms_ticketing_v2_validate_claim_assignments(
                $pid,
                $qty,
                $incoming_assignments,
                $buyer_user_id,
                array(
                    'source' => 'atomic_add_assignment',
                    'log_results' => true,
                    'existing_counts' => $existing_counts,
                )
            );
            if (empty($assignment_result['ok'])) {
                $assignment_message = sanitize_text_field((string) ($assignment_result['message'] ?? ''));
                if ($assignment_message === '') {
                    $assignment_message = __('Please add one approved guest email per selected ticket before adding tickets to your cart.', 'backstage-venue-manager');
                }
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($assignment_message, 'error');
                }
                $errors[] = array(
                    'line' => $idx,
                    'type' => 'ticket',
                    'product_id' => $pid,
                    'code' => 'claim_assignment_invalid',
                    'reason_code' => sanitize_key((string) ($assignment_result['reason_code'] ?? 'claim_assignment_invalid')),
                    'message' => $assignment_message,
                );
                break;
            }

            $validated_assignments = vms_ticketing_v2_claim_assignments_normalize($assignment_result['assignments'] ?? array());
            $cart_item_data['vms_claim_assignments'] = $validated_assignments;
            $cart_item_data['vms_claim_assignment_uid'] = function_exists('wp_generate_uuid4')
                ? wp_generate_uuid4()
                : uniqid('vms_claim_', true);

            if (!isset($request_assignee_counts[$counts_key]) || !is_array($request_assignee_counts[$counts_key])) {
                $request_assignee_counts[$counts_key] = array();
            }
            foreach ($validated_assignments as $assignment_row) {
                $email_key = strtolower(trim((string) ($assignment_row['assignee_email'] ?? '')));
                if ($email_key === '') {
                    continue;
                }
                $request_assignee_counts[$counts_key][$email_key] = absint($request_assignee_counts[$counts_key][$email_key] ?? 0) + 1;
            }
        }

        $cart_key = $wc->cart->add_to_cart($pid, $qty, $variation_id, $variation, $cart_item_data);
        if (!$cart_key) {
            $errors[] = array('line' => $idx, 'type' => 'ticket', 'product_id' => $pid, 'code' => 'add_failed');
            break;
        }
        $added_keys[] = (string) $cart_key;
        $added_tickets += $qty;
    }

    $GLOBALS['bvmgr_ticketing_v2_atomic_add_in_progress'] = false;

    if (empty($errors) && $event_plan_id > 0) {
        $ratio_violations = vms_ticketing_v2_collect_ticket_ratio_violations((int) $event_plan_id);
        foreach ($ratio_violations as $ratio_violation) {
            $ratio_message = sanitize_text_field((string) ($ratio_violation['message'] ?? ''));
            if ($ratio_message !== '') {
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($ratio_message, 'error');
                }
                $errors[] = array(
                    'type' => 'ticket',
                    'ticket_key' => sanitize_key((string) ($ratio_violation['ticket_key'] ?? '')),
                    'code' => 'ticket_ratio_limit_exceeded',
                    'message' => $ratio_message,
                );
                break;
            }
        }
    }

    if (empty($errors)) {
        foreach ($addon_lines as $idx => $line) {
            $pid = absint($line['product_id'] ?? 0);
            $qty = absint($line['qty'] ?? 0);

            if ($pid <= 0 || $qty < 1) {
                $errors[] = array('line' => $idx, 'type' => 'addon', 'code' => 'invalid_addon_line');
                break;
            }
            if (!vms_ticketing_v2_product_is_entitlement($pid)) {
                $errors[] = array('line' => $idx, 'type' => 'addon', 'product_id' => $pid, 'code' => 'not_entitlement');
                break;
            }
            $sale_context = vms_ticketing_v2_validate_product_sale_context($pid, $event_plan_id, $tec_event_id, 'entitlement');
            if (empty($sale_context['ok'])) {
                $sale_message = sanitize_text_field((string) ($sale_context['message'] ?? ''));
                if ($sale_message !== '' && function_exists('wc_add_notice')) {
                    wc_add_notice($sale_message, 'error');
                }
                $errors[] = array(
                    'line' => $idx,
                    'type' => 'addon',
                    'product_id' => $pid,
                    'code' => sanitize_key((string) ($sale_context['code'] ?? 'event_unavailable')),
                    'message' => $sale_message,
                );
                break;
            }

            $cart_key = $wc->cart->add_to_cart($pid, $qty);
            if (!$cart_key) {
                $errors[] = array('line' => $idx, 'type' => 'addon', 'product_id' => $pid, 'code' => 'add_failed');
                break;
            }
            $added_keys[] = (string) $cart_key;
            $added_addons += $qty;
        }
    }

    $notice_messages = vms_ticketing_v2_atomic_error_notices();
    if (!empty($errors) || !empty($notice_messages)) {
        vms_ticketing_v2_atomic_rollback_added_items($added_keys);
        vms_ticketing_v2_clear_success_notices();
        $message = !empty($notice_messages)
            ? (string) $notice_messages[0]
            : __('Could not add all selected items to cart. Please review your selection and try again.', 'backstage-venue-manager');
        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }
        vms_ticketing_v2_ajax_send_error(array(
            'ok' => false,
            'message' => $message,
            'errors' => $errors,
            'notice_messages' => $notice_messages,
        ), 400);
    }

    $wc->cart->calculate_totals();
    vms_ticketing_v2_clear_success_notices();
    if (function_exists('wc_clear_notices')) {
        wc_clear_notices();
    }

    vms_ticketing_v2_ajax_send_success(array(
        'ok' => true,
        'cart_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
        'added_tickets' => $added_tickets,
        'added_addons' => $added_addons,
        'added_total' => ($added_tickets + $added_addons),
    ));
}


/**
 * Silent add endpoint used by the front-end reserved add-ons helper.
 * Adds entitlement products to cart without generating Woo “added to cart” notices.
 */
function vms_ticketing_v2_ajax_silent_add(): void
{
    if (!function_exists('wp_send_json_error') || !function_exists('WC')) {
        status_header(400);
        exit;
    }

    $request_payload = vms_ticketing_v2_read_json_request_payload(65536);
    if (empty($request_payload['ok'])) {
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'invalid_payload'), 400);
    }

    $data = $request_payload['present'] ? $request_payload['payload'] : null;
    if (!is_array($data)) {
        $data = vms_ticketing_v2_read_form_request_payload($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Silent-add form fallback preserves the existing optional nonce contract and only normalizes payload shape before cart validation.
    }
    if (!is_array($data) || !vms_ticketing_v2_validate_silent_add_payload($data)) {
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'invalid_payload'), 400);
    }

    // Nonce may arrive via query string, form POST, or JSON payload.
    $nonce = '';
    if (isset($data['nonce']) && !is_array($data['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash((string) $data['nonce']));
    } elseif (isset($_REQUEST['nonce']) && !is_array($_REQUEST['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_REQUEST['nonce']));
    }

    // If a nonce is provided, validate it. (We don’t hard-require one to avoid caching edge cases.)
    if ($nonce !== '' && !wp_verify_nonce($nonce, 'vms_ticketing_v2_silent_add')) {
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'bad_nonce'), 403);
    }

    $tec_event_id = absint($data['tec_event_id'] ?? 0);
    $event_plan_id = absint($data['event_plan_id'] ?? 0);
    $ga_qty_hint = absint($data['ga_qty_hint'] ?? 0);
    $items = $data['items'] ?? array();

    if (!is_array($items) || empty($items)) {
        vms_ticketing_v2_ajax_send_success(array('ok' => true, 'added' => 0));
    }

    if (function_exists('wc_load_cart')) {
        wc_load_cart();
    }

    $wc = WC();
    if (!$wc || !isset($wc->cart) || !$wc->cart) {
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'cart_unavailable'), 400);
    }

    // Clear stale success notices but keep validation/error notices intact.
    vms_ticketing_v2_clear_success_notices();

    $hint_plan_id = $event_plan_id;
    if ($hint_plan_id <= 0 && $tec_event_id > 0 && function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id')) {
        $hint_plan_id = absint(bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id));
    }
    $seeded_hint_plan_id = 0;
    if ($hint_plan_id > 0 && $ga_qty_hint > 0) {
        vms_ticketing_v2_session_seed_ga_hint($hint_plan_id, $ga_qty_hint, 'silent_add_payload');
        $seeded_hint_plan_id = $hint_plan_id;
    }

    if ($hint_plan_id > 0 || $tec_event_id > 0) {
        $event_validation = vms_ticketing_v2_validate_product_sale_context(0, $hint_plan_id, $tec_event_id, 'entitlement');
        if (empty($event_validation['ok'])) {
            if ($seeded_hint_plan_id > 0) {
                vms_ticketing_v2_session_clear_ga_hint($seeded_hint_plan_id);
            }
            vms_ticketing_v2_clear_success_notices();
            vms_ticketing_v2_ajax_send_error(array(
                'ok' => false,
                'message' => sanitize_text_field((string) ($event_validation['code'] ?? 'event_unavailable')),
                'notice_message' => sanitize_text_field((string) ($event_validation['message'] ?? '')),
            ), (int) ($event_validation['http'] ?? 400));
        }
        $hint_plan_id = absint($event_validation['plan_id'] ?? $hint_plan_id);
        $tec_event_id = absint($event_validation['event_id'] ?? $tec_event_id);
    }

    // Respect per-event ticketing override (when turned off, no add-ons can be added).
    if ($hint_plan_id > 0 && function_exists('bvmgr_event_plan_is_ticketing_enabled') && !bvmgr_event_plan_is_ticketing_enabled($hint_plan_id)) {
        if ($seeded_hint_plan_id > 0) {
            vms_ticketing_v2_session_clear_ga_hint($seeded_hint_plan_id);
        }
        vms_ticketing_v2_clear_success_notices();
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'ticketing_disabled'), 403);
    }

    $added = 0;
    $errors = array();

    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        // Accept both camelCase and snake_case (front helper has evolved across patches).
        $pid = absint($it['productId'] ?? ($it['product_id'] ?? ($it['product'] ?? 0)));
        $qty = absint($it['qty'] ?? 0);

        if ($pid <= 0 || $qty < 1) {
            $errors[] = array('productId' => $pid, 'code' => 'invalid_item');
            continue;
        }

        if (!vms_ticketing_v2_product_is_entitlement($pid)) {
            $errors[] = array('productId' => $pid, 'code' => 'not_entitlement');
            continue;
        }

        $sale_context = vms_ticketing_v2_validate_product_sale_context($pid, $hint_plan_id, $tec_event_id, 'entitlement');
        if (empty($sale_context['ok'])) {
            $errors[] = array(
                'productId' => $pid,
                'code' => sanitize_key((string) ($sale_context['code'] ?? 'event_unavailable')),
                'message' => sanitize_text_field((string) ($sale_context['message'] ?? '')),
            );
            continue;
        }

        $cart_key = $wc->cart->add_to_cart($pid, $qty);
        if (!$cart_key) {
            $errors[] = array('productId' => $pid, 'code' => 'add_failed');
            continue;
        }

        $added += $qty;
    }

    if (!empty($errors)) {
        // Preserve validation/error notices, but drop success notices from partial adds.
        if ($seeded_hint_plan_id > 0) {
            vms_ticketing_v2_session_clear_ga_hint($seeded_hint_plan_id);
        }
        vms_ticketing_v2_clear_success_notices();
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'add_failed', 'errors' => $errors), 400);
    }

    $wc->cart->calculate_totals();

    // Ensure no success notices were queued.
    vms_ticketing_v2_clear_success_notices();
    if ($seeded_hint_plan_id > 0) {
        vms_ticketing_v2_session_clear_ga_hint($seeded_hint_plan_id);
    }

    vms_ticketing_v2_ajax_send_success(array('ok' => true, 'added' => $added));
}

/**
 * Session-accurate cart context endpoint for front-end unlock state refresh.
 * Useful when cached event HTML carries stale data-vms-cart-ga-qty markers.
 */
function vms_ticketing_v2_ajax_cart_context(): void
{
    if (!function_exists('wp_send_json_error') || !function_exists('WC')) {
        status_header(400);
        exit;
    }

    $plan_id = absint($_REQUEST['event_plan_id'] ?? 0);
    $tec_event_id = absint($_REQUEST['tec_event_id'] ?? 0);

    $nonce = '';
    if (isset($_REQUEST['nonce']) && !is_array($_REQUEST['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash((string) $_REQUEST['nonce']));
    }
    if ($nonce !== '' && !wp_verify_nonce($nonce, 'vms_ticketing_v2_cart_context')) {
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'bad_nonce'), 403);
    }

    if ($plan_id <= 0 && $tec_event_id > 0 && function_exists('bvmgr_ticketing_v2_find_plan_id_by_tec_event_id')) {
        $plan_id = absint(bvmgr_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id));
    }

    if (function_exists('wc_load_cart')) {
        wc_load_cart();
    }

    $wc = WC();
    if (!$wc || !isset($wc->cart) || !$wc->cart) {
        vms_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'cart_unavailable'), 400);
    }

    $scan = vms_ticketing_v2_cart_scan();
    $ga_qty_raw = 0;
    if ($plan_id > 0 && isset($scan['ga_qty_by_plan']) && is_array($scan['ga_qty_by_plan'])) {
        $ga_qty_raw = absint($scan['ga_qty_by_plan'][$plan_id] ?? 0);
    }
    $ga_qty = ($plan_id > 0) ? vms_ticketing_v2_effective_ga_qty_for_plan($plan_id, $ga_qty_raw) : $ga_qty_raw;
    $cfg = array();
    if ($plan_id > 0 && function_exists('vms_ticketing_v2_get_config')) {
        $cfg = vms_ticketing_v2_get_config($plan_id);
    }
    $prior_history = ($plan_id > 0 && function_exists('vms_ticketing_v2_prior_addon_history_for_plan'))
        ? vms_ticketing_v2_prior_addon_history_for_plan($plan_id, is_array($cfg) ? $cfg : array())
        : array('qualifying_qty' => 0, 'pool_qty_by_key' => array());
    $prior_qualifying_qty = max(0, absint($prior_history['qualifying_qty'] ?? 0));
    $prior_pool_qty_by_key = (isset($prior_history['pool_qty_by_key']) && is_array($prior_history['pool_qty_by_key']))
        ? $prior_history['pool_qty_by_key']
        : array();
    $pool_qty_by_key = ($plan_id > 0 && function_exists('vms_ticketing_v2_cart_pool_qty_by_key_for_plan'))
        ? vms_ticketing_v2_cart_pool_qty_by_key_for_plan($plan_id, is_array($cfg) ? $cfg : array())
        : array();

    $has_vms_cart_items = !empty($scan['ticket_lines']) || !empty($scan['ent_lines']);
    $checkout_blocker_messages = $has_vms_cart_items
        ? vms_ticketing_v2_capture_checkout_blocker_error_messages()
        : array();

    vms_ticketing_v2_ajax_send_success(array(
        'ok' => true,
        'event_plan_id' => $plan_id,
        'tec_event_id' => $tec_event_id,
        'ga_qty_raw' => $ga_qty_raw,
        'ga_qty' => $ga_qty,
        'prior_qualifying_qty' => $prior_qualifying_qty,
        'prior_pool_qty_by_key' => $prior_pool_qty_by_key,
        'pool_qty_by_key' => $pool_qty_by_key,
        'has_checkout_blockers' => !empty($checkout_blocker_messages),
        'checkout_blocker_messages' => $checkout_blocker_messages,
    ));
}
