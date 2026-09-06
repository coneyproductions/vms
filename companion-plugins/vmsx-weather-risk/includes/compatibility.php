<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Compatibility')) {
	class VMSX_Weather_Risk_Compatibility {
		public static function core_function(string $legacy_function): string
		{
			$canonical_function = preg_replace('/^vms_/', 'bvmgr_', $legacy_function);
			if (is_string($canonical_function) && function_exists($canonical_function)) {
				return $canonical_function;
			}
			if (function_exists($legacy_function)) {
				return $legacy_function;
			}

			return '';
		}

		public static function core_constant(string $canonical_constant, string $legacy_constant, $fallback = null)
		{
			if (defined($canonical_constant)) {
				return constant($canonical_constant);
			}
			if (defined($legacy_constant)) {
				return constant($legacy_constant);
			}

			return $fallback;
		}

		public static function core_version(): string
		{
			return (string) self::core_constant('BVMGR_VERSION', 'VMS_VERSION', '');
		}

		public static function is_vms_present(): bool
		{
			return self::core_constant('BVMGR_PLUGIN_PATH', 'VMS_PLUGIN_PATH', '') !== ''
				|| self::core_version() !== ''
				|| self::core_function('vms_register_module') !== '';
		}

		public static function has_minimum_vms_version(): bool
		{
			$core_version = self::core_version();
			if ($core_version === '') {
				return false;
			}

			return version_compare($core_version, VMSX_WR_MIN_VMS_VERSION, '>=');
		}

		public static function is_ready(): bool
		{
			return self::is_vms_present() && self::has_minimum_vms_version();
		}

		public static function issues(): array
		{
			$issues = array();

			if (!self::is_vms_present()) {
				$issues[] = sprintf(
					/* translators: %s minimum version */
					__('Show Risk Advisor requires the VMS core plugin. Install/activate VMS first.', 'vmsx-weather-risk')
				);
			} elseif (!self::has_minimum_vms_version()) {
				$issues[] = sprintf(
					/* translators: %s minimum VMS version */
					__('Show Risk Advisor requires VMS %s or newer.', 'vmsx-weather-risk'),
					VMSX_WR_MIN_VMS_VERSION
				);
			}

			return $issues;
		}

		public static function render_admin_notice(): void
		{
			if (!is_admin()) {
				return;
			}
			if (!current_user_can('manage_options')) {
				return;
			}

			$issues = self::issues();
			if (empty($issues)) {
				return;
			}

			echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Show Risk Advisor is installed but not active.', 'vmsx-weather-risk') . '</strong></p><ul style="margin-left:1.2em;list-style:disc;">';
			foreach ($issues as $issue) {
				echo '<li>' . esc_html((string) $issue) . '</li>';
			}
			echo '</ul></div>';
		}
	}
}
