<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_social_event_platforms')) {
	/**
	 * @return array<int,string>
	 */
	function vms_social_event_platforms(): array
	{
		return array('facebook', 'linkedin', 'x');
	}
}

if (!function_exists('vms_social_event_panel_meta_key')) {
	function vms_social_event_panel_meta_key(string $field): string
	{
		$fallbacks = array(
			'do_not_post' => '_vms_social_do_not_post',
			'platform_overrides' => '_vms_social_platform_overrides',
			'template_overrides' => '_vms_social_template_overrides',
			'unpublished_after_post' => '_vms_social_unpublished_after_post',
			'status' => '_vms_event_plan_status',
		);
		$fallback = isset($fallbacks[$field]) ? $fallbacks[$field] : '_vms_social_' . sanitize_key($field);
		if (function_exists('vms_meta_key')) {
			$key = (string) vms_meta_key('event_plan', $field);
			if ($key !== '') {
				return $key;
			}
		}
		return $fallback;
	}
}

if (!function_exists('vms_social_post_value')) {
	function vms_social_post_value(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw unslashed POST values are sanitized or validated at the call site.
		if (!isset($_POST[$key]) || is_array($_POST[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw unslashed POST values are sanitized or validated at the call site.
		return (string) wp_unslash($_POST[$key]);
	}
}

if (!function_exists('vms_social_post_array')) {
	function vms_social_post_array(string $key): array
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw unslashed POST arrays are sanitized element-by-element at the call site.
		if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw unslashed POST arrays are sanitized element-by-element at the call site.
		return wp_unslash($_POST[$key]);
	}
}

if (!function_exists('vms_social_query_value')) {
	function vms_social_query_value(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin query values are sanitized by the caller.
		if (!isset($_GET[$key]) || is_array($_GET[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin query values are sanitized by the caller.
		return (string) wp_unslash($_GET[$key]);
	}
}

if (!function_exists('vms_social_event_meta_enabled_platforms')) {
	/**
	 * @return array<string,int>
	 */
	function vms_social_event_meta_enabled_platforms(int $event_plan_id): array
	{
		$key = vms_social_event_panel_meta_key('platform_overrides');
		$raw = get_post_meta($event_plan_id, $key, true);
		$stored = is_array($raw) ? $raw : array();
		$out = array();
		foreach (vms_social_event_platforms() as $platform) {
			$val = isset($stored[$platform]) ? (int) $stored[$platform] : 1;
			$out[$platform] = $val ? 1 : 0;
		}
		return $out;
	}
}

if (!function_exists('vms_social_event_meta_template_overrides')) {
	/**
	 * @return array<string,int>
	 */
	function vms_social_event_meta_template_overrides(int $event_plan_id): array
	{
		$key = vms_social_event_panel_meta_key('template_overrides');
		$raw = get_post_meta($event_plan_id, $key, true);
		$stored = is_array($raw) ? $raw : array();
		$out = array();
		foreach (vms_social_event_platforms() as $platform) {
			$out[$platform] = absint($stored[$platform] ?? 0);
		}
		return $out;
	}
}

if (!function_exists('vms_social_event_has_posted_queue')) {
	function vms_social_event_has_posted_queue(int $event_plan_id): bool
	{
		global $wpdb;
		$table = vms_social_table_queue();
		$count = (int) $wpdb->get_var(
			$wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_plan_id = %d AND status = 'posted'", $event_plan_id)
		);
		return $count > 0;
	}
}

if (!function_exists('vms_social_values_are_equal')) {
	/**
	 * Compare social-panel values after normalization so default/unchanged form
	 * submissions do not create Event Plan save-profile module noise.
	 *
	 * @param mixed $left
	 * @param mixed $right
	 */
	function vms_social_values_are_equal($left, $right): bool
	{
		if (is_array($left) || is_array($right)) {
			$left = is_array($left) ? $left : array();
			$right = is_array($right) ? $right : array();
			ksort($left);
			ksort($right);
			return wp_json_encode($left) === wp_json_encode($right);
		}

		return (string) $left === (string) $right;
	}
}

if (!function_exists('vms_social_update_event_panel_meta_if_changed')) {
	/**
	 * Update event-plan social meta only when the canonical value actually changes.
	 * Treat a missing meta row as the supplied default so normal Event Plan saves do
	 * not persist default social settings and falsely dirty the Marketing module.
	 *
	 * @param mixed $value
	 * @param mixed $default
	 */
	function vms_social_update_event_panel_meta_if_changed(int $post_id, string $key, $value, $default): bool
	{
		$exists = metadata_exists('post', $post_id, $key);
		$current = $exists ? get_post_meta($post_id, $key, true) : $default;

		if (vms_social_values_are_equal($current, $value)) {
			return false;
		}

		return update_post_meta($post_id, $key, $value) !== false;
	}
}

if (!function_exists('vms_social_event_share_url')) {
	function vms_social_event_share_url(string $platform, string $url, string $caption): string
	{
		$platform = sanitize_key($platform);
		$url = esc_url_raw($url);
		$caption = trim($caption);
		if ($platform === 'facebook') {
			return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url);
		}
		if ($platform === 'linkedin') {
			return 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($url);
		}
		if ($platform === 'x') {
			return 'https://twitter.com/intent/tweet?text=' . rawurlencode($caption) . '&url=' . rawurlencode($url);
		}
		return '';
	}
}

if (!function_exists('vms_social_parse_local_datetime_to_utc')) {
	function vms_social_parse_local_datetime_to_utc(string $local): string
	{
		$local = trim($local);
		if ($local === '') {
			return vms_social_now_mysql_utc();
		}

		$tz = wp_timezone();
		$dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $local, $tz);
		if (!($dt instanceof DateTimeImmutable)) {
			$dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $local, $tz);
		}
		if (!($dt instanceof DateTimeImmutable)) {
			return vms_social_now_mysql_utc();
		}

		return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	}
}

if (!function_exists('vms_social_event_panel_form_id')) {
	function vms_social_event_panel_form_id(int $event_plan_id, string $kind): string
	{
		return 'vms-social-' . sanitize_key($kind) . '-form-' . absint($event_plan_id);
	}
}

if (!function_exists('vms_social_event_panel_register_footer_forms')) {
	function vms_social_event_panel_register_footer_forms(int $event_plan_id, int $queue_id = 0): void
	{
		global $vms_social_event_panel_footer_forms;
		if (!is_array($vms_social_event_panel_footer_forms)) {
			$vms_social_event_panel_footer_forms = array();
		}
		$vms_social_event_panel_footer_forms[$event_plan_id] = array(
			'event_plan_id' => $event_plan_id,
			'queue_id' => $queue_id > 0 ? $queue_id : 0,
		);
	}
}

if (!function_exists('vms_social_event_panel_footer_forms_html')) {
	function vms_social_event_panel_footer_forms_html(int $event_plan_id, int $queue_id = 0): string
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return '';
		}

		ob_start();

		$queue_form_id = vms_social_event_panel_form_id($event_plan_id, 'event-queue');
		echo '<form id="' . esc_attr($queue_form_id) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-social-detached-form" style="display:none;">';
		wp_nonce_field('vms_social_event_queue', '_wpnonce', false);
		echo '<input type="hidden" name="action" value="vms_social_event_queue" />';
		echo '<input type="hidden" name="event_plan_id" value="' . (int) $event_plan_id . '" />';
		echo '</form>';

		$queue_id = absint($queue_id);
		if ($queue_id > 0) {
			$cancel_form_id = vms_social_event_panel_form_id($event_plan_id, 'queue-cancel');
			echo '<form id="' . esc_attr($cancel_form_id) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-social-detached-form" style="display:none;">';
			wp_nonce_field('vms_social_queue_cancel', '_wpnonce', false);
			echo '<input type="hidden" name="action" value="vms_social_queue_cancel" />';
			echo '<input type="hidden" name="queue_id" value="' . (int) $queue_id . '" />';
			echo '<input type="hidden" name="event_plan_id" value="' . (int) $event_plan_id . '" />';
			echo '<input type="hidden" name="tab" value="queue" />';
			echo '</form>';

			$retry_form_id = vms_social_event_panel_form_id($event_plan_id, 'queue-retry');
			echo '<form id="' . esc_attr($retry_form_id) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-social-detached-form" style="display:none;">';
			wp_nonce_field('vms_social_queue_retry', '_wpnonce', false);
			echo '<input type="hidden" name="action" value="vms_social_queue_retry" />';
			echo '<input type="hidden" name="queue_id" value="' . (int) $queue_id . '" />';
			echo '<input type="hidden" name="event_plan_id" value="' . (int) $event_plan_id . '" />';
			echo '<input type="hidden" name="tab" value="queue" />';
			echo '</form>';
		}

		return (string) ob_get_clean();
	}
}

