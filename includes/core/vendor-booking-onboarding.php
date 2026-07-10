<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_vendor_booking_onboarding_settings_option_key')) {
    function vms_vendor_booking_onboarding_settings_option_key(): string
    {
        return 'vms_vendor_booking_onboarding_settings';
    }
}

if (!function_exists('vms_vendor_booking_onboarding_plan_meta_key')) {
    function vms_vendor_booking_onboarding_plan_meta_key(): string
    {
        return '_vms_vendor_booking_onboarding_v1';
    }
}

if (!function_exists('vms_vendor_booking_onboarding_default_settings')) {
    function vms_vendor_booking_onboarding_default_settings(): array
    {
        return array(
            'enabled' => true,
            'trigger_statuses' => array('ready', 'published'),
            'video_soft_requirement' => true,
            'reminder_after_days' => 3,
            'reminder_before_days' => 7,
            'subject' => __('You\'re booked: {event_title} at {venue_name} on {event_date}', 'backstage-venue-manager'),
            'body' => implode("\n", array(
                __('Hi {contact_name},', 'backstage-venue-manager'),
                '',
                __('We\'re excited to have {vendor_name} booked for {event_title}.', 'backstage-venue-manager'),
                __('Venue: {venue_name}', 'backstage-venue-manager'),
                __('Date: {event_date}', 'backstage-venue-manager'),
                __('Time: {event_time}', 'backstage-venue-manager'),
                __('Event page: {event_url}', 'backstage-venue-manager'),
                '',
                __('Vendor portal: {vendor_portal_url}', 'backstage-venue-manager'),
                __('Website login: {website_login_url}', 'backstage-venue-manager'),
                '',
                '{vendor_account_prompt}',
                '',
                __('What vendors need to know:', 'backstage-venue-manager'),
                __('Please review your event details, arrival timing, and any venue instructions in the vendor portal.', 'backstage-venue-manager'),
                '',
                '{promo_video_request_block}',
                '',
                __('If anything looks off, reply to this email and we\'ll get it fixed.', 'backstage-venue-manager'),
                '',
                __('Thank you,', 'backstage-venue-manager'),
                '{site_name}',
                '{contact_email}',
            )),
            'promo_video_script' => __('Hey, this is {vendor_name}, and we\'re excited to be coming to {venue_name} on {event_date}. We hope you\'ll come out and join us for a great night at {event_title}.', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_booking_onboarding_normalize_settings')) {
    function vms_vendor_booking_onboarding_normalize_settings($raw): array
    {
        $defaults = vms_vendor_booking_onboarding_default_settings();

        $settings = is_array($raw) ? $raw : array();
        $trigger_statuses = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($settings['trigger_statuses'] ?? $defaults['trigger_statuses'])))));
        $allowed_statuses = array('ready', 'published');
        $trigger_statuses = array_values(array_intersect($trigger_statuses, $allowed_statuses));
        if (empty($trigger_statuses)) {
            $trigger_statuses = $defaults['trigger_statuses'];
        }

        $subject = isset($settings['subject']) ? sanitize_text_field((string) wp_unslash($settings['subject'])) : (string) $defaults['subject'];
        $body = isset($settings['body']) ? sanitize_textarea_field((string) wp_unslash($settings['body'])) : (string) $defaults['body'];
        $promo_video_script = isset($settings['promo_video_script']) ? sanitize_textarea_field((string) wp_unslash($settings['promo_video_script'])) : (string) $defaults['promo_video_script'];

        if (trim($subject) === '') {
            $subject = (string) $defaults['subject'];
        }
        if (trim($body) === '') {
            $body = (string) $defaults['body'];
        }
        if (trim($promo_video_script) === '') {
            $promo_video_script = (string) $defaults['promo_video_script'];
        }

        return array(
            'enabled' => !empty($settings['enabled']),
            'trigger_statuses' => $trigger_statuses,
            'video_soft_requirement' => array_key_exists('video_soft_requirement', $settings) ? !empty($settings['video_soft_requirement']) : !empty($defaults['video_soft_requirement']),
            'reminder_after_days' => max(0, min(60, (int) ($settings['reminder_after_days'] ?? $defaults['reminder_after_days']))),
            'reminder_before_days' => max(0, min(60, (int) ($settings['reminder_before_days'] ?? $defaults['reminder_before_days']))),
            'subject' => $subject,
            'body' => $body,
            'promo_video_script' => $promo_video_script,
        );
    }
}

if (!function_exists('vms_vendor_booking_onboarding_get_settings')) {
    function vms_vendor_booking_onboarding_get_settings(): array
    {
        $raw = get_option(vms_vendor_booking_onboarding_settings_option_key(), array());
        return vms_vendor_booking_onboarding_normalize_settings($raw);
    }
}

