<?php

/**
 * VMS ADMIN — Vendor list UI refinements (UI-only)
 * - Reduce column clutter
 * - Consolidate tax/W-9 related fields into a single "Tax" column
 * - Add lightweight icons + tooltips for scanability
 * - Add admin filters for Tax Profile + W-9 status (read-only)
 *
 * IMPORTANT:
 * - Presentation only. Do not change storage, meta keys, or business logic.
 * - Uses registry-only meta access (vms_meta_key) where available.
 */

// Vendor “Type” (Band/Food Truck/etc.) = taxonomy vms_vendor_type
// Tax “Entity Type” (LLC/etc.) = meta _vms_entity_type

if (!defined('ABSPATH')) {
    exit;
}

add_filter('manage_edit-vms_vendor_columns', 'vms_admin_vendor_columns_refined', 50);
add_action('manage_vms_vendor_posts_custom_column', 'vms_admin_vendor_column_render_refined', 50, 2);

// Admin list filters (read-only)
add_action('restrict_manage_posts', 'vms_admin_vendor_list_filters_render', 50);
add_action('pre_get_posts', 'vms_admin_vendor_list_filters_apply', 50);

/**
 * Convert meta values to a display-safe scalar.
 * - If we cannot derive a scalar, return '' and log (no silent failures).
 */
function vms_admin_vendor_list_get_meta_scalar(int $post_id, string $meta_key): string
{
    if ($meta_key === '') {
        return '';
    }

    $v = get_post_meta($post_id, $meta_key, true);

    if ($v === null || $v === false) {
        return '';
    }

    if (is_string($v) || is_int($v) || is_float($v)) {
        return trim((string) $v);
    }

    // If a plugin/theme accidentally stored an array/object, do NOT render garbage.
    if (is_array($v) || is_object($v)) {
        error_log('[VMS] vendor-list-ui: non-scalar meta for key ' . $meta_key . ' on post_id ' . $post_id);
        return '';
    }

    return trim((string) $v);
}

/**
 * Read provider mode from vms_settings (mirrors vendor-tax-profile.php behavior).
 */
