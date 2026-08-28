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
         * --user=<id-or-login>
         * : Existing administrator/operator who can edit the Event Plan.
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
         *     wp vms event reschedule 5568 --old-start="2026-09-19 19:00" --new-start="2026-09-12 19:00" --reason=date_correction --user=1 --dry-run
         *     wp vms event reschedule 5568 --old-start="2026-09-19 19:00" --new-start="2026-09-12 19:00" --reason=date_correction --user=1 --apply --confirm=RESCHEDULE
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
                WP_CLI::error('Event Plan ID, --old-start, --new-start, --reason, and --user are required.');
            }
            if ($dry_run === $apply) {
                WP_CLI::error('Specify exactly one of --dry-run or --apply.');
            }
            $user = $this->resolve_user((string) ($assoc_args['user'] ?? ''));
            if (!$user instanceof WP_User || !user_can($user, 'edit_post', $plan_id)) {
                WP_CLI::error('The --user value must identify a user who can edit this Event Plan.');
            }
            wp_set_current_user((int) $user->ID);

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

            $result = bvmgr_event_occurrence_apply($plan_id, $old_start, $new_start, $reason, (int) $user->ID);
            if (empty($result['ok'])) {
                $rollback = !empty($result['rolled_back']) ? ' Transaction rolled back.' : '';
                WP_CLI::error((string) ($result['message'] ?? 'Occurrence operation failed.') . $rollback);
            }
            WP_CLI::success((string) ($result['message'] ?? 'Occurrence operation applied.'));
            WP_CLI::log('Operation ID: ' . (string) ($result['operation_id'] ?? 'existing'));
            $this->render_integrity((array) ($result['integrity'] ?? array()));
        }

        private function resolve_user(string $raw): ?WP_User
        {
            $raw = trim($raw);
            if ($raw === '') {
                return null;
            }
            $user = ctype_digit($raw) ? get_user_by('id', (int) $raw) : get_user_by('login', $raw);
            return $user instanceof WP_User ? $user : null;
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

WP_CLI::add_command('bvmgr event reschedule', 'BVMGR_CLI_Event_Reschedule_Command');
WP_CLI::add_command('bvmgr event integrity', 'BVMGR_CLI_Event_Integrity_Command');
WP_CLI::add_command('vms event reschedule', 'BVMGR_CLI_Event_Reschedule_Command');
WP_CLI::add_command('vms event integrity', 'BVMGR_CLI_Event_Integrity_Command');
