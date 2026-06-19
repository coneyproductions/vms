<?php
defined('ABSPATH') || exit;

function vms_ticket_integrity_parse_wp_datetime(string $value): int
{
	$value = trim($value);
	if ($value === '') {
		return 0;
	}

	if (function_exists('vms_ticketing_v2_parse_wp_datetime_to_ts')) {
		return absint(vms_ticketing_v2_parse_wp_datetime_to_ts($value));
	}

	if (function_exists('wp_timezone')) {
		$tz = wp_timezone();
		$formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d');
		foreach ($formats as $format) {
			$dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
			if ($dt instanceof DateTimeImmutable) {
				return (int) $dt->getTimestamp();
			}
		}
	}

	$ts = strtotime($value);
	return $ts ? (int) $ts : 0;
}

function vms_ticket_integrity_normalize_program_list($raw, string $legacy_program = ''): array
{
	if (function_exists('vms_ticketing_v2_normalize_allowed_programs')) {
		return array_values(array_unique(array_filter(array_map('sanitize_key', (array) vms_ticketing_v2_normalize_allowed_programs($raw, $legacy_program)))));
	}

	$programs = array();
	foreach ((array) $raw as $program) {
		$key = sanitize_key((string) $program);
		if ($key !== '') {
			$programs[$key] = $key;
		}
	}

	$legacy_program = sanitize_key($legacy_program);
	if ($legacy_program !== '') {
		$programs[$legacy_program] = $legacy_program;
	}

	return array_values($programs);
}

function vms_ticket_integrity_event_timestamp(int $plan_id, int $tec_event_id): int
{
	$plan_id = absint($plan_id);
	$tec_event_id = absint($tec_event_id);

	if ($tec_event_id > 0 && function_exists('tribe_get_start_date')) {
		$raw = (string) tribe_get_start_date($tec_event_id, true, 'Y-m-d H:i:s');
		$ts = vms_ticket_integrity_parse_wp_datetime($raw);
		if ($ts > 0) {
			return $ts;
		}
	}

	if ($tec_event_id > 0) {
		$raw = (string) get_post_meta($tec_event_id, '_EventStartDate', true);
		$ts = vms_ticket_integrity_parse_wp_datetime($raw);
		if ($ts > 0) {
			return $ts;
		}
	}

	if ($plan_id > 0) {
		$date = trim((string) get_post_meta($plan_id, '_vms_event_date', true));
		$time = trim((string) get_post_meta($plan_id, '_vms_start_time', true));
		$raw = $date;
		if ($raw !== '' && $time !== '') {
			$raw .= ' ' . $time;
		}
		$ts = vms_ticket_integrity_parse_wp_datetime($raw);
		if ($ts > 0) {
			return $ts;
		}
	}

	return 0;
}

function vms_ticket_integrity_issue(string $key, string $severity, string $category, string $title, string $details, array $extra = array()): array
{
	$severity = sanitize_key($severity);
	if (!in_array($severity, array('informational', 'yellow', 'red'), true)) {
		$severity = 'yellow';
	}

	$issue = array(
		'key' => sanitize_key($key),
		'severity' => $severity,
		'category' => sanitize_key($category),
		'title' => sanitize_text_field($title),
		'details' => trim(wp_strip_all_tags($details)),
		'status' => 'open',
	);

	foreach ($extra as $extra_key => $extra_value) {
		$extra_key = sanitize_key((string) $extra_key);
		if ($extra_key === '') {
			continue;
		}
		$issue[$extra_key] = $extra_value;
	}

	return $issue;
}

function vms_ticket_integrity_add_issue(array &$issues, array $issue): void
{
	$key = sanitize_key((string) ($issue['key'] ?? ''));
	if ($key === '') {
		return;
	}

	if (!isset($issues[$key])) {
		$issues[$key] = $issue;
		return;
	}

	$existing_rank = vms_ticket_integrity_status_rank((string) ($issues[$key]['severity'] ?? ''));
	$new_rank = vms_ticket_integrity_status_rank((string) ($issue['severity'] ?? ''));
	if ($new_rank > $existing_rank) {
		$issues[$key] = $issue;
	}
}

function vms_ticket_integrity_product_catalog_visibility(int $product_id): string
{
	$product_id = absint($product_id);
	if ($product_id <= 0) {
		return '';
	}

	if (function_exists('vms_ticketing_v2_get_product_catalog_visibility_state')) {
		return (string) vms_ticketing_v2_get_product_catalog_visibility_state($product_id);
	}

	if (function_exists('wc_get_product')) {
		$product = wc_get_product($product_id);
		if ($product && method_exists($product, 'get_catalog_visibility')) {
			return (string) $product->get_catalog_visibility();
		}
	}

	return '';
}

function vms_ticket_integrity_normalize_title_token(string $value): string
{
	$value = trim((string) $value);
	if ($value === '') {
		return '';
	}

	if (function_exists('vms_ticketing_v2_normalize_admin_ticket_title_for_match')) {
		$value = (string) vms_ticketing_v2_normalize_admin_ticket_title_for_match($value);
	}

	$value = strtolower(trim(wp_strip_all_tags($value)));
	$value = preg_replace('/[^a-z0-9]+/', ' ', $value);
	if (!is_string($value)) {
		return '';
	}
	return trim($value);
}

function vms_ticket_integrity_snapshot_product(int $product_id, array $context): array
{
	$product_id = absint($product_id);
	$post = $product_id > 0 ? get_post($product_id) : null;
	$post_type = $post instanceof WP_Post ? (string) $post->post_type : '';
	$post_status = $post instanceof WP_Post ? (string) $post->post_status : '';

	$snapshot = array(
		'product_id' => $product_id,
		'title' => $product_id > 0 ? get_the_title($product_id) : '',
		'post_type' => $post_type,
		'post_status' => $post_status,
		'catalog_visibility' => '',
		'stock_status' => '',
		'stock_quantity' => null,
		'managing_stock' => false,
		'backorders_allowed' => false,
		'is_in_stock' => null,
		'price' => '',
		'regular_price' => '',
		'total_sales' => $product_id > 0 ? max(0, (int) get_post_meta($product_id, 'total_sales', true)) : 0,
		'linked_event_id' => $product_id > 0 ? absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true)) : 0,
		'event_plan_marker' => 0,
		'tec_event_marker' => 0,
		'role' => '',
		'ticket_key' => '',
		'entitlement_id' => '',
		'is_public' => false,
		'labels' => array(),
	);

	if ($product_id > 0 && function_exists('vms_ticketing_v2_product_meta_key')) {
		$snapshot['event_plan_marker'] = absint(get_post_meta($product_id, vms_ticketing_v2_product_meta_key('event_plan_id'), true));
		$snapshot['tec_event_marker'] = absint(get_post_meta($product_id, vms_ticketing_v2_product_meta_key('tec_event_id'), true));
		$snapshot['role'] = sanitize_key((string) get_post_meta($product_id, vms_ticketing_v2_product_meta_key('product_role'), true));
		$snapshot['ticket_key'] = sanitize_key((string) get_post_meta($product_id, vms_ticketing_v2_product_meta_key('ticketing_ticket_key'), true));
		$snapshot['entitlement_id'] = sanitize_key((string) get_post_meta($product_id, vms_ticketing_v2_product_meta_key('ticketing_entitlement_id'), true));
	}

	if ($post_type === 'product' && function_exists('wc_get_product')) {
		$product = wc_get_product($product_id);
		if ($product) {
			$snapshot['catalog_visibility'] = vms_ticket_integrity_product_catalog_visibility($product_id);
			$snapshot['stock_status'] = method_exists($product, 'get_stock_status') ? (string) $product->get_stock_status() : '';
			$snapshot['stock_quantity'] = method_exists($product, 'get_stock_quantity') ? $product->get_stock_quantity() : null;
			$snapshot['managing_stock'] = method_exists($product, 'managing_stock') ? (bool) $product->managing_stock() : false;
			$snapshot['backorders_allowed'] = method_exists($product, 'backorders_allowed') ? (bool) $product->backorders_allowed() : false;
			$snapshot['is_in_stock'] = method_exists($product, 'is_in_stock') ? (bool) $product->is_in_stock() : null;
			$snapshot['price'] = method_exists($product, 'get_price') ? (string) $product->get_price() : '';
			$snapshot['regular_price'] = method_exists($product, 'get_regular_price') ? (string) $product->get_regular_price() : '';
		}
	}

	$snapshot['is_public'] = ($snapshot['post_status'] === 'publish' && $snapshot['catalog_visibility'] !== 'hidden');

	$is_mapped_ticket = in_array($product_id, (array) ($context['mapped_ticket_product_ids'] ?? array()), true);
	$is_mapped_entitlement = in_array($product_id, (array) ($context['mapped_entitlement_product_ids'] ?? array()), true);
	$labels = array();
	if ($is_mapped_ticket || $is_mapped_entitlement) {
		$labels[] = 'Mapped by VMS';
		$labels[] = 'Active';
	} else {
		$is_legacy = (
			($snapshot['event_plan_marker'] > 0 && $snapshot['event_plan_marker'] === absint($context['plan_id'] ?? 0))
			|| ($snapshot['tec_event_marker'] > 0 && $snapshot['tec_event_marker'] === absint($context['tec_event_id'] ?? 0))
			|| $snapshot['role'] !== ''
			|| $snapshot['ticket_key'] !== ''
			|| $snapshot['entitlement_id'] !== ''
		);
		$labels[] = $is_legacy ? 'Legacy' : 'Untracked';
	}

	if ($snapshot['post_status'] === 'trash') {
		$labels[] = 'In Trash';
	} elseif ($snapshot['post_status'] === 'draft') {
		$labels[] = 'Retired';
	}

	if ($snapshot['total_sales'] > 0) {
		$labels[] = 'Has Sales';
		if (!$is_mapped_ticket && !$is_mapped_entitlement && !$snapshot['is_public']) {
			$labels[] = 'Historical Only';
		}
	}

	$snapshot['labels'] = array_values(array_unique($labels));
	return $snapshot;
}

