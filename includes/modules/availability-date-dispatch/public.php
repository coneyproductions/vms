<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_add_dispatch_register_rewrite')) {
	function vms_add_dispatch_register_rewrite(): void
	{
		add_rewrite_tag('%vms_add_dispatch_token%', '([^&]+)');
		add_rewrite_rule('^availability-dispatch/respond/([^/]+)/?$', 'index.php?vms_add_dispatch_token=$matches[1]', 'top');
	}
}
add_action('init', 'vms_add_dispatch_register_rewrite', 30);

if (!function_exists('vms_add_dispatch_maybe_flush_rewrites')) {
	function vms_add_dispatch_maybe_flush_rewrites(): void
	{
		$key = 'vms_rewrite_flushed_add_dispatch_v1';
		if (get_option($key, '') === '1') {
			return;
		}

		flush_rewrite_rules(false);
		update_option($key, '1', false);
	}
}
add_action('admin_init', 'vms_add_dispatch_maybe_flush_rewrites', 30);

if (!function_exists('vms_add_dispatch_public_response_allowed_html')) {
	function vms_add_dispatch_public_response_allowed_html(): array
	{
		return array(
			'a' => array(
				'class' => true,
				'href' => true,
			),
			'br' => array(),
			'div' => array(
				'class' => true,
			),
			'h1' => array(),
			'p' => array(
				'class' => true,
			),
			'strong' => array(),
		);
	}
}

if (!function_exists('vms_add_dispatch_public_shell_stylesheet_url')) {
	function vms_add_dispatch_public_shell_stylesheet_url(): string
	{
		if (!defined('VMS_PLUGIN_URL') || !is_string(VMS_PLUGIN_URL) || VMS_PLUGIN_URL === '') {
			return '';
		}

		$stylesheet_url = VMS_PLUGIN_URL . 'assets/css/vms-add-dispatch-public-shell.css';
		$version = function_exists('vms_asset_version') ? trim((string) vms_asset_version()) : '';
		if ($version === '' && defined('VMS_VERSION')) {
			$version = (string) VMS_VERSION;
		}
		if ($version !== '') {
			$stylesheet_url = add_query_arg('ver', $version, $stylesheet_url);
		}

		return (string) $stylesheet_url;
	}
}

if (!function_exists('vms_add_dispatch_render_public_shell')) {
	function vms_add_dispatch_render_public_shell(string $headline, string $content_html): void
	{
		$stylesheet_url = vms_add_dispatch_public_shell_stylesheet_url();

		status_header(200);
		nocache_headers();
		echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html($headline) . '</title>';
		if ($stylesheet_url !== '') {
			echo '<link rel="stylesheet" href="' . esc_url($stylesheet_url) . '">';
		}
		echo '</head><body><div class="vms-add-public"><div class="vms-add-card">';
		echo wp_kses($content_html, vms_add_dispatch_public_response_allowed_html());
		echo '</div></div></body></html>';
		exit;
	}
}

