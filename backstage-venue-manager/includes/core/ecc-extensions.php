<?php
defined('ABSPATH') || exit;

/**
 * Event Command Center extension registry for first-party and companion modules.
 */

if (!function_exists('bvmgr_ecc_extension_registry')) {
	/** @return array<string,array<string,mixed>> */
	function &bvmgr_ecc_extension_registry(): array
	{
		static $registry = array();
		return $registry;
	}
}

if (!function_exists('bvmgr_register_ecc_extension')) {
	function bvmgr_register_ecc_extension(array $extension): bool
	{
		$id = sanitize_key((string) ($extension['id'] ?? ''));
		$title = sanitize_text_field((string) ($extension['title'] ?? ''));
		if ($id === '' || $title === '') {
			return false;
		}

		$registry = &bvmgr_ecc_extension_registry();
		if (isset($registry[$id])) {
			return false;
		}

		$callbacks = array('summary_callback', 'render_callback', 'assets_callback', 'permission_callback', 'empty_callback');
		foreach ($callbacks as $callback_key) {
			if (isset($extension[$callback_key]) && $extension[$callback_key] !== null && !is_callable($extension[$callback_key])) {
				return false;
			}
		}

		$registry[$id] = array(
			'id' => $id,
			'title' => $title,
			'order' => (int) ($extension['order'] ?? 100),
			'capability' => sanitize_key((string) ($extension['capability'] ?? 'manage_options')),
			'source' => sanitize_key((string) ($extension['source'] ?? '')),
			'summary_callback' => $extension['summary_callback'] ?? null,
			'render_callback' => $extension['render_callback'] ?? null,
			'assets_callback' => $extension['assets_callback'] ?? null,
			'permission_callback' => $extension['permission_callback'] ?? null,
			'empty_callback' => $extension['empty_callback'] ?? null,
			'empty_message' => sanitize_text_field((string) ($extension['empty_message'] ?? __('Nothing to show for this event.', 'backstage-venue-manager'))),
			'error_message' => sanitize_text_field((string) ($extension['error_message'] ?? __('This Event Command Center section is temporarily unavailable.', 'backstage-venue-manager'))),
		);
		return true;
	}
}

if (!function_exists('bvmgr_ecc_boot_extension_registry')) {
	function bvmgr_ecc_boot_extension_registry(): void
	{
		static $booted = false;
		if ($booted) {
			return;
		}
		$booted = true;

		/** Register Event Command Center extensions. */
		do_action('bvmgr_ecc_register_extensions');
	}
}

if (!function_exists('bvmgr_ecc_extension_context')) {
	/** @return array<string,mixed> */
	function bvmgr_ecc_extension_context(int $event_plan_id, array $payload = array()): array
	{
		$event_plan_id = absint($event_plan_id);
		return array(
			'event_plan_id' => $event_plan_id,
			'venue_id' => $event_plan_id > 0 ? absint(get_post_meta($event_plan_id, '_vms_venue_id', true)) : 0,
			'payload' => $payload,
		);
	}
}

if (!function_exists('bvmgr_ecc_extension_is_visible')) {
	function bvmgr_ecc_extension_is_visible(array $extension, array $context): bool
	{
		$capability = sanitize_key((string) ($extension['capability'] ?? 'manage_options'));
		$allowed = $capability !== '' && (
			function_exists('bvmgr_current_user_can_operate_context')
				? bvmgr_current_user_can_operate_context(
					$capability,
					absint($context['event_plan_id'] ?? 0),
					absint($context['venue_id'] ?? 0),
					array('surface' => 'event_command_center', 'extension_id' => (string) ($extension['id'] ?? ''))
				)
				: current_user_can($capability)
		);
		if (!$allowed) {
			return false;
		}

		$permission_callback = $extension['permission_callback'] ?? null;
		if (!is_callable($permission_callback)) {
			return true;
		}
		try {
			return (bool) call_user_func($permission_callback, $context, $extension);
		} catch (Throwable $throwable) {
			unset($throwable);
			return false;
		}
	}
}

if (!function_exists('bvmgr_ecc_get_extensions')) {
	/** @return array<int,array<string,mixed>> */
	function bvmgr_ecc_get_extensions(array $context = array(), bool $visible_only = true): array
	{
		bvmgr_ecc_boot_extension_registry();
		$registry = bvmgr_ecc_extension_registry();
		/**
		 * Filter normalized Event Command Center extensions.
		 *
		 * @param array<string,array<string,mixed>> $registry Registered extensions keyed by ID.
		 * @param array                            $context  Current ECC context.
		 */
		$filtered = apply_filters('bvmgr_ecc_extensions', $registry, $context);
		$extensions = array_values(array_filter(
			is_array($filtered) ? $filtered : $registry,
			static fn($extension): bool => is_array($extension)
		));
		if ($visible_only) {
			$extensions = array_values(array_filter($extensions, static function ($extension) use ($context): bool {
				return is_array($extension) && bvmgr_ecc_extension_is_visible($extension, $context);
			}));
		}
		usort($extensions, static function (array $left, array $right): int {
			return ((int) ($left['order'] ?? 100) <=> (int) ($right['order'] ?? 100))
				?: strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
		});
		return $extensions;
	}
}