if (!function_exists('vms_vendor_booking_onboarding_placeholder_help')) {
    function vms_vendor_booking_onboarding_placeholder_help(): array
    {
        return array(
            '{contact_name}' => __('Primary vendor contact name when available.', 'backstage-venue-manager'),
            '{vendor_name}' => __('Vendor profile title.', 'backstage-venue-manager'),
            '{vendor_type}' => __('Vendor type label such as Music Vendor, Food Vendor, or Vendor.', 'backstage-venue-manager'),
            '{vendor_role}' => __('How this vendor is scheduled on the plan, such as Headliner or Supporting act.', 'backstage-venue-manager'),
            '{site_name}' => __('Your site or venue name.', 'backstage-venue-manager'),
            '{venue_name}' => __('Venue linked to the Event Plan.', 'backstage-venue-manager'),
            '{event_title}' => __('Public event title when available; otherwise the Event Plan title.', 'backstage-venue-manager'),
            '{event_date}' => __('Formatted event date in the site timezone.', 'backstage-venue-manager'),
            '{event_day}' => __('Day of week for the event date.', 'backstage-venue-manager'),
            '{event_time}' => __('Formatted event time window when available.', 'backstage-venue-manager'),
            '{event_url}' => __('Public event page URL when available.', 'backstage-venue-manager'),
            '{vendor_portal_url}' => __('Vendor portal URL for this vendor.', 'backstage-venue-manager'),
            '{website_login_url}' => __('Website login or account page URL.', 'backstage-venue-manager'),
            '{contact_email}' => __('Operator/admin contact email.', 'backstage-venue-manager'),
            '{vendor_email}' => __('Resolved vendor recipient email.', 'backstage-venue-manager'),
            '{vendor_account_prompt}' => __('Context-aware account-linking instructions.', 'backstage-venue-manager'),
            '{promo_video_request_block}' => __('Promo-video request block. It auto-hides for non-headliners.', 'backstage-venue-manager'),
            '{promo_video_script}' => __('Suggested promo-video script.', 'backstage-venue-manager'),
            '{video_upload_url}' => __('Direct vendor portal dashboard URL for promo-video upload.', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_booking_onboarding_get_store')) {
    function vms_vendor_booking_onboarding_get_store(int $plan_id): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }
        $raw = get_post_meta($plan_id, vms_vendor_booking_onboarding_plan_meta_key(), true);
        return is_array($raw) ? $raw : array();
    }
}

if (!function_exists('vms_vendor_booking_onboarding_update_store')) {
    function vms_vendor_booking_onboarding_update_store(int $plan_id, array $store): void
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return;
        }
        if (empty($store)) {
            delete_post_meta($plan_id, vms_vendor_booking_onboarding_plan_meta_key());
            return;
        }
        update_post_meta($plan_id, vms_vendor_booking_onboarding_plan_meta_key(), $store);
    }
}

if (!function_exists('vms_vendor_booking_onboarding_contact_name')) {
    function vms_vendor_booking_onboarding_contact_name(int $vendor_id): string
    {
        $key = function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'contact_name') : '_vms_contact_name';
        if ($key === '') {
            $key = '_vms_contact_name';
        }
        $contact_name = trim((string) get_post_meta($vendor_id, $key, true));
        if ($contact_name !== '') {
            return html_entity_decode(wp_specialchars_decode($contact_name, ENT_QUOTES), ENT_QUOTES, (string) get_bloginfo('charset') ?: 'UTF-8');
        }
        $title = trim((string) get_the_title($vendor_id));
        return $title !== '' ? $title : __('there', 'backstage-venue-manager');
    }
}

if (!function_exists('vms_vendor_booking_onboarding_link_state')) {
    function vms_vendor_booking_onboarding_link_state(int $vendor_id, string $email = ''): array
    {
        $linked_user_id = 0;
        if (function_exists('vms_vendor_user_links_get_by_vendor')) {
            foreach ((array) vms_vendor_user_links_get_by_vendor($vendor_id, false) as $row) {
                $linked_user_id = absint($row['user_id'] ?? 0);
                if ($linked_user_id > 0) {
                    break;
                }
            }
        }

        $candidate_user_id = 0;
        $email = sanitize_email($email);
        if ($email !== '') {
            $candidate = get_user_by('email', $email);
            if ($candidate instanceof WP_User) {
                $candidate_user_id = (int) $candidate->ID;
            }
        }

        return array(
            'linked_user_id' => $linked_user_id,
            'candidate_user_id' => $candidate_user_id,
            'is_linked' => $linked_user_id > 0,
            'has_matching_account' => $linked_user_id <= 0 && $candidate_user_id > 0,
        );
    }
}

if (!function_exists('vms_vendor_booking_onboarding_vendor_email')) {
    function vms_vendor_booking_onboarding_vendor_email(int $vendor_id): string
    {
        $keys = function_exists('vms_vendor_user_link_vendor_email_meta_keys')
            ? (array) vms_vendor_user_link_vendor_email_meta_keys()
            : array('_vms_contact_email', '_vms_vendor_email', '_vms_primary_email', '_vms_email');

        foreach ($keys as $key) {
            $email = sanitize_email((string) get_post_meta($vendor_id, (string) $key, true));
            if ($email !== '' && is_email($email)) {
                return $email;
            }
        }

        $link_state = vms_vendor_booking_onboarding_link_state($vendor_id, '');
        $linked_user_id = (int) ($link_state['linked_user_id'] ?? 0);
        if ($linked_user_id > 0) {
            $user = get_user_by('id', $linked_user_id);
            if ($user instanceof WP_User) {
                $email = sanitize_email((string) $user->user_email);
                if ($email !== '' && is_email($email)) {
                    return $email;
                }
            }
        }

        return '';
    }
}

