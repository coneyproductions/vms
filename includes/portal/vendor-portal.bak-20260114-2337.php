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
    $months_ahead = (int) $months_ahead;
    if ($months_ahead < 1) $months_ahead = 12;
    if ($months_ahead > 24) $months_ahead = 24;

    // Season dates (if defined)
    $season = function_exists('vms_get_active_season_dates') ? (array) vms_get_active_season_dates() : array();
    $season = array_values(array_filter(array_map('sanitize_text_field', $season)));

    // Rolling window dates (always include so ICS can evaluate what the UI shows)
    $tz = wp_timezone();
    $start = new DateTime('today', $tz);
    $end   = (clone $start)->modify('+' . $months_ahead . ' months');

    $rolling = array();
    $cur = clone $start;
    while ($cur < $end) {
        $rolling[] = $cur->format('Y-m-d');
        $cur->modify('+1 day');
    }

    // Union
    $all = array_values(array_unique(array_merge($rolling, $season)));
    sort($all);
    return $all;
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
/* =========================================================
   Notices + Portal base
   ========================================================= */
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

/* =========================================================
   Availability grid: source icon + booked styling
   ========================================================= */
.vms-av-grid .vms-av-btn{position:relative}

.vms-av-grid .vms-av-src{
  position:absolute;
  top:6px;
  right:6px;
  font-size:12px;
  line-height:1;
  opacity:.75;
  pointer-events:none;
}

.vms-av-badge-booked{
  font-size:10px;
  font-weight:900;
  padding:2px 6px;
  border-radius:999px;
  background:#eff6ff;
  color:#1e3a8a;
}

/* Booked should always win visually */
.vms-av-grid .vms-av-btn[data-src="booked"]{
  border-color:#2563eb !important;
  background:#eff6ff !important;
  color:#1e3a8a !important;
}
.vms-av-grid .vms-av-btn[data-src="booked"] .vms-av-state{
  text-transform:uppercase;
  font-size:12px;
  letter-spacing:.02em;
}

/* =========================================================
   Method accordions (ICS / Pattern / Manual)
   ========================================================= */
details.vms-av-method{
  border:1px solid #e5e5e5;
  border-radius:12px;
  background:#fff;
  margin:0 0 12px;
  overflow:hidden;
}

.vms-av-method summary{
  cursor:pointer;
  list-style:none;
  padding:12px 14px;
  font-weight:800;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
}
.vms-av-method summary::-webkit-details-marker{display:none}

/* Summary title clamps nicely */
.vms-av-method summary > span:first-child{
  flex:1 1 auto;
  min-width:0;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis; /* renders “…” */
}

/* Meta always stays on one line */
.vms-av-summarymeta{
  font-weight:700;
  font-size:12px;
  opacity:.65;
  flex:0 0 auto;
  white-space:nowrap;
  margin-left:12px;
}

/* Body padding */
details.vms-av-method > :not(summary){
  padding:14px;
}

/* If a method body contains a nested card, remove double padding */
details.vms-av-method > :not(summary) .vms-av-card{padding:0}
details.vms-av-method .vms-av-card{border:none;margin:0}

/* =========================================================
   ICS form tweaks (desktop + responsive)
   ========================================================= */
details.vms-av-method[data-method="ics"] .vms-field input{max-width:100% !important}

details.vms-av-method[data-method="ics"] .vms-av-actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  align-items:center;
}
details.vms-av-method[data-method="ics"] .vms-av-actions .button{
  flex:1 1 220px; /* allows 2-up on wider, stacked on small */
  min-width:0;
}

details.vms-av-method[data-method="ics"] .vms-av-last-sync{display:none}

details.vms-av-method[data-method="ics"] label{
  display:flex;
  gap:8px;
  align-items:flex-start;
  flex-wrap:wrap;
}
details.vms-av-method[data-method="ics"] label input[type="checkbox"]{margin-top:3px}

/* ICS URL input sizing */
details.vms-av-method[data-method="ics"] .field input[type="url"]{
  width:100% !important;
  max-width:520px;
  font-size:12px;
  padding:8px 10px;
}

/* =========================================================
   Mobile polish
   ========================================================= */
@media (max-width:520px){

  /* Availability: hide source icon inside the button (noisy on mobile) */
  .vms-av-grid .vms-av-btn .vms-av-src{display:none !important;}

  .vms-av-grid td{padding:8px !important;}

  .vms-av-grid .vms-av-btn{
    border-radius:999px !important;
    padding:6px 0 !important;
    min-height:34px !important;
  }

  .vms-av-grid .vms-av-btn .vms-av-state{
    font-size:15px !important;
    line-height:1 !important;
    white-space:nowrap !important;
  }

  .vms-av-event-title{
    margin-top:4px !important;
    font-size:10px !important;
    line-height:1.15 !important;
    display:block !important;
    white-space:nowrap !important;
    overflow:hidden !important;
    text-overflow:ellipsis !important; /* single ellipsis glyph */
    word-break:normal !important;
    overflow-wrap:normal !important;
  }

  /* ICS: stack cleanly */
  details.vms-av-method[data-method="ics"] .vms-av-row{
    flex-direction:column;
    align-items:stretch;
  }
  details.vms-av-method[data-method="ics"] .vms-av-row > .field{
    min-width:0 !important;
    flex:1 1 auto !important;
    width:100% !important;
  }
  details.vms-av-method[data-method="ics"] .vms-av-actions{width:100%}
  details.vms-av-method[data-method="ics"] .vms-av-actions .button{
    width:100% !important;
    display:block;
  }
}

