<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('vms_vendor_command_center_page_slug')) {
    function vms_vendor_command_center_page_slug(): string
    {
        return 'vms-vendor-command-center';
    }
}


if (!function_exists('vms_vendor_command_center_template_option_key')) {
    function vms_vendor_command_center_template_option_key(): string
    {
        return defined('VMS_OPT_VENDOR_ONBOARDING_EMAIL_TEMPLATE')
            ? (string) VMS_OPT_VENDOR_ONBOARDING_EMAIL_TEMPLATE
            : 'vms_vendor_onboarding_email_template';
    }
}

if (!function_exists('vms_vendor_command_center_decode_human_text')) {
    function vms_vendor_command_center_decode_human_text(string $text): string
    {
        $charset = (string) get_bloginfo('charset');
        if ($charset === '') {
            $charset = 'UTF-8';
        }

        return html_entity_decode(wp_specialchars_decode((string) $text, ENT_QUOTES), ENT_QUOTES, $charset);
    }
}

if (!function_exists('vms_vendor_command_center_vendor_title')) {
    function vms_vendor_command_center_vendor_title(int $vendor_id): string
    {
        $title = get_post_field('post_title', $vendor_id, 'raw');
        if (!is_string($title)) {
            $title = '';
        }

        $title = trim(vms_vendor_command_center_decode_human_text($title));
        if ($title !== '') {
            return $title;
        }

        return trim(vms_vendor_command_center_decode_human_text((string) get_the_title($vendor_id)));
    }
}


if (!function_exists('vms_vendor_command_center_builtin_template')) {
    function vms_vendor_command_center_builtin_template(): array
    {
        return array(
            'subject' => __('Your {site_name} vendor portal setup for {vendor_name}', 'vms'),
            'body' => implode("
", array(
                __('Hi {contact_name},', 'vms'),
                '',
                __('We are getting your vendor setup organized in {site_name}.', 'vms'),
                '',
                __('Vendor portal: {vendor_portal_url}', 'vms'),
                __('Website login: {website_login_url}', 'vms'),
                '',
                __('If you already have a website account tied to this email, please sign in and let us know if anything is not connected correctly yet.', 'vms'),
                __('If you do not have an account yet, reply to this email and we will help get you connected.', 'vms'),
                '',
                __('Thank you,', 'vms'),
                '{site_name}',
                '{contact_email}',
            )),
        );
    }
}

if (!function_exists('vms_vendor_command_center_template_default_scope')) {
    function vms_vendor_command_center_template_default_scope(): string
    {
        return 'default';
    }
}

if (!function_exists('vms_vendor_command_center_type_scope_key')) {
    function vms_vendor_command_center_type_scope_key(string $type_slug): string
    {
        $type_slug = sanitize_title($type_slug);
        if ($type_slug === '') {
            return vms_vendor_command_center_template_default_scope();
        }

        return 'type:' . $type_slug;
    }
}

if (!function_exists('vms_vendor_command_center_parse_template_scope')) {
    function vms_vendor_command_center_parse_template_scope(string $scope): array
    {
        $scope = trim((string) $scope);
        if ($scope === '' || $scope === vms_vendor_command_center_template_default_scope()) {
            return array(
                'scope' => vms_vendor_command_center_template_default_scope(),
                'is_type' => false,
                'type_slug' => '',
            );
        }

        if (strpos($scope, 'type:') === 0) {
            $type_slug = sanitize_title(substr($scope, 5));
            if ($type_slug !== '') {
                return array(
                    'scope' => vms_vendor_command_center_type_scope_key($type_slug),
                    'is_type' => true,
                    'type_slug' => $type_slug,
                );
            }
        }

        return array(
            'scope' => vms_vendor_command_center_template_default_scope(),
            'is_type' => false,
            'type_slug' => '',
        );
    }
}

if (!function_exists('vms_vendor_command_center_query_arg')) {
    function vms_vendor_command_center_query_arg(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Vendor Command Center filters and selection only change admin display state.
        if (!isset($_GET[$key])) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only Vendor Command Center filters and selection are unslashed here and sanitized or allowlisted by the caller.
        return (string) wp_unslash($_GET[$key]);
    }
}

if (!function_exists('vms_vendor_command_center_normalize_template_entry')) {
    function vms_vendor_command_center_normalize_template_entry($raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $subject = isset($raw['subject']) ? sanitize_text_field(vms_vendor_command_center_decode_human_text((string) $raw['subject'])) : '';
        $body = isset($raw['body']) ? sanitize_textarea_field(vms_vendor_command_center_decode_human_text((string) $raw['body'])) : '';
        if ($subject === '' || trim($body) === '') {
            return null;
        }

        return array(
            'subject' => $subject,
            'body' => $body,
        );
    }
}