if (!function_exists('vms_vendor_booking_onboarding_vendor_type_label')) {
    function vms_vendor_booking_onboarding_vendor_type_label(int $vendor_id): string
    {
        if (function_exists('vms_calendar_vendor_primary_type')) {
            $type = (array) vms_calendar_vendor_primary_type($vendor_id);
            $label = trim((string) ($type['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        $terms = get_the_terms($vendor_id, 'vms_vendor_type');
        if (!is_wp_error($terms) && !empty($terms) && !empty($terms[0]->name)) {
            return (string) $terms[0]->name;
        }

        return __('Vendor', 'backstage-venue-manager');
    }
}

if (!function_exists('vms_vendor_booking_onboarding_format_date')) {
    function vms_vendor_booking_onboarding_format_date(string $ymd): string
    {
        $ymd = trim($ymd);
        if ($ymd === '') {
            return '';
        }
        try {
            $dt = new DateTimeImmutable($ymd . ' 12:00:00', function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));
            return function_exists('wp_date') ? wp_date('F j, Y', $dt->getTimestamp(), function_exists('wp_timezone') ? wp_timezone() : null) : date_i18n('F j, Y', $dt->getTimestamp());
        } catch (Throwable $e) {
            return $ymd;
        }
    }
}

if (!function_exists('vms_vendor_booking_onboarding_format_day')) {
    function vms_vendor_booking_onboarding_format_day(string $ymd): string
    {
        $ymd = trim($ymd);
        if ($ymd === '') {
            return '';
        }
        try {
            $dt = new DateTimeImmutable($ymd . ' 12:00:00', function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));
            return function_exists('wp_date') ? wp_date('l', $dt->getTimestamp(), function_exists('wp_timezone') ? wp_timezone() : null) : date_i18n('l', $dt->getTimestamp());
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('vms_vendor_booking_onboarding_format_time')) {
    function vms_vendor_booking_onboarding_format_time(string $start, string $end = ''): string
    {
        $start = trim($start);
        $end = trim($end);
        $format_one = static function (string $raw): string {
            $raw = trim($raw);
            if ($raw === '') {
                return '';
            }
            $formats = array('H:i:s', 'H:i', 'g:ia', 'g:i a');
            foreach ($formats as $format) {
                $dt = DateTimeImmutable::createFromFormat($format, $raw, function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));
                if ($dt instanceof DateTimeImmutable) {
                    return function_exists('wp_date') ? wp_date('g:ia', $dt->getTimestamp(), function_exists('wp_timezone') ? wp_timezone() : null) : date_i18n('g:ia', $dt->getTimestamp());
                }
            }
            return $raw;
        };

        $start_label = $format_one($start);
        $end_label = $format_one($end);
        if ($start_label !== '' && $end_label !== '') {
            return $start_label . ' – ' . $end_label;
        }
        return $start_label !== '' ? $start_label : $end_label;
    }
}

if (!function_exists('vms_vendor_booking_onboarding_portal_url')) {
    function vms_vendor_booking_onboarding_portal_url(int $vendor_id = 0): string
    {
        $args = array('tab' => 'dashboard');
        if ($vendor_id > 0) {
            $args['vendor_id'] = $vendor_id;
        }
        return add_query_arg($args, home_url('/vendor-portal/'));
    }
}

if (!function_exists('vms_vendor_booking_onboarding_plan_targets')) {
    function vms_vendor_booking_onboarding_plan_targets(int $plan_id): array
    {
        $targets = array();
        $seen = array();

        $primary_vendor_id = 0;
        if (function_exists('vms_get_event_plan_lineup_primary_entry')) {
            $primary = (array) vms_get_event_plan_lineup_primary_entry($plan_id);
            $primary_vendor_id = absint($primary['vendor_id'] ?? 0);
        }
        if ($primary_vendor_id <= 0) {
            $primary_vendor_id = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
        }
        if ($primary_vendor_id > 0) {
            $targets[$primary_vendor_id] = array(
                'vendor_id' => $primary_vendor_id,
                'role' => 'headliner',
                'role_label' => __('Headliner', 'backstage-venue-manager'),
                'is_headliner' => true,
            );
            $seen[$primary_vendor_id] = true;
        }

        if (function_exists('vms_get_event_plan_lineup_supporting_entries')) {
            foreach ((array) vms_get_event_plan_lineup_supporting_entries($plan_id) as $entry) {
                $vendor_id = absint($entry['vendor_id'] ?? 0);
                if ($vendor_id <= 0 || isset($seen[$vendor_id])) {
                    continue;
                }
                $targets[$vendor_id] = array(
                    'vendor_id' => $vendor_id,
                    'role' => 'supporting',
                    'role_label' => __('Supporting act', 'backstage-venue-manager'),
                    'is_headliner' => false,
                );
                $seen[$vendor_id] = true;
            }
        }

        $secondary_ids = get_post_meta($plan_id, function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids', true);
        if (!is_array($secondary_ids)) {
            $secondary_ids = get_post_meta($plan_id, '_vms_secondary_vendor_ids', true);
        }
        if (!is_array($secondary_ids)) {
            $secondary_ids = array();
        }
        foreach (array_values(array_unique(array_filter(array_map('absint', $secondary_ids)))) as $vendor_id) {
            if ($vendor_id <= 0 || isset($seen[$vendor_id])) {
                continue;
            }
            $targets[$vendor_id] = array(
                'vendor_id' => $vendor_id,
                'role' => 'secondary',
                'role_label' => __('Scheduled vendor', 'backstage-venue-manager'),
                'is_headliner' => false,
            );
            $seen[$vendor_id] = true;
        }

        return $targets;
    }
}

if (!function_exists('vms_vendor_booking_onboarding_video_attachment_id')) {
    function vms_vendor_booking_onboarding_video_attachment_id(int $plan_id): int
    {
        if (function_exists('vms_vendor_portal_get_headliner_promo_video_data')) {
            $data = (array) vms_vendor_portal_get_headliner_promo_video_data($plan_id);
            return (int) ($data['attachment_id'] ?? 0);
        }
        $key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'headliner_promo_video_attachment_id') : '_vms_headliner_promo_video_attachment_id';
        if ($key === '') {
            $key = '_vms_headliner_promo_video_attachment_id';
        }
        return (int) get_post_meta($plan_id, $key, true);
    }
}

if (!function_exists('vms_vendor_booking_onboarding_video_is_live')) {
    function vms_vendor_booking_onboarding_video_is_live(int $plan_id): bool
    {
        if (function_exists('vms_vendor_portal_get_headliner_promo_video_data')) {
            $data = (array) vms_vendor_portal_get_headliner_promo_video_data($plan_id);
            return !empty($data['source_type']) && (string) ($data['source_type'] ?? 'none') !== 'none' && empty($data['hidden']);
        }
        $attachment_id = vms_vendor_booking_onboarding_video_attachment_id($plan_id);
        if ($attachment_id <= 0) {
            return false;
        }
        $hidden_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'headliner_promo_video_hidden') : '_vms_headliner_promo_video_hidden';
        if ($hidden_key === '') {
            $hidden_key = '_vms_headliner_promo_video_hidden';
        }
        return get_post_meta($plan_id, $hidden_key, true) !== '1';
    }
}

if (!function_exists('vms_vendor_booking_onboarding_video_is_submitted')) {
    function vms_vendor_booking_onboarding_video_is_submitted(int $plan_id): bool
    {
        if (function_exists('vms_vendor_portal_get_headliner_promo_video_submission_data')) {
            $data = (array) vms_vendor_portal_get_headliner_promo_video_submission_data($plan_id);
            return !empty($data['attachment_id']);
        }
        return false;
    }
}

if (!function_exists('vms_vendor_booking_onboarding_get_vendor_plan_status')) {
    function vms_vendor_booking_onboarding_get_vendor_plan_status(int $plan_id, int $vendor_id): array
    {
        $targets = vms_vendor_booking_onboarding_plan_targets($plan_id);
        $assignment = isset($targets[$vendor_id]) && is_array($targets[$vendor_id]) ? $targets[$vendor_id] : array();
        $store = vms_vendor_booking_onboarding_get_store($plan_id);
        $entry = isset($store[$vendor_id]) && is_array($store[$vendor_id]) ? $store[$vendor_id] : array();
        $settings = vms_vendor_booking_onboarding_get_settings();

        $video_required = !empty($assignment['is_headliner']) && !empty($settings['video_soft_requirement']);
        $video_waived = !empty($entry['video_waived']);
        $video_uploaded = $video_required && vms_vendor_booking_onboarding_video_is_live($plan_id);
        $video_submitted = $video_required && !$video_uploaded && vms_vendor_booking_onboarding_video_is_submitted($plan_id);
        $video_status = 'not_applicable';
        $video_label = __('No video needed', 'backstage-venue-manager');
        $video_tone = 'neutral';

        if ($video_required) {
            if ($video_uploaded) {
                $video_status = 'uploaded';
                $video_label = __('Video ready', 'backstage-venue-manager');
                $video_tone = 'success';
            } elseif ($video_submitted) {
                $video_status = 'submitted';
                $video_label = __('Video submitted for review', 'backstage-venue-manager');
                $video_tone = 'warning';
            } elseif ($video_waived) {
                $video_status = 'waived';
                $video_label = __('Video waived', 'backstage-venue-manager');
                $video_tone = 'neutral';
            } else {
                $video_status = 'needed';
                $video_label = __('Video needed', 'backstage-venue-manager');
                $video_tone = 'warning';
            }
        }

        return array(
            'role' => (string) ($assignment['role'] ?? ''),
            'role_label' => (string) ($assignment['role_label'] ?? ''),
            'is_headliner' => !empty($assignment['is_headliner']),
            'email_sent_at_gmt' => (string) ($entry['last_sent_at_gmt'] ?? ''),
            'initial_sent_at_gmt' => (string) ($entry['initial_sent_at_gmt'] ?? ''),
            'last_reminder_at_gmt' => (string) ($entry['last_reminder_at_gmt'] ?? ''),
            'reminder_count' => (int) ($entry['reminder_count'] ?? 0),
            'signature' => (string) ($entry['signature'] ?? ''),
            'video_required' => $video_required,
            'video_waived' => $video_waived,
            'video_status' => $video_status,
            'video_label' => $video_label,
            'video_tone' => $video_tone,
        );
    }
}

if (!function_exists('vms_vendor_booking_onboarding_set_video_waiver')) {
    function vms_vendor_booking_onboarding_set_video_waiver(int $plan_id, int $vendor_id, bool $waived, int $actor_user_id = 0): void
    {
        $store = vms_vendor_booking_onboarding_get_store($plan_id);
        $entry = isset($store[$vendor_id]) && is_array($store[$vendor_id]) ? $store[$vendor_id] : array();
        $entry['video_waived'] = $waived ? 1 : 0;
        $entry['video_waived_at_gmt'] = $waived ? current_time('mysql', true) : '';
        $entry['video_waived_by'] = $waived ? max(0, $actor_user_id) : 0;
        $store[$vendor_id] = $entry;
        vms_vendor_booking_onboarding_update_store($plan_id, $store);
    }
}

if (!function_exists('vms_vendor_booking_onboarding_resolve_text')) {
    function vms_vendor_booking_onboarding_resolve_text(string $template, array $tokens): string
    {
        $resolved = strtr($template, $tokens);
        $resolved = preg_replace("/\n{3,}/", "\n\n", str_replace(array("\r\n", "\r"), "\n", $resolved));
        return trim((string) $resolved);
    }
}

if (!function_exists('vms_vendor_booking_onboarding_account_prompt')) {
    function vms_vendor_booking_onboarding_account_prompt(array $link_state): string
    {
        if (!empty($link_state['is_linked'])) {
            return __('Your vendor account is already linked, so you can head straight to the vendor portal for details and updates.', 'backstage-venue-manager');
        }
        if (!empty($link_state['has_matching_account'])) {
            return __('A website account already appears to exist for this email, but it is not linked to your vendor profile yet. Please sign in, open the vendor portal, and request the account link if it is not already connected.', 'backstage-venue-manager');
        }
        return __('If you have not linked a vendor account yet, please create or sign in to your website account, then open the vendor portal so we can connect it to your vendor profile.', 'backstage-venue-manager');
    }
}

if (!function_exists('vms_vendor_booking_onboarding_build_tokens')) {
    function vms_vendor_booking_onboarding_build_tokens(int $plan_id, int $vendor_id, array $assignment, array $settings): array
    {
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $vendor_name = trim((string) get_the_title($vendor_id));
        $vendor_email = vms_vendor_booking_onboarding_vendor_email($vendor_id);
        $contact_name = vms_vendor_booking_onboarding_contact_name($vendor_id);
        $vendor_type = vms_vendor_booking_onboarding_vendor_type_label($vendor_id);
        $venue_id = (int) get_post_meta($plan_id, '_vms_venue_id', true);
        $venue_name = $venue_id > 0 ? trim((string) get_the_title($venue_id)) : $site_name;
        $event_date_raw = trim((string) get_post_meta($plan_id, '_vms_event_date', true));
        $start_time = trim((string) get_post_meta($plan_id, '_vms_start_time', true));
        $end_time = trim((string) get_post_meta($plan_id, '_vms_end_time', true));
        $event_time = vms_vendor_booking_onboarding_format_time($start_time, $end_time);
        $tec_event_id = (int) get_post_meta($plan_id, function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'tec_event_id') : '_vms_tec_event_id', true);
        $event_url = trim((string) get_post_meta($plan_id, function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'tec_event_url') : '_vms_tec_event_url', true));
        if ($event_url === '' && $tec_event_id > 0) {
            $event_url = (string) get_permalink($tec_event_id);
        }
        $event_title = $tec_event_id > 0 ? trim((string) get_the_title($tec_event_id)) : trim((string) get_the_title($plan_id));
        $link_state = vms_vendor_booking_onboarding_link_state($vendor_id, $vendor_email);
        $video_upload_url = vms_vendor_booking_onboarding_portal_url($vendor_id);

        $base_tokens = array(
            '{contact_name}' => $contact_name,
            '{vendor_name}' => $vendor_name,
            '{vendor_type}' => $vendor_type,
            '{vendor_role}' => (string) ($assignment['role_label'] ?? __('Scheduled vendor', 'backstage-venue-manager')),
            '{site_name}' => $site_name,
            '{venue_name}' => $venue_name,
            '{event_title}' => $event_title,
            '{event_date}' => vms_vendor_booking_onboarding_format_date($event_date_raw),
            '{event_day}' => vms_vendor_booking_onboarding_format_day($event_date_raw),
            '{event_time}' => $event_time,
            '{event_url}' => $event_url,
            '{vendor_portal_url}' => vms_vendor_booking_onboarding_portal_url($vendor_id),
            '{website_login_url}' => home_url('/my-account/'),
            '{contact_email}' => sanitize_email((string) get_option('admin_email', '')),
            '{vendor_email}' => $vendor_email,
            '{video_upload_url}' => $video_upload_url,
        );

        $base_tokens['{vendor_account_prompt}'] = vms_vendor_booking_onboarding_account_prompt($link_state);
        $base_tokens['{promo_video_script}'] = vms_vendor_booking_onboarding_resolve_text((string) ($settings['promo_video_script'] ?? ''), $base_tokens);

        $promo_block = '';
        if (!empty($assignment['is_headliner']) && !empty($settings['video_soft_requirement'])) {
            $promo_block = implode("\n", array(
                __('Promo video request (soft requirement):', 'backstage-venue-manager'),
                __('Please upload a short 30–60 second “we\'re coming to {venue_name}” clip for this show.', 'backstage-venue-manager'),
                __('Upload here: {video_upload_url}', 'backstage-venue-manager'),
                __('Suggested script:', 'backstage-venue-manager'),
                '{promo_video_script}',
                __('If you cannot do it for this show, just reply and we can waive it. This helps us promote your date, but it does not block the booking.', 'backstage-venue-manager'),
            ));
            $promo_block = vms_vendor_booking_onboarding_resolve_text($promo_block, $base_tokens);
        }
        $base_tokens['{promo_video_request_block}'] = $promo_block;

        return $base_tokens;
    }
}

