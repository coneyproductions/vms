<?php
defined('ABSPATH') || exit;

/**
 * Season Dates admin UI + POST handler
 * Location: vms/includes/admin/season-dates.php
 * UI rev: 2026-01-22f
 */

add_action('admin_init', 'bvmgr_sd_maybe_handle_post', 1);

function bvmgr_sd_require_engine(): void
{
	$vms_root = dirname(__DIR__, 2);
	$season_file = $vms_root . '/includes/schedule/season-dates.php';
	if (file_exists($season_file)) {
		require_once $season_file;
	}
}

function bvmgr_sd_redirect(string $url): void
{
	if (headers_sent($file, $line)) {
		$GLOBALS['bvmgr_sd_headers_sent'] = [$file, (int)$line];
		$_GET['vms_error'] = 'headers_sent';
		return;
	}
	wp_safe_redirect($url);
	exit;
}

if (!function_exists('bvmgr_admin_season_rules_updated_at_get')) {
	function bvmgr_admin_season_rules_updated_at_get(int $venue_id): int
	{
		$all = get_option('vms_season_rules_updated_at_v1', []);
		if (!is_array($all)) {
			$all = [];
		}
		return isset($all[(string)$venue_id]) ? (int) $all[(string)$venue_id] : 0;
	}
}

if (!function_exists('bvmgr_admin_season_rules_updated_at_touch')) {
	function bvmgr_admin_season_rules_updated_at_touch(int $venue_id): void
	{
		$all = get_option('vms_season_rules_updated_at_v1', []);
		if (!is_array($all)) {
			$all = [];
		}
		$all[(string)$venue_id] = (int) current_time('timestamp');
		update_option('vms_season_rules_updated_at_v1', $all, false);
	}
}

function bvmgr_sd_base_url(string $page_slug, int $venue_id): string
{
	$url = add_query_arg(
		[
			'page'     => $page_slug,
			'venue_id' => $venue_id,
		],
		admin_url('admin.php')
	);
	return remove_query_arg(['vms_notice', 'vms_error'], $url);
}

if (!function_exists('bvmgr_sd_query_arg')) {
	function bvmgr_sd_query_arg(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Season Dates query state only affects admin rendering and redirects.
		if (!isset($_GET[$key])) return '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only Season Dates query state is unslashed here and sanitized or cast by the caller.
		return (string) wp_unslash($_GET[$key]);
	}
}

function bvmgr_sd_norm_mmdd(string $val): string
{
	$val = trim((string)$val);
	if ($val === '') return '';
	if (preg_match('/^\s*(\d{1,2})\D+(\d{1,2})\s*$/', $val, $m)) {
		$mm = (int)$m[1];
		$dd = (int)$m[2];
		if ($mm >= 1 && $mm <= 12 && $dd >= 1 && $dd <= 31) {
			return sprintf('%02d-%02d', $mm, $dd);
		}
	}
	return '';
}

function bvmgr_sd_days_mask_from_values($values): ?int
{
	if (empty($values) || !is_array($values)) return null;

	$mask = 0;
	foreach ($values as $raw) {
		if (!is_scalar($raw)) continue;
		$dow = (int)$raw;
		if ($dow < 0 || $dow > 6) continue;
		$mask |= (1 << $dow);
	}
	return ($mask > 0 && $mask <= 127) ? $mask : null;
}

function bvmgr_sd_days_from_mask(int $mask): array
{
	$mask = max(0, min(127, (int)$mask));
	$out = [];
	for ($i = 0; $i <= 6; $i++) {
		if ($mask & (1 << $i)) $out[] = $i;
	}
	return $out;
}

function bvmgr_sd_rule_key(array $r): string
{
	$type = (string)($r['type'] ?? '');
	if ($type === 'open_window') {
		$start = (string)($r['start_mmdd'] ?? '');
		$end   = (string)($r['end_mmdd'] ?? '');
		$dw    = isset($r['days_w']) ? (int)$r['days_w'] : 0; // 0 means all days
		$dw    = max(0, min(127, $dw));
		return 'ow|' . $start . '|' . $end . '|' . (string)$dw;
	}
	if ($type === 'blackout_date') {
		$date = (string)($r['date_ymd'] ?? '');
		return 'bo|' . $date;
	}
	if ($type === 'blackout_range') {
		$start = (string)($r['start_ymd'] ?? '');
		$end   = (string)($r['end_ymd'] ?? '');
		return 'bor|' . $start . '|' . $end;
	}
	return 'x|';
}

function bvmgr_sd_rules_hash(array $rules): string
{
	$rows = [];
	foreach ($rules as $r) {
		if (!is_array($r)) continue;

		// Use your existing canonical key (ignores id + note, focuses on rule meaning).
		$key = bvmgr_sd_rule_key($r);
		if ($key === 'x|') continue;

		$enabled = !empty($r['enabled']) ? 1 : 0;

		// Enabled affects generated dates, so include it.
		$rows[] = $key . '|en:' . (string)$enabled;
	}

	sort($rows);
	return md5(wp_json_encode($rows));
}

function bvmgr_sd_has_duplicates(array $rules): bool
{
	$seen = [];
	foreach ($rules as $r) {
		if (!is_array($r)) continue;
		$key = bvmgr_sd_rule_key($r);
		if ($key === 'x|') continue;
		if (isset($seen[$key])) return true;
		$seen[$key] = true;
	}
	return false;
}

