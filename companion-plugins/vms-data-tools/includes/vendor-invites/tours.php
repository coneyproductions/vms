<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_register_tours')) {
    /**
     * @param array<int,array<string,mixed>> $tours
     * @return array<int,array<string,mixed>>
     */
    function vms_dt_vio_register_tours(array $tours): array
    {
        $tours[] = array(
            'id' => vms_dt_vio_help_tour_id(),
            'title' => __('Vendor Invites Overview', 'vms-data-tools'),
            'description' => __('Preview invite batches, verify MailPoet routing, manage interest submissions, and audit claim activity from one screen.', 'vms-data-tools'),
            'version' => 3,
            'contexts' => array(
                array(
                    'context_key' => 'vms-dt-vendor-invites',
                    'screen_id' => 'tools_page_' . vms_dt_get_menu_slug_vendor_invites(),
                    'url' => 'admin.php?page=' . vms_dt_get_menu_slug_vendor_invites(),
                ),
            ),
            'steps' => array(
                array(
                    'anchor' => 'vendor-invites.help',
                    'title' => __('Guided Help', 'vms-data-tools'),
                    'content' => __('Use this launcher any time you need a guided pass through Vendor Invites instead of guessing at the workflow.', 'vms-data-tools'),
                    'placement' => 'bottom',
                ),
                array(
                    'anchor' => 'vendor-invites.tabs',
                    'title' => __('Work Areas', 'vms-data-tools'),
                    'content' => __('The Send, Settings, Interest Queue, and Invite Logs tabs split batching, MailPoet setup, vendor responses, and audit history into clear work areas.', 'vms-data-tools'),
                    'placement' => 'bottom',
                ),
                array(
                    'anchor' => 'vendor-invites.interest.queue',
                    'title' => __('Interest Queue', 'vms-data-tools'),
                    'content' => __('Pending vendor responses land here so you can assign a vendor to the Event Plan or decline submissions without leaving Vendor Invites.', 'vms-data-tools'),
                    'placement' => 'bottom',
                ),
                array(
                    'anchor' => 'vendor-invites.interest.filters',
                    'title' => __('Queue Filters', 'vms-data-tools'),
                    'content' => __('Filter the queue by vendor type, date range, or submission status to work only the responses you need.', 'vms-data-tools'),
                    'placement' => 'top',
                ),
                array(
                    'anchor' => 'vendor-invites.send.filters',
                    'title' => __('Batch Filters', 'vms-data-tools'),
                    'content' => __('Pick vendor type, status, language routing, and venue/date scope here before building a preview batch.', 'vms-data-tools'),
                    'placement' => 'top',
                ),
                array(
                    'anchor' => 'vendor-invites.send.preview',
                    'title' => __('Preview Before Commit', 'vms-data-tools'),
                    'content' => __('Preview is where you validate the claim link payload, open-dates snippet, and MailPoet tag before committing any invite rows.', 'vms-data-tools'),
                    'placement' => 'top',
                ),
                array(
                    'anchor' => 'vendor-invites.settings.verify',
                    'title' => __('MailPoet Bridge Settings', 'vms-data-tools'),
                    'content' => __('These settings control whether invite commits can write subscribers, custom fields, and trigger tags into MailPoet.', 'vms-data-tools'),
                    'placement' => 'bottom',
                ),
                array(
                    'anchor' => 'vendor-invites.settings.verify-action',
                    'title' => __('Verify Setup', 'vms-data-tools'),
                    'content' => __('Run setup verification after changing tag bases or MailPoet availability so the bridge can confirm tags and report field-sync issues early.', 'vms-data-tools'),
                    'placement' => 'top',
                ),
                array(
                    'anchor' => 'vendor-invites.logs.audit',
                    'title' => __('Invite Audit Trail', 'vms-data-tools'),
                    'content' => __('Every invite send, claim attempt, resend, and admin-generated claim link is recorded here so operators can audit activity before deciding whether anything should be archived or purged.', 'vms-data-tools'),
                    'placement' => 'bottom',
                ),
                array(
                    'anchor' => 'vendor-invites.logs.retention-panel',
                    'title' => __('Retention Controls', 'vms-data-tools'),
                    'content' => __('Archive hides old or test logs from the normal working view without erasing them. Restore brings archived rows back. Purge is reserved for archived cleanup rows and writes a retention audit before anything is removed.', 'vms-data-tools'),
                    'placement' => 'bottom',
                ),
                array(
                    'anchor' => 'vendor-invites.logs.retention-preview',
                    'title' => __('Preview Before Cleanup', 'vms-data-tools'),
                    'content' => __('Retention actions use a preview so you can see how many rows are eligible, which ones will be skipped, and whether any unused claim tokens would also be removed before you commit the cleanup.', 'vms-data-tools'),
                    'placement' => 'bottom',
                ),
                array(
                    'anchor' => 'vendor-invites.logs.table',
                    'title' => __('Row-Level Actions', 'vms-data-tools'),
                    'content' => __('Use the log table to filter active versus archived history, resend invite rows, or generate a fresh claim link while retention cleanup stays in the preview-driven controls above.', 'vms-data-tools'),
                    'placement' => 'top',
                ),
            ),
        );

        return $tours;
    }
}
add_filter('vms_register_tours', 'vms_dt_vio_register_tours');