if (!function_exists('vms_vendor_booking_onboarding_signature')) {
    function vms_vendor_booking_onboarding_signature(int $plan_id, int $vendor_id, array $assignment): string
    {
        $status = function_exists('vms_event_plan_get_status')
            ? sanitize_key((string) vms_event_plan_get_status($plan_id, 'dashboard_bills'))
            : sanitize_key((string) get_post_meta($plan_id, '_vms_event_plan_status', true));
        $payload = array(
            'plan_id' => $plan_id,
            'vendor_id' => $vendor_id,
            'role' => (string) ($assignment['role'] ?? ''),
            'status' => $status,
            'event_date' => (string) get_post_meta($plan_id, '_vms_event_date', true),
            'start_time' => (string) get_post_meta($plan_id, '_vms_start_time', true),
            'end_time' => (string) get_post_meta($plan_id, '_vms_end_time', true),
            'venue_id' => (int) get_post_meta($plan_id, '_vms_venue_id', true),
            'tec_event_id' => (int) get_post_meta($plan_id, function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'tec_event_id') : '_vms_tec_event_id', true),
            'video_required' => !empty($assignment['is_headliner']),
        );
        return md5(wp_json_encode($payload));
    }
}

if (!function_exists('vms_vendor_booking_onboarding_touch_vendor_contact_meta')) {
    function vms_vendor_booking_onboarding_touch_vendor_contact_meta(int $vendor_id, string $email, string $subject, int $actor_user_id = 0): void
    {
        $ts = (int) current_time('timestamp');
        update_post_meta($vendor_id, '_vms_vendor_onboarding_last_contacted_at', $ts);
        update_post_meta($vendor_id, '_vms_vendor_onboarding_last_contacted_by', max(0, $actor_user_id));
        update_post_meta($vendor_id, '_vms_vendor_onboarding_last_contact_email', $email);
        update_post_meta($vendor_id, '_vms_vendor_onboarding_last_contact_subject', $subject);
        $count = (int) get_post_meta($vendor_id, '_vms_vendor_onboarding_contact_count', true);
        update_post_meta($vendor_id, '_vms_vendor_onboarding_contact_count', max(0, $count) + 1);
    }
}

