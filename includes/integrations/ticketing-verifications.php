<?php
defined('ABSPATH') || exit;

if (!defined('VMS_VERIFICATION_PROOF_TTL_DAYS')) {
    define('VMS_VERIFICATION_PROOF_TTL_DAYS', 7);
}

if (!defined('VMS_TICKETING_VERIFICATION_RETENTION_DAYS')) {
    define('VMS_TICKETING_VERIFICATION_RETENTION_DAYS', (int) VMS_VERIFICATION_PROOF_TTL_DAYS);
}

if (!defined('VMS_TICKETING_VERIFICATION_MAX_UPLOAD_BYTES')) {
    define('VMS_TICKETING_VERIFICATION_MAX_UPLOAD_BYTES', 20 * 1024 * 1024);
}

if (!defined('VMS_TICKETING_VERIFICATION_WARN_UPLOAD_BYTES')) {
    define('VMS_TICKETING_VERIFICATION_WARN_UPLOAD_BYTES', 10 * 1024 * 1024);
}

if (!defined('VMS_TICKETING_VERIFICATION_IMAGE_MAX_DIMENSION')) {
    define('VMS_TICKETING_VERIFICATION_IMAGE_MAX_DIMENSION', 2200);
}

if (!defined('VMS_TICKETING_VERIFICATION_IMAGE_QUALITY')) {
    define('VMS_TICKETING_VERIFICATION_IMAGE_QUALITY', 86);
}

if (!function_exists('vms_ticketing_verification_manage_capability')) {
    function vms_ticketing_verification_manage_capability(): string
    {
        return 'vms_manage_verifications';
    }
}

if (!function_exists('vms_ticketing_verification_request_post_type')) {
    function vms_ticketing_verification_request_post_type(): string
    {
        return 'vms_verify_req';
    }
}

if (!function_exists('vms_ticketing_verification_request_post_type_legacy')) {
    function vms_ticketing_verification_request_post_type_legacy(): string
    {
        return 'vms_verification_request';
    }
}

if (!function_exists('vms_ticketing_verification_request_post_types')) {
    /**
     * @return array<int,string>
     */
    function vms_ticketing_verification_request_post_types(): array
    {
        $types = array(
            vms_ticketing_verification_request_post_type(),
            vms_ticketing_verification_request_post_type_legacy(),
        );
        $types = array_values(array_unique(array_filter(array_map('sanitize_key', $types))));
        return !empty($types) ? $types : array('vms_verify_req');
    }
}

if (!function_exists('vms_ticketing_verification_upload_settings_option_key')) {
    function vms_ticketing_verification_upload_settings_option_key(): string
    {
        return 'vms_verification_upload_settings';
    }
}

if (!function_exists('vms_ticketing_verification_default_upload_settings')) {
    /**
     * @return array<string,int>
     */
    function vms_ticketing_verification_default_upload_settings(): array
    {
        return array(
            'max_upload_mb' => max(1, (int) round(((int) VMS_TICKETING_VERIFICATION_MAX_UPLOAD_BYTES) / (1024 * 1024))),
        );
    }
}

if (!function_exists('vms_ticketing_verification_sanitize_upload_settings')) {
    /**
     * @param mixed $raw
     * @return array<string,int>
     */
    function vms_ticketing_verification_sanitize_upload_settings($raw): array
    {
        $defaults = vms_ticketing_verification_default_upload_settings();
        $input = is_array($raw) ? $raw : array();

        $max_upload_mb = isset($input['max_upload_mb']) ? absint($input['max_upload_mb']) : (int) $defaults['max_upload_mb'];
        $max_upload_mb = max(1, min(50, $max_upload_mb));

        return array(
            'max_upload_mb' => $max_upload_mb,
        );
    }
}

if (!function_exists('vms_ticketing_verification_get_upload_settings')) {
    /**
     * @return array<string,int>
     */
    function vms_ticketing_verification_get_upload_settings(): array
    {
        $stored = get_option(vms_ticketing_verification_upload_settings_option_key(), array());
        return vms_ticketing_verification_sanitize_upload_settings($stored);
    }
}

if (!function_exists('vms_ticketing_verification_get_configured_max_upload_bytes')) {
    function vms_ticketing_verification_get_configured_max_upload_bytes(): int
    {
        $settings = vms_ticketing_verification_get_upload_settings();
        return max(1, (int) ($settings['max_upload_mb'] ?? 20)) * 1024 * 1024;
    }
}

if (!function_exists('vms_ticketing_verification_get_effective_max_upload_bytes')) {
    function vms_ticketing_verification_get_effective_max_upload_bytes(): int
    {
        $configured = vms_ticketing_verification_get_configured_max_upload_bytes();
        if (!function_exists('wp_max_upload_size')) {
            return $configured;
        }

        $server_limit = (int) wp_max_upload_size();
        if ($server_limit <= 0) {
            return $configured;
        }

        return min($configured, $server_limit);
    }
}

if (!function_exists('vms_ticketing_verification_get_warn_upload_bytes')) {
    function vms_ticketing_verification_get_warn_upload_bytes(): int
    {
        $effective = vms_ticketing_verification_get_effective_max_upload_bytes();
        $default_warn = max(1, (int) VMS_TICKETING_VERIFICATION_WARN_UPLOAD_BYTES);
        if ($effective <= 0) {
            return $default_warn;
        }

        $scaled = (int) floor($effective * 0.6);
        $scaled = max(2 * 1024 * 1024, $scaled);
        $scaled = min($scaled, max(1, $effective - (512 * 1024)));

        return $scaled > 0 ? $scaled : $default_warn;
    }
}

if (!function_exists('vms_ticketing_verification_program_labels_option_key')) {
    function vms_ticketing_verification_program_labels_option_key(): string
    {
        return 'vms_verification_program_labels';
    }
}

if (!function_exists('vms_ticketing_verification_default_programs')) {
    /**
     * @return array<string,string>
     */
    function vms_ticketing_verification_default_programs(): array
    {
        return array(
            'veteran' => __('Veteran', 'backstage-venue-manager'),
            'teacher' => __('Teacher', 'backstage-venue-manager'),
            'first_responder' => __('First Responder', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_ticketing_verification_sanitize_program_map')) {
    /**
     * @param mixed $raw
     * @return array<string,string>
     */
    function vms_ticketing_verification_sanitize_program_map($raw): array
    {
        $input = is_array($raw) ? $raw : array();
        $out = array();

        foreach ($input as $key => $label) {
            $program = sanitize_key((string) $key);
            if ($program === '') {
                continue;
            }

            $text = trim(sanitize_text_field((string) $label));
            if ($text === '') {
                $text = ucwords(str_replace('_', ' ', $program));
            }

            $out[$program] = $text;
        }

        return $out;
    }
}

if (!function_exists('vms_ticketing_verification_programs')) {
    /**
     * @return array<string,string>
     */
    function vms_ticketing_verification_programs(): array
    {
        $base = vms_ticketing_verification_default_programs();
        $stored = get_option(vms_ticketing_verification_program_labels_option_key(), array());
        $stored = vms_ticketing_verification_sanitize_program_map($stored);
        $merged = array_merge($base, $stored);

        $programs = apply_filters('vms_ticketing_verification_programs', $merged);
        if (!is_array($programs)) {
            return $merged;
        }

        $out = vms_ticketing_verification_sanitize_program_map($programs);
        return !empty($out) ? $out : $merged;
    }
}

if (!function_exists('vms_ticketing_verification_program_label')) {
    function vms_ticketing_verification_program_label(string $program): string
    {
        $program = sanitize_key($program);
        $programs = vms_ticketing_verification_programs();
        if ($program !== '' && isset($programs[$program])) {
            return (string) $programs[$program];
        }
        return ucwords(str_replace('_', ' ', $program));
    }
}

if (!function_exists('vms_ticketing_verification_role_for_program')) {
    function vms_ticketing_verification_role_for_program(string $program): string
    {
        $program = sanitize_key($program);
        if ($program === '') {
            return '';
        }
        return 'vms_verified_' . $program;
    }
}

if (!function_exists('vms_ticketing_verification_role_label_for_program')) {
    function vms_ticketing_verification_role_label_for_program(string $program): string
    {
        $program = sanitize_key($program);
        if ($program === '') {
            return __('VMS Verified Member', 'backstage-venue-manager');
        }
        $program_label = vms_ticketing_verification_program_label($program);
        /* translators: %s: human-readable value used in this message. */
        return sprintf(__('VMS Verified %s', 'backstage-venue-manager'), $program_label);
    }
}

if (!function_exists('vms_ticketing_verification_ensure_roles')) {
    function vms_ticketing_verification_ensure_roles(): void
    {
        foreach (vms_ticketing_verification_programs() as $program => $_label) {
            $program = sanitize_key((string) $program);
            if ($program === '') {
                continue;
            }

            $role_key = vms_ticketing_verification_role_for_program($program);
            if ($role_key === '') {
                continue;
            }

            $role_label = vms_ticketing_verification_role_label_for_program($program);
            $role = get_role($role_key);
            if (!($role instanceof WP_Role)) {
                add_role($role_key, $role_label, array('read' => true));
                $role = get_role($role_key);
            }

            if ($role instanceof WP_Role && !$role->has_cap('read')) {
                $role->add_cap('read');
            }
        }
    }
}
add_action('init', 'vms_ticketing_verification_ensure_roles', 5);

if (!function_exists('vms_ticketing_verification_allowances_option_key')) {
    function vms_ticketing_verification_allowances_option_key(): string
    {
        return 'vms_verification_program_allowances';
    }
}

if (!function_exists('vms_ticketing_verification_default_allowances')) {
    /**
     * @return array<string,int>
     */
    function vms_ticketing_verification_default_allowances(): array
    {
        $defaults = array();
        foreach (vms_ticketing_verification_programs() as $program => $_label) {
            $defaults[sanitize_key((string) $program)] = 2;
        }
        return $defaults;
    }
}

if (!function_exists('vms_ticketing_verification_sanitize_allowances')) {
    /**
     * @param mixed $raw
     * @return array<string,int>
     */
    function vms_ticketing_verification_sanitize_allowances($raw): array
    {
        $programs = vms_ticketing_verification_programs();
        $defaults = vms_ticketing_verification_default_allowances();
        $input = is_array($raw) ? $raw : array();
        $out = array();

        foreach ($programs as $program => $_label) {
            $program = sanitize_key((string) $program);
            $default_value = (int) ($defaults[$program] ?? 2);
            $value = array_key_exists($program, $input) ? $input[$program] : $default_value;

            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value === '' || $value === null) {
                $value = $default_value;
            }

            $int_value = max(0, absint($value));
            $out[$program] = $int_value;
        }

        return $out;
    }
}

if (!function_exists('vms_ticketing_verification_get_program_allowances')) {
    /**
     * @return array<string,int>
     */
    function vms_ticketing_verification_get_program_allowances(): array
    {
        $raw = get_option(vms_ticketing_verification_allowances_option_key(), array());
        return vms_ticketing_verification_sanitize_allowances($raw);
    }
}

if (!function_exists('vms_ticketing_verification_get_program_default_allowance')) {
    function vms_ticketing_verification_get_program_default_allowance(string $program): int
    {
        $program = sanitize_key($program);
        if ($program === '') {
            return 0;
        }
        $allowances = vms_ticketing_verification_get_program_allowances();
        return max(0, absint($allowances[$program] ?? 0));
    }
}

if (!function_exists('vms_ticketing_verification_get_user_allowance_override')) {
    function vms_ticketing_verification_get_user_allowance_override(int $user_id, string $program): ?int
    {
        $user_id = absint($user_id);
        $program = sanitize_key($program);
        if ($user_id <= 0 || $program === '') {
            return null;
        }

        $key = 'vms_verified_allowance_' . $program;
        $raw = get_user_meta($user_id, $key, true);
        if ($raw === '' || $raw === null) {
            return null;
        }

        return max(0, absint($raw));
    }
}

if (!function_exists('vms_ticketing_verification_get_effective_allowance')) {
    function vms_ticketing_verification_get_effective_allowance(int $user_id, string $program): int
    {
        $program = sanitize_key($program);
        if ($program === '') {
            return 0;
        }

        $override = vms_ticketing_verification_get_user_allowance_override($user_id, $program);
        if ($override !== null) {
            return max(0, absint($override));
        }

        return vms_ticketing_verification_get_program_default_allowance($program);
    }
}

if (!function_exists('vms_ticketing_verification_resolve_ticket_limit')) {
    function vms_ticketing_verification_resolve_ticket_limit(int $user_id, string $program, int $ticket_max_qty = 0): int
    {
        $allowance = vms_ticketing_verification_get_effective_allowance($user_id, $program);
        $ticket_max_qty = max(0, absint($ticket_max_qty));
        if ($ticket_max_qty <= 0) {
            return $allowance;
        }
        if ($allowance <= 0) {
            return $ticket_max_qty;
        }
        return min($allowance, $ticket_max_qty);
    }
}

if (!function_exists('vms_ticketing_verification_count_purchased_qty_for_program_event')) {
    function vms_ticketing_verification_count_purchased_qty_for_program_event(int $user_id, string $program, int $tec_event_id): int
    {
        $user_id = absint($user_id);
        $program = sanitize_key($program);
        $tec_event_id = absint($tec_event_id);
        if ($user_id <= 0 || $program === '' || $tec_event_id <= 0 || !function_exists('wc_get_orders')) {
            return 0;
        }

        $visibility_key = function_exists('vms_ticketing_v2_product_meta_key')
            ? vms_ticketing_v2_product_meta_key('ticketing_visibility_mode')
            : '_vms_ticketing_visibility_mode';
        $program_key = function_exists('vms_ticketing_v2_product_meta_key')
            ? vms_ticketing_v2_product_meta_key('ticketing_verified_program')
            : '_vms_ticketing_verified_program';

        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status' => array('wc-processing', 'wc-completed'),
            'limit' => -1,
            'return' => 'objects',
        ));

        $total = 0;
        foreach ((array) $orders as $order) {
            if (!is_object($order) || !method_exists($order, 'get_items')) {
                continue;
            }
            foreach ((array) $order->get_items('line_item') as $item) {
                if (!is_object($item) || !method_exists($item, 'get_product_id')) {
                    continue;
                }

                $variation_id = method_exists($item, 'get_variation_id') ? absint($item->get_variation_id()) : 0;
                $pid = absint($variation_id ?: $item->get_product_id());
                if ($pid <= 0) {
                    continue;
                }
                $linked_event = function_exists('vms_ticketing_v2_meta_get')
                    ? absint(vms_ticketing_v2_meta_get($pid, '_tribe_wooticket_for_event'))
                    : absint(get_post_meta($pid, '_tribe_wooticket_for_event', true));
                if ($linked_event !== $tec_event_id) {
                    continue;
                }

                $visibility_mode = function_exists('vms_ticketing_v2_meta_get')
                    ? sanitize_key((string) vms_ticketing_v2_meta_get($pid, $visibility_key))
                    : sanitize_key((string) get_post_meta($pid, $visibility_key, true));
                $ticket_program = function_exists('vms_ticketing_v2_meta_get')
                    ? sanitize_key((string) vms_ticketing_v2_meta_get($pid, $program_key))
                    : sanitize_key((string) get_post_meta($pid, $program_key, true));
                if ($visibility_mode !== 'verified' || $ticket_program !== $program) {
                    continue;
                }

                $line_qty = method_exists($item, 'get_quantity') ? absint($item->get_quantity()) : 0;
                $total += max(0, $line_qty);
            }
        }

        return max(0, $total);
    }
}

if (!function_exists('vms_ticketing_verification_current_user_can_manage')) {
    function vms_ticketing_verification_current_user_can_manage(): bool
    {
        $cap = vms_ticketing_verification_manage_capability();
        return current_user_can($cap) || current_user_can('manage_options');
    }
}

if (!function_exists('vms_ticketing_verification_ensure_caps')) {
    function vms_ticketing_verification_ensure_caps(): void
    {
        $admin = get_role('administrator');
        if (!($admin instanceof WP_Role)) {
            return;
        }

        $cap = vms_ticketing_verification_manage_capability();
        if (!$admin->has_cap($cap)) {
            $admin->add_cap($cap);
        }
    }
}
add_action('init', 'vms_ticketing_verification_ensure_caps', 6);

