<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Tours_Maintenance')) {
	class VMS_Tours_Maintenance
	{
		public static function init(): void
		{
			add_action('admin_menu', array(__CLASS__, 'register_menu'), 40);
		}

		public static function register_menu(): void
		{
			add_submenu_page(
				'vms-dashboard',
				__('Tour Maintenance', 'vms'),
				__('Tour Maintenance', 'vms'),
				'manage_options',
				'vms-tour-maintenance',
				array(__CLASS__, 'render_page')
			);
		}

		public static function render_page(): void
		{
			if (!current_user_can('manage_options')) {
				wp_die('Insufficient permissions.');
			}

			if (function_exists('vms_admin_ui_render_shell')) {
				vms_admin_ui_render_shell(
					array(
						'title' => __('Tour Maintenance', 'vms'),
						'shell_id' => 'vms-tour-maintenance',
					),
					array(__CLASS__, 'render_page_content')
				);
				return;
			}

			echo '<div class="wrap" id="vms-tour-maintenance">';
			echo '<h1>Tour Maintenance</h1>';
			self::render_page_content();
			echo '</div>';
		}

		public static function render_page_content(): void
		{
			echo '<div data-vms-tour="tour-maintenance-root">';
			echo '<p>Run authoritative drift scans and copy report JSON for Codex.</p>';
			echo '<div class="vms-tours-maintenance-actions">';
			echo '<button type="button" class="button button-primary" data-vms-tour-scan-now data-vms-tour="maintenance-scan-now">Run Scan Now</button> ';
			echo '<button type="button" class="button" data-vms-tour-copy-report>Copy report for Codex</button> ';
			echo '<button type="button" class="button button-secondary" data-vms-tour-start="vms-welcome-dashboard">Start Welcome Tour</button>';
			echo '</div>';
			echo '<div id="vms-tours-scan-status" class="vms-tours-maintenance-status" aria-live="polite"></div>';
			echo '<h2>Latest Drift Report</h2>';
			echo '<pre id="vms-tours-report-json" class="vms-tours-report-json">Loading…</pre>';
			echo '</div>';
		}
	}
}

VMS_Tours_Maintenance::init();
