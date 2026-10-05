<?php
/**
 * Narrow compatibility layer for the recovered Outreach implementation.
 *
 * Historical vms_* data identifiers remain intact. Runtime calls are delegated
 * to the supported BVM 1.2 APIs instead of loading or detecting legacy VMS code.
 */

defined('ABSPATH') || exit;

if (!function_exists('vms_register_module')) {
	function vms_register_module(array $module): bool { return bvmgr_register_module($module); }
}
if (!function_exists('vms_register_admin_page')) {
	function vms_register_admin_page(array $args): bool { return bvmgr_register_admin_page($args); }
}
if (!function_exists('vms_admin_ui_render_shell')) {
	function vms_admin_ui_render_shell(array $args, callable $content_callback): void { bvmgr_admin_ui_render_shell($args, $content_callback); }
}
if (!function_exists('vms_admission_audit_log')) {
	function vms_admission_audit_log(int $event_plan_id, ?int $entry_id, string $action, int $actor_user_id, string $actor_context, array $details = array()): bool { return bvmgr_admission_audit_log($event_plan_id, $entry_id, $action, $actor_user_id, $actor_context, $details); }
}
if (!function_exists('vms_admission_normalize_email')) {
	function vms_admission_normalize_email(string $email): string { return bvmgr_admission_normalize_email($email); }
}
if (!function_exists('vms_admission_normalize_name')) {
	function vms_admission_normalize_name(string $name): string { return bvmgr_admission_normalize_name($name); }
}
if (!function_exists('vms_admission_normalize_phone')) {
	function vms_admission_normalize_phone(string $phone): string { return bvmgr_admission_normalize_phone($phone); }
}
if (!function_exists('vms_admission_now_mysql')) {
	function vms_admission_now_mysql(): string { return bvmgr_admission_now_mysql(); }
}
if (!function_exists('vms_admission_table_entries')) {
	function vms_admission_table_entries(): string { return bvmgr_admission_table_entries(); }
}
if (!function_exists('vms_admission_table_pass_sources')) {
	function vms_admission_table_pass_sources(): string { return bvmgr_admission_table_pass_sources(); }
}
if (!function_exists('vms_admission_table_pass_batches')) {
	function vms_admission_table_pass_batches(): string { return bvmgr_admission_table_pass_batches(); }
}
if (!function_exists('vms_admission_table_pass_tokens')) {
	function vms_admission_table_pass_tokens(): string { return bvmgr_admission_table_pass_tokens(); }
}
if (!function_exists('vms_admission_table_pass_claims')) {
	function vms_admission_table_pass_claims(): string { return bvmgr_admission_table_pass_claims(); }
}
if (!function_exists('vms_admission_table_pass_outreach_campaigns')) {
	function vms_admission_table_pass_outreach_campaigns(): string { global $wpdb; return $wpdb->prefix . 'vms_pass_outreach_campaigns'; }
}
if (!function_exists('vms_admission_table_pass_outreach_recipients')) {
	function vms_admission_table_pass_outreach_recipients(): string { global $wpdb; return $wpdb->prefix . 'vms_pass_outreach_recipients'; }
}
if (!function_exists('vms_admission_vendor_guest_is_internal_comp_order')) {
	function vms_admission_vendor_guest_is_internal_comp_order($order_ref): bool { return bvmgr_admission_vendor_guest_is_internal_comp_order($order_ref); }
}
if (!function_exists('vms_admission_vendor_guest_product_event_id')) {
	function vms_admission_vendor_guest_product_event_id(int $product_id): int { return bvmgr_admission_vendor_guest_product_event_id($product_id); }
}
if (!function_exists('vms_pass_claims_admin_page_url')) {
	function vms_pass_claims_admin_page_url(array $args = array()): string { return bvmgr_pass_claims_admin_page_url($args); }
}
if (!function_exists('vms_pass_claims_allowed_validity_types')) {
	function vms_pass_claims_allowed_validity_types(): array { return bvmgr_pass_claims_allowed_validity_types(); }
}
if (!function_exists('vms_pass_claims_capability')) {
	function vms_pass_claims_capability(): string { return bvmgr_pass_claims_capability(); }
}
if (!function_exists('vms_pass_claims_eligible_events_for_batch')) {
	function vms_pass_claims_eligible_events_for_batch(array $batch): array { return bvmgr_pass_claims_eligible_events_for_batch($batch); }
}
if (!function_exists('vms_pass_claims_format_local_datetime_input')) {
	function vms_pass_claims_format_local_datetime_input(string $raw): string { return bvmgr_pass_claims_format_local_datetime_input($raw); }
}
if (!function_exists('vms_pass_claims_generate_tokens_for_batch')) {
	function vms_pass_claims_generate_tokens_for_batch(int $batch_id, int $quantity, int $source_id, int $created_by) { return bvmgr_pass_claims_generate_tokens_for_batch($batch_id, $quantity, $source_id, $created_by); }
}
if (!function_exists('vms_pass_claims_get_batch_by_id')) {
	function vms_pass_claims_get_batch_by_id(int $batch_id): ?array { return bvmgr_pass_claims_get_batch_by_id($batch_id); }
}
if (!function_exists('vms_pass_claims_get_batches')) {
	function vms_pass_claims_get_batches(int $limit = 200): array { return bvmgr_pass_claims_get_batches($limit); }
}
if (!function_exists('vms_pass_claims_get_event_plan_brief')) {
	function vms_pass_claims_get_event_plan_brief(int $event_plan_id): ?array { return bvmgr_pass_claims_get_event_plan_brief($event_plan_id); }
}
if (!function_exists('vms_pass_claims_get_published_event_plans')) {
	function vms_pass_claims_get_published_event_plans(int $limit = 300): array { return bvmgr_pass_claims_get_published_event_plans($limit); }
}
if (!function_exists('vms_pass_claims_get_source_by_id')) {
	function vms_pass_claims_get_source_by_id(int $source_id): ?array { return bvmgr_pass_claims_get_source_by_id($source_id); }
}
if (!function_exists('vms_pass_claims_get_sources')) {
	function vms_pass_claims_get_sources(bool $include_inactive = false): array { return bvmgr_pass_claims_get_sources($include_inactive); }
}
if (!function_exists('vms_pass_claims_get_token_by_id')) {
	function vms_pass_claims_get_token_by_id(int $token_id): ?array { return bvmgr_pass_claims_get_token_by_id($token_id); }
}
if (!function_exists('vms_pass_claims_menu_slug')) {
	function vms_pass_claims_menu_slug(): string { return bvmgr_pass_claims_menu_slug(); }
}
if (!function_exists('vms_pass_claims_parse_local_datetime')) {
	function vms_pass_claims_parse_local_datetime(string $raw): string { return bvmgr_pass_claims_parse_local_datetime($raw); }
}
if (!function_exists('vms_pass_claims_render_admin_notices')) {
	function vms_pass_claims_render_admin_notices(): void { bvmgr_pass_claims_render_admin_notices(); }
}
if (!function_exists('vms_pass_claims_render_public_shell')) {
	function vms_pass_claims_render_public_shell(string $headline, string $content_html, string $page_variant = ''): void
	{
		bvmgr_pass_claims_render_public_shell($headline, static function () use ($content_html): void { echo wp_kses_post($content_html); });
	}
}
if (!function_exists('vms_pass_claims_sanitize_batch_payload')) {
	function vms_pass_claims_sanitize_batch_payload(array $source): array { return bvmgr_pass_claims_sanitize_batch_payload($source); }
}
if (!function_exists('vms_pass_claims_set_user_message')) {
	function vms_pass_claims_set_user_message(string $type, string $message): void { bvmgr_pass_claims_set_user_message($type, $message); }
}
if (!function_exists('vms_pass_claims_build_raw_token')) {
	function vms_pass_claims_build_raw_token(array $token_row): string { return bvmgr_pass_claims_build_raw_token($token_row); }
}
if (!function_exists('vms_ticketing_v2_paid_order_statuses')) {
	function vms_ticketing_v2_paid_order_statuses(): array { return bvmgr_ticketing_v2_paid_order_statuses(); }
}
if (!function_exists('vms_ticketing_v2_paid_order_statuses_with_prefix')) {
	function vms_ticketing_v2_paid_order_statuses_with_prefix(array $statuses): array { return bvmgr_ticketing_v2_paid_order_statuses_with_prefix($statuses); }
}