if (!function_exists('vms_ticketing_verification_register_cpt')) {
    function vms_ticketing_verification_register_cpt(): void
    {
        register_post_status('approved', array(
            'label'                     => _x('Approved', 'verification status', 'backstage-venue-manager'),
            'public'                    => false,
            'internal'                  => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: human-readable value used in this message. */
            'label_count'               => _n_noop('Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>', 'backstage-venue-manager'),
        ));

        register_post_status('denied', array(
            'label'                     => _x('Denied', 'verification status', 'backstage-venue-manager'),
            'public'                    => false,
            'internal'                  => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: human-readable value used in this message. */
            'label_count'               => _n_noop('Denied <span class="count">(%s)</span>', 'Denied <span class="count">(%s)</span>', 'backstage-venue-manager'),
        ));

        register_post_type(vms_ticketing_verification_request_post_type(), array(
            'labels' => array(
                'name'          => __('Verification Requests', 'backstage-venue-manager'),
                'singular_name' => __('Verification Request', 'backstage-venue-manager'),
            ),
            'public'              => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'exclude_from_search' => true,
            'map_meta_cap'        => true,
            'supports'            => array('title', 'author'),
        ));
    }
}
add_action('init', 'vms_ticketing_verification_register_cpt', 7);

if (!function_exists('vms_ticketing_verification_migrate_legacy_post_type_once')) {
    function vms_ticketing_verification_migrate_legacy_post_type_once(): void
    {
        $marker = 'vms_ticketing_verification_pt_migrated_v1';
        if ((string) get_option($marker, '') === '1') {
            return;
        }

        global $wpdb;
        $legacy = vms_ticketing_verification_request_post_type_legacy();
        $canonical = vms_ticketing_verification_request_post_type();
        if ($legacy === '' || $canonical === '' || $legacy === $canonical) {
            update_option($marker, '1', false);
            return;
        }

        $sql = $wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
            $canonical,
            $legacy
        );
        $wpdb->query($sql);
        update_option($marker, '1', false);
    }
}
add_action('init', 'vms_ticketing_verification_migrate_legacy_post_type_once', 8);

if (!function_exists('vms_ticketing_verification_allowed_mimes')) {
    /**
     * @return array<string,string>
     */
    function vms_ticketing_verification_allowed_mimes(): array
    {
        $base = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
        );
        $mimes = apply_filters('vms_ticketing_verification_allowed_mimes', $base);
        return is_array($mimes) ? $mimes : $base;
    }
}

if (!function_exists('vms_ticketing_verification_is_image_mime')) {
    function vms_ticketing_verification_is_image_mime(string $mime): bool
    {
        $mime = trim(strtolower($mime));
        return in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true);
    }
}

if (!function_exists('vms_ticketing_verification_image_extension_for_mime')) {
    function vms_ticketing_verification_image_extension_for_mime(string $mime): string
    {
        return trim(strtolower($mime)) === 'image/jpeg' ? 'jpg' : 'jpg';
    }
}

if (!function_exists('vms_ticketing_verification_image_output_mime')) {
    function vms_ticketing_verification_image_output_mime(): string
    {
        return 'image/jpeg';
    }
}

if (!function_exists('vms_ticketing_verification_format_bytes')) {
    function vms_ticketing_verification_format_bytes(int $bytes): string
    {
        $bytes = max(0, $bytes);
        if ($bytes < 1024) {
            /* translators: %d: number of items described in this message. */
            return sprintf(__('%d B', 'backstage-venue-manager'), $bytes);
        }

        $units = array('KB', 'MB', 'GB');
        $value = $bytes / 1024;
        $unit_index = 0;
        while ($value >= 1024 && $unit_index < (count($units) - 1)) {
            $value /= 1024;
            $unit_index++;
        }

        $decimals = $value >= 100 ? 0 : ($value >= 10 ? 1 : 2);
        return sprintf('%s %s', number_format_i18n($value, $decimals), $units[$unit_index]);
    }
}

if (!function_exists('vms_ticketing_verification_allowed_formats_label')) {
    function vms_ticketing_verification_allowed_formats_label(): string
    {
        return __('JPG, PNG, WEBP, PDF', 'backstage-venue-manager');
    }
}

if (!function_exists('vms_ticketing_verification_is_heic_extension')) {
    function vms_ticketing_verification_is_heic_extension(string $extension): bool
    {
        return in_array(trim(strtolower($extension)), array('heic', 'heics', 'heif', 'heifs'), true);
    }
}

if (!function_exists('vms_ticketing_verification_guess_upload_kind')) {
    function vms_ticketing_verification_guess_upload_kind(string $filename = '', string $mime = ''): string
    {
        $mime = trim(strtolower($mime));
        $extension = trim(strtolower(pathinfo($filename, PATHINFO_EXTENSION)));

        if ($mime === 'application/pdf' || $extension === 'pdf') {
            return 'pdf';
        }

        if ($mime === 'image/heic' || $mime === 'image/heif' || vms_ticketing_verification_is_heic_extension($extension)) {
            return 'heic';
        }

        if (strpos($mime, 'image/') === 0 || in_array($extension, array('jpg', 'jpeg', 'png', 'webp'), true)) {
            return 'image';
        }

        return 'other';
    }
}

if (!function_exists('vms_ticketing_verification_upload_error_notice_code')) {
    function vms_ticketing_verification_upload_error_notice_code(int $error, string $filename = '', string $mime = ''): string
    {
        $kind = vms_ticketing_verification_guess_upload_kind($filename, $mime);

        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return $kind === 'pdf' ? 'pdf_too_large' : 'file_too_large';
            case UPLOAD_ERR_NO_FILE:
                return 'file_missing';
            case UPLOAD_ERR_PARTIAL:
                return 'save_failed';
            default:
                return 'save_failed';
        }
    }
}

if (!function_exists('vms_ticketing_verification_optimize_image_upload')) {
    /**
     * @return array{path:string,mime:string}|WP_Error
     */
    function vms_ticketing_verification_optimize_image_upload(string $tmp_name, string $root, string $filename_base)
    {
        $normalized = vms_normalize_uploaded_image_to_jpeg($tmp_name, $root, $filename_base, array(
            'max_dimension' => (int) VMS_TICKETING_VERIFICATION_IMAGE_MAX_DIMENSION,
            'quality' => (int) VMS_TICKETING_VERIFICATION_IMAGE_QUALITY,
            'max_output_bytes' => vms_ticketing_verification_get_effective_max_upload_bytes(),
        ));
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        return array(
            'path' => (string) ($normalized['path'] ?? ''),
            'mime' => sanitize_text_field((string) ($normalized['mime'] ?? vms_ticketing_verification_image_output_mime())),
        );
    }
}

if (!function_exists('vms_ticketing_verification_upload_root')) {
    function vms_ticketing_verification_upload_root(): string
    {
        if (!function_exists('vms_private_files_ensure_dir') || !function_exists('vms_private_files_bucket_dir')) {
            return '';
        }

        if (!vms_private_files_ensure_dir('verifications')) {
            return '';
        }

        return vms_private_files_bucket_dir('verifications');
    }
}

if (!function_exists('vms_ticketing_verification_path_within_root')) {
    function vms_ticketing_verification_path_within_root(string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }

        $upload_dir = wp_upload_dir(null, false);
        $base = isset($upload_dir['basedir']) ? trim((string) $upload_dir['basedir']) : '';
        $roots = array();

        $current_root = vms_ticketing_verification_upload_root();
        if ($current_root !== '') {
            $roots[] = $current_root;
        }
        if ($base !== '') {
            $legacy_root = trailingslashit($base) . 'vms-verification-proofs';
            if ($legacy_root !== '' && !in_array($legacy_root, $roots, true)) {
                $roots[] = $legacy_root;
            }
        }

        if (empty($roots)) {
            return false;
        }

        $real_path = realpath($path);
        if ($real_path === false) {
            return false;
        }

        $real_path = wp_normalize_path($real_path);
        if ($real_path === '') {
            return false;
        }

        foreach ($roots as $root) {
            $real_root = realpath((string) $root);
            if ($real_root === false) {
                continue;
            }

            $real_root = wp_normalize_path($real_root);
            if ($real_root === '') {
                continue;
            }

            if (strpos($real_path, trailingslashit($real_root)) === 0 || $real_path === $real_root) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('vms_ticketing_verification_delete_proof_file')) {
    function vms_ticketing_verification_delete_proof_file(string $path): void
    {
        $path = trim($path);
        if ($path === '') {
            return;
        }

        if (!vms_ticketing_verification_path_within_root($path)) {
            return;
        }

        if (file_exists($path) && is_file($path)) {
            @unlink($path);
        }
    }
}

if (!function_exists('vms_ticketing_verification_store_proof_file')) {
    /**
     * @param array<string,mixed> $validated_upload
     * @return array{file_id:int,mime:string}|WP_Error
     */
    function vms_ticketing_verification_store_proof_file(array $validated_upload)
    {
        $mime = sanitize_text_field((string) ($validated_upload['mime'] ?? ''));
        if ($mime === '') {
            return new WP_Error('save_failed', __('Could not save the uploaded verification proof.', 'backstage-venue-manager'));
        }

        if (!vms_ticketing_verification_is_image_mime($mime)) {
            $file_id = vms_private_files_store_validated_upload(
                $validated_upload,
                array(
                    'bucket' => 'verifications',
                )
            );
            if (is_wp_error($file_id)) {
                return $file_id;
            }

            return array(
                'file_id' => (int) $file_id,
                'mime' => $mime,
            );
        }

        $root = vms_ticketing_verification_upload_root();
        if ($root === '' || !is_dir($root) || !is_writable($root)) {
            return new WP_Error('save_failed', __('Could not save the uploaded verification proof.', 'backstage-venue-manager'));
        }

        $storage_key = function_exists('vms_private_files_generate_storage_key')
            ? vms_private_files_generate_storage_key('verifications', vms_ticketing_verification_image_extension_for_mime(vms_ticketing_verification_image_output_mime()))
            : '';
        $target_path = $storage_key !== '' && function_exists('vms_private_files_absolute_path')
            ? vms_private_files_absolute_path($storage_key)
            : '';
        if ($storage_key === '' || $target_path === '') {
            return new WP_Error('save_failed', __('Could not save the uploaded verification proof.', 'backstage-venue-manager'));
        }

        $optimized = vms_ticketing_verification_optimize_image_upload(
            (string) ($validated_upload['tmp_name'] ?? ''),
            dirname($target_path),
            (string) pathinfo(basename($target_path), PATHINFO_FILENAME)
        );
        if (is_wp_error($optimized)) {
            return $optimized;
        }

        $stored_path = trim((string) ($optimized['path'] ?? ''));
        $stored_mime = sanitize_text_field((string) ($optimized['mime'] ?? vms_ticketing_verification_image_output_mime()));
        if ($stored_path === '' || !vms_ticketing_verification_path_within_root($stored_path)) {
            return new WP_Error('save_failed', __('Could not save the uploaded verification proof.', 'backstage-venue-manager'));
        }

        $stored_key = 'verifications/' . basename($stored_path);
        $display_name = sanitize_file_name((string) ($validated_upload['sanitized_name'] ?? 'proof.jpg'));
        $display_base = (string) pathinfo($display_name, PATHINFO_FILENAME);
        if ($display_base === '') {
            $display_base = 'proof';
        }
        $display_name = $display_base . '.jpg';
        $file_id = function_exists('vms_private_files_register_path')
            ? vms_private_files_register_path(
                $stored_key,
                $stored_path,
                $display_name,
                $stored_mime,
                array(
                    'bucket' => 'verifications',
                )
            )
            : new WP_Error('save_failed', __('Could not save the uploaded verification proof.', 'backstage-venue-manager'));
        if (is_wp_error($file_id)) {
            if ($stored_path !== '' && file_exists($stored_path)) {
                @unlink($stored_path);
            }
            return $file_id;
        }

        return array(
            'file_id' => (int) $file_id,
            'mime' => $stored_mime,
        );
    }
}

if (!function_exists('vms_ticketing_verification_proof_payload')) {
    /**
     * @return array<string,string|int>|WP_Error
     */
    function vms_ticketing_verification_proof_payload(int $request_id)
    {
        $request_id = absint($request_id);
        if ($request_id <= 0) {
            return new WP_Error('proof_missing', __('Proof file not found or already deleted.', 'backstage-venue-manager'));
        }

        $file_id = absint(get_post_meta($request_id, 'proof_file_id', true));
        $storage_kind = sanitize_key((string) get_post_meta($request_id, 'proof_storage_kind', true));
        if ($file_id > 0 && $storage_kind === 'private_file') {
            $row = vms_private_file_get($file_id);
            if (!is_array($row)) {
                return new WP_Error('proof_missing', __('Proof file not found or already deleted.', 'backstage-venue-manager'));
            }

            $path = vms_private_file_path((string) ($row['stored_filename'] ?? ''));
            if ($path === '' || !vms_ticketing_verification_path_within_root($path)) {
                return new WP_Error('proof_missing', __('Proof file not found or already deleted.', 'backstage-venue-manager'));
            }

            return array(
                'path' => $path,
                'mime' => (string) ($row['mime_type'] ?? 'application/octet-stream'),
                'filename' => (string) ($row['original_filename'] ?? 'verification-proof'),
                'storage_kind' => 'private_file',
                'file_id' => $file_id,
            );
        }

        $path = (string) get_post_meta($request_id, 'proof_file_path', true);
        $mime = (string) get_post_meta($request_id, 'proof_mime', true);
        if ($path === '' || !file_exists($path) || !vms_ticketing_verification_path_within_root($path)) {
            return new WP_Error('proof_missing', __('Proof file not found or already deleted.', 'backstage-venue-manager'));
        }

        return array(
            'path' => $path,
            'mime' => $mime !== '' ? $mime : 'application/octet-stream',
            'filename' => sanitize_file_name((string) basename($path)),
            'storage_kind' => 'legacy_path',
            'file_id' => 0,
        );
    }
}

if (!function_exists('vms_ticketing_verification_delete_proof_asset_for_request')) {
    function vms_ticketing_verification_delete_proof_asset_for_request(int $request_id): void
    {
        $request_id = absint($request_id);
        if ($request_id <= 0) {
            return;
        }

        $file_id = absint(get_post_meta($request_id, 'proof_file_id', true));
        $storage_kind = sanitize_key((string) get_post_meta($request_id, 'proof_storage_kind', true));
        if ($file_id > 0 && $storage_kind === 'private_file' && function_exists('vms_private_files_delete')) {
            vms_private_files_delete($file_id);
        }

        $legacy_path = (string) get_post_meta($request_id, 'proof_file_path', true);
        if ($legacy_path !== '') {
            vms_ticketing_verification_delete_proof_file($legacy_path);
        }

        delete_post_meta($request_id, 'proof_file_id');
        delete_post_meta($request_id, 'proof_storage_kind');
        delete_post_meta($request_id, 'proof_file_path');
    }
}

if (!function_exists('vms_ticketing_get_user_verified_programs')) {
    /**
     * @return string[]
     */
    function vms_ticketing_get_user_verified_programs(int $user_id): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return array();
        }

        $programs = array();
        $meta = get_user_meta($user_id, 'vms_verified_programs', true);
        if (is_array($meta)) {
            foreach ($meta as $program) {
                $program = sanitize_key((string) $program);
                if ($program !== '') {
                    $programs[$program] = $program;
                }
            }
        }

        $user = get_userdata($user_id);
        if ($user instanceof WP_User) {
            foreach ((array) $user->roles as $role) {
                $role = sanitize_key((string) $role);
                if (strpos($role, 'vms_verified_') !== 0) {
                    continue;
                }
                $program = sanitize_key(substr($role, strlen('vms_verified_')));
                if ($program !== '') {
                    $programs[$program] = $program;
                }
            }
        }

        $all = vms_ticketing_verification_programs();
        $out = array();
        foreach (array_keys($programs) as $program) {
            if (isset($all[$program])) {
                $out[] = $program;
            }
        }

        sort($out, SORT_STRING);
        return $out;
    }
}

