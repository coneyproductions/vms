<?php

defined('ABSPATH') || exit;

require_once VMSX_WR_PATH . 'includes/admin/menu.php';
require_once VMSX_WR_PATH . 'includes/admin/page-settings.php';
require_once VMSX_WR_PATH . 'includes/admin/page-event-risk.php';
require_once VMSX_WR_PATH . 'includes/admin/metabox-event-plan.php';
require_once VMSX_WR_PATH . 'includes/admin/assets.php';

if (!class_exists('VMSX_Weather_Risk_Admin')) {
	class VMSX_Weather_Risk_Admin {
		public static function init(): void
		{
			VMSX_Weather_Risk_Admin_Menu::init();
			VMSX_Weather_Risk_Admin_Assets::init();
			VMSX_Weather_Risk_Admin_Event_Plan::init();
		}
	}
}