if (!function_exists('bvmgr_ecc_render_extension_metric')) {
	function bvmgr_ecc_render_extension_metric(string $label, string $value, string $sub = ''): void
	{
		if (function_exists('bvmgr_event_command_center_render_metric')) {
			bvmgr_event_command_center_render_metric($label, $value, $sub);
			return;
		}
		if (function_exists('vms_event_command_center_render_metric')) {
			vms_event_command_center_render_metric($label, $value, $sub);
			return;
		}

		echo '<div class="vms-cc-metric">';
		echo '<span class="vms-cc-metric__label">' . esc_html($label) . '</span>';
		echo '<strong class="vms-cc-metric__value">' . esc_html($value) . '</strong>';
		if ($sub !== '') {
			echo '<span class="vms-cc-metric__sub">' . esc_html($sub) . '</span>';
		}
		echo '</div>';
	}
}

if (!function_exists('bvmgr_ecc_render_registered_extensions')) {
	function bvmgr_ecc_render_registered_extensions(int $event_plan_id, array $payload = array()): void
	{
		$context = bvmgr_ecc_extension_context($event_plan_id, $payload);
		$extensions = bvmgr_ecc_get_extensions($context, true);
		if (empty($extensions)) {
			return;
		}

		$metrics = array();
		foreach ($extensions as $extension) {
			$callback = $extension['summary_callback'] ?? null;
			if (!is_callable($callback)) {
				continue;
			}
			try {
				$rows = call_user_func($callback, $context, $extension);
				if (is_array($rows) && isset($rows['label'])) {
					$rows = array($rows);
				}
				foreach (is_array($rows) ? $rows : array() as $row) {
					if (is_array($row) && trim((string) ($row['label'] ?? '')) !== '') {
						$metrics[] = $row;
					}
				}
			} catch (Throwable $throwable) {
				unset($throwable);
			}
		}

		if (!empty($metrics)) {
			echo '<section class="vms-cc-card vms-cc-extension-summary">';
			echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Extension Summary', 'backstage-venue-manager') . '</h3></div>';
			echo '<div class="vms-cc-metrics">';
			foreach ($metrics as $metric) {
				bvmgr_ecc_render_extension_metric(
					sanitize_text_field((string) ($metric['label'] ?? '')),
					sanitize_text_field((string) ($metric['value'] ?? '')),
					sanitize_text_field((string) ($metric['sub'] ?? ''))
				);
			}
			echo '</div></section>';
		}

		$panels = array_values(array_filter($extensions, static fn(array $extension): bool => is_callable($extension['render_callback'] ?? null)));
		if (empty($panels)) {
			return;
		}

		echo '<div class="vms-cc-grid vms-cc-grid--extensions">';
		foreach ($panels as $extension) {
			$render_callback = $extension['render_callback'] ?? null;
			echo '<section class="vms-cc-card vms-cc-extension" data-bvmgr-ecc-extension="' . esc_attr((string) ($extension['id'] ?? '')) . '">';
			echo '<div class="vms-cc-card__header"><h3>' . esc_html((string) ($extension['title'] ?? '')) . '</h3></div>';
			try {
				$empty_callback = $extension['empty_callback'] ?? null;
				if (is_callable($empty_callback) && (bool) call_user_func($empty_callback, $context, $extension)) {
					echo '<p class="vms-cc-empty">' . esc_html((string) ($extension['empty_message'] ?? '')) . '</p>';
				} else {
					call_user_func($render_callback, $context, $extension);
				}
			} catch (Throwable $throwable) {
				unset($throwable);
				echo '<p class="vms-cc-empty">' . esc_html((string) ($extension['error_message'] ?? '')) . '</p>';
			}
			echo '</section>';
		}
		echo '</div>';
	}
}

if (!function_exists('bvmgr_ecc_enqueue_registered_extension_assets')) {
	function bvmgr_ecc_enqueue_registered_extension_assets(): void
	{
		$page = function_exists('bvmgr_request_read_key')
			? bvmgr_request_read_key($_GET, 'page')
			: ((isset($_GET['page']) && !is_array($_GET['page'])) ? sanitize_key((string) wp_unslash($_GET['page'])) : ''); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing scopes registered ECC assets.
		$expected_page = function_exists('bvmgr_event_command_center_page_slug')
			? bvmgr_event_command_center_page_slug()
			: (function_exists('vms_event_command_center_page_slug') ? vms_event_command_center_page_slug() : 'vms-event-command-center');
		if ($page !== sanitize_key((string) $expected_page)) {
			return;
		}
		$plan_id = function_exists('bvmgr_request_read_absint')
			? bvmgr_request_read_absint($_GET, 'plan_id')
			: ((isset($_GET['plan_id']) && !is_array($_GET['plan_id'])) ? absint(wp_unslash($_GET['plan_id'])) : 0); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Event Plan selection scopes registered ECC assets.
		$context = bvmgr_ecc_extension_context($plan_id);
		foreach (bvmgr_ecc_get_extensions($context, true) as $extension) {
			$callback = $extension['assets_callback'] ?? null;
			if (is_callable($callback)) {
				try {
					call_user_func($callback, $context, $extension);
				} catch (Throwable $throwable) {
					unset($throwable);
				}
			}
		}
	}
}
add_action('admin_enqueue_scripts', 'bvmgr_ecc_enqueue_registered_extension_assets', 60);