if (!function_exists('vms_ticketing_get_current_user_verified_programs')) {
    /**
     * @return string[]
     */
    function vms_ticketing_get_current_user_verified_programs(): array
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return array();
        }
        return vms_ticketing_get_user_verified_programs((int) $user_id);
    }
}

if (!function_exists('vms_ticketing_verification_get_latest_request')) {
    function vms_ticketing_verification_get_latest_request(int $user_id, array $statuses = array('pending', 'denied')): ?WP_Post
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return null;
        }

        $query_statuses = array_values(array_unique(array_filter(array_map('sanitize_key', $statuses))));
        if (empty($query_statuses)) {
            return null;
        }

        $posts = get_posts(array(
            'post_type'      => vms_ticketing_verification_request_post_types(),
            'post_status'    => $query_statuses,
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'meta_key'       => 'user_id',
            'meta_value'     => (string) $user_id,
        ));

        if (empty($posts) || !($posts[0] instanceof WP_Post)) {
            return null;
        }

        return $posts[0];
    }
}

if (!function_exists('vms_ticketing_verification_get_user_state')) {
    /**
     * @return array{mode:string,request_id:int,program:string,submitted_at:string,review_notes:string,verified_programs:array<int,string>}
     */
    function vms_ticketing_verification_get_user_state(int $user_id): array
    {
        $user_id = absint($user_id);
        $state = array(
            'mode' => 'not_submitted',
            'request_id' => 0,
            'program' => '',
            'submitted_at' => '',
            'review_notes' => '',
            'verified_programs' => array(),
        );
        if ($user_id <= 0) {
            return $state;
        }

        $verified_programs = vms_ticketing_get_user_verified_programs($user_id);
        if (!empty($verified_programs)) {
            $state['mode'] = 'approved';
            $state['verified_programs'] = $verified_programs;
            return $state;
        }

        $pending_request = vms_ticketing_verification_get_latest_request($user_id, array('pending'));
        if ($pending_request instanceof WP_Post) {
            $state['mode'] = 'pending';
            $state['request_id'] = (int) $pending_request->ID;
            $state['program'] = sanitize_key((string) get_post_meta((int) $pending_request->ID, 'program', true));
            $state['submitted_at'] = (string) get_post_meta((int) $pending_request->ID, 'submitted_at', true);
            return $state;
        }

        $denied_request = vms_ticketing_verification_get_latest_request($user_id, array('denied'));
        if ($denied_request instanceof WP_Post) {
            $state['mode'] = 'denied';
            $state['request_id'] = (int) $denied_request->ID;
            $state['program'] = sanitize_key((string) get_post_meta((int) $denied_request->ID, 'program', true));
            $state['submitted_at'] = (string) get_post_meta((int) $denied_request->ID, 'submitted_at', true);
            $state['review_notes'] = (string) get_post_meta((int) $denied_request->ID, 'review_notes', true);
            return $state;
        }

        return $state;
    }
}

if (!function_exists('vms_ticketing_user_is_verified_for_program')) {
    function vms_ticketing_user_is_verified_for_program(int $user_id, string $program): bool
    {
        $user_id = absint($user_id);
        $program = sanitize_key($program);
        if ($user_id <= 0 || $program === '') {
            return false;
        }

        $verified = vms_ticketing_get_user_verified_programs($user_id);
        return in_array($program, $verified, true);
    }
}

if (!function_exists('vms_ticketing_verification_assign_program')) {
    function vms_ticketing_verification_assign_program(int $user_id, string $program, string $notes = '', int $reviewer_id = 0): bool
    {
        $user_id = absint($user_id);
        $program = sanitize_key($program);
        $reviewer_id = absint($reviewer_id);
        if ($user_id <= 0 || $program === '') {
            return false;
        }

        $programs = vms_ticketing_verification_programs();
        if (!isset($programs[$program])) {
            return false;
        }

        $user = new WP_User($user_id);
        if (!$user || !$user->exists()) {
            return false;
        }

        $role = vms_ticketing_verification_role_for_program($program);
        if ($role !== '' && !in_array($role, (array) $user->roles, true)) {
            $user->add_role($role);
        }

        $verified_programs = vms_ticketing_get_user_verified_programs($user_id);
        if (!in_array($program, $verified_programs, true)) {
            $verified_programs[] = $program;
            sort($verified_programs, SORT_STRING);
        }

        update_user_meta($user_id, 'vms_verified_programs', $verified_programs);
        update_user_meta($user_id, 'vms_verified_at_' . $program, current_time('mysql'));
        if ($reviewer_id > 0) {
            update_user_meta($user_id, 'vms_verified_by_' . $program, $reviewer_id);
        }

        $notes = trim(sanitize_text_field($notes));
        if ($notes !== '') {
            update_user_meta($user_id, 'vms_verified_notes_' . $program, $notes);
        }

        return true;
    }
}

if (!function_exists('vms_ticketing_verification_remove_program')) {
    function vms_ticketing_verification_remove_program(int $user_id, string $program): bool
    {
        $user_id = absint($user_id);
        $program = sanitize_key($program);
        if ($user_id <= 0 || $program === '') {
            return false;
        }

        $user = new WP_User($user_id);
        if (!$user || !$user->exists()) {
            return false;
        }

        $role = vms_ticketing_verification_role_for_program($program);
        if ($role !== '' && in_array($role, (array) $user->roles, true)) {
            $user->remove_role($role);
        }

        $meta_programs = get_user_meta($user_id, 'vms_verified_programs', true);
        $meta_programs = is_array($meta_programs) ? $meta_programs : array();
        $updated_programs = array();
        foreach ($meta_programs as $stored_program) {
            $stored_program = sanitize_key((string) $stored_program);
            if ($stored_program !== '' && $stored_program !== $program) {
                $updated_programs[$stored_program] = $stored_program;
            }
        }
        $updated_programs = array_values($updated_programs);
        sort($updated_programs, SORT_STRING);

        update_user_meta($user_id, 'vms_verified_programs', $updated_programs);
        delete_user_meta($user_id, 'vms_verified_at_' . $program);
        delete_user_meta($user_id, 'vms_verified_by_' . $program);
        delete_user_meta($user_id, 'vms_verified_notes_' . $program);

        return true;
    }
}



if (!function_exists('vms_ticketing_verification_decision_email_context')) {
    /**
     * Build a normalized email context for verification decision emails.
     *
     * @return array<string,mixed>
     */
    function vms_ticketing_verification_decision_email_context(
        int $user_id,
        int $request_id,
        string $decision,
        string $previous_status,
        string $program,
        string $review_notes = ''
    ): array {
        $user_id         = absint($user_id);
        $request_id      = absint($request_id);
        $decision        = sanitize_key($decision);
        $previous_status = sanitize_key($previous_status);
        $program         = sanitize_key($program);
        $review_notes    = trim(wp_strip_all_tags($review_notes));

        $user = $user_id > 0 ? get_userdata($user_id) : false;

        $email       = ($user && !empty($user->user_email)) ? sanitize_email((string) $user->user_email) : '';
        $first_name  = $user_id > 0 ? trim((string) get_user_meta($user_id, 'first_name', true)) : '';
        $display     = ($user && !empty($user->display_name)) ? (string) $user->display_name : '';
        $site_name   = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $account_url = function_exists('wc_get_page_permalink')
            ? (string) wc_get_page_permalink('myaccount')
            : (string) home_url('/my-account/');

        if (function_exists('vms_ticketing_verification_account_dashboard_url')) {
            $dashboard_url = (string) vms_ticketing_verification_account_dashboard_url();
            if ($dashboard_url !== '') {
                $account_url = $dashboard_url;
            }
        }

        return array(
            'user_id'         => $user_id,
            'request_id'      => $request_id,
            'decision'        => $decision,
            'previous_status' => $previous_status,
            'program'         => $program,
            'program_label'   => function_exists('vms_ticketing_verification_program_label')
                ? vms_ticketing_verification_program_label($program)
                : ucfirst(str_replace(array('-', '_'), ' ', $program)),
            'review_notes'    => $review_notes,
            'user_email'      => is_email($email) ? $email : '',
            'first_name'      => $first_name,
            'display_name'    => $display,
            'site_name'       => $site_name !== '' ? $site_name : __('Our site', 'backstage-venue-manager'),
            'account_url'     => $account_url,
        );
    }
}

if (!function_exists('vms_ticketing_verification_decision_email_subject')) {
    function vms_ticketing_verification_decision_email_subject(array $ctx): string
    {
        $site_name     = (string) ($ctx['site_name'] ?? __('Our site', 'backstage-venue-manager'));
        $program_label = (string) ($ctx['program_label'] ?? __('Verification', 'backstage-venue-manager'));
        $decision      = sanitize_key((string) ($ctx['decision'] ?? ''));
        $previous      = sanitize_key((string) ($ctx['previous_status'] ?? ''));

        if ($decision === 'approved') {
            return sprintf(
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                __('Approved: %1$s verification on %2$s', 'backstage-venue-manager'),
                $program_label,
                $site_name
            );
        }

        if ($decision === 'denied' && $previous === 'approved') {
            return sprintf(
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                __('Update: %1$s verification changed on %2$s', 'backstage-venue-manager'),
                $program_label,
                $site_name
            );
        }

        return sprintf(
            /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
            __('Update on your %1$s verification for %2$s', 'backstage-venue-manager'),
            $program_label,
            $site_name
        );
    }
}

