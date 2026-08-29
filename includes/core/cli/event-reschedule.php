<?php

defined('ABSPATH') || exit;

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

if (!class_exists('BVMGR_CLI_Event_Reschedule_Command')) {
    final class BVMGR_CLI_Event_Reschedule_Command
    {
        /**
         * Preview or apply a published Event Plan occurrence change/repair.
         *
         * ## OPTIONS
         *
         * <event-plan-id>
         * : Exact Event Plan post ID.
         *
         * --old-start=<local-datetime>
         * : Expected old local occurrence, for example "2026-09-19 19:00".
         *
         * --new-start=<local-datetime>
         * : New/current local occurrence, for example "2026-09-12 19:00".
         *
         * --reason=<reason>
         * : date_correction or rescheduled.
         *
         * [--dry-run]
         * : Analyze only. Exactly one of --dry-run or --apply is required.
         *
         * [--apply]
         * : Apply through the canonical transactional service.
         *
         * [--confirm=<token>]
         * : Required with --apply. Must be RESCHEDULE.
         *
         * ## EXAMPLES
         *
         *     wp --user=1 bvmgr event reschedule 5568 --old-start="2026-09-19 19:00" --new-start="2026-09-12 19:00" --reason=date_correction --dry-run
         *     wp --user=1 bvmgr event reschedule 5568 --old-start="2026-09-19 19:00" --new-start="2026-09-12 19:00" --reason=date_correction --apply --confirm=RESCHEDULE
         *
         * @when after_wp_load
         *
         * @param array<int,string> $args
         * @param array<string,mixed> $assoc_args
         */
        public function __invoke(array $args, array $assoc_args): void
        {
            $plan_id = absint($args[0] ?? 0);
            $old_start = sanitize_text_field((string) ($assoc_args['old-start'] ?? ''));
            $new_start = sanitize_text_field((string) ($assoc_args['new-start'] ?? ''));
            $reason = sanitize_key((string) ($assoc_args['reason'] ?? ''));
            $dry_run = array_key_exists('dry-run', $assoc_args);
            $apply = array_key_exists('apply', $assoc_args);

            if ($plan_id <= 0 || $old_start === '' || $new_start === '' || $reason === '') {
                WP_CLI::error('Event Plan ID, --old-start, --new-start, and --reason are required.');
            }
            if ($dry_run === $apply) {
                WP_CLI::error('Specify exactly one of --dry-run or --apply.');
            }
            $user = wp_get_current_user();
            if (!$user instanceof WP_User || (int) $user->ID <= 0) {
                WP_CLI::error('An authenticated WordPress user is required. Pass WP-CLI global --user=<id|login|email> before the bvmgr command.');
            }
            if (!user_can($user, 'edit_post', $plan_id)) {
                WP_CLI::error('The authenticated WordPress user cannot edit this Event Plan.');
            }
            WP_CLI::log('Actor user ID: ' . (int) $user->ID);

            $preview = bvmgr_event_occurrence_preview($plan_id, $old_start, $new_start, $reason);
            $this->render_preview($preview);
            if (!$apply) {
                if (empty($preview['allowed'])) {
                    WP_CLI::warning('APPLY WOULD BE BLOCKED. Resolve every ambiguity before continuing.');
                    return;
                }
                WP_CLI::success('Dry run complete. APPLY would be allowed with the same inputs and --apply --confirm=RESCHEDULE.');
                return;
            }

            if ((string) ($assoc_args['confirm'] ?? '') !== 'RESCHEDULE') {
                WP_CLI::error('--apply requires --confirm=RESCHEDULE.');
            }
            if (empty($preview['allowed'])) {
                WP_CLI::error('APPLY blocked by preview ambiguity. No changes were made.');
            }

            $result = bvmgr_event_occurrence_apply(
                $plan_id,
                $old_start,
                $new_start,
                $reason,
                (int) $user->ID,
                bvmgr_event_occurrence_preview_fingerprint($preview)
            );
            if (empty($result['ok'])) {
                $rollback = !empty($result['rolled_back']) ? ' Transaction rolled back.' : '';
                WP_CLI::error((string) ($result['message'] ?? 'Occurrence operation failed.') . $rollback);
            }
            WP_CLI::success((string) ($result['message'] ?? 'Occurrence operation applied.'));
            WP_CLI::log('Operation ID: ' . (string) ($result['operation_id'] ?? 'existing'));
            $this->render_integrity((array) ($result['integrity'] ?? array()));
        }

        private function render_preview(array $preview): void
        {
            WP_CLI::log('Event Plan: #' . (int) ($preview['plan_id'] ?? 0) . ' ' . (string) ($preview['plan_title'] ?? ''));
            WP_CLI::log('Mode: ' . ((string) ($preview['mode'] ?? '') ?: 'blocked'));
            WP_CLI::log('Canonical current: ' . (string) ($preview['canonical']['start_local'] ?? 'invalid'));
            WP_CLI::log('Expected old: ' . (string) ($preview['old']['start_local'] ?? 'invalid'));
            WP_CLI::log('Requested new: ' . (string) ($preview['new']['start_local'] ?? 'invalid'));
            WP_CLI::log('Linked calendar event: #' . (int) ($preview['tec_event_id'] ?? 0));
            foreach ((array) ($preview['counts'] ?? array()) as $label => $value) {
                WP_CLI::log(str_replace('_', ' ', ucfirst((string) $label)) . ': ' . (int) $value);
            }
            foreach ((array) ($preview['categories'] ?? array()) as $category_kind => $category_rows) {
                foreach ((array) $category_rows as $category_label => $category_count) {
                    WP_CLI::log(ucfirst((string) $category_kind) . ' / ' . (string) $category_label . ': ' . (int) $category_count);
                }
            }
            WP_CLI::log('Products: ' . implode(', ', array_map('strval', (array) ($preview['product_ids'] ?? array()))));
            WP_CLI::log('Attendees: ' . count((array) ($preview['attendee_ids'] ?? array())));
            foreach ((array) ($preview['notification_rows'] ?? array()) as $notification) {
                $entitlements = array_map(static function (array $entitlement): string {
                    return sprintf(
                        '%s x%d (item #%d)',
                        (string) ($entitlement['label'] ?? 'Entitlement'),
                        (int) ($entitlement['quantity'] ?? 0),
                        (int) ($entitlement['order_item_id'] ?? 0)
                    );
                }, (array) ($notification['entitlements'] ?? array()));
                WP_CLI::log(sprintf(
                    'Affected order #%1$d | %2$s | %3$s | %4$s',
                    (int) ($notification['order_id'] ?? 0),
                    (string) (($notification['customer_name'] ?? '') ?: 'Customer'),
                    (string) (($notification['customer_email'] ?? '') ?: 'no email'),
                    implode('; ', $entitlements)
                ));
            }
            foreach ((array) ($preview['warnings'] ?? array()) as $warning) {
                WP_CLI::warning((string) $warning);
            }
            foreach ((array) ($preview['ambiguities'] ?? array()) as $ambiguity) {
                WP_CLI::warning('AMBIGUITY: ' . (string) $ambiguity);
            }
            WP_CLI::log('Apply allowed: ' . (!empty($preview['allowed']) ? 'yes' : 'NO'));
        }

        private function render_integrity(array $integrity): void
        {
            WP_CLI::log('Integrity: ' . (!empty($integrity['ok']) ? 'PASS' : 'FAIL'));
            WP_CLI::log('Mismatched active units: ' . (int) ($integrity['mismatch_units'] ?? 0));
            WP_CLI::log('Mismatched admissions: ' . (int) ($integrity['mismatch_admission_units'] ?? 0));
            WP_CLI::log('Mismatched reservations: ' . (int) ($integrity['mismatch_reservation_units'] ?? 0));
            foreach ((array) ($integrity['messages'] ?? array()) as $message) {
                WP_CLI::log(' - ' . (string) $message);
            }
        }
    }
}

