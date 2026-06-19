<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_add_dispatch_enqueue_admin_assets')) {
	function vms_add_dispatch_enqueue_admin_assets(): void
	{
		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$is_event_plan = is_object($screen) && !empty($screen->post_type) && (string) $screen->post_type === 'vms_event_plan';
		if ($page !== vms_add_dispatch_page_slug() && !$is_event_plan) {
			return;
		}

		wp_enqueue_style(
			'vms-add-dispatch-admin',
			VMS_PLUGIN_URL . 'assets/css/vms-add-dispatch-admin.css',
			array('vms-admin'),
			defined('VMS_VERSION') ? VMS_VERSION : null
		);
	}
}
add_action('admin_enqueue_scripts', 'vms_add_dispatch_enqueue_admin_assets', 50);

if (!function_exists('vms_add_dispatch_register_admin_page')) {
	function vms_add_dispatch_register_admin_page(): void
	{
		add_submenu_page(
			'vms-dashboard',
			__('ADD — Availability & Date Dispatch', 'vms'),
			__('ADD Dispatch', 'vms'),
			'manage_options',
			vms_add_dispatch_page_slug(),
			'vms_add_dispatch_render_admin_page'
		);
	}
}
add_action('admin_menu', 'vms_add_dispatch_register_admin_page', 40);
if (!function_exists('vms_add_dispatch_strip_menu_badge_markup')) {
	function vms_add_dispatch_strip_menu_badge_markup(string $label): string
	{
		return trim((string) preg_replace('/\s*<span class="awaiting-mod vms-add-dispatch-alert-badge">.*?<\/span>\s*/i', '', $label));
	}
}

if (!function_exists('vms_add_dispatch_menu_badge_markup')) {
	function vms_add_dispatch_menu_badge_markup(int $count): string
	{
		$count = max(0, (int) $count);
		if ($count <= 0) {
			return '';
		}
		return ' <span class="awaiting-mod vms-add-dispatch-alert-badge"><span class="pending-count">' . esc_html((string) $count) . '</span></span>';
	}
}

if (!function_exists('vms_add_dispatch_current_pending_count')) {
	function vms_add_dispatch_current_pending_count(): int
	{
		return function_exists('vms_add_dispatch_pending_portal_interest_count')
			? (int) vms_add_dispatch_pending_portal_interest_count()
			: 0;
	}
}

if (!function_exists('vms_add_dispatch_should_render_shell_count')) {
	function vms_add_dispatch_should_render_shell_count(): bool
	{
		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		if ($page === vms_add_dispatch_page_slug() || $page === 'vms-dashboard') {
			return true;
		}

		return function_exists('vms_admin_ui_is_vms_screen') && vms_admin_ui_is_vms_screen();
	}
}

if (!function_exists('vms_add_dispatch_add_menu_badge')) {
	function vms_add_dispatch_add_menu_badge(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		$count = vms_add_dispatch_current_pending_count();
		if ($count <= 0) {
			return;
		}

		global $submenu, $menu;
		$markup = vms_add_dispatch_menu_badge_markup($count);

		if (isset($submenu['vms-dashboard']) && is_array($submenu['vms-dashboard'])) {
			foreach ($submenu['vms-dashboard'] as $index => $item) {
				$slug = isset($item[2]) ? (string) $item[2] : '';
				if ($slug !== vms_add_dispatch_page_slug()) {
					continue;
				}
				$label = function_exists('vms_add_dispatch_strip_menu_badge_markup') ? vms_add_dispatch_strip_menu_badge_markup((string) ($item[0] ?? __('ADD Dispatch', 'vms'))) : (string) ($item[0] ?? __('ADD Dispatch', 'vms'));
				$submenu['vms-dashboard'][$index][0] = $label . $markup;
				break;
			}
		}

		if (is_array($menu)) {
			foreach ($menu as $index => $item) {
				$slug = isset($item[2]) ? (string) $item[2] : '';
				if ($slug !== 'vms-dashboard') {
					continue;
				}
				$label = function_exists('vms_add_dispatch_strip_menu_badge_markup') ? vms_add_dispatch_strip_menu_badge_markup((string) ($item[0] ?? 'VMS')) : (string) ($item[0] ?? 'VMS');
				$menu[$index][0] = $label . $markup;
				break;
			}
		}
	}
}
add_action('admin_menu', 'vms_add_dispatch_add_menu_badge', 1001);
add_action('admin_head', 'vms_add_dispatch_add_menu_badge', 20);

if (!function_exists('vms_add_dispatch_render_menu_badge_css')) {
	function vms_add_dispatch_render_menu_badge_css(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		$count = vms_add_dispatch_current_pending_count();
		if ($count <= 0) {
			return;
		}
		echo '<style>';
		echo '#adminmenu .vms-add-dispatch-alert-badge{margin-left:6px;min-width:18px;height:18px;line-height:18px;border-radius:999px;background:#d63638;box-shadow:none;}';
		echo '#adminmenu .vms-add-dispatch-alert-badge .pending-count{display:block;min-width:18px;height:18px;line-height:18px;padding:0 4px;color:#fff;font-size:11px;font-weight:700;text-align:center;}';
		echo '</style>';
	}
}
add_action('admin_head', 'vms_add_dispatch_render_menu_badge_css', 21);

if (!function_exists('vms_add_dispatch_render_menu_badge_js')) {
	function vms_add_dispatch_render_menu_badge_js(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		$count = vms_add_dispatch_current_pending_count();
		if ($count <= 0) {
			return;
		}
		$markup = wp_json_encode(vms_add_dispatch_menu_badge_markup($count));
		echo '<script>(function(){var markup=' . $markup . ';if(!markup){return;}function applyBadge(selector){var nodes=document.querySelectorAll(selector);if(!nodes.length){return;}for(var i=0;i<nodes.length;i++){var el=nodes[i];if(!el||el.innerHTML.indexOf("vms-add-dispatch-alert-badge")!==-1){continue;}el.insertAdjacentHTML("beforeend",markup);}}function run(){applyBadge("#toplevel_page_vms-dashboard > a .wp-menu-name");applyBadge("#toplevel_page_vms-dashboard .wp-submenu li a[href*=\"page=vms-add-dispatch\"]");applyBadge("#toplevel_page_vms-dashboard .wp-submenu li.current a[href*=\"page=vms-add-dispatch\"]");}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",run);}else{run();}})();</script>';
	}
}
add_action('admin_footer', 'vms_add_dispatch_render_menu_badge_js', 50);


if (!function_exists('vms_add_dispatch_render_dashboard_card')) {
	function vms_add_dispatch_render_dashboard_card(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		$count = vms_add_dispatch_current_pending_count();
		if ($count <= 0) {
			return;
		}
		$add_url = function_exists('vms_add_dispatch_admin_url') ? vms_add_dispatch_admin_url() : admin_url('admin.php?page=' . vms_add_dispatch_page_slug());
		echo '<section id="vms-dashboard-add-dispatch" class="vms-dashboard-approvals-card">';
		echo '<h2>' . esc_html__('Vendor Interest Waiting', 'vms') . '</h2>';
		echo '<p>' . esc_html__('A vendor has asked to be considered for an open date. Review this queue so those requests are not missed.', 'vms') . '</p>';
		echo '<p class="vms-dashboard-approvals-card__total"><strong>' . esc_html((string) $count) . '</strong> ' . esc_html(_n('interest submission waiting', 'interest submissions waiting', $count, 'vms')) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url($add_url) . '">' . esc_html__('Open ADD Dispatch', 'vms') . '</a></p>';
		echo '</section>';
	}
}

if (!function_exists('vms_add_dispatch_register_shell_page')) {
	function vms_add_dispatch_register_shell_page(array $pages): array
	{
		$pages[] = vms_add_dispatch_page_slug();
		return array_values(array_unique($pages));
	}
}
add_filter('vms_admin_ui_shell_pages', 'vms_add_dispatch_register_shell_page');

if (!function_exists('vms_add_dispatch_add_nav_item')) {
	function vms_add_dispatch_add_nav_item(array $items, string $cluster_key): array
	{
		if ($cluster_key !== 'vendors_staff') {
			return $items;
		}

		$nav_label = 'ADD Dispatch';
		if (vms_add_dispatch_should_render_shell_count()) {
			$count = vms_add_dispatch_current_pending_count();
			if ($count > 0) {
			$nav_label .= ' (' . $count . ')';
			}
		}
		$items[] = array(
			'label' => $nav_label,
			'url' => vms_add_dispatch_admin_url(),
		);
		return $items;
	}
}
add_filter('vms_admin_ui_nav_cluster_items', 'vms_add_dispatch_add_nav_item', 20, 2);

