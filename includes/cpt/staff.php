<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Staff (Labor Contractors)
 * CPT: vms_staff
 * Taxonomy (editable UI titles): vms_staff_role
 */

add_action('init', function () {

    // CPT: Staff
    register_post_type('vms_staff', array(
        'labels' => array(
            'name'               => __('Staff', 'backstage-venue-manager'),
            'singular_name'      => __('Staff Member', 'backstage-venue-manager'),
            'add_new'            => __('Add Staff', 'backstage-venue-manager'),
            'add_new_item'       => __('Add New Staff Member', 'backstage-venue-manager'),
            'edit_item'          => __('Edit Staff Member', 'backstage-venue-manager'),
            'new_item'           => __('New Staff Member', 'backstage-venue-manager'),
            'view_item'          => __('View Staff Member', 'backstage-venue-manager'),
            'search_items'       => __('Search Staff', 'backstage-venue-manager'),
            'not_found'          => __('No staff found', 'backstage-venue-manager'),
            'not_found_in_trash' => __('No staff found in trash', 'backstage-venue-manager'),
            'menu_name'          => __('Staff', 'backstage-venue-manager'),
        ),
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'    => false,
        'show_in_rest'        => false,
        'capability_type'     => 'post',
        'supports'            => array('title', 'thumbnail'),
        'taxonomies'          => array('vms_staff_role'),
        'has_archive'         => false,
        'rewrite'             => false,
        'menu_position'       => 27,
        'menu_icon'           => 'dashicons-id-alt',
    ));

    // Taxonomy: Staff Roles (editable in UI)
    register_taxonomy('vms_staff_role', array('vms_staff'), array(
        'labels' => array(
            'name'          => __('Staff Roles', 'backstage-venue-manager'),
            'singular_name' => __('Staff Role', 'backstage-venue-manager'),
            'menu_name'     => __('Roles', 'backstage-venue-manager'),
            'all_items'     => __('All Roles', 'backstage-venue-manager'),
            'edit_item'     => __('Edit Role', 'backstage-venue-manager'),
            'view_item'     => __('View Role', 'backstage-venue-manager'),
            'update_item'   => __('Update Role', 'backstage-venue-manager'),
            'add_new_item'  => __('Add New Role', 'backstage-venue-manager'),
            'new_item_name' => __('New Role Name', 'backstage-venue-manager'),
            'search_items'  => __('Search Roles', 'backstage-venue-manager'),
        ),
        'public'            => false,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => false,
        'hierarchical'      => true,
        'rewrite'           => false,
    ));
}, 5);

/**
 * Seed default roles once (editable later in UI).
 */
add_action('admin_init', function () {
    if (get_option('vms_staff_roles_seeded')) return;

    $defaults = array('Bar', 'Cleanup', 'Ticket Checker');

    foreach ($defaults as $name) {
        if (!term_exists($name, 'vms_staff_role')) {
            wp_insert_term($name, 'vms_staff_role');
        }
    }

    update_option('vms_staff_roles_seeded', 1);
});

add_action('save_post_vms_staff', function (int $post_id, WP_Post $post, bool $update): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!taxonomy_exists('vms_staff_role')) return;
    if (!isset($_POST['tax_input']) || !is_array($_POST['tax_input'])) return;
    if (!array_key_exists('vms_staff_role', $_POST['tax_input'])) return;

    $raw_terms = wp_unslash($_POST['tax_input']['vms_staff_role']);
    $term_ids = is_array($raw_terms)
        ? array_values(array_filter(array_map('absint', $raw_terms)))
        : array();

    wp_set_post_terms($post_id, $term_ids, 'vms_staff_role', false);
}, 30, 3);


