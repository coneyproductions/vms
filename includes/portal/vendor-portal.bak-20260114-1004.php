<?php

/**
 * VMS Vendor Portal (Procedural)
 *
 * Shortcode: [vms_vendor_portal]
 *
 * Tabs:
 *  - dashboard
 *  - profile
 *  - tax-profile
 *  - availability
 *  - tech
 *
 * Notes:
 *  - Mobile-first availability UI: tap a day to cycle (— → Available → Not Available)
 *  - Stores manual overrides in: _vms_availability_manual (array: YYYY-MM-DD => available|unavailable)
 *  - Optional ICS data:
 *      _vms_ics_url (string)
 *      _vms_ics_autosync (0|1)
 *      _vms_ics_last_sync (timestamp)
 *      _vms_ics_unavailable (array of YYYY-MM-DD)  (if your ICS sync module stores this)
 *  - “Preferred method” (for collapsing sections):
 *      _vms_availability_preferred_method = manual|ics
 */

if (!defined('ABSPATH')) {
    exit;
}

// Optional module includes
$tax_file = plugin_dir_path(__FILE__) . 'vendor-tax-profile.php';
if (file_exists($tax_file)) {
    require_once $tax_file;
}

// Register shortcode once.
if (!shortcode_exists('vms_vendor_portal') && function_exists('vms_vendor_portal_shortcode')) {
    add_shortcode('vms_vendor_portal', 'vms_vendor_portal_shortcode');
}

/**
 * Small, theme-agnostic notices for the portal.
 */
if (!function_exists('vms_portal_notice')) {
    function vms_portal_notice(string $type, string $msg): string
    {
        $type = ($type === 'success' || $type === 'warning') ? $type : 'error';
        return '<div class="vms-notice vms-notice-' . esc_attr($type) . '">' . esc_html($msg) . '</div>';
    }
}

/**
 * Flag vendor updates in a consistent way.
 * (Uses whichever helper exists in your project; falls back safely.)
 */
if (!function_exists('vms_vendor_flag_vendor_update')) {
    function vms_vendor_flag_vendor_update($vendor_id, $context = ''): void
    {
        $vendor_id = (int) $vendor_id;
        if ($vendor_id <= 0) return;

        $user_id = (int) get_current_user_id();

        // Preferred helper (newer)
        if (function_exists('vms_vendor_flag_updated')) {
            vms_vendor_flag_updated($vendor_id, $user_id, (string) $context);
            return;
        }

        // Existing helper you’ve used throughout
        if (function_exists('vms_vendor_mark_profile_updated')) {
            // Call simplest signature to avoid fatals from mismatched versions.
            vms_vendor_mark_profile_updated($vendor_id, $user_id);
            return;
        }

        // Absolute fallback (won’t break anything)
        update_post_meta($vendor_id, '_vms_vendor_last_updated_at', current_time('mysql'));
        update_post_meta($vendor_id, '_vms_vendor_last_updated_by', $user_id);
        update_post_meta($vendor_id, '_vms_vendor_needs_review', 1);
        if ($context !== '') {
            update_post_meta($vendor_id, '_vms_vendor_last_update_context', sanitize_key($context));
        }
    }
}

/**
 * Year-round availability dates.
 * - Uses vms_get_active_season_dates() if configured
 * - Otherwise generates a rolling window of days (default 12 months, cap 24)
 *
 * @return string[] YYYY-MM-DD
 */
