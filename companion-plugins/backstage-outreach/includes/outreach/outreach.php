<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/suppression.php';
require_once __DIR__ . '/contacts.php';
require_once __DIR__ . '/admin-ui.php';

if (!function_exists('vms_outreach_module_boot')) {
	function vms_outreach_module_boot(): void
	{
		if (function_exists('vms_register_module')) {
			vms_register_module(array(
				'slug' => 'outreach',
				'name' => 'Outreach',
				'version' => '1.0.0',
				'premium' => false,
				'description' => 'Reusable outreach campaigns with Guest Pass invitations live now and future-purpose slots ready.',
				'source' => 'backstage-outreach',
			));
		}

		if (function_exists('vms_outreach_maybe_upgrade_schema')) {
			vms_outreach_maybe_upgrade_schema();
		}
	}
}
add_action('plugins_loaded', 'vms_outreach_module_boot', 8);

if (!function_exists('vms_outreach_register_admin_page_metadata')) {
	function vms_outreach_register_admin_page_metadata(): void
	{
		if (!function_exists('vms_register_admin_page') || !function_exists('vms_outreach_admin_menu_slug')) {
			return;
		}

		vms_register_admin_page(array(
			'id' => 'vms-outreach',
			'slug' => vms_outreach_admin_menu_slug(),
			'page_title' => __('Outreach', 'backstage-outreach'),
			'menu_title' => __('Outreach', 'backstage-outreach'),
			'capability' => function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options',
			'section' => 'marketing_sales',
			'order' => 15,
			'source' => 'backstage-outreach',
			'description' => __('Reusable outreach campaigns. Guest Pass invitations are live now; future purposes can expand from the same engine.', 'backstage-outreach'),
			'left_menu' => true,
			'callback' => 'vms_outreach_render_admin_page',
			'shell' => true,
			'register' => true,
		));
	}
}
add_action('vms_admin_register_pages', 'vms_outreach_register_admin_page_metadata', 15);

if (!function_exists('vms_outreach_redirect_legacy_guest_pass_tab')) {
	function vms_outreach_redirect_legacy_guest_pass_tab(): void
	{
		if (!is_admin() || !function_exists('vms_pass_claims_menu_slug') || !function_exists('vms_pass_outreach_tab_slug')) {
			return;
		}

		$page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
		$tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
		if ($page !== vms_pass_claims_menu_slug() || $tab !== vms_pass_outreach_tab_slug()) {
			return;
		}

		$args = array();
		foreach (array('campaign_id', 'recipient_id', 'recipient_search', 'recipient_status', 'recipient_group_label', 'campaign_status', 'campaign_purpose', 'delivery_notice', 'delivery_affected', 'delivery_skipped', 'delivery_sent', 'delivery_failed') as $key) {
			if (!isset($_GET[$key])) {
				continue;
			}
			$value = wp_unslash($_GET[$key]);
			if (is_array($value)) {
				continue;
			}
			$args[$key] = sanitize_text_field((string) $value);
		}

		wp_safe_redirect(vms_pass_outreach_admin_page_url($args));
		exit;
	}
}
add_action('admin_init', 'vms_outreach_redirect_legacy_guest_pass_tab', 5);

if (!function_exists('vms_outreach_render_admin_page')) {
	function vms_outreach_render_admin_page(): void
	{
		$capability = function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options';
		if (!current_user_can($capability)) {
			wp_die(esc_html__('You do not have permission to manage Outreach.', 'backstage-outreach'));
		}

		$content = static function (): void {
			if (function_exists('vms_pass_claims_render_admin_notices')) {
				vms_pass_claims_render_admin_notices();
			}
			if (function_exists('vms_outreach_render_admin_screen')) {
				vms_outreach_render_admin_screen();
				return;
			}
			echo '<div class="notice notice-error"><p>' . esc_html__('The Outreach renderer is unavailable.', 'backstage-outreach') . '</p></div>';
		};

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(
				array(
					'title' => __('Outreach', 'backstage-outreach'),
					'subtitle' => __('Reusable campaign workflow for imports, mapping, preview, delivery prep, and audience tracking. Customer sending stays in MailPoet.', 'backstage-outreach'),
					'actions_html' => '',
					'shell_id' => 'vms-pass-claims-wrap',
				),
				$content
			);
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Outreach', 'backstage-outreach') . '</h1>';
		$content();
		echo '</div>';
	}
}
