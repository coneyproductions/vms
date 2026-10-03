<?php
defined('ABSPATH') || exit;

add_shortcode('vms_express_bar_menu', 'vmseb_shortcode');
add_action('wp_enqueue_scripts', 'vmseb_public_enqueue_assets');
add_filter('bvmgr_ticketing_post_cart_offer', 'vmseb_ticketing_post_cart_offer', 10, 3);
add_action('admin_post_vmseb_build_cart', 'vmseb_handle_build_cart');
add_action('admin_post_nopriv_vmseb_build_cart', 'vmseb_handle_build_cart');
add_filter('woocommerce_get_item_data', 'vmseb_cart_item_data', 10, 2);
add_action('woocommerce_checkout_create_order', 'vmseb_add_order_meta', 10, 2);
add_action('woocommerce_checkout_create_order_line_item', 'vmseb_add_order_line_item_meta', 10, 4);
add_action('woocommerce_store_api_checkout_update_order_meta', 'vmseb_sync_store_api_order_meta', 10, 1);
add_action('woocommerce_store_api_checkout_order_processed', 'vmseb_sync_store_api_order_meta', 10, 1);

if (!function_exists('vmseb_public_enqueue_assets')) {
    function vmseb_public_enqueue_assets(): void
    {
        wp_register_style('vmseb-primary-buttons', VMSEB_URL . 'assets/css/primary-buttons.css', array(), VMSEB_VERSION);
        wp_register_style('vmseb-public', VMSEB_URL . 'assets/css/public.css', array('vmseb-primary-buttons'), VMSEB_VERSION);
        wp_register_script('vmseb-public', VMSEB_URL . 'assets/js/public.js', array(), VMSEB_VERSION, true);
    }
}