if (!function_exists('vms_vendor_command_center_get_template_store_raw')) {
    function vms_vendor_command_center_get_template_store_raw(): array
    {
        $stored = get_option(vms_vendor_command_center_template_option_key(), array());
        $default_template = null;
        $by_type = array();

        if (is_array($stored) && (array_key_exists('default', $stored) || array_key_exists('by_type', $stored))) {
            $default_template = vms_vendor_command_center_normalize_template_entry($stored['default'] ?? null);
            foreach ((array) ($stored['by_type'] ?? array()) as $type_slug => $entry) {
                $type_slug = sanitize_title((string) $type_slug);
                if ($type_slug === '') {
                    continue;
                }
                $normalized = vms_vendor_command_center_normalize_template_entry($entry);
                if ($normalized !== null) {
                    $by_type[$type_slug] = $normalized;
                }
            }
        } else {
            $default_template = vms_vendor_command_center_normalize_template_entry($stored);
        }

        if (!empty($by_type)) {
            ksort($by_type, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return array(
            'default' => $default_template,
            'by_type' => $by_type,
        );
    }
}

if (!function_exists('vms_vendor_command_center_persist_template_store')) {
    function vms_vendor_command_center_persist_template_store(array $store): void
    {
        $payload = array();
        $default_template = vms_vendor_command_center_normalize_template_entry($store['default'] ?? null);
        if ($default_template !== null) {
            $payload['default'] = $default_template;
        }

        $by_type_payload = array();
        foreach ((array) ($store['by_type'] ?? array()) as $type_slug => $entry) {
            $type_slug = sanitize_title((string) $type_slug);
            if ($type_slug === '') {
                continue;
            }
            $normalized = vms_vendor_command_center_normalize_template_entry($entry);
            if ($normalized !== null) {
                $by_type_payload[$type_slug] = $normalized;
            }
        }

        if (!empty($by_type_payload)) {
            ksort($by_type_payload, SORT_NATURAL | SORT_FLAG_CASE);
            $payload['by_type'] = $by_type_payload;
        }

        if (empty($payload)) {
            delete_option(vms_vendor_command_center_template_option_key());
            return;
        }

        update_option(vms_vendor_command_center_template_option_key(), $payload, false);
    }
}

if (!function_exists('vms_vendor_command_center_get_saved_template')) {
    function vms_vendor_command_center_get_saved_template(string $scope = 'default'): array
    {
        $defaults = vms_vendor_command_center_builtin_template();
        $store = vms_vendor_command_center_get_template_store_raw();
        $scope_meta = vms_vendor_command_center_parse_template_scope($scope);

        if (!empty($scope_meta['is_type'])) {
            $type_slug = (string) ($scope_meta['type_slug'] ?? '');
            if ($type_slug !== '' && !empty($store['by_type'][$type_slug])) {
                return (array) $store['by_type'][$type_slug];
            }
        }

        if (!empty($store['default'])) {
            return (array) $store['default'];
        }

        return $defaults;
    }
}

if (!function_exists('vms_vendor_command_center_has_custom_template')) {
    function vms_vendor_command_center_has_custom_template(string $scope = 'default'): bool
    {
        $store = vms_vendor_command_center_get_template_store_raw();
        $scope_meta = vms_vendor_command_center_parse_template_scope($scope);

        if (!empty($scope_meta['is_type'])) {
            $type_slug = (string) ($scope_meta['type_slug'] ?? '');
            return $type_slug !== '' && !empty($store['by_type'][$type_slug]);
        }

        return !empty($store['default']);
    }
}

if (!function_exists('vms_vendor_command_center_type_terms')) {
    function vms_vendor_command_center_type_terms(int $vendor_id): array
    {
        $terms = get_the_terms($vendor_id, 'vms_vendor_type');
        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $items = array();
        foreach ($terms as $term) {
            if (empty($term->name)) {
                continue;
            }

            $slug = sanitize_title((string) ($term->slug ?? ''));
            if ($slug === '') {
                $slug = sanitize_title((string) $term->name);
            }
            if ($slug === '') {
                continue;
            }

            $items[$slug] = array(
                'slug' => $slug,
                'label' => (string) $term->name,
            );
        }

        if (empty($items)) {
            return array();
        }

        uasort($items, static function (array $a, array $b): int {
            return strnatcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return array_values($items);
    }
}

if (!function_exists('vms_vendor_command_center_primary_type_label')) {
    function vms_vendor_command_center_primary_type_label(int $vendor_id): string
    {
        $terms = vms_vendor_command_center_type_terms($vendor_id);
        if (!empty($terms[0]['label'])) {
            return (string) $terms[0]['label'];
        }

        return __('vendor', 'vms');
    }
}

if (!function_exists('vms_vendor_command_center_get_vendor_contact_name')) {
    function vms_vendor_command_center_get_vendor_contact_name(int $vendor_id): string
    {
        $contact_name = trim(vms_vendor_command_center_decode_human_text((string) get_post_meta($vendor_id, vms_vendor_command_center_vendor_meta_key('contact_name', '_vms_contact_name'), true)));
        if ($contact_name !== '') {
            return $contact_name;
        }

        $vendor_name = vms_vendor_command_center_vendor_title($vendor_id);
        if ($vendor_name !== '') {
            return $vendor_name;
        }

        return __('there', 'vms');
    }
}

if (!function_exists('vms_vendor_command_center_template_tokens')) {
    function vms_vendor_command_center_template_tokens(int $vendor_id): array
    {
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $contact_email = sanitize_email((string) get_option('admin_email', ''));

        return array(
            '{vendor_name}' => vms_vendor_command_center_vendor_title($vendor_id),
            '{contact_name}' => vms_vendor_command_center_decode_human_text(vms_vendor_command_center_get_vendor_contact_name($vendor_id)),
            '{vendor_type}' => vms_vendor_command_center_decode_human_text(vms_vendor_command_center_primary_type_label($vendor_id)),
            '{site_name}' => vms_vendor_command_center_decode_human_text($site_name),
            '{vendor_portal_url}' => home_url('/vendor-portal/'),
            '{website_login_url}' => home_url('/my-account/'),
            '{contact_email}' => $contact_email,
            '{vendor_email}' => vms_vendor_command_center_get_vendor_email($vendor_id),
        );
    }
}

if (!function_exists('vms_vendor_command_center_placeholder_help')) {
    function vms_vendor_command_center_placeholder_help(): array
    {
        return array(
            '{contact_name}' => __('Primary contact name when available; otherwise the vendor name.', 'vms'),
            '{vendor_name}' => __('Vendor profile title.', 'vms'),
            '{vendor_type}' => __('Vendor type label, such as Music Vendor or Food Vendor.', 'vms'),
            '{site_name}' => __('Your site or venue name.', 'vms'),
            '{vendor_portal_url}' => __('Vendor portal page URL.', 'vms'),
            '{website_login_url}' => __('Website login or My Account URL.', 'vms'),
            '{contact_email}' => __('Your site admin contact email.', 'vms'),
            '{vendor_email}' => __('Vendor email currently shown in the To field.', 'vms'),
        );
    }
}

if (!function_exists('vms_vendor_command_center_resolve_template_text')) {
    function vms_vendor_command_center_resolve_template_text(string $template, int $vendor_id): string
    {
        $resolved = strtr($template, vms_vendor_command_center_template_tokens($vendor_id));
        $resolved = str_replace(array("
", "
"), "
", $resolved);
        $resolved = preg_replace("/
{3,}/", "

", $resolved);
        return trim((string) $resolved);
    }
}

if (!function_exists('vms_vendor_command_center_prepare_plain_email_text')) {
    function vms_vendor_command_center_prepare_plain_email_text(string $message): string
    {
        $message = vms_vendor_command_center_decode_human_text($message);
        $message = str_replace(array("\r\n", "\r"), "\n", $message);
        $message = wp_strip_all_tags($message);
        $message = preg_replace("/\n{3,}/", "\n\n", $message);

        return trim((string) $message);
    }
}

if (!function_exists('vms_vendor_command_center_active_template_scope_for_vendor')) {
    function vms_vendor_command_center_active_template_scope_for_vendor(int $vendor_id): array
    {
        $store = vms_vendor_command_center_get_template_store_raw();
        foreach (vms_vendor_command_center_type_terms($vendor_id) as $term) {
            $type_slug = (string) ($term['slug'] ?? '');
            if ($type_slug === '' || empty($store['by_type'][$type_slug])) {
                continue;
            }

            return array(
                'scope' => vms_vendor_command_center_type_scope_key($type_slug),
                /* translators: %s: vendor type label. */
                'label' => sprintf(__('%s template', 'vms'), (string) ($term['label'] ?? $type_slug)),
                'type_slug' => $type_slug,
                'type_label' => (string) ($term['label'] ?? $type_slug),
                'is_type' => true,
            );
        }

        return array(
            'scope' => vms_vendor_command_center_template_default_scope(),
            'label' => __('General default template', 'vms'),
            'type_slug' => '',
            'type_label' => '',
            'is_type' => false,
        );
    }
}

if (!function_exists('vms_vendor_command_center_active_template_note')) {
    function vms_vendor_command_center_active_template_note(int $vendor_id): string
    {
        $active = vms_vendor_command_center_active_template_scope_for_vendor($vendor_id);
        if (!empty($active['is_type'])) {
            /* translators: %s: active vendor template label. */
            return sprintf(__('Using the saved %s for this vendor.', 'vms'), (string) ($active['label'] ?? __('type template', 'vms')));
        }

        $type_label = vms_vendor_command_center_primary_type_label($vendor_id);
        if ($type_label !== __('vendor', 'vms')) {
            /* translators: %s: vendor type label. */
            return sprintf(__('Using the General default template. Save a %s template if you want this vendor type to use different wording.', 'vms'), $type_label);
        }

        return __('Using the General default template for this vendor.', 'vms');
    }
}

if (!function_exists('vms_vendor_command_center_resolved_template')) {
    function vms_vendor_command_center_resolved_template(int $vendor_id, string $scope = ''): array
    {
        if ($scope === '') {
            $active = vms_vendor_command_center_active_template_scope_for_vendor($vendor_id);
            $scope = (string) ($active['scope'] ?? vms_vendor_command_center_template_default_scope());
        }

        $template = vms_vendor_command_center_get_saved_template($scope);

        return array(
            'subject' => vms_vendor_command_center_resolve_template_text((string) ($template['subject'] ?? ''), $vendor_id),
            'body' => vms_vendor_command_center_resolve_template_text((string) ($template['body'] ?? ''), $vendor_id),
        );
    }
}

if (!function_exists('vms_vendor_command_center_vendor_meta_key')) {
    function vms_vendor_command_center_vendor_meta_key(string $field, string $fallback): string
    {
        if (function_exists('vms_meta_key')) {
            $mapped = (string) vms_meta_key('vendor', $field);
            if ($mapped !== '') {
                return $mapped;
            }
        }
        return $fallback;
    }
}

if (!function_exists('vms_vendor_command_center_get_vendor_email')) {
    function vms_vendor_command_center_get_vendor_email(int $vendor_id): string
    {
        $keys = array(
            vms_vendor_command_center_vendor_meta_key('primary_email', '_vms_vendor_primary_email'),
            vms_vendor_command_center_vendor_meta_key('contact_email', '_vms_contact_email'),
            vms_vendor_command_center_vendor_meta_key('email', '_vms_vendor_email'),
        );

        foreach ($keys as $key) {
            $value = trim((string) get_post_meta($vendor_id, $key, true));
            if ($value !== '' && is_email($value)) {
                return sanitize_email($value);
            }
        }

        return '';
    }
}

if (!function_exists('vms_vendor_command_center_get_vendor_phone')) {
    function vms_vendor_command_center_get_vendor_phone(int $vendor_id): string
    {
        $keys = array(
            vms_vendor_command_center_vendor_meta_key('primary_phone', '_vms_vendor_primary_phone'),
            vms_vendor_command_center_vendor_meta_key('contact_phone', '_vms_contact_phone'),
            vms_vendor_command_center_vendor_meta_key('phone', '_vms_vendor_phone'),
        );

        foreach ($keys as $key) {
            $value = trim((string) get_post_meta($vendor_id, $key, true));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('vms_vendor_command_center_get_vendor_website')) {
    function vms_vendor_command_center_get_vendor_website(int $vendor_id): string
    {
        $website = trim((string) get_post_meta($vendor_id, vms_vendor_command_center_vendor_meta_key('website', '_vms_vendor_website'), true));
        if ($website === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $website)) {
            $website = 'https://' . ltrim($website, '/');
        }

        return esc_url_raw($website);
    }
}

if (!function_exists('vms_vendor_command_center_get_linked_user_id')) {
    function vms_vendor_command_center_get_linked_user_id(int $vendor_id): int
    {
        if (function_exists('vms_vendor_user_links_get_by_vendor')) {
            $rows = (array) vms_vendor_user_links_get_by_vendor($vendor_id, false);
            foreach ($rows as $row) {
                $user_id = isset($row['user_id']) ? (int) $row['user_id'] : 0;
                if ($user_id > 0) {
                    return $user_id;
                }
            }
        }

        return (int) get_post_meta($vendor_id, (defined('VMS_VENDOR_PRIMARY_USER_META_KEY') ? VMS_VENDOR_PRIMARY_USER_META_KEY : '_vms_vendor_user_id'), true);
    }
}

if (!function_exists('vms_vendor_command_center_get_candidate_user_id')) {
    function vms_vendor_command_center_get_candidate_user_id(int $vendor_id, string $email = ''): int
    {
        $email = $email !== '' ? sanitize_email($email) : vms_vendor_command_center_get_vendor_email($vendor_id);
        if ($email === '') {
            return 0;
        }

        $user = get_user_by('email', $email);
        return ($user instanceof WP_User) ? (int) $user->ID : 0;
    }
}

if (!function_exists('vms_vendor_command_center_get_application_snapshot')) {
    function vms_vendor_command_center_get_application_snapshot(int $vendor_id): array
    {
        $app_id = (int) get_post_meta($vendor_id, '_vms_application_id', true);
        if ($app_id <= 0) {
            return array(
                'app_id' => 0,
                'status' => '',
                'label' => __('No application', 'vms'),
                'edit_link' => '',
            );
        }

        $post = get_post($app_id);
        if (!$post) {
            return array(
                'app_id' => 0,
                'status' => '',
                'label' => __('No application', 'vms'),
                'edit_link' => '',
            );
        }

        $status = function_exists('vms_vendor_app_get_status') ? (string) vms_vendor_app_get_status($app_id) : sanitize_key((string) get_post_meta($app_id, '_vms_app_status', true));
        $labels = function_exists('vms_vendor_app_statuses') ? (array) vms_vendor_app_statuses() : array();
        $label = isset($labels[$status]) ? (string) $labels[$status] : ucfirst($status ?: 'linked');

        return array(
            'app_id' => $app_id,
            'status' => $status,
            'label' => $label,
            'edit_link' => get_edit_post_link($app_id, ''),
        );
    }
}

if (!function_exists('vms_vendor_command_center_terms_days')) {
    function vms_vendor_command_center_terms_days(): int
    {
        $days = (int) get_option('vms_dash_bills_terms_days', 0);
        if ($days < 0) {
            $days = 0;
        }
        if ($days > 365) {
            $days = 365;
        }
        return $days;
    }
}

if (!function_exists('vms_vendor_command_center_extract_known_amount')) {
    function vms_vendor_command_center_extract_known_amount(int $plan_id, string $event_date_ymd, int $venue_id): array
    {
        $snapshot = get_post_meta($plan_id, '_vms_comp_snapshot', true);
        $structure = '';
        $flat_fee = null;

        if (is_array($snapshot)) {
            $structure = isset($snapshot['structure']) ? sanitize_key((string) $snapshot['structure']) : '';
            $flat_fee = array_key_exists('flat_fee_amount', $snapshot) ? $snapshot['flat_fee_amount'] : null;
        }

        if ($structure === '') {
            $structure = sanitize_key((string) get_post_meta($plan_id, '_vms_comp_structure', true));
        }

        if ($flat_fee === null) {
            $raw_flat = get_post_meta($plan_id, '_vms_flat_fee_amount', true);
            $flat_fee = ($raw_flat === '' || $raw_flat === null) ? null : (float) $raw_flat;
        }

        if (($flat_fee === null || (float) $flat_fee <= 0) && in_array($structure, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true)) {
            if (function_exists('vms_get_venue_default_comp_for_date') && $venue_id > 0 && $event_date_ymd !== '') {
                $default = (array) vms_get_venue_default_comp_for_date($venue_id, $event_date_ymd);
                $def_structure = isset($default['structure']) ? sanitize_key((string) $default['structure']) : '';
                $def_fee = isset($default['flat_fee_amount']) ? $default['flat_fee_amount'] : null;
                if (in_array($def_structure, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true) && is_numeric($def_fee) && (float) $def_fee > 0) {
                    $flat_fee = (float) $def_fee;
                }
            }
        }

        $known_amount = null;
        if (in_array($structure, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true) && is_numeric($flat_fee) && (float) $flat_fee > 0) {
            $known_amount = (float) $flat_fee;
        }

        return array(
            'structure' => $structure,
            'known_amount' => $known_amount,
        );
    }
}

if (!function_exists('vms_vendor_command_center_get_supporting_payables')) {
    function vms_vendor_command_center_get_supporting_payables(int $plan_id, string $event_date_ymd, int $venue_id): array
    {
        if (!function_exists('vms_get_event_plan_lineup_supporting_entries')) {
            return array();
        }

        $rows = array();
        foreach ((array) vms_get_event_plan_lineup_supporting_entries($plan_id, array(
            'event_date' => $event_date_ymd,
            'venue_id' => $venue_id,
        )) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $vendor_id = absint($entry['vendor_id'] ?? 0);
            if ($vendor_id <= 0) {
                continue;
            }

            $fee = $entry['guaranteed_fee'] ?? '';
            $known_amount = null;
            if ($fee !== '' && $fee !== null && is_numeric($fee) && (float) $fee > 0) {
                $known_amount = (float) $fee;
            }

            $rows[] = array(
                'vendor_id' => $vendor_id,
                'known_amount' => $known_amount,
                'structure' => 'supporting_guaranteed_fee',
            );
        }

        return $rows;
    }
}

if (!function_exists('vms_vendor_command_center_collect_plan_maps')) {
    function vms_vendor_command_center_collect_plan_maps(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $tz = wp_timezone();
        $today = new DateTimeImmutable('today', $tz);
        $today_ymd = $today->format('Y-m-d');
        $due_soon_ymd = $today->modify('+14 days')->format('Y-m-d');
        $window_start = $today->modify('-120 days')->format('Y-m-d');
        $window_end = $today->modify('+365 days')->format('Y-m-d');
        $terms_days = vms_vendor_command_center_terms_days();

        $plan_ids = get_posts(array(
            'post_type' => defined('VMS_CPT_EVENT_PLAN') ? VMS_CPT_EVENT_PLAN : 'vms_event_plan',
            'post_status' => array('publish', 'draft', 'private', 'pending'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_key' => '_vms_event_date',
            'meta_query' => array(
                array(
                    'key' => '_vms_event_date',
                    'value' => array($window_start, $window_end),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
                ),
            ),
            'no_found_rows' => true,
        ));

        $next_map = array();
        $payables_map = array();

        foreach ((array) $plan_ids as $plan_id) {
            $plan_id = (int) $plan_id;
            if ($plan_id <= 0) {
                continue;
            }

            $event_date = trim((string) get_post_meta($plan_id, '_vms_event_date', true));
            if ($event_date === '') {
                continue;
            }

            $status = function_exists('vms_event_plan_get_status')
                ? sanitize_key((string) vms_event_plan_get_status($plan_id, 'dashboard_bills'))
                : sanitize_key((string) get_post_meta($plan_id, '_vms_event_plan_status', true));
            if ($status === 'canceled') {
                $status = 'cancelled';
            }
            if ($status === 'cancelled') {
                continue;
            }

            $primary_vendor_id = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
            $secondary_vendor_ids = get_post_meta($plan_id, '_vms_secondary_vendor_ids', true);
            if (!is_array($secondary_vendor_ids)) {
                $secondary_vendor_ids = array();
            }
            $secondary_vendor_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_vendor_ids))));
            $lineup_vendor_ids = function_exists('vms_get_event_plan_lineup_vendor_ids')
                ? array_values(array_unique(array_filter(array_map('absint', (array) vms_get_event_plan_lineup_vendor_ids($plan_id, array(
                    'event_date' => $event_date,
                ))))))
                : array();

            if ($event_date >= $today_ymd) {
                $schedule_vendor_ids = array_values(array_unique(array_filter(array_merge(
                    $primary_vendor_id > 0 ? array($primary_vendor_id) : array(),
                    $secondary_vendor_ids,
                    $lineup_vendor_ids
                ))));

                foreach ($schedule_vendor_ids as $vendor_id) {
                    if (!isset($next_map[$vendor_id]) || strcmp($event_date, (string) $next_map[$vendor_id]['event_date']) < 0) {
                        $next_map[$vendor_id] = array(
                            'plan_id' => $plan_id,
                            'event_date' => $event_date,
                            'status' => $status,
                            'label' => function_exists('vms_event_plan_status_label')
                                ? (string) vms_event_plan_status_label($status)
                                : ucwords(str_replace(array('_', '-'), ' ', $status)),
                            'edit_link' => get_edit_post_link($plan_id, ''),
                        );
                    }
                }
            }

            if ($primary_vendor_id <= 0) {
                continue;
            }

            $include_financial = function_exists('vms_event_plan_should_include')
                ? (bool) vms_event_plan_should_include($plan_id, 'dashboard_bills', array(
                    'include_drafts' => false,
                    'include_cancelled' => false,
                ))
                : in_array($status, array('published', 'tentative', 'confirmed'), true);

            if (!$include_financial) {
                continue;
            }

            $venue_id = (int) get_post_meta($plan_id, '_vms_venue_id', true);
            $primary_amount_info = vms_vendor_command_center_extract_known_amount($plan_id, $event_date, $venue_id);
            $payable_rows = array_merge(
                array(array(
                    'vendor_id' => $primary_vendor_id,
                    'structure' => (string) ($primary_amount_info['structure'] ?? ''),
                    'known_amount' => $primary_amount_info['known_amount'] ?? null,
                )),
                function_exists('vms_vendor_command_center_get_supporting_payables') ? vms_vendor_command_center_get_supporting_payables($plan_id, $event_date, $venue_id) : array()
            );

            $due_date = $event_date;
            if ($terms_days > 0) {
                try {
                    $due_date = (new DateTimeImmutable($event_date, $tz))->modify('+' . $terms_days . ' days')->format('Y-m-d');
                } catch (Throwable $e) {
                    $due_date = $event_date;
                }
            }

            foreach ($payable_rows as $payable_row) {
                $pay_vendor_id = (int) ($payable_row['vendor_id'] ?? 0);
                if ($pay_vendor_id <= 0) {
                    continue;
                }

                if (!isset($payables_map[$pay_vendor_id])) {
                    $payables_map[$pay_vendor_id] = array(
                        'has_items' => false,
                        'blocked' => 0,
                        'missing_amount' => 0,
                        'overdue' => 0,
                        'due_soon' => 0,
                        'future' => 0,
                        'past_items' => 0,
                        'next_due_date' => '',
                    );
                }

                $summary = &$payables_map[$pay_vendor_id];
                $summary['has_items'] = true;

                $structure = (string) ($payable_row['structure'] ?? '');
                $known_amount = $payable_row['known_amount'] ?? null;
                if (in_array($structure, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus', 'supporting_guaranteed_fee'), true) && $known_amount === null) {
                    $summary['missing_amount']++;
                }

                $tax_missing = false;
                if (function_exists('vms_is_vendor_tax_profile_complete')) {
                    $tax_missing = !vms_is_vendor_tax_profile_complete($pay_vendor_id);
                }
                $tax_bypass_active = false;
                if (function_exists('vms_get_tax_bypass_status')) {
                    $bypass = (array) vms_get_tax_bypass_status($pay_vendor_id);
                    $tax_bypass_active = !empty($bypass['is_active']);
                }
                if ($tax_missing && !$tax_bypass_active) {
                    $summary['blocked']++;
                }

                if ($event_date < $today_ymd) {
                    $summary['past_items']++;
                }
                if ($due_date < $today_ymd) {
                    $summary['overdue']++;
                } elseif ($due_date <= $due_soon_ymd) {
                    $summary['due_soon']++;
                } else {
                    $summary['future']++;
                }

                if ($summary['next_due_date'] === '' || strcmp($due_date, $summary['next_due_date']) < 0) {
                    $summary['next_due_date'] = $due_date;
                }
                unset($summary);
            }
        }

        $cache = array(
            'next_map' => $next_map,
            'payables_map' => $payables_map,
        );

        return $cache;
    }
}

if (!function_exists('vms_vendor_command_center_pill')) {
    function vms_vendor_command_center_pill(string $label, string $tone = 'neutral', string $title = ''): string
    {
        $class = 'vms-status-pill vms-vcc-pill vms-vcc-pill--' . sanitize_html_class($tone);
        $title_attr = $title !== '' ? ' title="' . esc_attr($title) . '"' : '';
        return '<span class="' . esc_attr($class) . '"' . $title_attr . '>' . esc_html($label) . '</span>';
    }
}

if (!function_exists('vms_vendor_command_center_format_date')) {
    function vms_vendor_command_center_format_date(string $ymd): string
    {
        if ($ymd === '') {
            return '';
        }
        try {
            $dt = new DateTimeImmutable($ymd, wp_timezone());
            return wp_date('M j, Y', $dt->getTimestamp(), wp_timezone());
        } catch (Throwable $e) {
            return $ymd;
        }
    }
}

if (!function_exists('vms_vendor_command_center_format_datetime_from_timestamp')) {
    function vms_vendor_command_center_format_datetime_from_timestamp(int $ts): string
    {
        if ($ts <= 0) {
            return '';
        }
        return wp_date('M j, Y g:i a', $ts, wp_timezone());
    }
}

if (!function_exists('vms_vendor_command_center_phone_href')) {
    function vms_vendor_command_center_phone_href(string $phone): string
    {
        $digits = preg_replace('/[^0-9+]/', '', trim($phone));
        if (!is_string($digits) || $digits === '') {
            return '';
        }
        $plain_digits = preg_replace('/[^0-9]/', '', $digits);
        if (!is_string($plain_digits) || strlen($plain_digits) < 7) {
            return '';
        }
        if (strpos($digits, '+') > 0) {
            $digits = str_replace('+', '', $digits);
        }
        return 'tel:' . $digits;
    }
}

if (!function_exists('vms_vendor_command_center_profile_link_snapshot')) {
    function vms_vendor_command_center_profile_link_snapshot(int $linked_user_id, int $candidate_user_id): array
    {
        $status = 'no_link';
        $label = __('No profile link', 'vms');
        $tone = 'neutral';
        $title = __('No website account is available to link yet.', 'vms');

        if ($linked_user_id > 0) {
            $status = 'linked';
            $label = __('Linked profile', 'vms');
            $tone = 'success';
            $title = __('Vendor profile is connected to a website account.', 'vms');
        } elseif ($candidate_user_id > 0) {
            $status = 'needs_link';
            $label = __('Needs link', 'vms');
            $tone = 'warning';
            $title = __('A website account exists for this email, but the vendor profile is not linked yet.', 'vms');
        }

        return array(
            'status' => $status,
            'label' => $label,
            'tone' => $tone,
            'title' => $title,
        );
    }
}

if (!function_exists('vms_vendor_command_center_onboarding_snapshot')) {
    function vms_vendor_command_center_onboarding_snapshot(int $vendor_id): array
    {
        $last_at = (int) get_post_meta($vendor_id, vms_vendor_command_center_vendor_meta_key('onboarding_last_contacted_at', '_vms_vendor_onboarding_last_contacted_at'), true);
        $last_by = (int) get_post_meta($vendor_id, vms_vendor_command_center_vendor_meta_key('onboarding_last_contacted_by', '_vms_vendor_onboarding_last_contacted_by'), true);
        $last_email = trim((string) get_post_meta($vendor_id, vms_vendor_command_center_vendor_meta_key('onboarding_last_contact_email', '_vms_vendor_onboarding_last_contact_email'), true));
        $last_subject = trim((string) get_post_meta($vendor_id, vms_vendor_command_center_vendor_meta_key('onboarding_last_contact_subject', '_vms_vendor_onboarding_last_contact_subject'), true));
        $contact_count = (int) get_post_meta($vendor_id, vms_vendor_command_center_vendor_meta_key('onboarding_contact_count', '_vms_vendor_onboarding_contact_count'), true);

        $status = 'needs_contact';
        $label = __('Needs contact', 'vms');
        $tone = 'danger';
        $title = __('No onboarding outreach has been logged yet.', 'vms');

        if ($last_at > 0) {
            $status = 'contacted';
            $label = __('Contacted', 'vms');
            $tone = 'info';
            $title = __('Manual onboarding outreach has been logged for this vendor.', 'vms');
        }

        return array(
            'status' => $status,
            'label' => $label,
            'tone' => $tone,
            'title' => $title,
            'last_contacted_at' => $last_at,
            'last_contacted_by' => $last_by,
            'last_contacted_email' => $last_email,
            'last_contacted_subject' => $last_subject,
            'contact_count' => $contact_count,
        );
    }
}

if (!function_exists('vms_vendor_command_center_payables_snapshot')) {
    function vms_vendor_command_center_payables_snapshot(int $vendor_id, array $payables_map): array
    {
        $summary = isset($payables_map[$vendor_id]) && is_array($payables_map[$vendor_id]) ? $payables_map[$vendor_id] : array();
        $snapshot = array(
            'status' => 'none',
            'label' => __('No bill data', 'vms'),
            'tone' => 'neutral',
            'title' => __('No open bill items were found for this vendor in the current Event Plan payables window.', 'vms'),
            'next_due_date' => '',
        );

        if (empty($summary['has_items'])) {
            return $snapshot;
        }

        $snapshot['next_due_date'] = isset($summary['next_due_date']) ? (string) $summary['next_due_date'] : '';

        if (!empty($summary['blocked'])) {
            $count = (int) $summary['blocked'];
            $snapshot['status'] = 'blocked';
            /* translators: %d: number of blocked payable items. */
            $snapshot['label'] = sprintf(_n('Blocked (%d)', 'Blocked (%d)', $count, 'vms'), $count);
            $snapshot['tone'] = 'danger';
            $snapshot['title'] = __('Payment is blocked by tax-profile requirements on at least one bill item.', 'vms');
            return $snapshot;
        }

        if (!empty($summary['missing_amount'])) {
            $count = (int) $summary['missing_amount'];
            $snapshot['status'] = 'missing_amount';
            /* translators: %d: number of payable items missing an amount. */
            $snapshot['label'] = sprintf(_n('Needs amount (%d)', 'Needs amount (%d)', $count, 'vms'), $count);
            $snapshot['tone'] = 'warning';
            $snapshot['title'] = __('At least one payable item is missing a guaranteed amount.', 'vms');
            return $snapshot;
        }

        if (!empty($summary['overdue'])) {
            $count = (int) $summary['overdue'];
            $snapshot['status'] = 'overdue';
            /* translators: %d: number of overdue payable items. */
            $snapshot['label'] = sprintf(_n('Overdue (%d)', 'Overdue (%d)', $count, 'vms'), $count);
            $snapshot['tone'] = 'danger';
            $snapshot['title'] = __('At least one Event Plan payable is past due and still open in VMS.', 'vms');
            return $snapshot;
        }

        if (!empty($summary['due_soon'])) {
            $count = (int) $summary['due_soon'];
            $snapshot['status'] = 'due_soon';
            /* translators: %d: number of payable items due soon. */
            $snapshot['label'] = sprintf(_n('Due soon (%d)', 'Due soon (%d)', $count, 'vms'), $count);
            $snapshot['tone'] = 'warning';
            $snapshot['title'] = __('At least one Event Plan payable is due within the next 14 days.', 'vms');
            return $snapshot;
        }

        if (!empty($summary['future'])) {
            $count = (int) $summary['future'];
            $snapshot['status'] = 'future';
            /* translators: %d: number of upcoming payable items. */
            $snapshot['label'] = sprintf(_n('Upcoming (%d)', 'Upcoming (%d)', $count, 'vms'), $count);
            $snapshot['tone'] = 'info';
            $snapshot['title'] = __('This vendor has upcoming Event Plan payable items with no current overdue balance in VMS.', 'vms');
            return $snapshot;
        }

        $snapshot['status'] = 'clear';
        $snapshot['label'] = __('No open items', 'vms');
        $snapshot['tone'] = 'success';
        $snapshot['title'] = __('Past Event Plan payables exist, but no current open items were found in the current VMS payables view.', 'vms');
        return $snapshot;
    }
}

if (!function_exists('vms_vendor_command_center_type_labels')) {
    function vms_vendor_command_center_type_labels(int $vendor_id): array
    {
        $labels = array();
        foreach (vms_vendor_command_center_type_terms($vendor_id) as $term) {
            if (!empty($term['label'])) {
                $labels[] = (string) $term['label'];
            }
        }
        return $labels;
    }
}

if (!function_exists('vms_vendor_command_center_build_rows')) {
    function vms_vendor_command_center_build_rows(): array
    {
        $maps = vms_vendor_command_center_collect_plan_maps();
        $next_map = isset($maps['next_map']) && is_array($maps['next_map']) ? $maps['next_map'] : array();
        $payables_map = isset($maps['payables_map']) && is_array($maps['payables_map']) ? $maps['payables_map'] : array();

        $vendor_ids = get_posts(array(
            'post_type' => defined('VMS_VENDOR_CPT') ? VMS_VENDOR_CPT : 'vms_vendor',
            'post_status' => array('publish', 'draft', 'private', 'pending'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));

        $rows = array();
        foreach ((array) $vendor_ids as $vendor_id) {
            $vendor_id = (int) $vendor_id;
            if ($vendor_id <= 0) {
                continue;
            }

            $title = vms_vendor_command_center_vendor_title($vendor_id);
            $email = vms_vendor_command_center_get_vendor_email($vendor_id);
            $phone = vms_vendor_command_center_get_vendor_phone($vendor_id);
            $website = vms_vendor_command_center_get_vendor_website($vendor_id);
            $linked_user_id = vms_vendor_command_center_get_linked_user_id($vendor_id);
            $candidate_user_id = vms_vendor_command_center_get_candidate_user_id($vendor_id, $email);
            $application = vms_vendor_command_center_get_application_snapshot($vendor_id);
            $profile_link = vms_vendor_command_center_profile_link_snapshot($linked_user_id, $candidate_user_id);
            $onboarding = vms_vendor_command_center_onboarding_snapshot($vendor_id);
            $payables = vms_vendor_command_center_payables_snapshot($vendor_id, $payables_map);
            $next_item = isset($next_map[$vendor_id]) && is_array($next_map[$vendor_id]) ? $next_map[$vendor_id] : array();
            $type_terms = vms_vendor_command_center_type_terms($vendor_id);
            $type_labels = array();
            $type_slugs = array();
            foreach ($type_terms as $type_term) {
                if (!empty($type_term['label'])) {
                    $type_labels[] = (string) $type_term['label'];
                }
                if (!empty($type_term['slug'])) {
                    $type_slugs[] = (string) $type_term['slug'];
                }
            }

            $account_status = 'no_account';
            $account_label = __('No account', 'vms');
            $account_tone = 'danger';
            $account_title = __('No website account was found for this vendor email.', 'vms');
            if ($linked_user_id > 0) {
                $account_status = 'linked';
                $account_label = __('Linked account', 'vms');
                $account_tone = 'success';
                $account_title = __('Vendor profile is connected to a website account.', 'vms');
            } elseif ($candidate_user_id > 0) {
                $account_status = 'account_exists';
                $account_label = __('Account exists', 'vms');
                $account_tone = 'warning';
                $account_title = __('A website account exists for this vendor email, but the profile is not linked yet.', 'vms');
            }

            $rows[] = array(
                'vendor_id' => $vendor_id,
                'title' => $title,
                'edit_link' => get_edit_post_link($vendor_id, ''),
                'email' => $email,
                'phone' => $phone,
                'website' => $website,
                'types' => $type_labels,
                'type_slugs' => $type_slugs,
                'linked_user_id' => $linked_user_id,
                'candidate_user_id' => $candidate_user_id,
                'account_status' => $account_status,
                'account_label' => $account_label,
                'account_tone' => $account_tone,
                'account_title' => $account_title,
                'application' => $application,
                'profile_link' => $profile_link,
                'onboarding' => $onboarding,
                'next_item' => $next_item,
                'payables' => $payables,
            );
        }

        return $rows;
    }
}

if (!function_exists('vms_vendor_command_center_filter_rows')) {
    function vms_vendor_command_center_filter_rows(array $rows): array
    {
        $search = sanitize_text_field(vms_vendor_command_center_query_arg('vms_vendor_q'));
        $type_filter = sanitize_key(vms_vendor_command_center_query_arg('vms_vendor_type'));
        $account_filter = sanitize_key(vms_vendor_command_center_query_arg('vms_vendor_account'));
        $onboarding_filter = sanitize_key(vms_vendor_command_center_query_arg('vms_vendor_onboarding'));
        $payables_filter = sanitize_key(vms_vendor_command_center_query_arg('vms_vendor_payables'));

        $filtered = array();
        foreach ($rows as $row) {
            $haystack = strtolower(trim(implode(' ', array_filter(array(
                (string) ($row['title'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['phone'] ?? ''),
                implode(' ', (array) ($row['types'] ?? array())),
            )))));

            if ($search !== '' && strpos($haystack, strtolower($search)) === false) {
                continue;
            }

            if ($type_filter !== '') {
                $matched_type = in_array($type_filter, (array) ($row['type_slugs'] ?? array()), true);
                if (!$matched_type) {
                    continue;
                }
            }

            if ($account_filter !== '' && (string) ($row['account_status'] ?? '') !== $account_filter) {
                continue;
            }

            if ($onboarding_filter !== '' && (string) (($row['onboarding']['status'] ?? '')) !== $onboarding_filter) {
                continue;
            }

            if ($payables_filter !== '' && (string) (($row['payables']['status'] ?? '')) !== $payables_filter) {
                continue;
            }

            $filtered[] = $row;
        }

        usort($filtered, static function (array $a, array $b): int {
            $a_date = isset($a['next_item']['event_date']) ? (string) $a['next_item']['event_date'] : '9999-12-31';
            $b_date = isset($b['next_item']['event_date']) ? (string) $b['next_item']['event_date'] : '9999-12-31';
            if ($a_date === $b_date) {
                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            }
            return strcmp($a_date, $b_date);
        });

        return $filtered;
    }
}

if (!function_exists('vms_vendor_command_center_all_type_options')) {
    function vms_vendor_command_center_all_type_options(array $rows): array
    {
        $options = array();
        foreach ($rows as $row) {
            $vendor_id = isset($row['vendor_id']) ? (int) $row['vendor_id'] : 0;
            if ($vendor_id <= 0) {
                continue;
            }
            foreach (vms_vendor_command_center_type_terms($vendor_id) as $term) {
                $slug = (string) ($term['slug'] ?? '');
                $label = (string) ($term['label'] ?? '');
                if ($slug !== '' && $label !== '') {
                    $options[$slug] = $label;
                }
            }
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        return $options;
    }
}

if (!function_exists('vms_vendor_command_center_template_scope_options')) {
    function vms_vendor_command_center_template_scope_options(array $type_options): array
    {
        $options = array(
            vms_vendor_command_center_template_default_scope() => __('General default', 'vms'),
        );

        foreach ($type_options as $slug => $label) {
            $slug = sanitize_title((string) $slug);
            if ($slug === '') {
                continue;
            }
            /* translators: %s: vendor type label. */
            $options[vms_vendor_command_center_type_scope_key($slug)] = sprintf(__('%s template', 'vms'), (string) $label);
        }

        return $options;
    }
}

if (!function_exists('vms_vendor_command_center_template_editor_payload')) {
    function vms_vendor_command_center_template_editor_payload(array $type_options): array
    {
        $scope_options = vms_vendor_command_center_template_scope_options($type_options);
        $payload = array();

        foreach ($scope_options as $scope => $label) {
            $scope_meta = vms_vendor_command_center_parse_template_scope((string) $scope);
            $effective = vms_vendor_command_center_get_saved_template((string) $scope);
            $has_custom = vms_vendor_command_center_has_custom_template((string) $scope);
            $description = __('All vendors use this copy unless their vendor type has its own saved template.', 'vms');
            if (!empty($scope_meta['is_type'])) {
                $type_slug = (string) ($scope_meta['type_slug'] ?? '');
                $type_label = isset($type_options[$type_slug]) ? (string) $type_options[$type_slug] : $type_slug;
                if ($has_custom) {
                    /* translators: %s: vendor type label. */
                    $description = sprintf(__('%s vendors currently use this saved type-specific template.', 'vms'), $type_label);
                } else {
                    /* translators: %s: vendor type label. */
                    $description = sprintf(__('%s vendors currently fall back to the General default until you save a type-specific template here.', 'vms'), $type_label);
                }
            }

            $payload[(string) $scope] = array(
                'scope' => (string) $scope,
                'label' => (string) $label,
                'subject' => (string) ($effective['subject'] ?? ''),
                'body' => (string) ($effective['body'] ?? ''),
                'has_custom' => $has_custom,
                'description' => $description,
            );
        }

        return $payload;
    }
}

if (!function_exists('vms_vendor_command_center_summary_counts')) {
    function vms_vendor_command_center_summary_counts(array $rows): array
    {
        $counts = array(
            'total' => 0,
            'linked' => 0,
            'account_exists' => 0,
            'no_account' => 0,
            'needs_contact' => 0,
            'needs_link' => 0,
            'scheduled_30' => 0,
            'blocked_payables' => 0,
        );

        $today = new DateTimeImmutable('today', wp_timezone());
        $plus_30 = $today->modify('+30 days')->format('Y-m-d');
        foreach ($rows as $row) {
            $counts['total']++;
            $account_status = (string) ($row['account_status'] ?? '');
            if ($account_status === 'linked') {
                $counts['linked']++;
            } elseif ($account_status === 'account_exists') {
                $counts['account_exists']++;
            } else {
                $counts['no_account']++;
            }

            $onboarding_status = (string) (($row['onboarding']['status'] ?? ''));
            if ($onboarding_status === 'needs_contact') {
                $counts['needs_contact']++;
            }
            if ($onboarding_status === 'needs_link') {
                $counts['needs_link']++;
            }

            $next_date = isset($row['next_item']['event_date']) ? (string) $row['next_item']['event_date'] : '';
            if ($next_date !== '' && strcmp($next_date, $plus_30) <= 0) {
                $counts['scheduled_30']++;
            }

            if ((string) (($row['payables']['status'] ?? '')) === 'blocked') {
                $counts['blocked_payables']++;
            }
        }

        return $counts;
    }
}

if (!function_exists('vms_vendor_command_center_default_subject')) {
    function vms_vendor_command_center_default_subject(int $vendor_id): string
    {
        $resolved = vms_vendor_command_center_resolved_template($vendor_id);
        return trim((string) ($resolved['subject'] ?? ''));
    }
}

if (!function_exists('vms_vendor_command_center_default_body')) {
    function vms_vendor_command_center_default_body(int $vendor_id): string
    {
        $resolved = vms_vendor_command_center_resolved_template($vendor_id);
        return trim((string) ($resolved['body'] ?? ''));
    }
}

if (!function_exists('vms_render_vendor_command_center_page')) {
    function vms_render_vendor_command_center_page(): void
    {
        $tour_button = '<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.vendor_command_center.basics" data-vms-tour="vendor-command.help-action">' . esc_html__('Start Guided Tour', 'vms') . '</button>';
        if (function_exists('vms_render_help_button')) {
            $tour_button = vms_render_help_button(array(
                'tour_id' => 'vms.vendor_command_center.basics',
                'anchor' => 'vendor-command.help-action',
                'label' => __('Start Guided Tour', 'vms'),
                'class' => 'button-secondary',
            ));
        }

        $actions_html = '<div class="vms-vcc-header-actions">' . $tour_button . '</div>';

        if (function_exists('vms_admin_ui_render_shell')) {
            vms_admin_ui_render_shell(
                array(
                    'title' => __('Vendor Command Center', 'vms'),
                    'subtitle' => __('One vendor-focused table for website accounts, profile links, onboarding outreach, next dates, and payable health.', 'vms'),
                    'shell_id' => 'vms-vendor-command-center',
                    'content_class' => 'vms-vcc-content',
                    'actions_html' => $actions_html,
                ),
                'vms_render_vendor_command_center_page_content'
            );
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vendor Command Center', 'vms') . '</h1>';
        vms_render_vendor_command_center_page_content();
        echo '</div>';
    }
}

if (!function_exists('vms_render_vendor_command_center_page_content')) {
    function vms_render_vendor_command_center_page_content(): void
    {
        $all_rows = vms_vendor_command_center_build_rows();
        $rows = vms_vendor_command_center_filter_rows($all_rows);
        $summary = vms_vendor_command_center_summary_counts($all_rows);
        $type_options = vms_vendor_command_center_all_type_options($all_rows);

        $selected_vendor_id = absint(vms_vendor_command_center_query_arg('vendor_id'));
        if ($selected_vendor_id <= 0 && !empty($rows)) {
            $selected_vendor_id = (int) ($rows[0]['vendor_id'] ?? 0);
        }

        $selected_vendor_email = $selected_vendor_id > 0 ? vms_vendor_command_center_get_vendor_email($selected_vendor_id) : '';
        $selected_subject = $selected_vendor_id > 0 ? vms_vendor_command_center_default_subject($selected_vendor_id) : '';
        $selected_body = $selected_vendor_id > 0 ? vms_vendor_command_center_default_body($selected_vendor_id) : '';
        $selected_template_note = $selected_vendor_id > 0 ? vms_vendor_command_center_active_template_note($selected_vendor_id) : __('Using the General default template for this vendor.', 'vms');
        $template_scope_options = vms_vendor_command_center_template_scope_options($type_options);
        $template_editor_payload = vms_vendor_command_center_template_editor_payload($type_options);
        $selected_template_scope = sanitize_text_field(vms_vendor_command_center_query_arg('template_scope'));
        if ($selected_template_scope === '') {
            $selected_template_scope = vms_vendor_command_center_template_default_scope();
        }
        if (!isset($template_scope_options[$selected_template_scope])) {
            $selected_template_scope = vms_vendor_command_center_template_default_scope();
        }

        $vendor_form_map = array();
        foreach ($all_rows as $row) {
            $vendor_id = (int) ($row['vendor_id'] ?? 0);
            if ($vendor_id <= 0) {
                continue;
            }
            $vendor_form_map[(string) $vendor_id] = array(
                'to' => (string) ($row['email'] ?? ''),
                'subject' => vms_vendor_command_center_default_subject($vendor_id),
                'message' => vms_vendor_command_center_default_body($vendor_id),
                'template_note' => vms_vendor_command_center_active_template_note($vendor_id),
            );
        }

        echo '<div class="vms-vcc-intro" data-vms-tour="vendor-command.help">';
        echo '<p>' . esc_html__('This command center does not replace final accounting. It shows account linkage, outreach history, next scheduled dates, and open Event Plan payable health in one place so nothing gets buried.', 'vms') . '</p>';
        echo '</div>';

        echo '<div class="vms-vcc-summary-grid" data-vms-tour="vendor-command.summary">';
        $cards = array(
            array('label' => __('Total vendors', 'vms'), 'value' => (string) $summary['total']),
            array('label' => __('Linked accounts', 'vms'), 'value' => (string) $summary['linked']),
            array('label' => __('Account exists, not linked', 'vms'), 'value' => (string) $summary['account_exists']),
            array('label' => __('Needs contact', 'vms'), 'value' => (string) $summary['needs_contact']),
            array('label' => __('Scheduled in next 30 days', 'vms'), 'value' => (string) $summary['scheduled_30']),
            array('label' => __('Payment blocked', 'vms'), 'value' => (string) $summary['blocked_payables']),
        );
        foreach ($cards as $card) {
            echo '<div class="vms-vcc-summary-card">';
            echo '<div class="vms-vcc-summary-card__value">' . esc_html($card['value']) . '</div>';
            echo '<div class="vms-vcc-summary-card__label">' . esc_html($card['label']) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        $placeholder_help = vms_vendor_command_center_placeholder_help();

        echo '<details class="vms-vcc-compose vms-vcc-panel" data-vms-tour="vendor-command.compose" data-vms-persist-key="vcc-compose" open>';
        echo '<summary class="vms-vcc-panel__summary">';
        echo '<span class="vms-vcc-panel__summary-text">';
        echo '<span class="vms-vcc-panel__title">' . esc_html__('Single-vendor onboarding email', 'vms') . '</span>';
        echo '<span class="vms-vcc-panel__description">' . esc_html__('Use this when one vendor needs a nudge, a portal reminder, or help getting their website account connected. The fields below start from the saved template that matches the vendor type when one exists, and otherwise fall back to your General default.', 'vms') . '</span>';
        echo '</span>';
        echo '<span class="vms-vcc-panel__toggle" aria-hidden="true"></span>';
        echo '</summary>';
        echo '<div class="vms-vcc-panel__body">';

        echo '<script type="application/json" id="vms-vcc-vendor-map">' . wp_json_encode($vendor_form_map) . '</script>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-vcc-compose__form">';
        wp_nonce_field('vms_vendor_command_center_send_onboarding', 'vms_vendor_command_center_nonce');
        echo '<input type="hidden" name="action" value="vms_vendor_command_center_send_onboarding">';

        echo '<div class="vms-vcc-compose__grid">';
        echo '<p>';
        echo '<label for="vms-vcc-vendor-id"><strong>' . esc_html__('Vendor', 'vms') . '</strong></label><br>';
        echo '<select id="vms-vcc-vendor-id" name="vendor_id" required>';
        echo '<option value="">' . esc_html__('Select a vendor…', 'vms') . '</option>';
        foreach ($all_rows as $row) {
            $vendor_id = (int) ($row['vendor_id'] ?? 0);
            $label = (string) ($row['title'] ?? '');
            $email = (string) ($row['email'] ?? '');
            if ($email !== '') {
                $label .= ' — ' . $email;
            }
            echo '<option value="' . esc_attr((string) $vendor_id) . '"' . selected($selected_vendor_id, $vendor_id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</p>';

        echo '<p>';
        echo '<label for="vms-vcc-to"><strong>' . esc_html__('To', 'vms') . '</strong></label><br>';
        echo '<input type="email" id="vms-vcc-to" name="to_email" value="' . esc_attr($selected_vendor_email) . '" required>';
        echo '</p>';
        echo '</div>';

        echo '<p class="description vms-vcc-compose__template-note" id="vms-vcc-current-template-note">' . esc_html($selected_template_note) . '</p>';

        echo '<p>';
        echo '<label for="vms-vcc-subject"><strong>' . esc_html__('Subject', 'vms') . '</strong></label><br>';
        echo '<input type="text" id="vms-vcc-subject" name="subject" value="' . esc_attr($selected_subject) . '" required>';
        echo '</p>';

        echo '<p>';
        echo '<label for="vms-vcc-body"><strong>' . esc_html__('Message', 'vms') . '</strong></label><br>';
        echo '<textarea id="vms-vcc-body" name="message" rows="10" required>' . esc_textarea($selected_body) . '</textarea>';
        echo '</p>';

        echo '<p class="vms-vcc-compose__actions">';
        submit_button(__('Send onboarding email', 'vms'), 'primary', 'submit', false);
        echo ' <button type="button" class="button button-secondary" id="vms-vcc-reset-fields">' . esc_html__('Restore matching template', 'vms') . '</button>';
        echo '</p>';
        echo '</form>';
        echo '</div>';
        echo '</details>';

        echo '<details class="vms-vcc-compose vms-vcc-template-editor vms-vcc-panel" data-vms-tour="vendor-command.template" data-vms-persist-key="vcc-templates" open>';
        echo '<summary class="vms-vcc-panel__summary">';
        echo '<span class="vms-vcc-panel__summary-text">';
        echo '<span class="vms-vcc-panel__title">' . esc_html__('Saved onboarding templates', 'vms') . '</span>';
        echo '<span class="vms-vcc-panel__description">' . esc_html__('Keep one General default plus optional vendor-type templates. When a vendor type has its own saved template, the single-vendor composer uses that automatically. Otherwise it falls back to the General default.', 'vms') . '</span>';
        echo '</span>';
        echo '<span class="vms-vcc-panel__toggle" aria-hidden="true"></span>';
        echo '</summary>';
        echo '<div class="vms-vcc-panel__body">';
        echo '<script type="application/json" id="vms-vcc-template-map">' . wp_json_encode($template_editor_payload) . '</script>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-vcc-compose__form">';
        wp_nonce_field('vms_vendor_command_center_save_template', 'vms_vendor_command_center_template_nonce');
        echo '<input type="hidden" name="action" value="vms_vendor_command_center_save_template">';
        echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $selected_vendor_id) . '">';

        echo '<div class="vms-vcc-compose__grid">';
        echo '<p>';
        echo '<label for="vms-vcc-template-scope"><strong>' . esc_html__('Template applies to', 'vms') . '</strong></label><br>';
        echo '<select id="vms-vcc-template-scope" name="template_scope">';
        foreach ($template_scope_options as $scope => $label) {
            echo '<option value="' . esc_attr((string) $scope) . '"' . selected($selected_template_scope, (string) $scope, false) . '>' . esc_html((string) $label) . '</option>';
        }
        echo '</select>';
        echo '</p>';
        echo '</div>';
        echo '<p class="description vms-vcc-template-scope-help" id="vms-vcc-template-scope-help"></p>';

        echo '<p>';
        echo '<label for="vms-vcc-template-subject"><strong>' . esc_html__('Saved subject template', 'vms') . '</strong></label><br>';
        echo '<input type="text" id="vms-vcc-template-subject" name="template_subject" value="" required>';
        echo '</p>';

        echo '<p>';
        echo '<label for="vms-vcc-template-body"><strong>' . esc_html__('Saved message template', 'vms') . '</strong></label><br>';
        echo '<textarea id="vms-vcc-template-body" name="template_body" rows="10" required></textarea>';
        echo '</p>';

        echo '<div class="vms-vcc-token-grid">';
        foreach ($placeholder_help as $token => $help_text) {
            echo '<div class="vms-vcc-token-item"><code>' . esc_html($token) . '</code><span>' . esc_html($help_text) . '</span></div>';
        }
        echo '</div>';

        echo '<p class="vms-vcc-compose__actions">';
        submit_button(__('Save template', 'vms'), 'secondary', 'submit', false);
        echo ' <button type="submit" class="button" name="vms_reset_template" value="1">' . esc_html__('Reset selected template', 'vms') . '</button>';
        echo '</p>';
        echo '</form>';
        echo '</div>';
        echo '</details>';

        if (function_exists('vms_vendor_booking_onboarding_render_settings_panel')) {
            vms_vendor_booking_onboarding_render_settings_panel();
        }

        echo '<form method="get" class="vms-vcc-filters" data-vms-tour="vendor-command.filters">';
        echo '<input type="hidden" name="page" value="' . esc_attr(vms_vendor_command_center_page_slug()) . '">';
        echo '<input type="search" name="vms_vendor_q" value="' . esc_attr(sanitize_text_field(vms_vendor_command_center_query_arg('vms_vendor_q'))) . '" placeholder="' . esc_attr__('Search vendor, email, phone, or type', 'vms') . '">';

        echo '<select name="vms_vendor_type">';
        echo '<option value="">' . esc_html__('All types', 'vms') . '</option>';
        $current_type = sanitize_key(vms_vendor_command_center_query_arg('vms_vendor_type'));
        foreach ($type_options as $slug => $label) {
            echo '<option value="' . esc_attr($slug) . '"' . selected($current_type, $slug, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        $current_account = sanitize_key(vms_vendor_command_center_query_arg('vms_vendor_account'));
        echo '<select name="vms_vendor_account">';
        echo '<option value="">' . esc_html__('All account states', 'vms') . '</option>';
        echo '<option value="linked"' . selected($current_account, 'linked', false) . '>' . esc_html__('Linked account', 'vms') . '</option>';
        echo '<option value="account_exists"' . selected($current_account, 'account_exists', false) . '>' . esc_html__('Account exists, not linked', 'vms') . '</option>';
        echo '<option value="no_account"' . selected($current_account, 'no_account', false) . '>' . esc_html__('No account', 'vms') . '</option>';
        echo '</select>';

        $current_onboarding = sanitize_key(vms_vendor_command_center_query_arg('vms_vendor_onboarding'));
        echo '<select name="vms_vendor_onboarding">';
        echo '<option value="">' . esc_html__('All onboarding states', 'vms') . '</option>';
        echo '<option value="contacted"' . selected($current_onboarding, 'contacted', false) . '>' . esc_html__('Contacted', 'vms') . '</option>';
        echo '<option value="needs_contact"' . selected($current_onboarding, 'needs_contact', false) . '>' . esc_html__('Needs contact', 'vms') . '</option>';
        echo '</select>';

        $current_payables = sanitize_key(vms_vendor_command_center_query_arg('vms_vendor_payables'));
        echo '<select name="vms_vendor_payables">';
        echo '<option value="">' . esc_html__('All payables states', 'vms') . '</option>';
        foreach (array(
            'blocked' => __('Blocked', 'vms'),
            'missing_amount' => __('Needs amount', 'vms'),
            'overdue' => __('Overdue', 'vms'),
            'due_soon' => __('Due soon', 'vms'),
            'future' => __('Upcoming', 'vms'),
            'clear' => __('No open items', 'vms'),
            'none' => __('No bill data', 'vms'),
        ) as $status => $label) {
            echo '<option value="' . esc_attr($status) . '"' . selected($current_payables, $status, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        submit_button(__('Filter', 'vms'), '', '', false);
        echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=' . vms_vendor_command_center_page_slug())) . '">' . esc_html__('Reset', 'vms') . '</a>';
        echo '</form>';

        echo '<div class="vms-vcc-table-wrap" data-vms-tour="vendor-command.table">';
        echo '<table class="widefat striped vms-vcc-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Vendor', 'vms') . '</th>';
        echo '<th>' . esc_html__('Type', 'vms') . '</th>';
        echo '<th>' . esc_html__('Website account', 'vms') . '</th>';
        echo '<th>' . esc_html__('Profile link', 'vms') . '</th>';
        echo '<th>' . esc_html__('Application', 'vms') . '</th>';
        echo '<th>' . esc_html__('Onboarding', 'vms') . '</th>';
        echo '<th>' . esc_html__('Next scheduled', 'vms') . '</th>';
        echo '<th>' . esc_html__('Payables', 'vms') . '</th>';
        echo '<th>' . esc_html__('Actions', 'vms') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="9">' . esc_html__('No vendors matched the current filters.', 'vms') . '</td></tr>';
        } else {
            foreach ($rows as $row) {
                $vendor_id = (int) ($row['vendor_id'] ?? 0);
                $title = (string) ($row['title'] ?? '');
                $edit_link = (string) ($row['edit_link'] ?? '');
                $email = (string) ($row['email'] ?? '');
                $phone = (string) ($row['phone'] ?? '');
                $website = (string) ($row['website'] ?? '');
                $linked_user_id = (int) ($row['linked_user_id'] ?? 0);
                $candidate_user_id = (int) ($row['candidate_user_id'] ?? 0);
                $application = (array) ($row['application'] ?? array());
                $profile_link = (array) ($row['profile_link'] ?? array());
                $onboarding = (array) ($row['onboarding'] ?? array());
                $next_item = (array) ($row['next_item'] ?? array());
                $payables = (array) ($row['payables'] ?? array());
                $booking_status = (!empty($next_item['plan_id']) && function_exists('vms_vendor_booking_onboarding_get_vendor_plan_status'))
                    ? (array) vms_vendor_booking_onboarding_get_vendor_plan_status((int) $next_item['plan_id'], $vendor_id)
                    : array();

                echo '<tr>';
                echo '<td class="vms-vcc-col-vendor">';
                echo '<div class="vms-vcc-vendor-name">';
                if ($edit_link !== '') {
                    echo '<a href="' . esc_url($edit_link) . '"><strong>' . esc_html($title) . '</strong></a>';
                } else {
                    echo '<strong>' . esc_html($title) . '</strong>';
                }
                echo '</div>';
                echo '<div class="vms-vcc-vendor-meta">';
                if ($email !== '') {
                    echo '<div><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></div>';
                }
                if ($phone !== '') {
                    $tel = vms_vendor_command_center_phone_href($phone);
                    echo '<div>';
                    if ($tel !== '') {
                        echo '<a href="' . esc_attr($tel) . '">' . esc_html($phone) . '</a>';
                    } else {
                        echo esc_html($phone);
                    }
                    echo '</div>';
                }
                if ($website !== '') {
                    echo '<div><a href="' . esc_url($website) . '" target="_blank" rel="noopener noreferrer">' . esc_html(preg_replace('#^https?://#i', '', $website)) . '</a></div>';
                }
                echo '</div>';
                echo '</td>';

                echo '<td>';
                if (!empty($row['types'])) {
                    foreach ((array) $row['types'] as $type_label) {
                        echo wp_kses_post(vms_vendor_command_center_pill((string) $type_label, 'neutral')) . ' ';
                    }
                } else {
                    echo wp_kses_post(vms_vendor_command_center_pill(__('Uncategorized', 'vms'), 'neutral'));
                }
                echo '</td>';

                echo '<td>';
                echo wp_kses_post(vms_vendor_command_center_pill((string) ($row['account_label'] ?? __('No account', 'vms')), (string) ($row['account_tone'] ?? 'neutral'), (string) ($row['account_title'] ?? '')));
                if ($linked_user_id > 0) {
                    $user = get_user_by('id', $linked_user_id);
                    $user_link = get_edit_user_link($linked_user_id);
                    if ($user instanceof WP_User) {
                        echo '<div class="vms-vcc-subline">';
                        if ($user_link !== '') {
                            echo '<a href="' . esc_url($user_link) . '">' . esc_html($user->display_name ?: $user->user_email) . '</a>';
                        } else {
                            echo esc_html($user->display_name ?: $user->user_email);
                        }
                        echo '</div>';
                    }
                } elseif ($candidate_user_id > 0) {
                    $user = get_user_by('id', $candidate_user_id);
                    if ($user instanceof WP_User) {
                        echo '<div class="vms-vcc-subline">' . esc_html($user->display_name ?: $user->user_email) . '</div>';
                    }
                }
                echo '</td>';

                echo '<td>';
                echo wp_kses_post(vms_vendor_command_center_pill((string) ($profile_link['label'] ?? __('No profile link', 'vms')), (string) ($profile_link['tone'] ?? 'neutral'), (string) ($profile_link['title'] ?? '')));
                echo '</td>';

                echo '<td>';
                $app_status = (string) ($application['status'] ?? '');
                $app_tone = 'neutral';
                if ($app_status === 'approved') {
                    $app_tone = 'success';
                } elseif ($app_status === 'pending') {
                    $app_tone = 'warning';
                } elseif ($app_status === 'rejected') {
                    $app_tone = 'danger';
                }
                echo wp_kses_post(vms_vendor_command_center_pill((string) ($application['label'] ?? __('No application', 'vms')), $app_tone));
                if (!empty($application['edit_link'])) {
                    echo '<div class="vms-vcc-subline"><a href="' . esc_url((string) $application['edit_link']) . '">' . esc_html__('Open application', 'vms') . '</a></div>';
                }
                echo '</td>';

                echo '<td>';
                echo wp_kses_post(vms_vendor_command_center_pill((string) ($onboarding['label'] ?? __('Needs contact', 'vms')), (string) ($onboarding['tone'] ?? 'neutral'), (string) ($onboarding['title'] ?? '')));
                if (!empty($onboarding['last_contacted_at'])) {
                    /* translators: %s: formatted onboarding email sent date and time. */
                    echo '<div class="vms-vcc-subline">' . esc_html(sprintf(__('Last sent %s', 'vms'), vms_vendor_command_center_format_datetime_from_timestamp((int) $onboarding['last_contacted_at']))) . '</div>';
                }
                echo '</td>';

                echo '<td>';
                if (!empty($next_item['event_date'])) {
                    $next_date = (string) $next_item['event_date'];
                    $next_label = vms_vendor_command_center_format_date($next_date);
                    if (!empty($next_item['edit_link'])) {
                        echo '<a href="' . esc_url((string) $next_item['edit_link']) . '"><strong>' . esc_html($next_label) . '</strong></a>';
                    } else {
                        echo '<strong>' . esc_html($next_label) . '</strong>';
                    }
                    echo '<div class="vms-vcc-subline">' . esc_html((string) ($next_item['label'] ?? '')) . '</div>';
                    if (!empty($booking_status['role_label'])) {
                        echo '<div class="vms-vcc-subline">' . esc_html((string) $booking_status['role_label']) . '</div>';
                    }
                    if (!empty($booking_status['video_required'])) {
                        echo '<div class="vms-vcc-subline">' . wp_kses_post(vms_vendor_command_center_pill((string) ($booking_status['video_label'] ?? __('Video needed', 'vms')), (string) ($booking_status['video_tone'] ?? 'warning'))) . '</div>';
                    }
                } else {
                    echo wp_kses_post(vms_vendor_command_center_pill(__('No future date', 'vms'), 'neutral'));
                }
                echo '</td>';

                echo '<td>';
                echo wp_kses_post(vms_vendor_command_center_pill((string) ($payables['label'] ?? __('No bill data', 'vms')), (string) ($payables['tone'] ?? 'neutral'), (string) ($payables['title'] ?? '')));
                if (!empty($payables['next_due_date'])) {
                    /* translators: %s: formatted next payable due date. */
                    echo '<div class="vms-vcc-subline">' . esc_html(sprintf(__('Next due %s', 'vms'), vms_vendor_command_center_format_date((string) $payables['next_due_date']))) . '</div>';
                }
                echo '</td>';

                echo '<td class="vms-vcc-actions">';
                if ($edit_link !== '') {
                    echo '<a class="button button-small vms-vcc-action-link vms-vcc-action-link--edit" href="' . esc_url($edit_link) . '">' . esc_html__('Edit vendor', 'vms') . '</a> ';
                }
                echo '<a class="button button-small vms-vcc-action-link vms-vcc-action-link--contact" href="' . esc_url(add_query_arg(array('page' => vms_vendor_command_center_page_slug(), 'vendor_id' => $vendor_id), admin_url('admin.php'))) . '#vms-vcc-body">' . esc_html__('Contact', 'vms') . '</a> ';

                if (!empty($next_item['plan_id']) && function_exists('vms_vendor_booking_onboarding_send_booked_email')) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-vcc-inline-form">';
                    wp_nonce_field('vms_vendor_booking_onboarding_resend_' . (int) $next_item['plan_id'] . '_' . $vendor_id);
                    echo '<input type="hidden" name="action" value="vms_vendor_booking_onboarding_resend">';
                    echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) ((int) $next_item['plan_id'])) . '">';
                    echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $vendor_id) . '">';
                    submit_button(__('Resend booked email', 'vms'), 'secondary small vms-vcc-action-link', 'submit', false);
                    echo '</form>';
                }

                if (!empty($booking_status['video_required']) && !empty($next_item['plan_id']) && function_exists('vms_vendor_booking_onboarding_set_video_waiver') && ((string) ($booking_status['video_status'] ?? '') !== 'uploaded' || !empty($booking_status['video_waived']))) {
                    $waive_now = empty($booking_status['video_waived']);
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-vcc-inline-form">';
                    wp_nonce_field('vms_vendor_booking_onboarding_toggle_waiver_' . (int) $next_item['plan_id'] . '_' . $vendor_id);
                    echo '<input type="hidden" name="action" value="vms_vendor_booking_onboarding_toggle_waiver">';
                    echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) ((int) $next_item['plan_id'])) . '">';
                    echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $vendor_id) . '">';
                    echo '<input type="hidden" name="waive" value="' . esc_attr($waive_now ? '1' : '0') . '">';
                    submit_button($waive_now ? __('Waive video', 'vms') : __('Require video', 'vms'), 'secondary small vms-vcc-action-link', 'submit', false);
                    echo '</form>';
                }

                if ($candidate_user_id > 0 && $linked_user_id <= 0) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-vcc-inline-form">';
                    wp_nonce_field('vms_vendor_command_center_link_matching_user_' . $vendor_id, 'vms_vendor_command_center_link_nonce');
                    echo '<input type="hidden" name="action" value="vms_vendor_command_center_link_matching_user">';
                    echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $vendor_id) . '">';
                    submit_button(__('Link account', 'vms'), 'secondary small vms-vcc-action-link vms-vcc-action-link--link', 'submit', false);
                    echo '</form>';
                }
                echo '</td>';

                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}


add_action('admin_post_vms_vendor_command_center_save_template', 'vms_vendor_command_center_handle_save_template');
if (!function_exists('vms_vendor_command_center_handle_save_template')) {
    function vms_vendor_command_center_handle_save_template(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'vms'));
        }

        check_admin_referer('vms_vendor_command_center_save_template', 'vms_vendor_command_center_template_nonce');

        $vendor_id = absint(wp_unslash((string) ($_POST['vendor_id'] ?? 0)));
        $template_scope = isset($_POST['template_scope']) ? sanitize_text_field((string) wp_unslash($_POST['template_scope'])) : vms_vendor_command_center_template_default_scope();
        $scope_meta = vms_vendor_command_center_parse_template_scope($template_scope);
        $template_scope = (string) ($scope_meta['scope'] ?? vms_vendor_command_center_template_default_scope());

        $redirect_args = array(
            'page' => vms_vendor_command_center_page_slug(),
            'template_scope' => $template_scope,
        );
        if ($vendor_id > 0) {
            $redirect_args['vendor_id'] = $vendor_id;
        }

        $store = vms_vendor_command_center_get_template_store_raw();

        if (!empty($_POST['vms_reset_template'])) {
            if (!empty($scope_meta['is_type'])) {
                $type_slug = (string) ($scope_meta['type_slug'] ?? '');
                if ($type_slug !== '') {
                    unset($store['by_type'][$type_slug]);
                }
                vms_vendor_command_center_persist_template_store($store);
                if (function_exists('vms_add_admin_notice')) {
                    vms_add_admin_notice(__('Selected vendor-type template reset. Matching vendors will now fall back to the General default.', 'vms'), 'success');
                }
            } else {
                unset($store['default']);
                vms_vendor_command_center_persist_template_store($store);
                if (function_exists('vms_add_admin_notice')) {
                    vms_add_admin_notice(__('General default onboarding template reset to the built-in default.', 'vms'), 'success');
                }
            }
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        $subject = isset($_POST['template_subject']) ? sanitize_text_field((string) wp_unslash($_POST['template_subject'])) : '';
        $body = isset($_POST['template_body']) ? sanitize_textarea_field((string) wp_unslash($_POST['template_body'])) : '';

        if ($subject === '' || trim($body) === '') {
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('Saved template subject and message are both required.', 'vms'), 'error');
            }
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        $entry = array(
            'subject' => $subject,
            'body' => $body,
        );

        if (!empty($scope_meta['is_type'])) {
            $type_slug = (string) ($scope_meta['type_slug'] ?? '');
            if ($type_slug !== '') {
                $store['by_type'][$type_slug] = $entry;
            }
            vms_vendor_command_center_persist_template_store($store);
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('Saved vendor-type onboarding template updated.', 'vms'), 'success');
            }
        } else {
            $store['default'] = $entry;
            vms_vendor_command_center_persist_template_store($store);
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('General default onboarding template updated. Future single-vendor emails will use it unless a vendor type has its own template.', 'vms'), 'success');
            }
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}

add_action('admin_post_vms_vendor_command_center_send_onboarding', 'vms_vendor_command_center_handle_send_onboarding');
if (!function_exists('vms_vendor_command_center_handle_send_onboarding')) {
    function vms_vendor_command_center_handle_send_onboarding(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'vms'));
        }

        check_admin_referer('vms_vendor_command_center_send_onboarding', 'vms_vendor_command_center_nonce');

        $vendor_id = absint(wp_unslash((string) ($_POST['vendor_id'] ?? 0)));
        $to_email = isset($_POST['to_email']) ? sanitize_email((string) wp_unslash($_POST['to_email'])) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field(vms_vendor_command_center_decode_human_text((string) wp_unslash($_POST['subject']))) : '';
        $message = isset($_POST['message']) ? vms_vendor_command_center_decode_human_text((string) wp_unslash($_POST['message'])) : '';
        $message_text = vms_vendor_command_center_prepare_plain_email_text($message);

        if ($vendor_id <= 0 || get_post_type($vendor_id) !== (defined('VMS_VENDOR_CPT') ? VMS_VENDOR_CPT : 'vms_vendor')) {
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('Select a valid vendor before sending onboarding email.', 'vms'), 'error');
            }
            wp_safe_redirect(admin_url('admin.php?page=' . vms_vendor_command_center_page_slug()));
            exit;
        }

        if (!is_email($to_email)) {
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('A valid recipient email is required.', 'vms'), 'error');
            }
            wp_safe_redirect(add_query_arg(array('page' => vms_vendor_command_center_page_slug(), 'vendor_id' => $vendor_id), admin_url('admin.php')));
            exit;
        }

        if ($subject === '' || $message_text === '') {
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('Subject and message are both required.', 'vms'), 'error');
            }
            wp_safe_redirect(add_query_arg(array('page' => vms_vendor_command_center_page_slug(), 'vendor_id' => $vendor_id), admin_url('admin.php')));
            exit;
        }

        $result = function_exists('vms_notify_provider_core_email_send')
            ? (array) vms_notify_provider_core_email_send(array(
                'to' => $to_email,
                'subject' => $subject,
                'body_text' => $message_text,
            ))
            : array('success' => wp_mail($to_email, $subject, $message_text));

        $sent = !empty($result['success']);
        if (!$sent) {
            if (function_exists('vms_notify_insert_log')) {
                vms_notify_insert_log(array(
                    'source' => 'vendor_command_center',
                    'event_key' => 'vendor_onboarding_manual',
                    'recipient_user_id' => 0,
                    'recipient_address' => $to_email,
                    'channel' => 'email',
                    'locale' => get_locale(),
                    'template_key' => 'vendor_onboarding_manual',
                    'payload' => array('vendor_id' => $vendor_id),
                    'provider' => 'core_email',
                    'status' => 'failed',
                    'error_message' => isset($result['error_message']) ? (string) $result['error_message'] : __('wp_mail reported failure.', 'vms'),
                ));
            }
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('Email could not be sent. Please confirm the recipient address and your WordPress mail setup.', 'vms'), 'error');
            }
            wp_safe_redirect(add_query_arg(array('page' => vms_vendor_command_center_page_slug(), 'vendor_id' => $vendor_id), admin_url('admin.php')));
            exit;
        }

        $ts = (int) current_time('timestamp');
        update_post_meta($vendor_id, vms_vendor_command_center_vendor_meta_key('onboarding_last_contacted_at', '_vms_vendor_onboarding_last_contacted_at'), $ts);
        update_post_meta($vendor_id, vms_vendor_command_center_vendor_meta_key('onboarding_last_contacted_by', '_vms_vendor_onboarding_last_contacted_by'), (int) get_current_user_id());
        update_post_meta($vendor_id, vms_vendor_command_center_vendor_meta_key('onboarding_last_contact_email', '_vms_vendor_onboarding_last_contact_email'), $to_email);
        update_post_meta($vendor_id, vms_vendor_command_center_vendor_meta_key('onboarding_last_contact_subject', '_vms_vendor_onboarding_last_contact_subject'), $subject);
        $count_key = vms_vendor_command_center_vendor_meta_key('onboarding_contact_count', '_vms_vendor_onboarding_contact_count');
        $count = (int) get_post_meta($vendor_id, $count_key, true);
        update_post_meta($vendor_id, $count_key, max(0, $count) + 1);

        if (function_exists('vms_notify_insert_log')) {
            vms_notify_insert_log(array(
                'source' => 'vendor_command_center',
                'event_key' => 'vendor_onboarding_manual',
                'recipient_user_id' => 0,
                'recipient_address' => $to_email,
                'channel' => 'email',
                'locale' => get_locale(),
                'template_key' => 'vendor_onboarding_manual',
                'payload' => array(
                    'vendor_id' => $vendor_id,
                    'subject' => $subject,
                ),
                'provider' => 'core_email',
                'status' => 'sent',
                'error_message' => '',
            ));
        }

        if (function_exists('vms_add_admin_notice')) {
            vms_add_admin_notice(__('Onboarding email sent and logged on the vendor record.', 'vms'), 'success');
        }

        wp_safe_redirect(add_query_arg(array('page' => vms_vendor_command_center_page_slug(), 'vendor_id' => $vendor_id), admin_url('admin.php')));
        exit;
    }
}

