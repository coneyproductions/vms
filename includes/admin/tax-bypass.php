<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/../core/tax-bypass.php';

/**
 * VMS – Temporary Tax/W-9 Compliance Bypass (Admin-only)
 *
 * Adds an admin-only bypass for tax-profile completeness requirements.
 * - Must have an expiration date
 * - Must have a reason
 * - Saved with audit fields (who/when)
 * - Works for BOTH vms_vendor and vms_staff (adjust staff CPT if needed)
 *
 * Meta keys:
 *  _vms_tax_bypass_enabled  (bool "1"/"0")
 *  _vms_tax_bypass_until    (YYYY-MM-DD)
 *  _vms_tax_bypass_reason   (string)
 *  _vms_tax_bypass_set_by   (int user_id)
 *  _vms_tax_bypass_set_at   (int timestamp)
 */

if (!function_exists('vms_tax_bypass_supported_post_types')) {
function vms_tax_bypass_supported_post_types(): array
{
    // ✅ If your staff CPT slug differs, change it here.
    return array('vms_vendor', 'vms_staff');
}
}

if (!function_exists('vms_vendor_tax_bypass_meta_key')) {
function vms_vendor_tax_bypass_meta_key(string $field, string $fallback): string
{
    if (!function_exists('vms_meta_key')) {
        return $fallback;
    }
    $mapped = (string) vms_meta_key('vendor', $field);
    return $mapped !== '' ? $mapped : $fallback;
}
}


/**
 * Returns array status:
 *  - enabled (bool)
 *  - until (string YYYY-MM-DD)
 *  - reason (string)
 *  - expired (bool)
 *  - days_left (int|null)
 */
if (!function_exists('vms_get_tax_bypass_status')) {
function vms_get_tax_bypass_status(int $post_id): array
{
    $k_enabled = vms_vendor_tax_bypass_meta_key('tax_bypass_enabled', '_vms_tax_bypass_enabled');
    $k_until   = vms_vendor_tax_bypass_meta_key('tax_bypass_until', '_vms_tax_bypass_until');
    $k_reason  = vms_vendor_tax_bypass_meta_key('tax_bypass_reason', '_vms_tax_bypass_reason');

    $enabled = (int) get_post_meta($post_id, $k_enabled, true) === 1;
    $until   = (string) get_post_meta($post_id, $k_until, true);
    $reason  = (string) get_post_meta($post_id, $k_reason, true);

    $expired = false;
    $days_left = null;

    if ($enabled) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
            // invalid date -> treat as expired (forces you to fix it)
            $expired = true;
        } else {
            $today = current_time('Y-m-d');
            if ($today > $until) {
                $expired = true;
            } else {
                $t1 = strtotime($today);
                $t2 = strtotime($until);
                if ($t1 && $t2) {
                    $days_left = (int) floor(($t2 - $t1) / DAY_IN_SECONDS);
                }
            }
        }
    }

    return array(
        'enabled'   => $enabled,
        'until'     => $until,
        'reason'    => $reason,
        'expired'   => $expired,
        'days_left' => $days_left,
    );
}
}


/**
 * Bypass is ACTIVE only when enabled, has valid until date, and not expired.
 */
if (!function_exists('vms_tax_bypass_is_active')) {
function vms_tax_bypass_is_active(int $post_id): bool
{
    $s = vms_get_tax_bypass_status($post_id);
    return $s['enabled'] && !$s['expired'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s['until']);
}
}


/**
 * Human warning label used in admin notices / validators.
 */
if (!function_exists('vms_tax_bypass_warning_label')) {
function vms_tax_bypass_warning_label(int $post_id): string
{
    $s = vms_get_tax_bypass_status($post_id);
    if (!$s['enabled']) return '';
    if ($s['expired']) return 'Tax bypass is set but EXPIRED.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s['until'])) return 'Tax bypass is set but has an invalid expiration date.';
    $left = ($s['days_left'] === null) ? '' : (' (' . (int)$s['days_left'] . ' day(s) left)');
    return 'Tax bypass active until ' . $s['until'] . $left . '.';
}
}


/**
 * Admin metabox (sidebar)
 */
