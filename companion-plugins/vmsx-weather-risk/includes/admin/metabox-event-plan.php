<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Admin_Event_Plan')) {
	class VMSX_Weather_Risk_Admin_Event_Plan {
		public static function init(): void
		{
			add_action('vms_event_plan_advanced_controls_after_intro', array(__CLASS__, 'render_card'), 10, 2);
		}

		public static function render_card($post, array $context = array()): void
		{
			unset($context);
			if (!$post instanceof WP_Post || $post->post_type !== 'vms_event_plan') {
				return;
			}
			if (!VMSX_Weather_Risk_Capabilities::can_view_event((int) $post->ID)) {
				return;
			}

			echo self::get_card_markup((int) $post->ID); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		public static function get_card_markup(int $event_plan_id, ?array $snapshot = null): string
		{
			$settings = VMSX_Weather_Risk_Settings::get();
			if (!$snapshot) {
				$snapshot = VMSX_Weather_Risk_Advisory_Engine::get_snapshot($event_plan_id);
			}
			$details_url = VMSX_Weather_Risk_Admin_Menu::details_url($event_plan_id);

			ob_start();
			echo '<section class="vmsx-weather-risk-card" data-vmsx-weather-risk-card="1" data-event-plan-id="' . esc_attr((string) $event_plan_id) . '">';
			echo '<div class="vmsx-weather-risk-card__head"><div><strong>' . esc_html__('Show Risk Advisor', 'vmsx-weather-risk') . '</strong><p class="description">' . esc_html__('Compact advisory summary for this Event Plan.', 'vmsx-weather-risk') . '</p></div>';
			if (!empty($settings['enabled'])) {
				echo '<span class="vmsx-weather-risk__pill vmsx-weather-risk__pill--enabled">' . esc_html__('Enabled', 'vmsx-weather-risk') . '</span>';
			} else {
				echo '<span class="vmsx-weather-risk__pill vmsx-weather-risk__pill--watch">' . esc_html__('Disabled in settings', 'vmsx-weather-risk') . '</span>';
			}
			echo '</div>';

			if (empty($settings['enabled'])) {
				echo '<p class="description">' . esc_html__('Show Risk Advisor is installed but disabled. Enable it in settings to start building snapshots for this event.', 'vmsx-weather-risk') . '</p>';
				echo '<p><a class="button" href="' . esc_url(VMSX_Weather_Risk_Admin_Menu::settings_url()) . '">' . esc_html__('Open Show Risk Settings', 'vmsx-weather-risk') . '</a></p>';
				echo '</section>';
				return (string) ob_get_clean();
			}

			if (!$snapshot) {
				echo '<p class="description">' . esc_html__('No advisory snapshot is cached yet. Refresh Show Risk to build the first event-window advisory.', 'vmsx-weather-risk') . '</p>';
				echo '<div class="vmsx-weather-risk-card__actions">';
				echo '<button type="button" class="button button-secondary" data-vmsx-weather-refresh="1" data-event-plan-id="' . esc_attr((string) $event_plan_id) . '">' . esc_html__('Refresh Show Risk', 'vmsx-weather-risk') . '</button> ';
				echo '<a class="button" href="' . esc_url($details_url) . '">' . esc_html__('View Show Risk Details', 'vmsx-weather-risk') . '</a>';
				echo '</div>';
				echo '<div class="vmsx-weather-risk-card__message" data-vmsx-weather-message></div>';
				echo '</section>';
				return (string) ob_get_clean();
			}

			$advisory = isset($snapshot['advisory']) && is_array($snapshot['advisory']) ? $snapshot['advisory'] : array();
			$weather = isset($snapshot['weather_risk']) && is_array($snapshot['weather_risk']) ? $snapshot['weather_risk'] : array();
			$sales = isset($snapshot['sales_risk']) && is_array($snapshot['sales_risk']) ? $snapshot['sales_risk'] : array();
			$provider_health = isset($snapshot['provider_health']) && is_array($snapshot['provider_health']) ? $snapshot['provider_health'] : array();

			echo '<div class="vmsx-weather-risk-card__summary">';
			echo '<div><span>' . esc_html__('Advisory', 'vmsx-weather-risk') . '</span><strong class="vmsx-weather-risk__pill ' . esc_attr(VMSX_Weather_Risk_Helpers::band_css_class((string) ($advisory['label'] ?? 'watch'))) . '">' . esc_html((string) ($advisory['label'] ?? __('Monitor Closely', 'vmsx-weather-risk'))) . '</strong></div>';
			echo '<div><span>' . esc_html__('Weather', 'vmsx-weather-risk') . '</span><strong>' . esc_html(sprintf(__('%s (%d)', 'vmsx-weather-risk'), (string) ($weather['band'] ?? 'Watch'), (int) ($weather['score'] ?? 0))) . '</strong></div>';
			echo '<div><span>' . esc_html__('Sales', 'vmsx-weather-risk') . '</span><strong>' . esc_html(sprintf(__('%s (%d)', 'vmsx-weather-risk'), (string) ($sales['band'] ?? 'Watch'), (int) ($sales['score'] ?? 0))) . '</strong></div>';
			echo '<div><span>' . esc_html__('Updated', 'vmsx-weather-risk') . '</span><strong>' . esc_html((string) ($snapshot['computed_at_local'] ?? '')) . '</strong></div>';
			echo '<div><span>' . esc_html__('Next decision', 'vmsx-weather-risk') . '</span><strong>' . esc_html((string) ($snapshot['decision_checkpoint'] ?? '')) . '</strong></div>';
			echo '<div><span>' . esc_html__('Providers', 'vmsx-weather-risk') . '</span><strong>' . esc_html((string) ($provider_health['label'] ?? '')) . '</strong></div>';
			echo '</div>';

			$warnings = isset($snapshot['warnings']) && is_array($snapshot['warnings']) ? $snapshot['warnings'] : array();
			if (!empty($warnings)) {
				echo '<p class="description">' . esc_html((string) reset($warnings)) . '</p>';
			}

			echo '<div class="vmsx-weather-risk-card__actions">';
			echo '<button type="button" class="button button-secondary" data-vmsx-weather-refresh="1" data-event-plan-id="' . esc_attr((string) $event_plan_id) . '">' . esc_html__('Refresh Show Risk', 'vmsx-weather-risk') . '</button> ';
			echo '<a class="button" href="' . esc_url($details_url) . '">' . esc_html__('View Show Risk Details', 'vmsx-weather-risk') . '</a>';
			echo '</div>';
			echo '<div class="vmsx-weather-risk-card__message" data-vmsx-weather-message></div>';
			echo '</section>';

			return (string) ob_get_clean();
		}
	}
}
