<?php

defined('ABSPATH') || exit;

if (!function_exists('bvmgr_event_occurrence_admin_transient_key')) {
    function bvmgr_event_occurrence_admin_transient_key(int $plan_id, int $user_id = 0): string
    {
        $user_id = $user_id > 0 ? $user_id : get_current_user_id();
        return 'vms_occurrence_' . absint($user_id) . '_' . absint($plan_id);
    }
}

if (!function_exists('bvmgr_event_occurrence_admin_signature')) {
    function bvmgr_event_occurrence_admin_signature(int $plan_id, string $old_start, string $new_start, string $reason): string
    {
        return hash('sha256', absint($plan_id) . '|' . trim($old_start) . '|' . trim($new_start) . '|' . sanitize_key($reason));
    }
}

if (!function_exists('bvmgr_event_occurrence_render_admin_panel')) {
    function bvmgr_event_occurrence_render_admin_panel(int $plan_id): void
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
            return;
        }
        $GLOBALS['bvmgr_event_occurrence_admin_form_plan_id'] = $plan_id;
        $occurrence = bvmgr_event_occurrence_for_plan($plan_id);
        if (empty($occurrence['valid'])) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('The stored occurrence is invalid. Use WP-CLI dry-run diagnostics before attempting a repair.', 'backstage-venue-manager') . '</p></div>';
            return;
        }
        $canonical = bvmgr_event_occurrence_payload($occurrence['start'], $occurrence['end']);
        $state = get_transient(bvmgr_event_occurrence_admin_transient_key($plan_id));
        $state = is_array($state) ? $state : array();
        $old_start = (string) ($state['old_start'] ?? $canonical['start_local']);
        $new_start = (string) ($state['new_start'] ?? '');
        $reason = sanitize_key((string) ($state['reason'] ?? 'date_correction'));
        $preview = is_array($state['preview'] ?? null) ? $state['preview'] : array();
        $result = is_array($state['result'] ?? null) ? $state['result'] : array();
        $integrity = bvmgr_event_occurrence_integrity($plan_id);
        $form_id = 'vms-occurrence-change-form-' . $plan_id;

        if (empty($integrity['ok'])) {
            $unit_count = (int) ($integrity['mismatch_units'] ?? 0);
            echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Date mismatch detected', 'backstage-venue-manager') . '</strong><br>';
            printf(
                /* translators: %d: Number of active admission and reservation units with a mismatched occurrence. */
                esc_html__('%d active admission/reservation units reference a different effective occurrence. Preview the controlled repair before applying it.', 'backstage-venue-manager'),
                $unit_count
            );
            echo '</p></div>';
        }
        if (!empty($result)) {
            $class = !empty($result['ok']) ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . ' inline"><p>' . esc_html((string) ($result['message'] ?? '')) . '</p></div>';
        }

        echo '<details class="vms-ep-card"' . (!empty($preview) || !empty($result) ? ' open' : '') . '>';
        echo '<summary><strong>' . esc_html__('Change event date…', 'backstage-venue-manager') . '</strong></summary>';
        echo '<div class="vms-mt-12">';
        echo '<p>' . esc_html__('Published event dates are protected. Previewing identifies every linked purchase, admission, reservation, guest assignment, product, and attendee before anything changes.', 'backstage-venue-manager') . '</p>';
        echo '<p><strong>' . esc_html__('Current occurrence:', 'backstage-venue-manager') . '</strong> ' . esc_html($canonical['start_local'] . ' – ' . $canonical['end_local'] . ' ' . $canonical['timezone']) . '</p>';
        echo '<div class="vms-ep-basic-grid">';
        echo '<p class="vms-ep-basic-item"><label><strong>' . esc_html__('Expected old start', 'backstage-venue-manager') . '</strong><br>';
        echo '<input form="' . esc_attr($form_id) . '" type="datetime-local" name="vms_old_start" value="' . esc_attr(str_replace(' ', 'T', substr($old_start, 0, 16))) . '" required></label><br>';
        echo '<span class="description">' . esc_html__('For an incomplete repair, enter the occurrence still carried by stale entitlements.', 'backstage-venue-manager') . '</span></p>';
        echo '<p class="vms-ep-basic-item"><label><strong>' . esc_html__('New start', 'backstage-venue-manager') . '</strong><br>';
        echo '<input form="' . esc_attr($form_id) . '" type="datetime-local" name="vms_new_start" value="' . esc_attr(str_replace(' ', 'T', substr($new_start, 0, 16))) . '" required></label><br>';
        echo '<span class="description">' . esc_html__('The existing event duration is preserved, including cross-midnight events.', 'backstage-venue-manager') . '</span></p>';
        echo '<p class="vms-ep-basic-item"><label><strong>' . esc_html__('Reason', 'backstage-venue-manager') . '</strong><br>';
        echo '<select form="' . esc_attr($form_id) . '" name="vms_occurrence_reason">';
        echo '<option value="date_correction"' . selected($reason, 'date_correction', false) . '>' . esc_html__('Date correction', 'backstage-venue-manager') . '</option>';
        echo '<option value="rescheduled"' . selected($reason, 'rescheduled', false) . '>' . esc_html__('Event rescheduled', 'backstage-venue-manager') . '</option>';
        echo '</select></label></p>';
        echo '</div>';
        echo '<p><button form="' . esc_attr($form_id) . '" type="submit" class="button button-secondary" name="vms_occurrence_action" value="preview">' . esc_html__('Preview impact', 'backstage-venue-manager') . '</button></p>';

        if (!empty($preview)) {
            $counts = (array) ($preview['counts'] ?? array());
            $customer_units = (int) ($counts['admission_units'] ?? 0) + (int) ($counts['reservation_units'] ?? 0) + (int) ($counts['custom_admission_units'] ?? 0);
            echo '<hr><h4>' . esc_html__('Impact preview', 'backstage-venue-manager') . '</h4>';
            echo '<p><strong>' . esc_html($customer_units > 0
                ? __('This event already has customer purchases or reservations. A full controlled migration is required.', 'backstage-venue-manager')
                : __('No affected sold entitlements were found. This can use the lightweight controlled correction path.', 'backstage-venue-manager')) . '</strong></p>';
            echo '<ul>';
            $labels = array(
                'orders' => __('Affected orders', 'backstage-venue-manager'),
                'admission_units' => __('Paid/free admission units', 'backstage-venue-manager'),
                'reservation_units' => __('Reservation/add-on units', 'backstage-venue-manager'),
                'free_units' => __('Free/comp units', 'backstage-venue-manager'),
                'paid_units' => __('Paid units', 'backstage-venue-manager'),
                'registered_assignments' => __('Registered guest assignments', 'backstage-venue-manager'),
                'numbered_reservation_units' => __('Numbered reservation units', 'backstage-venue-manager'),
                'multi_quantity_lines' => __('Multi-quantity lines', 'backstage-venue-manager'),
                'customers' => __('Unique purchaser contacts', 'backstage-venue-manager'),
                'custom_admission_units' => __('Custom admission units', 'backstage-venue-manager'),
            );
            foreach ($labels as $key => $label) {
                echo '<li>' . esc_html($label) . ': <strong>' . esc_html((string) ((int) ($counts[$key] ?? 0))) . '</strong></li>';
            }
            echo '</ul>';
            foreach ((array) ($preview['categories'] ?? array()) as $category_kind => $category_rows) {
                if (empty($category_rows) || !is_array($category_rows)) {
                    continue;
                }
                $parts = array();
                foreach ($category_rows as $category_label => $category_count) {
                    $parts[] = (string) $category_label . ' ×' . (int) $category_count;
                }
                echo '<p><strong>' . esc_html(ucfirst((string) $category_kind)) . ':</strong> ' . esc_html(implode(', ', $parts)) . '</p>';
            }
            if (!empty($preview['notification_rows'])) {
                echo '<h5>' . esc_html__('Affected customer notification list', 'backstage-venue-manager') . '</h5>';
                echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Order', 'backstage-venue-manager') . '</th><th>' . esc_html__('Customer', 'backstage-venue-manager') . '</th><th>' . esc_html__('Email', 'backstage-venue-manager') . '</th><th>' . esc_html__('Affected entitlements', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
                foreach ((array) $preview['notification_rows'] as $notification) {
                    $entitlements = array_map(static function (array $entitlement): string {
                        return sprintf(
                            /* translators: 1: Entitlement label, 2: Quantity, 3: WooCommerce order-item ID. */
                            __('%1$s ×%2$d (item #%3$d)', 'backstage-venue-manager'),
                            (string) ($entitlement['label'] ?? __('Entitlement', 'backstage-venue-manager')),
                            (int) ($entitlement['quantity'] ?? 0),
                            (int) ($entitlement['order_item_id'] ?? 0)
                        );
                    }, (array) ($notification['entitlements'] ?? array()));
                    echo '<tr><td>#' . esc_html((string) ((int) ($notification['order_id'] ?? 0))) . '</td>';
                    echo '<td>' . esc_html((string) (($notification['customer_name'] ?? '') ?: __('Customer', 'backstage-venue-manager'))) . '</td>';
                    echo '<td>' . esc_html((string) (($notification['customer_email'] ?? '') ?: '—')) . '</td>';
                    echo '<td>' . esc_html(implode('; ', $entitlements)) . '</td></tr>';
                }
                echo '</tbody></table>';
            }
            foreach ((array) ($preview['warnings'] ?? array()) as $warning) {
                echo '<div class="notice notice-warning inline"><p>' . esc_html((string) $warning) . '</p></div>';
            }
            foreach ((array) ($preview['ambiguities'] ?? array()) as $ambiguity) {
                echo '<div class="notice notice-error inline"><p>' . esc_html((string) $ambiguity) . '</p></div>';
            }
            if (!empty($preview['allowed'])) {
                echo '<p><label><input form="' . esc_attr($form_id) . '" type="checkbox" name="vms_occurrence_confirm" value="1" required> ' . esc_html__('I reviewed this preview and authorize the controlled occurrence change.', 'backstage-venue-manager') . '</label></p>';
                echo '<p><button form="' . esc_attr($form_id) . '" type="submit" class="button button-primary" name="vms_occurrence_action" value="apply">' . esc_html__('Apply controlled change', 'backstage-venue-manager') . '</button></p>';
            } else {
                echo '<p><strong>' . esc_html__('Apply is blocked until every ambiguity is resolved.', 'backstage-venue-manager') . '</strong></p>';
            }
        }
        echo '</div></details>';

        $history = bvmgr_event_occurrence_history($plan_id);
        if (!empty($history)) {
            echo '<details class="vms-ep-card vms-mt-12"><summary><strong>' . esc_html__('Event date history', 'backstage-venue-manager') . '</strong> (' . count($history) . ')</summary><ul>';
            foreach (array_reverse($history) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                printf(
                    '<li><code>%1$s</code> → <code>%2$s</code> — %3$s — %4$s — %5$s</li>',
                    esc_html((string) ($entry['old_start_local'] ?? '')),
                    esc_html((string) ($entry['new_start_local'] ?? '')),
                    esc_html((string) ($entry['reason'] ?? '')),
                    esc_html((string) ($entry['created_at_utc'] ?? '') . ' UTC'),
                    esc_html(sprintf(
                        /* translators: %d: WordPress user ID. */
                        __('user #%d', 'backstage-venue-manager'),
                        (int) ($entry['actor_user_id'] ?? 0)
                    ))
                );
            }
            echo '</ul></details>';
        }
    }
}

