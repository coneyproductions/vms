<?php defined('ABSPATH') || exit; ?>
    <h4><?php esc_html_e('Event Plan Status & Workflow', 'backstage-venue-manager'); ?></h4>
    <?php
        $vms_cancel_has_data = (
            $plan_status === 'cancelled'
            || $cancel_policy !== 'status_only'
            || $cancel_reason_code !== ''
            || $cancel_reason_note !== ''
            || $cancel_vendor_message !== ''
        );
    ?>
    <h4 id="vms-cancellation" class="vms-collapsible-title" data-section-key="cancellation" data-section-has-data="<?php echo $vms_cancel_has_data ? '1' : '0'; ?>"><?php esc_html_e('Cancellation', 'backstage-venue-manager'); ?></h4>
    <div data-vms-section-has-data="<?php echo $vms_cancel_has_data ? '1' : '0'; ?>">
    <p>
        <label for="vms_cancel_policy"><strong><?php esc_html_e('Cancellation policy', 'backstage-venue-manager'); ?></strong></label><br>
        <select name="vms_cancel_policy" id="vms_cancel_policy">
            <?php foreach ($cancel_policy_options as $policy_key => $policy_label) : ?>
                <option value="<?php echo esc_attr((string) $policy_key); ?>" <?php selected($cancel_policy, (string) $policy_key); ?>>
                    <?php echo esc_html((string) $policy_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="vms_cancel_reason_code"><strong><?php esc_html_e('Cancellation reason', 'backstage-venue-manager'); ?></strong></label><br>
        <select name="vms_cancel_reason_code" id="vms_cancel_reason_code">
            <option value="" <?php selected($cancel_reason_code, ''); ?>><?php esc_html_e('Select reason (optional)', 'backstage-venue-manager'); ?></option>
            <?php foreach ($cancel_reason_options as $reason_key => $reason_label) : ?>
                <option value="<?php echo esc_attr((string) $reason_key); ?>" <?php selected($cancel_reason_code, (string) $reason_key); ?>>
                    <?php echo esc_html((string) $reason_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="vms_cancel_reason_note"><strong><?php esc_html_e('Cancellation note', 'backstage-venue-manager'); ?></strong></label><br>
        <textarea
            name="vms_cancel_reason_note"
            id="vms_cancel_reason_note"
            rows="3"
            class="large-text"
            placeholder="<?php esc_attr_e('Optional internal note for cancellation context. This is not included in vendor/staff emails.', 'backstage-venue-manager'); ?>"
        ><?php echo esc_textarea($cancel_reason_note); ?></textarea>
    </p>

    <p>
        <label for="vms_cancel_vendor_message"><strong><?php esc_html_e('Primary vendor email message', 'backstage-venue-manager'); ?></strong></label><br>
        <textarea
            name="vms_cancel_vendor_message"
            id="vms_cancel_vendor_message"
            rows="5"
            class="large-text"
            placeholder="<?php esc_attr_e('Optional message sent only to the primary vendor when this Event Plan is cancelled.', 'backstage-venue-manager'); ?>"
        ><?php echo esc_textarea($cancel_vendor_message); ?></textarea>
        <span class="description"><?php esc_html_e('Staff, secondary vendors, and lineup/supporting vendors receive the standard cancellation notice. The internal cancellation note above stays internal.', 'backstage-venue-manager'); ?></span>
    </p>

    <input type="hidden" name="vms_cancel_auto_refund_confirmed" id="vms_cancel_auto_refund_confirmed" value="0" />

    <?php $this->render_cancellation_job_panel((int) $post->ID, (string) $plan_status); ?>

    <?php if (function_exists('vms_event_credits_render_event_plan_panel')) { vms_event_credits_render_event_plan_panel((int) $post->ID, (string) $plan_status); } ?>

    <?php
        $k_rescheduled_from = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'rescheduled_from_plan_id') ?: '_vms_rescheduled_from_plan_id')
            : '_vms_rescheduled_from_plan_id';
        $k_rescheduled_to = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'rescheduled_to_plan_ids') ?: '_vms_rescheduled_to_plan_ids')
            : '_vms_rescheduled_to_plan_ids';
        $rescheduled_from_id = absint(get_post_meta($post->ID, $k_rescheduled_from, true));
        $rescheduled_to_ids = function_exists('vms_event_plan_normalize_related_plan_ids')
            ? vms_event_plan_normalize_related_plan_ids(get_post_meta($post->ID, $k_rescheduled_to, true))
            : array();
    ?>

    <?php
        $workflow_request = function_exists('vms_event_plan_editor_verified_post_data')
            ? vms_event_plan_editor_verified_post_data()
            : array();
        $reschedule_date_value = isset($workflow_request['vms_reschedule_event_date']) ? sanitize_text_field((string) $workflow_request['vms_reschedule_event_date']) : '';
    ?>

    <hr />
    <p>
        <label for="vms_reschedule_event_date"><strong><?php esc_html_e('Replacement date', 'backstage-venue-manager'); ?></strong></label><br>
        <input type="date" id="vms_reschedule_event_date" name="vms_reschedule_event_date" value="<?php echo esc_attr($reschedule_date_value); ?>" />
    </p>

    <?php if ($plan_status !== 'cancelled') : ?>
        <p class="description"><?php esc_html_e('Optional. If you enter a replacement date and click “Mark Cancelled,” VMS will cancel this plan and immediately create a linked Draft Event Plan for the new date.', 'backstage-venue-manager'); ?></p>
    <?php endif; ?>

    <?php if ($plan_status === 'cancelled') : ?>
        <?php if (!empty($rescheduled_to_ids)) : ?>
            <p class="description">
                <strong><?php esc_html_e('Existing rescheduled drafts:', 'backstage-venue-manager'); ?></strong>
                <?php
                    $links = array();
                    foreach ($rescheduled_to_ids as $linked_plan_id) {
                        $linked_post = get_post($linked_plan_id);
                        if (!$linked_post || $linked_post->post_type !== 'vms_event_plan') {
                            continue;
                        }
                        $linked_date = (string) get_post_meta($linked_plan_id, '_vms_event_date', true);
                        $linked_label = trim((string) get_the_title($linked_plan_id));
                        if ($linked_label === '') {
                            /* translators: %d: event plan ID. */
                            $linked_label = sprintf(__('Event Plan #%d', 'backstage-venue-manager'), $linked_plan_id);
                        }
                        if ($linked_date !== '') {
                            $linked_label .= ' — ' . $linked_date;
                        }
                        $links[] = '<a href="' . esc_url(vms_event_plan_admin_edit_url($linked_plan_id)) . '">' . esc_html($linked_label) . '</a>';
                    }
                    echo wp_kses_post(implode(' • ', $links));
                ?>
            </p>
        <?php endif; ?>

        <p>
            <button type="submit" name="vms_event_plan_action" value="create_rescheduled_draft" class="button button-secondary" id="vms_create_rescheduled_draft_button">
                <?php esc_html_e('Create Rescheduled Draft', 'backstage-venue-manager'); ?>
            </button>
        </p>
        <p class="description"><?php esc_html_e('Creates a new Draft Event Plan linked to this cancelled one. VMS copies the useful planning details, but clears live calendar, ticket, sales, and cancellation state so you can review safely before republishing.', 'backstage-venue-manager'); ?></p>
    <?php endif; ?>
    </div>
    <div data-vms-collapsible-break="1"></div>

    <p class="vms-ep-status-current">
        <strong><?php esc_html_e('Status:', 'backstage-venue-manager'); ?></strong>
        <?php
            $plan_status_label = function_exists('vms_event_plan_status_label')
                ? (string) vms_event_plan_status_label((string) $plan_status)
                : ucwords(str_replace(array('_', '-'), ' ', (string) $plan_status));
            echo esc_html($plan_status_label);
        ?>
    </p>
 
    <p>
        <button type="submit" name="vms_event_plan_action" value="save_draft" class="button">
            <?php esc_html_e('Save Draft', 'backstage-venue-manager'); ?>
        </button>

        <button type="submit" name="vms_event_plan_action" value="mark_ready" class="button button-secondary">
            <?php esc_html_e('Mark Ready', 'backstage-venue-manager'); ?>
        </button>

        <button type="submit" name="vms_event_plan_action" value="publish_now" class="button button-primary"
            <?php echo ($plan_status === 'ready' || $plan_status === 'published') ? '' : ' disabled="disabled"'; ?>>
            <?php esc_html_e('Publish Now', 'backstage-venue-manager'); ?>
        </button>

        <button type="submit" name="vms_event_plan_action" value="mark_cancelled" class="button vms-button-danger"
            <?php echo ($plan_status === 'cancelled') ? ' disabled="disabled"' : ''; ?>>
            <?php esc_html_e('Mark Cancelled', 'backstage-venue-manager'); ?>
        </button>
    </p>

    <?php if ($rescheduled_from_id > 0 && get_post_type($rescheduled_from_id) === 'vms_event_plan') : ?>
        <p class="description">
            <strong><?php esc_html_e('Rescheduled from:', 'backstage-venue-manager'); ?></strong>
            <?php /* translators: %d: event plan ID. */ ?>
            <a href="<?php echo esc_url(vms_event_plan_admin_edit_url($rescheduled_from_id)); ?>"><?php echo esc_html(get_the_title($rescheduled_from_id) ?: sprintf(__('Event Plan #%d', 'backstage-venue-manager'), $rescheduled_from_id)); ?></a>
        </p>
    <?php endif; ?>

    <p class="description"><?php esc_html_e('“Publish Now” is only available once the plan is Ready. Use “Mark Cancelled” to explicitly cancel a plan.', 'backstage-venue-manager'); ?></p>