function vms_ticket_integrity_snapshot_product_cached(int $product_id, array $context, array &$cache): array
{
	$product_id = absint($product_id);
	if ($product_id <= 0) {
		return vms_ticket_integrity_snapshot_product(0, $context);
	}

	if (!isset($cache[$product_id])) {
		$cache[$product_id] = vms_ticket_integrity_snapshot_product($product_id, $context);
	}

	return $cache[$product_id];
}

function vms_ticket_integrity_ticket_sales_state(array $ticket_row, int $product_id): array
{
	$now = time();
	$config_start_raw = trim((string) ($ticket_row['sales_start'] ?? ''));
	$config_end_raw = trim((string) ($ticket_row['sales_end'] ?? ''));
	$config_start_ts = vms_ticket_integrity_parse_wp_datetime($config_start_raw);
	$config_end_ts = vms_ticket_integrity_parse_wp_datetime($config_end_raw);
	$config_start_valid = ($config_start_raw === '' || $config_start_ts > 0);
	$config_end_valid = ($config_end_raw === '' || $config_end_ts > 0);
	$config_is_open = true;
	if ($config_start_valid && $config_start_ts > 0 && $now < $config_start_ts) {
		$config_is_open = false;
	}
	if ($config_end_valid && $config_end_ts > 0 && $now > $config_end_ts) {
		$config_is_open = false;
	}

	$product_window = array('sales_start' => '', 'sales_end' => '');
	if ($product_id > 0 && function_exists('vms_ticketing_v2_get_product_sales_window')) {
		$product_window = vms_ticketing_v2_get_product_sales_window($product_id);
	}

	$product_start_raw = trim((string) ($product_window['sales_start'] ?? ''));
	$product_end_raw = trim((string) ($product_window['sales_end'] ?? ''));
	$product_start_ts = vms_ticket_integrity_parse_wp_datetime($product_start_raw);
	$product_end_ts = vms_ticket_integrity_parse_wp_datetime($product_end_raw);
	$product_start_valid = ($product_start_raw === '' || $product_start_ts > 0);
	$product_end_valid = ($product_end_raw === '' || $product_end_ts > 0);
	$product_window_present = ($product_start_raw !== '' || $product_end_raw !== '');
	$product_is_open = true;
	if ($product_start_valid && $product_start_ts > 0 && $now < $product_start_ts) {
		$product_is_open = false;
	}
	if ($product_end_valid && $product_end_ts > 0 && $now > $product_end_ts) {
		$product_is_open = false;
	}

	return array(
		'config_start_raw' => $config_start_raw,
		'config_end_raw' => $config_end_raw,
		'config_start_valid' => $config_start_valid,
		'config_end_valid' => $config_end_valid,
		'config_start_ts' => $config_start_ts,
		'config_end_ts' => $config_end_ts,
		'config_window_valid' => ($config_start_valid && $config_end_valid),
		'config_is_open' => $config_is_open,
		'product_start_raw' => $product_start_raw,
		'product_end_raw' => $product_end_raw,
		'product_start_valid' => $product_start_valid,
		'product_end_valid' => $product_end_valid,
		'product_start_ts' => $product_start_ts,
		'product_end_ts' => $product_end_ts,
		'product_window_present' => $product_window_present,
		'product_is_open' => $product_is_open,
	);
}

function vms_ticket_integrity_ticket_is_customer_facing(array $ticket): bool
{
	$visibility = sanitize_key((string) ($ticket['visibility_mode'] ?? 'public'));
	return in_array($visibility, array('public', 'login'), true);
}

function vms_ticket_integrity_ticket_is_sellable(array $ticket_snapshot): bool
{
	$product = is_array($ticket_snapshot['product'] ?? null) ? $ticket_snapshot['product'] : array();
	if (($product['post_type'] ?? '') !== 'product' || empty($product['is_public'])) {
		return false;
	}

	if (!empty($ticket_snapshot['sales_state']['product_window_present']) && empty($ticket_snapshot['sales_state']['product_is_open'])) {
		return false;
	}

	if (($product['is_in_stock'] ?? null) === false || (string) ($product['stock_status'] ?? '') === 'outofstock') {
		return false;
	}

	if (!empty($product['managing_stock']) && !$product['backorders_allowed']) {
		$stock_quantity = $product['stock_quantity'];
		if (is_numeric($stock_quantity) && (int) $stock_quantity <= 0) {
			return false;
		}
	}

	$config_price = (float) ($ticket_snapshot['config_price'] ?? 0);
	if ($config_price > 0 && (float) ($product['price'] ?? 0) <= 0) {
		return false;
	}

	return true;
}

