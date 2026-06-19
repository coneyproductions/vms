<?php
defined('ABSPATH') || exit;

/**
 * Vendor meta registry (DERIVED).
 *
 * Single source of truth: vms_vendor_schema()
 *
 * Safety rules:
 * - Never register meta keys for fields marked persist=false or sensitive=true.
 * - All meta registration must flow from the master schema to prevent drift.
 */
final class VMS_Vendor_Meta_Registry {

	/**
	 * Build the WordPress meta registration array for vendor meta keys.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get(): array {

		if (!function_exists('vms_vendor_schema')) {
			// Hard fail would be fine too, but returning an empty array avoids white screens
			// if schema load order is temporarily wrong. Up to you.
			return [];
		}

		$schema = vms_vendor_schema();
		$meta   = [];

		foreach ($schema as $field_key => $def) {

			$storage = $def['storage'] ?? '';
			if ($storage !== 'meta') {
				continue;
			}

			$meta_key = $def['meta_key'] ?? '';
			if (!$meta_key) {
				continue;
			}

			// Safety: never register sensitive or non-persisted fields.
			if (($def['persist'] ?? true) === false) {
				continue;
			}
			if (($def['sensitive'] ?? false) === true) {
				continue;
			}

			$type = self::map_schema_type_to_meta_type($def['type'] ?? 'string');

			$meta[$meta_key] = [
				'label'        => $def['label'] ?? $field_key,
				'type'         => $type,
				'single'       => true,
				'show_in_rest' => false,
				'sanitize_cb'  => self::pick_sanitizer($def['type'] ?? 'string'),
			];
		}

		/**
		 * Allow extensions to add/modify registrations while still keeping schema
		 * as the authoritative source.
		 */
		return apply_filters('vms_vendor_meta_registry', $meta);
	}

	private static function map_schema_type_to_meta_type(string $schema_type): string {
		$schema_type = strtolower(trim($schema_type));

		// WP meta types are basically: string, boolean, integer, number, array, object
		if (in_array($schema_type, ['bool', 'boolean'], true)) {
			return 'boolean';
		}
		if (in_array($schema_type, ['int', 'integer'], true)) {
			return 'integer';
		}
		if (in_array($schema_type, ['float', 'double', 'number', 'decimal'], true)) {
			return 'number';
		}
		if (in_array($schema_type, ['array'], true)) {
			return 'array';
		}
		if (in_array($schema_type, ['object'], true)) {
			return 'object';
		}

		// email, url, string, date, etc → string
		return 'string';
	}

	private static function pick_sanitizer(string $schema_type): string {
		$schema_type = strtolower(trim($schema_type));

		if ($schema_type === 'email') {
			return 'sanitize_email';
		}
		if ($schema_type === 'url') {
			return 'esc_url_raw';
		}
		if (in_array($schema_type, ['int', 'integer'], true)) {
			return 'absint';
		}
		if (in_array($schema_type, ['bool', 'boolean'], true)) {
			// sanitize_text_field is fine if you store "0/1", but this is cleaner.
			return 'rest_sanitize_boolean';
		}

		return 'sanitize_text_field';
	}
}
