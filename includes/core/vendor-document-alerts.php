<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_vendor_submission_alert_default_settings')) {
    function vms_vendor_submission_alert_default_settings(): array
    {
        return array(
            'vendor_doc_submission_notify_enabled' => 1,
            'vendor_doc_submission_notify_target' => 'site_admin',
            'vendor_doc_submission_notify_user_id' => 0,
            'vendor_doc_submission_notify_role' => '',
            'vendor_doc_submission_notify_capability' => '',
        );
    }
}

if (!function_exists('vms_vendor_submission_alert_settings')) {
    function vms_vendor_submission_alert_settings(): array
    {
        $defaults = vms_vendor_submission_alert_default_settings();
        $raw = (array) get_option('vms_settings', array());

        $settings = array(
            'vendor_doc_submission_notify_enabled' => array_key_exists('vendor_doc_submission_notify_enabled', $raw)
                ? (!empty($raw['vendor_doc_submission_notify_enabled']) ? 1 : 0)
                : (int) $defaults['vendor_doc_submission_notify_enabled'],
            'vendor_doc_submission_notify_target' => isset($raw['vendor_doc_submission_notify_target'])
                ? sanitize_key((string) $raw['vendor_doc_submission_notify_target'])
                : (string) $defaults['vendor_doc_submission_notify_target'],
            'vendor_doc_submission_notify_user_id' => isset($raw['vendor_doc_submission_notify_user_id'])
                ? absint($raw['vendor_doc_submission_notify_user_id'])
                : (int) $defaults['vendor_doc_submission_notify_user_id'],
            'vendor_doc_submission_notify_role' => isset($raw['vendor_doc_submission_notify_role'])
                ? sanitize_key((string) $raw['vendor_doc_submission_notify_role'])
                : (string) $defaults['vendor_doc_submission_notify_role'],
            'vendor_doc_submission_notify_capability' => isset($raw['vendor_doc_submission_notify_capability'])
                ? sanitize_key((string) $raw['vendor_doc_submission_notify_capability'])
                : (string) $defaults['vendor_doc_submission_notify_capability'],
        );

        if (!in_array($settings['vendor_doc_submission_notify_target'], array('none', 'site_admin', 'user', 'role', 'capability'), true)) {
            $settings['vendor_doc_submission_notify_target'] = (string) $defaults['vendor_doc_submission_notify_target'];
        }

        return $settings;
    }
}

