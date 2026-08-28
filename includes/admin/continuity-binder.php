<?php
defined('ABSPATH') || exit;

function bvmgr_continuity_binder_enqueue_assets($hook) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Continuity Binder admin routing only controls asset loading for the current page.
    $page = bvmgr_request_read_key($_GET, 'page');
    if ($page !== 'vms-continuity-binder') {
        return;
    }

    wp_enqueue_script(
        'bvmgr-continuity-binder',
        plugins_url('../../assets/js/vms-continuity-binder.js', __FILE__),
        array(),
        function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : ''),
        true
    );
}
add_action('admin_enqueue_scripts', 'bvmgr_continuity_binder_enqueue_assets');

function bvmgr_continuity_binder_option_key() {
    if (defined('BVMGR_CONTINUITY_BINDER_OPTION')) {
        return BVMGR_CONTINUITY_BINDER_OPTION;
    }

    // Fallback only; the canonical key should live in constants.php.
    return 'vms_continuity_binder_v1';
}

function bvmgr_continuity_binder_default_data() {
    return array(
        'version'    => 1,
        'updated_at' => 0,
        'updated_by' => 0,
        'sections'   => array(
            'overview' => array(
                'title'   => 'Overview (Read This First)',
                'content' =>
"Purpose: Run critical Serenade Range ops without developer knowledge.

Rules:
- Do NOT store passwords or full tax IDs here.
- If you change a process, update this binder the same day.
- Keep steps simple and check them off.

Emergency checklist:
1) Confirm today’s events and staffing coverage.
2) Confirm any vendor payments due today.
3) Confirm ticketing is live (if applicable).
4) Check pending W-9 / tax-profile items.",
            ),
            'w9_1099' => array(
                'title'   => 'W-9 + 1099 Workflow',
                'content' =>
"Goal: Ensure we can pay vendors and issue 1099s cleanly.

Before paying a vendor:
1) Confirm vendor legal name is captured (exactly as on W-9).
2) Confirm vendor tax classification (individual / LLC / corp) is recorded.
3) Confirm we have W-9 on file OR have initiated collection (Tax1099, email, or paper).
4) Confirm payment method (check, ACH, cash) is agreed.

During the year:
- For each paid event, record the amount and date in your accounting system and (when available) in VMS payment tracking.

Year-end (typical):
- Reconcile vendor totals.
- Generate 1099 list.
- Send/issue 1099s per your filing workflow.
Note: Deadlines can change; confirm each year.",
            ),
            'payouts' => array(
                'title'   => 'Vendor Payout Steps (Per Event)',
                'content' =>
"1) Open the Event Plan in VMS and confirm:
   - Venue, date, and vendor name
   - Agreed pay structure (guarantee/split/bonus)
2) Confirm internal approvals (who OKs the payout).
3) Confirm vendor has completed required paperwork (W-9 / tax profile as needed).
4) Pay vendor using the chosen method.
5) Record the payment in accounting (QuickBooks).
6) Record payout status in VMS (or note it here until payout tracking is implemented).",
            ),
            'due_dates' => array(
                'title'   => 'Due Dates + Reporting (Customize This)',
                'content' =>
"Add your real due dates here (local/state/federal can differ).

Monthly:
- Sales tax filing/payment:
- Any venue-specific reporting:
- Licensing reporting (if applicable):

Quarterly:
- Quarterly tax estimates:
- Any quarterly royalty reports (BMI/ASCAP/SESAC if required by your process):

Annual / Seasonal:
- 1099 process:
- Business tax filings:
- Insurance renewals:
- Permits/licensing renewals:",
            ),
        ),
    );
}

function bvmgr_continuity_binder_get_data() {
    $key  = bvmgr_continuity_binder_option_key();
    $data = get_option($key);

    if (!is_array($data) || empty($data['sections']) || !is_array($data['sections'])) {
        return bvmgr_continuity_binder_default_data();
    }

    // Merge defaults to ensure new sections appear after updates.
    $defaults = bvmgr_continuity_binder_default_data();
    $merged   = $defaults;

    $merged['version']    = isset($data['version']) ? (int) $data['version'] : $defaults['version'];
    $merged['updated_at'] = isset($data['updated_at']) ? (int) $data['updated_at'] : 0;
    $merged['updated_by'] = isset($data['updated_by']) ? (int) $data['updated_by'] : 0;

    foreach ($defaults['sections'] as $section_id => $section_def) {
        if (isset($data['sections'][$section_id]) && is_array($data['sections'][$section_id])) {
            $merged['sections'][$section_id]['title'] = isset($data['sections'][$section_id]['title'])
                ? (string) $data['sections'][$section_id]['title']
                : $section_def['title'];

            $merged['sections'][$section_id]['content'] = isset($data['sections'][$section_id]['content'])
                ? (string) $data['sections'][$section_id]['content']
                : $section_def['content'];
        }
    }

    return $merged;
}