add_action('admin_post_vms_vendor_command_center_link_matching_user', 'vms_vendor_command_center_handle_link_matching_user');
if (!function_exists('vms_vendor_command_center_handle_link_matching_user')) {
    function vms_vendor_command_center_handle_link_matching_user(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'vms'));
        }

        $vendor_id = absint(wp_unslash((string) ($_POST['vendor_id'] ?? 0)));
        check_admin_referer('vms_vendor_command_center_link_matching_user_' . $vendor_id, 'vms_vendor_command_center_link_nonce');

        if ($vendor_id <= 0 || get_post_type($vendor_id) !== (defined('VMS_VENDOR_CPT') ? VMS_VENDOR_CPT : 'vms_vendor')) {
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('Vendor account linking failed because the vendor record could not be found.', 'vms'), 'error');
            }
            wp_safe_redirect(admin_url('admin.php?page=' . vms_vendor_command_center_page_slug()));
            exit;
        }

        $linked_user_id = vms_vendor_command_center_get_linked_user_id($vendor_id);
        if ($linked_user_id > 0) {
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('This vendor is already linked to a website account.', 'vms'), 'warning');
            }
            wp_safe_redirect(admin_url('admin.php?page=' . vms_vendor_command_center_page_slug()));
            exit;
        }

        $user_id = vms_vendor_command_center_get_candidate_user_id($vendor_id);
        if ($user_id <= 0) {
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('No matching website account was found for this vendor email.', 'vms'), 'error');
            }
            wp_safe_redirect(admin_url('admin.php?page=' . vms_vendor_command_center_page_slug()));
            exit;
        }

        $ok = false;
        if (function_exists('vms_vendor_user_link_upsert')) {
            $ok = (bool) vms_vendor_user_link_upsert($vendor_id, $user_id, array(
                'role' => 'primary_contact',
                'status' => 'active',
                'set_primary_for_user' => true,
                'source' => 'vendor_command_center',
            ), (int) get_current_user_id());
        } else {
            update_post_meta($vendor_id, (defined('VMS_VENDOR_PRIMARY_USER_META_KEY') ? VMS_VENDOR_PRIMARY_USER_META_KEY : '_vms_vendor_user_id'), $user_id);
            update_user_meta($user_id, (defined('VMS_USER_PRIMARY_VENDOR_META_KEY') ? VMS_USER_PRIMARY_VENDOR_META_KEY : '_vms_vendor_id'), $vendor_id);
            $ok = true;
        }

        if (function_exists('vms_add_admin_notice')) {
            if ($ok) {
                vms_add_admin_notice(__('Matching website account linked to vendor profile.', 'vms'), 'success');
            } else {
                vms_add_admin_notice(__('Vendor account link could not be saved.', 'vms'), 'error');
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=' . vms_vendor_command_center_page_slug()));
        exit;
    }
}

