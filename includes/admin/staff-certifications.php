<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('bvmgr_staff_certifications_admin_badge_markup')) {
    function bvmgr_staff_certifications_admin_badge_markup(int $count): string
    {
        $count = max(0, $count);
        if ($count <= 0) {
            return '';
        }
        return ' <span class="update-plugins count-' . esc_attr((string) $count) . '"><span class="plugin-count">' . esc_html((string) $count) . '</span></span>';
    }
}

if (!function_exists('bvmgr_staff_certifications_pending_count')) {
    function bvmgr_staff_certifications_pending_count(): int
    {
        return function_exists('bvmgr_staffing_get_pending_staff_qualification_count')
            ? (int) bvmgr_staffing_get_pending_staff_qualification_count()
            : 0;
    }
}

if (!function_exists('bvmgr_staff_certifications_get_pending_review_items')) {
    /**
     * @return array<int|string,mixed>
     */
    function bvmgr_staff_certifications_get_pending_review_items(): array
    {
        return function_exists('bvmgr_staffing_get_staff_qualification_review_items')
            ? (array) bvmgr_staffing_get_staff_qualification_review_items('pending_verification')
            : array();
    }
}

if (!function_exists('bvmgr_staff_certifications_admin_menu_label')) {
    function bvmgr_staff_certifications_admin_menu_label(string $label = ''): string
    {
        $label = $label !== '' ? $label : __('Staff Certifications', 'backstage-venue-manager');
        return esc_html($label) . bvmgr_staff_certifications_admin_badge_markup(bvmgr_staff_certifications_pending_count());
    }
}

if (!function_exists('bvmgr_staff_certifications_render_empty_state_notice')) {
    /**
     * @param array<int|string,mixed> $pending
     */
    function bvmgr_staff_certifications_render_empty_state_notice(array $pending): void
    {
        if (!empty($pending)) {
            return;
        }

        echo '<div class="notice notice-success inline"><p>' . esc_html__('No staff certifications are waiting for review.', 'backstage-venue-manager') . '</p></div>';
    }
}

if (!function_exists('bvmgr_render_staff_certifications_admin_page')) {
    function bvmgr_render_staff_certifications_admin_page(): void
    {
        $pending = bvmgr_staff_certifications_get_pending_review_items();

        if (function_exists('bvmgr_admin_ui_render_shell')) {
            bvmgr_admin_ui_render_shell(
                array(
                    'title' => __('Staff Certifications', 'backstage-venue-manager'),
                    'subtitle' => __('Review staff-uploaded certificates, licenses, and permits that are waiting on admin approval.', 'backstage-venue-manager'),
                    'shell_id' => 'vms-staff-certifications-admin',
                    'notices_callback' => function () use ($pending): void {
                        bvmgr_staff_certifications_render_empty_state_notice($pending);
                    },
                ),
                function () use ($pending): void {
                    bvmgr_render_staff_certifications_admin_page_content($pending);
                }
            );
            return;
        }

        echo '<div class="wrap" id="vms-staff-certifications-admin">';
        echo '<h1>' . esc_html__('Staff Certifications', 'backstage-venue-manager') . '</h1>';
        bvmgr_render_staff_certifications_admin_page_content($pending);
        bvmgr_staff_certifications_render_empty_state_notice($pending);
        echo '</div>';
    }
}