function vms_vendor_get_active_dates_or_rolling_window(int $months_ahead = 12): array
{
    // 1) Use season dates if they exist
    $active_dates = function_exists('vms_get_active_season_dates') ? (array) vms_get_active_season_dates() : array();
    $active_dates = array_values(array_filter(array_map('sanitize_text_field', $active_dates)));

    if (!empty($active_dates)) {
        return $active_dates;
    }

    // 2) Otherwise generate rolling window
    $months_ahead = (int) $months_ahead;
    if ($months_ahead < 1) $months_ahead = 12;
    if ($months_ahead > 24) $months_ahead = 24;

    $tz = wp_timezone();
    $start = new DateTime('today', $tz);
    $end   = (clone $start)->modify('+' . $months_ahead . ' months');

    $dates = array();
    $cur = clone $start;

    while ($cur < $end) {
        $dates[] = $cur->format('Y-m-d');
        $cur->modify('+1 day');
    }

    return $dates;
}
function vms_vendor_portal_shortcode()
{
    $base_url = get_permalink(); // page where shortcode lives

    // Logged-out view
    if (!is_user_logged_in()) {
        $apply_url = site_url('/vendor-application/'); // where [vms_vendor_apply] lives

        ob_start(); ?>
        <div class="vms-portal-auth-wrap">
            <div class="vms-portal-auth-col vms-portal-auth-login">
                <h2><?php echo esc_html(function_exists('vms_ui_text') ? vms_ui_text('portal_login_title', __('Vendor Portal Login', 'vms')) : __('Vendor Portal Login', 'vms')); ?></h2>
                <?php
                echo wp_login_form(array(
                    'echo'     => false,
                    'redirect' => esc_url(get_permalink()),
                ));
                ?>
                <p style="margin-top:10px;">
                    <a href="<?php echo esc_url(wp_lostpassword_url(get_permalink())); ?>"><?php echo esc_html__('Forgot password?', 'vms'); ?></a>
                </p>
            </div>

            <div class="vms-portal-auth-col vms-portal-auth-apply">
                <h2><?php echo esc_html(function_exists('vms_ui_text') ? vms_ui_text('portal_need_account_title', __('Need an Account?', 'vms')) : __('Need an Account?', 'vms')); ?></h2>

                <p><?php echo esc_html(function_exists('vms_ui_text') ? vms_ui_text('portal_need_account_blurb', __('Vendors must be approved before getting portal access.', 'vms')) : __('Vendors must be approved before getting portal access.', 'vms')); ?></p>

                <p>
                    <a class="button button-primary" href="<?php echo esc_url($apply_url); ?>">
                        <?php echo esc_html(function_exists('vms_ui_text') ? vms_ui_text('portal_apply_button', __('Apply for an Account', 'vms')) : __('Apply for an Account', 'vms')); ?>
                    </a>
                </p>

                <p style="margin-top:10px; opacity:0.85;">
                    <?php echo esc_html(function_exists('vms_ui_text') ? vms_ui_text('portal_applied_hint', __('Already applied? We’ll email you once you’re approved.', 'vms')) : __('Already applied? We’ll email you once you’re approved.', 'vms')); ?>
                </p>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    // Logged-in: find linked vendor post
    $user_id   = get_current_user_id();
    $vendor_id = (int) get_user_meta($user_id, '_vms_vendor_id', true);

    if (!$vendor_id) {
        return '<p>' . esc_html__('Your account is not linked to a vendor profile yet. Please contact the venue admin.', 'vms') . '</p>';
    }

    $vendor = get_post($vendor_id);
    if (!$vendor || $vendor->post_type !== 'vms_vendor') {
        return '<p>' . esc_html__('Your linked vendor profile could not be found. Please contact the venue admin.', 'vms') . '</p>';
    }

    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

    // Pre-build URLs
    $url_dashboard    = add_query_arg('tab', 'dashboard', $base_url);
    $url_profile      = add_query_arg('tab', 'profile', $base_url);
    $url_tax_profile  = add_query_arg('tab', 'tax-profile', $base_url);
    $url_availability = add_query_arg('tab', 'availability', $base_url);
    $url_tech         = add_query_arg('tab', 'tech', $base_url);

    ob_start();

    // Base styles (minimal, mobile-friendly)
    echo '<style>
.vms-notice{padding:10px 12px;border-radius:12px;margin:12px 0;border:1px solid transparent;font-weight:600}
.vms-notice-success{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
.vms-notice-warning{background:#fffbeb;border-color:#fcd34d;color:#92400e}
.vms-notice-error{background:#fef2f2;border-color:#fecaca;color:#991b1b}

.vms-portal-card{background:#fff;border:1px solid #e5e5e5;border-radius:14px;padding:14px;margin:0 0 14px}
.vms-portal-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
@media (max-width:820px){.vms-portal-grid{grid-template-columns:1fr}}
.vms-muted{opacity:.8}

.vms-portal-nav{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0}
.vms-portal-nav a{display:inline-block;padding:8px 10px;border:1px solid #e5e5e5;border-radius:12px;text-decoration:none}
.vms-portal-nav a.is-active{border-color:#111827;font-weight:800}

.vms-field{margin:0 0 14px}
.vms-field label{display:block;margin:0 0 6px;font-weight:800}
.vms-field input,.vms-field textarea,.vms-field select{width:100%;max-width:520px}
</style>';

    // Header + nav (shown on all tabs)
    echo '<div class="vms-portal">';
    echo '<h2 style="margin:0 0 8px;">' . esc_html(function_exists('vms_ui_text') ? vms_ui_text('portal_title_prefix', __('Vendor Portal:', 'vms')) : __('Vendor Portal:', 'vms')) . ' ' . esc_html($vendor->post_title) . '</h2>';

    echo '<nav class="vms-portal-nav">';
    echo '<a class="' . ($tab === 'dashboard' ? 'is-active' : '') . '" href="' . esc_url($url_dashboard) . '">' . esc_html__('Dashboard', 'vms') . '</a>';
    echo '<a class="' . ($tab === 'profile' ? 'is-active' : '') . '" href="' . esc_url($url_profile) . '">' . esc_html__('Profile', 'vms') . '</a>';
    echo '<a class="' . ($tab === 'tax-profile' ? 'is-active' : '') . '" href="' . esc_url($url_tax_profile) . '">' . esc_html__('Tax Profile', 'vms') . '</a>';
    echo '<a class="' . ($tab === 'availability' ? 'is-active' : '') . '" href="' . esc_url($url_availability) . '">' . esc_html__('Availability', 'vms') . '</a>';
    echo '<a class="' . ($tab === 'tech' ? 'is-active' : '') . '" href="' . esc_url($url_tech) . '">' . esc_html__('Tech Docs', 'vms') . '</a>';
    echo '</nav>';
    echo '</div>';

    // Route
    if ($tab === 'dashboard') {
        echo '<div class="vms-portal-card">';
        echo '<p class="vms-muted">' . esc_html(function_exists('vms_ui_text') ? vms_ui_text('portal_intro', __('Choose a tab to update your information.', 'vms')) : __('Choose a tab to update your information.', 'vms')) . '</p>';
        echo '</div>';

        echo '<div class="vms-portal-grid">';

        echo '<div class="vms-portal-card">
            <h3 style="margin-top:0;">' . esc_html__('Profile', 'vms') . '</h3>
            <p class="vms-muted">' . esc_html__('Update your contact info, links, and logo.', 'vms') . '</p>
            <p><a class="button" href="' . esc_url($url_profile) . '">' . esc_html__('Edit Profile', 'vms') . '</a></p>
        </div>';

        echo '<div class="vms-portal-card">
            <h3 style="margin-top:0;">' . esc_html__('Availability', 'vms') . '</h3>
            <p class="vms-muted">' . esc_html__('Mark dates you are available or unavailable.', 'vms') . '</p>
            <p><a class="button" href="' . esc_url($url_availability) . '">' . esc_html__('Update Availability', 'vms') . '</a></p>
        </div>';

        echo '<div class="vms-portal-card">
            <h3 style="margin-top:0;">' . esc_html__('Tech Docs', 'vms') . '</h3>
            <p class="vms-muted">' . esc_html__('Upload your stage plot and input list.', 'vms') . '</p>
            <p><a class="button" href="' . esc_url($url_tech) . '">' . esc_html__('Manage Tech Docs', 'vms') . '</a></p>
        </div>';

        echo '<div class="vms-portal-card">
            <h3 style="margin-top:0;">' . esc_html__('Tax Profile', 'vms') . '</h3>
            <p class="vms-muted">' . esc_html__('Complete your payee + mailing info and upload your signed W-9.', 'vms') . '</p>
            <p><a class="button" href="' . esc_url($url_tax_profile) . '">' . esc_html__('Update Tax Profile', 'vms') . '</a></p>
        </div>';

        echo '</div>';
    } elseif ($tab === 'profile') {
        if (function_exists('vms_vendor_portal_render_profile')) {
            vms_vendor_portal_render_profile($vendor_id);
        } else {
            echo vms_portal_notice('error', __('Profile module is not loaded.', 'vms'));
        }
    } elseif ($tab === 'tax-profile') {
        if (function_exists('vms_vendor_portal_render_tax_profile')) {
            vms_vendor_portal_render_tax_profile($vendor_id);
        } else {
            echo vms_portal_notice('error', __('Tax Profile module is not loaded.', 'vms'));
        }
    } elseif ($tab === 'availability') {
        $active_dates = vms_vendor_get_active_dates_or_rolling_window(12); // year-round fallback
        vms_vendor_portal_render_availability($vendor_id, $active_dates);
    } elseif ($tab === 'tech') {
        if (function_exists('vms_vendor_portal_render_tech_docs')) {
            vms_vendor_portal_render_tech_docs($vendor_id);
        } else {
            echo vms_portal_notice('error', __('Tech Docs module is not loaded.', 'vms'));
        }
    }

    return ob_get_clean();
}

/* ==========================================================
 * Availability (Calendar UI — tap-to-toggle, mobile-first)
 * ========================================================== */

if (!function_exists('vms_vendor_portal_render_availability')) {
    function vms_vendor_portal_render_availability($vendor_id, $active_dates = array())
    {
        $vendor_id = (int) $vendor_id;

        if ($vendor_id <= 0) {
            echo vms_portal_notice('error', __('Invalid vendor.', 'vms'));
            return;
        }

        if (!is_array($active_dates)) $active_dates = array();

        // Normalize active dates
        $active_dates = array_values(array_filter(array_map(function ($d) {
            $d = trim((string) $d);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
        }, $active_dates)));

        // Load existing values
        $manual = get_post_meta($vendor_id, '_vms_availability_manual', true);
        if (!is_array($manual)) $manual = array();

        $ics_url      = (string) get_post_meta($vendor_id, '_vms_ics_url', true);
        $ics_autosync = (int) get_post_meta($vendor_id, '_vms_ics_autosync', true);
        $ics_last     = (int) get_post_meta($vendor_id, '_vms_ics_last_sync', true);

        // Optional: dates marked unavailable from ICS sync module
        $ics_unavailable = get_post_meta($vendor_id, '_vms_ics_unavailable', true);
        if (!is_array($ics_unavailable)) $ics_unavailable = array();
        $ics_lookup = array_flip(array_values(array_filter($ics_unavailable)));

        $preferred = (string) get_post_meta($vendor_id, '_vms_availability_preferred_method', true);
        if ($preferred !== 'manual' && $preferred !== 'ics') $preferred = 'manual';

        // ----------------------------------------------------------
        // POST handling
        // ----------------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // 1) Save ICS Settings
            if (isset($_POST['vms_save_ics_settings'])) {
                if (!isset($_POST['vms_ics_nonce']) || !wp_verify_nonce($_POST['vms_ics_nonce'], 'vms_ics_settings')) {
                    echo vms_portal_notice('error', __('Security check failed.', 'vms'));
                } else {
                    $new_url = isset($_POST['vms_ics_url']) ? esc_url_raw(trim((string) $_POST['vms_ics_url'])) : '';
                    $new_autosync = !empty($_POST['vms_ics_autosync']) ? 1 : 0;

                    $changed = (($new_url !== $ics_url) || ((int) $new_autosync !== (int) $ics_autosync));

                    update_post_meta($vendor_id, '_vms_ics_url', $new_url);
                    update_post_meta($vendor_id, '_vms_ics_autosync', (int) $new_autosync);

                    $ics_url      = $new_url;
                    $ics_autosync = (int) $new_autosync;

                    update_post_meta($vendor_id, '_vms_availability_preferred_method', 'ics');
                    $preferred = 'ics';

                    if ($changed) {
                        vms_vendor_flag_vendor_update($vendor_id, 'availability_ics_settings');
                    }

                    echo vms_portal_notice('success', __('Calendar settings saved.', 'vms'));
                }
            }

            // 2) Save Manual Availability
            if (isset($_POST['vms_save_availability'])) {
                if (!isset($_POST['vms_avail_nonce']) || !wp_verify_nonce($_POST['vms_avail_nonce'], 'vms_save_availability')) {
                    echo vms_portal_notice('error', __('Security check failed.', 'vms'));
                } else {
                    $incoming = (isset($_POST['vms_availability']) && is_array($_POST['vms_availability']))
                        ? $_POST['vms_availability']
                        : array();

                    $active_lookup = array_flip($active_dates);
                    $new_manual = array();

                    foreach ($incoming as $date => $state) {
                        $date  = sanitize_text_field((string) $date);
                        $state = sanitize_text_field((string) $state);

                        if (!isset($active_lookup[$date])) continue;

                        if ($state === 'available' || $state === 'unavailable') {
                            $new_manual[$date] = $state;
                        }
                    }

                    $changed = (serialize($new_manual) !== serialize($manual));

                    update_post_meta($vendor_id, '_vms_availability_manual', $new_manual);
                    $manual = $new_manual;

                    update_post_meta($vendor_id, '_vms_availability_preferred_method', 'manual');
                    $preferred = 'manual';

                    if ($changed) {
                        vms_vendor_flag_vendor_update($vendor_id, 'availability_manual');
                    }

                    echo vms_portal_notice('success', __('Availability saved.', 'vms'));
                }
            }

            // 3) Sync ICS Now
            if (isset($_POST['vms_sync_ics_now'])) {
                if (!isset($_POST['vms_ics_nonce']) || !wp_verify_nonce($_POST['vms_ics_nonce'], 'vms_ics_settings')) {
                    echo vms_portal_notice('error', __('Security check failed.', 'vms'));
                } else {
                    update_post_meta($vendor_id, '_vms_availability_preferred_method', 'ics');
                    $preferred = 'ics';

                    if (empty($ics_url)) {
                        echo vms_portal_notice('warning', __('Please paste your calendar feed (ICS) URL first.', 'vms'));
                    } elseif (!function_exists('vms_vendor_ics_sync_now')) {
                        echo vms_portal_notice('error', __('ICS sync module is not loaded.', 'vms'));
                    } else {
                        $result = vms_vendor_ics_sync_now($vendor_id, $active_dates);

                        if (!empty($result['ok'])) {

                            // If your sync returns a list, persist it for UI/source labels.
                            if (isset($result['ics_unavailable']) && is_array($result['ics_unavailable'])) {
                                $ics_unavailable = array_values(array_unique(array_filter(array_map('sanitize_text_field', $result['ics_unavailable']))));
                                update_post_meta($vendor_id, '_vms_ics_unavailable', $ics_unavailable);
                                $ics_lookup = array_flip($ics_unavailable);
                            }

                            update_post_meta($vendor_id, '_vms_ics_last_sync', time());
                            $ics_last = time();

                            vms_vendor_flag_vendor_update($vendor_id, 'availability_ics_sync');

                            $count = isset($result['ics_unavailable']) && is_array($result['ics_unavailable'])
                                ? count($result['ics_unavailable'])
                                : 0;

                            echo vms_portal_notice('success', sprintf(__('Calendar synced. %d date(s) marked unavailable.', 'vms'), $count));
                        } else {
                            $msg = !empty($result['error']) ? (string) $result['error'] : __('Calendar sync failed.', 'vms');
                            echo vms_portal_notice('error', $msg);
                        }
                    }
                }
            }
        }

        // ----------------------------------------------------------
        // Render UI
        // ----------------------------------------------------------
        echo '<h3>' . esc_html__('Availability', 'vms') . '</h3>';

        if (empty($active_dates)) {
            echo vms_portal_notice('warning', __('No season dates configured yet.', 'vms'));
            return;
        }

        // Group active dates by month
        $months = vms_av_group_dates_by_month($active_dates);

        // Which month should be open by default? (current month, else first active month)
        $today_ym = wp_date('Y-m');
        $default_open_ym = isset($months[$today_ym]) ? $today_ym : array_key_first($months);

        // CSS for calendar (mobile-first)
        echo '<style>
.vms-av-wrap{max-width:980px}
.vms-av-card{background:#fff;border:1px solid #e5e5e5;border-radius:14px;padding:14px;margin:0 0 14px}
.vms-av-row{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.vms-av-row .field{min-width:260px;flex:1}
.vms-av-actions{display:flex;gap:10px;flex-wrap:wrap}
.vms-av-muted{opacity:.8}

.vms-av-month details{border:1px solid #e5e5e5;border-radius:12px;background:#fff;margin:0 0 12px;overflow:hidden}
.vms-av-month summary{cursor:pointer;list-style:none;padding:12px 14px;font-weight:800;display:flex;justify-content:space-between;align-items:center}
.vms-av-month summary::-webkit-details-marker{display:none}

.vms-av-grid{width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed}
.vms-av-grid th{font-size:12px;letter-spacing:.02em;text-transform:uppercase;opacity:.75;padding:10px;border-top:1px solid #eee;text-align:left}
.vms-av-grid td{vertical-align:top;border-top:1px solid #eee;border-right:1px solid #eee;min-height:92px;padding:8px}
.vms-av-grid td:last-child{border-right:none}
.vms-av-inactive{background:#fafafa;opacity:.55}
.vms-av-day{display:flex;justify-content:space-between;gap:8px;align-items:center;margin:0 0 8px}
.vms-av-daynum{font-weight:900}
.vms-av-chip{font-size:11px;font-weight:900;border-radius:999px;padding:2px 8px;display:inline-block;line-height:1}
.vms-av-chip.na{background:#fee2e2;color:#991b1b}
.vms-av-chip.a{background:#dcfce7;color:#166534}
.vms-av-src{display:inline-flex;gap:6px;align-items:center;font-size:11px;font-weight:800;opacity:.8;margin-top:8px}
.vms-av-src span{display:inline-block;padding:2px 8px;border-radius:999px;background:#f3f4f6}
.vms-av-src .m{background:#eef2ff}
.vms-av-src .c{background:#ecfeff}

/* Tap-to-toggle button */
/* Keep day cells from letting content bleed into neighboring cells */
.vms-av-grid td{overflow:hidden}

/* Override theme button styles */
.vms-av-grid .vms-av-btn{
  appearance:none;
  -webkit-appearance:none;
  display:block;
  width:100%;
  max-width:100%;
  box-sizing:border-box;
  cursor:pointer;
  text-align:center;
  border:1px solid #e5e5e5 !important;
  border-radius:12px;
  padding:10px;
  background:#fff !important;
  color:#111827 !important;
  box-shadow:none !important;
  font:inherit;
  line-height:1.15;
}

.vms-av-grid .vms-av-btn:active{transform:scale(.99)}

.vms-av-grid .vms-av-btn[data-state="available"]{
  border-color:#16a34a !important;
  background:#f0fdf4 !important;
}

.vms-av-grid .vms-av-btn[data-state="unavailable"]{
  border-color:#ef4444 !important;
  background:#fef2f2 !important;
}

/* Keep the button label compact and unbreakable in its own box */
.vms-av-grid .vms-av-btn .row{
  display:flex;
  align-items:center;
  justify-content:center;
  min-width:0;
}

.vms-av-grid .vms-av-btn .vms-av-state{
  font-weight:900;
  font-size:13px;
  white-space:normal;
  overflow-wrap:anywhere;
}

/* Touch devices: make tap target comfy */
@media (hover:none) and (pointer:coarse){
  .vms-av-grid .vms-av-btn{min-height:44px}
  .vms-av-grid .vms-av-btn .vms-av-state{font-size:14px}
}
.vms-av-help{font-size:12px;opacity:.8;margin:10px 0 0}

/* Mobile: turn grid into stacked list */
@media (max-width:820px){
  .vms-av-grid, .vms-av-grid thead, .vms-av-grid tbody, .vms-av-grid th, .vms-av-grid tr{display:block}
  .vms-av-grid thead{display:none}
  .vms-av-grid tr{border-top:1px solid #eee}
  .vms-av-grid td{border-right:none;border-top:none;padding:10px}
}

.vms-av-autosave{
  font-size:12px;
  opacity:.85;
  margin:8px 0 0;
  min-height:16px;
}

@media (max-width:520px){
  /* The button already communicates state; chips just add height */
  .vms-av-chip{display:none}
  .vms-av-src{display:none}

  /* Keep tap targets uniform */
  .vms-av-grid .vms-av-btn{padding:8px}
  .vms-av-grid .vms-av-state{font-size:14px;font-weight:900}
}

/* =========================================================
   PATCH 20260114 @ 9:33am
   ========================================================= */
/* =========================================================
   Calendar grid: force true 7-column table layout (mobile-safe)
   ========================================================= */
.vms-av-grid{
  width:100% !important;
  table-layout:fixed !important;
  border-collapse:separate !important;
  border-spacing:0 !important;
}

/* Some themes “responsive-table” tables by changing display rules */
.vms-av-grid{display:table !important}
.vms-av-grid thead{display:table-header-group !important}
.vms-av-grid tbody{display:table-row-group !important}
.vms-av-grid tr{display:table-row !important}

.vms-av-grid th,
.vms-av-grid td{
  display:table-cell !important;
  width:14.2857% !important;
  max-width:14.2857% !important;
  vertical-align:top;
  min-width:0;
  overflow:hidden;
}

/* Keep content from bleeding into neighbors */
.vms-av-grid td{box-sizing:border-box}

/* =========================================================
   Availability button: keep theme from hijacking styles
   ========================================================= */
.vms-av-grid .vms-av-btn{
  appearance:none;
  -webkit-appearance:none;
  display:block;
  width:100%;
  max-width:100%;
  box-sizing:border-box;
  text-align:center;
  cursor:pointer;
  border:1px solid #e5e5e5 !important;
  border-radius:12px;
  padding:10px;
  background:#fff !important;
  color:#111827 !important;
  box-shadow:none !important;
  font:inherit;
  line-height:1.15;
}

.vms-av-grid .vms-av-btn[data-state="available"]{
  border-color:#16a34a !important;
  background:#f0fdf4 !important;
}

.vms-av-grid .vms-av-btn[data-state="unavailable"]{
  border-color:#ef4444 !important;
  background:#fef2f2 !important;
}

/* =========================================================
   Event Plan title in cell: clamp so layout never breaks
   ========================================================= */
.vms-av-event-title{
  margin-top:6px;
  font-size:11px;
  line-height:1.2;
  opacity:.9;
  display:-webkit-box;
  -webkit-box-orient:vertical;
  -webkit-line-clamp:2;
  overflow:hidden;
  word-break:break-word;
}

@media (max-width:520px){
  .vms-av-event-title{font-size:10px}
  /* Optional: hide chips on mobile if you’re still showing them */
  .vms-av-chip{display:none}
  .vms-av-src{display:none}
}

</style>';

        // echo '<div class="vms-av-wrap">'; // replaced 20260114 @ 9:17am
        echo '<div class="vms-av-wrap" id="vms-av" data-today-ym="' . esc_attr(wp_date('Y-m')) . '">';

        // Collapsible: ICS
        $ics_open = ($preferred === 'ics') ? ' open' : '';
        echo '<details class="vms-av-card"' . $ics_open . '>';
        echo '<summary style="cursor:pointer;font-weight:900;">' . esc_html__('Calendar Sync (ICS)', 'vms') . '</summary>';
        echo '<div style="padding-top:12px;">';

        echo '<form method="post" class="vms-av-row" style="margin:0;">';
        wp_nonce_field('vms_ics_settings', 'vms_ics_nonce');

        echo '<div class="field">';
        echo '<label><strong>' . esc_html__('ICS Feed URL', 'vms') . '</strong></label><br>';
        echo '<input type="url" name="vms_ics_url" value="' . esc_attr($ics_url) . '" style="width:100%;">';
        echo '</div>';

        echo '<div class="field" style="flex:0 0 auto;min-width:240px">';
        echo '<label style="display:block;margin-bottom:8px;">';
        echo '<input type="checkbox" name="vms_ics_autosync" value="1" ' . checked(1, $ics_autosync, false) . '> ';
        echo esc_html__('Auto-sync this calendar periodically (optional)', 'vms');
        echo '</label>';

        echo '<div class="vms-av-actions">';
        echo '<button class="button button-primary" type="submit" name="vms_save_ics_settings">' . esc_html__('Save Calendar Settings', 'vms') . '</button>';
        echo '<button class="button" type="submit" name="vms_sync_ics_now">' . esc_html__('Sync Now', 'vms') . '</button>';
        echo '</div>';

        if ($ics_last) {
            echo '<div class="vms-av-muted" style="margin-top:8px;">' . esc_html__('Last sync:', 'vms') . ' ' . esc_html(date_i18n('M j, Y g:ia', $ics_last)) . '</div>';
        }

        echo '</div>'; // field
        echo '</form>';

        echo '</div></details>'; // /ICS

        // Collapsible: Manual
        $manual_open = ($preferred === 'manual') ? ' open' : '';
        echo '<details class="vms-av-card"' . $manual_open . '>';
        echo '<summary style="cursor:pointer;font-weight:900;">' . esc_html__('Manual Availability', 'vms') . '</summary>';
        echo '<div style="padding-top:12px;">';
        echo '<p class="vms-av-help">' . esc_html__('Tap a date to cycle: — → Available → Not Available. Then save.', 'vms') . '</p>';

        echo '<form method="post" id="vms-av-form">';
        wp_nonce_field('vms_save_availability', 'vms_avail_nonce');

        foreach ($months as $ym => $dates_in_month) {
            $month_ts = strtotime($ym . '-01');
            $month_label = $month_ts ? date_i18n('F Y', $month_ts) : $ym;

            $matrix = vms_av_build_month_matrix($ym);

            // stats
            $cnt_na = 0;
            $cnt_a = 0;
            $cnt_active = 0;
            foreach ($dates_in_month as $d) {
                $cnt_active++;
                if (isset($manual[$d]) && $manual[$d] === 'unavailable') $cnt_na++;
                if (isset($manual[$d]) && $manual[$d] === 'available') $cnt_a++;
            }

            // Preload this vendor’s Event Plans for this month (for cell titles)
            // PATCH 20260114 @ 09:19am
            $events_by_date = array();

            $month_start = $ym . '-01';
            $month_end   = gmdate('Y-m-d', strtotime('+1 month', strtotime($month_start))); // exclusive end

            $plans = get_posts(array(
                'post_type'      => 'vms_event_plan',
                'post_status'    => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page' => -1,
                'orderby'        => 'meta_value',
                'meta_key'       => '_vms_event_date',
                'order'          => 'ASC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_vms_event_date',
                        'value'   => $month_start,
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ),
                    array(
                        'key'     => '_vms_event_date',
                        'value'   => $month_end,
                        'compare' => '<',
                        'type'    => 'DATE',
                    ),
                    array(
                        'key'     => '_vms_band_vendor_id',
                        'value'   => (int) $vendor_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ),
                ),
            ));

            foreach ($plans as $p) {
                $d = (string) get_post_meta($p->ID, '_vms_event_date', true);
                if (!$d) continue;

                $title = get_the_title($p->ID);
                if (!$title) $title = __('Booked', 'vms');

                if (!isset($events_by_date[$d])) $events_by_date[$d] = array();
                $events_by_date[$d][] = $title;
            }

            $open = ($ym === $default_open_ym) ? ' open' : '';
            echo '<div class="vms-av-month" data-ym="' . esc_attr($ym) . '">';
            echo '<details' . $open . '>';
            echo '<summary>';
            echo '<span>' . esc_html($month_label) . '</span>';
            echo '<span class="vms-av-muted" style="font-weight:700;">' . esc_html(sprintf('%d active • %d NA • %d A', $cnt_active, $cnt_na, $cnt_a)) . '</span>';
            echo '</summary>';

            echo '<table class="vms-av-grid"><tbody>';

            foreach ($matrix as $week) {
                echo '<tr>';
                foreach ($week as $cell) {
                    $date = $cell['date'];
                    $daynum = $cell['day'];

                    if (!$date) {
                        echo '<td class="vms-av-inactive"></td>';
                        continue;
                    }

                    $is_active = in_array($date, $dates_in_month, true);

                    // Determine “effective” state + source
                    $manual_state = $is_active && isset($manual[$date]) ? (string) $manual[$date] : '';
                    $ics_state    = ($is_active && isset($ics_lookup[$date])) ? 'unavailable' : '';

                    $effective = '';
                    $source = '';
                    if ($manual_state === 'available' || $manual_state === 'unavailable') {
                        $effective = $manual_state;
                        $source = 'manual';
                    } elseif ($ics_state === 'unavailable') {
                        $effective = 'unavailable';
                        $source = 'ics';
                    }

                    $chip = '';
                    if ($effective === 'unavailable') $chip = '<span class="vms-av-chip na">' . esc_html__('Not Available', 'vms') . '</span>';
                    elseif ($effective === 'available') $chip = '<span class="vms-av-chip a">' . esc_html__('Available', 'vms') . '</span>';

                    echo '<td' . ($is_active ? '' : ' class="vms-av-inactive"') . '>';

                    echo '<div class="vms-av-day"><span class="vms-av-daynum">' . esc_html((string) $daynum) . '</span>' . $chip . '</div>';

                    if ($is_active) {
                        // Hidden input that actually gets saved (manual only)
                        $val = $manual_state;
                        echo '<input type="hidden" name="vms_availability[' . esc_attr($date) . ']" value="' . esc_attr($val) . '" data-date="' . esc_attr($date) . '" class="vms-av-hidden">';

                        // Tap target
                        echo '<button type="button" class="vms-av-btn" data-date="' . esc_attr($date) . '" data-state="' . esc_attr($val) . '">';
                        echo '<div class="row"><span class="vms-av-state" data-label="' . esc_attr($date) . '"></span></div>';
                        echo '</button>';

                        // Source label
                        if ($source === 'manual') {
                            echo '<div class="vms-av-src"><span class="m">' . esc_html__('Manual', 'vms') . '</span></div>';
                        } elseif ($source === 'ics') {
                            echo '<div class="vms-av-src"><span class="c">' . esc_html__('Calendar', 'vms') . '</span></div>';
                        }
                    } else {
                        // echo '<div class="vms-av-muted" style="font-size:12px;">' . esc_html__('Not in season', 'vms') . '</div>';
                    }

                    // PATCH 20260114 @ 09:25am
                    if (isset($events_by_date[$date]) && !empty($events_by_date[$date])) {
                        $title = (string) $events_by_date[$date][0];
                        $more  = count($events_by_date[$date]) - 1;

                        $display = $title . ($more > 0 ? ' +' . (int) $more : '');

                        echo '<div class="vms-av-event-title" title="' . esc_attr($display) . '">'
                            . esc_html($display)
                            . '</div>';
                    }

                    echo '</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</details>';
            echo '</div>';
        }

        echo '<p style="margin:14px 0 0;">';
        echo '<button class="button button-primary" type="submit" name="vms_save_availability">' . esc_html__('Save Availability', 'vms') . '</button>';
        echo '</p>';

        echo '</form>';

        $avail_ajax_nonce = wp_create_nonce('vms_avail_ajax');
        echo '<div class="vms-av-autosave" aria-live="polite"></div>';
        echo '<script>
window.VMS_AV = window.VMS_AV || {};
window.VMS_AV.ajaxUrl = ' . wp_json_encode(admin_url('admin-ajax.php')) . ';
window.VMS_AV.nonce   = ' . wp_json_encode($avail_ajax_nonce) . ';
</script>';

        echo '</div></details>'; // /Manual

        // JS: tap-to-toggle
    ?>
        <script>
            (function() {
                document.documentElement.classList.add("vms-js");

                var cfg = window.VMS_AV || {};
                var ajaxUrl = cfg.ajaxUrl || "";
                var nonce = cfg.nonce || "";

                var statusEl = document.querySelector(".vms-av-autosave");

                var pending = 0;
                var failed = 0;
                var dirtyDates = new Set();

                function fullLabel(state) {
                    if (state === "available") return "Available";
                    if (state === "unavailable") return "Not Available";
                    return "Unset";
                }

                function shortLabel(state) {
                    if (state === "available") return "A";
                    if (state === "unavailable") return "NA";
                    return "—";
                }

                function isCompact() {
                    return window.matchMedia && window.matchMedia("(max-width:520px)").matches;
                }

                function setStatus(text) {
                    if (!statusEl) return;
                    statusEl.textContent = text;
                }

                function updateMonthCounts(btn) {
                    var month = btn.closest(".vms-av-month");
                    if (!month) return;

                    var a = month.querySelectorAll('.vms-av-btn[data-state="available"]').length;
                    var na = month.querySelectorAll('.vms-av-btn[data-state="unavailable"]').length;

                    var counts = month.querySelector(".vms-av-counts");
                    if (!counts) return;

                    var active = counts.getAttribute("data-active") || "";
                    if (active) {
                        counts.textContent = active + " active • " + na + " NA • " + a + " A";
                    } else {
                        counts.textContent = na + " NA • " + a + " A";
                    }
                }

                function sync(btn) {
                    var date = btn.getAttribute("data-date");
                    var state = btn.getAttribute("data-state") || "";

                    var hidden = btn.closest("td") ? btn.closest("td").querySelector('input.vms-av-hidden[data-date="' + date + '"]') : null;
                    if (hidden) hidden.value = state;

                    var lab = btn.querySelector('[data-label="' + date + '"]');
                    if (lab) lab.textContent = isCompact() ? shortLabel(state) : fullLabel(state);

                    btn.setAttribute("aria-label", date + ": " + fullLabel(state) + ". Tap to cycle.");
                }

                function post(params) {
                    return fetch(ajaxUrl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                        },
                        body: new URLSearchParams(params).toString(),
                        credentials: "same-origin"
                    }).then(function(r) {
                        return r.json();
                    });
                }

                function saveDay(date, state, btn) {
                    if (!ajaxUrl || !nonce) {
                        failed += 1;
                        setStatus("Save failed. Please reload and try again.");
                        return;
                    }

                    pending += 1;
                    dirtyDates.add(date);
                    setStatus("Saving…");

                    btn.classList.remove("vms-av-save-failed");

                    post({
                            action: "vms_save_manual_availability_day",
                            nonce: nonce,
                            date: date,
                            state: state
                        })
                        .then(function(json) {
                            pending -= 1;

                            if (!json || !json.success) {
                                failed += 1;
                                btn.classList.add("vms-av-save-failed");
                                setStatus("Save failed. Tap again or stay on this page and retry.");
                                return;
                            }

                            dirtyDates.delete(date);
                            updateMonthCounts(btn);

                            if (pending === 0 && failed === 0) setStatus("Saved");
                            if (pending === 0 && failed > 0) setStatus("Some changes failed to save. Stay here and retry.");
                        })
                        .catch(function() {
                            pending -= 1;
                            failed += 1;
                            btn.classList.add("vms-av-save-failed");
                            setStatus("Save failed. Check connection and retry.");
                        });
                }

                window.addEventListener("beforeunload", function(e) {
                    if (pending > 0 || failed > 0) {
                        e.preventDefault();
                        e.returnValue = "";
                        return "";
                    }
                });

                function cycle(state) {
                    if (state === "") return "available";
                    if (state === "available") return "unavailable";
                    return "";
                }

                document.querySelectorAll(".vms-av-btn").forEach(function(btn) {
                    sync(btn);

                    btn.addEventListener("click", function() {
                        var cur = btn.getAttribute("data-state") || "";
                        var next = cycle(cur);
                        var date = btn.getAttribute("data-date");

                        btn.setAttribute("data-state", next);
                        sync(btn);

                        saveDay(date, next, btn);
                    });
                });
            })();
        </script>

// PATCH 20260114 @ 9:35am
<script>
(function () {
  var root = document.getElementById("vms-av");
  if (!root) return;

  var cookieName = "vms_av_open_ym";
  var todayYm = root.getAttribute("data-today-ym") || "";
  var monthEls = Array.prototype.slice.call(root.querySelectorAll(".vms-av-month[data-ym]"));
  if (!monthEls.length) return;

  var byYm = new Map();
  monthEls.forEach(function (m) {
    var ym = m.getAttribute("data-ym") || "";
    var details = m.querySelector("details");
    if (ym && details) byYm.set(ym, details);
  });

  function getCookie(name) {
    var parts = document.cookie.split(";").map(function (c) { return c.trim(); });
    for (var i = 0; i < parts.length; i++) {
      if (parts[i].indexOf(name + "=") === 0) return decodeURIComponent(parts[i].slice(name.length + 1));
    }
    return "";
  }

  function setCookie(name, value, days) {
    var maxAge = (days || 30) * 24 * 60 * 60;
    document.cookie = name + "=" + encodeURIComponent(value) + "; path=/; max-age=" + maxAge + "; samesite=lax";
  }

  function firstYm() {
    for (var k of byYm.keys()) return k;
    return "";
  }

  var preferredYm = getCookie(cookieName);
  var openYm =
    (preferredYm && byYm.has(preferredYm)) ? preferredYm :
    (todayYm && byYm.has(todayYm)) ? todayYm :
    firstYm();

  var programmatic = false;

  function openOnly(ym) {
    if (!ym || !byYm.has(ym)) return;
    programmatic = true;

    byYm.forEach(function (d, key) {
      d.open = (key === ym);
    });

    setCookie(cookieName, ym, 30);
    programmatic = false;
  }

  // Initial state: only one month open
  openOnly(openYm);

  // When user opens a month, close all others and remember it.
  byYm.forEach(function (details, ym) {
    details.addEventListener("toggle", function () {
      if (programmatic) return;

      if (details.open) {
        openOnly(ym);
        return;
      }

      // Prevent “zero months open” state
      programmatic = true;
      details.open = true;
      programmatic = false;
    });
  });
})();
</script>

<?php
        echo '</div>'; // wrap
    }
}

/**
 * Group a list of YYYY-MM-DD dates into [YYYY-MM => [dates. . .]].
 */
if (!function_exists('vms_av_group_dates_by_month')) {
    function vms_av_group_dates_by_month(array $dates): array
    {
        $out = array();
        foreach ($dates as $d) {
            $ym = substr($d, 0, 7);
            if (!isset($out[$ym])) $out[$ym] = array();
            $out[$ym][] = $d;
        }
        ksort($out);
        return $out;
    }
}

/**
 * Build a calendar matrix for a month (YYYY-MM).
 * Week starts Sunday.
 */
if (!function_exists('vms_av_build_month_matrix')) {
    function vms_av_build_month_matrix(string $ym): array
    {
        $first_ts = strtotime($ym . '-01');
        if (!$first_ts) return array();

        $days_in_month = (int) date('t', $first_ts);
        $first_wday    = (int) date('w', $first_ts); // 0=Sun..6=Sat

        $weeks = array();
        $week  = array();

        for ($i = 0; $i < $first_wday; $i++) {
            $week[] = array('date' => null, 'day' => null);
        }

        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%s-%02d', $ym, $day);
            $week[] = array('date' => $date, 'day' => $day);

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = array();
            }
        }

        if (!empty($week)) {
            while (count($week) < 7) {
                $week[] = array('date' => null, 'day' => null);
            }
            $weeks[] = $week;
        }

        return $weeks;
    }
}

/* ==========================================================
 * Tech Docs (procedural) — FIXED (no “. . . .”, no broken echo)
 * ========================================================== */

if (!function_exists('vms_vendor_portal_render_tech_docs')) {
    function vms_vendor_portal_render_tech_docs($vendor_id)
    {
        $vendor_id = (int) $vendor_id;

        echo '<h3>' . esc_html__('Tech Docs', 'vms') . '</h3>';
        echo '<p class="vms-muted">' . esc_html__('Upload your current stage plot and input list (PDF or image). You can replace them any time.', 'vms') . '</p>';

        // Ensure media handling functions are available on front-end
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Handle uploads
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_techdocs_save'])) {
            if (!isset($_POST['vms_techdocs_nonce']) || !wp_verify_nonce($_POST['vms_techdocs_nonce'], 'vms_techdocs_save')) {
                echo vms_portal_notice('error', __('Security check failed.', 'vms'));
            } else {

                $updated = false;

                if (!empty($_FILES['vms_stage_plot']['name'])) {
                    $attach_id = media_handle_upload('vms_stage_plot', 0);
                    if (!is_wp_error($attach_id)) {
                        update_post_meta($vendor_id, '_vms_stage_plot_attachment_id', (int) $attach_id);
                        $updated = true;
                    } else {
                        echo vms_portal_notice('error', sprintf(__('Stage plot upload failed: %s', 'vms'), $attach_id->get_error_message()));
                    }
                }

                if (!empty($_FILES['vms_input_list']['name'])) {
                    $attach_id = media_handle_upload('vms_input_list', 0);
                    if (!is_wp_error($attach_id)) {
                        update_post_meta($vendor_id, '_vms_input_list_attachment_id', (int) $attach_id);
                        $updated = true;
                    } else {
                        echo vms_portal_notice('error', sprintf(__('Input list upload failed: %s', 'vms'), $attach_id->get_error_message()));
                    }
                }

                if ($updated) {
                    vms_vendor_flag_vendor_update($vendor_id, 'tech_docs');
                    echo vms_portal_notice('success', __('Tech docs updated.', 'vms'));
                }
            }
        }

        $stage_id = (int) get_post_meta($vendor_id, '_vms_stage_plot_attachment_id', true);
        $input_id = (int) get_post_meta($vendor_id, '_vms_input_list_attachment_id', true);

        $stage_url = $stage_id ? wp_get_attachment_url($stage_id) : '';
        $input_url = $input_id ? wp_get_attachment_url($input_id) : '';

        echo '<div class="vms-portal-card">';
        echo '<ul style="margin:0;padding-left:18px;">';
        echo '<li><strong>' . esc_html__('Stage Plot:', 'vms') . '</strong> ' . ($stage_url ? '<a target="_blank" rel="noopener" href="' . esc_url($stage_url) . '">' . esc_html__('View current', 'vms') . '</a>' : esc_html__('None uploaded', 'vms')) . '</li>';
        echo '<li><strong>' . esc_html__('Input List:', 'vms') . '</strong> ' . ($input_url ? '<a target="_blank" rel="noopener" href="' . esc_url($input_url) . '">' . esc_html__('View current', 'vms') . '</a>' : esc_html__('None uploaded', 'vms')) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="vms-portal-card">';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('vms_techdocs_save', 'vms_techdocs_nonce');

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Upload / Replace Stage Plot', 'vms') . '</strong></label>';
        echo '<input type="file" name="vms_stage_plot" accept=".pdf,.png,.jpg,.jpeg,.webp">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Upload / Replace Input List', 'vms') . '</strong></label>';
        echo '<input type="file" name="vms_input_list" accept=".pdf,.png,.jpg,.jpeg,.webp">';
        echo '</div>';

        echo '<p style="margin:0;"><button type="submit" name="vms_techdocs_save" class="button button-primary">' . esc_html__('Save Tech Docs', 'vms') . '</button></p>';
        echo '</form>';
        echo '</div>';
    }
}

if (!function_exists('vms_vendor_portal_render_profile')) {
    function vms_vendor_portal_render_profile($vendor_id)
    {
        $vendor_id = (int) $vendor_id;

        if ($vendor_id <= 0) {
            echo vms_portal_notice('error', __('Invalid vendor.', 'vms'));
            return;
        }

        // Ensure media handling functions are available on front-end
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Save handler
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_vendor_profile_save'])) {

            if (!isset($_POST['vms_vendor_profile_nonce']) || !wp_verify_nonce($_POST['vms_vendor_profile_nonce'], 'vms_vendor_profile_save')) {
                echo vms_portal_notice('error', __('Security check failed.', 'vms'));
            } else {

                $contact_name  = isset($_POST['vms_contact_name']) ? sanitize_text_field($_POST['vms_contact_name']) : '';
                $contact_email = isset($_POST['vms_contact_email']) ? sanitize_email($_POST['vms_contact_email']) : '';
                $contact_phone = isset($_POST['vms_contact_phone']) ? sanitize_text_field($_POST['vms_contact_phone']) : '';
                $location      = isset($_POST['vms_location']) ? sanitize_text_field($_POST['vms_location']) : '';
                $epk_url       = isset($_POST['vms_epk_url']) ? esc_url_raw($_POST['vms_epk_url']) : '';
                $social_links  = isset($_POST['vms_social_links']) ? sanitize_textarea_field($_POST['vms_social_links']) : '';

                // Logo upload (sets Vendor featured image)
                if (!empty($_FILES['vms_vendor_logo']['name'])) {
                    $attach_id = media_handle_upload('vms_vendor_logo', 0);
                    if (!is_wp_error($attach_id)) {
                        set_post_thumbnail($vendor_id, (int) $attach_id);
                    } else {
                        echo vms_portal_notice('error', sprintf(__('Logo upload failed: %s', 'vms'), $attach_id->get_error_message()));
                    }
                }

                update_post_meta($vendor_id, '_vms_contact_name', $contact_name);
                update_post_meta($vendor_id, '_vms_contact_email', $contact_email);
                update_post_meta($vendor_id, '_vms_contact_phone', $contact_phone);
                update_post_meta($vendor_id, '_vms_vendor_location', $location);
                update_post_meta($vendor_id, '_vms_vendor_epk', $epk_url);
                update_post_meta($vendor_id, '_vms_vendor_social', $social_links);

                // Flag update for admin review
                if (function_exists('vms_vendor_flag_vendor_update')) {
                    vms_vendor_flag_vendor_update($vendor_id, 'profile');
                }

                echo vms_portal_notice('success', __('Profile saved.', 'vms'));
            }
        }

        // Current values
        $contact_name  = get_post_meta($vendor_id, '_vms_contact_name', true);
        $contact_email = get_post_meta($vendor_id, '_vms_contact_email', true);
        $contact_phone = get_post_meta($vendor_id, '_vms_contact_phone', true);
        $location      = get_post_meta($vendor_id, '_vms_vendor_location', true);
        $epk_url       = get_post_meta($vendor_id, '_vms_vendor_epk', true);
        $social_links  = get_post_meta($vendor_id, '_vms_vendor_social', true);

        $thumb_id  = get_post_thumbnail_id($vendor_id);
        $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';

        echo '<h3>' . esc_html__('Profile', 'vms') . '</h3>';

        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('vms_vendor_profile_save', 'vms_vendor_profile_nonce');

        echo '<div class="vms-portal-card">';

        // LOGO
        echo '<h4 style="margin-top:0;">' . esc_html__('Logo', 'vms') . '</h4>';

        if ($thumb_url) {
            echo '<p><img src="' . esc_url($thumb_url) . '" alt="Vendor Logo" style="max-width:180px;height:auto;border:1px solid #ddd;border-radius:8px;padding:6px;background:#fff;"></p>';
        } else {
            echo '<p><em>' . esc_html__('No logo uploaded yet.', 'vms') . '</em></p>';
        }

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Upload / Replace Logo', 'vms') . '</strong></label>';
        echo '<input type="file" name="vms_vendor_logo" accept=".png,.jpg,.jpeg,.webp,.pdf">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Contact Name', 'vms') . '</strong></label>';
        echo '<input type="text" name="vms_contact_name" value="' . esc_attr($contact_name) . '">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Contact Email', 'vms') . '</strong></label>';
        echo '<input type="email" name="vms_contact_email" value="' . esc_attr($contact_email) . '">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Contact Phone', 'vms') . '</strong></label>';
        echo '<input type="tel" name="vms_contact_phone" id="vms_contact_phone" inputmode="tel" autocomplete="tel" placeholder="(###) ###-####" maxlength="14" value="' . esc_attr((string) $contact_phone) . '">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Home Base (City/State)', 'vms') . '</strong></label>';
        echo '<input type="text" name="vms_location" value="' . esc_attr($location) . '">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('EPK / Website Link', 'vms') . '</strong></label>';
        echo '<input type="url" name="vms_epk_url" value="' . esc_attr($epk_url) . '">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Social Links', 'vms') . '</strong></label>';
        echo '<textarea name="vms_social_links" rows="4" placeholder="' . esc_attr__('Instagram, Facebook, Spotify, YouTube&hellip;', 'vms') . '">' . esc_textarea($social_links) . '</textarea>';
        echo '</div>';

        echo '<p style="margin:0;"><button type="submit" name="vms_vendor_profile_save" class="button button-primary">' . esc_html__('Save Profile', 'vms') . '</button></p>';

        echo '</div>'; // card
        echo '</form>';

        // Phone mask (same as before) 
        echo '<script>
(function () {
  var el = document.getElementById("vms_contact_phone");
  if (!el) return;

  function formatPhone(value) {
    var digits = (value || "").replace(/\\D/g, "").slice(0, 10);
    var len = digits.length;

    if (len === 0) return "";
    if (len < 4) return "(" + digits;
    if (len < 7) return "(" + digits.slice(0,3) + ") " + digits.slice(3);
    return "(" + digits.slice(0,3) + ") " + digits.slice(3,6) + "-" + digits.slice(6);
  }

  el.addEventListener("input", function () {
    var start = el.selectionStart || 0;
    var before = el.value || "";

    el.value = formatPhone(before);

    if (document.activeElement === el) {
      var after = el.value || "";
      var diff = after.length - before.length;
      var pos = Math.max(0, start + diff);
      try { el.setSelectionRange(pos, pos); } catch(e) {}
    }
  });

  el.addEventListener("blur", function () {
    el.value = formatPhone(el.value);
  });

  el.value = formatPhone(el.value);
})();
</script>';
    }
}

add_action('wp_ajax_vms_save_manual_availability_day', 'vms_save_manual_availability_day_ajax');

function vms_save_manual_availability_day_ajax(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Not logged in.'), 403);
    }

    check_ajax_referer('vms_avail_ajax', 'nonce');

    $user_id   = (int) get_current_user_id();
    $vendor_id = (int) get_user_meta($user_id, '_vms_vendor_id', true);

    if ($vendor_id <= 0 || get_post_type($vendor_id) !== 'vms_vendor') {
        wp_send_json_error(array('message' => 'Vendor not linked.'), 400);
    }

    $date  = sanitize_text_field((string) ($_POST['date'] ?? ''));
    $state = sanitize_text_field((string) ($_POST['state'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(array('message' => 'Invalid date.'), 400);
    }

    if (!in_array($state, array('', 'available', 'unavailable'), true)) {
        wp_send_json_error(array('message' => 'Invalid state.'), 400);
    }

    $active_dates  = function_exists('vms_vendor_get_active_dates_or_rolling_window')
        ? vms_vendor_get_active_dates_or_rolling_window(12)
        : array();

    $active_lookup = array_flip($active_dates);
    if (!isset($active_lookup[$date])) {
        wp_send_json_error(array('message' => 'Date not in active range.'), 400);
    }

    $manual = get_post_meta($vendor_id, '_vms_availability_manual', true);
    if (!is_array($manual)) $manual = array();

    if ($state === '') {
        unset($manual[$date]);
    } else {
        $manual[$date] = $state;
    }

    update_post_meta($vendor_id, '_vms_availability_manual', $manual);
    update_post_meta($vendor_id, '_vms_availability_preferred_method', 'manual');

    if (function_exists('vms_vendor_flag_vendor_update')) {
        vms_vendor_flag_vendor_update($vendor_id, 'availability_manual');
    }

    wp_send_json_success(array(
        'date'  => $date,
        'state' => $state
    ));
}