add_action('add_meta_boxes', function () {
    foreach (vms_tax_bypass_supported_post_types() as $pt) {
        add_meta_box(
            'vms_tax_bypass_box',
            __('Tax Compliance Bypass', 'backstage-venue-manager'),
            'vms_render_tax_bypass_box',
            $pt,
            'side',
            'high'
        );
    }
});

function vms_render_tax_bypass_box($post)
{
    if (!current_user_can('manage_options')) {
        echo '<p class="description">' . esc_html__('Admins only.', 'backstage-venue-manager') . '</p>';
        return;
    }

    wp_nonce_field('vms_save_tax_bypass', 'vms_tax_bypass_nonce');

    $s = vms_get_tax_bypass_status((int)$post->ID);

    $k_set_by = vms_vendor_tax_bypass_meta_key('tax_bypass_set_by', '_vms_tax_bypass_set_by');
    $k_set_at = vms_vendor_tax_bypass_meta_key('tax_bypass_set_at', '_vms_tax_bypass_set_at');

    $set_by = (int) get_post_meta($post->ID, $k_set_by, true);
    $set_at = (int) get_post_meta($post->ID, $k_set_at, true);

    $badge = '';
    if ($s['enabled'] && !$s['expired']) {
        $badge = '<span class="vms-tax-bypass-badge vms-tax-bypass-badge-active">ACTIVE</span>';
    } elseif ($s['enabled'] && $s['expired']) {
        $badge = '<span class="vms-tax-bypass-badge vms-tax-bypass-badge-expired">EXPIRED</span>';
    } else {
        $badge = '<span class="vms-tax-bypass-badge vms-tax-bypass-badge-off">OFF</span>';
    }

    echo '<p class="vms-tax-bypass-badge-row">' . $badge . '</p>';

    echo '<p class="description vms-tax-bypass-desc-top">' .
        esc_html__('Use only if a W-9 is verbally agreed and will be supplied soon. This bypass expires automatically.', 'backstage-venue-manager') .
        '</p>';

    // Toggle
    echo '<p class="vms-tax-bypass-field-row">';
    echo '<label class="vms-tax-bypass-strong-label">';
    echo '<input type="checkbox" name="vms_tax_bypass_enabled" value="1" ' . checked($s['enabled'], true, false) . '> ';
    echo esc_html__('Allow temporary bypass', 'backstage-venue-manager');
    echo '</label>';
    echo '</p>';

    // Expiration
    echo '<p class="vms-tax-bypass-field-row-tight">';
    echo '<label class="vms-tax-bypass-strong-label vms-tax-bypass-strong-label-block">' . esc_html__('Bypass expires on (required)', 'backstage-venue-manager') . '</label>';
    echo '<input type="date" name="vms_tax_bypass_until" value="' . esc_attr($s['until']) . '" class="vms-tax-bypass-input-full">';
    echo '</p>';

    // Reason
    echo '<p class="vms-tax-bypass-field-row-tight">';
    echo '<label class="vms-tax-bypass-strong-label vms-tax-bypass-strong-label-block">' . esc_html__('Reason (required)', 'backstage-venue-manager') . '</label>';
    echo '<textarea name="vms_tax_bypass_reason" rows="3" class="vms-tax-bypass-input-full">' . esc_textarea($s['reason']) . '</textarea>';
    echo '</p>';

    // Audit line
    if ($set_at > 0) {
        $who = $set_by ? get_user_by('id', $set_by) : null;
        $who_name = $who ? ($who->display_name ?: $who->user_login) : __('Unknown', 'backstage-venue-manager');

        $dt = new DateTime('@' . $set_at);
        $dt->setTimezone(wp_timezone());

        echo '<p class="description vms-tax-bypass-audit">';
        echo esc_html__('Last set by:', 'backstage-venue-manager') . ' <strong>' . esc_html($who_name) . '</strong><br>';
        echo esc_html__('At:', 'backstage-venue-manager') . ' ' . esc_html($dt->format('M j, Y g:ia'));
        echo '</p>';
    }

    // Warning if enabled but invalid/expired
    if ($s['enabled']) {
        $warn = vms_tax_bypass_warning_label((int)$post->ID);
        $warn_class = $s['expired'] ? 'vms-tax-bypass-warn-expired' : 'vms-tax-bypass-warn-active';
        echo '<p class="description vms-tax-bypass-warn ' . esc_attr($warn_class) . '">' .
            esc_html($warn) .
            '</p>';
    }

    echo '<hr class="vms-tax-bypass-divider">';
    echo '<p class="description vms-tax-bypass-save-note">' .
        esc_html__('Save/Update this post to apply changes.', 'backstage-venue-manager') .
        '</p>';

    add_action('admin_footer-post.php', 'vms_admin_disable_required_for_tax_fields');
    add_action('admin_footer-post-new.php', 'vms_admin_disable_required_for_tax_fields');

    function vms_admin_disable_required_for_tax_fields()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) return;

        // Only on Vendor + Staff edit screens
        $ok = array('vms_vendor', 'vms_staff'); // adjust staff slug if needed
        if (!in_array($screen->post_type, $ok, true)) return;