if (!function_exists('bvmgr_render_staff_certifications_admin_page_content')) {
    /**
     * @param array<int|string,mixed>|null $pending
     */
    function bvmgr_render_staff_certifications_admin_page_content(?array $pending = null): void
    {
        if ($pending === null) {
            $pending = bvmgr_staff_certifications_get_pending_review_items();
        }

        echo '<div class="vms-admin-card vms-staff-certifications-summary">';
        echo '<h2>' . esc_html__('Pending Review', 'backstage-venue-manager') . '</h2>';
        echo '<p class="description">' . esc_html__('Staff uploads stay Pending Review until an admin approves or rejects them from the staff profile.', 'backstage-venue-manager') . '</p>';
        echo '<p class="vms-staff-certifications-count"><strong>' . esc_html((string) count($pending)) . '</strong> ' . esc_html(_n('certification needs review', 'certifications need review', count($pending), 'backstage-venue-manager')) . '</p>';
        echo '</div>';

        if (empty($pending)) {
            return;
        }

        echo '<table class="widefat striped vms-staff-certifications-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Staff', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Certification', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Submitted', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Expiration', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Proof', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Action', 'backstage-venue-manager') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($pending as $item) {
            $staff_id = absint((int) ($item['staff_id'] ?? 0));
            $row = isset($item['row']) && is_array($item['row']) ? $item['row'] : array();
            $staff_name = (string) ($item['staff_name'] ?? '');
            if ($staff_name === '') {
                $staff_name = __('Staff member', 'backstage-venue-manager');
            }
            $qualification = (string) ($row['name'] ?? __('Certification', 'backstage-venue-manager'));
            $submitted_at = !empty($row['submitted_at']) ? wp_date('M j, Y g:ia', absint($row['submitted_at']), wp_timezone()) : '—';
            $expiration = !empty($row['expiration_date']) ? (string) $row['expiration_date'] : '—';
            $proof_url = !empty($row['proof_download_url']) ? (string) $row['proof_download_url'] : (!empty($row['proof_url']) ? (string) $row['proof_url'] : '');
            $edit_url = $staff_id > 0 ? bvmgr_staffing_staff_qualification_review_url($staff_id) : admin_url('edit.php?post_type=vms_staff');

            echo '<tr>';
            echo '<td><strong>' . esc_html($staff_name) . '</strong></td>';
            echo '<td>' . esc_html($qualification) . '</td>';
            echo '<td>' . esc_html($submitted_at) . '</td>';
            echo '<td>' . esc_html($expiration) . '</td>';
            echo '<td>' . ($proof_url !== '' ? '<a href="' . esc_url($proof_url) . '" target="_blank" rel="noopener">' . esc_html__('View file', 'backstage-venue-manager') . '</a>' : '—') . '</td>';
            echo '<td><a class="button button-primary" href="' . esc_url($edit_url) . '">' . esc_html__('Review on Staff Profile', 'backstage-venue-manager') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}

if (!function_exists('bvmgr_staff_certifications_get_pending_review_warning_context')) {
    /**
     * @return array{show:bool,pending_count:int,review_url:string}
     */
    function bvmgr_staff_certifications_get_pending_review_warning_context(): array
    {
        if (!current_user_can('manage_options')) {
            return array(
                'show' => false,
                'pending_count' => 0,
                'review_url' => '',
            );
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && isset($screen->id) && $screen->id === 'vms_page_vms-staff-certifications') {
            return array(
                'show' => false,
                'pending_count' => 0,
                'review_url' => '',
            );
        }

        $pending_count = max(0, (int) bvmgr_staff_certifications_pending_count());
        if ($pending_count <= 0) {
            return array(
                'show' => false,
                'pending_count' => 0,
                'review_url' => '',
            );
        }

        return array(
            'show' => true,
            'pending_count' => $pending_count,
            'review_url' => admin_url('admin.php?page=vms-staff-certifications'),
        );
    }
}

if (!function_exists('bvmgr_staff_certifications_render_pending_review_warning')) {
    /**
     * @param array{show:bool,pending_count:int,review_url:string} $context
     */
    function bvmgr_staff_certifications_render_pending_review_warning(array $context): void
    {
        if (empty($context['show'])) {
            return;
        }

        $pending_count = max(0, (int) ($context['pending_count'] ?? 0));
        if ($pending_count <= 0) {
            return;
        }

        $review_url = isset($context['review_url']) ? (string) $context['review_url'] : '';

        echo '<div class="notice notice-warning is-dismissible vms-staff-certifications-admin-notice">';
        /* translators: %d: number of staff certifications awaiting review. */
        echo '<p><strong>' . esc_html(sprintf(_n('%d staff certification needs review.', '%d staff certifications need review.', $pending_count, 'backstage-venue-manager'), $pending_count)) . '</strong> ';
        echo '<a href="' . esc_url($review_url) . '">' . esc_html__('Open review queue', 'backstage-venue-manager') . '</a></p>';
        echo '</div>';
    }
}

if (!function_exists('bvmgr_staff_certifications_render_pending_review_admin_notice')) {
    function bvmgr_staff_certifications_render_pending_review_admin_notice(): void
    {
        bvmgr_staff_certifications_render_pending_review_warning(bvmgr_staff_certifications_get_pending_review_warning_context());
    }
}

add_action('admin_notices', 'bvmgr_staff_certifications_render_pending_review_admin_notice');
