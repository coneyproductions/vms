    <div id="vms-comp-ack-wrap" class="vms-comp-ack-wrap vms-hidden">

        <div id="vms-pay-override-box" class="vms-comp-ack-section">
            <div class="description">
                <?php esc_html_e('If Draft Pay differs from this venue/date default, acknowledgment is required before Ready, Publish, or Lock.', 'vms'); ?>
            </div>

            <input type="hidden" id="vms_default_source" name="vms_default_source" value="<?php echo esc_attr($default_source); ?>" />
            <input type="hidden" id="vms_default_label" name="vms_default_label" value="<?php echo esc_attr($default_label); ?>" />

            <input type="hidden" id="vms_default_structure" name="vms_default_structure" value="<?php echo esc_attr($default['structure']); ?>" />
            <input type="hidden" id="vms_default_flat_fee_amount" name="vms_default_flat_fee_amount" value="<?php echo esc_attr($default['flat_fee_amount']); ?>" />
            <input type="hidden" id="vms_default_door_split_percent" name="vms_default_door_split_percent" value="<?php echo esc_attr($default['door_split_percent']); ?>" />
            <input type="hidden" id="vms_default_attendance_bonus_mode" name="vms_default_attendance_bonus_mode" value="<?php echo esc_attr($default['attendance_bonus_mode']); ?>" />
            <input type="hidden" id="vms_default_attendance_bonus_start_count" name="vms_default_attendance_bonus_start_count" value="<?php echo esc_attr($default['attendance_bonus_start_count']); ?>" />
            <input type="hidden" id="vms_default_attendance_bonus_step_size" name="vms_default_attendance_bonus_step_size" value="<?php echo esc_attr($default['attendance_bonus_step_size']); ?>" />
            <input type="hidden" id="vms_default_attendance_bonus_step_bonus" name="vms_default_attendance_bonus_step_bonus" value="<?php echo esc_attr($default['attendance_bonus_step_bonus']); ?>" />
            <input type="hidden" id="vms_default_attendance_bonus_per_ticket_rate" name="vms_default_attendance_bonus_per_ticket_rate" value="<?php echo esc_attr($default['attendance_bonus_per_ticket_rate']); ?>" />
            <input type="hidden" id="vms_default_attendance_bonus_max_bonus" name="vms_default_attendance_bonus_max_bonus" value="<?php echo esc_attr($default['attendance_bonus_max_bonus']); ?>" />
            <input type="hidden" id="vms_default_commission_percent" name="vms_default_commission_percent" value="<?php echo esc_attr($default['commission_percent'] ?? ''); ?>" />
            <input type="hidden" id="vms_default_commission_mode" name="vms_default_commission_mode" value="<?php echo esc_attr($default['commission_mode'] ?? ''); ?>" />

            <div id="vms-pay-override-summary" class="description"></div>

            <?php if ($ack === '1' && $ack_ts > 0 && !$ack_still_valid): ?>
                <div class="description vms-pay-override-note">
                    <?php esc_html_e('Note: Draft Pay or venue/date defaults changed since your last acknowledgment. Please re-check the box before saving again.', 'vms'); ?>
                </div>
            <?php endif; ?>

            <?php if ($ack === '1' && $ack_ts > 0): ?>
                <div class="description vms-pay-override-last">
                    <strong><?php esc_html_e('Last acknowledgment:', 'vms'); ?></strong>
                    <?php echo esc_html(date_i18n('M j, Y g:i A', $ack_ts)); ?>
                    <?php if ($ack_user > 0): ?>
                        <?php $u = get_user_by('id', $ack_user); ?>
                        <?php if ($u && !is_wp_error($u)): ?>
                            <?php echo esc_html(' by ' . $u->display_name); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>


    <div id="vms-low-guarantee-box" class="vms-comp-ack-section vms-low-guarantee-box vms-notice vms-notice--warning <?php echo ($vms_requires_low_guarantee_ack ? '' : 'vms-hidden'); ?>">
        <div class="description">
            <?php esc_html_e('If Draft Pay is below the highest guaranteed tile, acknowledgment is required before Ready, Publish, or Lock.', 'vms'); ?>
        </div>

        <div id="vms-low-guarantee-summary" class="description vms-mt-6">
            <?php
            echo esc_html(sprintf(
                __('Selected guaranteed: %1$s. Highest available guaranteed: %2$s.', 'vms'),
                $vms_fmt_money($vms_selected_guarantee),
                $vms_fmt_money($vms_guarantee_max)
            ));
            ?>
        </div>

        <?php if ($vms_low_ack === '1' && $vms_low_ack_ts > 0 && !$vms_low_ack_still_valid): ?>
            <div class="description vms-low-guarantee-note">
                <?php esc_html_e('Note: Draft Pay changed since your last acknowledgment. Please re-check the box before saving again.', 'vms'); ?>
            </div>
        <?php endif; ?>

        <?php if ($vms_low_ack === '1' && $vms_low_ack_ts > 0): ?>
            <div class="description vms-low-guarantee-last">
                <strong><?php esc_html_e('Last acknowledgment:', 'vms'); ?></strong>
                <?php echo esc_html(date_i18n('M j, Y g:i A', $vms_low_ack_ts)); ?>
                <?php if ($vms_low_ack_user > 0): ?>
                    <?php $u = get_user_by('id', $vms_low_ack_user); ?>
                    <?php if ($u && !is_wp_error($u)): ?>
                        <?php echo esc_html(' by ' . $u->display_name); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <label class="vms-pay-override-ack vms-mt-10">
        <input type="checkbox" id="vms_pay_override_ack" name="vms_pay_override_ack" value="1" <?php checked($vms_combined_ack_checked); ?> />
        <strong><?php esc_html_e('I acknowledge this Draft Pay selection may differ from defaults and/or the highest guaranteed option for this event.', 'vms'); ?></strong>
    </label>

    </div>