function vms_ticket_integrity_build_context(int $plan_id): array
{
	$plan_id = absint($plan_id);
	$plan = $plan_id > 0 ? get_post($plan_id) : null;
	$tec_event_id = $plan_id > 0 && function_exists('vms_ticketing_b_get_linked_tec_event_id')
		? absint(vms_ticketing_b_get_linked_tec_event_id($plan_id))
		: absint(get_post_meta($plan_id, '_vms_tec_event_id', true));
	$event = $tec_event_id > 0 ? get_post($tec_event_id) : null;

	$saved_cfg = function_exists('vms_ticketing_v2_get_saved_config') ? vms_ticketing_v2_get_saved_config($plan_id) : array();
	$cfg = !empty($saved_cfg) ? $saved_cfg : (function_exists('vms_ticketing_v2_get_config') ? vms_ticketing_v2_get_config($plan_id) : array());
	$sync = function_exists('vms_ticketing_v2_get_sync') ? vms_ticketing_v2_get_sync($plan_id) : array();
	$sync_map = is_array($sync['map'] ?? null) ? $sync['map'] : array();

	$mapped_ticket_ids = array();
	if (is_array($sync_map['tickets'] ?? null)) {
		foreach ($sync_map['tickets'] as $row) {
			if (!is_array($row)) {
				continue;
			}
			$pid = absint($row['woo_product_id'] ?? 0);
			if ($pid > 0) {
				$mapped_ticket_ids[] = $pid;
			}
		}
	}

	if (is_array($sync_map['ga'] ?? null)) {
		$legacy_ga_pid = absint($sync_map['ga']['woo_product_id'] ?? 0);
		if ($legacy_ga_pid > 0) {
			$mapped_ticket_ids[] = $legacy_ga_pid;
		}
	}

	$mapped_entitlement_ids = array();
	if (is_array($sync_map['entitlements'] ?? null)) {
		foreach ($sync_map['entitlements'] as $row) {
			if (!is_array($row)) {
				continue;
			}
			$pid = absint($row['woo_product_id'] ?? 0);
			if ($pid > 0) {
				$mapped_entitlement_ids[] = $pid;
			}
		}
	}

	$attached_product_ids = array();
	if ($tec_event_id > 0 && function_exists('vms_ticketing_b_get_event_ticket_products')) {
		$attached_product_ids = array_values(array_unique(array_filter(array_map('absint', (array) vms_ticketing_b_get_event_ticket_products($tec_event_id)))));
	}

	$event_timestamp = vms_ticket_integrity_event_timestamp($plan_id, $tec_event_id);
	$event_date_local = $event_timestamp > 0 ? vms_ticket_integrity_format_datetime($event_timestamp) : '';
	$mode = sanitize_key((string) ($cfg['mode'] ?? ''));
	if ($mode === '' && function_exists('vms_ticketing_b_get_mode')) {
		$mode = sanitize_key(vms_ticketing_b_get_mode($plan_id));
	}

	$ticketing_enabled = function_exists('vms_event_plan_is_ticketing_enabled')
		? (bool) vms_event_plan_is_ticketing_enabled($plan_id)
		: true;

	$verification_programs = function_exists('vms_ticketing_verification_programs')
		? array_keys(vms_ticketing_verification_programs())
		: array();

	return array(
		'plan_id' => $plan_id,
		'plan_exists' => ($plan instanceof WP_Post),
		'plan_status' => $plan instanceof WP_Post ? (string) $plan->post_status : '',
		'tec_event_id' => $tec_event_id,
		'event_exists' => ($event instanceof WP_Post),
		'event_status' => $event instanceof WP_Post ? (string) $event->post_status : '',
		'event_title' => $event instanceof WP_Post ? get_the_title($tec_event_id) : ($plan instanceof WP_Post ? get_the_title($plan_id) : ''),
		'event_timestamp' => $event_timestamp,
		'event_date_local' => $event_date_local,
		'event_url' => $tec_event_id > 0 ? (string) get_permalink($tec_event_id) : '',
		'edit_plan_url' => $plan_id > 0 ? (string) get_edit_post_link($plan_id, '') : '',
		'edit_event_url' => $tec_event_id > 0 ? (string) get_edit_post_link($tec_event_id, '') : '',
		'has_saved_config' => !empty($saved_cfg),
		'cfg' => is_array($cfg) ? $cfg : array(),
		'sync' => is_array($sync) ? $sync : array(),
		'sync_map' => $sync_map,
		'mode' => $mode,
		'ticketing_enabled' => $ticketing_enabled,
		'cancelled' => ($tec_event_id > 0 && function_exists('vms_tec_is_cancelled_event')) ? (bool) vms_tec_is_cancelled_event($tec_event_id) : false,
		'attached_product_ids' => $attached_product_ids,
		'mapped_ticket_product_ids' => array_values(array_unique($mapped_ticket_ids)),
		'mapped_entitlement_product_ids' => array_values(array_unique($mapped_entitlement_ids)),
		'mapped_all_product_ids' => array_values(array_unique(array_merge($mapped_ticket_ids, $mapped_entitlement_ids))),
		'verification_programs' => array_values(array_unique(array_filter(array_map('sanitize_key', $verification_programs)))),
	);
}

function vms_ticket_integrity_build_ticket_snapshots(array $context, array &$product_cache): array
{
	$tickets = array();
	if (empty($context['has_saved_config']) || !is_array($context['cfg']['tickets'] ?? null)) {
		return $tickets;
	}

	$ticket_sync_map = is_array($context['sync_map']['tickets'] ?? null) ? $context['sync_map']['tickets'] : array();
	$legacy_ga_pid = is_array($context['sync_map']['ga'] ?? null) ? absint($context['sync_map']['ga']['woo_product_id'] ?? 0) : 0;

	foreach (array_values($context['cfg']['tickets']) as $index => $ticket_row) {
		if (!is_array($ticket_row)) {
			continue;
		}

		$enabled = array_key_exists('enabled', $ticket_row) ? !empty($ticket_row['enabled']) : true;
		if (!$enabled) {
			continue;
		}

		$ticket_key = sanitize_key((string) ($ticket_row['ticket_key'] ?? $ticket_row['key'] ?? ''));
		if ($ticket_key === '') {
			$ticket_key = 'ticket_' . (string) $index;
		}

		$mapped_product_id = 0;
		if (!empty($ticket_sync_map[$ticket_key]) && is_array($ticket_sync_map[$ticket_key])) {
			$mapped_product_id = absint($ticket_sync_map[$ticket_key]['woo_product_id'] ?? 0);
		} elseif ((int) $index === 0 && $legacy_ga_pid > 0) {
			$mapped_product_id = $legacy_ga_pid;
		}

		$product = vms_ticket_integrity_snapshot_product_cached($mapped_product_id, $context, $product_cache);
		$mapping_state = 'unmapped';
		if ($mapped_product_id > 0) {
			if (($product['post_type'] ?? '') === '') {
				$mapping_state = 'missing';
			} elseif (($product['post_type'] ?? '') !== 'product') {
				$mapping_state = 'not_product';
			} elseif (($product['post_status'] ?? '') === 'trash') {
				$mapping_state = 'trash';
			} elseif (($product['linked_event_id'] ?? 0) > 0 && absint($product['linked_event_id']) !== absint($context['tec_event_id'] ?? 0)) {
				$mapping_state = 'event_mismatch';
			} else {
				$mapping_state = 'ok';
			}
		}

		$visibility_mode = sanitize_key((string) ($ticket_row['visibility_mode'] ?? 'public'));
		if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
			$visibility_mode = 'public';
		}

		$verified_program = sanitize_key((string) ($ticket_row['verified_program'] ?? ''));
		$allowed_programs = vms_ticket_integrity_normalize_program_list($ticket_row['allowed_programs'] ?? array(), $verified_program);

		$tickets[] = array(
			'ticket_key' => $ticket_key,
			'title' => trim((string) ($ticket_row['title'] ?? $ticket_key)),
			'visibility_mode' => $visibility_mode,
			'verified_program' => $verified_program,
			'allowed_programs' => $allowed_programs,
			'allow_direct_grants' => !empty($ticket_row['allow_direct_grants']),
			'counts_toward_unlock' => !empty($ticket_row['counts_toward_unlock']),
			'config_price' => (float) ($ticket_row['price'] ?? 0),
			'inventory_total' => max(0, (int) ($ticket_row['inventory_total'] ?? 0)),
			'mapped_product_id' => $mapped_product_id,
			'mapping_state' => $mapping_state,
			'product' => $product,
			'product_labels' => (array) ($product['labels'] ?? array()),
			'sales_state' => vms_ticket_integrity_ticket_sales_state($ticket_row, $mapped_product_id),
			'customer_facing' => vms_ticket_integrity_ticket_is_customer_facing($ticket_row),
		);
	}

	return $tickets;
}

