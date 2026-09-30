<?php defined('ABSPATH') || exit; ?>
<div class="vms-ep-basic-item vms-ep-basic-item--secondary vms-ep-title-control">
    <div class="vms-ep-title-control__heading">
        <strong><?php esc_html_e('Event title', 'backstage-venue-manager'); ?></strong>
        <?php
        if (function_exists('bvmgr_help_icon')) {
            bvmgr_help_icon(
                __('Automatic titles follow the selected Primary Vendor. Turn this off when the WordPress title should remain custom; changing the Primary Vendor will then ask before replacing it.', 'backstage-venue-manager'),
                __('Automatic title help', 'backstage-venue-manager')
            );
        }
        ?>
        <span id="vms_title_preview_text"><?php echo esc_html(get_the_title($post->ID)); ?></span>
    </div>
    <p class="vms-m0">
        <label>
            <input type="checkbox" name="vms_auto_title" value="1" <?php checked($auto_title, '1'); ?> />
            <?php esc_html_e('Auto-update title to Primary Vendor', 'backstage-venue-manager'); ?>
        </label>
    </p>
    <div class="description<?php echo checked($auto_title, '1', false) ? ' vms-hidden' : ''; ?>" id="vms_title_lock_note">
        <?php esc_html_e('Auto-title is off. Primary Vendor changes will not update the title unless you confirm.', 'backstage-venue-manager'); ?>
    </div>
    <div class="description" id="vms_title_missing_vendor_note"<?php echo $selected_band_id > 0 ? ' hidden' : ''; ?>>
        <?php esc_html_e('No Primary Vendor is selected. Choose one in Schedule & Lineup before using automatic titles.', 'backstage-venue-manager'); ?>
    </div>
</div>