if (!function_exists('bvmgr_event_occurrence_admin_footer_form')) {
    function bvmgr_event_occurrence_admin_footer_form(): void
    {
        $plan_id = absint($GLOBALS['bvmgr_event_occurrence_admin_form_plan_id'] ?? 0);
        if ($plan_id <= 0) {
            return;
        }
        $form_id = 'vms-occurrence-change-form-' . $plan_id;
        echo '<form id="' . esc_attr($form_id) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bvmgr_event_occurrence_change">';
        echo '<input type="hidden" name="vms_event_plan_id" value="' . esc_attr((string) $plan_id) . '">';
        wp_nonce_field('bvmgr_event_occurrence_change_' . $plan_id, 'bvmgr_event_occurrence_nonce');
        echo '</form>';
    }
}
add_action('admin_footer-post.php', 'bvmgr_event_occurrence_admin_footer_form');

if (!function_exists('bvmgr_event_occurrence_admin_handle_change')) {
    function bvmgr_event_occurrence_admin_handle_change(): void
    {
        $plan_id = isset($_POST['vms_event_plan_id']) ? absint(wp_unslash($_POST['vms_event_plan_id'])) : 0;
        if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
            wp_die(
                esc_html__('You cannot change this Event Plan occurrence.', 'backstage-venue-manager'),
                '',
                array('response' => 403)
            );
        }
        check_admin_referer('bvmgr_event_occurrence_change_' . $plan_id, 'bvmgr_event_occurrence_nonce');
        $old_start = isset($_POST['vms_old_start']) ? sanitize_text_field(wp_unslash((string) $_POST['vms_old_start'])) : '';
        $new_start = isset($_POST['vms_new_start']) ? sanitize_text_field(wp_unslash((string) $_POST['vms_new_start'])) : '';
        $reason = isset($_POST['vms_occurrence_reason']) ? sanitize_key(wp_unslash((string) $_POST['vms_occurrence_reason'])) : '';
        $action = isset($_POST['vms_occurrence_action']) ? sanitize_key(wp_unslash((string) $_POST['vms_occurrence_action'])) : '';
        $old_start = str_replace('T', ' ', $old_start);
        $new_start = str_replace('T', ' ', $new_start);
        $key = bvmgr_event_occurrence_admin_transient_key($plan_id);
        $preview = bvmgr_event_occurrence_preview($plan_id, $old_start, $new_start, $reason);
        $state = array(
            'old_start' => $old_start,
            'new_start' => $new_start,
            'reason' => $reason,
            'signature' => bvmgr_event_occurrence_admin_signature($plan_id, $old_start, $new_start, $reason),
            'preview_fingerprint' => bvmgr_event_occurrence_preview_fingerprint($preview),
            'preview' => $preview,
        );

        if ($action === 'apply') {
            $prior = get_transient($key);
            $confirmed = isset($_POST['vms_occurrence_confirm']) && (string) wp_unslash($_POST['vms_occurrence_confirm']) === '1';
            $prior_signature = is_array($prior) ? (string) ($prior['signature'] ?? '') : '';
            $prior_fingerprint = is_array($prior) ? (string) ($prior['preview_fingerprint'] ?? '') : '';
            if (!$confirmed
                || $prior_signature === ''
                || !hash_equals($prior_signature, $state['signature'])
                || $prior_fingerprint === ''
                || !hash_equals($prior_fingerprint, $state['preview_fingerprint'])
                || empty($prior['preview']['allowed'])) {
                $state['result'] = array('ok' => false, 'message' => __('Apply was blocked. Preview these exact values and check the confirmation first.', 'backstage-venue-manager'));
            } else {
                $state['result'] = bvmgr_event_occurrence_apply($plan_id, $old_start, $new_start, $reason, get_current_user_id(), $prior_fingerprint);
                $state['preview'] = bvmgr_event_occurrence_preview($plan_id, $old_start, $new_start, $reason);
                $state['preview_fingerprint'] = bvmgr_event_occurrence_preview_fingerprint($state['preview']);
            }
        } elseif ($action !== 'preview') {
            $state['result'] = array('ok' => false, 'message' => __('Unknown occurrence action.', 'backstage-venue-manager'));
        }

        set_transient($key, $state, 15 * MINUTE_IN_SECONDS);
        wp_safe_redirect(get_edit_post_link($plan_id, 'raw'));
        exit;
    }
}
add_action('admin_post_vms_event_occurrence_change', 'bvmgr_event_occurrence_admin_handle_change');