function bvmgr_continuity_binder_render_text($text) {
    $safe = esc_html((string) $text);
    $safe = wpautop($safe);
    $safe = make_clickable($safe);
    return $safe;
}

function bvmgr_continuity_binder_anchor_id($section_id) {
    $id = sanitize_key((string) $section_id);
    if ($id === '') {
        $id = 'section';
    }
    return 'vms-cb-section-' . $id;
}

function bvmgr_continuity_binder_render_nav($sections, $base_url) {
    if (!is_array($sections) || empty($sections)) {
        return;
    }

    echo '<div class="vms-cb-nav">';
    echo '<div class="vms-cb-nav-title">' . esc_html__('Jump to:', 'backstage-venue-manager') . '</div>';
    echo '<ul class="vms-cb-nav-list">';

    foreach ($sections as $section_id => $section) {
        $anchor_id = bvmgr_continuity_binder_anchor_id($section_id);
        $label     = isset($section['title']) ? (string) $section['title'] : (string) $section_id;
        $href      = $base_url . '#' . $anchor_id;

        echo '<li class="vms-cb-nav-item">';
        echo '<a class="vms-cb-nav-link" href="' . esc_url($href) . '">' . esc_html($label) . '</a>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
}

function bvmgr_continuity_binder_render_updated_notice(): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Continuity Binder notice state only affects admin feedback.
    if (bvmgr_request_read_scalar($_GET, 'updated') !== '1') {
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Binder updated.', 'backstage-venue-manager') . '</p></div>';
}

function bvmgr_render_continuity_binder_page() {
    if (function_exists('bvmgr_admin_ui_render_shell')) {
        bvmgr_admin_ui_render_shell(
            array(
                'title' => __('Continuity Binder', 'backstage-venue-manager'),
                'notices_callback' => 'bvmgr_continuity_binder_render_updated_notice',
            ),
            'bvmgr_render_continuity_binder_page_content'
        );
        return;
    }

    echo '<div class="wrap"><h1>' . esc_html__('Continuity Binder', 'backstage-venue-manager') . '</h1>';
    bvmgr_render_continuity_binder_page_content();
    echo '</div>';
}

function bvmgr_render_continuity_binder_page_content() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to view this page.', 'backstage-venue-manager'));
    }

    $data      = bvmgr_continuity_binder_get_data();
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Continuity Binder mode switching only affects the current admin view.
    $is_edit   = (bvmgr_request_read_scalar($_GET, 'edit') === '1');
    $is_saved  = !empty($data['updated_at']);
    $page_slug = 'vms-continuity-binder';

    echo '<div class="vms-continuity-binder">';

    echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Security note:', 'backstage-venue-manager') . '</strong> ' .
        esc_html__('Do not store passwords, full SSNs, or full EINs here.', 'backstage-venue-manager') . '</p></div>';

    $base_url = admin_url('admin.php?page=' . $page_slug);
    $edit_url = add_query_arg('edit', '1', $base_url);

    if ($is_edit) {
        echo '<div class="vms-cb-actions">';
        echo '<a class="button" href="' . esc_url($base_url) . '">' . esc_html__('Back to View Mode', 'backstage-venue-manager') . '</a>';
        echo '</div>';

        bvmgr_continuity_binder_render_nav($data['sections'], $edit_url);

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vms_save_continuity_binder" />';
        wp_nonce_field('vms_save_continuity_binder', '_vms_cb_nonce');

        echo '<div class="vms-cb-form-actions">';
        echo get_submit_button(__('Save Binder', 'backstage-venue-manager'), 'primary', 'submit', false);
        echo '</div>';

        foreach ($data['sections'] as $section_id => $section) {
            $title_name   = 'sections[' . $section_id . '][title]';
            $content_name = 'sections[' . $section_id . '][content]';

            $anchor_id = bvmgr_continuity_binder_anchor_id($section_id);

            echo '<div class="postbox vms-cb-section" id="' . esc_attr($anchor_id) . '">';
            echo '<h2 class="hndle"><span>' . esc_html($section['title']) . '</span></h2>';
            echo '<div class="inside">';

            echo '<p><label><strong>' . esc_html__('Section Title', 'backstage-venue-manager') . '</strong></label><br />';
            echo '<input type="text" class="regular-text" name="' . esc_attr($title_name) . '" value="' . esc_attr($section['title']) . '" /></p>';

            echo '<p><label><strong>' . esc_html__('Section Content', 'backstage-venue-manager') . '</strong></label><br />';
            echo '<textarea class="large-text" rows="10" name="' . esc_attr($content_name) . '">' . esc_textarea($section['content']) . '</textarea></p>';

            echo '</div></div>';
        }

        submit_button(__('Save Binder', 'backstage-venue-manager'));
        echo '</form>';

        echo '</div>';
        return;
    }

    echo '<div class="vms-cb-actions">';
    echo '<a class="button button-primary" href="' . esc_url($edit_url) . '">' . esc_html__('Edit Binder', 'backstage-venue-manager') . '</a>';
    echo '</div>';

    bvmgr_continuity_binder_render_nav($data['sections'], $base_url);

    if ($is_saved) {
        $updated_at = wp_date('Y-m-d H:i', (int) $data['updated_at'], wp_timezone());
        $user       = $data['updated_by'] ? get_userdata((int) $data['updated_by']) : null;
        $who        = ($user && !empty($user->display_name)) ? $user->display_name : __('Unknown', 'backstage-venue-manager');

        /* translators: 1: formatted update timestamp, 2: user display name. */
        echo '<p><em>' . esc_html(sprintf(__('Last updated: %1$s by %2$s', 'backstage-venue-manager'), $updated_at, $who)) . '</em></p>';
    } else {
        echo '<p><em>' . esc_html__('Currently showing defaults (not yet saved). Click “Edit Binder” to customize and save.', 'backstage-venue-manager') . '</em></p>';
    }

    foreach ($data['sections'] as $section_id => $section) {
        $anchor_id = bvmgr_continuity_binder_anchor_id($section_id);

        echo '<div class="postbox vms-cb-section" id="' . esc_attr($anchor_id) . '">';
        echo '<h2 class="hndle"><span>' . esc_html($section['title']) . '</span></h2>';
        echo '<div class="inside vms-cb-section-content">' . bvmgr_continuity_binder_render_text($section['content']) . '</div>';
        echo '</div>';
    }

    echo '</div>';
}