if (!function_exists('vms_social_event_panel_render_footer_forms')) {
	function vms_social_event_panel_render_footer_forms(): void
	{
		if (!is_admin()) {
			return;
		}
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!is_object($screen) || (string) ($screen->post_type ?? '') !== 'vms_event_plan') {
			return;
		}

		global $vms_social_event_panel_footer_forms;
		if (!is_array($vms_social_event_panel_footer_forms) || empty($vms_social_event_panel_footer_forms)) {
			return;
		}

		foreach ($vms_social_event_panel_footer_forms as $entry) {
			$event_plan_id = absint($entry['event_plan_id'] ?? 0);
			if ($event_plan_id <= 0) {
				continue;
			}

			$queue_id = absint($entry['queue_id'] ?? 0);
			echo vms_social_event_panel_footer_forms_html($event_plan_id, $queue_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
add_action('admin_footer', 'vms_social_event_panel_render_footer_forms', 40);

if (!function_exists('vms_social_event_panel_is_collapsed_for_user')) {
	function vms_social_event_panel_is_collapsed_for_user(WP_Post $post): bool
	{
		if (!is_admin()) {
			return false;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$screen_id = is_object($screen) && !empty($screen->id)
			? (string) $screen->id
			: (string) $post->post_type;
		if ($screen_id === '') {
			$screen_id = 'vms_event_plan';
		}

		$closed = get_user_option('closedpostboxes_' . $screen_id);
		if (is_array($closed)) {
			return in_array('vms_social_promotion', array_map('strval', $closed), true);
		}

		return false;
	}
}

if (!function_exists('vms_social_event_panel_markup')) {
	function vms_social_event_panel_markup(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array(
				'html' => '',
				'queue_id' => 0,
			);
		}

		$context = vms_social_event_plan_context($event_plan_id);
		$platform_enabled = vms_social_event_meta_enabled_platforms($event_plan_id);
		$template_overrides = vms_social_event_meta_template_overrides($event_plan_id);
		$do_not_post = (int) get_post_meta($event_plan_id, vms_social_event_panel_meta_key('do_not_post'), true) === 1;
		$flag_unpublished = (int) get_post_meta($event_plan_id, vms_social_event_panel_meta_key('unpublished_after_post'), true) === 1;
		$last_queue = vms_social_queue_latest_for_event($event_plan_id);
		$queue_id = is_array($last_queue) ? (int) ($last_queue['id'] ?? 0) : 0;
		$queue_form_id = vms_social_event_panel_form_id($event_plan_id, 'event-queue');
		$queue_cancel_form_id = vms_social_event_panel_form_id($event_plan_id, 'queue-cancel');
		$queue_retry_form_id = vms_social_event_panel_form_id($event_plan_id, 'queue-retry');

		ob_start();
		wp_nonce_field('vms_social_event_panel_save', 'vms_social_event_panel_nonce');

		echo '<p class="description">' . esc_html__('Phase 1 manual toolkit: copy caption/link and open share dialogs. Queue actions currently use the Phase 0 provider framework.', 'backstage-venue-manager') . '</p>';
		echo '<p><label><input type="checkbox" name="vms_social_do_not_post" value="1" ' . checked(true, $do_not_post, false) . ' /> ' . esc_html__('Do not post this event', 'backstage-venue-manager') . '</label></p>';

		if ($flag_unpublished) {
			echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Unpublished after social post', 'backstage-venue-manager') . '</strong> ' . esc_html__('This event is now Draft but has at least one previously posted social queue item.', 'backstage-venue-manager') . '</p></div>';
		}

		echo '<div class="vms-social-platform-grid">';
		foreach (vms_social_event_platforms() as $platform) {
			$platform_templates = vms_social_templates_all($platform);
			$selected_template_id = (int) ($template_overrides[$platform] ?? 0);
			$template_id = $selected_template_id;
			if ($template_id <= 0) {
				$default_tpl = vms_social_template_default_for_platform($platform);
				$template_id = (int) ($default_tpl['id'] ?? 0);
			}
			$template = vms_social_template_for_platform($platform, $template_id);
			$rendered = is_array($template)
				? vms_social_render_template_payload($platform, (string) ($template['body'] ?? ''), $context, !empty(vms_social_get_settings()['utm_enabled']))
				: array('caption' => '', 'final_url' => (string) ($context['ticket_url'] ?? $context['event_url'] ?? ''));
			$caption = (string) ($rendered['caption'] ?? '');
			$final_url = (string) ($rendered['final_url'] ?? '');
			$share_url = vms_social_event_share_url($platform, $final_url, $caption);

			echo '<section class="vms-social-platform-card">';
			echo '<h4>' . esc_html(ucfirst($platform)) . '</h4>';
			echo '<p><label><input type="checkbox" name="vms_social_enabled[' . esc_attr($platform) . ']" value="1" ' . checked(1, (int) ($platform_enabled[$platform] ?? 1), false) . ' /> ' . esc_html__('Enabled for this event', 'backstage-venue-manager') . '</label></p>';
			echo '<p><label>' . esc_html__('Template', 'backstage-venue-manager') . ' <select name="vms_social_template[' . esc_attr($platform) . ']">';
			echo '<option value="0" ' . selected(0, $selected_template_id, false) . '>' . esc_html__('Default', 'backstage-venue-manager') . '</option>';
			foreach ($platform_templates as $tpl) {
				echo '<option value="' . (int) $tpl['id'] . '" ' . selected($selected_template_id, (int) $tpl['id'], false) . '>#' . (int) $tpl['id'] . ' - ' . esc_html((string) $tpl['name']) . '</option>';
			}
			echo '</select></label></p>';

			echo '<div class="vms-social-manual-tools">';
			echo '<button type="button" class="button vms-social-copy-btn" data-copy-text="' . esc_attr($caption) . '">' . esc_html__('Copy Caption', 'backstage-venue-manager') . '</button> ';
			echo '<button type="button" class="button vms-social-copy-btn" data-copy-text="' . esc_attr($final_url) . '">' . esc_html__('Copy Link', 'backstage-venue-manager') . '</button> ';
			if ($share_url !== '') {
				echo '<a class="button button-secondary" href="' . esc_url($share_url) . '" target="_blank" rel="noopener">' . esc_html__('Open Share Dialog', 'backstage-venue-manager') . '</a>';
			}
			echo '</div>';
			echo '<p class="description"><strong>' . esc_html__('Preview:', 'backstage-venue-manager') . '</strong> ' . esc_html(vms_social_trim_preview($caption, 180)) . '</p>';
			echo '</section>';
		}
		echo '</div>';

		echo '<hr />';
		echo '<h4>' . esc_html__('Queue Actions', 'backstage-venue-manager') . '</h4>';
		if (is_array($last_queue)) {
			echo '<p><strong>' . esc_html__('Latest queue item:', 'backstage-venue-manager') . '</strong> #' . (int) $last_queue['id'] . ' | ' . esc_html((string) $last_queue['status']) . ' | ' . esc_html((string) $last_queue['platform']) . '</p>';
			if (!empty($last_queue['last_error_message'])) {
				echo '<p class="description">' . esc_html((string) $last_queue['last_error_message']) . '</p>';
			}
		}

		echo '<div class="vms-social-event-queue-form">';
		echo '<p><label>' . esc_html__('Queue Platform', 'backstage-venue-manager') . ' <select name="platform" form="' . esc_attr($queue_form_id) . '">';
		foreach (array('mock', 'webhook', 'facebook', 'linkedin', 'x') as $platform) {
			echo '<option value="' . esc_attr($platform) . '">' . esc_html($platform) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>' . esc_html__('Template ID (optional override)', 'backstage-venue-manager') . ' <input type="number" min="0" step="1" name="template_id" value="0" form="' . esc_attr($queue_form_id) . '" /></label></p>';
		echo '<p><label>' . esc_html__('Destination ID (optional override)', 'backstage-venue-manager') . ' <input type="text" name="destination_id" value="" class="regular-text" form="' . esc_attr($queue_form_id) . '" /></label></p>';
		echo '<p><label>' . esc_html__('Schedule (optional, local timezone)', 'backstage-venue-manager') . ' <input type="datetime-local" name="scheduled_at_local" value="" form="' . esc_attr($queue_form_id) . '" /></label></p>';
		echo '<p><button type="submit" class="button button-primary" form="' . esc_attr($queue_form_id) . '">' . esc_html__('Queue / Schedule', 'backstage-venue-manager') . '</button></p>';
		echo '</div>';

		if (is_array($last_queue)) {
			echo '<div class="vms-social-event-queue-ops">';
			echo '<button type="submit" class="button" form="' . esc_attr($queue_cancel_form_id) . '">' . esc_html__('Cancel Latest', 'backstage-venue-manager') . '</button> ';
			echo '<button type="submit" class="button button-secondary" form="' . esc_attr($queue_retry_form_id) . '">' . esc_html__('Retry Latest', 'backstage-venue-manager') . '</button>';
			echo '</div>';
		}

		return array(
			'html' => (string) ob_get_clean(),
			'queue_id' => $queue_id,
		);
	}
}

if (!function_exists('vms_social_add_event_panel')) {
	function vms_social_add_event_panel(): void
	{
		add_meta_box(
			'vms_social_promotion',
			__('Promotion (Social Sharing)', 'backstage-venue-manager'),
			'vms_social_render_event_panel',
			'vms_event_plan',
			'normal',
			'high'
		);
	}
}
add_action('add_meta_boxes_vms_event_plan', 'vms_social_add_event_panel');

if (!function_exists('vms_social_render_event_panel')) {
	function vms_social_render_event_panel(WP_Post $post): void
	{
		if (!vms_social_current_user_can_manage()) {
			echo '<p>' . esc_html__('You do not have permission to manage social sharing.', 'backstage-venue-manager') . '</p>';
			return;
		}

		$event_plan_id = (int) $post->ID;
		if (vms_social_event_panel_is_collapsed_for_user($post)) {
			echo '<div class="vms-social-event-panel-shell" data-vms-social-lazy="1" data-vms-social-post-id="' . esc_attr((string) $event_plan_id) . '" data-vms-social-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-vms-social-nonce="' . esc_attr(wp_create_nonce('vms_social_load_event_panel')) . '">';
			echo '<p class="description">' . esc_html__('Open this panel to load social templates, previews, and queue actions.', 'backstage-venue-manager') . '</p>';
			echo '</div>';
			return;
		}

		$payload = vms_social_event_panel_markup($event_plan_id);
		echo (string) ($payload['html'] ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		vms_social_event_panel_register_footer_forms($event_plan_id, (int) ($payload['queue_id'] ?? 0));
	}
}

if (!function_exists('vms_social_ajax_load_event_panel')) {
	function vms_social_ajax_load_event_panel(): void
	{
		$event_plan_id = absint(vms_social_post_value('post_id'));
		if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
			wp_send_json_error(array('message' => 'Invalid Event Plan.'), 400);
		}
		if (!current_user_can('edit_post', $event_plan_id) || !vms_social_current_user_can_manage()) {
			wp_send_json_error(array('message' => 'Not allowed.'), 403);
		}

		check_ajax_referer('vms_social_load_event_panel', 'nonce');

		$payload = vms_social_event_panel_markup($event_plan_id);
		wp_send_json_success(array(
			'html' => (string) ($payload['html'] ?? ''),
			'footer_forms_html' => vms_social_event_panel_footer_forms_html($event_plan_id, (int) ($payload['queue_id'] ?? 0)),
		));
	}
}
add_action('wp_ajax_vms_social_load_event_panel', 'vms_social_ajax_load_event_panel');

if (!function_exists('vms_social_save_event_panel')) {
	function vms_social_save_event_panel(int $post_id, WP_Post $post): void
	{
		if ($post->post_type !== 'vms_event_plan') {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (wp_is_post_revision($post_id)) {
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$panel_nonce = sanitize_text_field(vms_social_post_value('vms_social_event_panel_nonce'));
		if ($panel_nonce === '' || !wp_verify_nonce($panel_nonce, 'vms_social_event_panel_save')) {
			return;
		}

		$do_not_post = (vms_social_post_value('vms_social_do_not_post') !== '') ? 1 : 0;
		vms_social_update_event_panel_meta_if_changed($post_id, vms_social_event_panel_meta_key('do_not_post'), $do_not_post, 0);

		$enabled_raw = vms_social_post_array('vms_social_enabled');
		$enabled = array();
		$enabled_default = array();
		foreach (vms_social_event_platforms() as $platform) {
			$enabled[$platform] = isset($enabled_raw[$platform]) ? 1 : 0;
			$enabled_default[$platform] = 1;
		}
		vms_social_update_event_panel_meta_if_changed($post_id, vms_social_event_panel_meta_key('platform_overrides'), $enabled, $enabled_default);

		$tpl_raw = vms_social_post_array('vms_social_template');
		$tpl = array();
		$tpl_default = array();
		foreach (vms_social_event_platforms() as $platform) {
			$tpl[$platform] = absint($tpl_raw[$platform] ?? 0);
			$tpl_default[$platform] = 0;
		}
		vms_social_update_event_panel_meta_if_changed($post_id, vms_social_event_panel_meta_key('template_overrides'), $tpl, $tpl_default);

		$status = function_exists('vms_event_plan_current_internal_status')
			? (string) vms_event_plan_current_internal_status($post_id, 'financial')
			: sanitize_key((string) get_post_meta($post_id, vms_social_event_panel_meta_key('status'), true));
		$status = sanitize_key($status);
		$flag = ($status === 'draft' && vms_social_event_has_posted_queue($post_id)) ? 1 : 0;
		vms_social_update_event_panel_meta_if_changed($post_id, vms_social_event_panel_meta_key('unpublished_after_post'), $flag, 0);
	}
}
add_action('save_post_vms_event_plan', 'vms_social_save_event_panel', 30, 2);

if (!function_exists('vms_social_redirect_event_edit')) {
	function vms_social_redirect_event_edit(int $event_plan_id, string $notice, string $type = 'success'): void
	{
		$url = add_query_arg(
			array(
				'post' => $event_plan_id,
				'action' => 'edit',
				'vms_social_event_notice' => rawurlencode($notice),
				'vms_social_event_notice_type' => sanitize_key($type),
			),
			admin_url('post.php')
		);
		wp_safe_redirect($url);
		exit;
	}
}

if (!function_exists('vms_social_handle_event_queue')) {
	function vms_social_handle_event_queue(): void
	{
		vms_social_require_manage_capability();
		check_admin_referer('vms_social_event_queue');

		$event_plan_id = absint(vms_social_post_value('event_plan_id'));
		if ($event_plan_id <= 0) {
			wp_die(esc_html__('Invalid event plan.', 'backstage-venue-manager'));
		}

		$do_not_post = (int) get_post_meta($event_plan_id, vms_social_event_panel_meta_key('do_not_post'), true) === 1;
		if ($do_not_post) {
			vms_social_redirect_event_edit($event_plan_id, __('Queue blocked: this event is marked Do Not Post.', 'backstage-venue-manager'), 'warning');
		}

		$platform = sanitize_key(vms_social_post_value('platform'));
		if ($platform === '') {
			$platform = 'mock';
		}

		$template_id = absint(vms_social_post_value('template_id'));
		$destination_id = sanitize_text_field(vms_social_post_value('destination_id'));
		$context = vms_social_event_plan_context($event_plan_id);
		$venue_id = (int) ($context['venue_id'] ?? 0);

		$map = vms_social_venue_map_for_platform($venue_id, $platform);
		if (is_array($map)) {
			if ($destination_id === '') {
				$destination_id = sanitize_text_field((string) ($map['destination_id'] ?? ''));
			}
			if ($template_id <= 0) {
				$template_id = (int) ($map['default_template_id'] ?? 0);
			}
		}

		if ($template_id <= 0) {
			$template = vms_social_template_default_for_platform($platform);
			$template_id = (int) ($template['id'] ?? 0);
		}

		$scheduled_local = sanitize_text_field(vms_social_post_value('scheduled_at_local'));
		$scheduled_utc = vms_social_parse_local_datetime_to_utc($scheduled_local);

		$queue_id = vms_social_queue_create(array(
			'event_plan_id' => $event_plan_id,
			'tec_event_id' => 0,
			'venue_id' => $venue_id,
			'platform' => $platform,
			'destination_id' => $destination_id,
			'template_id' => $template_id,
			'status' => 'queued',
			'scheduled_at_utc' => $scheduled_utc,
			'payload_snapshot_json' => array(
				'queued_from' => 'event_panel',
				'account_id' => is_array($map) ? (int) ($map['account_id'] ?? 0) : 0,
				'event_title' => (string) ($context['event_title'] ?? ''),
			),
			'created_by' => get_current_user_id(),
			'updated_by' => get_current_user_id(),
		));

		vms_social_audit_log('queue', array(
			'queue_id' => $queue_id,
			'event_plan_id' => $event_plan_id,
			'platform' => $platform,
			'scheduled_at_utc' => $scheduled_utc,
		), $queue_id, $platform, get_current_user_id());

		/* translators: %d: queued social post ID. */
		vms_social_redirect_event_edit($event_plan_id, sprintf(__('Queue item #%d created.', 'backstage-venue-manager'), $queue_id), 'success');
	}
}
add_action('admin_post_vms_social_event_queue', 'vms_social_handle_event_queue');

add_action('admin_notices', function (): void {
	if (!is_admin()) {
		return;
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!is_object($screen) || (string) ($screen->post_type ?? '') !== 'vms_event_plan') {
		return;
	}
	$notice = sanitize_text_field(vms_social_query_value('vms_social_event_notice'));
	if ($notice === '') {
		return;
	}
	$type = sanitize_key(vms_social_query_value('vms_social_event_notice_type'));
	if ($type === '') {
		$type = 'success';
	}
	$class = in_array($type, array('success', 'error', 'warning', 'info'), true) ? $type : 'success';
	echo '<div class="notice notice-' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($notice) . '</p></div>';
});
