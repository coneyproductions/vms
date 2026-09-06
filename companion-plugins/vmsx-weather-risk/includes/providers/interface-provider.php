<?php

defined('ABSPATH') || exit;

if (!interface_exists('VMSX_Weather_Risk_Provider_Interface')) {
	interface VMSX_Weather_Risk_Provider_Interface {
		public function slug(): string;

		public function name(): string;

		public function is_enabled(array $settings): bool;

		public function fetch(array $location, array $window, array $settings): array;
	}
}
