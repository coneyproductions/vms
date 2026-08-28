<?php

defined('ABSPATH') || exit;

if (!class_exists('BVMGR_Tours_Admin')) {
	class BVMGR_Tours_Admin
	{
		/**
		 * @var BVMGR_Tours_Service
		 */
		private $service;

			/**
			 * @var BVMGR_Tours_Storage
			 */
			private $storage;

			public function __construct(BVMGR_Tours_Service $service, BVMGR_Tours_Storage $storage)
			{
				$this->service = $service;
				$this->storage = $storage;
			}

			private function query_arg(string $key): string
			{
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tours admin notice flag only changes admin display state.
				if (!isset($_GET[$key])) {
					return '';
				}

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only tours admin notice flag is unslashed here and sanitized by the caller.
				return (string) wp_unslash($_GET[$key]);
			}

			public function init(): void
			{
			add_action('admin_menu', array($this, 'register_menu'), 40);
			add_action('admin_init', array($this, 'register_settings'));
			add_action('admin_post_vms_tours_reset_my_state', array($this, 'handle_reset_my_state'));
		}

		public function register_menu(): void
		{
			add_submenu_page(
				'vms-dashboard',
				__('Guided Tours', 'backstage-venue-manager'),
				__('Guided Tours', 'backstage-venue-manager'),
				'manage_options',
				'vms-guided-tours',
				array($this, 'render_page')
			);
		}

		public function register_settings(): void
		{
			register_setting(
				'vms_tours_settings_group',
				BVMGR_Tours_Storage::OPTION_SETTINGS,
				array($this, 'sanitize_settings_option')
			);

			add_settings_section(
				'vms_tours_settings_section',
				__('Guided Tours Settings', 'backstage-venue-manager'),
				array($this, 'render_settings_intro'),
				'vms-guided-tours'
			);

			add_settings_field('global_enabled', __('Enable guided tours globally', 'backstage-venue-manager'), array($this, 'render_checkbox_global_enabled'), 'vms-guided-tours', 'vms_tours_settings_section');
			add_settings_field('default_level', __('Default level', 'backstage-venue-manager'), array($this, 'render_select_default_level'), 'vms-guided-tours', 'vms_tours_settings_section');
			add_settings_field('auto_run_default', __('Default auto-run enabled', 'backstage-venue-manager'), array($this, 'render_checkbox_auto_run_default'), 'vms-guided-tours', 'vms_tours_settings_section');
			add_settings_field('auto_run_delay_ms', __('Auto-run delay (ms)', 'backstage-venue-manager'), array($this, 'render_number_auto_run_delay'), 'vms-guided-tours', 'vms_tours_settings_section');
			add_settings_field('max_auto_run_per_page_load', __('Max auto-run tours per page load', 'backstage-venue-manager'), array($this, 'render_number_max_auto_run'), 'vms-guided-tours', 'vms_tours_settings_section');
			add_settings_field('help_button_enabled', __('Help button enabled', 'backstage-venue-manager'), array($this, 'render_checkbox_help_button'), 'vms-guided-tours', 'vms_tours_settings_section');
			add_settings_field('debug_log_enabled', __('Debug logging enabled', 'backstage-venue-manager'), array($this, 'render_checkbox_debug_log'), 'vms-guided-tours', 'vms_tours_settings_section');
		}

		/**
		 * @param mixed $value
		 * @return array<string,mixed>
		 */
		public function sanitize_settings_option($value): array
		{
			$input = is_array($value) ? $value : array();
			return $this->storage->save_site_settings($input);
		}

		public function render_settings_intro(): void
		{
			echo '<p>' . esc_html__('Use one consistent guided tour framework across Backstage Venue Manager modules and screens.', 'backstage-venue-manager') . '</p>';
		}

		public function render_page(): void
		{
			if (!current_user_can('manage_options')) {
				wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
			}

			if (function_exists('bvmgr_admin_ui_render_shell')) {
				bvmgr_admin_ui_render_shell(
					array(
						'title' => __('Guided Tours', 'backstage-venue-manager'),
						'subtitle' => __('Manage global tour defaults, reset your progress, and run tours by screen.', 'backstage-venue-manager'),
						'shell_id' => 'vms-guided-tours-wrap',
					),
					array($this, 'render_page_content')
				);
				return;
			}

			echo '<div class="wrap">';
			echo '<h1>' . esc_html__('Guided Tours', 'backstage-venue-manager') . '</h1>';
			$this->render_page_content();
			echo '</div>';
		}

		public function handle_reset_my_state(): void
		{
			if (!is_user_logged_in() || !current_user_can('read')) {
				wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
			}

			bvmgr_check_admin_referer_compat('bvmgr_tours_reset_my_state');
			$this->storage->reset_user_state(get_current_user_id());

			$redirect = add_query_arg(
				array(
					'page' => 'vms-guided-tours',
					'vms_tours_reset_my_state' => '1',
				),
				admin_url('admin.php')
			);
			wp_safe_redirect($redirect);
			exit;
		}

		private function render_registry_table(): void
		{
			$tours = $this->service->get_registry();
			if (empty($tours)) {
				echo '<h2>' . esc_html__('Registered Tours', 'backstage-venue-manager') . '</h2>';
				echo '<p>' . esc_html__('No tours are registered yet.', 'backstage-venue-manager') . '</p>';
				return;
			}

			usort($tours, static function ($a, $b): int {
				$a_screen = (string) ($a['screen'] ?? '');
				$b_screen = (string) ($b['screen'] ?? '');
				if ($a_screen !== $b_screen) {
					return strcmp($a_screen, $b_screen);
				}

				$a_priority = (int) ($a['priority'] ?? 10);
				$b_priority = (int) ($b['priority'] ?? 10);
				return $a_priority <=> $b_priority;
			});

			$state = $this->storage->get_user_state(get_current_user_id());

			echo '<h2>' . esc_html__('Registered Tours', 'backstage-venue-manager') . '</h2>';
			echo '<table class="widefat striped vms-tours-admin-table" data-vms-tour="guided-tours.registry-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__('Tour ID', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Screen key', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Level', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Version', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Auto-run', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Priority', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Status for current user', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Run', 'backstage-venue-manager') . '</th>';
			echo '</tr></thead><tbody>';

			foreach ($tours as $tour) {
				$id = (string) ($tour['id'] ?? '');
				$row_state = isset($state[$id]) && is_array($state[$id]) ? $state[$id] : array();
				$completed_version = isset($row_state['completed_version']) ? (string) $row_state['completed_version'] : '';
				$completed_at = isset($row_state['completed_at']) ? (string) $row_state['completed_at'] : '';
				$tour_version = (string) ($tour['version'] ?? '');

				$status_label = __('Not completed', 'backstage-venue-manager');
				if ($completed_version !== '') {
					if ($completed_version === $tour_version) {
						$status_label = sprintf(
							/* translators: 1: version, 2: datetime */
							__('Completed at version %1$s (%2$s)', 'backstage-venue-manager'),
							esc_html($completed_version),
							esc_html($completed_at !== '' ? $completed_at : __('time unknown', 'backstage-venue-manager'))
						);
					} else {
						$status_label = sprintf(
							/* translators: 1: completed version, 2: current version */
							__('Completed older version %1$s (current %2$s)', 'backstage-venue-manager'),
							esc_html($completed_version),
							esc_html($tour_version)
						);
					}
				}

				echo '<tr>';
				echo '<td><code>' . esc_html($id) . '</code></td>';
				echo '<td><code>' . esc_html((string) ($tour['screen'] ?? '')) . '</code></td>';
				echo '<td>' . esc_html((string) ($tour['level'] ?? 'beginner')) . '</td>';
				echo '<td><code>' . esc_html($tour_version) . '</code></td>';
				echo '<td>' . (!empty($tour['auto_run']) ? esc_html__('Yes', 'backstage-venue-manager') : esc_html__('No', 'backstage-venue-manager')) . '</td>';
				echo '<td>' . esc_html((string) ((int) ($tour['priority'] ?? 10))) . '</td>';
				echo '<td>' . wp_kses_post($status_label) . '</td>';
				echo '<td><button type="button" class="button button-secondary" data-vms-tour-run="' . esc_attr($id) . '">' . esc_html__('Run', 'backstage-venue-manager') . '</button></td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		/**
		 * @return array{show:bool,state:string}
		 */
		private function get_reset_notice_context(): array
		{
			$show_notice = ($this->query_arg('vms_tours_reset_my_state') !== '');

			return array(
				'show' => $show_notice,
				'state' => $show_notice ? 'reset_success' : 'hidden',
			);
		}

		/**
		 * @param array{show:bool,state:string} $context
		 */
		private function render_reset_notice(array $context): void
		{
			if (empty($context['show']) || ($context['state'] ?? 'hidden') !== 'reset_success') {
				return;
			}

			echo '<div class="notice notice-success is-dismissible" data-vms-tour="guided-tours.reset-notice"><p>' . esc_html__('Your tour progress has been reset.', 'backstage-venue-manager') . '</p></div>';
		}

		public function render_page_content(): void
		{
			echo '<div class="vms-tours-admin-page" data-vms-tour="guided-tours.settings">';
			$this->render_reset_notice($this->get_reset_notice_context());

			echo '<form method="post" action="options.php" data-vms-tour="guided-tours.global-settings">';
			settings_fields('vms_tours_settings_group');
			do_settings_sections('vms-guided-tours');
			submit_button(__('Save Guided Tours Settings', 'backstage-venue-manager'));
			echo '</form>';

			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-tours-admin-reset-form" data-vms-tour="guided-tours.reset-progress">';
			echo '<input type="hidden" name="action" value="vms_tours_reset_my_state" />';
			wp_nonce_field('bvmgr_tours_reset_my_state');
			submit_button(__('Reset my tour progress', 'backstage-venue-manager'), 'secondary', 'submit', false);
			echo '</form>';

			echo '<div data-vms-tour="guided-tours.registry">';
			$this->render_registry_table();
			echo '</div>';

			echo '</div>';
		}

		private function get_settings_value(string $key)
		{
			$settings = $this->storage->get_site_settings();
			return $settings[$key] ?? null;
		}

		public function render_checkbox_global_enabled(): void
		{
			$this->render_checkbox_field('global_enabled', !empty($this->get_settings_value('global_enabled')));
		}

		public function render_select_default_level(): void
		{
			$level = sanitize_key((string) $this->get_settings_value('default_level'));
			if (!in_array($level, array('beginner', 'standard', 'advanced'), true)) {
				$level = 'beginner';
			}

			echo '<select name="' . esc_attr(BVMGR_Tours_Storage::OPTION_SETTINGS) . '[default_level]">';
			echo '<option value="beginner"' . selected($level, 'beginner', false) . '>' . esc_html__('Beginner', 'backstage-venue-manager') . '</option>';
			echo '<option value="standard"' . selected($level, 'standard', false) . '>' . esc_html__('Standard', 'backstage-venue-manager') . '</option>';
			echo '<option value="advanced"' . selected($level, 'advanced', false) . '>' . esc_html__('Advanced', 'backstage-venue-manager') . '</option>';
			echo '</select>';
		}

		public function render_checkbox_auto_run_default(): void
		{
			$this->render_checkbox_field('auto_run_default', !empty($this->get_settings_value('auto_run_default')));
		}

		public function render_number_auto_run_delay(): void
		{
			$value = (int) $this->get_settings_value('auto_run_delay_ms');
			echo '<input type="number" min="0" step="10" name="' . esc_attr(BVMGR_Tours_Storage::OPTION_SETTINGS) . '[auto_run_delay_ms]" value="' . esc_attr((string) $value) . '" />';
		}

		public function render_number_max_auto_run(): void
		{
			$value = (int) $this->get_settings_value('max_auto_run_per_page_load');
			echo '<input type="number" min="1" max="5" step="1" name="' . esc_attr(BVMGR_Tours_Storage::OPTION_SETTINGS) . '[max_auto_run_per_page_load]" value="' . esc_attr((string) $value) . '" />';
		}

		public function render_checkbox_help_button(): void
		{
			$this->render_checkbox_field('help_button_enabled', !empty($this->get_settings_value('help_button_enabled')));
		}

		public function render_checkbox_debug_log(): void
		{
			$this->render_checkbox_field('debug_log_enabled', !empty($this->get_settings_value('debug_log_enabled')));
		}

		private function render_checkbox_field(string $key, bool $checked): void
		{
			echo '<label><input type="checkbox" name="' . esc_attr(BVMGR_Tours_Storage::OPTION_SETTINGS) . '[' . esc_attr($key) . ']" value="1"' . checked($checked, true, false) . '> ' . esc_html__('Enabled', 'backstage-venue-manager') . '</label>';
		}
	}
}