function vms_admin_tax_settings_get_provider(): string
{
    $settings = get_option('vms_settings', array());
    $settings = is_array($settings) ? $settings : array();

    $provider = isset($settings['tax_w9_provider']) ? (string) $settings['tax_w9_provider'] : '';
    if (!in_array($provider, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
        $provider = 'upload';
    }
    return $provider;
}

function vms_admin_vendor_list_query_arg(string $key): string
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only vendor list filters only affect admin list display state.
    if (!isset($_GET[$key])) {
        return '';
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only vendor list filters are unslashed here and allowlisted by the caller.
    return (string) wp_unslash($_GET[$key]);
}

function vms_admin_vendor_list_allowed_filter(string $key, array $allowed): string
{
    $value = sanitize_key(vms_admin_vendor_list_query_arg($key));
    return in_array($value, $allowed, true) ? $value : '';
}

/**
 * Returns true if W-9 requirement is satisfied for the configured provider mode.
 * This does not imply the full tax profile is complete.
 */
function vms_admin_vendor_w9_is_satisfied(int $vendor_id): bool
{
    $provider = vms_admin_tax_settings_get_provider();

    $k_upload = vms_meta_key('vendor', 'w9_upload_id');
    $k_recv   = vms_meta_key('vendor', 'w9_received_date');
    $k_attest = vms_meta_key('vendor', 'w9_attested_at');

    $upload_id = (int) vms_admin_vendor_list_get_meta_scalar($vendor_id, (string) $k_upload);
    $recv_date = vms_admin_vendor_list_get_meta_scalar($vendor_id, (string) $k_recv);
    $attest_at = (int) vms_admin_vendor_list_get_meta_scalar($vendor_id, (string) $k_attest);

    if ($provider === 'upload') {
        return ($upload_id > 0) || ($recv_date !== '');
    }

    return ($attest_at > 0);
}

/**
 * Returns a list of missing tax-profile fields for tooltip display.
 * Note: VMS intentionally does not store SSN/EIN.
 */
function vms_admin_vendor_tax_missing_fields(int $vendor_id): array
{
    $missing = array();

    $k_legal  = vms_meta_key('vendor', 'payee_legal_name');
    $k_entity = vms_meta_key('vendor', 'entity_type');

    $k_addr1 = vms_meta_key('vendor', 'addr1');
    $k_city  = vms_meta_key('vendor', 'city');
    $k_state = vms_meta_key('vendor', 'state');
    $k_zip   = vms_meta_key('vendor', 'zip');

    $legal  = vms_admin_vendor_list_get_meta_scalar($vendor_id, (string) $k_legal);
    $entity = vms_admin_vendor_list_get_meta_scalar($vendor_id, (string) $k_entity);

    $addr1 = vms_admin_vendor_list_get_meta_scalar($vendor_id, (string) $k_addr1);
    $city  = vms_admin_vendor_list_get_meta_scalar($vendor_id, (string) $k_city);
    $state = vms_admin_vendor_list_get_meta_scalar($vendor_id, (string) $k_state);
    $zip   = vms_admin_vendor_list_get_meta_scalar($vendor_id, (string) $k_zip);

    if ($legal === '')  $missing[] = 'legal name';
    if ($entity === '') $missing[] = 'entity type';

    if ($addr1 === '') $missing[] = 'address';
    if ($city === '')  $missing[] = 'city';
    if ($state === '') $missing[] = 'state';
    if ($zip === '')   $missing[] = 'zip';

    if (!vms_admin_vendor_w9_is_satisfied($vendor_id)) {
        $missing[] = 'W-9';
    }

    return $missing;
}

/**
 * Column layout: keep it tight.
 */
function vms_admin_vendor_columns_refined($columns)
{
    $new = [];

    if (isset($columns['cb'])) {
        $new['cb'] = $columns['cb'];
    }

    $new['title']              = 'Vendor';
    $new['vms_vendor_type']       = 'Type';
    $new['vms_vendor_categories'] = 'Categories';
    $new['vms_vendor_contact']    = 'Contact';
    $new['vms_vendor_portal']  = 'Portal';
    $new['vms_vendor_dualhat'] = 'Dual-Hat';
    $new['vms_vendor_tax']     = 'Tax';

    if (isset($columns['date'])) {
        $new['date'] = $columns['date'];
    }

    return $new;
}

function vms_phone_to_tel_href(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') return '';

    // Keep only digits and a leading '+' (E.164-ish). This makes tel: links reliable.
    $digits = preg_replace('/[^0-9+]/', '', $phone);
    if ($digits === null) return '';

    // If there's a '+' not at the start, strip it.
    if (strpos($digits, '+') > 0) {
        $digits = str_replace('+', '', $digits);
    }

    // Basic sanity: require at least 7 digits.
    $plain_digits = preg_replace('/[^0-9]/', '', $digits);
    if ($plain_digits === null || strlen($plain_digits) < 7) return '';

    return 'tel:' . $digits;
}

function vms_admin_vendor_column_render_refined($column, $post_id)
{
    switch ($column) {

        case 'vms_vendor_contact': {
                $k_email = vms_meta_key('vendor', 'primary_email');
                $k_phone = vms_meta_key('vendor', 'primary_phone');

                $email = vms_admin_vendor_list_get_meta_scalar((int) $post_id, (string) $k_email);
                $phone = vms_admin_vendor_list_get_meta_scalar((int) $post_id, (string) $k_phone);

                // Fallback for legacy data if a vendor hasn't been resaved yet.
                if ($email === '') {
                    $k_email_legacy = vms_meta_key('vendor', 'contact_email');
                    $email = vms_admin_vendor_list_get_meta_scalar((int) $post_id, (string) $k_email_legacy);
                }
                if ($phone === '') {
                    $k_phone_legacy = vms_meta_key('vendor', 'contact_phone');
                    $phone = vms_admin_vendor_list_get_meta_scalar((int) $post_id, (string) $k_phone_legacy);
                }


                echo '<div class="vms-vcol-wrap">';

                if ($email !== '') {
                    echo '<div><span class="dashicons dashicons-email"></span> ';
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></div>';
                }

                if ($phone !== '') {
                    $tel = vms_phone_to_tel_href($phone);

                    echo '<div><span class="dashicons dashicons-phone"></span> ';

                    if ($tel !== '') {
                        echo '<a href="' . esc_attr($tel) . '">' . esc_html($phone) . '</a>';
                    } else {
                        echo esc_html($phone);
                    }

                    echo '</div>';
                }

                echo '</div>';
                break;
            }

        case 'vms_vendor_categories': {
                $terms = get_the_terms($post_id, 'vms_vendor_category');

                if (is_wp_error($terms) || empty($terms)) {
                    echo '&nbsp;';
                    break;
                }

                $names = [];
                foreach ($terms as $t) {
                    if (!empty($t->name)) {
                        $names[] = $t->name;
                    }
                }

                if (empty($names)) {
                    echo '&nbsp;';
                    break;
                }

                echo esc_html(implode(', ', $names));
                break;
            }

        case 'vms_vendor_type': {
                $terms = get_the_terms($post_id, 'vms_vendor_type');

                if (is_wp_error($terms) || empty($terms)) {
                    echo '&nbsp;';
                    break;
                }

                $names = [];
                foreach ($terms as $t) {
                    if (!empty($t->name)) {
                        $names[] = $t->name;
                    }
                }

                if (empty($names)) {
                    echo '&nbsp;';
                    break;
                }

                $primary = array_shift($names);
                $extra_count = count($names);

                $label = $primary;
                if ($extra_count > 0) {
                    $label .= ' +' . $extra_count;
                }

                $tooltip = $primary;
                if (!empty($names)) {
                    $tooltip .= ', ' . implode(', ', $names);
                }

                echo '<span title="' . esc_attr($tooltip) . '">' . esc_html($label) . '</span>';
                break;
            }

        case 'vms_vendor_dualhat': {
                if (!function_exists('vms_vendor_linked_staff_meta_key')) {
                    echo '&nbsp;';
                    break;
                }

                $staff_id = (int) get_post_meta((int) $post_id, vms_vendor_linked_staff_meta_key(), true);
                if ($staff_id <= 0) {
                    echo '&nbsp;';
                    break;
                }

                $sp = get_post($staff_id);
                if (!$sp || $sp->post_type !== 'vms_staff') {
                    echo '&nbsp;';
                    break;
                }

                $label = get_the_title($staff_id);
                if (!is_string($label) || $label === '') {
                    $label = 'Staff #' . (string) $staff_id;
                }

                $edit_url = get_edit_post_link($staff_id, '');
                echo '<span class="vms-badge vms-badge-dualhat">' . esc_html__('Dual-Hat', 'vms') . '</span>';
                if (is_string($edit_url) && $edit_url !== '') {
                    echo ' <a href="' . esc_url($edit_url) . '">' . esc_html($label) . '</a>';
                } else {
                    echo ' ' . esc_html($label);
                }

                break;
            }

        case 'vms_vendor_portal': {
                $linked_user_id = 0;

                if (function_exists('vms_vendor_user_links_get_by_vendor')) {
                    $rows = (array) vms_vendor_user_links_get_by_vendor((int) $post_id, false);
                    foreach ($rows as $row) {
                        $candidate_user_id = isset($row['user_id']) ? (int) $row['user_id'] : 0;
                        $link_status = isset($row['link_status']) ? (string) $row['link_status'] : '';
                        if ($candidate_user_id > 0 && ($link_status === '' || $link_status === 'active')) {
                            $linked_user_id = $candidate_user_id;
                            break;
                        }
                    }
                }

                if ($linked_user_id <= 0) {
                    $link_key = defined('VMS_VENDOR_PRIMARY_USER_META_KEY') ? VMS_VENDOR_PRIMARY_USER_META_KEY : '_vms_vendor_user_id';
                    $linked_user_id = (int) get_post_meta($post_id, $link_key, true);
                }

                if ($linked_user_id > 0) {
                    echo '<span class="vms-pill vms-pill-ok" title="Portal linked"><span class="dashicons dashicons-yes"></span> Linked</span>';
                } else {
                    echo '<span class="vms-pill vms-pill-muted" title="No linked WP user"><span class="dashicons dashicons-minus"></span> Not linked</span>';
                }
                break;
            }

        case 'vms_vendor_tax': {
                $k_done   = vms_meta_key('vendor', 'tax_profile_completed_at');
                $done_at  = (int) vms_admin_vendor_list_get_meta_scalar((int) $post_id, (string) $k_done);

                $provider = vms_admin_tax_settings_get_provider();
                $provider_label = ($provider === 'quickbooks_email')
                    ? 'QuickBooks'
                    : (($provider === 'tax1099_email') ? 'Tax1099' : 'Upload');

                $w9_ok = vms_admin_vendor_w9_is_satisfied((int) $post_id);

                $missing = vms_admin_vendor_tax_missing_fields((int) $post_id);
                $missing_title = '';
                if (!empty($missing)) {
                    $missing_title = 'Missing: ' . implode(', ', $missing);
                }

                $w9_pill = $w9_ok
                    ? '<span class="vms-pill vms-pill-ok" title="W-9 requirement satisfied"><span class="dashicons dashicons-yes"></span> W-9</span>'
                    : '<span class="vms-pill vms-pill-bad" title="W-9 missing or not confirmed"><span class="dashicons dashicons-warning"></span> W-9</span>';

                $mode_pill = '<span class="vms-pill vms-pill-muted" title="W-9 provider mode"><span class="dashicons dashicons-admin-settings"></span> ' . esc_html($provider_label) . '</span>';

                $status_pill = ($done_at > 0)
                    ? '<span class="vms-pill vms-pill-ok" title="Tax profile marked complete"><span class="dashicons dashicons-yes"></span> Complete</span>'
                    : '<span class="vms-pill vms-pill-warn" title="Tax profile incomplete"><span class="dashicons dashicons-clock"></span> Incomplete</span>';

                $wrap_title = $missing_title !== '' ? ' title="' . esc_attr($missing_title) . '"' : '';

                echo '<div class="vms-vcol-wrap"' . $wrap_title . '>' . $w9_pill . $mode_pill . $status_pill . '</div>';
                break;
            }
    }
}

/**
 * Render dropdown filters above vendor list.
 */
function vms_admin_vendor_list_filters_render()
{
    if (!is_admin()) return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-vms_vendor') {
        return;
    }

    $tax = vms_admin_vendor_list_allowed_filter('vms_tax', array('complete', 'incomplete'));
    $w9  = vms_admin_vendor_list_allowed_filter('vms_w9', array('ok', 'missing'));

    echo '<label class="screen-reader-text" for="vms_tax">Tax Profile</label>';
    echo '<select name="vms_tax" id="vms_tax" class="vms-vendor-filter-tax">';
    echo '<option value="">Tax Profile: All</option>';
    echo '<option value="complete"' . selected($tax, 'complete', false) . '>Tax Profile: Complete</option>';
    echo '<option value="incomplete"' . selected($tax, 'incomplete', false) . '>Tax Profile: Incomplete</option>';
    echo '</select>';

    echo '<label class="screen-reader-text" for="vms_w9">W-9</label>';
    echo '<select name="vms_w9" id="vms_w9" class="vms-vendor-filter-w9">';
    echo '<option value="">W-9: All</option>';
    echo '<option value="ok"' . selected($w9, 'ok', false) . '>W-9: Satisfied</option>';
    echo '<option value="missing"' . selected($w9, 'missing', false) . '>W-9: Missing</option>';
    echo '</select>';
}