function vms_ticket_integrity_build_entitlement_snapshots(array $context, array &$product_cache): array
{
	$entitlements = array();
	if (empty($context['has_saved_config']) || !is_array($context['cfg']['entitlements'] ?? null)) {
		return $entitlements;
	}

	$ent_sync_map = is_array($context['sync_map']['entitlements'] ?? null) ? $context['sync_map']['entitlements'] : array();
	foreach ((array) $context['cfg']['entitlements'] as $entitlement) {
		if (!is_array($entitlement) || empty($entitlement['enabled'])) {
			continue;
		}

		$entitlement_id = sanitize_key((string) ($entitlement['entitlement_id'] ?? ''));
		if ($entitlement_id === '') {
			continue;
		}

		$mapped_product_id = !empty($ent_sync_map[$entitlement_id]) && is_array($ent_sync_map[$entitlement_id])
			? absint($ent_sync_map[$entitlement_id]['woo_product_id'] ?? 0)
			: 0;
		$product = vms_ticket_integrity_snapshot_product_cached($mapped_product_id, $context, $product_cache);
		$mapping_state = 'unmapped';
		if ($mapped_product_id > 0) {
			if (($product['post_type'] ?? '') === '') {
				$mapping_state = 'missing';
			} elseif (($product['post_type'] ?? '') !== 'product') {
				$mapping_state = 'not_product';
			} elseif (($product['post_status'] ?? '') === 'trash') {
				$mapping_state = 'trash';
			} else {
				$mapping_state = 'ok';
			}
		}

		$eligibility = is_array($entitlement['eligibility'] ?? null) ? $entitlement['eligibility'] : array();
		if (function_exists('vms_ticketing_v2_resolve_eligibility_for_product')) {
			$eligibility = vms_ticketing_v2_resolve_eligibility_for_product($mapped_product_id, absint($context['plan_id'] ?? 0), $entitlement);
		}

		$entitlements[] = array(
			'entitlement_id' => $entitlement_id,
			'label' => trim((string) ($entitlement['label'] ?? $entitlement_id)),
			'capacity' => max(0, (int) ($entitlement['capacity'] ?? 0)),
			'mapped_product_id' => $mapped_product_id,
			'mapping_state' => $mapping_state,
			'product' => $product,
			'eligibility' => $eligibility,
		);
	}

	return $entitlements;
}

function vms_ticket_integrity_should_compact_scan_payload(array $args = array()): bool
{
	return !empty($args['compact_diagnostics']);
}

function vms_ticket_integrity_compact_mutation_diagnostics(array $diagnostics): array
{
	if (empty($diagnostics)) {
		return array();
	}

	$compact = array();
	foreach (array('origin', 'latest_mutation', 'last_repair', 'repeated_drift', 'public_path_healthy', 'recommended_action') as $key) {
		if (array_key_exists($key, $diagnostics)) {
			$compact[$key] = $diagnostics[$key];
		}
	}

	return $compact;
}

function vms_ticket_integrity_compact_inventory_diagnostics(array $diagnostics): array
{
	if (empty($diagnostics)) {
		return array();
	}

	$compact = array();
	foreach (
		array(
			'event_capacity',
			'event_available',
			'event_sold',
			'event_global_stock_enabled',
			'event_global_stock_level',
			'zero_available_mismatch',
			'zero_available_ticket_count',
			'all_public_tickets_zero',
			'addon_divergence',
			'woo_primary_mismatch',
			'woo_primary_mismatch_count',
			'tec_followup_required',
			'tec_followup_count',
			'woo_recorruption_detected',
			'suspected_cause',
			'suspected_cause_label',
			'cause_reasons',
			'verification_summary',
			'upstream_writer_suspect',
			'latest_inventory_mutation',
			'repeated_inventory_drift',
			'recommended_action',
			'origin_cluster'
		) as $key
	) {
		if (array_key_exists($key, $diagnostics)) {
			$compact[$key] = $diagnostics[$key];
		}
	}

	$woo_recorruption = is_array($diagnostics['woo_recorruption'] ?? null) ? $diagnostics['woo_recorruption'] : array();
	if (!empty($woo_recorruption)) {
		unset($woo_recorruption['rows'], $woo_recorruption['rows_flagged']);
		$compact['woo_recorruption'] = $woo_recorruption;
	}

	return $compact;
}

function vms_ticket_integrity_compact_repair_diagnostics(array $diagnostics): array
{
	if (empty($diagnostics)) {
		return array();
	}

	$compact = array();
	foreach (array('saved_at_gmt', 'repair_status', 'repair_status_label', 'summary_text', 'detail_state', 'changed', 'preview_change_count') as $key) {
		if (array_key_exists($key, $diagnostics)) {
			$compact[$key] = $diagnostics[$key];
		}
	}

	$role_breakdown = array();
	foreach ((array) ($diagnostics['role_breakdown'] ?? array()) as $role_key => $group) {
		if (!is_array($group)) {
			continue;
		}

		unset($group['entries']);
		$role_breakdown[$role_key] = $group;
	}
	if (!empty($role_breakdown)) {
		$compact['role_breakdown'] = $role_breakdown;
	}

	return $compact;
}

