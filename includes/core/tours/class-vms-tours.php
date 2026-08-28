<?php

defined('ABSPATH') || exit;

if (!class_exists('BVMGR_Tours')) {
	class BVMGR_Tours
	{
		const OPT_ENABLED                = 'vms_tours_enabled';
		const OPT_AUTOSTART              = 'vms_tours_autostart';
		const OPT_CAPABILITY             = 'vms_tours_capability';
		const OPT_LIBRARY                = 'vms_tours_library';
		const OPT_DRIFT_NOTICE_ENABLED   = 'vms_tours_drift_notice_enabled';
		const OPT_DRIFT_BADGE_ENABLED    = 'vms_tours_drift_badge_enabled';
		const OPT_AUTO_SCAN_ON_UPDATE    = 'vms_tours_auto_scan_on_update';
		const OPT_PENDING_SCAN           = 'vms_tours_pending_scan';
		const OPT_ANCHOR_CONTRACT        = 'vms_tours_anchor_contract';
		const OPT_DRIFT_REPORT           = 'vms_tours_drift_report';
		const OPT_DRIFT_HISTORY          = 'vms_tours_drift_history';
		const OPT_DRIFT_BADGE_CACHE      = 'vms_tours_drift_badge_cache';
		const OPT_LAST_VERSION           = 'vms_version';
		const USER_META_STATE            = 'vms_tours_state';
		const USER_META_NOTICE_DISMISSED = 'vms_tours_notice_dismissed_hash';
		const DRIVER_VERSION             = '1.4.0';

		public static function init(): void
		{
			add_action('admin_init', array(__CLASS__, 'register_defaults'));
			add_action('init', array(__CLASS__, 'maybe_mark_pending_scan_on_version_change'), 11);
			add_action('admin_init', array(__CLASS__, 'refresh_anchor_contract'));
			add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'), 25);
			add_action('admin_notices', array(__CLASS__, 'render_global_help_button'), 3);
			add_action('admin_notices', array(__CLASS__, 'render_drift_notice'));
			add_action('admin_menu', array(__CLASS__, 'add_menu_badge'), 999);
			add_action('admin_post_vms_tours_dismiss_notice', array(__CLASS__, 'dismiss_notice'));
			add_action('wp_ajax_vms_tours_update_state', array(__CLASS__, 'ajax_update_state'));
		}

		public static function register_defaults(): void
		{
			add_option(self::OPT_ENABLED, 1);
			add_option(self::OPT_AUTOSTART, 1);
			add_option(self::OPT_CAPABILITY, 'read');
			add_option(self::OPT_LIBRARY, 'driverjs');
			add_option(self::OPT_DRIFT_NOTICE_ENABLED, 1);
			add_option(self::OPT_DRIFT_BADGE_ENABLED, 1);
			add_option(self::OPT_AUTO_SCAN_ON_UPDATE, 1);
			add_option(self::OPT_PENDING_SCAN, 0);
			if (false === get_option(self::OPT_ANCHOR_CONTRACT, false)) {
				update_option(self::OPT_ANCHOR_CONTRACT, array());
			}
			if (false === get_option(self::OPT_DRIFT_REPORT, false)) {
				update_option(self::OPT_DRIFT_REPORT, self::empty_report('runtime'));
			}
			if (false === get_option(self::OPT_DRIFT_HISTORY, false)) {
				update_option(self::OPT_DRIFT_HISTORY, array());
			}
			if (false === get_option(self::OPT_DRIFT_BADGE_CACHE, false)) {
				update_option(self::OPT_DRIFT_BADGE_CACHE, array('count' => 0, 'generated_at' => time()));
			}
		}

		public static function maybe_mark_pending_scan_on_version_change(): void
		{
			$current = defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '';
			if ($current === '') {
				return;
			}

			$stored = (string) get_option(self::OPT_LAST_VERSION, '');
			if ($stored === '') {
				update_option(self::OPT_LAST_VERSION, $current, false);
				return;
			}

			if ($stored !== $current) {
				if (self::get_bool_option(self::OPT_AUTO_SCAN_ON_UPDATE, true)) {
					update_option(self::OPT_PENDING_SCAN, 1, false);
				}
				update_option(self::OPT_LAST_VERSION, $current, false);
			}
		}

		public static function get_run_capability(): string
		{
			$cap = (string) get_option(self::OPT_CAPABILITY, 'read');
			$cap = $cap !== '' ? $cap : 'read';
			return (string) apply_filters('vms_tours_capability', $cap);
		}

		public static function can_run_tours(): bool
		{
			if (!is_user_logged_in()) {
				return false;
			}
			if (!self::get_bool_option(self::OPT_ENABLED, true)) {
				return false;
			}
			return current_user_can(self::get_run_capability());
		}

		public static function is_admin_on_vms_page(): bool
		{
			if (!is_admin()) {
				return false;
			}
			$page = bvmgr_request_read_key($_GET, 'page'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive tours page routing only selects read-only admin context and remains nonce-free.
			if ($page === 'vms' || strpos($page, 'vms-') === 0) {
				return true;
			}
			if (function_exists('get_current_screen')) {
				$screen = get_current_screen();
				if ($screen && !empty($screen->post_type)) {
					$post_type = (string) $screen->post_type;
					return in_array($post_type, array('vms_event_plan', 'vms_vendor', 'vms_venue', 'vms_staff'), true);
				}
			}
			return false;
		}

		public static function get_registry(): array
		{
			if (!function_exists('bvmgr_get_tour_registry')) {
				return array();
			}
			$raw = (array) bvmgr_get_tour_registry();
			$out = array();
			foreach ($raw as $tour) {
				$normalized = self::normalize_tour($tour);
				if (!empty($normalized)) {
					$out[] = $normalized;
				}
			}
			return $out;
		}

		public static function get_anchor_contract(): array
		{
			$contract = get_option(self::OPT_ANCHOR_CONTRACT, array());
			return is_array($contract) ? $contract : array();
		}

		public static function refresh_anchor_contract(): void
		{
			$contract = array();
			foreach (self::get_registry() as $tour) {
				$tour_id  = sanitize_key((string) ($tour['id'] ?? ''));
				$required = array();
				$optional = array();
				foreach ((array) $tour['steps'] as $step) {
					$anchor = (string) $step['anchor'];
					if ($anchor === '') {
						continue;
					}
					if (!empty($step['optional']) || ((string) $step['severity']) === 'optional') {
						$optional[] = $anchor;
					} else {
						$required[] = $anchor;
					}
				}

				foreach ((array) $tour['contexts'] as $context) {
					$key = (string) $context['context_key'];
					if ($key === '') {
						continue;
					}
					if (!isset($contract[$key]) || !is_array($contract[$key])) {
						$contract[$key] = array(
							'screen_id'        => (string) $context['screen_id'],
							'url'              => (string) $context['url'],
							'required_anchors' => array(),
							'optional_anchors' => array(),
							'anchor_tour_map'  => array(),
							'tour_ids'         => array(),
							'generated_at'     => time(),
						);
					}

					$contract[$key]['screen_id'] = (string) $context['screen_id'];
					$contract[$key]['url'] = (string) $context['url'];
					$contract[$key]['required_anchors'] = array_values(array_unique(array_merge(
						(array) ($contract[$key]['required_anchors'] ?? array()),
						array_values(array_unique($required))
					)));
					$contract[$key]['optional_anchors'] = array_values(array_unique(array_merge(
						(array) ($contract[$key]['optional_anchors'] ?? array()),
						array_values(array_unique($optional))
					)));

					$anchor_tour_map = isset($contract[$key]['anchor_tour_map']) && is_array($contract[$key]['anchor_tour_map'])
						? $contract[$key]['anchor_tour_map']
						: array();
					foreach (array_merge($required, $optional) as $anchor) {
						$anchor = self::sanitize_anchor_token((string) $anchor);
						if ($anchor === '') {
							continue;
						}
						if (!isset($anchor_tour_map[$anchor]) || $anchor_tour_map[$anchor] === '') {
							$anchor_tour_map[$anchor] = $tour_id !== '' ? $tour_id : 'unknown';
						}
					}
					$contract[$key]['anchor_tour_map'] = $anchor_tour_map;

					$tour_ids = isset($contract[$key]['tour_ids']) && is_array($contract[$key]['tour_ids'])
						? $contract[$key]['tour_ids']
						: array();
					if ($tour_id !== '' && !in_array($tour_id, $tour_ids, true)) {
						$tour_ids[] = $tour_id;
					}
					$contract[$key]['tour_ids'] = array_values($tour_ids);
					$contract[$key]['tour_id'] = count($tour_ids) === 1 ? (string) $tour_ids[0] : '';
					$contract[$key]['generated_at'] = time();
				}
			}

			$existing = self::get_anchor_contract();
			if (wp_json_encode($existing) !== wp_json_encode($contract)) {
				update_option(self::OPT_ANCHOR_CONTRACT, $contract, false);
			}
		}

		public static function get_report(): array
		{
			$report = get_option(self::OPT_DRIFT_REPORT, array());
			if (!is_array($report) || empty($report)) {
				return self::empty_report('runtime');
			}
			if (!isset($report['summary']) || !is_array($report['summary'])) {
				$report['summary'] = array();
			}
			if (!isset($report['contexts']) || !is_array($report['contexts'])) {
				$report['contexts'] = array();
			}
			$report['summary'] = wp_parse_args($report['summary'], array(
				'missing_anchor_count' => 0,
				'affected_tour_count'  => 0,
			));
			return $report;
		}

		public static function get_tile_data(): array
		{
			$cache_key = 'vms_tours_tile_data';
			$cached    = get_transient($cache_key);
			if (is_array($cached)) {
				return $cached;
			}

			$report   = self::get_report();
			$summary  = isset($report['summary']) && is_array($report['summary']) ? $report['summary'] : array();
			$response = array(
				'enabled'              => self::get_bool_option(self::OPT_ENABLED, true),
				'autostart'            => self::get_bool_option(self::OPT_AUTOSTART, true),
				'pending_scan'         => (int) get_option(self::OPT_PENDING_SCAN, 0),
				'last_source'          => isset($report['source']) ? (string) $report['source'] : 'runtime',
				'last_report_at'       => isset($report['updated_at']) ? (int) $report['updated_at'] : 0,
				'missing_anchor_count' => (int) ($summary['missing_anchor_count'] ?? 0),
				'affected_tour_count'  => (int) ($summary['affected_tour_count'] ?? 0),
				'maintenance_url'      => admin_url('admin.php?page=vms-tour-maintenance'),
			);

			set_transient($cache_key, $response, 60);
			return $response;
		}

		public static function merge_runtime_report(array $payload): array
		{
			$context_key = sanitize_key((string) ($payload['context_key'] ?? ''));
			$anchor      = self::sanitize_anchor_token((string) ($payload['anchor'] ?? ''));
			$tour_id     = sanitize_key((string) ($payload['tour_id'] ?? 'unknown'));
			$severity    = sanitize_key((string) ($payload['severity'] ?? 'required'));
			$minute_key  = gmdate('YmdHi');

			if ($context_key === '' || $anchor === '') {
				return self::get_report();
			}

			$report = self::get_report();
			$today  = gmdate('Ymd');
			if (!isset($report['runtime_daily']) || !is_array($report['runtime_daily']) || (string) ($report['runtime_daily']['date'] ?? '') !== $today) {
				$report['runtime_daily'] = array('date' => $today, 'counts' => array());
			}

			$daily_counts = (array) ($report['runtime_daily']['counts'] ?? array());
			$ctx_count    = (int) ($daily_counts[$context_key] ?? 0);
			if ($ctx_count >= 200) {
				return $report;
			}

			if (!isset($report['contexts'][$context_key])) {
				$report['contexts'][$context_key] = array(
					'missing_anchors' => array(),
					'scan_error'      => '',
				);
			}
			if (!isset($report['contexts'][$context_key]['missing_anchors']) || !is_array($report['contexts'][$context_key]['missing_anchors'])) {
				$report['contexts'][$context_key]['missing_anchors'] = array();
			}

			$entry = $report['contexts'][$context_key]['missing_anchors'][$anchor] ?? array();
			$last_minute = (string) ($entry['last_minute_key'] ?? '');
			if ($last_minute === $minute_key) {
				$entry['last_seen_at'] = time();
			} else {
				$entry['seen_count']   = (int) ($entry['seen_count'] ?? 0) + 1;
				$entry['last_seen_at'] = time();
				$entry['first_seen_at'] = (int) ($entry['first_seen_at'] ?? time());
				$entry['tour_id']      = $tour_id;
				$entry['severity']     = $severity === 'optional' ? 'optional' : 'required';
				$entry['anchor']       = $anchor;
				$entry['last_minute_key'] = $minute_key;
				$ctx_count++;
			}

			$report['contexts'][$context_key]['missing_anchors'][$anchor] = $entry;
			$daily_counts[$context_key] = $ctx_count;
			$report['runtime_daily']['counts'] = $daily_counts;
			$report['source'] = 'runtime';
			$report['updated_at'] = time();
			$report['summary'] = self::build_summary($report['contexts']);

			update_option(self::OPT_DRIFT_REPORT, $report, false);
			self::update_badge_cache($report);
			delete_transient('vms_tours_tile_data');

			return $report;
		}

		public static function replace_scan_report(array $scan_report, string $source = 'scan'): array
		{
			$contexts_in = isset($scan_report['contexts']) && is_array($scan_report['contexts']) ? $scan_report['contexts'] : array();
			$contexts    = array();
			foreach ($contexts_in as $context_key => $row) {
				$key = sanitize_key((string) $context_key);
				if ($key === '') {
					continue;
				}
				$ctx = array(
					'scan_error'      => '',
					'missing_anchors' => array(),
				);
					if (is_array($row)) {
						$ctx['scan_error'] = sanitize_text_field((string) ($row['scan_error'] ?? ''));
						$missing = isset($row['missing_anchors']) && is_array($row['missing_anchors']) ? $row['missing_anchors'] : array();
						foreach ($missing as $anchor => $entry) {
							$anchor_key = self::sanitize_anchor_token((string) $anchor);
							if ($anchor_key === '') {
								continue;
							}
						$ctx['missing_anchors'][$anchor_key] = array(
							'anchor'        => $anchor_key,
							'tour_id'       => sanitize_key((string) ($entry['tour_id'] ?? 'unknown')),
							'severity'      => sanitize_key((string) ($entry['severity'] ?? 'required')),
							'first_seen_at' => time(),
							'last_seen_at'  => time(),
							'seen_count'    => (int) ($entry['seen_count'] ?? 1),
						);
					}
				}
				$contexts[$key] = $ctx;
			}

			$report = array(
				'source'       => in_array($source, array('scan', 'auto-update'), true) ? $source : 'scan',
				'updated_at'   => time(),
				'summary'      => self::build_summary($contexts),
				'contexts'     => $contexts,
				'runtime_daily'=> array('date' => gmdate('Ymd'), 'counts' => array()),
			);

			update_option(self::OPT_DRIFT_REPORT, $report, false);
			self::push_history($report);
			self::update_badge_cache($report);
			update_option(self::OPT_PENDING_SCAN, 0, false);
			delete_transient('vms_tours_tile_data');

			return $report;
		}

		public static function get_badge_count(): int
		{
			$cache = get_option(self::OPT_DRIFT_BADGE_CACHE, array());
			if (is_array($cache)) {
				$generated = (int) ($cache['generated_at'] ?? 0);
				if ($generated > 0 && (time() - $generated) < 60) {
					return (int) ($cache['count'] ?? 0);
				}
			}

			$report = self::get_report();
			$count  = (int) (($report['summary']['missing_anchor_count'] ?? 0));
			update_option(self::OPT_DRIFT_BADGE_CACHE, array(
				'count'        => $count,
				'generated_at' => time(),
			), false);
			return $count;
		}

		public static function enqueue_assets(): void
		{
			if (!self::can_run_tours()) {
				return;
			}

			$current_context = self::get_current_context_key();
			$page            = bvmgr_request_read_key($_GET, 'page'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive tours asset scope only selects read-only admin context and remains nonce-free.
			$needs_assets    = ($current_context !== '' || $page === 'vms-tour-maintenance' || $page === 'vms' || $page === 'vms-dashboard');
			if (!$needs_assets) {
				return;
			}

			self::enqueue_shared_assets();

			$nonce = wp_create_nonce('wp_rest');
			wp_localize_script('bvmgr-tours', 'BVMGR_TOURS', array(
				'enabled'         => self::get_bool_option(self::OPT_ENABLED, true),
				'autostart'       => self::get_bool_option(self::OPT_AUTOSTART, true),
				'library'         => (string) get_option(self::OPT_LIBRARY, 'driverjs'),
				'currentContext'  => $current_context,
				'tours'           => self::get_registry(),
				'anchorContract'  => self::get_anchor_contract(),
				'rest'            => array(
					'root'            => esc_url_raw(rest_url('vms/v1/tours/')),
					'nonce'           => $nonce,
					'drift'           => esc_url_raw(rest_url('vms/v1/tours/drift')),
					'driftScan'       => esc_url_raw(rest_url('vms/v1/tours/drift-scan')),
					'driftReport'     => esc_url_raw(rest_url('vms/v1/tours/drift-report')),
					'tileData'        => esc_url_raw(rest_url('vms/v1/tours/tile-data')),
					'anchorContract'  => esc_url_raw(rest_url('vms/v1/tours/anchor-contract')),
				),
				'ajax'            => array(
					'url'   => esc_url_raw(admin_url('admin-ajax.php')),
					'nonce' => wp_create_nonce('bvmgr_tours_state'),
				),
				'userState'       => self::get_current_user_state(),
				'canManage'       => current_user_can('manage_options'),
				'pendingScan'     => (int) get_option(self::OPT_PENDING_SCAN, 0),
				'maintenanceUrl'  => esc_url_raw(admin_url('admin.php?page=vms-tour-maintenance')),
				'i18n'            => array(
					'startWelcomeTour' => 'Start Welcome Tour',
					'scanNow'          => 'Run Scan Now',
					'copyReport'       => 'Copy report for Codex',
					'scanInProgress'   => 'Scan in progress…',
					'quickTipsStub'    => 'Quick Tips will be added in a future update.',
					'whatsNewStub'     => 'What\'s New will be added in a future update.',
				),
				'driverVersion'   => self::DRIVER_VERSION,
			));
		}

		public static function enqueue_shared_assets(): void
		{
			$version = function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '');

			$driver_js_rel  = 'assets/vendor/driverjs/driver.min.js';
			$driver_css_rel = 'assets/vendor/driverjs/driver.min.css';
			$driver_js_abs  = defined('BVMGR_PLUGIN_PATH') ? BVMGR_PLUGIN_PATH . $driver_js_rel : '';
			$driver_css_abs = defined('BVMGR_PLUGIN_PATH') ? BVMGR_PLUGIN_PATH . $driver_css_rel : '';

			if ($driver_js_abs !== '' && file_exists($driver_js_abs)) {
				wp_enqueue_script('bvmgr-driverjs', BVMGR_PLUGIN_URL . $driver_js_rel, array(), $version, true);
			}
			if ($driver_css_abs !== '' && file_exists($driver_css_abs)) {
				wp_enqueue_style('bvmgr-driverjs', BVMGR_PLUGIN_URL . $driver_css_rel, array(), $version);
			}

			wp_enqueue_style('bvmgr-tours', BVMGR_PLUGIN_URL . 'assets/css/vms-tours.css', array('bvmgr-admin'), $version);
			wp_enqueue_script('bvmgr-tours', BVMGR_PLUGIN_URL . 'assets/js/vms-tours.js', array(), $version, true);
		}

		public static function get_current_context_key(): string
		{
			$page   = bvmgr_request_read_key($_GET, 'page'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive tours context lookup only selects read-only admin context and remains nonce-free.
			$screen = function_exists('get_current_screen') ? get_current_screen() : null;
			$sid    = ($screen && isset($screen->id)) ? (string) $screen->id : '';
			$url    = self::get_current_admin_relative_url();

			foreach (self::get_registry() as $tour) {
				foreach ((array) $tour['contexts'] as $context) {
					$context_key = (string) ($context['context_key'] ?? '');
					if ($context_key === '') {
						continue;
					}
					$ctx_url = (string) ($context['url'] ?? '');
					if ($ctx_url !== '' && $url === $ctx_url) {
						return $context_key;
					}
					$ctx_screen = (string) ($context['screen_id'] ?? '');
					if ($ctx_screen !== '' && $sid !== '' && $ctx_screen === $sid) {
						return $context_key;
					}
					if ($page !== '' && $ctx_url !== '' && strpos($ctx_url, 'page=' . $page) !== false) {
						return $context_key;
					}
				}
			}
			return '';
		}

		public static function render_drift_notice(): void
		{
			if (!current_user_can('manage_options')) {
				return;
			}
			if (!self::is_admin_on_vms_page()) {
				return;
			}
			if (!self::get_bool_option(self::OPT_DRIFT_NOTICE_ENABLED, true)) {
				return;
			}
			$report = self::get_report();
			$count  = (int) (($report['summary']['missing_anchor_count'] ?? 0));
			if ($count <= 0) {
				return;
			}

			$hash = md5(wp_json_encode(array(
				'updated_at' => (int) ($report['updated_at'] ?? 0),
				'missing'    => $count,
			)));
			$user_id   = get_current_user_id();
			$dismissed = (string) get_user_meta($user_id, self::USER_META_NOTICE_DISMISSED, true);
			if ($dismissed !== '' && hash_equals($dismissed, $hash)) {
				return;
			}

			$dismiss_url = wp_nonce_url(
				admin_url('admin-post.php?action=vms_tours_dismiss_notice&hash=' . rawurlencode($hash)),
				'bvmgr_tours_dismiss_notice'
			);

			echo '<div class="notice notice-warning is-dismissible vms-tours-notice">';
			echo '<p><strong>Backstage Venue Manager Tour Drift:</strong> ' . esc_html((string) $count) . ' anchor(s) are missing. Run a scan and copy the report for Codex.</p>';
			echo '<p><a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=vms-tour-maintenance')) . '">View Tour Maintenance</a> ';
			echo '<a class="button" href="' . esc_url($dismiss_url) . '">Dismiss until new drift/scan</a></p>';
			echo '</div>';
		}

		public static function render_global_help_button(): void
		{
			if (!self::can_run_tours()) {
				return;
			}
			if (!self::is_admin_on_vms_page()) {
				return;
			}
			$enabled = apply_filters('vms_tours_render_global_help_button', true);
			if ($enabled === false) {
				return;
			}

			$context_key = self::get_current_context_key();
			if ($context_key === '') {
				return;
			}

			$tour_id = '';
			foreach (self::get_registry() as $tour) {
				foreach ((array) ($tour['contexts'] ?? array()) as $context) {
					if ($context_key === sanitize_key((string) ($context['context_key'] ?? ''))) {
						$tour_id = sanitize_key((string) ($tour['id'] ?? ''));
						break 2;
					}
				}
			}
			if (!function_exists('bvmgr_render_help_button')) {
				return;
			}

			echo '<div class="vms-global-help-float" style="position:fixed;top:42px;right:16px;z-index:100000;">';
			echo bvmgr_render_help_button(array(
				'tour_id' => $tour_id,
				'anchor' => '',
				'class' => 'vms-global-help-menu',
			));
			echo '</div>';
		}

		public static function dismiss_notice(): void
		{
			if (!current_user_can('manage_options')) {
				wp_die('Insufficient permissions.');
			}
			bvmgr_check_admin_referer_compat('bvmgr_tours_dismiss_notice');
			$hash = bvmgr_request_read_text_field($_GET, 'hash');
			if ($hash !== '') {
				update_user_meta(get_current_user_id(), self::USER_META_NOTICE_DISMISSED, $hash);
			}
			wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=vms-dashboard'));
			exit;
		}

		public static function add_menu_badge(): void
		{
			if (!current_user_can('manage_options')) {
				return;
			}
			if (!self::get_bool_option(self::OPT_DRIFT_BADGE_ENABLED, true)) {
				return;
			}
			$count = self::get_badge_count();
			if ($count <= 0) {
				return;
			}

			global $menu;
			if (!is_array($menu)) {
				return;
			}

			foreach ($menu as $index => $item) {
				$slug = isset($item[2]) ? (string) $item[2] : '';
				if ($slug !== 'vms-dashboard') {
					continue;
				}
				$title = isset($item[0]) ? (string) $item[0] : 'Backstage Venue Manager';
				if (strpos($title, 'plugin-count') !== false) {
					return;
				}
				$title .= ' <span class="update-plugins count-' . esc_attr((string) $count) . '"><span class="plugin-count">' . esc_html((string) $count) . '</span></span>';
				$menu[$index][0] = $title;
				return;
			}
		}

		public static function ajax_update_state(): void
		{
			if (!is_user_logged_in() || !self::can_run_tours()) {
				wp_send_json_error(array('message' => 'Forbidden'), 403);
			}
			bvmgr_check_ajax_referer_compat('bvmgr_tours_state', 'nonce');

			$tour_id = bvmgr_request_read_key($_POST, 'tour_id');
			$status  = bvmgr_request_read_key($_POST, 'status');
			if ($status === '') {
				$status = 'in_progress';
			}
			$version = bvmgr_request_read_absint($_POST, 'version');
			if ($version <= 0) {
				$version = 1;
			}
			$step = bvmgr_request_read_absint($_POST, 'step_index');
			if ($tour_id === '') {
				wp_send_json_error(array('message' => 'Missing tour_id'), 400);
			}

			$allowed = array('never', 'in_progress', 'completed', 'dismissed');
			if (!in_array($status, $allowed, true)) {
				$status = 'in_progress';
			}

			$user_id = get_current_user_id();
			$state   = self::get_current_user_state();
			$state[$tour_id] = array(
				'version_seen' => $version,
				'status'       => $status,
				'step_index'   => $step,
				'updated_at'   => time(),
			);
			update_user_meta($user_id, self::USER_META_STATE, wp_json_encode($state));
			update_user_meta($user_id, 'vms_tour_seen_' . $tour_id, $version);

			wp_send_json_success(array('state' => $state));
		}

		public static function get_current_user_state(): array
		{
			$user_id = get_current_user_id();
			if ($user_id <= 0) {
				return array();
			}
			$raw = get_user_meta($user_id, self::USER_META_STATE, true);
			if (is_array($raw)) {
				return $raw;
			}
			if (!is_string($raw) || $raw === '') {
				$state = array();
			} else {
				$decoded = json_decode($raw, true);
				$state = is_array($decoded) ? $decoded : array();
			}

			// Compatibility state: mirrors tour version seen in dedicated user meta per tour.
			foreach (self::get_registry() as $tour) {
				$tour_id = sanitize_key((string) ($tour['id'] ?? ''));
				if ($tour_id === '') {
					continue;
				}
				$seen_version = absint(get_user_meta($user_id, 'vms_tour_seen_' . $tour_id, true));
				if ($seen_version <= 0) {
					continue;
				}

				$current_seen = isset($state[$tour_id]) && is_array($state[$tour_id])
					? absint($state[$tour_id]['version_seen'] ?? 0)
					: 0;
				if ($seen_version > $current_seen) {
					$state[$tour_id] = array(
						'version_seen' => $seen_version,
						'status' => 'completed',
						'step_index' => 0,
						'updated_at' => time(),
					);
				}
			}

			return $state;
		}

		public static function verify_rest_nonce(WP_REST_Request $request): bool
		{
			$nonce = $request->get_header('X-WP-Nonce');
			$nonce = is_string($nonce) ? sanitize_text_field($nonce) : '';
			if ($nonce === '') {
				$param = $request->get_param('_wpnonce');
				if (!is_scalar($param)) {
					return false;
				}
				$nonce = sanitize_text_field((string) $param);
			}
			return $nonce !== '' && wp_verify_nonce($nonce, 'wp_rest');
		}

		public static function render_dashboard_tile(): void
		{
			if (!current_user_can('manage_options')) {
				return;
			}
			echo '<section id="vms-dashboard-tours" data-vms-tour="dashboard-tours-tile">';
			echo '<h2>Tours &amp; Drift Health</h2>';
			echo '<div class="vms-panel-body" id="vms-tours-dashboard-tile">';
			echo '<p class="vms-tours-tile-status">Loading…</p>';
			echo '<div class="vms-tours-tile-actions">';
			echo '<button type="button" class="button button-secondary" data-vms-tour-start="vms-welcome-dashboard">Start Welcome Tour</button> ';
			echo '<button type="button" class="button" data-vms-tour-scan-now>Run Scan Now</button> ';
			echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=vms-tour-maintenance')) . '">View Tour Maintenance</a> ';
			echo '<button type="button" class="button" data-vms-tour-copy-report>Copy report for Codex</button>';
			echo '</div>';
			echo '<pre class="vms-tours-copy-source" id="vms-tours-dashboard-copy" aria-hidden="true"></pre>';
			echo '</div>';
			echo '</section>';
		}

		private static function normalize_tour($tour): array
		{
			if (!is_array($tour)) {
				return array();
			}
			$id = sanitize_key((string) ($tour['id'] ?? ''));
			if ($id === '') {
				return array();
			}

			$title = sanitize_text_field((string) ($tour['title'] ?? $id));
			$version = absint($tour['version'] ?? 1);
			if ($version <= 0) {
				$version = 1;
			}

			$contexts = array();
			foreach ((array) ($tour['contexts'] ?? array()) as $context) {
				if (!is_array($context)) {
					continue;
				}
				$context_key = sanitize_key((string) ($context['context_key'] ?? ''));
				if ($context_key === '') {
					continue;
				}
				$contexts[] = array(
					'context_key' => $context_key,
					'screen_id'   => sanitize_key((string) ($context['screen_id'] ?? '')),
					'page_hook'   => sanitize_key((string) ($context['page_hook'] ?? '')),
					'url'         => self::normalize_admin_relative_url((string) ($context['url'] ?? '')),
				);
			}
			if (empty($contexts)) {
				return array();
			}

			$steps = array();
				foreach ((array) ($tour['steps'] ?? array()) as $step) {
					if (!is_array($step)) {
						continue;
					}
					$anchor = self::sanitize_anchor_token((string) ($step['anchor'] ?? ''));
					if ($anchor === '') {
						continue;
					}
				$optional = !empty($step['optional']);
				$severity = sanitize_key((string) ($step['severity'] ?? 'required'));
				if ($optional) {
					$severity = 'optional';
				}
				if (!in_array($severity, array('required', 'optional'), true)) {
					$severity = 'required';
				}
				$placement = sanitize_key((string) ($step['placement'] ?? 'bottom'));
				if (!in_array($placement, array('top', 'right', 'bottom', 'left'), true)) {
					$placement = 'bottom';
				}
				$skip_when_filled = array();
				foreach ((array) ($step['skip_when_filled'] ?? array()) as $rule) {
					if (!is_array($rule)) {
						continue;
					}
					$selector = sanitize_text_field((string) ($rule['selector'] ?? ''));
					if ($selector === '') {
						continue;
					}
					$default_value = $rule['defaultValue'] ?? ($rule['default'] ?? '');
					if (is_bool($default_value)) {
						$default_value = $default_value ? '1' : '0';
					} elseif (is_numeric($default_value)) {
						$default_value = 0 + $default_value;
					} else {
						$default_value = sanitize_text_field((string) $default_value);
					}
					$skip_when_filled[] = array(
						'selector' => $selector,
						'defaultValue' => $default_value,
					);
				}
				$steps[] = array(
					'anchor'    => $anchor,
					'title'     => sanitize_text_field((string) ($step['title'] ?? $anchor)),
					'content'   => self::sanitize_step_html((string) ($step['content'] ?? '')),
					'placement' => $placement,
					'align'     => self::normalize_align((string) ($step['align'] ?? 'start')),
					'severity'  => $severity,
					'optional'  => $optional,
					'on_next'   => sanitize_text_field((string) ($step['on_next'] ?? '')),
					'on_prev'   => sanitize_text_field((string) ($step['on_prev'] ?? '')),
					'skip_when_filled' => $skip_when_filled,
				);
			}
			if (empty($steps)) {
				return array();
			}

			return array(
				'id'       => $id,
				'title'    => $title,
				'version'  => $version,
				'contexts' => $contexts,
				'steps'    => $steps,
			);
		}

		private static function sanitize_step_html(string $html): string
		{
			$allowed = array(
				'p'      => array(),
				'br'     => array(),
				'strong' => array(),
				'em'     => array(),
				'ul'     => array(),
				'ol'     => array(),
				'li'     => array(),
				'a'      => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
				),
				'code'   => array(),
			);
			return wp_kses($html, $allowed);
		}

		private static function normalize_align(string $align): string
		{
			$align = sanitize_key($align);
			if (!in_array($align, array('start', 'center', 'end'), true)) {
				return 'start';
			}
			return $align;
		}

		private static function sanitize_anchor_token(string $anchor): string
		{
			$anchor = strtolower(trim($anchor));
			if ($anchor === '') {
				return '';
			}

			$sanitized = preg_replace('/[^a-z0-9._-]/', '', $anchor);
			return is_string($sanitized) ? $sanitized : '';
		}

		private static function build_summary(array $contexts): array
		{
			$missing = 0;
			$affected_tours = array();
			foreach ($contexts as $context) {
				if (!is_array($context)) {
					continue;
				}
				$anchors = isset($context['missing_anchors']) && is_array($context['missing_anchors']) ? $context['missing_anchors'] : array();
				foreach ($anchors as $entry) {
					$missing++;
					$tour_id = sanitize_key((string) ($entry['tour_id'] ?? 'unknown'));
					$affected_tours[$tour_id] = true;
				}
			}
			return array(
				'missing_anchor_count' => $missing,
				'affected_tour_count'  => count($affected_tours),
			);
		}

		private static function update_badge_cache(array $report): void
		{
			$count = (int) (($report['summary']['missing_anchor_count'] ?? 0));
			update_option(self::OPT_DRIFT_BADGE_CACHE, array(
				'count'        => $count,
				'generated_at' => time(),
			), false);
		}

		private static function push_history(array $report): void
		{
			$history = get_option(self::OPT_DRIFT_HISTORY, array());
			if (!is_array($history)) {
				$history = array();
			}
			$history[] = $report;
			if (count($history) > 10) {
				$history = array_slice($history, -10);
			}
			update_option(self::OPT_DRIFT_HISTORY, $history, false);
		}

		private static function get_bool_option(string $key, bool $default): bool
		{
			$value = get_option($key, $default ? 1 : 0);
			return !empty($value);
		}

		private static function normalize_admin_relative_url(string $url): string
		{
			$url = trim($url);
			if ($url === '') {
				return '';
			}
			if (strpos($url, 'admin.php') !== 0 && strpos($url, 'edit.php') !== 0) {
				return 'admin.php?page=' . ltrim($url, '?');
			}
			return $url;
		}

		private static function get_current_admin_relative_url(): string
		{
			$request_uri = bvmgr_request_current_uri('');
			if ($request_uri === '') {
				return '';
			}
			$parts = wp_parse_url($request_uri);
			if (!is_array($parts)) {
				return '';
			}
			$path  = isset($parts['path']) ? basename((string) $parts['path']) : '';
			$query = isset($parts['query']) ? (string) $parts['query'] : '';
			if ($path === '') {
				return '';
			}
			return $query !== '' ? ($path . '?' . $query) : $path;
		}

		private static function empty_report(string $source): array
		{
			return array(
				'source'      => $source,
				'updated_at'  => 0,
				'summary'     => array(
					'missing_anchor_count' => 0,
					'affected_tour_count'  => 0,
				),
				'contexts'    => array(),
				'runtime_daily' => array('date' => gmdate('Ymd'), 'counts' => array()),
			);
		}
	}
}

