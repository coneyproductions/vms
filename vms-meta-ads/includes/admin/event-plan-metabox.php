<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_ma_register_event_plan_metabox')) {
	function vms_ma_register_event_plan_metabox(): void
	{
		if (!vms_ma_current_user_can_manage()) {
			return;
		}
		add_meta_box(
			'vms_ma_event_plan_meta_ads',
			__('Meta Ads', 'vms-meta-ads'),
			'vms_ma_render_event_plan_metabox',
			'vms_event_plan',
			'side',
			'default'
		);
	}
}
add_action('add_meta_boxes_vms_event_plan', 'vms_ma_register_event_plan_metabox');

if (!function_exists('vms_ma_render_event_plan_metabox')) {
	function vms_ma_render_event_plan_metabox(WP_Post $post): void
	{
		$event_plan_id = (int) $post->ID;
		$last_updated = trim((string) get_post_meta($event_plan_id, 'vms_ma_last_updated_local', true));
		$last_exported = trim((string) get_post_meta($event_plan_id, 'vms_ma_last_exported_local', true));
		$status = __('No draft yet', 'vms-meta-ads');
		if ($last_updated !== '') {
			$status = sprintf(__('Draft saved %s', 'vms-meta-ads'), $last_updated);
		}
		if ($last_exported !== '') {
			$status = sprintf(__('Exported %s', 'vms-meta-ads'), $last_exported);
		}
		$builder_url = add_query_arg(
			array(
				'page' => 'vms-ma-ads-builder',
				'event_plan_id' => $event_plan_id,
				'prefill' => 1,
				'tour' => 0,
			),
			admin_url('admin.php')
		);
		echo '<p><strong>' . esc_html__('Status:', 'vms-meta-ads') . '</strong> ' . esc_html($status) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url($builder_url) . '">' . esc_html__('Open in Meta Ads Builder', 'vms-meta-ads') . '</a></p>';
	}
}