/* Dashboard layout */
.vms-dash-grid{display:grid;grid-template-columns:2fr 1fr;gap:12px}
@media (max-width:820px){.vms-dash-grid{grid-template-columns:1fr}}

.vms-dash-kpis{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 0}
.vms-dash-kpi{border:1px solid #eee;border-radius:12px;padding:10px 12px;min-width:160px;background:#fff}
.vms-dash-kpi b{display:block;font-size:12px;opacity:.7;margin:0 0 4px}
.vms-dash-kpi span{font-weight:900}

.vms-dash-list{margin:10px 0 0;padding-left:18px}
.vms-dash-list li{margin:6px 0}
.vms-dash-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}


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

        $tz   = wp_timezone();
        $now  = (int) current_time('timestamp');
        $today = wp_date('Y-m-d', $now, $tz);

        // ------------------------------------------------------
        // Upcoming bookings (Event Plans)
        // ------------------------------------------------------
        $upcoming = get_posts(array(
            'post_type'      => 'vms_event_plan',
            'post_status'    => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => 5,
            'orderby'        => 'meta_value',
            'meta_key'       => '_vms_event_date',
            'order'          => 'ASC',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_vms_event_date',
                    'value'   => $today,
                    'compare' => '>=',
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

        $next_booking = !empty($upcoming) ? $upcoming[0] : null;

        // ------------------------------------------------------
        // Availability setup status
        // ------------------------------------------------------
        $ics_url      = (string) get_post_meta($vendor_id, '_vms_ics_url', true);
        $ics_autosync = (int) get_post_meta($vendor_id, '_vms_ics_autosync', true);
        $ics_last     = (int) get_post_meta($vendor_id, '_vms_ics_last_sync', true);

        $ics_enabled  = !empty($ics_url);
        $ics_last_h   = $ics_last ? wp_date('M j, Y g:ia', $ics_last, $tz) : '';
        $ics_stale_days = ($ics_enabled && $ics_last) ? (int) floor(($now - $ics_last) / DAY_IN_SECONDS) : 0;

        // Pattern meta (assumes you added these keys)
        $pattern_enabled = (int) get_post_meta($vendor_id, '_vms_pattern_enabled', true);
        $pattern_days    = get_post_meta($vendor_id, '_vms_pattern_days', true);
        if (!is_array($pattern_days)) $pattern_days = array();
        $pattern_days = array_values(array_unique(array_filter(array_map('intval', $pattern_days), function ($d) {
            return $d >= 0 && $d <= 6;
        })));
        sort($pattern_days);
        if (empty($pattern_days)) $pattern_enabled = 0;

        $dow_labels = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
        $pattern_label = 'Off';
        if ($pattern_enabled) {
            $picked = array();
            foreach ($pattern_days as $d) {
                if (isset($dow_labels[$d])) $picked[] = $dow_labels[$d];
            }
            $pattern_label = 'On' . (!empty($picked) ? ' · ' . implode(', ', $picked) : '');
        }

        // Manual overrides count
        $manual = get_post_meta($vendor_id, '_vms_availability_manual', true);
        if (!is_array($manual)) $manual = array();

        $manual_future = 0;
        foreach ($manual as $d => $state) {
            if (!is_string($d)) continue;
            if ($d >= $today) $manual_future++;
        }

        // ------------------------------------------------------
        // Action Needed signals
        // ------------------------------------------------------
        $needs_av_setup = (!$ics_enabled && !$pattern_enabled && empty($manual));
        $needs_ics_sync = ($ics_enabled && $ics_last && $ics_stale_days >= 7);

        echo '<div class="vms-dash-grid">';

        // LEFT: Bookings
        echo '<div>';

        echo '<div class="vms-portal-card">';
        echo '<h3 style="margin:0 0 6px;">' . esc_html__('Next Booking', 'vms') . '</h3>';

        if ($next_booking) {
            $d = (string) get_post_meta($next_booking->ID, '_vms_event_date', true);
            $t = (string) get_post_meta($next_booking->ID, '_vms_start_time', true);

            $time_label = '';
            if ($d && $t) {
                $time_label = '';
                if ($d && $t) {
                    try {
                        $dt = new DateTimeImmutable(trim($d . ' ' . $t), $tz);
                        $time_label = $dt->format('g:ia');
                    } catch (Exception $e) {
                        $time_label = '';
                    }
                }
            }

            $date_label = $d ? wp_date('D, M j, Y', strtotime($d), $tz) : '';
            echo '<div style="font-weight:900;font-size:18px;">' . esc_html($date_label) . '</div>';
            if ($time_label) {
                echo '<div class="vms-muted" style="margin-top:2px;">' . esc_html__('Set time:', 'vms') . ' ' . esc_html($time_label) . '</div>';
            }
            echo '<div class="vms-muted" style="margin-top:8px;">' . esc_html__('Event Plan:', 'vms') . ' ' . esc_html(get_the_title($next_booking)) . '</div>';
        } else {
            echo '<p class="vms-muted" style="margin:0;">' . esc_html__('No upcoming bookings found yet.', 'vms') . '</p>';
        }

        echo '</div>';

        echo '<div class="vms-portal-card">';
        echo '<h3 style="margin:0 0 6px;">' . esc_html__('Upcoming Bookings', 'vms') . '</h3>';

        if (!empty($upcoming)) {
            echo '<ul class="vms-dash-list">';
            foreach ($upcoming as $p) {
                $d = (string) get_post_meta($p->ID, '_vms_event_date', true);
                $t = (string) get_post_meta($p->ID, '_vms_start_time', true);

                $date_label = $d ? wp_date('M j', strtotime($d), $tz) : '';
                $time_label = '';
                if ($d && $t) {
                    $time_label = '';
                    if ($d && $t) {
                        try {
                            $dt = new DateTimeImmutable(trim($d . ' ' . $t), $tz);
                            $time_label = $dt->format('g:ia');
                        } catch (Exception $e) {
                            $time_label = '';
                        }
                    }
                }

                $line = trim($date_label . ($time_label ? ' @ ' . $time_label : ''));
                echo '<li><strong>' . esc_html($line) . '</strong> <span class="vms-muted">— ' . esc_html(get_the_title($p)) . '</span></li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="vms-muted" style="margin:0;">' . esc_html__('Nothing scheduled yet.', 'vms') . '</p>';
        }

        echo '<div class="vms-dash-actions">';
        echo '<a class="button button-primary" href="' . esc_url($url_availability) . '">' . esc_html__('Update Availability', 'vms') . '</a>';
        echo '<a class="button" href="' . esc_url($url_profile) . '">' . esc_html__('Edit Profile', 'vms') . '</a>';
        echo '</div>';

        echo '</div>'; // bookings card

        echo '</div>'; // left col

        // RIGHT: Status + Actions needed
        echo '<div>';

        echo '<div class="vms-portal-card">';
        echo '<h3 style="margin:0 0 6px;">' . esc_html__('Availability Setup', 'vms') . '</h3>';

        echo '<div class="vms-dash-kpis">';
        echo '<div class="vms-dash-kpi"><b>' . esc_html__('Manual', 'vms') . '</b><span>' . esc_html($manual_future) . '</span> <span class="vms-muted">' . esc_html__('future overrides', 'vms') . '</span></div>';

        $ics_label = $ics_enabled ? 'On' : 'Off';
        if ($ics_enabled && $ics_last_h) $ics_label .= ' · ' . $ics_last_h;
        echo '<div class="vms-dash-kpi"><b>' . esc_html__('ICS', 'vms') . '</b><span>' . esc_html($ics_label) . '</span></div>';

        echo '<div class="vms-dash-kpi"><b>' . esc_html__('Pattern', 'vms') . '</b><span>' . esc_html($pattern_label) . '</span></div>';
        echo '</div>';

        echo '<div class="vms-muted" style="margin-top:10px;">';
        echo esc_html__('Priority order:', 'vms') . ' ';
        echo esc_html__('Manual overrides Pattern, and Pattern overrides ICS.', 'vms');
        echo '</div>';

        echo '</div>';

        echo '<div class="vms-portal-card">';
        echo '<h3 style="margin:0 0 6px;">' . esc_html__('Action Needed', 'vms') . '</h3>';

        $any_actions = false;

        if ($needs_av_setup) {
            $any_actions = true;
            echo vms_portal_notice('warning', __('You have not set up availability yet. Enable Pattern, connect ICS, or set a few manual dates.', 'vms'));
        }

        if ($needs_ics_sync) {
            $any_actions = true;
            echo vms_portal_notice('warning', sprintf(__('Your calendar sync is %d days old. Open Availability and tap “Sync Now”.', 'vms'), $ics_stale_days));
        }

        if (!$any_actions) {
            echo '<p class="vms-muted" style="margin:0;">' . esc_html__('You’re all set.', 'vms') . '</p>';
        }

        echo '<div class="vms-dash-actions">';
        echo '<a class="button" href="' . esc_url($url_availability) . '#ics">' . esc_html__('Calendar Sync (ICS)', 'vms') . '</a>';
        echo '<a class="button" href="' . esc_url($url_tax_profile) . '">' . esc_html__('Tax Profile', 'vms') . '</a>';
        echo '</div>';

        echo '</div>';

        echo '</div>'; // right col

        echo '</div>'; // grid

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
        $ics_meta = '';
        if (!empty($ics_url)) {
            $ics_meta = __('Enabled', 'vms');
            if (!empty($ics_last)) {
                $ics_meta .= ' · ' . __('Last sync', 'vms') . ' ' . wp_date('M j, Y g:ia', (int)$ics_last, wp_timezone());
            }
        } else {
            $ics_meta = __('Not set', 'vms');
        }

        // Optional: dates marked unavailable from ICS sync module
        $ics_unavailable = get_post_meta($vendor_id, '_vms_ics_unavailable', true);
        if (!is_array($ics_unavailable)) $ics_unavailable = array();

        // Backward/alternate storage support:
        // - if it's a map (date => 'unavailable'), use keys
        // - if it's empty, fall back to the canonical ICS layer meta (also a map)
        $is_list = (array_keys($ics_unavailable) === range(0, max(0, count($ics_unavailable) - 1)));
        if (!$is_list && !empty($ics_unavailable)) {
            $ics_unavailable = array_keys($ics_unavailable);
        } elseif (empty($ics_unavailable)) {
            $ics_layer = get_post_meta($vendor_id, '_vms_availability_ics', true);
            if (is_array($ics_layer) && !empty($ics_layer)) {
                $ics_unavailable = array_keys($ics_layer);
            }
        }

        $ics_unavailable = array_values(array_unique(array_filter(array_map('sanitize_text_field', $ics_unavailable))));
        $ics_lookup = array_fill_keys($ics_unavailable, true);
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

            // 2b) Save Pattern Availability
            if (isset($_POST['vms_save_pattern'])) {
                if (
                    !isset($_POST['vms_pattern_nonce']) ||
                    !wp_verify_nonce($_POST['vms_pattern_nonce'], 'vms_pattern_settings')
                ) {
                    echo vms_portal_notice('error', __('Security check failed.', 'vms'));
                } else {
                    $enabled = !empty($_POST['vms_pattern_enabled']) ? 1 : 0;

                    $days = array();
                    if (isset($_POST['vms_pattern_days']) && is_array($_POST['vms_pattern_days'])) {
                        foreach ($_POST['vms_pattern_days'] as $d) {
                            $d = (int) $d;
                            if ($d >= 0 && $d <= 6) $days[] = $d;
                        }
                    }
                    $days = array_values(array_unique($days));

                    if (!$enabled) $days = array();

                    update_post_meta($vendor_id, '_vms_pattern_enabled', $enabled);
                    update_post_meta($vendor_id, '_vms_pattern_days', $days);

                    update_post_meta($vendor_id, '_vms_availability_preferred_method', 'pattern');
                    $preferred = 'pattern';

                    vms_vendor_flag_vendor_update($vendor_id, 'availability_pattern');

                    echo vms_portal_notice('success', __('Pattern availability saved.', 'vms'));
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
                                $raw = $result['ics_unavailable'];

                                // Accept either:
                                // - list: ['YYYY-MM-DD', 'YYYY-MM-DD']
                                // - map:  ['YYYY-MM-DD' => 'unavailable']
                                $raw_is_list = (array_keys($raw) === range(0, max(0, count($raw) - 1)));
                                if (!$raw_is_list && !empty($raw)) {
                                    $raw = array_keys($raw);
                                }

                                $ics_unavailable = array_values(array_unique(array_filter(array_map('sanitize_text_field', $raw))));
                                update_post_meta($vendor_id, '_vms_ics_unavailable', $ics_unavailable);
                                $ics_lookup = array_fill_keys($ics_unavailable, true);
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
/* =========================================================
   VMS Vendor Portal – Availability (consolidated CSS)
   ========================================================= */

.vms-av-wrap{max-width:980px}
.vms-av-card{background:#fff;border:1px solid #e5e5e5;border-radius:14px;padding:14px;margin:0 0 14px}
.vms-av-row{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.vms-av-row .field{min-width:260px;flex:1}
.vms-av-actions{display:flex;gap:10px;flex-wrap:wrap}
.vms-av-muted{opacity:.8}
.vms-av-help{font-size:12px;opacity:.8;margin:10px 0 0}

.vms-av-month details{border:1px solid #e5e5e5;border-radius:12px;background:#fff;margin:0 0 12px;overflow:hidden}
.vms-av-month summary{cursor:pointer;list-style:none;padding:12px 14px;font-weight:800;display:flex;justify-content:space-between;align-items:center}
.vms-av-month summary::-webkit-details-marker{display:none}
.vms-av-month summary{font-weight:800}

/* =========================================================
   Calendar grid – true 7-column table, always
   ========================================================= */
.vms-av-grid{
  width:100%;
  table-layout:fixed;
  border-collapse:separate;
  border-spacing:0;
  display:table;
}

.vms-av-grid thead{display:table-header-group}
.vms-av-grid tbody{display:table-row-group}
.vms-av-grid tr{display:table-row}

.vms-av-grid th,
.vms-av-grid td{
  display:table-cell;
  width:14.2857%;
  max-width:14.2857%;
  vertical-align:top;
  min-width:0;
  box-sizing:border-box;
}

.vms-av-grid th{
  font-size:12px;
  letter-spacing:.02em;
  text-transform:uppercase;
  opacity:.75;
  padding:10px;
  border-top:1px solid #eee;
  text-align:left;
}

.vms-av-grid td{
  border-top:1px solid #eee;
  border-right:1px solid #eee;
  padding:8px;
  overflow:hidden; /* prevents bleed into neighbor cells */
}

.vms-av-grid td:last-child{border-right:none}

.vms-av-inactive{background:#fafafa;opacity:.55}
.vms-av-day{display:flex;justify-content:space-between;gap:8px;align-items:center;margin:0 0 8px}
.vms-av-daynum{font-weight:900}

/* =========================================================
   Tap-to-cycle button (manual state stored, visual state shown)
   ========================================================= */
.vms-av-grid .vms-av-btn{
  appearance:none;
  -webkit-appearance:none;
  position:relative; /* needed for icon positioning */
  display:block;
  width:100%;
  max-width:100%;
  box-sizing:border-box;
  cursor:pointer;
  text-align:center;
  border:1px solid #e5e5e5;
  border-radius:12px;
  padding:10px;
  background:#fff;
  color:#111827;
  box-shadow:none;
  font:inherit;
  line-height:1.15;
}

.vms-av-grid .vms-av-btn:active{transform:scale(.99)}

/* Prefer data-visual, fall back to data-state */
.vms-av-grid .vms-av-btn[data-visual="available"],
.vms-av-grid .vms-av-btn[data-state="available"]{
  border-color:#16a34a;
  background:#f0fdf4;
}

.vms-av-grid .vms-av-btn[data-visual="unavailable"],
.vms-av-grid .vms-av-btn[data-state="unavailable"]{
  border-color:#ef4444;
  background:#fef2f2;
}

/* Label layout */
.vms-av-grid .vms-av-btn .row{
  display:flex;
  align-items:center;
  justify-content:center;
  min-width:0;
}

.vms-av-grid .vms-av-btn .vms-av-state{
  font-weight:900;
  font-size:13px;
  display:block;
  min-width:0;
}

/* Desktop: prevent label from sitting under the source icon */
@media (min-width:521px){
  .vms-av-grid .vms-av-btn{padding-right:30px}

  .vms-av-grid .vms-av-btn .vms-av-state{
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis; /* browser renders the single glyph automatically */
  }
}

/* Source icon inside button (only shown when not manual) */
.vms-av-grid .vms-av-btn .vms-av-src{
  position:absolute;
  top:50%;
  right:10px;
  transform:translateY(-50%);
  font-size:11px;
  line-height:1;
  opacity:.65;
  pointer-events:none;
}

/* Event label under the button (clamped) */
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

.vms-av-event-title span{display:block}

/* Autosave status line */
.vms-av-autosave{
  font-size:12px;
  opacity:.85;
  margin:8px 0 0;
  min-height:16px;
}

/* Touch devices: slightly bigger tap target */
@media (hover:none) and (pointer:coarse){
  .vms-av-grid .vms-av-btn{min-height:44px}
  .vms-av-grid .vms-av-btn .vms-av-state{font-size:14px}
}

/* Small screens: tighter spacing, hide redundant chips if they exist */
@media (max-width:520px){
  .vms-av-grid td{padding:10px}
  .vms-av-grid .vms-av-btn{padding:8px}
  .vms-av-grid .vms-av-state{font-size:14px;font-weight:900}
}

</style>';

        // echo '<div class="vms-av-wrap">'; // replaced 20260114 @ 9:17am
        echo '<div class="vms-av-wrap" id="vms-av" data-today-ym="' . esc_attr(wp_date('Y-m')) . '">';

        // Collapsible: ICS
        $ics_open = ($preferred === 'ics') ? ' open' : '';
        echo '<details class="vms-av-method" data-method="ics" ' . $ics_open . '>';
        echo '<summary>';
        echo '<span>Calendar Sync (ICS)</span>';
        echo '<span class="vms-av-summarymeta" data-summarymeta="ics">' . esc_html($ics_meta) . '</span>';
        echo '</summary>';
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

        // if ($ics_last) {
        //     echo '<div class="vms-av-muted" style="margin-top:8px;">' . esc_html__('Last sync:', 'vms') . ' ' . esc_html(wp_date('M j, Y g:ia', $ics_last, wp_timezone())) . '</div>';
        // }

        echo '</div>'; // field
        echo '</form>';

        echo '</div></details>'; // /ICS

        // Collapsible: Pattern
        $pattern_open = ($preferred === 'pattern') ? ' open' : '';

        $pattern_enabled = (int) get_post_meta($vendor_id, '_vms_pattern_enabled', true);

        $pattern_days = get_post_meta($vendor_id, '_vms_pattern_days', true);
        if (!is_array($pattern_days)) $pattern_days = array();

        // Normalize: ints 0–6 only, unique, sorted
        $pattern_days = array_values(array_unique(array_filter(array_map('intval', $pattern_days), function ($d) {
            return $d >= 0 && $d <= 6;
        })));
        sort($pattern_days);

        $pattern_meta = __('Off', 'vms');
        if ($pattern_enabled && !empty($pattern_days)) {
            $labels = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
            $picked = array();
            foreach ($pattern_days as $d) {
                if (isset($labels[(int)$d])) $picked[] = $labels[(int)$d];
            }
            $pattern_meta = __('Enabled', 'vms') . ' · ' . implode(', ', $picked);
        }

        // If none selected, treat as disabled
        if (empty($pattern_days)) $pattern_enabled = 0;

        echo '<details class="vms-av-method" data-method="pattern"' . $pattern_open . '>';
        echo '<summary>';
        echo '<span>Pattern Availability</span>';
        echo '<span class="vms-av-summarymeta" data-summarymeta="pattern">' . esc_html($pattern_meta) . '</span>';
        echo '</summary>';
        echo '<div class="vms-av-card" style="border:none;margin:0;padding:14px">';

        echo '<p class="vms-av-muted" style="margin:0 0 10px">';
        echo esc_html__('Choose the days you’re usually available. All other days will be marked Not Available.', 'vms');
        echo '<br>';
        echo esc_html__('You can still tap any date in the calendar to override.', 'vms');
        echo '</p>';

        echo '<form method="post">';
        wp_nonce_field('vms_pattern_settings', 'vms_pattern_nonce');

        echo '<label style="display:flex;gap:8px;align-items:center;margin:0 0 12px">';
        echo '<input type="checkbox" name="vms_pattern_enabled" value="1" ' . checked(1, $pattern_enabled, false) . '>';
        echo '<strong>' . esc_html__('Enable pattern availability', 'vms') . '</strong>';
        echo '</label>';

        $dows = array(
            0 => __('Sun', 'vms'),
            1 => __('Mon', 'vms'),
            2 => __('Tue', 'vms'),
            3 => __('Wed', 'vms'),
            4 => __('Thu', 'vms'),
            5 => __('Fri', 'vms'),
            6 => __('Sat', 'vms'),
        );

        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 12px">';
        foreach ($dows as $i => $lbl) {
            $is_checked = in_array((int) $i, array_map('intval', $pattern_days), true);
            echo '<label style="display:flex;gap:6px;align-items:center">';
            echo '<input type="checkbox" name="vms_pattern_days[]" value="' . esc_attr($i) . '" ' . checked(true, $is_checked, false) . '>';
            echo '<span>' . esc_html($lbl) . '</span>';
            echo '</label>';
        }
        echo '</div>';

        echo '<button class="button button-primary" type="submit" name="vms_save_pattern">';
        echo esc_html__('Save Pattern', 'vms');
        echo '</button>';

        echo '</form>';
        echo '</div></details>';

        // Collapsible: Manual
        $manual_open = ($preferred === 'manual') ? ' open' : '';
        echo '<details class="vms-av-method" data-method="manual"' . $manual_open . '>';
        echo '<summary>';
        echo '<span>Manual Availability</span>';
        echo '<span class="vms-av-summarymeta" data-summarymeta="manual"></span>';
        echo '</summary>';
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
            $events_by_date  = array();
            $booked_by_date  = array(); // dates where this vendor is booked on an event plan

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
                $booked_by_date[$d] = true;

                if (!$d) continue;

                $band_id   = (int) get_post_meta($p->ID, '_vms_band_vendor_id', true);
                $headliner = $band_id ? get_the_title($band_id) : '';
                if (!$headliner) $headliner = __('Booked', 'vms');

                $time_label = '';
                $start_time = (string) get_post_meta($p->ID, '_vms_start_time', true);

                if ($start_time) {
                    try {
                        $dt = new DateTime($date . ' ' . $start_time, wp_timezone());
                        $time_label = $dt->format('g:ia'); // 7:00pm
                    } catch (Exception $e) {
                        $time_label = '';
                    }
                }

                $label = $headliner . ($time_label ? ' @ ' . $time_label : '');

                if (!isset($events_by_date[$d])) $events_by_date[$d] = array();
                $events_by_date[$d][] = $label;
            }

            $open = ($ym === $default_open_ym) ? ' open' : '';
            echo '<div class="vms-av-month" data-ym="' . esc_attr($ym) . '">';
            echo '<details' . $open . '>';
            echo '<summary>';
            echo '<span>' . esc_html($month_label) . '</span>';
            echo '<span class="vms-av-muted" style="font-weight:700;">' . esc_html(sprintf('%d active • %d NA • %d A', $cnt_active, $cnt_na, $cnt_a)) . '</span>';
            echo '</summary>';

            echo '<table class="vms-av-grid">';
            echo '<thead><tr class="vms-av-dow">';
            $dow = array(
                __('Sun', 'vms'),
                __('Mon', 'vms'),
                __('Tue', 'vms'),
                __('Wed', 'vms'),
                __('Thu', 'vms'),
                __('Fri', 'vms'),
                __('Sat', 'vms')
            );
            foreach ($dow as $lbl) {
                echo '<th scope="col">' . esc_html($lbl) . '</th>';
            }
            echo '</tr></thead><tbody>';

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

                    // Pattern layer (if you don’t have it wired yet, keep it blank for now)
                    $pattern_state = ''; // 'unavailable' when pattern blocks this weekday


                    if ($is_active && $pattern_enabled && !empty($pattern_days)) {
                        try {
                            $dt  = new DateTime($date . ' 12:00:00', wp_timezone());
                            $dow = (int) $dt->format('w'); // 0=Sun … 6=Sat

                            if (!in_array($dow, $pattern_days, true)) {
                                $pattern_state = 'unavailable';
                            }
                        } catch (Exception $e) {
                            $pattern_state = '';
                        }
                    }

                    $ics_state = ($is_active && isset($ics_lookup[$date])) ? 'unavailable' : '';

                    // If you already compute “booked” for that date, keep using it
                    $booked = isset($booked_lookup[$date]) && $booked_lookup[$date];

                    $is_booked = !empty($booked_by_date[$date]);

                    $base_state = '';
                    $base_src   = '';

                    if ($is_booked) {
                        $base_state = 'unavailable';
                        $base_src   = 'booked';
                    } elseif ($pattern_state === 'unavailable') {
                        $base_state = 'unavailable';
                        $base_src   = 'pattern';
                    } elseif (!empty($ics_lookup[$date])) {
                        $base_state = 'unavailable';
                        $base_src   = 'ics';
                    } else {
                        $base_state = '';
                        $base_src   = '';
                    }

                    $visual_state = ($manual_state === '') ? $base_state : $manual_state;

                    // Source shown in the UI: manual wins, otherwise baseline source
                    $src = ($manual_state === '') ? $base_src : 'manual';


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
                    if (!empty($booked)) {
                        echo '<span class="vms-av-badge-booked">Booked!!</span>';
                    }

                    echo '<div class="vms-av-day"><span class="vms-av-daynum">' . esc_html((string) $daynum) . '</span></div>';

                    if ($is_active) {
                        // Hidden input that actually gets saved (manual only)
                        $val = $manual_state;
                        echo '<input type="hidden"
  name="vms_availability[' . esc_attr($date) . ']"
  value="' . esc_attr($manual_state) . '"
  data-date="' . esc_attr($date) . '"
  class="vms-av-hidden">';

                        echo '<button type="button"
  class="vms-av-btn"
  data-date="' . esc_attr($date) . '"
  data-state="' . esc_attr($manual_state) . '"
  data-base="' . esc_attr($base_state) . '"
  data-base-src="' . esc_attr($base_src) . '"
  data-visual="' . esc_attr($visual_state) . '"
  data-src="' . esc_attr($src) . '"'
                            . (!empty($booked) ? ' disabled aria-disabled="true"' : '')
                            . '>';

                        echo '<div class="row"><span class="vms-av-state" data-label="' . esc_attr($date) . '"></span></div>';

                        // Show source icon only when NOT manual (and only when there is a source)
                        if ($manual_state === '' && $base_src !== '') {
                            // Easy find/replace later if you want different icons
                            $icon = ($base_src === 'ics') ? '📅' : (($base_src === 'pattern') ? '🗓️' : '🎟️');
                            echo '<span class="vms-av-src" aria-hidden="true" title="' . esc_attr($base_src) . '">' . esc_html($icon) . '</span>';
                        }

                        echo '</button>';
                    } else {
                        // echo '<div class="vms-av-muted" style="font-size:12px;">' . esc_html__('Not in season', 'vms') . '</div>';
                    }

                    // PATCH 20260114 @ 09:25am
                    if (isset($events_by_date[$date]) && !empty($events_by_date[$date])) {
                        $items = $events_by_date[$date];
                        $lines = array_slice($items, 0, 2);
                        $more  = count($items) - count($lines);

                        $title_attr = implode(' | ', $items);

                        echo '<div class="vms-av-event-title" title="' . esc_attr($title_attr) . '">';
                        foreach ($lines as $ln) {
                            echo '<span>' . esc_html($ln) . '</span>';
                        }
                        if ($more > 0) {
                            echo '<span>+' . (int) $more . '</span>';
                        }
                        echo '</div>';
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
                var methods = document.querySelectorAll("details.vms-av-method");
                if (!methods.length) return;

                function closeOthers(except) {
                    methods.forEach(function(d) {
                        if (d !== except) d.removeAttribute("open");
                    });
                }

                methods.forEach(function(d) {
                    d.addEventListener("toggle", function() {
                        if (d.open) {
                            closeOthers(d);
                            var key = d.getAttribute("data-method");
                            if (key) localStorage.setItem("vms_av_open_method", key);
                        }
                    });
                });

                // Restore last open
                var last = localStorage.getItem("vms_av_open_method");
                var target = last ? document.querySelector('details.vms-av-method[data-method="' + last + '"]') : null;
                if (target) {
                    target.setAttribute("open", "open");
                    closeOthers(target);
                } else {
                    // default
                    var def = document.querySelector('details.vms-av-method[data-method="manual"]');
                    if (def) {
                        def.setAttribute("open", "open");
                        closeOthers(def);
                    }
                }
            })();

            (function() {
                document.documentElement.classList.add("vms-js");

                var cfg = window.VMS_AV || {};
                var ajaxUrl = cfg.ajaxUrl || "";
                var nonce = cfg.nonce || "";

                var statusEl = document.querySelector(".vms-av-autosave");

                var pending = 0;
                var failed = 0;
                var dirtyDates = new Set();

                function labelFor(state, src) {
                    if (src === "booked") return "Booked";
                    if (state === "available") return "✓";
                    if (state === "unavailable") return "🔒";
                    return "—";
                }

                function ariaFor(state, src) {
                    if (src === "booked") return "Booked";
                    if (state === "available") return "Available";
                    if (state === "unavailable") return "Not Available";
                    return "Unset";
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

                function iconFor(src) {
                    if (src === "ics") return "📅";
                    if (src === "pattern") return "🗓️";
                    if (src === "booked") return "🎟️";
                    return "";
                }

                function sync(btn) {
                    var date = btn.getAttribute("data-date");
                    var manual = btn.getAttribute("data-state") || "";
                    var base = btn.getAttribute("data-base") || "";
                    var baseSrc = btn.getAttribute("data-base-src") || "";

                    var visual = manual || base || "";
                    var src = manual ? "manual" : (baseSrc || "");

                    btn.setAttribute("data-visual", visual);
                    btn.setAttribute("data-src", src);

                    var hidden = btn.closest("td") ?
                        btn.closest("td").querySelector('input.vms-av-hidden[data-date="' + date + '"]') :
                        null;
                    if (hidden) hidden.value = manual;

                    var lab = btn.querySelector('[data-label="' + date + '"]');
                    if (lab) lab.textContent = labelFor(visual, src);

                    // Icon: only show when NOT manual
                    var iconEl = btn.querySelector(".vms-av-src");
                    if (!iconEl) {
                        // If PHP didn’t render it, create it once so JS can manage it
                        iconEl = document.createElement("span");
                        iconEl.className = "vms-av-src";
                        iconEl.setAttribute("aria-hidden", "true");
                        btn.appendChild(iconEl);
                    }

                    if (src && src !== "manual") {
                        iconEl.textContent = iconFor(src);
                        iconEl.style.display = "";
                        iconEl.setAttribute("title", src);
                    } else {
                        iconEl.textContent = "";
                        iconEl.style.display = "none";
                        iconEl.removeAttribute("title");
                    }

                    btn.setAttribute("aria-label", date + ": " + ariaFor(visual, src) + ". Tap to cycle.");

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

                function isCompact() {
                    return window.matchMedia("(max-width:520px)").matches;
                }

                function labelFor(state, src) {
                    if (isCompact()) {
                        if (src === "booked") return "✅";
                        if (state === "available") return "✓";
                        if (state === "unavailable") return "🔒";
                        return "—";
                    }

                    // Desktop
                    if (src === "booked") return "Booked";
                    if (state === "available") return "✓";
                    if (state === "unavailable") return "🔒";
                    return "—";
                }


            })();
        </script>

        <script>
            (function() {
                var root = document.getElementById("vms-av");
                if (!root) return;

                var cookieName = "vms_av_open_ym";
                var todayYm = root.getAttribute("data-today-ym") || "";

                var monthEls = Array.prototype.slice.call(root.querySelectorAll(".vms-av-month[data-ym]"));
                if (!monthEls.length) return;

                function getCookie(name) {
                    var parts = document.cookie.split(";").map(function(c) {
                        return c.trim();
                    });
                    for (var i = 0; i < parts.length; i++) {
                        if (parts[i].indexOf(name + "=") === 0) return decodeURIComponent(parts[i].slice(name.length + 1));
                    }
                    return "";
                }

                function setCookie(name, value, days) {
                    var maxAge = (days || 30) * 24 * 60 * 60;
                    document.cookie = name + "=" + encodeURIComponent(value) + "; path=/; max-age=" + maxAge + "; samesite=lax";
                }

                var byYm = new Map();
                monthEls.forEach(function(m) {
                    var ym = m.getAttribute("data-ym") || "";
                    var details = m.querySelector("details");
                    var summary = details ? details.querySelector("summary") : null;
                    if (ym && details && summary) byYm.set(ym, {
                        details: details,
                        summary: summary
                    });
                });

                function firstYm() {
                    for (var k of byYm.keys()) return k;
                    return "";
                }

                var preferredYm = getCookie(cookieName);
                var openYm =
                    (preferredYm && byYm.has(preferredYm)) ? preferredYm :
                    (todayYm && byYm.has(todayYm)) ? todayYm :
                    firstYm();

                var currentOpenYm = "";

                function openOnly(ym) {
                    if (!ym || !byYm.has(ym)) return;

                    currentOpenYm = ym;

                    byYm.forEach(function(obj, key) {
                        obj.details.open = (key === ym);
                    });

                    setCookie(cookieName, ym, 30);
                }

                // Initial state
                openOnly(openYm);

                // Switch months via summary click (prevents “twitch” from toggle loops)
                byYm.forEach(function(obj, ym) {
                    obj.summary.addEventListener("click", function(e) {
                        e.preventDefault();
                        if (currentOpenYm === ym) return;
                        openOnly(ym);
                        try {
                            obj.summary.scrollIntoView({
                                block: "start",
                                behavior: "smooth"
                            });
                        } catch (err) {
                            // no-op
                        }
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
