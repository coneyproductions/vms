<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Admin_Addons')) {
	class VMS_Admin_Addons {
		private const PAGE_SLUG = 'vms-addons';
		private const NONCE_ACTION = 'vms_addons_action';

		public static function init(): void
		{
			add_action('admin_menu', array(__CLASS__, 'register_menu'), 15);
			add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));

			$actions = array(
				'get_state',
				'install_zip',
				'activate',
				'deactivate',
				'license_save',
				'license_activate',
				'license_deactivate',
				'license_validate',
				'updates_refresh',
				'update_run',
				'support_healthcheck',
				'support_export',
				'support_reset_uid',
			);
			foreach ($actions as $action) {
				add_action('wp_ajax_vms_addons_' . $action, array(__CLASS__, 'ajax_' . $action));
			}
		}

		public static function register_menu(): void
		{
			add_submenu_page(
				'vms-dashboard',
				__('Premium Add-ons', 'backstage-venue-manager'),
				__('Add-ons', 'backstage-venue-manager'),
				self::required_capability(),
				self::PAGE_SLUG,
				array(__CLASS__, 'render_page')
			);
		}

		public static function render_page(): void
		{
			self::authorize();
			$tab = self::current_tab();
			$state = self::build_state();
			require VMS_PLUGIN_PATH . 'includes/admin/addons/views/page-addons.php';
		}

		public static function enqueue_assets(string $hook): void
		{
			$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
			if ($page !== self::PAGE_SLUG) {
				return;
			}
			wp_enqueue_style('vms-addons-admin', VMS_PLUGIN_URL . 'assets/admin/addons/addons.css', array(), VMS_VERSION);
			wp_enqueue_script('vms-addons-admin', VMS_PLUGIN_URL . 'assets/admin/addons/addons.js', array(), VMS_VERSION, true);
			wp_localize_script('vms-addons-admin', 'VMS_ADDONS', array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce(self::NONCE_ACTION),
				'tab' => self::current_tab(),
				'pageSlug' => self::PAGE_SLUG,
			));
		}

		private static function current_tab(): string
		{
			$tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'installed';
			$allowed = array('installed', 'discover', 'licenses', 'updates', 'support');
			return in_array($tab, $allowed, true) ? $tab : 'installed';
		}

		private static function required_capability(): string
		{
			if (defined('VMS_CAP_MANAGE') && VMS_CAP_MANAGE !== '') {
				return VMS_CAP_MANAGE;
			}
			return 'manage_options';
		}

		private static function authorize(): void
		{
			if (!current_user_can(self::required_capability())) {
				wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
			}
		}

		private static function ajax_authorize(): void
		{
			self::authorize();
			check_ajax_referer(self::NONCE_ACTION, 'nonce');
		}

		private static function read_slug(): string
		{
			$slug = isset($_POST['slug']) ? sanitize_key((string) $_POST['slug']) : '';
			if ($slug === '' || !VMS_Addons_Manifest::by_slug($slug)) {
				wp_send_json_error(array('message' => __('Unknown add-on slug.', 'backstage-venue-manager')), 400);
			}
			return $slug;
		}

		private static function build_state(): array
		{
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$plugins = get_plugins();
			$active = array_map('strval', (array) get_option('active_plugins', array()));
			$updates = get_site_transient('update_plugins');
			$license_store = VMS_Addons_Licensing::store();
			$entries = VMS_Addons_Manifest::entries();
			$health = (array) get_option(VMS_Addons_Health::OPTION_LAST_HEALTH, array());

			$items = array();
			$counts = array('installed' => 0, 'updates' => 0, 'licenses_active' => 0, 'total' => count($entries));
			foreach ($entries as $entry) {
				$plugin_file = (string) $entry['plugin_file'];
				$installed = isset($plugins[$plugin_file]);
				$is_active = in_array($plugin_file, $active, true);
				$has_update = is_object($updates) && isset($updates->response[$plugin_file]);
				$license = (array) ($license_store[$entry['slug']] ?? array('status' => 'missing'));
				$status = sanitize_key((string) ($license['status'] ?? 'missing'));

				if ($installed) {
					$counts['installed']++;
				}
				if ($has_update) {
					$counts['updates']++;
				}
				if ($status === 'active') {
					$counts['licenses_active']++;
				}

				$items[] = array_merge($entry, array(
					'installed' => $installed,
					'active' => $is_active,
					'license' => array(
						'status' => $status,
						'status_message' => (string) ($license['status_message'] ?? ''),
						'last_validated' => (string) ($license['last_validated'] ?? ''),
						'install_id' => absint($license['install_id'] ?? 0),
						'license_key_masked' => VMS_Addons_Licensing::masked_key((string) ($license['license_key'] ?? '')),
					),
					'version_current' => $installed ? (string) ($plugins[$plugin_file]['Version'] ?? '') : '',
					'version_new' => $has_update ? (string) ($updates->response[$plugin_file]->new_version ?? '') : '',
					'update_available' => $has_update,
				));
			}

			return array(
				'items' => $items,
				'counts' => $counts,
				'logs' => VMS_Addons_Logger::recent(50),
				'health' => $health,
				'uid' => VMS_Addons_Licensing::uid(),
			);
		}

		public static function ajax_get_state(): void
		{
			self::ajax_authorize();
			wp_send_json_success(array('state' => self::build_state()));
		}

		public static function ajax_install_zip(): void
		{
			self::ajax_authorize();
			$slug = self::read_slug();
			$manifest = VMS_Addons_Manifest::by_slug($slug);
			if (($manifest['install']['method'] ?? '') !== 'zip_upload') {
				wp_send_json_error(array('message' => __('This add-on does not support ZIP install in Phase 1.', 'backstage-venue-manager')), 400);
			}
			$result = VMS_Addons_Installer::install_zip($_FILES['zip_file'] ?? array());
			if (is_wp_error($result)) {
				VMS_Addons_Logger::log('error', 'install_zip', $result->get_error_message(), $slug);
				wp_send_json_error(array('message' => $result->get_error_message()), 500);
			}
			VMS_Addons_Logger::log('info', 'install_zip', __('ZIP installation completed.', 'backstage-venue-manager'), $slug, $result);
			if (!empty($_POST['activate_after'])) {
				$plugin_file = (string) ($manifest['plugin_file'] ?? $result['plugin_file'] ?? '');
				if ($plugin_file !== '') {
					$activate = VMS_Addons_Installer::activate($plugin_file);
					if (is_wp_error($activate)) {
						VMS_Addons_Logger::log('error', 'activate_after_install', $activate->get_error_message(), $slug);
					}
				}
			}
			wp_send_json_success(array('state' => self::build_state()));
		}

		public static function ajax_activate(): void
		{
			self::ajax_authorize();
			$slug = self::read_slug();
			$entry = VMS_Addons_Manifest::by_slug($slug);
			$result = VMS_Addons_Installer::activate((string) $entry['plugin_file']);
			if (is_wp_error($result)) {
				VMS_Addons_Logger::log('error', 'activate', $result->get_error_message(), $slug);
				wp_send_json_error(array('message' => $result->get_error_message()), 500);
			}
			VMS_Addons_Logger::log('info', 'activate', __('Add-on activated.', 'backstage-venue-manager'), $slug);
			wp_send_json_success(array('state' => self::build_state()));
		}

		public static function ajax_deactivate(): void
		{
			self::ajax_authorize();
			$slug = self::read_slug();
			$entry = VMS_Addons_Manifest::by_slug($slug);
			VMS_Addons_Installer::deactivate((string) $entry['plugin_file']);
			VMS_Addons_Logger::log('info', 'deactivate', __('Add-on deactivated.', 'backstage-venue-manager'), $slug);
			wp_send_json_success(array('state' => self::build_state()));
		}

		public static function ajax_license_save(): void
		{
			self::ajax_authorize();
			$slug = self::read_slug();
			$key = sanitize_text_field((string) ($_POST['license_key'] ?? ''));
			$status = ($key === '') ? 'missing' : 'unknown';
			$entry = VMS_Addons_Licensing::save_entry($slug, array('license_key' => $key, 'status' => $status, 'status_message' => ''));
			VMS_Addons_Logger::log('info', 'license_save', __('License key saved.', 'backstage-venue-manager'), $slug, array('license_key_tail' => substr($key, -4)));
			wp_send_json_success(array('entry' => $entry, 'state' => self::build_state()));
		}

		public static function ajax_license_activate(): void
		{
			self::ajax_authorize();
			self::handle_license_operation('activate');
		}

		public static function ajax_license_deactivate(): void
		{
			self::ajax_authorize();
			self::handle_license_operation('deactivate');
		}

		public static function ajax_license_validate(): void
		{
			self::ajax_authorize();
			self::handle_license_operation('validate');
		}

		private static function handle_license_operation(string $operation): void
		{
			$slug = self::read_slug();
			$manifest = VMS_Addons_Manifest::by_slug($slug);
			$store = VMS_Addons_Licensing::store();
			$current = (array) ($store[$slug] ?? array());
			if (empty($manifest['freemius']['product_id'])) {
				wp_send_json_error(array('message' => __('This add-on does not define Freemius product metadata.', 'backstage-venue-manager')), 400);
			}

			if ($operation === 'activate') {
				$result = VMS_Addons_Licensing::activate($current, $manifest);
			} elseif ($operation === 'deactivate') {
				$result = VMS_Addons_Licensing::deactivate($current, $manifest);
			} else {
				$result = VMS_Addons_Licensing::validate($current, $manifest);
			}

			if (is_wp_error($result)) {
				VMS_Addons_Licensing::save_entry($slug, array('status' => 'error', 'status_message' => $result->get_error_message()));
				VMS_Addons_Logger::log('error', 'license_' . $operation, $result->get_error_message(), $slug, array('license_key_tail' => substr((string) ($current['license_key'] ?? ''), -4)));
				wp_send_json_error(array('message' => $result->get_error_message()), 500);
			}

			VMS_Addons_Licensing::save_entry($slug, $result);
			VMS_Addons_Logger::log('info', 'license_' . $operation, __('License operation completed.', 'backstage-venue-manager'), $slug);
			wp_send_json_success(array('state' => self::build_state()));
		}

		public static function ajax_updates_refresh(): void
		{
			self::ajax_authorize();
			wp_update_plugins();
			VMS_Addons_Logger::log('info', 'updates_refresh', __('Plugin updates refreshed.', 'backstage-venue-manager'));
			wp_send_json_success(array('state' => self::build_state()));
		}

		public static function ajax_update_run(): void
		{
			self::ajax_authorize();
			$slug = self::read_slug();
			$entry = VMS_Addons_Manifest::by_slug($slug);
			$result = VMS_Addons_Installer::update((string) $entry['plugin_file']);
			if (is_wp_error($result)) {
				VMS_Addons_Logger::log('error', 'update_run', $result->get_error_message(), $slug);
				wp_send_json_error(array('message' => $result->get_error_message()), 500);
			}
			wp_update_plugins();
			VMS_Addons_Logger::log('info', 'update_run', __('Update completed.', 'backstage-venue-manager'), $slug);
			wp_send_json_success(array('state' => self::build_state()));
		}

		public static function ajax_support_healthcheck(): void
		{
			self::ajax_authorize();
			$health = VMS_Addons_Health::check();
			VMS_Addons_Logger::log('info', 'support_healthcheck', __('Health check completed.', 'backstage-venue-manager'));
			wp_send_json_success(array('health' => $health, 'state' => self::build_state()));
		}

		public static function ajax_support_export(): void
		{
			self::ajax_authorize();
			$state = self::build_state();
			$payload = VMS_Addons_Health::export_payload($state);
			VMS_Addons_Logger::log('info', 'support_export', __('Diagnostics export generated.', 'backstage-venue-manager'));
			wp_send_json_success(array(
				'filename' => 'vms-addons-diagnostics-' . gmdate('Ymd-His') . '.json',
				'contents' => wp_json_encode($payload, JSON_PRETTY_PRINT),
			));
		}

		public static function ajax_support_reset_uid(): void
		{
			self::ajax_authorize();
			$new_uid = VMS_Addons_Licensing::reset_uid();
			VMS_Addons_Logger::log('warn', 'support_reset_uid', __('Site UID reset by admin.', 'backstage-venue-manager'));
			wp_send_json_success(array('uid' => $new_uid, 'state' => self::build_state()));
		}
	}
}

VMS_Admin_Addons::init();
