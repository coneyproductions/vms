<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_ma_render_promotable_events_screen')) {
	function vms_ma_render_promotable_events_screen(): void
	{
		$days = 90;
		$items = VMS_Meta_Ads_Event_Plans_Service::list_event_plans(array(
			'after' => wp_date('Y-m-d', null, wp_timezone()),
			'days' => $days,
			'limit' => 80,
			'statuses' => array('published', 'ready', 'draft'),
		));
		?>
		<div class="wrap vms-ma" id="vms-ma-promote-wrap">
			<div class="vms-ma-page-header">
				<div class="vms-ma-page-title">
					<h1><?php esc_html_e('Promotable Events', 'vms-meta-ads'); ?></h1>
					<p class="description vms-ma-intro"><?php echo esc_html(sprintf(__('Upcoming Event Plans in the next %d days. Choose one and jump straight into Builder.', 'vms-meta-ads'), $days)); ?></p>
				</div>
			</div>

			<?php if (empty($items)) : ?>
				<div class="notice notice-warning vms-ma-notice">
					<p><?php esc_html_e('No upcoming Event Plans found for the current window.', 'vms-meta-ads'); ?></p>
					<p><a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=vms_event_plan')); ?>"><?php esc_html_e('Create an Event Plan', 'vms-meta-ads'); ?></a></p>
				</div>
			<?php else : ?>
				<div class="vms-ma-promote-list">
					<?php foreach ($items as $item) : ?>
						<?php
						$promote_url = add_query_arg(
							array(
								'page' => 'vms-ma-ads-builder',
								'event_plan_id' => (int) $item['id'],
								'prefill' => 1,
								'tour' => 0,
							),
							admin_url('admin.php')
						);
						$ticket_status = !empty($item['has_ticket_url']) ? __('OK', 'vms-meta-ads') : __('Missing', 'vms-meta-ads');
						?>
						<article class="vms-ma-promote-card">
							<div class="vms-ma-promote-main">
								<h2><?php echo esc_html((string) $item['title']); ?></h2>
								<p class="description"><?php echo esc_html((string) ($item['start_display'] ?? $item['start_local'])); ?><?php if (!empty($item['venue_name'])) : ?> · <?php echo esc_html((string) $item['venue_name']); ?><?php endif; ?></p>
								<p><strong><?php esc_html_e('Ticket link:', 'vms-meta-ads'); ?></strong> <?php echo esc_html($ticket_status); ?></p>
								<p><strong><?php esc_html_e('Meta Ads:', 'vms-meta-ads'); ?></strong> <?php echo esc_html((string) ($item['meta_ads_status'] ?? __('No draft yet', 'vms-meta-ads'))); ?></p>
							</div>
							<div class="vms-ma-promote-actions">
								<a class="button button-primary" href="<?php echo esc_url($promote_url); ?>"><?php esc_html_e('Promote', 'vms-meta-ads'); ?></a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
