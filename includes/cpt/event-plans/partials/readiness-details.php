<?php
defined('ABSPATH') || exit;

$vms_readiness_detail_context = isset($vms_readiness_detail_context) && is_array($vms_readiness_detail_context)
    ? $vms_readiness_detail_context
    : array();
$summary_rows = isset($vms_readiness_detail_context['summary_rows']) && is_array($vms_readiness_detail_context['summary_rows'])
    ? $vms_readiness_detail_context['summary_rows']
    : array();
$warning_items = isset($vms_readiness_detail_context['warning_items']) && is_array($vms_readiness_detail_context['warning_items'])
    ? $vms_readiness_detail_context['warning_items']
    : array();
$linked_tec_summary = isset($vms_readiness_detail_context['linked_tec_summary']) && is_array($vms_readiness_detail_context['linked_tec_summary'])
    ? $vms_readiness_detail_context['linked_tec_summary']
    : array();
$ticketing_summary = isset($vms_readiness_detail_context['ticketing_summary']) && is_array($vms_readiness_detail_context['ticketing_summary'])
    ? $vms_readiness_detail_context['ticketing_summary']
    : array();
$secondary_vendor_boot_summary = isset($vms_readiness_detail_context['secondary_vendor_boot_summary']) && is_array($vms_readiness_detail_context['secondary_vendor_boot_summary'])
    ? $vms_readiness_detail_context['secondary_vendor_boot_summary']
    : array();
?>
<div class="vms-ep-card vms-ep-card--white vms-ep-card--readiness-details">
    <p class="description"><?php echo esc_html((string) ($vms_readiness_detail_context['status_label'] ?? __('No blocking publish warnings', 'backstage-venue-manager'))); ?></p>

    <?php if (!empty($summary_rows)) : ?>
        <ul class="vms-ep-inline-list">
            <?php foreach ($summary_rows as $summary_row) : ?>
                <?php if (!is_array($summary_row)) { continue; } ?>
                <li>
                    <strong><?php echo esc_html((string) ($summary_row['label'] ?? '')); ?>:</strong>
                    <?php echo esc_html((string) ($summary_row['value'] ?? '')); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($warning_items)) : ?>
        <div class="notice notice-warning inline vms-notice vms-notice--warning">
            <p><strong><?php esc_html_e('Current warning details', 'backstage-venue-manager'); ?></strong></p>
            <ul>
                <?php foreach ($warning_items as $warning_item) : ?>
                    <li><?php echo esc_html((string) $warning_item); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else : ?>
        <div class="notice notice-success inline vms-notice">
            <p><?php esc_html_e('No blocking or vendor-warning details are currently flagged in this summary view.', 'backstage-venue-manager'); ?></p>
        </div>
    <?php endif; ?>

    <p class="description">
        <?php
            $linked_tec_id = absint($linked_tec_summary['linked_tec_id'] ?? 0);
            if ($linked_tec_id > 0) {
                printf(
                    esc_html__('Linked TEC event: %1$s (%2$s).', 'backstage-venue-manager'),
                    esc_html((string) ($linked_tec_summary['linked_tec_title'] ?? sprintf(__('Event #%d', 'backstage-venue-manager'), $linked_tec_id))),
                    esc_html(strtoupper((string) ($linked_tec_summary['linked_tec_status'] ?? 'draft')))
                );
            } else {
                esc_html_e('Linked TEC event: not linked.', 'backstage-venue-manager');
            }
        ?>
    </p>
    <p class="description">
        <?php
            printf(
                esc_html__('Configured tickets: %1$d. Configured add-ons: %2$d.', 'backstage-venue-manager'),
                absint($ticketing_summary['effective_ticket_count'] ?? 0),
                absint($vms_readiness_detail_context['add_on_summary']['enabled_add_on_count'] ?? 0)
            );
        ?>
    </p>
    <p class="description">
        <?php
            printf(
                esc_html__('Secondary vendor warnings: %1$d. Selected secondary vendors: %2$d.', 'backstage-venue-manager'),
                count((array) ($secondary_vendor_boot_summary['secondary_missing'] ?? array()))
                    + count((array) ($secondary_vendor_boot_summary['secondary_mismatch'] ?? array()))
                    + count((array) ($secondary_vendor_boot_summary['secondary_unqualified'] ?? array())),
                absint($vms_readiness_detail_context['readiness_boot_summary']['secondary_vendor_count'] ?? 0)
            );
        ?>
    </p>
</div>