if (!function_exists('vms_add_dispatch_register_tours')) {
	function vms_add_dispatch_register_tours(array $tours): array
	{
		$tours[] = array(
			'id' => 'vms.add_dispatch.basics',
			'title' => __('Availability & Date Dispatch', 'vms'),
			'screen' => 'admin:' . vms_add_dispatch_page_slug(),
			'version' => '1.0.1',
			'level' => 'beginner',
			'audience' => array('admin'),
			'steps' => array(
				array(
					'id' => 'add-help',
					'selector' => '[data-vms-tour="add-dispatch.help"]',
					'title' => __('What ADD does', 'vms'),
					'body' => __('Use ADD to contact eligible vendors for one Event Plan/date, collect secure YES or NO responses, and write those answers back into canonical availability.', 'vms'),
					'position' => 'bottom',
				),
				array(
					'id' => 'add-builder',
					'selector' => '[data-vms-tour="add-dispatch.builder"]',
					'title' => __('Build the request', 'vms'),
					'body' => __('Choose the missing role/type, decide whether to include unknown or tentative vendors, preview the recipient list, then send the request.', 'vms'),
					'position' => 'bottom',
				),
				array(
					'id' => 'add-requests',
					'selector' => '[data-vms-tour="add-dispatch.requests"]',
					'title' => __('Review responses', 'vms'),
					'body' => __('This table shows who was contacted, who replied, and which available responders can be assigned back onto the Event Plan.', 'vms'),
					'position' => 'top',
				),
			),
		);

		return $tours;
	}
}
add_filter('vms_tours_register', 'vms_add_dispatch_register_tours');

if (!function_exists('vms_add_dispatch_register_event_plan_metabox')) {
	function vms_add_dispatch_register_event_plan_metabox(): void
	{
		add_meta_box(
			'vms_event_plan_add_dispatch',
			__('ADD Responses', 'vms'),
			'vms_add_dispatch_render_event_plan_metabox',
			'vms_event_plan',
			'normal',
			'low'
		);
	}
}
add_action('add_meta_boxes', 'vms_add_dispatch_register_event_plan_metabox');

if (!function_exists('vms_add_dispatch_status_pill')) {
	function vms_add_dispatch_status_pill(string $status): string
	{
		$status = sanitize_key($status);
		$label_map = array(
			'requested' => __('Requested', 'vms'),
			'available' => __('Available', 'vms'),
			'unavailable' => __('Unavailable', 'vms'),
			'withdrawn' => __('Withdrawn', 'vms'),
			'active' => __('Active', 'vms'),
			'closed' => __('Closed', 'vms'),
			'open' => __('Open', 'vms'),
			'full' => __('Full', 'vms'),
			'over_capacity' => __('Over capacity', 'vms'),
			'excluded' => __('Excluded', 'vms'),
		);
		$class = 'vms-add-pill vms-add-pill--neutral';
		if ($status === 'available' || $status === 'active' || $status === 'open') {
			$class = 'vms-add-pill vms-add-pill--success';
		} elseif ($status === 'unavailable' || $status === 'closed' || $status === 'withdrawn' || $status === 'over_capacity') {
			$class = 'vms-add-pill vms-add-pill--danger';
		} elseif ($status === 'requested') {
			$class = 'vms-add-pill vms-add-pill--warning';
		}

		return '<span class="' . esc_attr($class) . '">' . esc_html($label_map[$status] ?? $status) . '</span>';
	}
}

if (!function_exists('vms_add_dispatch_source_pill')) {
	function vms_add_dispatch_source_pill(string $source): string
	{
		$source = sanitize_key($source);
		$label_map = array(
			'portal_interest' => __('Vendor Portal', 'vms'),
			'email' => __('Email Link', 'vms'),
			'' => __('ADD', 'vms'),
		);

		return '<span class="vms-add-pill vms-add-pill--neutral vms-add-pill--source">' . esc_html($label_map[$source] ?? __('ADD', 'vms')) . '</span>';
	}
}

