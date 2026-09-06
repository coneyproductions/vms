<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Page_Event_Risk')) {
	class VMSX_Weather_Risk_Page_Event_Risk {
		public static function render(): void
		{
			if (!VMSX_Weather_Risk_Capabilities::can_manage_settings()) {
				return;
			}

			$event_plan_id = isset($_GET['event_plan_id']) ? absint($_GET['event_plan_id']) : 0;
			$settings = VMSX_Weather_Risk_Settings::get();
			$event = $event_plan_id > 0 ? VMSX_Weather_Risk_Helpers::get_event_summary($event_plan_id) : array();
			$actions_html = '<a class="button" href="' . esc_url(VMSX_Weather_Risk_Admin_Menu::settings_url()) . '">' . esc_html__('Settings', 'vmsx-weather-risk') . '</a>';

			$render_content = static function () use ($event_plan_id, $settings, $event): void {
				self::render_event_picker($event_plan_id);

				if ($event_plan_id <= 0) {
					return;
				}

				if (!VMSX_Weather_Risk_Capabilities::can_view_event($event_plan_id)) {
					echo '<div class="notice notice-error"><p>' . esc_html__('You do not have access to that Event Plan.', 'vmsx-weather-risk') . '</p></div>';
					return;
				}

				$snapshot = VMSX_Weather_Risk_Advisory_Engine::get_snapshot($event_plan_id);
				if (!is_array($snapshot)) {
					$snapshot = VMSX_Weather_Risk_Advisory_Engine::refresh_snapshot($event_plan_id, false, 'details_initial');
				}

				$event_summary = isset($snapshot['event']) && is_array($snapshot['event']) ? $snapshot['event'] : $event;
				$warnings = isset($snapshot['warnings']) && is_array($snapshot['warnings']) ? $snapshot['warnings'] : array();
				$provider_details_open = !empty($settings['show_provider_details_by_default']);

				echo '<div class="vmsx-weather-risk__page-head">';
				echo '<div>';
				echo '<h2>' . esc_html(VMSX_Weather_Risk_Helpers::clean_display_text((string) ($event_summary['title'] ?? ('Event Plan #' . $event_plan_id)))) . '</h2>';
				$meta_parts = array_filter(array(
					(string) ($event_summary['date'] ?? ''),
					(string) ($event_summary['time_label'] ?? ''),
					(string) ($event_summary['venue_name'] ?? ''),
				));
				if (!empty($meta_parts)) {
					echo '<p class="description">' . esc_html(VMSX_Weather_Risk_Helpers::clean_display_text(implode(' · ', $meta_parts))) . '</p>';
				}
				echo '</div>';
				echo '<div class="vmsx-weather-risk__page-actions">';
				echo '<button type="button" class="button button-primary" data-vmsx-weather-refresh="1" data-vmsx-weather-refresh-page="details" data-event-plan-id="' . esc_attr((string) $event_plan_id) . '">' . esc_html__('Refresh Show Risk', 'vmsx-weather-risk') . '</button> ';
				echo '<a class="button" href="' . esc_url((string) ($event_summary['edit_url'] ?? admin_url('post.php?post=' . $event_plan_id . '&action=edit'))) . '">' . esc_html__('Edit Event Plan', 'vmsx-weather-risk') . '</a> ';
				echo '<a class="button" href="' . esc_url(VMSX_Weather_Risk_Admin_Menu::settings_url()) . '">' . esc_html__('Settings', 'vmsx-weather-risk') . '</a>';
				echo '</div>';
				echo '</div>';

				self::render_setup_notices($snapshot);

				if (!empty($warnings)) {
					echo '<div class="notice notice-warning">';
					foreach ($warnings as $warning) {
						echo '<p>' . esc_html((string) $warning) . '</p>';
					}
					echo '</div>';
				}

				self::render_final_cards($snapshot);
				self::render_provider_breakdown($snapshot, $provider_details_open);
				self::render_ticket_and_dt($snapshot);
				self::render_reasoning($snapshot);
				self::render_logs($event_plan_id);
			};

			$render_shell = VMSX_Weather_Risk_Compatibility::core_function('vms_admin_ui_render_shell');
			if ($render_shell !== '') {
				$title = __('Show Risk Advisor', 'vmsx-weather-risk');
				$subtitle = __('Operator-facing weather advisory details for one Event Plan at a time.', 'vmsx-weather-risk');
				if (!empty($event['title'])) {
					$title = VMSX_Weather_Risk_Helpers::clean_display_text((string) $event['title']);
					$subtitle_parts = array_filter(array(
						__('Show Risk Advisor', 'vmsx-weather-risk'),
						(string) ($event['date'] ?? ''),
						(string) ($event['time_label'] ?? ''),
						(string) ($event['venue_name'] ?? ''),
					));
					$subtitle = VMSX_Weather_Risk_Helpers::clean_display_text(implode(' · ', $subtitle_parts));
				}

				$render_shell(
					array(
						'title' => $title,
						'subtitle' => $subtitle,
						'actions_html' => $actions_html,
						'shell_id' => 'vms-weather-risk',
						'content_class' => 'vmsx-weather-risk-admin',
					),
					$render_content
				);
				return;
			}

			echo '<div class="wrap vmsx-weather-risk-admin">';
			echo '<h1>' . esc_html__('Show Risk Advisor', 'vmsx-weather-risk') . '</h1>';
			echo '<p class="description">' . esc_html__('Operator-facing weather advisory details for one Event Plan at a time.', 'vmsx-weather-risk') . '</p>';
			$render_content();
			echo '</div>';
		}

		private static function render_event_picker(int $current_event_plan_id): void
		{
			$options = VMSX_Weather_Risk_Helpers::recent_event_plan_options(30);
			echo '<form method="get" class="vmsx-weather-risk__picker">';
			echo '<input type="hidden" name="page" value="' . esc_attr(VMSX_Weather_Risk_Admin_Menu::DETAILS_SLUG) . '">';
			echo '<label for="vmsx-weather-risk-event-plan"><strong>' . esc_html__('Event Plan', 'vmsx-weather-risk') . '</strong></label> ';
			echo '<select id="vmsx-weather-risk-event-plan" name="event_plan_id">';
			echo '<option value="0">' . esc_html__('Select an Event Plan', 'vmsx-weather-risk') . '</option>';
			foreach ($options as $option) {
				echo '<option value="' . esc_attr((string) ($option['id'] ?? 0)) . '" ' . selected($current_event_plan_id, (int) ($option['id'] ?? 0), false) . '>' . esc_html((string) ($option['label'] ?? '')) . '</option>';
			}
			echo '</select> ';
			submit_button(__('Open', 'vmsx-weather-risk'), 'secondary', '', false);
			echo '</form>';
		}

		private static function render_setup_notices(array $snapshot): void
		{
			$window = isset($snapshot['window']) && is_array($snapshot['window']) ? $snapshot['window'] : array();
			$location = isset($snapshot['location']) && is_array($snapshot['location']) ? $snapshot['location'] : array();
			$provider_health = isset($snapshot['provider_health']) && is_array($snapshot['provider_health']) ? $snapshot['provider_health'] : array();
			$notices = array();

			if (!empty($window['window_end_utc']) && (int) $window['window_end_utc'] < time()) {
				$notices[] = array(
					'type' => 'notice-info',
					'messages' => array(__('This event window is in the past. Forecast-based Show Risk works best for upcoming events. Historical weather support is not included yet.', 'vmsx-weather-risk')),
				);
			}

			if (empty($location['latitude']) || empty($location['longitude'])) {
				$notices[] = array(
					'type' => 'notice-warning',
					'messages' => array(__('This event still does not have usable coordinates. Save a complete venue address or add fallback coordinates in Show Risk Settings.', 'vmsx-weather-risk')),
				);
			}

			$expected = (int) ($provider_health['expected'] ?? 0);
			$responded = (int) ($provider_health['responded'] ?? 0);
			$skipped = (int) ($provider_health['skipped'] ?? 0);
			if ($expected === 0) {
				$notices[] = array(
					'type' => 'notice-error',
					'messages' => array(__('No weather providers are fully active right now. Open-Meteo and NOAA can work without paid API keys once coordinates are available. OpenWeather and WeatherAPI require saved API keys.', 'vmsx-weather-risk')),
				);
			} elseif ($expected < 2 || $skipped > 0) {
				$notices[] = array(
					'type' => 'notice-info',
					'messages' => array(__('Only part of the multi-source setup is active right now. Open-Meteo plus NOAA is the recommended free baseline, and OpenWeather/WeatherAPI can be added for more coverage.', 'vmsx-weather-risk')),
				);
			}
			if ($expected > 0 && $responded === 0) {
				$notices[] = array(
					'type' => 'notice-warning',
					'messages' => array(__('No active provider returned a usable forecast for this event window yet. Check the provider rows below for the exact reason.', 'vmsx-weather-risk')),
				);
			}

			foreach ($notices as $notice) {
				echo '<div class="notice ' . esc_attr((string) ($notice['type'] ?? 'notice-info')) . '">';
				foreach ((array) ($notice['messages'] ?? array()) as $message) {
					echo '<p>' . esc_html((string) $message) . '</p>';
				}
				echo '</div>';
			}
		}

		private static function render_final_cards(array $snapshot): void
		{
			$advisory = isset($snapshot['advisory']) && is_array($snapshot['advisory']) ? $snapshot['advisory'] : array();
			$weather = isset($snapshot['weather_risk']) && is_array($snapshot['weather_risk']) ? $snapshot['weather_risk'] : array();
			$sales = isset($snapshot['sales_risk']) && is_array($snapshot['sales_risk']) ? $snapshot['sales_risk'] : array();
			$financial = isset($snapshot['financial_exposure']) && is_array($snapshot['financial_exposure']) ? $snapshot['financial_exposure'] : array();
			$provider_health = isset($snapshot['provider_health']) && is_array($snapshot['provider_health']) ? $snapshot['provider_health'] : array();

			echo '<div class="vmsx-weather-risk__card-grid">';
			echo '<section class="vmsx-weather-risk__panel vmsx-weather-risk__panel--hero">';
			echo '<div class="vmsx-weather-risk__panel-label">' . esc_html__('Final advisory', 'vmsx-weather-risk') . '</div>';
			echo '<div class="vmsx-weather-risk__hero-line"><span class="vmsx-weather-risk__pill ' . esc_attr(VMSX_Weather_Risk_Helpers::band_css_class((string) ($advisory['label'] ?? 'monitor'))) . '">' . esc_html((string) ($advisory['label'] ?? __('Monitor Closely', 'vmsx-weather-risk'))) . '</span>';
			echo '<strong>' . esc_html(sprintf(__('Score %d', 'vmsx-weather-risk'), (int) ($advisory['score'] ?? 0))) . '</strong></div>';
			echo '<p class="description">' . esc_html((string) ($snapshot['decision_checkpoint'] ?? '')) . '</p>';
			echo '<div class="vmsx-weather-risk__metric-list">';
			echo '<div><span>' . esc_html__('Confidence', 'vmsx-weather-risk') . '</span><strong>' . esc_html((string) ($snapshot['confidence_band'] ?? 'Low')) . '</strong></div>';
			echo '<div><span>' . esc_html__('Providers', 'vmsx-weather-risk') . '</span><strong>' . esc_html((string) ($provider_health['label'] ?? self::na_label())) . '</strong></div>';
			echo '<div><span>' . esc_html__('Updated', 'vmsx-weather-risk') . '</span><strong>' . esc_html((string) ($snapshot['computed_at_local'] ?? self::na_label())) . '</strong></div>';
			echo '</div>';
			echo '</section>';

			self::render_score_card(__('Weather risk', 'vmsx-weather-risk'), $weather);
			self::render_score_card(__('Sales risk', 'vmsx-weather-risk'), $sales);
			self::render_score_card(__('Financial exposure', 'vmsx-weather-risk'), $financial);
			echo '</div>';
		}

		private static function render_score_card(string $label, array $block): void
		{
			echo '<section class="vmsx-weather-risk__panel">';
			echo '<div class="vmsx-weather-risk__panel-label">' . esc_html($label) . '</div>';
			echo '<div class="vmsx-weather-risk__score-row"><strong>' . esc_html((string) (($block['score'] ?? 0))) . '</strong><span class="vmsx-weather-risk__pill ' . esc_attr(VMSX_Weather_Risk_Helpers::band_css_class((string) ($block['band'] ?? 'watch'))) . '">' . esc_html((string) ($block['band'] ?? 'Watch')) . '</span></div>';
			echo '</section>';
		}

		private static function render_provider_breakdown(array $snapshot, bool $open): void
		{
			$providers = isset($snapshot['providers']) && is_array($snapshot['providers']) ? $snapshot['providers'] : array();
			$mode = (string) ($snapshot['mode_used'] ?? 'conservative');
			echo '<section class="vmsx-weather-risk__panel">';
			echo '<div class="vmsx-weather-risk__panel-label">' . esc_html__('Provider breakdown', 'vmsx-weather-risk') . '</div>';
			echo '<p class="description">' . esc_html(sprintf(__('Provider mode: %s', 'vmsx-weather-risk'), ucfirst($mode))) . '</p>';

			foreach ($providers as $provider) {
				if (!is_array($provider)) {
					continue;
				}
				$title = (string) ($provider['provider_name'] ?? __('Provider', 'vmsx-weather-risk'));
				$status = (string) ($provider['status'] ?? 'unknown');
				echo '<details class="vmsx-weather-risk__provider" ' . ($open ? 'open' : '') . '>';
				echo '<summary><strong>' . esc_html($title) . '</strong><span class="vmsx-weather-risk__provider-status">' . esc_html(ucfirst($status)) . '</span></summary>';
				if ($status !== 'success') {
					echo '<p class="description">' . esc_html((string) ($provider['error_message'] ?? __('No data returned.', 'vmsx-weather-risk'))) . '</p>';
					echo '</details>';
					continue;
				}
				$summary = isset($provider['summary']) && is_array($provider['summary']) ? $provider['summary'] : array();
				echo '<div class="vmsx-weather-risk__provider-summary">';
				echo '<div><span>' . esc_html__('Max PoP', 'vmsx-weather-risk') . '</span><strong>' . esc_html((string) ((int) ($summary['window_precip_probability_max'] ?? 0))) . '%</strong></div>';
				echo '<div><span>' . esc_html__('Total precip', 'vmsx-weather-risk') . '</span><strong>' . esc_html(number_format((float) ($summary['window_precip_amount_total'] ?? 0), 2)) . '"</strong></div>';
				echo '<div><span>' . esc_html__('Max wind', 'vmsx-weather-risk') . '</span><strong>' . esc_html(number_format((float) ($summary['window_wind_max_mph'] ?? 0), 1)) . ' mph</strong></div>';
				echo '</div>';
				self::render_provider_hours((array) ($provider['hours'] ?? array()));
				echo '</details>';
			}

			echo '</section>';
		}

		private static function render_provider_hours(array $hours): void
		{
			if (empty($hours)) {
				echo '<p class="description">' . esc_html__('No event-window rows were returned for this provider.', 'vmsx-weather-risk') . '</p>';
				return;
			}

			echo '<table class="widefat striped vmsx-weather-risk__table"><thead><tr>';
			echo '<th>' . esc_html__('Time', 'vmsx-weather-risk') . '</th>';
			echo '<th>' . esc_html__('PoP', 'vmsx-weather-risk') . '</th>';
			echo '<th>' . esc_html__('Precip', 'vmsx-weather-risk') . '</th>';
			echo '<th>' . esc_html__('Wind', 'vmsx-weather-risk') . '</th>';
			echo '<th>' . esc_html__('Temp', 'vmsx-weather-risk') . '</th>';
			echo '<th>' . esc_html__('Flags', 'vmsx-weather-risk') . '</th>';
			echo '</tr></thead><tbody>';
			foreach ($hours as $row) {
				if (!is_array($row)) {
					continue;
				}
				$flags = array_filter(array(
					!empty($row['lightning_risk_flag']) ? __('Lightning', 'vmsx-weather-risk') : '',
					!empty($row['severe_flag']) ? __('Severe', 'vmsx-weather-risk') : '',
				));
				if (empty($flags) && !empty($row['summary'])) {
					$flags[] = sanitize_text_field((string) $row['summary']);
				}
				$time_label = (string) ($row['timestamp_local'] ?? '');
				if ($time_label === '' && !empty($row['timestamp_utc'])) {
					$time_label = VMSX_Weather_Risk_Helpers::format_local_timestamp((int) $row['timestamp_utc']);
				}
				echo '<tr>';
				echo '<td>' . esc_html($time_label !== '' ? $time_label : self::na_label()) . '</td>';
				echo '<td>' . esc_html((string) ((int) ($row['precip_probability'] ?? 0))) . '%</td>';
				echo '<td>' . esc_html(number_format((float) ($row['precip_amount'] ?? 0), 2)) . '"</td>';
				echo '<td>' . esc_html(number_format((float) ($row['wind_mph'] ?? 0), 1)) . ' mph</td>';
				echo '<td>' . esc_html(isset($row['temperature_f']) && $row['temperature_f'] !== null ? number_format((float) $row['temperature_f'], 1) . '°F' : self::na_label()) . '</td>';
				echo '<td>' . esc_html(!empty($flags) ? implode(', ', $flags) : self::na_label()) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		private static function render_ticket_and_dt(array $snapshot): void
		{
			$ticket = isset($snapshot['sales_context']) && is_array($snapshot['sales_context']) ? $snapshot['sales_context'] : array();
			$dt = isset($snapshot['dt_context']) && is_array($snapshot['dt_context']) ? $snapshot['dt_context'] : array();

			echo '<div class="vmsx-weather-risk__card-grid">';
			echo '<section class="vmsx-weather-risk__panel">';
			echo '<div class="vmsx-weather-risk__panel-label">' . esc_html__('Ticket snapshot', 'vmsx-weather-risk') . '</div>';
			echo '<div class="vmsx-weather-risk__metric-list">';
			echo '<div><span>' . esc_html__('Qty sold', 'vmsx-weather-risk') . '</span><strong>' . esc_html(number_format((int) ($ticket['sold_qty'] ?? 0))) . '</strong></div>';
			echo '<div><span>' . esc_html__('Gross', 'vmsx-weather-risk') . '</span><strong>' . esc_html(VMSX_Weather_Risk_Helpers::format_money_cents(isset($ticket['gross_cents']) ? (int) $ticket['gross_cents'] : null)) . '</strong></div>';
			echo '<div><span>' . esc_html__('Source', 'vmsx-weather-risk') . '</span><strong>' . esc_html((string) ($ticket['provider_label'] ?? self::na_label())) . '</strong></div>';
			echo '<div><span>' . esc_html__('Updated', 'vmsx-weather-risk') . '</span><strong>' . esc_html(!empty($ticket['computed_at_local']) ? (string) $ticket['computed_at_local'] : self::na_label()) . '</strong></div>';
			echo '</div>';
			echo '</section>';

			echo '<section class="vmsx-weather-risk__panel">';
			echo '<div class="vmsx-weather-risk__panel-label">' . esc_html__('DT pace / comparison', 'vmsx-weather-risk') . '</div>';
			if (empty($dt['available'])) {
				echo '<p class="description">' . esc_html((string) ($dt['note'] ?? __('DT enrichment is unavailable.', 'vmsx-weather-risk'))) . '</p>';
			} elseif (empty($dt['used'])) {
				echo '<p class="description">' . esc_html((string) ($dt['note'] ?? __('DT enrichment has limited data for this event.', 'vmsx-weather-risk'))) . '</p>';
			} else {
				echo '<div class="vmsx-weather-risk__metric-list">';
				echo '<div><span>' . esc_html__('Pace', 'vmsx-weather-risk') . '</span><strong>' . esc_html((string) ($dt['pace_label'] ?? self::na_label())) . '</strong></div>';
				echo '<div><span>' . esc_html__('Checkpoint', 'vmsx-weather-risk') . '</span><strong>' . esc_html(sprintf(__('%d days out', 'vmsx-weather-risk'), (int) ($dt['checkpoint_days_out'] ?? 0))) . '</strong></div>';
				echo '<div><span>' . esc_html__('Comparable qty', 'vmsx-weather-risk') . '</span><strong>' . esc_html(number_format((int) ($dt['avg_comparable_qty'] ?? 0))) . '</strong></div>';
				echo '<div><span>' . esc_html__('Projected finish', 'vmsx-weather-risk') . '</span><strong>' . esc_html(!empty($dt['projection']['projected_final_qty']) ? number_format((int) $dt['projection']['projected_final_qty']) : self::na_label()) . '</strong></div>';
				echo '</div>';
			}
			echo '</section>';
			echo '</div>';
		}

		private static function render_reasoning(array $snapshot): void
		{
			$reasons = isset($snapshot['reasons']) && is_array($snapshot['reasons']) ? $snapshot['reasons'] : array();
			echo '<section class="vmsx-weather-risk__panel">';
			echo '<div class="vmsx-weather-risk__panel-label">' . esc_html__('Advisory reasoning', 'vmsx-weather-risk') . '</div>';
			if (empty($reasons)) {
				echo '<p class="description">' . esc_html__('No explicit reasoning was recorded for this snapshot.', 'vmsx-weather-risk') . '</p>';
			} else {
				echo '<ul class="vmsx-weather-risk__reason-list">';
				foreach ($reasons as $reason) {
					echo '<li>' . esc_html((string) $reason) . '</li>';
				}
				echo '</ul>';
			}
			echo '</section>';
		}

		private static function render_logs(int $event_plan_id): void
		{
			$items = VMSX_Weather_Risk_Logging::recent(25, $event_plan_id);
			echo '<section class="vmsx-weather-risk__panel">';
			echo '<div class="vmsx-weather-risk__panel-label">' . esc_html__('Debug / log', 'vmsx-weather-risk') . '</div>';
			if (empty($items)) {
				echo '<p class="description">' . esc_html__('No log entries were recorded for this event yet.', 'vmsx-weather-risk') . '</p>';
			} else {
				echo '<table class="widefat striped vmsx-weather-risk__table"><thead><tr><th>' . esc_html__('Time', 'vmsx-weather-risk') . '</th><th>' . esc_html__('Level', 'vmsx-weather-risk') . '</th><th>' . esc_html__('Message', 'vmsx-weather-risk') . '</th></tr></thead><tbody>';
				foreach ($items as $item) {
					$ts = isset($item['timestamp']) ? (int) $item['timestamp'] : 0;
					$context = isset($item['context']) && is_array($item['context']) ? $item['context'] : array();
					$context_line = VMSX_Weather_Risk_Helpers::format_log_context($context);
					echo '<tr>';
					echo '<td>' . esc_html(VMSX_Weather_Risk_Helpers::format_local_timestamp($ts)) . '</td>';
					echo '<td>' . esc_html((string) ($item['level'] ?? 'info')) . '</td>';
					echo '<td>';
					echo '<div>' . esc_html(VMSX_Weather_Risk_Helpers::clean_display_text((string) ($item['message'] ?? ''))) . '</div>';
					if ($context_line !== '') {
						echo '<div class="description">' . esc_html($context_line) . '</div>';
					}
					echo '</td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			}
			echo '</section>';
		}

		private static function na_label(): string
		{
			return __('N/A', 'vmsx-weather-risk');
		}
	}
}