function bvmgr_admin_post_save_continuity_binder() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized.', 'backstage-venue-manager'));
    }

    if (!isset($_POST['_vms_cb_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_vms_cb_nonce'])), 'vms_save_continuity_binder')) {
        wp_die(esc_html__('Invalid nonce.', 'backstage-venue-manager'));
    }

    $incoming = (isset($_POST['sections']) && is_array($_POST['sections']))
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Binder section fields are unslashed once here and sanitized field-by-field below.
        ? (array) wp_unslash($_POST['sections'])
        : array();

    $defaults = bvmgr_continuity_binder_default_data();
    $sections = array();

    foreach ($defaults['sections'] as $section_id => $section_def) {
        $raw_title = isset($incoming[$section_id]['title']) ? $incoming[$section_id]['title'] : $section_def['title'];
        $raw_body  = isset($incoming[$section_id]['content']) ? $incoming[$section_id]['content'] : $section_def['content'];

        $sections[$section_id] = array(
            'title'   => sanitize_text_field((string) $raw_title),
            'content' => sanitize_textarea_field((string) $raw_body),
        );
    }

    $data = array(
        'version'    => 1,
        'updated_at' => time(),
        'updated_by' => get_current_user_id(),
        'sections'   => $sections,
    );

    update_option(bvmgr_continuity_binder_option_key(), $data, false);

    $url = add_query_arg(
        array(
            'page'    => 'vms-continuity-binder',
            'updated' => '1',
        ),
        admin_url('admin.php')
    );

    wp_safe_redirect($url);
    exit;
}
