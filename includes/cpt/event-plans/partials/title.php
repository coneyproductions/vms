<?php defined('ABSPATH') || exit; ?>
<h4 class="vms-ep-basic-span"><?php esc_html_e('Title', 'backstage-venue-manager'); ?></h4>

<p class="vms-ep-basic-item vms-ep-basic-span">
        <label>
            <input type="checkbox" name="vms_auto_title" value="1" <?php checked($auto_title, '1'); ?> />
            <?php esc_html_e('Auto-update title to Primary Vendor', 'backstage-venue-manager'); ?>
        </label>
    </p>

    <div id="vms_title_preview_wrap" class="vms-ep-basic-item vms-ep-basic-span">
        <span class="description">
            <strong><?php esc_html_e('Title preview:', 'backstage-venue-manager'); ?></strong>
            <span id="vms_title_preview_text"><?php echo esc_html(get_the_title($post->ID)); ?></span>
        </span>
    <div class="description<?php echo checked($auto_title, '1', false) ? ' vms-hidden' : ''; ?>" id="vms_title_lock_note">
        <?php esc_html_e('Auto-title is off. Primary Vendor changes will not update the title unless you confirm.', 'backstage-venue-manager'); ?>
    </div>

    </div>

    </div>