if (!function_exists('vms_vendor_submission_recipient_mode_options')) {
    function vms_vendor_submission_recipient_mode_options(): array
    {
        return array(
            'site_admin' => __('Site admin email', 'backstage-venue-manager'),
            'user' => __('Specific WordPress user', 'backstage-venue-manager'),
            'role' => __('All users in a WordPress role', 'backstage-venue-manager'),
            'capability' => __('All users with a capability', 'backstage-venue-manager'),
            'none' => __('Do not email anyone', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_submission_context_labels')) {
    function vms_vendor_submission_context_labels(): array
    {
        return array(
            'tech_docs' => __('Tech docs upload', 'backstage-venue-manager'),
            'headliner_promo_video' => __('Promo video upload', 'backstage-venue-manager'),
            'tax_w9_upload' => __('W-9 upload', 'backstage-venue-manager'),
            'tax_w9_offsite_attest' => __('Off-site tax step confirmed', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_submission_context_is_document')) {
    function vms_vendor_submission_context_is_document(string $context): bool
    {
        $context = sanitize_key($context);
        return isset(vms_vendor_submission_context_labels()[$context]);
    }
}

if (!function_exists('vms_vendor_submission_context_label')) {
    function vms_vendor_submission_context_label(string $context): string
    {
        $context = sanitize_key($context);
        $labels = vms_vendor_submission_context_labels();
        if (isset($labels[$context])) {
            return (string) $labels[$context];
        }
        if ($context === '') {
            return __('General update', 'backstage-venue-manager');
        }
        $context = str_replace(array('-', '_'), ' ', $context);
        return ucwords(trim($context));
    }
}

if (!function_exists('vms_vendor_submission_resolve_recipients')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function vms_vendor_submission_resolve_recipients(array $settings = array()): array
    {
        if (empty($settings)) {
            $settings = vms_vendor_submission_alert_settings();
        }

        $mode = sanitize_key((string) ($settings['vendor_doc_submission_notify_target'] ?? 'site_admin'));
        $found = array();
        $seen = array();

        $add_recipient = static function (string $email, int $user_id = 0, string $label = '') use (&$found, &$seen): void {
            $email = sanitize_email($email);
            if (!is_email($email)) {
                return;
            }
            $key = strtolower($email);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $found[] = array(
                'email' => $email,
                'user_id' => max(0, $user_id),
                'label' => $label,
            );
        };

        if ($mode === 'none') {
            return array();
        }

        if ($mode === 'site_admin') {
            $add_recipient((string) get_option('admin_email'), 0, (string) get_bloginfo('name'));
            return $found;
        }

        if ($mode === 'user') {
            $user_id = absint($settings['vendor_doc_submission_notify_user_id'] ?? 0);
            if ($user_id > 0) {
                $user = get_userdata($user_id);
                if ($user instanceof WP_User) {
                    $add_recipient((string) $user->user_email, $user_id, (string) $user->display_name);
                }
            }
            return $found;
        }

        if ($mode === 'role') {
            $role = sanitize_key((string) ($settings['vendor_doc_submission_notify_role'] ?? ''));
            if ($role !== '') {
                $users = get_users(array(
                    'role' => $role,
                    'orderby' => 'display_name',
                    'order' => 'ASC',
                ));
                foreach ($users as $user) {
                    if ($user instanceof WP_User) {
                        $add_recipient((string) $user->user_email, (int) $user->ID, (string) $user->display_name);
                    }
                }
            }
            return $found;
        }

        if ($mode === 'capability') {
            $capability = sanitize_key((string) ($settings['vendor_doc_submission_notify_capability'] ?? ''));
            if ($capability !== '') {
                $users = get_users(array(
                    'orderby' => 'display_name',
                    'order' => 'ASC',
                ));
                foreach ($users as $user) {
                    if ($user instanceof WP_User && user_can($user, $capability)) {
                        $add_recipient((string) $user->user_email, (int) $user->ID, (string) $user->display_name);
                    }
                }
            }
            return $found;
        }

        return $found;
    }
}

if (!function_exists('vms_vendor_submission_build_notification_payload')) {
    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    function vms_vendor_submission_build_notification_payload(int $vendor_id, string $context, array $meta = array()): array
    {
        $vendor_id = absint($vendor_id);
        $context = sanitize_key($context);
        $vendor_name = get_the_title($vendor_id);
        if ($vendor_name === '') {
            /* translators: %d: Vendor post ID. */
            $vendor_name = sprintf(__('Vendor #%d', 'backstage-venue-manager'), $vendor_id);
        }

        $submitted_by_label = __('Vendor portal user', 'backstage-venue-manager');
        $submitted_by_user_id = absint($meta['submitted_by_user_id'] ?? get_current_user_id());
        if ($submitted_by_user_id > 0) {
            $user = get_userdata($submitted_by_user_id);
            if ($user instanceof WP_User && $user->display_name !== '') {
                $submitted_by_label = (string) $user->display_name;
            }
        }

        $site_tz = function_exists('wp_timezone') ? wp_timezone() : null;
        $submitted_ts = time();
        $submitted_at = function_exists('wp_date')
            ? wp_date('M j, Y g:ia', $submitted_ts, $site_tz)
            : date_i18n('M j, Y g:ia', $submitted_ts);

        $plan_id = absint($meta['plan_id'] ?? 0);
        $event_title = '';
        if ($plan_id > 0) {
            $event_title = get_the_title($plan_id);
        }

        return array(
            'vendor_id' => $vendor_id,
            'vendor_name' => $vendor_name,
            'context' => $context,
            'context_label' => vms_vendor_submission_context_label($context),
            'submitted_by_user_id' => $submitted_by_user_id,
            'submitted_by_label' => $submitted_by_label,
            'submitted_at' => $submitted_at,
            'submitted_timestamp' => $submitted_ts,
            'vendor_edit_url' => admin_url('post.php?post=' . $vendor_id . '&action=edit'),
            'plan_id' => $plan_id,
            'event_title' => $event_title,
        );
    }
}

if (!function_exists('vms_vendor_submission_dispatch_alert')) {
    /**
     * @param array<string,mixed> $meta
     */
    function vms_vendor_submission_dispatch_alert(int $vendor_id, string $context, array $meta = array()): void
    {
        $vendor_id = absint($vendor_id);
        $context = sanitize_key($context);
        if ($vendor_id <= 0 || !vms_vendor_submission_context_is_document($context)) {
            return;
        }

        $settings = vms_vendor_submission_alert_settings();
        $payload = vms_vendor_submission_build_notification_payload($vendor_id, $context, $meta);
        $event_key = 'vendor_document_submission';

        if (empty($settings['vendor_doc_submission_notify_enabled'])) {
            if (function_exists('vms_notify_insert_log')) {
                vms_notify_insert_log(array(
                    'source' => 'vms_vendor_portal',
                    'event_key' => $event_key,
                    'recipient_user_id' => 0,
                    'recipient_address' => '',
                    'channel' => 'email',
                    'template_key' => 'vendor_docs.submission_alert',
                    'payload' => $payload,
                    'provider' => 'core_email',
                    'status' => 'skipped',
                    'error_message' => 'vendor document alerts disabled',
                ));
            }
            return;
        }

        $recipients = vms_vendor_submission_resolve_recipients($settings);
        if (empty($recipients)) {
            if (function_exists('vms_notify_insert_log')) {
                vms_notify_insert_log(array(
                    'source' => 'vms_vendor_portal',
                    'event_key' => $event_key,
                    'recipient_user_id' => 0,
                    'recipient_address' => '',
                    'channel' => 'email',
                    'template_key' => 'vendor_docs.submission_alert',
                    'payload' => $payload,
                    'provider' => 'core_email',
                    'status' => 'skipped',
                    'error_message' => 'no recipients resolved for vendor document alert',
                ));
            }
            return;
        }

        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        /* translators: 1: Site name. 2: Vendor name. */
        $subject = sprintf(__('[%1$s] Vendor document submitted: %2$s', 'backstage-venue-manager'), $site_name !== '' ? $site_name : 'Backstage Venue Manager', (string) $payload['vendor_name']);

        $lines = array(
            __('A vendor submitted a document in Backstage Venue Manager.', 'backstage-venue-manager'),
            '',
            /* translators: %s: Vendor name. */
            sprintf(__('Vendor: %s', 'backstage-venue-manager'), (string) $payload['vendor_name']),
            /* translators: %s: Submission context label. */
            sprintf(__('Submission: %s', 'backstage-venue-manager'), (string) $payload['context_label']),
        );
        if (!empty($payload['event_title'])) {
            /* translators: %s: Event title. */
            $lines[] = sprintf(__('Event: %s', 'backstage-venue-manager'), (string) $payload['event_title']);
        }
        /* translators: %s: User display name or fallback label. */
        $lines[] = sprintf(__('Submitted by: %s', 'backstage-venue-manager'), (string) $payload['submitted_by_label']);
        /* translators: %s: Localized submission timestamp. */
        $lines[] = sprintf(__('Submitted: %s', 'backstage-venue-manager'), (string) $payload['submitted_at']);
        $lines[] = '';
        $lines[] = __('The vendor is already flagged as Needs review in the Vendors list.', 'backstage-venue-manager');
        /* translators: %s: Vendor edit admin URL. */
        $lines[] = sprintf(__('Review: %s', 'backstage-venue-manager'), (string) $payload['vendor_edit_url']);
        $body_text = implode("\n", $lines);
        $body_html = '';
        foreach ($lines as $line) {
            if ($line === '') {
                $body_html .= '<p>&nbsp;</p>';
                continue;
            }
            if (strpos($line, 'http') === 0) {
                $body_html .= '<p><a href="' . esc_url($line) . '">' . esc_html($line) . '</a></p>';
                continue;
            }
            if (strpos($line, __('Review:', 'backstage-venue-manager')) === 0) {
                $parts = explode(': ', $line, 2);
                $url = $parts[1] ?? '';
                $body_html .= '<p><strong>' . esc_html__('Review:', 'backstage-venue-manager') . '</strong> <a href="' . esc_url($url) . '">' . esc_html__('Open vendor record', 'backstage-venue-manager') . '</a></p>';
                continue;
            }
            $body_html .= '<p>' . esc_html($line) . '</p>';
        }

        foreach ($recipients as $recipient) {
            $to = sanitize_email((string) ($recipient['email'] ?? ''));
            if (!is_email($to)) {
                continue;
            }
            $result = function_exists('vms_notify_provider_core_email_send')
                ? (array) vms_notify_provider_core_email_send(array(
                    'to' => $to,
                    'subject' => $subject,
                    'body_text' => $body_text,
                    'body_html' => $body_html,
                ))
                : array(
                    'success' => (bool) wp_mail($to, $subject, $body_text),
                    'provider_message_id' => null,
                    'error_message' => '',
                );

            if (function_exists('vms_notify_insert_log')) {
                vms_notify_insert_log(array(
                    'source' => 'vms_vendor_portal',
                    'event_key' => $event_key,
                    'recipient_user_id' => absint($recipient['user_id'] ?? 0),
                    'recipient_address' => $to,
                    'channel' => 'email',
                    'template_key' => 'vendor_docs.submission_alert',
                    'payload' => $payload,
                    'provider' => 'core_email',
                    'provider_message_id' => (string) ($result['provider_message_id'] ?? ''),
                    'status' => !empty($result['success']) ? 'sent' : 'failed',
                    'error_message' => (string) ($result['error_message'] ?? ''),
                ));
            }
        }
    }
}