add_action('add_meta_boxes_vms_staff', function (): void {
    add_meta_box(
        'vms-staff-qualifications',
        __('Qualifications / Licenses', 'backstage-venue-manager'),
        function (WP_Post $post): void {
            $rows = function_exists('vms_staffing_get_staff_qualifications')
                ? (array) vms_staffing_get_staff_qualifications((int) $post->ID)
                : array();
            if (empty($rows)) {
                $rows = array(array(
                    'id' => '',
                    'name' => '',
                    'authority' => '',
                    'credential_number' => '',
                    'issue_date' => '',
                    'expiration_date' => '',
                    'status' => 'active',
                    'proof_url' => '',
                    'attachment_id' => '',
                    'notes' => '',
                    'source' => 'admin',
                    'submitted_by' => '',
                    'submitted_at' => '',
                    'reviewed_by' => '',
                    'reviewed_at' => '',
                ));
            }
            $status_options = array(
                'active' => __('Approved', 'backstage-venue-manager'),
                'pending_verification' => __('Pending Review', 'backstage-venue-manager'),
                'rejected' => __('Rejected', 'backstage-venue-manager'),
                'expired' => __('Expired', 'backstage-venue-manager'),
                'inactive' => __('Inactive', 'backstage-venue-manager'),
            );
            wp_nonce_field('vms_staff_qualifications_save', 'vms_staff_qualifications_nonce');
            $pending_review_count = 0;
            foreach ($rows as $pending_row) {
                if (is_array($pending_row) && sanitize_key((string) ($pending_row['status'] ?? '')) === 'pending_verification') {
                    $pending_review_count++;
                }
            }
            ?>
            <p class="description"><?php esc_html_e('Track certifications, licenses, and other role qualifications for staff scheduling checks. Staff uploads stay Pending Review until an admin approves them.', 'backstage-venue-manager'); ?></p>
            <?php if ($pending_review_count > 0) : ?>
                <div class="notice notice-warning inline vms-staff-qualification-review-notice">
                    <p><strong><?php echo esc_html(sprintf(_n('%d uploaded certification needs review.', '%d uploaded certifications need review.', $pending_review_count, 'backstage-venue-manager'), $pending_review_count)); ?></strong> <?php esc_html_e('Change the status to Approved or Rejected, add a review note if needed, then update this staff profile.', 'backstage-venue-manager'); ?></p>
                </div>
            <?php endif; ?>
            <div class="vms-staff-qualification-list" id="vms-staff-qualifications-list" data-vms-staff-qualification-list="1">
                <?php foreach ($rows as $idx => $row) :
                    $status = sanitize_key((string) ($row['status'] ?? 'active'));
                    if (!isset($status_options[$status])) {
                        $status = 'active';
                    }
                    $proof_url = !empty($row['proof_url']) ? (string) $row['proof_url'] : '';
                    $submitted_by = !empty($row['submitted_by']) ? get_user_by('id', absint($row['submitted_by'])) : null;
                    $reviewed_by = !empty($row['reviewed_by']) ? get_user_by('id', absint($row['reviewed_by'])) : null;
                    $submitted_label = !empty($row['submitted_at']) ? wp_date('M j, Y g:ia', absint($row['submitted_at']), wp_timezone()) : '';
                    $reviewed_label = !empty($row['reviewed_at']) ? wp_date('M j, Y g:ia', absint($row['reviewed_at']), wp_timezone()) : '';
                ?>
                    <div class="vms-staff-qualification-card" data-vms-staff-qualification-row="1">
                        <input type="hidden" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][id]" value="<?php echo esc_attr((string) ($row['id'] ?? '')); ?>">
                        <input type="hidden" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][attachment_id]" value="<?php echo esc_attr((string) ($row['attachment_id'] ?? '')); ?>">
                        <input type="hidden" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][source]" value="<?php echo esc_attr((string) ($row['source'] ?? 'admin')); ?>">
                        <input type="hidden" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][submitted_by]" value="<?php echo esc_attr((string) ($row['submitted_by'] ?? '')); ?>">
                        <input type="hidden" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][submitted_at]" value="<?php echo esc_attr((string) ($row['submitted_at'] ?? '')); ?>">
                        <input type="hidden" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][reviewed_by]" value="<?php echo esc_attr((string) ($row['reviewed_by'] ?? '')); ?>">
                        <input type="hidden" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][reviewed_at]" value="<?php echo esc_attr((string) ($row['reviewed_at'] ?? '')); ?>">

                        <div class="vms-staff-qualification-card__grid vms-staff-qualification-card__grid--primary">
                            <label>
                                <span><?php esc_html_e('Qualification', 'backstage-venue-manager'); ?></span>
                                <input type="text" class="regular-text" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][name]" value="<?php echo esc_attr((string) ($row['name'] ?? '')); ?>">
                            </label>
                            <label>
                                <span><?php esc_html_e('Authority', 'backstage-venue-manager'); ?></span>
                                <input type="text" class="regular-text" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][authority]" value="<?php echo esc_attr((string) ($row['authority'] ?? '')); ?>">
                            </label>
                            <label>
                                <span><?php esc_html_e('Credential #', 'backstage-venue-manager'); ?></span>
                                <input type="text" class="regular-text" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][credential_number]" value="<?php echo esc_attr((string) ($row['credential_number'] ?? '')); ?>">
                            </label>
                        </div>
                        <div class="vms-staff-qualification-card__grid vms-staff-qualification-card__grid--secondary">
                            <label>
                                <span><?php esc_html_e('Issue date', 'backstage-venue-manager'); ?></span>
                                <input type="date" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][issue_date]" value="<?php echo esc_attr((string) ($row['issue_date'] ?? '')); ?>">
                            </label>
                            <label>
                                <span><?php esc_html_e('Expiration', 'backstage-venue-manager'); ?></span>
                                <input type="date" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][expiration_date]" value="<?php echo esc_attr((string) ($row['expiration_date'] ?? '')); ?>">
                            </label>
                            <label>
                                <span><?php esc_html_e('Status', 'backstage-venue-manager'); ?></span>
                                <select name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][status]">
                                    <?php foreach ($status_options as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="vms-staff-qualification-card__proof">
                                <span><?php esc_html_e('Proof URL', 'backstage-venue-manager'); ?></span>
                                <input type="url" class="regular-text" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][proof_url]" value="<?php echo esc_attr($proof_url); ?>">
                                <?php if ($proof_url !== '') : ?>
                                    <a href="<?php echo esc_url($proof_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('View proof', 'backstage-venue-manager'); ?></a>
                                <?php endif; ?>
                            </label>
                        </div>
                        <div class="vms-staff-qualification-card__grid vms-staff-qualification-card__grid--notes">
                            <label class="vms-staff-qualification-card__notes">
                                <span><?php esc_html_e('Review note / rejection reason', 'backstage-venue-manager'); ?></span>
                                <input type="text" class="regular-text" name="vms_staff_qualifications[<?php echo esc_attr((string) $idx); ?>][notes]" value="<?php echo esc_attr((string) ($row['notes'] ?? '')); ?>">
                            </label>
                            <div class="vms-staff-qualification-card__actions">
                                <?php if ($submitted_label !== '') : ?>
                                    <span class="description"><?php echo esc_html(sprintf(__('Submitted %s', 'backstage-venue-manager'), $submitted_label)); ?><?php echo $submitted_by instanceof WP_User ? esc_html(' · ' . ($submitted_by->display_name ?: $submitted_by->user_login)) : ''; ?></span>
                                <?php endif; ?>
                                <?php if ($reviewed_label !== '') : ?>
                                    <span class="description"><?php echo esc_html(sprintf(__('Reviewed %s', 'backstage-venue-manager'), $reviewed_label)); ?><?php echo $reviewed_by instanceof WP_User ? esc_html(' · ' . ($reviewed_by->display_name ?: $reviewed_by->user_login)) : ''; ?></span>
                                <?php endif; ?>
                                <button type="button" class="button vms-staff-qualification-remove"><?php esc_html_e('Remove', 'backstage-venue-manager'); ?></button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p><button type="button" class="button" id="vms-staff-qualification-add"><?php esc_html_e('Add qualification', 'backstage-venue-manager'); ?></button></p>
            <script>
            (function(){
                var addBtn = document.getElementById('vms-staff-qualification-add');
                var wrap = document.getElementById('vms-staff-qualifications-list');
                if (!addBtn || !wrap) return;
                function hidden(idx, key, value){
                    return '<input type="hidden" name="vms_staff_qualifications['+idx+']['+key+']" value="'+(value || '')+'">';
                }
                function buildRow(idx){
                    var card = document.createElement('div');
                    card.className = 'vms-staff-qualification-card';
                    card.setAttribute('data-vms-staff-qualification-row', '1');
                    card.innerHTML =
                        hidden(idx, 'id', '') + hidden(idx, 'attachment_id', '') + hidden(idx, 'source', 'admin') + hidden(idx, 'submitted_by', '') + hidden(idx, 'submitted_at', '') + hidden(idx, 'reviewed_by', '') + hidden(idx, 'reviewed_at', '') +
                        '<div class="vms-staff-qualification-card__grid vms-staff-qualification-card__grid--primary">' +
                            '<label><span><?php echo esc_js(__('Qualification', 'backstage-venue-manager')); ?></span><input type="text" class="regular-text" name="vms_staff_qualifications['+idx+'][name]" value=""></label>' +
                            '<label><span><?php echo esc_js(__('Authority', 'backstage-venue-manager')); ?></span><input type="text" class="regular-text" name="vms_staff_qualifications['+idx+'][authority]" value=""></label>' +
                            '<label><span><?php echo esc_js(__('Credential #', 'backstage-venue-manager')); ?></span><input type="text" class="regular-text" name="vms_staff_qualifications['+idx+'][credential_number]" value=""></label>' +
                        '</div>' +
                        '<div class="vms-staff-qualification-card__grid vms-staff-qualification-card__grid--secondary">' +
                            '<label><span><?php echo esc_js(__('Issue date', 'backstage-venue-manager')); ?></span><input type="date" name="vms_staff_qualifications['+idx+'][issue_date]" value=""></label>' +
                            '<label><span><?php echo esc_js(__('Expiration', 'backstage-venue-manager')); ?></span><input type="date" name="vms_staff_qualifications['+idx+'][expiration_date]" value=""></label>' +
                            '<label><span><?php echo esc_js(__('Status', 'backstage-venue-manager')); ?></span><select name="vms_staff_qualifications['+idx+'][status]"><option value="active"><?php echo esc_js(__('Approved', 'backstage-venue-manager')); ?></option><option value="pending_verification"><?php echo esc_js(__('Pending Review', 'backstage-venue-manager')); ?></option><option value="rejected"><?php echo esc_js(__('Rejected', 'backstage-venue-manager')); ?></option><option value="expired"><?php echo esc_js(__('Expired', 'backstage-venue-manager')); ?></option><option value="inactive"><?php echo esc_js(__('Inactive', 'backstage-venue-manager')); ?></option></select></label>' +
                            '<label class="vms-staff-qualification-card__proof"><span><?php echo esc_js(__('Proof URL', 'backstage-venue-manager')); ?></span><input type="url" class="regular-text" name="vms_staff_qualifications['+idx+'][proof_url]" value=""></label>' +
                        '</div>' +
                        '<div class="vms-staff-qualification-card__grid vms-staff-qualification-card__grid--notes">' +
                            '<label class="vms-staff-qualification-card__notes"><span><?php echo esc_js(__('Review note / rejection reason', 'backstage-venue-manager')); ?></span><input type="text" class="regular-text" name="vms_staff_qualifications['+idx+'][notes]" value=""></label>' +
                            '<div class="vms-staff-qualification-card__actions"><button type="button" class="button vms-staff-qualification-remove"><?php echo esc_js(__('Remove', 'backstage-venue-manager')); ?></button></div>' +
                        '</div>';
                    return card;
                }
                addBtn.addEventListener('click', function(){
                    var idx = wrap.querySelectorAll('[data-vms-staff-qualification-row="1"]').length;
                    wrap.appendChild(buildRow(idx));
                });
                wrap.addEventListener('click', function(e){
                    var btn = e.target.closest('.vms-staff-qualification-remove');
                    if (!btn) return;
                    e.preventDefault();
                    var rows = wrap.querySelectorAll('[data-vms-staff-qualification-row="1"]');
                    if (rows.length <= 1) {
                        rows[0].querySelectorAll('input').forEach(function(input){ input.value=''; });
                        var sel = rows[0].querySelector('select');
                        if (sel) sel.value = 'active';
                        return;
                    }
                    var row = btn.closest('[data-vms-staff-qualification-row="1"]');
                    if (row) row.remove();
                });
            })();
            </script>
            <?php
        },
        'vms_staff',
        'normal',
        'default'
    );
});

add_action('save_post_vms_staff', function (int $post_id, WP_Post $post, bool $update): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['vms_staff_qualifications_nonce']) || !wp_verify_nonce((string) $_POST['vms_staff_qualifications_nonce'], 'vms_staff_qualifications_save')) return;

    $rows = (isset($_POST['vms_staff_qualifications']) && is_array($_POST['vms_staff_qualifications']))
        ? (array) wp_unslash($_POST['vms_staff_qualifications'])
        : array();
    if (function_exists('vms_staffing_save_staff_qualifications_with_review')) {
        vms_staffing_save_staff_qualifications_with_review($post_id, $rows, get_current_user_id());
    } elseif (function_exists('vms_staffing_save_staff_qualifications')) {
        vms_staffing_save_staff_qualifications($post_id, $rows);
    }
}, 40, 3);
