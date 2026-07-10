<?php

defined('ABSPATH') || exit;

if (!defined('VMS_TOURS_VERSION')) {
	define('VMS_TOURS_VERSION', '1.0.0');
}

if (!defined('VMS_TOURS_DEBUG')) {
	define('VMS_TOURS_DEBUG', false);
}

require_once __DIR__ . '/class-vms-tours-screen.php';
require_once __DIR__ . '/class-vms-tours-storage.php';
require_once __DIR__ . '/class-vms-tours-registry.php';
require_once __DIR__ . '/class-vms-tours-compat.php';
require_once __DIR__ . '/class-vms-tours-admin.php';
require_once __DIR__ . '/class-vms-tours-service.php';

if (!function_exists('vms_tours_register')) {
	/**
	 * @param array<string,mixed> $tour_def
	 */
	function vms_tours_register(array $tour_def): void
	{
		VMS_Tours_Service::instance()->register_tour($tour_def);
	}
}

if (!function_exists('vms_enqueue_tour_assets')) {
	function vms_enqueue_tour_assets(): void
	{
		VMS_Tours_Service::instance()->enqueue_admin_assets();
	}
}

if (!function_exists('vms_tours_sanitize_anchor_token')) {
	function vms_tours_sanitize_anchor_token(string $anchor): string
	{
		$anchor = strtolower(trim($anchor));
		if ($anchor === '') {
			return '';
		}

		$sanitized = preg_replace('/[^a-z0-9._\-]/', '', $anchor);
		return is_string($sanitized) ? $sanitized : '';
	}
}

if (!function_exists('vms_render_help_button')) {
	/**
	 * @param array<string,mixed> $args
	 */
	function vms_render_help_button(array $args = array()): string
	{
		$tour_id = isset($args['tour_id']) ? (string) $args['tour_id'] : '';
		$tour_id = strtolower(trim($tour_id));
		$tour_id = preg_replace('/[^a-z0-9._\-]/', '', $tour_id);
		if (!is_string($tour_id)) {
			$tour_id = '';
		}

		$anchor = isset($args['anchor']) ? vms_tours_sanitize_anchor_token((string) $args['anchor']) : '';
		$label = isset($args['label']) ? sanitize_text_field((string) $args['label']) : vms_i18n_runtime('Help', 'backstage-venue-manager');
		$class = isset($args['class']) ? sanitize_html_class((string) $args['class']) : '';

		$classes = trim('button button-secondary vms-tour-help-trigger ' . $class);
		$attrs = ' type="button" class="' . esc_attr($classes) . '"';
		if ($tour_id !== '') {
			$attrs .= ' data-vms-tour-start="' . esc_attr($tour_id) . '"';
		} else {
			$attrs .= ' data-vms-help-open="1"';
		}
		if ($anchor !== '') {
			$attrs .= ' data-vms-tour="' . esc_attr($anchor) . '"';
		}

		return '<button' . $attrs . '>' . esc_html($label) . '</button>';
	}
}

add_action('plugins_loaded', static function (): void {
	VMS_Tours_Service::instance();
}, 20);