if (!function_exists('vms_vendor_booking_onboarding_log_email')) {
    function vms_vendor_booking_onboarding_log_email(string $event_key, string $recipient, array $payload, array $result): void
    {
        if (!function_exists('vms_notify_insert_log')) {
            return;
        }
        vms_notify_insert_log(array(
            'source' => 'vendor_booking_onboarding',
            'event_key' => $event_key,
            'recipient_user_id' => 0,
            'recipient_address' => $recipient,
            'channel' => 'email',
            'locale' => get_locale(),
            'template_key' => $event_key,
            'payload' => $payload,
            'provider' => 'core_email',
            'status' => !empty($result['success']) ? 'sent' : 'failed',
            'error_message' => !empty($result['success']) ? '' : (string) ($result['error_message'] ?? __('wp_mail reported failure.', 'backstage-venue-manager')),
        ));
    }
}

if (!function_exists('vms_vendor_booking_onboarding_send_booked_email')) {
    function vms_vendor_booking_onboarding_send_booked_email(int $plan_id, int $vendor_id, string $reason = 'auto', int $actor_user_id = 0): array
    {
        $plan_id = absint($plan_id);
        $vendor_id = absint($vendor_id);
        if ($plan_id <= 0 || $vendor_id <= 0) {
            return array('success' => false, 'error_message' => __('Missing Event Plan or vendor.', 'backstage-venue-manager'));
        }

        $targets = vms_vendor_booking_onboarding_plan_targets($plan_id);
        $assignment = isset($targets[$vendor_id]) && is_array($targets[$vendor_id]) ? $targets[$vendor_id] : array();
        if (empty($assignment)) {
            return array('success' => false, 'error_message' => __('That vendor is not currently scheduled on this Event Plan.', 'backstage-venue-manager'));
        }

        $settings = vms_vendor_booking_onboarding_get_settings();
        $vendor_email = vms_vendor_booking_onboarding_vendor_email($vendor_id);
        if ($vendor_email === '' || !is_email($vendor_email)) {
            return array('success' => false, 'error_message' => __('No valid vendor email address was found.', 'backstage-venue-manager'));
        }

        $tokens = vms_vendor_booking_onboarding_build_tokens($plan_id, $vendor_id, $assignment, $settings);
        $subject = vms_vendor_booking_onboarding_resolve_text((string) $settings['subject'], $tokens);
        $body = vms_vendor_booking_onboarding_resolve_text((string) $settings['body'], $tokens);

        $result = function_exists('vms_notify_provider_core_email_send')
            ? (array) vms_notify_provider_core_email_send(array(
                'to' => $vendor_email,
                'subject' => $subject,
                'body_text' => $body,
            ))
            : array('success' => wp_mail($vendor_email, $subject, $body));

        vms_vendor_booking_onboarding_log_email('vendor_booked_email', $vendor_email, array(
            'plan_id' => $plan_id,
            'vendor_id' => $vendor_id,
            'reason' => $reason,
            'subject' => $subject,
        ), $result);

        if (empty($result['success'])) {
            return $result;
        }

        vms_vendor_booking_onboarding_touch_vendor_contact_meta($vendor_id, $vendor_email, $subject, $actor_user_id);

        $store = vms_vendor_booking_onboarding_get_store($plan_id);
        $entry = isset($store[$vendor_id]) && is_array($store[$vendor_id]) ? $store[$vendor_id] : array();
        $now_gmt = current_time('mysql', true);
        if (empty($entry['initial_sent_at_gmt'])) {
            $entry['initial_sent_at_gmt'] = $now_gmt;
        }
        $entry['last_sent_at_gmt'] = $now_gmt;
        $entry['last_sent_reason'] = sanitize_key($reason);
        $entry['last_sent_by'] = max(0, $actor_user_id);
        $entry['signature'] = vms_vendor_booking_onboarding_signature($plan_id, $vendor_id, $assignment);
        $entry['role'] = (string) ($assignment['role'] ?? '');
        $entry['role_label'] = (string) ($assignment['role_label'] ?? '');
        $entry['is_headliner'] = !empty($assignment['is_headliner']) ? 1 : 0;
        $entry['vendor_email'] = $vendor_email;
        if (!empty($assignment['is_headliner']) && !empty($settings['video_soft_requirement']) && empty($entry['video_requested_at_gmt'])) {
            $entry['video_requested_at_gmt'] = $now_gmt;
        }
        $store[$vendor_id] = $entry;
        vms_vendor_booking_onboarding_update_store($plan_id, $store);

        return array(
            'success' => true,
            'subject' => $subject,
            'recipient' => $vendor_email,
        );
    }
}

