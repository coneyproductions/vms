<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_social_platform_character_limit')) {
	function vms_social_platform_character_limit(string $platform): int
	{
		$platform = sanitize_key($platform);
		$limits = array(
			'facebook' => 63206,
			'instagram' => 2200,
			'linkedin' => 3000,
			'x' => 280,
			'mock' => 10000,
			'webhook' => 10000,
			'meta' => 63206,
		);
		$default = 4000;
		$limit = isset($limits[$platform]) ? (int) $limits[$platform] : $default;
		$limit = (int) apply_filters('vms_social_platform_character_limit', $limit, $platform);
		return max(50, $limit);
	}
}

if (!function_exists('vms_social_build_campaign_slug')) {
	function vms_social_build_campaign_slug(array $context): string
	{
		$venue = sanitize_title((string) ($context['venue_name'] ?? 'venue'));
		$date = preg_replace('/[^0-9]/', '', (string) ($context['event_date_raw'] ?? ''));
		if ($date === '') {
			$date = gmdate('Ymd');
		}
		if ($venue === '') {
			$venue = 'venue';
		}
		return $venue . '-' . $date;
	}
}

if (!function_exists('vms_social_append_utm')) {
	function vms_social_append_utm(string $url, string $source, array $context = array()): string
	{
		$url = esc_url_raw($url);
		if ($url === '') {
			return '';
		}

		$source = sanitize_key($source);
		if ($source === '') {
			$source = 'social';
		}

		$campaign = vms_social_build_campaign_slug($context);
		return add_query_arg(
			array(
				'utm_source' => $source,
				'utm_medium' => 'social',
				'utm_campaign' => $campaign,
			),
			$url
		);
	}
}

if (!function_exists('vms_social_template_token_map')) {
	/**
	 * @param array<string,mixed> $context
	 * @return array<string,string>
	 */
	function vms_social_template_token_map(array $context): array
	{
		$ticket_url = (string) ($context['ticket_url'] ?? '');
		$event_url = (string) ($context['event_url'] ?? '');

		return array(
			'{event_title}' => (string) ($context['event_title'] ?? ''),
			'{event_date}' => (string) ($context['event_date'] ?? ''),
			'{start_time}' => (string) ($context['start_time'] ?? ''),
			'{end_time}' => (string) ($context['end_time'] ?? ''),
			'{venue_name}' => (string) ($context['venue_name'] ?? ''),
			'{venue_city}' => (string) ($context['venue_city'] ?? ''),
			'{venue_state}' => (string) ($context['venue_state'] ?? ''),
			'{ticket_url}' => $ticket_url,
			'{event_url}' => $event_url,
			'{performer_names}' => (string) ($context['performer_names'] ?? ''),
			'{price_text}' => (string) ($context['price_text'] ?? ''),
			'{hashtags}' => (string) ($context['hashtags'] ?? ''),
		);
	}
}

if (!function_exists('vms_social_render_template_body')) {
	function vms_social_render_template_body(string $body, array $context): string
	{
		$rendered = strtr($body, vms_social_template_token_map($context));
		return vms_social_normalize_whitespace((string) $rendered);
	}
}

if (!function_exists('vms_social_template_get')) {
	/**
	 * @return array<string,mixed>|null
	 */
	function vms_social_template_get(int $template_id): ?array
	{
		$template_id = absint($template_id);
		if ($template_id <= 0) {
			return null;
		}
		global $wpdb;
		$table = vms_social_table_templates();
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $template_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_social_template_default_for_platform')) {
	/**
	 * @return array<string,mixed>|null
	 */
	function vms_social_template_default_for_platform(string $platform): ?array
	{
		$platform = sanitize_key($platform);
		global $wpdb;
		$table = vms_social_table_templates();
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE platform = %s ORDER BY is_default DESC, id ASC LIMIT 1",
				$platform
			),
			ARRAY_A
		);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_social_template_for_platform')) {
	/**
	 * @return array<string,mixed>|null
	 */
	function vms_social_template_for_platform(string $platform, int $template_id = 0): ?array
	{
		$template_id = absint($template_id);
		if ($template_id > 0) {
			$tpl = vms_social_template_get($template_id);
			if (is_array($tpl)) {
				return $tpl;
			}
		}
		return vms_social_template_default_for_platform($platform);
	}
}

if (!function_exists('vms_social_render_template_payload')) {
	/**
	 * @param array<string,mixed> $context
	 * @return array<string,mixed>
	 */
	function vms_social_render_template_payload(string $platform, string $body, array $context, bool $utm_enabled = true): array
	{
		$platform = sanitize_key($platform);
		$caption = vms_social_render_template_body($body, $context);

		$base_url = (string) ($context['ticket_url'] ?? '');
		if ($base_url === '') {
			$base_url = (string) ($context['event_url'] ?? '');
		}

		$final_url = $base_url;
		if ($utm_enabled && $final_url !== '') {
			$utm_source = $platform;
			if ($utm_source === 'meta') {
				$utm_source = 'facebook';
			}
			$final_url = vms_social_append_utm($final_url, $utm_source, $context);
		}

		$limit = vms_social_platform_character_limit($platform);
		$length = function_exists('mb_strlen') ? mb_strlen($caption) : strlen($caption);
		$needs_review = $length > $limit;

		return array(
			'caption' => $caption,
			'base_url' => $base_url,
			'final_url' => $final_url,
			'length' => $length,
			'limit' => $limit,
			'needs_review' => $needs_review,
			'needs_review_reason' => $needs_review ? 'caption_too_long' : '',
		);
	}
}