if (!function_exists('vmseb_maybe_append_to_event_content')) {
    function vmseb_maybe_append_to_event_content(string $content): string
    {
        if (is_admin() || !is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        if (strpos($content, '[vms_express_bar_menu') !== false && has_shortcode($content, 'vms_express_bar_menu')) {
            return $content;
        }
        global $post;
        if (!$post instanceof WP_Post || !in_array($post->post_type, array('vms_event_plan', 'tribe_events'), true)) {
            return $content;
        }
        $event_plan_id = vmseb_resolve_event_plan_id_from_post($post);
        if ($event_plan_id <= 0) {
            return $content;
        }
        $cfg = vmseb_get_event_meta($event_plan_id);
        if (empty($cfg['enabled']) || empty($cfg['auto_embed'])) {
            return $content;
        }
        return $content . vmseb_render_event_page_cta($event_plan_id);
    }
}

if (!function_exists('vmseb_ticketing_post_cart_offer')) {
    /**
     * Offer Express Bar after tickets and amenities have been committed to cart.
     */
    function vmseb_ticketing_post_cart_offer(array $offer, int $tec_event_id, int $event_plan_id): array
    {
        unset($tec_event_id);
        $event_plan_id = absint($event_plan_id);
        if ($event_plan_id <= 0 || !vmseb_is_woocommerce_active()) {
            return $offer;
        }

        $cfg = vmseb_get_event_meta($event_plan_id);
        $window = vmseb_get_event_window_status($event_plan_id);
        if (empty($cfg['enabled']) || empty($window['is_open'])) {
            return $offer;
        }

        $has_orderable_item = false;
        foreach (vmseb_get_catalog_items(true) as $item) {
            $availability = vmseb_get_item_online_availability($item);
            if (!empty($availability['orderable'])) {
                $has_orderable_item = true;
                break;
            }
        }
        if (!$has_orderable_item) {
            return $offer;
        }

        return array(
            'enabled'        => 1,
            'id'             => 'express_bar',
            'title'          => __('Your tickets are in your cart.', 'vms-express-bar'),
            'message'        => __('Would you like to order your drinks ahead of time? You can add them now through Express Bar and check out everything together.', 'vms-express-bar'),
            'primaryLabel'   => __('ORDER DRINKS', 'vms-express-bar'),
            'primaryUrl'     => vmseb_get_public_page_url($event_plan_id),
            'secondaryLabel' => __('NO THANKS — CONTINUE TO CHECKOUT', 'vms-express-bar'),
            'secondaryUrl'   => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/'),
        );
    }
}

if (!function_exists('vmseb_render_event_page_cta')) {
    function vmseb_render_event_page_cta(int $event_plan_id): string
    {
        $event_plan_id = absint($event_plan_id);
        if ($event_plan_id <= 0) {
            return '';
        }

        wp_enqueue_style('vmseb-public');

        $window = vmseb_get_event_window_status($event_plan_id);
        $url = vmseb_get_public_page_url($event_plan_id);
        $button_label = empty($window['is_open']) ? 'Browse Express Bar' : 'Open Express Bar';
        $status = empty($window['is_open']) ? 'Menu available to browse. Ordering opens during the event.' : 'Ordering is open now.';
        if (!empty($window['message'])) {
            $status = (string) $window['message'];
        }

        ob_start();
        ?>
        <aside class="vms-express-bar vmseb-event-cta" aria-label="Express Bar">
            <div class="vmseb-event-cta__copy">
                <h3>Skip the line at the bar.</h3>
                <p>Browse the Express Bar menu and build your order from the dedicated Express Bar page.</p>
                <p class="vmseb-event-cta__status"><?php echo esc_html($status); ?></p>
            </div>
            <div class="vmseb-event-cta__action">
                <a class="button button-primary" href="<?php echo esc_url($url); ?>"><?php echo esc_html($button_label); ?></a>
            </div>
        </aside>
        <?php
        return (string) ob_get_clean();
    }
}


if (!function_exists('vmseb_shortcode')) {
    function vmseb_shortcode(array $atts = array()): string
    {
        if (!vmseb_is_woocommerce_active()) {
            return '<div class="vms-express-bar"><p>Express Bar requires WooCommerce.</p></div>';
        }
        $atts = shortcode_atts(array('event_plan_id' => 0), $atts, 'vms_express_bar_menu');
        $shortcode_event_plan_id = absint($atts['event_plan_id']);
        $query_event_plan_id = isset($_GET['event_plan_id']) ? absint($_GET['event_plan_id']) : 0;

        global $post;
        $is_dedicated_public_page = false;
        if ($post instanceof WP_Post && $post->post_type === 'page') {
            $selected_public_page_id = function_exists('vmseb_find_public_page_id') ? vmseb_find_public_page_id(false) : 0;
            $is_dedicated_public_page = ((int) $selected_public_page_id > 0 && (int) $post->ID === (int) $selected_public_page_id)
                || (string) $post->post_name === 'express-bar';
        }

        $explicit_event_plan_id = 0;
        if ($query_event_plan_id > 0) {
            $explicit_event_plan_id = $query_event_plan_id;
        } elseif (!$is_dedicated_public_page && $shortcode_event_plan_id > 0) {
            $explicit_event_plan_id = $shortcode_event_plan_id;
        }

        if ($is_dedicated_public_page && $explicit_event_plan_id > 0 && !vmseb_event_plan_is_public_candidate($explicit_event_plan_id, true)) {
            // Never expose a draft/private/disabled Event Plan through a hand-edited or stale public URL.
            $explicit_event_plan_id = 0;
        }

        $event_plan_id = $explicit_event_plan_id;
        if ($event_plan_id <= 0) {
            if ($post instanceof WP_Post && in_array($post->post_type, array('vms_event_plan', 'tribe_events'), true)) {
                $event_plan_id = vmseb_resolve_event_plan_id_from_post($post);
            }
        }
        if ($event_plan_id <= 0) {
            $event_plan_id = vmseb_resolve_public_event_plan_id();
        }
        if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
            wp_enqueue_style('vmseb-public');
            return '<div class="vms-express-bar vmseb-shell vmseb-shell--closed"><div class="vmseb-closed-panel"><h3>Express Bar Closed</h3><p>There is no Express Bar menu available right now.</p></div></div>';
        }

        $cfg = vmseb_get_event_meta($event_plan_id);
        if (empty($cfg['enabled'])) {
            if ($shortcode_event_plan_id > 0) {
                return '';
            }
            wp_enqueue_style('vmseb-public');
            return '<div class="vms-express-bar vmseb-shell vmseb-shell--closed"><div class="vmseb-closed-panel"><h3>Express Bar Closed</h3><p>There is no Express Bar menu available right now.</p></div></div>';
        }

        $items = vmseb_get_catalog_items(true);
        $settings = vmseb_get_settings();
        if (empty($items)) {
            wp_enqueue_style('vmseb-public');
            return '<div class="vms-express-bar vmseb-shell vmseb-shell--closed"><div class="vmseb-closed-panel"><h3>Express Bar Closed</h3><p>Express Bar is enabled, but no items have been turned on yet.</p></div></div>';
        }

        wp_enqueue_style('vmseb-public');
        wp_enqueue_script('vmseb-public');

        $window = vmseb_get_event_window_status($event_plan_id);
        $event_context = vmseb_get_event_context($event_plan_id);
        $event_poster_id = vmseb_get_event_banner_image_id($event_plan_id);
        $event_poster_html = '';
        $event_poster_allowed_html = array(
            'img' => array(
                'alt'           => true,
                'class'         => true,
                'decoding'      => true,
                'fetchpriority' => true,
                'height'        => true,
                'loading'       => true,
                'sizes'         => true,
                'src'           => true,
                'srcset'        => true,
                'width'         => true,
            ),
        );
        if ($event_poster_id > 0) {
            $event_poster_html = (string) wp_get_attachment_image(
                $event_poster_id,
                'large',
                false,
                array(
                    'class'    => 'vmseb-event-card__poster-image',
                    'alt'      => (string) ($event_context['title'] ?? '') . ' event poster',
                    'loading'  => 'eager',
                    'decoding' => 'async',
                    'sizes'    => '(max-width: 440px) min(72vw, 280px), (max-width: 700px) 110px, 160px',
                )
            );
        }
        $event_choices = $is_dedicated_public_page ? vmseb_get_public_event_choices() : array();
        $admission_state = $is_dedicated_public_page ? vmseb_get_event_admission_state($event_plan_id) : array('cart_count' => 0, 'account_count' => 0, 'logged_in' => is_user_logged_in(), 'verifiable' => false);
        $other_bar_cart_events = $is_dedicated_public_page ? vmseb_get_cart_express_bar_event_summaries($event_plan_id) : array();
        $disabled_quantity_message = '';
        if (empty($window['is_open'])) {
            if (($window['phase'] ?? '') === 'closed') {
                $disabled_quantity_message = 'Express Bar drink pre-orders have closed for this event.';
            } elseif (!empty($window['opens_at_display'])) {
                $disabled_quantity_message = 'Express Bar drink pre-orders open on ' . (string) $window['opens_at_display'] . '.';
            } else {
                $disabled_quantity_message = 'Express Bar drink pre-orders are not open yet.';
            }
        }
        $grouped = vmseb_group_items_by_category($items);
        $return_url = remove_query_arg('vmseb_notice', vmseb_get_current_public_url());
        $pickup_default = '';
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if ($user instanceof WP_User && $user->exists()) {
                $display_name = trim((string) $user->display_name);
                $first_name = trim((string) get_user_meta($user->ID, 'first_name', true));
                $last_name = trim((string) get_user_meta($user->ID, 'last_name', true));
                $full_name = trim($first_name . ' ' . $last_name);
                if ($display_name !== '') {
                    $pickup_default = $display_name;
                } elseif ($full_name !== '') {
                    $pickup_default = $full_name;
                } else {
                    $pickup_default = (string) $user->user_login;
                }
            }
        }
        $notice = '';
        if (!empty($_GET['vmseb_notice'])) {
            $notice_key = sanitize_key((string) $_GET['vmseb_notice']);
            if ($notice_key === 'no-items') {
                $notice = 'Add at least one item before continuing.';
            } elseif ($notice_key === 'window-closed') {
                $notice = 'Drink pre-orders are closed right now.';
            } elseif ($notice_key === 'invalid-items') {
                $notice = 'One or more items could not be added. Please review your order and try again.';
            } elseif ($notice_key === 'age-gate') {
                $notice = 'Enter your birthday to continue with age-gated items. ID is still required at pickup.';
            } elseif ($notice_key === 'pickup-name') {
                $notice = 'Enter a pickup name before continuing.';
            }
        }

        $cart_count = 0;
        $cart_total_html = function_exists('wc_price') ? wc_price(0) : '$0.00';
        $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
        if (function_exists('WC') && WC()->cart) {
            $cart_count = max(0, (int) WC()->cart->get_cart_contents_count());
            $cart_total_html = (string) WC()->cart->get_cart_total();
        }
        $cart_has_items = $cart_count > 0;

        ob_start();
        ?>
        <div class="vms-express-bar vmseb-shell"
             data-event-plan-id="<?php echo (int) $event_plan_id; ?>"
             data-age-gate-enabled="<?php echo !empty($settings['age_gate_enabled']) ? '1' : '0'; ?>"
             data-age-gate-min-age="<?php echo (int) $settings['age_gate_min_age']; ?>"
             data-age-gate-title="<?php echo esc_attr($settings['age_gate_title']); ?>"
             data-age-gate-message="<?php echo esc_attr($settings['age_gate_message']); ?>">
            <?php if ($notice !== '') : ?>
                <div class="vmseb-inline-notice vmseb-inline-notice--error"><?php echo esc_html($notice); ?></div>
            <?php endif; ?>

            <form class="vmseb-builder" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="vmseb_build_cart" />
                <input type="hidden" name="event_plan_id" value="<?php echo (int) $event_plan_id; ?>" />
                <input type="hidden" name="vmseb_payload" value="{}" data-vmseb-payload />
                <input type="hidden" name="vmseb_target" value="cart" data-vmseb-target />
                <input type="hidden" name="vmseb_return_url" value="<?php echo esc_url($return_url); ?>" />
                <input type="hidden" name="vmseb_age_gate_confirmed" value="0" data-vmseb-age-gate-confirmed />
                <?php wp_nonce_field('vmseb_build_cart_' . $event_plan_id, 'vmseb_nonce'); ?>

                <div class="vmseb-builder-layout">
                    <div class="vmseb-builder-section vmseb-builder-section--intro">
                        <div class="vmseb-header__copy">
                            <h3><?php echo esc_html($cfg['headline']); ?></h3>
                        </div>
                    </div>

                    <div class="vmseb-builder-section vmseb-builder-section--pickup">
                        <div class="vmseb-builder-panel vmseb-builder-panel--pickup">
                            <label class="vmseb-pickup-field">
                                <span>Pickup name</span>
                                <input type="text" name="vmseb_pickup_name" maxlength="120" placeholder="Name on the order" value="<?php echo esc_attr($pickup_default); ?>" autocomplete="name" required <?php disabled(empty($window['is_open'])); ?> />
                            </label>
                            <?php if ($cfg['pickup_instructions'] !== '') : ?>
                                <p class="vmseb-pickup-copy"><?php echo esc_html($cfg['pickup_instructions']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="vmseb-builder-section vmseb-builder-section--summary" data-vmseb-summary-wrapper>
                        <div class="vmseb-summary" data-vmseb-summary>
                            <div class="vmseb-summary__copy">
                                <div class="vmseb-summary__count"><strong data-vmseb-selected-count>0</strong> item(s) selected</div>
                                <div class="vmseb-summary__amount"><span data-vmseb-regular-subtotal>$0.00</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="vmseb-builder-section vmseb-builder-section--status">
                        <div class="vmseb-status-card<?php echo empty($window['is_open']) ? ' is-' . esc_attr(sanitize_html_class((string) ($window['phase'] ?? 'browse'))) : ' is-open'; ?>">
                            <dl class="vmseb-status-fields">
                                <div class="vmseb-status-fields__row">
                                    <dt><?php echo esc_html((string) ($window['status_field_label'] ?? 'Express Bar Status')); ?></dt>
                                    <dd><strong><?php echo esc_html((string) ($window['customer_status'] ?? ($window['label'] ?? 'Open'))); ?></strong></dd>
                                </div>
                                <?php if (!empty($window['opens_at_display'])) : ?>
                                    <div class="vmseb-status-fields__row">
                                        <dt><?php echo esc_html((string) ($window['open_field_label'] ?? 'Drink pre-orders open')); ?></dt>
                                        <dd><?php echo esc_html((string) $window['opens_at_display']); ?></dd>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($window['closes_at_display'])) : ?>
                                    <div class="vmseb-status-fields__row">
                                        <dt><?php echo esc_html((string) ($window['last_call_field_label'] ?? 'Last call for drink pre-orders')); ?></dt>
                                        <dd><?php echo esc_html((string) $window['closes_at_display']); ?></dd>
                                    </div>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>

                    <?php if (!empty($event_context['title']) || !empty($event_context['date_display'])) : ?>
                        <div class="vmseb-builder-section vmseb-builder-section--event">
                            <?php if ($is_dedicated_public_page) : ?>
                                <div class="vmseb-event-card">
                                    <div class="vmseb-event-card__poster">
                                        <?php if ($event_poster_html !== '') : ?>
                                            <?php echo wp_kses($event_poster_html, $event_poster_allowed_html); ?>
                                        <?php else : ?>
                                            <span class="vmseb-event-card__poster-fallback" aria-hidden="true">Concert</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vmseb-event-card__copy">
                                        <span class="vmseb-event-card__eyebrow">Ordering for</span>
                                        <?php if (!empty($event_context['url'])) : ?>
                                            <a class="vmseb-event-card__title" href="<?php echo esc_url((string) $event_context['url']); ?>"><?php echo esc_html((string) $event_context['title']); ?></a>
                                        <?php else : ?>
                                            <strong class="vmseb-event-card__title"><?php echo esc_html((string) $event_context['title']); ?></strong>
                                        <?php endif; ?>
                                        <?php if (!empty($event_context['date_display'])) : ?>
                                            <span class="vmseb-event-card__date"><?php echo esc_html((string) $event_context['date_display']); ?></span>
                                        <?php endif; ?>

                                        <?php if (!empty($admission_state['cart_count'])) : ?>
                                            <div class="vmseb-admission-note is-found">
                                                <strong>✓ Admission found</strong>
                                                <?php
                                                $cart_admission_count = (int) $admission_state['cart_count'];
                                                echo esc_html(sprintf(
                                                    _n('%d admission is already in your cart for this event.', '%d admissions are already in your cart for this event.', $cart_admission_count, 'vms-express-bar'),
                                                    $cart_admission_count
                                                ));
                                                ?>
                                            </div>
                                        <?php elseif (!empty($admission_state['account_count'])) : ?>
                                            <div class="vmseb-admission-note is-found">
                                                <strong>✓ Admission found</strong>
                                                <?php
                                                $account_admission_count = (int) $admission_state['account_count'];
                                                echo esc_html(sprintf(
                                                    _n('We found %d admission for this event on your account.', 'We found %d admissions for this event on your account.', $account_admission_count, 'vms-express-bar'),
                                                    $account_admission_count
                                                ));
                                                ?>
                                            </div>
                                        <?php else : ?>
                                            <div class="vmseb-admission-note is-unconfirmed" data-vmseb-admission-warning data-event-plan-id="<?php echo (int) $event_plan_id; ?>">
                                                <strong>Before you order</strong>
                                                <span>
                                                    <?php if (empty($admission_state['verifiable'])) : ?>
                                                        We can't verify admission for this event automatically.
                                                    <?php elseif (!empty($admission_state['logged_in'])) : ?>
                                                        We don't currently see admission for this event in your cart or account.
                                                    <?php else : ?>
                                                        We don't currently see admission for this event in your cart.
                                                    <?php endif; ?>
                                                    If you already have tickets, someone else has your ticket, or you are ordering for another guest, you're all set.
                                                </span>
                                                <div class="vmseb-admission-note__actions">
                                                    <?php if (!empty($event_context['url'])) : ?>
                                                        <a class="button" href="<?php echo esc_url((string) $event_context['url']); ?>">Get Tickets</a>
                                                    <?php endif; ?>
                                                    <button type="button" class="button" data-vmseb-admission-ack>I already have tickets</button>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="vmseb-event-card__actions">
                                            <?php if (count($event_choices) > 1) : ?>
                                                <button type="button" class="button button-primary" data-vmseb-event-selector-toggle aria-expanded="false">Change event</button>
                                            <?php endif; ?>
                                            <?php if (!empty($event_context['url'])) : ?>
                                                <a class="button" href="<?php echo esc_url((string) $event_context['url']); ?>">View event</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (count($event_choices) > 1) : ?>
                                    <div class="vmseb-event-selector" data-vmseb-event-selector hidden>
                                        <div class="vmseb-event-selector__head">
                                            <div>
                                                <strong>Choose another Express Bar event</strong>
                                                <span>Only eligible upcoming Express Bar events are shown.</span>
                                            </div>
                                            <button type="button" class="button" data-vmseb-event-selector-close>Close</button>
                                        </div>
                                        <div class="vmseb-event-selector__grid">
                                            <?php foreach ($event_choices as $choice) : ?>
                                                <?php if ((int) ($choice['id'] ?? 0) === (int) $event_plan_id) { continue; } ?>
                                                <button type="button"
                                                        class="vmseb-event-choice"
                                                        data-vmseb-event-choice
                                                        data-event-plan-id="<?php echo (int) ($choice['id'] ?? 0); ?>"
                                                        data-event-url="<?php echo esc_url((string) ($choice['bar_url'] ?? '')); ?>">
                                                    <span class="vmseb-event-choice__poster">
                                                        <?php if (!empty($choice['poster_url'])) : ?>
                                                            <img src="<?php echo esc_url((string) $choice['poster_url']); ?>" alt="" />
                                                        <?php else : ?>
                                                            <span class="vmseb-event-choice__poster-fallback" aria-hidden="true">Concert</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="vmseb-event-choice__copy">
                                                        <strong><?php echo esc_html((string) ($choice['title'] ?? 'Event')); ?></strong>
                                                        <?php if (!empty($choice['date_display'])) : ?>
                                                            <span><?php echo esc_html((string) $choice['date_display']); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (!empty($other_bar_cart_events)) : ?>
                                            <div class="vmseb-cart-event-note">
                                                <strong>Also in your cart:</strong>
                                                <?php
                                                $other_titles = array_map(static function (array $event): string {
                                                    return (string) ($event['title'] ?? 'another event');
                                                }, $other_bar_cart_events);
                                                echo esc_html('Express Bar items for ' . implode(', ', $other_titles) . '. Those items will stay associated with their original event if you switch.');
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else : ?>
                                <div class="vmseb-event-context">
                                    <p class="vmseb-event-context__line">
                                        <span class="vmseb-event-context__label"><?php echo esc_html((string) ($event_context['context_prefix'] ?? 'Ordering for:')); ?></span>
                                        <?php if (!empty($event_context['url'])) : ?>
                                            <a class="vmseb-event-context__event" href="<?php echo esc_url((string) $event_context['url']); ?>"><?php echo esc_html((string) $event_context['title']); ?></a>
                                        <?php else : ?>
                                            <span class="vmseb-event-context__event vmseb-event-context__event--text"><?php echo esc_html((string) $event_context['title']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($event_context['date_display'])) : ?>
                                            <span class="vmseb-event-context__separator" aria-hidden="true">·</span>
                                            <span class="vmseb-event-context__date"><?php echo esc_html((string) $event_context['date_display']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($event_context['url'])) : ?>
                                            <span class="vmseb-event-context__separator" aria-hidden="true">·</span>
                                            <a class="vmseb-event-context__link" href="<?php echo esc_url((string) $event_context['url']); ?>"><?php echo esc_html((string) ($event_context['view_event_label'] ?? 'View event page')); ?></a>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($window['is_open'])) : ?>
                        <div class="vmseb-builder-section vmseb-builder-section--notice">
                            <div class="vmseb-closed-panel vmseb-closed-panel--browse">
                                <?php if (($window['phase'] ?? '') === 'closed') : ?>
                                    <p><strong>Drink pre-orders are closed for this event.</strong></p>
                                    <p><?php echo esc_html($window['message']); ?></p>
                                <?php else : ?>
                                    <p><strong>Browse the menu now. Drink pre-orders are not open yet.</strong></p>
                                    <p><?php echo esc_html($window['message']); ?> You can decide what you want now and come back when ordering opens.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="vmseb-builder-section vmseb-builder-section--menu">
                        <div class="vmseb-categories" data-vmseb-accordion>
                            <?php $category_index = 0; foreach ($grouped as $category => $category_items) : ?>
                                <section class="vmseb-category<?php echo $category_index === 0 ? ' is-open' : ''; ?>" data-category>
                                    <button type="button" class="vmseb-category__toggle" data-category-toggle aria-expanded="<?php echo $category_index === 0 ? 'true' : 'false'; ?>">
                                        <span><?php echo esc_html($category); ?></span>
                                        <span class="vmseb-category__meta"><span data-category-count>0</span> selected</span>
                                    </button>
                                    <div class="vmseb-category__panel" data-category-panel>
                                        <div class="vmseb-item-list">
                                            <?php foreach ($category_items as $token => $item) :
                                                $availability = vmseb_get_item_online_availability($item);
                                                $orderable = !empty($window['is_open']) && !empty($availability['orderable']);
                                                $status_note = '';
                                                if (empty($window['is_open'])) {
                                                    if (($window['phase'] ?? '') === 'closed') {
                                                        $status_note = 'Drink pre-orders are closed for this event.';
                                                    } else {
                                                        $window_message_for_item = trim((string) ($window['message'] ?? 'Ordering opens during the event.'));
                                                        $status_note = 'Browse now — ' . lcfirst($window_message_for_item);
                                                    }
                                                } elseif (!empty($availability['bar_only'])) {
                                                    $status_note = 'Low stock — order at the bar.';
                                                } elseif ($availability['available'] !== null && $availability['available'] <= 0) {
                                                    $status_note = 'Unavailable online.';
                                                }
                                                ?>
                                                <div class="vmseb-item-row<?php echo $orderable ? '' : ' is-disabled'; ?>" data-item-row data-token="<?php echo esc_attr($token); ?>" data-price="<?php echo esc_attr((string) $item['price']); ?>" data-bucket-eligible="<?php echo !empty($item['bucket_eligible']) ? '1' : '0'; ?>" data-age-gate="<?php echo !empty($item['age_gate']) ? '1' : '0'; ?>">
                                                    <div class="vmseb-item-row__main">
                                                        <?php if (!empty($item['image_url'])) : ?>
                                                            <img class="vmseb-item-row__thumb" src="<?php echo esc_url($item['image_url']); ?>" alt="" loading="lazy" decoding="async" />
                                                        <?php endif; ?>
                                                        <div class="vmseb-item-row__copy">
                                                            <div class="vmseb-item-row__topline">
                                                                <strong><span class="vmseb-item-row__title"><?php echo esc_html($item['title']); ?></span><?php if (!empty($item['bucket_eligible'])) : ?><span class="vmseb-bucket-marker" aria-label="Bucket eligible" title="Eligible for checkout discount rules"><?php echo esc_html($settings['bucket_emoji']); ?></span><?php endif; ?></strong>
                                                                <span class="vmseb-item-row__price"><?php echo wp_kses_post($item['price_html']); ?></span>
                                                            </div>
                                                            <?php if ($status_note !== '') : ?>
                                                                <small><?php echo esc_html($status_note); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="vmseb-stepper-wrap<?php echo $orderable ? '' : ' is-disabled'; ?>"<?php if (!$orderable && $disabled_quantity_message !== '') : ?> data-disabled-message="<?php echo esc_attr($disabled_quantity_message); ?>" tabindex="0" aria-label="<?php echo esc_attr($disabled_quantity_message); ?>"<?php endif; ?>>
                                                        <div class="vmseb-stepper" data-stepper>
                                                            <button type="button" class="vmseb-stepper__btn" data-step="down" <?php disabled(!$orderable); ?>>−</button>
                                                            <input type="number" inputmode="numeric" min="0" value="0" readonly data-qty <?php disabled(!$orderable); ?> />
                                                            <button type="button" class="vmseb-stepper__btn" data-step="up" <?php disabled(!$orderable); ?>>+</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            <?php $category_index++; endforeach; ?>
                        </div>

                        <div class="vmseb-footer-actions<?php echo empty($window['is_open']) ? ' is-disabled' : ''; ?>">
                            <button type="submit" class="button" data-vmseb-submit="cart" <?php disabled(empty($window['is_open'])); ?>>Review Order</button>
                            <button type="submit" class="button button-primary" data-vmseb-submit="checkout" <?php disabled(empty($window['is_open'])); ?>>Checkout</button>
                        </div>
                    </div>
                </div>

                <div class="vmseb-mobile-sticky-action vmseb-purchase-bar<?php echo $cart_has_items ? '' : ' is-disabled'; ?>"
                     data-vmseb-mobile-action
                     data-vmseb-purchase-bar
                     data-vmseb-cart-count="<?php echo (int) $cart_count; ?>"
                     data-vmseb-cart-url="<?php echo esc_url($cart_url); ?>"
                     data-vmseb-ordering-open="<?php echo empty($window['is_open']) ? '0' : '1'; ?>">
                    <div class="vmseb-purchase-bar__inner">
                        <div class="vmseb-purchase-bar__cart" aria-live="polite">
                            <span><strong><?php echo (int) $cart_count; ?></strong> <?php echo esc_html(_n('item in cart', 'items in cart', $cart_count, 'vms-express-bar')); ?></span>
                            <span class="vmseb-purchase-bar__separator" aria-hidden="true">/</span>
                            <span><?php echo wp_kses_post($cart_total_html); ?></span>
                        </div>
                        <button type="submit" class="button button-primary" data-vmseb-submit="cart" data-vmseb-mobile-review <?php disabled(!$cart_has_items); ?>>Review Order</button>
                    </div>
                </div>

                <div class="vmseb-age-gate" data-vmseb-age-gate hidden>
                    <div class="vmseb-age-gate__backdrop" data-vmseb-age-gate-close></div>
                    <div class="vmseb-age-gate__dialog" role="dialog" aria-modal="true" aria-labelledby="vmseb-age-gate-title-<?php echo (int) $event_plan_id; ?>">
                        <button type="button" class="vmseb-age-gate__dismiss" data-vmseb-age-gate-close aria-label="Close">×</button>
                        <h4 id="vmseb-age-gate-title-<?php echo (int) $event_plan_id; ?>"><?php echo esc_html($settings['age_gate_title']); ?></h4>
                        <p><?php echo esc_html($settings['age_gate_message']); ?></p>
                        <label class="vmseb-age-gate__field">
                            <span>What is your birthday?</span>
                            <input type="date" data-vmseb-age-gate-date max="<?php echo esc_attr(wp_date('Y-m-d')); ?>" />
                        </label>
                        <p class="vmseb-age-gate__status" data-vmseb-age-gate-status aria-live="polite"></p>
                        <div class="vmseb-age-gate__actions">
                            <button type="button" class="button" data-vmseb-age-gate-close>Cancel</button>
                            <button type="button" class="button button-primary" data-vmseb-age-gate-confirm>Continue</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('vmseb_handle_build_cart')) {
    function vmseb_handle_build_cart(): void
    {
        $cart_loaded_late = function_exists('WC') && WC() && !WC()->cart;
        if (function_exists('wc_load_cart') && $cart_loaded_late) {
            wc_load_cart();
            if (did_action('wp_loaded') && class_exists('WC_Cart_Session') && WC()->cart) {
                $cart_session = new WC_Cart_Session(WC()->cart);
                $cart_session->get_cart_from_session();
            }
        }
        if (!vmseb_is_woocommerce_active() || !WC()->cart) {
            wp_die('WooCommerce cart unavailable.');
        }
        $event_plan_id = isset($_POST['event_plan_id']) ? absint($_POST['event_plan_id']) : 0;
        $return_url = isset($_POST['vmseb_return_url']) ? esc_url_raw(wp_unslash($_POST['vmseb_return_url'])) : home_url('/');
        $target = isset($_POST['vmseb_target']) ? sanitize_key(wp_unslash($_POST['vmseb_target'])) : 'cart';
        $nonce = isset($_POST['vmseb_nonce']) ? sanitize_text_field(wp_unslash($_POST['vmseb_nonce'])) : '';
        if ($event_plan_id <= 0 || !wp_verify_nonce($nonce, 'vmseb_build_cart_' . $event_plan_id)) {
            wp_safe_redirect(add_query_arg('vmseb_notice', 'invalid-items', $return_url));
            exit;
        }
        $payload = isset($_POST['vmseb_payload']) ? json_decode(wp_unslash((string) $_POST['vmseb_payload']), true) : array();
        $payload = is_array($payload) ? $payload : array();
        $quantities = array();
        foreach ($payload as $token => $qty) {
            $qty = max(0, absint($qty));
            if ($qty > 0) {
                $quantities[(string) $token] = $qty;
            }
        }
        if (empty($quantities)) {
            if ($target === 'cart' && WC()->cart->get_cart_contents_count() > 0) {
                wp_safe_redirect(wc_get_cart_url());
                exit;
            }
            wp_safe_redirect(add_query_arg('vmseb_notice', 'no-items', $return_url));
            exit;
        }

        $cfg = vmseb_get_event_meta($event_plan_id);
        $window = vmseb_get_event_window_status($event_plan_id);
        if (empty($cfg['enabled']) || empty($window['is_open'])) {
            wp_safe_redirect(add_query_arg('vmseb_notice', 'window-closed', $return_url));
            exit;
        }

        $age_gate_confirmed = !empty($_POST['vmseb_age_gate_confirmed']) ? '1' : '0';
        $settings = vmseb_get_settings();
        if (!empty($settings['age_gate_enabled']) && vmseb_selected_quantities_require_age_gate($quantities) && $age_gate_confirmed !== '1') {
            wp_safe_redirect(add_query_arg('vmseb_notice', 'age-gate', $return_url));
            exit;
        }

        $pickup_name = isset($_POST['vmseb_pickup_name']) ? sanitize_text_field(wp_unslash($_POST['vmseb_pickup_name'])) : '';
        if ($pickup_name === '') {
            wp_safe_redirect(add_query_arg('vmseb_notice', 'pickup-name', $return_url));
            exit;
        }
        $has_error = false;
        foreach ($quantities as $token => $qty) {
            $item = vmseb_get_item_by_token($token);
            if (!$item || empty($item['enabled'])) {
                $has_error = true;
                break;
            }
            $availability = vmseb_get_item_online_availability($item);
            if (empty($availability['orderable'])) {
                $has_error = true;
                break;
            }
            if ($availability['available'] !== null && $qty > (int) $availability['available']) {
                $has_error = true;
                break;
            }
        }
        if ($has_error) {
            wp_safe_redirect(add_query_arg('vmseb_notice', 'invalid-items', $return_url));
            exit;
        }

        foreach ($quantities as $token => $qty) {
            $item = vmseb_get_item_by_token($token);
            if (!$item) {
                continue;
            }
            $cart_item_data = vmseb_build_cart_item_data_for_item($event_plan_id, $pickup_name, $item, $age_gate_confirmed === '1');
            if ($item['kind'] === 'variation' && !empty($item['variation_id'])) {
                $variation = wc_get_product((int) $item['variation_id']);
                if (!$variation instanceof WC_Product_Variation) {
                    continue;
                }
                WC()->cart->add_to_cart((int) $item['product_id'], $qty, (int) $item['variation_id'], $variation->get_attributes(), $cart_item_data);
            } else {
                WC()->cart->add_to_cart((int) $item['product_id'], $qty, 0, array(), $cart_item_data);
            }
        }

        $redirect = $target === 'checkout' ? wc_get_checkout_url() : wc_get_cart_url();
        wp_safe_redirect($redirect);
        exit;
    }
}