if (!function_exists('vms_pass_claims_create_source_label')) {
	function vms_pass_claims_create_source_label(string $source_name, int $user_id = 0)
	{
		$source_name = sanitize_text_field($source_name);
		if ($source_name === '') {
			return new WP_Error('missing_source_name', __('Tracking category name is required.', 'backstage-outreach'));
		}
		foreach (bvmgr_pass_claims_get_sources(true) as $source) {
			if (strcasecmp((string) ($source['source_name'] ?? ''), $source_name) === 0) {
				return $source;
			}
		}

		global $wpdb;
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		$inserted = $wpdb->insert(
			bvmgr_admission_table_pass_sources(),
			array('source_name' => $source_name, 'contact_name' => '', 'phone' => '', 'email' => '', 'notes' => '', 'status' => 'active', 'created_by' => $user_id, 'created_at' => bvmgr_admission_now_mysql()),
			array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
		);
		if ($inserted === false) {
			return new WP_Error('source_create_failed', __('Could not create tracking category.', 'backstage-outreach'));
		}
		$source_id = (int) $wpdb->insert_id;
		bvmgr_admission_audit_log(0, null, 'pass_source_create', $user_id, 'admin', array('source_id' => $source_id, 'source_name' => $source_name, 'created_inline_for_outreach' => true));
		return bvmgr_pass_claims_get_source_by_id($source_id);
	}
}