if (!class_exists('BVMGR_CLI_Event_Integrity_Command')) {
    final class BVMGR_CLI_Event_Integrity_Command
    {
        /**
         * Check current/effective occurrence integrity for an Event Plan.
         *
         * ## OPTIONS
         *
         * <event-plan-id>
         * : Exact Event Plan post ID.
         *
         * ## EXAMPLES
         *
         *     wp vms event integrity 5568
         *
         * @when after_wp_load
         *
         * @param array<int,string> $args
         */
        public function __invoke(array $args): void
        {
            $plan_id = absint($args[0] ?? 0);
            if ($plan_id <= 0) {
                WP_CLI::error('Event Plan ID is required.');
            }
            $integrity = bvmgr_event_occurrence_integrity($plan_id);
            WP_CLI::log('Event Plan: #' . $plan_id . ' ' . get_the_title($plan_id));
            WP_CLI::log('Canonical date: ' . (string) ($integrity['canonical_date'] ?? ''));
            WP_CLI::log('Mismatched active units: ' . (int) ($integrity['mismatch_units'] ?? 0));
            WP_CLI::log('Mismatched admissions: ' . (int) ($integrity['mismatch_admission_units'] ?? 0));
            WP_CLI::log('Mismatched reservations: ' . (int) ($integrity['mismatch_reservation_units'] ?? 0));
            WP_CLI::log('Mismatched products: ' . implode(', ', array_map('strval', (array) ($integrity['product_mismatches'] ?? array()))));
            WP_CLI::log('Mismatched attendees: ' . implode(', ', array_map('strval', (array) ($integrity['attendee_mismatches'] ?? array()))));
            foreach ((array) ($integrity['messages'] ?? array()) as $message) {
                WP_CLI::warning((string) $message);
            }
            if (empty($integrity['ok'])) {
                WP_CLI::error('Occurrence integrity check failed.');
            }
            WP_CLI::success('Occurrence integrity check passed.');
        }
    }
}

