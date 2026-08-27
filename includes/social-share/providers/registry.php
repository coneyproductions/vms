<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_social_resolve_provider_key')) {
	function vms_social_resolve_provider_key(string $platform): string
	{
		$platform = sanitize_key($platform);
		if (in_array($platform, array('facebook', 'instagram'), true)) {
			return 'meta';
		}
		if ($platform === 'linkedin') {
			return 'linkedin';
		}
		return $platform;
	}
}

if (!function_exists('bvmgr_social_get_providers')) {
	/**
	 * @return array<string,BVMGR_Social_Provider_Interface>
	 */
	function bvmgr_social_get_providers(): array
	{
		static $providers = null;
		if (is_array($providers)) {
			return $providers;
		}

		$defaults = array(
			'mock' => new BVMGR_Social_Provider_Mock(),
			'webhook' => new BVMGR_Social_Provider_Webhook(),
			'meta' => new BVMGR_Social_Provider_Meta(),
			'linkedin' => new BVMGR_Social_Provider_LinkedIn(),
		);

		$registered = apply_filters('vms_social_register_providers', $defaults);
		$registered = is_array($registered) ? $registered : array();

		$providers = array();
		foreach ($registered as $key => $provider) {
			if (!($provider instanceof BVMGR_Social_Provider_Interface)) {
				continue;
			}
			$provider_key = sanitize_key((string) $key);
			if ($provider_key === '') {
				$provider_key = sanitize_key($provider->get_platform_key());
			}
			if ($provider_key === '') {
				continue;
			}
			$providers[$provider_key] = $provider;
		}

		ksort($providers);
		return $providers;
	}
}

if (!function_exists('bvmgr_social_get_provider')) {
	function bvmgr_social_get_provider(string $platform): ?BVMGR_Social_Provider_Interface
	{
		$key = vms_social_resolve_provider_key($platform);
		$providers = bvmgr_social_get_providers();
		return isset($providers[$key]) ? $providers[$key] : null;
	}
}