?>
        <script>
            (function() {
                // Strip HTML required flags so admin can still save titles/notes/etc.
                // Compliance is enforced at workflow gates (READY/assign), not on basic saving.
                var selectors = [
                    'input[name^="vms_tax_"]',
                    'select[name^="vms_tax_"]',
                    'input[name^="vms_addr"]',
                    'input[name="vms_city"]',
                    'input[name="vms_state"]',
                    'input[name="vms_zip"]',
                    'input[name="vms_payee_legal_name"]',
                    'select[name="vms_entity_type"]'
                ];
                document.querySelectorAll(selectors.join(',')).forEach(function(el) {
                    el.removeAttribute('required');
                    // also remove ARIA required if any
                    el.removeAttribute('aria-required');
                });
            })();
        </script>
<?php
    }
}

/**
 * Save handler
 */
add_action('save_post', function ($post_id, $post) {
    if (!is_object($post)) return;

    $supported = vms_tax_bypass_supported_post_types();
    if (!in_array($post->post_type, $supported, true)) return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    if (!current_user_can('manage_options')) return;

    if (
        empty($_POST['vms_tax_bypass_nonce']) ||
        !wp_verify_nonce($_POST['vms_tax_bypass_nonce'], 'vms_save_tax_bypass')
    ) {
        return;
    }

    $enabled = !empty($_POST['vms_tax_bypass_enabled']) ? 1 : 0;
    $until   = isset($_POST['vms_tax_bypass_until']) ? sanitize_text_field(wp_unslash($_POST['vms_tax_bypass_until'])) : '';
    $reason  = isset($_POST['vms_tax_bypass_reason']) ? sanitize_text_field(wp_unslash($_POST['vms_tax_bypass_reason'])) : '';

    $k_enabled = vms_vendor_tax_bypass_meta_key('tax_bypass_enabled', '_vms_tax_bypass_enabled');
    $k_until   = vms_vendor_tax_bypass_meta_key('tax_bypass_until', '_vms_tax_bypass_until');
    $k_reason  = vms_vendor_tax_bypass_meta_key('tax_bypass_reason', '_vms_tax_bypass_reason');
    $k_set_by  = vms_vendor_tax_bypass_meta_key('tax_bypass_set_by', '_vms_tax_bypass_set_by');
    $k_set_at  = vms_vendor_tax_bypass_meta_key('tax_bypass_set_at', '_vms_tax_bypass_set_at');

    // Normalize
    $until = trim($until);
    $reason = trim($reason);

    // If turning OFF, clear everything (keeps things clean).
    if (!$enabled) {
        update_post_meta($post_id, $k_enabled, 0);
        delete_post_meta($post_id, $k_until);
        delete_post_meta($post_id, $k_reason);
        delete_post_meta($post_id, $k_set_by);
        delete_post_meta($post_id, $k_set_at);
        return;
    }

    // Turning ON requires valid date + reason
    $valid_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $until);
    if (!$valid_date || $reason === '') {
        // Save as "enabled" but immediately expired/invalid forces attention;
        // Still store the attempted values.
        update_post_meta($post_id, $k_enabled, 1);
        update_post_meta($post_id, $k_until, $until);
        update_post_meta($post_id, $k_reason, $reason);
    } else {
        update_post_meta($post_id, $k_enabled, 1);
        update_post_meta($post_id, $k_until, $until);
        update_post_meta($post_id, $k_reason, $reason);
    }

    update_post_meta($post_id, $k_set_by, get_current_user_id());
    update_post_meta($post_id, $k_set_at, time());
}, 20, 2);
