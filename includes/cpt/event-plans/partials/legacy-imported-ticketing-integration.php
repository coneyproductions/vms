<?php
defined('ABSPATH') || exit;

$linked_tec_id = isset($linked_tec_id) ? absint($linked_tec_id) : 0;
$linked_tec_title = isset($linked_tec_title) ? (string) $linked_tec_title : '';
$linked_tec_legacy_str = isset($linked_tec_legacy_str) ? (string) $linked_tec_legacy_str : '';
$ticket_stats = isset($ticket_stats) && is_array($ticket_stats) ? $ticket_stats : array();
$ticket_pids = isset($ticket_pids) && is_array($ticket_pids) ? $ticket_pids : array();
$ticket_pids_meta_exists = !empty($ticket_pids_meta_exists);
$manual_ticket_pids = isset($manual_ticket_pids) && is_array($manual_ticket_pids) ? $manual_ticket_pids : array();
?>
<?php if (!post_type_exists('tribe_events')) : ?>
    <details class="vms-ticketing__legacy" open>
        <summary><strong><?php esc_html_e('Legacy / Imported Ticketing Integration', 'vms'); ?></strong></summary>
        <p class="description"><?php esc_html_e('The Events Calendar (TEC) is not active, so ticketing links are unavailable.', 'vms'); ?></p>
    </details>
