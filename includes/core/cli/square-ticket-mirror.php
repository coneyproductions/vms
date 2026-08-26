<?php
defined('ABSPATH') || exit;

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

if (!class_exists('BVMGR_CLI_Square_Ticket_Mirror_Command')) {
    class BVMGR_CLI_Square_Ticket_Mirror_Command
    {
        /**
         * Show Square ticket mirror status and source model for one product.
         *
         * ## OPTIONS
         *
         * --product=<id>
         * : Woo product ID.
         *
         * [--format=<format>]
         * : Output format. Options: summary, json. Default: summary.
         *
         * ## EXAMPLES
         *
         *     wp vms square-ticket-mirror status --product=123
         *     wp vms square-ticket-mirror status --product=123 --format=json
         *
         * @subcommand status
         * @when after_wp_load
         *
         * @param array<int,string> $args
         * @param array<string,string> $assoc_args
         */
        public function status(array $args, array $assoc_args): void
        {
            unset($args);
            $product_id = isset($assoc_args['product']) ? absint($assoc_args['product']) : 0;
            if ($product_id <= 0) {
                WP_CLI::error('A valid --product ID is required.');
            }

            $state = vms_square_ticket_mirror_status_context($product_id);
            $payload = array(
                'product_id' => $product_id,
                'status' => (string) ($state['status'] ?? 'not_mirrored'),
                'status_label' => (string) ($state['status_label'] ?? ''),
                'eligibility' => (array) ($state['eligibility'] ?? array()),
                'mirror_meta' => array(
                    'mode' => (string) ($state['mode'] ?? ''),
                    'item_id' => (string) ($state['item_id'] ?? ''),
                    'variation_id' => (string) ($state['variation_id'] ?? ''),
                    'category_id' => (string) ($state['category_id'] ?? ''),
                    'location_id' => (string) ($state['location_id'] ?? ''),
                    'catalog_version' => absint($state['catalog_version'] ?? 0),
                    'last_sync_gmt' => (string) ($state['last_sync_gmt'] ?? ''),
                    'last_retired_gmt' => (string) ($state['last_retired_gmt'] ?? ''),
                    'last_order_stamp_gmt' => (string) ($state['last_order_stamp_gmt'] ?? ''),
                    'last_error_code' => (string) ($state['last_error_code'] ?? ''),
                    'last_error_message' => (string) ($state['last_error_message'] ?? ''),
                    'stored_source_hash' => (string) ($state['stored_source_hash'] ?? ''),
                    'current_source_hash' => (string) ($state['current_source_hash'] ?? ''),
                ),
                'source_model' => (array) ($state['source_model'] ?? array()),
            );

            $format = sanitize_key((string) ($assoc_args['format'] ?? 'summary'));
            if ($format === 'json') {
                WP_CLI::line(wp_json_encode($payload, JSON_PRETTY_PRINT));
                return;
            }

            WP_CLI::log('Product ID: ' . $product_id);
            WP_CLI::log('Status: ' . (string) ($payload['status_label'] ?? ''));
            WP_CLI::log('Eligibility: ' . (!empty($payload['eligibility']['eligible']) ? 'eligible' : 'not eligible'));
            if (empty($payload['eligibility']['eligible'])) {
                WP_CLI::log('Reason: ' . (string) ($payload['eligibility']['reason_message'] ?? ''));
            }
            WP_CLI::log('Square item ID: ' . (string) ($payload['mirror_meta']['item_id'] ?? ''));
            WP_CLI::log('Square variation ID: ' . (string) ($payload['mirror_meta']['variation_id'] ?? ''));
            WP_CLI::log('Square location ID: ' . (string) ($payload['mirror_meta']['location_id'] ?? ''));
            WP_CLI::log('Last sync GMT: ' . (string) ($payload['mirror_meta']['last_sync_gmt'] ?? ''));
            WP_CLI::line('');
            WP_CLI::line(wp_json_encode($payload['source_model'], JSON_PRETTY_PRINT));
        }

        /**
         * Dry-run order-item stamping for one product without creating a live order.
         *
         * ## OPTIONS
         *
         * --product=<id>
         * : Woo product ID.
         *
         * [--variation-id=<id>]
         * : Simulated Square mirror variation ID. When provided, the command writes temporary
         *   dedicated VMS mirror meta, runs the stamp test, then restores the original meta.
         *
         * [--keep-simulated-meta]
         * : Keep the simulated meta instead of restoring it after the stamp test.
         *
         * [--format=<format>]
         * : Output format. Options: summary, json. Default: summary.
         *
         * ## EXAMPLES
         *
         *     wp vms square-ticket-mirror stamp-test --product=123
         *     wp vms square-ticket-mirror stamp-test --product=123 --variation-id=VARIATION_TEST_123 --format=json
         *
         * @subcommand stamp-test
         * @when after_wp_load
         *
         * @param array<int,string> $args
         * @param array<string,string|bool> $assoc_args
         */
        public function stamp_test(array $args, array $assoc_args): void
        {
            unset($args);
            $product_id = isset($assoc_args['product']) ? absint((string) $assoc_args['product']) : 0;
            if ($product_id <= 0) {
                WP_CLI::error('A valid --product ID is required.');
            }

            $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
            if (!$product instanceof WC_Product) {
                WP_CLI::error('The requested product could not be loaded.');
            }

            $backup = array();
            $simulated_variation_id = isset($assoc_args['variation-id']) ? trim((string) $assoc_args['variation-id']) : '';
            $keep_simulated_meta = !empty($assoc_args['keep-simulated-meta']);
            if ($simulated_variation_id !== '') {
                $backup = $this->backup_mirror_meta($product_id);
                $this->apply_simulated_mirror_meta($product_id, $simulated_variation_id);
            }

            try {
                $item = new WC_Order_Item_Product();
                $item->set_product($product);
                $item->set_quantity(1);

                $result = vms_square_ticket_mirror_maybe_stamp_checkout_item($item, array(
                    'hook' => 'wp_cli_stamp_test',
                    'order_id' => 0,
                    'simulated_variation_id' => $simulated_variation_id,
                ));

                $payload = array(
                    'product_id' => $product_id,
                    'simulated_variation_id' => $simulated_variation_id,
                    'applied' => !empty($result['applied']),
                    'result' => $result,
                    'order_item_meta' => array(
                        '_square_item_variation_id' => (string) $item->get_meta('_square_item_variation_id', true),
                        '_vms_square_mirror_stamped' => (string) $item->get_meta('_vms_square_mirror_stamped', true),
                    ),
                    'status' => vms_square_ticket_mirror_status_context($product_id),
                );

                $format = sanitize_key((string) ($assoc_args['format'] ?? 'summary'));
                if ($format === 'json') {
                    WP_CLI::line(wp_json_encode($payload, JSON_PRETTY_PRINT));
                } else {
                    WP_CLI::log('Product ID: ' . $product_id);
                    WP_CLI::log('Applied: ' . (!empty($payload['applied']) ? 'yes' : 'no'));
                    WP_CLI::log('Stamped _square_item_variation_id: ' . (string) ($payload['order_item_meta']['_square_item_variation_id'] ?? ''));
                    WP_CLI::log('Stamped flag: ' . (string) ($payload['order_item_meta']['_vms_square_mirror_stamped'] ?? ''));
                    if (empty($payload['applied'])) {
                        WP_CLI::log('Reason: ' . (string) ($result['reason_message'] ?? 'not stamped'));
                    }
                }
            } finally {
                if ($simulated_variation_id !== '' && !$keep_simulated_meta) {
                    $this->restore_mirror_meta($product_id, $backup);
                }
            }
        }

        /**
         * @return array<string,array<string,mixed>>
         */
        private function backup_mirror_meta(int $product_id): array
        {
            $backup = array();
            foreach ($this->mirror_meta_fields() as $field) {
                $meta_key = vms_square_ticket_mirror_product_meta_key($field);
                if ($meta_key === '') {
                    continue;
                }

                $backup[$field] = array(
                    'exists' => metadata_exists('post', $product_id, $meta_key),
                    'value' => get_post_meta($product_id, $meta_key, true),
                );
            }

            return $backup;
        }

        /**
         * @param array<string,array<string,mixed>> $backup
         */
        private function restore_mirror_meta(int $product_id, array $backup): void
        {
            foreach ($this->mirror_meta_fields() as $field) {
                $meta_key = vms_square_ticket_mirror_product_meta_key($field);
                if ($meta_key === '') {
                    continue;
                }

                $row = is_array($backup[$field] ?? null) ? $backup[$field] : array();
                if (!empty($row['exists'])) {
                    update_post_meta($product_id, $meta_key, $row['value'] ?? '');
                } else {
                    delete_post_meta($product_id, $meta_key);
                }
            }
        }

        private function apply_simulated_mirror_meta(int $product_id, string $variation_id): void
        {
            $state = vms_square_ticket_mirror_status_context($product_id);
            $source_model = is_array($state['source_model'] ?? null) ? (array) $state['source_model'] : vms_square_ticket_mirror_build_source_model($product_id);
            $source_hash = vms_square_ticket_mirror_source_hash($source_model);

            vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_mode', vms_square_ticket_mirror_mode_value());
            vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_status', 'mirrored');
            vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_item_id', 'SIMULATED_ITEM_' . $product_id);
            vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_variation_id', $variation_id);
            vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_category_id', 'SIMULATED_CATEGORY');
            vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_location_id', 'SIMULATED_LOCATION');
            vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_catalog_version', '1');
            vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_source_hash', $source_hash);
            vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_last_sync_gmt', vms_square_ticket_mirror_now_gmt());
            vms_square_ticket_mirror_delete_meta($product_id, 'square_mirror_last_error_code');
            vms_square_ticket_mirror_delete_meta($product_id, 'square_mirror_last_error_message');
        }

        /**
         * @return string[]
         */
        private function mirror_meta_fields(): array
        {
            return array(
                'square_mirror_mode',
                'square_mirror_status',
                'square_mirror_item_id',
                'square_mirror_variation_id',
                'square_mirror_category_id',
                'square_mirror_location_id',
                'square_mirror_catalog_version',
                'square_mirror_source_hash',
                'square_mirror_last_sync_gmt',
                'square_mirror_last_error_code',
                'square_mirror_last_error_message',
                'square_mirror_last_retired_gmt',
                'square_mirror_last_order_stamp_gmt',
            );
        }
    }

    WP_CLI::add_command('vms square-ticket-mirror', 'BVMGR_CLI_Square_Ticket_Mirror_Command');
}
