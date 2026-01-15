<?php
if (!defined('ABSPATH')) exit;

/**
 * Vendor Portal — Profile Module (Procedural)
 * Provides: vms_vendor_portal_render_profile($vendor_id)
 */

function vms_vendor_portal_render_profile($vendor_id)
{
    $vendor_id = (int) $vendor_id;
    if ($vendor_id <= 0) {
        echo '<p>' . esc_html__('Invalid vendor.', 'vms') . '</p>';
        return;
    }

    // Ensure media functions exist on front-end (for logo upload)
    if (!function_exists('media_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    // -----------------------------
    // Save handler
    // -----------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_vendor_profile_save'])) {

        if (
            !isset($_POST['vms_vendor_profile_nonce']) ||
            !wp_verify_nonce($_POST['vms_vendor_profile_nonce'], 'vms_vendor_profile_save')
        ) {
            echo vms_portal_notice('error', __('Security check failed.', 'vms'));
        } else {

            $contact_name  = isset($_POST['vms_contact_name']) ? sanitize_text_field((string) $_POST['vms_contact_name']) : '';
            $contact_email = isset($_POST['vms_contact_email']) ? sanitize_email((string) $_POST['vms_contact_email']) : '';
            $contact_phone = isset($_POST['vms_contact_phone']) ? sanitize_text_field((string) $_POST['vms_contact_phone']) : '';
            $location      = isset($_POST['vms_location']) ? sanitize_text_field((string) $_POST['vms_location']) : '';
            $epk_url       = isset($_POST['vms_epk_url']) ? esc_url_raw((string) $_POST['vms_epk_url']) : '';
            $social_links  = isset($_POST['vms_social_links']) ? sanitize_textarea_field((string) $_POST['vms_social_links']) : '';

            // Snapshot old values (for change detection)
            $before = array(
                '_vms_contact_name'     => (string) get_post_meta($vendor_id, '_vms_contact_name', true),
                '_vms_contact_email'    => (string) get_post_meta($vendor_id, '_vms_contact_email', true),
                '_vms_contact_phone'    => (string) get_post_meta($vendor_id, '_vms_contact_phone', true),
                '_vms_vendor_location'  => (string) get_post_meta($vendor_id, '_vms_vendor_location', true),
                '_vms_vendor_epk'       => (string) get_post_meta($vendor_id, '_vms_vendor_epk', true),
                '_vms_vendor_social'    => (string) get_post_meta($vendor_id, '_vms_vendor_social', true),
            );

            // Save meta
            update_post_meta($vendor_id, '_vms_contact_name', $contact_name);
            update_post_meta($vendor_id, '_vms_contact_email', $contact_email);
            update_post_meta($vendor_id, '_vms_contact_phone', $contact_phone);
            update_post_meta($vendor_id, '_vms_vendor_location', $location);
            update_post_meta($vendor_id, '_vms_vendor_epk', $epk_url);
            update_post_meta($vendor_id, '_vms_vendor_social', $social_links);

            // Logo upload (Featured Image)
            $logo_changed = false;
            if (!empty($_FILES['vms_vendor_logo']['name'])) {
                $attach_id = media_handle_upload('vms_vendor_logo', 0);
                if (!is_wp_error($attach_id)) {
                    set_post_thumbnail($vendor_id, (int) $attach_id);
                    $logo_changed = true;
                } else {
                    echo vms_portal_notice('error', sprintf(
                        __('Logo upload failed: %s', 'vms'),
                        $attach_id->get_error_message()
                    ));
                }
            }

            // Detect changed fields (avoid noisy flags)
            $after = array(
                '_vms_contact_name'     => $contact_name,
                '_vms_contact_email'    => $contact_email,
                '_vms_contact_phone'    => $contact_phone,
                '_vms_vendor_location'  => $location,
                '_vms_vendor_epk'       => $epk_url,
                '_vms_vendor_social'    => $social_links,
            );

            $changed_fields = array();
            foreach ($after as $k => $new_val) {
                $old_val = isset($before[$k]) ? trim((string) $before[$k]) : '';
                $new_val = trim((string) $new_val);
                if ($old_val !== $new_val) {
                    $changed_fields[] = $k;
                }
            }
            if ($logo_changed) {
                $changed_fields[] = '_thumbnail_id';
            }

            if (!empty($changed_fields)) {
                vms_vendor_flag_vendor_update($vendor_id, 'profile', $changed_fields);
            }

            echo vms_portal_notice('success', __('Profile saved.', 'vms'));
        }
    }

    // -----------------------------
    // Current values
    // -----------------------------
    $contact_name  = (string) get_post_meta($vendor_id, '_vms_contact_name', true);
    $contact_email = (string) get_post_meta($vendor_id, '_vms_contact_email', true);
    $contact_phone = (string) get_post_meta($vendor_id, '_vms_contact_phone', true);
    $location      = (string) get_post_meta($vendor_id, '_vms_vendor_location', true);
    $epk_url       = (string) get_post_meta($vendor_id, '_vms_vendor_epk', true);
    $social_links  = (string) get_post_meta($vendor_id, '_vms_vendor_social', true);

    $thumb_id  = get_post_thumbnail_id($vendor_id);
    $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';

    echo '<h3>' . esc_html__('Profile', 'vms') . '</h3>';

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('vms_vendor_profile_save', 'vms_vendor_profile_nonce');

    // Logo
    echo '<h4 style="margin-top:18px;">' . esc_html__('Logo', 'vms') . '</h4>';

    if ($thumb_url) {
        echo '<p><img src="' . esc_url($thumb_url) . '" alt="" style="max-width:180px;height:auto;border:1px solid #ddd;border-radius:8px;padding:6px;background:#fff;"></p>';
    } else {
        echo '<p><em>' . esc_html__('No logo uploaded yet.', 'vms') . '</em></p>';
    }

    echo '<p><label><strong>' . esc_html__('Upload / Replace Logo', 'vms') . '</strong></label><br>';
    echo '<input type="file" name="vms_vendor_logo" accept=".png,.jpg,.jpeg,.webp"></p>';

    echo '<p><label><strong>' . esc_html__('Contact Name', 'vms') . '</strong></label><br>';
    echo '<input type="text" name="vms_contact_name" value="' . esc_attr($contact_name) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>' . esc_html__('Contact Email', 'vms') . '</strong></label><br>';
    echo '<input type="email" name="vms_contact_email" value="' . esc_attr($contact_email) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>' . esc_html__('Contact Phone', 'vms') . '</strong></label><br>';
    echo '<input type="tel" name="vms_contact_phone" id="vms_contact_phone" inputmode="tel" autocomplete="tel"
            placeholder="(###) ###-####" maxlength="14"
            value="' . esc_attr($contact_phone) . '"
            style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>' . esc_html__('Home Base (City/State)', 'vms') . '</strong></label><br>';
    echo '<input type="text" name="vms_location" value="' . esc_attr($location) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>' . esc_html__('EPK / Website Link', 'vms') . '</strong></label><br>';
    echo '<input type="url" name="vms_epk_url" value="' . esc_attr($epk_url) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>' . esc_html__('Social Links', 'vms') . '</strong></label><br>';
    echo '<textarea name="vms_social_links" rows="4" style="width:100%;max-width:520px;" placeholder="' .
        esc_attr__('Instagram, Facebook, Spotify, YouTube&hellip;', 'vms') . '">' .
        esc_textarea($social_links) . '</textarea></p>'; 

    echo '<p><button type="submit" name="vms_vendor_profile_save" class="button button-primary">' .
        esc_html__('Save Profile', 'vms') . '</button></p>';

    echo '</form>';

    // Phone mask (formats while typing; stored value is the formatted string)
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