<?php else : ?>
    <details class="vms-ticketing__legacy" <?php echo ($linked_tec_id <= 0 ? 'open' : ''); ?>>
        <summary><strong><?php esc_html_e('Legacy / Imported Ticketing Integration', 'vms'); ?></strong></summary>

        <?php if ($linked_tec_id > 0) : ?>
            <p class="vms-ticketing__linked">
                <strong><?php esc_html_e('Linked TEC event:', 'vms'); ?></strong>
                <span>WP #<?php echo (int) $linked_tec_id; ?></span>
                <?php if ($linked_tec_legacy_str !== '') : ?>
                    <span class="vms-ticketing__linked-legacy"><?php echo esc_html($linked_tec_legacy_str); ?></span>
                <?php endif; ?>
                <span class="vms-ticketing__linked-title"><?php echo esc_html($linked_tec_title !== '' ? $linked_tec_title : get_the_title($linked_tec_id)); ?></span>
            </p>

            <div class="vms-ticketing__stats">
                <?php
                    $qty = isset($ticket_stats['qty_sold']) ? (int) $ticket_stats['qty_sold'] : null;
                    $rev = isset($ticket_stats['revenue']) ? (float) $ticket_stats['revenue'] : null;
                    $label = isset($ticket_stats['revenue_label']) ? (string) $ticket_stats['revenue_label'] : '';
                    $provider = isset($ticket_stats['provider']) ? (string) $ticket_stats['provider'] : '';
                    $computed = isset($ticket_stats['computed_at_gmt']) ? (int) $ticket_stats['computed_at_gmt'] : 0;
                    $computed_str = '';
                    if ($computed > 0 && function_exists('wp_date') && function_exists('wp_timezone')) {
                        $computed_str = wp_date('Y-m-d H:i', $computed, wp_timezone());
                    }
                    $provider_label = $provider;
                    if ($provider === 'woo_analytics') { $provider_label = 'Woo analytics'; }
                    if ($provider === 'woo_product_totals') { $provider_label = 'Woo product totals'; }
                    if ($provider === 'none' || $provider === '') { $provider_label = 'N/A'; }
                    $rev_str = '';
                    if ($rev !== null) {
                        $rev_str = function_exists('vms_ticketing_format_money') ? vms_ticketing_format_money($rev) : (string) $rev;
                    }
                ?>

                <div class="vms-ticketing__stat"><strong><?php esc_html_e('Ticket products:', 'vms'); ?></strong> <?php echo $ticket_pids_meta_exists ? (int) count($ticket_pids) : esc_html__('Not refreshed', 'vms'); ?></div>
                <div class="vms-ticketing__stat"><strong><?php esc_html_e('Sold:', 'vms'); ?></strong> <?php echo ($qty === null) ? esc_html__('Not refreshed', 'vms') : (int) $qty; ?></div>
                <div class="vms-ticketing__stat"><strong><?php esc_html_e('Revenue:', 'vms'); ?></strong> <?php echo ($rev === null) ? esc_html__('Not refreshed', 'vms') : esc_html($rev_str); ?></div>
                <div class="vms-ticketing__stat"><strong><?php esc_html_e('Source:', 'vms'); ?></strong> <?php echo esc_html($provider_label); ?></div>
                <?php if ($computed_str) : ?>
                    <div class="vms-ticketing__stat"><strong><?php esc_html_e('Last updated:', 'vms'); ?></strong> <?php echo esc_html($computed_str); ?></div>
                <?php endif; ?>
                <?php if ($label) : ?>
                    <div class="description"><?php echo esc_html($label); ?></div>
                <?php endif; ?>
            </div>

            <p>
                <button type="button" class="button" id="vms-ticketing-unlink-btn" data-vms-link-sensitive="1"><?php esc_html_e('Unlink TEC event', 'vms'); ?></button>
                <button type="button" class="button button-secondary" id="vms-ticketing-refresh-btn" data-vms-link-sensitive="1"><?php esc_html_e('Refresh ticket stats', 'vms'); ?></button>
            </p>
        <?php else : ?>
            <p class="description"><?php esc_html_e('Optional: link an existing (legacy/imported) TEC event. For brand new Event Plans, you can ignore this section. Ticketing below can create and link a calendar event automatically.', 'vms'); ?></p>
            <p class="description"><em><?php esc_html_e('Tip: If the Link button does not respond, click “Save Draft” first (Draft is fine), then try again.', 'vms'); ?></em></p>
        <?php endif; ?>

        <div class="vms-ticketing__search">
            <label for="vms-ticketing-search"><strong><?php esc_html_e('Search TEC events to link:', 'vms'); ?></strong></label>
            <input type="text" id="vms-ticketing-search" class="regular-text" placeholder="<?php esc_attr_e('Type at least 2 characters…', 'vms'); ?>" />
            <div id="vms-ticketing-results"></div>
            <p>
                <button type="button" class="button button-primary" id="vms-ticketing-link-btn" data-vms-link-sensitive="1" disabled="disabled"><?php esc_html_e('Link selected TEC event', 'vms'); ?></button>
            </p>
            <div id="vms-ticketing-msg" class="vms-ticketing__msg vms-notice" aria-live="polite"></div>
        </div>
        <div class="vms-ticketing__manual">
            <hr />
            <h5><?php esc_html_e('WooCommerce products (legacy “Woo-only” tickets)', 'vms'); ?></h5>

            <?php if (!class_exists('WooCommerce')) : ?>
                <p class="description"><?php esc_html_e('WooCommerce is not active, so product-based ticket stats are unavailable.', 'vms'); ?></p>
            <?php else : ?>
                <p class="description"><?php esc_html_e('If your legacy tickets were sold as normal Woo products (not created inside TEC), attach those products here so VMS can calculate sold + revenue.', 'vms'); ?></p>

                <div id="vms-ticketing-manual-list">
                    <?php if (empty($manual_ticket_pids)) : ?>
                        <p class="description"><?php esc_html_e('No Woo products attached.', 'vms'); ?></p>
                    <?php else : ?>
                        <ul class="vms-ticketing__manual-list">
                            <?php foreach ($manual_ticket_pids as $pid) : ?>
                                <?php
                                    $pid = absint($pid);
                                    $p = function_exists('wc_get_product') ? wc_get_product($pid) : null;
                                    if (!$p) { continue; }
                                ?>
                                <li>
                                    <span class="vms-ticketing__pid">#<?php echo (int) $pid; ?></span>
                                    <span class="vms-ticketing__pname"><?php echo esc_html($p->get_name()); ?></span>
                                    <span class="vms-ticketing__pprice"><?php echo wp_kses_post(wc_price((float) $p->get_price())); ?></span>
                                    <button type="button" class="button button-small" data-vms-ticketing-detach="<?php echo (int) $pid; ?>"><?php esc_html_e('Remove', 'vms'); ?></button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="vms-ticketing__product-search">
                    <label for="vms-ticketing-product-search"><strong><?php esc_html_e('Search Woo products to attach:', 'vms'); ?></strong></label>
                    <input type="text" id="vms-ticketing-product-search" class="regular-text" placeholder="<?php esc_attr_e('Type at least 2 characters…', 'vms'); ?>" />
                    <div id="vms-ticketing-product-results"></div>
                </div>

                <?php if ($linked_tec_id <= 0) : ?>
                    <p>
                        <button type="button" class="button button-secondary" id="vms-ticketing-refresh-btn" data-vms-link-sensitive="1" <?php echo empty($manual_ticket_pids) ? 'disabled="disabled"' : ''; ?>><?php esc_html_e('Refresh ticket stats', 'vms'); ?></button>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </details>
<?php endif; ?>