if (!function_exists('vms_ticketing_verification_decision_email_body')) {
    function vms_ticketing_verification_decision_email_body(array $ctx): string
    {
        $decision      = sanitize_key((string) ($ctx['decision'] ?? ''));
        $previous      = sanitize_key((string) ($ctx['previous_status'] ?? ''));
        $program_label = (string) ($ctx['program_label'] ?? __('Verification', 'backstage-venue-manager'));
        $site_name     = (string) ($ctx['site_name'] ?? __('Our site', 'backstage-venue-manager'));
        $account_url   = esc_url_raw((string) ($ctx['account_url'] ?? ''));
        $review_notes  = trim((string) ($ctx['review_notes'] ?? ''));

        $name = trim((string) ($ctx['first_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($ctx['display_name'] ?? ''));
        }
        if ($name === '') {
            $name = __('there', 'backstage-venue-manager');
        }

        $lines   = array();
        /* translators: %s: human-readable value used in this message. */
        $lines[] = sprintf(__('Hi %s,', 'backstage-venue-manager'), $name);
        $lines[] = '';

        if ($decision === 'approved') {
            $lines[] = sprintf(
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                __('Your %1$s verification has been approved on %2$s.', 'backstage-venue-manager'),
                $program_label,
                $site_name
            );
            $lines[] = __('You can now access eligible discounted tickets when logged into your account.', 'backstage-venue-manager');
        } elseif ($decision === 'denied' && $previous === 'approved') {
            $lines[] = sprintf(
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                __('Your %1$s verification status has been updated and your access is no longer active on %2$s.', 'backstage-venue-manager'),
                $program_label,
                $site_name
            );
        } else {
            $lines[] = sprintf(
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                __('We reviewed your %1$s verification request for %2$s and it was not approved at this time.', 'backstage-venue-manager'),
                $program_label,
                $site_name
            );
        }

        if ($review_notes !== '') {
            $lines[] = '';
            $lines[] = __('Review note:', 'backstage-venue-manager');
            $lines[] = $review_notes;
        }

        if ($account_url !== '') {
            $lines[] = '';
            $lines[] = __('Account / verification page:', 'backstage-venue-manager');
            $lines[] = $account_url;
        }

        $lines[] = '';
        $lines[] = __('Thanks,', 'backstage-venue-manager');
        $lines[] = $site_name;

        return implode("\n", $lines);
    }
}

if (!function_exists('vms_ticketing_verification_send_decision_email')) {
    /**
     * Send approval/denial/revocation email and store delivery result on the request.
     */
    function vms_ticketing_verification_send_decision_email(
        int $request_id,
        int $user_id,
        string $decision,
        string $previous_status,
        string $program,
        string $review_notes = ''
    ): bool {
        $request_id = absint($request_id);
        $ctx = vms_ticketing_verification_decision_email_context(
            $user_id,
            $request_id,
            $decision,
            $previous_status,
            $program,
            $review_notes
        );

        $to = (string) ($ctx['user_email'] ?? '');
        if (!is_email($to) || $request_id <= 0) {
            if ($request_id > 0) {
                update_post_meta($request_id, 'decision_email_last_status', 'skipped_invalid_email');
                update_post_meta($request_id, 'decision_email_last_error', 'Missing or invalid recipient email.');
                update_post_meta($request_id, 'decision_email_last_sent_at', current_time('mysql'));
                update_post_meta($request_id, 'decision_email_last_to', $to);
            }
            return false;
        }

        $subject = vms_ticketing_verification_decision_email_subject($ctx);
        $body    = vms_ticketing_verification_decision_email_body($ctx);

        $subject = (string) apply_filters('vms_ticketing_verification_decision_email_subject', $subject, $ctx);
        $body    = (string) apply_filters('vms_ticketing_verification_decision_email_body', $body, $ctx);
        $enabled = (bool) apply_filters('vms_ticketing_verification_decision_email_enabled', true, $ctx);

        if (!$enabled) {
            update_post_meta($request_id, 'decision_email_last_status', 'skipped_disabled');
            update_post_meta($request_id, 'decision_email_last_error', '');
            update_post_meta($request_id, 'decision_email_last_sent_at', current_time('mysql'));
            update_post_meta($request_id, 'decision_email_last_to', $to);
            return false;
        }

        $sent = false;
        if (function_exists('wp_mail')) {
            $sent = (bool) wp_mail($to, $subject, $body);
        }

        update_post_meta($request_id, 'decision_email_last_status', $sent ? 'sent' : 'failed');
        update_post_meta($request_id, 'decision_email_last_error', $sent ? '' : 'wp_mail reported failure.');
        update_post_meta($request_id, 'decision_email_last_sent_at', current_time('mysql'));
        update_post_meta($request_id, 'decision_email_last_to', $to);

        return $sent;
    }
}

if (!function_exists('vms_ticketing_verification_upload_limit_label')) {
    function vms_ticketing_verification_upload_limit_label(): string
    {
        return vms_ticketing_verification_format_bytes(vms_ticketing_verification_get_effective_max_upload_bytes());
    }
}

if (!function_exists('vms_ticketing_verification_form_upload_label')) {
    function vms_ticketing_verification_form_upload_label(): string
    {
        return sprintf(
            /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
            __('Upload proof (%1$s, max %2$s)', 'backstage-venue-manager'),
            vms_ticketing_verification_allowed_formats_label(),
            vms_ticketing_verification_upload_limit_label()
        );
    }
}

if (!function_exists('vms_ticketing_verification_notice_message')) {
    function vms_ticketing_verification_notice_message(string $code): string
    {
        $limit_label = vms_ticketing_verification_upload_limit_label();

        switch (sanitize_key($code)) {
            case 'submitted':
                return __('Verification request submitted. We will review it soon.', 'backstage-venue-manager');
            case 'already_pending':
                return __('Your verification is already pending review.', 'backstage-venue-manager');
            case 'already_approved':
                return __('Your account is already verified for eligible discounted tickets.', 'backstage-venue-manager');
            case 'file_missing':
                return __('Please choose a proof file before submitting.', 'backstage-venue-manager');
            case 'file_too_large':
                /* translators: %s: human-readable value used in this message. */
                return sprintf(__('That image is too large. Upload a JPG, PNG, or WEBP up to %s.', 'backstage-venue-manager'), $limit_label);
            case 'pdf_too_large':
                /* translators: %s: human-readable value used in this message. */
                return sprintf(__('That PDF is too large. Upload a PDF up to %s, or upload a JPG/PNG screenshot instead.', 'backstage-venue-manager'), $limit_label);
            case 'file_type_not_allowed':
                return __('Unsupported file type. Upload JPG, PNG, WEBP, or PDF. If your phone saved HEIC/HEIF, take a screenshot or export it as JPG/PNG first.', 'backstage-venue-manager');
            case 'pdf_not_supported':
                return __('We could not read that PDF. Upload a standard PDF, or upload a JPG/PNG screenshot instead.', 'backstage-venue-manager');
            case 'heic_not_supported':
                return __('HEIC/HEIF photos are not supported here yet. Please take a screenshot or export the image as JPG/PNG first.', 'backstage-venue-manager');
            case 'dd214_blocked':
                return __('Do not upload DD214 documents. Please upload a valid photo ID instead.', 'backstage-venue-manager');
            case 'image_processing_failed':
                return __('We could not prepare that image. Try a clear screenshot or upload a JPG/PNG version instead.', 'backstage-venue-manager');
            case 'save_failed':
                return __('We could not save that upload. Please try again.', 'backstage-venue-manager');
            case 'bad_program':
                return __('Please select a verification program.', 'backstage-venue-manager');
            case 'login_required':
                return __('Please log in first to submit verification.', 'backstage-venue-manager');
            case 'confirm_required':
                return __('Please confirm you are eligible for this discount.', 'backstage-venue-manager');
            default:
                return '';
        }
    }
}

if (!function_exists('vms_ticketing_verification_form_url')) {
    function vms_ticketing_verification_form_url(int $tec_event_id = 0, string $program = ''): string
    {
        $tec_event_id = absint($tec_event_id);
        // IMPORTANT UX:
        // Do not inject a full verification form into the event page content.
        // Keep the event page clean and send users to a dedicated "My Account" area.
        // If an event ID is provided, include a return URL so the user can jump back.

        $dashboard_url = vms_ticketing_verification_account_dashboard_url();
        $url = add_query_arg('vms_verification', '1', $dashboard_url);

        $program = sanitize_key($program);
        if ($program !== '') {
            $url = add_query_arg('vms_verify_program', $program, $url);
        }

        if ($tec_event_id > 0) {
            $event_url = (string) get_permalink($tec_event_id);
            if ($event_url !== '') {
                $url = add_query_arg('vms_return_to', $event_url, $url);
            }
        }

        $url = preg_replace('/#.*$/', '', (string) $url);
        return (string) $url . '#vms-verification-panel';
    }
}

if (!function_exists('vms_ticketing_verification_event_has_verified_tickets')) {
    function vms_ticketing_verification_event_has_verified_tickets(int $tec_event_id): bool
    {
        $tec_event_id = absint($tec_event_id);
        if ($tec_event_id <= 0) {
            return false;
        }
        if (!function_exists('vms_ticketing_v2_find_plan_id_by_tec_event_id') || !function_exists('vms_ticketing_v2_get_config')) {
            return false;
        }

        $plan_id = vms_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id);
        if ($plan_id <= 0) {
            return false;
        }

        $cfg = vms_ticketing_v2_get_config($plan_id);
        $tickets = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? $cfg['tickets'] : array();
        foreach ($tickets as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            if (array_key_exists('enabled', $ticket) && empty($ticket['enabled'])) {
                continue;
            }
            $mode = sanitize_key((string) ($ticket['visibility_mode'] ?? 'public'));
            if ($mode === 'verified') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('vms_ticketing_verification_render_panel')) {
    function vms_ticketing_verification_render_panel(int $tec_event_id = 0): string
    {
        $tec_event_id = absint($tec_event_id);
        $programs = vms_ticketing_verification_programs();
        if (empty($programs)) {
            return '';
        }

        $notice_code = vms_request_read_key($_GET, 'vms_verification_notice');
        $notice_text = vms_ticketing_verification_notice_message($notice_code);
        $notice_class = in_array($notice_code, array('submitted'), true) ? 'vms-verify-notice--success' : 'vms-verify-notice--error';
        if ($notice_code === 'submitted') {
            $notice_class = 'vms-verify-notice--success';
        }

        $current_program = vms_request_read_key($_GET, 'vms_verify_program');
        if ($current_program !== '' && !isset($programs[$current_program])) {
            $current_program = '';
        }

        $return_to = vms_request_local_redirect('', $_GET['vms_return_to'] ?? null);

        $current_url = '';
        $request_uri = vms_request_current_uri('');
        if ($request_uri !== '') {
            $current_url = home_url($request_uri);
        }
        if ($current_url === '') {
            if ($tec_event_id > 0) {
                $current_url = (string) get_permalink($tec_event_id);
            }
            if ($current_url === '') {
                $current_url = home_url('/');
            }
        }
        $current_url = preg_replace('/#.*$/', '', (string) $current_url);
        $current_url = remove_query_arg(array('vms_verification_notice'), (string) $current_url);
        // Always redirect back to wherever the form is being shown.
        // (Typically the WooCommerce My Account dashboard, not the event page.)
        $redirect_args = array('vms_verification' => '1');
        if ($current_program !== '') {
            $redirect_args['vms_verify_program'] = $current_program;
        }
        if ($return_to !== '') {
            $redirect_args['vms_return_to'] = $return_to;
        }
        $redirect_to = (string) add_query_arg($redirect_args, vms_ticketing_verification_account_dashboard_url()) . '#vms-verification-panel';
        $form_action = admin_url('admin-post.php');
        $login_url = wp_login_url($redirect_to);

        $state = array(
            'mode' => 'not_submitted',
            'request_id' => 0,
            'program' => '',
            'submitted_at' => '',
            'review_notes' => '',
            'verified_programs' => array(),
        );
        if (is_user_logged_in()) {
            $state = vms_ticketing_verification_get_user_state((int) get_current_user_id());
            if ($current_program === '' && $state['program'] !== '' && isset($programs[$state['program']])) {
                $current_program = $state['program'];
            }
        }

        ob_start();
        ?>
        <section id="vms-verification-panel" class="vms-verification-panel" aria-label="<?php echo esc_attr__('Verification', 'backstage-venue-manager'); ?>">
            <h3><?php echo esc_html__('Get Verified', 'backstage-venue-manager'); ?></h3>
            <?php if ($notice_text !== '') : ?>
                <div class="vms-verify-notice <?php echo esc_attr($notice_class); ?>" role="status">
                    <?php echo esc_html($notice_text); ?>
                </div>
            <?php endif; ?>

            <?php if ($return_to !== '') : ?>
                <p class="vms-verify-copy">
                    <a class="button" href="<?php echo esc_url($return_to); ?>"><?php echo esc_html__('Back to event', 'backstage-venue-manager'); ?></a>
                </p>
            <?php endif; ?>

            <?php if (!is_user_logged_in()) : ?>
                <p class="vms-verify-copy">
                    <?php echo esc_html__('Please log in to submit verification.', 'backstage-venue-manager'); ?>
                </p>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url($login_url); ?>"><?php echo esc_html__('Log In', 'backstage-venue-manager'); ?></a>
                </p>
            <?php else : ?>
                <?php if ($state['mode'] === 'pending') : ?>
                    <div class="vms-verification-status vms-verification-status--pending">
                        <p class="vms-verify-copy"><strong><?php echo esc_html__('Verification pending review', 'backstage-venue-manager'); ?></strong></p>
                        <p class="vms-verify-copy"><?php echo esc_html__('We received your submission and will review it soon. You do not need to submit again unless we contact you for more information.', 'backstage-venue-manager'); ?></p>
                    </div>
                <?php elseif ($state['mode'] === 'approved') : ?>
                    <?php
                    $labels = array();
                    foreach ((array) $state['verified_programs'] as $verified_program) {
                        $labels[] = vms_ticketing_verification_program_label((string) $verified_program);
                    }
                    ?>
                    <div class="vms-verification-status vms-verification-status--approved">
                        <?php /* translators: %s: comma-separated approved verification groups. */ ?>
                        <p class="vms-verify-copy"><strong><?php echo esc_html(sprintf(__("You're verified for: %s", 'backstage-venue-manager'), implode(', ', $labels))); ?></strong></p>
                        <p class="vms-verify-copy"><?php echo esc_html__('Your account is approved for eligible discounted tickets.', 'backstage-venue-manager'); ?></p>
                    </div>
                <?php else : ?>
                    <?php if ($state['mode'] === 'denied') : ?>
                        <div class="vms-verification-status vms-verification-status--denied">
                            <p class="vms-verify-copy"><strong><?php echo esc_html__('Your previous submission could not be approved.', 'backstage-venue-manager'); ?></strong></p>
                            <p class="vms-verify-copy"><?php echo esc_html__('Please upload a new document and try again.', 'backstage-venue-manager'); ?></p>
                        </div>
                    <?php else : ?>
                        <p class="vms-verify-copy">
                            <?php echo esc_html__('Some ticket types require one-time verification. Submit a photo ID and we will review it.', 'backstage-venue-manager'); ?>
                        </p>
                    <?php endif; ?>
                    <p class="vms-verify-copy vms-verify-copy--warn">
                        <?php echo esc_html__('Do not upload DD214 documents.', 'backstage-venue-manager'); ?>
                    </p>

                    <form class="vms-verification-form" action="<?php echo esc_url($form_action); ?>" method="post" enctype="multipart/form-data" data-vms-photo-upload="1">
                        <?php wp_nonce_field('vms_submit_verification_request', 'vms_verification_nonce'); ?>
                        <input type="hidden" name="action" value="vms_submit_verification" />
                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
                        <input type="hidden" name="tec_event_id" value="<?php echo esc_attr((string) $tec_event_id); ?>" />

                        <label class="vms-verify-field">
                            <span><?php echo esc_html__('Program', 'backstage-venue-manager'); ?></span>
                            <select name="program" required>
                                <option value=""><?php echo esc_html__('Choose one', 'backstage-venue-manager'); ?></option>
                                <?php foreach ($programs as $program_key => $program_label) : ?>
                                    <option value="<?php echo esc_attr($program_key); ?>" <?php selected($current_program, $program_key); ?>><?php echo esc_html($program_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="vms-verify-field">
                            <span><?php echo esc_html(vms_ticketing_verification_form_upload_label()); ?></span>
                            <input type="file" name="proof_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required />
                        </label>

                        <p class="vms-verify-copy vms-verify-copy--mini">
                            <?php echo esc_html__('Image uploads are normalized to a readable JPG proof before review. PDFs stay as PDFs. If your phone saved HEIC/HEIF, take a screenshot or export it as JPG/PNG first.', 'backstage-venue-manager'); ?>
                        </p>

                        <label class="vms-verify-field">
                            <span><?php echo esc_html__('Notes (optional)', 'backstage-venue-manager'); ?></span>
                            <textarea name="notes" rows="3" maxlength="300" placeholder="<?php echo esc_attr__('Optional context for the reviewer', 'backstage-venue-manager'); ?>"></textarea>
                        </label>

                        <label class="vms-verify-field vms-verify-field--checkbox">
                            <input type="checkbox" name="eligibility_confirm" value="1" required />
                            <span><?php echo esc_html__('I confirm I am eligible for this discount.', 'backstage-venue-manager'); ?></span>
                        </label>

                        <p class="vms-verify-upload-status" data-vms-verify-upload-status hidden aria-live="polite"></p>
                        <p class="vms-verify-upload-debug" data-vms-verify-upload-debug hidden></p>

                        <p>
                            <button type="submit" class="button button-primary"><?php echo esc_html__('Submit Verification', 'backstage-venue-manager'); ?></button>
                        </p>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('vms_ticketing_verification_append_panel_to_event')) {
    function vms_ticketing_verification_append_panel_to_event(string $content): string
    {
        if (is_admin()) {
            return $content;
        }
        if (!is_singular('tribe_events')) {
            return $content;
        }
        if (function_exists('is_main_query') && !is_main_query()) {
            return $content;
        }
        if (function_exists('in_the_loop') && !in_the_loop()) {
            return $content;
        }
        if (function_exists('has_shortcode') && has_shortcode($content, 'vms_ticketing_verification_form')) {
            return $content;
        }

        $tec_event_id = (int) get_queried_object_id();
        if ($tec_event_id <= 0) {
            return $content;
        }
        if (!vms_ticketing_verification_event_has_verified_tickets($tec_event_id)) {
            return $content;
        }

        $panel = vms_ticketing_verification_render_panel($tec_event_id);
        if ($panel === '') {
            return $content;
        }

        return $content . $panel;
    }
}
// UX: Don't append the full verification form to the event description.
// Users access verification via the compact "Requires verification" link/button in ticket rows.
// If you ever want to show the full form somewhere, use the [vms_ticketing_verification_form] shortcode
// or the My Account dashboard entry.
// add_filter('the_content', 'vms_ticketing_verification_append_panel_to_event', 35);

if (!function_exists('vms_ticketing_verification_form_shortcode')) {
    function vms_ticketing_verification_form_shortcode($atts = array()): string
    {
        $a = shortcode_atts(array(
            'tec_event_id' => '0',
        ), (array) $atts, 'vms_ticketing_verification_form');
        $tec_event_id = absint($a['tec_event_id'] ?? 0);
        return vms_ticketing_verification_render_panel($tec_event_id);
    }
}
add_shortcode('vms_ticketing_verification_form', 'vms_ticketing_verification_form_shortcode');

if (!function_exists('vms_ticketing_verification_account_dashboard_url')) {
    function vms_ticketing_verification_account_dashboard_url(): string
    {
        if (function_exists('wc_get_account_endpoint_url')) {
            return (string) wc_get_account_endpoint_url('dashboard');
        }
        if (function_exists('wc_get_page_permalink')) {
            $url = wc_get_page_permalink('myaccount');
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }
        return home_url('/my-account/');
    }
}

if (!function_exists('vms_ticketing_verification_render_account_dashboard_entry')) {
    function vms_ticketing_verification_render_account_dashboard_entry(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $dashboard_url = vms_ticketing_verification_account_dashboard_url();
        $verification_url = add_query_arg('vms_verification', '1', $dashboard_url);
        $show_panel = isset($_GET['vms_verification']) && absint(wp_unslash($_GET['vms_verification'])) === 1;

        echo '<section class="vms-verification-account-entry">';
        echo '<h3>' . esc_html__('Verification Discounts', 'backstage-venue-manager') . '</h3>';
        echo '<p class="vms-verify-copy">' . esc_html__('Need access to verified ticket discounts? Submit your ID once and we will review it.', 'backstage-venue-manager') . '</p>';

        if ($show_panel) {
            echo vms_ticketing_verification_render_panel(0); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo '<p><a class="button" href="' . esc_url($verification_url . '#vms-verification-panel') . '">' . esc_html__('Open Verification Form', 'backstage-venue-manager') . '</a></p>';
        }

        echo '</section>';
    }
}
add_action('woocommerce_account_dashboard', 'vms_ticketing_verification_render_account_dashboard_entry', 25);

if (!function_exists('vms_ticketing_verification_enqueue_account_styles')) {
    function vms_ticketing_verification_enqueue_account_styles(): void
    {
        if (is_admin()) {
            return;
        }
        if (!function_exists('is_account_page') || !is_account_page()) {
            return;
        }

        $show_panel = isset($_GET['vms_verification']) && absint(wp_unslash($_GET['vms_verification'])) === 1;
        if (!$show_panel) {
            return;
        }

        $deps = array();
        if (function_exists('wp_style_is')) {
            foreach (array('kadence-tribe-css', 'sr-tec-custom-css-css') as $maybe_dep) {
                if (wp_style_is($maybe_dep, 'registered') || wp_style_is($maybe_dep, 'enqueued')) {
                    $deps[] = $maybe_dep;
                }
            }
        }

        wp_enqueue_style(
            'vms-ticketing-front',
            plugins_url('assets/css/vms-ticketing-front.css', VMS_PLUGIN_FILE),
            $deps,
            function_exists('vms_asset_version') ? vms_asset_version() : (defined('VMS_VERSION') ? (string) VMS_VERSION : '')
        );

        wp_enqueue_script(
            'vms-image-normalize',
            plugins_url('assets/js/vms-image-normalize.js', VMS_PLUGIN_FILE),
            array(),
            function_exists('vms_asset_version') ? vms_asset_version() : (defined('VMS_VERSION') ? (string) VMS_VERSION : ''),
            true
        );

        wp_enqueue_script(
            'vms-verification-upload',
            plugins_url('assets/js/vms-verification-upload.js', VMS_PLUGIN_FILE),
            array('vms-image-normalize'),
            function_exists('vms_asset_version') ? vms_asset_version() : (defined('VMS_VERSION') ? (string) VMS_VERSION : ''),
            true
        );

        $debug_mode = current_user_can('manage_options') && vms_request_read_bool_flag($_GET, 'vms_debug');
        wp_add_inline_script(
            'vms-verification-upload',
            'window.vmsVerificationUpload = ' . wp_json_encode(array(
                'debug' => $debug_mode ? 1 : 0,
                'maxUploadBytes' => vms_ticketing_verification_get_effective_max_upload_bytes(),
                'warnUploadBytes' => vms_ticketing_verification_get_warn_upload_bytes(),
                'maxDimension' => (int) VMS_TICKETING_VERIFICATION_IMAGE_MAX_DIMENSION,
                'quality' => max(0.6, min(0.92, ((int) VMS_TICKETING_VERIFICATION_IMAGE_QUALITY) / 100)),
                'maxUploadLabel' => vms_ticketing_verification_upload_limit_label(),
                'allowedFormatsLabel' => vms_ticketing_verification_allowed_formats_label(),
                'messages' => array(
                    'file_missing' => vms_ticketing_verification_notice_message('file_missing'),
                    'file_too_large' => vms_ticketing_verification_notice_message('file_too_large'),
                    'pdf_too_large' => vms_ticketing_verification_notice_message('pdf_too_large'),
                    'file_type_not_allowed' => vms_ticketing_verification_notice_message('file_type_not_allowed'),
                    'pdf_not_supported' => vms_ticketing_verification_notice_message('pdf_not_supported'),
                    'heic_not_supported' => vms_ticketing_verification_notice_message('heic_not_supported'),
                    'image_processing_failed' => vms_ticketing_verification_notice_message('image_processing_failed'),
                    'save_failed' => vms_ticketing_verification_notice_message('save_failed'),
                ),
            )) . ';',
            'before'
        );
    }
}
add_action('wp_enqueue_scripts', 'vms_ticketing_verification_enqueue_account_styles', 1000);

if (!function_exists('vms_ticketing_verification_submission_notification_recipients')) {
    /**
     * Resolve operator/admin recipients for new verification request emails.
     *
     * Defaults to the site admin email and can be overridden by filters.
     *
     * @param array<string,mixed> $context
     * @return array<int,string>
     */
    function vms_ticketing_verification_submission_notification_recipients(array $context = array()): array
    {
        $emails = array();

        $admin_email = sanitize_email((string) get_option('admin_email', ''));
        if (is_email($admin_email)) {
            $emails[] = $admin_email;
        }

        $emails = (array) apply_filters('vms_ticketing_verification_submission_notification_recipients', $emails, $context);

        $out = array();
        $seen = array();
        foreach ($emails as $email) {
            $email = sanitize_email((string) $email);
            if (!is_email($email)) {
                continue;
            }
            $key = strtolower($email);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $email;
        }

        return $out;
    }
}

if (!function_exists('vms_ticketing_verification_send_submission_notification')) {
    /**
     * Notify operator/admin recipients that a new verification request is pending.
     */
    function vms_ticketing_verification_send_submission_notification(int $request_id): bool
    {
        $request_id = absint($request_id);
        if ($request_id <= 0) {
            return false;
        }

        $request = get_post($request_id);
        if (!($request instanceof WP_Post) || !in_array((string) $request->post_type, vms_ticketing_verification_request_post_types(), true)) {
            return false;
        }

        $user_id = absint(get_post_meta($request_id, 'user_id', true));
        $program = sanitize_key((string) get_post_meta($request_id, 'program', true));
        $submitted_at = (string) get_post_meta($request_id, 'submitted_at', true);
        $submit_notes = (string) get_post_meta($request_id, 'submit_notes', true);
        $user = $user_id > 0 ? get_userdata($user_id) : null;

        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $program_label = vms_ticketing_verification_program_label($program);
        $approvals_url = admin_url('admin.php?page=vms-verifications&status=pending');
        $proof_url = wp_nonce_url(
            add_query_arg(array(
                'action' => 'vms_view_verification_proof',
                'request_id' => $request_id,
            ), admin_url('admin-post.php')),
            'vms_verification_proof_' . $request_id
        );

        /* translators: %d: user ID. */
        $user_label = sprintf(__('User #%d', 'backstage-venue-manager'), $user_id);
        $user_email = '';
        if ($user instanceof WP_User) {
            $user_label = $user->display_name !== '' ? (string) $user->display_name : $user_label;
            $user_email = sanitize_email((string) $user->user_email);
        }

        $context = array(
            'request_id' => $request_id,
            'user_id' => $user_id,
            'program' => $program,
            'program_label' => $program_label,
            'submitted_at' => $submitted_at,
            'submit_notes' => $submit_notes,
            'user_label' => $user_label,
            'user_email' => $user_email,
            'approvals_url' => $approvals_url,
            'proof_url' => $proof_url,
        );

        $recipients = vms_ticketing_verification_submission_notification_recipients($context);
        if (empty($recipients)) {
            update_post_meta($request_id, 'submission_email_last_status', 'skipped_no_recipient');
            update_post_meta($request_id, 'submission_email_last_error', 'No valid recipient email configured.');
            update_post_meta($request_id, 'submission_email_last_sent_at', current_time('mysql'));
            update_post_meta($request_id, 'submission_email_last_to', '');
            return false;
        }

        $subject = sprintf(
            /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
            __('[%1$s] New eligibility verification pending: %2$s', 'backstage-venue-manager'),
            $site_name !== '' ? $site_name : 'VMS',
            $program_label
        );
        $subject = (string) apply_filters('vms_ticketing_verification_submission_email_subject', $subject, $context);

        $lines = array(
            __('A new eligibility verification request is pending review.', 'backstage-venue-manager'),
            '',
            /* translators: %s: program. */
            sprintf(__('Program: %s', 'backstage-venue-manager'), $program_label),
            /* translators: %s: submitted timestamp. */
            sprintf(__('Submitted: %s', 'backstage-venue-manager'), $submitted_at !== '' ? $submitted_at : (string) $request->post_date),
            /* translators: %s: user. */
            sprintf(__('User: %s', 'backstage-venue-manager'), $user_label),
        );
        if ($user_email !== '') {
            /* translators: %s: email address. */
            $lines[] = sprintf(__('Email: %s', 'backstage-venue-manager'), $user_email);
        }
        if ($submit_notes !== '') {
            /* translators: %s: user note. */
            $lines[] = sprintf(__('User note: %s', 'backstage-venue-manager'), $submit_notes);
        }
        $lines[] = '';
        /* translators: %s: review queue. */
        $lines[] = sprintf(__('Review queue: %s', 'backstage-venue-manager'), $approvals_url);
        /* translators: %s: view proof. */
        $lines[] = sprintf(__('View proof: %s', 'backstage-venue-manager'), $proof_url);

        $body = implode("\n", $lines);
        $body = (string) apply_filters('vms_ticketing_verification_submission_email_body', $body, $context);

        $sent_count = 0;
        foreach ($recipients as $to) {
            if (function_exists('wp_mail') && wp_mail($to, $subject, $body)) {
                $sent_count++;
            }
        }

        update_post_meta($request_id, 'submission_email_last_status', ($sent_count > 0) ? 'sent' : 'failed');
        update_post_meta($request_id, 'submission_email_last_error', ($sent_count > 0) ? '' : 'wp_mail reported failure.');
        update_post_meta($request_id, 'submission_email_last_sent_at', current_time('mysql'));
        update_post_meta($request_id, 'submission_email_last_to', implode(', ', $recipients));

        /**
         * Fires after VMS sends a new verification submission notification email.
         *
         * @param int   $request_id
         * @param array $context
         * @param array $recipients
         * @param int   $sent_count
         */
        do_action('vms_ticketing_verification_submission_notification_sent', $request_id, $context, $recipients, $sent_count);

        return $sent_count > 0;
    }
}

if (!function_exists('vms_ticketing_verification_submission_notification_hook')) {
    function vms_ticketing_verification_submission_notification_hook(): string
    {
        return 'vms_ticketing_verification_send_submission_notification_async';
    }
}

if (!function_exists('vms_ticketing_verification_submission_notification_group')) {
    function vms_ticketing_verification_submission_notification_group(): string
    {
        return 'vms-ticketing-verification';
    }
}

if (!function_exists('vms_ticketing_verification_queue_submission_notification')) {
    function vms_ticketing_verification_queue_submission_notification(int $request_id): bool
    {
        $request_id = absint($request_id);
        if ($request_id <= 0) {
            return false;
        }

        $hook = vms_ticketing_verification_submission_notification_hook();
        $args = array($request_id);
        $group = vms_ticketing_verification_submission_notification_group();

        if (function_exists('as_has_scheduled_action') && function_exists('as_enqueue_async_action')) {
            if (as_has_scheduled_action($hook, $args, $group)) {
                return true;
            }

            return (int) as_enqueue_async_action($hook, $args, $group, true) > 0;
        }

        if (function_exists('wp_next_scheduled') && wp_next_scheduled($hook, $args)) {
            return true;
        }

        return (bool) wp_schedule_single_event(time() + 5, $hook, $args);
    }
}

if (!function_exists('vms_ticketing_verification_run_submission_notification')) {
    function vms_ticketing_verification_run_submission_notification(int $request_id = 0): void
    {
        $request_id = absint($request_id);
        if ($request_id <= 0) {
            return;
        }

        vms_ticketing_verification_send_submission_notification($request_id);
    }
}
add_action('vms_ticketing_verification_send_submission_notification_async', 'vms_ticketing_verification_run_submission_notification', 10, 1);

if (!function_exists('vms_ticketing_verification_create_request')) {
    function vms_ticketing_verification_create_request(int $user_id, string $program, $proof_file, string $proof_mime, string $notes = '', string $proof_storage_kind = 'private_file'): int
    {
        $user_id = absint($user_id);
        $program = sanitize_key($program);
        $proof_file_id = is_numeric($proof_file) ? absint($proof_file) : 0;
        $proof_path = $proof_file_id > 0 ? '' : trim((string) $proof_file);
        $proof_mime = sanitize_text_field($proof_mime);
        $notes = trim(sanitize_text_field($notes));
        $proof_storage_kind = sanitize_key($proof_storage_kind);
        if ($user_id <= 0 || $program === '' || ($proof_file_id <= 0 && $proof_path === '')) {
            return 0;
        }

        $label = vms_ticketing_verification_program_label($program);
        $request_id = wp_insert_post(array(
            'post_type'   => vms_ticketing_verification_request_post_type(),
            'post_status' => 'pending',
            'post_author' => $user_id,
            'post_title'  => sprintf('%s verification request — user #%d', $label, $user_id),
        ), true);

        if (is_wp_error($request_id) || !$request_id) {
            return 0;
        }

        $request_id = (int) $request_id;
        update_post_meta($request_id, 'user_id', $user_id);
        update_post_meta($request_id, 'program', $program);
        if ($proof_file_id > 0) {
            update_post_meta($request_id, 'proof_file_id', $proof_file_id);
            update_post_meta($request_id, 'proof_storage_kind', $proof_storage_kind !== '' ? $proof_storage_kind : 'private_file');
            delete_post_meta($request_id, 'proof_file_path');
        } else {
            update_post_meta($request_id, 'proof_file_path', $proof_path);
            delete_post_meta($request_id, 'proof_file_id');
            delete_post_meta($request_id, 'proof_storage_kind');
        }
        update_post_meta($request_id, 'proof_mime', $proof_mime);
        update_post_meta($request_id, 'submitted_at', current_time('mysql'));
        if ($notes !== '') {
            update_post_meta($request_id, 'submit_notes', $notes);
        }

        $queued = vms_ticketing_verification_queue_submission_notification($request_id);
        update_post_meta($request_id, 'submission_email_last_status', $queued ? 'queued' : 'queue_failed');
        update_post_meta($request_id, 'submission_email_last_error', $queued ? '' : 'Could not queue submission notification.');
        update_post_meta($request_id, 'submission_email_last_sent_at', current_time('mysql'));
        update_post_meta($request_id, 'submission_email_last_to', '');

        return $request_id;
    }
}

if (!function_exists('vms_ticketing_verification_notice_redirect_url')) {
    function vms_ticketing_verification_notice_redirect_url(string $redirect_to, string $notice): string
    {
        $redirect_to = vms_request_local_redirect(home_url('/'), $redirect_to);

        return add_query_arg('vms_verification_notice', sanitize_key($notice), $redirect_to);
    }
}

if (!function_exists('vms_ticketing_verification_wants_json_response')) {
    function vms_ticketing_verification_wants_json_response(): bool
    {
        $mode = vms_request_read_key($_POST, 'response_mode');
        if ($mode === 'json') {
            return true;
        }

        $accept = strtolower(vms_request_server_value('HTTP_ACCEPT'));
        return $accept !== '' && strpos($accept, 'application/json') !== false;
    }
}

if (!function_exists('vms_ticketing_verification_finish_success')) {
    function vms_ticketing_verification_finish_success(string $redirect_to, string $notice, array $data = array()): void
    {
        if (vms_ticketing_verification_wants_json_response()) {
            wp_send_json(array(
                'ok' => true,
                'data' => array_merge(array(
                    'notice' => sanitize_key($notice),
                    'message' => vms_ticketing_verification_notice_message($notice),
                    'redirect' => vms_ticketing_verification_notice_redirect_url($redirect_to, $notice),
                ), $data),
                'error' => null,
            ));
        }

        vms_ticketing_verification_redirect_with_notice($redirect_to, $notice);
    }
}

if (!function_exists('vms_ticketing_verification_finish_error')) {
    function vms_ticketing_verification_finish_error(string $redirect_to, string $notice, int $status = 400, array $data = array()): void
    {
        if (vms_ticketing_verification_wants_json_response()) {
            wp_send_json(array(
                'ok' => false,
                'data' => $data,
                'error' => array(
                    'code' => sanitize_key($notice),
                    'message' => vms_ticketing_verification_notice_message($notice),
                ),
            ), $status);
        }

        vms_ticketing_verification_redirect_with_notice($redirect_to, $notice);
    }
}

if (!function_exists('vms_ticketing_verification_redirect_with_notice')) {
    function vms_ticketing_verification_redirect_with_notice(string $redirect_to, string $notice): void
    {
        wp_safe_redirect(vms_ticketing_verification_notice_redirect_url($redirect_to, $notice));
        exit;
    }
}

if (!function_exists('vms_ticketing_verification_handle_submit')) {
    function vms_ticketing_verification_handle_submit(): void
    {
        if (!is_user_logged_in()) {
            $redirect_to = vms_request_local_redirect(home_url('/'), $_POST['redirect_to'] ?? null);
            vms_ticketing_verification_finish_error($redirect_to, 'login_required', 401);
        }

        $nonce = (isset($_POST['vms_verification_nonce']) && !is_array($_POST['vms_verification_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_POST['vms_verification_nonce']))
            : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_submit_verification_request')) {
            wp_die(esc_html__('Invalid verification request.', 'backstage-venue-manager'));
        }

        $redirect_to = vms_request_local_redirect(home_url('/'), $_POST['redirect_to'] ?? null);
        $user_state = vms_ticketing_verification_get_user_state((int) get_current_user_id());
        if ($user_state['mode'] === 'pending') {
            vms_ticketing_verification_finish_error($redirect_to, 'already_pending', 409);
        }
        if ($user_state['mode'] === 'approved') {
            vms_ticketing_verification_finish_error($redirect_to, 'already_approved', 409);
        }

        $program = vms_request_read_key($_POST, 'program');
        $notes = vms_request_read_text_field($_POST, 'notes');
        $eligibility_confirm = vms_request_read_absint($_POST, 'eligibility_confirm');

        $programs = vms_ticketing_verification_programs();
        if ($program === '' || !isset($programs[$program])) {
            vms_ticketing_verification_finish_error($redirect_to, 'bad_program', 400);
        }
        if ($eligibility_confirm !== 1) {
            vms_ticketing_verification_finish_error($redirect_to, 'confirm_required', 400);
        }

        if (empty($_FILES['proof_file']) || !is_array($_FILES['proof_file'])) {
            vms_ticketing_verification_finish_error($redirect_to, 'file_missing', 400);
        }

        $upload = vms_upload_read_file($_FILES, 'proof_file');
        if (is_wp_error($upload)) {
            vms_ticketing_verification_finish_error($redirect_to, 'file_missing', 400);
        }

        $name = isset($upload['name']) ? (string) $upload['name'] : '';
        $reported_mime = isset($upload['type']) ? sanitize_text_field((string) $upload['type']) : '';
        $error = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            $notice = vms_ticketing_verification_upload_error_notice_code($error, $name, $reported_mime);
            $status = in_array($notice, array('file_too_large', 'pdf_too_large'), true) ? 413 : 400;
            vms_ticketing_verification_finish_error($redirect_to, $notice, $status);
        }

        if (preg_match('/dd\s*214/i', $name)) {
            vms_ticketing_verification_finish_error($redirect_to, 'dd214_blocked', 400);
        }

        $kind_hint = vms_ticketing_verification_guess_upload_kind($name, $reported_mime);
        if ($kind_hint === 'heic') {
            vms_ticketing_verification_finish_error($redirect_to, 'heic_not_supported', 415);
        }

        $validated = vms_validate_uploaded_file(
            $upload,
            array(
                'allowed_mimes' => vms_ticketing_verification_allowed_mimes(),
                'max_bytes' => vms_ticketing_verification_get_effective_max_upload_bytes(),
                'type_message' => $kind_hint === 'pdf'
                    ? vms_ticketing_verification_notice_message('pdf_not_supported')
                    : vms_ticketing_verification_notice_message('file_type_not_allowed'),
                'empty_message' => vms_ticketing_verification_notice_message('file_missing'),
                'too_large_message' => $kind_hint === 'pdf'
                    ? vms_ticketing_verification_notice_message('pdf_too_large')
                    : vms_ticketing_verification_notice_message('file_too_large'),
                'tmp_invalid_message' => vms_ticketing_verification_notice_message('file_missing'),
            )
        );
        if (is_wp_error($validated)) {
            $code = (string) $validated->get_error_code();
            if ($code === 'upload_type_not_allowed') {
                if ($kind_hint === 'pdf') {
                    vms_ticketing_verification_finish_error($redirect_to, 'pdf_not_supported', 415);
                }
                vms_ticketing_verification_finish_error($redirect_to, 'file_type_not_allowed', 415);
            }
            if ($code === 'upload_too_large') {
                vms_ticketing_verification_finish_error($redirect_to, $kind_hint === 'pdf' ? 'pdf_too_large' : 'file_too_large', 413);
            }
            if ($code === 'upload_missing' || $code === 'upload_tmp_missing' || $code === 'upload_tmp_invalid' || $code === 'upload_empty') {
                vms_ticketing_verification_finish_error($redirect_to, 'file_missing', 400);
            }
            vms_ticketing_verification_finish_error($redirect_to, 'save_failed', 400);
        }

        $stored = vms_ticketing_verification_store_proof_file($validated);
        if (is_wp_error($stored)) {
            $code = (string) $stored->get_error_code();
            $status = $code === 'file_too_large' ? 413 : 500;
            vms_ticketing_verification_finish_error(
                $redirect_to,
                $code !== '' ? $code : 'save_failed',
                $status
            );
        }

        $user_id = get_current_user_id();
        $request_id = vms_ticketing_verification_create_request(
            $user_id,
            $program,
            (int) ($stored['file_id'] ?? 0),
            (string) ($stored['mime'] ?? ''),
            $notes,
            'private_file'
        );
        if ($request_id <= 0) {
            if (!empty($stored['file_id']) && function_exists('vms_private_files_delete')) {
                vms_private_files_delete((int) $stored['file_id']);
            }
            vms_ticketing_verification_finish_error($redirect_to, 'save_failed', 500);
        }

        vms_ticketing_verification_finish_success($redirect_to, 'submitted');
    }
}
add_action('admin_post_vms_submit_verification', 'vms_ticketing_verification_handle_submit');

if (!function_exists('vms_ticketing_verification_register_menu')) {
    function vms_ticketing_verification_register_menu(): void
    {
        if (!is_admin()) {
            return;
        }
        add_submenu_page(
            'vms-dashboard',
            __('Eligibility Approvals', 'backstage-venue-manager'),
            __('Eligibility Approvals', 'backstage-venue-manager'),
            vms_ticketing_verification_manage_capability(),
            'vms-verifications',
            'vms_ticketing_verification_render_admin_page'
        );
    }
}
add_action('admin_menu', 'vms_ticketing_verification_register_menu', 25);

if (!function_exists('vms_ticketing_verification_handle_save_programs')) {
    function vms_ticketing_verification_handle_save_programs(): void
    {
        if (!vms_ticketing_verification_current_user_can_manage()) {
            wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
        }

        if (
            !isset($_POST['vms_verification_programs_nonce'])
            || is_array($_POST['vms_verification_programs_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['vms_verification_programs_nonce'])), 'vms_save_verification_programs')
        ) {
            wp_die(esc_html__('Invalid verification program settings request.', 'backstage-venue-manager'));
        }

        $current = vms_ticketing_verification_programs();
        $updated = array();

        $existing_labels = isset($_POST['vms_verification_program_labels_existing'])
            ? wp_unslash($_POST['vms_verification_program_labels_existing'])
            : array();
        $existing_labels = is_array($existing_labels) ? $existing_labels : array();

        foreach ($current as $program_key => $program_label) {
            $program_key = sanitize_key((string) $program_key);
            if ($program_key === '') {
                continue;
            }

            $label = array_key_exists($program_key, $existing_labels)
                ? trim(sanitize_text_field((string) $existing_labels[$program_key]))
                : trim(sanitize_text_field((string) $program_label));
            if ($label === '') {
                $label = trim(sanitize_text_field((string) $program_label));
            }
            if ($label === '') {
                $label = ucwords(str_replace('_', ' ', $program_key));
            }

            $updated[$program_key] = $label;
        }

        $new_labels = isset($_POST['vms_verification_program_new_labels'])
            ? wp_unslash($_POST['vms_verification_program_new_labels'])
            : array();
        $new_labels = is_array($new_labels) ? $new_labels : array();

        foreach ($new_labels as $raw_label) {
            $label = trim(sanitize_text_field((string) $raw_label));
            if ($label === '') {
                continue;
            }

            $candidate_key = sanitize_key(str_replace('-', '_', sanitize_title($label)));
            if ($candidate_key === '') {
                continue;
            }

            $program_key = $candidate_key;
            $suffix = 2;
            while (isset($updated[$program_key])) {
                if (strcasecmp((string) $updated[$program_key], $label) === 0) {
                    $program_key = '';
                    break;
                }
                $program_key = $candidate_key . '_' . $suffix;
                $suffix++;
            }

            if ($program_key === '') {
                continue;
            }

            $updated[$program_key] = $label;
        }

        $updated = vms_ticketing_verification_sanitize_program_map($updated);
        $ok = update_option(vms_ticketing_verification_program_labels_option_key(), $updated, false);
        if (!$ok) {
            $saved = get_option(vms_ticketing_verification_program_labels_option_key(), array());
            $saved = vms_ticketing_verification_sanitize_program_map($saved);
            $ok = ($saved === $updated);
        }

        $notice = $ok ? 'programs_saved' : 'programs_failed';
        $redirect = add_query_arg(
            array(
                'page' => 'vms-verifications',
                'vms_notice' => $notice,
            ),
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }
}
add_action('admin_post_vms_save_verification_programs', 'vms_ticketing_verification_handle_save_programs');

if (!function_exists('vms_ticketing_verification_handle_save_allowances')) {
    function vms_ticketing_verification_handle_save_allowances(): void
    {
        if (!vms_ticketing_verification_current_user_can_manage()) {
            wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
        }

        if (
            !isset($_POST['vms_verification_allowances_nonce'])
            || is_array($_POST['vms_verification_allowances_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['vms_verification_allowances_nonce'])), 'vms_save_verification_allowances')
        ) {
            wp_die(esc_html__('Invalid allowance settings request.', 'backstage-venue-manager'));
        }

        $raw = isset($_POST['vms_verification_program_allowances']) ? wp_unslash($_POST['vms_verification_program_allowances']) : array();
        $allowances = vms_ticketing_verification_sanitize_allowances($raw);
        $ok = update_option(vms_ticketing_verification_allowances_option_key(), $allowances, false);
        if (!$ok) {
            $saved = get_option(vms_ticketing_verification_allowances_option_key(), array());
            $ok = (vms_ticketing_verification_sanitize_allowances($saved) === $allowances);
        }

        $notice = $ok ? 'allowances_saved' : 'allowances_failed';
        $redirect = add_query_arg(
            array(
                'page' => 'vms-verifications',
                'vms_notice' => $notice,
            ),
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }
}
add_action('admin_post_vms_save_verification_allowances', 'vms_ticketing_verification_handle_save_allowances');

if (!function_exists('vms_ticketing_verification_handle_save_upload_settings')) {
    function vms_ticketing_verification_handle_save_upload_settings(): void
    {
        if (!vms_ticketing_verification_current_user_can_manage()) {
            wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
        }

        if (
            !isset($_POST['vms_verification_upload_settings_nonce'])
            || is_array($_POST['vms_verification_upload_settings_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['vms_verification_upload_settings_nonce'])), 'vms_save_verification_upload_settings')
        ) {
            wp_die(esc_html__('Invalid upload settings request.', 'backstage-venue-manager'));
        }

        $raw = isset($_POST['vms_verification_upload_settings']) ? wp_unslash($_POST['vms_verification_upload_settings']) : array();
        $settings = vms_ticketing_verification_sanitize_upload_settings($raw);
        $ok = update_option(vms_ticketing_verification_upload_settings_option_key(), $settings, false);
        if (!$ok) {
            $saved = get_option(vms_ticketing_verification_upload_settings_option_key(), array());
            $ok = (vms_ticketing_verification_sanitize_upload_settings($saved) === $settings);
        }

        $notice = $ok ? 'upload_settings_saved' : 'upload_settings_failed';
        $redirect = add_query_arg(
            array(
                'page' => 'vms-verifications',
                'vms_notice' => $notice,
            ),
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }
}
add_action('admin_post_vms_save_verification_upload_settings', 'vms_ticketing_verification_handle_save_upload_settings');

if (!function_exists('vms_ticketing_verification_render_user_credential_fields')) {
    function vms_ticketing_verification_render_user_credential_fields(WP_User $user): void
    {
        $user_id = absint((int) $user->ID);
        if ($user_id <= 0 || !current_user_can('edit_user', $user_id) || !vms_ticketing_verification_current_user_can_manage()) {
            return;
        }

        $programs = vms_ticketing_verification_programs();
        if (empty($programs)) {
            return;
        }

        $verified_programs = vms_ticketing_get_user_verified_programs($user_id);
        $verified_lookup = array_fill_keys(array_map('sanitize_key', $verified_programs), true);
        ?>
        <h2><?php echo esc_html__('VMS Verified Ticket Credentials', 'backstage-venue-manager'); ?></h2>
        <p class="description"><?php echo esc_html__('Manually approve or revoke verified ticket eligibility for this user. This is useful for customer support, corrections, and testing the public verification flow.', 'backstage-venue-manager'); ?></p>
        <input type="hidden" name="vms_verified_programs_profile_present" value="1" />
        <table class="form-table" role="presentation">
            <tbody>
                <?php foreach ($programs as $program_key => $program_label) : ?>
                    <?php
                    $program_key = sanitize_key((string) $program_key);
                    if ($program_key === '') {
                        continue;
                    }
                    $is_verified = !empty($verified_lookup[$program_key]);
                    $verified_at = (string) get_user_meta($user_id, 'vms_verified_at_' . $program_key, true);
                    $verified_by = absint(get_user_meta($user_id, 'vms_verified_by_' . $program_key, true));
                    $verified_by_user = $verified_by > 0 ? get_userdata($verified_by) : false;
                    $notes = (string) get_user_meta($user_id, 'vms_verified_notes_' . $program_key, true);
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($program_label); ?></th>
                        <td>
                            <label for="<?php echo esc_attr('vms-user-credential-' . $program_key); ?>">
                                <input
                                    id="<?php echo esc_attr('vms-user-credential-' . $program_key); ?>"
                                    type="checkbox"
                                    name="<?php echo esc_attr('vms_verified_programs_profile[' . $program_key . ']'); ?>"
                                    value="1"
                                    <?php checked($is_verified); ?>
                                />
                                <?php echo esc_html__('Approved for verified ticket access', 'backstage-venue-manager'); ?>
                            </label>
                            <p class="description">
                                <?php if ($is_verified) : ?>
                                    <?php
                                    $details = array(__('Currently approved.', 'backstage-venue-manager'));
                                    if ($verified_at !== '') {
                                        /* translators: %s: human-readable value used in this message. */
                                        $details[] = sprintf(__('Approved at %s.', 'backstage-venue-manager'), $verified_at);
                                    }
                                    if ($verified_by_user instanceof WP_User) {
                                        /* translators: %s: human-readable value used in this message. */
                                        $details[] = sprintf(__('Approved by %s.', 'backstage-venue-manager'), $verified_by_user->display_name);
                                    }
                                    if ($notes !== '') {
                                        /* translators: %s: note. */
                                        $details[] = sprintf(__('Note: %s', 'backstage-venue-manager'), $notes);
                                    }
                                    echo esc_html(implode(' ', $details));
                                    ?>
                                <?php else : ?>
                                    <?php echo esc_html__('Not currently approved.', 'backstage-venue-manager'); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th scope="row"><label for="vms-user-credential-review-note"><?php echo esc_html__('Credential change note', 'backstage-venue-manager'); ?></label></th>
                    <td>
                        <input id="vms-user-credential-review-note" type="text" class="regular-text" name="vms_verified_programs_profile_note" value="" placeholder="<?php echo esc_attr__('Optional internal note for changes saved now', 'backstage-venue-manager'); ?>" />
                        <p class="description"><?php echo esc_html__('Saved only when a credential status changes. Manual profile changes do not upload proof files or send customer emails.', 'backstage-venue-manager'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
}
add_action('show_user_profile', 'vms_ticketing_verification_render_user_credential_fields');
add_action('edit_user_profile', 'vms_ticketing_verification_render_user_credential_fields');

if (!function_exists('vms_ticketing_verification_save_user_credential_fields')) {
    function vms_ticketing_verification_save_user_credential_fields(int $user_id): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !current_user_can('edit_user', $user_id) || !vms_ticketing_verification_current_user_can_manage()) {
            return;
        }
        if (!isset($_POST['vms_verified_programs_profile_present'])) {
            return;
        }

        $programs = vms_ticketing_verification_programs();
        if (empty($programs)) {
            return;
        }

        $raw_selected = isset($_POST['vms_verified_programs_profile']) && is_array($_POST['vms_verified_programs_profile'])
            ? wp_unslash($_POST['vms_verified_programs_profile'])
            : array();
        $note = isset($_POST['vms_verified_programs_profile_note'])
            ? trim(sanitize_text_field((string) wp_unslash($_POST['vms_verified_programs_profile_note'])))
            : '';
        if ($note === '') {
            $note = __('Manual user profile update.', 'backstage-venue-manager');
        }

        foreach ($programs as $program_key => $_program_label) {
            $program_key = sanitize_key((string) $program_key);
            if ($program_key === '') {
                continue;
            }

            $should_be_verified = !empty($raw_selected[$program_key]);
            $is_verified = vms_ticketing_user_is_verified_for_program($user_id, $program_key);
            if ($should_be_verified === $is_verified) {
                continue;
            }

            if ($should_be_verified) {
                $changed = vms_ticketing_verification_assign_program($user_id, $program_key, $note, get_current_user_id());
                $action = 'approved';
            } else {
                $changed = vms_ticketing_verification_remove_program($user_id, $program_key);
                $action = 'revoked';
            }

            if ($changed) {
                update_user_meta($user_id, 'vms_verified_profile_reviewed_at_' . $program_key, current_time('mysql'));
                update_user_meta($user_id, 'vms_verified_profile_reviewed_by_' . $program_key, get_current_user_id());
                update_user_meta($user_id, 'vms_verified_profile_action_' . $program_key, $action);
                update_user_meta($user_id, 'vms_verified_profile_notes_' . $program_key, $note);
            }
        }
    }
}
add_action('personal_options_update', 'vms_ticketing_verification_save_user_credential_fields');
add_action('edit_user_profile_update', 'vms_ticketing_verification_save_user_credential_fields');

if (!function_exists('vms_ticketing_verification_render_user_allowance_fields')) {
    function vms_ticketing_verification_render_user_allowance_fields(WP_User $user): void
    {
        if (!current_user_can('edit_user', (int) $user->ID)) {
            return;
        }

        $programs = vms_ticketing_verification_programs();
        if (empty($programs)) {
            return;
        }
        ?>
        <h2><?php echo esc_html__('VMS Verified Allowances', 'backstage-venue-manager'); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <?php foreach ($programs as $program_key => $program_label) : ?>
                    <?php
                    $program_key = sanitize_key((string) $program_key);
                    if ($program_key === '') {
                        continue;
                    }
                    $meta_key = 'vms_verified_allowance_' . $program_key;
                    $raw_override = get_user_meta((int) $user->ID, $meta_key, true);
                    $override = ($raw_override === '' || $raw_override === null) ? '' : (string) max(0, absint($raw_override));
                    $default_value = vms_ticketing_verification_get_program_default_allowance($program_key);
                    ?>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr('vms-user-allowance-' . $program_key); ?>"><?php echo esc_html($program_label); ?></label></th>
                        <td>
                            <input
                                id="<?php echo esc_attr('vms-user-allowance-' . $program_key); ?>"
                                type="number"
                                min="0"
                                step="1"
                                class="small-text"
                                name="<?php echo esc_attr('vms_verified_allowance[' . $program_key . ']'); ?>"
                                value="<?php echo esc_attr($override); ?>"
                                placeholder="<?php echo esc_attr((string) $default_value); ?>"
                            />
                            <?php /* translators: %d: default per-user allowance value. */ ?>
                            <p class="description"><?php echo esc_html(sprintf(__('Leave blank to use default (%d).', 'backstage-venue-manager'), $default_value)); ?></p>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
add_action('show_user_profile', 'vms_ticketing_verification_render_user_allowance_fields');
add_action('edit_user_profile', 'vms_ticketing_verification_render_user_allowance_fields');

if (!function_exists('vms_ticketing_verification_save_user_allowance_fields')) {
    function vms_ticketing_verification_save_user_allowance_fields(int $user_id): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !current_user_can('edit_user', $user_id)) {
            return;
        }
        if (!isset($_POST['vms_verified_allowance']) || !is_array($_POST['vms_verified_allowance'])) {
            return;
        }

        $programs = vms_ticketing_verification_programs();
        $raw = wp_unslash($_POST['vms_verified_allowance']);
        foreach ($programs as $program_key => $_program_label) {
            $program_key = sanitize_key((string) $program_key);
            if ($program_key === '') {
                continue;
            }

            $meta_key = 'vms_verified_allowance_' . $program_key;
            $value = isset($raw[$program_key]) ? trim((string) $raw[$program_key]) : '';
            if ($value === '') {
                delete_user_meta($user_id, $meta_key);
                continue;
            }

            if (!is_numeric($value)) {
                continue;
            }
            update_user_meta($user_id, $meta_key, max(0, absint($value)));
        }
    }
}
add_action('personal_options_update', 'vms_ticketing_verification_save_user_allowance_fields');
add_action('edit_user_profile_update', 'vms_ticketing_verification_save_user_allowance_fields');

if (!function_exists('vms_ticketing_verification_row_status_label')) {
    function vms_ticketing_verification_row_status_label(string $status): string
    {
        $status = sanitize_key($status);
        if ($status === 'approved') {
            return __('Approved', 'backstage-venue-manager');
        }
        if ($status === 'denied') {
            return __('Denied', 'backstage-venue-manager');
        }
        return __('Pending', 'backstage-venue-manager');
    }
}

if (!function_exists('vms_ticketing_verification_render_admin_page')) {
    function vms_ticketing_verification_render_admin_page(): void
    {
        if (!vms_ticketing_verification_current_user_can_manage()) {
            wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
        }

        $status_filter = vms_request_read_key($_GET, 'status');
        if ($status_filter === '') {
            $status_filter = 'pending';
        }
        if (!in_array($status_filter, array('all', 'pending', 'approved', 'denied'), true)) {
            $status_filter = 'pending';
        }

        $program_filter = vms_request_read_key($_GET, 'program');
        if ($program_filter === '') {
            $program_filter = 'all';
        }
        $programs = vms_ticketing_verification_programs();
        if ($program_filter !== 'all' && !isset($programs[$program_filter])) {
            $program_filter = 'all';
        }

        $search = vms_request_read_text_field($_GET, 's');
        $search = trim($search);
        $order = vms_request_read_key($_GET, 'order');
        if ($order === '') {
            $order = 'desc';
        }
        if (!in_array($order, array('asc', 'desc'), true)) {
            $order = 'desc';
        }

        $query_status = ($status_filter === 'all')
            ? array('pending', 'approved', 'denied')
            : array($status_filter);

        $query_args = array(
            'post_type'      => vms_ticketing_verification_request_post_types(),
            'post_status'    => $query_status,
            'posts_per_page' => 250,
            'orderby'        => 'date',
            'order'          => strtoupper($order),
            'no_found_rows'  => true,
        );
        if ($program_filter !== 'all') {
            $query_args['meta_query'] = array(
                array(
                    'key' => 'program',
                    'value' => $program_filter,
                    'compare' => '=',
                ),
            );
        }

        $requests = get_posts($query_args);
        if ($search !== '') {
            $filtered = array();
            foreach ((array) $requests as $request) {
                if (!($request instanceof WP_Post)) {
                    continue;
                }

                $request_id = (int) $request->ID;
                $user_id = absint(get_post_meta($request_id, 'user_id', true));
                $program = sanitize_key((string) get_post_meta($request_id, 'program', true));
                $user = ($user_id > 0) ? get_userdata($user_id) : null;

                $haystack = array(
                    (string) $request->post_title,
                    (string) $request->post_excerpt,
                    (string) $request->post_content,
                    (string) $program,
                    vms_ticketing_verification_program_label($program),
                );
                if ($user instanceof WP_User) {
                    $haystack[] = (string) $user->display_name;
                    $haystack[] = (string) $user->user_email;
                    $haystack[] = (string) $user->user_login;
                }

                $matches = false;
                foreach ($haystack as $value) {
                    if ($value === '') {
                        continue;
                    }
                    if (stripos($value, $search) !== false) {
                        $matches = true;
                        break;
                    }
                }

                if ($matches) {
                    $filtered[] = $request;
                }
            }
            $requests = $filtered;
        }

        $shared_args = array(
            'page' => 'vms-verifications',
        );
        if ($program_filter !== 'all') {
            $shared_args['program'] = $program_filter;
        }
        if ($search !== '') {
            $shared_args['s'] = $search;
        }
        if ($order !== 'desc') {
            $shared_args['order'] = $order;
        }

        $base_url = add_query_arg($shared_args, admin_url('admin.php'));
        $notice = isset($_GET['vms_notice']) ? sanitize_key((string) wp_unslash($_GET['vms_notice'])) : '';
        $allowances = vms_ticketing_verification_get_program_allowances();
        $upload_settings = vms_ticketing_verification_get_upload_settings();
        $configured_upload_bytes = vms_ticketing_verification_get_configured_max_upload_bytes();
        $effective_upload_bytes = vms_ticketing_verification_get_effective_max_upload_bytes();
        $server_upload_bytes = function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : $effective_upload_bytes;
        $results_count = count((array) $requests);
        $help_button = '<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.approvals.credentials" data-vms-tour="approvals.credentials.help">' . esc_html__('Start Guided Tour', 'backstage-venue-manager') . '</button>';
        if (function_exists('vms_approvals_queue_render_help_button')) {
            $help_button = vms_approvals_queue_render_help_button(
                'vms.approvals.credentials',
                'approvals.credentials.help',
                __('Start Guided Tour', 'backstage-venue-manager')
            );
        } elseif (function_exists('vms_render_help_button')) {
            $help_button = vms_render_help_button(
                array(
                    'tour_id' => 'vms.approvals.credentials',
                    'anchor' => 'approvals.credentials.help',
                    'label' => __('Start Guided Tour', 'backstage-venue-manager'),
                    'class' => 'button-secondary',
                )
            );
        }
        ?>
        <div class="wrap" data-vms-tour="approvals.credentials.root">
            <h1><?php echo esc_html__('Eligibility Approvals', 'backstage-venue-manager'); ?></h1>
            <p class="description"><?php echo esc_html__('Review credential access requests for special ticket programs. Proof files are deleted when a request is approved or denied.', 'backstage-venue-manager'); ?></p>
            <p data-vms-tour="approvals.credentials.help"><?php echo $help_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

            <?php if ($notice === 'decision_saved') : ?>
                <div class="notice notice-success"><p><?php echo esc_html__('Verification decision saved.', 'backstage-venue-manager'); ?></p></div>
            <?php elseif ($notice === 'decision_failed') : ?>
                <div class="notice notice-error"><p><?php echo esc_html__('Could not save verification decision.', 'backstage-venue-manager'); ?></p></div>
            <?php elseif ($notice === 'programs_saved') : ?>
                <div class="notice notice-success"><p><?php echo esc_html__('Verified ticket program labels saved.', 'backstage-venue-manager'); ?></p></div>
            <?php elseif ($notice === 'programs_failed') : ?>
                <div class="notice notice-error"><p><?php echo esc_html__('Could not save verified ticket program labels.', 'backstage-venue-manager'); ?></p></div>
            <?php elseif ($notice === 'allowances_saved') : ?>
                <div class="notice notice-success"><p><?php echo esc_html__('Allowance defaults saved.', 'backstage-venue-manager'); ?></p></div>
            <?php elseif ($notice === 'allowances_failed') : ?>
                <div class="notice notice-error"><p><?php echo esc_html__('Could not save allowance defaults.', 'backstage-venue-manager'); ?></p></div>
            <?php elseif ($notice === 'upload_settings_saved') : ?>
                <div class="notice notice-success"><p><?php echo esc_html__('Verification upload settings saved.', 'backstage-venue-manager'); ?></p></div>
            <?php elseif ($notice === 'upload_settings_failed') : ?>
                <div class="notice notice-error"><p><?php echo esc_html__('Could not save verification upload settings.', 'backstage-venue-manager'); ?></p></div>
            <?php endif; ?>

            <h2><?php echo esc_html__('Verified Ticket Programs', 'backstage-venue-manager'); ?></h2>
            <p class="description"><?php echo esc_html__('Rename the customer-facing verified groups here and add new ones without code. These labels feed the approval queue and the Event Plan ticket "Verified group" dropdown.', 'backstage-venue-manager'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 10px 0 16px; max-width: 780px;">
                <?php wp_nonce_field('vms_save_verification_programs', 'vms_verification_programs_nonce'); ?>
                <input type="hidden" name="action" value="vms_save_verification_programs" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <?php foreach ($programs as $program_key => $program_label) : ?>
                            <?php
                            $program_key = sanitize_key((string) $program_key);
                            if ($program_key === '') {
                                continue;
                            }
                            ?>
                            <tr>
                                <th scope="row"><label for="<?php echo esc_attr('vms-program-label-' . $program_key); ?>"><?php echo esc_html__('Program label', 'backstage-venue-manager'); ?></label></th>
                                <td>
                                    <input
                                        id="<?php echo esc_attr('vms-program-label-' . $program_key); ?>"
                                        type="text"
                                        class="regular-text"
                                        name="<?php echo esc_attr('vms_verification_program_labels_existing[' . $program_key . ']'); ?>"
                                        value="<?php echo esc_attr((string) $program_label); ?>"
                                    />
                                    <?php /* translators: %s: internal verification program key. */ ?>
                                    <p class="description"><?php echo esc_html(sprintf(__('Internal key: %s', 'backstage-venue-manager'), $program_key)); ?></p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php for ($new_row = 0; $new_row < 3; $new_row++) : ?>
                            <tr>
                                <th scope="row"><label for="<?php echo esc_attr('vms-program-new-' . $new_row); ?>"><?php echo esc_html__('Add new verified group', 'backstage-venue-manager'); ?></label></th>
                                <td>
                                    <input
                                        id="<?php echo esc_attr('vms-program-new-' . $new_row); ?>"
                                        type="text"
                                        class="regular-text"
                                        name="vms_verification_program_new_labels[]"
                                        value=""
                                        placeholder="<?php echo esc_attr__('Example: Active Military', 'backstage-venue-manager'); ?>"
                                    />
                                    <p class="description"><?php echo esc_html__('Leave blank to skip. VMS will create the internal key automatically.', 'backstage-venue-manager'); ?></p>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                <p>
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Program Labels', 'backstage-venue-manager'); ?></button>
                </p>
            </form>

            <h2><?php echo esc_html__('Verified Ticket Allowance Defaults', 'backstage-venue-manager'); ?></h2>
            <p class="description"><?php echo esc_html__('Set how many verified tickets each customer can buy per event, by program. User-specific overrides can be set on each user profile.', 'backstage-venue-manager'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 10px 0 16px; max-width: 680px;">
                <?php wp_nonce_field('vms_save_verification_allowances', 'vms_verification_allowances_nonce'); ?>
                <input type="hidden" name="action" value="vms_save_verification_allowances" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <?php foreach ($programs as $program_key => $program_label) : ?>
                            <?php
                            $program_key = sanitize_key((string) $program_key);
                            if ($program_key === '') {
                                continue;
                            }
                            $value = max(0, absint($allowances[$program_key] ?? 2));
                            ?>
                            <tr>
                                <th scope="row"><label for="<?php echo esc_attr('vms-allowance-' . $program_key); ?>"><?php echo esc_html($program_label); ?></label></th>
                                <td>
                                    <input
                                        id="<?php echo esc_attr('vms-allowance-' . $program_key); ?>"
                                        type="number"
                                        min="0"
                                        step="1"
                                        name="<?php echo esc_attr('vms_verification_program_allowances[' . $program_key . ']'); ?>"
                                        value="<?php echo esc_attr((string) $value); ?>"
                                        class="small-text"
                                    />
                                    <p class="description"><?php echo esc_html__('Per verified customer, per event.', 'backstage-venue-manager'); ?></p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Allowance Defaults', 'backstage-venue-manager'); ?></button>
                </p>
            </form>

            <h2><?php echo esc_html__('Verification Upload Settings', 'backstage-venue-manager'); ?></h2>
            <p class="description"><?php echo esc_html__('Verification images are normalized into readable JPG proofs at review time. PDFs bypass image normalization and stay as PDFs.', 'backstage-venue-manager'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 10px 0 16px; max-width: 680px;">
                <?php wp_nonce_field('vms_save_verification_upload_settings', 'vms_verification_upload_settings_nonce'); ?>
                <input type="hidden" name="action" value="vms_save_verification_upload_settings" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="vms-verification-max-upload-mb"><?php echo esc_html__('Original upload limit', 'backstage-venue-manager'); ?></label></th>
                            <td>
                                <input
                                    id="vms-verification-max-upload-mb"
                                    type="number"
                                    min="1"
                                    max="50"
                                    step="1"
                                    name="vms_verification_upload_settings[max_upload_mb]"
                                    value="<?php echo esc_attr((string) max(1, absint($upload_settings['max_upload_mb'] ?? 20))); ?>"
                                    class="small-text"
                                />
                                <span><?php echo esc_html__('MB', 'backstage-venue-manager'); ?></span>
                                <p class="description"><?php echo esc_html__('Applies to the original file the customer selects before any browser-side normalization.', 'backstage-venue-manager'); ?></p>
                                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                                <p class="description"><?php echo esc_html(sprintf(__('Configured limit: %1$s. Effective limit on this server: %2$s.', 'backstage-venue-manager'), vms_ticketing_verification_format_bytes($configured_upload_bytes), vms_ticketing_verification_format_bytes($effective_upload_bytes))); ?></p>
                                <?php if ($server_upload_bytes > 0 && $server_upload_bytes < $configured_upload_bytes) : ?>
                                    <?php /* translators: %s: formatted upload size limit. */ ?>
                                    <p class="description"><?php echo esc_html(sprintf(__('The server is currently capping uploads at %s, so larger values here will not take effect until PHP/WordPress upload limits are raised.', 'backstage-venue-manager'), vms_ticketing_verification_format_bytes($server_upload_bytes))); ?></p>
                                <?php endif; ?>
                                <?php /* translators: 1: maximum image long-edge size in pixels, 2: JPEG quality setting. */ ?>
                                <p class="description"><?php echo esc_html(sprintf(__('Image normalization target: long edge %1$d px, JPEG quality %2$d. PDFs stay separate.', 'backstage-venue-manager'), (int) VMS_TICKETING_VERIFICATION_IMAGE_MAX_DIMENSION, (int) VMS_TICKETING_VERIFICATION_IMAGE_QUALITY)); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p>
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Upload Settings', 'backstage-venue-manager'); ?></button>
                </p>
            </form>

            <p data-vms-tour="approvals.credentials.status">
                <a class="button <?php echo ($status_filter === 'pending') ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg('status', 'pending', $base_url)); ?>"><?php echo esc_html__('Pending', 'backstage-venue-manager'); ?></a>
                <a class="button <?php echo ($status_filter === 'approved') ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg('status', 'approved', $base_url)); ?>"><?php echo esc_html__('Approved', 'backstage-venue-manager'); ?></a>
                <a class="button <?php echo ($status_filter === 'denied') ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg('status', 'denied', $base_url)); ?>"><?php echo esc_html__('Denied', 'backstage-venue-manager'); ?></a>
                <a class="button <?php echo ($status_filter === 'all') ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg('status', 'all', $base_url)); ?>"><?php echo esc_html__('All', 'backstage-venue-manager'); ?></a>
            </p>

            <form method="get" class="vms-verifications-toolbar" data-vms-tour="approvals.credentials.filters">
                <input type="hidden" name="page" value="vms-verifications" />
                <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>" />
                <label for="vms-verifications-program"><strong><?php echo esc_html__('Program', 'backstage-venue-manager'); ?></strong></label>
                <select id="vms-verifications-program" name="program">
                    <option value="all"><?php echo esc_html__('All programs', 'backstage-venue-manager'); ?></option>
                    <?php foreach ($programs as $program_key => $program_label) : ?>
                        <?php $program_key = sanitize_key((string) $program_key); ?>
                        <option value="<?php echo esc_attr($program_key); ?>" <?php selected($program_filter, $program_key); ?>><?php echo esc_html($program_label); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="vms-verifications-order"><strong><?php echo esc_html__('Sort', 'backstage-venue-manager'); ?></strong></label>
                <select id="vms-verifications-order" name="order">
                    <option value="desc" <?php selected($order, 'desc'); ?>><?php echo esc_html__('Newest first', 'backstage-venue-manager'); ?></option>
                    <option value="asc" <?php selected($order, 'asc'); ?>><?php echo esc_html__('Oldest first', 'backstage-venue-manager'); ?></option>
                </select>

                <label for="vms-verifications-search" class="screen-reader-text"><?php echo esc_html__('Search submissions', 'backstage-venue-manager'); ?></label>
                <input id="vms-verifications-search" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Search by user, email, or program', 'backstage-venue-manager'); ?>" />
                <button type="submit" class="button"><?php echo esc_html__('Apply Filters', 'backstage-venue-manager'); ?></button>
                <a class="button button-link" href="<?php echo esc_url(admin_url('admin.php?page=vms-verifications&status=' . rawurlencode($status_filter))); ?>"><?php echo esc_html__('Reset', 'backstage-venue-manager'); ?></a>
            </form>

            <?php /* translators: %d: number of verification requests shown in the table. */ ?>
            <p class="description"><?php echo esc_html(sprintf(_n('%d request shown.', '%d requests shown.', $results_count, 'backstage-venue-manager'), $results_count)); ?></p>

            <table class="widefat striped" data-vms-tour="approvals.credentials.table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Submitted', 'backstage-venue-manager'); ?></th>
                        <th><?php echo esc_html__('User', 'backstage-venue-manager'); ?></th>
                        <th><?php echo esc_html__('Program', 'backstage-venue-manager'); ?></th>
                        <th><?php echo esc_html__('Status', 'backstage-venue-manager'); ?></th>
                        <th><?php echo esc_html__('Actions', 'backstage-venue-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)) : ?>
                        <tr><td colspan="5"><?php echo esc_html__('No verification requests found.', 'backstage-venue-manager'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($requests as $request) : ?>
                            <?php
                            $request_id = (int) $request->ID;
                            $user_id = absint(get_post_meta($request_id, 'user_id', true));
                            $program = sanitize_key((string) get_post_meta($request_id, 'program', true));
                            $submitted_at = (string) get_post_meta($request_id, 'submitted_at', true);
                            $status = (string) get_post_status($request_id);
                            if (!in_array($status, array('pending', 'approved', 'denied'), true)) {
                                $status = 'pending';
                            }
                            $submit_notes = (string) get_post_meta($request_id, 'submit_notes', true);
                            $user = $user_id > 0 ? get_userdata($user_id) : null;
                            $proof_payload = vms_ticketing_verification_proof_payload($request_id);
                            $proof_exists = !is_wp_error($proof_payload);
                            $decision_nonce = wp_create_nonce('vms_verification_decision_' . $request_id);
                            $proof_nonce = wp_create_nonce('vms_verification_proof_' . $request_id);
                            ?>
                            <tr>
                                <td><?php echo esc_html($submitted_at !== '' ? $submitted_at : (string) $request->post_date); ?></td>
                                <td>
                                    <?php if ($user instanceof WP_User) : ?>
                                        <strong><?php echo esc_html($user->display_name); ?></strong><br />
                                        <span class="description"><?php echo esc_html($user->user_email); ?></span>
                                    <?php else : ?>
                                        <?php /* translators: %d: WordPress user ID. */ ?>
                                        <span><?php echo esc_html(sprintf(__('User #%d', 'backstage-venue-manager'), $user_id)); ?></span>
                                    <?php endif; ?>
                                    <?php if ($submit_notes !== '') : ?>
                                        <br /><span class="description"><?php echo esc_html__('User note:', 'backstage-venue-manager'); ?> <?php echo esc_html($submit_notes); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(vms_ticketing_verification_program_label($program)); ?></td>
                                <td><?php echo esc_html(vms_ticketing_verification_row_status_label($status)); ?></td>
                                <td data-vms-tour="approvals.credentials.actions">
                                    <?php if ($proof_exists) : ?>
                                        <a class="button button-small" href="<?php echo esc_url(add_query_arg(array(
                                            'action' => 'vms_view_verification_proof',
                                            'request_id' => $request_id,
                                            '_wpnonce' => $proof_nonce,
                                        ), admin_url('admin-post.php'))); ?>" target="_blank" rel="noopener"><?php echo esc_html__('View Proof', 'backstage-venue-manager'); ?></a>
                                    <?php else : ?>
                                        <span class="description"><?php echo esc_html__('Proof removed', 'backstage-venue-manager'); ?></span>
                                    <?php endif; ?>

                                    <?php if ($status === 'pending') : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                            <input type="hidden" name="action" value="vms_verification_decision" />
                                            <input type="hidden" name="request_id" value="<?php echo esc_attr((string) $request_id); ?>" />
                                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($decision_nonce); ?>" />
                                            <input type="text" name="review_notes" value="" placeholder="<?php echo esc_attr__('Optional note', 'backstage-venue-manager'); ?>" />
                                            <button type="submit" class="button button-primary button-small" name="decision" value="approved"><?php echo esc_html__('Approve', 'backstage-venue-manager'); ?></button>
                                            <button type="submit" class="button button-small" name="decision" value="denied"><?php echo esc_html__('Deny', 'backstage-venue-manager'); ?></button>
                                        </form>
                                    <?php elseif ($status === 'approved') : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                            <input type="hidden" name="action" value="vms_verification_decision" />
                                            <input type="hidden" name="request_id" value="<?php echo esc_attr((string) $request_id); ?>" />
                                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($decision_nonce); ?>" />
                                            <input type="text" name="review_notes" value="" placeholder="<?php echo esc_attr__('Revoke note (optional)', 'backstage-venue-manager'); ?>" />
                                            <button type="submit" class="button button-small" name="decision" value="denied" onclick="return confirm(<?php echo esc_attr(wp_json_encode(__('Revoke this approval for the account?', 'backstage-venue-manager'))); ?>);"><?php echo esc_html__('Revoke Approval', 'backstage-venue-manager'); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

if (!function_exists('vms_ticketing_verification_handle_decision')) {
    function vms_ticketing_verification_handle_decision(): void
    {
        if (!vms_ticketing_verification_current_user_can_manage()) {
            wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
        }

        $request_id = vms_request_read_absint($_POST, 'request_id');
        $decision = vms_request_read_key($_POST, 'decision');
        $review_notes = vms_request_read_text_field($_POST, 'review_notes');

        if ($request_id <= 0 || !in_array($decision, array('approved', 'denied'), true)) {
            wp_die(esc_html__('Invalid verification decision.', 'backstage-venue-manager'));
        }

        $nonce = (isset($_POST['_wpnonce']) && !is_array($_POST['_wpnonce']))
            ? sanitize_text_field(wp_unslash((string) $_POST['_wpnonce']))
            : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_verification_decision_' . $request_id)) {
            wp_die(esc_html__('Invalid verification decision nonce.', 'backstage-venue-manager'));
        }

        $request = get_post($request_id);
        if (!($request instanceof WP_Post) || !in_array((string) $request->post_type, vms_ticketing_verification_request_post_types(), true)) {
            wp_die(esc_html__('Verification request not found.', 'backstage-venue-manager'));
        }

        $user_id = absint(get_post_meta($request_id, 'user_id', true));
        $program = sanitize_key((string) get_post_meta($request_id, 'program', true));
        $from_status = sanitize_key((string) $request->post_status);
        if (!in_array($from_status, array('pending', 'approved', 'denied'), true)) {
            $from_status = 'pending';
        }

        $ok = true;
        if ($decision === 'approved') {
            $ok = vms_ticketing_verification_assign_program($user_id, $program, $review_notes, get_current_user_id());
        } elseif ($decision === 'denied' && $from_status === 'approved') {
            $ok = function_exists('vms_ticketing_verification_remove_program')
                ? vms_ticketing_verification_remove_program($user_id, $program)
                : false;
        }

        if (!$ok) {
            $redirect = add_query_arg(array(
                'page' => 'vms-verifications',
                'status' => 'pending',
                'vms_notice' => 'decision_failed',
            ), admin_url('admin.php'));
            wp_safe_redirect($redirect);
            exit;
        }

        $post_update = wp_update_post(array(
            'ID'          => $request_id,
            'post_status' => $decision,
        ), true);
        if (is_wp_error($post_update) || !$post_update) {
            $ok = false;
        }

        update_post_meta($request_id, 'reviewed_at', current_time('mysql'));
        update_post_meta($request_id, 'reviewed_by', get_current_user_id());
        if ($review_notes !== '') {
            update_post_meta($request_id, 'review_notes', $review_notes);
        }

        vms_ticketing_verification_delete_proof_asset_for_request($request_id);

        if (function_exists('vms_approvals_queue_record_transition')) {
            vms_approvals_queue_record_transition(
                'credential_access',
                (int) $request_id,
                $from_status,
                $decision,
                array('note' => $review_notes)
            );
        }

        if ($ok) {
            vms_ticketing_verification_send_decision_email(
                (int) $request_id,
                (int) $user_id,
                (string) $decision,
                (string) $from_status,
                (string) $program,
                (string) $review_notes
            );
        }

        $notice = $ok ? 'decision_saved' : 'decision_failed';
        $redirect = add_query_arg(array(
            'page' => 'vms-verifications',
            'status' => 'pending',
            'vms_notice' => $notice,
        ), admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }
}
add_action('admin_post_vms_verification_decision', 'vms_ticketing_verification_handle_decision');

if (!function_exists('vms_ticketing_verification_stream_proof')) {
    function vms_ticketing_verification_stream_proof(): void
    {
        if (!vms_ticketing_verification_current_user_can_manage()) {
            wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
        }

        $request_id = vms_request_read_absint($_GET, 'request_id');
        if ($request_id <= 0) {
            wp_die(esc_html__('Verification proof not found.', 'backstage-venue-manager'));
        }

        $nonce = (isset($_GET['_wpnonce']) && !is_array($_GET['_wpnonce']))
            ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce']))
            : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_verification_proof_' . $request_id)) {
            wp_die(esc_html__('Invalid proof request nonce.', 'backstage-venue-manager'));
        }

        $request = get_post($request_id);
        if (!($request instanceof WP_Post) || !in_array((string) $request->post_type, vms_ticketing_verification_request_post_types(), true)) {
            wp_die(esc_html__('Verification request not found.', 'backstage-venue-manager'));
        }

        $payload = vms_ticketing_verification_proof_payload($request_id);
        if (is_wp_error($payload)) {
            wp_die(esc_html($payload->get_error_message()));
        }

        vms_private_files_stream_path(
            (string) ($payload['path'] ?? ''),
            (string) ($payload['filename'] ?? 'verification-proof'),
            (string) ($payload['mime'] ?? 'application/octet-stream')
        );
    }
}
add_action('admin_post_vms_view_verification_proof', 'vms_ticketing_verification_stream_proof');

if (!function_exists('vms_ticketing_verification_cleanup_old_proofs')) {
    function vms_ticketing_verification_cleanup_old_proofs(): void
    {
        $retention_days = (int) apply_filters('vms_ticketing_verification_retention_days', (int) VMS_VERIFICATION_PROOF_TTL_DAYS);
        if ($retention_days < 1) {
            $retention_days = 1;
        }
        $cutoff_ts = time() - ($retention_days * DAY_IN_SECONDS);

        $request_ids = get_posts(array(
            'post_type'      => vms_ticketing_verification_request_post_types(),
            'post_status'    => array('pending', 'approved', 'denied'),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        foreach ((array) $request_ids as $request_id_raw) {
            $request_id = absint($request_id_raw);
            if ($request_id <= 0) {
                continue;
            }

            $payload = vms_ticketing_verification_proof_payload($request_id);
            if (is_wp_error($payload)) {
                vms_ticketing_verification_delete_proof_asset_for_request($request_id);
                continue;
            }

            $path = (string) ($payload['path'] ?? '');
            $status = (string) get_post_status($request_id);
            $delete_now = in_array($status, array('approved', 'denied'), true);

            if (!$delete_now && file_exists($path)) {
                $mtime = @filemtime($path);
                if ($mtime !== false && $mtime < $cutoff_ts) {
                    $delete_now = true;
                }
            } elseif (!$delete_now && !file_exists($path)) {
                $delete_now = true;
            }

            if ($delete_now) {
                vms_ticketing_verification_delete_proof_asset_for_request($request_id);
            }
        }
    }
}
add_action('vms_ticketing_verification_cleanup', 'vms_ticketing_verification_cleanup_old_proofs');

if (!function_exists('vms_ticketing_verification_schedule_cleanup')) {
    function vms_ticketing_verification_schedule_cleanup(): void
    {
        if (function_exists('vms_should_run_runtime_maintenance') && !vms_should_run_runtime_maintenance()) {
            return;
        }
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }
        if (wp_next_scheduled('vms_ticketing_verification_cleanup')) {
            return;
        }
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'vms_ticketing_verification_cleanup');
    }
}
add_action('init', 'vms_ticketing_verification_schedule_cleanup', 40);