if (!function_exists('vms_add_dispatch_render_public_response')) {
	function vms_add_dispatch_render_public_response(string $raw_token): void
	{
		$response = vms_add_dispatch_find_response_by_raw_token($raw_token);
		if (!$response) {
			vms_add_dispatch_render_public_shell(
				__('Availability Response', 'backstage-venue-manager'),
				'<h1>' . esc_html__('Request Not Found', 'backstage-venue-manager') . '</h1><p class="vms-add-error">' . esc_html__('This availability link is invalid or can no longer be verified.', 'backstage-venue-manager') . '</p>'
			);
		}

		$request = vms_add_dispatch_get_request((int) ($response['request_id'] ?? 0));
		$context = vms_add_dispatch_get_event_plan_context((int) ($response['event_plan_id'] ?? 0));
		if (!$request || !$context) {
			vms_add_dispatch_render_public_shell(
				__('Availability Response', 'backstage-venue-manager'),
				'<h1>' . esc_html__('Request Unavailable', 'backstage-venue-manager') . '</h1><p class="vms-add-error">' . esc_html__('This request is no longer available.', 'backstage-venue-manager') . '</p>'
			);
		}

		if (sanitize_key((string) ($request['status'] ?? '')) !== 'active') {
			vms_add_dispatch_render_public_shell(
				__('Availability Response', 'backstage-venue-manager'),
				'<h1>' . esc_html__('Request Closed', 'backstage-venue-manager') . '</h1><p class="vms-add-note">' . esc_html__('This availability request has already been closed by the operator.', 'backstage-venue-manager') . '</p>'
			);
		}

		if (vms_add_dispatch_response_expired($response)) {
			vms_add_dispatch_render_public_shell(
				__('Availability Response', 'backstage-venue-manager'),
				'<h1>' . esc_html__('Link Expired', 'backstage-venue-manager') . '</h1><p class="vms-add-error">' . esc_html__('This availability link has expired. Please contact the operator if you still want to respond.', 'backstage-venue-manager') . '</p>'
			);
		}

		$choice = vms_add_dispatch_get_request_choice();
		$current_status = sanitize_key((string) ($response['response_status'] ?? 'requested'));
		$html = '<h1>' . esc_html__('Availability Request', 'backstage-venue-manager') . '</h1>';
		$html .= '<div class="vms-add-meta">';
		$html .= '<div><strong>' . esc_html__('Event:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) ($context['event_title'] ?? '')) . '</div>';
		$html .= '<div><strong>' . esc_html__('Date:', 'backstage-venue-manager') . '</strong> ' . esc_html(vms_add_dispatch_format_date((string) ($context['event_date'] ?? ''))) . '</div>';
		if (!empty($context['venue_name'])) {
			$html .= '<div><strong>' . esc_html__('Venue:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) $context['venue_name']) . '</div>';
		}
		$html .= '</div>';

		if ($choice !== '') {
			$result = vms_add_dispatch_record_public_response($response, $choice, 'email');
			if (is_wp_error($result)) {
				$html .= '<p class="vms-add-error">' . esc_html($result->get_error_message()) . '</p>';
			} else {
				$final_status = sanitize_key((string) ($result['status'] ?? $choice));
				$already = !empty($result['already_recorded']);
				$label = $final_status === 'available' ? __('Available', 'backstage-venue-manager') : __('Unavailable', 'backstage-venue-manager');
				$message = $already
					/* translators: %s: human-readable value used in this message. */
					? sprintf(__('Your response was already recorded as %s.', 'backstage-venue-manager'), $label)
					/* translators: %s: human-readable value used in this message. */
					: sprintf(__('Your response has been recorded as %s.', 'backstage-venue-manager'), $label);
				$html .= '<p class="vms-add-success">' . esc_html($message) . '</p>';
				$html .= '<p class="vms-add-note">' . esc_html__('Thank you. The operator can now use your response in Backstage Venue Manager availability and Event Plan staffing decisions.', 'backstage-venue-manager') . '</p>';
				vms_add_dispatch_render_public_shell(__('Availability Response Recorded', 'backstage-venue-manager'), $html);
			}
		}

		if (in_array($current_status, array('available', 'unavailable'), true)) {
			$label = $current_status === 'available' ? __('Available', 'backstage-venue-manager') : __('Unavailable', 'backstage-venue-manager');
			/* translators: %s: human-readable value used in this message. */
			$html .= '<p class="vms-add-success">' . esc_html(sprintf(__('Your response is already recorded as %s.', 'backstage-venue-manager'), $label)) . '</p>';
			vms_add_dispatch_render_public_shell(__('Availability Response', 'backstage-venue-manager'), $html);
		}

		if (!empty($request['message'])) {
			$html .= '<p class="vms-add-note">' . nl2br(esc_html((string) $request['message'])) . '</p>';
		}
		$html .= '<p>' . esc_html__('Choose the option below that best matches your availability for this date.', 'backstage-venue-manager') . '</p>';
		$html .= '<div class="vms-add-actions">';
		$html .= '<a class="vms-add-btn vms-add-btn--yes" href="' . esc_url(vms_add_dispatch_build_response_url($response, 'available')) . '">' . esc_html__('YES - I am available', 'backstage-venue-manager') . '</a>';
		$html .= '<a class="vms-add-btn vms-add-btn--no" href="' . esc_url(vms_add_dispatch_build_response_url($response, 'unavailable')) . '">' . esc_html__('NO - I am not available', 'backstage-venue-manager') . '</a>';
		$html .= '</div>';

		vms_add_dispatch_render_public_shell(__('Availability Response', 'backstage-venue-manager'), $html);
	}
}

if (!function_exists('vms_add_dispatch_template_router')) {
	function vms_add_dispatch_template_router(): void
	{
		if (is_admin()) {
			return;
		}

		$token = vms_add_dispatch_get_request_token();
		if ($token === '') {
			return;
		}

		vms_add_dispatch_render_public_response($token);
	}
}
add_action('template_redirect', 'vms_add_dispatch_template_router', 0);