if (!function_exists('vms_vendor_booking_onboarding_should_process_status')) {
    function vms_vendor_booking_onboarding_should_process_status(string $status, array $settings): bool
    {
        $status = sanitize_key($status);
        return $status !== '' && in_array($status, (array) ($settings['trigger_statuses'] ?? array()), true);
    }
}

if (!function_exists('vms_vendor_booking_onboarding_process_plan')) {
    function vms_vendor_booking_onboarding_process_plan(int $plan_id, string $reason = 'auto', int $actor_user_id = 0, bool $allow_notices = false): array
    {
        $settings = vms_vendor_booking_onboarding_get_settings();
        $results = array(
            'sent' => 0,
            'skipped' => 0,
            'failed' => array(),
        );

        if (empty($settings['enabled'])) {
            return $results;
        }

        $status = function_exists('vms_event_plan_get_status')
            ? sanitize_key((string) vms_event_plan_get_status($plan_id, 'dashboard_bills'))
            : sanitize_key((string) get_post_meta($plan_id, '_vms_event_plan_status', true));
        if ($status === 'canceled') {
            $status = 'cancelled';
        }
        if (!vms_vendor_booking_onboarding_should_process_status($status, $settings)) {
            return $results;
        }

        $targets = vms_vendor_booking_onboarding_plan_targets($plan_id);
        $store = vms_vendor_booking_onboarding_get_store($plan_id);

        foreach ($targets as $vendor_id => $assignment) {
            $vendor_id = absint($vendor_id);
            if ($vendor_id <= 0) {
                continue;
            }

            $signature = vms_vendor_booking_onboarding_signature($plan_id, $vendor_id, $assignment);
            $entry = isset($store[$vendor_id]) && is_array($store[$vendor_id]) ? $store[$vendor_id] : array();
            if (($entry['signature'] ?? '') === $signature && !empty($entry['last_sent_at_gmt'])) {
                $results['skipped']++;
                continue;
            }

            $result = vms_vendor_booking_onboarding_send_booked_email($plan_id, $vendor_id, $reason, $actor_user_id);
            if (!empty($result['success'])) {
                $results['sent']++;
            } else {
                $results['failed'][] = array(
                    'vendor_id' => $vendor_id,
                    'vendor_name' => (string) get_the_title($vendor_id),
                    'message' => (string) ($result['error_message'] ?? __('Email could not be sent.', 'backstage-venue-manager')),
                );
            }
        }

        if ($allow_notices && function_exists('vms_add_admin_notice')) {
            if ($results['sent'] > 0) {
                vms_add_admin_notice(sprintf(_n('%d booked-vendor email sent.', '%d booked-vendor emails sent.', $results['sent'], 'backstage-venue-manager'), $results['sent']), 'success');
            }
            if (!empty($results['failed'])) {
                $labels = array();
                foreach ($results['failed'] as $failure) {
                    $labels[] = trim(((string) ($failure['vendor_name'] ?? __('Vendor', 'backstage-venue-manager'))) . ': ' . (string) ($failure['message'] ?? ''));
                }
                vms_add_admin_notice(__('Booked vendor email issue(s): ', 'backstage-venue-manager') . implode(' | ', $labels), 'warning');
            }
        }

        return $results;
    }
}