if (!class_exists('BVMGR_CLI_Event_Item_Name_Reconcile_Command')) {
    final class BVMGR_CLI_Event_Item_Name_Reconcile_Command
    {
        /**
         * Preview or apply current Woo order-item name reconciliation.
         *
         * ## OPTIONS
         *
         * <event-plan-id>
         * : Exact Event Plan post ID.
         *
         * [--operation-id=<uuid>]
         * : Restrict candidates to items stamped by one recorded occurrence operation.
         *
         * [--dry-run]
         * : Analyze only. Exactly one of --dry-run or --apply is required.
         *
         * [--apply]
         * : Change only eligible current Woo order-item display names.
         *
         * [--confirm=<token>]
         * : Required with --apply. Must be RECONCILE-NAMES.
         *
         * ## EXAMPLES
         *
         *     wp --user=1 bvmgr event reconcile-current-item-names 5568 --operation-id=de1814a7-5ada-4e6e-b587-46c1e80eff89 --dry-run
         *     wp --user=1 bvmgr event reconcile-current-item-names 5568 --operation-id=de1814a7-5ada-4e6e-b587-46c1e80eff89 --apply --confirm=RECONCILE-NAMES
         *
         * @when after_wp_load
         *
         * @param array<int,string> $args
         * @param array<string,mixed> $assoc_args
         */
        public function __invoke(array $args, array $assoc_args): void
        {
            $plan_id = absint($args[0] ?? 0);
            $operation_id = sanitize_text_field((string) ($assoc_args['operation-id'] ?? ''));
            $dry_run = array_key_exists('dry-run', $assoc_args);
            $apply = array_key_exists('apply', $assoc_args);
            if ($plan_id <= 0) {
                WP_CLI::error('Event Plan ID is required.');
            }
            if ($dry_run === $apply) {
                WP_CLI::error('Specify exactly one of --dry-run or --apply.');
            }
            $user = wp_get_current_user();
            if (!$user instanceof WP_User || (int) $user->ID <= 0) {
                WP_CLI::error('An authenticated WordPress user is required. Pass WP-CLI global --user=<id|login|email> before the bvmgr command.');
            }
            if (!user_can($user, 'edit_post', $plan_id)) {
                WP_CLI::error('The authenticated WordPress user cannot edit this Event Plan.');
            }
            WP_CLI::log('Actor user ID: ' . (int) $user->ID);

            $preview = bvmgr_event_occurrence_name_reconciliation_preview($plan_id, $operation_id);
            $this->render_preview($preview);
            if (!$apply) {
                if (empty($preview['allowed'])) {
                    WP_CLI::warning('APPLY WOULD BE BLOCKED. Resolve every ambiguity before continuing.');
                    return;
                }
                WP_CLI::success('Dry run complete. APPLY would be allowed with the same inputs and --apply --confirm=RECONCILE-NAMES.');
                return;
            }
            if ((string) ($assoc_args['confirm'] ?? '') !== 'RECONCILE-NAMES') {
                WP_CLI::error('--apply requires --confirm=RECONCILE-NAMES.');
            }
            if (empty($preview['allowed'])) {
                WP_CLI::error('APPLY blocked by preview ambiguity. No changes were made.');
            }

            $result = bvmgr_event_occurrence_name_reconciliation_apply(
                $plan_id,
                $operation_id,
                (int) $user->ID,
                bvmgr_event_occurrence_name_reconciliation_fingerprint($preview)
            );
            if (empty($result['ok'])) {
                $rollback = !empty($result['rolled_back']) ? ' Transaction rolled back.' : '';
                WP_CLI::error((string) ($result['message'] ?? 'Name reconciliation failed.') . $rollback);
            }
            WP_CLI::success((string) ($result['message'] ?? 'Current order-item names reconciled.'));
            WP_CLI::log('Changed order-item IDs: ' . implode(', ', array_map('strval', (array) ($result['changed_order_item_ids'] ?? array()))));
            WP_CLI::log('Integrity: ' . (!empty($result['integrity']['ok']) ? 'PASS' : 'FAIL'));
        }

        private function render_preview(array $preview): void
        {
            WP_CLI::log('Event Plan: #' . (int) ($preview['plan_id'] ?? 0) . ' ' . (string) ($preview['plan_title'] ?? ''));
            WP_CLI::log('Operation ID: ' . ((string) ($preview['operation_id'] ?? '') ?: 'not restricted'));
            WP_CLI::log('Current occurrence: ' . (string) ($preview['current_occurrence']['start_local'] ?? 'invalid'));
            foreach ((array) ($preview['counts'] ?? array()) as $label => $value) {
                WP_CLI::log(str_replace('_', ' ', ucfirst((string) $label)) . ': ' . (int) $value);
            }
            foreach ((array) ($preview['rows'] ?? array()) as $row) {
                WP_CLI::log(sprintf(
                    'Order #%1$d | item #%2$d | current: %3$s | proposed: %4$s | effective: %5$s | original snapshot: %6$s | safe: %7$s (%8$s)',
                    (int) ($row['order_id'] ?? 0),
                    (int) ($row['order_item_id'] ?? 0),
                    (string) ($row['current_name'] ?? ''),
                    (string) ($row['proposed_name'] ?? ''),
                    (string) ($row['current_effective_occurrence'] ?? ''),
                    (string) ($row['historical_original_name_snapshot'] ?? ''),
                    !empty($row['safe']) ? 'yes' : 'NO',
                    (string) ($row['safety_reason'] ?? '')
                ));
            }
            foreach ((array) ($preview['warnings'] ?? array()) as $warning) {
                WP_CLI::warning((string) $warning);
            }
            foreach ((array) ($preview['ambiguities'] ?? array()) as $ambiguity) {
                WP_CLI::warning('AMBIGUITY: ' . (string) $ambiguity);
            }
            WP_CLI::log('Apply allowed: ' . (!empty($preview['allowed']) ? 'yes' : 'NO'));
        }
    }
}