/**
 * Apply dropdown filters to the vendor list query (read-only).
 */
function vms_admin_vendor_list_filters_apply($query)
{
    if (!is_admin()) return;
    if (!$query instanceof WP_Query) return;
    if (!$query->is_main_query()) return;

    $post_type = $query->get('post_type');
    if ($post_type !== 'vms_vendor') return;

    $tax = vms_admin_vendor_list_allowed_filter('vms_tax', array('complete', 'incomplete'));
    $w9  = vms_admin_vendor_list_allowed_filter('vms_w9', array('ok', 'missing'));

    $meta_query = (array) $query->get('meta_query');
    if (!is_array($meta_query)) $meta_query = array();

    // Tax Profile filter uses completion stamp
    if ($tax === 'complete' || $tax === 'incomplete') {
        $k_done = vms_meta_key('vendor', 'tax_profile_completed_at');

        if ($tax === 'complete') {
            $meta_query[] = array(
                'key'     => (string) $k_done,
                'compare' => 'EXISTS',
            );
        } else {
            // Incomplete = stamp not present
            $meta_query[] = array(
                'key'     => (string) $k_done,
                'compare' => 'NOT EXISTS',
            );
        }
    }

    // W-9 filter depends on provider mode
    if ($w9 === 'ok' || $w9 === 'missing') {

        $provider = vms_admin_tax_settings_get_provider();

        if ($provider === 'upload') {
            $k_upload = vms_meta_key('vendor', 'w9_upload_id');
            $k_recv   = vms_meta_key('vendor', 'w9_received_date');

            if ($w9 === 'ok') {
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => (string) $k_upload,
                        'value'   => 0,
                        'type'    => 'NUMERIC',
                        'compare' => '>',
                    ),
                    array(
                        'key'     => (string) $k_recv,
                        'value'   => '',
                        'compare' => '!=',
                    ),
                );
            } else {
                // Missing (storage patterns in your portal delete the meta when not set)
                $meta_query[] = array(
                    'relation' => 'AND',
                    array(
                        'key'     => (string) $k_upload,
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => (string) $k_recv,
                        'compare' => 'NOT EXISTS',
                    ),
                );
            }

        } else {
            $k_attest = vms_meta_key('vendor', 'w9_attested_at');

            if ($w9 === 'ok') {
                $meta_query[] = array(
                    'key'     => (string) $k_attest,
                    'value'   => 0,
                    'type'    => 'NUMERIC',
                    'compare' => '>',
                );
            } else {
                $meta_query[] = array(
                    'key'     => (string) $k_attest,
                    'compare' => 'NOT EXISTS',
                );
            }
        }
    }

    if (!empty($meta_query)) {
        $query->set('meta_query', $meta_query);
    }
}
