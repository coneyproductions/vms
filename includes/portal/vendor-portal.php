<?php
if (!defined('ABSPATH')) exit;

$tax_file = plugin_dir_path(__FILE__) . 'vendor-tax-profile.php';
if (file_exists($tax_file)) {
    require_once $tax_file;
}

add_shortcode('vms_vendor_portal', 'vms_vendor_portal_shortcode');

function vms_vendor_portal_shortcode()
{
    $base_url = get_permalink(); // the page where the shortcode lives
    $url_dashboard    = add_query_arg('tab', 'dashboard', $base_url);
    $url_profile      = add_query_arg('tab', 'profile', $base_url);
    $url_availability = add_query_arg('tab', 'availability', $base_url);
    $url_tech         = add_query_arg('tab', 'tech', $base_url);
    $url_tax_profile  = add_query_arg('tab', 'tax-profile', $base_url);

    if (!is_user_logged_in()) {

        // Change this to your actual application page URL (where [vms_vendor_apply] lives)
        $apply_url        = site_url('/vendor-application/');

        ob_start(); ?>
        <div class="vms-portal-auth-wrap">
            <div class="vms-portal-auth-col vms-portal-auth-login">
                <h2><?php echo esc_html(vms_ui_text('portal_login_title', __('Vendor Portal Login', 'vms'))); ?></h2>
                <?php
                echo wp_login_form(array(
                    'echo'     => false,
                    'redirect' => esc_url(get_permalink()),
                ));
                ?>
                <p style="margin-top:10px;">
                    <a href="<?php echo esc_url(wp_lostpassword_url(get_permalink())); ?>">Forgot password?</a>
                </p>
            </div>

            <div class="vms-portal-auth-col vms-portal-auth-apply">
                <h2><?php echo esc_html(vms_ui_text('portal_need_account_title', __('Need an Account?', 'vms'))); ?></h2>

                <p><?php echo esc_html(vms_ui_text('portal_need_account_blurb', __('Vendors must be approved before getting portal access.', 'vms'))); ?></p>

                <p>
                    <a class="button button-primary" href="<?php echo esc_url($apply_url); ?>">
                        <?php echo esc_html(vms_ui_text('portal_apply_button', __('Apply for an Account', 'vms'))); ?>
                    </a>
                </p>

                <p style="margin-top:10px; opacity:0.85;">
                    <?php echo esc_html(vms_ui_text('portal_applied_hint', __('Already applied? We’ll email you once you’re approved.', 'vms'))); ?>
                </p>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    $user_id   = get_current_user_id();
    $vendor_id = (int) get_user_meta($user_id, '_vms_vendor_id', true);

    if (!$vendor_id) {
        return '<p>Your account is not linked to a vendor profile yet. Please contact the venue admin.</p>';
    }

    $vendor = get_post($vendor_id);
    if (!$vendor || $vendor->post_type !== 'vms_vendor') {
        return '<p>' . esc_html__('Your linked vendor profile could not be found. Please contact the venue admin.', 'vms') . '</p>';
    }

    // Simple tab routing
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

    ob_start();

    if ($tab != 'dashboard') {
        echo '<div class="vms-portal">';
        echo '<h2>' . esc_html(vms_ui_text('portal_title_prefix', __('Vendor Portal:', 'vms'))) . ' ' . esc_html($vendor->post_title) . '</h2>';

        echo '<nav style="margin:12px 0;">';
        echo '<a style="margin-right:10px;" href="' . esc_url(add_query_arg('tab', 'dashboard')) . '">Dashboard</a>';
        echo '<a style="margin-right:10px;" href="' . esc_url(add_query_arg('tab', 'profile')) . '">Profile</a>';
        echo '<a style="margin-right:10px;" href="' . esc_url(add_query_arg('tab', 'tax-profile')) . '">Tax Profile</a>';
        echo '<a style="margin-right:10px;" href="' . esc_url(add_query_arg('tab', 'availability')) . '">Availability</a>';
        echo '<a style="margin-right:10px;" href="' . esc_url(add_query_arg('tab', 'tech')) . '">Tech Docs</a>';
        echo '</nav>';
        echo '</div>';
    }

    if ($tab === 'dashboard') {
        echo '<style>
.vms-portal-card{background:#fff;border:1px solid #e5e5e5;border-radius:14px;padding:14px;margin:0 0 14px;}
.vms-portal-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;}
@media (max-width:820px){.vms-portal-grid{grid-template-columns:1fr;}}
.vms-muted{opacity:.8}
</style>';

        echo '<div class="vms-portal-card">';
        echo '<p class="vms-muted">' . esc_html(vms_ui_text('portal_intro', __('Choose a tab to update your information.', 'vms'))) . '</p>';
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
  <p class="vms-muted">' . esc_html__('Manage your stage plot and input list.', 'vms') . '</p>
  <p><a class="button" href="' . esc_url($url_tech) . '">' . esc_html__('Manage Tech Docs', 'vms') . '</a></p>
</div>';
        echo '<div class="vms-portal-card">
  <h3 style="margin-top:0;">' . esc_html__('Tax Profile', 'vms') . '</h3>
  <p class="vms-muted">' . esc_html__('Complete your payee + mailing info and upload your signed W-9.', 'vms') . '</p>
  <p><a class="button" href="' . esc_url($url_tax_profile) . '">' . esc_html__('Update Tax Profile', 'vms') . '</a></p>
</div>';
    } elseif ($tab === 'profile') {
        if (function_exists('vms_vendor_portal_render_profile')) {
            vms_vendor_portal_render_profile($vendor_id);
        } else {
            echo '<p><strong>Profile module is not loaded.</strong> (Missing vms_vendor_portal_render_profile)</p>';
        }
    } elseif ($tab === 'tax-profile') {
        vms_vendor_portal_render_tax_profile($vendor_id);
    } elseif ($tab === 'availability') {
        vms_vendor_portal_render_availability($vendor_id);
    } elseif ($tab === 'tech') {
        if (function_exists('vms_vendor_portal_render_tech_docs')) {
            vms_vendor_portal_render_tech_docs($vendor_id);
        } else {
            echo '<p><strong>Tech Docs module is not loaded.</strong> (Missing vms_vendor_portal_render_tech_docs)</p>';
        }
    } else {

        // echo '<p>Choose a tab to update your information.</p>';
        // echo '<p>' . esc_html(vms_ui_text('portal_intro', __('Choose a tab to update your information.', 'vms'))) . '</p>';
    }

    echo '</div>';
    return ob_get_clean();
}

function vms_vendor_portal_render_availability($vendor_id)
{
    $active_dates = get_option('vms_active_dates', array());
    // $availability = get_post_meta($vendor_id, '_vms_availability', true);
    $ics_url      = (string) get_post_meta($vendor_id, '_vms_ics_url', true);
    $ics_autosync = (int) get_post_meta($vendor_id, '_vms_ics_autosync', true);
    $ics_last     = (int) get_post_meta($vendor_id, '_vms_ics_last_sync', true);

    $manual = get_post_meta($vendor_id, '_vms_availability_manual', true);
    $ics    = get_post_meta($vendor_id, '_vms_availability_ics', true);

    if (!is_array($manual)) $manual = array();
    if (!is_array($ics)) $ics = array();

    // Handle ICS settings + sync
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Save ICS settings
        if (isset($_POST['vms_save_ics_settings'])) {
            if (!isset($_POST['vms_ics_nonce']) || !wp_verify_nonce($_POST['vms_ics_nonce'], 'vms_ics_settings')) {
                echo '<p>' . esc_html__('Security check failed.', 'vms') . '</p>';
                return;
            }

            $new_url = isset($_POST['vms_ics_url']) ? trim((string) $_POST['vms_ics_url']) : '';
            $new_url = esc_url_raw($new_url);

            $new_autosync = !empty($_POST['vms_ics_autosync']) ? 1 : 0;

            update_post_meta($vendor_id, '_vms_ics_url', $new_url);
            update_post_meta($vendor_id, '_vms_ics_autosync', $new_autosync);

            $ics_url      = $new_url;
            $ics_autosync = $new_autosync;

            echo '<div class="notice notice-success"><p>' . esc_html__('Calendar settings saved.', 'vms') . '</p></div>';
        }

        // Handle save
        if (isset($_POST['vms_save_availability'])) {
            if (!isset($_POST['vms_avail_nonce']) || !wp_verify_nonce($_POST['vms_avail_nonce'], 'vms_save_availability')) {
                echo '<p>' . esc_html__('Security check failed.', 'vms') . '</p>';
                return;
            }

            $incoming = isset($_POST['vms_availability']) && is_array($_POST['vms_availability']) ? $_POST['vms_availability'] : array();
            $clean = array();

            foreach ($incoming as $date => $state) {
                $date  = sanitize_text_field($date);
                $state = sanitize_text_field($state);
                if ($state === 'available' || $state === 'unavailable') {
                    $clean[$date] = $state;
                }
            }

            update_post_meta($vendor_id, '_vms_availability_manual', $clean);
            $manual = $clean; // keep in-memory state in sync

            echo '<div class="notice notice-success"><p>' . esc_html__('Availability saved.', 'vms') . '</p></div>';
        }


        // Sync now
        if (isset($_POST['vms_sync_ics_now'])) {
            if (!isset($_POST['vms_ics_nonce']) || !wp_verify_nonce($_POST['vms_ics_nonce'], 'vms_ics_settings')) {
                echo '<p>' . esc_html__('Security check failed.', 'vms') . '</p>';
                return;
            }

            if (empty($active_dates)) {
                echo '<div class="notice notice-warning"><p>' . esc_html__('No season dates are configured yet, so there is nothing to sync.', 'vms') . '</p></div>';
            } elseif (empty($ics_url)) {
                echo '<div class="notice notice-warning"><p>' . esc_html__('Please paste your calendar feed (ICS) URL first.', 'vms') . '</p></div>';
            } elseif (!function_exists('vms_vendor_ics_sync_now')) {
                echo '<div class="notice notice-error"><p>' . esc_html__('ICS sync module is not loaded.', 'vms') . '</p></div>';
            } else {
                $result = vms_vendor_ics_sync_now($vendor_id, $active_dates);

                if (!empty($result['ok'])) {

                    $ics_unavailable = isset($result['ics_unavailable']) && is_array($result['ics_unavailable'])
                        ? $result['ics_unavailable']
                        : array();

                    $ics = $ics_unavailable;

                    update_post_meta($vendor_id, '_vms_ics_last_sync', time());
                    $ics_last = time();

                    $count = count($ics_unavailable);

                    echo '<div class="notice notice-success"><p>' .
                        sprintf(esc_html__('Calendar synced. %d date(s) marked unavailable.', 'vms'), $count) .
                        '</p></div>';
                } else {
                    $msg = !empty($result['error']) ? (string) $result['error'] : __('Calendar sync failed.', 'vms');
                    echo '<div class="notice notice-error"><p>' . esc_html($msg) . '</p></div>';
                }
            }
        }
    }


    if (empty($active_dates)) {
        echo '<p>' . esc_html__('No season dates have been configured yet.', 'vms') . '</p>';
        return;
    }

    echo '<style>
.vms-panel{border-radius:14px;border:1px solid #e5e5e5;background:#fff;}
.vms-panel > summary{list-style:none;}
.vms-panel > summary::-webkit-details-marker{display:none;}
.vms-panel-sync{border-left:6px solid #4f46e5;}   /* sync accent */
.vms-panel-manual{border-left:6px solid #10b981;} /* manual accent */
</style>';

    echo '<h3>' . esc_html(vms_ui_text('availability_heading', __('Availability', 'vms'))) . '</h3>';
    echo '<p class="description" style="margin:-6px 0 14px;max-width:720px;"><!-- DESCRIPTION --></p>';
    echo '<form method="post" id="vms-availability-form">';
    wp_nonce_field('vms_save_availability', 'vms_avail_nonce');
    wp_nonce_field('vms_ics_settings', 'vms_ics_nonce');
    echo '<input type="hidden" name="vms_autosave_before_sync" value="0" id="vms-autosave-before-sync">';

    echo '<details class="vms-panel vms-panel-sync" open style="margin:12px 0 16px;">';
    echo '<summary style="cursor:pointer;font-weight:700;font-size:15px;padding:10px 12px;border-radius:14px;">
  ' . esc_html__('Calendar Sync (Optional)', 'vms') . '
  <span style="font-weight:400;opacity:.75;margin-left:8px;">' . esc_html__('Paste once, then sync anytime.', 'vms') . '</span>
</summary>';

    echo '<div style="padding:12px 12px 14px;">';

    echo '<p class="description" style="margin:0 0 10px;">
  ' . esc_html__('If you use Google Calendar (or any calendar with an iCal/ICS link), you can sync conflicts automatically. Otherwise, just use manual availability below.', 'vms') . '
</p>';

    echo '<p style="margin:0 0 10px;">
  <label><strong>' . esc_html__('Calendar Feed URL (ICS)', 'vms') . '</strong></label><br>
  <input type="url" name="vms_ics_url" value="' . esc_attr($ics_url) . '" style="width:100%;max-width:720px;" placeholder="' . esc_attr__('https://… (secret iCal address)', 'vms') . '">
</p>';

    echo '<p style="margin:0 0 10px;">
  <label>
    <input type="checkbox" name="vms_ics_autosync" value="1" ' . checked($ics_autosync, 1, false) . '>
    ' . esc_html__('Auto-sync nightly (recommended)', 'vms') . '
  </label>
</p>';

    if (!empty($ics_last)) {
        $tz_label = vms_get_timezone_id(); // e.g. America/Chicago
        $tz = vms_get_timezone();
        $dt = new DateTime('@' . $ics_last); // timestamp is UTC
        $dt->setTimezone($tz);

        echo '<p class="description" style="margin:0 0 10px;">' .
            sprintf(
                esc_html__('Last synced: %s', 'vms'),
                esc_html($dt->format('M j, Y g:ia') . ' (' . $tz_label . ')')
            ) .
            '</p>';
    }


    echo '<p style="margin:0;">
  <button type="submit" class="button" name="vms_save_ics_settings" value="1">' . esc_html__('Save Calendar Settings', 'vms') . '</button>
  <button type="submit" class="button button-secondary" id="vms-sync-ics-now" name="vms_sync_ics_now" value="1" style="margin-left:6px;">' . esc_html__('Sync Now', 'vms') . '</button>
</p>';

    echo '<p class="description" style="margin:10px 0 0;">
  ' . esc_html__('We only detect busy/unavailable conflicts. Event details are not stored.', 'vms') . '
</p>';

    echo '</div></details>';

    echo '<details class="vms-panel vms-panel-manual" open style="margin:12px 0 16px;">';
    echo '<summary style="cursor:pointer;font-weight:700;font-size:15px;padding:10px 12px;border-radius:14px;">
  ' . esc_html__('Manual Availability', 'vms') . '
  <span style="font-weight:400;opacity:.75;margin-left:8px;">' . esc_html__('Use this if you prefer not to sync a calendar.', 'vms') . '</span>
</summary>';
    echo '<div style="padding:12px 12px 14px;">';


    echo '<p>
  <button class="button button-primary" name="vms_save_availability">' .
        esc_html(vms_ui_text('availability_save_button', __('Save Availability', 'vms'))) .
        '</button>
</p>';


    // Group dates by month first
    $dates_by_month = array();

    $event_titles_by_date = function_exists('vms_get_event_titles_by_date')
        ? vms_get_event_titles_by_date($active_dates)
        : array();

    foreach ($active_dates as $date_str) {
        $ts = strtotime($date_str);
        if (!$ts) continue;

        $month_label = date_i18n('F Y', $ts); // e.g. "March 2026"
        if (!isset($dates_by_month[$month_label])) {
            $dates_by_month[$month_label] = array();
        }
        $dates_by_month[$month_label][] = $date_str;
    }

    // Default open behavior: only open the current month (fallback: first month)
    $current_month_label = date_i18n('F Y', current_time('timestamp'));
    $has_current_month   = isset($dates_by_month[$current_month_label]);
    $opened_first        = false;

    // Optional: Expand/Collapse all buttons (nice for long seasons)
    echo '<p style="margin:8px 0 14px;">
  <button type="button" class="button" onclick="document.querySelectorAll(\'.vms-portal-month\').forEach(d=>d.open=true);">' . esc_html__('Expand all', 'vms') . '</button>
  <button type="button" class="button" onclick="document.querySelectorAll(\'.vms-portal-month\').forEach(d=>d.open=false);">' . esc_html__('Collapse all', 'vms') . '</button>
</p>';

    foreach ($dates_by_month as $month_label => $month_dates) {

        $open_attr = '';

        if ($has_current_month) {
            if ($month_label === $current_month_label) {
                $open_attr = ' open';
            }
        } else {
            // If current month isn't present in season, open the first month we render
            if (!$opened_first) {
                $open_attr = ' open';
                $opened_first = true;
            }
        }

        echo '<details class="vms-portal-month"' . $open_attr . ' style="margin:0 0 14px;background:#fff;border:1px solid #e5e5e5;border-radius:14px;padding:10px 12px;">';

        echo '<summary style="cursor:pointer;font-weight:700;font-size:15px;">' . esc_html($month_label) . '</summary>';

        echo '<table class="widefat striped" style="margin-top:10px;">';
        echo '<thead><tr>
    <th>' . esc_html__('Event', 'vms') . '</th>
    <th>' . esc_html__('Date', 'vms') . '</th>
    <th>' . esc_html__('Day', 'vms') . '</th>
    <th>' . esc_html__('Status', 'vms') . '</th>
    </tr></thead><tbody>';

        foreach ($month_dates as $date_str) {
            $ts = strtotime($date_str);
            if (!$ts) continue;

            $day  = date_i18n('D', $ts);
            $nice = date_i18n('M j, Y', $ts);
            $val = '';
            $event_title = isset($event_titles_by_date[$date_str]) ? $event_titles_by_date[$date_str] : '';

            if (isset($manual[$date_str]) && ($manual[$date_str] === 'available' || $manual[$date_str] === 'unavailable')) {
                $val = $manual[$date_str];
            } elseif (isset($ics[$date_str]) && $ics[$date_str] === 'unavailable') {
                $val = 'unavailable';
            }

            echo '<tr>';
            echo '<td style="max-width:260px;">' . ($event_title !== '' ? esc_html($event_title) : '—') . '</td>';
            echo '<td>' . esc_html($nice) . '</td>';
            echo '<td>' . esc_html($day) . '</td>';
            echo '<td>';
            echo '<select name="vms_availability[' . esc_attr($date_str) . ']">';
            echo '<option value="" ' . selected($val, '', false) . '>' . esc_html__('—', 'vms') . '</option>';
            echo '<option value="available" ' . selected($val, 'available', false) . '>' . esc_html__('Available', 'vms') . '</option>';
            echo '<option value="unavailable" ' . selected($val, 'unavailable', false) . '>' . esc_html__('Not Available', 'vms') . '</option>';
            echo '</select>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</details>';
    }

    echo '<script>
(function(){
  var form = document.getElementById("vms-availability-form");
  var syncBtn = document.getElementById("vms-sync-ics-now");
  var autosaveFlag = document.getElementById("vms-autosave-before-sync");
  if (!form || !syncBtn || !autosaveFlag) return;

  var dirty = false;     // availability changes
  var icsDirty = false;  // ICS settings changes

  // Mark dirty when availability dropdowns or ICS settings change
  form.addEventListener("change", function(e){
    if (!e.target || !e.target.name) return;

    if (e.target.name.indexOf("vms_availability[") === 0) {
      dirty = true;
    }

    if (e.target.name === "vms_ics_url" || e.target.name === "vms_ics_autosync") {
      icsDirty = true;
    }
  });

  // If Sync Now clicked and dirty, auto-save first
  syncBtn.addEventListener("click", function(e){
    if (!dirty && !icsDirty) return;

    e.preventDefault();

    // Show a brief warning (non-blocking)
    var msg = document.createElement("div");
    msg.className = "notice notice-warning";
    msg.style.margin = "10px 0";
    msg.innerHTML = "<p><strong>' . esc_js(__('Heads up:', 'vms')) . '</strong> ' . esc_js(__('You have unsaved changes. Saving them first, then syncing your calendar…', 'vms')) . '</p>";
    form.insertBefore(msg, form.firstChild);

    // Set flag so backend knows this was autosave+sync intent
    autosaveFlag.value = "1";

    // If availability changed, save it too
    if (dirty) {
      var hiddenSave = document.createElement("input");
      hiddenSave.type = "hidden";
      hiddenSave.name = "vms_save_availability";
      hiddenSave.value = "1";
      form.appendChild(hiddenSave);
    }

    // Always save ICS settings before syncing (checkbox + URL) if they were changed
    if (icsDirty) {
      var hiddenIcsSave = document.createElement("input");
      hiddenIcsSave.type = "hidden";
      hiddenIcsSave.name = "vms_save_ics_settings";
      hiddenIcsSave.value = "1";
      form.appendChild(hiddenIcsSave);
    }

    // Submit the form normally; backend saves then syncs.
    form.submit();
  });
})();
</script>';


    echo '<div class="vms-sticky-actions" style="position:sticky;bottom:0;z-index:20;margin-top:14px;padding:10px 12px;background:#fff;border:1px solid #e5e5e5;border-radius:14px;box-shadow:0 -6px 18px rgba(0,0,0,0.06);display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';

    echo '<button class="button button-primary" name="vms_save_availability" value="1">' .
        esc_html(vms_ui_text('availability_save_button', __('Save Availability', 'vms'))) .
        '</button>';

    echo '<span class="description" style="margin:0;opacity:.85;">' .
        esc_html__('Tip: after editing dates, click Save Availability.', 'vms') .
        '</span>';

    echo '</div>';
    echo '</form>';
    echo '</div></details>';
}

function vms_vendor_portal_render_profile($vendor_id)
{
    $vendor_id = (int) $vendor_id;

    // Ensure media handling functions are available on front-end
    if (!function_exists('media_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    // Save handler
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_vendor_profile_save'])) {
        if (
            !isset($_POST['vms_vendor_profile_nonce']) ||
            !wp_verify_nonce($_POST['vms_vendor_profile_nonce'], 'vms_vendor_profile_save')
        ) {
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
                    // echo '<div class="notice notice-error"><p>Logo upload failed: ' . esc_html($attach_id->get_error_message()) . '</p></div>';
                    echo '<div class="notice notice-error"><p>' . esc_html__('Logo upload failed:', 'vms') . esc_html($attach_id->get_error_message()) . '</p></div>';
                }
            }

            // Store in vendor meta (adjust keys later if you already have a schema)
            update_post_meta($vendor_id, '_vms_contact_name', $contact_name);
            update_post_meta($vendor_id, '_vms_contact_email', $contact_email);
            update_post_meta($vendor_id, '_vms_contact_phone', $contact_phone);
            update_post_meta($vendor_id, '_vms_vendor_location', $location);
            update_post_meta($vendor_id, '_vms_vendor_epk', $epk_url);
            update_post_meta($vendor_id, '_vms_vendor_social', $social_links);

            echo '<div class="notice notice-success"><p>' . esc_html__('Profile saved.', 'vms') . '</p></div>';
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

    echo '<h3>Profile</h3>';

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('vms_vendor_profile_save', 'vms_vendor_profile_nonce');

    // LOGO
    echo '<h4 style="margin-top:18px;">Logo</h4>';

    if ($thumb_url) {
        echo '<p><img src="' . esc_url($thumb_url) . '" alt="Vendor Logo" style="max-width:180px;height:auto;border:1px solid #ddd;border-radius:8px;padding:6px;background:#fff;"></p>';
    } else {
        echo '<p><em>No logo uploaded yet.</em></p>';
    }

    echo '<p><label><strong>Upload / Replace Logo</strong></label><br>';
    echo '<input type="file" name="vms_vendor_logo" accept=".png,.jpg,.jpeg,.webp,.pdf"></p>';
    // /LOGO

    echo '<p><label><strong>Contact Name</strong></label><br>';
    echo '<input type="text" name="vms_contact_name" value="' . esc_attr($contact_name) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>Contact Email</strong></label><br>';
    echo '<input type="email" name="vms_contact_email" value="' . esc_attr($contact_email) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>Contact Phone</strong></label><br>';
    echo '<input type="text" name="vms_contact_phone" value="' . esc_attr($contact_phone) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>Home Base (City/State)</strong></label><br>';
    echo '<input type="text" name="vms_location" value="' . esc_attr($location) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>EPK / Website Link</strong></label><br>';
    echo '<input type="url" name="vms_epk_url" value="' . esc_attr($epk_url) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>Social Links</strong></label><br>';
    echo '<textarea name="vms_social_links" rows="4" style="width:100%;max-width:520px;" placeholder="' . esc_attr__('Instagram, Facebook, Spotify, YouTube...', 'vms') . '">'
        . esc_textarea($social_links) . '</textarea></p>';

    echo '<p><button type="submit" name="vms_vendor_profile_save" class="button button-primary">Save Profile</button></p>';
    echo '</form>';
}

function vms_vendor_portal_render_tech_docs($vendor_id)
{
    $vendor_id = (int) $vendor_id;

    echo '<h3>Tech Docs</h3>';
    echo '<p>Upload your current stage plot and input list (PDF or image). You can replace them any time.</p>';

    // Ensure media handling functions are available on front-end
    if (!function_exists('media_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    // Handle uploads
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_techdocs_save'])) {
        if (
            !isset($_POST['vms_techdocs_nonce']) ||
            !wp_verify_nonce($_POST['vms_techdocs_nonce'], 'vms_techdocs_save')
        ) {
            echo vms_portal_notice('error', __('Security check failed.', 'vms'));
        } else {

            $updated = false;

            if (!empty($_FILES['vms_stage_plot']['name'])) {
                $attach_id = media_handle_upload('vms_stage_plot', 0);
                if (!is_wp_error($attach_id)) {
                    update_post_meta($vendor_id, '_vms_stage_plot_attachment_id', (int) $attach_id);
                    $updated = true;
                } else {
                    // echo '<div class="notice notice-error"><p>Stage plot upload failed: ' . esc_html($attach_id->get_error_message()) . '</p></div>';
                    echo '<div class="notice notice-success"><p>' . esc_html__('Stage plot upload failed.', 'vms') . esc_html($attach_id->get_error_message()) . '</p></div>';
                }
            }

            if (!empty($_FILES['vms_input_list']['name'])) {
                $attach_id = media_handle_upload('vms_input_list', 0);
                if (!is_wp_error($attach_id)) {
                    update_post_meta($vendor_id, '_vms_input_list_attachment_id', (int) $attach_id);
                    $updated = true;
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Input list upload failed:', 'vms') . esc_html($attach_id->get_error_message()) . '</p></div>';
                }
            }

            if ($updated) {
                echo '<div class="notice notice-success"><p>' . esc_html__('Tech docs updated.', 'vms') . '</p></div>';
            }
        }
    }

    $stage_id = (int) get_post_meta($vendor_id, '_vms_stage_plot_attachment_id', true);
    $input_id = (int) get_post_meta($vendor_id, '_vms_input_list_attachment_id', true);

    $stage_url = $stage_id ? wp_get_attachment_url($stage_id) : '';
    $input_url = $input_id ? wp_get_attachment_url($input_id) : '';

    echo '<ul>';
    echo '<li><strong>Stage Plot:</strong> ' . ($stage_url ? '<a target="_blank" rel="noopener" href="' . esc_url($stage_url) . '">View current</a>' : 'None uploaded') . '</li>';
    echo '<li><strong>Input List:</strong> ' . ($input_url ? '<a target="_blank" rel="noopener" href="' . esc_url($input_url) . '">View current</a>' : 'None uploaded') . '</li>';
    echo '</ul>';

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('vms_techdocs_save', 'vms_techdocs_nonce');

    echo '<p><label><strong>Upload / Replace Stage Plot</strong></label><br>';
    echo '<input type="file" name="vms_stage_plot" accept=".pdf,.png,.jpg,.jpeg,.webp"></p>';

    echo '<p><label><strong>Upload / Replace Input List</strong></label><br>';
    echo '<input type="file" name="vms_input_list" accept=".pdf,.png,.jpg,.jpeg,.webp"></p>';

    echo '<p><button type="submit" name="vms_techdocs_save" class="button button-primary">Save Tech Docs</button></p>';
    echo '</form>';
}

function vms_portal_notice(string $type, string $msg): string
{
    $cls = ($type === 'success') ? 'success' : 'error';
    return '<div class="vms-notice ' . esc_attr($cls) . '">' . esc_html($msg) . '</div>';
}