if (!function_exists('vms_vendor_command_center_register_tours')) {
    /**
     * @param array<int,array<string,mixed>> $tours
     * @return array<int,array<string,mixed>>
     */
    function vms_vendor_command_center_register_tours(array $tours): array
    {
        $tours[] = array(
            'id' => 'vms.vendor_command_center.basics',
            'title' => __('Vendor Command Center', 'vms'),
            'screen' => 'admin:' . vms_vendor_command_center_page_slug(),
            'version' => '1.0.0',
            'level' => 'beginner',
            'description' => __('Review vendor account setup, onboarding outreach, and payable health from one table.', 'vms'),
            'audience' => array(
                'capabilities_any' => array('manage_options'),
                'capabilities_all' => array(),
                'roles_any' => array(),
                'roles_all' => array(),
            ),
            'auto_run' => true,
            'priority' => 10,
            'steps' => array(
                array(
                    'id' => 'vendor_command_summary',
                    'selector' => '[data-vms-tour="vendor-command.summary"]',
                    'title' => __('Summary cards', 'vms'),
                    'body' => wp_kses_post(__('Start here to see how many vendors still need outreach, linking, or payment cleanup before you open the full table.', 'vms')),
                    'placement' => 'bottom',
                    'guard' => array('type' => 'element_exists'),
                ),
                array(
                    'id' => 'vendor_command_compose',
                    'selector' => '[data-vms-tour="vendor-command.compose"]',
                    'title' => __('Single-vendor outreach', 'vms'),
                    'body' => wp_kses_post(__('Use this composer when one vendor needs a reminder or portal setup email without running a batch workflow.', 'vms')),
                    'placement' => 'bottom',
                    'guard' => array('type' => 'element_exists'),
                ),
                array(
                    'id' => 'vendor_command_template',
                    'selector' => '[data-vms-tour="vendor-command.template"]',
                    'title' => __('Saved template', 'vms'),
                    'body' => wp_kses_post(__('Set your General default once here, then add vendor-type templates anywhere you want different wording. The single-vendor composer will pick the matching saved template automatically.', 'vms')),
                    'placement' => 'bottom',
                    'guard' => array('type' => 'element_exists'),
                ),
                array(
                    'id' => 'vendor_command_booked_automation',
                    'selector' => '[data-vms-tour="vendor-command.booked-automation"]',
                    'title' => __('Booked vendor automation', 'vms'),
                    'body' => wp_kses_post(__("Configure the automatic “you’ve been booked” email, the account-link prompt, and the soft-requested headliner promo-video workflow here. This is where consistency turns one-off follow-up into a repeatable system.", 'vms')),
                    'placement' => 'bottom',
                    'guard' => array('type' => 'element_exists'),
                ),
                array(
                    'id' => 'vendor_command_filters',
                    'selector' => '[data-vms-tour="vendor-command.filters"]',
                    'title' => __('Filter the list', 'vms'),
                    'body' => wp_kses_post(__('Narrow the table by account state, onboarding state, payable state, or vendor type when you need to work a focused queue.', 'vms')),
                    'placement' => 'bottom',
                    'guard' => array('type' => 'element_exists'),
                ),
                array(
                    'id' => 'vendor_command_table',
                    'selector' => '[data-vms-tour="vendor-command.table"]',
                    'title' => __('At-a-glance vendor health', 'vms'),
                    'body' => wp_kses_post(__('Each row combines account registration, profile linking, application status, outreach, next date, and open payable health so you do not have to jump across scattered screens.', 'vms')),
                    'placement' => 'top',
                    'guard' => array('type' => 'element_exists'),
                ),
            ),
        );

        return $tours;
    }
}
add_filter('vms_tours_register', 'vms_vendor_command_center_register_tours');