function vms_ticket_integrity_scan_event_record(int $plan_id, array $args = array()): array
{
	$plan_id = absint($plan_id);
	$context = vms_ticket_integrity_build_context($plan_id);
	$product_cache = array();
	$issues = array();
	$now = time();

	if (empty($context['plan_exists'])) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'missing_plan',
				'red',
				'event',
				__('Event Plan is missing', 'vms'),
				__('The monitor could not load the source Event Plan record for this scan target.', 'vms')
			)
		);
	}

	if (empty($context['event_exists']) || empty($context['tec_event_id'])) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'missing_calendar_event',
				'red',
				'event',
				__('Linked calendar event is missing', 'vms'),
				__('This Event Plan no longer has a valid published TEC event to sell against, so customers cannot reliably see live tickets.', 'vms')
			)
		);
	}

	if (!empty($context['event_exists']) && (string) ($context['event_status'] ?? '') !== 'publish') {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'event_not_public',
				'red',
				'event',
				__('Linked event is not public', 'vms'),
				__('The linked calendar event is not published, so the public ticket path is blocked even if products still exist.', 'vms')
			)
		);
	}

	if (absint($context['event_timestamp'] ?? 0) <= 0) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'event_time_unresolved',
				'yellow',
				'event',
				__('Event time could not be resolved cleanly', 'vms'),
				__('The monitor could not confidently resolve the event start date/time from the WordPress-side records, which makes upcoming-window decisions less reliable.', 'vms')
			)
		);
	} elseif ((int) $context['event_timestamp'] <= $now) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'event_not_upcoming',
				'informational',
				'event',
				__('Event is no longer upcoming', 'vms'),
				__('This event is outside the normal “published upcoming events” monitoring window, so any remaining ticket residue is informational unless you explicitly include inactive events.', 'vms')
			)
		);
	}

	if (!empty($context['cancelled'])) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'event_cancelled',
				'informational',
				'event',
				__('Event is cancelled', 'vms'),
				__('This event is cancelled, so closed sales are expected unless you are deliberately reviewing inactive-event residue.', 'vms')
			)
		);
	}

	if (empty($context['ticketing_enabled'])) {
		$severity = (!empty($context['attached_product_ids']) || !empty($context['mapped_all_product_ids'])) ? 'yellow' : 'informational';
		$details = ($severity === 'yellow')
			? __('Ticketing is disabled for this event, but linked ticket products or mappings still exist. Review whether this is intentional inactive residue or a disabled live path.', 'vms')
			: __('Ticketing is disabled for this event, so the monitor is treating missing public ticket output as intentional unless live ticket objects still exist.', 'vms');
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'ticketing_disabled',
				$severity,
				'event',
				__('Ticketing is disabled for this event', 'vms'),
				$details
			)
		);
	}

	if (empty($context['has_saved_config']) && (!empty($context['attached_product_ids']) || !empty($context['mapped_all_product_ids']))) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'no_saved_v2_config',
				'informational',
				'structure',
				__('No saved Ticketing v2 config is present', 'vms'),
				__('Live ticket products exist, but this Event Plan does not currently store a saved Ticketing v2 config. The monitor is treating the attached products as the live source of truth and the config side as potentially legacy or partial.', 'vms')
			)
		);
	}

	$context['attached_products'] = array();
	foreach ((array) $context['attached_product_ids'] as $product_id) {
		$context['attached_products'][] = vms_ticket_integrity_snapshot_product_cached((int) $product_id, $context, $product_cache);
	}

	$context['ticket_snapshots'] = vms_ticket_integrity_build_ticket_snapshots($context, $product_cache);
	$context['entitlement_snapshots'] = vms_ticket_integrity_build_entitlement_snapshots($context, $product_cache);

	if (!empty($context['event_exists']) && !empty($context['tec_event_id']) && function_exists('vms_ticketing_v2_reconcile_event_plan_ticket_cache')) {
		$recon = vms_ticketing_v2_reconcile_event_plan_ticket_cache((int) $context['plan_id'], (int) $context['tec_event_id'], (array) $context['sync_map'], false);

		if (!empty($recon['mapped_missing_product_ids'])) {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'recon_missing_products',
					'red',
					'mapping',
					__('Mapped ticket products are missing', 'vms'),
					__('One or more products still referenced by the active VMS ticket map no longer exist. Customers may lose the intended live ticket path until the mapping is rebuilt.', 'vms'),
					array('product_ids' => array_values(array_map('absint', (array) $recon['mapped_missing_product_ids'])))
				)
			);
		}

		if (!empty($recon['mapped_trashed_product_ids'])) {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'recon_trashed_products',
					'red',
					'mapping',
					__('Mapped ticket products are in Trash', 'vms'),
					__('The active VMS sync map still points at products in Trash. This is a live-risk condition, not harmless history, because the current mapping still references those objects.', 'vms'),
					array('product_ids' => array_values(array_map('absint', (array) $recon['mapped_trashed_product_ids'])))
				)
			);
		}

		if (!empty($recon['mapped_not_product_ids'])) {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'recon_not_products',
					'red',
					'mapping',
					__('Mapped IDs are not Woo products', 'vms'),
					__('The active ticket map points at posts that are not WooCommerce products, so the current live ticket identity is not trustworthy.', 'vms'),
					array('product_ids' => array_values(array_map('absint', (array) $recon['mapped_not_product_ids'])))
				)
			);
		}

		if (!empty($recon['mapped_marker_mismatch_product_ids'])) {
			$severity = !empty($context['mapped_ticket_product_ids']) ? 'yellow' : 'red';
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'recon_marker_mismatch',
					$severity,
					'mapping',
					__('Mapped products have marker mismatches', 'vms'),
					__('At least one mapped product carries plan/event markers that disagree with the current Event Plan. That often signals drift after a partial commit, migration, or manual reassignment.', 'vms'),
					array('product_ids' => array_values(array_map('absint', (array) $recon['mapped_marker_mismatch_product_ids'])))
				)
			);
		}

		if (!empty($recon['detected_unmapped_product_ids'])) {
			$detected_unmapped_ids = array_values(array_map('absint', (array) $recon['detected_unmapped_product_ids']));
			$public_unmapped = array();
			$history_only = true;
			foreach ($detected_unmapped_ids as $product_id) {
				$product = vms_ticket_integrity_snapshot_product_cached($product_id, $context, $product_cache);
				if (!empty($product['is_public'])) {
					$public_unmapped[] = $product_id;
				}
				if (empty($product['labels']) || !in_array('Historical Only', (array) $product['labels'], true)) {
					$history_only = false;
				}
			}

			$severity = 'yellow';
			$title = __('Extra ticket products are attached to the event', 'vms');
			$details = __('The linked TEC event contains ticket products that are not tracked in the current VMS sync map. Review whether they are harmless leftovers or a conflicting live path.', 'vms');

			if ($history_only && empty($public_unmapped)) {
				$severity = 'informational';
				$title = __('Legacy ticket residue detected', 'vms');
				$details = __('Extra ticket products exist, but they currently read as historical-only residue rather than the active customer-facing path.', 'vms');
			} elseif (empty($context['mapped_ticket_product_ids']) && count($public_unmapped) > 1) {
				$severity = 'red';
				$title = __('Current live ticket path is ambiguous', 'vms');
				$details = __('Multiple public ticket products are attached to the event, but VMS does not currently have a clear authoritative active mapping. Manual cleanup is not recommended until the mapping is rebuilt.', 'vms');
			}

			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'recon_unmapped_products',
					$severity,
					'mapping',
					$title,
					$details,
					array('product_ids' => $detected_unmapped_ids)
				)
			);
		}
	}

	$active_ticket_keys = array();
	$active_ticket_title_tokens = array();
	foreach ((array) $context['ticket_snapshots'] as $ticket_snapshot) {
		$ticket_key = sanitize_key((string) ($ticket_snapshot['ticket_key'] ?? ''));
		if ($ticket_key !== '') {
			$active_ticket_keys[$ticket_key] = true;
		}
		$title_token = vms_ticket_integrity_normalize_title_token((string) ($ticket_snapshot['title'] ?? ''));
		if ($title_token !== '') {
			$active_ticket_title_tokens[$title_token] = true;
		}
	}

	$duplicate_live_ticket_products = array();
	$extra_public_ticket_products = array();
	foreach ((array) ($context['attached_products'] ?? array()) as $attached_product) {
		if (!is_array($attached_product)) {
			continue;
		}

		$product_id = absint($attached_product['product_id'] ?? 0);
		if ($product_id <= 0 || in_array($product_id, (array) ($context['mapped_all_product_ids'] ?? array()), true)) {
			continue;
		}
		if (($attached_product['post_type'] ?? '') !== 'product' || empty($attached_product['is_public'])) {
			continue;
		}

		$product_role = sanitize_key((string) ($attached_product['role'] ?? ''));
		if ($product_role === 'addon') {
			continue;
		}

		$ticket_key = sanitize_key((string) ($attached_product['ticket_key'] ?? ''));
		$title = (string) ($attached_product['title'] ?? '');
		$title_token = vms_ticket_integrity_normalize_title_token($title);
		$matches_active_ticket = ($ticket_key !== '' && isset($active_ticket_keys[$ticket_key]))
			|| ($title_token !== '' && isset($active_ticket_title_tokens[$title_token]));

		$extra_public_ticket_products[] = array(
			'product_id' => $product_id,
			'title' => $title,
			'ticket_key' => $ticket_key,
		);

		if ($matches_active_ticket) {
			$duplicate_live_ticket_products[] = array(
				'product_id' => $product_id,
				'title' => $title,
				'ticket_key' => $ticket_key,
			);
		}
	}

	if (!empty($duplicate_live_ticket_products)) {
		$product_ids = array_values(array_unique(array_filter(array_map('absint', wp_list_pluck($duplicate_live_ticket_products, 'product_id')))));
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'duplicate_live_ticket_products_attached',
				'red',
				'mapping',
				__('Duplicate live ticket products are still attached to the event', 'vms'),
				__('One or more extra public ticket products still match the active VMS ticket titles or ticket keys. Customers can see duplicate admission options until the leftover live products are retired or detached.', 'vms'),
				array('product_ids' => $product_ids)
			)
		);
	} elseif (!empty($extra_public_ticket_products)) {
		$product_ids = array_values(array_unique(array_filter(array_map('absint', wp_list_pluck($extra_public_ticket_products, 'product_id')))));
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'extra_public_ticket_products_attached',
				'yellow',
				'mapping',
				__('Extra public ticket products are still attached to the event', 'vms'),
				__('Public-facing ticket products exist outside the active VMS map. Even when titles do not collide cleanly, they can still reopen an unintended customer-facing ticket path.', 'vms'),
				array('product_ids' => $product_ids)
			)
		);
	}

	$product_role_map = array();
	$title_groups = array();
	foreach ((array) $context['ticket_snapshots'] as $ticket_snapshot) {
		$product_id = absint($ticket_snapshot['mapped_product_id'] ?? 0);
		if ($product_id > 0) {
			$product_role_map[$product_id][] = 'ticket:' . (string) ($ticket_snapshot['ticket_key'] ?? '');
		}

		$title_token = vms_ticket_integrity_normalize_title_token((string) ($ticket_snapshot['title'] ?? ''));
		if ($title_token !== '') {
			$title_groups[$title_token][] = (string) ($ticket_snapshot['title'] ?? '');
		}
	}

	foreach ((array) $context['entitlement_snapshots'] as $ent_snapshot) {
		$product_id = absint($ent_snapshot['mapped_product_id'] ?? 0);
		if ($product_id > 0) {
			$product_role_map[$product_id][] = 'entitlement:' . (string) ($ent_snapshot['entitlement_id'] ?? '');
		}
	}

	foreach ($product_role_map as $product_id => $roles) {
		$roles = array_values(array_unique(array_filter(array_map('strval', (array) $roles))));
		if (count($roles) < 2) {
			continue;
		}

		$ticket_roles = array_values(array_filter($roles, static function (string $role): bool {
			return strpos($role, 'ticket:') === 0;
		}));
		$severity = count($ticket_roles) > 1 ? 'red' : 'yellow';
		$details = count($ticket_roles) > 1
			? __('A single product is currently mapped to multiple ticket rows, which makes the active live ticket identity ambiguous.', 'vms')
			: __('A single product is currently reused across ticket/add-on roles. Review whether that is an intentional shortcut or a drifted mapping.', 'vms');

		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'duplicate_mapping_' . $product_id,
				$severity,
				'structure',
				__('A product is mapped to multiple active roles', 'vms'),
				$details,
				array('product_id' => $product_id, 'roles' => $roles)
			)
		);
	}

	foreach ($title_groups as $title_token => $titles) {
		if ($title_token === '' || count($titles) < 2) {
			continue;
		}

		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'duplicate_ticket_titles_' . sanitize_key($title_token),
				'yellow',
				'structure',
				__('Multiple enabled tickets share the same public title', 'vms'),
				__('Enabled ticket rows currently share the same visible title. That can confuse operators during reconciliation and customers if multiple similar live objects remain attached to the event.', 'vms')
			)
		);
	}

	$verification_programs = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($context['verification_programs'] ?? array())))));
	foreach ((array) $context['ticket_snapshots'] as $ticket_snapshot) {
		$ticket_key = sanitize_key((string) ($ticket_snapshot['ticket_key'] ?? 'ticket'));
		$title = (string) ($ticket_snapshot['title'] ?? $ticket_key);
		$product = is_array($ticket_snapshot['product'] ?? null) ? $ticket_snapshot['product'] : array();
		$sales_state = is_array($ticket_snapshot['sales_state'] ?? null) ? $ticket_snapshot['sales_state'] : array();

		if ((string) ($ticket_snapshot['mapping_state'] ?? '') === 'unmapped') {
			$severity = ($context['mode'] === 'vms_managed' || empty($context['attached_product_ids'])) ? 'red' : 'yellow';
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'ticket_unmapped_' . $ticket_key,
					$severity,
					'mapping',
					sprintf(__('Enabled ticket "%s" has no mapped product', 'vms'), $title),
					__('This ticket row is enabled, but VMS does not currently have a linked Woo/TEC product for it. If this is supposed to be part of the live public path, customers are at risk.', 'vms')
				)
			);
		} elseif ((string) ($ticket_snapshot['mapping_state'] ?? '') !== 'ok') {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'ticket_mapping_problem_' . $ticket_key,
					'red',
					'mapping',
					sprintf(__('Enabled ticket "%s" points to an invalid product object', 'vms'), $title),
					__('The mapped product is missing, trashed, not a product, or linked to the wrong event. The current customer-facing path is not trustworthy until this mapping is repaired.', 'vms')
				)
			);
		}

		if (($product['post_type'] ?? '') === 'product') {
			if (in_array((string) ($product['post_status'] ?? ''), array('draft', 'private'), true)) {
				vms_ticket_integrity_add_issue(
					$issues,
					vms_ticket_integrity_issue(
						'ticket_hidden_status_' . $ticket_key,
						'red',
						'visibility',
						sprintf(__('Enabled ticket "%s" is unpublished', 'vms'), $title),
						__('The mapped ticket product exists but is not publicly published, so customers may not be able to purchase it.', 'vms')
					)
				);
			}

			if ((string) ($product['catalog_visibility'] ?? '') === 'hidden') {
				vms_ticket_integrity_add_issue(
					$issues,
					vms_ticket_integrity_issue(
						'ticket_hidden_catalog_' . $ticket_key,
						'red',
						'visibility',
						sprintf(__('Enabled ticket "%s" is hidden from catalog visibility', 'vms'), $title),
						__('The mapped ticket product is marked hidden, which can suppress the public purchase path even when the ticket should be on sale.', 'vms')
					)
				);
			}

			if (is_numeric($product['stock_quantity']) && (int) $product['stock_quantity'] < 0) {
				vms_ticket_integrity_add_issue(
					$issues,
					vms_ticket_integrity_issue(
						'ticket_negative_stock_' . $ticket_key,
						'red',
						'inventory',
						sprintf(__('Enabled ticket "%s" has negative stock state', 'vms'), $title),
						__('The mapped product reports a negative stock quantity, which is a malformed live inventory state.', 'vms')
					)
				);
			}

			if (!empty($product['managing_stock']) && !$product['backorders_allowed'] && is_numeric($product['stock_quantity']) && (int) $product['stock_quantity'] === 0 && (int) ($ticket_snapshot['inventory_total'] ?? 0) > 0) {
				vms_ticket_integrity_add_issue(
					$issues,
					vms_ticket_integrity_issue(
						'ticket_zero_stock_mismatch_' . $ticket_key,
						'red',
						'inventory',
						sprintf(__('Enabled ticket "%s" looks sold out unexpectedly', 'vms'), $title),
						__('The Event Plan config still suggests sellable capacity, but the mapped product currently reports zero stock.', 'vms')
					)
				);
			}

			if ((($product['is_in_stock'] ?? null) === false || (string) ($product['stock_status'] ?? '') === 'outofstock') && !empty($ticket_snapshot['customer_facing']) && !empty($sales_state['config_is_open'])) {
				$severity = ((int) ($ticket_snapshot['inventory_total'] ?? 0) > 0) ? 'red' : 'yellow';
				vms_ticket_integrity_add_issue(
					$issues,
					vms_ticket_integrity_issue(
						'ticket_outofstock_' . $ticket_key,
						$severity,
						'inventory',
						sprintf(__('Enabled ticket "%s" is currently out of stock', 'vms'), $title),
						__('The mapped product is reporting out-of-stock while the customer-facing config still reads as active. This can create a false sold-out condition or an unexpectedly closed path.', 'vms')
					)
				);
			}

			if (function_exists('vms_ticket_integrity_low_inventory_signal')) {
				$inventory_signal = vms_ticket_integrity_low_inventory_signal($ticket_snapshot, absint($context['event_timestamp'] ?? 0));
				if (!empty($inventory_signal['flagged'])) {
					$remaining = absint($inventory_signal['remaining'] ?? 0);
					$total = absint($inventory_signal['total'] ?? 0);
					$percent = is_numeric($inventory_signal['percent_remaining'] ?? null) ? number_format((float) $inventory_signal['percent_remaining'], 1) : '0.0';
					$details = ($total > 0)
						? sprintf(__('Remaining inventory is %1$d of %2$d (%3$s%%).', 'vms'), $remaining, $total, $percent)
						: sprintf(__('Remaining inventory is %1$d tickets.', 'vms'), $remaining);

					vms_ticket_integrity_add_issue(
						$issues,
						vms_ticket_integrity_issue(
							'ticket_low_inventory_' . $ticket_key,
							(string) ($inventory_signal['severity'] ?? 'yellow'),
							'inventory',
							sprintf(__('Ticket "%s" is running low', 'vms'), $title),
							$details,
							array(
								'issue_kind' => 'low_inventory',
								'remaining' => $remaining,
								'total' => $total,
								'percent_remaining' => (float) ($inventory_signal['percent_remaining'] ?? 0),
							)
						)
					);
				}
			}

			if ((float) ($ticket_snapshot['config_price'] ?? 0) > 0 && (float) ($product['price'] ?? 0) <= 0) {
				vms_ticket_integrity_add_issue(
					$issues,
					vms_ticket_integrity_issue(
						'ticket_price_mismatch_' . $ticket_key,
						'red',
						'data',
						sprintf(__('Enabled ticket "%s" is missing a live price', 'vms'), $title),
						__('The Event Plan config still expects this to be a paid ticket, but the mapped product price is blank or zero.', 'vms')
					)
				);
			}
		}

		if (!empty($sales_state['config_start_raw']) && empty($sales_state['config_start_valid'])) {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'ticket_bad_start_' . $ticket_key,
					'red',
					'sale_window',
					sprintf(__('Enabled ticket "%s" has a malformed sale start', 'vms'), $title),
					__('The configured sale-start value could not be parsed with WordPress timezone semantics, so the public open/closed state may be wrong.', 'vms')
				)
			);
		}

		if (!empty($sales_state['config_end_raw']) && empty($sales_state['config_end_valid'])) {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'ticket_bad_end_' . $ticket_key,
					'red',
					'sale_window',
					sprintf(__('Enabled ticket "%s" has a malformed sale end', 'vms'), $title),
					__('The configured sale-end value could not be parsed with WordPress timezone semantics, so the public open/closed state may be wrong.', 'vms')
				)
			);
		}

		if (!empty($sales_state['config_start_valid']) && !empty($sales_state['config_end_valid']) && (int) ($sales_state['config_start_ts'] ?? 0) > 0 && (int) ($sales_state['config_end_ts'] ?? 0) > 0 && (int) $sales_state['config_end_ts'] < (int) $sales_state['config_start_ts']) {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'ticket_window_reversed_' . $ticket_key,
					'red',
					'sale_window',
					sprintf(__('Enabled ticket "%s" has a reversed sale window', 'vms'), $title),
					__('The sale end is earlier than the sale start, which is a hard customer-facing risk.', 'vms')
				)
			);
		}

		if (!empty($sales_state['product_window_present']) && !empty($sales_state['config_is_open']) && empty($sales_state['product_is_open']) && !empty($ticket_snapshot['customer_facing'])) {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'ticket_window_drift_' . $ticket_key,
					'red',
					'sale_window',
					sprintf(__('Enabled ticket "%s" appears closed by live product dates', 'vms'), $title),
					__('The Event Plan config reads as currently on sale, but the mapped product carries start/end dates that currently close the product. This is a strong false sold-out / early-close signal.', 'vms')
				)
			);
		}

		if ((string) ($ticket_snapshot['visibility_mode'] ?? '') === 'verified') {
			$allowed_programs = (array) ($ticket_snapshot['allowed_programs'] ?? array());
			$allow_direct_grants = !empty($ticket_snapshot['allow_direct_grants']);
			if (empty($allowed_programs) && !$allow_direct_grants) {
				vms_ticket_integrity_add_issue(
					$issues,
					vms_ticket_integrity_issue(
						'ticket_verified_invalid_' . $ticket_key,
						'red',
						'verified',
						sprintf(__('Verified ticket "%s" has no valid qualification rule', 'vms'), $title),
						__('This ticket is gated as a verified-only path, but no credential program or direct-grant rule is currently configured for it.', 'vms')
					)
				);
			} else {
				$missing_programs = array_values(array_diff($allowed_programs, $verification_programs));
				if (!empty($missing_programs)) {
					vms_ticket_integrity_add_issue(
						$issues,
						vms_ticket_integrity_issue(
							'ticket_verified_program_missing_' . $ticket_key,
							'red',
							'verified',
							sprintf(__('Verified ticket "%s" references missing programs', 'vms'), $title),
							__('One or more verification programs referenced by this ticket no longer exist in the current program registry.', 'vms'),
							array('programs' => $missing_programs)
						)
					);
				}
			}
		}
	}

	$expected_public_tickets = array_values(array_filter((array) $context['ticket_snapshots'], static function (array $ticket_snapshot): bool {
		return !empty($ticket_snapshot['customer_facing']) && !empty($ticket_snapshot['sales_state']['config_is_open']);
	}));
	$sellable_public_tickets = array_values(array_filter($expected_public_tickets, 'vms_ticket_integrity_ticket_is_sellable'));
	if (!empty($expected_public_tickets) && empty($sellable_public_tickets) && empty($context['cancelled']) && !empty($context['ticketing_enabled'])) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'no_public_ticket_path',
				'red',
				'render',
				__('No public ticket path is currently usable', 'vms'),
				__('At least one customer-facing ticket appears intended to be on sale now, but the linked live products do not currently present a usable public purchase path.', 'vms')
			)
		);
	}

	$false_sold_out_ticket_labels = array();
	foreach ($expected_public_tickets as $ticket_snapshot) {
		$product = is_array($ticket_snapshot['product'] ?? null) ? $ticket_snapshot['product'] : array();
		$sales_state = is_array($ticket_snapshot['sales_state'] ?? null) ? $ticket_snapshot['sales_state'] : array();
		if (($product['post_type'] ?? '') !== 'product' || empty($product['is_public'])) {
			continue;
		}

		$out_of_stock = (($product['is_in_stock'] ?? null) === false || (string) ($product['stock_status'] ?? '') === 'outofstock');
		$product_closed = !empty($sales_state['product_window_present']) && empty($sales_state['product_is_open']);
		if ($out_of_stock || $product_closed) {
			$false_sold_out_ticket_labels[] = (string) ($ticket_snapshot['title'] ?? __('Unnamed ticket', 'vms'));
		}
	}

	if (!empty($false_sold_out_ticket_labels)) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'possible_false_sold_out',
				'red',
				'render',
				__('Possible false sold-out state detected', 'vms'),
				__('One or more tickets still read as sellable from the Event Plan side, but the live product state currently closes or sells them out. That can surface to customers as a false sold-out state.', 'vms'),
				array('tickets' => array_values(array_unique($false_sold_out_ticket_labels)))
			)
		);
	}

	$qualifying_tickets = array_values(array_filter((array) $context['ticket_snapshots'], static function (array $ticket_snapshot): bool {
		return !empty($ticket_snapshot['counts_toward_unlock']) && !empty($ticket_snapshot['customer_facing']);
	}));
	$qualifying_live_tickets = array_values(array_filter($qualifying_tickets, 'vms_ticket_integrity_ticket_is_sellable'));
	$expected_addon_render = !empty($context['entitlement_snapshots'])
		&& !empty($qualifying_live_tickets)
		&& empty($context['cancelled'])
		&& !empty($context['ticketing_enabled'])
		&& (!function_exists('vms_ticketing_v2_ga_is_on_sale_now') || vms_ticketing_v2_ga_is_on_sale_now((array) ($context['cfg'] ?? array())));

	foreach ((array) $context['entitlement_snapshots'] as $ent_snapshot) {
		$entitlement_id = sanitize_key((string) ($ent_snapshot['entitlement_id'] ?? ''));
		$label = (string) ($ent_snapshot['label'] ?? $entitlement_id);
		$mapping_state = (string) ($ent_snapshot['mapping_state'] ?? 'unmapped');
		$product = is_array($ent_snapshot['product'] ?? null) ? $ent_snapshot['product'] : array();
		$eligibility = is_array($ent_snapshot['eligibility'] ?? null) ? $ent_snapshot['eligibility'] : array();
		$requires_qualifying = empty($eligibility['allow_without_ga']) && ((int) ($eligibility['min_ga_per_unit'] ?? 0) > 0 || sanitize_key((string) ($eligibility['pool_key'] ?? '')) !== '');

		if (in_array($mapping_state, array('missing', 'trash', 'not_product'), true)) {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'entitlement_mapping_problem_' . $entitlement_id,
					'yellow',
					'addons',
					sprintf(__('Add-on "%s" points to an invalid product object', 'vms'), $label),
					__('This add-on is enabled in config, but its linked Woo product is missing, trashed, or not a product. Core ticket sales may still work, but the add-on path is not healthy.', 'vms')
				)
			);
		}

		if ($requires_qualifying && empty($qualifying_tickets)) {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'entitlement_no_parent_' . $entitlement_id,
					'red',
					'addons',
					sprintf(__('Add-on "%s" has no qualifying ticket path', 'vms'), $label),
					__('This add-on still requires qualifying tickets, but the current Event Plan does not have any enabled qualifying ticket rows that can unlock it.', 'vms')
				)
			);
		} elseif ($requires_qualifying && !empty($product['is_public']) && empty($qualifying_live_tickets)) {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'entitlement_parent_closed_' . $entitlement_id,
					'red',
					'addons',
					sprintf(__('Add-on "%s" has no live qualifying ticket path', 'vms'), $label),
					__('This add-on is still live, but the qualifying customer-facing ticket path currently is not sellable. That can leave the add-on path effectively broken.', 'vms')
				)
			);
		}
	}

	if ($expected_addon_render && function_exists('vms_ticketing_v2_render_entitlements_block')) {
		$rendered_addons = trim((string) vms_ticketing_v2_render_entitlements_block((int) $context['tec_event_id'], (int) $context['plan_id']));
		if ($rendered_addons === '') {
			vms_ticket_integrity_add_issue(
				$issues,
				vms_ticket_integrity_issue(
					'entitlements_render_empty',
					'yellow',
					'render',
					__('Expected add-on block did not render', 'vms'),
					__('Enabled add-ons and a qualifying ticket path exist, but the server-side add-on render returned empty output during the integrity check.', 'vms')
				)
			);
		}
	}

	$origin_classification = '';
	$origin_reasons = array();
	$mutation_diagnostics = array();
	$inventory_diagnostics = array();
	$repair_diagnostics = function_exists('vms_ticket_integrity_get_repair_report')
		? vms_ticket_integrity_get_repair_report($plan_id)
		: array();
	if (function_exists('vms_ticket_mutation_audit_build_snapshot')) {
		$audit_snapshot = vms_ticket_mutation_audit_build_snapshot($plan_id);
		$origin = is_array($audit_snapshot['origin'] ?? null) ? $audit_snapshot['origin'] : array();
		$origin_classification = sanitize_key((string) ($origin['classification'] ?? ''));
		$origin_reasons = array_values(array_filter(array_map('strval', (array) ($origin['reasons'] ?? array()))));

		if (function_exists('vms_ticket_mutation_audit_build_event_diagnostics')) {
			$mutation_diagnostics = vms_ticket_mutation_audit_build_event_diagnostics(
				$plan_id,
				array(
					'snapshot' => $audit_snapshot,
					'issues' => $issues,
				)
			);
		}
	}

	if (function_exists('vms_ticket_inventory_forensics_build_event_diagnostics')) {
		$inventory_diagnostics = vms_ticket_inventory_forensics_build_event_diagnostics(
			$plan_id,
			array(
				'context' => $context,
				'ticket_snapshots' => $context['ticket_snapshots'],
				'entitlement_snapshots' => $context['entitlement_snapshots'],
				'issues' => $issues,
				'origin_classification' => $origin_classification,
				'repair_report' => $repair_diagnostics,
			)
		);
	}

	if (!empty($inventory_diagnostics['zero_available_mismatch'])) {
		$details = __('TEC is calculating zero available tickets even though the event still has remaining capacity.', 'vms');
		$cause_label = trim((string) ($inventory_diagnostics['suspected_cause_label'] ?? ''));
		if ($cause_label !== '' && $cause_label !== vms_ticket_inventory_forensics_cause_label('healthy')) {
			$details .= ' ' . sprintf(__('Likely pattern: %s.', 'vms'), $cause_label);
		}

		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'tec_zero_available_despite_capacity',
				'red',
				'inventory',
				__('TEC is resolving zero available tickets despite remaining event capacity', 'vms'),
				$details
			)
		);
	}

	if (!empty($inventory_diagnostics['woo_primary_mismatch']) && empty($inventory_diagnostics['zero_available_mismatch'])) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'woo_inventory_mismatch',
				'red',
				'inventory',
				__('Woo inventory disagrees with the intended sellability state', 'vms'),
				__('At least one mapped ticket or add-on still disagrees with the intended Woo sellability state. Review the Woo-first verification rows before running another repair.', 'vms')
			)
		);
	}

	if (!empty($inventory_diagnostics['addon_divergence'])) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'inventory_role_divergence',
				'yellow',
				'inventory',
				__('Add-ons and admission tickets are diverging in a suspicious way', 'vms'),
				__('Admission tickets are resolving to zero while one or more add-ons still appear available. That usually points to role-specific repair or inventory logic drift.', 'vms')
			)
		);
	}

	if (!empty($inventory_diagnostics['woo_recorruption_detected'])) {
		$details = trim((string) ($inventory_diagnostics['woo_recorruption']['message'] ?? __('Woo inventory was repaired into a sellable state, but a later write closed it again.', 'vms')));
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'woo_recorruption_detected',
				'red',
				'inventory',
				__('Woo re-corruption detected after repair', 'vms'),
				$details
			)
		);
	}

	if (!empty($inventory_diagnostics['tec_followup_required'])) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'tec_followup_required',
				'yellow',
				'inventory',
				__('Woo now matches intent, but TEC still disagrees', 'vms'),
				__('At least one mapped product now matches the intended Woo sellability state, but TEC availability still resolves differently. Review the Woo/TEC verification rows before rewriting Woo again.', 'vms')
			)
		);
	}

	$repeated_inventory_drift = is_array($inventory_diagnostics['repeated_inventory_drift'] ?? null) ? $inventory_diagnostics['repeated_inventory_drift'] : array();
	if (!empty($repeated_inventory_drift['flagged'])) {
		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'repeated_inventory_drift_detected',
				'red',
				'inventory',
				__('Repeated inventory drift detected', 'vms'),
				trim((string) ($repeated_inventory_drift['message'] ?? __('Inventory drift returned after a prior rebuild.', 'vms')))
			)
		);
	}

	$repeated_drift = is_array($mutation_diagnostics['repeated_drift'] ?? null) ? $mutation_diagnostics['repeated_drift'] : array();
	if (!empty($repeated_drift['flagged'])) {
		$severity = sanitize_key((string) ($repeated_drift['severity'] ?? 'yellow'));
		$message = trim((string) ($repeated_drift['message'] ?? ''));
		if ($message === '') {
			$message = __('This event has developed the same mapping problem again after a prior repair. Another process may still be rewriting ticket relationships.', 'vms');
		}

		vms_ticket_integrity_add_issue(
			$issues,
			vms_ticket_integrity_issue(
				'repeated_drift_detected',
				$severity,
				'mapping',
				__('Repeated drift detected', 'vms'),
				$message
			)
		);
		$mutation_diagnostics['public_path_healthy'] = 0;
	}

	if (vms_ticket_integrity_should_compact_scan_payload($args)) {
		$mutation_diagnostics = vms_ticket_integrity_compact_mutation_diagnostics($mutation_diagnostics);
		$inventory_diagnostics = vms_ticket_integrity_compact_inventory_diagnostics($inventory_diagnostics);
		$repair_diagnostics = vms_ticket_integrity_compact_repair_diagnostics($repair_diagnostics);
	}

	$issues = vms_ticket_integrity_sort_issues(array_values($issues));

	return array(
		'plan_id' => absint($context['plan_id'] ?? 0),
		'tec_event_id' => absint($context['tec_event_id'] ?? 0),
		'event_title' => (string) ($context['event_title'] ?? ''),
		'event_timestamp' => absint($context['event_timestamp'] ?? 0),
		'event_date_local' => (string) ($context['event_date_local'] ?? ''),
		'event_url' => (string) ($context['event_url'] ?? ''),
		'edit_plan_url' => (string) ($context['edit_plan_url'] ?? ''),
		'edit_event_url' => (string) ($context['edit_event_url'] ?? ''),
		'plan_status' => (string) ($context['plan_status'] ?? ''),
		'event_status' => (string) ($context['event_status'] ?? ''),
		'mode' => (string) ($context['mode'] ?? ''),
		'ticketing_enabled' => !empty($context['ticketing_enabled']) ? 1 : 0,
		'has_saved_config' => !empty($context['has_saved_config']) ? 1 : 0,
		'status' => vms_ticket_integrity_status_from_issues($issues),
		'issue_summary' => vms_ticket_integrity_issue_summary($issues),
		'issues' => $issues,
		'product_provenance' => array_values((array) ($context['attached_products'] ?? array())),
		'ticket_snapshots' => array_values((array) ($context['ticket_snapshots'] ?? array())),
		'entitlement_snapshots' => array_values((array) ($context['entitlement_snapshots'] ?? array())),
		'origin_classification' => $origin_classification,
		'origin_reasons' => $origin_reasons,
		'mutation_diagnostics' => $mutation_diagnostics,
		'inventory_diagnostics' => $inventory_diagnostics,
		'repair_diagnostics' => $repair_diagnostics,
		'scanned_at_gmt' => $now,
	);
}