BVMGR_Tours::init();

if (!function_exists('bvmgr_enqueue_tour_assets')) {
	function bvmgr_enqueue_tour_assets(): void
	{
		if (!class_exists('BVMGR_Tours') || !method_exists('BVMGR_Tours', 'enqueue_shared_assets')) {
			return;
		}
		BVMGR_Tours::enqueue_shared_assets();
	}
}

if (!function_exists('bvmgr_render_help_button')) {
	if (!function_exists('bvmgr_tours_sanitize_anchor_token')) {
		function bvmgr_tours_sanitize_anchor_token(string $anchor): string
		{
			$anchor = strtolower(trim($anchor));
			if ($anchor === '') {
				return '';
			}
			$sanitized = preg_replace('/[^a-z0-9._-]/', '', $anchor);
			return is_string($sanitized) ? $sanitized : '';
		}
	}

	/**
	 * @param array<string,mixed> $args
	 */
	function bvmgr_render_help_button(array $args = array()): string
	{
		$tour_id = sanitize_key((string) ($args['tour_id'] ?? ''));
		$anchor = bvmgr_tours_sanitize_anchor_token((string) ($args['anchor'] ?? ''));
		$label = sanitize_text_field((string) ($args['label'] ?? __('Help', 'backstage-venue-manager')));
		$class = sanitize_html_class((string) ($args['class'] ?? ''));

		$wrapper_class = 'vms-help-menu';
		if ($class !== '') {
			$wrapper_class .= ' ' . $class;
		}
		$anchor_attr = $anchor !== '' ? ' data-vms-tour="' . esc_attr($anchor) . '"' : '';

		$html = '<details class="' . esc_attr($wrapper_class) . '" style="display:inline-block;position:relative;margin-left:6px;"' . $anchor_attr . '>';
		$html .= '<summary class="button button-secondary" style="list-style:none;cursor:pointer;">' . esc_html($label) . '</summary>';
		$html .= '<div class="vms-help-menu__panel" style="position:absolute;right:0;z-index:1000;margin-top:6px;padding:8px;border:1px solid #ccd0d4;background:#fff;box-shadow:0 8px 18px rgba(0,0,0,.12);display:flex;gap:6px;flex-wrap:wrap;min-width:320px;">';
		if ($tour_id !== '') {
			$html .= '<button type="button" class="button button-secondary" data-vms-tour-start="' . esc_attr($tour_id) . '">' . esc_html__('Start Guided Tour', 'backstage-venue-manager') . '</button>';
		}
		$html .= '<button type="button" class="button" data-vms-help-action="quick_tips">' . esc_html__('Quick Tips', 'backstage-venue-manager') . '</button>';
		$html .= '<button type="button" class="button" data-vms-help-action="whats_new">' . esc_html__('What\'s New', 'backstage-venue-manager') . '</button>';
		$html .= '</div>';
		$html .= '</details>';

		return $html;
	}
}