if (!function_exists('vms_vendor_booking_onboarding_plan_saved')) {
    function vms_vendor_booking_onboarding_plan_saved(int $plan_id, array $context = array()): void
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return;
        }
        $actor_user_id = isset($context['actor_user_id']) ? absint($context['actor_user_id']) : (int) get_current_user_id();
        vms_vendor_booking_onboarding_process_plan($plan_id, 'auto', $actor_user_id, is_admin());
    }
}
add_action('vms_event_plan_saved', 'vms_vendor_booking_onboarding_plan_saved', 20, 2);

if (!function_exists('vms_vendor_booking_onboarding_schedule_event')) {
    function vms_vendor_booking_onboarding_schedule_event(): void
    {
        if (function_exists('vms_should_run_runtime_maintenance') && !vms_should_run_runtime_maintenance()) {
            return;
        }
        if (!wp_next_scheduled('vms_vendor_booking_onboarding_daily')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'vms_vendor_booking_onboarding_daily');
        }
    }
}
add_action('init', 'vms_vendor_booking_onboarding_schedule_event');

if (!function_exists('vms_vendor_booking_onboarding_send_reminder')) {
    function vms_vendor_booking_onboarding_send_reminder(int $plan_id, int $vendor_id): array
    {
        $settings = vms_vendor_booking_onboarding_get_settings();
        $targets = vms_vendor_booking_onboarding_plan_targets($plan_id);
        $assignment = isset($targets[$vendor_id]) && is_array($targets[$vendor_id]) ? $targets[$vendor_id] : array();
        if (empty($assignment) || empty($assignment['is_headliner']) || empty($settings['video_soft_requirement'])) {
            return array('success' => false, 'error_message' => __('No reminder is needed for this vendor.', 'backstage-venue-manager'));
        }

        $status = vms_vendor_booking_onboarding_get_vendor_plan_status($plan_id, $vendor_id);
        if ($status['video_status'] !== 'needed') {
            return array('success' => false, 'error_message' => __('Promo video is no longer needed.', 'backstage-venue-manager'));
        }

        $tokens = vms_vendor_booking_onboarding_build_tokens($plan_id, $vendor_id, $assignment, $settings);
        $vendor_email = (string) ($tokens['{vendor_email}'] ?? '');
        if ($vendor_email === '' || !is_email($vendor_email)) {
            return array('success' => false, 'error_message' => __('No valid vendor email address was found.', 'backstage-venue-manager'));
        }

        $subject = sprintf(__('Reminder: promo video for %s', 'backstage-venue-manager'), (string) ($tokens['{event_title}'] ?? __('your upcoming show', 'backstage-venue-manager')));
        $body = implode("\n", array(
            sprintf(__('Hi %s,', 'backstage-venue-manager'), (string) ($tokens['{contact_name}'] ?? __('there', 'backstage-venue-manager'))),
            '',
            __('This is a friendly reminder about the short promo clip for your upcoming show.', 'backstage-venue-manager'),
            '',
            (string) ($tokens['{promo_video_request_block}'] ?? ''),
            '',
            __('Thank you,', 'backstage-venue-manager'),
            (string) ($tokens['{site_name}'] ?? wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)),
        ));
        $body = vms_vendor_booking_onboarding_resolve_text($body, $tokens);

        $result = function_exists('vms_notify_provider_core_email_send')
            ? (array) vms_notify_provider_core_email_send(array(
                'to' => $vendor_email,
                'subject' => $subject,
                'body_text' => $body,
            ))
            : array('success' => wp_mail($vendor_email, $subject, $body));

        vms_vendor_booking_onboarding_log_email('vendor_booked_video_reminder', $vendor_email, array(
            'plan_id' => $plan_id,
            'vendor_id' => $vendor_id,
            'subject' => $subject,
        ), $result);

        if (!empty($result['success'])) {
            $store = vms_vendor_booking_onboarding_get_store($plan_id);
            $entry = isset($store[$vendor_id]) && is_array($store[$vendor_id]) ? $store[$vendor_id] : array();
            $entry['last_reminder_at_gmt'] = current_time('mysql', true);
            $entry['reminder_count'] = max(0, (int) ($entry['reminder_count'] ?? 0)) + 1;
            $store[$vendor_id] = $entry;
            vms_vendor_booking_onboarding_update_store($plan_id, $store);
        }

        return $result;
    }
}