function bvmgr_sd_notice_text(string $code): string
{
	$map = [
		'rules_saved'        => __('Rules saved. Active dates were not regenerated.', 'backstage-venue-manager'),
		'rule_deleted'       => __('Rule deleted. Active dates were not regenerated.', 'backstage-venue-manager'),
		'open_window_added'  => __('Open window added. Active dates were not regenerated.', 'backstage-venue-manager'),
		'open_window_exists' => __('That open window already exists for this venue (no duplicate added).', 'backstage-venue-manager'),
		'blackout_added'     => __('Blackout date added. Active dates were not regenerated.', 'backstage-venue-manager'),
		'blackout_exists'    => __('That blackout date already exists for this venue (no duplicate added).', 'backstage-venue-manager'),
		'blackout_range_added'  => __('Blackout range added. Active dates were not regenerated.', 'backstage-venue-manager'),
		'blackout_range_exists' => __('That blackout range already exists for this venue (no duplicate added).', 'backstage-venue-manager'),
		'generated'          => __('Active dates generated and saved for this venue.', 'backstage-venue-manager'),
		'cleared'            => __('Generated dates cleared for this venue.', 'backstage-venue-manager'),
	];
	return $map[$code] ?? __('Saved.', 'backstage-venue-manager');
}

function bvmgr_sd_error_text(string $code): string
{
	$map = [
		'bad_nonce'        => __('Security check failed. Please try again.', 'backstage-venue-manager'),
		'confirm_required' => __('Confirmation checkbox required for this action.', 'backstage-venue-manager'),
		'add_rule_failed'  => __('Rule could not be added (invalid inputs).', 'backstage-venue-manager'),
		'save_failed'      => __('Rules could not be saved.', 'backstage-venue-manager'),
		'duplicates_found' => __('Duplicate rules detected. Remove duplicates and try again.', 'backstage-venue-manager'),
		'missing_core'     => __('Missing core season functions. Check includes.', 'backstage-venue-manager'),
		'unknown_action'   => __('Unknown action.', 'backstage-venue-manager'),
		'headers_sent'     => __('Redirect failed because output started early (headers already sent).', 'backstage-venue-manager'),
	];
	return $map[$code] ?? __('An error occurred.', 'backstage-venue-manager');
}

