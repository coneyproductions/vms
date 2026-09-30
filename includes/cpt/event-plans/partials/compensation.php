<?php defined('ABSPATH') || exit; ?>

    <?php /* Pay Override Acknowledgment lives in the Compensation section above. */ ?>

    <h4 id="vms-compensation" class="vms-collapsible-title" data-section-key="compensation" data-section-summary="<?php echo esc_attr((string) ($vms_actual_draft_summary ?? '')); ?>" data-section-has-data="<?php
        echo (
            ((string) $comp_structure !== 'flat_fee')
            || ((string) $flat_fee_amount !== '')
            || ((string) $door_split_percent !== '')
            || ((string) $commission_percent !== '')
            || ((int) $current_pkg_id > 0)
            || ((string) $selected_opt !== '')
        ) ? '1' : '0';
    ?>"><?php esc_html_e('Primary Vendor Compensation', 'backstage-venue-manager'); ?></h4>

    <?php $vms_comp_ack_html = $this->capture_event_plan_partial('comp-ack', get_defined_vars()); ?>

	    <?php
	        $vms_is_attendance_bonus = ((string) $comp_structure === 'attendance_bonus');
	        $vms_flat_fee_label = $vms_is_attendance_bonus ? __('Base Pay', 'backstage-venue-manager') : __('Flat Fee Amount', 'backstage-venue-manager');
	        $vms_comp_has_data = (
	            ((string) $comp_structure !== 'flat_fee')
	            || ((string) $flat_fee_amount !== '')
	            || ((string) $door_split_percent !== '')
	            || ((string) $attendance_bonus_mode !== '')
	            || ((string) $attendance_bonus_start_count !== '')
	            || ((string) $attendance_bonus_step_size !== '')
	            || ((string) $attendance_bonus_step_bonus !== '')
	            || ((string) $attendance_bonus_per_ticket_rate !== '')
	            || ((string) $attendance_bonus_max_bonus !== '')
	            || ((string) $commission_percent !== '')
                || ((string) $deposit_amount !== '')
                || ((string) $deposit_status !== '' && (string) $deposit_status !== 'not_required')
                || ((string) $deposit_due_date !== '')
                || ((string) $deposit_paid_date !== '')
                || ((string) $deposit_notes !== '')
	            || ((int) $current_pkg_id > 0)
	            || ((string) $selected_opt !== '')
	        );
	    ?>
    <div class="vms-ep-card vms-ep-card--white vms-ep-card--comp" data-vms-section-has-data="<?php echo $vms_comp_has_data ? '1' : '0'; ?>">
        <div class="vms-ep-comp-heading">
            <strong><?php esc_html_e('Compensation Options', 'backstage-venue-manager'); ?></strong>
            <?php
            if (function_exists('bvmgr_help_icon')) {
                bvmgr_help_icon(
                    __('Load Draft Pay from a tile, review it, then lock it for this event. Draft Pay is what you are editing; Locked Pay is the agreed payout source for this event. Locking reviewed Draft Pay protects this event from later default changes.', 'backstage-venue-manager'),
                    __('Compensation Options help', 'backstage-venue-manager')
                );
            }
            ?>
        </div>

        <div id="vms-comp-options" data-nonce="<?php echo esc_attr($comp_options_nonce); ?>">
            <?php echo $this->render_event_plan_compensation_options_response_html($comp_opts, (int) $current_pkg_id, (string) $selected_opt); ?>
        </div>

	        <input type="hidden" id="vms_max_guarantee_available" value="<?php echo esc_attr((string) ((float) ($comp_opts['max_guarantee'] ?? 0.0))); ?>" />
	        <input type="hidden" id="vms_comp_package_id" name="vms_comp_package_id" value="<?php echo esc_attr((string) (int) $current_pkg_id); ?>" />
	        <input type="hidden" id="vms_comp_selected_option" name="vms_comp_selected_option" value="<?php echo esc_attr((string) $selected_opt); ?>" />

        <?php if (!empty($vms_show_vendor_default_drift_notice)): ?>
            <div class="vms-ep-card vms-ep-card--amber vms-notice vms-notice--warning vms-mt-10">
                <strong class="vms-text-warn">⚠️ <?php esc_html_e('Live Primary Vendor default differs from Draft Pay', 'backstage-venue-manager'); ?></strong><br>
                <span class="description"><?php esc_html_e('The current vendor profile resolves to different default terms for this venue/date. Draft Pay stays as-is until you choose to apply the updated default.', 'backstage-venue-manager'); ?></span>

                <?php if (!empty($vms_vendor_default_subtitle)): ?>
                    <div class="description vms-mt-6"><strong><?php esc_html_e('Winning source:', 'backstage-venue-manager'); ?></strong> <?php echo esc_html((string) $vms_vendor_default_subtitle); ?></div>
                <?php endif; ?>

                <div class="description vms-mt-6"><strong><?php esc_html_e('Resolved live default:', 'backstage-venue-manager'); ?></strong> <?php echo esc_html($vms_vendor_default_summary !== '' ? (string) $vms_vendor_default_summary : __('Configured, but no summary available.', 'backstage-venue-manager')); ?></div>
                <div class="description vms-mt-4"><strong><?php esc_html_e('Current Draft Pay:', 'backstage-venue-manager'); ?></strong> <?php echo esc_html($vms_actual_draft_summary !== '' ? (string) $vms_actual_draft_summary : __('Draft Pay is set, but no summary is available.', 'backstage-venue-manager')); ?></div>

                <?php if (!empty($vms_vendor_default_source_rows) && is_array($vms_vendor_default_source_rows)): ?>
                    <div class="description vms-mt-8"><strong><?php esc_html_e('Source ladder:', 'backstage-venue-manager'); ?></strong></div>
                    <ul class="description vms-mt-4 vms-mb-0" style="margin-left:18px;">
                        <?php foreach ($vms_vendor_default_source_rows as $vms_source_row): ?>
                            <li>
                                <strong><?php echo esc_html((string) ($vms_source_row['label'] ?? '')); ?>:</strong>
                                <?php echo esc_html((string) ($vms_source_row['summary'] ?? '')); ?>
                                <?php if (!empty($vms_source_row['is_active'])): ?>
                                    <em><?php esc_html_e('(winning source)', 'backstage-venue-manager'); ?></em>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($vms_vendor_default_diff_rows) && is_array($vms_vendor_default_diff_rows)): ?>
                    <div class="description vms-mt-8"><strong><?php esc_html_e('Fields that differ:', 'backstage-venue-manager'); ?></strong></div>
                    <ul class="description vms-mt-4 vms-mb-0" style="margin-left:18px;">
                        <?php foreach ($vms_vendor_default_diff_rows as $vms_diff_row): ?>
                            <li>
                                <strong><?php echo esc_html((string) ($vms_diff_row['label'] ?? '')); ?>:</strong>
                                <?php esc_html_e('Live', 'backstage-venue-manager'); ?> <?php echo esc_html((string) ($vms_diff_row['live'] ?? '—')); ?> ·
                                <?php esc_html_e('Draft', 'backstage-venue-manager'); ?> <?php echo esc_html((string) ($vms_diff_row['draft'] ?? '—')); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <p class="vms-mt-8 vms-mb-0">
                    <button type="submit" name="vms_event_plan_action" value="apply_vendor_defaults" class="button button-secondary">
                        <?php esc_html_e('Apply current Primary Vendor default to Draft Pay', 'backstage-venue-manager'); ?>
                    </button>
                </p>
            </div>
        <?php endif; ?>

        <p class="vms-mt-10 vms-mb-0">
            <label>
                <input type="checkbox" id="vms_auto_comp_venue" name="vms_auto_comp_venue" value="1" <?php checked($auto_comp_venue, '1'); ?> />
                <?php esc_html_e('Auto-fill Draft Pay from date defaults (Holiday overrides Venue when configured)', 'backstage-venue-manager'); ?>
            </label>
            <br />
            <span id="vms-venue-defaults-hint" class="description"></span>
        </p>

        <div class="vms-ep-draft-pay-title">
            <strong><?php esc_html_e('Draft Pay (Editable)', 'backstage-venue-manager'); ?></strong>
            <?php
            if (function_exists('bvmgr_help_icon')) {
                $vms_draft_pay_help = __('These fields stay editable even after you load a default or package. When comparable guarantees are available, tile colors progress from lower guaranteed pay to higher guaranteed pay.', 'backstage-venue-manager');
                bvmgr_help_icon($vms_draft_pay_help, __('Draft Pay help', 'backstage-venue-manager'));
            }
            ?>
        </div>

                <div class="vms-comp-tiles" id="vms-comp-tiles" role="radiogroup" aria-label="<?php esc_attr_e('Compensation Structure', 'backstage-venue-manager'); ?>">
            <?php
	            $vms_tile_defs = array(
	                'flat_fee' => array(
	                    'title' => __('Flat Fee', 'backstage-venue-manager'),
	                    'sub' => __('Guaranteed payout', 'backstage-venue-manager'),
	                ),
                'door_split' => array(
                    'title' => __('Door Split', 'backstage-venue-manager'),
                    'sub' => __('Guaranteed payout (0) • variable payout', 'backstage-venue-manager'),
                ),
	                'flat_fee_door_split' => array(
	                    'title' => __('Flat Fee + Door Split', 'backstage-venue-manager'),
	                    'sub' => __('Guaranteed payout + variable split', 'backstage-venue-manager'),
	                ),
	                'attendance_bonus' => array(
	                    'title' => __('Base + Attendance Bonus', 'backstage-venue-manager'),
	                    'sub' => __('Guaranteed payout + variable attendance bonus', 'backstage-venue-manager'),
	                ),
	            );

            // Scale structure tiles against the highest relevant guarantee for this event.
            // This keeps Draft Pay cards visually comparable to available defaults (e.g., 300 vs 500).
            $vms_struct_scale_ref_max = max((float) $vms_struct_guarantee_max, (float) $vms_guarantee_max);
            if ($vms_struct_scale_ref_max < 0) $vms_struct_scale_ref_max = 0.0;
            $vms_struct_scale_class_for = static function(float $g) use ($vms_struct_scale_ref_max): string {
                if ($vms_struct_scale_ref_max <= 0) return '';
                if ($g < 0) $g = 0.0;

                $ratio = $g / $vms_struct_scale_ref_max;
                if ($ratio < 0) $ratio = 0;
                if ($ratio > 1) $ratio = 1;

                $bucket = (int) floor($ratio * 4.0); // 0..4
                $bucket = max(0, min(4, $bucket));
                return ' vms-comp-tile--scale-' . (string) ($bucket + 1);
            };

            foreach ($vms_tile_defs as $k => $def):
                $is_sel = ((string)$comp_structure === (string)$k);
                $g = isset($vms_struct_guarantee_map[$k]) ? (float)$vms_struct_guarantee_map[$k] : 0.0;
                $is_max = ($vms_struct_guarantee_max > 0 && (float)$g === (float)$vms_struct_guarantee_max);
                $vms_scale_class = $vms_struct_scale_class_for((float) $g);
            ?>
                <button type="button"
                    class="vms-comp-tile<?php echo esc_attr($vms_scale_class); ?> <?php echo ($is_sel ? 'is-selected' : ''); ?>"
                    data-structure="<?php echo esc_attr($k); ?>"
                    role="radio"
                    aria-checked="<?php echo ($is_sel ? 'true' : 'false'); ?>">
                    <div class="vms-comp-tile__title"><?php echo esc_html($def['title']); ?></div>
                    <div class="vms-comp-tile__guarantee" data-guarantee-for="<?php echo esc_attr($k); ?>">
                        <?php echo esc_html($vms_fmt_money($g)); ?>
                    </div>
                    <div class="vms-comp-tile__sub"><?php echo esc_html($def['sub']); ?></div>
                </button>
            <?php endforeach; ?>
        </div>
	        <div class="vms-ep-pay-sections">
	            <div class="vms-ep-pay-section vms-ep-pay-section--vendor">
	                <div class="vms-ep-pay-section__header">
	                    <h4>
	                        <?php esc_html_e('Vendor Pay', 'backstage-venue-manager'); ?>
	                        <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('What the vendor is paid for this event.', 'backstage-venue-manager'), __('Vendor Pay help', 'backstage-venue-manager')); ?>
	                    </h4>
	                </div>
	                <div class="vms-ep-draft-pay-grid">
	                    <p class="vms-comp-structure-select vms-comp-field--structure">
	                        <label for="vms_comp_structure"><strong><?php esc_html_e('Structure', 'backstage-venue-manager'); ?></strong></label><br />
	                        <select id="vms_comp_structure" name="vms_comp_structure">
	                            <option value="flat_fee" <?php selected($comp_structure, 'flat_fee'); ?>><?php esc_html_e('Flat Fee', 'backstage-venue-manager'); ?></option>
	                            <option value="door_split" <?php selected($comp_structure, 'door_split'); ?>><?php esc_html_e('Door Split', 'backstage-venue-manager'); ?></option>
	                            <option value="flat_fee_door_split" <?php selected($comp_structure, 'flat_fee_door_split'); ?>><?php esc_html_e('Flat Fee + Door Split', 'backstage-venue-manager'); ?></option>
	                            <option value="attendance_bonus" <?php selected($comp_structure, 'attendance_bonus'); ?>><?php esc_html_e('Base + Attendance Bonus', 'backstage-venue-manager'); ?></option>
	                        </select>
	                    </p>
	                    <p class="vms-comp-field vms-comp-field--base" data-show-when="flat_fee,flat_fee_door_split,attendance_bonus">
	                        <span class="vms-comp-field-label">
	                            <label for="vms_flat_fee_amount"><strong><span id="vms_flat_fee_amount_label_text"><?php echo esc_html($vms_flat_fee_label); ?></span></strong></label>
	                            <span id="vms_flat_fee_amount_help" class="<?php echo $vms_is_attendance_bonus ? '' : 'vms-hidden'; ?>">
	                                <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('The guaranteed amount for this event before attendance bonuses.', 'backstage-venue-manager'), __('Base Pay help', 'backstage-venue-manager')); ?>
	                            </span>
	                        </span><br />
	                        <input type="text" inputmode="decimal" autocomplete="off" id="vms_flat_fee_amount" name="vms_flat_fee_amount" class="vms-ep-input-sm" value="<?php echo esc_attr($flat_fee_amount); ?>" placeholder="<?php esc_attr_e('0.00', 'backstage-venue-manager'); ?>" />
	                    </p>
	                    <p class="vms-comp-field vms-comp-field--door" data-show-when="door_split,flat_fee_door_split">
	                        <label for="vms_door_split_percent"><strong><?php esc_html_e('Door Split %', 'backstage-venue-manager'); ?></strong></label><br />
	                        <input type="text" inputmode="decimal" autocomplete="off" id="vms_door_split_percent" name="vms_door_split_percent" class="vms-ep-input-sm" value="<?php echo esc_attr($door_split_percent); ?>" placeholder="<?php esc_attr_e('0', 'backstage-venue-manager'); ?>" /> %
	                    </p>
	                    <p class="vms-comp-field vms-comp-field--bonus-mode" data-show-when="attendance_bonus">
	                        <label for="vms_attendance_bonus_mode"><strong><?php esc_html_e('Bonus Style', 'backstage-venue-manager'); ?></strong></label><br />
	                        <select id="vms_attendance_bonus_mode" name="vms_attendance_bonus_mode" class="vms-ep-input-sm">
	                            <option value=""><?php esc_html_e('Select bonus style', 'backstage-venue-manager'); ?></option>
	                            <option value="step" <?php selected($attendance_bonus_mode, 'step'); ?>><?php esc_html_e('Step', 'backstage-venue-manager'); ?></option>
	                            <option value="continuous" <?php selected($attendance_bonus_mode, 'continuous'); ?>><?php esc_html_e('Continuous', 'backstage-venue-manager'); ?></option>
	                        </select>
	                    </p>
	                    <p class="vms-comp-field vms-comp-field--bonus-start" data-show-when="attendance_bonus">
	                        <span class="vms-comp-field-label">
	                            <label for="vms_attendance_bonus_start_count"><strong><?php esc_html_e('Starts After', 'backstage-venue-manager'); ?></strong></label>
	                            <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('No attendance bonus is earned until attendance goes above this number.', 'backstage-venue-manager'), __('Attendance bonus starting count help', 'backstage-venue-manager')); ?>
	                        </span><br />
	                        <input type="text" inputmode="numeric" autocomplete="off" id="vms_attendance_bonus_start_count" name="vms_attendance_bonus_start_count" class="vms-ep-input-sm" value="<?php echo esc_attr($attendance_bonus_start_count); ?>" placeholder="<?php esc_attr_e('0', 'backstage-venue-manager'); ?>" />
	                    </p>
	                    <p class="vms-comp-field vms-comp-field--bonus-step-size" data-show-when="attendance_bonus" data-show-when-mode="step">
	                        <span class="vms-comp-field-label">
	                            <label for="vms_attendance_bonus_step_size"><strong><?php esc_html_e('Step Size', 'backstage-venue-manager'); ?></strong></label>
	                            <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('How many additional tickets are needed to earn each bonus step.', 'backstage-venue-manager'), __('Attendance bonus step size help', 'backstage-venue-manager')); ?>
	                        </span><br />
	                        <input type="text" inputmode="numeric" autocomplete="off" id="vms_attendance_bonus_step_size" name="vms_attendance_bonus_step_size" class="vms-ep-input-sm" value="<?php echo esc_attr($attendance_bonus_step_size); ?>" placeholder="<?php esc_attr_e('0', 'backstage-venue-manager'); ?>" />
	                    </p>
	                    <p class="vms-comp-field vms-comp-field--bonus-step-bonus" data-show-when="attendance_bonus" data-show-when-mode="step">
	                        <span class="vms-comp-field-label">
	                            <label for="vms_attendance_bonus_step_bonus"><strong><?php esc_html_e('Bonus Per Step', 'backstage-venue-manager'); ?></strong></label>
	                            <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('The amount added each time a step is reached.', 'backstage-venue-manager'), __('Attendance bonus amount help', 'backstage-venue-manager')); ?>
	                        </span><br />
	                        <input type="text" inputmode="decimal" autocomplete="off" id="vms_attendance_bonus_step_bonus" name="vms_attendance_bonus_step_bonus" class="vms-ep-input-sm" value="<?php echo esc_attr($attendance_bonus_step_bonus); ?>" placeholder="<?php esc_attr_e('0.00', 'backstage-venue-manager'); ?>" />
	                    </p>
	                    <p class="vms-comp-field vms-comp-field--bonus-rate" data-show-when="attendance_bonus" data-show-when-mode="continuous">
	                        <span class="vms-comp-field-label">
	                            <label for="vms_attendance_bonus_per_ticket_rate"><strong><?php esc_html_e('Bonus Per Ticket', 'backstage-venue-manager'); ?></strong></label>
	                            <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('The amount added for each ticket above the starting count.', 'backstage-venue-manager'), __('Attendance bonus per-ticket help', 'backstage-venue-manager')); ?>
	                        </span><br />
	                        <input type="text" inputmode="decimal" autocomplete="off" id="vms_attendance_bonus_per_ticket_rate" name="vms_attendance_bonus_per_ticket_rate" class="vms-ep-input-sm" value="<?php echo esc_attr($attendance_bonus_per_ticket_rate); ?>" placeholder="<?php esc_attr_e('0.00', 'backstage-venue-manager'); ?>" />
	                    </p>
	                    <p class="vms-comp-field vms-comp-field--bonus-max" data-show-when="attendance_bonus">
	                        <span class="vms-comp-field-label">
	                            <label for="vms_attendance_bonus_max_bonus"><strong><?php esc_html_e('Bonus Cap', 'backstage-venue-manager'); ?></strong></label>
	                            <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('Optional cap on the total attendance bonus. Leave blank for no cap.', 'backstage-venue-manager'), __('Attendance bonus cap help', 'backstage-venue-manager')); ?>
	                        </span><br />
	                        <input type="text" inputmode="decimal" autocomplete="off" id="vms_attendance_bonus_max_bonus" name="vms_attendance_bonus_max_bonus" class="vms-ep-input-sm" value="<?php echo esc_attr($attendance_bonus_max_bonus); ?>" placeholder="<?php esc_attr_e('Optional', 'backstage-venue-manager'); ?>" />
	                    </p>
	                </div>
	            </div>
                <div class="vms-ep-pay-section vms-ep-pay-section--deposit">
                    <div class="vms-ep-pay-section__header">
                        <h4>
                            <?php esc_html_e('Deposit / Advance', 'backstage-venue-manager'); ?>
                            <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('Optional event-level deposit terms. These are separate from final pay and are included in Locked Pay snapshots for agreement packets.', 'backstage-venue-manager'), __('Deposit and advance help', 'backstage-venue-manager')); ?>
                        </h4>
                    </div>
                    <div class="vms-ep-agent-grid">
                        <p class="vms-comp-field vms-comp-field--deposit-amount">
                            <span class="vms-comp-field-label">
                                <label for="vms_deposit_amount"><strong><?php esc_html_e('Deposit Amount', 'backstage-venue-manager'); ?></strong></label>
                                <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('Leave blank when no deposit is required.', 'backstage-venue-manager'), __('Deposit Amount help', 'backstage-venue-manager')); ?>
                            </span><br />
                            <input type="text" inputmode="decimal" autocomplete="off" id="vms_deposit_amount" name="vms_deposit_amount" class="vms-ep-input-sm" value="<?php echo esc_attr($deposit_amount); ?>" placeholder="<?php esc_attr_e('0.00', 'backstage-venue-manager'); ?>" />
                        </p>
                        <p class="vms-comp-field vms-comp-field--deposit-status">
                            <label for="vms_deposit_status"><strong><?php esc_html_e('Status', 'backstage-venue-manager'); ?></strong></label><br />
                            <select id="vms_deposit_status" name="vms_deposit_status" class="vms-ep-input-sm">
                                <?php foreach ((array) $deposit_status_options as $value => $label): ?>
                                    <option value="<?php echo esc_attr((string) $value); ?>" <?php selected($deposit_status, (string) $value); ?>><?php echo esc_html((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="vms-comp-field vms-comp-field--deposit-treatment" data-vms-deposit-details>
                            <label for="vms_deposit_treatment"><strong><?php esc_html_e('Treatment', 'backstage-venue-manager'); ?></strong></label><br />
                            <select id="vms_deposit_treatment" name="vms_deposit_treatment" class="vms-ep-input-sm">
                                <?php foreach ((array) $deposit_treatment_options as $value => $label): ?>
                                    <option value="<?php echo esc_attr((string) $value); ?>" <?php selected($deposit_treatment, (string) $value); ?>><?php echo esc_html((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="vms-comp-field vms-comp-field--deposit-due" data-vms-deposit-details>
                            <label for="vms_deposit_due_date"><strong><?php esc_html_e('Due Date', 'backstage-venue-manager'); ?></strong></label><br />
                            <input type="date" id="vms_deposit_due_date" name="vms_deposit_due_date" class="vms-ep-input-sm" value="<?php echo esc_attr($deposit_due_date); ?>" />
                        </p>
                        <p class="vms-comp-field vms-comp-field--deposit-paid" data-vms-deposit-details>
                            <label for="vms_deposit_paid_date"><strong><?php esc_html_e('Paid Date', 'backstage-venue-manager'); ?></strong></label><br />
                            <input type="date" id="vms_deposit_paid_date" name="vms_deposit_paid_date" class="vms-ep-input-sm" value="<?php echo esc_attr($deposit_paid_date); ?>" />
                        </p>
                        <p class="vms-comp-field vms-comp-field--deposit-notes" data-vms-deposit-details>
                            <span class="vms-comp-field-label">
                                <label for="vms_deposit_notes"><strong><?php esc_html_e('Deposit Notes', 'backstage-venue-manager'); ?></strong></label>
                                <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('Use this for plain-English details such as refund deadline, crediting rule, or special agreement context.', 'backstage-venue-manager'), __('Deposit Notes help', 'backstage-venue-manager')); ?>
                            </span><br />
                            <textarea id="vms_deposit_notes" name="vms_deposit_notes" rows="2" class="large-text" placeholder="<?php esc_attr_e('Optional agreement wording or internal note.', 'backstage-venue-manager'); ?>"><?php echo esc_textarea($deposit_notes); ?></textarea>
                        </p>
                    </div>
                    <?php
                    $vms_deposit_summary = function_exists('bvmgr_comp_deposit_summary_part')
                        ? (string) bvmgr_comp_deposit_summary_part(array(
                            'deposit_amount' => $deposit_amount,
                            'deposit_status' => $deposit_status,
                            'deposit_treatment' => $deposit_treatment,
                            'deposit_due_date' => $deposit_due_date,
                            'deposit_paid_date' => $deposit_paid_date,
                            'deposit_notes' => $deposit_notes,
                        ))
                        : '';
                    ?>
                    <?php if ($vms_deposit_summary !== ''): ?>
                        <div class="vms-ep-card vms-ep-card--gray vms-mt-10">
                            <?php echo esc_html($vms_deposit_summary); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="vms-ep-pay-section vms-ep-pay-section--final-payment">
                    <div class="vms-ep-pay-section__header">
                        <h4>
                            <?php esc_html_e('Final Payment', 'backstage-venue-manager'); ?>
                            <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('When and how the remaining vendor payment is expected to be paid. These terms are captured in agreement snapshots.', 'backstage-venue-manager'), __('Final Payment help', 'backstage-venue-manager')); ?>
                        </h4>
                    </div>
                    <div class="vms-ep-agent-grid">
                        <p class="vms-comp-field vms-comp-field--final-payment-timing">
                            <label for="vms_final_payment_timing"><strong><?php esc_html_e('Expected Final Payment', 'backstage-venue-manager'); ?></strong></label><br />
                            <select id="vms_final_payment_timing" name="vms_final_payment_timing" class="vms-ep-input-sm">
                                <?php foreach ((array) $final_payment_timing_options as $value => $label): ?>
                                    <option value="<?php echo esc_attr((string) $value); ?>" <?php selected($final_payment_timing, (string) $value); ?>><?php echo esc_html((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="vms-comp-field vms-comp-field--final-payment-days" data-vms-final-payment-timing="days_after">
                            <span class="vms-comp-field-label">
                                <label for="vms_final_payment_days_after"><strong><?php esc_html_e('Days After Event', 'backstage-venue-manager'); ?></strong></label>
                                <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('Used when Expected Final Payment is N days after event.', 'backstage-venue-manager'), __('Days After Event help', 'backstage-venue-manager')); ?>
                            </span><br />
                            <input type="text" inputmode="numeric" autocomplete="off" id="vms_final_payment_days_after" name="vms_final_payment_days_after" class="vms-ep-input-sm" value="<?php echo esc_attr($final_payment_days_after); ?>" placeholder="<?php esc_attr_e('Example: 7', 'backstage-venue-manager'); ?>" />
                        </p>
                        <p class="vms-comp-field vms-comp-field--final-payment-date" data-vms-final-payment-timing="fixed_date">
                            <span class="vms-comp-field-label">
                                <label for="vms_final_payment_date"><strong><?php esc_html_e('Specific Pay Date', 'backstage-venue-manager'); ?></strong></label>
                                <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('Used when Expected Final Payment is Specific date.', 'backstage-venue-manager'), __('Specific Pay Date help', 'backstage-venue-manager')); ?>
                            </span><br />
                            <input type="date" id="vms_final_payment_date" name="vms_final_payment_date" class="vms-ep-input-sm" value="<?php echo esc_attr($final_payment_date); ?>" />
                        </p>
                        <p class="vms-comp-field vms-comp-field--final-payment-custom" data-vms-final-payment-timing="custom">
                            <span class="vms-comp-field-label">
                                <label for="vms_final_payment_custom_text"><strong><?php esc_html_e('Custom Timing', 'backstage-venue-manager'); ?></strong></label>
                                <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('Used when Expected Final Payment is Custom timing.', 'backstage-venue-manager'), __('Custom Timing help', 'backstage-venue-manager')); ?>
                            </span><br />
                            <input type="text" id="vms_final_payment_custom_text" name="vms_final_payment_custom_text" class="regular-text" value="<?php echo esc_attr($final_payment_custom_text); ?>" placeholder="<?php esc_attr_e('Example: after settlement is approved', 'backstage-venue-manager'); ?>" />
                        </p>
                        <p class="vms-comp-field vms-comp-field--final-payment-method">
                            <label for="vms_final_payment_method"><strong><?php esc_html_e('Payment Method', 'backstage-venue-manager'); ?></strong></label><br />
                            <select id="vms_final_payment_method" name="vms_final_payment_method" class="vms-ep-input-sm">
                                <?php foreach ((array) $final_payment_method_options as $value => $label): ?>
                                    <option value="<?php echo esc_attr((string) $value); ?>" <?php selected($final_payment_method, (string) $value); ?>><?php echo esc_html((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="vms-comp-field vms-comp-field--final-payment-method-other" data-vms-final-payment-method="other">
                            <span class="vms-comp-field-label">
                                <label for="vms_final_payment_method_other"><strong><?php esc_html_e('Other Method', 'backstage-venue-manager'); ?></strong></label>
                                <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('Used when Payment Method is Other.', 'backstage-venue-manager'), __('Other Method help', 'backstage-venue-manager')); ?>
                            </span><br />
                            <input type="text" id="vms_final_payment_method_other" name="vms_final_payment_method_other" class="regular-text" value="<?php echo esc_attr($final_payment_method_other); ?>" placeholder="<?php esc_attr_e('Describe payment method', 'backstage-venue-manager'); ?>" />
                        </p>
                    </div>
                    <?php
                    $vms_final_payment_summary = function_exists('bvmgr_comp_final_payment_summary_part')
                        ? (string) bvmgr_comp_final_payment_summary_part(array(
                            'final_payment_timing' => $final_payment_timing,
                            'final_payment_days_after' => $final_payment_days_after,
                            'final_payment_date' => $final_payment_date,
                            'final_payment_custom_text' => $final_payment_custom_text,
                            'final_payment_method' => $final_payment_method,
                            'final_payment_method_other' => $final_payment_method_other,
                        ))
                        : '';
                    ?>
                    <?php if ($vms_final_payment_summary !== ''): ?>
                        <div class="vms-ep-card vms-ep-card--gray vms-mt-10">
                            <?php echo esc_html($vms_final_payment_summary); ?>
                        </div>
                    <?php endif; ?>
                </div>
	            <div class="vms-ep-pay-section vms-ep-pay-section--agent" data-show-when="flat_fee,flat_fee_door_split,attendance_bonus">
	                <div class="vms-ep-pay-section__header">
	                    <h4>
	                        <?php esc_html_e('Agent Fee', 'backstage-venue-manager'); ?>
	                        <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('Separate event expense. Blank or 0 means none for this event.', 'backstage-venue-manager'), __('Agent Fee help', 'backstage-venue-manager')); ?>
	                    </h4>
	                </div>
	                <div class="vms-ep-agent-grid">
	                    <p class="vms-comp-field vms-comp-field--agent-pct">
	                        <span class="vms-comp-field-label">
	                            <label for="vms_commission_percent"><strong><?php esc_html_e('Agent Fee %', 'backstage-venue-manager'); ?></strong></label>
	                            <?php if (function_exists('bvmgr_help_icon')) bvmgr_help_icon(__('Tracked as its own expense, separate from vendor pay.', 'backstage-venue-manager'), __('Agent Fee percentage help', 'backstage-venue-manager')); ?>
	                        </span><br />
	                        <input type="text" inputmode="decimal" autocomplete="off" id="vms_commission_percent" name="vms_commission_percent" class="vms-ep-input-sm" value="<?php echo esc_attr($commission_percent); ?>" placeholder="<?php esc_attr_e('0', 'backstage-venue-manager'); ?>" />
	                    </p>
	                    <p class="vms-comp-field vms-comp-field--agent-basis">
	                        <label for="vms_commission_mode"><strong><?php esc_html_e('Basis', 'backstage-venue-manager'); ?></strong></label><br />
	                        <select id="vms_commission_mode" name="vms_commission_mode" class="vms-ep-input-sm">
	                            <option value="artist_fee" <?php selected($commission_mode, 'artist_fee'); ?>><?php esc_html_e('Added on top of vendor pay', 'backstage-venue-manager'); ?></option>
	                            <option value="gross" <?php selected($commission_mode, 'gross'); ?>><?php esc_html_e('Based on gross / settlement', 'backstage-venue-manager'); ?></option>
	                        </select>
	                    </p>
	                </div>
	                <div id="vms-agent-fee-summary" class="vms-ep-card vms-ep-card--gray vms-mt-10" hidden></div>
	            </div>
	        </div>
	        <div id="vms-attendance-bonus-preview" class="vms-attendance-preview<?php echo $vms_is_attendance_bonus ? '' : ' vms-hidden'; ?>" data-vms-tour="event-plan.attendance-preview">
	            <strong class="vms-attendance-preview__title"><?php esc_html_e('Attendance Bonus Preview', 'backstage-venue-manager'); ?></strong>
	            <div id="vms-attendance-bonus-formula" class="description vms-mt-6"></div>
	            <div id="vms-attendance-bonus-preview-table" class="vms-attendance-preview__table vms-mt-8"></div>
	        </div>
	    </div>

    <?php if ($out_of_sync): ?>
        <div class="vms-ep-card vms-ep-card--amber vms-notice vms-notice--warning">
            <strong class="vms-text-warn">⚠️ <?php esc_html_e('Draft Pay differs from Locked Snapshot', 'backstage-venue-manager'); ?></strong><br>
            <span class="description"><?php esc_html_e('Draft Pay is newer than Locked Pay. Payout still uses Locked Pay until you lock again.', 'backstage-venue-manager'); ?></span>
        </div>
    <?php endif; ?>

<div class="vms-ep-review-stack">
    <?php if (!empty($snapshot)): ?>
        <div class="vms-ep-card vms-ep-card--gray vms-ep-review-card vms-ep-review-card--locked">
            <strong><?php esc_html_e('Locked Pay Snapshot (Current payout source)', 'backstage-venue-manager'); ?></strong><br>
            <div class="vms-mt-6">
                <?php
                $summary = function_exists('bvmgr_snapshot_summary_line') ? (string) bvmgr_snapshot_summary_line($snapshot) : '';
                echo esc_html($summary !== '' ? $summary : __('Pay structure locked, but no values recorded.', 'backstage-venue-manager'));
                ?>
            </div>

            <?php if (!empty($snapshot['package_title'])): ?>
                <div class="description vms-mt-6">
                    <strong><?php esc_html_e('Package:', 'backstage-venue-manager'); ?></strong> <?php echo esc_html((string)$snapshot['package_title']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($snapshot['applied_at'])): ?>
                <div class="description vms-mt-4">
                    <strong><?php esc_html_e('Applied:', 'backstage-venue-manager'); ?></strong>
                    <?php echo esc_html(date_i18n('D M j, Y g:i A', strtotime((string)$snapshot['applied_at']))); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($vms_comp_ack_html)) echo $vms_comp_ack_html; ?>

    <?php
    echo function_exists('bvmgr_event_plan_render_lock_pay_actions_html')
        ? bvmgr_event_plan_render_lock_pay_actions_html((array) $lock_pay_basics_state) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes all dynamic values.
        : '';
    ?>
</div>

<div data-vms-collapsible-break="1" hidden aria-hidden="true"></div>
