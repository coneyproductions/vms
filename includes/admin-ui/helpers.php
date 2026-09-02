<?php

defined('ABSPATH') || exit;

if (!function_exists('bvmgr_admin_ui_asset_version')) {
	function bvmgr_admin_ui_asset_version(): string
	{
		if (function_exists('bvmgr_asset_version')) {
			$version = (string) bvmgr_asset_version();
			if ($version !== '') {
				return $version;
			}
		}

		return defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '';
	}
}

if (!function_exists('bvmgr_admin_ui_page_url')) {
	function bvmgr_admin_ui_page_url(string $slug, array $args = array()): string
	{
		$url = admin_url('admin.php?page=' . rawurlencode($slug));
		if (!empty($args)) {
			$url = add_query_arg($args, $url);
		}
		return $url;
	}
}

if (!function_exists('bvmgr_admin_ui_post_type_url')) {
	function bvmgr_admin_ui_post_type_url(string $post_type, array $args = array()): string
	{
		$params = array_merge(array('post_type' => $post_type), $args);
		return add_query_arg($params, admin_url('edit.php'));
	}
}