if (!function_exists('vms_add_dispatch_render_event_plan_metabox')) {
	function vms_add_dispatch_render_event_plan_metabox(WP_Post $post): void
	{
		$context = vms_add_dispatch_get_event_plan_context((int) $post->ID);
		if (!$context) {
			echo '<p>' . esc_html__('Save this Event Plan first to use Availability & Date Dispatch.', 'vms') . '</p>';
			return;
		}

		$open_url = vms_add_dispatch_admin_url(array('event_plan_id' => (int) $post->ID));
		$responses = vms_add_dispatch_get_recent_responses_for_event_plan((int) $post->ID, 8);
		$requests = vms_add_dispatch_get_requests_for_event_plan((int) $post->ID, 6);

		echo '<div class="vms-add-card vms-add-card--compact">';
		echo '<p class="vms-add-muted">' . esc_html__('Use ADD to contact eligible vendors for this date, capture availability responses, and assign a responder back onto the Event Plan.', 'vms') . '</p>';
		if (!empty($context['missing_slots'])) {
			$labels = isset($context['missing_slot_labels']) && is_array($context['missing_slot_labels'])
				? array_values(array_filter(array_map('strval', (array) $context['missing_slot_labels'])))
				: array();
			echo '<p><strong>' . esc_html__('Current missing slots:', 'vms') . '</strong> ' . esc_html(implode(' | ', $labels)) . '</p>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url($open_url) . '">' . esc_html__('Open ADD Dispatch', 'vms') . '</a></p>';
		echo '</div>';

		if (!empty($requests)) {
			echo '<div class="vms-add-card">';
			echo '<h4>' . esc_html__('Recent ADD Requests', 'vms') . '</h4>';
			echo '<table class="widefat striped vms-add-table"><thead><tr><th>' . esc_html__('Created', 'vms') . '</th><th>' . esc_html__('Target', 'vms') . '</th><th>' . esc_html__('Status', 'vms') . '</th><th>' . esc_html__('Recipients', 'vms') . '</th></tr></thead><tbody>';
			foreach ($requests as $request) {
				$target = sanitize_key((string) ($request['target_mode'] ?? 'secondary')) === 'primary'
					? __('Primary vendor', 'vms')
					: (string) vms_add_dispatch_type_label((string) ($request['vendor_type'] ?? ''));
				echo '<tr>';
				echo '<td>' . esc_html((string) ($request['created_at'] ?? '')) . '</td>';
				echo '<td>' . esc_html($target) . '</td>';
				echo '<td>' . vms_add_dispatch_status_pill((string) ($request['status'] ?? 'active')) . '</td>';
				echo '<td>' . esc_html(sprintf('%d total / %d available', (int) ($request['recipient_total'] ?? 0), (int) ($request['available_count'] ?? 0))) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}

		if (!empty($responses)) {
			echo '<div class="vms-add-card">';
			echo '<h4>' . esc_html__('Recent Responses', 'vms') . '</h4>';
			echo '<table class="widefat striped vms-add-table"><thead><tr><th>' . esc_html__('Vendor', 'vms') . '</th><th>' . esc_html__('Source', 'vms') . '</th><th>' . esc_html__('Status', 'vms') . '</th><th>' . esc_html__('Responded', 'vms') . '</th><th>' . esc_html__('Action', 'vms') . '</th></tr></thead><tbody>';
			foreach ($responses as $response) {
				$status = sanitize_key((string) ($response['response_status'] ?? 'requested'));
				echo '<tr>';
				echo '<td>' . esc_html((string) ($response['vendor_title'] ?? '')) . '</td>';
				echo '<td>' . vms_add_dispatch_source_pill((string) ($response['response_source'] ?? '')) . '</td>';
				echo '<td>' . vms_add_dispatch_status_pill($status) . '</td>';
				echo '<td>' . esc_html((string) ($response['responded_at'] ?? '')) . '</td>';
				echo '<td>';
				if ($status === 'available') {
					echo '<a class="button button-small" href="' . esc_url($open_url) . '">' . esc_html__('Review in ADD', 'vms') . '</a>';
				} else {
					echo '<span class="vms-add-muted">' . esc_html__('See request details in ADD', 'vms') . '</span>';
				}
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}
	}
}

if (!function_exists('vms_add_dispatch_render_request_builder')) {
	function vms_add_dispatch_render_request_builder(array $context, array $builder_args, array $recipients): void
	{
		$event_plan_id = (int) ($context['event_plan_id'] ?? 0);
		$target_mode = sanitize_key((string) ($builder_args['target_mode'] ?? 'secondary'));
		$options = vms_add_dispatch_type_options();

		echo '<div class="vms-add-card" data-vms-tour="add-dispatch.builder">';
		echo '<h2>' . esc_html__('Build Request', 'vms') . '</h2>';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr(vms_add_dispatch_page_slug()) . '">';
		echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '">';
		echo '<div class="vms-add-grid">';
		echo '<label><span>' . esc_html__('Target role', 'vms') . '</span><select name="target_mode">';
		echo '<option value="primary"' . selected($target_mode, 'primary', false) . '>' . esc_html__('Primary vendor', 'vms') . '</option>';
		echo '<option value="secondary"' . selected($target_mode, 'secondary', false) . '>' . esc_html__('Secondary vendor', 'vms') . '</option>';
		echo '</select></label>';
		echo '<label><span>' . esc_html__('Vendor type', 'vms') . '</span><select name="vendor_type">';
		echo '<option value="">' . esc_html__('Choose a vendor type', 'vms') . '</option>';
		foreach ($options as $slug => $label) {
			echo '<option value="' . esc_attr((string) $slug) . '"' . selected((string) ($builder_args['vendor_type'] ?? ''), (string) $slug, false) . '>' . esc_html((string) $label) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="vms-add-grid-span-2"><span>' . esc_html__('Message', 'vms') . '</span><textarea name="message" rows="4">' . esc_textarea((string) ($builder_args['message'] ?? '')) . '</textarea></label>';
		echo '</div>';
		echo '<div class="vms-add-toggle-row">';
		echo '<label><input type="checkbox" name="include_no_response" value="1"' . checked(1, (int) ($builder_args['include_no_response'] ?? $builder_args['include_unknown'] ?? 0), false) . '> ' . esc_html__('Include vendors with no response / no availability setup', 'vms') . '</label>';
		echo '<label><input type="checkbox" name="include_tentative" value="1"' . checked(1, (int) ($builder_args['include_tentative'] ?? 0), false) . '> ' . esc_html__('Include tentative vendors', 'vms') . '</label>';
		echo '<label><input type="checkbox" name="include_previously_contacted" value="1"' . checked(1, (int) ($builder_args['include_previously_contacted'] ?? 0), false) . '> ' . esc_html__('Include previously contacted vendors', 'vms') . '</label>';
		echo '</div>';
		echo '<p><button class="button button-secondary" type="submit">' . esc_html__('Update Vendor Review', 'vms') . '</button></p>';
		echo '</form>';

		$candidates = vms_add_dispatch_collect_recipient_candidates($context, $builder_args);
		$included_count = count($recipients);
		$total_candidates = count($candidates);
		if (!empty($candidates)) {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			echo '<input type="hidden" name="action" value="vms_add_dispatch_send_request">';
			wp_nonce_field('vms_add_dispatch_send_request', 'vms_add_dispatch_nonce');
			echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '">';
			echo '<input type="hidden" name="target_mode" value="' . esc_attr((string) ($builder_args['target_mode'] ?? 'secondary')) . '">';
			echo '<input type="hidden" name="vendor_type" value="' . esc_attr((string) ($builder_args['vendor_type'] ?? '')) . '">';
			echo '<input type="hidden" name="message" value="' . esc_attr((string) ($builder_args['message'] ?? '')) . '">';
			echo '<input type="hidden" name="include_unknown" value="' . esc_attr((string) (int) ($builder_args['include_unknown'] ?? 0)) . '">';
			echo '<input type="hidden" name="include_no_response" value="' . esc_attr((string) (int) ($builder_args['include_no_response'] ?? $builder_args['include_unknown'] ?? 0)) . '">';
			echo '<input type="hidden" name="include_tentative" value="' . esc_attr((string) (int) ($builder_args['include_tentative'] ?? 0)) . '">';
			echo '<input type="hidden" name="include_previously_contacted" value="' . esc_attr((string) (int) ($builder_args['include_previously_contacted'] ?? 0)) . '">';
			echo '<h3 class="vms-add-review-heading">' . esc_html__('Vendor Review', 'vms') . ' <span class="vms-add-muted">(' . esc_html(sprintf(__('%1$d matching this vendor type', 'vms'), $total_candidates)) . ' • <span data-vms-add-eligible-count>' . esc_html((string) $included_count) . '</span> ' . esc_html__('selectable', 'vms') . ' • <span data-vms-add-selected-count>0</span> ' . esc_html__('selected', 'vms') . ')</span></h3>';
			echo '<div class="vms-add-recipient-actions">';
			echo '<button class="button button-small" type="button" data-vms-add-select-all>' . esc_html__('Select all eligible', 'vms') . '</button>';
			echo '<button class="button button-small" type="button" data-vms-add-clear-all>' . esc_html__('Clear selected', 'vms') . '</button>';
			echo '<span class="vms-add-muted">' . esc_html__('Eligible vendors are selectable, but only checked vendors will receive this request.', 'vms') . '</span>';
			echo '</div>';
			echo '<table class="widefat striped vms-add-table"><thead><tr><th></th><th>' . esc_html__('Vendor', 'vms') . '</th><th>' . esc_html__('State', 'vms') . '</th><th>' . esc_html__('ADD decision', 'vms') . '</th><th>' . esc_html__('Email', 'vms') . '</th></tr></thead><tbody>';
			foreach ($candidates as $recipient) {
				$included = !empty($recipient['included']);
				$state = sanitize_key((string) ($recipient['state'] ?? 'requested'));
				$selection_reason = (string) ($recipient['selection_reason'] ?? $recipient['detail'] ?? '');
				$base_selectable = !empty($recipient['contactable']) && in_array($state, array('available', 'no-response', 'tentative'), true);
				$row_attrs = array(
					'class' => 'vms-add-recipient-row',
					'data-vms-add-state' => $state,
					'data-vms-add-contactable' => !empty($recipient['contactable']) ? '1' : '0',
					'data-vms-add-base-selectable' => $base_selectable ? '1' : '0',
					'data-vms-add-previously-contacted' => !empty($recipient['previously_contacted']) ? '1' : '0',
					'data-vms-add-default-detail' => $selection_reason,
					'data-vms-add-no-email-detail' => __('No vendor email on file.', 'vms'),
					'data-vms-add-no-response-detail' => __('Enable no-response vendors to select this contact.', 'vms'),
					'data-vms-add-tentative-detail' => __('Enable tentative vendors to select this contact.', 'vms'),
					'data-vms-add-previous-detail' => __('Enable previously contacted vendors to select this contact.', 'vms'),
				);
				echo '<tr';
				foreach ($row_attrs as $attr_name => $attr_value) {
					echo ' ' . esc_attr($attr_name) . '="' . esc_attr((string) $attr_value) . '"';
				}
				echo '>';
				echo '<td><input type="checkbox" class="vms-add-recipient-checkbox" name="vendor_ids[]" value="' . esc_attr((string) ($recipient['vendor_id'] ?? 0)) . '"' . disabled(!$included, true, false) . '></td>';
				echo '<td><strong>' . esc_html((string) ($recipient['title'] ?? '')) . '</strong>';
				if (!empty($recipient['previously_contacted'])) {
					echo '<div class="description">' . esc_html__('Previously contacted on this Event Plan', 'vms') . '</div>';
				}
				echo '</td>';
				echo '<td>' . vms_add_dispatch_status_pill((string) ($recipient['state'] ?? 'requested')) . '</td>';
				echo '<td class="vms-add-decision-cell">';
				if ($included) {
					echo '<strong data-vms-add-decision-label>' . esc_html__('Selectable.', 'vms') . '</strong>';
					if ($state === 'no-response') {
						echo '<div class="description" data-vms-add-decision-detail>' . esc_html__('No response / no setup. Select this row to contact them.', 'vms') . '</div>';
					} elseif ($selection_reason !== '') {
						echo '<div class="description" data-vms-add-decision-detail>' . esc_html($selection_reason) . '</div>';
					} else {
						echo '<div class="description" data-vms-add-decision-detail></div>';
					}
				} else {
					echo '<strong data-vms-add-decision-label>' . esc_html__('Excluded.', 'vms') . '</strong>';
					$detail = $selection_reason;
					if ($state === 'no-response' && empty($builder_args['include_unknown']) && empty($builder_args['include_no_response'])) {
						$detail = __('Enable no-response vendors to select this contact.', 'vms');
					}
					echo '<div class="description" data-vms-add-decision-detail>' . esc_html($detail) . '</div>';
				}
				echo '</td>';
				echo '<td>' . esc_html((string) ($recipient['email'] ?? '')) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '<p><button class="button button-primary" type="submit" data-vms-add-send-button disabled>' . esc_html__('Send ADD Request', 'vms') . '</button> <span class="vms-add-muted">' . esc_html__('Select one or more recipients before sending.', 'vms') . '</span></p>';
			echo '</form>';
			echo '<script>(function(){var root=document.currentScript.previousElementSibling;if(!root){return;}var filterForm=root.previousElementSibling;var select=root.querySelector("[data-vms-add-select-all]");var clear=root.querySelector("[data-vms-add-clear-all]");var send=root.querySelector("[data-vms-add-send-button]");var eligibleCount=root.querySelector("[data-vms-add-eligible-count]");var selectedCount=root.querySelector("[data-vms-add-selected-count]");function filterChecked(name){var field=filterForm?filterForm.querySelector("[name=\\"" + name + "\\"]"):null;return !!(field&&field.checked);}function syncHidden(name,checked){root.querySelectorAll("input[type=\\"hidden\\"][name=\\"" + name + "\\"]").forEach(function(field){field.value=checked?"1":"0";});}function rowDetail(row,reason){var detail=row.querySelector("[data-vms-add-decision-detail]");if(detail){detail.textContent=reason||"";}}function rowLabel(row,label){var target=row.querySelector("[data-vms-add-decision-label]");if(target){target.textContent=label;}}function isEligible(row){if(row.getAttribute("data-vms-add-contactable")!=="1"){rowDetail(row,row.getAttribute("data-vms-add-no-email-detail")||"No vendor email on file.");return false;}if(row.getAttribute("data-vms-add-base-selectable")!=="1"){rowDetail(row,row.getAttribute("data-vms-add-default-detail")||"Not eligible.");return false;}var state=row.getAttribute("data-vms-add-state")||"";if(state==="no-response"&&!filterChecked("include_no_response")){rowDetail(row,row.getAttribute("data-vms-add-no-response-detail")||"Enable no-response vendors to select this contact.");return false;}if(state==="tentative"&&!filterChecked("include_tentative")){rowDetail(row,row.getAttribute("data-vms-add-tentative-detail")||"Enable tentative vendors to select this contact.");return false;}if(row.getAttribute("data-vms-add-previously-contacted")==="1"&&!filterChecked("include_previously_contacted")){rowDetail(row,row.getAttribute("data-vms-add-previous-detail")||"Enable previously contacted vendors to select this contact.");return false;}rowDetail(row,state==="no-response"?"No response / no setup. Select this row to contact them.":(row.getAttribute("data-vms-add-default-detail")||"Select this row to contact them."));return true;}function boxes(){return root.querySelectorAll(".vms-add-recipient-checkbox");}function update(){var eligible=0;var selected=0;syncHidden("include_no_response",filterChecked("include_no_response"));syncHidden("include_unknown",filterChecked("include_no_response"));syncHidden("include_tentative",filterChecked("include_tentative"));syncHidden("include_previously_contacted",filterChecked("include_previously_contacted"));root.querySelectorAll(".vms-add-recipient-row").forEach(function(row){var box=row.querySelector(".vms-add-recipient-checkbox");var ok=isEligible(row);if(box){box.disabled=!ok;if(!ok){box.checked=false;}if(box.checked){selected++;}}if(ok){eligible++;rowLabel(row,"Selectable.");}else{rowLabel(row,"Excluded.");}});if(eligibleCount){eligibleCount.textContent=String(eligible);}if(selectedCount){selectedCount.textContent=String(selected);}if(send){send.disabled=selected<=0;}}if(select){select.addEventListener("click",function(){boxes().forEach(function(box){if(!box.disabled){box.checked=true;}});update();});}if(clear){clear.addEventListener("click",function(){boxes().forEach(function(box){box.checked=false;});update();});}boxes().forEach(function(box){box.addEventListener("change",update);});if(filterForm){filterForm.querySelectorAll("[name=\\"include_no_response\\"],[name=\\"include_tentative\\"],[name=\\"include_previously_contacted\\"]").forEach(function(field){field.addEventListener("change",update);});}update();})();</script>';
		} else {
			echo '<p class="vms-add-muted">' . esc_html__('No vendors currently match this vendor type. Adjust the vendor type and preview again.', 'vms') . '</p>';
		}
		echo '</div>';
	}
}

if (!function_exists('vms_add_dispatch_render_request_history')) {
	function vms_add_dispatch_render_request_history(array $requests, int $event_plan_id = 0): void
	{
		echo '<div class="vms-add-card" data-vms-tour="add-dispatch.requests">';
		echo '<h2>' . esc_html__('Request History', 'vms') . '</h2>';
		if (empty($requests)) {
			echo '<p class="vms-add-muted">' . esc_html__('No ADD requests have been created yet.', 'vms') . '</p>';
			echo '</div>';
			return;
		}

		foreach ($requests as $request) {
			$request_id = (int) ($request['id'] ?? 0);
			$responses = vms_add_dispatch_get_responses_for_request($request_id);
			$target = sanitize_key((string) ($request['target_mode'] ?? 'secondary')) === 'primary'
				? __('Primary vendor', 'vms')
				: (string) vms_add_dispatch_type_label((string) ($request['vendor_type'] ?? ''));
			$source = '';
			if (!empty($responses)) {
				$source = sanitize_key((string) ($responses[0]['response_source'] ?? ''));
			}
			echo '<div class="vms-add-request-block">';
			echo '<div class="vms-add-request-head">';
			echo '<div><strong>' . esc_html($target) . '</strong> ' . vms_add_dispatch_source_pill($source) . '<div class="description">' . esc_html((string) ($request['created_at'] ?? '')) . '</div></div>';
			echo '<div class="vms-add-request-actions">';
			echo vms_add_dispatch_status_pill((string) ($request['status'] ?? 'active'));
			if (sanitize_key((string) ($request['status'] ?? 'active')) === 'active') {
				$close_url = wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'vms_add_dispatch_close_request',
							'request_id' => $request_id,
							'event_plan_id' => $event_plan_id > 0 ? $event_plan_id : (int) ($request['event_plan_id'] ?? 0),
						),
						admin_url('admin-post.php')
					),
					'vms_add_dispatch_close_request_' . $request_id
				);
				echo ' <a class="button button-small" href="' . esc_url($close_url) . '">' . esc_html__('Close Request', 'vms') . '</a>';
			}
			echo '</div></div>';

			if (!empty($request['message'])) {
				echo '<p class="vms-add-muted">' . esc_html((string) $request['message']) . '</p>';
			}

			echo '<table class="widefat striped vms-add-table"><thead><tr><th>' . esc_html__('Vendor', 'vms') . '</th><th>' . esc_html__('Source', 'vms') . '</th><th>' . esc_html__('Status', 'vms') . '</th><th>' . esc_html__('Responded', 'vms') . '</th><th>' . esc_html__('Action', 'vms') . '</th></tr></thead><tbody>';
			if (empty($responses)) {
				echo '<tr><td colspan="5">' . esc_html__('No recipients were stored for this request.', 'vms') . '</td></tr>';
			} else {
				foreach ($responses as $response) {
					$response_id = (int) ($response['id'] ?? 0);
					$status = sanitize_key((string) ($response['response_status'] ?? 'requested'));
					echo '<tr>';
					$target_label = sanitize_key((string) ($request['target_mode'] ?? 'secondary')) === 'primary' ? __('Primary vendor', 'vms') : (string) vms_add_dispatch_type_label((string) ($request['vendor_type'] ?? ''));
					echo '<td><strong>' . esc_html((string) ($response['vendor_title'] ?? '')) . '</strong><div class="description">' . esc_html($target_label !== '' ? $target_label : __('Vendor', 'vms')) . ' • ' . esc_html((string) ($response['vendor_email'] ?? '')) . '</div></td>';
					echo '<td>' . vms_add_dispatch_source_pill((string) ($response['response_source'] ?? '')) . '</td>';
					echo '<td>' . vms_add_dispatch_status_pill($status) . '</td>';
					echo '<td>' . esc_html((string) ($response['responded_at'] ?? '')) . '</td>';
					echo '<td>';
					if ($status === 'available') {
						if (trim((string) ($response['assigned_at'] ?? '')) !== '') {
							echo '<span class="vms-add-pill vms-add-pill--success">' . esc_html__('Assigned', 'vms') . '</span> ';
						} else {
							$assign_url = vms_add_dispatch_assignment_review_url($response_id, $event_plan_id > 0 ? $event_plan_id : (int) ($request['event_plan_id'] ?? 0));
							echo '<a class="button button-small button-primary" href="' . esc_url($assign_url) . '">' . esc_html__('Review Assignment', 'vms') . '</a> ';
						}
					}
					if ($status === 'requested') {
						$resend_url = wp_nonce_url(
							add_query_arg(
								array(
									'action' => 'vms_add_dispatch_resend_response',
									'response_id' => $response_id,
									'event_plan_id' => $event_plan_id > 0 ? $event_plan_id : (int) ($request['event_plan_id'] ?? 0),
								),
								admin_url('admin-post.php')
							),
							'vms_add_dispatch_resend_response_' . $response_id
						);
						echo '<a class="button button-small" href="' . esc_url($resend_url) . '">' . esc_html__('Resend Request', 'vms') . '</a>';
					}
					echo '</td>';
					echo '</tr>';
				}
			}
			echo '</tbody></table>';
			echo '</div>';
		}

		echo '</div>';
	}
}

if (!function_exists('vms_add_dispatch_dashboard_filters')) {
	function vms_add_dispatch_dashboard_filters(array $source = array()): array
	{
		return array(
			'show_full_groups' => !empty($source['show_full_groups']),
			'show_over_capacity_groups' => !empty($source['show_over_capacity_groups']),
			'include_past_events' => !empty($source['include_past_events']),
			'include_cancelled_events' => !empty($source['include_cancelled_events']),
		);
	}
}

if (!function_exists('vms_add_dispatch_dashboard_need_rows')) {
	function vms_add_dispatch_dashboard_need_rows(array $context, array $filters): array
	{
		$rows = isset($context['vendor_need_rows']) && is_array($context['vendor_need_rows'])
			? (array) $context['vendor_need_rows']
			: (function_exists('vms_add_dispatch_context_vendor_need_rows') ? vms_add_dispatch_context_vendor_need_rows((array) $context, true) : array());

		return array_values(array_filter($rows, static function ($row) use ($filters): bool {
			if (!is_array($row)) {
				return false;
			}
			if (!empty($row['is_open'])) {
				return true;
			}
			$status = sanitize_key((string) ($row['status'] ?? ''));
			if ($status === 'over_capacity') {
				return !empty($filters['show_over_capacity_groups']);
			}
			if ($status === 'full') {
				return !empty($filters['show_full_groups']);
			}
			return false;
		}));
	}
}

if (!function_exists('vms_add_dispatch_need_row_summary_label')) {
	function vms_add_dispatch_need_row_summary_label(array $row): string
	{
		$target_mode = sanitize_key((string) ($row['target_mode'] ?? 'secondary'));
		if ($target_mode === 'primary') {
			return __('Primary Vendor missing', 'vms');
		}

		$label = (string) ($row['label'] ?? __('Vendor', 'vms'));
		$status = sanitize_key((string) ($row['status'] ?? ''));
		if ($status === 'over_capacity') {
			return sprintf(__('%1$s: over capacity by %2$d', 'vms'), $label, max(0, (int) ($row['over_capacity_by'] ?? 0)));
		}
		if ($status === 'full') {
			return sprintf(__('%s: full', 'vms'), $label);
		}

		return sprintf(
			_n('%1$s: %2$d slot open', '%1$s: %2$d slots open', max(1, (int) ($row['open_needed'] ?? $row['open_spots'] ?? 1)), 'vms'),
			$label,
			max(1, (int) ($row['open_needed'] ?? $row['open_spots'] ?? 1))
		);
	}
}

if (!function_exists('vms_add_dispatch_assignment_review_url')) {
	function vms_add_dispatch_assignment_review_url(int $response_id, int $event_plan_id = 0): string
	{
		$args = array(
			'page' => vms_add_dispatch_page_slug(),
			'add_assignment_response_id' => absint($response_id),
		);
		if ($event_plan_id > 0) {
			$args['event_plan_id'] = absint($event_plan_id);
		}

		return wp_nonce_url(
			add_query_arg($args, admin_url('admin.php')),
			'vms_add_dispatch_assignment_review_' . absint($response_id)
		);
	}
}

if (!function_exists('vms_add_dispatch_render_assignment_review')) {
	function vms_add_dispatch_render_assignment_review(int $response_id): void
	{
		$selected_type = isset($_GET['assign_as']) ? sanitize_key((string) wp_unslash($_GET['assign_as'])) : '';
		$review = vms_add_dispatch_assignment_review($response_id, $selected_type);
		echo '<div class="vms-add-card vms-add-assignment-review" data-vms-tour="add-dispatch.assignment-review">';
		echo '<h2>' . esc_html__('Review ADD Assignment', 'vms') . '</h2>';
		if (is_wp_error($review)) {
			echo '<div class="vms-add-message vms-add-message--error">' . esc_html($review->get_error_message()) . '</div>';
			echo '<p><a class="button" href="' . esc_url(vms_add_dispatch_admin_url()) . '">' . esc_html__('Back to ADD', 'vms') . '</a></p>';
			echo '</div>';
			return;
		}

		$review = (array) $review;
		$response = (array) ($review['response'] ?? array());
		$request = (array) ($review['request'] ?? array());
		$event_plan_id = (int) ($review['event_plan_id'] ?? 0);
		$target_mode = sanitize_key((string) ($review['target_mode'] ?? 'secondary'));
		$targets = (array) ($review['targets'] ?? array());
		$selected_type = (string) ($review['selected_type'] ?? '');

		echo '<div class="vms-add-review-summary">';
		echo '<div><span class="vms-add-label">' . esc_html__('Event Plan', 'vms') . '</span><strong>' . esc_html((string) ($review['event_title'] ?? '')) . '</strong><div class="description">' . esc_html(vms_add_dispatch_format_date((string) ($review['event_date'] ?? ''))) . '</div></div>';
		echo '<div><span class="vms-add-label">' . esc_html__('Vendor', 'vms') . '</span><strong>' . esc_html((string) ($review['vendor_title'] ?? '')) . '</strong></div>';
		echo '<div><span class="vms-add-label">' . esc_html__('Original ADD type', 'vms') . '</span><strong>' . esc_html((string) ($review['original_type_label'] ?? '')) . '</strong></div>';
		echo '<div><span class="vms-add-label">' . esc_html__('Current vendor type(s)', 'vms') . '</span><strong>' . esc_html((string) ($review['current_type_labels'] ?? '')) . '</strong></div>';
		echo '</div>';

		foreach ((array) ($review['warnings'] ?? array()) as $warning) {
			echo '<div class="vms-add-message vms-add-message--warning">' . esc_html((string) $warning) . '</div>';
		}

		echo '<h3>' . esc_html__('Existing Event Plan vendor groups', 'vms') . '</h3>';
		$group_rows = (array) ($review['group_rows'] ?? array());
		if (empty($group_rows)) {
			echo '<p class="vms-add-muted">' . esc_html__('No Additional Vendor groups exist yet. Confirming a valid target can create the matching group.', 'vms') . '</p>';
		} else {
			echo '<table class="widefat striped vms-add-table"><thead><tr><th>' . esc_html__('Group', 'vms') . '</th><th>' . esc_html__('Mode', 'vms') . '</th><th>' . esc_html__('Filled', 'vms') . '</th><th>' . esc_html__('Capacity', 'vms') . '</th><th>' . esc_html__('Status', 'vms') . '</th></tr></thead><tbody>';
			foreach ($group_rows as $group) {
				$capacity = ($group['capacity'] ?? null);
				echo '<tr>';
				echo '<td>' . esc_html((string) ($group['label'] ?? __('Vendor', 'vms'))) . '</td>';
				echo '<td>' . esc_html(sanitize_key((string) ($group['mode'] ?? 'standard')) === 'market' ? __('Market', 'vms') : __('Standard', 'vms')) . '</td>';
				echo '<td>' . esc_html((string) (int) ($group['filled_slots'] ?? 0)) . '</td>';
				echo '<td>' . esc_html($capacity !== null && $capacity !== '' ? (string) (int) $capacity : __('No slot limit', 'vms')) . '</td>';
				echo '<td>' . vms_add_dispatch_status_pill((string) ($group['status'] ?? 'full')) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h3>' . esc_html__('Eligible target groups for this vendor', 'vms') . '</h3>';
		if ($target_mode === 'primary') {
			echo '<p class="vms-add-muted">' . esc_html__('This response targets the Primary Vendor slot. Confirming will set the selected vendor as Primary Vendor if that slot is not already occupied by another vendor.', 'vms') . '</p>';
		} elseif (empty($targets)) {
			echo '<div class="vms-add-message vms-add-message--error">' . esc_html__('This vendor has no current Additional Vendor type that can be assigned on this Event Plan.', 'vms') . '</div>';
		} else {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-add-assignment-form">';
			echo '<input type="hidden" name="action" value="vms_add_dispatch_confirm_assignment">';
			echo '<input type="hidden" name="response_id" value="' . esc_attr((string) $response_id) . '">';
			echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '">';
			wp_nonce_field('vms_add_dispatch_confirm_assignment_' . $response_id, 'vms_add_dispatch_assignment_nonce');
			echo '<label><span class="vms-add-label">' . esc_html__('Assign as', 'vms') . '</span><select name="target_type">';
			foreach ($targets as $type_slug => $target) {
				$target = (array) $target;
				$suffix = empty($target['exists']) ? __(' - create group', 'vms') : '';
				echo '<option value="' . esc_attr((string) $type_slug) . '"' . selected($selected_type, (string) $type_slug, false) . '>' . esc_html((string) ($target['label'] ?? $type_slug) . $suffix) . '</option>';
			}
			echo '</select></label>';
			foreach ($targets as $type_slug => $target) {
				$target = (array) $target;
				echo '<div class="vms-add-target-card' . ($selected_type === (string) $type_slug ? ' is-selected' : '') . '">';
				echo '<strong>' . esc_html((string) ($target['label'] ?? $type_slug)) . '</strong>';
				echo '<span class="vms-add-muted"> ' . esc_html((string) ($target['capacity_label'] ?? '')) . '</span>';
				if (empty($target['exists'])) {
					echo '<div class="description">' . esc_html__('This target group does not exist yet. Confirming will create it using the default capacity for this vendor type.', 'vms') . '</div>';
				}
				foreach ((array) ($target['warnings'] ?? array()) as $warning) {
					echo '<div class="vms-add-message vms-add-message--warning">' . esc_html((string) $warning) . '</div>';
				}
				echo '</div>';
			}
			echo '<label class="vms-add-inline-check"><input type="checkbox" name="allow_over_capacity" value="1"> ' . esc_html__('Allow over-capacity assignment for the selected target group', 'vms') . '</label>';
			echo '<p><button type="submit" class="button button-primary">' . esc_html__('Confirm Assignment', 'vms') . '</button> <a class="button" href="' . esc_url(vms_add_dispatch_admin_url(array('event_plan_id' => $event_plan_id))) . '">' . esc_html__('Cancel', 'vms') . '</a></p>';
			echo '</form>';
		}

		if ($target_mode === 'primary') {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-add-assignment-form">';
			echo '<input type="hidden" name="action" value="vms_add_dispatch_confirm_assignment">';
			echo '<input type="hidden" name="response_id" value="' . esc_attr((string) $response_id) . '">';
			echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '">';
			wp_nonce_field('vms_add_dispatch_confirm_assignment_' . $response_id, 'vms_add_dispatch_assignment_nonce');
			echo '<p><button type="submit" class="button button-primary">' . esc_html__('Confirm Primary Vendor Assignment', 'vms') . '</button> <a class="button" href="' . esc_url(vms_add_dispatch_admin_url(array('event_plan_id' => $event_plan_id))) . '">' . esc_html__('Cancel', 'vms') . '</a></p>';
			echo '</form>';
		}

		echo '</div>';
	}
}

if (!function_exists('vms_add_dispatch_render_dashboard_home')) {
	function vms_add_dispatch_render_dashboard_home(): void
	{
		$counts = vms_add_dispatch_get_dashboard_counts();
		$dashboard_filters = vms_add_dispatch_dashboard_filters(isset($_GET) && is_array($_GET) ? (array) wp_unslash($_GET) : array());
		$need_scan = function_exists('vms_add_dispatch_get_event_plan_need_scan')
			? vms_add_dispatch_get_event_plan_need_scan(12, 8, $dashboard_filters)
			: array('contexts' => array(), 'excluded' => array());
		$vendor_need_events = (array) ($need_scan['contexts'] ?? array());
		$excluded_need_events = (array) ($need_scan['excluded'] ?? array());
		$open_events = array_values(array_filter($vendor_need_events, static function (array $context) use ($dashboard_filters): bool {
			return !empty(vms_add_dispatch_dashboard_need_rows($context, $dashboard_filters));
		}));
		$open_events = array_slice($open_events, 0, 10);
		$requests = vms_add_dispatch_get_recent_requests(8);
		$responses = vms_add_dispatch_get_recent_responses(8);
		$portal_interest_rows = function_exists('vms_add_dispatch_get_pending_portal_interest_rows')
			? vms_add_dispatch_get_pending_portal_interest_rows(8)
			: array();

		echo '<div class="vms-add-card vms-add-card--hero" data-vms-tour="add-dispatch.help">';
		echo '<div class="vms-add-hero">';
		echo '<div>';
		echo '<h2>' . esc_html__('ADD is your vendor outreach engine', 'vms') . '</h2>';
		echo '<p class="vms-add-muted">' . esc_html__('Phase 1 is now live: single-event dispatch from saved Event Plans. Use the dashboard below to jump into open dates, monitor replies, and follow up on active requests without hunting through the system.', 'vms') . '</p>';
		echo '</div>';
		echo '<div class="vms-add-hero-actions">';
		echo '<a class="button button-primary" href="' . esc_url(admin_url('edit.php?post_type=vms_event_plan')) . '">' . esc_html__('Open Event Plans', 'vms') . '</a>';
		echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . vms_vendor_availability_page_slug())) . '">' . esc_html__('Open Vendor Availability', 'vms') . '</a>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="vms-add-grid vms-add-grid--stats">';
		echo '<div class="vms-add-card vms-add-stat"><span class="vms-add-label">' . esc_html__('Active requests', 'vms') . '</span><strong>' . esc_html((string) ($counts['active_requests'] ?? 0)) . '</strong></div>';
		echo '<div class="vms-add-card vms-add-stat"><span class="vms-add-label">' . esc_html__('Waiting on replies', 'vms') . '</span><strong>' . esc_html((string) ($counts['pending_recipients'] ?? 0)) . '</strong></div>';
		echo '<div class="vms-add-card vms-add-stat"><span class="vms-add-label">' . esc_html__('Available responses', 'vms') . '</span><strong>' . esc_html((string) ($counts['available_responses'] ?? 0)) . '</strong></div>';
		echo '<div class="vms-add-card vms-add-stat"><span class="vms-add-label">' . esc_html__('Closed requests', 'vms') . '</span><strong>' . esc_html((string) ($counts['closed_requests'] ?? 0)) . '</strong></div>';
		echo '</div>';

		echo '<div class="vms-add-card">';
		echo '<h2>' . esc_html__('Pending vendor interest', 'vms') . '</h2>';
		echo '<p class="vms-add-muted">' . esc_html__('These are vendor portal submissions that still need operator follow-up.', 'vms') . '</p>';
		if (empty($portal_interest_rows)) {
			echo '<p class="vms-add-muted">' . esc_html__('No pending vendor interest submissions are waiting right now.', 'vms') . '</p>';
		} else {
			echo '<table class="widefat striped vms-add-table"><thead><tr><th>' . esc_html__('Vendor', 'vms') . '</th><th>' . esc_html__('Vendor Type', 'vms') . '</th><th>' . esc_html__('Event', 'vms') . '</th><th>' . esc_html__('When', 'vms') . '</th><th>' . esc_html__('Action', 'vms') . '</th></tr></thead><tbody>';
			foreach ($portal_interest_rows as $row) {
				$assign_url = vms_add_dispatch_assignment_review_url((int) ($row['id'] ?? 0), (int) ($row['event_plan_id'] ?? 0));
				$dispatch_url = vms_add_dispatch_admin_url(array('event_plan_id' => (int) ($row['event_plan_id'] ?? 0)));
				echo '<tr>';
				echo '<td><strong>' . esc_html((string) ($row['vendor_title'] ?? '')) . '</strong><div class="description">' . vms_add_dispatch_source_pill('portal_interest') . '</div></td>';
				$target_label = sanitize_key((string) ($row['target_mode'] ?? 'secondary')) === 'primary'
					? __('Primary vendor', 'vms')
					: (string) vms_add_dispatch_type_label((string) ($row['vendor_type'] ?? ''));
				echo '<td>' . esc_html($target_label !== '' ? $target_label : __('Vendor', 'vms')) . '</td>';
				echo '<td>' . esc_html((string) ($row['event_title'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) (($row['responded_at'] ?? '') ?: ($row['created_at'] ?? ''))) . '</td>';
				echo '<td><a class="button button-small button-primary" href="' . esc_url($assign_url) . '">' . esc_html__('Review Assignment', 'vms') . '</a> <a class="button button-small" href="' . esc_url($dispatch_url) . '">' . esc_html__('Review in ADD', 'vms') . '</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		echo '<div class="vms-add-card">';
		echo '<h2>' . esc_html__('Open Vendor Needs', 'vms') . '</h2>';
		echo '<p class="vms-add-muted">' . esc_html__('Actionable open vendor needs are shown by default. Past, cancelled, full, over-capacity, and non-dispatchable Event Plans are hidden unless a diagnostic filter is enabled.', 'vms') . '</p>';
		echo '<form method="get" class="vms-add-filterbar">';
		echo '<input type="hidden" name="page" value="' . esc_attr(vms_add_dispatch_page_slug()) . '">';
		echo '<label><input type="checkbox" name="show_full_groups" value="1"' . checked(!empty($dashboard_filters['show_full_groups']), true, false) . '> ' . esc_html__('Show full groups', 'vms') . '</label>';
		echo '<label><input type="checkbox" name="show_over_capacity_groups" value="1"' . checked(!empty($dashboard_filters['show_over_capacity_groups']), true, false) . '> ' . esc_html__('Show over-capacity groups', 'vms') . '</label>';
		echo '<label><input type="checkbox" name="include_past_events" value="1"' . checked(!empty($dashboard_filters['include_past_events']), true, false) . '> ' . esc_html__('Include past events', 'vms') . '</label>';
		echo '<label><input type="checkbox" name="include_cancelled_events" value="1"' . checked(!empty($dashboard_filters['include_cancelled_events']), true, false) . '> ' . esc_html__('Include cancelled/archived diagnostics', 'vms') . '</label>';
		echo '<button class="button button-secondary" type="submit">' . esc_html__('Apply diagnostics', 'vms') . '</button>';
		echo '</form>';
		$need_rows_rendered = 0;
		if (!empty($vendor_need_events)) {
			echo '<table class="widefat striped vms-add-table"><thead><tr><th>' . esc_html__('Event', 'vms') . '</th><th>' . esc_html__('Date', 'vms') . '</th><th>' . esc_html__('Vendor Type', 'vms') . '</th><th>' . esc_html__('Mode', 'vms') . '</th><th>' . esc_html__('Filled', 'vms') . '</th><th>' . esc_html__('Needed/Target', 'vms') . '</th><th>' . esc_html__('Capacity', 'vms') . '</th><th>' . esc_html__('Open needed', 'vms') . '</th><th>' . esc_html__('Open capacity', 'vms') . '</th><th>' . esc_html__('Status', 'vms') . '</th><th>' . esc_html__('Action', 'vms') . '</th></tr></thead><tbody>';
			foreach ($vendor_need_events as $context) {
				$dispatch_url = vms_add_dispatch_admin_url(array('event_plan_id' => (int) ($context['event_plan_id'] ?? 0)));
				$need_rows = vms_add_dispatch_dashboard_need_rows((array) $context, $dashboard_filters);
				foreach ($need_rows as $group) {
					if (!is_array($group)) {
						continue;
					}
					$need_rows_rendered++;
					$capacity = ($group['capacity'] ?? null);
					$needed = ($group['needed_slots'] ?? ($group['target_slots'] ?? null));
					$open_needed = ($group['open_needed'] ?? ($group['open_spots'] ?? 0));
					$open_capacity = ($group['open_capacity'] ?? null);
					$mode = sanitize_key((string) ($group['mode'] ?? 'standard'));
					echo '<tr>';
					echo '<td><strong>' . esc_html((string) ($context['event_title'] ?? '')) . '</strong><div class="description">' . esc_html((string) ($context['venue_name'] ?? '')) . '</div></td>';
					echo '<td>' . esc_html(vms_add_dispatch_format_date((string) ($context['event_date'] ?? ''))) . '</td>';
					echo '<td>' . esc_html((string) ($group['label'] ?? __('Vendor', 'vms'))) . '</td>';
					echo '<td>' . esc_html($mode === 'primary' ? __('Primary', 'vms') : ($mode === 'market' ? __('Market', 'vms') : __('Standard', 'vms'))) . '</td>';
					echo '<td>' . esc_html((string) (int) ($group['filled_slots'] ?? 0)) . '</td>';
					echo '<td>' . esc_html($needed !== null && $needed !== '' ? (string) (int) $needed : __('Not set', 'vms')) . '</td>';
					echo '<td>' . esc_html($capacity !== null && $capacity !== '' ? (string) (int) $capacity : __('No slot limit', 'vms')) . '</td>';
					echo '<td>' . esc_html((string) max(0, (int) $open_needed)) . '</td>';
					echo '<td>' . esc_html($open_capacity !== null && $open_capacity !== '' ? (string) max(0, (int) $open_capacity) : __('Uncapped', 'vms')) . '</td>';
					echo '<td>' . vms_add_dispatch_status_pill((string) ($group['status'] ?? 'full')) . '</td>';
					echo '<td><a class="button button-small" href="' . esc_url($dispatch_url) . '">' . esc_html__('Review in ADD', 'vms') . '</a></td>';
					echo '</tr>';
				}
			}
			echo '</tbody></table>';
		}
		if ($need_rows_rendered <= 0) {
			echo '<p class="vms-add-muted">' . esc_html__('No open vendor needs found. Past, cancelled, full, and non-dispatchable Event Plans are hidden by default.', 'vms') . '</p>';
			if (!empty($excluded_need_events)) {
				echo '<details class="vms-add-muted"><summary>' . esc_html__('Excluded Event Plans checked', 'vms') . '</summary>';
				echo '<table class="widefat striped vms-add-table"><thead><tr><th>' . esc_html__('Event Plan', 'vms') . '</th><th>' . esc_html__('Date', 'vms') . '</th><th>' . esc_html__('Post status', 'vms') . '</th><th>' . esc_html__('Plan status', 'vms') . '</th><th>' . esc_html__('Reason excluded', 'vms') . '</th></tr></thead><tbody>';
				foreach ($excluded_need_events as $excluded) {
					echo '<tr>';
					echo '<td>' . esc_html((string) ($excluded['event_title'] ?? '')) . '</td>';
					echo '<td>' . esc_html((string) (($excluded['event_date'] ?? '') !== '' ? vms_add_dispatch_format_date((string) $excluded['event_date']) : __('Missing date', 'vms'))) . '</td>';
					echo '<td>' . esc_html((string) ($excluded['post_status'] ?? '')) . '</td>';
					echo '<td>' . esc_html((string) ($excluded['event_status'] ?? '')) . '</td>';
					echo '<td>' . esc_html((string) ($excluded['reason'] ?? '')) . '</td>';
					echo '</tr>';
				}
				echo '</tbody></table></details>';
			}
		}
		echo '</div>';

		echo '<div class="vms-add-grid vms-add-grid--dashboard">';
		echo '<div class="vms-add-card" data-vms-tour="add-dispatch.builder">';
		echo '<h2>' . esc_html__('Quick Start: open Event Plans that still need vendors', 'vms') . '</h2>';
		if (empty($open_events)) {
			echo '<p class="vms-add-muted">' . esc_html__('No open vendor needs found. Past, cancelled, full, and non-dispatchable Event Plans are hidden by default.', 'vms') . '</p>';
		} else {
			echo '<div class="vms-add-quickstart-list">';
			foreach ($open_events as $context) {
				$need_rows = vms_add_dispatch_dashboard_need_rows((array) $context, $dashboard_filters);
				$dispatch_url = vms_add_dispatch_admin_url(array('event_plan_id' => (int) ($context['event_plan_id'] ?? 0)));
				$edit_url = get_edit_post_link((int) ($context['event_plan_id'] ?? 0), '');
				echo '<div class="vms-add-quickstart-event">';
				echo '<div class="vms-add-quickstart-event__head"><strong>' . esc_html(sprintf(__('Event: %s', 'vms'), (string) ($context['event_title'] ?? ''))) . '</strong><span>' . esc_html(vms_add_dispatch_format_date((string) ($context['event_date'] ?? ''))) . '</span></div>';
				if (!empty($context['venue_name'])) {
					echo '<div class="description">' . esc_html((string) $context['venue_name']) . '</div>';
				}
				echo '<ul class="vms-add-quickstart-needs">';
				foreach ($need_rows as $need_row) {
					echo '<li>' . esc_html(vms_add_dispatch_need_row_summary_label((array) $need_row)) . '</li>';
				}
				echo '</ul>';
				echo '<div class="vms-add-quickstart-actions"><a class="button button-small button-primary" href="' . esc_url($dispatch_url) . '">' . esc_html__('Start ADD', 'vms') . '</a>';
				if ($edit_url) {
					echo ' <a class="button button-small" href="' . esc_url($edit_url) . '">' . esc_html__('Open Plan', 'vms') . '</a>';
				}
				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		echo '<div class="vms-add-card">';
		echo '<h2>' . esc_html__('Recent responses', 'vms') . '</h2>';
		if (empty($responses)) {
			echo '<p class="vms-add-muted">' . esc_html__('No vendors have responded through ADD yet.', 'vms') . '</p>';
		} else {
			echo '<table class="widefat striped vms-add-table"><thead><tr><th>' . esc_html__('Vendor', 'vms') . '</th><th>' . esc_html__('Vendor Type', 'vms') . '</th><th>' . esc_html__('Event', 'vms') . '</th><th>' . esc_html__('Source', 'vms') . '</th><th>' . esc_html__('Status', 'vms') . '</th><th>' . esc_html__('When', 'vms') . '</th></tr></thead><tbody>';
			foreach ($responses as $response) {
				echo '<tr>';
				echo '<td><strong>' . esc_html((string) ($response['vendor_title'] ?? '')) . '</strong></td>';
				$target_label = sanitize_key((string) ($response['target_mode'] ?? 'secondary')) === 'primary'
					? __('Primary vendor', 'vms')
					: (string) vms_add_dispatch_type_label((string) ($response['vendor_type'] ?? ''));
				echo '<td>' . esc_html($target_label !== '' ? $target_label : __('Vendor', 'vms')) . '</td>';
				echo '<td>' . esc_html((string) ($response['event_title'] ?? '')) . '</td>';
				echo '<td>' . vms_add_dispatch_source_pill((string) ($response['response_source'] ?? '')) . '</td>';
				echo '<td>' . vms_add_dispatch_status_pill((string) ($response['response_status'] ?? 'requested')) . '</td>';
				echo '<td>' . esc_html((string) (($response['responded_at'] ?? '') ?: ($response['created_at'] ?? ''))) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
		echo '</div>';

		vms_add_dispatch_render_request_history($requests);
	}
}

if (!function_exists('vms_add_dispatch_render_admin_page_content')) {
	function vms_add_dispatch_render_admin_page_content(): void
	{
		$assignment_response_id = isset($_GET['add_assignment_response_id']) ? absint((string) $_GET['add_assignment_response_id']) : 0;
		if ($assignment_response_id > 0) {
			check_admin_referer('vms_add_dispatch_assignment_review_' . $assignment_response_id);
			vms_add_dispatch_render_assignment_review($assignment_response_id);
			return;
		}

		$event_plan_id = isset($_GET['event_plan_id']) ? absint((string) $_GET['event_plan_id']) : 0;
		$context = $event_plan_id > 0 ? vms_add_dispatch_get_event_plan_context($event_plan_id) : null;

		if (!$context) {
			vms_add_dispatch_render_dashboard_home();
			return;
		}

		$builder_args = vms_add_dispatch_parse_builder_args($_GET, $context);
		$recipients = vms_add_dispatch_collect_eligible_recipients($context, $builder_args);

		echo '<div class="vms-add-card" data-vms-tour="add-dispatch.help">';
		echo '<h2>' . esc_html((string) ($context['event_title'] ?? '')) . '</h2>';
		echo '<div class="vms-add-grid vms-add-grid--meta">';
		echo '<div><span class="vms-add-label">' . esc_html__('Date', 'vms') . '</span><strong>' . esc_html(vms_add_dispatch_format_date((string) ($context['event_date'] ?? ''))) . '</strong></div>';
		echo '<div><span class="vms-add-label">' . esc_html__('Venue', 'vms') . '</span><strong>' . esc_html((string) ($context['venue_name'] ?? '')) . '</strong></div>';
		echo '</div>';
		echo '</div>';

		vms_add_dispatch_render_request_builder($context, $builder_args, $recipients);
		vms_add_dispatch_render_request_history(vms_add_dispatch_get_requests_for_event_plan((int) $context['event_plan_id'], 12), (int) $context['event_plan_id']);
	}
}

if (!function_exists('vms_add_dispatch_render_admin_page')) {
	function vms_add_dispatch_render_admin_page(): void
	{
		$help_button = '<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.add_dispatch.basics" data-vms-tour="add-dispatch.help">' . esc_html__('Start Guided Tour', 'vms') . '</button>';
		if (function_exists('vms_render_help_button')) {
			$help_button = vms_render_help_button(array(
				'tour_id' => 'vms.add_dispatch.basics',
				'anchor' => 'add-dispatch.help',
				'label' => __('Start Guided Tour', 'vms'),
				'class' => 'button-secondary',
			));
		}

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(
				array(
					'title' => __('Availability & Date Dispatch', 'vms'),
					'subtitle' => __('Universal vendor outreach lives here. Phase 1 supports one saved Event Plan at a time, with secure YES or NO responses and one-click assignment follow-up.', 'vms'),
					'shell_id' => 'vms-add-dispatch',
					'content_class' => 'vms-add-dispatch-content',
					'actions_html' => '<div class="vms-add-page-actions">' . $help_button . '</div>',
				),
				'vms_add_dispatch_render_admin_page_content'
			);
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__('Availability & Date Dispatch', 'vms') . '</h1>';
		vms_add_dispatch_render_admin_page_content();
		echo '</div>';
	}
}

if (!function_exists('vms_add_dispatch_handle_send_request')) {
	function vms_add_dispatch_handle_send_request(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'vms'));
		}

		check_admin_referer('vms_add_dispatch_send_request', 'vms_add_dispatch_nonce');

		$event_plan_id = isset($_POST['event_plan_id']) ? absint((string) $_POST['event_plan_id']) : 0;
		$context = vms_add_dispatch_get_event_plan_context($event_plan_id);
		if (!$context) {
			if (function_exists('vms_add_admin_notice')) {
				vms_add_admin_notice(__('Save the Event Plan first before using ADD.', 'vms'), 'error');
			}
			wp_safe_redirect(vms_add_dispatch_admin_url());
			exit;
		}

		$builder_args = vms_add_dispatch_parse_builder_args($_POST, $context);
		$eligible = vms_add_dispatch_collect_eligible_recipients($context, $builder_args);
		$eligible_map = array();
		foreach ($eligible as $recipient) {
			$eligible_map[(int) ($recipient['vendor_id'] ?? 0)] = $recipient;
		}

		$selected_vendor_ids = vms_add_dispatch_selected_vendor_ids($_POST);
		$selected_recipients = array();
		foreach ($selected_vendor_ids as $vendor_id) {
			if (isset($eligible_map[$vendor_id])) {
				$selected_recipients[] = $eligible_map[$vendor_id];
			}
		}

		$created = vms_add_dispatch_create_request($event_plan_id, $builder_args, $selected_recipients);
		if (is_wp_error($created)) {
			if (function_exists('vms_add_admin_notice')) {
				vms_add_admin_notice($created->get_error_message(), 'error');
			}
			wp_safe_redirect(vms_add_dispatch_admin_url(array('event_plan_id' => $event_plan_id)));
			exit;
		}

		$request = (array) ($created['request'] ?? array());
		$responses = (array) ($created['responses'] ?? array());
		$sent = 0;
		$failed = 0;
		foreach ($responses as $response) {
			$result = vms_add_dispatch_send_response_email($request, (array) $response, $context);
			if (!empty($result['success'])) {
				$sent++;
			} else {
				$failed++;
			}
		}

		if (function_exists('vms_add_admin_notice')) {
			if ($sent > 0) {
				vms_add_admin_notice(sprintf(__('ADD request sent to %d vendor(s).', 'vms'), $sent), 'success');
			}
			if ($failed > 0) {
				vms_add_admin_notice(sprintf(__('ADD could not email %d vendor(s). Check notification logs and recipient email addresses.', 'vms'), $failed), 'warning');
			}
		}

		wp_safe_redirect(vms_add_dispatch_admin_url(array('event_plan_id' => $event_plan_id)));
		exit;
	}
}
add_action('admin_post_vms_add_dispatch_send_request', 'vms_add_dispatch_handle_send_request');

if (!function_exists('vms_add_dispatch_handle_assign_vendor')) {
	function vms_add_dispatch_handle_assign_vendor(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'vms'));
		}

		$response_id = isset($_GET['response_id']) ? absint((string) $_GET['response_id']) : 0;
		check_admin_referer('vms_add_dispatch_assign_vendor_' . $response_id);
		$event_plan_id = isset($_GET['event_plan_id']) ? absint((string) $_GET['event_plan_id']) : 0;
		wp_safe_redirect(vms_add_dispatch_assignment_review_url($response_id, $event_plan_id));
		exit;
	}
}
add_action('admin_post_vms_add_dispatch_assign_vendor', 'vms_add_dispatch_handle_assign_vendor');

if (!function_exists('vms_add_dispatch_handle_confirm_assignment')) {
	function vms_add_dispatch_handle_confirm_assignment(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'vms'));
		}

		$response_id = isset($_POST['response_id']) ? absint((string) $_POST['response_id']) : 0;
		check_admin_referer('vms_add_dispatch_confirm_assignment_' . $response_id, 'vms_add_dispatch_assignment_nonce');
		$event_plan_id = isset($_POST['event_plan_id']) ? absint((string) $_POST['event_plan_id']) : 0;
		$target_type = isset($_POST['target_type']) ? sanitize_key((string) wp_unslash($_POST['target_type'])) : '';
		$allow_over_capacity = !empty($_POST['allow_over_capacity']);

		$result = vms_add_dispatch_apply_assignment_review($response_id, $target_type, $allow_over_capacity);
		if (function_exists('vms_add_admin_notice')) {
			if (is_wp_error($result)) {
				vms_add_admin_notice($result->get_error_message(), 'error');
			} else {
				vms_add_admin_notice(__('Vendor assigned from ADD response.', 'vms'), 'success');
			}
		}
		if (is_wp_error($result)) {
			wp_safe_redirect(vms_add_dispatch_assignment_review_url($response_id, $event_plan_id));
			exit;
		}

		wp_safe_redirect(vms_add_dispatch_admin_url(array('event_plan_id' => $event_plan_id)));
		exit;
	}
}
add_action('admin_post_vms_add_dispatch_confirm_assignment', 'vms_add_dispatch_handle_confirm_assignment');