function bvmgr_sd_maybe_handle_post(): void
{
	if (!function_exists('bvmgr_sd_is_exact_post_request')) {
		function bvmgr_sd_is_exact_post_request(): bool
		{
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Exact POST gate intentionally accepts only the literal REQUEST_METHOD value "POST".
			$request_method = $_SERVER['REQUEST_METHOD'] ?? null;
			if (!is_scalar($request_method)) {
				return false;
			}

			return 'POST' === wp_unslash($request_method);
		}
	}

	if (!bvmgr_sd_is_exact_post_request()) return;
	if (empty($_POST['bvmgr_season_dates_nonce']) || empty($_POST['vms_action'])) return;

	$page_slug = sanitize_key(bvmgr_sd_query_arg('page'));
	if ($page_slug === '') return;
	$cap = apply_filters('vms_admin_capability', 'manage_options');
	if (!current_user_can($cap)) return;

	bvmgr_sd_require_engine();

	if (!function_exists('bvmgr_sch_season_get_rules') || !function_exists('bvmgr_sch_season_save_rules')) {
		$venue_id = isset($_POST['venue_id']) ? absint($_POST['venue_id']) : 0;
		$redirect = bvmgr_sd_base_url($page_slug, $venue_id);
		bvmgr_sd_redirect(add_query_arg('vms_error', 'missing_core', $redirect));
		return;
	}

	$post = wp_unslash($_POST);
	$venue_id = absint($post['venue_id'] ?? ($_GET['venue_id'] ?? 0));
	$redirect = bvmgr_sd_base_url($page_slug, $venue_id);

	$nonce = (isset($post['bvmgr_season_dates_nonce']) && !is_array($post['bvmgr_season_dates_nonce']))
		? sanitize_text_field((string) $post['bvmgr_season_dates_nonce'])
		: '';
	if (!$venue_id || !wp_verify_nonce($nonce, bvmgr_nonce_action_for_value($nonce, 'bvmgr_season_dates_' . $venue_id))) {
		bvmgr_sd_redirect(add_query_arg('vms_error', 'bad_nonce', $redirect));
		return;
	}

	$action = sanitize_key((string)($post['vms_action'] ?? ''));
	if ($action === '') {
		bvmgr_sd_redirect(add_query_arg('vms_error', 'unknown_action', $redirect));
		return;
	}

	// SAVE RULES (also handles delete via delete_rule_id)
	if ($action === 'save_rules') {

		$delete_id = isset($post['delete_rule_id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$post['delete_rule_id']) : '';
		if ($delete_id !== '') {
			$rules = bvmgr_sch_season_get_rules($venue_id);
			$rules = is_array($rules) ? $rules : [];

			$kept = [];
			foreach ($rules as $r) {
				if (!is_array($r)) continue;
				if ((string)($r['id'] ?? '') === $delete_id) continue;
				$kept[] = $r;
			}

			$res = bvmgr_sch_season_save_rules($venue_id, $kept);
			if (is_wp_error($res) || $res === false) {
				bvmgr_sd_redirect(add_query_arg('vms_error', 'save_failed', $redirect));
				return;
			}
			bvmgr_admin_season_rules_updated_at_touch($venue_id);

			bvmgr_sd_redirect(add_query_arg('vms_notice', 'rule_deleted', $redirect));
			return;
		}

		$posted = $post['rules'] ?? [];
		$list = [];

		if (is_array($posted)) {
			foreach ($posted as $id => $r) {
				if (!is_array($r)) continue;

				$rid  = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($r['id'] ?? $id));
				$type = sanitize_key((string)($r['type'] ?? ''));

				$item = [
					'id'      => $rid,
					'type'    => $type,
					'enabled' => !empty($r['enabled']),
					'note'    => isset($r['note']) ? sanitize_text_field((string)$r['note']) : '',
				];

				if ($type === 'open_window') {
					$start = bvmgr_sd_norm_mmdd((string)($r['start_mmdd'] ?? ''));
					$end   = bvmgr_sd_norm_mmdd((string)($r['end_mmdd'] ?? ''));

					if ($start === '' || $end === '') {
						bvmgr_sd_redirect(add_query_arg('vms_error', 'save_failed', $redirect));
						return;
					}

					$item['start_mmdd'] = $start;
					$item['end_mmdd']   = $end;

					$mask = bvmgr_sd_days_mask_from_values($r['days_w_days'] ?? []);
					if ($mask !== null) $item['days_w'] = (int)$mask; // omit if blank
				}

				if ($type === 'blackout_date') {
					$date = isset($r['date_ymd']) ? sanitize_text_field((string)$r['date_ymd']) : '';
					if ($date === '') {
						bvmgr_sd_redirect(add_query_arg('vms_error', 'save_failed', $redirect));
						return;
					}
					$item['date_ymd'] = $date;
				}

				$list[] = $item;
			}
		}

		if (bvmgr_sd_has_duplicates($list)) {
			bvmgr_sd_redirect(add_query_arg('vms_error', 'duplicates_found', $redirect));
			return;
		}

		$res = bvmgr_sch_season_save_rules($venue_id, $list);
		if (is_wp_error($res) || $res === false) {
			bvmgr_sd_redirect(add_query_arg('vms_error', 'save_failed', $redirect));
			return;
		}
		bvmgr_admin_season_rules_updated_at_touch($venue_id);
		bvmgr_sd_redirect(add_query_arg('vms_notice', 'rules_saved', $redirect));
		return;
	}

	// ADD OPEN WINDOW
	if ($action === 'add_open_window') {

		$start = bvmgr_sd_norm_mmdd((string)($post['new_start_mmdd'] ?? ''));
		$end   = bvmgr_sd_norm_mmdd((string)($post['new_end_mmdd'] ?? ''));
		$note  = isset($post['new_note']) ? sanitize_text_field((string)$post['new_note']) : '';

		if ($start === '' || $end === '') {
			bvmgr_sd_redirect(add_query_arg('vms_error', 'add_rule_failed', $redirect));
			return;
		}

		$mask = bvmgr_sd_days_mask_from_values($post['new_days_w_days'] ?? []);

		$rules = bvmgr_sch_season_get_rules($venue_id);
		$rules = is_array($rules) ? $rules : [];

		$new_rule = [
			'id'         => 'r_' . substr(wp_generate_uuid4(), 0, 8),
			'type'       => 'open_window',
			'enabled'    => true,
			'start_mmdd' => $start,
			'end_mmdd'   => $end,
			'note'       => $note,
		];
		if ($mask !== null) $new_rule['days_w'] = (int)$mask;

		$new_key = bvmgr_sd_rule_key($new_rule);
		foreach ($rules as $r) {
			if (!is_array($r)) continue;
			if (($r['type'] ?? '') !== 'open_window') continue;
			if (bvmgr_sd_rule_key($r) === $new_key) {
				bvmgr_sd_redirect(add_query_arg('vms_notice', 'open_window_exists', $redirect));
				return;
			}
		}

		$rules[] = $new_rule;

		$res = bvmgr_sch_season_save_rules($venue_id, $rules);
		if (is_wp_error($res) || $res === false) {
			bvmgr_sd_redirect(add_query_arg('vms_error', 'add_rule_failed', $redirect));
			return;
		}
		bvmgr_admin_season_rules_updated_at_touch($venue_id);
		bvmgr_sd_redirect(add_query_arg('vms_notice', 'open_window_added', $redirect));
		return;
	}

	// ADD BLACKOUT
	if ($action === 'add_blackout') {

		$date = isset($post['new_blackout_ymd']) ? sanitize_text_field((string)$post['new_blackout_ymd']) : '';
		$note = isset($post['new_note']) ? sanitize_text_field((string)$post['new_note']) : '';

		if ($date === '') {
			bvmgr_sd_redirect(add_query_arg('vms_error', 'add_rule_failed', $redirect));
			return;
		}

		$rules = bvmgr_sch_season_get_rules($venue_id);
		$rules = is_array($rules) ? $rules : [];

		foreach ($rules as $r) {
			if (!is_array($r)) continue;
			if (($r['type'] ?? '') === 'blackout_date' && (string)($r['date_ymd'] ?? '') === $date) {
				bvmgr_sd_redirect(add_query_arg('vms_notice', 'blackout_exists', $redirect));
				return;
			}
		}

		$rules[] = [
			'id'       => 'b_' . substr(wp_generate_uuid4(), 0, 8),
			'type'     => 'blackout_date',
			'enabled'  => true,
			'date_ymd' => $date,
			'note'     => $note,
		];

		$res = bvmgr_sch_season_save_rules($venue_id, $rules);
		if (is_wp_error($res) || $res === false) {
			bvmgr_sd_redirect(add_query_arg('vms_error', 'add_rule_failed', $redirect));
			return;
		}
		bvmgr_admin_season_rules_updated_at_touch($venue_id);
		bvmgr_sd_redirect(add_query_arg('vms_notice', 'blackout_added', $redirect));
		return;
	}

	// ADD BLACKOUT RANGE
	if ($action === 'add_blackout_range') {

		$start = isset($post['new_blackout_start_ymd']) ? sanitize_text_field((string)$post['new_blackout_start_ymd']) : '';
		$end   = isset($post['new_blackout_end_ymd']) ? sanitize_text_field((string)$post['new_blackout_end_ymd']) : '';
		$note  = isset($post['new_note']) ? sanitize_text_field((string)$post['new_note']) : '';

		if ($start === '' || $end === '') {
			bvmgr_sd_redirect(add_query_arg('vms_error', 'add_rule_failed', $redirect));
			return;
		}

		$rules = bvmgr_sch_season_get_rules($venue_id);
		$rules = is_array($rules) ? $rules : [];

		// Normalize order
		if (strcmp($start, $end) > 0) {
			$tmp = $start;
			$start = $end;
			$end = $tmp;
		}

		foreach ($rules as $r) {
			if (!is_array($r)) continue;
			if (($r['type'] ?? '') === 'blackout_range'
				&& (string)($r['start_ymd'] ?? '') === $start
				&& (string)($r['end_ymd'] ?? '') === $end) {
				bvmgr_sd_redirect(add_query_arg('vms_notice', 'blackout_range_exists', $redirect));
				return;
			}
			// A single-day range equivalent already exists.
			if (($r['type'] ?? '') === 'blackout_date' && (string)($r['date_ymd'] ?? '') === $start && $start === $end) {
				bvmgr_sd_redirect(add_query_arg('vms_notice', 'blackout_exists', $redirect));
				return;
			}
		}

		$rules[] = [
			'id'        => 'br_' . substr(wp_generate_uuid4(), 0, 8),
			'type'      => 'blackout_range',
			'enabled'   => true,
			'start_ymd' => $start,
			'end_ymd'   => $end,
			'note'      => $note,
		];

		$res = bvmgr_sch_season_save_rules($venue_id, $rules);
		if (is_wp_error($res) || $res === false) {
			bvmgr_sd_redirect(add_query_arg('vms_error', 'add_rule_failed', $redirect));
			return;
		}
		bvmgr_admin_season_rules_updated_at_touch($venue_id);
		bvmgr_sd_redirect(add_query_arg('vms_notice', 'blackout_range_added', $redirect));
		return;
	}

	// GENERATE ACTIVE
	if ($action === 'generate_active') {
		$from    = isset($post['gen_from_ymd']) ? sanitize_text_field((string)$post['gen_from_ymd']) : '';
		$to      = isset($post['gen_to_ymd']) ? sanitize_text_field((string)$post['gen_to_ymd']) : '';
		$confirm = !empty($post['gen_confirm_replace']);

		if (!$confirm) {
			bvmgr_sd_redirect(add_query_arg('vms_error', 'confirm_required', $redirect));
			return;
		}

		if (!function_exists('bvmgr_sch_season_generate_active_dates') || !function_exists('bvmgr_sch_season_set_active_payload')) {
			bvmgr_sd_redirect(add_query_arg('vms_error', 'missing_core', $redirect));
			return;
		}

		$payload = bvmgr_sch_season_generate_active_dates($venue_id, $from, $to);
		if (is_array($payload) && !empty($payload['error'])) {
			bvmgr_sd_redirect(add_query_arg('vms_error', sanitize_key((string)$payload['error']), $redirect));
			return;
		}

		// Ensure the payload has a generation timestamp.
		if (!isset($payload['generated_at']) || (int)$payload['generated_at'] <= 0) {
			$payload['generated_at'] = (int) current_time('timestamp');
		}

		// Store the fingerprint of the rules used to generate this payload.
		$current_rules = bvmgr_sch_season_get_rules($venue_id);
		$current_rules = is_array($current_rules) ? $current_rules : [];
		$payload['rules_hash'] = bvmgr_sd_rules_hash($current_rules);

		bvmgr_sch_season_set_active_payload($venue_id, $payload);
		bvmgr_sd_redirect(add_query_arg('vms_notice', 'generated', $redirect));
		return;
	}

	// CLEAR GENERATED
	if ($action === 'clear_generated') {
		$confirm = !empty($post['clear_confirm']);
		if (!$confirm) {
			bvmgr_sd_redirect(add_query_arg('vms_error', 'confirm_required', $redirect));
			return;
		}

		if (function_exists('bvmgr_sch_season_clear_active_dates')) {
			bvmgr_sch_season_clear_active_dates($venue_id);
		}

		bvmgr_sd_redirect(add_query_arg('vms_notice', 'cleared', $redirect));
		return;
	}

	// vms_sd_redirect(add_query_arg('vms_error', 'unknown_action', $redirect));
}

function bvmgr_render_season_dates_page(): void
{
	$cap = apply_filters('vms_admin_capability', 'manage_options');
	if (!current_user_can($cap)) {
		wp_die(esc_html__('You do not have permission to access this page.', 'backstage-venue-manager'));
	}

	bvmgr_sd_require_engine();

	$page_slug = sanitize_key(bvmgr_sd_query_arg('page'));
	$base_url  = admin_url('admin.php?page=' . $page_slug);

	$venue_ids = function_exists('bvmgr_sch_get_all_venue_ids') ? bvmgr_sch_get_all_venue_ids() : [];
	$venue_ids = is_array($venue_ids) ? array_values(array_filter(array_map('absint', $venue_ids))) : [];

	$selected_venue_id = absint(bvmgr_sd_query_arg('venue_id'));
	if ($selected_venue_id <= 0 && !empty($venue_ids)) {
		$selected_venue_id = (int)$venue_ids[0];
	}

	$rules  = function_exists('bvmgr_sch_season_get_rules') ? bvmgr_sch_season_get_rules($selected_venue_id) : [];
	$active = function_exists('bvmgr_sch_season_get_active_payload') ? bvmgr_sch_season_get_active_payload($selected_venue_id) : [];

	$rules  = is_array($rules) ? $rules : [];
	$active = is_array($active) ? $active : [];

	$rules_updated_at = bvmgr_admin_season_rules_updated_at_get($selected_venue_id);

	// Prefer a fingerprint comparison when available (most reliable).
	$active_rules_hash   = isset($active['rules_hash']) ? (string) $active['rules_hash'] : '';
	$current_rules_hash  = bvmgr_sd_rules_hash($rules);

	$generated_at = 0;
	foreach (['generated_at', 'generated_ts', 'last_generated_at'] as $k) {
		if (isset($active[$k])) {
			$generated_at = (int) $active[$k];
			break;
		}
	}

	$has_rules = !empty($rules);
	$has_generated_dates = !empty($dates_map);

	// Stale logic:
	$active_is_stale = false;

	// If we have a rules hash in the payload, it is authoritative.
	if ($active_rules_hash !== '') {
		$active_is_stale = ($active_rules_hash !== $current_rules_hash);
	} elseif ($rules_updated_at > 0) {
		// Fallback to timestamps only if no hash exists (older payloads).
		$active_is_stale = ($generated_at === 0 || $rules_updated_at > $generated_at);
	}


	// Generated dates payload compatibility
	$active_dates = [];
	if (isset($active['active_dates']) && is_array($active['active_dates'])) {
		$active_dates = array_values($active['active_dates']);
	} elseif (isset($active['dates']) && is_array($active['dates'])) {
		$active_dates = array_values($active['dates']);
	}

	$dates_map = [];
	if (isset($active['dates_map']) && is_array($active['dates_map'])) {
		$dates_map = $active['dates_map'];
	} elseif (!empty($active_dates)) {
		foreach ($active_dates as $ymd) {
			if (is_string($ymd) && $ymd !== '') {
				$dates_map[$ymd] = 1;
			}
		}
	}

	$dates_count = isset($active['count']) ? (int) $active['count'] : count($dates_map);
	$has_generated_dates = ($dates_count > 0);

	$names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

	$notice = sanitize_key(bvmgr_sd_query_arg('vms_notice'));
	$error  = sanitize_key(bvmgr_sd_query_arg('vms_error'));

	echo '<div class="wrap vms-season-dates-admin">';
	echo '<h1>' . esc_html__('Season Dates', 'backstage-venue-manager') . '</h1>';

	if ($error === 'headers_sent' && !empty($GLOBALS['bvmgr_sd_headers_sent'])) {
		[$f, $ln] = $GLOBALS['bvmgr_sd_headers_sent'];
		echo '<div class="notice notice-error is-dismissible"><p>';
		echo esc_html(bvmgr_sd_error_text('headers_sent')) . ' <code>' . esc_html($f . ':' . (string)$ln) . '</code>';
		echo '</p></div>';
	}

	// Notices
	if ($notice) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(bvmgr_sd_notice_text($notice)) . '</p></div>';
	}
	if ($error && $error !== 'headers_sent') {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(bvmgr_sd_error_text($error)) . '</p></div>';
	}

	if (empty($venue_ids)) {
		echo '<div class="notice notice-warning"><p>' . esc_html__('No venues found. Add a venue first, then return here.', 'backstage-venue-manager') . '</p></div>';
		echo '</div>';
		return;
	}

	if ($has_rules && !$has_generated_dates) {
		echo '<div class="notice notice-warning"><p><strong>'
			. esc_html__('Heads up:', 'backstage-venue-manager')
			. '</strong> '
			. esc_html__('You have season rules, but no active dates have been generated yet. Use Generate Active Dates below to apply your rules.', 'backstage-venue-manager')
			. '</p></div>';
	}

	if ($active_is_stale) {
		echo '<div class="notice notice-warning"><p><strong>'
			. esc_html__('Heads up:', 'backstage-venue-manager')
			. '</strong> '
			. esc_html__('Season rules have changed since active dates were last generated. Regenerate active dates to apply your updates.', 'backstage-venue-manager')
			. '</p></div>';
	}


	// Venue selector
	echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
	echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '">';
	echo '<table class="form-table" role="presentation"><tr><th scope="row">';
	echo '<label for="venue_id">' . esc_html__('Venue', 'backstage-venue-manager') . '</label></th><td>';
	echo '<select name="venue_id" id="venue_id" onchange="this.form.submit()">';
	foreach ($venue_ids as $vid) {
		$title = get_the_title($vid);
		if (!$title) $title = sprintf('Venue #%d', (int)$vid);
		echo '<option value="' . esc_attr((string)$vid) . '"' . selected($selected_venue_id, $vid, false) . '>' . esc_html($title) . '</option>';
	}
	echo '</select></td></tr></table></form>';

	echo '<p class="vms-sd-stats">';
	echo '<strong>' . esc_html__('Season rules:', 'backstage-venue-manager') . '</strong> ' . esc_html((string)count($rules));
	echo ' &nbsp;|&nbsp; ';
	echo '<strong>' . esc_html__('Active dates generated:', 'backstage-venue-manager') . '</strong> ' . esc_html((string)$dates_count);
	echo '</p>';

	$rules_action_url = remove_query_arg(['vms_notice', 'vms_error'], add_query_arg(['venue_id' => $selected_venue_id], $base_url));

	// Rules table (single form; no nested forms)
	echo '<hr><h2>' . esc_html__('Season Rules', 'backstage-venue-manager') . '</h2>';

	echo '<form method="post" action="' . esc_url($rules_action_url) . '">';
	wp_nonce_field('bvmgr_season_dates_' . $selected_venue_id, 'bvmgr_season_dates_nonce');
	echo '<input type="hidden" name="vms_action" value="save_rules">';
	echo '<input type="hidden" name="venue_id" value="' . esc_attr((string)$selected_venue_id) . '">';

	echo '<table class="widefat striped"><thead><tr>';
	echo '<th class="vms-sd-col-enabled">' . esc_html__('Enabled', 'backstage-venue-manager') . '</th>';
	echo '<th class="vms-sd-col-type">' . esc_html__('Type', 'backstage-venue-manager') . '</th>';
	echo '<th>' . esc_html__('Rule', 'backstage-venue-manager') . '</th>';
	echo '<th class="vms-sd-col-note">' . esc_html__('Note', 'backstage-venue-manager') . '</th>';
	echo '<th class="vms-sd-col-delete">' . esc_html__('Delete', 'backstage-venue-manager') . '</th>';
	echo '</tr></thead><tbody>';

	if (!empty($rules)) {
		foreach ($rules as $r) {
			if (!is_array($r)) continue;

			$id = (string)($r['id'] ?? '');
			$type = (string)($r['type'] ?? '');
			$enabled = !empty($r['enabled']);
			$note = (string)($r['note'] ?? '');

			echo '<tr>';
			echo '<td><input type="checkbox" name="rules[' . esc_attr($id) . '][enabled]" value="1"' . checked($enabled, true, false) . '></td>';

			echo '<td><strong>' . esc_html(
				$type === 'open_window'
					? __('Open window', 'backstage-venue-manager')
					: ($type === 'blackout_date'
						? __('Blackout date', 'backstage-venue-manager')
						: ($type === 'blackout_range' ? __('Blackout range', 'backstage-venue-manager') : __('Unknown', 'backstage-venue-manager')))
			) . '</strong>';
			echo '<input type="hidden" name="rules[' . esc_attr($id) . '][id]" value="' . esc_attr($id) . '">';
			echo '<input type="hidden" name="rules[' . esc_attr($id) . '][type]" value="' . esc_attr($type) . '">';
			echo '</td>';

			echo '<td>';
			if ($type === 'open_window') {
				$start = (string)($r['start_mmdd'] ?? '');
				$end   = (string)($r['end_mmdd'] ?? '');
				echo esc_html__('Open window:', 'backstage-venue-manager') . ' ';
				echo '<input type="text" class="regular-text vms-sd-mmdd-input" name="rules[' . esc_attr($id) . '][start_mmdd]" value="' . esc_attr($start) . '" placeholder="MM-DD"> ';
				echo esc_html__('to', 'backstage-venue-manager') . ' ';
				echo '<input type="text" class="regular-text vms-sd-mmdd-input" name="rules[' . esc_attr($id) . '][end_mmdd]" value="' . esc_attr($end) . '" placeholder="MM-DD">';

				$mask = isset($r['days_w']) ? (int)$r['days_w'] : 0;
				$checked = bvmgr_sd_days_from_mask($mask);

				echo '<div class="vms-sd-pattern-row">';
				echo '<span class="vms-sd-pattern-label">' . esc_html__('Pattern:', 'backstage-venue-manager') . '</span>';
				for ($i = 0; $i <= 6; $i++) {
					$day_attr = checked(in_array($i, $checked, true), true, false);
					echo '<label class="vms-sd-day-option">';
					echo '<input type="checkbox" name="rules[' . esc_attr($id) . '][days_w_days][]" value="' . esc_attr((string)$i) . '" ' . $day_attr . '> ';
					echo esc_html($names[$i]) . '</label>';
				}
				echo '<div class="description vms-sd-pattern-help">' . esc_html__('Leave blank for all days.', 'backstage-venue-manager') . '</div>';
				echo '</div>';
			} elseif ($type === 'blackout_date') {
				$date = (string)($r['date_ymd'] ?? '');
				echo esc_html__('Blackout date:', 'backstage-venue-manager') . ' ';
				echo '<input type="date" name="rules[' . esc_attr($id) . '][date_ymd]" value="' . esc_attr($date) . '">';
			} elseif ($type === 'blackout_range') {
				$start = (string)($r['start_ymd'] ?? '');
				$end   = (string)($r['end_ymd'] ?? '');
				echo esc_html__('Blackout range:', 'backstage-venue-manager') . ' ';
				echo '<input type="date" name="rules[' . esc_attr($id) . '][start_ymd]" value="' . esc_attr($start) . '"> ';
				echo esc_html__('to', 'backstage-venue-manager') . ' ';
				echo '<input type="date" name="rules[' . esc_attr($id) . '][end_ymd]" value="' . esc_attr($end) . '">';
			} else {
				echo esc_html__('Unknown rule type', 'backstage-venue-manager');
			}
			echo '</td>';

			echo '<td><input type="text" class="regular-text vms-sd-note-input" name="rules[' . esc_attr($id) . '][note]" value="' . esc_attr($note) . '" placeholder="' . esc_attr__('Optional note', 'backstage-venue-manager') . '"></td>';

			echo '<td>';
			echo '<button type="submit" class="button button-secondary" name="delete_rule_id" value="' . esc_attr($id) . '" onclick="return confirm(\'' . esc_js(__('Delete this rule?', 'backstage-venue-manager')) . '\');">';
			echo esc_html__('Delete', 'backstage-venue-manager') . '</button>';
			echo '</td>';

			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="5">' . esc_html__('No rules yet. Add your first rule below.', 'backstage-venue-manager') . '</td></tr>';
	}

	echo '</tbody></table>';
	echo '<p class="vms-sd-save-row"><button type="submit" class="button button-primary">' . esc_html__('Save Rules', 'backstage-venue-manager') . '</button></p>';
	echo '<p class="description">' . esc_html__('Saving rules does not regenerate active dates. Use Generate Active Dates below to apply changes.', 'backstage-venue-manager') . '</p>';
	echo '</form>';

	// Add Open Window
	echo '<h3 class="vms-sd-subhead-gap">' . esc_html__('Add Open Window', 'backstage-venue-manager') . '</h3>';
	echo '<form method="post" action="' . esc_url($rules_action_url) . '">';
	wp_nonce_field('bvmgr_season_dates_' . $selected_venue_id, 'bvmgr_season_dates_nonce');
	echo '<input type="hidden" name="vms_action" value="add_open_window">';
	echo '<input type="hidden" name="venue_id" value="' . esc_attr((string)$selected_venue_id) . '">';

	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row">' . esc_html__('Open window', 'backstage-venue-manager') . '</th><td>';
	echo '<input type="text" name="new_start_mmdd" class="regular-text vms-sd-mmdd-input" placeholder="MM-DD"> ';
	echo esc_html__('to', 'backstage-venue-manager') . ' ';
	echo '<input type="text" name="new_end_mmdd" class="regular-text vms-sd-mmdd-input" placeholder="MM-DD">';
	echo '</td></tr>';

	echo '<tr><th scope="row">' . esc_html__('Pattern (optional)', 'backstage-venue-manager') . '</th><td><div class="vms-sd-pattern-row vms-sd-pattern-row--tight">';
	for ($i = 0; $i <= 6; $i++) {
		echo '<label class="vms-sd-day-option">';
		echo '<input type="checkbox" name="new_days_w_days[]" value="' . esc_attr((string)$i) . '"> ' . esc_html($names[$i]) . '</label>';
	}
	echo '</div><p class="description vms-sd-pattern-help">' . esc_html__('Leave blank for all days.', 'backstage-venue-manager') . '</p></td></tr>';

	echo '<tr><th scope="row">' . esc_html__('Note', 'backstage-venue-manager') . '</th><td>';
	echo '<input type="text" class="regular-text vms-sd-note-input--wide" name="new_note" placeholder="' . esc_attr__('Optional note', 'backstage-venue-manager') . '">';
	echo '</td></tr>';
	echo '</table>';

	submit_button(__('Add Open Window', 'backstage-venue-manager'));
	echo '</form>';

	// Add Blackout
	echo '<h3 class="vms-sd-subhead-gap">' . esc_html__('Add Blackout Date', 'backstage-venue-manager') . '</h3>';
	echo '<form method="post" action="' . esc_url($rules_action_url) . '">';
	wp_nonce_field('bvmgr_season_dates_' . $selected_venue_id, 'bvmgr_season_dates_nonce');
	echo '<input type="hidden" name="vms_action" value="add_blackout">';
	echo '<input type="hidden" name="venue_id" value="' . esc_attr((string)$selected_venue_id) . '">';

	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row">' . esc_html__('Blackout date', 'backstage-venue-manager') . '</th><td><input type="date" name="new_blackout_ymd"></td></tr>';
	echo '<tr><th scope="row">' . esc_html__('Note', 'backstage-venue-manager') . '</th><td>';
	echo '<input type="text" class="regular-text vms-sd-note-input--wide" name="new_note" placeholder="' . esc_attr__('Optional note', 'backstage-venue-manager') . '">';
	echo '</td></tr>';
	echo '</table>';

	echo '<p><button type="submit" class="button button-secondary">' . esc_html__('Add Blackout Date', 'backstage-venue-manager') . '</button></p>';
	echo '</form>';

	// Add Blackout Range
	echo '<h3 class="vms-sd-subhead-gap">' . esc_html__('Add Blackout Range', 'backstage-venue-manager') . '</h3>';
	echo '<form method="post" action="' . esc_url($rules_action_url) . '">';
	wp_nonce_field('bvmgr_season_dates_' . $selected_venue_id, 'bvmgr_season_dates_nonce');
	echo '<input type="hidden" name="vms_action" value="add_blackout_range">';
	echo '<input type="hidden" name="venue_id" value="' . esc_attr((string)$selected_venue_id) . '">';

	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row">' . esc_html__('From', 'backstage-venue-manager') . '</th><td><input type="date" name="new_blackout_start_ymd"></td></tr>';
	echo '<tr><th scope="row">' . esc_html__('To', 'backstage-venue-manager') . '</th><td><input type="date" name="new_blackout_end_ymd"></td></tr>';
	echo '<tr><th scope="row">' . esc_html__('Note', 'backstage-venue-manager') . '</th><td>';
	echo '<input type="text" class="regular-text vms-sd-note-input--wide" name="new_note" placeholder="' . esc_attr__('Optional note', 'backstage-venue-manager') . '">';
	echo '</td></tr>';
	echo '</table>';

	echo '<p><button type="submit" class="button button-secondary">' . esc_html__('Add Blackout Range', 'backstage-venue-manager') . '</button></p>';
	echo '</form>';

	// Generate + Clear
	echo '<hr><h2>' . esc_html__('Generate Active Dates', 'backstage-venue-manager') . '</h2>';

	$default_from = gmdate('Y') . '-01-01';
	$default_to   = gmdate('Y') . '-12-31';

	echo '<form method="post" action="' . esc_url($rules_action_url) . '">';
	wp_nonce_field('bvmgr_season_dates_' . $selected_venue_id, 'bvmgr_season_dates_nonce');
	echo '<input type="hidden" name="vms_action" value="generate_active">';
	echo '<input type="hidden" name="venue_id" value="' . esc_attr((string)$selected_venue_id) . '">';

	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row">' . esc_html__('From', 'backstage-venue-manager') . '</th><td><input type="date" name="gen_from_ymd" value="' . esc_attr($default_from) . '"></td></tr>';
	echo '<tr><th scope="row">' . esc_html__('To', 'backstage-venue-manager') . '</th><td><input type="date" name="gen_to_ymd" value="' . esc_attr($default_to) . '"></td></tr>';
	echo '<tr><th scope="row">' . esc_html__('Confirm', 'backstage-venue-manager') . '</th><td>';
	echo '<label><input type="checkbox" name="gen_confirm_replace" value="1"> ' . esc_html__('I understand this will replace existing generated dates for this venue.', 'backstage-venue-manager') . '</label>';
	echo '</td></tr></table>';

	echo '<p><button type="submit" class="button button-primary">' . esc_html__('Generate Active Dates', 'backstage-venue-manager') . '</button></p>';
	echo '</form>';

	echo '<h3 class="vms-sd-subhead-gap-sm">' . esc_html__('Clear Generated Dates', 'backstage-venue-manager') . '</h3>';
	echo '<form method="post" action="' . esc_url($rules_action_url) . '">';
	wp_nonce_field('bvmgr_season_dates_' . $selected_venue_id, 'bvmgr_season_dates_nonce');
	echo '<input type="hidden" name="vms_action" value="clear_generated">';
	echo '<input type="hidden" name="venue_id" value="' . esc_attr((string)$selected_venue_id) . '">';
	echo '<p><label><input type="checkbox" name="clear_confirm" value="1"> ' . esc_html__('I understand this will delete generated dates for this venue.', 'backstage-venue-manager') . '</label></p>';
	echo '<p><button type="submit" class="button button-secondary">' . esc_html__('Clear Generated Dates', 'backstage-venue-manager') . '</button></p>';
	echo '</form>';

	echo '</div>';

// Season Dates → Schedule integration helpers
// Keep this REST-safe (no admin dependencies).

if (!function_exists('bvmgr_sch_season_rules_have_enabled_open_windows')) {
	function bvmgr_sch_season_rules_have_enabled_open_windows(array $rules): bool {
		foreach ($rules as $r) {
			$type = (string)($r['type'] ?? '');
			$en   = (bool)($r['enabled'] ?? false);
			if ($en && $type === 'open_window') {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('bvmgr_sch_season_get_active_payload_for_venue')) {
	function bvmgr_sch_season_get_active_payload_for_venue(int $venue_id): array {
		$all = get_option('vms_season_active_dates_v1', []);
		if (!is_array($all)) {
			return [];
		}
		$p = $all[(string)$venue_id] ?? $all[$venue_id] ?? [];
		return is_array($p) ? $p : [];
	}
}

if (!function_exists('bvmgr_sch_season_payload_extract_open_dates_set')) {
	function bvmgr_sch_season_payload_extract_open_dates_set(array $payload): array {
		// Support multiple possible historical keys, normalize to set: ['YYYY-MM-DD' => true]
		$candidates = [];
		foreach (['open_ymd', 'dates_ymd', 'active_ymd', 'dates'] as $k) {
			if (isset($payload[$k]) && is_array($payload[$k])) {
				$candidates = $payload[$k];
				break;
			}
		}

		$set = [];

		// If already a set-like array, keep keys; if list-like, convert.
		$is_list = array_keys($candidates) === range(0, count($candidates) - 1);

		if ($is_list) {
			foreach ($candidates as $d) {
				$d = is_string($d) ? trim($d) : '';
				if ($d !== '') {
					$set[$d] = true;
				}
			}
		} else {
			foreach ($candidates as $k => $v) {
				$d = is_string($k) ? trim($k) : '';
				if ($d !== '') {
					$set[$d] = (bool)$v;
				}
			}
		}

		return $set;
	}
}

if (!function_exists('bvmgr_sch_season_rules_fingerprint_v1')) {
	function bvmgr_sch_season_rules_fingerprint_v1(array $rules): string {
		// Deterministic fingerprint: normalize rule shape → json → sha1
		$norm = [];

		foreach ($rules as $r) {
			if (!is_array($r)) { continue; }

			$norm[] = [
				'id'        => (string)($r['id'] ?? ''),
				'type'      => (string)($r['type'] ?? ''),
				'enabled'   => (bool)($r['enabled'] ?? false),
				'note'      => (string)($r['note'] ?? ''),
				'start_mmdd'=> (string)($r['start_mmdd'] ?? ''),
				'end_mmdd'  => (string)($r['end_mmdd'] ?? ''),
				'days_w'    => (int)($r['days_w'] ?? 0),
				'date_ymd'  => (string)($r['date_ymd'] ?? ''),
			];
		}

		// Stable ordering
		usort($norm, function($a, $b) {
			return strcmp($a['type'].$a['id'], $b['type'].$b['id']);
		});

		return sha1(wp_json_encode($norm));
	}
}

if (!function_exists('bvmgr_sch_season_open_override')) {
	/**
	 * Returns:
	 * - null if Season Dates is NOT authoritative for this venue (no enabled open_window rules)
	 * - bool if Season Dates IS authoritative:
	 *     - true/false based on generated active dates payload
	 *     - false if missing payload or stale payload (conservative)
	 *
	 * $info is filled with reason codes for UI/debugging.
	 */
	function bvmgr_sch_season_open_override(int $venue_id, string $ymd, array &$info = []): ?bool {
		$info = [
			'applied' => false,
			'reason'  => '',
		];

		if (!function_exists('bvmgr_sch_season_get_rules')) {
			return null;
		}

		$rules = bvmgr_sch_season_get_rules($venue_id);
		if (!is_array($rules)) {
			$rules = [];
		}

		// Authoritative only when we have enabled open windows.
		if (!bvmgr_sch_season_rules_have_enabled_open_windows($rules)) {
			return null;
		}

		$payload = bvmgr_sch_season_get_active_payload_for_venue($venue_id);
		if (!$payload) {
			$info['applied'] = true;
			$info['reason']  = 'season_not_generated';
			return false;
		}

		// Stale detection: prefer fingerprint stored inside payload at generation time.
		$current_fp = bvmgr_sch_season_rules_fingerprint_v1($rules);
		$payload_fp = (string)($payload['rules_fp'] ?? $payload['rules_hash'] ?? '');

		if ($payload_fp === '') {
			$info['applied'] = true;
			$info['reason']  = 'season_payload_missing_fingerprint';
			return false;
		}

		if (!hash_equals($payload_fp, $current_fp)) {
			$info['applied'] = true;
			$info['reason']  = 'season_stale_payload';
			return false;
		}

		$set = bvmgr_sch_season_payload_extract_open_dates_set($payload);

		$info['applied'] = true;
		$info['reason']  = 'season_applied';

		return isset($set[$ymd]) && $set[$ymd] === true;
	}
}

}