if (!function_exists('vms_vendor_booking_onboarding_daily_runner')) {
    function vms_vendor_booking_onboarding_daily_runner(): void
    {
        $settings = vms_vendor_booking_onboarding_get_settings();
        if (empty($settings['enabled']) || empty($settings['video_soft_requirement'])) {
            return;
        }

        $today = new DateTimeImmutable('today', function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));
        $today_ymd = $today->format('Y-m-d');
        $window_end = $today->modify('+365 days')->format('Y-m-d');

        $plan_ids = get_posts(array(
            'post_type' => defined('VMS_CPT_EVENT_PLAN') ? VMS_CPT_EVENT_PLAN : 'vms_event_plan',
            'post_status' => array('publish', 'draft', 'private', 'pending'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => '_vms_event_date',
            'meta_query' => array(
                array(
                    'key' => '_vms_event_date',
                    'value' => array($today_ymd, $window_end),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
                ),
            ),
            'no_found_rows' => true,
        ));

        foreach ((array) $plan_ids as $plan_id) {
            $plan_id = absint($plan_id);
            if ($plan_id <= 0) {
                continue;
            }

            $status = function_exists('vms_event_plan_get_status')
                ? sanitize_key((string) vms_event_plan_get_status($plan_id, 'dashboard_bills'))
                : sanitize_key((string) get_post_meta($plan_id, '_vms_event_plan_status', true));
            if (!vms_vendor_booking_onboarding_should_process_status($status, $settings)) {
                continue;
            }

            $event_date = trim((string) get_post_meta($plan_id, '_vms_event_date', true));
            if ($event_date === '') {
                continue;
            }
            try {
                $event_dt = new DateTimeImmutable($event_date . ' 12:00:00', function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));
                $days_until_event = (int) $today->diff($event_dt)->format('%r%a');
            } catch (Throwable $e) {
                $days_until_event = 999;
            }

            $targets = vms_vendor_booking_onboarding_plan_targets($plan_id);
            foreach ($targets as $vendor_id => $assignment) {
                if (empty($assignment['is_headliner'])) {
                    continue;
                }
                $status_info = vms_vendor_booking_onboarding_get_vendor_plan_status($plan_id, (int) $vendor_id);
                if ($status_info['video_status'] !== 'needed') {
                    continue;
                }
                if (empty($status_info['initial_sent_at_gmt'])) {
                    continue;
                }

                $send = false;
                $last_reminder_ts = !empty($status_info['last_reminder_at_gmt']) ? strtotime((string) $status_info['last_reminder_at_gmt'] . ' GMT') : 0;
                $initial_sent_ts = strtotime((string) $status_info['initial_sent_at_gmt'] . ' GMT');
                $now_ts = current_time('timestamp', true);
                $days_since_initial = $initial_sent_ts ? (int) floor(($now_ts - $initial_sent_ts) / DAY_IN_SECONDS) : 0;

                if ((int) $settings['reminder_after_days'] > 0 && $days_since_initial >= (int) $settings['reminder_after_days'] && $last_reminder_ts <= 0) {
                    $send = true;
                }

                if (!$send && (int) $settings['reminder_before_days'] > 0 && $days_until_event >= 0 && $days_until_event <= (int) $settings['reminder_before_days']) {
                    if ($last_reminder_ts <= 0 || ($now_ts - $last_reminder_ts) >= (2 * DAY_IN_SECONDS)) {
                        $send = true;
                    }
                }

                if ($send) {
                    vms_vendor_booking_onboarding_send_reminder($plan_id, (int) $vendor_id);
                }
            }
        }
    }
}
add_action('vms_vendor_booking_onboarding_daily', 'vms_vendor_booking_onboarding_daily_runner');