if (!function_exists('vms_pass_claims_admin_field_label')) {
	function vms_pass_claims_admin_field_label(string $label, string $badge_text = '', string $badge_variant = 'required'): string
	{
		$html = '<span class="vms-pass-field-label">' . esc_html($label) . '</span>';
		if ($badge_text !== '') {
			$classes = 'vms-pass-label-badge ' . ($badge_variant === 'conditional' ? 'is-conditional' : 'is-required');
			$html .= ' <span class="' . esc_attr($classes) . '">' . esc_html($badge_text) . '</span>';
		}
		return $html;
	}
}
if (!function_exists('vms_pass_claims_admin_field_description')) {
	function vms_pass_claims_admin_field_description(string $description): string
	{
		return $description === '' ? '' : '<span class="description vms-pass-field-description">' . esc_html($description) . '</span>';
	}
}
if (!function_exists('vms_pass_claims_admin_help_label')) {
	function vms_pass_claims_admin_help_label(string $label, array $args = array()): string
	{
		static $help_index = 0;
		$help = trim((string) ($args['help'] ?? ''));
		$html = '<span class="vms-pass-field-label-row"><span class="vms-pass-field-label">' . esc_html($label);
		if (!empty($args['required'])) {
			$html .= ' <span class="vms-pass-required-marker" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__('Required', 'backstage-outreach') . '</span>';
		}
		$html .= '</span>';
		if ($help !== '') {
			$tooltip_id = 'vms-pass-help-' . (++$help_index);
			$html .= '<span class="vms-pass-help"><button type="button" class="vms-pass-help__toggle" aria-describedby="' . esc_attr($tooltip_id) . '" aria-expanded="false" aria-label="' . esc_attr(sprintf(__('More information about %s', 'backstage-outreach'), $label)) . '"><span aria-hidden="true">i</span></button><span id="' . esc_attr($tooltip_id) . '" class="vms-pass-help__popover" role="tooltip">' . esc_html($help) . '</span></span>';
		}
		return $html . '</span>';
	}
}
