<?php
    defined('ABSPATH') || exit;
    $vms_secondary_has_data = ($secondary_vendor_type !== '' || !empty($secondary_vendor_ids));
?>
<?php if (!empty($vms_secondary_vendors_html)): ?>
<section class="vms-collapsible-section" data-section-key="secondary_vendors" data-has-data="<?php echo $vms_secondary_has_data ? '1' : '0'; ?>">
    <button type="button" class="vms-collapsible-toggle" aria-expanded="true">
        <span class="vms-collapsible-chevron" aria-hidden="true"></span>
        <span class="vms-collapsible-label"><?php esc_html_e('Secondary Vendors', 'vms'); ?></span>
        <span class="vms-collapsible-flag" aria-hidden="true" hidden><?php esc_html_e('Changed', 'vms'); ?></span>
    </button>
    <div class="vms-collapsible-body">
        <h4 id="vms-secondary-vendors" class="vms-collapsible-title vms-collapsible-title--in-body" data-section-key="secondary_vendors" data-section-has-data="<?php echo $vms_secondary_has_data ? '1' : '0'; ?>"><?php esc_html_e('Secondary Vendors', 'vms'); ?></h4>
        <div class="vms-ep-card vms-ep-card--white vms-ep-card--secondary-vendors" data-vms-section-has-data="<?php echo $vms_secondary_has_data ? '1' : '0'; ?>">
            <?php echo $vms_secondary_vendors_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </div>
</section>
<?php endif; ?>
