<?php

defined('ABSPATH') || exit;

if (!class_exists('BVMGR_Tours_Service')) {
	class BVMGR_Tours_Service
	{
		/**
		 * @var BVMGR_Tours_Service|null
		 */
		private static $instance = null;

		/**
		 * @var BVMGR_Tours_Registry
		 */
		private $registry;

		/**
		 * @var BVMGR_Tours_Storage
		 */
		private $storage;

		/**
		 * @var BVMGR_Tours_Screen
		 */
		private $screen;

		/**
		 * @var BVMGR_Tours_Compat
		 */
		private $compat;

		/**
		 * @var BVMGR_Tours_Admin
		 */
		private $admin;

		/**
		 * @var bool
		 */
		private $loaded_filter_tours = false;

		/**
		 * @var bool
		 */
		private $core_tours_registered = false;

		public static function instance(): BVMGR_Tours_Service
		{
			if (!(self::$instance instanceof self)) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __construct()
		{
			$this->registry = new BVMGR_Tours_Registry();
			$this->storage = new BVMGR_Tours_Storage();
			$this->screen = new BVMGR_Tours_Screen();
			$this->compat = new BVMGR_Tours_Compat($this->screen);
			$this->admin = new BVMGR_Tours_Admin($this, $this->storage);

			$this->compat->init();
			$this->admin->init();

			add_action('init', array($this, 'boot_tours_registry'), 20);

			add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'), 30);
			add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'), 30);
			add_action('admin_footer', array($this, 'render_help_mount'));
			add_action('wp_footer', array($this, 'render_help_mount'));
			add_action('admin_notices', array($this, 'render_missing_tour_notice'));

			add_action('wp_ajax_vms_tours_save_prefs', array($this, 'ajax_save_prefs'));
			add_action('wp_ajax_vms_tours_mark_complete', array($this, 'ajax_mark_complete'));
		}

		public function boot_tours_registry(): void
		{
			if (!$this->core_tours_registered) {
				$this->register_core_tours();
				$this->core_tours_registered = true;
			}

			$this->refresh_filter_tours();
		}

		/**
		 * @param array<string,mixed> $tour_def
		 */
		public function register_tour(array $tour_def): void
		{
			$this->registry->register($tour_def);
		}

		/**
		 * @return array<int,array<string,mixed>>
		 */
		public function get_registry(): array
		{
			return $this->registry->all();
		}

		public function enqueue_admin_assets(string $hook_suffix = ''): void
		{
			if (!$this->screen->is_vms_admin_screen()) {
				return;
			}

			$screen_key = $this->screen->resolve_screen_key();
			$this->storage->remember_seen_screen($screen_key);

			$page = vms_request_read_key($_GET, 'page'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive tours admin asset scope only selects read-only screen context and remains nonce-free.
			$settings = $this->storage->get_site_settings();
			$screen_tours = $this->registry->for_screen($screen_key);

			$should_enqueue_runtime = !empty($screen_tours) || !empty($settings['help_button_enabled']) || current_user_can('manage_options') || $page === 'vms-guided-tours';
			if (!$should_enqueue_runtime) {
				return;
			}

			$this->enqueue_runtime_assets($screen_key);

			if ($page === 'vms-guided-tours') {
				$this->enqueue_admin_ui_assets();
			}
		}

		public function enqueue_frontend_assets(): void
		{
			if (is_admin()) {
				return;
			}

			$screen_key = $this->screen->resolve_screen_key();
			if ($screen_key === 'frontend:unknown') {
				return;
			}

			$tours = $this->registry->for_screen($screen_key);
			if (empty($tours)) {
				return;
			}

			$this->enqueue_runtime_assets($screen_key);
		}

		public function render_help_mount(): void
		{
			if (!wp_script_is('vms-tours-runtime', 'enqueued')) {
				return;
			}

			echo '<div id="vms-help-tour-root" aria-hidden="true"></div>';
		}

		public function render_missing_tour_notice(): void
		{
			if (!$this->is_debug_enabled()) {
				return;
			}
			if (!current_user_can('manage_options')) {
				return;
			}
			if (!$this->screen->is_vms_admin_screen()) {
				return;
			}

			$screen_key = $this->screen->resolve_screen_key();
			if ($screen_key === 'admin:unknown') {
				return;
			}

			if (!empty($this->registry->for_screen($screen_key))) {
				return;
			}

			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p>' . esc_html__('No guided tour is registered for this screen yet.', 'backstage-venue-manager') . ' <code>' . esc_html($screen_key) . '</code></p>';
			echo '</div>';
		}

		/**
		 * @return array<string,mixed>
		 */
		private function read_ajax_prefs_from_request(array $source): array
		{
			$prefs = vms_request_read_array($source, 'prefs');
			return is_array($prefs) ? $prefs : array();
		}

		public function ajax_save_prefs(): void
		{
			if (!is_user_logged_in() || !current_user_can('read')) {
				wp_send_json_error(array('message' => 'Forbidden'), 403);
			}
			check_ajax_referer('vms_tours', 'nonce');

			$user_id = get_current_user_id();
			$screen_key = $this->sanitize_screen_key(vms_request_read_scalar($_POST, 'screen_key'));
			if (!current_user_can('manage_options')) {
				if ($screen_key === '' || empty($this->registry->for_screen($screen_key))) {
					wp_send_json_error(array('message' => 'Screen not allowed'), 403);
				}
			}

			$incoming = $this->read_ajax_prefs_from_request($_POST);
			$existing = $this->storage->get_user_prefs($user_id);

			if (array_key_exists('auto_run_enabled', $incoming) && !is_array($incoming['auto_run_enabled']) && !is_object($incoming['auto_run_enabled'])) {
				$existing['auto_run_enabled'] = !empty($incoming['auto_run_enabled']);
			}
			if (array_key_exists('level', $incoming) && is_scalar($incoming['level'])) {
				$existing['level'] = sanitize_key((string) $incoming['level']);
			}
			if (array_key_exists('dismissed_tours', $incoming) && is_array($incoming['dismissed_tours'])) {
				$dismissed = isset($existing['dismissed_tours']) && is_array($existing['dismissed_tours'])
					? $existing['dismissed_tours']
					: array();
				foreach ((array) $incoming['dismissed_tours'] as $tour_id => $value) {
					$id = $this->sanitize_tour_id((string) $tour_id);
					if ($id === '') {
						continue;
					}
					if (is_array($value) || is_object($value)) {
						continue;
					}
					$dismissed[$id] = !empty($value);
				}
				$existing['dismissed_tours'] = $dismissed;
			}

			$stored = $this->storage->save_user_prefs($user_id, $existing);
			wp_send_json_success(array('prefs' => $stored));
		}

		public function ajax_mark_complete(): void
		{
			if (!is_user_logged_in() || !current_user_can('read')) {
				wp_send_json_error(array('message' => 'Forbidden'), 403);
			}
			check_ajax_referer('vms_tours', 'nonce');

			$user_id = get_current_user_id();
			$tour_id = $this->sanitize_tour_id(vms_request_read_scalar($_POST, 'tour_id'));
			$mode = vms_request_read_key($_POST, 'mode');
			if ($mode === '') {
				$mode = 'complete';
			}
			$tour_version = vms_request_read_text_field($_POST, 'tour_version');

			if ($tour_id === '') {
				wp_send_json_error(array('message' => 'Missing tour_id'), 400);
			}

			$tour = $this->registry->get($tour_id);
			if (!$tour) {
				wp_send_json_error(array('message' => 'Unknown tour'), 404);
			}

			$user = wp_get_current_user();
			$prefs = $this->storage->get_user_prefs($user_id);
			$user_level = isset($prefs['level']) ? (string) $prefs['level'] : 'beginner';
			if (!$this->registry->user_matches_audience($tour, $user) || !$this->registry->level_allows_tour($user_level, (string) ($tour['level'] ?? 'beginner'))) {
				wp_send_json_error(array('message' => 'Tour not visible to user'), 403);
			}

			if ($mode === 'seen') {
				$this->storage->mark_tour_seen($user_id, $tour_id);
			} else {
				if ($tour_version === '') {
					$tour_version = (string) ($tour['version'] ?? '1.0.0');
				}
				$this->storage->mark_tour_complete($user_id, $tour_id, $tour_version);
			}

			$state = $this->storage->get_user_state($user_id);
			wp_send_json_success(array(
				'tour_id' => $tour_id,
				'state' => isset($state[$tour_id]) ? $state[$tour_id] : array(),
			));
		}

		private function load_filter_tours(): void
		{
			if ($this->loaded_filter_tours) {
				return;
			}

			$bulk = apply_filters('vms_tours_register', array());
			if (is_array($bulk)) {
				foreach ($bulk as $tour_def) {
					if (is_array($tour_def)) {
						$this->register_tour($tour_def);
					}
				}
			}

			$legacy_tours = $this->compat->collect_legacy_tours();
			foreach ($legacy_tours as $legacy_tour) {
				if (!is_array($legacy_tour)) {
					continue;
				}

				$converted = $this->compat->convert_legacy_tour($legacy_tour);
				if (!empty($converted)) {
					$this->register_tour($converted);
				}
			}

			$this->loaded_filter_tours = true;
		}

		public function refresh_filter_tours(): void
		{
			$this->loaded_filter_tours = false;
			$this->load_filter_tours();
		}

		private function enqueue_runtime_assets(string $screen_key): void
		{
			$version = $this->asset_version();

			$driver_js = 'assets/vendor/driverjs/driver.js';
			$driver_css = 'assets/vendor/driverjs/driver.css';
			$runtime_js = 'assets/js/vms-tours-runtime.js';
			$runtime_css = 'assets/css/vms-tours.css';

			if (file_exists(BVMGR_PLUGIN_PATH . $driver_css)) {
				wp_enqueue_style('vms-driverjs', BVMGR_PLUGIN_URL . $driver_css, array(), $version);
			}
			if (file_exists(BVMGR_PLUGIN_PATH . $driver_js)) {
				wp_enqueue_script('vms-driverjs', BVMGR_PLUGIN_URL . $driver_js, array(), $version, true);
			}

			wp_enqueue_style('vms-tours', BVMGR_PLUGIN_URL . $runtime_css, array('vms-driverjs'), $version);
			wp_enqueue_script('vms-tours-runtime', BVMGR_PLUGIN_URL . $runtime_js, array('vms-driverjs'), $version, true);

			$payload = $this->build_payload($screen_key);
			wp_add_inline_script('vms-tours-runtime', 'window.VMS_TOURS_PAYLOAD = ' . wp_json_encode($payload) . ';', 'before');
		}

		private function enqueue_admin_ui_assets(): void
		{
			$version = $this->asset_version();
			wp_enqueue_style('vms-tours-admin', BVMGR_PLUGIN_URL . 'assets/css/vms-tours-admin.css', array('vms-tours'), $version);
			wp_enqueue_script('vms-tours-admin', BVMGR_PLUGIN_URL . 'assets/js/vms-tours-admin.js', array('vms-tours-runtime'), $version, true);
		}

		/**
		 * @return array<string,mixed>
		 */
		private function build_payload(string $screen_key): array
		{
			$user_id = get_current_user_id();
			$settings = $this->storage->get_site_settings();
			$can_run_tours = ($user_id > 0 && current_user_can('read'));

			if ($can_run_tours) {
				$prefs = $this->storage->get_user_prefs($user_id);
				$state = $this->storage->get_user_state($user_id);
				$user = wp_get_current_user();
			} else {
				$prefs = array(
					'auto_run_enabled' => !empty($settings['auto_run_default']),
					'level' => (string) ($settings['default_level'] ?? 'beginner'),
					'dismissed_tours' => array(),
				);
				$state = array();
				$user = new WP_User(0);
			}

			$payload_tours = array();
			$screen_tours = $this->registry->for_screen($screen_key);
			foreach ($screen_tours as $tour) {
				if (!$can_run_tours) {
					continue;
				}

				if (!$this->registry->user_matches_audience($tour, $user)) {
					continue;
				}

				$user_level = isset($prefs['level']) ? (string) $prefs['level'] : 'beginner';
				if (!$this->registry->level_allows_tour($user_level, (string) ($tour['level'] ?? 'beginner'))) {
					continue;
				}

				$payload_tours[] = $this->registry->sanitize_for_js($tour);
			}

			$payload = array(
				'screenKey' => $screen_key,
				'user' => array(
					'id' => (int) $user_id,
					'canRunTours' => $can_run_tours,
					'prefs' => array(
						'auto_run_enabled' => !empty($prefs['auto_run_enabled']),
						'level' => sanitize_key((string) ($prefs['level'] ?? 'beginner')),
						'dismissed_tours' => isset($prefs['dismissed_tours']) && is_array($prefs['dismissed_tours'])
							? $prefs['dismissed_tours']
							: array(),
					),
					'state' => $state,
				),
				'settings' => array(
					'global_enabled' => !empty($settings['global_enabled']),
					'auto_run_delay_ms' => (int) ($settings['auto_run_delay_ms'] ?? 400),
					'max_auto_run_per_page_load' => (int) ($settings['max_auto_run_per_page_load'] ?? 1),
					'help_button_enabled' => !empty($settings['help_button_enabled']),
					'debug_log_enabled' => !empty($settings['debug_log_enabled']),
				),
				'context' => array(
					'isAdminScreen' => is_admin(),
				),
				'tours' => $payload_tours,
				'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
				'nonce' => wp_create_nonce('vms_tours'),
				'debug' => $this->is_debug_enabled(),
			);

			return $payload;
		}

		private function register_core_tours(): void
		{
			$this->register_tour(array(
				'id' => 'vms.dashboard.basics',
				'title' => __('Backstage Venue Manager Dashboard Basics', 'backstage-venue-manager'),
				'screen' => 'admin:vms-dashboard',
				'version' => '1.0.0',
				'level' => 'beginner',
				'description' => __('Walk through the key dashboard areas and where to click next.', 'backstage-venue-manager'),
				'audience' => array(
					'capabilities_any' => array('manage_options', 'vms_manage'),
					'capabilities_all' => array(),
					'roles_any' => array(),
					'roles_all' => array(),
				),
				'auto_run' => true,
				'priority' => 10,
				'steps' => array(
						array(
							'id' => 'dashboard_filters',
							'selector' => '[data-vms-tour="dashboard-filters"]',
							'title' => __('Scope Filters', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Set venue and inclusion filters first so every dashboard panel reflects the right scope.', 'backstage-venue-manager')),
							'placement' => 'bottom',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'dashboard_quick_actions',
							'selector' => '[data-vms-tour="dashboard_quick_actions"]',
							'title' => __('Quick Actions', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Use these shortcuts to jump into Event Plans, Schedule, Vendors, and Venues.', 'backstage-venue-manager')),
							'placement' => 'top',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'dashboard_financial',
							'selector' => '[data-vms-tour="dashboard-financial"]',
							'title' => __('Financial Snapshot', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Review this panel first for immediate money visibility.', 'backstage-venue-manager')),
							'placement' => 'right',
						'guard' => array('type' => 'element_exists'),
					),
					array(
						'id' => 'dashboard_week',
						'selector' => '#vms-dashboard-week',
						'title' => __('This Week', 'backstage-venue-manager'),
						'body' => wp_kses_post(__('Track near-term operational load, then cross-check staffing and due dates.', 'backstage-venue-manager')),
						'placement' => 'left',
						'guard' => array('type' => 'element_exists'),
					),
						array(
							'id' => 'dashboard_staffing',
							'selector' => '[data-vms-tour="dashboard-staffing"]',
							'title' => __('Staffing Readiness', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Spot coverage gaps before event days arrive.', 'backstage-venue-manager')),
							'placement' => 'top',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'dashboard_help_start',
							'selector' => '[data-vms-tour="dashboard_help_action"]',
							'title' => __('Start Help Quickly', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Use this launcher to run the dashboard tour immediately whenever you need a refresher.', 'backstage-venue-manager')),
							'placement' => 'top',
							'guard' => array('type' => 'element_exists'),
						),
					array(
						'id' => 'dashboard_bills',
						'selector' => '#vms-dashboard-bills',
						'title' => __('Upcoming Bills', 'backstage-venue-manager'),
						'body' => wp_kses_post(__('Use this panel to prevent missed payouts and late obligations.', 'backstage-venue-manager')),
						'placement' => 'top',
						'guard' => array('type' => 'element_exists'),
					),
				),
			));

				$this->register_tour(array(
					'id' => 'vms.event_plan.editor.basics',
				'title' => __('Event Plan Editor Basics', 'backstage-venue-manager'),
				'screen' => 'admin:vms_event_plan',
				'version' => '1.2.0',
				'level' => 'beginner',
				'description' => __('Cover the essential Event Plan editor controls before publishing.', 'backstage-venue-manager'),
				'audience' => array(
					'capabilities_any' => array('manage_options', 'edit_posts'),
					'capabilities_all' => array(),
					'roles_any' => array(),
					'roles_all' => array(),
				),
				'auto_run' => false,
				'priority' => 10,
				'steps' => array(
					array(
						'id' => 'venue_selector',
						'selector' => '#vms_venue_id',
						'title' => __('Venue Selector', 'backstage-venue-manager'),
						'body' => wp_kses_post(__('Choose the venue first because compensation defaults and availability checks depend on it.', 'backstage-venue-manager')),
						'placement' => 'bottom',
						'guard' => array(
							'type' => 'field_is_empty',
							'selector' => '#vms_venue_id',
							'trim' => true,
						),
					),
					array(
						'id' => 'date_selector',
						'selector' => '#vms_event_date',
						'title' => __('Event Date', 'backstage-venue-manager'),
						'body' => wp_kses_post(__('Set the event date so holiday rules and date defaults are applied correctly.', 'backstage-venue-manager')),
						'placement' => 'bottom',
						'guard' => array(
							'type' => 'field_is_empty',
							'selector' => '#vms_event_date',
							'trim' => true,
						),
					),
					array(
						'id' => 'status_publish_area',
						'selector' => 'button[name="vms_event_plan_action"][value="publish_now"]',
						'title' => __('Status and Publish Controls', 'backstage-venue-manager'),
						'body' => wp_kses_post(__('Use Save Draft while iterating, then mark Ready before Publish Now.', 'backstage-venue-manager')),
						'placement' => 'top',
						'guard' => array('type' => 'element_exists'),
					),
					array(
						'id' => 'compensation_section',
						'selector' => '#vms-compensation',
						'title' => __('Compensation Section', 'backstage-venue-manager'),
						'body' => wp_kses_post(__('Review and draft compensation here while you edit the plan. Base + Attendance Bonus now lives in this section alongside the existing structures, and locked pay should only happen after the basic event details have been saved.', 'backstage-venue-manager')),
						'placement' => 'top',
						'guard' => array('type' => 'element_exists'),
					),
					array(
						'id' => 'attendance_bonus_structure',
						'selector' => '[data-structure="attendance_bonus"]',
						'title' => __('Base + Attendance Bonus', 'backstage-venue-manager'),
						'body' => wp_kses_post(__('Choose this structure when the event has a guaranteed base plus an attendance-driven payout. Operators can switch between Step and Continuous bonus styles, and can set an optional Max Bonus cap without faking door-split math.', 'backstage-venue-manager')),
						'placement' => 'top',
						'guard' => array('type' => 'element_exists'),
					),
					array(
						'id' => 'attendance_bonus_preview',
						'selector' => '#vms-attendance-bonus-preview:not(.vms-hidden)',
						'title' => __('Attendance Preview', 'backstage-venue-manager'),
						'body' => wp_kses_post(__('When Base + Attendance Bonus is selected, this preview shows the formula and projected payouts. If you set a Max Bonus cap, the preview extends progression out to the cap so the stopping point is obvious before you lock anything.', 'backstage-venue-manager')),
						'placement' => 'top',
						'guard' => array('type' => 'element_exists'),
					),
					array(
						'id' => 'lock_draft_pay',
						'selector' => 'button[name="vms_event_plan_action"][value="lock_draft_pay"]',
						'title' => __('Lock Draft Pay', 'backstage-venue-manager'),
						'body' => wp_kses_post(__('Use Lock Draft Pay to freeze the current payout values after Date, Venue, Start Time, End Time, and Primary Vendor are saved. The locked snapshot is the payout source of truth, including any attendance-bonus terms.', 'backstage-venue-manager')),
						'placement' => 'top',
						'guard' => array('type' => 'element_exists'),
					),
					array(
						'id' => 'staff_roles',
						'selector' => '[data-section-key="staff"]',
						'title' => __('Staff Roles Area', 'backstage-venue-manager'),
						'body' => wp_kses_post(__('Set staffing headcount and assignments so readiness reflects real coverage.', 'backstage-venue-manager')),
						'placement' => 'top',
						'guard' => array('type' => 'element_exists'),
					),
					array(
						'id' => 'save_draft_button',
						'selector' => 'button[name="vms_event_plan_action"][value="save_draft"]',
						'title' => __('Save Progress', 'backstage-venue-manager'),
						'body' => wp_kses_post(__('Save Draft once the basics are in place, then return to compensation or status actions. This first save is what enables safe pay locking.', 'backstage-venue-manager')),
						'placement' => 'left',
						'guard' => array('type' => 'element_exists'),
					),
					),
				));

				$this->register_tour(array(
					'id' => 'vms.comp_package.editor.basics',
					'title' => __('Comp Package Editor Basics', 'backstage-venue-manager'),
					'screen' => 'admin:vms_comp_package',
					'version' => '1.0.0',
					'level' => 'beginner',
					'description' => __('Set venue-scoped compensation packages, including the new attendance-bonus structure.', 'backstage-venue-manager'),
					'audience' => array(
						'capabilities_any' => array('manage_options', 'edit_posts'),
						'capabilities_all' => array(),
						'roles_any' => array(),
						'roles_all' => array(),
					),
					'auto_run' => true,
					'priority' => 11,
					'steps' => array(
						array(
							'id' => 'comp_package_help',
							'selector' => '[data-vms-tour="comp-package.help"]',
							'title' => __('Start Tour Anytime', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Use this button whenever you want to relaunch the Comp Package editor tour.', 'backstage-venue-manager')),
							'placement' => 'bottom',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'comp_package_venue',
							'selector' => '[data-vms-tour="comp-package.venue"]',
							'title' => __('Venue Scope', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Comp Packages are usually venue-scoped. Pick the venue first so operators only see relevant package options on Event Plans.', 'backstage-venue-manager')),
							'placement' => 'bottom',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'comp_package_type',
							'selector' => '[data-vms-tour="comp-package.type"]',
							'title' => __('Comp Type', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Use Comp Type to choose Flat Fee, Door Split, Flat Fee + Door Split, or Base + Attendance Bonus. Attendance bonuses use explicit Step or Continuous progression.', 'backstage-venue-manager')),
							'placement' => 'bottom',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'comp_package_base_pay',
							'selector' => '[data-vms-tour="comp-package.base-pay"]',
							'title' => __('Guaranteed Amount', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('This field becomes Base Pay when the package uses attendance bonuses. After you switch the type to Base + Attendance Bonus, Bonus Style, Bonus Starts After, Step or Continuous fields, and the optional Max Bonus cap appear below, and saving clears the opposite-mode fields so package terms stay canonical.', 'backstage-venue-manager')),
							'placement' => 'top',
							'guard' => array('type' => 'element_exists'),
						),
					),
				));

				$this->register_tour(array(
					'id' => 'vms.event_plan.list.basics',
					'title' => __('Event Plans List Basics', 'backstage-venue-manager'),
					'screen' => 'admin:edit-vms_event_plan',
					'version' => '1.0.0',
					'level' => 'beginner',
					'description' => __('Use filters, what-if status inclusion, and list tools to manage Event Plans quickly.', 'backstage-venue-manager'),
					'audience' => array(
						'capabilities_any' => array('manage_options', 'edit_posts'),
						'capabilities_all' => array(),
						'roles_any' => array(),
						'roles_all' => array(),
					),
					'auto_run' => true,
					'priority' => 12,
					'steps' => array(
						array(
							'id' => 'list_help_action',
							'selector' => '[data-vms-tour="event-plans.help-action"]',
							'title' => __('Start Tour Anytime', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Use this button whenever you want to relaunch the Event Plans list tour.', 'backstage-venue-manager')),
							'placement' => 'bottom',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'include_draft_ready',
							'selector' => '[data-vms-tour="event-plans.include-drafts"]',
							'title' => __('Include Draft/Ready What-If', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Toggle Draft/Ready inclusion so planning what-if records can be reviewed alongside published plans.', 'backstage-venue-manager')),
							'placement' => 'bottom',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'search_event_plans',
							'selector' => '#post-search-input',
							'title' => __('Search by Title', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Use search to quickly jump to a specific Event Plan before editing status or compensation details.', 'backstage-venue-manager')),
							'placement' => 'left',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'event_plan_rows',
							'selector' => '.wp-list-table.posts',
							'title' => __('Plan Rows', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Review status, readiness, and warning indicators directly in the list table before opening a plan.', 'backstage-venue-manager')),
							'placement' => 'top',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'add_new_event_plan',
							'selector' => '.page-title-action',
							'title' => __('Create a New Event Plan', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Use Add New to open the Event Plan editor and build the next show record.', 'backstage-venue-manager')),
							'placement' => 'bottom',
							'guard' => array('type' => 'element_exists'),
						),
					),
				));

				$this->register_tour(array(
					'id' => 'vms.guided_tours.page.basics',
					'title' => __('Guided Tours Page Basics', 'backstage-venue-manager'),
					'screen' => 'admin:vms-guided-tours',
					'version' => '1.0.0',
					'level' => 'beginner',
					'description' => __('Review global defaults, reset progress, and run tours by registry entry.', 'backstage-venue-manager'),
					'audience' => array(
						'capabilities_any' => array('manage_options'),
						'capabilities_all' => array(),
						'roles_any' => array(),
						'roles_all' => array(),
					),
					'auto_run' => true,
					'priority' => 10,
					'steps' => array(
						array(
							'id' => 'global_settings_form',
							'selector' => '[data-vms-tour="guided-tours.global-settings"]',
							'title' => __('Global Defaults', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('These controls define global guided-tour defaults, including auto-run behavior and help button visibility.', 'backstage-venue-manager')),
							'placement' => 'right',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'reset_progress',
							'selector' => '[data-vms-tour="guided-tours.reset-progress"]',
							'title' => __('Reset Your Progress', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Reset clears your personal completed-tour state and dismissed auto-run flags.', 'backstage-venue-manager')),
							'placement' => 'right',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'registry_table',
							'selector' => '[data-vms-tour="guided-tours.registry-table"]',
							'title' => __('Tour Registry', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Run tours directly from this table and verify completion status/version for your account.', 'backstage-venue-manager')),
							'placement' => 'top',
							'guard' => array('type' => 'element_exists'),
						),
					),
				));

				$this->register_tour(array(
					'id' => 'vms.settings.ticketing_ui',
					'title' => __('Ticketing UI Settings', 'backstage-venue-manager'),
					'screen' => 'admin:vms-settings',
					'version' => '1.0.1',
					'level' => 'beginner',
					'description' => __('Configure progressive ticket UI rollout, admin preview, add-on section labels, per-event overrides, and instant rollback behavior.', 'backstage-venue-manager'),
					'audience' => array(
						'capabilities_any' => array('manage_options'),
						'capabilities_all' => array(),
						'roles_any' => array(),
						'roles_all' => array(),
					),
					'auto_run' => true,
					'priority' => 12,
					'steps' => array(
						array(
							'id' => 'ticket_ui_about',
							'selector' => '[data-vms-tour="ticketing-ui.about"]',
							'title' => __('What the Ticket UI Options Are', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Progressive mode keeps all admission choices together in one card and tucks optional add-ons below while preserving existing business rules.', 'backstage-venue-manager')),
							'placement' => 'bottom',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'ticket_ui_admin_preview',
							'selector' => '[data-vms-tour="ticketing-ui.admin-preview"]',
							'title' => __('Admin Preview', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Turn this on to preview V2 as an admin while public users remain in Safe Mode (TEC-only). Use the Progressive layout option when you are ready to test the new grouped flow publicly or per event.', 'backstage-venue-manager')),
							'placement' => 'bottom',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'ticket_ui_public_enable',
							'selector' => '[data-vms-tour="ticketing-ui.public-enable"]',
							'title' => __('Choose the Public Ticket Layout', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('Set Ticket UI Layout to Progressive to publish the streamlined Tickets + Add-ons flow for all users. The add-on section heading and subtext can be customized for each venue.', 'backstage-venue-manager')),
							'placement' => 'bottom',
							'guard' => array('type' => 'element_exists'),
						),
						array(
							'id' => 'ticket_ui_rollback',
							'selector' => '[data-vms-tour="ticketing-ui.rollback"]',
							'title' => __('Rollback Instantly', 'backstage-venue-manager'),
							'body' => wp_kses_post(__('For instant rollback, switch layout to Safe Mode (TEC-only), disable Admin Preview, or force Legacy / Safe Mode on a single Event Plan.', 'backstage-venue-manager')),
							'placement' => 'top',
							'guard' => array('type' => 'element_exists'),
						),
					),
				));
			}

		private function asset_version(): string
		{
			if (defined('BVMGR_TOURS_VERSION')) {
				return (string) BVMGR_TOURS_VERSION;
			}
			if (function_exists('vms_asset_version')) {
				return vms_asset_version();
			}
			if (defined('BVMGR_VERSION')) {
				return (string) BVMGR_VERSION;
			}

			return '1.0.0';
		}

		private function sanitize_tour_id(string $tour_id): string
		{
			$tour_id = strtolower(trim($tour_id));
			if ($tour_id === '') {
				return '';
			}
			$sanitized = preg_replace('/[^a-z0-9._\-]/', '', $tour_id);
			return is_string($sanitized) ? $sanitized : '';
		}

		private function sanitize_screen_key(string $screen_key): string
		{
			$screen_key = strtolower(trim($screen_key));
			if ($screen_key === '') {
				return '';
			}
			$sanitized = preg_replace('/[^a-z0-9:_\-]/', '', $screen_key);
			return is_string($sanitized) ? $sanitized : '';
		}

		private function is_debug_enabled(): bool
		{
			$settings = $this->storage->get_site_settings();
			return !empty($settings['debug_log_enabled']) || (defined('BVMGR_TOURS_DEBUG') && BVMGR_TOURS_DEBUG);
		}
	}
}
