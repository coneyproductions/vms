<?php defined('ABSPATH') || exit; ?>
<div
    id="vms-event-plan-workspace-status"
    class="vms-ep-workspace-status"
    data-vms-workspace-status
    data-vms-workflow-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
    data-vms-workflow-nonce="<?php echo esc_attr(wp_create_nonce('bvmgr_event_plan_workflow_action')); ?>"
    data-vms-section-save-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
    data-vms-section-save-nonce="<?php echo esc_attr(wp_create_nonce('bvmgr_event_plan_section_save')); ?>"
    data-vms-plan-id="<?php echo (int) $post->ID; ?>"
    data-vms-calendar-state="<?php echo esc_attr((string) ($workspace_status['calendar_key'] ?? 'not_published')); ?>"
>
    <div class="vms-ep-workspace-status__facts">
        <span><strong><?php esc_html_e('Plan', 'backstage-venue-manager'); ?>:</strong> <?php echo esc_html((string) ($workspace_status['workflow_label'] ?? '')); ?></span>
        <span><strong><?php esc_html_e('Calendar', 'backstage-venue-manager'); ?>:</strong> <?php echo esc_html((string) ($workspace_status['calendar_label'] ?? '')); ?></span>
        <span><strong><?php esc_html_e('TEC', 'backstage-venue-manager'); ?>:</strong> <?php echo esc_html((string) ($workspace_status['tec_label'] ?? '')); ?></span>
        <span><strong><?php esc_html_e('Ticketing', 'backstage-venue-manager'); ?>:</strong> <?php echo esc_html((string) ($workspace_status['ticketing_label'] ?? '')); ?></span>
        <span><strong><?php esc_html_e('Staffing', 'backstage-venue-manager'); ?>:</strong> <?php echo esc_html((string) ($workspace_status['staffing_label'] ?? '')); ?></span>
        <span><strong><?php esc_html_e('Readiness', 'backstage-venue-manager'); ?>:</strong> <?php
            printf(
                esc_html(_n('%d blocking issue', '%d blocking issues', (int) ($workspace_status['blocking_issue_count'] ?? 0), 'backstage-venue-manager')),
                (int) ($workspace_status['blocking_issue_count'] ?? 0)
            );
        ?></span>
    </div>

    <?php if (!empty($workspace_status['last_error_message']) && ($workspace_status['calendar_key'] ?? '') === 'failed') : ?>
        <p class="vms-ep-workspace-status__error"><?php echo esc_html((string) $workspace_status['last_error_message']); ?></p>
    <?php endif; ?>

    <div class="vms-ep-workspace-status__actions">
        <button type="button" class="button button-secondary" data-vms-workflow-action="mark_ready" <?php disabled((string) ($workspace_status['workflow_status'] ?? '') !== 'draft'); ?>><?php esc_html_e('Mark Ready', 'backstage-venue-manager'); ?></button>
        <button type="button" class="button button-primary" data-vms-workflow-action="publish_now" <?php disabled(!in_array((string) ($workspace_status['workflow_status'] ?? ''), array('ready', 'published'), true) || !empty($workspace_status['publishing']) || !empty($workspace_status['retry_allowed'])); ?>><?php esc_html_e('Publish Event', 'backstage-venue-manager'); ?></button>
        <?php if (!empty($workspace_status['retry_allowed'])) : ?>
            <button type="button" class="button button-primary" data-vms-workflow-action="retry_publish"><?php esc_html_e('Retry Publishing', 'backstage-venue-manager'); ?></button>
        <?php endif; ?>
        <button type="button" class="button vms-button-danger" data-vms-open-section="cancellation"><?php esc_html_e('Review Cancellation', 'backstage-venue-manager'); ?></button>
        <span class="description" data-vms-workflow-status aria-live="polite"></span>
    </div>
</div>