if (!function_exists('vmseb_cart_item_data')) {
    function vmseb_cart_item_data(array $item_data, array $cart_item): array
    {
        if (!empty($cart_item['_vms_express_bar_event_plan_title'])) {
            $item_data[] = array('name' => __('Express Bar Event', 'vms-express-bar'), 'value' => wc_clean((string) $cart_item['_vms_express_bar_event_plan_title']));
        }
        if (!empty($cart_item['_vms_express_bar_pickup_name'])) {
            $item_data[] = array('name' => __('Pickup Name', 'vms-express-bar'), 'value' => wc_clean((string) $cart_item['_vms_express_bar_pickup_name']));
        }
        return $item_data;
    }
}


if (!function_exists('vmseb_add_order_meta')) {
    function vmseb_add_order_meta(WC_Order $order, array $data): void
    {
        unset($data);
        vmseb_sync_order_meta_from_items($order);
    }
}

if (!function_exists('vmseb_sync_store_api_order_meta')) {
    function vmseb_sync_store_api_order_meta(WC_Order $order): void
    {
        vmseb_sync_order_meta_from_items($order);
    }
}

if (!function_exists('vmseb_add_order_line_item_meta')) {
    function vmseb_add_order_line_item_meta(WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order): void
    {
        unset($cart_item_key, $order);
        if (empty($values['_vms_express_bar'])) {
            return;
        }
        $item->add_meta_data('_vms_express_bar', '1', true);
        $item->add_meta_data('_vms_express_bar_event_plan_id', absint($values['_vms_express_bar_event_plan_id'] ?? 0), true);
        $item->add_meta_data('_vms_express_bar_event_plan_title', sanitize_text_field((string) ($values['_vms_express_bar_event_plan_title'] ?? '')), true);
        $item->add_meta_data('_vms_express_bar_pickup_name', sanitize_text_field((string) ($values['_vms_express_bar_pickup_name'] ?? '')), true);
        $item->add_meta_data('_vmseb_item_token', sanitize_text_field((string) ($values['_vmseb_item_token'] ?? '')), true);
        $item->add_meta_data('_vmseb_item_label', sanitize_text_field((string) ($values['_vmseb_item_label'] ?? '')), true);
        $item->add_meta_data('_vmseb_item_bucket_eligible', sanitize_text_field((string) ($values['_vmseb_item_bucket_eligible'] ?? '0')), true);
        $item->add_meta_data('_vmseb_item_age_gate', sanitize_text_field((string) ($values['_vmseb_item_age_gate'] ?? '0')), true);
        $item->add_meta_data('_vmseb_age_gate_confirmed', sanitize_text_field((string) ($values['_vmseb_age_gate_confirmed'] ?? '0')), true);
        $item->add_meta_data('_vmseb_item_category', sanitize_text_field((string) ($values['_vmseb_item_category'] ?? '')), true);
    }
}
