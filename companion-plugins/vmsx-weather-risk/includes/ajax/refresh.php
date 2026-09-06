<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Ajax_Refresh')) {
	class VMSX_Weather_Risk_Ajax_Refresh {
		public static function init(): void
		{
			add_action('wp_ajax_' . VMSX_WR_AJAX_REFRESH, array(__CLASS__, 'handle'));
		}

		public static function handle(): void
		{
			if (!check_ajax_referer('vmsx_weather_risk_refresh', 'nonce', false)) {
				wp_send_json_error(array('message' => __('Security check failed.', 'vmsx-weather-risk')), 403);
			}

			$event_plan_id = isset($_POST['event_plan_id']) ? absint($_POST['event_plan_id']) : 0;
			if ($event_plan_id <= 0) {
				wp_send_json_error(array('message' => __('Event Plan is required.', 'vmsx-weather-risk')), 400);
			}
			if (!VMSX_Weather_Risk_Capabilities::can_refresh_event($event_plan_id)) {
				wp_send_json_error(array('message' => __('You cannot refresh this Event Plan.', 'vmsx-weather-risk')), 403);
			}

			VMSX_Weather_Risk_Logging::write(
				'Manual Show Risk refresh requested.',
				array('event_plan_id' => $event_plan_id, 'source' => 'ajax'),
				'info'
			);

			$snapshot = VMSX_Weather_Risk_Advisory_Engine::refresh_snapshot($event_plan_id, true, 'manual_ajax');
			$card_html = VMSX_Weather_Risk_Admin_Event_Plan::get_card_markup($event_plan_id, $snapshot);

			wp_send_json_success(array(
				'message' => __('Show Risk refreshed.', 'vmsx-weather-risk'),
				'card_html' => $card_html,
				'detail_url' => VMSX_Weather_Risk_Admin_Menu::details_url($event_plan_id),
				'snapshot' => array(
					'advisory' => (array) ($snapshot['advisory'] ?? array()),
					'computed_at_local' => (string) ($snapshot['computed_at_local'] ?? ''),
				),
			));
		}
	}
}