if (!class_exists('BVMGR_CLI_Event_Communication_Command')) {
    final class BVMGR_CLI_Event_Communication_Command
    {
        /**
         * Preview or apply a retroactive operation communication ledger.
         *
         * ## OPTIONS
         *
         * <event-plan-id>
         * : Exact Event Plan post ID.
         *
         * --operation-id=<uuid>
         * : Exact recorded occurrence operation ID.
         *
         * [--dry-run]
         * : Reconstruct and display operation-specific evidence without writing.
         *
         * [--apply]
         * : Persist the reconstructed audience without sending email.
         *
         * [--confirm=<token>]
         * : Required with --apply. Must be BOOTSTRAP-COMMUNICATIONS.
         *
         * ## EXAMPLES
         *
         *     wp --user=1 bvmgr event communication bootstrap 5568 --operation-id=de1814a7-5ada-4e6e-b587-46c1e80eff89 --dry-run
         *     wp --user=1 bvmgr event communication bootstrap 5568 --operation-id=de1814a7-5ada-4e6e-b587-46c1e80eff89 --apply --confirm=BOOTSTRAP-COMMUNICATIONS
         *
         * @when after_wp_load
         *
         * @param array<int,string> $args
         * @param array<string,mixed> $assoc_args
         */
        public function bootstrap(array $args, array $assoc_args): void
        {
            $plan_id = absint($args[0] ?? 0);
            $operation_id = sanitize_text_field((string) ($assoc_args['operation-id'] ?? ''));
            $dry_run = array_key_exists('dry-run', $assoc_args);
            $apply = array_key_exists('apply', $assoc_args);
            $user = $this->authorized_user($plan_id);
            if ($operation_id === '' || $dry_run === $apply) {
                WP_CLI::error('Event Plan ID, --operation-id, and exactly one of --dry-run or --apply are required.');
            }

            $preview = bvmgr_event_communication_bootstrap_preview($plan_id, $operation_id);
            $this->render_bootstrap_preview($preview);
            if (!$apply) {
                if (empty($preview['allowed'])) {
                    WP_CLI::warning('BOOTSTRAP WOULD BE BLOCKED. Resolve every operation-evidence ambiguity.');
                    return;
                }
                WP_CLI::success('Bootstrap preview complete. No email was sent and no ledger was written.');
                return;
            }
            if ((string) ($assoc_args['confirm'] ?? '') !== 'BOOTSTRAP-COMMUNICATIONS') {
                WP_CLI::error('--apply requires --confirm=BOOTSTRAP-COMMUNICATIONS.');
            }
            $result = bvmgr_event_communication_bootstrap_apply(
                $plan_id,
                $operation_id,
                (int) $user->ID,
                (string) ($preview['fingerprint'] ?? '')
            );
            if (empty($result['ok'])) {
                $rollback = !empty($result['rolled_back']) ? ' Transaction rolled back.' : '';
                WP_CLI::error((string) ($result['message'] ?? 'Communication bootstrap failed.') . $rollback);
            }
            WP_CLI::success((string) ($result['message'] ?? 'Communication ledger bootstrapped.'));
            $this->render_summary((array) ($result['summary'] ?? array()));
            WP_CLI::log('Email sent: NO');
        }

        /**
         * Mark reviewed recipients as manually notified outside BVM.
         *
         * ## OPTIONS
         *
         * <event-plan-id>
         * : Exact Event Plan post ID.
         *
         * --operation-id=<uuid>
         * : Exact operation communication ledger.
         *
         * [--recipient-id=<id>]
         * : Limit to one recipient. Omit to target all included unresolved recipients.
         *
         * --channel=<channel>
         * : email_outside_bvm, letter, or other_written.
         *
         * [--note=<note>]
         * : Optional audit note.
         *
         * [--dry-run]
         * : Show eligible counts without writing.
         *
         * [--apply]
         * : Record manual written notice without sending email.
         *
         * [--confirm=<token>]
         * : Required with --apply. Must be MARK-MANUAL.
         *
         * ## EXAMPLES
         *
         *     wp --user=1 bvmgr event communication mark-manual 5568 --operation-id=de1814a7-5ada-4e6e-b587-46c1e80eff89 --channel=email_outside_bvm --dry-run
         *     wp --user=1 bvmgr event communication mark-manual 5568 --operation-id=de1814a7-5ada-4e6e-b587-46c1e80eff89 --channel=email_outside_bvm --apply --confirm=MARK-MANUAL
         *
         * @when after_wp_load
         *
         * @param array<int,string> $args
         * @param array<string,mixed> $assoc_args
         */
        public function mark_manual(array $args, array $assoc_args): void
        {
            $plan_id = absint($args[0] ?? 0);
            $operation_id = sanitize_text_field((string) ($assoc_args['operation-id'] ?? ''));
            $recipient_id = sanitize_key((string) ($assoc_args['recipient-id'] ?? ''));
            $channel = sanitize_key((string) ($assoc_args['channel'] ?? ''));
            $note = sanitize_textarea_field((string) ($assoc_args['note'] ?? ''));
            $dry_run = array_key_exists('dry-run', $assoc_args);
            $apply = array_key_exists('apply', $assoc_args);
            $user = $this->authorized_user($plan_id);
            if ($operation_id === '' || !in_array($channel, array('email_outside_bvm', 'letter', 'other_written'), true) || $dry_run === $apply) {
                WP_CLI::error('Event Plan ID, --operation-id, a valid --channel, and exactly one of --dry-run or --apply are required.');
            }
            $ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
            if (empty($ledger)) {
                WP_CLI::error('Communication ledger not found. Bootstrap it first if operation evidence allows.');
            }
            $eligible = 0;
            foreach ((array) ($ledger['audience'] ?? array()) as $candidate_id => $recipient) {
                if ($recipient_id !== '' && !hash_equals($recipient_id, (string) $candidate_id)) {
                    continue;
                }
                $state = (array) ($ledger['recipient_states'][$candidate_id] ?? array());
                $status = sanitize_key((string) ($state['written_notice']['status'] ?? 'pending'));
                if (!empty($state['included']) && !bvmgr_event_communication_status_is_resolved($status)) {
                    $eligible++;
                    WP_CLI::log(sprintf('%s | %s | %s', (string) $candidate_id, (string) ($recipient['customer_name'] ?? 'Customer'), (string) ($recipient['email_snapshot'] ?? 'no email')));
                }
            }
            WP_CLI::log('Eligible reviewed recipients: ' . $eligible);
            WP_CLI::log('Email sent: NO');
            if (!$apply) {
                WP_CLI::success('Manual written-notice dry run complete. No status changed.');
                return;
            }
            if ((string) ($assoc_args['confirm'] ?? '') !== 'MARK-MANUAL') {
                WP_CLI::error('--apply requires --confirm=MARK-MANUAL.');
            }
            $result = bvmgr_event_communication_mark_manual_bulk($plan_id, $operation_id, (int) $user->ID, $channel, $note, $recipient_id);
            if (empty($result['ok'])) {
                WP_CLI::error('One or more manual written-notice states could not be recorded.');
            }
            WP_CLI::success(sprintf('%d recipient written-notice states marked manual; %d skipped. No email was sent.', (int) ($result['updated'] ?? 0), (int) ($result['skipped'] ?? 0)));
            $this->render_summary(bvmgr_event_communication_operation_summary($plan_id, $operation_id));
        }

        private function authorized_user(int $plan_id): WP_User
        {
            if ($plan_id <= 0) {
                WP_CLI::error('Event Plan ID is required.');
            }
            $user = wp_get_current_user();
            if (!$user instanceof WP_User || (int) $user->ID <= 0) {
                WP_CLI::error('An authenticated WordPress user is required. Pass WP-CLI global --user=<id|login|email> before the bvmgr command.');
            }
            if (!user_can($user, 'edit_post', $plan_id)) {
                WP_CLI::error('The authenticated WordPress user cannot edit this Event Plan.');
            }
            WP_CLI::log('Actor user ID: ' . (int) $user->ID);
            return $user;
        }

        private function render_bootstrap_preview(array $preview): void
        {
            WP_CLI::log('Event Plan: #' . (int) ($preview['plan_id'] ?? 0) . ' ' . (string) ($preview['plan_title'] ?? ''));
            WP_CLI::log('Operation ID: ' . (string) ($preview['operation_id'] ?? ''));
            WP_CLI::log('Recipients: ' . (int) ($preview['counts']['customers'] ?? 0));
            WP_CLI::log('Orders: ' . (int) ($preview['counts']['orders'] ?? 0));
            WP_CLI::log('Affected line items: ' . (int) ($preview['counts']['line_items'] ?? 0));
            foreach ((array) ($preview['notification_rows'] ?? array()) as $recipient) {
                WP_CLI::log(sprintf(
                    '%s | %s | orders %s',
                    (string) (($recipient['customer_name'] ?? '') ?: 'Customer'),
                    (string) (($recipient['customer_email'] ?? '') ?: 'no email'),
                    implode(',', array_map('strval', (array) ($recipient['order_ids'] ?? array())))
                ));
            }
            foreach ((array) ($preview['ambiguities'] ?? array()) as $ambiguity) {
                WP_CLI::warning('AMBIGUITY: ' . (string) $ambiguity);
            }
            WP_CLI::log('Apply allowed: ' . (!empty($preview['allowed']) ? 'yes' : 'NO'));
            WP_CLI::log('Email sent by bootstrap: NO');
        }

        private function render_summary(array $summary): void
        {
            WP_CLI::log('Recipients: ' . (int) ($summary['recipient_count'] ?? 0));
            WP_CLI::log('Orders: ' . (int) ($summary['order_count'] ?? 0));
            WP_CLI::log('Resolved written notices: ' . (int) ($summary['resolved'] ?? 0));
            WP_CLI::log('Unresolved written notices: ' . (int) ($summary['unresolved'] ?? 0));
        }
    }
}

WP_CLI::add_command('bvmgr event reschedule', 'BVMGR_CLI_Event_Reschedule_Command');
WP_CLI::add_command('bvmgr event integrity', 'BVMGR_CLI_Event_Integrity_Command');
WP_CLI::add_command('bvmgr event reconcile-current-item-names', 'BVMGR_CLI_Event_Item_Name_Reconcile_Command');
WP_CLI::add_command('bvmgr event communication', 'BVMGR_CLI_Event_Communication_Command');
WP_CLI::add_command('vms event reschedule', 'BVMGR_CLI_Event_Reschedule_Command');
WP_CLI::add_command('vms event integrity', 'BVMGR_CLI_Event_Integrity_Command');
WP_CLI::add_command('vms event reconcile-current-item-names', 'BVMGR_CLI_Event_Item_Name_Reconcile_Command');
WP_CLI::add_command('vms event communication', 'BVMGR_CLI_Event_Communication_Command');