if (!function_exists('vms_add_dispatch_handle_close_request')) {
	function vms_add_dispatch_handle_close_request(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'vms'));
		}

		$request_id = isset($_GET['request_id']) ? absint((string) $_GET['request_id']) : 0;
		check_admin_referer('vms_add_dispatch_close_request_' . $request_id);
		$result = vms_add_dispatch_close_request($request_id);
		if (function_exists('vms_add_admin_notice')) {
			if (is_wp_error($result)) {
				vms_add_admin_notice($result->get_error_message(), 'error');
			} else {
				vms_add_admin_notice(__('ADD request closed.', 'vms'), 'success');
			}
		}

		$event_plan_id = isset($_GET['event_plan_id']) ? absint((string) $_GET['event_plan_id']) : 0;
		wp_safe_redirect(vms_add_dispatch_admin_url(array('event_plan_id' => $event_plan_id)));
		exit;
	}
}
add_action('admin_post_vms_add_dispatch_close_request', 'vms_add_dispatch_handle_close_request');

if (!function_exists('vms_add_dispatch_handle_resend_response')) {
	function vms_add_dispatch_handle_resend_response(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'vms'));
		}

		$response_id = isset($_GET['response_id']) ? absint((string) $_GET['response_id']) : 0;
		check_admin_referer('vms_add_dispatch_resend_response_' . $response_id);

		$prepared = vms_add_dispatch_prepare_resend($response_id);
		if (is_wp_error($prepared)) {
			if (function_exists('vms_add_admin_notice')) {
				vms_add_admin_notice($prepared->get_error_message(), 'error');
			}
		} else {
			$result = vms_add_dispatch_send_response_email(
				(array) ($prepared['request'] ?? array()),
				(array) ($prepared['response'] ?? array()),
				(array) ($prepared['context'] ?? array())
			);
			if (function_exists('vms_add_admin_notice')) {
				if (!empty($result['success'])) {
					vms_add_admin_notice(__('ADD request resent.', 'vms'), 'success');
				} else {
					vms_add_admin_notice(__('The ADD email could not be resent. Check the notification log for details.', 'vms'), 'error');
				}
			}
		}

		$event_plan_id = isset($_GET['event_plan_id']) ? absint((string) $_GET['event_plan_id']) : 0;
		wp_safe_redirect(vms_add_dispatch_admin_url(array('event_plan_id' => $event_plan_id)));
		exit;
	}
}
add_action('admin_post_vms_add_dispatch_resend_response', 'vms_add_dispatch_handle_resend_response');
